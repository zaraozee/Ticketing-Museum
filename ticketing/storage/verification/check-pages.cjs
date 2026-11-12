/**
 * Pemeriksaan responsif 360 px dan smoke test halaman ber-login (AC-6 Fase 8).
 *
 * Login lewat fetch form asli, logout di antara akun (agar role tidak
 * tercampur), lalu periksa: status render (bukan halaman error) dan overflow
 * horizontal (`scrollWidth > clientWidth`) pada viewport 360x640.
 *
 * Skrip verifikasi manual, bukan bagian dari aplikasi.
 */
const { spawn } = require('child_process');
const http = require('http');
const os = require('os');
const path = require('path');

const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const BASE = process.env.BASE_URL || 'http://127.0.0.1:8100';
const PORT = Number(process.env.CDP_PORT || 9336);

const ACCOUNTS = [
    {
        label: 'pengunjung',
        email: 'user@test.com',
        password: 'password',
        pages: ['/tickets', '/bookings', '/notifications', '/profile'],
    },
    {
        label: 'pengelola',
        email: 'organizer@test.com',
        password: 'password',
        pages: [
            '/organizer',
            '/organizer/museums',
            '/organizer/schedules',
            '/organizer/bookings',
            '/organizer/reports',
            '/organizer/scanner',
            '/organizer/scans',
        ],
    },
    {
        label: 'admin',
        email: 'admin@jejak.test',
        password: 'password123',
        pages: [
            '/admin',
            '/admin/museums',
            '/admin/categories',
            '/admin/facilities',
            '/admin/content-pages',
            '/admin/reports',
            '/admin/anomalies',
        ],
    },
];

const PUBLIC_PAGES = ['/', '/museums', '/bantuan', '/login', '/register'];

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

function waitForDevTools() {
    return new Promise((resolve, reject) => {
        const deadline = Date.now() + 20000;
        const poll = () => {
            http
                .get(`http://127.0.0.1:${PORT}/json/version`, (res) => {
                    let body = '';
                    res.on('data', (c) => (body += c));
                    res.on('end', () => resolve(JSON.parse(body)));
                })
                .on('error', () => (Date.now() > deadline ? reject(new Error('DevTools tidak siap')) : setTimeout(poll, 250)));
        };
        poll();
    });
}

