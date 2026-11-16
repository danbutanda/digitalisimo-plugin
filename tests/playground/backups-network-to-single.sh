#!/usr/bin/env bash
# «El respaldo de una red en un WordPress individual» (pedido del propietario del 2026-11-16) en WordPress Playground
# (SQLite), con Chrome sin interfaz y por la pantalla:
#   red      (run-blueprint, un proceso PHP por paso: backups-network-to-single/network.php) una red por subdirectorios en
#            red.test con el principal y los sitios /dos/ y /tres/: usuarios con roles distintos en cada uno, un plugin
#            activado en la red y uno propio de cada sitio, un tema por sitio (el del 2, hijo), una portada con un medio de
#            su carpeta; un respaldo completo de la red con el motor real y el inventario de los sitios 1 y 2.
#   destino  (server, destino.test: otra dirección) un WordPress individual con Backups. drive.mjs, con Chrome, sube el
#            respaldo de la red por «Subir un respaldo», lo restaura eligiendo el sitio 2 y después el principal, y
#            comprueba con huellas contenido, medios servidos desde uploads/, usuarios que entran con su contraseña,
#            plugins activos, que no quedó ninguna tabla de la red y que la portada carga sin errores; al final, una
#            restauración con una falla después del cambio vuelve sola a la copia previa (falla.php).
# Capturas en $OUT/shots y resultado en $OUT/result.txt.
# Uso: bash tests/playground/backups-network-to-single.sh   (en la PC, con pc-run.sh; una corrida pesada a la vez)
#   OUT, TMPDIR     resultados (por defecto /var/tmp/digi-bk/backups-network-to-single) y temporales de Playground
#   PORT            puerto del destino (por defecto 9631; si está ocupado, el siguiente libre)
#   PUPPETEER_PATH  carpeta node_modules con puppeteer-core (si no, se busca en la caché de npx o se instala 25.12.0)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
D="$ROOT/tests/playground/backups-network-to-single"
export OUT="${OUT:-/var/tmp/digi-bk/backups-network-to-single}"
export TMPDIR="${TMPDIR:-/var/tmp/digi-bk/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PORT="${PORT:-9631}"
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
rm -rf "$OUT/red" "$OUT/destino" "$OUT/shots" "$OUT/result.txt"
mkdir -p "$OUT/red/probe" "$OUT/red/store" "$OUT/destino/probe" "$OUT/destino/store" "$OUT/destino/web"
chmod 700 "$OUT/red/store" "$OUT/destino/store"
cp -a "$ROOT/digitalisimo-backups" "$OUT/red/backups"
cp -a "$ROOT/digitalisimo-backups" "$OUT/destino/backups"
cp "$D/network.php" "$D/falla.php" "$OUT/red/probe/"

# Fase «red».
steps=''
for step in setup backup; do steps="$steps,{\"step\":\"runPHP\",\"code\":\"<?php putenv('DIGI_STEP=$step'); require '/digi-probe/network.php';\"}"; done
cat > "$OUT/red/blueprint.json" <<JSON
{"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{"DIGITALISIMO_BACKUPS_STORAGE_DIR":"/digi-store","AUTH_KEY":"red-auth-key-0123456789abcdef0123456789abcdef","SECURE_AUTH_KEY":"red-secure-auth-key-0123456789abcdef0123456789"}},
{"step":"enableMultisite"}$steps]}
JSON
begun=$(date +%s)
$CLI run-blueprint --site-url=http://red.test --blueprint="$OUT/red/blueprint.json" \
  --mount="$OUT/red/backups:/wordpress/wp-content/plugins/digitalisimo-backups" \
  --mount="$OUT/red/probe:/digi-probe" \
  --mount="$OUT/red/store:/digi-store" > "$OUT/red/playground.log" 2>&1 || { echo "== red: Playground terminó con error"; tail -30 "$OUT/red/playground.log"; exit 1; }
