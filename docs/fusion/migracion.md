# Informe: DIGITALÍSIMO Migración, cómo funciona hoy y cómo ampliarla a Divi, Betheme, Bricks y GP Premium

> Revisión del 16 de octubre de 2026 (Migración 1.0.18), depurada para el repositorio público. Describe el código propio tal como estaba antes de F0. Donde el plan aprobado decidió otra cosa, manda `docs/FUSION_OBJECTIVE.md` (sobre todo el punto 10, Migración v2); las diferencias están al final.

**Resumen.** La maquinaria del proceso ya existe y es reutilizable: etapas, cerrojo, reanudación, reintentos, informe y red. Lo que convierte contenido, en cambio, está casado con Element Pack y con un orden pensado para él (desactivar primero, convertir después). Para los nuevos orígenes faltan cinco cosas:

- análisis previo guardado;
- respaldo completo (no sólo `_elementor_data`);
- tabla de equivalencias de IDs;
- validación y activación controlada;
- reversión que no pise las ediciones posteriores del usuario.

Hoy Migración nunca cambia el tema activo.

Abreviaturas de rutas (relativas a la raíz del repositorio):
- `MIG` = `digitalisimo-migration`
- `ELE` = `digitalisimo-elements`
- `CORE` = `ELE/elementor`
- `REPO` = la raíz del repositorio

---

## 1. Qué hay, versión y formato

**Migración 1.0.18** (`MIG/digitalisimo-migration.php:5,14`)
- Declara `Requires Plugins: digitalisimo-elements` (:9) y `Network: true` (:11).
- Arranca en `plugins_loaded` con prioridad 20 (:21).

**Arranque** (`MIG/includes/class-plugin.php:14-45`)
- Sin Elements, sólo muestra un aviso (:16-19).
- Siempre inicializa, para todas las peticiones: Extensions, Shapes, Masks y Legacy_Visibility.
- Los adaptadores se registran en `elementor/widgets/register` con prioridad 30, y sólo si Element Pack no está cargado (:33, :58-64).
- El Runner se inicializa siempre; Migration sólo en administración; la CLI sólo con WP-CLI.

**Componentes de `MIG/legacy/`**

| Archivo | Función |
|---|---|
| `class-runner.php` (1138 líneas) | La tarea por etapas, el cerrojo, la red y las vistas |
| `class-migration.php` | Inventario, `convert_document`/`revert_document`, recuperaciones de bugs antiguos (1.0.10 y 1.0.13), pantallas |
| `class-retire.php` | Detección y desactivación de plugins |
| `class-aliases.php` | Widgets fusionados de Elements (no tiene relación con Element Pack) |
| `class-translator.php` | DSL de mapas |
| `class-native.php` | Conversión completa al formato propio |
| `class-adapters.php` | Trait `Legacy_Adapter` y 121 clases `Bdt_*` |
| Auxiliares Element Pack | `class-masks.php`, `class-extensions.php`, `class-shapes.php`, `class-acf.php`, `class-posts-skin.php`, `conditions/*` |
| `maps/` | 402 archivos (`bdt-*.php` y `bdt-*.styles.php`, más fooevents y lightbox) |
| `unsupported.php` | Motivo por cada widget sin adaptador |
| `class-cli.php` | Órdenes de WP-CLI |
| `assets/js/migrate.js` | Bucle AJAX de la pantalla |

**Documentación y datos**
- `REPO/docs/migration-map.json`: 263 entradas (ADAPTAR 186, COMPARTIR 6, DESCARTAR 57, RECONSTRUIR 14) y 12 extensiones. Campos de cada entrada: `target`, `engine`, `classification`, `decision`, `decision_target`, `decision_reason`, `setting_mappings`, `responsive_conversions`, `skin_conversions`, `legacy_adapter`, `legacy_documents`, `status`, `user_priority_*`.
- `REPO/docs/ELEMENT_PACK_RETIREMENT.md`: guía operativa.
- `REPO/docs/ELEMENT_PACK_MIGRATION_OBJECTIVE.md`: historial por lotes.

**Referencias en ZIP**

| Referencia | Versión y licencia |
|---|---|
| Element Pack Pro | **10.0.1** (los mapas dicen 9.9.1, p. ej. `maps/bdt-advanced-button.php:2`) |
| Prime Slider Pro | 4.7.0, GPL3, widgets `prime-slider-*` |
| Divi | 5.15.0, GPLv2 o posterior según `LICENSE.md` (la cabecera de `style.css` dice v2) |
| Betheme | 29.0.3, «Betheme License» |
| GP Premium | 2.5.6, GPLv2+ |
| Bricks | No está en `reference/` |

---

## 2. Capacidades: completas, parciales y faltantes

### Completas (todas en `MIG/legacy/class-runner.php`)

**Etapas** (:45)
- Orden: `prepare, deactivate, recover, repair, convert, revisions, references, aliases, finish`.
- `LOOP_STAGES` (:47): recover, convert, revisions y aliases.
- Cada etapa se despacha con `call_user_func('stage_'.$stage)` (:274) y su resultado se guarda en cada vuelta (:276).

