#!/usr/bin/env bash
# La landing de producto del kit de ventas de DIGITALÍSIMO Elements, de punta a punta, en WordPress Playground y Chrome
# real (manual, en la PC). Caso real (plomerito, 2026-11-23): la promo de Black Friday de un producto de la tienda
# ($10,000 a $7,500, «de hoy a fin de mes») salió como la plantilla, con placeholders, el WhatsApp y el logo de muestra
# y enlaces a páginas que no existen.
#   - la tienda (la versión fijada en woocommerce-pin.sh) y Elements con su núcleo incluido, el MCP del sitio activo,
#     una tienda como la del caso (pesos, pagos y envío activos, logo, privacidad y términos publicados, reembolso en
#     borrador, un pie con un enlace wa.me) y el producto (descripción con lista, garantía, atributo, tres imágenes);
#   - JSON-RPC real contra /wp-json/elementor/mcp: digitalisimo-sales-create-page con product_id y offer, como lo
#     llamaría el Asistente IA;
#   - Chrome a 1 280 y 390 px en la vista previa: ningún [placeholder] en el HTML, el nombre, los precios, las imágenes,
#     el botón al carrito, el WhatsApp del sitio, el logo, la cuenta regresiva, cada enlace del sitio con HTTP 200 y
#     ninguno a otro sitio; capturas en $OUT/capturas.
# Uso: bash tests/playground/sales-product.sh
#      (en la PC: bash /var/tmp/digi-wt/pc-run.sh --traer-a <dir> <worktree> -- bash tests/playground/sales-product.sh)
#   OUT, TMPDIR    resultados (por defecto /var/tmp/digi-sales-product) y temporales de Playground
#   PORT           puerto (9791); el bloque 9790–9799 es de este arnés
#   ELEMENTS_DIR   DIGITALÍSIMO Elements a probar (por defecto digitalisimo-elements/ del repositorio; se copia fijo)
#   WOO_ZIP        la tienda (tests/playground/woocommerce-pin.sh)
#   PUPPETEER_PATH node_modules con puppeteer-core (si no, se instala puppeteer-core 25.12.0 para la corrida)
# Deja $OUT/result.txt, result.json, respuesta.json (la respuesta de create-page), landing.html, summary.json y
# capturas/. Al terminar borra los secretos del ensayo y la copia del plugin.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
D="$ROOT/tests/playground/sales-product"
export OUT="${OUT:-/var/tmp/digi-sales-product}"
export TMPDIR="${TMPDIR:-/var/tmp/digi-sales-product/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
. "$ROOT/tests/playground/woocommerce-pin.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PORT="${PORT:-9791}"
ELEMENTS_DIR="${ELEMENTS_DIR:-$ROOT/digitalisimo-elements}"
case "$PORT" in 979[0-9]) ;; *) echo "sales-product.sh usa el bloque 9790–9799: $PORT es de otro arnés"; exit 1;; esac
[ -f "$ELEMENTS_DIR/pro-elements.php" ] || { echo "ELEMENTS_DIR no contiene pro-elements.php: $ELEMENTS_DIR"; exit 1; }
SERVER=''
stop_server() {
  [ -n "$SERVER" ] && kill "$SERVER" 2>/dev/null || true
  for i in 1 2 3; do fuser -k -n tcp "$PORT" >/dev/null 2>&1 || break; sleep 1; done
  SERVER=''
}
scrub() { rm -rf "$OUT"/probe/secrets.json "$OUT"/probe/token.txt "$OUT/src" "$OUT/pp" "$OUT/woocommerce/plugin"; }
trap 'stop_server; scrub; playground_cleanup' EXIT
trap 'exit 130' INT TERM

rm -rf "$OUT/src" "$OUT/probe" "$OUT/capturas" "$OUT"/result.* "$OUT/server.log" "$OUT/respuesta.json" "$OUT/landing.html"
mkdir -p "$OUT/src" "$OUT/probe"
cp -a "$ELEMENTS_DIR" "$OUT/src/digitalisimo-elements"
ELEMENTS_VERSION="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([0-9.]*\).*/\1/p' "$OUT/src/digitalisimo-elements/pro-elements.php" | head -1)"
woo_pin "$OUT/woocommerce" "$OUT/woocommerce.json" || exit 1
WOO_STEP="$(woo_step true)"
cp "$D/setup.php" "$D/probe.php" "$OUT/probe/"
head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n' > "$OUT/probe/token.txt"
cat > "$OUT/blueprint.json" <<JSON
{"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{"WP_ENVIRONMENT_TYPE":"local"}},
${WOO_STEP:+$WOO_STEP,}{"step":"runPHP","code":"<?php require '/digi-probe/setup.php';"}]}
JSON
if fuser -n tcp "$PORT" >/dev/null 2>&1; then echo "El puerto $PORT está ocupado por otro proceso; usa PORT=…"; exit 1; fi
PP="${PUPPETEER_PATH:-$( { ls -d "$HOME"/.npm/_npx/*/node_modules 2>/dev/null || true; } | while read -r d; do [ -d "$d/puppeteer-core" ] && echo "$d" && break; done || true)}"
if [ -z "$PP" ]; then
  echo "== Instalando puppeteer-core 25.12.0 para esta corrida"
  npm install --silent --prefix "$OUT/pp" puppeteer-core@25.12.0 >"$OUT/npm.log" 2>&1 || { echo "npm falló:"; tail -20 "$OUT/npm.log"; exit 1; }
  PP="$OUT/pp/node_modules"
