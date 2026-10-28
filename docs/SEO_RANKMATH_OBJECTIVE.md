# Objetivo: lo que DIGITALÍSIMO SEO incorpora de Rank Math

Inventario de lo que DIGITALÍSIMO SEO (y su parte SEO AI) debe incorporar para que un sitio que hoy usa Rank Math
pueda retirarlo **sin perder datos ni funciones útiles**. Sale de leer el código de las dos copias de referencia, no de
la documentación del proveedor. Redactado el 2026-10-26. Todavía no se ha implementado nada: cada etapa se abre con
su propia rama y su aceptación, como los incrementos de la fusión.

## Fuentes

| Copia | Versión | Archivos | sha256 del ZIP | Verificación |
|---|---|---|---|---|
| `reference/seo-by-rank-math.zip` | Rank Math SEO 1.0.279 | 1 337 | `7386444ec3d801a979606ea650afb84d1b765ed78ac5b6100e74f86dadb7a7b7` | Los 1 337 archivos coinciden con `downloads.wordpress.org/plugin-checksums/seo-by-rank-math/1.0.279.json` |
| `reference/seo-by-rank-math-pro-2.zip` | Rank Math SEO PRO 3.0.123 | 642 | `a3f055719183ce79ee1377a0210da97459edf981087ec3eed8de12de06b7bb08` | Sin sumas públicas (no está en wordpress.org); sólo `unzip -t` |

Las dos son GPL (la gratuita GPL-3.0+; el PRO declara GPL-3.0+ y enlaza el texto de la GPL-2.0). Ninguna está anotada
todavía en `reference/OFFICIAL-SOURCES.txt`, así que **sólo se leen**: no se instalan ni en Playground hasta anotar su
origen y suma. Las rutas de este documento son relativas a `seo-by-rank-math/includes/` (gratuito) o
`seo-by-rank-math-pro/includes/` (PRO).

## Reglas de la incorporación

- **Portar la lógica, no los archivos.** El código de Rank Math depende de su marco (CMB2, `RankMath\Helper`, su query
  builder `DB_Helper`, Action Scheduler) y está lleno de `rank_math`, de su dominio y de su cabecera
  `X-Redirect-By: Rank Math`. Nada de eso entra: se reescribe con nuestras clases y nombres. Si algún fragmento se
  adapta casi literal, se anota con su atribución en `docs/third-party-licenses.md`.
- **Sin rastro del proveedor.** Ni enlaces, ni textos, ni peticiones a sus servicios. Su nombre sólo aparece, sin
  enlace, donde el administrador lo necesita: el importador («Importar desde Rank Math») y los avisos de desactivación.
- **Paridad sitio/red de `AGENTS.md`** para toda opción nueva: `defaults()`, `sanitize()`, `network_site_fields()` y
  `save_network()`, y lectura por `Digitalisimo_Integrations_SEO_Resolver::option()`. Los **datos** de un sitio
  (redirecciones, registro 404, enlaces, schema por entrada) son de ese sitio: viven en tablas con `$wpdb->prefix` y
  la red no los hereda; la red sólo define los **ajustes** por defecto.
- **Un solo `@graph`.** Todo schema nuevo pasa por `Digitalisimo_Integrations_Schema_Graph` y se valida con
  `Digitalisimo_Integrations_Schema_Vocabulary`. Nunca se imprime otro bloque JSON-LD.
- **Sólo informar, nunca reescribir contenido.** Lo que en Rank Math modifica el contenido guardado (auto-enlazado de
  Link Genius, reemplazos en `alt`) aquí queda como sugerencia o como decisión explícita del administrador.
- **Tareas largas en cola.** Importación, recuento de enlaces y comprobación de enlaces rotos corren por lotes en cron,
  nunca durante un guardado ni en una sola petición.
- **Fallar cerrado.** Lo que no se puede traducir con certeza (una regla regex inválida, un tipo de schema que no existe,
  una variable desconocida, un `robots.txt` que bloquearía el sitio) se informa y no se activa.

## Estado actual de nuestro SEO frente a Rank Math

Ya cubierto (no se duplica):

| Rank Math | DIGITALÍSIMO SEO |
|---|---|
| Títulos y descripciones con variables | `seo_title_*`/`seo_description_*` y `Digitalisimo_Integrations_SEO_Suite` |
| Robots por entrada, tipo y archivo | `noindex_*`, `digitalisimo_seo_noindex`/`nofollow`, filtro `wp_robots` |
| Canonical | `digitalisimo_seo_canonical` |
| Open Graph y Twitter | `social()` en `class-seo-suite.php`, con título, descripción e imagen social por entrada |
| Sitemap XML | `Digitalisimo_Integrations_Sitemap` |
| `robots.txt`, `llms.txt`, IndexNow | `class-robots.php`, `class-llms.php`, `class-seo-ai-indexnow-tools.php` |
| Schema | `Digitalisimo_Integrations_Schema_Graph` (`@graph` único) |
| Migas | `[digitalisimo_breadcrumbs]` y BreadcrumbList |
| SEO local | `Digitalisimo_Integrations_Local_Business` y catálogo de ubicaciones |
| Contenido pilar | `digitalisimo_seo_pillar` y shortcodes `[contenido_pilar]` |
| Auditoría del sitio | `Digitalisimo_Integrations_SEO_Audit_Hub` |
| Redirección de adjuntos | `seo_redirect_attachments` |

Hallazgos en nuestro propio código que esta etapa corrige:

1. **`seo_sitemap_images` no hace nada.** Está en `Digitalisimo_Integrations_Settings::defaults()`
   (`class-settings.php:133`) y en la sanitización, pero `class-sitemap.php` nunca imprime `<image:image>` ni declara
   el espacio de nombres `xmlns:image`.
2. **El importador de Rank Math es mínimo.** `Digitalisimo_Integrations_SEO::import_seo_data()` (`class-seo.php:92`)
   sólo trae `rank_math_title`, `rank_math_description`, `rank_math_focus_keyword`, `rank_math_canonical_url`, el
   `noindex` de `rank_math_robots` y `rank_math_pillar_content`. Además:
   - corre en **una sola petición** con `posts_per_page => -1`, lo que puede agotar el tiempo en sitios grandes;
   - **no importa términos, usuarios, opciones globales ni redirecciones**;
   - existe además `migrate_rankmath()` (`class-seo.php:88`), una versión anterior que repite la misma lógica.
   - **ninguno de los dos estaba registrado:** `Digitalisimo_Integrations_SEO::init()`, que añade sus acciones, no se llama en el arranque, así que no había ningún importador accesible. RM1 lo sustituye con su propia pestaña «Importar».

## Etapas

Cada etapa lleva su identificador `RM<n>`. El orden es el de prioridad: RM1 y RM2 evitan pérdidas al retirar Rank Math;
el resto amplía funciones.

### RM1 · Importador completo de Rank Math

**Objetivo:** que desactivar Rank Math no pierda ningún dato que nuestro SEO sepa usar, y que lo que no sepa usar quede
listado en un informe.

**Cómo funciona.**
- Lee directamente las metas, las opciones y las tablas, así que **funciona con Rank Math desactivado**.
- Corre en cola por lotes (cron) y por sitio; en una red, la acción de red encola cada sitio.
- Es idempotente y **nunca sobrescribe** un valor ya editado en DIGITALÍSIMO, igual que hoy.
- Deja un informe por sitio con lo importado, lo omitido y el motivo de cada omisión.
- Sustituye a `import_seo_data()` y `migrate_rankmath()` conservando su botón y su acción `admin-post.php`.

