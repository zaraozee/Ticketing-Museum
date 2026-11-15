# Rencana dan Lembar UAT — MusenTix

Uji terima pengguna (User Acceptance Test) oleh museum pilot sebelum go-live.
Dokumen ini adalah **lembar kerja**: kolom hasil diisi saat UAT berlangsung,
bukan sebelumnya.

Rujukan: `ROADMAP.md` Fase 9 AC-6, `GO_LIVE_CHECKLIST.md` §12, `SOP_GATE.md`.

---

## 1. Informasi pelaksanaan

| | |
|---|---|
| Museum pilot | _diisi_ |
| Perwakilan museum (penanggung jawab) | _diisi_ |
| Peran yang diuji | Pengunjung, petugas gate, pengelola museum, admin platform |
| Lingkungan | Produksi (`https://` domain produksi) |
| Tanggal UAT | _diisi_ |
| Pelaksana teknis | _diisi_ |

> **Mengapa di produksi, bukan di lokal.** UAT harus membuktikan sistem bekerja
> dengan Midtrans produksi, TLS nyata, dan perangkat gate sungguhan. UAT di
> lingkungan pengembangan tidak membuktikan apa pun tentang produksi.

---

## 2. Data uji yang disiapkan

| Kebutuhan | Keterangan |
|---|---|
| Museum pilot | sudah `published`, punya minimal 1 slot kunjungan |
| Tipe tiket | minimal 1 tipe aktif dengan harga nyata |
| Akun pengunjung | email nyata yang dapat menerima email |
| Akun petugas gate | terdaftar di `museum_staff` museum pilot |
| Akun pengelola museum | peran organizer untuk museum pilot |
| Perangkat gate | ponsel/tablet dengan kamera dan internet |
| Kartu/rekening uji | untuk pembayaran bernilai kecil |

---

## 3. Skenario UAT

Setiap baris diisi **Lulus**, **Gagal**, atau **Diterima dengan catatan**.
Skenario yang gagal wajib punya nomor temuan di §5.

### A. Pengunjung — pemesanan dan pembayaran

| # | Langkah | Hasil yang diharapkan | Hasil |
|---|---|---|---|
| A1 | Buka beranda, cari museum pilot | Museum muncul pada hasil pencarian | ☐ |
| A2 | Buka detail museum, pilih tanggal dan slot | Kuota tersisa tampil benar | ☐ |
| A3 | Pilih 2 tiket, isi data pemesan, lanjutkan | Ringkasan pesanan benar; total sesuai harga | ☐ |
| A4 | Selesaikan pembayaran | Halaman pembayaran Midtrans tampil; setelah bayar, pesanan terkonfirmasi | ☐ |
| A5 | Buka **Tiket Saya** | 2 tiket tampil dengan QR dan kode tiket | ☐ |
| A6 | Buka email pemesanan | Email e-ticket diterima, berisi kode tiket | ☐ |
| A7 | Coba bayar pesanan yang sama lagi | Ditolak dengan pesan jelas, bukan error | ☐ |
| A8 | Buat pesanan baru lalu biarkan 15 menit | Pesanan kedaluwarsa; kuota kembali tersedia | ☐ |

### B. Petugas gate — pemindaian

| # | Langkah | Hasil yang diharapkan | Hasil |
|---|---|---|---|
| B1 | Login petugas, buka `/organizer/scanner` | Scanner tampil dengan nama museum | ☐ |
| B2 | Pindai tiket dari A5 | Hasil `valid`; tiket berubah `used` | ☐ |
| B3 | Pindai tiket yang sama lagi | Hasil `already_used` dengan waktu pemakaian pertama | ☐ |
| B4 | Pindai tiket museum lain | Hasil `wrong_museum`; tiket tetap aktif | ☐ |
| B5 | Masukkan kode tiket manual | Tiket ditemukan dan dapat dipakai | ☐ |
| B6 | Masukkan kode manual yang salah berulang | Ditolak dengan pesan jelas; tidak ada error 500 | ☐ |
| B7 | Buka **Riwayat Scan** | Seluruh pemindaian tercatat dengan waktu dan hasil | ☐ |

### C. Pengelola museum — operasional

