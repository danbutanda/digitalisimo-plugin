# Roadmap de funcionalidades · Digitalisimo

Leyenda: `✓` implementado, `~` parcial/en validación, `□` pendiente. La prioridad se ejecuta por lotes para mantener paquetes instalables.

| Área | Estado | Alcance actual / siguiente avance |
|---|---:|---|
| Núcleo compartido y menú único | ✓ | Cada módulo aporta el mismo core protegido contra clases duplicadas. |
| Entrega y actualización | ~ | Cada módulo consulta Releases de GitHub por su ZIP y el repositorio incluye CI para lint PHP, construcción y validación previa a publicar. Falta comprobar una actualización desde WordPress activo. |
| Seguridad de endpoints | ~ | Chatbot y búsqueda de dominios usan límites efímeros por visitante y no guardan IPs, prompts ni respuestas. Falta validación de carga en WordPress público. |
| Interfaz compartida | ~ | Cada módulo carga una superficie administrativa moderna y coherente; falta comprobación visual dentro de WordPress. |
| Multisite con herencia | ~ | SEO, AI, Ecommerce, Geolocalización y Hosting resuelven valores dinámicamente desde sitio/red y muestran su origen en el módulo. Falta validación runtime en una red real. |
| SEO tradicional | ✓ | Canonical, robots/noindex, sitemap, schema, keywords, clusters e importación Yoast/Rank Math mediante una única emisión pública SEO; conserva metadatos ya migrados. |
| Crawlers IA | ~ | Registro central, presets, alta personalizada y origen efectivo disponibles. Verificación manual oficial DNS inverso/directo con caché para Google y Bing; faltan mecanismos oficiales equivalentes de otros proveedores cuando estén disponibles. |
| robots.txt IA | ~ | Reglas dinámicas, vista previa, alerta de archivo físico y tabla efectiva en Rastreadores y General disponibles. Cada crawler muestra su valor y origen sitio/red; falta validación runtime con robots público. |
| Entidades | ~ | Reutiliza configuración SEO y cuenta con pantalla de consistencia para Organization, WebSite, SEO local, logo y perfiles. Schema Graph usa IDs estables para Organization, WebSite, WebPage, tipo editorial, Breadcrumb y SEO local; falta validación runtime en HTML publicado. |
| Auditoría y diagnóstico SEO AI | ~ | Análisis manual por URL: HTTP, redirecciones, robots, canonical, sitemap, HTML, H1/headings, enlaces internos, Schema, IDs estables, entidades, autoridad editorial y WAF/CDN. Falta validación runtime con contenido y tráfico reales. |
| llms.txt | ~ | Endpoint, modos, selección automática de recursos públicos indexables, selector AJAX, vista previa, regeneración visual y comprobación HTTP administrativa disponibles. La ruta se registra al activar sin interceptar robots.txt u otros .txt; falta validación funcional desde un sitio público con CDN/WAF cuando aplique. |
| IndexNow | ~ | Cola asíncrona, batch, log, generación de key, publicación/actualización/cambios de estado/eliminación, reintentos con backoff y reenvío individual manual incluso con automatización desactivada disponibles. Falta validación funcional contra un sitio WordPress público. |
| Referencias IA | ~ | Registro sin IP/prompts, resumen, retención 30/90/180/365, borrado, evolución diaria, landings y fuentes configurables disponibles. Falta validación funcional con tráfico real y consentimientos/analítica del sitio cuando aplique. |
| SEO AI por contenido | ~ | Estado efectivo y controles por contenido disponibles; las reglas Allow/Disallow se aplican por URL y User-Agent en el mismo robots.txt, sin confundirlas con noindex. Falta validación runtime de sintaxis robots con un sitio público. |
| Base de conocimiento IA | ~ | Índice local, reconstrucción, recuperación contextual RAG, panel de fuentes, embeddings OpenAI manuales/opt-in y reordenamiento semántico opcional con caché de consulta disponibles. Falta validación runtime contra una cuenta OpenAI y un sitio configurado. |
| Chatbot y perfiles IA | ✓ | Perfiles por proveedor y fallback; contenido sólo bajo demanda. |
| Generación de imágenes | ✓ | Flujo revisable hacia Biblioteca de Medios. |

## Orden de ejecución pendiente

1. Completar crawlers, presets, robots efectivo y Multisite.
2. Completar auditoría, entidades, diagnóstico y score por URL.
3. Completar llms.txt e IndexNow.
4. Completar referencias IA y privacidad.
5. Completar embeddings opcionales y panel de conocimiento IA.
6. Validar en WordPress individual y Multisite, después generar paquetes versionados.

## Validación de paquetes

Última validación estructural: los ZIP de SEO 1.0.26, Ecommerce 1.0.9, Geolocalización 1.0.7, AI/Chatbot 1.0.12 y Hosting 1.0.3 contienen su archivo principal, declaran la versión esperada y superan `unzip -t`. La prueba runtime en WordPress individual/Multisite continúa pendiente.
