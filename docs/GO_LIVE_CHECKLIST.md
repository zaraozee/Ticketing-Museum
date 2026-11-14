# Checklist Go-Live — MusenTix

Daftar periksa terakhir sebelum aplikasi dipakai museum pilot. Setiap baris
harus dapat ditunjukkan buktinya; baris yang belum dapat dibuktikan **bukan**
"nanti", melainkan penghalang go-live.

Rujukan: `ROADMAP.md` Fase 9, `DEPLOYMENT.md`, `SECURITY_REVIEW.md`,
`RUNBOOK.md`, `SOP_GATE.md`, `RUNBOOK_PAYMENT_ANOMALY.md`.

Tanggal target go-live: _diisi saat penjadwalan_
Museum pilot: _diisi_
Penanggung jawab teknis: _diisi_
Penanggung jawab museum: _diisi_

---

## 1. Kode dan gerbang mutu

| # | Butir | Bukti yang harus ada | Status |
|---|---|---|---|
| 1.1 | `vendor/bin/pint --test` bersih | keluaran terminal | ☐ |
| 1.2 | `vendor/bin/phpstan analyse` 0 error | keluaran terminal | ☐ |
| 1.3 | `php artisan test` seluruhnya lolos | ringkasan jumlah tes | ☐ |
| 1.4 | `npm run build` sukses | `public/build/manifest.json` ada | ☐ |
| 1.5 | `vue-tsc --noEmit` 0 error | keluaran terminal | ☐ |
| 1.6 | `composer audit` tanpa advisory | keluaran terminal | ☐ |
| 1.7 | `npm audit --omit=dev` 0 kerentanan | keluaran terminal | ☐ |
| 1.8 | Tidak ada `public/hot` | `Test-Path public/hot` → `False` | ☐ |
| 1.9 | Tidak ada `.env` di dalam repo | `git ls-files | Select-String '\.env$'` kosong | ☐ |

---

## 2. Infrastruktur

| # | Butir | Bukti yang harus ada | Status |
|---|---|---|---|
| 2.1 | VPS dengan PHP ≥ 8.3, ekstensi `pdo_pgsql`, `redis`, `gd`/`imagick`, `intl`, `zip` | `php -m` | ☐ |
| 2.2 | PostgreSQL 15+ berjalan, hanya listen di localhost | `ss -lntp` | ☐ |
| 2.3 | Redis berjalan, `requirepass` diset, hanya localhost | `redis-cli ping` | ☐ |
| 2.4 | Nginx memakai `deploy/nginx.conf` | `nginx -t` sukses | ☐ |
| 2.5 | PHP-FPM pool memakai `deploy/php-fpm.conf` | `php-fpm -t` sukses | ☐ |
| 2.6 | Supervisor menjalankan worker antrean | `supervisorctl status` → `RUNNING` | ☐ |
| 2.7 | Cron `* * * * * php artisan schedule:run` terpasang | `crontab -l` | ☐ |
| 2.8 | Zona waktu server `Asia/Jakarta` | `timedatectl` | ☐ |
| 2.9 | Waktu server tersinkron NTP | `timedatectl` → `synchronized: yes` | ☐ |
| 2.10 | `storage/` dan `bootstrap/cache/` dapat ditulis proses web | `ls -ld` | ☐ |
| 2.11 | Root web menunjuk `public/`, bukan akar proyek | `nginx -T | Select-String root` | ☐ |

> **Mengapa waktu server penting.** Token tiket dan batas 15 menit pembayaran
> bergantung pada jam server. Jam yang meleset membuat tiket sah ditolak atau
> booking kedaluwarsa terlalu cepat.

---

## 3. Konfigurasi aplikasi

