#!/usr/bin/env bash
# Costo en el escritorio (wp-admin) de DIGITALÍSIMO SEO, IA Tools y Hosting, sin Chrome salvo CHROME=1.
# Un WordPress de Playground con contenido realista (300 entradas, 60 páginas, 120 medios, 40 usuarios, 300 comentarios;
# en la red, además dos subsitios) y los tres plugins activos; la sonda (seo-admin-perf/probe-mu.php) deja activo en cada
# petición sólo el conjunto que se mide: ninguno, cada plugin solo y los tres. Para cada pantalla (Escritorio, Entradas,
# editar una entrada y su REST, guardar por REST, Páginas, Medios, subir un medio, Plugins, Usuarios, Ajustes,
# Actualizaciones, heartbeat; en la red, sus pantallas y las de un subsitio) da la mediana de LOADS cargas del tiempo
# del servidor, las consultas y la memoria pico, más las peticiones HTTP salientes (ninguna sale: GitHub responde con un
# índice sin novedades), los avisos de PHP con su origen y los recursos encolados de los plugins. Además: pantallas
# propias, cachés remotas vencidas, autoload y cron frente a la foto sin plugins, cada evento de cron de los plugins
# ejecutado una vez y, con CHROME=1, errores de consola y guardar desde el editor de bloques (REST + metaboxes).
# Uso: bash tests/playground/seo-admin-perf.sh            → $OUT/<etiqueta>/results.json y summary.md
#   MODE=single|network  PHPV=8.2|7.4|8.5  OPTIONS=default|on (funciones opcionales encendidas)  WOO=1 (WooCommerce 11.2.1
#   montado de reference/)  SRC=<carpeta con los tres plugins>  SCALE=1 (fracción del contenido; sólo para probar el arnés)  LOADS=7  SETS="none seo ia hosting all"  ONLY=pantalla,…  CHROME=1  LABEL=antes|despues
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
export TMPDIR="${TMPDIR:-/tmp}"
. "$ROOT/tests/playground/playground-cleanup.sh"
D="$ROOT/tests/playground/seo-admin-perf"
MODE="${MODE:-single}"; PHPV="${PHPV:-8.2}"; OPTIONS="${OPTIONS:-default}"; WOO="${WOO:-0}"
LABEL="${LABEL:-medida}"
OUT="${OUT:-/var/tmp/digi-seo-admin-perf}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
case "$MODE" in single) PORT="${PORT:-9871}"; multi='' ;; network) PORT="${PORT:-9872}"; multi='{"step":"enableMultisite"},' ;; *) echo "MODE desconocido: $MODE"; exit 2 ;; esac
TAG="$LABEL-$MODE-php$PHPV-$OPTIONS$([ "$WOO" = 1 ] && echo -woo || true)"
RES="$OUT/$TAG"; RUN="$RES/run"
rm -rf "$RES"; mkdir -p "$RUN/probe"
cp "$D/probe-mu.php" "$D/setup.php" "$RUN/probe/"
echo "$OPTIONS" > "$RUN/probe/options.txt"
echo "${SCALE:-1}" > "$RUN/probe/scale.txt"
head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n' > "$RUN/probe/token.txt"
host="admin-perf-$MODE.test"
MOUNTS=()
# SRC: carpeta con los tres plugins (por defecto, el repositorio); otra copia, p. ej. la base de la rama, da el «antes».
SRC="${SRC:-$ROOT}"
for dir in digitalisimo-seo digitalisimo-ia-tools digitalisimo-hosting; do
  site=$dir; [ "$dir" = digitalisimo-ia-tools ] && site=digitalisimo.chatbot
  [ -f "$SRC/$dir/$( [ "$dir" = digitalisimo-seo ] && echo digitalisimo-integrations.php || ( [ "$dir" = digitalisimo-ia-tools ] && echo digitalisimo-chatbot.php || echo digitalisimo-hosting.php ) )" ] || { echo "Falta $SRC/$dir"; exit 2; }
  MOUNTS+=("--mount=$(cd "$SRC/$dir" && pwd):/wordpress/wp-content/plugins/$site")
