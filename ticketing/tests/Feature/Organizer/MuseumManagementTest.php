<?php

use App\Enums\MuseumStatus;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Museum;
use App\Models\MuseumCategory;
use App\Models\MuseumClosedDate;
use App\Models\MuseumImage;
use App\Models\MuseumOperatingHour;
use App\Models\OrganizerProfile;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $this->category = MuseumCategory::factory()->create();

    $this->organizer = User::factory()->create();
    $this->organizer->assignRole(UserRole::ORGANIZER->value);
    $this->profile = OrganizerProfile::factory()->approved()->create(['user_id' => $this->organizer->id]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::ADMIN->value);
});

/**
 * Lengkapi syarat minimum pengajuan verifikasi.
 */
function completeMuseum(Museum $museum): Museum
{
    MuseumImage::create([
        'museum_id' => $museum->id,
        'path' => 'museums/1/test.webp',
        'sort_order' => 0,
        'is_cover' => true,
    ]);

    MuseumOperatingHour::create([
        'museum_id' => $museum->id,
        'day_of_week' => 1,
        'open_time' => '09:00',
        'close_time' => '17:00',
        'is_closed' => false,
    ]);

    $museum->forceFill([
        'latitude' => -7.797068,
        'longitude' => 110.370529,
        'description' => 'Museum sejarah dengan koleksi batik Nusantara.',
    ])->save();

    return $museum->refresh();
}

test('submitting a museum without photos is rejected with a clear message', function (): void {
    $museum = Museum::factory()->forOrganizer($this->profile)->create([
        'latitude' => -7.79,
        'longitude' => 110.37,
        'description' => 'Deskripsi lengkap.',
    ]);

    MuseumOperatingHour::create([
        'museum_id' => $museum->id,
        'day_of_week' => 1,
        'open_time' => '09:00',
        'close_time' => '17:00',
        'is_closed' => false,
    ]);

    $this->actingAs($this->organizer)
        ->post("/organizer/museums/{$museum->slug}/submit")
        ->assertSessionHasErrors('submission');

    expect($museum->fresh()->status)->toBe(MuseumStatus::DRAFT);
});

test('submitting a museum without operating hours is rejected', function (): void {
    $museum = Museum::factory()->forOrganizer($this->profile)->create([
        'latitude' => -7.79,
        'longitude' => 110.37,
        'description' => 'Deskripsi lengkap.',
    ]);

    MuseumImage::create(['museum_id' => $museum->id, 'path' => 'a.webp', 'sort_order' => 0, 'is_cover' => true]);

    $this->actingAs($this->organizer)
        ->post("/organizer/museums/{$museum->slug}/submit")
        ->assertSessionHasErrors('submission');

    expect($museum->fresh()->status)->toBe(MuseumStatus::DRAFT);
});

test('submitting a museum without coordinates is rejected', function (): void {
    $museum = Museum::factory()->forOrganizer($this->profile)->create([
        'latitude' => null,
        'longitude' => null,
        'description' => 'Deskripsi lengkap.',
    ]);

    MuseumImage::create(['museum_id' => $museum->id, 'path' => 'a.webp', 'sort_order' => 0, 'is_cover' => true]);
    MuseumOperatingHour::create([
        'museum_id' => $museum->id,
        'day_of_week' => 1,
        'open_time' => '09:00',
        'close_time' => '17:00',
        'is_closed' => false,
    ]);

    $this->actingAs($this->organizer)
        ->post("/organizer/museums/{$museum->slug}/submit")
        ->assertSessionHasErrors('submission');

    expect($museum->fresh()->status)->toBe(MuseumStatus::DRAFT);
});

test('all submission problems are reported at once, not just the first', function (): void {
    // Museum kosong: foto, koordinat, jam operasional, dan deskripsi semuanya belum ada.
    $museum = Museum::factory()->forOrganizer($this->profile)->create([
        'latitude' => null,
        'longitude' => null,
        'description' => null,
    ]);

    $response = $this->actingAs($this->organizer)
        ->post("/organizer/museums/{$museum->slug}/submit");

    $response->assertSessionHasErrors('submission');

    $message = (string) session('errors')->first('submission');
    $problems = array_map('trim', explode('|', $message));

    // Inertia hanya mengirim satu pesan per kunci error, sehingga seluruh
    // masalah harus digabung — bukan hanya yang pertama.
    expect($problems)->toHaveCount(4);

    expect($museum->fresh()->status)->toBe(MuseumStatus::DRAFT);
});

