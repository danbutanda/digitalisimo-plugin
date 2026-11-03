#!/usr/bin/env bash
# F8 (Element Pack 10.x y Prime Slider en DIGITALÍSIMO Migración) en WordPress Playground (modo servidor, un worker) y
# Chrome sin interfaz: escenarios P0–P10 de la especificación (§8.5 con los cambios de V.16). Manual: no corre en CI.
# Cada escenario arranca su propio sitio en su puerto del bloque 9510–9519 con una copia fija de digitalisimo-elements/,
# digitalisimo-migration/ y digitalisimo-theme/ ($OUT/src, con las versiones que se publicarán: ver STAMP) y deja sus
# comprobaciones (ok, falla o pendiente con su motivo) y su duración en $OUT/summary.json.
#
#   P0  9510  A/B de Prime Slider Lite 4.7.0 oficial (sha256 contra OFFICIAL-SOURCES.txt): A con Lite activo, B tras
#             «Migrar» (Lite se convierte activo y se retira al final, nunca se borra)
#   P1  9511  Prime Slider Pro sintético: documentos con los 35 IDs de Pro sin Pro instalado
#   P2  9512  Element Pack sintético en un sitio: plugins falsos con sólo la cabecera y f8/mu-ep-header.php
#             (clean-room); sondeo de la portada en cada paso de «Migrar» (exactamente una cabecera y un pie).
#             PRO=1: además un «Elementor Pro» falso activo (conflicto de V.5.3)
#   P3  9513  red: barrera, liberación por el WP-Cron del sitio principal con la pantalla cerrada, plazo, sitio
#             archivado, sitio bloqueado, Addon en la red y Pro por sitio; SITES=24 añade sitios
#   P4  9514  marcadores de K: kit con widgets del origen importado sin Migración; con Migración, el editor y «Migrar»
#   P5  9515  front-budget.sh: casos f8-tooltip, f8-badge, f8-nested-counter y f8-marker, y los existentes frente a
#             4.3.0.169 (delegado: f8/p5.sh)
#   P6  9516  interaction.sh: información emergente, distintivo en RTL y contador (delegado: f8/p6.sh)
#   P7  9517  marca: brand-audit.sh ONLY=f8 en el editor (es_MX y en_US) y los proveedores de P0 y P2 migrados
#             (delegado: f8/p7.sh)
#   P8  9518  vuelta atrás: Migración 1.0.20 ↔ 1.0.21 y Elements 4.3.0.169 (ZIP publicados, sha256 comprobada)
#   P9  9519  --revert tras la migración de P2
#   P10 9510  A/B con Element Pack Lite 10.0.1 oficial: legacy-ab.sh y migrate.sh (delegado: f8/p10.sh)
#
# Uso: SCENARIOS=P2,P9 bash tests/playground/f8.sh
# Variables:
#   SCENARIOS        lista (por defecto P0–P10); cada escenario cabe en una llamada de 10 min, la corrida entera no:
#                    se parte en tandas con el mismo OUT y APPEND=1
#   APPEND=1         conserva $OUT/summary.json y le suma los escenarios de esta tanda
#   OUT, TMPDIR      resultados (/var/tmp/digi-f8/pg/f8) y temporales (/var/tmp/digi-f8/pg/tmp)
#   STAMP=0          no sube la versión de la copia montada. Por defecto la copia lleva Elements 4.3.0.170 y Migración
#                    1.0.21 (las que publicará la etapa): Migración exige Elements ≥ 4.3.0.170 y trata como «tarea de
#                    1.0.20» la creada por una versión anterior a 1.0.21
#   PRO=1            P2 con el conflicto de Elementor Pro      SITES=24   P3 con 24 sitios más
#   LIVE=1           monta el árbol de trabajo (sin STAMP; P8 queda fuera)
#   KEEP=1           sólo prepara el primer escenario y lo deja arrancado
#   REF_DIR          copias oficiales (por defecto reference/ del repositorio principal)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
STARTED="$(date +%s)"
export TMPDIR="${TMPDIR:-/var/tmp/digi-f8/pg/tmp}"
mkdir -p "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
F="$ROOT/tests/playground/f8"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
OUT="${OUT:-/var/tmp/digi-f8/pg/f8}"
SCENARIOS="${SCENARIOS:-P0,P1,P2,P3,P4,P5,P6,P7,P8,P9,P10}"
for flag in APPEND PRO LIVE KEEP; do case "${!flag:-0}" in 0|1) ;; *) echo "$flag debe ser 0 o 1"; exit 1;; esac; done
MAIN_ROOT="$(dirname "$(git -C "$ROOT" rev-parse --path-format=absolute --git-common-dir 2>/dev/null || echo "$ROOT/.git")")"
REF_DIR="${REF_DIR:-$MAIN_ROOT/reference}"
GIT_SHA="$(git -C "$ROOT" rev-parse --short HEAD 2>/dev/null || echo '')"
RUN_ID="$(date +%Y%m%d-%H%M%S)"
mkdir -p "$OUT" "$OUT/logs"
for dir in "$TMPDIR" "$OUT"; do
  free_kb="$(df -Pk "$dir" 2>/dev/null | awk 'NR == 2 { print $4 }')"
  [ -z "$free_kb" ] || [ "$free_kb" -ge 2097152 ] || { echo "$dir tiene $((free_kb / 1024)) MB libres: hacen falta 2 GB"; exit 1; }
