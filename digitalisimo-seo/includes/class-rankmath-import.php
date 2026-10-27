<?php
defined( 'ABSPATH' ) || exit;

/**
 * Importador completo de Rank Math (RM1 de docs/SEO_RANKMATH_OBJECTIVE.md).
 *
 * Lee metas, opciones y la tabla de redirecciones directamente, así que funciona con Rank Math
 * desactivado. Corre en cola por lotes (cron, y desde la pantalla si el cron no avanza), es
 * idempotente y nunca pisa un valor que el sitio ya editó en DIGITALÍSIMO. Lo que no se puede
 * traducir con certeza queda en el informe y no se activa.
 *
 * Las funciones `map_*` y `translate_template()` son puras: reciben los valores y devuelven qué
 * escribir, para que la prueba no necesite WordPress.
 */
class Digitalisimo_Integrations_RankMath_Import {
	const JOB    = 'digitalisimo_seo_rankmath_job';
	const LOCK   = 'digitalisimo_seo_rankmath_lock';
	const HOOK   = 'digitalisimo_seo_rankmath_batch';
	const BATCH  = 100;
	const BUDGET = 20;
	const PHASES = array( 'options', 'posts', 'terms', 'redirections', 'done' );

	/** Textos por defecto de Rank Math en inglés: no se importan sobre los propios en español. */
	const ENGLISH_DEFAULTS = array( 'Home', 'Archives for %s', 'Results for %s', '404 Error: page not found' );

	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'run_batch' ) );
		add_action( 'admin_post_digitalisimo_seo_rankmath_import', array( __CLASS__, 'handle_start' ) );
		add_action( 'admin_post_digitalisimo_seo_rankmath_import_network', array( __CLASS__, 'handle_network' ) );
	}

	/** Texto de cada nota del informe. */
	public static function note_labels() {
		return array(
			'title_vars'        => 'Títulos o descripciones con variables sin equivalente: se quitaron esas variables.',
			'twitter_differs'   => 'Contenidos con título, descripción o imagen de Twitter distintos de los de Facebook: DIGITALÍSIMO usa un solo juego social (se importó el de Facebook).',
			'image_preview'     => 'Contenidos con max-image-preview distinto de «large»: WordPress publica «large» y no se cambió.',
			'schema_other'      => 'Schemas sin equivalente directo en el selector de tipo (quedan sin importar; revisa su JSON-LD).',
			'schema_legacy'     => 'Contenidos con el formato antiguo de rich snippet de Rank Math (no se importan).',
			'news_robots'       => 'Contenidos excluidos sólo de Google News: llegará con el sitemap de noticias (RM4).',
			'gtin'              => 'GTIN de Rank Math que no pasó al campo GTIN de WooCommerce: no es un GTIN válido, el producto ya tenía uno, otro producto lo usa o WooCommerce no está activo.',
			'schema_default'    => 'Tipos de contenido con un schema por defecto de Rank Math sin equivalente (libro, receta, evento…): quedan en Automático.',
			'term_canonical'    => 'Términos con canonical propio: los términos aún no tienen canonical editable.',
			'term_social'       => 'Términos con datos sociales propios: los términos aún no los tienen.',
			'users'             => 'Usuarios con título, descripción o robots propios de su archivo de autor (no se importan).',
			'local_phone'       => 'Teléfonos de SEO local: revísalos en SEO → Local (el formato es distinto).',
			'local_hours'       => 'Horario de SEO local: revísalo en SEO → Local (el formato es distinto).',
			'local_review'      => 'Se importaron datos de negocio local: actívalo en SEO → Local cuando los revises.',
			'custom_webmaster'  => 'Etiquetas de verificación en HTML libre: pásalas a «Otras etiquetas» como nombre|contenido.',
			'robots_txt'        => 'robots.txt personalizado: no se copia porque podría bloquear el rastreo. Revísalo abajo.',
			'fallback_home'     => 'Rank Math redirigía todos los 404 a la portada: no se importa (Google lo trata como soft 404).',
			'indexnow_key'      => 'Clave IndexNow de Rank Math: no se copia; genera la propia en SEO AI → IndexNow.',
			'redirect_trashed'  => 'Redirecciones en la papelera de Rank Math (no se importan).',
			'redirect_invalid'  => 'Redirecciones con un origen o destino inválido (por ejemplo una expresión regular rota): no se importan.',
			'redirect_existing' => 'Redirecciones ya importadas antes (se omitieron).',
			'option_kept'       => 'Ajustes que ya estaban personalizados en DIGITALÍSIMO (se conservaron).',
		);
	}

	/* ------------------------------------------------------------------ */
	/* Funciones puras.                                                     */
	/* ------------------------------------------------------------------ */

	/** Desserializa sin admitir objetos. */
	public static function value( $raw ) {
		if ( is_string( $raw ) && is_serialized( $raw ) ) { $value = @unserialize( trim( $raw ), array( 'allowed_classes' => false ) ); return false === $value && 'b:0;' !== trim( $raw ) ? null : $value; } // phpcs:ignore
		return $raw;
	}

	/**
	 * Traduce una plantilla de Rank Math a las variables de DIGITALÍSIMO.
	 *
	 * @return array{0:string,1:string[]} Texto traducido y variables quitadas por no tener equivalente.
	 */
	public static function translate_template( $template, array $ctx = array() ) {
		$aliases   = array( 'excerpt_only' => '%excerpt%', 'term_description' => '%description%', 'pagenumber' => '%page%', 'primary_taxonomy_terms' => '%category%', 'post_author' => '%name%' );
		$supported = array_keys( Digitalisimo_Integrations_SEO_Variables::catalog() );
		$unknown   = array();
		$text      = preg_replace_callback( '/%([a-z_]+)(\([^)%]*\))?%/i', function( $m ) use ( $aliases, $supported, &$unknown ) {
			$name = strtolower( $m[1] );
			if ( in_array( $name, $supported, true ) && ( empty( $m[2] ) || in_array( $name, Digitalisimo_Integrations_SEO_Variables::WITH_ARGS, true ) ) ) return '%' . $name . ( $m[2] ?? '' ) . '%';
			if ( array_key_exists( $name, $aliases ) ) return $aliases[ $name ];
			$unknown[ $name ] = true;
			return '';
		}, (string) $template );
		// Un separador que queda en un extremo tras quitar una variable sobra.
		$text = preg_replace( '/^\s*(%sep%\s*)+|(\s*%sep%)+\s*$/', '', (string) $text );
		return array( trim( preg_replace( '/\s+/', ' ', $text ) ), array_keys( $unknown ) );
	}

	/** Robots efectivos de Rank Math como lista. */
	private static function robots_list( $value ) { return array_map( 'strtolower', array_filter( array_map( 'strval', (array) self::value( $value ) ) ) ); }
	private static function on( $value ) { return in_array( $value, array( 'on', '1', 1, true ), true ); }
	private static function filled( $value ) { return null !== $value && '' !== $value && array() !== $value; }

	/** Tipo del selector de schema que corresponde a un `@type` de Rank Math, o ''. */
	public static function schema_choice( $type ) {
		$map = array( 'article' => 'Article', 'blogposting' => 'Article', 'newsarticle' => 'Article', 'webpage' => 'WebPage', 'faqpage' => 'FAQPage', 'service' => 'Service', 'product' => 'Product', 'localbusiness' => 'LocalBusiness', 'person' => 'Person' );
		return $map[ strtolower( (string) $type ) ] ?? '';
	}

	/**
	 * Qué escribir en un contenido.
	 *
	 * @param array $rm      Metas de Rank Math (clave => valor ya desserializado).
	 * @param array $current Metas actuales de DIGITALÍSIMO sin prefijo (clave => valor; ausente si la meta no existe).
	 * @return array{set:array,notes:string[]}
	 */
	public static function map_post( array $rm, array $current, array $ctx = array() ) {
		$set   = array();
		$notes = array();
		$empty = function( $key ) use ( $current ) { return ! isset( $current[ $key ] ) || '' === $current[ $key ] || array() === $current[ $key ]; };
		foreach ( array( 'rank_math_title' => 'title', 'rank_math_description' => 'description' ) as $from => $to ) {
			if ( ! self::filled( $rm[ $from ] ?? null ) || ! $empty( $to ) ) continue;
			list( $text, $unknown ) = self::translate_template( $rm[ $from ], $ctx );
			if ( $unknown ) $notes[] = 'title_vars';
			if ( '' !== $text ) $set[ $to ] = $text;
		}
		if ( self::filled( $rm['rank_math_focus_keyword'] ?? null ) && $empty( 'keywords' ) ) {
			$keywords = array_slice( array_values( array_unique( array_filter( array_map( 'trim', explode( ',', (string) $rm['rank_math_focus_keyword'] ) ) ) ) ), 0, max( 1, (int) ( $ctx['keyword_max'] ?? 5 ) ) );
			if ( $keywords ) $set['keywords'] = implode( ',', $keywords );
		}
		if ( self::filled( $rm['rank_math_canonical_url'] ?? null ) && $empty( 'canonical' ) ) $set['canonical'] = (string) $rm['rank_math_canonical_url'];
		// Una meta de robots ya guardada (aunque sea vacía) es una decisión del sitio: no se toca.
		if ( isset( $rm['rank_math_robots'] ) && ! array_key_exists( 'noindex', $current ) ) {
			$robots = self::robots_list( $rm['rank_math_robots'] );
			foreach ( array( 'noindex', 'nofollow', 'noarchive', 'noimageindex', 'nosnippet' ) as $flag ) if ( in_array( $flag, $robots, true ) ) $set[ $flag ] = '1';
		}
		$advanced = (array) self::value( $rm['rank_math_advanced_robots'] ?? array() );
		foreach ( array( 'max-snippet' => 'max_snippet', 'max-video-preview' => 'max_video_preview' ) as $from => $to ) {
			if ( isset( $advanced[ $from ] ) && '' !== (string) $advanced[ $from ] && -1 !== (int) $advanced[ $from ] && $empty( $to ) ) $set[ $to ] = (string) max( 0, (int) $advanced[ $from ] );
		}
		if ( ! empty( $advanced['max-image-preview'] ) && 'large' !== $advanced['max-image-preview'] ) $notes[] = 'image_preview';
		if ( 'on' === ( $rm['rank_math_pillar_content'] ?? '' ) && $empty( 'pillar' ) ) $set['pillar'] = 'on';
		foreach ( array( 'rank_math_facebook_title' => 'social_title', 'rank_math_facebook_description' => 'social_description', 'rank_math_facebook_image' => 'social_image', 'rank_math_breadcrumb_title' => 'breadcrumb_title' ) as $from => $to ) {
			if ( self::filled( $rm[ $from ] ?? null ) && $empty( $to ) ) $set[ $to ] = (string) $rm[ $from ];
		}
		if ( 'off' === ( $rm['rank_math_twitter_use_facebook'] ?? '' ) && ( self::filled( $rm['rank_math_twitter_title'] ?? null ) || self::filled( $rm['rank_math_twitter_description'] ?? null ) || self::filled( $rm['rank_math_twitter_image'] ?? null ) ) ) $notes[] = 'twitter_differs';
		$choice  = '';
		foreach ( $rm as $key => $value ) {
			if ( 0 === strpos( $key, 'rank_math_primary_' ) && strlen( $key ) > 18 && absint( $value ) ) {
				$taxonomy = sanitize_key( substr( $key, 18 ) );
				if ( $taxonomy && $empty( 'primary_' . $taxonomy ) ) $set[ 'primary_' . $taxonomy ] = (string) absint( $value );
			}
			if ( 0 === strpos( $key, 'rank_math_schema_' ) ) {
				$schema = self::value( $value );
				$type   = is_array( $schema ) && isset( $schema['@type'] ) ? ( is_array( $schema['@type'] ) ? reset( $schema['@type'] ) : $schema['@type'] ) : substr( $key, 17 );
				$mapped = self::schema_choice( $type );
				if ( ! $mapped ) { $notes[] = 'schema_other'; continue; }
				// El schema principal gana a los demás.
				if ( ! $choice || ! empty( $schema['metadata']['isPrimary'] ) ) $choice = $mapped;
			}
		}
		if ( $choice && $empty( 'schema_type' ) ) $set['schema_type'] = $choice;
		if ( self::filled( $rm['rank_math_rich_snippet'] ?? null ) ) $notes[] = 'schema_legacy';
		if ( self::filled( $rm['rank_math_exclude_sitemap'] ?? null ) && $empty( 'sitemap_exclude' ) ) $set['sitemap_exclude'] = '1';
		if ( 'noindex' === ( $rm['rank_math_news_sitemap_robots'] ?? '' ) ) $notes[] = 'news_robots';
		// El GTIN pasa al campo nativo de WooCommerce, que ya lo publica; sólo si es un GTIN válido.
		if ( self::filled( $rm['_rank_math_gtin_code'] ?? null ) ) {
			$gtin = preg_replace( '/[^0-9]/', '', (string) $rm['_rank_math_gtin_code'] );
			if ( preg_match( '/^(\d{8}|\d{12,14})$/', $gtin ) ) $set['_gtin'] = $gtin; else $notes[] = 'gtin';
		}
		return array( 'set' => $set, 'notes' => array_values( array_unique( $notes ) ) );
	}

	/** Qué escribir en un término (mismas reglas: nunca pisa lo editado). */
	public static function map_term( array $rm, array $current, array $ctx = array() ) {
		$set   = array();
		$notes = array();
		foreach ( array( 'rank_math_title' => 'title', 'rank_math_description' => 'description' ) as $from => $to ) {
			if ( ! self::filled( $rm[ $from ] ?? null ) || ( isset( $current[ $to ] ) && '' !== $current[ $to ] ) ) continue;
			list( $text, $unknown ) = self::translate_template( $rm[ $from ], $ctx );
			if ( $unknown ) $notes[] = 'title_vars';
			if ( '' !== $text ) $set[ $to ] = $text;
		}
		if ( isset( $rm['rank_math_robots'] ) && ! array_key_exists( 'noindex', $current ) && in_array( 'noindex', self::robots_list( $rm['rank_math_robots'] ), true ) ) $set['noindex'] = '1';
		if ( self::filled( $rm['rank_math_canonical_url'] ?? null ) ) $notes[] = 'term_canonical';
		if ( self::filled( $rm['rank_math_facebook_title'] ?? null ) || self::filled( $rm['rank_math_facebook_image'] ?? null ) ) $notes[] = 'term_social';
		return array( 'set' => $set, 'notes' => array_values( array_unique( $notes ) ) );
	}

	/**
	 * Qué ajustes del sitio escribir. Un ajuste se importa sólo si el sitio no lo personalizó
	 * (no existe o tiene el valor por defecto).
	 *
	 * @param array $rm       Grupos de Rank Math: titles, general, sitemap, instant, modules.
	 * @param array $site     Opción del sitio de DIGITALÍSIMO.
	 * @param array $defaults Valores por defecto de DIGITALÍSIMO.
	 * @return array{set:array,notes:string[],robots_txt:string}
	 */
	public static function map_options( array $rm, array $site, array $defaults, array $ctx = array() ) {
		$t = (array) ( $rm['titles'] ?? array() );
		$g = (array) ( $rm['general'] ?? array() );
		$s = (array) ( $rm['sitemap'] ?? array() );
		$modules = (array) ( $rm['modules'] ?? array() );
		$want  = array();
		$notes = array();
		if ( isset( $t['title_separator'] ) ) $want['seo_separator'] = (string) $t['title_separator'];
		if ( ! empty( $t['website_name'] ) ) $want['seo_site_name'] = (string) $t['website_name'];
		if ( isset( $t['knowledgegraph_type'] ) ) $want['seo_organization_type'] = 'person' === $t['knowledgegraph_type'] ? 'Person' : 'Organization';
		foreach ( array( 'knowledgegraph_name' => 'seo_organization_name', 'knowledgegraph_logo' => 'seo_logo', 'url' => 'seo_organization_url', 'email' => 'seo_organization_email', 'open_graph_image' => 'seo_default_image' ) as $from => $to ) if ( ! empty( $t[ $from ] ) ) $want[ $to ] = (string) $t[ $from ];
		if ( in_array( $t['twitter_card_type'] ?? '', array( 'summary', 'summary_large_image' ), true ) ) $want['seo_twitter_card'] = $t['twitter_card_type'];
		if ( ! empty( $t['twitter_author_names'] ) ) $want['seo_twitter_user'] = '@' . ltrim( (string) $t['twitter_author_names'], '@' );
		foreach ( array( 'pt_post_title' => 'seo_title_post', 'pt_post_description' => 'seo_description_post', 'pt_page_title' => 'seo_title_page', 'pt_page_description' => 'seo_description_page', 'tax_category_title' => 'seo_title_taxonomy', 'tax_category_description' => 'seo_description_taxonomy' ) as $from => $to ) {
			if ( empty( $t[ $from ] ) ) continue;
			list( $text, $unknown ) = self::translate_template( $t[ $from ], $ctx );
			if ( $unknown ) $notes[] = 'title_vars';
			if ( '' !== $text ) $want[ $to ] = $text;
		}
		$global = self::robots_list( $t['robots_global'] ?? array() );
		if ( isset( $t['noindex_search'] ) ) $want['noindex_search'] = self::on( $t['noindex_search'] ) ? 1 : 0;
		if ( isset( $t['author_robots'] ) || isset( $t['disable_author_archives'] ) ) {
			$author = self::on( $t['author_custom_robots'] ?? '' ) ? self::robots_list( $t['author_robots'] ?? array() ) : $global;
			$want['noindex_authors'] = self::on( $t['disable_author_archives'] ?? '' ) || in_array( 'noindex', $author, true ) ? 1 : 0;
		}
		if ( isset( $t['date_archive_robots'] ) || isset( $t['disable_date_archives'] ) ) $want['noindex_date_archives'] = self::on( $t['disable_date_archives'] ?? '' ) || in_array( 'noindex', self::robots_list( $t['date_archive_robots'] ?? array() ), true ) ? 1 : 0;
		if ( isset( $t['noindex_empty_taxonomies'] ) ) $want['noindex_empty_tags'] = self::on( $t['noindex_empty_taxonomies'] ) ? 1 : 0;
		if ( ! empty( $t['local_business_type'] ) ) $want['seo_local_type'] = (string) $t['local_business_type'];
		$address = (array) self::value( $t['local_address'] ?? array() );
		foreach ( array( 'streetAddress' => 'seo_local_address', 'addressLocality' => 'seo_local_city', 'addressRegion' => 'seo_local_region', 'postalCode' => 'seo_local_postal', 'addressCountry' => 'seo_local_country' ) as $from => $to ) if ( ! empty( $address[ $from ] ) ) $want[ $to ] = (string) $address[ $from ];
		if ( ! empty( $t['geo'] ) && preg_match( '/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/', (string) $t['geo'], $geo ) ) { $want['seo_local_latitude'] = $geo[1]; $want['seo_local_longitude'] = $geo[2]; }
		if ( ! empty( $t['price_range'] ) ) $want['seo_local_price_range'] = (string) $t['price_range'];
		if ( ! empty( $t['phone_numbers'] ) || ! empty( $t['phone'] ) ) $notes[] = 'local_phone';
		if ( ! empty( $t['opening_hours'] ) ) $notes[] = 'local_hours';
		if ( $address || ! empty( $t['geo'] ) ) $notes[] = 'local_review';
		if ( isset( $g['breadcrumbs'] ) ) $want['seo_breadcrumbs'] = self::on( $g['breadcrumbs'] ) ? 1 : 0;
		foreach ( array( 'breadcrumbs_separator' => 'seo_breadcrumbs_separator', 'breadcrumbs_home_label' => 'seo_breadcrumbs_home_label', 'breadcrumbs_prefix' => 'seo_breadcrumbs_prefix', 'breadcrumbs_archive_format' => 'seo_breadcrumbs_archive_format', 'breadcrumbs_search_format' => 'seo_breadcrumbs_search_format', 'breadcrumbs_404_label' => 'seo_breadcrumbs_404_label' ) as $from => $to ) {
			if ( isset( $g[ $from ] ) && '' !== trim( (string) $g[ $from ] ) && ! in_array( trim( (string) $g[ $from ] ), self::ENGLISH_DEFAULTS, true ) ) $want[ $to ] = trim( (string) $g[ $from ] );
		}
		foreach ( array( 'breadcrumbs_home' => 'seo_breadcrumbs_show_home', 'breadcrumbs_remove_post_title' => 'seo_breadcrumbs_hide_current', 'breadcrumbs_blog_page' => 'seo_breadcrumbs_blog_page' ) as $from => $to ) if ( isset( $g[ $from ] ) ) $want[ $to ] = self::on( $g[ $from ] ) ? 1 : 0;
		$types = array();
		foreach ( $t as $key => $value ) {
			if ( ! preg_match( '/^pt_([a-z0-9_-]+)_(title|description)$/', (string) $key, $m ) || in_array( $m[1], array( 'post', 'page', 'attachment' ), true ) || '' === trim( (string) $value ) ) continue;
			list( $text, $unknown ) = self::translate_template( $value, $ctx );
			if ( $unknown ) $notes[] = 'title_vars';
			if ( '' !== $text ) $types[ $m[1] ][ $m[2] ] = $text;
		}
		if ( $types ) $want['_type_templates'] = $types;
		if ( ! empty( $g['product_brand'] ) ) $want['seo_product_brand'] = (string) $g['product_brand'];
		$defaults_by_type = array();
		foreach ( $t as $key => $value ) {
			if ( ! preg_match( '/^pt_([a-z0-9_-]+)_default_rich_snippet$/', (string) $key, $m ) || in_array( $m[1], array( 'page', 'attachment', 'product' ), true ) ) continue;
			$snippet = strtolower( (string) $value );
			if ( 'off' === $snippet ) $defaults_by_type[ $m[1] ] = 'none';
			elseif ( 'article' === $snippet ) { $article = (string) ( $t[ 'pt_' . $m[1] . '_default_article_type' ] ?? 'Article' ); $defaults_by_type[ $m[1] ] = in_array( $article, array( 'Article', 'BlogPosting', 'NewsArticle' ), true ) ? $article : 'Article'; }
			elseif ( 'service' === $snippet ) $defaults_by_type[ $m[1] ] = 'Service';
			else $notes[] = 'schema_default';
		}
		if ( $defaults_by_type ) $want['_schema_defaults'] = $defaults_by_type;
		if ( isset( $g['attachment_redirect_urls'] ) ) $want['seo_redirect_attachments'] = self::on( $g['attachment_redirect_urls'] ) ? 1 : 0;
		foreach ( array( 'google_verify' => 'seo_verify_google', 'bing_verify' => 'seo_verify_bing', 'baidu_verify' => 'seo_verify_baidu', 'yandex_verify' => 'seo_verify_yandex', 'pinterest_verify' => 'seo_verify_pinterest', 'norton_verify' => 'seo_verify_norton' ) as $from => $to ) if ( ! empty( $g[ $from ] ) ) $want[ $to ] = (string) $g[ $from ];
		if ( ! empty( $g['custom_webmaster_tags'] ) ) $notes[] = 'custom_webmaster';
		$robots_txt = trim( (string) ( $g['robots_txt_content'] ?? '' ) );
		if ( '' !== $robots_txt ) $notes[] = 'robots_txt';
		if ( isset( $g['redirections_post_redirect'] ) ) $want['seo_redirect_slug_watch'] = self::on( $g['redirections_post_redirect'] ) ? 1 : 0;
		if ( in_array( (int) ( $g['redirections_header_code'] ?? 0 ), array( 301, 302, 307 ), true ) ) $want['seo_redirect_default_code'] = (int) $g['redirections_header_code'];
		if ( 'custom' === ( $g['redirections_fallback'] ?? '' ) && ! empty( $g['redirections_custom_url'] ) ) $want['seo_redirect_fallback_url'] = (string) $g['redirections_custom_url'];
		if ( 'homepage' === ( $g['redirections_fallback'] ?? '' ) ) $notes[] = 'fallback_home';
		if ( in_array( '404-monitor', $modules, true ) ) {
			$want['seo_404_enabled'] = 1;
			if ( ! empty( $g['404_monitor_limit'] ) ) $want['seo_404_limit'] = (int) $g['404_monitor_limit'];
			$exclude = array();
			foreach ( (array) self::value( $g['404_monitor_exclude'] ?? array() ) as $row ) if ( is_array( $row ) && ! empty( $row['exclude'] ) ) $exclude[] = (string) $row['exclude'];
			if ( $exclude ) $want['seo_404_exclude'] = implode( "\n", $exclude );
			if ( isset( $g['404_monitor_ignore_query_parameters'] ) ) $want['seo_404_ignore_query'] = self::on( $g['404_monitor_ignore_query_parameters'] ) ? 1 : 0;
		}
		if ( isset( $s['include_images'] ) ) $want['seo_sitemap_images'] = self::on( $s['include_images'] ) ? 1 : 0;
		if ( ! empty( $s['exclude_posts'] ) ) $want['sitemap_exclude_ids'] = implode( ',', array_filter( array_map( 'absint', preg_split( '/[\s,;]+/', (string) $s['exclude_posts'] ) ) ) );
		if ( ! empty( ( (array) ( $rm['instant'] ?? array() ) )['indexnow_api_key'] ) ) $notes[] = 'indexnow_key';
		$profiles = array_filter( array_merge( array( (string) ( $t['social_url_facebook'] ?? '' ) ), preg_split( '/\r\n|\r|\n/', (string) ( $t['social_additional_profiles'] ?? '' ) ) ) );
		if ( $profiles ) $want['_profiles'] = array_values( array_map( 'trim', $profiles ) );

		$set = array();
		foreach ( $want as $key => $value ) {
			if ( in_array( $key, array( '_profiles', '_type_templates', '_schema_defaults' ), true ) ) { $set[ $key ] = $value; continue; }
			$edited = array_key_exists( $key, $site ) && (string) $site[ $key ] !== (string) ( $defaults[ $key ] ?? '' ) && (string) $site[ $key ] !== (string) $value;
			if ( $edited ) { $notes[] = 'option_kept'; continue; }
			$set[ $key ] = $value;
		}
		return array( 'set' => $set, 'notes' => array_values( array_unique( $notes ) ), 'robots_txt' => $robots_txt );
	}

	/**
	 * Datos de una redirección de Rank Math para `Digitalisimo_Integrations_Redirections::save()`, o null.
	 *
	 * @param array $row Fila de `{prefix}rank_math_redirections`.
	 */
	public static function map_redirection( array $row ) {
		if ( 'trashed' === ( $row['status'] ?? '' ) ) return null;
		$sources = array();
		foreach ( (array) self::value( $row['sources'] ?? '' ) as $source ) {
			if ( ! is_array( $source ) || ! isset( $source['pattern'] ) ) continue;
			$sources[] = array( 'pattern' => (string) $source['pattern'], 'comparison' => (string) ( $source['comparison'] ?? 'exact' ), 'ignore' => 'case' === ( $source['ignore'] ?? '' ) ? 'case' : '' );
		}
		if ( ! $sources ) return null;
		return array(
			'sources'       => $sources,
			'url_to'        => (string) ( $row['url_to'] ?? '' ),
			'header_code'   => (int) ( $row['header_code'] ?? 301 ),
			'status'        => 'inactive' === ( $row['status'] ?? '' ) ? 'inactive' : 'active',
			'origin'        => 'rankmath',
			'import_key'    => 'rankmath:' . absint( $row['id'] ?? 0 ),
			'hits'          => absint( $row['hits'] ?? 0 ),
			'created'       => self::datetime( $row['created'] ?? '' ),
			'updated'       => self::datetime( $row['updated'] ?? '' ),
			'last_accessed' => self::datetime( $row['last_accessed'] ?? '', null ),
		);
	}

	private static function datetime( $value, $empty = '' ) {
		$value = (string) $value;
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value ) || 0 === strpos( $value, '0000' ) ) return '' === $empty ? gmdate( 'Y-m-d H:i:s' ) : $empty;
		return $value;
	}

	/* ------------------------------------------------------------------ */
	/* Tarea por lotes.                                                     */
	/* ------------------------------------------------------------------ */

	private static function ctx() {
		return array(
			'sitedesc'    => get_bloginfo( 'description' ),
			'org_name'    => (string) Digitalisimo_Integrations_SEO_Resolver::option( 'seo_organization_name', '' ) ?: get_bloginfo( 'name' ),
			'keyword_max' => (int) Digitalisimo_Integrations_SEO_Resolver::option( 'keyword_max_count', 5 ),
		);
	}

	private static function redirections_table() { global $wpdb; return $wpdb->prefix . 'rank_math_redirections'; }
	private static function has_redirections_table() { global $wpdb; return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( self::redirections_table() ) ) ); }

	/** Cuánto hay de Rank Math en este sitio. */
	public static function detect() {
		global $wpdb;
		$like = $wpdb->esc_like( 'rank_math_' ) . '%';
		$out  = array(
			'posts'   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $like ) ),
			'terms'   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT term_id) FROM {$wpdb->termmeta} WHERE meta_key LIKE %s", $like ) ),
			'options' => (bool) ( get_option( 'rank-math-options-titles' ) || get_option( 'rank-math-options-general' ) ),
			'redirections' => self::has_redirections_table() ? (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::redirections_table() ) : 0, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
		$out['any'] = $out['posts'] || $out['terms'] || $out['options'] || $out['redirections'];
		return $out;
	}

	public static function job() { $job = get_option( self::JOB, array() ); return is_array( $job ) ? $job : array(); }
	private static function save_job( array $job ) { $job['tick'] = time(); update_option( self::JOB, $job, false ); }

	public static function start() {
		self::save_job( array( 'status' => 'running', 'phase' => 'options', 'cursor' => 0, 'started' => time(), 'done' => array(), 'notes' => array(), 'robots_txt' => '' ) );
		wp_clear_scheduled_hook( self::HOOK );
		wp_schedule_single_event( time(), self::HOOK );
	}

	/** Cerrojo atómico con token: INSERT IGNORE, nunca add_option. Caduca a los dos minutos. */
	private static function lock() {
		global $wpdb;
		$held = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK ) );
		if ( $held ) {
			if ( time() - (int) substr( strrchr( $held, '|' ), 1 ) < 2 * MINUTE_IN_SECONDS ) return false;
			$wpdb->delete( $wpdb->options, array( 'option_name' => self::LOCK, 'option_value' => $held ) );
		}
		$token = wp_generate_password( 16, false, false ) . '|' . time();
		$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", self::LOCK, $token ) );
		return $token === $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK ) ) ? $token : false;
	}
	private static function unlock( $token ) { global $wpdb; $wpdb->delete( $wpdb->options, array( 'option_name' => self::LOCK, 'option_value' => $token ) ); }

	private static function count( array &$job, $key, $n = 1 ) { $job['done'][ $key ] = (int) ( $job['done'][ $key ] ?? 0 ) + $n; }
	private static function note( array &$job, array $notes, $where = '' ) {
		foreach ( $notes as $note ) {
			$job['notes'][ $note ]['count'] = (int) ( $job['notes'][ $note ]['count'] ?? 0 ) + 1;
			if ( $where && count( $job['notes'][ $note ]['where'] ?? array() ) < 5 ) $job['notes'][ $note ]['where'][] = $where;
		}
	}

	public static function run_batch() {
		$job = self::job();
		if ( 'running' !== ( $job['status'] ?? '' ) ) return;
		$token = self::lock();
		if ( ! $token ) return;
		$started = time();
		try {
			while ( 'done' !== $job['phase'] && time() - $started < self::BUDGET ) {
				$finished = call_user_func_array( array( __CLASS__, 'phase_' . $job['phase'] ), array( &$job ) );
				if ( $finished ) { $next = array_search( $job['phase'], self::PHASES, true ) + 1; $job['phase'] = self::PHASES[ $next ]; $job['cursor'] = 0; }
				self::save_job( $job );
			}
			if ( 'done' === $job['phase'] ) { $job['status'] = 'done'; $job['finished'] = time(); self::save_job( $job ); }
			else wp_schedule_single_event( time() + 5, self::HOOK );
		} catch ( Throwable $error ) {
			$job['status'] = 'error';
			$job['error']  = $error->getMessage();
			self::save_job( $job );
		}
		self::unlock( $token );
	}

	private static function phase_options( array &$job ) {
		$rm = array( 'titles' => (array) get_option( 'rank-math-options-titles', array() ), 'general' => (array) get_option( 'rank-math-options-general', array() ), 'sitemap' => (array) get_option( 'rank-math-options-sitemap', array() ), 'instant' => (array) get_option( 'rank-math-options-instant', array() ), 'modules' => (array) get_option( 'rank_math_modules', array() ) );
		if ( ! array_filter( $rm ) ) return true;
		$site   = (array) get_option( Digitalisimo_Integrations_Settings::OPTION, array() );
		$result = self::map_options( $rm, $site, Digitalisimo_Integrations_Settings::defaults(), self::ctx() );
		$clean  = self::sanitize_options( $result['set'], $site );
		$inherit = (array) get_option( 'digitalisimo_seo_network_inherit', array() );
		foreach ( $clean as $key => $value ) {
			$site[ $key ] = $value;
			// En una red, el valor importado es una personalización del sitio: deja de heredar ese ajuste.
			if ( is_multisite() ) $inherit[ $key ] = 0;
			self::count( $job, 'options' );
		}
		if ( $clean ) {
			global $wp_filter;
			// Los valores ya están saneados con las mismas reglas que el formulario; el callback de
			// register_setting espera un envío de pestaña y podría tratar el lote como tal.
			$hook  = 'sanitize_option_' . Digitalisimo_Integrations_Settings::OPTION;
			$saved = $wp_filter[ $hook ] ?? null;
			unset( $wp_filter[ $hook ] );
			update_option( Digitalisimo_Integrations_Settings::OPTION, $site, false );
			if ( $saved ) $wp_filter[ $hook ] = $saved;
			if ( is_multisite() ) update_option( 'digitalisimo_seo_network_inherit', $inherit, false );
		}
		self::note( $job, $result['notes'] );
		$job['robots_txt'] = substr( $result['robots_txt'], 0, 5000 );
		return true;
	}

	/** Las mismas reglas que el formulario del sitio para cada clave que el importador escribe. */
	private static function sanitize_options( array $set, array $site ) {
		$out = array();
		foreach ( $set as $key => $value ) {
			switch ( $key ) {
				case '_profiles':
					$current = (array) ( $site[ Digitalisimo_Integrations_Social_Profiles::KEY ] ?? array() );
					$known   = array();
					$extra   = array();
					foreach ( $value as $url ) {
						$host = preg_replace( '/^www\./', '', strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) );
						$id   = '';
						foreach ( Digitalisimo_Integrations_Social_Profiles::networks() as $network => $meta ) if ( in_array( $host, $meta[2], true ) ) { $id = $network; break; }
						if ( $id && empty( $current[ $id ] ) && ! isset( $known[ $id ] ) ) $known[ $id ] = $url; elseif ( ! $id ) $extra[] = $url;
					}
					if ( $known ) $out[ Digitalisimo_Integrations_Social_Profiles::KEY ] = Digitalisimo_Integrations_Social_Profiles::sanitize( $known + $current, $current );
					if ( $extra && '' === trim( (string) ( $site[ Digitalisimo_Integrations_Social_Profiles::LEGACY ] ?? '' ) ) ) $out[ Digitalisimo_Integrations_Social_Profiles::LEGACY ] = Digitalisimo_Integrations_Social_Profiles::sanitize_extras( implode( "\n", $extra ) );
					break;
				case '_type_templates':
					// Sólo los tipos que el sitio aún no tiene: nunca se pisa una plantilla propia.
					$current = is_array( $site['seo_type_templates'] ?? null ) ? $site['seo_type_templates'] : array();
					$out['seo_type_templates'] = Digitalisimo_Integrations_SEO_Suite::sanitize_type_templates( $current + $value );
					break;
				case '_schema_defaults':
					$current = is_array( $site['seo_schema_type_defaults'] ?? null ) ? $site['seo_schema_type_defaults'] : array();
					$out['seo_schema_type_defaults'] = Digitalisimo_Integrations_Schema_Graph::sanitize_type_defaults( $current + $value );
					break;
				case 'seo_product_brand':
					$out[ $key ] = sanitize_key( $value );
					break;
				case 'seo_separator':
					if ( array_key_exists( $value, Digitalisimo_Integrations_SEO_Suite::separator_choices() ) ) $out[ $key ] = $value;
					break;
				case 'seo_organization_email':
					if ( is_email( $value ) ) $out[ $key ] = sanitize_email( $value );
					break;
				case 'seo_logo': case 'seo_default_image': case 'seo_organization_url':
					if ( $url = esc_url_raw( $value, array( 'http', 'https' ) ) ) $out[ $key ] = $url;
					break;
				case 'seo_local_type':
					$out[ $key ] = Digitalisimo_Integrations_Schema_Vocabulary::local_type( sanitize_text_field( $value ) );
					break;
				case 'seo_local_country':
					if ( preg_match( '/^[A-Za-z]{2}$/', $value ) ) $out[ $key ] = strtoupper( $value );
					break;
				case 'seo_redirect_default_code':
					$out[ $key ] = Digitalisimo_Integrations_Redirections::sanitize_code( $value );
					break;
				case 'seo_redirect_fallback_url':
					if ( $url = Digitalisimo_Integrations_Redirections::sanitize_target( $value ) ) $out[ $key ] = $url;
					break;
				case 'seo_404_limit':
					$out[ $key ] = Digitalisimo_Integrations_404_Monitor::sanitize_limit( $value );
					break;
				case 'seo_404_exclude':
					$out[ $key ] = Digitalisimo_Integrations_404_Monitor::sanitize_exclusions( $value );
					break;
				case 'sitemap_exclude_ids':
					$out[ $key ] = sanitize_textarea_field( $value );
					break;
				default:
					if ( in_array( $key, array_keys( Digitalisimo_Integrations_Verification::engines() ), true ) ) { if ( '' !== ( $code = Digitalisimo_Integrations_Verification::sanitize_code( $value ) ) ) $out[ $key ] = $code; }
					elseif ( is_int( $value ) ) $out[ $key ] = $value ? 1 : 0;
					else $out[ $key ] = sanitize_text_field( $value );
			}
		}
		return $out;
	}

	/** Metas de un objeto: las de Rank Math y las propias (sin prefijo), desserializadas. */
	private static function split_meta( array $all ) {
		$rm      = array();
		$current = array();
		foreach ( $all as $key => $values ) {
			$value = self::value( $values[0] ?? '' );
			if ( 0 === strpos( $key, 'rank_math_' ) || '_rank_math_gtin_code' === $key ) $rm[ $key ] = $value;
			elseif ( 0 === strpos( $key, 'digitalisimo_seo_' ) ) $current[ substr( $key, 17 ) ] = $value;
		}
		return array( $rm, $current );
	}

	private static function phase_posts( array &$job ) {
		global $wpdb;
		$ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE post_id > %d AND ( meta_key LIKE %s OR meta_key = '_rank_math_gtin_code' ) ORDER BY post_id ASC LIMIT %d", (int) $job['cursor'], $wpdb->esc_like( 'rank_math_' ) . '%', self::BATCH ) ) );
		if ( ! $ids ) return true;
		update_meta_cache( 'post', $ids );
		$ctx = self::ctx();
		foreach ( $ids as $id ) {
			list( $rm, $current ) = self::split_meta( (array) get_post_meta( $id ) );
			$result = self::map_post( $rm, $current, $ctx );
			if ( isset( $result['set']['_gtin'] ) ) {
				if ( ! self::import_gtin( $id, $result['set']['_gtin'] ) ) $result['notes'][] = 'gtin';
				else self::count( $job, 'gtin' );
				unset( $result['set']['_gtin'] );
			}
			foreach ( $result['set'] as $key => $value ) {
				$value = in_array( $key, array( 'canonical', 'social_image' ), true ) ? esc_url_raw( $value ) : sanitize_textarea_field( $value );
				if ( '' === $value ) continue;
				update_post_meta( $id, 'digitalisimo_seo_' . $key, $value );
			}
			if ( $result['set'] ) self::count( $job, 'posts' );
			self::note( $job, $result['notes'], (string) $id );
			$job['cursor'] = $id;
		}
		return count( $ids ) < self::BATCH;
	}

	/** GTIN al campo de WooCommerce, sólo si el producto no tiene uno; WooCommerce rechaza un GTIN repetido. */
	private static function import_gtin( $post_id, $gtin ) {
		if ( ! function_exists( 'wc_get_product' ) ) return false;
		$product = wc_get_product( $post_id );
		if ( ! $product || ! is_callable( array( $product, 'set_global_unique_id' ) ) || '' !== (string) $product->get_global_unique_id() ) return false;
		try { $product->set_global_unique_id( $gtin ); $product->save(); } catch ( Throwable $error ) { return false; }
		return $gtin === (string) wc_get_product( $post_id )->get_global_unique_id();
	}

	private static function phase_terms( array &$job ) {
		global $wpdb;
		$ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT term_id FROM {$wpdb->termmeta} WHERE term_id > %d AND meta_key LIKE %s ORDER BY term_id ASC LIMIT %d", (int) $job['cursor'], $wpdb->esc_like( 'rank_math_' ) . '%', self::BATCH ) ) );
		if ( ! $ids ) {
			// Los archivos de autor no tienen destino todavía: sólo se informa cuántos había.
			$users = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key IN ('rank_math_title','rank_math_description','rank_math_robots') AND meta_value <> %s", '' ) );
			if ( $users ) { $job['notes']['users']['count'] = $users; }
			return true;
		}
		update_meta_cache( 'term', $ids );
		$ctx = self::ctx();
		foreach ( $ids as $id ) {
			list( $rm, $current ) = self::split_meta( (array) get_term_meta( $id ) );
			$result = self::map_term( $rm, $current, $ctx );
			foreach ( $result['set'] as $key => $value ) update_term_meta( $id, 'digitalisimo_seo_' . $key, sanitize_textarea_field( $value ) );
			if ( $result['set'] ) self::count( $job, 'terms' );
			self::note( $job, $result['notes'], 'term:' . $id );
			$job['cursor'] = $id;
		}
		return count( $ids ) < self::BATCH;
	}

	private static function phase_redirections( array &$job ) {
		global $wpdb;
		if ( ! self::has_redirections_table() ) return true;
		Digitalisimo_Integrations_Redirections::maybe_install();
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::redirections_table() . ' WHERE id > %d ORDER BY id ASC LIMIT %d', (int) $job['cursor'], self::BATCH ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( (array) $rows as $row ) {
			$job['cursor'] = (int) $row['id'];
			$data = self::map_redirection( $row );
			if ( ! $data ) { self::note( $job, array( 'trashed' === $row['status'] ? 'redirect_trashed' : 'redirect_invalid' ), (string) $row['id'] ); continue; }
			if ( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . Digitalisimo_Integrations_Redirections::table() . ' WHERE import_key = %s', $data['import_key'] ) ) ) { self::note( $job, array( 'redirect_existing' ) ); continue; } // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$saved = Digitalisimo_Integrations_Redirections::save( $data, 0, false );
			if ( is_wp_error( $saved ) ) self::note( $job, array( 'redirect_invalid' ), (string) $row['id'] );
			else self::count( $job, 'redirections' );
		}
		$finished = count( (array) $rows ) < self::BATCH;
		if ( $finished ) Digitalisimo_Integrations_Redirections::refresh_index();
		return $finished;
	}

	/* ------------------------------------------------------------------ */
	/* Administración.                                                      */
	/* ------------------------------------------------------------------ */

	public static function handle_start() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'digitalisimo_seo_rankmath_import' ) ) wp_die( 'No autorizado.' );
		self::start();
		self::run_batch(); // El primer lote corre ya: en sitios pequeños la importación termina aquí.
		wp_safe_redirect( admin_url( 'admin.php?page=digitalisimo-seo-import&rankmath=started' ) );
		exit;
	}

	/** En una red encola cada sitio con datos de Rank Math; cada uno se importa en sus propias peticiones. */
	public static function handle_network() {
		if ( ! is_multisite() || ! current_user_can( 'manage_network_options' ) || ! is_super_admin() || ! check_admin_referer( 'digitalisimo_seo_rankmath_network', 'digitalisimo_rankmath_nonce' ) ) wp_die( 'No autorizado.' );
		$queued = 0;
		foreach ( get_sites( array( 'number' => 0, 'fields' => 'ids' ) ) as $site_id ) {
			switch_to_blog( (int) $site_id );
			if ( self::detect()['any'] && 'running' !== ( self::job()['status'] ?? '' ) ) { self::start(); $queued++; }
			restore_current_blog();
		}
		wp_safe_redirect( network_admin_url( 'admin.php?page=digitalisimo-network-seo&tab=import&rankmath_queued=' . $queued ) );
		exit;
	}

	private static function status_label( array $job ) {
		$phases = array( 'options' => 'ajustes', 'posts' => 'contenidos', 'terms' => 'categorías y etiquetas', 'redirections' => 'redirecciones', 'done' => 'terminado' );
		if ( ! $job ) return 'Sin importar';
		if ( 'done' === $job['status'] ) return 'Terminado el ' . wp_date( 'Y-m-d H:i', (int) ( $job['finished'] ?? time() ) );
		if ( 'error' === $job['status'] ) return 'Detenido por un error: ' . ( $job['error'] ?? '' );
		return 'En curso · ' . ( $phases[ $job['phase'] ] ?? $job['phase'] );
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$job = self::job();
		// Si el cron del sitio no avanza, la pantalla empuja un lote.
		if ( 'running' === ( $job['status'] ?? '' ) && time() - (int) ( $job['tick'] ?? 0 ) > MINUTE_IN_SECONDS ) { self::run_batch(); $job = self::job(); }
		$found = self::detect();
		echo '<h2>Importar desde Rank Math</h2><p>Trae a DIGITALÍSIMO SEO los datos que Rank Math guardó en este sitio, aunque esté desactivado: títulos, descripciones, keywords, canonical, robots, datos sociales, categoría principal, tipo de schema, ajustes generales, verificación de buscadores y redirecciones. Nunca sobrescribe lo que ya editaste aquí y se puede repetir sin duplicar nada. Lo que no tiene equivalente queda en el informe.</p>';
		echo '<table class="widefat striped" style="max-width:640px"><tbody><tr><th>Contenidos con datos de Rank Math</th><td>' . esc_html( number_format_i18n( $found['posts'] ) ) . '</td></tr><tr><th>Categorías y etiquetas</th><td>' . esc_html( number_format_i18n( $found['terms'] ) ) . '</td></tr><tr><th>Ajustes generales</th><td>' . ( $found['options'] ? 'Sí' : 'No' ) . '</td></tr><tr><th>Redirecciones</th><td>' . esc_html( number_format_i18n( $found['redirections'] ) ) . '</td></tr><tr><th>Estado</th><td>' . esc_html( self::status_label( $job ) ) . '</td></tr></tbody></table>';
		if ( 'running' === ( $job['status'] ?? '' ) ) echo '<p class="description">La importación sigue en segundo plano. Recarga esta pantalla para ver el avance.</p>';
		elseif ( $found['any'] ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="digitalisimo_seo_rankmath_import">';
			wp_nonce_field( 'digitalisimo_seo_rankmath_import' );
			submit_button( $job ? 'Volver a importar' : 'Importar desde Rank Math', 'primary', 'submit', false );
			echo '</form>';
		} else echo '<p class="description">No hay datos de Rank Math en este sitio.</p>';
		if ( ! $job ) return;
		$done = (array) ( $job['done'] ?? array() );
		echo '<h3>Informe</h3><ul><li>Ajustes importados: ' . esc_html( (int) ( $done['options'] ?? 0 ) ) . '</li><li>Contenidos actualizados: ' . esc_html( (int) ( $done['posts'] ?? 0 ) ) . '</li><li>Términos actualizados: ' . esc_html( (int) ( $done['terms'] ?? 0 ) ) . '</li><li>Redirecciones importadas: ' . esc_html( (int) ( $done['redirections'] ?? 0 ) ) . '</li><li>GTIN pasados a WooCommerce: ' . esc_html( (int) ( $done['gtin'] ?? 0 ) ) . '</li></ul>';
		$labels = self::note_labels();
		if ( ! empty( $job['notes'] ) ) {
			echo '<h3>Revisar</h3><table class="widefat striped"><thead><tr><th>Qué</th><th>Cuántos</th><th>Ejemplos</th></tr></thead><tbody>';
			foreach ( $job['notes'] as $code => $note ) echo '<tr><td>' . esc_html( $labels[ $code ] ?? $code ) . '</td><td>' . esc_html( (int) $note['count'] ) . '</td><td>' . esc_html( implode( ', ', (array) ( $note['where'] ?? array() ) ) ) . '</td></tr>';
			echo '</tbody></table>';
		}
		if ( ! empty( $job['robots_txt'] ) ) echo '<h3>robots.txt de Rank Math</h3><p class="description">Si necesitas alguna de estas reglas, cópiala en SEO → Robots → Reglas adicionales.</p><pre style="max-height:300px;overflow:auto;white-space:pre-wrap;background:#fff;border:1px solid var(--digi-line,#dce4ff);padding:12px">' . esc_html( $job['robots_txt'] ) . '</pre>';
	}

	/** Pestaña de red: estado de cada sitio y un botón que encola los que tienen datos. */
	public static function render_network() {
		$queued = isset( $_GET['rankmath_queued'] ) ? absint( $_GET['rankmath_queued'] ) : null; // phpcs:ignore WordPress.Security.NonceVerification
		echo '<tr><td colspan="2">';
		if ( null !== $queued ) echo '<div class="notice notice-success inline"><p>' . esc_html( sprintf( 'Importación encolada en %d sitios. Cada sitio avanza con su propio cron.', $queued ) ) . '</p></div>';
		echo '<p>Importa en cada sitio de la red los datos de Rank Math que guardó ese sitio. Cada sitio sólo modifica sus propios datos y nunca sobrescribe lo que ya personalizó.</p>';
		$url = wp_nonce_url( add_query_arg( 'action', 'digitalisimo_seo_rankmath_import_network', admin_url( 'admin-post.php' ) ), 'digitalisimo_seo_rankmath_network', 'digitalisimo_rankmath_nonce' );
		echo '<p><button type="submit" class="button button-primary" formaction="' . esc_url( $url ) . '" formnovalidate>Importar Rank Math en todos los sitios</button></p>';
		echo '<table class="widefat striped"><thead><tr><th>Sitio</th><th>Estado</th></tr></thead><tbody>';
		foreach ( get_sites( array( 'number' => 200 ) ) as $site ) {
			$job = get_blog_option( (int) $site->blog_id, self::JOB, array() );
			echo '<tr><td><a href="' . esc_url( get_admin_url( (int) $site->blog_id, 'admin.php?page=digitalisimo-seo-import' ) ) . '">' . esc_html( get_home_url( (int) $site->blog_id, '/' ) ) . '</a></td><td>' . esc_html( self::status_label( is_array( $job ) ? $job : array() ) ) . '</td></tr>';
		}
		echo '</tbody></table></td></tr>';
	}
}
