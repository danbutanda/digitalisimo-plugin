# Informe: capacidades de Divi y Betheme en DIGITALÍSIMO Elements (etapa F7a)

> Diseño de la etapa F7a, del 21 de octubre de 2026, depurado para el repositorio público. Se escribió leyendo el código de `main@e4506f1` (Elements 4.3.0.163 con Elementor 4.3.5 en el núcleo incluido) y se ajustó con lo que encontraron los frentes al implementarlo. Las líneas citadas pueden moverse con cada `sync-elementor-core.sh`: [ELEMENTOR_CORE.md](../ELEMENTOR_CORE.md) dice qué símbolos y ganchos volver a comprobar. El estado, las decisiones y la aceptación están en [FUSION_OBJECTIVE.md](../FUSION_OBJECTIVE.md), sección «F7a»: si algo no coincide, manda el objetivo. La evidencia de Playground (`design.sh` DS1–DS13 y las ampliaciones de `front-budget.sh`, `conditions.sh`, `kit-roundtrip.sh` y `brand-audit.sh`) irá a `f7a-evidence.json`; está pendiente del frente M.

**Abreviaturas de las citas.** `E:` es `digitalisimo-elements/`. `N:` es `digitalisimo-elements/elementor/`, el núcleo incluido, que nunca se edita. `D:` es `E:modules/digitalisimo-design/`, el módulo propio de F7a.

## 1. Qué resuelve

Divi y Betheme ofrecían capacidades que el constructor no tenía. Desde el cambio de alcance del 2026-10-20 no se importa nada de ellos: F7a integra esas capacidades en Elements, sin motores paralelos y sin otro sistema de kits.

- **Presets de estilo** con nombre para widgets v3 y contenedores.
- **Degradados** de N paradas, cónicos y repetidos.
- **Máscaras de recorte** y **patrones** con un catálogo propio de formas y teselas.
- **Siete condiciones de visualización** nuevas, clasificadas para la caché.
- **Prioridad manual** de las plantillas del Theme Builder.
- **Sticky «al fijarse»:** un descendiente de un contenedor fijo se muestra u oculta según su estado.
- **Controles de documento** para ocultar la cabecera, el pie o la imagen destacada.

Todo es opt-in por instancia o por documento, con el valor por defecto igual al de 4.3.0.163, y cuesta 0 B en las páginas que no lo usan. `plugin.php`, los paquetes compilados de PRO y `elementor/` no cambian. Hay una línea en un derivado de PRO (§9) y una en K (§5.6).

## 2. Origen: sólo comportamientos (clean-room)

Ninguna capacidad copia código, CSS, JS, SVG, imágenes, fuentes, nombres de campos, claves ni nombres de cookies de los orígenes. De Divi 5.15.0 (GPLv2 o posterior) se leyeron, sin extraer, instalar ni ejecutar nada, la lista de condiciones por elemento y la lista de capacidades de máscaras y patrones. De Betheme se tomaron dos comportamientos ya descritos en [betheme.md](betheme.md), sin abrir su ZIP. Bricks no aporta nada a F7a (es de F7b). Lo que se tomó, con palabras propias:

- **Cookie:** existe, no existe, su valor es igual o distinto a uno dado.
- **Visita de página:** el visitante abrió, o no, alguna de las páginas elegidas, recordado en una cookie del navegador.
- **Campo personalizado:** compara una meta del contenido consultado.
- **Autor:** el autor del contenido consultado es, o no, alguien concreto.
- **Existencias de un producto:** un producto concreto está, o no, en existencias.
- **Búsqueda:** la página es una búsqueda, con una frase dada o sin resultados.
- **Máscara de fondo:** una forma vectorial que recorta el elemento, con tamaño, posición y repetición.
- **Patrón de fondo:** una tesela vectorial, coloreada y repetida, con tamaño, repetición, opacidad y modo de fusión.
- **Degradado:** lineal, radial o cónico, con N paradas y repetición.
- **Cabecera al fijarse** (Betheme): una variante que sólo se ve mientras la cabecera está fijada.
- **Ocultar partes de la página** (Betheme): el título, la cabecera, el pie o la imagen destacada.

El «número de vistas» ya existía en el motor propio (`visit_count`) y no se repite. El catálogo de formas y teselas es geometría propia (§6.2); la atribución está en [third-party-licenses.md](../third-party-licenses.md).

## 3. Hechos del núcleo que sostienen el diseño

Cada uno se comprobó en el código. La última columna dice qué hace F7a con él.

