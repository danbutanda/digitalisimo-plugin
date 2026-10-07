# Slider Optimizado

En Elementor, abre la categoría **DIGITALÍSIMO** y arrastra **Slider Optimizado**. Admite imágenes múltiples con ALT y enlace, o una imagen repetida con espejo alternado. Los controles permiten animación continua o desactivada, dirección, duración, pausa, loop, cantidad visible por dispositivo, separación, altura, tamaño y carga de imágenes. Con movimiento reducido, el contenido queda visible sin animación.

En altura **Automática**, el widget usa las dimensiones reales del archivo para reservar la proporción correcta, incluso si faltan metadatos del adjunto. Si no puede determinar sus dimensiones, carga esa imagen sin diferirla para evitar un espacio provisional excesivo.

En **Estilo → Elementos visibles**, selecciona entre 1 y 9 para escritorio, tableta y móvil mediante los iconos responsive del control. Los valores iniciales son 9, 6 y 3; cada vista se puede cambiar por separado. Los sliders ya guardados conservan su configuración hasta que la edites.

En el modo de imágenes múltiples, **Seleccionar varias imágenes** abre la biblioteca para elegirlas y ordenarlas de una vez. Se usa el ALT guardado en cada adjunto. Los elementos individuales siguen disponibles para imágenes que necesiten un ALT o enlace propio; aparecen después de la selección múltiple. Los sliders guardados con elementos individuales no requieren migración.

El movimiento usa CSS y un script pequeño que espera a que se decodifiquen las imágenes visibles antes de iniciarlo. La primera vista y un elemento adicional se solicitan de inmediato; en dirección derecha se prepara la unión entre el final del primer grupo y el comienzo de su copia. El resto conserva carga diferida. El CSS y el script son dependencias del widget y Elementor los carga sólo donde se usa. No se utiliza Swiper ni se modifica el carrusel nativo o los contenidos guardados.

Con **Imagen repetida / banner** y **Reflejar elementos alternados**, todas las copias de la misma imagen se preparan antes de iniciar el movimiento, y el espacio entre mitades se reduce a cero para unir sus bordes. La imagen alterna normal/reflejada en ambas series, también en el punto donde reinicia el bucle. Los sliders de logos e imágenes múltiples conservan su separación y carga progresiva.

Una página puede contener varias instancias. Las que están a la vista o cerca de ella se agrupan para comenzar su animación en el mismo fotograma una vez listas sus imágenes; las instancias más abajo se preparan cuando el usuario se acerca, sin bloquear las primeras. Cada instancia conserva sus propios controles, dimensiones y contenido.

La migración conserva el tipo `digitalisimo-slider-optimizado` y todos los nombres de controles; no reescribe contenidos guardados en Elementor. Actualiza Elements antes de Tools para mantener el widget disponible durante el cambio.
