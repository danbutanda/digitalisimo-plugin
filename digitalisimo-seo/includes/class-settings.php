<?php
defined( 'ABSPATH' ) || exit;

class Digitalisimo_Integrations_Settings {
	const OPTION = 'digitalisimo_integrations_options';

	public static function defaults() {
		return array(
			'enable_seo'             => 1,
			'enable_woocommerce'     => 1,
			'enable_ipinfo'          => 0,
			'sitemap_enabled'        => 1,
			'sitemap_post_types'     => 'post,page',
			'sitemap_exclude_ids'    => '',
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
			'noindex_page_slugs'     => '',
			'noindex_page_ids'       => '',
			'openai_api_key'         => '',
			'openai_model'           => 'gpt-4o-mini',
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
			'seo_knowledge_urls'     => '',
			'seo_title_post'         => '%title% %sep% %sitename%',
			'seo_description_post'   => '%excerpt%',
			'seo_title_page'         => '%title% %sep% %sitename%',
			'seo_description_page'   => '%excerpt%',
			'seo_title_archive'      => '%title% %sep% %sitename%',
			'seo_description_archive'=> '',
			'seo_title_taxonomy'     => '%term% %sep% %sitename%',
			'seo_description_taxonomy'=> '%description%',
			'seo_schema_enabled'     => 1,
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
			'seo_taxonomies'         => 'category,post_tag',
			'seo_sitemap_taxonomies' => 'category',
			'seo_sitemap_images'     => 1,
			'seo_ai_enabled'         => 1,
			'seo_ai_search_default'  => 'allow',
			'seo_ai_training_default'=> 'disallow',
			'seo_ai_crawler_rules'  => '',
			'seo_ai_crawler_preset' => 'custom',
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
		);
	}

