#!/usr/bin/env bash
# La conversación del caso plomerito con la API REAL de Anthropic (claude-opus-5-5): ia-assistant-agent.sh con
# MODE=real. NO va en la CI (gasta tokens y necesita la clave). Guarda conversacion.json, respuestas.md,
# evaluacion.json y las capturas de la landing (landing.png, landing-movil.png) en $OUT/real.
# La clave: KEY_FILE (un archivo 600 en la máquina que corre el ensayo; nunca en argumentos ni en el repositorio).
# Desde el servidor, la clave viaja a la PC sólo por STDIN y se borra al terminar:
#   { echo 'umask 077; mkdir -p ~/digi-secrets; cat > ~/digi-secrets/ias.key <<"FIN"'; cat ~/.config/digitalisimo/anthropic.key; echo; echo FIN; } | bash /var/tmp/digi-wt/pc-wsl.sh digitalisimo
#   bash /var/tmp/digi-wt/pc-run.sh --traer-a <dir> <worktree> -- env KEY_FILE=/home/digitalisimo/digi-secrets/ias.key bash tests/playground/ia-assistant-real.sh
#   echo 'rm -f ~/digi-secrets/ias.key' | bash /var/tmp/digi-wt/pc-wsl.sh digitalisimo
set -euo pipefail
MODE=real exec bash "$(dirname "$0")/ia-assistant-agent.sh" "$@"
