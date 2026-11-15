<?php

/*
|--------------------------------------------------------------------------
| Case-sensitivity nama halaman Inertia
|--------------------------------------------------------------------------
|
| Windows tidak membedakan huruf besar/kecil pada nama berkas, Linux (runner CI
| dan server produksi) membedakannya. Bug nyata pernah lolos ke CI karena
| `config/inertia.php` menunjuk `resources/js/pages` (huruf kecil) sedangkan
| foldernya `resources/js/Pages`. Di mesin pengembang semuanya tampak benar;
| di CI seluruh `assertInertia(...->component(...))` gagal dengan pesan
| "Inertia page component file [...] does not exist".
|
| Tes ini membandingkan nama secara **case-sensitive** terhadap isi disk,
| sehingga perbedaan itu terlihat di sistem operasi mana pun.
|
*/

/**
 * Daftar path halaman relatif terhadap `resources/js/Pages`, diambil dari disk
 * apa adanya (bukan lewat pencarian yang toleran huruf).
 *
 * @return array<int, string>
 */
function pageFilesOnDisk(): array
{
    $root = resource_path('js/Pages');
    $files = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        if ($file->getExtension() !== 'vue') {
            continue;
        }

        $relative = str_replace($root.DIRECTORY_SEPARATOR, '', $file->getPathname());

        $files[] = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
    }

    sort($files);

    return $files;
}

test('the inertia page path is spelled exactly like the directory on disk', function (): void {
    $configured = config('inertia.pages.paths');

    expect($configured)->toBeArray()->not->toBeEmpty();

    foreach ($configured as $path) {
        // Perbandingan langsung dengan disk: `is_dir()` akan lolos di Windows
        // meski hurufnya berbeda, jadi nama folder dibandingkan persis.
        $actual = basename($path);

        $parent = dirname($path);
        $siblings = is_dir($parent) ? scandir($parent) : [];

        expect(in_array($actual, $siblings, true))->toBeTrue(
            "Folder halaman [{$actual}] tidak ada dengan ejaan persis itu di [{$parent}]. ".
            'Linux membedakan huruf besar/kecil, Windows tidak.'
        );
    }
});

test('every inertia page component resolves to a file with the exact name', function (): void {
    $files = pageFilesOnDisk();

    expect($files)->not->toBeEmpty();

    /*
     * Nama komponen diambil dari kode sumber, bukan dari daftar manual, agar
     * halaman baru otomatis ikut diperiksa.
     */
    $components = [];

    foreach (File::allFiles(app_path()) as $file) {
        $contents = File::get($file->getPathname());

        if (preg_match_all("/Inertia::render\(\s*'([^']+)'/", $contents, $matches) === false) {
            continue;
        }

        foreach ($matches[1] as $component) {
            $components[$component] = $file->getRelativePathname();
        }
    }

    expect($components)->not->toBeEmpty();

    foreach ($components as $component => $source) {
        $expected = $component.'.vue';

        expect(in_array($expected, $files, true))->toBeTrue(
            "Halaman [{$component}] yang dirender oleh [{$source}] tidak punya berkas ".
            "resources/js/Pages/{$expected} dengan ejaan persis itu."
        );
    }
});

test('page files are reachable through the configured inertia view finder', function (): void {
    $finder = app('inertia.view-finder');

    foreach (pageFilesOnDisk() as $relative) {
        $component = str_replace('/', '.', substr($relative, 0, -strlen('.vue')));

        expect($finder->find($component))->toBeString();
    }
});