**Cerrojo** (:829-888)
- `INSERT IGNORE` sobre la opción `digitalisimo_migration_lock`, con valor `"time|token"` y `autoload off`.
- Se renueva en cada `save()` mediante `touch()` (:854).
- Se considera abandonado a los 600 s (`STALE`, :62) y se roba con un UPDATE condicional (:839).
- `unlock_all` está en `register_shutdown_function`.

**Reanudación**
- Continuación por WP-Cron a los 120 s (`schedule()` :916), programada **antes** de trabajar (:195).
- Presupuesto de 20 s por petición (filtro `digitalisimo_migration_step_seconds`, :896).
- En la CLI, `Runner::run()` (:212) y un proceso nuevo con `WP_CLI::runcommand(... 'launch'=>true)` tras desactivar (`class-cli.php:131`).

**Reintentos**
- Cada documento se anota con su número de intento antes de trabajarlo. Con `MAX_ATTEMPTS=2` se salta y se lista en `counts.failed` (:539-568).
- Una etapa sin documentos que se corta dos veces también se salta (:263-273).

**Procesos viejos**
- `deactivated_at` se compara con `REQUEST_TIME_FLOAT`: una petición que arrancó antes de la desactivación se pausa (:197-200), hasta `MAX_PAUSES=3` (:374).

**Idempotencia**
- El identificador de clic `run` evita repetir una pasada (:186).
- El marcador `_digitalisimo_legacy` hace que la traducción ocurra una sola vez (`class-translator.php:15,68`).
- El respaldo se crea sólo si no existe (`class-migration.php:437-439`).
- La conversión que no cambia nada no escribe (:434).
- La reparación se hace una vez (opción `digitalisimo_ep_conditions_repaired`).
- Las revisiones ya comprobadas se cachean por md5 (`REVISION_CHECK`, :498-516).

**Red**
- `network_start()` (:676-767):
  - exige Elements activo en toda la red;
  - desactiva en cada sitio los conflictos (`Retire::deactivate_conflicts`);
  - después retira lo activado en toda la red;
  - encola en cada sitio una tarea `capable` bajo el cerrojo de ese sitio.
- La pantalla conduce cada sitio contra su propio `admin-ajax.php`, con CORS sólo para esa acción (`allowed_origins` :91-101) y un nonce por sitio (`digitalisimo_migration_step_{blog}`).
- `network_summary()` (:773) sólo lee.

**Informe** (`build_report` :571-611)
- Contenido: `convertible`, `blocked`, `unsupported` (con motivo), `missing_figures`, `review`, `failed`, `aliases_left`, `alias_blocked`, `alias_targets`, `ep_needed` y `plugins`.
- Se guarda en la opción `digitalisimo_migration_report` (el estado completo, :524) y se pinta en HTML (:994).

**Desactivación de plugins** (`class-retire.php`)
- Lista `PLUGINS` (:18-24) más el filtro `digitalisimo_migration_retired_plugins` (:38).
- Detección por basename y expresión sobre el `Plugin Name`.
- Atributos: `core` (Elementor, sólo si `core_ready()`), `conflict` (Elementor Pro y PRO Elements).
- `deactivate_plugins($file, false, $network)` (:104) ejecuta las rutinas de desactivación. Nunca borra nada.

### Parciales

**Respaldo**
- Sólo guarda `_elementor_data`, en la meta `_digitalisimo_ep_backup` con JSON `{"time":int,"data":"<raw>"}` (`class-migration.php:438`).
- Aliases comparte esa misma clave (`class-aliases.php:476-478`).
- No respalda `post_content`, `_elementor_page_settings`, opciones, kit, tema ni archivos.

**Reversión**
- Sólo por CLI: `--revert` (`class-cli.php:82-89`).
- Sobrescribe a ciegas y borra el respaldo (`class-migration.php:445-454`).
- No reactiva plugins.
- No borra las copias «Loop Item» creadas (`class-native.php:263-291`, meta `_digitalisimo_loop_item`).
- No retira las figuras copiadas a uploads.

**Análisis previo**
- `site_summary()` (`class-migration.php:691-730`) muestra el inventario antes del clic, pero:
  - sólo de Element Pack;
  - no se guarda;
  - no hay vista previa por documento.

**CSS y clases**
- Element Pack:
  - Los estilos que sólo existían en el adaptador se pasan a `custom_css` con `selector`, calculados con el propio motor CSS de Elementor (`class-native.php:220-245`, con `with_style_controls` :130-151).
  - Los ajustes desconocidos para el widget de destino se descartan (:168-184).
- Alias:
  - Reescriben el CSS propio de cada elemento (`rewrite_css` :438).
  - Bloquean la conversión si otro CSS del sitio nombra esas clases. El escaneo (`references()` :153-257) cubre CSS adicional, global styles, WPCode/Simple CSS, bloques, metas, opciones, sitemeta y archivos del tema y de mu-plugins.

