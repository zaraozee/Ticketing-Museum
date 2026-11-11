<?php

use App\Enums\ContentPageType;
use App\Models\ContentPage;

test('only published faqs and pages are listed on the help page', function (): void {
    ContentPage::create([
        'title' => 'Bagaimana cara memesan tiket?',
        'slug' => 'cara-memesan-tiket',
        'type' => ContentPageType::FAQ,
        'content' => 'Pilih museum lalu pilih slot kunjungan.',
        'is_published' => true,
    ]);

    ContentPage::create([
        'title' => 'Kebijakan Privasi',
        'slug' => 'kebijakan-privasi',
        'type' => ContentPageType::PAGE,
        'content' => 'Kami menghormati privasi Anda.',
        'is_published' => true,
    ]);

    ContentPage::create([
        'title' => 'FAQ Draf',
        'slug' => 'faq-draf',
        'type' => ContentPageType::FAQ,
        'content' => 'Belum tayang.',
        'is_published' => false,
    ]);

    $response = $this->get('/bantuan');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/ContentPages/Index')
        ->has('faqs', 1)
        ->where('faqs.0.slug', 'cara-memesan-tiket')
        ->has('pages', 1)
        ->where('pages.0.slug', 'kebijakan-privasi')
    );
});

test('a published content page is accessible by slug', function (): void {
    $page = ContentPage::create([
        'title' => 'Kebijakan Privasi',
        'slug' => 'kebijakan-privasi',
        'type' => ContentPageType::PAGE,
        'content' => 'Kami menghormati privasi Anda.',
        'is_published' => true,
    ]);

    $this->get("/bantuan/{$page->slug}")
        ->assertOk()
        ->assertInertia(fn ($assert) => $assert
            ->component('Public/ContentPages/Show')
            ->where('page.title', 'Kebijakan Privasi')
        );
});

test('an unpublished content page returns 404', function (): void {
    $page = ContentPage::create([
        'title' => 'Halaman Draf',
        'slug' => 'halaman-draf',
        'type' => ContentPageType::PAGE,
        'content' => 'Belum tayang.',
        'is_published' => false,
    ]);

    $this->get("/bantuan/{$page->slug}")->assertNotFound();
});
