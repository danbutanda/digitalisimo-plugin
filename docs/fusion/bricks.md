# Informe del frente Bricks Builder (sólo documentación pública)

> Revisión del 16 de octubre de 2026, depurada para el repositorio público. Bricks es un tema comercial y no hay código suyo en `reference/`: todo sale de su documentación pública (esquema 2.4.3), resumida con palabras propias y sin copiar sus ejemplos. Las claves no documentadas se cierran con las muestras reales del usuario, que quedan fuera de Git. Donde el plan aprobado decidió otra cosa, manda `docs/FUSION_OBJECTIVE.md`; las diferencias están al final.

**Fuentes.** Las 16 páginas cargaron. Varias se leyeron con desplazamiento por su largo. Quedaron sin leer los últimos ~7 KB de *dynamic-data*, los últimos ~8,5 KB de *query-filters* y la mitad final del esquema *theme-styles*. Para completar, leí estas páginas: el esquema por elemento (`/developer/schema/elements/common/{element,conditions,interactions}/`, `/general/content-area/`, `/global/{components,global-classes,global-variables,theme-styles}/`, `/settings/template/`), los JSON resueltos (`/schema-resolved/global/components.json`, `global-classes.json`, `settings/template.json`, `controls/{typography,background,spacing,query}.json`, `elements/container.json`) y `schema.md`. La versión del esquema publicado es **2.4.3**.

**Lo más importante:**
- **Encaje técnico con v4.** El modelo de Bricks (árbol plano, ajustes con forma de CSS, clases globales, variables, componentes) encaja mejor con el atómico de Elementor 4.3.5 que con los widgets clásicos. **El plan decidió destino clásico (v3)**: v4 sólo en sitios que ya lo tienen activo (ver «Decisiones posteriores»).
- **Dos piezas existentes cubren casi todo el estilo.** El conversor CSS→atómico y la importación de kits del núcleo.
- **Faltantes reales:** slots de componentes, acciones de interacción que no son animación, filtros fuera de taxonomía, consultas globales, plantillas de contraseña, query editor PHP y loops API/array.
- **Bricks es un tema, no un plugin.** La retirada actual (`class-retire.php`) no sirve tal cual.

---

## 1. Qué hay, versión y formato

- **No hay código en `reference/`.** Bricks es un **tema** comercial. Esto vale sólo para Bricks 2.x (2.4.x según los "since"). El esquema descargable `/downloads/bricks-schema.zip` no lo inspeccioné.
- **Contenido.** Arreglos PHP serializados en `wp_postmeta`:

| Meta | Constante | Contenido |
|---|---|---|
| `_bricks_page_header_2` | `BRICKS_DB_PAGE_HEADER` | elementos de cabecera |
| `_bricks_page_content_2` | `BRICKS_DB_PAGE_CONTENT` | elementos de contenido |
| `_bricks_page_footer_2` | `BRICKS_DB_PAGE_FOOTER` | elementos de pie |
| `_bricks_page_settings` | `BRICKS_DB_PAGE_SETTINGS` | p. ej. `headerDisabled`, `scrollSnapType` |
| `_bricks_template_settings` | `BRICKS_DB_TEMPLATE_SETTINGS` | condiciones, sticky, popup… |
| `_bricks_template_type` | `BRICKS_DB_TEMPLATE_TYPE` | tipo de plantilla (los valores no están documentados en lo leído) |

- **Opciones globales** en `wp_options`, serializadas: `bricks_global_classes`, `bricks_global_variables`, `bricks_theme_styles` (`BRICKS_DB_THEME_STYLES`), `bricks_color_palette`, `bricks_breakpoints`, `bricks_components` y `bricks_pseudo_classes`. No aparece ninguna `bricks_global_settings`.
- **En el frontend:**
  - El id DOM es `brxe-{id}`. Los filtros y queries se refieren al id "sin el prefijo `#brxe-`".
  - Los loops imprimen marcas `<!--brx-loop-start-{id}-->`.
  - El CSS personalizado usa `%root%`.
  - Las clases de paleta son `.bricks-color-primary` y `.bricks-background-primary`.

## 2. Capacidades: Bricks frente a lo que ya trae Elements

