#!/usr/bin/env bash
#
# Pemeriksaan operasional MusenTix untuk Healthchecks.io (Fase 9, AC-5).
#
# Healthchecks.io hanya dapat memanggil URL, bukan menjalankan perintah, jadi
# skrip inilah jembatannya: menjalankan `ops:health`, lalu memberi tahu
# Healthchecks.io apakah pemeriksaan berhasil atau gagal.
#
# Memakai `/fail` (bukan sekadar berhenti mengirim ping) supaya alarm langsung
# berbunyi saat ada masalah, tanpa menunggu grace time habis.
#
# Pasang di crontab:
#   */5 * * * * /usr/local/bin/musentix-health.sh >/dev/null 2>&1
#
set -uo pipefail

# --- Konfigurasi -----------------------------------------------------------
# Sesuaikan dua nilai berikut saat pemasangan.
APP_DIR="${MUSENTIX_APP_DIR:-/var/www/musentix}"
PING_URL="${HEALTHCHECKS_PING_URL:-}"

if [ -z "$PING_URL" ]; then
    echo "HEALTHCHECKS_PING_URL belum diset." >&2
    exit 2
fi

if [ ! -d "$APP_DIR" ]; then
    echo "Direktori aplikasi tidak ditemukan: $APP_DIR" >&2
    curl -fsS --retry 3 -m 10 "$PING_URL/fail" >/dev/null 2>&1 || true
    exit 1
fi

cd "$APP_DIR" || exit 1

# --- Jalankan pemeriksaan --------------------------------------------------
# Keluaran disimpan agar dapat diperiksa saat menyelidiki alarm.
HEALTH_LOG="${HEALTH_LOG:-$APP_DIR/storage/logs/health.log}"
mkdir -p "$(dirname "$HEALTH_LOG")"

{
    echo "--- $(date -Is) ---"
    php artisan ops:health --json
} >> "$HEALTH_LOG" 2>&1

STATUS=$?

if [ $STATUS -eq 0 ]; then
    # Sehat: beri tahu Healthchecks.io bahwa semuanya baik.
    curl -fsS --retry 3 -m 10 "$PING_URL" >/dev/null 2>&1
    exit 0
fi

# Ada alarm. Kirim ke /fail agar status langsung Down di dashboard.
curl -fsS --retry 3 -m 10 "$PING_URL/fail" >/dev/null 2>&1

# Sertakan ringkasan pada body agar terlihat di halaman detail check.
tail -n 40 "$HEALTH_LOG" 2>/dev/null | curl -fsS --retry 3 -m 10 --data-binary @- "$PING_URL/fail" >/dev/null 2>&1 || true

exit 1