**Dónde guarda Rank Math cada dato.**

| Origen | Contenido |
|---|---|
| `rank-math-options-general` | Enlaces, migas, verificación, redirecciones, 404, imágenes, WooCommerce, RSS, robots.txt, llms.txt |
| `rank-math-options-titles` | Plantillas por tipo y taxonomía, robots globales, identidad, social, local, archivos |
| `rank-math-options-sitemap` | Tipos, taxonomías, exclusiones, imágenes, autores, HTML, noticias y vídeo (PRO) |
| `rank-math-options-instant` | IndexNow (`indexnow_api_key`, `bing_post_types`) |
| `rank_math_modules` | Módulos activos (sólo para el informe) |
| Metas de entrada, término y usuario con prefijo `rank_math_` | Datos por contenido |
| `{prefix}rank_math_redirections` | Redirecciones (ver RM2) |
| `{prefix}rank_math_404_logs` | Registro 404 (no se importa; ver RM3) |

Los booleanos de las opciones se guardan como `'on'`/`'off'`, no como `1`/`0`.

**Metas por entrada** (los términos y usuarios usan las mismas claves con `get_term_meta`/`get_user_meta`):

| Meta de Rank Math | Destino | Notas |
|---|---|---|
| `rank_math_title` | `digitalisimo_seo_title` | Traducir variables (RM10) |
| `rank_math_description` | `digitalisimo_seo_description` | Traducir variables |
| `rank_math_focus_keyword` | `digitalisimo_seo_keywords` | Lista separada por comas; la primera es la principal. Respetar `keyword_max_count` (5) e informar el resto |
| `rank_math_canonical_url` | `digitalisimo_seo_canonical` | `esc_url_raw` |
| `rank_math_robots` (array) | `digitalisimo_seo_noindex`, `digitalisimo_seo_nofollow` y las nuevas de RM6 | Valores: `index`, `noindex`, `nofollow`, `noarchive`, `noimageindex`, `nosnippet` |
| `rank_math_advanced_robots` (array) | Nuevas metas de RM6 | `max-snippet`, `max-video-preview`, `max-image-preview` |
| `rank_math_pillar_content` = `'on'` | `digitalisimo_seo_pillar` | Ya se importa |
| `rank_math_breadcrumb_title` | Nueva `digitalisimo_seo_breadcrumb_title` (RM13) | |
| `rank_math_facebook_title` / `_description` | `digitalisimo_seo_social_title` / `_description` | |
| `rank_math_facebook_image` (+ `_image_id`) | `digitalisimo_seo_social_image` | Es un campo `media`: guardar la URL del adjunto |
| `rank_math_twitter_use_facebook`, `rank_math_twitter_title`, `_description`, `_image`, `_card_type` | Informe | Tenemos un solo juego social; sólo se informa cuando Twitter difiere de Facebook |
| `rank_math_primary_{taxonomía}` (p. ej. `rank_math_primary_category`) | Nueva `digitalisimo_seo_primary_{taxonomía}` (RM13) | Lo usan las migas y la variable `%primary_taxonomy_terms%` |
| `rank_math_schema_{Tipo}` | Schema nativo (RM8) | Una meta por schema; ver formato en RM8 |
| `rank_math_rich_snippet`, `rank_math_snippet_*` | Informe | Formato anterior a los schemas múltiples |
| `rank_math_exclude_sitemap` (en adjuntos) | Exclusión del sitemap (RM4) | |
| `rank_math_news_sitemap_robots` = `'noindex'` | Nueva meta de RM4 | |
| `_rank_math_gtin_code` (y por variación) | Nueva meta de GTIN (RM8/RM12) | PRO |
| `rank_math_seo_score`, `rank_math_contentai_score`, `rank_math_analytic_object_id`, `rank_math_internal_links_processed`, `rank_math_auto_redirect`, `rank_math_dont_show_seo_score`, `rank_math_hide_frontend_stats` | No se importan | Estado interno de Rank Math |

**Opciones globales** (`rank-math-options-titles` salvo que se indique otro grupo):

| Clave de Rank Math | Destino |
|---|---|
| `title_separator` | `seo_separator` |
| `website_name` | `seo_site_name` |
| `knowledgegraph_type` (`company`/`person`), `knowledgegraph_name`, `knowledgegraph_logo`, `url`, `email`, `phone` | `seo_organization_type`, `seo_organization_name`, `seo_logo`, `seo_organization_url`, `seo_organization_email` |
| `open_graph_image` | `seo_default_image` |
| `twitter_card_type` | `seo_twitter_card` |
| `social_url_facebook`, `social_additional_profiles` | `seo_social_profiles` (con `Digitalisimo_Integrations_Social_Profiles`; lo que no encaja va al textarea libre) |
| `twitter_author_names` | `seo_twitter_user` |
| `pt_post_title` / `_description`, `pt_page_title` / `_description` | `seo_title_post`, `seo_description_post`, `seo_title_page`, `seo_description_page` |
| `pt_{otro tipo}_*` | Informe hasta que existan plantillas por tipo (RM10) |
| `tax_{taxonomía}_title` / `_description` | `seo_title_taxonomy` / `seo_description_taxonomy` (sólo si todas coinciden; si no, informe) |
| `noindex_search` | `noindex_search` |
| `author_robots` con `noindex`, `disable_author_archives` | `noindex_authors` |
| `date_archive_robots` con `noindex`, `disable_date_archives` | `noindex_date_archives` |
| `noindex_empty_taxonomies` | `noindex_empty_tags` |
| `local_business_type`, `local_address`, `phone_numbers`, `opening_hours`, `price_range`, `geo` | `seo_local_type`, `seo_local_address`/`_city`/`_region`/`_postal`/`_country`, `seo_local_phone_cc` + `seo_local_phone`, `seo_local_schedule`, `seo_local_price_range`, `seo_local_latitude`/`_longitude` |
| general: `breadcrumbs` | `seo_breadcrumbs` |
| general: `attachment_redirect_urls` | `seo_redirect_attachments` |
| general: `google_verify`, `bing_verify`, `baidu_verify`, `yandex_verify`, `pinterest_verify`, `norton_verify` | RM5 |
| general: `robots_txt_content` | **Sólo informe.** Se muestra para revisión; no se copia a `seo_robots_rules` porque podría bloquear el rastreo (ver `docs/ROBOTS_POLICY.md`) |
| general: `llms_post_types`, `llms_taxonomies`, `llms_limit`, `llms_extra_content` | `seo_ai_llms_*` |
| instant: `indexnow_api_key` | `seo_ai_indexnow_key` (secreto: nunca se devuelve al navegador) |
| sitemap: `exclude_posts`, `exclude_terms` | `sitemap_exclude_ids` y exclusión de términos (RM4) |
| sitemap: `include_images`, `include_featured_image` | `seo_sitemap_images` (RM4) |
| sitemap: `pt_{tipo}_sitemap`, `tax_{taxonomía}_sitemap` | `sitemap_post_types`, `seo_sitemap_taxonomies` |

**Aceptación.**
- Un sitio de prueba con datos sintéticos de Rank Math (metas, opciones y una tabla de redirecciones con regex)
  importa todo lo mapeado.
