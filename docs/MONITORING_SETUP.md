# Setup Monitoring Eksternal (Healthchecks.io)

Dokumen ini melengkapi AC-5 Fase 9: alarm antrean dan webhook harus **terbukti
aktif**, bukan hanya terpasang.

Referensi: <https://healthchecks.io/docs/>

---

## 1. Kenapa perlu monitor eksternal

`ops:health` hanya berguna bila ada yang memanggilnya dan ada yang memberi tahu
manusia ketika hasilnya buruk. Monitor eksternal menutup dua lubang yang tidak
bisa ditutup oleh pemeriksaan internal:

- **Server mati total.** Pemeriksaan yang berjalan di dalam server yang mati
  tidak akan pernah melapor.
- **Pekerjaan terjadwal berhenti.** Cron yang tidak jalan tidak menghasilkan
  error apa pun; ia hanya diam. Healthchecks.io menandai "diam" sebagai Down.

Pola yang dipakai: **cron menjalankan pemeriksaan, pemeriksaan mengirim ping.**
Healthchecks.io tidak dapat menjalankan perintah, hanya menerima HTTP.

---

## 2. Membuat check

1. Daftar di <https://healthchecks.io>, buat proyek.
2. **+ New Check**:
   - **Name**: `MusenTix ops:health`
   - **Period**: 5 minutes
   - **Grace Time**: 5 minutes
3. Salin **Ping URL**, bentuknya `https://hc-ping.com/<uuid>`.

Period 5 menit dengan grace 5 menit berarti alarm berbunyi paling lama 10 menit
setelah pemeriksaan berhenti — sesuai jadwal `ops:health` yang berjalan setiap
lima menit.

---

## 3. Memasang ping

Cukup satu variabel. `ops:health` sendiri yang mengirim ping.

```dotenv
MONITORING_PING_URL=https://hc-ping.com/<uuid>
```

Perilakunya:

| Kondisi | Yang dikirim |
|---|---|
| Semua pemeriksaan sehat | `POST https://hc-ping.com/<uuid>` |
| Ada alarm | `POST https://hc-ping.com/<uuid>/fail` |
| `MONITORING_PING_URL` kosong | Tidak ada ping |

Memakai `/fail` (bukan berhenti mengirim ping) membuat status langsung **Down**
saat ada masalah, bukan menunggu grace time habis. Perbedaannya adalah alarm
yang berbunyi dalam hitungan detik versus beberapa menit kemudian.

Body ping berisi ringkasan pemeriksaan, sehingga penyebab alarm terbaca langsung
di halaman detail check tanpa perlu masuk ke server:

```
[ok] Antrean gagal: 0 job gagal.
[ok] Antrean menunggu: Antrean kosong.
[fail] Pembayaran menggantung: 2 pembayaran pending lebih dari 30 menit.
[ok] Webhook tertunda: Tidak ada notifikasi tertunda.
```

Kegagalan mengirim ping **tidak** mengubah hasil pemeriksaan. Monitor yang tidak
terjangkau adalah masalah terpisah dari kesehatan aplikasi, dan mencampurnya
akan membuat alarm sulit dipercaya.

---

## 4. Jadwal cron di server

Satu baris sudah cukup karena ping dikirim oleh perintah Artisan sendiri:

```cron
* * * * * cd /var/www/musentix && php artisan schedule:run >> /dev/null 2>&1
```

`ops:health` sudah terdaftar pada `routes/console.php` dengan
`everyFiveMinutes()`. Tidak perlu skrip pembungkus.

> `deploy/healthcheck.sh` tetap tersedia sebagai alternatif bila Anda lebih suka
> memisahkan ping dari perintah Artisan — misalnya bila ingin ping juga dikirim
> ketika `php artisan` sendiri gagal dijalankan. Jangan memakai keduanya
> bersamaan; Anda akan mendapat dua ping per periode.

---

## 5. Membuktikan alarm benar-benar aktif (AC-5)

Ini bagian yang membedakan "terpasang" dari "terbukti". Tiga bukti yang perlu
dikumpulkan:

### Bukti 1 — alarm pembayaran menggantung

```bash
php artisan ops:health
```

Dengan pembayaran `pending` yang melewati `MONITORING_STUCK_PAYMENT_MINUTES`,
perintah keluar dengan kode `1`, dan Healthchecks.io menampilkan status **Down**
disertai body yang menyebut "Pembayaran menggantung".

### Bukti 2 — alarm antrean menumpuk

Hentikan worker antrean, lalu buat pekerjaan yang masuk antrean (mis. kirim
email konfirmasi). Setelah `MONITORING_QUEUE_AGE_MINUTES` terlampaui,
`ops:health` melaporkan "Antrean menunggu" gagal.

### Bukti 3 — alarm job gagal

```bash
php artisan queue:failed
```

Bila jumlah job gagal melewati `MONITORING_FAILED_JOBS_THRESHOLD`, alarm berbunyi.

Untuk tiap bukti, simpan tangkapan layar halaman detail check yang menampilkan
status, waktu, dan body permintaan. Inilah yang dilampirkan pada bukti AC-5.

### Hasil uji alarm (14 Nov 2026)

Keempat pemeriksaan dipicu satu per satu pada lingkungan pengembangan. Worker
antrean dihentikan lebih dulu agar job benar-benar menumpuk, lalu `ops:health`
dijalankan dan ping `/fail` terkirim ke Healthchecks.io.