echo "== red ($(( $(date +%s) - begun )) s)"
node -e '
const r = require(process.argv[1]); let bad = 0;
for (const [step, out] of Object.entries(r)) { for (const p of out.problems) { console.log(`FALLA · ${step}: ${p}`); bad++; } if (!out.problems.length) console.log(`ok · ${step} ${JSON.stringify(out.data).slice(0, 400)}`); }
for (const s of ["setup", "backup"]) if (!r[s]) { console.log(`FALLA · falta el paso ${s}`); bad++; }
process.exit(bad ? 1 : 0);' "$OUT/red/probe/result.json" || { cat "$OUT/red/probe/fatal.log" 2>/dev/null || true; exit 1; }
[ ! -s "$OUT/red/probe/fatal.log" ] || { cat "$OUT/red/probe/fatal.log"; exit 1; }
backup="$(node -e 'console.log(require(process.argv[1]).backup)' "$OUT/red/probe/state.json")"
cp "$OUT/red/store/$backup" "$OUT/red.zip"
cp "$OUT/red/probe/state.json" "$OUT/red/probe/inventory.json" "$D/falla.php" "$D/setup.php" "$OUT/destino/probe/"
cp "$D/state.php" "$OUT/destino/web/"
head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n' > "$OUT/destino/web/token.txt"
echo "· respaldo de la red $backup ($(du -h "$OUT/red.zip" | cut -f1))"
rm -rf "$OUT/red/backups"

# Fase «destino»: otro WordPress, otra dirección, otras claves de wp-config.php.
cat > "$OUT/destino/blueprint.json" <<JSON
{"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{"DIGITALISIMO_BACKUPS_STORAGE_DIR":"/digi-store","WP_ENVIRONMENT_TYPE":"local","DIGITALISIMO_BACKUPS_RESTORE_MU_PLUGINS":true,"AUTH_KEY":"destino-auth-key-0123456789abcdef0123456789abcdef","SECURE_AUTH_KEY":"destino-secure-auth-key-0123456789abcdef0123456789"}},
{"step":"runPHP","code":"<?php require '/digi-probe/setup.php';"}]}
JSON
for try in $(seq "$PORT" $(( PORT + 40 ))); do fuser -n tcp "$try" >/dev/null 2>&1 || { PORT="$try"; break; }; done
if fuser -n tcp "$PORT" >/dev/null 2>&1; then echo "== destino: no hay un puerto libre desde $PORT; usa PORT=…"; exit 1; fi
begun=$(date +%s)
$CLI server --port="$PORT" --workers=1 --site-url="http://destino.test" --blueprint="$OUT/destino/blueprint.json" \
  --mount="$OUT/destino/backups:/wordpress/wp-content/plugins/digitalisimo-backups" \
  --mount="$OUT/destino/web:/wordpress/wp-content/digi-web" \
  --mount="$OUT/destino/probe:/digi-probe" \
  --mount="$OUT/destino/store:/digi-store" > "$OUT/destino/server.log" 2>&1 &
SERVER=$!
SERVER_PORT="$PORT"
ready=0
for i in $(seq 1 400); do
  sleep 3
  kill -0 "$SERVER" 2>/dev/null || break
  [ -f "$OUT/destino/probe/setup-error.txt" ] && break
  if [ -s "$OUT/destino/probe/setup.json" ] && [ "$(curl -s -o /dev/null -w '%{http_code}' -m 60 -H 'Host: destino.test' "http://127.0.0.1:$PORT/?rest_route=%2F")" = 200 ]; then ready=1; break; fi
done
if [ "$ready" != 1 ]; then
  echo "== destino: no quedó listo"; cat "$OUT/destino/probe/setup-error.txt" 2>/dev/null || true; tail -30 "$OUT/destino/server.log"; exit 1
fi
echo "== destino listo en $(( $(date +%s) - begun )) s (puerto $PORT)"
fail=0
node "$D/drive.mjs" "$PORT" destino.test "$OUT/destino" "$OUT" "$OUT/red.zip" "$OUT/destino/probe/inventory.json" || fail=1
if grep -qE 'Fatal error|Uncaught' "$OUT/destino/server.log"; then echo "== destino: errores de PHP en el servidor"; grep -E 'Fatal error|Uncaught' "$OUT/destino/server.log" | grep -v 'falla simulada' | head -5; grep -E 'Fatal error|Uncaught' "$OUT/destino/server.log" | grep -qv 'falla simulada' && fail=1; fi
cp "$OUT/destino/server.log" "$OUT/server-destino.log"
stop_server
echo "$(sha256sum "$OUT/red.zip" | cut -d' ' -f1) $(stat -c %s "$OUT/red.zip") red.zip" > "$OUT/archivos.txt"
# Los almacenes (respaldos de 40 MB, copias previas, trabajos) no hacen falta después: quedan su resultado y las capturas.
rm -rf "$OUT/destino/backups" "$OUT/red.zip" "$OUT/destino/store" "$OUT/red/store"
[ $fail -eq 0 ] || { echo 'Ensayo «el respaldo de una red en un WordPress individual» con fallos'; exit 1; }
echo "El respaldo de una red en un WordPress individual: validado en Playground con Chrome en $(( $(date +%s) - start )) s ($OUT/shots)."
