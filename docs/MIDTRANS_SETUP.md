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

### 6.1 Periksa dulu sebelum memakai uang sungguhan

Langkah 1–6 mudah salah dengan cara yang tidak terlihat sampai transaksi
pertama gagal. Karena itu ada perintah khusus:

```bash
php artisan payments:check-midtrans          # hanya membaca konfigurasi
php artisan payments:check-midtrans --probe  # + memastikan kunci diterima Midtrans
```

Perintah ini **tidak membuat transaksi**. Mode `--probe` mengirim permintaan
status untuk order yang tidak ada; jawaban `Transaction doesn't exist` berarti
kunci **diterima**, sedangkan `401` berarti kunci ditolak.

Yang diperiksa:

| Pemeriksaan | Kegagalan yang dicegah |
|---|---|
| Server Key dan Client Key terisi | `401` pada transaksi pertama |
| Prefix kunci cocok dengan mode | Snap.js produksi menolak token sandbox (dan sebaliknya) |
| `MIDTRANS_SNAP_URL` mengikuti mode | Halaman pembayaran memuat skrip host yang salah |
| Signature Secret Key terpisah | Semua notifikasi ditolak → pembayaran lunas tidak menjadi tiket |
| `APP_URL` HTTPS dan bukan localhost saat mode produksi | Midtrans tidak dapat menjangkau webhook → tiket tidak pernah terbit |
| Konfigurasi tidak sedang di-cache | `.env` baru diabaikan karena `config:cache` basi |

Keluaran normal di sandbox (kondisi proyek saat ini):

```
 [OK] MIDTRANS_SERVER_KEY: SB-Mid-s..n~Tc
 [OK] MIDTRANS_IS_PRODUCTION: false (sandbox)
 [OK] MIDTRANS_SIGNATURE_KEY: SB-Mid-s..fRov (terpisah dari Server Key)
 [OK] API Snap: https://app.sandbox.midtrans.com
 [OK] Snap.js: https://app.sandbox.midtrans.com/snap/snap.js
 [OK] Kunci diterima Midtrans (HTTP 200: Transaction doesn't exist.)
 Konfigurasi konsisten.
```

Perintah ini keluar dengan kode `1` bila ada ketidakcocokan, sehingga dapat
dipakai sebagai langkah wajib pada daftar periksa go-live (`GO_LIVE_CHECKLIST.md`
§7) dan bukan sekadar dibaca manusia.

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

1. `php artisan payments:check-midtrans` — periksa konsistensi kunci, mode, dan
   URL sekaligus. Bila perlu, tambahkan `--probe` untuk memastikan kunci
   benar-benar diterima Midtrans. Ini menggantikan pemeriksaan manual
   `config:show` satu per satu.
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
penerbitan tiket berjalan pada data Midtrans yang nyata.

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

### 10.2 Notifikasi uji dari dashboard Midtrans (bukti pengiriman nyata)

Tombol **Test notification URL** di **Settings → Payment → Payment notification
URL** mengirim POST **sungguhan** dari server Midtrans ke endpoint aplikasi.
Tombol ini bukan simulasi di sisi browser.

Empat kali tombol ditekan, empat notifikasi masuk dan tercatat di
`payment_events`:

| # | `order_id` | Signature | Hasil | Waktu |
|---|---|---|---|---|
| 10 | `payment_notif_test_M473511615_f39ecdd4…` | **valid** | `unknown_order` | 09:33:41 |
| 12 | `payment_notif_test_M473511615_e573ec1c…` | **valid** | `unknown_order` | 09:55:04 |
| 13 | `payment_notif_test_M473511615_2b622c93…` | **valid** | `unknown_order` | 09:56:33 |
| 14 | `payment_notif_test_M473511615_8d17b9bd…` | **valid** | `unknown_order` | 10:00:59 |

Yang dibuktikan ketiganya:

1. **Midtrans dapat menjangkau endpoint aplikasi** melewati tunnel.
2. **URL notifikasi di dashboard sudah benar** dan tersimpan.
3. **Verifikasi signature menerima tanda tangan asli Midtrans** — bukan hanya
   tanda tangan yang disusun skrip uji.
