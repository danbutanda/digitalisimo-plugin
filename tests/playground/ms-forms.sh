#!/usr/bin/env bash
# Paridad sitio/red de los formularios de Hosting, Backups y Migración en una red real de WordPress Playground (con
# Chrome sin interfaz): guardar, recargar y ver que quedó, en la red, en el sitio principal y en un subsitio. Ver
# ms-forms/check.mjs. Falla si una comprobación falla o hay un error de PHP.
# Uso: bash tests/playground/ms-forms.sh   (en la PC, con pc-run.sh)
#   OUT, TMPDIR     resultados (por defecto /var/tmp/digi-ms/ms-forms) y temporales de Playground
#   PORT            puerto (por defecto 9681)
#   PUPPETEER_PATH  carpeta node_modules con puppeteer-core (si no, se busca en la caché de npx o se instala 25.12.0)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
D="$ROOT/tests/playground/ms-forms"
export OUT="${OUT:-/var/tmp/digi-ms/ms-forms}"
export TMPDIR="${TMPDIR:-/var/tmp/digi-ms/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PORT="${PORT:-9681}"
host='ms-forms.test'
SERVER=''
trap '[ -n "$SERVER" ] && kill "$SERVER" 2>/dev/null; fuser -k -n tcp "$PORT" >/dev/null 2>&1 || true; playground_cleanup' EXIT
trap 'exit 130' INT TERM
if [ -z "${PUPPETEER_PATH:-}" ]; then
  PUPPETEER_PATH=$(ls -d "$HOME"/.npm/_npx/*/node_modules 2>/dev/null | while read -r d; do [ -d "$d/puppeteer-core" ] && echo "$d" && break; done || true)
fi
if [ -z "${PUPPETEER_PATH:-}" ]; then
  mkdir -p "$TMPDIR/pptr"
  npm install --silent --no-audit --no-fund --prefix "$TMPDIR/pptr" puppeteer-core@25.12.0 >/dev/null
  PUPPETEER_PATH="$TMPDIR/pptr/node_modules"
fi
export PUPPETEER_PATH
run="$OUT/run"
rm -rf "$run"; mkdir -p "$run/probe" "$run/store"; chmod 700 "$run/store"
cp "$D/setup.php" "$run/probe/"
mounts=()
for p in digitalisimo-hosting digitalisimo-backups digitalisimo-migration; do
  cp -a "$ROOT/$p" "$run/$p"
  mounts+=(--mount="$run/$p:/wordpress/wp-content/plugins/$p")
done
cat > "$run/blueprint.json" <<JSON
{"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{"DIGITALISIMO_BACKUPS_STORAGE_DIR":"/digi-store","WP_ENVIRONMENT_TYPE":"local"}},{"step":"enableMultisite"},
{"step":"runPHP","code":"<?php require '/digi-probe/setup.php';"}]}
JSON
if fuser -n tcp "$PORT" >/dev/null 2>&1; then echo "el puerto $PORT está ocupado; usa PORT=…"; exit 1; fi
begun=$(date +%s)
$CLI server --port="$PORT" --workers=1 --site-url="http://$host" --blueprint="$run/blueprint.json" \
  "${mounts[@]}" --mount="$run/store:/digi-store" --mount="$run/probe:/digi-probe" > "$run/server.log" 2>&1 &
SERVER=$!
ready=0
for i in $(seq 1 400); do
  sleep 3
  kill -0 "$SERVER" 2>/dev/null || break
  [ -f "$run/probe/setup-error.txt" ] && break
  if [ -s "$run/probe/setup.json" ] && [ "$(curl -s -o /dev/null -w '%{http_code}' -m 60 -H "Host: $host" "http://127.0.0.1:$PORT/?rest_route=%2F")" = 200 ]; then ready=1; break; fi
done
if [ "$ready" != 1 ]; then
  echo "== la red no quedó lista"; cat "$run/probe/setup-error.txt" 2>/dev/null || true; tail -20 "$run/server.log"; exit 1
fi
echo "== red lista en $(( $(date +%s) - begun )) s: $(cat "$run/probe/setup.json")"
fail=0
node "$D/check.mjs" "$PORT" "$host" "$OUT/pages" || fail=1
if grep -E 'Fatal error|Uncaught|PHP Warning' "$run/server.log" | head -5 | grep -q .; then echo "== errores de PHP en el servidor"; grep -E 'Fatal error|Uncaught|PHP Warning' "$run/server.log" | head -5; fail=1; fi
cp "$run/server.log" "$OUT/server.log"
exit $fail
