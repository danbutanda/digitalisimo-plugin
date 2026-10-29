#!/usr/bin/env bash
# Kits de Elementor con DIGITALÍSIMO Elements (etapa K, §13.3): ida y vuelta, kits de terceros, «sólo agregar»,
# reimportación, red, reversión, permisos y entradas hostiles, en WordPress Playground (modo servidor, un worker por
# sitio) y Chrome sin interfaz. Manual (D10): no corre en CI. Deja $OUT/summary.json con el SHA, los escenarios, sus
# duraciones, los conteos, la petición REST más lenta y el resultado de A1 a A14; antes de publicar se copia a
# docs/fusion/k-kits-evidence.json.
#
# Sitios: A (origen, http://a.test, PORT_A), B (destino, http://b.test, PORT_B; uno nuevo por escenario) y la red de
# R6 (http://playground.test con /sub/ y un tercer sitio, PORT_NET). Chrome resuelve esos hosts a sus puertos y todo
# lo demás a NOTFOUND. En PHP, net-sim.php responde detrás del proxy de K: nada sale a internet.
#
# Uso: bash tests/playground/kit-roundtrip.sh
#   SCENARIOS=R1,R4      escenarios (por defecto todos: R1,R1c,R2,R3,R3b,R4,R4v4,R5,R6,R7,R8,R9,R10; con SLOW=1, R13);
#                        R4w (WOO=1, sólo si se pide) es el paso de R4 con la versión anterior a F4-Woo, aparte
#   QUICK=1              R1 (sólo REST), R4 y R9, o los de SCENARIOS (R1 sólo por REST y en 1280 px; R4 sin el paso de
#                        4.3.0.163): iteración local, no vale para la aceptación
#   RENDER=<regex>       R1 sólo captura la portada y las páginas cuya clave (slug del título) cumple la expresión: para
#                        repetir una parte del render; no vale para la aceptación
#   CORE=bundled|plugin  núcleo incluido (por defecto) o el plugin Elementor de wordpress.org instalado y activo
#   WOO=0|1              WooCommerce en A y B (plantillas de producto, productos, variaciones, imagen de categoría y, de F4-Woo,
#                        atributos globales con muestras, variaciones con imagen y SKU, widgets y filtros; V.11.5): el
#                        fijado de tests/playground/woocommerce-pin.sh (11.2.1 de reference/, con su sha256, montado en los
#                        tres sitios; WOO_ZIP para otra copia anotada); sin copia local, la última de wordpress.org
#   SLOW=0|1             host de demostración lento y kit de 100 imágenes (A13: ninguna petición REST dura más de 25 s)
#   KIT_GOLDEN=write     reescribe tests/playground/kit-roundtrip/golden/ con las firmas de esta corrida
#   PREPARE=a|b          sólo prepara ese sitio y lo deja arrancado (para revisar la preparación; Ctrl+C para salir)
#   OUT, TMPDIR          resultados (por defecto /var/tmp/digi-k/kit-roundtrip) y temporales de Playground (2 GB libres)
#   PORT_A, PORT_B, PORT_NET   9461, 9462 y 9463; nunca los de otros arneses (9431, 9433, 9435, 9441, 9451, 9453, 9455, 9457,
#                              9481–9489 y 9490–9499)
#   WP_CLI_PHAR          wp-cli.phar local; si no, se descarga una vez a $KIT_CACHE (la misma copia que usa Playground)
#   OLD_ZIP, OLD_ELEMENTS  ZIP o carpeta de DIGITALÍSIMO Elements 4.3.0.163 para el paso de R4 con la versión anterior
#   PREV_ZIP, PREV_ELEMENTS  con WOO=1, ZIP o carpeta de DIGITALÍSIMO Elements 4.3.0.165 (la publicada antes de F4-Woo)
#                        para el paso de R4 en un B sin F4-Woo; por defecto, dist/digitalisimo-elements-4.3.0.165.zip
#                        del repositorio (o del principal, desde un worktree)
#   EXPORT_POST_TYPES    tipos que exporta A, como la App con «Editar contenido» y WP-CLI con --post-types (con WOO=1, por
#                        defecto post,product; vacío: el cuerpo de la App sin personalizar, que no lleva las taxonomías de
#                        los tipos propios)
#   REUSE_A=<OUT>        el kit, la firma y las referencias de A de otra corrida del mismo árbol, sin preparar A (no para
#                        R1 ni R1c): para repartir R4, R4w, R5, R6 y R8 con WOO=1 en corridas cortas
#   ELEMENTS_DIR         otra carpeta de DIGITALÍSIMO Elements para A, B y la red (p. ej. un ZIP publicado, extraído):
#                        la corrida de R1 sin WooCommerce con la versión anterior, para compararla con la de hoy
#   LIVE=1               monta digitalisimo-elements/ y digitalisimo-theme/ del árbol de trabajo; por defecto se monta una
#                        copia fija en $OUT/src, tomada al empezar, para que un cambio a mitad de corrida no mezcle versiones
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
. "$ROOT/tests/playground/playground-cleanup.sh"
K="$ROOT/tests/playground/kit-roundtrip"
export OUT="${OUT:-/var/tmp/digi-k/kit-roundtrip}"
export TMPDIR="${TMPDIR:-/var/tmp/digi-k/tmp}"
export PORT_A="${PORT_A:-9461}" PORT_B="${PORT_B:-9462}" PORT_NET="${PORT_NET:-9463}"
export CORE="${CORE:-bundled}" WOO="${WOO:-0}" SLOW="${SLOW:-0}" QUICK="${QUICK:-}" KIT_GOLDEN="${KIT_GOLDEN:-}" PREPARE="${PREPARE:-}"
export SCENARIOS="${SCENARIOS:-}"
export PLAYGROUND_CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
KIT_CACHE="${KIT_CACHE:-/var/tmp/digi-k/cache}"
PUPPETEER_PATH="${PUPPETEER_PATH:-$(ls -d "$HOME"/.npm/_npx/*/node_modules 2>/dev/null | while read -r d; do [ -d "$d/puppeteer-core" ] && echo "$d" && break; done)}"
[ -n "$PUPPETEER_PATH" ] || { echo "No se encontró puppeteer-core: exportar PUPPETEER_PATH"; exit 1; }
case "$CORE" in bundled|plugin) ;; *) echo "CORE debe ser bundled o plugin"; exit 1;; esac
case "$WOO" in 0|1) ;; *) echo "WOO debe ser 0 o 1"; exit 1;; esac
case "$SLOW" in 0|1) ;; *) echo "SLOW debe ser 0 o 1"; exit 1;; esac
case "$PREPARE" in ''|a|b) ;; *) echo "PREPARE debe ser a o b"; exit 1;; esac

