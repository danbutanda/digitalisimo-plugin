#!/usr/bin/env bash
# CSS al día tras cada escritura del MCP de DIGITALÍSIMO Elements, de punta a punta, en WordPress Playground (sin
# Chrome): el MCP del sitio activo, una contraseña de aplicación y JSON-RPC real contra /wp-json/elementor/mcp
# (initialize, notifications/initialized, tools/list y tools/call) desde elements-mcp-css/run.mjs. Tras cada escritura
# se abre la página como la abre un editor (borrador), como un visitante (publicada) o por el enlace firmado de
# create-preview-link, y se comprueba que cada clase de estilo local (e-xxxxxxxx-xxxx) y global del HTML existe en el
# CSS que carga la página y que el `ver` de su hoja cambió.
#   M1  página V4 en borrador (create-page, plantilla lienzo) reconstruida tres veces con build-composition
#       (replace_children) cambiando estilos (el incidente del 2026-11-13)
#   M2  append y replace_children dentro de un contenedor; M3 manage-elements
#   M4  enlace firmado de create-preview-link, también uno viejo abierto después de otra reconstrucción
#   M5  update-page-settings con un cambio de estilo; M6 publish-document y una página publicada (autosave y promoción)
#   M7  kit: manage-global-variable, manage-classes y manage-default-styles regeneran el CSS global
#   M8  una página V3 (clásica) no empeora; M9 la observación secundaria (font-family con comillas y `\n` literal)
# Uso: bash tests/playground/elements-mcp-css.sh   (en la PC: bash /var/tmp/digi-wt/pc-run.sh <worktree> -- bash tests/playground/elements-mcp-css.sh)
#   PHPS           versiones de PHP de Playground, una corrida por versión (por defecto «7.4 8.2 8.5»)
#   OUT, TMPDIR    resultados (por defecto /var/tmp/digi-mcp-css) y temporales de Playground
#   PORT           puerto (9551); el bloque 9550–9559 es de este arnés
#   ELEMENTS_DIR   DIGITALÍSIMO Elements a probar (por defecto digitalisimo-elements/ del repositorio; se copia fijo)
#   ELEMENTS_ZIP   o un ZIP publicado (p. ej. dist/digitalisimo-elements-4.3.0.185.zip) para ver qué falla antes
#   EXPECT_FAIL=1  con el código anterior: la corrida termina bien si fallan exactamente las comprobaciones esperadas
# Deja $OUT/php-<versión>/result.txt y result.json y $OUT/summary.json. Al terminar borra la contraseña de aplicación
# del ensayo y la copia del plugin.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
D="$ROOT/tests/playground/elements-mcp-css"
export OUT="${OUT:-/var/tmp/digi-mcp-css}"
export TMPDIR="${TMPDIR:-/var/tmp/digi-mcp-css/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PORT="${PORT:-9551}"
case "$PORT" in 955[0-9]) ;; *) echo "elements-mcp-css.sh usa el bloque 9550–9559: $PORT es de otro arnés"; exit 1;; esac
SERVER=''
stop_server() {
  [ -n "$SERVER" ] && kill "$SERVER" 2>/dev/null || true
  for i in 1 2 3; do fuser -k -n tcp "$PORT" >/dev/null 2>&1 || break; sleep 1; done
  SERVER=''
}
scrub() { rm -rf "$OUT"/php-*/probe/secrets.json "$OUT"/php-*/probe/token.txt "$OUT/src"; }
trap 'stop_server; scrub; playground_cleanup' EXIT
trap 'exit 130' INT TERM

rm -rf "$OUT/src" && mkdir -p "$OUT/src"
if [ -n "${ELEMENTS_ZIP:-}" ]; then
  [ -f "$ELEMENTS_ZIP" ] || { echo "No existe ELEMENTS_ZIP: $ELEMENTS_ZIP"; exit 1; }
  unzip -q "$ELEMENTS_ZIP" -d "$OUT/src/zip"
  mv "$OUT/src/zip/digitalisimo-elements" "$OUT/src/digitalisimo-elements" && rm -rf "$OUT/src/zip"
else
  ELEMENTS_DIR="${ELEMENTS_DIR:-$ROOT/digitalisimo-elements}"
  cp -a "$ELEMENTS_DIR" "$OUT/src/digitalisimo-elements"
