#!/usr/bin/env bash
# Tandas B y C de la auditoría SEO del 2026-11-24 (digitalisimo.lat) en un WordPress real, sin Chrome:
#   B1 la miga «[descripcion_sitio] [contenido_pilar]», una variable que no existe en un título SEO y un shortcode en el
#      nombre de una categoría salen como error en SEO → Auditoría → Análisis del sitio, en el aviso y en el MCP;
#   B2 /paginas-web/, /paginas-web-torreon/ y /diseno-de-paginas-web-en-torreon/ (casi iguales) forman un grupo; se
#      propone conservar la de más enlaces; el 301 sólo se aplica tras confirmar, con respaldo, y se deshace;
#   B3 huérfanas: no salen las enlazadas desde un menú o desde la plantilla del pie, ni la portada; noindex en lote;
#   B4 /que-es-una-pagina-web/ con canonical a un dominio que no existe: aviso de otro dominio, pendiente en la primera
#      comprobación y error confirmado en la segunda; las peticiones van por el cron, nunca al servir una página;
#   B5 el interruptor único encendido se migra a las cuatro partes; con una cerrada, un cliente externo no cambia nada;
#      en una red, el sitio que hereda sigue a la red;
#   C1 /black-friday/ con la descripción para redes de El Buen Fin (un plazo que ya pasó) sale como desactualizada.
#   Y lo que no debe cambiar: sin el análisis, ningún front cambia (ni redirecciones ni noindex).
# Uso: bash tests/playground/seo-analysis.sh   → $OUT/result.jsonl (por defecto /var/tmp/digi-seo-analysis)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
export TMPDIR="${TMPDIR:-/tmp}"
. "$ROOT/tests/playground/playground-cleanup.sh"
trap playground_cleanup EXIT
OUT="${OUT:-/var/tmp/digi-seo-analysis}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
STARTED="$(date +%s)"
rm -rf "$OUT" && mkdir -p "$OUT"
cp "$ROOT/tests/playground/seo-analysis/probe.php" "$OUT/"

run_blueprint() { # $1 archivo, $2 multisite (1/0), resto: pasos
  local file="$1" multi="$2"; shift 2
  local json='{"steps":['
  [ "$multi" = 1 ] && json="$json"'{"step":"enableMultisite"},'
  local first=1 step
  for step in "$@"; do
    [ $first = 1 ] || json="$json,"
    first=0
    json="$json{\"step\":\"runPHP\",\"code\":\"<?php putenv('DIGI_STEP=$step'); require '/wordpress/wp-content/probe/probe.php';\"}"
  done
  echo "$json]}" > "$OUT/$file"
  $CLI run-blueprint --site-url=http://playground.test --blueprint="$OUT/$file" \
    --mount="$ROOT/digitalisimo-seo:/wordpress/wp-content/plugins/digitalisimo-seo" \
    --mount="$OUT:/wordpress/wp-content/probe" > "$OUT/${file%.json}.log" 2>&1 || { tail -30 "$OUT/${file%.json}.log"; [ -s "$OUT/fatal.log" ] && cat "$OUT/fatal.log"; exit 1; }
}

run_blueprint blueprint-single.json 0 setup seed baseline analyze results canonical canonical-confirm render cluster undo orphans abilities
run_blueprint blueprint-network.json 1 setup network-abilities
status=0
fails=$(grep -c '"ok":false' "$OUT/result.jsonl" || true)
total=$(wc -l < "$OUT/result.jsonl")
grep '"ok":false' "$OUT/result.jsonl" || true
if [ -s "$OUT/fatal.log" ]; then echo "Avisos o errores de PHP:"; cat "$OUT/fatal.log"; status=1; fi
[ "$fails" = 0 ] || status=1
[ "$total" -ge 50 ] || { echo "Muy pocas comprobaciones ($total): algún paso no llegó al final."; status=1; }
echo "Comprobaciones: $total, fallidas: $fails · Duración: $(( $(date +%s) - STARTED )) s"
exit $status
