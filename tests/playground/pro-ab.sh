#!/usr/bin/env bash
# A/B de los widgets de PRO frente a sus widgets propios (programa «sin widgets de PRO», C0; aceptación de cada widget
# en docs/PRO_WIDGETS_OBJECTIVE.md). Arranca Playground en modo servidor con el núcleo incluido (sin el plugin
# Elementor), y por cada caso de pro-ab/cases.json crea dos páginas con la plantilla «Lienzo»: A con el widget de PRO y
# sus ajustes guardados, y B con los mismos datos convertidos por `Converter::tree( $data, $log, true )` (mapas en
# borrador incluidos), con el CSS de cada página ya generado y el registro de la conversión (códigos del informe).
# Después mide en Chrome las dos páginas a 375, 768 y 1280 px:
#
#   - firma del widget: palabras visibles, enlaces con su destino, imágenes, nombre accesible y rol de enlaces y
#     botones, y estilos calculados de los nodos de texto y de la caja del widget (color, tamaño, peso, estilo,
#     alineación, fondo, bordes, radio, relleno); toda diferencia sin motivo declarado (en el caso o, con `code`, en
#     `drop`/`approx` del mapa) falla;
#   - captura de cada widget y diferencia de píxeles entre A y B (en un canvas de Chrome, sin dependencias nuevas),
#     con umbral configurable, informada por caso y ancho, con la imagen de la diferencia;
#   - hover y foco con teclado del primer enlace o botón del widget (estilos y píxeles);
#   - B no carga `pro-elements-handlers`, `elementor-pro-frontend` ni la hoja del widget de PRO
#     (`widget-<widget>.min.css`, o la lista `forbid` del caso), y ninguna página tiene errores de JavaScript, 404 del
#     mismo origen ni peticiones a terceros nuevas en B;
#   - una clave que el mapa no declara (`pro.unmapped`) falla.
#
# El sitio es uno recién instalado: WP-Cron no se dispara desde las visitas (como en front-budget.sh) y setup.php aplica
# los ajustes de «Rendimiento» (`Transition::run_now()`), así la puerta de PRO actúa; sin ella la corrida falla. Los
# enlaces se comparan por su destino canónico (la consulta decodificada: `+` y `%20` son el mismo espacio), la página
# propia se normaliza (`pro-ab-<caso>-X`) y el navegador no sale del Playground (lo de otros orígenes se corta y se
# anota). Una hoja o un script del sitio servido con redirección o como HTML repite la medición de esa página.
#
# Uso: bash tests/playground/pro-ab.sh                 todos los casos
#      CASES=bq-defaults,bq-boxed bash ...             sólo esos casos
#      KEEP=1 bash ...                                 prepara el sitio y lo deja arrancado (depuración; Ctrl+C)
#
# Variables:
#   OUT              resultados (por defecto /var/tmp/digi-pro-ab): pro-ab.json, shots/ (A, B y la diferencia de cada
#                    caso, ancho y estado), conversion.json (registro de setup.php) y server.log
#   PORT             puerto del servidor (por defecto 9701; el arnés usa 9701–9720)
#   TMPDIR           temporales de Playground (por defecto /var/tmp: /tmp es tmpfs)
#   CASES=a,b        casos a medir (por defecto, todos los que no tienen `pending`)
#   PIXEL_MAX        proporción máxima de píxeles distintos entre A y B (por defecto la de cases.json, 0.02); un caso
#                    puede subirla con `pixels.max` y su `reason`
#   PIXEL_TOLERANCE  diferencia por canal (0–255) a partir de la cual un píxel cuenta como distinto (por defecto 48)
#   ELEMENTS_DIR     carpeta del plugin (por defecto digitalisimo-elements/ del repositorio)
#   MAP              mapa de prueba (archivo PHP que devuelve un mapa): setup.php lo fija con `Registry::set()` antes de
#                    convertir, sin tocar maps/ (sólo para depurar la mecánica del arnés)
#   LIVE=1           monta las carpetas tal cual; por defecto se mide una copia fija tomada al empezar ($OUT/src)
# Resultado: $OUT/pro-ab.json; sale con 1 si hay alguna falla.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
STARTED="$(date +%s)"
export TMPDIR="${TMPDIR:-/var/tmp}"
. "$ROOT/tests/playground/playground-cleanup.sh"
OUT="${OUT:-/var/tmp/digi-pro-ab}"
PORT="${PORT:-9701}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
ELEMENTS_DIR="${ELEMENTS_DIR:-$ROOT/digitalisimo-elements}"
PUPPETEER_PATH="${PUPPETEER_PATH:-$(ls -d "$HOME"/.npm/_npx/*/node_modules 2>/dev/null | while read -r d; do [ -d "$d/puppeteer-core" ] && echo "$d" && break; done)}"
[ -n "$PUPPETEER_PATH" ] || { echo "No se encontró puppeteer-core: exportar PUPPETEER_PATH"; exit 1; }
[ -f "$ELEMENTS_DIR/pro-elements.php" ] || { echo "ELEMENTS_DIR no contiene pro-elements.php: $ELEMENTS_DIR"; exit 1; }
case "$OUT" in /tmp/*) echo "OUT no puede estar en /tmp (tmpfs): usar /var/tmp"; exit 1;; esac
if [ -n "${MAP:-}" ] && [ ! -f "$MAP" ]; then echo "MAP no existe: $MAP"; exit 1; fi

# Puertos: nunca se mata un proceso que no sea un Playground de este arnés (el que sirve este mismo OUT).
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
rm -rf "$OUT/shots" "$OUT/probe" "$OUT/html"
rm -f "$OUT/pro-ab.json" "$OUT/server.log"
PROBE="$OUT/probe"
mkdir -p "$PROBE"
cp "$ROOT/tests/playground/pro-ab/"{cases.json,activate.php,setup.php} "$PROBE/"
[ -n "${MAP:-}" ] && cp "$MAP" "$PROBE/map-override.php"
printf '%s\n' "${CASES:-}" > "$PROBE/only.txt"

# Copia fija de lo que se mide: un cambio a mitad de corrida (otro agente editando) no mezcla versiones.
if [ "${LIVE:-0}" = 1 ]; then
  SRC_ELEMENTS="$ELEMENTS_DIR"
else
  rm -rf "$OUT/src" && mkdir -p "$OUT/src"
  cp -a "$ELEMENTS_DIR" "$OUT/src/digitalisimo-elements"
  SRC_ELEMENTS="$OUT/src/digitalisimo-elements"
fi
ELEMENTS_VERSION="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([0-9.]*\).*/\1/p' "$SRC_ELEMENTS/pro-elements.php" | head -1)"
GIT_SHA="$(git -C "$ROOT" rev-parse --short HEAD 2>/dev/null || echo '')"

