#!/usr/bin/env bash
# Paridad visual del cambio de tema: Hello Elementor 3.5.1 o un hijo suyo frente al tema DIGITALÍSIMO, con un kit con
# colores y tipografías globales y «Estilo del tema», en WordPress Playground (modo servidor) y Chrome sin interfaz.
# Compara estilos calculados de los elementos clave, píxeles y hojas antes y después del cambio, la vuelta atrás y, con
# VIA=migrar, el cambio que hace «Migrar» de DIGITALÍSIMO Migración (que instala Backups, Elements y el tema desde ZIP
# locales con las URL oficiales del índice, sin GitHub). Detalle de fases y criterios en theme-parity/run.cjs.
# Uso: bash tests/playground/theme-parity.sh
#   SCENARIO=site|network   sitio individual (hijo típico) o red (1: Hello, 2: hijo típico, 3: hijo con PHP)
#   VIA=apariencia|migrar   cambio desde Apariencia (con vuelta atrás y reversión) o con «Migrar»
#   SITE_KIND=child|child-php  (SCENARIO=site) el sitio con el hijo típico o con el hijo con PHP
#   BREAK=1                 (con VIA=migrar) el tema propio pierde el CSS adicional: «Migrar» debe volver solo
#   OUT, PORT (9871), WORKERS (1 con Apariencia, 3 con Migrar), HELLO_ZIP, MAX_PIXELS (0.005), PLAYGROUND_CLI, PUPPETEER_PATH
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
. "$ROOT/tests/playground/playground-cleanup.sh"
D="$ROOT/tests/playground/theme-parity"
SCENARIO="${SCENARIO:-site}"; VIA="${VIA:-apariencia}"; BREAK="${BREAK:-0}"; SITE_KIND="${SITE_KIND:-child}"
case "$SITE_KIND" in child|child-php) ;; *) echo 'SITE_KIND debe ser child o child-php'; exit 1;; esac
case "$SCENARIO" in site|network) ;; *) echo 'SCENARIO debe ser site o network'; exit 1;; esac
case "$VIA" in apariencia|migrar) ;; *) echo 'VIA debe ser apariencia o migrar'; exit 1;; esac
BASE_OUT="${OUT:-/tmp/digi-theme-parity}/$SCENARIO$([ "$SITE_KIND" = child-php ] && echo -php || true)-$VIA$([ "$BREAK" = 1 ] && echo -break || true)"
OUT="$BASE_OUT/probe"; ZIPS="$BASE_OUT/zips"; STORE="$BASE_OUT/store"
PORT="${PORT:-9871}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PUPPETEER_PATH="${PUPPETEER_PATH:-$(ls -d "$HOME"/.npm/_npx/*/node_modules 2>/dev/null | while read -r d; do [ -d "$d/puppeteer-core" ] && echo "$d" && break; done || true)}"
if [ -z "$PUPPETEER_PATH" ]; then
  mkdir -p "${TMPDIR:-/tmp}/pptr"
  npm install --silent --no-audit --no-fund --prefix "${TMPDIR:-/tmp}/pptr" puppeteer-core@25.12.0 >/dev/null
  PUPPETEER_PATH="${TMPDIR:-/tmp}/pptr/node_modules"
fi
rm -rf "$BASE_OUT" && mkdir -p "$OUT" "$ZIPS" "$STORE"

HELLO_ZIP="${HELLO_ZIP:-$ROOT/reference/hello-elementor.3.5.1.zip}"
if [ ! -f "$HELLO_ZIP" ]; then
  main_root="$(dirname "$(git -C "$ROOT" rev-parse --path-format=absolute --git-common-dir 2>/dev/null || echo "$ROOT/.git")")"
  HELLO_ZIP="$main_root/reference/hello-elementor.3.5.1.zip"
fi
[ -f "$HELLO_ZIP" ] || HELLO_ZIP="$ROOT/.hello.zip"
[ -f "$HELLO_ZIP" ] || { echo 'Falta Hello Elementor 3.5.1 (HELLO_ZIP)'; exit 1; }
sha256sum "$HELLO_ZIP" | grep -q '^c166fdcb091a38897f4f3339f83645e9747234fbc961560027d5563e3efd6447' || { echo "$HELLO_ZIP no es Hello Elementor 3.5.1 oficial"; exit 1; }
python3 -c 'import sys, zipfile; zipfile.ZipFile(sys.argv[1]).extractall(sys.argv[2])' "$HELLO_ZIP" "$OUT"

# El tema sale de su paquete (con la guarda de build-packages.sh), nunca del repositorio montado.
bash "$ROOT/scripts/build-packages.sh" "$BASE_OUT/pkg" digitalisimo-theme > "$BASE_OUT/build.log" 2>&1 || { cat "$BASE_OUT/build.log"; exit 1; }
grep -q 'THEME PACKAGE CHECK: PASS' "$BASE_OUT/build.log" || { echo 'El paquete del tema no pasó su guarda:'; cat "$BASE_OUT/build.log"; exit 1; }
THEME_ZIP="$(ls "$BASE_OUT"/pkg/digitalisimo-theme-*.zip | head -1)"
mkdir -p "$BASE_OUT/themes" && python3 -c 'import sys, zipfile; zipfile.ZipFile(sys.argv[1]).extractall(sys.argv[2])' "$THEME_ZIP" "$BASE_OUT/themes"
cp -r "$D/child" "$D/child-php" "$OUT/"
cp "$D/setup.php" "$D/mu-probe.php" "$OUT/"
head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n' > "$OUT/token"
echo "$PORT" > "$OUT/port"
printf '{"scenario":"%s","via":"%s","site_kind":"%s","break":%s,"port":%s}\n' "$SCENARIO" "$VIA" "$SITE_KIND" "$([ "$BREAK" = 1 ] && echo true || echo false)" "$PORT" > "$OUT/conf.json"

MOUNTS=(--mount="$OUT:/wordpress/wp-content/probe")
if [ "$VIA" = migrar ]; then
  # Índice de la Release con las URL oficiales y ZIP locales de esta copia (Backups, Elements y el paquete del tema).
  cp "$THEME_ZIP" "$ZIPS/"
  python3 - "$ROOT" "$OUT" "$ZIPS" "$(basename "$THEME_ZIP")" <<'PY'
import json, os, re, sys, zipfile
root, out, zips, theme_zip = sys.argv[1:5]
def version(path):
    return re.search(r'^\s*\*?\s*Version:\s*(\S+)', open(path, encoding='utf-8').read(), re.M).group(1)
url = 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v2026.11.20.999/'
plugins = {}
for slug, main in (('digitalisimo-backups', 'digitalisimo-backups/digitalisimo-backups.php'), ('digitalisimo-elements', 'digitalisimo-elements/pro-elements.php'), ('digitalisimo-seo', 'digitalisimo-seo/digitalisimo-integrations.php'), ('digitalisimo-migration', 'digitalisimo-migration/digitalisimo-migration.php')):
    v = version(f'{root}/{main}')
    plugins[slug] = {'version': v, 'url': f'{url}{slug}-{v}.zip'}
    if slug in ('digitalisimo-backups', 'digitalisimo-elements'):
        with zipfile.ZipFile(f'{zips}/{slug}-{v}.zip', 'w', zipfile.ZIP_DEFLATED) as z:
            for base, dirs, files in os.walk(f'{root}/{slug}'):
                dirs[:] = [d for d in dirs if d not in ('node_modules', '.git')]
                for f in files:
                    full = os.path.join(base, f)
                    z.write(full, os.path.relpath(full, root))
tv = version(f'{root}/digitalisimo-theme/style.css')
json.dump({'tag': 'v2026.11.20.999', 'plugins': plugins, 'themes': {'digitalisimo-theme': {'version': tv, 'url': f'{url}{theme_zip}'}}}, open(f'{out}/manifest.json', 'w'))
PY
  MOUNTS+=(--mount="$ROOT/digitalisimo-migration:/wordpress/wp-content/plugins/digitalisimo-migration" --mount="$ROOT/digitalisimo-tools:/wordpress/wp-content/plugins/digitalisimo-tools" --mount="$ZIPS:/tp-zips" --mount="$STORE:/tp-store")
  CONSTS='"DIGITALISIMO_BACKUPS_STORAGE_DIR":"/tp-store","DISABLE_WP_CRON":true'
else
  MOUNTS+=(--mount="$ROOT/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements" --mount="$ROOT/digitalisimo-tools:/wordpress/wp-content/plugins/digitalisimo-tools" --mount="$BASE_OUT/themes/digitalisimo-theme:/wordpress/wp-content/themes/digitalisimo-theme")
  CONSTS='"DISABLE_WP_CRON":true'
fi
if [ "$SCENARIO" = network ]; then STEPS='{"step":"enableMultisite"},'; SITE='http://playground.test'; else STEPS=''; SITE="http://127.0.0.1:$PORT"; fi
cat > "$OUT/blueprint.json" <<JSON
{"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{$CONSTS}},${STEPS}{"step":"login"},{"step":"runPHP","code":"<?php require '/wordpress/wp-content/probe/setup.php';"}]}
JSON
fuser -k -n tcp "$PORT" >/dev/null 2>&1 || true
# Un solo worker con Apariencia: con varios, Playground sincroniza /wordpress entre ellos y la primera petición tras un
# cambio de tema (que vacía la caché de CSS del constructor) a veces no encuentra la carpeta de CSS. «Migrar» necesita 3
# (su verificación pide la portada al propio servidor).
WORKERS="${WORKERS:-$([ "$VIA" = migrar ] && echo 3 || echo 1)}"
$CLI server --port="$PORT" --workers="$WORKERS" --site-url="$SITE" --blueprint="$OUT/blueprint.json" "${MOUNTS[@]}" > "$BASE_OUT/server.log" 2>&1 &
SERVER=$!
stop_server() { kill $SERVER 2>/dev/null || true; for i in 1 2 3; do fuser -k -n tcp "$PORT" >/dev/null 2>&1 || break; sleep 1; done; }
trap 'stop_server; playground_cleanup' EXIT
for i in $(seq 1 200); do
  kill -0 $SERVER 2>/dev/null || { tail -20 "$BASE_OUT/server.log"; exit 1; }
  [ -f "$OUT/setup-error.txt" ] && break
  [ -f "$OUT/setup.done" ] && curl -s -m 60 -H "Host: ${SITE#http://}" "http://127.0.0.1:$PORT/?tp=ready&token=$(cat "$OUT/token")" | grep -q '"ready":1' && break
  sleep 3
done
[ -f "$OUT/setup-error.txt" ] && { echo 'Preparación incompleta:'; cat "$OUT/setup-error.txt"; exit 1; }
[ -f "$OUT/setup.done" ] || { echo 'La preparación no terminó'; tail -20 "$BASE_OUT/server.log"; exit 1; }
status=0
OUT="$OUT" NODE_PATH="$PUPPETEER_PATH" node "$D/run.cjs" || status=$?
[ -s "$OUT/fatal.log" ] && { echo 'Errores fatales de PHP:'; cat "$OUT/fatal.log"; status=1; }
[ -s "$OUT/php.jsonl" ] && { echo 'Avisos de PHP:'; sort -u "$OUT/php.jsonl" | head -20; }
# Lo pesado no vuelve con los resultados: ZIP, almacén, paquete, copias de los temas y (salvo KEEP_SHOTS=1) capturas.
rm -rf "$ZIPS" "$STORE" "$BASE_OUT/pkg" "$BASE_OUT/themes" "$OUT/hello-elementor"
[ "${KEEP_SHOTS:-0}" = 1 ] || rm -rf "$OUT/shots"
exit $status
