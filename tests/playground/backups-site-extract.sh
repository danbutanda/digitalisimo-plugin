#!/usr/bin/env bash
# «Extraer como WordPress individual» de DIGITALÍSIMO Backups en WordPress Playground (SQLite), sin Chrome, de punta a
# punta y por las superficies reales:
#   red      (run-blueprint, un proceso PHP por paso: backups-site-extract/network.php) una red por subdirectorios en
#            red.test con los sitios /dos/ y /tres/, DIGITALÍSIMO Elements activo en ellos y Backups en la red; usuarios
#            en varios roles (dueña, editora, un suscriptor sólo del sitio 2, una administradora sólo del sitio 3, un
#            superadministrador autor del sitio 2 y otro que no lo administra); una página de Elements con imagen,
#            fondo y enlaces, y su CSS generado. «Extraer como WordPress individual» del sitio 2 por el handler
#            admin-post real (superadministrador, nonce, alcance de red), con la cola avanzada por ticks.
#   destino  (server, otra URL: 127.0.0.1:<puerto>, el prefijo wp_ de un WordPress recién instalado) Backups activo, el
#            paquete copiado a su almacén como un archivo suelto y restaurado (backups-site-extract/destination.php);
#            después check.mjs compara con el inventario de la red por HTTP: contenido, usuarios con sus roles e
#            inicio de sesión real por wp-login.php, medios en uploads/ servidos, la página de Elements con su CSS
#            regenerado, plugins activos, ajustes de red materializados, la configuración de Backups del destino y
#            ningún rastro del sitio de la red salvo lo declarado.
# Las copias de los plugins, el almacén y la carpeta de la sonda se montan desde la carpeta de resultados: una
# restauración nunca escribe en el repositorio.
# Uso: bash tests/playground/backups-site-extract.sh (en el servidor de desarrollo, por el semáforo de corridas pesadas
#      y con systemd-run --user)
#   OUT, TMPDIR   resultados (por defecto /var/tmp/digi-bk/backups-site-extract) y temporales de Playground
#   PORT          puerto del destino (por defecto 9471)
#   ELEMENTS_DIR  copia de DIGITALÍSIMO Elements (por defecto la del repositorio, que se copia a OUT)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
D="$ROOT/tests/playground/backups-site-extract"
export OUT="${OUT:-/var/tmp/digi-bk/backups-site-extract}"
export TMPDIR="${TMPDIR:-/var/tmp/digi-bk/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PORT="${PORT:-9471}"
SERVER=''
SERVER_PORT=''
# Sólo libera el puerto si este ensayo arrancó ahí su servidor: nunca el de otra corrida.
stop_server() {
  [ -n "$SERVER" ] && kill "$SERVER" 2>/dev/null || true
  [ -n "$SERVER_PORT" ] && for i in 1 2 3; do fuser -k -n tcp "$SERVER_PORT" >/dev/null 2>&1 || break; sleep 1; done
  SERVER=''
  SERVER_PORT=''
}
trap 'stop_server; playground_cleanup' EXIT
trap 'exit 130' INT TERM
start=$(date +%s)
rm -rf "$OUT/red" "$OUT/destino"
mkdir -p "$OUT/red/probe" "$OUT/red/store" "$OUT/destino/probe" "$OUT/destino/store" "$OUT/destino/web"
chmod 700 "$OUT/red/store" "$OUT/destino/store"
cp -a "$ROOT/digitalisimo-backups" "$OUT/red/backups"
cp -a "$ROOT/digitalisimo-backups" "$OUT/destino/backups"
[ -d "$OUT/elements" ] && [ -z "${REFRESH_ELEMENTS:-}" ] || { rm -rf "$OUT/elements"; cp -a "${ELEMENTS_DIR:-$ROOT/digitalisimo-elements}" "$OUT/elements"; }
cp "$D/network.php" "$OUT/red/probe/"

# Fase «red».
steps=''
for item in setup:1 css:2 extract:1; do
  step="${item%%:*}"; blog="${item##*:}"
  steps="$steps,{\"step\":\"runPHP\",\"code\":\"<?php putenv('DIGI_STEP=$step'); putenv('DIGI_BLOG=$blog'); require '/digi-probe/network.php';\"}"
done
cat > "$OUT/red/blueprint.json" <<JSON
{"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{"DIGITALISIMO_BACKUPS_STORAGE_DIR":"/digi-store","AUTH_KEY":"red-auth-key-0123456789abcdef0123456789abcdef","SECURE_AUTH_KEY":"red-secure-auth-key-0123456789abcdef0123456789"}},
{"step":"enableMultisite"}$steps]}
JSON
begun=$(date +%s)
$CLI run-blueprint --site-url=http://red.test --blueprint="$OUT/red/blueprint.json" \
  --mount="$OUT/red/backups:/wordpress/wp-content/plugins/digitalisimo-backups" \
  --mount="$OUT/elements:/wordpress/wp-content/plugins/digitalisimo-elements" \
  --mount="$OUT/red/probe:/digi-probe" \
  --mount="$OUT/red/store:/digi-store" > "$OUT/red/playground.log" 2>&1 || { echo "== red: Playground terminó con error"; tail -30 "$OUT/red/playground.log"; exit 1; }
