# Informe: sistema de diseño y edición que DIGITALÍSIMO Elements ya ofrece

> Revisión del 16 de octubre de 2026 (Elements 4.3.0.156, núcleo 4.3.5), depurada para el repositorio público. Donde el plan aprobado decidió otra cosa, manda `docs/FUSION_OBJECTIVE.md`; las diferencias están al final.

Prefijos de las rutas (relativas a la raíz del repositorio):
- `E/` = `digitalisimo-elements/elementor/` (núcleo de Elementor 4.3.5, sin modificar)
- `P/` = `digitalisimo-elements/` (lo derivado de PRO Elements y lo propio)

**Conclusión principal:** casi todo lo que piden los presets de Divi, las clases y variables de Bricks y los componentes ya existe en el editor atómico (v4) del núcleo. Pero ese editor está apagado por defecto en los sitios que instalaron Elementor antes de la 4.0. Además, dos piezas no funcionan en nuestro paquete: las interacciones avanzadas de PRO y la exportación e importación de componentes.

---

## 1. Qué hay, versión y formato

- **Núcleo:** Elementor 4.3.5, GPL-3.0-or-later. Así lo declara `E/digitalisimo-core.json` (sha256 y tree_sha256 incluidos).
- **Derivado de PRO:** viene de PRO Elements 4.3.0 y usa el espacio de nombres `ElementorPro\…` (`P/DIGITALISIMO_CHANGES.md:172`). El estado de licencia se resuelve en local: `P/license/api.php:104-141` responde siempre activo y vitalicio, con una lista de funciones.
  - La lista incluye `atomic-components`, `atomic-custom-css`, `atomic-loop`, `size-variable`, `global-widget`, `display-conditions`, `transitions` y `notes`.
  - **No incluye** `pro-interactions`.
  - `P/license/admin.php:33-35`: `get_license_key()` devuelve `""`.
- **Dos motores conviven en el mismo editor:**
  - **v3 clásico:** controles, ajustes planos en `_elementor_data` y CSS por documento.
  - **v4 atómico:** esquema de props tipadas `{"$$type","value"}`, estilos guardados como variantes y CSS en archivos `uploads/elementor/css/<clave>-<post>-<contexto>-<breakpoint>.css` (`E/modules/atomic-widgets/styles/css-files-manager.php:9,202`). Estos archivos se encolan con su atributo `media`.
- **Widgets propios:** todos los de `P/modules/digitalisimo-widgets/` son v3 (`extends \Elementor\Widget_Base`, por ejemplo `class-advanced-heading.php:7`). Ninguno es atómico.
- **Condiciones propias:** `P/modules/digitalisimo-conditions/class-module.php:7-45` amplía las Display Conditions nativas (meta `e_display_conditions`) sin otro motor. Agrega una sección de visibilidad v3 en `class-visibility.php:19-34`.

### Experimentos relevantes y su estado por defecto

DIGITALÍSIMO no fuerza ningún experimento. La búsqueda en `P/*.php`, en Migración, en `tests/` y en `scripts/` no encontró ninguno. La única mención es `docs/ELEMENTS_FRONT_OBJECTIVE.md:52`.

`install_compare()` compara contra la primera versión registrada en `elementor_install_history` (`E/core/experiments/manager.php:122-138`). Por eso, en sitios que instalaron Elementor antes de la 4.0, **el editor atómico (v4) está apagado** salvo que alguien lo active. Los experimentos ocultos no aparecen en la pantalla de experimentos sin `ELEMENTOR_SHOW_HIDDEN_EXPERIMENTS` (`manager.php:880-882`).

