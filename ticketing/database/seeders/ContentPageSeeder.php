<?php

namespace Database\Seeders;

use App\Enums\ContentPageType;
use App\Models\ContentPage;
use Illuminate\Database\Seeder;

/**
 * Konten awal untuk halaman /bantuan (FR-ADM-09).
 */
class ContentPageSeeder extends Seeder
{
    /**
     * @var array<int, array{title: string, type: ContentPageType, content: string, sort_order: int}>
     */
    private const PAGES = [
        [
            'title' => 'Bagaimana cara memesan tiket museum?',
            'type' => ContentPageType::FAQ,
            'content' => 'Buka halaman Jelajah Museum, pilih museum yang ingin dikunjungi, tentukan tanggal dan slot kunjungan, lalu lanjutkan pembayaran. E-ticket berisi QR akan dikirim setelah pembayaran terkonfirmasi.',
            'sort_order' => 1,
        ],
        [
            'title' => 'Apakah e-ticket bisa digunakan tanpa koneksi internet?',
            'type' => ContentPageType::FAQ,
            'content' => 'Bisa. E-ticket yang pernah dibuka akan tersimpan di perangkat sehingga QR tetap tampil saat jaringan tidak tersedia. Validitas tiket tetap ditentukan oleh server saat pemindaian di gate.',
            'sort_order' => 2,
        ],
        [
            'title' => 'Berapa lama batas waktu pembayaran?',
            'type' => ContentPageType::FAQ,
            'content' => 'Pemesanan berlaku selama 15 menit. Bila pembayaran belum diterima hingga batas waktu tersebut, kuota slot otomatis dilepas kembali untuk pengunjung lain.',
            'sort_order' => 3,
        ],
        [
            'title' => 'Apakah tiket bisa dibatalkan atau dijadwalkan ulang?',
            'type' => ContentPageType::FAQ,
            'content' => 'Kebijakan pembatalan dan penjadwalan ulang mengikuti ketentuan masing-masing museum. Silakan hubungi pengelola museum terkait melalui kontak yang tertera di halaman museum.',
            'sort_order' => 4,
        ],
        [
            'title' => 'Berapa tiket yang boleh dipesan dalam satu transaksi?',
            'type' => ContentPageType::FAQ,
            'content' => 'Jumlah tiket per pemesanan dibatasi oleh pengaturan museum, umumnya maksimal 10 tiket. Batas tersebut ditampilkan pada halaman detail museum.',
            'sort_order' => 5,
        ],
        [
            'title' => 'Kebijakan Privasi',
            'type' => ContentPageType::PAGE,
            'content' => 'Kami hanya mengumpulkan data yang diperlukan untuk memproses pemesanan tiket, yaitu nama, alamat email, dan nomor telepon. Data tidak dijual kepada pihak ketiga. Data transaksi disimpan untuk keperluan audit sesuai ketentuan yang berlaku.',
            'sort_order' => 1,
        ],
        [
            'title' => 'Syarat dan Ketentuan',
            'type' => ContentPageType::PAGE,
            'content' => 'Dengan menggunakan layanan ini, Anda menyetujui bahwa setiap tiket hanya berlaku untuk satu kali kunjungan pada tanggal dan slot yang dipilih. Penyalahgunaan tiket, termasuk penggunaan ganda atau pemalsuan QR, dapat mengakibatkan tiket dibatalkan tanpa pengembalian dana.',
            'sort_order' => 2,
        ],
    ];

    public function run(): void
    {
        foreach (self::PAGES as $page) {
            ContentPage::firstOrCreate(
                ['slug' => str()->slug($page['title'])],
                [
                    'title' => $page['title'],
                    'type' => $page['type'],
                    'content' => $page['content'],
                    'is_published' => true,
                    'sort_order' => $page['sort_order'],
                ],
            );
        }
    }
}
