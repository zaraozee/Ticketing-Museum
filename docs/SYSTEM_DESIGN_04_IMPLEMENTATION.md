# SYSTEM DESIGN — Part 4: Implementation

> **Bagian dari:** SYSTEM_DESIGN.md (dipecah untuk menghindari content length threshold)
> **Isi:** Alur bisnis, PWA, keamanan, UI/UX, struktur folder, testing, roadmap, risiko

---

## 12. Alur Bisnis

### 12.1 Alur pengunjung

| Langkah | Berhasil | Gagal |
|---|---|---|
| 1–7. Cari museum, pilih tiket/tanggal/slot | UI ketersediaan | Slot penuh disabled |
| 8. Periksa kuota | Tersedia | "Kuota tidak cukup" |
| 9. Isi data pemesan | Valid | Belum login → redirect |
| 10. Booking dibuat | `pending_payment` | Kuota habis → 409 |
| 11. Bayar via Snap | Menunggu notifikasi | Gagal → "Coba lagi" |
| 12–13. Webhook, booking `confirmed` | Idempoten | Terlambat/ganda → idempoten |
| 14–15. Tiket terbit, email | Di "My Tickets" | Email gagal → retry |
| 16–19. Scan QR, tiket `used` | Masuk | `already_used`, `invalid`, dll. |

### 12.2 Alur organizer

| Tahap | Berhasil | Gagal |
|---|---|---|
| 1. Pendaftaran | `pending` | Data tidak lengkap |
| 2. Verifikasi organizer | `approved` | `rejected` + alasan |
| 3. Buat museum | Tersimpan `draft` | Foto invalid ditolak |
| 4. Atur tiket & jadwal | Slot ter-generate | Kuota < terjual ditolak |
| 5. Ajukan verifikasi | `pending_review` | Wajib ≥1 foto, tiket, jam |
| 6. Publikasi | `published` | `rejected` + catatan |
| 7–9. Pantau, scan, laporan | Data real-time | – |

---

## 15. Rancangan PWA

### 15.1 Komponen

| Komponen | Rancangan |
|---|---|
| Manifest | `name`, `short_name` dari `APP_NAME`, ikon 192/512 px, `display: standalone` |
| Installability | HTTPS, manifest valid, service worker. Android: `beforeinstallprompt`. iOS: petunjuk manual |
| Service worker | Workbox (`vite-plugin-pwa`), pembaruan dengan prompt "muat ulang" |

Implementasi (Fase 8): manifest dirender server oleh `ManifestController` dari `config('app.name')`; service worker dibangun mode `injectManifest` dari `resources/js/sw.ts` dan **disajikan dari root `/sw.js`** (`Service-Worker-Allowed: /`, `no-store`) — bukan `/build/sw.js`, karena scope `/build/` membuat navigasi tidak pernah ter-intercept dan PWA tidak installable. Registrasi manual di `resources/js/pwa.ts`; pembaruan prompt-only.

### 15.2 Strategi cache

| Sumber daya | Strategi | Catatan |
|---|---|---|
| App shell (JS/CSS/ikon) | **Precache** | Versi ber-hash |
| Gambar museum | **CacheFirst** | Batas jumlah |
| Halaman publik | **StaleWhileRevalidate** | Boleh sedikit usang |
| Ketersediaan, slot, harga, checkout | **NetworkOnly** | Harus real-time |
| E-ticket | IndexedDB + client-side QR | Offline-capable |

Jalur NetworkOnly didefinisikan sekali di `config('ticketing.pwa.network_only_paths')` dan diuji terhadap bundel `public/build/sw.js` (`tests/Feature/PwaTest.php`) agar konfigurasi dan SW tidak menyimpang. Hanya respons **navigasi** ke halaman publik yang di-cache; respons XHR `X-Inertia` tidak pernah disimpan. Halaman publik yang belum pernah dikunjungi jatuh ke halaman offline, bukan error browser.

### 15.3 Batasan offline

| Fitur | Offline |
|---|---|
| Pembayaran, booking | Tidak tersedia, tombol dinonaktifkan |
| Browse museum | Dari cache dengan label "tidak terbaru" |
| E-ticket | QR render di client dari IndexedDB |
| Scan gate | Tidak tersedia di MVP |

Status tiket offline ditampilkan sebagai **"Tidak terverifikasi — terakhir diperbarui {waktu}"** (`offlineStatusLabel()`); validitas akhir selalu ditentukan server saat scan. Logout menghapus database IndexedDB tiket sebelum `POST /logout`.

---

## 16. Keamanan

| Area | Kontrol |
|---|---|
| Password | Hash bcrypt/Argon2id; minimum rule; cek bocor (opsional) |
| Validasi | Semua input di backend via Form Request |
| CSRF / XSS / SQLi | CSRF token; Vue escape default; query berparameter |
| Rate limiting | Login, register, checkout, scan, webhook (longgar) |
| Otorisasi | Policy + query scope kepemilikan |
| Session | `Secure`, `HttpOnly`, `SameSite=Lax`; regenerasi saat login |
| OAuth | Authorization code flow; `state` dicek; simpan hanya `provider_user_id` |
| Secret | Hanya di environment; tidak di repo |
| Webhook | Verifikasi signature SHA-512 + API status; idempotensi |
| Booking ganda | `SELECT ... FOR UPDATE` + `CHECK` constraint |
| Token QR | HMAC-signed, rotasi kunci via `kid` |
| Double scan | Update atomik bersyarat + `client_scan_uuid` |
| Audit log | `activity_logs` append-only |
| Unggah file | Validasi MIME/ukuran, re-encode, nama acak |
| Header keamanan | HSTS, CSP, X-Content-Type-Options |

