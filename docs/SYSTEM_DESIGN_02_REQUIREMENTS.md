# SYSTEM DESIGN — Part 2: Requirements

> **Bagian dari:** SYSTEM_DESIGN.md (dipecah untuk menghindari content length threshold)
> **Isi:** Role & hak akses, kebutuhan fungsional & nonfungsional

---

## 6. Role dan Hak Akses

### 6.1 Role

| Role | Penyimpanan | Lingkup data |
|---|---|---|
| **Admin Platform** | `users.role = admin` | Seluruh platform |
| **Organizer** | `users.role = organizer` + `organizer_profiles`; memiliki `museums` | Hanya museum miliknya (`museums.organizer_id`) |
| **User / Pengunjung** | `users.role = user` | Hanya data miliknya sendiri |
| **Gate Staff** | Bukan role global. `users.role = user` + baris `museum_staff` (`staff_role = gate_staff`) | Hanya fitur scan untuk museum yang ditugaskan |

**Keputusan Gate Staff (D-12) [Rekomendasi]:** cukup sebagai **permission turunan Organizer** pada MVP. Alasan: kebutuhan petugas gate hanya satu (scan dan lihat riwayat), data akses selalu terikat museum tertentu, dan role global tambahan menambah kompleksitas otorisasi.

### 6.2 Matriks hak akses

Legenda: ✅ penuh · 🔒 hanya milik sendiri / museum yang ditugaskan · ❌ tidak boleh · ◑ sebagian

| Fitur | Guest | User | Gate Staff | Organizer | Admin |
|---|:--:|:--:|:--:|:--:|:--:|
| Lihat daftar dan detail museum (published) | ✅ | ✅ | ✅ | ✅ | ✅ |
| Registrasi, login, lupa password | ✅ | – | – | – | – |
| Kelola profil akun sendiri | ❌ | 🔒 | 🔒 | 🔒 | 🔒 |
| Membuat booking dan membayar | ❌ | ✅ | ✅* | ✅* | ✅* |
| Lihat riwayat booking dan e-ticket sendiri | ❌ | 🔒 | 🔒 | 🔒 | 🔒 |
| Daftar sebagai organizer | ✅ | ✅ | ❌ | – | – |
| Kelola profil museum, galeri, fasilitas | ❌ | ❌ | ❌ | 🔒 | ✅ |
| Kelola jenis dan harga tiket | ❌ | ❌ | ❌ | 🔒 | ✅ |
| Ajukan museum untuk verifikasi | ❌ | ❌ | ❌ | 🔒 | – |
| Verifikasi/moderasi museum dan organizer | ❌ | ❌ | ❌ | ❌ | ✅ |
| Pindai tiket di gerbang | ❌ | ❌ | 🔒 | 🔒 | ❌** |
| Laporan penjualan dan kunjungan | ❌ | ❌ | ❌ | 🔒 | ✅ |

\* Selama login, semua role terautentikasi dapat membeli tiket sebagai pengunjung biasa.
\** Admin tidak memindai tiket; jika dibutuhkan untuk dukungan operasional, tambahkan sebagai staf museum tertentu.

---

## 7. Kebutuhan Fungsional dan Nonfungsional

Prioritas: **M** = MVP wajib · **S** = MVP *should-have* · **L** = tahap lanjutan · **O** = opsional.

### 7.1 Kebutuhan fungsional

**Autentikasi dan akun (AUTH)**

| ID | Kebutuhan | Prio |
|---|---|:--:|
| FR-AUTH-01 | Registrasi email + password | M |
| FR-AUTH-02 | Verifikasi email | M |
| FR-AUTH-03 | Login dan logout | M |
| FR-AUTH-04 | Lupa dan reset password | M |
| FR-AUTH-05 | Login dengan Google | S |
| FR-AUTH-06 | Login Facebook / TikTok | L |
| FR-AUTH-07 | Kelola profil dan ganti password | M |
| FR-AUTH-08 | Pendaftaran organizer mandiri | M |

**Museum dan pencarian (MUS)**

