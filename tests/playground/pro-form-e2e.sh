#!/usr/bin/env bash
# Prueba de punta a punta de envíos de C6 (docs/pro/c6.md): «ningún envío ni acción se pierde ni se duplica». Arranca
# Playground en modo servidor con el núcleo incluido, crea con pro-form-e2e/setup.php dos formularios (básico y por
# pasos con un campo condicional de F7b) en dos páginas cada uno: A con el Form de PRO y B convertido al Formulario
# propio (mapas en borrador incluidos), con las acciones «guardar envío», «correo» y «webhook». Un mu-plugin captura los
# correos (`pre_wp_mail`, sin enviarlos) y las llamadas al receptor local del arnés (`http://receptor.e2e.test/…`, por
# `pre_http_request`). pro-form-e2e/run.cjs envía los formularios en Chrome (error de validación, envío correcto, doble
# envío, pasos) y compara envíos guardados, correos y webhooks de A y de B.
#
# Uso: bash tests/playground/pro-form-e2e.sh      (OUT=/var/tmp/digi-pro-form-e2e, PORT=9761 por defecto)
# Resultado: $OUT/pro-form-e2e.json; sale con 1 si hay alguna falla.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
STARTED="$(date +%s)"
export TMPDIR="${TMPDIR:-/var/tmp}"
. "$ROOT/tests/playground/playground-cleanup.sh"
OUT="${OUT:-/var/tmp/digi-pro-form-e2e}"
PORT="${PORT:-9761}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
ELEMENTS_DIR="${ELEMENTS_DIR:-$ROOT/digitalisimo-elements}"
PUPPETEER_PATH="${PUPPETEER_PATH:-$(ls -d "$HOME"/.npm/_npx/*/node_modules 2>/dev/null | while read -r d; do [ -d "$d/puppeteer-core" ] && echo "$d" && break; done)}"
[ -n "$PUPPETEER_PATH" ] || { echo "No se encontró puppeteer-core: exportar PUPPETEER_PATH"; exit 1; }
case "$OUT" in /tmp/*) echo "OUT no puede estar en /tmp (tmpfs): usar /var/tmp"; exit 1;; esac

if fuser -n tcp "$PORT" >/dev/null 2>&1; then
  for pid in $(fuser -n tcp "$PORT" 2>/dev/null); do
    case "$(tr '\0' ' ' < "/proc/$pid/cmdline" 2>/dev/null || true)" in
      *playground*"$OUT"*) kill "$pid" 2>/dev/null || true;;
      *) echo "El puerto $PORT está ocupado por otro proceso ($pid); no se libera"; exit 1;;
    esac
  done
  sleep 1
fi

mkdir -p "$OUT" "$TMPDIR"
PROBE="$OUT/probe"
rm -rf "$PROBE" "$OUT/src" && mkdir -p "$PROBE" "$OUT/src"
rm -f "$OUT/pro-form-e2e.json" "$OUT/server.log"
cp "$ROOT/tests/playground/pro-ab/activate.php" "$ROOT/tests/playground/pro-form-e2e/setup.php" "$PROBE/"
# Copia fija de lo que se mide (otro agente puede estar editando el árbol).
cp -a "$ELEMENTS_DIR" "$OUT/src/digitalisimo-elements"
cat > "$PROBE/blueprint.json" <<'JSON'
{"steps":[
 {"step":"runPHP","code":"<?php require '/wordpress/wp-content/probe/activate.php';"},
 {"step":"runPHP","code":"<?php require '/wordpress/wp-content/probe/setup.php';"}
]}
JSON

echo "pro-form-e2e · puerto $PORT · $OUT"
$CLI server --port="$PORT" --workers=1 --site-url="http://127.0.0.1:$PORT" --blueprint="$PROBE/blueprint.json" \
  --mount="$OUT/src/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements" --mount="$PROBE:/wordpress/wp-content/probe" > "$OUT/server.log" 2>&1 &
SERVER=$!
stop_server() { kill $SERVER 2>/dev/null || true; for i in 1 2 3; do fuser -k -n tcp "$PORT" >/dev/null 2>&1 || break; sleep 1; done; }
trap 'stop_server; playground_cleanup' EXIT
trap 'exit 130' INT TERM
ping_site() { curl -s -m 30 -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/e2e-basic-a/" 2>/dev/null; }
for i in $(seq 1 200); do
  sleep 3
  if ! kill -0 $SERVER 2>/dev/null; then echo 'Playground terminó antes de tiempo:'; tail -20 "$OUT/server.log"; exit 1; fi
  [ -f "$PROBE/setup-error.txt" ] && break
  [ -f "$PROBE/ready.json" ] && ping_site | grep -q '^200$' && break
done
[ -f "$PROBE/setup-error.txt" ] && { echo "Preparación incompleta:"; cat "$PROBE/setup-error.txt"; exit 1; }
ping_site | grep -q '^200$' || { echo 'El servidor no sirvió las páginas de prueba'; tail -20 "$OUT/server.log"; exit 1; }
status=0
OUT="$OUT" PROBE="$PROBE" BASE="http://127.0.0.1:$PORT" NODE_PATH="$PUPPETEER_PATH" node "$ROOT/tests/playground/pro-form-e2e/run.cjs" || status=$?
[ -s "$PROBE/fatal.log" ] && { echo "Errores fatales de PHP:"; cat "$PROBE/fatal.log"; status=1; }
echo "Duración: $(( $(date +%s) - STARTED )) s"
exit $status
