# Informe: kits de Elementor en DIGITALÍSIMO Elements (etapa K)

> Diseño de la etapa K, del 20 de octubre de 2026, depurado para el repositorio público. Se escribió leyendo el código de `main@15b2b2d` (Elements 4.3.0.159 con Elementor 4.3.5 en el núcleo incluido), sin ejecutar el importador. Las líneas citadas pueden moverse con cada `sync-elementor-core.sh`: `tests/elements-kit-core-contract.php` comprueba que lo que K usa del núcleo sigue existiendo, y [ELEMENTOR_CORE.md](../ELEMENTOR_CORE.md) dice qué repetir. El estado, las decisiones y la aceptación están en [FUSION_OBJECTIVE.md](../FUSION_OBJECTIVE.md), sección «K»: si algo no coincide, manda el objetivo.

**Abreviaturas de las citas.** `E:` es `digitalisimo-elements/`. `N:` es `digitalisimo-elements/elementor/`, el núcleo incluido, que nunca se edita. `IEC:` es `N:app/modules/import-export-customization/`, el importador 3.0 (REST), que es el que usa la App. `IE:` es `N:app/modules/import-export/`, el importador heredado 2.0 (AJAX y `wp elementor kit`).

## 1. Qué resuelve

Con el importador del núcleo, importar un kit:

- **crea un kit nuevo** y duplica los `_id` de colores y tipografías (`IEC:runners/import/site-settings.php:112-175`);
- **puede cambiar el tema y los experimentos**: el runner de ajustes instala y activa el tema que nombra el kit, y el adaptador de los kits 2.0 vuelve a encender `theme` y `experiments` (`IEC:compatibility/customization.php:56-67`);
- **puede instalar plugins**, en el flujo 2.0;
- **sustituye** el CSS del kit, los puntos de corte y el estilo del tema del sitio;
- **pierde datos propios de Elements**: los valores por defecto de los elementos (en REST no viajan), las condiciones completas del Theme Builder, los separadores y máscaras de `uploads/digitalisimo-elements/`, las referencias por ID de los widgets propios y de los rasgos, los IDs de elemento, las metas de término, los widgets que el sitio no tiene y las instancias de componentes v4;
- **deja medios y enlaces apuntando al sitio de origen**.

K hace que exportar e importar un kit funcione con todas las mejoras de DIGITALÍSIMO Elements, con los kits propios y con los de terceros (Elementor y Elementor Pro en formato 1.x, 2.x o 3.0, y los Template Kits de Envato), **sin otro sistema de kits**, sin tocar el núcleo y sin cambiar nada que el administrador no haya pedido.

## 2. Hechos del núcleo que sostienen el diseño

Cada uno se comprobó en el código. La última columna dice qué hace K con él.

| Hecho | Evidencia | Consecuencia en K |
|---|---|---|
| Un widget de tipo desconocido **viaja crudo** al exportar, pero **se pierde al guardar** el documento importado | Exportar: `N:core/base/document.php:1661-1666` con `N:includes/db.php:268-276`. Guardar: `get_elements_raw_data` salta lo que no puede instanciar (`N:core/base/document.php:1110-1114`) | Marcador `digitalisimo-unconverted` en `elementor/document/save/data` (§9) |
| Las instancias de componentes v4 se quitan al importar mientras el núcleo no admita componentes en kits | `IS_IMPORT_EXPORT_SUPPORTED = false` (`N:modules/components/module.php:48`); `prepare_imported_elements()` quita `e-component` (`:125-129`) en `IEC:processes/import.php:789`; el documento componente no se exporta (`module.php:135-137`) | El marcador las conserva con el motivo `component`, envolviendo en prioridad 5 antes de la captura de `prevent_saving_elements_on_post_creation` en 10 (`IEC:processes/import.php:595-608`) |
| Un widget sin contenido no imprime ni su envoltorio, pero una **sección** sí se imprime vacía | `N:includes/base/widget-base.php:412-414`; `N:includes/base/element-base.php:294-296,527-541` | La sección envoltorio del marcador se oculta con `elementor/frontend/section/should_render` |
| `elementor/document/load/data` corre fuera del guardado y en el front | `N:core/base/document.php:1094-1096`; `N:includes/frontend.php:1161` | Desenvuelve el marcador cuando el tipo ya existe, sólo en documentos con marcadores |
| La acción `import-kit` sólo se dispara en `POST /import`; cada `POST /import-runner` reconstruye el proceso desde la sesión sin dispararla | `IEC:module.php:385-405,422-434` | K activa su contexto en cada petición por la ruta REST, no sólo en la acción |
| `rest_request_before_callbacks` corre **antes** del `permission_callback` | `wp-includes/rest-api/class-wp-rest-server.php` (WordPress 7.1) | K exige `manage_options` por su cuenta antes de activar nada |
| WordPress casa las rutas REST sin distinguir mayúsculas | `class-wp-rest-server.php` (patrón `@^…$@i`) | K normaliza la ruta (minúsculas y sin barra final) antes de compararla |
| El último runner es el único que guarda, y devuelve los datos importados, no `status` | `IEC:processes/import.php:383-387`; `IEC:module.php:429-430` | El runner final de K se registra con prioridad `PHP_INT_MAX`; las pruebas miden `failed`, no `success` |
| La App acepta un origen propio por `#/import-customization?referrer=<origen>&id=<token>` | `IEC:data/routes/upload.php:52-56` (`elementor/import/kit/result/<origen>`) | Es la vía de la pantalla «Kits» en modo «nuevo», de la biblioteca y de WP-CLI |
| Con `source`, la subida borra `dirname( file_name )` | `IEC:data/routes/upload.php:105-107` | El kit preparado vive en `tmp/<token>/kit/kit.zip`: el núcleo borra sólo `kit/` |
| `process-media` descarga las URL que recibe y las sube a la nube si hay conexión | `IEC:data/routes/process-media.php:26-70` | K responde 403 siempre: importar un kit local no la usa |
| `Media_Mapper` sólo se fija si el origen devuelve `media_file_name`; la importación local mueve el archivo y no anota el hash | `IEC:data/routes/upload.php:82-84,127-145`; `N:includes/template-library/classes/class-import-images.php:231-260` | K no usa ese camino: sirve sus medios con un proxy en `pre_http_request`, y el camino nativo busca por hash, descarga y anota el hash solo (`class-import-images.php:64-80,133,203`) |
| Los adjuntos del WXR se piden **en flujo** a un archivo y se rechazan si quedan vacíos o con otro tamaño | `N:core/utils/import-export/wp-import.php:1063-1070,1096-1108` | El proxy, con `stream`, escribe el archivo en `filename` con el tamaño exacto |
| El WXR no deduplica: sólo omite un tipo inexistente, un ID ya procesado y `auto-draft`, e inserta siempre; los adjuntos se descargan siempre | `wp-import.php:591-614,686,996-1039` | K deduplica con `wp_import_posts` y la procedencia |
| El WXR añade todas las metas salvo tres claves; kses sólo corre en `Document::save()` | `wp-import.php:827-849,1289-1296`; `N:core/base/document.php:856-858` | Lista negra de metas (§6), nunca una lista blanca, que borraría las de WooCommerce, ACF o SEO |
| Los elementos de menú salen del bucle antes de `wp_import_post_meta`, y `_menu_item_classes` pasa por `maybe_unserialize` | `wp-import.php:617-621,827,956` | El filtro principal de K para el WXR es `wp_import_post_data_raw` (`:600`), el único que ve todo |
| Hay filtros previos para frenar objetos serializados | `wp_import_term_meta` (`wp-import.php:546`), `wp_import_post_comments` (`:772`), `wp_import_post_meta` (`:827`) | Segunda comprobación, además de `wp_import_post_data_raw` |
| El WXR exportado lleva datos personales | `N:core/utils/import-export/wp-exporter.php:355-359,515,524-547,552-560` (autor, correo, contraseña de la entrada, comentarios con correo e IP) | K anonimiza el XML al exportar |
| La exportación REST pasa el ZIP entero a base64 | `IEC:data/routes/export.php:47-71`; `IEC:processes/export.php:345-381` | Tope de medios incrustados en la App; WP-CLI añade los medios después con `addFile` |
| El núcleo exporta sólo 20 entradas por tipo y sólo lo publicado | `IEC:runners/export/wp-content.php:81,92`; `elementor-content.php:68-69` | K quita el límite y anota lo no exportado (borradores, privados, variaciones) |
| Al exportar, los IDs de elemento se regeneran; el filtro `replace_id` ya recibe el ID nuevo | `N:core/base/document.php:1655-1659`; la importación no los regenera (`:1730-1738`) | K repone el ID original recorriendo en paralelo el árbol exportado y el del documento |
| `Theme_Document::import()` sólo usa la primera condición y guarda los `sub_id` del origen sin traducir | `E:modules/theme-builder/documents/theme-document.php:241-283` | Las condiciones completas viajan aparte, en `digitalisimo/theme-builder.json` |
| Loop Grid sólo traduce `template_id`; `alternate_templates.*.template_id` y el `template_id` de Loop Carousel no se traducen | `E:modules/loop-builder/widgets/loop-grid.php:310-327,596-602`; `E:modules/loop-builder/widgets/base.php:119-123`; `Template_Query` extiende `Control_Select2` (`E:modules/query-control/controls/template-query.php:10`) y `On_Import_Trait` no lo ve (`E:base/on-import-trait.php:56-58`) | K declara las dos referencias |
| La reversión nativa del kit borra `imported_kit_id` y aborta si `active_kit_id` no es un kit | `N:core/kits/manager.php:219-237` | K escribe siempre en el kit activo y deja metadatos nativos inertes (`active_kit_id = 0`), así la reversión nativa no borra nada |
| La reversión manda los documentos a la papelera y borra de forma definitiva el contenido del WXR y los adjuntos de la sesión | `N:core/base/document.php:1290-1297`; `IEC:runners/revert/wp-content.php:41-45`; `IEC:processes/revert.php:73,157-175` | Como el núcleo: el informe lista lo que fue a la papelera |
| `wp elementor kit` lo registran los dos módulos e importa con el 2.0; descarga y cambia la opción de subida sin filtrar **antes** de cualquier acción | `IEC:module.php:102`; `IE:module.php:100`; `IEC:wp-cli.php:120-170` | Guarda en `before_invoke`, antes de descargar o de tocar la opción |
| El AJAX heredado sólo captura `\Error` y `admin_post_elementor_revert_kit` no captura nada | `IE:module.php:451-458,574-621` | Cada camino heredado tiene su guarda (§7) |
| Las casillas de personalización fina dependen del «plan» | JS: `plan_type` (`E:core/app/modules/import-export-customization/assets/js/hooks/use-tier.js:1-4`, fijado en `E:plugin.php:388-390`); servidor: `Utils::is_high_tier()` (`E:core/app/modules/import-export-customization/utils.php:12-19`) | Se habilitan en origen; el servidor sigue forzando `theme` y `experiments` a `false` |
| Las opciones de sesión del 3.0 son `elementor_import_sessions` y **`elementor_revert_sessions`** | `IEC:module.php:53,55` | Son la fuente única del estado de cada importación |

