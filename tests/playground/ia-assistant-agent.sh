#!/usr/bin/env bash
# El Asistente IA de IA Tools como un agente, de punta a punta en WordPress Playground, con WooCommerce 11.2.1
# (copia fijada), DIGITALÍSIMO Elements (MCP del sitio y de la tienda) e IA Tools. Caso plomerito (2026-11-23,
# «Está muy tonto el asistente IA, no hace las cosas como aquí»): el producto del caso, un pie con el WhatsApp del
# sitio y las políticas reales; la conversación real se manda por la REST del Asistente mensaje por mensaje
# (ia-assistant-agent/run.cjs).
#   MODE=simulado (por defecto): la sonda (ia-assistant-agent/probe.php) contesta por api.anthropic.com con el formato
#     real de la API y guioniza el caso; se comprueba la memoria tras 19 consultas, el contacto del sitio, el borrador
#     sin confirmación, «sí hazlo» con la acción pendiente y el modelo claude-opus-5-5.
#   MODE=real: con la API de Anthropic de verdad (lo lanza ia-assistant-real.sh, que pasa la clave). NO va en la CI.
# Uso: bash tests/playground/ia-assistant-agent.sh
#      (en la PC: bash /var/tmp/digi-wt/pc-run.sh --traer-a <dir> <worktree> -- bash tests/playground/ia-assistant-agent.sh)
#   OUT            resultados (por defecto /var/tmp/digi-ias) · PORT 9781 (simulado) / 9782 (real): bloque 9780–9789
#   ELEMENTS_DIR   DIGITALÍSIMO Elements a probar (por defecto digitalisimo-elements/ del repositorio)
#   IA_DIR         IA Tools a probar (por defecto digitalisimo-ia-tools/ del repositorio; p. ej. la de main para comparar)
#   WOO_ZIP        WooCommerce fijado (tests/playground/woocommerce-pin.sh)
#   KEY_FILE       (sólo MODE=real) archivo con la clave de Anthropic: se copia a la sonda, el sitio la lee y la borra,
#                  y al terminar se borra de la opción del sitio. Nunca va en argumentos ni en los resultados.
# Deja $OUT/<modo>/result.txt, result.json, conversacion.json y respuestas.md (y, en real, evaluacion.json y capturas).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
D="$ROOT/tests/playground/ia-assistant-agent"
MODE="${MODE:-simulado}"
# SCENARIO=caso (por defecto: la conversación del caso plomerito de 1.0.72) o mostrar (ia-assistant-mostrar.sh: el caso
# plomerito 2 en una tienda inventada, «primero hacer y mostrar»; sólo con MODE=real). Cada escenario trae su preparación
# y su conductor.
SCENARIO="${SCENARIO:-caso}"
# SCENARIO=marca (ia-assistant-marca.sh, caso plomerito 3): la landing con la marca del sitio, en una tienda inventada con
# el tema DIGITALÍSIMO, cabecera y pie de Elements y 12 productos; CASE=kit (el kit con los colores de la marca) o
# CASE=fabrica (el kit de fábrica y los colores reales sólo en la cabecera y el pie).
# SCENARIO=subir (ia-assistant-subir.sh): soltar dos JPG y un PNG en el Asistente desde Chrome, que lleguen a la biblioteca
# en WebP con el conversor de DIGITALÍSIMO Tools (se monta y se activa sólo en este escenario) y que Claude use uno en un
# borrador. Sólo con MODE=real.
# SCENARIO=simple (ia-assistant-simple.sh, casos plomerito 2, 3 y 4): sin tecnicismos, en una ferretería inventada con
# DIGITALÍSIMO SEO y Skydropx 2.1.5 real (reference/skydropx.2.1.5.zip, comprobado con su sha256). Sólo con MODE=real.
# SCENARIO=aprende (ia-assistant-aprende.sh): el aprendizaje y la caché con la API real, en la tienda de «marca» con sus
# políticas y una página con texto de inyección (CASE=aprende).
EXTRA_MOUNTS=()
case "$SCENARIO" in caso) SETUP=setup.php; RUNNER=run.cjs ;; mostrar) SETUP=setup-mostrar.php; RUNNER=run-mostrar.cjs ;; marca) SETUP=setup-marca.php; RUNNER=run-marca.cjs ;; subir) SETUP=setup-subir.php; RUNNER=run-subir.cjs; EXTRA_MOUNTS=(--mount="$ROOT/digitalisimo-tools:/wordpress/wp-content/plugins/digitalisimo-tools") ;; aprende) SETUP=setup-marca.php; RUNNER=run-aprende.cjs; CASE=aprende ;; simple) SETUP=setup-simple.php; RUNNER=run-simple.cjs; EXTRA_MOUNTS=(--mount="$ROOT/digitalisimo-seo:/wordpress/wp-content/plugins/digitalisimo-seo") ;; *) echo "SCENARIO desconocido: $SCENARIO"; exit 2 ;; esac
export OUT="${OUT:-/var/tmp/digi-ias}"
export TMPDIR="${TMPDIR:-$OUT/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
case "$MODE" in simulado) PORT="${PORT:-9781}" ;; medir) PORT="${PORT:-9789}" ;; real) case "$SCENARIO" in mostrar) PORT="${PORT:-9783}" ;; marca) PORT="${PORT:-9784}" ;; subir) PORT="${PORT:-9785}" ;; aprende) PORT="${PORT:-9787}" ;; simple) PORT="${PORT:-9788}" ;; *) PORT="${PORT:-9782}" ;; esac ;; *) echo "MODE desconocido: $MODE"; exit 2 ;; esac
[ "$MODE" != medir ] || [ "$SCENARIO" = simple ] || { echo "MODE=medir sólo con SCENARIO=simple"; exit 2; }
case "$PORT" in 978[0-9]) ;; *) echo "ia-assistant-agent.sh usa el bloque 9780–9789: $PORT es de otro arnés"; exit 1 ;; esac
ELEMENTS_DIR="${ELEMENTS_DIR:-$ROOT/digitalisimo-elements}"
[ -f "$ELEMENTS_DIR/pro-elements.php" ] || { echo "ELEMENTS_DIR no contiene pro-elements.php: $ELEMENTS_DIR"; exit 1; }
PUPPETEER_PATH="${PUPPETEER_PATH:-$(ls -d "$HOME"/.npm/_npx/*/node_modules 2>/dev/null | while read -r d; do [ -d "$d/puppeteer-core" ] && echo "$d" && break; done || true)}"
if [ "$MODE" = real ] && [ -z "$PUPPETEER_PATH" ]; then
  mkdir -p "$TMPDIR/pptr"
  npm install --silent --no-audit --no-fund --prefix "$TMPDIR/pptr" puppeteer-core@25.12.0 >/dev/null 2>&1 && PUPPETEER_PATH="$TMPDIR/pptr/node_modules"
