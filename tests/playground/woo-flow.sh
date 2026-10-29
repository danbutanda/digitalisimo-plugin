#!/usr/bin/env bash
# WooCommerce visual de DIGITALÍSIMO Elements de punta a punta (etapa F4-Woo, V.16.4): Playground en modo servidor con
# WooCommerce 11.2.1 fijado (la copia oficial de reference/, comprobada con su sha256 y montada; nada de wordpress.org),
# una tienda de prueba por sitio (woo-flow/store.php: productos simples y variables, zona con «Envío gratuito», cupones y
# «Contra reembolso») y Chrome sin interfaz. Manual: no corre en CI. Deja $OUT/summary.json con el SHA, el modo,
# WooCommerce, la preparación de cada sitio y el estado de cada escenario (ok, falla, parcial o pendiente).
#
#   W0  Preparación y sin WooCommerce          W7  Barra de envío con las tarifas reales
#   W1  Lista de deseos de invitado            W8  Cupón en el mini carrito
#   W2  Lista con sesión (y Store API)         W9  Filtros en el Loop Grid
#   W3  Caché de página simulada               W10 Filtros en la tienda («Consulta actual»)
#   W4  Vista rápida accesible                 W11 Compra en modo de prueba (pago de bloques y clásico)
#   W5  Vista rápida y compra                  W12 Seguridad de las rutas REST
#   W6  Muestras de variación                  W13 Vuelta atrás a 4.3.0.165 y guarda hacia delante
# Hoy sólo W0 comprueba algo (la preparación: WooCommerce 11.2.1 activo y una compra completa como invitado con cupón,
# envío gratuito y «Contra reembolso»); los demás están descritos en woo-flow/run.cjs y salen «pendiente» hasta que
# existan sus widgets.
#
# Uso: bash tests/playground/woo-flow.sh
#   SCENARIOS=W0,W5    escenarios (por defecto todos)       KEEP=1   prepara la tienda y deja el sitio arrancado
#   CORE=bundled|plugin  núcleo incluido (por defecto) o el plugin Elementor de wordpress.org (ELEMENTOR_PIN, 4.3.5)
#   NET=1              red de subdirectorios: sitio principal, /dos/ y /tres/, cada uno con su tienda
#   CACHE=1            caché de elementos del núcleo a 24 h (si no, desactivada)
#   THEME=digitalisimo|block   tema DIGITALÍSIMO (por defecto) o el de bloques de Playground (W6, convivencia con wc-visual)
#   QUICK=1            iteración local (un solo ancho); no vale para la aceptación
#   WOO_ZIP            WooCommerce (tests/playground/woocommerce-pin.sh); obligatorio: sin la copia o con otra suma, se detiene
#   ELEMENTS_DIR       DIGITALÍSIMO Elements a probar (por defecto digitalisimo-elements/ del repositorio)
#   OUT, TMPDIR        resultados (/var/tmp/digi-f4/woo-flow-<modo>) y temporales de Playground (/var/tmp/digi-f4/tmp)
#   PORT               9491 (sitio), 9492 (NET=1), 9493 (CORE=plugin); 9494 queda para el sitio sin WooCommerce de W0.
#                      El bloque 9490–9499 es de este arnés: cualquier otro puerto se rechaza.
#   LIVE=1             monta las carpetas del árbol de trabajo; por defecto, una copia fija tomada al empezar ($OUT/src)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
STARTED="$(date +%s)"
export TMPDIR="${TMPDIR:-/var/tmp/digi-f4/tmp}"
mkdir -p "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
W="$ROOT/tests/playground/woo-flow"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
CORE="${CORE:-bundled}" NET="${NET:-}" CACHE="${CACHE:-}" QUICK="${QUICK:-}" THEME="${THEME:-digitalisimo}"
ELEMENTOR_PIN="${ELEMENTOR_PIN:-4.3.5}"
ELEMENTS_DIR="${ELEMENTS_DIR:-$ROOT/digitalisimo-elements}"
case "$CORE" in bundled|plugin) ;; *) echo "CORE debe ser bundled o plugin"; exit 1;; esac
case "$THEME" in digitalisimo|block) ;; *) echo "THEME debe ser digitalisimo o block"; exit 1;; esac
for flag in NET CACHE QUICK; do case "${!flag}" in ''|0|1) ;; *) echo "$flag debe ser 0 o 1"; exit 1;; esac; done
[ "$NET" = 0 ] && NET=''; [ "$CACHE" = 0 ] && CACHE=''; [ "$QUICK" = 0 ] && QUICK=''
[ -f "$ELEMENTS_DIR/pro-elements.php" ] || { echo "ELEMENTS_DIR no contiene pro-elements.php: $ELEMENTS_DIR"; exit 1; }