fi
[ -f "$OUT/src/digitalisimo-elements/pro-elements.php" ] || { echo "La copia de Elements no contiene pro-elements.php"; exit 1; }
ELEMENTS_VERSION="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([0-9.]*\).*/\1/p' "$OUT/src/digitalisimo-elements/pro-elements.php" | head -1)"
ELEMENTS_SHA="$(cd "$OUT/src/digitalisimo-elements" && find . -type f ! -path './elementor/*' -print0 | LC_ALL=C sort -z | xargs -0 sha256sum | sha256sum | cut -c1-16)"
FIX=0; [ -d "$OUT/src/digitalisimo-elements/modules/digitalisimo-mcp" ] && FIX=1
# Chrome para M9 (estilos calculados): puppeteer-core de PUPPETEER_PATH, de la caché de npx o instalado; CHROME=0 lo omite.
if [ "${CHROME:-1}" != 0 ] && [ -z "${PUPPETEER_PATH:-}" ]; then
  PUPPETEER_PATH=$(ls -d "$HOME"/.npm/_npx/*/node_modules 2>/dev/null | while read -r d; do [ -d "$d/puppeteer-core" ] && echo "$d" && break; done || true)
  if [ -z "$PUPPETEER_PATH" ]; then
    mkdir -p "$TMPDIR/pptr"
    npm install --silent --no-audit --no-fund --prefix "$TMPDIR/pptr" puppeteer-core@25.12.0 >/dev/null 2>&1 && PUPPETEER_PATH="$TMPDIR/pptr/node_modules" || PUPPETEER_PATH=''
  fi
fi
export PUPPETEER_PATH="${PUPPETEER_PATH:-}"

start=$(date +%s)
fail=0
summary='[]'
host="127.0.0.1:$PORT"
for php in ${PHPS:-7.4 8.2 8.5}; do
  dir="$OUT/php-$php"
  rm -rf "$dir"; mkdir -p "$dir/probe"
  cp "$D/setup.php" "$D/probe.php" "$dir/probe/"
  head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n' > "$dir/probe/token.txt"
  cat > "$dir/blueprint.json" <<JSON
{"preferredVersions":{"php":"$php","wp":"latest"},"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{"WP_ENVIRONMENT_TYPE":"local"}},
{"step":"runPHP","code":"<?php require '/digi-probe/setup.php';"}]}
JSON
  if fuser -n tcp "$PORT" >/dev/null 2>&1; then echo "== PHP $php: el puerto $PORT está ocupado por otro proceso; usa PORT=…"; fail=1; continue; fi
  begun=$(date +%s)
  $CLI server --php="$php" --port="$PORT" --workers=1 --site-url="http://$host" --blueprint="$dir/blueprint.json" \
    --mount="$OUT/src/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements" \
    --mount="$dir/probe:/digi-probe" > "$dir/server.log" 2>&1 &
  SERVER=$!
  ready=0
  for i in $(seq 1 400); do
    sleep 3
    kill -0 "$SERVER" 2>/dev/null || break
    [ -f "$dir/probe/setup-error.txt" ] && break
    if [ -s "$dir/probe/setup.json" ] && [ "$(curl -s -o /dev/null -w '%{http_code}' -m 60 "http://$host/?rest_route=%2F")" = 200 ]; then ready=1; break; fi
  done
  if [ "$ready" != 1 ]; then
    echo "== PHP $php: el sitio no quedó listo"; cat "$dir/probe/setup-error.txt" 2>/dev/null || true; tail -20 "$dir/server.log"
    stop_server; fail=1; continue
  fi
  echo "== PHP $php: sitio listo en $(( $(date +%s) - begun )) s (puerto $PORT) · Elements $ELEMENTS_VERSION ($ELEMENTS_SHA) · arreglo $FIX"
  status=0
  EXPECT_FAIL="${EXPECT_FAIL:-0}" node "$D/run.mjs" "$PORT" "$host" "$dir" || status=1
  if [ -s "$dir/probe/fatal.log" ]; then echo "== PHP $php: errores fatales"; cat "$dir/probe/fatal.log"; status=1; fi
  if grep -qE 'PHP Fatal error|Uncaught' "$dir/server.log"; then echo "== PHP $php: errores de PHP en el servidor"; grep -E 'PHP Fatal error|Uncaught' "$dir/server.log" | head -5; status=1; fi
  if [ -s "$dir/probe/php-errors.log" ]; then echo "== PHP $php: avisos de PHP del módulo"; sort "$dir/probe/php-errors.log" | uniq -c | head -10; status=1; else echo "== PHP $php: ningún aviso de PHP del módulo"; fi
  [ "$status" = 0 ] || fail=1
  summary="$(node -e '
const [list, php, file, seconds, status] = process.argv.slice(1);
const all = JSON.parse(list);
let r = {}; try { r = JSON.parse(require("fs").readFileSync(file, "utf8")); } catch {}
all.push({ php, status: status === "0" ? "ok" : "falla", wp: r.wp || null, checks: r.checks || 0, failures: r.failures ?? null, expected_failures: r.expected_failures ?? null, seconds: Number(seconds) });
console.log(JSON.stringify(all));
' "$summary" "$php" "$dir/result.json" "$(( $(date +%s) - begun ))" "$status")"
  stop_server
  sleep 2
done
node -e '
const [out, runs, version, sha, fix, seconds] = process.argv.slice(1);
require("fs").writeFileSync(out + "/summary.json", JSON.stringify({ elements: version, elements_sha: sha, fix: fix === "1", runs: JSON.parse(runs), seconds: Number(seconds) }, null, 1));
' "$OUT" "$summary" "$ELEMENTS_VERSION" "$ELEMENTS_SHA" "$FIX" "$(( $(date +%s) - start ))"
[ $fail -eq 0 ] || { echo 'CSS del MCP: ensayo con fallos'; exit 1; }
echo "CSS del MCP validado en Playground en $(( $(date +%s) - start )) s."