- Ejecutarlo dos veces no cambia nada; un valor editado antes en DIGITALÍSIMO no se pisa.
- Funciona con Rank Math desactivado y en Multisite (cada sitio, sus datos).
- El informe lista cada omisión con su motivo.

**Pruebas:** `tests/seo-rankmath-import.php`, con fixtures sintéticos en `tests/sources/`.

### RM2 · Redirecciones

**Qué hace Rank Math** (`modules/redirections/`).

Tabla `rank_math_redirections`:

| Columna | Tipo | Uso |
|---|---|---|
| `id` | `bigint unsigned` | |
| `sources` | `longtext` (colación `_bin`) | Array PHP **serializado** de orígenes `{pattern, comparison, ignore}` |
| `url_to` | `text` | Destino |
| `header_code` | `smallint` | 301, 302, 307, 410 o 451 |
| `hits` | `bigint` | Contador |
| `status` | `varchar(25)` | `active`, `inactive`, `trashed` |
| `created`, `updated`, `last_accessed` | `datetime` | |

Índices: `status` y `(status, updated)`.

Tabla de caché `rank_math_redirections_cache`: `from_url`, `redirection_id`, `object_id`, `object_type`
(`post`/`term`/`user`/`any`), `is_redirected`.

**Comparaciones** (`helpers/class-choices.php`): `exact`, `contains`, `start`, `end` y `regex`. Con `ignore = 'case'`,
`exact` compara en minúsculas.

**Flujo por petición** (`class-redirector.php`). Corre en `wp` con prioridad 11 (en `template_redirect` si hay
BuddyPress) y se salta: `wp-login.php`, la vista previa del personalizador, AJAX, peticiones de script o
`HTTP_X_*`, `?elementor-preview` y peticiones sin `REQUEST_URI`. Pasos:

1. Normaliza la URI: separa la query string y recorta las barras; en AMP no canónico quita el segmento AMP.
2. `pre_filter`: un filtro permite resolver antes.
3. `from_cache`: busca por objeto consultado o por URL en la tabla de caché.
4. `everything`, en `DB::match_redirections()`:
   - primero preselecciona con `LIKE` sobre `sources`, usando el patrón exacto serializado y cada palabra de la URI
     (partida por `/`, `.` y `-`);
   - si no hay coincidencia, recorre **todas** las activas ordenadas por `updated DESC`;
   - prueba con la URI sin query string y luego con la URI completa;
   - guarda el acierto en la caché.
5. `fallback`, sólo en 404: `default` (no hace nada), `homepage` o `custom` (`redirections_custom_url`).
6. Al redirigir:
   - suma `hits` y actualiza `last_accessed`;
   - con 410 o 451 no redirige: marca el 404 y envía ese estado;
   - con regex, sustituye en `url_to` con `preg_replace` usando `@patrón@`;
   - **añade la query string original** al destino (filtrable);
   - redirige con `wp_redirect` y la cabecera `X-Redirect-By`.
7. Opción `redirections_debug`: a los administradores les muestra una página intermedia en lugar de redirigir.

**Vigilante** (`class-watcher.php`, opción `redirections_post_redirect`):
- guarda el enlace permanente antes del cambio (`pre_post_update` y su equivalente para términos);
- si cambia, crea una redirección 301 del enlace viejo al nuevo, salvo que ya exista una para ese objeto;
- con esta opción activa quita `wp_old_slug_redirect` de WordPress;
- también invalida la caché al cambiar entradas, términos y autores.

**PRO** (`modules/redirections/`):
- categorías: taxonomía `rank_math_redirection_category`;
- programación: activar o desactivar en una fecha, con Action Scheduler;
- importación y exportación CSV, por lotes en segundo plano.

**Lo que añadimos.**
- Tabla propia por sitio, `{prefix}digitalisimo_redirections`, con las mismas columnas salvo que **`sources` se guarda
  en JSON**, nunca serializado.
- Una caché precalculada para las reglas `exact`, consultable por índice. Las de prefijo, sufijo, contenido y regex se
  evalúan en orden sólo si la exacta falla.
- La misma lista de exclusiones de Rank Math, más las rutas propias que nunca deben redirigirse:
  - la ruta privada de acceso de Tools/SEO (`class-hide-login.php`);
  - `/wp-admin`, REST, `sitemap.xml` y sus hojas, `robots.txt`, `llms.txt`, la clave de IndexNow;
  - las vistas previas del constructor.
- Seguridad de las regex:
  - se valida cada patrón con `preg_match` antes de guardarlo y se rechaza el inválido;
  - se escapa el delimitador;
  - se limita la longitud;
  - se ejecuta con `pcre.backtrack_limit` acotado.
- Un destino externo a otro dominio exige `manage_options` y se marca en la tabla.
- Vigilante de slugs para entradas y términos, con su opción. Por defecto conserva `wp_old_slug_redirect` y sólo lo
  sustituye cuando la opción está activa.
- **Respaldo de 404: sólo «ninguno» o «URL elegida».** No ofrecemos «redirigir todo a la portada»: Google lo trata
  como soft 404.
- Página de administración Digitalisimo → SEO → Redirecciones (con `digitalisimo-ui`): alta, edición, activar o
  desactivar, papelera, búsqueda, recuento y fecha de último acceso.
- Categorías, programación con WP-cron y CSV (importar y exportar) como segunda parte de la etapa.
- **Importación desde Rank Math:**
  - lee `{prefix}rank_math_redirections` con `maybe_unserialize`, aceptando sólo arrays (nunca objetos);
  - conserva estado, código, `hits` y fechas;
  - una regla con regex inválida queda inactiva y en el informe.

**Ajustes nuevos** (paridad sitio/red): `seo_redirect_enabled`, `seo_redirect_default_code` (301),
`seo_redirect_slug_watch` y `seo_redirect_fallback_url` (vacío: los 404 responden 404). La página intermedia de
depuración de Rank Math no se porta: el gestor ya muestra visitas y último acceso de cada regla.

**Aceptación.**
- Cada tipo de comparación y cada código se comportan como se describe.
- La query string se conserva.
- 410 y 451 responden con su estado sin redirigir.
- No hay bucles: el destino no puede coincidir con su propio origen.
- La ruta privada de acceso y los archivos SEO nunca se redirigen.
- Una portada sin reglas no hace ninguna consulta extra.

**Pruebas:** `tests/seo-redirections.php` y `tests/seo-redirections-import.php`.

### RM3 · Monitor 404

**Qué hace Rank Math** (`modules/404-monitor/`). Tabla `rank_math_404_logs`: `id`, `uri` (índice sobre 191
caracteres), `accessed`, `times_accessed`, `referer` y `user_agent`. **No guarda la IP.**

Ajustes:
- `404_monitor_mode`: `simple` (una fila por URI con contador) o `advanced` (una fila por visita);
- `404_monitor_limit`: tope de filas;
- `404_monitor_exclude`: lista de patrones con las mismas comparaciones que las redirecciones;
- `404_monitor_ignore_query_parameters`.

Desde el registro se crea una redirección. El PRO añade funciones en `modules/404-monitor/class-monitor-pro.php`, que
hay que revisar al abrir la etapa.

**Lo que añadimos.**
- Tabla por sitio `{prefix}digitalisimo_404_log` sólo en modo simple: URI, contador, primer y último acceso, último
  referer y user agent recortado. Sin IP.