| # | Butir | Nilai yang benar | Status |
|---|---|---|---|
| 3.1 | `APP_ENV` | `production` | ☐ |
| 3.2 | `APP_DEBUG` | `false` | ☐ |
| 3.3 | `APP_KEY` | terisi, dibuat dengan `php artisan key:generate` | ☐ |
| 3.4 | `APP_URL` | `https://` domain produksi | ☐ |
| 3.5 | `DB_CONNECTION` | `pgsql` | ☐ |
| 3.6 | `QUEUE_CONNECTION` | `redis` | ☐ |
| 3.7 | `CACHE_STORE` | `redis` | ☐ |
| 3.8 | `SESSION_DRIVER` | `database` atau `redis` | ☐ |
| 3.9 | `SESSION_SECURE_COOKIE` | `true` | ☐ |
| 3.10 | `LOG_LEVEL` | `warning` atau `error` | ☐ |
| 3.11 | `MAIL_MAILER` | SMTP nyata, bukan `log` | ☐ |
| 3.12 | `MIDTRANS_IS_PRODUCTION` | `true` | ☐ |
| 3.13 | `TICKET_SIGNING_KEYS` | kunci acak ≥ 32 byte, bukan nilai contoh | ☐ |
| 3.14 | Variabel `MONITORING_*` | sesuai ambang yang disepakati | ☐ |
| 3.15 | `php artisan config:cache` dijalankan **setelah** `.env` final | cache dibuat ulang | ☐ |

> **Jebakan umum.** `config:cache` yang dijalankan sebelum `.env` diubah akan
> mengunci nilai lama. Setiap perubahan `.env` di produksi wajib diikuti
> `php artisan config:cache` ulang.

---

## 4. Basis data

| # | Butir | Bukti | Status |
|---|---|---|---|
| 4.1 | `php artisan migrate --force` sukses | keluaran terminal | ☐ |
| 4.2 | Seeder peran dan izin dijalankan | `roles` terisi | ☐ |
| 4.3 | Akun admin platform dibuat, password kuat, bukan `password123` | login berhasil | ☐ |
| 4.4 | Tidak ada data demo di produksi | `museums` hanya museum pilot | ☐ |
| 4.5 | Indeks dan constraint ada | `php artisan db:show` | ☐ |
| 4.6 | Koneksi memakai user khusus aplikasi, bukan `postgres` | `\du` di psql | ☐ |
| 4.7 | User aplikasi tidak punya hak `SUPERUSER`/`CREATEDB` | `\du` | ☐ |

---

## 5. TLS dan domain

| # | Butir | Bukti | Status |
|---|---|---|---|
| 5.1 | Sertifikat TLS sah dan tidak kedaluwarsa < 30 hari | `openssl s_client` | ☐ |
| 5.2 | HTTP dialihkan permanen ke HTTPS | `curl -I http://…` → 301 | ☐ |
| 5.3 | Rantai sertifikat lengkap | uji di SSL Labs ≥ A | ☐ |
| 5.4 | Perpanjangan otomatis aktif | `systemctl list-timers` | ☐ |
| 5.5 | HSTS muncul di respons HTTPS | `curl -I https://…` | ☐ |
| 5.6 | Domain tanpa `www` dan dengan `www` konsisten | `curl -I` keduanya | ☐ |

---

## 6. Pembayaran

| # | Butir | Bukti | Status |
|---|---|---|---|
| 6.1 | Akun Midtrans produksi aktif | dashboard menampilkan mode produksi | ☐ |
| 6.2 | `MIDTRANS_SERVER_KEY` produksi terpasang, tidak pernah masuk repo | cek `.env` di server | ☐ |
| 6.3 | `MIDTRANS_CLIENT_KEY` produksi terpasang | halaman bayar memuat Snap | ☐ |
| 6.4 | URL notifikasi di dashboard Midtrans = `https://domain/webhooks/midtrans` | tangkapan dashboard | ☐ |
| 6.5 | Notifikasi uji dari dashboard diterima (200) | log `payment.webhook_applied` | ☐ |
| 6.6 | **Pembayaran produksi bernilai kecil berhasil end-to-end** (AC-4) | booking → tiket → scan `valid` | ☐ |
| 6.7 | Tiket hasil pembayaran uji dapat dipindai di gate | riwayat scan | ☐ |
| 6.8 | Refund transaksi uji diproses | status `refunded`, tiket `voided` | ☐ |
| 6.9 | `payments:reconcile` berjalan tanpa error di produksi | log terjadwal | ☐ |