cat > "$PROBE/blueprint.json" <<'JSON'
{"steps":[
 {"step":"runPHP","code":"<?php require '/wordpress/wp-content/probe/activate.php';"},
 {"step":"runPHP","code":"<?php require '/wordpress/wp-content/probe/setup.php';"}
]}
JSON

echo "pro-ab · Elements $ELEMENTS_VERSION · puerto $PORT · $OUT"
$CLI server --port="$PORT" --workers=1 --site-url="http://127.0.0.1:$PORT" --blueprint="$PROBE/blueprint.json" \
  --mount="$SRC_ELEMENTS:/wordpress/wp-content/plugins/digitalisimo-elements" --mount="$PROBE:/wordpress/wp-content/probe" > "$OUT/server.log" 2>&1 &
SERVER=$!
stop_server() { kill $SERVER 2>/dev/null || true; for i in 1 2 3; do fuser -k -n tcp "$PORT" >/dev/null 2>&1 || break; sleep 1; done; }
trap 'stop_server; playground_cleanup' EXIT
trap 'exit 130' INT TERM
ping_site() { curl -s -m 30 -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/pro-ab-ready/" 2>/dev/null; }
for i in $(seq 1 200); do
  sleep 3
  if ! kill -0 $SERVER 2>/dev/null; then echo 'Playground terminó antes de tiempo:'; tail -20 "$OUT/server.log"; exit 1; fi
  [ -f "$PROBE/setup-error.txt" ] && break
  [ -f "$PROBE/ready.json" ] && ping_site | grep -q '^200$' && break
done
[ -f "$PROBE/setup-error.txt" ] && { echo "Preparación incompleta:"; cat "$PROBE/setup-error.txt"; exit 1; }
ping_site | grep -q '^200$' || { echo 'El servidor no sirvió las páginas de prueba'; tail -20 "$OUT/server.log"; exit 1; }
cp "$PROBE/conversion.json" "$OUT/conversion.json"
if [ "${KEEP:-0}" = 1 ]; then
  echo "KEEP=1: sitio listo en http://127.0.0.1:$PORT (páginas /pro-ab-<caso>-a/ y -b/); Ctrl+C para terminar"
  while kill -0 $SERVER 2>/dev/null; do sleep 5; done
  exit 0
fi
status=0
OUT="$OUT" PROBE="$PROBE" BASE="http://127.0.0.1:$PORT" PIXEL_MAX="${PIXEL_MAX:-}" PIXEL_TOLERANCE="${PIXEL_TOLERANCE:-}" \
  ELEMENTS_VERSION="$ELEMENTS_VERSION" GIT_SHA="$GIT_SHA" STARTED="$STARTED" MAP="${MAP:-}" \
  NODE_PATH="$PUPPETEER_PATH" node "$ROOT/tests/playground/pro-ab/run.cjs" || status=$?
[ -s "$PROBE/fatal.log" ] && { echo "Errores fatales de PHP:"; cat "$PROBE/fatal.log"; status=1; }
echo "Duración: $(( $(date +%s) - STARTED )) s"
exit $status
