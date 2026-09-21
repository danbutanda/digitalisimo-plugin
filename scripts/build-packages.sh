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
  ( cd "$root_dir" && zip -FSrq "$output_dir/$prefix-$version.zip" "$directory" -x '*/.DS_Store' )
  unzip -t "$output_dir/$prefix-$version.zip" >/dev/null
  echo "$output_dir/$prefix-$version.zip"
}

build_package digitalisimo-seo digitalisimo-integrations.php digitalisimo-seo
build_package digitalisimo-ecommerce digitalisimo-ecommerce.php digitalisimo-ecommerce
build_package digitalisimo-geolocalizacion digitalisimo-geolocalizacion.php digitalisimo-geolocalizacion
build_package digitalisimo.chatbot digitalisimo-chatbot.php digitalisimo-ia-tools
build_package digitalisimo-hosting digitalisimo-hosting.php digitalisimo-hosting
