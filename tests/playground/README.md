# Pruebas en WordPress Playground

Pruebas que levantan un WordPress efímero con [WordPress Playground](https://wordpress.github.io/wordpress-playground/)
y montan las carpetas del repositorio como plugins (IA Tools se monta como `digitalisimo.chatbot/`, la carpeta que
tiene en los sitios). No forman parte de los workflows: se corren a mano antes de publicar un cambio que las afecte.

| Script | Qué comprueba |
| --- | --- |
| `legacy-ab.sh`, `migrate.sh`, `legacy-repair.sh`, `migration-report.sh` | Adaptadores y conversión de Element Pack con DIGITALÍSIMO Migración. |
| `core-switch.sh`, `brand-audit.sh` | Núcleo de Elementor incluido e identidad propia del constructor. |
| `interaction.sh`, `slider.sh`, `front-budget.sh`, `form-submit.sh`, `conditions.sh` | Widgets en Chrome real, presupuesto del front (con la puerta de PRO, sus modos y Lighthouse), formularios y condiciones de visualización. |
| `front-network.sh` | Ajustes «Rendimiento» de DIGITALÍSIMO Elements en una red Multisite sin Chrome: herencia, transición por sitio, permisos y vaciados (N1–N7). |
| `shared-ui.sh` | Diseño compartido de los siete plugins. |
| `tools-catalog.sh`, `tools-php.sh` | Catálogo de plugins y código PHP de DIGITALÍSIMO Tools. |
| `theme-switch.sh` | A/B en Chrome del tema DIGITALÍSIMO frente a Hello 3.5.1 (sitio individual, `WOO=1`): escenarios S1–S5, S3c y S3h; fases A–F2; 15 comprobaciones por URL y ancho. |
| `theme-network.sh` | Tema DIGITALÍSIMO en una red Multisite sin Chrome: cobertura de Tools, sección «Tema», actualización con la carpeta conservada (N1–N6 y N3f). |
| `seo-rankmath.sh` | Lo que DIGITALÍSIMO SEO trae de Rank Math (RM1–RM16) en red y sitio individual sin Chrome: importación por lotes de datos sintéticos (repetirla no duplica), redirecciones en el front (con categoría, programación y CSV), enlaces rotos con respuestas HTTP simuladas, vigilante de enlaces sin bucles, sitemap con imágenes, robots avanzados (también por término), texto del RSS, alt de avatares, verificación, migas con categoría principal, variables y plantillas por tipo, por taxonomía y por archivo, schema (marca, GTIN importado y entidad por defecto, con WooCommerce 11.2.1 en el sitio individual), sitemap de noticias y mapa HTML, enlaces internos (recuentos, huérfanas, pilar y sugerencias), análisis de contenido, edición rápida y masiva y CSV, productos ocultos y 301 de las URL sin base, abilities con sus permisos, pantallas del sitio y de la red, guardado de red y herencia. |
| `design.sh` | Capacidades de diseño de DIGITALÍSIMO Elements (F7a, DS1–DS13): presets, degradados, máscaras y patrones, «Visitó la página», prioridad del Theme Builder, controles de documento, sticky «al fijarse», caché de elementos, plugin original, v4, red y vuelta a 4.3.0.163. |
| `kit-roundtrip.sh`, `brand-audit.sh STATE=kit` | Kits de Elementor con DIGITALÍSIMO Elements (etapa K): ida y vuelta, terceros, «sólo agregar», red, reversión, entradas hostiles y marca. Obligatorias antes de publicar K y después de cada `sync-elementor-core.sh`. |

## A/B de adaptadores de Element Pack

`legacy-ab.sh` comprueba en WordPress Playground (red Multisite con un subsitio) que una página guardada con un widget `bdt-*` se ve igual con Element Pack activo que sólo con DIGITALÍSIMO Elements. Necesita la copia local de referencia en `reference/bdthemes-element-pack/`, que no se versiona ni se publica.

```bash
bash tests/playground/legacy-ab.sh                  # todos los widgets con casos en tests/legacy/
bash tests/playground/legacy-ab.sh bdt-accordion    # uno o varios, separados por comas
```

Fases por widget, caso y sitio:

- **A**: Element Pack 9.9.1 activo con todos sus módulos encendidos; se guarda la firma de contenido (textos visibles, enlaces e imágenes) y el `_elementor_data` original.
- **B**: Element Pack desactivado en la red; la misma página se muestra con el adaptador. Se compara la firma, se verifica que el editor recibe los ajustes ya traducidos y que el CSS regenerado contiene los fragmentos de `_expect_css`.
- **C**: la herramienta de migración traduce el documento; la firma debe seguir igual.
- **E**: con DIGITALÍSIMO Migración desactivado, el documento convertido se pinta sólo con DIGITALÍSIMO Elements y no conserva identificadores heredados.
- **D**: se restaura la copia y el `_elementor_data` debe ser idéntico al original.

Cada `tests/legacy/bdt-*.json` es un objeto `caso → ajustes guardados`. `_expect_css` lista fragmentos de CSS esperados y `_known` documenta diferencias deliberadas por tipo (`text`, `links`, `images`), que se informan pero no fallan. La caché de elementos de Elementor se desactiva en la prueba: Element Pack no marca como dinámicos sus widgets.

Opciones de los casos (`tests/legacy/*.json`):

- `_widget`: tipo de widget del caso cuando el archivo no es `bdt-*` (extensiones `ext-*.json`).
- `_section`: ajustes de la sección que contiene el widget (figuras del Shape Builder en secciones).
- `_expect_html`: fragmentos que deben aparecer en el HTML de la fase B.
- `_acf`: valores de ACF que se guardan en la página del caso con `update_field()` (clave del campo => valor; requiere `EXTRA_PLUGINS=secure-custom-fields`).
- `BLOG=1` o `BLOG=2` limita las cuatro fases a un sitio, en su propio proceso: bbPress arrastra estado entre sitios y entre shortcodes de un mismo proceso, así que sus listas se validan sueltas y, si hace falta, por sitio.
- Fase B, guardado desde el editor: cada página se vuelve a guardar con `ajax_save()` a partir de lo que carga el editor y debe seguir pintando lo mismo con el widget heredado aún en el documento (`editor_resave`); después se restaura el `_elementor_data` original para las fases C y D.
- `_options`: opciones del sitio que se fijan sólo durante el caso y se restauran después (por ejemplo, la clave de Google Maps de Element Pack).
- `__TEMPLATE__` y `__POST__` en cualquier valor se sustituyen por el ID de la plantilla de Elementor y de la entrada de prueba que crea `legacy-setup.php` en cada sitio, junto con el menú «Principal».

Con `FAKE_PLUGINS=1`, `legacy-ab.sh` monta los plugins de `tests/playground/fake-plugins/` en las rutas que Element Pack exige a sus widgets de integración (Contact Form 7, Give, Charitable, EDD, WPForms…). Sólo registran el shortcode del plugin e imprimen sus atributos, así que la fase A y la B se comparan por el shortcode exacto que arma cada una. No se publican.

Con `EXTRA_PLUGINS=slug,…`, `legacy-ab.sh` instala esos plugins reales de wordpress.org y los activa en la red; `legacy-content.php` crea después su contenido de prueba en cada sitio y deja sus IDs como marcadores: `bbpress` (foro, tema, respuesta y etiqueta: `__BBP_FORUM__`, `__BBP_TOPIC__`, `__BBP_REPLY__`, `__BBP_TAG__`) `secure-custom-fields` (grupo «Digi A/B» en páginas: repetidor `field_digi_ab_faq` con `question`, `answer`, `photo` y `target`, galería `field_digi_ab_gallery` y dos adjuntos: `__ACF_IMAGE_1__`, `__ACF_IMAGE_2__`) y `woocommerce` (instala WooCommerce en cada sitio y crea cuatro productos con categoría, uno destacado y otro en oferta: `__WC_PRODUCT__`). Por ejemplo:

```bash
EXTRA_PLUGINS=woocommerce bash tests/playground/legacy-ab.sh bdt-wc-products,bdt-wc-add-to-cart
```

`migrate.sh` prueba «Migrar toda la red» de punta a punta en Chrome real: una red de Playground servida como `http://playground.test` (una red no admite puertos; Chrome la resuelve al puerto local con `--host-resolver-rules`), con Elementor activado en toda la red y Element Pack en cada sitio. Firma cada página de los dos sitios (textos, enlaces, imágenes y, en los alias, el estilo calculado de cada elemento), pulsa el único botón de la pantalla de red, espera al resumen y comprueba que cada sitio queda sin esos plugins, sobre el núcleo incluido, con el contenido de Element Pack, su revisión y los alias convertidos con copia del original, y con las mismas páginas; en el subsitio, el CSS adicional que nombra las clases de la Cuadrícula de marcas mantiene ese alias y el informe lo explica. Necesita la copia de referencia de Element Pack (`EP_DIR`).

`migration-report.sh` comprueba en Playground Multisite la pantalla de red (Red → Ajustes → Migrar a DIGITALÍSIMO Elements), con su único botón «Migrar toda la red» y el estado de cada sitio con el enlace a su herramienta, y la del sitio, con su único botón «Migrar» y el motivo de cada widget sin adaptador.

`form-submit.sh` envía en Playground Multisite (sin Element Pack) una página con `bdt-contact-form`, otra con `bdt-webhook-form` y otra con `bdt-mailchimp` por el AJAX de PRO Elements en cada sitio, y comprueba el correo (destinatario, asunto, `Reply-To`, mensaje), la petición al webhook (URL y campos) y la llamada a la API de Mailchimp (audiencia y clave que Element Pack guardaba en sus ajustes, correo y `FNAME`).

`interaction.sh` incluye además una página por widget fusionado y por alias (`digitalisimo-logos`, `digitalisimo-brand-carousel`, `digitalisimo-brand-grid`, `digitalisimo-logo-grid`, `digitalisimo-advanced-button`, `digitalisimo-creative-button`, `digitalisimo-icon-nav`, `digitalisimo-icon-mobile-menu`, `digitalisimo-fancy-tabs`, `digitalisimo-content-switcher`) con ajustes del esquema de antes: comprueba que el alias traduce sus ajustes, conserva sus clases CSS y responde igual (carrusel, detalles, globo, aspecto del botón, barra, pestañas con plantilla).

`interaction.sh` arranca Playground en modo servidor (sitio simple, porque Multisite no admite puertos personalizados) y, con Chrome sin interfaz (`puppeteer-core`, ruta en `PUPPETEER_PATH`), comprueba en una página por widget heredado que el acordeón abre su segundo elemento, las pestañas cambian de panel, el deslizador propio, el carrusel de logos, el carrusel de medios y el deslizador de diapositivas avanzan con la flecha, la marquesina se pausa, el comparador de imágenes responde al teclado, el menú vertical despliega su submenú, el botón de desplazamiento vuelve al inicio y el panel lateral se abre con su botón y se cierra con Escape; en cada página exige que no haya errores de JavaScript, recursos 404 ni widgets ocultos por una animación de entrada que no empieza. Los casos están en `interaction/cases.json` (`__SITE__` y `__TEMPLATE__` se sustituyen al crearlos).

## Núcleo de Elementor incluido

DIGITALÍSIMO Elements trae el núcleo de Elementor (`digitalisimo-elements/elementor/`). Por defecto los arneses instalan además el plugin Elementor de wordpress.org, que manda y deja inactivo el núcleo incluido. Con `CORE=bundled`, `legacy-ab.sh`, `interaction.sh`, `form-submit.sh` y `migration-report.sh` no lo instalan y todo corre sobre el núcleo incluido: es el escenario final de un sitio que ya retiró Elementor, Elementor Pro y Element Pack. En `legacy-ab.sh` Element Pack se activa en la fase A sobre ese mismo núcleo.

```bash
CORE=bundled FAKE_PLUGINS=1 EXTRA_PLUGINS=woocommerce,secure-custom-fields bash tests/playground/legacy-ab.sh
CORE=bundled bash tests/playground/interaction.sh
```

`interaction.sh` abre además el editor de Elementor como administrador y exige panel, vista previa y ninguna respuesta de error; un mu-plugin de prueba deja en `fatal.log` los errores fatales de cada petición, también las AJAX del editor.

`slider.sh` arranca Playground en modo servidor con el núcleo incluido y una página con cinco Slider Optimizado (onda de diez copias de una imagen en modo múltiple con espejo, doce logotipos distintos, onda repetida con espejo, logotipos en una sola pasada y una instancia lejana tras un separador de 3000 px), con imágenes PNG creadas como adjuntos. Chrome comprueba en escritorio y en móvil que no hay errores ni recursos 404, que las instancias cercanas arrancan pronto y la lejana al llegar, que la pista de la onda se recorta a dos mitades con la duración en proporción (la velocidad de sus ocho copias en 60 s) mientras los doce logotipos distintos se conservan enteros, que la pasada única conserva el final, que la pista mide dos grupos exactos y el cierre del bucle salta un grupo, que el movimiento no pierde fotogramas, que al pasar a tableta vuelven los elementos apartados, que ningún grupo mide más que sus elementos (Firefox dejaba un hueco al medir por contenido), que sin JavaScript el movimiento arranca solo a los tres segundos y, si hay Firefox instalado (`/snap/bin/firefox`, `/usr/bin/firefox` o `FIREFOX_PATH`), repite en él las comprobaciones de marcha, uniones y cierre del bucle.

`core-switch.sh` instala el plugin Elementor en la versión fijada `ELEMENTOR_PIN` (por defecto 4.3.5, la del núcleo incluido; desde downloads.wordpress.org), crea en una red páginas con widgets de Elementor, Pro y DIGITALÍSIMO bajo el plugin Elementor, lo borra (con sus traducciones) y comprueba que el núcleo incluido las pinta igual (texto, enlaces, imágenes y CSS), en español, con un kit por sitio, aceptando los complementos que exigen `elementor` y sirviendo desde el núcleo las rutas antiguas guardadas en el contenido. Cada sitio se pinta en su propia petición: Elementor guarda en caché los documentos por ID sin distinguir el sitio. Con Elementor instalado pero desactivado, la lista de plugins (`WP_Plugins_List_Table`) lo sigue llamando «Elementor» por «Elementor.com» con su nota, sin que la identidad lo convierta en un segundo «DIGITALÍSIMO Elements», no lo cuenta como desinstalable, y el paso siguiente lo borra con el núcleo incluido cargado. En cada sitio y fase comprueba además el valor por defecto de `font-display` de F-front: `default_option_elementor_font_display` no existe con el plugin Elementor (ni antes ni después de la transición) y existe, con `swap`, sobre el núcleo incluido tras la transición (D3).

## Identidad propia del constructor

`brand-audit.sh` arranca un sitio en español sólo con DIGITALÍSIMO Elements y su núcleo incluido (Playground con un solo worker), y lo recorre en Chrome en español y en inglés. La fila del plugin Elementor original instalado queda fuera de esta auditoría a propósito (sus cabeceras se muestran tal cual); la cubre `core-switch.sh`.

- Todas las pantallas del menú del constructor, el escritorio, los plugins, las páginas y el editor de bloques.
- La app de kits.
- El editor del constructor: su pantalla de carga, su menú y la biblioteca de plantillas.
- El frontal, como administrador y como visitante.

Anota cada aparición visible de «Elementor» (textos, títulos, atributos accesibles, imágenes y logotipos, incluidos el icono del menú y los SVG de las barras superiores) y la meta `generator` del HTML público. Guarda capturas y el marcado de las barras en `$OUT` y falla si queda alguna. Los registros técnicos de «Información del sistema» (rutas y clases PHP) no cuentan: son identificadores.

También mide los colores calculados de cada elemento visible de la administración y del editor (texto, fondo, degradados, bordes, contorno, sombras, relleno y trazo de SVG, con `::before` y `::after`) y falla si uno es de la paleta de Elementor: así una versión nueva del núcleo no puede reintroducirla sin que se note.

### Nada hacia los plugins de origen

La misma corrida falla si algo apunta a un plugin de origen. `brand-audit/vendors.json` es la lista única de dominios: Elementor, Elementor Pro, PRO Elements, Element Pack, Prime Slider y los futuros (Divi, Betheme, GeneratePress, Bricks). También incluye:

- Mixpanel, sólo si lo llama el núcleo.
- Las fichas y descargas de wordpress.org de los plugins y temas de origen.
- Los vídeos de muestra del proveedor. `vendors.cjs` los regenera desde el código en cada corrida.

La clasificación es siempre por host. Por eso los identificadores internos (clases, slugs, `admin.php?page=elementor*`, rutas de `digitalisimo-elements/elementor/assets/`) nunca cuentan.

| Fuente | Qué se registra | Severidad |
|---|---|---|
| Navegador | Toda petición, también la de iframes de otro origen (sin aislamiento de sitios); navegaciones, redirecciones y ventanas nuevas (`popup`, `window.open`). Chrome resuelve todo host externo a `NOTFOUND`, así que nada sale a internet. | Falla |
| DOM | `href`, `src`, `srcset`, `action`, `poster`, `data-*`, `style`, `on*`, valores de campos, estilos calculados con `url()` (también `::before`/`::after`), texto con un dominio del proveedor, shadow DOM abierto y `<template>`. Se revisa en cada marco del sitio, en `about:blank` y en `srcdoc`, visible u oculto. | Falla |
| Latente | Scripts en línea y plantillas (`script#handle-js-extra`/`-js-before` lleva a la llamada PHP), datos globales del constructor y respuestas AJAX/REST con URL del proveedor. | Informe; con `LATENT=fail`, falla |
| Servidor | `probe-http.php`, un mu-plugin de la prueba, registra con `pre_http_request` (prioridad `PHP_INT_MAX`) cada petición con su pantalla (cookie `digi_screen`), contexto (AJAX, REST, cron) y llamador. También `wp_redirect`, la cabecera `Location` y `pre_wp_mail`. Va a `$OUT/http.jsonl`. | «salió», redirección y correo fallan; «bloqueada» (otra guarda ya respondió) es informe |
| Cron | `brand-audit/cron.php` ejecuta los eventos programados de los plugins y la telemetría del núcleo al terminar cada idioma. | Como el servidor |
| Sondeo | Los enlaces propios que redirigen (`go_*`, `elementor-one-upgrade`, `elementor-connect`, `action=authorize`) y las páginas `admin.php?page=` no visitadas se piden sin seguir la redirección. Nunca se piden `admin-post.php` ni acciones. | Falla |
| Netlog | `$OUT/netlog.json` de Chrome: hosts del proveedor que nadie atribuyó a una pantalla (workers, preconexiones). | Falla |

Una URL de YouTube, Vimeo, Dailymotion o VideoPress en el contenido (frontal y vista previa), o en los datos y plantillas de los widgets de vídeo, es un servicio: sólo falla si es un vídeo del proveedor. En la administración, cualquier vídeo que se cargue falla.

Opciones:

- `SCOPE=quick` recorre las pantallas de siempre, más la red, el DOM, el servidor y el cron. El frontal como visitante se revisa en una sesión sin cookies, con red, DOM y cabeceras HTTP. `SCOPE=full`, por defecto, añade:
  - pantallas de conexión, apps, roles, información del sistema, MCP, envíos y tipos de contenido del constructor;
  - pantallas de WordPress: temas, actualizaciones, medios y entrada nueva;
  - Migración, montado y activo;
  - cada pestaña de Ajustes y Herramientas, el diálogo «Añadir plantilla» y cada botón de la barra superior de Editor One;
  - las rutas de la app de kits y del Theme Builder;
  - en el editor: cada pestaña de la biblioteca, el panel de un widget seleccionado (con su enlace de ayuda), los vídeos con su valor por defecto, los ajustes del sitio y de la página, revisiones, navegador, atajos, buscador, notas, el menú contextual y cada botón de su barra;
  - las pantallas de F7a: la sección «Preset de estilo» y el menú contextual de presets (con el diálogo «Guardar estilo como preset…»), la pestaña «Presets de estilo» de Ajustes del sitio (con el diálogo de borrar), el degradado avanzado, «Máscara», el popover «Patrón», el aviso y el estado del sticky, los controles de documento y, en la cabecera, la prioridad de la plantilla; un control o una sección que no aparece es un error. Antes de cada paso, `reset` deja el editor limpio (cancela los diálogos, cierra sólo los paneles laterales abiertos, vuelve al modo edición y abre el panel) y `settle` espera a que haya documento actual y ningún elemento de la vista previa con su render remoto pendiente: si el del widget de vídeo vuelve mientras se abre o se cierra «Ajustes del sitio», el núcleo lanza en `onModelRemoteRender` y el editor queda sin documento para el resto de los pasos (también se espera, sin anotar nada, antes de cada estado previo del editor). El menú contextual se abre con el evento `contextmenu` sobre el elemento de la vista previa (el clic derecho queda de respaldo). Con un tema de bloques, la vista previa de la cabecera no se adjunta: si el panel no tiene «Ajustes de la página», la prioridad se comprueba en la configuración del documento (etiqueta y ayuda) y queda una nota. La sección «Privacidad» de «Rendimiento» se audita aparte. El paso `setup-f7a.php` siembra el preset `marca-titulo`;
  - un popup y una cabecera en el editor;
  - cada menú de la barra de administración del frontal.
- `STATE=kit` importa un kit sintético de terceros y audita la App de importación, la pantalla «Kits» y las páginas importadas (ver «Kits de Elementor»).
- `STATE=migrado` prepara un sitio que viene de Elementor: telemetría y beta activas, instalación de hace 60 días, cuenta conectada con un token ficticio y las cachés remotas guardadas. Las cachés (`brand-audit/migrado.json`) las descarga el arnés con `curl`; si una falla, se guarda un ejemplo con URL del proveedor.
- `ORIGINAL=1` instala el plugin Elementor de wordpress.org, inactivo, y un Elementor Pro con sólo sus cabeceras. Sus filas (`tr[data-plugin]`, la tabla de actualizaciones y su ventana de detalles) quedan excluidas, y también las imágenes que piden. La nota propia `digitalisimo-plugin-note` no se excluye. La exclusión debe aplicarse al menos una vez en `plugins.php`; si no, la corrida falla. En el servidor sólo se permite que WordPress consulte en wordpress.org las actualizaciones y traducciones del plugin instalado.
- `LATENT=fail` hace fallar también lo latente.

Las excepciones van en `brand-audit/allow.json`, cada una con pantalla, URL, fuente y motivo. Una excepción que no se use en toda la corrida falla. El informe agrupa las fallas por fuente, pantalla y dominio, con dos ejemplos y su ubicación (selector, script o llamador PHP). Después resume por dominio. El detalle completo queda en `$OUT/vendor-urls.json`, con un resumen por dominio y por pantalla.

```bash
OUT=/tmp/aud LOCALES=es_MX PORT=9449 bash tests/playground/brand-audit.sh            # completa, un idioma
SCOPE=quick STATE=migrado bash tests/playground/brand-audit.sh                         # sitio que viene de Elementor
ORIGINAL=1 bash tests/playground/brand-audit.sh                                        # con el plugin original instalado
```

`tests/elements-vendor-static.mjs` hace la misma revisión sin Playground sobre el código editable de DIGITALÍSIMO Elements. Busca los dominios de `vendors.json` y los vídeos del proveedor en `core/`, `modules/`, `license/`, `assets/js` (fuente y minificado), `plugin.php`, `pro-elements.php` y `digitalisimo-*.php`. Los comentarios no cuentan. Las excepciones, cada una con su motivo, están en `tests/elements-vendor-static.allow.json`; una excepción sin uso falla. El núcleo incluido (`elementor/`) no se revisa ahí.

## Kits de Elementor (etapa K)

`kit-roundtrip.sh` prueba el importador y el exportador de kits de DIGITALÍSIMO Elements en Playground (modo servidor, un worker por sitio) y Chrome sin interfaz. Nada sale a internet: Chrome resuelve `a.test`, `b.test` y `playground.test` a sus puertos y todo lo demás a `NOTFOUND`, y en PHP el simulador de red (`kit-roundtrip/net-sim.php`, en `pre_http_request` con prioridad `PHP_INT_MAX - 1`, después del proxy de medios de K) anota cada petición que el producto deja salir, con su host, si el contexto de K estaba activo y quién la pidió. Sólo sirve los hosts de demostración de los kits sintéticos desde `$OUT/shared/remote/`; el sitio A y el proveedor reciben un error.

- **A** (`http://a.test`, puerto 9461) se prepara una vez con `kit-roundtrip/cases.json`: cada documento se guarda con `Document::save()`, como el editor. Tiene los 44 widgets propios visibles, el Slider Optimizado y los 7 alias, los rasgos, los tres motores de condiciones, el Theme Builder en cinco ubicaciones, popup, loop con plantilla alterna y filtro de taxonomía, formulario, menús, fuente e iconos propios, kit con globales, valores por defecto y fondo con imagen, separadores `digi-*` y máscaras. `setup-a.php` vuelca además los controles de todos los widgets registrados (inventario en tiempo de ejecución, §9.2) y las referencias que anotó (`refs.json`): todo control que parece guardar un ID (consultas, plantillas, menús, usuarios) tiene que estar en el registro de referencias de K, en lo que el núcleo ya traduce (según el método de importación de cada widget, que se vuelca como `import`: el rasgo de PRO filtra por nombre y Loop Grid, Plantilla y Nav Menu sólo traducen lo suyo) o en `tests/sources/kits/no-reference.json` con su motivo, y nunca en dos de esos sitios a la vez, y A exporta su kit por REST (el cuerpo de la App) y por `wp digitalisimo-elements kit export`.
- **B** (`http://b.test`, puerto 9462) es un sitio nuevo en cada escenario: sin el contenido por defecto y con los autoincrementos de entradas y términos desplazados a 100000, así un ID de A sin traducir nunca coincide por azar (`unresolved:<id>`).
- **La red** de R6 (`http://playground.test`, puerto 9463) tiene el sitio 2 en `/sub/` y el 3 en `/tercero/`, un administrador del sitio 2 sin `unfiltered_html` y `upload_filetypes` sin SVG.

| Escenario | Qué comprueba |
| --- | --- |
| R1 | Ida y vuelta en «nuevo» por la pantalla «Kits» y por `--mode=fresh`: firma de datos de A igual a la de B (con IDs de elemento y referencias resueltas), export(A) = export(B), REST = WP-CLI (también el kit exportado: los mismos archivos y el mismo JSON), render en 360, 768 y 1280 px (≤ 0,5 % de píxeles, sin errores JS ni 404) y ninguna petición al host de A (A1, A2, A4, A6). |
| R1c | El cuerpo exacto de la App, capturado en Chrome (la prueba no sale de la App hasta que termina su último runner), y una variante con selección parcial (CV-14). |
| R2, R3, R3b | Kits de terceros sintéticos (marketplace, Pro 3.x en 2.0 y 1.x) en «agregar» sobre B vacío, frente al `expect` de su spec; R2 aplica después los ajustes del kit y R3 abre el editor con un widget desconocido (A3, A4, A14). |
| R4, R4v4 | «Sólo agregar» sobre B con contenido de choque (y, en R4v4, variables y clases v4): lo previo no cambia y el kit se compara campo a campo (A6, A7). |
| R5 | Reimportar el mismo kit no crea filas ni archivos (A8). |
| R6 | Importación en el sitio 2 de la red: los sitios 1 y 3 y `sitemeta` no cambian (A9). |
| R7 | Sin proveedor: `file_url`, `source=cloud` y `process-media` responden 403 y nadie llega a sus dominios (A5). |
| R8 | Revertir deja B como su instantánea; cada documento que creó la sesión sale de B, como hace el núcleo: páginas y entradas a la papelera (D16) y plantillas borradas del todo (`Document::delete()`), y lo que B ya tenía no cambia (A10). |
| R9 | Permisos (una editora recibe un rechazo: 404 del núcleo, o 401/403) y ZIP hostiles construidos en la prueba (A11). |
| R10 | Un kit subido en Plantillas llega al importador de kits con un token de un solo uso (CV-16). |
| R13 | Con `SLOW=1`: cien imágenes en un host lento; ninguna petición REST pasa de 25 s (A13). |

Los tres sitios usan el tema DIGITALÍSIMO (`digitalisimo-theme/` del repositorio, montado y activado en la preparación, antes de cualquier instantánea) y la misma identidad (nombre y descripción), que nunca viaja en un kit: un tema de bloques pinta su propia navegación de reserva y no imprime las ubicaciones del Theme Builder, así que la comparación de capturas mediría el tema y no el kit. Una editora no ve las rutas del importador (el núcleo sólo registra el módulo de kits con `manage_options`): recibe 404, no 403, y R9 lo acepta como rechazo.

Opciones: `SCENARIOS=R1,R4`, `QUICK=1` (R1 sólo por REST, R4 y R9; no vale para la aceptación), `CORE=plugin` (el plugin Elementor de wordpress.org instalado y activo; para R1 y R3), `WOO=1` (WooCommerce: plantillas de producto, productos, variaciones e imagen de categoría), `SLOW=1`, `KIT_GOLDEN=write` (reescribe `kit-roundtrip/golden/` con la firma de R1, que las corridas siguientes comparan) y `PREPARE=a|b` (sólo prepara ese sitio y lo deja arrancado). Los puertos son 9461, 9462 y 9463 (`PORT_A`, `PORT_B`, `PORT_NET`); el arnés rechaza los de otros arneses y sólo libera un puerto ocupado por una corrida anterior suya. `OUT` (por defecto `/var/tmp/digi-k/kit-roundtrip`) y `TMPDIR` necesitan 2 GB libres. WP-CLI corre dentro del sitio (`kit-roundtrip/cli.php`) con `wp-cli.phar`, que el arnés descarga una vez a `KIT_CACHE` (o `WP_CLI_PHAR`). Los sitios montan una copia fija de `digitalisimo-elements/` y `digitalisimo-theme/` tomada al empezar (`$OUT/src`, con la lista de archivos sin confirmar en `summary.json`), así un cambio en el árbol a mitad de corrida no mezcla versiones entre escenarios; `LIVE=1` monta el árbol de trabajo.

`SCENARIOS=RX KIT=<ZIP>` es un diagnóstico que no cuenta para A1–A14: importa ese ZIP en «agregar» sobre un B vacío y anota cada runner y los errores fatales (sirve, por ejemplo, para aislar qué parte de un kit rompe una importación).

`$OUT/summary.json` guarda el SHA (y, en `src`, los archivos sin confirmar de la copia montada), los escenarios con su duración y sus problemas, la petición REST más lenta de toda la corrida (A13 suma cada escenario: `rest-all.jsonl`) y el estado de A1 a A14; un error fatal de cualquier escenario (`fatal-all.log`) hace fallar la corrida; antes de publicar se copia a `docs/fusion/k-kits-evidence.json`. Las diferencias aceptadas entre A y B están en `kit-roundtrip/known.json` (una entrada puede limitarse a unos valores con `valor_a` y `valor_b`; y, en su lista «opciones», las opciones que WordPress o el constructor escriben solos, como `fresh_site`, `*_children` o `elementor_log`, cada una con su motivo). Las cachés derivadas (condiciones del Theme Builder y conjuntos de iconos) no se aceptan por nombre: la instantánea las guarda decodificadas y se comparan por contenido, iguales o, en «agregar», sólo con entradas nuevas. Las metas del kit también se comparan por contenido, sin las marcas que el constructor reescribe en cada guardado (versión, índice de clases globales). En `uploads` sólo se aceptan los temporales de K (`digitalisimo-elements/tmp/<token>/`) con su `.htaccess` y su `index.php` y con la limpieza `digitalisimo_elements_kits_cleanup` programada; la copia extraída del núcleo (`elementor/tmp/`) debe desaparecer. R1 compara además capturas con zonas quitadas (`display: none` en A y en B) declaradas en `known.json` («capturas»: el código QR, la caja de autor, autor y comentarios de una entrada), cada una con su motivo y lo que nunca debe viajar en un kit propio (opciones internas, metas efímeras, correos, comentarios, contraseñas, webhooks) en `kit-roundtrip/not-exported.json`, cada entrada con su motivo; una entrada que no se usa hace fallar la corrida. Los kits de terceros son sintéticos y deterministas (`tests/sources/kits/`, con `build.mjs` y `fixtures.lock.json`): ningún kit real entra en el repositorio.

```bash
OUT=/var/tmp/digi-kits bash tests/playground/kit-roundtrip.sh
SCENARIOS=R1 QUICK=1 bash tests/playground/kit-roundtrip.sh
SCENARIOS=R2,R3 CORE=plugin bash tests/playground/kit-roundtrip.sh
SCENARIOS=R6 bash tests/playground/kit-roundtrip.sh
SCENARIOS=RX KIT=/var/tmp/digi-k/mi-kit.zip bash tests/playground/kit-roundtrip.sh
SCENARIOS=R1 SLOW=1 bash tests/playground/kit-roundtrip.sh
OUT=/var/tmp/digi-aud-kit PORT=9464 SCOPE=quick STATE=kit bash tests/playground/brand-audit.sh
OUT=/var/tmp/digi-aud-kit-full PORT=9464 SCOPE=full STATE=kit bash tests/playground/brand-audit.sh
OUT=/var/tmp/digi-aud-kit-envato PORT=9464 SCOPE=quick STATE=kit KIT_FIXTURE=tercero-envato bash tests/playground/brand-audit.sh
```

`brand-audit.sh STATE=kit` (puerto 9464 por defecto) importa el kit sintético `KIT_FIXTURE` (por defecto `tercero-pro3`) con `wp digitalisimo-elements kit import`, con el sitio ya en marcha (`brand-audit/kit-cli.php` y el `wp-cli.phar` local de `KIT_CACHE`), y, con cualquier `SCOPE`, recorre además la App de importación (`#/import-customization`, `/content` y `/complete`), la pantalla «Kits» con el informe de esa sesión y cada documento importado (la vista previa de los borradores de «agregar»).

## Presupuesto del front y puerta de PRO (F-front)

`front-budget.sh` arranca Playground en modo servidor (`PORT`, por defecto 9441: no correrlo a la vez que `brand-audit.sh`) con una **copia fija** del plugin tomada al empezar (`$OUT/src`, como `kit-roundtrip.sh`; `LIVE=1` monta el árbol), crea una página `digi-fb-<caso>` por caso de `front-budget/cases.json` y mide cada una en Chrome sin caché. Con el modo normal compara con `front-budget/baseline.json`; los demás modos sólo exigen su `expect` y guardan `front-budget-<modo>.json`. Cada resultado anota la versión y la huella de `ELEMENTS_DIR`, el SHA de git, el modo y la duración.

| Variable | Por defecto | Qué hace |
|---|---|---|
| `ELEMENTS_DIR` | `digitalisimo-elements/` | carpeta del plugin medido (p. ej. el ZIP publicado de una versión anterior, extraído) |
| `BASELINE_FILE`, `BASELINE=write` | `front-budget/baseline.json` | línea base del modo normal; `write` la guarda (con `LIGHTHOUSE=1`, en la misma pasada) |
| `EXPECT=0` | `1` | sólo mide: no exige `expect` ni compara (la medición «antes» de una versión sin la puerta) |
| `CASES=a,b` | los del modo | casos a medir; por defecto los que nombran el modo en `modes` (sin `modes`, sólo el normal) |
| `LIGHTHOUSE=1`, `LIGHTHOUSE_PATH` | la carpeta de `puppeteer-core` | Lighthouse móvil en `empty`, `all-own` y `pro-heavy`; exige 13.5.0 (`lhr.lighthouseVersion`) y un `{error}` hace fallar |
| `ELEMENTOR_PIN` | `4.3.5` | versión del plugin Elementor de `CORE=plugin` |
| `NORMAL_FILE` | `$OUT/front-budget.json` | medición normal con la que `LAZY=1` compara el CLS de `lottie-above` |
| `KEEP=1` | — | sólo prepara el sitio y lo deja arrancado (depuración) |
| `COMPARE_FILE`, `COMPARE_STRICT` | —, `empty,all-own` | `front-budget[-modo].json` de otra corrida del mismo modo (p. ej. el ZIP 4.3.0.163 extraído en `ELEMENTS_DIR`): cada caso sin `since` debe dar el mismo HTML y CSS normalizados; en `COMPARE_STRICT`, además los mismos bytes, peticiones, handles y `$wpdb->num_queries` (F7a, criterios 1 y 2) |

| Modo | Qué hace | `expect` propio |
|---|---|---|
| normal | sitio nuevo con el núcleo incluido | la tabla de casos y la línea base |
| `CACHE=1` | sin `elementor_element_cache_ttl` (caché de elementos activa); cada página se visita dos veces y se mide la segunda | las mismas decisiones de la puerta |
| `CORE=plugin` | Elementor `ELEMENTOR_PIN` de downloads.wordpress.org, activo junto a Elements | las mismas decisiones; ningún filtro `default_option_` del módulo (`?digi_probe=state`) |
| `SEO=1` | DIGITALÍSIMO SEO con `perf_js_jquery_mode = defer` | `defer` en jQuery y `elementor-frontend` (`empty`) y en `pro-elements-handlers` (`pro-popup`), popup visible, `digitalisimo_performance_js_fallback` vacía |
| `SEO=guard` | SEO con el blindaje de fuentes (`auto`, modo seguro apagado), fuentes de Google locales, sitio antiguo y el kit en Lato 400/700 (archivos estáticos por peso: con una fuente variable como Roboto el blindaje no tiene nada que recortar y, con razón, no crea copias). Tras la medición previa, la sonda hace lo de «Optimizar fuentes» (`?digi_probe=fontguard`) y se exige la copia servida | tras la transición, una visita y dos pasadas de cron (cada una espera a que venza el evento de SEO, agendado a `time() + 10`), el `<link>` apunta a una copia `digitalisimo-` nueva con `swap` |
| `THEME=1` | tema `digitalisimo-theme`; una cabecera del Theme Builder con un encabezado del núcleo y otra con el Menú de PRO, activadas de una en una por la sonda | `tb-header-core` sin paquetes y `tb-header-pro` con ellos en página, archivo, búsqueda, 404 y página clásica |
| `MIGRATION=1` | DIGITALÍSIMO Migración activo: `bdt-navbar`, `bdt-contact-form` y `bdt-countdown` | `reason: class:` y menú, formulario y cuenta atrás funcionando |
| `LAZY=1` | `lazy_libs` elegido en el sitio (con `THEME=1`, sobre el tema clásico; la referencia de CLS es entonces una corrida `THEME=1` en `NORMAL_FILE`) | `lottie-below` y `code-below` cargan al desplazar; `lottie-above` con CLS ≤ normal + 0,005 |
| `V4=1` | experimento atómico activo; página `v4-only` | sin paquetes y con `elementor-frontend` (D19) |
| `DEFAULTS=old` | sitio instalado con 3.16.0 (historial), sin `elementor_font_display` ni experimento de iconos. Mide antes de la transición (`pre`), comprueba que la primera visita agenda el evento, ejecuta `wp-cron.php` y vuelve a medir | `swap` tras el cron; iconos sin cambio |
| `DEFAULTS=old-applied` | `old` y «Aplicar» por la acción real (sesión, pantalla «Rendimiento», formulario con nonce) | sin Font Awesome ni `eicons`, con SVG; con `CACHE=1`, `icons` se calienta con `<i>` antes de aplicar (`warm`) |
| `DEFAULTS=old-dirty` | `old` en una red (`http://digi-fb.test`, que Chrome y Node resuelven al puerto) con `.fa-star{}` en el CSS adicional y `svg_icons` pedido por la red | no se aplica; el hallazgo aparece en `?digi_probe=state` |
| `DEFAULTS=old-inactive` | `old` con el experimento guardado `inactive` y «Aplicar» | no cambia: manda la elección del sitio |
| `DEFAULTS=off` | `old` con los seis interruptores apagados | paquetes en `empty` y `display=auto` |

**Casos** (`front-budget/cases.json`, documentos construidos por `front-budget/lib.php`, el mismo código que usa la prueba de CI `tests/elements-front-cases.php`): `widgets` dentro de un contenedor (`container`: sus ajustes) tras el encabezado de referencia, o `elements` (árbol crudo), o `classic` (sin contenido del constructor); `extra` (popups con disparadores, condiciones y `timing`, plantillas, plantillas de bucle, cabeceras, widget global); `actions` (`click`, `scroll`, `wait`, `waitFor`, `waitCount` —hasta que haya al menos `min` elementos—, `mark` y `waitChange` —hasta que cambie su texto—, `hover`, `openSubmenu` —abre un submenú del menú de PRO con la API de SmartMenus, que en Chrome sin interfaz no reconoce un puntero simulado—, `viewport`, `fill`, `fillAll`); `sequence` (casos que se visitan antes en un perfil limpio, para los contadores de popups); `views` (URL medidas en `THEME=1`); `viewport`; `runtime` (lo decide la petición, no los datos: la prueba de CI lo omite); `pending` (contrato pendiente, se omite); `modes` y `expect`. `expect` se indexa por modo (`*` vale para todos; `old-applied+cache` para la combinación) y por instantánea: `pre` (antes de la transición), `warm` (caché caliente antes de aplicar), la carga y `after` (tras las acciones, con `changed` y `grew` frente a la carga). `reason` se compara por prefijo con cualquiera de las razones; `">0"`, `"<=0.005"` son comparaciones numéricas. Marcadores: `__SITE__`, `__IMAGE__`, `__MENU__` y, por caso, `__SELF__`, `__POPUP__`, `__TEMPLATE__`, `__LOOP__` y `__DOC:<key>__`.

**Casos de F7a.** `since` (versión que los trae: no cuentan en `COMPARE_FILE`), `presets` (se siembran en la opción del sitio con `Store::replace()`), `front_settings` (claves propias de «Rendimiento», p. ej. `page_visits_no_consent`), `page_settings` y `thumbnail` (ajustes del documento e imagen destacada), `save: document` (además se guarda por `Document::save()`, para que corran `save/data` y `after_save`), `styles` (valor calculado, también de `::before`/`::after`), `counts`, `visible`, `hits` (`elementFromPoint` en el centro), `cssCount` (coincidencias en las hojas del mismo origen y en los `<style>`), `assets` (bytes de las peticiones que coinciden), `volatile` (expresiones extra de lo que depende de la hora, que la comparación sustituye; el horario ya lo normaliza `compare-lib.cjs`) y la acción `reload`. Cada medición guarda `queries` (consultas hasta el pie), `queriesWarm` (tres visitas más en `COMPARE_STRICT`), `queriesShutdown`, `handles`, `jsFiles`, `hash`, `pageFile` y la página y su CSS normalizados en `$OUT/pages/`. `front-budget/compare-lib.cjs` normaliza (origen y puerto, `ver=`, nonces, tokens de campos ocultos, JSON y `data-*`, reloj, fecha, `data-today` y estado del horario, y `volatile`) y compara; `node tests/playground/front-budget/compare.cjs <ref.json> <actual.json>` compara dos corridas ya hechas (`CASES_FILE`, `COMPARE_STRICT`, `COMPARE_OUT`).

**Sonda** (`front-budget/probe.php`, mu-plugin sólo de Playground con el token de `mode.json`): imprime `<!-- digitalisimo-pro-gate {…} -->` con `Pro_Gate::reasons()` en `wp_footer` 99, atiende `?digi_probe=state` (aplicado, efectivo, pendiente, `wp_next_scheduled()`, análisis de iconos, filtros `default_option_`, filas del núcleo, respaldo de JavaScript de SEO), `?digi_probe=header&key=<caso>` (`THEME=1`) y `?digi_probe=fontguard` (`SEO=guard`), fuerza `…_needed` en `digi-fb-forced`, da por enviados los correos y apaga el disparo de WP-Cron desde las visitas: el arnés ejecuta `wp-cron.php` hasta que no queda nada de la transición, así la medición no depende del lazo de red de Playground. El complemento falso `front-budget/fake-addon/` registra un widget de terceros, otro que su filtro declara libre y, en `digi-fb-third-party-script`, un script que depende de `elementor-pro-frontend`.

Procedimiento de la Fase 2 (§5.7): la medición «antes» se toma con el arnés de la rama sobre el ZIP publicado (`ELEMENTS_DIR=<ZIP extraído> EXPECT=0 BASELINE=write BASELINE_FILE=… LIGHTHOUSE=1`), la rama se mide en modo normal (`BASELINE=write LIGHTHOUSE=1`, línea base nueva) y en cada modo con su `CASES` por defecto, y la tabla queda en `docs/measurements/`.

```bash
OUT=/var/tmp/digi-fb PORT=9471 BASELINE=write LIGHTHOUSE=1 bash tests/playground/front-budget.sh
OUT=/var/tmp/digi-fb-cache PORT=9472 CACHE=1 bash tests/playground/front-budget.sh
OUT=/var/tmp/digi-fb-lazy PORT=9473 LAZY=1 NORMAL_FILE=/var/tmp/digi-fb/front-budget.json bash tests/playground/front-budget.sh
OUT=/var/tmp/digi-fb-apply PORT=9474 DEFAULTS=old-applied CACHE=1 bash tests/playground/front-budget.sh
```

`front-network.sh` (run-blueprint con Multisite, sin Chrome) cubre la red: cada paso de `front-network/probe.php` es una petición propia al sitio que le toca (front, el wp-cron de ese blog o `admin-post.php` con usuario y nonce, con `wp_redirect` y `wp_die` capturados) y `front-network/report.cjs` juzga N1–N7: herencia del sitio 2 con el evento en su blog, el sitio 1 que personaliza, el sitio 3 creado después, rechazos 403 (editor, administrador de sitio en la red, nonce de otro sitio) y redirección a la misma superficie, «Heredar de la red» sólo en la pantalla del sitio (otro blueprint comprueba el sitio único), un vaciado `elementor/core/files/clear_cache` por sitio que cambia y ninguno en los demás, y las pantallas sin «Elementor», enlaces externos ni dominios de `brand-audit/vendors.json`. La clave de privacidad `page_visits_no_consent` (F7a) recorre los mismos pasos: el sitio 1 la personaliza apagada, `post-network-privacy` la enciende en la red, los sitios 2 y 3 la heredan sin evento de transición ni vaciado, y su casilla y la sección «Privacidad» aparecen en las tres pantallas, con «Heredar de la red» sólo en el sitio de la red.

```bash
OUT=/var/tmp/digi-front-network bash tests/playground/front-network.sh
```

## Diseño compartido

`shared-ui.sh` monta los siete plugins en un sitio y, con un mu-plugin que filtra los plugins activos por petición (cookie `digi_ui_plugins`), abre la pantalla de cada uno con ese plugin solo (Migración junto a Elements, que necesita; de Elements, «Acerca de» y «Rendimiento») y después con los siete activos. En cada una comprueba que haya una sola hoja compartida `digitalisimo-ui`, la clase de body `digitalisimo-admin`, el token `--digi-gradient` válido, el botón primario con el degradado propio y ningún color de la paleta de Elementor; en «Rendimiento», además, la sección «Privacidad» visible con su casilla (F7a), con captura de página entera. Guarda capturas en `$OUT`.

## Código PHP de DIGITALÍSIMO Tools

`tools-php.sh` arranca Playground en modo servidor con un solo proceso (`PORT`, por defecto 9451), primero en un sitio individual con la ruta privada de acceso y después en una red servida como `http://playground.test` con los sitios 2 y 3. Monta Tools, el plugin falso `fake-plugins/digitalisimo-php-redirect` (con su bandera, la portada redirige una vez) y la sonda `tools-php/probe.php` (mu-plugin): banderas por sitio en la opción `digi_php_probe_flags` para `DIGITALISIMO_TOOLS_PHP_SAFE_MODE`, `DISALLOW_FILE_EDIT` y `SAVEQUERIES` (en una opción y no en archivos, porque Playground conserva entre peticiones la caché de rutas de PHP), captura de correos con `pre_wp_mail` y `?digi_php_probe=state|tamper|import|cron|mails` para leer o preparar el estado. El conductor `tools-php/run.mjs` hace lo que haría el navegador del administrador con un tarro de cookies: los tres pasos de «Guardar y activar» (prepare, abrir el escritorio con la sesión y cada portada sin cookies, activate con su reintento) sin que el servidor se pida nada a sí mismo, y juzga por los códigos HTTP, las cabeceras que añaden los fragmentos y el estado de la sonda. Cubre los escenarios de la especificación: fragmento válido, sintaxis, fatal al cargarse, fatal diferido con su conciliación, correo y cron, salida al cargarse, portada que redirige, la constante, el enlace de rescate con la ruta privada (página, cookie, acceso, modo reducido, cookie falsificada o de otro sitio, petición pública limitada), la red (aislamiento, pausa sólo en un sitio, un solo correo, «Reanudar en todos los sitios»), una alteración directa de la base de datos, `DISALLOW_FILE_EDIT`, las consultas sin fragmentos, una importación con `status=active` y el escape de un título y un error con `<script>`.

```bash
OUT=/tmp/digi-tools-php PORT=9451 bash tests/playground/tools-php.sh
```

Todos los arneses cargan `playground-cleanup.sh`: al salir borran las copias temporales del sitio (`node-playground-cli-site-*`, unos 400 MB) que Playground deja cuando su proceso termina por la fuerza, sin tocar las de otra prueba en marcha.

## Condiciones de visualización

`conditions.sh` crea páginas reales con los tres motores (Display Conditions nativo, reglas propias `digitalisimo_visibility_*` y reglas heredadas `ep_display_conditions_*`), en widgets y en contenedores con hijos, con «Mostrar» y «Ocultar», con reglas de sesión, tipo de contenido, página especial y URL, combinadas con el enlace del elemento y las figuras, dentro de plantillas incrustadas (acordeón propio y shortcode de plantilla), y las pinta como visitante, con sesión, otra vez como visitante y en la vista previa del editor, con la caché de elementos de Elementor desactivada y activada. Comprueba que lo oculto no aparece en el HTML y lo visible sí; que quien llena la caché primero (un administrador) no decide lo que ven los visitantes; y que quien llena la caché primero (un administrador) no decide lo que ven los visitantes. `probe-save.php`, en una segunda petición PHP (como el guardado AJAX real), guarda cada página desde el editor como administrador con la caché activa y comprueba que el primer visitante no ve lo que sólo él podía ver. `probe-cache.php` se ejecuta en un proceso PHP limpio por página (una petición de visitante con su consulta principal y `template_redirect`) y comprueba que la página con elementos condicionados pide quedar fuera de la caché de página antes de la salida (`DONOTCACHEPAGE` y, si el SAPI las expone, las cabeceras HTTP) y que una página sin condiciones no lo pide.

Las siete condiciones de F7a (cookie, «Visitó la página», campo, campo del usuario, autor, búsqueda y existencias de productos) se pintan en las mismas dos cachés, como visitante y con sesión, en cada variante de cookie y de consulta, en la vista previa del editor y después como visitante. Comprueban que una lista vacía nunca se cumple, que el centinela `digitalisimo_special_page` deja igual un grupo y que ningún valor (cookie, meta o término buscado) llega al HTML. En `probe-cache.php`, cookie, visitas y existencias sacan la página de la caché de página y las otras cuatro no. Con `WOO=1` el blueprint instala WooCommerce y las existencias se miden con dos productos reales; sin WooCommerce no se cumplen nunca.

`legacy-repair.sh` reproduce el caso real de una portada estática con una regla heredada «ocultar en la página de inicio estática» y la opción `element_pack_elementor_extend` guardada sin la clave de Visibility Controls (activa por defecto en Element Pack): el motor heredado oculta el título en la portada y lo muestra en una interna, la conversión conserva el comportamiento, un documento convertido que perdió las reglas se repara desde su copia original (`Migration::repair_conditions`) y, con la extensión apagada expresamente, la conversión guarda las reglas desactivadas; un documento que sólo conserva un widget sin adaptador se cuenta aparte (`Migration::classify`) y no vuelve a entrar en los lotes; las revisiones no entran en pendientes, una página sobrescrita por la conversión de una revisión se detecta (`Migration::overwritten_parents`) y se recupera desde su última revisión guardada, y convertir una revisión escribe en la revisión y no en la página; sin revisión guardada (autoguardado de una plantilla) se recupera desde la copia original o se da por revisada.


## Capacidades de diseño (F7a)

`design.sh` arranca Playground en modo servidor con un solo proceso (`PORT`, 9481 en un sitio y 9482 con `NET=1`; rechaza los puertos de los demás arneses) y una **copia fija** de `digitalisimo-elements/`, `digitalisimo-theme/` y `digitalisimo-seo/` (`$OUT/src`; `LIVE=1` monta el árbol). Activa el tema DIGITALÍSIMO, crea los documentos de `design/cases.json` con `Document::save()` (corren `save/data` y `after_save`: centinela de las condiciones, copia saneada de los SVG, guardas de presets) y deja los de `at: ajax` para que el conductor los guarde por `elementor_ajax` como el editor. Los presets y degradados salen de las fixtures compartidas con las unitarias (`tests/sources/design/{presets,gradients}.json`). El conductor `design/run.cjs` (con `design/scenarios.cjs`) habla con el sitio por HTTP con tarros de cookies por usuario, mide en Chrome sin interfaz (front y vista previa del editor) y escribe `$OUT/summary.json` con el SHA, el modo, cada comprobación con un nombre estable y la duración de cada escenario y del total. Antes de publicar F7a, los `summary.json` de cada modo van a `docs/fusion/f7a-evidence.json`.

| Modo | Escenarios | Qué cambia |
|---|---|---|
| normal | DS1–DS8, DS13 | núcleo incluido, caché de elementos apagada |
| `CACHE=1` | DS1–DS9 | caché de elementos a 24 h; cada visita se hace dos veces y se exige la misma decisión |
| `CORE=plugin ELEMENTOR_PIN=…` | DS1–DS8 rápidos y DS10 (con una versión < 4.3, sólo DS10) | plugin Elementor de downloads.wordpress.org; compara cada comprobación con el `summary.json` del modo normal (`COMPARE_SUMMARY`, por defecto `/var/tmp/digi-f7a/design-normal/summary.json`) |
| `V4=1` | DS11 | experimento atómico y caché de elementos activos |
| `NET=1` | DS12 | red de subdirectorios (`/uno/`, `/dos/`, IDs repetidos) servida como `http://playground.test`, que Chrome resuelve con `--host-resolver-rules` |
| `WOO=1` | DS1 con un widget de WooCommerce | WooCommerce de wordpress.org |

`SCENARIOS=DS1,DS5` elige escenarios y `QUICK=1` mide un solo ancho (1280 px) sin abrir el editor. `KEEP=1` deja el sitio arrancado y escribe `$OUT/env.json`, con el que `ENV_FILE=$OUT/env.json SCENARIOS=DS3 node tests/playground/design/run.cjs` corre escenarios a mano contra él (la sonda se carga desde la carpeta montada, así que un arreglo del arnés llega sin preparar el sitio otra vez). DS7 monta Hello 3.5.1 desde `reference/hello-elementor.3.5.1.zip` (copia oficial anotada, comprobada por su sha256) y cambia de tema sin `switch_theme()`: volver desde Hello con la activación nativa dejaría el tema en paridad. DS13 extrae el asset `digitalisimo-elements-4.3.0.163.zip` de la Release `v2026.10.21.417` (`OLD_ZIP`, comprobado por su sha256 y `unzip -t`) y lo instala sobre el resultado reemplazando la copia montada entre dos peticiones, como una actualización en el sitio; con `LIVE=1` no se corre.

**Sonda** (`design/probe.php`, mu-plugin sólo de Playground con el token de `mode.json`): imprime `<!-- digi-design-probe {…} -->` (`DONOTCACHEPAGE`, contenido consultado, tema, RTL, las cabeceras y pies del Theme Builder con el filtro de prioridad y sin él, que es el orden de 4.3.0.163, y las razones de la puerta de PRO), fuerza RTL en los documentos `rtl`, imprime un marcador en `digitalisimo_before_header`, anota `litespeed_purge_post`, simula la WP Consent API (bandera `consent_api`: `wp_has_consent()` en PHP y en el navegador, con el evento `wp_listen_for_consent_change`) y atiende `?digi_design=` (`ping`, `ids`, `tree`, `data`, `save`, `controls`, `flag`, `front`, `theme`, `clearcss`, `kitimported`, `file`, `state`, `sitemeta`, `blogopts`). Un mu-plugin aparte anota los errores fatales en `fatal.log` y los avisos de PHP de Elements (fuera de `elementor/`) en `php-errors.log`.

| Escenario | Qué comprueba |
|---|---|
| DS1 | Crea los presets por `elementor_ajax` (su `code`, no el estado HTTP) y guarda los documentos vinculados; en 360, 768 y 1280 px el estilo calculado del elemento vinculado es igual al de su referencia (lo local más lo que aporta el preset) y distinto del de sólo lo local cuando el preset aporta algo, también en RTL; la vista previa del editor da lo mismo en cada modo de dispositivo; «Usar sólo el preset» y «Desvincular y copiar estilos» desde el menú contextual real dejan el mismo aspecto (píxeles ≤ 0,5 %); borrar con usos da 409; actualizar un preset regenera el CSS; un editor y un rol con la restricción `design` reciben 403 en las cuatro acciones y su guardado conserva el vínculo; el CSS del kit no cambia |
| DS2 | `background-image` calculado de cada degradado en tres anchos y en hover, frente a la fixture (con las diferencias declaradas de un sitio real), y la vista previa igual al front |
| DS3 | `mask-image` y `-webkit-mask-image` con `data:` en porcentaje en contenedor, sección, columna y SVG del sitio; SVG hostil sin regla y visible; una definición por variable; ninguna petición a SVG; la copia `masks/<sha8>.svg` y su control oculto; la vista previa |
| DS4 | `::after` del patrón, clic sobre el botón y el desplegable del menú sobre el contenedor siguiente (aprueba o reprueba con «Elevar» por defecto; con «Elevar» apagado se anota); 20 usos con una sola variable; cabecera con patrón |
| DS5 | Índice de páginas rastreadas (borrador, revisión e importación fuera; purga), cookie y script con la opción, con el filtro, con la WP Consent API sin y con consentimiento; caché de página; el valor de una cookie nunca en el HTML |
| DS6 | Sin manuales, el orden de 4.3.0.163; manual, número menor, ID mayor, `exclude` y `?theme_template_id=` |
| DS7 | Ocultar cabecera y pie (plantillas y respaldo del tema), el gancho `digitalisimo_before_header`, `og:image`, imagen destacada en la plantilla y en bucles; con Hello, sin esos controles y la página igual; el tema sin cambios |
| DS8 | Sin destello, `show`/`hide` y sus variantes de efectos, salto ≤ 1 px de la sección siguiente, CLS fuera del contenedor fijo, `bottom` sin oscilación, dispositivo fuera de `sticky_on`, hoja sólo donde se usa, editor |
| DS9 | Logotipo dinámico e hijo con condición dentro del fijo, imagen destacada en la plantilla compartida, en cualquier orden de visitas |
| DS10 | Núcleo del plugin original; con una versión < 4.3, guardas sin errores y F7a inerte; con 4.3.x, mismos resultados que el modo normal |
| DS11 | Sin controles de F7a en los atómicos; centinela en el prop v4; elemento v4 con condición dinámico con la caché a 24 h; el modal abierto desde v4 sin las condiciones de F7a |
| DS12 | Cookie y ruta por sitio sin cruce con IDs repetidos; presets, prioridad e índice en su sitio; `sitemeta` igual salvo `_site_transient_*` |
| DS13 | Con 4.3.0.163 sobre el resultado: lo condicionado por F7a no se ve, orden automático, cabecera y pie visibles; tras regenerar el CSS, sin máscaras ni patrones, degradado A → B, `show` visible y presets en sus valores locales; editor y páginas sin errores fatales ni avisos de PHP |

```bash
bash tests/playground/design.sh                                   # normal → /var/tmp/digi-f7a/design-normal
CACHE=1 bash tests/playground/design.sh                           # DS9
CORE=plugin ELEMENTOR_PIN=4.0.0 bash tests/playground/design.sh   # DS10, mínima admitida
CORE=plugin ELEMENTOR_PIN=4.3.5 bash tests/playground/design.sh   # DS10, última
V4=1 bash tests/playground/design.sh                              # DS11
NET=1 bash tests/playground/design.sh                             # DS12
WOO=1 bash tests/playground/design.sh                             # DS1 con WooCommerce
```

## Tema DIGITALÍSIMO

Los dos arneses del tema nunca montan `digitalisimo-theme/` del repositorio: lo construyen con `scripts/build-packages.sh` (que pasa la guarda `THEME PACKAGE CHECK`) y usan la copia extraída en `$OUT`. El sitio de prueba no sale a internet: un mu-plugin de la prueba responde o bloquea toda petición del servidor, y Chrome resuelve todo host externo a `NOTFOUND`.

Playground copia el sitio en `$TMPDIR` (unos 400 MB) y Chrome guarda allí su perfil. Con `/tmp` en un tmpfs casi lleno las escrituras fallan («Unknown system error -122», `errno=28`) y aparecen falsos fallos de carga, de CSS y de carrito; los dos arneses avisan si `$TMPDIR` u `$OUT` tienen menos de 2 GB libres. En ese caso: `TMPDIR=/var/tmp/digi OUT=/var/tmp/digi/out bash tests/playground/theme-switch.sh`.

### A/B frente a Hello (`theme-switch.sh`)

Arranca Playground en modo servidor (sitio simple, `--workers=1`, puerto `PORT`, por defecto 9453) con DIGITALÍSIMO Elements (núcleo incluido), SEO, Tools, WooCommerce, Hello Elementor 3.5.1 (copiado desde `reference/hello-elementor.3.5.1.zip`, que no se versiona; si el worktree no lo tiene se usa el del worktree principal, o `HELLO_ZIP`) y un hijo sintético de Hello. El contenido es el mismo en todas las fases: portada con encabezado, imagen, botón y Slider Optimizado; página del constructor; entrada con extracto, etiquetas, miniatura y comentarios anidados; la entrada más reciente con el título oculto por el constructor; categoría, blog (con paginación), búsqueda y 404; páginas Canvas, de ancho completo y corta; carrusel anidado; una página cuyo título oculta un fragmento con `hello_elementor_page_title`; y, con WooCommerce, tienda, producto sin plantilla y carrito con un producto (cada fase lo añade con `?wc-ajax=add_to_cart`, que no deja el aviso «añadido al carrito»: así ninguna fase lo ve en un ancho distinto que A).

```bash
bash tests/playground/theme-switch.sh                              # aceptación: S1-S5, S3c, S3h, D, E, F, F2, WOO=1, tres anchos
WOO=0 QUICK=1 SCENARIOS=S1,S3 bash tests/playground/theme-switch.sh   # iteración local (no vale para la aceptación)
MODE=measure BASE=https://staging.example PATHS=/,/blog/ bash tests/playground/theme-switch.sh   # sólo con autorización
```

Escenarios (cada uno parte de Hello sin rastro del anterior, con `?digi-ab=scenario`):

- **S1**: sin constructor de temas, cabecera clásica (el experimento de cabecera y pie de Hello apagado), con el fragmento de `hello_elementor_page_title` (FR-9).
- **S2**: plantillas del constructor de temas para cabecera, pie, entrada y archivo con `include/general`.
- **S3**: cabecera y pie dinámicos de Hello (experimento activo y ajustes `hello_header_*`/`hello_footer_*` en el kit). **S3c**: el cambio lo hace un administrador, pero la primera petición siguiente es anónima; el registro queda pendiente con el evento programado y `?digi-ab=cron` genera las plantillas. **S3h**: los tres interruptores de la cabecera apagados; no hay cabecera ni en A ni en B (se comprueba en cada URL).
- **S4**: hijo de Hello con reglas propias, un encolado que depende de `hello-elementor-theme-style`, un shortcode y una plantilla de página que sólo existen en él. Se comprueba el inventario «Antes de cambiar de tema» de Tools, la bitácora (`child`, `filters`, avisos) y `import --child-css` (las `url()` relativas pasan a absolutas).
- **S5**: como S1 sin SEO: A tiene una meta description (la de Hello) y B ninguna, declarada, con el aviso `description_lost` en la bitácora y en Tools.

Fases de cada escenario:

- **A**: Hello; captura cada URL en 360×800, 768×1024 y 1280×900 con `prefers-reduced-motion: reduce`.
- **B**: `switch_theme()` como administrador desde la ruta de prueba y una petición de calentamiento en la administración (en S3c, una visita anónima y el cron). Comprueba el registro (`origin.family = hello`, paridad, modo de cabecera y pie, plantillas generadas publicadas, tamaño y carga automática) y compara con A.
- **C**: `digitalisimo_theme_revert()`; las firmas (a 360 y 1280 px) vuelven a ser las de A, igual que el md5 de los mods de Hello (sin `sidebars_widgets`) y de `hello_elementor_settings_*`; en S3 las plantillas generadas quedan en la papelera.
- **C2**: otra vez al tema; las plantillas generadas vuelven a publicarse sin duplicados.
- **D** (S1): rodeo por `WP_DEFAULT_THEME` y vuelta (`origin` sigue en `hello`); con la paridad apagada por la prueba, sólo `base.css` (5 827 B y 1 399 B gzip como máximo), sin desplazamiento a 360 px y con la paginación en fila.
- **F** (S2, S3): la vista previa del editor de la cabecera responde 200 con su marcado una sola vez.
- **F2** (S1): con el tema activo, el AJAX `elementor_activate_hello_theme` y la ruta REST `elementor/v1/onboarding/install-theme` responden 403 y el tema sigue activo; el arnés lista las rutas REST del asistente que encuentra registradas.
- **E** (al final): sin DIGITALÍSIMO Elements, los respaldos `fallback/` (Canvas y ancho completo), el aviso en Temas y ningún error fatal.

Comprobaciones por URL y ancho (B frente a A): estado HTTP; firma (texto visible, enlaces e imágenes visibles, y los `meta name`/`link rel` del `<head>`); número de meta description; número de `h1`; cajas de la cabecera, `main`, el pie y `.page-header h1` (±2 px); `scrollWidth` sin desbordar (con los elementos que desbordan); errores de consola y 404 propios; diferencia de píxeles de 0,5 % como máximo (canal con diferencia mayor de 16; salvo portada y carrusel); hojas del tema sólo `digitalisimo-theme*` y sin más bytes que las de A; nodos del DOM como mucho A + 5; la hoja de un tercero que depende de `hello-elementor-theme-style` sigue impresa (alias P-4); ninguna URL del DOM ni de la red hacia un plugin de origen (`brand-audit/vendors.cjs`); en S3, color, tamaño, peso y familia del título, el lema y el primer enlace del menú; tienda y producto sin `#sidebar`; un solo `#content` al que llega el enlace de salto. Además, el registro de PHP (`php.jsonl`: avisos, obsolescencias y `_doing_it_wrong`) no puede tener en B nada que no tuviera A, ni nada nacido en el tema, ni el `_deprecated_file` de `theme-compat`; y `fatal.log` debe quedar vacío.

Las diferencias aceptadas están en `theme-switch/known.json`, cada una con escenario, fase, URL, ancho, comprobación, detalle y motivo, y opcionalmente un tope (`max`) para la magnitud aceptada (por ejemplo, cuántos nodos de más). Una entrada que no se use en la corrida hace fallar la prueba (con `WOO=0` no cuentan las marcadas `woo`, y con `QUICK=1` las marcadas `full`).

Variables:

| Variable | Valor por defecto | Uso |
|---|---|---|
| `WOO` | `1` | `1` instala WooCommerce (obligatorio para la aceptación, FR-1); `0` sólo para corridas rápidas |
| `SCENARIOS` | `S1,S2,S3,S3c,S3h,S4,S5` | escenarios a ejecutar; `SKIP_E=1` omite la fase E |
| `QUICK` | — | `1`: siete URL (diez con WooCommerce) y dos anchos, C en un ancho (iteración local) |
| `OUT` | `/tmp/digi-theme-switch` | `theme-switch.json`, capturas `shots/{A,B}-<escenario>-<url>-<ancho>.png` y `shots/D-…png` (píxeles distintos en rojo), `php.jsonl`, `http.jsonl`, `fatal.log` |
| `PORT` | `9453` | puerto del servidor de Playground |
| `HELLO_ZIP` | `reference/hello-elementor.3.5.1.zip` | ZIP de Hello 3.5.1 (se comprueba su sha256) |
| `MODE` | `ab` | `measure`: mide `BASE` sin Playground y sin cambiar el tema |
| `BASE`, `PATHS`, `RUNS` | —, `/`, `5` | con `MODE=measure`: sitio de staging (sólo con autorización), rutas y corridas en frío y en caliente; mediana de TTFB, LCP y CLS, bytes por hoja o script y nodos en `$OUT/theme-measure.json` |

La ruta de prueba (`?digi-ab=<op>&token=…`, mu-plugin `theme-switch/mu-ab.php`) sólo existe en el sitio de Playground y exige el token aleatorio que el arnés deja en `$OUT/token`.

### Red Multisite (`theme-network.sh`)

`run-blueprint` sin Chrome: una red con el sitio principal en el tema por defecto y un subsitio que tiene activa la segunda copia del tema, `digitalisimo-theme-1.0.0` (carpeta con punto, RL-8). DIGITALÍSIMO Tools está activo en la red; ambos salen de `build-packages.sh`: Tools se monta desde `$OUT` y las dos copias del tema se copian al sitio (así la actualización puede reemplazarlas sin tocar un montaje). Un mu-plugin de la prueba sirve un índice de Releases falso con `plugins` en las versiones de las cabeceras (ninguna se ofrece como nueva, P-5) y `themes.digitalisimo-theme` en la versión siguiente, cuyo ZIP es el mismo tema con la versión cambiada; responde vacío a wordpress.org, anota toda consulta a `api.github.com`, bloquea lo demás y apaga `auto_update_plugin` y `auto_update_core`.

```bash
bash tests/playground/theme-network.sh            # todos los pasos
ONLY=setup,n5 bash tests/playground/theme-network.sh   # depuración de un paso («setup» siempre hace falta)
```

| Paso | Qué comprueba |
|---|---|
| **N1** | Con el actualizador del tema sin cargar (sitio principal) y otra vez desde el subsitio (RL-12), `update_themes->response` tiene las dos copias como array con `package`, sin `autoupdate`, con el `id` del tema; ninguna consulta a la API de GitHub |
| **N2** | La sección «Tema» de la red tiene una fila por copia con «Actualización disponible», Actualizar y Buscar actualizaciones, y nunca Activar ni Habilitar; la del subsitio sólo muestra el estado. Una URL de actualizar fabricada en el contexto del sitio y un nonce de otra copia terminan en `wp_die` |
| **N3** | «Actualizar» de Tools en la red deja la copia con punto en la versión siguiente en su misma carpeta; el tema del subsitio, el md5 de sus mods y `allowedthemes` (red y sitio) no cambian; `.maintenance` existió durante la instalación y no quedó después |
| **N3f** | Con un `$wp_filesystem` cuyo `move()` falla, el handler vuelve con `update_failed`, las dos carpetas siguen en su versión y el subsitio no cambia de tema (RL-1) |
| **N4** | `wp_maybe_auto_update()` no actualiza un tema que no está en `auto_update_themes` y sí tras añadirlo (la otra copia no se toca); con `requires` 99.0 en el índice la entrada lleva `disable_autoupdate` y no se instala sola (RL-9) |
| **N5** | La ficha `admin-post.php?action=digitalisimo_theme_details` lleva `digitalisimo-admin` en `body` y el enlace con `noopener`, sin avisos de PHP en la salida ni en el registro (RL-13) |
| **N6** | Con el tema activo en el sitio principal su actualizador escribe la entrada y Tools no la cambia; con los filtros del tema retirados (actualizador roto), Tools escribe la de respaldo (RL-3) |

En N2 y N5, además, ninguna URL de la pantalla va a un plugin de origen (`brand-audit/vendors.cjs`) y los avisos de PHP nuevos (o nacidos en Tools o en el tema) hacen fallar; un error fatal en cualquier paso (`fatal.log`) también. Resultado en `$OUT/result.json` (por defecto `/tmp/digi-theme-network`), con `http.jsonl`, `php.jsonl`, `upgrade.jsonl` y las pantallas guardadas.