- Tope de filas y retención en días con limpieza en cron.
- Exclusiones con las mismas comparaciones de RM2.
- Ignorar `?` opcional.
- No registra bots verificados de `Digitalisimo_Integrations_SEO_AI_Crawler_Verifier` si así se elige, ni rutas de
  `wp-admin`, REST o archivos estáticos.
- Acción «Crear redirección» que abre RM2 con el origen relleno.
- Escritura barata: una sola consulta `INSERT … ON DUPLICATE KEY UPDATE` por 404, y nunca en peticiones de
  administración.

**Ajustes nuevos:** `seo_404_enabled`, `seo_404_limit`, `seo_404_retention`, `seo_404_exclude` y
`seo_404_ignore_query`.

**Pruebas:** `tests/seo-404-monitor.php`.

### RM4 · Sitemaps: imágenes, autores, HTML, noticias y vídeo

**Qué hace Rank Math.**

Gratuito (`modules/sitemap/settings/`):
- `include_images`, `include_featured_image` e imágenes de campos personalizados (`pt_{tipo}_image_customfields`);
- exclusión de adjuntos con `rank_math_exclude_sitemap`;
- sitemap de autores (`authors_sitemap`, `include_authors_without_posts`, `exclude_roles`, `exclude_users`);
- sitemap HTML (`html_sitemap`, `html_sitemap_display` como shortcode o página, `_sort`, `_show_dates`,
  `_seo_titles`);
- `items_per_page`, `exclude_posts` y `exclude_terms`.

Noticias, PRO (`modules/news-sitemap/`):
- tipos en `news_sitemap_post_type`;
- **sólo entradas de las últimas 48 horas** (`TIMESTAMPDIFF(MINUTE, post_date_gmt, UTC_TIMESTAMP()) <= 2880`) y
  **como máximo 1 000**;
- `<news:publication>` con `news_sitemap_publication_name` y el idioma, `<news:publication_date>` y `<news:title>`;
- exclusión por términos (`news_sitemap_exclude_{tipo}_terms`);
- por entrada, `rank_math_news_sitemap_robots = noindex` imprime un robots para Googlebot-News.

Vídeo, PRO (`modules/video-sitemap/`):
- sale del schema `rank_math_schema_VideoObject` de cada entrada;
- imprime `video:title`, `description`, `thumbnail_loc`, `player_loc`/`content_loc`, `publication_date`, `duration`,
  `rating`, `tag`, `family_friendly` y `uploader`;
- tipos en `video_sitemap_post_type`; `hide_video_sitemap` lo oculta a los no administradores.

**Lo que añadimos.**
- **Hacer real `seo_sitemap_images`:** `xmlns:image` y un `<image:image><image:loc>` por imagen destacada y por
  imagen del contenido del mismo sitio, con tope por URL y sin consultas por entrada (precarga por lote). Más la
  exclusión por adjunto.
- Exclusión de términos.
- Sitemap de autores, opcional y nunca para autores con `noindex_authors`.
- Sitemap HTML como shortcode `[digitalisimo_sitemap]`, sin crear páginas solo.
- Sitemap de noticias: `news-sitemap.xml` en el índice, con las mismas reglas de 48 h y 1 000, nombre de publicación
  (por defecto `seo_site_name`), idioma del sitio, exclusión por términos y meta por entrada para Googlebot-News.
- Sitemap de vídeo a partir del VideoObject que ya genera `Schema_Graph`. Nunca hace peticiones a YouTube o Vimeo en
  la petición del sitemap: la duración y la miniatura se resuelven en cola (RM8).
- Todo con el `home_url()` del sitio en curso y anunciado en el único `robots.txt` de `Digitalisimo_Integrations_Sitemap::robots()`.

**Ajustes nuevos:** `seo_sitemap_featured_images`, `seo_sitemap_exclude_terms`, `seo_sitemap_authors`,
`seo_sitemap_news_enabled`, `seo_sitemap_news_types`, `seo_sitemap_news_name`, `seo_sitemap_news_exclude_terms`,
`seo_sitemap_video_enabled` y `seo_sitemap_video_types`.

**Pruebas:** ampliar `tests/sitemap.php` y `tests/sitemap-routes.php`.

### RM5 · Verificación de buscadores

**Qué hace Rank Math** (`settings/general/webmaster.php`): `google_verify`, `bing_verify` (`msvalidate.01`),
`baidu_verify`, `yandex_verify`, `pinterest_verify` (`p:domain_verify`), `norton_verify` y `custom_webmaster_tags`
(HTML libre).

**Lo que añadimos.**
- Un campo por buscador. El valor admite el código o la etiqueta completa: se extrae el `content` y se valida con
  `[A-Za-z0-9_\-=:.]+`.
- En lugar del HTML libre, un campo repetible «nombre + contenido» que sólo produce etiquetas `<meta name>`.
- Sólo en la portada.

**Ajustes nuevos:** `seo_verify_google`, `seo_verify_bing`, `seo_verify_baidu`, `seo_verify_yandex`,
`seo_verify_pinterest`, `seo_verify_norton` y `seo_verify_custom`. Cada uno tiene su caso en `save_network()`: no
basta `sanitize_textarea_field()`.

**Pruebas:** `tests/seo-verification.php`.

### RM6 · Robots avanzados

**Qué hace Rank Math.**
- Robots por entrada (`rank_math_robots`: `noindex`, `nofollow`, `noarchive`, `noimageindex`, `nosnippet`).
- Robots avanzados (`rank_math_advanced_robots`: `max-snippet`, `max-video-preview`, `max-image-preview`), con sus
  equivalentes globales (`robots_global`, `advanced_robots_global`) y por tipo, taxonomía, autor y portada
  (`*_custom_robots`, `*_robots`, `*_advanced_robots`).
- Además: `noindex_paginated_pages`, `noindex_archive_subpages`, `noindex_password_protected`,
  `disable_author_archives` y `disable_date_archives` (éstos redirigen el archivo a la portada).

**Lo que añadimos.**
- Por entrada y por término: `noarchive`, `noimageindex`, `nosnippet` y `max-snippet`/`max-video-preview`.
  `max-image-preview:large` ya lo añade WordPress (`wp_robots_max_image_preview_large`) y no se duplica.
- Global, con paridad: `seo_noindex_paginated`, `seo_noindex_password`, `seo_max_snippet` y `seo_max_video_preview`.
- `seo_disable_author_archives` y `seo_disable_date_archives`: responden 301 a la portada (o a la página de autor
  externa elegida). Se aplican en `template_redirect`, sin tocar la reescritura.
- Todo se escribe por el filtro `wp_robots` que ya usa `Digitalisimo_Integrations_SEO_Suite::robots()`.

**Pruebas:** ampliar `tests/robots.php`.

### RM7 · Enlaces: recuento, huérfanas, sugerencias y enlaces rotos

**Qué hace Rank Math.**

Gratuito (`modules/links/`):
- tablas `rank_math_internal_links` (`url`, `post_id`, `target_post_id`, `type`) y `rank_math_internal_meta`
  (`internal_link_count`, `external_link_count`, `incoming_link_count`);
- `ContentProcessor` extrae los enlaces del contenido al guardar (`save_post`) y los clasifica como internos o externos
  con `Sitemap\Classifier( home_url() )`;
- normaliza cada enlace (sin barra final, sin fragmento, sin `home_url()`) y resuelve el ID destino, incluidos los
  productos;
- un cron procesa lo pendiente y una columna del listado muestra los recuentos.

