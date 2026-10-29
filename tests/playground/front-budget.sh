#!/usr/bin/env bash
# Presupuesto del front de DIGITALÍSIMO Elements: arranca Playground en modo servidor con el núcleo incluido (sin el
# plugin Elementor ni Element Pack), crea una página por caso de front-budget/cases.json (una por widget propio, la
# página vacía de referencia, las de la puerta de PRO, las de librerías e iconos) y mide en Chrome cada página: bytes por
# tipo y por origen, peticiones, jQuery, paquetes de PRO, Swiper, Lottie, Prism, fuentes de iconos, `font-display`,
# handles de Elements, CLS, tareas largas, nodos, imágenes, iframes, errores, las razones de la puerta (comentario de la
# sonda) y las comprobaciones funcionales de cada caso (popup visible, cuenta atrás, formulario, menú, bucles,
# movimiento, sticky, resaltado). El coste de cada widget es la diferencia con la página vacía.
#
# Uso: bash tests/playground/front-budget.sh               modo normal: compara con la línea base y exige el `expect`
#      BASELINE=write ...                                 guarda la medición como nueva línea base (con LIGHTHOUSE=1, en la misma pasada)
#      LIGHTHOUSE=1 ...                                   Lighthouse 13.5.0 móvil en empty, all-own y pro-heavy
#
# Variables (§5.1 de la especificación F-front):
#   ELEMENTS_DIR     carpeta del plugin a medir (por defecto digitalisimo-elements/ del repositorio; p. ej. el ZIP 4.3.0.162 extraído)
#   BASELINE_FILE    línea base (por defecto tests/playground/front-budget/baseline.json); sólo el modo normal la usa
#   EXPECT=0         sólo mide: no exige `expect` ni compara (medición «antes» de una versión sin la puerta)
#   CASES=a,b        casos a medir (por defecto, los del modo: los que lo nombran en `modes`; sin `modes`, sólo normal)
#   LIGHTHOUSE_PATH  carpeta node_modules con lighthouse 13.5.0 (por defecto la de puppeteer-core); otra versión falla
#   Modos: CACHE=1 · CORE=plugin · SEO=1|guard · THEME=1 · MIGRATION=1 · LAZY=1 · V4=1 · WOO=1 ·
#          DEFAULTS=old|old-applied|old-dirty|old-inactive|off (se combinan: DEFAULTS=old-applied CACHE=1)
#   WOO=1            WooCommerce activo, con productos, categorías y un variable (F4-Woo, V.16.3): sólo los casos con
#                    «woo» en `modes`; la página de referencia es `woo-empty` y COMPARE_STRICT, por defecto, los cuatro
#                    casos sin `since` (woo-empty, all-own, woo-loop-filter-pro, woo-pro-existing)
#   WOO_ZIP          WooCommerce fijado (tests/playground/woocommerce-pin.sh): por defecto reference/woocommerce.11.2.1.zip,
#                    comprobado con su sha256 y montado; sin copia local, la última de wordpress.org (anotado en el resumen)
#   ELEMENTOR_PIN    versión del plugin Elementor de CORE=plugin (por defecto 4.3.5, la del núcleo incluido)
#   PORT, OUT, TMPDIR   puerto (por defecto 9441: no correr a la vez que brand-audit.sh), resultados y temporales
#   COMPARE_FILE     front-budget.json de otra corrida del mismo modo (p. ej. 4.3.0.163 con ELEMENTS_DIR): cada caso sin
#                    `since` debe dar el mismo HTML y CSS normalizados (sin origen, `ver=`, nonces, tokens ni la hora del horario); en COMPARE_STRICT (por
#                    defecto empty,all-own), además los mismos bytes, peticiones, handles y $wpdb->num_queries
#   LIVE=1           monta las carpetas tal cual; por defecto se mide una copia fija tomada al empezar ($OUT/src)
#   KEEP=1           sólo prepara el sitio y lo deja arrancado (depuración; Ctrl+C para salir)
# Resultado: $OUT/front-budget.json (normal) o $OUT/front-budget-<modo>.json, con SHA, modo y duración.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
STARTED="$(date +%s)"
export TMPDIR="${TMPDIR:-/tmp}"
. "$ROOT/tests/playground/playground-cleanup.sh"
OUT="${OUT:-/tmp/digi-front-budget}"
PORT="${PORT:-9441}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
ELEMENTS_DIR="${ELEMENTS_DIR:-$ROOT/digitalisimo-elements}"
BASELINE_FILE="${BASELINE_FILE:-$ROOT/tests/playground/front-budget/baseline.json}"
ELEMENTOR_PIN="${ELEMENTOR_PIN:-4.3.5}"
CACHE="${CACHE:-}" CORE="${CORE:-bundled}" SEO="${SEO:-}" THEME="${THEME:-}" MIGRATION="${MIGRATION:-}" LAZY="${LAZY:-}" V4="${V4:-}" DEFAULTS="${DEFAULTS:-}" WOO="${WOO:-}"
PUPPETEER_PATH="${PUPPETEER_PATH:-$(ls -d "$HOME"/.npm/_npx/*/node_modules 2>/dev/null | while read -r d; do [ -d "$d/puppeteer-core" ] && echo "$d" && break; done)}"
[ -n "$PUPPETEER_PATH" ] || { echo "No se encontró puppeteer-core: exportar PUPPETEER_PATH"; exit 1; }
LIGHTHOUSE_PATH="${LIGHTHOUSE_PATH:-$PUPPETEER_PATH}"
[ -f "$ELEMENTS_DIR/pro-elements.php" ] || { echo "ELEMENTS_DIR no contiene pro-elements.php: $ELEMENTS_DIR"; exit 1; }
case "$CORE" in bundled|plugin) ;; *) echo "CORE debe ser bundled o plugin"; exit 1;; esac
case "$SEO" in ''|0|1|guard) ;; *) echo "SEO debe ser 1 o guard"; exit 1;; esac
case "$DEFAULTS" in ''|old|old-applied|old-dirty|old-inactive|off) ;; *) echo "DEFAULTS debe ser old, old-applied, old-dirty, old-inactive u off"; exit 1;; esac
case "$WOO" in ''|0|1) ;; *) echo "WOO debe ser 0 o 1"; exit 1;; esac
[ "$WOO" = 0 ] && WOO=''
[ "$SEO" = 0 ] && SEO=''
# SEO=guard mide un sitio antiguo (sin font-display guardado): la transición vacía las fuentes locales.
[ "$SEO" = guard ] && [ -z "$DEFAULTS" ] && DEFAULTS=old

