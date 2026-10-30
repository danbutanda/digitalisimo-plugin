#!/usr/bin/env bash
# Capacidades de F7b en DIGITALÍSIMO Elements (escenarios B1–B12 de la especificación, §6 ampliado por V.14 y por los
# informes de los frentes) en WordPress Playground (modo servidor, un worker) y Chrome sin interfaz. Manual: no corre en
# CI. Monta una copia fija de digitalisimo-elements/ y digitalisimo-theme/ ($OUT/src), la sonda f7b/probe.php
# (mu-plugin) y los documentos de f7b/cases.json, y deja $OUT/summary.json con el SHA, el modo, cada escenario con sus
# comprobaciones (ok, falla o pendiente con su motivo) y su duración.
#
#   B1  acciones con destino: teclado, foco, `hover` con foco y Escape, popup y panel lateral con la puerta de PRO
#       activa, «Oculto al cargar» con `<noscript>`, `display:none` en flex, grid, columna y widget, y la segunda
#       visita con la caché de elementos llena
#   B2  interacciones PRO (v4 activado en el Playground efímero): estados pending → ack y clear, aviso y botón,
#       movimiento reducido emulado
#   B3  consultas guardadas = grupo de PRO con los mismos ajustes; contextos `current`/`root` y relaciones en una
#       plantilla «Entrada individual» y bucle en bucle; B3+B5 con `digi_ctx` por REST
#   B4  «Desvincular» en el editor real (con su «Deshacer») y borrado bloqueado («En uso (N)» en edit.php, en la
#       pantalla «Consultas» y en la biblioteca del editor)
#   B5  filtros nuevos sobre un Loop Grid = WP_Query directo; petición REST forjada; teclado y árbol de accesibilidad
#   B6  destino «Archivo» con `digi_f`          B7  convivencia con los filtros de F4-Woo (WOO=1)
#   B8  sobrescrituras del Template (cada `kind`, dos instancias, caché de la plantilla, editor)
#   B9  etiquetas dentro del texto (escape, listas, caché, «Insertar dato» en el editor real)
#   B10 formulario condicional (navegador, envío real y envío colado; reglas en el editor real)
#   B11 slider anidado (teclado, autoplay, movimiento reducido, tres anchos, árbol de accesibilidad, editor) y
#       presentación de pestañas y acordeón
#   B12 vuelta a 4.3.0.168 (ZIP de dist/, comprobado por su sha256) instalada sobre el resultado
#
# Uso: bash tests/playground/f7b.sh                   B1–B6 y B8–B12 (B7 sólo con WOO=1)
#      WOO=1 SCENARIOS=B7 bash tests/playground/f7b.sh
# Variables:
#   SCENARIOS=B1,B5    escenarios (por defecto todos los del modo)   QUICK=1  un solo ancho (1280 px): iteración local
#   OLD_ZIP            ZIP 4.3.0.168 de B12 (por defecto dist/digitalisimo-elements-4.3.0.168.zip del repositorio
#                      principal); se comprueba su sha256
#   WOO_ZIP            WooCommerce de WOO=1 (tests/playground/woocommerce-pin.sh: 11.2.1 de reference/, montado)
#   OUT, TMPDIR        resultados (por defecto /var/tmp/digi-f7b/pg-f7b-<modo>) y temporales (/var/tmp/digi-f7b/tmp)
#   PORT               9501 (sitio) o 9502 (WOO=1); el bloque 9500–9509 es de F7b y cualquier otro puerto se rechaza
#   LIVE=1             monta el árbol de trabajo (B12 queda fuera: reemplaza la copia montada)
#   KEEP=1             sólo prepara el sitio y lo deja arrancado ($OUT/env.json sirve para correr run.cjs a mano)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
STARTED="$(date +%s)"
export TMPDIR="${TMPDIR:-/var/tmp/digi-f7b/tmp}"
mkdir -p "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
F="$ROOT/tests/playground/f7b"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
WOO="${WOO:-}" QUICK="${QUICK:-}"
for flag in WOO QUICK; do case "${!flag}" in ''|0|1) ;; *) echo "$flag debe ser 0 o 1"; exit 1;; esac; done
[ "$WOO" = 0 ] && WOO=''; [ "$QUICK" = 0 ] && QUICK=''
MODE_LABEL=normal
[ -n "$WOO" ] && MODE_LABEL=woo
OUT="${OUT:-/var/tmp/digi-f7b/pg-f7b-$MODE_LABEL}"
if [ -n "$WOO" ]; then PORT="${PORT:-9502}"; else PORT="${PORT:-9501}"; fi
SITE_HOST="127.0.0.1:$PORT"

