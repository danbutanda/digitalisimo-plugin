# DIGITALÍSIMO para WordPress

Siete plugins WordPress independientes y un tema. Cada plugin se instala solo o junto a los demás; cuando hay varios
activos, todos se agrupan bajo el menú **Digitalisimo** mediante un núcleo interno compartido. No hay un plugin Core
que instalar aparte. Todo funciona en WordPress individual y en Multisite, donde la red define valores que cada sitio
hereda o personaliza.

## Plugins y tema

| Plugin | Qué hace |
| --- | --- |
| **DIGITALÍSIMO SEO** | Señales SEO públicas (title, description, canonical, robots, sitemap, schema), keywords y clusters, SEO local, social, SEO AI (robots.txt, llms.txt, IndexNow), rendimiento, auditorías e importación desde Yoast y Rank Math. |
| **DIGITALÍSIMO IA Tools** | Proveedores de IA, chatbot, base de conocimiento (RAG), asistentes editoriales, borradores con chat de artículo e imágenes. |
| **DIGITALÍSIMO Hosting** | Dominios, Namecheap, formularios y flujo de compra de dominio y hosting en WooCommerce. |
| **DIGITALÍSIMO Backups** | Respaldos completos del sitio o de la red hacia almacenamiento local, SFTP, Google Drive u OneDrive, y su restauración. |
| **DIGITALÍSIMO Tools** | Plantillas de red del constructor, WebP, ajustes del sitio (acceso privado, adjuntos, desplazamiento móvil) y editor de fragmentos PHP. |
| **DIGITALÍSIMO Elements** | Constructor visual completo: núcleo de Elementor incluido, funciones avanzadas y widgets propios. |
| **DIGITALÍSIMO Migración** | Migra un sitio o una red a DIGITALÍSIMO Elements con un solo botón; se instala sólo mientras dura la migración. |
| Tema **`digitalisimo-theme`** | Tema clásico mínimo para DIGITALÍSIMO Elements, sin tema hijo: el código PHP propio va al editor de fragmentos de Tools. |

## Carpetas: repositorio, ZIP y sitio

La carpeta y el archivo principal que tiene cada plugin en los sitios no cambian nunca: renombrarlos desactivaría el
plugin instalado. La carpeta del repositorio puede llamarse distinto; el empaquetado pone en el ZIP la del sitio. El
tema se reconoce por su `Update URI`, así que una copia instalada en otra carpeta sigue siendo el mismo tema.

| Carpeta del repositorio | ZIP de la Release | Carpeta y archivo principal en el sitio |
| --- | --- | --- |
| `digitalisimo-seo/` | `digitalisimo-seo-X.zip` | `digitalisimo-seo/digitalisimo-integrations.php` |
| `digitalisimo-ia-tools/` | `digitalisimo-ia-tools-X.zip` | `digitalisimo.chatbot/digitalisimo-chatbot.php` |
| `digitalisimo-hosting/` | `digitalisimo-hosting-X.zip` | `digitalisimo-hosting/digitalisimo-hosting.php` |
| `digitalisimo-backups/` | `digitalisimo-backups-X.zip` | `digitalisimo-backups/digitalisimo-backups.php` |
| `digitalisimo-tools/` | `digitalisimo-tools-X.zip` | `digitalisimo-tools/digitalisimo-tools.php` |
| `digitalisimo-elements/` | `digitalisimo-elements-X.zip` | `digitalisimo-elements/pro-elements.php` |
| `digitalisimo-migration/` | `digitalisimo-migration-X.zip` | `digitalisimo-migration/digitalisimo-migration.php` |
| `digitalisimo-theme/` | `digitalisimo-theme-X.zip` | `wp-content/themes/digitalisimo-theme/style.css` |
| `digitalisimo-elements-installer/` | `digitalisimo-elements-installer.zip` (sin versión) | `digitalisimo-elements-installer/digitalisimo-elements-installer.php` (se borra solo al terminar) |

El instalador de Elements no es uno de los siete plugins: no tiene actualizador, no entra en el índice de la Release y
su versión sólo cambia si cambia él (ver «Instalar Elements en un sitio nuevo»).

