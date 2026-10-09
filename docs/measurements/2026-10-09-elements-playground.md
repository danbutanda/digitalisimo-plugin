# Prueba instalada de DIGITALÍSIMO Elements

Fecha: 2026-10-09. Entorno aislado: WordPress Playground, WordPress 7.1.3, Elementor gratuito 4.3.4 y DIGITALÍSIMO Elements 4.3.0.71/4.3.0.72 montado desde el repositorio. No se modificó el sitio público.

Se comprobó el registro de Accordion, Advanced Button, Advanced Divider y Slider Optimizado en WordPress individual, en un subsitio Multisite con activación por sitio y en los dos sitios de una red con activación de red. La respuesta de cada instalación informó Elementor cargado, Elements cargado y los cuatro IDs disponibles. Se creó una página Elementor de prueba en el sitio principal (blog 1) y otra en el subsitio (blog 2). Ambas devolvieron el HTML del acordeón con título y contenido personalizados, el botón con texto y enlace propios, y las variantes de separador círculo, cruz y estrella; no apareció un error fatal. La hoja condicional `advanced-divider.css` sirvió las reglas de cruz y estrella.

Esta prueba acredita carga y renderizado real por sitio; no acredita aún paridad visual con Element Pack, comportamiento en el editor, mediciones de CLS o migración automática de documentos `bdt-*`. Los controles, atributos y salidas básicas tienen además pruebas aisladas en `tests/elements-*.php`. Las páginas y los probes fueron temporales dentro de Playground.

Después se montó el código del Encabezado avanzado con controles tipográficos de Elementor y se creó una página adicional en cada sitio. Ambos frontend imprimieron su antetítulo, título, fragmento destacado y decoración sin error fatal. El widget generó su propia etiqueta H1; el tema de prueba añadió además el título de la página, por lo que esta comprobación no representa una validación de jerarquía de encabezados de una plantilla de producción.

La siguiente página de prueba incluyó Caja de icono avanzada y Encabezado animado en ambos sitios. Se imprimieron el título y la descripción de la tarjeta, además de la primera frase visible del encabezado y sus datos de rotación. La inspección del enlace externo confirmó `target="_blank"` y `rel="nofollow noopener noreferrer"` en el frontend tras la corrección. La prueba sigue sin cubrir interacción visual del editor ni mediciones de diseño.

También se creó una página con Brand Carousel y dos logos en el sitio principal y el subsitio. Ambos frontend imprimieron las dos marcas y solicitaron únicamente el motor compartido del carrusel. Ninguna imagen recibió una prioridad de carga fija por su índice en el carrusel. Las URLs usadas eran datos de prueba; esta comprobación verifica el HTML y el registro de recursos, no la descarga de esas imágenes.

Para Breadcrumbs se registró temporalmente un CPT público y jerárquico con archivo, padre e hijo en los dos sitios. Tras regenerar las reglas de enlace de ese entorno de prueba, la página hija imprimió el padre con el permalink correcto para el blog principal (`/blog/`) y para el subsitio (`/sub/`). El widget no añadió JSON-LD propio. La ruta del CPT temporal no existe en producción.

Botón doble se renderizó con dos enlaces en páginas Elementor del sitio principal y el subsitio. El primer enlace externo conservó `target="_blank"` y `rel="nofollow noopener noreferrer"`; el texto intermedio y la segunda acción siguieron visibles. La prueba PHP comprueba también que el editor incluye los iconos y atributos de los dos enlaces.
