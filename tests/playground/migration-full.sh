#!/usr/bin/env bash
# «Migrar» lo hace todo (DIGITALÍSIMO Migración, regla del propietario del 2026-11-18) en WordPress Playground, modo
# servidor: un sitio con Hello Elementor 3.5.1 oficial, Element Pack Pro, Rank Math y UpdraftPlus de prueba (sólo su
# cabecera, como f8), contenido con metas de Rank Math y Migración sola activa (Backups, Elements, SEO y el tema los
# instala Migrar desde ZIP locales; el respaldo, que va antes, no lleva Elements: comprimir sus 127 MB con el PHP en
# WebAssembly de Playground pasaba de los 10 minutos de una petición). drive.mjs pulsa Migrar por AJAX con sesión de administrador (doble clic, pantalla cerrada a mitad y vuelta) y
# comprueba: respaldo completo verificado (DIGITALÍSIMO Backups real, instalado desde su ZIP), Backups, SEO y el tema
# instalados con el instalador de WordPress desde ZIP locales (`upgrader_pre_download`, sin GitHub), Elements activado,
# metas de Rank Math importadas por el importador real de SEO, lo reemplazado borrado, Hello cambiado por el tema propio
# y borrado, y la portada respondiendo. SCENARIO=network hace lo mismo en una red de dos sitios. El almacén de Backups
# va en /digi-store, montado, como en los arneses de Backups: con varios workers (la verificación de la portada pide
# una petición a la propia portada), Playground sólo comparte entre ellos /wordpress y lo montado.
#
# Uso: bash tests/playground/migration-full.sh            (sitio)
#      SCENARIO=network bash tests/playground/migration-full.sh
# Variables: OUT (resultados), PORT (9620), HELLO_ZIP (Hello 3.5.1 oficial; por defecto reference/ del repositorio
# principal o .hello.zip en la raíz de esta copia), PLAYGROUND_CLI.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
. "$ROOT/tests/playground/playground-cleanup.sh"
DIR="$ROOT/tests/playground/migration-full"
SCENARIO="${SCENARIO:-site}"
case "$SCENARIO" in site|network) ;; *) echo "SCENARIO debe ser site o network"; exit 1;; esac
OUT="${OUT:-/tmp/digi-migration-full}/$SCENARIO"
PORT="${PORT:-9620}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
rm -rf "$OUT" && mkdir -p "$OUT/zips" "$OUT/store"

HELLO_ZIP="${HELLO_ZIP:-$ROOT/reference/hello-elementor.3.5.1.zip}"
if [ ! -f "$HELLO_ZIP" ]; then
  main_root="$(dirname "$(git -C "$ROOT" rev-parse --path-format=absolute --git-common-dir 2>/dev/null || echo "$ROOT/.git")")"
  HELLO_ZIP="$main_root/reference/hello-elementor.3.5.1.zip"
fi
[ -f "$HELLO_ZIP" ] || HELLO_ZIP="$ROOT/.hello.zip"
[ -f "$HELLO_ZIP" ] || { echo "Falta Hello Elementor 3.5.1 (HELLO_ZIP)"; exit 1; }
sha256sum "$HELLO_ZIP" | grep -q '^c166fdcb091a38897f4f3339f83645e9747234fbc961560027d5563e3efd6447' || { echo "$HELLO_ZIP no es Hello Elementor 3.5.1 oficial"; exit 1; }

cp "$DIR/setup.php" "$DIR/mu-probe.php" "$DIR/check.php" "$OUT/"
echo "$SCENARIO" > "$OUT/scenario"
# ZIP de esta copia con su carpeta de siempre y el índice con las URL oficiales que valida Migración.
python3 - "$ROOT" "$OUT" "$HELLO_ZIP" <<'PY'
import json, os, re, sys, zipfile
root, out, hello = sys.argv[1:4]
def version(path, key):
    m = re.search(r'^\s*\*?\s*' + key + r':\s*(\S+)', open(path, encoding='utf-8').read(), re.M)
    return m.group(1)
def pack(folder, name):
    with zipfile.ZipFile(f'{out}/zips/{name}', 'w', zipfile.ZIP_DEFLATED) as z:
        for base, dirs, files in os.walk(f'{root}/{folder}'):
            dirs[:] = [d for d in dirs if d not in ('node_modules', '.git')]
            for f in files:
                full = os.path.join(base, f)
                z.write(full, os.path.relpath(full, root))
url = 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v2026.11.18.999/'
plugins, themes = {}, {}
for slug, main in (('digitalisimo-backups', 'digitalisimo-backups/digitalisimo-backups.php'), ('digitalisimo-seo', 'digitalisimo-seo/digitalisimo-integrations.php'), ('digitalisimo-elements', 'digitalisimo-elements/pro-elements.php'), ('digitalisimo-migration', 'digitalisimo-migration/digitalisimo-migration.php')):
    v = version(f'{root}/{main}', 'Version')
    plugins[slug] = {'version': v, 'url': f'{url}{slug}-{v}.zip'}
    if slug in ('digitalisimo-backups', 'digitalisimo-seo', 'digitalisimo-elements'):
        pack(slug, f'{slug}-{v}.zip')
v = version(f'{root}/digitalisimo-theme/style.css', 'Version')
themes['digitalisimo-theme'] = {'version': v, 'url': f'{url}digitalisimo-theme-{v}.zip'}
pack('digitalisimo-theme', f'digitalisimo-theme-{v}.zip')
json.dump({'tag': 'v2026.11.18.999', 'plugins': plugins, 'themes': themes}, open(f'{out}/manifest.json', 'w'))
zipfile.ZipFile(hello).extractall(out)
PY

if [ network = "$SCENARIO" ]; then
  STEPS='{"step":"enableMultisite"},'
  BASE='http://playground.test'
else
  STEPS=''
  BASE="http://127.0.0.1:$PORT"
fi
echo "$PORT" > "$OUT/port"
cat > "$OUT/blueprint.json" <<JSON
{"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{"DIGITALISIMO_BACKUPS_STORAGE_DIR":"/digi-store","DISABLE_WP_CRON":true}},${STEPS}{"step":"login"},{"step":"runPHP","code":"<?php require '/wordpress/wp-content/probe/setup.php';"}]}
JSON

$CLI server --port="$PORT" --workers=3 --site-url="$BASE" --blueprint="$OUT/blueprint.json" \
  --mount="$ROOT/digitalisimo-migration:/wordpress/wp-content/plugins/digitalisimo-migration" \
  --mount="$OUT:/wordpress/wp-content/probe" --mount="$OUT/store:/digi-store" > "$OUT/server.log" 2>&1 &
SERVER=$!
stop_server() { kill $SERVER 2>/dev/null || true; for i in 1 2 3; do fuser -k -n tcp "$PORT" >/dev/null 2>&1 || break; sleep 1; done; }
trap 'stop_server; playground_cleanup' EXIT
for i in $(seq 1 180); do
  if ! kill -0 $SERVER 2>/dev/null; then tail -20 "$OUT/server.log"; exit 1; fi
  [ -f "$OUT/setup.done" ] && ! curl -s -H "Host: ${BASE#http://}" "http://127.0.0.1:$PORT/" | grep -q 'not ready' && break
  sleep 2
done
[ -f "$OUT/setup.done" ] || { echo 'La preparación del sitio no terminó'; tail -20 "$OUT/server.log"; exit 1; }
PORT="$PORT" node "$DIR/drive.mjs" "$BASE" "$SCENARIO"
