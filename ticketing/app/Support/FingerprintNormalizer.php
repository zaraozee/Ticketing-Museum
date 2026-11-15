<?php

namespace App\Support;

use JsonSerializable;

/**
 * Normalisasi nilai baris agar sidik jari dapat dibandingkan antar-driver.
 *
 * Latihan restore (`db:verify-restore`) membandingkan isi database **sumber**
 * dengan isi database **sementara** hasil restore. Keduanya belum tentu
 * memakai driver yang sama: di produksi sumbernya PostgreSQL sedangkan database
 * sementara selalu SQLite (lihat `VerifyRestoreCommand`).
 *
 * Perbedaan representasi antar-driver membuat perbandingan mentah selalu
 * menyimpulkan "berbeda" meski datanya utuh:
 *
 * - `boolean` PostgreSQL kembali sebagai `true`/`false`, SQLite sebagai `1`/`0`;
 * - `numeric`/`decimal` PostgreSQL kembali sebagai string ("-6.2000000"),
 *   SQLite sebagai float (-6.2).
 *
 * Kelas ini memetakan setiap nilai ke bentuk teks kanonik sehingga perbedaan
 * representasi hilang, sementara perbedaan **nilai** tetap terlihat.
 */
class FingerprintNormalizer
{
    /**
     * Hash isi baris setelah dinormalisasi.
     *
     * @param  iterable<int, array<string, mixed>|object>  $rows
     */
    public static function hash(iterable $rows): string
    {
        $normalized = [];

        foreach ($rows as $row) {
            $normalized[] = self::row(is_object($row) ? (array) $row : $row);
        }

        return hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
    }

    /**
     * Normalisasi satu baris menjadi peta kolom => teks kanonik.
     *
     * Kolom diurutkan agar urutan kolom yang dikembalikan driver tidak
     * memengaruhi hasil.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, string|null>
     */
    public static function row(array $row): array
    {
        $normalized = [];

        foreach ($row as $column => $value) {
            $normalized[(string) $column] = self::value($value);
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * Bentuk kanonik satu nilai.
     */
    private static function value(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return self::number((string) $value);
        }

        if (is_string($value)) {
            // Kolom angka yang dikembalikan sebagai string (mis. `numeric`
            // PostgreSQL) dan kolom angka asli harus menghasilkan teks sama.
            return is_numeric($value) ? self::number($value) : $value;
        }

        if (is_array($value) || $value instanceof JsonSerializable) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        return (string) $value;
    }

    /**
     * Bentuk kanonik angka: tanpa nol berlebih dan tanpa tanda plus.
     */
    private static function number(string $value): string
    {
        $value = trim($value);

        $sign = '';

        if (str_starts_with($value, '-')) {
            $sign = '-';
            $value = substr($value, 1);
        } elseif (str_starts_with($value, '+')) {
            $value = substr($value, 1);
        }

        $parts = explode('.', $value, 2);

        $whole = ltrim($parts[0], '0');
        $whole = $whole === '' ? '0' : $whole;

        // "6.2000000" dan "6.2" adalah nilai yang sama.
        $fraction = isset($parts[1]) ? rtrim($parts[1], '0') : '';

        if ($whole === '0' && $fraction === '') {
            return '0';
        }

        return $sign.$whole.($fraction === '' ? '' : '.'.$fraction);
    }
}
