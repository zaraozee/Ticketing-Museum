# Laporan Tinjauan Keamanan — MusenTix

Tinjauan keamanan Fase 9 terhadap checklist `SYSTEM_DESIGN.md` §16, ditambah
pemeriksaan dependensi. Dokumen ini mencatat **apa yang diperiksa**, **bukti
otomatisnya**, dan **temuan yang masih terbuka**.

Tanggal tinjauan: 12 November 2026
Lingkup: seluruh kode aplikasi, konfigurasi, dan dependensi.

---

## 1. Ringkasan

| | |
|---|---|
| Acceptance criteria (2) | **Tidak ada temuan kritis/tinggi yang terbuka** |
| `composer audit` | Tidak ada advisory |
| `npm audit --omit=dev` | 0 kerentanan |
| Tes keamanan otomatis | 22 tes (`SecurityControlsTest` 16 + `TransactionalEmailTest` 6 terkait) |
| Temuan kritis | 0 |
| Temuan tinggi | 0 |
| Temuan sedang | 3 (semua diterima dengan mitigasi terdokumentasi, §6) |
| Temuan rendah | 5 (§6) |

---

## 2. Metode

1. **Pemeriksaan kode** terhadap checklist §16 baris per baris.
2. **Uji otomatis** untuk setiap kontrol yang dapat diuji (header, rate limit,
   otorisasi, escaping, injeksi).
3. **Pemeriksaan dependensi** lewat `composer audit` dan `npm audit`.
4. **Penelusuran manual** pada alur berisiko: webhook pembayaran, scan gate,
   unggah gambar, otorisasi lintas organizer, dan pemulihan sesi.

---

## 3. Hasil per area checklist §16

### 3.1 Password

| Kontrol | Status | Bukti |
|---|---|---|
| Hash lewat `Hash` Laravel | ✅ | `BCRYPT_ROUNDS=12`; tidak ada penyimpanan password mentah |
| Aturan minimum password | ✅ | `Password::defaults()` di `AppServiceProvider`; diuji `AuthenticationTest` |
| Akun social-only tanpa password | ✅ | `GoogleSocialAuthTest`: akun OAuth tidak membuat password acak yang dapat ditebak |
| Cek password bocor | ⚠️ opsional | Tidak diimplementasikan (di luar MVP); lihat §6-R1 |

### 3.2 Validasi input

| Kontrol | Status | Bukti |
|---|---|---|
| Validasi backend lewat Form Request | ✅ | Seluruh endpoint mutasi memakai Form Request; tidak ada input mentah yang dipercaya |
| Validasi frontend hanya UX | ✅ | Aturan backend independen dari validasi Vue |
| Bug ditemukan & diperbaiki | ✅ | `ScanController:85` mengakses `$validated['qr_payload']` tanpa `??` → **500** saat petugas hanya mengirim `ticket_code`. Diperbaiki menjadi `empty(...)`; diuji |

### 3.3 CSRF / XSS / SQLi

| Kontrol | Status | Bukti |
|---|---|---|
| CSRF bawaan Laravel | ✅ | Aktif untuk seluruh route web; dikecualikan **hanya** `webhooks/midtrans` (memakai signature) |
| Escaping output Vue | ✅ | Tidak ada `v-html` pada data yang dapat dikendalikan pengguna |
| Deskripsi museum dibersihkan | ✅ | Pembersihan HTML sebelum simpan; `MuseumManagementTest` menguji tag berbahaya |
| Query berparameter | ✅ | Tidak ada `DB::raw()` dengan input pengguna; pencarian memakai binding |
| Uji injeksi | ✅ | `SecurityControlsTest`: istilah pencarian berisi kutip tunggal dan `--` diperlakukan sebagai data, bukan SQL |

### 3.4 Rate limiting

