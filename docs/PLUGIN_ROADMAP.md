# Roadmap de funcionalidades · Digitalisimo

Leyenda: `✓` implementado, `~` parcial/en validación, `□` pendiente. La prioridad se ejecuta por lotes para mantener paquetes instalables.

| Área | Estado | Alcance actual / siguiente avance |
|---|---:|---|
| Núcleo compartido y menú único | ✓ | Cada módulo aporta el mismo core protegido contra clases duplicadas. |
| Entrega y actualización | ~ | Cada módulo consulta Releases de GitHub por su ZIP y el repositorio incluye CI para lint PHP, construcción y validación previa a publicar. La caché del índice dura una hora y cada módulo añade un enlace «Buscar actualizaciones» que la invalida junto con el transient `update_plugins`, porque «Volver a comprobar» de WordPress no la toca. Falta comprobar una actualización desde WordPress activo. |
| Seguridad de endpoints | ~ | Chatbot y búsqueda de dominios usan límites efímeros por visitante y no guardan IPs, prompts ni respuestas. Falta validación de carga en WordPress público. |
| Interfaz compartida | ~ | SEO Front muestra miniaturas de imágenes propias o externas, puntaje global y por sección, recomendaciones técnicas y, cuando Digitalisimo IA Tools está activo, una propuesta bajo demanda del perfil SEO. Se organiza como Resumen, Encabezados, Imágenes, Enlaces, Social y Herramientas con el detalle público equivalente a un inspector SEO. |
| Multisite con herencia | ~ | SEO, AI, Ecommerce, Geolocalización y Hosting resuelven valores dinámicamente desde sitio/red y muestran su origen en el módulo. Falta validación runtime en una red real. |
| Acceso oculto al escritorio | ~ | Ruta privada configurable en SEO → General y en Red → Digitalisimo → SEO → General, con herencia sitio/red: sirve wp-login.php desde el slug elegido y deja sin respuesta wp-login.php, /wp-admin, /admin y /dashboard para visitas sin sesión; con sesión iniciada /wp-admin no cambia. Cada sitio resuelve la ruta sobre su propio `home_url()`, por lo que un valor de red sirve para subdominios y subdirectorios. Salida de emergencia por constante `DIGITALISIMO_HIDE_LOGIN_DISABLE`. Falta validación runtime en WordPress individual y en una red real, en particular el intercambio de cookies entre dominios de un Multisite por subdominios. |
| SEO tradicional | ✓ | Canonical, robots/noindex, sitemap, schema, keywords, clusters e importación Yoast/Rank Math mediante una única emisión pública SEO; conserva metadatos ya migrados. |
| Crawlers IA | ~ | Registro central, presets, alta personalizada y origen efectivo disponibles. Verificación manual oficial DNS inverso/directo con caché para Google y Bing; faltan mecanismos oficiales equivalentes de otros proveedores cuando estén disponibles. |
| robots.txt IA | ~ | Reglas dinámicas, vista previa, alerta de archivo físico y tabla efectiva en Rastreadores y General disponibles. Cada crawler muestra su valor y origen sitio/red; falta validación runtime con robots público. |
| Negocio local: teléfono y horario | ~ | El teléfono se pide con lada desplegable (países de LatAm, España y EE. UU./Canadá) y número aparte, y se concatena al publicarlo. El horario se elige por día con estado abierto/cerrado/24 h, horario partido opcional y copia lunes→semana, lunes→L-V y sábado→fin de semana. Se emite como `openingHoursSpecification` omitiendo los días cerrados; antes el campo existía pero nunca llegaba al schema. El textarea anterior se convierte automáticamente la primera vez. Falta validación runtime con Rich Results Test. |
| Perfiles sociales | ~ | Un campo por red (Facebook, Instagram, X, LinkedIn, YouTube, TikTok, WhatsApp, Pinterest, Threads y Google Business) en sitio y en red, con textarea sólo para perfiles sin campo propio. Lo ya guardado como lista se reparte automáticamente por dominio la primera vez. `sameAs` se emite deduplicado. Falta validación runtime del reparto con datos reales. |
| Entidades | ~ | Reutiliza configuración SEO y cuenta con pantalla de consistencia para Organization, WebSite, SEO local, logo y perfiles. Schema Graph usa IDs estables para Organization, WebSite, WebPage, tipo editorial, Breadcrumb y SEO local; falta validación runtime en HTML publicado. |
| Auditoría y diagnóstico SEO AI | ~ | Análisis manual por URL: HTTP, redirecciones, robots, canonical, sitemap, HTML, H1/headings, enlaces internos, Schema, IDs estables, entidades, autoridad editorial y WAF/CDN. Falta validación runtime con contenido y tráfico reales. |
| llms.txt | ~ | Endpoint, modos, selección automática de recursos públicos indexables, selector AJAX, vista previa, regeneración visual y comprobación HTTP administrativa disponibles. La ruta se registra al activar sin interceptar robots.txt u otros .txt; falta validación funcional desde un sitio público con CDN/WAF cuando aplique. |
| IndexNow | ~ | Cola asíncrona, batch, log, generación de key, publicación/actualización/cambios de estado/eliminación, reintentos con backoff y reenvío individual manual incluso con automatización desactivada disponibles. Falta validación funcional contra un sitio WordPress público. |
| Referencias IA | ~ | Registro sin IP/prompts, resumen, retención 30/90/180/365, borrado, evolución diaria, landings y fuentes configurables disponibles. Falta validación funcional con tráfico real y consentimientos/analítica del sitio cuando aplique. |
| SEO AI por contenido | ~ | Estado efectivo y controles por contenido disponibles; las reglas Allow/Disallow se aplican por URL y User-Agent en el mismo robots.txt, sin confundirlas con noindex. Falta validación runtime de sintaxis robots con un sitio público. |
| Base de conocimiento IA | ~ | Índice local, reconstrucción, recuperación contextual RAG, panel de fuentes, embeddings OpenAI manuales/opt-in y reordenamiento semántico opcional con caché de consulta disponibles. Falta validación runtime contra una cuenta OpenAI y un sitio configurado. |
| Digitalisimo IA Tools | ✓ | Perfiles por proveedor y fallback; chatbot, contenido, SEO, ecommerce, conocimiento e imágenes bajo demanda. Incluye endpoints protegidos para que Codex guarde borradores SEO desde una keyword y el cluster elegido, sin publicar ni llamar a un proveedor IA. |
| Generación de imágenes | ✓ | Flujo revisable hacia Biblioteca de Medios. |

## Orden de ejecución pendiente

1. Completar crawlers, presets, robots efectivo y Multisite.
2. Completar auditoría, entidades, diagnóstico y score por URL.
3. Completar llms.txt e IndexNow.
4. Completar referencias IA y privacidad.
5. Completar embeddings opcionales y panel de conocimiento IA.
6. Validar en WordPress individual y Multisite, después generar paquetes versionados.

## Validación de paquetes

Última validación estructural: los ZIP de SEO 1.0.71, Ecommerce 1.0.14, Geolocalización 1.0.12, IA Tools 1.0.25 y Hosting 1.0.8 contienen su archivo principal, declaran la versión esperada y superan `unzip -t`.
