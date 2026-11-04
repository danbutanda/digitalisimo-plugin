= DIGITALÍSIMO Elements =

Constructor visual completo: editor, funciones avanzadas, kits, plantillas y widgets propios en un solo plugin. Requiere WordPress 6.8 o posterior y PHP 7.4 o posterior, y no necesita otros plugins.

Incluye software libre de terceros bajo GPLv3. Sus avisos de copyright y licencias están en license.txt y COPYING; los cambios de DIGITALÍSIMO se describen en DIGITALISIMO_CHANGES.md.

Todo funciona en el sitio: el constructor no se conecta con servicios ni cuentas externas. Sólo los widgets que incrustan un servicio que eliges (un vídeo de YouTube, Vimeo, Dailymotion o Wistia, un mapa de OpenStreetMap, un visor de documentos) lo contactan; con las fachadas activas, el visitante no lo hace hasta pulsar, y el servidor descarga una sola vez la portada o la imagen del mapa para servirla desde el sitio.

== Kits ==

Exporta e importa kits en ZIP con todo lo que añade DIGITALÍSIMO Elements: valores por defecto de los elementos, condiciones completas de las plantillas, separadores y máscaras propios, imágenes, archivos, fuentes e iconos propios incluidos en el kit y enlaces traducidos al sitio nuevo (también los de los textos). También importa kits de terceros, incluidos los que se compran en marketplaces.

* Por defecto sólo agrega: lo que se ve queda en borrador para revisarlo, y los colores y tipografías del kit se añaden sin pisar los del sitio. Los ajustes completos del kit se aplican aparte, con un botón que se puede deshacer.
* Importar nunca instala, activa ni desactiva plugins o temas, ni cambia funciones experimentales: lo que el kit pide sólo se informa.
* Nada de lo importado deja ver un contenido a más personas que en el sitio de origen: si una regla no tiene equivalente, la página queda en borrador con un aviso.
* Volver a importar el mismo kit no duplica nada, y la última importación se puede revertir.
* Lo que el sitio no sabe mostrar se conserva oculto, sin pintar nada en las páginas, y aparece en el informe.
* El kit exportado no lleva correos, nombres de usuario, contraseñas, comentarios ni claves, ni las copias y registros que guarda DIGITALÍSIMO Migración en los documentos migrados.

Se usa desde Herramientas (Importar/Exportar), desde la pantalla «Kits» de DIGITALÍSIMO Elements, con el informe de cada importación, y con «wp digitalisimo-elements kit».

== Rendimiento ==

Las páginas sólo cargan lo que usan: una página hecha con el constructor y los widgets propios pesa unos 380 KB menos de JavaScript y hace cuatro peticiones menos.

* Funciones avanzadas sólo donde hacen falta: los scripts de popups, formularios, menús, carruseles, efectos de movimiento y elementos fijos se cargan en las páginas que los usan. Ante cualquier duda se cargan como siempre, y los contadores de visitas y sesiones de los popups siguen contando en las demás páginas.
* Sin peticiones a CDN en las páginas públicas: el resaltado de código trae sus librerías dentro del plugin, sin cambiar ni un byte, y el editor de código personalizado usa el analizador de WordPress.
* Mejores valores por defecto sólo donde el sitio nunca eligió otra cosa en los ajustes del constructor: el texto se muestra mientras cargan las fuentes de Google. Nunca se cambia un ajuste ya elegido, y la caché de elementos y la carga optimizada de imágenes del constructor se pueden desactivar con un interruptor.
* Iconos en línea (SVG) sin fuentes de iconos: en un sitio que ya existe nunca se aplican solos. Primero se analiza el sitio, se muestra lo que podría depender de las fuentes de iconos y se aplican con un botón que se puede revertir.
* Opcional: Lottie y el resaltado de código pueden cargar su librería al acercarse a la pantalla.
* Opcional: los vídeos de fondo y los del widget Vídeo con reproducción automática (archivos alojados, no YouTube ni Vimeo) pueden esperar a estar a una pantalla de distancia para descargarse; hasta entonces no cuestan ni un byte.
* Opcional, fachadas: el Reproductor de video, el Slider de dispositivos, el Panel lateral, el Mapa y el Visor de documentos muestran una portada con un botón («Reproducir el vídeo», «Ver mapa interactivo», «Ver el documento aquí») en lugar del reproductor, el mapa o el visor de otro sitio. Hasta que el visitante pulsa, la página no pide nada a ese servicio; entonces el vídeo arranca solo y el foco pasa a él. La portada de cada vídeo y la imagen de cada mapa (con su marcador y la atribución de OpenStreetMap) se descargan una sola vez al servidor, en segundo plano, y se sirven desde el sitio; mientras tanto la fachada se ve sin imagen. Sin JavaScript se ve el contenido de siempre.

