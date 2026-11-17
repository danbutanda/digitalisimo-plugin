#!/usr/bin/env bash
# Subir e importar un respaldo externo con el mismo panel de avance que el respaldo (pedido del propietario del
# 2026-11-16) en WordPress Playground (SQLite), con Chrome sin interfaz a 1 280 y 390 px y todo por la pantalla (ver
# backups-upload-progress/drive.mjs):
#   1  «Respaldar ahora» deja un respaldo de varias decenas de MB; se saca del almacén y se borra de la lista;
#   2  «Subir un respaldo» lo sube con los fragmentos frenados (el laboratorio espera en cada uno): el panel, en la
#      sección, pinta «Subida» con bytes, velocidad y tiempo restante; a mitad se recarga la página (el panel dice «Subida
#      en pausa»), se elige el mismo archivo y sigue desde lo recibido; la «Comprobación» (frenada al empezar) es
#      indeterminada y se recarga otra vez a mitad: el panel vuelve a ella y la sigue; termina en «Respaldo subido y
#      verificado» con su fila, y «Restaurarlo» abre esa fila en la lista;
#   3  un respaldo de Backuply (sintético, con el formato de Backuply 1.6.0, backups-upload-progress/backuply.php) se
#      importa desde su carpeta y otro se sube y se importa con «Importarlo ahora»: los dos se siguen en el mismo panel,
#      en la sección «Importar», con sus fases y su resultado.
# La barra nunca retrocede; sin errores de PHP ni de JavaScript. Capturas en $OUT/shots y cada estado del panel en
# $OUT/samples.jsonl; el resultado de cada comprobación en $OUT/result.txt.
# Uso: bash tests/playground/backups-upload-progress.sh   (en la PC, con pc-run.sh; una corrida pesada a la vez)
#   OUT, TMPDIR     resultados (por defecto /var/tmp/digi-bk/backups-upload-progress) y temporales de Playground
#   PORT            puerto (por defecto 9641)
#   WORKERS         procesos de PHP de Playground (por defecto 2: con uno solo, la página no carga mientras el servidor
#                   comprueba la subida y no se puede recargar a media comprobación)
#   PUPPETEER_PATH  carpeta node_modules con puppeteer-core (si no, se busca en la caché de npx o se instala 25.12.0)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
D="$ROOT/tests/playground/backups-upload-progress"
export OUT="${OUT:-/var/tmp/digi-bk/backups-upload-progress}"
export TMPDIR="${TMPDIR:-/var/tmp/digi-bk/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PORT="${PORT:-9641}"
WORKERS="${WORKERS:-2}"
SERVER=''
stop_server() {
  [ -n "$SERVER" ] && kill "$SERVER" 2>/dev/null || true
  for i in 1 2 3; do fuser -k -n tcp "$PORT" >/dev/null 2>&1 || break; sleep 1; done
  SERVER=''
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
dir="$OUT/sitio"
host="subida.test"
rm -rf "$dir" "$OUT/shots" "$OUT/result.txt" "$OUT/samples.jsonl"
mkdir -p "$dir/probe" "$dir/store" "$dir/ctl" "$dir/backuply" "$OUT/shots"
chmod 700 "$dir/store"
cp -a "$ROOT/digitalisimo-backups" "$dir/plugin"
cp "$D/setup.php" "$D/lab.php" "$dir/probe/"
head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n' > "$dir/probe/token.txt"
# Los respaldos de Backuply del ensayo: uno en su carpeta del sitio y otro para subirlo (otro nombre).
"${PHP_BIN:-php}" "$D/backuply.php" "$dir/backuply" "$OUT"
cat > "$dir/blueprint.json" <<JSON
{"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{"DIGITALISIMO_BACKUPS_STORAGE_DIR":"/digi-store","WP_ENVIRONMENT_TYPE":"local"}},
{"step":"runPHP","code":"<?php require '/digi-probe/setup.php';"}]}
JSON
if fuser -n tcp "$PORT" >/dev/null 2>&1; then echo "el puerto $PORT está ocupado; usa PORT=…"; exit 1; fi
begun=$(date +%s)
$CLI server --port="$PORT" --workers="$WORKERS" --site-url="http://$host" --blueprint="$dir/blueprint.json" \
  --mount="$dir/plugin:/wordpress/wp-content/plugins/digitalisimo-backups" \
  --mount="$dir/probe:/digi-probe" \
  --mount="$dir/ctl:/digi-ctl" \
  --mount="$dir/store:/digi-store" \
  --mount="$dir/backuply:/wordpress/wp-content/backuply" > "$dir/server.log" 2>&1 &
SERVER=$!
ready=0
for i in $(seq 1 400); do
  sleep 3
  kill -0 "$SERVER" 2>/dev/null || break
  [ -f "$dir/probe/setup-error.txt" ] && break
  if [ -s "$dir/probe/setup.json" ] && [ "$(curl -s -o /dev/null -w '%{http_code}' -m 60 -H "Host: $host" "http://127.0.0.1:$PORT/?rest_route=%2F")" = 200 ]; then ready=1; break; fi
done
if [ "$ready" != 1 ]; then echo "el sitio no quedó listo"; cat "$dir/probe/setup-error.txt" 2>/dev/null || true; tail -20 "$dir/server.log"; exit 1; fi
echo "== sitio listo en $(( $(date +%s) - begun )) s (puerto $PORT, $WORKERS procesos de PHP)"
fail=0
node "$D/drive.mjs" "$PORT" "$host" "$dir" "$OUT" || fail=1
if grep -E 'Fatal error|Uncaught' "$dir/server.log" | head -5 | grep -q .; then echo "== errores de PHP en el servidor"; grep -E 'Fatal error|Uncaught' "$dir/server.log" | head -5; fail=1; fi
cp "$dir/server.log" "$OUT/server.log"
stop_server; sleep 2
# Los respaldos del ensayo no hacen falta después: quedan su tamaño y su SHA-256.
for file in "$OUT"/*.zip "$OUT"/*.tar.gz; do [ -f "$file" ] && echo "$(sha256sum "$file" | cut -d' ' -f1) $(stat -c %s "$file") $(basename "$file")" >> "$OUT/archivos.txt" && rm -f "$file"; done
rm -rf "$dir"
[ $fail -eq 0 ] || { echo 'Ensayo «subir e importar con el panel de avance» con fallos'; exit 1; }
echo "Subir e importar con el panel de avance: validado en Playground con Chrome en $(( $(date +%s) - start )) s ($OUT/shots)."