done
if [ "$WOO" = 1 ]; then
  WOO_ZIP="${WOO_ZIP:-$ROOT/reference/woocommerce.11.2.1.zip}"
  [ -f "$WOO_ZIP" ] || { echo "Falta $WOO_ZIP"; exit 2; }
  echo "5317434cb381b5fdc7c1dd6ee258324f08c390976aaf8d1199f2a0e9a3ab8586  $WOO_ZIP" | sha256sum -c --quiet - || { echo 'La suma de WooCommerce no coincide'; exit 2; }
  mkdir -p "$RUN/woo"; unzip -q "$WOO_ZIP" -d "$RUN/woo"
  MOUNTS+=("--mount=$RUN/woo/woocommerce:/wordpress/wp-content/plugins/woocommerce")
fi
cat > "$RUN/blueprint.json" <<JSON
{"preferredVersions":{"php":"$PHPV","wp":"latest"},"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{"WP_DEBUG":true,"WP_DEBUG_DISPLAY":false,"WP_DEBUG_LOG":"/digi-ap/debug.log","DISABLE_WP_CRON":true,"WP_ENVIRONMENT_TYPE":"local"}},$multi
{"step":"runPHP","code":"<?php putenv('DIGI_AP_STEP=prepare'); require '/digi-ap/setup.php';"},
{"step":"runPHP","code":"<?php putenv('DIGI_AP_STEP=seed'); require '/digi-ap/setup.php';"}]}
JSON
if fuser -n tcp "$PORT" >/dev/null 2>&1; then echo "el puerto $PORT está ocupado; usa PORT=…"; exit 1; fi
began=$(date +%s)
$CLI server --php="$PHPV" --port="$PORT" --workers=1 --site-url="http://$host" --blueprint="$RUN/blueprint.json" \
  "${MOUNTS[@]}" --mount="$RUN/probe:/digi-ap" > "$RUN/server.log" 2>&1 &
SERVER=$!
stop_server() { kill "$SERVER" 2>/dev/null || true; for i in 1 2 3; do fuser -k -n tcp "$PORT" >/dev/null 2>&1 || break; sleep 1; done; }
trap 'stop_server; playground_cleanup' EXIT
ready=0
for i in $(seq 1 600); do
  sleep 3
  kill -0 "$SERVER" 2>/dev/null || break
  [ -f "$RUN/probe/setup-error.txt" ] && break
  if [ -s "$RUN/probe/setup.json" ] && [ "$(curl -s -o /dev/null -w '%{http_code}' -m 60 -H "Host: $host" "http://127.0.0.1:$PORT/wp-login.php")" = 200 ]; then ready=1; break; fi
done
if [ "$ready" != 1 ]; then echo "== $TAG: el sitio no quedó listo"; cat "$RUN/probe/setup-error.txt" 2>/dev/null || true; tail -30 "$RUN/server.log"; exit 1; fi
echo "== $TAG: sitio listo en $(( $(date +%s) - began )) s · $(cat "$RUN/probe/setup.json")"
if [ "${CHROME:-0}" = 1 ] && [ -z "${PUPPETEER_PATH:-}" ]; then
  for d in "$HOME"/.npm/_npx/*/node_modules; do [ -d "$d/puppeteer-core" ] && { PUPPETEER_PATH="$d"; break; }; done
  # Sin copia en la caché de npx (la PC), una instalación temporal de la misma versión que usan las otras pruebas.
  if [ -z "${PUPPETEER_PATH:-}" ]; then
    npm install --prefix "$RUN/pptr" --no-audit --no-fund --silent puppeteer-core@25.12.0 >/dev/null 2>&1 || { echo 'No se pudo instalar puppeteer-core'; exit 1; }
    PUPPETEER_PATH="$RUN/pptr/node_modules"
  fi
  export PUPPETEER_PATH
fi
status=0
node "$D/measure.mjs" "$PORT" "$host" "$RUN/probe" "$RES" || status=$?
cp "$RUN/probe/log.jsonl" "$RES/log.jsonl" 2>/dev/null || true
[ -s "$RUN/probe/debug.log" ] && cp "$RUN/probe/debug.log" "$RES/debug.log"
grep -E 'Fatal error|Uncaught' "$RUN/server.log" | head -5 > "$RES/server-fatal.txt" || true
[ -s "$RES/server-fatal.txt" ] && { echo "Errores fatales en el servidor:"; cat "$RES/server-fatal.txt"; status=1; }
[ -f "$RES/results.json" ] && node "$D/report.mjs" "$RES/results.json" > "$RES/summary.md" && cat "$RES/summary.md"
stop_server; sleep 1
rm -rf "$RUN/woo" "$RUN/pptr"
echo "== $TAG en $(( $(date +%s) - began )) s → $RES"
exit $status
