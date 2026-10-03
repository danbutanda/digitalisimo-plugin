# Objetivo activo: Rendimiento Multisite de DIGITALÍSIMO SEO

Fuente: solicitud del propietario del 2026-09-30 (`Texto pegado.txt`, objetivo «DIGITALÍSIMO SEO — RENDIMIENTO MULTISITE»). Amplía el [objetivo conservador anterior](PERFORMANCE_OBJECTIVE.md); no lo sustituye ni declara cerradas sus pruebas pendientes.

## Arquitectura y límites

- Un solo plugin cambia: DIGITALÍSIMO SEO. Los Releases deben contener sólo su ZIP nuevo. Elementor, Pro, tema, Redis y otros plugins no se modifican.
- Configuración efectiva: sobrescritura del sitio → valor de red → default del plugin. Cada control nuevo necesita default, sanitización de sitio, campo de red y sanitización de red. Ningún dato de un sitio se reutiliza para otro.
- El frontend sólo lee opciones y datos precalculados. No escanea CSS, fuentes, filesystem ni Kit Elementor por visita. Los cálculos pesados se inician en administración y usan el grupo `digitalisimo_performance` mediante `wp_cache_get/set/delete`. No se vacía el object cache completo.
- Ninguna optimización nueva se activa automáticamente. Modo seguro excluye administración, editor, preview, Customizer, REST y recursos críticos; las optimizaciones avanzadas permanecen apagadas hasta verificar regresiones.
- No se eliminan Custom Codes de Elementor automáticamente. Una migración debe detectar, preparar y verificar antes de sugerir eliminación manual.

## Fases y puertas

| Fase | Entrega | Puerta de avance |
| --- | --- | --- |
| 0 | Cerrar validación del módulo anterior en blog/Gutenberg, editor/preview, Pro, WooCommerce y subsitios. | Pruebas reales documentadas; la portada sola no basta. |
| 1 | Panel independiente de Rendimiento en sitio/red, Estado, Redis/Object Cache, tabla de red, grupo propio de caché e invalidación selectiva. | Herencia y permisos comprobados en Multisite y WP individual; ninguna alteración frontend. |
| 2 | CSS técnico por sitio/red y diferido opt-in de los cuatro widgets propuestos. | Comparativa visual y funcional móvil/desktop, formularios e iconos intactos. |
| 3 | Inventario de Kit, fuentes, variantes, icon fonts, remotas/locales y whitelist; preload selectivo. | Network confirma que las fuentes no permitidas dejan de solicitarse y los iconos funcionan. |
| 4 | GT/GA4/GTM opt-in, detección de duplicados y herramienta de migración de Elementor Custom Code. | Consentimiento, eventos y no duplicación verificados en navegador y con Site Kit cuando exista. |
| 5 | Debug, prueba de red completa, Lighthouse antes/después y rollback. | Todas las superficies y módulos sin regresión, mediciones documentadas. |

## Checklist

- [ ] Cerrar pruebas del módulo anterior.
- [ ] Arquitectura Multisite y panel Sitio/Red.
- [ ] CSS diferido y CSS técnico.
- [ ] Kit, fuentes, variantes, Google remoto/local e icon fonts.
- [ ] Preloads sin duplicados.
- [ ] GT, GA4, GTM y detección de duplicados.
- [ ] Migración asistida de Elementor Custom Code.
- [ ] Estado Redis/Object Cache y caché propia con invalidación selectiva.
- [ ] Debug seguro.
- [ ] Pruebas funcionales, Lighthouse, documentación y rollback.

## Estado inicial

SEO 1.0.150 ya tiene ajustes heredables en una pestaña de SEO, modo seguro, diagnóstico de assets/fuentes/imágenes y comparativas manuales. La portada de la red pasó smoke test con SEO 1.0.149; blog, editor, Pro y WooCommerce siguen sin cobertura real. No se atribuye la mejora de una corrida Lighthouse al plugin. La fase 0 permanece abierta hasta disponer de páginas representativas o sitio de pruebas.

## Avance de fase 1 · SEO 1.0.151

- Se preparó un menú independiente `Digitalísimo → Rendimiento` en sitio y red. Reutiliza los controles existentes de SEO mediante el mismo guardado y herencia; los diagnósticos y mediciones regresan a la misma superficie donde se iniciaron. El enlace anterior de Rendimiento dentro de la navegación SEO se retira para evitar dos entradas visibles.
- Estado muestra la política efectiva y la red presenta una tabla paginada de 50 sitios con CSS, fuentes, Redis y modo seguro. Las columnas de preloads y tracking marcan «Sin configurar» hasta sus fases respectivas.
- Redis/Object Cache muestra si WordPress usa caché externa, si existe el drop-in y si su encabezado menciona Redis. La presencia del archivo no se confunde con una conexión Redis confirmada. El botón limpia únicamente las claves conocidas del grupo `digitalisimo_performance` para el sitio actual y cambia su generación; nunca llama `wp_cache_flush()`.
- Las claves incluyen blog ID y generación de sitio/red. Guardar ajustes de sitio, herencia o defaults de red invalida selectivamente el espacio correspondiente. Las entradas viejas no conocidas expiran como máximo a los 60 minutos. El frontend todavía no usa este cache para cálculos de fuentes/Kit hasta que esas funciones estén probadas.
- Ninguna optimización nueva de frontend se activa en esta fase. Es reversible volviendo a SEO 1.0.150. Faltan pruebas de interfaz real y regresión antes de marcar la fase completa.

## Inspección pública previa a CSS diferido

En la portada principal publicada aparecen encolados los cuatro candidatos propuestos: handles `widget-form`, `widget-divider`, `widget-social-icons` y `widget-blockquote`. `widget-form` y `widget-blockquote` vienen de Elementor Pro; los otros dos de Elementor. La simple presencia de esos archivos no demuestra que sus widgets estén fuera del primer pantallazo: el formulario de la portada exige comprobar ubicación y estilos antes de diferirlos. No se cambió su carga en SEO 1.0.151.

## Avance CSS · SEO 1.0.152

- Se agregaron tres controles heredables sitio/red: diferir CSS, lista de handles y normalización de captions. El diferido inicia apagado y además exige apagar modo seguro. Sólo acepta handles `widget-*` no críticos y, al imprimir, exige que el archivo pertenezca a Elementor o Elementor Pro. Usa `media="print"` + `onload` + copia original en `<noscript>`. No descarga ningún stylesheet.
- La regla de captions también inicia apagada. Al habilitarla imprime `.elementor-image-carousel-caption{font-style:normal!important}` sólo en frontend seguro; puede revertirse con un interruptor. No se afirma que por sí sola elimine una solicitud de fuente italic: hay que medir después de regenerar CSS y revisar el Kit.
- CSS técnico personalizado acepta hasta 20 KiB, sin HTML, `@import` ni `url()`. Un sitio hereda el CSS de red o lo reemplaza con su propio valor. No se emite en administración ni preview. Esta restricción protege la etiqueta `<style>` y evita que el CSS técnico agregue solicitudes externas.
- Los handles Elementor protegidos incluyen heading, image-carousel, nav-menu, nested-tabs, slides, counter y call-to-action; core, Pro, Swiper y hojas por post nunca pasan por este filtro. La compatibilidad con CSP que bloquee handlers `onload` y el efecto visual de diferir `widget-form` siguen pendientes de prueba real. Por eso el ajuste viene apagado y la fase 2 no se declara completa.

## Inventario por sitio · SEO 1.0.153

