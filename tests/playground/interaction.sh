#!/usr/bin/env bash
# Interacción en navegador real: arranca Playground en modo servidor (sitio simple, sin Element Pack),
# crea una página por widget heredado y comprueba con Chrome que el acordeón, las pestañas, el deslizador
# propio, el carrusel de medios y el panel lateral responden al pulsar, sin errores de JavaScript ni recursos 404.
# Uso: bash tests/playground/interaction.sh   (PUPPETEER_PATH apunta al node_modules con puppeteer-core)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
OUT="${OUT:-/tmp/digi-interaction}"
PORT="${PORT:-9431}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PUPPETEER_PATH="${PUPPETEER_PATH:-$(ls -d "$HOME"/.npm/_npx/*/node_modules 2>/dev/null | while read -r d; do [ -d "$d/puppeteer-core" ] && echo "$d" && break; done)}"
[ -n "$PUPPETEER_PATH" ] || { echo "No se encontró puppeteer-core: exportar PUPPETEER_PATH"; exit 1; }
mkdir -p "$OUT" && rm -f "$OUT"/*.png "$OUT/interaction.json"
cp "$ROOT/tests/playground/interaction/cases.json" "$OUT/cases.json"
cp "$ROOT/tests/playground/interaction/setup.php" "$OUT/setup.php"
cat > "$OUT/blueprint.json" <<'JSON'
{"steps":[{"step":"installPlugin","pluginData":{"resource":"wordpress.org/plugins","slug":"elementor"},"options":{"activate":true}},
{"step":"runPHP","code":"<?php require '/wordpress/wp-content/probe/setup.php';"}]}
JSON
# El servidor anterior puede seguir escuchando (el proceso hijo de npx sobrevive a su padre): se libera el puerto.
fuser -k -n tcp "$PORT" >/dev/null 2>&1 || true
sleep 1
$CLI server --port="$PORT" --site-url="http://127.0.0.1:$PORT" --blueprint="$OUT/blueprint.json" \
  --mount="$ROOT/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements" \
  --mount="$OUT:/wordpress/wp-content/probe" > "$OUT/server.log" 2>&1 &
SERVER=$!
stop_server() { kill $SERVER 2>/dev/null || true; for i in 1 2 3; do fuser -k -n tcp "$PORT" >/dev/null 2>&1 || break; sleep 1; done; }
trap stop_server EXIT
for i in $(seq 1 200); do
  sleep 3
  if ! kill -0 $SERVER 2>/dev/null; then tail -20 "$OUT/server.log"; exit 1; fi
  curl -s -m 20 -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/digi-ix-bdt-accordion/" 2>/dev/null | grep -q '^200$' && break
done
curl -s -m 20 -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/digi-ix-bdt-accordion/" | grep -q '^200$' || { echo 'El servidor no sirvió las páginas de prueba'; tail -20 "$OUT/server.log"; exit 1; }
OUT="$OUT" NODE_PATH="$PUPPETEER_PATH" node "$ROOT/tests/playground/interaction/run.cjs" "http://127.0.0.1:$PORT"
