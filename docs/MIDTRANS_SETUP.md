# Setup Midtrans (Sandbox dan Produksi)

Dokumen ini mencatat hal-hal yang mudah salah saat menghubungkan MusenTix ke
Midtrans. Sebagian besar isinya berasal dari kesalahan nyata yang sudah terjadi
di proyek ini, bukan teori.

Referensi resmi: <https://docs.midtrans.com/docs/access-keys>

---

## 1. Sejak 28 Oktober 2026 ada empat kredensial, bukan dua

Midtrans memisahkan kunci penanda tangan notifikasi dari kunci pengotorisasi
API. Ini sumber kebingungan paling mahal karena keduanya sama-sama diawali
`SB-Mid-server-` dan sama-sama disebut "kunci server" di percakapan sehari-hari.

| Kunci | Jumlah | Dipakai untuk | Bisa dilihat lagi? |
|---|---|---|---|
| **Merchant ID** | 1 | Identitas akun saat menghubungi Midtrans | Ya |
| **Client Key** | 1 | Snap.js dan tokenisasi kartu di browser | Ya |
| **Server Key** | sampai 10 | Mengotorisasi panggilan API dari server (Snap, status, expire) | **Tidak** — hanya sekali saat dibuat |
| **Signature Secret Key** | 1 | Memverifikasi `signature_key` pada notifikasi | Ya |

### Pemetaan ke `.env`

| Variabel | Isi dengan |
|---|---|
| `MIDTRANS_SERVER_KEY` | **Server Key** dari tabel `Server Keys` |
| `MIDTRANS_SIGNATURE_KEY` | **Signature Secret Key** |
| `MIDTRANS_CLIENT_KEY` | **Client Key** |

`MIDTRANS_SIGNATURE_KEY` boleh dikosongkan. Nilainya akan jatuh kembali ke
`MIDTRANS_SERVER_KEY`, yang benar untuk akun lama yang belum pernah meregenerasi
Signature Secret Key (Midtrans menyatakan nilai keduanya sama pada akun seperti
itu).

### Kesalahan yang pernah terjadi di proyek ini

`.env` diisi dengan **Signature Secret Key** pada `MIDTRANS_SERVER_KEY`. Kunci
itu tidak dapat mengotorisasi API, sehingga setiap pembuatan transaksi dijawab
`HTTP 401 {"error":"Unauthorized"}`. Kunci terlihat benar — formatnya tepat,
panjangnya tepat, disalin langsung dari dashboard — sehingga mudah disimpulkan
"kuncinya salah" padahal yang salah adalah **kunci mana yang dipakai**.

Cara membedakan dengan cepat:

```bash
curl -s -o /dev/null -w '%{http_code}\n' \
  -X POST https://app.sandbox.midtrans.com/snap/v1/transactions \
  -u "SB-Mid-server-xxxx:" \
  -H 'Content-Type: application/json' \
  -d '{"transaction_details":{"order_id":"probe-1","gross_amount":1000}}'
```

`401` berarti kunci bukan Server Key yang sah untuk API. `201` berarti berhasil.

---

## 2. Server Key hanya ditampilkan sekali

Server Key tidak dapat dilihat lagi setelah pop-up pembuatan ditutup. Bila
hilang, buat kunci baru, pasang di `.env`, lalu revoke kunci lama — jangan
mencoba menebak atau menyalin ulang dari tempat lain.

Karena itu juga: **jangan** menyimpan Server Key di tempat yang tidak bisa
dipulihkan. Untuk produksi, simpan di manajer rahasia, bukan hanya di `.env`
server.

---

## 3. Izin Server Key harus mencakup Snap Checkout (Write)

Server Key baru memberi izin **None** untuk semua produk secara bawaan. Kunci
tanpa izin akan menjawab `HTTP 403 Forbidden`, bukan 401 — perbedaan ini penting
saat menelusuri masalah.

Minimal yang dibutuhkan MusenTix:

| Bagian | Izin |
|---|---|
| Snap Checkout | **Write** (membuat transaksi) |
| Core API | **Read** (opsional; hanya bila memakai endpoint status Core API) |

Aplikasi ini memakai:

- `POST /snap/v1/transactions` — butuh Snap Checkout **Write**
- `GET /v2/{order_id}/status` — butuh Core API **Read**

---

## 4. Signature notifikasi memakai Signature Secret Key

Rumusnya:

```
SHA512(order_id + status_code + gross_amount + SignatureSecretKey)
```

Sebelum 28 Oktober 2026 rumus ini memakai Server Key. Kode MusenTix sudah
memakai `MIDTRANS_SIGNATURE_KEY` (dengan fallback ke Server Key), dan perilaku
ini dikunci oleh `tests/Feature/Payment/MidtransSignatureTest.php`.

Bila signature mulai ditolak setelah Anda meregenerasi Signature Secret Key,
periksa `MIDTRANS_SIGNATURE_KEY` — bukan `MIDTRANS_SERVER_KEY`.

