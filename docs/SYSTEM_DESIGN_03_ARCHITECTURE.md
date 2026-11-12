# SYSTEM DESIGN — Part 3: Architecture & Business Rules

> **Bagian dari:** SYSTEM_DESIGN.md (dipecah untuk menghindari content length threshold)
> **Isi:** Arsitektur sistem, tech stack, database, business rules, status transaksi

---

## 8. Arsitektur Sistem

### 8.1 Pendekatan: modular monolith

Satu aplikasi Laravel yang dipisahkan secara logis per modul (Museum, Schedule, Booking, Payment, Ticket, Gate, Reporting, Notification). **Alasan:** tim kecil, transaksi database lintas modul (booking + kuota + tiket) harus atomik, deploy dan debug lebih sederhana.

### 8.2 Komponen utama

| Komponen | Tanggung jawab |
|---|---|
| PWA (Vue + Inertia) | UI, navigasi, service worker, render QR di sisi klien |
| Laravel (web) | Autentikasi, otorisasi, logika bisnis, endpoint API dan webhook |
| Queue worker | Email, pemrosesan webhook, pembuatan tiket, notifikasi |
| Scheduler | Expire booking, generate slot, reminder H-1, rekonsiliasi |
| PostgreSQL | Sumber kebenaran data, constraint, transaksi |
| Redis | Antrean, cache, rate limiting |
| Object storage | Foto museum dan aset unggahan |

### 8.3 Logging dan monitoring

| Aspek | Rancangan MVP |
|---|---|
| Log aplikasi | Log harian berformat JSON + `request_id` |
| Audit log | Tabel `activity_logs` (append-only) |
| Error tracking | Sentry atau setara |
| Monitoring | Uptime monitor, metrik antrean, alarm webhook gagal |
| Backup | `pg_dump` harian + arsip WAL; salinan terenkripsi; uji restore berkala |

---

## 9. Tech Stack

### 9.1 Stack final

| Teknologi | Fungsi | Status |
|---|---|---|
| PHP 8.3+ | Runtime | MVP |
| Laravel 13 | Framework backend | MVP |
| Inertia.js + Vue 3 + TypeScript | Frontend | MVP |
| Tailwind CSS | Styling responsif | MVP |
| `vite-plugin-pwa` (Workbox) | Manifest + service worker | MVP |
| PostgreSQL 16+ | Database | MVP |
| Redis | Queue, cache, rate limit | MVP |
| Laravel Socialite | Login Google | MVP (S) |
| `midtrans/midtrans-php` | Integrasi Midtrans | MVP |
| QR library (endroid/qr-code, qrcode, vue-qrcode-reader) | Generate & scan QR | MVP |
| S3-compatible storage | Foto museum | MVP |
| Intervention Image | Resize unggahan | MVP |
| Laravel Pint, Larastan, Pest | Style, analisis, testing | MVP |
| Sentry | Error tracking | MVP |

### 9.2 Katalog endpoint utama

| Area | Method + Path | Auth | Keterangan |
|---|---|---|---|
| Publik | `GET /museums`, `GET /museums/{slug}` | – | Halaman Inertia |
| Publik | `GET /api/v1/museums/{slug}/availability` | – | Kalender ketersediaan |
| Booking | `POST /api/v1/bookings` | user | Buat booking + tahan kuota |
| Payment | `POST /api/v1/bookings/{id}/payments` | user | Snap token |
| Webhook | `POST /webhooks/midtrans` | signature | Tanpa CSRF |
| Tiket | `GET /api/v1/me/tickets/{id}` | user | Payload QR offline |
| Gate | `POST /api/v1/gate/scans` | staf/organizer | Scan QR atau kode |

---

## 10. Database

Rancangan lengkap ada di **`ERD.md`**. Ringkasan:

| Kelompok | Tabel |
|---|---|
| Akun | `users`, `social_accounts`, `organizer_profiles`, `museum_staff` |
| Museum | `museum_categories`, `museums`, `museum_images`, `facilities`, `museum_facility`, `museum_operating_hours`, `museum_closed_dates` |
| Tiket & jadwal | `ticket_types`, `slot_templates`, `visit_slots` |
| Booking & payment | `bookings`, `booking_items`, `payments`, `payment_events` |
| E-ticket & gate | `tickets`, `ticket_scans` |
| Pendukung | `notifications`, `email_logs`, `activity_logs`, `app_settings`, `content_pages` |

**Keputusan kunci:** kuota disimpan di `visit_slots` (`capacity`, `reserved_count`, `sold_count`) dan diubah hanya dalam transaksi dengan `SELECT ... FOR UPDATE`, dilindungi `CHECK (reserved_count + sold_count <= capacity)`.

---

## 11. Business Rules dan Status Transaksi

### 11.1 Aturan bisnis

