# Inventario: enlaces, vídeos y accesos de cuenta hacia Elementor en DIGITALÍSIMO Elements (núcleo incluido 4.3.5 + derivado de PRO)

> **Estado: resuelto en F0.2** (rama `fusion/f0-2-sin-referencias`, commit «Remove every link, video, account and request toward other companies»). La guarda vive en `digitalisimo-elements/digitalisimo-vendor-free.php` y `assets/digitalisimo/vendor-free.js`; las pruebas son `tests/elements-vendor-guard.php`, `tests/elements-vendor-static.mjs`, `tests/elements-identity.php` y `tests/playground/brand-audit.sh` ampliado (ver `tests/playground/README.md`). Este inventario, del 16 de octubre de 2026 y depurado para el repositorio público, queda como referencia de qué se retiró y por qué; las líneas citadas son de antes del cambio. Sirve también para repetir la revisión en cada `sync-elementor-core.sh`.

> **Pendiente con aprobación del propietario** (D1 y valores por defecto de Migración): las páginas publicadas que hoy muestran el vídeo de muestra por defecto (Elementor no guarda los valores por defecto) se listan y se corrigen sólo con aprobación, porque cambian páginas publicadas.

Hoy `digitalisimo-identity.php` cubre una parte pequeña. Retira lo que llega como datos remotos: el inicio rápido, el feed y el pie del panel del escritorio, el contenido de «Novedades» del editor, los anuncios, el onboarding, el menú «Upgrade» y el pie del administrador. Casi ninguno de los enlaces escritos en el código está cubierto. Lo más visible que queda, en este orden:
- La barra superior Editor One del administrador: ayuda, novedades remotas y conexión a la cuenta de Elementor.
- La barra superior del editor: «My Elementor», «Help Center», «What's New» y la promoción de Angie.
- Los «Need Help» del panel.
- Los vídeos de Elementor que vienen por defecto en los widgets de vídeo.
- Los vídeos del Theme Builder.
- Los accesos de inicio de sesión: biblioteca de plantillas, kits, nube e IA.

Rutas: `E:` = `digitalisimo-elements/elementor/` (no se edita) y `P:` = `digitalisimo-elements/` (código propio y derivado de PRO, editable). Lo marcado «compilado PRO» exige editar el `.js` y el `.min.js`, y la fuente si existe.

Precedente útil: `P:core/editor/editor.php:156-161` y `P:core/admin/admin.php:89-96` ya limpian con `if ( defined( 'IS_PRO_ELEMENTS' ) )`. Es el lugar natural para borrar más URL del editor sin tocar `E:`.

---

## Conteo por dominio (apariciones en código ejecutable, sin `.min.js`, sin `languages/`)

| Dominio | núcleo PHP | núcleo vendor PHP | núcleo JS | vendor JS | PRO PHP | PRO JS | Lo retira la identidad |
|---|---|---|---|---|---|---|---|
| go.elementor.com | 143 | 1 | 253 | 16 | 48 | 26 | ~6: pie `admin-review` (`E:core/admin/admin.php:452`), 4 enlaces del pie del panel del escritorio (`admin.php:660-675`) y el menú «Upgrade» |
| my.elementor.com | 13 | 3 | 21 | 21 | 4 | 0 | sólo el feed del escritorio (`pre_option_elementor_remote_info_feed_data`) |
| assets.elementor.com | 16 | 0 | 14 | 36 | 0 | 3 | datos del inicio rápido, notificaciones del editor, anuncios y onboarding (datos remotos) |
| elementor.com (raíz, `/help`, `/pro`) | 9 | 1 | 10 | 1 | 1 | 0 | no |
| developers / angie / planner / composer.elementor.com | 1 / 0 / 3 / 0 | 0 | 0 / 4 / 0 / 0 | 1 | 0 | 0 | no |
| *.elementor.red (staging, no se ve) | 4 | 0 | 12 | 15 | 0 | 0 | — |
| youtube(-nocookie).com | 9 | 0 | 18 | 0 | 17 | 1 | sólo el vídeo del inicio rápido (datos + `identity.css:55-58`) |
| img.youtube.com / vimeo.com | 0 / 4 | 0 | 0 / 4 | 0 | 3 / 1 | 1 / 1 | no |
| wordpress.org/(plugins\|support/plugin)/elementor | 0 (sólo `E:readme.txt` y traducciones) | – | – | – | – | – | sí, en el pie (`identity.php:363`) |
| help.elementor.com, elementor.cloud, youtu.be de Elementor | 0 | – | – | – | – | – | — |
| proelements.org / github.com/proelements | 0 en código; sólo `P:license.txt:2`, `P:changelog.txt:1`, `P:DIGITALISIMO_CHANGES.md:172` (no se ven; la licencia GPL exige conservarlos) | | | | | | — |

