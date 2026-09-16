import { mkdirSync, writeFileSync } from 'node:fs';

mkdirSync('dist', { recursive: true });
writeFileSync('dist/server.js', `
const port = Number(process.env.PORT || 4100);
Bun.serve({
  port,
  fetch(req) {
    const url = new URL(req.url);
    if (url.pathname === '/health') return new Response('ok');
    if (url.pathname === '/api/hello') return Response.json({ hello: 'fixture' });
    return new Response('not found', { status: 404 });
  },
});
console.log('listening', port);
`);

console.log('built');
