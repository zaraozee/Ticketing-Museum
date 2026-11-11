# AGENTS.md — Aplikasi Ticketing Museum (PWA)

Konteks dan aturan kerja untuk agen coding (Google Antigravity dan agen lain yang membaca `AGENTS.md`). Letakkan file ini di **root repositori**. Dokumen rancangan lengkap ada di `docs/`.

## Sumber kebenaran

Baca sebelum mengerjakan tugas apa pun:

| Dokumen | Isi |
|---|---|
| `docs/SYSTEM_DESIGN.md` | Keputusan (D-xx), kebutuhan (FR/NFR), aturan bisnis (BR-xx), status, arsitektur, keamanan, struktur folder, testing |
| `docs/ERD.md` | Skema database, indeks, strategi anti-overbooking |
| `docs/UML.md` | Use case, activity, sequence |
| `docs/THIRD_PARTY_INTEGRATIONS.md` | Midtrans, email, maps, calendar, QR scanner, dll. |
| `docs/ROADMAP.md` | Fase dan acceptance criteria |

Jika kode dan dokumen bertentangan: **berhenti dan tanyakan**, atau perbarui dokumen terlebih dahulu. Jangan menyimpang diam-diam.

## Aturan proses (WAJIB)

1. **Jangan mulai implementasi** sampai ada instruksi eksplisit, mis. *"Rancangan disetujui. Mulai implementasi Fase N."* Kerjakan **hanya fase yang diminta**.
2. Susun **rencana implementasi singkat** dan tunggu persetujuan sebelum menulis kode untuk fase baru.
3. Jangan menimpa file yang sudah ada tanpa membacanya; jangan menjalankan migrasi destruktif, `migrate:fresh`, atau menghapus data tanpa izin.
4. Jangan memasang package di luar daftar stack tanpa persetujuan; jelaskan alasannya bila perlu paket baru. Pin versi lewat lockfile.
5. Jangan mengubah aturan inti (BR-05…BR-16, transisi status, skema kuota) tanpa persetujuan eksplisit.
6. Satu fase = satu cabang Git + PR kecil. Jalankan Pint, Larastan, Pest sebelum menyatakan selesai.
7. Jangan membuat folder abstraksi berlebihan (tanpa Repository layer). Ikuti konvensi Laravel.

## Stack (ringkas)

Laravel 13 (PHP 8.3+) · PostgreSQL 16+ · Redis (queue, cache, rate limit) · Inertia + Vue 3 + TypeScript + Tailwind · `vite-plugin-pwa` · Midtrans Snap · Socialite (Google) · S3-compatible storage · Pest, Pint, Larastan.
**Verifikasi** versi paket dan dokumentasi resmi sebelum memakainya; jangan mengarang API pihak ketiga.

## Aturan domain yang tidak boleh dilanggar

- **Kuota** hanya berubah di `visit_slots` lewat `QuotaService`, dalam transaksi dengan `SELECT ... FOR UPDATE`. Dilarang memanggil HTTP eksternal di dalam transaksi database.
- **Status pembayaran** hanya berubah dari job pemrosesan webhook setelah (a) signature valid, (b) idempotensi `payment_events`, (c) verifikasi API status Midtrans, (d) `gross_amount` cocok. **Jangan pernah** mempercayai redirect atau data dari frontend.
- **Tiket**: satu baris per orang. QR = `MT1.<kid>.<ticket_public_id>.<sig>` (HMAC); tanpa data pribadi/pembayaran. Penandaan `used` memakai `UPDATE ... WHERE status='active'` dan dianggap sukses hanya jika 1 baris terpengaruh.
- **Otorisasi**: setiap akses Organizer/Gate Staff dibatasi kepemilikan museum (Policy + query scope). Tambahkan tes otorisasi untuk setiap endpoint baru.
- **Tabel transaksi tidak pernah dihapus** (`bookings`, `booking_items`, `payments`, `payment_events`, `tickets`, `ticket_scans`, `activity_logs`).
- **Uang** = integer rupiah (`*_amount`, `bigint`). **Waktu** = UTC di database; tanggal/jam kunjungan di zona waktu museum.
- **Nama aplikasi** jangan di-hardcode; pakai `config('app.name')`.
- Status memakai **Enum PHP** (`BookingStatus`, `PaymentStatus`, `TicketStatus`, `ScanResult`, `MuseumStatus`, `UserRole`), bukan string literal tersebar. Nilai persis sesuai `docs/ERD.md`.

## Konvensi kode

- Controller tipis → logika di `app/Services/<Modul>`; validasi di Form Request; bentuk JSON di API Resource.
- Identifikasi di kode: Bahasa Inggris. Teks UI: Bahasa Indonesia (lewat file bahasa).
- Primary key `bigint`; ID publik `public_id` (ULID) pada URL/API.
- Semua input divalidasi di backend. Jangan log password, token, atau payload sensitif.
- Setiap migrasi punya `down()` yang masuk akal; tambahkan index dan constraint seperti di `docs/ERD.md`.
- Tes: gunakan PostgreSQL untuk tes yang menyangkut locking/konkurensi (bukan SQLite).

## Definition of Done (per tugas)

1. Memenuhi acceptance criteria fase terkait di `docs/ROADMAP.md`.
2. Tes (unit/feature, plus otorisasi dan konkurensi bila relevan) ditambahkan dan lulus.
3. Pint, Larastan, Pest hijau.
4. Tidak ada rahasia di repo; `.env.example` diperbarui.
5. Dokumen diperbarui bila ada keputusan yang berubah.
6. Ringkasan perubahan, risiko, dan langkah verifikasi manual ditulis di PR.

## Struktur proyek

Lihat `docs/SYSTEM_DESIGN.md` §18 (struktur folder dan penempatan modul museum, booking, payment, ticket, scanner).