Element Pack, Prime Slider y Hello Elementor: Elements no tiene referencias visibles. Hello sólo aparece en el onboarding (ya desactivado) y en el banner de conversión, que sólo sale sin PRO. Divi, Betheme, GeneratePress y Bricks: 0.

Muchos enlaces `go-pro-*`/`renew-*` del núcleo y de PRO están apagados porque `Utils::has_pro()` es verdadero y `P:license/api.php:104-141` da una licencia vitalicia con todas las funciones. Están al final, como higiene.

---

## A. Barra superior Editor One (todas las pantallas del constructor en el administrador)

Se pinta en `E:modules/editor-one/components/top-bar-handler.php:25-26,43-71` y su lógica está en `E:assets/js/editor-one-top-bar.js`. **Nada de esta barra lo cubre la identidad.**

- **A1. Icono «?» de ayuda** → `go.elementor.com/one-help-center-editor/`. Siempre visible.
  - Origen: `editor-one-top-bar.js:45234-45242`, botón en `:45416` (`data-test="header-help-button"`).
  - Retiro: CSS `#editor-one-top-bar [data-test="header-help-button"]{display:none!important}`.
  - Se pierde: la ayuda remota. Equivalente propio: ninguno (se puede enlazar a `?page=digitalisimo-elements-about`).
- **A2. «Novedades» (cajón con noticias y CTA).**
  - Origen: botón en `:36558` (`data-test="whats-new-button"`, dentro de un `MuiBadge`). Datos de la REST `elementor-one/v1/top-bar/notifications` (`E:vendor/elementor/wp-one-package/src/Admin/Controllers/TopBar.php:36-56,97-113`), que **pide siempre** a `my.elementor.com/api/v1/notifications` (`E:vendor/elementor/wp-notifications-package/src/V120/Notifications.php:22,55-84`, `force_request = true`).
  - El filtro `elementor/core/admin/notifications` de la identidad no pasa por aquí.
  - Retiro: `add_filter('rest_pre_dispatch', fn($r,$s,$q)=> '/elementor-one/v1/top-bar/notifications'===$q->get_route() ? new WP_REST_Response([]) : $r, 10, 3)`, más CSS `#editor-one-top-bar .MuiBadge-root:has([data-test="whats-new-button"]){display:none!important}`.
  - Se pierde: nada local.
- **A3. Usuario / «Connect» / «Go to my account» (inicio de sesión de Elementor One).**
  - Origen: `:33979` (`header-user-button`), `:33896` (`user-menu-go-to-account`, `window.open` a `MY_ELEMENTOR_URL` en `:15339`), conexión a `my.elementor.com/connect` (`E:vendor/elementor/wp-one-package/src/Admin/Config.php:16`).
  - Retiro: CSS sobre `[data-test="header-user-button"]` y sobre el divisor vertical que lo precede (`.MuiDivider-vertical:has(+ [data-test="header-user-button"])`).
  - Se pierde: conectar con Elementor One, que no aporta nada sin sus servicios.
- **A4. Botón «feedback»** (`:45393`, sólo con la cuenta conectada) **y «Upgrade»** (`:45221`, sólo si detecta el plugin `elementor` activo sin Pro, así que en la práctica no sale). CSS sobre `[data-test="header-feedback-button"]` y `[data-test="header-upgrade-button"]`. Sin pérdida.

## B. Barra superior del editor v4 (`E:assets/js/packages/editor-app-bar/editor-app-bar.js`)

Los menús aceptan reemplazar un elemento por id con `overwrite:true` y los componentes respetan `visible:false` (`E:assets/js/packages/menus/menus.js:50-70`, `E:assets/js/packages/locations/locations.js:146-150`, `editor-app-bar.js:108-125,162-164`). El orden de carga no importa: si el script propio llega antes, el original se rechaza; si llega después, lo reemplaza.

