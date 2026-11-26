#!/usr/bin/env bash
# La landing con la marca DEL SITIO, con la API REAL de Anthropic (caso plomerito 3 del 2026-11-25, IA Tools 1.0.73 +
# Elements 4.3.0.197: la landing de «todos los productos al 40 %» salió con los colores de DIGITALÍSIMO, una plantilla de
# curso, sin productos, con el botón al carrito vacío, y el Asistente pidió activar el «Editor atómico»).
# ia-assistant-agent.sh con SCENARIO=marca y MODE=real, en una tienda INVENTADA (ia-assistant-agent/setup-marca.php:
# morado y naranja, Montserrat y Lato, tema DIGITALÍSIMO con cabecera y pie de Elements, 12 productos en tres
# categorías). CASE=kit: el kit con los colores de la marca; CASE=fabrica: el kit de fábrica y los colores reales sólo
# en la cabecera y el pie. El conductor ia-assistant-agent/run-marca.cjs pide «créame una landing de oferta navideña con
# todos los productos al 40 %» y mide en la landing que se ve (Chrome): colores y fuentes del sitio y ninguno de
# DIGITALÍSIMO, la cabecera y el pie del sitio, la cuadrícula de productos reales con su precio tachado y 40 % menos,
# ningún texto de cursos, el botón a la tienda, cero preguntas y ningún precio cambiado, nada de jerga y ningún ajuste
# que activar. Deja la captura (landing.png).
# NO va en la CI (gasta tokens y necesita la clave). Como el modelo varía, se corre varias veces seguidas (RUN_LABEL).
# Puerto 9784 (bloque 9780–9789). La clave: KEY_FILE (archivo 600; nunca en argumentos ni en el repositorio).
#   KEY_FILE=<archivo 600> CASE=kit RUN_LABEL=1 bash tests/playground/ia-assistant-marca.sh
set -euo pipefail
SCENARIO=marca MODE=real exec bash "$(dirname "$0")/ia-assistant-agent.sh" "$@"
