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
    const dbUrl = process.env.DATABASE_URL || '';

    if (url.pathname === '/health') {
      if (!dbUrl) {
        return Response.json({ ok: false, error: 'DATABASE_URL required' }, { status: 503 });
      }
      // Mock connectivity: accept any non-empty URL (fake postgres:// is fine).
      return Response.json({ ok: true, service: 'api-private', db: 'connected' });
    }

    if (url.pathname === '/api/v1/ping') {
      return Response.json({
        pong: true,
        service: 'api-private',
        hasDb: Boolean(dbUrl),
      });
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
        hasDb: Boolean(dbUrl),
      }));
    }

    return new Response('not found', { status: 404 });
  },
});
console.log('listening', port);
`,
);

console.log('built api-private');
