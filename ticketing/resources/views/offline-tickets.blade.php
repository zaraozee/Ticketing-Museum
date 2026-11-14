<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="{{ config('ticketing.pwa.theme_color', '#2563eb') }}">
    <meta name="robots" content="noindex, nofollow">
    <title>Tiket Offline — {{ config('app.name') }}</title>
    <style>
        :root { color-scheme: light; --brand: {{ config('ticketing.pwa.theme_color', '#2563eb') }}; }
        * { box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', sans-serif;
            background: #f8fafc; color: #1e293b; margin: 0; min-height: 100vh;
            padding: max(1rem, env(safe-area-inset-top)) 1rem max(2rem, env(safe-area-inset-bottom));
        }
        .wrap { max-width: 42rem; margin: 0 auto; }
        .top { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem 0; }
        .brand { display: flex; align-items: center; gap: .5rem; font-weight: 700; }
        .mark { width: 2.25rem; height: 2.25rem; border-radius: .75rem; background: var(--brand); color: #fff;
            display: flex; align-items: center; justify-content: center; font-weight: 700; }
        .back { font-size: .875rem; font-weight: 600; color: var(--brand); text-decoration: none; }
        .banner { border: 1px solid #fde68a; background: #fffbeb; color: #92400e; border-radius: .75rem;
            padding: .875rem 1rem; font-size: .8125rem; line-height: 1.5; margin-bottom: 1rem; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 1rem; padding: 1.25rem; margin-bottom: 1rem; }
        .card h2 { margin: 0; font-size: 1.0625rem; }
        .meta { margin: .25rem 0 0; font-size: .8125rem; color: #475569; }
        .code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .8125rem; color: #0f172a; }
        .qr { display: flex; justify-content: center; margin: 1rem 0; }
        .qr img { width: min(17rem, 70vw); height: auto; border: 2px solid #e2e8f0; border-radius: .75rem; padding: .5rem; background: #fff; }
        .hint { font-size: .75rem; color: #64748b; text-align: center; margin: 0; }
        .empty { text-align: center; color: #64748b; font-size: .875rem; padding: 2rem 1rem; }
        .empty a { color: var(--brand); font-weight: 600; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div class="brand">
            <span class="mark">{{ mb_substr(config('app.name'), 0, 1) }}</span><span>{{ config('app.name') }}</span>
        </div>
        <a class="back" href="/tickets">Tiket Saya</a>
    </div>

    <div class="banner">
        Anda sedang offline. Tiket di bawah ini berasal dari penyimpanan perangkat dan
        <strong>belum terverifikasi</strong>. Validitas akhir ditentukan petugas saat pemindaian di gerbang.
    </div>

    <div id="list" aria-live="polite">
        <p class="empty">Memuat tiket tersimpan…</p>
    </div>
</div>

<script>
    (function () {
        var list = document.getElementById('list');
        var DB_NAME = 'musentix-tickets';
        var STORE = 'tickets';

        function renderEmpty() {
            list.innerHTML =
                '<div class="card"><p class="empty">Belum ada tiket yang tersimpan di perangkat ini. ' +
                'Buka e-ticket saat online agar dapat diakses kembali saat offline. ' +
                '<a href="/tickets">Buka Tiket Saya</a></p></div>';
        }

        function render(tickets) {
            if (!tickets.length) {
                renderEmpty();
                return;
            }

            tickets.sort(function (a, b) {
                return String(a.visit_date).localeCompare(String(b.visit_date));
            });

            list.innerHTML = tickets.map(function (t) {
                var updated = t.cached_at ? new Date(t.cached_at).toLocaleString('id-ID', {
                    day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'
                }) : 'waktu tidak diketahui';

                return '' +
                    '<article class="card">' +
                        '<h2>' + escapeHtml(t.museum_name) + '</h2>' +
                        '<p class="meta">' + escapeHtml(t.visit_date) + ' &middot; ' + escapeHtml(t.slot_label) + '</p>' +
                        '<p class="meta">Kode: <span class="code">' + escapeHtml(t.ticket_code) + '</span> &middot; ' + escapeHtml(t.ticket_type_name) + '</p>' +
                        '<div class="qr"><img alt="Kode QR tiket ' + escapeHtml(t.ticket_code) + '" src="' + escapeHtml(t.qr_data_url) + '"></div>' +
                        '<p class="hint">Tidak terverifikasi &mdash; terakhir diperbarui ' + escapeHtml(updated) + '</p>' +
                    '</article>';
            }).join('');
        }

        function escapeHtml(value) {
            return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
            });
        }

        if (!('indexedDB' in window)) {
            renderEmpty();
            return;
        }

        var request = indexedDB.open(DB_NAME, 1);

        request.onerror = renderEmpty;
        request.onupgradeneeded = function () {
            var db = request.result;
            if (!db.objectStoreNames.contains(STORE)) {
                db.createObjectStore(STORE, { keyPath: 'uuid' });
            }
        };
        request.onsuccess = function () {
            var db = request.result;
            if (!db.objectStoreNames.contains(STORE)) {
                renderEmpty();
                return;
            }

            var tx = db.transaction(STORE, 'readonly');
            var getAll = tx.objectStore(STORE).getAll();
            getAll.onsuccess = function () { render(getAll.result || []); };
            getAll.onerror = renderEmpty;
        };
    })();
</script>
</body>
</html>
