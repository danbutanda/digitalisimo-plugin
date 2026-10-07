# Accordion en WordPress Playground

Prueba local con WordPress y Elementor activos. Una página publicada contiene tres elementos de `digitalisimo-accordion`; el primero inicia abierto y se exige un solo panel abierto a la vez.

| Ancho | Paneles | CSS del widget | JS del widget | Desbordamiento | Teclado |
| --- | ---: | ---: | ---: | --- | --- |
| 1280 px | 3 | 1 | 0 | No | Enter abre el segundo y cierra el primero |
| 768 px | 3 | 1 | 0 | No | Enter abre el segundo y cierra el primero |
| 390 px | 3 | 1 | 0 | No | Enter abre el segundo y cierra el primero |

La portada sin este widget no cargó `accordion.css`. El editor de Elementor mostró tanto el control «Acordeón» como su vista previa; no hubo errores JavaScript. El contenido admitió texto con formato y el CSS conservó el foco visible. La implementación usa HTML nativo y no genera schema FAQ por separado.

Estas pruebas no acreditan todavía equivalencia con los controles y estilos de `bdt-accordion`, ni cubren una red Multisite real. El ID anterior permanece libre.

La ampliación 4.3.0.25 pasó nuevamente las pruebas en 1280, 768 y 390 px: dos paneles, icono de título, iconos alternos de estado y posición izquierda se renderizaron sin desbordamiento; Enter abrió el segundo y cerró el primero. La portada siguió sin cargar el CSS y el editor mostró la vista previa sin errores JavaScript. Una prueba PHP comprobó que sólo se listan y muestran plantillas publicadas del sitio actual y que dos instancias del widget comparten las consultas del selector. Aún falta probar el render de una plantilla real dentro de Elementor y una red Multisite.
