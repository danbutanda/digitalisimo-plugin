#!/usr/bin/env bash
# Bucles propios de C5 (frente J, docs/pro/c5.md) en WordPress real con el constructor incluido: Cuadrícula de bucle,
# Carrusel de bucle y Filtro de taxonomías. Prepara 9 entradas y dos plantillas de Elemento de bucle
# (pro-loop/setup.php) y comprueba (pro-loop/check.php) el registro, que cada control de los widgets de PRO (también los
# de su base, de Entradas, del rasgo de carrusel y de cada piel) existe en el propio con el mismo tipo, valor por
# defecto, condiciones, dispositivos y sección, el marcado del servidor (rejilla, plantillas alternas, paginación,
# «Cargar más», sin resultados, términos, carrusel, filtro y filtro por la URL) y la conversión con los mapas.
#
# Uso: bash tests/playground/pro-loop.sh   (OUT=/var/tmp/digi-pro-loop por defecto). Sale con 1 si hay alguna FALLA.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
export TMPDIR="${TMPDIR:-/var/tmp}"
. "$ROOT/tests/playground/playground-cleanup.sh"
OUT="${OUT:-/var/tmp/digi-pro-loop}"
case "$OUT" in /tmp/*) echo "OUT no puede estar en /tmp (tmpfs): usar /var/tmp"; exit 1;; esac
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
rm -rf "$OUT" && mkdir -p "$OUT/probe"
cp "$ROOT/tests/playground/pro-loop/"*.php "$ROOT/tests/playground/pro-ab/activate.php" "$OUT/probe/"
cat > "$OUT/blueprint.json" <<'JSON'
{"steps":[
 {"step":"runPHP","code":"<?php require '/wordpress/wp-content/probe/activate.php';"},
 {"step":"runPHP","code":"<?php require '/wordpress/wp-content/probe/setup.php';"},
 {"step":"runPHP","code":"<?php require '/wordpress/wp-content/probe/check.php';"}
]}
JSON
trap 'playground_cleanup' EXIT
$CLI run-blueprint --blueprint="$OUT/blueprint.json" \
  --mount="$ROOT/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements" \
  --mount="$OUT/probe:/wordpress/wp-content/probe" > "$OUT/cli.log" 2>&1 || { tail -20 "$OUT/cli.log"; exit 1; }
[ -f "$OUT/probe/setup-error.txt" ] && { echo 'Preparación incompleta:'; cat "$OUT/probe/setup-error.txt"; exit 1; }
[ -s "$OUT/probe/fatal.log" ] && { echo 'Errores fatales de PHP:'; cat "$OUT/probe/fatal.log"; exit 1; }
[ -f "$OUT/probe/results.txt" ] || { echo 'check.php no dejó resultados'; tail -20 "$OUT/cli.log"; exit 1; }
cat "$OUT/probe/results.txt"
if grep -q '^FALLA' "$OUT/probe/results.txt"; then echo "pro-loop: $(grep -c '^FALLA' "$OUT/probe/results.txt") fallas"; exit 1; fi
echo "pro-loop: en verde ($(grep -c '^OK' "$OUT/probe/results.txt") comprobaciones)"
