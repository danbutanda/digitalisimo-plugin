#!/usr/bin/env bash
# «Importar un WordPress individual en una red» de DIGITALÍSIMO Backups (pedido del propietario del 2026-11-16: un
# respaldo subido decía «No se restaura aquí») en WordPress Playground (SQLite), con Chrome sin interfaz y por la pantalla
# de la red:
#   origen  un WordPress individual `origen.test` con contenido, un medio, usuarios con roles, un plugin y un tema que la
#           red no tiene; su respaldo completo con el motor de trabajos (backups-site-import/origin.php).
#   red     una red por subdirectorios `red.test` con el principal y el sitio 2 (/dos/), usuarios que chocan con los del
#           respaldo (mismo usuario y correo, mismo correo, mismo nombre de otra persona) y el administrador del sitio 2.
#           backups-site-import/drive.mjs sube el respaldo, lo importa como sitio nuevo en /importado/, después
#           reemplaza el sitio 2 y, por último, repite el reemplazo con una falla inyectada justo después de fundir los
#           usuarios (vuelve sola a la copia previa del sitio 2), comprobándolo todo con huellas.
# Capturas en $OUT/shots y resultado en $OUT/result.txt.
# Uso: bash tests/playground/backups-site-import.sh   (en la PC, con pc-run.sh; una corrida pesada a la vez)
#   OUT, TMPDIR     resultados (por defecto /var/tmp/digi-bk/backups-site-import) y temporales de Playground
#   PORT            puerto de la red (por defecto 9731; si está ocupado, el siguiente libre)
#   PUPPETEER_PATH  carpeta node_modules con puppeteer-core (si no, se busca en la caché de npx o se instala 25.12.0)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
D="$ROOT/tests/playground/backups-site-import"
export OUT="${OUT:-/var/tmp/digi-bk/backups-site-import}"
export TMPDIR="${TMPDIR:-/var/tmp/digi-bk/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PORT="${PORT:-9731}"
SERVER=''
SERVER_PORT=''
stop_server() {
  [ -n "$SERVER" ] && kill "$SERVER" 2>/dev/null || true
  [ -n "$SERVER_PORT" ] && for i in 1 2 3; do fuser -k -n tcp "$SERVER_PORT" >/dev/null 2>&1 || break; sleep 1; done
  SERVER=''; SERVER_PORT=''
}
trap 'stop_server; playground_cleanup' EXIT
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
start=$(date +%s)
rm -rf "$OUT/origen" "$OUT/red" "$OUT/shots" "$OUT/result.txt"
mkdir -p "$OUT/origen/probe" "$OUT/origen/store" "$OUT/red/probe" "$OUT/red/store"
chmod 700 "$OUT/origen/store" "$OUT/red/store"
cp -a "$ROOT/digitalisimo-backups" "$OUT/origen/backups"
cp -a "$ROOT/digitalisimo-backups" "$OUT/red/backups"
cp "$D/origin.php" "$OUT/origen/probe/"

# Fase «origen»: el WordPress individual y su respaldo.
cat > "$OUT/origen/blueprint.json" <<JSON
{"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{"DIGITALISIMO_BACKUPS_STORAGE_DIR":"/digi-store","AUTH_KEY":"origen-auth-key-0123456789abcdef0123456789abcdef","SECURE_AUTH_KEY":"origen-secure-auth-key-0123456789abcdef0123456789"}},
{"step":"runPHP","code":"<?php require '/digi-probe/origin.php';"}]}
JSON
begun=$(date +%s)
$CLI run-blueprint --site-url=http://origen.test --blueprint="$OUT/origen/blueprint.json" \
  --mount="$OUT/origen/backups:/wordpress/wp-content/plugins/digitalisimo-backups" \
  --mount="$OUT/origen/probe:/digi-probe" \
  --mount="$OUT/origen/store:/digi-store" > "$OUT/origen/playground.log" 2>&1 || { echo "== origen: Playground terminó con error"; tail -30 "$OUT/origen/playground.log"; exit 1; }
