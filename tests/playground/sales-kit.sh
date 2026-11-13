#!/usr/bin/env bash
# Kit de ventas de DIGITALÍSIMO Elements, de punta a punta, en WordPress Playground y Chrome real (manual, en la PC):
# Elements con su núcleo incluido (sin el plugin Elementor), el MCP del sitio activo, contraseñas de aplicación y
# JSON-RPC real contra /wp-json/elementor/mcp (initialize, notifications/initialized, tools/list y tools/call) desde
# sales-kit/run.mjs:
#   - las tres herramientas (digitalisimo-sales/list-templates, create-page y guide) con su entrada cerrada;
#   - create-page de las ocho plantillas con contenedores y, con el experimento de contenedores apagado, con secciones:
#     borrador, lienzo, todos los widgets, IDs nuevos; sin fecha de cierre (urgencia quitada) y con bloques omitidos;
#   - los candados (URLs de otros sitios, cierre, enlaces, precios, WhatsApp, otro estado) sin crear nada, y los
#     permisos (una editora crea borradores; una autora y una editora sin edit_pages no pueden);
#   - Chrome a 1 280 y 390 px en cada página creada: HTTP 200, sin errores de JavaScript, sin 404/500 del sitio, sin
#     peticiones fuera del sitio, sin avisos de PHP, sin desborde, widgets pintados = guardados y su CSS cargado;
#   - el aviso al importar un ZIP de plantillas sin la carga de archivos sin filtrar (y que con ella importa las ocho).
# Uso: bash tests/playground/sales-kit.sh   (en la PC: bash /var/tmp/digi-wt/pc-run.sh <worktree> -- bash tests/playground/sales-kit.sh)
#   OUT, TMPDIR    resultados (por defecto /var/tmp/digi-sales-kit) y temporales de Playground
#   PORT           puerto (9641); el bloque 9640–9649 es de este arnés
#   ELEMENTS_DIR   DIGITALÍSIMO Elements a probar (por defecto digitalisimo-elements/ del repositorio; se copia fijo)
#   PUPPETEER_PATH node_modules con puppeteer-core (si no, se instala puppeteer-core 25.12.0 para la corrida)
#   SOLO=importar  sólo S0 (servidor y herramientas) y el aviso de importación (S8), sin crear páginas
# Deja $OUT/result.txt, result.json, summary.json y capturas/. Al terminar borra los secretos del ensayo y la copia del
# plugin.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
D="$ROOT/tests/playground/sales-kit"
export OUT="${OUT:-/var/tmp/digi-sales-kit}"
export TMPDIR="${TMPDIR:-/var/tmp/digi-sales-kit/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PORT="${PORT:-9641}"
ELEMENTS_DIR="${ELEMENTS_DIR:-$ROOT/digitalisimo-elements}"
case "$PORT" in 964[0-9]) ;; *) echo "sales-kit.sh usa el bloque 9640–9649: $PORT es de otro arnés"; exit 1;; esac
[ -f "$ELEMENTS_DIR/pro-elements.php" ] || { echo "ELEMENTS_DIR no contiene pro-elements.php: $ELEMENTS_DIR"; exit 1; }
SERVER=''
stop_server() {
  [ -n "$SERVER" ] && kill "$SERVER" 2>/dev/null || true
  for i in 1 2 3; do fuser -k -n tcp "$PORT" >/dev/null 2>&1 || break; sleep 1; done
  SERVER=''
}
scrub() { rm -rf "$OUT"/probe/secrets.json "$OUT"/probe/token.txt "$OUT/src" "$OUT/pp"; }
trap 'stop_server; scrub; playground_cleanup' EXIT
trap 'exit 130' INT TERM

rm -rf "$OUT/src" "$OUT/probe" "$OUT/capturas" "$OUT"/result.* "$OUT/server.log"
mkdir -p "$OUT/src" "$OUT/probe"
cp -a "$ELEMENTS_DIR" "$OUT/src/digitalisimo-elements"
ELEMENTS_VERSION="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([0-9.]*\).*/\1/p' "$OUT/src/digitalisimo-elements/pro-elements.php" | head -1)"
ELEMENTS_SHA="$(cd "$OUT/src/digitalisimo-elements" && find . -type f ! -path './elementor/*' -print0 | LC_ALL=C sort -z | xargs -0 sha256sum | sha256sum | cut -c1-16)"
cp "$D/setup.php" "$D/probe.php" "$OUT/probe/"
head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n' > "$OUT/probe/token.txt"
# El ZIP del kit (las ocho plantillas con secciones del repositorio), como lo subiría una persona.
python3 -I - "$ROOT/digitalisimo-elements/modules/digitalisimo-sales/templates/sections" "$OUT/kit-ventas.zip" <<'PY'
import os, sys, zipfile
src, dst = sys.argv[1], sys.argv[2]
with zipfile.ZipFile(dst, "w", zipfile.ZIP_DEFLATED) as z:
    for f in sorted(os.listdir(src)):
        z.write(os.path.join(src, f), f)
