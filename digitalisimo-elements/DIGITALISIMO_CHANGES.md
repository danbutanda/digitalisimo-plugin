# DIGITALÍSIMO Elements 4.3.0.7

Derivado de [PRO Elements 4.3.0](https://github.com/proelements/proelements/releases/tag/v4.3.0), que incorpora código de Elementor Pro. Se conserva el `license.txt` original con los derechos de Elementor Ltd. y del equipo PRO Elements. El código derivado se distribuye bajo GPLv3 o posterior; `COPYING` contiene la licencia completa. DIGITALÍSIMO no está afiliado con Elementor Ltd. ni con el equipo PRO Elements.

Cambios de DIGITALÍSIMO respecto del ZIP original:

- Nombre, avisos visibles y página «Acerca de» con logos de DIGITALÍSIMO; los identificadores internos `ElementorPro`, `elementor-pro` y `ELEMENTOR_PRO_*` se conservan por compatibilidad.
- Aviso explícito cuando Elementor Pro u otro derivado ya se cargó.
- Actualizador propio de GitHub Releases; se desactiva el actualizador de PRO Elements para evitar que sobrescriba esta versión.
- Logos extraídos del sitio `https://digitalisimo.mx/` el 3 de octubre de 2026. Son marcas de DIGITALÍSIMO y no se conceden derechos sobre marcas de Elementor o PRO Elements.
- 4.3.0.2: incluye los archivos `vendor/` del ZIP original en la Release. La primera publicación 4.3.0.1 los omitió y no debe instalarse.
- 4.3.0.3: espera a `plugins_loaded` para comprobar `elementor/loaded`. Corrige el falso aviso «Activa Elementor» cuando Elementor gratuito está activo para la red Multisite.
- 4.3.0.4: incorpora la actualización automática nativa de WordPress mediante `Update URI`, sin activar actualizaciones de otros derivados.
- 4.3.0.5: corrige la comilla de apertura del skin «quotation» del widget Blockquote con la secuencia CSS Unicode `\201C` en las tres hojas de estilo. No modifica el contenido, los estilos ni la configuración de los widgets existentes; el recurso usa la versión propia del plugin para invalidar cachés al actualizar.
- 4.3.0.6: incorpora el widget Slider Optimizado desde Tools, conservando su identificador, controles y salida para que los sliders existentes sigan funcionando. Si todavía hay una versión anterior de Tools activa, Elements reemplaza su registro y su CSS en lugar de mostrar dos widgets.
- 4.3.0.7: limita el icono de Elements en el menú de administración a 20 × 20 píxeles en sitio y red. Conserva el PNG original para la página Acerca de.

Requisitos de esta base: WordPress 6.8+, PHP 7.4+, Elementor gratuito 4.0+ (recomendado 4.3+). Elementor Pro u otra copia de PRO Elements no deben estar activos al mismo tiempo. Funciones como bibliotecas o servicios alojados por Elementor pueden requerir cuenta, conexión o permisos independientes; la licencia GPL del código no concede acceso a dichos servicios.