PARTS=()
[ "$CORE" = plugin ] && PARTS+=("plugin-$ELEMENTOR_PIN")
[ -n "$NET" ] && PARTS+=(net)
[ "$THEME" = block ] && PARTS+=(block)
[ -n "$CACHE" ] && PARTS+=(cache)
MODE_LABEL="$(IFS=+; echo "${PARTS[*]:-}")"
[ -z "$MODE_LABEL" ] && MODE_LABEL=normal
OUT="${OUT:-/var/tmp/digi-f4/woo-flow-$MODE_LABEL}"
if [ "$CORE" = plugin ]; then PORT="${PORT:-9493}"; elif [ -n "$NET" ]; then PORT="${PORT:-9492}"; else PORT="${PORT:-9491}"; fi
# Multisite no admite un puerto en la URL: la red se sirve como http://playground.test y Chrome y Node resuelven ese
# host a 127.0.0.1:$PORT.
if [ -n "$NET" ]; then SITE_HOST="playground.test"; else SITE_HOST="127.0.0.1:$PORT"; fi

# Puertos: sólo los del bloque 9490–9499 (V.16.4), y sólo se libera uno ocupado por una corrida anterior de este arnés.
case "$PORT" in 949[0-9]) ;; *) echo "woo-flow.sh usa el bloque 9490–9499 (9491 sitio, 9492 NET=1, 9493 CORE=plugin): $PORT es de otro arnés"; exit 1;; esac
free_port() {
  local port="$1" pids pid cmd
  pids="$(fuser -n tcp "$port" 2>/dev/null | tr -s ' ' || true)"
  [ -z "${pids// /}" ] && return 0
  for pid in $pids; do
    cmd="$(tr '\0' ' ' < "/proc/$pid/cmdline" 2>/dev/null || true)"
    case "$cmd" in
      *playground*"$OUT"*) ;;
      *) echo "El puerto $port está ocupado por otro proceso ($pid: ${cmd:0:120}); no se libera"; return 1;;
    esac
  done
  fuser -k -n tcp "$port" >/dev/null 2>&1 || true
  sleep 1
}
free_port "$PORT"

mkdir -p "$OUT"
# Playground copia el sitio en $TMPDIR (unos 400 MB) y Chrome guarda allí su perfil.
for dir in "$TMPDIR" "$OUT"; do
  free_kb="$(df -Pk "$dir" 2>/dev/null | awk 'NR == 2 { print $4 }')"
  [ -z "$free_kb" ] || [ "$free_kb" -ge 2097152 ] || { echo "$dir tiene $((free_kb / 1024)) MB libres: hacen falta 2 GB"; exit 1; }
done
rm -f "$OUT"/{setup-error.txt,fatal.log,server.log,summary.json,run.log,env.json,woocommerce.json}
rm -rf "$OUT/shots" && mkdir -p "$OUT/shots"
cp "$W"/{activate.php,probe.php,store.php} "$OUT/"
# Preparación y operaciones de sonda de cada escenario (woo-flow/setup/*.php y woo-flow/ops/*.php).
rm -rf "$OUT/setup" "$OUT/ops"
for dir in setup ops; do [ -d "$W/$dir" ] && cp -r "$W/$dir" "$OUT/$dir"; done

# WooCommerce 11.2.1 fijado y obligatorio: sin la copia o con otra suma, el arnés se detiene (V.16.1).
. "$ROOT/tests/playground/woocommerce-pin.sh"
WOO_REQUIRE=1 woo_pin "$OUT/woocommerce" "$OUT/woocommerce.json" || exit 1

# Copia fija de lo que se prueba: un cambio a mitad de corrida no mezcla versiones.
if [ "${LIVE:-0}" = 1 ]; then
  SRC_ELEMENTS="$ELEMENTS_DIR"; SRC_THEME="$ROOT/digitalisimo-theme"
else
  rm -rf "$OUT/src" && mkdir -p "$OUT/src"
  cp -a "$ELEMENTS_DIR" "$OUT/src/digitalisimo-elements"
  cp -a "$ROOT/digitalisimo-theme" "$OUT/src/digitalisimo-theme"
  git -C "$ROOT" status --porcelain -- digitalisimo-elements digitalisimo-theme > "$OUT/src/dirty.txt" 2>/dev/null || true
  SRC_ELEMENTS="$OUT/src/digitalisimo-elements"; SRC_THEME="$OUT/src/digitalisimo-theme"
fi
ELEMENTS_SHA="$(cd "$SRC_ELEMENTS" && find . -type f ! -path './elementor/*' -print0 | LC_ALL=C sort -z | xargs -0 sha256sum | sha256sum | cut -c1-16)"
ELEMENTS_VERSION="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([0-9.]*\).*/\1/p' "$SRC_ELEMENTS/pro-elements.php" | head -1)"
GIT_SHA="$(git -C "$ROOT" rev-parse --short HEAD 2>/dev/null || echo '')"
TOKEN="$(head -c 18 /dev/urandom | od -An -tx1 | tr -d ' \n')"

node -e '
const [out, label, token, core, pin, net, cache, theme, host] = process.argv.slice(1);
require("fs").writeFileSync(out + "/mode.json", JSON.stringify({ label, token, core, elementor_pin: core === "plugin" ? pin : "", net: net === "1", cache: cache === "1", theme, host }, null, 1));
' "$OUT" "$MODE_LABEL" "$TOKEN" "$CORE" "$ELEMENTOR_PIN" "${NET:-0}" "${CACHE:-0}" "$THEME" "$SITE_HOST"