Ajustes de salida (`settings/general/links.php`): `strip_category_base`, `nofollow_external_links`,
`nofollow_image_links`, `nofollow_domains`, `nofollow_exclude_domains` y `new_window_external_links`.

Link Genius, PRO (`modules/link-genius/`):
- auditoría de enlaces y rastreo del estado HTTP (`Background/class-link-status-crawler.php`);
- comprobación contra `robots.txt` y sugerencias por similitud de contenido;
- entradas relacionadas (shortcode y bloque);
- mapas keyword → URL con **auto-enlazado** (`Features/KeywordMaps/`);
- edición masiva de enlaces con historial y reversión, y exportación.

**Lo que añadimos.**
- Tablas por sitio `{prefix}digitalisimo_links` y `{prefix}digitalisimo_link_counts`, rellenadas en
  `wp_after_insert_post` y en un cron por lotes para el contenido existente.
- Extracción con `WP_HTML_Tag_Processor`, sin expresiones regulares sobre HTML.
- Columnas de enlaces internos, externos y entrantes en el listado.
- **Informe de páginas huérfanas** (sin enlaces entrantes) en SEO → Auditoría, cruzado con clusters y páginas pilar:
  una página secundaria sin enlace hacia su pilar es un aviso.
- **Sugerencias de enlazado** en el metabox:
  - toman las keywords de `digitalisimo_seo_keywords` y la relación pilar/secundario;
  - sólo se sugieren, **nunca se insertan solas**;
  - el auto-enlazado de Keyword Maps no se porta, porque modificaría contenido.
- Enlaces rotos:
  - comprobación HTTP en cola, con límite de peticiones por lote y `HEAD` antes de `GET`;
  - el resultado se muestra en SEO → Auditoría y nunca corrige nada.
- `nofollow`/`_blank` en enlaces externos: **no se porta como reescritura automática**. Si se pide, sería un filtro de
  salida opt-in por dominio, declarado en `docs/ROBOTS_POLICY.md`.
- `strip_category_base` va con RM12, porque cambia URLs y necesita RM2.

**Pruebas:** `tests/seo-links.php`.

### RM8 · Schema: múltiples por entrada, plantillas, vídeo y producto completo

**Qué hace Rank Math.**

Gratuito (`modules/schema/`):
- cada schema de una entrada es una meta `rank_math_schema_{TipoPrincipal}` con el nodo JSON-LD más una clave
  `metadata`, que guarda:
  - `title`, `type` (`template`/`custom`), `shortcode` (id `s-…`) e `isPrimary`;
  - `reviewLocation` y `reviewLocationShortcode`;
  - `unpublish` (sólo JobPosting);
- los valores admiten variables (`%seo_title%`, `%keywords%`, `%name%`…);
- schema por defecto por tipo de contenido (`pt_{tipo}_default_rich_snippet`, `_default_article_type`,
  `_default_snippet_name`, `_default_snippet_desc`);
- snippets: Article, Author, Breadcrumbs, PrimaryImage, Product (WooCommerce y EDD), ProductsPage, Publisher, WebPage
  y WebSite;
- shortcode `[rank_math_rich_snippet id="s-…"]`;
- bloques de FAQ, HowTo y tabla de contenidos (`toc_block_*`).

PRO (`modules/schema/`):
- plantillas como tipo de contenido `rank_math_schema`, con **condiciones de visualización**
  (`class-display-conditions.php`):
  - grupos `general`, `singular` y `archive`;
  - inclusión y exclusión por ID, término, tipo y búsquedas;
- importar schema desde una URL (`class-parser.php`);
- detección de vídeos en el contenido para generar VideoObject: parsers de YouTube, Vimeo, Dailymotion, TED,
  VideoPress y WordPress (`video/`), con proceso por lotes `rank_math_video_posts`;
- tipos con shortcode: ClaimReview, Dataset, FAQPage, HowTo, JobPosting, Movie, PodcastEpisode, Product y Recipe;
- WooCommerce PRO:
  - GTIN en `_rank_math_gtin_code` (y `_rank_math_gtin_code_variable{n}` por variación), con `gtin` (tipo),
    `show_gtin` y `gtin_label`;
  - marca en `product_brand` o `custom_product_brand`;
  - `noindex_hidden_products`.

**Lo que tenemos.** `Schema_Graph::MAIN_TYPES` ya admite Article, Product, Service, Event, Recipe, Course, JobPosting,
SoftwareApplication, VideoObject, Book y Movie, con `digitalisimo_native_schema_type` y `_schema_custom` por entrada.
Product sólo publica `name`, `description`, `url`, `image`, `sku` y una `Offer`.

**Lo que añadimos.**
- **Producto completo:** `gtin`/`gtin8`/`gtin12`/`gtin13`/`gtin14`/`isbn`/`mpn` (desde una meta propia y desde los
  campos GTIN nativos de WooCommerce cuando existan), `brand` (taxonomía de marcas de WooCommerce o un atributo
  elegido), `aggregateRating` y `review` **sólo con reseñas reales visibles**, `offers` por variación como
  `AggregateOffer`, y `availability`, `priceValidUntil` e `itemCondition`.
- **Schema por defecto por tipo de contenido** (la entidad principal que se aplica si la entrada no elige otra).
- **Plantillas con condiciones**, como opción del sitio con las mismas condiciones que fallan cerradas de F7a. Nunca
  añaden un segundo bloque: aportan la entidad principal o sus propiedades al `@graph`.
- **VideoObject detectado:** detectar `iframe`/`oembed` de YouTube y Vimeo en el contenido y resolver duración y
  miniatura **en cola** por oEmbed, guardándolo en una meta. También alimenta el sitemap de vídeo (RM4).
- **Importar `rank_math_schema_*`** (RM1):
  - si el `@type` principal existe en el vocabulario y está en `MAIN_TYPES`, se traduce a nuestro tipo y sus
    propiedades al JSON personalizado validado;
  - si no, queda en el informe;
  - los FAQ sólo se importan si sus preguntas están visibles en el contenido (regla de unificación).
- Del PRO, **no** se porta: importar schema desde URLs de terceros ni los shortcodes que imprimen schema
  independiente.

**Pruebas:** ampliar `tests/schema.php` y `tests/schema-detection.php`.

### RM9 · Análisis de contenido ampliado

**Qué hace Rank Math.** Pruebas en el editor (nombres internos en sus `assets`):

| Grupo | Pruebas |
|---|---|
| Keyword | `keywordInTitle`, `keywordInMetaDescription`, `keywordInPermalink`, `keywordInContent`, `keywordInSubheadings`, `keywordInImageAlt`, `keywordDensity`, `keywordNotUsed` (no usada en otra entrada) |
| Longitud | `lengthContent`, `lengthPermalink` |
| Enlaces | `linksHasInternal`, `linksHasExternals`, `linksNotAllExternals` |
| Título | `titleStartWithKeyword`, `titleSentiment`, `titleHasPowerWords`, `titleHasNumber` |
| Contenido | `contentHasTOC`, `contentHasShortParagraphs`, `contentHasAssets` |

La ability `rank-math/analyze-post-content` devuelve estos datos a un agente.

**Lo que tenemos.** `analysis_box()` (`class-seo.php:58`) con 6 pruebas: título SEO, meta descripción, slug, primer
10 %, 300 palabras y H2/H3.

