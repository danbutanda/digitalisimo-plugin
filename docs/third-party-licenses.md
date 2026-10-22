# Licencias de referencia para DIGITALÍSIMO Elements

La copia local `reference/bdthemes-element-pack/` se utiliza sólo para análisis. Su cabecera declara `GPL3` y contiene un archivo `LICENSE` GPLv3. La presencia de esos archivos permite estudiar el código, pero no autoriza asumir automáticamente la procedencia ni licencia individual de todos los iconos, imágenes, fuentes, plantillas y bibliotecas incluidas.

| Componente | Estado | Acción antes de publicarlo |
| --- | --- | --- |
| Código PHP de Element Pack | Referencia local; no incluido en runtime ni ZIP | Si se reutiliza código directamente, registrar archivo, procedencia, avisos de copyright, cambios y fuente correspondiente. |
| Código de PRO Elements ya integrado | Distribuido como derivado GPLv3 | Mantener `COPYING`, `license.txt` y `DIGITALISIMO_CHANGES.md`. |
| Elementor (núcleo gratuito) en `digitalisimo-elements/elementor/` | Distribuido sin cambios, GPL-3.0-or-later, © Elementor Ltd.; traducciones es_* de translate.wordpress.org (GPL) | Sincronizar sólo con `scripts/sync-elementor-core.sh` (sumas SHA-256 oficiales), conservar su `license.txt` y el manifiesto `digitalisimo-core.json`; ver [ELEMENTOR_CORE.md](ELEMENTOR_CORE.md). |
| UIkit de Element Pack | Referencia; exclusión prevista | Reconstruir sólo las funciones necesarias con código propio. |
| Swiper | Candidato a motor compartido | Confirmar versión, licencia, fuente y compatibilidad antes de reutilizar un archivo externo. |
| Iconos, imágenes, fuentes, plantillas y otros assets | Licencia individual pendiente de auditar | No trasladar a un widget ni a un ZIP hasta documentar origen y permiso de distribución. |
| Administración, activación y licenciamiento de Element Pack | Excluidos | No copiar ni ejecutar. |

## Librerías de terceros servidas desde el plugin

Desde la etapa F-front (Fase 2 del front, [ELEMENTS_FRONT_OBJECTIVE.md](ELEMENTS_FRONT_OBJECTIVE.md)) el widget Resaltado de código no pide nada a un CDN: Prism y clipboard.js se distribuyen sin cambios en `digitalisimo-elements/assets/lib/prism/`, con los mismos bytes que antes servía cdnjs (comparados byte a byte y con sus sumas SRI el 21 de octubre de 2026, anotado en `MANIFEST.json`). Sólo se actualizan con `scripts/sync-prism.mjs`, que descarga el paquete oficial de npm, comprueba su `integrity` contra la fijada en el script y contra el registro, copia el subconjunto y escribe el manifiesto con el sha256 de cada archivo; `tests/elements-local-libs.php` lo comprueba.

| Componente | Versión | Autor | Origen | Licencia | Ruta distribuida | Aviso |
| --- | --- | --- | --- | --- | --- | --- |
| Prism | 1.23.0 | Lea Verou y colaboradores | Paquete npm `prismjs@1.23.0` (`registry.npmjs.org/prismjs/-/prismjs-1.23.0.tgz`, `sha512-c29LVsqO…bAA==`) | MIT | `assets/lib/prism/components/` (core y 36 gramáticas: las de los 37 valores del control `language`, con los alias del autoloader, más el cierre de sus `require`) y `assets/lib/prism/plugins/` (autoloader, normalize-whitespace, line-numbers, line-highlight, toolbar y copy-to-clipboard), sólo los `.min.js`; sin CSS, que ya va en la hoja del widget | `assets/lib/prism/LICENSE` (© 2012 Lea Verou) |
| clipboard.js | 2.0.0 | Zeno Rocha | Paquete npm `clipboard@2.0.0` (`registry.npmjs.org/clipboard/-/clipboard-2.0.0.tgz`, `sha512-gXzHBlzE…E3A==`), archivo `dist/clipboard.min.js` | MIT | `assets/lib/prism/clipboard/clipboard.min.js`: el plugin copy-to-clipboard de Prism 1.23.0 lo pedía a cdnjs cuando `window.ClipboardJS` no existía; ahora se registra (`prismjs_clipboard`) como dependencia de ese plugin | `assets/lib/prism/clipboard/LICENSE` (texto MIT del proyecto: el paquete declara MIT en `package.json` y en la cabecera del archivo, pero no trae LICENSE) |