## 3. Formato del kit

El ZIP sigue siendo un **kit 3.0 del núcleo**: cualquier importador lo lee, y los que no conocen K sólo extraen `json` y `xml` (`IEC:processes/import.php:618`) y leen las claves que conocen. Lo propio va en una clave y una carpeta:

```
manifest.json                    claves nativas + "digitalisimo": { v, elements, core, home, site, uploads, kit,
                                   parts: { <clave>: <versión> }, widgets: { <tipo>: n }, needs, media, not_exported }
digitalisimo/<parte>.json        una por parte con datos
digitalisimo/theme-builder.json  condiciones completas por plantilla: { "<id origen>": { conditions, fields } }
digitalisimo/media.json          { "<url original>": { file: "<sha256>.<ext>", sha256, mime, bytes } }
digitalisimo/media/<sha256>.<ext>
digitalisimo/files.json          { "shapes/<slug>.svg": { sha256, bytes }, "masks/<slug>.svg": … }
digitalisimo/files/(shapes|masks)/<slug>.svg
digitalisimo/urls.json           { "<tipo>:<id origen>": "<ruta relativa del permalink>" }
```

- `manifest.digitalisimo.site` es `get_site_url()`, que puede no ser `home`. Un kit de terceros sólo trae `site`.
- **Formas válidas**, comprobadas en el guardián (lo demás rechaza el kit): `file` de `media.json`, `^[0-9a-f]{64}\.(jpg|jpeg|png|gif|webp|avif|svg|mp4|webm|mp3|ogg|wav|woff|woff2|ttf|otf|eot|csv|pdf|json|css)$` (`css` y `eot` sólo los usan los archivos de un conjunto de iconos); claves de `files.json`, `^(shapes|masks)/[a-z0-9]+(?:-[a-z0-9]+)*\.svg$`; claves de `urls.json`, `^[a-z0-9_-]+:[0-9]+$`; claves de `theme-builder.json`, `^[0-9]+$`. Están en `Module::*_PATTERN`. Un Template Kit de Envato llega con claves de texto (`header`, `home-2`): el normalizador las renumera antes de comprobar el esquema (`Normalizer::renumber()`), así que el patrón numérico sigue valiendo.
- **Nada del kit decide una ruta de disco.** K construye cada destino con el slug o el sha256 ya validados y comprueba con `realpath` que queda dentro de su carpeta.
- Un kit sin la clave `digitalisimo` es de terceros: se importa igual, sin las partes. Un kit de K en un sitio sin Elements pierde los widgets propios y la carpeta `digitalisimo/` se ignora.

## 4. Modos, estados y reimportación

- **«Agregar» (sólo agregar) es el modo por defecto, también en un sitio vacío.** No aplica los ajustes del kit: los colores y tipografías que usa el contenido se añaden como personalizados con un `_id` estable (`k` + 6 hex de `sha1( kit | _id | valor )`), y el resto queda en el respaldo hasta «Aplicar los ajustes del kit». Las condiciones del Theme Builder existentes no cambian, y la portada (`page_on_front`, `show_on_front`) tampoco.
- **«Nuevo»** sólo si el administrador lo pide (casilla de la pantalla «Kits» o `--mode=fresh`) **y** el sitio está vacío: sin entradas de tipos públicos salvo las tres que crea WordPress, sin productos, sin plantillas salvo el kit, con `show_on_front = posts` y sin menús. Si no está vacío, se rechaza con el motivo. Un Template Kit de Envato entra siempre en «agregar».
- **Siempre en el kit activo**, con respaldo; nunca se crea un kit nuevo.
- **Nunca** se instalan, activan ni desactivan plugins o temas, ni se cambian experimentos, en ningún modo. Lo que el kit pedía se informa.
- **Estados en «agregar»:** queda en borrador lo que se ve sin que nadie lo enlace (entradas de tipos visibles, plantillas con ubicación propia —cabecera, pie, entrada única, archivo…— o con ubicación y condiciones, popups y botones flotantes). Una sección es del grupo Theme Builder del núcleo pero no tiene ubicación propia: conserva su estado salvo que traiga ubicación y condiciones (`Session::is_theme_template()` lee la propiedad `location` del tipo; `Finish` cierra las secciones con ubicación). Conservan su estado las plantillas que sólo se ven incrustadas, fuentes, iconos, adjuntos, elementos de menú y demás tipos no visibles. **Siempre en borrador, en los dos modos:** los fragmentos de Custom Code y todo documento con una referencia de acceso sin destino (§8.4).
- **Reimportación:** lo creado por el mismo kit se reconoce por `_digitalisimo_import_origin` **en cualquier estado**. Si ni el origen ni la copia local cambiaron, se reutiliza; si cambió el origen, se crea un borrador «actualización»; si se editó la copia local, un borrador «conflicto». **Nunca se actualiza en el sitio** lo ya importado.

