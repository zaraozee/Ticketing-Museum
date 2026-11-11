<?php

namespace App\Services\Museum;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use RuntimeException;

/**
 * Unggah gambar museum dengan re-encode dan resize.
 *
 * Keamanan (SYSTEM_DESIGN §16 "Unggah file"): validasi MIME dilakukan di Form Request,
 * di sini berkas benar-benar didekode ulang sebagai gambar (memutus EXIF/payload
 * tersembunyi) lalu ditulis dengan nama acak ke disk non-eksekusi.
 *
 * Format keluaran mengikuti dukungan GD pada server: WebP bila tersedia (paling
 * efisien), lalu JPEG, dan terakhir PNG. Semua jalur tetap melalui re-encode.
 */
class MuseumImageService
{
    private const MAX_WIDTH = 1600;

    private const QUALITY = 82;

    public function __construct(
        private readonly ImageManager $manager = new ImageManager(new Driver),
    ) {}

    /**
     * Simpan gambar yang sudah di-re-encode dan kembalikan path relatifnya.
     */
    public function store(UploadedFile $file, int $museumId): string
    {
        $disk = $this->disk();

        $path = $file->getRealPath();

        if ($path === false) {
            throw new RuntimeException('Berkas gambar tidak dapat dibaca.');
        }

        $image = $this->manager->decodePath($path);

        if ($image->width() > self::MAX_WIDTH) {
            $image->scaleDown(width: self::MAX_WIDTH);
        }

        [$extension, $encoded] = $this->encode($image);

        $filename = sprintf('%d/%s.%s', $museumId, Str::ulid(), $extension);

        if (! Storage::disk($disk)->put($filename, $encoded)) {
            throw new RuntimeException('Gagal menyimpan gambar museum.');
        }

        return $filename;
    }

    /**
     * @return array{0: string, 1: string} ekstensi dan isi berkas
     */
    private function encode(ImageInterface $image): array
    {
        if ($this->gdSupports('webp')) {
            return ['webp', $image->encode(new WebpEncoder(quality: self::QUALITY, strip: true))->toString()];
        }

        if ($this->gdSupports('jpeg')) {
            return ['jpg', $image->encode(new JpegEncoder(quality: self::QUALITY, strip: true))->toString()];
        }

        return ['png', $image->encode(new PngEncoder(strip: true))->toString()];
    }

    private function gdSupports(string $format): bool
    {
        if (! function_exists('gd_info')) {
            return false;
        }

        /** @var array<string, mixed> $info */
        $info = gd_info();

        return match ($format) {
            'webp' => (bool) ($info['WebP Support'] ?? false),
            'jpeg' => (bool) ($info['JPEG Support'] ?? false),
            default => true,
        };
    }

    public function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        Storage::disk($this->disk())->delete($path);
    }

    /**
     * URL publik untuk ditampilkan di halaman.
     */
    public function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return Storage::disk($this->disk())->url($path);
    }

    private function disk(): string
    {
        return (string) config('filesystems.museum_disk', 'public');
    }
}
