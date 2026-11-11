# UML dan Diagram Alur Proses

Dokumen turunan dari `SYSTEM_DESIGN.md` (§11–§13). Seluruh diagram memakai **Mermaid**. Nama status, tabel, dan endpoint identik dengan dokumen lain.

> **Catatan keterbatasan Mermaid:** Mermaid tidak memiliki notasi *use case* UML bawaan. Use case digambar dengan `flowchart`: aktor = bentuk stadium `([ ])`, use case = bentuk bulat `( )`, relasi `include`/`extend` = panah putus-putus berlabel. Maknanya setara dengan use case UML; hanya bentuk visualnya yang berbeda. *Activity diagram* digambar dengan `flowchart` (keputusan = belah ketupat). Diagram state ada di `SYSTEM_DESIGN.md` §11.2.

---

## A. Use Case Diagram

### A.1 Pengunjung (User)

```mermaid
flowchart LR
    V(["Pengunjung / User"])
    MT(["Midtrans (sistem eksternal)"])

    subgraph SYS["Sistem Ticketing Museum"]
        direction TB
        U1("Registrasi dan login")
        U1b("Login dengan Google")
        U2("Mencari dan memfilter museum")
        U3("Melihat detail museum")
        U3b("Melihat lokasi dan petunjuk arah")
        U4("Memilih jenis tiket, tanggal, slot, jumlah")
        U5("Membuat booking")
        U5b("Memeriksa ketersediaan kuota")
        U6("Membayar via Midtrans")
        U7("Melihat e-ticket QR")
        U7b("Menyimpan tiket untuk offline")
        U8("Menambah jadwal ke Google Calendar")
        U9("Melihat riwayat booking")
        U10("Membatalkan booking pending")
        U11("Mengelola profil")
        U12("Menerima email dan notifikasi")
    end

    V --> U1
    V --> U2
    V --> U3
    V --> U4
    V --> U5
    V --> U6
    V --> U7
    V --> U9
    V --> U10
    V --> U11
    V --> U12
    U1b -.->|extend| U1
    U3b -.->|extend| U3
    U5 -.->|include| U5b
    U4 -.->|include| U5b
    U6 -.->|include| U5
    U7b -.->|extend| U7
    U8 -.->|extend| U7
    MT --> U6
```

### A.2 Gate Staff dan Organizer

```mermaid
flowchart LR
    GS(["Gate Staff"])
    OR(["Organizer"])

    subgraph SYS2["Sistem Ticketing Museum"]
        direction TB
        G1("Memindai QR tiket")
        G1b("Memvalidasi tiket ke backend")
        G1c("Menandai tiket digunakan")
        G2("Input manual kode tiket")
        G3("Melihat riwayat scan")

        O1("Mendaftar dan melengkapi profil organizer")
        O2("Mengelola profil dan galeri museum")
        O3("Mengelola jam operasional dan tanggal tutup")
        O4("Mengelola jenis dan harga tiket")
        O5("Mengelola template slot dan kuota")
        O6("Mengajukan museum untuk verifikasi")
        O7("Melihat booking dan pengunjung")
        O8("Mengelola staf gate")
        O9("Melihat dashboard dan laporan")
    end

    GS --> G1
    GS --> G2
    GS --> G3
    G1 -.->|include| G1b
    G2 -.->|include| G1b
    G1b -.->|include| G1c

    OR --> G1
    OR --> G3
    OR --> O1
    OR --> O2
    OR --> O3
    OR --> O4
    OR --> O5
    OR --> O6
    OR --> O7
    OR --> O8
    OR --> O9
```

### A.3 Admin Platform

```mermaid
flowchart LR
    AD(["Admin Platform"])

    subgraph SYS3["Sistem Ticketing Museum"]
        direction TB
        A1("Melihat dashboard platform")
        A2("Mengelola akun user")
        A3("Memverifikasi organizer")
        A4("Memverifikasi dan memoderasi museum")
        A5("Mengelola kategori dan fasilitas")
        A6("Memantau semua transaksi")
        A6b("Menangani antrean anomali pembayaran")
        A7("Melihat laporan lintas museum")
        A8("Mengelola pengaturan sistem")
        A9("Mengelola konten dan FAQ")
        A10("Melihat activity log")
    end

    AD --> A1
    AD --> A2
    AD --> A3
    AD --> A4
    AD --> A5
    AD --> A6
    AD --> A7
    AD --> A8
    AD --> A9
    AD --> A10
    A6b -.->|extend| A6
```

