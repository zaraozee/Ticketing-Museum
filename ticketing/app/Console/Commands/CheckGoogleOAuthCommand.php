<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Throwable;

/**
 * Memeriksa kesiapan konfigurasi login Google (FR-AUTH-05).
 *
 * Memeriksa hal-hal yang paling sering salah dan tidak terlihat dari kode:
 * variabel `.env` kosong, cache konfigurasi basi, dan URI pengalihan yang
 * tidak cocok dengan yang didaftarkan di Google Cloud Console.
 */
class CheckGoogleOAuthCommand extends Command
{
    protected $signature = 'auth:check-google';

    protected $description = 'Periksa konfigurasi login Google (OAuth) tanpa membuka browser';

    public function handle(): int
    {
        $clientId = (string) config('services.google.client_id');
        $clientSecret = (string) config('services.google.client_secret');
        $redirect = (string) config('services.google.redirect');

        $this->newLine();
        $this->line('  <options=bold>Konfigurasi Google OAuth</>');
        $this->newLine();

        $problems = 0;

        $problems += $this->report(
            'GOOGLE_CLIENT_ID',
            $clientId !== '',
            $clientId !== '' ? $this->mask($clientId) : 'kosong',
            'Isi dengan Client ID dari Google Auth Platform > Clients.',
        );

        $problems += $this->report(
            'GOOGLE_CLIENT_SECRET',
            $clientSecret !== '',
            $clientSecret !== '' ? $this->mask($clientSecret) : 'kosong',
            'Isi dengan Client secret (hanya tampil sekali saat dibuat).',
        );

        $expectedRedirect = rtrim((string) config('app.url'), '/').'/auth/google/callback';

        $problems += $this->report(
            'GOOGLE_REDIRECT_URI',
            $redirect === $expectedRedirect,
            $redirect !== '' ? $redirect : 'kosong',
            "Harus sama persis dengan APP_URL: {$expectedRedirect}",
        );

        if (is_file(base_path('bootstrap/cache/config.php'))) {
            $this->newLine();
            $this->warn('  Konfigurasi sedang di-cache (bootstrap/cache/config.php).');
            $this->line('  Setelah mengubah .env, jalankan: <options=bold>php artisan config:clear</>');
        }

        if ($problems === 0) {
            $this->newLine();
            $this->info('  Semua terisi. Memeriksa kesiapan driver Google...');
            $this->newLine();

            try {
                // Jangan panggil redirect(): ia menulis state ke sesi HTTP yang
                // tidak ada di CLI. Cukup pastikan driver terbentuk dan
                // kredensialnya terbaca.
                $driver = Socialite::driver('google');

                if (! $driver instanceof GoogleProvider) {
                    $this->error('  Driver google bukan GoogleProvider.');

                    return self::FAILURE;
                }

                $this->line('  <fg=green>[OK]</> Driver Google siap dipakai.');
                $this->line('  Buka <options=bold>/login</> lalu klik "Masuk dengan Google".');
                $this->line('  Pastikan redirect URI di Google Cloud Console sama dengan:');
                $this->line("    <options=bold>{$redirect}</>");
                $this->line('  Dan email Anda terdaftar di Audience > Test users.');
                $this->newLine();

                return self::SUCCESS;
            } catch (Throwable $exception) {
                $this->error('  Gagal menyiapkan driver Google: '.$exception->getMessage());

                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->error("  {$problems} masalah ditemukan. Login Google belum dapat dipakai.");
        $this->newLine();

        return self::FAILURE;
    }

    private function report(string $name, bool $ok, string $value, string $hint): int
    {
        if ($ok) {
            $this->line("  <fg=green>[OK]</> {$name}: {$value}");

            return 0;
        }

        $this->line("  <fg=red>[X]</> {$name}: {$value}");
        $this->line("      {$hint}");

        return 1;
    }

    /**
     * Tampilkan sebagian nilai agar dapat dicocokkan dengan dashboard tanpa
     * mencetak rahasia penuh ke terminal atau log.
     */
    private function mask(string $value): string
    {
        if (mb_strlen($value) <= 12) {
            return str_repeat('*', mb_strlen($value));
        }

        return mb_substr($value, 0, 8).'...'.mb_substr($value, -4);
    }
}