done
[ "${APPEND:-0}" = 1 ] || rm -f "$OUT/summary.json"

PUPPETEER_PATH="${PUPPETEER_PATH:-$(ls -d "$HOME"/.npm/_npx/*/node_modules 2>/dev/null | while read -r d; do [ -d "$d/puppeteer-core" ] && echo "$d" && break; done)}"
[ -n "$PUPPETEER_PATH" ] || { echo "No se encontró puppeteer-core: exportar PUPPETEER_PATH"; exit 1; }

# ── Copias oficiales: sólo en este Playground efímero, con su sha256 contra OFFICIAL-SOURCES.txt ──────────────────
official() { # official <zip> <sha256 esperada> <carpeta de destino>
  local zip="$REF_DIR/$1" want="$2" dest="$3" noted
  [ -s "$zip" ] || { echo "Falta la copia oficial $zip"; return 1; }
  noted="$(awk -v n="$1" '$1 == n { getline; print $2; exit }' "$REF_DIR/OFFICIAL-SOURCES.txt" 2>/dev/null || true)"
  [ "$noted" = "$want" ] || { echo "$1: la suma anotada en OFFICIAL-SOURCES.txt ($noted) no es la esperada"; return 1; }
  [ "$(sha256sum "$zip" | cut -d' ' -f1)" = "$want" ] || { echo "$1: sha256 distinta de la anotada"; return 1; }
  if [ ! -f "$dest/.sha256" ] || [ "$(cat "$dest/.sha256")" != "$want" ]; then
    rm -rf "$dest" && mkdir -p "$dest" && unzip -q "$zip" -d "$dest" && echo "$want" > "$dest/.sha256"
  fi
}
PS_LITE_SHA=a6e86226b2919ec736b2c7dd2c611511bc4cd1ebc5ad260123565ba5704a1601
EP_LITE_SHA=476b853d8d936787991b8983c2b5407b84796e37fc33a5da5774ad914e6dd9d7
# P8: ZIP publicados (Release v2026.11.01.444 o dist/ del repositorio principal, del mismo contenido).
OLD_ELEMENTS_SHAS="29221de3fdb83a1844e1a039798de84f6296b5b258da5d8ee7beab45c4d5c396 526d2b55dadbcb518250f957a29669d320c573cf17910fff07d5365d34c2b52c"
OLD_MIGRATION_SHAS="32ce3fd2d5c817b9a49dcc92a6f3a8a97de5b59dd656c4b574899b9b893a3618 f540700d78d2779226ae25fb077107bd8297e5e0a32dbb24499630dd2def2636"
published() { # published <zip> <sumas aceptadas> <destino>
  local zip="$1" shas="$2" dest="$3" sum
  [ -s "$zip" ] || { echo "Falta $zip"; return 1; }
  sum="$(sha256sum "$zip" | cut -d' ' -f1)"
  [[ " $shas " == *" $sum "* ]] || { echo "$zip no es el ZIP publicado (sha256 $sum)"; return 1; }
  unzip -tq "$zip" >/dev/null || { echo "$zip no supera unzip -t"; return 1; }
  if [ ! -f "$dest/.sha256" ] || [ "$(cat "$dest/.sha256")" != "$sum" ]; then
    rm -rf "$dest" && mkdir -p "$dest" && unzip -q "$zip" -d "$dest" && echo "$sum" > "$dest/.sha256"
  fi
}

