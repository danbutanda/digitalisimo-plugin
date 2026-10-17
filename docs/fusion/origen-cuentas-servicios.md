# Inventario: cuentas, inicio de sesión y servicios remotos del proveedor dentro de DIGITALÍSIMO Elements

> **Estado: resuelto en F0.2** (rama `fusion/f0-2-sin-referencias`, commit «Remove every link, video, account and request toward other companies»). La guarda vive en `digitalisimo-elements/digitalisimo-vendor-free.php` y `assets/digitalisimo/vendor-free.js`; las pruebas son `tests/elements-vendor-guard.php`, `tests/elements-vendor-static.mjs`, `tests/elements-identity.php` y `tests/playground/brand-audit.sh` ampliado (ver `tests/playground/README.md`). Este inventario, del 16 de octubre de 2026 y depurado para el repositorio público, queda como referencia de qué se retiró y por qué; las líneas citadas son de antes del cambio. Sirve también para repetir la revisión en cada `sync-elementor-core.sh`.

Revisé el código sin modificar ni ejecutar nada. Rutas:
- `[E]` = `digitalisimo-elements/elementor/` (núcleo, no se edita)
- `[P]` = `digitalisimo-elements/` (código propio y lo derivado de PRO, editable)

## 0. Conflicto con la política documentada

- `[P]../docs/ELEMENTOR_CORE.md:28` dice que la biblioteca de kits y de plantillas, la conexión de cuenta y la IA del editor siguen funcionando con los servicios del proveedor. `[P]digitalisimo-brand.php:73` (página «Acerca de») lo repite: «pueden requerir una cuenta del proveedor».
- El comentario de `[P]digitalisimo-identity.php:27` trata la IA como un servicio externo que se conserva.
- El pedido del usuario contradice esa política. Hay que actualizar esos tres puntos.

**Lo que la identidad actual ya cubre** (`[P]digitalisimo-identity.php:57-71`, `[P]assets/digitalisimo/identity.css`):
- textos y logotipos;
- `generator`;
- notificaciones y anuncios, que se vacían después de pedirlos;
- noticias del escritorio, que se ocultan aunque la petición se hace igual;
- inicio rápido, sin vídeo ni promociones (la petición se hace igual);
- onboarding marcado como completado;
- menú «Upgrade» de Editor One;
- pie del widget del escritorio;
- etiqueta de la biblioteca en «Información del sistema».

`[P]digitalisimo-core.php:74-77` además bloquea la reversión de versiones y el canal beta.

**Lo que no cubre nada:** Connect, biblioteca remota, Kit Library, nube, IA, Angie, telemetría, la barra superior de Editor One, Site Planner y las migraciones remotas.

---

## A. Cuentas e inicio de sesión (Connect)

### A1. Motor Connect y página oculta `page=elementor-connect`
1. **Qué ve el usuario.** Ningún menú propio. Todos los botones «Conectar», «Empezar» y «Crear cuenta» del editor, de la App y de la IA llevan a `admin.php?page=elementor-connect&app=X&action=authorize`. Esa página redirige a `my.elementor.com/connect/v1/{app}`, donde está el inicio de sesión del proveedor.
2. **Origen.**
   - `[E]core/common/modules/connect/module.php:76-80`: apps connect, library y feedback. `:120`: `do_action('elementor/connect/apps/register')`. `:197-233`: planes con URLs `my.elementor.com/upgrade-subscription`.
   - `[E]core/common/modules/connect/admin.php:17,133-139`.
   - `[E]core/common/modules/connect/apps/base-app.php`: `:23,25` (URLs), `:561-602` (URL de autorización), `:453-472` (`http_request`, que hace `wp_die()` si recibe `WP_Error`), `:509-515` (un 401 borra datos y relanza la autorización).
   - Apps registradas en otros lados: `[E]app/modules/kit-library/module.php:110`, `[E]modules/ai/module.php:49`, `[E]modules/cloud-library/module.php:66`, `[E]modules/cloud-kit-library/module.php:26`, `[P]core/connect/manager.php:16` (activate).
3. **Identidad.** No lo cubre.
4. **Cómo retirarlo.**
   - En `admin_init` con prioridad 0: si `page=elementor-connect`, `wp_safe_redirect()` a `digitalisimo-elements-about` y `exit`. Corre antes del `load-` de Editor One (`[E]vendor/elementor/wp-one-package/src/Admin/Services/Licenses.php:61`).
   - Sustituir cada app por una subclase «nula» con `add_action('elementor/connect/apps/register', fn($m)=>$m->register_app('library', Own_Null_Library::class), 100)`; igual para `kit-library`, `cloud-library`, `cloud-kits`, `ai` y `feedback`. La subclase sobreescribe `http_request()` (sin red: `[]` o `WP_Error` sin `wp_die`), `is_connected()` (siempre `false`) y `get_admin_url()` (página propia).
   - Mantengan el componente `connect`. Muchos llamadores no comprueban que exista: `[E]app/modules/kit-library/module.php:52` llama a `->is_connected()` sobre la app, y `[E]core/editor/loader/editor-scripts-settings.php:35` lee `get_app('activate') ?? get_app('library')`. Retirarlo entero provoca errores fatales.
   - La app `activate` de PRO es editable directamente: `[P]core/connect/apps/activate.php`.
