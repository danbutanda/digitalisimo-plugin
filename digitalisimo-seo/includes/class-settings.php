<?php
defined( 'ABSPATH' ) || exit;

class Digitalisimo_Integrations_Settings {
	const OPTION = 'digitalisimo_integrations_options';
	const TEMPLATE_DEFAULTS_MIGRATION = 'digitalisimo_seo_template_defaults_1_0_122';

	/** Máscara mostrada en lugar de un secreto ya guardado. */
	const MASK = '••••••••••••';
	/** Registro de la limpieza única de la opción de red (clean_network_secrets()). */
	const NETWORK_CLEANED = 'digitalisimo_seo_network_cleanup_2';

	public static function defaults() {
		return array(
			'enable_seo'             => 1,
			'enable_woocommerce'     => 1,
			'enable_ipinfo'          => 0,
			'sitemap_enabled'        => 1,
			'sitemap_post_types'     => 'auto',
			'sitemap_exclude_ids'    => '',
			'seo_robots_rules'       => '',
			'keyword_post_types'     => 'all',
			'keyword_max_count'      => 5,
			'keyword_auto_from_title'=> 0,
			'noindex_search'         => 1,
			'noindex_authors'        => 1,
			'noindex_empty_tags'     => 1,
			'noindex_date_archives'  => 1,
			'noindex_attachments'    => 1,
			'noindex_elementor'      => 1,
			'noindex_woo_pages'      => 1,
			'seo_noindex_unresolved' => 0,
			'noindex_page_slugs'     => '',
			'noindex_page_ids'       => '',
			'openai_api_key'         => '',
			'openai_model'           => 'gpt-4o-mini',
			'ai_providers'           => array(),
			'ai_profile_provider'    => 'openai',
			'ai_profile_model'       => 'gpt-4o-mini',
			'ai_profile_fallback'    => '',
			'namecheap_api_user'     => '',
			'namecheap_username'     => '',
			'namecheap_api_key'      => '',
			'namecheap_client_ip'    => '',
			'domain_products'        => ".com|\n.mx|\n.org|",
			'hosting_product_ids'    => '',
			'hosting_category_ids'   => '',
			'require_domain_hosting' => 0,
			'replace_hosting'        => 0,
			'simplify_checkout'      => 0,
			'ipinfo_tokens'          => '',
			'ipinfo_cache_minutes'   => 60,
			'seo_site_name'          => '',
			'seo_separator'          => '|',
			'seo_organization_type'  => 'Organization',
			'seo_organization_name'  => '',
			'seo_organization_url'   => '',
			'seo_organization_email' => '',
			'seo_logo'               => '',
			'seo_default_image'      => '',
			'seo_default_image_alt'  => '',
			'seo_favicon_source'     => '',
			'seo_alt_optimize'       => 1,
			'seo_alt_keyword_mode'   => 'contextual',
			'seo_alt_keyword'        => '',
			'seo_knowledge_urls'     => '',
			'seo_title_post'         => '%title% %sep% %sitename%',
			'seo_description_post'   => '%excerpt%',
			'seo_title_page'         => '%title%',
			'seo_description_page'   => '%excerpt%',
			'seo_title_archive'      => '%title%',
			'seo_description_archive'=> '%excerpt%',
			'seo_title_home'         => '',
			'seo_description_home'   => '',
			'seo_title_author'       => '',
			'seo_description_author' => '',
			'seo_title_taxonomy'     => '%term% %sep% %sitename%',
			'seo_description_taxonomy'=> '%description%',
			'seo_schema_enabled'     => 1,
			'seo_schema_unify'       => 1,
			'seo_breadcrumbs'        => 1,
			'seo_open_graph'         => 1,
			'seo_twitter_card'       => 'summary_large_image',
			'seo_twitter_enabled'    => 1,
			'seo_twitter_user'       => '',
			'seo_local_enabled'      => 0,
			'seo_local_type'         => 'LocalBusiness',
			'seo_local_name'         => '',
			'seo_local_phone'        => '',
			'seo_local_address'      => '',
			'seo_local_city'         => '',
			'seo_local_region'       => '',
			'seo_local_postal'       => '',
			'seo_local_country'      => 'MX',
			'seo_local_latitude'     => '',
			'seo_local_longitude'    => '',
			'seo_local_hours'        => '',
			'seo_local_price_range'  => '',
			'seo_redirect_attachments' => 1,
			'seo_mobile_prevent_horizontal_scroll' => 0,
			'perf_safe_mode'       => 1,
			'perf_gutenberg'       => 1,
			'perf_emojis'          => 0,
			'perf_embeds'          => 0,
			'perf_dashicons'       => 0,
			'perf_font_swap'       => 0,
			'perf_font_guard_mode' => 'off',
			'perf_font_guard_allowlist' => '',
			'perf_font_icon_families' => '',
			'perf_css_defer'       => 0,
			'perf_css_defer_exclusions' => '',
			'perf_hero_critical'   => 1,
			'perf_css_defer_handles' => '',
			'perf_caption_normal'  => 0,
			'perf_custom_css'      => '',
			'perf_custom_css_paused' => 0,
			'perf_preload_mode'    => 'off',
			'perf_preload_paths'   => '',
			'perf_preload_limit'   => 2,
			'perf_tracking_enabled' => 0,
			'perf_gt_id'           => '',
			'perf_ga4_id'          => '',
			'perf_gtm_id'          => '',
			'perf_tracking_mode'   => 'normal',
			'perf_tracking_delay'  => 12,
			'perf_tracking_fallback' => 12,
			'perf_img_enabled'     => 0,
			'perf_img_srcset'      => 1,
			'perf_img_sizes'       => 1,
			'perf_img_dimensions'  => 1,
			'perf_img_lazy'        => 1,
			'perf_img_backgrounds' => 0,
			'perf_img_mode'        => 'safe',
			'perf_img_exclusions'  => '',
			'perf_js_jquery_mode'  => 'off',
			'perf_touch_guard'     => 0,
			'perf_touch_selectors' => ".elementor-social-icon\n.swiper-pagination-bullet",
			'seo_taxonomies'         => 'category,post_tag',
			'seo_sitemap_taxonomies' => 'category',
			'seo_sitemap_images'     => 1,
			'seo_redirect_enabled'   => 1,
			'seo_redirect_slug_watch'=> 1,
			'seo_redirect_default_code' => 301,
			'seo_redirect_fallback_url' => '',
			'seo_404_enabled'        => 0,
			'seo_404_limit'          => 1000,
			'seo_404_retention'      => 30,
			'seo_404_exclude'        => '',
			'seo_404_ignore_query'   => 1,
			'seo_verify_google'      => '',
			'seo_verify_bing'        => '',
			'seo_verify_yandex'      => '',
			'seo_verify_baidu'       => '',
			'seo_verify_pinterest'   => '',
			'seo_verify_norton'      => '',
			'seo_verify_custom'      => '',
			'seo_noindex_paginated'  => 0,
			'seo_noindex_password'   => 0,
			'seo_max_snippet'        => '',
			'seo_max_video_preview'  => '',
			'seo_disable_author_archives' => 0,
			'seo_disable_date_archives'   => 0,
			'seo_breadcrumbs_separator'   => '›',
			'seo_breadcrumbs_show_home'   => 1,
			'seo_breadcrumbs_home_label'  => 'Inicio',
			'seo_breadcrumbs_prefix'      => '',
			'seo_breadcrumbs_hide_current'=> 0,
			'seo_breadcrumbs_taxonomy'    => 1,
			'seo_breadcrumbs_blog_page'   => 1,
			'seo_breadcrumbs_archive_format' => 'Archivo de %s',
			'seo_breadcrumbs_search_format'  => 'Resultados de «%s»',
			'seo_breadcrumbs_404_label'   => 'Página no encontrada',
			'seo_type_templates'          => array(),
			'seo_tax_templates'           => array(),
			'seo_schema_type_defaults'    => array(),
			'seo_schema_templates'        => array(),
			'seo_product_brand'           => '',
			'seo_product_brand_name'      => '',
			'seo_product_condition'       => '',
			'seo_sitemap_news_enabled'    => 0,
			'seo_links_counter'           => 1,
			'seo_links_check'             => 0,
			'seo_noindex_hidden_products' => 0,
			'seo_wc_remove_generator'     => 0,
			'seo_strip_product_base'      => 0,
			'seo_strip_product_cat_base'  => 0,
			'seo_strip_category_base'     => 0,
			'seo_avatar_alt'              => 0,
			'seo_rss_before'              => '',
			'seo_rss_after'               => '',
			'seo_sitemap_news_types'      => 'post',
			'seo_sitemap_news_name'       => '',
			'seo_sitemap_video_enabled'   => 0,
			'seo_sitemap_news_exclude_terms' => '',
			'seo_ai_enabled'         => 1,
			'seo_ai_search_default'  => 'allow',
			'seo_ai_training_default'=> 'allow',
			'seo_ai_google_extended_policy' => 'allow',
			'seo_ai_crawler_rules'  => '',
			'seo_ai_crawler_preset' => 'visibility',
			'seo_ai_crawlers'        => '',
			'seo_ai_llms_enabled'    => 0,
			'seo_ai_llms_mode'       => 'automatic',
			'seo_ai_llms_manual'     => '',
			'seo_ai_llms_urls'       => '',
			'seo_ai_indexnow_enabled'=> 0,
			'seo_ai_indexnow_key'    => '',
			'seo_ai_indexnow_auto'   => 1,
			'seo_ai_referrals_enabled'=> 0,
			'seo_ai_referral_retention'=> 90,
			'seo_hide_login_slug'    => '',
			'seo_social_profiles'    => array(),
			'seo_local_phone_cc'     => '',
			'seo_local_schedule'     => array(),
			'content_editorial_voice' => 'Tono amigable, cercano y experto en tecnología. Explica con claridad, usa español de México y evita promesas de posicionamiento garantizado.',
			'content_editorial_context' => 'Digitalisimo es una agencia digital mexicana. Combina SEO, marketing, desarrollo web, automatización e inteligencia digital para ayudar a empresas a crecer.',
			'content_editorial_cta' => 'Cierra con una invitación útil a solicitar una asesoría con Digitalisimo, relacionada de forma natural con el tema del artículo.',
			'content_editorial_method' => 'Responde la intención de búsqueda al inicio. No repitas el H1 en el cuerpo. Usa una keyword principal de forma natural y secundarias sólo cuando aporten contexto. Organiza con H2 y H3, ejemplos, preguntas frecuentes y enlaces internos reales. Puedes abrir con un diálogo breve entre 🧑 Empresario, 🤖 MaryIA y 👨‍💼 Daniel; MaryIA habla con precisión robótica y Daniel aterriza decisiones. El diálogo aparece una sola vez al inicio.',
			'default_cluster_pillar' => 0,
			'default_content_location' => 0,
		);
	}
	/** Plantillas donde un valor vacío significa restaurar el comportamiento inicial. */
	/** Plantillas de título y descripción globales (RM10). */
	public static function template_keys() { return array( 'seo_title_post', 'seo_description_post', 'seo_title_page', 'seo_description_page', 'seo_title_archive', 'seo_description_archive', 'seo_title_taxonomy', 'seo_description_taxonomy', 'seo_title_home', 'seo_description_home', 'seo_title_author', 'seo_description_author' ); }

