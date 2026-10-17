# Cómo verificar automáticamente que no quede nada hacia los plugins de origen

> **Estado: resuelto en F0.2** (rama `fusion/f0-2-sin-referencias`, commit «Remove every link, video, account and request toward other companies»). La guarda vive en `digitalisimo-elements/digitalisimo-vendor-free.php` y `assets/digitalisimo/vendor-free.js`; las pruebas son `tests/elements-vendor-guard.php`, `tests/elements-vendor-static.mjs`, `tests/elements-identity.php` y `tests/playground/brand-audit.sh` ampliado (ver `tests/playground/README.md`). Este inventario, del 16 de octubre de 2026 y depurado para el repositorio público, queda como referencia de qué se retiró y por qué; las líneas citadas son de antes del cambio. Sirve también para repetir la revisión en cada `sync-elementor-core.sh`.

> Los esbozos de código de este documento son la propuesta de diseño; la implementación real (`brand-audit/run.cjs`, `probe-http.php`, `cron.php`, `vendors.json`, `vendors.cjs`, `allow.json`, `migrado.json`) puede diferir y es la referencia. Pendiente: añadir a `vendors.json` los dominios futuros (Divi, Betheme, GeneratePress, Bricks) como dominios activos cuando cada origen entre en Migración.

Hoy la auditoría no ve la red, el servidor, las redirecciones, los correos ni las ventanas o paneles que no se abren solos. Además, varios retiros que se dan por hechos siguen haciendo peticiones al proveedor.

`R/` es la raíz del repositorio.

## 0. Huecos concretos de la auditoría actual

1. **Sólo se revisan los iframes del propio sitio.** `R/tests/playground/brand-audit/run.cjs:106` filtra con `f.url().startsWith(base)`. Un iframe de YouTube o de my.elementor.com nunca se examina.
2. **Sólo cuenta el texto «Elementor».** `run.cjs:9,13` no mira URL ni dominios. Para los logotipos sólo busca rutas locales (`run.cjs:52`).
3. **Las redirecciones al proveedor pasan como pantallas limpias.** `admin.php?page=go_elementor_pro` y `elementor-one-upgrade` redirigen con `wp_safe_redirect` a go.elementor.com (`R/digitalisimo-elements/elementor/modules/promotions/module.php:118-126`). `page.goto` sigue la redirección y la auditoría sólo descarta frames que no son del sitio.
4. **El vídeo del inicio rápido sigue cargando.** `identity.css:55-58` lo oculta con CSS, pero el iframe se crea igual con `src: "https://www.youtube.com/embed/".concat(youtubeEmbeddedId)` (`R/digitalisimo-elements/elementor/assets/js/e-home-screen.js:2575,2632`). Con el id vacío pide `youtube.com/embed/`. Para que no se pinte, `top_with_licences` tiene que quedar vacío (`if (!topData) return null`, línea 2570).
5. **Los datos remotos se descargan antes de filtrarse.** `modules/notifications/api.php:40-41` y `modules/home/api.php:15-17` piden primero a assets.elementor.com y aplican el filtro después. Por eso `elementor/core/admin/notifications` y `elementor/core/admin/homescreen` esconden el contenido, pero la petición sale igual. La caché se guarda en opciones (`includes/editor-assets-api.php`, `get_transient()` usa `get_option`).
6. **La prueba unitaria protege lo que hay que quitar.** `R/tests/elements-identity.php:63` exige que se conserve `https://assets.elementor.com/i/site-settings.svg`. La línea 61 sólo vacía `youtube_embed_id`.
7. **La preparación esconde un estado.** `setup.php:20` fija `elementor_onboarded` a mano, aunque la identidad ya lo hace con `pre_option`.
8. **No hay registro de peticiones salientes del servidor, cron, AJAX, REST ni correos.**

## 1. Una sola lista de proveedores

Propongo un archivo nuevo, `R/tests/playground/brand-audit/vendors.json`, que lean `run.cjs`, el mu-plugin y la prueba unitaria:

```json
{
 "hosts": ["elementor.com","elementor.cloud","elementor.red","elementor.io","mxpnl.com","proelements.org","bdthemes.com","bdthemes.co","elementpack.pro","primeslider.pro"],
 "callerHosts": {"mixpanel.com": "telemetría del núcleo (api-eu / api-js)"},
 "paths": [
  {"host": "^storage\\.googleapis\\.com$", "re": "elementor"},
  {"host": "^(downloads|api)\\.wordpress\\.org$|^ps\\.w\\.org$", "re": "/(plugins?|themes?)/(elementor|elementor-pro|pro-elements|hello-elementor|hello-biz|hello-commerce|bdthemes-element-pack(-lite)?|bdthemes-prime-slider-lite)\\b"},
  {"host": "^(www\\.)?wordpress\\.org$", "re": "/(support/)?(plugin|theme)s?/(elementor|pro-elements|hello-elementor)\\b"}
 ],
 "videoHosts": "(^|\\.)(youtube(-nocookie)?\\.com|youtu\\.be|ytimg\\.com|vimeo\\.com|vimeocdn\\.com)$",
 "vendorVideos": ["XHOmBV4js_E","235215203","vN9DnFiRMX0","YE7VzlLtp-4"],
 "future": ["elegantthemes.com","muffingroup.com","generatepress.com","gpsites.co","bricksbuilder.io"],
 "originalPlugins": ["elementor/elementor.php","elementor-pro/elementor-pro.php","pro-elements/pro-elements.php","bdthemes-element-pack/bdthemes-element-pack.php","bdthemes-element-pack-lite/bdthemes-element-pack-lite.php","bdthemes-prime-slider-lite/bdthemes-prime-slider.php"]
}
```

De dónde sale cada entrada:
- **`elementor.red`:** producción real, no sólo pruebas. Lo usan `cloud-library.prod.builder.elementor.red` y `storage.googleapis.com/static-platform-client-*-prod.new-prod.elementor.red`, en `modules/cloud-library/connect/cloud-library.php`, `assets/js/app.js` y `editor-mcp`.
- **`mxpnl.com` y `mixpanel.com`:** son la telemetría del núcleo (`core/common/modules/events-manager/module.php:25`; `cdn.mxpnl.com/libs/mixpanel-recorder.min.js` en `common.js`).
- **Vídeos del proveedor:**
  - `XHOmBV4js_E`: `includes/widgets/video.php:182`, `includes/controls/groups/background.php:527`, `modules/atomic-widgets/elements/atomic-youtube/atomic-youtube.php:61` y `R/digitalisimo-elements/modules/video-playlist/widgets/video-playlist.php:414-428`.
  - `235215203`: `video.php:207`.
  - `vN9DnFiRMX0` y `YE7VzlLtp-4`: `R/digitalisimo-migration/legacy/maps/bdt-video-gallery.php:52`, `bdt-device-slider.conf:27` y `lightbox.php:34`.
  - Conviene regenerar la lista al arrancar la prueba con un `grep` de `youtube.com/watch?v=|vimeo.com/` sobre `elementor/`, `modules/`, `digitalisimo-migration/legacy/` y, si existen, los ZIP de `reference/`.
- **YouTube y Vimeo genéricos se permiten en el contenido.** Los widgets propios los usan como servicio (`class-video-player.php:42,74`, `class-device-slider.php:37`, `class-offcanvas.php:123`).

## 2. Navegador: registrar toda petición

En `run.cjs:120`, añadir a `puppeteer.launch`:

```js
const V = JSON.parse(fs.readFileSync(__dirname + '/vendors.json', 'utf8'));
args: [...actuales, '--disable-features=IsolateOrigins,site-per-process', '--disable-site-isolation-trials',
  '--host-resolver-rules=' + V.hosts.flatMap((h) => [`MAP ${h} ~NOTFOUND`, `MAP *.${h} ~NOTFOUND`]).join(', '),
  `--log-net-log=${OUT}/netlog.json`, '--net-log-capture-mode=Default'],
```

- **Por qué desactivar el aislamiento de sitios:** así los iframes de otros dominios quedan en el mismo proceso y sus peticiones pasan por `page.on('request')`.
- **`--host-resolver-rules`:** garantiza que nada llegue al proveedor, también desde workers o ventanas nuevas. El repositorio ya usa esta opción en `migrate.sh`.
- **`netlog.json`:** es la red de respaldo. Registra también `preconnect` y `dns-prefetch`. Al final se busca ahí cualquier host del proveedor que no se haya atribuido a una pantalla.

Registro por página y por ventana nueva, siguiendo el patrón de `R/tests/playground/migrate/run.cjs:23-25`:

```js
let screen = 'login', locale = '';
const net = [];
const vendor = (raw, content) => { /* host del sitio → ''; V.hosts por sufijo con punto; V.paths; videoHosts: en admin siempre, en contenido sólo si contiene un id de V.vendorVideos */ };
async function watch(p) {
  await p.setRequestInterception(true);
  await p.evaluateOnNewDocument(() => { const o = window.open; window.__digiOpened = []; window.open = function (u, ...r) { window.__digiOpened.push(String(u || '')); return o.call(this, u, ...r); }; });
  p.on('request', (r) => {
    const url = r.url(), f = r.frame() ? r.frame().url() : '';
    const content = /\/digi-marca\/|elementor-preview=/.test(f) && !r.isNavigationRequest();
    const why = vendor(url, content);
    if (why) net.push({ locale, screen, why, type: r.resourceType(), url: url.slice(0, 300), frame: f.slice(0, 160), nav: r.isNavigationRequest(), from: (r.redirectChain().slice(-1)[0] || { url: () => '' }).url() });
    return url.startsWith(base) || /^(data|blob):/.test(url) ? r.continue() : r.abort('blockedbyclient');
  });
  p.on('popup', (w) => { net.push({ locale, screen, why: 'ventana', url: w.url(), type: 'popup' }); watch(w).catch(() => {}); });
}
browser.on('targetchanged', (t) => { if (vendor(t.url())) net.push({ locale, screen, why: 'destino', url: t.url(), type: t.type() }); });
```

- Antes de cada pantalla: `screen = label; await page.setCookie({ name: 'digi_screen', value: encodeURIComponent(label), url: base })`. Esa cookie viaja también en el AJAX y el REST, y el registro del servidor la usa para saber de qué pantalla vino cada petición.
- En los `catch` de `run.cjs:156` y `:186`, si el error es `ERR_BLOCKED_BY_CLIENT` o `ERR_NAME_NOT_RESOLVED` y hay una entrada `nav:true` en `net`, se anota «redirección al proveedor desde X».

**Sondeo de enlaces propios que redirigen al proveedor.** Se toman los `href` del sitio que encajen con `/[?&]page=(go_[a-z_]+|elementor-one-upgrade|elementor-connect)\b|[?&]action=authorize\b/`, más los `admin.php?page=` sin `action=` ni `_wpnonce` que no se hayan visitado. Desde Node se piden con `fetch(url, { redirect: 'manual', headers: { cookie } })` y se clasifica el `Location`. No hay que seguir enlaces de `admin-post.php`: pueden ejecutar acciones.

## 3. DOM: URL en atributos, estilos, plantillas y datos

Se añade `collectUrls(V, base, content)` y se evalúa en todos los frames del sitio, en `about:blank` y en `srcdoc`. Los frames de otro origen se anotan directamente por su `frame.url()`.
- **Atributos de URL:** `href`, `src`, `action`, `formaction`, `poster`, `data` (`object`), `cite`, `ping`, `background`, `xlink:href`.
- **`srcset` e `imagesrcset`:** se separan por comas.
- **`data-*`, `style`, `content` (meta refresh) y `on*`:** se buscan URL dentro del valor, con regex `(?:https?:)?\\?\/\\?\/[^\s"'<>()\\]+` y quitando el escape de `\/`. Esto cubre `data-settings` con `youtube_url`.
- **Estilos calculados, también en `::before` y `::after`:** `background-image`, `mask-image`, `-webkit-mask-image`, `list-style-image`, `border-image-source`, `content` y `cursor`. Conviene integrarlo en el bucle de paleta ya existente (`run.cjs:82-100`) para no recalcular estilos.
- **Elementos:** `iframe`, `video`, `source`, `track`, `embed`, `object` y `link[rel=preconnect|dns-prefetch|preload|prefetch]` caen solos con lo anterior. También se recorren los `shadowRoot` abiertos y `template.content`.
- **Contenido latente.** En `script[type="text/template"]` y en `script:not([src])` se buscan URL del proveedor y se informa el `id` del script. WordPress los nombra `{handle}-js-extra`, `-js-before` y `-js-after`, así que el `id` lleva directo a la llamada PHP que hay que filtrar. Un ejemplo es `ElementorConfig.initial_document.widgets.*.help_url`, que viene de `includes/base/element-base.php:268,1511` y alimenta el enlace «Need help?»; se puede filtrar con `elementor/document/config` o `elementor/editor/localize_settings`.
- Al final se suman `window.__digiOpened` y los globales `elementor*Config`/`Elementor*Config` serializados, con la ruta JSON de cada URL.

