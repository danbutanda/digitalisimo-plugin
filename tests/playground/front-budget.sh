#!/usr/bin/env bash
# Presupuesto del front de DIGITALÍSIMO Elements: arranca Playground en modo servidor con el núcleo incluido (sin el
# plugin Elementor ni Element Pack), crea una página por widget propio con ajustes representativos, una página vacía de
# referencia (sólo un encabezado) y una con widgets PRO pesados, y mide en Chrome cada página: bytes por tipo y por
# origen (propio o de terceros), peticiones, jQuery, paquetes de PRO, Swiper y Lottie, handles de Elements, CLS, tareas
# largas, nodos, imágenes sin dimensiones o sin srcset, iframes sin diferir y errores. El coste de cada widget es la
# diferencia con la página vacía.
# Uso: bash tests/playground/front-budget.sh            compara con tests/playground/front-budget/baseline.json y falla si empeora
#      BASELINE=write bash tests/playground/front-budget.sh   guarda la medición como nueva línea base
#      LIGHTHOUSE=1 ...                                        añade Lighthouse móvil local a las páginas de referencia
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
. "$ROOT/tests/playground/playground-cleanup.sh"
OUT="${OUT:-/tmp/digi-front-budget}"
PORT="${PORT:-9441}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PUPPETEER_PATH="${PUPPETEER_PATH:-$(ls -d "$HOME"/.npm/_npx/*/node_modules 2>/dev/null | while read -r d; do [ -d "$d/puppeteer-core" ] && echo "$d" && break; done)}"
[ -n "$PUPPETEER_PATH" ] || { echo "No se encontró puppeteer-core: exportar PUPPETEER_PATH"; exit 1; }
mkdir -p "$OUT" && rm -f "$OUT"/*.png "$OUT/front-budget.json" "$OUT/setup-error.txt" "$OUT/fatal.log"
cp "$ROOT/tests/playground/front-budget/cases.json" "$OUT/cases.json"
cp "$ROOT/tests/playground/front-budget/setup.php" "$OUT/setup.php"
cat > "$OUT/blueprint.json" <<JSON
{"steps":[{"step":"runPHP","code":"<?php require '/wordpress/wp-content/probe/setup.php';"}]}
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
  curl -s -m 20 -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/digi-fb-empty/" 2>/dev/null | grep -q '^200$' && break
done
curl -s -m 20 -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/digi-fb-empty/" | grep -q '^200$' || { echo 'El servidor no sirvió las páginas de prueba'; tail -20 "$OUT/server.log"; exit 1; }
[ -f "$OUT/setup-error.txt" ] && { echo "Preparación incompleta:"; cat "$OUT/setup-error.txt"; exit 1; }
OUT="$OUT" BASELINE="${BASELINE:-}" LIGHTHOUSE="${LIGHTHOUSE:-}" BASELINE_FILE="$ROOT/tests/playground/front-budget/baseline.json" NODE_PATH="$PUPPETEER_PATH" node "$ROOT/tests/playground/front-budget/run.cjs" "http://127.0.0.1:$PORT"
