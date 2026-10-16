#!/usr/bin/env bash
# «Migrar toda la red» de punta a punta en Chrome real: red de Playground con Elementor (plugin) activado en toda la
# red y Element Pack activado en cada sitio; se firma cada página, se pulsa el único botón de Red → Ajustes → Migrar a
# DIGITALÍSIMO Elements y se comprueba que cada sitio queda sin esos plugins, sobre el núcleo incluido, con el contenido
# de Element Pack y los alias convertidos (salvo el alias cuyo CSS del sitio lo impide) y con las páginas iguales.
# Requiere la copia local de referencia de Element Pack (EP_DIR, por defecto reference/bdthemes-element-pack/).
# Uso: bash tests/playground/migrate.sh   → resultados en $OUT (por defecto /tmp/digi-migrate)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
. "$ROOT/tests/playground/playground-cleanup.sh"
OUT="${OUT:-/tmp/digi-migrate}"
PORT="${PORT:-9445}"
EP_DIR="${EP_DIR:-$ROOT/reference/bdthemes-element-pack}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PUPPETEER_PATH="${PUPPETEER_PATH:-$(ls -d "$HOME"/.npm/_npx/*/node_modules 2>/dev/null | while read -r d; do [ -d "$d/puppeteer-core" ] && echo "$d" && break; done)}"
[ -n "$PUPPETEER_PATH" ] || { echo "No se encontró puppeteer-core: exportar PUPPETEER_PATH"; exit 1; }
[ -f "$EP_DIR/bdthemes-element-pack.php" ] || { echo "Falta la copia de referencia de Element Pack en $EP_DIR"; exit 1; }
mkdir -p "$OUT" && rm -f "$OUT"/*.json "$OUT/setup-error.txt" "$OUT/fatal.log"
cp "$ROOT/tests/playground/migrate/setup.php" "$OUT/setup.php"
# Una red no admite puertos en su dirección: el sitio es http://playground.test y Chrome lo resuelve al puerto local.
cat > "$OUT/blueprint.json" <<'JSON'
{"steps":[{"step":"enableMultisite"},
{"step":"installPlugin","pluginData":{"resource":"wordpress.org/plugins","slug":"elementor"},"options":{"activate":false}},
{"step":"runPHP","code":"<?php require '/wordpress/wp-content/probe/setup.php';"}]}
JSON
fuser -k -n tcp "$PORT" >/dev/null 2>&1 || true
sleep 1
# Un solo proceso: con varios, Playground deja sin servir archivos que Elementor vuelve a generar tras vaciar su caché
# (pasa igual con Elementor solo), y la comparación de páginas mediría eso en lugar de la migración.
$CLI server --port="$PORT" --site-url="http://playground.test" --workers=1 --blueprint="$OUT/blueprint.json" \
  --mount="$ROOT/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements" \
  --mount="$ROOT/digitalisimo-migration:/wordpress/wp-content/plugins/digitalisimo-migration" \
  --mount="$EP_DIR:/wordpress/wp-content/plugins/bdthemes-element-pack" \
  --mount="$ROOT/tests/legacy:/wordpress/wp-content/digi-legacy-fixtures" \
  --mount="$OUT:/wordpress/wp-content/probe" > "$OUT/server.log" 2>&1 &
SERVER=$!
stop_server() { kill $SERVER 2>/dev/null || true; for i in 1 2 3; do fuser -k -n tcp "$PORT" >/dev/null 2>&1 || break; sleep 1; done; }
# Element Pack copia sus máscaras (shape-N) y separadores (ep-*) dentro de la carpeta del Elementor que manda. Mientras
# sigue activo en un sitio y el núcleo incluido ya manda, esa carpeta es la del repositorio: se borran al terminar (sólo
# esos nombres y sólo si git no los versiona), como en legacy-ab.sh.
trap 'stop_server; git -C "$ROOT" clean -fq -- "digitalisimo-elements/elementor/assets/mask-shapes/shape-*.svg" "digitalisimo-elements/elementor/assets/shapes/ep-*.svg" >/dev/null 2>&1 || true; playground_cleanup' EXIT
for i in $(seq 1 200); do
  sleep 3
  if ! kill -0 $SERVER 2>/dev/null; then tail -20 "$OUT/server.log"; exit 1; fi
  [ -f "$OUT/pages.json" ] && curl -s -m 30 -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/sub/digi-mg-accordion/" 2>/dev/null | grep -q '^200$' && break
done
[ -f "$OUT/pages.json" ] || { echo 'La red de prueba no quedó preparada'; tail -20 "$OUT/server.log"; exit 1; }
[ -f "$OUT/setup-error.txt" ] && { echo "Preparación incompleta:"; cat "$OUT/setup-error.txt"; exit 1; }
status=0
OUT="$OUT" NODE_PATH="$PUPPETEER_PATH" node "$ROOT/tests/playground/migrate/run.cjs" "$PORT" || status=$?
if [ -s "$OUT/fatal.log" ]; then echo "Errores fatales de PHP:"; cat "$OUT/fatal.log"; status=1; fi
exit $status
