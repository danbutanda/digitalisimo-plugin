#!/usr/bin/env bash
# Actualizaciones del tema DIGITALÍSIMO en una red Multisite (Playground, run-blueprint, sin Chrome): dos copias del
# tema (digitalisimo-theme y digitalisimo-theme-1.0.0, ésta activa en un subsitio) y DIGITALÍSIMO Tools activo en la
# red, con un índice de Releases falso que ofrece la versión siguiente del tema. Comprueba N1 (Tools cubre las copias
# no cargadas; también desde el subsitio, RL-12), N2 (sección «Tema» de la red y del subsitio, wp_die con contexto o
# nonce ajenos), N3 (Actualizar desde Tools conserva carpeta, tema activo, mods y allowedthemes, con .maintenance),
# N3f (move() fallido: WP_Error y nada cambia), N4 (actualización automática sólo con auto_update_themes y nunca con
# requisitos incompatibles), N5 (ficha de detalles sin avisos ni URL de origen) y N6 (el tema activo manda; con su
# actualizador roto, Tools escribe la entrada de respaldo).
# Nada del repositorio se monta: Tools y el tema salen de scripts/build-packages.sh y se montan (o copian) desde $OUT.
# Uso: bash tests/playground/theme-network.sh   → resultados en $OUT (por defecto /tmp/digi-theme-network)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
. "$ROOT/tests/playground/playground-cleanup.sh"
trap playground_cleanup EXIT
OUT="${OUT:-/tmp/digi-theme-network}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
N="$ROOT/tests/playground/theme-network"
rm -rf "$OUT" && mkdir -p "$OUT/pkg" "$OUT/plugins" "$OUT/themes" "$OUT/next"
# Playground copia el sitio en $TMPDIR (unos 400 MB) y Chrome guarda allí su perfil: con /tmp en tmpfs casi lleno las
# escrituras fallan con ENOSPC/EDQUOT («Unknown system error -122», «errno=28») y la corrida da falsos fallos.
for dir in "${TMPDIR:-/tmp}" "$OUT"; do
  free_kb="$(df -Pk "$dir" 2>/dev/null | awk 'NR == 2 { print $4 }')"
  [ -z "$free_kb" ] || [ "$free_kb" -ge 2097152 ] || echo "Aviso: $dir tiene $((free_kb / 1024)) MB libres; exportar TMPDIR y OUT en un disco con 2 GB o más."
done

# Paquetes con sus guardas y copias extraídas fuera del repositorio (RL-6, P-5).
bash "$ROOT/scripts/build-packages.sh" "$OUT/pkg" digitalisimo-tools digitalisimo-theme > "$OUT/build.log" 2>&1 || { cat "$OUT/build.log"; exit 1; }
grep -q 'THEME PACKAGE CHECK: PASS' "$OUT/build.log" || { echo 'El paquete del tema no pasó su guarda:'; cat "$OUT/build.log"; exit 1; }
unzip -q "$(ls "$OUT"/pkg/digitalisimo-tools-*.zip | head -1)" -d "$OUT/plugins"
unzip -q "$(ls "$OUT"/pkg/digitalisimo-theme-*.zip | head -1)" -d "$OUT/themes"
INSTALLED="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([0-9.]*\)[[:space:]]*$/\1/p' "$OUT/themes/digitalisimo-theme/style.css" | head -1)"
[ -n "$INSTALLED" ] || { echo 'El style.css del tema no declara « * Version:»'; exit 1; }
NEXT="$(node -e 'const v = process.argv[1].split(".").map(Number); v[v.length - 1]++; console.log(v.join("."))' "$INSTALLED")"

