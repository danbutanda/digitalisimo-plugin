# Publicación de los plugins DIGITALÍSIMO

## Versiones y paquetes

Los siete plugins se actualizan de forma independiente desde WordPress. Cada entrega de código incrementa la versión
(cabecera PHP y constante) y su expectativa en `tests/validate-suite.mjs` **sólo de los plugins modificados**; cada
versión nueva debe superar la ya publicada de ese plugin. El ZIP anterior se mueve a `rollback/`.

`scripts/build-packages.sh <directorio> [slugs]` crea un ZIP por plugin. La carpeta dentro del ZIP es la que tienen
los sitios instalados y nunca cambia, aunque la del repositorio se llame distinto (tabla completa en
[README.md](../README.md)):

| Slug del ZIP | Carpeta del repositorio | Carpeta y archivo en los sitios |
| --- | --- | --- |
| `digitalisimo-seo` | `digitalisimo-seo/` | `digitalisimo-seo/digitalisimo-integrations.php` |
| `digitalisimo-ia-tools` | `digitalisimo-ia-tools/` | `digitalisimo.chatbot/digitalisimo-chatbot.php` |
| `digitalisimo-hosting` | `digitalisimo-hosting/` | `digitalisimo-hosting/digitalisimo-hosting.php` |
| `digitalisimo-backups` | `digitalisimo-backups/` | `digitalisimo-backups/digitalisimo-backups.php` |
| `digitalisimo-tools` | `digitalisimo-tools/` | `digitalisimo-tools/digitalisimo-tools.php` |
| `digitalisimo-elements` | `digitalisimo-elements/` | `digitalisimo-elements/pro-elements.php` |
| `digitalisimo-migration` | `digitalisimo-migration/` | `digitalisimo-migration/digitalisimo-migration.php` |

El empaquetado aborta si un ZIP no contiene su carpeta y archivo principal de los sitios, si el núcleo de Elementor
incluido no coincide con su manifiesto (ver [ELEMENTOR_CORE.md](ELEMENTOR_CORE.md)) o si el ZIP de DIGITALÍSIMO
Elements contiene o menciona Element Pack (ni sus prefijos `bdt-`, `bdt_` o `ep_`): toda la compatibilidad heredada se
distribuye en DIGITALÍSIMO Migración, que se instala sólo mientras dura la migración. La copia de referencia de
Element Pack vive en `reference/`, ignorada por Git y fuera de todo ZIP. `scripts/verify-release.mjs` comprueba que
están exactamente los ZIP esperados y que cada uno conserva la carpeta y el archivo principal de los sitios.

## Actualizadores

Cada plugin trae su actualizador; ninguno necesita a otro plugin activo:

| Plugins | Actualizador | Conserva la carpeta instalada |
| --- | --- | --- |
| Elements | `digitalisimo-updater.php` (`Digitalisimo_Elements_Updater`) | Sí |
| Migración | `includes/class-updater.php` | Sí |
| Tools | `includes/class-updater.php` | Sí |
| Backups | `includes/class-digitalisimo-updater.php` (`Digitalisimo_Backups_Updater`) | Sí |
| SEO, Hosting e IA Tools | `includes/class-digitalisimo-updater.php` (`Digitalisimo_Updater`, compartido: se declara una vez y atiende a los tres; las tres copias son idénticas) | Sí |

Reglas que cumplen todos (`tests/updater-*.php`, `tests/*-updater*.php`, `tests/update-enqueue.php`):

- Identifican su plugin por su archivo real (`plugin_basename()`), nunca por la carpeta habitual: un ZIP subido a mano
  con otro nombre de carpeta conserva «Buscar actualizaciones» y sus versiones nuevas, y al actualizar la copia nueva se
  deja en esa misma carpeta (`upgrader_source_selection`).
- Sólo cambian su propia entrada del transitorio `update_plugins`. Si WordPress devuelve `false` o un objeto sin
  `checked`, lo devuelven intacto; una comprobación manual o programada nunca borra el transitorio global ni los avisos
  de terceros (`response`, `no_update`, `checked`, `translations`).
