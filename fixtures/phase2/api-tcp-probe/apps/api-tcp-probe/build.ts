import { mkdirSync, writeFileSync } from 'node:fs';

// THROWAWAY Phase2 T12 probe: TCP readiness only (no readyPath in services.php).
mkdirSync('dist', { recursive: true });
writeFileSync(
  'dist/server.js',
  `
const port = Number(process.env.PORT || 4104);
Bun.serve({
  port,
  fetch(req) {
    const url = new URL(req.url);
    if (url.pathname === '/health' || url.pathname === '/api/v1/ping') {
      return Response.json({ ok: true, service: 'api-tcp-probe', mode: 'tcp-ready' });
    }
    return new Response('not found', { status: 404 });
  },
});
console.log('listening', port);
`,
);

console.log('built api-tcp-probe');
