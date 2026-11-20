#!/usr/bin/env bash
# `digitalisimo-woo/skydropx` (MCP de DIGITALÍSIMO Elements) de punta a punta en WordPress Playground (sin Chrome):
# WooCommerce 11.2.1 y Skydropx 2.1.5 reales (las copias oficiales de reference/, comprobadas con su sha256 y montadas;
# nada de wordpress.org), la plataforma Skydropx simulada por la sonda con pre_http_request (cotización, guía firmada y
# webhooks; ninguna petición sale a internet) y JSON-RPC real contra /wp-json/elementor/mcp desde skydropx-mcp/run.mjs:
# la herramienta sólo aparece con Skydropx activo; status; auditar, corregir (gramos y milímetros) y volver a auditar;
# cotizar con el método del plugin y su caché sin tocar ningún carrito guardado; envíos; registrar el rastreo de un
# envío hecho a mano (metas del plugin, estado, nota y webhook, sin recalcular totales) y verlo en la caja del plugin en
# la pantalla del pedido; el enlace de la guía abierto con la sesión del administrador hasta el PDF del plugin (petición
# firmada con su clave); reenviar un pedido; permisos (también por pedido, con un rol sin edit_others_shop_orders),
# bitácora sin valores y modos.
#   MODES          «posts hpos» por defecto. posts: pedidos como entradas y webhooks entregados en la misma petición.
#                  hpos: HPOS activo y webhooks por Action Scheduler (como una tienda real), más audit_products con
#                  5 000 productos y variaciones (tiempo, consultas y memoria de cada página).
# Uso: bash tests/playground/skydropx-mcp.sh   (en la PC: bash /var/tmp/digi-wt/pc-run.sh <worktree> -- bash tests/playground/skydropx-mcp.sh)
#   OUT, TMPDIR    resultados (por defecto /var/tmp/digi-skydropx-mcp) y temporales de Playground
#   PORT           puerto del modo posts (9545); hpos usa PORT+1 (el bloque 9545–9549 es de este arnés)
#   WOO_ZIP        WooCommerce (tests/playground/woocommerce-pin.sh); obligatorio
#   SKYDROPX_ZIP   Skydropx (por defecto reference/skydropx.2.1.5.zip del repositorio o del principal); obligatorio
#   ELEMENTS_DIR   DIGITALÍSIMO Elements a probar (por defecto digitalisimo-elements/ del repositorio; se copia fijo)
# Deja $OUT/<modo>/result.txt, result.json y responses.jsonl, y $OUT/summary.json. Al terminar borra los secretos del
# ensayo y las copias de los plugins.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
D="$ROOT/tests/playground/skydropx-mcp"
export OUT="${OUT:-/var/tmp/digi-skydropx-mcp}"
export TMPDIR="${TMPDIR:-/var/tmp/digi-skydropx-mcp/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PORT="${PORT:-9545}"
ELEMENTS_DIR="${ELEMENTS_DIR:-$ROOT/digitalisimo-elements}"
case "$PORT" in 954[5-9]) ;; *) echo "skydropx-mcp.sh usa el bloque 9545–9549: $PORT es de otro arnés"; exit 1;; esac
[ -f "$ELEMENTS_DIR/pro-elements.php" ] || { echo "ELEMENTS_DIR no contiene pro-elements.php: $ELEMENTS_DIR"; exit 1; }
# Copia de la suma de reference/OFFICIAL-SOURCES.txt (que no se versiona): la descarga oficial de wordpress.org.
SKYDROPX_PIN_SHA256=be61b6e4d996bb324cda3bbf67056443bd6b89f7b1c2faf5e2c8f3b57de29163
SERVER=''
SERVER_PORT=''
stop_server() {
  [ -n "$SERVER" ] && kill "$SERVER" 2>/dev/null || true
  [ -n "$SERVER_PORT" ] && for i in 1 2 3; do fuser -k -n tcp "$SERVER_PORT" >/dev/null 2>&1 || break; sleep 1; done
  SERVER=''; SERVER_PORT=''
}
scrub() { rm -rf "$OUT"/*/probe/secrets.json "$OUT"/*/probe/token.txt "$OUT/src" "$OUT/woocommerce" "$OUT/skydropx"; }
trap 'stop_server; scrub; playground_cleanup' EXIT
trap 'exit 130' INT TERM

