#!/usr/bin/env bash
# WooCommerce en el MCP de DIGITALÍSIMO Elements, de punta a punta, en WordPress Playground (sin Chrome): WooCommerce
# 11.2.1 fijado (la copia oficial de reference/, comprobada con su sha256 y montada; nada de wordpress.org), el MCP del
# sitio activo, contraseñas de aplicación y JSON-RPC real contra /wp-json/elementor/mcp (initialize,
# notifications/initialized, tools/list y tools/call) desde woo-mcp/run.mjs.
#   single     las 7 herramientas sólo con WooCommerce y modo ≠ off; modo read (rechaza escrituras diciendo dónde se
#              cambia), off y write; categoría, atributo y términos; producto variable con variaciones y stock; cupón;
#              cliente; pedido creado, cambiado de estado, nota y reembolso manual (api_refund false por defecto);
#              papelera y borrado definitivo con force; ajuste de la tienda; zona de envío con ubicaciones y método;
#              impuestos; pasarela con clave enmascarada (reenviar la máscara la conserva); informes; bitácora sin datos
#              personales; un gerente de tienda puede, una editora y una suscriptora no.
#   multisite  red de subdirectorios: sitios 1 y 2 con tienda y 3 sin WooCommerce; herencia del modo (la red en write,
#              un sitio que hereda escribe y otro con read propio no; la red en off apaga sólo a los que heredan) y
#              cada sitio con su propia tienda.
# Uso: bash tests/playground/woo-mcp.sh   (en la PC: bash /var/tmp/digi-wt/pc-run.sh <worktree> -- bash tests/playground/woo-mcp.sh)
#   MODES          modos a correr (por defecto «single multisite»)
#   OUT, TMPDIR    resultados (por defecto /var/tmp/digi-woo-mcp) y temporales de Playground
#   PORT           puerto del modo single (9521); multisite usa PORT+1. El bloque 9520–9529 es de este arnés.
#   WOO_ZIP        WooCommerce (tests/playground/woocommerce-pin.sh); obligatorio: sin la copia o con otra suma, se detiene
#   ELEMENTS_DIR   DIGITALÍSIMO Elements a probar (por defecto digitalisimo-elements/ del repositorio; se copia fijo)
# Deja $OUT/<modo>/result.txt y result.json y $OUT/summary.json. Al terminar borra los secretos del ensayo (contraseñas
# de aplicación y la clave de la pasarela), la copia del plugin y WooCommerce extraído.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
D="$ROOT/tests/playground/woo-mcp"
export OUT="${OUT:-/var/tmp/digi-woo-mcp}"
export TMPDIR="${TMPDIR:-/var/tmp/digi-woo-mcp/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PORT="${PORT:-9521}"
ELEMENTS_DIR="${ELEMENTS_DIR:-$ROOT/digitalisimo-elements}"
case "$PORT" in 952[0-8]) ;; *) echo "woo-mcp.sh usa el bloque 9520–9529 (9521 sitio, 9522 red): $PORT es de otro arnés"; exit 1;; esac
[ -f "$ELEMENTS_DIR/pro-elements.php" ] || { echo "ELEMENTS_DIR no contiene pro-elements.php: $ELEMENTS_DIR"; exit 1; }
SERVER=''
SERVER_PORT=''
stop_server() {
  [ -n "$SERVER" ] && kill "$SERVER" 2>/dev/null || true
  [ -n "$SERVER_PORT" ] && for i in 1 2 3; do fuser -k -n tcp "$SERVER_PORT" >/dev/null 2>&1 || break; sleep 1; done
  SERVER=''; SERVER_PORT=''
}
# Los secretos del ensayo y las copias grandes nunca quedan en los resultados.
scrub() { rm -rf "$OUT"/*/probe/secrets.json "$OUT"/*/probe/token.txt "$OUT/src" "$OUT/woocommerce"; }
trap 'stop_server; scrub; playground_cleanup' EXIT
trap 'exit 130' INT TERM