| Capacidad Bricks | Equivalente en Elements (ruta) | Estado |
|---|---|---|
| Árbol plano `id/parent/children` | `_elementor_data` anidado. Contenedores atómicos `e-flexbox`, `e-div-block`, `e-grid` (`elementor/modules/atomic-widgets/elements/{flexbox,div-block,grid}/*.php:35-46`) o el Container clásico flex/grid | **Completo** (con conversión de estructura) |
| Ajustes CSS `_padding`, `_typography`… con sufijos `:breakpoint:estado` | Variantes de estilo atómico `{meta:{breakpoint,state},props}` (`atomic-widgets/styles/style-variant.php:36-42`). Conversor CSS→props: `Css_Converter::convert()` (`atomic-widgets/css-converter/css-converter.php:151`) y REST `POST elementor/v1/css-to-atomic` (`css-converter-rest-api.php:20-36`) | **Completo** para propiedades CSS soportadas por los convertidores (`css-converter/converters/*.php`) |
| Estados `:hover :active :focus` + personalizados | `Style_States`: hover, active, focus, focus-visible, checked y `e--selected/disabled/playing/paused` (`atomic-widgets/styles/style-states.php:6-15`). El resto va por `custom_css` de la variante (`styles-renderer.php:121-133`) | **Parcial** (`::before` o `:nth-child` sólo como CSS personalizado) |
| `selectors` 2.0 (`& > span`, `&::before`) | `custom_css` de la variante, que se inserta dentro del bloque del selector (`styles-renderer.php:133`). Funcionaría con anidamiento CSS nativo; falta verificar que la sanitización lo permita | **Parcial / por verificar** |
| Breakpoints `desktop`(base, 1279), `tablet_portrait` 991, `mobile_landscape` 767, `mobile_portrait` 478 | `mobile`, `mobile_extra`, `tablet`, `tablet_extra`, `laptop`, `widescreen` (`elementor/core/breakpoints/manager.php:18-23`), sólo desktop-first | **Parcial**: hay que reasignar anchos del kit. Un Bricks mobile-first (otra base) no tiene equivalente |
| Clases globales | CPT `e_global_class` (`global-classes/global-class-post-type.php:10`), metas `_elementor_global_class_id/_data/_data_preview` (`global-class-post.php:19-23`), orden/etiquetas en el kit `_elementor_global_classes` (`global-classes-repository.php:18-19`). CSS sólo de las clases usadas por el documento y sus embebidos (`atomic-global-styles.php:56,135`) | **Completo** para atómicos. Bricks: bloqueo, papelera (`deletedAt`), categorías y orden del Class Manager. Elementor: orden sí, categorías no vistas |
| Variables globales `{id,name,value,category,type}` | `_elementor_global_variables` en el kit, formato v2, máximo 1000 (`variables/storage/constants.php:10-13`). Tipos `global-color-variable`, `global-font-variable`, `global-size-variable` (`variables/prop-types/*.php:13`). El nombre CSS sale del `label` (`variables/classes/css-renderer.php:58`) | **Parcial**: valores que no son color, fuente o tamaño no tienen tipo |
| Paletas `bricks_color_palette` | Colores del kit (`__globals__`) o variables de color v4 | **Completo** (hay que reescribir `var(--bricks-color-*)`) |
| Theme styles con `conditions` | Kit único (tipografía, botones, enlaces, colores) | **Parcial**: sin estilos de tema condicionales |
| Componentes (props, variantes, slots, anidación) | Documento `elementor_component` y metas `_elementor_component_uid`, `_overridable_props`, `_variants`, `_is_archived` (`elementor/modules/components/documents/component.php:18-23`). Instancia `e-component` `{component_id, overrides}` (`components/prop-types/component-instance-prop-type.php:14,27-28`). Variantes en `components/variants/`; anidación validada por `circular-dependency-validator.php` | **Parcial**: **no hay slots** (no aparece "slot" en `components/`). Tampoco props tipo `query` ni `class` ni toggle "Hide element" |
| Componentes remotos (2.4) | Nada local. `elementor/modules/design-system-sync/` y las plantillas de red de DIGITALÍSIMO Tools son lo más cercano | **Faltante** (no necesario para migrar) |
| Interacciones (trigger/acción/destino) | Interacciones v4 sólo de animación. Disparadores base `load`, `scrollIn`; PRO `scrollOut`, `scrollOn`, `hover`, `click`. Efectos `fade/slide/scale/custom` (`elementor/modules/interactions/presets.php:18-32`, `validators/trigger-value.php:12-18`), con script PRO en `digitalisimo-elements/modules/interactions/hooks.php`. Además, animaciones de entrada clásicas (`_animation`, nombres Animate.css) | **Parcial**: faltan show/hide, set/toggle attribute, storage, JS, scrollTo, clearForm y mapa |
| Popups (plantilla + `showPopup/hidePopup`) | PRO `modules/popup/` con disparadores `page_load`, `scrolling`, `click`, `inactivity`, `exit_intent` (`popup/display-settings/triggers.php:31-122`) y `timing.php` | **Completo** en lo esencial. Faltan los límites por storage con la misma semántica |
| Offcanvas `toggleOffCanvas` | PRO `modules/off-canvas/` y `modules/digitalisimo-widgets/class-offcanvas.php` | **Completo** (hay que mapear el disparador) |
| Plantillas header/footer/single/archive/search/error/section | `modules/theme-builder/documents/{header,footer,single-post,single-page,archive,search-results,error-404,section}.php` | **Completo**. **Faltan**: plantilla de protección por contraseña y cabecera lateral izquierda/derecha |
| Condiciones de plantilla (incluir/excluir y especificidad) | `_elementor_conditions` + caché `elementor_pro_theme_builder_conditions` (`theme-builder/classes/conditions-cache.php:15,114`) y `theme-builder/conditions/*.php` | **Completo** |
| Condiciones de elemento (OR de AND) | PRO `e_display_conditions` (`modules/display-conditions/module.php:172`) con 22 condiciones (`display-conditions/conditions/*.php`) y `and/or-condition.php`. Propias: `modules/digitalisimo-conditions/class-visibility-rules.php:15-17` (os, browser, url_parameters, ex_url/referer, language, country, visit_count, WooCommerce, ACF) | **Casi completo**: `post_id` lo cubre la regla propia `post` |
| Datos dinámicos `{tag:filtro}` | Dynamic tags clásicos y atómicos `{$$type:'dynamic', value:{name,group,settings}}` (`atomic-widgets/dynamic-tags/dynamic-prop-type.php:78-98`) | **Parcial**: no hay interpolación de varios tags en un texto ni filtros encadenados |
| Query loop en cualquier contenedor | `e-collection-loop` con hijos en línea (`modules/collection-loop/elements/collection-loop/collection-loop.php:56`), paginación y estado vacío (`collection-loop/elements/*`), constructores posts/terms/users (`atomic-widgets/query/*-query-builder.php`). Loop Grid clásico con plantilla Loop Item (`modules/loop-builder/`) | **Parcial**: sin loops API ni array y sin editor PHP |
| Global queries (2.1) | No existe | **Faltante** |
| Query filters (checkbox, radio, select, range, fecha, búsqueda, activos, submit/reset, orden, por página, índice) | PRO `modules/loop-filter/widgets/taxonomy-filter.php`. URL `e-filter-{id 7 car.}-{taxonomía}` (`loop-filter/module.php:172-178`), AJAX `data/endpoints/refresh-loop.php` | **Parcial**: sólo taxonomía |
| Carga de recursos (inline o archivos en `uploads/bricks/css`) | CSS por post de Elementor y archivos atómicos (`atomic-widgets/styles/css-files-manager.php`). CSS por widget del registro propio (`modules/digitalisimo-widgets/class-registry.php:18-21`) | **Completo** |
| Cascade layers (`bricks.reset` < `bricks`; `bricks.gutenberg/icons/libs`) | Elementor no usa `@layer` (no aparece en `elementor/assets/css` ni en `modules`) | **No aplica**, pero es un riesgo de especificidad (§5) |
| Importar/exportar global (ZIP + `manifest.json`) | Kit de Elementor: `elementor/app/modules/import-export-customization/runners/import/{elementor-content,site-settings,templates,taxonomies,wp-content,plugins}.php` y el runner de clases `global-classes/import-export/import-runner.php:30-55` (JSON `{items, order}`) | **Reutilizable** |

