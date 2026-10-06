# Slider Optimizado

En Elementor, abre la categoría **DIGITALÍSIMO** y arrastra **Slider Optimizado**. Admite imágenes múltiples con ALT y enlace, o una imagen repetida con espejo alternado. Los controles permiten animación continua o desactivada, dirección, duración, pausa, loop, cantidad visible por dispositivo, separación, altura, tamaño y carga de imágenes. Con movimiento reducido, el contenido queda visible sin animación.

En altura **Automática**, el widget usa las dimensiones reales del archivo para reservar la proporción correcta, incluso si faltan metadatos del adjunto. Si no puede determinar sus dimensiones, carga esa imagen sin diferirla para evitar un espacio provisional excesivo.

En **Estilo → Elementos visibles**, selecciona entre 1 y 9 para escritorio, tableta y móvil mediante los iconos responsive del control. Los valores iniciales son 9, 6 y 3; cada vista se puede cambiar por separado. Los sliders ya guardados conservan su configuración hasta que la edites.

En el modo de imágenes múltiples, **Seleccionar varias imágenes** abre la biblioteca para elegirlas y ordenarlas de una vez. Se usa el ALT guardado en cada adjunto. Los elementos individuales siguen disponibles para imágenes que necesiten un ALT o enlace propio; aparecen después de la selección múltiple. Los sliders guardados con elementos individuales no requieren migración.

El modo continuo usa únicamente CSS; el widget no declara scripts ni Swiper. Su CSS se registra como dependencia del widget y Elementor lo carga sólo donde se usa. No modifica el carrusel nativo ni páginas existentes.

La migración conserva el tipo `digitalisimo-slider-optimizado` y todos los nombres de controles; no reescribe contenidos guardados en Elementor. Actualiza Elements antes de Tools para mantener el widget disponible durante el cambio.