**Lo que añadimos.**
- `keywordInImageAlt`, densidad (informativa, con rango y no como objetivo), keyword no usada como principal en otra
  entrada (cruza `digitalisimo_seo_keywords`, que ya tiene inventario), longitud del slug, enlaces internos y
  externos (con RM7), párrafos cortos, imágenes o vídeo y tabla de contenidos.
- **Sin** «power words» ni «sentimiento»: son heurísticas en inglés sin base verificable.
- Igual que hoy, sólo informa. El cálculo se mueve a una clase propia reutilizable por la ability de RM15 y por la
  auditoría.

**Pruebas:** `tests/seo-content-analysis.php`.

### RM10 · Variables de plantilla

**Qué hace Rank Math** (`replace-variables/` y la clase `class-register-vars.php` del PRO). Variables: `sep`,
`sitename`, `sitedesc`, `title`, `parent_title`, `excerpt`, `excerpt_only`, `seo_title`, `seo_description`,
`focuskw`, `keywords`, `url`, `id`, `date`, `modified`, `currentdate`, `currentday`, `currentmonth`, `currentyear`,
`currenttime`, `page`, `pagenumber`, `pagetotal`, `category`, `categories`, `tag`, `tags`, `term`,
`term_description`, `customterm`, `customterm_desc`, `customfield`, `primary_taxonomy_terms`, `pt_single`,
`pt_plural`, `name`, `userid`, `user_description`, `post_author`, `post_thumbnail`, `filename`, `count`,
`search_query`, `org_name`, `org_logo` y `org_url`. Varias aceptan argumentos (`%date(Y)%`, `%customfield(clave)%`,
`%count(title)%`).

**Lo que tenemos.** `%title%`, `%sep%`, `%sitename%`, `%term%`, `%excerpt%`, `%description%` y `%page%`.

**Lo que añadimos.**
- `%sitedesc%`, `%parent_title%`, `%focuskw%`, `%date%`/`%modified%` (con formato), `%currentyear%`,
  `%currentmonth%`, `%category%`/`%primary_category%`, `%tag%`, `%pt_single%`/`%pt_plural%`, `%name%` (autor),
  `%search_query%` y `%customfield(clave)%` (sólo metas públicas, sin prefijo `_`).
- Plantillas por tipo de contenido y por taxonomía (`seo_title_pt_{tipo}`…), declaradas con paridad, para poder
  importar `pt_{tipo}_*` y `tax_{taxonomía}_*`.
- Una variable desconocida nunca se imprime literal: se elimina y la auditoría la señala.
- El importador traduce los nombres equivalentes y deja en el informe los que no existen.

**Pruebas:** ampliar `tests/seo-excerpt.php` o crear `tests/seo-variables.php`.

### RM11 · Imágenes SEO

**Qué hace Rank Math.** Gratuito (`modules/image-seo/options.php`): `add_img_alt` + `img_alt_format` (por defecto
`%filename%`) y `add_img_title` + `img_title_format`, aplicados al imprimir. PRO (`class-image-seo-pro.php`): formatos
de `caption` y `description`, cambio de mayúsculas, `image_replacements` (buscar y reemplazar en atributos) y
`add_avatar_alt`.

**Lo que tenemos.** `Digitalisimo_Integrations_Performance_Image_Alt` y `seo_alt_optimize`/`seo_alt_keyword_mode`.

**Lo que añadimos.** Sólo `alt` en avatares (`get_avatar`), que es accesibilidad sin inventar contenido. El resto
choca con las reglas:
- generar `alt` desde el nombre de archivo produce textos pobres;
- `title` automático no aporta SEO;
- buscar y reemplazar modifica lo que el autor escribió.

Se documenta como **descartado** salvo que el propietario pida lo contrario.

### RM12 · WooCommerce

**Qué hace Rank Math** (`modules/woocommerce/views/options-general.php` y PRO): `wc_remove_product_base`,
`wc_remove_category_base`, `wc_remove_category_parent_slugs`, `wc_remove_generator`, `remove_shop_snippet_data`,
`product_brand`, GTIN y `noindex_hidden_products`.

**Lo que añadimos.**
- `noindex` en productos con visibilidad «oculto» (con paridad).
- Quitar la meta `generator` de WooCommerce.
- GTIN y marca en el schema (RM8).
- Quitar `/product/` y `/product-category/` (y `strip_category_base` de RM7) **sólo si RM2 ya está publicado**:
  - al activarlo se crean las redirecciones 301 de las URLs viejas;
  - al desactivarlo, las inversas;
  - se comprueban colisiones de slug con páginas antes de activarlo y, si las hay, no se activa (falla cerrado).

**Pruebas:** `tests/seo-woocommerce.php`, con la copia `reference/woocommerce.11.2.1.zip`.

### RM13 · Migas y categoría principal

**Qué hace Rank Math** (`settings/general/breadcrumbs.php`): `breadcrumbs_separator`, `breadcrumbs_home`,
`breadcrumbs_home_label`, `breadcrumbs_home_link`, `breadcrumbs_prefix`, `breadcrumbs_archive_format`,
`breadcrumbs_search_format`, `breadcrumbs_404_label`, `breadcrumbs_remove_post_title`,
`breadcrumbs_ancestor_categories`, `breadcrumbs_hide_taxonomy_name` y `breadcrumbs_blog_page`. Además, título de miga
por entrada (`rank_math_breadcrumb_title`), categoría principal por taxonomía (`rank_math_primary_{taxonomía}`, con su
selector en edición rápida del PRO, `admin/class-quick-edit.php`) y un widget de migas para Elementor.

**Lo que tenemos.** `breadcrumbs_for()` y `[digitalisimo_breadcrumbs]` en `class-seo-suite.php`, y el widget
Breadcrumbs de DIGITALÍSIMO Elements.

**Lo que añadimos.**
- Ajustes con paridad: separador, etiqueta y enlace de inicio, prefijo, formatos de archivo, búsqueda y 404, ocultar
  el título actual, categorías antecesoras y página del blog.
- `digitalisimo_seo_breadcrumb_title` por entrada y término.
- **Categoría principal** por taxonomía jerárquica (`digitalisimo_seo_primary_{taxonomía}`), con selector en el
  metabox. La usan:
  - las migas;
  - `%primary_category%`;
  - el `articleSection` del schema;
  - opcionalmente, `%category%` en el enlace permanente (filtro `post_link_category`).
- El widget de Elements lee la misma fuente: un solo motor de migas.

**Pruebas:** `tests/seo-breadcrumbs.php`.

### RM14 · Edición masiva, edición rápida y CSV

**Qué hace Rank Math (PRO).**
- `admin/class-bulk-actions.php` y `class-quick-edit.php`: título, descripción, keyword, robots y categoría principal
  desde el listado (opción `pt_{tipo}_bulk_editing`).
- `admin/csv-import-export/`: exporta e importa todas las metas SEO de entradas, términos y usuarios por lotes en
  segundo plano.
- `admin/class-post-filters.php`: filtros del listado por puntuación, keyword, schema y noindex.

**Lo que tenemos.** `bulk_edit_custom_box` para keywords y columnas de SEO en entradas y páginas.

**Lo que añadimos.**
- Edición rápida y masiva de título, descripción, keywords, noindex/nofollow y categoría principal, para todos los
  tipos de `keyword_post_types`.
- Filtros del listado: sin descripción, noindex, sin keyword y huérfanas (con RM7).
- **CSV** de metas SEO (exportar e importar) por lotes en cola, con `current_user_can( 'edit_post', $id )` por fila.
  Una fila nunca escribe en otro sitio.

