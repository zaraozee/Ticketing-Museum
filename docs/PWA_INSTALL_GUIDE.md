# Panduan Pemasangan PWA MusenTix

Dokumen ini menjelaskan cara memasang aplikasi MusenTix sebagai PWA (Progressive
Web App) di perangkat pengguna, cara memverifikasi bahwa pemasangan berhasil,
dan cara menguji ulang kriteria penerimaan Fase 8.

Rujukan: `ROADMAP.md` Fase 8, `SYSTEM_DESIGN.md` §15.

---

## 1. Prasyarat

| Hal | Syarat |
|---|---|
| Protokol | **HTTPS** di produksi. `localhost`/`127.0.0.1` dikecualikan browser dan tetap dianggap konteks aman untuk pengembangan |
| Manifest | `GET /manifest.webmanifest` mengembalikan 200 dengan `Content-Type: application/manifest+json` |
| Service worker | `GET /sw.js` mengembalikan 200, header `Service-Worker-Allowed: /`, `Cache-Control: no-store` |
| Ikon | `public/icons/icon-192.png`, `icon-512.png`, `maskable-192.png`, `maskable-512.png` ada di disk |
| Build aset | `public/build/` sudah dihasilkan (`npm run build`) dan `public/hot` **tidak** ada |

Bila ikon belum ada, buat ulang:

```powershell
php artisan pwa:icons --force
```

---

## 2. Pemasangan di Android (Chrome / Edge)

1. Buka aplikasi di Chrome.
2. Ketuk tombol **"Pasang Aplikasi"** yang muncul pada banner ajakan, **atau**
   menu ⋮ → **"Instal aplikasi"** / **"Tambahkan ke Layar utama"**.
3. Konfirmasi. Ikon MusenTix muncul di launcher dan aplikasi terbuka dalam mode
   `standalone` (tanpa address bar).
4. Bila banner ajakan tidak muncul, pastikan service worker aktif:
   DevTools → **Application** → **Service workers** harus menampilkan status
   `activated and is running` untuk `/sw.js` dengan scope `/`.

> Banner ajakan hanya tampil sekali per periode tertentu dan tidak muncul bila
> pengguna pernah menutupnya. Menu browser tetap bisa dipakai.

## 3. Pemasangan di iOS (Safari)

iOS tidak memiliki `beforeinstallprompt`, jadi pemasangan dilakukan manual.
Aplikasi menampilkan petunjuk ini otomatis pada perangkat iOS.

1. Buka aplikasi di **Safari** (bukan Chrome/Firefox di iOS).
2. Ketuk tombol **Bagikan** (kotak dengan panah ke atas).
3. Pilih **"Tambahkan ke Layar Utama"**.
4. Ketuk **"Tambah"**. Ikon MusenTix muncul di layar utama.

Catatan iOS: e-ticket offline tetap berfungsi di dalam Safari, tetapi Web Push
belum tersedia di MVP (lihat `SYSTEM_DESIGN.md` §15.4).

## 4. Pemasangan di desktop (Chrome / Edge)

Ikon **instal** (monitor dengan panah) muncul di sisi kanan address bar.
Klik ikon tersebut → **"Instal"**. Aplikasi terbuka di jendela sendiri.

---

## 5. Yang berfungsi saat offline

| Fitur | Perilaku |
|---|---|
| E-ticket yang pernah dibuka | Tersedia dari IndexedDB, QR dirender di perangkat, berlabel **"Tidak terverifikasi — terakhir diperbarui {waktu}"** |
| Daftar e-ticket tersimpan | `/offline/tickets` (di-precache saat instalasi) |
| Halaman publik yang pernah dikunjungi | Tampil dari cache (mungkin sedikit usang) |
| Halaman publik yang belum pernah dikunjungi | Halaman pemberitahuan offline, bukan error browser |
| Checkout, pembayaran, status booking | **Tidak tersedia.** Selalu diambil dari jaringan; tombol dinonaktifkan saat offline |
| Gate Scanner | **Tidak tersedia di MVP.** Petugas memakai prosedur manual museum |
| Login, area pengelola/admin | **Tidak tersedia offline** (NetworkOnly) |