. "$ROOT/tests/playground/woocommerce-pin.sh"
WOO_REQUIRE=1 woo_pin "$OUT/woocommerce" "$OUT/woocommerce.json" || exit 1
rm -rf "$OUT/src" && mkdir -p "$OUT/src"
cp -a "$ELEMENTS_DIR" "$OUT/src/digitalisimo-elements"
ELEMENTS_VERSION="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([0-9.]*\).*/\1/p' "$OUT/src/digitalisimo-elements/pro-elements.php" | head -1)"
ELEMENTS_SHA="$(cd "$OUT/src/digitalisimo-elements" && find . -type f ! -path './elementor/*' -print0 | LC_ALL=C sort -z | xargs -0 sha256sum | sha256sum | cut -c1-16)"

start=$(date +%s)
fail=0
offset=0
summary='[]'
for mode in ${MODES:-single multisite}; do
  port=$((PORT + offset)); offset=$((offset + 1))
  case "$mode" in
    single) host="127.0.0.1:$port"; extra='' ;;
    multisite) host='playground.test'; extra='{"step":"enableMultisite"},' ;;
    *) echo "Modo desconocido: $mode"; exit 2 ;;
  esac
  dir="$OUT/$mode"
  rm -rf "$dir"; mkdir -p "$dir/probe"
  cp "$D/setup.php" "$D/probe.php" "$dir/probe/"
  head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n' > "$dir/probe/token.txt"
  cat > "$dir/blueprint.json" <<JSON
{"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{"WP_ENVIRONMENT_TYPE":"local"}},$extra
{"step":"runPHP","code":"<?php require '/digi-probe/setup.php';"}]}
JSON
  if fuser -n tcp "$port" >/dev/null 2>&1; then echo "== $mode: el puerto $port está ocupado por otro proceso; usa PORT=…"; fail=1; continue; fi
  begun=$(date +%s)
  $CLI server --port="$port" --workers=1 --site-url="http://$host" --blueprint="$dir/blueprint.json" \
    --mount="$OUT/src/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements" \
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
    echo "== $mode: el sitio no quedó listo"; cat "$dir/probe/setup-error.txt" 2>/dev/null || true; tail -20 "$dir/server.log"
    stop_server; fail=1; continue
  fi
  echo "== $mode: sitio listo en $(( $(date +%s) - begun )) s (puerto $port) · Elements $ELEMENTS_VERSION ($ELEMENTS_SHA) · WooCommerce $WOO_VERSION"
  status=0
  node "$D/run.mjs" "$mode" "$port" "$host" "$dir" || status=1
  if [ -s "$dir/probe/fatal.log" ]; then echo "== $mode: errores fatales"; cat "$dir/probe/fatal.log"; status=1; fi
  if grep -qE 'PHP Fatal error|Uncaught' "$dir/server.log"; then echo "== $mode: errores de PHP en el servidor"; grep -E 'PHP Fatal error|Uncaught' "$dir/server.log" | head -5; status=1; fi
  # Ningún aviso, advertencia, obsolescencia ni _doing_it_wrong del MCP de tienda (la sonda los anota sin WP_DEBUG).
  if [ -s "$dir/probe/php-errors.log" ]; then echo "== $mode: avisos de PHP del MCP de tienda"; sort "$dir/probe/php-errors.log" | uniq -c | head -10; status=1; else echo "== $mode: ningún aviso de PHP del MCP de tienda"; fi
  # Ningún secreto del ensayo (contraseñas de aplicación, clave y token de la pasarela) en los resultados ni registros.
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
process.exit(leaks.length ? 1 : 0);
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
const [out, modes, version, sha, woo, seconds] = process.argv.slice(1);
require("fs").writeFileSync(out + "/summary.json", JSON.stringify({ elements: version, elements_sha: sha, woocommerce: woo, modes: JSON.parse(modes), seconds: Number(seconds) }, null, 1));
' "$OUT" "$summary" "$ELEMENTS_VERSION" "$ELEMENTS_SHA" "$WOO_VERSION" "$(( $(date +%s) - start ))"
[ $fail -eq 0 ] || { echo 'WooCommerce en el MCP: ensayo con fallos'; exit 1; }
echo "WooCommerce en el MCP validado en Playground en $(( $(date +%s) - start )) s."