## 5. Contextos y ganchos

K no añade rutas propias al importador: engancha el 3.0 con filtros públicos de WordPress y del núcleo, que sólo viven mientras dura una importación o una exportación.

**Activación.** En `rest_request_before_callbacks`, si la ruta normalizada es `/elementor/v1/import-export-customization/(upload|import|import-runner|revert)`; desde WP-CLI; desde el `admin-post` de la pantalla «Kits» y desde la redirección de la biblioteca. Antes se exige `manage_options`: sin esa capacidad no se toca nada y la ruta devuelve su 403. Se desactiva en `rest_request_after_callbacks` o al final del comando.

**Importación**

| Gancho | Prio. | Qué hace |
|---|---|---|
| `pre_http_request` | 1 | **Proxy de medios.** Sólo responde a URL de `media.json` con el prefijo `uploads` o `home` del origen, y sólo si quien llama es el importador. Con `stream`, copia el archivo a `filename`. Sanea los SVG. **Bloquea**, con los dos núcleos, los dominios del proveedor (`Digitalisimo_Vendor_Free::vendor_url()`) y `*.wordpress.org`. Lo demás sale dentro de los presupuestos de red |
| `http_request_args` | 10 | `timeout` ≤ 20 s, `redirection` ≤ 3 y tope de tamaño por archivo |
| `import_attachment_size_limit` | 10 | Tope por archivo del WXR |
| `elementor/import/get_default_settings_conflicts` | 99 | Sin conflictos de ajustes por defecto |
| `wp_insert_post_data` | 99 | Estados de §4 en lo creado por la sesión |
| `wp_import_posts` | 10 | Deduplicación del WXR por procedencia |
| `wp_import_post_data_raw` | 10 | Quita los serializados con objetos y la lista negra de §6 en las metas de toda entrada (también elementos de menú y metas de comentarios); reescribe el prefijo `home` del origen en `_menu_item_url` |
| `wp_import_post_data_processed` | 10 | Estados en «agregar» y el prefijo `home` del origen en `post_content` |
| `wp_import_post_meta`, `wp_import_term_meta`, `wp_import_post_comments` | 10 | Segunda comprobación de objetos, lista negra en metas de término y comentarios sin correo ni IP |
| `wp_import_insert_post`, `add_attachment` | 10 | Procedencia en lo que crea el WXR |
| `pre_update_option_page_on_front`, `pre_update_option_show_on_front` | 10 | En «agregar», conservan el valor y anotan «el kit trae portada» |
| `elementor/document/save/data` | 5 | Marcador para lo desconocido y los componentes v4 (§9) |
| `elementor/document/save/data` | 20 | Remapeo (§8), una sola vez por documento creado en la sesión |

En «agregar» se saltan además los runners v4 (`global-variables`, `global-classes`, `default-styles`) sin sobrescribir nada, y el respaldo incluye `_elementor_global_variables` y las clases globales.

**Exportación** (se activa en `export-kit`, prioridad 20)

| Gancho | Qué hace |
|---|---|
| `wxr_export_skip_postmeta`, `wxr_export_skip_termmeta` | Lista negra de §6 |
| `elementor/import-export-customization/export/wp-content/query-args/customization` | Sin el límite de 20 por tipo |
| `wp_get_attachment_image_src` | La miniatura del manifiesto en tamaño completo |
| `elementor/templates/collect_media_url` | Medios incrustados |

**Ganchos de carga** (coste cero fuera de una importación): `elementor/import-export-customization/{import,export,revert}-kit` y su `runner/after-run`, `elementor/import/kit/result/digitalisimo`, `elementor/kit/meta_to_preserve_on_kit_import`, `elementor/template_library/sources/local/import/elements` (marcador en plantillas sueltas) y `wp_ajax_elementor_library_direct_actions` (prioridad 5, ZIP de kit en la biblioteca). El widget marcador y `elementor/document/load/data` sólo se registran si el sitio tiene marcadores (la opción `digitalisimo_elements_unconverted`, con carga automática, es un entero), si hay una importación en curso o si se importan plantillas.

## 6. Seguridad de las entradas

**Guardián del ZIP** (sin extraer nada a disco):