---

## B. Activity Diagram

### A1. Pemesanan dan pembayaran tiket

```mermaid
flowchart TD
    S(["Mulai"]) --> A["Pilih museum, jenis tiket, tanggal, slot, jumlah"]
    A --> B{"Sudah login?"}
    B -- "Belum" --> B1["Login atau registrasi"] --> C
    B -- "Sudah" --> C["Isi data pemesan"]
    C --> D["Kirim booking"]
    D --> E{"Validasi dan kuota tersedia?"}
    E -- "Tidak (kuota habis atau tanggal tutup)" --> E1["Tampilkan pesan 409, pilih ulang"] --> A
    E -- "Ya" --> F["Kunci slot, tahan kuota, buat booking pending_payment"]
    F --> G["Backend membuat transaksi Midtrans, kirim Snap token"]
    G --> H{"Transaksi gateway berhasil dibuat?"}
    H -- "Tidak" --> H1["Booking tetap pending, tampilkan Coba bayar lagi"] --> G
    H -- "Ya" --> I["Pengunjung membayar di Snap"]
    I --> J["Menunggu webhook dan verifikasi status"]
    J --> K{"Status pembayaran"}
    K -- "paid" --> L["Booking confirmed, kuota reserved menjadi sold"]
    L --> M["Terbitkan N tiket QR"]
    M --> N["Kirim email dan notifikasi"]
    N --> Z(["Selesai: tiket di My Tickets"])
    K -- "failed" --> K1{"Booking belum kedaluwarsa?"}
    K1 -- "Ya" --> I
    K1 -- "Tidak" --> X
    K -- "cancel atau expire" --> X["Booking cancelled atau expired, kuota dilepas"]
    X --> Y(["Selesai: tidak ada tiket"])
    J --> T{"Melewati expires_at tanpa pembayaran?"}
    T -- "Ya" --> X
```

### A2. Validasi dan scan QR di gerbang

```mermaid
flowchart TD
    S(["Mulai: petugas membuka Scanner"]) --> A["Pilih museum yang ditugaskan"]
    A --> B["Arahkan kamera ke QR atau input kode manual"]
    B --> C{"Ada koneksi ke server?"}
    C -- "Tidak" --> C1["Tampilkan Tidak ada koneksi, scan ditolak"] --> B
    C -- "Ya" --> D["Kirim payload dan client_scan_uuid"]
    D --> E{"Tanda tangan token valid dan tiket ditemukan?"}
    E -- "Tidak" --> R1["Hasil: INVALID"]
    E -- "Ya" --> F{"Museum tiket sama dengan museum gate?"}
    F -- "Tidak" --> R2["Hasil: WRONG_MUSEUM"]
    F -- "Ya" --> G{"Tiket void atau booking tidak confirmed?"}
    G -- "Ya" --> R3["Hasil: VOIDED"]
    G -- "Tidak" --> H{"Tiket sudah used?"}
    H -- "Ya" --> R4["Hasil: ALREADY_USED"]
    H -- "Tidak" --> I{"Tanggal dan jam dalam jendela berlaku?"}
    I -- "Terlalu awal" --> R5["Hasil: NOT_YET_VALID"]
    I -- "Sudah lewat" --> R6["Hasil: EXPIRED"]
    I -- "Ya" --> J["UPDATE tiket menjadi used WHERE status = active"]
    J --> K{"Satu baris terpengaruh?"}
    K -- "Tidak (kalah balapan)" --> R4
    K -- "Ya" --> R7["Hasil: VALID"]
    R1 --> L["Catat ticket_scans dan tampilkan hasil berwarna"]
    R2 --> L
    R3 --> L
    R4 --> L
    R5 --> L
    R6 --> L
    R7 --> L
    L --> M{"Hasil VALID?"}
    M -- "Ya" --> N(["Pengunjung masuk"])
    M -- "Tidak" --> O(["Petugas mengikuti SOP museum"])
```

### A3. Pengelolaan dan publikasi museum oleh organizer