Prism 1.23.0 tiene avisos de seguridad publicados (al menos CVE-2024-53382, corregido en 1.30.0). Servirlo desde el plugin no aumenta la exposición frente a pedirlo a cdnjs, que daba los mismos bytes; la subida de versión queda como etapa propia. Un lenguaje que un tercero añada con el filtro `elementor_pro/code_highlight/languages` y que no esté en el subconjunto queda sin resaltar.

## Primer componente reconstruido: Animated Link

`digitalisimo-elements/modules/digitalisimo-widgets/class-animated-link.php` reconstruye controles y salida con clases de Elementor. Hasta 4.3.0.129 sus tres trazos SVG y `assets/css/animated-link.css` se habían adaptado del módulo Animated Link de Element Pack Pro 9.9.1 (GPLv3). Desde 4.3.0.130 ambos son implementación propia escrita desde cero: hoja con variables CSS y keyframes propios, y trazos (onda, arco y garabato) dibujados con coordenadas propias. Se conservan sólo los valores guardados de las quince variantes (`metis`, `io`, `leda`…) para que los documentos existentes sigan mostrando el mismo efecto; una comparación de capturas en Chrome, en reposo y al pasar el cursor, confirmó el mismo efecto en las quince salvo el dibujo de los tres trazos. Ya no se distribuye código de Element Pack.

El inventario estático no sustituye la revisión de licencias archivo por archivo. Cada nueva dependencia publicada debe añadirse aquí con versión, autor, URL de origen, licencia, ruta distribuida y archivo de aviso cuando corresponda.

## Segundo componente reconstruido: Fancy List

`digitalisimo-elements/modules/digitalisimo-widgets/class-fancy-list.php` y `digitalisimo-elements/assets/css/fancy-list.css` se escribieron para reproducir la función básica del módulo `modules/fancy-list/widgets/fancy-list.php` de Element Pack Pro 9.9.1 (GPLv3), sin copiar su clase, trait de controles ni CSS, y sin incluir imágenes, iconos o librerías de la referencia. Los nombres de varios controles se conservan para facilitar una futura conversión explícita de documentos; aún no se registra el identificador antiguo.

## Tercer componente reconstruido: Document Viewer

`digitalisimo-elements/modules/digitalisimo-widgets/class-document-viewer.php` y `digitalisimo-elements/assets/css/document-viewer.css` se escribieron como reconstrucción funcional del módulo `modules/document-viewer/widgets/document-viewer.php` de Element Pack Pro 9.9.1 (GPLv3). No se incluyen sus clases, recursos gráficos ni librerías. El modo opcional de Google Docs carga un servicio externo elegido por el administrador; no incorpora su código al paquete.

## Cuarto componente reconstruido: Brand Carousel

`class-brand-carousel.php`, `class-carousel-engine.php`, `brand-carousel.css`, `carousel-engine.css` y `carousel-engine.js` son implementación propia de la función principal del módulo `modules/brand-carousel/widgets/brand-carousel.php` de Element Pack Pro 9.9.1 (GPLv3). No se copia la clase, los estilos, las bibliotecas UIkit/Swiper, los iconos ni las imágenes de la referencia. La mención del módulo original identifica la procedencia funcional; la equivalencia completa permanece pendiente.

## Quinto componente reconstruido: Logo Carousel

`class-logo-carousel.php` y `logo-carousel.css` son implementación propia de selección y presentación de logotipos inspirada en `modules/logo-carousel/widgets/logo-carousel.php` de Element Pack Pro 9.9.1 (GPLv3). Reutilizan sólo el motor propio descrito arriba. No se incluyen los SVG de ejemplo, UIkit, Swiper, Tippy ni archivos de la referencia.

## Sexto componente reconstruido: Advanced Heading

