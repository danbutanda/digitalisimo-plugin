<?php
defined( 'ABSPATH' ) || exit;

/**
 * llms.txt de cada sitio: Markdown con un H1, la descripción y enlaces a sus
 * recursos públicos. No depende del módulo AI y Chatbot.
 *
 * Todo sale del propio sitio (nombre, descripción, home_url(), páginas
 * publicadas e indexables, sitemap): nada se escribe a mano en el código. La
 * salida se valida antes de servirse; un contenido manual inválido nunca se
 * publica, en su lugar se sirve el automático.
 */
class Digitalisimo_Integrations_LLMS {
	const MODES     = array( 'automatic', 'manual', 'hybrid' );
	const CACHE     = 'llms';
	const MAX_BYTES = 102400;
	const MAX_URLS  = 50;

	public static function init() {
		add_action( 'parse_request', array( __CLASS__, 'maybe_serve' ), 0 );
		foreach ( array( 'save_post', 'deleted_post', 'trashed_post' ) as $hook ) add_action( $hook, array( __CLASS__, 'forget' ) );
	}

	public static function sanitize_mode( $value ) {
		return in_array( $value, self::MODES, true ) ? $value : 'automatic';
	}

	/** Una URL http(s) por línea, sin repetir. */
	public static function sanitize_urls( $value ) {
		$urls = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $value ) as $line ) {
			$url = esc_url_raw( trim( $line ) );
			if ( '' === $url || ! preg_match( '~^https?://~i', $url ) ) continue;
			$urls[ $url ] = true;
			if ( count( $urls ) >= self::MAX_URLS ) break;
		}
		return implode( "\n", array_keys( $urls ) );
	}

	public static function enabled() {
		return (bool) Digitalisimo_Integrations_Settings::get( 'seo_ai_llms_enabled' );
	}

	public static function mode() {
		return self::sanitize_mode( Digitalisimo_Integrations_Settings::get( 'seo_ai_llms_mode' ) );
	}

	public static function endpoint() {
		return home_url( '/llms.txt' );
	}

	/** /llms.txt del sitio en curso: funciona en subdominios y subdirectorios sin reglas de reescritura. */
	public static function requested() {
		$path   = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
		$target = (string) wp_parse_url( self::endpoint(), PHP_URL_PATH );
		return '' !== $path && rawurldecode( $path ) === $target;
	}

	public static function maybe_serve() {
		if ( self::requested() && self::enabled() ) self::serve();
	}

	public static function serve() {
		$result = self::result();
		if ( empty( $result['valid'] ) ) return; // Nunca se publica un archivo inválido: WordPress responde 404.
		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		echo $result['content']; // Markdown plano; no se interpreta como HTML.
		exit;
	}

	public static function forget() {
		if ( class_exists( 'Digitalisimo_Integrations_Performance_Cache' ) ) Digitalisimo_Integrations_Performance_Cache::delete( self::CACHE );
	}

	/* ------------------------------------------------------------------ *
	 * Validación
	 * ------------------------------------------------------------------ */

	/** H1 al principio, uno solo, al menos un enlace http(s) válido, sin HTML y con tamaño acotado. */
	public static function validate( $text ) {
		$text   = (string) $text;
		$errors = array();
		$lines  = preg_split( '/\r\n|\r|\n/', trim( $text ) );
		$first  = '';
		foreach ( $lines as $line ) if ( '' !== trim( $line ) ) { $first = trim( $line ); break; }
		if ( ! preg_match( '/^#\s+\S/', $first ) ) $errors[] = 'Debe empezar con un H1 Markdown («# Nombre del sitio»).';
		$h1 = 0;
		foreach ( $lines as $line ) if ( preg_match( '/^#\s+\S/', trim( $line ) ) ) ++$h1;
		if ( $h1 > 1 ) $errors[] = 'Tiene ' . $h1 . ' H1: debe haber uno solo.';
		$links = 0;
		if ( preg_match_all( '/\[([^\]\n]+)\]\(([^)\s]+)\)/', $text, $found, PREG_SET_ORDER ) ) {
			foreach ( $found as $link ) if ( false !== filter_var( $link[2], FILTER_VALIDATE_URL ) && preg_match( '~^https?://~i', $link[2] ) ) ++$links;
		}
		if ( ! $links ) $errors[] = 'Debe contener al menos un enlace Markdown válido («[Título](https://…)»).';
		if ( preg_match( '/<\s*(?:script|style|iframe|a|div|span|p|img)\b/i', $text ) ) $errors[] = 'Contiene HTML: llms.txt es Markdown plano.';
		if ( strlen( $text ) > self::MAX_BYTES ) $errors[] = 'Supera ' . (int) ( self::MAX_BYTES / 1024 ) . ' KB.';
		return array( 'valid' => ! $errors, 'errors' => $errors, 'links' => $links, 'title' => preg_match( '/^#\s+(.+)$/', $first, $m ) ? trim( $m[1] ) : '' );
	}

	/* ------------------------------------------------------------------ *
	 * Construcción
	 * ------------------------------------------------------------------ */

	private static function plain( $value ) {
		$value = html_entity_decode( wp_strip_all_tags( (string) $value ), ENT_QUOTES, 'UTF-8' );
		return trim( preg_replace( '/\s+/u', ' ', $value ) );
	}

	/** Texto de un enlace Markdown: sin saltos y con corchetes escapados. */
	public static function link_text( $value ) {
		return str_replace( array( '[', ']' ), array( '\\[', '\\]' ), self::plain( $value ) );
	}

	public static function link_url( $url ) {
		return esc_url_raw( str_replace( array( ' ', '(', ')' ), array( '%20', '%28', '%29' ), trim( (string) $url ) ) );
	}

	public static function link( $title, $url ) {
		return '- [' . self::link_text( $title ) . '](' . self::link_url( $url ) . ')';
	}

	private static function key( $url ) {
		return untrailingslashit( strtolower( preg_replace( '~^https?://~i', '', strtok( (string) $url, '#' ) ) ) );
	}

	private static function indexable( $post ) {
		if ( post_password_required( $post ) ) return false;
		$robots = (array) get_post_meta( $post->ID, 'digitalisimo_seo_robots', true );
		return ! in_array( 'noindex', $robots, true );
	}

	private static function title_for( $url ) {
		$id = url_to_postid( $url );
		if ( $id ) {
			$title = self::plain( get_the_title( $id ) );
			if ( '' !== $title ) return $title;
		}
		$path = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
		$last = '' === $path ? (string) wp_parse_url( $url, PHP_URL_HOST ) : basename( $path );
		return ucfirst( str_replace( array( '-', '_' ), ' ', rawurldecode( $last ) ) );
	}

	/** Recursos: URLs elegidas o, sin selección, páginas publicadas e indexables y contenido reciente. */
	public static function automatic() {
		$name = self::plain( Digitalisimo_Integrations_Settings::get( 'seo_site_name' ) );
		if ( '' === $name ) $name = self::plain( get_bloginfo( 'name' ) );
		if ( '' === $name ) $name = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$description = self::plain( get_bloginfo( 'description' ) );
		$home  = home_url( '/' );
		$seen  = array( self::key( $home ) => true );
		$lines = array( '# ' . str_replace( array( "\r", "\n" ), ' ', $name ), '' );
		if ( '' !== $description ) array_push( $lines, '> ' . $description, '' );
		array_push( $lines, '## Recursos', '', self::link( 'Inicio', $home ) );

		$selected = array_filter( explode( "\n", self::sanitize_urls( Digitalisimo_Integrations_Settings::get( 'seo_ai_llms_urls' ) ) ) );
		foreach ( $selected as $url ) {
			if ( isset( $seen[ self::key( $url ) ] ) ) continue;
			$seen[ self::key( $url ) ] = true;
			$lines[] = self::link( self::title_for( $url ), $url );
		}
		if ( ! $selected ) {
			$skip  = array_map( 'intval', array( get_option( 'page_on_front' ), get_option( 'page_for_posts' ) ) );
			$pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => 30, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ), 'has_password' => false, 'no_found_rows' => true ) );
			foreach ( $pages as $page ) {
				$url = get_permalink( $page );
				if ( in_array( (int) $page->ID, $skip, true ) || ! $url || isset( $seen[ self::key( $url ) ] ) || ! self::indexable( $page ) ) continue;
				$seen[ self::key( $url ) ] = true;
				$lines[] = self::link( get_the_title( $page ), $url );
			}
			$recent = array();
			foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 10, 'has_password' => false, 'no_found_rows' => true ) ) as $post ) {
				$url = get_permalink( $post );
				if ( ! $url || isset( $seen[ self::key( $url ) ] ) || ! self::indexable( $post ) ) continue;
				$seen[ self::key( $url ) ] = true;
				$recent[] = self::link( get_the_title( $post ), $url );
			}
			if ( $recent ) $lines = array_merge( $lines, array( '', '## Contenido reciente', '' ), $recent );
		}
		if ( Digitalisimo_Integrations_Settings::get( 'sitemap_enabled' ) ) $lines = array_merge( $lines, array( '', '## Opcional', '', self::link( 'Sitemap XML', home_url( '/wp-sitemap.xml' ) ) ) );
		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Contenido que se publica y su validación. «fallback» indica que el manual
	 * (o el híbrido) no pasó la validación y se sirve el automático.
	 */
	public static function result() {
		$found  = false;
		$cached = class_exists( 'Digitalisimo_Integrations_Performance_Cache' ) ? Digitalisimo_Integrations_Performance_Cache::get( self::CACHE, $found ) : null;
		if ( $found && is_array( $cached ) ) return $cached;

		$mode   = self::mode();
		$auto   = self::automatic();
		$manual = trim( (string) Digitalisimo_Integrations_Settings::get( 'seo_ai_llms_manual' ) );
		$text   = 'manual' === $mode ? $manual : ( 'hybrid' === $mode && '' !== $manual ? rtrim( $auto ) . "\n\n" . $manual . "\n" : $auto );
		$filtered = apply_filters( 'digitalisimo_seo_ai_llms_content', $text, $mode );
		if ( is_string( $filtered ) && self::validate( $filtered )['valid'] ) $text = $filtered;
		$check  = self::validate( $text );
		$result = array( 'content' => $text, 'valid' => $check['valid'], 'fallback' => false, 'errors' => $check['errors'], 'links' => $check['links'], 'mode' => $mode );
		if ( ! $check['valid'] && $text !== $auto ) {
			$auto_check = self::validate( $auto );
			$result = array( 'content' => $auto, 'valid' => $auto_check['valid'], 'fallback' => true, 'errors' => $check['errors'], 'links' => $auto_check['links'], 'mode' => $mode );
		}
		if ( class_exists( 'Digitalisimo_Integrations_Performance_Cache' ) ) Digitalisimo_Integrations_Performance_Cache::set( self::CACHE, $result, 3600 );
		return $result;
	}

	/** Estado para el resumen de la auditoría y la pestaña Agentes IA. */
	public static function status() {
		$result = self::result();
		return array( 'enabled' => self::enabled(), 'valid' => ! empty( $result['valid'] ), 'fallback' => ! empty( $result['fallback'] ) ) + $result;
	}
}