**Registro (log)**
- Sólo hay contadores, `failed` y `warnings`.
- No hay registro por documento ni historial: cada pasada sobrescribe el informe anterior.

### Faltantes para lo pedido

- Validación posterior a la conversión (el renderizado sólo se comprueba en las pruebas, fase E).
- Activación controlada (el corte a la versión convertida es inmediato).
- Tabla de equivalencias de IDs y URLs.
- Protección de los cambios posteriores del usuario al revertir.
- Detección fuera de `_elementor_data`: `query_documents` sólo busca con LIKE en esa meta (`class-migration.php:305-317`).
- Cambio de tema: no existe. Un `grep` de `switch_theme` en `MIG` y `ELE` da 0 resultados.
- Registro extensible de etapas: `STAGES` es una constante y no hay filtro.

### El tema activo

Migración **no cambia el tema**. Sólo lo lee para buscar referencias CSS de los alias:
- `get_stylesheet()` en `class-aliases.php:194`;
- los archivos del tema y de su padre en `code_files()`, :490-513.

Hello Elementor sigue activo después de migrar. Por su parte, Elements trae `GeneratePress_Theme_Support` (`ELE/modules/theme-builder/theme-support/generate-press-theme-support.php:12`), que deja que el Theme Builder reemplace la cabecera y el pie de GP.

---

## 3. Formatos de datos exactos

### Estado de la tarea: opción `digitalisimo_migration_job` (`fresh()`, `class-runner.php:221-247`)

```
{version, started, updated, network, stage, done, pause, pauses, deactivated_at(float), cursor(post_id),
 processed, total, current, attempts, stage_attempts, capable,
 counts:{deactivated[], recovered, repaired, converted, elements, revisions, aliases, alias_elements, failed[]},
 warnings[], review[], keep_revisions[], plugins{file:{label,site,network,core}}, blocked{alias:[lugares]},
 report{}, run, finished}
```

### Otras claves de Migración

| Tipo | Nombre |
|---|---|
| Opción de red | `digitalisimo_migration_network_job` `{version, started, done, sites[], skipped[], deactivated[], warnings[], capable, finished}` |
| Cron | `digitalisimo_migration_continue` |
| AJAX | `digitalisimo_migration_step` |
| Opciones de reparación (bugs antiguos) | `digitalisimo_ep_conditions_repaired`, `_repair_cursor`, `_repair_cutoff`, `_repair_totals`, `digitalisimo_ep_revision_check` |
| Meta | `_digitalisimo_loop_item` (en la plantilla de origen, apunta a la copia) |

**Cachés que vacía cada conversión** (`clear_css` :457-464): `_elementor_css`, `_elementor_element_cache`, `_elementor_page_assets` y el archivo de `Post CSS`.

### DSL de mapas (`MIG/legacy/maps/{id}.php`, interpretado en `class-translator.php:349-403`)

| Clave | Uso |
|---|---|
| `target` | Widget de destino |
| `classes` | Sólo la usa `scripts/legacy-styles.py` al generar estilos |
| `defaults` | Valores por defecto; en los de valor múltiple se mezclan las partes |
| `rename` | Con variantes por dispositivo (`DEVICES`: `''`, `_widescreen`, `_laptop`, `_tablet_extra`, `_tablet`, `_mobile_extra`, `_mobile`) y con `__dynamic__` |
| `groups` | Renombra grupos de controles por prefijo |
| `values` | Tabla de valores o callable |
| `strip`, `set`, `drop` | Quitar HTML, fijar valores, eliminar claves |
| `repeaters{key:{target, default_rows, …}}` | Traducción de repetidores |
| `conditions` | Anula ajustes cuya condición no se cumple, como `get_settings_for_display` |
| `filter` | `callable($out, $settings)` |

- Los archivos `.styles.php` son una lista de controles: `name`, `type`, `responsive`, `selectors`, `default*`, `options`, `group`/`selector`, `types`, `exclude`, `condition`, `size_units`, `selectors_dictionary`.
- Los adaptadores se registran como `final class Bdt_X extends <Widget destino> { use Legacy_Adapter; const LEGACY_ID='bdt-x'; }` más una entrada en `Adapters::CLASSES` (`class-adapters.php:113`).
- Sólo se registran si existen el mapa y la clase, el destino está registrado y el ID está libre (:321-339).

### Escritura de documentos

- Todo sale y vuelve a `_elementor_data`, con `update_metadata('post', $id, …)` para no escribir en el padre de una revisión (`class-migration.php:428-443`).
- Nunca se toca `post_content`, `_elementor_edit_mode` ni `_wp_page_template`.

### Almacenamiento de Elementor que se puede reutilizar como destino