. "$ROOT/tests/playground/woocommerce-pin.sh"
WOO_REQUIRE=1 woo_pin "$OUT/woocommerce" "$OUT/woocommerce.json" || exit 1
sky_zip="${SKYDROPX_ZIP:-}"
if [ -z "$sky_zip" ]; then
  sky_zip="$ROOT/reference/skydropx.2.1.5.zip"
  if [ ! -f "$sky_zip" ]; then
    main_root="$(git -C "$ROOT" rev-parse --path-format=absolute --git-common-dir 2>/dev/null || true)"
    [ -n "$main_root" ] && sky_zip="$(dirname "$main_root")/reference/skydropx.2.1.5.zip"
  fi
fi
[ -f "$sky_zip" ] || { echo "Falta reference/skydropx.2.1.5.zip (o SKYDROPX_ZIP)"; exit 1; }
[ "$(sha256sum "$sky_zip" | cut -c1-64)" = "$SKYDROPX_PIN_SHA256" ] || { echo "skydropx.2.1.5.zip no es la copia oficial anotada (sha256 distinta)"; exit 1; }
rm -rf "$OUT/skydropx" && mkdir -p "$OUT/skydropx"
unzip -q "$sky_zip" -d "$OUT/skydropx"
SKY_VERSION="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([0-9.]*\).*/\1/p' "$OUT/skydropx/skydropx/skydropx.php" | head -1)"
[ "$SKY_VERSION" = 2.1.5 ] || { echo "La cabecera de Skydropx no dice 2.1.5: $SKY_VERSION"; exit 1; }
rm -rf "$OUT/src" && mkdir -p "$OUT/src"
cp -a "$ELEMENTS_DIR" "$OUT/src/digitalisimo-elements"
ELEMENTS_VERSION="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([0-9.]*\).*/\1/p' "$OUT/src/digitalisimo-elements/pro-elements.php" | head -1)"
ELEMENTS_SHA="$(cd "$OUT/src/digitalisimo-elements" && find . -type f ! -path './elementor/*' -print0 | LC_ALL=C sort -z | xargs -0 sha256sum | sha256sum | cut -c1-16)"

start=$(date +%s)
fail=0
offset=0
summary='[]'
for mode in ${MODES:-posts hpos}; do
port=$((PORT + offset)); offset=$((offset + 1))
begun=$(date +%s)
host="127.0.0.1:$port"
dir="$OUT/$mode"
rm -rf "$dir"; mkdir -p "$dir/probe"
cp "$D/setup.php" "$D/probe.php" "$dir/probe/"
head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n' > "$dir/probe/token.txt"
cat > "$dir/blueprint.json" <<JSON
{"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{"WP_ENVIRONMENT_TYPE":"local"}},
{"step":"runPHP","code":"<?php require '/digi-probe/setup.php';"}]}
JSON
if fuser -n tcp "$port" >/dev/null 2>&1; then echo "== $mode: el puerto $port está ocupado por otro proceso; usa PORT=…"; fail=1; continue; fi
$CLI server --port="$port" --workers=1 --site-url="http://$host" --blueprint="$dir/blueprint.json" \
  --mount="$OUT/src/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements" \
  --mount="$OUT/skydropx/skydropx:/wordpress/wp-content/plugins/skydropx" \
  --mount="$dir/probe:/digi-probe" \
  "$(woo_mount)" > "$dir/server.log" 2>&1 &