| ID | Kebutuhan | Prio |
|---|---|:--:|
| FR-MUS-01 | Daftar museum berstatus `published` | M |
| FR-MUS-02 | Pencarian nama dan filter kategori/kota | M |
| FR-MUS-03 | Detail museum: galeri, deskripsi, fasilitas, jam operasional, harga mulai dari, video YouTube | M |
| FR-MUS-04 | Peta lokasi dan tautan petunjuk arah | M |
| FR-MUS-05 | Wishlist | O |
| FR-MUS-06 | Review dan rating | O |

**Booking (BKG)**

| ID | Kebutuhan | Prio |
|---|---|:--:|
| FR-BKG-01 | Memilih jenis tiket dan jumlah (≤ `max_tickets_per_booking`) | M |
| FR-BKG-02 | Memilih tanggal kunjungan dengan kalender ketersediaan | M |
| FR-BKG-03 | Memilih slot waktu (museum `timed_slot`) | M |
| FR-BKG-04 | Pengecekan ketersediaan kuota real-time | M |
| FR-BKG-05 | Mengisi data pemesan (nama, email, telepon) | M |
| FR-BKG-06 | Membuat booking, menahan kuota, menetapkan batas waktu bayar | M |
| FR-BKG-07 | Membatalkan booking `pending_payment` oleh pemilik | M |
| FR-BKG-08 | Riwayat dan detail booking | M |
| FR-BKG-09 | Melanjutkan pembayaran booking yang masih pending | M |
| FR-BKG-10 | Reschedule booking | L |
| FR-BKG-11 | Refund otomatis | L |
| FR-BKG-12 | Kode promo/voucher | L |

**Pembayaran (PAY)**

| ID | Kebutuhan | Prio |
|---|---|:--:|
| FR-PAY-01 | Membuat transaksi Midtrans Snap dari backend | M |
| FR-PAY-02 | Menerima webhook, verifikasi signature | M |
| FR-PAY-03 | Idempotensi pemrosesan webhook | M |
| FR-PAY-04 | Verifikasi status ke API Midtrans sebelum mengubah booking | M |
| FR-PAY-05 | Halaman status pembayaran (polling status dari backend) | M |
| FR-PAY-06 | Expire otomatis dan pelepasan kuota | M |
| FR-PAY-07 | Deteksi anomali pembayaran | M |
| FR-PAY-08 | Mencatat refund/chargeback via webhook | M |

**E-ticket (TIX)**

| ID | Kebutuhan | Prio |
|---|---|:--:|
| FR-TIX-01 | Menerbitkan satu tiket per orang setelah booking `confirmed` | M |
| FR-TIX-02 | E-ticket dengan QR (token bertanda tangan) dan kode manual | M |
| FR-TIX-03 | Halaman My Tickets dan detail tiket | M |
| FR-TIX-04 | Pengiriman e-ticket lewat email | M |
| FR-TIX-05 | Penyimpanan tiket untuk tampilan offline | M |
| FR-TIX-06 | Tambah ke Google Calendar (URL) dan unduh .ics | M |
| FR-TIX-07 | Unduh/cetak tiket PDF | L |

**Gate (GATE)**

| ID | Kebutuhan | Prio |
|---|---|:--:|
| FR-GATE-01 | Scanner QR via kamera perangkat petugas | M |
| FR-GATE-02 | Validasi online dan penandaan `used` secara atomik | M |
| FR-GATE-03 | Hasil scan visual dengan status berbeda | M |
| FR-GATE-04 | Input manual kode tiket sebagai cadangan | M |
| FR-GATE-05 | Riwayat scan | M |
| FR-GATE-06 | Idempotensi retry scan (`client_scan_uuid`) | M |
| FR-GATE-07 | Mode scan offline | L |

**Organizer (ORG)**