> **Catatan AC-4.** Butir 6.6–6.8 memerlukan akun Midtrans produksi nyata dan
> tidak dapat dibuktikan di lingkungan pengembangan. Lihat
> `ROADMAP.md` Fase 9 bagian "Yang belum dapat dibuktikan".
>
> **Kunci Midtrans sejak 28 Oktober 2026 ada empat, bukan dua.** `MIDTRANS_SERVER_KEY`
> harus diisi **Server Key** dari tabel `Server Keys` (dibuat lewat *Generate
> server key*), bukan **Signature Secret Key**. Memakai Signature Secret Key
> menghasilkan `HTTP 401` pada setiap pembuatan transaksi. Signature Secret Key
> masuk ke `MIDTRANS_SIGNATURE_KEY`. Rincian dan cara mendiagnosisnya ada di
> `MIDTRANS_SETUP.md`.

---

## 7. Keamanan

| # | Butir | Bukti | Status |
|---|---|---|---|
| 7.1 | `SECURITY_REVIEW.md` tanpa temuan kritis/tinggi terbuka (AC-2) | dokumen | ☐ |
| 7.2 | Header keamanan muncul di produksi | `curl -I` | ☐ |
| 7.3 | Rate limit login aktif | 6 percobaan gagal → 429 | ☐ |
| 7.4 | `APP_DEBUG=false` tidak membocorkan stack trace | akses URL salah | ☐ |
| 7.5 | `.env` tidak dapat diakses lewat web | `curl https://domain/.env` → 404 | ☐ |
| 7.6 | `storage/` tidak dapat dieksekusi | unggah `.php`, akses → 403/404 | ☐ |
| 7.7 | Direktori `.git` tidak terekspos | `curl https://domain/.git/config` → 404 | ☐ |
| 7.8 | API key Google Maps dibatasi per referrer | Google Cloud Console | ☐ |
| 7.9 | Akun demo (`user@test.com`) tidak ada di produksi | query `users` | ☐ |

---

## 8. Backup dan pemulihan

| # | Butir | Bukti | Status |
|---|---|---|---|
| 8.1 | Backup otomatis harian terjadwal | `crontab -l` | ☐ |
| 8.2 | Backup tersimpan di luar server | bucket S3 / host lain | ☐ |
| 8.3 | Backup terenkripsi | berkas berakhiran `.age`/`.gpg` | ☐ |
| 8.4 | **Uji restore berhasil pada data produksi** (AC-3) | `php artisan db:verify-restore` | ☐ |
| 8.5 | Waktu pemulihan terukur | catatan durasi | ☐ |
| 8.6 | Kunci enkripsi disimpan terpisah dari server | dipegang penanggung jawab | ☐ |
| 8.7 | Retensi backup disepakati (mis. 30 hari) | kebijakan tertulis | ☐ |

> **Uji restore wajib pada data produksi, bukan hanya data demo.** Backup yang
> belum pernah dipulihkan belum terbukti sebagai backup.

---

## 9. Monitoring dan alarm

| # | Butir | Bukti | Status |
|---|---|---|---|
| 9.1 | `php artisan ops:health --json` berjalan di produksi | keluaran JSON | ☐ |
| 9.2 | Monitor eksternal menerima ping `ops:health` tiap 5 menit | konfigurasi monitor | ☐ |
| 9.3 | Alarm gagal job terbukti berbunyi (AC-5) | tangkapan notifikasi | ☐ |
| 9.4 | Alarm antrean menumpuk terbukti berbunyi (AC-5) | tangkapan notifikasi | ☐ |
| 9.5 | Alarm webhook tertunda terbukti berbunyi (AC-5) | tangkapan notifikasi | ☐ |
| 9.6 | Alarm dikirim ke saluran yang dibaca orang (email/WhatsApp/Telegram) | uji kirim | ☐ |
| 9.7 | Log aplikasi berotasi | `logrotate` terpasang | ☐ |
| 9.8 | Kontak penerima alarm tercatat | daftar kontak | ☐ |

