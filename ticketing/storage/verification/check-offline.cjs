/**
 * Uji fungsional PWA (AC-2, AC-3, AC-4 Fase 8) memakai Chrome DevTools Protocol.
 *
 * Emulasi `Network.emulateNetworkConditions` **tidak** berlaku untuk target
 * service worker, sehingga skenario offline di sini dilakukan dengan
 * mematikan proxy upstream sungguhan: service worker benar-benar gagal
 * `fetch` ke server, persis seperti perangkat tanpa jaringan.
 *
 * Skenario:
 *  1. Buka beranda, pastikan service worker aktif dan mengendalikan halaman.
 *  2. Buka satu e-ticket (snapshot tersimpan di IndexedDB beserta QR).
 *  3. Matikan proxy upstream (offline nyata).
 *  4. `/tickets` (NetworkOnly) harus menampilkan fallback offline, bukan
 *     halaman tiket dari cache.
 *  5. `/offline/tickets` menampilkan e-ticket tersimpan berlabel
 *     "Tidak terverifikasi".
 *  6. `/museums` (StaleWhileRevalidate) tetap terbuka dari cache.
 *  7. Proxy dihidupkan lagi: `/tickets` kembali normal.
 *
 * Skrip verifikasi manual, bukan bagian dari aplikasi.
 */
const { spawn } = require('child_process');
const http = require('http');
const zlib = require('zlib');
const os = require('os');
const path = require('path');

const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const UPSTREAM = { host: '127.0.0.1', port: Number(process.env.UPSTREAM_PORT || 8000) };
const PROXY_PORT = Number(process.env.PROXY_PORT || 8102);
const BASE = `http://127.0.0.1:${PROXY_PORT}`;
const PORT = Number(process.env.CDP_PORT || 9338);
const TICKET_UUID = process.env.TICKET_UUID;

if (!TICKET_UUID) {
    console.error('TICKET_UUID wajib diisi (uuid tiket milik user uji).');
    process.exit(1);
}

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