| Qué | Dónde |
|---|---|
| Sesiones de importación | `elementor_import_sessions` y `elementor_revert_sessions`; meta `_elementor_import_session_id` (`CORE/app/modules/import-export-customization/module.php:53-57`) |
| Registro de runners | `Import::register()` (`processes/import.php:257`) |
| Gancho de importación | `do_action('elementor/import-export-customization/import-kit', $import)` (`module.php:393`) |
| Clases globales (v4) | `_elementor_global_classes` (`CORE/modules/global-classes/global-classes-repository.php:18`) |
| Variables globales (v4) | `_elementor_global_variables` (`CORE/modules/variables/storage/constants.php:13`) |
| Condiciones del Theme Builder | `_elementor_conditions` y caché `elementor_pro_theme_builder_conditions` (`ELE/modules/theme-builder/classes/conditions-manager.php:300`, `conditions-cache.php:15`) |
| Ubicaciones de tema | `register_location()` (`locations-manager.php:546`) y `elementor/theme/register_locations` (:165); las plantillas «Sección» tienen ajuste `location` (`documents/section.php:68-102`) |
| Código personalizado | CPT `elementor_snippet` (`custom-code/module.php:32`); ubicaciones `elementor_head`, `elementor_body_start`, `elementor_body_end` (`custom-code-metabox.php:18-20`) |
| Reasignar IDs al importar | `Document::on_import_update_dynamic_content($config, ['post_ids'=>…])` (`CORE/core/base/document.php:1318`) |
| Reemplazar URLs | `Utils::replace_urls()` (`CORE/includes/utils.php:226`; SQL REPLACE global sobre metas JSON) |

### Formatos de origen (lo que cada driver necesita para detectar)

**Divi 5.15**
- Contenido:
  - en D5, `post_content` con bloques `<!-- wp:divi/section|row|column|text|global-layout|placeholder -->`;
  - en D4, shortcodes `[et_pb_*]`.
- Metas de página: `_et_pb_use_builder`, `_et_pb_use_divi_5`, `_et_pb_old_content`, `_et_pb_divi_4_content` (Divi ya guarda su propio respaldo D4), `_et_pb_page_layout`, `_et_pb_custom_css`, `_et_pb_built_for_post_type`, `_et_pb_use_ab_testing`.
- CPT: `et_pb_layout`, `et_template`, `et_header_layout`, `et_body_layout`, `et_footer_layout`, `et_theme_builder`, `et_code_snippet` y **`project`** (taxonomías `project_category` y `project_tag`, registradas por el tema).
- Theme Builder: `_et_use_on`, `_et_exclude_from`, `_et_default`, `_et_enabled`, `_et_header_layout_id`, `_et_body_layout_id`, `_et_footer_layout_id`.
- Opciones: `et_global_colors`, `et_global_data`, `builder_global_presets_d5` y `builder_global_presets_ng`, `et_uploaded_fonts`.

**Betheme 29.0.3**
- Meta `mfn-page-items` = `base64(serialize(array))`. El propio tema la lee con `unserialize(…, ['allowed_classes'=>false])`.
- Otras metas: `mfn-page-items-seo`, `mfn-page-object`, `mfn-page-local-style`, `mfn-post-css`, `mfn-builder-revision-*`.
- CPT `template` (`mfn_template_type`, `mfn_template_conditions`) y `portfolio`, `slide`, `offer`, `client`, `testimonial`, `layout`, `icons`, con taxonomías `*-types`, todos registrados por el tema.
- Secciones globales: `mfn-global-section` y `mfn-global-wrap`. Opciones del tema: `mfn-opts`.

**GP Premium 2.5.6**
- CPT `gp_elements`.
- Tipo de elemento: `_generate_element_type` (hook, block, header, layout) y `_generate_block_type`.
- Ganchos: `_generate_hook`, `_generate_custom_hook`, `_generate_hook_priority`, `_generate_hook_execute_php`, `_generate_hook_execute_shortcodes`, `_generate_hook_disable_site_header` y `_footer`.
- Condiciones: `_generate_element_display_conditions` y `_exclude_conditions` (reglas `general:site`, `general:front_page`, `post:*`…), `_generate_element_user_conditions` (`general:logged_in`, roles), `_generate_element_ignore_languages`.
- Hero y diseño: `_generate_hero_*`, `_generate_sidebar_layout`, `_generate_footer_widgets`, `_generate_content_area`, `_generate_disable_featured_image`.
- Sections antiguas: `_generate_use_sections` y `_generate_sections`.
- Opciones: `generate_settings` y `generate_dynamic_css_output` (caché).

**Bricks** (sólo documentación pública; está fuera de `reference/` y hay que verificarlo)
- Metas: `_bricks_page_content_2`, `_bricks_page_header_2`, `_bricks_page_footer_2` (lista plana de nodos `{id, name, parent, children, settings}`) y `_bricks_editor_mode`.
- CPT `bricks_template` con `_bricks_template_type` y `_bricks_template_settings`.
- Opciones: `bricks_global_classes`, `bricks_global_settings`, `bricks_theme_styles`, `bricks_color_palette`.

