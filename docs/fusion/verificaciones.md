# Verificaciones y críticas del plan de fusión

> Tres revisiones adversariales del 16 de octubre de 2026 sobre el borrador del plan: cobertura, riesgos y reglas, y verificación en código. Aquí queda, depurado, lo que se comprobó en el código y cómo lo resolvió el plan aprobado (`docs/FUSION_OBJECTIVE.md`). Rutas relativas al repositorio: `E/` = `digitalisimo-elements/elementor/` (núcleo, no se edita), `P/` = `digitalisimo-elements/` (derivado de PRO y propio), `MIG/` = `digitalisimo-migration/`.

## 1. Comprobado en el código

### Sobrescrituras por instancia en el widget Template

- `Template::render()` llama a `get_builder_content_for_display( $template_id )` (`P/modules/library/widgets/template.php:87-106`). Los únicos puntos de intervención, `elementor/document/load/data` y `elementor/frontend/builder_content_data` (`E/includes/frontend.php:1161,1173`), no reciben la instancia.
- **La caché de elementos mezcla instancias.** Está activa salvo `elementor_element_cache_ttl = 'disable'` (`E/core/base/document.php:1835`) y se guarda una vez por documento plantilla en `_elementor_element_cache` (`:1852-1889`): el texto de la primera instancia se serviría a todas durante 24 h (`:1964-1979`).
- **Los IDs cambian al reimportar.** `Source_Local::save_item()` regenera los IDs (`E/includes/template-library/sources/local.php:521-522`).
- **Resolución (F7b):** sólo si la instancia tiene sobrescrituras, envolver la llamada con `add_filter( 'elementor/frontend/builder_content_data', … )` (patrón de `P/modules/library/wp-widgets/elementor-library.php:44-49`), apagar la caché de elementos **sólo dentro** de ese envoltorio y retirar ambos filtros al terminar. Cada sobrescritura se identifica con una clave estable en el elemento de la plantilla (`_digitalisimo_slot`), no con su ID. Las imágenes de fondo quedan fuera: van por CSS compartido.

### Interacciones

- **Sólo con v4.** El módulo PRO sale si `e_atomic_elements` no está activo (`P/modules/interactions/module.php:41,48-51`) y el del núcleo ni registra sus ganchos (`E/modules/interactions/module.php:52-63`).
- **Los clásicos pierden la clave.** `Element_Base::get_raw_data()` sólo guarda `id`, `elType`, `settings`, `elements` e `isInner` (`E/includes/base/element-base.php:651-673`); el JS busca `[data-interaction-id]` (`P/assets/js/interactions-pro.js:577`), que sólo imprimen los atómicos.
- **El validador borra acciones sin animación.** Corre en `elementor/document/save/data` y descarta todo ítem sin `animation` válida; máximo 5 por elemento (`E/modules/interactions/validation.php:16,123-157`).
- **Habilitar las PRO:** basta quitar la salida de `P/modules/interactions/module.php:23-25`. No hace falta tocar la licencia (`should_allow_all_features()` ya devuelve verdadero, `P/license/api.php:207-209`). **No** se toca `get_license_key()` (`P/license/admin.php:33-35`): esa clave viaja a la Kit Library remota.
- **Efecto en páginas existentes:** el núcleo ya acepta guardar `hover`, `click` y `scrollOn`, pero su JS sólo ejecuta `load`, `scrollIn` y `scrollOut`. Al habilitar PRO, el contenido con esos disparadores empezaría a animarse.
- **Resolución:** antes de habilitar, inventariar la meta `elementor-interactions-cache` y añadir el caso a `interaction.sh`. Las acciones con destino van a un registro propio (`_digitalisimo_actions`) con su cargador, que en v4 se reinyecta después del validador (F7b).

### Presets vinculados

