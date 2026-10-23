#!/usr/bin/env bash
# Capacidades de diseño de DIGITALÍSIMO Elements (etapa F7a, escenarios DS1–DS13 de la especificación, V.13 y 6.4) en
# WordPress Playground (modo servidor, un worker) y Chrome sin interfaz. Manual: no corre en CI. Monta una copia fija de
# digitalisimo-elements/, digitalisimo-theme/ y digitalisimo-seo/ ($OUT/src), la sonda design/probe.php (mu-plugin) y
# los documentos de design/cases.json, y deja $OUT/summary.json con el SHA, el modo, cada escenario con sus
# comprobaciones y su duración, y la duración total. Antes de publicar F7a, los summary.json de cada modo van a
# docs/fusion/f7a-evidence.json.
#
#   DS1  presets: acciones por elementor_ajax (su `code`), estilo calculado = el del preset en 360, 768 y 1280 px, lo
#        local gana, vista previa = front, «Usar sólo el preset» y «Desvincular y copiar estilos» (píxeles ≤ 0,5 %),
#        borrar con usos, editor y rol «sólo contenido» (403 y vínculo conservado), CSS del kit intacto
#   DS2  degradados de N paradas, cónicos y repetidos: valor calculado en tres anchos y en hover, front = vista previa
#   DS3  máscaras (contenedor, sección, columna, SVG del sitio, SVG hostil): `data:` en porcentaje, sin peticiones
#   DS4  patrones: ::after, clic sobre el botón y el desplegable del menú sobre el contenedor siguiente (aprueba o
#        reprueba con «Elevar» por defecto; con «Elevar» apagado y con índice Z propio, se anota)
#   DS5  «Visitó la página»: con la WP Consent API y sin ella, opción page_visits_no_consent, filtro, borrador,
#        revisión e importación en «agregar», caché de página y purga
#   DS6  prioridad manual del Theme Builder        DS7  controles de documento con el tema DIGITALÍSIMO, Hello y bucles
#   DS8  sticky «al fijarse»: sin destello, salto ≤ 1 px, `bottom`, dispositivo fuera de sticky_on y editor
#   DS9  caché de elementos a 24 h (CACHE=1)       DS10 plugin Elementor original (CORE=plugin)
#   DS11 v4 (V4=1)                                 DS12 red de subdirectorios con IDs repetidos (NET=1)
#   DS13 vuelta a 4.3.0.163 (asset de la Release v2026.10.21.417) instalada sobre el resultado
#
# Uso: bash tests/playground/design.sh                 normal: DS1–DS8 y DS13
#      CACHE=1 bash tests/playground/design.sh         DS1–DS9 con la caché de elementos a 24 h (cada visita, dos veces)
#      CORE=plugin ELEMENTOR_PIN=4.3.5 ...             DS10: DS1–DS8 rápidos con el plugin Elementor de wordpress.org
#      V4=1 ...  ·  NET=1 ...  ·  WOO=1 ...            DS11 · DS12 · DS1 con un widget de WooCommerce
# Variables:
#   SCENARIOS=DS1,DS5   escenarios (por defecto los del modo)       QUICK=1  un solo ancho (1280 px), sin editor
#   ELEMENTOR_PIN       versión del plugin Elementor con CORE=plugin (por defecto 4.3.5; la mínima admitida es 4.0.0)
#   OLD_ZIP             ZIP 4.3.0.163 para DS13 (por defecto la copia de /var/tmp/digi-f7a/rel163/zip o se descarga);
#                       se comprueba su sha256
#   HELLO_ZIP           Hello 3.5.1 (reference/hello-elementor.3.5.1.zip, también la del worktree principal)
#   WOO_ZIP             WooCommerce de WOO=1 (tests/playground/woocommerce-pin.sh: por defecto 11.2.1 de reference/, con su
#                       sha256, montado); sin copia local, la última de wordpress.org, anotado en summary.json
#   OUT, TMPDIR         resultados (por defecto /var/tmp/digi-f7a/design-<modo>) y temporales (/var/tmp/digi-f7a/tmp)
#   PORT                9481 (sitio individual) o 9482 (NET=1); se rechazan los de los demás arneses
#   LIVE=1              monta el árbol de trabajo (DS13 queda fuera: reemplaza la copia montada)
#   KEEP=1              sólo prepara el sitio y lo deja arrancado ($OUT/env.json sirve para correr run.cjs a mano)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
STARTED="$(date +%s)"
export TMPDIR="${TMPDIR:-/var/tmp/digi-f7a/tmp}"
mkdir -p "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
D="$ROOT/tests/playground/design"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
CACHE="${CACHE:-}" CORE="${CORE:-bundled}" V4="${V4:-}" NET="${NET:-}" WOO="${WOO:-}" QUICK="${QUICK:-}"
ELEMENTOR_PIN="${ELEMENTOR_PIN:-4.3.5}"
case "$CORE" in bundled|plugin) ;; *) echo "CORE debe ser bundled o plugin"; exit 1;; esac
for flag in CACHE V4 NET WOO QUICK; do case "${!flag}" in ''|0|1) ;; *) echo "$flag debe ser 0 o 1"; exit 1;; esac; done
[ "$CACHE" = 0 ] && CACHE=''; [ "$V4" = 0 ] && V4=''; [ "$NET" = 0 ] && NET=''; [ "$WOO" = 0 ] && WOO=''; [ "$QUICK" = 0 ] && QUICK=''

