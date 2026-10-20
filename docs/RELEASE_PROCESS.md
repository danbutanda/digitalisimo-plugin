# Publicación de los plugins y del tema DIGITALÍSIMO

## Versiones y paquetes

Los siete plugins y el tema se actualizan de forma independiente desde WordPress. Cada entrega de código incrementa la
versión (cabecera PHP y constante; en el tema, ` * Version:` de `style.css` y `DIGITALISIMO_THEME_VERSION`, ver «Versión
del tema») y su expectativa en `tests/validate-suite.mjs` **sólo de los paquetes modificados**; cada versión nueva debe
superar la ya publicada de ese paquete. El ZIP anterior se mueve a `rollback/`.

`scripts/build-packages.sh <directorio> [slugs]` crea un ZIP por plugin y otro para el tema. La carpeta dentro del ZIP es la que tienen
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
| `digitalisimo-theme` | `digitalisimo-theme/` | `wp-content/themes/digitalisimo-theme/style.css` |

El empaquetado aborta si un ZIP no contiene su carpeta y archivo principal de los sitios, si el núcleo de Elementor
incluido no coincide con su manifiesto (ver [ELEMENTOR_CORE.md](ELEMENTOR_CORE.md)) o si el ZIP de DIGITALÍSIMO
Elements contiene o menciona Element Pack (ni sus prefijos `bdt-`, `bdt_` o `ep_`), o si el tema no pasa su guarda (ver «Versión del
tema»): toda la compatibilidad heredada se
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

El tema `digitalisimo-theme` tiene su propio actualizador (`inc/class-updater.php`, `Digitalisimo_Theme_Updater`), con las mismas reglas sobre su entrada en `update_themes`: nunca borra ese transitorio global ni toca entradas de terceros. Ofrece la versión del índice por su `Update URI` (`https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-theme`) con el filtro nativo `update_themes_github.com` y, de respaldo, en `pre_set_site_transient_update_themes` y `site_transient_update_themes`, que además sustituyen una respuesta de wordpress.org con el mismo slug. Sus entradas son **siempre arrays** (`theme`, `new_version`, `package`, `url`, `requires`, `requires_php`, `id`), como las que escribe WordPress. Compara con la versión instalada que lee de su `style.css` (`wp_get_theme()`), no con `DIGITALISIMO_THEME_VERSION`: en la petición que actualiza el propio tema la constante sigue siendo la anterior. Al actualizar conserva la carpeta con `upgrader_source_selection`, y reconoce la copia por `$hook_extra['theme']`, que llega igual con `upgrade()` y con `bulk_upgrade()`. Si no puede dejar la versión nueva en esa carpeta, o si el paquete no lleva su `Update URI` o declara `Template:`, devuelve `WP_Error` antes de que WordPress vacíe otra carpeta o cambie de tema. **Nunca fuerza la actualización automática:** ninguna oferta lleva `autoupdate` y nada filtra `auto_update_theme`, así que decide la casilla de Temas (`auto_update_themes`). Una versión cuyo `requires` o `requires_php` no se cumple lleva `disable_autoupdate`, que sólo sirve para apagarla. Ni una actualización ni una Release activan el tema. Como WordPress no ejecuta el código de un tema que no es la plantilla del sitio, DIGITALÍSIMO Tools (`Digitalisimo\Tools\Theme_Updates`, activable en red) cubre las copias que el actualizador del tema no atiende: inactivas, en otra carpeta o en otros sitios de la red. Para ellas ofrece «Buscar actualizaciones» en la sección «Tema» de su catálogo y, en una red, también en la fila de la copia en Temas de la red. También hace de respaldo de la copia cargada cuando, después de la prioridad 30, no hay una entrada suya. Las dos piezas registran sus filtros en todos los sitios de una red, porque `wp_update_themes()` rehace el inventario de toda la red desde cualquiera de ellos. Sin Tools, en una red sólo se actualiza la copia activa en el sitio principal.

Hay dos excepciones a la regla de la entrada propia:

- `digitalisimo-elements/digitalisimo-sibling-updates.php`: DIGITALÍSIMO Elements cubre la entrada, el enlace y la
  carpeta de cualquier plugin DIGITALÍSIMO que su propio actualizador no atiende (inactivo, porque WordPress no ejecuta
  su código, o con un actualizador antiguo en otra carpeta), y quita las entradas huérfanas que esos actualizadores
  antiguos dejan con la carpeta habitual.
- `Digitalisimo\Tools\Theme_Updates` (`digitalisimo-tools/includes/class-theme-updates.php`): DIGITALÍSIMO Tools escribe
  la entrada de las copias del tema que su actualizador no atiende (inactivas, en otra carpeta o en otros sitios de la
  red) y, sólo si después de la prioridad 30 no hay una entrada suya, la de la copia cargada.

## Workflows

