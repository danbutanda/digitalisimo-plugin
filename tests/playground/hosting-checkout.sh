#!/usr/bin/env bash
# El dominio antes de pagar y DIGITALÍSIMO Hosting en el MCP de punta a punta en WordPress Playground (sin servidor web
# real): WooCommerce 11.2.1 fijado (la copia oficial de reference/, comprobada con su sha256 y montada), DIGITALÍSIMO
# Hosting y DIGITALÍSIMO Elements del repositorio (copias), el MCP del sitio y el editor atómico activos, y una sonda
# (mu-plugin) que simula Namecheap por pre_http_request (nada sale a internet). hosting-checkout/run.mjs usa el MCP real
# (JSON-RPC con contraseñas de aplicación), compra como invitada por la Store API (dominio nuevo, «Uno que ya tengo»,
# transferencia con EPP) y abre una página atómica con el buscador (con Chrome si está).
# Uso: bash tests/playground/hosting-checkout.sh
#      (en la PC: bash /var/tmp/digi-wt/pc-run.sh --traer-a <dir> <worktree> -- bash tests/playground/hosting-checkout.sh)
#   OUT, TMPDIR    resultados (por defecto /var/tmp/digi-hosting-checkout) y temporales de Playground
#   PORT           puerto (9881; el bloque 9880–9889 es de este arnés)
#   WOO_ZIP        WooCommerce (tests/playground/woocommerce-pin.sh); obligatorio: sin la copia o con otra suma, se detiene
#   PHP_VERSION    versión de PHP de Playground (8.2 por defecto)
# Deja $OUT/result.txt, result.json y summary.json. Al terminar borra los secretos del ensayo (claves inventadas, EPP y
# contraseñas de aplicación), las copias de los plugins y WooCommerce extraído.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
D="$ROOT/tests/playground/hosting-checkout"
export OUT="${OUT:-/var/tmp/digi-hosting-checkout}"
export TMPDIR="${TMPDIR:-/var/tmp/digi-hosting-checkout/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PORT="${PORT:-9881}"
case "$PORT" in 988[0-9]) ;; *) echo "hosting-checkout.sh usa el bloque 9880–9889: $PORT es de otro arnés"; exit 1;; esac
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
rm -rf "$OUT/src" "$OUT/probe" "$OUT/result.txt" "$OUT/result.json" && mkdir -p "$OUT/src" "$OUT/probe"
cp -a "$ROOT/digitalisimo-hosting" "$OUT/src/digitalisimo-hosting"
cp -a "$ROOT/digitalisimo-elements" "$OUT/src/digitalisimo-elements"
HOSTING_VERSION="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([0-9.]*\).*/\1/p' "$OUT/src/digitalisimo-hosting/digitalisimo-hosting.php" | head -1)"
ELEMENTS_VERSION="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([0-9.]*\).*/\1/p' "$OUT/src/digitalisimo-elements/pro-elements.php" | head -1)"
cp "$D/setup.php" "$D/probe.php" "$OUT/probe/"
head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n' > "$OUT/probe/token.txt"
host="127.0.0.1:$PORT"
cat > "$OUT/blueprint.json" <<JSON
{"preferredVersions":{"php":"${PHP_VERSION:-8.2}","wp":"latest"},"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{"WP_ENVIRONMENT_TYPE":"local"}},
{"step":"runPHP","code":"<?php require '/digi-probe/setup.php';"}]}
JSON
if fuser -n tcp "$PORT" >/dev/null 2>&1; then echo "El puerto $PORT está ocupado por otro proceso; usa PORT=…"; exit 1; fi
begun=$(date +%s)
$CLI server --port="$PORT" --workers=1 --site-url="http://$host" --blueprint="$OUT/blueprint.json" \
  --mount="$OUT/src/digitalisimo-hosting:/wordpress/wp-content/plugins/digitalisimo-hosting" \
  --mount="$OUT/src/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements" \
  --mount="$OUT/probe:/digi-probe" \
  "$(woo_mount)" > "$OUT/server.log" 2>&1 &