- Leen las versiones del índice `digitalisimo-plugins.json` de la última Release
  (`releases/latest/download/digitalisimo-plugins.json`), sin pasar por la API de GitHub, que limita a 60 consultas por
  hora a cada servidor sin autenticar; la API queda sólo de respaldo, y si GitHub no responde se mantiene el último
  índice conocido.
- «Actualízalo ahora» en Plugins usa `wp.updates.updatePlugin()` en la misma fila: el controlador
  `assets/js/update-in-place.js` es idéntico en los siete (`tests/update-in-place.mjs`), se carga sólo en `plugins.php`
  una vez y cada plugin declara su archivo con `wp_add_inline_script()`. Los plugins de otros desarrolladores quedan
  bajo el comportamiento nativo de WordPress.

La única excepción a la regla de la entrada propia es `digitalisimo-elements/digitalisimo-sibling-updates.php`:
DIGITALÍSIMO Elements cubre la entrada, el enlace y la carpeta de cualquier plugin DIGITALÍSIMO que su propio
actualizador no atiende (inactivo, porque WordPress no ejecuta su código, o con un actualizador antiguo en otra
carpeta), y quita las entradas huérfanas que esos actualizadores antiguos dejan con la carpeta habitual.

## Workflows

`.github/workflows/validate.yml` corre en cada push y `.github/workflows/release.yml` al llegar a `main` un cambio en
cualquiera de los siete directorios de plugins. Los dos ejecutan la misma lista de pruebas (`tests/repo-hygiene.mjs`
comprueba que coincidan y que toda prueba de `tests/` esté en ella); validate prueba además el MCP de
`mcp/digitalisimo-wordpress/`.

La publicación detecta qué plugins cambiaron (`scripts/changed-modules.sh`), comprueba que la versión de cada uno
supera la publicada y crea una Release con etiqueta `vAAAA.MM.DD.N` (`N` es el número de la ejecución). La Release
contiene **el ZIP vigente de los siete plugins** —los modificados con su versión nueva y los demás con la misma versión
que ya tenían— y `digitalisimo-plugins.json` (`scripts/release-manifest.mjs`). Sus notas indican qué plugins tienen
versión nueva. El workflow falla si falta o sobra un ZIP o si una versión modificada no avanzó.

También puede iniciarse a mano desde GitHub Actions: con slugs separados por espacios comprueba que esos plugins
tienen versión nueva; con la lista vacía vuelve a publicar el ZIP vigente de los siete sin cambiar versiones.

Antes de dar la entrega por terminada, comprobar que **Publicar módulos Digitalisimo** terminó con éxito y que la
Release pública tiene los siete ZIP, con versión nueva en los modificados: los actualizadores y los catálogos de
plugins de SEO y Tools sólo leen las 50 Releases más recientes. WordPress detecta una actualización cuando la versión
publicada supera la instalada, en su próxima consulta de actualizaciones.

## Núcleo de Elementor

DIGITALÍSIMO Elements incluye el núcleo de Elementor en `digitalisimo-elements/elementor/`. Cada versión de Elementor
(en especial si corrige seguridad) se trae con `bash scripts/sync-elementor-core.sh X.Y.Z`, se valida con
`bash tests/playground/core-switch.sh` y `CORE=bundled bash tests/playground/interaction.sh` y se publica como una
versión de Elements. El procedimiento completo está en [ELEMENTOR_CORE.md](ELEMENTOR_CORE.md).

## Validación local

- Suite completa: `bash scripts/build-packages.sh /tmp/digitalisimo-suite`,
  `DIGITALISIMO_PACKAGE_DIR=/tmp/digitalisimo-suite node tests/validate-suite.mjs` y
  `DIGITALISIMO_PACKAGE_DIR=/tmp/digitalisimo-suite node scripts/verify-release.mjs`.
- Un plugin: `bash scripts/build-packages.sh /tmp/digitalisimo-release digitalisimo-tools` y
  `DIGITALISIMO_PACKAGE_DIR=/tmp/digitalisimo-release node scripts/verify-release.mjs --remote digitalisimo-tools`.

Usar directorios vacíos para que los ZIP históricos no se mezclen con una entrega nueva.
