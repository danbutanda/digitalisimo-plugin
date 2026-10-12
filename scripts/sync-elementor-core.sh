#!/usr/bin/env bash
# Sincroniza el núcleo de Elementor incluido en DIGITALÍSIMO Elements (digitalisimo-elements/elementor/).
# Uso: bash scripts/sync-elementor-core.sh 4.3.4
#
# Descarga la versión indicada de wordpress.org, comprueba cada archivo contra las sumas SHA-256 oficiales
# (downloads.wordpress.org/plugin-checksums) y la copia sin cambios en elementor/. Añade los paquetes de idioma
# en español (todas las variantes es_*) en elementor/languages/ y deja constancia en elementor/digitalisimo-core.json.
# Los archivos de elementor/ nunca se editan a mano: cada actualización de Elementor (en especial las de
# seguridad) se trae con este script y se publica como una versión nueva de DIGITALÍSIMO Elements.
set -euo pipefail
VERSION="${1:?Indica la versión de Elementor, por ejemplo 4.3.4}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
CORE="$ROOT/digitalisimo-elements/elementor"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

echo "Descargando Elementor $VERSION…"
curl -fsSL -o "$WORK/elementor.zip" "https://downloads.wordpress.org/plugin/elementor.$VERSION.zip"
unzip -tq "$WORK/elementor.zip" > /dev/null
curl -fsSL -o "$WORK/checksums.json" "https://downloads.wordpress.org/plugin-checksums/elementor/$VERSION.json"
mkdir -p "$WORK/src"
unzip -q "$WORK/elementor.zip" -d "$WORK/src"
[ -f "$WORK/src/elementor/elementor.php" ] || { echo "El paquete no contiene elementor/elementor.php"; exit 1; }
grep -q "define( 'ELEMENTOR_VERSION', '$VERSION' );" "$WORK/src/elementor/elementor.php" || { echo "La versión del paquete no es $VERSION"; exit 1; }

# Cada archivo del paquete debe figurar en las sumas oficiales con el mismo SHA-256, y viceversa.
python3 - "$WORK/checksums.json" "$WORK/src/elementor" <<'PY'
import hashlib, json, os, sys
checksums = json.load(open(sys.argv[1]))['files']
root = sys.argv[2]
found = {}
for base, _, files in os.walk(root):
    for name in files:
        path = os.path.join(base, name)
        found[os.path.relpath(path, root).replace(os.sep, '/')] = hashlib.sha256(open(path, 'rb').read()).hexdigest()
missing = sorted(set(checksums) - set(found))
extra = sorted(set(found) - set(checksums))
bad = sorted(p for p, h in found.items() if p in checksums and h not in (checksums[p]['sha256'] if isinstance(checksums[p]['sha256'], list) else [checksums[p]['sha256']]))
if missing or extra or bad:
    print('Sumas que no cuadran. Faltan: %s · Sobran: %s · Distintos: %s' % (missing[:5], extra[:5], bad[:5]))
    sys.exit(1)
print('Sumas comprobadas: %d archivos idénticos a wordpress.org.' % len(found))
PY

# Paquetes de idioma en español de esa versión (PHP y JSON de los scripts del editor).
mkdir -p "$WORK/languages"
LOCALES="$(curl -fsSL "https://api.wordpress.org/translations/plugins/1.0/?slug=elementor&version=$VERSION" | python3 -c "
import json, sys
for t in json.load(sys.stdin)['translations']:
    if t['language'].startswith('es'):
        print(t['language'] + ' ' + t['package'])")"
while read -r locale package; do
  [ -n "$locale" ] || continue
  curl -fsSL -o "$WORK/$locale.zip" "$package"
  unzip -qo "$WORK/$locale.zip" -d "$WORK/languages"
done <<< "$LOCALES"
# Los .po son el texto fuente de las traducciones; WordPress sólo usa .l10n.php, .mo y los JSON.
find "$WORK/languages" -name '*.po' -delete

# Sólo se reemplaza una carpeta creada por este script (con su manifiesto): nunca otra del plugin.
if [ -e "$CORE" ] && [ ! -f "$CORE/digitalisimo-core.json" ]; then echo "$CORE existe y no es un núcleo sincronizado: no se toca"; exit 1; fi
rm -rf "$CORE"
mkdir -p "$CORE"
cp -a "$WORK/src/elementor/." "$CORE/"
mkdir -p "$CORE/languages"
cp -a "$WORK/languages/." "$CORE/languages/"
python3 - "$CORE/digitalisimo-core.json" "$VERSION" "$WORK/elementor.zip" "$LOCALES" <<'PY'
import datetime, hashlib, json, sys
path, version, archive, locales = sys.argv[1:5]
json.dump({
    'name': 'Elementor',
    'version': version,
    'source': 'https://downloads.wordpress.org/plugin/elementor.%s.zip' % version,
    'sha256': hashlib.sha256(open(archive, 'rb').read()).hexdigest(),
    'license': 'GPL-3.0-or-later',
    'translations': sorted(line.split()[0] for line in locales.splitlines() if line.strip()),
    'synced': datetime.date.today().isoformat(),
}, open(path, 'w'), indent=2)
open(path, 'a').write('\n')
PY
echo "Núcleo de Elementor $VERSION en digitalisimo-elements/elementor ($(find "$CORE" -type f | wc -l) archivos, idiomas: $(echo "$LOCALES" | awk '{print $1}' | paste -sd' '))."
