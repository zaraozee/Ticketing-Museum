<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="{{ config('ticketing.pwa.theme_color', '#2563eb') }}">
    <meta name="robots" content="noindex, nofollow">
    <title>Offline — {{ config('app.name') }}</title>
    <style>
        :root { color-scheme: light; --brand: {{ config('ticketing.pwa.theme_color', '#2563eb') }}; }
        * { box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', sans-serif;
            background: #f8fafc; color: #1e293b; margin: 0; min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            padding: max(1rem, env(safe-area-inset-top)) 1rem max(1rem, env(safe-area-inset-bottom));
        }
        .container { text-align: center; padding: 2rem 1.5rem; max-width: 26rem; }
        .icon { width: 4rem; height: 4rem; background: var(--brand); border-radius: 1rem; display: flex;
            align-items: center; justify-content: center; margin: 0 auto 1.5rem; color: #fff; font-size: 2rem; font-weight: 700; }
        h1 { font-size: 1.5rem; font-weight: 700; margin: 0 0 .5rem; }
        p { color: #64748b; font-size: .875rem; line-height: 1.6; margin: 0 0 1.5rem; }
        .actions { display: flex; flex-direction: column; gap: .5rem; }
        a, button { display: block; width: 100%; border-radius: .75rem; padding: .875rem 1.5rem; font-weight: 600;
            font-size: .9375rem; cursor: pointer; text-decoration: none; border: 1px solid transparent; }
        .primary { background: var(--brand); color: #fff; }
        .primary:hover { filter: brightness(0.92); }
        .secondary { background: #fff; color: #334155; border-color: #cbd5e1; }
        .secondary:hover { background: #f1f5f9; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon" aria-hidden="true">{{ mb_substr(config('app.name'), 0, 1) }}</div>
        <h1>Anda Sedang Offline</h1>
        <p>
            Koneksi internet terputus. Tiket yang pernah dibuka masih tersimpan di perangkat
            dan dapat dilihat tanpa jaringan.
        </p>
        <div class="actions">
            <a class="primary" href="/offline/tickets">Lihat Tiket Tersimpan</a>
            <button class="secondary" type="button" onclick="window.location.reload()">Coba Lagi</button>
        </div>
    </div>
</body>
</html>
