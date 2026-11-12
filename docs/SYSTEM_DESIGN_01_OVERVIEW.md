# SYSTEM DESIGN — Part 1: Overview & Decisions

> **Bagian dari:** SYSTEM_DESIGN.md (dipecah untuk menghindari content length threshold)
> **Isi:** Ringkasan proyek, keputusan, referensi, masalah, tujuan, role & hak akses

| | |
|---|---|
| **Versi dokumen** | 1.0 (Tahap 1: analisis dan perancangan) |
| **Tanggal** | 2 November 2026 |
| **Status** | Menunggu persetujuan eksplisit sebelum implementasi |
| **Dokumen terkait** | `ERD.md`, `UML.md`, `THIRD_PARTY_INTEGRATIONS.md`, `ROADMAP.md`, `AGENTS.md` |
| **Target development** | Google Antigravity (lihat `AGENTS.md`) |

> **Cara membaca dokumen ini.** Setiap keputusan diberi ID (`D-xx`), kebutuhan diberi ID (`FR-xx`, `NFR-xx`), aturan bisnis diberi ID (`BR-xx`). ID ini dipakai konsisten di semua dokumen supaya agen development dapat menelusuri kebutuhan → tabel → diagram → fase.

---

## 1. Ringkasan Proyek

Aplikasi ini adalah **platform pemesanan tiket museum online** berbasis **Progressive Web App (PWA)**. Pengunjung mencari museum, memilih jenis tiket, tanggal, dan slot waktu, membayar lewat Midtrans, lalu menerima e-ticket ber-QR yang dipindai petugas di gerbang. Pengelola museum (Organizer) mengelola konten, tiket, jadwal, kuota, dan laporan. Admin Platform memverifikasi pengelola/museum dan memantau seluruh transaksi.

**Prioritas teknis utama** (urutan sesuai file acuan): (1) konsistensi kuota tanpa overbooking, (2) keamanan pembayaran (webhook terverifikasi dan idempotent), (3) validitas tiket (satu tiket hanya lolos scan sekali).

### 1.1 Keputusan yang ditetapkan (hasil persetujuan default klarifikasi)

| ID | Keputusan | Catatan |
|---|---|---|
| D-01 | Platform **multi-museum, multi-organizer** | Satu database bersama, isolasi data lewat `museum_id`/`organizer_id` |
| D-02 | **Modular monolith** (satu aplikasi Laravel), bukan microservices | Sederhana, mudah di-deploy dan di-debug |
| D-03 | **Laravel 13** (PHP 8.3+) | **[Fakta]** per pencarian saat penyusunan: Laravel 13 dirilis 17 Maret 2026 dan memerlukan PHP 8.3+. **[Verifikasi]** versi patch terbaru saat setup proyek |
| D-04 | **PostgreSQL 16+** sebagai database utama | Skema dijaga portabel ke MySQL 8 (lihat `ERD.md` §1) |
| D-05 | **Inertia.js + Vue 3 + TypeScript + Tailwind CSS** | Satu codebase, UX mobile-first, mudah dijadikan PWA |
| D-06 | **Redis** untuk queue, cache, rate limiter (sesi tetap di database atau Redis) | Satu layanan untuk tiga kebutuhan |
| D-07 | Payment gateway **Midtrans Snap**; **refund manual** via dashboard Midtrans (tercatat otomatis lewat webhook) | Refund otomatis = tahap lanjutan |
| D-08 | Slot waktu **opsional per museum** (`booking_mode`: `daily` atau `timed_slot`) | Kuota selalu disimpan di entitas `visit_slots` |
| D-09 | Satu booking = **satu museum + satu slot/tanggal**, boleh berisi beberapa jenis tiket | |
| D-10 | **Checkout wajib login** (tanpa guest checkout) | **[Asumsi A11]** Memudahkan riwayat, e-ticket, dan pencegahan penyalahgunaan |
| D-11 | Data pengunjung di MVP: **hanya data pemesan**; satu booking dengan N tiket menghasilkan N baris `tickets` | Kolom `holder_name` disiapkan (nullable) untuk tahap lanjutan |
| D-12 | **Gate Staff = permission turunan Organizer** lewat tabel `museum_staff`, bukan role global | Role global hanya `admin`, `organizer`, `user` |
| D-13 | Validasi scan **online saja** di MVP | Mode offline = tahap lanjutan (lihat `THIRD_PARTY_INTEGRATIONS.md` §9.6) |
| D-14 | Notifikasi MVP: **email + in-app**. Web push (FCM) = tahap lanjutan. Pusher **tidak** dipakai | |
| D-15 | Login sosial MVP: **Google** (prioritas *should-have*). Facebook dan TikTok = tahap lanjutan | |
| D-16 | Google Maps MVP: koordinat + tautan arah + embed; Google Calendar MVP: **tautan "Tambah ke Kalender" + file .ics**, tanpa OAuth | |
| D-17 | AI Chatbot (Gemini) = **tahap lanjutan** | |
| D-18 | Primary key `bigint`; entitas yang terekspos ke publik memakai `public_id` **ULID** (char 26) | Mencegah enumerasi ID |
| D-19 | Uang disimpan sebagai **integer rupiah** (`bigint`, kolom berakhiran `_amount`), mata uang `IDR` | Tanpa floating point |
| D-20 | Batas waktu pembayaran default **15 menit** (`app_settings.payment_window_minutes`) | Dapat diubah Admin |
| D-21 | Role global disimpan sebagai kolom `users.role` + Laravel Policies (tanpa package permission) | Cukup untuk 3 role; dapat dimigrasi ke package permission bila role bertambah |

### 1.1a Keputusan tambahan (Fase 3)

