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
