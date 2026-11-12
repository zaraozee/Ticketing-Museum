# SOP Petugas Gate

Prosedur standar untuk petugas pintu masuk museum yang memindai tiket
(FR-GATE-01…06). Ditulis untuk dibaca di depan pintu, bukan di meja kerja —
kalimat pendek, urutan jelas.

Rujukan: `SYSTEM_DESIGN.md` §11.3 (token QR), `ROADMAP.md` Fase 6 dan Fase 9.

---

## 1. Sebelum museum buka

| # | Tindakan | Hasil yang benar |
|---|---|---|
| 1 | Buka `/organizer/scanner` di perangkat gate, login dengan akun petugas | Halaman scanner tampil dengan nama museum |
| 2 | Izinkan akses kamera saat browser meminta | Kamera menampilkan gambar, bukan pesan error |
| 3 | Pastikan perangkat terhubung internet | Indikator koneksi hijau; bila tidak, lihat §6 |
| 4 | Pindai satu tiket percobaan bila tersedia | Hasil `valid` dan tiket kembali `active` setelah direset admin |
| 5 | Catat jam buka dan nama petugas | Tercatat di buku serah terima shift |

> **Penting.** Scanner **wajib** online. Verifikasi tiket dilakukan di server,
> bukan di perangkat, sehingga tiket tidak dapat dipalsukan dengan mengubah
> jam perangkat.

---

## 2. Alur scan normal

1. Minta pengunjung menunjukkan QR pada halaman **Tiket Saya**.
2. Arahkan kamera ke QR hingga terdeteksi.
3. Baca hasil di layar:

| Hasil | Arti | Tindakan |
|---|---|---|
| `valid` | Tiket sah, baru dipakai | Persilakan masuk. Layar menampilkan jumlah orang |
| `already_used` | Tiket sudah dipakai | **Tolak.** Tunjukkan waktu pemakaian pertama ke pengunjung |
| `wrong_museum` | Tiket milik museum lain | Tolak dengan sopan; arahkan ke museum yang benar |
| `voided` | Tiket dibatalkan (refund) | Tolak; arahkan ke layanan pelanggan |
| `expired` | Tanggal kunjungan sudah lewat | Tolak; arahkan ke layanan pelanggan |
| `invalid` | Token tidak dikenali | Minta pengunjung membuka ulang halaman tiket; bila tetap gagal, pakai kode manual (§3) |

4. Bila pengunjung membawa lebih dari satu tiket, pindai **satu per satu**.
   Sistem mencatat tiap tiket secara terpisah.

---

## 3. Kode manual (QR tidak terbaca)

Dipakai ketika kamera rusak, layar pengunjung retak, atau QR terlipat.

1. Minta pengunjung menyebutkan **kode tiket** (10 karakter, mis. `WR4PFS9Q7T`).
   Kode tertera di bawah QR pada halaman e-ticket.
2. Masukkan kode pada kolom **Kode manual**, tekan **Cari**.
3. Verifikasi **nama pemegang tiket** dan **tanggal kunjungan** pada layar
   sebelum menekan **Gunakan tiket**.
4. Bila pengunjung tidak dapat membuka halaman tiket sama sekali (baterai habis,
   perangkat hilang): minta **kode booking** atau **email pemesanan**, lalu
   cari lewat menu **Riwayat Scan** (`/organizer/scans`) atau hubungi admin.

> **Batas.** Pencarian kode manual dibatasi 10 percobaan per menit per petugas.
> Bila muncul pesan "terlalu banyak percobaan", tunggu satu menit — jangan
> meminta petugas lain mencoba berulang, karena itu tidak membantu dan
> menandakan kode yang dimasukkan memang salah.

---

## 4. Penanganan sengketa di pintu

| Situasi | Yang **boleh** dilakukan | Yang **tidak boleh** |
|---|---|---|
| Pengunjung mengaku sudah bayar tetapi tiket tidak muncul | Minta bukti pembayaran; cek di menu Riwayat Scan; hubungi admin | Memasukkan pengunjung tanpa tiket sah |
| Tiket berstatus `already_used` tetapi pengunjung baru datang | Catat waktu scan pertama, tunjukkan ke pengunjung, hubungi admin | Memindai ulang untuk "mereset" tiket |
| Pengunjung salah tanggal | Arahkan ke layanan pelanggan | Mengubah tanggal sendiri |
| Nama pada tiket berbeda dengan KTP | Ikuti kebijakan museum; bila ragu, hubungi admin | Menolak tanpa dasar atau meloloskan tanpa pemeriksaan |

**Prinsip.** Petugas gate tidak pernah mengubah status tiket secara manual.
Semua koreksi dilakukan admin lewat log audit agar dapat ditelusuri.

---

## 5. Serah terima shift

1. Catat jumlah tiket yang dipindai pada shift tersebut (menu **Riwayat Scan**).
2. Laporkan setiap kejadian luar biasa: tiket ganda, sengketa, kamera rusak.
3. Logout dari perangkat gate.
4. Kembalikan perangkat ke tempat pengisian daya.

---

## 6. Bila internet mati

| Kondisi | Tindakan |
|---|---|
| Internet mati total | Scanner tidak dapat memverifikasi. Catat nama pemegang tiket, kode tiket, dan waktu; verifikasi manual setelah koneksi pulih |
| Internet lambat | Tetap pakai scanner; tunggu hasil. Jangan menekan tombol berulang |
| Perangkat gate rusak | Gunakan perangkat cadangan; bila tidak ada, pakai prosedur kertas sementara |

> **Mengapa tidak ada mode scan offline.** Verifikasi tiket harus dilakukan di
> server agar tiket yang sudah dipakai atau dibatalkan tidak dapat lolos.
> Mode offline akan membuka celah tiket ganda. Karena itu, petugas **selalu**
> menyiapkan perangkat cadangan dan rencana kertas.

**Prosedur kertas (darurat).**

1. Siapkan lembar catatan: jam, nama, kode tiket, jumlah orang.
2. Periksa kode tiket 10 karakter dan tanggal kunjungan secara visual.
3. Setelah koneksi pulih, admin memasukkan catatan tersebut untuk audit.
4. Tiket yang tercatat di kertas **tidak** dipindai ulang; admin menandainya
   lewat panel agar tidak dihitung dua kali.

---

## 7. Kontak

| Keperluan | Hubungi |
|---|---|
| Masalah akun atau perangkat gate | Admin platform |
| Sengketa pembayaran | Admin platform (antrean anomali) |
| Kebijakan museum | Pengelola museum |

Nomor kontak diisi saat go-live (`GO_LIVE_CHECKLIST.md` §9).