- La pantalla Fuentes añade un análisis solicitado por un administrador que lee el Kit Elementor activo mediante `get_active_kit_for_frontend()`. Extrae familias/pesos/estilos de Body, H1–H6, botones y tipografías globales. También lee hasta 50 CSS de fuentes locales de Elementor dentro de los uploads del sitio, con límite por archivo y comprobación de ruta real. Guarda el informe por sitio y en el grupo de caché propio; no analiza fuentes durante visitas públicas ni las bloquea todavía.
- La pantalla Google Tracking audita Custom Code de Elementor por sitio: IDs GT/GA4/GTM, enlaces de preload y CSS técnico. Detecta la presencia de Site Kit sin desactivarlo. Sólo guarda un resumen; no ejecuta ni borra código. En la red, las tablas enlazan al inventario del sitio correspondiente.
- En el sitio principal se observaron un Kit con familias de Body/H1 y tipografías globales diferentes, dos Custom Codes publicados (tracking y preloads), Site Kit y Redis Object Cache activos. Esto confirma que bloquear fuentes usando únicamente el Kit o instalar tracking en paralelo crearía riesgos reales. El próximo paso es preparar variantes locales y una migración opt-in con detección de duplicados antes de activarlas.

## Google Tracking opt-in · SEO 1.0.154

- Se añadieron GT, GA4 y GTM heredables por sitio/red, con validación estricta de ID y modos Normal, DOMContentLoaded, window.load, interacción y delay. DataLayer y `gtag()` se crean de inmediato; los modos diferidos cargan la librería al evento elegido. Interacción escucha scroll, touchstart, keydown y click, con fallback configurable tras `window.load` (12 s por defecto), sin mousemove ni eventos artificiales. GTM usa `wp_head` y `wp_body_open`.
- El interruptor empieza apagado. Para evitar duplicados, el frontend no imprime tracking si Site Kit está activo, si falta un inventario actualizado de Custom Code, si un ID configurado sigue publicado allí o si ya hay un script Google encolado. Guardar o archivar Custom Code invalida el inventario y exige volver a analizarlo. La detección de un script crudo del tema que no esté encolado requiere prueba de navegador; no se afirma cobertura total todavía.
- El módulo no desactiva Site Kit ni elimina Custom Code. En el sitio principal, con Site Kit y un Google Tag publicado, la salida de Digitalísimo queda bloqueada aunque alguien active el interruptor. La migración efectiva sólo puede verificarse después de decidir cuál implementación conservar y comprobar consentimiento/medición en un sitio real.