**Prime Slider 4.7.0**
- Usa el formato de Elementor (`_elementor_data`) con 36 tipos `prime-slider-*`.
- `Translator::ID_PATTERN` (`class-translator.php:19`) **no los reconoce**.

---

## 4. Licencia y qué se puede reutilizar

**Nuestro código**
- Elements es un derivado GPLv3 de PRO Elements y el núcleo de Elementor es GPL-3.0+ (`REPO/docs/third-party-licenses.md:8-9`).
- Ni `MIG/digitalisimo-migration.php` ni `ELE/pro-elements.php` declaran cabecera `License:`. Conviene añadirla (GPL-3.0-or-later) antes de distribuir los convertidores.
- Toda la maquinaria de Migración (Runner, Retire, Aliases, el DSL del Translator, `with_style_controls`, el escáner `references()`) es código propio y se puede reutilizar sin restricciones.

**Fuentes externas**

| Origen | Licencia | Uso recomendado |
|---|---|---|
| Divi | GPLv2 o posterior (`Divi/LICENSE.md`; `style.css` dice v2); terceros en `CREDITS.md` | Estudiar el formato y escribir los convertidores desde cero. No empaquetar imágenes, iconos ni SVG de divisores |
| Betheme | «Betheme License» (ThemeForest) | Sólo conocer el formato de datos; no copiar código ni recursos |
| GP Premium | GPLv2+ | Se puede estudiar; reescribir igualmente para no acoplar |
| Prime Slider | GPL3 | Igual que Element Pack |
| Bricks | Sólo documentación pública | Ningún código de Bricks |

**Patrón para figuras y recursos**
- El modelo correcto ya existe en Masks: copiar, saneados y en el momento de migrar, los SVG desde la instalación del propio sitio a `uploads/digitalisimo-elements/` (`MIG/legacy/class-masks.php`, encabezado :6-22). El paquete no distribuye nada.
- Usar lo mismo para los divisores de Divi y Betheme.

**Guarda de empaquetado**
- `REPO/scripts/build-packages.sh:48` aborta si el ZIP de Elements menciona `bdthemes`, `element pack`, `bdt-`, `bdt_` o `ep_`.
- Por eso los convertidores de **Prime Slider y Element Pack deben quedarse en Migración**.
- Los de Divi, Betheme, Bricks y GP pueden vivir en Elements si evitan esos tokens.

---

## 5. Riesgos

1. **El orden de las etapas sólo sirve para Element Pack.** `deactivate` va antes que `convert` porque los controles de Element Pack chocan con los propios (`can_convert` :349-382). Si Divi Builder o GP Premium se añaden a `Retire::PLUGINS` vía filtro, se desactivan **antes** de convertir y los shortcodes `[et_pb_*]` o los Elements de GP dejan de verse al instante.
2. **Las compuertas de Element Pack saltan todo lo demás.** Si Element Pack sigue activo, `stage_convert` salta directamente a `finish` (:448-451). Una etapa nueva para otro origen colocada después de `aliases` nunca correría.
3. **La reversión pisa las ediciones del usuario.** `revert_document` (`class-migration.php:445-454`) no compara hashes. Además comparte la clave `_digitalisimo_ep_backup` con Aliases y no deshace las copias Loop Item, las figuras ni los plugins.
4. **Pérdida de `post_content`.** Al guardar desde el editor, Elementor reescribe `post_content` con texto plano. Si en Divi o GP no se respalda `post_content` antes de activar, el original desaparece. Divi D5 guarda `_et_pb_divi_4_content`, pero eso no cubre el contenido D5.
5. **CPT del tema.** Al cambiar de tema dejan de registrarse `project` (Divi) y `portfolio`/`slide`/`offer`/`client`/`testimonial` (Betheme): sus URLs dan 404. Registrar esos tipos es trabajo cotidiano, así que no puede vivir sólo en Migración.
6. **Shortcodes sueltos.** Shortcodes de Divi o Betheme en widgets, extractos, descripciones de términos o campos ACF se imprimirían en crudo después de cambiar el tema.
7. **Dependencia del tema que se retira.** Menús (`nav_menu_locations` dentro de `theme_mods_{tema}`), widgets, CSS adicional (CPT `custom_css` por stylesheet) y ajustes de cabecera y pie (Hello, GP, Divi) dependen del tema. Hoy nada los traslada.
8. **Coste en sitios grandes.**
   - `Migration::documents()` repite un LIKE múltiple sobre `postmeta` en cada petición de `convert` (:453).
   - `classify()` decodifica todos los documentos en el informe y en la pantalla (:604).
   - `references()` recorre todas las opciones, metas y archivos del tema.
   - Con más orígenes, esto se multiplica.
9. **Seguridad.**
   - Betheme exige `unserialize` con `allowed_classes=>false`.
   - Los hooks de GP con `_generate_hook_execute_php` nunca deben evaluarse.
   - En el análisis no se debe usar `do_shortcode` con Divi cargado, por sus efectos secundarios.
