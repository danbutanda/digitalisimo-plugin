# DIGITALÍSIMO Elements 4.3.0.94

- 4.3.0.94: Las páginas guardadas con Accordion, Advanced Button, Advanced Divider, Advanced Heading, Advanced Icon Box y Animated Heading de Element Pack se siguen mostrando sin ese plugin: sus IDs `bdt-*` se registran como adaptadores de los widgets propios, traducen los ajustes guardados (completando los valores por defecto de Element Pack) y registran sus controles de estilo con selectores del marcado propio para que Elementor regenere los colores, tipografías y espacios configurados. Herramientas → Migrar Element Pack y `wp digitalisimo-elements ep-migrate` muestran qué widgets usa cada sitio y traducen los documentos por lotes con copia del original y reversión. Los adaptadores no se activan mientras Element Pack siga cargado.

- 4.3.0.93: QR Code usa el objeto realmente consultado en páginas individuales y la URL actual en archivos, evitando generar el enlace de una plantilla de Elementor o de otro post global.

- 4.3.0.92: Product Grid deja que WordPress decida la carga de imágenes, conserva el ALT de imágenes externas sin título, protege enlaces externos y alinea la vista previa con el conteo de reseñas y los atributos del enlace.

- 4.3.0.91: Notification espera correctamente al activador de clic o cursor y no aparece sola cuando éste falta. El modo cursor también responde al enfoque del teclado.

- 4.3.0.90: Logo Grid deja a WordPress decidir la carga de imágenes según la página y protege enlaces externos. Google Reviews se validó con Elementor instalado en el sitio principal y un subsitio Multisite, sin exponer la clave; no se consultó la API real.

- 4.3.0.89: Icon Mobile Menu e Icon Nav protegen los enlaces externos y alinean en el editor los tooltips, atributos de enlaces e imagen de marca con el frontend.

- 4.3.0.88: Fancy Tabs muestra todos sus paneles, iconos y CTA en el editor y deja que WordPress decida la carga de sus iconos de imagen. Featured Box alinea ALT, enlaces e icono del botón en el editor. Ambos protegen enlaces externos.

- 4.3.0.87: Fancy Icons protege enlaces externos; Fancy Slider alinea enlaces de títulos y botones, ALT y controles de navegación entre frontend y editor.

- 4.3.0.86: Fancy Card muestra el icono, ALT y enlaces en el editor; Fancy List protege enlaces externos y deja que WordPress decida la prioridad de carga de las imágenes.

- 4.3.0.85: Creative Button muestra icono y atributos del enlace en el editor y protege enlaces externos; Device Slider muestra títulos, ALT, enlaces y flechas en el editor y reactiva el motor compartido allí junto con Fancy Slider.

- 4.3.0.84: Custom Gallery alinea la vista previa del editor con el ALT de Medios y los atributos de cada enlace; los enlaces externos del frontend usan `noopener noreferrer`.

- 4.3.0.83: Content Switcher muestra todos los paneles e iconos en el editor, con IDs y relaciones accesibles consistentes con el frontend.

- 4.3.0.82: Las páginas con Visibility Controls de Element Pack siguen mostrando y ocultando lo mismo al retirar ese plugin. Elements lee los mismos ajustes `ep_display_conditions_*` con su misma lógica, sin reescribir documentos, y registra sus controles para que el editor los conserve y permita cambiarlos. Respeta si la extensión estaba encendida en Element Pack, no interviene mientras Element Pack siga activo y marca esos elementos como dinámicos para la caché de Elementor. El país usa la misma cadena que Element Pack (filtros, zona horaria del navegador y consulta por IP con caché), ahora también para la condición País de Display Conditions.

- 4.3.0.81: Display Conditions incorpora las reglas de Visibility Controls de Element Pack como condiciones del mismo motor: usuario específico, sistema operativo, navegador, idioma, país, parámetro y ruta de URL, buscador de origen, tipo de contenido, contenido específico, páginas especiales y resultado de un shortcode; con WooCommerce añade carrito, compras del cliente y estado, tipo, categoría, precio y existencias del producto. Sin JavaScript ni CSS nuevos en el frontend y sin consultas remotas durante el render.


- 4.3.0.79: Comparison List muestra en el editor los CTA de los planes y sus atributos, respeta el máximo de ocho columnas y protege enlaces externos en el frontend.