---

## 5. Menjalankan di lokal: batas yang nyata

Midtrans mengirim notifikasi dari server mereka ke URL publik. `localhost`
tidak dapat dijangkau dari internet, jadi **webhook tidak akan pernah sampai**
bila aplikasi hanya berjalan di `http://localhost:8000`.

Yang masih bisa diuji di lokal:

- Pembuatan transaksi Snap (tombol bayar membuka pop-up Midtrans)
- Halaman pembayaran Midtrans sampai selesai
- Pencatatan pembayaran berstatus `pending`

Yang tidak bisa diuji tanpa URL publik:

- Notifikasi otomatis yang mengubah `pending` menjadi `paid`
- Penerbitan tiket otomatis setelah pembayaran
- Rekonsiliasi via `payments:reconcile`

### Pilihan untuk menutup celah itu

**A. Tunnel sementara (untuk uji coba).** `cloudflared` lebih andal daripada
`localtunnel` untuk webhook karena tidak menampilkan halaman peringatan browser:

```bash
cloudflared tunnel --url http://localhost:8000
```

Lalu daftarkan `https://<nama-acak>.trycloudflare.com/webhooks/midtrans` pada
**Settings > Payment Settings > Payment Notification URL**.

Keterbatasan: URL berubah setiap kali tunnel dijalankan ulang, sehingga URL di
dashboard harus diperbarui setiap kali. Ini tidak memenuhi AC-4 (pembayaran
produksi end-to-end) karena AC-4 membutuhkan domain tetap.

**B. Kirim notifikasi manual dari dashboard.** Setelah membayar di sandbox,
buka **Transactions**, pilih transaksi, lalu gunakan **Resend notification**.
Ini menguji handler webhook tanpa tunnel, tetapi **belum** menguji pengiriman
nyata dari Midtrans ke server kita.

---

## 6. Beralih ke produksi

1. Selesaikan aktivasi akun di dashboard produksi.
2. `MIDTRANS_IS_PRODUCTION=true`.
3. `MIDTRANS_SERVER_KEY` dan `MIDTRANS_CLIENT_KEY` diganti kunci **produksi**
   (tanpa prefix `SB-`).
4. `MIDTRANS_SNAP_URL` **dikosongkan** — nilai bawaan mengikuti
   `MIDTRANS_IS_PRODUCTION`. Mengisinya dengan host sandbox lalu menyalakan mode
   produksi adalah kesalahan yang membuat pembayaran gagal tanpa pesan jelas.
5. Isi **Payment Notification URL** dengan domain tetap:
   `https://<domain-anda>/webhooks/midtrans`
6. `php artisan config:clear` dan `php artisan config:cache`.
7. Uji satu transaksi bernilai kecil (mis. Rp1.000) dengan kartu sungguhan,
   lalu pastikan tiket terbit.

---

## 7. Kartu uji sandbox

| Kartu | Perilaku |
|---|---|
| `4811 1111 1111 1114` | Sukses (3DS), OTP `112233` |
| `4911 1111 1111 1113` | Ditolak bank |
| `4411 1111 1111 1118` | Gagal 3DS |

Masa berlaku: bulan/tahun di masa depan. CVV: `123`.

---

## 8. Daftar periksa saat pembayaran gagal

1. `php artisan config:show services.midtrans` — pastikan `is_production`,
   `api_base_url`, dan `snap_url` konsisten satu sama lain.
2. Cek `storage/logs/laravel.log` untuk `midtrans.snap_failed`; body respons
   Midtrans ikut tercatat di sana.
3. `401` → kunci bukan Server Key, atau kunci sudah di-revoke.
4. `403` → Server Key tidak punya izin Snap Checkout Write.
5. `cURL error 60` → bundel CA PHP tidak terbaca. Setel `curl.cainfo` dan
   `openssl.cafile` di `php.ini`.
6. Notifikasi masuk tetapi ditolak → periksa `MIDTRANS_SIGNATURE_KEY`.

---

## 9. Jebakan yang sudah pernah menggigit proyek ini

Tiga kesalahan di bawah semuanya lolos dari tes otomatis pada awalnya, karena
`FakeMidtransClient` tidak menyentuh jalur HTTP yang sebenarnya.

### 9.1 Kunci tertukar (HTTP 401)

`MIDTRANS_SERVER_KEY` diisi Signature Secret Key. Format benar, panjang benar,
disalin langsung dari dashboard — tetapi kunci itu tidak dapat mengotorisasi
API. Gejalanya `401 {"error":"Unauthorized"}` pada setiap pembuatan transaksi.

### 9.2 Signature memakai Server Key (notifikasi ditolak)