## 3. Formatos de datos exactos (según la documentación)

**Elemento:** campos `id`, `name`, `parent`, `children`, `settings`, `selectors`, `label`, `themeStyles`.
- `id`: 6 caracteres alfanuméricos, y en `connections` `^[a-z0-9]{6}$`.
- `parent`: `0` en la raíz.
- `children`: arreglo ordenado de ids.

Forma descrita: una lista plana de elementos; la raíz tiene `parent` 0 y `children` con los ids de sus hijos; cada hijo apunta a su padre por id. Un encabezado, por ejemplo, lleva en `settings` su texto y su etiqueta, la lista `_cssGlobalClasses` con ids de clases globales y un ajuste de relleno con sufijo de breakpoint (`_padding:mobile_portrait` con `top` y `bottom`). `selectors` es una lista de selectores relativos (`& > span`) con sus propios `settings`.

**`settings` tiene tres capas:**
- Controles propios del elemento (`text`, `tag`, `link`…).
- CSS heredado con prefijo `_`: `_typography`, `_padding`, `_margin`, `_background`, `_border`, `_boxShadow`, `_direction`, `_justifyContent`, `_alignItems`, `_columnGap`, `_rowGap`, `_display`, `_gridTemplateColumns`, `_width`, `_widthMax`, `_position`, `_zIndex`, `_cssId`, `_cssClasses`, `_cssCustom`, `_attributes`.
- Metaajustes: `_cssGlobalClasses` (ids), `_conditions`, `_interactions`, `_hideElementBuilder`, `_hideElementFrontend`, `_attributes`.

