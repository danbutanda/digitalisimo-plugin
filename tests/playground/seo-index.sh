#!/usr/bin/env bash
# A4 y A5 de la auditoría SEO del 2026-11-24 en un WordPress real, sin Chrome:
#   - una página renombrada por la REST (como la 14601: /buen-fin/ → /black-friday/) en un sitio que importó de Rank
#     Math con el vigilante apagado: tras la migración la URL vieja responde 301 a la nueva;
#   - cambios de página padre (subpáginas y nietas), cadenas A→B→C en un salto, A→B→A sin bucle y un contenido nuevo
#     que ocupa una dirección vieja;
#   - el monitor de 404 anota sin tocar la base, vuelca agregado y sugiere destino;
#   - shortcodes sin resolver: sin falsos positivos (Elementor con etiquetas dinámicas, formularios, texto entre
#     corchetes), sólo aviso de fábrica, noindex con el ajuste, «Mantener indexable» y el tope de seguridad.
# Uso: bash tests/playground/seo-index.sh   → $OUT/result.jsonl (por defecto /var/tmp/digi-seo-index)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
export TMPDIR="${TMPDIR:-/tmp}"
. "$ROOT/tests/playground/playground-cleanup.sh"
trap playground_cleanup EXIT
OUT="${OUT:-/var/tmp/digi-seo-index}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
STARTED="$(date +%s)"
rm -rf "$OUT" && mkdir -p "$OUT"
cp "$ROOT/tests/playground/seo-index/probe.php" "$OUT/"

steps='{"steps":['
first=1
for step in setup seed migrate rename parent chains front monitor hygiene hygiene-scan hygiene-front hygiene-cap; do
  [ $first = 1 ] || steps="$steps,"
  first=0
  steps="$steps{\"step\":\"runPHP\",\"code\":\"<?php putenv('DIGI_STEP=$step'); require '/wordpress/wp-content/probe/probe.php';\"}"
done
echo "$steps]}" > "$OUT/blueprint.json"
$CLI run-blueprint --site-url=http://playground.test --blueprint="$OUT/blueprint.json" \
  --mount="$ROOT/digitalisimo-seo:/wordpress/wp-content/plugins/digitalisimo-seo" \
  --mount="$OUT:/wordpress/wp-content/probe" > "$OUT/blueprint.log" 2>&1 || { tail -30 "$OUT/blueprint.log"; [ -s "$OUT/fatal.log" ] && cat "$OUT/fatal.log"; exit 1; }
status=0
touch "$OUT/result.jsonl"
fails=$(grep -c '"ok":false' "$OUT/result.jsonl" || true)
total=$(wc -l < "$OUT/result.jsonl")
grep '"ok":false' "$OUT/result.jsonl" || true
if [ -s "$OUT/fatal.log" ]; then echo "Avisos o errores de PHP:"; cat "$OUT/fatal.log"; status=1; fi
[ "$fails" = 0 ] || status=1
[ "$total" -ge 30 ] || { echo "Muy pocas comprobaciones ($total): algún paso no llegó al final."; status=1; }
echo "Comprobaciones: $total, fallidas: $fails · Duración: $(( $(date +%s) - STARTED )) s"
exit $status