| ID | Kebutuhan | Prio |
|---|---|:--:|
| FR-ORG-01 | Dashboard statistik museum | M |
| FR-ORG-02 | Kelola profil museum, alamat, koordinat | M |
| FR-ORG-03 | Kelola galeri foto | M |
| FR-ORG-04 | Kelola fasilitas (dari master) | M |
| FR-ORG-05 | Kelola jam operasional dan tanggal tutup | M |
| FR-ORG-06 | Kelola jenis tiket dan harga | M |
| FR-ORG-07 | Kelola template slot dan kuota; override kuota per tanggal | M |
| FR-ORG-08 | Daftar booking dan status pembayaran | M |
| FR-ORG-09 | Daftar pengunjung per tanggal/slot | M |
| FR-ORG-10 | Kelola staf gate | M |
| FR-ORG-11 | Laporan penjualan dan kunjungan, ekspor CSV | M |
| FR-ORG-12 | Ajukan museum untuk verifikasi | M |

**Admin (ADM)**

| ID | Kebutuhan | Prio |
|---|---|:--:|
| FR-ADM-01 | Dashboard statistik platform | M |
| FR-ADM-02 | Kelola akun user (suspend/aktifkan) | M |
| FR-ADM-03 | Verifikasi organizer | M |
| FR-ADM-04 | Verifikasi dan moderasi museum | M |
| FR-ADM-05 | Kelola kategori dan fasilitas master | M |
| FR-ADM-06 | Pantau semua transaksi dan antrean anomali | M |
| FR-ADM-07 | Laporan lintas museum | M |
| FR-ADM-08 | Pengaturan sistem (`app_settings`) | M |
| FR-ADM-09 | Kelola konten umum dan FAQ | M |
| FR-ADM-10 | Melihat activity log | M |

**Notifikasi & PWA**

| ID | Kebutuhan | Prio |
|---|---|:--:|
| FR-NTF-01 | Email transaksional | M |
| FR-NTF-02 | Notifikasi in-app | M |
| FR-NTF-03 | Pengingat kunjungan H-1 | S |
| FR-NTF-04 | Web push (FCM) | L |
| FR-PWA-01 | Web App Manifest dan installability | M |
| FR-PWA-02 | Service worker dan strategi cache | M |
| FR-PWA-03 | Halaman fallback offline | M |
| FR-PWA-04 | UI ajakan instal | M |

### 7.2 Kebutuhan nonfungsional

| ID | Kategori | Kebutuhan | Target |
|---|---|---|---|
| NFR-01 | Performa | Waktu respons server halaman daftar/detail museum | p95 ≤ 500 ms |
| NFR-02 | Performa | Core Web Vitals di jaringan 4G | LCP ≤ 2,5 s; Lighthouse ≥ 85 |
| NFR-03 | Performa | Validasi scan end-to-end | p95 ≤ 1 s |
| NFR-04 | Konkurensi | N pengguna memesan kuota terakhir bersamaan | Tepat satu berhasil; tidak ada overbooking |
| NFR-05 | Ketersediaan | Uptime layanan | ≥ 99,5% per bulan |
| NFR-06 | Integritas | Idempotensi webhook, atomisitas scan | Dijamin di level database dan aplikasi |
| NFR-07 | Keamanan | Sesuai OWASP ASVS L1 | Tidak ada temuan kritis |
| NFR-08 | Privasi | Minimasi data pribadi, kebijakan privasi | Kepatuhan UU PDP |
| NFR-09 | Skalabilitas | Dapat naik dengan menambah worker/replica | Stateless web, sesi di Redis |
| NFR-10 | Usability | Mobile-first, tombol ≥ 44 px | WCAG 2.1 AA sebagai target |
| NFR-11 | Kompatibilitas | Chrome/Edge/Firefox/Safari terbaru; Android 9+; iOS 16.4+ | |
| NFR-12 | Maintainability | Coding standard, analisis statis, cakupan tes ≥ 70% | |
| NFR-13 | Observability | Log terstruktur, error tracking, metrik | |
| NFR-14 | Backup | RPO ≤ 24 jam, RTO ≤ 4 jam | |
| NFR-15 | Zona waktu | Penyimpanan UTC; zona waktu museum | WIB/WITA/WIT |
| NFR-16 | i18n | UI Bahasa Indonesia; struktur siap multi-bahasa | |
| NFR-17 | Auditabilitas | Data transaksi tidak dihapus; snapshot nama/harga | |

---

**[Lihat bagian lanjutan di SYSTEM_DESIGN_03_ARCHITECTURE.md]**