Los sufijos se combinan: `_typography:tablet_portrait:hover`.

**Formas de valor:**
- **spacing:** `{top,right,bottom,left}`, cadenas. El ejemplo `"40"` va sin unidad, así que la unidad por defecto debe confirmarse con muestras.
- **typography:** `font-family`, `font-size`, `font-weight`, `font-style`, `line-height`, `letter-spacing`, `text-align`, `text-transform`, `text-decoration`, `color`.
- **color:** uno de `{hex}`, `{rgb}`, `{hsl}` o `{raw}`. En paletas también lleva `{id, raw:"var(--bricks-color-x)", light, dark?}`.
- **background:** `color`, `image{id,filename,url,size,full,useDynamicData}`, `video{source:media|youtube|vimeo|url, id, url, youtubeId, vimeoId, autoplay, loop, muted, controls}`, `size`, `position`, `repeat`, `attachment`. **No tiene gradiente** en el esquema.

**Clase global:**
- Campos: `{id, name, category, settings, selectors, modified, user_id, deletedAt}`. Sólo `id` y `name` son obligatorios.
- El nombre se guarda sin el punto.

Una clase global guarda en `settings` los mismos ajustes que un elemento (por ejemplo un `_background` cuyo color es una variable de la paleta `var(--bricks-color-…)`, o un relleno con sufijo de breakpoint), más la fecha de modificación y el usuario.

**Variable:** `{id, name, value, category, type}`. El nombre va sin `--` (ejemplo: `spacing-xl` = `80px`). Los valores de `type` no están documentados.

**Breakpoints:** lista de objetos con `key`, `width` e `icon`; el base (`base: true`) es `desktop` (1279) y los demás `tablet_portrait` (991), `mobile_landscape` (767) y `mobile_portrait` (478).

**Pseudoclases:** `[":hover",":active",":focus"]`.

**Theme styles:** `{"<id>":{label, settings:{conditions:{conditions:[{id,main:"any"}]}, colors:{colorPrimary}, section:{…}, heading:{_typography}, button:{…}}}}`.

**Componente (`bricks_components`):**
- Campos: `{id, category, desc, elements[], properties[], propertyGroups[{id,name}], variants[{id:"variant-…", name}], _created, _user_id, _version, blockEditor, blockCategory, blockIcon, blockPreviewImage}`.
- El id de la raíz es igual al id del componente.
- **Propiedad:** `{id, label, type, connections{elementId:[settingKeys]}, default, desc, group, options[{id,label,value}], multiple, replace}`.
- **Tipos de propiedad:** `text`, `editor`, `icon`, `image`, `image-gallery`, `link`, `select`, `toggle`, `query`, `class`.

Ejemplo descrito: un componente cuya raíz es un botón (con el mismo id que el componente) declara una propiedad de tipo `text` cuya `connections` apunta al ajuste `text` de ese botón.

**Instancia en el área de contenido:**
- Campos: `cid` (componente), `pid`, `instanceId`, `variant` (`^variant-…`), `parent`, `children`, `slotChildren{slotId:[ids]}` y `properties{propId: valor | "parent:cid_X:prop_Y"}`.
- El elemento Slot es `name:"slot"`, anidable.

**Condiciones de elemento:**
- `_conditions` es un OR de AND: `[[{id,key,compare,value,dynamic_data?}, …], […]]`.
- Claves:
  - Generales: `browser`, `current_url`, `date`, `datetime`, `dynamic_data`, `featured_image`, `operating_system`, `referer`, `time`, `weekday`.
  - Post: `post_author`, `post_date`, `post_id`, `post_parent`, `post_status`, `post_title`.
  - Usuario: `user_id`, `user_logged_in`, `user_registered`, `user_role`.
  - WooCommerce: unas 20 con prefijo `woo_*`.
- Operadores: `== != >= <= > < contains contains_not empty empty_not`.
- Se evalúan en el servidor: si fallan, el HTML no se imprime.
- API de extensión: `bricks/conditions/{groups,options,result}`.