| Experimento | Estado por defecto | Qué controla / dónde |
|---|---|---|
| `e_atomic_elements` | oculto, beta, inactivo; activo en sitios nuevos desde 4.0.0 | `E/modules/atomic-widgets/module.php:177,240-253`. Habilita widgets atómicos, clases globales, variables, componentes, interacciones, Default Styles, el atomic de PRO, las variables de PRO y la sincronización v3↔v4. |
| `e_opt_in_v4` (Editor V4) | oculto, alfa, inactivo; activo en sitios nuevos desde 4.0.0 | `E/modules/atomic-widgets/opt-in/opt-in.php:11,35-46`. Activarlo enciende `e_opt_in_v4`, `container` y `e_atomic_elements` (`:15-19`) por Ajax `editor_v4_opt_in` o `POST elementor/v1/operations/opt-in-v4`. Hay una pestaña «Atomic Editor» en ajustes (`E/modules/atomic-opt-in/opt-in-page.php:87-97`). También activa la validación de nombres de clase (`E/modules/atomic-widgets/parsers/style-parser.php:49`). |
| `container` | estable, inactivo; activo en sitios nuevos desde 3.16 | `manager.php:371-395` |
| `nested-elements` | estable, activo, inmutable, depende de `container` | `E/modules/nested-elements/module.php:12-24` |
| `e_component_variants` | desarrollo, inactivo | `E/modules/components/module.php:31,129-138` |
| `e_list`, `e_icon_button`, `e_accordion`, `e_svg_library` | desarrollo, inactivos | `atomic-widgets/module.php:178-181,259-305` |
| `e_pro_collection_loop` / `e_pro_atomic_form` | activos (beta / desarrollo) | `P/modules/collection-loop/module.php:29,41-46`; `P/modules/atomic-form/module.php:31,73-78` |
| `e_optimized_markup` | inactivo; activo en sitios nuevos desde 3.30 | `manager.php:397-408` |

---

## 2. Capacidades: completas, parciales y faltantes

### (a) Kit, estilos globales y algo equivalente a los «presets por tipo de elemento» de Divi

**Completo (v3, en el kit):**
- Colores y tipografías globales.
  - `E/core/kits/documents/tabs/global-colors.php:65-66,106,128` genera `--e-global-color-<_id>`.
  - `global-typography.php:73-104` genera `--e-global-typography-<id>-font-family`, etc.
- Theme Style por selector HTML:
  - Tipografía de body, enlaces y H1–H6 en `theme-style-typography.php:177-182`.
  - Botones con `{{WRAPPER}} button`, `input[type=submit]` y `.elementor-button` en `theme-style-buttons.php:39-55`.
  - También cubre formularios, imágenes, layout, fondo, lightbox y CSS del sitio.
- El kit activo se guarda en la opción `elementor_active_kit` (`E/core/kits/manager.php:22`).

**Parcial (v3): «Guardar como predeterminado» por tipo de widget o contenedor:**
- Se guarda en la meta del kit `_elementor_elements_default_values` (`E/modules/kit-elements-defaults/module.php:13`), con una ruta REST en `data/controller.php`.
- El hook `FillDefaultsOnDrop` se ejecuta en `preview/drop` (`E/assets/js/kit-elements-defaults-editor.js:637-720`). **Copia** los valores al insertar el elemento: no queda vinculado.
- Sólo hay un valor predeterminado por tipo y sólo lo ve un administrador (`:1310-1345`).
- **No sirve para widgets atómicos.** El saneador filtra por `get_controls()` (`utils/settings-sanitizer.php:92-118`), y los atómicos devuelven `[]` (`has-atomic-base.php`, `final get_controls`).

**Completo (v4): Default Styles por etiqueta HTML, vinculados y por kit:**
- Tipo de contenido `e_default_style` (`E/modules/default-styles/default-style-post-type.php:10`).
- Metas `_elementor_default_style_tag`, `_elementor_default_style_data` y `_elementor_version` (`default-style-post.php:15-17`).
- Mapa en el kit: `_elementor_default_styles_post_ids` (`default-styles-tag-post-ids.php:17`).
- 29 etiquetas permitidas (`default-styles-allowed-tags.php`).
- La macro Twig agrega la clase `e-default-<tag>` a todo elemento atómico (`E/modules/atomic-widgets/elements/base/_macros.html.twig:1-6`).
- El CSS se pinta con la clave `default` y prioridad 15 (`atomic-default-styles.php:13-16`).
- En el editor hay un panel «Default Style» con autocompletado de etiquetas (`E/assets/js/packages/editor-default-styles/editor-default-styles.js:146-166,363-366`).
- Se clonan a los kits nuevos (`module.php:47-60`).

**Fijo (v4, sólo en código): estilos base por tipo de elemento:**
- Identificadores `<tipo>-<clave>`, por ejemplo `e-heading-base` (`has-base-styles.php:39-41`).
- El usuario no los edita.

**Puente v4 → v3 (en un solo sentido):**
- `E/modules/design-system-sync/`: las variables de color con `sync_to_v3` aparecen en el selector de color v3 como `v4-<label>` (`classes/global-colors-extension.php:56-80`).
- Las clases de tipografía aparecen en la lista de tipografía v3 (`global-typography-extension.php:17-45`).
- Ambos se publican en `design-system-sync.css` (`stylesheet-manager.php:11,49-70`).

