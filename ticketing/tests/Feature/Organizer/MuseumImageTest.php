<?php

use App\Enums\UserRole;
use App\Models\Museum;
use App\Models\MuseumCategory;
use App\Models\MuseumImage;
use App\Models\OrganizerProfile;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    Storage::fake('public');

    $this->category = MuseumCategory::factory()->create();

    $this->organizer = User::factory()->create();
    $this->organizer->assignRole(UserRole::ORGANIZER->value);
    $this->profile = OrganizerProfile::factory()->approved()->create(['user_id' => $this->organizer->id]);

    $this->museum = Museum::factory()->forOrganizer($this->profile)->create([
        'category_id' => $this->category->id,
    ]);

    $this->otherOrganizer = User::factory()->create();
    $this->otherOrganizer->assignRole(UserRole::ORGANIZER->value);
    $otherProfile = OrganizerProfile::factory()->approved()->create(['user_id' => $this->otherOrganizer->id]);
    $this->foreignMuseum = Museum::factory()->forOrganizer($otherProfile)->create();
});

test('organizer can upload a valid image and the stored file is re-encoded', function (): void {
    $file = UploadedFile::fake()->image('museum.png', 1200, 800);

    $this->actingAs($this->organizer)
        ->post("/organizer/museums/{$this->museum->slug}/images", [
            'image' => $file,
            'caption' => 'Gedung utama',
            'is_cover' => true,
        ])
        ->assertSessionHasNoErrors();

    $image = MuseumImage::where('museum_id', $this->museum->id)->first();

    expect($image)->not->toBeNull()
        // Format keluaran mengikuti dukungan GD server (webp/jpg/png), tetapi
        // selalu hasil re-encode dengan nama acak — bukan nama berkas asli.
        // Folder mengikuti `museum_id`, jadi jangan hardcode "1": pada MySQL
        // auto-increment dapat melanjutkan dari nilai sebelumnya.
        ->and($image->path)->toMatch('/^'.$this->museum->id.'\/[0-9A-Z]{26}\.(webp|jpg|png)$/')
        ->and($image->is_cover)->toBeTrue();

    Storage::disk('public')->assertExists($image->path);
});

test('uploading a non image file is rejected', function (): void {
    $file = UploadedFile::fake()->create('dokumen.pdf', 500, 'application/pdf');

    $this->actingAs($this->organizer)
        ->post("/organizer/museums/{$this->museum->slug}/images", ['image' => $file])
        ->assertSessionHasErrors('image');

    expect(MuseumImage::count())->toBe(0);
});

test('a php payload disguised as a png image is rejected', function (): void {
    // Berkas berisi kode PHP dengan ekstensi .png. Validasi harus memeriksa
    // isi/MIME sebenarnya, bukan ekstensi nama berkas.
    $path = tempnam(sys_get_temp_dir(), 'evil').'.png';
    file_put_contents($path, '<?php echo "pwned"; ?>');

    $file = new UploadedFile($path, 'evil.png', 'image/png', null, true);

    $this->actingAs($this->organizer)
        ->post("/organizer/museums/{$this->museum->slug}/images", ['image' => $file])
        ->assertSessionHasErrors('image');

    expect(MuseumImage::count())->toBe(0);

    @unlink($path);
});

test('an uploaded file is stored with a random name and never with the original name', function (): void {
    $file = UploadedFile::fake()->image('foto-asli-museum.png', 1200, 800);

    $this->actingAs($this->organizer)
        ->post("/organizer/museums/{$this->museum->slug}/images", ['image' => $file])
        ->assertSessionHasNoErrors();

    $image = MuseumImage::first();

    expect($image)->not->toBeNull()
        ->and($image->path)->not->toContain('foto-asli-museum')
        ->and($image->path)->not->toContain('..');
});

test('uploading an oversized image is rejected', function (): void {
    $file = UploadedFile::fake()->image('besar.png', 1200, 800)->size(6000);

    $this->actingAs($this->organizer)
        ->post("/organizer/museums/{$this->museum->slug}/images", ['image' => $file])
        ->assertSessionHasErrors('image');
});

test('uploading an image with dimensions below the minimum is rejected', function (): void {
    $file = UploadedFile::fake()->image('kecil.png', 100, 100);

    $this->actingAs($this->organizer)
        ->post("/organizer/museums/{$this->museum->slug}/images", ['image' => $file])
        ->assertSessionHasErrors('image');
});

test('organizer cannot upload an image to another organizer museum', function (): void {
    $file = UploadedFile::fake()->image('museum.png', 1200, 800);

    $this->actingAs($this->organizer)
        ->post("/organizer/museums/{$this->foreignMuseum->slug}/images", ['image' => $file])
        ->assertForbidden();

    expect(MuseumImage::count())->toBe(0);
});

test('first uploaded image automatically becomes the cover', function (): void {
    $file = UploadedFile::fake()->image('museum.png', 1200, 800);

    $this->actingAs($this->organizer)
        ->post("/organizer/museums/{$this->museum->slug}/images", ['image' => $file])
        ->assertSessionHasNoErrors();

    expect(MuseumImage::first()?->is_cover)->toBeTrue();
});

test('setting a new cover image unsets the previous cover', function (): void {
    $first = MuseumImage::create([
        'museum_id' => $this->museum->id,
        'path' => 'museums/1/first.webp',
        'sort_order' => 0,
        'is_cover' => true,
    ]);

    $second = MuseumImage::create([
        'museum_id' => $this->museum->id,
        'path' => 'museums/1/second.webp',
        'sort_order' => 1,
        'is_cover' => false,
    ]);

    $this->actingAs($this->organizer)
        ->patch("/organizer/museums/{$this->museum->slug}/images/{$second->id}", ['is_cover' => true])
        ->assertSessionHasNoErrors();

    expect($first->fresh()->is_cover)->toBeFalse()
        ->and($second->fresh()->is_cover)->toBeTrue();
});

test('deleting the cover image promotes the next image', function (): void {
    $cover = MuseumImage::create([
        'museum_id' => $this->museum->id,
        'path' => 'museums/1/cover.webp',
        'sort_order' => 0,
        'is_cover' => true,
    ]);

    $next = MuseumImage::create([
        'museum_id' => $this->museum->id,
        'path' => 'museums/1/next.webp',
        'sort_order' => 1,
        'is_cover' => false,
    ]);

    $this->actingAs($this->organizer)
        ->delete("/organizer/museums/{$this->museum->slug}/images/{$cover->id}")
        ->assertSessionHasNoErrors();

    expect(MuseumImage::find($cover->id))->toBeNull()
        ->and($next->fresh()->is_cover)->toBeTrue();
});

test('organizer cannot delete an image belonging to another museum', function (): void {
    $foreignImage = MuseumImage::create([
        'museum_id' => $this->foreignMuseum->id,
        'path' => 'museums/2/foreign.webp',
        'sort_order' => 0,
        'is_cover' => true,
    ]);

    $this->actingAs($this->organizer)
        ->delete("/organizer/museums/{$this->foreignMuseum->slug}/images/{$foreignImage->id}")
        ->assertForbidden();

    expect(MuseumImage::find($foreignImage->id))->not->toBeNull();
});

test('image cannot be manipulated through a different museum route', function (): void {
    $image = MuseumImage::create([
        'museum_id' => $this->foreignMuseum->id,
        'path' => 'museums/2/foreign.webp',
        'sort_order' => 0,
        'is_cover' => true,
    ]);

    $this->actingAs($this->organizer)
        ->delete("/organizer/museums/{$this->museum->slug}/images/{$image->id}")
        ->assertNotFound();
});