**Interacciones:**
- `_interactions` es una lista plana: `{id, trigger, action, target:"self"|id|selector, targetSelector, delay, runOnce, rootMargin, animationType, animationDuration, animationDelay, jsFunction, jsFunctionArgs, scrollToOffset, templateId, formId, ajaxQueryId, offCanvasSelector, filterElementId, loadMoreQuery, actionAttributeKey, actionAttributeValue, disablePreventDefault, storageType, popupContextId, interactionConditions[{conditionType,storageKey,storageCompare,storageCompareValue}], interactionConditionsRelation:"and"|"or"}`.
- **Disparadores:** `click`, `mouseover`, `focus`, `blur`, `mouseenter`, `mouseleave`, `enterView`, `leaveView`, `animationEnd`, `ajaxStart`, `ajaxEnd`, `formSubmit`, `formSuccess`, `formError`, `scroll`, `contentLoaded`, `mouseleaveWindow`, `filterOptionEmpty`, `filterOptionNotEmpty`, eventos `woo*`. En popups: `showPopup`, `hidePopup`.
- **Acciones:** `show`, `hide`, `click`, `setAttribute`, `removeAttribute`, `toggleAttribute`, `toggleOffCanvas`, `loadMore`, `loadMoreGallery`, `startAnimation` (Animate.css), `scrollTo`, `javascript`, `openAddress`, `closeAddress`, `clearForm`, `storageAdd`, `storageRemove`, `storageCount`.
- **Eventos JS:** `bricks/animation/end/{id}`, `bricks/ajax/start`, `bricks/form/{submit,success,error}`.

**Plantillas:**
- `_bricks_template_settings.templateConditions` es un repetidor `[{id, main}]`.
- Valores de `main`: `any`, `frontpage`, `postType`, `archiveType`, `search`, `error`, `ids`. Los subcampos no están en el esquema.
- Especificidad, de más a menos: individual > hijos, términos y archivos seleccionados > front page > archivo o tipo amplio > tipo de post > sitio entero. Cualquier condición puede ser de inclusión o exclusión.
- Claves de cabecera: `headerPosition` (left/right), `headerSticky`, `headerStickyOnScroll`, `headerStickySlideUpAfter`, `headerStickyScrolling*`, `headerWidth`.
- Claves de popup (~50): `popupCloseOn` (`backdrop|esc|none`), `popupShowAt`, `popupShowOn`, `popupLimitLocalStorage`, `popupLimitSessionStorage`, `popupLimitTimeStorage`, `popupLimitWindow`, `popupAjax`, `popupZindex`.
- Claves de protección por contraseña: `passwordProtection*`.
- Interacciones de plantilla en `template_interactions`.

**Datos dinámicos:**
- Sintaxis: `{tag}`, `{tag:filtro:filtro}`, `{tag @clave:valor}`, `{echo:fn('arg')}` (lista blanca `bricks/code/echo_function_names`), `{do_action:hook}`.
- Ejemplos: `{post_title:link}`, `{post_excerpt:55}`, `{featured_image:large:link}`, `{post_terms_<tax>:plain}`, `{cf_<meta>}`, `{acf_*}`, `{term_meta:k}`, `{author_meta:k}`, `{url_parameter:k}`, `{query_loop_index @pad:3}`, `{query_results_count:<id>}`, `{current_date:Y-m-d}`, `{format_date @date:'…' @from:'…' @to:'…'}`.
- El filtro `:value` compara el valor guardado y no la etiqueta.

**Query filters:**
- Tablas propias de índice (sin nombre documentado).
- URL `brx_{FILTERID}=valor` o un "nice name".
- Historial back/forward por AJAX.
- Sólo loops de posts, términos y usuarios. No funcionan dentro de componentes salvo que la raíz del componente sea el loop.

**Consultas globales:** se guardan en los datos globales y el loop guarda sólo el id. **Ni la opción ni la clave están documentadas.**

**Exportación global:** un ZIP con `manifest.json` más un JSON por tipo. **Las claves no están documentadas.** El sobre JSON de una plantilla (`content`, `pageSettings`, `templateType`) **no está verificado**: la búsqueda tampoco lo confirmó.

## 4. Licencia y qué se puede reutilizar

- No hay código de Bricks: **sólo se reutiliza el formato de datos** (interoperabilidad) y las reglas de comportamiento documentadas.
- No se copian textos de Academy, CSS por defecto (`frontend*.min.css`), JS, iconos ni el ZIP de esquemas. No verifiqué la licencia de ese ZIP: se puede usar para validar en pruebas, pero no debe ir en nuestros paquetes.
- Registrado en `docs/third-party-licenses.md`: «Bricks: sólo documentación pública, sin código».
- Del lado Elementor, todo lo citado es GPL-3.0 y ya se distribuye. Se reutiliza llamándolo, sin editar `elementor/`.

## 5. Riesgos