| ID | Keputusan | Catatan |
|---|---|---|
| D-22 | Status museum: `draft`, `pending_review`, `published`, `rejected`, `suspended`, `archived` | Menyatukan BR-21 (`published/rejected`), §12.3 (`suspended`), dan ERD (`archived`). `approved` dihapus karena bermakna sama dengan `published` |
| D-23 | Fasilitas memakai **master `facilities` + pivot `museum_facility`** | Memenuhi FR-ADM-05 (Admin mengelola master) dan menjaga konsistensi penamaan fasilitas lintas museum. `museum_facilities` (denormalisasi) dibatalkan |
| D-24 | Video museum disimpan sebagai `museums.youtube_video_id` (char 11) | Sesuai THIRD_PARTY §6: backend mengekstrak ID dari URL, regex `^[A-Za-z0-9_-]{11}$`; tidak menyimpan HTML/iframe pengguna |
| D-25 | Nama kolom mengikuti BR/D-08: `max_tickets_per_booking`, `entry_early_minutes`, `entry_late_minutes`, `booking_mode = daily\|timed_slot` | ERD disesuaikan. Dua kolom grace menggantikan `entry_grace_minutes` agar jendela scan eksplisit (BR-15) |

### 1.1b Keputusan tambahan (Fase 4)

| ID | Keputusan | Catatan |
|---|---|---|
| D-26 | Tabel kuota bernama **`visit_slots`** (bukan `visit_sessions`), kolom **`reserved_count`** (bukan `held_count`), dan tabel **`slot_templates`** berdiri sendiri | ERD §3.3 sebelumnya menyimpang dari dokumen ini (D-08, BR-05, §10, §13) dan dari ROADMAP Fase 4. Dua dari tiga dokumen sepakat pada `visit_slots`/`reserved_count`/`slot_templates`, sehingga ERD disesuaikan agar konsisten. FK di `bookings` menjadi `visit_slot_id` |
| D-27 | Override kuota per tanggal disimpan di tabel **`slot_capacity_overrides`** (`museum_id`, `visit_date`, `start_time` nullable, `capacity`), bukan dengan mengubah `visit_slots.capacity` | `slots:generate` bersifat idempoten dan tidak boleh menimpa override. Override dibaca saat generate/regenerate; menghapus override mengembalikan kuota ke nilai template. UQ `(museum_id, visit_date, start_time)` dengan `COALESCE` untuk mode `daily` |
| D-28 | `slots:generate` **idempoten** dan hanya membuat slot yang belum ada (`updateOrCreate` pada kunci `(museum_id, visit_date, start_time)`); tidak pernah menurunkan `capacity` slot yang sudah punya `reserved_count + sold_count` | Memenuhi acceptance criteria Fase 4 butir 1 dan BR-20. Slot yang sudah ada tidak dihapus agar booking lama tetap valid |

### 1.1c Keputusan tambahan (Fase 5)

| ID | Keputusan | Catatan |
|---|---|---|
| D-29 | Fase 5 **menerbitkan tiket** (tabel `tickets` + `TicketIssuer`) walau QR/scan/offline baru di Fase 6 | BR-07 mewajibkan tiket terbit dalam transaksi konfirmasi, dan AC-3 Fase 5 ("webhook duplikat tidak membuat tiket ganda") tidak dapat diuji tanpa tabel `tickets`. Fase 6 melengkapi token QR, halaman e-ticket, scanner, dan penyimpanan offline |
| D-30 | Status tiket: **`active`, `used`, `void`, `expired`** (mengikuti §11.2), bukan `issued`/`used`/`void` seperti ERD §3.5 | §11.2 adalah state machine eksplisit dan satu-satunya tempat yang mendefinisikan transisi tiket; `issued` diganti `active` agar konsisten dengan tabel "kondisi terlarang" (`used → active`). ERD disesuaikan |
| D-31 | Gateway pembayaran dikurung di balik **`MidtransClient` (interface)** dengan dua implementasi: `HttpMidtransClient` (dipakai bila kunci tersedia) dan `FakeMidtransClient` (pengujian) | Memenuhi §1.6 "logika gateway dikurung di `Services/Payment`". Pengujian tidak boleh bergantung jaringan; tanpa kredensial sandbox, `HttpMidtransClient` tidak pernah dipanggil diam-diam |
| D-32 | `config/services.php` belum punya entri `midtrans` padahal `.env.example` sudah mendefinisikan 4 variabelnya | Ditambahkan di Fase 5 agar variabel benar-benar terbaca. Sebelumnya variabel tersebut tidak berpengaruh apa pun |
| D-33 | Nominal uang disimpan sebagai **integer rupiah** (`bigint`), tidak pernah float | Konsisten dengan `ticket_types.price`; menghindari pembulatan pada perbandingan `gross_amount` (BR-11) |
| D-34 | Token QR memakai **peta kunci** `TICKET_SIGNING_KEYS` (`kid => secret`) dengan format `MT1.<kid>.<uuid>.<sig>`, bukan satu nilai `APP_TICKET_SECRET` seperti ERD §5 | §11.3 dan `.env.example` (sejak Fase 1) sudah memakai bentuk peta agar kunci dapat **dirotasi** tanpa membuat seluruh tiket lama tidak valid. ERD §5 disesuaikan |

### 1.2 Di luar cakupan MVP (tahap lanjutan / opsional)

Refund otomatis, reschedule booking, kode promo/voucher, tiket rombongan, nama pengunjung per tiket, kuota per jenis tiket, harga terjadwal (`ticket_prices`), review dan wishlist, web push FCM, login Facebook/TikTok, chatbot AI, mode scan offline, aplikasi native, payout/komisi ke museum.

---

**[Lihat bagian lanjutan di file SYSTEM_DESIGN_02_REQUIREMENTS.md]**