# ── Copia fija de lo que se prueba ──────────────────────────────────────────────────────────────────────────────
if [ "${LIVE:-0}" = 1 ]; then
  SRC="$ROOT"
  STAMP=0
else
  SRC="$OUT/src"
  rm -rf "$SRC" && mkdir -p "$SRC"
  cp -a "$ROOT/digitalisimo-elements" "$ROOT/digitalisimo-migration" "$ROOT/digitalisimo-theme" "$SRC/"
  git -C "$ROOT" status --porcelain -- digitalisimo-elements digitalisimo-migration digitalisimo-theme > "$SRC/dirty.txt" 2>/dev/null || true
fi
STAMP="${STAMP:-1}"
ELEMENTS_SHA="$(cd "$SRC/digitalisimo-elements" && find . -type f ! -path './elementor/*' -print0 | LC_ALL=C sort -z | xargs -0 sha256sum | sha256sum | cut -c1-16)"
MIGRATION_SHA="$(cd "$SRC/digitalisimo-migration" && find . -type f -print0 | LC_ALL=C sort -z | xargs -0 sha256sum | sha256sum | cut -c1-16)"
ver_of() { sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([0-9.]*\).*/\1/p' "$1" | head -1; }
TREE_ELEMENTS="$(ver_of "$SRC/digitalisimo-elements/pro-elements.php")"
TREE_MIGRATION="$(ver_of "$SRC/digitalisimo-migration/digitalisimo-migration.php")"
if [ "$STAMP" = 1 ]; then
  # Sólo en la copia montada: la cabecera y la constante con la versión que publicará la etapa (§7).
  sed -i "s/^\( \* Version: \)$TREE_ELEMENTS\$/\14.3.0.170/; s/define( 'DIGITALISIMO_ELEMENTS_VERSION', '$TREE_ELEMENTS' )/define( 'DIGITALISIMO_ELEMENTS_VERSION', '4.3.0.170' )/" "$SRC/digitalisimo-elements/pro-elements.php"
  sed -i "s/^\( \* Version: \)$TREE_MIGRATION\$/\11.0.21/; s/define( 'DIGITALISIMO_MIGRATION_VERSION', '$TREE_MIGRATION' )/define( 'DIGITALISIMO_MIGRATION_VERSION', '1.0.21' )/" "$SRC/digitalisimo-migration/digitalisimo-migration.php"
  grep -q "DIGITALISIMO_ELEMENTS_VERSION', '4.3.0.170'" "$SRC/digitalisimo-elements/pro-elements.php" || { echo 'No se pudo fijar la versión de Elements en la copia'; exit 1; }
  grep -q "DIGITALISIMO_MIGRATION_VERSION', '1.0.21'" "$SRC/digitalisimo-migration/digitalisimo-migration.php" || { echo 'No se pudo fijar la versión de Migración en la copia'; exit 1; }
fi
ELEMENTS_VERSION="$(ver_of "$SRC/digitalisimo-elements/pro-elements.php")"
MIGRATION_VERSION="$(ver_of "$SRC/digitalisimo-migration/digitalisimo-migration.php")"
VENDOR="$OUT/vendor"
mkdir -p "$VENDOR"
node "$ROOT/tests/playground/brand-audit/vendors.cjs" "$ROOT" > "$VENDOR/vendors.json"

# ── Escenarios ─────────────────────────────────────────────────────────────────────────────────────────────────
# conf <P>: PORT, NET (red), WOO, PSLITE (copia oficial de Lite), EPLITE, FAKES (plugins falsos), EP_HEADER, DELEGATE.
conf() {
  NET=0 WOO=0 PSLITE=0 EPLITE=0 FAKES='' EP_HEADER=0 DELEGATE=0
  case "$1" in
    P0) PORT=9510 PSLITE=1 WOO=1;;
    P1) PORT=9511 PSLITE=1;;
    P2) PORT=9512 WOO=1 EP_HEADER=1 FAKES='bdthemes-element-pack bdthemes-element-pack-addon bdthemes-prime-slider bdthemes-prime-slider-lite'
        [ "${PRO:-0}" = 1 ] && FAKES="$FAKES elementor-pro";;
    P3) PORT=9513 NET=1 EP_HEADER=1 FAKES='bdthemes-element-pack bdthemes-element-pack-addon bdthemes-prime-slider bdthemes-prime-slider-lite';;
    P4) PORT=9514 PSLITE=1;;
    P5) PORT=9515 DELEGATE=1;;
    P6) PORT=9516 DELEGATE=1;;
    P7) PORT=9517 DELEGATE=1;;
    P8) PORT=9518 EP_HEADER=1 FAKES='bdthemes-element-pack';;
    P9) PORT=9519 WOO=1 EP_HEADER=1 FAKES='bdthemes-element-pack bdthemes-element-pack-addon bdthemes-prime-slider bdthemes-prime-slider-lite';;
    P10) PORT=9510 DELEGATE=1;;
    *) echo "Escenario desconocido: $1 (P0–P10)"; return 1;;
  esac
  case "$PORT" in 951[0-9]) ;; *) echo "f8.sh usa el bloque 9510–9519: $PORT es de otro arnés"; return 1;; esac
}