```mermaid
flowchart TD
    S(["Mulai"]) --> A["Registrasi akun organizer dan lengkapi profil organisasi"]
    A --> B{"Organizer diverifikasi Admin?"}
    B -- "Rejected" --> B1["Perbaiki data dan ajukan ulang"] --> B
    B -- "Approved" --> C["Buat museum (status draft)"]
    C --> D["Isi profil, galeri, fasilitas, koordinat"]
    D --> E["Atur jam operasional dan tanggal tutup"]
    E --> F["Buat jenis tiket dan harga"]
    F --> G["Atur template slot dan kuota, sistem membuat visit_slots"]
    G --> H{"Syarat minimum lengkap?"}
    H -- "Belum" --> D
    H -- "Ya" --> I["Ajukan verifikasi: pending_review"]
    I --> J{"Keputusan Admin"}
    J -- "Reject dengan alasan" --> K["Status rejected, organizer memperbaiki"] --> D
    J -- "Approve" --> L["Status published, tampil publik"]
    L --> M["Pantau booking, scan, dan laporan"]
    M --> N{"Perlu ubah harga atau kuota?"}
    N -- "Ya" --> O{"Kuota di atas terjual dan ditahan?"}
    O -- "Tidak" --> O1["Ditolak dengan peringatan"] --> M
    O -- "Ya" --> P["Simpan perubahan, catat di activity_logs"] --> M
    N -- "Tidak" --> Z(["Selesai"])
```

---

## C. Sequence Diagram

### S1. User melakukan booking sampai e-ticket diterbitkan

```mermaid
sequenceDiagram
    autonumber
    actor U as Pengunjung
    participant FE as Frontend PWA
    participant BE as Backend Laravel
    participant DB as PostgreSQL
    participant MT as Midtrans
    participant Q as Queue Worker
    participant NS as Notification Service

    U->>FE: Pilih tiket, tanggal, slot, jumlah
    FE->>BE: GET availability dan slots
    BE->>DB: Baca visit_slots
    DB-->>BE: Sisa kuota
    BE-->>FE: Ketersediaan
    U->>FE: Isi data pemesan, tekan Pesan
    FE->>BE: POST /api/v1/bookings
    BE->>DB: BEGIN, SELECT slot FOR UPDATE
    alt Kuota cukup
        BE->>DB: UPDATE reserved_count, INSERT booking dan items, COMMIT
        BE-->>FE: 201 booking pending_payment + expires_at
        FE->>BE: POST /api/v1/bookings/{id}/payments
        BE->>MT: Buat transaksi Snap (order_id, gross_amount, expiry)
        MT-->>BE: Snap token
        BE->>DB: INSERT payments status created
        BE-->>FE: Snap token
        FE->>MT: Buka Snap, pengunjung membayar
        MT-->>FE: Hasil di UI (tidak dipercaya sebagai bukti)
        Note over FE,BE: Frontend melakukan polling GET /bookings/{id}/status
        MT->>BE: Webhook notifikasi (lihat S2)
        BE->>DB: Booking confirmed, sold_count naik, INSERT tickets
        BE->>Q: Dispatch email dan notifikasi (afterCommit)
        Q->>NS: Kirim e-ticket email dan notifikasi in-app
        NS-->>U: Email e-ticket
        FE->>BE: Polling status
        BE-->>FE: confirmed
        FE-->>U: Tampilkan tiket QR
    else Kuota tidak cukup
        BE->>DB: ROLLBACK
        BE-->>FE: 409 kuota tidak cukup
        FE-->>U: Pilih jumlah atau slot lain
    end
```

### S2. Payment gateway mengirim callback/webhook