Todo se ajusta en la pantalla «Rendimiento» de DIGITALÍSIMO Elements, en cada sitio y, en una red, también en la administración de la red, cuyos valores heredan los sitios que no los cambian. Al actualizar, cada sitio vacía una vez los archivos generados del constructor para aplicar los cambios.

== Diseño y visibilidad ==

Más control sobre el aspecto de cada elemento y sobre lo que ve cada visitante, sin cambiar nada de lo que ya existe: todo se activa por elemento o por página, y una página que no lo usa pesa lo mismo que antes.

* Presets de estilo: guarda con un nombre el estilo de un widget o de un contenedor y aplícalo a otros del mismo tipo desde el menú contextual del editor. Sólo llevan estilo (colores, tipografías, fondos, bordes, sombras y espacios), nunca textos, enlaces ni imágenes. Lo que cambies en un elemento siempre gana sobre su preset, y al cambiar un preset cambian todos los elementos que lo usan. Se revisan en Ajustes del sitio → «Presets de estilo» y viajan en los kits con sus colores y tipografías. Sólo un administrador crea, cambia o borra presets, y un preset en uso no se puede borrar.
* Degradados, máscaras y patrones: degradados de más de dos colores, cónicos o repetidos en el fondo de secciones, columnas, contenedores y widgets; máscaras de recorte en secciones, columnas y contenedores, con 12 formas propias o un SVG de la biblioteca de medios; y patrones con 12 teselas propias o un SVG propio, con su color, tamaño, repetición y opacidad. Las formas van incrustadas en el CSS de la página, nunca se piden por URL, y viajan en los kits.
* Condiciones nuevas: cookie, «Visitó una página», campo del contenido o del término, campo del usuario, autor (es el usuario actual o tiene un rol), existencias de un producto concreto (con WooCommerce) y término de búsqueda o búsqueda sin resultados. Deciden qué se muestra, no quién puede leerlo: no protegen contenido. Las que cambian de un visitante a otro sacan la página de la caché de página; las demás no. La condición de cookie sólo la configura quien puede publicar HTML sin filtrar.
* La prioridad de las plantillas: cada cabecera, pie, entrada o archivo del Theme Builder puede llevar una prioridad de 1 a 99 que decide cuál gana cuando varias se aplican a la misma página (gana el número más bajo). Sin prioridad, el orden es el de siempre.
* Sticky «al fijarse»: dentro de un contenedor fijo, un elemento puede verse sólo mientras el contenedor está fijado u ocultarse al fijarse, también a partir del desplazamiento de efectos (por ejemplo, un logotipo más pequeño en la cabecera fijada). Sólo con CSS, sin scripts y sin saltos al cargar.
* Controles de documento: cada página o entrada puede ocultar su imagen destacada (nunca en las listas, en los bucles ni en la imagen para redes sociales) y, con el tema DIGITALÍSIMO, también su cabecera o su pie. Con otro tema, para eso están las condiciones de la plantilla.
* Acciones: en la pestaña Avanzado de cualquier widget, contenedor, sección o columna, un clic, el puntero o el foco, la aparición en pantalla o la carga de la página pueden mostrar u ocultar otro elemento de la misma página, cambiarle una clase o un atributo de una lista corta, llevar hasta él, abrir o cerrar un popup, abrir un panel lateral o cargar más entradas de un Loop Grid. Sin escribir código: las acciones salen de una lista cerrada, funcionan con el teclado y devuelven el foco. Un elemento puede salir «Oculto al cargar» para que lo muestre una acción; sin JavaScript se ve.
* Animaciones de los widgets atómicos: con los widgets atómicos activos, sus interacciones se reproducen sin licencia. Si el sitio ya tenía interacciones guardadas, siguen apagadas hasta pulsar «Reproducir las interacciones guardadas» en el aviso de las pantallas de DIGITALÍSIMO. Con movimiento reducido no se animan.
* Slider anidado ligero: un slider cuyas diapositivas son contenedores, con deslizar, fundido o escala, flechas, puntos y reproducción automática con un botón de pausa visible. Se maneja con el teclado, no se mueve solo con movimiento reducido y sin JavaScript sigue siendo legible y desplazable. No carga librerías de carrusel.
* Contador del slider anidado: opcional, muestra «2 / 5» junto a los puntos o en su lugar. Sin activarlo, el slider es exactamente el mismo de antes.
* Información emergente: en la pestaña Avanzado de cualquier widget, un texto breve que aparece al pasar el puntero, al enfocar con el teclado o al hacer clic, arriba, abajo o a los lados, con su flecha, colores, fondo, borde y tipografía. Se cierra con Escape, se puede recorrer con el puntero sin que desaparezca, describe a los botones y enlaces del widget para los lectores de pantalla y se queda dentro de la ventana en un teléfono.
* Distintivo: una etiqueta («Nuevo», «Oferta»…) con icono opcional en una esquina de cualquier widget, sección, columna o contenedor, con forma de píldora, cinta, etiqueta o un recorte propio, giro y flotación (que se detiene con movimiento reducido). Sin JavaScript, y los lectores de pantalla leen su texto.
* Presentación de pestañas y acordeones: las pestañas y el acordeón anidados pueden tomar un estilo de títulos propio (píldoras, subrayado, recuadro o mínimo) que marca el activo con algo más que el color. Los colores, bordes y medidas que elijas siguen mandando.
* Aplicar a todos: desde el menú contextual de un widget, «Aplicar este estilo a todos los de este tipo» pega su estilo en los demás widgets del mismo tipo de la página, con confirmación y un solo «Deshacer». Nunca cambia textos, enlaces ni acciones.
* Datos dentro del texto: en Encabezado, Editor de texto, Botón, Caja de icono y Llamada a la acción, escribe {post:title}, {post:meta:clave}, {acf:campo}, {user:first_name|visitante}, {site:name} o {date:F Y} en medio del texto; «Insertar dato» los ofrece con su vista previa. Sólo datos de una lista segura: nunca contraseñas, correos, teléfonos ni campos parecidos, y del usuario sólo su nombre y su perfil público. {query:parámetro|valor1,valor2} muestra el valor de esa lista que coincide con la dirección, nunca lo que llega en ella.