10. **Pruebas que no corren tal cual.** `tests/playground/migrate.sh:13`, `legacy-ab.sh:52` y `scripts/inventory-element-pack.py:10` esperan `reference/bdthemes-element-pack/` descomprimido, pero sólo hay ZIP. Además, la referencia es la 10.0.1 y los mapas la 9.9.1: hay que reinventariar.
11. **Política de un solo botón** (AGENTS.md). La «activación controlada» no puede añadir pasos. El análisis tiene que verse antes del clic y el corte tiene que decidirse solo, con compuertas.
12. **Multisite.** El tema destino debe estar permitido en el sitio (`allowedthemes`). Además, Divi Builder o GP Premium activados en toda la red no se pueden retirar desde un solo sitio.

---

## 6. Recomendaciones de integración concretas

### 6.1 Arquitectura de «orígenes»: convertidores puros en Elements, drivers en Migración

**En Elements: módulo nuevo `ELE/modules/digitalisimo-import/`**
- Cargado sólo en administración, AJAX y CLI; nunca en el front. Sin dependencia de Migración.
- Contenido:
  - `interface-source.php`: `id()`, `sniff($payload)`, `parse($raw): Tree`, `convert(Tree, Context): Result{elements[], page_settings[], kit[], templates[], refs{post_ids, term_ids, attachment_ids, urls}, unsupported[{type, count, reason}], warnings[]}`. Es puro: sin escrituras.
  - `class-registry.php`, con el filtro `digitalisimo_elements_import_sources`.
  - `class-tree.php`: árbol neutro (`type`, `attrs`, `style[device][prop]`, `classes`, `children`).
  - `class-emitter.php`: genera contenedores flex o grid y widgets de Elements y PRO.
  - `class-style-map.php`: propiedades CSS a controles, reutilizando la lista `DEVICES` de `class-translator.php:16`.
  - `class-kit-writer.php`: colores y tipografías del kit; variables y clases globales v4 a través de los repositorios del núcleo, no escribiendo la meta en bruto.
  - `sources/divi`: D5 con `parse_blocks()`; D4 con tokenizador propio.
  - `sources/betheme`, `sources/bricks`, `sources/gp`.
- **Importador de Elements, sin crear otro sistema de kits.**
  - Plantillas sueltas: convertir y guardar como `elementor_library`.
  - Kits: armar un kit con el formato de Elementor e importarlo con `import_kit()` (`CORE/app/modules/import-export-customization/module.php:385`). Así se heredan las sesiones y la reversión nativas (`elementor_import_sessions`, `_elementor_import_session_id`).
  - Las reasignaciones de IDs pasan por `on_import_update_dynamic_content` (`document.php:1318`).
- **Funciones cotidianas que deben vivir en Elements** (o en el tema propio):
  - registro de compatibilidad de los CPT `project` y `portfolio`, sólo si existen entradas de esos tipos y conservando el slug;
  - divisores `digi-*` desde uploads (ya existe en `modules/digitalisimo-widgets/class-divider-shapes.php`);
  - ningún adaptador en tiempo de ejecución para formatos ajenos: siempre se convierte.

**En Migración: carpeta nueva `MIG/sources/`**
- `interface-driver.php`: `detect()`, `analyze($after, $limit)`, `documents($after, $limit)`, `backup($id)`, `convert($id)`, `validate($id, Result)`, `activate($id)`, `revert($id)`, `retire_plan()`, `theme_plan()`.
- `class-drivers.php`, con el filtro `digitalisimo_migration_sources`.
- `drivers/class-element-pack.php`: una fachada sobre Migration, Native y Aliases **sin cambiar su comportamiento**.
- `drivers/class-divi.php`, `class-betheme.php`, `class-bricks.php`, `class-gp.php`: delegan en `\Digitalisimo\Elements\Import\Registry` si existe; si no, avisan.
- `drivers/class-prime-slider.php`: al estilo de Element Pack (mapas `MIG/legacy/maps/prime-slider-*.php` más adaptadores), porque se trata del mismo formato y de un nombre prohibido en Elements.

### 6.2 Cambios en el Runner sin romper lo actual

- **Etapas.** Sustituir la constante por `self::stages()`. El orden queda: `prepare, analyze*, deactivate, recover, repair, convert, revisions, references, aliases, globals*, templates*, sources*, validate*, activate*, retire_late*, theme*, finish`.
  - Las marcadas con `*` son nuevas y no hacen nada si ningún driver detecta su origen. Ninguna prueba depende del número de etapas.
  - Cambiar los saltos `next($state,'finish')` de `stage_convert` (:451) y `stage_references` (:481) a `next($state,'globals')`, para que la compuerta de Element Pack sólo omita las etapas de Element Pack.
