<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Menghasilkan ikon PWA PNG (FR-PWA-01) dari bentuk geometris.
 *
 * PWA installability mensyaratkan ikon PNG 192 px dan 512 px — Lighthouse
 * menolak manifest yang hanya menyediakan SVG. Ikon dibuat dengan GD agar tidak
 * menambah dependensi build. Varian `maskable` memakai padding area aman 20%
 * supaya huruf tidak terpotong oleh mask Android/iOS.
 */
class GeneratePwaIconsCommand extends Command
{
    protected $signature = 'pwa:icons {--force : Timpa ikon yang sudah ada}';

    protected $description = 'Generate ikon PWA PNG (192/512, any + maskable)';

    public function handle(): int
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->error('Ekstensi GD tidak tersedia; ikon tidak dapat dibuat.');

            return self::FAILURE;
        }

        $directory = public_path('icons');

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            $this->error("Tidak dapat membuat direktori {$directory}.");

            return self::FAILURE;
        }

        $variants = [
            ['file' => 'icon-192.png', 'size' => 192, 'maskable' => false],
            ['file' => 'icon-512.png', 'size' => 512, 'maskable' => false],
            ['file' => 'maskable-192.png', 'size' => 192, 'maskable' => true],
            ['file' => 'maskable-512.png', 'size' => 512, 'maskable' => true],
        ];

        foreach ($variants as $variant) {
            $path = $directory.DIRECTORY_SEPARATOR.$variant['file'];

            if (file_exists($path) && ! $this->option('force')) {
                $this->line("Lewati {$variant['file']} (sudah ada).");

                continue;
            }

            $this->render($path, $variant['size'], $variant['maskable']);

            $this->info("Dibuat {$variant['file']}");
        }

        return self::SUCCESS;
    }

    private function render(string $path, int $size, bool $maskable): void
    {
        $image = imagecreatetruecolor($size, $size);

        $blue = (int) imagecolorallocate($image, 0x25, 0x63, 0xEB);
        $white = (int) imagecolorallocate($image, 0xFF, 0xFF, 0xFF);

        // Latar penuh (wajib untuk `maskable`: tidak boleh ada sudut transparan).
        imagefilledrectangle($image, 0, 0, $size - 1, $size - 1, $blue);

        // Huruf "J" dari tiga batang, diskalakan ke area aman.
        $safe = $maskable ? 0.58 : 0.74;
        $unit = (int) round($size * $safe);
        $originX = intdiv($size - $unit, 2);
        $originY = intdiv($size - $unit, 2);

        $x = fn (float $fraction): int => $originX + (int) round($unit * $fraction);
        $y = fn (float $fraction): int => $originY + (int) round($unit * $fraction);

        // Batang vertikal.
        imagefilledrectangle($image, $x(0.56), $y(0.10), $x(0.78), $y(0.74), $white);
        // Dasar horizontal.
        imagefilledrectangle($image, $x(0.16), $y(0.74), $x(0.78), $y(0.90), $white);
        // Kait kiri yang naik.
        imagefilledrectangle($image, $x(0.16), $y(0.60), $x(0.34), $y(0.90), $white);

        imagepng($image, $path);
        imagedestroy($image);
    }
}