| Hecho | Evidencia | Consecuencia en F7a |
|---|---|---|
| `elementor/element/before_parse_css` corre para cada elemento **antes** de `render_element_styles()` | `N:core/files/css/post.php:304,307` | Los presets se aplanan ahí, en memoria (§5.3) |
| En una pila optimizada (front y WP-CLI), `Controls_Stack::add_control()` quita `options` y `size_units` de cada control | `N:includes/base/controls-stack.php:451-489` | Fuera de la administración, una opción se valida por su forma (§13, A1) |
| Fuera del editor y de REST sólo se registran los controles de contenido, salvo con `Performance::set_use_style_controls( true )` | `N:core/frontend/performance.php`; ya lo usa K (`k-kits.md`, §14) | Todo saneado y aplanado va dentro de `Css::with_style_controls()`, que restaura el valor anterior |
| El núcleo repite los ganchos `elementor/element/common/…` para la pila `common-optimized` | `N:includes/base/controls-stack.php:1588,1633,1686` (`should_manually_trigger_common_action()`) | Los widgets sólo se enganchan en `common`; las pruebas comprueban una instancia de cada control, con el marcado optimizado y sin él |
| `Stylesheet::add_rules()` parte la cadena por `;` y abandona la regla si un trozo no tiene `:`; el editor hace lo mismo en `this.addRules` | `N:includes/stylesheet.php:166-178`; `N:assets/js/editor.js` | Los SVG van codificados en porcentaje, sin `;` ni base64, y las reglas se pasan como array (§6) |
| `gradient_angle` sólo aparece con `linear` y `gradient_position` sólo con `radial` | `N:includes/controls/groups/background.php:236-289` | El cónico lleva sus controles `digi_conic_from` y `digi_conic_at` |
| El editor pinta con `.elementor-{doc} .elementor-element.elementor-element-{id}`, y `editor/style/styleText` concatena detrás de la hoja del elemento | `N:assets/js/editor.js` (`renderStyles`, `addStyleToDocument`) | La vista previa de presets y degradados usa ese filtro, ese selector (0,3,0) y los `@media` de los puntos de corte activos |
| Con `prefix_class` y `render_type => 'ui'` el editor repinta la clase en vivo; con `'none'` no | `N:assets/js/editor.js:9868-9890,9925-9948` | `digitalisimo_sticky_state` y `digitalisimo_preset` llevan `'ui'` |
| `elementor/document/load/data` corre también en el front, no sólo en el editor | `N:includes/frontend.php:1161` | El centinela se retira sólo en modo de edición y en `elementor_ajax` (§7.2) |
| `data-settings` se imprime con `wp_json_encode()` sin opciones, y `get_frontend_settings()` quita `''` y `null` | `N:includes/base/element-base.php:791,844`; `N:includes/base/controls-stack.php:1367-1377` | `"sticky":"top"` o `"sticky":"bottom"` sólo aparece en un elemento fijo: la hoja del sticky lo reconoce (§10) |
| Una condición de visualización desconocida se da por cumplida | `E:modules/display-conditions/classes/and-condition.php:34-39` | Volver a 4.3.0.163 haría visibles las condiciones nuevas; de ahí el centinela (§7.2) |
| `Special_Page_Condition::check()` de 4.3.0.163 sólo se cumple con las páginas que conoce | `E:modules/digitalisimo-conditions/class-conditions.php` de 4.3.0.163 (sha256 `1ba5f9da…2e42`, igual en el asset de `v2026.10.21.417`) | `pages: [ digitalisimo_f7a ]` da `false` en 4.3.0.163 |
| v4 no tiene lista de condiciones propia: el control atómico abre el modal sin `conditionsConfig` y el modal usa `elementor.config.displayConditions`, el mismo objeto que v3 | `E:assets/js/packages/editor-controls-extended/editor-controls-extended.js:158-209`; `E:assets/js/display-conditions.js:2273-2294`; `E:core/editor/editor.php:182-186` | No se puede filtrar en el servidor por tipo de elemento: se filtra en el editor (§7.1) |
| En el prop v4 `display-conditions`, cada condición es `{ $$type: string, value: "<JSON>" }` dentro de un `condition-group` | `E:assets/js/display-conditions.js:268-279`; `E:modules/atomic-widgets/prop-types/display-conditions/` | El centinela y la lectura de `Visitor_Content` decodifican esa cadena (§7.3) |
| `sticky` es `frontend_available` y PRO sólo lo añade a `common`, `section` y `container` | `E:modules/sticky/module.php:47-62,257-259` | El estado «al fijarse» se registra en esas tres pilas |
| El espaciador del sticky es un clon hecho **antes** de `--active`, y `--effects` se evalúa después de fijar y se quita al soltar | `E:assets/lib/sticky/jquery.sticky.js:105-114,225-233,288-318` | El clon nunca lleva `--active` ni `--effects`: conserva la altura del estado normal |
| `get_location_templates()` termina con `asort( $conditions_priority )` y el `return` | `E:modules/theme-builder/classes/conditions-manager.php:411` | Una línea con el filtro de la prioridad manual (§9) |
| Loop Grid y Loop Carousel marcan `$wp_query->is_loop_widget`, y `Loop::get_content()` cambia el documento actual a `loop-item`, también en los bucles de términos | `E:modules/loop-builder/skins/skin-loop-base.php:144-154`; `E:modules/loop-builder/documents/loop.php:270-297` | La imagen destacada nunca se oculta dentro de un bucle (§11) |
| `Theme_Compat::active()` reconoce el tema DIGITALÍSIMO por su carpeta o por su `Update URI` | `E:modules/digitalisimo-theme/class-module.php:93-107` | «Ocultar cabecera» y «Ocultar pie» sólo se registran con el tema activo |
| `Parts\Files` admite `masks/<sha8>.svg`, `collect_strings()` recoge esa ruta relativa en cualquier ajuste y la importación escribe el archivo saneado con el mismo nombre; `Remapper::find_media()` sólo traduce URL | `E:modules/digitalisimo-import/kits/parts/class-files.php:254`; `E:modules/digitalisimo-import/kits/class-remapper.php` | Las copias de los SVG del sitio viajan sin declarar nada; queda el hueco de nombres de §14 |
| `Svg::get_inline_svg()` sanea **antes** de la lista de rechazo y escribe una meta de caché | `N:core/files/file-types/svg.php:212` | No se reutiliza: un SVG hostil saldría limpio y aceptado. Se usa `Css::svg_clean()` (§6.3) |
| `Kit_Settings::export()` calcula los globales usados recorriendo sólo documentos, ajustes de página y valores por defecto | `E:modules/digitalisimo-import/kits/parts/class-kit-settings.php:50-71` | Un color que sólo usa un preset no viajaba: filtro nuevo (§5.6) |
| `Remapper::map_value()` anota `map.miss` de todo valor sin entrada en el mapa | `E:modules/digitalisimo-import/kits/class-remapper.php` | `add_map( 'presets', … )` lleva también la identidad |
| La WP Consent API ofrece `wp_has_consent()` y el evento `wp_listen_for_consent_change` en `document`, con `detail = { categoría: valor }`; su script va en el pie sin `defer` y, sin plataforma de consentimiento, `wp_has_consent()` devuelve `true` | Plugin WP Consent API (`assets/js/wp-consent-api.js:90,193`; `inc/api-functions.php:45`) | El script de visitas se imprime después que el suyo, los dos en `wp_footer` 20 (§8) |
| LiteSpeed Cache atiende `do_action( 'litespeed_purge_post', $id )` | Plugin LiteSpeed Cache (`src/api.cls.php:111`) | La purga del índice de visitas lo llama, junto a `clean_post_cache()` |

## 4. Módulo, arranque y guardas

- **Arranque.** Una línea en `E:pro-elements.php`, justo después de `Front\Module::init()`: `require_once` de `D:class-module.php` y `Design\Module::init()`. Desde `pro_elements_load_plugin_func()` (en `plugins_loaded`, antes de `init`), `Design\Module::BOOT` arranca `Presets\Module`, `Backgrounds\Gradient`, `Backgrounds\Mask`, `Backgrounds\Pattern`, `Sticky\Sticky_State`, `Document\Document_Controls` y `Theme_Builder\Priority`, con una autocarga acotada al espacio de nombres `Digitalisimo\Elements\Design`. Las condiciones viven en `E:modules/digitalisimo-conditions/`, que ya arrancaba, y `Display_Conditions::init()` arranca `Page_Visits::init()`.
- **Guardas (V.9).** Cada submódulo llama a `Module::requires( $feature, $symbols )` con los símbolos que usa (`class_exists`, `method_exists` o `function_exists`) y exige `ELEMENTOR_VERSION` ≥ 4.3 (`Module::MIN_CORE`). Si falta algo, no registra controles ni ganchos, los valores guardados quedan inertes, nunca hay un error fatal y el editor avisa sólo a quien puede administrar. Las listas están en [ELEMENTOR_CORE.md](../ELEMENTOR_CORE.md).
- **Utilidades** (`D:class-css.php`): `color()`, `length()`, `svg_data_uri()`, `svg_clean()`, `core_selectors()` y `with_style_controls()`, puras y sin estado.
- **Cerrojo compartido** `Digitalisimo\Elements\Lock` (`D:class-lock.php`): `INSERT IGNORE` con token en opciones `digitalisimo_elements_lock_*`, sin carga automática, que se roban pasado su plazo. Lo usan los presets (`digitalisimo_elements_lock_presets`, 30 s) y la conversión de la cabecera y el pie de Hello, que conserva su opción `digitalisimo_theme_convert_lock` y sus 300 s. Por eso `class-lock.php` es un archivo obligatorio del ZIP. El de la transición de F-front no se migró: `tests/elements-front-transition.php` exige su `INSERT IGNORE INTO` en ese archivo.

## 5. Presets de estilo

### 5.1 Formato de la opción

Opción del sitio `digitalisimo_elements_presets`, **sin carga automática** y, sin presets, inexistente:

```json
{ "v": 1,
  "presets": {
    "tarjeta": {
      "type": "heading",
      "label": "Tarjeta",
      "settings": { "typography_typography": "custom", "typography_font_size": { "unit": "px", "size": 24 }, "__globals__": { "title_color": "globals/colors?id=primary" } },
      "hash": "<sha1 de los ajustes canónicos>",
      "updated": 1792000000,
      "inert": false } } }
```

