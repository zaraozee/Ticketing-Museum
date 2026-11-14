# Runbook Operasional MusenTix

Panduan operasional untuk menjalankan, memantau, dan memulihkan aplikasi MusenTix di
produksi. Dokumen ini melengkapi `SYSTEM_DESIGN.md` §8.5 (logging, monitoring,
backup) dan `ROADMAP.md` Fase 9.

Rujukan terkait: `DEPLOYMENT.md` (pemasangan awal), `PWA_INSTALL_GUIDE.md`
(pemasangan PWA), `SECURITY_REVIEW.md` (hasil tinjauan keamanan).

---

## 1. Ringkasan arsitektur produksi

| Komponen | Peran | Catatan |
|---|---|---|
| Nginx | TLS termination, serve `public/`, teruskan PHP ke PHP-FPM | Konfigurasi di `deploy/nginx.conf` |
| PHP-FPM | Menjalankan Laravel | Pool di `deploy/php-fpm.conf` |
| PostgreSQL 16 | Basis data utama | Wajib untuk locking nyata (BR-05) |
| Redis | Cache, session, queue, rate limit | Jangan pakai `database` di produksi |
| Supervisor | Menjaga `queue:work` tetap hidup | `deploy/supervisor.conf` |
| cron | Menjalankan `schedule:run` tiap menit | Lihat §3 |
| Object storage | Foto museum | `MUSEUM_MEDIA_DISK=s3` |

---

## 2. Pemeriksaan harian

Jalankan atau pastikan monitor menampilkan:

```bash
php artisan ops:health
```

Exit code `0` berarti sehat; `≠ 0` berarti ada alarm. Keluaran JSON untuk
monitor otomatis:

```bash
php artisan ops:health --json
```

Yang diperiksa:

| Pemeriksaan | Arti bila gagal | Tindakan |
|---|---|---|
| Antrean gagal | `failed_jobs` melebihi ambang | Lihat §5.1 |
| Antrean menunggu | Job tertua terlalu lama → worker mati | Lihat §5.2 |
| Pembayaran menggantung | `payments.pending` melewati batas bayar | Lihat §5.3 |
| Webhook tertunda | Notifikasi sah belum diproses | Lihat §5.4 |

Ambang batas diatur lewat `config/ticketing.php` (`monitoring`) dan dapat
ditimpa lewat environment (`MONITORING_*`).

---

## 3. Penjadwalan (cron)

Satu baris cron sudah cukup karena Laravel menjadwalkan seluruh command:

```cron
* * * * * cd /var/www/musentix && php artisan schedule:run >> /dev/null 2>&1
```

Command terjadwal:

| Command | Jadwal | Fungsi |
|---|---|---|
| `slots:generate` | 01:00 | Buat slot kunjungan dari template (FR-SLT) |
| `bookings:expire` | tiap menit | Lepas kuota booking yang lewat batas bayar (BR-06) |
| `payments:reconcile` | tiap 5 menit | Cadangan bila webhook hilang (BR-12) |
| `tickets:expire` | 02:00 | Tandai tiket kedaluwarsa (FR-TIX-01) |
| `bookings:remind` | 17:00 | Pengingat kunjungan H-1 (FR-NTF-03) |
| `ops:health` | tiap 5 menit | Alarm operasional (NFR-13) |

Verifikasi jadwal aktif:

```bash
php artisan schedule:list
```

> **Penting.** `schedule:run` hanya berjalan bila cron aktif. Bila cron mati,
> kuota booking tidak pernah dilepas dan slot akan terlihat penuh padahal
> tidak. Pasang alarm pada `ops:health` untuk mendeteksi ini.

---

## 4. Backup dan restore

### 4.1 Backup harian (PostgreSQL)

Jalur utama produksi memakai `pg_dump`:

```bash
pg_dump --format=custom --no-owner --file=/var/backups/musentix-$(date +%F).dump "$DB_DATABASE"
```

Simpan salinan terenkripsi di lokasi berbeda (NFR-14: RPO ≤ 1 jam untuk DB):

```bash
age -r "$BACKUP_PUBLIC_KEY" -o /var/backups/musentix-$(date +%F).dump.age /var/backups/musentix-$(date +%F).dump
aws s3 cp /var/backups/musentix-$(date +%F).dump.age "s3://$BUCKET/backups/"
```

### 4.2 Backup portabel (semua driver)

Command bawaan aplikasi menulis satu berkas JSON berisi seluruh tabel aplikasi.
Berguna untuk lingkungan tanpa `pg_dump` dan sebagai jalur verifikasi:

```bash
php artisan db:backup --path=/var/backups/musentix
```

Tabel framework (`migrations`, `cache`, `sessions`, `jobs`, `failed_jobs`)
sengaja dilewati karena dibangun ulang oleh `migrate` dan `queue:restart`.

### 4.3 Restore

**Latihan wajib (AC-3).** Command ini membuat backup, memulihkannya ke database
sementara yang baru dimigrasikan, lalu membandingkan sidik jari data:

```bash
php artisan db:verify-restore
```

Keluaran yang diharapkan berakhir dengan:

```
Backup dan restore terverifikasi: data identik sebelum dan sesudah.
```

Latihan berjalan di database sementara, sehingga **tidak menyentuh** database
sumber. Aman dijalankan di lokal maupun staging.

**Restore sungguhan:**

