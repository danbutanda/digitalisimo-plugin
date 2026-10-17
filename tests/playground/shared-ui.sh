#!/usr/bin/env bash
# Diseño compartido en navegador: los siete plugins montados en un sitio y, por petición, sólo el conjunto que se
# prueba activo (cada plugin solo y todos juntos). En cada pantalla propia comprueba que haya una sola hoja
# compartida, la clase de body, tokens válidos, el botón primario con el degradado propio y ningún color de la
# paleta del constructor original. Capturas y detalle en $OUT.
# Uso: bash tests/playground/shared-ui.sh
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
. "$ROOT/tests/playground/playground-cleanup.sh"
OUT="${OUT:-/tmp/digi-shared-ui}"
PORT="${PORT:-9448}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PUPPETEER_PATH="${PUPPETEER_PATH:-$(ls -d "$HOME"/.npm/_npx/*/node_modules 2>/dev/null | while read -r d; do [ -d "$d/puppeteer-core" ] && echo "$d" && break; done)}"
[ -n "$PUPPETEER_PATH" ] || { echo "No se encontró puppeteer-core: exportar PUPPETEER_PATH"; exit 1; }
mkdir -p "$OUT" && rm -f "$OUT"/*.png "$OUT/shared-ui.json" "$OUT/setup-error.txt" "$OUT/fatal.log"
cp "$ROOT/tests/playground/shared-ui/setup.php" "$OUT/setup.php"
cat > "$OUT/blueprint.json" <<JSON
{"steps":[{"step":"setSiteLanguage","language":"es_MX"},
{"step":"runPHP","code":"<?php require '/wordpress/wp-content/probe/setup.php';"}]}
JSON
MOUNTS=()
for dir in digitalisimo-seo digitalisimo.chatbot digitalisimo-hosting digitalisimo-backups digitalisimo-tools digitalisimo-elements digitalisimo-migration; do
  MOUNTS+=("--mount=$ROOT/$dir:/wordpress/wp-content/plugins/$dir")
done
fuser -k -n tcp "$PORT" >/dev/null 2>&1 || true
sleep 1
$CLI server --port="$PORT" --workers=1 --site-url="http://127.0.0.1:$PORT" --blueprint="$OUT/blueprint.json" \
  "${MOUNTS[@]}" --mount="$OUT:/wordpress/wp-content/probe" > "$OUT/server.log" 2>&1 &
SERVER=$!
stop_server() { kill $SERVER 2>/dev/null || true; for i in 1 2 3; do fuser -k -n tcp "$PORT" >/dev/null 2>&1 || break; sleep 1; done; }
trap 'stop_server; playground_cleanup' EXIT
for i in $(seq 1 200); do
  sleep 3
  if ! kill -0 $SERVER 2>/dev/null; then tail -20 "$OUT/server.log"; exit 1; fi
  curl -s -m 20 -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/wp-login.php" 2>/dev/null | grep -q '^200$' && [ -f "$OUT/ready.txt" ] && break
done
[ -f "$OUT/setup-error.txt" ] && { echo "Preparación incompleta:"; cat "$OUT/setup-error.txt"; exit 1; }
[ -f "$OUT/ready.txt" ] || { echo 'El sitio no terminó de prepararse'; tail -20 "$OUT/server.log"; exit 1; }
status=0
OUT="$OUT" NODE_PATH="$PUPPETEER_PATH" node "$ROOT/tests/playground/shared-ui/run.cjs" "http://127.0.0.1:$PORT" || status=$?
[ -s "$OUT/fatal.log" ] && { echo "Errores fatales:"; cat "$OUT/fatal.log"; status=1; }
exit $status