5. **Qué se pierde.** El enlace con la cuenta del proveedor. No hace falta un equivalente propio.

### A2. Menú del logotipo del editor: «Conectar mi cuenta» o «Mi Elementor»
1. **Qué ve el usuario (editor).** Con PRO cargado, `elementorPro.config.isActive` es `true` porque la licencia es un stub (`[P]license/api.php:104-141`). Por eso el menú muestra «Mi Elementor», que la identidad reescribe como «Mi DIGITALÍSIMO Elements», y abre `https://go.elementor.com/wp-dash-top-bar-account/`. Sin PRO mostraría «Conectar mi cuenta» con la ventana de inicio de sesión.
2. **Origen.**
   - `[E]assets/js/packages/editor-app-bar/editor-app-bar.js:836-886`: elemento `app-bar-connect` del grupo `exits`.
   - `[E]core/editor/loader/editor-scripts-settings.php:35,79-89`: `user.top_bar.connect_url` y `my_elementor_url`.
3. **Identidad.** Sólo cambia el nombre: el enlace sigue llevando al proveedor.
4. **Cómo retirarlo.** Script en línea después de `elementor-v2-editor-app-bar` (los menús admiten `overwrite`, `[E]assets/js/packages/menus/menus.js:51-67`):
   `elementorV2.editorAppBar.mainMenu.registerLink({id:'app-bar-connect',group:'exits',priority:10,overwrite:true,useProps:()=>({visible:false,title:'',icon:()=>null})})`
   Como respaldo, vaciar `user.top_bar.*` con `elementor/editor/localize_settings` a prioridad 20.
5. **Qué se pierde.** Nada funcional. Equivalente posible: un enlace a la página «Acerca de».

### A3. «Centro de ayuda», «Enviar comentarios» y Angie en el mismo menú
1. **Qué ve el usuario (editor).**
   - «Centro de ayuda» abre `go.elementor.com/editor-top-bar-learn/`, o Angie si está instalado.
   - «Enviar comentarios» sólo aparece con el experimento oculto `in_editor_feedback` activo. Exige estar conectado y muestra «Conectar con Elementor».
2. **Origen.**
   - `editor-app-bar.js:1322-1362` (`open-help-center`, grupo `help`).
   - `editor-app-bar.js:1270-1290` y `:582-700` (ventana de comentarios).
   - `[E]core/common/modules/connect/apps/feedback.php:15,19-31`; ruta REST `[E]modules/feedback/module.php:28`.
3. **Identidad.** No lo cubre.
4. **Cómo retirarlo.** El mismo `overwrite` con `visible:false`, o redirigir la ayuda a documentación propia. Forzar `pre_option_elementor_experiment-in_editor_feedback` a `'inactive'`.
5. **Qué se pierde.** El canal de comentarios hacia el proveedor. Sin equivalente propio.

### A4. Barra superior de Editor One en la administración (`#editor-one-top-bar`)
1. **Qué ve el usuario (administración, pantallas del constructor):**
   - menú «mi cuenta» con «¿Tienes un plan Elementor One?», «Conectar», «Ir a mi cuenta» (`my.elementor.com`), «Desconectar» y «Elementor Uno»;
   - «Qué hay de nuevo»;
   - «Comparta sus comentarios» y el formulario de soporte (`my.elementor.com/support-form/`);
   - ayuda (`go.elementor.com/one-help-center*`).
2. **Origen.**
   - `[E]modules/editor-one/components/top-bar-handler.php:25-26,29-72`.
   - `[E]assets/js/editor-one-top-bar.js:15336-15339` (URLs) y `:33825-33840` (menú de usuario).
   - Textos en `[E]assets/js/locales/elementor-one-assets/es/common.json`, que la barra carga con su propio i18n y no con `wp.i18n`.
   - `editor-one-top-bar.js:33244`: arranca Mixpanel en el navegador con un token fijo cuando `shareUsageData` es verdadero.
3. **Identidad.** Sólo cambia el logotipo (`identity.css:45-52`). Los textos «Elementor One» y «Elementor Uno» salen sin cambiar, porque las reglas `i18n.*` no los alcanzan.
4. **Cómo retirarlo.** En `elementor/init`:
   - obtener el manejador: `$m = Plugin::$instance->modules_manager->get_modules('editor-one'); $h = $m ? $m->get_component('top-bar-handler') : null;`
   - `remove_action('admin_enqueue_scripts', [$h,'enqueue_assets'])` y `remove_action('in_admin_header', [$h,'render_top_bar_container'])`.
   - Si se prefiere conservar la barra, ocultar con CSS el bloque derecho (MUI, frágil).
5. **Qué se pierde.** El título «website builder» y los accesos de cuenta, novedades y ayuda. Equivalente: el encabezado de `digitalisimo-ui`.

### A5. Paquete Editor One en el servidor (`vendor/elementor/wp-one-package`)
1. **Qué ve o qué pasa:**
   - Google Fonts (Roboto) y `common.js` en todas las pantallas de administración.
   - El menú de nivel superior `elementor-home` lo crea este paquete (`src/Admin/Components/Page.php:77-129`). Los submenús del núcleo cuelgan de él: `[E]core/admin/editor-one-menu/elementor-one-menu-manager.php:67-109`.
   - Widget «Site Management» en el escritorio, sólo si está instalado el plugin «manage».
   - Revisión de licencia cada hora, sólo si está conectado.
   - Sincronización de licencias al cargar `elementor-connect`.
   - Ruta REST `elementor-one/v1/*`: notificaciones vía `my.elementor.com/api/v1/notifications` (`[E]vendor/elementor/wp-notifications-package/src/V120/Notifications.php:22,33,188`) y comentarios (`src/Admin/Controllers/TopBar.php:99-140`).
