#!/usr/bin/env bash
# «Sin tecnicismos» con la API REAL de Anthropic (casos plomerito 2, 3 y 4 del 2026-11-24 al 2026-11-27; el 4: «el
# asistente lo veo muy técnico, debe ser fácil de usar para el usuario sin nada de conocimientos que sólo quiere un sitio
# o tienda web»): ia-assistant-agent.sh con SCENARIO=simple y MODE=real, en una ferretería INVENTADA
# (ia-assistant-agent/setup-simple.php: DIGITALÍSIMO SEO, Skydropx 2.1.5 real sin vincular, tres productos sin peso, la
# «Sample Page» publicada y texto de muestra en latín en la portada). El conductor ia-assistant-agent/run-simple.cjs
# manda las frases de los casos («revisa el sitio web y optimizalo en SEO», «revisa si el plugin skydropx esta
# funcionando bien y mi carrito funciona bien», la landing de Black Friday y la oferta navideña de toda la tienda) y mide:
# cero tarjetas de confirmación en revisiones, cero palabras técnicas, cero «hazlo tú» cuando hay herramienta y como
# mucho una pregunta por respuesta.
# NO va en la CI (gasta tokens y necesita la clave). Como el modelo varía, se corre varias veces (RUN_LABEL=1, 2, 3…).
# Puerto 9788 (bloque 9780–9789). La clave: KEY_FILE (archivo 600 en la máquina que corre el ensayo; nunca en
# argumentos ni en el repositorio). Desde el servidor, la clave viaja a la PC sólo por STDIN y se borra al terminar:
#   { echo 'umask 077; mkdir -p ~/digi-secrets; cat > ~/digi-secrets/ias.key <<"FIN"'; cat ~/.config/digitalisimo/anthropic.key; echo; echo FIN; } | bash /var/tmp/digi-wt/pc-wsl.sh digitalisimo
#   bash /var/tmp/digi-wt/pc-run.sh --traer-a <dir> <worktree> -- env KEY_FILE=/home/digitalisimo/digi-secrets/ias.key RUN_LABEL=1 bash tests/playground/ia-assistant-simple.sh
#   echo 'rm -f ~/digi-secrets/ias.key' | bash /var/tmp/digi-wt/pc-wsl.sh digitalisimo
set -euo pipefail
SCENARIO=simple MODE=real exec bash "$(dirname "$0")/ia-assistant-agent.sh" "$@"