- **Estado por driver.** `state['drivers'][id] = {cursor, processed, total, failed[], blocked[], validated[], activated[]}` en lugar del `cursor` global. Consultas con cursor `post_id > %d LIMIT N`, como `Aliases::documents_with` (`class-aliases.php:129-140`), en vez de recalcular listas completas.
- **Retire.** Añadir los atributos `phase => 'start'|'end'` y `needed_until_converted`; generalizar `element_pack` (`class-retire.php:46`).
  - Divi Builder (`divi-builder.php`) y GP Premium (`gp-premium.php`) llevarían `phase => 'end'`: se desactivan en `retire_late`.
  - `network_start()` sólo retiraría en el arranque los de `phase = 'start'`.
- **Análisis previo.**
  - `analyze` guarda `digitalisimo_migration_analysis` (autoload off) por driver: inventario, convertibles, bloqueados con motivo, CPT y shortcodes sueltos, CSS ajeno con clases del constructor anterior.
  - Para lo último se reutiliza `Aliases::references()` con agujas como `et_pb_`, `mfn-`, `brxe-`.
  - `site_summary()` lo muestra antes del clic. Se respeta el botón único.

### 6.3 Respaldo, activación controlada, validación y reversión

**Respaldo v2**
- Meta `_digitalisimo_migration_backup`:

```json
{"v":2,"source":"divi","run":"…","time":0,
 "meta":{"post_content":"…","_elementor_data":"…","_elementor_edit_mode":"","_wp_page_template":"…","_et_pb_use_builder":"on"},
 "source_hash":"md5","converted_hash":"md5"}
```

- Se mantiene `_digitalisimo_ep_backup` sin tocar para Element Pack: la reparación y `recover` dependen de él.
- Opciones y tema en `digitalisimo_migration_site_backup`: `stylesheet`, `template`, `active_plugins`, `nav_menu_locations`, `sidebars_widgets`, el ID del CPT `custom_css` y los ajustes del kit (`_elementor_page_settings` del post `elementor_active_kit`).
- Antes de `activate`, ofrecer como integración opcional una copia con DIGITALÍSIMO Backups (`REPO/digitalisimo-backups/includes/class-backups.php`, `manual_start`/`manual_status`), sin exigir ese plugin.

**Activación controlada**
- `sources` convierte y escribe en una meta de preparación `_digitalisimo_migration_draft`, no en `_elementor_data`. El sitio sigue viéndose con el constructor original.
- `validate` comprueba, por documento:
  - que el JSON decodifica;
  - que cada `widgetType` está registrado;
  - un renderizado de prueba con `try/catch`;
  - una firma de texto, enlaces e imágenes del árbol de origen frente al convertido. Se puede portar `digi_ab_signature()` de `REPO/tests/playground/legacy-signature.php:3` a una clase propia.
- `activate` mueve la preparación a `_elementor_data`, fija `_elementor_edit_mode=builder` y `_elementor_version`, mapea las plantillas de página (Divi en blanco a `elementor_canvas`), apaga `_et_pb_use_builder` y vacía cachés con `Migration::clear_css()`.
- `theme` sólo cambia el tema si el driver no deja ningún documento bloqueado ni fallido. Si quedan, se avisa y se espera al siguiente clic.

**Equivalencias de IDs y URLs**
- Opción `digitalisimo_migration_idmap` (autoload off), con este formato: `{source:{post:{old:new}, term:{}, attachment:{}, layout:{}, class:{old:new_id}, color:{old:kit_id}, url:{old:new}}}`.
- En las entradas creadas, meta `_digitalisimo_migration_origin` = `{"source","type","id","hash"}`. En el origen, `_digitalisimo_migration_target`, siguiendo el patrón de `_digitalisimo_loop_item` (`class-native.php:271-289`). Así una segunda pasada no crea duplicados.
- Las URLs se reemplazan en memoria sobre el JSON convertido, no con el REPLACE global de `Utils::replace_urls`.

**No sobrescribir cambios posteriores**
- Revertir sólo si `md5(_elementor_data actual) === converted_hash`. Si no coincide, se lista como «editado después de migrar».
- Reconvertir sólo si `source_hash` cambió **y** el contenido convertido no fue editado. Si cambiaron los dos, es un conflicto y se lista.
- Mantener `--revert` en la CLI con `--source=` (`class-cli.php:82`), en línea con el botón único.

### 6.4 Tema propio

> Actualizado con el plan: no hay tema hijo, el slug es `digitalisimo-theme` y en producción el tema lo activa el administrador tras la vista previa.

Clase nueva `MIG/sources/class-theme-switch.php`. Opera por sitio, en la petición de cada sitio:
- Destino: `digitalisimo-theme`. En una red, habilitarlo (`allowedthemes`) es de alcance de red: sólo lo hace «Migrar toda la red» con un superadministrador; la petición de un sitio informa.
- En producción (`wp_get_environment_type() === 'production'`, el valor por defecto) el clic deja todo preparado y la tarea queda en `awaiting_theme`; al activar el tema propio, la tarea se reanuda sola. Fuera de producción, el clic hace todo.
- Antes del cambio:
  - mapear `nav_menu_locations` del tema anterior a las ubicaciones del propio;
  - copiar el CSS adicional (CPT `custom_css`) al nuevo stylesheet;
  - convertir cabecera y pie del tema (opciones de Divi, `generate_settings`, `mfn-opts`, ajustes del kit de Hello) en plantillas del Theme Builder con `_elementor_conditions`.
  - Las claves de Hello Elementor hay que verificarlas: Hello no está en `reference/`.
