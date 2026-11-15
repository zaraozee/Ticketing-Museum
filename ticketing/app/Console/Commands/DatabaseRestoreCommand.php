<?php

namespace App\Console\Commands;

use App\Support\TableDependencyOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Pulihkan database dari berkas backup `db:backup` (NFR-14).
 *
 * **Mengganti, bukan menggabungkan.** Tabel dikosongkan lebih dulu agar hasil
 * restore persis sama dengan saat backup dibuat. Command ini menolak berjalan
 * di produksi tanpa `--force`.
 *
 * **Urutan tabel.** Penghapusan mengikuti urutan anak→induk dan penyisipan
 * induk→anak, dihitung dari foreign key nyata. Mematikan pemeriksaan foreign
 * key tidak dapat diandalkan: SQLite mengabaikan `PRAGMA foreign_keys` bila
 * dijalankan di dalam transaksi, dan restore selalu berada dalam transaksi.
 */
class DatabaseRestoreCommand extends Command
{
    protected $signature = 'db:restore
        {file : Berkas backup hasil db:backup}
        {--force : Wajib di produksi untuk mencegah kecelakaan}';

    protected $description = 'Pulihkan database dari berkas backup JSON (mengganti data saat ini)';

    public function handle(): int
    {
        $path = (string) $this->argument('file');

        if (! File::isFile($path)) {
            $this->error("Berkas backup tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Restore di produksi memerlukan --force. Pastikan backup terbaru sudah ada.');

            return self::FAILURE;
        }

        /** @var mixed $decoded */
        $decoded = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded) || ! isset($decoded['tables']) || ! is_array($decoded['tables'])) {
            $this->error('Berkas backup tidak memiliki bagian "tables".');

            return self::FAILURE;
        }

        /** @var array<string, array<int, array<string, mixed>>> $tables */
        $tables = $decoded['tables'];

        /** @var array<int, string> $present */
        $present = array_values(array_filter(
            array_keys($tables),
            fn (string $table): bool => Schema::hasTable($table),
        ));

        $deleteOrder = TableDependencyOrder::childrenFirst($present);
        $insertOrder = TableDependencyOrder::parentsFirst($present);

        try {
            DB::transaction(function () use ($tables, $deleteOrder, $insertOrder): void {
                foreach ($deleteOrder as $table) {
                    DB::table($table)->delete();
                }

                foreach ($insertOrder as $table) {
                    $rows = $tables[$table];

                    if ($rows === []) {
                        continue;
                    }

                    foreach (array_chunk($rows, 200) as $chunk) {
                        DB::table($table)->insert($chunk);
                    }
                }
            });
        } catch (Throwable $exception) {
            throw new RuntimeException('Restore gagal: '.$exception->getMessage(), 0, $exception);
        }

        // Baris disisipkan dengan `id` eksplisit, sehingga sequence PostgreSQL
        // tidak ikut maju. Tanpa langkah ini, INSERT berikutnya akan gagal
        // "duplicate key value violates unique constraint" karena sequence
        // masih menunjuk id yang sudah terpakai.
        $this->resynchronizeSequences($insertOrder);

        $totalRows = array_sum(array_map('count', $tables));

        $this->info(sprintf(
            'Restore selesai: %d tabel, %d baris.',
            count($present),
            $totalRows,
        ));

        return self::SUCCESS;
    }

    /**
     * Selaraskan sequence id dengan nilai id tertinggi setelah restore.
     *
     * Hanya berlaku untuk PostgreSQL. MySQL menyesuaikan AUTO_INCREMENT sendiri
     * saat baris dengan id eksplisit disisipkan, dan SQLite memakai ROWID
     * maksimum. PostgreSQL tidak: sequence tetap pada posisi sebelumnya,
     * sehingga INSERT tanpa `id` akan menabrak primary key.
     *
     * @param  array<int, string>  $tables
     */
    private function resynchronizeSequences(array $tables): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($tables as $table) {
            // Nama sequence mengikuti konvensi Laravel: {table}_id_seq.
            $sequence = $table.'_id_seq';

            $exists = DB::selectOne(
                "select 1 as ada from pg_class where relkind = 'S' and relname = ?",
                [$sequence],
            );

            if ($exists === null) {
                continue;
            }

            // Nama tabel berasal dari skema nyata (sudah disaring
            // Schema::hasTable), tetapi tetap dikutip agar aman.
            $quotedTable = '"'.str_replace('"', '""', $table).'"';
            $quotedSequence = '"'.str_replace('"', '""', $sequence).'"';

            /*
             * setval(seq, max(id), true) bila tabel berisi, sehingga nextval
             * memberi max(id)+1. Untuk tabel kosong dipakai setval(seq, 1,
             * false) agar id berikutnya adalah 1, bukan 2.
             */
            DB::statement(sprintf(
                'select setval(%s, coalesce((select max(id) from %s), 1), (select max(id) from %s) is not null)',
                $quotedSequence,
                $quotedTable,
                $quotedTable,
            ));
        }
    }
}
