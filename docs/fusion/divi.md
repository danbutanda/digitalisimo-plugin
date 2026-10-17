# Informe del frente Divi: `reference/Divi.zip` (tema Divi 5.15.0, Elegant Themes)

> Revisión del 16 de octubre de 2026, depurada para el repositorio público. Se leyó con `unzip -p` / `unzip -Z1`, sin extraer ni ejecutar nada. Las rutas que empiezan por `Divi/` son internas del ZIP y los números de línea, de `unzip -p archivo | grep -n`. No se reproduce código de Divi ni de sus bundles compilados: sólo rutas, claves y formatos. Donde el plan aprobado decidió otra cosa, manda `docs/FUSION_OBJECTIVE.md`; las diferencias están al final, en «Decisiones posteriores».

---

## 1. Qué hay, versión y formato

- **Cabecera** (`Divi/style.css`): Divi 5.15.0. Pide WordPress 6.0 o superior y PHP 7.4. La cabecera dice «GNU General Public License v2»; `LICENSE.md` añade «or (at your option) any later version» (ver §4). El ZIP tiene 3376 entradas (≈131 MB sin comprimir).
- **Dos motores conviven en el tema:**
  - **Divi 4 (D4)** en `Divi/includes/builder/` (862 archivos). Incluye el framework de shortcodes `ET_Builder_Element`, el Theme Builder (`frontend-builder/theme-builder/`), la Biblioteca (`feature/Library.php`) y la portabilidad (`Divi/core/components/Portability.php`).
  - **Divi 5 (D5)** en `Divi/includes/builder-5/` (1980 archivos):
    - `server/` es PHP con namespace `ET\Builder\…`.
    - `visual-builder/build/` son bundles JS ya compilados, sin fuente TypeScript.
    - `visual-builder/packages/module-library/src/components/<slug>/` trae por módulo `module.json`, `conversion-outline.json`, `module-default-render-attributes.json` y `module-default-printed-style-attributes.json` (115/92/113/113 archivos).
  - D5 sigue cargando el framework D4 para contenido no convertible (`Packages/Conversion/Conversion.php:98-117`, `et_load_shortcode_framework()`).
- **Tablas autogeneradas reutilizables como datos** en `Divi/includes/builder-5/server/`:
  - `_all_modules_metadata.php` (3.1 MB): 115 módulos con `name`, `d4Shortcode`, `category`, `childrenName` y `attributes`.
  - `_all_modules_conversion_outline.php`.
  - `_all_modules_default_render_attributes.php`.
  - `_all_modules_default_printed_style_attributes.php`.
- **Migrador propio D4→D5:**
  - `Divi/includes/builder-5/server/Packages/Conversion/` (`Conversion.php` de 3393 líneas, `AdvancedOptionConversion.php`, `ValueExpansion.php`, `ShortcodeMigration.php`, `LegacyAttributeNames.php`, `DeprecatedAttributeMapping.php`).
  - `server/Migration/` (≈30 migraciones versionadas de contenido y presets, por ejemplo `FlexboxMigration.php`, `flexbox.v1`, `5.0.0-public-alpha.18.2`).
  - `Divi/d5-readiness/server/Conversion.php`: convierte post por post.
- **Íconos:** `visual-builder/packages/icon-library/src/components/icon-font/iconList.json` tiene 1989 íconos: 380 propios de Divi (fuente ETmodules) y 1609 de Font Awesome, cada uno con `unicode`, `name`, `styles` y `fontWeight`.
- **Divisores y máscaras:** `divider-library/src/components/*.json` y `mask-and-pattern-library/src`.
- **Fuera de alcance:** `ai-app/`, `cloud/` (Divi Cloud), `onboarding/`, `epanel/` (opciones del tema), `core/code-snippets/` (CPT `et_code_snippet`).

---

## 2. Capacidades: qué tiene Divi y qué tiene hoy Elements

| Capacidad | Divi (dónde) | En Elements hoy | Estado para fusionar |
|---|---|---|---|
| Estructura sección/fila/columna, flex y grid | `ModuleLibrary/{Section,Row,RowInner,Column,ColumnInner,Group}`; `StyleLibrary/Declarations/Layout/Layout.php` (display flex/grid, `gridTemplateColumns`, `gridColumnCount`, `gridAutoFlow`, `columnGap`…) | Container Flexbox/Grid del núcleo (`digitalisimo-elements/elementor/includes/elements/container.php:94-103`, `container_type`) | **Completo** |
| Presets de módulo y de grupo de opciones, apilables, con prioridad | `Packages/GlobalData/GlobalPreset*.php` | No hay equivalente directo. Clases globales v4 solo para widgets atómicos (`elementor/modules/global-classes`). Kit elements defaults solo se aplica en el editor (`kit-elements-defaults/module.php:17`) | **Parcial**: aplanar en cada instancia |
| Colores globales (con variantes HSL relativas) | `GlobalData.php` (`et_global_data`) | Colores del kit (`system_colors`/`custom_colors`) y variables v4 (`_elementor_global_variables`) | **Parcial**: las variantes HSL se resuelven a literal |
| Variables (numbers, strings, images, links, fonts, gradients) | `GlobalData.php:821-1034` | Variables v4 de color, fuente y tamaño (`elementor/modules/variables`) | **Parcial**: los widgets v3 no pueden referenciarlas |
| Elementos globales con sincronía selectiva y slots | `divi/global-layout`, `divi/global-slot`, `GlobalLayout.php` | Global Widget (`modules/global-widget`, `global`), widget Template (`template`). Components v4 aceptan solo atómicos (`elementor/modules/components/non-atomic-widget-validator.php`) | **Parcial**: se pierden las sobrescrituras locales y los slots |
| Theme Builder (cabecera, cuerpo, pie y condiciones) | `includes/builder/frontend-builder/theme-builder/*` | Theme Builder Pro (`modules/theme-builder`, `_elementor_conditions`) | **Completo** salvo «archivo de autor por rol» |
| Condiciones de visualización (~25 tipos) | `Module/Options/Conditions/Conditions.php:260-2400` | `modules/digitalisimo-conditions` (browser, país, fecha/hora, login, os, post_type, url_parameters, user_role, woocommerce), `modules/display-conditions` | **Parcial**: faltan cookie, número de vistas, visitas de página, campo personalizado, autor, categorías/etiquetas, stock y resultados de búsqueda |
| Interacciones (click, load, mouseEnter/Exit, viewport y breakpoint Enter/Exit; efectos de clase, atributo, cookie, preset, visibilidad, scrollToElement, tilt, transformaciones, canvas) | `visual-builder/build/script-library-interactions.js`; `Options/Interactions/*` | Interactions v4 (load, scrollIn/Out/On, click, hover; fade/slide/scale) solo atómicos; Motion Effects Pro | **Parcial / faltante** |
| Loop en cualquier módulo y filtros de posts | `Options/Loop/LoopUtils.php` (`module.advanced.loop.desktop.value.{enable,queryType,postType,loopId…}`), `post-filter` | Loop Grid/Carousel y Taxonomy Filter (Pro) | **Parcial**: hace falta crear la plantilla de ítem |
| Off-canvas | CPT `et_pb_canvas` (`VisualBuilder/OffCanvas/OffCanvasHooks.php:41`), `divi/canvas-portal` | `off-canvas` (Pro), `digitalisimo-offcanvas` | **Parcial** |
| Sticky con estilos por estado | `Options/Sticky/*`, estado `sticky` en atributos | Sticky Pro sin estilos por estado | **Parcial** |
| Efectos de scroll | `Options/Scroll/*` | Motion Effects Pro | **Parcial** |
| Fondos (degradado de N paradas, parallax, video, patrón, máscara) | `AdvancedOptionConversion.php:48-91` | Fondo v3 (degradado de 2 paradas, video, slideshow); máscaras de Element Pack solo en Migración (`digitalisimo-migration/legacy/class-masks.php`) | **Parcial** |
| Divisores SVG de sección | `divider-library` | Divisores de forma del núcleo, `class-divider-shapes.php`, `legacy/class-shapes.php` | **Parcial**: el juego de formas es distinto |
| Contenido dinámico (+ACF, WooCommerce) | `Layout/Components/DynamicContent/*` (≈60 opciones) | Etiquetas dinámicas Pro | **Casi completo** |
| WooCommerce (25 módulos) | `ModuleLibrary/WooCommerce/*` | Widgets `woocommerce-*` Pro | **Parcial**: carrito y checkout son monolíticos en Pro |
| Breakpoints configurables (7) | `Framework/Breakpoint/Breakpoint.php:29-145` | 6 breakpoints del kit (767/880/1024/1200/1366/2400, `elementor/core/breakpoints/manager.php:315-340`) | **Parcial**: los valores no coinciden |
| A/B testing | `_et_pb_use_ab_testing`, `_et_pb_ab_*` | — | **Faltante** |
| Tooltip, Dropdown, Instagram, Imagely | módulos D5 | — | **Faltante** |

