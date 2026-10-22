= DIGITALÍSIMO Elements =

Constructor visual completo: editor, funciones avanzadas, kits, plantillas y widgets propios en un solo plugin. Requiere WordPress 6.8 o posterior y PHP 7.4 o posterior, y no necesita otros plugins.

Incluye software libre de terceros bajo GPLv3. Sus avisos de copyright y licencias están en license.txt y COPYING; los cambios de DIGITALÍSIMO se describen en DIGITALISIMO_CHANGES.md.

Todo funciona en el sitio: el constructor no se conecta con servicios ni cuentas externas.

== Kits ==

Exporta e importa kits en ZIP con todo lo que añade DIGITALÍSIMO Elements: valores por defecto de los elementos, condiciones completas de las plantillas, separadores y máscaras propios, imágenes, archivos, fuentes e iconos propios incluidos en el kit y enlaces traducidos al sitio nuevo (también los de los textos). También importa kits de terceros, incluidos los que se compran en marketplaces.

* Por defecto sólo agrega: lo que se ve queda en borrador para revisarlo, y los colores y tipografías del kit se añaden sin pisar los del sitio. Los ajustes completos del kit se aplican aparte, con un botón que se puede deshacer.
* Importar nunca instala, activa ni desactiva plugins o temas, ni cambia funciones experimentales: lo que el kit pide sólo se informa.
* Nada de lo importado deja ver un contenido a más personas que en el sitio de origen: si una regla no tiene equivalente, la página queda en borrador con un aviso.
* Volver a importar el mismo kit no duplica nada, y la última importación se puede revertir.
* Lo que el sitio no sabe mostrar se conserva oculto, sin pintar nada en las páginas, y aparece en el informe.
* El kit exportado no lleva correos, nombres de usuario, contraseñas, comentarios ni claves.

Se usa desde Herramientas (Importar/Exportar), desde la pantalla «Kits» de DIGITALÍSIMO Elements, con el informe de cada importación, y con «wp digitalisimo-elements kit».

== Rendimiento ==

Las páginas sólo cargan lo que usan: una página hecha con el constructor y los widgets propios pesa unos 380 KB menos de JavaScript y hace cuatro peticiones menos.

* Funciones avanzadas sólo donde hacen falta: los scripts de popups, formularios, menús, carruseles, efectos de movimiento y elementos fijos se cargan en las páginas que los usan. Ante cualquier duda se cargan como siempre, y los contadores de visitas y sesiones de los popups siguen contando en las demás páginas.
* Sin peticiones a CDN en las páginas públicas: el resaltado de código trae sus librerías dentro del plugin, sin cambiar ni un byte, y el editor de código personalizado usa el analizador de WordPress.
* Mejores valores por defecto sólo donde el sitio nunca eligió otra cosa en los ajustes del constructor: el texto se muestra mientras cargan las fuentes de Google. Nunca se cambia un ajuste ya elegido, y la caché de elementos y la carga optimizada de imágenes del constructor se pueden desactivar con un interruptor.
* Iconos en línea (SVG) sin fuentes de iconos: en un sitio que ya existe nunca se aplican solos. Primero se analiza el sitio, se muestra lo que podría depender de las fuentes de iconos y se aplican con un botón que se puede revertir.
* Opcional: Lottie y el resaltado de código pueden cargar su librería al acercarse a la pantalla.

Todo se ajusta en la pantalla «Rendimiento» de DIGITALÍSIMO Elements, en cada sitio y, en una red, también en la administración de la red, cuyos valores heredan los sitios que no los cambian. Al actualizar, cada sitio vacía una vez los archivos generados del constructor para aplicar los cambios.

== Diseño y visibilidad ==

Más control sobre el aspecto de cada elemento y sobre lo que ve cada visitante, sin cambiar nada de lo que ya existe: todo se activa por elemento o por página, y una página que no lo usa pesa lo mismo que antes.

