#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WORK="$(mktemp -d "${TMPDIR:-/tmp}/jit-smoke.XXXXXX")"
cleanup() { rm -rf "$WORK"; }
trap cleanup EXIT

git init "$WORK" >/dev/null 2>&1
git -C "$WORK" config user.email "jit-smoke@local"
git -C "$WORK" config user.name "jit-smoke"
cp -R "$ROOT/fixtures/bun-service" "$WORK/app"

cat >"$WORK/services.php" <<'PHP'
<?php
return ['services' => ['fixture' => [
    'dir' => 'app',
    'port' => 4100,
    'commands' => ['build' => ['bun', 'run', 'build'], 'start' => ['bun', 'run', 'start']],
    'watch' => ['app'],
    'envPublic' => ['PORT' => '4100'],
    'readyPath' => '/health',
]]];
PHP

cd "$WORK"
git add -A >/dev/null 2>&1
git commit -m "init" >/dev/null 2>&1

echo "smoke workspace: $WORK"
JIT_USE_DOCKER="${JIT_USE_DOCKER:-0}" "$ROOT/bin/dev" test

if command -v bun >/dev/null 2>&1; then
  cd "$WORK/app"
  bun run build >/dev/null
  PORT=4100 bun run start >/dev/null &
  pid=$!
  sleep 1
  code=$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:4100/health" || true)
  kill "$pid" 2>/dev/null || true
  if [[ "$code" != "200" ]]; then
    echo "smoke fixture health failed (code=$code)"
    exit 1
  fi
  echo "smoke fixture bun lifecycle ok"
else
  echo "smoke: bun not on host; skipped fixture HTTP probe"
fi

echo "smoke ok"