---

## 17. UI/UX

### Hasil scan (warna + ikon + teks)

| Hasil | Warna | Pesan |
|---|---|---|
| `valid` | Hijau | "VALID — silakan masuk" + jenis tiket |
| `already_used` | Merah/oranye | "SUDAH DIGUNAKAN" + waktu scan |
| `invalid` | Merah | "TIDAK VALID" |
| `not_yet_valid` | Kuning | "BELUM BERLAKU" + tanggal benar |
| `expired` | Abu/merah | "KEDALUWARSA" |
| `wrong_museum` | Ungu/oranye | "TIKET MUSEUM LAIN" |
| `voided` | Merah | "TIKET DIBATALKAN" |

**Arah visual:** modern, bersih, banyak ruang kosong, foto museum sebagai elemen utama, mode gelap opsional, komponen konsisten, animasi ringan.

---

## 18. Struktur Folder

```
app/
├── Enums/          # BookingStatus, PaymentStatus, TicketStatus, ...
├── Http/
│   ├── Controllers/
│   │   ├── Public/        # MuseumController, AvailabilityController
│   │   ├── Account/       # BookingController, TicketController
│   │   ├── Organizer/     # MuseumController, StaffController
│   │   ├── Admin/         # UserController, TransactionController
│   │   ├── Gate/          # ScanController
│   │   └── Webhook/       # MidtransWebhookController
│   ├── Requests/          # Form Request per aksi
│   └── Resources/         # API Resource
├── Models/
├── Policies/
├── Services/
│   ├── Museum/
│   ├── Schedule/          # SlotGenerator, AvailabilityService
│   ├── Booking/           # BookingService, QuotaService
│   ├── Payment/           # PaymentService, MidtransClient, WebhookProcessor
│   ├── Ticket/            # TicketIssuer, TokenSigner
│   └── Gate/              # ScanService
├── Jobs/                  # ProcessPaymentEvent, SendTicketEmail
├── Console/Commands/      # slots:generate, bookings:expire
├── Notifications/
└── Mail/
```

**Aturan:** controller tipis; transaksi & logika di Service; tidak ada HTTP eksternal di dalam transaksi DB; akses data via Policy & scope; semua status pakai Enum.

---

## 19. Strategi Testing

| Jenis | Cakupan | Alat |
|---|---|---|
| Unit | Token QR, total, status, waktu | Pest/PHPUnit |
| Feature | Booking, payment, tiket, scan | Pest + PostgreSQL uji |
| API | Kontrak `/api/v1` | Pest HTTP |
| Authorization | Matriks role × endpoint | Pest dataset |
| Webhook | Signature, duplikat, amount | Fake client |
| Concurrency | Kuota terakhir paralel | Proses paralel + PostgreSQL |
| QR scan | 7 hasil, retry idempoten | Pest |
| PWA | Manifest, offline | Lighthouse + manual |

PWA (Fase 8): kontrak server-side diuji otomatis oleh `tests/Feature/PwaTest.php` (manifest, ikon di disk, header `/sw.js`, jalur NetworkOnly vs bundel SW, halaman offline, sitemap, robots, meta app-shell). Perilaku offline diuji manual lewat Chrome DevTools Protocol dengan upstream dimatikan sungguhan — emulasi `Network.emulateNetworkConditions` biasa tidak menjangkau `fetch` dari target service worker sehingga bisa memberi hasil palsu.

**Skenario kunci:**

| # | Skenario | Hasil |
|---|---|---|
| 1 | Dua user pesan kuota terakhir | Tepat satu berhasil; 409 untuk lainnya |
| 2 | Callback payment duplikat | Satu `payment_events` diproses |
| 3 | Payment berhasil, browser tertutup | Webhook tetap konfirmasi + tiket |
| 4 | Booking kedaluwarsa | `expired`; `reserved_count` kembali |
| 5 | Tiket sama dipindai 2x | Satu `valid`, sisanya `already_used` |
| 6 | Organizer akses museum lain | 403/404 |
| 8 | Signature webhook salah | 403; event `rejected_invalid_signature` |

---

## 20. Roadmap

Roadmap lengkap ada di **`ROADMAP.md`**. Ringkasan:

| Fase | Nama | Estimasi* |
|---|---|---|
| 1 | Finalisasi rancangan | 1 minggu |
| 2 | Setup, autentikasi, role | 1–2 minggu |
| 3 | Modul museum | 2 minggu |
| 4 | Tiket, jadwal, kuota | 2 minggu |
| 5 | Booking & payment | 2–3 minggu |
| 6 | E-ticket & scanner | 2 minggu |
| 7 | Dashboard, laporan | 2 minggu |
| 8 | PWA & optimasi | 1–2 minggu |
| 9 | Testing, deployment | 2 minggu |
| 10 | Fitur lanjutan | Berkelanjutan |

\* **[Asumsi]** 1–2 developer; dengan AI agent estimasi dapat berubah.

---

## 21. Risiko

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Onboarding Midtrans produksi lambat | Delay go-live | Mulai proses sejak Fase 1 |
| Scanner kamera beda iOS/Android | Gate lambat | Uji perangkat nyata; input manual |
| Webhook gagal/terlambat | Tiket terlambat | Idempotensi, retry, scheduler |
| Overbooking | Reputasi | Uji konkurensi, `CHECK`, rekonsiliasi |
| Pembayaran terlambat | Uang tanpa tiket | Buffer waktu, jalur anomali |
| Internet buruk di gate | Antrean | SOP fallback; offline mode nanti |
| Kebocoran data | Hukum | Minimasi, akses terbatas, audit log |

---

**[Akhir dokumen — bagian 4 dari 4]**