- Rechaza entradas con `..`, ruta absoluta, `\`, NUL, nombres duplicados sin distinguir mayúsculas y enlaces simbólicos.
- Dentro del kit sólo admite `json` y `xml`, las extensiones de §3 bajo `digitalisimo/` y capturas `jpg`, `png` y `webp` en la raíz. La documentación de un ZIP de marketplace fuera del kit se ignora. `php`, `phtml`, `phar`, `php3`…`php8`, `cgi`, `pl`, `py`, `sh` y `.htaccess` rechazan el kit entero, estén donde estén.
- Límites (filtro `digitalisimo_elements_kit_guard_limits`): 512 MB descomprimido, 5 000 entradas, relación 100:1 en entradas de más de 1 MB, JSON ≤ 32 MB con profundidad ≤ 256, XML ≤ 64 MB sin `<!DOCTYPE` ni `<!ENTITY`, un solo nivel de ZIP anidado. El tamaño real se comprueba al leer. Por sesión: 512 MB de descargas, 20 s por descarga, 3 redirecciones y 20 s de red acumulados por petición; además, pasados 15 s de una petición (`request_media`) no se descarga otro medio, porque el núcleo procesa cada imagen después de bajarla: lo que no cabe queda «no descargado (tiempo)» (`media.time`). Los medios que viajan en el kit no cuentan (se sirven sin red), y en WP-CLI sólo cuenta el tiempo de red.
- Cada JSON pasa un esquema mínimo, porque un tipo erróneo rompe la petición final.
- Sin `ZipArchive`, se detiene con un mensaje claro.

**Normalizador:** apaga lo que el kit pide (tema, experimentos y plugins pasan al análisis), quita la identidad del sitio (`site_name`, `site_description`, `site_logo`, `site_favicon`), limpia `metadata` (claves protegidas fuera, serializados con objetos fuera, URL del origen a `home_url()`), aplica `sanitize_key` y `basename` a las rutas del manifiesto, comprueba el sha256 y el tipo real de cada medio y sanea los SVG.

**Lista negra de metas**, al exportar y al importar (todo lo demás pasa):

- cachés y estado efímero: `_edit_lock`, `_edit_last`, `_wp_old_slug`, `_elementor_css`, `_elementor_element_cache`, `_elementor_page_assets`, `_elementor_controls_usage`;
- sesión y procedencia: `_elementor_import_session_id`, `_digitalisimo_import_origin`, `_digitalisimo_unconverted`;
- datos del constructor dentro del WXR (el exportador nativo nunca los pone ahí; si llegan, es para saltarse kses): `_elementor_data`, `_elementor_page_settings`, `_elementor_edit_mode`, `_elementor_template_type`, `_elementor_conditions`, `_elementor_version`, `_elementor_pro_version`;
- plantillas de red de Tools: `_digitalisimo_tools_network_*`.

**Datos personales y secretos.** El WXR exportado vacía `author_email`, nombres del autor y `post_password`, cambia `author_login` por `autor-<n>` (también en `<dc:creator>` y en `<guid>`, donde el exportador del núcleo escribe el login) y no lleva comentarios. El `author` del manifiesto (el nombre visible de quien creó el kit activo) sale vacío: durante la exportación, `get_the_author_display_name` devuelve ''. Tampoco viajan las URL de webhooks de formularios, la clave de Google Places ni las opciones `digitalisimo_elements_vendor_free` y `digitalisimo_elements_core_mode`.

**Temporales** en `uploads/digitalisimo-elements/tmp/<64 hex>/`, con `index.php` vacío y `.htaccess` que deniega todo. La sesión guarda la carpeta **relativa** a `uploads`, así un sitio clonado resuelve la suya. Antes de usarla o borrarla se comprueba con `realpath` que queda dentro de `tmp/`; se borra al terminar y con el evento único `digitalisimo_elements_kits_cleanup` a las 24 h, que recibe el ID de sesión, nunca una ruta.

**Medios incrustados al exportar:** sólo adjuntos del sitio actual (`attachment_url_to_postid()` → `get_attached_file()` → `realpath` dentro de `uploads`, con extensión y MIME permitidos; fuentes y SVG, que WordPress no lista, con el MIME de `Parts\Media::MIMES`) y los archivos de cada conjunto de iconos (`uploads/elementor/custom-icons/<conjunto>/`, que no son adjuntos: `realpath` dentro de esa carpeta y sólo `css`, `json`, `woff`, `woff2`, `ttf`, `otf`, `eot` y `svg`), nunca por HTTP ni de otro sitio de la red. Esos archivos de iconos sólo los sirve el proxy al importador de iconos: la parte `media` nunca crea adjuntos con ellos. Se recogen también los `href`/`src` bajo `uploads` de los textos enriquecidos y la `wp.url` de los widgets de medios de WordPress. Tope de 20 MB por archivo y, en la App, de `min(32 MB, memory_limit / 8)` en total (filtro `digitalisimo_elements_kit_media_limits`); WP-CLI no tiene tope total.

## 7. Importador: runners, flujos heredados y reversión

**Runners sustituidos por nombre** (`register()` reemplaza en su sitio):

| Nombre | Qué cambia |
|---|---|
| `site-settings` | Escribe en el kit activo con `$kit->save()` (kses y ganchos de guardado), sin identidad, sin tema y sin experimentos. En «nuevo» importa antes los medios de los ajustes del kit; en «agregar» sólo añade colores y tipografías |
| `plugins` | Sólo informa. Ejecuta `Early_Part::import_early()` (§10) |
| `templates` | Procedencia antes de crear. Un tipo de documento no registrado entra como plantilla `section` con `_digitalisimo_original_type` |
| `elementor-content` | Procedencia. El contenido de tipos de entrada que el sitio no tiene entra como plantilla `section` en borrador |
| `taxonomies` | Menús del mismo kit sin crear `-duplicate`; taxonomías inexistentes informadas |
| `floating-elements` | En «agregar», condiciones sólo en los importados (el nativo borra las de todos) |
| `woocommerce-settings` | En «agregar», informa sin cambiar las páginas de WooCommerce |
| `elements-default-values` | Parte `element-defaults`: reemplaza en «nuevo» y fusiona por tipo en «agregar» |

El runner final `digitalisimo` (prioridad `PHP_INT_MAX`) importa las partes en la petición final, antes de guardar los documentos, anota el tipo original que traiga `manifest.templates[*].original_type` en lo que haya creado cualquier runner, y en `after-run` escribe la procedencia, aplica las condiciones cerradas del Theme Builder y de Custom Code, deja en borrador lo que debe revisarse, limpia la caché de archivos, recalcula los marcadores, cierra el informe y dispara `digitalisimo_elements_kit_imported`.

**Flujos heredados (2.0)**, sólo con el núcleo incluido; con el plugin Elementor original activo siguen como él los hace:

| Camino | Comportamiento |
|---|---|
| App `#/import`, `#/export` y `#/kit-library` | `vendor-free.js` los lleva a `#/import-customization` y `#/export-customization` |
| AJAX `elementor_import_kit`, `elementor_import_kit__runner`, `elementor_export_kit` | 409 con un mensaje que manda a Herramientas |
| `wp elementor kit import\|export\|revert` | Error en `before_invoke`, antes de descargar o de cambiar la opción de subida; manda a `wp digitalisimo-elements kit` |
| `wp elementor library import\|import-dir` | Rechaza una URL; un ZIP con `manifest.json` manda a `wp digitalisimo-elements kit import` |
| `admin_post_elementor_revert_kit` | `wp_die()` con el mensaje y un enlace |

**Reversión.** Sólo la última importación, como el núcleo. Con el kit sin editar, restaura los ajustes, las metas del kit y los datos v4 del respaldo; si se editó, quita sólo lo que añadió y lo anota. Los documentos van a la papelera y los adjuntos de la sesión se borran; lo reutilizado no lleva la meta de la sesión y no se toca. Una sesión anterior a K delega en el nativo.

**Correcciones en derivados de PRO** (en origen y anotadas en `E:DIGITALISIMO_CHANGES.md`; ninguna toca el núcleo ni el render):

| Archivo | Cambio | Riesgo que cierra |
|---|---|---|
| `E:modules/assets-manager/asset-types/fonts/import-export-customization/import-runner.php` | `font_face` se regenera desde los archivos importados; duplicados por procedencia | XSS almacenado y duplicados |
| `E:modules/assets-manager/asset-types/import-export/traits/external-attachment-trait.php` | Sin archivo local, descarga por el proxy y crea un adjunto local | Fuentes servidas desde el dominio del autor |
| `E:modules/assets-manager/asset-types/icons/import-export-customization/import-runner.php` | Reconstruye la configuración del conjunto desde los archivos locales; nunca guarda URL externas | Ruta arbitraria; CSS y fuentes de otro host |
| `E:modules/assets-manager/asset-types/icons/custom-icons.php` | `rmdir` sólo dentro de `uploads/elementor/custom-icons` | Borrado arbitrario de carpetas |
| `E:modules/custom-code/import-export-customization/{export,import}-runner.php` | Exportación e importación simétricas (ubicación, prioridad, dispositivos, roles, idiomas, condiciones); sin `unfiltered_html` no se importa; siempre en borrador | Fragmentos que no se imprimen o se duplican |
| `E:modules/theme-builder/import-export-customization/import.php` y `E:modules/global-widget/import-export-customization/import.php` | Consultan `digitalisimo_elements_kit_existing_document` antes de crear; reutilizan la miniatura que el mismo kit ya importó | Duplicados (también de miniaturas al reimportar) |
| `E:modules/global-widget/widgets/global-widget.php` y `E:modules/global-widget/module.php` | El widget global nunca se toma a sí mismo como original; el guardado no deja `originalWidgetType = global` | Recursión infinita que agotaba la memoria con un kit de terceros (R3) |
| `E:modules/global-widget/import-export-customization/import.php` | Une el resultado conservando los IDs de origen como claves | El mapa perdía la plantilla de cada instancia global |
| `E:modules/assets-manager/asset-types/icons/import-export-customization/{export,revert}-runner.php` | La exportación incluye los archivos de la carpeta del conjunto; revertir rehace la caché de conjuntos | Iconos pedidos al sitio de origen; caché de iconos distinta tras revertir |
| `E:modules/forms/actions/webhook.php` | `on_export()` quita los webhooks y lo anota | Una URL privada en el kit |
| `E:core/app/modules/import-export-customization/runners/import/site-settings.php` | Nunca llama a `import_theme()` ni a `import_experiments()` | Defensa si el runner de K no se registra |
| `E:core/app/modules/import-export-customization/utils.php` y `E:plugin.php` | Personalización fina habilitada sólo en la App | Casillas deshabilitadas |
| `E:digitalisimo-duplicator.php` (propio) | No copia `_elementor_import_session_id`, `_digitalisimo_import_origin` ni `_elementor_element_cache` | Revertir borraría duplicados del usuario |