- El tema propio debe registrar ubicaciones equivalentes a los ganchos de GP mediante `elementor/theme/register_locations`. Los Hook Elements de GP sin PHP pasan a plantillas «Sección» con `location`; los de `<head>` o `body` van al Custom Code (`elementor_snippet`).

### 6.5 Qué se convierte solo y qué no

**Se convierte solo**
- Estructura de secciones, filas y columnas a contenedores flex o grid.
- Texto, encabezado, imagen, botón, icono, separador, espaciador, video, galería, pestañas, acordeón, contador y precios, a widgets de Elements o PRO.
- Colores y tipografías globales al kit; clases de Bricks a clases globales v4, sólo cuando el destino es un widget atómico.
- Theme Builder de Divi, plantillas de Betheme, plantillas de Bricks y GP Elements de tipo cabecera, pie o diseño, a `elementor_library` con condiciones (`general:site` a `include/general`, etc.).
- Bloques de GP: se quedan como Gutenberg.
- `module_class` y `_cssClasses` a `_css_classes`; `module_id` a `_element_id`.
- CSS por módulo a `custom_css` con `selector` (en Bricks, `%root%` pasa a `selector`).
- CSS de página a `_elementor_page_settings['custom_css']`.

**Semiautomático (se convierte y se lista para revisión)**
- Bucles y consultas (a Loop Grid con un Loop Item, como `Native::loop_item`).
- Popups, formularios, menús y mega menús, animaciones e interacciones, componentes de Bricks y Divi Global Modules (a plantilla y widget `template`).

**No se convierte (se informa con motivo)**
- PHP en ganchos de GP.
- Pruebas A/B de Divi (`_et_pb_use_ab_testing`).
- JavaScript libre.
- Elementos marcados como obsoletos en Betheme (`mfn-deprecated`).
- Sliders de terceros por shortcode (Revolution, Layer): se conserva el shortcode.
- Contenido dinámico de plugins ausentes.

### 6.6 Pruebas

- Copiar el patrón de stubs de `REPO/tests/migration-runner.php` (motor simulado y WordPress mínimo) para drivers falsos. Debe cubrir:
  - el orden: un `phase => 'end'` nunca se retira antes de `activate`;
  - que la compuerta de Element Pack no salte a los demás orígenes;
  - la reversión con hash;
  - el mapa de IDs idempotente.
- Crear fixtures `REPO/tests/sources/{divi,betheme,bricks,gp}/*.json` extraídas a mano, nunca ejecutando las referencias.
- Adaptar `migrate.sh` y `legacy-ab.sh` para que acepten la referencia descomprimida en una carpeta temporal.
- Añadir una entrada `sources` a `migration-map.json` (o un `docs/sources-map.json`) con decisión por módulo y origen.

---

## Decisiones posteriores (plan aprobado)

- **Conversores en Elements, orquestación en Migración.** El intérprete genérico de mapas (el DSL de `class-translator.php`) se traslada a `digitalisimo-elements/modules/digitalisimo-import/`; en Migración quedan los mapas de Element Pack y Prime Slider, que se le pasan por filtro.
- **Drivers:** `detect, analyze, documents, backup, convert, validate, activate, revert`. Runner con estado por driver y cursor, y `state['v']`: una tarea iniciada con la versión vieja termina con su semántica.
- **Análisis guardado** y visible antes del clic; recorre `post_content`, extractos, widgets, términos, ítems de menú, opciones y ACF.
- **Respaldo v2** del documento y del sitio con un hash por pieza; borrador en `_digitalisimo_migration_draft`; validación (widgets registrados, render de prueba, firma de texto, enlaces e imágenes, IDs únicos, referencias, ciclos); equivalencias en `digitalisimo_migration_idmap`.
- **Reglas de «no pisar»:** se reconvierte si cambió el origen; nunca se reescribe ni se revierte lo editado después (hash); la reversión sólo por CLI.
- **Retirada:** `Retire::PLUGINS` gana `phase` (`start`, `end`, `theme`); la red sólo retira `start` al inicio. Element Pack conserva su orden actual como excepción documentada.
- **Tema:** borradores + `awaiting_theme` en producción; las plantillas del Theme Builder convertidas nunca se publican antes del cambio de tema. Sin tema hijo: los hooks PHP de GP van desactivados al editor de fragmentos de Tools.
- **Lo no convertible** se marca con el widget oculto `digitalisimo-unconverted`; mientras quede alguno bloqueante, no se activa el resultado ni se retira el origen.
