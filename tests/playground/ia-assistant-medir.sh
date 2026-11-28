#!/usr/bin/env bash
# Mide sin gastar lo que el Asistente IA le mandaría a la IA en el caso plomerito (docs/ASSISTANT.md, «Contexto»):
# ia-assistant-agent.sh con SCENARIO=simple y MODE=medir, en la misma ferretería inventada que ia-assistant-simple.sh,
# SIN clave ni API: la sonda guarda cada petición entera en <OUT>/simple-medir/probe/bodies/NNN.json y contesta sola
# «Listo.». Así queda el primer paso de cada caso (instrucciones, herramientas y mensaje), para contar sus partes.
# Puerto 9789 (bloque 9780–9789). No va en la CI.
#   bash /var/tmp/digi-wt/pc-run.sh --traer-a <dir> <worktree> -- bash tests/playground/ia-assistant-medir.sh
set -euo pipefail
SCENARIO=simple MODE=medir exec bash "$(dirname "$0")/ia-assistant-agent.sh" "$@"
