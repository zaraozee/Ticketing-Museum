# Runbook Anomali Pembayaran

Prosedur penanganan pembayaran yang tidak selesai normal: nominal tidak cocok,
pembayaran datang setelah kedaluwarsa, webhook tidak pernah masuk, atau tiket
ganda. Ditulis untuk admin platform.

Rujukan: `SYSTEM_DESIGN.md` BR-10…BR-12 dan BR-23, `RUNBOOK.md` §5,
`THIRD_PARTY_INTEGRATIONS.md` §1.

---

## 1. Prinsip

1. **Status lokal adalah hasil verifikasi ke gateway**, bukan hasil percaya
   pada payload notifikasi. Jangan pernah mengubah status berdasarkan tangkapan
   layar pengunjung.
2. **Uang masuk bukan bukti tiket sah.** Tiket hanya terbit setelah booking
   `confirmed` dan kuota tersedia.
3. **Semua tindakan manual dicatat** di `activity_logs`. Jangan menghapus baris
   `payments` atau `payment_events` — itu bukti audit.
4. **Bila ragu, eskalasi.** Refund yang salah lebih mahal daripada satu tiket
   yang tertunda sehari.

---

## 2. Antrean anomali

Pembayaran masuk antrean ini bila `payments.needs_review = true`.

```bash
php artisan ops:health --json     # ringkasan kondisi
```

Daftar kasus: `/admin/anomalies`.

| `review_note` | Arti | Tindakan |
|---|---|---|
| Nominal gateway tidak cocok dengan tagihan | `gross_amount` ≠ tagihan (BR-11) | §3 |
| Pembayaran masuk setelah booking kedaluwarsa dan kuota tidak tersedia (BR-23) | Uang masuk, kuota habis | §4 |
| Status gateway tidak dikenal | Status baru dari Midtrans | §5 |

---

## 3. Nominal tidak cocok (BR-11)

**Gejala.** `payments.needs_review = true`, `review_note` menyebut nominal.

**Mengapa berbahaya.** Selisih nominal bisa berarti pengunjung membayar kurang,
atau ada percobaan manipulasi. Booking **sengaja tidak** dikonfirmasi.

**Langkah.**

1. Bandingkan `payments.gross_amount` dengan `last_response.gross_amount` dan
   dengan nominal di dashboard Midtrans.
2. Bila selisih karena **pembulatan** (mis. `50000` vs `50000.00`): konfirmasi
   bahwa selisihnya nol rupiah. Sistem sudah membandingkan sebagai rupiah
   bulat; bila tetap tertandai, catat sebagai bug dan eskalasi ke engineer.
3. Bila pengunjung **membayar kurang**: hubungi pengunjung, jelaskan, dan
   tawarkan pembayaran ulang atau refund. Jangan mengonfirmasi booking.
4. Bila pengunjung **membayar lebih**: konfirmasi booking, lalu proses refund
   selisih sesuai kebijakan museum.
5. Catat tindakan di `activity_logs` dengan alasan.

---

## 4. Pembayaran setelah kedaluwarsa (BR-23)

**Gejala.** `review_note` menyebut kuota tidak tersedia; booking berstatus
`expired`, payment berstatus `paid`.

**Langkah.**

1. Periksa apakah slot masih memiliki kuota (`reserved_count + sold_count <
   capacity`). Sistem sudah mencoba memesan ulang otomatis; bila gagal, berarti
   kuota memang habis.
2. Bila kuota tersedia kembali (mis. ada pembatalan): minta engineer mengonfirmasi
   booking tersebut lewat jalur admin. `payments:reconcile` **tidak** menangani
   kasus ini — command itu hanya memeriksa payment berstatus `created`/`pending`,
   sedangkan payment di kasus BR-23 sudah `paid`.
3. Bila kuota tetap habis: **refund penuh**. Tiket tidak diterbitkan.
4. Hubungi pengunjung lebih dulu sebelum refund agar tidak ada kejutan.
5. Bila pengunjung tetap ingin berkunjung: tawarkan tanggal lain, buat booking
   baru, dan refund transaksi lama.