`class-advanced-heading.php` y `advanced-heading.css` son una implementación propia de la función básica de `modules/advanced-heading/widgets/advanced-heading.php` de Element Pack Pro 9.9.1 (GPLv3). Sólo se conserva el nombre de controles funcionales que facilitaría una conversión posterior; no se copian su clase, CSS, iconos ni efectos avanzados. El ID antiguo no se registra y la equivalencia visual está pendiente.

## Séptimo componente reconstruido: Brand Grid

`class-brand-grid.php` y `brand-grid.css` son implementación propia de la función principal de `modules/brand-grid/widgets/brand-grid.php` de Element Pack Pro 9.9.1 (GPLv3). No se copian su clase, traits, CSS, iconos ni recursos. La cuadrícula utiliza elementos semánticos y CSS nativo; el ID anterior continúa sin registrarse.

## Octavo componente reconstruido: Logo Grid

`class-logo-grid.php` y `logo-grid.css` son implementación propia inspirada en `modules/logo-grid/widgets/logo-grid.php` de Element Pack Pro 9.9.1 (GPLv3). No se incluyen su clase, traits, estilos, máscaras, Tippy, Popper ni recursos gráficos. El ID anterior permanece sin registrar mientras se comprueba la equivalencia funcional y visual.

## Noveno componente reconstruido: Scroll Button

`class-scroll-button.php`, `scroll-button.css` y `scroll-button.js` son implementación propia de la función principal de `modules/scroll-button/widgets/scroll-button.php` de Element Pack Pro 9.9.1 (GPLv3). No se incluyen su clase, traits, CSS, JavaScript ni efectos; el nuevo widget usa un enlace nativo y no registra el ID anterior.

## Décimo componente reconstruido: Accordion

`class-accordion.php` y `accordion.css` son implementación propia inspirada en `modules/accordion/widgets/accordion.php` de Element Pack Pro 9.9.1 (GPLv3). Utilizan elementos HTML de divulgación nativos; no incorporan clases, traits, estilos, scripts ni UIkit del original. El ID antiguo continúa libre mientras se verifica la paridad.

## Undécimo componente reconstruido: Advanced Button

`class-advanced-button.php` y `advanced-button.css` son implementación propia de un botón con icono y distintivo inspirada en `modules/advanced-button/widgets/advanced-button.php` de Element Pack Pro 9.9.1 (GPLv3). Sus nueve efectos se expresan mediante CSS nuevo y ligero. No se copia la clase, su hoja CSS, UIkit ni otros recursos de la referencia. El ID antiguo permanece libre hasta comprobar paridad funcional y visual.

## Duodécimo componente reconstruido: Advanced Divider

`class-advanced-divider.php` y `advanced-divider.css` implementan formas decorativas propias tras estudiar `modules/advanced-divider/widgets/advanced-divider.php` de Element Pack Pro 9.9.1 (GPLv3). No distribuyen sus SVG, hoja CSS, JavaScript, UIkit ni infraestructura. La imagen opcional se toma de la biblioteca de Medios del sitio. El ID antiguo permanece libre y la equivalencia completa está pendiente.

## Extensión editorial reconstruida: Duplicator

`digitalisimo-elements/digitalisimo-duplicator.php` es implementación propia de la función editorial observada en `includes/class-duplicator.php` de Element Pack Pro 9.9.1 (GPLv3). No copia su clase, consultas SQL, loader, ajustes ni otros archivos. Usa las APIs de WordPress, restringe tipos y permisos, y no incorpora recursos externos. Conserva datos de Elementor sin copiar sus cachés temporales.

## Decimotercer componente reconstruido: Advanced Icon Box

`class-advanced-icon-box.php` y `advanced-icon-box.css` son una implementación propia de la función principal de `modules/advanced-icon-box/widgets/advanced-icon-box.php` de Element Pack Pro 9.9.1 (GPLv3). No se copiaron la clase, los efectos, CSS ni JavaScript de referencia. El widget nuevo no registra el ID anterior y mantiene pendientes los controles avanzados y la equivalencia visual.

## Decimocuarto componente reconstruido: Animated Heading

