# Primer piloto de Enlace animado

Comparación estática de los archivos de la copia de referencia Element Pack Pro 9.9.1 frente a DIGITALÍSIMO Elements 4.3.0.11. Las cifras son tamaños de archivo locales, no una medición de transferencia o render en un sitio publicado.

| Recurso | Referencia | DIGITALÍSIMO |
| --- | ---: | ---: |
| CSS del widget | 11 071 B (gzip 1 779 B) | 12 180 B (gzip 1 990 B) |
| JS propio del widget | 0 B | 0 B |
| UIkit CSS global que carga el loader original | 102 437 B (gzip 16 416 B) | 0 B adicionales |
| Helper CSS global original | 46 631 B (gzip 7 156 B) | 0 B adicionales |
| UIkit JS global original | 147 237 B (gzip 49 548 B) | 0 B adicionales |

El CSS propio aumenta 211 B comprimidos para añadir prefijos aislados, foco visible y soporte para movimiento reducido. El widget nuevo declara solamente su hoja mediante `get_style_depends()`; el registro de estilos no la encola globalmente. Esto confirma la arquitectura a nivel de código, pero faltan una prueba de red en WordPress/Elementor y comparación visual de las 15 variantes en escritorio, tableta y móvil. No se atribuye una mejora de Core Web Vitals sin esas pruebas.

Las pruebas PHP verificaron registro único, escape del texto, salida con y sin URL, SVG decorativo y las 15 clases CSS. La compatibilidad de páginas que ya contienen el ID `bdt-animated-link` permanece pendiente; ese ID no se registra en este piloto.
