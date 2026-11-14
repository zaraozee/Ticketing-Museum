# Deployment MusenTix ke VPS

Panduan pemasangan produksi dari server kosong. Untuk operasi sehari-hari
(monitoring, backup, prosedur gangguan) lihat `RUNBOOK.md`.

Rujukan: `SYSTEM_DESIGN.md` §8, `ROADMAP.md` Fase 9.

---

## 1. Prasyarat server

| Komponen | Versi minimum | Catatan |
|---|---|---|
| Ubuntu | 24.04 LTS | Distribusi lain dapat dipakai; sesuaikan nama paket |
| PHP | 8.4 | Ekstensi: `pdo_pgsql`, `redis`, `mbstring`, `xml`, `curl`, `zip`, `gd`, `intl` |
| PostgreSQL | 16 | Wajib untuk locking nyata (BR-05) |
| Redis | 7 | Cache, session, queue, rate limit |
| Nginx | 1.24 | |
| Node.js | 20 LTS | Hanya untuk build aset, tidak perlu di server produksi |
| Composer | 2.7 | |

> **Mengapa PostgreSQL, bukan SQLite.** Reservasi kuota memakai
> `SELECT ... FOR UPDATE` (BR-05). SQLite mengunci seluruh database sehingga
> perilaku konkurensinya berbeda, dan tes konkurensi Fase 4 membuktikan
> mekanisme ini pada MySQL/PostgreSQL.

---

## 2. Paket sistem

```bash
sudo apt update
sudo apt install -y nginx postgresql redis-server supervisor \
  php8.4-fpm php8.4-cli php8.4-pgsql php8.4-redis php8.4-mbstring \
  php8.4-xml php8.4-curl php8.4-zip php8.4-gd php8.4-intl unzip git certbot python3-certbot-nginx
```

---

## 3. Basis data

```bash
sudo -u postgres psql <<'SQL'
CREATE USER jejak WITH PASSWORD 'ganti-dengan-kata-sandi-kuat';
CREATE DATABASE jejak OWNER jejak;
SQL
```

---

## 4. Kode dan dependensi

```bash
sudo mkdir -p /var/www/jejak && sudo chown "$USER":www-data /var/www/jejak
git clone <repo> /var/www/jejak
cd /var/www/jejak

composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build
```

Salin konfigurasi dan isi nilainya:

```bash
cp .env.example .env
php artisan key:generate
```

Nilai yang **wajib** benar di produksi:

| Variabel | Nilai |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://jejak.example.com` |
| `DB_CONNECTION` | `pgsql` |
| `QUEUE_CONNECTION`, `CACHE_STORE`, `SESSION_DRIVER` | `redis` |
| `TICKET_SIGNING_KEYS` | JSON `{"MT1":"<32 byte acak base64>"}` |
| `MIDTRANS_SERVER_KEY`, `MIDTRANS_CLIENT_KEY` | Kunci **produksi** |
| `MIDTRANS_IS_PRODUCTION` | `true` |
| `MAIL_MAILER` | `smtp` (atau API provider transaksional) |

Buat kunci tiket:

```bash
openssl rand -base64 32
```

---

## 5. Migrasi dan cache

```bash
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
```

> **Jangan** menjalankan `migrate:fresh` atau `db:seed` penuh di produksi:
> seeder demo membuat akun contoh dengan kata sandi yang diketahui.

---

## 6. Nginx, PHP-FPM, Supervisor

```bash
sudo cp deploy/nginx.conf /etc/nginx/sites-available/jejak
sudo ln -s /etc/nginx/sites-available/jejak /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default

sudo cp deploy/php-fpm.conf /etc/php/8.4/fpm/pool.d/jejak.conf
sudo cp deploy/supervisor.conf /etc/supervisor/conf.d/jejak-worker.conf

sudo mkdir -p /var/log/jejak && sudo chown www-data:www-data /var/log/jejak

sudo nginx -t && sudo systemctl reload nginx
sudo systemctl reload php8.4-fpm
sudo supervisorctl reread && sudo supervisorctl update
```

Izin berkas:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

---

## 7. TLS

```bash
sudo certbot --nginx -d jejak.example.com
```

Verifikasi pembaruan otomatis:

```bash
sudo certbot renew --dry-run
```

---

## 8. cron penjadwal

```bash
sudo crontab -u www-data -e
```

Isi:

```cron
* * * * * cd /var/www/jejak && php artisan schedule:run >> /dev/null 2>&1
```

Verifikasi:

```bash
sudo -u www-data php artisan schedule:list
```

---

## 9. Webhook Midtrans produksi

Di dashboard Midtrans (mode produksi), atur **Payment Notification URL**:

```
https://jejak.example.com/webhooks/midtrans
```

Pastikan:

- Endpoint dapat diakses dari internet tanpa autentikasi.
- Tidak terkena CSRF (sudah dikecualikan di `bootstrap/app.php`).
- `MIDTRANS_SERVER_KEY` di server **sama persis** dengan yang dipakai Midtrans;
  bila berbeda, semua notifikasi ditolak dengan 403
  (`payment.webhook_rejected_signature`).

---

## 10. Verifikasi pasca-deploy

```bash
# Kesehatan aplikasi
curl -sS -o /dev/null -w '%{http_code}\n' https://jejak.example.com/up

# Header keamanan
curl -sSI https://jejak.example.com/ | grep -Ei 'strict-transport|x-content-type|x-frame|referrer-policy'

# Service worker dan manifest
curl -sSI https://jejak.example.com/sw.js | grep -i 'service-worker-allowed'
curl -sS https://jejak.example.com/manifest.webmanifest | head -c 200

# Alarm operasional
php artisan ops:health

# Latihan restore
php artisan db:verify-restore
```

Lanjutkan dengan `GO_LIVE_CHECKLIST.md`.

---

## 11. Pembaruan aplikasi (deploy berikutnya)

```bash
cd /var/www/jejak
php artisan down --render="errors::503"
git pull
composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
php artisan up
```

`php artisan down` tidak memblokir webhook pembayaran; pastikan deploy selesai
sebelum ada pembayaran besar, atau gunakan jendela pemeliharaan.

> **Catatan PWA.** Setelah `npm run build`, service worker berubah hash dan
> pengguna akan menerima prompt pembaruan. Pembaruan bersifat prompt-only
> (AC-4 Fase 8): pengguna menentukan sendiri kapan memuat ulang, sehingga
> checkout yang sedang berjalan tidak terputus.
