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

Referencias API: [WordPress Object Cache](https://developer.wordpress.org/reference/classes/wp_object_cache/), [detección de caché externa](https://developer.wordpress.org/reference/functions/wp_using_ext_object_cache/), [filtro de etiquetas CSS](https://developer.wordpress.org/reference/hooks/style_loader_tag/) y [tipografía global Elementor](https://developers.elementor.com/docs/editor-controls/global-style/).