## 8. Remapeo de IDs y URL

**Mapas** en la petición final: `post` y `template` (los del núcleo más los pares de la deduplicación del WXR), `term` y `menu`, `menu_slug`, `global_color` y `global_typography`, `url` (prefijos `home`, `site` y `uploads` del origen hacia los del sitio), `attachment` (URL vieja → adjunto por `_elementor_source_image_hash`) y `map:<nombre>` de las partes.

**Referencias integradas** (`References::builtin()`). Lo que ya traduce el núcleo no se repite: el widget Template, el `template_id` de Loop Grid, Nav Menu, las etiquetas de Popup, los controles `Query` con `On_Import_Trait` cuyo nombre lo permite (ver la fila de controles de consulta), la Query de los ajustes de documento y `__dynamic__`.

| Ámbito · tipo | Ruta | Clase |
|---|---|---|
| element · `digitalisimo-accordion`; `digitalisimo-fancy-tabs` y sus alias | `tabs.*.template_id` | template |
| element · `digitalisimo-accordion` | `tabs.*.anywhere_id` | post |
| element · `digitalisimo-offcanvas` | `template_id`; `sidebar` | template; `note:sidebar` |
| element · `slides` (rasgo) | `slides.*.digitalisimo_template_id` | template |
| element · `digitalisimo-icon-nav` y alias; `digitalisimo-vertical-menu` | `navbar`; `menu` | menu; menu_slug |
| element · `author-box` (rasgo) | `digitalisimo_author_user` | user |
| element · `digitalisimo-google-reviews` | `google_place_id` | `note:google_places` |
| element · `*` | reglas de visibilidad (`digitalisimo_visibility_rules.*`: entradas, página, usuario, términos) | post, user o term, con `gate` |
| element · `*` | `e_display_conditions` | `conditions`: cada condición se resuelve con `scope = condition` |
| condition · propias y nativas | argumentos de usuario, de entrada y de término | user, post o term, con `gate` |
| element · `section`, `container` | `shape_divider_top`, `shape_divider_bottom` (sólo `digi-*`) | `map:shapes` |
| element · `*`; document · `*` | `custom_css` | css |
| element · `*` | todo control URL, también en repetidores (derivado por tipo) | url, o media-url si está bajo `uploads` del origen |
| element · `loop-grid`; `loop-carousel` | `alternate_templates.*.template_id`; `template_id` | template |
| element · `taxonomy-filter` | `selected_element` | element |
| element · `wp-widget-nav_menu`, `wp-widget-media_*` | `wp.nav_menu`, `wp.attachment_id`, `wp.ids`, `wp.url` | menu, attachment, url |
| element · `*` | todo texto enriquecido (control `wysiwyg`, derivado por tipo) | `html`: cada `href` y `src` del origen pasa al destino |
| element · `*` | todo control de medios y galería, también en repetidores (derivado por tipo) | `media`: si la URL sigue en el origen, pasa al adjunto importado (por URL o por ID de origen); lo que ya es del sitio no cambia |
| element · `*` | todo control de consulta (`query`) y de plantilla (`template_query`), derivado por tipo, salvo lo que ya traduce el núcleo | según `autocomplete.object`: post, attachment, template, term o user. El núcleo sólo traduce por nombre (rasgo de importación de PRO: `*post_ids*`, `*term_ids*`, `post_id`, `template_id`, `popup`) o con un método propio (Loop Grid, Template, Nav Menu: `template_id`, `menu`); `posts_posts_ids`, `*_exclude_ids`, `*_fallback_ids`, la consulta de Loop Grid, `popup_action_popup_id` del formulario y similares los traduce K (`References::core_query_import()`) |
| instancia de widget global | `templateID` (en el elemento, no en sus ajustes; `Remapper::GLOBAL_WIDGET_REF`) | template |
| element · `form` | destinatarios y webhooks vacíos | `note:form` |
| element · `*` | `digitalisimo_acf_*` | `note:acf` |

Un inventario en tiempo de ejecución (`kit-roundtrip/setup-a.php`) vuelca los controles de todo widget, rasgo y condición registrados, y la comparación falla si un control con aspecto de referencia (tipos `query` y `template_query`, o `select2`, `select` o `number` cuyo nombre termina en `_id`, `_ids`, `template`, `menu`, `navbar`, `user` o `_value`) no está declarado, no deriva de su tipo y no figura con motivo en `tests/sources/kits/no-reference.json`.

**URL.** Un valor con el prefijo base del origen pasa a `home_url()` conservando ruta, consulta y fragmento; si el slug cambió, se informa. En CSS, cada `url(…)` bajo `uploads` con adjunto va a la URL del adjunto nuevo, y bajo `uploads/digitalisimo-elements/`, a la del destino. Al terminar, el informe cuenta las URL del origen que quedan. **Regla para lo que venga:** ningún CSS generado lleva una URL absoluta de `uploads`.

**Una sola vez.** El remapeo es una función pura que se aplica una vez por documento de la sesión (un mapa solapado como `{5→12, 12→20}` no es idempotente). También recorre la carga de los marcadores, con el remapeo nativo de cada elemento que sí existe.

**Widget global.** El núcleo no traduce el `templateID` de una instancia global: lo hace K. Además, el widget global derivado de PRO nunca se toma a sí mismo como original (una instancia ya global con `originalWidgetType = global`, o una plantilla cuyo contenido es otra instancia global): antes, si su plantilla no existía, `get_controls()` se llamaba sin fin y la petición final agotaba la memoria (R3, `tercero-pro3.zip`). La importación de widgets globales conserva el ID de origen como clave del resultado (`array_merge()` lo renumeraba y el mapa perdía la plantilla).

**Theme Builder y Custom Code**, en `after-run`: se traduce cada `sub_id` según su objeto; las condiciones por autor nunca tienen destino. Las condiciones del manifiesto se ordenan en `type`, `name`, `sub_name`, `sub_id` al normalizar (`Normalizer::ordered_conditions()`): un objeto JSON no tiene orden y el documento de tema del núcleo las une con `implode()`. Si alguna queda sin destino, la plantilla o el fragmento se guardan **sin condiciones**, en borrador, y el informe conserva la lista original.

## 9. Kits de terceros y marcador