Retiro común: un script en línea después de `elementor-v2-editor-app-bar` que llame a `elementorV2.editorAppBar.mainMenu.registerLink({id, group, overwrite:true, useProps:()=>({visible:false, title:'', icon:()=>null})})` con el mismo id y grupo que el original. Para Angie se usa `toolsMenu.registerToggleAction`; para «What's New», `utilitiesMenu.registerLink`.

- **B1. «My Elementor» / «Connect my account».**
  - Origen: id `app-bar-connect`, grupo `exits` (`:883`). Con PRO activo `isActive=true` (`P:core/editor/editor.php:62`), así que **siempre muestra «My Elementor»** hacia `go.elementor.com/wp-dash-top-bar-account/` (`E:core/editor/loader/editor-scripts-settings.php:89`). `connect_url` está en `:79-88`.
  - La identidad lo convierte en «My DIGITALÍSIMO Elements», que **parece una cuenta propia y lleva a my.elementor.com**: es peor que dejarlo en inglés.
  - Retiro: el reemplazo anterior, más borrar `user.top_bar.my_elementor_url` y `connect_url` en `P:core/editor/editor.php::localize_settings`.
  - Se pierde: el acceso directo a la cuenta de Elementor.
- **B2. «Help Center»**: id `open-help-center`, grupo `help` (`:1360`), URL `go.elementor.com/editor-top-bar-learn/` (`:1322`). Mismo retiro. Equivalente propio: ninguno.
- **B3. «What's New»**: id `app-bar-menu-item-whats-new` en `utilitiesMenu` (`E:assets/js/editor-notifications.js:1563-1564`). La identidad vacía el contenido (`identity.php:58`), pero **el icono queda**. Si llegara contenido, sus vídeos se insertan en `editor-notifications.js:1041`. Mismo retiro.
- **B4. «Angie»** (promoción del agente IA de Elementor).
  - Origen: id `toggle-angie` en `toolsMenu` (`:813`); visible mientras Angie no esté instalado (`:788`). Tarjeta con «Learn more» `go.elementor.com/angie-learn-more` e imagen de `assets.elementor.com` (`:395-398`).
  - Retiro: `registerToggleAction` con `overwrite:true` y `visible:false`.
  - Se pierde: sólo la promoción.
- **B5. «Send Feedback»**: id `open-send-feedback` (`:1272`), sólo con el experimento `in_editor_feedback`. Su ventana ofrece «Connect to Elementor» (`:640-700`). Retiro: `pre_option_elementor_experiment-in_editor_feedback` → `'inactive'`, o el mismo reemplazo.

## C. «Need Help» del panel del editor (widgets, Ajustes del sitio, menú)

