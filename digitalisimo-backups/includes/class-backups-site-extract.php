<?php
defined( 'ABSPATH' ) || exit;

/**
 * «Extraer como WordPress individual»: un sitio de una red sale como paquete restaurable en una instalación individual.
 *
 * Es un trabajo del mismo motor que los respaldos completos (cola, fases, reanudación, verificación, cifrado y firma) con
 * el alcance explícito `extracted-site` en el manifiesto v3. El paquete es un WordPress individual cuyo prefijo de tablas
 * es el del sitio en la red (`wp_N_`): sus tablas tal cual, más `wp_N_users` y `wp_N_usermeta` sólo con los usuarios del
 * sitio. Ese es el formato de una instalación individual con ese prefijo, así que sus metas (`wp_N_capabilities`…) y sus
 * roles (`wp_N_user_roles`) ya son los suyos. Al restaurar, el motor de reubicación (E1) pasa el prefijo, las URL y las
 * rutas a las del destino: este paquete no reescribe ninguna URL, ruta ni prefijo.
 *
 * Qué decide esta clase (y nada más):
 * - qué tablas, usuarios y metas viajan (`Digitalisimo_Backups_Site_Extract_Export`, que el exportador del motor llama
 *   fila a fila dentro de su instantánea);
 * - qué opciones del sitio cambian para que se comporte igual fuera de la red: plugins activos (los del sitio y los
 *   activados en la red), los ajustes de red de los plugins DIGITALÍSIMO que el sitio hereda (`materialize()`), y las
 *   rutas propias de los medios en la red (`upload_path`), que quedan en las de un sitio individual;
 * - qué archivos viajan (`file_map()`): núcleo, mu-plugins, idiomas, los plugins activos, el tema y su padre, y los
 *   medios del sitio (`uploads/sites/N/` pasa a `uploads/`);
 * - qué se conserva en el destino al restaurar (la configuración de Backups del servidor de destino).
 */
final class Digitalisimo_Backups_Site_Extract {
	const SCOPE = 'extracted-site';
	/** Acción admin-post de la pantalla de red. */
	const ACTION = 'digitalisimo_backups_extract';
	const POLICY = 'extract';
	const FILE_PREFIX = 'extracted-site-';
	/** Qué hacer con los superadministradores que no son miembros del sitio (ver `users()`). */
	const SUPER_ADMINS = array( 'none', 'managing', 'all' );
	/** Tablas de la red que no son de ningún sitio. */
	const GLOBAL_TABLES = array( 'users', 'usermeta', 'blogs', 'blogmeta', 'site', 'sitemeta', 'signups', 'registration_log', 'sitecategories', 'blog_versions' );
	/** Tablas de núcleo de cada sitio. */
	const CORE_SITE_TABLES = array( 'options', 'posts', 'postmeta', 'comments', 'commentmeta', 'terms', 'termmeta', 'term_taxonomy', 'term_relationships', 'links' );
	/** Metas de usuario que WordPress guarda por sitio con el prefijo del sitio (`wp_` es el principal). */
	const SITE_USER_META = array( 'capabilities', 'user_level', 'user-settings', 'user-settings-time', 'dashboard_quick_press_last_post_id', 'persisted_preferences', 'media_library_mode' );
	/** Metas globales que sólo tienen sentido en una red (y nombran su dominio). */
	const NETWORK_USER_META = array( 'primary_blog', 'source_domain' );
	/** `sitemeta` de WordPress: ni se materializa ni se lista como de otro plugin. */
	const CORE_SITEMETA = array( 'site_name', 'admin_email', 'admin_user_id', 'registration', 'upload_filetypes', 'blog_upload_space', 'fileupload_maxk', 'site_admins', 'allowedthemes', 'illegal_names', 'wpmu_upgrade_site', 'welcome_email', 'welcome_user_email', 'first_post', 'first_page', 'first_comment', 'first_comment_url', 'first_comment_author', 'siteurl', 'add_new_users', 'upload_space_check_disabled', 'subdomain_install', 'ms_files_rewriting', 'user_count', 'blog_count', 'initial_db_version', 'active_sitewide_plugins', 'WPLANG', 'registrationnotification', 'banned_email_domains', 'limited_email_domains', 'new_admin_email', 'global_terms_enabled', 'menu_items', 'recently_activated', 'can_compress_scripts', 'auto_update_plugins', 'auto_update_themes', 'auto_core_update_notified', 'wp_force_deactivated_plugins', 'site_meta_supported', 'db_version', 'dismissed_update_core', 'update_core', 'update_plugins', 'update_themes', 'user_count_ts', 'adminhash', 'customize_stashed_theme_mods', 'ms_files_rewriting', 'super_admins', 'recovery_keys', 'recovery_mode_email_last_sent', 'fresh_site', 'theme_switch_menu_locations', 'main_site', 'auth_key', 'auth_salt', 'secure_auth_key', 'secure_auth_salt', 'logged_in_key', 'logged_in_salt', 'nonce_key', 'nonce_salt' );
	/** Secretos de los ajustes de red de los plugins DIGITALÍSIMO: viajan sólo si se pide. */
	const SEO_OPTION = 'digitalisimo_integrations_options';
	/**
	 * SEO (`digitalisimo_integrations_options`), por lista blanca: SEO_COPY son las claves que se copian de la red cuando el
	 * sitio las hereda; SEO_ACCOUNT, las claves y cuentas de la red (claves de API, proveedores IA, Namecheap, IPinfo,
	 * IndexNow, verificaciones de buscadores y medición), que sólo viajan con `network_secrets`. Una clave que no está en
	 * ninguna (un ajuste nuevo de SEO) no se copia y el informe la nombra: un secreto nuevo nunca viaja sin pedirlo.
	 */
	const SEO_COPY = array(
		'enable_seo', 'enable_woocommerce', 'enable_ipinfo', 'sitemap_enabled', 'sitemap_post_types', 'sitemap_exclude_ids', 'seo_robots_rules', 'keyword_post_types',
		'keyword_max_count', 'keyword_auto_from_title', 'noindex_search', 'noindex_authors', 'noindex_empty_tags', 'noindex_date_archives', 'noindex_attachments', 'noindex_elementor',
		'noindex_woo_pages', 'noindex_page_slugs', 'noindex_page_ids', 'openai_model', 'ai_profile_provider', 'ai_profile_model', 'ai_profile_fallback', 'namecheap_client_ip',
		'domain_products', 'hosting_product_ids', 'hosting_category_ids', 'require_domain_hosting', 'replace_hosting', 'simplify_checkout', 'ipinfo_cache_minutes', 'seo_separator',
		'seo_organization_type', 'seo_organization_name', 'seo_organization_url', 'seo_organization_email', 'seo_logo', 'seo_alt_optimize', 'seo_alt_keyword_mode', 'seo_knowledge_urls',
		'seo_title_post', 'seo_description_post', 'seo_title_page', 'seo_description_page', 'seo_title_archive', 'seo_description_archive', 'seo_title_home', 'seo_description_home',
		'seo_title_author', 'seo_description_author', 'seo_title_taxonomy', 'seo_description_taxonomy', 'seo_schema_enabled', 'seo_schema_unify', 'seo_breadcrumbs', 'seo_open_graph',
		'seo_twitter_card', 'seo_twitter_enabled', 'seo_twitter_user', 'seo_local_enabled', 'seo_local_type', 'seo_local_name', 'seo_local_phone', 'seo_local_address',
		'seo_local_city', 'seo_local_region', 'seo_local_postal', 'seo_local_country', 'seo_local_latitude', 'seo_local_longitude', 'seo_local_hours', 'seo_local_price_range',
		'seo_redirect_attachments', 'seo_mobile_prevent_horizontal_scroll', 'perf_safe_mode', 'perf_gutenberg', 'perf_emojis', 'perf_embeds', 'perf_dashicons', 'perf_font_swap',
		'perf_font_guard_mode', 'perf_font_guard_allowlist', 'perf_font_icon_families', 'perf_css_defer', 'perf_css_defer_exclusions', 'perf_hero_critical', 'perf_css_defer_handles', 'perf_caption_normal',
		'perf_custom_css', 'perf_custom_css_paused', 'perf_preload_mode', 'perf_preload_paths', 'perf_preload_limit', 'perf_tracking_enabled', 'perf_tracking_mode', 'perf_tracking_delay',
		'perf_tracking_fallback', 'perf_img_enabled', 'perf_img_srcset', 'perf_img_sizes', 'perf_img_dimensions', 'perf_img_lazy', 'perf_img_backgrounds', 'perf_img_mode',
		'perf_img_exclusions', 'perf_js_jquery_mode', 'perf_touch_guard', 'perf_touch_selectors', 'seo_taxonomies', 'seo_sitemap_taxonomies', 'seo_sitemap_images', 'seo_redirect_enabled',
		'seo_redirect_slug_watch', 'seo_redirect_default_code', 'seo_redirect_fallback_url', 'seo_404_enabled', 'seo_404_limit', 'seo_404_retention', 'seo_404_exclude', 'seo_404_ignore_query',
		'seo_noindex_paginated', 'seo_noindex_password', 'seo_max_snippet', 'seo_max_video_preview', 'seo_disable_author_archives', 'seo_disable_date_archives', 'seo_breadcrumbs_separator', 'seo_breadcrumbs_show_home',
		'seo_breadcrumbs_home_label', 'seo_breadcrumbs_prefix', 'seo_breadcrumbs_hide_current', 'seo_breadcrumbs_taxonomy', 'seo_breadcrumbs_blog_page', 'seo_breadcrumbs_archive_format', 'seo_breadcrumbs_search_format', 'seo_breadcrumbs_404_label',
		'seo_type_templates', 'seo_tax_templates', 'seo_schema_type_defaults', 'seo_schema_templates', 'seo_product_brand', 'seo_product_brand_name', 'seo_product_condition', 'seo_sitemap_news_enabled',
		'seo_links_counter', 'seo_links_check', 'seo_noindex_hidden_products', 'seo_wc_remove_generator', 'seo_strip_product_base', 'seo_strip_product_cat_base', 'seo_strip_category_base', 'seo_abilities_write',
		'seo_avatar_alt', 'seo_rss_before', 'seo_rss_after', 'seo_sitemap_news_types', 'seo_sitemap_news_name', 'seo_sitemap_video_enabled', 'seo_sitemap_news_exclude_terms', 'seo_ai_enabled',
		'seo_ai_search_default', 'seo_ai_training_default', 'seo_ai_google_extended_policy', 'seo_ai_crawler_rules', 'seo_ai_crawler_preset', 'seo_ai_crawlers', 'seo_ai_llms_enabled', 'seo_ai_llms_mode',
		'seo_ai_llms_manual', 'seo_ai_llms_urls', 'seo_ai_indexnow_enabled', 'seo_ai_indexnow_auto', 'seo_ai_referrals_enabled', 'seo_ai_referral_retention', 'seo_hide_login_slug', 'seo_social_profiles',
		'seo_local_phone_cc', 'seo_local_schedule', 'content_editorial_voice', 'content_editorial_context', 'content_editorial_cta', 'content_editorial_method', 'default_cluster_pillar', 'default_content_location',
	);
	const SEO_ACCOUNT = array(
		'openai_api_key', 'ai_providers', 'namecheap_api_user', 'namecheap_username', 'namecheap_api_key', 'ipinfo_tokens', 'seo_ai_indexnow_key', 'seo_verify_google',
		'seo_verify_bing', 'seo_verify_yandex', 'seo_verify_baidu', 'seo_verify_pinterest', 'seo_verify_norton', 'seo_verify_custom', 'perf_gt_id', 'perf_ga4_id',
		'perf_gtm_id',
	);
	const SEO_SITE_ONLY = array( 'seo_site_name', 'seo_default_image', 'seo_default_image_alt', 'seo_alt_keyword', 'seo_favicon_source' );
	const SEO_MODULE_KEYS = array( 'enable_woocommerce', 'require_domain_hosting', 'replace_hosting', 'simplify_checkout', 'namecheap_api_user', 'namecheap_username', 'namecheap_api_key', 'namecheap_client_ip', 'domain_products', 'hosting_product_ids', 'hosting_category_ids', 'enable_ipinfo', 'ipinfo_tokens', 'ipinfo_cache_minutes' );
	const TOOLS_SITE = 'digitalisimo_tools_site_options';
	const TOOLS_NETWORK = 'digitalisimo_tools_site_defaults';
	const TOOLS_DEFAULTS = array( 'login_slug' => '', 'redirect_attachments' => 1, 'mobile_scroll' => 0 );
	const TOOLS_LEGACY_KEYS = array( 'login_slug' => 'seo_hide_login_slug', 'redirect_attachments' => 'seo_redirect_attachments', 'mobile_scroll' => 'seo_mobile_prevent_horizontal_scroll' );
	const FRONT_SITE = 'digitalisimo_elements_front';
	const FRONT_NETWORK = 'digitalisimo_elements_front_defaults';
	const GOOGLE_KEY = 'digitalisimo_elements_google_places_key';
	const HOSTING = 'digitalisimo_hosting_options';
	/** Hosting e IA Tools, por lista blanca: lo que se copia de la red y lo que es de su cuenta (sólo con `network_secrets`). */
	const HOSTING_COPY = array( 'enabled', 'client_ip', 'domain_products', 'hosting_product_ids', 'hosting_category_ids', 'require_domain_hosting', 'replace_hosting', 'simplify_checkout', 'form_selector', 'domain_field', 'extension_field' );
	const HOSTING_ACCOUNT = array( 'api_user', 'username', 'api_key' );
	const AI_COPY = array( 'chat_enabled', 'chat_profile', 'context_posts', 'knowledge_enabled', 'knowledge_auto_index', 'knowledge_results', 'knowledge_embeddings_enabled', 'knowledge_embeddings_model', 'knowledge_semantic_rerank', 'image_size', 'image_style', 'image_quality', 'mcp_featured_image_enabled', 'log_retention', 'editorial_voice', 'editorial_context', 'editorial_cta', 'editorial_method', 'profiles' );
	/** Archivo principal de cada plugin DIGITALÍSIMO (sin la carpeta, que puede cambiar): sus ajustes sólo si está activo en el sitio. */
	const PLUGIN_FILES = array( 'seo' => 'digitalisimo-integrations.php', 'tools' => 'digitalisimo-tools.php', 'elements' => 'pro-elements.php', 'hosting' => 'digitalisimo-hosting.php', 'ai' => 'digitalisimo-chatbot.php' );
	const AI = 'digitalisimo_ai_options';
	/** Opciones del sitio que el exportador debe leer antes de recorrer la tabla (herencia y ajustes que se materializan). */
	const SITE_READS = array( 'active_plugins', 'stylesheet', 'template', 'admin_email', 'home', 'siteurl', 'upload_path', 'upload_url_path', self::SEO_OPTION, 'digitalisimo_seo_network_inherit', 'digitalisimo_module_network_inherit', self::TOOLS_SITE, self::FRONT_SITE, self::GOOGLE_KEY, self::HOSTING, self::AI );
	/** `sitemeta` que se leen dentro de la instantánea. */
	const NETWORK_READS = array( 'site_admins', 'active_sitewide_plugins', 'ms_files_rewriting', 'subdomain_install', 'blog_count', self::SEO_OPTION, self::TOOLS_NETWORK, self::FRONT_NETWORK, self::GOOGLE_KEY, self::HOSTING, self::AI );
	/** Topes de las listas del informe dentro del manifiesto. */
	const REPORT_LIMIT = 200;
	/** Con más sitios, las tablas que no viajan no se listan (sería recorrer todas las tablas de la red sólo para el informe). */
	const LIST_TABLES_UP_TO = 500;

