<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

class AppSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'payment_window_minutes',
                'value' => '15',
                'description' => 'Batas waktu pembayaran pemesanan tiket (menit)',
            ],
            [
                'key' => 'max_tickets_per_booking',
                'value' => '10',
                'description' => 'Maksimal jumlah tiket per transaksi pemesanan',
            ],
            [
                'key' => 'booking_window_days',
                'value' => '60',
                'description' => 'Rentang hari pemesanan ke depan yang diizinkan',
            ],
            [
                'key' => 'entry_grace_minutes',
                'value' => '15',
                'description' => 'Toleransi waktu kedatangan tiket (menit)',
            ],
        ];

        foreach ($settings as $setting) {
            AppSetting::firstOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value'], 'description' => $setting['description']]
            );
        }
    }
}
