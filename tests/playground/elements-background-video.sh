#!/usr/bin/env bash
# Vídeos de fondo de DIGITALÍSIMO Elements en WordPress Playground y Chrome (auditoría de digitalisimo.lat, 2026-11-24:
# la cabecera bajaba un MP4 de 8,1 MB también en el celular). setup.php sube un MP4 de 3 MB y una imagen de respaldo y
# crea tres páginas con un contenedor con fondo de vídeo; el conductor elements-background-video/run.cjs, como visitante
# a 390 px (celular) y a 1 280 px, cuenta las peticiones al MP4 con cada política del sitio («lo decide cada elemento»,
# «nunca en el celular», «también en el celular») y comprueba en el editor el aviso de peso (3 MB > 2 MB), si se
# descarga en el celular y si falta la imagen de respaldo. Detalle en run.cjs.
# Uso: bash tests/playground/elements-background-video.sh   (en la PC: bash /var/tmp/digi-wt/pc-run.sh --traer-a <dir> <worktree> -- bash tests/playground/elements-background-video.sh)
#   PHP 8.2 (o PHP=7.4) · OUT /var/tmp/digi-bv · PORT 9811 (bloque 9810–9819) · ELEMENTS_DIR otra copia de Elements
# Deja $OUT/result.txt y capturas; sale 0 si todo cuadra.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
D="$ROOT/tests/playground/elements-background-video"
export OUT="${OUT:-/var/tmp/digi-bv}"
export TMPDIR="${TMPDIR:-$OUT/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PHPV="${PHP:-8.2}"
PORT="${PORT:-9811}"
case "$PORT" in 981[0-9]) ;; *) echo "elements-background-video.sh usa el bloque 9810–9819: $PORT es de otro arnés"; exit 1;; esac
PUPPETEER_PATH="${PUPPETEER_PATH:-$(ls -d "$HOME"/.npm/_npx/*/node_modules 2>/dev/null | while read -r d; do [ -d "$d/puppeteer-core" ] && echo "$d" && break; done || true)}"
if [ -z "$PUPPETEER_PATH" ]; then
  mkdir -p "$TMPDIR/pptr"
  npm install --silent --no-audit --no-fund --prefix "$TMPDIR/pptr" puppeteer-core@25.12.0 >/dev/null 2>&1 && PUPPETEER_PATH="$TMPDIR/pptr/node_modules"
fi
[ -n "$PUPPETEER_PATH" ] || { echo "No se encontró puppeteer-core: exportar PUPPETEER_PATH"; exit 1; }
SERVER=''
stop_server() {
  [ -n "$SERVER" ] && kill "$SERVER" 2>/dev/null || true
  for i in 1 2 3; do fuser -k -n tcp "$PORT" >/dev/null 2>&1 || break; sleep 1; done
  SERVER=''
}
trap 'stop_server; playground_cleanup' EXIT
trap 'exit 130' INT TERM

host="127.0.0.1:$PORT"
dir="$OUT"
rm -f "$dir/ready.json" "$dir/setup-error.txt" "$dir/fatal.log" "$dir/result.txt"
cat > "$dir/blueprint.json" <<JSON
{"preferredVersions":{"php":"$PHPV","wp":"latest"},"steps":[{"step":"runPHP","code":"<?php require '/digi-bv-src/setup.php';"}]}
JSON
if fuser -n tcp "$PORT" >/dev/null 2>&1; then echo "El puerto $PORT está ocupado: usa PORT=…"; exit 1; fi
begun=$(date +%s)
$CLI server --php="$PHPV" --port="$PORT" --workers=1 --site-url="http://$host" --blueprint="$dir/blueprint.json" \
  --mount="${ELEMENTS_DIR:-$ROOT/digitalisimo-elements}:/wordpress/wp-content/plugins/digitalisimo-elements" \
  --mount="$D/probe.php:/wordpress/wp-content/mu-plugins/digi-bv-probe.php" \
  --mount="$D:/digi-bv-src" --mount="$dir:/digi-bv" > "$dir/server.log" 2>&1 &
SERVER=$!
ok=0
for i in $(seq 1 300); do
  sleep 3
  kill -0 "$SERVER" 2>/dev/null || break
  [ -f "$dir/setup-error.txt" ] && break
  if [ -s "$dir/ready.json" ] && [ "$(curl -s -o /dev/null -w '%{http_code}' -m 60 "http://$host/wp-login.php")" = 200 ]; then ok=1; break; fi
done
if [ "$ok" != 1 ]; then
  echo "El sitio no quedó listo"; cat "$dir/setup-error.txt" 2>/dev/null || true; tail -20 "$dir/server.log"; exit 1
fi
echo "== listo en $(( $(date +%s) - begun )) s · $(cat "$dir/ready.json")"
status=0
NODE_PATH="$PUPPETEER_PATH" node "$D/run.cjs" "$PORT" "$host" "$dir" || status=1
[ -s "$dir/fatal.log" ] && { echo "Errores fatales:"; cat "$dir/fatal.log"; status=1; }
if grep -qE 'PHP Fatal error|Uncaught' "$dir/server.log"; then echo "Errores de PHP en el servidor:"; grep -E 'PHP Fatal error|Uncaught' "$dir/server.log" | head -5; status=1; fi
exit $status