**Lo que falta frente a Divi:**
- Un preset predeterminado **vinculado por tipo de elemento**: el filtro `elementor/atomic-widgets/props-schema` (`has-atomic-base.php:403-416`) no recibe el tipo de elemento.
- Varios presets con nombre listados por tipo de módulo.
- Presets vinculados para widgets v3.

### (b) Componentes v4 y Global Widget de PRO

**Componentes (núcleo):**
- **Definición maestra:** documento y tipo de contenido `elementor_component` (`E/modules/components/documents/component.php:18-31`).
  - Metas: `_elementor_component_uid`, `_elementor_component_overridable_props`, `_elementor_component_variants`, `_elementor_component_is_archived`, `_elementor_component_archived_at`.
  - El contenido vive en `_elementor_data`.
  - Sólo se crean desde div-block o flexbox, y sólo admiten elementos atómicos (`non-atomic-widget-validator.php`; en el editor, `prevent-non-atomic-nesting`).
- **Instancia:** widget `e-component`, oculto del panel (`widgets/component-instance.php:17-45`).
- **Props sobrescribibles:**
  - Toda prop de *settings* recibe la unión con `overridable` (`overridable-schema-extender.php`).
  - Se excluyen `classes` y las marcadas con `ignore()`: `_cssid`, `attributes`, display-conditions y `component_instance`.
  - Hay grupos con etiquetas y orden (`overridable-props/component-overridable-props-parser.php:10-45`).
  - **Los estilos no se sobrescriben por instancia.** Sólo las variantes (experimento de desarrollo) permiten agregar clases por elemento (`variants/component-variant.php`).
- **Anidación:** permitida, con validación de dependencias circulares (`module.php:74,185-203`) y cadenas de sobrescritura (`originPropFields`).
- **Desvincular:** «Detach from Component» regenera los IDs de estilo local (en `editor-components.js`: `utils/detach-component-instance/*`).
- **Otros:** bloqueo por edición concurrente (`component-lock-manager.php`), archivado en vez de borrado, límite de **100 componentes** (`components-rest-api.php:21`, `components-repository.php:21-25`) y creación por REST con `manage_options`.
- **Permisos:** `components-access-controller.php:13-62` exige nivel «pro», y la licencia local lo da.
- **Faltan:**
  - **Slots:** no hay rastro en PHP ni en JS más allá de los slots de la interfaz.
  - **Seguimiento de usos visible:** sólo existe una relación interna para el CSS (`styles/component-styles.php:29-50`).
  - **Exportación e importación:** `IS_IMPORT_EXPORT_SUPPORTED = false` (`module.php:48`). Al exportar un kit se excluye `elementor_component`, y al importar se eliminan las instancias `e-component` (`utils/strip-component-instances.php`).

**Global Widget (PRO, v3):**
- Plantilla `elementor_library` de tipo `widget` con meta `_elementor_template_widget_type` (`P/modules/global-widget/module.php:13-17`).
- Instancia guardada como `{"elType":"widget","widgetType":"global","templateID":N}` (`widgets/global-widget.php:117-145,161-163`).
- Registra los documentos que lo usan en `_elementor_global_widget_included_posts`, sólo para invalidar CSS.
- Se puede desvincular («Unlink» en `P/assets/js/editor.js`).
- Limitaciones: un único widget, sin props sobrescribibles y sin contenedores.

### (c) Clases globales v4

**Almacenamiento:**
- Tipo de contenido `e_global_class` (`E/modules/global-classes/global-class-post-type.php:10`).
- Metas: `_elementor_global_class_id`, `_elementor_global_class_data`, `_elementor_global_class_data_preview` (borrador), `_elementor_global_class_edited` (`global-class-post.php:19-23`).
- En el kit: `_elementor_global_classes_post_ids`, `_elementor_global_classes_order(_preview)` y `_elementor_global_classes_labels(_preview)`.
- Relaciones de uso en metas del documento: `_elementor_used_global_class(_preview)`, `_elementor_global_class_using_documents…` (`global-classes-relations.php:18-25`).
- El formato antiguo `_elementor_global_classes` del kit se migra a entradas (`database/migrations/migrate-to-posts.php`).

**Asignar frente a editar:**
- Aplicar y quitar: capacidades `elementor_global_classes_apply_class` y `elementor_global_classes_remove_class`, para administrador, editor, autor, colaborador y shop_manager.
- Editar: `elementor_global_classes_update_class`, sólo administrador (`database/migrations/add-capabilities.php:8-16`). El `PUT` de REST la exige (`global-classes-rest-api.php:160-163`).