# Componentes del modo, en orden fijo; la etiqueta los une con «+» (normal sin ninguno).
PARTS=()
[ "$CORE" = plugin ] && PARTS+=(plugin)
[ "$SEO" = 1 ] && PARTS+=(seo)
[ "$SEO" = guard ] && PARTS+=(guard)
[ "${THEME:-0}" = 1 ] && PARTS+=(theme)
[ "${MIGRATION:-0}" = 1 ] && PARTS+=(migration)
[ "${LAZY:-0}" = 1 ] && PARTS+=(lazy)
[ "${V4:-0}" = 1 ] && PARTS+=(v4)
[ -n "$WOO" ] && PARTS+=(woo)
[ -n "$DEFAULTS" ] && PARTS+=("$DEFAULTS")
[ "${CACHE:-0}" = 1 ] && PARTS+=(cache)
MODE_LABEL="$(IFS=+; echo "${PARTS[*]:-normal}")"
[ "${#PARTS[@]}" -eq 0 ] && MODE_LABEL=normal
# old-dirty necesita una red (la red pide los iconos y el sitio los hereda): Multisite no admite un puerto en la URL,
# así que el sitio se sirve como http://digi-fb.test y Chrome y Node resuelven ese host a 127.0.0.1:$PORT.
NETWORK=0
[ "$DEFAULTS" = old-dirty ] && NETWORK=1
SITE_HOST="127.0.0.1:$PORT"
[ "$NETWORK" = 1 ] && SITE_HOST="digi-fb.test"

# Puertos: nunca se mata un proceso que no sea un Playground de este arnés.
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

