=== DIGITALÍSIMO ===
Contributors: digitalisimo
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Tema clásico mínimo para DIGITALÍSIMO Elements.

== Descripción ==

El tema cede la cabecera, el pie, las entradas y los archivos al constructor de temas de DIGITALÍSIMO Elements y
sólo pinta respaldos ligeros (base.css, todo en :where()) cuando no hay plantilla. No tiene pantallas propias, no
encola JavaScript y no imprime señales SEO (title, description, canonical ni schema): de eso se encarga
Digitalisimo SEO.

Registra las cuatro ubicaciones del núcleo (cabecera, pie, entrada y archivo) y diez ubicaciones por gancho
(`digitalisimo_before_header`, `digitalisimo_after_header`, `digitalisimo_before_main_content`,
`digitalisimo_before_entry_title`, `digitalisimo_after_entry_title`, `digitalisimo_after_entry_content`,
`digitalisimo_before_comments`, `digitalisimo_after_main_content`, `digitalisimo_before_footer` y
`digitalisimo_after_footer`).

Al activarse copia lo que el sitio tenía en el tema anterior (logo, menús, CSS adicional y ajustes) sin pisar nada
editado después, vuelve a cargar desde su carpeta las hojas, fuentes y scripts que encolaba un tema hijo (leído sin
ejecutar su PHP) y deja el registro `digitalisimo_theme_switch` y la bitácora `digitalisimo_theme_switch_log`.
`wp digitalisimo-theme status|import|revert` los consulta, repite la importación o vuelve al tema anterior. El tema
nunca se activa solo y se distribuye sin tema hijo: el código PHP propio de cada sitio va a los fragmentos de
DIGITALÍSIMO Tools.

Las actualizaciones llegan desde las Releases del repositorio danbutanda/digitalisimo-plugin. Se ofrecen en Temas
y en Actualizaciones; sólo se instalan solas si activas la actualización automática del tema.

== Changelog ==

= 1.0.2 =
* En paridad declara las constantes y funciones públicas del tema anterior que usan sus temas hijo, así que el
  functions.php del hijo en Código PHP de DIGITALÍSIMO Tools corre igual; sus rutas (get_stylesheet_directory_uri(),
  __DIR__…) apuntan al hijo o a su copia.
* Copia en uploads/digitalisimo-theme/child/ las hojas, fuentes, imágenes y scripts que el hijo encolaba (con sha256) y
  los sirve desde ahí: el hijo y el tema anterior pueden borrarse sin que cambie nada.

= 1.0.1 =
* El paso desde un tema hijo trae lo que su functions.php encolaba (hojas, fuentes y scripts, con su handle, sus
  dependencias, su versión y su prioridad), desde la carpeta del hijo y en el mismo punto de la cascada; lo que necesita
  ejecutar PHP se dice con su archivo y su línea, y su functions.php queda en Código PHP de DIGITALÍSIMO Tools, inactivo.
* El actualizador respeta la espera compartida tras un fallo de GitHub y lee sus cachés con las de los plugins.

= 1.0.0 =
* Primera versión: tema clásico, ubicaciones del constructor, respaldos, paso desde el tema anterior con
  registro y reversión, `wp digitalisimo-theme` y actualizador propio.

== Créditos ==

assets/css/parity/*.css: copia sin cambios de las hojas de Hello Elementor 3.5.1 (GPL-3.0-or-later, © Elementor Ltd.), cargadas sólo en sitios que venían de ese tema.
theme.json: copia de la configuración de Hello Elementor 3.5.1 (GPL-3.0-or-later, © Elementor Ltd.).
