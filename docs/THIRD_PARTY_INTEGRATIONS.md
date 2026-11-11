# Analisis Integrasi Third-Party

Dokumen turunan dari `SYSTEM_DESIGN.md` (§14). Prinsip: **jangan mengarang kemampuan, batasan, atau harga layanan pihak ketiga.** Hal yang bertanda **[Verifikasi]** harus dicek ke dokumentasi resmi terbaru sebelum diimplementasikan atau dijanjikan ke bisnis.

## 0. Ringkasan dan prioritas

| # | Integrasi | Jenis | Status | Fase |
|---|---|---|---|---|
| 1 (A) | Midtrans (Snap + status API + webhook) | Backend, kritis | **MVP wajib** | 5 |
| 2 (B) | Login sosial: Google | Backend (OAuth) | MVP (should-have) | 2 |
| 2 (B) | Login sosial: Facebook, TikTok | Backend (OAuth) | Lanjutan | 10 |
| 3 (C) | Email transaksional | Backend | **MVP wajib** | 2 (verifikasi), 5–7 |
| 4 (D) | Google Maps | Frontend + data | MVP | 3 |
| 5 (E) | Google Calendar | Tautan/file | MVP (tanpa OAuth) | 6 |
| 6 (F) | YouTube | Embed konten | MVP (konten biasa) | 3 |
| 7 (G) | Notifikasi: in-app + email | Internal | **MVP** | 7 |
| 7 (G) | FCM Web Push | Backend + PWA | Lanjutan | 10 |
| 7 (G) | Pusher | Real-time | Tidak direncanakan | – |
| 8 (H) | AI Chatbot (Gemini) | Backend | Lanjutan | 10 |
| 9 (I) | QR generation dan scanning | Library internal | **MVP wajib** | 6 |

---

## 1. (A) Payment Gateway — Midtrans

**Fungsi:** menerima pembayaran online (QRIS, virtual account, e-wallet, kartu, dll. sesuai yang diaktifkan pada akun merchant). Pendekatan: **Snap** (halaman pembayaran siap pakai) + **verifikasi status via API server-to-server** + **webhook (HTTP notification)**.

| Aspek | Rancangan |
|---|---|
| Alur singkat | Backend membuat transaksi Snap → menerima `token` → frontend membuka Snap → pengunjung bayar → Midtrans mengirim notifikasi ke backend → backend memverifikasi → booking `confirmed` → tiket terbit |
| Data dikirim ke Midtrans | `transaction_details` (`order_id`, `gross_amount`), `item_details` (nama jenis tiket, harga, qty), `customer_details` (nama, email, telepon), `expiry` (durasi sesuai BR-09), URL redirect akhir (hanya UX) |
| Data diterima | `token`, `redirect_url`; pada notifikasi: `order_id`, `transaction_status`, `status_code`, `gross_amount`, `signature_key`, `payment_type`, `transaction_id`, `fraud_status` (untuk kartu) dan sebagainya |
| Konfigurasi | `MIDTRANS_SERVER_KEY`, `MIDTRANS_CLIENT_KEY`, `MIDTRANS_IS_PRODUCTION`, URL *Payment Notification* di dashboard (`https://<domain>/webhooks/midtrans`), domain yang diizinkan, durasi expiry, metode pembayaran yang diaktifkan. Sandbox dan produksi memakai kunci berbeda |
| Penyimpanan | `payments.order_id` (unik), `provider_transaction_id`, `snap_token`, `payment_type`, `amount`, `status`; setiap notifikasi masuk ke `payment_events` |

### 1.1 Pembuatan transaksi (backend)

1. Prasyarat: booking `pending_payment` milik pengguna, sisa waktu booking ≥ 3 menit (BR-09).
2. Kunci baris booking; pastikan tidak ada payment aktif lain (`created`/`pending`); bila ada dan masih berlaku, kembalikan token yang sama.
3. Buat `payments` (`status = created`, `order_id = {booking_code}-{n}`, `amount = total_amount`, `expires_at = booking.expires_at − 2 menit`).
4. **Setelah commit**, panggil API Snap dari backend (tidak pernah dari frontend; Server Key tidak pernah keluar dari backend).
5. Simpan `snap_token`; kembalikan ke frontend. Bila panggilan gagal, booking tetap `pending_payment` dan pengguna dapat mencoba lagi.

