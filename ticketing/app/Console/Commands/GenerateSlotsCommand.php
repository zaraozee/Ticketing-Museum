<?php

namespace App\Console\Commands;

use App\Enums\MuseumStatus;
use App\Models\AppSetting;
use App\Models\Museum;
use App\Services\Slot\SlotGenerator;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Membuat slot kunjungan dari template (FR-ORG-07).
 *
 * Dijalankan terjadwal harian (lihat `routes/console.php`). Aman dijalankan
 * berulang kali karena `SlotGenerator` bersifat idempoten (AC-1 Fase 4).
 */
class GenerateSlotsCommand extends Command
{
    protected $signature = 'slots:generate
                            {--museum= : Slug museum tertentu (default: semua museum published)}
                            {--days= : Jumlah hari ke depan (default: app_settings.booking_window_days)}
                            {--from= : Tanggal mulai YYYY-MM-DD (default: hari ini)}
                            {--to= : Tanggal akhir YYYY-MM-DD (menimpa --days)}';

    protected $description = 'Membuat slot kunjungan dari template slot museum';

    public function handle(SlotGenerator $generator): int
    {
        $from = $this->option('from') !== null
            ? Carbon::parse((string) $this->option('from'))->startOfDay()
            : Carbon::today();

        $to = $this->option('to') !== null
            ? Carbon::parse((string) $this->option('to'))->startOfDay()
            : $from->copy()->addDays($this->windowDays());

        if ($to->lessThan($from)) {
            $this->error('Tanggal akhir tidak boleh lebih awal dari tanggal mulai.');

            return self::FAILURE;
        }

        $museums = $this->resolveMuseums();

        if ($museums->isEmpty()) {
            $this->warn('Tidak ada museum yang perlu diproses.');

            return self::SUCCESS;
        }

        $totalCreated = 0;
        $totalUpdated = 0;
        $totalSkipped = 0;

        foreach ($museums as $museum) {
            $result = $generator->generate($museum, $from->copy(), $to->copy());

            $totalCreated += $result['created'];
            $totalUpdated += $result['updated'];
            $totalSkipped += $result['skipped'];

            $this->line(sprintf(
                '  %-40s dibuat: %d, diperbarui: %d, dilewati: %d',
                $museum->name,
                $result['created'],
                $result['updated'],
                $result['skipped'],
            ));
        }

        $this->info(sprintf(
            'Selesai %s s.d. %s — dibuat %d, diperbarui %d, dilewati %d.',
            $from->toDateString(),
            $to->toDateString(),
            $totalCreated,
            $totalUpdated,
            $totalSkipped,
        ));

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Museum>
     */
    private function resolveMuseums()
    {
        $query = Museum::query()->where('status', MuseumStatus::PUBLISHED->value);

        $slug = $this->option('museum');

        if ($slug !== null) {
            $query->where('slug', (string) $slug);
        }

        return $query->orderBy('id')->get();
    }

    private function windowDays(): int
    {
        $setting = AppSetting::query()
            ->where('key', 'booking_window_days')
            ->value('value');

        return is_numeric($setting) ? (int) $setting : 60;
    }
}
