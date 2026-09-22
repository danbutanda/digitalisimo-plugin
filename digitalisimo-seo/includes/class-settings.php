<?php
defined( 'ABSPATH' ) || exit;

class Digitalisimo_Integrations_Settings {
	const OPTION = 'digitalisimo_integrations_options';

	/** Máscara mostrada en lugar de un secreto ya guardado. */
	const MASK = '••••••••••••';

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
			'seo_hide_login_slug'    => '',
			'seo_social_profiles'    => array(),
			'seo_local_phone_cc'     => '',
			'seo_local_schedule'     => array(),
			'content_editorial_voice' => 'Tono amigable, cercano y experto en tecnología. Explica con claridad, usa español de México y evita promesas de posicionamiento garantizado.',
			'content_editorial_context' => 'Digitalisimo es una agencia digital mexicana. Combina SEO, marketing, desarrollo web, automatización e inteligencia digital para ayudar a empresas a crecer.',
			'content_editorial_cta' => 'Cierra con una invitación útil a solicitar una asesoría con Digitalisimo, relacionada de forma natural con el tema del artículo.',
			'content_editorial_method' => 'Responde la intención de búsqueda al inicio. No repitas el H1 en el cuerpo. Usa una keyword principal de forma natural y secundarias sólo cuando aporten contexto. Organiza con H2 y H3, ejemplos, preguntas frecuentes y enlaces internos reales. Puedes abrir con un diálogo breve entre 🧑 Empresario, 🤖 MaryIA y 👨‍💼 Daniel; MaryIA habla con precisión robótica y Daniel aterriza decisiones. El diálogo aparece una sola vez al inicio.',
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
		$pages = array( 'digitalisimo-seo-settings' => array( 'SEO', 'settings_page' ), 'digitalisimo-keyword-settings' => array( 'Ajustes de keywords', 'keyword_settings_page' ), 'digitalisimo-sitemap-settings' => array( 'Ajustes de sitemap', 'sitemap_settings_page' ), 'digitalisimo-indexing-settings' => array( 'Ajustes de indexación', 'indexing_settings_page' ) );
		foreach ( $pages as $slug => $page ) add_submenu_page( null, __( $page[0], 'digitalisimo-integrations' ), __( $page[0], 'digitalisimo-integrations' ), 'manage_options', $slug, array( __CLASS__, $page[1] ) );
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
		// Cada pestaña envía solo sus propios campos: un campo ausente conserva su valor,
		// nunca se vacía. De lo contrario, guardar una pestaña borraría las demás.
		foreach ( array( 'openai_model', 'namecheap_api_user', 'namecheap_username', 'namecheap_client_ip', 'seo_site_name', 'seo_separator', 'seo_organization_type', 'seo_organization_name', 'seo_organization_url', 'seo_organization_email', 'seo_twitter_card', 'seo_twitter_user', 'seo_local_type', 'seo_local_name', 'seo_local_city', 'seo_local_region', 'seo_local_postal', 'seo_local_country', 'seo_local_latitude', 'seo_local_longitude', 'seo_local_price_range' ) as $key ) {
			if ( isset( $input[ $key ] ) ) $output[ $key ] = sanitize_text_field( $input[ $key ] );
		}
		foreach ( array( 'domain_products', 'hosting_product_ids', 'hosting_category_ids', 'ipinfo_tokens', 'sitemap_post_types', 'sitemap_exclude_ids', 'keyword_post_types', 'noindex_page_slugs', 'noindex_page_ids', 'seo_title_post', 'seo_description_post', 'seo_title_page', 'seo_description_page', 'seo_title_archive', 'seo_description_archive', 'seo_title_taxonomy', 'seo_description_taxonomy', 'seo_local_address', 'seo_local_hours', 'seo_taxonomies', 'seo_sitemap_taxonomies', 'content_editorial_voice', 'content_editorial_context', 'content_editorial_cta', 'content_editorial_method' ) as $key ) {
			if ( isset( $input[ $key ] ) ) $output[ $key ] = sanitize_textarea_field( $input[ $key ] );
		}
		if ( isset( $input['ipinfo_cache_minutes'] ) ) $output['ipinfo_cache_minutes'] = max( 0, absint( $input['ipinfo_cache_minutes'] ) );
		if ( isset( $input['keyword_max_count'] ) ) $output['keyword_max_count'] = min( 20, max( 1, absint( $input['keyword_max_count'] ) ) );
		// Las claves sólo se escriben si llega un valor nuevo; vacío conserva la almacenada.
		foreach ( array( 'openai_api_key', 'namecheap_api_key' ) as $key ) {
			if ( ! empty( $input[ $key ] ) ) $output[ $key ] = sanitize_text_field( $input[ $key ] );
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
			foreach ( self::defaults() as $key => $unused ) if ( isset( $input['network_inherit'][ $key ] ) ) $inherit[ $key ] = empty( $input['network_inherit'][ $key ] ) ? 0 : 1;
			update_option( 'digitalisimo_seo_network_inherit', $inherit, false );
		}
		return $output;
	}

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
			if ( ! empty( $in['key'] ) ) $output[ $id ]['key'] = sanitize_text_field( $in['key'] );
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
			echo '<p><input class="regular-text" type="password" autocomplete="new-password" name="' . $option . '[ai_providers][' . esc_attr( $id ) . '][key]" placeholder="' . esc_attr( $has_key ? self::MASK : 'API Key' ) . '"> <span class="digitalisimo-secret ' . ( $has_key ? 'is-set' : 'is-empty' ) . '">' . esc_html( $has_key ? 'Guardada · escribe una nueva sólo si quieres reemplazarla' : 'Sin clave configurada' ) . '</span></p>';
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
		$inherit = is_multisite() && class_exists( 'Digitalisimo_Integrations_SEO_Resolver' ) && Digitalisimo_Integrations_SEO_Resolver::inherits_network( $key );
		$disabled = $inherit ? ' disabled' : '';
		echo '<tr><th scope="row"><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
		if ( 'checkbox' === $type ) {
			echo '<input type="hidden" name="' . esc_attr( self::OPTION ) . '[' . esc_attr( $key ) . ']" value="0"><label><input class="digitalisimo-site-field" type="checkbox" id="' . esc_attr( $key ) . '" name="' . esc_attr( self::OPTION ) . '[' . esc_attr( $key ) . ']" value="1" ' . checked( $value, 1, false ) . $disabled . '> ' . esc_html__( 'Activar', 'digitalisimo-integrations' ) . '</label>';
		} elseif ( 'textarea' === $type ) {
			echo '<textarea class="large-text code digitalisimo-site-field" rows="4" id="' . esc_attr( $key ) . '" name="' . esc_attr( self::OPTION ) . '[' . esc_attr( $key ) . ']"' . $disabled . '>' . esc_textarea( $value ) . '</textarea>';
		} elseif ( 'media' === $type ) {
			Digitalisimo_Media_Field::render( $key, self::OPTION . '[' . $key . ']', $value, (bool) $inherit, 'digitalisimo-site-field' );
		} elseif ( 'select' === $type ) {
			echo '<select class="digitalisimo-site-field" id="' . esc_attr( $key ) . '" name="' . esc_attr( self::OPTION ) . '[' . esc_attr( $key ) . ']"' . $disabled . '>';
			foreach ( (array) $choices as $choice => $label_choice ) echo '<option value="' . esc_attr( $choice ) . '" ' . selected( (string) $value, (string) $choice, false ) . '>' . esc_html( $label_choice ) . '</option>';
			echo '</select>';
		} else {
			$secret       = 'secret' === $type;
			$actual_type  = $secret ? 'password' : $type;
			$actual_value = $secret ? '' : $value;
			// El valor nunca viaja al navegador: la máscara sólo va en el placeholder,
			// así el campo sigue llegando vacío y la clave almacenada se conserva.
			$placeholder  = ( $secret && ! empty( $value ) ) ? ' placeholder="' . esc_attr( self::MASK ) . '" autocomplete="new-password"' : '';
			echo '<input class="regular-text digitalisimo-site-field" type="' . esc_attr( $actual_type ) . '" id="' . esc_attr( $key ) . '" name="' . esc_attr( self::OPTION ) . '[' . esc_attr( $key ) . ']" value="' . esc_attr( $actual_value ) . '"' . $placeholder . $disabled . '>';
			if ( $secret ) echo ' <span class="digitalisimo-secret ' . ( empty( $value ) ? 'is-empty' : 'is-set' ) . '">' . esc_html( empty( $value ) ? __( 'Sin configurar', 'digitalisimo-integrations' ) : __( 'Guardada · escribe una nueva sólo si quieres reemplazarla', 'digitalisimo-integrations' ) ) . '</span>';
		}
		if ( $help ) echo '<p class="description">' . esc_html( $help ) . '</p>';
		if ( is_multisite() && class_exists( 'Digitalisimo_Integrations_SEO_Resolver' ) ) { $origin = Digitalisimo_Integrations_SEO_Resolver::option_with_origin( $key ); echo '<p><input type="hidden" name="' . esc_attr( self::OPTION ) . '[network_inherit][' . esc_attr( $key ) . ']" value="0"><label><input class="digitalisimo-network-inherit" data-field="' . esc_attr( $key ) . '" type="checkbox" name="' . esc_attr( self::OPTION ) . '[network_inherit][' . esc_attr( $key ) . ']" value="1" ' . checked( $inherit, true, false ) . '> Heredar de la red</label><br><span class="description">Valor efectivo: <strong>' . esc_html( is_scalar( $origin['value'] ) ? (string) $origin['value'] : '' ) . '</strong> · Origen: ' . esc_html( $origin['label'] ) . '</span></p>'; }
		echo '</td></tr>';
	}

	public static function settings_page( $fixed_tab = null ) {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$tab  = $fixed_tab ? $fixed_tab : sanitize_key( $_GET['tab'] ?? 'general' );
		$tabs = array( 'general' => 'General', 'seo' => 'Contenido', 'ai' => 'Proveedores IA', 'sitemap' => 'Avanzado: Sitemap', 'indexing' => 'Avanzado: Indexación' );
		if ( ! isset( $tabs[ $tab ] ) ) $tab = 'general';

		echo '<div class="wrap digitalisimo-admin-shell"><h1>' . esc_html__( 'Digitalisimo · SEO', 'digitalisimo-integrations' ) . '</h1>';
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
		echo '</form><script>document.querySelectorAll(".digitalisimo-network-inherit").forEach(function(c){var f=document.getElementById(c.dataset.field);function s(){if(f)f.disabled=c.checked;}c.addEventListener("change",s);s();});</script></div>';
	}

	/** Campos de una pestaña. Separado para poder pintarlas todas de una vez. */
	private static function tab_fields( $tab ) {
		if ( 'general' === $tab ) {
			self::field( 'enable_seo', 'Módulo SEO propio', 'checkbox', 'Añade metadatos SEO, clusters, shortcodes, breadcrumbs, schema y sitemap nativos.' );
			self::hide_login_field();
		} elseif ( 'seo' === $tab ) {
			self::field( 'openai_api_key', 'Clave API de OpenAI', 'secret', 'Se usa solo para detectar intención de búsqueda.' );
			self::field( 'openai_model', 'Modelo OpenAI', 'text', 'Por ejemplo: gpt-4o-mini.' );
			self::field( 'keyword_post_types', 'Tipos de contenido con keywords', 'textarea', 'Usa all para mostrarlo en todos los tipos editables, o separa por comas: post,page,product.' );
			self::field( 'keyword_max_count', 'Máximo de keywords por contenido', 'number', 'Entre 1 y 20.' );
			self::field( 'keyword_auto_from_title', 'Usar título como keyword inicial', 'checkbox', 'Si no se asigna una keyword al guardar, se usa el título del contenido.' );
			echo '<tr><th scope="row">Palabras clave</th><td><p>Las keywords se configuran por entrada o página, en el metabox <strong>SEO de Digitalisimo</strong>.</p><p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=digitalisimo-keywords' ) ) . '">Gestionar keywords</a></p></td></tr>';
		} elseif ( 'ai' === $tab ) {
			self::ai_fields();
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
	}

	/** Ruta privada de acceso: sustituye a wp-login.php y a /wp-admin para las visitas sin sesión. */
	private static function hide_login_field() {
		$slug = Digitalisimo_Integrations_Hide_Login::slug();
		$help = $slug
			? 'Acceso activo en ' . Digitalisimo_Integrations_Hide_Login::login_url() . ' · Guarda esta URL: wp-login.php, /wp-admin, /admin y /dashboard ya no responden sin sesión.'
			: 'Escribe una sola palabra o guiones (ejemplo: acceso-digitalisimo). Déjalo vacío para conservar el acceso estándar de WordPress.';
		self::field( 'seo_hide_login_slug', 'Ruta privada de acceso', 'text', $help );
		echo '<tr><th scope="row"></th><td><p class="description">Con la sesión iniciada, /wp-admin funciona igual que siempre. Si pierdes la ruta, añade <code>define( \'DIGITALISIMO_HIDE_LOGIN_DISABLE\', true );</code> a wp-config.php o desactiva el plugin para recuperar el acceso.</p></td></tr>';
	}

	public static function keyword_settings_page() { self::settings_page( 'seo' ); }
	public static function sitemap_settings_page() { self::settings_page( 'sitemap' ); }
	public static function indexing_settings_page() { self::settings_page( 'indexing' ); }

}