Kode memakai Server Key untuk menghitung `SHA512(order_id + status_code +
gross_amount + kunci)`. Selama Server Key dan Signature Secret Key bernilai
sama, tidak ada gejala. Begitu merchant meregenerasi Signature Secret Key,
**setiap** notifikasi ditolak — pembayaran lunas tidak pernah menjadi tiket.

Bukti dari data nyata (respons `GET /v2/{order}/status` Midtrans):

```
signature_key_prefix      : SB-Mid-server-monWgZ   (Signature Secret Key)
server_key_prefix         : SB-Mid-server-QPNTYa   (Server Key)
keys_differ               : true
valid_with_signature_key  : true    <- signature asli Midtrans LOLOS
valid_with_server_key     : false   <- dengan Server Key GAGAL
```

Dikunci oleh `tests/Feature/Payment/MidtransSignatureTest.php`.

### 9.3 Kesalahan API dibungkus HTTP 200

Midtrans membalas **HTTP 200** untuk kesalahan tingkat API, dengan kode
sebenarnya di dalam body:

```json
{"status_code":"404","status_message":"Transaction doesn't exist."}
```

Kode yang hanya memeriksa `$response->successful()` menerima body itu sebagai
status transaksi yang sah. Akibatnya setiap notifikasi untuk transaksi yang
belum dikenal berubah menjadi **anomali palsu** di antrean admin, dan penyebab
sebenarnya tidak pernah tercatat.

Perbaikannya: `fetchTransactionStatus()` mewajibkan adanya
`transaction_status` — penanda yang ada pada setiap respons transaksi nyata
(`pending`, `settlement`, `expire`, ...) dan tidak pernah ada pada body
kesalahan. Dikunci oleh `tests/Feature/Payment/MidtransStatusApiTest.php`.

---

## 10. Hasil uji sandbox yang sudah dilakukan

Transaksi dibuat lewat Core API sandbox, lalu notifikasi dikirim ke endpoint
webhook aplikasi melalui tunnel publik:

| Tahap | Hasil |
|---|---|
| Tokenisasi kartu `4811 1111 1111 1114` | `token_id` diterima |
| Charge kartu (`secure=false`) | `transaction_status: capture` |
| `GET /v2/{order}/status` | `transaction_status` sah, signature Midtrans terverifikasi |
| Notifikasi ke `/webhooks/midtrans` | `HTTP 200`, `result: applied` |
| Booking | `pending_payment` → **`confirmed`** |
| Pembayaran | `pending` → **`paid`** |
| Tiket | 0 → **1 tiket terbit** |

Ini membuktikan rantai webhook → verifikasi signature → pengambilan status →
penerbitan tiket berjalan pada data Midtrans yang nyata. Yang **belum**
terbukti adalah pengiriman notifikasi **oleh Midtrans sendiri** (bukan oleh
skrip uji) ke domain tetap — itu bagian dari AC-4 yang masih terblokir.

### 10.1 Uji bayar sungguhan dari browser (14 Nov 2026)

Pembayaran dilakukan lewat popup Snap di `http://localhost:8000` dengan kartu
`4811 1111 1111 1114`. Midtrans menyatakan **Payment successful** Rp100.000.

| Yang diperiksa | Hasil |
|---|---|
| Transaksi di Midtrans | `capture`, `fraud_status: accept`, `settlement_time` terisi |
| Notifikasi masuk ke `/webhooks/midtrans` | **Tidak ada** |
| `payment_events` baru | **Tidak ada** |
| Booking setelah bayar | tetap `pending_payment` |
| Setelah `payments:reconcile --minutes=1` | `confirmed`, pembayaran `paid`, **2 tiket terbit** |
| Pemindaian gate | `not_yet_valid` — benar, jadwal kunjungan 16/11 belum tiba |

**Penyebab notifikasi tidak masuk:** URL notifikasi di dashboard Midtrans masih
menunjuk ke tunnel **lama** (`modern-rules-joke.loca.lt`, sudah mati). Tunnel
aktif sekarang bernama lain, sehingga notifikasi dikirim ke alamat yang tidak
ada. Log akses server hanya mencatat tiga permintaan `/webhooks/midtrans`, dan
ketiganya berasal dari skrip uji — bukan dari Midtrans.

**Pelajaran:** URL `trycloudflare`/`localtunnel` berubah setiap kali tunnel
dijalankan ulang. Setiap kali tunnel restart, URL di dashboard **wajib**
diperbarui, atau notifikasi akan hilang tanpa jejak apa pun di sisi aplikasi.
Inilah alasan AC-4 menuntut domain tetap: tanpa domain tetap, keandalan
notifikasi bergantung pada langkah manual yang mudah terlewat.

Jalur pemulihan bekerja sesuai `RUNBOOK_PAYMENT_ANOMALY.md` §5: transaksi yang
lunas tetap dikonfirmasi lewat `payments:reconcile` meski notifikasi hilang.
Ini **bukan** pengganti webhook — jeda rekonsiliasi berarti pengunjung menunggu
sebelum tiketnya terbit.