- 4.3.0.78: Call Out muestra el icono y los atributos del enlace en el editor como en el frontend y añade `noopener noreferrer` a enlaces externos. Sin JavaScript nuevo.

- 4.3.0.77: Botón doble muestra iconos y atributos de los dos enlaces en el editor, y añade `noopener noreferrer` a enlaces externos del frontend. Comprobado en el sitio principal y un subsitio Multisite, sin añadir scripts.

- 4.3.0.76: Breadcrumbs incluye la cadena de padres de un CPT jerárquico usando los permalinks del sitio actual, después del archivo cuando existe. Comprobado con páginas Elementor en el sitio principal y un subsitio Multisite; sin JSON-LD duplicado.

- 4.3.0.75: Brand Carousel deja de asignar carga diferida por el orden de sus logos; WordPress determina el atributo de cada adjunto según la ubicación real del widget. La salida se comprobó en el sitio principal y en un subsitio Multisite, conservando el motor de carrusel compartido.

- 4.3.0.74: añade tipografía independiente y sombra opcional a la Caja de icono avanzada; el Encabezado animado conserva los atributos del enlace en el editor y añade `noopener noreferrer` a enlaces externos en frontend. Brand Grid deja que WordPress decida la carga de imágenes según la posición real en la página. Sin cambios de diseño por defecto ni nuevos scripts.

- 4.3.0.73: permite configurar con controles nativos de Elementor la tipografía del título, antetítulo, fragmento destacado y texto decorativo del Encabezado avanzado por separado. Cada control afecta sólo a la instancia donde se usa; sin ajustes nuevos, se conserva el diseño existente.

- 4.3.0.72: añade al Separador avanzado las variantes propias de cruz y estrella centrales, con tamaño y separación responsivos, SVG decorativo sin JavaScript y vista previa equivalente. La prueba instalada con Elementor comprobó su salida en el sitio principal y un subsitio Multisite con activación de red.