---

## 3. Formatos de datos exactos

### 3.1 Contenido Divi 5 en `post_content`

Es un árbol de comentarios de bloque al estilo Gutenberg, envuelto en `divi/placeholder` (`Conversion.php:1829, 1911`). Hay un ejemplo real en `Packages/WooCommerce/WooCommerceHooks.php:1246`. Descrito:

- `divi/placeholder` → `divi/section` → `divi/row` → `divi/column` → módulo (por ejemplo `divi/woocommerce-cross-sells`), cada uno con su JSON de atributos en el comentario de apertura y su comentario de cierre.
- La sección lleva `builderVersion`.
- La fila declara `module.advanced.columnStructure` (por ejemplo `1_2,1_2`) y `module.advanced.flexColumnStructure` por breakpoint, y `module.decoration.layout` con `flexWrap` distinto por breakpoint (`desktop`, `phoneWide`, `phone`).
- La columna declara `module.advanced.type` (`1_2`) y `module.decoration.sizing.flexType` por breakpoint (`12_24` en escritorio, `24_24` en teléfono).

**Ruta de un atributo:** `<elemento>.<innerContent|decoration|advanced>.<opción>.<breakpoint>.<estado>[.subpropiedad]`.
- Breakpoints: `desktop | tablet | phone | phoneWide | tabletWide | widescreen | ultraWide`.
- Estados: `value | hover | sticky | focus | checked | active` (`Conversion.php:986-1110`).
- Ejemplos: `module.decoration.background.desktop.hover.color`, `title.decoration.font.font.tablet.value.size`, `content.innerContent.desktop.value`, `button.innerContent.desktop.value.{text,linkUrl,linkTarget}`, `image.innerContent.desktop.value.{src,alt,titleText,linkUrl}`.

**Claves raíz de cada bloque:**
- `builderVersion`.
- `modulePreset`: string o array (pila).
- `groupPreset`: `{ "<groupId p. ej. title.decoration.font>": {"presetId":["…"],"groupName":"divi/font"} }`.
- `globalModule`, `globalParent`, `localAttrs`, `localAttrOverridePaths`, `localAttrsMap`, `localChildren`.
- `unknownAttributes`, `nonconvertible`, `shortcodeName`.
- `css.desktop.value.{mainElement,before,after,freeForm}`: el CSS libre usa el token `selector` (`Conversion.php:799-830`).
- `module.advanced.htmlAttributes` (id/clase), `module.meta.adminLabel`.

**Grupos de decoración** (por ejemplo los de section, `module.json`): `animation, attributes, background, border, boxShadow, conditions, disabledOn, filters, interactions, layout, overflow, order, position, scroll, sizing, spacing, sticky, transform, transition, zIndex`. Avanzados de sección: `elements, html, loop, link, innerShadow, dividers, gutter`.

**Formato de los valores:**
- Color global: `$variable({"type":"color","value":{"name":"gcid-…","settings":{}}})$`. En CSS se emite `var(--gcid-…)`.
- Contenido dinámico: `$variable({"type":"content","value":{"name":"post_title","settings":{…}}})$` (`Conversion.php:668-680`; en Theme Builder la regex es `ET_THEME_BUILDER_DYNAMIC_CONTENT_REGEX_D5`, `theme-builder.php:36`).
- Variable de diseño: `var(--gvid-…)`, también dentro de `calc()` o `clamp()` (`GlobalData.php:1275-1335`).
- Ícono: `{unicode:"&#xe08b;", type:"divi|fa", weight:"400"}` (`ValueExpansion.php:31-50`).
- Espaciado: `{top,right,bottom,left,syncVertical:on|off,syncHorizontal}` (`ValueExpansion.php:194-210`).
- Fuente: `{family, weight, style:[italic|underline|strikethrough|overline], capitalization, lineColor, lineStyle, size, lineHeight, letterSpacing, color, textAlign, headingLevel}` (`AdvancedOptionConversion.php:2442-2530`).
- Fondo: `{color, gradient:{enabled,type,direction,stops[],repeat,length,overlaysImage}, image:{url,size,position,repeat,blend,parallax:{enabled,method}}, video:{mp4,webm,…}, mask:{…}, pattern:{…}}`.
- Condiciones: `module.decoration.conditions.desktop.value[] = {id, conditionName:'loggedInStatus', conditionSettings:{displayRule, enableCondition,…}, operator:'OR'}` (`Conditions/ConditionsRenderer.php:171-184`).