| Entrada | Cómo entra |
|---|---|
| Kit 3.0 (Elementor o DIGITALÍSIMO) | Directo |
| Kit 1.x o 2.x (Elementor Pro 3.x) | Adaptador nativo; K apaga `theme` y `experiments` después del adaptador |
| Template Kit de Envato | Se convierte a kit 3.0 y entra siempre en «agregar» |
| ZIP de marketplace | Una subcarpeta o un ZIP interior con `manifest.json`; la documentación se ignora |
| Plantilla suelta o ZIP de plantillas | Camino nativo de la biblioteca, con el marcador |
| ZIP de kit subido en la biblioteca | Se redirige al importador de kits con un token de un solo uso (1 h, por usuario) |
| `file_url`, Kit Library, nube, `process-media` | 403 |

**Template Kits de Envato** (formato descrito con palabras propias): los estilos globales pasan a los ajustes del kit; cada plantilla, a `templates/<clave>.json` con su tipo (manda `type`, como en la lectura del núcleo; el editorial `template_type` sólo decide si no hay `type`, y uno que el sitio no conoce queda como tipo original; sin tipo registrado, `section` con el tipo original), su ubicación y sólo la primera condición `include` sin `sub_id`; la lista completa va a `digitalisimo/theme-builder.json`. Las claves del formato se quitan de `metadata` para que no acaben como metas. Se leen las condiciones y el tipo tanto del manifiesto como del archivo de plantilla. El estado de la validación con kits reales está en [k-envato.md](k-envato.md).

**Marcador `digitalisimo-unconverted`** (`Digitalisimo\Elements\Import\Unconverted_Widget`):

- sustituye a todo elemento que no se puede instanciar (motivo `unknown`) y a las instancias `e-component` mientras el núcleo no las admita en kits (motivo `component`), con el original completo en `settings.digitalisimo_unconverted` y su ID;
- en la raíz de un documento con secciones, o con `container` apagado, va dentro de una sección y una columna marcadas como envoltorio, que el front oculta;
- no aparece en el panel, `render()` no imprime nada y no carga recursos (presupuesto 0/0 en [ELEMENTS_FRONT_OBJECTIVE.md](../ELEMENTS_FRONT_OBJECTIVE.md)); el editor muestra una tarjeta con el tipo y el motivo; al exportar devuelve el original;
- con motivo `unknown`, cuando el tipo ya existe, se desenvuelve al leer el documento, y la primera vez se borran la caché de CSS y la de elementos de ese documento; el motivo `component` no se desenvuelve solo;
- la conversión de Migración (`digitalisimo_elements_unconverted_convert`) sólo corre en el editor y en Migración, nunca en el front;
- para un usuario sin `unfiltered_html`, el núcleo pasa la carga por kses como cualquier contenido: K no la codifica para esquivarlo y lo anota en el informe;
- contadores: la opción `digitalisimo_elements_unconverted` y la meta `_digitalisimo_unconverted` del documento.

**Medios de terceros:** pasan por el proxy y sus presupuestos; las fuentes de Google no se descargan y se listan. Ninguna petición al proveedor ni a `*.wordpress.org` durante la sesión.

## 10. Registro extensible

Es lo que permite que F7a, F7b, F4-Woo y SEO añadan sus datos de kit **sin tocar el código de K**. Vive en `E:modules/digitalisimo-import/kits/`, en el espacio de nombres `Digitalisimo\Elements\Import\Kits`.

```php
interface Part {
	public function key(): string;            // digitalisimo/<key>.json y manifest.digitalisimo.parts; [a-z0-9-]+
	public function version(): int;           // sube al cambiar el formato; import() recibe versiones ≤ ésta
	public function kit_meta_keys(): array;   // metas del kit que deben sobrevivir si el núcleo crea un kit
	public function export( Export_Context $context ): ?array;  // null o [] = sin archivo
	public function import( array $data, int $version, Import_Context $context ): array;  // devuelve el resumen
	public function revert( array $stored, Revert_Context $context ): void;
}
interface Early_Part extends Part {          // corre en el runner `plugins`, antes de taxonomías y contenido
	public function import_early( array $data, int $version, Import_Context $context ): array;
}
```

- **Partes** (`Parts`, filtro `digitalisimo_elements_kit_parts`). Las de K van primero, en este orden: `origin`, `element-defaults`, `kit-settings`, `files`, `media`, `theme-builder` y `term-meta`; después, las del filtro, por clave. El filtro recibe las de K indexadas por clave y devuelve instancias de `Part` o nombres de clase con constructor sin argumentos. Una clave inválida, repetida o reservada (`urls`), una versión menor que 1 o una meta de kit con nombre inválido se rechazan con `_doing_it_wrong()`. Se instancian una vez por petición: hay que engancharse en `init` o antes.
- **Referencias** (`References`, filtro `digitalisimo_elements_kit_references`). Cada entrada, con claves o posicional, es `scope`, `type`, `path`, `kind` y, opcionales, `gate` y `never`:
  - `scope`: `element`, `document`, `condition`, `post_meta` o `term_meta` (en las dos últimas, `type` es la clave de la meta);
  - `kind`: `post`, `template`, `term`, `menu`, `menu_slug`, `user` (nunca tiene destino), `attachment`, `url`, `media-url`, `css`, `html` (enlaces e imágenes de un texto enriquecido), `media` (`{ id, url }` de un control de medios o una galería), `file`, `element` (ID de elemento del mismo documento), `conditions` (un JSON de condiciones de visualización, como `e_display_conditions`, que se resuelve con las referencias de `scope = condition`), `note:<código>` o `map:<nombre>`;
  - `gate`: la referencia decide quién ve algo o dónde se aplica; por defecto es verdadero en `scope = condition` y en `kind = conditions`;
  - `never`: el valor que nunca coincide, para un control en el que vacío significa «cualquiera»; debe sobrevivir a `absint()` sin volverse un ID real.

  `matching( $scope, $type )` da las que se aplican: gana la declarada para el tipo, después la de `*` y por último la derivada de los controles URL.
- **Contextos.** `Export_Context`, `Import_Context` y `Revert_Context` extienden la base abstracta `Part_Context` (`kits/class-part-context.php`), que lleva el informe (`report()`, `tally()`, hasta 200 entradas por código) y la huella canónica (`hash()`). No dependen del núcleo.
  - `Export_Context::add_file( $path, $source )` sólo acepta las formas de §3 (`media/<sha256>.<ext>` y `files/(shapes|masks)/<slug>.svg`) y archivos del sitio actual; `budget()` dice cuánto cabe.
  - `Import_Context` da el modo (un valor desconocido es «agregar»), los mapas (`mapped()`), `add_map()` (no pisa: gana el primero y queda `map-conflict`), `store()` para la reversión, `staged_path()` (único modo de leer un archivo preparado) y `flag_review()` (borrador con «revisar antes de publicar»).
  - `Revert_Context::unchanged( $actual, $hash )` aplica «no pisar».
- **Ganchos publicados:** además de los dos filtros, `digitalisimo_elements_kit_media_limits`, `digitalisimo_elements_kit_guard_limits`, `digitalisimo_elements_kit_existing_document`, la acción `digitalisimo_elements_kit_imported( $sesión, $informe )`, `digitalisimo_elements_unconverted_convert` (para Migración en F8) y `digitalisimo_elements_kit_theme_builder_fields` (para la prioridad manual de F7a). F7a añadió `digitalisimo_elements_kit_used_globals_trees`, la primera excepción a «sin tocar K» (§15), y F4-Woo, `digitalisimo_elements_kit_not_exported` y la clave `taxonomies` de las referencias `term_meta` (§16).
- **Obligaciones de cada etapa:** un caso en `kit-roundtrip/cases.json`, toda meta del kit en `kit_meta_keys()` y `version()` que sube al cambiar el formato. La prueba del contrato es la sección 1 de `tests/elements-kit-parts.php`; el inventario estático (`tests/elements-kit-inventory.mjs`) exige un caso por widget propio y por parte.
- **Límite del registro:** los archivos sólo pueden tener las formas de §3. Un tipo de archivo nuevo exige ampliar esas formas y el guardián en K.

