import { mkdirSync, writeFileSync } from 'node:fs';

mkdirSync('dist', { recursive: true });
writeFileSync(
  'dist/server.js',
  `
const port = Number(process.env.PORT || 4101);
Bun.serve({
  port,
  fetch(req) {
    const url = new URL(req.url);
    if (url.pathname === '/health') {
      return Response.json({ ok: true, service: 'api-simple' });
    }
    if (url.pathname === '/api/v1/ping') {
      return Response.json({ pong: true, service: 'api-simple' });
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

console.log('built api-simple');

// dirty-1789527489