SERVER=$!
ready=0
for i in $(seq 1 400); do
  sleep 3
  kill -0 "$SERVER" 2>/dev/null || break
  [ -f "$OUT/probe/setup-error.txt" ] && break
  if [ -s "$OUT/probe/setup.json" ] && [ "$(curl -s -o /dev/null -w '%{http_code}' -m 60 -H "Host: $host" "http://127.0.0.1:$PORT/?rest_route=%2F")" = 200 ]; then ready=1; break; fi
done
if [ "$ready" != 1 ]; then echo "El sitio no quedó listo"; cat "$OUT/probe/setup-error.txt" 2>/dev/null || true; tail -20 "$OUT/server.log"; exit 1; fi
echo "== sitio listo en $(( $(date +%s) - begun )) s (puerto $PORT) · Hosting $HOSTING_VERSION · Elements $ELEMENTS_VERSION · WooCommerce $WOO_VERSION · $(cat "$OUT/probe/setup.json")"
status=0
node "$D/run.mjs" "$PORT" "$host" "$OUT" || status=1
if [ -s "$OUT/probe/fatal.log" ]; then echo "== errores fatales"; cat "$OUT/probe/fatal.log"; status=1; fi
if grep -qE 'PHP Fatal error|Uncaught' "$OUT/server.log"; then echo "== errores de PHP en el servidor"; grep -E 'PHP Fatal error|Uncaught' "$OUT/server.log" | head -5; status=1; fi
if [ -s "$OUT/probe/php-errors.log" ]; then echo "== avisos de PHP de Hosting"; sort "$OUT/probe/php-errors.log" | uniq -c | head -10; status=1; else echo "== ningún aviso de PHP de DIGITALÍSIMO Hosting"; fi
# Ningún secreto del ensayo (claves inventadas, EPP y contraseñas de aplicación) fuera de la sonda.
node -e '
const fs = require("fs"), path = require("path");
const dir = process.argv[1];
let s = {}; try { s = JSON.parse(fs.readFileSync(path.join(dir, "probe", "secrets.json"), "utf8")); } catch {}
const values = Object.values(s).filter((v) => typeof v === "string" && v.length >= 12);
const leaks = [];
const skip = new Set(["secrets.json", "token.txt", "server.log"]);
const walk = (d) => { for (const n of fs.readdirSync(d)) { const p = path.join(d, n); const st = fs.statSync(p); if (st.isDirectory()) { if (!["src", "woocommerce", "tmp"].includes(n)) walk(p); } else if (!skip.has(n)) { const t = fs.readFileSync(p, "utf8"); values.forEach((v, i) => { if (t.includes(v)) leaks.push(path.relative(dir, p) + " (secreto " + (i + 1) + ")"); }); } } };
walk(dir);
console.log(leaks.length ? "== secretos en los resultados: " + leaks.join(", ") : "== ningún secreto del ensayo en los resultados (" + values.length + " revisados)");
process.exit(leaks.length ? 1 : 0);
' "$OUT" || status=1
node -e '
const [out, hv, ev, woo, seconds, status] = process.argv.slice(1);
let r = {}; try { r = JSON.parse(require("fs").readFileSync(out + "/result.json", "utf8")); } catch {}
require("fs").writeFileSync(out + "/summary.json", JSON.stringify({ hosting: hv, elements: ev, woocommerce: woo, status: status === "0" ? "ok" : "falla", checks: r.checks || 0, failures: r.failures ?? null, seconds: Number(seconds) }, null, 1));
' "$OUT" "$HOSTING_VERSION" "$ELEMENTS_VERSION" "$WOO_VERSION" "$(( $(date +%s) - begun ))" "$status"
[ $status -eq 0 ] || { echo 'Dominio antes de pagar y Hosting en el MCP: ensayo con fallos'; exit 1; }
echo "Dominio antes de pagar y Hosting en el MCP validados en Playground en $(( $(date +%s) - begun )) s."