PARTS=()
[ "$CORE" = plugin ] && PARTS+=("plugin-$ELEMENTOR_PIN")
[ -n "$V4" ] && PARTS+=(v4)
[ -n "$NET" ] && PARTS+=(net)
[ -n "$WOO" ] && PARTS+=(woo)
[ -n "$CACHE" ] && PARTS+=(cache)
MODE_LABEL="$(IFS=+; echo "${PARTS[*]:-}")"
[ -z "$MODE_LABEL" ] && MODE_LABEL=normal
OUT="${OUT:-/var/tmp/digi-f7a/design-$MODE_LABEL}"
if [ -n "$NET" ]; then PORT="${PORT:-9482}"; SITE_HOST="playground.test"; else PORT="${PORT:-9481}"; SITE_HOST="127.0.0.1:$PORT"; fi

# Puertos: nunca los de otros arneses (6.4) ni los que usan a la vez otros agentes; sólo se libera uno ocupado por una
# corrida anterior de este mismo arnés en este mismo $OUT.
case "$PORT" in
  9431|9433|9435|9441|9445|9448|9449|9451|9453|9454|9455|9456|9457|946[0-9]|947[0-9]|949[0-9]) echo "El puerto $PORT es de otro arnés: usa 9481–9489"; exit 1;;
esac
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
for dir in "$TMPDIR" "$OUT"; do
  free_kb="$(df -Pk "$dir" 2>/dev/null | awk 'NR == 2 { print $4 }')"
  [ -z "$free_kb" ] || [ "$free_kb" -ge 2097152 ] || { echo "$dir tiene $((free_kb / 1024)) MB libres: hacen falta 2 GB"; exit 1; }
done
rm -f "$OUT"/{setup-error.txt,ready.txt,fatal.log,php-errors.log,server.log,summary.json,run.log,env.json}
rm -rf "$OUT/shots" && mkdir -p "$OUT/shots"
cp "$D"/{setup.php,lib.php,probe.php,cases.json,svg-valida.svg,svg-hostil.svg} "$OUT/"
cp "$ROOT/tests/sources/design/presets.json" "$ROOT/tests/sources/design/gradients.json" "$OUT/"
# WOO=1: WooCommerce fijado, extraído en $OUT/woocommerce y montado (setup.php lo activa).
rm -f "$OUT/woocommerce.json"
WOO_MOUNT=''
if [ -n "$WOO" ]; then
  . "$ROOT/tests/playground/woocommerce-pin.sh"
  woo_pin "$OUT/woocommerce" "$OUT/woocommerce.json" || exit 1
  WOO_MOUNT="$(woo_mount)"
fi

# Copia fija de lo que se prueba: un cambio a mitad de corrida no mezcla versiones, y DS13 la reemplaza por 4.3.0.163.
if [ "${LIVE:-0}" = 1 ]; then
  SRC="$ROOT"
else
  SRC="$OUT/src"
  rm -rf "$SRC" && mkdir -p "$SRC"
  cp -a "$ROOT/digitalisimo-elements" "$ROOT/digitalisimo-theme" "$ROOT/digitalisimo-seo" "$SRC/"
  git -C "$ROOT" status --porcelain -- digitalisimo-elements digitalisimo-theme > "$SRC/dirty.txt" 2>/dev/null || true
fi
ELEMENTS_SHA="$(cd "$SRC/digitalisimo-elements" && find . -type f ! -path './elementor/*' -print0 | LC_ALL=C sort -z | xargs -0 sha256sum | sha256sum | cut -c1-16)"
GIT_SHA="$(git -C "$ROOT" rev-parse --short HEAD 2>/dev/null || echo '')"

# Hello 3.5.1 (DS7): copia oficial anotada en reference/OFFICIAL-SOURCES.txt, sólo en este Playground efímero.
HELLO_ZIP="${HELLO_ZIP:-$ROOT/reference/hello-elementor.3.5.1.zip}"
if [ ! -f "$HELLO_ZIP" ]; then
  main_root="$(dirname "$(git -C "$ROOT" rev-parse --path-format=absolute --git-common-dir)")"
  HELLO_ZIP="$main_root/reference/hello-elementor.3.5.1.zip"