# Puertos: nunca los de otros arneses, y sólo se libera uno ocupado por una corrida anterior de este mismo arnés.
for port in "$PORT_A" "$PORT_B" "$PORT_NET"; do
  case "$port" in 9431|9433|9435|9441|9451|9453|9455|9457|948[1-9]|949[0-9]) echo "El puerto $port es de otro arnés: usa 9461, 9462 y 9463"; exit 1;; esac
done
[ "$PORT_A" != "$PORT_B" ] && [ "$PORT_A" != "$PORT_NET" ] && [ "$PORT_B" != "$PORT_NET" ] || { echo 'PORT_A, PORT_B y PORT_NET deben ser distintos'; exit 1; }
free_port() {
  local port="$1" pids pid cmd
  pids="$(fuser -n tcp "$port" 2>/dev/null | tr -s ' ' || true)"
  [ -z "${pids// /}" ] && return 0
  for pid in $pids; do
    cmd="$(tr '\0' ' ' < "/proc/$pid/cmdline" 2>/dev/null || true)"
    case "$cmd" in
      *kit-roundtrip*|*digi-k*) ;;
      *) echo "El puerto $port está ocupado por otro proceso ($pid: ${cmd:0:120}); no se libera"; return 1;;
    esac
  done
  fuser -k -n tcp "$port" >/dev/null 2>&1 || true
  sleep 1
}
for port in "$PORT_A" "$PORT_B" "$PORT_NET"; do free_port "$port"; done
export -f free_port

mkdir -p "$OUT" "$TMPDIR" "$KIT_CACHE"
# Playground copia cada sitio en $TMPDIR (unos 400 MB) y Chrome guarda allí su perfil: con poco espacio, las escrituras
# fallan con ENOSPC/EDQUOT y la corrida da falsos fallos.
for dir in "$TMPDIR" "$OUT"; do
  free_kb="$(df -Pk "$dir" 2>/dev/null | awk 'NR == 2 { print $4 }')"
  [ -z "$free_kb" ] || [ "$free_kb" -ge 2097152 ] || { echo "$dir tiene $((free_kb / 1024)) MB libres: hacen falta 2 GB (exportar TMPDIR y OUT en otro disco)"; exit 1; }
done

rm -rf "$OUT/a" "$OUT/b" "$OUT/net" "$OUT/shared" "$OUT/shots" "$OUT/scenarios"
rm -f "$OUT/summary.json" "$OUT/run.log" "$OUT/woocommerce.json"
mkdir -p "$OUT/shared/exports" "$OUT/shots" "$OUT/scenarios"