# Versión siguiente del tema: el mismo paquete con la cabecera y la constante cambiadas (lo que publicaría una Release).
cp -r "$OUT/themes/digitalisimo-theme" "$OUT/next/digitalisimo-theme"
sed -i "s/^\([[:space:]]*\*[[:space:]]*Version:[[:space:]]*\)$INSTALLED[[:space:]]*$/\1$NEXT/" "$OUT/next/digitalisimo-theme/style.css"
sed -i "s/\('DIGITALISIMO_THEME_VERSION',[[:space:]]*'\)$INSTALLED'/\1$NEXT'/" "$OUT/next/digitalisimo-theme/functions.php"
grep -q "Version: $NEXT" "$OUT/next/digitalisimo-theme/style.css" && grep -q "'$NEXT'" "$OUT/next/digitalisimo-theme/functions.php" \
  || { echo "No se pudo preparar la versión $NEXT del tema"; exit 1; }
( cd "$OUT/next" && zip -qr "$OUT/pkg/digitalisimo-theme-$NEXT.zip" digitalisimo-theme )

# Índice: los siete plugins con la versión de su cabecera (ninguno se ofrece como nuevo) y el tema en la siguiente.
node -e '
const fs = require("fs"), path = require("path");
const [root, out, installed, next] = process.argv.slice(1);
const modules = [["digitalisimo-seo", "digitalisimo-integrations.php", "digitalisimo-seo"], ["digitalisimo-ia-tools", "digitalisimo-chatbot.php", "digitalisimo-ia-tools"], ["digitalisimo-hosting", "digitalisimo-hosting.php", "digitalisimo-hosting"], ["digitalisimo-backups", "digitalisimo-backups.php", "digitalisimo-backups"], ["digitalisimo-tools", "digitalisimo-tools.php", "digitalisimo-tools"], ["digitalisimo-elements", "pro-elements.php", "digitalisimo-elements"], ["digitalisimo-migration", "digitalisimo-migration.php", "digitalisimo-migration"]];
const plugins = {};
for (const [dir, file, slug] of modules) plugins[slug] = fs.readFileSync(path.join(root, dir, file), "utf8").match(/^ \* Version: (\d+(?:\.\d+)+)\s*$/m)[1];
fs.writeFileSync(path.join(out, "release.json"), JSON.stringify({ plugins, installed, theme: next }, null, 1));
' "$ROOT" "$OUT" "$INSTALLED" "$NEXT"
cp "$N/mu-net.php" "$N/fs-fail.php" "$N/probe.php" "$OUT/"
echo '{}' > "$OUT/state.json"

STEPS=(setup n1-main n1-sub n2-net n2-sub n5 n6-switch n6-check n6-restore n3f n3f-check n4a n4c n4b n3 n3-check)
# ONLY=setup,n5 ejecuta sólo esos pasos (depuración; «setup» siempre hace falta).
[ -n "${ONLY:-}" ] && IFS=',' read -r -a STEPS <<<"$ONLY"
JSON='{"steps":[{"step":"enableMultisite"}'
for step in "${STEPS[@]}"; do
  JSON="$JSON,{\"step\":\"runPHP\",\"code\":\"<?php putenv('DIGI_STEP=$step'); require '/wordpress/wp-content/probe/probe.php';\"}"
done
echo "$JSON]}" > "$OUT/blueprint.json"
$CLI run-blueprint --site-url=http://playground.test --blueprint="$OUT/blueprint.json" \
  --mount="$OUT/plugins/digitalisimo-tools:/wordpress/wp-content/plugins/digitalisimo-tools" \
  --mount="$OUT:/wordpress/wp-content/probe" > "$OUT/playground.log" 2>&1 || { tail -20 "$OUT/playground.log"; [ -s "$OUT/fatal.log" ] && cat "$OUT/fatal.log"; exit 1; }
status=0
ROOT="$ROOT" OUT="$OUT" STEPS="${STEPS[*]}" node "$N/report.cjs" || status=$?
# Un error fatal hace fallar la prueba aunque el paso alcanzara a guardar su resultado.
if [ -s "$OUT/fatal.log" ]; then echo "Errores fatales:"; cat "$OUT/fatal.log"; status=1; fi
exit $status