fi
begun=$(date +%s)
# shellcheck disable=SC2046
$CLI server --port="$PORT" --workers=1 --site-url="http://127.0.0.1:$PORT" --blueprint="$OUT/blueprint.json" \
  --mount="$OUT/src/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements" \
  --mount="$OUT/probe:/digi-probe" $(woo_mount) > "$OUT/server.log" 2>&1 &
SERVER=$!
ready=0
for i in $(seq 1 400); do
  sleep 3
  kill -0 "$SERVER" 2>/dev/null || break
  [ -f "$OUT/probe/setup-error.txt" ] && break
  if [ -s "$OUT/probe/setup.json" ] && [ "$(curl -s -o /dev/null -w '%{http_code}' -m 60 "http://127.0.0.1:$PORT/?rest_route=%2F")" = 200 ]; then ready=1; break; fi
done
if [ "$ready" != 1 ]; then
  echo "== el sitio no quedó listo"; cat "$OUT/probe/setup-error.txt" 2>/dev/null || true; tail -20 "$OUT/server.log"; exit 1
fi
echo "== sitio listo en $(( $(date +%s) - begun )) s (puerto $PORT) · Elements $ELEMENTS_VERSION · tienda ${WOO_VERSION:-wordpress.org} · $(cat "$OUT/probe/setup.json" | head -c 300)"
status=0
PUPPETEER_PATH="$PP" node "$D/run.mjs" "$PORT" "$OUT" || status=1
if [ -s "$OUT/probe/fatal.log" ]; then echo "== errores fatales"; cat "$OUT/probe/fatal.log"; status=1; fi
if grep -qE 'PHP Fatal error|Uncaught' "$OUT/server.log"; then echo "== errores de PHP en el servidor"; grep -E 'PHP Fatal error|Uncaught' "$OUT/server.log" | head -5; status=1; fi
if [ -s "$OUT/probe/php-errors.log" ]; then echo "== avisos de PHP del kit de ventas"; sort "$OUT/probe/php-errors.log" | uniq -c | head -10; status=1; else echo "== ningún aviso de PHP del kit de ventas"; fi
# Ningún secreto del ensayo en los resultados.
node -e '
const fs = require("fs"), path = require("path");
const dir = process.argv[1];
let s = {}; try { s = JSON.parse(fs.readFileSync(path.join(dir, "probe/secrets.json"), "utf8")); } catch {}
const vals = Object.values(s).filter((v) => typeof v === "string" && v.length >= 12);
const leaks = [];
const walk = (d) => { for (const n of fs.readdirSync(d)) { const p = path.join(d, n); const st = fs.statSync(p); if (st.isDirectory()) { if (!["src", "pp", "tmp", "woocommerce"].includes(n)) walk(p); } else if (n !== "secrets.json" && st.size < 50e6 && !/\.(png|zip)$/.test(n)) { const t = fs.readFileSync(p, "utf8"); vals.forEach((v) => { if (t.includes(v) || t.includes(v.replace(/ /g, ""))) leaks.push(path.relative(dir, p)); }); } } };
walk(dir);
console.log(leaks.length ? "== secretos en los resultados: " + leaks.join(", ") : "== ningún secreto del ensayo en los resultados (" + vals.length + " revisados)");
process.exit(leaks.length ? 1 : 0);
' "$OUT" || status=1
node -e '
const [file, out, version, woo, seconds, status] = process.argv.slice(1);
let r = {}; try { r = JSON.parse(require("fs").readFileSync(file, "utf8")); } catch {}
require("fs").writeFileSync(out, JSON.stringify({ elements: version, woocommerce: woo || "wordpress.org", checks: r.checks || 0, failures: r.failures ?? null, status: status === "0" ? "ok" : "falla", seconds: Number(seconds) }, null, 1));
' "$OUT/result.json" "$OUT/summary.json" "$ELEMENTS_VERSION" "${WOO_VERSION:-}" "$(( $(date +%s) - begun ))" "$status"
[ "$status" = 0 ] || { echo 'Landing de producto: ensayo con fallos'; exit 1; }
echo "Landing de producto validada en Playground en $(( $(date +%s) - begun )) s."
