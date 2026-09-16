import { tag } from 'shared-lib';

const port = Number(process.env.PORT || 4102);

Bun.serve({
  port,
  fetch(req) {
    const url = new URL(req.url);

    if (url.pathname === '/health') {
      return Response.json({ ok: true, service: 'api-with-lib' });
    }

    if (url.pathname === '/api/v1/version') {
      return Response.json({ service: 'api-with-lib', lib: tag });
    }

    if (url.pathname === '/echo') {
      const headers: Record<string, string> = {};
      req.headers.forEach((v, k) => {
        headers[k] = v;
      });
      return req.text().then((body) =>
        Response.json({
          method: req.method,
          path: url.pathname,
          query: url.search,
          headers,
          body,
        }),
      );
    }

    return new Response('not found', { status: 404 });
  },
});

console.log('listening', port);
