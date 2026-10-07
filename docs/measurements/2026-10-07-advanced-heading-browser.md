# Encabezado avanzado · prueba de navegador

Entorno: WordPress Playground (WordPress latest, PHP 8.3), Elementor gratuito, DIGITALÍSIMO Elements 4.3.0.20, Chromium 1280 × 900. Página de prueba con un `digitalisimo-advanced-heading`: antetítulo, H1, fragmento destacado, enlace y decoración.

- El frontend muestra exactamente un H1 propio del widget; su texto leído es «Agencia de marketing digital para empresas». El fragmento conserva un espacio real en el DOM.
- El texto ornamental lleva `aria-hidden="true"`; el título y el enlace se muestran en la página sin desbordamiento horizontal.
- La página carga `advanced-heading.css?ver=4.3.0.20` y ningún script específico del widget. No hubo errores JavaScript.
- El editor Elementor muestra «Encabezado avanzado» en el panel y renderiza la vista previa dentro del iframe; allí se carga una sola hoja específica y no hubo errores JavaScript.
- La comparación con `bdt-advanced-heading` todavía no está hecha. No se registró el ID anterior ni se migraron documentos existentes.

La prueba se ejecutó con un sitio local temporal; no modifica el WordPress público.
