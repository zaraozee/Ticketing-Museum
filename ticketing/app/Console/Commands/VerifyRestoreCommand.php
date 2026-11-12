<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Uji restore sungguhan (AC-3 Fase 9, NFR-14).
 *
 * Alur yang dibuktikan:
 * 1. hitung sidik jari data sumber;
 * 2. buat backup dari database sumber;
 * 3. siapkan database **sementara**, jalankan migrasi di sana;
 * 4. pulihkan backup ke database sementara itu;
 * 5. bandingkan sidik jari — harus identik dengan sumber.
 *
 * **Mengapa database sementara.** Restore bersifat mengganti seluruh isi
 * database. Menjalankannya pada database sumber akan menghapus data nyata,
 * dan membungkusnya dalam satu transaksi tidak mungkin karena SQLite
 * mengabaikan `PRAGMA foreign_keys` di dalam transaksi.
 *
 * **Mengapa proses terpisah.** Migrasi dan restore dijalankan lewat
 * `artisan` dengan environment sendiri. Mengubah koneksi default di proses
 * ini akan memutus koneksi yang sedang dipakai — termasuk database `:memory:`
 * milik test suite. Proses terpisah juga meniru kondisi restore sungguhan,
 * di mana tidak ada koneksi aplikasi lain yang menahan lock.
 */
class VerifyRestoreCommand extends Command
{
    protected $signature = 'db:verify-restore {--keep : Simpan berkas backup dan database sementara}';

    protected $description = 'Uji backup dan restore end-to-end pada database sementara, lalu bandingkan sidik jari';

    /**
     * Tabel yang dipakai sebagai bukti pemulihan. Tabel transaksi dipilih
     * karena tidak boleh hilang dan memiliki foreign key nyata.
     *
     * @var array<int, string>
     */
    private const SAMPLE_TABLES = [
        'users',
        'museums',
        'bookings',
        'booking_items',
        'payments',
        'tickets',
        'ticket_scans',
        'activity_logs',
    ];

    private const TEMP_CONNECTION = 'verify_restore_tmp';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Latihan restore tidak boleh dijalankan di produksi. Gunakan database staging.');

            return self::FAILURE;
        }

        $directory = storage_path('backups/verify');
        File::ensureDirectoryExists($directory, 0750);

        $source = $this->fingerprint(DB::connection());

        $this->line('1/5 Sidik jari sumber: '.$this->describe($source));

        // Hanya berkas dari drill ini yang boleh dipertimbangkan: direktori
        // mungkin masih menyimpan backup dari eksekusi sebelumnya.
        $before = File::glob($directory.DIRECTORY_SEPARATOR.'jejak-*.json');

        $this->call('db:backup', ['--path' => $directory]);

        $created = array_values(array_diff(File::glob($directory.DIRECTORY_SEPARATOR.'jejak-*.json'), $before));
        sort($created);
        $backup = $created === [] ? false : end($created);

        if ($backup === false) {
            $this->error('Backup tidak terbentuk.');

            return self::FAILURE;
        }

        $this->line('2/5 Backup dibuat: '.basename($backup));

        $tempDatabase = $directory.DIRECTORY_SEPARATOR.'verify-'.now()->format('Ymd-His-u').'.sqlite';

        if (File::exists($tempDatabase)) {
            File::delete($tempDatabase);
        }

        File::put($tempDatabase, '');

        $environment = [
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $tempDatabase,
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
        ];

        try {
            $this->runArtisan(['migrate', '--force'], $environment);
            $this->line('3/5 Database sementara disiapkan dan dimigrasikan.');

            $this->runArtisan(['db:restore', $backup, '--force'], $environment);
            $this->line('4/5 Restore dijalankan pada database sementara.');

            $restored = $this->fingerprint($this->temporaryConnection($tempDatabase));
        } catch (Throwable $exception) {
            $this->error('Restore gagal: '.$exception->getMessage());
            $this->warn('Berkas backup dipertahankan untuk pemeriksaan: '.$backup);

            return self::FAILURE;
        }

        $this->line('5/5 Sidik jari hasil restore: '.$this->describe($restored));

        if ($source !== $restored) {
            $this->error('Sidik jari berbeda — restore tidak mengembalikan data dengan utuh.');

            foreach (array_keys($source) as $table) {
                $sourceRow = $source[$table];
                $restoredRow = $restored[$table] ?? null;

                if ($restoredRow !== $sourceRow) {
                    $this->line(sprintf(
                        '  %s: sumber %d baris, hasil restore %d baris',
                        $table,
                        $sourceRow['count'],
                        $restoredRow['count'] ?? 0,
                    ));
                }
            }

            return self::FAILURE;
        }

        DB::purge(self::TEMP_CONNECTION);

        if (! $this->option('keep')) {
            File::delete($backup);
            File::delete($tempDatabase);
        }

        $this->info('Backup dan restore terverifikasi: data identik sebelum dan sesudah.');

        return self::SUCCESS;
    }

    /**
     * Jalankan artisan di proses terpisah dengan environment sendiri.
     *
     * @param  array<int, string>  $arguments
     * @param  array<string, string>  $environment
     */
    private function runArtisan(array $arguments, array $environment): void
    {
        $result = Process::path(base_path())
            ->env($environment)
            ->timeout(300)
            ->run(array_merge([PHP_BINARY, 'artisan'], $arguments));

        if ($result->failed()) {
            throw new \RuntimeException(trim($result->errorOutput() ?: $result->output()));
        }
    }

    /**
     * Koneksi sementara khusus baca, tanpa menyentuh koneksi default.
     */
    private function temporaryConnection(string $path): Connection
    {
        config([
            'database.connections.'.self::TEMP_CONNECTION => [
                'driver' => 'sqlite',
                'database' => $path,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        DB::purge(self::TEMP_CONNECTION);

        return DB::connection(self::TEMP_CONNECTION);
    }

    /**
     * Sidik jari = jumlah baris + hash isi terurut per tabel sampel.
     *
     * @return array<string, array{count: int, hash: string}>
     */
    private function fingerprint(Connection $connection): array
    {
        $schema = Schema::connection($connection->getName());
        $result = [];

        foreach (self::SAMPLE_TABLES as $table) {
            if (! $schema->hasTable($table)) {
                continue;
            }

            $rows = $connection->table($table)->orderBy('id')->get();

            $result[$table] = [
                'count' => $rows->count(),
                'hash' => hash('sha256', $rows->toJson()),
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, array{count: int, hash: string}>  $fingerprint
     */
    private function describe(array $fingerprint): string
    {
        return collect($fingerprint)
            ->map(fn (array $row, string $table): string => $table.'='.$row['count'])
            ->implode(', ');
    }
}