/** Proxy gzip sederhana agar ukuran transfer menyerupai produksi. */
function startProxy() {
    const COMPRESSIBLE = /^(text\/|application\/(javascript|json|xml|manifest\+json)|image\/svg)/;

    const server = http.createServer((req, res) => {
        const proxy = http.request(
            { ...UPSTREAM, path: req.url, method: req.method, headers: req.headers },
            (upstream) => {
                const headers = { ...upstream.headers };
                const type = String(headers['content-type'] || '');
                const acceptsGzip = String(req.headers['accept-encoding'] || '').includes('gzip');

                delete headers['content-length'];

                if (!acceptsGzip || !COMPRESSIBLE.test(type)) {
                    res.writeHead(upstream.statusCode || 200, headers);
                    upstream.pipe(res);

                    return;
                }

                const chunks = [];
                upstream.on('data', (chunk) => chunks.push(chunk));
                upstream.on('end', () => {
                    const gzipped = zlib.gzipSync(Buffer.concat(chunks), { level: 9 });
                    headers['content-encoding'] = 'gzip';
                    headers['content-length'] = gzipped.length;
                    headers.vary = 'Accept-Encoding';
                    res.writeHead(upstream.statusCode || 200, headers);
                    res.end(gzipped);
                });
            },
        );

        proxy.on('error', () => {
            res.writeHead(502);
            res.end('upstream error');
        });

        req.pipe(proxy);
    });

    return new Promise((resolve) => server.listen(PROXY_PORT, '127.0.0.1', () => resolve(server)));
}

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
    let proxy = await startProxy();

    const chrome = spawn(CHROME, [
        '--headless=new',
        '--no-sandbox',
        '--disable-gpu',
        `--remote-debugging-port=${PORT}`,
        '--user-data-dir=' + path.join(os.tmpdir(), 'jejak-cdp-offline-' + Date.now()),
        'about:blank',
    ]);

    const results = [];
    const check = (name, passed, detail = '') => {
        results.push({ name, passed });
        console.log(`${passed ? 'PASS' : 'FAIL'}  ${name}${detail ? ` — ${detail}` : ''}`);
    };

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

        const go = async (url, wait = 2200) => {
            await send('Page.navigate', { url: BASE + url }, sessionId);
            await sleep(wait);
        };

        // --- 1. Login + registrasi service worker ----------------------------
        await go('/login', 1600);
        await evaluate(`(async () => {
            const meta = document.querySelector('meta[name=csrf-token]');
            await fetch('/login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': meta.content },
                body: new URLSearchParams({ email: 'user@test.com', password: 'password' }).toString(),
                redirect: 'manual',
            });
        })()`);

        await go('/', 3000);

        const swState = await evaluate(`(async () => {
            if (!('serviceWorker' in navigator)) return { supported: false };
            const reg = await navigator.serviceWorker.ready;
            for (let i = 0; i < 60 && !navigator.serviceWorker.controller; i++) {
                await new Promise((r) => setTimeout(r, 100));
            }
            return {
                supported: true,
                scope: reg.scope,
                active: !!reg.active,
                controlled: !!navigator.serviceWorker.controller,
                script: reg.active ? reg.active.scriptURL : null,
            };
        })()`);

        check('service worker aktif & mengendalikan halaman', swState.supported && swState.active && swState.controlled, JSON.stringify(swState));
        check('scope service worker mencakup root', String(swState.scope || '').endsWith('/'), swState.scope);
        check('service worker disajikan dari /sw.js', String(swState.script || '').endsWith('/sw.js'), swState.script);

        // --- 2. Buka e-ticket agar snapshot tersimpan ------------------------
        await go(`/tickets/${TICKET_UUID}`, 3500);

        // Halaman publik perlu dikunjungi sekali agar masuk cache SWR.
        await go('/museums', 2500);

        const saved = await evaluate(`(async () => {
            const db = await new Promise((resolve, reject) => {
                const req = indexedDB.open('jejak-tickets', 1);
                req.onsuccess = () => resolve(req.result);
                req.onerror = () => reject(req.error);
            });
            const rows = await new Promise((resolve, reject) => {
                const tx = db.transaction('tickets', 'readonly');
                const req = tx.objectStore('tickets').getAll();
                req.onsuccess = () => resolve(req.result || []);
                req.onerror = () => reject(req.error);
            });
            db.close();
            return rows.map((r) => ({
                uuid: r.uuid,
                hasQr: typeof r.qr_data_url === 'string' && r.qr_data_url.startsWith('data:image'),
                code: r.ticket_code,
            }));
        })()`);

        check('e-ticket tersimpan di IndexedDB beserta QR', saved.length > 0 && saved.every((r) => r.hasQr), JSON.stringify(saved));

        // --- 3. Offline nyata: matikan proxy upstream ------------------------
        await new Promise((resolve) => proxy.close(resolve));
        await sleep(500);

        // --- 4. NetworkOnly: /tickets tidak boleh dari cache ------------------
        await go('/tickets', 3000);

        const offlineTickets = await evaluate(`({
            url: location.pathname,
            title: document.title,
            text: document.body.innerText.slice(0, 160).replace(/\\s+/g, ' '),
            hasOfflineNotice: document.body.innerText.includes('offline') || document.body.innerText.includes('Offline'),
            hasSavedLink: !!document.querySelector('a[href="/offline/tickets"]'),
            mountedVueApp: (document.querySelector('#app')?.children.length || 0) > 0,
        })`);

        check(
            '/tickets (NetworkOnly) menampilkan fallback offline, bukan halaman dari cache',
            offlineTickets.hasOfflineNotice && !offlineTickets.mountedVueApp,
            JSON.stringify(offlineTickets),
        );

        // --- 5. E-ticket offline berlabel "Tidak terverifikasi" --------------
        await go('/offline/tickets', 3000);

        const offlineList = await evaluate(`({
            title: document.title,
            text: document.body.innerText.slice(0, 900),
            images: Array.from(document.querySelectorAll('img')).map((img) => img.src.slice(0, 24)),
        })`);

        check(
            'e-ticket offline tampil dengan label "Tidak terverifikasi"',
            offlineList.text.includes('Tidak terverifikasi') && offlineList.images.some((src) => src.startsWith('data:image')),
            JSON.stringify({ title: offlineList.title, images: offlineList.images }),
        );

        // --- 6. Halaman publik tetap tersedia dari cache ---------------------
        await go('/museums', 3000);

        const publicPage = await evaluate(`({
            title: document.title,
            mountedVueApp: (document.querySelector('#app')?.children.length || 0) > 0,
            h1: (document.querySelector('h1')?.textContent || '').trim(),
        })`);

        check(
            'halaman publik tetap terbuka dari cache saat offline',
            publicPage.mountedVueApp && publicPage.h1 === 'Jelajah Museum',
            JSON.stringify(publicPage),
        );

        // --- 7. Kembali online ----------------------------------------------
        proxy = await startProxy();
        await sleep(500);

        await go('/tickets', 3500);

        const backOnline = await evaluate(`({
            title: document.title,
            mountedVueApp: (document.querySelector('#app')?.children.length || 0) > 0,
            h1: (document.querySelector('h1')?.textContent || '').trim(),
        })`);

        check('/tickets kembali normal setelah online', backOnline.mountedVueApp && backOnline.h1 === 'Tiket Saya', JSON.stringify(backOnline));

        ws.close();
        const failed = results.filter((r) => !r.passed).length;
        console.log(`\n${results.length - failed}/${results.length} pemeriksaan lolos.`);
        process.exitCode = failed === 0 ? 0 : 1;
    } finally {
        chrome.kill();
        await new Promise((resolve) => proxy.close(resolve));
    }
}

main().catch((e) => {
    console.error(e);
    process.exit(1);
});