- **Claves:** `type` es el tipo de widget o `elType` (`/^[A-Za-z0-9_.-]{1,191}$/`), y el slug cumple `/^[a-z0-9]+(?:-[a-z0-9]+){0,7}$/`, con 40 caracteres como máximo. `label` tiene hasta 60 caracteres. `hash` es `sha1` de los ajustes en forma canónica, y `updated`, un sello Unix.
- **Límites** (`Store::LIMITS`, filtro `digitalisimo_elements_preset_limits`): 200 presets, 16 KB de `settings` por preset, 128 KB la opción entera y 200 usos listados.
- **`inert`:** un preset cuyo tipo no está registrado en el sitio (un widget de WooCommerce sin WooCommerce, o el de un plugin ausente) se guarda tal cual. No se aplica ni se edita, aparece en la pestaña con «tipo no disponible» y sin acciones, viaja en los kits y vuelve a funcionar cuando el tipo existe.
- **Escritura:** bajo el cerrojo `digitalisimo_elements_lock_presets` y con `update_option( …, false )`. Crear, cambiar, renombrar o borrar exige `manage_options` sin la restricción `design` del Role Manager; vincular, poder diseñar.

### 5.2 Qué es estilo

`Store::sanitize_settings( $type, $settings, &$dropped )`, siempre dentro de `set_use_style_controls( true )`:

1. **Instancia de muestra** del tipo y la cadena del núcleo `Settings_Sanitizer` → `for()` → `using()` → `remove_invalid_settings()` → `kses_deep()`.
2. **Semilla:** controles con `selectors` no vacíos y sus variantes responsivas. Quedan fuera MEDIA, GALLERY, REPEATER, URL, WYSIWYG, CODE, `custom_css`, `_css_classes`, `css_classes`, `_element_id`, `digitalisimo_preset`, todo control con `prefix_class` y `__dynamic__`. `frontend_available` sólo excluye de la semilla (§13, A2).
3. **Cierre por dependencias**, repetido hasta que no cambie: lo que nombran `condition` y `conditions`, los `{{otro.X}}` de los selectores y el POPOVER_TOGGLE de cada grupo con algún campo. Lo que entra por `condition` debe ser SELECT, CHOOSE, SWITCHER o POPOVER_TOGGLE con un valor válido; lo que entra por `{{otro.X}}` pasa por la lista blanca de su tipo. `*_background` = `video` o `slideshow` se rechaza, porque es contenido.
4. **Lista blanca por tipo de control:** COLOR por `Css::color()`; SLIDER, DIMENSIONS, NUMBER y GAPS con números y las unidades del control; SELECT, CHOOSE, SWITCHER, POPOVER_TOGGLE y VISUAL_CHOICE dentro de sus opciones; FONT de la lista del núcleo; BOX_SHADOW y TEXT_SHADOW; TEXT y TEXTAREA con `selectors` sólo con `[A-Za-z0-9 #%.,()+\-_*/]`, sin `\`, `//`, `/*` ni comillas. Lo demás se descarta.
5. **Comprobación final** del preset serializado (`Store::safe()`): `\`, `//`, `url(`, `image-set(`, `image(`, `cross-fade(`, `@`, llaves, `<` o `expression(` descartan el preset entero.
6. **`__globals__`** sólo de las claves que sobreviven, con la forma `globals/(colors|typography)?id=[a-z0-9]+`.

### 5.3 Aplicación: aplanado en el CSS del documento

`Compiler::flatten()` en `elementor/element/before_parse_css`, para cada elemento con `digitalisimo_preset` que apunta a un preset existente, no inerte y del mismo tipo:

1. Se toman las claves del preset ya validado (`Compiler::validated()`, que vuelve a pasar `sanitize_settings()`; un preset con error no se aplica y el editor lo marca).
2. Se quitan las de los controles que el elemento guarda, en cualquier variante responsiva: un control es local **o** del preset, nunca mezclado.
3. Lo que queda se escribe en el elemento con `set_settings()`, y sus `__globals__` se fusionan sin pisar los locales.
4. El elemento genera su CSS con sus propios selectores (RTL, `.woocommerce` y `{{ID}}` incluidos), con el valor del preset donde antes iba el valor por defecto local.

Nada se guarda: `_elementor_data` y el HTML no cambian, la caché de elementos no se toca y el CSS del kit no lleva nada (0 B). Crear, actualizar o borrar un preset deja pendiente **un** `files_manager->clear_cache()` por petición, en `shutdown`; renombrar no vacía nada.

### 5.4 Vínculo, editor y pestaña

- **Control** `digitalisimo_preset` (SELECT, `render_type => 'ui'`, sin `prefix_class` ni `frontend_available`, `style_transfer`), en `elementor/element/common/_section_style/before_section_start` (widgets) y `elementor/element/container/section_layout/before_section_start` (contenedores). En el front se registra sin opciones y no lee la opción.
- **`Guard::on_save()`** en `elementor/document/save/data`, prioridad 30 (después del remapeo de K, en 20): vacía un vínculo a un preset que no existe o es de otro tipo y, con la restricción `design`, restaura el vínculo anterior de cada elemento.
- **Acciones AJAX** (`elementor/ajax/register_actions`): `digitalisimo_preset_save`, `…_rename`, `…_delete` y `…_uses`. Responden `{ ok, slug, presets, options, types, total }`; los errores llevan su `code`: 400 si los datos fallan, 403 sin permiso, 404 si no existe y 409 para `type_mismatch`, `inert`, `in_use` y `busy`. Borrar un preset con usos se rechaza con `in_use`.
- **Usos** (`Store::uses()`): una consulta a `postmeta` con `$wpdb->esc_like()` que excluye revisiones, papelera y borradores automáticos, más los valores por defecto del kit, con un tope de 200. Sólo la piden la pestaña y «Borrar».
- **`presets-editor.js`** (8 185 B, ES2017, sólo en `elementor/editor/after_enqueue_scripts`):
  - filtra las opciones del control por `widgetType` o `elType` y marca «con error» los que no pasan el saneado;
  - pinta la vista previa por `editor/style/styleText`, con un `ControlsCSSParser` nuevo sólo con lo que el modelo no guarda (`toJSON( { remove: [ 'default' ] } )`);
  - añade el menú contextual: «Guardar estilo como preset…», «Usar sólo el preset», «Desvincular y copiar estilos» y actualizar el preset.

  Comparte con PHP las 10 fixtures de `tests/sources/design/presets.json`.
- **Pestaña «Presets de estilo»** de Ajustes del sitio (`Kit_Tab`, grupo «Sistema de diseño», `elementor/kit/register_tabs`), con los usos de cada preset, renombrar y borrar.

### 5.5 Kit

- **Parte `presets`** (`D:presets/class-kit-part.php`): `version()` = 1 y `kit_meta_keys()` = `[]`, porque no hay meta del kit. Se registra con `digitalisimo_elements_kit_parts` en `Editor::init()`.
  - **Exportar:** la opción entera, con los inertes, su `hash` y su `updated`.
  - **«Nuevo»:** reemplaza.
  - **«Agregar»:** fusiona por slug. Con la misma huella (tipo y ajustes) reutiliza; con otra, el preset del kit pasa a `<slug>-k<6 hex>`, con el hex sacado de la huella, así que reimportar no duplica; un slug libre se añade. Nunca cambia un preset del sitio.
  - **Escritura:** sólo con `Store::replace( $data, $keep )`. Los slugs de `$keep` (los del sitio) se escriben tal cual.
- **Mapa:** `add_map( 'presets', [ origen => final ] )` para **todos** los slugs del kit, también la identidad. Lo que no entra apunta a un slug que no existe, nunca al preset del sitio con el mismo nombre, y `Guard` lo vacía.
- **Referencia:** `element · * · digitalisimo_preset · map:presets`, sin `gate`.
- **Ajustes:** los `__globals__` se reescriben con `Remapper::rewrite_globals()`, y `digitalisimo_preset` sólo en los tipos que añadió `element-defaults` en esa importación.
- **Revertir** (en orden inverso, así que `presets` revierte antes que `element-defaults`): valores por defecto primero, después la opción, con `Revert_Context::unchanged()`. Lo editado después se conserva y se informa; en «nuevo» vuelven los presets del sitio.
- **Códigos del informe**, en la sección «Ajustes del kit»: `settings.presets.{inert,renamed,invalid,rejected,limit,dropped,site-invalid,unavailable,edited,defaults-edited}`, además de `map.miss`.