**[Verifikasi]** panjang maksimum `order_id`, format parameter `expiry`, dan batas nilai `gross_amount` ke dokumentasi Midtrans. Pastikan `gross_amount` = jumlah `item_details` (selisih ditolak gateway).

### 1.2 Pemetaan status

| `transaction_status` Midtrans | Arti | Status `payments` | Efek ke `bookings` / `tickets` |
|---|---|---|---|
| `pending` | Menunggu pembayaran | `pending` | Tetap `pending_payment` |
| `settlement` | Dana diterima | `paid` | `confirmed`, tiket terbit |
| `capture` + `fraud_status = accept` | Kartu berhasil | `paid` | `confirmed`, tiket terbit |
| `capture` + `fraud_status = challenge` | Perlu tinjauan fraud | `pending` + `needs_review = true` | Tetap pending; Admin memutuskan di dashboard Midtrans |
| `deny` | Ditolak | `failed` | Booking tetap pending sampai expired (boleh coba lagi) |
| `cancel` | Dibatalkan | `cancelled` | Booking `cancelled`, kuota dilepas |
| `expire` | Kedaluwarsa | `expired` | Booking `expired` bila booking juga lewat batas; kuota dilepas |
| `refund` | Dikembalikan penuh | `refunded` | Booking `refunded`, tiket `void`, `sold_count` turun |
| `partial_refund` | Refund sebagian | tetap `paid` + `needs_review = true` | Tidak otomatis; Admin memutuskan (MVP) |
| `chargeback` / `partial_chargeback` | Sengketa | `needs_review = true` | Tidak otomatis; Admin menindaklanjuti |

**[Verifikasi]** daftar status dan perilaku per metode pembayaran ke dokumentasi resmi (termasuk status yang mungkin ditambahkan kemudian). Status tak dikenal harus disimpan di `payment_events` dan menghasilkan `needs_review`, bukan diabaikan diam-diam.

### 1.3 Webhook: keamanan dan idempotensi

1. Endpoint `POST /webhooks/midtrans`: tanpa CSRF, di luar autentikasi sesi, dengan rate limit longgar.
2. **Verifikasi signature:** `SHA512(order_id + status_code + gross_amount + ServerKey)` dibandingkan dengan `signature_key` memakai perbandingan *constant-time* (`hash_equals`). Tidak cocok → 403 dan catat `rejected_invalid_signature`. **[Verifikasi]** rumus ke dokumentasi resmi.
3. **Idempotensi:** hitung `idempotency_key`, `INSERT` ke `payment_events` (kolom unik). Kunci duplikat → balas 200 tanpa memproses ulang.
4. Dispatch job `ProcessPaymentEvent`; balas 200 secepatnya.
5. Job memanggil **API status Midtrans** untuk `order_id` dan memakai **status dari API**, bukan dari payload mentah (BR-10).
6. Cek `gross_amount` = `payments.amount` (BR-11). Terapkan transisi monoton di bawah lock (lihat `UML.md` S2).
7. Jangan menjadikan **daftar IP Midtrans** sebagai satu-satunya pertahanan; jika dipakai sebagai lapisan tambahan, ambil dari dokumentasi resmi **[Verifikasi]**.
8. Cadangan: scheduler memeriksa payment `pending` yang lebih tua dari N menit lewat API status (menutup kasus webhook hilang).

### 1.4 Redirect dan frontend

URL *finish/unfinish/error* hanya mengarahkan pengguna kembali ke aplikasi. Halaman status memanggil `GET /api/v1/bookings/{id}/status` yang membaca **database** (hasil webhook), tidak mempercayai parameter di URL redirect atau callback JavaScript Snap.