2. **Origen.** `[E]vendor/elementor/wp-one-package/runner.php:38-53`, `src/Admin/Config.php:16-17`, `src/Admin/Components/Assets.php:48-66,123-151`, `src/Connect/Components/License.php:44-52`.
3. **Identidad.** Sólo `elementor_one/upgrade_available` en `false`.
4. **Cómo retirarlo.**
   - **No desactiven el paquete:** sin `add_menu_page('elementor-home')` se quedan huérfanos todos los submenús.
   - `add_filter('elementor_one/elementor_one_license_check_enabled','__return_false')`.
   - `wp_dequeue_style('elementor-one-admin-fonts')` en `admin_enqueue_scripts` con prioridad 100.
   - La redirección de `elementor-connect` (A1).
   - Quitar `/elementor-one/v1/*` con el filtro `rest_endpoints`.
5. **Qué se pierde.** Nada propio.

### A6. Site Planner: contraseña de aplicación para `planner.elementor.com`
1. **Qué pasa.** Una página oculta, `admin.php?page=e-site-planner-password-generator`, crea una contraseña de aplicación y la envía por `postMessage` a `https://planner.elementor.com`. Carga Google Fonts. Se registra **aunque la IA esté apagada**.
2. **Origen.** `[E]modules/ai/module.php:38`; `[E]modules/ai/site-planner-connect/module.php:11,14-55`; `view.php:9-12,167-187`; REST `elementor-ai/v1/permissions` en `wp-rest-api.php:15`.
3. **Identidad.** No lo cubre.
4. **Cómo retirarlo.** `add_action('load-admin_page_e-site-planner-password-generator', fn()=>wp_die('…'))` y quitar `/elementor-ai/v1/permissions` con `rest_endpoints`. Además `[E]app/modules/onboarding/module.php:139` enlaza a `planner.elementor.com/onboarding.html`; el onboarding ya está marcado como completado.
5. **Qué se pierde.** Nada propio. Además reduce la superficie de ataque.

### A7. Conexión por REST y WP-CLI
- `POST/DELETE elementor/v1/library/connect`: `[E]core/common/modules/connect/rest/rest-api.php`.
- `wp elementor library connect|disconnect|sync`: `[E]modules/wp-cli/library.php:40,218,256`.
- **Cómo retirarlo.** Filtro `rest_endpoints`. Para la CLI basta la app nula de A1.

---

## B. Biblioteca de plantillas remota y en la nube (editor)

### B1. Pestañas «Bloques» y «Páginas» con la pantalla «Conectar con la biblioteca»
1. **Qué ve el usuario (editor, modal de plantillas).**
   - Abre por defecto en `templates/blocks`.
   - Al insertar una plantilla remota sin conexión aparece «Connect to Template Library… Get Started», que lleva al inicio de sesión.
2. **Origen.**
   - JS: `[E]assets/js/editor.js:45006-45035` (`defaultTabs`), `:45090` (`setDefaultRoute`), ruta `connect`.
   - `[E]core/base/document.php:1807-1813` (`default_route = templates/blocks`).
   - `[E]includes/editor-templates/templates.php:740-769` (`tmpl-…-connect`).
   - `[E]includes/template-library/sources/remote.php:22,252-275`.
3. **Identidad.** No lo cubre.
4. **Cómo retirarlo.**
   - `add_filter('elementor/document/config', fn($c)=>array_replace_recursive((array)$c, ['remoteLibrary'=>['default_route'=>'templates/my-templates']]))`. El filtro está en `document.php:774`.
   - En JS, al iniciar el editor: `$e.components.get('library').removeTab('templates/blocks')` y lo mismo con `templates/pages`.
   - Cuidado: al cerrar un documento de botones flotantes el núcleo vuelve a añadir esas pestañas (`editor.js:37928-37929`). Conviene reforzar con CSS sobre `[data-tab="templates/blocks"]` y `[data-tab="templates/pages"]`.
   - En `elementor/init`, con prioridad mayor a 10: `Plugin::$instance->templates_manager->unregister_source('remote')`.
5. **Qué se pierde.** El catálogo de bloques y páginas del proveedor. También las plantillas remotas de Botones flotantes y Link in Bio, que usan `source:'remote'` (`editor.js:37860-37873`). **Equivalente:**
   - multisitio: «Plantillas de red» de DIGITALÍSIMO Tools (`digitalisimo-tools/modules/elementor-network-templates/`), que copia `elementor_library` del sitio maestro a «Mis plantillas»;
   - sitio único: importar `.json` o `.zip` locales.

### B2. Datos de la biblioteca (`templates/info`): riesgo de dejar vacío «Mis plantillas»
- **Petición:** servidor → `GET https://my.elementor.com/api/v1/templates/info/` cada vez que se abre el modal; sin caché de lectura previa. Guarda la opción `elementor_remote_info_library`.
  - Origen: `[E]includes/api.php:56,196-243`.