### 5.6 La única línea en K

`digitalisimo_elements_kit_used_globals_trees( array $trees, Export_Context $context )`, en `Kit_Settings::export()` (`E:modules/digitalisimo-import/kits/parts/class-kit-settings.php:68`), antes de calcular `used`: una parte aporta árboles de ajustes para que sus colores y tipografías globales viajen y se traduzcan. Sólo suma, y lo que no es array se ignora. La usa la parte `presets`. Es la única excepción declarada a «sin tocar K» ([k-kits.md](k-kits.md), §15), y la prueba `tests/elements-design-presets-kit.php`.

## 6. Degradados, máscaras y patrones

### 6.1 Degradados

`D:backgrounds/class-gradient.php`, sobre el grupo de fondo del núcleo, en sección, columna, contenedor y widget (sólo la pila `common`), en normal, hover y superposición:

- **Controles propios** por prefijo de grupo, detrás de `gradient_position`: `digi_stops` (repetidor de hasta 8 paradas intermedias), `digi_kind` (lineal, radial o cónico), `digi_conic_from` (ángulo, responsivo), `digi_conic_at` (9 posiciones) y `digi_repeat`. Las paradas A y B siguen siendo las del núcleo.
- **CSS:** las reglas van en los mismos selectores y `@media` que el núcleo y lo sustituyen, con `add_rules()` en array. Sin controles nuevos, la hoja queda byte a byte igual. A vacío se pinta `transparent`; un A o B no válido deja el degradado del núcleo. En cada dispositivo, lo que no tiene valor se hereda del mayor, como en el núcleo.
- **`guard_selectors()`** (`elementor/files/css/selectors`) descarta la regla de un control propio cuyo valor no es suyo: un slug de otra versión llegado por un kit o CSS inyectado en un SELECT, porque el núcleo escribe `{{VALUE}}` sin validar.
- **Vista previa:** `design-editor.js` (8 190 B, ES2020, sólo en el editor) calcula las mismas cadenas que PHP; `tests/sources/design/gradients.json` (24 casos) las fija para los dos.

### 6.2 Biblioteca propia

12 máscaras (círculo, elipse, rombo, hexágono, octógono, triángulo, estrella, cruz, escudo, gota, corazón y rectángulo redondeado) y 12 teselas (puntos, rejilla, rayas, tablero, rombos, anillos, signos más, ladrillos, puntos alternos, líneas cruzadas, semicírculos y cuadros), sin `diagonal`, `arco` ni `ola`.

- Son primitivas geométricas dibujadas para F7a, con `viewBox` y un solo relleno opaco. Ocupan de 90 a 184 B y se comprobaron en Chrome como `mask-image` real; las teselas encajan sin costuras.
- `D:backgrounds/MANIFEST.json` lleva de cada archivo su sha256, sus bytes, su autoría («DIGITALÍSIMO, GPL-3.0-or-later») y su fecha.
- `D:backgrounds/library.php` (6 184 B) se genera con `node scripts/design-library.mjs --write`; sin argumentos, el script sólo comprueba. `tests/elements-design-backgrounds.php` exige que se regenere idéntico y rechaza `<script`, `on*=`, `href` externo, `<foreignObject` e `<image`.

### 6.3 Máscaras, patrones y SVG del sitio

- **Máscara de recorte** (`class-mask.php`) en contenedores, secciones y columnas (en la columna, sobre `.elementor-widget-wrap`), en `section_effects/after_section_end`. Los widgets conservan la máscara del núcleo.
- **Patrón** (`class-pattern.php`) en los cuatro, como POPOVER_TOGGLE con su color, tamaño, repetición, opacidad, desplazamiento, volteo y modo de fusión. Va en `::after` con `display: block`, y el elemento lleva `isolation: isolate`.
  - Con «Elevar sobre lo siguiente», activado por defecto, se eleva sin pisar un `z-index` del usuario: en contenedores con `--z-index: 1`, que el control del núcleo puede sustituir; en el resto con `:where(…){z-index:1}`.
- **Una definición por documento.** El diccionario de cada control lleva el slug a `var(--digi-mask-<slug>)` o `var(--digi-tile-<slug>)`. `elementor/element/parse_css` reúne los slugs del documento y añade una sola vez `:root{--digi-mask-<slug>:url("data:…")}`. En la vista previa, las 24 variables las define PHP una vez en `elementor/preview/enqueue_styles` (5,8 KB en línea, 944 B en gzip), sólo en el editor.
- **Codificación** (`Css::svg_data_uri()`):
  1. rechazo previo con `svg_rejected()` y tope de 64 KB para lo recibido;
  2. `Svg_Sanitizer::sanitize()` del núcleo;
  3. minimizado, con un resultado que empieza por `<svg`, pesa ≤ 8 KB y vuelve a pasar el rechazo;
  4. si no hay `'`, cada `"` pasa a `'`;
  5. `rawurlencode()`; en JS, `encodeURIComponent()` con `!'()*` también codificados.

  Resultado: `url("data:image/svg+xml,…")`, sin `;`, `#`, llaves, `//` ni `://`. La biblioteca se genera con el mismo algoritmo.
- **SVG del sitio:** se eligen con un control MEDIA, sólo SVG de la biblioteca de medios del sitio actual. Al guardar, el adjunto pasa por `Css::svg_clean()` (rechazo, saneador del núcleo, rechazo otra vez y tope de 8 KB) y se copia a `uploads/digitalisimo-elements/masks/<sha8>.svg`, con la ruta comprobada con `realpath`. La ruta relativa va en el control oculto `digitalisimo_mask_file` o `digitalisimo_pattern_file`, y el CSS lee esa copia, nunca el adjunto ni una URL. Si la copia falla o el SVG es hostil no hay `mask-image`: el elemento se ve entero.
- **Kit:** nada nuevo que declarar. Las paradas son `__globals__`, el SVG elegido es una referencia `media` derivada de su control, la parte `files` de K exporta, sanea y traduce la ruta relativa de la copia, y las formas de la biblioteca viajan por su slug.

## 7. Condiciones nuevas

### 7.1 Las siete

| ID | Grupo | Comparadores | Caché |
|---|---|---|---|
| `digitalisimo_cookie` | Visitante | existe, no existe, es, no es (sin «contiene») | Variable |
| `digitalisimo_page_visit` | Visitante | visitó alguna de, no visitó ninguna de | Variable |
| `digitalisimo_product_stock_of` | WooCommerce | estados de existencias y ≤ o ≥ unidades, con «alguno» o «todos» | Variable |
| `digitalisimo_field` | Página | diez, sobre una meta del contenido o del término consultado | Cacheable |
| `digitalisimo_user_field` | Usuario | ocho, sobre una clave de la lista blanca | Cacheable |
| `digitalisimo_author` | Página | es el usuario actual, no lo es, tiene uno de estos roles, no tiene ninguno | Cacheable |
| `digitalisimo_search` | Página | es, no es, contiene, no contiene, es uno de, sin resultados, con resultados (sin expresiones regulares) | Cacheable |

