#!/usr/bin/env bash
# Instalador de DIGITALÍSIMO Elements de punta a punta en WordPress Playground, con Chrome y las pantallas reales:
# un WordPress nuevo con los límites de subida de un hosting típico (upload_max_filesize = 2M, post_max_size = 8M) donde
# el ZIP de Elements (31 MB) no entra por Plugins → Añadir nuevo → Subir plugin y el instalador (pocos KB) sí; al
# activarlo, Elements queda instalado y activo y el instalador, borrado. Modos (MODES, por defecto «single network site»):
#   single   WordPress individual; primero con el índice inaccesible (mensaje, «Reintentar», el instalador se queda) y
#            después «Reintentar» con el índice de vuelta;
#   network  red, instalador activado en toda la red: Elements activo en toda la red;
#   site     red, instalador activado sólo en el subsitio «uno»: Elements activo sólo en «uno»;
#   github   (opcional, no está en los modos por defecto) WordPress individual contra la última Release real de GitHub.
# Para no depender de GitHub, en single, network y site el índice y el ZIP de Elements salen de dist/ por un servidor
# local (server.cjs, 127.0.0.1) con la forma de las Releases; el índice lo genera scripts/release-manifest.mjs con los
# 8 ZIP de dist/ y sólo se cambia su base. El instalador sólo acepta ese origen con el filtro de pruebas
# digitalisimo_elements_installer_test_source del mu-plugin del ensayo (y sólo de bucle local): la lista blanca de
# producción sólo admite las Releases oficiales de GitHub.
# Los límites se escriben en el php.ini de php-wasm (setup.php) y se aplican cuando Playground rota su runtime: el
# arnés lo fuerza con peticiones a /digi-ping.php hasta que responde «2M|8M».
# Uso: bash tests/playground/elements-installer.sh   (en la PC, con pc-run.sh)
#   OUT, TMPDIR     resultados (por defecto /var/tmp/digi-el/elements-installer) y temporales de Playground
#   MODES           modos a correr
#   PORT            primer puerto de Playground (por defecto 9531; uno por modo); SRV_PORT el del servidor local (9539)
#   ELEMENTS_ZIP    ZIP de Elements (por defecto el de dist/)
#   PUPPETEER_PATH  carpeta node_modules con puppeteer-core (si no, se busca en la caché de npx o se instala 25.12.0)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
D="$ROOT/tests/playground/elements-installer"
export OUT="${OUT:-/var/tmp/digi-el/elements-installer}"
export TMPDIR="${TMPDIR:-/var/tmp/digi-el/tmp}"
mkdir -p "$OUT" "$TMPDIR"
OUT="$(cd "$OUT" && pwd)"
. "$ROOT/tests/playground/playground-cleanup.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PORT="${PORT:-9531}"
SRV_PORT="${SRV_PORT:-9539}"
MODES="${MODES:-single network site}"
SERVER=''
SERVER_PORT=''
SRV=''
stop_server() {
  [ -n "$SERVER" ] && kill "$SERVER" 2>/dev/null || true
  [ -n "$SERVER_PORT" ] && for i in 1 2 3; do fuser -k -n tcp "$SERVER_PORT" >/dev/null 2>&1 || break; sleep 1; done
  SERVER=''; SERVER_PORT=''
}
stop_all() {
  stop_server
  [ -n "$SRV" ] && kill "$SRV" 2>/dev/null || true
  playground_cleanup
}
trap stop_all EXIT
trap 'exit 130' INT TERM