if [ -f "$OUT/origen/probe/setup-error.txt" ] || [ ! -s "$OUT/origen/probe/origen.zip" ]; then echo "== origen: no quedó el respaldo"; cat "$OUT/origen/probe/setup-error.txt" 2>/dev/null || true; tail -20 "$OUT/origen/playground.log"; exit 1; fi
echo "== origen: respaldo $(du -h "$OUT/origen/probe/origen.zip" | cut -f1) en $(( $(date +%s) - begun )) s"

# Fase «red»: la red por subdirectorios con Chrome.
cp "$OUT/origen/probe/origen.zip" "$OUT/origen/probe/origen.json" "$D/network.php" "$D/probe.php" "$OUT/red/probe/"
head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n' > "$OUT/red/probe/token.txt"
cat > "$OUT/red/blueprint.json" <<JSON
{"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{"DIGITALISIMO_BACKUPS_STORAGE_DIR":"/digi-store","WP_ENVIRONMENT_TYPE":"local","DIGITALISIMO_BACKUPS_RESTORE_MU_PLUGINS":true,"AUTH_KEY":"red-auth-key-0123456789abcdef0123456789abcdef","SECURE_AUTH_KEY":"red-secure-auth-key-0123456789abcdef0123456789"}},
{"step":"enableMultisite"},
{"step":"runPHP","code":"<?php require '/digi-probe/network.php';"}]}
JSON
for try in $(seq "$PORT" $(( PORT + 40 ))); do fuser -n tcp "$try" >/dev/null 2>&1 || { PORT="$try"; break; }; done
if fuser -n tcp "$PORT" >/dev/null 2>&1; then echo "== red: no hay un puerto libre desde $PORT; usa PORT=…"; exit 1; fi
begun=$(date +%s)
$CLI server --port="$PORT" --workers=1 --site-url="http://red.test" --blueprint="$OUT/red/blueprint.json" \
  --mount="$OUT/red/backups:/wordpress/wp-content/plugins/digitalisimo-backups" \
  --mount="$OUT/red/probe:/digi-probe" \
  --mount="$OUT/red/store:/digi-store" > "$OUT/red/server.log" 2>&1 &
SERVER=$!
SERVER_PORT="$PORT"
ready=0
for i in $(seq 1 600); do
  sleep 3
  kill -0 "$SERVER" 2>/dev/null || break
  [ -f "$OUT/red/probe/setup-error.txt" ] && break
  if [ -s "$OUT/red/probe/red.json" ] && [ "$(curl -s -o /dev/null -w '%{http_code}' -m 120 -H 'Host: red.test' "http://127.0.0.1:$PORT/?rest_route=%2F")" = 200 ]; then ready=1; break; fi
done
if [ "$ready" != 1 ]; then echo "== red: no quedó lista"; cat "$OUT/red/probe/setup-error.txt" 2>/dev/null || true; tail -30 "$OUT/red/server.log"; exit 1; fi
echo "== red lista en $(( $(date +%s) - begun )) s (puerto $PORT)"
fail=0
node "$D/drive.mjs" "$PORT" "$OUT/red" "$OUT" || fail=1
if grep -qE 'Fatal error|Uncaught' "$OUT/red/server.log"; then echo "== red: errores de PHP en el servidor"; grep -E 'Fatal error|Uncaught' "$OUT/red/server.log" | head -5; fail=1; fi
cp "$OUT/red/server.log" "$OUT/server-red.log"
stop_server
rm -f "$OUT/red/probe/origen.zip" "$OUT/origen/probe/origen.zip"
[ $fail -eq 0 ] || { echo 'Ensayo «importar un individual en una red» con fallos'; exit 1; }
echo "Importar un WordPress individual en una red: validado en Playground con Chrome en $(( $(date +%s) - start )) s ($OUT/shots)."
