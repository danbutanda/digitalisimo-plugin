#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "$0")/.." && pwd)"
output_dir="${1:-$root_dir/dist}"
mkdir -p "$output_dir"
# Ruta absoluta: algunos paquetes se arman desde una copia temporal con la carpeta del sitio (cd), y una salida
# relativa como `dist` dejaría de encontrarse.
output_dir="$(cd "$output_dir" && pwd)"

# build_package <carpeta del repositorio> <archivo principal> <prefijo del ZIP> [carpeta dentro del ZIP]
# La carpeta dentro del ZIP es la que reciben los sitios (wp-content/plugins/<carpeta>/<archivo principal>) y no puede
# cambiar sin desactivar el plugin instalado; la del repositorio puede llamarse distinto (IA Tools vive en
# digitalisimo-ia-tools/ y se instala como digitalisimo.chatbot/). Ver la tabla de README.md.
build_package() {
  local directory="$1"
  local main_file="$2"
  local prefix="$3"
  local folder="${4:-$1}"
  local version
  version="$(sed -n 's/^ \* Version: \(.*\)$/\1/p' "$root_dir/$directory/$main_file" | head -1 | tr -d '[:space:]')"
  test -n "$version"
  local archive="$output_dir/$prefix-$version.zip"
  local exclude=('*/.DS_Store')
  if [[ "$directory" == 'digitalisimo-elements' ]]; then
    # The local Element Pack copy is research material, never plugin runtime code.
    exclude+=( 'digitalisimo-elements/bdthemes-element-pack' 'digitalisimo-elements/bdthemes-element-pack/*' )
  fi
  if [[ "$directory" == 'digitalisimo-elements' ]]; then
    # El núcleo de Elementor incluido sale tal cual de wordpress.org: otros plugins copian archivos en la carpeta
    # de Elementor (y en un sitio de pruebas esa carpeta es ésta), así que su huella debe ser la del manifiesto.
    local expected actual
    expected="$(python3 -c 'import json,sys; m=json.load(open(sys.argv[1])); print(m["files"], m["tree_sha256"])' "$root_dir/$directory/elementor/digitalisimo-core.json")"
    actual="$(python3 "$root_dir/scripts/elementor-core-tree.py" "$root_dir/$directory/elementor" | python3 -c 'import json,sys; m=json.load(sys.stdin); print(m["files"], m["tree_sha256"])')"
    if [[ "$expected" != "$actual" ]]; then
      echo "BUILD ABORTED: digitalisimo-elements/elementor no coincide con su manifiesto ($actual frente a $expected)" >&2
      git -C "$root_dir" status --short --untracked-files=all --ignored -- "$directory/elementor" 2>/dev/null | head -20 >&2 || true
      return 1
    fi
    echo 'BUNDLED CORE CHECK: PASS' >&2
  fi
  if [[ "$folder" == "$directory" ]]; then
    ( cd "$root_dir" && zip -FSrq "$archive" "$directory" -x "${exclude[@]}" )
  else
    local stage
    stage="$(mktemp -d)"
    cp -a "$root_dir/$directory" "$stage/$folder"
    ( cd "$stage" && zip -FSrq "$archive" "$folder" -x "${exclude[@]}" )
    rm -rf "$stage"
  fi
  unzip -t "$archive" >/dev/null
  if ! unzip -Z1 "$archive" | grep -xF "$folder/$main_file" >/dev/null; then
    echo "BUILD ABORTED: $archive no contiene $folder/$main_file" >&2
    rm -f "$archive"
    return 1
  fi
  if [[ "$directory" == 'digitalisimo-elements' ]]; then
    local leaked
    leaked="$(unzip -Z1 "$archive" | LC_ALL=C grep -Ei '(^|/)(bdthemes-element-pack|element-pack-pro)(/|$)|bdthemes' || true)"
    if [[ -n "$leaked" ]]; then
      echo "BUILD ABORTED: Element Pack reference leaked into $archive" >&2
      echo "$leaked" >&2
      rm -f "$archive"
      return 1
    fi
    echo 'REFERENCE LEAK CHECK: PASS' >&2
    # El núcleo no nombra ni contiene nada de Element Pack ni de Prime Slider: su compatibilidad vive en DIGITALÍSIMO
    # Migración.
    local mentions
    mentions="$(unzip -p "$archive" | LC_ALL=C grep -aoiE 'bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]' | sort | uniq -c | head -20 || true)"
    if [[ -n "$mentions" ]]; then
      echo "BUILD ABORTED: $archive menciona Element Pack o Prime Slider" >&2
      echo "$mentions" >&2
      rm -f "$archive"
      return 1
    fi
    echo 'LEGACY MENTION CHECK: PASS' >&2
  fi
  echo "$archive"
}

packages=("${@:2}")
if (( ${#packages[@]} == 0 )); then
  packages=(digitalisimo-seo digitalisimo-ia-tools digitalisimo-hosting digitalisimo-backups digitalisimo-tools digitalisimo-elements digitalisimo-migration)
fi

for package in "${packages[@]}"; do
  case "$package" in
    digitalisimo-seo) build_package digitalisimo-seo digitalisimo-integrations.php "$package" ;;
    digitalisimo-ia-tools) build_package digitalisimo-ia-tools digitalisimo-chatbot.php "$package" digitalisimo.chatbot ;;
    digitalisimo-hosting) build_package digitalisimo-hosting digitalisimo-hosting.php "$package" ;;
    digitalisimo-backups) build_package digitalisimo-backups digitalisimo-backups.php "$package" ;;
    digitalisimo-tools) build_package digitalisimo-tools digitalisimo-tools.php "$package" ;;
    digitalisimo-elements) build_package digitalisimo-elements pro-elements.php "$package" ;;
    digitalisimo-migration) build_package digitalisimo-migration digitalisimo-migration.php "$package" ;;
    *) echo "Módulo desconocido: $package" >&2; exit 1 ;;
  esac
done
