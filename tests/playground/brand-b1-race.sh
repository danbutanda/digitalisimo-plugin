#!/usr/bin/env bash
# B-1 de la revisión F2 de marca propia (docs/MARCA_PROPIA.md): con el paquete transformado y la marca APAGADA, una
# petición que encuentra los originales sin desplegar nunca debe servir el HTML original con los assets transformados.
# En Playground con varios workers:
#   A) se borran los originales desplegados (uploads/digitalisimo-elements/base-*) y se piden dos páginas A LA VEZ;
#   B) con el cerrojo de un despliegue que no termina (base-<versión>.lock) se pide otra página.
# Cada página se revisa: todos sus CSS y JS del plugin y de los originales responden 200, y el HTML y el CSS del núcleo
# hablan el mismo idioma (`elementor-` con `.elementor-` o `dg-` con `.dg-`). Sale 1 si alguna página está rota.
# Uso: bash tests/playground/brand-b1-race.sh   (ELEMENTS_DIR para otra copia de Elements, p. ej. la de antes)
#   OUT, TMPDIR, PORT (9655; bloque 9650–9659), PHP (8.2)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
D="$ROOT/tests/playground/brand-zero-trace"
export OUT="${OUT:-/var/tmp/digi-brand-b1-race}"
export TMPDIR="${TMPDIR:-$OUT/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PORT="${PORT:-9655}"
case "$PORT" in 965[0-9]) ;; *) echo "brand-b1-race.sh usa el bloque 9650–9659"; exit 1;; esac
SERVER=''
stop_server() { [ -n "$SERVER" ] && kill "$SERVER" 2>/dev/null || true; for i in 1 2 3; do fuser -k -n tcp "$PORT" >/dev/null 2>&1 || break; sleep 1; done; SERVER=''; }
trap 'stop_server; rm -rf "$OUT/src" "$OUT/uploads" "$OUT/probe/secrets.json"; playground_cleanup' EXIT
trap 'exit 130' INT TERM

rm -rf "$OUT/src" "$OUT/probe" "$OUT/uploads" "$OUT/paginas" && mkdir -p "$OUT/src" "$OUT/probe" "$OUT/uploads" "$OUT/paginas"
cp -a "${ELEMENTS_DIR:-$ROOT/digitalisimo-elements}" "$OUT/src/digitalisimo-elements"
rm -rf "$OUT/src/digitalisimo-elements/bdthemes-element-pack"
node "$ROOT/scripts/marca-empaquetar.mjs" "$OUT/src/digitalisimo-elements" > "$OUT/paquete.txt"
version=$(sed -n 's/^[ *]*Version:[ ]*\([^ ]*\).*/\1/p' "$OUT/src/digitalisimo-elements/pro-elements.php" | head -1)
cp "$D/setup.php" "$D/probe.php" "$OUT/probe/"
echo '{"marca":"off","proto":false,"paquete":true}' > "$OUT/probe/options.json"
php="${PHP:-8.2}"
cat > "$OUT/blueprint.json" <<JSON
{"preferredVersions":{"php":"$php","wp":"latest"},"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{"WP_ENVIRONMENT_TYPE":"local"}},{"step":"runPHP","code":"<?php require '/digi-probe/setup.php';"}]}
JSON
host="127.0.0.1:$PORT"
$CLI server --php="$php" --port="$PORT" --workers=4 --site-url="http://$host" --blueprint="$OUT/blueprint.json" \
  --mount="$OUT/src/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements" --mount="$OUT/uploads:/wordpress/wp-content/uploads" \
  --mount="$OUT/probe:/digi-probe" > "$OUT/server.log" 2>&1 &
SERVER=$!
ready=0
for i in $(seq 1 400); do
  sleep 3
  kill -0 "$SERVER" 2>/dev/null || break
  [ -f "$OUT/probe/setup-error.txt" ] && break
  if [ -s "$OUT/probe/setup.json" ] && [ "$(curl -s -o /dev/null -w '%{http_code}' -m 120 "http://$host/")" = 200 ]; then ready=1; break; fi
done
[ "$ready" = 1 ] || { echo "El sitio no quedó listo"; cat "$OUT/probe/setup-error.txt" 2>/dev/null || true; tail -20 "$OUT/server.log"; exit 1; }
up="$OUT/uploads/digitalisimo-elements"
echo "Sitio listo (Elements $version, marca apagada, paquete transformado). Originales: $(ls "$up" 2>/dev/null | tr '\n' ' ')"
# Calentar los workers (cada uno compila el código la primera vez).
warm=(); for i in 1 2 3 4; do curl -s -o /dev/null -m 120 "http://$host/?calentar=$i" & warm+=($!); done; wait "${warm[@]}"

# A) Dos peticiones a la vez sin originales.
rm -rf "$up"/base-*
curl -s -m 180 -o "$OUT/paginas/a1.html" "http://$host/?carrera=1" & p1=$!
curl -s -m 180 -o "$OUT/paginas/a2.html" "http://$host/?carrera=2" & p2=$!
wait $p1 $p2
echo "A) tras la carrera: $(ls "$up" 2>/dev/null | tr '\n' ' ')"
# B) Un despliegue que no termina (cerrojo reciente de otra petición).
rm -rf "$up"/base-*
mkdir -p "$up/base-$version.lock"
curl -s -m 180 -o "$OUT/paginas/b1.html" "http://$host/?cerrojo=1"
rmdir "$up/base-$version.lock"
curl -s -m 180 -o "$OUT/paginas/c1.html" "http://$host/?despues=1"
echo "C) después: $(ls "$up" 2>/dev/null | tr '\n' ' ')"
status=0
node "$ROOT/tests/playground/brand-b1-race/check.cjs" "http://$host" "$OUT/paginas" a1 a2 b1 c1 | tee "$OUT/result.txt" || status=1
[ -s "$OUT/probe/fatal.log" ] && { echo "Errores fatales:"; cat "$OUT/probe/fatal.log"; status=1; }
exit $status
