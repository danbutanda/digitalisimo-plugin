#!/usr/bin/env bash
# Marca propia (docs/MARCA_PROPIA.md), PROTOTIPO de la fase 0 (D-9): el ZIP de DIGITALÍSIMO Elements con el motor en una
# carpeta neutra, sin tocar `digitalisimo-elements/elementor/` del repositorio.
#   - copia el plugin a una carpeta temporal y renombra `elementor/` a `motor/` (digitalisimo-core.php la encuentra sola);
#   - quita `motor/readme.txt` (D-8);
#   - genera el espejo de assets en `marca/` (scripts/marca-espejo.mjs --motor motor);
#   - arma el ZIP normal y el de prototipo con `zip -9` y compara pesos y nombres de archivo con «elementor».
# Uso: bash scripts/marca-prototipo/zip-motor.sh <carpeta-de-salida>
# Deja <salida>/digitalisimo-elements-normal.zip, <salida>/digitalisimo-elements-motor.zip y <salida>/zip-motor.json.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
OUT="${1:?uso: zip-motor.sh <carpeta-de-salida>}"
mkdir -p "$OUT"; OUT="$(cd "$OUT" && pwd)"
STAGE="$OUT/stage"
rm -rf "$STAGE" "$OUT"/digitalisimo-elements-*.zip && mkdir -p "$STAGE"
EXCL=(-x 'digitalisimo-elements/bdthemes-element-pack/*' '*/.DS_Store')
( cd "$ROOT" && nice zip -9 -rq "$OUT/digitalisimo-elements-normal.zip" digitalisimo-elements "${EXCL[@]}" )
cp -a "$ROOT/digitalisimo-elements" "$STAGE/digitalisimo-elements"
rm -rf "$STAGE/digitalisimo-elements/bdthemes-element-pack"
mv "$STAGE/digitalisimo-elements/elementor" "$STAGE/digitalisimo-elements/motor"
rm -f "$STAGE/digitalisimo-elements/motor/readme.txt"
nice node "$ROOT/scripts/marca-espejo.mjs" --out "$STAGE/digitalisimo-elements/marca" --motor motor > "$OUT/espejo.txt"
( cd "$STAGE" && nice zip -9 -rq "$OUT/digitalisimo-elements-motor.zip" digitalisimo-elements "${EXCL[@]}" )
unzip -tq "$OUT/digitalisimo-elements-motor.zip" > /dev/null
node -e '
const { execSync } = require("child_process");
const [out] = process.argv.slice(1);
const list = (z) => execSync(`unzip -Z1 ${z}`, { maxBuffer: 1 << 28 }).toString().trim().split("\n");
const size = (z) => require("fs").statSync(z).size;
const normal = list(out + "/digitalisimo-elements-normal.zip"), motor = list(out + "/digitalisimo-elements-motor.zip");
const named = motor.filter((f) => /elementor/i.test(f) && !f.endsWith("/"));
const groups = {};
for (const f of named) { const g = f.split("/").slice(0, 3).join("/"); groups[g] = (groups[g] || 0) + 1; }
const r = { normal: { archivos: normal.filter((f) => !f.endsWith("/")).length, bytes: size(out + "/digitalisimo-elements-normal.zip"), carpeta_elementor: normal.some((f) => f.startsWith("digitalisimo-elements/elementor/")) },
  motor: { archivos: motor.filter((f) => !f.endsWith("/")).length, bytes: size(out + "/digitalisimo-elements-motor.zip"), carpeta_elementor: motor.some((f) => f.startsWith("digitalisimo-elements/elementor/")), readme: motor.includes("digitalisimo-elements/motor/readme.txt"), nombres_con_elementor: named.length, por_carpeta: Object.fromEntries(Object.entries(groups).sort((a, b) => b[1] - a[1]).slice(0, 15)) },
  espejo: require("fs").readFileSync(out + "/espejo.txt", "utf8").trim() };
r.diferencia_mb = +((r.motor.bytes - r.normal.bytes) / 1048576).toFixed(2);
require("fs").writeFileSync(out + "/zip-motor.json", JSON.stringify(r, null, 1));
console.log(JSON.stringify(r, null, 1));
' "$OUT"
rm -rf "$STAGE"