1. **Bricks es un tema.** `class-retire.php:18-24` sólo desactiva plugins. Al cambiar de tema, `_bricks_page_*` deja de renderizarse, así que la conversión debe ocurrir **antes** de cambiar el tema y debe guardar `stylesheet` y `template` para revertir. El código del tema hijo (elementos propios con `\Bricks\Elements::register_element`, funciones `echo`) no se convierte.
2. **Ids.** Los de Bricks tienen 6 caracteres `[a-z0-9]`. Elementor usa 7: el regex del filtro (`loop-filter/module.php:173`) exige `[a-z0-9]{7}`. Hay que remapear con una tabla y reescribir:
   - `target` y `targetSelector` de las interacciones;
   - el destino de los filtros;
   - `{query_results_count:id}`;
   - `formId`;
   - `#brxe-xxxxxx` y `%root%` en `_cssCustom` y en las clases.
3. **Especificidad.** Bricks deja sus valores por defecto en `@layer bricks`, así que cualquier CSS sin capa le gana. Elementor no usa capas. El CSS personalizado migrado puede perder o ganar distinto, por lo que hace falta comparación visual.
4. **Breakpoints.** Reproducir 991/767/478 exige cambiar los anchos del kit (`viewport_tablet`, `viewport_mobile_extra`, `viewport_mobile`). Eso afecta a todo el contenido Elementor del sitio, así que sólo es seguro en sitios cuyo contenido viene de Bricks. Un sitio Bricks mobile-first no tiene equivalente.
5. **Clases globales v4 sólo en atómicos.** El selector usa `cssName` o el id interno (`styles-renderer.php:106`), y el CSS se emite según la relación con el documento (`atomic-global-styles.php`). Un `_css_classes` clásico que nombre `.hero-section` probablemente no recibirá ese CSS. Hay que verificar si `cssName` toma la etiqueta.
6. **El atómico v4 debe estar habilitado** en el sitio destino (`elementor/modules/atomic-opt-in/`). Su catálogo de widgets es corto (heading, paragraph, button, image, svg, divider, youtube, video, tabs, accordion, form, list).
7. **Sin slots ni props `query`/`class`** en los componentes de Elementor: los componentes Bricks que los usen pierden el vínculo.
8. **Interacciones de acción.** Show/hide, atributos y storage no existen en Elementor. Crear un runtime de acciones sería un motor paralelo que hay que decidir de forma explícita.
9. **Código ejecutable.** `{echo:…}`, query editor PHP, acción `javascript` y `do_action` no deben convertirse automáticamente: hay que decidir si son seguros.
10. **Formatos no documentados:** ver §6.4. Además, la base de las unidades (`"40"` sin `px`) y el estilo por defecto de cada elemento Bricks (ancho de `.brxe-container`, dirección, gaps) no están en la documentación, así que la paridad visual necesita capturas.

## 6. Recomendaciones de integración

### 6.1 Arquitectura (sin motores paralelos)

- **Un solo conversor** en Elements, por ejemplo `digitalisimo-elements/modules/digitalisimo-import/formats/bricks/`, que:
  - lee `_bricks_*` y las opciones `bricks_*`;
  - produce `_elementor_data` atómico más clases, variables y componentes de Elementor.
- **Migración lo usa por etapas:**
  - `legacy/class-runner.php:45`: añadir una etapa `bricks` (o un "origen" dentro de `convert`). Hoy `class-migration.php:308` sólo consulta `_elementor_data`, así que hace falta un recolector de posts con `_bricks_page_content_2`.
  - `class-retire.php`: añadir la retirada de **tema** (cambio reversible al tema DIGITALÍSIMO) y desactivar plugins de Bricks.
- **Importar plantillas o kits Bricks** = convertir y luego **reutilizar** los runners del kit (`elementor/app/modules/import-export-customization/runners/import/*.php`; clases con el formato `{items, order}` de `global-classes/import-export/import-runner.php`).
- **Estilos:** los ajustes Bricks se traducen a declaraciones CSS (`_padding` → `padding`…) y pasan por `Css_Converter::convert()` para obtener props atómicos. No hay que escribir otro mapeador de propiedades.
- **Mapas declarativos por elemento**, al estilo de `legacy/maps/bdt-*.php` (`target`, `classes`, `defaults`, `drop`): `maps/bricks-heading.php`, `bricks-button.php`, etc.
- **Reversibilidad:** no borrar nunca `_bricks_*`. Revertir = borrar las metas `_elementor_*` creadas y restaurar el tema.

### 6.2 Qué se convierte automáticamente