| Area | Batas | Bukti |
|---|---|---|
| Login | 5/menit (IP + email) | `SecurityControlsTest`: throttle aktif; isolasi per email diuji |
| Register | 10/menit (IP) | Diuji |
| Reset password | 5/menit (IP + email) | Diuji |
| Booking & bayar | 20/menit | Terpasang di `bookings.store` dan `bookings.pay` |
| Scan QR | 60/menit | Terpasang di `POST /api/v1/gate/scans` |
| **Lookup kode manual** | 10/menit **per petugas** | Limiter terpisah `scan-manual:<id>`; diuji mengembalikan 429 dengan `retry_after` |
| Webhook | 120/menit | Longgar tetapi terbatas |

Nilai berasal dari `config/security.php` (dapat ditimpa environment), bukan
string `throttle:n,m` tersebar — satu sumber kebenaran untuk kode dan dokumen.

### 3.5 Otorisasi

| Kontrol | Status | Bukti |
|---|---|---|
| Policy per model | ✅ | `MuseumPolicy`, `BookingPolicy`, `TicketPolicy`, `ReportPolicy` |
| Scope kepemilikan pada query | ✅ | `MuseumAuthorizationTest` (8 tes), `Phase8PageRegressionTest` |
| Organizer lintas museum | ✅ | 403/404 konsisten di UI, API, dan laporan (§19 skenario 6) |
| Gate staff dibatasi `museum_staff` aktif | ✅ | `ScanController` memeriksa keanggotaan aktif sebelum memproses |
| **IDOR pada tiket orang lain** | ✅ | `a visitor cannot poll the status of another visitor booking` |

### 3.6 Session & cookie

| Kontrol | Status | Bukti |
|---|---|---|
| `HttpOnly` | ✅ | `SESSION_HTTP_ONLY=true` (bawaan) |
| `SameSite=Lax` | ✅ | `SESSION_SAME_SITE=lax` |
| `Secure` di produksi | ✅ | `SESSION_SECURE_COOKIE=true` didokumentasikan di `.env.example` dan `DEPLOYMENT.md` |
| Regenerasi sesi saat login | ✅ | `Session::regenerate()` pada login; diuji `AuthenticationTest` |
| Timeout idle | ✅ | `SESSION_LIFETIME=120` menit |
| Enkripsi sesi | ⚠️ | `SESSION_ENCRYPT=false` (bawaan Laravel). Lihat §6-R2 |

### 3.7 Secret dan API key

| Kontrol | Status | Bukti |
|---|---|---|
| Tidak ada secret di repo | ✅ | `.env` di-gitignore; `.env.example` hanya berisi placeholder |
| `MIDTRANS_SERVER_KEY` hanya di backend | ✅ | Tidak pernah dikirim ke frontend; `MIDTRANS_CLIENT_KEY` yang diekspos |
| `TICKET_SIGNING_KEYS` hanya di backend | ✅ | Token dihitung server-side; hanya `token_hash` yang disimpan |
| API key Google Maps dibatasi | ⚠️ | Pembatasan referrer dilakukan di Google Cloud Console, bukan di kode. Lihat `DEPLOYMENT.md` §4 |

### 3.8 Webhook pembayaran

| Kontrol | Status | Bukti |
|---|---|---|
| Signature SHA-512 + `hash_equals` | ✅ | `WebhookProcessor`; `AC-3: an invalid signature returns 403` |
| Verifikasi ulang via API status | ✅ | BR-10; payload mentah tidak dipercaya |
| Idempotensi `payment_events` | ✅ | `AC-2`: satu baris event, satu set tiket |
| Signature palsu tidak meracuni idempotency | ✅ | `an invalid signature cannot poison the idempotency key of the real notification` |
| Nominal tidak cocok ditandai anomali | ✅ | `AC-6`; §19 skenario 11 |
| Tidak bergantung pada daftar IP | ✅ | Hanya signature + API |

### 3.9 Booking ganda / overbooking