**Valores por defecto:** no se guardan. Están en `module-default-printed-style-attributes.json`; por ejemplo la fila trae `width:80%`, `maxWidth:1080px`, `flexDirection:row`.

**Metas de post:**
- D4: `_et_pb_use_builder=on`, `_et_builder_version`, `_et_pb_old_content`, `_et_pb_custom_css`.
- D5: `_et_pb_use_divi_5=on`, `_et_pb_divi_4_content` (respaldo D4), `_et_pb_divi_5_conversion_status`, `_et_pb_has_newly_convertible_modules` (`d5-readiness/server/Conversion.php:40-153`).
- Ajustes de página: `_et_pb_page_gutter_width_is_default`, `_et_pb_gutter_width`, `_et_pb_light_text_color`, `_et_pb_dark_text_color`, `_et_pb_post_settings_title/excerpt`.
- Off-canvas: `_divi_off_canvas_data`, `_divi_canvas_{id,append_to_main,z_index,parent_post_id,parent_context}`.

**Bloque de Gutenberg:** `divi/layout` lleva shortcodes D4 en `innerHTML` (`Conversion.php:1832-1860`).

### 3.2 Contenido Divi 4 (shortcodes)

- **Jerarquía:**
  - `[et_pb_section][et_pb_row column_structure="1_2,1_2"][et_pb_column type="1_2"][et_pb_text …]…[/et_pb_text]…`
  - Sección de ancho completo: `fullwidth="on"`, con los módulos `et_pb_fullwidth_*` directamente dentro, sin fila.
  - Sección especial: `specialty="on"`, con `et_pb_column specialty_columns` → `et_pb_row_inner` → `et_pb_column_inner`.
- **Sufijos de atributo** (`Conversion.php:740-797`):
  - `_tablet`, `_phone` + `{attr}_last_edited="on|phone"`.
  - `__hover` + `__hover_enabled="on|hover"`.
  - `__sticky` + `__sticky_enabled` y `sticky_position[_tablet|_phone]`.
  - `__focus`, `__checked`, `__active`.
- **Otros formatos D4:**
  - Colores: `global_colors_info="{%22gcid-x%22:%91%22background_color%22%93}"` (`Conversion.php:299-350`).
  - Contenido dinámico: `@ET-DC@<base64 JSON {dynamic,content,settings}>@` (`Conversion.php:54, 634-660`).
  - Espaciado: `custom_margin="t|r|b|l|true|false"`.
  - Fuente: cadena de 13 campos `family|weight|italic|upper|underline|smallcaps|strike|lineColor|lineStyle|overline|lower|…`.
  - Ícono: `"&#xe08b;||divi||400"`.
  - Preset: `_module_preset`. Instancia global: `global_module="123" saved_tabs="all"`; los hijos llevan `global_parent`.
- **Ejemplos de mapeo D4→D5** sacados de `conversion-outline.json`:
  - button: `button_text→button.innerContent.*.text`, `button_url→…linkUrl`.
  - blurb: `title→title.innerContent.*.text`, `image→imageIcon.innerContent.*.src`, `font_icon→imageIcon.innerContent.*.icon`.
  - slide: `heading→title.innerContent.*`, `button_link→button.innerContent.*.linkUrl`.
  - section: `fullwidth|specialty→module.advanced.type.*`, `gutter_width→module.advanced.gutter.*.width`, `inner_max_width→innerSizing.decoration.sizing.*.maxWidth`.
  - row: `column_structure→module.advanced.columnStructure.*`.

### 3.3 Cómo Divi convierte D4→D5 (pipeline real)

1. `Conversion::maybeConvertContent()` (`Conversion.php:1766`) desenvuelve el placeholder y hace `trim`.
2. Aplica `ShortcodeMigration::maybe_migrate_legacy_shortcode()` y luego `Migration::migrate_content_shortcode()`.
3. Según el formato del contenido:
   - **D4:** `parseShortcode()` (`:2000`) → `convertShortcodeToGbFormat()` (`:1515`) → `encodeAttrs()` (`:1370`) → `convertAttrs()` (`:2856`) → `getAttrMap()` (`:983`).
     - El mapeo usa el outline de cada módulo: `attributeMap`, `advanced`, `css`, `valueExpansionFunctionMap`, `optionEnableMap`, `deprecatedMap` y `conditionalConversionMap`.
     - La conversión se puede extender por filtros: `divi.conversion.moduleLibrary.conversionMap`, `divi.moduleLibrary.conversion.valueExpansionFunctionMap`, `divi_conversion_deprecated_attribute`.
   - **Bloque `divi/layout`:** se convierte su `innerHTML`.
   - **Bloques `divi/shortcode-module` dentro de contenido D5:** `convertShortcodeModulesInD5Content()` (`:1878`).
4. **Atributos desconocidos** (típicos de extensiones D4 de terceros) van a `unknownAttributes.*`. El módulo entero pasa entonces a `divi/shortcode-module` con el shortcode original (`:1606-1617`), y lo sigue pintando el framework D4.
5. **Instancias globales** se convierten en `<!-- wp:divi/global-layout {"globalModule":…,"blockName":…,"localAttrs":…} /-->` (`:1567-1597`).
6. **Plantillas globales:** el meta `_et_pb_excluded_global_options` se convierte en `localAttrsMap`/`localChildren` (`:1373-1460`).
7. **Conversión de sitio completo:** `d5-readiness/server/Conversion.php:36-153` guarda D4 en `_et_pb_divi_4_content`, marca `_et_pb_use_divi_5`, normaliza `_et_pb_custom_css` y borra `_divi_dynamic_assets_cached_*`.
   - Tipos de post que convierte (`d5-readiness/server/PostTypes.php:27-46`): `et_header_layout`, `et_body_layout`, `et_footer_layout`, `et_pb_layout`, `page`, `post`, `project` y los tipos habilitados en el builder.
   - **Consecuencia:** en un sitio con Divi 5 conviven contenido D4 y D5 mientras el sitio no haya pasado por la herramienta de conversión.

### 3.4 Presets

