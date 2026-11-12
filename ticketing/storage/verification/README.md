# Skrip Verifikasi Fase 8

Folder ini berisi perkakas verifikasi **manual** yang dipakai untuk membuktikan
kriteria penerimaan Fase 8 (PWA + optimasi mobile). Ini **bukan** bagian dari
aplikasi: tidak ada kode produksi yang mengimpornya, dan tidak dijalankan di CI.

Jalankan hanya saat melakukan verifikasi ulang di mesin lokal.

## Prasyarat

```powershell
# terminal 1 — server aplikasi
cd ticketing
php artisan serve --port=8000
```

## `check-offline.cjs` — uji offline nyata (AC-2, AC-3, AC-4)

Menjalankan Chrome headless lewat DevTools Protocol dan **mematikan upstream
sungguhan** untuk mensimulasikan offline. Ini penting karena
`Network.emulateNetworkConditions` bawaan DevTools **tidak** menjangkau `fetch`
yang dijalankan target service worker, sehingga emulasi biasa bisa memberi
hasil palsu ("lolos" padahal server masih hidup).

Skrip menjalankan proxy gzip sendiri di port `8102`, jadi tidak perlu server
tambahan. Delapan pemeriksaan:

1. service worker aktif & mengendalikan halaman
2. scope service worker mencakup root
3. service worker disajikan dari `/sw.js`
4. e-ticket tersimpan di IndexedDB beserta QR
5. `/tickets` (NetworkOnly) menampilkan fallback offline, bukan halaman cache
6. e-ticket offline tampil dengan label "Tidak terverifikasi"
7. halaman publik tetap terbuka dari cache saat offline
8. `/tickets` kembali normal setelah online

```powershell
cd ticketing
$env:TICKET_UUID="<uuid tiket milik user@test.com>"
node storage\verification\check-offline.cjs
```

Variabel lingkungan opsional: `UPSTREAM_PORT` (default `8000`),
`PROXY_PORT` (default `8102`), `CDP_PORT` (default `9338`).

## `check-pages.cjs` — smoke test render + lebar 360 px (AC-6)

Membuka setiap halaman publik dan terautentikasi pada viewport 360×640 dan
memeriksa bahwa `document.scrollWidth === document.clientWidth` (tidak ada
overflow dokumen), bahwa aplikasi Vue benar-benar ter-mount, dan bahwa payload
Inertia ada (bukan halaman error).

Default `BASE_URL` adalah `http://127.0.0.1:8100` (proxy gzip). Bila proxy tidak
dijalankan, arahkan langsung ke server aplikasi:

```powershell
cd ticketing
$env:BASE_URL="http://127.0.0.1:8000"
node storage\verification\check-pages.cjs
```

Akun uji dibaca dari database lokal: `user@test.com` / `password`,
`organizer@test.com` / `password`, `admin@jejak.test` / `password123`.

## `lighthouse-proxy.cjs` — proxy gzip untuk pengukuran LCP (AC-5)

`php artisan serve` tidak mengompres respons, sehingga Lighthouse mobile
membaca LCP ~3,5 s — angka yang tidak mewakili produksi. Proxy ini meng-gzip
respons di port `8100` dan meneruskannya ke `:8000`, menyerupai Nginx.

```powershell
cd ticketing
node storage\verification\lighthouse-proxy.cjs     # terminal 2
```

Lalu jalankan Lighthouse terhadap `http://127.0.0.1:8100/`.

## Berkas laporan

`lighthouse-*.json` adalah keluaran mentah Lighthouse yang disimpan sebagai
bukti. Berkas ini **di-gitignore** (ukurannya besar dan dapat dihasilkan ulang);
angka ringkasannya dicatat di `docs/ROADMAP.md` Fase 8.