# Sólo se libera un puerto ocupado por una corrida anterior de este arnés (nunca el de otro arnés).
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

SERVER=''
stop_server() {
  [ -n "$SERVER" ] && kill "$SERVER" 2>/dev/null || true
  [ -n "${PORT:-}" ] && for i in 1 2 3; do fuser -k -n tcp "$PORT" >/dev/null 2>&1 || break; sleep 1; done
  SERVER=''
}
trap 'stop_server; playground_cleanup' EXIT
trap 'exit 130' INT TERM

# Conserva los registros de la tanda (nunca se pierden entre tandas): $OUT/logs/<P>-<tanda>-<registro>.
keep_logs() {
  local p="$1" run="$2" f
  for f in fatal.log php-errors.log http-blocked.log http-out.log setup-error.txt setup-warnings.txt server.log; do
    [ -s "$run/$f" ] && cp "$run/$f" "$OUT/logs/$p-$RUN_ID-$f"
  done
  return 0
}

status=0
run_scenario() {
  local P="$1" p run host site_url token steps mounts=() fake ready=0
  conf "$P"
  p="$(echo "$P" | tr 'P' 'p')"
  run="$OUT/$P"
  rm -rf "$run" && mkdir -p "$run/shots" "$run/pages"
  echo "── $P · puerto $PORT · $run"
  if [ "$DELEGATE" = 1 ]; then
    # El escenario usa otro arnés: f8/<p>.sh lo lanza con este puerto y deja su resultado en $run; f8/<p>.cjs lo lee.
    [ -f "$F/$p.sh" ] || { echo "Falta $F/$p.sh"; return 1; }
    free_port "$PORT" || return 1
    local t0; t0="$(date +%s)"
    ROOT="$ROOT" RUN="$run" PORT="$PORT" SRC="$SRC" REF_DIR="$REF_DIR" VENDOR="$VENDOR" OUT_F8="$OUT" PUPPETEER_PATH="$PUPPETEER_PATH" \
      bash "$F/$p.sh" > "$run/delegate.log" 2>&1 || echo "  $p.sh terminó con error (ver $run/delegate.log)"
    tail -5 "$run/delegate.log" | sed 's/^/  /'
    node -e 'require("fs").writeFileSync(process.argv[1] + "/env.json", JSON.stringify(Object.fromEntries(process.argv.slice(2).map((kv) => [kv.slice(0, kv.indexOf("=")), kv.slice(kv.indexOf("=") + 1)])), null, 1))' "$run" \
      "OUT=$OUT" "RUN=$run" "ROOT=$ROOT" "SCENARIO=$P" "PORT=$PORT" "DELEGATED=1" "GIT_SHA=$GIT_SHA" "STARTED=$t0" "RUN_ID=$RUN_ID" \
      "ELEMENTS_VERSION=$ELEMENTS_VERSION" "ELEMENTS_SHA=$ELEMENTS_SHA" "MIGRATION_VERSION=$MIGRATION_VERSION" "MIGRATION_SHA=$MIGRATION_SHA" "SRC=$SRC"
    ENV_FILE="$run/env.json" NODE_PATH="$PUPPETEER_PATH" node "$F/run.cjs" || status=1
    keep_logs "$P" "$run"
    return 0
  fi
  free_port "$PORT" || return 1
  cp "$F"/{probe.php,lib.php,setup.php,mu-ep-header.php,cli.php} "$F/$p.php" "$run/"
  cp "$VENDOR/vendors.json" "$run/vendors.json"
  cp /var/tmp/digi-f8/pg/cache/wp-cli.phar "$run/wp-cli.phar" 2>/dev/null || cp "${WP_CLI_PHAR:-/var/tmp/digi-k/cache/wp-cli.phar}" "$run/wp-cli.phar" 2>/dev/null || true
  token="$(head -c 18 /dev/urandom | od -An -tx1 | tr -d ' \n')"
  if [ "$NET" = 1 ]; then host='playground.test'; site_url='http://playground.test'; else host="127.0.0.1:$PORT"; site_url="http://$host"; fi
  node -e '
const [out, scenario, file, token, net, woo, epHeader, pro, sites, host] = process.argv.slice(1);
require("fs").writeFileSync(out + "/mode.json", JSON.stringify({ scenario, scenario_file: file, token, net: net === "1", woo: woo === "1", ep_header: epHeader === "1", pro: pro === "1", sites: Number(sites) || 0, host, writable: ["elementor_active_kit"] }, null, 1));
' "$run" "$P" "$p.php" "$token" "$NET" "$WOO" "$EP_HEADER" "${PRO:-0}" "${SITES:-0}" "$host"
  steps=''
  [ "$NET" = 1 ] && steps='{"step":"enableMultisite"},'
  steps="$steps"'{"step":"runPHP","code":"<?php putenv('"'"'DIGI_F8_PHASE=activate'"'"'); require '"'"'/wordpress/wp-content/probe/setup.php'"'"';"},'
  steps="$steps"'{"step":"runPHP","code":"<?php putenv('"'"'DIGI_F8_PHASE=content'"'"'); require '"'"'/wordpress/wp-content/probe/setup.php'"'"';"}'
  printf '{"steps":[%s]}\n' "$steps" > "$run/blueprint.json"
  mounts=(--mount="$SRC/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements"
    --mount="$SRC/digitalisimo-migration:/wordpress/wp-content/plugins/digitalisimo-migration"
    --mount="$SRC/digitalisimo-theme:/wordpress/wp-content/themes/digitalisimo-theme"
    --mount="$run:/wordpress/wp-content/probe"
    --mount="$ROOT/tests/legacy:/wordpress/wp-content/digi-legacy-fixtures"
    --mount="$ROOT/tests/sources/migration:/wordpress/wp-content/digi-sources")
  for fake in $FAKES; do mounts+=(--mount="$F/fake-plugins/$fake:/wordpress/wp-content/plugins/$fake"); done
  if [ "$PSLITE" = 1 ]; then
    official bdthemes-prime-slider-lite.4.7.0.zip "$PS_LITE_SHA" "$VENDOR/ps-lite" || return 1
    mounts+=(--mount="$VENDOR/ps-lite/bdthemes-prime-slider-lite:/wordpress/wp-content/plugins/bdthemes-prime-slider-lite")
  fi
  if [ "$EPLITE" = 1 ]; then
    official bdthemes-element-pack-lite.10.0.1.zip "$EP_LITE_SHA" "$VENDOR/ep-lite" || return 1
    mounts+=(--mount="$VENDOR/ep-lite/bdthemes-element-pack-lite:/wordpress/wp-content/plugins/bdthemes-element-pack-lite")
  fi
  if [ "$WOO" = 1 ]; then
    . "$ROOT/tests/playground/woocommerce-pin.sh"
    if [ ! -f "$VENDOR/woocommerce/plugin/woocommerce/woocommerce.php" ]; then WOO_REQUIRE=1 woo_pin "$VENDOR/woocommerce" "$VENDOR/woocommerce.json" || return 1; fi
    mounts+=(--mount="$VENDOR/woocommerce/plugin/woocommerce:/wordpress/wp-content/plugins/woocommerce")
  fi
  if [ "$P" = P8 ]; then
    # Los ZIP publicados de 4.3.0.169 y 1.0.20 (Release v2026.11.01.444; dist/ tiene el mismo contenido).
    local rel="/var/tmp/digi-f8/pg/rel" ez mz
    ez="$rel/digitalisimo-elements-4.3.0.169.zip"; [ -s "$ez" ] || ez="$MAIN_ROOT/dist/digitalisimo-elements-4.3.0.169.zip"
    mz="$rel/digitalisimo-migration-1.0.20.zip"; [ -s "$mz" ] || mz="$MAIN_ROOT/dist/digitalisimo-migration-1.0.20.zip"
    published "$ez" "$OLD_ELEMENTS_SHAS" "$VENDOR/elements-169" || return 1
    published "$mz" "$OLD_MIGRATION_SHAS" "$VENDOR/migration-1020" || return 1
    # P8 cambia las versiones en caliente: monta copias propias que el escenario sustituye (nunca $SRC).
    rm -rf "$run/mnt" && mkdir -p "$run/mnt"
    cp -a "$SRC/digitalisimo-elements" "$SRC/digitalisimo-migration" "$run/mnt/"
    mounts[0]="--mount=$run/mnt/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements"
    mounts[1]="--mount=$run/mnt/digitalisimo-migration:/wordpress/wp-content/plugins/digitalisimo-migration"
  fi
  echo "f8 · $P · Elements $ELEMENTS_VERSION ($ELEMENTS_SHA) · Migración $MIGRATION_VERSION ($MIGRATION_SHA) · $site_url"
  local t0; t0="$(date +%s)"
  $CLI server --port="$PORT" --workers=1 --site-url="$site_url" --blueprint="$run/blueprint.json" "${mounts[@]}" > "$run/server.log" 2>&1 &
  SERVER=$!
  for i in $(seq 1 200); do
    sleep 3
    if ! kill -0 "$SERVER" 2>/dev/null; then echo 'Playground terminó durante la preparación:'; tail -20 "$run/server.log"; keep_logs "$P" "$run"; status=1; return 0; fi
    [ -f "$run/setup-error.txt" ] && break
    if [ -f "$run/ready.txt" ] && curl -s -m 60 -H "Host: $host" "http://127.0.0.1:$PORT/?digi_f8=ping&token=$token" 2>/dev/null | grep -q '"ready":[1-9]'; then ready=1; break; fi
  done
  if [ -f "$run/setup-error.txt" ] || [ "$ready" != 1 ]; then
    echo "Preparación incompleta de $P:"; cat "$run/setup-error.txt" 2>/dev/null || tail -20 "$run/server.log"
    node -e '
const fs = require("fs"); const [out, P, why, t0] = process.argv.slice(1); const f = out + "/summary.json";
let s = {}; try { s = JSON.parse(fs.readFileSync(f, "utf8")); } catch (e) { s = { harness: "f8", scenarios: {} }; }
s.scenarios = s.scenarios || {}; s.scenarios[P] = { id: P, status: "falla", checks: [{ id: P + ".0", what: "preparación del sitio", status: "falla", detail: why.slice(0, 1500) }], problems: [why.slice(0, 300)], ms: (Date.now() / 1000 - t0) * 1000 };
fs.writeFileSync(f, JSON.stringify(s, null, 1) + "\n");' "$OUT" "$P" "$(cat "$run/setup-error.txt" 2>/dev/null || echo 'el sitio no quedó listo')" "$t0"
    stop_server; keep_logs "$P" "$run"; status=1; return 0
  fi
  echo "  sitio listo en $(( $(date +%s) - t0 )) s"
  node -e 'require("fs").writeFileSync(process.argv[1] + "/env.json", JSON.stringify(Object.fromEntries(process.argv.slice(2).map((kv) => [kv.slice(0, kv.indexOf("=")), kv.slice(kv.indexOf("=") + 1)])), null, 1))' "$run" \
    "OUT=$OUT" "RUN=$run" "ROOT=$ROOT" "SCENARIO=$P" "BASE=$site_url" "PORT=$PORT" "HOST=$host" "TOKEN=$token" "NET=$NET" "WOO=$WOO" \
    "PRO=${PRO:-0}" "SITES=${SITES:-0}" "GIT_SHA=$GIT_SHA" "STARTED=$t0" "RUN_ID=$RUN_ID" "SRC=$SRC" "VENDOR=$VENDOR" \
    "ELEMENTS_VERSION=$ELEMENTS_VERSION" "ELEMENTS_SHA=$ELEMENTS_SHA" "MIGRATION_VERSION=$MIGRATION_VERSION" "MIGRATION_SHA=$MIGRATION_SHA" \
    "TREE_ELEMENTS=$TREE_ELEMENTS" "TREE_MIGRATION=$TREE_MIGRATION" "STAMP=$STAMP" "LIVE=${LIVE:-0}"
  if [ "${KEEP:-0}" = 1 ]; then
    echo "KEEP=1: $P en $site_url (puerto $PORT, token $token). A mano: ENV_FILE=$run/env.json NODE_PATH=$PUPPETEER_PATH node $F/run.cjs · Ctrl+C para terminar"
    while kill -0 "$SERVER" 2>/dev/null; do sleep 5; done
    exit 0
  fi
  ENV_FILE="$run/env.json" NODE_PATH="$PUPPETEER_PATH" node "$F/run.cjs" || status=1
  stop_server
  keep_logs "$P" "$run"
  [ -s "$run/fatal.log" ] && { echo "  Errores fatales de PHP en $P:"; sed 's/^/    /' "$run/fatal.log" | head -10; }
  [ -s "$run/php-errors.log" ] && { echo "  Avisos de PHP de los plugins propios en $P (ver $OUT/logs/):"; sed -E 's/^[^ ]+ [^ ]+ //' "$run/php-errors.log" | sort | uniq -c | sort -rn | head -5 | sed 's/^/    /'; }
  return 0
}

IFS=',' read -r -a LIST <<< "$SCENARIOS"
for P in "${LIST[@]}"; do
  P="$(echo "$P" | tr -d ' ')"
  [ -n "$P" ] || continue
  conf "$P" || exit 1
done
for P in "${LIST[@]}"; do
  P="$(echo "$P" | tr -d ' ')"
  [ -n "$P" ] || continue
  run_scenario "$P" || status=1
done
echo "Resumen: $OUT/summary.json · registros: $OUT/logs/ · duración: $(( $(date +%s) - STARTED )) s"
exit $status
