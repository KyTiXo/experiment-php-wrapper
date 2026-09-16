import { mkdirSync } from 'node:fs';

mkdirSync('dist', { recursive: true });

const result = await Bun.build({
  entrypoints: ['./entry.ts'],
  outdir: './dist',
  target: 'bun',
  naming: 'server.js',
});

if (!result.success) {
  console.error('build failed');
  for (const log of result.logs) {
    console.error(log);
  }
  process.exit(1);
}

console.log('built api-with-lib');