mkdir -p "$OUT" "$TMPDIR"
rm -f "$OUT"/*.png "$OUT/setup-error.txt" "$OUT/setup-notes.txt" "$OUT/fatal.log" "$OUT/ready.txt" "$OUT/server.log" "$OUT/queries.jsonl" "$OUT/markers.json"
rm -rf "$OUT/pages"
cp "$ROOT/tests/playground/front-budget/"{cases.json,setup.php,activate.php,lib.php,probe.php} "$OUT/"

# Copia fija de lo que se mide (como kit-roundtrip.sh): un cambio a mitad de corrida no mezcla versiones.
if [ "${LIVE:-0}" = 1 ]; then
  SRC_ELEMENTS="$ELEMENTS_DIR"; SRC="$ROOT"
else
  SRC="$OUT/src"
  rm -rf "$SRC" && mkdir -p "$SRC"
  cp -a "$ELEMENTS_DIR" "$SRC/digitalisimo-elements"
  for dir in digitalisimo-seo digitalisimo-migration digitalisimo-theme; do cp -a "$ROOT/$dir" "$SRC/$dir"; done
  SRC_ELEMENTS="$SRC/digitalisimo-elements"
fi
cp -a "$ROOT/tests/playground/front-budget/fake-addon" "$OUT/fake-addon"
# WOO=1: WooCommerce fijado (11.2.1 de reference/, con su sha256), extraído en $OUT/woocommerce y montado.
rm -f "$OUT/woocommerce.json"
if [ -n "$WOO" ]; then
  . "$ROOT/tests/playground/woocommerce-pin.sh"
  woo_pin "$OUT/woocommerce" "$OUT/woocommerce.json" || exit 1
fi
# Huella de lo medido: SHA del árbol de ELEMENTS_DIR (como lo guarda git) y versión del plugin.
ELEMENTS_SHA="$(cd "$SRC_ELEMENTS" && find . -type f ! -path './elementor/*' -print0 | LC_ALL=C sort -z | xargs -0 sha256sum | sha256sum | cut -c1-16)"
ELEMENTS_VERSION="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([0-9.]*\).*/\1/p' "$SRC_ELEMENTS/pro-elements.php" | head -1)"
GIT_SHA="$(git -C "$ROOT" rev-parse --short HEAD 2>/dev/null || echo '')"
TOKEN="$(head -c 18 /dev/urandom | od -An -tx1 | tr -d ' \n')"

STEPS=()
[ "$NETWORK" = 1 ] && STEPS+=('{"step":"enableMultisite"}')
if [ "$CORE" = plugin ]; then
  STEPS+=("{\"step\":\"installPlugin\",\"pluginData\":{\"resource\":\"url\",\"url\":\"https://downloads.wordpress.org/plugin/elementor.$ELEMENTOR_PIN.zip\"},\"options\":{\"activate\":false}}")
fi
# WooCommerce montado no necesita paso (activate.php lo activa); sin copia local, el installPlugin de siempre.
[ -n "$WOO" ] && [ -n "$(woo_step false)" ] && STEPS+=("$(woo_step false)")
STEPS+=('{"step":"runPHP","code":"<?php require '"'"'/wordpress/wp-content/probe/activate.php'"'"';"}')
STEPS+=('{"step":"runPHP","code":"<?php require '"'"'/wordpress/wp-content/probe/setup.php'"'"';"}')
printf '{"steps":[%s]}\n' "$(IFS=,; echo "${STEPS[*]}")" > "$OUT/blueprint.json"

node -e '
const [out, label, parts, token, core, seo, theme, migration, lazy, v4, defaults, cache, network, host, pin, woo] = process.argv.slice(1);
require("fs").writeFileSync(out + "/mode.json", JSON.stringify({ label, parts: parts ? parts.split(",") : [], token, core, seo, theme: theme === "1", migration: migration === "1", lazy: lazy === "1", v4: v4 === "1", defaults, cache: cache === "1", network: network === "1", host, elementor_pin: pin, woo: woo === "1" }, null, 1));
' "$OUT" "$MODE_LABEL" "$(IFS=,; echo "${PARTS[*]:-}")" "$TOKEN" "$CORE" "$SEO" "${THEME:-0}" "${MIGRATION:-0}" "${LAZY:-0}" "${V4:-0}" "$DEFAULTS" "${CACHE:-0}" "$NETWORK" "$SITE_HOST" "$ELEMENTOR_PIN" "$([ -n "$WOO" ] && echo 1 || echo 0)"