fi
dir="$OUT/$MODE"
[ "$SCENARIO" = caso ] || dir="$OUT/$SCENARIO-$MODE${CASE:+-$CASE}${RUN_LABEL:+-$RUN_LABEL}"
SERVER=''
stop_server() {
  [ -n "$SERVER" ] && kill "$SERVER" 2>/dev/null || true
  for i in 1 2 3; do fuser -k -n tcp "$PORT" >/dev/null 2>&1 || break; sleep 1; done
  SERVER=''
}
# La clave y el token nunca quedan en los resultados; WooCommerce extraído y la copia de Elements tampoco.
scrub() { rm -f "$dir/probe/k" "$dir/probe/token.txt"; rm -rf "$OUT/src" "$OUT/woocommerce" "$OUT/skydropx"; }
trap 'stop_server; scrub; playground_cleanup' EXIT
trap 'exit 130' INT TERM

. "$ROOT/tests/playground/woocommerce-pin.sh"
# «subir» no usa la tienda: sin WooCommerce (ni su copia fijada).
WOO_MOUNT=()
if [ "$SCENARIO" != subir ]; then
  WOO_REQUIRE=1 woo_pin "$OUT/woocommerce" "$OUT/woocommerce.json" || exit 1
  WOO_MOUNT=("$(woo_mount)")
else
  WOO_VERSION='(no se usa)'
fi
if [ "$SCENARIO" = simple ]; then
  # Skydropx 2.1.5 real, la copia oficial anotada (como skydropx-mcp.sh).
  sky_zip="${SKYDROPX_ZIP:-$ROOT/reference/skydropx.2.1.5.zip}"
  if [ ! -f "$sky_zip" ]; then
    main_root="$(git -C "$ROOT" rev-parse --path-format=absolute --git-common-dir 2>/dev/null || true)"
    [ -n "$main_root" ] && sky_zip="$(dirname "$main_root")/reference/skydropx.2.1.5.zip"
  fi
  [ -f "$sky_zip" ] || { echo "Falta reference/skydropx.2.1.5.zip (o SKYDROPX_ZIP)"; exit 1; }
  [ "$(sha256sum "$sky_zip" | cut -c1-64)" = be61b6e4d996bb324cda3bbf67056443bd6b89f7b1c2faf5e2c8f3b57de29163 ] || { echo "skydropx.2.1.5.zip no es la copia oficial anotada"; exit 1; }
  rm -rf "$OUT/skydropx" && mkdir -p "$OUT/skydropx" && unzip -q "$sky_zip" -d "$OUT/skydropx"
  EXTRA_MOUNTS+=(--mount="$OUT/skydropx/skydropx:/wordpress/wp-content/plugins/skydropx")
fi
rm -rf "$OUT/src" && mkdir -p "$OUT/src"
cp -a "$ELEMENTS_DIR" "$OUT/src/digitalisimo-elements"
rm -rf "$dir"; mkdir -p "$dir/probe"
echo "$MODE" > "$dir/probe/mode.txt"
echo "${CASE:-kit}" > "$dir/probe/case.txt"
head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n' > "$dir/probe/token.txt"
# DUMP=1: cada petición a la API queda entera en probe/bodies/ (para medir el contexto; MODE=medir lo hace siempre).
[ "${DUMP:-0}" = 1 ] && : > "$dir/probe/dump"
if [ "$MODE" = real ]; then
  [ -n "${KEY_FILE:-}" ] && [ -s "$KEY_FILE" ] || { echo "MODE=real necesita KEY_FILE con la clave de Anthropic"; exit 1; }
  ( umask 077; cp "$KEY_FILE" "$dir/probe/k" )