if [ -z "${PUPPETEER_PATH:-}" ]; then
  PUPPETEER_PATH=$(ls -d "$HOME"/.npm/_npx/*/node_modules 2>/dev/null | while read -r d; do [ -d "$d/puppeteer-core" ] && echo "$d" && break; done || true)
fi
if [ -z "${PUPPETEER_PATH:-}" ]; then
  mkdir -p "$TMPDIR/pptr"
  npm install --silent --no-audit --no-fund --prefix "$TMPDIR/pptr" puppeteer-core@25.12.0 >/dev/null
  PUPPETEER_PATH="$TMPDIR/pptr/node_modules"
fi
export PUPPETEER_PATH

# Paquetes: el instalador con su guarda y el ZIP de Elements de dist/.
rm -rf "$OUT/pkg" "$OUT/idx" "$OUT/srv"
mkdir -p "$OUT/pkg" "$OUT/idx" "$OUT/srv/releases/latest/download"
bash "$ROOT/scripts/build-packages.sh" "$OUT/pkg" digitalisimo-elements-installer > "$OUT/build.log" 2>&1 || { cat "$OUT/build.log"; exit 1; }
INSTALLER_ZIP="$OUT/pkg/digitalisimo-elements-installer.zip"
ELEMENTS_ZIP="${ELEMENTS_ZIP:-$(ls "$ROOT"/dist/digitalisimo-elements-*.zip | sort -V | tail -1)}"
[ -s "$ELEMENTS_ZIP" ] || { echo "Falta el ZIP de Elements en dist/ (o ELEMENTS_ZIP)"; exit 2; }
echo "== Instalador: $(wc -c < "$INSTALLER_ZIP") bytes · Elements: $(basename "$ELEMENTS_ZIP") ($(( $(wc -c < "$(readlink -f "$ELEMENTS_ZIP")") / 1048576 )) MB)"

# Índice con la forma de una Release (los 8 ZIP de dist/ por scripts/release-manifest.mjs) servido en 127.0.0.1.
TAG="v$(date -u +%Y.%m.%d).9999"
for zip in "$ROOT"/dist/digitalisimo-*.zip; do ln -s "$(readlink -f "$zip")" "$OUT/idx/$(basename "$zip")"; done
node "$ROOT/scripts/release-manifest.mjs" "$OUT/idx" "$TAG" > "$OUT/index.log"
sed "s#https://github.com/danbutanda/digitalisimo-plugin/#http://127.0.0.1:$SRV_PORT/#g" "$OUT/idx/digitalisimo-plugins.json" > "$OUT/srv/releases/latest/download/digitalisimo-plugins.json"
mkdir -p "$OUT/srv/releases/download/$TAG"
ln -s "$(readlink -f "$ELEMENTS_ZIP")" "$OUT/srv/releases/download/$TAG/$(basename "$ELEMENTS_ZIP")"
grep -q "\"url\": \"http://127.0.0.1:$SRV_PORT/releases/download/$TAG/$(basename "$ELEMENTS_ZIP")\"" "$OUT/srv/releases/latest/download/digitalisimo-plugins.json" \
  || { echo 'El índice de prueba no apunta al ZIP de Elements de dist/'; cat "$OUT/srv/releases/latest/download/digitalisimo-plugins.json"; exit 1; }
if fuser -n tcp "$SRV_PORT" >/dev/null 2>&1; then echo "el puerto $SRV_PORT está ocupado; usa SRV_PORT=…"; exit 1; fi
: > "$OUT/srv-requests.log"
rm -f "$OUT/srv-fail-index"
node "$D/server.cjs" "$OUT/srv" "$SRV_PORT" "$OUT/srv-requests.log" "$OUT/srv-fail-index" &
SRV=$!

start=$(date +%s)
fail=0
port=$PORT
for mode in $MODES; do
  case "$mode" in
    single) multi=''; host='instalador-solo.test'; source=true ;;
    github) multi=''; host='instalador-github.test'; source=false ;;
    network) multi='{"step":"enableMultisite"},'; host='instalador-red.test'; source=true ;;
    site) multi='{"step":"enableMultisite"},'; host='instalador-sitio.test'; source=true ;;
    *) echo "Modo desconocido: $mode"; exit 2 ;;
  esac
  dir="$OUT/$mode"
  rm -rf "$dir"; mkdir -p "$dir/probe"
  cp "$D/setup.php" "$D/mu-plugin.php" "$dir/probe/"
  : > "$OUT/srv-requests.log"
  token="$(head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n')"
  node -e 'const fs = require("fs"); const [file, token, port, source, out, inst, el] = process.argv.slice(1); fs.writeFileSync(file, JSON.stringify({ token, srv_port: Number(port), test_source: source === "true", srv_log: out + "/srv-requests.log", fail_file: out + "/srv-fail-index", installer_zip: inst, elements_zip: el }));' \
    "$dir/probe/mode.json" "$token" "$SRV_PORT" "$source" "$OUT" "$INSTALLER_ZIP" "$(readlink -f "$ELEMENTS_ZIP")"
  cat > "$dir/blueprint.json" <<JSON
{"steps":[$multi{"step":"runPHP","code":"<?php require '/digi-probe/setup.php';"}]}
JSON
  if fuser -n tcp "$port" >/dev/null 2>&1; then echo "el puerto $port está ocupado; usa PORT=…"; exit 1; fi
  begun=$(date +%s)
  $CLI server --port="$port" --workers=1 --site-url="http://$host" --blueprint="$dir/blueprint.json" \
    --mount="$dir/probe:/digi-probe" > "$dir/server.log" 2>&1 &
  SERVER=$!
  SERVER_PORT="$port"
  ready=0
  for i in $(seq 1 400); do
    sleep 3
    kill -0 "$SERVER" 2>/dev/null || break
    [ -f "$dir/probe/setup-error.txt" ] && break
    if [ -s "$dir/probe/setup.json" ] && [ "$(curl -s -o /dev/null -w '%{http_code}' -m 60 -H "Host: $host" "http://127.0.0.1:$port/digi-ping.php")" = 200 ]; then ready=1; break; fi
  done
  if [ "$ready" != 1 ]; then
    echo "== $mode: el sitio no quedó listo"; cat "$dir/probe/setup-error.txt" 2>/dev/null || true; tail -20 "$dir/server.log"
    stop_server; fail=1; port=$((port + 1)); continue
  fi
  # Los límites del php.ini se aplican al rotar el runtime de PHP (cada 400 peticiones): se fuerza la rotación.
  limits=''
  for n in $(seq 1 1200); do
    limits="$(curl -s -m 30 -H "Host: $host" "http://127.0.0.1:$port/digi-ping.php" || true)"
    [ "$limits" = '2M|8M' ] && break
  done
  echo "== $mode: sitio listo en $(( $(date +%s) - begun )) s ($(cat "$dir/probe/setup.json")); límites $limits tras $n peticiones"
  [ "$limits" = '2M|8M' ] || { echo "== $mode: los límites de subida no se aplicaron"; stop_server; fail=1; port=$((port + 1)); continue; }
  node "$D/run.mjs" "$mode" "$port" "$host" "$dir" || fail=1
  if grep -E 'PHP (Fatal|Parse) error|Fatal error:' "$dir/server.log" | head -5 | grep -q .; then echo "== $mode: errores fatales de PHP en el servidor"; grep -E 'PHP (Fatal|Parse) error|Fatal error:' "$dir/server.log" | head -5; fail=1; fi
  stop_server; sleep 2
  port=$((port + 1))
done
[ $fail -eq 0 ] || { echo "Ensayo del instalador con fallos ($OUT/*/summary.json)"; exit 1; }
echo "Instalador de Elements en Playground: modos $MODES en verde en $(( $(date +%s) - start )) s ($OUT/*/summary.json)."