**Estados y responsive:**
- Estados: `hover` (agrega `focus-visible` automáticamente), `active`, `focus`, `focus-visible`, `checked`, y estados por clase `e--selected`, `e--disabled`, `e--playing`, `e--paused` (`E/modules/atomic-widgets/styles/style-states.php`).
- Responsive: `meta.breakpoint` acepta cualquier breakpoint del kit (`styles-renderer.php`, `wrap_with_media_query`).

**Renombrar:**
- El **label es el nombre de clase que se pinta**: la transformación de IDs a labels está en `atomic-global-styles.php:47-50` y se aplica al construir los estilos en `:167-178`. Renombrar cambia el HTML y el CSS, y se invalidan los documentos relacionados.
- Reglas del nombre: de 2 a 50 caracteres, `[a-zA-Z0-9_-]`, sin dígito inicial, sin `--`, y `container` está reservado (`style-parser.php:94-150`). Sólo se aplican si `e_opt_in_v4` está activo.
- Un label duplicado se renombra solo (`global-classes-rest-api.php:337-352`).

**Orden, límite y variables:**
- La primera clase del Class Manager gana (`array_reverse` en `atomic-global-styles.php:163`).
- Límite de 1000 clases (`global-classes-rest-api.php:22`).
- Las clases aceptan variables:
  - color → `global-color-variable`
  - font-family → `global-font-variable`
  - tamaños → `global-size-variable` (PRO; la licencia local lo incluye)
  - Ver `E/modules/variables/classes/style-schema.php` y `P/modules/variables/hooks.php`.

**Herramientas:**
- CSS propio por variante (`custom_css.raw` en base64), conservado porque la licencia incluye `atomic-custom-css` (`P/modules/atomic-widgets/module.php`).
- Convertir local en global, duplicar, ver usos (`GET global-classes/usage`), buscar y filtrar, y borrar con limpieza de documentos (`global-classes-cleanup.php`).

**Exportación:**
- En plantillas viajan como *snapshot*, con modos `match_site`, `keep_create` y `keep_flatten` (`E/core/utils/template-library-import-export-utils.php:13-19`).
- En kits: `global-classes/<id>.json` y `global-classes/order.json`.

**Falta:** usarlas en widgets v3, que sólo tienen el campo de texto `_css_classes`.

**Variables v4:**
- Se guardan en la meta del kit `_elementor_global_variables` con la forma `{data:{<id>:{type,label,value,order,created_at,updated_at,deleted_at?,sync_to_v3?}},watermark,version}` (`E/modules/variables/storage/constants.php`, `variables-collection.php:46-62`).
- IDs `e-gv-*` y límite de 1000 activas.
- El CSS es `:root{--<label>:valor}` (`classes/css-renderer.php`). Una variable borrada se conserva como `--<id>` y no rompe la página (`transformers/global-variable-transformer.php:25-38`).
- REST: `list`, `create`, `update`, `delete`, `restore`, `batch` (`classes/rest-api.php:50-165`).

### (d) Cascade layers y precedencia CSS en lo atómico

- **No hay `@layer`.** En el pintado PHP no aparece ninguno; sólo hay `@layer` en los bundles JS de la interfaz.
- **Todo selector tiene la misma especificidad:** `.elementor .<nombre>` (`styles-renderer.php:15,94-112`). Los estados suben a (0,3,0).
- **El orden decide** (`atomic-styles-manager.php`, `render()`): primero se recorren los breakpoints (desktop y luego los demás) y, dentro de cada uno, las capas en este orden:
  1. `base`, prioridad 10 (`atomic-widget-base-styles.php:14-16`)
  2. `default`, 15
  3. `global`, 20, en orden invertido (`atomic-global-styles.php:22-24`)
  4. `local`, 30 (`atomic-widget-styles.php:17-19`), más el local de los componentes
- **El editor imita ese orden** con prioridades de proveedor: default 15, global 30, elementos del documento 50, plantillas 50, documentos incrustados 75 (`E/assets/js/packages/editor-canvas/editor-canvas.js:4612-4615`, `editor-styles-repository.js:29,157`).
- **Frente a v3:** el Theme Style del kit (`.elementor-kit-N h2` o `button`, especificidad 0,1,1) **pierde** contra la base atómica (`.elementor .e-button-base`, 0,2,0). El botón atómico trae fondo `#375EFB` de base (`atomic-button.php`, `define_base_styles`). Para los atómicos sirven Default Styles o clases, no el Theme Style v3.
- **Detalle del núcleo:** el CSS propio se concatena con `'\n'` literal (`styles-renderer.php:192`).