| # | Langkah | Hasil yang diharapkan | Hasil |
|---|---|---|---|
| C1 | Login pengelola, buka dashboard | Ringkasan kunjungan dan pendapatan tampil | ☐ |
| C2 | Ubah kuota slot | Perubahan tersimpan dan tercermin di halaman publik | ☐ |
| C3 | Tandai tanggal tutup | Tanggal tutup muncul; pemesanan pada tanggal itu ditolak | ☐ |
| C4 | Buka laporan penjualan, ekspor | Berkas terunduh dan angkanya sesuai transaksi UAT | ☐ |
| C5 | Coba akses data museum lain | Ditolak (403/404), bukan menampilkan data | ☐ |

### D. Admin platform — pengawasan

| # | Langkah | Hasil yang diharapkan | Hasil |
|---|---|---|---|
| D1 | Login admin, buka antrean anomali | Halaman `/admin/anomalies` terbuka | ☐ |
| D2 | Tinjau transaksi UAT | Transaksi normal tidak masuk antrean anomali | ☐ |
| D3 | Jalankan `php artisan ops:health --json` | Keluaran JSON; kode keluar 0 saat sehat | ☐ |
| D4 | Periksa log aplikasi | Tidak ada error tak terduga selama UAT | ☐ |

### E. Keamanan dan ketahanan (diamati, bukan diajarkan)

| # | Langkah | Hasil yang diharapkan | Hasil |
|---|---|---|---|
| E1 | Akses halaman admin tanpa login | Dialihkan ke login | ☐ |
| E2 | Login salah 6 kali berturut-turut | Dibatasi (429), bukan percobaan tanpa batas | ☐ |
| E3 | Buka aplikasi di ponsel dengan jaringan lambat | Halaman tetap dapat dipakai | ☐ |
| E4 | Putuskan internet petugas gate | Pesan jelas; tidak ada tiket yang lolos tanpa verifikasi | ☐ |
| E5 | Buka `/bantuan` | Kebijakan Privasi dan Syarat & Ketentuan terbaca | ☐ |

### F. Pembayaran bernilai kecil end-to-end (AC-4)

| # | Langkah | Hasil yang diharapkan | Hasil |
|---|---|---|---|
| F1 | Pesan 1 tiket termurah dengan akun nyata | Pesanan dibuat | ☐ |
| F2 | Bayar dengan instrumen produksi | Pembayaran berhasil di Midtrans | ☐ |
| F3 | Periksa booking | Status `confirmed`, tiket terbit | ☐ |
| F4 | Pindai tiket di gate | Hasil `valid` | ☐ |
| F5 | Proses refund transaksi uji | Status `refunded`; tiket `voided` | ☐ |

> **Catatan AC-4.** Skenario F adalah bukti acceptance criterion (4) pada
> `ROADMAP.md`. Tanpa F2–F5 yang benar-benar dijalankan dengan kredensial
> produksi, AC-4 belum terpenuhi.

---

## 4. Ringkasan hasil

| Kelompok | Jumlah skenario | Lulus | Gagal | Diterima dengan catatan |
|---|---|---|---|---|
| A. Pengunjung | 8 | | | |
| B. Petugas gate | 7 | | | |
| C. Pengelola museum | 5 | | | |
| D. Admin platform | 4 | | | |
| E. Keamanan | 5 | | | |
| F. Pembayaran produksi | 5 | | | |
| **Total** | **34** | | | |

---

## 4A. Ketertelusuran: skenario mana yang sudah dijaga tes otomatis

Sesi UAT memerlukan perwakilan museum dan waktu mereka. Agar waktu itu dipakai
untuk hal yang **hanya dapat dibuktikan manusia** — tampilan, alur nyata,
perangkat gate sungguhan, pembayaran produksi — tabel di bawah memetakan tiap
skenario ke bukti otomatis yang sudah ada di suite.

Kolom **"Masih perlu diuji manual"** adalah yang wajib dijalankan pada sesi UAT.
Kolom **"Bukti otomatis"** adalah pengaman regresi: bila suatu saat perilakunya
rusak, suite akan gagal sebelum sesi UAT berikutnya.