| Kontrol | Status | Bukti |
|---|---|---|
| `SELECT ... FOR UPDATE` | ✅ | `QuotaService`; diuji `QuotaConcurrencyTest` dengan proses paralel nyata |
| `CHECK` constraint kuota | ✅ | Migrasi `visit_slots` |
| Uji konkurensi | ✅ | §19 skenario 1: tepat satu booking berhasil, sisanya 409 |

### 3.10 Token QR dan double scan

| Kontrol | Status | Bukti |
|---|---|---|
| HMAC-signed, tidak dapat ditebak | ✅ | `TicketTokenSigner`; `kid` untuk rotasi |
| Tanpa data pribadi di token | ✅ | Token hanya memuat `uuid` tiket |
| Update atomik bersyarat | ✅ | `ScanService`; `scan bersamaan hanya satu yang berhasil` |
| Retry aman via `client_scan_uuid` | ✅ | §19 skenario 10; mengembalikan hasil asli, bukan `already_used` |
| Museum berbeda | ✅ | §19 skenario 7: `wrong_museum`, tiket tetap `active`, scan tercatat |

### 3.11 Audit log

| Kontrol | Status | Bukti |
|---|---|---|
| `activity_logs` append-only | ✅ | Tidak ada endpoint update/delete; hanya insert |
| Aksi sensitif tercatat | ✅ | Konfirmasi, pembatalan, kedaluwarsa, anomali, perubahan museum |

### 3.12 Data pribadi

| Kontrol | Status | Bukti |
|---|---|---|
| Minimasi (nama, email, telepon) | ✅ | Skema tidak menyimpan data berlebih |
| Organizer hanya melihat booking museumnya | ✅ | Query scope + policy; diuji |
| Gate staff melihat informasi minimum | ✅ | Respons scan tidak memuat email/telepon |
| Masking pada tampilan | ⚠️ | Sebagian; lihat §6-R3 |
| Kebijakan privasi tersedia | ✅ | Halaman `/bantuan` — "Kebijakan Privasi" (`ContentPageSeeder`) |

### 3.13 Unggah file

| Kontrol | Status | Bukti |
|---|---|---|
| Validasi MIME dan ukuran | ✅ | `MuseumImageTest` (7 tes) |
| Re-encode gambar | ✅ | `MuseumImageService`; menghapus metadata dan payload tersembunyi |
| Nama file acak | ✅ | `an uploaded file is stored with a random name` |
| PHP menyamar sebagai PNG ditolak | ✅ | `a php payload disguised as a png image is rejected` |
| Disimpan di storage non-eksekusi | ✅ | Nginx memblokir eksekusi PHP di `/storage` |

### 3.14 Header keamanan