- **Si se bloquea con `WP_Error`:** `Manager::get_library_data()` devuelve `[]` cuando falla esa petición (`[E]includes/template-library/manager.php:227-231`). El JS crea entonces una colección vacía (`editor.js:46597-46612`) y **las plantillas locales tampoco aparecen**.
- **Cómo hacerlo bien:** en `pre_http_request`, responder 200 con cuerpo `{"types_data":{}}`. `getConfig()` devuelve `{}` cuando falta un tipo (`editor.js:46593-46595`).

### B3. Plantillas en la nube (núcleo y PRO)
1. **Qué ve el usuario (editor):**
   - selector «Plantillas del sitio / Plantillas en la nube» con insignia «Pro»;
   - insignias «Mi cuenta de Elementor», «Conectar», «Connect account» y «Conectar y activar» (PRO);
   - promociones `go.elementor.com/go-pro-cloud-templates-*` en el diálogo «Guardar como plantilla».
2. **Origen.**
   - `[E]includes/editor-templates/templates.php:127-152,546-565,642-665,782-800`.
   - `[E]modules/cloud-library/module.php:91-114`.
   - `[P]modules/cloud-library/module.php:35-51` (editable).
   - App: `[E]modules/cloud-library/connect/cloud-library.php:19` (`cloud-library.prod.builder.elementor.red`).
   - `editor.js:43426-43461` (cuota) y `:43990-44001` (ventana de conexión).
3. **Petición:** servidor → `GET …/cloud-library/resources` **en cada `get_library_data`**, porque se recorren todas las fuentes (`manager.php:198-211`), y `quota` al abrir «Guardar».
4. **Si se bloquea con `WP_Error` sin más:** `Base_App::http_request()` hace `wp_die()` y la AJAX `get_library_data` se rompe entera.
5. **Cómo retirarlo.**
   - `unregister_source('cloud')`.
   - App `cloud-library` nula (A1).
   - CSS: ocultar `.source-option[data-source="cloud"]`, `.source-selections-input.cloud`, `.connect-badge`, `.cloud-badge` y `.account-badge`.
   - En PRO, borrar `set_cloud_library_settings()` (`[P]modules/cloud-library/module.php:26-51`).
6. **Qué se pierde.** La nube del proveedor entre sitios. Equivalente: Plantillas de red de Tools en multisitio; exportar e importar JSON en sitio único.

### B4. «Sincronizar biblioteca» y fila de «Información del sistema»
- **Sincronizar biblioteca:** botón en Herramientas (`[E]includes/settings/tools.php:321-328`) que llama a AJAX `elementor_reset_library` → `get_info_data(true)`.
- **Información del sistema:** la fila «Template Library» hace `GET my.elementor.com/api/v2/info/` **en cada carga, sin caché** (`[E]modules/system-info/reporters/server.php:62,371-400`). Bloqueada, mostraría «Not connected» como advertencia.
- **Cómo retirarlo.** Respuesta sintética 200 (ver H1). El botón se oculta por CSS o con el búfer de salida que ya existe en `identity.php:318-325`.

---

## C. App: Kit Library y kits en la nube

### C1. «Plantillas de sitio web» (`#/kit-library`): principal origen de los «login»
1. **Qué ve el usuario (App de kits):**
   - submenú «Website Templates»;
   - catálogo remoto con miniaturas;
   - al aplicar cualquier kit, una ventana «Conectar» con «Create Account» y «Already have an account?» que lleva a `my.elementor.com`.
2. **Por qué siempre pide conectar.** PRO fija `is_library_connected => false` (`[P]core/app/modules/kit-library/module.php:46-52`, editable) y la App devuelve `TYPE_CONNECT` (`[E]assets/js/app.js:22463`).
3. **Origen.**
   - `[E]app/modules/kit-library/connect/kit-library.php:12-13` (`my.elementor.com/api/v1/kits-library` y `ms-8874.elementor.com`).
   - `[E]app/modules/kit-library/module.php:48-67,101-112`.
   - `[E]app/modules/kit-library/admin-menu-items/editor-one-website-templates-menu.php:28,35-49`.
   - Entrada del buscador Finder: `[E]core/common/modules/finder/categories/general.php:77-80`.
4. **Petición:** servidor, desde REST `elementor/v1/kits*`, al abrir la App. Guarda los transitorios `elementor_remote_kits` y `elementor_remote_kits_taxonomies` (12 h; `[E]app/modules/kit-library/data/repository.php:190-245`).
5. **Si se bloquea con `WP_Error`:** `wp_die()` (A1). Con 403 la App muestra un error.
6. **Cómo retirarlo.**
   - Excluir el submenú con `elementor/editor-one/menu/excluded_level4_slugs`. El slug lleva un `return_to` dinámico: hay que comprobar el normalizador (`menu-data-provider.php:538-544`). Como alternativa, CSS.
   - En la App (`elementor/app/init`), script en línea que cambie `#/kit-library*` por `#/import` y escuche `hashchange`.
   - App `kit-library` nula.
   - Quitar `kit-library` con `elementor/finder/categories`.
   - En PRO, quitar la URL de Activate.