Severidad: la red, la red del servidor, las redirecciones, los correos y cualquier atributo del DOM, visible u oculto, hacen fallar. Lo latente (plantillas y datos localizados) se informa y pasa a fallar con `LATENT=fail` cuando se cierre el inventario.

## 4. Servidor: mu-plugin de registro

Archivo nuevo `R/tests/playground/brand-audit/probe-http.php`. Se copia a `$OUT` y lo carga una línea en `setup.php`:

```php
file_put_contents( WPMU_PLUGIN_DIR . '/digi-http-probe.php', '<?php require "/wordpress/wp-content/probe/probe-http.php";' );
require '/wordpress/wp-content/probe/probe-http.php';
```

El `require` inmediato hace falta para registrar también lo que pide la activación dentro de `setup.php`.

```php
<?php
$GLOBALS['digi_v'] = json_decode( file_get_contents( __DIR__ . '/vendors.json' ), true );
function digi_vendor( $url ) { /* misma lógica que vendor() de run.cjs; para callerHosts exige que digi_caller() esté bajo digitalisimo-elements/elementor/ */ }
function digi_caller() { foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ) as $f ) { $file = $f['file'] ?? ''; if ( false !== strpos( $file, '/wp-content/plugins/' ) ) { return preg_replace( '#^.*/wp-content/plugins/#', '', $file ) . ':' . ( $f['line'] ?? 0 ); } } return ''; }
function digi_context() {
	if ( wp_doing_cron() ) { return 'cron:' . ( $GLOBALS['digi_cron_hook'] ?? '' ); }
	if ( wp_doing_ajax() ) { $a = sanitize_key( $_REQUEST['action'] ?? '' ); if ( 'elementor_ajax' === $a ) { $a .= '[' . implode( ',', array_unique( array_column( (array) json_decode( wp_unslash( $_REQUEST['actions'] ?? '' ), true ), 'action' ) ) ) . ']'; } return 'ajax:' . $a; }
	return ( defined( 'REST_REQUEST' ) ? 'rest:' : ( is_admin() ? 'admin:' : 'front:' ) ) . ( $_SERVER['REQUEST_URI'] ?? '' );
}
function digi_log( $kind, $url, $extra = array() ) {
	file_put_contents( __DIR__ . '/http.jsonl', wp_json_encode( array( 'kind' => $kind, 'url' => $url, 'why' => digi_vendor( $url ), 'context' => digi_context(), 'caller' => digi_caller(), 'screen' => rawurldecode( $_COOKIE['digi_screen'] ?? '' ) ) + $extra, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n", FILE_APPEND | LOCK_EX );
}
add_filter( 'pre_http_request', static function ( $pre, $args, $url ) {
	if ( ! digi_vendor( $url ) ) { return $pre; }
	digi_log( false === $pre ? 'salió' : 'bloqueada', $url, array( 'method' => $args['method'] ?? 'GET' ) );
	return false === $pre ? new WP_Error( 'digi_probe', 'Auditoría: petición al proveedor' ) : $pre;
}, PHP_INT_MAX, 3 );
add_filter( 'wp_redirect', static function ( $l ) { if ( digi_vendor( (string) $l ) ) { digi_log( 'redirección', (string) $l ); } return $l; }, PHP_INT_MAX );
add_filter( 'allowed_redirect_hosts', static function ( $h ) { foreach ( (array) $h as $x ) { if ( digi_vendor( 'https://' . $x . '/' ) ) { digi_log( 'host permitido', $x ); } } return $h; }, PHP_INT_MAX );
add_filter( 'pre_wp_mail', static function ( $n, $a ) { preg_match_all( '#https?://[^\s"\'<>]+#', $a['subject'] . "\n" . $a['message'], $m ); foreach ( $m[0] as $u ) { if ( digi_vendor( $u ) ) { digi_log( 'correo', $u, array( 'subject' => $a['subject'] ) ); } } return $n; }, PHP_INT_MAX, 2 );
```