	/* ------------------------------------------------------------------ Petición */

	/** Opciones de la extracción con sus valores por defecto; WP_Error si alguna no es válida. */
	public static function options( array $args ) {
		$mode = isset( $args['super_admins'] ) && '' !== (string) $args['super_admins'] ? sanitize_key( (string) $args['super_admins'] ) : 'none';
		if ( ! in_array( $mode, self::SUPER_ADMINS, true ) ) return new WP_Error( 'digitalisimo_backups_super_admins', 'Elige qué hacer con los superadministradores que no son miembros del sitio: none (ninguno, por defecto), managing (sólo quien tiene el correo de administración del sitio) o all.', array( 'status' => 400 ) );
		return array( 'super_admins' => $mode, 'network_secrets' => ! empty( $args['network_secrets'] ) );
	}
	/**
	 * Sitio de la red que se puede extraer, o el motivo: no existe, o su base de datos o sus medios no tienen la forma de
	 * un sitio de una red. Devuelve array( id, domain, path, network_id, url, label ).
	 */
	public static function site( $site_id ) {
		$site_id = (int) $site_id;
		if ( ! is_multisite() ) return new WP_Error( 'digitalisimo_backups_scope', 'Esta instalación no es una red: no hay sitios que extraer.', array( 'status' => 400 ) );
		$site = $site_id > 0 && function_exists( 'get_site' ) ? get_site( $site_id ) : null;
		if ( ! $site ) return new WP_Error( 'digitalisimo_backups_site_missing', 'El sitio ' . $site_id . ' no existe en esta red.', array( 'status' => 404 ) );
		$network = (int) ( $site->network_id ?? ( $site->site_id ?? 1 ) );
		if ( function_exists( 'get_main_network_id' ) && $network !== (int) get_main_network_id() ) return new WP_Error( 'digitalisimo_backups_site_network', 'El sitio ' . $site_id . ' pertenece a otra red de esta instalación; se extrae desde la red principal sólo un sitio suyo.', array( 'status' => 400 ) );
		$url = untrailingslashit( (string) get_home_url( $site_id ) );
		return array( 'id' => $site_id, 'domain' => (string) $site->domain, 'path' => (string) $site->path, 'network_id' => $network, 'url' => $url, 'label' => self::label( (string) $site->domain, (string) $site->path ) );
	}
	public static function label( $domain, $path ) { return rtrim( $domain . $path, '/' ); }
	/** Nombre del paquete: nunca coincide con la rotación de los respaldos completos (`digitalisimo-*.zip`). */
	public static function file_name( array $site, $encrypt = false ) {
		$slug = substr( sanitize_title( str_replace( array( '.', '/' ), '-', $site['label'] ) ), 0, 80 );
		return self::FILE_PREFIX . ( '' !== $slug ? $slug : 'sitio-' . (int) $site['id'] ) . '-' . gmdate( 'Ymd-His' ) . '-' . substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 8 ) . ( $encrypt ? '.dgbk' : '.zip' );
	}
	/**
	 * Especificación inmutable del trabajo: la de un respaldo (Digitalisimo_Backups_Jobs::spec()) con el alcance
	 * `extracted-site`, el sitio y su propia seguridad ($own, de own_key()): un sitio extraído nunca se cifra ni se firma con
	 * la clave de la red, que abre todos sus respaldos; sólo con la frase de esa extracción, o sin cifrar y sin firma. El
	 * motor de la cola no cambia: las fases leen el alcance.
	 */
	public static function job_spec( array $spec, array $site, array $options, array $own = array( 'encrypt' => false, 'key_id' => '' ) ) {
		$spec['scope'] = self::SCOPE;
		$spec['inline'] = false;
		$spec['security'] = array( 'encrypt' => ! empty( $own['encrypt'] ), 'key_id' => (string) ( $own['key_id'] ?? '' ), 'own' => true );
		$spec['file'] = preg_replace( '/\.(?:zip|dgbk)$/', '', (string) $spec['file'] ) . ( ! empty( $own['encrypt'] ) ? '.dgbk' : '.zip' );
		$spec['extract'] = array( 'site_id' => (int) $site['id'], 'label' => (string) $site['label'], 'super_admins' => (string) $options['super_admins'], 'network_secrets' => (bool) $options['network_secrets'] );
		return $spec;
	}

	/* ------------------------------------------------------------------ Clave propia de la extracción */

	/**
	 * Clave del paquete: la frase de esta extracción (o la del destino que elija el superadministrador), derivada como la
	 * de «Seguridad» (PBKDF2-SHA256 con su propio salt; el descriptor viaja en el paquete, así que en el destino basta la
	 * frase). Nunca la de la red. Mientras dura el trabajo, la clave vive envuelta con AUTH_KEY y SECURE_AUTH_KEY en el
	 * almacén privado (`.extract-key-<huella>.json`, 0600), nunca en la especificación ni en claro; se borra al
	 * registrar el paquete. Sin frase: el paquete sale sin cifrar ni firmar, salvo que la red tenga el cifrado activo
	 * (entonces se exige: nunca sale en claro lo que debía cifrarse). Devuelve array( encrypt, key_id ) o WP_Error.
	 */
	public static function own_key( $secret ) {
		$secret = is_string( $secret ) ? $secret : '';
		if ( '' === $secret ) {
			if ( Digitalisimo_Backups_Security::settings()['encrypt'] ) return new WP_Error( 'digitalisimo_backups_extract_secret', 'Los respaldos de esta red se cifran: escribe una frase propia para este sitio extraído (16 caracteres o más). La clave de la red nunca se usa para un sitio que se entrega: abre todos sus respaldos.', array( 'status' => 400 ) );
			return array( 'encrypt' => false, 'key_id' => '' );
		}
		if ( ! Digitalisimo_Backups_Keys::valid_passphrase( $secret ) ) return new WP_Error( 'digitalisimo_backups_extract_secret', 'La frase del sitio extraído debe tener entre 16 y 1024 caracteres.', array( 'status' => 400 ) );
		if ( ! Digitalisimo_Backups_Sodium::available() ) return new WP_Error( 'digitalisimo_backups_not_ready', 'Este servidor no tiene las funciones de libsodium para cifrar el paquete (' . implode( ', ', Digitalisimo_Backups_Sodium::missing() ) . ').', array( 'status' => 422 ) );
		$wrap = self::wrap_key();
		if ( false === $wrap ) return new WP_Error( 'digitalisimo_backups_not_ready', 'Para cifrar un sitio extraído con su frase, wp-config.php debe definir AUTH_KEY y SECURE_AUTH_KEY propias (la clave se guarda envuelta con ellas mientras dura el trabajo).', array( 'status' => 422 ) );
		$kdf = Digitalisimo_Backups_Keys::new_kdf();
		$root = Digitalisimo_Backups_Keys::derive( $secret, $kdf );
		$key_id = Digitalisimo_Backups_Keys::key_id( $root );
		$nonce = random_bytes( 24 );
		$entry = array( 'v' => 1, 'descriptor' => Digitalisimo_Backups_Keys::descriptor( $key_id, 'passphrase', $kdf ), 'box' => Digitalisimo_Backups_Keys::b64url_encode( $nonce . sodium_crypto_secretbox( $root, $nonce, $wrap ) ) );
		Digitalisimo_Backups_Sodium::memzero( $root );
		Digitalisimo_Backups_Sodium::memzero( $wrap );
		$path = self::key_path( $key_id );
		$json = wp_json_encode( $entry );
		$temp = $path . '.tmp';
		if ( is_link( $path ) || is_link( $temp ) || ! is_string( $json ) || strlen( $json ) !== @file_put_contents( $temp, $json ) || ! @chmod( $temp, 0600 ) || ! @rename( $temp, $path ) ) {
			@unlink( $temp );
			return new WP_Error( 'digitalisimo_backups_storage', 'No se pudo guardar la clave del sitio extraído en el almacén privado.', array( 'status' => 500 ) );
		}
		return array( 'encrypt' => true, 'key_id' => $key_id );
	}
	/** Clave del trabajo en cada fase (la que fijó al encolarse). Falla cerrado: un paquete que debía cifrarse no sale en claro. */
	public static function job_plan( array $spec ) {
		$security = (array) ( $spec['security'] ?? array() );
		$key_id = (string) ( $security['key_id'] ?? '' );
		$none = array( 'root' => null, 'descriptor' => null, 'encrypt' => false );
		if ( '' === $key_id ) {
			if ( ! empty( $security['encrypt'] ) ) throw new Exception( 'El sitio extraído debía cifrarse y no fijó su clave. No se creó un paquete sin cifrar; vuelve a iniciarlo.' );
			return $none;
		}
		$path = self::key_path( $key_id );
		$entry = is_file( $path ) && ! is_link( $path ) ? json_decode( (string) @file_get_contents( $path ), true ) : null;
		$wrap = self::wrap_key();
		$raw = is_array( $entry ) ? Digitalisimo_Backups_Keys::b64url_decode( $entry['box'] ?? null ) : false;
		$root = false;
		if ( false !== $wrap && false !== $raw && strlen( $raw ) === 24 + 16 + 32 ) {
			try { $root = sodium_crypto_secretbox_open( substr( $raw, 24 ), substr( $raw, 0, 24 ), $wrap ); } catch ( Throwable $e ) { $root = false; }
		}
		if ( false !== $wrap ) Digitalisimo_Backups_Sodium::memzero( $wrap );
		$descriptor = is_array( $entry['descriptor'] ?? null ) ? $entry['descriptor'] : null;
		if ( ! is_string( $root ) || 32 !== strlen( $root ) || ! hash_equals( $key_id, Digitalisimo_Backups_Keys::key_id( $root ) ) || ! Digitalisimo_Backups_Keys::valid_descriptor( $descriptor ) ) {
			throw new Exception( 'La clave del sitio extraído ya no se puede abrir (se perdió del almacén o cambiaron las claves de wp-config.php). No se creó un paquete sin cifrar; vuelve a iniciar la extracción con su frase.' );
		}
		return array( 'root' => $root, 'descriptor' => $descriptor, 'encrypt' => ! empty( $security['encrypt'] ) );
	}
	/** Retira la clave de una extracción que ya no la necesita y las de trabajos que ya no siguen. */
	public static function forget_keys( $key_id = '' ) {
		$active = array();
		try {
			foreach ( Digitalisimo_Backups_Jobs::active() as $job ) $active[ (string) ( $job['spec']['security']['key_id'] ?? '' ) ] = true;
			Digitalisimo_Backups::storage_directory();
		} catch ( Throwable $e ) {
			return;
		}
		if ( '' !== $key_id ) unset( $active[ $key_id ] );
		foreach ( glob( trailingslashit( Digitalisimo_Backups::storage_directory() ) . '.extract-key-*.json' ) ?: array() as $path ) {
			$id = substr( basename( $path, '.json' ), strlen( '.extract-key-' ) );
			if ( ( $id === $key_id || ! isset( $active[ $id ] ) ) && ( is_file( $path ) || is_link( $path ) ) ) @unlink( $path );
		}
	}
	/**
	 * Para el historial de la red (nunca para el paquete): lo que no viajó por nombre, las tablas sin número de sitio que no
	 * viajaron, y los mu-plugins y drop-ins. Sale del informe de la exportación que sigue en el área de trabajo.
	 */
	public static function network_detail( $job_id ) {
		try { $staging = Digitalisimo_Backups_Job_Store::staging( (string) $job_id ); } catch ( Throwable $e ) { return array(); }
		$report = array();
		foreach ( glob( $staging . '/database.*.sql.extract.json' ) ?: array() as $path ) $report = self::read_report( $path );
		if ( empty( $report['site'] ) ) return array();
		$files = self::file_map( $report );
		return array( 'excluded' => $files['excluded'], 'excluded_tables' => $report['internal']['excluded_tables'] ?? null, 'mu_plugins' => $files['mu_plugins'], 'drop_ins' => $files['drop_ins'] );
	}
	private static function key_path( $key_id ) { return trailingslashit( Digitalisimo_Backups::storage_directory() ) . '.extract-key-' . preg_replace( '/[^0-9a-f]/', '', (string) $key_id ) . '.json'; }
	/** Subclave propia (separación de dominio) de la que envuelve el llavero de «Seguridad». */
	private static function wrap_key() {
		$wrap = Digitalisimo_Backups_Keys::wrap_key();
		return false === $wrap ? false : sodium_crypto_generichash( 'DGBK1/extract-key', $wrap, 32 );
	}
	/** Etiqueta del trabajo en «Trabajos» (filtro `digitalisimo_backups_job_origin`). */
	public static function job_origin( $label, $spec ) {
		return is_array( $spec ) && self::is_extract( $spec ) ? 'Extracción de ' . (string) $spec['extract']['label'] . ' como WordPress individual' : $label;
	}
	/** Informe de la exportación junto al SQL del área de trabajo: `database.<tag>.sql.extract.json`. */
	public static function report_path( $sql_path ) { return preg_replace( '/\.part$/', '', (string) $sql_path ) . '.extract.json'; }
	public static function write_report( $path, array $report ) {
		$json = wp_json_encode( $report );
		$temp = $path . '.tmp';
		if ( ! is_string( $json ) || is_link( $path ) || is_link( $temp ) || strlen( $json ) !== @file_put_contents( $temp, $json ) || ! @chmod( $temp, 0600 ) || ! @rename( $temp, $path ) ) {
			@unlink( $temp );
			throw new Exception( 'No se pudo guardar el informe de la exportación del sitio en el área de trabajo.' );
		}
	}
	public static function read_report( $path ) {
		if ( is_link( $path ) || ! is_file( $path ) ) return array();
		$data = json_decode( (string) @file_get_contents( $path ), true );
		return is_array( $data ) ? $data : array();
	}
	public static function is_extract( array $spec ) { return self::SCOPE === ( $spec['scope'] ?? '' ) && is_array( $spec['extract'] ?? null ); }
	public static function policy_id( $site_id ) { return self::POLICY . '-' . (int) $site_id; }

	/* ------------------------------------------------------------------ Comprobaciones */

	/**
	 * Lo que impide extraer el sitio, antes de exportar nada: tablas esenciales, una tabla propia que chocaría con
	 * `wp_N_users`, y los medios en la forma de una red (`ms_files_rewriting`, que guarda los medios en blogs.dir, se
	 * acepta: la carpeta del sitio pasa igual a `uploads/`).
	 */
	public static function problems( array $site ) {
		global $wpdb;
		$problems = array();
		$prefix = $wpdb->get_blog_prefix( $site['id'] );
		$tables = self::site_tables( $site['id'] );
		foreach ( array( 'options', 'posts', 'postmeta' ) as $core ) if ( ! in_array( $prefix . $core, $tables, true ) ) $problems[] = 'Al sitio ' . $site['label'] . ' le falta la tabla ' . $prefix . $core . '.';
		if ( 1 !== (int) $site['id'] ) foreach ( array( 'users', 'usermeta' ) as $own ) if ( in_array( $prefix . $own, $tables, true ) ) $problems[] = 'El sitio ' . $site['label'] . ' tiene una tabla propia ' . $prefix . $own . ' que el paquete usaría para sus usuarios; renómbrala antes de extraerlo.';
		$uploads = self::uploads( $site['id'] );
		$root = trailingslashit( wp_normalize_path( (string) realpath( ABSPATH ) ) );
		if ( is_dir( $uploads['basedir'] ) ) {
			$real = realpath( $uploads['basedir'] );
			if ( false === $real || is_link( $uploads['basedir'] ) || 0 !== strpos( trailingslashit( wp_normalize_path( $real ) ), $root ) ) $problems[] = 'Los medios del sitio ' . $site['label'] . ' (' . $uploads['basedir'] . ') están fuera de WordPress o tras un enlace simbólico.';
		}
		return $problems;
	}
	/** Fase «preflight» del trabajo: los mismos motivos, como error permanente. */
	public static function preflight( array $spec ) {
		$site = self::site( (int) ( $spec['extract']['site_id'] ?? 0 ) );
		if ( is_wp_error( $site ) ) throw new Digitalisimo_Backups_Permanent_Error( $site->get_error_message() );
		foreach ( self::problems( $site ) as $problem ) throw new Digitalisimo_Backups_Permanent_Error( $problem );
		return $site;
	}

	/* ------------------------------------------------------------------ Base de datos */

	/**
	 * Tablas del sitio en la red: las de su prefijo y, en el sitio principal (que comparte el prefijo con la red), sin las
	 * globales ni las de los demás sitios (`wp_<n>_`).
	 */
	public static function site_tables( $site_id ) {
		global $wpdb;
		$base = (string) $wpdb->base_prefix;
		$prefix = (string) $wpdb->get_blog_prefix( $site_id );
		$all = (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $prefix ) . '%' ) );
		$tables = array();
		foreach ( $all as $table ) {
			$table = (string) $table;
			if ( 0 !== strpos( $table, $prefix ) ) continue;
			if ( $prefix === $base ) {
				$rest = substr( $table, strlen( $base ) );
				if ( in_array( $rest, self::GLOBAL_TABLES, true ) || preg_match( '/^[0-9]+_/', $rest ) ) continue;
			}
			$tables[] = $table;
		}
		sort( $tables );
		return $tables;
	}
	/**
	 * Tablas con el prefijo de la red, sin número de sitio y que no son de WordPress, que no viajan: de plugins del sitio
	 * principal o globales de un plugin (no se distinguen). Al extraer el principal, viajan todas.
	 */
	public static function global_plugin_tables( $site_id ) {
		global $wpdb;
		if ( 1 === (int) $site_id ) return array();
		$base = (string) $wpdb->base_prefix;
		$out = array();
		foreach ( (array) $wpdb->get_col( 'SHOW TABLES' ) as $table ) {
			$table = (string) $table;
			if ( 0 !== strpos( $table, $base ) ) continue;
			$rest = substr( $table, strlen( $base ) );
			if ( in_array( $rest, self::GLOBAL_TABLES, true ) || in_array( $rest, self::CORE_SITE_TABLES, true ) || preg_match( '/^[0-9]+_/', $rest ) ) continue;
			$out[] = $table;
		}
		sort( $out );
		return $out;
	}
	/** Carpeta y URL de los medios del sitio, como las calcula WordPress dentro del sitio. */
	public static function uploads( $site_id ) {
		$switched = function_exists( 'switch_to_blog' ) && (int) get_current_blog_id() !== (int) $site_id;
		if ( $switched ) switch_to_blog( (int) $site_id );
		try {
			$uploads = wp_upload_dir( null, false );
		} finally {
			if ( $switched ) restore_current_blog();
		}
		return array( 'basedir' => untrailingslashit( wp_normalize_path( (string) ( $uploads['basedir'] ?? '' ) ) ), 'baseurl' => untrailingslashit( (string) ( $uploads['baseurl'] ?? '' ) ) );
	}
	/**
	 * Otras URL de la misma carpeta de medios. WordPress arma la URL de los medios con WP_CONTENT_URL, que sale del
	 * `siteurl` del sitio que atiende la petición: lo subido desde el propio sitio lleva su dirección
	 * (`red.test/dos/wp-content/uploads/sites/2`) y lo que se creó desde el principal (switch_to_blog(), como este mismo
	 * trabajo), la del principal (`red.test/wp-content/uploads/sites/2`). Con `home` distinto de `siteurl`, también esa.
	 */
	public static function upload_aliases( $site_id, array $uploads ) {
		$content = wp_normalize_path( (string) realpath( WP_CONTENT_DIR ) );
		$basedir = wp_normalize_path( (string) $uploads['basedir'] );
		$root = trailingslashit( wp_normalize_path( (string) realpath( ABSPATH ) ) );
		if ( '' === $content || 0 !== strpos( $content . '/', $root ) ) return array();
		// Fuera de wp-content (blogs.dir con otra raíz) sólo valen las URL de ms_files_rewriting.
		$under = 0 === strpos( $basedir . '/', $content . '/' );
		$tail = $under ? '/' . trim( substr( $content, strlen( $root ) ), '/' ) . substr( $basedir, strlen( $content ) ) : '';
		$out = array();
		// Red antigua con ms_files_rewriting (medios en blogs.dir): el contenido guarda la URL `…/files/` (`fileupload_url`),
		// que en un WordPress individual no existe; pasa también a la carpeta de medios.
		if ( function_exists( 'get_site_option' ) && get_site_option( 'ms_files_rewriting' ) ) {
			foreach ( array( get_blog_option( $site_id, 'fileupload_url' ), untrailingslashit( (string) get_blog_option( $site_id, 'siteurl' ) ) . '/files' ) as $files ) {
				$files = untrailingslashit( (string) $files );
				if ( '' !== $files && preg_match( '#^https?://#i', $files ) && $files !== $uploads['baseurl'] && ! in_array( $files, $out, true ) ) $out[] = $files;
			}
		}
		foreach ( $under ? array( get_blog_option( $site_id, 'siteurl' ), get_blog_option( $site_id, 'home' ), get_blog_option( get_main_site_id(), 'siteurl' ), get_blog_option( get_main_site_id(), 'home' ) ) : array() as $base ) {
			$base = untrailingslashit( (string) $base );
			if ( '' === $base ) continue;
			$url = $base . $tail;
			if ( $url !== $uploads['baseurl'] && ! in_array( $url, $out, true ) ) $out[] = $url;
		}
		return $out;
	}
	/**
	 * Exportador del sitio para `database_sql_to_file()`: sus tablas, y los usuarios y metas filtrados con el prefijo del
	 * sitio. `report()` dice qué hizo, sin valores ni datos personales.
	 */
	public static function exporter( array $spec ) {
		global $wpdb;
		$site = self::preflight( $spec );
		$tables = self::site_tables( $site['id'] );
		$base = (string) $wpdb->base_prefix;
		$tables[] = $base . 'users';
		$tables[] = $base . 'usermeta';
		$uploads = self::uploads( $site['id'] );
		$uploads['aliases'] = self::upload_aliases( $site['id'], $uploads );
		return new Digitalisimo_Backups_Site_Extract_Export( $site, $spec['extract'], $tables, $uploads );
	}

	/* ------------------------------------------------------------------ Usuarios */

	/**
	 * Quién viaja y cómo. Tipos:
	 * - `member`: tiene `wp_N_capabilities` (WordPress lo da por miembro aunque no tenga rol). Entra con su rol, todos sus
	 *   datos y el hash de su contraseña.
	 * - `admin`: superadministrador que no es miembro y entra como administrador, con su contraseña. Con `all`, todos; con
	 *   `managing`, sólo quien administra el sitio de forma comprobable: su correo es el de administración del sitio (quien
	 *   tiene en el sitio un rol con `manage_options` ya es miembro y entra con él). Haber escrito una entrada, una revisión
	 *   o un comentario no cuenta: WordPress guarda como autor a quien edita, también como soporte. Con `none` (por
	 *   defecto), ninguno: ningún superadministrador que no es miembro viaja con su contraseña sin pedirlo.
	 * - `author`: autor de entradas o comentarios que no viaja como miembro ni como administrador (un usuario retirado del
	 *   sitio, un superadministrador que no entra, un usuario bloqueado en la red). Viaja sólo lo que necesita la autoría
	 *   (usuario, nombre público y perfil), sin contraseña, correo, rol ni metas globales.
	 * Un usuario bloqueado en la red (`spam` o `deleted`) nunca entra como miembro ni como administrador: fuera de la red,
	 * WordPress no comprueba esas marcas y volvería a entrar. Si escribió en el sitio viaja como autor; si no, no viaja.
	 * Devuelve user_id => tipo.
	 */
	public static function users( array $members, array $super_admins, array $authors, array $admin_emails, $mode, array $blocked = array() ) {
		$out = array();
		$blocked = array_flip( array_map( 'intval', $blocked ) );
		foreach ( $members as $id ) if ( ! isset( $blocked[ (int) $id ] ) ) $out[ (int) $id ] = 'member';
		$authors = array_flip( array_map( 'intval', $authors ) );
		foreach ( $super_admins as $id => $email ) {
			$id = (int) $id;
			if ( isset( $out[ $id ] ) || isset( $blocked[ $id ] ) ) continue;
			$manages = '' !== (string) $email && in_array( strtolower( (string) $email ), $admin_emails, true );
			if ( 'all' === $mode || ( 'managing' === $mode && $manages ) ) $out[ $id ] = 'admin';
		}
		foreach ( array_keys( $authors ) as $id ) if ( $id > 0 && ! isset( $out[ $id ] ) ) $out[ $id ] = 'author';
		ksort( $out );
		return $out;
	}
	/** Metas de un autor que no es miembro: sólo el perfil público que pinta su autoría. */
	const AUTHOR_USER_META = array( 'nickname', 'first_name', 'last_name', 'description' );
	/**
	 * Destino de una meta de usuario en el paquete: su nombre, o null si no viaja. El paquete usa el prefijo del sitio
	 * (`wp_N_`), así que sus metas por sitio quedan tal cual y las de los demás sitios se descartan. Las del sitio principal
	 * (`wp_` sin número) también, cuando no es el que se extrae: las de núcleo siempre (rol, nivel y preferencias), y las
	 * demás cuando el sitio tiene la suya con el mismo nombre. Una meta con el prefijo de la red y sin número que no es de
	 * WordPress viaja: puede ser global de un plugin (`wp_2fa_…`), y nunca da permisos. Un autor que no es miembro sólo
	 * lleva su perfil público (AUTHOR_USER_META): ni la facturación de otra tienda, ni su segundo factor, ni nada más.
	 */
	public static function user_meta_key( $key, $kind, $site_id, $base, array $own_suffixes ) {
		$key = (string) $key;
		if ( 'author' === $kind ) return in_array( $key, self::AUTHOR_USER_META, true ) ? $key : null;
		if ( in_array( $key, self::NETWORK_USER_META, true ) ) return null;
		if ( '' === $base || 0 !== strpos( $key, $base ) ) return $key;
		$rest = substr( $key, strlen( $base ) );
		if ( preg_match( '/^([0-9]+)_(.+)$/s', $rest, $match ) ) return ( (int) $match[1] === (int) $site_id && 1 !== (int) $site_id ) ? $key : null;
		if ( 1 === (int) $site_id ) return $key;
		if ( in_array( $rest, self::SITE_USER_META, true ) || isset( $own_suffixes[ $rest ] ) ) return null;
		return $key;
	}

	/* ------------------------------------------------------------------ Ajustes */

	/**
	 * Ajustes de red de los plugins DIGITALÍSIMO que el sitio usa, escritos en sus opciones con la misma resolución que
	 * cada plugin (`contenido → sitio → red → default`), para que fuera de la red se comporte igual. Funciona sin los
	 * plugins cargados: lee sus datos. $site y $network son nombre => valor ya leído (sin serializar); $active, los
	 * plugins activos del sitio y de la red (null: sin comprobar). Sólo se materializa lo de un plugin activo en el sitio:
	 * un plugin inactivo no recibe ajustes de la red (la ruta privada de acceso, por ejemplo). Todo por lista blanca: lo
	 * que es de la cuenta de la red (claves de API, cuentas, verificaciones, medición) sólo con $secrets, y lo que no se
	 * reconoce no se copia. Devuelve array( 'set' => nombre => valor, 'delete' => nombres, 'report' => array(
	 * 'materialized', 'secrets', 'unknown', 'inactive' ) ).
	 */
	public static function materialize( array $site, array $network, $secrets, $active = null ) {
		$set = array();
		$delete = array();
		$materialized = array();
		$withheld = array();
		$unknown = array();
		$inactive = array();
		$on = function( $plugin ) use ( $active, &$inactive ) {
			if ( null === $active ) return true;
			foreach ( (array) $active as $file ) if ( basename( (string) $file ) === self::PLUGIN_FILES[ $plugin ] ) return true;
			$inactive[] = $plugin;
			return false;
		};
		$filled = function( $value ) { return is_array( $value ) ? (bool) array_filter( $value, function( $v ) { return '' !== ( is_scalar( $v ) ? (string) $v : ( is_array( $v ) && $v ? 'x' : '' ) ); } ) : '' !== (string) ( is_scalar( $value ) ? $value : '' ); };
		// SEO (también los ajustes de módulos que Hosting heredó de SEO): una clave heredada toma el valor de la red si la red lo tiene.
		if ( is_array( $network[ self::SEO_OPTION ] ?? null ) && $network[ self::SEO_OPTION ] && $on( 'seo' ) ) {
			$own = is_array( $site[ self::SEO_OPTION ] ?? null ) ? $site[ self::SEO_OPTION ] : array();
			$inherit = is_array( $site['digitalisimo_seo_network_inherit'] ?? null ) ? $site['digitalisimo_seo_network_inherit'] : array();
			$module = is_array( $site['digitalisimo_module_network_inherit'] ?? null ) ? $site['digitalisimo_module_network_inherit'] : array();
			$count = 0;
			foreach ( $network[ self::SEO_OPTION ] as $key => $value ) {
				$key = (string) $key;
				if ( in_array( $key, self::SEO_SITE_ONLY, true ) ) continue;
				$map = in_array( $key, self::SEO_MODULE_KEYS, true ) ? $module : $inherit;
				if ( array_key_exists( $key, $map ) && empty( $map[ $key ] ) ) continue;
				if ( in_array( $key, self::SEO_ACCOUNT, true ) ) {
					// De la cuenta de la red: sin pedirlo queda vacía (también el vacío de la red manda sobre un valor viejo del sitio).
					if ( ! $secrets && $filled( $value ) ) {
						$withheld[] = self::SEO_OPTION . ':' . $key;
						if ( 'ai_providers' === $key && is_array( $value ) ) {
							// Los proveedores IA de SEO se quedan con su configuración y sin su clave.
							$own[ $key ] = array();
							foreach ( $value as $id => $provider ) $own[ $key ][ $id ] = is_array( $provider ) ? array_merge( $provider, array( 'key' => '' ) ) : '';
						} else {
							$own[ $key ] = '';
						}
						continue;
					}
					$own[ $key ] = $value;
					$count++;
					continue;
				}
				if ( ! in_array( $key, self::SEO_COPY, true ) ) { $unknown[] = self::SEO_OPTION . ':' . $key; continue; }
				$own[ $key ] = $value;
				$count++;
			}
			// Reglas de rastreadores IA: «inherit» por rastreador se resolvía con las de la red; fuera de ella sería el valor por defecto.
			if ( is_string( $own['seo_ai_crawler_rules'] ?? null ) ) {
				$rules = json_decode( $own['seo_ai_crawler_rules'], true );
				if ( is_array( $rules ) && in_array( 'inherit', $rules, true ) ) {
					$from = json_decode( (string) ( $network[ self::SEO_OPTION ]['seo_ai_crawler_rules'] ?? '' ), true );
					foreach ( $rules as $id => $state ) {
						if ( 'inherit' !== $state ) continue;
						if ( is_array( $from ) && in_array( $from[ $id ] ?? null, array( 'allow', 'disallow' ), true ) ) $rules[ $id ] = $from[ $id ];
						else unset( $rules[ $id ] );
					}
					$own['seo_ai_crawler_rules'] = wp_json_encode( $rules );
				}
			}
			$set[ self::SEO_OPTION ] = $own;
			$materialized[ self::SEO_OPTION ] = $count;
		}
		// Tools: ruta privada de acceso, redirección de adjuntos y desplazamiento en móvil (heredada salvo que el sitio la personalice).
		$tools_network = $network[ self::TOOLS_NETWORK ] ?? null;
		if ( ! is_array( $tools_network ) && is_array( $network[ self::SEO_OPTION ] ?? null ) ) $tools_network = self::tools_import( $network[ self::SEO_OPTION ] );
		if ( is_array( $tools_network ) && $on( 'tools' ) ) {
			$net = array_merge( self::TOOLS_DEFAULTS, array_intersect_key( $tools_network, self::TOOLS_DEFAULTS ) );
			$data = $site[ self::TOOLS_SITE ] ?? null;
			if ( ! is_array( $data ) ) {
				// Sin la opción de Tools, Tools la importaba de SEO con la herencia del sitio y la red.
				$legacy = is_array( $site[ self::SEO_OPTION ] ?? null ) ? $site[ self::SEO_OPTION ] : array();
				$map = is_array( $site['digitalisimo_seo_network_inherit'] ?? null ) ? $site['digitalisimo_seo_network_inherit'] : array();
				$net_legacy = is_array( $network[ self::SEO_OPTION ] ?? null ) ? $network[ self::SEO_OPTION ] : array();
				$values = self::tools_import( $legacy );
				$inherit = array();
				foreach ( self::TOOLS_LEGACY_KEYS as $key => $old ) {
					$inherits = ! array_key_exists( $old, $map ) || ! empty( $map[ $old ] );
					if ( $inherits && ! array_key_exists( $old, $net_legacy ) && array_key_exists( $key, $values ) ) $inherits = false;
					$inherit[ $key ] = $inherits;
				}
				$data = array( 'values' => $values, 'inherit' => $inherit );
			}
			$values = array_intersect_key( (array) ( $data['values'] ?? array() ), self::TOOLS_DEFAULTS );
			$inherit = (array) ( $data['inherit'] ?? array() );
			$count = 0;
			foreach ( self::TOOLS_DEFAULTS as $key => $default ) {
				if ( array_key_exists( $key, $inherit ) && empty( $inherit[ $key ] ) ) continue;
				$values[ $key ] = $net[ $key ];
				$count++;
			}
			$set[ self::TOOLS_SITE ] = array( 'values' => $values, 'inherit' => $inherit );
			$materialized[ self::TOOLS_SITE ] = $count;
		}
		// Elements (front): cada interruptor heredado toma el de la red, o el neutro si la red no lo guardó. Sólo booleanos.
		if ( is_array( $network[ self::FRONT_NETWORK ] ?? null ) && $on( 'elements' ) ) {
			$own = is_array( $site[ self::FRONT_SITE ] ?? null ) ? $site[ self::FRONT_SITE ] : array();
			$mine = is_array( $own['own'] ?? null ) ? array_map( 'strval', $own['own'] ) : array();
			$keys = array_unique( array_merge( array_keys( $network[ self::FRONT_NETWORK ] ), array_diff( array_keys( $own ), array( 'own' ) ) ) );
			$count = 0;
			foreach ( $keys as $key ) {
				if ( 'own' === $key || in_array( (string) $key, $mine, true ) || ! preg_match( '/^[a-z0-9_]+$/D', (string) $key ) ) continue;
				if ( array_key_exists( $key, $network[ self::FRONT_NETWORK ] ) ) $own[ $key ] = ! empty( $network[ self::FRONT_NETWORK ][ $key ] );
				else unset( $own[ $key ] );
				$count++;
			}
			$set[ self::FRONT_SITE ] = $own;
			$materialized[ self::FRONT_SITE ] = $count;
		}
		// Clave de Google Places de Elements: la del sitio, o la de la red (es de la cuenta de la red).
		$google_site = is_string( $site[ self::GOOGLE_KEY ] ?? null ) ? trim( $site[ self::GOOGLE_KEY ] ) : '';
		$google_network = is_string( $network[ self::GOOGLE_KEY ] ?? null ) ? trim( $network[ self::GOOGLE_KEY ] ) : '';
		if ( '' === $google_site && '' !== $google_network && $on( 'elements' ) ) {
			if ( $secrets ) { $set[ self::GOOGLE_KEY ] = $google_network; $materialized[ self::GOOGLE_KEY ] = 1; }
			else $withheld[] = self::GOOGLE_KEY;
		}
		// Hosting e IA Tools heredan todo su ajuste cuando el sitio lo pide (IA, por defecto), por lista blanca.
		foreach ( array( self::HOSTING => array( 'hosting', false ), self::AI => array( 'ai', true ) ) as $option => $meta ) {
			$own = is_array( $site[ $option ] ?? null ) ? $site[ $option ] : array();
			$inherits = array_key_exists( 'inherit_network', $own ) ? ! empty( $own['inherit_network'] ) : $meta[1];
			if ( ! $inherits || ( ! is_array( $network[ $option ] ?? null ) && ! $own ) || ! $on( $meta[0] ) ) continue;
			$from = is_array( $network[ $option ] ?? null ) ? $network[ $option ] : array();
			$value = array();
			$copy = self::HOSTING === $option ? self::HOSTING_COPY : self::AI_COPY;
			$account = self::HOSTING === $option ? self::HOSTING_ACCOUNT : array();
			foreach ( $from as $key => $item ) {
				$key = (string) $key;
				if ( 'inherit_network' === $key ) continue;
				if ( in_array( $key, $copy, true ) ) { $value[ $key ] = $item; continue; }
				if ( in_array( $key, $account, true ) ) {
					if ( $secrets || ! $filled( $item ) ) $value[ $key ] = $item;
					else { $withheld[] = $option . ':' . $key; $value[ $key ] = ''; }
					continue;
				}
				// IA: los proveedores viajan sin su clave (salvo con $secrets); sus perfiles, tal cual.
				if ( self::AI === $option && 'providers' === $key && is_array( $item ) ) {
					foreach ( $item as $provider => $data ) {
						if ( ! is_array( $data ) ) continue;
						$keep = array_intersect_key( $data, array_flip( array( 'enabled', 'url', 'model' ) ) );
						if ( '' !== (string) ( is_scalar( $data['key'] ?? null ) ? $data['key'] : '' ) ) {
							if ( $secrets ) $keep['key'] = $data['key'];
							else { $withheld[] = $option . ':providers.' . $provider . '.key'; $keep['key'] = ''; }
						}
						$value['providers'][ $provider ] = $keep;
					}
					continue;
				}
				$unknown[] = $option . ':' . $key;
			}
			$value['inherit_network'] = $own['inherit_network'] ?? 1;
			$set[ $option ] = $value;
			$materialized[ $option ] = count( $value ) - 1;
		}
		return array( 'set' => $set, 'delete' => $delete, 'report' => array( 'materialized' => $materialized, 'secrets' => $withheld, 'unknown' => array_slice( $unknown, 0, self::REPORT_LIMIT ), 'inactive' => array_values( array_unique( $inactive ) ) ) );
	}
	private static function tools_import( array $legacy ) {
		$out = array();
		foreach ( self::TOOLS_LEGACY_KEYS as $key => $old ) if ( array_key_exists( $old, $legacy ) ) $out[ $key ] = $legacy[ $old ];
		return $out;
	}
	/** `sitemeta` de otros plugins (no de WordPress ni DIGITALÍSIMO): sólo los nombres, para el informe del paquete. */
	public static function other_network_options( array $keys ) {
		$out = array();
		foreach ( $keys as $key ) {
			$key = (string) $key;
			if ( in_array( $key, self::CORE_SITEMETA, true ) || 0 === strpos( $key, '_site_transient_' ) || 0 === strpos( $key, 'digitalisimo_' ) ) continue;
			$out[] = $key;
		}
		sort( $out );
		return array_values( array_unique( $out ) );
	}

	/* ------------------------------------------------------------------ Archivos */

	/** Archivos de la raíz que trae WordPress (6.x y 7.x): todo lo demás de la raíz no viaja (configuración, otras aplicaciones). */
	const CORE_ROOT_FILES = array( 'index.php', 'license.txt', 'readme.html', 'wp-activate.php', 'wp-blog-header.php', 'wp-comments-post.php', 'wp-config-sample.php', 'wp-cron.php', 'wp-links-opml.php', 'wp-load.php', 'wp-login.php', 'wp-mail.php', 'wp-settings.php', 'wp-signup.php', 'wp-trackback.php', 'xmlrpc.php' );
	/** Archivos que WordPress carga desde la raíz de wp-content (drop-ins): son del servidor de la red y no viajan activos. */
	const DROP_INS = array( 'advanced-cache.php', 'db.php', 'db-error.php', 'install.php', 'maintenance.php', 'object-cache.php', 'php-error.php', 'fatal-error-handler.php', 'sunrise.php', 'blog-deleted.php', 'blog-inactive.php', 'blog-suspended.php' );
	/** Nombre de una carpeta o archivo de tema o de plugin: sin «.», «..», barras ni rutas. */
	public static function safe_name( $name ) { return is_string( $name ) && (bool) preg_match( '/^[A-Za-z0-9_-][A-Za-z0-9._-]{0,199}$/D', $name ); }

	/**
	 * Archivos del paquete: origen => nombre en el ZIP (carpetas, recursivas). Todo por lista blanca: los archivos de la
	 * raíz que trae WordPress (nunca wp-config.php, wp-config-*.php, wp-salt.php ni otro archivo de configuración o de
	 * otra aplicación), `wp-admin` y `wp-includes`, `wp-content/index.php` (sin drop-ins: db.php, object-cache.php y los
	 * demás son del servidor de la red), mu-plugins (se avisa y se listan), idiomas, los plugins activos (del sitio y de la
	 * red), el tema y su padre, y los medios del sitio, que pasan a `wp-content/uploads/`. Un nombre de tema o de plugin
	 * con «.», «..» o una barra no se usa, y cada carpeta debe quedar dentro de su raíz. Devuelve también lo que no viaja,
	 * por categoría (para el historial de la red; el paquete sólo lleva los recuentos), y los mu-plugins y drop-ins.
	 */
	public static function file_map( array $report ) {
		$root = trailingslashit( wp_normalize_path( (string) realpath( ABSPATH ) ) );
		$content = wp_normalize_path( (string) realpath( WP_CONTENT_DIR ) );
		$rel_content = 0 === strpos( trailingslashit( $content ), $root ) ? substr( $content, strlen( $root ) ) : 'wp-content';
		$map = array();
		$excluded = array( 'root' => array(), 'content' => array(), 'plugins' => array(), 'themes' => array() );
		$rejected = array();
		$add = function( $path, $name ) use ( &$map ) {
			$path = wp_normalize_path( $path );
			if ( file_exists( $path ) || is_link( $path ) ) $map[] = array( 'from' => $path, 'to' => 'wordpress/' . ltrim( $name, '/' ) );
		};
		$relative = function( $path ) use ( $root ) {
			$real = realpath( $path );
			$path = wp_normalize_path( false === $real ? $path : $real );
			return 0 === strpos( $path . '/', $root ) ? rtrim( substr( $path, strlen( $root ) ), '/' ) : null;
		};
		// Dentro de su raíz: la carpeta (o archivo) real queda bajo la raíz real de temas o de plugins.
		$inside = function( $path, $parent ) {
			$real = realpath( $path );
			$base = realpath( $parent );
			return false !== $real && false !== $base && 0 === strpos( wp_normalize_path( $real ), trailingslashit( wp_normalize_path( $base ) ) );
		};
		foreach ( (array) @scandir( $root ) as $name ) {
			if ( '.' === $name || '..' === $name || '' === $name ) continue;
			$path = $root . $name;
			if ( ( in_array( $name, array( 'wp-admin', 'wp-includes' ), true ) && is_dir( $path ) ) || ( in_array( $name, self::CORE_ROOT_FILES, true ) && is_file( $path ) ) ) $add( $path, $name );
			elseif ( $root . $rel_content !== $path ) $excluded['root'][] = $name;
		}
		$drop_ins = array();
		foreach ( (array) @scandir( $content ) as $name ) {
			if ( '.' === $name || '..' === $name || '' === $name ) continue;
			$path = $content . '/' . $name;
			if ( 'index.php' === $name && is_file( $path ) ) $add( $path, $rel_content . '/index.php' );
			elseif ( in_array( $name, self::DROP_INS, true ) ) $drop_ins[] = $name;
			elseif ( ! in_array( $name, array( 'plugins', 'themes', 'mu-plugins', 'languages', 'uploads' ), true ) ) $excluded['content'][] = $name;
		}
		$included_plugins = array();
		$rel_plugins = $relative( WP_PLUGIN_DIR );
		foreach ( (array) ( $report['plugins']['active'] ?? array() ) as $plugin ) {
			$parts = explode( '/', wp_normalize_path( (string) $plugin ) );
			if ( count( $parts ) > 2 || ! self::safe_name( $parts[0] ) || ( isset( $parts[1] ) && ! self::safe_name( $parts[1] ) ) ) { $rejected[] = (string) $plugin; continue; }
			$top = $parts[0];
			$path = WP_PLUGIN_DIR . '/' . $top;
			if ( null === $rel_plugins || isset( $included_plugins[ $top ] ) || ! ( is_dir( $path ) || is_file( $path ) ) ) continue;
			if ( ! $inside( $path, WP_PLUGIN_DIR ) ) { $rejected[] = (string) $plugin; continue; }
			$included_plugins[ $top ] = true;
			$add( $path, $rel_plugins . '/' . $top );
		}
		if ( null !== $rel_plugins ) {
			$add( WP_PLUGIN_DIR . '/index.php', $rel_plugins . '/index.php' );
			foreach ( (array) @scandir( WP_PLUGIN_DIR ) as $name ) if ( '.' !== $name && '..' !== $name && 'index.php' !== $name && ! isset( $included_plugins[ $name ] ) ) $excluded['plugins'][] = $name;
		}
		$mu = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
		$mu_list = array();
		if ( null !== ( $rel = $relative( $mu ) ) && is_dir( $mu ) ) {
			$add( $mu, $rel );
			foreach ( (array) @scandir( $mu ) as $name ) if ( '.' !== $name && '..' !== $name && 'index.php' !== $name ) $mu_list[] = $name;
		}
		$languages = defined( 'WP_LANG_DIR' ) ? WP_LANG_DIR : WP_CONTENT_DIR . '/languages';
		if ( null !== ( $rel = $relative( $languages ) ) ) $add( $languages, $rel );
		$themes = array();
		foreach ( array( 'stylesheet', 'template' ) as $which ) {
			$theme = (string) ( $report['themes'][ $which ] ?? '' );
			if ( '' === $theme || isset( $themes[ $theme ] ) ) continue;
			if ( ! self::safe_name( $theme ) ) { $rejected[] = $theme; continue; }
			$theme_root = function_exists( 'get_theme_root' ) ? get_theme_root( $theme ) : WP_CONTENT_DIR . '/themes';
			if ( null === ( $rel = $relative( $theme_root ) ) || ! is_dir( $theme_root . '/' . $theme ) ) continue;
			if ( ! $inside( $theme_root . '/' . $theme, $theme_root ) ) { $rejected[] = $theme; continue; }
			$themes[ $theme ] = true;
			$add( $theme_root . '/' . $theme, $rel . '/' . $theme );
		}
		$theme_root = WP_CONTENT_DIR . '/themes';
		if ( null !== ( $rel = $relative( $theme_root ) ) ) {
			$add( $theme_root . '/index.php', $rel . '/index.php' );
			foreach ( (array) @scandir( $theme_root ) as $name ) if ( '.' !== $name && '..' !== $name && 'index.php' !== $name && ! isset( $themes[ $name ] ) ) $excluded['themes'][] = $name;
		}
		// Los medios del sitio pasan a la carpeta de un sitio individual; en el principal, sin las carpetas de los demás.
		$basedir = (string) ( $report['uploads']['basedir'] ?? '' );
		$skip = array();
		if ( 1 === (int) ( $report['site']['id'] ?? 0 ) ) $skip[] = wp_normalize_path( $basedir ) . '/sites';
		if ( '' !== $basedir && is_dir( $basedir ) && null !== $relative( $basedir ) ) $map[] = array( 'from' => wp_normalize_path( $basedir ), 'to' => 'wordpress/' . $rel_content . '/uploads', 'skip' => $skip );
		$count = array_map( 'count', $excluded );
		foreach ( $excluded as $kind => $names ) $excluded[ $kind ] = array_slice( $names, 0, self::REPORT_LIMIT );
		return array( 'map' => $map, 'excluded' => $excluded, 'excluded_count' => $count, 'rejected' => array_slice( $rejected, 0, 20 ), 'mu_plugins' => array_slice( $mu_list, 0, self::REPORT_LIMIT ), 'drop_ins' => $drop_ins );
	}
	/** mu-plugins (viajan) y drop-ins (no viajan) de la red, para el aviso de la pantalla. */
	public static function server_files() {
		$mu = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
		$mu_list = array();
		foreach ( is_dir( $mu ) ? (array) @scandir( $mu ) : array() as $name ) if ( '.' !== $name && '..' !== $name && 'index.php' !== $name ) $mu_list[] = $name;
		$drop_ins = array();
		foreach ( self::DROP_INS as $name ) if ( is_file( WP_CONTENT_DIR . '/' . $name ) ) $drop_ins[] = $name;
		return array( 'mu_plugins' => array_slice( $mu_list, 0, 50 ), 'drop_ins' => $drop_ins );
	}
	/** Entradas que exige un paquete de este alcance (sin wp-config.php: el del destino nunca se toca). */
	public static function required_entries() { return array( 'database.sql', 'wordpress/wp-load.php', 'wordpress/wp-settings.php', 'wordpress/wp-admin/index.php', 'wordpress/wp-includes/version.php' ); }

	/* ------------------------------------------------------------------ Manifiesto */

	/**
	 * Bloque `extract` del manifiesto: lo que el destino y quien restaura necesitan saber, sin valores ni datos personales.
	 * De lo que no viaja sólo van los recuentos: los nombres de la raíz del servidor, de los plugins y temas de la red y de
	 * sus tablas de otros plugins se quedan en el historial de la red (`internal`). Lo que sí viaja y conviene revisar antes
	 * de entregar el sitio (mu-plugins) va por nombre, y también los drop-ins que no viajan.
	 */
	public static function manifest_block( array $report, array $files = array() ) {
		$block = $report;
		unset( $block['internal'] );
		$block['excluded_tables'] = (int) ( $report['internal']['excluded_tables_count'] ?? 0 );
		$block['files'] = array( 'excluded' => (array) ( $files['excluded_count'] ?? array() ), 'mu_plugins' => array_values( (array) ( $files['mu_plugins'] ?? array() ) ), 'drop_ins' => array_values( (array) ( $files['drop_ins'] ?? array() ) ), 'rejected' => array_values( (array) ( $files['rejected'] ?? array() ) ) );
		return $block;
	}

	/* ------------------------------------------------------------------ Reubicación (motor de E1) */

	/**
	 * Bloque `origin` del manifiesto (el que lee Digitalisimo_Backups_Relocate::origin_of()): el sitio extraído descrito
	 * como el WordPress individual que será. Sus direcciones son las suyas en la red (el dominio y la ruta del sitio), su
	 * prefijo el que tenía en ella y su carpeta de medios la suya (`uploads/sites/N`): el motor de E1 pasa todo eso a las
	 * del destino al restaurar. Las rutas del servidor (ABSPATH, wp-content) son las de la red, que son las mismas.
	 */
	public static function origin_block( array $report ) {
		$origin = Digitalisimo_Backups_Relocate::describe( false );
		$origin['home'] = untrailingslashit( (string) $report['site']['url'] );
		$origin['siteurl'] = untrailingslashit( (string) ( $report['site']['site_url'] ?? $report['site']['url'] ) );
		$origin['uploads_dir'] = (string) ( $report['uploads']['basedir'] ?? '' );
		$origin['prefix'] = (string) $report['source_prefix'];
		$origin['multisite'] = false;
		$origin['subdomain'] = false;
		unset( $origin['network'], $origin['networks'], $origin['sites'], $origin['config'] );
		return $origin;
	}
	/**
	 * Pares de URL que el plan de E1 no deduce de `home` y `siteurl`: la URL de los medios del sitio en la red (con
	 * `/sites/N`, o la de blogs.dir con ms_files_rewriting) y sus alias (la misma carpeta servida desde el dominio de la
	 * red) pasan a la URL de los medios de un sitio individual. Con `addresses = current` (por defecto), la del destino;
	 * con `backup`, la del sitio con sus direcciones de siempre. Se añaden al mismo plan (el mismo reescritor seguro con
	 * datos serializados, JSON y URL codificadas); la ruta más larga del mismo host manda, así que van antes que `home`.
	 */
	public static function adjust_plan( Digitalisimo_Backups_Relocate $plan, array $manifest ) {
		if ( self::SCOPE !== ( $manifest['scope'] ?? '' ) || ! is_array( $manifest['extract'] ?? null ) ) return $plan;
		$extract = $manifest['extract'];
		$from = untrailingslashit( (string) ( $extract['uploads']['baseurl'] ?? '' ) );
		if ( '' === $from ) return $plan;
		$here = wp_upload_dir( null, false );
		$baseurl = untrailingslashit( (string) ( $here['baseurl'] ?? '' ) );
		$siteurl = untrailingslashit( (string) get_option( 'siteurl' ) );
		if ( 'backup' === $plan->mode() ) {
			$origin = untrailingslashit( (string) ( $extract['site']['site_url'] ?? $extract['site']['url'] ?? '' ) );
			$to = '' !== $siteurl && 0 === strpos( $baseurl, $siteurl . '/' ) ? $origin . substr( $baseurl, strlen( $siteurl ) ) : $origin . '/wp-content/uploads';
		} else {
			$to = $baseurl;
		}
		if ( '' === $to ) return $plan;
		$plan->add_url( $from, $to );
		foreach ( (array) ( $extract['uploads']['aliases'] ?? array() ) as $alias ) if ( is_string( $alias ) && '' !== $alias ) $plan->add_url( untrailingslashit( $alias ), $to );
		return $plan;
	}

	/* ------------------------------------------------------------------ Restauración */

	/**
	 * La configuración de Backups del destino (destinos, credenciales cifradas con sus salts, automatizaciones, almacén):
	 * describe ese servidor y el paquete no trae la de la red. El historial, el llavero y «Seguridad» tienen su propio
	 * mecanismo. Nombre => valor.
	 */
	public static function destination_snapshot() {
		global $wpdb;
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT option_name, autoload FROM `' . $wpdb->prefix . 'options` WHERE option_name LIKE %s', $wpdb->esc_like( 'digitalisimo_backups_' ) . '%' ), ARRAY_A );
		$keep = array();
		foreach ( $rows as $row ) {
			$name = (string) ( $row['option_name'] ?? '' );
			if ( ! self::kept( $name ) ) continue;
			$keep[ $name ] = array( 'value' => get_option( $name ), 'autoload' => in_array( strtolower( (string) ( $row['autoload'] ?? '' ) ), array( 'yes', 'on', 'auto-on' ), true ) );
		}
		return $keep;
	}
	/** Opciones de Backups que describen el servidor (no el historial, el llavero ni «Seguridad», que tienen su mecanismo). */
	private static function kept( $name ) {
		return 0 === strpos( $name, 'digitalisimo_backups_' ) && ! in_array( $name, array( Digitalisimo_Backups::HISTORY, Digitalisimo_Backups_Security::OPTION, Digitalisimo_Backups::LAST_RELOCATION ), true ) && 0 !== strpos( $name, Digitalisimo_Backups_Keys::SECRET_PREFIX );
	}
	/**
	 * Lo mismo para el diario de la restauración. Antes de tomar la foto, restore_digitalisimo_zip() pasa al almacén
	 * cifrado de S4 cualquier secreto que siguiera en claro en los ajustes, así que con AUTH_KEY y SECURE_AUTH_KEY propias
	 * el diario lleva las credenciales del destino cifradas (`digitalisimo_backups_credentials`) y ninguna en claro, y una
	 * restauración interrumpida no las pierde. Sin esas claves no hay con qué cifrarlas: van tal cual están en la base de
	 * datos (como hasta 1.0.60, con aviso), en el diario privado del almacén, que se borra al terminar.
	 */
	public static function journal_snapshot( array $keep ) {
		return $keep;
	}
	/** Tras el SQL: vuelve la configuración de Backups del destino y se retira la que no sea suya. */
	public static function reapply( $keep ) {
		if ( ! is_array( $keep ) ) return;
		global $wpdb;
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT option_name FROM `' . $wpdb->prefix . 'options` WHERE option_name LIKE %s', $wpdb->esc_like( 'digitalisimo_backups_' ) . '%' ) ) as $name ) {
			$name = (string) $name;
			if ( self::kept( $name ) && ! array_key_exists( $name, $keep ) ) delete_option( $name );
		}
		foreach ( $keep as $name => $entry ) {
			if ( ! is_string( $name ) || ! self::kept( $name ) || ! is_array( $entry ) || ! array_key_exists( 'value', $entry ) ) continue;
			update_option( $name, $entry['value'], ! empty( $entry['autoload'] ) );
		}
	}
	/** Resumen público del bloque `extract` de un manifiesto (verify en REST y WP-CLI); null si no es un sitio extraído. */
	public static function summary( array $manifest ) {
		if ( self::SCOPE !== ( $manifest['scope'] ?? '' ) || ! is_array( $manifest['extract'] ?? null ) ) return null;
		$extract = $manifest['extract'];
		return array(
			'site' => (string) ( $extract['site']['label'] ?? '' ),
			'url' => (string) ( $extract['site']['url'] ?? '' ),
			'table_prefix' => (string) ( $extract['source_prefix'] ?? '' ),
			'users' => (array) ( $extract['users'] ?? array() ),
			'plugins' => array_values( (array) ( $extract['plugins']['active'] ?? array() ) ),
			'theme' => (string) ( $extract['themes']['stylesheet'] ?? '' ),
			'materialized' => (array) ( $extract['settings']['materialized'] ?? array() ),
			'secrets_withheld' => array_values( (array) ( $extract['settings']['secrets_withheld'] ?? array() ) ),
			'network_options' => array_values( (array) ( $extract['network_options'] ?? array() ) ),
			'excluded_tables' => (int) ( $extract['excluded_tables'] ?? 0 ),
			'excluded_files' => array_sum( array_map( 'intval', (array) ( $extract['files']['excluded'] ?? array() ) ) ),
			'mu_plugins' => array_values( (array) ( $extract['files']['mu_plugins'] ?? array() ) ),
			'drop_ins' => array_values( (array) ( $extract['files']['drop_ins'] ?? array() ) ),
			'blocked_users' => (int) ( $extract['users']['blocked'] ?? 0 ),
		);
	}
	/** Texto para quien restaura: con qué usuarios se entra después. */
	public static function restored_notice( array $manifest ) {
		$users = (array) ( $manifest['extract']['users'] ?? array() );
		return 'Sitio extraído de ' . (string) ( $manifest['extract']['site']['label'] ?? 'una red' ) . ' restaurado: sus usuarios entran con su contraseña de la red' . ( ! empty( $users['authors'] ) ? ' (los autores que no eran miembros no tienen contraseña ni rol)' : '' ) . '; las cuentas que tenía este WordPress ya no existen. Fuera de la red, administradores y editores pueden publicar HTML sin filtrar (unfiltered_html), que en la red era sólo del superadministrador: si no lo quieres, define DISALLOW_UNFILTERED_HTML en wp-config.php.';
	}
}