fi
# ASSISTANT_MODEL / ASSISTANT_EFFORT: el modelo fijo del Asistente para este ensayo, por las constantes de wp-config que usa
# DIGITALÍSIMO (Digitalisimo_AI_Assistant_Model::fixed()); vacíos, el de fábrica.
consts='"WP_ENVIRONMENT_TYPE":"local"'
case "${ASSISTANT_MODEL:-}" in "") ;; *[!A-Za-z0-9._-]*) echo "ASSISTANT_MODEL no válido"; exit 2 ;; *) consts="$consts,\"DIGITALISIMO_AI_ASSISTANT_MODEL\":\"$ASSISTANT_MODEL\"" ;; esac
case "${ASSISTANT_EFFORT:-}" in "") ;; low|medium|high|xhigh|max) consts="$consts,\"DIGITALISIMO_AI_ASSISTANT_EFFORT\":\"$ASSISTANT_EFFORT\"" ;; *) echo "ASSISTANT_EFFORT no válido"; exit 2 ;; esac
cat > "$dir/blueprint.json" <<JSON
{"preferredVersions":{"php":"8.2","wp":"latest"},"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{$consts}},
{"step":"runPHP","code":"<?php require '/digi-ias-src/$SETUP';"}]}
JSON
if fuser -n tcp "$PORT" >/dev/null 2>&1; then echo "El puerto $PORT está ocupado por otro proceso; usa PORT=…"; exit 1; fi
host="127.0.0.1:$PORT"
begun=$(date +%s)
$CLI server --port="$PORT" --workers=1 --site-url="http://$host" --blueprint="$dir/blueprint.json" \
  --mount="$OUT/src/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements" \
  --mount="${IA_DIR:-$ROOT/digitalisimo-ia-tools}:/wordpress/wp-content/plugins/digitalisimo.chatbot" \
  --mount="$ROOT/digitalisimo-theme:/wordpress/wp-content/themes/digitalisimo-theme" \
  --mount="$D/probe.php:/wordpress/wp-content/mu-plugins/digi-ias-probe.php" \
  ${EXTRA_MOUNTS[@]+"${EXTRA_MOUNTS[@]}"} \
  --mount="$D:/digi-ias-src" \
  --mount="$dir/probe:/digi-ias" \
  ${WOO_MOUNT[@]+"${WOO_MOUNT[@]}"} > "$dir/server.log" 2>&1 &
SERVER=$!
ready=0
for i in $(seq 1 400); do
  sleep 3
  kill -0 "$SERVER" 2>/dev/null || break
  [ -f "$dir/probe/setup-error.txt" ] && break
  if [ -s "$dir/probe/ready.json" ] && [ "$(curl -s -o /dev/null -w '%{http_code}' -m 60 "http://$host/wp-login.php")" = 200 ]; then ready=1; break; fi
done
# La clave ya está en la opción del sitio (o el arranque falló): el archivo nunca se queda.
rm -f "$dir/probe/k"
if [ "$ready" != 1 ]; then
  echo "== el sitio no quedó listo"; cat "$dir/probe/setup-error.txt" 2>/dev/null || true; tail -20 "$dir/server.log"
  exit 1
fi
echo "== $MODE: sitio listo en $(( $(date +%s) - begun )) s · $(cat "$dir/probe/ready.json") · WooCommerce $WOO_VERSION"
status=0
NODE_PATH="${PUPPETEER_PATH:-}" node "$D/$RUNNER" "$PORT" "$host" "$dir" "$MODE" || status=1
if grep -qE 'PHP Fatal error|Uncaught' "$dir/server.log"; then echo "== errores de PHP en el servidor"; grep -E 'PHP Fatal error|Uncaught' "$dir/server.log" | head -5; status=1; fi
if [ -s "$dir/probe/php-errors.log" ]; then echo "== avisos de PHP de IA Tools"; sort "$dir/probe/php-errors.log" | uniq -c | head -10; status=1; else echo "== ningún aviso de PHP de IA Tools"; fi
# Ninguna clave en los resultados (la real empieza por sk-ant-).
if grep -rqE 'sk-ant-[A-Za-z0-9_-]{20,}' "$dir" --exclude=server.log 2>/dev/null || grep -qE 'sk-ant-api[A-Za-z0-9_-]{20,}' "$dir/server.log" 2>/dev/null; then echo "== HAY UNA CLAVE EN LOS RESULTADOS"; grep -rlE 'sk-ant-[A-Za-z0-9_-]{20,}' "$dir" | head; status=1; else echo "== ninguna clave en los resultados"; fi
stop_server
[ $status -eq 0 ] || { echo "Asistente como agente ($MODE): ensayo con fallos"; exit 1; }
echo "Asistente como agente ($MODE) validado en $(( $(date +%s) - begun )) s."
