#!/usr/bin/env bash
# P5 · presupuesto del front (f8.sh, escenario delegado en front-budget.sh, puerto 9515). Lo lanza f8.sh con RUN, PORT,
# SRC (copia con Elements 4.3.0.170), OUT_F8 y PUPPETEER_PATH; deja cada parte en $OUT_F8/P5-data/<parte>/ (fuera de
# $RUN, que f8.sh vacía en cada tanda) y f8/p5.cjs la convierte en comprobaciones.
#
# Partes (P5_PARTS, separadas por comas; por defecto, las que aún no tienen resultado, en este orden). Cada una cabe en
# una llamada de 10 min; la corrida completa no: se parte en tandas con APPEND=1 de f8.sh.
#   ref-A, ref-B         4.3.0.169 publicado (ZIP de la Release, sha256 comprobada por f8.sh/P8 o aquí) sin `expect`,
#                        mitades A y B de los casos del modo normal (cada una con `empty`)
#   tree-A, tree-B       la copia de f8.sh con COMPARE_FILE de su mitad (en B, COMPARE_STRICT es `empty`)
#   tree-f8              `empty` y los cuatro casos de F8 (f8-tooltip, f8-badge, f8-nested-counter y f8-marker)
#   ref-cache-A, ref-cache-B, tree-cache-A, tree-cache-B   CACHE=1: los casos del modo con caché frente a 4.3.0.169,
#                        en dos mitades (cada una con `empty`)
#   tree-cache-f8        CACHE=1: `empty` y los casos de F8
#   tree-migration       MIGRATION=1: f8-marker con DIGITALÍSIMO Migración activo (el front nunca convierte el marcador)
set -euo pipefail
: "${ROOT:?}" "${PORT:?}" "${SRC:?}" "${OUT_F8:?}"
DATA="$OUT_F8/P5-data"
mkdir -p "$DATA"
REL_ZIP="${REL169_ZIP:-/var/tmp/digi-f8/pg/rel/digitalisimo-elements-4.3.0.169.zip}"
REL_DIR="${REL169_DIR:-/var/tmp/digi-f8/pg/rel169}"
REL_SHAS="29221de3fdb83a1844e1a039798de84f6296b5b258da5d8ee7beab45c4d5c396 526d2b55dadbcb518250f957a29669d320c573cf17910fff07d5365d34c2b52c"
if [ ! -f "$REL_DIR/digitalisimo-elements/pro-elements.php" ]; then
  sum="$(sha256sum "$REL_ZIP" | cut -d' ' -f1)"
  [[ " $REL_SHAS " == *" $sum "* ]] || { echo "$REL_ZIP no es el ZIP publicado de 4.3.0.169 (sha256 $sum)"; exit 1; }
  rm -rf "$REL_DIR" && mkdir -p "$REL_DIR" && unzip -q "$REL_ZIP" -d "$REL_DIR"
fi
grep -q "Version: 4.3.0.169" "$REL_DIR/digitalisimo-elements/pro-elements.php" || { echo "La referencia no es 4.3.0.169"; exit 1; }

# Mitades del modo normal (las de F7b: A hasta all-own; B el resto), cada una con `empty`.
read -r HALF_A HALF_B CACHE_A CACHE_B <<< "$(node -e '
const c = require(process.argv[1]);
const runs = (k, m) => !c[k].pending && (m === "normal" ? (!Array.isArray(c[k].modes) || c[k].modes.includes("normal")) : Array.isArray(c[k].modes) && c[k].modes.includes(m));
const f8 = (k) => k.startsWith("f8-");
const normal = Object.keys(c).filter((k) => runs(k, "normal") && !f8(k));
const cut = normal.indexOf("all-own") + 1;
const a = normal.slice(0, cut);
const b = ["empty", ...normal.slice(cut).filter((k) => k !== "empty")];
const cache = Object.keys(c).filter((k) => runs(k, "cache") && !f8(k) && k !== "empty");
const half = Math.ceil(cache.length / 2);
console.log([a.join(","), b.join(","), ["empty", ...cache.slice(0, half)].join(","), ["empty", ...cache.slice(half)].join(",")].join(" "));
' "$ROOT/tests/playground/front-budget/cases.json")"
F8_CASES="f8-tooltip,f8-badge,f8-nested-counter,f8-marker"
PARTS="${P5_PARTS:-}"
if [ -z "$PARTS" ]; then
  for part in ref-A ref-B tree-A tree-B tree-f8 ref-cache-A ref-cache-B tree-cache-A tree-cache-B tree-cache-f8 tree-migration; do
    [ -s "$DATA/$part/done.json" ] || { PARTS="$part"; break; }
  done