### 1.5 Refund
MVP: refund dilakukan **manual** di dashboard Midtrans; aplikasi hanya mencatat hasilnya lewat webhook (`refund`) dan antrean `needs_review`. Tahap lanjutan: tabel `refunds`, tombol refund, kebijakan pembatalan, pemanggilan API refund. **[Verifikasi]** metode pembayaran yang mendukung refund dan batas waktunya.

### 1.6 Risiko dan alternatif

| Risiko | Mitigasi |
|---|---|
| Akun produksi memerlukan verifikasi bisnis/dokumen | Mulai pendaftaran sejak Fase 1 |
| Metode bayar tertentu belum aktif | Konfirmasi di dashboard; UI tidak menjanjikan metode tertentu |
| Webhook terlambat atau berulang | Idempotensi, job retry, pemeriksaan aktif |
| Selisih nominal | Validasi `gross_amount`, anomali |
| Ketergantungan vendor | Kolom `provider` pada `payments`; logika gateway dikurung di `Services/Payment` |

Alternatif: Xendit, DOKU, Faspay, iPaymu (**[Verifikasi]** fitur dan biaya). Pergantian vendor mengubah `MidtransClient` dan `WebhookProcessor`, bukan model booking/tiket.

---

## 2. (B) Social Login

| Provider | Kelayakan MVP | Catatan |
|---|---|---|
| **Google** | **Paling layak** (S, fase 2) | OAuth 2.0 / OpenID Connect, email terverifikasi tersedia, didukung Laravel Socialite, adopsi tinggi di Android |
| Facebook | Lanjutan | Perlu app review untuk izin tertentu; pengguna dapat tidak membagikan email; **[Verifikasi]** kebijakan terbaru |
| TikTok | Lanjutan | Alur dan ketersediaan data (termasuk email) berbeda; dukungan resmi framework perlu dicek; **[Verifikasi]** dokumentasi Login Kit terbaru |

Jangan menganggap semua provider memiliki alur, izin, dan ketersediaan API yang sama.

