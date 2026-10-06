#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "$0")/.." && pwd)"
output_dir="${1:-$root_dir/dist}"
mkdir -p "$output_dir"

build_package() {
  local directory="$1"
  local main_file="$2"
  local prefix="$3"
  local version
  version="$(sed -n 's/^ \* Version: \(.*\)$/\1/p' "$root_dir/$directory/$main_file" | head -1 | tr -d '[:space:]')"
  test -n "$version"
  local archive="$output_dir/$prefix-$version.zip"
  local exclude=('*/.DS_Store')
  if [[ "$directory" == 'digitalisimo-elements' ]]; then
    # The local Element Pack copy is research material, never plugin runtime code.
    exclude+=( 'digitalisimo-elements/bdthemes-element-pack' 'digitalisimo-elements/bdthemes-element-pack/*' )
  fi
  ( cd "$root_dir" && zip -FSrq "$archive" "$directory" -x "${exclude[@]}" )
  unzip -t "$archive" >/dev/null
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
  fi
  echo "$archive"
}

packages=("${@:2}")
if (( ${#packages[@]} == 0 )); then
  packages=(digitalisimo-seo digitalisimo-ia-tools digitalisimo-hosting digitalisimo-backups digitalisimo-tools digitalisimo-elements)
fi

for package in "${packages[@]}"; do
  case "$package" in
    digitalisimo-seo) build_package digitalisimo-seo digitalisimo-integrations.php "$package" ;;
    digitalisimo-ia-tools) build_package digitalisimo.chatbot digitalisimo-chatbot.php "$package" ;;
    digitalisimo-hosting) build_package digitalisimo-hosting digitalisimo-hosting.php "$package" ;;
    digitalisimo-backups) build_package digitalisimo-backups digitalisimo-backups.php "$package" ;;
    digitalisimo-tools) build_package digitalisimo-tools digitalisimo-tools.php "$package" ;;
    digitalisimo-elements) build_package digitalisimo-elements pro-elements.php "$package" ;;
    *) echo "Módulo desconocido: $package" >&2; exit 1 ;;
  esac
done