# Puertos: sólo los del bloque 9500–9509 (F7b), y sólo se libera uno ocupado por una corrida anterior de este arnés.
case "$PORT" in 950[0-9]) ;; *) echo "f7b.sh usa el bloque 9500–9509 (9501 sitio, 9502 WOO=1): $PORT es de otro arnés"; exit 1;; esac
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
rm -f "$OUT"/{setup-error.txt,ready.txt,fatal.log,php-errors.log,server.log,summary.json,run.log,env.json,woocommerce.json}
rm -rf "$OUT/shots" "$OUT/pages" "$OUT/ops" && mkdir -p "$OUT/shots" "$OUT/pages"
cp "$F"/{setup.php,lib.php,probe.php,cases.json} "$OUT/"
cp -r "$F/ops" "$OUT/ops"
# Vídeo alojado de B8 (el mismo MP4 mínimo que mide front-budget.sh), servido desde la carpeta de la sonda.
cp "$ROOT/tests/playground/front-budget/digi-medicion.mp4" "$OUT/digi-medicion.mp4"

# WOO=1 (B7): WooCommerce fijado, extraído en $OUT/woocommerce y montado (setup.php lo activa).
WOO_MOUNT=''
if [ -n "$WOO" ]; then
  . "$ROOT/tests/playground/woocommerce-pin.sh"
  WOO_REQUIRE=1 woo_pin "$OUT/woocommerce" "$OUT/woocommerce.json" || exit 1
  WOO_MOUNT="$(woo_mount)"
fi

# Copia fija de lo que se prueba: un cambio a mitad de corrida no mezcla versiones, y B12 la reemplaza por 4.3.0.168.
if [ "${LIVE:-0}" = 1 ]; then
  SRC="$ROOT"
else
  SRC="$OUT/src"
  rm -rf "$SRC" && mkdir -p "$SRC"
  cp -a "$ROOT/digitalisimo-elements" "$ROOT/digitalisimo-theme" "$SRC/"
  git -C "$ROOT" status --porcelain -- digitalisimo-elements digitalisimo-theme > "$SRC/dirty.txt" 2>/dev/null || true
fi
ELEMENTS_SHA="$(cd "$SRC/digitalisimo-elements" && find . -type f ! -path './elementor/*' -print0 | LC_ALL=C sort -z | xargs -0 sha256sum | sha256sum | cut -c1-16)"
ELEMENTS_VERSION="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([0-9.]*\).*/\1/p' "$SRC/digitalisimo-elements/pro-elements.php" | head -1)"
GIT_SHA="$(git -C "$ROOT" rev-parse --short HEAD 2>/dev/null || echo '')"

# B12: el ZIP publicado de 4.3.0.168 (dist/ del repositorio principal), comprobado por su sha256.
OLD_SHA=267c79704dae583849140e26657df9076b27fc5558ef65eebe5a2e3abfdbed89
OLD_ELEMENTS=''
wants_b12() { [ -n "${SCENARIOS:-}" ] && [[ ",${SCENARIOS}," == *",B12,"* ]] && return 0; [ -z "${SCENARIOS:-}" ] && [ -z "$WOO" ] && return 0; return 1; }
if wants_b12 && [ "${LIVE:-0}" != 1 ]; then
  main_root="$(dirname "$(git -C "$ROOT" rev-parse --path-format=absolute --git-common-dir)")"
  OLD_ZIP="${OLD_ZIP:-$main_root/dist/digitalisimo-elements-4.3.0.168.zip}"
  [ -s "$OLD_ZIP" ] || { echo "No está el ZIP 4.3.0.168 de B12: $OLD_ZIP (OLD_ZIP)"; exit 1; }
  [ "$(sha256sum "$OLD_ZIP" | cut -d' ' -f1)" = "$OLD_SHA" ] || { echo "$OLD_ZIP no es el ZIP publicado de 4.3.0.168 (sha256 distinto)"; exit 1; }
  unzip -tq "$OLD_ZIP" >/dev/null || { echo "$OLD_ZIP no supera unzip -t"; exit 1; }
  rm -rf "$OUT/old" && mkdir -p "$OUT/old" && unzip -q "$OLD_ZIP" -d "$OUT/old"
  OLD_ELEMENTS="$OUT/old/digitalisimo-elements"
  grep -q "Version: 4.3.0.168" "$OLD_ELEMENTS/pro-elements.php" || { echo "El ZIP de B12 no contiene 4.3.0.168"; exit 1; }