STEPS=()
[ -n "$NET" ] && STEPS+=('{"step":"enableMultisite"}')
[ "$CORE" = plugin ] && STEPS+=("{\"step\":\"installPlugin\",\"pluginData\":{\"resource\":\"url\",\"url\":\"https://downloads.wordpress.org/plugin/elementor.$ELEMENTOR_PIN.zip\"},\"options\":{\"activate\":false}}")
STEPS+=('{"step":"runPHP","code":"<?php require '"'"'/wordpress/wp-content/probe/activate.php'"'"';"}')
printf '{"steps":[%s]}\n' "$(IFS=,; echo "${STEPS[*]}")" > "$OUT/blueprint.json"

MOUNTS=(--mount="$SRC_ELEMENTS:/wordpress/wp-content/plugins/digitalisimo-elements"
  --mount="$SRC_THEME:/wordpress/wp-content/themes/digitalisimo-theme"
  --mount="$OUT:/wordpress/wp-content/probe"
  "$(woo_mount)")

echo "woo-flow · modo $MODE_LABEL · Elements $ELEMENTS_VERSION ($ELEMENTS_SHA) · WooCommerce $WOO_VERSION · puerto $PORT · $OUT"
$CLI server --port="$PORT" --workers=1 --site-url="http://$SITE_HOST" --blueprint="$OUT/blueprint.json" "${MOUNTS[@]}" > "$OUT/server.log" 2>&1 &
SERVER=$!
stop_server() { kill $SERVER 2>/dev/null || true; for i in 1 2 3; do fuser -k -n tcp "$PORT" >/dev/null 2>&1 || break; sleep 1; done; }
trap 'stop_server; playground_cleanup' EXIT
trap 'exit 130' INT TERM
ping_site() { curl -s -m 60 -H "Host: $SITE_HOST" "http://127.0.0.1:$PORT/?digi_wf=ping&token=$TOKEN" 2>/dev/null; }
ready=0
for i in $(seq 1 300); do
  sleep 3
  if ! kill -0 $SERVER 2>/dev/null; then echo 'Playground terminó durante la preparación:'; tail -20 "$OUT/server.log"; exit 1; fi
  [ -f "$OUT/setup-error.txt" ] && break
  if ping_site | grep -q '"ready":true'; then ready=1; break; fi
done
[ -f "$OUT/setup-error.txt" ] && { echo "Preparación incompleta:"; cat "$OUT/setup-error.txt"; exit 1; }
[ "$ready" = 1 ] || { echo 'El sitio no quedó listo'; tail -20 "$OUT/server.log"; exit 1; }
echo "Sitio arrancado en $(( $(date +%s) - STARTED )) s"

node -e '
const env = Object.fromEntries(process.argv.slice(2).map((kv) => [kv.slice(0, kv.indexOf("=")), kv.slice(kv.indexOf("=") + 1)]));
require("fs").writeFileSync(env.OUT + "/env.json", JSON.stringify(env, null, 1));
' x "OUT=$OUT" "ROOT=$ROOT" "BASE=http://$SITE_HOST" "PORT=$PORT" "HOST=$SITE_HOST" "TOKEN=$TOKEN" "MODE_LABEL=$MODE_LABEL" \
  "NET=${NET:-0}" "CORE=$CORE" "THEME=$THEME" "CACHE=${CACHE:-0}" "ELEMENTS_DIR=$ELEMENTS_DIR" "ELEMENTS_VERSION=$ELEMENTS_VERSION" \
  "ELEMENTS_SHA=$ELEMENTS_SHA" "GIT_SHA=$GIT_SHA" "LIVE=${LIVE:-0}" "STARTED=$STARTED"

PUPPETEER_PATH="${PUPPETEER_PATH:-$(ls -d "$HOME"/.npm/_npx/*/node_modules 2>/dev/null | while read -r d; do [ -d "$d/puppeteer-core" ] && echo "$d" && break; done)}"
[ -n "$PUPPETEER_PATH" ] || { echo "No se encontró puppeteer-core: exportar PUPPETEER_PATH"; exit 1; }
status=0
ENV_FILE="$OUT/env.json" SCENARIOS="${SCENARIOS:-}" QUICK="$QUICK" KEEP="${KEEP:-0}" NODE_PATH="$PUPPETEER_PATH" node "$W/run.cjs" || status=$?
if [ "${KEEP:-0}" = 1 ] && [ "$status" = 0 ]; then
  echo "KEEP=1: tienda lista en http://$SITE_HOST (puerto $PORT, token $TOKEN). Escenarios a mano:"
  echo "  ENV_FILE=$OUT/env.json SCENARIOS=W0 NODE_PATH=$PUPPETEER_PATH node $W/run.cjs   · Ctrl+C para terminar"
  while kill -0 $SERVER 2>/dev/null; do sleep 5; done
  exit 0
fi
[ -s "$OUT/fatal.log" ] && { echo "Errores fatales:"; cat "$OUT/fatal.log"; status=1; }
echo "Resumen: $OUT/summary.json · duración: $(( $(date +%s) - STARTED )) s"
exit $status
