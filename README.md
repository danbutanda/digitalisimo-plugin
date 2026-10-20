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

Otras carpetas: `mcp/digitalisimo-wordpress/` (servidor MCP local que guarda borradores mediante la API de IA Tools;
ver su [README](mcp/digitalisimo-wordpress/README.md)), `scripts/` (empaquetado, publicación y herramientas de la
fusión), `tests/` (pruebas PHP y Node de los workflows; `tests/playground/` para pruebas en WordPress Playground),
`docs/` y `rollback/` (ZIP de versiones anteriores).

## Actualizaciones y publicación

Cada plugin y el tema traen su propio actualizador, que lee las [Releases del repositorio](https://github.com/danbutanda/digitalisimo-plugin/releases).
Sólo los paquetes modificados suben de versión, pero cada Release lleva los siete plugins y el tema (8 ZIP), cada uno
en su versión vigente. Los ZIP se generan con `scripts/build-packages.sh`; el procedimiento completo está en
[docs/RELEASE_PROCESS.md](docs/RELEASE_PROCESS.md).

## Documentación

- [AGENTS.md](AGENTS.md): reglas de arquitectura y de trabajo.
- [docs/RELEASE_PROCESS.md](docs/RELEASE_PROCESS.md): versiones, actualizadores, workflows y Releases.
- [docs/ELEMENTOR_CORE.md](docs/ELEMENTOR_CORE.md): núcleo de Elementor incluido en DIGITALÍSIMO Elements.
- [docs/FUSION_OBJECTIVE.md](docs/FUSION_OBJECTIVE.md): fusión de constructores, tema y editor de fragmentos PHP.
- [docs/PLUGIN_ROADMAP.md](docs/PLUGIN_ROADMAP.md): estado de cada área e historial de publicaciones.
- [docs/BACKUPS.md](docs/BACKUPS.md) y [docs/PERFORMANCE_OBJECTIVE.md](docs/PERFORMANCE_OBJECTIVE.md): objetivos de Backups y del rendimiento de SEO.