fi
[ -n "$PARTS" ] || { echo "P5: todas las partes tienen resultado (P5_PARTS=… para repetir una)"; exit 0; }

run_part() {
  local part="$1" out="$DATA/$1" file status=0 t0
  t0="$(date +%s)"
  rm -rf "$out" && mkdir -p "$out"
  local env=(OUT="$out" PORT="$PORT" TMPDIR="/var/tmp/digi-f8/pg/tmp" PUPPETEER_PATH="$PUPPETEER_PATH")
  case "$part" in
    ref-A)  env+=(EXPECT=0 ELEMENTS_DIR="$REL_DIR/digitalisimo-elements" CASES="$HALF_A");;
    ref-B)  env+=(EXPECT=0 ELEMENTS_DIR="$REL_DIR/digitalisimo-elements" CASES="$HALF_B");;
    tree-A) env+=(ELEMENTS_DIR="$SRC/digitalisimo-elements" CASES="$HALF_A" COMPARE_FILE="$DATA/ref-A/front-budget.json");;
    tree-B) env+=(ELEMENTS_DIR="$SRC/digitalisimo-elements" CASES="$HALF_B" COMPARE_FILE="$DATA/ref-B/front-budget.json" COMPARE_STRICT=empty);;
    tree-f8) env+=(ELEMENTS_DIR="$SRC/digitalisimo-elements" CASES="empty,$F8_CASES");;
    ref-cache-A)  env+=(CACHE=1 EXPECT=0 ELEMENTS_DIR="$REL_DIR/digitalisimo-elements" CASES="$CACHE_A");;
    ref-cache-B)  env+=(CACHE=1 EXPECT=0 ELEMENTS_DIR="$REL_DIR/digitalisimo-elements" CASES="$CACHE_B");;
    tree-cache-A) env+=(CACHE=1 ELEMENTS_DIR="$SRC/digitalisimo-elements" CASES="$CACHE_A" COMPARE_FILE="$DATA/ref-cache-A/front-budget-cache.json" COMPARE_STRICT=empty);;
    tree-cache-B) env+=(CACHE=1 ELEMENTS_DIR="$SRC/digitalisimo-elements" CASES="$CACHE_B" COMPARE_FILE="$DATA/ref-cache-B/front-budget-cache.json" COMPARE_STRICT=empty);;
    tree-cache-f8) env+=(CACHE=1 ELEMENTS_DIR="$SRC/digitalisimo-elements" CASES="empty,$F8_CASES");;
    tree-migration) env+=(MIGRATION=1 ELEMENTS_DIR="$SRC/digitalisimo-elements" CASES="empty,f8-marker");;
    *) echo "Parte desconocida: $part"; return 1;;
  esac
  case "$part" in tree-A|tree-B|tree-cache-A|tree-cache-B)
    local ref; ref="$(printf '%s\n' "${env[@]}" | sed -n 's/^COMPARE_FILE=//p')"
    [ -s "$ref" ] || { echo "P5 $part: falta la referencia $ref (correr antes su parte ref-)"; return 1; };;
  esac
  echo "P5 · $part · $(date -u +%H:%M:%S)"
  env "${env[@]}" bash "$ROOT/tests/playground/front-budget.sh" > "$out/front-budget.log" 2>&1 || status=$?
  file="$(ls "$out"/front-budget*.json 2>/dev/null | head -1 || true)"
  node -e '
const [out, part, status, t0, file] = process.argv.slice(1);
require("fs").writeFileSync(out + "/done.json", JSON.stringify({ part, status: Number(status), file, seconds: Math.round(Date.now() / 1000 - Number(t0)) }, null, 1));
' "$out" "$part" "$status" "$t0" "$file"
  tail -4 "$out/front-budget.log"
  # La copia montada de la corrida ya no hace falta (el disco es escaso); las páginas sí: las compara la parte tree-.
  rm -rf "$out/src" "$out/woocommerce" "$out/fake-addon"
  return 0
}
IFS=',' read -r -a LIST <<< "$PARTS"
for part in "${LIST[@]}"; do run_part "$part"; done