7. **Qué se pierde.** El catálogo de kits del proveedor. **Equivalente:** importar kits en ZIP (`#/import`), que funciona sin cuenta.

### C2. Kits en la nube (exportar a la nube e importar desde ella)
1. **Qué ve el usuario (App):** «Save to library», «Your library is full» y «Connect your account».
2. **Origen.** `[E]modules/cloud-kit-library/module.php:26-34`; `connect/cloud-kits.php:26`. La App lo enlaza en `app.js:14002-14031,17640-17694`.
3. **Cómo retirarlo.** App `cloud-kits` nula y CSS u ocultación de la opción de nube en el paso de exportación.
4. **Qué se pierde.** La nube de kits del proveedor. Equivalente: exportar el ZIP en local.

### C3. Onboarding de la App
- **Peticiones:**
  - `assets.elementor.com/ab-testing/v1/ab-testing.json` en **cada carga de la App**, porque se ejecuta si `app->is_current()` (`[E]app/modules/onboarding/module.php:77-83,529-537`; opción `_elementor_ab_testing_data`, 1 h);
  - textos de `onboarding-new` (`[E]app/modules/onboarding-new/module.php:22,68`) y Google Fonts.
- **Identidad.** `pre_option_elementor_onboarded` no impide la petición de A/B.
- **Cómo retirarlo.** Escudo remoto (sección K).

---

## D. IA de Elementor

### D1. Módulo IA: editor, medios, Gutenberg, WooCommerce y controles
1. **Qué ve el usuario:**
   - editor: botones de IA en textos, código, CSS e imágenes; botones «Generar con IA» y «variaciones IA» de la biblioteca; controles de efecto y animación con IA;
   - biblioteca de medios: «Editar con IA»;
   - Gutenberg: extracto e imagen destacada;
   - productos WooCommerce: imágenes con IA;
   - después de importar un kit.

   La primera vez pide conectar en `my.elementor.com/connect/v1/ai`.
2. **Origen.**
   - `[E]modules/ai/module.php:35-165` (todo depende de `is_ai_enabled()` en `:45`), `:126-155` (Gutenberg), `:113-123` (medios y Woo), `:161-164` (controles).
   - `[E]modules/ai/connect/ai.php:14` (`my.elementor.com/api/v2/ai/`).
3. **Petición:** servidor, por AJAX `ai_*`, en cada acción del usuario.
4. **Identidad.** Sólo cambia el nombre a «IA».
5. **Cómo retirarlo.** `add_filter('get_user_option_elementor_enable_ai', fn()=>'0')`:
   - `Preferences::is_ai_enabled()` lo lee con `get_user_option()` (`[E]modules/ai/preferences.php:41-43`, `[E]includes/user.php:378-382`);
   - el módulo no registra entonces la app, ni AJAX, ni scripts, ni controles;
   - hay que añadirlo antes de `init`, cuando se construyen los módulos. `pro-elements.php` se incluye antes, así que sirve.
6. **Qué se pierde.** Toda la IA dentro del constructor. **Equivalente parcial:** DIGITALÍSIMO IA Tools (`digitalisimo.chatbot/digitalisimo-chatbot.php`) cubre contenido e imágenes en sus propias pantallas, **no dentro de los controles del editor**.

### D2. Perfil de usuario: «DIGITALÍSIMO Elements - AI / Activar IA»
- **Origen.** `[E]modules/ai/preferences.php:19-31,51-79`, registrado con clausuras anónimas.
- **Cómo retirarlo.** CSS `tr:has(#elementor_enable_ai)` más la fila de encabezado anterior, o buscar en `$wp_filter['personal_options']` la clausura cuyo `$this` sea `Preferences` (Reflection).

### D3. Código personalizado (PRO): botón de IA
- **Origen.** `[P]modules/custom-code/module.php:344` depende de `elementor-ai-admin`; `[P]assets/js/custom-code.js` tiene `addOpenAIButton`.
- **Cómo retirarlo.** Es editable: quitar la dependencia y el botón. Con D1 activo, el botón llamaría a AJAX inexistente.

---

## E. Angie y MCP

### E1. Angie: botón, tarjeta e instalación
1. **Qué ve el usuario (editor):**
   - botón «Angie» en la barra;
   - tarjeta «Generar páginas completas con IA / Crear con Angie» (`editor-app-bar.js:394-520,786-819`, `toggle-angie`);
   - en el panel de elementos, «Crea un widget con Angie… Pruébalo gratis» (`[E]includes/editor-templates/panel-elements.php:104-123`);
   - ventana «Instalar y activar» Angie desde wordpress.org, con términos en `elementor.com` (`[E]assets/js/packages/editor-widget-creation/editor-widget-creation.js:70-185`).
   - Si Angie está instalado, `editor-mcp` abre un iframe de `angie.elementor.com` (navegador).
2. **Origen adicional.** `[E]modules/widget-creation/module.php:18-25,47,56-62`; `angie-promotion.php:19`.
3. **Cómo retirarlo.**
   - `add_filter('elementor/editor/v2/packages', …)` para quitar `editor-widget-creation` (`[E]core/editor/loader/editor-loader.php:263`).
   - `toolsMenu.registerToggleAction({id:'toggle-angie', overwrite:true, useProps:()=>({visible:false…})})`.
   - Borrar por JS los `<script type="text/template">` `#tmpl-elementor-panel-elements-widget-creation-*`: `hasTemplate()` (`editor.js:52179-52181`) devuelve entonces `false` y no se pinta nada.
   - Quitar `elementor/v1/angie/consent` con `rest_endpoints`.
