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