- **Estructura:**
  - `section`, `container`, `block`, `div` → `e-flexbox`, `e-div-block` o `e-grid` (según `_display`).
  - `heading` → `e-heading`; `text` → `e-paragraph`; `button` → `e-button`; `image` → `e-image`; video → `e-youtube` o `e-self-hosted-video`.
  - `slider-nested` → Slider Optimizado (`modules/digitalisimo-slider/`); `tabs-nested` y `accordion-nested` → `atomic-tabs` y `atomic-accordion`.
- **Estilo:**
  - Sufijos `:tablet_portrait`, `:mobile_landscape`, `:mobile_portrait` → `meta.breakpoint` `tablet`, `mobile_extra`, `mobile`.
  - `:hover`, `:active`, `:focus` → `meta.state`.
  - El resto de estados y los `selectors` → `custom_css`.
- **Sistema de diseño:**
  - `bricks_global_classes` → `e_global_class`, con `_cssGlobalClasses` como prop `classes`.
  - `bricks_global_variables` y `bricks_color_palette` → variables v4 de color, fuente y tamaño, reescribiendo `var(--bricks-color-*)`.
  - Theme style con `main:"any"` → kit.
- **Plantillas:**
  - header, footer, archive, search, error, single y section → documentos de `modules/theme-builder/documents/`.
  - `templateConditions` → `_elementor_conditions`: `any` → `include/general`, `frontpage` → `include/singular/front_page`, `error` → `include/singular/not_found404`, `search` → `include/archive/search`.
  - Popup → `modules/popup/` con `popupCloseOn` y disparadores de página.
- **Condiciones de elemento:**
  - Con `e_display_conditions`: `user_logged_in` → login-status, `user_role` → user-role, `post_title` → post-title, `post_parent` → page-parent, `post_author` → post-author, `post_date` → date-of-publish, `featured_image`, `weekday` → day-of-the-week, `date`/`time` → current-date / time-of-the-day, `referer` → `from_url`, `user_registered`.
  - Con `digitalisimo-conditions`: `browser`, `operating_system`, `current_url`, `post_id`.
  - Hay que conservar la estructura OR de AND.
- **Interacciones:**
  - `enterView`, `contentLoaded` o `leaveView` + `startAnimation` → `scrollIn`, `load` o `scrollOut` (o `_animation` clásico, que usa los mismos nombres Animate.css).
  - `click` + popup → acción de popup de PRO.
  - `toggleOffCanvas` → off-canvas.
  - `scrollTo` → enlace ancla.
  - `loadMore` → paginación "load more" de `e-collection-loop`.
- **Datos dinámicos:** un tag que ocupa todo el campo → tag equivalente (`post-title`, `post-excerpt`, `post-featured-image`, `post-custom-field` o ACF, `request-parameter`, `current-date-time`). Prefijo o sufijo fijo → `before`/`after`.
- **Query loop** posts/terms/users → `e-collection-loop` con hijos en línea. Esto requiere conocer las claves del loop (§6.4).
- **Filtro de taxonomía** checkbox, radio o select → `taxonomy-filter`, con URL `e-filter-{id}-{tax}`.

### 6.3 Qué no se convierte (va al informe de Migración, como `unsupported.php`)

- Slots de componentes y props `query`/`class`: se desvinculan e incrustan el árbol.
- Variantes de componente: hasta tener muestras.
- Componentes remotos y bloques Gutenberg.
- Consultas globales: se copian en línea en cada loop, igual que "Disconnect".
- Loops API o array y query editor PHP.
- Filtros range, datepicker, search, campo personalizado, activos, submit/reset, orden, por página e índice.
- Interacciones show/hide, atributos, storage, JS, `clearForm` y mapa.
- `{echo:}`, `{do_action:}`, varios tags en un mismo texto y filtros encadenados.
- Theme styles condicionales, cabecera lateral y plantilla de contraseña.
- Elementos propios del tema hijo y CSS que dependa de `@layer bricks`.

### 6.4 Qué se verifica sólo con la documentación y qué exige muestras reales

- **Basta con la documentación:**
  - sobre del elemento, árbol plano y `parent:0`;
  - las seis metas y las siete opciones;
  - gramática de sufijos;
  - esquemas de clase global, variable (claves), paleta, breakpoints y pseudoclases;
  - definición de componente, tipos de prop, patrón de `connections` y campos de instancia (`cid`, `properties`, `variant`, `slotChildren`, referencia `parent:cid_…:prop_…`);
  - `_conditions` y `_interactions` completos;
  - formas typography, background y spacing;
  - valores de `main` en `templateConditions` y claves de cabecera y popup;
  - sintaxis de tags dinámicos.