Derivado de [PRO Elements 4.3.0](https://github.com/proelements/proelements/releases/tag/v4.3.0), que incorpora código de Elementor Pro. Se conserva el `license.txt` original con los derechos de Elementor Ltd. y del equipo PRO Elements. El código derivado se distribuye bajo GPLv3 o posterior; `COPYING` contiene la licencia completa. DIGITALÍSIMO no está afiliado con Elementor Ltd. ni con el equipo PRO Elements.

Cambios de DIGITALÍSIMO respecto del ZIP original:

- 4.3.0.71: añade al Botón avanzado controles responsivos de ancho y grosor del borde, color y estilo de borde, tipografía y sombra mediante CSS que Elementor genera sólo cuando se configura el widget. El diseño anterior permanece como valor inicial; no agrega JavaScript ni estilos globales.

- 4.3.0.70: el Acordeón puede abrir inicialmente todos sus paneles cuando se permite apertura múltiple; omite plantillas inexistentes o privadas y cuenta sólo paneles visibles para elegir el inicial. Mantiene el HTML nativo y los recursos condicionales, sin JavaScript adicional.

- 4.3.0.69: el Encabezado animado comparte un solo listener global de tamaño y de movimiento reducido entre todas sus instancias. Libera temporizadores al retirarlas del DOM y permite reinicializarlas si Elementor las inserta de nuevo, sin alterar la primera frase visible ni la rotación.

- 4.3.0.68: alinea la vista previa de la Caja de icono avanzada con el frontend: valida URLs, conserva `target` y `rel` en sus tres tipos de enlace y expone la imagen al lector de pantalla cuando no hay título. No cambia el HTML público existente ni añade JavaScript de frontend.

- 4.3.0.67: el Encabezado avanzado permite ajustar de forma responsiva el tamaño y la posición de su texto decorativo mediante CSS generado por Elementor. Los valores iniciales conservan la apariencia anterior; no se añaden scripts ni otro encabezado semántico.

- 4.3.0.66: el Separador avanzado permite cambiar de forma responsiva el diámetro y la separación del círculo manteniendo sus medidas anteriores por defecto. La vista previa exige un adjunto real de Medios, igual que el frontend. La imagen ya no fuerza `loading="lazy"`; WordPress decide los atributos de carga según el contexto de la página.

- 4.3.0.65: corrige la estructura HTML del Acordeón cuando el título usa H2–H6: el encabezado queda directamente dentro de `<summary>` en frontend y editor, sin un `<span>` envolvente inválido. Conserva el estilo, iconos y funcionamiento nativo.

- 4.3.0.64: corrige la disposición del icono inferior en el Botón avanzado: ahora aparece debajo del texto, como indica el control, y añade una comprobación de CSS y marcado para evitar regresiones.

- 4.3.0.63: da un nombre de grupo único a cada renderizado del Acordeón para que una plantilla repetida no cierre instancias ajenas. La vista previa del Botón avanzado conserva ID, destino externo y `rel` como el frontend. No cambia controles ni IDs de widgets.

- 4.3.0.62: añade Realistic Image Shadow como opción del widget Imagen clásico con `drop-shadow()` nativo, desplazamiento, desenfoque y color. Respeta la silueta alfa, no cambia el marcado ni carga JavaScript y permanece apagado por defecto. No ocupa la extensión de Element Pack; faltan selector personalizado, modo hover y paridad visual.

- 4.3.0.61: añade Text Gradient Background opcional al título Heading clásico. Elementor genera CSS nativo de degradado con colores y ángulo configurables; no cambia el HTML, no agrega scripts y permanece apagado por defecto. No ocupa la extensión de Element Pack; faltan selectores personalizados, widgets adicionales y paridad visual.

- 4.3.0.60: incorpora una base segura de Shape Builder para títulos Heading clásicos. Añade al final una figura decorativa de tamaño estable, forma y color configurables; no reemplaza el contenido, no altera el posicionamiento del widget y no carga GSAP ni SVG externos. No ocupa la extensión de Element Pack; faltan repetidores, otras figuras, animaciones y paridad visual.

- 4.3.0.59: añade una base de Notation para el título del widget Heading clásico: subrayado, línea superior o tachado con color y grosor opcionales. Utiliza CSS nativo y no carga scripts. No altera títulos sin configuración ni ocupa la extensión `bdt-notation`; faltan marcas SVG, repetidores y compatibilidad con otros widgets.

- 4.3.0.58: incorpora Floating Effects opcional para widgets y contenedores Elementor. La animación usa la API nativa del navegador, se carga sólo donde se activa, se pausa fuera de pantalla y respeta la preferencia de movimiento reducido. No ocupa controles ni recursos de Element Pack.

- 4.3.0.57: incorpora Backdrop Filter opcional en widgets y contenedores Elementor, con desenfoque, brillo y saturación generados por controles CSS. Apagado por defecto y sin recursos globales de frontend.

- 4.3.0.56: añade Video Player con `<video>` nativo, portada, proporción estable, controles accesibles y `preload="none"`, sin jPlayer ni JavaScript propio. Rechaza adjuntos que no son video y URLs con esquemas no HTTP(S).

- 4.3.0.55: añade un widget ligero de acceso al registro nativo de WordPress. Sólo aparece cuando el sitio o la red permiten crear cuentas y el visitante no está conectado; no duplica el procesamiento de usuarios ni carga JavaScript.

- 4.3.0.54: completa la política de registro en el control del editor de Login y evita avisos si un documento anterior no guardó `show_register`.

- 4.3.0.53: el widget Login asigna a «Recordarme» un ID único por instancia en frontend y editor. En Multisite, el enlace de registro sigue la política de altas de la red (`user`/`all`); en WordPress individual sigue `users_can_register`.

- 4.3.0.52: añade Total Count con conteos públicos limitados al sitio actual y sin animación ni JavaScript. Corrige la ruta de CSS de Product Grid, QR Code, Table y Tags Cloud y la ruta de los scripts QR locales; añade una prueba que comprueba que todos los assets registrados existen.

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
- 4.3.0.8: conserva la caché y los avisos de actualización de otros plugins cuando Elements consulta sus propias Releases; espera a que WordPress complete el inventario antes de agregar su versión.
- 4.3.0.11: incorpora Enlace animado como widget propio de DIGITALÍSIMO con 15 variantes CSS, controles nativos y registro condicional del estilo. Las reglas CSS y tres trazos SVG decorativos se adaptan del módulo Animated Link de Element Pack Pro 9.9.1 (GPLv3); su procedencia y modificaciones figuran en `docs/third-party-licenses.md` del repositorio. No se carga el plugin Element Pack ni su infraestructura.
- 4.3.0.12: añade un adaptador de lectura para documentos que todavía contienen `bdt-animated-link`. Conserva los selectores CSS originales de la página y nunca toma el ID cuando Element Pack está activo o ya lo registró. No convierte ni sobrescribe `_elementor_data`.
- 4.3.0.13: incorpora Lista destacada como segundo widget propio, con repetidor para texto, imagen, icono y enlace; tres presentaciones, columnas responsivas y CSS condicional. No carga UIkit ni JS. El ID `bdt-fancy-list` no se registra hasta verificar equivalencia visual en Elementor.
- 4.3.0.14: completa la vista previa de Lista destacada en Elementor y añade controles nativos de color, tipografía, relleno, radio e imagen. Corrige la estructura HTML del título y conserva la imagen mediante URL si falta el adjunto de WordPress.
- 4.3.0.15: incorpora Visor de documentos como widget propio, con iframe diferido, enlace alternativo, altura responsiva y URL validada. El visor de Google Docs es opcional; las direcciones privadas permanecen en el navegador. No carga JavaScript ni UIkit y no reemplaza documentos con `bdt-document-viewer`.
- 4.3.0.16: incorpora Carrusel de marcas con motor nativo compartido, navegación accesible, columnas responsivas, logos con ALT y estilos condicionales. El widget nuevo no reemplaza `bdt-brand-carousel` ni modifica carruseles existentes. No carga UIkit ni Swiper.
- 4.3.0.17: incorpora Carrusel de logotipos como segundo consumidor del motor compartido. Permite elegir varias imágenes de Medios en una acción y agregar elementos con enlaces individuales, ALT y altura responsiva; conserva los carruseles anteriores y no ocupa el ID `bdt-logo-carousel`.
- 4.3.0.18: corrige el ancho de las tarjetas cuando hay menos imágenes que columnas configuradas. El ajuste se realiza con CSS desde el número de elementos renderizados, sin esperar a JavaScript; se comprobó en WordPress Playground con Elementor en escritorio, tableta y móvil.
- 4.3.0.19: incorpora Encabezado avanzado como widget independiente. El título usa una sola etiqueta HTML validada, el texto decorativo queda fuera de la lectura accesible y el CSS se solicita sólo al usar el widget. No registra `bdt-advanced-heading` ni altera páginas existentes de Element Pack.
- 4.3.0.20: conserva el espacio de lectura entre el título y el fragmento destacado. Se comprobó el widget en frontend y editor de WordPress Playground con Elementor gratuito, sin errores JavaScript.
- 4.3.0.21: incorpora Cuadrícula de marcas como widget independiente con lista semántica, selección de logos, nombres y enlaces, columnas responsivas y detalles accesibles mediante HTML nativo. Su CSS sólo se carga al usarlo y no requiere JavaScript. No ocupa `bdt-brand-grid` ni altera páginas existentes.
- 4.3.0.22: incorpora Cuadrícula de logotipos con selección múltiple, elementos individuales, tres presentaciones, columnas responsivas y detalles opcionales sin Tippy ni JavaScript. No ocupa `bdt-logo-grid` ni cambia los documentos existentes.
- 4.3.0.23: incorpora Botón de desplazamiento con ancla local accesible, controles de duración y desplazamiento, movimiento reducido cuando el visitante lo solicita y recursos cargados sólo cuando aparece el widget. No ocupa `bdt-scroll-button` ni altera documentos antiguos.
- 4.3.0.24: inicia el bloque de widgets prioritarios con Acordeón, basado en divulgación HTML nativa, contenido saneado, controles Elementor y CSS condicional. No carga UIkit ni JavaScript y no ocupa `bdt-accordion` hasta completar la equivalencia.
- 4.3.0.25: Acordeón admite iconos de título y estado y contenido de plantillas Elementor publicadas; la integración con Anywhere Elementor aparece sólo cuando existe su tipo de contenido. Los selectores se limitan al sitio actual y sus consultas se comparten por petición. No muestra plantillas privadas ni cambia el ID de los documentos existentes.
- 4.3.0.26: añade Botón avanzado independiente con enlace validado, icono, distintivo, tamaños y nueve efectos CSS sin JavaScript. El CSS sólo se carga donde aparece el widget. Conserva el ID `bdt-advanced-button` disponible hasta completar su equivalencia visual y funcional.
- 4.3.0.27: corrige el inicio del Slider Optimizado: el movimiento espera a las imágenes visibles y prioriza la unión del bucle cuando avanza hacia la derecha. Mantiene carga diferida para el resto, conserva las dimensiones y controles existentes y añade un script pequeño sólo en páginas que usan el widget.
- 4.3.0.28: estabiliza la onda repetida con espejo. Las dos series cargan y decodifican todas sus copias antes de animar; sus bordes se unen sin separación, incluso cuando el slider tenía un gap configurado. El resto de modos conserva su carga y separación.
- 4.3.0.29: permite cualquier cantidad de Slider Optimizado en una misma página. Las instancias próximas al viewport esperan sus imágenes y arrancan juntas en el mismo fotograma; las lejanas se preparan al entrar en vista sin retrasar a las visibles. Conserva los modos estático, continuo, espejo, dirección, pausa y movimiento reducido.
- 4.3.0.30: reconstruye el bucle infinito de una imagen repetida con espejo con sólo dos mitades y una copia de cierre. Reduce el ancho de la pista, usa la imagen a ancho completo en cada dispositivo, elimina configuraciones que cortarían su unión y conserva la velocidad equivalente a la versión anterior. Los modos sin espejo, de imágenes múltiples y sin bucle conservan su salida.
- 4.3.0.31: añade Separador avanzado como widget propio, con línea, guiones, puntos, doble línea, círculo, onda SVG y una imagen decorativa de la biblioteca. Su CSS se carga sólo cuando se usa; no solicita JavaScript, UIkit ni archivos de Element Pack. No ocupa `bdt-advanced-divider` ni modifica páginas existentes. Las pruebas de registro incluyen el nuevo widget y su estilo condicional.
- 4.3.0.32: incorpora Duplicador editorial propio. Añade «Duplicar» a entradas, páginas, tipos públicos con interfaz y plantillas Elementor; crea un borrador del sitio actual con contenido, términos y metadatos, omitiendo bloqueos, cachés y la URL canónica de la entrada original. Exige permiso de edición, creación y nonce ligado al sitio; excluye productos y datos transaccionales. No carga código, opciones ni recursos de Element Pack.
- 4.3.0.33: incorpora Caja de icono avanzada como widget propio con icono o imagen, título semántico, subtítulo, descripción, enlaces y distintivo. El CSS se carga sólo en las páginas que usan el widget; no solicita JavaScript, UIkit ni recursos de Element Pack. Conserva `bdt-advanced-icon-box` libre mientras falta verificar paridad visual y controles avanzados.
- 4.3.0.34: incorpora Encabezado animado como widget propio. El primer término se publica visible en HTML; la rotación sólo inicia cuando todas las frases caben en el espacio reservado, tras cargar las fuentes, y se detiene con movimiento reducido. CSS y JavaScript se solicitan sólo en las páginas que usan el widget. No ocupa `bdt-animated-heading`; sus modos typed, split y GSAP siguen pendientes.
- 4.3.0.35: añade Ruta de navegación como widget propio, generada con las URLs y jerarquías de WordPress del sitio actual. No requiere Yoast ni DIGITALÍSIMO SEO, no imprime schema duplicado y sólo solicita su CSS cuando se usa. No ocupa `bdt-breadcrumbs` y mantiene pendiente la paridad visual.
- 4.3.0.36: añade Botón doble y Llamada a la acción como widgets propios. Ambos usan enlaces semánticos, textos saneados, controles de estilo responsivo y CSS condicional sin JavaScript. No registran los IDs antiguos de Element Pack mientras su equivalencia visual y efectos siguen pendientes.
- 4.3.0.37: añade Lista comparativa como tabla semántica con desplazamiento horizontal, y Alternador de contenido con pestañas, navegación por teclado e instancias aisladas. Los recursos se solicitan sólo al usar cada widget. No ocupa los IDs antiguos de Element Pack; sus skins y fuentes de contenido avanzadas siguen pendientes.
- 4.3.0.38: añade Galería personalizada con selección múltiple de Medios, ALT/srcset y enlaces individuales, y Botón creativo con cinco efectos CSS, foco visible y movimiento reducido. Cada widget sólo solicita su CSS al usarse; no registra IDs antiguos de Element Pack.
- 4.3.0.39: añade Slider de dispositivos usando el motor de carrusel compartido y Tarjeta destacada con imagen/icono, título, descripción y CTA semánticos. Ambos cargan sólo sus estilos y el slider reutiliza un único script. Refuerza además los valores predeterminados de los nuevos widgets cuando Elementor aún no entrega un control. No registra IDs antiguos de Element Pack.
- 4.3.0.40: añade Iconos destacados con iconos o texto, nombres accesibles, enlaces seguros, controles responsivos y CSS condicional. Omite enlaces vacíos e iconos sin nombre; no añade JavaScript ni ocupa el ID `bdt-fancy-icons`.
- 4.3.0.41: añade Slider destacado con imagen, título, subtítulo, descripción y CTA por diapositiva. Reutiliza el motor de carrusel propio, solicita sólo los assets utilizados, mantiene la primera imagen sin carga diferida y sanea el contenido. No ocupa `bdt-fancy-slider`.
- 4.3.0.42: añade Pestañas destacadas con icono o imagen, contenido y CTA por pestaña, navegación accesible reutilizada del Alternador de contenido y fallback sin JavaScript. Registra CSS sólo cuando se utiliza y no ocupa `bdt-fancy-tabs`.
- 4.3.0.43: añade Caja destacada con imagen de Medios, diseño sobre imagen o dividido, título, subtítulo, distintivo y botón opcional. Conserva ALT/srcset, no emite enlaces vacíos ni JavaScript, y sólo solicita su CSS al usarse. No ocupa `bdt-featured-box`.

- 4.3.0.44: incorpora Reseñas de Google como widget propio. Consulta Places API (New) desde PHP cuando el bloque entra en pantalla, con clave secreta por sitio o heredada de red, firma aislada por blog, enlace individual a cada reseña y atribución visible. No almacena reseñas, no carga recursos donde no se usa ni ocupa `bdt-google-reviews`. Requiere una clave Places configurada por el administrador.

- 4.3.0.45: incorpora Menú móvil con iconos como widget propio, con cuatro presentaciones, enlaces semánticos, nombres accesibles y CSS condicional. Los nombres emergentes usan CSS sin cargar Popper ni Tippy. No ocupa `bdt-icon-mobile-menu` ni altera documentos existentes.

- 4.3.0.46: incorpora Navegación con iconos como widget propio. Usa enlaces accesibles, marca del sitio en curso y menú WordPress opcional en panel HTML nativo. Los recursos son condicionales y no requiere UIkit, Popper ni Tippy. No ocupa `bdt-iconnav`; las animaciones y skins de referencia siguen pendientes.

- 4.3.0.47: incorpora Notificación como widget propio. Admite tarjeta o barra fija, activación por carga, demora, clic o cursor, cierre accesible, contenido saneado y tiempos limitados; CSS/JS se cargan sólo al usarlo. No ocupa `bdt-notification`. Plantillas, skins y reglas avanzadas de la referencia siguen pendientes.

- 4.3.0.48: incorpora Cuadrícula de productos como widget manual con fichas repetibles, imágenes responsivas de Medios, enlaces explícitos, columnas adaptables y CSS condicional sin JavaScript. Las calificaciones ingresadas por el editor se muestran sólo como texto, sin schema inventado. No consulta WooCommerce ni ocupa `bdt-product-grid`; faltan skins, controles avanzados y paridad visual de la referencia.

- 4.3.0.49: incorpora Código QR como widget propio con contenido o URL pública de la página actual, generador local y recursos condicionales. Usa jquery-qrcode 0.17.0 bajo MIT con su aviso de licencia, sin enviar datos a terceros. El contenido conserva enlace o texto accesible cuando no se genera el canvas. No ocupa `bdt-qrcode`; faltan etiquetas internas e imagen central de la referencia.

- 4.3.0.50: incorpora Tabla de datos como widget propio para CSV estático. Genera encabezados de columna y fila, título opcional, desplazamiento horizontal accesible y CSS condicional sin JavaScript de frontend. Limita filas, columnas y tamaño de entrada; no consulta Google Sheets ni DataTables. No ocupa `bdt-table`; fuentes CSV externas, ACF y skins de referencia siguen pendientes.

- 4.3.0.51: incorpora Nube de etiquetas como widget propio. Consulta términos públicos del sitio actual con límite y orden configurables, enlaces semánticos y CSS condicional sin JavaScript. Evita términos vacíos, taxonomías privadas y schema adicional. No ocupa `bdt-tags-cloud`; paridad visual y runtime real en Multisite siguen pendientes.

Requisitos de esta base: WordPress 6.8+, PHP 7.4+, Elementor gratuito 4.0+ (recomendado 4.3+). Elementor Pro u otra copia de PRO Elements no deben estar activos al mismo tiempo. Funciones como bibliotecas o servicios alojados por Elementor pueden requerir cuenta, conexión o permisos independientes; la licencia GPL del código no concede acceso a dichos servicios.
