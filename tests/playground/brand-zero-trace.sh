#!/usr/bin/env bash
# Auditoría de cero rastro de «elementor» en WordPress Playground con Chrome (docs/MARCA_PROPIA.md, prueba de aceptación
# de DECISIONES): front, escritorio, editor, MCP con nombre propio, /wp-json/ y URL directas a archivos del paquete.
# Cuenta por capa (brand-zero-trace/run.cjs) y deja $OUT/result.json, que tests/brand-zero-trace.mjs --sitio suma a las
# capas del ZIP. En la fase 0 falla en lo que cubre la fase 2: el conteo dice cuánto falta.
# Uso: bash tests/playground/brand-zero-trace.sh   (en la PC: pc-run.sh <worktree> -- bash tests/playground/brand-zero-trace.sh)
#   MARCA=on|off   la marca propia encendida (por defecto on; forzada para no depender de complementos)
#   PROTO=1        además el prototipo de la capa del front (scripts/marca-prototipo/buffer.php) con el espejo generado
#                  por scripts/marca-espejo.mjs en la copia del plugin
#   MOTOR=1        el núcleo en `motor/` como en el ZIP de la D-9 (scripts/marca-prototipo/zip-motor.sh); implica el espejo
#   ELEMENTS_DIR   Elements a auditar (por defecto digitalisimo-elements/ del repositorio; se copia), p. ej. un
#                  `git archive` de origin/main para el «antes»
#   PHP            versión de PHP de Playground (8.2)
#   OUT, TMPDIR, PORT (9651; bloque 9650–9659)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
D="$ROOT/tests/playground/brand-zero-trace"
export OUT="${OUT:-/var/tmp/digi-brand-zero-trace}"
export TMPDIR="${TMPDIR:-$OUT/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PORT="${PORT:-9651}"
case "$PORT" in 965[0-9]) ;; *) echo "brand-zero-trace.sh usa el bloque 9650–9659"; exit 1;; esac
PUPPETEER_PATH="${PUPPETEER_PATH:-$(ls -d "$HOME"/.npm/_npx/*/node_modules 2>/dev/null | while read -r d; do [ -d "$d/puppeteer-core" ] && echo "$d" && break; done)}"
[ -n "$PUPPETEER_PATH" ] || { echo "No se encontró puppeteer-core: exportar PUPPETEER_PATH"; exit 1; }
export PUPPETEER_PATH
SERVER=''
stop_server() { [ -n "$SERVER" ] && kill "$SERVER" 2>/dev/null || true; for i in 1 2 3; do fuser -k -n tcp "$PORT" >/dev/null 2>&1 || break; sleep 1; done; SERVER=''; }
trap 'stop_server; rm -rf "$OUT/src" "$OUT/probe/secrets.json"; playground_cleanup' EXIT
trap 'exit 130' INT TERM

rm -rf "$OUT/src" "$OUT/probe" && mkdir -p "$OUT/src" "$OUT/probe"
cp -a "${ELEMENTS_DIR:-$ROOT/digitalisimo-elements}" "$OUT/src/digitalisimo-elements"
rm -rf "$OUT/src/digitalisimo-elements/bdthemes-element-pack"
PROTO="${PROTO:-}"
if [ "${MOTOR:-}" = 1 ]; then
  mv "$OUT/src/digitalisimo-elements/elementor" "$OUT/src/digitalisimo-elements/motor"
  rm -f "$OUT/src/digitalisimo-elements/motor/readme.txt"
  PROTO=1
fi
if [ "$PROTO" = 1 ]; then
  node "$ROOT/scripts/marca-espejo.mjs" --out "$OUT/src/digitalisimo-elements/marca" --motor "$([ "${MOTOR:-}" = 1 ] && echo motor || echo elementor)"
  cp "$ROOT/scripts/marca-prototipo/buffer.php" "$D/proto.php" "$OUT/probe/"
fi
cp "$D/setup.php" "$D/probe.php" "$OUT/probe/"
printf '{"marca":"%s","proto":%s}\n' "${MARCA:-on}" "$([ "$PROTO" = 1 ] && echo true || echo false)" > "$OUT/probe/options.json"
php="${PHP:-8.2}"
cat > "$OUT/blueprint.json" <<JSON
{"preferredVersions":{"php":"$php","wp":"latest"},"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{"WP_ENVIRONMENT_TYPE":"local"}},{"step":"runPHP","code":"<?php require '/digi-probe/setup.php';"}]}
JSON
host="127.0.0.1:$PORT"
$CLI server --php="$php" --port="$PORT" --workers=1 --site-url="http://$host" --blueprint="$OUT/blueprint.json" \
  --mount="$OUT/src/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements" --mount="$OUT/probe:/digi-probe" > "$OUT/server.log" 2>&1 &
SERVER=$!
ready=0
for i in $(seq 1 400); do
  sleep 3
  kill -0 "$SERVER" 2>/dev/null || break
  [ -f "$OUT/probe/setup-error.txt" ] && break
  if [ -s "$OUT/probe/setup.json" ] && [ "$(curl -s -o /dev/null -w '%{http_code}' -m 60 "http://$host/?rest_route=%2F")" = 200 ]; then ready=1; break; fi
done
[ "$ready" = 1 ] || { echo "El sitio no quedó listo"; cat "$OUT/probe/setup-error.txt" 2>/dev/null || true; tail -20 "$OUT/server.log"; exit 1; }
echo "Sitio listo (marca ${MARCA:-on}, prototipo ${PROTO:-0}, motor ${MOTOR:-0}): $(cat "$OUT/probe/setup.json")"
status=0
node "$D/run.cjs" "$PORT" "$host" "$OUT/probe" || status=1
cp "$OUT/probe/result.json" "$OUT/result.json" 2>/dev/null || true
[ -s "$OUT/probe/fatal.log" ] && { echo "Errores fatales:"; cat "$OUT/probe/fatal.log"; status=1; }
[ -s "$OUT/probe/buffer-ms.log" ] && echo "Capa del front (ms bytes): $(sort -n "$OUT/probe/buffer-ms.log" | awk '{a[NR]=$1} END {print "mediana " a[int((NR+1)/2)] " ms en " NR " páginas"}')"
node "$ROOT/tests/brand-zero-trace.mjs" --sitio "$OUT/result.json" || true
exit $status