### (e) Copiar y pegar estilos, varios a la vez, restablecer, herencia

**v3:**
- El menú contextual ofrece Copy, Paste, «Paste style» (Ctrl+⇧+V), «Paste interactions» (sólo para atómicos), «Paste from other site», «Reset style», Duplicate y «Delete N items». Todos actúan sobre la selección múltiple `elementor.selection.getElements()` (`E/assets/js/editor.js:9555-9670`).
- Pegar estilo sólo transfiere los controles de estilo, incluidos `__globals__` y `__dynamic__` (`editor.js:14830-14900`).
- Copiar y duplicar exigen que todo lo seleccionado sea del mismo tipo (`:13595`, `:14099`).
- **No hay edición simultánea de propiedades:** «Edit» se desactiva con selección múltiple (`:9558-9561`).

**v4:**
- `editor-canvas` intercepta `paste-style` y `reset-style` para los atómicos (`editor-canvas.js:9600-9690`).
  - Pegar copia el estilo local completo (todas las variantes y el `custom_css`) y **fusiona las clases globales** en cada elemento seleccionado. Se puede deshacer.
  - Restablecer borra los estilos locales.
- El panel también tiene:
  - restablecer por campo (`reset-style-props`);
  - indicadores de herencia con infotip: «Inherited from base styles», «This value is overridden by another style», «Style origin» (`editor-editing-panel.js`, `styles-inheritance/*`);
  - indicador de CSS propio.

**Faltan:** pegar estilos entre v3 y v4, editar varios elementos a la vez y «aplicar a todos los de este tipo» (el *extend styles* de Divi).

### (f) Contratos de elementos

**v3:**
- Forma: `{id, elType: section|column|container|widget, widgetType, isInner, settings: valores planos + __globals__ + __dynamic__, elements}`.
- El ID es `dechex(rand())` (`E/includes/utils.php:394-396`).
- **No hay validación de esquema al guardar** (`E/includes/base/element-base.php:675-693`): las claves desconocidas se conservan.
- La versión del documento se guarda en `_elementor_version` y `_elementor_pro_version`.

**v4:**
- Forma: `{id, elType, widgetType: "e-heading"…, version: "0.0", settings, styles, editor_settings, interactions, elements}`.
- No hay versión de esquema por elemento: `version` vale `'0.0'` (`atomic-widget-base.php:19,28`).
- **Esquema de props:**
  - Cada elemento lo declara en `define_props_schema()`; se agrega `_cssid` automáticamente y pasa por el filtro `elementor/atomic-widgets/props-schema` (`has-atomic-base.php:403-416`).
  - El editor recibe `atomic_props_schema`, `base_styles` y `dependencies_per_target_mapping` (`atomic-widget-base.php:69-84`).
- **Validación al guardar:**
  - Ajustes inválidos → **excepción y el guardado falla** (`has-atomic-base.php:177`).
  - Claves fuera del esquema → se descartan (`parsers/props-parser.php:28-50`).
  - Estilos inválidos → se descartan con un aviso en el registro (`has-atomic-base.php:104-118`).
- **Evolución del esquema:**
  - Migraciones declarativas en `E/migrations/manifest.json` (renombres en `widgetKeys`; `propTypes` de un tipo a otro con operaciones JSON).
  - Se aplican al leer, con caché por hash del manifiesto (`prop-type-migrations/migrations-orchestrator.php:103-125`).
  - Sólo en modo reversión se descargan de `https://editor.elementor.com/v1/migrations/` (`:23,352-360`).
- **Extensión de estilos:** el filtro `elementor/atomic-widgets/styles/schema` (`style-schema.php:36`) permite agregar props de estilo.

---

## 3. Formatos de datos exactos