* Presets de estilo: guarda con un nombre el estilo de un widget o de un contenedor y aplícalo a otros del mismo tipo desde el menú contextual del editor. Sólo llevan estilo (colores, tipografías, fondos, bordes, sombras y espacios), nunca textos, enlaces ni imágenes. Lo que cambies en un elemento siempre gana sobre su preset, y al cambiar un preset cambian todos los elementos que lo usan. Se revisan en Ajustes del sitio → «Presets de estilo» y viajan en los kits con sus colores y tipografías. Sólo un administrador crea, cambia o borra presets, y un preset en uso no se puede borrar.
* Degradados, máscaras y patrones: degradados de más de dos colores, cónicos o repetidos en el fondo de secciones, columnas, contenedores y widgets; máscaras de recorte en secciones, columnas y contenedores, con 12 formas propias o un SVG de la biblioteca de medios; y patrones con 12 teselas propias o un SVG propio, con su color, tamaño, repetición y opacidad. Las formas van incrustadas en el CSS de la página, nunca se piden por URL, y viajan en los kits.
* Condiciones nuevas: cookie, «Visitó una página», campo del contenido o del término, campo del usuario, autor (es el usuario actual o tiene un rol), existencias de un producto concreto (con WooCommerce) y término de búsqueda o búsqueda sin resultados. Deciden qué se muestra, no quién puede leerlo: no protegen contenido. Las que cambian de un visitante a otro sacan la página de la caché de página; las demás no. La condición de cookie sólo la configura quien puede publicar HTML sin filtrar.
* La prioridad de las plantillas: cada cabecera, pie, entrada o archivo del Theme Builder puede llevar una prioridad de 1 a 99 que decide cuál gana cuando varias se aplican a la misma página (gana el número más bajo). Sin prioridad, el orden es el de siempre.
* Sticky «al fijarse»: dentro de un contenedor fijo, un elemento puede verse sólo mientras el contenedor está fijado u ocultarse al fijarse, también a partir del desplazamiento de efectos (por ejemplo, un logotipo más pequeño en la cabecera fijada). Sólo con CSS, sin scripts y sin saltos al cargar.
* Controles de documento: cada página o entrada puede ocultar su imagen destacada (nunca en las listas, en los bucles ni en la imagen para redes sociales) y, con el tema DIGITALÍSIMO, también su cabecera o su pie. Con otro tema, para eso están las condiciones de la plantilla.

Volver a una versión anterior nunca muestra a más visitantes lo que ocultaba una condición nueva: lo condicionado deja de verse hasta que se actualiza otra vez. Los presets, degradados, máscaras y patrones se conservan guardados y vuelven a aplicarse al actualizar.

== Privacidad ==

DIGITALÍSIMO Elements no envía datos de los visitantes fuera del sitio. Sólo escribe estas cookies propias del sitio, y sólo en las páginas que usan una condición o una regla de visibilidad que las necesita:

* digi_seen (en una red, digi_seen_ seguido del número del sitio, con la ruta de ese sitio): las páginas del sitio que abrió el visitante, guardadas como números (hasta 50), para la condición «Visitó una página». Dura 30 días.
* digi_visitor_tz: la zona horaria del navegador, para las condiciones por país. Dura 30 días.
* digi_session: un identificador aleatorio de la sesión del navegador, para contar sesiones. Se borra al cerrar el navegador.
* digi-visit-count-…: cuántas veces vio el visitante un elemento, para las reglas de visibilidad que lo piden. Se borra al cerrar el navegador.
* _digi_…: en cuántas sesiones vio el visitante un elemento, para las reglas de visibilidad que lo piden. Dura un año.

La cookie de «Visitó una página» sólo se escribe en las páginas que alguna condición rastrea y con permiso:

* Con un gestor de consentimiento compatible con la WP Consent API, sólo si el visitante aceptó las cookies de preferencias, y se respeta cuando cambia de opinión.
* Sin la WP Consent API, sólo si el administrador activa «Registrar visitas sin gestor de consentimiento» en la pantalla «Rendimiento» → «Privacidad». Viene apagada; en una red, la red define el valor que heredan los sitios que no lo cambian.
* Sin cookie, «visitó alguna de estas páginas» no se cumple y «no visitó ninguna» sí.
* El filtro `digitalisimo_elements_page_visit_tracking` (recibe `true` y el número de la página) apaga el registro donde se quiera.

El texto sugerido para la política de privacidad, con todas estas cookies, aparece en Ajustes → Privacidad. Al añadir o quitar una página rastreada se purga su caché; si el plugin de caché de página no atiende esa purga, hay que vaciar la caché de esa página a mano.