Referencias API: [WordPress Object Cache](https://developer.wordpress.org/reference/classes/wp_object_cache/), [detección de caché externa](https://developer.wordpress.org/reference/functions/wp_using_ext_object_cache/), [filtro de etiquetas CSS](https://developer.wordpress.org/reference/hooks/style_loader_tag/) y [tipografía global Elementor](https://developers.elementor.com/docs/editor-controls/global-style/).

## Preloads locales opt-in · SEO 1.0.155

- El análisis administrativo de fuentes guarda la URL de cada WOFF2 local validado dentro de los uploads del sitio y muestra su ruta relativa para configuración manual. No se buscan archivos durante visitas públicas.
- La pestaña Preloads ofrece modos apagado, automático y manual, con límite de uno o dos archivos. El modo automático considera sólo familias declaradas por el Kit, variantes normales de peso 400 y CSS local efectivamente encolado en la página; el manual usa rutas relativas a uploads para que los defaults de red no incorporen el dominio de otro sitio. Ningún modo presume que la fuente sea crítica: un administrador debe comparar su efecto visual y Lighthouse antes de habilitarlo.
- La salida omite un WOFF2 cuando no existe un inventario de fuentes y Custom Code, su CSS no está encolado, WordPress ya lo precarga o un Custom Code publicado tiene la misma URL. Mantiene el límite y elimina duplicados internos. La compatibilidad con preloads directos emitidos por un tema después de `wp_head` prioridad 7 queda pendiente de verificación real.
- Se requiere comprobar en un sitio de pruebas las solicitudes de red y la ausencia de preloads duplicados antes de activar la función en producción. La fase de fuentes y la validación global siguen abiertas.

## Preparación de migración · SEO 1.0.156

- Tras analizar Custom Code y fuentes, un administrador puede preparar en los ajustes del sitio IDs GT/GA4/GTM inequívocos de fragmentos publicados y rutas WOFF2 locales presentes en el inventario. Nunca sobrescribe un campo no vacío ni importa una URL ajena a los uploads del sitio. Los fragmentos de Elementor siguen intactos.
- Los controles de tracking y preloads se fijan en apagado cuando se importan valores, incluso si existía un default de red activo. La revisión de Site Kit, consentimiento, páginas reales y duplicados sigue siendo necesaria antes de habilitar la nueva salida y desactivar manualmente el Custom Code anterior. CSS técnico arbitrario queda fuera de esta migración porque requiere revisión humana.

## Blindaje local opt-in · SEO 1.0.157

- Fuentes ofrece modos apagado, automático conservador y manual, heredables sitio/red. Manual acepta familia, pesos y estilos permitidos; automático usa el Kit y conserva familias no declaradas allí porque en el sitio principal el H1 real difiere de su tipografía global. Los icon fonts se conservan siempre. Requiere apagar Modo seguro para actuar en el frontend.
- Un administrador analiza fuentes y luego genera copias filtradas del CSS local de Elementor en los uploads del propio sitio. Los archivos originales no se modifican ni se borran. El frontend sustituye únicamente hojas locales cuyo archivo original y política coinciden con la copia preparada; si cambia la política, desaparece un archivo o Elementor lo regenera, vuelve al CSS original. El editor y las vistas previas quedan excluidos.
- El filtro oficial `elementor/frontend/print_google_fonts` sólo permite cortar toda la carga Google Fonts, no seleccionar familias o variantes; por ello esta fase actúa exclusivamente sobre CSS local y no pretende bloquear otras fuentes remotas o del tema. Antes de encender el blindaje hay que comprobar tipografía, iconos y solicitudes WOFF/WOFF2 en páginas representativas de cada sitio. No se declara concluida la fase 3.

## Prueba aislada de integración · SEO 1.0.158

- En WordPress Playground con Elementor gratuito y Hello Elementor activos, SEO inició sin errores fatales, las siete secciones de Rendimiento respondieron HTTP 200 y el análisis administrativo detectó el Kit activo. El archivo `wp-includes/version.php` de esa instancia declaraba WordPress 7.1.2; esta prueba no representa la versión ni la configuración del servidor de producción.
- Se agregó únicamente en la instalación aislada un CSS local de muestra con Inter 400 normal, Inter 700 italic y Font Awesome. Tras guardar el modo manual, volver a analizar fuentes y generar copias, el informe indicó una variante retirada. La copia conservó Inter regular y Font Awesome y omitió Inter italic. Un visitante recibió la copia filtrada; un administrador recibió el original. Al cambiar el archivo original, una visita pública volvió al original. Ningún archivo de Elementor se modificó.
- No hubo prueba con Elementor Pro, Multisite ni Redis real, ni medición Lighthouse después de activar optimizaciones en un sitio representativo. La instalación pública aún reportaba SEO 1.0.150 al revisar la API, así que las versiones recientes no se han validado allí. El objetivo global permanece abierto.

## Coherencia de fuentes y precargas · SEO 1.0.159

- Una precarga no debe descargar un WOFF2 excluido del CSS protegido. Antes de elegir las fuentes críticas, la política efectiva de blindaje filtra las variantes inventariadas; los icon fonts permanecen permitidos. La política se calcula una vez por petición para mantener bajo el costo del frontend.
- La herencia red → sitio → default y el aislamiento de dos sitios se probaron con funciones WordPress simuladas. Se intentó crear una red local en Playground; la herramienta rechazó `127.0.0.1:9400` porque Multisite no acepta puertos personalizados. Los puertos estándar de la máquina estaban ocupados y un espacio de red aislado no tuvo salida para descargar las dependencias de Playground. Esto no cuenta como validación real de Multisite.

## Debug administrativo · SEO 1.0.160

- Debug muestra por sitio familias ELEMENTOR LOCAL e ICON FONT, política PERMITIDO/BLOQUEADO, preloads configurados, CSS CRÍTICO/DIFERIDO CONFIGURADO/NORMAL, estado DIGITALÍSIMO/SITE KIT/ELEMENTOR, indicios de GOOGLE REMOTO y resultado HIT/MISS del grupo de caché propio. La tabla de red enlaza al Debug del sitio. Se usan inventarios ya guardados y la última captura pública, sin sondeos nuevos en cada visita.
- Las etiquetas «configurado» y «no detectado» expresan límites del diagnóstico: no sustituyen Network del navegador ni detectan por completo scripts inline crudos de un tema. Sólo administradores con la capacidad pertinente pueden abrirlo. El objetivo global aún requiere validación en Multisite, Elementor Pro, Redis y Lighthouse.

## Precisión de Debug · SEO 1.0.161

- El estado «BLOQUEADO» para una variante local exige que la copia CSS generada exista, coincida con la política vigente y el original no haya cambiado. Si la regla la excluye pero aún no hay copia válida, Debug muestra «POLÍTICA PENDIENTE»: el frontend conserva el CSS original.
- La señal de posible duplicado de tracking se evalúa sólo cuando el tracking de Digitalísimo está habilitado y contiene un ID. Los scripts externos detectados se presentan sin atribuirlos a un tema o plugin sin evidencia. Se probó la lógica de etiquetas y fallback; las pruebas reales de navegador, Multisite, Pro, Redis y Lighthouse siguen abiertas.

## Aislamiento de diagnósticos de red · SEO 1.0.162

- Cada nueva captura de assets guarda el ID del sitio de origen. Debug comprueba el ID y la URL del sitio seleccionado antes de mostrar recursos; las capturas anteriores sin ID se validan por dominio y ruta, también en redes de subdirectorios.
- La navegación de Rendimiento de red conserva `site_id`, el formulario de análisis inicia con la URL del sitio elegido y el botón de limpieza del caché propio actúa sobre ese sitio y vuelve a su pantalla. Se añadieron casos de prueba para dos subsitios bajo el mismo dominio. Falta corroborarlo en una instalación Multisite real con Redis.

## Referencia Lighthouse pública · 2026-09-30

- Se registraron tres corridas móviles y una de escritorio de la portada pública con SEO 1.0.160 instalado. Las puntuaciones móviles oscilaron entre 57 y 89; la mediana fue 61. Las cuatro hojas de widgets candidatas estaban presentes. [Datos, límites y comando reproducible](measurements/2026-09-30-home-public-snapshot.md).
- Esta referencia no valida SEO 1.0.162 ni sustituye la comparación controlada antes/después en Multisite y páginas representativas. El objetivo permanece abierto.

## Prueba aislada Multisite y retorno de red · SEO 1.0.163

- Se creó una red local WordPress 7.1.2/PHP 8.3 en WordPress Playground, con dos sitios en subdirectorios, SEO activado para la red, Elementor gratuito 4.3.3 y Hello Elementor 3.5.1 en el segundo sitio. Es una instalación desechable, sin datos de producción ni Elementor Pro.
- `Rendimiento de red → Debug` mostró el segundo sitio al elegir `site_id=2`; `Rendimiento → Fuentes` abrió desde su administración y analizó su Kit. El análisis de red del sitio principal volvió a Red y mantuvo `site_id=1`. Los inventarios quedaron separados por sitio.
- El botón de limpieza del caché propio desde Red para el sitio 2 volvió a Red conservando `site_id=2`; desde el sitio 2 volvió a su administración. Una solicitud del sitio 2 que intentó limpiar el sitio principal fue rechazada. No había drop-in Redis: esto verifica rutas, permisos y aislamiento, no el funcionamiento de Redis externo.
- Se guardó en la red el modo de fuentes `auto` con el modo seguro intacto. Los dos sitios mostraron `auto` heredado y origen «Configuración de red». El sitio 2 guardó una sobrescritura `off` y cambió su origen a «Este sitio»; el sitio principal mantuvo `auto` heredado. No se activó el filtrado de fuentes en frontend.
- La prueba encontró que guardar ajustes de Rendimiento de red perdía el sitio seleccionado. SEO 1.0.163 agrega `digitalisimo_site_id` al formulario y al redirect tras validar que el sitio existe. Se comprobó con un guardado real: volvió a `section=fonts&site_id=2`.
- Después del cambio, las siete secciones (Estado, CSS, Fuentes, Preloads, Google Tracking, Redis/Object Cache y Debug) respondieron HTTP 200 sin errores fatales tanto en Red con `site_id=2` como en la administración del sitio 2.
- Quedan pendientes pruebas con Elementor Pro, Redis Object Cache real, Site Kit, páginas con contenido representativo, editor, imágenes, formularios, sliders, CSS diferido y Network del navegador con las optimizaciones activadas. Esta prueba no cierra el objetivo global.

## Preparación conservadora de CSS de Elementor · SEO 1.0.164

- El inventario de Custom Code reconoce un fragmento publicado que contenga únicamente un bloque `<style>` con CSS aceptado por el sanitizador existente. Los fragmentos mixtos, con recursos externos o con dos candidatos CSS distintos quedan para revisión manual.
- «Preparar valores en SEO» copia ese CSS sólo si el campo del sitio está vacío y marca una pausa específica del sitio: el CSS copiado no se imprime durante la comparación. No cambia ni desactiva el fragmento de Elementor. La pausa también está disponible como ajuste de red heredable; su valor predeterminado es desactivado para conservar el comportamiento del CSS que ya estuviera configurado.
- El análisis y la preparación regresan a la sección de origen (CSS, Tracking o Preloads) y conservan el sitio seleccionado en la administración de red. Se comprobaron clasificación, propuesta, salida en pausa y herencia red/sitio mediante pruebas PHP aisladas. Falta validar la migración con Elementor Pro y revisar visualmente el resultado antes de desactivar Custom Code en producción.

## Diagnóstico de assets sin solicitud TLS interna · SEO 1.0.166

- El servidor de WordPress reportó `cURL error 60` al solicitar su propio dominio para capturar assets, mientras la conexión HTTPS externa presentó un certificado válido. El diagnóstico ahora hace que el navegador visite temporalmente la URL pública seleccionada y regrese al panel de origen. El servidor conserva la captura bajo un token de corta duración y sólo la entrega al administrador que inició la prueba, con el sitio/red originales.
- No se desactiva `sslverify` ni se usa HTTP. La captura respeta el renderizado público y evita que una resolución DNS o un vhost interno con certificado distinto impida analizar la página. Queda por confirmar en la instalación real el retorno de una URL de un subsitio con dominio mapeado y una caché de página/CDN que pudiera interceptar parámetros únicos.

## Controles de Rendimiento y comparativa · SEO 1.0.167

- Los modos de Fuentes, Preloads y Google Tracking usan las mismas opciones desplegables en sitio y red; el guardado de red sigue aplicando su sanitización específica y la herencia no cambia.
- El diagnóstico de assets conserva la URL elegida al volver al panel. La comparativa usa esa misma URL sin pedirla otra vez, se presenta plegada como registro opcional y explica cada métrica. «Analizar URL» inventaría recursos; no ejecuta Lighthouse ni obtiene automáticamente FCP, LCP, TBT, CLS o puntuación. El administrador puede introducir sólo los valores de un informe externo que realmente tenga.

## Separación del diagnóstico y las mediciones · SEO 1.0.168

- Estado muestra el diagnóstico de recursos y un enlace directo a Mediciones para la misma URL. La pestaña Mediciones registra únicamente resultados externos de PageSpeed/Lighthouse; al guardar, permanece en ella.
- El sitio y la URL seleccionados se conservan en la navegación, y la última captura válida del mismo sitio se recupera si se abre Mediciones sin URL explícita. No se muestran capturas de otro sitio como resultados del actual.

## Una sola entrada de Rendimiento · SEO 1.0.169

- Las pestañas distinguen explícitamente el inventario de assets de la carga manual de resultados PageSpeed/Lighthouse. Los enlaces históricos del sitio y de la red redirigen a la consola actual antes de generar HTML y conservan sitio/URL válidos.

## Estado visible de opciones heredadas · SEO 1.0.170

- Los checkboxes activos heredados conservan el color de activación aunque sigan deshabilitados para edición local. La leyenda de valor efectivo usa «Activo»/«Inactivo» y mantiene visible el origen. La herencia y el guardado no cambian.

## Checkboxes activos con valor predeterminado · SEO 1.0.171

- La indicación «Valor predeterminado del plugin» conserva su etiqueta informativa, pero deja de pintar de gris el checkbox. Un valor predeterminado activo se ve marcado en azul tanto en red como en sitio y sigue siendo editable.

## Optimización asistida de fuentes locales · SEO 1.0.172

- «Detectar y optimizar fuentes» analiza bajo demanda el Kit y hasta 500 documentos Elementor publicados del sitio seleccionado, une familias y variantes y genera copias CSS locales. En red, la acción sólo modifica la configuración y los archivos del sitio elegido; en WordPress individual actúa sobre ese sitio.
- Si el inventario es parcial, conserva las familias no reconocidas. Si el inventario es completo, puede excluir reglas `@font-face` de familias no detectadas; los icon fonts y bloques de peso ambiguo permanecen. Los originales nunca se borran y un cambio posterior de datos Elementor invalida el manifiesto para volver al CSS original.
- Las copias activadas explícitamente por el botón pueden operar con Modo seguro sin habilitar las demás optimizaciones avanzadas. La herramienta no analiza uso visual de cada página ni elimina archivos o fuentes remotas del tema; la verificación de solicitudes y apariencia en navegador sigue pendiente para cerrar la fase de fuentes.

## Blindaje de fuentes generalizado · SEO 1.0.182

Fuente: solicitud del propietario del 2026-10-01 («Ajuste general del blindaje de fuentes — Multisite»). El blindaje no puede depender de familias ni dominios concretos.

- **Sin nombres fijos.** El código de producción no menciona familias, rutas, hashes ni pesos concretos. La whitelist efectiva de cada sitio sale de su propio Kit y contenido Elementor, más sus excepciones. Las familias citadas en los tests son datos de prueba.
- **Modos explícitos.** `auto` pasa a ser **Seguro**: recorta las variantes que no usan las familias detectadas y conserva —registrándolas en el manifest y en la pantalla— las familias que la detección no vio. El recorte de familias no detectadas ya no depende de que el inventario salga completo: es el modo **Estricto** (`strict`), opcional y explícito. **Manual** usa sólo la lista. El default del plugin sigue siendo apagado, conforme a la regla de no activar optimizaciones automáticamente; «Detectar y optimizar» activa Seguro en el sitio elegido.
- **Excepciones manuales.** La lista `Familia|pesos|estilos` se suma a lo detectado en Seguro y Estricto. Un peso o estilo que la detección dejó abierto («todos») nunca se estrecha por una excepción.
- **Variantes exactas.** Se decide por familia + estilo + peso. Los bloques conservados no se reescriben: mantienen `font-display`, todos sus `unicode-range` y las URLs relativas a WOFF/WOFF2. Los subsets no se eliminan por idioma. Los bloques idénticos repetidos se descartan al generar.
- **Icon fonts.** Ruta de exclusión separada: patrón incorporado (eicons, Font Awesome, Line Awesome, Material Icons/Symbols, Dashicons, IcoMoon, Fontello, Themify, Ionicons, Bootstrap Icons, Remix, Boxicons, `star`/WooCommerce, nombres terminados en *icon(s)*) más la nueva opción heredable `perf_font_icon_families`. Nunca se filtran ni se precargan. Un nombre de texto que termine en «icon» quedaría protegido de más: el error posible es conservar, nunca borrar.
- **Preloads críticos.** El modo automático ya no fija 400/normal: precarga las variantes críticas que declara el Kit de cada sitio (cuerpo, texto global, H1, principal), en su subset latino si existe, y sólo si están autorizadas. Un H1 sin peso declarado no se adivina. Sin tipografía crítica identificable, usa la variante regular de cada familia detectada (valor por defecto de CSS).
- **Regeneración.** Editar el Kit, una página o un template; cambiar la whitelist o la configuración de sitio/red; que Elementor regenere su CSS; limpiar la caché de rendimiento o pulsar «Recalcular fuentes» agenda un único recálculo por sitio (inventario → copias → preloads). Antes el blindaje sólo se borraba y no volvía a generarse. La aprobación del administrador se guarda aparte y ligada al modo, así que un recálculo automático no apaga una optimización aprobada; cambiar de modo exige aprobar otra vez.
- **Caché.** Los manifests de copias y preloads se leen por la caché propia con claves por `blog_id`. La opción persistente sigue siendo la fuente de verdad: un MISS la relee y el frontend no cambia. Las copias propias sin referencia se borran a los siete días, por si una caché de página aún las enlaza.
- **Validación.** Tras generar, cada copia se contrasta con la whitelist: una variante no autorizada que sobrevive, una autorizada que falta o un duplicado introducido marcan error, y esa copia no se sirve. Los preloads se verifican contra las variantes autorizadas y sin duplicados. Los errores aparecen en Debug.
- **Debug.** Tabla por sitio con Familia, Estilo, Peso, Estado, Archivo, Origen y Preload. Estados combinables: PERMITIDO, BLOQUEADO, POLÍTICA PENDIENTE, ICON FONT, EXCEPCIÓN MANUAL, CRÍTICO y PRELOAD.

Pruebas: `tests/performance-font-policy.php` construye tres sitios con familias distintas (Roboto, Montserrat, Playfair Display, una fuente local) y comprueba variantes exactas, subsets, iconos, excepciones, aislamiento entre sitios, validación, preloads críticos y estados de debug. Se verificó con mutaciones que el test falla si el modo estricto se comporta como seguro, si la validación ignora faltantes o si Material Symbols deja de reconocerse.

Límites que siguen abiertos: el filtrado actúa sobre el CSS local de Google Fonts que genera Elementor (`uploads/elementor/google-fonts/css`). Las fuentes personalizadas de Elementor Pro, las del tema y las remotas (`fonts.googleapis.com`) se detectan como familias pero no se recortan. La detección lee la configuración de Elementor, no el render: CSS libre con `font-family` deja el inventario como parcial. Falta la validación real en Multisite con Elementor Pro, Redis y Network del navegador.

## Fuentes variables y formato del contenido · SEO 1.0.183

El primer Debug real de digitalisimo.mx con 1.0.182 mostró que Inter y Source Serif 4 son fuentes variables: todos los pesos de un estilo apuntan al mismo WOFF2 en cada subset. Retirar sus reglas de 100, 700 o 900 no evitaba ninguna descarga —el archivo se baja igual para el 400— y sí provocaba negritas sintéticas o caídas al 600 en textos en 700.

- Una regla `@font-face` bloqueada se conserva si su archivo ya lo usa una variante permitida. La validación aplica el mismo criterio. Sólo se retiran reglas cuyos archivos no usa ninguna variante permitida, que es donde realmente se ahorra.
- La versión de esquema del blindaje sube a 3: las copias generadas con 1.0.182 quedan invalidadas, el sitio sirve el CSS original y el recálculo programado genera las nuevas.
- La detección incluye el formato del contenido: `<strong>`/`<b>` y `<em>`/`<i>` en documentos Elementor (también escapados en su JSON) y en el contenido publicado fuera de Elementor añaden 700 e italic a la familia del texto corrido, nunca a la de los títulos.
- Debug agrupa los subsets: una fila por familia, estilo y peso, sin el corte de 150 filas que ocultaba Source Serif 4 normal. Marca FUENTE VARIABLE y sólo pone PRELOAD en la variante elegida, no en cada peso que comparte el archivo.

Un navegador sólo descarga una fuente si algún texto de la página la necesita. Retirar declaraciones que nadie usa ahorra poco; el valor del blindaje está en impedir que una variante no prevista —una cursiva en un pie de foto, por ejemplo— dispare la descarga de un archivo propio.

### Cobertura por sitio: Google Fonts remoto y recálculo de red · SEO 1.0.183

Requisito del propietario: el blindaje debe aplicar a las fuentes de cada sitio, sean cuales sean, y no sólo a los sitios que cargan Google Fonts en local.

- **Google Fonts remoto.** Un sitio con «Cargar Google Fonts localmente» apagado recibía de Elementor `fonts.googleapis.com/css?family=…:100,100italic,…,900italic` y quedaba sin optimizar: ni siquiera generaba su whitelist porque no existía la carpeta local. Ahora la whitelist se guarda en el manifest aunque no haya CSS local, y `style_loader_src` reescribe esa URL para pedir sólo las variantes autorizadas de cada familia. Cubre la API v1 (la de Elementor, con alias `regular`, `italic`, `b`, `bi`…) y css2 (`ital,wght@…`). Un token desconocido, un rango variable (`100..900`), un eje distinto de `ital`/`wght` y los icon fonts se conservan sin tocar. Si no queda ninguna familia autorizada, la hoja no se imprime. Debug muestra, junto a cada hoja remota capturada, la URL que recibiría un visitante.
- **Recalcular toda la red.** Red → Rendimiento → Fuentes ofrece «Recalcular fuentes en todos los sitios». Recorre la red, aprueba cada sitio con su propio modo efectivo y agenda el recálculo en el cron de ese sitio, que detecta sus propias familias. Los sitios con el blindaje apagado se omiten y nunca se copia la whitelist de un sitio a otro.

Siguen fuera del recorte las fuentes personalizadas de Elementor Pro (se escriben dentro del CSS de cada documento) y las que un tema imprime sin `wp_enqueue_style`.

## Imágenes responsive globales · SEO 1.0.184

Fuente: objetivo del propietario del 2026-10-02 («Optimización responsive global de imágenes»). Los diseñadores pueden seguir eligiendo «Full» en Elementor; cada dispositivo debe descargar la variante adecuada sin perder calidad en escritorio.

- **Dónde actúa.** Sobre el HTML final de cada página pública, que es el único punto donde convergen WordPress, Elementor, Elementor Pro, el tema y los plugins. Un buffer que se abre en `template_redirect` procesa cada `<img>` en orden de documento. No actúa en administración, editor, vistas previas, REST, AJAX, feeds ni respuestas que no sean HTML, y ante cualquier error devuelve la página original. `<script>`, `<style>`, `<noscript>`, `<template>`, `<textarea>`, `<svg>` y `<picture>` se apartan antes y se restauran intactos.
- **Sólo lo que ya existe.** No genera, convierte, reduce ni reemplaza archivos, ni cambia el tamaño elegido por el diseñador. Completa `srcset` con los tamaños registrados del adjunto (`wp_calculate_image_srcset`), `sizes`, `width`/`height` del archivo realmente pedido, `loading`, `decoding` y, como máximo una vez por página, `fetchpriority`. Lo que ya trae `srcset`, `sizes` y dimensiones no se toca.
- **Vinculación con el adjunto.** Por la clase `wp-image-ID` (comprobando que el archivo pertenece a ese adjunto) o por la URL dentro de los uploads de ese sitio, reconociendo tamaños intermedios y copias `-scaled`. Una URL de otro dominio —aunque tenga la misma ruta— o de otro sitio de la red nunca se vincula. Si no hay vínculo fiable, la imagen queda byte a byte igual.
- **sizes.** Se conserva el existente. Si falta, se usa el layout de Elementor: columnas, contenedores con ancho en %, ancho en caja del elemento o del Kit y breakpoints activos. Ejemplo: columna de 50 % en caja de 1140 px → `(max-width: 1024px) 100vw, (max-width: 1140px) 50vw, 570px`. Si el layout no se conoce con certeza (un hijo sin ancho dentro de un contenedor en fila), se usa el `sizes` de WordPress. Seguro declara 100vw hasta tablet; Estricto, sólo hasta móvil. El navegador elige del `srcset` multiplicando por su DPR: nunca se fuerza una variante.
- **Prioridad.** El logo y las tres primeras imágenes relevantes no se difieren; las pequeñas de cabecera no consumen ese cupo. La primera grande (≥ 300 px) es la candidata a LCP y recibe `fetchpriority="high"` sólo si la página no tiene ya otra. El resto recibe `loading="lazy"` y `decoding="async"` si no los tenía. Un `loading` puesto por WordPress, Elementor o el tema se respeta; Estricto quita el lazy de la candidata a LCP.
- **Carruseles.** Sólo se añaden atributos a la imagen; clases, `src` y estructura de Swiper no cambian. Las imágenes con carga diferida propia (`swiper-lazy`, `data-src`, `lazyload`…) se excluyen.
- **Exclusiones.** SVG, `data:`, `blob:`, píxeles de seguimiento, CAPTCHA, iconos, emojis, avatares, otros sistemas de carga diferida, marcas `data-no-optimize`/`data-skip-lazy`/`skip-lazy` y una lista manual por `.clase`, `#id`, `[atributo]`, `id:123` o fragmento de URL.
- **Fondos (opcional, apagado).** Añade al CSS que genera Elementor una variante para tablet y móvil sólo si cubre viewport × DPR 2 y es menor que el original, y sólo con DPR ≤ 2. Con `background-size: cover` el archivo necesario depende del alto del elemento, que no se conoce: se conserva el original. Se respeta la imagen que el diseñador ya eligió para tablet o móvil. Cambiar el ajuste regenera el CSS de Elementor del sitio.
- **Caché.** Datos derivados por sitio, adjunto, archivo y generación en el grupo propio. URL → adjunto en la caché de objetos si existe; si no, en un mapa acotado por sitio que se lee una vez por petición y sólo se escribe si cambió. Editar o borrar un adjunto sube la generación del sitio.
- **Configuración.** Nueve opciones heredables sitio/red con paridad completa: interruptor general (apagado por defecto), srcset, sizes, width/height, lazy, fondos, modo y exclusiones.
- **Rendimiento → Imágenes.** Auditoría de la última URL analizada en «Diagnóstico de assets»: totales, con/sin srcset, sin dimensiones, lazy, eager, prioridad alta y potencialmente sobredimensionadas. Detalle por imagen con estado (RESPONSIVE, YA OPTIMIZADA, LAZY, CRÍTICA, EXCLUIDA, SIN ATTACHMENT, SIN VARIANTES, ERROR), adjunto, original, ancho renderizado estimado a 390 y 1440 px, srcset, sizes, dimensiones, loading y fetchpriority.

Pruebas: `tests/performance-images.php`, con mutaciones que confirman que falla si se permite más de un `fetchpriority`, si se acepta una URL de otro dominio con la misma ruta o si se procesan `<img>` dentro de `<script>`, `<noscript>` o `<picture>`.

Pendiente de validar en un sitio real: el efecto visual con temas que no declaren `img{height:auto}` (el `height` añadido podría deformar), la interacción con plugins de caché de página que abran su buffer después de este, y la medición Lighthouse antes/después en móvil.

### Tope del srcset en el tamaño elegido · SEO 1.0.185

La primera captura real de digitalisimo.mx mostró carruseles con el tamaño `768×480` elegido por el diseñador, no «Full». WordPress incluye en el srcset también los tamaños mayores (1024, 1536…), así que un teléfono de DPR 3 habría pasado a descargar 1536 px en lugar de 768: más peso que antes. El srcset ahora nunca ofrece un archivo mayor que el del `src`. Con «Full» no cambia nada; con un tamaño menor, el móvil sólo puede bajar a variantes iguales o más pequeñas.

### Correcciones con la primera auditoría real · SEO 1.0.186

La auditoría de la portada de digitalisimo.mx (108 imágenes) mostró cinco problemas:

- **Carruseles con carga diferida de Swiper.** Unos 50 slides no tienen `src`, sino `data-src` + `swiper-lazy`, y quedaban excluidos. Swiper (y lazysizes con `lazyload`) también aplica `data-srcset` y `data-sizes`, así que ahora se completan esos dos atributos, con el mismo tope del tamaño elegido y el `sizes` del layout. No se añaden `src`, dimensiones ni prioridad: la librería decide cuándo cargar. Otros sistemas con `data-src` siguen excluidos.
- **Logos de clientes como críticos.** «logo» en el nombre del archivo o en el alt sólo identifica el logo del sitio al principio de la página; en la clase o el id, en cualquier parte. Los logos de clientes y el del pie se difieren como cualquier imagen.
- **Imágenes con prioridad propia ocupando el puesto de LCP.** Una imagen que ya declara `fetchpriority` (las olas decorativas traen `low`) no es candidata; la siguiente imagen grande sí.
- **Estado engañoso.** Añadir sólo `width`/`height` a una imagen sin variantes menores ya no la marca como RESPONSIVE: figura SIN VARIANTES, con el motivo.
- **`sizes` redundante.** A ancho completo dentro de una caja se publica `(max-width: 1300px) 100vw, 1300px`, sin repetir la cláusula de tablet.

Además, la tabla de imágenes de «Diagnóstico de assets» leía `data-src` como `src` (la expresión `\bsrc` coincide tras «data-»), lo que hacía parecer que los slides tenían `src`. Ya no.

## Experimento: defer seguro de jQuery · SEO 1.0.187

Fuente: solicitud del propietario del 2026-10-02 («Defer seguro de jQuery y dependencias»). Objetivo: medir sólo el efecto de diferir la cadena jQuery/Elementor conservando toda la funcionalidad. Desactivado por defecto, heredable sitio → red → plugin.

- **Lo que no hace.** No elimina, desregistra ni reemplaza jQuery, no usa async, no toca archivos de WordPress ni Elementor, no se combina con eliminar Migrate, retrasos por interacción o segundos, ni cambios de Swiper.
- **Estrategia nativa.** Construye el árbol real de scripts encolados (cola + dependencias, con `jquery` como alias de `jquery-core` y `jquery-migrate`) y pide `strategy => defer` para cada script que depende de jQuery, una vez en `wp_enqueue_scripts` y otra antes de imprimir el pie, porque Elementor encola scripts de widgets mientras pinta el contenido. Un script que ya trae su estrategia no se toca. WordPress (6.3+) sólo concede el defer si toda la cadena puede diferirse y deja bloqueante lo que tenga código inline «after»: el orden de ejecución entre jQuery, Migrate, Elementor, Pro, tema y plugins se mantiene porque los scripts diferidos se ejecutan en orden de documento.
- **Revisión del HTML final.** WordPress no ve el código inline impreso por temas, plugins o un widget HTML, ni los `<script>` escritos a mano. Antes de enviar la página se revisa lo que va después del primer script diferido de la cadena: un inline que use `jQuery` o `$` (salvo datos de `wp_localize_script` y traducciones), un script bloqueante sin registrar o un dependiente de jQuery que WordPress dejó bloqueante hacen que esa página vuelva a la carga normal, quitando sólo el defer que pidió el experimento. JSON-LD, plantillas, módulos y scripts async no cuentan.
- **Red de seguridad en el navegador.** Un script mínimo impreso antes que cualquier otro escucha «jQuery is not defined», «Can't find variable: jQuery» o «$ is not a function». Si aparece, avisa con `sendBeacon` y el sitio vuelve a la carga normal hasta que un administrador lo reactive desde Rendimiento → JavaScript. El endpoint es público pero sólo puede apagar el experimento una vez y con un mensaje de dependencia válido. Una caché de página puede seguir sirviendo HTML diferido hasta purgarse.
- **Rendimiento → JavaScript.** Selector Desactivada / Defer seguro experimental, estado efectivo y, desde la última captura de «Diagnóstico de assets», la estrategia solicitada y efectiva de jquery-core, jquery-migrate y cada dependiente, con SEGURO PARA DEFER o NO DIFERIDO y su motivo.

Pruebas: `tests/performance-javascript.php`, con el árbol real de la portada de digitalisimo.mx. Las mutaciones confirman que el test falla si no se revisa el inline, si se pisa una estrategia existente, si cualquier error apaga el experimento o si se ignora un dependiente bloqueante.

Validación pendiente en un sitio de pruebas: consola sin errores, menú móvil, sticky, formularios, pestañas, acordeones, contadores, carruseles, lightbox, Call To Action y WooCommerce; varias corridas de Lighthouse móvil antes y después comparando FCP, LCP, Element render delay y TBT.

### Captura como visitante · SEO 1.0.188

La primera prueba del experimento jQuery en digitalisimo.mx volvió a carga normal por un inline `jquery-ui-core-js-before`. La captura se había pintado con la sesión del administrador: incluía `elementor-common`, `elementor-pro-notes`, Backbone y jQuery UI draggable, que un visitante no carga. «Analizar URL» ahora pinta la página sin usuario (`wp_set_current_user( 0 )`), sin barra de administración y sin sus recursos, de modo que fuentes, imágenes, CSS y JavaScript se diagnostican como los recibe un visitante. Cuando una página vuelve a carga normal por código inline, Rendimiento → JavaScript muestra sus primeros 400 caracteres para decidir con el contenido real.

### Inline «before» de la cadena · SEO 1.0.189

Capturada como visitante, la portada de digitalisimo.mx volvía a carga normal por una sola línea que añade WordPress antes de jQuery UI: `jQuery.uiBackCompat = true;` (`jquery-ui-core-js-before`). WordPress no considera los inline «before» al decidir si un script puede diferirse, pero éste necesita jQuery y, impreso tal cual, se ejecutaría antes que jQuery diferido.

Los inline «before» que WordPress adjunta a un script diferido de la cadena se convierten en un script diferido con `src="data:text/javascript;base64,…"` en la misma posición. Los scripts diferidos se ejecutan en orden de documento: corre después de jQuery y justo antes de su script, igual que antes. Sólo se hace si la CSP del sitio (cabecera o `<meta>`; `script-src-elem`, `script-src` o `default-src`) admite `data:`; si no, la página vuelve a carga normal. Si cualquier otra causa obliga a volver a carga normal, el inline se restaura tal como estaba. El código inline suelto, que no está adjunto a un script de la cadena, sigue provocando fallback.

## Auditoría y calidad frontend · SEO 1.0.190

Fuente: solicitud del propietario del 2026-10-01, objetivo «AUDITORÍA Y OPTIMIZACIÓN GLOBAL DE CALIDAD FRONTEND». La consola pasa a llamarse **Rendimiento y calidad** y abre en **Auditoría**. Pestañas: Auditoría, Imágenes, Accesibilidad, SEO técnico, Agentes IA, JavaScript, CSS, Fuentes, Preloads, Diagnóstico de assets, PageSpeed manual, Google Tracking, Redis/Object Cache y Debug.

- **Cómo mide.** Un administrador lanza la auditoría de una URL pública del sitio (o, en la red, de un subsitio). La página se abre en su navegador como visitante sin sesión (`digitalisimo_quality_probe`, token de un solo uso de 10 minutos), con el HTML final ya procesado por imágenes y JavaScript. `assets/quality-audit.js` la recorre para cargar lo diferido, mide escritorio, abre la misma URL en un iframe del mismo origen a 390 px para la vista móvil y envía los hechos a `admin-ajax.php` del sitio auditado. Si el sitio prohíbe iframes (X-Frame-Options DENY o CSP), la vista móvil queda como «no medida» y los objetivos táctiles se miden en escritorio.
- **Quién decide.** El navegador sólo recoge hechos; `Digitalisimo_Integrations_Quality_Rules` (sin WordPress, probado en `tests/quality-rules.php`) asigna OK, ADVERTENCIA, ERROR o NO APLICABLE. Ninguna regla nombra URLs, colores, IDs de Elementor ni páginas.
- **Qué revisa.**
  - Enlaces: sin href, vacío, `#`, `javascript:`, variables sin resolver, `mailto:`/`tel:` sin dato, anclas sin destino. Se distinguen iconos sociales y botones; las acciones `#elementor-action` no se reportan.
  - Nombre accesible: enlaces sin nombre, iconos sin aria-label, textos genéricos, mismo destino con nombres incompatibles y mismo nombre hacia destinos distintos.
  - Encabezados visibles en escritorio y móvil: sin H1, varios H1, saltos de nivel, encabezados vacíos, de sólo números o símbolos, o con longitud de párrafo. Se dibuja el árbol.
  - Alt: faltante, igual al caption o al texto del enlace, con forma de nombre de archivo, oculto pero descriptivo, y vacío en una imagen grande.
  - Objetivos táctiles: WCAG 2.5.8, 24 × 24 px o separación equivalente; los enlaces en una frase están exentos.
  - Contraste: AA con colores computados y opacidad. Los textos sobre imagen, degradado o vídeo se cuentan como «revisión visual».
  - Imágenes: tamaño descargado frente a pintado × DPR (DPR 3 en móvil). Sólo se advierte si existe una variante menor suficiente o falta srcset.
  - CSS por página: handle, archivo, widget, presencia y primer viewport en ambas vistas.
- **Qué corrige (sólo lo determinista y pedido).**
  - Imágenes que el administrador clasifica como decorativas: se publican con `alt=""` sin borrar el alt guardado, incluso con la optimización de imágenes apagada.
  - Protección táctil opcional (`perf_touch_guard`, apagada): un `::after` centrado con especificidad cero en los selectores enumerados.
  - Un handle mostrado como seguro puede añadirse a la lista diferible de ese sitio con un botón; la lista global nunca se amplía sola.
  - Colores, encabezados, textos de enlace, destinos y jerarquía nunca se cambian.
- **Imágenes.**
  - Estados OPTIMIZADA, SIZES CORREGIDO, SIZES GENÉRICO y SIN DIMENSIONES.
  - Un `sizes="100vw"` existente se sustituye sólo por el layout real de Elementor, nunca por una suposición.
  - Los slides de Swiper reciben width/height del archivo que cargarán.
  - El debug muestra decoding.
- **llms.txt.** `Digitalisimo_Integrations_LLMS` ya no depende del módulo AI.
  - Se sirve en `home_url('/llms.txt')` de cada sitio, también en subdirectorios.
  - Modos: Automático, con nombre, descripción, portada, páginas publicadas e indexables, contenido reciente y sitemap; Manual; Híbrido; y Desactivado.
  - La salida se valida (un solo H1 al inicio, al menos un enlace http(s), sin HTML, ≤ 100 KB). Un manual inválido nunca se publica: se sirve el automático y la pestaña Agentes IA muestra los errores.
  - Un archivo físico `llms.txt` en la raíz del servidor tiene prioridad sobre WordPress.
- **Multisite y caché.**
  - Cada auditoría vive en una opción del sitio auditado (`digitalisimo_quality_audit_{md5(url)}`, índice de las 10 últimas) y guarda `blog_id`, URL y la versión de configuración con la que se midió. Si la configuración cambia, la pantalla pide repetirla.
  - Las lecturas usan `Digitalisimo_Integrations_Performance_Cache` (grupo `digitalisimo_performance`, claves por sitio), sin `wp_cache_flush()`.
  - Los roles de imagen (`digitalisimo_image_roles`) también son por sitio.
- **Validación pendiente en el sitio real.**
  - Auditar portada, una entrada y una página con formulario.
  - Confirmar que la vista móvil carga.
  - Revisar visualmente los carruseles tras añadir width/height a los slides.
  - Comprobar que una imagen marcada como decorativa publica `alt=""` (purgar caché de página).

## ALT contextual y palabra clave de apoyo · SEO 1.0.191

Fuente: solicitud del propietario del 2026-10-02, «CAMBIAR FUNCIÓN ALT PREDETERMINADO». La plantilla «Alt predeterminado de Biblioteca» se aplicaba a toda imagen sin ALT, así que una plantilla con la keyword repetía la misma palabra en todas. Se sustituye por `Digitalisimo_Integrations_Image_Alt`, que trabaja sólo en la salida, dentro del buffer de imágenes.

- **Orden de decisión.**
  1. ALT manual en el HTML o en la Biblioteca: se publica tal cual.
  2. Imagen clasificada como decorativa: `alt=""`.
  3. ALT igual al figcaption visible de su figura: `alt=""` en la página, sin tocar Medios.
  4. Icono o píxel: `alt=""`.
  5. Imagen ajena a la Biblioteca que ya trae `alt=""`: se respeta.
  6. Sin ALT: se genera con, por prioridad, el título útil del adjunto, el nombre limpio del archivo (sin tamaño, «-scaled», prefijos de cámara ni hashes, y con tildes frecuentes recuperadas), el título del widget (Image Box, CTA, Flip Box, testimonio), el H2/H3 anterior y el título del contenido. Una fuente ya usada en la página se salta para no repetir.
- **Logos.** El logo principal (custom-logo, widget Site Logo o `custom_logo`) usa «Nombre del sitio - descripción». Un logo de cliente usa sólo la marca («Logo-Kimball» → «Kimball»).
- **Personas.** «Nombre-Apellido» se reconoce sólo si empieza por un nombre de pila frecuente. En modo Contextual se añade «de {sitio}».
- **Palabra clave de apoyo para ALT** (`seo_alt_keyword_mode`: Desactivado, Sólo fallback o Contextual, que es el predeterminado).
  - En Contextual se añade como mucho a una imagen por página y sólo si comparte vocabulario con su ALT (completa las palabras que faltan) o con su widget o encabezado (« – keyword»).
  - En Sólo fallback se usa una vez, únicamente cuando no hay ningún otro contexto.
  - La palabra clave (`seo_alt_keyword`) es exclusiva de cada sitio. Si está vacía, se usa el texto fijo de la antigua plantilla y, en su defecto, la keyword principal del contenido.
  - `seo_alt_optimize` apaga todo el motor. El modo y el interruptor se heredan de la red.
- **Auditoría.**
  - La captura guarda por imagen el ALT publicado, su origen y su estado: MANUAL, GENERADO, REPETIDO, IGUAL A CAPTION, DECORATIVO, VACÍO CORRECTO, POSIBLEMENTE GENÉRICO o SIN ALT. Se muestra en Rendimiento y calidad → Imágenes → Auditoría de ALT.
  - La auditoría del navegador añade ALT repetido entre imágenes visibles y ALT genérico.
- **Límites.** Las frases descriptivas que no salen del archivo ni del contexto (por ejemplo, «análisis del» en «Gráfico de análisis del mercado digital») requieren un ALT manual o un asistente de IA. El motor nunca inventa contenido que la página no tiene.

## llms.txt corregido sobre la implementación existente · SEO 1.0.192

Fuente: solicitud del propietario del 2026-10-02, «CORREGIR EL LLMS.TXT EXISTENTE». Se corrigió `Digitalisimo_Integrations_LLMS` (`includes/class-llms.php`) en su lugar. Se conservan la ruta `/llms.txt`, sus ajustes (`seo_ai_llms_*`), la caché del grupo `digitalisimo_performance` y las pantallas existentes (Rendimiento y calidad → Agentes IA, y SEO AI → llms.txt). No hay ruta, módulo ni pantalla nuevos.

- **Fallo observado en producción.** `GET https://digitalisimo.mx/llms.txt` respondía 301 a `/llms.txt/` y luego HTML con 200: con el módulo desactivado, la ruta caía en el enrutado de WordPress y la redirección canónica añadía la barra.
  - Ahora, desactivado (o con salida inválida), responde 404 en `text/plain`.
  - Activado, `/llms.txt/` redirige 301 a `/llms.txt`.
- **Estructura automática.**
  - `# {get_bloginfo('name')}`.
  - `> {descripción}`: meta descripción SEO de la portada, en su defecto `get_bloginfo('description')`; si no hay ninguna, no se escribe nada.
  - Párrafo opcional con el extracto de la portada, si dice algo distinto.
  - `## Páginas principales` con `- [Inicio](home_url('/')): {tagline o «Página principal del sitio.»}`.
  - `## Optional` (sección estándar de llmstxt.org) con el sitemap si está activo.
  - Ya no se listan páginas ni entradas automáticamente. Sólo se añaden las URLs elegidas que pertenecen al sitio, existen, están publicadas, son indexables y no tienen ancla. Se retiró `SEO_AI_LLMS_Tools::automatic_resources()`, que ya no estaba conectada y listaba 30 contenidos.
  - Shortcodes, HTML y entidades se eliminan.
- **Diagnóstico real.**
  - `LLMS::check_endpoint()` pide `/llms.txt` al propio sitio sin seguir redirecciones.
  - `LLMS::diagnose()` informa HTTP, Content-Type, H1, enlaces, secciones y bytes, con el estado CORRECTO, FALTA H1, SIN ENLACES, VACÍO o ERROR HTTP. Un HTML con 200 o una redirección cuentan como ERROR HTTP.
  - Se muestra en Agentes IA y en SEO AI → llms.txt.
- **Caché.** Además de los ajustes del plugin y del guardado de contenidos, se invalida al cambiar `blogname`, `blogdescription`, `home`, `siteurl`, `page_on_front` y `show_on_front`.
- **Pendiente de verificación en producción.** Activar «Activar llms.txt» en SEO AI, purgar la caché de página o CDN (puede conservar el 301 anterior) y comprobar `GET /llms.txt`.

### llms.txt vuelve a SEO AI · SEO 1.0.193

Por decisión del propietario, llms.txt se configura en **SEO AI → llms.txt**, su pestaña original, y no en Rendimiento. Se retiró la pestaña «Agentes IA» de Rendimiento y calidad, junto con sus campos duplicados (`performance_agents_*_fields`).

- **Pestaña de SEO AI.** Conserva su formulario y su guardado (sitio y red, con herencia `ai_inherit`).
  - El modo pasa de texto libre a selector.
  - El guardado valida el modo con `LLMS::sanitize_mode()` y las URLs con `LLMS::sanitize_urls()`.
  - Debajo del formulario, `LLMS::render_status()` muestra el estado, la respuesta real de `/llms.txt` y la vista previa. En la red sólo se explica que cada sitio ve su propio diagnóstico.
- **Auditoría.** El resumen enlaza la fila llms.txt a esa pestaña.
- **Límite.** SEO AI sólo existe con el módulo AI y Chatbot activo. Sin él, `/llms.txt` sigue funcionando con los valores guardados, pero no hay pantalla para cambiarlos.

### Correcciones con la primera auditoría de ALT e imágenes · SEO 1.0.199

Los datos reales de digitalisimo.mx mostraron estos ajustes.

- **ALT del logo.** El logo usa `get_bloginfo('name')` y omite la descripción si el nombre ya la contiene. Un archivo «logo» sin marca, compuesto sólo por variantes como «Logo-solo-blanco», es el logo del sitio.
- **Fondos y adornos.** Un nombre con fondo, background, bg, pattern, shape, divider u overlay recibe `alt=""` en vez de un ALT generado.
- **Imágenes repetidas.** La misma imagen repetida en la página repite su ALT, sea manual o generado, en lugar de tomar otra fuente.
- **Limpieza de nombres.**
  - Se conservan los números que forman parte del nombre («Coahuila 1000»).
  - Se conservan «web», «pantalla», «sin» y las conjunciones de una letra.
  - Los nombres con Mayúsculas Iniciales mantienen sus mayúsculas.
  - Se recuperan siglas (SEO, AI) y tildes frecuentes.
  - Se retiran sufijos como «webp-comprimida».
- **Palabra clave.** Sólo completa el ALT si faltan una o dos palabras significativas, para no producir fragmentos como «agencia de en mexico». Si no encaja, no se fuerza.
- **ALT igual al nombre del archivo.** Un ALT manual idéntico al nombre del archivo («fondo-olas-1») se informa como POSIBLEMENTE GENÉRICO, sin cambiarlo.
- **Metadata dañada.** Una imagen de la Biblioteca con `width="1" height="1"` ya no se trata como píxel de seguimiento. Para el ALT, su clase `wp-image-N` la identifica aunque su metadata no permita vincularla.
- **`sizes` de ancho fijo.** Call to Action con imagen y el widget Image con ancho en px publican un `sizes` exacto, con sus valores de tablet y móvil, en lugar del «(max-width: Npx) 100vw, Npx» de WordPress. Los iconos de 75 px con DPR 2 descargan la variante de 150 px, no la de 300.
- **Imágenes → Auditoría de imágenes.** Usa la última auditoría de calidad cuando no hay captura del diagnóstico de assets.

## Favicon generado · SEO 1.0.213

En SEO → Avanzado, «Generar favicon» (`seo_favicon_source`) recibe un único PNG cuadrado de 512 px o más mediante el campo de Biblioteca y arrastre. La clave es propia de cada sitio (`site_only_keys`), como el nombre del sitio: no se hereda de la red.

- **Generación.** `Digitalisimo_Integrations_Favicon` usa `wp_get_image_editor()` para generar `favicon-48x48.png`, `favicon-96x96.png`, `favicon-192x192.png`, `favicon-512x512.png` y `apple-touch-icon.png` (180 px).
  - `favicon.ico` es un contenedor ICO con los PNG de 16, 32 y 48 px, porque WordPress no escribe ICO. Se comprobó con `file` y Pillow.
- **Ubicación y versión.** Los archivos van a `uploads/digitalisimo-favicon/` del sitio en curso (`uploads/sites/N/` en Multisite), con nombres fijos. La versión `?v=` es el hash del PNG maestro.
- **Regeneración.** Se regenera al guardar sólo si cambia la URL o el contenido del PNG. Existe además un botón «Regenerar ahora».
- **Head.** Con favicon activo se retira `wp_site_icon` de `wp_head`, `login_head` y `admin_head`, y se imprimen seis etiquetas: el ICO, los cuatro PNG y apple-touch-icon.
  - El ajuste «Icono del sitio» de WordPress (el que usa Elementor) no se modifica.
  - `/favicon.ico` se sirve desde `do_faviconico`.
  - Al quitar el PNG se borran los archivos generados y vuelve el icono anterior.
- **Diagnóstico.** La pantalla lee la portada y avisa si siguen publicándose otros iconos, escritos por el tema, un plugin o una caché.
