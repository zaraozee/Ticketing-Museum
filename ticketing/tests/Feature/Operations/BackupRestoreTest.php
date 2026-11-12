<?php

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Museum;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| Backup dan restore (NFR-14, AC-3 Fase 9)
|--------------------------------------------------------------------------
|
| Membuktikan bahwa berkas backup benar-benar dapat dipulihkan, bukan hanya
| bahwa command selesai tanpa error.
|
*/

beforeEach(function (): void {
    $this->directory = storage_path('framework/testing/backups-'.Str::random(8));
    File::ensureDirectoryExists($this->directory);
});

afterEach(function (): void {
    File::deleteDirectory($this->directory);
});

/**
 * Jalankan `db:backup` lalu kembalikan berkas yang baru dibuat.
 */
function runBackup(string $directory, array $options = []): string
{
    $before = File::glob($directory.DIRECTORY_SEPARATOR.'jejak-*.json');

    test()->artisan('db:backup', ['--path' => $directory] + $options)->assertSuccessful();

    $created = array_values(array_diff(File::glob($directory.DIRECTORY_SEPARATOR.'jejak-*.json'), $before));

    expect($created)->toHaveCount(1);

    return $created[0];
}

test('the backup file contains every application table and its rows', function (): void {
    $user = User::factory()->create();
    $museum = Museum::factory()->published()->create();
    $booking = Booking::factory()->forUser($user)->for($museum)->confirmed()->create();

    $item = BookingItem::factory()->forBooking($booking)->forTicketType(
        TicketType::factory()->create(['museum_id' => $museum->id]),
    )->create();

    Ticket::factory()->count(2)->sequence(fn ($sequence): array => ['sequence' => $sequence->index + 1])->create([
        'booking_id' => $booking->id,
        'booking_item_id' => $item->id,
        'museum_id' => $museum->id,
        'visit_slot_id' => $booking->visit_slot_id,
    ]);

    $path = runBackup($this->directory);

    $payload = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

    // Baris yang benar-benar ada harus ikut tercadang, dengan isi yang utuh.
    expect(collect($payload['tables']['bookings'])->pluck('id'))->toContain($booking->id)
        ->and(collect($payload['tables']['tickets'])->where('booking_id', $booking->id))->toHaveCount(2)
        ->and(collect($payload['tables']['users'])->pluck('id'))->toContain($user->id);
});

test('framework tables that are rebuilt by artisan are left out of the backup', function (): void {
    $path = runBackup($this->directory);

    $payload = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

    // Isinya dibangun ulang oleh migrate/queue:restart, jadi mencadangkannya
    // hanya menambah ukuran tanpa memberi nilai pemulihan.
    expect($payload['tables'])
        ->not->toHaveKey('migrations')
        ->not->toHaveKey('cache')
        ->not->toHaveKey('sessions')
        ->not->toHaveKey('jobs')
        ->not->toHaveKey('failed_jobs');
});

test('tables named with --exclude are skipped', function (): void {
    $path = runBackup($this->directory, ['--exclude' => 'activity_logs,email_logs']);

    $payload = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

    expect($payload['tables'])
        ->not->toHaveKey('activity_logs')
        ->not->toHaveKey('email_logs')
        ->toHaveKey('bookings');
});

test('the backup records the driver and timestamp so a restore can be judged', function (): void {
    $path = runBackup($this->directory);

    $payload = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

    expect($payload['connection'])->toBe('sqlite')
        ->and($payload['created_at'])->not->toBeEmpty();
});

test('restoring a missing file fails loudly instead of silently doing nothing', function (): void {
    $this->artisan('db:restore', ['file' => $this->directory.'/tidak-ada.json', '--force' => true])
        ->expectsOutputToContain('tidak ditemukan')
        ->assertFailed();
});

test('a backup file without a tables section is rejected', function (): void {
    $path = $this->directory.DIRECTORY_SEPARATOR.'rusak.json';
    File::put($path, json_encode(['app' => 'Jejak'], JSON_THROW_ON_ERROR));

    $this->artisan('db:restore', ['file' => $path, '--force' => true])
        ->expectsOutputToContain('tables')
        ->assertFailed();
});

test('restoring replaces the current data with the backup contents', function (): void {
    $user = User::factory()->create(['name' => 'Nama Saat Backup']);
    $museum = Museum::factory()->published()->create();
    Booking::factory()->forUser($user)->for($museum)->confirmed()->create();

    $backup = runBackup($this->directory);

    // Ubah data setelah backup: restore harus mengembalikan kondisi lama.
    $user->forceFill(['name' => 'Nama Setelah Backup'])->save();
    $extra = User::factory()->create(['name' => 'Pengguna Baru']);

    $this->artisan('db:restore', ['file' => $backup, '--force' => true])->assertSuccessful();

    expect(User::query()->find($user->id)->name)->toBe('Nama Saat Backup')
        // Baris yang dibuat setelah backup tidak boleh bertahan: restore
        // bersifat mengganti, bukan menggabungkan.
        ->and(User::query()->find($extra->id))->toBeNull();
});

test('restoring twice produces the same result', function (): void {
    $user = User::factory()->create(['name' => 'Nama Stabil']);
    $museum = Museum::factory()->published()->create();
    Booking::factory()->forUser($user)->for($museum)->confirmed()->create();

    $backup = runBackup($this->directory);

    $this->artisan('db:restore', ['file' => $backup, '--force' => true])->assertSuccessful();
    $first = User::query()->orderBy('id')->pluck('name')->all();

    $this->artisan('db:restore', ['file' => $backup, '--force' => true])->assertSuccessful();

    expect(User::query()->orderBy('id')->pluck('name')->all())->toBe($first);
});

test('the end-to-end restore drill reports success without touching the source database', function (): void {
    $user = User::factory()->create(['name' => 'Tetap Utuh']);
    $museum = Museum::factory()->published()->create();
    Booking::factory()->forUser($user)->for($museum)->confirmed()->create();

    $this->artisan('db:verify-restore')->assertSuccessful();

    // Latihan berjalan pada database sementara; data sumber harus utuh.
    expect(User::query()->find($user->id)?->name)->toBe('Tetap Utuh')
        ->and(Booking::query()->count())->toBe(1);
});