| Aspek | Rancangan |
|---|---|
| OAuth flow | Authorization Code (server-side) via Socialite; `state` diverifikasi |
| Callback URL | `https://<domain>/auth/google/callback` (didaftarkan di Google Cloud Console; satu per lingkungan) |
| Data diminta | `openid`, `email`, `profile` (minimal) |
| Penyimpanan | `social_accounts` (`provider`, `provider_user_id`, `email`); **token provider tidak disimpan** di MVP |
| Penautan akun | (a) `provider_user_id` sudah ada → login. (b) Email provider **terverifikasi** sama dengan email user yang ada → tautkan setelah pengguna membuktikan kepemilikan (login dulu) atau jika email akun lokal sudah terverifikasi. (c) Email baru → buat akun `role = user`, `email_verified_at` = sekarang jika provider menyatakan terverifikasi |
| Fallback | Email + password tetap tersedia; pengguna social-only dapat menetapkan password lewat "lupa password" |
| Risiko | Akun diambil alih lewat email tak terverifikasi → hanya percaya email terverifikasi; provider dinonaktifkan → fallback password |
| Konfigurasi | `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, redirect URI; layar persetujuan (consent screen) dan domain terverifikasi |

---

## 3. (C) Email

| Email | Pemicu | Antrean |
|---|---|---|
| Verifikasi email | Registrasi | Ya (prioritas tinggi) |
| Reset password | Permintaan pengguna | Ya |
| Konfirmasi booking | Booking dibuat (berisi batas waktu bayar) | Ya |
| Bukti pembayaran | Payment `paid` | Ya |
| **E-ticket** | Tiket terbit | Ya (kritis) |
| Pembatalan / kedaluwarsa | Booking `cancelled`/`expired`/`refunded` | Ya |
| Perubahan jadwal / penutupan | Organizer menutup tanggal (tahap lanjutan: massal) | Ya |
| Pengingat H-1 | Scheduler | Ya |

| Aspek | Rancangan |
|---|---|
| Pengiriman | Laravel Mail via SMTP atau API provider transaksional. Pilihan: Amazon SES, Brevo, Mailgun, Postmark, Resend, dll. (**[Verifikasi]** harga, kuota, reputasi pengiriman ke penyedia email Indonesia). Dev: Mailpit/Mailtrap |
| Template | Blade (Markdown mail) dengan layout seragam, `APP_NAME` dinamis, Bahasa Indonesia; tautan "Buka tiket" selalu ada (QR inline dapat diblokir klien email) |
| Isi e-ticket | Ringkasan kunjungan, QR (gambar inline atau lampiran) **dan** kode tiket manual serta tautan ke halaman tiket |
| Antrean dan retry | Job queued `afterCommit`; `tries = 5`, backoff naik (mis. 1, 5, 15, 60 menit); job gagal masuk `failed_jobs` dan alarm |
| Pencatatan | `email_logs`: `queued → sent/failed`, `template`, `booking_id`, error. Berguna untuk dukungan ("email tidak sampai") |
| Deliverability | SPF, DKIM, DMARC pada domain pengirim; alamat `no-reply` + kontak dukungan; tangani bounce/complaint (webhook provider, tahap lanjutan) |
| Risiko | Email masuk spam → tiket tetap tersedia di akun; kegagalan provider → ganti SMTP lewat konfigurasi |
| Wajib MVP | Ya (verifikasi, reset, e-ticket) |

---

## 4. (D) Google Maps

| Aspek | Rancangan |
|---|---|
| Fungsi | Peta lokasi di detail museum dan tautan petunjuk arah |
| Data | `museums.latitude`, `longitude`, `address`, `name` |
| MVP lapis 1 (tanpa API key) | Tautan **Google Maps URLs** untuk arah, mis. `https://www.google.com/maps/dir/?api=1&destination={lat},{lng}` dan pencarian; tombol "Petunjuk Arah" |
| MVP lapis 2 | Peta tertanam (Maps Embed) bila diperlukan; membutuhkan API key. **[Verifikasi]** kuota, harga, dan kebijakan terbaru |
| API key | Dibatasi: *HTTP referrer* domain aplikasi + hanya API yang dipakai; simpan di `.env`; pantau penggunaan dan beri *budget alert* |
| Input koordinat oleh Organizer | Tempel lat/lng atau tautan Maps, atau *pin picker* (opsi: Leaflet + OpenStreetMap; patuhi kebijakan penggunaan tile) |
| Risiko | Biaya/kuota tak terduga, API key bocor → pembatasan + alert; koordinat salah → moderasi Admin |
| Alternatif | OpenStreetMap + Leaflet, Mapbox |
| Wajib MVP | Tautan arah: ya. Embed interaktif: opsional |

---

## 5. (E) Google Calendar

| Aspek | Rancangan |
|---|---|
| Opsi MVP (tanpa OAuth) | **Tautan "Tambah ke Google Calendar"** (URL template kalender) + **unduhan file .ics** (kompatibel Apple/Outlook) |
| Data event | Judul "Kunjungan {nama museum}", tanggal dan jam (zona waktu museum, `ctz`), lokasi (alamat), deskripsi (kode booking, jenis dan jumlah tiket, tautan tiket) |
| Zona waktu | Gunakan `museums.timezone`; untuk mode `daily` gunakan jam buka–tutup hari itu |
| Kapan perlu OAuth + Calendar API | Hanya bila aplikasi harus **menulis langsung** ke kalender pengguna tanpa interaksi (mis. sinkron otomatis atau pembaruan jika jadwal berubah). Memerlukan consent, scope Calendar, penyimpanan token, review Google. **Tidak** direkomendasikan di MVP |
| Risiko | Format parameter URL berubah → uji berkala (**[Verifikasi]**); perubahan jadwal setelah event ditambahkan tidak tersinkron (jelaskan di UI) |
| Wajib MVP | Opsional (tautan + .ics) — murah dan bernilai tinggi |

---

## 6. (F) YouTube

