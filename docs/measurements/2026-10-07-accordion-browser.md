# Accordion en WordPress Playground

Prueba local con WordPress y Elementor activos. Una página publicada contiene tres elementos de `digitalisimo-accordion`; el primero inicia abierto y se exige un solo panel abierto a la vez.

| Ancho | Paneles | CSS del widget | JS del widget | Desbordamiento | Teclado |
| --- | ---: | ---: | ---: | --- | --- |
| 1280 px | 3 | 1 | 0 | No | Enter abre el segundo y cierra el primero |
| 768 px | 3 | 1 | 0 | No | Enter abre el segundo y cierra el primero |
| 390 px | 3 | 1 | 0 | No | Enter abre el segundo y cierra el primero |

La portada sin este widget no cargó `accordion.css`. El editor de Elementor mostró tanto el control «Acordeón» como su vista previa; no hubo errores JavaScript. El contenido admitió texto con formato y el CSS conservó el foco visible. La implementación usa HTML nativo y no genera schema FAQ por separado.

Estas pruebas no acreditan todavía equivalencia con los controles y estilos de `bdt-accordion`, ni cubren una red Multisite real. El ID anterior permanece libre.