- **Dónde:** en el gestor de Display Conditions de PRO, el mismo en el que ya registraba las suyas `digitalisimo-conditions`. Se registran siempre, aunque falte WooCommerce; las de existencias dan `false` sin él y el editor sólo las ofrece con él.
- **Lista vacía:** una lista de IDs o un valor vacío nunca cumplen, con ningún comparador ni `match`.
- **Caché:** las cuatro cacheables entran en `Visitor_Content::CACHEABLE_CONDITIONS`; las tres variables sacan la página de la caché de página.
- **No protegen nada.** Cada una lleva el aviso «decide qué se muestra, no quién puede leerlo», en su configuración (`digitalisimo_notice`) y en el aviso del editor al cerrar el modal.
- **Campos:** metas del contenido o del término consultado, salvo las protegidas; con ACF, `get_field( key, id o 'term_' . id, false )`; fechas con un ayudante propio `Y-m-d`. Claves vetadas por fragmento (`pass`, `secret`, `token`, `key`, `nonce`, `hash`, `salt`…), ampliables con `digitalisimo_elements_field_condition_denied`. El usuario se compara sólo por la lista blanca `first_name`, `last_name`, `nickname`, `description`, `locale` y `billing_country` (filtro `digitalisimo_elements_field_condition_user_keys`, que pasa por el mismo veto); sin sesión, `false`. Nunca se leen opciones.
- **Kit:** `condition · digitalisimo_page_visit · pages.* · post` y `condition · digitalisimo_product_stock_of · products.* · post`, las dos con `gate`.
- **Editor.** Un script en línea, en posición `before` del handle `e-display-conditions` (`elementor/editor/after_enqueue_scripts`):
  - el modal abierto desde v4 recibe un `conditionsConfig` sin las siete en `conditionsByGroup` ni en `flattenedConditionOptions`, pero con `conditions` completo, para que un elemento que ya las trae se siga pintando;
  - sin permiso para la cookie, v3 tampoco la ofrece;
  - al cerrar el modal avisa, una vez por elemento, con el texto común y, si una condición variable está en una cabecera o un pie, de que todo el sitio sale de la caché de página;
  - la ayuda de «Visitó una página» lleva un botón a «Rendimiento» → «Privacidad» (sólo con `manage_options`).

### 7.2 El centinela

Una condición desconocida se da por cumplida (`and-condition.php:34-39`), así que con sólo volver a 4.3.0.163 todo lo condicionado por una condición nueva se mostraría a todos. Para que **falle cerrado**:

1. En `elementor/document/save/data`, `Conditions_Guard` añade al final de cada grupo Y que contenga una condición de F7a:

   ```json
   { "condition": "digitalisimo_special_page", "comparator": "is_one_of", "pages": [ "digitalisimo_f7a" ] }
   ```

   Vale en v3 (también el formato antiguo de lista plana) y en el prop v4. Es idempotente, y un documento sin condiciones de F7a sale idéntico (`===`).
2. En 4.3.0.164, `Special_Page_Condition::check()` devuelve `true` con `pages === [ 'digitalisimo_f7a' ]`. Es el único cambio en `class-conditions.php`, en un bloque marcado `// F7a · centinela: inicio … fin`.
3. En 4.3.0.163 esa página especial no existe, la condición da `false` y el grupo falla.
4. En `elementor/document/load/data` el centinela se retira para que el editor no lo muestre, pero **sólo** en modo de edición y en `elementor_ajax` (al insertar plantillas, que `is_edit_mode()` no reconoce): el front no decodifica nada. Al volver a guardar no se duplica.
5. Un kit lo conserva tal cual, porque `special_page` no tiene referencia en K, y los marcadores de K no se tocan.

**Por qué `special_page`:** ya existe en 4.3.0.163, así que esa versión la evalúa en lugar de darla por cumplida, y con una página que no conoce siempre da `false`. Sin WooCommerce, en cualquier versión, las de existencias tampoco se cumplen.

**Prueba** (`tests/elements-conditions-more.php`): quita el bloque marcado de `class-conditions.php`, comprueba que el resultado tiene la sha256 de 4.3.0.163, lo carga en otro espacio de nombres y evalúa con las clases reales de PRO (`Or_Condition` y `And_Condition`, con sus sumas fijadas). Sin centinela, una condición desconocida se cumple. Con centinela, el grupo no se cumple (cookie, «no visitó», «todos agotados»), tampoco junto a otro grupo O falso. En v4, el transformador real `Condition_Group` de PRO decodifica el centinela y el grupo da `false`.

### 7.3 La corrección de la caché v4

PRO sólo miraba `e_display_conditions` para decidir si un elemento es dinámico, así que la caché de elementos congelaba **cualquier** condición v4: servía a todos lo que vio el primer visitante. Además, `Visitor_Content::condition_ids()` no leía el formato real del prop v4 (cada condición es una cadena JSON), y una condición v4 variable no sacaba la página de la caché de página. En 4.3.0.164:

- `Visitor_Content::is_dynamic_content()` devuelve `true` si el prop `display-conditions` tiene algún grupo (`has_atomic_conditions()`).
- `condition_ids()` suma `atomic_condition_ids()`, que decodifica esas cadenas, y sigue leyendo el formato anterior.

Es una corrección para todas las condiciones v4, no sólo las de F7a, y es conservadora: sólo puede dejar más elementos fuera de la caché de elementos y más páginas fuera de la caché de página. Los sitios v4 con condiciones variables dejan de servirse desde la caché de página.

### 7.4 La cookie

- **Nombre** normalizado como PHP (`.` y espacio pasan a `_`), con `[A-Za-z0-9_-]{1,64}`.
- **Vetos por fragmento**, sin distinguir mayúsculas: `wordpress_`, `wp-postpass_`/`wp_postpass_`, `wp-settings-`/`wp_settings_`, `wp_woocommerce_session_`, `woocommerce_`, `comment_author_`, `phpsessid`, `sess`, `auth`, `token`, `login`, `pass`, `secret`, `csrf`, `nonce`, `key` y `hash`. El filtro `digitalisimo_elements_cookie_condition_denied` sólo amplía la lista.
- **Comparación:** con `hash_equals()`, sólo presencia o igualdad; nunca subcadenas, que permitirían leer el valor carácter a carácter.
- **Permiso en el servidor:** añadir o cambiar una condición de cookie exige `unfiltered_html` y, en una red, ser superadministrador. Sin él:
  - un elemento que ya existía recupera sus condiciones guardadas (v3 y v4);
  - en uno nuevo, la condición de cookie que no estaba ya en el documento se queda sin nombre, y nunca se cumple;
  - copiar una condición idéntica que ya está en el documento, o quitar una, no lo necesita;
  - nunca se quita una condición, porque quitarla ampliaría quién ve el elemento.

## 8. «Visitó una página» y consentimiento

- **Cookie:** `digi_seen` en un sitio único y `digi_seen_<blog_id>` en una red, con la ruta de `home_url( '/' )` saneada. Guarda hasta 50 IDs en base 36 separados por `.`, dura 30 días y lleva `SameSite=Lax`, más `Secure` cuando `is_ssl()` o `home_url()` van por HTTPS. El servidor nunca la escribe; al leerla sólo acepta IDs en base 36.
- **Script** en `wp_footer` 20, con `wp_print_inline_script_tag()` (CSP con `nonce`) e `id="digitalisimo-page-visit"`. Mide 350 B en un sitio único, 367 B en una red con `/sub/` y HTTPS y 381 B con una ruta de 17 caracteres. Sólo se imprime en `is_singular()` de una página rastreada; nunca en `is_home()`, la tienda, los feeds, `is_preview()`, `?elementor-preview`, el editor ni la administración. El filtro `digitalisimo_elements_page_visit_tracking( true, $id )` lo apaga donde se quiera.
- **Política de consentimiento:**
  1. El servidor imprime el script sólo si existe la WP Consent API (`function_exists( 'wp_has_consent' )`) **o** si la opción `page_visits_no_consent` del sitio está activada.
  2. El script escribe sólo si `wp_has_consent( 'preferences' )` lo permite o, sin la API, por la opción, y vuelve a decidir con `wp_listen_for_consent_change`.
  3. Con la API instalada pero sin una plataforma de consentimiento, `wp_has_consent()` devuelve `true` (es su semántica): manda la plataforma que el sitio instale.
  4. Sin escritura, «visitó alguna de» no se cumple y «no visitó ninguna de» sí. Lo dicen la ayuda de la condición y el aviso del editor.
  5. `page_visits_no_consent` («Registrar visitas sin gestor de consentimiento», en «Rendimiento» → «Privacidad») viene apagada. Cumple los cuatro puntos de paridad (`Settings::defaults()`, `sanitize()`, `network_privacy_fields()` y `save_network()`), con «Heredar de la red» sólo en un sitio de una red. Está fuera de `KEYS`: no entra en la huella de la transición de F-front ni vacía cachés.