PY
cat > "$OUT/blueprint.json" <<JSON
{"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{"WP_ENVIRONMENT_TYPE":"local"}},
{"step":"runPHP","code":"<?php require '/digi-probe/setup.php';"}]}
JSON
if fuser -n tcp "$PORT" >/dev/null 2>&1; then echo "El puerto $PORT está ocupado por otro proceso; usa PORT=…"; exit 1; fi
PP="${PUPPETEER_PATH:-$( { ls -d "$HOME"/.npm/_npx/*/node_modules 2>/dev/null || true; } | while read -r d; do [ -d "$d/puppeteer-core" ] && echo "$d" && break; done || true)}"
if [ -z "$PP" ]; then
  echo "== Instalando puppeteer-core 25.12.0 para esta corrida"
  npm install --silent --prefix "$OUT/pp" puppeteer-core@25.12.0 >"$OUT/npm.log" 2>&1 || { echo "npm falló:"; tail -20 "$OUT/npm.log"; exit 1; }
  PP="$OUT/pp/node_modules"
fi
begun=$(date +%s)
$CLI server --port="$PORT" --workers=1 --site-url="http://127.0.0.1:$PORT" --blueprint="$OUT/blueprint.json" \
  --mount="$OUT/src/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements" \
  --mount="$OUT/probe:/digi-probe" > "$OUT/server.log" 2>&1 &
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
echo "== sitio listo en $(( $(date +%s) - begun )) s (puerto $PORT) · Elements $ELEMENTS_VERSION ($ELEMENTS_SHA) · $(cat "$OUT/probe/setup.json")"
status=0
PUPPETEER_PATH="$PP" node "$D/run.mjs" "$PORT" "$OUT" "$OUT/kit-ventas.zip" || status=1
if [ -s "$OUT/probe/fatal.log" ]; then echo "== errores fatales"; cat "$OUT/probe/fatal.log"; status=1; fi
if grep -qE 'PHP Fatal error|Uncaught' "$OUT/server.log"; then echo "== errores de PHP en el servidor"; grep -E 'PHP Fatal error|Uncaught' "$OUT/server.log" | head -5; status=1; fi
if [ -s "$OUT/probe/php-errors.log" ]; then echo "== avisos de PHP del kit de ventas"; sort "$OUT/probe/php-errors.log" | uniq -c | head -10; status=1; else echo "== ningún aviso de PHP del kit de ventas"; fi
if [ -s "$OUT/probe/elements-notices.log" ]; then echo "== avisos de PHP del resto de Elements (informativo)"; sed 's/^[^·]*· //' "$OUT/probe/elements-notices.log" | sort | uniq -c | sort -rn | head -5; fi
# Ningún secreto del ensayo en los resultados.
node -e '
const fs = require("fs"), path = require("path");
const dir = process.argv[1];
let s = {}; try { s = JSON.parse(fs.readFileSync(path.join(dir, "probe/secrets.json"), "utf8")); } catch {}
const vals = Object.values(s).filter((v) => typeof v === "string" && v.length >= 12);
const leaks = [];
const walk = (d) => { for (const n of fs.readdirSync(d)) { const p = path.join(d, n); const st = fs.statSync(p); if (st.isDirectory()) { if (!["src", "pp", "tmp"].includes(n)) walk(p); } else if (n !== "secrets.json" && st.size < 50e6 && !/\.(png|zip)$/.test(n)) { const t = fs.readFileSync(p, "utf8"); vals.forEach((v, i) => { if (t.includes(v) || t.includes(v.replace(/ /g, ""))) leaks.push(path.relative(dir, p)); }); } } };
walk(dir);
console.log(leaks.length ? "== secretos en los resultados: " + leaks.join(", ") : "== ningún secreto del ensayo en los resultados (" + vals.length + " revisados)");
process.exit(leaks.length ? 1 : 0);
' "$OUT" || status=1
node -e '
const [file, out, version, sha, seconds, status] = process.argv.slice(1);
let r = {}; try { r = JSON.parse(require("fs").readFileSync(file, "utf8")); } catch {}
require("fs").writeFileSync(out, JSON.stringify({ elements: version, elements_sha: sha, checks: r.checks || 0, failures: r.failures ?? null, status: status === "0" ? "ok" : "falla", seconds: Number(seconds) }, null, 1));
' "$OUT/result.json" "$OUT/summary.json" "$ELEMENTS_VERSION" "$ELEMENTS_SHA" "$(( $(date +%s) - begun ))" "$status"
[ $status -eq 0 ] || { echo 'Kit de ventas: ensayo con fallos'; exit 1; }
echo "Kit de ventas validado en Playground y Chrome en $(( $(date +%s) - begun )) s."
