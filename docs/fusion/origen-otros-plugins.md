# Inventario: referencias a los plugins de origen en nuestros otros plugins y en los textos propios de Elements

> **Estado: resuelto en F0.2** (rama `fusion/f0-2-sin-referencias`, commit «Remove every link, video, account and request toward other companies»). La guarda vive en `digitalisimo-elements/digitalisimo-vendor-free.php` y `assets/digitalisimo/vendor-free.js`; las pruebas son `tests/elements-vendor-guard.php`, `tests/elements-vendor-static.mjs`, `tests/elements-identity.php` y `tests/playground/brand-audit.sh` ampliado (ver `tests/playground/README.md`). Este inventario, del 16 de octubre de 2026 y depurado para el repositorio público, queda como referencia de qué se retiró y por qué; las líneas citadas son de antes del cambio. Sirve también para repetir la revisión en cada `sync-elementor-core.sh`.

> Se conservan, por regla o por ley: las cabeceras del plugin Elementor original instalado, tal cual; `license.txt`, `COPYING` y créditos dentro del ZIP; los identificadores internos (clases, hooks, metas, `elementor_library`), y los nombres, sin enlaces, de los plugins que Migración desactiva.

## Resumen

- **No hay enlaces a los plugins de origen.** No hay ninguna URL a elementor.com, go.elementor.com, wordpress.org/plugins/elementor, bdthemes.com, elementpack.pro ni proelements.org en SEO, IA/Chatbot, Hosting, Backups, Tools ni en las pantallas de Migración. Tampoco los catálogos «Digitalisimo → Plugins» de SEO y Tools: sólo enlazan Releases de `danbutanda/digitalisimo-plugin`. La única excepción son los valores por defecto heredados de Element Pack en Migración (sección G).
- **Lo que sí hay son textos visibles:** unos 45 en SEO y menos de 10 en cada uno de Tools, Hosting e IA. Ninguno lo cubre la identidad actual: `Digitalisimo_Elements_Identity` sólo filtra los dominios `elementor` y `elementor-pro`, y nuestros plugins usan textos literales. Todos están en código propio editable, así que se retiran cambiando el texto en origen, sin filtros. Ninguno quita funciones.
- **Backups está limpio** (0 apariciones).
- **Prime Slider, Hello (como texto), Divi, Betheme, GeneratePress y Bricks** no aparecen en ningún texto propio. Hello sólo aparece como identificador (ver C3).
- **Lo más grave está en el propio Elements:** tres avisos de `pro-elements.php` mandan a instalar o actualizar el plugin Elementor, y los mensajes a Discord y Slack firman con «Elementor» y su logotipo.

Leyenda: (a) texto visible · (b) enlace · (c) identificador interno, no se cambia · (d) texto necesario que nombra un plugin a retirar.

---

## A. DIGITALÍSIMO Elements: archivos propios fuera de `elementor/`

