#!/usr/bin/env bash
# El aprendizaje («Entrenar con mi sitio web», «Lo recordaré: …») y la caché del Asistente IA con la API REAL de
# Anthropic (pedidos del propietario del 2026-11-26). ia-assistant-agent.sh con SCENARIO=aprende y MODE=real, en la tienda
# INVENTADA de «marca» con sus políticas, envíos, garantía y una página con texto de inyección. El conductor
# ia-assistant-agent/run-aprende.cjs mide lo que cobró la API en cada respuesta (tokens de entrada, leídos y escritos en
# la caché, salida), las consultas por turno antes y después de entrenar, el resumen y el tamaño del aprendizaje, que
# recuerda una preferencia en otra conversación y que la inyección no se aprende (medicion.json).
# Para comparar la caché con la IA Tools de main: IA_DIR=<copia de digitalisimo-ia-tools de main> PHASES=cache
# IA_LABEL=main (sólo la conversación de varios turnos).
# NO va en la CI (gasta tokens y necesita la clave). Puerto 9787 (bloque 9780–9789; 9785 es de «subir» y 9786 de ia-assistant-estilo.sh). La clave: KEY_FILE (archivo 600).
#   KEY_FILE=<archivo 600> RUN_LABEL=1 bash tests/playground/ia-assistant-aprende.sh
set -euo pipefail
SCENARIO=aprende MODE=real exec bash "$(dirname "$0")/ia-assistant-agent.sh" "$@"