Otras carpetas: `mcp/digitalisimo-wordpress/` (servidor MCP local que guarda borradores mediante la API de IA Tools;
ver su [README](mcp/digitalisimo-wordpress/README.md)), `scripts/` (empaquetado, publicación y herramientas de la
fusión), `tests/` (pruebas PHP y Node de los workflows; `tests/playground/` para pruebas en WordPress Playground),
`docs/` y `rollback/` (ZIP de versiones anteriores).

## Actualizaciones y publicación

Cada plugin y el tema traen su propio actualizador, que lee las [Releases del repositorio](https://github.com/danbutanda/digitalisimo-plugin/releases).
Sólo los paquetes modificados suben de versión, pero cada Release lleva los siete plugins y el tema (8 ZIP), cada uno
en su versión vigente, más el índice `digitalisimo-plugins.json` y el instalador de Elements: 10 archivos. Los ZIP se
generan con `scripts/build-packages.sh`; el procedimiento completo está en
[docs/RELEASE_PROCESS.md](docs/RELEASE_PROCESS.md).

## Instalar Elements en un sitio nuevo

El ZIP de DIGITALÍSIMO Elements pesa unos 31 MB, porque lleva el núcleo del constructor sin cambios. Muchos hostings no
dejan subir tanto por **Plugins → Añadir nuevo → Subir plugin** (`upload_max_filesize` y `post_max_size`; el valor
predeterminado de PHP es 2 MB y 8 MB) y WordPress responde «The link you followed has expired». Para esos sitios está el
instalador, de pocos KB, con un enlace que no cambia entre versiones:

**<https://github.com/danbutanda/digitalisimo-plugin/releases/latest/download/digitalisimo-elements-installer.zip>**

1. Descarga ese ZIP y súbelo por Plugins → Añadir nuevo → Subir plugin (en una red, desde la administración de la red).
2. Pulsa «Activar plugin» (o «Activar en la red»). El instalador lleva a su pantalla (Plugins → Instalar DIGITALÍSIMO
   Elements) y empieza solo; si no redirige (activación en bloque), un aviso con botón hace lo mismo.
3. En el servidor, sin límite de subida, descarga de la última Release el ZIP de Elements que indica su índice
   `digitalisimo-plugins.json` (sólo acepta `https://github.com/danbutanda/digitalisimo-plugin/releases/download/<etiqueta>/digitalisimo-elements-<versión>.zip`),
   lo instala con el actualizador de WordPress y lo activa: en toda la red si el instalador se activó en toda la red;
   si no, sólo en ese sitio. Hace falta poder instalar y activar plugins (en una red, el superadministrador).
4. Si todo salió bien, el instalador se desactiva, se borra y muestra «Listo» con el enlace a Elements. Si Elements ya
   estaba instalado, no lo reinstala: lo activa si hace falta, recuerda que se actualiza desde las actualizaciones de
   WordPress y se retira igual.

Si algo falla (el servidor no sale a GitHub, no puede escribir en `wp-content/plugins`, falta memoria o el índice no se
puede leer), la pantalla explica la causa, ofrece «Reintentar» y el instalador se queda. La alternativa manual es subir
el ZIP de Elements de la [última Release](https://github.com/danbutanda/digitalisimo-plugin/releases/latest) con el
administrador de archivos del hosting a `wp-content/plugins/`, extraerlo ahí y activarlo en Plugins.

## Documentación

- [AGENTS.md](AGENTS.md): reglas de arquitectura y de trabajo.
- [docs/RELEASE_PROCESS.md](docs/RELEASE_PROCESS.md): versiones, actualizadores, workflows y Releases.
- [docs/ELEMENTOR_CORE.md](docs/ELEMENTOR_CORE.md): núcleo de Elementor incluido en DIGITALÍSIMO Elements.
- [docs/FUSION_OBJECTIVE.md](docs/FUSION_OBJECTIVE.md): fusión de constructores, tema y editor de fragmentos PHP.
- [docs/PLUGIN_ROADMAP.md](docs/PLUGIN_ROADMAP.md): estado de cada área e historial de publicaciones.
- [docs/BACKUPS.md](docs/BACKUPS.md) y [docs/PERFORMANCE_OBJECTIVE.md](docs/PERFORMANCE_OBJECTIVE.md): objetivos de Backups y del rendimiento de SEO.