test('a complete museum can be submitted and published by admin', function (): void {
    $museum = completeMuseum(
        Museum::factory()->forOrganizer($this->profile)->create(),
    );

    $this->actingAs($this->organizer)
        ->post("/organizer/museums/{$museum->slug}/submit")
        ->assertSessionHasNoErrors();

    $museum->refresh();
    expect($museum->status)->toBe(MuseumStatus::PENDING_REVIEW)
        ->and($museum->submitted_at)->not->toBeNull();

    $this->actingAs($this->admin)
        ->patch("/admin/museums/{$museum->slug}/review", ['decision' => 'approve'])
        ->assertSessionHasNoErrors();

    $museum->refresh();
    expect($museum->status)->toBe(MuseumStatus::PUBLISHED)
        ->and($museum->published_at)->not->toBeNull();
});

test('admin rejection stores the note and returns museum to organizer for revision', function (): void {
    $museum = completeMuseum(Museum::factory()->forOrganizer($this->profile)->pendingReview()->create());

    $this->actingAs($this->admin)
        ->patch("/admin/museums/{$museum->slug}/review", [
            'decision' => 'reject',
            'note' => 'Foto kurang jelas dan deskripsi terlalu singkat.',
        ])
        ->assertSessionHasNoErrors();

    $museum->refresh();
    expect($museum->status)->toBe(MuseumStatus::REJECTED)
        ->and($museum->rejection_note)->toContain('Foto kurang jelas');

    // Organizer boleh memperbaiki dan mengajukan ulang.
    $this->actingAs($this->organizer)
        ->post("/organizer/museums/{$museum->slug}/submit")
        ->assertSessionHasNoErrors();

    expect($museum->fresh()->status)->toBe(MuseumStatus::PENDING_REVIEW);
});

test('admin can suspend a published museum and restore it', function (): void {
    $museum = Museum::factory()->forOrganizer($this->profile)->published()->create();

    $this->actingAs($this->admin)
        ->patch("/admin/museums/{$museum->slug}/review", [
            'decision' => 'suspend',
            'note' => 'Konten melanggar pedoman.',
        ])
        ->assertSessionHasNoErrors();

    expect($museum->fresh()->status)->toBe(MuseumStatus::SUSPENDED);

    $this->actingAs($this->admin)
        ->patch("/admin/museums/{$museum->slug}/review", ['decision' => 'restore'])
        ->assertSessionHasNoErrors();

    expect($museum->fresh()->status)->toBe(MuseumStatus::PUBLISHED);
});

test('critical museum changes are recorded in activity logs', function (): void {
    $museum = Museum::factory()->forOrganizer($this->profile)->create([
        'name' => 'Nama Lama',
        'address' => 'Alamat Lama',
    ]);

    $this->actingAs($this->organizer)
        ->patch("/organizer/museums/{$museum->slug}", [
            'name' => 'Nama Baru',
            'category_id' => $this->category->id,
            'address' => 'Alamat Baru',
            'latitude' => -7.8,
            'longitude' => 110.4,
        ])
        ->assertSessionHasNoErrors();

    $log = ActivityLog::where('action', 'museum.critical_updated')
        ->where('subject_id', $museum->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->properties)->toHaveKeys(['name', 'address'])
        ->and($log->properties['name']['after'])->toBe('Nama Baru');
});

test('non critical updates do not create critical audit entries', function (): void {
    $museum = Museum::factory()->forOrganizer($this->profile)->create();

    $this->actingAs($this->organizer)
        ->patch("/organizer/museums/{$museum->slug}", [
            'name' => $museum->name,
            'category_id' => $this->category->id,
            'max_tickets_per_booking' => 6,
        ])
        ->assertSessionHasNoErrors();

    expect(ActivityLog::where('action', 'museum.critical_updated')->count())->toBe(0);
});

