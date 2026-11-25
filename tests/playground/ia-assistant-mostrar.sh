#!/usr/bin/env bash
# «Primero hacer y mostrar» con la API REAL de Anthropic (caso plomerito 2 del 2026-11-24, IA Tools 1.0.72: «es muy
# preguntón, arroja información técnica… y toca cosas de más»): ia-assistant-agent.sh con SCENARIO=mostrar y MODE=real,
# en una tienda INVENTADA (ia-assistant-agent/setup-mostrar.php: el sitio en UTC y la tienda en México, un purificador de
# $10,000, políticas y el WhatsApp en el pie). El conductor ia-assistant-agent/run-mostrar.cjs manda «¿en qué me ayudas?»,
# «créame una landing de Black Friday del purificador de 10,000 a 7,500, hasta fin de mes, con el número del sitio» y un
# cambio de título, y mide: cero preguntas antes de crear, el borrador en el primer turno, ninguna escritura fuera de la
# landing (ni un intento de cambiar el producto), ninguna palabra técnica ni URL suelta, respuestas cortas y una landing
# sin bloques de muestra, sin enlaces rotos y con el contador a la medianoche de la tienda.
# NO va en la CI (gasta tokens y necesita la clave). Como el modelo varía, se corre varias veces seguidas
# (RUN_LABEL=1, 2, 3… deja cada corrida en $OUT/mostrar-real-<n>). Puerto 9783 (bloque 9780–9789).
# La clave: KEY_FILE (archivo 600 en la máquina que corre el ensayo; nunca en argumentos ni en el repositorio). Desde el
# servidor, la clave viaja a la PC sólo por STDIN y se borra al terminar:
#   { echo 'umask 077; mkdir -p ~/digi-secrets; cat > ~/digi-secrets/iam.key <<"FIN"'; cat ~/.config/digitalisimo/anthropic.key; echo; echo FIN; } | bash /var/tmp/digi-wt/pc-wsl.sh digitalisimo
#   bash /var/tmp/digi-wt/pc-run.sh --traer-a <dir> <worktree> -- env KEY_FILE=/home/digitalisimo/digi-secrets/iam.key RUN_LABEL=1 bash tests/playground/ia-assistant-mostrar.sh
#   echo 'rm -f ~/digi-secrets/iam.key' | bash /var/tmp/digi-wt/pc-wsl.sh digitalisimo
set -euo pipefail
SCENARIO=mostrar MODE=real exec bash "$(dirname "$0")/ia-assistant-agent.sh" "$@"