| # | Alarm | Cara dipicu | Output `ops:health` | Exit |
|---|---|---|---|---|
| 1 | Antrean gagal | 6 baris `failed_jobs` (ambang 5) | `[FAIL] Antrean gagal: 6 job gagal (ambang 5).` | 1 |
| 2 | Antrean menunggu | job `BookingConfirmedMail` sungguhan, worker dimatikan, `created_at` dimundurkan 15 menit (ambang 10) | `[FAIL] Antrean menunggu: Job tertua berumur 15 menit (ambang 10).` | 1 |
| 3 | Webhook tertunda | `payment_event` signature sah, `processed_at` kosong, umur 15 menit (ambang 10) | `[FAIL] Webhook tertunda: 1 notifikasi sah belum diproses lebih dari 10 menit.` | 1 |
| 4 | Pembayaran menggantung | pembayaran `pending` melewati 30 menit | `[FAIL] Pembayaran menggantung: 3 pembayaran pending lebih dari 30 menit.` | 1 |

Setelah keempatnya terbukti, seluruh data uji dihapus dan `ops:health` kembali
`exit 0` dengan keempat pemeriksaan `[OK]` — membuktikan alarm tidak menyala
palsu saat kondisi normal.

### Bukti notifikasi sampai ke manusia (15 Nov 2026)

Uji alarm sebelumnya hanya membuktikan ping **terkirim**. Yang belum terbukti
adalah apakah ada **manusia** yang benar-benar diberi tahu — alarm yang tidak
pernah dibaca sama saja dengan tidak ada alarm. Kekurangan itu ditutup dengan
satu uji rantai penuh.

**Konfigurasi.** Integrasi **Email** pada project Healthchecks.io, nama
`MusenTix On-call`, terpasang pada check `MusenTix ops:health`
(UUID `e4101373-8628-42ae-a9ae-9ec8ef28c4ef`, period 5 menit, grace 5 menit).
Status integrasi: **ON**.

**Rantai yang dibuktikan:**

| Langkah | Bukti |
|---|---|
| 1. Kondisi normal | `ops:health` → keempat pemeriksaan `[OK]`, `exit 0`, check **UP** |
| 2. Alarm dipicu | 6 baris `failed_jobs` disisipkan (ambang 5, label `ac5-alarm-probe`) |
| 3. Perintah mendeteksi | `[FAIL] Antrean gagal: 6 job gagal (ambang 5).` → `exit 1` |
| 4. Ping kegagalan terkirim | `POST <ping-url>/fail` berhasil; tidak ada `ops.health_ping_failed` pada log |
| 5. Monitor menandai | Check `MusenTix ops:health` berubah **DOWN** (merah) di dashboard |
| 6. **Manusia diberi tahu** | Email "**… is DOWN**" masuk ke kotak masuk, body memuat `[FAIL] Antrean gagal: 6 job gagal (ambang 5).` |
| 7. Data uji dibersihkan | 6 baris probe dihapus; `failed_jobs` kembali 0 |
| 8. Alarm pulih sendiri | `ops:health` → `[OK]` semua, `exit 0`, check kembali **UP**, email pemulihan diterima |

Langkah 6 dan 8 adalah inti AC-5: notifikasi **sampai** ke manusia saat rusak,
dan **berhenti** saat pulih — bukan alarm yang menyala terus sehingga diabaikan.

**Batas yang jujur.** Alarm ini berbunyi dari **laptop pengembangan** lewat
scheduler lokal (`schedule:work`), bukan dari server produksi. Yang terbukti
adalah rantai `ops:health → Healthchecks.io → email → manusia`. Rantai
`server produksi → Healthchecks.io` baru terbukti saat aplikasi terpasang di
VPS — itu bagian dari AC-4/AC-6 yang masih terblokir, bukan cacat konfigurasi
monitor. Konfigurasi cron produksinya ada di `DEPLOYMENT.md`.

---

## 6. Notifikasi ke manusia

Healthchecks.io hanya menandai status; ia perlu diberi tahu cara menghubungi
manusia. Pada **Integrations**:

- **Email** — paling sederhana, cukup alamat surel penanggung jawab.
- **Slack / Discord / Telegram** — lebih cepat dilihat tim operasional.

Tanpa integrasi, check akan berubah merah tanpa ada yang menyadarinya. Ini
lubang yang mudah terlewat: alarm yang tidak pernah dibaca sama saja dengan
tidak ada alarm.

**Status saat ini: aktif.** Integrasi Email `MusenTix On-call` terpasang pada
check `MusenTix ops:health` dan rantainya sudah dibuktikan end-to-end
(§5 "Bukti notifikasi sampai ke manusia"). Saat check DOWN, email "… is DOWN"
masuk ke kotak masuk; saat pulih, email pemulihan menyusul.

Dua hal yang perlu diketahui saat mengoperasikannya:

- Notifikasi hanya dikirim **saat status berubah**, bukan berulang. Untuk
  alarm yang berlangsung lama, aktifkan **hourly/daily email reminders** di
  *Account Settings → Email Reports* agar tidak ada yang terlewat.
- Healthchecks.io menyarankan **dua kanal berbeda** (mis. Email + Slack) supaya
  kegagalan satu transport tidak membuat alarm hilang. Kanal kedua belum
  dipasang; Email adalah satu-satunya jalur saat ini.

---

## 7. Yang tidak dicakup monitor ini

- **Ketersediaan HTTP aplikasi.** Healthchecks.io memantau ping dari cron, bukan
  halaman aplikasi. Untuk itu gunakan monitor uptime terpisah yang memeriksa
  `https://<domain>/up`.
- **Kebenaran isi halaman.** Monitor hanya tahu perintah berjalan dan keluar
  dengan kode 0.
- **Pembayaran yang gagal di sisi Midtrans.** Itu domain `payments:reconcile`
  dan antrean anomali admin, bukan `ops:health`.