## 11. Pruebas

- **Unitarias**, en `validate.yml` y `release.yml`, tras `tests/elements-hello-header-footer.php`: `tests/elements-kit-{parts,guard,core-contract,session,settings,remap,preserve,pro-fixes,actions,global-widget,integration}.php` y `tests/elements-kit-{static,inventory,app-guard,fixtures}.mjs`. `global-widget` cubre la recursión del widget global; `integration`, lo que une a los frentes (una sola fuente de huellas, lista negra, condiciones y recuento; secciones del informe; «Publicar»; datos personales; medios dentro del kit). Fallan si falta `ZipArchive`; nunca se saltan.
- **Fixtures sintéticos** en `tests/sources/kits/`, generados por `build.mjs` (IDs de 7 hex, fechas fijas, hosts `.test`, correos sólo `@example.com`) y sellados en `fixtures.lock.json`.
- **Playground**, a mano antes de publicar y después de cada `sync-elementor-core.sh` (no en CI): `tests/playground/kit-roundtrip.sh` (escenarios R1 a R10, con `WOO`, `CORE`, `SLOW`, `QUICK=1` y `KIT_GOLDEN=write`; puertos 9461 a 9463) y `brand-audit.sh` con `STATE=kit` (puerto 9464). Su `summary.json` se guarda como `docs/fusion/k-kits-evidence.json`.

## 12. Alternativas descartadas y su motivo

| Alternativa | Motivo |
|---|---|
| Lista blanca de metas en el WXR | Borraría las metas de WooCommerce, ACF y SEO; se usa la lista negra de §6 |
| Pasar por kses el `_elementor_data` que llegue en el WXR | Más estricto: esas metas nunca entran por el WXR, que el exportador nativo no usa para el constructor |
| Restaurar el ID de elemento con `elementor/document/element/replace_id` | El filtro recibe el ID nuevo, no el original; se usa el recorrido en paralelo |
| Usar el remapeo estático nativo de cada widget en lugar del registro | Es privado y sólo lleva entradas y términos: no transporta menús, globales, separadores ni URL, y los rasgos transversales no tienen un método por clase. El registro es declarativo, no un motor de render, componentes, interacciones o condiciones |
| Desactivar la regla o la condición sin destino | Quitar una regla de un grupo «se cumplen todas» también amplía quién ve; se usa el valor que nunca coincide, el borrador con revisión y las plantillas sin condiciones |
| Codificar la carga del marcador para que kses no la toque | Dejaría restaurar contenido sin sanear a quien no tiene `unfiltered_html`; sólo se informa |
| Aplicar las guardas de portada y de páginas de WooCommerce también en «nuevo» | «Nuevo» es explícito y sólo en un sitio vacío: el propietario pide justo eso |
| Guardar el informe en los metadatos de la sesión del núcleo | El núcleo reescribe esa opción entera en cada petición; el informe (hasta 64 KB) y el respaldo van en una opción propia sin carga automática |
| Exportar las variaciones de producto en K | Pertenecen a F4-Woo; K las anota en `not_exported` |
| Conservar el contenido de tipos ausentes en la carpeta preparada | Es temporal; se conserva como plantilla `section` en borrador con su tipo original |

## 13. Notas sobre el contrato implementado

El contrato (commit `0027f46`) se ajustó al código en cuatro puntos respecto del diseño de trabajo; este informe y `FUSION_OBJECTIVE.md` ya los reflejan:

- **`Part_Context`** (`kits/class-part-context.php`) es una base abstracta que comparten los tres contextos.
- El registro de referencias tiene el `kind` **`conditions`**, con `gate` por defecto, y el campo **`never`** para el valor de cierre.
- La opción de sesiones de reversión del núcleo es **`elementor_revert_sessions`**: es la que se excluye al comparar opciones antes y después.
- La prueba del contrato es la sección 1 de **`tests/elements-kit-parts.php`**, sin un archivo aparte.

## 14. Integración (frente I)

La primera corrida completa de `kit-roundtrip.sh` y los pendientes de los frentes dejaron estos cambios de integración:

- **Una sola fuente.** Huellas y procedencia viven en `Parts\Origin` (`Session` sólo las expone); la lista negra, en `Export\Meta_Blacklist` (el WXR ya no tiene copia); la traducción de condiciones, en `Remapper::theme_conditions()`; el recuento de marcadores, en `Preserve::recount_site()`, que cuenta también la papelera.
- **Procedencia de plantillas.** La búsqueda por procedencia consulta todos los tipos registrados: con `post_type = any` la biblioteca de plantillas (`exclude_from_search`) quedaba fuera y una reimportación las duplicaba (A8).
- **«Publicar páginas y plantillas».** `Session::publishable()` decide qué se publica (tipos visibles y plantillas que sólo se ven incrustadas); la pantalla «Kits» no ofrecía el botón porque el método no existía.
- **Informe.** Todos los códigos que emiten K y los derivados de PRO tienen sección (`Report::SECTION_PREFIXES`), salvo los de diagnóstico, que van a «Otros avisos»; `tests/elements-kit-integration.php` lo comprueba recorriendo el código.
- **Acción de revertir** declarada en `Module::ACTION_REVERT` (`digitalisimo_elements_kit_revert`).
- **Arranque.** `Module::init()` engancha el marcador (`Preserve::boot()` con marcadores; sin ellos, el registro condicionado del widget y el filtro de plantillas sueltas), la biblioteca (`Library::intercept()` diferido, sin cargar la clase hasta que se dispara), la pantalla «Kits» y WP-CLI.
- **Lo que mostraron las corridas 2 y 3.** `media.json` lleva el título y el texto alternativo de cada adjunto, que se aplican a los adjuntos creados por la importación cuyo título es el nombre del archivo; las entradas del WXR conservan su fecha (`Wxr_Guard::processed()`, el importador del núcleo no la pasa); los términos que crea el WXR llevan la sesión (`created_term` en el contexto), así que «Revertir» los borra; el kit se guarda «exacto» en «agregar», al revertir y al deshacer (`Site_Settings::save_kit( …, true )` quita lo que el guardado añade solo, como `viewport_md` y `viewport_lg`); sin valores por defecto de elementos la meta del kit se borra en lugar de quedar `[]`; las fuentes importadas llevan su tipo (`elementor_font_type = custom`); al terminar se borra la copia que el núcleo extrae en `uploads/elementor/tmp/` (sin protección) y `tmp/` de K desaparece si queda vacía; el informe nombra «datos internos del constructor» en lugar de las metas `_elementor_*`.
- **Respaldo por la forma del valor.** Después del registro, el remapeo recorre los ajustes de cada elemento y traduce lo que aún apunte al origen por su forma: `{ url, is_external | nofollow }` como URL y `{ id, url }` como medio. Cubre los alias de widgets fusionados (sus ajustes conservan nombres de controles que la clase ya no declara) y los widgets sin controles registrados. Lo ya traducido no cambia.
- **Revertir deja el kit como estaba**, también las metas que reescribe su guardado (`_elementor_version`, `_elementor_pro_version`, `_elementor_global_class_usage_indexed` y su vista previa, en `Import_Hooks::KIT_METAS`), y no deja la carpeta vacía de iconos que creó la importación.
- **Controles de consulta.** `References::for_type()` deriva todo control `query` y `template_query` según su objeto (entrada, adjunto, plantilla, término o usuario) y sólo deja fuera lo que el núcleo ya traduce: por nombre con el rasgo de importación de PRO, o con el método propio de Loop Grid, Template y Nav Menu (`References::core_query_import()`). Antes, la consulta entera de Loop Grid, `*_posts_ids`, `*_exclude_ids`, `*_fallback_ids` y los filtros por autor quedaban con los IDs del origen.
- **Informe sin identificadores del proveedor.** `Report::public_value()` nombra con palabras propias las metas `_elementor_*` y los tipos `elementor_*` (pantalla, WP-CLI y JSON).
- **Tiempo por petición (A13).** En Playground, la importación de un kit propio con 70 páginas tardaba unos 38 s en `elementor-content` y 30 s en la petición final. Dos causas, ninguna de red: el núcleo guarda cada documento dos veces (al crearlo y en `save_elements_of_imported_posts()`) y cada guardado creaba una revisión, y al guardar los metadatos de un SVG sin medidas el núcleo (`core/files/file-types/svg.php`) las pedía con `simplexml_load_file()` a la URL del propio sitio, una petición a sí mismo que con un solo proceso esperaba 10 s. Dentro del contexto de importación, `Context::no_revisions()` (`wp_revisions_to_keep`, 99) no guarda revisiones y `Context::svg_dimensions()` (`wp_update_attachment_metadata`, 5) lee ancho y alto del archivo local (atributos o `viewBox`, sin red, `LIBXML_NONET`). Medido con `kit-roundtrip.sh` y `SLOW=1`: `elementor-content` 14,2–16,5 s y la petición final 11,8–15,9 s en R1, R8 y R10, y 16,5 s con las 100 imágenes de R13; ninguna petición del producto pasa de 16,5 s (≈0,1 s por documento guardado). Los medios siguen su presupuesto de 15 s por petición (`media.time`). Los bucles del núcleo siguen en una petición cada uno: un kit de varios cientos de documentos aún puede pasar de 25 s, y repartirlos exigiría cambiar el proceso del núcleo.
- **WP-CLI igual que REST.** Fuera del editor y de REST el núcleo sólo da los controles de contenido de cada elemento; el contexto de importación y el de exportación piden también los de estilo (`Performance::set_use_style_controls()`) y al terminar dejan el valor anterior: por WP-CLI se perdían valores por defecto de estilo y referencias de controles de estilo. `wp digitalisimo-elements kit export` exporta como «exportar todo» de la App: con la personalización por defecto del núcleo quedaban fuera las categorías y etiquetas de las entradas, y las condiciones y metas de término del kit no tenían destino.