**Dónde se guardan.** `et_get_option(...,$is_product_setting=true)` produce `et_<shortname>_<nombre>` (`Divi/epanel/custom_functions.php:215-231`), y el shortname es `divi` (`Divi/functions.php:16`). Por eso las opciones quedan así:
- `et_divi_builder_global_presets_d5`: D5 (`GlobalPreset.php:154-156, 197`).
- `et_divi_builder_global_presets_ng`: D4 (`:596`).
- `et_divi_builder_is_legacy_presets_imported_to_d5`.
- Si el sitio usa el plugin Divi Builder en vez del tema, el prefijo cambia: conviene detectar con `LIKE 'et\_%builder_global_presets_%'`.

**Forma D5:**
```
{ "module": { "divi/text": { "default":"<id>", "items": { "<id>": {
      "id","name","moduleName","type":"module","version","created","updated","priority":10,
      "attrs":{…}, "styleAttrs":{…}, "renderAttrs":{…},
      "groupPresets":{ "<groupId>":{"presetId":[…],"groupName":"divi/font"} } } } } },
  "group":  { "divi/font": { "default":"<id>", "items": { "<id>": {
      "id","name","groupName","type":"group","attrs","styleAttrs","renderAttrs" } } } } }
```
- Usos de cada grupo en el metadata: `divi/font` (90), `divi/image` (27), `divi/font-body` (27), `divi/sizing` (20), `divi/layout` (16), `divi/text` (13), `divi/button` (10), `divi/animation` (10), `divi/border`, `divi/background`, `divi/visibility-settings`, `divi/spacing`, `divi/box-shadow`, `divi/filters`, `divi/font-header`, `divi/alignment`, `divi/attributes`, `divi/tooltip`, `divi/dropdown`.

**Forma D4:** `{ "et_pb_text": { "default":"<id>", "presets": { "<id>": {name, version, settings:{attrs D4}} } } }`. La conversión está en `Conversion.php:2134-2285` e incluye los alias `et_pb_section_fullwidth→divi/fullwidth-section`, `et_pb_section_specialty→divi/specialty-section` y `et_pb_slide_fullwidth→divi/slide`.

**Cómo se aplican y heredan:**
- `normalize_preset_stack()` (`GlobalPreset.php:3302`): un string, `default` o `_initial` se tratan como pila vacía, y la pila vacía usa el preset `default` del módulo.
- La pila se ordena por `priority` ascendente y se fusiona en ese orden: gana el de mayor prioridad (`:845-890`).
- Grupos (`:2489-2660`): primero los presets por defecto del módulo, luego los anidados del preset de módulo y por último los explícitos de `groupPreset`. Cuando coinciden anidados y explícitos se apilan: primero los anidados, después los explícitos.
- **Sobrescritura local:** los atributos del propio bloque, emitidos con la clase de orden `.et_pb_text_0`, ganan sobre el preset.
- El CSS del preset se emite una vez por clase: `preset--module--divi-text--<id>` y `preset--group--<módulo>--<grupo>[--host][--nested]--<id>` (`GlobalPresetItemUtils.php:44-95`).

### 3.5 Colores, fuentes y variables globales

- **Colores D5:** en la opción de una fila `et_divi` (`functions.php:17`), clave `et_global_data` = `{global_colors:{"gcid-…":{color, folder, label, lastUpdated, status: active|inactive|temporary, usedInPosts[]}}}` (`GlobalData.php:138-170, 349-351`).
- **Colores D4:** `et_divi['et_global_colors'] = {"gcid-…":{color, active:yes|no}}`; se migran a D5 en `GlobalData.php:542-561`.
- **Colores fijos del Customizer** (`GlobalData.php:59-85`): `gcid-primary-color→accent_color`, `gcid-secondary-color→secondary_accent_color`, `gcid-heading-color→header_color`, `gcid-body-color→font_color`, `gcid-link-color→link_color`.
- **Fuentes globales:** `--et_global_heading_font→heading_font` y `--et_global_body_font→body_font` (`:93-104`).
- **Colores relativos HSL:** `transform_state_into_global_color_value()` (`:181-220`) arma `calc(h ± n)` sobre otra variable.
- **Variables:** opción `et_divi_global_variables` con tipos `numbers|strings|images|links|colors|fonts|gradients`. Cada entrada es `"gvid-…":{label,value,order,status:active|archived}` (`GlobalData.php:821-1034`).
- **Salida CSS:**
  - Los colores van a un `<style class="et-vb-global-data et-vb-global-colors">` con `--gcid-x: …` (`FrontEnd/FrontEnd.php:751-760`; `FrontEnd/Module/Style.php:1383`).
  - Las variables numéricas y de fuentes se emiten solo si se detectan en el contenido (`FrontEnd.php:800-840`).

### 3.6 Elementos globales (Biblioteca)

- **CPT `et_pb_layout`.** Taxonomías: `layout_type` (layout, section, row, module, fullwidth_section, specialty_section), `scope` (global / non_global), `module_width` (regular / fullwidth), `layout_category`, `layout_tag`, `layout_pack`.
- **Metas:** `_et_pb_built_for_post_type`, `_et_pb_predefined_layout`, `_et_pb_module_type`, `_et_pb_row_layout`, `_et_pb_excluded_global_options` (JSON con los atributos D4 que no se sincronizan) (`includes/builder/feature/Library.php:1509-1521, 1740-1790`).
- **Detección de plantilla global:** `GlobalLayout::is_global_layout_template()` (`GlobalLayout.php:37-60`). La detección de ciclos está en `:77-200`.
- **Render D5** (`FrontEnd/BlockParser/BlockParserStore.php:584-660, 1710-1840`): la plantilla se expande dentro de la instancia. `localAttrs` y `localAttrOverridePaths` se superponen a la plantilla. Con `localChildren:true` la instancia conserva sus hijos. `divi/global-slot` injerta el contenido de la instancia en huecos de la plantilla y no pinta nada si queda vacío (`GlobalSlot/GlobalSlotModule.php:41-60`).

### 3.7 Theme Builder

- **CPTs** (`theme-builder.php:7-23`):
  - `et_theme_builder`: la versión publicada es `publish` y el borrador `auto-draft`; lleva el meta múltiple `_et_template`.
  - `et_template`.
  - `et_header_layout`, `et_body_layout`, `et_footer_layout`: su `post_content` puede estar en D4 o en D5.
- **Plantilla** (`et_theme_builder_get_template()`, `:471-530`):
  - Metas: `_et_default`, `_et_enabled`, `_et_{header|body|footer}_layout_id`, `_et_{…}_layout_enabled`, `_et_{…}_layout_global`, `_et_use_on[]`, `_et_exclude_from[]`, `_et_autogenerated_title`, `_et_library_item_id`, `_et_theme_builder_marked_as_unused`.
  - `override` vale `id≠0 || !enabled`. Una zona desactivada oculta la cabecera o el pie del tema.