- No hay filtro de ajustes por elemento (`E/includes/managers/elements.php:79-99`), el CSS por documento lee los datos sin filtro (`E/core/files/css/post.php:160-187`) y `elementor/document/load/data` también alimenta el editor (fusionar ahí guardaría el preset como valor local).
- **Diseño A** (fusión en tiempo de ejecución): posible con `elementor/element/before_parse_css`, `elementor/frontend/widget/before_render`, `elementor/widget/before_render_content` y JS de editor propio. Costoso.
- **Diseño B (elegido, sólo estilo):** compilar cada preset en el CSS del kit con `elementor/css-file/post/parse` (`E/core/files/css/base.php:745`) y `add_controls_stack_style_rules()` sobre una instancia de muestra, con selector `.elementor-kit-N .elementor-widget-{tipo}.digi-preset-{slug}`. Especificidad (0,2,0) frente a lo local (0,3,0) (`post.php:101-103`). La instancia sólo lleva una clase por `prefix_class`.
- **Invalidación:** `files_manager->clear_cache()` borra también `_elementor_element_cache`; `delete_included_posts_css()` de Global Widget no basta.
- Hay que definir cómo convive con «Guardar como predeterminado» (`FillDefaultsOnDrop` copia valores locales que taparían el preset).

### Importación de kits y plantillas

- **Kit nuevo.** Con `settings`, el runner crea y activa un kit nuevo (`E/app/modules/import-export-customization/runners/import/site-settings.php:146-148`) y `array_replace_recursive` reemplaza por índice colores y tipografías del sistema y el Theme Style. Del kit anterior sólo se copian las metas de `elementor/kit/meta_to_preserve_on_kit_import`; `_elementor_elements_default_values` no está.
- **Tema, experimentos y plugins.** El runner de site-settings instala desde wordpress.org y llama a `switch_theme` (`:158-171,272-279,346-383`); el de plugins los instala.
- **Disponibilidad.** El componente sólo existe con `manage_options` en `init` o en WP-CLI (`E/app/app.php:321-326`). Si se instancia `Import` a mano, hay que disparar `elementor/import-export-customization/import-kit` para que PRO registre sus runners. `import_kit()` quita el saneador de contenido y la reversión sólo deshace la última sesión.
- **Plantillas sueltas.** `prepare_import_template_data()` rechaza los JSON sin `content` antes de cualquier filtro (`E/includes/template-library/sources/base.php:465-475`); `direct_import_template()` fija `'local'` y el editor no envía `source`. Un `Source` propio no debe extender `Source_Local` (su constructor vuelve a registrar ganchos).
- **Componentes.** `IS_IMPORT_EXPORT_SUPPORTED = false` es una constante sin filtro (`E/modules/components/module.php:48`): al importar se eliminan las instancias `e-component`.
- **Resolución (F2):** kit 3.0 sin `theme`, `experiments` ni `plugins`; filtro `site-settings/customization` en modo «sólo agregar» (sistema y Theme Style sólo se reemplazan en sitios sin contenido previo); `_digitalisimo_presets` y `_elementor_elements_default_values` preservados; punto de entrada propio que llama a `import_template(['source'=>'digitalisimo-import'])` y delega en `get_source('local')->save_item()`; componentes creados con `Components_Repository` después y sus instancias reinsertadas con el mapa de IDs.

### Variante anidada «con el mismo ID»

- `support_nesting` se decide por tipo de widget, no por instancia (`E/modules/nested-elements/base/widget-nested-base.php:84-95`); un documento guardado sin hijos falla con «Child container was not found».
- Migración extiende Acordeón, Fancy Slider y Fancy Tabs por nombre (`MIG/legacy/class-adapters.php:342,399,402`) y el núcleo ya trae nested-tabs, nested-accordion y nested-carousel.
- **Resolución:** nunca se cambia la clase de un widget existente. Slider anidado nuevo; pestañas y acordeones a los anidados del núcleo con la presentación DIGITALÍSIMO añadida por hooks; «Migrar» convierte.

### Global Widget

- La instancia toma entero los ajustes de la plantilla (`P/modules/global-widget/widgets/global-widget.php:73-75`), el CSS va por `.elementor-global-{id}` y el editor está compilado sin fuentes. El índice de usos sólo se actualiza en `elementor/editor/after_save`.
- **Resolución:** Global Widget sólo para módulos globales de un solo widget; índices de usos en `elementor/document/after_save`.

### Tema y Theme Builder

- `register_location()` acepta `hook` y `remove_hooks` (`P/modules/theme-builder/classes/locations-manager.php:546-566`); la ubicación corre con prioridad 5 y `remove_hooks` quita en 10, así que los callbacks por defecto de `digitalisimo_*` van en prioridad 10.
- Si el tema no registra cabecera o pie, `Theme_Support` engancha `get_header`/`get_footer` (`P/modules/theme-builder/classes/theme-support.php:36-88`): **una plantilla del Theme Builder publicada reemplaza la cabecera de cualquier tema**. Por eso las convertidas nunca se publican antes del cambio de tema.
- Escribir `_elementor_conditions` con `update_post_meta` no regenera la caché `elementor_pro_theme_builder_conditions`: hay que llamar a `save_conditions()`.

