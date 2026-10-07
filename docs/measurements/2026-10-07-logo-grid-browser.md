# Cuadrícula de logotipos en WordPress Playground

Prueba local con WordPress y Elementor activos, usando `digitalisimo-logo-grid` en una página publicada. Se verificó el editor de Elementor y el HTML público en 1280, 768 y 390 px.

| Ancho | Columnas observadas | Elementos | CSS del widget | JS del widget | Desbordamiento horizontal |
| --- | --- | ---: | ---: | ---: | --- |
| 1280 px | 4 | 5 | 1 | 0 | No |
| 768 px | 2 | 5 | 1 | 0 | No |
| 390 px | 2 | 5 | 1 | 0 | No, tras corregir el ancho de la tarjeta |

La portada sin este widget no cargó `logo-grid.css`. En Elementor aparecieron tanto el control «Cuadrícula de logotipos» como la vista previa, sin errores JavaScript. La prueba confirmó el enlace individual, la leyenda y el texto alternativo. La primera pasada detectó un desbordamiento a 390 px provocado por `aspect-ratio` junto a `min-height`; se corrigió fijando `width:100%` en cada tarjeta y se repitió la comprobación de la página y de la portada.

La prueba no acredita equivalencia visual con `bdt-logo-grid` ni cubre todavía WordPress Multisite; el ID anterior permanece sin registrar.