- **Condiciones** (separador `:`, `:697-1070`): `homepage`, `search`, `404`, `singular:post_type:<pt>:all`, `singular:post_type:<pt>:id:<id>`, `singular:post_type:<pt>:children:id:<id>`, `singular:taxonomy:<tax>:term:id:<id>`, `archive:all`, `archive:post_type:<pt>`, `archive:taxonomy:<tax>:all`, `archive:taxonomy:<tax>:term:id:<id>`, `archive:user:all`, `archive:user:id:<id>`, `archive:user:role:<rol>`, `archive:date:all`.
- **Resolución:** por prioridad numérica (30–110) y por relación padre/hijo, en `ThemeBuilderRequest.php:206-440`. El render D5 está en `builder-5/server/ThemeBuilder/Layout.php:79`.

### 3.8 Exportación e importación (JSON)

- **Contextos registrados:**
  - `et_builder` (post/plantilla) y `et_builder_layouts` (CPT de biblioteca): `includes/builder/core.php:4087-4110`.
  - `et_divi_mods` (Customizer, opción `et_divi`): `functions.php:9182`.
  - `epanel`: `epanel/core_functions.php:1182-1207`.
  - `et_pb_roles`, snippets de código.
  - `et_theme_builder`.
- **Biblioteca (tipo `post_type`)** (`Portability.php:838-848`):
```
{ "context":"et_builder_layouts",
  "data": { "<ID>": {ID, post_title, post_name, post_content, post_type, post_status, …,
                     "post_meta":{"_et_pb_…":["…"]}, "terms":{"<term_id>":{name,slug,taxonomy,parent,description}}}},
  "presets": <forma D4 o D5>, "global_colors": [["gcid-…",{color,status|active,label}], …],
  "global_variables": {…}, "canvases": {"local":…, "global":…},
  "images": { "<url>": {"encoded":"<BASE64>","url":"…","id":123} }, "thumbnails": {…} }
```
- **Un post suelto:** `data = {"<ID>": "<post_content>"}`.
- **Imágenes:** van en **base64**, leídas del archivo adjunto o descargadas (`Portability.php:4244-4320`).
- **Exportación D5 por layout:** añade `post_title`, `post_type`, `theme_builder`, `components` y `canvases` (`:1666-1675`).
- **Theme Builder** (`:1714-1725`): `{context:"et_theme_builder", templates:[<estructura get_template>], layouts:{<id>:{…,post_meta}}, presets, global_colors, global_variables, has_default_template, has_global_layouts}`.
- **Importación de presets:** acepta las dos formas, D5 o D4 (`Portability.php:273-293`).
- **Importación de colores** (`:3248-3290`): excluye los colores del Customizer.

### 3.9 Inventario de módulos

Hay 115 módulos (`_all_modules_metadata.php`): 8 estructurales, 10 de ancho completo, 84 normales (25 de WooCommerce) y 13 hijos.

- **Estructurales:**
  - `section` (`et_pb_section`, grupos `module column1-3 innerSizing`), `row`, `row-inner`, `column`, `column-inner`.
  - Solo D5: `group`, `global-layout`, `global-slot`.
- **De ancho completo:** `fullwidth-code`, `-header` (module image logo title subhead content buttonOne buttonTwo scrollDown overlay), `-image`, `-map`, `-menu`, `-portfolio`, `-post-content`, `-post-slider`, `-post-title`, `-slider`.
- **Módulos y sus grupos de atributos principales:**
  - accordion (+accordion-item): title content openToggle closedToggle.
  - audio: audio title artistName albumName image.
  - before-after-image (D5): beforeImage afterImage slider labels.
  - blog: post image readMore pagination meta overlay blogGrid masonry.
  - blurb: imageIcon title content contentContainer.
  - breadcrumbs (D5).
  - button: button.
  - canvas-portal: canvas.
  - charts: chart.
  - circle-counter: circle number title.
  - code.
  - comments.
  - contact-form (+contact-field con `conditionalLogic`): captcha email redirect.
  - contact-form-7.
  - countdown-timer.
  - counters (+counter).
  - cta.
  - divider.
  - dropdown (D5).
  - filterable-portfolio.
  - gallery.
  - gravity-forms (D5).
  - group-carousel (D5): arrows dotNav activeGroups.
  - heading.
  - icon.
  - icon-list (+item).
  - image.
  - imagely-gallery.
  - instagram-feed.
  - link.
  - login.
  - lottie.
  - map (+map-pin).
  - menu.
  - number-counter.
  - payment-button.
  - portfolio.
  - post-content.
  - post-filter (+item).
  - post-nav.
  - post-slider.
  - post-title.
  - pricing-tables (+pricing-table).
  - search.
  - sidebar.
  - signup (+signup-custom-field).
  - slider (+slide: title content button image video contentOverlay).
  - social-media-follow (+network).
  - svg.
  - table-of-contents.
  - tabs (+tab).
  - team-member.
  - testimonial.
  - text (con `content.decoration.headingFont.h1-h6` y `bodyFont.{body,link,ul,ol,quote}`).
  - timeline (+item).
  - toggle.
  - tooltip.
  - video.
  - video-slider (+item).
- **WooCommerce (`divi/woocommerce-*`):** breadcrumb, cart-notice, cart-products, cart-totals, checkout-billing, checkout-shipping, checkout-additional-info, checkout-order-details, checkout-payment-info, cross-sells, product-add-to-cart, -additional-info, -description, -gallery, -images, -meta, -price, -rating, -reviews, -stock, -tabs, -title, -upsell, related-products y `divi/shop` (`et_pb_shop`). Las plantillas PHP propias están en `Packages/WooCommerce/Templates/{cart,checkout,global}`.

### 3.10 CSS y responsive

- **Breakpoints:** opción `et_divi_builder_breakpoints` (`Breakpoint.php:29, 220`). Valores por defecto (`:72-145`):

| Breakpoint | Límite | Activo por defecto |
|---|---|---|
| phone | ≤767px | sí |
| phoneWide | ≤860px | no |
| tablet | ≤980px | sí |
| tabletWide | ≤1024px | no |
| desktop | base | sí |
| widescreen | ≥1280px | no |
| ultraWide | ≥1440px | no |

  - Además `desktopAbove` y `tabletOnly`, que solo usa `disabledOn`.
  - Las media queries se generan en `:500-730`.
  - Media queries heredadas (`Style.php:582-594`): 1405/1100/981/980/768/767/479.