	/**
	 * Sanea una plantilla sin romper sus variables: sanitize_text_field() quita los «%xx» hexadecimales
	 * como si fueran octetos y convertiría %description% en «scription%» y %date% en «te%».
	 */
	public static function sanitize_template( $value, $multiline = false ) {
		$vars  = array();
		$value = preg_replace_callback( '/%[a-z_]+(?:\([^)%<>]*\))?%/i', function( $m ) use ( &$vars ) { $vars[] = $m[0]; return 'DIGITALISIMOVAR' . ( count( $vars ) - 1 ) . 'X'; }, is_scalar( $value ) ? (string) $value : '' );
		$value = $multiline ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
		return preg_replace_callback( '/DIGITALISIMOVAR(\d+)X/', function( $m ) use ( $vars ) { return $vars[ (int) $m[1] ] ?? ''; }, $value );
	}

	public static function template_default_keys() { return array( 'seo_title_post', 'seo_description_post', 'seo_title_page', 'seo_description_page', 'seo_title_archive', 'seo_description_archive' ); }

	public static function init() {
		// En Multisite el plugin puede activarse para toda la red, pero cada sitio
		// conserva sus propios ajustes mediante get_option()/update_option().
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'migrate_template_defaults' ), 1 );
		add_action( 'admin_init', array( __CLASS__, 'ensure_autoload' ), 2 );
		add_action( 'admin_init', array( __CLASS__, 'clean_network_secrets' ), 3 );
		add_action( 'admin_init', array( __CLASS__, 'forget_ability_switches' ), 1 );
	}

	/**
	 * La opción principal (≈8 KB) y el estado del favicon se leen en cada petición (escritorio, REST y front; en una red,
	 * además por cada sitio del menú «Mis sitios»). Varias rutas los guardaban sin autoload, una consulta más en cada
	 * petición de ese sitio: ahora se guardan con autoload y lo ya guardado se corrige una vez por sitio (WordPress 6.4+).
	 */
	public static function ensure_autoload() {
		if ( ! function_exists( 'wp_set_option_autoload_values' ) ) return;
		$all = wp_load_alloptions(); $fix = array();
		foreach ( array( self::OPTION, 'digitalisimo_favicon_state' ) as $name ) if ( ! array_key_exists( $name, $all ) && false !== get_option( $name, false ) ) $fix[ $name ] = true;
		if ( $fix ) wp_set_option_autoload_values( $fix );
	}

	/** Rellena una vez las plantillas que versiones previas dejaron vacías. */
	public static function migrate_template_defaults() {
		$keys = self::template_default_keys(); $defaults = self::defaults();
		if ( ! get_option( self::TEMPLATE_DEFAULTS_MIGRATION ) ) { $site = (array) get_option( self::OPTION, array() ); foreach ( $keys as $key ) if ( ! array_key_exists( $key, $site ) || '' === trim( (string) $site[ $key ] ) ) $site[ $key ] = $defaults[ $key ]; update_option( self::OPTION, $site, true ); update_option( self::TEMPLATE_DEFAULTS_MIGRATION, 1, false ); }
		if ( is_multisite() && is_network_admin() && ! get_site_option( self::TEMPLATE_DEFAULTS_MIGRATION ) ) { $network = (array) get_site_option( self::OPTION, array() ); foreach ( $keys as $key ) if ( ! array_key_exists( $key, $network ) || '' === trim( (string) $network[ $key ] ) ) $network[ $key ] = $defaults[ $key ]; update_site_option( self::OPTION, $network ); update_site_option( self::TEMPLATE_DEFAULTS_MIGRATION, 1 ); }
	}

	/** Interruptores retirados de la escritura SEO por MCP (el único de antes y sus cuatro partes de la tanda B). */
	public static function legacy_ability_keys() { return array( 'seo_abilities_write', 'seo_abilities_write_meta', 'seo_abilities_write_schema', 'seo_abilities_write_index', 'seo_abilities_write_redirects' ); }

	/**
	 * Decisión del dueño del 2026-11-27: el MCP trae acceso total, sin interruptores. Las abilities de escritura ya no
	 * leen `seo_abilities_write` ni sus cuatro partes; aquí se borran, una vez y sin tocar nada más, del sitio, de su mapa
	 * de herencia y de la red. Idempotente: sin esas claves no escribe nada.
	 */
	public static function forget_ability_switches() {
		$keys  = self::legacy_ability_keys();
		$strip = function( $values ) use ( $keys ) {
			if ( ! is_array( $values ) || ! array_intersect( $keys, array_keys( $values ) ) ) return null;
			return array_diff_key( $values, array_flip( $keys ) );
		};
		$site = $strip( get_option( self::OPTION, array() ) );
		if ( null !== $site ) update_option( self::OPTION, $site );
		if ( ! is_multisite() ) return;
		$inherit = $strip( get_option( 'digitalisimo_seo_network_inherit', array() ) );
		if ( null !== $inherit ) update_option( 'digitalisimo_seo_network_inherit', $inherit );
		$network = $strip( get_site_option( self::OPTION, array() ) );
		if ( null !== $network ) update_site_option( self::OPTION, $network );
	}

	public static function get( $key = null, $default = null ) {
		if ( null !== $key && class_exists( 'Digitalisimo_Integrations_SEO_Resolver' ) ) return Digitalisimo_Integrations_SEO_Resolver::option( $key, $default );
		$stored  = get_option( self::OPTION, array() );
		return wp_parse_args( $stored, self::defaults() );
	}

	public static function admin_menu() {
		$pages = array( 'digitalisimo-seo-settings' => array( 'SEO', 'settings_page' ), 'digitalisimo-keyword-settings' => array( 'Ajustes de keywords', 'keyword_settings_page' ), 'digitalisimo-sitemap-settings' => array( 'Ajustes de sitemap', 'sitemap_settings_page' ), 'digitalisimo-indexing-settings' => array( 'Ajustes de indexación', 'indexing_settings_page' ) );
		foreach ( $pages as $slug => $page ) add_submenu_page( null, __( $page[0], 'digitalisimo-integrations' ), __( $page[0], 'digitalisimo-integrations' ), 'manage_options', $slug, array( __CLASS__, $page[1] ) );
	}

	/**
	 * La ruta privada de acceso, la redirección de adjuntos y el bloqueo del
	 * desplazamiento móvil pasaron a DIGITALÍSIMO Tools. Mientras Tools no esté
	 * activo, SEO las sigue aplicando con los valores guardados para no abrir
	 * wp-login.php ni cambiar el sitio al actualizar.
	 */
	public static function tools_handles_site_options() {
		static $handled = null;
		if ( null !== $handled ) return $handled;
		$file   = 'digitalisimo-tools/digitalisimo-tools.php';
		$active = in_array( $file, (array) get_option( 'active_plugins', array() ), true ) || ( is_multisite() && isset( ( (array) get_site_option( 'active_sitewide_plugins', array() ) )[ $file ] ) );
		return $handled = $active && file_exists( WP_PLUGIN_DIR . '/digitalisimo-tools/modules/site-options/class-site-options.php' );
	}

	public static function register_settings() {
		register_setting( 'digitalisimo_integrations', self::OPTION, array( __CLASS__, 'sanitize' ) );
	}

	public static function sanitize( $input ) {
		// WordPress aplica este filtro (sanitize_option_{opción}) a toda escritura de la opción, también a la de red:
		// update_site_option() lo llama, y el resultado se armaba sobre las opciones del sitio en curso, así que guardar
		// Red → SEO copiaba a la red los ajustes y las claves del sitio principal (enable_seo, OpenAI, proveedores…) y los
		// heredaban todos los sitios. Las escrituras del código (red, SEO AI, importaciones) ya llegan saneadas y completas:
		// sólo el envío parcial del formulario del sitio (options.php) se completa y se sanea aquí.
		if ( ! self::is_form_submission() ) return $input;
		$current = (array) get_option( self::OPTION, array() );
		$output  = $current;
		foreach ( array( 'enable_seo', 'enable_woocommerce', 'enable_ipinfo', 'sitemap_enabled', 'keyword_auto_from_title', 'noindex_search', 'noindex_authors', 'noindex_empty_tags', 'noindex_date_archives', 'noindex_attachments', 'noindex_elementor', 'noindex_woo_pages', 'seo_noindex_unresolved', 'require_domain_hosting', 'replace_hosting', 'simplify_checkout', 'seo_schema_enabled', 'seo_schema_unify', 'seo_breadcrumbs', 'seo_open_graph', 'seo_twitter_enabled', 'seo_local_enabled', 'seo_redirect_attachments', 'seo_mobile_prevent_horizontal_scroll', 'seo_sitemap_images', 'seo_redirect_enabled', 'seo_redirect_slug_watch', 'seo_404_enabled', 'seo_404_ignore_query', 'seo_noindex_paginated', 'seo_noindex_password', 'seo_disable_author_archives', 'seo_disable_date_archives', 'seo_breadcrumbs_show_home', 'seo_breadcrumbs_hide_current', 'seo_breadcrumbs_taxonomy', 'seo_breadcrumbs_blog_page', 'seo_sitemap_news_enabled', 'seo_sitemap_video_enabled', 'seo_links_counter', 'seo_links_check', 'seo_noindex_hidden_products', 'seo_wc_remove_generator', 'seo_strip_product_base', 'seo_strip_product_cat_base', 'seo_strip_category_base', 'seo_avatar_alt', 'perf_safe_mode', 'perf_gutenberg', 'perf_emojis', 'perf_embeds', 'perf_dashicons', 'perf_font_swap', 'perf_css_defer', 'perf_hero_critical', 'perf_caption_normal', 'perf_custom_css_paused', 'perf_tracking_enabled', 'perf_img_enabled', 'perf_img_srcset', 'perf_img_sizes', 'perf_img_dimensions', 'perf_img_lazy', 'perf_img_backgrounds', 'perf_touch_guard', 'seo_ai_llms_enabled', 'seo_alt_optimize' ) as $key ) {
			if ( isset( $input[ $key ] ) ) $output[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}
		// Cada pestaña envía solo sus propios campos: un campo ausente conserva su valor,
		// nunca se vacía. De lo contrario, guardar una pestaña borraría las demás.
		foreach ( array( 'openai_model', 'namecheap_api_user', 'namecheap_username', 'namecheap_client_ip', 'seo_site_name', 'seo_separator', 'seo_organization_type', 'seo_organization_name', 'seo_organization_url', 'seo_organization_email', 'seo_default_image_alt', 'seo_twitter_card', 'seo_twitter_user', 'seo_local_name', 'seo_local_city', 'seo_local_region', 'seo_local_postal', 'seo_local_country', 'seo_local_latitude', 'seo_local_longitude', 'seo_local_price_range' ) as $key ) {
			if ( isset( $input[ $key ] ) ) $output[ $key ] = sanitize_text_field( $input[ $key ] );
		}
		foreach ( array( 'domain_products', 'hosting_product_ids', 'hosting_category_ids', 'ipinfo_tokens', 'sitemap_exclude_ids', 'keyword_post_types', 'noindex_page_slugs', 'noindex_page_ids', 'seo_title_post', 'seo_description_post', 'seo_title_page', 'seo_description_page', 'seo_title_archive', 'seo_description_archive', 'seo_title_taxonomy', 'seo_description_taxonomy', 'seo_local_address', 'seo_local_hours', 'seo_taxonomies', 'content_editorial_voice', 'content_editorial_context', 'content_editorial_cta', 'content_editorial_method' ) as $key ) {
			if ( isset( $input[ $key ] ) ) $output[ $key ] = sanitize_textarea_field( $input[ $key ] );
		}
		foreach ( array( 'sitemap_post_types', 'seo_sitemap_taxonomies', 'seo_sitemap_news_types' ) as $key ) if ( isset( $input[ $key ] ) ) $output[ $key ] = Digitalisimo_Integrations_SEO_Suite::sanitize_sitemap_list( $input[ $key ] );
		if ( isset( $input['seo_organization_type'] ) && ! array_key_exists( $output['seo_organization_type'], Digitalisimo_Integrations_SEO_Suite::entity_type_choices() ) ) $output['seo_organization_type'] = 'Organization';
		if ( isset( $input['seo_separator'] ) && ! array_key_exists( $output['seo_separator'], Digitalisimo_Integrations_SEO_Suite::separator_choices() ) ) $output['seo_separator'] = '|';
		if ( isset( $input['seo_twitter_card'] ) && ! array_key_exists( $output['seo_twitter_card'], Digitalisimo_Integrations_SEO_Suite::twitter_card_choices() ) ) $output['seo_twitter_card'] = 'summary_large_image';
		// Sólo tipos oficiales de Schema.org: un texto libre publicaría un @type inventado.
		if ( isset( $input['seo_local_type'] ) ) $output['seo_local_type'] = Digitalisimo_Integrations_Schema_Vocabulary::local_type( sanitize_text_field( $input['seo_local_type'] ) );
		if ( isset( $input['seo_robots_rules'] ) ) $output['seo_robots_rules'] = Digitalisimo_Integrations_Robots::sanitize( $input['seo_robots_rules'] );
		// Redirecciones, 404, verificación y robots avanzados (docs/SEO_RANKMATH_OBJECTIVE.md): las mismas reglas que save_network().
		if ( isset( $input['seo_redirect_default_code'] ) ) $output['seo_redirect_default_code'] = Digitalisimo_Integrations_Redirections::sanitize_code( $input['seo_redirect_default_code'] );
		if ( isset( $input['seo_redirect_fallback_url'] ) ) $output['seo_redirect_fallback_url'] = Digitalisimo_Integrations_Redirections::sanitize_target( $input['seo_redirect_fallback_url'] );
		if ( isset( $input['seo_404_limit'] ) ) $output['seo_404_limit'] = Digitalisimo_Integrations_404_Monitor::sanitize_limit( $input['seo_404_limit'] );
		if ( isset( $input['seo_404_retention'] ) ) $output['seo_404_retention'] = Digitalisimo_Integrations_404_Monitor::sanitize_retention( $input['seo_404_retention'] );
		if ( isset( $input['seo_404_exclude'] ) ) $output['seo_404_exclude'] = Digitalisimo_Integrations_404_Monitor::sanitize_exclusions( $input['seo_404_exclude'] );
		foreach ( array_keys( Digitalisimo_Integrations_Verification::engines() ) as $key ) if ( isset( $input[ $key ] ) ) $output[ $key ] = Digitalisimo_Integrations_Verification::sanitize_code( $input[ $key ] );
		if ( isset( $input['seo_verify_custom'] ) ) $output['seo_verify_custom'] = Digitalisimo_Integrations_Verification::sanitize_custom( $input['seo_verify_custom'] );
		foreach ( array( 'seo_max_snippet', 'seo_max_video_preview' ) as $key ) if ( isset( $input[ $key ] ) ) $output[ $key ] = Digitalisimo_Integrations_SEO_Suite::sanitize_robots_number( $input[ $key ] );
		// Migas y plantillas por tipo (RM13 y RM10).
		foreach ( Digitalisimo_Integrations_SEO_Suite::breadcrumb_text_keys() as $key ) if ( isset( $input[ $key ] ) ) $output[ $key ] = sanitize_text_field( $input[ $key ] );
		if ( isset( $input['seo_schema_type_defaults'] ) ) $output['seo_schema_type_defaults'] = Digitalisimo_Integrations_Schema_Graph::sanitize_type_defaults( $input['seo_schema_type_defaults'] );
		if ( isset( $input['seo_schema_templates'] ) ) $output['seo_schema_templates'] = Digitalisimo_Integrations_Schema_Graph::sanitize_templates( $input['seo_schema_templates'] );
		foreach ( array( 'seo_rss_before', 'seo_rss_after' ) as $key ) if ( isset( $input[ $key ] ) ) $output[ $key ] = Digitalisimo_Integrations_SEO_Feed::sanitize( $input[ $key ] );
		if ( isset( $input['seo_sitemap_news_name'] ) ) $output['seo_sitemap_news_name'] = sanitize_text_field( $input['seo_sitemap_news_name'] );
		if ( isset( $input['seo_sitemap_news_exclude_terms'] ) ) $output['seo_sitemap_news_exclude_terms'] = Digitalisimo_Integrations_Sitemap::sanitize_ids( $input['seo_sitemap_news_exclude_terms'] );
		if ( isset( $input['seo_product_brand'] ) ) $output['seo_product_brand'] = sanitize_key( $input['seo_product_brand'] );
		if ( isset( $input['seo_product_brand_name'] ) ) $output['seo_product_brand_name'] = Digitalisimo_Integrations_Schema_Graph::sanitize_brand_name( $input['seo_product_brand_name'] );
		if ( isset( $input['seo_product_condition'] ) ) $output['seo_product_condition'] = Digitalisimo_Integrations_Schema_Graph::sanitize_condition( $input['seo_product_condition'] );
		if ( isset( $input['seo_tax_templates'] ) ) $output['seo_tax_templates'] = Digitalisimo_Integrations_SEO_Suite::sanitize_tax_templates( $input['seo_tax_templates'] );
		if ( isset( $input['seo_type_templates'] ) ) $output['seo_type_templates'] = Digitalisimo_Integrations_SEO_Suite::sanitize_type_templates( $input['seo_type_templates'] );
		if ( isset( $input['seo_ai_google_extended_policy'] ) ) $output['seo_ai_google_extended_policy'] = Digitalisimo_Integrations_SEO_AI::sanitize_google_extended_policy( $input['seo_ai_google_extended_policy'] );
		if ( isset( $input['perf_css_defer_handles'] ) ) $output['perf_css_defer_handles'] = Digitalisimo_Integrations_Performance_CSS::sanitize_handles( $input['perf_css_defer_handles'] );
		if ( isset( $input['perf_css_defer_exclusions'] ) ) $output['perf_css_defer_exclusions'] = Digitalisimo_Integrations_Performance_CSS::sanitize_exclusions( $input['perf_css_defer_exclusions'] );
		if ( isset( $input['perf_custom_css'] ) ) $output['perf_custom_css'] = Digitalisimo_Integrations_Performance_CSS::sanitize_custom_css( $input['perf_custom_css'] );
		if ( isset( $input['perf_font_guard_mode'] ) ) $output['perf_font_guard_mode'] = Digitalisimo_Integrations_Performance_Font_Guard::sanitize_mode( $input['perf_font_guard_mode'] );
		if ( isset( $input['perf_font_guard_allowlist'] ) ) $output['perf_font_guard_allowlist'] = Digitalisimo_Integrations_Performance_Font_Guard::sanitize_allowlist( $input['perf_font_guard_allowlist'] );
		if ( isset( $input['perf_js_jquery_mode'] ) ) $output['perf_js_jquery_mode'] = Digitalisimo_Integrations_Performance_JavaScript::sanitize_mode( $input['perf_js_jquery_mode'] );
		if ( isset( $input['perf_img_mode'] ) ) $output['perf_img_mode'] = Digitalisimo_Integrations_Performance_Images::sanitize_mode( $input['perf_img_mode'] );
		if ( isset( $input['seo_alt_keyword_mode'] ) ) $output['seo_alt_keyword_mode'] = Digitalisimo_Integrations_Image_Alt::sanitize_mode( $input['seo_alt_keyword_mode'] );
		if ( isset( $input['seo_alt_keyword'] ) ) $output['seo_alt_keyword'] = Digitalisimo_Integrations_Image_Alt::sanitize_keyword( $input['seo_alt_keyword'] );
		if ( isset( $input['perf_touch_selectors'] ) ) $output['perf_touch_selectors'] = Digitalisimo_Integrations_Quality_Audit::sanitize_selectors( $input['perf_touch_selectors'] );
		if ( isset( $input['seo_ai_llms_mode'] ) ) $output['seo_ai_llms_mode'] = Digitalisimo_Integrations_LLMS::sanitize_mode( $input['seo_ai_llms_mode'] );
		if ( isset( $input['seo_ai_llms_urls'] ) ) $output['seo_ai_llms_urls'] = Digitalisimo_Integrations_LLMS::sanitize_urls( $input['seo_ai_llms_urls'] );
		if ( isset( $input['seo_ai_llms_manual'] ) ) $output['seo_ai_llms_manual'] = sanitize_textarea_field( $input['seo_ai_llms_manual'] );
		if ( isset( $input['perf_img_exclusions'] ) ) $output['perf_img_exclusions'] = Digitalisimo_Integrations_Performance_Images::sanitize_exclusions( $input['perf_img_exclusions'] );
		if ( isset( $input['perf_font_icon_families'] ) ) $output['perf_font_icon_families'] = Digitalisimo_Integrations_Performance_Font_Guard::sanitize_icon_families( $input['perf_font_icon_families'] );
		if ( isset( $input['perf_preload_mode'] ) ) $output['perf_preload_mode'] = Digitalisimo_Integrations_Performance_Preloads::sanitize_mode( $input['perf_preload_mode'] );
		if ( isset( $input['perf_preload_paths'] ) ) $output['perf_preload_paths'] = Digitalisimo_Integrations_Performance_Preloads::sanitize_paths( $input['perf_preload_paths'] );
		if ( isset( $input['perf_preload_limit'] ) ) $output['perf_preload_limit'] = Digitalisimo_Integrations_Performance_Preloads::sanitize_limit( $input['perf_preload_limit'] );
		foreach ( array( 'perf_gt_id' => 'gt', 'perf_ga4_id' => 'ga4', 'perf_gtm_id' => 'gtm' ) as $key => $kind ) if ( isset( $input[ $key ] ) ) $output[ $key ] = Digitalisimo_Integrations_Performance_Tracking::sanitize_id( $input[ $key ], $kind );
		if ( ! empty( $input['perf_tracking_enabled'] ) ) {
			foreach ( Digitalisimo_Integrations_Performance_Tracking::site_kit_ids() as $key => $detected ) {
				if ( $detected && empty( $output[ $key ] ) ) $output[ $key ] = $detected;
			}
		}
		if ( isset( $input['perf_tracking_mode'] ) ) $output['perf_tracking_mode'] = Digitalisimo_Integrations_Performance_Tracking::sanitize_mode( $input['perf_tracking_mode'] );
		foreach ( array( 'perf_tracking_delay', 'perf_tracking_fallback' ) as $key ) if ( isset( $input[ $key ] ) ) $output[ $key ] = Digitalisimo_Integrations_Performance_Tracking::sanitize_seconds( $input[ $key ] );
		foreach ( self::template_keys() as $key ) if ( isset( $input[ $key ] ) ) $output[ $key ] = self::sanitize_template( $input[ $key ], 0 === strpos( $key, 'seo_description_' ) );
		foreach ( self::template_default_keys() as $key ) if ( isset( $input[ $key ] ) && '' === trim( (string) $input[ $key ] ) ) $output[ $key ] = self::defaults()[ $key ];
		if ( isset( $input['ipinfo_cache_minutes'] ) ) $output['ipinfo_cache_minutes'] = max( 0, absint( $input['ipinfo_cache_minutes'] ) );
		if ( isset( $input['keyword_max_count'] ) ) $output['keyword_max_count'] = min( 5, max( 1, absint( $input['keyword_max_count'] ) ) );
		if ( isset( $input['default_cluster_pillar'] ) ) $output['default_cluster_pillar'] = Digitalisimo_Integrations_Editorial::is_cluster_candidate( absint( $input['default_cluster_pillar'] ) ) ? absint( $input['default_cluster_pillar'] ) : 0;
		if ( isset( $input['default_content_location'] ) ) $output['default_content_location'] = term_exists( absint( $input['default_content_location'] ), 'digitalisimo_seo_location' ) ? absint( $input['default_content_location'] ) : 0;
		// Las claves sólo se escriben si llega un valor nuevo; vacío conserva la almacenada.
		foreach ( array( 'openai_api_key', 'namecheap_api_key' ) as $key ) {
			// La máscara •••• es lo que el campo muestra con una clave guardada: llegar con ella (o vacío) la conserva.
			if ( ! empty( $input['secret_remove'][ $key ] ) ) $output[ $key ] = '';
			elseif ( '' !== ( $new = self::unmask( $input[ $key ] ?? '' ) ) ) $output[ $key ] = $new;
			elseif ( ! array_key_exists( $key, $output ) ) $output[ $key ] = '';
		}
		if ( isset( $input['seo_local_phone'] ) ) $output['seo_local_phone'] = Digitalisimo_Integrations_Local_Business::sanitize_phone( $input['seo_local_phone'] );
		if ( isset( $input['seo_local_phone_cc'] ) ) $output['seo_local_phone_cc'] = Digitalisimo_Integrations_Local_Business::sanitize_code( $input['seo_local_phone_cc'] );
		if ( isset( $input['seo_local_schedule'] ) ) $output['seo_local_schedule'] = Digitalisimo_Integrations_Local_Business::sanitize_schedule( $input['seo_local_schedule'] );
		$output[ Digitalisimo_Integrations_Social_Profiles::KEY ] = isset( $input[ Digitalisimo_Integrations_Social_Profiles::KEY ] )
			? Digitalisimo_Integrations_Social_Profiles::sanitize( $input[ Digitalisimo_Integrations_Social_Profiles::KEY ], $current[ Digitalisimo_Integrations_Social_Profiles::KEY ] ?? array() )
			: ( $current[ Digitalisimo_Integrations_Social_Profiles::KEY ] ?? array() );
		if ( isset( $input['seo_knowledge_urls'] ) ) $output['seo_knowledge_urls'] = Digitalisimo_Integrations_Social_Profiles::sanitize_extras( $input['seo_knowledge_urls'] );
		// Un archivo se guarda como URL: sanitize_text_field() dejaba pasar texto suelto.
		foreach ( Digitalisimo_Integrations_SEO_Suite::media_keys() as $key ) {
			if ( isset( $input[ $key ] ) ) $output[ $key ] = esc_url_raw( trim( (string) $input[ $key ] ) );
		}
		if ( isset( $input['seo_hide_login_slug'] ) ) $output['seo_hide_login_slug'] = Digitalisimo_Integrations_Hide_Login::sanitize_option( $input['seo_hide_login_slug'], $current['seo_hide_login_slug'] ?? '' );
		$output['ai_providers'] = self::sanitize_providers( $input['ai_providers'] ?? null, (array) ( $current['ai_providers'] ?? array() ) );
		foreach ( array( 'ai_profile_provider' => 'openai', 'ai_profile_fallback' => '' ) as $key => $unused ) {
			if ( isset( $input[ $key ] ) ) $output[ $key ] = sanitize_key( $input[ $key ] );
		}
		if ( isset( $input['ai_profile_model'] ) ) $output['ai_profile_model'] = sanitize_text_field( $input['ai_profile_model'] );
		if ( is_multisite() && isset( $input['network_inherit'] ) ) {
			$inherit = (array) get_option( 'digitalisimo_seo_network_inherit', array() );
			// Dejar de heredar sin escribir nada conserva el valor que se veía (el de la red): el campo seguía deshabilitado y
			// no viajaba (sin JavaScript, o con un campo agrupado), y el sitio caía al predeterminado del plugin.
			foreach ( (array) $input['network_inherit'] as $key => $flag ) {
				// Un secreto nunca se copia de la red al sitio: queda vacío y el campo pide la clave de este sitio.
				if ( ! empty( $flag ) || array_key_exists( $key, $input ) || ! array_key_exists( $key, self::defaults() ) || in_array( $key, self::secret_keys(), true ) || ! Digitalisimo_Integrations_SEO_Resolver::inherits_network( $key ) ) continue;
				$network = (array) get_site_option( self::OPTION, array() );
				if ( array_key_exists( $key, $network ) ) $output[ $key ] = $network[ $key ];
			}
			foreach ( self::defaults() as $key => $unused ) if ( isset( $input['network_inherit'][ $key ] ) ) $inherit[ $key ] = empty( $input['network_inherit'][ $key ] ) ? 0 : 1;
			update_option( 'digitalisimo_seo_network_inherit', $inherit, false );
			// Un campo heredado se pinta deshabilitado, pero el «0» oculto de su casilla sí viaja: guardado en el sitio, y si
			// la red aún no define esa clave, ganaba el 0 (guardar «Ajustes de keywords» apagaba el SEO propio del sitio).
			// Lo que se hereda conserva el valor propio que el sitio tenía.
			foreach ( (array) $input['network_inherit'] as $key => $flag ) {
				if ( empty( $flag ) || ! array_key_exists( $key, self::defaults() ) ) continue;
				if ( array_key_exists( $key, $current ) ) $output[ $key ] = $current[ $key ]; else unset( $output[ $key ] );
			}
		}
		return $output;
	}

	/** El envío del formulario de ajustes de este sitio a options.php (settings_fields( 'digitalisimo_integrations' )). */
	private static function is_form_submission() {
		return 'options.php' === ( $GLOBALS['pagenow'] ?? '' ) && 'digitalisimo_integrations' === sanitize_key( wp_unslash( $_POST['option_page'] ?? '' ) ) && ! ( function_exists( 'is_network_admin' ) && is_network_admin() );
	}

	/**
	 * Una pantalla sólo de sitio (llms.txt, Referencias, IndexNow) que guarda estas claves en el sitio las personaliza: si
	 * siguieran heredándose, el sitio seguiría usando las de la red y lo guardado parecería no haberse aplicado.
	 */
	public static function personalize( array $keys ) {
		if ( ! is_multisite() ) return;
		$inherit = (array) get_option( 'digitalisimo_seo_network_inherit', array() );
		foreach ( $keys as $key ) $inherit[ $key ] = 0;
		update_option( 'digitalisimo_seo_network_inherit', $inherit, false );
	}

	/** Valores efectivos (sitio o red) de estas claves sobre las opciones del sitio, para pintar una pantalla de sitio. */
	public static function effective_over( array $option, array $keys ) {
		foreach ( $keys as $key ) $option[ $key ] = self::get( $key );
		return $option;
	}

	/** Claves y secretos: nunca se imprimen, tampoco como «valor efectivo». */
	public static function secret_keys() { return array( 'openai_api_key', 'namecheap_api_key', 'ipinfo_tokens', 'ai_providers' ); }

	/** Lo escrito en un campo de clave, sin la máscara: una clave real nunca lleva «•» (el mismo criterio que IA::unmask()). */
	public static function unmask( $raw ) { return sanitize_text_field( str_replace( '•', '', (string) $raw ) ); }

	/** Claves de IA antiguas de SEO que guardan secretos: nadie las lee de la red (son exclusivas de cada sitio). */
	public static function network_secret_keys() { return array( 'openai_api_key', 'ai_providers', 'ai_profile_provider', 'ai_profile_model', 'ai_profile_fallback' ); }

	/**
	 * Lo que la red ya no puede editar desde Red → SEO pero sigue leyéndose de ella (lo exclusivo de cada sitio, el
	 * interruptor del SEO propio, el horario antiguo, los textos editoriales que lee IA Tools y lo que pasó a Tools).
	 * Nunca se quita solo: Red → SEO lo lista para que quien administra la red decida (SEO_Suite::network_review_keys()).
	 */
	public static function network_uneditable_keys() {
		return array_values( array_diff( array_unique( array_merge( Digitalisimo_Integrations_SEO_Resolver::site_only_keys(), array( 'enable_seo', 'default_content_location', 'seo_taxonomies', 'seo_local_hours', 'perf_css_defer_handles', 'seo_redirect_attachments', 'seo_mobile_prevent_horizontal_scroll', 'seo_hide_login_slug', 'content_editorial_voice', 'content_editorial_context', 'content_editorial_cta', 'content_editorial_method' ) ) ), self::network_secret_keys() ) );
	}

	/** Las que Tools administra cuando está activo; SEO las sigue sirviendo mientras no lo esté. */
	public static function tools_keys() { return array( 'seo_hide_login_slug', 'seo_redirect_attachments', 'seo_mobile_prevent_horizontal_scroll' ); }

	/**
	 * Una vez por red (bandera nueva: también corre donde pasó la limpieza anterior). Hasta esta versión, todo guardado
	 * de la red se armaba sobre las opciones del sitio principal (sanitize() se aplicaba también a la opción de red) y
	 * copiaba a la red sus claves de IA. Sólo se quitan solas esas claves secretas, que nadie lee de la red; todo lo
	 * demás se queda y Red → SEO lo revisa con quien administra la red. No toca ningún sitio y deja el registro (sin
	 * valores: son secretos).
	 */
	public static function clean_network_secrets() {
		if ( ! is_multisite() || get_site_option( self::NETWORK_CLEANED ) ) return;
		$network = get_site_option( self::OPTION, null );
		$removed = array();
		if ( is_array( $network ) ) {
			foreach ( self::network_secret_keys() as $key ) if ( array_key_exists( $key, $network ) ) { unset( $network[ $key ] ); $removed[] = $key; }
			if ( $removed ) update_site_option( self::OPTION, $network );
		}
		update_site_option( self::NETWORK_CLEANED, array( 'time' => time(), 'removed' => $removed ) );
	}

	/**
	 * Script de «Heredar de la red»: deshabilita el campo y su «0» oculto (data-inherit-of) mientras se hereda. Sin
	 * JavaScript el resultado es el mismo: lo deshabilitado no viaja y sanitize() conserva lo que se veía.
	 */
	public static function inherit_script() { return '<script>document.querySelectorAll(".digitalisimo-network-inherit").forEach(function(c){var f=document.getElementById(c.dataset.field);function s(){if(f)f.disabled=c.checked;document.querySelectorAll("[data-inherit-of=\""+c.dataset.field+"\"]").forEach(function(h){h.disabled=c.checked;});}c.addEventListener("change",s);s();});</script>'; }

	/** Proveedores de IA admitidos, en el orden en que se muestran. */
	public static function ai_providers() {
		return array( 'openai' => 'OpenAI / GPT', 'anthropic' => 'Anthropic / Claude', 'google' => 'Google / Gemini', 'deepseek' => 'DeepSeek', 'xai' => 'xAI / Grok' );
	}

	/**
	 * Normaliza los proveedores conservando cada clave existente cuando el campo
	 * llega vacío, de modo que guardar no obliga a reescribir las credenciales.
	 */
	private static function sanitize_providers( $input, $current ) {
		if ( ! is_array( $input ) ) return $current;
		$output = $current;
		foreach ( self::ai_providers() as $id => $unused ) {
			if ( ! isset( $input[ $id ] ) ) continue;
			$in = (array) $input[ $id ];
			$output[ $id ]['enabled'] = empty( $in['enabled'] ) ? 0 : 1;
			$output[ $id ]['url']     = esc_url_raw( $in['url'] ?? '' );
			if ( ! empty( $in['remove'] ) ) $output[ $id ]['key'] = '';
			elseif ( '' !== ( $new = self::unmask( $in['key'] ?? '' ) ) ) $output[ $id ]['key'] = $new;
			elseif ( ! isset( $output[ $id ]['key'] ) ) $output[ $id ]['key'] = '';
		}
		return $output;
	}

	/** Indica si el proveedor tiene clave guardada, sin exponer su valor. */
	public static function ai_provider_has_key( $id ) {
		$providers = (array) self::get( 'ai_providers' );
		return ! empty( $providers[ $id ]['key'] );
	}

	/** Pestaña de proveedores IA: credenciales y perfil usado por las funciones SEO. */
	private static function ai_fields() {
		$providers = (array) self::get( 'ai_providers' );
		$option    = esc_attr( self::OPTION );
		echo '<tr><th scope="row">Proveedores</th><td><p class="description">Las claves se guardan en los ajustes del sitio y no se muestran de vuelta en el formulario. Deja el campo vacío para conservar la clave existente.</p>';
		foreach ( self::ai_providers() as $id => $name ) {
			$p       = (array) ( $providers[ $id ] ?? array() );
			$has_key = ! empty( $p['key'] );
			echo '<fieldset style="margin:14px 0;padding:14px 16px;border:1px solid #ccd6e5;border-radius:12px"><legend><strong>' . esc_html( $name ) . '</strong></legend>';
			echo '<p><label><input type="hidden" name="' . $option . '[ai_providers][' . esc_attr( $id ) . '][enabled]" value="0"><input type="checkbox" name="' . $option . '[ai_providers][' . esc_attr( $id ) . '][enabled]" value="1" ' . checked( ! empty( $p['enabled'] ), true, false ) . '> Activar</label></p>';
			echo '<p><input class="regular-text" type="password" autocomplete="new-password" name="' . $option . '[ai_providers][' . esc_attr( $id ) . '][key]" value="' . esc_attr( $has_key ? self::MASK : '' ) . '" onfocus="this.select()" placeholder="API Key"> <span class="digitalisimo-secret ' . ( $has_key ? 'is-set' : 'is-empty' ) . '">' . esc_html( $has_key ? 'Guardada · escribe una nueva sólo si quieres reemplazarla' : 'Sin clave configurada' ) . '</span>' . ( $has_key ? ' <label><input type="checkbox" name="' . $option . '[ai_providers][' . esc_attr( $id ) . '][remove]" value="1"> Quitar la clave</label>' : '' ) . '</p>';
			echo '<p><label>URL compatible <input class="regular-text" type="url" name="' . $option . '[ai_providers][' . esc_attr( $id ) . '][url]" value="' . esc_attr( $p['url'] ?? '' ) . '" placeholder="Opcional"></label></p>';
			echo '</fieldset>';
		}
		echo '</td></tr>';
		$choices = self::ai_providers();
		self::field( 'ai_profile_provider', 'Proveedor para funciones SEO', 'select', 'Se usa al detectar intención de búsqueda y en el resto de apoyos de IA.', $choices );
		self::field( 'ai_profile_model', 'Modelo', 'text', 'Por ejemplo: gpt-4o-mini, claude-sonnet-5 o gemini-2.0-flash.' );
		self::field( 'ai_profile_fallback', 'Proveedor alternativo', 'select', 'Se intenta si el principal falla. Deja «Sin alternativa» para no reintentar.', array( '' => 'Sin alternativa' ) + $choices );
	}

	private static function field( $key, $label, $type = 'text', $help = '', $choices = array() ) {
		$value = self::get( $key );
		$site_only = class_exists( 'Digitalisimo_Integrations_SEO_Resolver' ) && in_array( $key, Digitalisimo_Integrations_SEO_Resolver::site_only_keys(), true );
		$inherit = is_multisite() && class_exists( 'Digitalisimo_Integrations_SEO_Resolver' ) && Digitalisimo_Integrations_SEO_Resolver::inherits_network( $key );
		$disabled = $inherit ? ' disabled' : '';
		$defaults = self::defaults(); $is_default = ! $inherit && array_key_exists( $key, $defaults ) && $value == $defaults[ $key ];
		$field_class = 'digitalisimo-site-field' . ( $inherit ? ' digitalisimo-inherited-value' : ( $is_default ? ' digitalisimo-default-value' : '' ) );
		echo '<tr><th scope="row"><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
		if ( 'checkbox' === $type ) {
			echo '<input type="hidden" name="' . esc_attr( self::OPTION ) . '[' . esc_attr( $key ) . ']" value="0" data-inherit-of="' . esc_attr( $key ) . '"' . $disabled . '><label><input class="' . esc_attr( $field_class ) . '" type="checkbox" id="' . esc_attr( $key ) . '" name="' . esc_attr( self::OPTION ) . '[' . esc_attr( $key ) . ']" value="1" ' . checked( $value, 1, false ) . $disabled . '> ' . esc_html__( 'Activar', 'digitalisimo-integrations' ) . '</label>';
		} elseif ( 'textarea' === $type ) {
			echo '<textarea class="large-text code ' . esc_attr( $field_class ) . '" rows="' . ( 'seo_robots_rules' === $key ? '12' : '4' ) . '" id="' . esc_attr( $key ) . '" name="' . esc_attr( self::OPTION ) . '[' . esc_attr( $key ) . ']"' . $disabled . '>' . esc_textarea( $value ) . '</textarea>';
		} elseif ( 'media' === $type ) {
			Digitalisimo_Media_Field::render( $key, self::OPTION . '[' . $key . ']', $value, (bool) $inherit, $field_class );
		} elseif ( 'select' === $type ) {
			echo '<select class="' . esc_attr( $field_class ) . '" id="' . esc_attr( $key ) . '" name="' . esc_attr( self::OPTION ) . '[' . esc_attr( $key ) . ']"' . $disabled . '>';
			foreach ( (array) $choices as $choice => $label_choice ) echo '<option value="' . esc_attr( $choice ) . '" ' . selected( (string) $value, (string) $choice, false ) . '>' . esc_html( $label_choice ) . '</option>';
			echo '</select>';
		} else {
			$secret       = 'secret' === $type;
			$actual_type  = $secret ? 'password' : $type;
			// El secreto nunca viaja al navegador: con una clave guardada el campo muestra la máscara (pedido del
			// propietario: «deben salir los *****»), y al guardar la máscara conserva la clave almacenada.
			$actual_value = $secret ? ( empty( $value ) ? '' : self::MASK ) : $value;
			$placeholder  = $secret ? ' autocomplete="new-password" onfocus="this.select()"' : '';
			echo '<input class="regular-text ' . esc_attr( $field_class ) . '" type="' . esc_attr( $actual_type ) . '" id="' . esc_attr( $key ) . '" name="' . esc_attr( self::OPTION ) . '[' . esc_attr( $key ) . ']" value="' . esc_attr( $actual_value ) . '"' . $placeholder . $disabled . '>';
			if ( $secret ) echo ' <span class="digitalisimo-secret ' . ( empty( $value ) ? 'is-empty' : 'is-set' ) . '">' . esc_html( empty( $value ) ? __( 'Sin configurar', 'digitalisimo-integrations' ) : __( 'Guardada · escribe una nueva sólo si quieres reemplazarla', 'digitalisimo-integrations' ) ) . '</span>';
			if ( $secret && ! empty( $value ) && ! $inherit ) echo ' <label><input type="checkbox" name="' . esc_attr( self::OPTION ) . '[secret_remove][' . esc_attr( $key ) . ']" value="1"> ' . esc_html__( 'Quitar la clave', 'digitalisimo-integrations' ) . '</label>';
		}
		if ( $help ) echo '<p class="description">' . esc_html( $help ) . '</p>';
		if ( $is_default ) echo '<p class="description digitalisimo-default-note">Valor predeterminado del plugin.</p>';
		if ( is_multisite() && class_exists( 'Digitalisimo_Integrations_SEO_Resolver' ) && ! $site_only ) { $origin = Digitalisimo_Integrations_SEO_Resolver::option_with_origin( $key ); $effective = in_array( $key, self::secret_keys(), true ) || 'secret' === $type ? ( empty( $origin['value'] ) ? 'Sin configurar' : self::MASK ) : ( 'seo_robots_rules' === $key ? ( empty( $origin['value'] ) ? 'Sin reglas adicionales' : 'Reglas disponibles' ) : ( is_scalar( $origin['value'] ) ? (string) $origin['value'] : '' ) ); echo '<p class="digitalisimo-inheritance"><input type="hidden" name="' . esc_attr( self::OPTION ) . '[network_inherit][' . esc_attr( $key ) . ']" value="0"><label><input class="digitalisimo-network-inherit" data-field="' . esc_attr( $key ) . '" type="checkbox" name="' . esc_attr( self::OPTION ) . '[network_inherit][' . esc_attr( $key ) . ']" value="1" ' . checked( $inherit, true, false ) . '> Heredar de la red</label><br><span class="description">Valor efectivo: <strong>' . esc_html( $effective ) . '</strong> · Origen: ' . esc_html( $origin['label'] ) . '</span></p>'; }
		echo '</td></tr>';
	}

	public static function settings_page( $fixed_tab = null ) {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$tab  = $fixed_tab ? $fixed_tab : sanitize_key( $_GET['tab'] ?? 'general' );
		$tabs = array( 'general' => 'General', 'seo' => 'Contenido', 'ai' => 'Proveedores IA', 'sitemap' => 'Avanzado: Sitemap', 'robots' => 'Robots', 'indexing' => 'Avanzado: Indexación' );
		if ( ! isset( $tabs[ $tab ] ) ) $tab = 'general';

		echo '<div class="wrap digitalisimo-admin-shell"><h1>' . esc_html__( 'Digitalisimo · SEO', 'digitalisimo-integrations' ) . '</h1><hr class="wp-header-end">';
		echo '<h2 class="nav-tab-wrapper digitalisimo-tabs">';
		foreach ( $tabs as $slug => $name ) echo '<a class="nav-tab ' . ( $tab === $slug ? 'nav-tab-active' : '' ) . '" data-tab="' . esc_attr( $slug ) . '" href="' . esc_url( admin_url( 'admin.php?page=digitalisimo-seo-settings&tab=' . $slug ) ) . '">' . esc_html( $name ) . '</a>';
		echo '</h2>';
		// En un menú propio los avisos de register_setting() no se imprimen solos.
		settings_errors( 'digitalisimo_integrations' );
		echo '<p class="description">Configura primero módulos y contenido. Las reglas técnicas e integraciones especializadas están agrupadas como opciones avanzadas.</p>';
		echo '<form method="post" action="options.php">';
		settings_fields( 'digitalisimo_integrations' );

		// Todas las pestañas viven en el mismo formulario: cambiar de pestaña no
		// recarga, así que lo escrito en una no se pierde al pasar a otra y un
		// único guardado las envía todas.
		foreach ( $tabs as $slug => $name ) {
			echo '<div class="digitalisimo-panel" data-tab="' . esc_attr( $slug ) . '"' . ( $slug === $tab ? '' : ' hidden' ) . '>';
			echo '<table class="form-table" role="presentation">';
			self::tab_fields( $slug );
			echo '</table></div>';
		}

		echo '<input type="hidden" class="digitalisimo-active-tab" name="digitalisimo_active_tab" value="' . esc_attr( $tab ) . '">';
		submit_button();
		echo '</form>' . self::inherit_script() . '</div>';
	}

	/** Campos de una pestaña. Separado para poder pintarlas todas de una vez. */
	private static function tab_fields( $tab ) {
		if ( 'general' === $tab ) {
			self::field( 'enable_seo', 'Módulo SEO propio', 'checkbox', 'Añade metadatos SEO, clusters, shortcodes, breadcrumbs, schema y sitemap nativos.' );
		} elseif ( 'seo' === $tab ) {
			self::field( 'openai_api_key', 'Clave API de OpenAI', 'secret', 'Se usa solo para detectar intención de búsqueda.' );
			self::field( 'openai_model', 'Modelo OpenAI', 'text', 'Por ejemplo: gpt-4o-mini.' );
			self::field( 'keyword_post_types', 'Tipos de contenido con keywords', 'textarea', 'Usa all para mostrarlo en todos los tipos editables, o separa por comas: post,page,product.' );
			self::field( 'keyword_max_count', 'Máximo de keywords objetivo por contenido', 'number', 'Máximo 5: una principal y hasta cuatro secundarias de la misma intención.' );
			self::field( 'keyword_auto_from_title', 'Usar título como keyword inicial', 'checkbox', 'Si no se asigna una keyword al guardar, se usa el título del contenido.' );
			echo '<tr><th scope="row">Palabras clave</th><td><p>Las keywords se configuran por entrada o página, en el metabox <strong>SEO de Digitalisimo</strong>.</p><p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=digitalisimo-keywords' ) ) . '">Gestionar keywords</a></p></td></tr>';
		} elseif ( 'ai' === $tab ) {
			self::ai_fields();
		} elseif ( 'sitemap' === $tab ) {
			Digitalisimo_Integrations_SEO_Suite::sitemap_link_row();
			self::field( 'sitemap_enabled', 'Activar sitemap XML', 'checkbox', 'Disponible en /sitemap.xml: un archivo por sitio de la red o un índice por tipo en WordPress individual.' );
			self::field( 'sitemap_post_types', 'Tipos de contenido incluidos', 'textarea', 'Auto incluye entradas, páginas, productos y otros tipos públicos indexables. Para limitar, escribe los slugs separados por comas; las páginas públicas siempre se incluyen.' );
			self::field( 'sitemap_exclude_ids', 'IDs excluidos del sitemap', 'textarea', 'Separados por comas. Los contenidos marcados noindex siempre se excluyen.' );
		} elseif ( 'robots' === $tab ) {
			Digitalisimo_Integrations_SEO_Suite::robots_info_row();
			self::field( 'seo_ai_google_extended_policy', 'Google-Extended', 'select', 'Permitir favorece la presencia en Gemini; bloquear limita entrenamiento y grounding en Gemini, pero no afecta Google Search.', array( 'allow' => 'Permitir (máxima visibilidad en Gemini)', 'disallow' => 'Bloquear uso por Gemini' ) );
			self::field( 'seo_robots_rules', 'Reglas adicionales', 'textarea', 'Admite User-agent, Allow y Disallow. El sitemap y las reglas automáticas se conservan.' );
		} elseif ( 'indexing' === $tab ) {
			self::field( 'noindex_search', 'Resultados de búsqueda internos', 'checkbox' );
			self::field( 'noindex_authors', 'Archivos de autor', 'checkbox', 'Útil cuando las páginas de autor no aportan contenido único.' );
			self::field( 'noindex_empty_tags', 'Tags vacíos', 'checkbox' );
			self::field( 'noindex_date_archives', 'Archivos por fecha', 'checkbox' );
			self::field( 'noindex_attachments', 'Páginas de adjuntos', 'checkbox' );
			self::field( 'noindex_elementor', 'Plantillas del constructor (DIGITALÍSIMO Elements)', 'checkbox' );
			self::field( 'noindex_woo_pages', 'Carrito, checkout, gracias y mi cuenta', 'checkbox' );
			self::field( 'seo_noindex_unresolved', 'Sacar del índice las páginas con shortcodes sin resolver', 'checkbox', Digitalisimo_Integrations_SEO_Suite::UNRESOLVED_HELP );
			self::field( 'noindex_page_slugs', 'Slugs de páginas de prueba o privadas', 'textarea', 'Uno por línea. Ejemplo: prueba, gracias-por-contactar.' );
			self::field( 'noindex_page_ids', 'IDs de páginas de prueba o privadas', 'textarea', 'Separados por comas.' );
		}
	}


	public static function keyword_settings_page() { self::settings_page( 'seo' ); }
	public static function sitemap_settings_page() { self::settings_page( 'sitemap' ); }
	public static function indexing_settings_page() { self::settings_page( 'indexing' ); }

}