Logout menghapus seluruh snapshot tiket di perangkat sebelum sesi diakhiri.

---

## 6. Pembaruan aplikasi

Pembaruan **tidak pernah** memuat ulang halaman secara otomatis, supaya alur
checkout tidak terputus.

1. Saat versi baru terdeteksi, muncul banner **"Versi baru tersedia"**.
2. Pengguna menekan **"Muat ulang"** ketika sudah tidak berada di tengah
   transaksi.
3. Service worker baru mengambil alih (`SKIP_WAITING`), lalu halaman dimuat
   ulang.

Bila pengguna mengabaikan banner, versi lama tetap berjalan normal dan
pembaruan akan ditawarkan lagi pada kunjungan berikutnya.

---

## 7. Verifikasi cepat

### 7.1 Pemeriksaan manual di DevTools

| Panel | Yang diperiksa |
|---|---|
| Application → Manifest | Nama, ikon 192/512, `display: standalone`, tanpa error |
| Application → Service workers | `/sw.js` aktif, scope `/`, status *activated* |
| Application → Cache Storage | `musentix-public-pages-v1`, `musentix-museum-images-v1`, cache precache Workbox |
| Application → IndexedDB | Database `musentix-tickets`, object store `tickets` berisi snapshot tiket |
| Lighthouse → PWA | Semua audit berskor 1 (installable, maskable icon, splash, themed omnibox, viewport) |

### 7.2 Uji offline yang benar

Emulasi offline bawaan DevTools (`Network → Offline`) **tidak** menjangkau
`fetch` yang dijalankan service worker, sehingga halaman terautentikasi bisa
tampak "berhasil" padahal server masih hidup. Untuk uji yang sah, matikan
sumbernya:

- Hentikan server aplikasi (`php artisan serve` / PHP-FPM), lalu buka
  `/tickets`. Yang benar: halaman fallback "Tiket Offline", **bukan** halaman
  daftar tiket dari cache.
- Skrip `ticketing/storage/verification/check-offline.cjs` melakukan ini otomatis:
  ia menjalankan proxy gzip sendiri, mematikan upstream, dan memverifikasi 8
  pemeriksaan (scope SW, IndexedDB, NetworkOnly, e-ticket offline, halaman
  publik dari cache, kembali online).

```powershell
cd ticketing
$env:TICKET_UUID="<uuid tiket milik user@test.com>"
node storage\verification\check-offline.cjs
```

### 7.3 Perintah gate kualitas

```powershell
cd ticketing
./vendor/bin/pint
./vendor/bin/phpstan analyse --memory-limit=2G --no-progress
php artisan test
npx vue-tsc --noEmit
npm run build
```

---

## 8. Pemecahan masalah

| Gejala | Penyebab umum | Perbaikan |
|---|---|---|
| Tombol instal tidak muncul | Halaman tidak di HTTPS (selain localhost), manifest error, atau SW gagal terdaftar | Periksa panel Manifest & Service workers; pastikan `/sw.js` 200 dan scope `/` |
| `manifest.webmanifest` 404 | Route belum terdaftar atau cache config | `php artisan route:list --path=manifest`; `php artisan config:clear` |
| SW scope menjadi `/build/` | SW lama masih tersaji dari `/build/sw.js` | Pastikan `vite.config.ts` memakai `injectManifest` dan `ServiceWorkerController` yang menyajikan `/sw.js` |
| Aset 404 setelah deploy | `public/build/manifest.json` tidak ikut terunggah atau `public/hot` masih ada | Hapus `public/hot`, jalankan `npm run build`, unggah ulang `public/build` |
| Pengguna terjebak di versi lama | Banner pembaruan diabaikan dan cache lama masih aktif | Minta pengguna menekan "Muat ulang" pada banner; atau tutup semua tab aplikasi lalu buka kembali |
| E-ticket tidak muncul offline | Tiket belum pernah dibuka saat online, atau pengguna sudah logout (IndexedDB dihapus) | Buka tiket sekali saat online; jangan logout sebelum kunjungan |
| Tabel melebar di layar kecil | Halaman belum memakai pembungkus `overflow-x-auto` | Bungkus tabel dengan `overflow-x-auto` + `min-w-[36rem]` seperti halaman laporan |
