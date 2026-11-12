<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Backup database portabel (NFR-14, SYSTEM_DESIGN §8.5).
 *
 * Menulis satu berkas JSON berisi seluruh tabel aplikasi beserta jumlah
 * barisnya. Format netral driver dipilih agar hasilnya dapat dipulihkan dan
 * diverifikasi di lingkungan mana pun — termasuk mesin pengembangan yang
 * tidak memiliki `pg_dump`/`mysqldump`.
 *
 * Untuk produksi PostgreSQL, `pg_dump` tetap menjadi jalur utama (lihat
 * `docs/RUNBOOK.md`); command ini melengkapi dengan bukti bahwa data dapat
 * dibaca kembali dan dipulihkan.
 *
 * **Keamanan berkas.** Backup memuat data pribadi pengunjung. Berkas ditulis
 * ke `storage/backups` yang berada di luar `public/`, dan direktori induk
 * dibuat dengan izin terbatas.
 */
class DatabaseBackupCommand extends Command
{
    protected $signature = 'db:backup
        {--path= : Lokasi berkas tujuan (default: storage/backups)}
        {--exclude= : Tabel yang dilewati, dipisahkan koma}';

    protected $description = 'Buat cadangan seluruh tabel aplikasi ke berkas JSON yang dapat dipulihkan';

    /**
     * Tabel milik framework yang tidak perlu dicadangkan: isinya dibangun
     * ulang oleh `migrate` dan `queue:restart`.
     *
     * @var array<int, string>
     */
    private const ALWAYS_EXCLUDED = [
        'migrations',
        'cache',
        'cache_locks',
        'sessions',
        'jobs',
        'job_batches',
        'failed_jobs',
        'password_reset_tokens',
    ];

    public function handle(): int
    {
        $directory = $this->option('path') ?: storage_path('backups');

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0750, true);
        }

        $excluded = array_filter(array_map('trim', explode(',', (string) $this->option('exclude'))));
        $tables = array_values(array_diff($this->tables(), self::ALWAYS_EXCLUDED, $excluded));

        // Nama berkas memuat mikrodetik agar dua backup dalam detik yang sama
        // tidak saling menimpa — dan agar drill tidak memilih berkas lama.
        $filename = $directory.DIRECTORY_SEPARATOR.'jejak-'.now()->format('Ymd-His-u').'.json';

        $payload = [
            'app' => config('app.name'),
            'created_at' => now()->toIso8601String(),
            'connection' => DB::connection()->getDriverName(),
            'database' => DB::connection()->getDatabaseName(),
            'tables' => [],
        ];

        $totalRows = 0;

        foreach ($tables as $table) {
            $rows = DB::table($table)->get()->map(fn (object $row): array => (array) $row)->all();

            $payload['tables'][$table] = $rows;
            $totalRows += count($rows);
        }

        File::put($filename, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        $this->info(sprintf(
            'Cadangan dibuat: %s (%d tabel, %d baris).',
            $filename,
            count($payload['tables']),
            $totalRows,
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function tables(): array
    {
        // `Schema::getTables()` mengembalikan array asosiatif, bukan objek.
        $tables = array_map(
            fn (array $table): string => (string) $table['name'],
            Schema::getTables(),
        );

        sort($tables);

        return $tables;
    }
}
