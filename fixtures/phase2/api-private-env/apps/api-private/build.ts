import { mkdirSync, writeFileSync } from 'node:fs';

mkdirSync('dist', { recursive: true });
writeFileSync(
  'dist/server.js',
  `
const port = Number(process.env.PORT || 4103);
Bun.serve({
  port,
  fetch(req) {
    const url = new URL(req.url);
    if (url.pathname === '/health') {
      const dbUrl = process.env.DATABASE_URL;
      if (!dbUrl) {
        return Response.json({ ok: false, error: 'DATABASE_URL missing' }, { status: 503 });
      }
      return Response.json({ ok: true, db: 'connected' });
    }
    if (url.pathname === '/echo') {
      const headers = {};
      req.headers.forEach((v, k) => { headers[k] = v; });
      return req.text().then((body) => Response.json({
        method: req.method,
        path: url.pathname,
        query: url.search,
        headers,
        body,
      }));
    }
    return new Response('not found', { status: 404 });
  },
});
console.log('listening', port);
`,
);

console.log('built api-private');
