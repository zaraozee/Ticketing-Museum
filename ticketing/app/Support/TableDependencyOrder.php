<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Urutan tabel yang menghormati foreign key.
 *
 * Restore harus menghapus baris dari tabel anak lebih dulu dan
 * memasukkannya dari tabel induk lebih dulu. Urutan dihitung dari skema
 * nyata, bukan daftar manual, sehingga tabel baru otomatis tertangani.
 *
 * Pendekatan ini dipilih daripada mematikan pemeriksaan foreign key karena
 * `PRAGMA foreign_keys` diabaikan SQLite bila dijalankan di dalam transaksi —
 * dan restore selalu berjalan di dalam transaksi (termasuk di dalam tes).
 */
class TableDependencyOrder
{
    /**
     * Urutkan tabel sehingga setiap induk muncul sebelum anaknya.
     *
     * @param  array<int, string>  $tables
     * @return array<int, string>
     */
    public static function parentsFirst(array $tables): array
    {
        $remaining = array_values($tables);
        $ordered = [];
        $resolved = [];

        while ($remaining !== []) {
            $progressed = false;

            foreach ($remaining as $index => $table) {
                $parents = self::parentsOf($table, $tables);

                if (array_diff($parents, $resolved) === []) {
                    $ordered[] = $table;
                    $resolved[] = $table;
                    unset($remaining[$index]);
                    $progressed = true;
                }
            }

            if (! $progressed) {
                /*
                 * Siklus (mis. rujukan diri sendiri yang tidak dapat
                 * diurutkan). Kembalikan sisanya apa adanya: lebih baik
                 * mencoba daripada menggantung.
                 */
                foreach ($remaining as $table) {
                    $ordered[] = $table;
                }

                break;
            }

            $remaining = array_values($remaining);
        }

        return $ordered;
    }

    /**
     * Urutkan tabel sehingga setiap anak muncul sebelum induknya.
     *
     * @param  array<int, string>  $tables
     * @return array<int, string>
     */
    public static function childrenFirst(array $tables): array
    {
        return array_reverse(self::parentsFirst($tables));
    }

    /**
     * Tabel induk yang dirujuk `$table` dan ikut berada dalam `$known`.
     *
     * @param  array<int, string>  $known
     * @return array<int, string>
     */
    private static function parentsOf(string $table, array $known): array
    {
        $parents = [];

        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            $referenced = $foreignKey['foreign_table'];

            // Rujukan ke dirinya sendiri tidak menghalangi urutan.
            if ($referenced !== $table && in_array($referenced, $known, true)) {
                $parents[] = $referenced;
            }
        }

        return array_values(array_unique($parents));
    }
}