**Elemento atómico en `_elementor_data`:**
```json
{"id":"a1b2c3d","elType":"widget","widgetType":"e-heading","version":"0.0",
 "settings":{"classes":{"$$type":"classes","value":["e-a1b2c3d-7f3e2a1","g-9c1d2e3"]},
             "tag":{"$$type":"string","value":"h2"},
             "title":{"$$type":"html","value":"Hola"},
             "color":{"$$type":"global-color-variable","value":"e-gv-4f2a1b0"}},
 "styles":{"e-a1b2c3d-7f3e2a1":{"id":"e-a1b2c3d-7f3e2a1","label":"local","type":"class",
   "variants":[{"meta":{"breakpoint":"desktop","state":null},
                "props":{"font-size":{"$$type":"size","value":{"size":32,"unit":"px"}}},
                "custom_css":{"raw":"<base64>"}},
               {"meta":{"breakpoint":"mobile","state":"hover"},"props":{},"custom_css":null}]}},
 "editor_settings":{"title":"Título"},"interactions":{"items":[],"version":1},"elements":[]}
```
- El ID de un estilo local es `e-<idElemento>-<aleatorio>` (`editor-elements.js:1134`).
- El de una clase global es `g-<aleatorio>` (`editor-global-classes.js:3020`).
- El de una variable es `e-gv-*`.
- Las dimensiones usan claves lógicas: `block-start`, `inline-end`, `block-end`, `inline-start`.

**Clase global:** `_elementor_global_class_data` = `{id, type:"class", variants:[…], sync_to_v3?}`. El label es el `post_title` más el mapa de labels del kit (`utils/global-class-data-normalizer.php`).

**Default Style:** `_elementor_default_style_data` = `{type:"class", variants:[…]}`. Se pinta como `{id:<tag>, label:<tag>, cssName:"e-default-<tag>"}` (`default-styles/utils/default-style-data-normalizer.php`).

**Instancia de componente:** `widgetType:"e-component"`, con:
```json
"settings":{"component_instance":{"$$type":"component-instance","value":{
  "component_id":{"$$type":"number","value":123},
  "overrides":{"$$type":"overrides","value":[{"$$type":"override","value":{
     "override_key":"prop1","override_value":{"$$type":"html","value":"Ana"},
     "schema_source":{"type":"component","id":123}}}]}}}}
```
Ver `prop-types/component-instance-prop-type.php`. El formato de las props sobrescribibles está documentado en `overridable-props/component-overridable-props-parser.php:14-43` (`props{overrideKey,elementId,elType,widgetType,propKey,label,originValue,groupId}`, `groups{items,order}`).

**Kit v3** (meta `_elementor_page_settings` del kit):
- `system_colors` / `custom_colors`: `[{_id,title,color}]`
- `system_typography` / `custom_typography`: `[{_id,title,typography_*}]`
- Las referencias en elementos se guardan como `__globals__:{"title_color":"globals/colors?id=primary"}` (`global-colors.php:14-17`).

**ZIP del kit nativo** (import-export-customization):
- `global-classes/<id>.json` y `global-classes/order.json`
- `global-variables.json`
- `default-styles/<tag>.json`
- `kit-elements-defaults.json`
- ajustes del sitio **con los estados de los experimentos** (`E/app/modules/import-export-customization/runners/export/site-settings.php:182-202`; importación en `runners/import/site-settings.php:346-371`)

El panel Design System del editor importa sólo `design-system`, con conflictos en modo `skip` o `replace` (`editor-design-system.js:147-239`).

---

## 4. Licencia y qué se puede reutilizar

- El núcleo es GPL-3.0-or-later. El derivado de PRO es GPLv3+ y conserva `license.txt` y `COPYING` (`P/DIGITALISIMO_CHANGES.md:172`).
- Todo lo descrito se puede reutilizar y ampliar dentro de GPL, **sin editar `E/`**. Hay varios puntos de extensión:
  - filtros: `elementor/atomic-widgets/props-schema`, `elementor/atomic-widgets/styles/schema`, `elementor/global_classes/import/transform_snapshot`, `elementor/kit/meta_to_preserve_on_kit_import`
  - acciones: `elementor/variables/register`, `elementor/atomic-widgets/*/transformers/register`
- Repositorios y servicios PHP reutilizables:
  - `Global_Classes_Repository::make()->put()` / `apply_changes()`
  - `Variables_Service::create()` / `update()` / `process_batch()` (`E/modules/variables/services/variables-service.php:80-250`)
  - `Default_Styles_Repository::make()->put($tag,$data)`
  - `Components_Repository::create($title,$content,$status,$uid,$settings)` (`E/modules/components/components-repository.php:61`)
  - **`Css_Converter::convert($css)`**, que devuelve `{props, customCss, rejected}` y resuelve `var()` a variables (`E/modules/atomic-widgets/css-converter/css-converter.php:151`). También está en REST como `POST elementor/v1/css-to-atomic`.
  - `Css_Media_Splitter` y `Style_Variants_Merger` (`E/modules/mcp/abilities/utils/style-variants-merger.php`).