# Fixtures sintéticos deterministas (tests/sources/kits/) y medios remotos de demostración para net-sim.php.
node "$ROOT/tests/sources/kits/build.mjs" --check
node "$ROOT/tests/sources/kits/build.mjs" --out "$OUT/shared/kits" --remote "$OUT/shared/remote"

# WP-CLI: la copia que usa el paso «wp-cli» de los blueprints, descargada una vez por el equipo de prueba.
if [ -n "${WP_CLI_PHAR:-}" ]; then
  cp "$WP_CLI_PHAR" "$OUT/shared/wp-cli.phar"
else
  if [ ! -s "$KIT_CACHE/wp-cli.phar" ]; then
    curl -fsSL -m 120 -o "$KIT_CACHE/wp-cli.phar.part" https://playground.wordpress.net/wp-cli.phar && mv "$KIT_CACHE/wp-cli.phar.part" "$KIT_CACHE/wp-cli.phar"
  fi
  cp "$KIT_CACHE/wp-cli.phar" "$OUT/shared/wp-cli.phar"
fi
cp "$K/cases.json" "$OUT/shared/cases.json"
# SVG sintéticos de los medios de cases.json con «svg_file» (máscara y tesela de F7a).
mkdir -p "$OUT/shared/media" && cp "$ROOT"/tests/sources/kits/media/*.svg "$OUT/shared/media/"

# Plugin y tema: una copia fija para toda la corrida (A, B y la red ven el mismo código aunque el árbol cambie).
if [ "${LIVE:-0}" = 1 ]; then
  SRC="$ROOT"
else
  SRC="$OUT/src"
  rm -rf "$SRC"
  mkdir -p "$SRC"
  cp -a "${ELEMENTS_DIR:-$ROOT/digitalisimo-elements}" "$SRC/digitalisimo-elements"
  cp -a "$ROOT/digitalisimo-theme" "$SRC/"
  git -C "$ROOT" status --porcelain -- digitalisimo-elements digitalisimo-theme > "$SRC/dirty.txt" 2>/dev/null || true
  [ -z "${ELEMENTS_DIR:-}" ] || echo "ELEMENTS_DIR=$ELEMENTS_DIR" >> "$SRC/dirty.txt"
fi
[ -z "${ELEMENTS_DIR:-}" ] || [ "${LIVE:-0}" != 1 ] || { echo 'ELEMENTS_DIR no se combina con LIVE=1'; exit 1; }
[ -z "${ELEMENTS_DIR:-}" ] || [ -f "$ELEMENTS_DIR/pro-elements.php" ] || { echo "ELEMENTS_DIR no es una carpeta de DIGITALÍSIMO Elements: $ELEMENTS_DIR"; exit 1; }

# R4 importa también el kit de A en un B con DIGITALÍSIMO Elements 4.3.0.163 (el asset de la Release v2026.10.21.417):
# lo condicionado por una condición de F7a no debe verse allí (V.13 #10, centinela). OLD_ZIP = ZIP local; si no, se
# descarga una vez a $KIT_CACHE y se comprueba su sha256. OLD_ELEMENTS = carpeta ya extraída (se usa tal cual).
OLD_SHA=ae6f92a7f7a2d0d823c9011a0dea5e54882d19eafb73656c913bfec1efa22c26
if [ -z "${OLD_ELEMENTS:-}" ] && { [ -z "$SCENARIOS" ] || [[ ",$SCENARIOS," == *",R4,"* ]]; } && [ -z "$QUICK" ]; then
  OLD_ZIP="${OLD_ZIP:-$KIT_CACHE/digitalisimo-elements-4.3.0.163.zip}"
  if [ ! -s "$OLD_ZIP" ]; then
    curl -fsSL -m 300 -o "$OLD_ZIP.part" https://github.com/danbutanda/digitalisimo-plugin/releases/download/v2026.10.21.417/digitalisimo-elements-4.3.0.163.zip && mv "$OLD_ZIP.part" "$OLD_ZIP"
  fi
  [ "$(sha256sum "$OLD_ZIP" | cut -d' ' -f1)" = "$OLD_SHA" ] || { echo "$OLD_ZIP no es el asset 4.3.0.163 de v2026.10.21.417 (sha256 distinto)"; exit 1; }
  rm -rf "$OUT/old" && mkdir -p "$OUT/old" && unzip -q "$OLD_ZIP" -d "$OUT/old"
  OLD_ELEMENTS="$OUT/old/digitalisimo-elements"
fi
export OLD_ELEMENTS="${OLD_ELEMENTS:-}"

# R4 con WOO=1 importa además el kit de A en un B con la versión publicada antes de F4-Woo (4.3.0.165, sin
# modules/digitalisimo-woo/): las partes woo-* se ignoran y los widgets de F4-Woo quedan como marcadores (V.11.5).
# PREV_ZIP = ZIP local (por defecto el de dist/ del repositorio o del principal); PREV_ELEMENTS = carpeta ya extraída.
PREV_VERSION=4.3.0.165
if [ "$WOO" = 1 ] && [ -z "${PREV_ELEMENTS:-}" ] && { [ -z "$SCENARIOS" ] || [[ ",$SCENARIOS," == *",R4,"* || ",$SCENARIOS," == *",R4w,"* ]]; }; then
  if [ -z "${PREV_ZIP:-}" ]; then
    common="$(git -C "$ROOT" rev-parse --path-format=absolute --git-common-dir 2>/dev/null || true)"
    for dir in "$ROOT" "${common%/.git}"; do
      [ -n "$dir" ] && [ -s "$dir/dist/digitalisimo-elements-$PREV_VERSION.zip" ] && { PREV_ZIP="$dir/dist/digitalisimo-elements-$PREV_VERSION.zip"; break; }
    done
  fi
  [ -s "${PREV_ZIP:-}" ] || { echo "WOO=1 con R4: falta el ZIP de DIGITALÍSIMO Elements $PREV_VERSION (PREV_ZIP o PREV_ELEMENTS)"; exit 1; }
  rm -rf "$OUT/prev" && mkdir -p "$OUT/prev" && unzip -q "$PREV_ZIP" -d "$OUT/prev"
  PREV_ELEMENTS="$OUT/prev/digitalisimo-elements"
  echo "PREV_ZIP=$PREV_ZIP sha256 $(sha256sum "$PREV_ZIP" | cut -d' ' -f1)" > "$OUT/prev/source.txt"
fi
if [ -n "${PREV_ELEMENTS:-}" ]; then
  grep -q "^ \* Version: $PREV_VERSION\$" "$PREV_ELEMENTS/pro-elements.php" || { echo "PREV_ELEMENTS no es DIGITALÍSIMO Elements $PREV_VERSION"; exit 1; }
  [ ! -d "$PREV_ELEMENTS/modules/digitalisimo-woo" ] || { echo "PREV_ELEMENTS ya trae F4-Woo (modules/digitalisimo-woo/): no es la versión anterior"; exit 1; }
fi
export PREV_ELEMENTS="${PREV_ELEMENTS:-}"

# WOO=1: WooCommerce fijado, extraído una vez en $OUT/woocommerce y montado en A, B y la red (run.cjs lee WOO_DIR).
export WOO_DIR=''
if [ "$WOO" = 1 ]; then
  . "$ROOT/tests/playground/woocommerce-pin.sh"
  woo_pin "$OUT/woocommerce" "$OUT/woocommerce.json" || exit 1
fi

TOKEN="$(head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n')"
export TOKEN
# Carpeta de cada sitio (se monta como wp-content/probe): scripts del arnés y sus opciones.
for site in a b net; do
  mkdir -p "$OUT/$site"
  cp "$K/lib.php" "$K/mu-kit.php" "$K/net-sim.php" "$K/signature.php" "$K/activate.php" "$K/setup-a.php" "$K/setup-b.php" "$K/setup-net.php" "$K/cli.php" "$K/cases.json" "$OUT/$site/"
  cp "$ROOT/tests/playground/front-budget/cases.json" "$OUT/$site/front-budget-cases.json"
  printf '{"site":"%s","token":"%s","core":"%s","woo":%s,"slow":%s,"slow_ms":1500,"hosts":["a.test","b.test","playground.test"]}\n' \
    "$site" "$TOKEN" "$CORE" "$([ "$WOO" = 1 ] && echo true || echo false)" "$([ "$SLOW" = 1 ] && echo true || echo false)" > "$OUT/$site/options.json"
done

STATUS=0
cleanup() {
  for port in "$PORT_A" "$PORT_B" "$PORT_NET"; do fuser -k -n tcp "$port" >/dev/null 2>&1 || true; done
  playground_cleanup
}
trap cleanup EXIT
OUT="$OUT" ROOT="$ROOT" SRC="$SRC" K="$K" NODE_PATH="$PUPPETEER_PATH" node "$K/run.cjs" || STATUS=$?
for site in a b net; do
  # fatal-all.log guarda los de los escenarios anteriores (cada escenario arranca B de nuevo).
  for log in fatal-all.log fatal.log; do
    [ -s "$OUT/$site/$log" ] && { echo "Errores fatales en $site ($log):"; cat "$OUT/$site/$log"; STATUS=1; }
  done
done
[ -f "$OUT/summary.json" ] && echo "Resumen: $OUT/summary.json (copiarlo a docs/fusion/k-kits-evidence.json antes de publicar)"
exit $STATUS