- Con prioridad `PHP_INT_MAX`, el registro ve lo que dejó pasar la guarda del producto. «bloqueada» significa que la guarda funcionó y no falla. «salió» falla, y además se corta con `WP_Error` para que la prueba no dependa de internet.
- Archivo nuevo `R/tests/playground/brand-audit/cron.php`. Hace `define('DOING_CRON', true)`, carga `wp-load` y `wp_set_current_user(0)`. Recorre `_get_cron_array()` y ejecuta cada hook con `$GLOBALS['digi_cron_hook'] = $hook; do_action_ref_array(...)`. Fuerza además `elementor/tracker/send_event` y los hooks de `wp-one-package` (`Connect/Components/License.php:99`). `run.cjs` lo llama con `fetch(base + '/wp-content/probe/cron.php')` al final de cada idioma.
- `run.cjs` lee `$OUT/http.jsonl` después de cada pantalla y al terminar.
- `brand-audit.sh:16-17`: copiar `vendors.json`, `probe-http.php` y `cron.php`, y borrar `http.jsonl` y `netlog.json`. En `brand-audit.sh:39`, fallar si hay líneas `salió`, `redirección` o `correo`.

## 5. Pantallas y estados nuevos

**Administración.** Todos los enlaces de `#toplevel_page_elementor-home`, `#toplevel_page_elementor` y `#menu-posts-elementor_library`, en lugar de la regex de `run.cjs:137`, que se salta páginas. Además:
- `page=elementor-connect`, `elementor-apps`, `elementor-element-manager`, `elementor-role-manager`, `elementor-system-info`, `elementor-mcp` y `e-form-submissions`.
- Cada pestaña de `elementor-settings` y de `elementor-tools`, clic en `.nav-tab-wrapper a`; incluye versiones, mantenimiento e importar kit.
- `edit.php?post_type=elementor_library&tabs_group=popup`, `edit.php?post_type=elementor_snippet`, `e-custom-fonts`, `e-custom-icons`, `e-floating-buttons`, `e-landing-page` y el diálogo «Añadir plantilla» (`.page-title-action`).
- Pantallas de WordPress: `themes.php` (banner de Hello en `modules/promotions/conversion-banner.php:28`), `update-core.php`, `upload.php` con el modal de medios (IA), `post-new.php` (Gutenberg con `ai-gutenberg`) y las pantallas de Migración (sitio y red; hay que montar `digitalisimo-migration`).
- Barra superior de Editor One (`#editor-one-top-bar`): clic en cada botón, auditoría y Escape. Ahí están ayuda, novedades (`WHATS_NEW_API`), formulario de soporte (`SUPPORT_FORM_URL`, `editor-one-top-bar.js:15336-15338`), cuenta y mejora.

**App de kits:** `#/kit-library/cloud`, `#/kit-library/favorites`, `#/onboarding` (instala Hello: `app/modules/onboarding-new/data/endpoints/install-theme.php:14,72`), `#/import-customization`, `#/export-customization`, `#/site-editor/templates`, `#/site-editor/add-new` y `#/site-editor/import`.

**Editor.** Usar comandos `$e` y no textos, que dependen del idioma:
- `$e.route('finder')`.
- `$e.run('library/open')` con las rutas `library/templates/blocks`, `pages`, `my-templates` y `cloud` (ahí aparece el inicio de sesión).
- `panel/elements/categories`.
- Seleccionar el título (`$e.run('document/elements/select', {container})`) y recorrer content, style y advanced; ahí está «Need help?».
- Crear `e-heading` y `e-youtube` para el panel v4, y `video` sin ajustes para que se use el valor por defecto.
- `panel/global/open`, `panel/page-settings/settings`, `panel/history/revisions`, `navigator`, `shortcuts`, `notes`, `checklist`.
- Un botón de IA de un control.
- El menú contextual.
- Cada botón de `#elementor-editor-wrapper-v2 header`, salvo publicar o guardar (regex `/publi|guard|save|update|actualiz/i`).
- Documento popup (plantilla `_elementor_template_type=popup` en `setup.php`) y cabecera del Theme Builder con su modal de condiciones.

**Frontal.** En la página de prueba: Video sin `youtube_url`, sección con vídeo de fondo por defecto y `e-youtube`. Después:
- Pasar el ratón por el menú de la barra de administración y auditar.
- Como visitante: el HTML completo, todos los atributos, los scripts en línea y las cabeceras HTTP, no sólo la meta generator (`run.cjs:192-195`).

