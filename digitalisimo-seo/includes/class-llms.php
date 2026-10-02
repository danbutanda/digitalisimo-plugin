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
		// Nombre, descripción, URL y portada cambian el contenido: la caché del sitio se descarta.
		foreach ( array( 'blogname', 'blogdescription', 'home', 'siteurl', 'page_on_front', 'show_on_front' ) as $option ) add_action( 'update_option_' . $option, array( __CLASS__, 'forget' ) );
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

	/**
	 * /llms.txt del sitio en curso: funciona en subdominios y subdirectorios sin
	 * reglas de reescritura. Devuelve 'exact', 'slash' (con barra final) o ''.
	 */
	public static function requested() {
		$path   = rawurldecode( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ) );
		$target = (string) wp_parse_url( self::endpoint(), PHP_URL_PATH );
		if ( '' === $path ) return '';
		if ( $path === $target ) return 'exact';
		return $path === $target . '/' ? 'slash' : '';
	}

	/**
	 * Se resuelve antes que el enrutado de WordPress: si no, la redirección
	 * canónica añade una barra (/llms.txt/) y se sirve una página HTML con 200.
	 */
	public static function maybe_serve() {
		$requested = self::requested();
		if ( '' === $requested ) return;
		if ( ! self::enabled() ) self::not_found();
		if ( 'slash' === $requested ) { wp_redirect( self::endpoint(), 301 ); exit; }
		self::serve();
	}

	/** Desactivado o inválido: 404 en texto, nunca una página HTML del sitio. */
	private static function not_found() {
		status_header( 404 );
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		echo "llms.txt no está disponible en este sitio.\n";
		exit;
	}

	public static function serve() {
		$result = self::result();
		if ( empty( $result['valid'] ) ) self::not_found(); // Nunca se publica un archivo inválido.
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

	/** Texto de WordPress sin HTML, shortcodes ni entidades. */
	private static function text( $value ) {
		$value = (string) $value;
		if ( function_exists( 'strip_shortcodes' ) ) $value = strip_shortcodes( $value );
		return self::plain( preg_replace( '/\[[^\]]*\]/', ' ', $value ) );
	}

	/** Sólo contenido publicado, público, indexable y de este sitio. */
	private static function publishable( $url ) {
		$host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		if ( strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) !== $host || false !== strpos( $url, '#' ) ) return 0;
		$id   = url_to_postid( $url );
		$post = $id ? get_post( $id ) : null;
		return $post && 'publish' === $post->post_status && self::indexable( $post ) ? (int) $id : 0;
	}

	/**
	 * Descripción del sitio, por prioridad: meta descripción SEO de la portada,
	 * descripción de WordPress. Si no hay ninguna no se inventa texto.
	 */
	public static function descriptions() {
		$front   = 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : 0;
		$meta    = $front ? self::text( get_post_meta( $front, 'digitalisimo_seo_description', true ) ) : '';
		$tagline = self::text( get_bloginfo( 'description' ) );
		$excerpt = $front ? self::text( get_post_field( 'post_excerpt', $front ) ) : '';
		$summary = '' !== $meta ? $meta : $tagline;
		// Párrafo opcional: el extracto escrito para la portada, si dice algo distinto.
		$about = '' !== $excerpt && ! in_array( self::key( $excerpt ), array( self::key( $summary ), self::key( $tagline ) ), true ) ? $excerpt : '';
		return array( 'summary' => $summary, 'about' => $about, 'tagline' => $tagline );
	}

	/**
	 * Estructura: H1 con el nombre real, cita con la descripción, párrafo
	 * opcional y «## Páginas principales» con Inicio y las páginas elegidas
	 * que existen, están publicadas y son indexables. No se listan páginas
	 * automáticamente: ningún enlace se inventa.
	 */
	public static function automatic() {
		$name = self::plain( get_bloginfo( 'name' ) );
		if ( '' === $name ) $name = self::plain( Digitalisimo_Integrations_Settings::get( 'seo_site_name' ) );
		if ( '' === $name ) $name = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$text  = self::descriptions();
		$home  = home_url( '/' );
		$seen  = array( self::key( $home ) => true );
		$lines = array( '# ' . str_replace( array( "\r", "\n" ), ' ', $name ), '' );
		if ( '' !== $text['summary'] ) array_push( $lines, '> ' . $text['summary'], '' );
		if ( '' !== $text['about'] ) array_push( $lines, $text['about'], '' );
		array_push( $lines, '## Páginas principales', '', self::link( 'Inicio', $home ) . ': ' . ( '' !== $text['tagline'] ? $text['tagline'] : 'Página principal del sitio.' ) );
		foreach ( array_filter( explode( "\n", self::sanitize_urls( Digitalisimo_Integrations_Settings::get( 'seo_ai_llms_urls' ) ) ) ) as $url ) {
			$id = self::publishable( $url );
			if ( ! $id || isset( $seen[ self::key( $url ) ] ) ) continue;
			$seen[ self::key( $url ) ] = true;
			$lines[] = self::link( get_the_title( $id ), $url );
		}
		if ( Digitalisimo_Integrations_Settings::get( 'sitemap_enabled' ) ) $lines = array_merge( $lines, array( '', '## Opcional', '', self::link( 'Sitemap XML', home_url( '/wp-sitemap.xml' ) ) . ': Índice de todas las URLs públicas.' ) );
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

	/** Secciones «## » de un Markdown. */
	public static function sections( $text ) {
		return preg_match_all( '/^##\s+\S/m', (string) $text );
	}

	/**
	 * Diagnóstico de una respuesta real de /llms.txt.
	 *
	 * @return array state (CORRECTO, ERROR HTTP, VACÍO, FALTA H1, SIN ENLACES), detail, code, type, h1, links, sections, bytes.
	 */
	public static function diagnose( $code, $type, $body, $error = '' ) {
		$body   = (string) $body;
		$check  = self::validate( $body );
		$h1     = (bool) preg_match( '/^#\s+\S/m', $body ) && ! preg_grep( '/H1/', $check['errors'] );
		$report = array( 'code' => (int) $code, 'type' => (string) $type, 'h1' => $h1, 'title' => $check['title'], 'links' => $check['links'], 'sections' => self::sections( $body ), 'bytes' => strlen( $body ), 'detail' => '' );
		if ( '' !== $error || 200 !== (int) $code ) return array( 'state' => 'ERROR HTTP', 'detail' => '' !== $error ? $error : 'HTTP ' . (int) $code . ': se esperaba 200.' ) + $report;
		if ( 0 !== stripos( trim( (string) $type ), 'text/plain' ) ) return array( 'state' => 'ERROR HTTP', 'detail' => 'Content-Type «' . $type . '»: se esperaba text/plain; charset=utf-8. Otra regla del servidor o una caché está sirviendo HTML.' ) + $report;
		if ( '' === trim( $body ) ) return array( 'state' => 'VACÍO' ) + $report;
		if ( ! $h1 ) return array( 'state' => 'FALTA H1', 'detail' => implode( ' ', $check['errors'] ) ) + $report;
		if ( ! $check['links'] ) return array( 'state' => 'SIN ENLACES' ) + $report;
		return array( 'state' => 'CORRECTO', 'detail' => $check['valid'] ? '' : implode( ' ', $check['errors'] ) ) + $report;
	}

	/** Pide /llms.txt al propio sitio como lo haría un agente: sin seguir redirecciones. */
	public static function check_endpoint() {
		$response = wp_remote_get( self::endpoint(), array( 'timeout' => 8, 'redirection' => 0, 'user-agent' => 'Digitalisimo llms.txt Check/1.1' ) );
		if ( is_wp_error( $response ) ) return self::diagnose( 0, '', '', $response->get_error_message() );
		return self::diagnose( wp_remote_retrieve_response_code( $response ), (string) wp_remote_retrieve_header( $response, 'content-type' ), wp_remote_retrieve_body( $response ) );
	}

	/** Pestaña llms.txt de SEO AI del sitio (o de la red con el sitio elegido). */
	public static function admin_url( $network = false, $site_id = 0 ) {
		if ( $network && $site_id && is_multisite() ) return get_admin_url( (int) $site_id, 'admin.php?page=digitalisimo-seo-ai&tab=llms' );
		return admin_url( 'admin.php?page=digitalisimo-seo-ai&tab=llms' );
	}

	private static function badge( $ok, $label ) {
		return class_exists( 'Digitalisimo_Integrations_Quality_Audit' ) ? Digitalisimo_Integrations_Quality_Audit::badge( $ok, $label ) : '<strong>' . esc_html( $label ) . '</strong>';
	}

	/**
	 * Estado, respuesta real y vista previa, bajo el formulario de SEO AI → llms.txt.
	 * En la red sólo se definen defaults: cada sitio genera y comprueba el suyo.
	 */
	public static function render_status( $network = false ) {
		if ( $network ) { echo '<p class="description">La red define los valores que heredan los sitios. Cada sitio genera su propio llms.txt con su nombre, descripción y home_url(); su estado y su respuesta real se ven en SEO AI → llms.txt de ese sitio.</p>'; return; }
		$status = self::status();
		$state  = ! $status['enabled'] ? 'NO APLICABLE' : ( ! $status['valid'] ? 'ERROR' : ( $status['fallback'] ? 'ADVERTENCIA' : 'OK' ) );
		$modes  = array( 'automatic' => 'Automático', 'manual' => 'Manual', 'hybrid' => 'Híbrido' );
		echo '<h2>Estado de llms.txt</h2><table class="widefat striped" style="max-width:900px"><tbody><tr><th>Contenido generado</th><td>' . self::badge( $state, $state ) . ' ' . esc_html( $status['enabled'] ? '' : 'Desactivado en este sitio.' ) . '</td></tr><tr><th>URL</th><td><a href="' . esc_url( self::endpoint() ) . '" target="_blank" rel="noopener">' . esc_html( self::endpoint() ) . '</a></td></tr><tr><th>Modo</th><td>' . esc_html( $modes[ $status['mode'] ] ?? $status['mode'] ) . '</td></tr><tr><th>Enlaces válidos</th><td>' . (int) $status['links'] . '</td></tr>';
		if ( $status['errors'] ) echo '<tr><th>' . ( $status['fallback'] ? 'Markdown manual rechazado' : 'Errores' ) . '</th><td><ul style="margin:0">' . implode( '', array_map( function( $e ) { return '<li>' . esc_html( $e ) . '</li>'; }, $status['errors'] ) ) . '</ul>' . ( $status['fallback'] ? '<p>Se sirve el contenido automático hasta que el manual sea válido.</p>' : '' ) . '</td></tr>';
		echo '</tbody></table>';
		$http = self::check_endpoint();
		echo '<h3>Respuesta real de /llms.txt</h3><p class="description">Petición GET al propio sitio al abrir esta pantalla, sin seguir redirecciones. Un CDN, WAF o caché de página puede responder distinto desde fuera.</p><table class="widefat striped" style="max-width:900px"><tbody>';
		echo '<tr><th>Resultado</th><td>' . self::badge( 'CORRECTO' === $http['state'] ? 'OK' : 'ERROR', $http['state'] ) . ( $http['detail'] ? ' ' . esc_html( $http['detail'] ) : '' ) . '</td></tr>';
		echo '<tr><th>HTTP</th><td>' . esc_html( $http['code'] ?: '—' ) . '</td></tr><tr><th>Content-Type</th><td><code>' . esc_html( $http['type'] ?: '—' ) . '</code></td></tr><tr><th>H1</th><td>' . esc_html( $http['h1'] ? 'Encontrado: ' . $http['title'] : 'No encontrado' ) . '</td></tr><tr><th>Enlaces</th><td>' . (int) $http['links'] . '</td></tr><tr><th>Secciones ##</th><td>' . (int) $http['sections'] . '</td></tr><tr><th>Longitud</th><td>' . (int) $http['bytes'] . ' bytes</td></tr></tbody></table>';
		if ( ! $status['enabled'] ) echo '<p>Activa «Publicar llms.txt» y guarda: mientras esté desactivado, /llms.txt responde 404.</p>';
		echo '<h3>Vista previa</h3><pre style="background:#fff;border:1px solid #dcdcde;padding:12px;max-height:420px;overflow:auto;white-space:pre-wrap">' . esc_html( substr( (string) $status['content'], 0, 8000 ) ) . '</pre>';
	}

	/** Estado para el resumen de la auditoría y la pestaña llms.txt de SEO AI. */
	public static function status() {
		$result = self::result();
		return array( 'enabled' => self::enabled(), 'valid' => ! empty( $result['valid'] ), 'fallback' => ! empty( $result['fallback'] ) ) + $result;
	}
}