async function main() {
    const chrome = spawn(CHROME, [
        '--headless=new',
        '--no-sandbox',
        '--disable-gpu',
        `--remote-debugging-port=${PORT}`,
        '--user-data-dir=' + path.join(os.tmpdir(), 'jejak-cdp-check-' + Date.now()),
        'about:blank',
    ]);

    const failures = [];

    try {
        const version = await waitForDevTools();
        const ws = new WebSocket(version.webSocketDebuggerUrl);
        await new Promise((res, rej) => {
            ws.addEventListener('open', res);
            ws.addEventListener('error', rej);
        });

        let nextId = 0;
        const pending = new Map();
        ws.addEventListener('message', (event) => {
            const m = JSON.parse(event.data);
            if (m.id !== undefined && pending.has(m.id)) {
                const { resolve, reject } = pending.get(m.id);
                pending.delete(m.id);
                m.error ? reject(new Error(m.error.message)) : resolve(m.result);
            }
        });

        const send = (method, params = {}, sessionId) =>
            new Promise((resolve, reject) => {
                const id = ++nextId;
                pending.set(id, { resolve, reject });
                ws.send(JSON.stringify({ id, method, params, sessionId }));
            });

        const { targetId } = await send('Target.createTarget', { url: 'about:blank' });
        const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true });
        await send('Page.enable', {}, sessionId);
        await send('Runtime.enable', {}, sessionId);
        await send('Emulation.setDeviceMetricsOverride', { width: 360, height: 640, deviceScaleFactor: 2, mobile: true }, sessionId);

        const evaluate = async (expression) => {
            const { result, exceptionDetails } = await send(
                'Runtime.evaluate',
                { expression, returnByValue: true, awaitPromise: true },
                sessionId,
            );

            if (exceptionDetails) {
                throw new Error(exceptionDetails.text || 'evaluate gagal');
            }

            return result.value;
        };

        const visit = async (page) => {
            await send('Page.navigate', { url: BASE + page }, sessionId);
            await sleep(1800);

            return evaluate(`(() => {
                const de = document.documentElement;
                const offenders = [];
                for (const el of document.querySelectorAll('body *')) {
                    const rect = el.getBoundingClientRect();
                    if (rect.width === 0 || rect.height === 0) continue;
                    if (rect.right > de.clientWidth + 1) {
                        if (getComputedStyle(el).position === 'fixed') continue;
                        offenders.push({ tag: el.tagName.toLowerCase(), cls: String(el.className || '').slice(0, 80), right: Math.round(rect.right) });
                    }
                }
                // Inertia v3 menaruh payload di <script data-page>, bukan di #app.
                const mounted = (document.querySelector('#app')?.children.length || 0) > 0;
                const hasInertiaPayload = !!document.querySelector('script[data-page]');
                const isErrorPage = !!document.querySelector('.shiki, [class*="exception"], #ignition-error');
                return {
                    path: location.pathname,
                    title: document.title,
                    h1: (document.querySelector('h1')?.textContent || '').trim().slice(0, 48),
                    scrollWidth: de.scrollWidth,
                    clientWidth: de.clientWidth,
                    overflow: de.scrollWidth > de.clientWidth,
                    mounted,
                    hasInertiaPayload,
                    error: isErrorPage || !mounted || !hasInertiaPayload,
                    offenders: offenders.slice(0, 3),
                };
            })()`);
        };

        const report = (label, info) => {
            const status = info.error ? 'ERROR' : info.overflow ? 'OVERFLOW' : 'ok';
            console.log(`${status.padEnd(9)} ${label.padEnd(9)} ${info.path.padEnd(26)} title="${info.title}"`);
            for (const o of info.offenders) {
                console.log(`            ↳ <${o.tag} class="${o.cls}"> right=${o.right}`);
            }
            if (status !== 'ok') {
                failures.push(`${label} ${info.path}`);
            }
        };

        const logout = async () => {
            await evaluate(`(async () => {
                const meta = document.querySelector('meta[name=csrf-token]');
                if (!meta) return;
                await fetch('/logout', { method: 'POST', headers: { 'X-CSRF-TOKEN': meta.content }, redirect: 'manual' }).catch(() => {});
            })()`);
        };

        console.log('== publik ==');
        for (const page of PUBLIC_PAGES) {
            report('publik', await visit(page));
        }

        for (const account of ACCOUNTS) {
            await send('Page.navigate', { url: `${BASE}/login` }, sessionId);
            await sleep(1500);
            await logout();
            await send('Page.navigate', { url: `${BASE}/login` }, sessionId);
            await sleep(1500);

            const loginStatus = await evaluate(`(async () => {
                const meta = document.querySelector('meta[name=csrf-token]');
                const res = await fetch('/login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': meta.content },
                    body: new URLSearchParams({ email: ${JSON.stringify(account.email)}, password: ${JSON.stringify(account.password)} }).toString(),
                    redirect: 'manual',
                });
                return res.status;
            })()`);

            console.log(`\n== ${account.label} (login HTTP ${loginStatus}) ==`);

            for (const page of account.pages) {
                report(account.label, await visit(page));
            }
        }

        ws.close();
        process.exitCode = failures.length === 0 ? 0 : 1;
        console.log(failures.length === 0 ? '\nSemua halaman lolos (render + 360 px).' : `\nGagal: ${failures.join(', ')}`);
    } finally {
        chrome.kill();
    }
}

main().catch((e) => {
    console.error(e);
    process.exit(1);
});
