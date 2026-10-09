# Prueba instalada de DIGITALÍSIMO Elements

Fecha: 2026-10-09. Entorno aislado: WordPress Playground, WordPress 7.1.3, Elementor gratuito 4.3.4 y DIGITALÍSIMO Elements 4.3.0.71/4.3.0.72 montado desde el repositorio. No se modificó el sitio público.

Se comprobó el registro de Accordion, Advanced Button, Advanced Divider y Slider Optimizado en WordPress individual, en un subsitio Multisite con activación por sitio y en los dos sitios de una red con activación de red. La respuesta de cada instalación informó Elementor cargado, Elements cargado y los cuatro IDs disponibles. Se creó una página Elementor de prueba en el sitio principal (blog 1) y otra en el subsitio (blog 2). Ambas devolvieron el HTML del acordeón con título y contenido personalizados, el botón con texto y enlace propios, y las variantes de separador círculo, cruz y estrella; no apareció un error fatal. La hoja condicional `advanced-divider.css` sirvió las reglas de cruz y estrella.

Esta prueba acredita carga y renderizado real por sitio; no acredita aún paridad visual con Element Pack, comportamiento en el editor, mediciones de CLS o migración automática de documentos `bdt-*`. Los controles, atributos y salidas básicas tienen además pruebas aisladas en `tests/elements-*.php`. Las páginas y los probes fueron temporales dentro de Playground.

Después se montó el código del Encabezado avanzado con controles tipográficos de Elementor y se creó una página adicional en cada sitio. Ambos frontend imprimieron su antetítulo, título, fragmento destacado y decoración sin error fatal. El widget generó su propia etiqueta H1; el tema de prueba añadió además el título de la página, por lo que esta comprobación no representa una validación de jerarquía de encabezados de una plantilla de producción.
