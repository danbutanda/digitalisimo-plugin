#!/usr/bin/env bash
# P10 de f8.sh (delegado): A/B con Element Pack Lite 10.0.1 oficial (ep-lite-pin.sh: sha256 contra OFFICIAL-SOURCES.txt),
# con legacy-ab.sh (fases A–E y la firma de estilos computados de los mapas corregidos) y migrate.sh («Migrar toda la
# red»), en el puerto 9510 (lo comparte P0: cada corrida toma el cerrojo /var/tmp/digi-f8/pg/port-9510.lock).
#
# Una pasada completa no cabe en 10 min: se parte en tandas que se guardan en $OUT_F8/P10-parts/<tanda>/ (fuera de $RUN,
# que f8.sh vacía) y f8/p10.cjs las suma. Cada llamada corre la primera tanda que falta (o P10_PART):
#   w1, w2   legacy-ab.sh con los widgets de Lite que tienen casos (dos mitades; w2 lleva también lightbox)
#   ext      legacy-ab.sh con las extensiones de Lite que tienen casos (las 8 que se convierten, cursor-effects y las
#            que ya tenían adaptador)
#   mg-base  migrate.sh con las páginas de antes de F8 (acordeón, botón y los alias)
#   mg-lite  migrate.sh con Trailer Box, Animated Card, Interactive Card y las 8 extensiones
# P10_RESET=1 borra las tandas guardadas antes de empezar.
#
# Variables que pasa f8.sh: ROOT, RUN, PORT, SRC, REF_DIR, OUT_F8, PUPPETEER_PATH.
set -uo pipefail
PARTS="$OUT_F8/P10-parts"
[ "${P10_RESET:-0}" = 1 ] && rm -rf "$PARTS"
mkdir -p "$PARTS" "$RUN"
ALL_PARTS="w1 w2 ext mg-base mg-lite"
part="${P10_PART:-}"
if [ -z "$part" ]; then
  for p in $ALL_PARTS; do [ -f "$PARTS/$p/done.json" ] || { part="$p"; break; }; done
fi
[ -n "$part" ] || { echo "P10: las cinco tandas ya están hechas (P10_RESET=1 para repetir)"; exit 0; }
[[ " $ALL_PARTS " == *" $part "* ]] || { echo "P10_PART desconocida: $part ($ALL_PARTS)"; exit 1; }
[ "$PORT" = 9510 ] || { echo "P10 usa el puerto 9510"; exit 1; }
. "$ROOT/tests/playground/ep-lite-pin.sh"
ep_pin "$PARTS/ep.json" || exit 1

# Copia propia de la copia fija de f8.sh: Element Pack escribe figuras en la carpeta del núcleo montada.
rm -rf "$RUN/src" && mkdir -p "$RUN/src"
cp -a "$SRC/digitalisimo-elements" "$SRC/digitalisimo-migration" "$RUN/src/"

# Casos de lo que Lite registra: widgets `bdt-*` con fixture (y lightbox) y extensiones con fixture y módulo.
widgets=()
while read -r w; do [ -f "$ROOT/tests/legacy/$w.json" ] && widgets+=("$w"); done < "$PARTS/ep-widgets.txt"
half=$(( (${#widgets[@]} + 1) / 2 ))
exts=()
for f in "$ROOT"/tests/legacy/ext-*.json; do
  slug="$(basename "$f" .json)"; slug="${slug#ext-}"
  grep -qx "$slug" "$PARTS/ep-modules.txt" && exts+=("ext-$slug")
done
case "$part" in
  w1) only="$(IFS=,; echo "${widgets[*]:0:$half}")";;
  w2) only="$(IFS=,; echo "${widgets[*]:$half}")"; grep -qx lightbox "$PARTS/ep-modules.txt" && only="$only,lightbox";;
  ext) only="$(IFS=,; echo "${exts[*]}")";;
esac

dest="$PARTS/$part"
rm -rf "$dest" && mkdir -p "$dest"
echo "P10 · tanda $part · $(date -Iseconds)"
t0="$(date +%s)"
lock=/var/tmp/digi-f8/pg/port-9510.lock
case "$part" in
  w1|w2|ext)
    echo "$only" > "$dest/only.txt"
    # FAKE_PLUGINS=1: los widgets de formularios de Lite sólo se registran con su plugin activo (plugins falsos de
    # tests/playground/fake-plugins/, que sólo registran el shortcode).
    flock -n "$lock" env OUT="$dest" PORT="$PORT" CORE=bundled FAKE_PLUGINS=1 ELEMENTS_DIR="$RUN/src/digitalisimo-elements" MIGRATION_DIR="$RUN/src/digitalisimo-migration" \
      PUPPETEER_PATH="$PUPPETEER_PATH" bash "$ROOT/tests/playground/legacy-ab.sh" "$only" > "$dest/out.log" 2>&1
    rc=$?;;
  mg-base|mg-lite)
    flock -n "$lock" env OUT="$dest" PORT="$PORT" MIGRATE_SET="${part#mg-}" ELEMENTS_DIR="$RUN/src/digitalisimo-elements" MIGRATION_DIR="$RUN/src/digitalisimo-migration" \
      PUPPETEER_PATH="$PUPPETEER_PATH" bash "$ROOT/tests/playground/migrate.sh" > "$dest/out.log" 2>&1
    rc=$?;;
esac
# flock -n sale con 1 sin ejecutar nada si el puerto lo tiene otra corrida: la tanda queda sin hacer.
if [ "$rc" != 0 ] && ! grep -q . "$dest/out.log"; then echo "P10: el puerto 9510 está tomado (cerrojo); la tanda $part no corrió"; rm -rf "$dest"; exit 1; fi
node -e '
const [file, part, rc, t0, only] = process.argv.slice(1);
require("fs").writeFileSync(file, JSON.stringify({ part, exit: Number(rc), duration_s: Math.round(Date.now() / 1000 - Number(t0)), finished: new Date().toISOString(), only: only ? only.split(",") : [] }, null, 1));
' "$dest/done.json" "$part" "$rc" "$t0" "${only:-}"
tail -5 "$dest/out.log"
echo "P10 · tanda $part terminada con $rc en $(( $(date +%s) - t0 )) s"
exit 0