| # | Skenario | Bukti otomatis | Masih perlu diuji manual |
|---|---|---|---|
| A1 | Cari museum pilot | `MuseumCatalogTest` (`public listing can be filtered by search keyword`, kategori, kota) | Tampilan hasil pencarian || A2 | Kuota tersisa tampil benar | `AvailabilityApiTest`, `QuotaServiceTest` | Angka pada layar |
| A3 | Ringkasan pesanan dan total | `BookingFlowTest` (batas jumlah, slot tertutup, tanggal lewat) | Tampilan ringkasan |
| A4 | Pembayaran selesai | `PaymentWebhookTest` (AC-4: konfirmasi + tiket terbit) | **Ya** — popup Snap nyata |
| A5 | Tiket tampil dengan QR | `Phase8PageRegressionTest`, `MuseumTicketDisplayTest` | Tampilan QR |
| A6 | Email e-ticket diterima | `TransactionalEmailTest` (antrean, isi, idempoten) | **Ya** — kotak masuk nyata |
| A7 | Bayar ulang ditolak | `PaymentWebhookTest` (percobaan kedua memakai tagihan yang sama) | Pesan di layar |
| A8 | Pesanan kedaluwarsa | `BookingFlowTest` (perintah expire, idempoten, kuota kembali) | Menunggu 15 menit sungguhan |
| B1 | Scanner tampil | `ScanServiceTest`, `PwaTest` (shortcut scanner) | **Ya** — perangkat gate |
| B2 | Pindai tiket valid | `ScanServiceTest` (`scan valid mengubah tiket menjadi used`) | **Ya** — kamera nyata |
| B3 | Pindai ulang | `ScanServiceTest` (`already_used`) | Pesan di layar |
| B4 | Tiket museum lain | `ScanServiceTest` (`wrong_museum`) | **Ya** — dua museum |
| B5 | Kode manual | `SecurityControlsTest` (throttle kode manual) | **Ya** — input manual |
| B6 | Kode salah berulang | `SecurityControlsTest` (throttle terpisah dari QR) | Pesan di layar |
| B7 | Riwayat scan | `ScanServiceTest` (idempotensi `client_scan_uuid`) | Tampilan riwayat |
| C1 | Dashboard pengelola | `Phase8PageRegressionTest` (halaman laporan) | Angka di layar |
| C2 | Ubah kuota slot | `TicketAndScheduleTest` (BR-20 batas kuota) | Perubahan di halaman publik |
| C3 | Tandai tanggal tutup | `SecurityControlsTest`, `MuseumManagementTest` | **Ya** — alur penuh |
| C4 | Ekspor laporan | `AnomalyAndExportRegressionTest` (isi CSV, angka, kepemilikan) | **Ya** — berkas terbuka di Excel |
| C5 | Akses data museum lain | `MuseumAuthorizationTest`, `Phase8PageRegressionTest` | — |
| D1 | Antrean anomali terbuka | `AnomalyAndExportRegressionTest` | — |
| D2 | Transaksi normal tidak masuk antrean | `AnomalyAndExportRegressionTest` | — |
| D3 | `ops:health --json` | `HealthCheckTest` (6 tes, termasuk keluaran JSON) | **Ya** — dijalankan di produksi |
| D4 | Log bersih | — | **Ya** — log produksi nyata |
| E1 | Admin tanpa login | `RoleAuthorizationTest`, `SecurityControlsTest` | — |
| E2 | Login salah 6 kali | `SecurityControlsTest` (throttle, tidak mengunci IP lain) | **Ya** — diamati |
| E3 | Jaringan lambat | `PwaTest` (service worker, fallback offline) | **Ya** — perangkat nyata |
| E4 | Internet gate putus | `PwaTest` (jalur offline, tidak menyimpan area transaksional) | **Ya** — mode pesawat |
| E5 | Halaman bantuan | `ContentPageTest` | — |
| F1–F5 | Pembayaran produksi | `PaymentWebhookTest` (seluruh rantai di sandbox) | **Ya** — AC-4, wajib produksi |

**Cara membaca tabel ini.** Skenario dengan bukti otomatis **tetap dijalankan**
saat UAT — UAT membuktikan pengalaman manusia, bukan kebenaran logika. Yang
berubah: bila ada yang gagal, penyebabnya dapat dipersempit dengan cepat, dan
regresi tidak menunggu sesi UAT berikutnya untuk ketahuan.

### 4A.1 Temuan dari penyiapan ketertelusuran

