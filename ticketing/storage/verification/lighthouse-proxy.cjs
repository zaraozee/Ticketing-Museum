/**
 * Proxy uji Lighthouse: meneruskan permintaan ke `php artisan serve` sambil
 * mengompresi respons seperti Nginx produksi (gzip). Hanya dipakai untuk
 * verifikasi AC-5; bukan bagian dari aplikasi.
 */
const http = require('http');
const zlib = require('zlib');

const UPSTREAM = { host: '127.0.0.1', port: 8000 };

const COMPRESSIBLE = /^(text\/|application\/(javascript|json|xml|manifest\+json)|image\/svg)/;

http
    .createServer((req, res) => {
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
                    const body = Buffer.concat(chunks);
                    const gzipped = zlib.gzipSync(body, { level: 9 });

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
    })
    .listen(8100, '127.0.0.1', () => console.log('proxy on http://127.0.0.1:8100'));