- **Privacidad:** `wp_add_privacy_policy_content( 'DIGITALÍSIMO Elements', … )` con todas las cookies propias de Elements: `digi_seen`, `digi_visitor_tz`, `digi_session`, `digi-visit-count-…` y `_digi_…`. Con la WP Consent API, además, `wp_add_cookie_info()` en `preferences` y el filtro `wp_consent_api_registered_<plugin>`.
- **Índice** en la opción `digitalisimo_elements_tracked_pages`, con carga automática y la forma `{ "docs": { "<id>": [ ids ] }, "union": [ ids ] }`. La unión (≤ 200) se calcula al escribir, para que el front la lea sin recorrer documentos; sin documentos rastreados vuelve a `[]`.
  - Se crea vacía en `admin_init`, y el front sólo la lee si está en `wp_load_alloptions()`: 0 consultas sin ella.
  - Cuentan sólo documentos `publish` con `_elementor_edit_mode = builder` (los de la biblioteca, sólo con `_elementor_conditions`), nunca revisiones, borradores ni marcadores. No hay meta por documento, así que nada viaja en el WXR.
  - Se recalcula en `elementor/document/after_save`, `transition_post_status` (cuando cambia el estado y `publish` está en un lado), `deleted_post` y `digitalisimo_elements_kit_imported`, con una consulta preparada y `esc_like()`.
  - Lee el prop v4 con `Display_Conditions::extract_from_settings()` si existe; si falla o no resuelve, a mano.
- **Purga:** cada página que entra en la unión o sale de ella pasa por `clean_post_cache()` y `do_action( 'litespeed_purge_post', $id )`. Las demás cachés de página se vacían a mano, y lo dice `readme.txt`.

## 9. Prioridad del Theme Builder

- **Control** `digitalisimo_tb_priority` (NUMBER de 1 a 99; vacío es automática) en `elementor/documents/register_controls`, sólo en `Theme_Document` de ubicación no múltiple (o en una de las cuatro del núcleo si el tema no la registra), inyectado tras `post_status` con respaldo en `post_title`.
- **Línea en el derivado de PRO** (`E:modules/theme-builder/classes/conditions-manager.php:412`, nota en `DIGITALISIMO_CHANGES.md`): justo después del `asort( $conditions_priority )`, `apply_filters( 'digitalisimo_elements_theme_builder_priorities', $conditions_priority, $location )`.
- **`Priority::apply()`:** sin candidatas en el índice devuelve el mismo array (`===`). Con alguna, ordena con la escala de PRO: gana el número menor; a igual número gana la manual, y entre manuales iguales, el ID más alto. Las automáticas empatadas conservan su orden. La prioridad sustituye a la calculada en cualquier `include` que se cumpla, y un `exclude` sigue mandando. Popups y ubicaciones de gancho (múltiples) no se reordenan.
- **Índice** `digitalisimo_elements_tb_priority`, con carga automática. Existe siempre (`install()` lo crea `[]` en `admin_init`, o corrige uno sin carga automática) y sólo se lee si está en `wp_load_alloptions()`. Se reindexa en `elementor/document/after_save`, `trashed_post`, `untrashed_post`, `deleted_post` y `added/updated/deleted_post_meta` de `_elementor_page_settings` en `elementor_library`, y sólo escribe si cambia.
- **Kit:** campo `priority` de `digitalisimo_elements_kit_theme_builder_fields`. `export()` da el número o `null`; `import()` escribe el ajuste si falta y reindexa con el valor recibido.

## 10. Sticky «al fijarse»

- **Control** `digitalisimo_sticky_state` (vacío, `show`, `hide`, `show-effects` y `hide-effects`) en `section`, `container` y `common`, en `section_effects/before_section_end` detrás de `sticky_divider`, con `prefix_class => 'digi-sticky-'` y `render_type => 'ui'`. Se pone en los **descendientes**: en el propio elemento fijo no tiene efecto, y un aviso lo dice.
- **Hoja** `D:sticky/assets/sticky-state.css`, de 475 B (224 B en gzip -9), registrada en `elementor/frontend/after_register_styles` y encolada por el cargador del núcleo con la declaración `assets` del control, como `e-sticky`. No lleva JS, `before_render`, pila de ancestros ni estado por petición. Es una sola regla:

  ```css
  body:not(.elementor-editor-active) :is(
    :is([data-settings*='"sticky":"top"'],[data-settings*='"sticky":"bottom"']):not(.elementor-sticky--active) .digi-sticky-show,
    .elementor-sticky--active .digi-sticky-hide,
    :is([data-settings*='"sticky":"top"'],[data-settings*='"sticky":"bottom"']):not(.elementor-sticky--effects) .digi-sticky-show-effects,
    .elementor-sticky--effects .digi-sticky-hide-effects
  ){display:none!important}
  ```

  Agrupar los cuatro estados la deja por debajo de 0,5 KB; con cuatro reglas sueltas eran unos 590 B.
- **Qué se gana:** el HTML de ningún elemento existente cambia, funciona con la caché de elementos y con hijos dinámicos, y no hay destello, porque `show` está oculto desde el primer pintado. El espaciador conserva la altura del estado normal. Sin ancestro fijo no coincide ninguna regla y el elemento se ve, y en el editor se ve todo.
- **Comprobado** en Chrome sin interfaz con la hoja real (no es Playground): los cuatro estados con el ancestro sin fijar, `--active`, `--active --effects`, el espaciador y sin sticky.
- **Dependencia:** el formato de `data-settings` (§3). Lo vigilan `tests/elements-sticky-state.php`, que lee `element-base.php`, `controls-stack.php` y `modules/sticky/module.php`, y [ELEMENTOR_CORE.md](../ELEMENTOR_CORE.md). La alternativa preparada, un ajuste oculto `_digi_sticky_scope` calculado al guardar, no hizo falta.

## 11. Controles de documento

- **Dónde:** en `elementor/documents/register_controls`, en los documentos `PageBase` (entradas, páginas, páginas de aterrizaje y productos) salvo el kit y los botones flotantes, y en la página de la biblioteca. Nunca en plantillas del Theme Builder, popups ni elementos de bucle. Se inyectan tras `post_status` con respaldo en `post_title`; el núcleo inyecta «Ocultar título» después en el mismo punto, así que quedan justo debajo de él.
- **«Ocultar imagen destacada»** (`digitalisimo_hide_featured_image`), con cualquier tema. `elementor/frontend/widget/should_render` devuelve `false` para `theme-post-featured-image` cuando `get_the_ID()` es el contenido consultado y no se está en un bucle (§3). Se oculta en la plantilla de entrada y en la propia página. `og:image` no cambia. En las peticiones que la ocultan, `elementor/element/is_dynamic_content` marca ese widget como dinámico, para que la caché de la plantilla no guarde la imagen oculta.
- **«Ocultar cabecera»** y **«Ocultar pie»** (`digitalisimo_hide_header`, `digitalisimo_hide_footer`), sólo con el tema DIGITALÍSIMO (`Theme_Compat::active()`). En `template_redirect` 15, para la vista consultada:
  - `skip_doc_in_location()` en cada plantilla de `get_documents_for_location()`;
  - el filtro público `digitalisimo_theme_header_footer` (20) sólo para esa parte y esa petición, que quita también el respaldo del tema.

  Los ganchos `digitalisimo_before_*` y `after_*` siguen, y ninguna función `digitalisimo_theme_*` se llama. Con otro tema no se registran: una nota remite a las condiciones `exclude` de la plantilla, y un ajuste guardado antes con el tema propio no se aplica. La ayuda de «Ocultar título» no cambia.