**Pruebas:** `tests/seo-bulk-csv.php`.

### RM15 · Abilities API para agentes (SEO AI)

**Qué hace Rank Math** (`abilities/`, registradas con `wp_register_ability` cuando existe la API de WordPress 6.9).

| Grupo | Abilities |
|---|---|
| Lectura | `get-post-seo-meta`, `get-post-schema`, `analyze-post-content`, `get-seo-scores`, `get-link-report`, `get-post-links`, `get-404-logs`, `get-redirections`, `get-sitemap-status`, `get-robots-txt`, `get-llms-txt`, `get-settings`, `get-system-status`, `audit-site-seo`, `get-top-keywords` |
| Escritura | `fix-site-seo`, `set-breadcrumb-settings`, `set-global-seo-settings`, `set-homepage-seo`, `set-link-settings`, `set-module-status`, `set-plugin-preferences`, `set-post-type-seo-settings`, `set-sitemap-settings`, `set-website-identity` |
| De su SaaS | `get-ai-visibility-*`, `create-ai-visibility-brand` |

Cada ability declara `input_schema`/`output_schema`, `permission_callback` (`edit_post` sobre el ID) y
`annotations` (`readonly`, `destructive`, `idempotent`).

**Lo que añadimos.**
- Categoría `digitalisimo-seo` y **sólo abilities de lectura** en una primera fase:
  - metadatos SEO efectivos de una entrada (por `SEO_Resolver`);
  - `@graph` de una URL;
  - análisis de contenido (RM9);
  - enlaces e informe de huérfanas (RM7);
  - redirecciones y 404 (RM2/RM3);
  - estado de sitemap, `robots.txt` y `llms.txt`;
  - resultado de la auditoría.
- Todas con `readonly: true`, permisos por objeto y sin devolver secretos.
- Las de escritura quedan fuera hasta que se decida. Si llegan, sólo crean borradores o propuestas, igual que el MCP
  de IA Tools (`digitalisimo-mcp/v1`), que podría exponerlas.
- Sin la API (WordPress < 6.9), no se registra nada.

**Pruebas:** `tests/seo-abilities.php`.

### RM16 · Funciones menores

| Función de Rank Math | Origen | Decisión |
|---|---|---|
| Texto antes/después del contenido en el RSS (`rss_before_content`, `rss_after_content`, con variables) | `settings/general/others.php` | Añadir con paridad; útil contra el scraping |
| Ajustes de portada (`homepage_title`, `homepage_description`, `homepage_robots`, `homepage_facebook_*`) | `settings/titles/homepage.php` | Añadir sólo para portada de últimas entradas; con página estática ya manda el metabox |
| Archivo de autor (`url_author_base`, título y descripción) | `settings/titles/author.php` | Título y descripción con RM10; la base de URL no |
| `capitalize_titles` | `settings/titles/global.php` | Descartado: cambia el texto del autor |
| `facebook_app_id`, `facebook_admin_id`, `facebook_author_urls` | `settings/titles/social.php` | Sólo `fb:app_id` si se pide |
| Overlay de miniatura social (icono de reproducción) | `class-thumbnail-overlay.php` y PRO | Baja; requiere GD/Imagick y copia en `uploads` |
| Soporte headless (`headless_support`: endpoint REST con el `<head>`) | `settings/general/others.php` | Baja; sólo si hay un sitio headless |
| Múltiples ubicaciones (tipo `rank_math_locations`, KML, mapa, buscador de tiendas, horario en shortcode) | PRO `modules/local-seo/` | Evaluar sobre nuestro catálogo de ubicaciones; mapas sólo sin peticiones a terceros por defecto |
| Podcast (feed y PodcastEpisode) | PRO `modules/podcast/` | Sólo si un sitio lo necesita |
| ACF (análisis e imágenes en sitemap) | `modules/acf/` y PRO | Baja |
| bbPress, BuddyPress, Web Stories | `modules/` | Fuera mientras ningún sitio los use |
| Gestor de roles | `modules/role-manager/` | Fuera: seguimos con `manage_options` y `edit_post` |
| Puntuación SEO en el front | `class-frontend-seo-score.php` | Descartado |

## Lo que no se incorpora

| Módulo | Motivo |
|---|---|
| Content AI (`modules/content-ai/`) | Servicio de pago de Rank Math. Las funciones IA son de DIGITALÍSIMO IA Tools |
| AI Visibility (`modules/ai-visibility/`, con `Api/class-trial-controller.php` y `class-checkout-controller.php`) | SaaS con prueba y cobro |
| Analytics, Rank Tracker y competidores (`modules/analytics/`, PRO `seo-analysis/class-competitor-analysis.php`) | Dependen de su servidor y su OAuth. Search Console propia, con OAuth del sitio, sería un objetivo aparte |
| Trends (`admin/class-trends-tool.php`) | Servicio externo |
| Marketplace, licencias, actualizador, `class-tracking.php`, `class-update-email.php` | Propios del proveedor |
| Control de versiones y rollback (`modules/version-control/`) | Ya existe con las Releases y `rollback/` |
| Asistente de configuración | Nuestras pantallas no lo necesitan |
| Editor de `.htaccess` (`settings/general/htaccess.php`) | Riesgo alto y fuera de alcance |
| Auto-enlazado por keywords (Link Genius) | Modifica contenido; queda como sugerencia (RM7) |

## Orden de trabajo

| Etapa | Depende de | Motivo del orden |
|---|---|---|
| RM1 Importador | — | Evita pérdidas; base de las demás importaciones |
| RM2 Redirecciones | — | Sin ella, retirar Rank Math rompe URLs |
| RM4 (imágenes del sitemap) | — | Corrige una opción que hoy no hace nada |
| RM5 Verificación | — | Pequeña; la importa RM1 |
| RM6 Robots avanzados | — | La importa RM1 |
| RM3 Monitor 404 | RM2 | Convierte 404 en redirecciones |
| RM13 Migas y categoría principal | — | La importa RM1; la usan RM8 y RM10 |
| RM10 Variables | **Publicado** · SEO 1.0.234 · `v2026.10.27.420` (25 variables y plantillas por tipo de contenido) y SEO 1.0.244 · `v2026.10.28.430` (plantillas por taxonomía en `seo_tax_templates` y por archivo de tipo en `seo_type_templates[tipo][archive_*]`, importadas de `tax_{taxonomía}_*` y `pt_{tipo}_archive_*`; saneado que conserva `%description%`, `%date%` y `%category%`). |
| RM8 Schema | RM10 | |
| RM4 (noticias, vídeo, autores, HTML) | RM8 | El de vídeo sale del VideoObject |
| RM7 Enlaces | — | |
| RM9 Análisis | RM7 | |
| RM14 Masiva y CSV | RM13 | |
| RM12 WooCommerce | Primera parte **publicada** · SEO 1.0.240 · `v2026.10.27.426`: noindex y sitemap de productos ocultos y generator. URL sin base **publicadas** · SEO 1.0.248: `seo_strip_product_base`, `seo_strip_product_cat_base` y `seo_strip_category_base` (opt-in, con paridad), con colisiones que fallan cerradas (la página o entrada gana y el enlace conserva su base), resolución sólo cuando la ruta no es de nadie y 301 de la URL anterior por la redirección canónica; el importador las enciende cuando Rank Math quitaba la base (URL idénticas, sin 301) y las 301 de importaciones anteriores se desactivan mientras la base esté quitada. «Quitar las categorías superiores» no se reproduce: se informa. |
| RM15 Abilities | RM7, RM9 | |
| RM16 Menores | — | Según demanda |