SERVER=$!
SERVER_PORT="$port"
ready=0
for i in $(seq 1 400); do
  sleep 3
  kill -0 "$SERVER" 2>/dev/null || break
  [ -f "$dir/probe/setup-error.txt" ] && break
  if [ -s "$dir/probe/setup.json" ] && [ "$(curl -s -o /dev/null -w '%{http_code}' -m 60 -H "Host: $host" "http://127.0.0.1:$port/?rest_route=%2F")" = 200 ]; then ready=1; break; fi
done
if [ "$ready" != 1 ]; then
  echo "== $mode: el sitio no quedó listo"; cat "$dir/probe/setup-error.txt" 2>/dev/null || true; tail -20 "$dir/server.log"; stop_server; fail=1; continue
fi
echo "== $mode: sitio listo en $(( $(date +%s) - begun )) s (puerto $port) · Elements $ELEMENTS_VERSION ($ELEMENTS_SHA) · WooCommerce $WOO_VERSION · Skydropx $SKY_VERSION"
status=0
node "$D/run.mjs" "$port" "$host" "$dir" "$mode" || status=1
if [ -s "$dir/probe/fatal.log" ]; then echo "== errores fatales"; cat "$dir/probe/fatal.log"; status=1; fi
if grep -qE 'PHP Fatal error|Uncaught' "$dir/server.log"; then echo "== errores de PHP en el servidor"; grep -E 'PHP Fatal error|Uncaught' "$dir/server.log" | head -5; status=1; fi
if [ -s "$dir/probe/php-errors.log" ]; then echo "== avisos de PHP de la herramienta o del plugin"; sort "$dir/probe/php-errors.log" | uniq -c | head -10; status=1; else echo "== ningún aviso de PHP de la herramienta ni del plugin"; fi
# Ningún secreto del ensayo (contraseñas de aplicación, clave del plugin, token de la URL de cotización, secretos de los
# webhooks) en los resultados ni en los registros.
node -e '
const fs = require("fs"), path = require("path");
const dir = process.argv[1];
let secrets = {};
try { secrets = JSON.parse(fs.readFileSync(path.join(dir, "probe/secrets.json"), "utf8")); } catch {}
const values = Object.values(secrets).filter((v) => typeof v === "string" && v.length >= 12);
const leaks = [];
const walk = (d) => { for (const n of fs.readdirSync(d)) { const p = path.join(d, n); const st = fs.statSync(p); if (st.isDirectory()) walk(p); else if (n !== "secrets.json") { const t = fs.readFileSync(p, "utf8"); values.forEach((v, i) => { if (t.includes(v) || t.includes(v.replace(/ /g, ""))) leaks.push(path.relative(dir, p) + " (secreto " + (i + 1) + ")"); }); } } };
walk(dir);
console.log(leaks.length ? "== secretos en los resultados: " + leaks.join(", ") : "== ningún secreto del ensayo en los resultados (" + values.length + " revisados)");
process.exit(leaks.length || values.length < 6 ? 1 : 0);
' "$dir" || status=1
[ "$status" = 0 ] || fail=1
summary="$(node -e '
const [list, mode, file, seconds, status] = process.argv.slice(1);
const all = JSON.parse(list);
let r = {}; try { r = JSON.parse(require("fs").readFileSync(file, "utf8")); } catch {}
all.push({ mode, status: status === "0" ? "ok" : "falla", checks: r.checks || 0, failures: r.failures ?? null, seconds: Number(seconds) });
console.log(JSON.stringify(all));
' "$summary" "$mode" "$dir/result.json" "$(( $(date +%s) - begun ))" "$status")"
stop_server
sleep 2
done
node -e '
const [out, modes, version, sha, woo, sky, seconds] = process.argv.slice(1);
require("fs").writeFileSync(out + "/summary.json", JSON.stringify({ elements: version, elements_sha: sha, woocommerce: woo, skydropx: sky, modes: JSON.parse(modes), seconds: Number(seconds) }, null, 1));
' "$OUT" "$summary" "$ELEMENTS_VERSION" "$ELEMENTS_SHA" "$WOO_VERSION" "$SKY_VERSION" "$(( $(date +%s) - start ))"
[ $fail -eq 0 ] || { echo 'Skydropx en el MCP: ensayo con fallos'; exit 1; }
echo "Skydropx en el MCP validado en Playground en $(( $(date +%s) - start )) s."