- **El tema no cambia:** `tests/elements-document-controls.php` exige que el árbol de `digitalisimo-theme/` sea byte a byte el de 1.0.0.

## 12. Ganchos y filtros nuevos

| Gancho | Firma | Dónde | Para qué |
|---|---|---|---|
| `digitalisimo_elements_theme_builder_priorities` | `( array $priorities, string $location ): array` | `E:modules/theme-builder/classes/conditions-manager.php` (derivado de PRO) | Reordenar las plantillas candidatas de una ubicación; sin manuales, el mismo array |
| `digitalisimo_elements_kit_used_globals_trees` | `( array $trees, Export_Context $context ): array` | `Kit_Settings::export()` de K | Aportar árboles de ajustes cuyos globales deben viajar; sólo suma |
| `digitalisimo_elements_preset_limits` | `( array $limits ): array` | `Store::limits()` | Ajustar los topes de §5.1 |
| `digitalisimo_elements_cookie_condition_denied` | `( array $extra, string $name ): array` | `Conditions_Guard` | Ampliar los nombres de cookie vetados |
| `digitalisimo_elements_field_condition_denied` | `( array $extra, string $key ): array` | `More_Condition` | Ampliar las claves de campo vetadas |
| `digitalisimo_elements_field_condition_user_keys` | `( array $keys ): array` | `User_Field_Condition::keys()` | Cambiar la lista blanca del campo del usuario, siempre con el veto |
| `digitalisimo_elements_page_visit_tracking` | `( bool $track, int $id ): bool` | `Page_Visits` | Apagar el registro de visitas en una página |

Además, F7a se registra en los ganchos de K sin cambiarlos: la parte `presets` en `digitalisimo_elements_kit_parts`; las referencias de §5.5 y §7.1 en `digitalisimo_elements_kit_references`; el campo `priority` en `digitalisimo_elements_kit_theme_builder_fields`, y el reindexado de visitas en `digitalisimo_elements_kit_imported`. Usa también el filtro público del tema `digitalisimo_theme_header_footer`. Los ganchos del núcleo que usa están en [ELEMENTOR_CORE.md](../ELEMENTOR_CORE.md).

## 13. Desviaciones de cada frente

Respecto de la sección V de la especificación, con su motivo.

**A, B y C · base, almacén y aplanado**
1. En el front y en WP-CLI no hay `options` ni `size_units` (§3): las opciones se validan por su forma (`Store::OPTION_TOKEN`) y las unidades contra `Store::UNITS`. Un valor bien formado escrito por WP-CLI que no está entre las opciones se aplica en el front y no en la vista previa.
2. Cierre: `frontend_available` sólo excluye de la semilla, porque `background_background` lleva esa marca y es la condición de todo su grupo. La regla «SELECT, CHOOSE, SWITCHER o POPOVER_TOGGLE» vale para lo que entra por `condition`, y lo que entra por `{{otro.X}}` (`color_b`, `color_stop`) pasa por la lista blanca de su tipo. `prefix_class` también queda fuera del cierre.
3. Lista blanca ampliada con BOX_SHADOW, TEXT_SHADOW y VISUAL_CHOICE; TEXT y TEXTAREA rechazan además `/*` y `*/`.
4. Vaciado agrupado por petición, en `shutdown`, en vez de una ventana de 2 s: el editor ya agrupa sus acciones en una petición. Renombrar y un `replace()` sin cambios no vacían.
5. `Guard` en prioridad 30, detrás del remapeo de K (20): antes vaciaría un slug que `map:presets` aún tenía que traducir.
6. `digitalisimo_preset` con `render_type => 'ui'` en vez de `'none'`: con `'none'`, deshacer o «Pegar estilo» no repintaban la vista previa. `'ui'` sólo actúa en el editor; el HTML del front no cambia.
7. Desaparecen `Compiler::selector()`, `css_for()` y la meta del kit; `Store::all()` y `replace()` no reciben el kit.
8. `Presets\Module` arranca `Editor`, que registra la parte, la referencia, el filtro de globales y el script.
9. Firmas ampliadas: `Css::color()` sin tipo, `core_selectors()` con un tercer argumento opcional, `sanitize_settings()` con `&$dropped` y `digitalisimo_preset_save` con el slug vacío, que crea uno con `Store::unique_slug()`.
10. El cerrojo de la transición de F-front no pasa a `Lock` (§4).

**D y E · editor y kit**
1. `presets-editor.js` usa funciones flecha y `const` (ES2017) para caber en 8 KB; los demás JS propios usan `var` y `function`.
2. La acción `uses` no la llama el JS: la pestaña calcula los usos en PHP.
3. Para una clave que no es un control, el JS quita el sufijo de dispositivo y `Store::root()` devuelve `null`. Con ajustes saneados no cambia nada, porque todas sus claves son controles.

**F y G · fondos**
1. Las variables de la biblioteca en la vista previa las define PHP (§6.3), no `design-editor.js`: el script queda por debajo de 8 KB sin duplicar la biblioteca.
2. Las tablas del script (posiciones, valores por defecto del núcleo, claves responsivas, `Css::SVG_DENIED`, topes y mensajes) vienen de `Gradient::editor_config()`, su única fuente.
3. `design-editor.js` usa ES2020 (`?.`) para caber en 8 KB; sólo corre en el editor.
4. El patrón es un POPOVER_TOGGLE y no un SWITCHER.
5. «Elevar» nunca pisa un `z-index` del usuario (§6.3).
6. Con un SVG hostil o una copia inválida quedan `mask-size`, `mask-position` y `mask-repeat`, que sin imagen no recortan nada.
7. El editor avisa cuando él mismo rechaza el SVG (lista de rechazo y tope); un fallo de escritura en disco no avisa.
8. `guard_selectors()` no estaba en la especificación (§6.1).
9. `Svg::get_inline_svg()` no se reutiliza (§3).

**H e I · condiciones y visitas**
1. La regla de `digitalisimo_author` y `digitalisimo_search` va dentro de `comparator`: el modal de PRO no oculta controles según otro, y `tests/elements-display-conditions.php` exige un `comparator` en cada condición propia.
2. El índice es `{ docs, union }` en vez de `{ "doc": [ids] }`, para que el front lea la unión sin recorrer documentos.
3. Los vetos de la cookie son por fragmento, más estrictos que por prefijo.
4. `Secure` lo decide el servidor: con la comprobación en JS, el script pasaba de 400 B en una red.
5. El aviso común no va en `get_label()` ni en un RAW_HTML, que el modal trataría como campo de texto y que rompería el selector: va en la configuración de cada condición y en el aviso al cerrar el modal.
6. `tests/elements-display-conditions.php` sólo cambia sus dos recuentos (12 → 19 y 28 → 35), porque las siete se registran siempre.
7. La prueba del script del editor de condiciones está en `tests/elements-page-visits.mjs`.
8. Las plantillas de biblioteca sin `_elementor_conditions` (secciones incrustadas con el widget Plantilla) no activan el rastreo, que es la regla literal de V.4.3.
9. No hay aviso en el editor cuando la unión pasa de 200: se corta.
10. El centinela se retira también en `elementor_ajax` (§7.2).
11. Añadidos: `wp_add_cookie_info()`, `wp_consent_api_registered_<plugin>` y los filtros de vetos de §12.