Cada etapa:
- sube la versión sólo de DIGITALÍSIMO SEO;
- añade sus pruebas a `validate.yml` y `release.yml`;
- añade su fila a `docs/PLUGIN_ROADMAP.md`;
- anota su estado aquí.

Antes de instalar las copias de referencia en un Playground para comparar comportamientos, se anotan en
`reference/OFFICIAL-SOURCES.txt` (la gratuita, con su URL de wordpress.org y la suma ya comprobada).

## Estado

Inventario cerrado el 2026-10-26 sobre Rank Math 1.0.279 y PRO 3.0.123.

| Etapa | Estado |
|---|---|
| RM1 Importador | **Publicado** · SEO 1.0.233 · `v2026.10.27.419`. Convivencia en SEO 1.0.247 · `v2026.10.28.433`: con Rank Math (o Yoast, All in One SEO, SEOPress, The SEO Framework) activo, DIGITALÍSIMO SEO no publica ninguna señal y avisa en SEO y en Plugins; se importa con el otro activo y, al desactivarlo, toma el relevo. Pestaña «Importar» del sitio y botón de red que encola cada sitio. Usuarios: sólo se cuentan (sin destino). |
| RM2 Redirecciones | **Publicado** · SEO 1.0.233 · `v2026.10.27.419` (primera parte) y SEO 1.0.243 · `v2026.10.28.429` (segunda parte: categoría por regla con filtro, programación desde/hasta con WP-cron y CSV de ida y vuelta compatible con el de Rank Math; el importador trae sus categorías y sus fechas pendientes de Action Scheduler). |
| RM3 Monitor 404 | **Publicado** · SEO 1.0.233 · `v2026.10.27.419`. Exclusiones por fragmento de URL (sin comparaciones). |
| RM4 Sitemaps | Imágenes **publicadas** · SEO 1.0.233 · `v2026.10.27.419`. Noticias y mapa HTML **publicados** · SEO 1.0.236 · `v2026.10.27.422`. Autores y términos: **no se añaden**, porque el sitemap propio sólo publica documentos (decisión previa que `tests/sitemap.php` exige; por lo mismo, `seo_sitemap_taxonomies` no tiene efecto). Pendiente: vídeo, con el VideoObject de RM8. Vídeo **publicado** · SEO 1.0.246 · `v2026.10.28.432`: `video-sitemap.xml` opt-in (`seo_sitemap_video_enabled`, con paridad) desde la meta `digitalisimo_seo_video` de cada contenido. |
| RM5 Verificación | **Publicado** · SEO 1.0.233 · `v2026.10.27.419`. |
| RM6 Robots avanzados | **Publicado** · SEO 1.0.233 · `v2026.10.27.419` para contenidos y sitio; por término (nofollow, noarchive, noimageindex, nosnippet, `max-snippet` y `max-video-preview`, también importados) en SEO 1.0.242 · `v2026.10.28.428`. |
| RM13 Migas y categoría principal | **Publicado** · SEO 1.0.234 · `v2026.10.27.420`. Pendientes: título de miga en términos y que el widget de migas de DIGITALÍSIMO Elements lea la misma ruta. |
| RM10 Variables | **Publicado** · SEO 1.0.234 · `v2026.10.27.420` (25 variables y plantillas por tipo de contenido). Pendientes: plantillas por taxonomía y por archivo de tipo. |
| RM8 Schema | Primera parte **publicada** · SEO 1.0.235 · `v2026.10.27.421`: marca del producto, entidad por defecto por tipo, `articleSection` y `keywords`, GTIN importado al campo de WooCommerce. WooCommerce 11.2.1 ya publica GTIN, AggregateOffer, `priceValidUntil`, valoraciones y reseñas, y la unificación las fusiona: no se duplican. VideoObject de Vimeo **publicado** · SEO 1.0.246 · `v2026.10.28.432`: oEmbed resuelto en cola (fecha, duración, descripción y miniatura), `embedUrl` y retirada si el reproductor no aparece en el HTML; el vídeo de la biblioteca añade descripción y duración reales. YouTube no se marca: su oEmbed no da la fecha de publicación y no se inventa (sólo se cuenta). Pendiente: plantillas con condiciones. |
| RM7 Enlaces | **Publicado** · SEO 1.0.237 · `v2026.10.27.423`: recuentos, columna, sugerencias en el editor, huérfanas y secundarios sin pilar en Auditoría. Enlaces rotos en SEO 1.0.245 · `v2026.10.28.431`: opt-in `seo_links_check` con paridad, cola en WP-cron (10 URL por lote, `HEAD` y `GET` limitado con `wp_safe_remote_*`, nueva comprobación semanal) y lista en Auditoría con «No existe», «Error» y «No verificable»; nunca corrige nada. |
| RM9 Análisis de contenido | **Publicado** · SEO 1.0.238 · `v2026.10.27.424`: caja «Análisis SEO» en el editor, puntuación en la columna «Análisis» y el mismo título y descripción que publica el head. Pendiente: exponerlo como ability (RM15). |
| RM14 Edición masiva y CSV | **Publicado** · SEO 1.0.239 · `v2026.10.27.425`: edición rápida y masiva, filtro SEO en los listados y CSV de ida y vuelta. La categoría principal se edita en el editor (SEO → Avanzado), no en la edición rápida. |
| RM12 WooCommerce | Primera parte **publicada** · SEO 1.0.240 · `v2026.10.27.426`: noindex y sitemap de productos ocultos, generator, y 301 de las URL sin base cuando Rank Math las quitaba. **Pendiente de decisión del propietario:** quitar `/product/` y `/product-category/` en DIGITALÍSIMO SEO, porque cambia las URL de todos los productos. |
| RM15 Abilities | **Publicado** · SEO 1.0.241 · `v2026.10.27.427`: ocho abilities de sólo lectura en la categoría `digitalisimo-seo`. Las de escritura no se añaden sin una decisión del propietario. |
| RM11 Imágenes | **Publicado** · SEO 1.0.242 · `v2026.10.28.428`: `alt` de los avatares con el nombre público, opt-in (`seo_avatar_alt`) e importado de `add_avatar_alt`; nunca el correo y nunca sustituye un alt dado. El resto se descartó. |
| RM16 Menores | Texto antes y después del contenido en el RSS **publicado** · SEO 1.0.242 · `v2026.10.28.428` (`seo_rss_before`, `seo_rss_after`, HTML de entradas, variables de los títulos y `%post_link%`, `%site_link%`, `%sitedesc_link%`, `%author_link%`; importa los de Rank Math). El resto, según demanda. |

Pruebas de lo publicado: `tests/seo-redirections.php`, `tests/seo-rankmath-import.php`, `tests/seo-sitemap-images.php` `tests/seo-breadcrumbs.php`, `tests/seo-links.php`, `tests/seo-content-analysis.php`, `tests/seo-bulk.php`, `tests/seo-abilities.php`, `tests/seo-feed.php`, `tests/seo-template-sanitize.php`, `tests/seo-video.php`, `tests/seo-filter-hooks.php`, `tests/seo-url-bases.php` y `tests/playground/seo-rankmath.sh` (399 comprobaciones en red y en un sitio individual con WooCommerce 11.2.1).