fi
HELLO_DIR=''
if [ -f "$HELLO_ZIP" ] && [ -z "$NET" ]; then
  sha256sum "$HELLO_ZIP" | grep -q '^c166fdcb091a38897f4f3339f83645e9747234fbc961560027d5563e3efd6447 ' || { echo "$HELLO_ZIP no es Hello Elementor 3.5.1 (sha256)"; exit 1; }
  rm -rf "$OUT/themes" && mkdir -p "$OUT/themes" && unzip -q "$HELLO_ZIP" -d "$OUT/themes"
  HELLO_DIR="$OUT/themes/hello-elementor"
fi

# DS13: el asset digitalisimo-elements-4.3.0.163.zip de la Release v2026.10.21.417, comprobado por su sha256.
OLD_SHA=ae6f92a7f7a2d0d823c9011a0dea5e54882d19eafb73656c913bfec1efa22c26
OLD_ELEMENTS=''
wants_ds13() { [ -n "${SCENARIOS:-}" ] && [[ ",${SCENARIOS}," == *",DS13,"* ]] && return 0; [ -z "${SCENARIOS:-}" ] && [ "$MODE_LABEL" = normal ] && return 0; return 1; }
if wants_ds13 && [ "${LIVE:-0}" != 1 ]; then
  OLD_ZIP="${OLD_ZIP:-/var/tmp/digi-f7a/rel163/zip/digitalisimo-elements-4.3.0.163.zip}"
  if [ ! -s "$OLD_ZIP" ]; then
    OLD_ZIP="/var/tmp/digi-f7a/cache/digitalisimo-elements-4.3.0.163.zip"
    mkdir -p "$(dirname "$OLD_ZIP")"
    [ -s "$OLD_ZIP" ] || { curl -fsSL -m 300 -o "$OLD_ZIP.part" https://github.com/danbutanda/digitalisimo-plugin/releases/download/v2026.10.21.417/digitalisimo-elements-4.3.0.163.zip && mv "$OLD_ZIP.part" "$OLD_ZIP"; }
  fi
  [ "$(sha256sum "$OLD_ZIP" | cut -d' ' -f1)" = "$OLD_SHA" ] || { echo "$OLD_ZIP no es el asset 4.3.0.163 de v2026.10.21.417 (sha256 distinto)"; exit 1; }
  unzip -tq "$OLD_ZIP" >/dev/null || { echo "$OLD_ZIP no supera unzip -t"; exit 1; }
  rm -rf "$OUT/old" && mkdir -p "$OUT/old" && unzip -q "$OLD_ZIP" -d "$OUT/old"
  OLD_ELEMENTS="$OUT/old/digitalisimo-elements"
  grep -q "Version: 4.3.0.163" "$OLD_ELEMENTS/pro-elements.php" || { echo "El ZIP de DS13 no contiene 4.3.0.163"; exit 1; }
fi

TOKEN="$(head -c 18 /dev/urandom | od -An -tx1 | tr -d ' \n')"
node -e '
const [out, label, token, core, pin, cache, v4, net, woo, host] = process.argv.slice(1);
require("fs").writeFileSync(out + "/mode.json", JSON.stringify({ label, token, core, elementor_pin: core === "plugin" ? pin : "", cache: cache === "1", v4: v4 === "1", net: net === "1", woo: woo === "1", host }, null, 1));
' "$OUT" "$MODE_LABEL" "$TOKEN" "$CORE" "$ELEMENTOR_PIN" "${CACHE:-0}" "${V4:-0}" "${NET:-0}" "${WOO:-0}" "$SITE_HOST"

STEPS=()
[ -n "$NET" ] && STEPS+=('{"step":"enableMultisite"}')
[ "$CORE" = plugin ] && STEPS+=("{\"step\":\"installPlugin\",\"pluginData\":{\"resource\":\"url\",\"url\":\"https://downloads.wordpress.org/plugin/elementor.$ELEMENTOR_PIN.zip\"},\"options\":{\"activate\":false}}")
[ -n "$WOO" ] && [ -n "$(woo_step false)" ] && STEPS+=("$(woo_step false)")
STEPS+=('{"step":"runPHP","code":"<?php putenv('"'"'DIGI_DS_PHASE=activate'"'"'); require '"'"'/wordpress/wp-content/probe/setup.php'"'"';"}')
STEPS+=('{"step":"runPHP","code":"<?php putenv('"'"'DIGI_DS_PHASE=content'"'"'); require '"'"'/wordpress/wp-content/probe/setup.php'"'"';"}')
printf '{"steps":[%s]}\n' "$(IFS=,; echo "${STEPS[*]}")" > "$OUT/blueprint.json"

MOUNTS=(--mount="$SRC/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements"
  --mount="$SRC/digitalisimo-theme:/wordpress/wp-content/themes/digitalisimo-theme"
  --mount="$OUT:/wordpress/wp-content/probe")
[ -z "$NET" ] && MOUNTS+=(--mount="$SRC/digitalisimo-seo:/wordpress/wp-content/plugins/digitalisimo-seo")
[ -n "$HELLO_DIR" ] && MOUNTS+=(--mount="$HELLO_DIR:/wordpress/wp-content/themes/hello-elementor")
[ -n "$WOO_MOUNT" ] && MOUNTS+=("$WOO_MOUNT")

echo "design · modo $MODE_LABEL · Elements $ELEMENTS_SHA · puerto $PORT · $OUT"
$CLI server --port="$PORT" --workers=1 --site-url="http://$SITE_HOST" --blueprint="$OUT/blueprint.json" "${MOUNTS[@]}" > "$OUT/server.log" 2>&1 &
SERVER=$!
stop_server() { kill $SERVER 2>/dev/null || true; for i in 1 2 3; do fuser -k -n tcp "$PORT" >/dev/null 2>&1 || break; sleep 1; done; }
trap 'stop_server; playground_cleanup' EXIT
trap 'exit 130' INT TERM
ping_site() { curl -s -m 60 -H "Host: $SITE_HOST" "http://127.0.0.1:$PORT/$([ -n "$NET" ] && echo 'uno/')?digi_design=ping&token=$TOKEN" 2>/dev/null; }
ready=0
for i in $(seq 1 300); do
  sleep 3
  if ! kill -0 $SERVER 2>/dev/null; then echo 'Playground terminó durante la preparación:'; tail -20 "$OUT/server.log"; exit 1; fi
  [ -f "$OUT/setup-error.txt" ] && break
  if [ -f "$OUT/ready.txt" ] && ping_site | grep -q '"ready":[1-9]'; then ready=1; break; fi
done
[ -f "$OUT/setup-error.txt" ] && { echo "Preparación incompleta:"; cat "$OUT/setup-error.txt"; exit 1; }
[ "$ready" = 1 ] || { echo 'El sitio no quedó listo'; tail -20 "$OUT/server.log"; exit 1; }
echo "Sitio listo en $(( $(date +%s) - STARTED )) s"

node -e '
const env = Object.fromEntries(process.argv.slice(2).map((kv) => [kv.slice(0, kv.indexOf("=")), kv.slice(kv.indexOf("=") + 1)]));
require("fs").writeFileSync(env.OUT + "/env.json", JSON.stringify(env, null, 1));
' x "OUT=$OUT" "ROOT=$ROOT" "BASE=http://$SITE_HOST" "PORT=$PORT" "HOST=$SITE_HOST" "TOKEN=$TOKEN" "MODE_LABEL=$MODE_LABEL" \
  "SRC_ELEMENTS=$SRC/digitalisimo-elements" "LIVE=${LIVE:-0}" "OLD_ELEMENTS=$OLD_ELEMENTS" "ELEMENTS_SHA=$ELEMENTS_SHA" "GIT_SHA=$GIT_SHA" \
  "HELLO=$([ -n "$HELLO_DIR" ] && echo 1 || echo 0)" "STARTED=$STARTED"

if [ "${KEEP:-0}" = 1 ]; then
  echo "KEEP=1: sitio en http://$SITE_HOST (puerto $PORT, token $TOKEN). Para correr escenarios a mano:"
  echo "  ENV_FILE=$OUT/env.json SCENARIOS=DS3 NODE_PATH=<puppeteer> node $D/run.cjs   · Ctrl+C para terminar"
  while kill -0 $SERVER 2>/dev/null; do sleep 5; done
  exit 0
fi

PUPPETEER_PATH="${PUPPETEER_PATH:-$(ls -d "$HOME"/.npm/_npx/*/node_modules 2>/dev/null | while read -r d; do [ -d "$d/puppeteer-core" ] && echo "$d" && break; done)}"
[ -n "$PUPPETEER_PATH" ] || { echo "No se encontró puppeteer-core: exportar PUPPETEER_PATH"; exit 1; }
status=0
ENV_FILE="$OUT/env.json" SCENARIOS="${SCENARIOS:-}" QUICK="$QUICK" NODE_PATH="$PUPPETEER_PATH" node "$D/run.cjs" || status=$?
[ -s "$OUT/fatal.log" ] && { echo "Errores fatales:"; cat "$OUT/fatal.log"; status=1; }
[ -s "$OUT/php-errors.log" ] && { echo "Avisos de PHP de DIGITALÍSIMO Elements (ver $OUT/php-errors.log):"; sort "$OUT/php-errors.log" | uniq -c | sort -rn | head -10; }
echo "Resumen: $OUT/summary.json · duración: $(( $(date +%s) - STARTED )) s"
exit $status