| Aspek | Rancangan |
|---|---|
| Sifat | **Fitur konten**, bukan integrasi backend. Tidak butuh API key untuk sekadar menyematkan video |
| Data | `museums.youtube_video_id` (hanya ID 11 karakter) |
| Validasi | Organizer memasukkan tautan; backend mengekstrak dan memvalidasi ID dengan regex `^[A-Za-z0-9_-]{11}$`; tidak menyimpan HTML/iframe dari pengguna |
| Embed aman | Domain `youtube-nocookie.com`, `loading="lazy"`, atribut `allow` minimal, `referrerpolicy`; CSP `frame-src` hanya domain tersebut; rasio 16:9 responsif (`aspect-ratio`) |
| Performa | Pola *facade*: tampilkan thumbnail dan muat iframe saat diklik |
| Risiko | Video dihapus/dibatasi → tampilkan fallback; privasi → domain *nocookie* |
| YouTube Data API | Hanya bila ingin mengambil metadata otomatis (tahap lanjutan) |

---

## 7. (G) Notifikasi — Email, In-app, Web Push (FCM), Real-time (Pusher)

### 7.1 Perbandingan kanal

| Kanal | Fungsi | Perlu izin/pihak ketiga | Cocok untuk | Status |
|---|---|---|---|---|
| Email | Bukti dan tiket yang tahan lama | Provider email | Konfirmasi, e-ticket, reset | **MVP** |
| In-app (tabel `notifications`) | Daftar notifikasi di akun | Tidak | Status booking/tiket, info museum | **MVP** |
| Web push (FCM / Web Push) | Dorongan ke perangkat saat aplikasi tertutup | Izin pengguna + Firebase **[Verifikasi]** | Pengingat, perubahan jadwal | Lanjutan |
| Real-time (Pusher/WebSocket) | Pembaruan langsung di layar terbuka | Pusher/self-hosted WebSocket | Dashboard live, status bayar instan | Tidak direncanakan |

**Rekomendasi MVP:** email + in-app. Status pembayaran memakai **polling ringan** (mis. tiap 3–5 detik selama halaman status terbuka) sehingga Pusher **tidak diperlukan**. Web push ditambahkan di Fase 10 setelah inti stabil. Pusher baru dipertimbangkan bila ada kebutuhan live dashboard skala besar.

### 7.2 Matriks notifikasi

| Peristiwa | Email | In-app | Web push (lanjutan) |
|---|:--:|:--:|:--:|
| Pembayaran berhasil | ✅ bukti bayar | ✅ | ✅ |
| E-ticket diterbitkan | ✅ (dengan QR + tautan) | ✅ | ✅ |
| Pengingat kunjungan (H-1) | ✅ | ✅ | ✅ |
| Perubahan/pembatalan jadwal oleh museum | ✅ | ✅ | ✅ |
| Booking hampir kedaluwarsa | – | ✅ | opsional |
| Informasi penting dari museum | ✅ | ✅ | opsional |

### 7.3 Rancangan FCM (tahap lanjutan)
Proyek Firebase, kunci VAPID, `push_subscriptions` (per perangkat: `user_id`, endpoint/token, `user_agent`, `last_seen_at`), pengiriman dari backend lewat job, penanganan token kedaluwarsa, preferensi notifikasi per pengguna, izin diminta secara kontekstual (bukan saat pertama membuka). iOS: hanya untuk PWA yang dipasang ke layar utama, **[Verifikasi]** versi dan perilaku terbaru. Pertimbangkan Web Push standar (VAPID) langsung sebagai alternatif tanpa Firebase.

---

## 8. (H) AI Chatbot — Gemini atau Grok

**Rekomendasi:** **tahap lanjutan (Fase 10)**, bukan MVP. Alasan: tidak menyelesaikan masalah inti (pemesanan, pembayaran, tiket), menambah biaya, risiko keamanan, dan beban pengujian. FAQ statis (`content_pages`) sudah mengisi kebutuhan awal.

| Aspek | Gemini | Grok (xAI) |
|---|---|---|
| Ketersediaan API, harga, batas, kebijakan data | **[Verifikasi]** | **[Verifikasi]** |
| Kecocokan | Ekosistem Google (konsisten dengan Maps/Calendar/Antigravity), dokumentasi luas | Alternatif; perlu verifikasi dukungan bahasa Indonesia dan SDK |
| Rekomendasi | **Gemini sebagai kandidat utama** (putusan final setelah uji kualitas bahasa Indonesia dan biaya) | Cadangan |

