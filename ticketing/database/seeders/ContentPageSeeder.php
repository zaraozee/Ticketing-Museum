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
            'content' => 'Kebijakan Privasi ini menjelaskan data pribadi yang dikumpulkan MusenTix saat Anda memesan tiket museum, tujuan penggunaannya, serta hak Anda atas data tersebut.'
                ."\n\n".'Data yang kami kumpulkan: (1) identitas pemesan berupa nama, alamat email, dan nomor telepon; (2) data transaksi berupa museum, tanggal kunjungan, jumlah tiket, dan nominal pembayaran; (3) data teknis berupa alamat IP dan jenis perangkat untuk keamanan serta pencegahan penyalahgunaan.'
                ."\n\n".'Kami tidak menyimpan nomor kartu atau kredensial pembayaran Anda. Seluruh pemrosesan pembayaran dilakukan oleh Midtrans sebagai penyedia layanan pembayaran; MusenTix hanya menerima status dan nomor referensi transaksi.'
                ."\n\n".'Tujuan penggunaan: menerbitkan dan memverifikasi tiket, mengirim email e-ticket dan pengingat kunjungan, menangani pengaduan atau refund, serta memenuhi kewajiban audit dan hukum yang berlaku.'
                ."\n\n".'Berbagi data: data pemesanan diteruskan kepada pengelola museum yang bersangkutan sebatas kebutuhan verifikasi kunjungan. Kami tidak menjual data pribadi kepada pihak ketiga.'
                ."\n\n".'Penyimpanan: data transaksi disimpan selama diperlukan untuk keperluan audit dan kewajiban hukum. Setelah itu data dihapus atau dianonimkan.'
                ."\n\n".'Hak Anda: Anda dapat meminta akses, pembetulan, atau penghapusan data pribadi Anda, serta menarik persetujuan atas penggunaan tertentu, dengan menghubungi pengelola museum atau admin platform.'
                ."\n\n".'Keamanan: akses ke data pribadi dibatasi berdasarkan peran, koneksi memakai HTTPS, dan kata sandi disimpan dalam bentuk hash.'
                ."\n\n".'Kontak pengaduan data pribadi: privasi@musentix.id. Kami akan menanggapi permintaan Anda dalam waktu 14 hari kerja.',
            'sort_order' => 1,
        ],
        [
            'title' => 'Syarat dan Ketentuan',
            'type' => ContentPageType::PAGE,
            'content' => 'Dengan menggunakan layanan MusenTix, Anda menyetujui Syarat dan Ketentuan berikut. Layanan ini menghubungkan pengunjung dengan pengelola museum untuk pemesanan tiket secara daring.'
                ."\n\n".'Pemesanan: setiap tiket hanya berlaku untuk satu kali kunjungan pada tanggal dan slot yang dipilih. Kuota ditahan selama 15 menit sejak pemesanan dibuat; bila pembayaran belum diterima hingga batas waktu tersebut, pesanan otomatis dibatalkan dan kuota dilepas kembali.'
                ."\n\n".'Pembayaran: transaksi diproses oleh Midtrans. Tiket diterbitkan setelah pembayaran terverifikasi. MusenTix tidak menyimpan data kartu Anda.'
                ."\n\n".'Penggunaan tiket: tiket wajib ditunjukkan dalam bentuk QR kepada petugas gate. Setiap QR hanya dapat dipakai satu kali. Tiket yang telah dipakai, dibatalkan, atau kedaluwarsa tidak dapat digunakan kembali.'
                ."\n\n".'Penyalahgunaan: penggunaan ganda, pemalsuan QR, atau upaya mengakses akun dan data milik orang lain dapat mengakibatkan pembatalan tiket tanpa pengembalian dana dan pemblokiran akun.'
                ."\n\n".'Pembatalan dan penjadwalan ulang: kebijakan pembatalan dan penjadwalan ulang ditetapkan oleh masing-masing museum. Silakan menghubungi pengelola museum yang bersangkutan.'
                ."\n\n".'Refund: pengembalian dana diproses melalui Midtrans sesuai kebijakan museum. Waktu pengembalian dana mengikuti ketentuan penyedia layanan pembayaran.'
                ."\n\n".'Perubahan layanan: MusenTix dapat memperbarui Syarat dan Ketentuan ini. Perubahan berlaku setelah dipublikasikan pada halaman ini.'
                ."\n\n".'Hukum yang berlaku: Syarat dan Ketentuan ini tunduk pada hukum yang berlaku di Republik Indonesia.',
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