test('organizer can still edit a published museum and the change is audited', function (): void {
    $museum = Museum::factory()->forOrganizer($this->profile)->published()->create([
        'name' => 'Museum Tayang',
        'address' => 'Alamat Lama',
    ]);

    // BR-21: perubahan data kritis pada museum published tetap diizinkan,
    // tetapi wajib tercatat di activity_logs.
    $this->actingAs($this->organizer)
        ->get("/organizer/museums/{$museum->slug}/edit")
        ->assertOk();

    $this->actingAs($this->organizer)
        ->patch("/organizer/museums/{$museum->slug}", [
            'name' => 'Museum Tayang',
            'category_id' => $this->category->id,
            'address' => 'Alamat Baru',
        ])
        ->assertSessionHasNoErrors();

    expect($museum->fresh()->status)->toBe(MuseumStatus::PUBLISHED)
        ->and(ActivityLog::where('action', 'museum.critical_updated')->count())->toBe(1);
});

test('organizer cannot edit a museum that is under review or suspended', function (string $state): void {
    $museum = Museum::factory()->forOrganizer($this->profile)->{$state}()->create();

    $this->actingAs($this->organizer)
        ->get("/organizer/museums/{$museum->slug}/edit")
        ->assertForbidden();
})->with([
    'pending review' => 'pendingReview',
    'suspended' => 'suspended',
]);

test('deleting a draft museum soft deletes it and keeps its gallery intact', function (): void {
    $museum = Museum::factory()->forOrganizer($this->profile)->create();

    MuseumImage::create([
        'museum_id' => $museum->id,
        'path' => 'museums/1/keep.webp',
        'sort_order' => 0,
        'is_cover' => true,
    ]);

    $this->actingAs($this->organizer)
        ->delete("/organizer/museums/{$museum->slug}")
        ->assertRedirect('/organizer/museums');

    expect(Museum::find($museum->id))->toBeNull()
        ->and(Museum::withTrashed()->find($museum->id))->not->toBeNull()
        // Soft delete tidak boleh menghapus baris galeri, agar museum dapat dipulihkan.
        ->and(MuseumImage::where('museum_id', $museum->id)->count())->toBe(1);
});

test('organizer cannot delete a museum that is under review or suspended', function (string $state): void {
    $museum = Museum::factory()->forOrganizer($this->profile)->{$state}()->create();

    $this->actingAs($this->organizer)
        ->delete("/organizer/museums/{$museum->slug}")
        ->assertForbidden();

    expect(Museum::find($museum->id))->not->toBeNull();
})->with([
    'pending review' => 'pendingReview',
    'suspended' => 'suspended',
]);

test('closed dates are unique per museum', function (): void {
    $museum = Museum::factory()->forOrganizer($this->profile)->create();

    $date = now()->addWeek()->toDateString();

    $this->actingAs($this->organizer)
        ->post("/organizer/museums/{$museum->slug}/closed-dates", ['closed_on' => $date, 'reason' => 'Libur nasional'])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->organizer)
        ->post("/organizer/museums/{$museum->slug}/closed-dates", ['closed_on' => $date, 'reason' => 'Duplikat'])
        ->assertSessionHasErrors('closed_on');

    expect(MuseumClosedDate::where('museum_id', $museum->id)->count())->toBe(1);
});

test('closed date in the past is rejected', function (): void {
    $museum = Museum::factory()->forOrganizer($this->profile)->create();

    $this->actingAs($this->organizer)
        ->post("/organizer/museums/{$museum->slug}/closed-dates", [
            'closed_on' => now()->subDay()->toDateString(),
        ])
        ->assertSessionHasErrors('closed_on');
});

test('youtube url is normalised into a video id and invalid links are rejected', function (): void {
    $this->actingAs($this->organizer)
        ->post('/organizer/museums', [
            'name' => 'Museum Video',
            'category_id' => $this->category->id,
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ])
        ->assertSessionHasNoErrors();

    expect(Museum::where('name', 'Museum Video')->first()?->youtube_video_id)->toBe('dQw4w9WgXcQ');

    $this->actingAs($this->organizer)
        ->post('/organizer/museums', [
            'name' => 'Museum Video Salah',
            'category_id' => $this->category->id,
            'youtube_url' => 'https://example.com/bukan-youtube',
        ])
        ->assertSessionHasErrors('youtube_url');
});