**Kegunaan:** menjawab pertanyaan umum museum, menjelaskan fasilitas/jam/aturan kunjungan, rekomendasi berdasarkan minat, panduan penggunaan aplikasi.

**Arsitektur dan batasan keamanan**

| Aturan | Implementasi |
|---|---|
| API key hanya di backend | Endpoint `POST /api/v1/chat`; frontend tidak pernah memegang kunci |
| Tidak mengarang harga, jadwal, kuota, status booking | Fakta diambil lewat **fungsi/tool terbatas** yang membaca database (detail museum, jam, jenis tiket + harga, ketersediaan); model hanya merangkai jawaban. Jika data tidak ada → "saya tidak tahu, silakan cek halaman museum" |
| Tidak ada data pribadi/pembayaran ke penyedia AI | Tidak mengirim nama, email, telepon, nomor booking, atau data bayar; masking sebelum kirim |
| Tidak mengubah data | Tool bersifat **baca-saja**; chatbot tidak membuat booking/pembatalan |
| Prompt injection | Perlakukan input pengguna dan konten museum sebagai data; instruksi sistem tetap; abaikan perintah dari konten |
| Fallback | Timeout/kegagalan → tampilkan FAQ dan tautan bantuan |
| Biaya dan abuse | Rate limit per pengguna/IP, batas panjang input/output, kuota harian, caching jawaban umum |
| Privasi dan log | Simpan minimal (tanpa PII); **[Verifikasi]** kebijakan retensi data penyedia AI |
| Evaluasi | Kumpulan pertanyaan uji + pengecekan akurasi sebelum rilis |

---

## 9. (I) QR Code / Barcode Gate Scanner

### 9.1 Token tiket
Lihat `SYSTEM_DESIGN.md` §11.3. Ringkas: payload `MT1.<kid>.<ticket_public_id>.<sig>`, `sig` = HMAC-SHA256 yang dipotong; kunci di `TICKET_SIGNING_KEYS` (environment); rotasi kunci lewat `kid` (kunci lama tetap dapat memverifikasi sampai tiket yang diterbitkan dengannya habis masa berlaku). QR **tidak** berisi data pribadi atau pembayaran.

### 9.2 Tampilan QR
- **Format:** QR (bukan barcode 1D) karena payload ± 55–60 karakter; level koreksi kesalahan sedang (M).
- **Render:** di sisi **klien** dari payload (agar tersedia offline); QR server-side (PNG/SVG) hanya untuk email dan cetak.
- **UI:** QR besar, kontras tinggi, kecerahan layar disarankan naik, kode manual di bawah QR.

### 9.3 Pemindaian oleh petugas
- Halaman `/gate` (PWA) memakai kamera lewat `getUserMedia` — **memerlukan HTTPS** dan izin kamera (`Permissions-Policy` mengizinkan `camera`).
- `BarcodeDetector` bawaan browser belum merata; gunakan library scanner dengan fallback (**[Verifikasi]** dukungan iOS Safari saat Fase 6). Sediakan **input kode manual** sebagai cadangan.
- Setelah hasil tampil, scanner otomatis siap untuk tiket berikutnya setelah jeda singkat atau tombol "Scan Berikutnya".

### 9.4 Kontrak API validasi

`POST /api/v1/gate/scans`

```json
{
  "museum_id": "01J...ULID",
  "payload": "MT1.k1.01J...ULID.AbCdEf...",
  "ticket_code": null,
  "client_scan_uuid": "6f1d2c9e-....",
  "gate_name": "Gate Utara"
}
```

Respons (kode HTTP 200 untuk semua hasil scan; kesalahan sistem memakai 4xx/5xx):

