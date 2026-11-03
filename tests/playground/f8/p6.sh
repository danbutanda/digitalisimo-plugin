#!/usr/bin/env bash
# P6 · interacción (f8.sh, escenario delegado en interaction.sh, puerto 9516): todos los casos de interaction/cases.json,
# con los de F8 (f8-tooltip, f8-badge-rtl y f8-nested-counter). Deja interaction.json en $RUN/ix y f8/p6.cjs lo lee.
set -euo pipefail
: "${ROOT:?}" "${RUN:?}" "${PORT:?}"
mkdir -p "$RUN/ix"
OUT="$RUN/ix" PORT="$PORT" PUPPETEER_PATH="${PUPPETEER_PATH:-}" bash "$ROOT/tests/playground/interaction.sh"
