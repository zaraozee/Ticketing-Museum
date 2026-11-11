<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\MuseumCategory;
use Illuminate\Database\Seeder;

/**
 * Master data awal untuk Fase 3 (ROADMAP deliverable: seed kategori/fasilitas).
 */
class MuseumMasterDataSeeder extends Seeder
{
    /**
     * @var array<int, string>
     */
    private const CATEGORIES = [
        'Museum Sejarah',
        'Museum Seni',
        'Museum Budaya',
        'Museum Sains dan Teknologi',
        'Museum Arkeologi',
        'Museum Maritim',
        'Museum Geologi',
        'Galeri dan Ruang Pamer',
    ];

    /**
     * @var array<int, array{0: string, 1: string}>
     */
    private const FACILITIES = [
        ['Toilet', 'toilet'],
        ['Musala', 'mosque'],
        ['Area Parkir', 'parking'],
        ['Kafetaria', 'cafe'],
        ['Toko Suvenir', 'shop'],
        ['Lift', 'elevator'],
        ['Ruang Laktasi', 'baby'],
        ['Akses Difabel', 'accessibility'],
        ['Pemandu Wisata', 'guide'],
        ['WiFi Gratis', 'wifi'],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $index => $name) {
            MuseumCategory::firstOrCreate(
                ['slug' => str()->slug($name)],
                [
                    'name' => $name,
                    'is_active' => true,
                    'sort_order' => $index,
                ],
            );
        }

        foreach (self::FACILITIES as $index => [$name, $icon]) {
            Facility::firstOrCreate(
                ['name' => $name],
                [
                    'icon' => $icon,
                    'is_active' => true,
                    'sort_order' => $index,
                ],
            );
        }
    }
}