```json
{
  "result": "valid",
  "message": "Tiket valid, silakan masuk",
  "ticket": {
    "ticket_code": "7H2K9QXM4A",
    "ticket_type": "Dewasa",
    "booker_name": "Sari",
    "visit_date": "2026-12-05",
    "slot": "10:00-12:00"
  },
  "previous_scan": null
}
```

`result` ∈ `valid`, `already_used`, `invalid`, `not_yet_valid`, `expired`, `wrong_museum`, `voided`. Untuk `already_used`, `previous_scan` berisi `scanned_at` dan nama petugas. Untuk `wrong_museum` dan `invalid`, **tidak** ada detail tiket yang dikembalikan.

### 9.5 Pemeriksaan (berurutan)
Token valid → museum cocok dan petugas berwenang → tiket tidak `void` (booking `confirmed`) → belum `used` → jendela tanggal/slot → `UPDATE ... WHERE status = 'active'` (BR-14, BR-15, BR-16). Pencatatan ke `ticket_scans` selalu dilakukan (waktu, petugas, `gate_name`, hasil).

### 9.6 Koneksi buruk dan mode offline

| Skenario | Perilaku MVP |
|---|---|
| Timeout / jaringan putus saat scan | Klien menampilkan "Menunggu respons…", timeout (mis. 5–8 detik), **retry dengan `client_scan_uuid` yang sama**. Server mengembalikan hasil asli bila sudah diproses, sehingga tidak berubah menjadi `already_used` secara keliru |
| Tidak ada koneksi sama sekali | Scanner menampilkan banner "Tidak ada koneksi" dan **tidak** menandai tiket; museum memakai SOP cadangan (mis. cek nama + kode manual setelah koneksi pulih) |
| Mode offline penuh (**tahap lanjutan**) | Memungkinkan verifikasi tanda tangan HMAC offline, tetapi status `used` tidak dapat dijamin. Dua perangkat offline dapat sama-sama menerima tiket yang sama → **konflik sinkronisasi** dan potensi masuk ganda. Mitigasi: batasi ke satu perangkat per gate, daftar tiket sah diunduh sebelumnya, sinkron berurutan dengan aturan "scan tertua menang", dan kebijakan bisnis yang jelas untuk konflik. Karena risikonya, **tidak masuk MVP** (D-13) |

### 9.7 Risiko screenshot dan penyalinan QR
Siapa pun yang memegang QR dapat mencoba memindai. Sistem memastikan **hanya scan pertama** yang berhasil (atomik). Risiko tersisa: pemegang QR yang menyalin lebih dulu dapat masuk lebih dulu. Mitigasi MVP: petugas mencocokkan nama pemesan/jenis tiket di layar; peringatan di UI "Jangan bagikan QR Anda". Lanjutan: QR dinamis (kode berputar tiap beberapa detik), nama per tiket, pencocokan ID.

### 9.8 Tampilan hasil
Warna + ikon + teks per status (lihat `SYSTEM_DESIGN.md` §17.2); suara/getar opsional; hasil tetap terlihat sampai petugas menutup atau scan berikutnya.

---

## 10. Konfigurasi lingkungan (ringkasan variabel)

| Variabel | Fungsi | Catatan |
|---|---|---|
| `APP_NAME`, `APP_URL`, `APP_KEY` | Identitas dan enkripsi | Nama aplikasi tidak di-hardcode |
| `DB_*`, `REDIS_*` | Database, Redis | |
| `MIDTRANS_SERVER_KEY`, `MIDTRANS_CLIENT_KEY`, `MIDTRANS_IS_PRODUCTION` | Pembayaran | Server key hanya di backend |
| `TICKET_SIGNING_KEYS` | Kunci HMAC tiket (map `kid → key`) | Acak ≥ 32 byte, jangan di repo |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | Login Google | |
| `GOOGLE_MAPS_KEY` | Peta tertanam | Dibatasi referrer |
| `MAIL_*` | Email | |
| `FILESYSTEM_DISK`, `AWS_*` / storage setara | Penyimpanan foto | |
| `SENTRY_DSN` (atau setara) | Error tracking | |
| `FCM_*`, `GEMINI_API_KEY` | Tahap lanjutan | Belum diset di MVP |