```mermaid
sequenceDiagram
    autonumber
    participant MT as Midtrans
    participant BE as Backend (Webhook Controller)
    participant DB as PostgreSQL
    participant Q as Queue Worker
    participant PS as Payment Service
    participant NS as Notification Service

    MT->>BE: POST /webhooks/midtrans (payload + signature_key)
    BE->>BE: Hitung SHA512(order_id + status_code + gross_amount + server key)
    alt Signature tidak cocok
        BE->>DB: INSERT payment_events rejected_invalid_signature
        BE-->>MT: 403
    else Signature cocok
        BE->>DB: INSERT payment_events (idempotency_key unik)
        alt Kunci sudah ada (duplikat)
            BE-->>MT: 200 (diabaikan)
        else Peristiwa baru
            BE->>Q: Dispatch ProcessPaymentEvent
            BE-->>MT: 200
            Q->>PS: Proses event
            PS->>MT: GET status transaksi (verifikasi resmi)
            MT-->>PS: Status terkini
            PS->>DB: BEGIN, lock payment dan booking dan slot
            alt Amount tidak sama dengan payments.amount
                PS->>DB: needs_review true, hasil anomaly, COMMIT
            else Status paid dan booking pending_payment
                PS->>DB: payment paid, booking confirmed, reserved turun, sold naik, INSERT tickets, COMMIT
                PS->>NS: Dispatch BookingConfirmed (setelah commit)
            else Status paid tetapi booking expired
                PS->>DB: Coba reserve ulang kuota
                alt Kuota tersedia
                    PS->>DB: Konfirmasi booking dan terbitkan tiket, COMMIT
                else Kuota habis
                    PS->>DB: needs_review true, alasan paid_after_expiry, COMMIT
                end
            else Status expire atau cancel atau deny
                PS->>DB: Update payment, lepas kuota bila booking dibatalkan, COMMIT
            else Status lebih rendah dari status tersimpan
                PS->>DB: Abaikan, hasil ignored_stale
            end
        end
    end
```

### S3. Gate staff melakukan scan dan validasi tiket

```mermaid
sequenceDiagram
    autonumber
    actor GS as Gate Staff
    participant SC as Gate Scanner PWA
    participant BE as Backend Laravel
    participant DB as PostgreSQL

    GS->>SC: Arahkan kamera ke QR tiket
    SC->>SC: Baca payload MT1.kid.ticketId.sig
    SC->>BE: POST /api/v1/gate/scans (payload, museum_id, client_scan_uuid)
    BE->>BE: Cek otorisasi petugas untuk museum_id
    BE->>DB: Cari ticket_scans dengan client_scan_uuid yang sama
    alt Retry (sudah pernah diproses)
        DB-->>BE: Hasil lama
        BE-->>SC: Kembalikan hasil asli
    else Scan baru
        BE->>BE: Verifikasi HMAC token
        alt Token tidak valid
            BE->>DB: INSERT ticket_scans result invalid
            BE-->>SC: invalid
        else Token valid
            BE->>DB: SELECT ticket dan booking dan slot
            alt Museum tiket berbeda
                BE->>DB: INSERT ticket_scans wrong_museum
                BE-->>SC: wrong_museum
            else Tiket void
                BE->>DB: INSERT ticket_scans voided
                BE-->>SC: voided
            else Tiket sudah used
                BE->>DB: INSERT ticket_scans already_used
                BE-->>SC: already_used (waktu dan petugas sebelumnya)
            else Di luar jendela waktu
                BE->>DB: INSERT ticket_scans not_yet_valid atau expired
                BE-->>SC: not_yet_valid atau expired
            else Semua pemeriksaan lolos
                BE->>DB: BEGIN, UPDATE tickets SET status used WHERE id dan status active
                alt Tepat satu baris terpengaruh
                    BE->>DB: INSERT ticket_scans valid, COMMIT
                    BE-->>SC: valid (jenis tiket, nama pemesan)
                else Nol baris (kalah balapan)
                    BE->>DB: INSERT ticket_scans already_used, COMMIT
                    BE-->>SC: already_used
                end
            end
        end
    end
    SC-->>GS: Tampilkan hasil berwarna
```

### S4. Expire booking otomatis dan pengembalian kuota

```mermaid
sequenceDiagram
    autonumber
    participant SCH as Scheduler (tiap menit)
    participant BE as Backend (bookings:expire)
    participant MT as Midtrans
    participant DB as PostgreSQL

    SCH->>BE: Jalankan bookings:expire
    BE->>DB: Ambil booking pending_payment dengan expires_at lewat
    loop Setiap booking
        BE->>MT: GET status transaksi aktif
        MT-->>BE: Status
        alt Sudah dibayar
            BE->>BE: Proses sebagai pembayaran sukses (lihat S2)
        else Belum dibayar
            BE->>DB: BEGIN, lock booking dan slot
            BE->>DB: Booking expired, payment expired, reserved_count dikurangi
            BE->>DB: COMMIT
        end
    end
```
