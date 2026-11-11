# Instruksi untuk AI Coding Agent (Antigravity)

> Taruh seluruh folder `docs/` di root proyek. File ini dapat disalin juga sebagai `AGENTS.md` di root proyek, atau ditempel ke fitur rules/instruksi Antigravity jika tersedia (**[VERIFIKASI]** cara kerjanya di versi Antigravity yang Anda pakai).

## Aturan Utama

1. **Baca dulu** semua file di `docs/` sebelum menulis kode: `SYSTEM_DESIGN.md`, `ERD.md`, `UML.md`, `THIRD_PARTY_INTEGRATIONS.md`, `ROADMAP.md`.
2. **Kerjakan satu fase per sesi**, sesuai `ROADMAP.md`. Jangan mengerjakan fase berikutnya sebelum saya menyetujui.
3. **Dokumen adalah sumber kebenaran.** Jangan mengubah desain (skema, status, aturan bisnis, arsitektur) tanpa bertanya. Jika menemukan konflik atau celah, **berhenti dan tanyakan**, jangan menebak.
4. **Jangan menambah fitur di luar fase aktif** (tandai L/O di SYSTEM_DESIGN §7 = jangan dikerjakan di MVP).
5. **Verifikasi detail eksternal ke dokumentasi resmi** (Midtrans, Laravel, paket pihak ketiga) sebelum memakainya; item bertanda **[VERIFIKASI]** wajib dicek.
6. **Tulis test** untuk alur kritis (SYSTEM_DESIGN §19) bersamaan dengan kodenya.
7. **Setiap akhir fase** laporkan: yang selesai, yang belum, keputusan yang diambil, asumsi, dan cara menjalankan/menguji.

## Aturan Teknis Wajib

| Area | Aturan |
|---|---|
| Stack | Laravel (versi stabil terbaru yang didukung), Inertia + Vue 3, Tailwind, PostgreSQL, Redis (queue/cache) |
| Struktur | Logika bisnis di `app/Services/*`; controller tipis; validasi di Form Request; akses lewat Policy |
| Uang | Integer rupiah (bigint), tidak pernah float |
| Kuota | Hanya lewat update kondisional atomik pada `visit_sessions` (ERD §6). **Dilarang** menghitung sisa kuota dengan `COUNT` saat checkout |
| Status | Gunakan Enum PHP yang nilainya identik dengan SYSTEM_DESIGN §11.2 |
| Pembayaran | Status `paid` **hanya** dari webhook terverifikasi (signature + Get Status). Redirect/JS Snap tidak boleh mengubah status |
| Idempotensi | `payment_events.event_key` UNIQUE; konfirmasi booking dalam transaksi dengan `FOR UPDATE`; tiket unik `(booking_item_id, sequence)` |
| Token QR | HMAC dari uuid tiket (ERD §5); simpan hanya `token_hash`; QR tidak berisi data pribadi |
| Scan | Ikuti ERD §9 (urutan pemeriksaan + update atomik `WHERE status='issued'`) |
| Secret | Semua kunci di `.env`; sediakan `.env.example`; jangan commit secret |
| Waktu | Simpan UTC; perbandingan jadwal memakai zona waktu museum |
| Commit | Commit kecil per tugas dengan pesan jelas; jalankan lint + test sebelum commit |

## Prompt Siap Pakai per Fase

**Fase 2 (mulai di sini):**
> Baca semua file di `docs/`. Kerjakan **hanya Fase 2** pada `ROADMAP.md`: setup Laravel + Inertia/Vue + Tailwind + PostgreSQL, autentikasi (registrasi, verifikasi email, login, reset password), role Admin/Organizer/User dengan `spatie/laravel-permission`, tabel `museum_staff`, seeder, layout dasar, CI. Ikuti `AGENT_INSTRUCTIONS.md`. Sebelum menulis kode, berikan rencana langkah singkat dan daftar paket yang akan dipasang. Berhenti dan laporkan setelah kriteria selesai Fase 2 terpenuhi.

**Fase 3 dan seterusnya:**
> Fase 2 telah selesai dan disetujui. Kerjakan **hanya Fase N** sesuai `ROADMAP.md`. Ikuti aturan di `AGENT_INSTRUCTIONS.md`. Tanyakan lebih dulu jika ada yang ambigu.

## Keputusan yang Harus Final Sebelum Fase Tertentu

| Sebelum fase | Putuskan |
|---|---|
| 4 | Mode default museum (`daily`/`slot`), nilai `entry_grace_minutes` |
| 5 | Batas waktu bayar (default 30 menit), guest checkout atau wajib akun (default: wajib akun), data pengunjung per tiket wajib atau tidak, kebijakan pembayaran terlambat (SYSTEM_DESIGN §11.3) |
| 5 | Akun sandbox Midtrans dan URL webhook yang dapat dijangkau (mis. tunnel saat development) |
| 9 | Hosting/produksi, domain, provider email, kebijakan privasi dan S&K |
| 10a | Kebijakan refund/reschedule dan komisi |

## Catatan Revisi Dokumen

| Perubahan | Lokasi |
|---|---|
| Kolom `sequence` + UNIQUE `(booking_item_id, sequence)` ditambahkan ke `tickets` | ERD.md |
| Kolom `users.global_role` dihapus (role murni dari paket permission) | ERD.md |
| Validasi scan dirinci: jendela waktu, urutan cek, update atomik, retry timeout | ERD.md §9 |
| Fungsi `token_hash` dan keputusan token HMAC diperjelas | ERD.md §3.5, §5 |