- **Exigen muestras de exportación o de base de datos:**
  - claves del query loop. Ni `container.json` ni `controls/query.json` las incluyen: este último sólo trae claves de API (`objectType`, `api_*`, `pagination_*`);
  - subcampos de `templateConditions` (`ids`, `postType`, `archiveType`, `terms`, `exclude`) y valores de `_bricks_template_type`;
  - sobre JSON de plantilla exportada y `manifest.json` del ZIP global;
  - dónde se guardan los ajustes de cada variante de componente;
  - grupos completos de theme styles;
  - claves de los elementos de filtro (`filterQueryId` no está verificada) y opción y clave de las consultas globales;
  - formas de `link`, `image`, `icon`, `_border`, `_boxShadow` y `_attributes`;
  - `type` de las variables y categorías;
  - unidad por defecto de los números;
  - meta de modo editor (`_bricks_editor_mode`, no documentada en lo leído) y opción legacy de elementos globales;
  - estilos por defecto de cada elemento para la paridad visual.

**Primer paso recomendado:** pedir a un sitio Bricks real la exportación global (ZIP) y un volcado de `_bricks_*` y `bricks_*` de 3 a 5 páginas representativas (loop, filtros, componente con slot, popup, cabecera sticky). Con eso se cierran las dudas antes de escribir los mapas.

Sources:
- [Bricks Data Model](https://academy.bricksbuilder.io/developer/schema/)
- [schema.md](https://academy.bricksbuilder.io/developer/schema.md)
- [Global classes schema (JSON)](https://academy.bricksbuilder.io/schema-resolved/global/global-classes.json)
- [Global Class Manager](https://academy.bricksbuilder.io/builder/styling/global-class-manager/)
- [Respira Bricks reference](https://docs.respira.press/docs/builders/bricks)
- [Create your own elements](https://academy.bricksbuilder.io/developer/elements/create-your-own-elements/), [Nestable elements](https://academy.bricksbuilder.io/developer/elements/nestable-elements/), [Components](https://academy.bricksbuilder.io/builder/features/components/), [Global CSS classes](https://academy.bricksbuilder.io/builder/styling/global-css-classes/), [Cascade layer](https://academy.bricksbuilder.io/builder/styling/cascade-layer/), [Asset loading](https://academy.bricksbuilder.io/developer/guides/asset-loading/), [Dynamic data](https://academy.bricksbuilder.io/builder/dynamic-content/dynamic-data/), [Query loop](https://academy.bricksbuilder.io/builder/dynamic-content/query-loop/), [Global queries](https://academy.bricksbuilder.io/builder/dynamic-content/global-queries-query-manager/), [Query filters](https://academy.bricksbuilder.io/builder/dynamic-content/query-filters/), [Element conditions](https://academy.bricksbuilder.io/builder/features/element-conditions/), [Interactions](https://academy.bricksbuilder.io/builder/features/interactions/), [Template settings](https://academy.bricksbuilder.io/builder/features/template-settings/), [Remote components](https://academy.bricksbuilder.io/builder/features/remote-components/), [Global import/export](https://academy.bricksbuilder.io/builder/features/global-import-export/)

---

## Decisiones posteriores (plan aprobado)

- **Destino clásico (v3).** Las clases de Bricks se incrustan como estilos de cada instancia; las repetidas pueden ir a presets de estilo del kit (F7a). En sitios con v4 activo se usan clases globales v4 con `Css_Converter`. Nunca se activa v4 sin permiso.
- **Componentes** a plantilla de biblioteca + widget Template con sobrescrituras de contenido (F7b); en v4, Components del núcleo. Los slots pierden el vínculo y se declara.
- **Interacciones con destino** (mostrar u ocultar, atributos, ir a, popup, off-canvas, cargar más): registro propio `_digitalisimo_actions` en Elements, con lista blanca y sin JS arbitrario (F7b). `javascript`, `{echo:}`, `{do_action:}` y el query editor PHP nunca se convierten ni se ejecutan.
- **Consultas guardadas, contextos y filtros ampliados** (búsqueda, meta o rango, orden, por página, con `popstate`) se construyen en F7b, antes de F6.
- **Slider anidado:** widget nuevo que comparte hojas y scripts del Slider Optimizado; pestañas y acordeones anidados van a nested-tabs y nested-accordion del núcleo.
- **Tema:** borradores + activación del tema por el administrador en producción; sin tema hijo (el código del hijo de Bricks no se convierte; el PHP propio va al editor de fragmentos de Tools).
- **Mapa de IDs** de 6 a 7 caracteres en `digitalisimo_migration_idmap`, que reescribe destinos de filtros, interacciones, anclas y CSS.
- **Aceptación:** contra capturas y HTML de los sitios reales en tres anchos, porque Bricks no se puede ejecutar.