fi

TOKEN="$(head -c 18 /dev/urandom | od -An -tx1 | tr -d ' \n')"
node -e '
const [out, label, token, woo, host] = process.argv.slice(1);
require("fs").writeFileSync(out + "/mode.json", JSON.stringify({ label, token, woo: woo === "1", host }, null, 1));
' "$OUT" "$MODE_LABEL" "$TOKEN" "${WOO:-0}" "$SITE_HOST"

STEPS=()
STEPS+=('{"step":"runPHP","code":"<?php putenv('"'"'DIGI_F7B_PHASE=activate'"'"'); require '"'"'/wordpress/wp-content/probe/setup.php'"'"';"}')
STEPS+=('{"step":"runPHP","code":"<?php putenv('"'"'DIGI_F7B_PHASE=content'"'"'); require '"'"'/wordpress/wp-content/probe/setup.php'"'"';"}')
printf '{"steps":[%s]}\n' "$(IFS=,; echo "${STEPS[*]}")" > "$OUT/blueprint.json"

MOUNTS=(--mount="$SRC/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements"
  --mount="$SRC/digitalisimo-theme:/wordpress/wp-content/themes/digitalisimo-theme"
  --mount="$OUT:/wordpress/wp-content/probe")
[ -n "$WOO_MOUNT" ] && MOUNTS+=("$WOO_MOUNT")

echo "f7b · modo $MODE_LABEL · Elements $ELEMENTS_VERSION ($ELEMENTS_SHA) · puerto $PORT · $OUT"
$CLI server --port="$PORT" --workers=1 --site-url="http://$SITE_HOST" --blueprint="$OUT/blueprint.json" "${MOUNTS[@]}" > "$OUT/server.log" 2>&1 &
SERVER=$!
stop_server() { kill $SERVER 2>/dev/null || true; for i in 1 2 3; do fuser -k -n tcp "$PORT" >/dev/null 2>&1 || break; sleep 1; done; }
trap 'stop_server; playground_cleanup' EXIT
trap 'exit 130' INT TERM
ping_site() { curl -s -m 60 -H "Host: $SITE_HOST" "http://127.0.0.1:$PORT/?digi_f7b=ping&token=$TOKEN" 2>/dev/null; }
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
  "SRC_ELEMENTS=$SRC/digitalisimo-elements" "LIVE=${LIVE:-0}" "OLD_ELEMENTS=$OLD_ELEMENTS" "ELEMENTS_SHA=$ELEMENTS_SHA" \
  "ELEMENTS_VERSION=$ELEMENTS_VERSION" "GIT_SHA=$GIT_SHA" "WOO=${WOO:-0}" "STARTED=$STARTED"

if [ "${KEEP:-0}" = 1 ]; then
  echo "KEEP=1: sitio en http://$SITE_HOST (puerto $PORT, token $TOKEN). Para correr escenarios a mano:"
  echo "  ENV_FILE=$OUT/env.json SCENARIOS=B1 NODE_PATH=<puppeteer> node $F/run.cjs   · Ctrl+C para terminar"
  while kill -0 $SERVER 2>/dev/null; do sleep 5; done
  exit 0
fi

PUPPETEER_PATH="${PUPPETEER_PATH:-$(ls -d "$HOME"/.npm/_npx/*/node_modules 2>/dev/null | while read -r d; do [ -d "$d/puppeteer-core" ] && echo "$d" && break; done)}"
[ -n "$PUPPETEER_PATH" ] || { echo "No se encontró puppeteer-core: exportar PUPPETEER_PATH"; exit 1; }
status=0
ENV_FILE="$OUT/env.json" SCENARIOS="${SCENARIOS:-}" QUICK="$QUICK" NODE_PATH="$PUPPETEER_PATH" node "$F/run.cjs" || status=$?
[ -s "$OUT/fatal.log" ] && { echo "Errores fatales:"; cat "$OUT/fatal.log"; status=1; }
[ -s "$OUT/php-errors.log" ] && { echo "Avisos de PHP de DIGITALÍSIMO Elements (ver $OUT/php-errors.log):"; sort "$OUT/php-errors.log" | uniq -c | sort -rn | head -10; }
echo "Resumen: $OUT/summary.json · duración: $(( $(date +%s) - STARTED )) s"
exit $status
