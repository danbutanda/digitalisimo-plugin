#!/usr/bin/env bash
# Slider Optimizado en navegador real: Playground en modo servidor con el núcleo incluido y una página con cuatro
# instancias (onda de diez copias con espejo, doce logotipos, onda repetida con espejo y una lejana tras 3000 px).
# Chrome comprueba, en escritorio y en móvil, que no hay errores ni recursos 404, que las instancias cercanas arrancan
# pronto y la lejana al llegar, que cada pista se recorta a lo que cubre el viewport más un elemento (par con espejo)
# conservando la velocidad, que el cierre del bucle no deja hueco, que el movimiento no pierde fotogramas, que al pasar
# a tableta vuelven los elementos apartados, y que sin JavaScript el movimiento arranca solo a los tres segundos.
# Uso: bash tests/playground/slider.sh   (PUPPETEER_PATH apunta al node_modules con puppeteer-core)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
. "$ROOT/tests/playground/playground-cleanup.sh"
OUT="${OUT:-/tmp/digi-slider}"
PORT="${PORT:-9451}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PUPPETEER_PATH="${PUPPETEER_PATH:-$(ls -d "$HOME"/.npm/_npx/*/node_modules 2>/dev/null | while read -r d; do [ -d "$d/puppeteer-core" ] && echo "$d" && break; done)}"
[ -n "$PUPPETEER_PATH" ] || { echo "No se encontró puppeteer-core: exportar PUPPETEER_PATH"; exit 1; }
mkdir -p "$OUT" && rm -f "$OUT"/*.png "$OUT/slider.json" "$OUT/setup-error.txt"
cp "$ROOT/tests/playground/slider/setup.php" "$OUT/setup.php"
cat > "$OUT/blueprint.json" <<'JSON'
{"steps":[
{"step":"runPHP","code":"<?php require '/wordpress/wp-load.php'; require_once ABSPATH . 'wp-admin/includes/plugin.php'; activate_plugin( 'digitalisimo-elements/pro-elements.php' );"},
{"step":"runPHP","code":"<?php require '/wordpress/wp-content/probe/setup.php';"}]}
JSON
fuser -k -n tcp "$PORT" >/dev/null 2>&1 || true
sleep 1
$CLI server --port="$PORT" --site-url="http://127.0.0.1:$PORT" --blueprint="$OUT/blueprint.json" \
  --mount="$ROOT/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements" \
  --mount="$OUT:/wordpress/wp-content/probe" > "$OUT/server.log" 2>&1 &
SERVER=$!
stop_server() { kill $SERVER 2>/dev/null || true; for i in 1 2 3; do fuser -k -n tcp "$PORT" >/dev/null 2>&1 || break; sleep 1; done; }
trap 'stop_server; playground_cleanup' EXIT
for i in $(seq 1 200); do
  sleep 3
  if ! kill -0 $SERVER 2>/dev/null; then tail -20 "$OUT/server.log"; exit 1; fi
  curl -s -m 20 -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/digi-slider/" 2>/dev/null | grep -q '^200$' && break
done
curl -s -m 20 -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/digi-slider/" | grep -q '^200$' || { echo 'El servidor no sirvió la página de prueba'; tail -20 "$OUT/server.log"; exit 1; }
[ -f "$OUT/setup-error.txt" ] && { echo "Preparación incompleta:"; cat "$OUT/setup-error.txt"; exit 1; }
OUT="$OUT" NODE_PATH="$PUPPETEER_PATH" node "$ROOT/tests/playground/slider/run.cjs" "http://127.0.0.1:$PORT"