Volver a una versión anterior nunca muestra a más visitantes lo que ocultaba una condición nueva: lo condicionado deja de verse hasta que se actualiza otra vez. Los presets, degradados, máscaras y patrones se conservan guardados y vuelven a aplicarse al actualizar. Al volver a una versión anterior a 4.3.0.170, el distintivo, la información emergente y el contador del slider anidado dejan de verse hasta actualizar otra vez. Al volver a una versión anterior a 4.3.0.169 sí se ven tres cambios, que el editor avisa: lo que oculta una acción o «Oculto al cargar» vuelve a verse, los campos condicionales de los formularios reaparecen (y son obligatorios si lo eran) y los datos dentro del texto se imprimen tal cual, entre llaves. Todo vuelve al actualizar.

== Plantillas, consultas y filtros ==

* Consultas guardadas: en Plantillas → Consultas se guardan consultas con nombre (qué entradas, de qué tipo y términos, en qué orden y cuántas) y se prueban antes de usarlas. Pueden relacionarse con la entrada que se muestra o con la de la página: del mismo autor, con los mismos términos, sus hijas o con el mismo valor en un campo. Posts, Portfolio, Loop Grid y Loop Carousel las eligen en «Consulta guardada»; al cambiar la consulta cambian todos los que la usan, y «Desvincular» copia sus ajustes al widget.
* Filtros del Loop Grid: buscador, filtro por campo (un valor, varios o un rango de números o fechas), orden y resultados por página, sin WooCommerce. Filtran un Loop Grid de la misma página sin recargarla, o la lista de un archivo por la dirección, y conviven con los filtros de precio y de atributo. Sólo aplican lo que el administrador configuró en cada filtro.
* Contenido propio en cada plantilla incrustada: en una plantilla de la biblioteca, los encabezados, textos, imágenes, botones, iconos, vídeos y cajas de icono pueden llevar una clave; cada widget Plantilla que la muestra puede cambiar su texto, su imagen o su enlace, u ocultarlo, sin tocar la plantilla ni a las demás páginas que la usan.
* Usos: la lista de plantillas y la de consultas dicen dónde se usa cada una, y lo que está en uso no se puede mover a la papelera ni borrar («En uso»), tampoco desde la biblioteca del editor.

