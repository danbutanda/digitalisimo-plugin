#!/usr/bin/env bash
# Subir archivos al Asistente IA con Chrome y la API REAL de Anthropic (pedido del propietario del 2026-11-26: «agrégale
# la función de agregar las imágenes y todas las debe comprimir a .webp al subirse, ya debemos tener un plugin que hace
# eso, solo úsalo»): ia-assistant-agent.sh con SCENARIO=subir y MODE=real. Suelta dos JPG y un PNG en la pantalla real,
# comprueba que llegan a la biblioteca en WebP con el conversor de DIGITALÍSIMO Tools y le pide a Claude que use uno en
# un borrador. NO va en la CI (gasta tokens y necesita la clave). Deja evaluacion.json, respuestas.md y las capturas
# subir-soltar.png, subir-bandeja.png y subir-respuesta.png en $OUT/subir-real.
# La clave: KEY_FILE, como en ia-assistant-real.sh (por STDIN a la PC y borrada al terminar).
set -euo pipefail
MODE=real SCENARIO=subir exec bash "$(dirname "$0")/ia-assistant-agent.sh" "$@"