Pemetaan ini menemukan dua skenario yang **tidak punya jaring pengaman sama
sekali**: C4 (ekspor laporan) dan D1/D2 (antrean anomali admin). Keduanya
sebelumnya hanya diuji dengan mata.

Saat menulis tesnya, ditemukan satu bug nyata: endpoint
`/organizer/reports/export` memakai `$profile->id` tanpa memeriksa apakah
profil organisasi ada, sedangkan halaman laporannya sendiri sudah menangani
kasus itu dengan paginator kosong. Akibatnya pengelola tanpa profil organisasi
mendapat **HTTP 500** saat mengekspor, bukan 404. Sudah diperbaiki dan dikunci
tes (`the organizer export returns not found instead of a server error without
an organizer profile`).

### 4A.2 Gladi bersih lokal (15 Nov 2026) — bukan UAT resmi

> **Bukan AC-6.** Gladi bersih dijalankan di lingkungan lokal untuk membuang
> bug sebelum sesi museum. `UAT_PLAN.md` §1 menetapkan UAT resmi harus di
> produksi; tanpa deployment, hasil ini tidak membuktikan apa pun tentang
> produksi. Pencatatan di sini hanya agar sesi museum nanti cepat.

34 skenario dijalankan satu per satu melalui HTTP sungguhan (bukan tes unit):
login tiap peran, mengikuti alur pengunjung dari cari museum sampai tiket di
Tiket Saya, pemindaian gate lewat API dengan `client_scan_uuid`, laporan &
ekspor CSV yang angkanya dicocokkan dengan database, dan `ops:health --json`.

| Kelompok | Skenario | Lulus | Gagal | Tidak dapat diuji |
|---|---|---|---|---|
| A. Pengunjung | 8 | 8 | – | – |
| B. Petugas gate | 7 | 7 | – | – |
| C. Pengelola museum | 5 | 5 | – | – |
| D. Admin platform | 4 | 4 | – | – |
| E. Keamanan | 5 | 3 | – | 2 (E3 jaringan lambat, E4 offline gate) |
| F. Pembayaran produksi | 5 | – | – | 5 (butuh Midtrans produksi) |

Empat bug nyata ditemukan dan diperbaiki (lihat `ROADMAP.md` tabel bug #13–#16):

1. Petugas gate mendapat 403 di halaman scanner (rute dijaga `role:organizer`).
2. Halaman scanner 500 saat petugas gate akhirnya dapat membukanya
   (`merge()` Eloquent vs array).
3. Menekan "Bayar" pada pesanan yang sudah lunas gagal diam-diam tanpa pesan.
4. Menandai tanggal tutup tidak menolak pemesanan pada tanggal itu.

Setelah perbaikan, seluruh gate hijau: Pint, PHPStan, Pest **354 tes / 1424
assertions**, `vue-tsc --noEmit`, `npm run build`. Dua skenario (E3, E4) tidak
dapat diuji tanpa perangkat dan jaringan nyata, dan lima skenario F menunggu
Midtrans produksi — keduanya di luar jangkauan gladi bersih lokal.

---

## 5. Daftar temuan

| # | Skenario | Uraian | Tingkat | Rencana tindak lanjut | Status |
|---|---|---|---|---|---|
| 1 | | | | | |
| 2 | | | | | |
| 3 | | | | | |

Tingkat: **Kritis** (menghalangi go-live), **Tinggi** (harus diperbaiki sebelum
go-live), **Sedang** (dapat menyusul dengan jadwal), **Rendah** (perbaikan
kosmetik).

---

## 6. Persetujuan

UAT dinyatakan **disetujui** apabila seluruh skenario kritis berstatus Lulus
dan tidak ada temuan Kritis/Tinggi yang terbuka.

> "Saya telah menjalankan dan mengamati skenario UAT di atas. Sistem MusenTix
> sesuai dengan kebutuhan operasional museum kami, dan saya menyetujui
> penggunaan sistem ini untuk museum yang saya wakili, dengan catatan temuan
> yang tercantum pada bagian 5."

| | |
|---|---|
| Nama perwakilan museum | |
| Jabatan | |
| Tanggal | |
| Tanda tangan | |

| | |
|---|---|
| Nama pelaksana teknis | |
| Tanggal | |
| Tanda tangan | |

Keputusan: ☐ Disetujui ☐ Disetujui dengan catatan ☐ Belum disetujui