Las consultas y estos ajustes viajan en los kits.

== Sin widgets de PRO ==

Los widgets que DIGITALÍSIMO Elements trae de PRO se sustituyen, familia por familia, por widgets propios más ligeros, con las mismas opciones y el mismo aspecto. Un widget de PRO sustituido deja de ofrecerse en el panel, pero el contenido que ya lo usa se sigue viendo y editando igual, y DIGITALÍSIMO Migración lo convierte al widget propio con «Migrar» (con copia del original y vuelta atrás); un kit que lo trae llega ya convertido.

* Cita, en lugar del Blockquote.
* Carrusel anidado, Carrusel multimedia, Carrusel de testimonios, Reseñas y Diapositivas, en lugar de los carruseles de PRO: un motor propio sin Swiper ni jQuery, con teclado, botón para pausar la reproducción automática y respeto por el movimiento reducido.

== Formularios ==

* Campos condicionales: cada campo del Formulario puede mostrarse sólo si otros campos cumplen unas reglas (es, no es, contiene, está vacío, mayor que, menor que…), todas o alguna. Un campo oculto no se valida, no se guarda y no se envía en el correo ni en los webhooks, aunque el navegador lo mande. Los valores con los que comparan las reglas se ven en el código de la página: no los uses para nada privado.

== Privacidad ==

DIGITALÍSIMO Elements no envía datos de los visitantes fuera del sitio. Sólo escribe estas cookies propias del sitio, y sólo en las páginas que usan una condición o una regla de visibilidad que las necesita:

* digi_seen (en una red, digi_seen_ seguido del número del sitio, con la ruta de ese sitio): las páginas del sitio que abrió el visitante, guardadas como números (hasta 50), para la condición «Visitó una página». Dura 30 días.
* digi_visitor_tz: la zona horaria del navegador, para las condiciones por país. Dura 30 días.
* digi_session: un identificador aleatorio de la sesión del navegador, para contar sesiones. Se borra al cerrar el navegador.
* digi-visit-count-…: cuántas veces vio el visitante un elemento, para las reglas de visibilidad que lo piden. Se borra al cerrar el navegador.
* _digi_…: en cuántas sesiones vio el visitante un elemento, para las reglas de visibilidad que lo piden. Dura un año.

Con WooCommerce, la lista de deseos de un visitante sin sesión vive en el almacenamiento del navegador, no en una cookie:

* digi_wishlist (en una red, digi_wishlist_ con el número del sitio): los productos que el visitante pidió guardar, sólo después de pulsar el botón y como mucho 365 días. Para mostrar la lista, el navegador envía los números de producto al sitio, que no los guarda. Si el visitante inicia sesión y acepta añadirla a su cuenta, la lista se guarda en su cuenta de este sitio hasta que la vacíe, deje de ser usuario del sitio o borre la cuenta, y se puede exportar o borrar desde las herramientas de privacidad. Con la lista de deseos de WooCommerce activa, la cuenta usa la de WooCommerce. Con la WP Consent API, la clave se declara como almacenamiento local de la categoría funcional.

La cookie de «Visitó una página» sólo se escribe en las páginas que alguna condición rastrea y con permiso:

* Con un gestor de consentimiento compatible con la WP Consent API, sólo si el visitante aceptó las cookies de preferencias, y se respeta cuando cambia de opinión.
* Sin la WP Consent API, sólo si el administrador activa «Registrar visitas sin gestor de consentimiento» en la pantalla «Rendimiento» → «Privacidad». Viene apagada; en una red, la red define el valor que heredan los sitios que no lo cambian.
* Sin cookie, «visitó alguna de estas páginas» no se cumple y «no visitó ninguna» sí.
* El filtro `digitalisimo_elements_page_visit_tracking` (recibe `true` y el número de la página) apaga el registro donde se quiera.

El texto sugerido para la política de privacidad, con todas estas cookies, aparece en Ajustes → Privacidad. Al añadir o quitar una página rastreada se purga su caché; si el plugin de caché de página no atiende esa purga, hay que vaciar la caché de esa página a mano.