**J, K y L · prioridad, sticky y documento**
1. La hoja del sticky es una sola regla (§10).
2. `render_type => 'ui'` en el control del sticky: con `'none'` el editor no repinta la clase.
3. Con otro tema, una nota RAW_HTML aparte en lugar de los controles de cabecera y pie, para no cambiar la ayuda de «Ocultar título». Quitarla es quitar la rama `else` de `register_controls()`.
4. Reindexado también en `deleted_post_meta`, por simetría: borrar los ajustes de una plantilla la devuelve a automática.
5. `apply()` no reordena ubicaciones múltiples, aunque una plantilla de ese tipo traiga el ajuste por un kit o por REST.
6. `install()` del índice de prioridad corre en `admin_init`; el front nunca escribe.
7. La imagen destacada se marca como dinámica sólo en las peticiones que la ocultan (§11).
8. En la página de entradas y en la tienda, leer los ajustes del documento puede costar una consulta; en las vistas individuales, 0.
9. La alternativa `_digi_sticky_scope` no hizo falta (§10).

## 14. Límites declarados

- **Hueco de nombres de K con `masks/<sha8>.svg`.** Si en el destino ya existe `masks/<sha8>.svg` con otro contenido, K guarda la copia que llega como `<sha8>-k<hex>`, pero no reescribe la ruta relativa de `digitalisimo_mask_file` o `digitalisimo_pattern_file`, porque `Remapper::find_media()` sólo traduce URL. El control sigue apuntando a la copia que ya había, también saneada. Exige que coincidan los 8 hex con distinto contenido, lo que en la práctica sólo ocurre por manipulación. Arreglarlo exige traducir en K esas rutas relativas con `map:files` (otra etapa).
- **Condiciones de F7a fuera de v4.** No se ofrecen en el control v4. Si un documento v4 las trae (por un kit o por la API), funcionan y llevan el centinela, pero el prop v4 `display-conditions` sigue sin `kind` en el registro de K: al importar un kit, sus IDs (páginas de «Visitó una página», productos) no se traducen. Las mismas condiciones en v3 sí se traducen. Arreglarlo exige un `kind` para el prop v4 en K (otra etapa).
- **Volver a 4.3.0.163.** Tras «Vaciar archivos y datos» en Herramientas del constructor:
  - los elementos vinculados vuelven a sus valores locales y por defecto;
  - el degradado queda en A → B, y máscaras y patrones desaparecen;
  - los elementos «al fijarse» se ven siempre;
  - lo condicionado por una condición de F7a **no** se ve;
  - el Theme Builder vuelve al orden automático;
  - la cabecera, el pie y la imagen destacada se ven.

  Los ajustes quedan guardados y vuelven a aplicarse al actualizar.
- **Presets:**
  - un preset no lleva las paradas intermedias de un degradado (son un repetidor) ni el SVG de una máscara o un patrón;
  - en la vista previa, si un control responsivo tiene un valor por defecto en tableta y el preset sólo define escritorio, la regla del preset gana en tableta, y en el front no (raro: esos valores suelen ir vacíos);
  - las plantillas de red de Tools pueden copiar un elemento vinculado a un preset que el sitio destino no tiene: se ve con su estilo local.
- **«Visitó una página»:** no hace nada sin gestor de consentimiento ni la opción. La unión se corta en 200. La purga cubre `clean_post_cache()` y LiteSpeed; las demás cachés de página se vacían a mano.
- **Máscaras y patrones:** la máscara recorta todo su contenido, también los desplegables. Con «Elevar» apagado, un desplegable puede quedar bajo el contenedor siguiente.
- **Sticky:** depende del formato de `data-settings`. En los dispositivos donde el contenedor no se fija, «Sólo mientras está fijado» queda oculto.
- **Documento:** si un sitio fuerza `_element_cache = yes` en el widget «Imagen destacada», manda ese ajuste y la decisión queda en la caché de la plantilla.
- **Prioridad:** el aviso de conflictos del núcleo sigue apareciendo, y sin manuales el desempate de PHP 7.4 sigue como hoy.

## 15. Pruebas

- **Unitarias nuevas** (14), en `validate.yml` y `release.yml`:
  - presets: `tests/elements-design-{css,presets,presets-kit}.php` y `tests/elements-design-presets-editor.mjs`;
  - fondos: `tests/elements-design-{gradient,backgrounds}.php` y `tests/elements-design-gradient.mjs`;
  - condiciones y visitas: `tests/elements-conditions-more.php` y `tests/elements-page-visits.{php,mjs}`;
  - prioridad, sticky y documento: `tests/elements-tb-priority.php`, `tests/elements-sticky-state.php` y `tests/elements-document-controls.php`;
  - revisión estática: `tests/elements-design-static.mjs`.

  Las PHP usan el núcleo incluido y las clases reales de PRO donde se pueden cargar: `Stylesheet`, `Settings_Sanitizer`, `Svg_Sanitizer`, el grupo de fondo, `Locations_Manager`, `Conditions_Manager` y `Condition_Group`. La de degradados también usa el `addRules` del editor. Se juzgan por la salida completa (php-wasm sale con 0 aunque haya un fatal).
- **Ampliadas:** `tests/elements-front-settings.php` (la clave nueva en los cuatro puntos de paridad), `tests/elements-visitor-content.php`, `tests/elements-display-conditions.php` (recuentos), `tests/elements-kit-integration.php` (`glob` ampliado a `modules/digitalisimo-design/` y `modules/digitalisimo-conditions/`), `tests/elements-kit-inventory.mjs` (las `const KEY` de las partes de fuera de `kits/`), `tests/elements-front-budget.php` y `tests/validate-suite.mjs`.
- **Fixtures** en `tests/sources/design/`: `presets.json` (10 casos compartidos por PHP y JS), `gradients.json` (24 casos), `backgrounds-doubles.php` y `pro-pinned.json` (sha256 de `plugin.php` y de los CSS compilados de PRO). Los SVG de kit están en `tests/sources/kits/media/{mascara,tesela}.svg`.
- **Playground**, a mano antes de publicar y tras cada `sync-elementor-core.sh` (no en CI, como K-D10): `design.sh` DS1–DS13 (con `CACHE=1`, `CORE=plugin`, `V4=1` y `NET=1`) y las ampliaciones de `front-budget.sh`, `conditions.sh`, `kit-roundtrip.sh` y `brand-audit.sh SCOPE=full`. Resultados en `f7a-evidence.json`: todo en verde salvo el caso `editor` de `interaction.sh` y A13 de `kit-roundtrip.sh`, que fallan igual con 4.3.0.163 (entorno y tamaño del arnés), y A1 de la corrida final (captura del visor de PDF antes de pintar; la corrida anterior pasó).

## 16. Correcciones de la integración

- **`Store::replace( $data, $keep )`** volvía a sanear los presets que el sitio ya tenía, así que uno que hoy no pasa el saneado se perdía al importar en «agregar» o al revertir. Ahora los slugs de `$keep` se escriben tal cual, con su `hash` y su `updated`, después de pasar sólo la forma, el tope por preset y `safe()`. Nada sin sanear llega al CSS, porque `Compiler::validated()` los sanea antes de aplicarlos.
- **`Visitor_Content`** lee el prop v4 (§7.3).
- **El control del preset repinta la vista previa** con `render_type => 'ui'`.
- **Pestaña:** un preset inerte sale sin acciones.
- **Fixtures:** cada caso de `presets.json` lleva los ajustes ya saneados, y el JS compara ahora los 10 casos completos.