	public static function init() {
		// En Multisite el plugin puede activarse para toda la red, pero cada sitio
		// conserva sus propios ajustes mediante get_option()/update_option().
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	public static function get( $key = null, $default = null ) {
		if ( null !== $key && class_exists( 'Digitalisimo_Integrations_SEO_Resolver' ) ) return Digitalisimo_Integrations_SEO_Resolver::option( $key, $default );
		$stored  = get_option( self::OPTION, array() );
		return wp_parse_args( $stored, self::defaults() );
	}

	public static function admin_menu() {
		add_submenu_page( 'digitalisimo', __( 'SEO', 'digitalisimo-integrations' ), __( 'SEO', 'digitalisimo-integrations' ), 'manage_options', 'digitalisimo-seo-settings', array( __CLASS__, 'settings_page' ) );
		add_submenu_page( 'digitalisimo', __( 'Ajustes de keywords', 'digitalisimo-integrations' ), __( 'Ajustes de keywords', 'digitalisimo-integrations' ), 'manage_options', 'digitalisimo-keyword-settings', array( __CLASS__, 'keyword_settings_page' ) );
		add_submenu_page( 'digitalisimo', __( 'Ajustes de sitemap', 'digitalisimo-integrations' ), __( 'Ajustes de sitemap', 'digitalisimo-integrations' ), 'manage_options', 'digitalisimo-sitemap-settings', array( __CLASS__, 'sitemap_settings_page' ) );
		add_submenu_page( 'digitalisimo', __( 'Ajustes de indexación', 'digitalisimo-integrations' ), __( 'Ajustes de indexación', 'digitalisimo-integrations' ), 'manage_options', 'digitalisimo-indexing-settings', array( __CLASS__, 'indexing_settings_page' ) );
	}

	public static function register_settings() {
		register_setting( 'digitalisimo_integrations', self::OPTION, array( __CLASS__, 'sanitize' ) );
	}

	public static function sanitize( $input ) {
		$current = (array) get_option( self::OPTION, array() );
		$output  = $current;
		foreach ( array( 'enable_seo', 'enable_woocommerce', 'enable_ipinfo', 'sitemap_enabled', 'keyword_auto_from_title', 'noindex_search', 'noindex_authors', 'noindex_empty_tags', 'noindex_date_archives', 'noindex_attachments', 'noindex_elementor', 'noindex_woo_pages', 'require_domain_hosting', 'replace_hosting', 'simplify_checkout', 'seo_schema_enabled', 'seo_breadcrumbs', 'seo_open_graph', 'seo_twitter_enabled', 'seo_local_enabled', 'seo_redirect_attachments', 'seo_sitemap_images' ) as $key ) {
			if ( isset( $input[ $key ] ) ) $output[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}
		foreach ( array( 'openai_model', 'namecheap_api_user', 'namecheap_username', 'namecheap_client_ip', 'seo_site_name', 'seo_separator', 'seo_organization_type', 'seo_organization_name', 'seo_organization_url', 'seo_organization_email', 'seo_logo', 'seo_default_image', 'seo_twitter_card', 'seo_twitter_user', 'seo_local_type', 'seo_local_name', 'seo_local_phone', 'seo_local_city', 'seo_local_region', 'seo_local_postal', 'seo_local_country', 'seo_local_latitude', 'seo_local_longitude', 'seo_local_price_range' ) as $key ) {
			$output[ $key ] = isset( $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : '';
		}
		foreach ( array( 'domain_products', 'hosting_product_ids', 'hosting_category_ids', 'ipinfo_tokens', 'sitemap_post_types', 'sitemap_exclude_ids', 'keyword_post_types', 'noindex_page_slugs', 'noindex_page_ids', 'seo_knowledge_urls', 'seo_title_post', 'seo_description_post', 'seo_title_page', 'seo_description_page', 'seo_title_archive', 'seo_description_archive', 'seo_title_taxonomy', 'seo_description_taxonomy', 'seo_local_address', 'seo_local_hours', 'seo_taxonomies', 'seo_sitemap_taxonomies' ) as $key ) {
			$output[ $key ] = isset( $input[ $key ] ) ? sanitize_textarea_field( $input[ $key ] ) : '';
		}
		$output['ipinfo_cache_minutes'] = max( 0, absint( $input['ipinfo_cache_minutes'] ?? 60 ) );
		if ( isset( $input['keyword_max_count'] ) ) $output['keyword_max_count'] = min( 20, max( 1, absint( $input['keyword_max_count'] ) ) );
		foreach ( array( 'openai_api_key', 'namecheap_api_key' ) as $key ) {
			$output[ $key ] = ! empty( $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : ( $current[ $key ] ?? '' );
		}
		if ( is_multisite() && isset( $input['network_inherit'] ) ) {
			$inherit = (array) get_option( 'digitalisimo_seo_network_inherit', array() );
			foreach ( self::defaults() as $key => $unused ) if ( isset( $input['network_inherit'][ $key ] ) ) $inherit[ $key ] = empty( $input['network_inherit'][ $key ] ) ? 0 : 1;
			update_option( 'digitalisimo_seo_network_inherit', $inherit, false );
		}
		return $output;
	}

	private static function field( $key, $label, $type = 'text', $help = '' ) {
		$value = self::get( $key );
		$inherit = is_multisite() && class_exists( 'Digitalisimo_Integrations_SEO_Resolver' ) && Digitalisimo_Integrations_SEO_Resolver::inherits_network( $key );
		$disabled = $inherit ? ' disabled' : '';
		echo '<tr><th scope="row"><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
		if ( 'checkbox' === $type ) {
			echo '<input type="hidden" name="' . esc_attr( self::OPTION ) . '[' . esc_attr( $key ) . ']" value="0"><label><input class="digitalisimo-site-field" type="checkbox" id="' . esc_attr( $key ) . '" name="' . esc_attr( self::OPTION ) . '[' . esc_attr( $key ) . ']" value="1" ' . checked( $value, 1, false ) . $disabled . '> ' . esc_html__( 'Activar', 'digitalisimo-integrations' ) . '</label>';
		} elseif ( 'textarea' === $type ) {
			echo '<textarea class="large-text code digitalisimo-site-field" rows="4" id="' . esc_attr( $key ) . '" name="' . esc_attr( self::OPTION ) . '[' . esc_attr( $key ) . ']"' . $disabled . '>' . esc_textarea( $value ) . '</textarea>';
		} else {
			$actual_type = 'secret' === $type ? 'password' : $type;
			$actual_value = 'secret' === $type ? '' : $value;
			echo '<input class="regular-text digitalisimo-site-field" type="' . esc_attr( $actual_type ) . '" id="' . esc_attr( $key ) . '" name="' . esc_attr( self::OPTION ) . '[' . esc_attr( $key ) . ']" value="' . esc_attr( $actual_value ) . '"' . $disabled . '>';
			if ( 'secret' === $type && ! empty( $value ) ) echo ' <span class="description">' . esc_html__( 'Ya configurada. Déjala vacía para conservarla.', 'digitalisimo-integrations' ) . '</span>';
		}
		if ( $help ) echo '<p class="description">' . esc_html( $help ) . '</p>';
		if ( is_multisite() && class_exists( 'Digitalisimo_Integrations_SEO_Resolver' ) ) { $origin = Digitalisimo_Integrations_SEO_Resolver::option_with_origin( $key ); echo '<p><input type="hidden" name="' . esc_attr( self::OPTION ) . '[network_inherit][' . esc_attr( $key ) . ']" value="0"><label><input class="digitalisimo-network-inherit" data-field="' . esc_attr( $key ) . '" type="checkbox" name="' . esc_attr( self::OPTION ) . '[network_inherit][' . esc_attr( $key ) . ']" value="1" ' . checked( $inherit, true, false ) . '> Heredar de la red</label><br><span class="description">Valor efectivo: <strong>' . esc_html( is_scalar( $origin['value'] ) ? (string) $origin['value'] : '' ) . '</strong> · Origen: ' . esc_html( $origin['label'] ) . '</span></p>'; }
		echo '</td></tr>';
	}

	public static function settings_page( $fixed_tab = null ) {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$tab = $fixed_tab ? $fixed_tab : sanitize_key( $_GET['tab'] ?? 'general' );
		$tabs = array( 'general' => 'General', 'seo' => 'Contenido', 'sitemap' => 'Avanzado: Sitemap', 'indexing' => 'Avanzado: Indexación' );
		if ( ! isset( $tabs[ $tab ] ) ) $tab = 'general';
		echo '<div class="wrap digitalisimo-admin-shell"><h1>' . esc_html__( 'Digitalisimo · SEO', 'digitalisimo-integrations' ) . '</h1><h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $slug => $name ) echo '<a class="nav-tab ' . ( $tab === $slug ? 'nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=digitalisimo-seo-settings&tab=' . $slug ) ) . '">' . esc_html( $name ) . '</a>';
		echo '</h2><p class="description">Configura primero módulos y contenido. Las reglas técnicas e integraciones especializadas están agrupadas como opciones avanzadas.</p><form method="post" action="options.php">';
		settings_fields( 'digitalisimo_integrations' );
		echo '<table class="form-table" role="presentation">';
		if ( 'general' === $tab ) {
			self::field( 'enable_seo', 'Módulo SEO propio', 'checkbox', 'Añade metadatos SEO, clusters, shortcodes, breadcrumbs, schema y sitemap nativos.' );
		} elseif ( 'seo' === $tab ) {
			self::field( 'openai_api_key', 'Clave API de OpenAI', 'secret', 'Se usa solo para detectar intención de búsqueda.' );
			self::field( 'openai_model', 'Modelo OpenAI', 'text', 'Por ejemplo: gpt-4o-mini.' );
			self::field( 'keyword_post_types', 'Tipos de contenido con keywords', 'textarea', 'Usa all para mostrarlo en todos los tipos editables, o separa por comas: post,page,product.' );
			self::field( 'keyword_max_count', 'Máximo de keywords por contenido', 'number', 'Entre 1 y 20.' );
			self::field( 'keyword_auto_from_title', 'Usar título como keyword inicial', 'checkbox', 'Si no se asigna una keyword al guardar, se usa el título del contenido.' );
			echo '<tr><th scope="row">Palabras clave</th><td><p>Las keywords se configuran por entrada o página, en el metabox <strong>SEO de Digitalisimo</strong>.</p><p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=digitalisimo-keywords' ) ) . '">Gestionar keywords</a></p></td></tr>';
		} elseif ( 'sitemap' === $tab ) {
			self::field( 'sitemap_enabled', 'Activar sitemap XML', 'checkbox', 'Usa el sitemap nativo de WordPress: /wp-sitemap.xml.' );
			self::field( 'sitemap_post_types', 'Tipos de contenido incluidos', 'textarea', 'Separados por comas. Ejemplo: post,page,product. Solo se incluyen tipos públicos.' );
			self::field( 'sitemap_exclude_ids', 'IDs excluidos del sitemap', 'textarea', 'Separados por comas. Los contenidos marcados noindex siempre se excluyen.' );
		} elseif ( 'indexing' === $tab ) {
			self::field( 'noindex_search', 'Resultados de búsqueda internos', 'checkbox' );
			self::field( 'noindex_authors', 'Archivos de autor', 'checkbox', 'Útil cuando las páginas de autor no aportan contenido único.' );
			self::field( 'noindex_empty_tags', 'Tags vacíos', 'checkbox' );
			self::field( 'noindex_date_archives', 'Archivos por fecha', 'checkbox' );
			self::field( 'noindex_attachments', 'Páginas de adjuntos', 'checkbox' );
			self::field( 'noindex_elementor', 'Plantillas de Elementor', 'checkbox' );
			self::field( 'noindex_woo_pages', 'Carrito, checkout, gracias y mi cuenta', 'checkbox' );
			self::field( 'noindex_page_slugs', 'Slugs de páginas de prueba o privadas', 'textarea', 'Uno por línea. Ejemplo: prueba, gracias-por-contactar.' );
			self::field( 'noindex_page_ids', 'IDs de páginas de prueba o privadas', 'textarea', 'Separados por comas.' );
		}
		echo '</table>'; submit_button(); echo '</form><script>document.querySelectorAll(".digitalisimo-network-inherit").forEach(function(c){var f=document.getElementById(c.dataset.field);function s(){if(f)f.disabled=c.checked;}c.addEventListener("change",s);s();});</script></div>';
	}

	public static function keyword_settings_page() { self::settings_page( 'seo' ); }
	public static function sitemap_settings_page() { self::settings_page( 'sitemap' ); }
	public static function indexing_settings_page() { self::settings_page( 'indexing' ); }

}