---

## 5. Webhook tidak pernah masuk

**Gejala.** Booking tetap `pending_payment` padahal pengunjung mengaku sudah
membayar; `payments.status` masih `pending`.

**Langkah.**

1. Cek status sebenarnya di dashboard Midtrans (cari `order_id`).
2. Bila gateway menyatakan **settlement**, jalankan rekonsiliasi:
   ```bash
   php artisan payments:reconcile
   ```
   Command ini memverifikasi ke API gateway dan menerapkan status yang benar.
3. Bila rekonsiliasi tidak menemukan apa pun, periksa `payment_events`:
   notifikasi mungkin ditolak karena signature.
   ```bash
   grep 'payment.webhook_rejected_signature' storage/logs/laravel.log
   ```
   Penyebab paling umum: `MIDTRANS_SERVER_KEY` di server berbeda dengan yang
   dipakai Midtrans. Perbaiki kunci, lalu minta Midtrans mengirim ulang
   notifikasi (menu **Resend notification** di dashboard).
4. Bila URL notifikasi salah, perbaiki di dashboard Midtrans
   (`DEPLOYMENT.md` §9) lalu kirim ulang.

> **Jangan** mengonfirmasi booking manual hanya karena pengunjung mengirim
> tangkapan layar. Selalu verifikasi lewat dashboard gateway.

---

## 6. Tiket ganda

**Gejala.** Pengunjung menerima dua set tiket untuk satu pembayaran, atau dua
booking untuk satu transaksi.

**Langkah.**

1. Periksa `payments` untuk `booking_id` tersebut — seharusnya satu baris
   `paid`. Bila ada dua, eskalasi ke engineer: kemungkinan ada bug idempotensi.
2. Bila dua **booking** berbeda untuk satu pembayaran: identifikasi booking yang
   sah (yang memiliki `payment_id` cocok), lalu refund booking lainnya.
3. Tiket pada booking yang dibatalkan menjadi `voided` otomatis dan tidak dapat
   dipindai.
4. Catat kejadian; ini selalu dianggap bug sampai terbukti sebaliknya.

---

## 7. Refund

1. Refund dilakukan lewat dashboard Midtrans, **bukan** dari aplikasi (refund
   otomatis di luar MVP — lihat `ROADMAP.md` Fase 10).
2. Setelah refund diproses gateway, status lokal berubah menjadi `refunded`
   lewat notifikasi; tiket yang belum dipakai menjadi `voided`.
3. Bila notifikasi refund tidak masuk, jalankan `php artisan payments:reconcile`.
4. Catat nomor referensi refund di catatan internal.

---

## 8. Eskalasi

| Gejala | Eskalasi ke |
|---|---|
| Nominal cocok tetapi booking tidak terkonfirmasi | Engineer (kemungkinan bug) |
| Dua booking untuk satu pembayaran | Engineer (kemungkinan bug idempotensi) |
| Signature selalu ditolak setelah kunci diperbaiki | Engineer + dukungan Midtrans |
| Kuota negatif atau melebihi kapasitas | Engineer (pelanggaran constraint) |

Saat eskalasi, sertakan: `order_id`, `booking_code`, waktu kejadian, isi
`payments.last_response`, dan keluaran `php artisan ops:health --json`.

---

## 9. Pencegahan

| Kontrol | Di mana |
|---|---|
| Verifikasi signature + verifikasi ulang ke API | `WebhookProcessor` |
| Idempotensi `payment_events` | Unique index `event_key` |
| Rekonsiliasi tiap 5 menit | `routes/console.php` |
| Alarm pembayaran menggantung | `ops:health` (`stuck_payment_minutes`) |
| Alarm webhook tertunda | `ops:health` (`webhook_lag_minutes`) |
| Transisi status monoton | `PaymentStatus::canTransitionTo()` |