**Estados (`STATE=`):**
- `nuevo`: el actual, sin forzar `elementor_onboarded`.
- `migrado`: un sitio que viene de Elementor. Se preparan:
  - `elementor_allow_tracking=yes` y `_elementor_installed_time` de hace 60 días, con varias páginas del constructor;
  - `elementor_beta=yes` y `elementor_connect_site_key`;
  - opciones de usuario `elementor_connect_common_data` y `elementor_connect_library` con un `access_token` ficticio, para que se vean «Mi cuenta» y «Desconectar»;
  - cachés de EditorAssetsAPI con datos del proveedor ya guardados (`{timeout: futuro, value: json}`): `_elementor_home_screen_data`, `_elementor_notifications_data`, `_elementor_apps_data`, `_elementor_free_to_pro_upsell`, `_elementor_v4_promotions`, `elementor_plugins_page_assets_data`, `elementor_side_menu_assets_data`, `elementor_conversion_banner_assets_data`, `elementor_pointer_assets_data`, `elementor_connect_page_assets_data`, `_elementor_mixpanel_config`, `_elementor_ab_testing_data` y `_elementor_onboarding_strings_*`;
  - `elementor_remote_info_feed_data`.

  Sirve para comprobar que lo que ya estaba en la base de datos tampoco se pinta.
- `ORIGINAL=1`: Elementor de wordpress.org instalado e inactivo, como en `core-switch.sh:18`, más un Elementor Pro falso con sólo sus cabeceras.
- Idiomas: es_MX y en_US, como ahora.

## 6. Exclusiones correctas

- **Filas del plugin original.** En el DOM se omiten `tr[data-plugin="X"]:not(.digitalisimo-plugin-note)` para cada X de `V.originalPlugins`. Se omite también la fila de `#update-plugins-table` cuyo `input[value="X"]` coincida, y `#TB_window` cuando su iframe sea `plugin=elementor`.
- **La nota propia no se excluye de la revisión de URL.** La nota (`tr.digitalisimo-plugin-note`, `R/digitalisimo-elements/digitalisimo-core.php:173`) sólo queda fuera de la revisión de texto, porque nombra a propósito al original. No debe tener enlaces.
- **Imágenes de esas filas.** Los `img` dentro de las filas excluidas (iconos de `ps.w.org/elementor/…`) forman una lista blanca por pantalla para las peticiones de red. Sólo valen si coinciden exactamente con esa lista.
- **Servidor.** Sólo se permite lo que hace WordPress para el plugin instalado: `api.wordpress.org/plugins/update-check/`, `/translations/` y `downloads.wordpress.org/translation/plugin/elementor*`. El llamador debe estar en `wp-includes/update.php` o `wp-admin/includes/class-language-pack-upgrader.php`. Nunca vale un dominio del proveedor ni un llamador bajo `plugins/digitalisimo-elements/`.
- **Identificadores internos.** No hace falta excluirlos uno por uno: la clasificación va por host y omite el propio origen. Eso deja fuera clases, ids, `data-elementor-type`, `admin.php?page=elementor*`, `post_type=elementor_library` y `/digitalisimo-elements/elementor/assets/…`.
- **Lista de excepciones.** Las excepciones restantes van en `R/tests/playground/brand-audit/allow.json` (pantalla, regex y motivo). Una entrada que no se use en toda la corrida falla, para que la lista no se quede vieja.
- **Comprobación de la exclusión.** Con `ORIGINAL=1`, la exclusión debe haberse aplicado al menos una vez: la fila tiene enlaces a elementor.com. Fuera de la fila no debe haber ninguno.

## 7. Prueba unitaria PHP

Archivo nuevo `R/tests/elements-vendor-guard.php`, con el estilo de `R/tests/elements-identity.php`: stubs de `add_filter`, `WP_Error`, `wp_parse_url` (sobre `parse_url`), `admin_url` y `add_query_arg`. Se añade a `R/.github/workflows/release.yml` después de la línea 109. La guarda todavía no existe; la API que propongo es `Digitalisimo_Elements_Identity::vendor_url()`, `::block_http()` y `::no_vendor_redirect()`.