4. **Qué se pierde.** Promociones. Si el usuario instala Angie por su cuenta, el iframe es responsabilidad de ese plugin.

### E2. Página MCP
- **Qué es.** Un servidor MCP local; no hace peticiones al proveedor. La identidad ya renombra su contenido (`identity.php:303-315`).
- **Qué queda.** El enlace promocional `go.elementor.com/go-pro-mcp-connector-page-upgrade/` (`[E]modules/mcp/module.php:28`). Se conserva la función y se oculta la promoción.

---

## F. Productos del proveedor con aviso o instalación

### F1. Image Optimizer
- **Dónde aparece:** sugerencias en controles de imagen y galería y en la biblioteca de medios; aviso en `upload.php`.
  - Origen: `[E]core/utils/hints.php:54-92`; `[E]includes/controls/media.php:338-380`; `[E]includes/controls/gallery.php:144-175`; `[E]core/admin/admin.php:1122-1195` (`media-hints`); `[E]core/admin/admin-notices.php:567`.
- **Cómo retirarlo.** Filtro `get_user_metadata` para:
  - `elementor_dismissed_editor_notices`: añadir `image-optimization-once`, `image-optimization-once-media-modal`, `image_optimizer_hint`, `image-optimization-media-modal` y `ally_heading_notice`;
  - `elementor_admin_notices`: marcar como vistos `plugin_image_optimization` y `ally_pages_promotion`.

### F2. Ally
- **Dónde aparece:** `[E]includes/widgets/heading.php:492-560` y `admin-notices.php:419`.
- **Cómo retirarlo.** El mismo filtro que F1.

### F3. Promociones con PRO
- **Peticiones:**
  - `assets.elementor.com/packages/v1/promotions.json` en cada carga del editor (`[E]modules/promotions/module.php:55,253-271`; opción `_elementor_v4_promotions`, 1 h);
  - `free-to-pro-upsell.json` (`:58-61,228-241`).
- **Cómo retirarlo.** `remove_filter` y `remove_action` sobre `Plugin::$instance->modules_manager->get_modules('promotions')` en `elementor/init`, o el escudo remoto.

### F4. Element Manager, Hello+ y Site Mailer
- **Element Manager:** con PRO no hace peticiones, sólo URLs de promoción (`[E]modules/element-manager/ajax.php:18-22,96-98`).
- **Hello+:** sólo una categoría vacía (`[E]includes/managers/elements.php:314`).
- **Site Mailer:** sólo enlaces de ayuda dentro de Editor One (A4).

---

## G. Telemetría

### G1. Seguimiento de uso (`elementor_allow_tracking`)
- **Petición:** servidor → `wp_safe_remote_post my.elementor.com/api/v1/tracker/`, con `blocking:false`. Activa `https_ssl_verify=false` **de forma global** al enviar (`[E]includes/tracker.php:32,96-160`).
- **Cuándo:** cron diario `elementor/tracker/send_event`, que programa `Digitalisimo_Elements_Core::activate()` vía `[E]includes/maintenance.php:34-36` y que nunca se limpia.
- **Qué incluye:** los correos de los usuarios conectados (`[E]core/common/modules/connect/module.php:93-95,235-265`).
- **Qué ve el usuario:** aviso «¿Quieres dar forma al futuro…?» (`[E]core/admin/admin-notices.php:191-252`) y el campo «Compartir datos» en Ajustes (`[E]includes/settings/settings-page.php:389-405`).
- **Cómo retirarlo:**
  - `pre_option_elementor_allow_tracking` → `'no'`;
  - `pre_option_elementor_tracker_notice` → `'1'`;
  - `pre_option_elementor_allow_tracking_last_update` → una marca de tiempo fija; así `Assets::should_share_usage_data()` de Editor One devuelve `false` en las pantallas del constructor;
  - `wp_clear_scheduled_hook('elementor/tracker/send_event')`;
  - ocultar el campo por CSS: `Settings_Page` no tiene método para quitarlo.

### G2. Eventos y Mixpanel
- **Peticiones:**
  - servidor: configuración `assets.elementor.com/mixpanel` y proxy `api-eu.mixpanel.com` / `cdn.mxpnl.com`;
  - navegador: SDK en el editor y en la barra de Editor One;
  - también decide variantes A/B, por ejemplo la del diálogo «Guardar plantilla» (`editor.js:45738-45748`).
  - Origen: `[E]core/common/modules/events-manager/module.php:23-26,104-124,145-165`; token en `[E]elementor.php:48-49`.
- **Cómo retirarlo:**
  - `define('ELEMENTOR_EDITOR_EVENTS_MIXPANEL_TOKEN','')` en `[P]pro-elements.php` antes de `Digitalisimo_Elements_Core::load()`;
  - `pre_option_elementor_experiment-editor_events` → `'inactive'`.