`.github/workflows/validate.yml` corre en cada push y `.github/workflows/release.yml` al llegar a `main` un cambio en
cualquiera de los siete directorios de plugins o en `digitalisimo-theme/`. Los dos ejecutan la misma lista de pruebas (`tests/repo-hygiene.mjs`
comprueba que coincidan y que toda prueba de `tests/` esté en ella); validate prueba además el MCP de
`mcp/digitalisimo-wordpress/`.

La publicación detecta qué plugins cambiaron (`scripts/changed-modules.sh`), comprueba que la versión de cada uno
supera la publicada y crea una Release con etiqueta `vAAAA.MM.DD.N` (`N` es el número de la ejecución). La Release
contiene **el ZIP vigente de los siete plugins y del tema (8 ZIP)** —los modificados con su versión nueva y los demás con la misma versión
que ya tenían— y `digitalisimo-plugins.json` (`scripts/release-manifest.mjs`): la clave `plugins` siempre con siete entradas y la clave
`themes` con la del tema, con `requires` y `requires_php` leídos de su `style.css`. Un índice válido sin `themes`
significa «tema sin publicar» y no provoca consultas. Sus notas indican qué plugins tienen
versión nueva. El workflow falla si falta o sobra un ZIP o si una versión modificada no avanzó.

También puede iniciarse a mano desde GitHub Actions: con slugs separados por espacios (`digitalisimo-theme` para el tema) comprueba que esos
paquetes tienen versión nueva; con la lista vacía vuelve a publicar los 8 ZIP vigentes sin cambiar versiones.

Antes de dar la entrega por terminada, comprobar que **Publicar módulos Digitalisimo** terminó con éxito y que la
Release pública tiene los siete ZIP de plugins y el del tema, con versión nueva en los modificados, y que
`digitalisimo-plugins.json` trae `themes.digitalisimo-theme`: los actualizadores y los catálogos de
plugins de SEO y Tools sólo leen las 50 Releases más recientes. WordPress detecta una actualización cuando la versión
publicada supera la instalada, en su próxima consulta de actualizaciones.

## Versión del tema

Sólo está en dos lugares, que deben coincidir:

- ` * Version:` de `digitalisimo-theme/style.css`, que leen WordPress, `build-packages.sh`, `verify-release.mjs` y `release-manifest.mjs`;
- la constante `DIGITALISIMO_THEME_VERSION` de `functions.php`.

`tests/theme-structure.php` y `tests/validate-suite.mjs` comprueban que coinciden, y en `tests/validate-suite.mjs` también se actualiza `themeModule`. El tema empezó en 1.0.0 y, como un plugin, sólo sube de versión cuando cambia algo dentro de `digitalisimo-theme/`.

`scripts/build-packages.sh` le aplica una guarda propia. Exige su árbol mínimo y prohíbe:

- JavaScript;
- PHP en la raíz fuera de `index`, `header`, `footer`, `comments`, `functions` y `sidebar`;
- `sidebar-*.php` y las carpetas `woocommerce/`, `templates/`, `parts/`, `block-templates/` y `block-template-parts/`;
- `Template:` en `style.css`;
- los dominios de `tests/playground/brand-audit/vendors.json`.

Si algo falla, borra el ZIP; si todo pasa, imprime `THEME PACKAGE CHECK: PASS`. `dist/` y `rollback/` se llevan igual que con los plugins: al subir una versión, `git mv dist/<ZIP anterior> rollback/`, el ZIP nuevo con `bash scripts/build-packages.sh dist <slugs>`, `git add -f` (los ZIP están en `.gitignore`) y `unzip -t`. La primera publicación sólo añade `dist/digitalisimo-theme-1.0.0.zip`, sin nada que mover.

## Núcleo de Elementor

DIGITALÍSIMO Elements incluye el núcleo de Elementor en `digitalisimo-elements/elementor/`. Cada versión de Elementor
(en especial si corrige seguridad) se trae con `bash scripts/sync-elementor-core.sh X.Y.Z`, se valida con
`bash tests/playground/core-switch.sh` y `CORE=bundled bash tests/playground/interaction.sh` y se publica como una
versión de Elements. El procedimiento completo está en [ELEMENTOR_CORE.md](ELEMENTOR_CORE.md).

## Validación local

- Suite completa: `bash scripts/build-packages.sh /tmp/digitalisimo-suite` (8 ZIP y `THEME PACKAGE CHECK: PASS`),
  `DIGITALISIMO_PACKAGE_DIR=/tmp/digitalisimo-suite node tests/validate-suite.mjs` y
  `DIGITALISIMO_PACKAGE_DIR=/tmp/digitalisimo-suite node scripts/verify-release.mjs` (exige los 8), más
  `node tests/release-manifest.mjs`.
- Un plugin: `bash scripts/build-packages.sh /tmp/digitalisimo-release digitalisimo-tools` y
  `DIGITALISIMO_PACKAGE_DIR=/tmp/digitalisimo-release node scripts/verify-release.mjs --remote digitalisimo-tools`;
  para el tema, lo mismo con `digitalisimo-theme`.

Usar directorios vacíos para que los ZIP históricos no se mezclen con una entrega nueva.