```php
$id::init(); // Sin núcleo incluido: ningún hook (regla del plugin original activo).
check( empty( $GLOBALS['hooks']['pre_http_request'] ), '…' );
Digitalisimo_Elements_Core::$bundled = true; $id::init();
foreach ( array( 'pre_http_request', 'wp_redirect', 'allowed_redirect_hosts', 'pre_option_elementor_allow_tracking' ) as $h ) check( ! empty( $GLOBALS['hooks'][ $h ] ), $h );
$v = json_decode( file_get_contents( __DIR__ . '/playground/brand-audit/vendors.json' ), true );
foreach ( $v['hosts'] as $h ) foreach ( array( "https://$h/x", "https://sub.$h/x", 'HTTPS://' . strtoupper( $h ) . '/' ) as $u ) check( is_wp_error( $id::block_http( false, array(), $u ) ) || is_array( $id::block_http( false, array(), $u ) ), "bloquea $u" );
foreach ( array( 'https://my.elementor.com/api/v2/info/', 'https://assets.elementor.com/notifications/v1/notifications.json', 'https://cloud-library.prod.builder.elementor.red/api/v1/cloud-library', 'https://storage.googleapis.com/static-platform-client-elementor-one-prod.new-prod.elementor.red/a.js', 'https://downloads.wordpress.org/theme/hello-elementor.latest-stable.zip' ) as $u ) check( false !== $id::block_http( false, array(), $u ), $u );
foreach ( array( 'https://notelementor.com/', 'https://elementor.com.example.org/', 'https://api.wordpress.org/plugins/update-check/1.1/', 'https://fonts.googleapis.com/css2', 'https://example.com/hook', 'https://github.com/danbutanda/digitalisimo-plugin', 'https://player.vimeo.com/video/1' ) as $u ) check( false === $id::block_http( false, array(), $u ), "deja pasar $u" );
$pre = array( 'body' => 'x' ); check( $pre === $id::block_http( $pre, array(), 'https://my.elementor.com/' ), 'respeta una respuesta ya resuelta' );
check( 0 === strpos( $id::no_vendor_redirect( 'https://go.elementor.com/go-pro-upgrade-one-wp-menu/' ), 'http' ) && false === strpos( $id::no_vendor_redirect( 'https://go.elementor.com/x' ), 'elementor.com' ), 'redirecciones del proveedor van a una página propia' );
check( 'no' === $id::no_tracking(), 'sin telemetría aunque el sitio migrado diga yes' );
// Cada caché remota del núcleo tiene su pre_option (se extraen de elementor/ para detectar claves nuevas):
preg_match_all( "/ASSETS_DATA_TRANSIENT_KEY\s*=>\s*'([^']+)'/", shell_exec( 'grep -rh ASSETS_DATA_TRANSIENT_KEY ' . escapeshellarg( __DIR__ . '/../digitalisimo-elements/elementor' ) . ' --include=*.php' ), $m );
foreach ( $m[1] as $key ) check( ! empty( $GLOBALS['hooks'][ 'pre_option_' . rtrim( $key, '_' ) ] ) || in_array( $key, $id::REMOTE_CACHES, true ), "caché remota $key" );
```

En `R/tests/elements-identity.php` hay que cambiar dos cosas:
- La línea 63 debe exigir que no quede ninguna imagen de `assets.elementor.com`: o se sustituyen por recursos propios o se quitan.
- La línea 61 debe exigir `array() === $home['top_with_licences']`; si no, el iframe sigue pidiendo YouTube.

Hay que añadir dos casos de `brand_deep` para URL en datos localizados.

## 8. Complemento: prueba estática del código editable

Archivo nuevo `R/tests/elements-vendor-static.mjs`, enganchado en `validate-suite.mjs` y en release:
- Busca dominios de `vendors.json` en `R/digitalisimo-elements/{core,modules,license,assets/js,plugin.php,digitalisimo-*.php}` y en `R/digitalisimo-migration/includes`, con una lista de excepciones con motivo.
- Hoy aparecen, entre otros, `license/api.php:19-23`, `core/admin/action-links.php:22`, `modules/video-playlist/widgets/video-playlist.php:414-428` y los mapas `legacy/maps/bdt-*.php` de Migración.

Esto atrapa una regresión sin arrancar Playground. En `elementor/` sólo se buscan las claves de caché y las constantes de API, para detectar canales nuevos en cada versión del núcleo.

## 9. Archivos a cambiar

- **`R/tests/playground/brand-audit/run.cjs`:**
  - `:4-9`: cargar `vendors.json` y la función `vendor()`.
  - `:105-117`: `audit()` evalúa también `collectUrls`, los frames de otro origen y `about:blank`.
  - `:120-125`: opciones de arranque, `watch(page)` y `targetchanged`.
  - `:133-159`: cookie `digi_screen`, menú completo, pestañas y sondeo de redirecciones.
  - `:160-188`: estados del editor de la sección 5.
  - `:189-195`: frontal como visitante completo.
  - `:197-215`: secciones de red, servidor, redirecciones, correo y latentes en `brand-audit.json` y en la salida, y el código de salida.
- **`R/tests/playground/brand-audit/setup.php`:**
  - `:9-11`: mu-plugin de registro.
  - `:20`: quitar `elementor_onboarded`.
  - Agregar los widgets de vídeo por defecto, el popup, la cabecera del Theme Builder y las ramas `STATE=migrado` y `ORIGINAL=1`.