## 15. Excepción declarada de F7a: `digitalisimo_elements_kit_used_globals_trees`

F7a no cambia el código de K salvo en **un punto**, que es la única excepción a «sin tocar K» de esa etapa. Las demás etapas siguen obligadas a declarar sus datos con el registro de §10.

- **Problema.** `Kit_Settings::export()` calcula los colores y las tipografías globales que usa el sitio (`used`) recorriendo sólo documentos, ajustes de página y valores por defecto de los elementos (`E:modules/digitalisimo-import/kits/parts/class-kit-settings.php:50-71`). Un global que sólo nombra un preset de estilo, guardado en la opción `digitalisimo_elements_presets` y no en un documento, no entraba en `used`. No viajaba como personalizado en «agregar», y el mapa de colores no podía traducir los `__globals__` del preset en el destino.
- **Cambio.** Dentro de `export()` y antes de calcular `used` (`:67-72`), los árboles que devuelve `apply_filters( 'digitalisimo_elements_kit_used_globals_trees', array(), $context )` se suman a los que K ya recorre.
- **Firma.** `( array $trees, Export_Context $context ): array`. Cada árbol tiene la forma que recibe `used_globals()`, una lista de elementos con `settings`, y K busca en él los `__globals__` como en los documentos.
- **Contrato.** Sólo suma: lo que no es un array se ignora y nada de lo que K ya recogía se quita ni se cambia. Sin funciones enganchadas, `used` es el de 4.3.0.163.
- **Quién la usa.** La parte `presets` de F7a (`E:modules/digitalisimo-design/presets/class-kit-part.php`, enganchada por `Editor::init()`), con los ajustes de cada preset, también de los inertes.
- **Por qué no es un motor paralelo.** Es un punto de extensión genérico, del mismo tipo que los filtros de §10: cualquier etapa cuyos datos de kit nombren globales fuera de un documento puede aportarlos por él, sin otra línea en K.
- **Prueba.** `tests/elements-design-presets-kit.php` comprueba que el filtro aparece una sola vez en K, que un global que sólo usa un preset entra en `used` y que, sin el filtro, no entra.
- **Detalle.** El diseño de la parte `presets` está en [f7a.md](f7a.md), §5.5 y §5.6.

## 16. Excepción declarada de F4-Woo: contadores que sólo bajan y referencias `term_meta` acotadas

F4-Woo cambia el código de K en **un punto genérico con dos piezas** (V.11.4 de su especificación). Las dos son puntos de extensión para cualquier etapa (F7b, F8, SEO), no una pieza de WooCommerce, y sin uso dejan el manifiesto y la traducción idénticos.

- **Contadores no exportados.** `Export_Context::get_not_exported()` (`E:modules/digitalisimo-import/kits/class-export-context.php`) pasa el mapa por `apply_filters( 'digitalisimo_elements_kit_not_exported', $map, $context )` (constante `Export_Context::FILTER_NOT_EXPORTED`), **acotado por `Export_Context::lower_only()`**: por cada motivo sólo vale un entero entre 0 y el número de K. Un motivo nuevo, un número mayor o negativo, un valor que no es entero, una clave quitada o un filtro que no devuelve un array no tienen efecto, y con el mapa vacío el filtro ni se aplica. Una parte sólo puede bajar lo que ella misma exporta: `woo-variations` baja `variations` a las variaciones cuyo producto padre no se exportó.
- **Referencias `term_meta` acotadas por taxonomía.** Una referencia de ámbito `term_meta` puede llevar la clave opcional `taxonomies`: nombres exactos o prefijos con `*` final, de hasta 32 caracteres y nunca `*` solo. `References` (`kits/class-references.php`) la valida y la normaliza (sin repetir y ordenada); fuera de `term_meta`, o inválida, la referencia se rechaza con `_doing_it_wrong`. `matching()` sólo cuenta una referencia acotada dentro de `References::within_taxonomy( $taxonomy, $callback )` con una de sus taxonomías, y `Term_Meta::import()` y `attachment_ids()` (`kits/parts/class-term-meta.php`) sólo la aplican a los términos de esas taxonomías. Sin la clave, el mismo resultado y el mismo coste. F4-Woo la usa para traducir la meta `image` de los términos `pa_*`, sin tocar la de otras taxonomías.
- **Prueba.** `tests/elements-kit-extensions.php`: sin filtro ni clave, manifiesto y traducción idénticos; un filtro que sube un contador o inventa un motivo no tiene efecto; `taxonomies` acota `image` a `pa_*`.
- **Detalle.** Las partes `woo-attributes` (temprana) y `woo-variations` están en `E:modules/digitalisimo-woo/kit/` y se describen en [f4woo.md](f4woo.md).

