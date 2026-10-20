=== DIGITALÍSIMO ===
Contributors: digitalisimo
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
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
editado después, y deja el registro `digitalisimo_theme_switch` y la bitácora `digitalisimo_theme_switch_log`.
`wp digitalisimo-theme status|import|revert` los consulta, repite la importación o vuelve al tema anterior. El tema
nunca se activa solo y se distribuye sin tema hijo: el código PHP propio de cada sitio va a los fragmentos de
DIGITALÍSIMO Tools.

Las actualizaciones llegan desde las Releases del repositorio danbutanda/digitalisimo-plugin. Se ofrecen en Temas
y en Actualizaciones; sólo se instalan solas si activas la actualización automática del tema.

== Changelog ==

= 1.0.0 =
* Primera versión: tema clásico, ubicaciones del constructor, respaldos, paso desde el tema anterior con
  registro y reversión, `wp digitalisimo-theme` y actualizador propio.

== Créditos ==

assets/css/parity/*.css: copia sin cambios de las hojas de Hello Elementor 3.5.1 (GPL-3.0-or-later, © Elementor Ltd.), cargadas sólo en sitios que venían de ese tema.
theme.json: copia de la configuración de Hello Elementor 3.5.1 (GPL-3.0-or-later, © Elementor Ltd.).