- **Cómo se genera el CSS:**
  - `Style::add()` acumula declaraciones con plantillas `{{selector}}` del `module.json` (`styleProps`) y las ordena por media query (`Style.php:1068-1120`).
  - Selectores: clase de orden por módulo (`.et_pb_text_0`) más las clases de preset.
  - Salida: archivo estático `et-cache/<post>/et-core-unified[-cpt]-<id>.min.css` o inline, con CSS crítico más `-deferred` (`FrontEnd/Assets/StaticCSS.php:337-412`). Dynamic Assets carga solo el CSS de los módulos que se usan.
- **CSS base del tema:** sección con 4 % de relleno vertical, fila con 80 % de ancho y máximo 1080 px, y medianil de 5,5 % a la derecha de cada columna en el modo de bloques heredado (`Divi/style-static.min.css`). El modo flex de D5 usa `gap`.

---

## 4. Licencia y qué se puede reutilizar

- **Licencia general:** `Divi/LICENSE.md` dice GPLv2 «or (at your option) any later version»; la cabecera de `Divi/style.css` dice sólo «GNU General Public License v2». Se toma `LICENSE.md` como vigente (compatible con la GPLv3 de Elements/Elementor) y la discrepancia queda registrada en `docs/third-party-licenses.md`. © Elegant Themes. «Divi» es marca: no conviene usarla en nombres de la interfaz, solo como «Importar desde Divi».
- **Componentes de terceros** (`Divi/CREDITS.md`):
  - MIT: Smooth Scroll, Underscore, React Tooltip, CodeMirror, Chart.js, jQuery UI, Magnific Popup, Salvattore, Waypoints, Lodash…
  - TinyMCE (Ephox) y adaptaciones de WordPress/Calypso (GPL).
  - `rogervila/array-diff-multidimensional` (MIT, `server/vendor`).
  - Font Awesome (`core/admin/fonts/fontawesome/*`): no aparece en CREDITS.md; su licencia hay que comprobarla.
  - Fuente ETmodules (`core/admin/fonts/modules/*`): sin licencia propia declarada, solo el paraguas GPL de ET. **No conviene redistribuir los glifos.**
- **Se puede reutilizar como dato**, con atribución en `docs/third-party-licenses.md`:
  - Las tablas `conversion-outline.json` y `module-default-*.json`.
  - `_all_modules_metadata.php` (nombres, `d4Shortcode`, árbol de hijos).
  - `iconList.json` (nombres y unicode para mapear íconos).
  - Las condiciones del Theme Builder.
- **No reutilizar:**
  - Los bundles JS compilados (`visual-builder/build/*`): la fuente no viene en el ZIP y redistribuirlos sin fuente choca con la GPL.
  - Las imágenes, los layouts de Divi Cloud, la IA ni el onboarding.
  - Los SVG de divisores, máscaras y patrones solo con registro explícito de procedencia, como se hizo con Element Pack.

---

## 5. Riesgos

1. **Breakpoints distintos.** La tableta de Divi llega a 980px y la de Elementor a 1024px; móvil coincide en 767. Entre 981 y 1024px las páginas convertidas cambian de layout. widescreen/ultraWide son breakpoints `min-width` y Elementor solo tiene un widescreen (2400). Cambiar el kit afecta a las páginas Elementor que ya existan en el mismo sitio.
2. **El CPT `project` lo registra el builder del tema** (`includes/builder/core.php:2824-2873`, con `project_category` y `project_tag`). Al cambiar de tema, esos posts y sus URL desaparecen.
3. **Las barras laterales y los menús viven en el tema:** `sidebar-1..5` (`includes/functions/sidebars.php`), áreas propias en `get_theme_mod('et_pb_widgets')` (`core.php:6939-6943`) y ubicaciones `primary-menu`, `secondary-menu`, `footer-menu` (`functions.php:80-83`). Los módulos `sidebar` y `menu` quedan vacíos si nadie los vuelve a registrar.
4. **El CSS personalizado del usuario apunta a clases de Divi:** `_et_pb_custom_css`, `et_divi['divi_custom_css']`, el `freeForm` de cada módulo y `wp_custom_css` usan `.et_pb_*` y clases de orden `_N`.
5. **Conviven contenido D4 y D5,** además de `divi/shortcode-module` con shortcodes de extensiones D4 de terceros que nadie sabría pintar sin Divi.
6. **Los presets no se guardan en el contenido.** Si no se aplanan en la instancia, se pierde el estilo. Divi tampoco guarda los valores por defecto: hay que fusionar `module-default-printed-style-attributes`.
7. **Colores HSL relativos, gradientes de más de 2 paradas y variables dentro de `calc()`/`clamp()`** no tienen equivalente en los widgets v3.
8. **Sincronía selectiva de los globales** (`localAttrs`, slots): Global Widget y Template de Elementor no admiten sobrescrituras por instancia, y Components v4 rechaza widgets no atómicos.
9. **Íconos ETmodules:** 380 glifos sin equivalente 1:1 en Font Awesome.
10. **Divi es un tema, no un plugin.** `Retire::PLUGINS` (`digitalisimo-migration/legacy/class-retire.php:18-24`) no lo cubre. Si se cambia el tema antes de convertir, el contenido D4 muestra shortcodes crudos y el D5 queda sin renderizador.
11. **Los formularios guardan cuentas de servicios** (Mailchimp, spam y pagos) en opciones de Divi, y las credenciales no se pueden convertir solas.
12. **El importador de plantillas del núcleo** (`elementor/includes/template-library/sources/base.php:464-475`) exige `$data['content']` y rechaza el JSON de Divi. Además no tiene filtro previo al `json_decode`, y el núcleo no se edita.

---

## 6. Recomendaciones de integración

### 6.1 Un solo conversor, en Elements, que también usa Migración

Así ni el importador ni la migración duplican motor. Propuesta: `digitalisimo-elements/modules/digitalisimo-import/sources/divi/`. La ubicación es nueva; el resto del módulo lo comparten los otros frentes.

- **`class-divi-reader.php`** normaliza todo a un árbol intermedio con atributos D5:
  - D5: `parse_blocks()` de WordPress.
  - D4: un parser propio de shortcodes `et_pb_*` que aplica las tablas D4→D5 generadas desde `conversion-outline.json` a `maps/divi/<slug>.php`. Se generan con un script en `scripts/`, igual que `scripts/legacy-styles.py`. Hay que reimplementar los sufijos y los formatos de §3.2.
  - Con esto solo hace falta un mapeador, el de D5→Elementor.