- Algunas dependencias son servicios externos y no código reutilizable: Angie, la biblioteca de kits en la nube, la IA y las migraciones remotas en modo reversión.
- Las habilidades MCP (`build-composition`, `manage-elements`, `manage-classes`, `manage-variable`, `manage-component`, `manage-default-styles`) exigen WP Abilities API, McpAdapter y la puerta `Mcp_V4_Gate`, y son una API interna que puede cambiar.

---

## 5. Riesgos

1. **v4 apagado en los sitios heredados.** Por `install_compare` (`manager.php:122-138`), cualquier conversión a atómicos exige activar `e_atomic_elements`. Eso cambia la experiencia del editor (panel v4, Class Manager). Las páginas v3 no cambian.
2. **Importar un kit cambia experimentos** (`import/site-settings.php:346-371`). Un kit convertido desde otro formato no debe llevar `experiments`, o encenderá o apagará funciones sin aviso.
3. **Los componentes no viajan en kits:** `IS_IMPORT_EXPORT_SUPPORTED=false` (`components/module.php:48`). Un import nativo **borra** las instancias `e-component`.
4. **Interacciones de PRO desactivadas:** como `get_license_key()` devuelve `""` (`P/license/admin.php:33`), `P/modules/interactions/module.php:23` sale antes de registrar nada. El core acepta `scrollOut`, `scrollOn`, `hover`, `click` y `custom` en el validador (`E/modules/interactions/validators/trigger-value.php`), pero el editor los muestra desactivados y el script PRO del frontal no se carga. Un convertidor que los escriba produciría animaciones que no se ejecutan.
5. **Renombrar una clase o variable cambia el CSS público:** el nombre de clase es el label y la variable se pinta con `--<label>`. Hay riesgo de chocar con CSS del tema o de terceros, y la caché de página o CDN queda desfasada.
6. **Límites:** 100 componentes, 1000 clases y 1000 variables. Etiquetas de 50 caracteres como máximo.
7. **Guardado frágil en v4:** un ajuste atómico inválido aborta el guardado del documento (`has-atomic-base.php:177`). Los importadores deben validar con `Props_Parser` antes de escribir.
8. **El Theme Style v3 no afecta a los atómicos** por especificidad, y la base del botón es azul de Elementor. Si se mezclan v3 y v4 en un sitio sin Default Styles, se verá inconsistente.
9. **El núcleo puede cambiar formatos** (manifiesto de migraciones). Hay que repetir pruebas en cada `sync-elementor-core.sh`.

---

## 6. Recomendaciones de integración

**Regla base:** Migración sólo **escribe datos** con los repositorios del núcleo. El pintado lo hace el núcleo, así que no hace falta ningún renderizador en Migración. Si falta una función en tiempo de ejecución, se agrega en Elements (`P/modules/…`), nunca en `E/`.

| Concepto de origen | Destino existente | ¿Conversión automática? |
|---|---|---|
| Colores globales de Divi, Betheme, GP o Bricks | Con v4 activo: variables de color (`Variables_Service::create`, `sync_to_v3=true`, label con prefijo, p. ej. `digi-primary`). Sin v4: `custom_colors` del kit. | Sí |
| Fuentes y tipografías globales | `custom_typography` del kit (v3) más variables de fuente y una clase global de tipografía con `sync_to_v3` (v4) | Sí (familias y tamaños); pesos y estilos por clase |
| Variables de tamaño o espaciado (Bricks) | Variables de tamaño PRO (`global-size-variable`, ya habilitado) | Sí |
| Clases globales de Bricks | Clases globales v4 (label = nombre de la clase validado; estados y breakpoints como `meta`) usando `Css_Converter` + `Style_Variants_Merger`; lo que sobre va a `custom_css` | Sí; lo rechazado queda anotado |
| Presets de módulo de Divi | v4: una clase global por preset, aplicada a cada elemento convertido. v3: aplanar en los ajustes del widget y, para el preset predeterminado, una entrada en `_elementor_elements_default_values` (sólo copia al insertar) | Sí con aplanado; el vínculo se pierde en v3 |
| Estilo predeterminado por etiqueta (Divi o GP para h1–h6, p, a, button, input) | Default Styles v4 (`Default_Styles_Repository::put`) y Theme Style del kit para v3 | Sí, para las 29 etiquetas permitidas |
| Bricks Components / Divi Global Modules / secciones globales de Betheme | Componente v4 si todo el contenido es atómico, con propiedades → `overridable_props`. Si no: Global Widget (un solo widget) o plantilla de biblioteca con widget Template | Parcial. **Los slots no tienen destino**: hay que aplanarlos o desvincular la instancia |
| Animaciones de entrada (load o scrollIn: fade, slide, scale) | `interactions` v4 | Sí. El resto no funciona mientras las interacciones PRO sigan desactivadas |

