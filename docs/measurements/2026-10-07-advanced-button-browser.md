# Botón avanzado en WordPress Playground

WordPress con Elementor activo y página publicada con `digitalisimo-advanced-button`. El widget usó texto, enlace externo, icono, distintivo y efecto CSS C.

| Ancho | Botones | CSS del widget | Icono | Distintivo | Desbordamiento horizontal |
| --- | ---: | ---: | ---: | --- | --- |
| 1280 px | 1 | 1 | 1 | Nuevo | No |
| 768 px | 1 | 1 | 1 | Nuevo | No |
| 390 px | 1 | 1 | 1 | Nuevo | No |

El enlace conservó `https://example.com/` y `rel="noopener noreferrer nofollow"`. Al pasar el cursor, el efecto C transformó su capa decorativa desde el lado sin alterar el texto. La portada sin el widget no cargó `advanced-button.css`. El editor mostró el control y la vista previa sin errores JavaScript. La prueba PHP rechazó URL `javascript:`, escapó texto e ID, y evitó renderizar un botón sin nombre accesible. Aún falta equivalencia visual con las nueve variantes originales y una prueba de runtime en Multisite. El ID `bdt-advanced-button` permanece libre.