4. Order tak dikenal ditangani dengan **HTTP 200 `unknown_order`**, bukan 500:
   gateway tidak akan mengirim ulang tanpa henti, dan kejadiannya tetap
   tercatat.

`unknown_order` adalah hasil yang **benar** untuk notifikasi uji ini: Midtrans
memakai `order_id` karangan (`payment_notif_test_…`) yang tidak pernah ada di
database. Yang diuji adalah jalur pengiriman dan verifikasi, bukan transaksi.

### 10.3 AC-4: notifikasi transaksi nyata masuk tanpa rekonsiliasi

Ini bukti yang selama ini dicari. Pembayaran **baru** dilakukan dari browser
(`MUS-FT6U7E`, Rp50.000, kartu `4811 1111 1111 1114`), dan notifikasi datang
**sendiri** dari Midtrans — `payments:reconcile` **tidak** dijalankan.

| Yang diperiksa | Hasil |
|---|---|
| `payment_events` id 16 | `order_id: MUS-FT6U7E-1`, `signature_valid: true`, `result: applied` |
| Waktu transaksi Midtrans → waktu proses | 17:10:08 → **17:10:11** (3 detik) |
| Booking `MUS-FT6U7E` | `pending_payment` → **`confirmed`** |
| Pembayaran | `pending` → **`paid`**, `needs_review: false` |
| Tiket | **1 tiket aktif** (`7B8M9VJPQZ`) |
| `payments:reconcile` dijalankan? | **Tidak** |
| `ops:health` | exit 0 |

**Bukti kriptografis.** `signature_key` pada payload notifikasi
(`0b56cbd7a1853437…`) **identik** dengan `signature_key` dari
`GET /v2/MUS-FT6U7E-1/status`. Hanya Midtrans yang dapat menghasilkan nilai
itu, sehingga payload tidak mungkin disusun sendiri.

**Field yang tidak dapat dikarang aplikasi** juga ikut terbawa:
`approval_code: 1794651009442`, `masked_card: 48111111-1114`, `bank: cimb`,
`channel_response_code: "00"`, `settlement_time` terisi.

**Tentang OTP.** Kartu uji sandbox `4811 1111 1114` disetujui langsung tanpa
3DS/OTP, sehingga `transaction_status` langsung `capture` dan
`fraud_status: accept`. Ini normal untuk sandbox, **bukan** indikasi error.
Di produksi, kartu asli akan meminta 3DS dan `fraud_status` dapat bernilai
`challenge`; kode sudah memetakan `capture` + `challenge` ke `pending` (bukan
`paid`) sehingga tiket tidak terbit sebelum bank menyetujui.

> **Batas yang jujur.** AC-4 menuntut pembayaran **produksi**. Yang terbukti di
> sini adalah seluruh rantai bekerja di **sandbox** dengan notifikasi nyata dari
> Midtrans. Sisa pekerjaan AC-4 murni lingkungan: akun Midtrans produksi,
> domain tetap, dan satu transaksi bernilai kecil. Bukan lagi pertanyaan
> "apakah sistemnya bekerja".

### 10.4 URL tetap dan verifikasi signature dengan data asli Midtrans

Tunnel `localtunnel` dengan subdomain tetap dipakai agar URL notifikasi tidak
berubah setiap restart:

```bash
lt --port 8000 --subdomain musentix
# → https://musentix.loca.lt
```

`loca.lt` menampilkan halaman peringatan untuk permintaan **browser**, tetapi
**tidak** untuk permintaan server-to-server: POST dari luar tanpa header
khusus tetap sampai ke aplikasi. Ini sudah diuji dengan `User-Agent` mirip
Midtrans dan tanpa header `bypass-tunnel-reminder`.

Untuk membuktikan verifikasi signature bekerja pada tanda tangan yang
**benar-benar dibuat Midtrans** (bukan buatan skrip uji), payload notifikasi
disusun dari respons `GET /v2/{order_id}/status` — termasuk `signature_key`
asli — lalu dikirim ke URL tetap:

| Uji | Hasil |
|---|---|
| POST pertama (signature asli Midtrans) | **HTTP 200** `{"message":"OK","result":"applied"}` |
| POST kedua, payload identik | **HTTP 200** `{"message":"OK","result":"ignored_duplicate"}` |
| Jumlah tiket booking `MUS-NM2CQT` | tetap **2** (tidak tergandakan) |

Artinya rantai verifikasi signature → pengambilan status → penerapan status →
idempotensi terbukti benar terhadap tanda tangan produksi Midtrans, bukan hanya
terhadap payload buatan sendiri.

Yang **tetap belum** terbukti: Midtrans mengirim notifikasi atas inisiatifnya
sendiri ke URL yang terdaftar. Itu memerlukan URL notifikasi di dashboard
menunjuk ke tunnel aktif — langkah manual yang harus diulang setiap tunnel
dijalankan ulang, dan justru alasan AC-4 menuntut domain tetap.

### 10.5 Verifikasi konfigurasi tanpa memakai uang sungguhan (15 Nov 2026)

Sisa pekerjaan AC-4 bersifat lingkungan, tetapi kesalahan **konfigurasi** saat
beralih ke produksi tetap dapat terjadi dan gejalanya menyesatkan. Karena itu
ditambahkan perintah `payments:check-midtrans` (§6.1) beserta 12 tes yang
mengunci perilakunya.

Yang diperiksa pada konfigurasi nyata proyek ini:

| Pemeriksaan | Hasil |
|---|---|
| Server Key dan Client Key terisi | `[OK]` |
| Prefix kunci cocok dengan mode | `[OK]` — kunci `SB-Mid-…` pada mode sandbox |
| Signature Secret Key terpisah dari Server Key | `[OK]` — kondisi setelah regenerasi |
| Snap.js, API Snap, API Status mengikuti mode | `[OK]` — ketiganya menunjuk host sandbox |
| `--probe` ke API Midtrans | `[OK]` **HTTP 200 `Transaction doesn't exist.`** — kunci diterima, bukan ditolak |
| Kode keluar | `0` (konfigurasi konsisten) |

`--probe` sengaja memakai `GET /v2/{order}/status` untuk order yang tidak ada,
bukan membuat transaksi. Jawaban `Transaction doesn't exist` adalah hasil yang
**benar**: Midtrans mengenali kuncinya lalu mengatakan transaksinya tidak ada.
`401` akan berarti kunci ditolak, dan itulah yang ingin diketahui sebelum uang
sungguhan dipakai.

Tes yang menyertainya membuktikan perintah ini **gagal** pada kondisi yang
berbahaya — bukan sekadar selalu hijau:

| Kondisi yang diuji | Perilaku yang dikunci |
|---|---|
| Kunci `SB-Mid-…` dengan `MIDTRANS_IS_PRODUCTION=true` | ditolak, `exit 1` |
| Kunci produksi dengan mode sandbox | ditolak, `exit 1` |
| `MIDTRANS_SNAP_URL` tertinggal di host sandbox saat mode produksi | ditolak, `exit 1` |
| `APP_URL` localhost saat mode produksi | ditolak, `exit 1` |
| `APP_URL` bukan HTTPS | ditolak, `exit 1` |
| Kredensial kosong | disebut satu per satu, `exit 1` |
| `--probe` dijawab `401` / `403` | pesan spesifik, `exit 1` |
| Gateway tidak terjangkau | pesan jelas, bukan crash |
| Kunci penuh tercetak di terminal | tidak pernah terjadi (hanya sebagian dimask) |

Kedua tes penjaga (prefix kunci dan localhost produksi) dibuktikan "menggigit"
dengan sengaja merusak kodenya lebih dulu: masing-masing membuat tes gagal, lalu
kode dikembalikan.

> **Batas yang tetap jujur.** Ini memverifikasi **konfigurasi**, bukan transaksi
> produksi. AC-4 tetap menuntut akun Midtrans produksi, domain HTTPS tetap, dan
> satu pembayaran bernilai kecil yang benar-benar terjadi. Yang berubah: saat
> ketiganya tersedia, kesalahan konfigurasi tidak lagi menjadi penyebab
> kegagalan yang harus ditebak.