| Archivo:línea | Qué ve el usuario y dónde | Tipo | ¿Lo cubre la identidad? | Propuesta |
|---|---|---|---|---|
| `digitalisimo-elements/pro-elements.php:153-184` (`pro_elements_plugin_fail_load`); el enlace está en `:176` | Aviso en el admin: «DIGITALÍSIMO Elements requiere Elementor gratuito.» con botón «Install Now» a `update.php?action=install-plugin&plugin=elementor` (lo instala desde wordpress.org) o «Activate Now» para el plugin Elementor | (a)+(b) | No. Sale cuando el núcleo incluido no se cargó, y entonces la identidad no corre | Cambiar a: «No se pudo cargar el constructor incluido en DIGITALÍSIMO Elements (carpeta `elementor/` incompleta o ilegible). Reinstala DIGITALÍSIMO Elements.» Sin enlace a Elementor. No se pierde nada: instalar Elementor ya no hace falta |
| `pro-elements.php:186-203` (`…_out_of_date`) | Con el plugin Elementor externo activo y anterior a 4.0: «Actualiza Elementor…» + «Update Now» que actualiza `elementor/elementor.php` | (a)+(b) | No (el externo está activo) | «El plugin Elementor %s activo es anterior al constructor incluido en DIGITALÍSIMO Elements (%s) y manda mientras siga activo. Desactívalo en Plugins.» Enlace a `plugins.php` |
| `pro-elements.php:205-222` (`…_upgrade_recommendation`) | En inglés: «Don’t miss out on the new version of Elementor… Update to the latest version of Elementor» + «Update Now» | (a)+(b) | No | Mismo texto de la fila anterior, sin enlace de actualización de Elementor |
| `pro-elements.php:136` | «…porque Elementor Pro u otra versión derivada ya está activa…» | (d), sin enlace | — | Mantener. Opcional: «Elementor Pro o PRO Elements» |
| `digitalisimo-core.php:181-195` (nota bajo la fila del plugin original), `:374-380` (aviso en Plugins), `:339` (bloqueo de reversión) | Nombran el plugin Elementor original para decir que sobra | (d), sin enlace | — | Mantener: es la regla «el original se muestra tal cual» |
| `pro-elements.php:2-13` (cabeceras), `digitalisimo-brand.php:63-77` (Acerca de), `digitalisimo-identity.php:256-259` (enlaces del inicio rápido) | Description, Plugin URI (GitHub), Author URI (digitalisimo.mx), la página Acerca de y los enlaces de la fila («Buscar actualizaciones», «Acerca de DIGITALÍSIMO Elements») | — | — | Limpios. `Elementor tested up to: 4.3.5` y `Text Domain: pro-elements` son (c) |
| Fila de plugins: enlaces del núcleo | — | — | — | No aparecen. Los enlaces «Video Tutorials» y «Go Pro» del núcleo se registran para `ELEMENTOR_PLUGIN_BASE` (`elementor/core/admin/admin.php:1030-1031, 411-412`), que con el núcleo incluido es `digitalisimo-elements/elementor/elementor.php` y no corresponde a ninguna fila |
| «Ver detalles» de Elements, Tools, Migración y Backups | El modal consulta wordpress.org con el slug `digitalisimo-*` y responde «No se encontró el plugin» | — | — | No nombra Elementor, pero el enlace está roto. Ninguno de esos cuatro engancha `plugins_api`: añadirlo como en `digitalisimo-seo/includes/class-digitalisimo-updater.php:280` |
| `readme.txt:3-7` | Dentro del ZIP; WordPress no lo muestra. Dice «Versión derivada de PRO Elements… junto con Elementor gratuito. Requiere Elementor 4.0», ya falso | (a) en el ZIP | — | Reescribir: «Constructor visual completo… No necesita otros plugins. Créditos y licencias en license.txt.» |
| `changelog.txt:1` («# PRO Elements - by PROElements.org») y 586 enlaces a `github.com/elementor/elementor/issues` | Sólo en el ZIP; nada lo lee (lo verifiqué: `tests/validate-suite.mjs:82` no lo exige) | (b) en el ZIP | — | Excluirlo del ZIP en `scripts/build-packages.sh` (sin borrar la fuente) o sustituirlo por uno propio |
| `license.txt`, `COPYING`, `DIGITALISIMO_CHANGES.md` | Sólo en el ZIP | (d), legal | — | **No tocar.** La GPLv3 exige conservar los avisos de copyright y de modificación |
| `updater/updater.php` | Actualizador por GitHub heredado de PRO Elements. Es código muerto: no se incluye en ningún sitio, pero viaja en el ZIP | — | — | Excluirlo del ZIP |
| `modules/digitalisimo-*` | — | — | — | Limpios. Sólo hay comentarios y clases CSS o PHP (c); las URL de YouTube y Vimeo son para incrustar los vídeos del usuario |

**Hallazgos del código derivado de PRO** (son de otro frente, pero llegan fuera del admin):
- `modules/forms/actions/discord.php:197-203`: el pie del mensaje a Discord dice «Powered by %s» con el literal `'Elementor'`. Como es un argumento, el filtro de traducción no lo toca, y además pone `icon_url` = logotipo de Elementor (`ELEMENTOR_ASSETS_URL . 'images/logo-icon.png'`). No lo cubre la identidad. Poner `'DIGITALÍSIMO Elements'` y `assets/digitalisimo/icono.png`.
- `modules/forms/actions/slack.php:232`: el texto ya es propio, pero `footer_icon` sigue siendo el logotipo de Elementor.
- `modules/atomic-form/classes/metadata-resolver.php:91`: «Powered by: Elementor» en el correo del formulario. Lo cubre la identidad sólo con el núcleo incluido; conviene fijar el texto como en `modules/forms/classes/form-record.php:270`.

---

## B. Digitalisimo SEO (nada de esto lo cubre la identidad; todo es editable)

**Ajustes e indexación**
- `includes/class-settings.php:434`, `includes/class-seo-suite.php:158` y `:391`: casilla «Plantillas de Elementor» / «Plantillas Elementor» → «Plantillas del constructor (DIGITALÍSIMO Elements)». La clave `noindex_elementor` es (c).
- `class-seo-suite.php:7` (ayuda de unificación de schema): «las FAQ de Elementor» → «las FAQ de DIGITALÍSIMO Elements».
- `class-seo-suite.php:62` (Red → Clonar sitio): «tema, Elementor, opciones» → «tema, diseños de DIGITALÍSIMO Elements (kit, plantillas y CSS), opciones».
- `includes/class-favicon.php:354`: «lo reciben WordPress, el tema, Elementor, la API…» → «…el tema, el constructor, la API…».

**Rendimiento: campos del sitio y de la red** (`class-seo-suite.php`)
- `:220` y `:275`: «recursos centrales de Elementor, Swiper…» y el título «Elementor y WooCommerce» → «del constructor» y «Constructor y WooCommerce».
- `:260`: «Protege Elementor, Elementor Pro, Swiper…» → «Protege los recursos de DIGITALÍSIMO Elements, Swiper, formularios, menús y WooCommerce».
- `:262`, `:264`, `:280`, `:286`: «páginas (confirmadas como) Elementor» → «páginas hechas con DIGITALÍSIMO Elements».
- `:267` y `:284`: «font-display: swap en fuentes de Elementor… de Elementor Pro… regenera CSS desde Elementor → Herramientas». Además de la marca, la ruta del menú está mal con el núcleo incluido. Cambiar a «fuentes del constructor… regenera el CSS desde DIGITALÍSIMO Elements → Herramientas» (hay que verificar la etiqueta exacta del menú).
- `:270`: «no difiere CSS de Elementor» → «del constructor».
- `:272` y `:288`: «Normalizar captions de carruseles Elementor» → «Normalizar leyendas de los carruseles».
- `:274`: «durante la migración desde Elementor» → «mientras pasas el Código personalizado a Digitalísimo SEO».
- `:293`: «un ID publicado en Elementor» → «en el Código personalizado de DIGITALÍSIMO Elements».
- `:303`, `:306`, `:309`, `:320`: «en Elementor», «layout de Elementor», «Fondos de Elementor», «el CSS de Elementor» → «del constructor».
- `:343`: «Si Elementor ya publica el mismo preload» → «Si el constructor ya…».
- `:349`: «Fuentes locales de Elementor» → «Fuentes locales del constructor».
- `:346`: la ruta de ejemplo `elementor/google-fonts/...` es (c), una ruta real en uploads. Se mantiene.

**Pantallas de rendimiento y auditoría** (`includes/performance/`)
- `class-performance-console.php:72, 73, 85, 99, 126`: «Elementor, Pro y WooCommerce», «páginas Elementor elegibles», «Revisar Custom Code de Elementor», «Widgets de Elementor innecesarios», «con WordPress y Elementor» → «el constructor» / «Código personalizado del constructor».
- `class-performance-migration.php:79, 171, 189`: «Elementor Custom Code», «Migrar Custom Code de Elementor» → «Código personalizado de DIGITALÍSIMO Elements».
- `class-performance-fonts.php:256, 266, 277`, `class-performance-font-guard.php:555` («Kit Elementor», «Contenido Elementor»), `:612`, `:787` → «del constructor».
- `class-performance-manager.php:119, 133` (motivos que se ven en el registro de diagnóstico): «Página Elementor…» → «Página del constructor…».
- `class-asset-diagnostics.php:53-54` («Elementor y Swiper»), `:241` (estado «Elementor») y `:354` («ANTES/DESPUÉS de Elementor») → «Constructor», «antes/después del CSS del constructor».
- `class-asset-diagnostics.php:228-229`: las etiquetas de origen «Elementor» y «Elementor Pro» sólo salen si el recurso viene de verdad de la carpeta del plugin original. Son (d) y pueden quedarse.
- `class-quality-rules.php:423, 439` y `class-quality-audit.php:671`: «elige un tamaño menor en Elementor», «widget de Elementor», «Elementor permite elegir la etiqueta…» → «en el constructor».
- `class-performance-debug.php:141`: «ELEMENTOR: inventariado» → «CÓDIGO PERSONALIZADO: inventariado».

**Catálogo** (`includes/class-plugin-catalog.php:59-60`)
- «Herramientas internas y plantillas Elementor de red.» → «Herramientas internas, plantillas de red, WebP y ajustes del sitio.»
- «Funciones GPL avanzadas para Elementor gratuito.» (ya falso) → «Constructor visual completo: editor, funciones avanzadas, kits, plantillas y widgets propios.»
- Ningún catálogo lista `wordpress.org/plugins/elementor`.

---

## C. DIGITALÍSIMO Tools
- `includes/class-plugin-catalog.php:45-46`: mismas dos descripciones de B (la de Elements añade «…con identidad DIGITALÍSIMO»).
- `modules/elementor-network-templates/class-network-admin.php:26`: pestaña «Plantillas Elementor» → «Plantillas de red».
- `…/class-network-admin.php:33`: «Elementor debe estar activo en el sitio maestro…» → «DIGITALÍSIMO Elements debe estar activo en el sitio maestro para sincronizar plantillas.» `Elementor_Adapter::available()` ya da verdadero con el núcleo incluido.
- `…/class-elementor-adapter.php:8`: error «no es una Saved Template de Elementor» → «no es una plantilla guardada de DIGITALÍSIMO Elements».
- `README.md:3, 9, 12, 18, 20, 26, 36`: sólo está en el ZIP. Reescribirlo como «plantillas de red».
- Se quedan como (c): el namespace `Elementor_Network_Templates`, el slug `digitalisimo-tools-elementor-templates` (`class-site-options.php:17`, `class-network-admin.php:5`), `tab=elementor`, el tipo `elementor_library`, los metadatos `_elementor_*` y los selectores `.elementor-swiper…` (`class-site-options.php:110`). Cambiarlos rompe marcadores, opciones y contenido.

## D. Digitalisimo Hosting
- `digitalisimo-hosting.php:4` (Description en la lista de plugins): «formularios Elementor» → «formularios de DIGITALÍSIMO Elements».
- `includes/class-hosting.php:143`: título «Formulario Elementor» → «Formulario de DIGITALÍSIMO Elements».
- `includes/class-hosting.php:145`: «en formularios Elementor» → «en el widget Formulario de DIGITALÍSIMO Elements».
- `:37`: el valor por defecto `.elementor-form` es (c).

## E. Digitalisimo IA Tools (Chatbot)
- `includes/class-digitalisimo-ai-article-chat.php:28` (caja del editor de entradas): «…o en la plantilla individual de Elementor.» → «…o en la plantilla de entrada individual de DIGITALÍSIMO Elements.»
- `class-digitalisimo-ai-content-publisher.php:109, 168`: sólo comentarios.

## F. Digitalisimo Backups
Sin apariciones.

## G. DIGITALÍSIMO Migración (temporal: se desinstala al terminar)

**(d) Se pueden mantener: nombran lo que se desactiva o convierte y no tienen enlaces.** Sin esos nombres, el administrador no sabría qué filas borrar después en Plugins. Comprobé que las pantallas no tienen ningún `href` externo.
- `digitalisimo-migration.php:4` (Description).
- `legacy/class-migration.php:287, 674, 699, 702, 704, 712, 715, 720, 722, 728, 742, 788`.
- `legacy/class-runner.php:53, 359, 364, 379, 955, 1023, 1033, 1053, 1056, 1064`.
- `legacy/class-cli.php:93, 96, 102`.
- `legacy/unsupported.php` (motivos de los widgets sin adaptador).

**(a) Cambiaría:**
- `legacy/unsupported.php:6, 44, 50`: recomiendan como alternativa para contenido nuevo «formulario de PRO Elements», «PRO Elements incluye botones flotantes» y «el Mega Menu de PRO Elements». Pero PRO Elements es un plugin que se retira, y esas funciones ya están dentro de Elements → «DIGITALÍSIMO Elements».
- `legacy/class-runner.php:507`: «hazlo en Elementor → Herramientas → Regenerar archivos». Después de migrar manda el núcleo incluido, así que también es una ruta falsa → «No se pudo vaciar la caché del constructor (…): hazlo en DIGITALÍSIMO Elements → Herramientas → Regenerar archivos y datos.»
- Etiquetas que ven los editores al abrir un documento aún no migrado:
  - `legacy/class-adapters.php:32`: sufijo «(Element Pack)» en el título del widget (no sale en el panel: `show_in_panel` es falso).
  - `:70`: sección «Estilo de Element Pack».
  - `legacy/class-extensions.php:59, 65`: «Efectos de Element Pack».
  - `legacy/class-shapes.php:71`: «Figuras de Element Pack».
  - `legacy/class-masks.php:207`: «(Element Pack)».
  - Propuesta: «(heredado)», «Estilo heredado», «Efectos heredados», «Figuras heredadas». No se pierde ninguna función, sólo la marca de origen, que la pantalla Migrar ya explica.

**(b) Valores por defecto con URL del proveedor** (los aplica `legacy/class-translator.php:350`):
- Ubicaciones:
  - `legacy/maps/bdt-qrcode.php:12` (`http://bdthemes.com`)
  - `bdt-timeline.php:86`
  - `bdt-advanced-gmap.php:41, 53`
  - `bdt-custom-gallery.php:31` (`https://elementpack.pro`)
  - `bdt-member.php:76` y `bdt-profile-card.php:88` (`x.com/bdthemes…`)
  - `bdt-video-player.php:13, 15`: el vídeo y el póster se cargan desde `elementpack.pro/demo`, una dependencia externa en el front.
- Reproducen lo que el visitante ya veía si el usuario dejó el valor por defecto. Cambiarlos en silencio alteraría el contenido.
- Propuesta: que el informe de Migración liste «Este documento conserva un enlace o recurso por defecto del plugin anterior (dominio X)» para que el administrador lo sustituya.

**(c)** Se quedan los tipos `bdt-*`, los filtros `element_pack_visibility_*` (`includes/class-plugin.php:72-80`), las rutas `bdthemes-element-pack*` y las clases `.bdt-*`.

---

## Riesgos
1. **Rutas del plugin original que fallan con el núcleo incluido** (no es tema de marca, pero son las mismas líneas). Hay comprobaciones de `/plugins/elementor(-pro)/` en:
   - `digitalisimo-seo/includes/performance/class-performance-css.php:48` (diferir el CSS de widgets);
   - `class-performance-debug.php:71`;
   - `class-asset-diagnostics.php:241` y `:349`.
   
   Con el núcleo incluido, los recursos salen de `/plugins/<carpeta de Elements>/elementor/assets/` y `/plugins/<carpeta>/assets/css/`, así que esas funciones no se activan o diagnostican mal. Conviene corregirlas cuando se cambien los textos.
2. **`class-performance-manager.php:50` exige `get_template() === 'hello-elementor'`.** Con el tema propio que sustituya a Hello, «Desactivar CSS de Gutenberg» y «wp-embed» dejan de actuar sin avisar. Conviene una lista filtrable de temas.
3. **No renombrar identificadores:** `noindex_elementor`, `digitalisimo-tools-elementor-templates`, `.elementor-form`, `_elementor_*`, `elementor_library`, las ID de herramientas MCP `elementor/*`. Rompen opciones guardadas, marcadores y contenido.
4. **Ya hay un identificador renombrado:** `modules/compatibility-tag/compatibility-tag-component.php:16` cambió la cabecera que leen los complementos («Elementor Pro tested up to») por «DIGITALÍSIMO Elements tested up to». Como ningún complemento la declara, la alerta de compatibilidad al actualizar Elements nunca sale. Es inocuo pero contradice la regla.
5. **Con el plugin Elementor externo activo, la identidad no corre** y el constructor es Elementor. Donde nuestros textos sirvan a los dos casos, conviene «el constructor» y dejar «DIGITALÍSIMO Elements» para rutas de menú y nombres de producto.
6. **Textos legales:** license.txt, COPYING y DIGITALISIMO_CHANGES.md deben seguir nombrando a Elementor Ltd. y PRO Elements (GPLv3, §5a y avisos de copyright). No se ven en WordPress.
7. **`tests/playground/brand-audit.sh` no detecta esto hoy.** Sólo recorre las pantallas cuyo `href` contiene elementor, e-… o digitalisimo-elements (`brand-audit/run.cjs:137`), y no revisa `a[href]` hacia elementor.com ni iframes de YouTube. Habría que ampliarlo a `page=digitalisimo*`, a Herramientas → Migrar y a los enlaces e iframes.

## Lista priorizada
1. **P1, enlaces que llevan al plugin original:** `pro-elements.php:153-222`, es decir, los tres avisos con «Install/Update Now» de Elementor, uno de ellos en inglés.
2. **P1, fuera del admin:** el pie de Discord con «Powered by Elementor» y el logotipo de Elementor en Discord y Slack (`modules/forms/actions/discord.php:197-203`, `slack.php:232`).
3. **P2, textos visibles en SEO (B)**, empezando por las rutas de menú falsas «Elementor → Herramientas» (`class-seo-suite.php:267, 284`).
4. **P2, textos de Tools (C), Hosting (D) e IA (E)**, más las descripciones de los dos catálogos (`digitalisimo-seo/includes/class-plugin-catalog.php:59-60`, `digitalisimo-tools/includes/class-plugin-catalog.php:45-46`).
5. **P2, Migración:** «PRO Elements» como alternativa (`unsupported.php:6, 44, 50`), la ruta de `class-runner.php:507` y las etiquetas «(Element Pack)» del editor.
6. **P3:** reescribir `readme.txt`; sacar del ZIP `changelog.txt` y `updater/updater.php`; reescribir el README de Tools; listar en el informe de Migración los valores por defecto con dominios del proveedor.
7. **P3, funcional:** las rutas del núcleo incluido en las comprobaciones de SEO (riesgo 1) y el tema Hello (riesgo 2).
8. **P4:** ampliar `brand-audit.sh` a nuestras pantallas, enlaces e iframes; añadir `plugins_api` para «Ver detalles» en Elements, Tools, Migración y Backups.
