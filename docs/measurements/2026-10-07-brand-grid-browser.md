# Cuadrícula de marcas · prueba de navegador

Entorno: WordPress Playground (PHP 8.3), Elementor gratuito y Chromium. Página temporal con tres instancias de `digitalisimo-brand-grid`: detalles visibles, revelado por foco o puntero y divulgación con `<details>`.

- Con cuatro marcas, el grid mostró 3 columnas a 1280 px, 2 a 768 px y 1 a 390 px. No hubo desbordamiento horizontal ni errores JavaScript.
- `brand-grid.css` apareció una vez en la página de prueba y cero veces en la portada sin el widget. No se cargó ningún script propio.
- El modo «Al abrir» respondió al clic con `<details open>`. En el modo «Al apuntar o enfocar», el contenido apareció al enfocar el enlace con teclado; su área no agranda la tarjeta oculta.
- El editor Elementor mostró «Cuadrícula de marcas» y las tres instancias en su vista previa sin errores JavaScript.
- Las imágenes de ejemplo fueron recursos del propio plugin. Una versión blanca sobre fondo blanco sólo afecta este fixture visual, no el modo de carga.

Todavía faltan la comparación visual con `bdt-brand-grid`, las opciones decorativas avanzadas del original y la verificación de páginas heredadas. El ID antiguo permanece libre.