/**
 * Exportador fila a fila de un sitio (lo llama `Digitalisimo_Backups::database_sql_to_file()` dentro de su instantánea):
 * `prepare()` lee lo necesario antes de recorrer las tablas (con `MYSQLI_USE_RESULT` no se puede consultar mientras
 * se lee una tabla) y `rows()`/`finish()` deciden cada fila sin consultar nada.
 */
final class Digitalisimo_Backups_Site_Extract_Export {
	private $site;
	private $options;
	private $tables;
	private $uploads;
	private $base;
	private $prefix;
	private $users = array();
	private $own_suffixes = array();
	private $site_values = array();
	private $network_values = array();
	private $materialized = array( 'set' => array(), 'delete' => array(), 'report' => array( 'materialized' => array(), 'secrets' => array() ) );
	private $written = array();
	private $max_option = 0;
	private $max_meta = 0;
	private $option_columns = array( 'option_id', 'option_name', 'option_value', 'autoload' );
	private $report = array();
	private $active = array();
	private $network_active = array();
	private $blocked = 0;

	public function __construct( array $site, array $options, array $tables, array $uploads ) {
		global $wpdb;
		$this->site = $site;
		$this->options = $options;
		$this->tables = $tables;
		$this->uploads = $uploads;
		$this->base = (string) $wpdb->base_prefix;
		$this->prefix = (string) $wpdb->get_blog_prefix( $site['id'] );
	}
	public function tables() { return $this->tables; }
	/**
	 * Condición SQL de las filas que se leen de una tabla (null: todas). Usuarios y metas, sólo los del sitio: en una red
	 * grande no se recorren `wp_users` ni `wp_usermeta` enteras.
	 */
	public function where( $table ) {
		if ( $table !== $this->base . 'users' && $table !== $this->base . 'usermeta' ) return null;
		return ( $table === $this->base . 'users' ? '`ID`' : '`user_id`' ) . ' IN (' . self::in( $this->users ? array_keys( $this->users ) : array( 0 ) ) . ')';
	}
	/** Tablas que se leen además de las exportadas (para el bloqueo de lectura sin InnoDB). */
	public function read_tables() { return array( $this->base . 'sitemeta' ); }
	/** Nombre de cada tabla en el paquete: el prefijo del sitio también para usuarios y metas. */
	public function name( $table ) {
		if ( $table === $this->base . 'users' ) return $this->prefix . 'users';
		if ( $table === $this->base . 'usermeta' ) return $this->prefix . 'usermeta';
		return $table;
	}
	public function create( $table, $sql ) {
		$name = $this->name( $table );
		if ( $name === $table ) return $sql;
		return (string) preg_replace( '/^(\s*CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?)`' . preg_quote( str_replace( '`', '``', $table ), '/' ) . '`/i', '${1}`' . str_replace( '`', '``', $name ) . '`', $sql, 1 );
	}
	/** Una sola vez, ya dentro de la instantánea. */
	public function prepare() {
		global $wpdb;
		$users = $this->base . 'users';
		$usermeta = $this->base . 'usermeta';
		$options = $this->prefix . 'options';
		$network_id = (int) $this->site['network_id'];
		// Opciones del sitio que deciden algo (herencia, plugins, tema, medios).
		$rows = (array) $wpdb->get_results( 'SELECT option_name, option_value FROM `' . $options . '` WHERE option_name IN (' . self::in( Digitalisimo_Backups_Site_Extract::SITE_READS ) . ')', ARRAY_A );
		foreach ( $rows as $row ) $this->site_values[ (string) $row['option_name'] ] = self::value( $row['option_value'] );
		$this->max_option = (int) $wpdb->get_var( 'SELECT MAX(option_id) FROM `' . $options . '`' );
		// Ajustes de la red que el sitio usa, y los nombres de los demás `sitemeta` para el informe.
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT meta_key, meta_value FROM `' . $this->base . 'sitemeta` WHERE site_id = %d AND meta_key IN (' . self::in( Digitalisimo_Backups_Site_Extract::NETWORK_READS ) . ')', $network_id ), ARRAY_A );
		foreach ( $rows as $row ) $this->network_values[ (string) $row['meta_key'] ] = self::value( $row['meta_value'] );
		$network_keys = (array) $wpdb->get_col( $wpdb->prepare( 'SELECT meta_key FROM `' . $this->base . 'sitemeta` WHERE site_id = %d', $network_id ) );
		// Usuarios: miembros, superadministradores y autores.
		$members = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT user_id FROM `' . $usermeta . '` WHERE meta_key = %s', $this->prefix . 'capabilities' ) ) );
		$logins = array_values( array_filter( array_map( 'strval', (array) ( $this->network_values['site_admins'] ?? array() ) ), 'strlen' ) );
		$super = array();
		if ( $logins ) foreach ( (array) $wpdb->get_results( 'SELECT ID, user_email FROM `' . $users . '` WHERE user_login IN (' . self::in( $logins ) . ')', ARRAY_A ) as $row ) $super[ (int) $row['ID'] ] = (string) $row['user_email'];
		$authors = array_map( 'intval', (array) $wpdb->get_col( 'SELECT DISTINCT post_author FROM `' . $this->prefix . 'posts` WHERE post_author > 0' ) );
		if ( in_array( $this->prefix . 'comments', $this->tables, true ) ) $authors = array_merge( $authors, array_map( 'intval', (array) $wpdb->get_col( 'SELECT DISTINCT user_id FROM `' . $this->prefix . 'comments` WHERE user_id > 0' ) ) );
		$admin_emails = array_filter( array( strtolower( (string) ( $this->site_values['admin_email'] ?? '' ) ) ), 'strlen' );
		// Bloqueados en la red (spam o deleted): fuera de ella WordPress no lo comprueba. Por lotes, sólo entre los candidatos.
		$blocked = array();
		$candidates = array_values( array_unique( array_merge( $members, array_keys( $super ), $authors ) ) );
		foreach ( array_chunk( $candidates, 500 ) as $chunk ) {
			foreach ( array( 'spam', 'deleted' ) as $flag ) {
				$wpdb->suppress_errors( true );
				$ids = $wpdb->get_col( 'SELECT ID FROM `' . $users . '` WHERE ID IN (' . self::in( $chunk ) . ') AND ' . $flag . ' = 1' );
				$wpdb->suppress_errors( false );
				foreach ( (array) $ids as $id ) $blocked[ (int) $id ] = true;
			}
		}
		$this->users = Digitalisimo_Backups_Site_Extract::users( $members, $super, array_unique( $authors ), $admin_emails, (string) $this->options['super_admins'], array_keys( $blocked ) );
		$this->blocked = count( array_intersect_key( $blocked, array_flip( array_merge( $members, array_keys( $super ) ) ) ) );
		// Metas por sitio que cada usuario ya tiene: la del sitio principal con el mismo nombre no viaja. Primero los nombres
		// distintos del principal que no son de núcleo (pocos, con el índice de meta_key) y sólo después quién tiene esos
		// mismos en el sitio: la memoria no crece con el número de usuarios por cada meta del sitio.
		if ( 1 !== (int) $this->site['id'] ) {
			$suffixes = array();
			$movers = array_keys( array_filter( $this->users, function( $kind ) { return 'author' !== $kind; } ) );
			foreach ( array_chunk( $movers, 500 ) as $chunk ) {
				foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT meta_key FROM `' . $usermeta . '` WHERE user_id IN (' . self::in( $chunk ) . ') AND meta_key LIKE %s', $wpdb->esc_like( $this->base ) . '%' ) ) as $key ) {
					$rest = substr( (string) $key, strlen( $this->base ) );
					if ( '' !== $rest && ! preg_match( '/^[0-9]+_/', $rest ) && ! in_array( $rest, Digitalisimo_Backups_Site_Extract::SITE_USER_META, true ) ) $suffixes[ $this->prefix . $rest ] = true;
				}
			}
			$suffixes = array_keys( $suffixes );
			foreach ( array_chunk( $suffixes, 200 ) as $chunk ) {
				foreach ( (array) $wpdb->get_results( 'SELECT user_id, meta_key FROM `' . $usermeta . '` WHERE meta_key IN (' . self::in( $chunk ) . ') AND user_id IN (' . self::in( $movers ) . ')', ARRAY_A ) as $row ) {
					$this->own_suffixes[ (int) $row['user_id'] ][ substr( (string) $row['meta_key'], strlen( $this->prefix ) ) ] = true;
				}
			}
		}
		$this->max_meta = (int) $wpdb->get_var( 'SELECT MAX(umeta_id) FROM `' . $usermeta . '`' );
		// Plugins: los del sitio, los activados en la red y este mismo, que es quien restaura en el destino.
		$this->active = array_values( array_filter( array_map( 'strval', (array) ( $this->site_values['active_plugins'] ?? array() ) ), 'strlen' ) );
		$this->network_active = array_values( array_filter( array_map( 'strval', array_keys( (array) ( $this->network_values['active_sitewide_plugins'] ?? array() ) ) ), 'strlen' ) );
		$self = defined( 'DIGITALISIMO_BACKUPS_FILE' ) && function_exists( 'plugin_basename' ) ? (string) plugin_basename( DIGITALISIMO_BACKUPS_FILE ) : '';
		$active = array_values( array_unique( array_merge( $this->active, $this->network_active, '' !== $self ? array( $self ) : array() ) ) );
		sort( $active );
		$this->materialized = Digitalisimo_Backups_Site_Extract::materialize( $this->site_values, $this->network_values, ! empty( $this->options['network_secrets'] ), $active );
		$this->materialized['set']['active_plugins'] = $active;
		foreach ( array( 'upload_path', 'upload_url_path' ) as $name ) if ( '' !== trim( (string) ( is_scalar( $this->site_values[ $name ] ?? null ) ? $this->site_values[ $name ] : '' ) ) ) $this->materialized['set'][ $name ] = '';
		$kinds = array_count_values( $this->users );
		$this->report = array(
			'version' => 1,
			'site' => array( 'id' => (int) $this->site['id'], 'domain' => (string) $this->site['domain'], 'path' => (string) $this->site['path'], 'label' => (string) $this->site['label'], 'url' => (string) $this->site['url'], 'site_url' => untrailingslashit( (string) ( $this->site_values['siteurl'] ?? $this->site['url'] ) ), 'subdomain_install' => ! empty( $this->network_values['subdomain_install'] ), 'ms_files_rewriting' => ! empty( $this->network_values['ms_files_rewriting'] ) ),
			'source_prefix' => $this->prefix,
			'network_prefix' => $this->base,
			'uploads' => array( 'basedir' => $this->uploads['basedir'], 'baseurl' => $this->uploads['baseurl'], 'aliases' => array_values( (array) ( $this->uploads['aliases'] ?? array() ) ) ),
			'users' => array( 'policy' => (string) $this->options['super_admins'], 'total' => count( $this->users ), 'members' => (int) ( $kinds['member'] ?? 0 ), 'super_admins' => (int) ( $kinds['admin'] ?? 0 ), 'authors' => (int) ( $kinds['author'] ?? 0 ), 'super_admins_left' => count( array_diff_key( $super, array_filter( $this->users, function( $kind ) { return 'author' !== $kind; } ) ) ), 'blocked' => (int) $this->blocked ),
			'plugins' => array( 'active' => $active, 'site' => $this->active, 'network' => $this->network_active ),
			'themes' => array( 'stylesheet' => (string) ( $this->site_values['stylesheet'] ?? '' ), 'template' => (string) ( $this->site_values['template'] ?? '' ) ),
			'settings' => array( 'materialized' => $this->materialized['report']['materialized'], 'secrets_withheld' => $this->materialized['report']['secrets'], 'not_copied' => $this->materialized['report']['unknown'], 'inactive' => $this->materialized['report']['inactive'], 'network_secrets' => ! empty( $this->options['network_secrets'] ), 'reset' => array_values( array_intersect( array( 'upload_path', 'upload_url_path' ), array_keys( $this->materialized['set'] ) ) ) ),
			'network_options' => array_slice( Digitalisimo_Backups_Site_Extract::other_network_options( $network_keys ), 0, Digitalisimo_Backups_Site_Extract::REPORT_LIMIT ),
		);
		// Lo que sólo ve la red (historial): tablas sin número de sitio que no viajan. En una red grande no se listan.
		$tables = (int) ( $this->network_values['blog_count'] ?? 0 ) > Digitalisimo_Backups_Site_Extract::LIST_TABLES_UP_TO ? null : Digitalisimo_Backups_Site_Extract::global_plugin_tables( $this->site['id'] );
		$this->report['internal'] = array( 'excluded_tables' => null === $tables ? null : array_slice( $tables, 0, Digitalisimo_Backups_Site_Extract::REPORT_LIMIT ), 'excluded_tables_count' => null === $tables ? 0 : count( $tables ) );
	}
	public function report() { return $this->report; }
	/** Filas del paquete para una fila de origen (ninguna si no viaja). */
	public function rows( $table, array $row ) {
		if ( $table === $this->base . 'users' ) {
			$id = (int) ( $row['ID'] ?? 0 );
			if ( ! isset( $this->users[ $id ] ) ) return array();
			// Un autor que no viaja como miembro: sólo lo que pinta su autoría (usuario y nombre público). Sin contraseña,
			// correo, web ni clave de activación: su contenido conserva el autor, pero no puede entrar.
			if ( 'author' === $this->users[ $id ] ) foreach ( array( 'user_pass', 'user_activation_key', 'user_email', 'user_url' ) as $column ) if ( array_key_exists( $column, $row ) ) $row[ $column ] = '';
			return array( $row );
		}
		if ( $table === $this->base . 'usermeta' ) {
			$id = (int) ( $row['user_id'] ?? 0 );
			if ( ! isset( $this->users[ $id ] ) ) return array();
			$key = Digitalisimo_Backups_Site_Extract::user_meta_key( (string) ( $row['meta_key'] ?? '' ), $this->users[ $id ], (int) $this->site['id'], $this->base, $this->own_suffixes[ $id ] ?? array() );
			if ( null === $key ) return array();
			$row['meta_key'] = $key;
			return array( $row );
		}
		if ( $table === $this->prefix . 'options' ) {
			$name = (string) ( $row['option_name'] ?? '' );
			$this->option_columns = array_keys( $row );
			// La configuración de Backups de la red (destinos, historial, automatizaciones) no es del sitio.
			if ( 0 === strpos( $name, 'digitalisimo_backups_' ) || in_array( $name, $this->materialized['delete'], true ) ) return array();
			if ( array_key_exists( $name, $this->materialized['set'] ) ) {
				$this->written[ $name ] = true;
				$row['option_value'] = self::store( $this->materialized['set'][ $name ] );
			}
			return array( $row );
		}
		return array( $row );
	}
	/** Filas nuevas al final de una tabla: ajustes materializados que el sitio no tenía y el rol de los superadministradores. */
	public function finish( $table ) {
		$out = array();
		if ( $table === $this->prefix . 'options' ) {
			foreach ( $this->materialized['set'] as $name => $value ) {
				if ( isset( $this->written[ $name ] ) || in_array( $name, array( 'upload_path', 'upload_url_path' ), true ) ) continue;
				$row = array();
				foreach ( $this->option_columns as $column ) $row[ $column ] = null;
				$row['option_id'] = (string) ++$this->max_option;
				$row['option_name'] = $name;
				$row['option_value'] = self::store( $value );
				if ( array_key_exists( 'autoload', $row ) ) $row['autoload'] = 'yes';
				$out[] = $row;
			}
		}
		if ( $table === $this->base . 'usermeta' ) {
			foreach ( $this->users as $id => $kind ) {
				if ( 'admin' !== $kind ) continue;
				$out[] = array( 'umeta_id' => (string) ++$this->max_meta, 'user_id' => (string) $id, 'meta_key' => $this->prefix . 'capabilities', 'meta_value' => serialize( array( 'administrator' => true ) ) );
				$out[] = array( 'umeta_id' => (string) ++$this->max_meta, 'user_id' => (string) $id, 'meta_key' => $this->prefix . 'user_level', 'meta_value' => '10' );
			}
		}
		return $out;
	}

	/** Valor de una opción leída de esta misma base de datos, sin instanciar objetos. */
	public static function value( $raw ) {
		if ( ! is_string( $raw ) ) return $raw;
		if ( function_exists( 'is_serialized' ) ? ! is_serialized( $raw ) : ! preg_match( '/^(?:a|s|i|d|b|N|O|C):/', $raw ) ) return $raw;
		$value = @unserialize( $raw, array( 'allowed_classes' => false ) );
		return false === $value && 'b:0;' !== $raw ? $raw : $value;
	}
	/** Como maybe_serialize(): matrices serializadas; un escalar, como texto (true es «1»). */
	private static function store( $value ) { return is_array( $value ) || is_object( $value ) ? serialize( $value ) : (string) $value; }
	private static function in( array $values ) {
		global $wpdb;
		return implode( ', ', array_map( function( $value ) use ( $wpdb ) { return $wpdb->prepare( '%s', (string) $value ); }, $values ) );
	}
}

// Ganchos propios, al cargar el archivo: así el alta no toca Digitalisimo_Backups::init() ni el motor de la cola.
if ( function_exists( 'add_action' ) ) add_action( 'admin_post_' . Digitalisimo_Backups_Site_Extract::ACTION, array( 'Digitalisimo_Backups', 'extract_request' ) );
if ( function_exists( 'add_filter' ) ) add_filter( 'digitalisimo_backups_job_origin', array( 'Digitalisimo_Backups_Site_Extract', 'job_origin' ), 10, 2 );