echo "== red ($(( $(date +%s) - begun )) s)"
node -e '
const r = require(process.argv[1]); let bad = 0;
for (const [step, out] of Object.entries(r)) { for (const p of out.problems) { console.log(`FALLA · ${step}: ${p}`); bad++; } if (!out.problems.length) console.log(`ok · ${step} ${JSON.stringify(out.data).slice(0, 400)}`); }
for (const s of ["setup", "css", "extract"]) if (!r[s]) { console.log(`FALLA · falta el paso ${s}`); bad++; }
process.exit(bad ? 1 : 0);' "$OUT/red/probe/result.json" || { cat "$OUT/red/probe/fatal.log" 2>/dev/null || true; exit 1; }
[ ! -s "$OUT/red/probe/fatal.log" ] || { cat "$OUT/red/probe/fatal.log"; exit 1; }
package="$(node -e 'console.log(require(process.argv[1]).package)' "$OUT/red/probe/state.json")"
cp "$OUT/red/store/$package" "$OUT/destino/store/"
cp "$OUT/red/probe/state.json" "$OUT/red/probe/inventory.json" "$OUT/destino/probe/"
echo "$package" > "$OUT/destino/probe/package.txt"
cp "$D/destination.php" "$OUT/destino/probe/"
cp "$D/check.php" "$OUT/destino/web/"
head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n' > "$OUT/destino/web/token.txt"
echo "· paquete $package ($(du -h "$OUT/destino/store/$package" | cut -f1))"

# Fase «destino»: otro WordPress, otra URL, otras claves de wp-config.php.
cat > "$OUT/destino/blueprint.json" <<JSON
{"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{"DIGITALISIMO_BACKUPS_STORAGE_DIR":"/digi-store","WP_ENVIRONMENT_TYPE":"local","AUTH_KEY":"destino-auth-key-0123456789abcdef0123456789abcdef","SECURE_AUTH_KEY":"destino-secure-auth-key-0123456789abcdef0123456789"}},
{"step":"runPHP","code":"<?php require '/digi-probe/destination.php';"}]}
JSON
if fuser -n tcp "$PORT" >/dev/null 2>&1; then echo "== destino: el puerto $PORT está ocupado; usa PORT=…"; exit 1; fi
begun=$(date +%s)
$CLI server --port="$PORT" --workers=1 --site-url="http://127.0.0.1:$PORT" --blueprint="$OUT/destino/blueprint.json" \
  --mount="$OUT/destino/backups:/wordpress/wp-content/plugins/digitalisimo-backups" \
  --mount="$OUT/destino/web:/wordpress/wp-content/digi-web" \
  --mount="$OUT/destino/probe:/digi-probe" \
  --mount="$OUT/destino/store:/digi-store" > "$OUT/destino/server.log" 2>&1 &
SERVER=$!
SERVER_PORT="$PORT"
ready=0
for i in $(seq 1 600); do
  sleep 3
  kill -0 "$SERVER" 2>/dev/null || break
  [ -f "$OUT/destino/probe/setup-error.txt" ] && break
  if [ -s "$OUT/destino/probe/restore.json" ] && [ "$(curl -s -o /dev/null -w '%{http_code}' -m 120 "http://127.0.0.1:$PORT/?rest_route=%2F")" = 200 ]; then ready=1; break; fi
done
if [ "$ready" != 1 ]; then
  echo "== destino: no quedó listo"; cat "$OUT/destino/probe/setup-error.txt" 2>/dev/null || true; tail -30 "$OUT/destino/server.log"; exit 1
fi
echo "== destino listo en $(( $(date +%s) - begun )) s (puerto $PORT)"
fail=0
mkdir -p "$OUT/destino/probe"
node "$D/check.mjs" "$PORT" "$OUT/destino" || fail=1
grep -qE 'Fatal error|Uncaught' "$OUT/destino/server.log" && { echo "== destino: errores de PHP en el servidor"; grep -E 'Fatal error|Uncaught' "$OUT/destino/server.log" | head -5; fail=1; }
stop_server
[ $fail -eq 0 ] || { echo 'Ensayo de «Extraer como WordPress individual» con fallos'; exit 1; }
echo "«Extraer como WordPress individual» validado en Playground en $(( $(date +%s) - start )) s."