- **`class-divi-presets.php`** reimplementa en propio la pila, la prioridad, los presets por defecto y los de grupo (§3.4), y **aplana** el resultado en cada instancia antes de mapear. Opcional: copiar el preset `default` de cada módulo a `_elementor_elements_default_values` para que solo afecte a lo que se edite después.
- **`class-divi-mapper.php`** sigue el formato de los mapas de Element Pack (`target`, `rename`, `values`, `defaults`, `repeaters`, como en `digitalisimo-migration/legacy/maps/bdt-accordion.php`). La tabla común de decoración queda así:

| Divi (decoración) | Elementor |
|---|---|
| spacing | `_padding` / `_margin` (en contenedores `padding` / `margin`) |
| sizing | `_element_width`, `width`, `boxed_width`, `min_height` |
| border, boxShadow | `_border_*`, `_box_shadow_*` |
| background | `background_*` y `__globals__` |
| transform | `_transform_*` (incluido hover) |
| position, zIndex | `_position`, `_offset_*`, `_z_index` |
| disabledOn | `hide_desktop`, `hide_tablet`, `hide_mobile` |
| htmlAttributes | `_element_id`, `_css_classes` |
| css | `custom_css` (Pro, mismo token `selector`) |
| link | extensión Wrapper Link de Elements |
| animation | `_animation` |
| scroll | `motion_fx_*` |
| sticky | `sticky*` |

- **Rutas de entrada:**
  - **Importar JSON** (`context` `et_builder`, `et_builder_layouts` o `et_theme_builder`): una acción propia en `digitalisimo-elements/modules/library/` (derivado de Pro, editable) que convierte y llama a `save_item()` de la fuente local. Las imágenes base64 se suben a Medios.
  - **Migración:** el mismo conversor, invocado desde el runner.

### 6.2 Migración: etapas nuevas en `digitalisimo-migration/legacy/class-runner.php`

`STAGES`, línea 45.

- **Detección:** `get_template()==='Divi'`, la opción `et_divi`, y posts con `_et_pb_use_builder=on`, `<!-- wp:divi/` o `[et_pb_`. `Migration::documents()` hoy solo consulta `_elementor_data` (`class-migration.php:305-318`); hace falta una consulta paralela sobre `post_content`.
- **`convert`:** escribe `_elementor_data`, `_elementor_edit_mode=builder` y `_elementor_template_type`. El respaldo va a un meta propio (`post_content` más las metas `_et_*`) para que `--revert` siga funcionando como con Element Pack.
- **`references`:**
  - Biblioteca: `et_pb_layout` → `elementor_library`. Un módulo global pasa a Global Widget (`global`). Una sección o fila global pasa a una plantilla `container` más el widget `template`. Si la instancia tiene `localAttrs`, se desglobaliza la copia y se informa.
  - Theme Builder: `et_template` → `elementor_library` header/footer/single/archive/error-404/search-results con `_elementor_conditions`. Mapeo de condiciones:

| Divi | Elementor |
|---|---|
| plantilla por defecto | `include/general` |
| `homepage` | `include/singular/front_page` |
| `singular:post_type:page:all` | `include/singular/page` |
| `…:id:42` | `include/singular/page/42` |
| `…:children:id:42` | `include/singular/child_of/42` |
| `singular:taxonomy:category:term:id:5` | `include/singular/in_category/5` |
| `archive:all` | `include/archive` |
| `archive:taxonomy:category:term:id:5` | `include/archive/category/5` |
| `archive:post_type:product` | `include/archive/product_archive` |
| `archive:user:all` / `id:3` | `include/archive/author` / `author/3` |
| `archive:date:all` | `include/archive/date` |
| `search` | `include/archive/search` |
| `404` | `include/singular/not_found404` |
| `archive:user:role:*` | sin equivalente (se informa) |

  - Los IDs de `_et_use_on` y `_et_exclude_from` pasan a `include/...` y `exclude/...`.
  - Off-canvas: `et_pb_canvas` → widget `off-canvas` o un popup.
- **Kit:**
  - Colores del Customizer → `system_colors` (primary, secondary, text, accent).
  - Los `gcid-*` → `custom_colors`, referenciados con `__globals__`.
  - `heading_font` y `body_font` → `system_typography`.
  - `content_width` (1080) → `container_width`.
  - Los `gvid` numéricos se resuelven a literal; opcionalmente también se crean como variables v4.
  - Breakpoints: solo en sitios donde todo era Divi, fijar `viewport_tablet=980`.
- **Código y CSS:**
  - `divi_custom_css` → CSS del kit.
  - `divi_integration_head` / `_body` / `_single_*` → Custom Code (`modules/custom-code`).
  - `_et_pb_custom_css` → `custom_css` de la página.
  - Añadir `et_pb_<módulo> et_pb_<módulo>_<n>` a `_css_classes`, para que el CSS heredado siga funcionando.
- **`finish`:** cambia al tema DIGITALÍSIMO **solo** cuando no quede contenido Divi pendiente. Extender Retire con temas (Divi, Extra) y con el plugin `divi-builder`. El tema se desactiva, nunca se borra.

### 6.3 Lo que debe vivir en Elements (render diario), no solo en Migración

- **Compatibilidad del tipo `project`:** registrar `project`, `project_category` y `project_tag` con los mismos argumentos y slugs, siempre que existan posts de ese tipo. Va en `digitalisimo-compat.php`. Si fuera en el tema, se perdería al cambiar de tema.
- **Ampliar `digitalisimo-conditions`** con: cookie, número de vistas, visitas de página, campo personalizado, autor, categorías y etiquetas, stock de producto y resultados de búsqueda.
- **Huecos funcionales:**

| Hueco | Dónde encajaría |
|---|---|
| tooltip, dropdown, contador circular | widgets en `digitalisimo-widgets` |
| máscaras y patrones de fondo, gradientes de N paradas | runtime en Elements (hoy `class-masks.php` y `class-shapes.php` están solo en Migración) |
| estilos en estado sticky | sobre `.elementor-sticky--active` |
| interacciones de clase, atributo, cookie o preset | `modules/interactions` derivado de Pro |

### 6.4 Tema DIGITALÍSIMO (frente compartido)

- Registrar `sidebar-1..5` y las áreas de `et_pb_widgets`.
- Ubicaciones de menú `primary-menu`, `secondary-menu` y `footer-menu`.
- Cabecera y pie mínimos de respaldo.
- La cabecera y el pie nativos de Divi (cuando no hay plantilla del Theme Builder) se generan como plantilla de cabecera de Elementor: logo `divi_logo`, `nav-menu`, búsqueda y carrito. Es una conversión **parcial**.