| ID | Aturan |
|---|---|
| BR-01 | Hanya museum berstatus `published` yang tampil dan dapat dipesan |
| BR-02 | Organizer hanya mengakses museum miliknya; Gate Staff hanya museum dalam `museum_staff` aktif |
| BR-03 | Satu booking berisi tepat satu slot dan ≥ 1 item tiket; total ≤ `max_tickets_per_booking` |
| BR-04 | Pemesanan hanya untuk tanggal dalam rentang hari ini s.d. `booking_window_days`, bukan tanggal tutup, dan sebelum `sales_cutoff_minutes` |
| BR-05 | Saat booking dibuat, kuota ditahan (`reserved_count += qty`) dalam satu transaksi dengan lock baris slot |
| BR-06 | Booking `pending_payment` punya `expires_at`; setelah lewat → `expired` dan kuota dilepas |
| BR-07 | Pembayaran sukses (`paid`) → booking `confirmed`, kuota dari `reserved_count` ke `sold_count`, tiket terbit, **semua dalam satu transaksi** |
| BR-08 | Pembayaran gagal/dibatalkan/kedaluwarsa melepas kuota; kegagalan satu percobaan tidak langsung batalkan booking |
| BR-09 | Batas waktu transaksi Midtrans harus lebih awal ±2 menit dari `bookings.expires_at` |
| BR-10 | Status pembayaran hanya diubah backend berdasarkan webhook tervalidasi + verifikasi API Midtrans |
| BR-11 | `gross_amount` dari Midtrans harus sama dengan `payments.amount`; jika beda → anomali |
| BR-12 | Pemrosesan webhook idempoten; transisi status monoton |
| BR-13 | Setiap tiket punya `public_id` dan `ticket_code` unik |
| BR-14 | Satu tiket hanya berhasil dipindai sekali: `UPDATE ... WHERE status = 'active'` |
| BR-15 | Tiket valid di museum, tanggal, dan jendela waktu slotnya |
| BR-16 | Urutan pemeriksaan scan: token valid → museum sesuai → tidak `void` → belum `used` → jendela waktu → tandai `used` atomik |
| BR-17 | QR tidak memuat data pribadi; hanya token bertanda tangan |
| BR-18 | Data transaksi tidak pernah dihapus keras |
| BR-19 | Perubahan harga/nama tidak mengubah booking lama (snapshot) |
| BR-20 | Kuota tidak boleh diturunkan di bawah `reserved_count + sold_count` |
| BR-21 | Museum baru: `draft → pending_review → published/rejected` |
| BR-22 | Organizer baru harus `approved` sebelum dapat mengajukan museum |
| BR-23 | Pembayaran masuk setelah booking `expired`: coba pesan ulang kuota; gagal → antrean anomali |
| BR-24 | Akun `suspended` tidak dapat login; booking `confirmed` tetap valid |

### 11.2 Status dan transisi

**Booking:** `pending_payment`, `confirmed`, `expired`, `cancelled`, `refunded`

- `pending_payment → confirmed` (payment paid)
- `pending_payment → expired` (melewati expires_at)
- `pending_payment → cancelled` (dibatalkan)
- `confirmed → refunded` (webhook refund)

**Payment:** `created`, `pending`, `paid`, `failed`, `expired`, `cancelled`, `refunded`

- `created → pending` (metode dipilih)
- `pending → paid` (settlement)
- `pending → failed/expired/cancelled`
- `paid → refunded`
- Transisi monoton; webhook terlambat dengan status lebih rendah diabaikan

**Ticket:** `active`, `used`, `void`, `expired`

- `active → used` (scan valid pertama)
- `active → void` (booking refunded)
- `active → expired` (tanggal lewat tanpa dipakai)

**Kondisi TIDAK boleh terjadi:**

| Kondisi terlarang | Pengaman |
|---|---|
| `used → active` atau tiket `used` dipindai berhasil lagi | `UPDATE ... WHERE status='active'` |
| Booking `confirmed` tanpa payment `paid` | Konfirmasi dalam satu transaksi |
| `paid → pending/failed` (mundur) | Transisi monoton |
| `reserved_count + sold_count > capacity` | Lock baris + `CHECK` constraint |

### 11.3 Token QR dan validitas tiket

- **Payload QR:** `MT1.<kid>.<ticket_public_id>.<sig>`
  - `kid` = ID kunci penandatangan (rotasi kunci)
  - `sig` = `base64url(HMAC-SHA256(key[kid], "MT1." + ticket_public_id))` dipotong 22 char
- **Mengapa HMAC:** token tidak dapat ditebak atau dipalsukan tanpa kunci rahasia
- **Kode manual:** `ticket_code` (10 karakter Crockford base32) untuk input manual
- **Screenshot QR:** QR dapat difoto; hanya scan pertama yang berhasil
- **Validasi online (MVP):** status selalu dibaca/ditulis di server

---

**[Lihat bagian lanjutan di SYSTEM_DESIGN_04_IMPLEMENTATION.md]**