```bash
# PostgreSQL (jalur utama)
pg_restore --clean --if-exists --no-owner --dbname="$DB_DATABASE" /var/backups/musentix-2026-11-12.dump

# Dari berkas JSON aplikasi
php artisan db:restore /var/backups/musentix/musentix-20261112-033517.json --force
```

`db:restore` **mengganti** seluruh isi tabel, bukan menggabungkan. Di produksi
wajib memakai `--force`; tanpa itu command menolak berjalan.

**Urutan pemulihan setelah restore:**

1. `php artisan migrate --force` — terapkan migrasi yang lebih baru dari backup.
2. `php artisan queue:restart` — worker memuat kode dan koneksi baru.
3. `php artisan ops:health` — pastikan sistem sehat.
4. Uji satu tiket: buka halaman e-ticket dan pindai di scanner.

### 4.4 Jadwal uji restore

| Frekuensi | Tindakan |
|---|---|
| Harian | Backup otomatis + verifikasi ukuran berkas |
| Mingguan | `php artisan db:verify-restore` di staging |
| Kuartalan | Restore penuh ke staging dan uji alur booking → tiket → scan |

---

## 5. Prosedur gangguan

### 5.1 Antrean gagal menumpuk

```bash
php artisan queue:failed          # daftar job gagal
php artisan queue:retry all       # coba ulang seluruhnya
php artisan queue:flush           # hapus (hanya bila sudah dipahami)
```

Periksa `storage/logs/laravel.log` pada `payment.*` dan `mail.*`. Bila
penyebabnya SMTP, lihat `email_logs` (`status = 'failed'`).

### 5.2 Worker antrean mati

```bash
supervisorctl status musentix-worker:*
supervisorctl restart musentix-worker:*
```

Penyebab umum: `queue:work` berhenti karena memori (`--max-time`), atau
koneksi Redis terputus. Supervisor mengembalikan proses secara otomatis; bila
berulang, periksa memori server.

### 5.3 Pembayaran menggantung

1. Periksa `payments` dengan `status = 'pending'` dan `created_at` lama.
2. Jalankan rekonsiliasi manual:
   ```bash
   php artisan payments:reconcile
   ```
3. Bila Midtrans melaporkan `settlement` tetapi status lokal belum berubah,
   periksa `payment_events` — notifikasi mungkin ditolak karena signature.
4. Bila `needs_review = true`, tangani lewat antrean anomali admin.

> **Payment yang ditutup karena booking berakhir.** Saat booking
> `expired`/`cancelled`, percobaan pembayaran yang masih aktif ikut ditutup
> (`expired`/`cancelled`) supaya `ops:health` tidak melaporkan pembayaran
> menggantung selamanya. Karena itu `payments:reconcile` **juga** memeriksa
> payment berstatus `expired`/`cancelled` selama masa tenggang
> `--grace-hours` (default 2 jam, hanya untuk booking yang berakhir).
> Tanpa masa tenggang itu, pembayaran telat (BR-23) yang notifikasinya juga
> hilang tidak akan pernah pulih: uang masuk, tiket tidak terbit, dan tidak
> ada yang menanganinya.
>
> ```bash
> php artisan payments:reconcile --grace-hours=6
> ```

### 5.4 Webhook tertunda

```bash
tail -f storage/logs/laravel.log | grep payment.webhook
```

| Gejala | Kemungkinan penyebab |
|---|---|
| `payment.webhook_rejected_signature` | `MIDTRANS_SERVER_KEY` salah atau berbeda antara lingkungan |
| Notifikasi tidak pernah masuk | URL webhook di dashboard Midtrans salah, atau Nginx memblokir `POST /webhooks/midtrans` |
| `payment.status_verification_failed` | API Midtrans tidak dapat dijangkau dari server |

Pastikan `POST /webhooks/midtrans` **tidak** terkena CSRF dan tidak diarahkan
ke halaman maintenance.

### 5.5 Situs down

1. `curl -sS -o /dev/null -w '%{http_code}\n' https://<domain>/up` — endpoint
   kesehatan Laravel.
2. `supervisorctl status` dan `systemctl status php8.4-fpm nginx`.
3. Periksa `storage/logs/laravel.log` dan log Nginx.
4. Bila karena deploy: `php artisan down` sebelumnya memungkinkan rollback cepat.

---

## 6. Prosedur go-live

Checklist lengkap ada di `GO_LIVE_CHECKLIST.md`. Ringkasnya:

1. `.env` produksi terisi (lihat `.env.example`), `APP_DEBUG=false`.
2. `php artisan migrate --force`, `config:cache`, `route:cache`, `view:cache`.
3. `php artisan pwa:icons` bila ikon belum ada; `npm run build`.
4. cron dan Supervisor aktif; `php artisan schedule:list` benar.
5. `php artisan ops:health` hijau.
6. `php artisan db:verify-restore` hijau di staging.
7. Uji pembayaran bernilai kecil di Midtrans produksi (AC-4).
8. Monitor uptime dan alarm terpasang.

---

## 7. Kontak dan eskalasi

| Peran | Tanggung jawab |
|---|---|
| On-call engineer | Menjalankan runbook ini, memulihkan layanan |
| Pengelola museum pilot | Melaporkan gangguan operasional gate |
| Admin platform | Menangani antrean anomali pembayaran dan refund |

Catat setiap insiden (waktu, gejala, tindakan, akar masalah) agar runbook ini
terus membaik.
