#!/usr/bin/env bash
# La plantilla de página (`_wp_page_template`) de DIGITALÍSIMO Elements de punta a punta, en WordPress Playground (sin
# Chrome): el MCP del sitio activo, una contraseña de aplicación, JSON-RPC real contra /wp-json/elementor/mcp y la REST
# de WordPress (/wp/v2/pages/<id>) desde elements-page-template/run.mjs. Regla del dueño (2026-11-24, digitalisimo.lat:
# cambiar el slug o el título por la API dejó la página sin su lienzo): toda actualización que no menciona la plantilla
# la conserva.
#   T1/T2  update-page-settings con post_title y con post_name    T3/T4  REST con slug y con title
#   T5     REST sobre un cambio pendiente del MCP y publish-document
#   T6     plantilla pedida con un autoguardado pendiente: publish-document la conserva
#   T7     borrador con lienzo: build-composition, título y publicar  T8  post_status publish con post_title
#   T9     la página publicada sale con page-template-elementor_canvas
# Uso: bash tests/playground/elements-page-template.sh   (en la PC: bash /var/tmp/digi-wt/pc-run.sh <worktree> -- bash tests/playground/elements-page-template.sh)
#   PHPS           versiones de PHP de Playground, una corrida por versión (por defecto «7.4 8.5»)
#   OUT, TMPDIR    resultados (por defecto /var/tmp/digi-page-template) y temporales de Playground
#   PORT           puerto (9801); el bloque 9800–9809 es de este arnés
#   ELEMENTS_DIR   DIGITALÍSIMO Elements a probar (por defecto digitalisimo-elements/ del repositorio; se copia fijo)
#   ELEMENTS_ZIP   o un ZIP publicado (p. ej. dist/digitalisimo-elements-4.3.0.195.zip) para ver qué falla antes
# Deja $OUT/php-<versión>/result.txt y result.json y $OUT/summary.json. Al terminar borra la contraseña de aplicación
# del ensayo y la copia del plugin.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
D="$ROOT/tests/playground/elements-page-template"
export OUT="${OUT:-/var/tmp/digi-page-template}"
export TMPDIR="${TMPDIR:-/var/tmp/digi-page-template/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PORT="${PORT:-9801}"
case "$PORT" in 980[0-9]) ;; *) echo "elements-page-template.sh usa el bloque 9800–9809: $PORT es de otro arnés"; exit 1;; esac
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
  cp -a "${ELEMENTS_DIR:-$ROOT/digitalisimo-elements}" "$OUT/src/digitalisimo-elements"
fi
[ -f "$OUT/src/digitalisimo-elements/pro-elements.php" ] || { echo "La copia de Elements no contiene pro-elements.php"; exit 1; }
ELEMENTS_VERSION="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([0-9.]*\).*/\1/p' "$OUT/src/digitalisimo-elements/pro-elements.php" | head -1)"
FIX=0; [ -f "$OUT/src/digitalisimo-elements/modules/digitalisimo-mcp/class-page-template.php" ] && FIX=1

start=$(date +%s)
fail=0
summary='[]'
host="127.0.0.1:$PORT"
for php in ${PHPS:-7.4 8.5}; do
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
  echo "== PHP $php: sitio listo en $(( $(date +%s) - begun )) s (puerto $PORT) · Elements $ELEMENTS_VERSION · arreglo $FIX"
  status=0
  node "$D/run.mjs" "$PORT" "$host" "$dir" || status=1
  if [ -s "$dir/probe/fatal.log" ]; then echo "== PHP $php: errores fatales"; cat "$dir/probe/fatal.log"; status=1; fi
  if grep -qE 'PHP Fatal error|Uncaught' "$dir/server.log"; then echo "== PHP $php: errores de PHP en el servidor"; grep -E 'PHP Fatal error|Uncaught' "$dir/server.log" | head -5; status=1; fi
  [ -s "$dir/probe/template-writes.log" ] && cp "$dir/probe/template-writes.log" "$dir/template-writes.log"
  if [ -s "$dir/probe/php-errors.log" ]; then echo "== PHP $php: avisos de PHP del módulo"; sort "$dir/probe/php-errors.log" | uniq -c | head -10; status=1; else echo "== PHP $php: ningún aviso de PHP del módulo"; fi
  [ "$status" = 0 ] || fail=1
  summary="$(node -e '
const [list, php, file, seconds, status] = process.argv.slice(1);
const all = JSON.parse(list);
let r = {}; try { r = JSON.parse(require("fs").readFileSync(file, "utf8")); } catch {}
all.push({ php, status: status === "0" ? "ok" : "falla", wp: r.wp || null, checks: r.checks || 0, failures: r.failures ?? null, failed: r.failed || [], seconds: Number(seconds) });
console.log(JSON.stringify(all));
' "$summary" "$php" "$dir/result.json" "$(( $(date +%s) - begun ))" "$status")"
  stop_server
  sleep 2
done
node -e '
const [out, runs, version, fix, seconds] = process.argv.slice(1);
require("fs").writeFileSync(out + "/summary.json", JSON.stringify({ elements: version, fix: fix === "1", runs: JSON.parse(runs), seconds: Number(seconds) }, null, 1));
' "$OUT" "$summary" "$ELEMENTS_VERSION" "$FIX" "$(( $(date +%s) - start ))"
[ $fail -eq 0 ] || { echo 'Plantilla de página: ensayo con fallos'; exit 1; }
echo "Plantilla de página validada en Playground en $(( $(date +%s) - start )) s."
