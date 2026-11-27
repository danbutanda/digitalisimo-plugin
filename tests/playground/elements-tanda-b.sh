#!/usr/bin/env bash
# La tanda B de DIGITALÍSIMO Elements (auditoría de digitalisimo.lat, 2026-11-24) de punta a punta, en WordPress
# Playground con Chrome: WooCommerce 11.2.1 fijado (tests/playground/woocommerce-pin.sh), DIGITALÍSIMO SEO, el tema
# DIGITALÍSIMO, el MCP del sitio y los elementos atómicos activos. Casos (elements-tanda-b/run.cjs):
#   G  plantillas globales pesadas (B1): cálculo en WP-Cron (nunca en el frente) y avisos en «Rendimiento», en el editor
#      y en el «Análisis SEO»;
#   H  un solo H1 en la ficha de un producto real (B2), opcional por elemento;
#   M  marquesina atómica por el MCP real (C1), su aviso cuando un estilo trae `animation`, y en Chrome el movimiento,
#      el botón de pausa y «reducir movimiento».
# Uso: bash tests/playground/elements-tanda-b.sh   (en la PC: bash /var/tmp/digi-wt/pc-run.sh <worktree> -- bash tests/playground/elements-tanda-b.sh)
#   PHP            versión de PHP de Playground (por defecto 8.2)
#   OUT, TMPDIR    resultados (por defecto /var/tmp/digi-tanda-b) y temporales de Playground
#   PORT           puerto (9831); el bloque 9830–9839 es de este arnés
#   WOO_ZIP        WooCommerce 11.2.1 (obligatorio: sin la copia o con otra suma, se detiene)
#   ELEMENTS_DIR   DIGITALÍSIMO Elements a probar (por defecto digitalisimo-elements/ del repositorio; se copia fijo)
# Deja $OUT/result.txt, result.json y summary.json. Al terminar borra la contraseña de aplicación y las copias.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
D="$ROOT/tests/playground/elements-tanda-b"
export OUT="${OUT:-/var/tmp/digi-tanda-b}"
export TMPDIR="${TMPDIR:-/var/tmp/digi-tanda-b/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PHPV="${PHP:-8.2}"
PORT="${PORT:-9831}"
case "$PORT" in 983[0-9]) ;; *) echo "elements-tanda-b.sh usa el bloque 9830–9839: $PORT es de otro arnés"; exit 1;; esac
ELEMENTS_DIR="${ELEMENTS_DIR:-$ROOT/digitalisimo-elements}"
[ -f "$ELEMENTS_DIR/pro-elements.php" ] || { echo "ELEMENTS_DIR no contiene pro-elements.php: $ELEMENTS_DIR"; exit 1; }
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
scrub() { rm -rf "$OUT"/probe/secrets.json "$OUT"/probe/token.txt "$OUT/src" "$OUT/woocommerce"; }
trap 'stop_server; scrub; playground_cleanup' EXIT
trap 'exit 130' INT TERM

. "$ROOT/tests/playground/woocommerce-pin.sh"
WOO_REQUIRE=1 woo_pin "$OUT/woocommerce" "$OUT/woocommerce.json" || exit 1
rm -rf "$OUT/src" && mkdir -p "$OUT/src"
cp -a "$ELEMENTS_DIR" "$OUT/src/digitalisimo-elements"
cp -a "$ROOT/digitalisimo-seo" "$ROOT/digitalisimo-theme" "$OUT/src/"
ELEMENTS_VERSION="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([0-9.]*\).*/\1/p' "$OUT/src/digitalisimo-elements/pro-elements.php" | head -1)"

start=$(date +%s)
dir="$OUT"
rm -rf "$dir/probe" "$dir/result.txt" "$dir/result.json"; mkdir -p "$dir/probe"
cp "$D/setup.php" "$D/probe.php" "$dir/probe/"
head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n' > "$dir/probe/token.txt"
host="127.0.0.1:$PORT"
cat > "$dir/blueprint.json" <<JSON
{"preferredVersions":{"php":"$PHPV","wp":"latest"},"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{"WP_ENVIRONMENT_TYPE":"local","DISABLE_WP_CRON":true}},
{"step":"runPHP","code":"<?php require '/digi-probe/setup.php';"}]}
JSON
if fuser -n tcp "$PORT" >/dev/null 2>&1; then echo "El puerto $PORT está ocupado por otro proceso; usa PORT=…"; exit 1; fi
$CLI server --php="$PHPV" --port="$PORT" --workers=1 --site-url="http://$host" --blueprint="$dir/blueprint.json" \
  --mount="$OUT/src/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements" \
  --mount="$OUT/src/digitalisimo-seo:/wordpress/wp-content/plugins/digitalisimo-seo" \
  --mount="$OUT/src/digitalisimo-theme:/wordpress/wp-content/themes/digitalisimo-theme" \
  --mount="$dir/probe:/digi-probe" \
  "$(woo_mount)" > "$dir/server.log" 2>&1 &
SERVER=$!
ready=0
for i in $(seq 1 400); do
  sleep 3
  kill -0 "$SERVER" 2>/dev/null || break
  [ -f "$dir/probe/setup-error.txt" ] && break
  if [ -s "$dir/probe/setup.json" ] && [ "$(curl -s -o /dev/null -w '%{http_code}' -m 60 "http://$host/?rest_route=%2F")" = 200 ]; then ready=1; break; fi
done
if [ "$ready" != 1 ]; then
  echo "El sitio no quedó listo"; cat "$dir/probe/setup-error.txt" 2>/dev/null || true; tail -20 "$dir/server.log"
  exit 1
fi
echo "== Sitio listo en $(( $(date +%s) - start )) s (puerto $PORT, PHP $PHPV) · Elements $ELEMENTS_VERSION · WooCommerce $WOO_VERSION"
status=0
NODE_PATH="$PUPPETEER_PATH" node "$D/run.cjs" "$PORT" "$host" "$dir" || status=1
if [ -s "$dir/probe/fatal.log" ]; then echo "== Errores fatales"; cat "$dir/probe/fatal.log"; status=1; fi
if grep -qE 'PHP Fatal error|Uncaught' "$dir/server.log"; then echo "== Errores de PHP en el servidor"; grep -E 'PHP Fatal error|Uncaught' "$dir/server.log" | head -5; status=1; fi
if [ -s "$dir/probe/php-errors.log" ]; then echo "== Avisos de PHP de los módulos propios"; sort "$dir/probe/php-errors.log" | uniq -c | head -10; status=1; else echo "== Ningún aviso de PHP de los módulos propios"; fi
node -e '
const [out, version, seconds, status] = process.argv.slice(1);
let r = {}; try { r = JSON.parse(require("fs").readFileSync(out + "/result.json", "utf8")); } catch {}
require("fs").writeFileSync(out + "/summary.json", JSON.stringify({ elements: version, status: status === "0" ? "ok" : "falla", checks: r.checks || 0, failures: r.failures ?? null, failed: r.failed || [], seconds: Number(seconds) }, null, 1));
' "$OUT" "$ELEMENTS_VERSION" "$(( $(date +%s) - start ))" "$status"
[ $status -eq 0 ] || { echo 'Tanda B de Elements: ensayo con fallos'; exit 1; }
echo "Tanda B de Elements validada en Playground en $(( $(date +%s) - start )) s."