`class-animated-heading.php`, `animated-heading.css` y `animated-heading.js` son una implementación propia de rotación progresiva tras estudiar `modules/animated-heading/widgets/animated-heading.php` de Element Pack Pro 9.9.1 (GPLv3). No se copiaron su clase, CSS, JavaScript, UIkit, Typed.js, Morphext ni GSAP. El ID antiguo sigue libre y la paridad de sus modos avanzados está pendiente.

## Decimoquinto componente reconstruido: Breadcrumbs

`class-breadcrumbs.php` y `breadcrumbs.css` son una implementación propia tras estudiar `modules/breadcrumbs/widgets/breadcrumbs.php` de Element Pack Pro 9.9.1 (GPLv3). No se copió su clase, CSS, UIkit ni otros recursos. El widget Breadcrumbs ya incluido en la base derivada de PRO Elements depende de Yoast; esta variante propia usa las APIs de WordPress y no reemplaza ese widget ni el ID de Element Pack.

## Decimosexto y decimoséptimo componentes reconstruidos: Dual Button y Call Out

`class-dual-button.php`, `dual-button.css`, `class-call-out.php` y `call-out.css` son implementaciones propias tras estudiar `modules/dual-button/widgets/dual-button.php` y `modules/call-out/widgets/call-out.php` de Element Pack Pro 9.9.1 (GPLv3). No se copiaron sus clases, CSS, JavaScript, iconos, UIkit ni recursos gráficos. Los IDs antiguos siguen libres hasta verificar la paridad.

## Decimoctavo y decimonoveno componentes reconstruidos: Comparison List y Content Switcher

`class-comparison-list.php`, `comparison-list.css`, `class-content-switcher.php`, `content-switcher.css` y `content-switcher.js` son implementaciones propias tras estudiar los módulos homónimos de Element Pack Pro 9.9.1 (GPLv3). No se copiaron sus clases, CSS, JavaScript, UIkit ni recursos gráficos. Los IDs antiguos siguen libres hasta verificar la paridad.

## Vigésimo y vigesimoprimer componentes reconstruidos: Custom Gallery y Creative Button

`class-custom-gallery.php`, `custom-gallery.css`, `class-creative-button.php` y `creative-button.css` son implementaciones propias tras estudiar los módulos homónimos de Element Pack Pro 9.9.1 (GPLv3). No se copiaron sus clases, estilos, Tilt.js, UIkit, imágenes ni iconos. Los IDs antiguos siguen libres hasta verificar la paridad.

## Vigesimosegundo y vigesimotercer componentes reconstruidos: Device Slider y Fancy Card

`class-device-slider.php`, `device-slider.css`, `class-fancy-card.php` y `fancy-card.css` son implementaciones propias tras estudiar los módulos homónimos de Element Pack Pro 9.9.1 (GPLv3). No se copiaron sus clases, estilos, dispositivos gráficos, UIkit, imágenes ni iconos. Device Slider utiliza únicamente `Carousel_Engine` propio; los IDs antiguos siguen libres hasta verificar la paridad.

## Vigesimocuarto componente reconstruido: Fancy Icons

`class-fancy-icons.php` y `fancy-icons.css` son implementación propia tras revisar el módulo homónimo de Element Pack Pro 9.9.1 (GPLv3). Se conservaron los conceptos de repetidor, icono/texto, enlace y columnas; no se copiaron sus clases, estilos, fondos de video, UIkit ni iconos. `bdt-fancy-icons` sigue libre hasta validar la paridad.

## Vigesimoquinto componente reconstruido: Fancy Slider

`class-fancy-slider.php` y `fancy-slider.css` son implementación propia tras revisar el módulo homónimo de Element Pack Pro 9.9.1 (GPLv3). Reutiliza el `Carousel_Engine` propio; no copia transiciones, estilos, imágenes, UIkit ni scripts de la referencia. `bdt-fancy-slider` permanece libre mientras falten paridad visual y pruebas de runtime.

## Vigesimosexto componente reconstruido: Fancy Tabs