MOUNTS=(--mount="$SRC_ELEMENTS:/wordpress/wp-content/plugins/digitalisimo-elements" --mount="$OUT/fake-addon:/wordpress/wp-content/plugins/digi-fb-fake-addon" --mount="$OUT:/wordpress/wp-content/probe")
[ -n "$SEO" ] && MOUNTS+=(--mount="$SRC/digitalisimo-seo:/wordpress/wp-content/plugins/digitalisimo-seo")
[ "${MIGRATION:-0}" = 1 ] && MOUNTS+=(--mount="$SRC/digitalisimo-migration:/wordpress/wp-content/plugins/digitalisimo-migration")
[ "${THEME:-0}" = 1 ] && MOUNTS+=(--mount="$SRC/digitalisimo-theme:/wordpress/wp-content/themes/digitalisimo-theme")
[ -n "$WOO" ] && [ -n "$(woo_mount)" ] && MOUNTS+=("$(woo_mount)")

echo "front-budget · modo $MODE_LABEL · Elements $ELEMENTS_VERSION ($ELEMENTS_SHA) · puerto $PORT · $OUT"
$CLI server --port="$PORT" --workers=1 --site-url="http://$SITE_HOST" --blueprint="$OUT/blueprint.json" "${MOUNTS[@]}" > "$OUT/server.log" 2>&1 &
SERVER=$!
stop_server() { kill $SERVER 2>/dev/null || true; for i in 1 2 3; do fuser -k -n tcp "$PORT" >/dev/null 2>&1 || break; sleep 1; done; }
trap 'stop_server; playground_cleanup' EXIT
trap 'exit 130' INT TERM
ping_site() { curl -s -m 30 -o /dev/null -w '%{http_code}' -H "Host: $SITE_HOST" "http://127.0.0.1:$PORT/digi-fb-empty/" 2>/dev/null; }
for i in $(seq 1 300); do
  sleep 3
  if ! kill -0 $SERVER 2>/dev/null; then tail -20 "$OUT/server.log"; exit 1; fi
  [ -f "$OUT/setup-error.txt" ] && break
  [ -f "$OUT/ready.txt" ] && ping_site | grep -q '^200$' && break
done
[ -f "$OUT/setup-error.txt" ] && { echo "Preparación incompleta:"; cat "$OUT/setup-error.txt"; exit 1; }
ping_site | grep -q '^200$' || { echo 'El servidor no sirvió las páginas de prueba'; tail -20 "$OUT/server.log"; exit 1; }
if [ "${KEEP:-0}" = 1 ]; then
  echo "KEEP=1: sitio listo en http://$SITE_HOST (puerto $PORT, token $TOKEN); Ctrl+C para terminar"
  while kill -0 $SERVER 2>/dev/null; do sleep 5; done
  exit 0
fi
status=0
OUT="$OUT" BASE="http://$SITE_HOST" PORT="$PORT" HOST_MAP="$([ "$NETWORK" = 1 ] && echo "$SITE_HOST" || echo '')" TOKEN="$TOKEN" MODE_LABEL="$MODE_LABEL" MODE_PARTS="$(IFS=,; echo "${PARTS[*]:-}")" \
  EXPECT="${EXPECT:-1}" CASES="${CASES:-}" BASELINE="${BASELINE:-}" BASELINE_FILE="$BASELINE_FILE" LIGHTHOUSE="${LIGHTHOUSE:-}" LIGHTHOUSE_PATH="$LIGHTHOUSE_PATH" \
  NORMAL_FILE="${NORMAL_FILE:-}" COMPARE_FILE="${COMPARE_FILE:-}" COMPARE_STRICT="${COMPARE_STRICT:-}" ELEMENTS_SHA="$ELEMENTS_SHA" ELEMENTS_VERSION="$ELEMENTS_VERSION" GIT_SHA="$GIT_SHA" ELEMENTS_DIR="$ELEMENTS_DIR" STARTED="$STARTED" \
  NODE_PATH="$PUPPETEER_PATH" node "$ROOT/tests/playground/front-budget/run.cjs" || status=$?
[ -s "$OUT/fatal.log" ] && { echo "Errores fatales:"; cat "$OUT/fatal.log"; status=1; }
echo "Duración: $(( $(date +%s) - STARTED )) s"
exit $status