### 6.5 Mapeo Divi → Elements

**Estructura:**

| Divi | Elements | Conversión |
|---|---|---|
| `section` | `container` exterior a ancho completo (`content_width=full`); el padding 4% se fija explícitamente | Automática |
| `row` / `row-inner` | `container` en caja (`boxed_width` 1080px / 80%); `columnStructure` → anchos en %; gutter → `flex_gap`; layout grid → `container_type=grid` | Automática |
| `column` / `-inner`, `group` | contenedor hijo | Automática |
| sección especial | contenedores anidados | Automática |
| `global-layout` | `global` / `template` | Semiautomática |
| `global-slot` | sin equivalente: se aplana | — |

**Módulos con conversión automática:**

| Divi | Elements |
|---|---|
| text | `text-editor` |
| heading | `heading` |
| image, fullwidth-image | `image` |
| button, link | `button` / `digitalisimo-advanced-button` |
| blurb | `icon-box` / `image-box` / `digitalisimo-advanced-icon-box` |
| cta | `call-to-action` / `digitalisimo-call-out` |
| divider | `divider` / `digitalisimo-advanced-divider` |
| code, fullwidth-code | `html` |
| contact-form-7, gravity-forms, imagely-gallery | `shortcode` |
| video | `video` |
| audio | `digitalisimo-audio-player` |
| icon | `icon` |
| icon-list | `icon-list` |
| social-media-follow | `social-icons` |
| testimonial | `testimonial` |
| number-counter | `counter` |
| counters | `progress` / `digitalisimo-progress-bars` |
| pricing-tables | `price-table` dentro de un contenedor |
| tabs | `nested-tabs` |
| accordion, toggle | `nested-accordion` / `digitalisimo-accordion` |
| slider, fullwidth-slider | `slides` (el Slider Optimizado `digitalisimo-slider-optimizado` es para marquesina de logos, no para este caso) |
| gallery | `image-gallery` / `gallery` / `image-carousel` / `digitalisimo-custom-gallery` |
| before-after-image | `digitalisimo-image-compare` |
| countdown-timer | `countdown` |
| search | `search-form` |
| login | `login` |
| post-title | `theme-post-title` (+`post-info`) |
| post-content | `theme-post-content` |
| post-nav | `post-navigation` |
| comments | `post-comments` |
| breadcrumbs | `breadcrumbs` / `digitalisimo-breadcrumbs` |
| table-of-contents | `table-of-contents` |
| lottie | `lottie` |
| svg | `image` / `e-svg` |
| timeline | `digitalisimo-timeline` |
| group-carousel | `nested-carousel` |
| sidebar | `sidebar` |

- **WooCommerce:** `woocommerce-breadcrumb`, `-notices`, `-product-{title,price,rating,images,meta,stock,add-to-cart,additional-information,data-tabs,upsell,related,content,short-description}` y `shop` → `woocommerce-products` / `digitalisimo-product-grid`.

**Conversión parcial:**
- blog, portfolio, filterable-portfolio, post-slider → `posts`, `portfolio`, `loop-grid` / `loop-carousel` (requieren plantilla de ítem).
- post-filter → `taxonomy-filter`.
- menu, fullwidth-menu → `nav-menu`, `theme-site-logo`, `woocommerce-menu-cart`.
- map, fullwidth-map → `digitalisimo-map` / `google_maps`.
- contact-form, signup → `form` (se pierde la lógica condicional y las credenciales se configuran a mano).
- charts → `digitalisimo-chart`.
- team-member → composición de widgets.
- video-slider → `video-playlist`.
- payment-button → `paypal-button` / `stripe-button`.
- canvas-portal → `off-canvas`.
- fullwidth-header → contenedor con heading, texto, botones e imagen.
- Carrito y checkout divididos en 5 módulos → `woocommerce-cart` y `woocommerce-checkout-page`; cross-sells no tiene equivalente directo.
- Estados hover sin control en Elementor, sticky por estado, íconos ETmodules (requieren SVG o equivalente en FA), loop en módulos arbitrarios e interacciones.

**Presets, colores y globales:**
- Presets → aplanados en cada instancia, y el preset por defecto opcionalmente como default del kit. Las clases globales v4 solo sirven si se eligen widgets atómicos.
- Colores, fuentes y variables → kit y variables v4.
- Globales → Global Widget o Template.

**Sin equivalente:** tooltip, dropdown, instagram-feed (y su servicio de tokens), global-slot, A/B testing, ultraWide, la sincronía selectiva por instancia, los colores HSL relativos enlazados, los estilos de cabecera nativa de Divi (fija, centrada, slide-in, pantalla completa) y los ítems de Divi Cloud y la IA.

---

## Decisiones posteriores (plan aprobado)

Estas decisiones de `docs/FUSION_OBJECTIVE.md` sustituyen recomendaciones de este informe:

- **Destino clásico (v3).** Contenedores flex o grid y widgets nativos o propios. v4 sólo en sitios que ya lo tienen activo; nunca se activa sin permiso.
- **Presets.** Los presets de estilo se guardan en la meta del kit y compilan su CSS en el del kit, con una clase `digi-preset-{slug}` por instancia (F7a). Lo que no es estilo se aplana en la instancia.
- **Elementos globales.** Una sección o fila global va a una plantilla de biblioteca con el widget Template y sobrescrituras de contenido por instancia (F7b). Global Widget sólo para módulos globales de un solo widget. Los slots pierden el vínculo y se declara.
- **Tema (§6.2 `finish` y §6.4).** «Migrar» deja documentos y plantillas del Theme Builder en borrador; en producción el administrador activa el tema tras la vista previa y la tarea termina sola. El tema propio no lleva hijo ni registra `sidebar-1..5`: sólo `menu-1`/`menu-2`. Los menús de Divi van a esas ubicaciones o a un Nav Menu por ID; las sidebars, a un widget Sidebar dentro de plantillas del Theme Builder, registradas por la opción `digitalisimo_compat` de Elements.
- **CPT `project`.** Lo registra Elements sólo con `digitalisimo_compat` activa (cero consultas sin ella).
- **Condiciones, degradados, máscaras y patrones** se construyen en F7a, antes de F3, en clean-room y sin recursos de Divi.
- **Interacciones con destino** (clase, atributo, cookie): registro propio `_digitalisimo_actions` en F7b; nunca JS arbitrario.
- **Formularios:** campos, validación y acciones a `modules/forms`; las credenciales nunca se copian.
- **Conversor:** vive en `digitalisimo-elements/modules/digitalisimo-import/sources/divi/`; Migración sólo orquesta (driver de Migración v2).