- **Origen:**
  - Plantillas `E:includes/editor-templates/panel.php:29-36` (menú, `needHelpUrl`) y `:111-118` (widget, `help_url`); `E:core/kits/views/panel.php:9-16` (pestañas del kit, `helpUrl`).
  - URL de los widgets: `E:includes/base/element-base.php:268-270`, que se asigna sólo si la clase está en el espacio `Elementor\` o `ElementorPro\` (`:1510-1514`). Afecta a **todos los widgets del núcleo y de PRO**; los propios `Digitalisimo\Elements\` no tienen enlace.
  - El contenedor fija su enlace en JS (`E:assets/js/editor.js:17841`).
  - Kit: `E:core/kits/documents/kit.php:72` y 12 pestañas en `E:core/kits/documents/tabs/*.php` (`get_help_url`).
  - PRO: `P:modules/woocommerce/settings/settings-woocommerce.php:40-41`, `P:modules/woocommerce/widgets/checkout.php:41-42`, `P:modules/woocommerce/widgets/notices.php:38-39`.
- Cubre: No.
- **Retiro:** CSS en `identity.css` → `#elementor-panel__editor__help{display:none!important}`. Es lo único que cubre también el enlace del contenedor en JS y los documentos que se cargan por AJAX. Opcionalmente, en PRO: `get_help_url(){return '';}`.
- Se pierde: el enlace a la documentación de Elementor de cada widget o pestaña. Equivalente propio: no existe.

## D. Vídeos que se ven

- **D1. Vídeo por defecto del widget Vídeo (promoción de Elementor `XHOmBV4js_E`).** Se ve en el editor, la vista previa y **el sitio público** si el usuario no cambió la URL.
  - Origen: `E:includes/widgets/video.php:182` (YouTube), `:207` (Vimeo `235215203`), `:231` (Dailymotion `x6tqhqb`), `:305` (VideoPress `ZCAOzTNk`); YouTube atómico v4 `E:modules/atomic-widgets/elements/atomic-youtube/atomic-youtube.php:61`; PRO Video Playlist `P:modules/video-playlist/widgets/video-playlist.php:168,414-428` (más miniaturas de img.youtube.com). El placeholder del fondo de vídeo (`E:includes/controls/groups/background.php:527`) es sólo texto de ejemplo.
  - Cubre: No.
  - Retiro: **no cambiar el `default` a secas** (ver Riesgo 2). Para el núcleo, al crear un widget nuevo se fija la URL vacía o propia: hook JS de `$e` sobre `document/elements/create`, o *Element defaults* del kit. El `default` sólo puede cambiarse con `elementor/element/video/section_video/before_section_end` + `update_control` **junto con** una migración que escriba el valor antiguo en los documentos que dependen de él. En PRO (Video Playlist) se edita en origen con el mismo cuidado.
  - Se pierde: un vídeo de muestra. Equivalente: un vídeo propio o el campo vacío.
- **D2. Theme Builder (App → Theme Builder → botón «i» de cada parte): ventana con iframe de YouTube y «Learn More».**
  - Origen: datos PRO `tooltip_data.video_url` y `docs` en `P:modules/theme-builder/documents/header.php:44-45`, `footer.php:43-44`, `single-post.php:31-32`, `single-page.php:34-35`, `archive.php:42-43`, `search-results.php:39-40`, `error-404.php:35-36`, `P:modules/woocommerce/documents/product.php:47-48`, `product-archive.php:52-53`, `P:modules/loop-builder/documents/loop.php:66-67`.
  - El componente está en `E:assets/js/app-packages.js:5141-5165` y pinta el iframe `#ytplayer` **aunque no haya URL**. Hay datos de respaldo con vídeos en `:5287-5395`.
  - Cubre: No.
  - Retiro: vaciar `docs` y `video_url` en PRO, más CSS `.e-site-part__info-toggle{display:none!important}`. Se puede conservar la ventana con «Tip» ocultando sólo `#ytplayer` y el botón de enlace.
  - Se pierde: tutoriales de Elementor. Sin equivalente.
- **D3. Inicio rápido** (`E:assets/js/e-home-screen.js:2632`): **cubierto** (`identity.php:260`, `identity.css:55-58`).
- **D4. Páginas promocionales del administrador con YouTube** (`E:modules/promotions/admin-menu-items/editor-one-{fonts,icons,custom-code,submissions}-menu.php:62/66/62/72`): sólo salen sin PRO. No se ven.
- **D5. Onboarding nuevo** (vídeos de `assets.elementor.com/onboarding/v1/videos/`, `E:assets/js/packages/onboarding/onboarding.js:1600`): **cubierto** por `pre_option_elementor_onboarded`.

## E. Inicio de sesión y servicios en la nube de Elementor

- **E1. Biblioteca de plantillas del editor.**
  - Pantalla «Connect» con el botón hacia `my.elementor.com` (`E:includes/editor-templates/templates.php:740-770`).
  - Distintivos de conexión al guardar en la nube (`:546-561`, `:642-660`; `library_connect_url` en `E:modules/cloud-library/module.php:106`).
  - Pie «Template Library» → `go.elementor.com/docs-library/` (`:595,702,718,732`).
  - «Learn more» del gestor de variables → `elementor.com/help/variables-manager/` (`:841`).
  - Cubre: No.
  - Retiro:
    - CSS `.elementor-template-library-blank-footer-link`, `#elementor-template-library-connect__badge*`, `.connect-badge`.
    - Para quitar el catálogo remoto: `Plugin::$instance->templates_manager->unregister_source('remote')` y `('cloud')` (`E:includes/template-library/manager.php:149`), y ocultar las pestañas Bloques/Páginas.
  - Se pierde: **el catálogo gratuito de bloques y páginas de Elementor y las plantillas en la nube**. Queda «Mis plantillas» locales.
- **E2. App de kits (Website Templates).**
  - «Connect & Activate»: `P:core/app/modules/kit-library/module.php:46-52`, con `is_library_connected` fijo en `false`.
  - «What are kits» ×10 (`E:assets/js/app.js:6352…19783`), Envato (`:20965`), «support-center» ×10 (diálogos de error), go-pro de la nube (`:23955-24474`).
  - Cubre: No.
  - Retiro: `elementor/editor-one/menu/excluded_level3_slugs` para quitar «Website Templates» del menú, ocultar la ruta `#/kit-library`, y CSS `a[href*="elementor.com"]` dentro de `#e-app`.
  - Se pierde: el catálogo de kits de Elementor. Importar y exportar kits locales sigue funcionando.
- **E3. IA de Elementor** (botones IA en controles, biblioteca de medios, Gutenberg, productos de WooCommerce).
  - Origen: `E:assets/js/ai*.js`, con 21 URL distintas cada uno: términos, privacidad, `go-pro-ai`, «disconnecting-reconnecting» de elementor.com/help.
  - Cubre: sólo renombra a «IA».
  - Retiro: `add_filter('get_user_option_elementor_enable_ai', fn()=>'0')`. Lo lee `E:modules/ai/preferences.php:41-42` y lo usa `E:modules/ai/module.php:167-173`. Debe registrarse antes de que el núcleo construya sus módulos. Ocultar también la sección «Elementor - AI» del perfil (`preferences.php:57-76`).
  - Se pierde: toda la IA del editor, que exige una cuenta de Elementor. Equivalente propio posible: Digitalisimo AI.
- **E4. Página «Connect»** (`?page=elementor-connect`, `E:core/common/modules/connect/admin.php:17`, oculta) y las apps de `E:core/common/modules/connect/apps/base-app.php:23-25`. Sin enlaces directos si se quitan E1–E3.
- **E5. Site Planner**: página oculta `e-site-planner-password-generator` (`E:modules/ai/site-planner-connect/module.php:11,29-37`). Entrega una contraseña de aplicación a `planner.elementor.com` y lleva el logotipo de Elementor. Retiro: `remove_action('admin_menu', …, 100)` o `admin_init` que bloquee esa página. Se pierde: nada.

## F. Promociones de Angie y del «widget personalizado»

- **Categoría «Custom Widget» con «Try for free»** en el panel de elementos: `E:modules/widget-creation/module.php:63-110`. **Paquete `editor-widget-creation`** con imagen de assets.elementor.com y enlaces a `elementor.com/terms/angie-terms-conditions/` y `/about/privacy/` (`E:assets/js/packages/editor-widget-creation/editor-widget-creation.js:68,162,166`). Promoción en `E:modules/widget-creation/angie-promotion.php:18`.
  - Retiro: filtro `elementor/editor/v2/packages` para quitar `editor-widget-creation`, y `remove_action('elementor/elements/categories_registered', [módulo, 'maybe_register_custom_widgets_category_fallback'], 100)` con el módulo de `modules_manager->get_modules('widget-creation')`.
- **Componentes (PRO, compilado)**: estado vacío con Angie e imagen (`P:assets/js/packages/editor-components-extended/editor-components-extended.js:1994,2088`) y «components-guide» (`:89,2201`). En el núcleo, `E:assets/js/packages/editor-components/editor-components.js:556`. Se edita en origen en PRO.
- `angie.elementor.com` en `E:assets/js/packages/editor-mcp/editor-mcp.js:8809…9352`: sólo actúa con Angie instalado.

## G. Pantallas del administrador con «Learn more» hacia Elementor

Cubre: No en todos. Salvo que se indique otra cosa, se retiran con el respaldo de CSS por dominio (sección M) o en origen en PRO.

- **Ajustes → General**, uso compartido de datos: `E:includes/settings/settings-page.php:398`.
- **Ajustes → Funciones (experimentos)**: `E:core/experiments/manager.php:346,362,377,379,390,563,571`.
- **Ajustes → Editor V4** (opt-in): ayuda, feedback e imágenes de assets.elementor.com en `E:assets/js/editor-v4-opt-in.js:3196-3213`. Mismo tipo de contenido en el editor: `E:assets/js/packages/v4-activation-modal/v4-activation-modal.js:11549-11573`, `E:assets/js/packages/unlock-v4-promo/unlock-v4-promo.js:67-68`, `E:assets/js/editor-v4-opt-in-alphachip.js:786-791`.
- **Ajustes → Importar/Exportar**: `E:app/modules/import-export/module.php:205`, `E:app/modules/import-export-customization/module.php:212`.
- **Ajustes → Markdown**: `E:modules/markdown-render/module.php:45`.
- **Herramientas → Reemplazar URL** (copia de seguridad): `E:includes/settings/tools.php:345`.
- **Herramientas → Modo seguro**: `E:modules/safe-mode/module.php:20-23,359,368`.
- **Beta tester** (boletín de Elementor): `E:includes/beta-testers.php:18-20`. Retiro: `add_filter('elementor/admin/show_beta_tester','__return_false')` (`tools.php:483`).
- **Información del sistema**: `E:modules/system-info/reporters/server.php:173`, `theme.php:208`.
- **Element Manager**: `E:assets/js/element-manager-admin.js:2496`.
- **PRO Custom Fonts**, fuentes variables: `P:modules/assets-manager/asset-types/fonts/custom-fonts.php:117`.
- **PRO Custom Code**: `P:modules/custom-code/module.php:402` y `P:assets/js/custom-code.js:1114` (compilado PRO).
- **PRO Ajustes → Integraciones, Stripe**: `P:modules/payments/module.php:21-22`.
- **Plugin MU «Elementor Safe Mode»** (lista de Must-Use, con el modo seguro activo).
  - Muestra Plugin URI y Author URI `elementor.com` y «Learn More» (`E:modules/safe-mode/mu-plugin/elementor-safe-mode.php:5,8,103-110`).
  - Es una copia que crea Elements, no el plugin original: la regla de no tocarlo no aplica.
  - Retiro: `plugin_row_meta` con prioridad mayor que 10 para `elementor-safe-mode.php`, quitando los enlaces con `elementor.com`.
- **Página MCP**, «Learn more»: `E:vendor/elementor/elementor-mcp-composer/assets/build/mcp-page.js:102`.

## H. Avisos del administrador

Origen: `E:core/admin/admin-notices.php`. Hay una lista fija (`:24-33`) y se pinta un aviso por pantalla (`:776-800`, enganchado en `:857`).

- **Opt-in de datos** con «Learn more» (`:191-230`). Retiro: `pre_option_elementor_tracker_notice` → `'1'` (`:201`).
- **«Rate us»** hacia `go.elementor.com/admin-review/` (`:295-322`).
- **`api_notice` / `api_upgrade_plugin`**, remotos desde `my.elementor.com/api/v2/info` (`E:includes/api.php:42,58-112,144-160`).
- **Promociones de Ally e Image Optimizer** (`:419-510`, `:567+`).
- `role_manager_promote`: no sale con PRO.

Retiro global: `remove_action('admin_notices', [ Plugin::$instance->admin->get_component('admin-notices'), 'admin_notices' ], 20)`.
Se pierde: también los avisos que otros complementos añaden con `elementor/core/admin/notices`.

## I. Sugerencias en controles («Hints»)

Instalar Image Optimizer y Ally, plugins de Elementor desde wordpress.org.
- Origen: `E:core/utils/hints.php:54-90,252`; `E:includes/controls/media.php:275,338,377`; `E:includes/controls/gallery.php:126-183`; `E:includes/widgets/heading.php:495-552`; `E:core/admin/admin.php:1122-1135` (`media-hints`).
- Retiro: filtro `get_user_metadata` para la clave `elementor_dismissed_editor_notices` (`E:includes/user.php:57,395`) que añada todas las claves de `get_hints()`.
- Se pierde: nada propio.

## J. Checklist (launchpad) del editor

- «Learn more» en `E:modules/checklist/steps/{add-logo:49, create-pages:44, set-fonts-and-colors:55, assign-homepage:47, setup-header:86,92, step-base:106}.php`, en PRO `P:modules/checklist/steps/setup-header.php:18`, e imágenes de assets.elementor.com.
- Retiro: `pre_option_elementor_experiment-launchpad-checklist` → `'inactive'` (`E:modules/checklist/module.php:21,50-52`; prefijo en `E:core/experiments/manager.php:36`).
- Se pierde: la guía de primeros pasos.

## K. Finder

- «Knowledge Base» → `go.elementor.com/docs-admin-menu/` (`E:core/common/modules/finder/categories/general.php:66-70`).
- «Add-ons» → `?page=elementor-apps`, una página que en 4.3.5 ya no está registrada (`E:modules/apps/module.php:21-30`; si llegara a pintarse: `E:modules/apps/admin-apps-page.php:14,27`).
- Retiro: filtro `elementor/finder/categories`, quitando `['general']['items']['knowledge-base']` y `['site']['items']['apps']`.

## L. Descripciones, avisos y diálogos de error con enlace

URL que se pasan a la editora con `elementor/editor/localize_settings`: `E:core/editor/loader/editor-scripts-settings.php:58,66-71,89,93-95,123`. Se vacían en `P:core/editor/editor.php::localize_settings`.
- `elementor_site`, `docs_elementor_site`.
- `help_the_content_url`: usada en `E:assets/js/editor.js:54247`.
- `help_flexbox_bc_url`: usada en `editor.js:53892`.
- `preview.help_*`: errores de vista previa; también `E:core/debug/loading-inspection-manager.php:45` y `E:core/debug/classes/{htaccess:46, theme-missing:26, shop-page-edit:19}.php`.

Controles del núcleo (en `E:` no hay filtro genérico; se usa `update_control` en `elementor/element/{widget}/{section}/before_section_end` o el respaldo de CSS):
- `E:includes/widgets/video.php:604` (preload).
- `E:includes/elements/section.php:471`.
- `E:includes/controls/url.php:76`.
- `E:modules/shapes/widgets/text-path.php:148`.
- `E:assets/js/editor.js:53362` (caché de elementos).
- `E:assets/js/packages/editor-controls/editor-controls.js:10514` y `E:assets/js/packages/editor-canvas/editor-canvas.js:9457` (link-in-link).
- `E:assets/js/packages/editor-design-system/editor-design-system.js:369`.
- `E:assets/js/packages/editor-site-navigation/editor-site-navigation.js:344` («Notify support»).
- `E:modules/history/views/revisions-panel-template.php:32`.

PRO (se edita en origen):
- `P:modules/custom-css/module.php:139-140`, `P:modules/sticky/module.php:157`, `P:modules/mega-menu/module.php:54`, `P:modules/off-canvas/widgets/off-canvas.php:110`, `P:modules/forms/classes/akismet.php:55`.
- Fechas: `P:modules/dynamic-tags/tags/{post-time:64, post-date:66, current-date-time:72}.php`, `P:modules/dynamic-tags/{toolset/tags/toolset-date:101, pods/tags/pods-date:82}.php`. Se pueden apuntar a la documentación de fechas de wordpress.org.
- Compilados: `P:assets/js/display-conditions.js:1932,1977`; Notas en `P:modules/notes/module.php:146` y `P:assets/js/notes/notes-app-initiator.js:13206`.
- Errores del Theme Builder: `P:assets/js/app.js:1773,2565,2634,2709`, con fuentes en `P:core/app/modules/site-editor/assets/js/{site-editor.js:48, pages/import.js:63, pages/conditions/conditions-rows.js:71, organisms/site-templates.js:75}`.

## M. Respaldo de CSS y JS por dominio

Agregar a `identity.css`, que ya se carga en el administrador, el editor y la App, pero no en el lienzo de vista previa:
- `a[href*="go.elementor.com"], a[href*="my.elementor.com"], a[href*="//elementor.com"], a[href*="angie.elementor.com"]{display:none!important}`, excluyendo `#the-list tr[data-plugin^="elementor/"]`, `tr[data-plugin^="elementor-pro/"]` y la ventana de información de plugins.
- En JS, una guarda de `window.open` para esos dominios, porque algunos accesos abren la URL desde un `onClick`.
- En `P:tests/playground/brand-audit/run.cjs`: fallar si queda visible un `a[href*="elementor.com"]` o un `iframe[src*="youtube"]` en las pantallas del constructor. Hoy sólo revisa texto, logotipos y colores.

## N. Correos y webhooks

- Discord: pie «Powered by Elementor», con el literal sin traducción (no lo cubre la identidad), y el logotipo `logo-icon.png` de Elementor (`P:modules/forms/actions/discord.php:196-204`). Slack ya dice DIGITALÍSIMO, pero conserva ese mismo logotipo (`P:modules/forms/actions/slack.php:228-233`). Se edita en PRO.
- `P:modules/atomic-form/classes/metadata-resolver.php:90-91` y `P:modules/forms/classes/form-record.php:269-271` ya salen con la marca.

## O. Código apagado (higiene opcional, en PRO)

Sólo sale sin PRO o sin licencia: `P:license/api.php:23`, `P:core/behaviors/feature-lock.php:35`, `P:modules/editor-one/module.php:18`, `P:core/admin/action-links.php:22` (no se usa), los menús de promoción de PRO (`popups-`, `submissions-`, `custom-fonts-`, `custom-icons-promotion-menu-item.php`), `custom-attributes:178`, `global-widget:219`, `custom-css:54`, `settings-custom-css-pro:16`, `role-manager:108-112`, `woocommerce settings:854,871`, `atomic-form-panel-promotion:21-23`, `P:assets/js/admin.js:89-92`, `P:assets/js/editor.js:1177,4162`. Más todo `E:modules/promotions/*` y `E:modules/pro-install/*`, que sólo se cargan sin PRO.

---

## Riesgos

1. **Regla del plugin original.** Un respaldo por CSS o JS no puede ocultar enlaces de las filas `elementor/elementor.php` ni `elementor-pro/elementor-pro.php`, ni de la ventana de información o la pantalla de actualizaciones: hay que acotar los selectores.
2. **Cambiar el `default` del vídeo cambia sitios en uso.** Elementor no guarda los valores por defecto, así que una página con el vídeo de muestra mostraría otro vídeo o ninguno. Para PRO (repetidor de Video Playlist) hay que comprobar antes si se guarda.
3. **Ocultar no es desconectar.** El CSS quita la interfaz, pero las peticiones siguen: novedades de Editor One, información remota, tracker, configuración de Mixpanel. Si además se bloquea con `pre_http_request`, se rompen la biblioteca remota, los kits, la nube y la IA.
4. **Retirar el inicio de sesión es retirar funciones.** Se pierden la biblioteca de bloques y páginas, los kits remotos, las plantillas en la nube y la IA. No hay equivalente propio hoy. Hay que actualizar el texto de «Acerca de» en `P:digitalisimo-brand.php:72`, que dice que esos servicios «pueden requerir una cuenta del proveedor».
5. **La marca engaña en los accesos de cuenta.** «My/Connect to DIGITALÍSIMO Elements» lleva a my.elementor.com. Hay que quitar esos accesos, no dejarlos renombrados.
6. **Selectores frágiles.** `data-test`, la estructura MUI, los ids de los menús y `#ytplayer` pueden cambiar con cada sincronización del núcleo. La auditoría de marca debe vigilar los dominios.
7. **Experimentos.** `pre_option_elementor_experiment-*` no tiene efecto si el experimento es `mutable=false` o cambia de estado; hay que verificarlo con cada versión.
8. **Avisos.** Quitar `admin_notices` del módulo también quita los avisos de complementos de terceros.
9. **Compilados de PRO.** Hay que mantener iguales `.js` y `.min.js`, y las fuentes de `core/app/.../assets/js`.
10. **Momento de registro.** `get_user_option_elementor_enable_ai` debe registrarse antes de que el núcleo construya sus módulos.

## Lista priorizada

1. **A1–A4** (barra Editor One en todas las pantallas): CSS y `rest_pre_dispatch` de notificaciones.
2. **B1–B5** (barra del editor): un script con `overwrite:true` y `visible:false`, más vaciar `my_elementor_url` y `connect_url` en `P:core/editor/editor.php`.
3. **C** (todos los «Need Help»): una regla de CSS.
4. **D2** (vídeos y documentación del Theme Builder): vaciar en PRO y añadir CSS.
5. **D1** (vídeo de muestra de Elementor): fijar la URL sólo en widgets nuevos y planear la migración del `default`.
6. **E3** (IA): `get_user_option_elementor_enable_ai`. **E1/E2** (inicio de sesión de plantillas y kits): ocultar la conexión y decidir si se quitan las fuentes remotas.
7. **F** (Angie y «Custom Widget»): quitar el paquete y la categoría.
8. **H** (avisos y tracker), **J** (checklist), **K** (Finder), **I** (sugerencias de plugins).
9. **L y G** (descripciones y «Learn more» de PRO en origen; del núcleo con `update_control` o CSS), **N** (Discord y Slack).
10. **M** (respaldo por dominio y regla en `brand-audit`), luego **O** (higiene).
