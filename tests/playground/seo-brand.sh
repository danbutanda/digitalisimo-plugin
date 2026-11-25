#!/usr/bin/env bash
# Marca, schema y plantillas de DIGITALÍSIMO SEO (auditoría SEO del 2026-11-24, puntos A1–A3) en un WordPress real, sin
# Chrome, en un sitio individual y en un sitio de una red que hereda de la red:
#   A1 la imagen social en un dominio que no existe y un logo de demo: antes de la comprobación el front no hace
#      peticiones; la comprobación corre en el cron y después og:image, twitter:image y el logo e image del schema usan
#      el logo del tema; el aviso sale en el Escritorio y en get-site-status del MCP;
#   A2 nombres sin emoji en el schema y og:site_name, sin teléfono ni horario inventados, sameAs con Google Business;
#   Con Rank Math activo (instalado de wordpress.org): ni comprobación, ni peticiones, ni avisos, ni brand_warnings.
#   A3 una plantilla «%focuskw% %sep% %excerpt%» no deja «|» y genera la descripción del contenido (≤ 155); el título
#      y la descripción escritos a mano se respetan; el texto fijo de la plantilla de títulos se avisa.
# Uso: bash tests/playground/seo-brand.sh   → $OUT/result.jsonl (por defecto /var/tmp/digi-seo-brand)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
export TMPDIR="${TMPDIR:-/tmp}"
. "$ROOT/tests/playground/playground-cleanup.sh"
trap playground_cleanup EXIT
OUT="${OUT:-/var/tmp/digi-seo-brand}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
STARTED="$(date +%s)"
rm -rf "$OUT" && mkdir -p "$OUT"
cp "$ROOT/tests/playground/seo-brand/probe.php" "$OUT/"

run_blueprint() { # $1 archivo, $2 multisite (1/0), resto: pasos «paso:blog»
  local file="$1" multi="$2"; shift 2
  local json='{"steps":['
  [ "$multi" = 1 ] && json="$json"'{"step":"enableMultisite"},'
  local first=1 item step blog
  for item in "$@"; do
    step="${item%%:*}"; blog="${item##*:}"
    [ $first = 1 ] || json="$json,"
    first=0
    if [ "$step" = install-rankmath ]; then
      # Rank Math de verdad, de wordpress.org (la versión instalada queda en $OUT/rankmath-version.txt).
      json="$json{\"step\":\"installPlugin\",\"pluginData\":{\"resource\":\"wordpress.org/plugins\",\"slug\":\"seo-by-rank-math\"},\"options\":{\"activate\":true}}"
      continue
    fi
    json="$json{\"step\":\"runPHP\",\"code\":\"<?php putenv('DIGI_STEP=$step'); putenv('DIGI_BLOG=$blog'); require '/wordpress/wp-content/probe/probe.php';\"}"
  done
  echo "$json]}" > "$OUT/$file"
  $CLI run-blueprint --site-url=http://playground.test --blueprint="$OUT/$file" \
    --mount="$ROOT/digitalisimo-seo:/wordpress/wp-content/plugins/digitalisimo-seo" \
    --mount="$OUT:/wordpress/wp-content/probe" > "$OUT/${file%.json}.log" 2>&1 || { tail -30 "$OUT/${file%.json}.log"; [ -s "$OUT/fatal.log" ] && cat "$OUT/fatal.log"; exit 1; }
}

run_blueprint blueprint-single.json 0 setup:1 seed:1 before:1 cron:1 after:1 notice:1 manual:1 clean:1
run_blueprint blueprint-network.json 1 setup:1 network-seed:1 seed:2 before:2 cron:2 after:2 notice:2
# Con Rank Math activo de verdad: DIGITALÍSIMO SEO no comprueba ni avisa sobre una salida que no publica.
run_blueprint blueprint-rankmath.json 0 setup:1 seed:1 install-rankmath:1 rankmath:1
[ -s "$OUT/rankmath-version.txt" ] && echo "Rank Math $(cat "$OUT/rankmath-version.txt") (wordpress.org)"
status=0
fails=$(grep -c '"ok":false' "$OUT/result.jsonl" || true)
total=$(wc -l < "$OUT/result.jsonl")
grep '"ok":false' "$OUT/result.jsonl" || true
if [ -s "$OUT/fatal.log" ]; then echo "Avisos o errores de PHP:"; cat "$OUT/fatal.log"; status=1; fi
[ "$fails" = 0 ] || status=1
[ "$total" -ge 70 ] || { echo "Muy pocas comprobaciones: $total"; status=1; }
echo "Comprobaciones: $total, fallidas: $fails · Duración: $(( $(date +%s) - STARTED )) s"
exit $status
