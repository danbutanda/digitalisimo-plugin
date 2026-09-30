#!/usr/bin/env bash
set -euo pipefail

base="$1"
head="$2"
if [[ "$base" =~ ^0+$ ]]; then
  base="$(git rev-list --max-parents=0 "$head" | head -1)"
fi

git diff --name-only -z "$base" "$head" | while IFS= read -r -d '' path; do
  case "$path" in
    digitalisimo-seo/*) echo digitalisimo-seo ;;
    digitalisimo-ecommerce/*) echo digitalisimo-ecommerce ;;
    digitalisimo.chatbot/*) echo digitalisimo-ia-tools ;;
    digitalisimo-hosting/*) echo digitalisimo-hosting ;;
    digitalisimo-backups/*) echo digitalisimo-backups ;;
    digitalisimo-tools/*) echo digitalisimo-tools ;;
  esac
done | sort -u