- **`R/tests/playground/brand-audit.sh`:**
  - `:16-17`: copias y limpieza.
  - `:19-21`: blueprint con `installPlugin elementor activate:false` si `ORIGINAL=1`.
  - `:24-26`: montar `digitalisimo-migration`.
  - `:39`: revisar `http.jsonl` y `netlog.json`.
- **Nuevos:** `vendors.json`, `probe-http.php`, `cron.php`, `allow.json`, `R/tests/elements-vendor-guard.php` y `R/tests/elements-vendor-static.mjs`.
- **Ya existentes:** `R/tests/elements-identity.php` (líneas 61 y 63), `R/.github/workflows/release.yml:109` y `R/tests/playground/README.md:64-73`, para documentar la red, el servidor, los estados y las exclusiones.
- **`R/tests/playground/form-submit/probe.php`:** revisar que el contenido enviado a Discord y a Slack no lleve marca ni logotipo del proveedor:
  - `modules/forms/actions/discord.php:200-203` escribe `'Elementor'` literal;
  - `discord.php:205` y `slack.php:231` usan `ELEMENTOR_ASSETS_URL . 'images/logo-icon.png'`.

## 10. Riesgos

- **Bloquear el vídeo por dominio rompe el vídeo del usuario.** En el contenido se permite YouTube o Vimeo genérico y sólo falla un id del proveedor. Como `iframe_api` queda abortado, el id se detecta en `data-settings` del DOM, no en la red.
- **Los valores por defecto heredados son contenido guardado.** Cambiar los de Migración (`bdthemes.com` en `bdt-qrcode.php:12`, `bdt-advanced-gmap.php:41,53` y `bdt-timeline.php:86`; vídeos de bdthemes) cambia lo que muestran las páginas migradas que nunca tocaron ese ajuste. Necesita una decisión de producto y la prueba A/B de `legacy-ab.sh`.
- **Quitar datos remotos quita funciones.** Bloquear assets.elementor.com y my.elementor.com deja sin biblioteca remota de plantillas, kits en la nube, IA, novedades y conexión de cuenta. La prueba lo marcará mientras la interfaz los ofrezca: o se ocultan los puntos de entrada o se declaran en `allow.json` con su motivo.
- **Una caché fallida esconde la siguiente petición.** Si la primera petición falla, Elementor guarda una caché de fallo durante horas (`editor-assets-api.php`). Por eso el registro tiene que estar activo desde `setup.php` y las cachés hay que borrarlas en cada corrida.
- **El aislamiento de sitios desactivado no es lo que corre en producción.** Puede tapar diferencias del navegador real; el `netlog` es la red de respaldo.
- **El tiempo de ejecución sube bastante.** Conviene `SCOPE=quick`, con las pantallas actuales más la red, y `SCOPE=full`.
- **`--host-resolver-rules` con comodines** necesita también la regla del dominio raíz, que ya está incluida.
- **Mixpanel no es del proveedor.** Bloquearlo en todo el sitio afectaría a otros plugins. Se limita a llamadas cuyo origen esté bajo `elementor/` y, en el producto, se resuelve con `pre_option_elementor_allow_tracking='no'` (`includes/tracker.php:177`, `events-manager/module.php:119-121`).

## 11. Lista priorizada

1. **Red del navegador y mu-plugin de servidor** (secciones 2 y 4). Es lo de mayor impacto: mostrará de inmediato las peticiones de inicio rápido, notificaciones, información de my.elementor.com, YouTube en el inicio rápido y las redirecciones `go_elementor_pro` y `elementor-one-upgrade`.
2. **Revisión de URL en el DOM y frontal de visitante completo** (sección 3).
3. **Arreglar `R/tests/elements-identity.php:61,63` y crear `elements-vendor-guard.php`** con la cobertura de todas las cachés de EditorAssetsAPI.
4. **Pantallas y estados del editor, la App y la barra superior** (sección 5). `STATE=migrado` es el caso real de los sitios que vienen de Elementor.
5. **`ORIGINAL=1` con `allow.json` comprobado.**
6. **Prueba estática del código editable y paso de lo latente de aviso a fallo.**
7. **Cron y correos** (Discord y Slack en `form-submit`).
8. **Dominios futuros** (Divi, Betheme, GeneratePress, Bricks) en `vendors.json`, cuando entren en Migración.