**Dónde encaja cada cosa:**
- **En Migración:** una etapa «sistema de diseño» antes del contenido en `digitalisimo-migration/legacy/class-runner.php`. Primero escribe variables, clases y Default Styles, y luego el contenido que las referencia por ID. Los adaptadores van por constructor de origen, junto a los de Element Pack, con un mapa de IDs de origen a `g-*` y `e-gv-*`.
- **Para importar plantillas o kits de otros formatos:** generar un ZIP de kit nativo (`global-classes/`, `global-variables.json`, `default-styles/`, plantillas y contenido) **sin `experiments`** y pasarlo por el import-export-customization nativo. Así no hace falta otro sistema de kits. Los componentes van aparte, con `Components_Repository::create`, mientras el núcleo no los admita en kits.
- **En Elements:**
  - Si se quiere un preset predeterminado vinculado por tipo de elemento (Divi), agregar un módulo propio, por ejemplo `P/modules/digitalisimo-design-system/`, con un paquete del editor que, al soltar un elemento atómico, le añada la clase global elegida a `classes`. Puede imitar `FillDefaultsOnDrop` (`kit-elements-defaults-editor.js:684-718`) y guardar el mapa en una meta del kit, registrada en `elementor/kit/meta_to_preserve_on_kit_import`.
  - Para recuperar los disparadores de interacción PRO basta quitar la salida de `P/modules/interactions/module.php:23-25` (código derivado, no del núcleo). **No** se toca `get_license_key()`: esa clave viaja a la Kit Library remota. Antes se inventarían las páginas con `hover`, `click` o `scrollOn` guardados, que empezarían a animarse, y se decide el presupuesto de JS conforme a `docs/ELEMENTS_FRONT_OBJECTIVE.md`. Sólo sirve en sitios con v4.
- **Decisión de producto (tomada):** destino clásico (v3) para todos los orígenes; v4 sólo en sitios que ya lo tienen activo, y nunca se activa sin permiso del propietario.
- **Pruebas nuevas en `tests/playground/`:**
  - precedencia CSS entre Theme Style v3, Default Styles, clases y estilo local;
  - renombrar una clase o variable con caché de página;
  - importar un kit convertido sin cambiar experimentos;
  - que no se pierdan instancias `e-component` al importar.

---

## Decisiones posteriores (plan aprobado)

- **El kit es el sistema de diseño.** Colores y tipografías del origen van al kit con `_id` prefijado; variables v4 donde exista v4.
- **Presets de estilo (F7a):** se guardan en la meta del kit (`_digitalisimo_presets`) y compilan su CSS en el del kit con `elementor/css-file/post/parse` y `add_controls_stack_style_rules()` sobre una instancia de muestra. La instancia lleva una clase por `prefix_class`, con selector `.elementor-kit-N .elementor-widget-{tipo}.digi-preset-{slug}`: su especificidad (0,2,0) siempre pierde ante lo local (0,3,0). Se reutilizan la extracción, el saneador y el import/export de kit-elements-defaults, y se define qué gana frente a «Guardar como predeterminado». En v4, un preset es una clase global.
- **Componentes:** v4 con Components del núcleo (sin slots ni import/export: limitación del núcleo; se crean con `Components_Repository` después de importar). v3 con plantilla + widget Template y sobrescrituras de contenido envolviendo `builder_content_data`, caché de elementos apagada sólo dentro del envoltorio y claves estables `_digitalisimo_slot`. Vista de usos, borrado bloqueado con usos e IDs HTML únicos.
- **Kits:** sin `experiments`, `theme` ni `plugins`; importación en modo «sólo agregar» con el filtro `site-settings/customization`; `_digitalisimo_presets` y `_elementor_elements_default_values` en `elementor/kit/meta_to_preserve_on_kit_import`.
- **Edición múltiple** y «aplicar a todos los de este tipo» en F7b.