### G3. Comentarios al desactivar y programa beta
- **Comentarios al desactivar:** `[E]core/admin/feedback.php:20-30,157-170` llama a `Api::send_feedback()`. Sólo se engancha al enlace «Desactivar» de la fila con `data-slug="elementor"` (`[E]assets/js/admin-feedback.js:18`), es decir, al plugin original. Con ese plugin activo el núcleo incluido no se carga, así que en la práctica nunca se dispara. Sólo deja HTML inerte en la pantalla de plugins.
- **Programa beta:** `[E]includes/beta-testers.php:53,115-122` e `[E]includes/user.php:48,320-345`. Ya queda inactivo con `no_beta`.

---

## H. API remota de información, notificaciones y pantallas

### H1. `Api::get_info_data()`
- **Petición:** servidor → `GET my.elementor.com/api/v2/info/`. Guarda el transitorio `elementor_remote_info_api_data_{versión}` (12 h; 2 h si falla) y la opción `elementor_remote_info_feed_data` (`[E]includes/api.php:42,58-115`).
- **Cuándo:** **en cualquier pantalla de administración**, porque `notice_api_upgrade_plugin()` lo llama antes de comprobar la pantalla (`[E]core/admin/admin-notices.php:85-88`, enganchado en `:857`). También desde el widget del escritorio (`[E]core/admin/admin.php:599`) y desde Información del sistema.
- **Identidad.** Oculta el feed, pero la petición se hace igual.
- **Cómo retirarlo.** Respuesta sintética 200 `{"ok":true}`: queda en caché 12 h, sin avisos, sin `pro_widgets` y sin canary. Otra opción: `remove_action('admin_notices', [Plugin::$instance->admin->get_component('admin-notices'),'admin_notices'], 20)`, aunque eso retira también avisos útiles.

### H2. Notificaciones del editor («Novedades»)
- **Petición:** `assets.elementor.com/notifications/v1/notifications.json`. Guarda la opción `_elementor_notifications_data` (12 h). La AJAX `notifications_get` fuerza una petición nueva cada vez que se abre el panel (`[E]modules/notifications/api.php:9,33-44`; `module.php:46-47,86-92`).
- **Identidad.** La lista queda vacía, pero el icono y la petición siguen.
- **Cómo retirarlo.** `wp_dequeue_script('e-editor-notifications')` en `elementor/editor/after_enqueue_scripts` y en `elementor/editor/v2/scripts/enqueue` (prioridad 20), más el escudo remoto.

### H3. Inicio rápido y Canary
- **Inicio rápido:** pide `home-screen.json` aunque la identidad lo depura (`[E]modules/home/module.php:99-105`) y carga Google Fonts Poppins (`:26-32`).
- **Canary:** `[E]core/admin/canary-deployment.php` es código muerto; no se instancia.

---

## I. Migraciones remotas en modo reversión
- **Petición:** servidor → `https://editor.elementor.com/v1/migrations/manifest.json` y un archivo por migración. Guarda el transitorio `elementor_migrations_manifest` (12 h).
- **Cuándo:** al cargar cualquier documento si `ELEMENTOR_VERSION` es menor que la opción `elementor_version` (`[E]modules/atomic-widgets/prop-type-migrations/migrations-orchestrator.php:23,71-79,353-382`; `migrations-loader.php:261-317,368-380`). Es justo lo que pasa en un sitio que venía del plugin Elementor más nuevo que la 4.3.5 incluida.
- **Si se bloquea:** usa las migraciones locales. Pero el contenido atómico guardado con la versión nueva puede no rebajarse bien.
- **Cómo retirarlo sin perder función:** en `pre_http_request`, servir el `manifest.json` y sus archivos desde una copia propia empaquetada. Si no hay copia, avisar que la reversión queda sin migraciones descendentes.

## J. Recursos de navegador y CDN
- **Imágenes de `assets.elementor.com`:** checklist, promociones v4, Angie, onboarding y Loop. Pertenecen a módulos que ya se retiran o están ocultos.
- **Ace desde `cdn.jsdelivr.net`:** editor de CSS y código (`[E]core/editor/loader/editor-loader.php:272-278`). No es del proveedor.
- **Google Fonts:**
  - Roboto en el editor (`editor-loader.php:476`);
  - Editor One en todas las pantallas de administración;
  - Poppins en el inicio rápido, en atomic y en el onboarding;
  - las fuentes del sitio en el frontal (`[E]includes/frontend.php:964`), que se pueden servir en local con `elementor_local_google_fonts=1`.
- **El navegador no se puede bloquear desde PHP.** Hay que ocultar o retirar la interfaz, o usar una CSP en administración y editor (`img-src`, `connect-src` y `frame-src` sin `*.elementor.com`). Conviene empezar en modo `Report-Only`.

---

## K. Mecanismo transversal recomendado: «escudo remoto»

Un solo `add_filter('pre_http_request', …, 10, 3)`. Sólo debe actuar si `Digitalisimo_Elements_Core::bundled()`, para que el plugin original activo se quede «tal cual». Por host y ruta:

| Destino | Respuesta |
|---|---|
| `my.elementor.com/api/v1/templates/info/` | 200 `{"types_data":{}}` (B2) |
| `my.elementor.com/api/v2/info/` | 200 `{"ok":true}` (H1, B4) |
| `my.elementor.com/api/connect/v1/*`, `/api/v1/kits-library/*`, `ms-8874.elementor.com`, `/api/v2/ai/*`, `cloud-library.prod.builder.elementor.red` | **403 con JSON**, nunca `WP_Error` (evita `wp_die`) ni 401 (borra tokens y relanza authorize) |
| `assets.elementor.com`, `/api/v1/tracker/`, `/api/v1/feedback/`, `/api/v1/beta_tester/`, `/api/v1/notifications`, `/feedback/api/v1`, `/connect/api/v1/*`, `planner.elementor.com`, `*.mixpanel.com`, `cdn.mxpnl.com`, `plugins.svn.wordpress.org/elementor/` | `WP_Error` (sus llamadores lo toleran) |
| `editor.elementor.com/v1/migrations/*` | copia local o `WP_Error` (I) |

**Por qué no las otras vías:**
- `http_request_host_is_external` **no bloquea**: sólo autoriza hosts internos en `wp_safe_remote_*`.
- `WP_HTTP_BLOCK_EXTERNAL` es global: rompería el actualizador de GitHub, la importación de imágenes y Google Fonts locales.

**Limpieza única (migración de datos):**
- opción `elementor_remote_info_library` y transitorios `elementor_remote_info_api_data_*`, `elementor_remote_kits`, `elementor_remote_kits_taxonomies` y `elementor_migrations_manifest`;
- opciones de caché `_elementor_notifications_data`, `_elementor_home_screen_data`, `_elementor_v4_promotions`, `_elementor_free_to_pro_upsell`, `_elementor_mixpanel_config`, `_elementor_ab_testing_data`, `_elementor_apps_data`, `elementor_plugins_page_assets_data`, `elementor_side_menu_assets_data` y `elementor_connect_page_assets_data`;
- **tokens:** la opción de usuario `elementor_connect_common_data` de todos los usuarios y las opciones `elementor_one_*` (client_id, secret, access/refresh token);
- el cron `elementor/tracker/send_event`.

---

## Riesgos

1. **`wp_die()` en Connect.** Bloquear con `WP_Error` los hosts de Connect y de la nube rompe la AJAX del modal de plantillas y la App (`base-app.php:469-472`). Usar 403 y apps nulas.
2. **Plantillas locales invisibles.** Si `templates/info` falla, «Mis plantillas» aparece vacío (B2).
3. **Errores fatales.** No retirar el componente `connect` ni el paquete Editor One: hay llamadores sin comprobación y el menú `elementor-home` depende de ese paquete.
4. **Pestañas que vuelven.** Los botones flotantes vuelven a añadir las pestañas remotas al cerrarse. Además pierden su catálogo remoto.
5. **Reversión de versión.** En modo reversión, sin servidor ni copia local, el contenido atómico de versiones nuevas puede no migrarse hacia atrás.
6. **Productos del ecosistema.** Un escudo global también corta a Image Optimizer, Site Mailer o Ally si el usuario los instala. Hay que acotarlo por host y ruta del núcleo incluido, o por traza de llamada dentro de `[E]`.
7. **Interfaz bloqueada pero visible.** Si se bloquea la red sin ocultar la interfaz, quedan botones «Conectar» que no hacen nada, spinners y errores (Kit Library y nube). Hay que retirar ambas cosas a la vez.
8. **Selectores frágiles.** Los selectores MUI y CSS cambian con cada núcleo. Conviene sumar a `tests/playground/brand-audit.sh` una comprobación de peticiones de red a `*.elementor.com` y a `mixpanel` (HAR o DevTools) en administración, editor y App.
9. **Textos sin marca.** Los de la barra de Editor One («Elementor Uno», «Elementor One») vienen de JSON propios y no pasan por `wp.i18n`. Hoy salen sin la marca propia.

## Lista priorizada

1. Escudo remoto (K) con respuestas sintéticas para `templates/info` y `info`, y 403 para Connect y nube.
2. Apps Connect nulas y redirección de `page=elementor-connect` (A1); en PRO, editar `activate.php`, `cloud-library/module.php` y `kit-library/module.php`.
3. Apagar la IA con `get_user_option_elementor_enable_ai` (D1), su fila del perfil (D2) y el botón de IA de Código personalizado (D3, editable).
4. Biblioteca: pestañas fuera, ruta por defecto `my-templates`, `unregister_source('remote')` y `('cloud')`, CSS de insignias y conexión (B1, B3).
5. Kit Library: submenú fuera, redirección `#/kit-library` → `#/import`, Finder, kits en la nube (C1, C2).
6. Menú del editor: «Mi Elementor» / «Conectar», Ayuda, Comentarios y Angie con `overwrite` (A2, A3, E1).
7. Barra de Editor One fuera y paquete neutralizado: fuentes, revisión de licencia y REST (A4, A5).
8. Telemetría: `allow_tracking=no`, token de Mixpanel vacío, experimento `editor_events` inactivo, limpiar el cron (G1, G2).
9. Site Planner y rutas REST de conexión y Angie (A6, A7, E1).
10. Notificaciones del editor, promociones con PRO, onboarding A/B, sugerencias de Image Optimizer y Ally (H2, F1-F3, C3).
11. Migraciones remotas desde copia local (I) y limpieza de datos y tokens.
12. Actualizar `docs/ELEMENTOR_CORE.md:28`, el texto de «Acerca de» (`digitalisimo-brand.php:73`) y la auditoría de marca con comprobación de red.
