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