| Header | Nilai | Bukti |
|---|---|---|
| `X-Content-Type-Options` | `nosniff` | `SecurityControlsTest` pada 6 jalur |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Diuji |
| `X-Frame-Options` | `SAMEORIGIN` | Diuji |
| `Permissions-Policy` | `camera=(self), microphone=(), geolocation=(self), payment=(self)` | Diuji |
| `Cross-Origin-Opener-Policy` | `same-origin` | Diuji |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` — **hanya via HTTPS** | Diuji: tidak muncul pada HTTP |
| `Content-Security-Policy` | `default-src 'none'; frame-ancestors 'none'` untuk `api/*` | Diuji |

Middleware **tidak menimpa** header yang sudah diset controller — dibuktikan
oleh tes `the service worker keeps its own headers` (`Service-Worker-Allowed`).

**CSP penuh sengaja ditunda.** `app.blade.php` memuat skrip inline (bootstrap
Inertia + Vite) dan satu handler `onload`. CSP ketat akan mematahkan aplikasi
tanpa memberi perlindungan berarti selama skrip masih inline. Lihat §6-R4.

### 3.15 Backup dan pemulihan

| Kontrol | Status | Bukti |
|---|---|---|
| Backup otomatis | ✅ | `docs/RUNBOOK.md` §4.1 (`pg_dump` + enkripsi + S3) |
| Backup portabel | ✅ | `php artisan db:backup` |
| **Uji restore** | ✅ | `php artisan db:verify-restore` — 9 tes otomatis + drill nyata |
| Enkripsi backup | ⚠️ | Dilakukan di lapisan operasi (`age`/GPG), bukan di aplikasi |

### 3.16 Dependensi

| Kontrol | Status | Bukti |
|---|---|---|
| `composer audit` bersih | ✅ | 0 advisory (lihat §4) |
| `npm audit --omit=dev` | ✅ | 0 kerentanan |
| Versi dipin lewat lockfile | ✅ | `composer.lock`, `package-lock.json` di repo |
| Pemeriksaan di CI | ⚠️ | Belum ada pipeline CI; lihat §6-R5 |

---

## 4. Temuan dependensi dan penyelesaiannya

### 4.1 Symfony — 4 advisory (1 high) → **selesai**

`composer audit` awal melaporkan advisory pada `symfony/http-foundation`,
`http-kernel`, `routing`, dan `mime`.

**Penyelesaian.** Versi diturunkan ke rilis yang telah diperbaiki dalam cabang
7.4: `http-foundation|http-kernel|routing` → **v7.4.23**, `mime` → **v7.4.22**.
Laravel 13 mengizinkan `^7.4.0 || ^8.0.0`, sehingga penurunan ini sah dan tidak
mengubah API yang dipakai aplikasi.

**Mengapa bukan 8.1.11.** Perbaikan penuh berada di `8.1.11`, yang mensyaratkan
**PHP ≥ 8.4.1**; lingkungan ini menjalankan PHP 8.4.0. Menaikkan versi akan
membuat aplikasi tidak dapat dijalankan.

**Dampak.** Tidak ada perubahan kode. Regresi diverifikasi dengan menjalankan
seluruh suite setelah penurunan versi.

**Tindak lanjut.** Setelah PHP dinaikkan ke ≥ 8.4.1, jalankan
`composer update "symfony/*" -W` lalu `composer audit` untuk kembali ke cabang 8.x.

### 4.2 `symfony/yaml` — 1 advisory → **selesai**

Laravel Boost (dependensi pengembangan) menarik `symfony/yaml` v8.0.15 yang
terdampak CVE-2026-123792. Dipin ke **v7.4.23**. Paket ini hanya untuk
pengembangan dan tidak masuk produksi, tetapi tetap dipin agar `composer audit`
bersih dan tidak ada kejutan saat `--no-dev` berubah.

### 4.3 JavaScript

`npm audit --omit=dev` melaporkan **0 kerentanan**. Kerentanan pada dependensi
pengembangan tidak memengaruhi artefak yang dikirim ke browser.

---

## 5. Temuan fungsional yang ditemukan selama tinjauan

| # | Temuan | Dampak | Status |
|---|---|---|---|
| F-1 | `ScanController` mengakses kunci `qr_payload` tanpa `??` | **500** saat petugas memakai kode manual — jalur operasional utama saat QR rusak | **Diperbaiki** + tes regresi |
| F-2 | Notifikasi memakai `DB::afterCommit()` | Callback tidak pernah berbunyi bila sudah ada transaksi luar (termasuk tes) → email e-ticket tidak terkirim diam-diam | **Diperbaiki**: efek samping dijalankan setelah transaksi service selesai |
| F-3 | `MuseumCategoryFactory` memakai `fake()->unique()` pada 8 elemen | Suite gagal setelah >8 museum dibuat dalam satu proses (`Maximum retries reached`) | **Diperbaiki**: sufiks acak |
| F-4 | `db:restore` menghapus tabel induk lebih dulu | Restore gagal karena foreign key | **Diperbaiki**: urutan dependensi dihitung dari skema |
| F-5 | Drill restore awal merusak database pengembangan | Kehilangan data demo | **Diperbaiki**: drill berjalan pada database sementara di proses terpisah |

F-1 dan F-2 adalah bug produksi nyata yang tidak terdeteksi sebelum Fase 9
karena tidak ada tes yang menutup jalur tersebut. Keduanya sekarang memiliki
tes regresi.

---

## 6. Temuan yang diterima (risiko tersisa)

| # | Temuan | Tingkat | Alasan diterima | Mitigasi |
|---|---|---|---|---|
| R1 | Tidak ada pemeriksaan password bocor (HIBP) | Rendah | Di luar MVP; menambah ketergantungan layanan eksternal dan biaya privasi | Aturan panjang minimum + rate limit login |
| R2 | `SESSION_ENCRYPT=false` | Rendah | Sesi disimpan di Redis yang tidak terekspos; enkripsi menambah overhead tiap request | Redis hanya listen di localhost, tanpa akses publik; `SESSION_SECURE_COOKIE=true` |
| R3 | Masking data pribadi belum menyeluruh | Sedang | Kebijakan masking per peran belum disepakati museum pilot | Akses sudah dibatasi policy; masking ditambahkan pada Fase 10 |
| R4 | CSP penuh belum diterapkan | Sedang | `app.blade.php` memuat skrip inline; CSP ketat akan mematahkan aplikasi | CSP ketat untuk `api/*`; header lain aktif; nonce-based CSP direncanakan saat build Vite diubah |
| R5 | Belum ada pipeline CI | Sedang | Repositori belum memiliki runner | Perintah `composer audit`, `npm audit`, Pint, PHPStan, Pest sudah siap dipasang di CI; lihat `GO_LIVE_CHECKLIST.md` §7 |
| R6 | Backup terenkripsi di lapisan operasi | Rendah | Kunci enkripsi tidak boleh berada di server aplikasi | `pg_dump` + `age`/GPG sebelum keluar server; kunci disimpan terpisah |
| R7 | Pembatasan Google Maps API di luar kode | Rendah | Dikonfigurasi di Google Cloud Console | Didokumentasikan di `DEPLOYMENT.md` |
| R8 | `%` dan `_` pada pencarian museum diperlakukan sebagai wildcard LIKE | Rendah | Tidak membocorkan data privat: hanya memengaruhi hasil pencarian pada data yang memang publik, dan query tetap berparameter (bukan SQL injection) | Diterima untuk MVP; escaping wildcard direncanakan bersama pencarian lanjutan (Fase 10) |

Tidak ada temuan **kritis** atau **tinggi** yang terbuka — AC-2 terpenuhi.

---

## 7. Bukti pengujian

```bash
php artisan test --filter=SecurityControlsTest   # 16 tes, 79 asersi
php artisan test --filter=TransactionalEmailTest # 13 tes
php artisan test                                 # seluruh suite
composer audit                                   # tidak ada advisory
npm audit --omit=dev                             # 0 kerentanan
```

Yang dibuktikan `SecurityControlsTest`:

1. Header keamanan pada 6 jalur berbeda.
2. CSP ketat pada `api/*`.
3. HSTS hanya lewat HTTPS.
4. `/sw.js` mempertahankan header miliknya.
5. Login di-throttle.
6. Throttle login terisolasi per email.
7. Registrasi di-throttle.
8. Kode manual di-throttle terpisah dari scan QR.
9. Tanggal tutup ditolak bila sudah ada booking aktif (BR-20).
10. Tanggal tutup diizinkan bila booking sudah batal/kedaluwarsa.

---

## 8. Rekomendasi sebelum go-live

1. Pasang pipeline CI yang menjalankan audit, Pint, PHPStan, dan Pest pada
   setiap pull request (R5).
2. Naikkan PHP ke ≥ 8.4.1 lalu kembalikan Symfony ke cabang 8.x (§4.1).
3. Aktifkan `SESSION_SECURE_COOKIE=true` di produksi.
4. Batasi API key Google Maps pada referrer domain produksi.
5. Jadwalkan tinjauan ulang setelah UAT museum pilot selesai.