### Publicación

- `.gitignore` ignoraba cualquier `functions.php` y `style.css`: el tema no entraría en Git (corrección en F0).
- `scripts/release-manifest.mjs` aceptaría el ZIP del tema como plugin y `scripts/verify-release.mjs` exige exactamente siete; `scripts/build-packages.sh` lee ` * Version:`; `tests/validate-suite.mjs` exige `Update URI`. Listas fijas en `release.yml`, `validate.yml`, `scripts/changed-modules.sh`, `docs/RELEASE_PROCESS.md` y AGENTS.md («siete»). El catálogo de Tools usaría `Plugin_Upgrader` (corrección en F1: clave `themes` y `Theme_Upgrader`).
- Los actualizadores de Elements, Migración y `digitalisimo-sibling-updates.php` devuelven `true` en `auto_update_plugin`: cada publicación llega sola a los sitios. De ahí la regla «todo cambio de comportamiento es opt-in por instancia» y `state['v']` en el runner. El tema ofrece sus versiones sin forzar la actualización.

### Condiciones, caché y permisos

- `class-visitor-content.php` ya tiene condiciones por cookie (`visit_count`, `session_count`) y una lista fija `THEME_LOCATIONS` (`header, footer, single, archive, popup`): lo condicionado en ubicaciones nuevas quedaría en caché. `THEME_LOCATIONS` debe ampliarse por filtro.
- Elementor guarda una copia en texto plano en `post_content`: un texto oculto por condición sigue en feeds, REST y búsqueda. Ninguna condición se presenta como protección.
- El Role Manager sólo ajusta la interfaz («editar sólo contenido»); no hay verificación al guardar. Los permisos nuevos se aplican en el servidor.

### Migración

- `MIG/legacy/class-native.php:328-334` descarta sin aviso las claves de 28 extensiones de Element Pack, y el Addon (adonde el changelog de 10.0 dice que pasaron 85 widgets; faltan 88 IDs en 10.0.1) no se retira (F0.1 y F8).
- La etapa `deactivate` va antes de `convert` (excepción documentada para Element Pack); el contador «paso %d de %d» usa `count(STAGES)` y la red compara índices: mostrar sólo las etapas de los drivers detectados.
- SEO sólo imprime la meta description en contenidos individuales y no vacíos (`digitalisimo-seo/includes/class-seo.php:71`): la de Hello se retira sólo donde SEO imprime la suya.

### Licencias

- Divi: `Divi/style.css` dice «GNU General Public License v2» y `Divi/LICENSE.md` «version 2 … or (at your option) any later version». Se registra la discrepancia y se toma `LICENSE.md` como vigente.

## 2. Preguntas que se hicieron y cómo se respondieron

| Pregunta | Respuesta (plan aprobado) |
|---|---|
| ¿Quién activa el tema en producción? | «Migrar» deja todo en borrador; el administrador activa el tema tras la vista previa y la tarea termina sola. Fuera de producción el clic hace todo. |
| ¿Muestras reales en Git? | No: quedan fuera de Git; al repositorio sólo llegan fixtures sintéticos o anonimizados. |
| ¿Variante anidada con el mismo ID? | No: widget nuevo o anidados del núcleo. |
| ¿Presets de estilo o también de contenido? | Sólo estilo, compilados en el CSS del kit. |
| ¿Activar v4 para tener interacciones? | No: destino clásico (v3); v4 sólo donde ya esté activo. Acciones con destino en un registro propio. |
| ¿Publicación de cada incremento? | Cada etapa en una rama `fusion/<n>`, publicada en cuanto queda validada («siempre publica», 2026-10-16). |
| ¿Tema hijo único por red o por sitio? | Ninguno: el código PHP propio va al editor de fragmentos de DIGITALÍSIMO Tools, por sitio o por red. |
| ¿Páginas de GP con bloques? | Siguen en el editor de bloques; GenerateBlocks se convierte si las muestras y su copia oficial lo permiten. |