> **AC-5 menuntut alarm "terbukti aktif", bukan sekadar terpasang.** Caranya:
> picu kegagalan buatan (mis. hentikan worker sampai antrean menumpuk), lalu
> tunjukkan notifikasi yang benar-benar diterima.
>
> Pemasangan: isi `MONITORING_PING_URL` dengan ping URL Healthchecks.io;
> `ops:health` sendiri yang mengirim ping (`/fail` saat ada alarm). Langkah
> lengkap dan cara mengumpulkan ketiga bukti ada di `MONITORING_SETUP.md`.

---

## 10. Operasional

| # | Butir | Bukti | Status |
|---|---|---|---|
| 10.1 | `SOP_GATE.md` dibagikan dan dibaca petugas | tanda terima | ☐ |
| 10.2 | `RUNBOOK_PAYMENT_ANOMALY.md` dipegang admin | tanda terima | ☐ |
| 10.3 | `RUNBOOK.md` dipegang penanggung jawab teknis | tanda terima | ☐ |
| 10.4 | Petugas gate terlatih memakai scanner | sesi latihan | ☐ |
| 10.5 | Perangkat gate cadangan tersedia | perangkat terdaftar | ☐ |
| 10.6 | Prosedur kertas darurat tersedia di pintu | salinan cetak | ☐ |
| 10.7 | Jalur eskalasi dan nomor kontak diisi | daftar kontak | ☐ |
| 10.8 | Akun admin dan petugas museum pilot dibuat | login berhasil | ☐ |

---

## 11. Kepatuhan dan konten

| # | Butir | Bukti | Status |
|---|---|---|---|
| 11.1 | Kebijakan Privasi tampil dan isinya sesuai praktik nyata | `/bantuan` | ☐ |
| 11.2 | Syarat dan Ketentuan tampil | `/bantuan` | ☐ |
| 11.3 | Kebijakan privasi menyebut Midtrans sebagai pemroses pembayaran | isi halaman | ☐ |
| 11.4 | Kontak pengaduan data pribadi tercantum | isi halaman | ☐ |
| 11.5 | Kebijakan pembatalan/refund museum pilot tertulis | dokumen museum | ☐ |
| 11.6 | Email transaksional memuat identitas pengirim yang sah | uji kirim | ☐ |

---

## 12. UAT

| # | Butir | Bukti | Status |
|---|---|---|---|
| 12.1 | Skenario UAT dijalankan di lingkungan produksi | `UAT_PLAN.md` terisi | ☐ |
| 12.2 | Seluruh skenario berstatus lulus atau diterima dengan catatan | tabel hasil | ☐ |
| 12.3 | **Persetujuan tertulis perwakilan museum pilot** (AC-6) | tanda tangan | ☐ |
| 12.4 | Daftar masalah sisa disepakati dan dijadwalkan | tabel | ☐ |

---

## 13. Hari-H

| # | Tindakan | Waktu |
|---|---|---|
| 13.1 | Bekukan perubahan kode (`git tag v1.0.0`) | H-1 |
| 13.2 | Backup terbaru sebelum migrasi | H-1 |
| 13.3 | `php artisan down --secret=…` selama deploy | H-0 |
| 13.4 | `git pull` + `composer install --no-dev --optimize-autoloader` | H-0 |
| 13.5 | `php artisan migrate --force` | H-0 |
| 13.6 | `php artisan config:cache route:cache view:cache` | H-0 |
| 13.7 | `php artisan queue:restart` | H-0 |
| 13.8 | `php artisan up` | H-0 |
| 13.9 | Uji asap: buka beranda, cari museum, login | H-0 |
| 13.10 | Uji asap: satu pembayaran kecil + scan | H-0 |
| 13.11 | Pantau `ops:health` dan log selama 2 jam pertama | H-0 |

---

## 14. Tanda tangan

| Peran | Nama | Tanggal | Tanda tangan |
|---|---|---|---|
| Penanggung jawab teknis | | | |
| Perwakilan museum pilot | | | |
| Admin platform | | | |

Go-live disetujui: ☐ Ya ☐ Belum — alasan: ______________________