`class-fancy-tabs.php` y `fancy-tabs.css` son implementación propia tras revisar el módulo homónimo de Element Pack Pro 9.9.1 (GPLv3). Reutiliza el script propio de `Content_Switcher_Widget`; no copia la interfaz, los estilos, UIkit ni JavaScript de la referencia. `bdt-fancy-tabs` permanece libre mientras falten skins y pruebas de paridad.

## Vigesimoséptimo componente reconstruido: Featured Box

`class-featured-box.php` y `featured-box.css` son implementación propia tras estudiar el módulo homónimo de Element Pack Pro 9.9.1 (GPLv3). Ofrece diseños sobre imagen y dividido sin copiar los skins, máscaras, efectos, CSS, iconos ni scripts de la referencia. `bdt-featured-box` permanece libre mientras falten paridad visual y pruebas de runtime.

## Vigesimoctavo componente reconstruido: Google Reviews

`class-google-reviews.php`, `class-google-reviews-service.php`, `google-reviews.css` y `google-reviews.js` son implementación propia tras analizar el widget de Element Pack Pro 9.9.1 (GPLv3). No se reutilizan clases, assets ni servicios del original. Los datos de reseñas proceden de Google Places API (New), se consultan con una clave aportada por el administrador y se muestran con atribución a Google Maps conforme a sus [políticas](https://developers.google.com/maps/documentation/places/web-service/policies). No se redistribuyen ni almacenan reseñas. `bdt-google-reviews` permanece libre mientras falten paridad y pruebas de runtime.

## Vigesimonoveno componente reconstruido: Icon Mobile Menu

`class-icon-mobile-menu.php` y `icon-mobile-menu.css` son implementación propia tras analizar el widget de Element Pack Pro 9.9.1 (GPLv3). No se reutilizan CSS, JavaScript, iconos ni Popper/Tippy del original. El ID `bdt-icon-mobile-menu` permanece libre mientras falten paridad y pruebas de runtime.

## Trigésimo componente reconstruido: Icon Nav

`class-icon-nav.php` y `icon-nav.css` son implementación propia tras analizar el widget de Element Pack Pro 9.9.1 (GPLv3). No se reutilizan clases, CSS, JavaScript, iconos ni el walker del original. El panel del menú usa HTML nativo y funciones WordPress del sitio actual. `bdt-iconnav` permanece libre mientras falten paridad y pruebas de runtime.

## Trigésimo primer componente reconstruido: Notification

`class-notification.php`, `notification.css` y `notification.js` son implementación propia tras analizar el widget de Element Pack Pro 9.9.1 (GPLv3). No se reutilizan clases, CSS, JavaScript ni UIkit del original. `bdt-notification` permanece libre mientras falten paridad y pruebas de runtime.

## Product Grid y QR Code

`class-product-grid.php` y `product-grid.css` son implementación propia de fichas manuales tras revisar `modules/product-grid/widgets/product-grid.php` de Element Pack Pro 9.9.1. No se copian su clase, traits, CSS ni JavaScript. `bdt-product-grid` permanece libre.

`class-qr-code.php`, `qr-code.css` y `qr-code.js` son implementación propia tras revisar `modules/qrcode/widgets/qrcode.php`. El archivo `modules/digitalisimo-widgets/assets/js/third-party/qrcode-generator.min.js` es [qrcode-generator 1.4.4 de Kazuhiko Arase](https://github.com/kazuhikoarase/qrcode-generator), bajo licencia MIT, minificado con esbuild sin cambios de código; el aviso está en `modules/digitalisimo-widgets/assets/js/third-party/qrcode-generator.LICENSE`. Sustituye desde 4.3.0.153 a jquery-qrcode (Lars Jung, MIT), que exigía jQuery: el widget dibuja ahora un SVG sin dependencias. «QR Code» es marca registrada de DENSO WAVE INCORPORATED.

`class-table.php` y `table.css` son implementación propia de tabla CSV estática tras estudiar `modules/table/widgets/table.php` de Element Pack Pro 9.9.1. No se reutiliza su clase, CSS, DataTables, conector de Google Sheets, ACF ni otros assets. `bdt-table` permanece libre hasta comprobar paridad.

`class-tags-cloud.php` y `tags-cloud.css` son implementación propia tras revisar el módulo Tags Cloud de Element Pack Pro 9.9.1. No reutilizan clase, CSS, JavaScript ni assets externos; consultan taxonomías públicas con APIs de WordPress. `bdt-tags-cloud` permanece libre.

## Extensión reconstruida: Visibility Controls

`digitalisimo-elements/modules/digitalisimo-conditions/` es implementación propia de las reglas observadas en `modules/visibility-controls/` de Element Pack Pro 9.9.1 (GPLv3). No copia sus clases, su repetidor, su script de zona horaria ni su consulta de geolocalización: registra condiciones en el motor Display Conditions del derivado de PRO Elements (GPLv3) usando sus clases base y comparadores. Las listas de países (ISO 3166-1) e idiomas se generaron con `Intl.DisplayNames` de Node.js, que usa datos CLDR de Unicode (licencia Unicode, compatible), y se incluyen como nombres en español.

La compatibilidad de 4.3.0.82 (`class-legacy-rules.php` y `class-legacy-visibility.php`) reimplementa la lógica observada de cada regla de Visibility Controls para leer los ajustes que Element Pack ya guardó; conserva los nombres de ajustes, valores por defecto, nombres de cookies y filtros `element_pack_visibility_*` porque forman parte de esos datos. `countries-legacy.php` es una tabla de nombres de países en inglés (datos ISO 3166) derivada de la referencia para interpretar valores antiguos. No se copian clases, controles personalizados, UIkit ni otros recursos de Element Pack (GPLv3).

## Adaptadores de documentos de Element Pack

`digitalisimo-migration/legacy/` (plugin DIGITALÍSIMO Migración, desde 4.3.0.131 fuera de DIGITALÍSIMO Elements) permite leer y convertir documentos ya guardados con widgets de Element Pack Pro 9.9.1 (GPLv3). Los mapas son implementación propia. Los archivos `maps/bdt-*.styles.php` se generan con `scripts/legacy-styles.py` a partir de la copia local de referencia y contienen datos necesarios para interpretar esos documentos: nombres de ajustes, valores por defecto y plantillas CSS de sus controles, con los selectores reescritos al marcado propio. No incluyen clases, plantillas HTML, JavaScript, CSS base, UIkit ni imágenes de Element Pack; el derivado se distribuye bajo GPL y conserva esta atribución.

## Referencias de la fusión de constructores

Estado verificado el 16 y el 17 de octubre de 2026 leyendo los ZIP de `reference/` sin extraerlos (`unzip -p`), sin instalar ni ejecutar nada. Las copias de `reference/` sirven para leer y nunca se toman de ellas componentes de terceros (si alguno hiciera falta, se toma de su fuente oficial). Sólo se instala una copia cuya descarga oficial (origen y suma) esté anotada en `reference/OFFICIAL-SOURCES.txt`, y sólo en un Playground efímero de pruebas, sin licencias ni cuentas del proveedor; Betheme y Bricks nunca se instalan ni se ejecutan (regla completa en `AGENTS.md`, «Fusión de constructores»). El plan y las reglas están en [FUSION_OBJECTIVE.md](FUSION_OBJECTIVE.md); la evidencia depurada, en [fusion/](fusion/README.md).

| Producto | Versión | Licencia (fuente verificada) | Uso permitido | No se usa |
| --- | --- | --- | --- | --- |
| Divi (Elegant Themes) | 5.15.0 | `Divi/LICENSE.md`: GPL versión 2 «or (at your option) any later version». **Discrepancia:** la cabecera de `Divi/style.css` dice sólo «GNU General Public License v2». Se toma `LICENSE.md` como vigente (GPLv2 o posterior, compatible con la GPLv3 de Elements). Terceros en `Divi/CREDITS.md` (MIT, GPL, BSD y Apache 2.0) | Formatos de datos y tablas generadas a partir de `_all_modules_metadata.php`, `conversion-outline.json`, `module-default-*.json`, nombres y códigos de `iconList.json` y las condiciones del Theme Builder, con atribución («datos derivados de Divi 5.15.0, © Elegant Themes, Inc., GPLv2 o posterior») en la cabecera de cada archivo generado y una línea en este registro | Bundles JS compilados sin fuente (`includes/builder-5/visual-builder/build/*`), imágenes, SVG de divisores, máscaras y patrones, glifos de la fuente ETmodules, layouts de Divi Cloud, IA y onboarding. «Divi» es marca: en la interfaz sólo «Importar desde Divi» |
| GP Premium (GeneratePress) | 2.5.6 | Cabecera de `gp-premium.php` y `readme.txt`: «GNU General Public License v2 or later» | Estudio de formatos y comportamiento, y reescritura propia. Si algún día se reutiliza código, se registran aquí archivo, procedencia, avisos de copyright y cambios | Actualizador y licenciamiento (`library/class-plugin-updater.php`), servicios de generatepress.com y gpsites.co, las marcas «GeneratePress» y «GP» en la interfaz, la ejecución de hooks con `eval` y los scripts de terceros incluidos (offside.js, Infinite Scroll, select2) |
| GeneratePress (tema) y GenerateBlocks | GeneratePress 3.6.1 y GenerateBlocks 2.5.0 | GPLv2 o posterior, verificado en las copias oficiales de wordpress.org (`style.css` y `readme.txt` del tema; cabecera del plugin y `readme.txt` de GenerateBlocks) | Referencia de formatos (`generate_settings`, metas por página, bloques `generateblocks/*`) y de paridad | Su código: los conversores se escriben desde los formatos |
| Hello Elementor (Elementor Ltd.) | 3.5.1 | GPL v3 o posterior, verificado en la copia oficial de wordpress.org (`style.css` y `readme.txt`; ZIP `reference/hello-elementor.3.5.1.zip`, sha256 `c166fdcb091a38897f4f3339f83645e9747234fbc961560027d5563e3efd6447`); su `readme.txt` declara además OFL 1.1 para las fuentes y CC0 1.0 para las imágenes | Referencia de paridad para el paso desde Hello (A/B con `tests/playground/theme-switch.sh`). **Distribuido en el tema `digitalisimo-theme`** (GPL-3.0-or-later), que conserva el aviso © Elementor Ltd. en «Créditos» de su `readme.txt`. Lleva tres copias sin cambios de `hello-elementor/assets/css/*.css` de 3.5.1, leídas con `unzip -p` y cargadas sólo en paridad (sitios que venían de Hello):<br>• `assets/css/parity/reset.css`, sha256 `72eaeaf7ea81956ddf733ceac890269ab63e0aec77558bfaf045a5facd0bb6d4`, 5 829 B;<br>• `assets/css/parity/theme.css`, sha256 `d5c91a4cfdd98dba2870eeb3699e5ae57ad54203eef5fbe46accaee6b678d7df`, 5 827 B;<br>• `assets/css/parity/header-footer.css`, sha256 `d4428ab6d474bf08e6d95cb1beafc2ebc48ee97d229b7d07295c4e89be053b93`, 7 534 B.<br>Sin variantes `-rtl`, porque Hello no las encola. `tests/theme-structure.php` comprueba las tres sumas. `theme.json` es la configuración equivalente: copia literal de `hello-elementor/theme.json` (sha256 `26efd7c1b1015c3d37327604720004c1f93c1578eacff70cc78365654cad92fd`, 533 B: versión 3, anchos 800/1200 y `appearanceTools`). El marcado de `template-parts/` y `comments.php` reproduce las etiquetas, ids y clases de Hello por compatibilidad con el CSS de los sitios. La cabecera y el pie dinámicos se convierten en plantillas del constructor a partir de los ajustes guardados del kit y de los valores por defecto de Hello 3.5.1, sin copiar su código. El ZIP de Hello no entra en el repositorio | Los nombres «Hello» y «Elementor» en el nombre, la descripción, la captura o las cadenas traducibles del tema (sólo aparecen, sin enlace, en «Créditos» de `readme.txt`); su PHP, su JavaScript (`hello-frontend.js` y las pantallas de administración), sus imágenes y sus fuentes |
| Bricks Builder | 2.x (esquema 2.4.3) | **Sin código** en `reference/`: tema comercial. Sólo su documentación pública (academy.bricksbuilder.io) | Interoperabilidad: nombres de metas, opciones y claves y su estructura, descritos con palabras propias en `docs/fusion/bricks.md`; las claves no documentadas se cierran con exportaciones del usuario, que quedan fuera de Git | Textos y ejemplos de la documentación, CSS, JS, iconos y el ZIP de esquemas (licencia no verificada: como mucho para validar en pruebas locales, nunca en un paquete) |
| Betheme / BeBuilder (Muffin Group) | 29.0.3 | **Propietaria**: cabecera de `betheme/style.css` «License: Betheme License»; el ZIP no trae LICENSE ni COPYING del tema | Sólo formatos, en clean-room: nombres de metas, opciones, claves y su estructura, descritos con palabras propias en `docs/fusion/betheme.md`. Los conversores se escriben desde esa especificación; las equivalencias (como `old_id → id`) se reconstruyen como dato propio desde muestras | Todo su código PHP, JS y CSS, imágenes, SVG (formas, máscaras), fuentes, demos y secciones prefabricadas remotas. Tampoco los componentes de terceros que trae dentro (TGM Plugin Activation, WordPress Importer, svg-sanitize, GSAP con licencia propia no GPL) |
| Element Pack Pro (BdThemes) | 10.0.1 | `LICENSE` GPLv3 y cabecera «License: GPL3» | Como la 9.9.1 (secciones anteriores): sólo en DIGITALÍSIMO Migración, para leer y convertir documentos; el ZIP de Elements no puede contenerlo ni nombrarlo | Cargador, licenciamiento, UIkit y recursos gráficos |
| Prime Slider Pro (BdThemes) | 4.7.0 | `LICENSE` GPLv3 y cabecera «License: GPL3». Su `readme.txt` declara que los efectos de UI Initiative (Shutters, Swiper GL, Slicer, Tinder, Cards Stack, Expo, Panorama, Material) están licenciados a BdThemes y se distribuyen sólo con el plugin de pago | Sólo en Migración, para detectar y convertir sus widgets | Efectos de UI Initiative, GSAP y SplitText (licencia propia de GreenSock, no GPL), cargador y licenciamiento. Falta la versión Lite, de la que dependen sus valores por defecto |
| Dokan, Rank Math, UpdraftPlus | — | GPL | Fuera del alcance de la fusión | — |

Reglas para lo que se genere a partir de estas referencias:

- Un archivo generado desde datos de Divi lleva en su cabecera la procedencia y la licencia, y se añade a este registro con su ruta.
- De Betheme y Bricks no se genera nada desde su código: sólo se escribe código propio a partir de la especificación de formato.
- Las muestras reales del usuario no entran en Git; los fixtures de `tests/sources/` son sintéticos o anonimizados.

**F7a · capacidades clean-room** (DIGITALÍSIMO Elements 4.3.0.164). Sin código, datos, tablas, nombres de campos, claves, nombres de cookies, textos ni recursos de Divi, Betheme ni Bricks; los comportamientos están descritos con palabras propias en [fusion/f7a.md](fusion/f7a.md), §2.
- **Origen de cada capacidad.** Se inspiran en el comportamiento descrito de Divi 5.15.0: los presets de estilo, los degradados de N paradas, las máscaras y los patrones de fondo, y las condiciones nuevas (cookie, visita de página, campo, autor, existencias de un producto y búsqueda). De Betheme 29.0.3 vienen la cabecera visible sólo al fijarse y ocultar partes de la página. Lo que se inspira en el origen es la **capacidad**, no su implementación ni su catálogo.
- **Catálogo propio.** Las 12 máscaras y las 12 teselas de `digitalisimo-elements/modules/digitalisimo-design/backgrounds/svg/` son geometría propia, dibujada para F7a: ningún trazo se calca de una referencia, y no hay `diagonal`, `arco` ni `ola`. Su autoría es «DIGITALÍSIMO, GPL-3.0-or-later», y `MANIFEST.json` lleva el sha256, los bytes y la fecha de cada archivo. `tests/elements-design-backgrounds.php` lo comprueba.
- **Sin datos de Divi.** No se generó ninguna tabla desde datos de Divi, porque ninguna capacidad la necesita. Una etapa que generara una llevaría la atribución de la tabla de arriba.
