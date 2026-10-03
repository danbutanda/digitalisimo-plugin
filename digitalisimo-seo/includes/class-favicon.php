<?php
defined( 'ABSPATH' ) || exit;

/**
 * Favicon generado desde un PNG maestro cuadrado de 512 px o más.
 *
 * Genera, con el editor de imágenes de WordPress, los PNG de 48, 96, 192 y 512
 * px y el apple-touch-icon de 180 px, más un favicon.ico con 16, 32 y 48 px
 * (contenedor ICO con PNG dentro: WordPress no escribe .ico). Los archivos
 * viven en las uploads del sitio en curso con nombres fijos, así que sus URLs
 * son estables; sólo se regeneran cuando cambia el contenido del PNG maestro.
 *
 * Cuando hay favicon de Digitalísimo, el icono del sitio de WordPress (el que
 * también usa Elementor) deja de imprimirse para no duplicar etiquetas; su
 * ajuste no se modifica y vuelve a usarse en cuanto se quita el PNG maestro.
 */
class Digitalisimo_Integrations_Favicon {
	const KEY   = 'seo_favicon_source';
	const STATE = 'digitalisimo_favicon_state';
	const DIR   = 'digitalisimo-favicon';
	const MIN   = 512;
	const ICO   = array( 16, 32, 48 );
	/** Archivo => lado en px. */
	const FILES = array( 'favicon-48x48.png' => 48, 'favicon-96x96.png' => 96, 'favicon-192x192.png' => 192, 'favicon-512x512.png' => 512, 'apple-touch-icon.png' => 180 );

	public static function init() {
		add_action( 'update_option_' . Digitalisimo_Integrations_Settings::OPTION, array( __CLASS__, 'settings_changed' ), 10, 2 );
		add_action( 'add_option_' . Digitalisimo_Integrations_Settings::OPTION, array( __CLASS__, 'added' ) );
		add_action( 'admin_post_digitalisimo_favicon_regenerate', array( __CLASS__, 'regenerate_action' ) );
		add_action( 'init', array( __CLASS__, 'takeover' ) );
		add_action( 'do_faviconico', array( __CLASS__, 'serve_ico' ), 0 );
	}

	/* ------------------------------------------------------------------ *
	 * Estado y URLs
	 * ------------------------------------------------------------------ */

	public static function state() {
		$state = get_option( self::STATE, array() );
		return is_array( $state ) ? $state : array();
	}

	public static function source() {
		return trim( (string) Digitalisimo_Integrations_SEO_Resolver::option( self::KEY ) );
	}

	/** Generado y vigente para el PNG maestro configurado. */
	public static function active() {
		$state = self::state();
		return '' !== self::source() && ! empty( $state['version'] ) && ( $state['source'] ?? '' ) === self::source() && empty( $state['error'] );
	}

	private static function dir() {
		$uploads = wp_upload_dir( null, false );
		return array( trailingslashit( (string) $uploads['basedir'] ) . self::DIR, trailingslashit( (string) $uploads['baseurl'] ) . self::DIR );
	}

	public static function url( $file ) {
		$state = self::state();
		return self::dir()[1] . '/' . $file . ( ! empty( $state['version'] ) ? '?v=' . rawurlencode( $state['version'] ) : '' );
	}

	/* ------------------------------------------------------------------ *
	 * Validación y generación
	 * ------------------------------------------------------------------ */

	/** Requisitos del PNG maestro. @return string Error, o vacío si sirve. */
	public static function validate( $info ) {
		if ( ! is_array( $info ) || empty( $info[0] ) || empty( $info[1] ) ) return 'No se pudo leer la imagen.';
		if ( 'image/png' !== ( $info['mime'] ?? '' ) ) return 'El archivo debe ser PNG.';
		if ( (int) $info[0] !== (int) $info[1] ) return 'La imagen debe ser cuadrada: mide ' . (int) $info[0] . ' × ' . (int) $info[1] . ' px.';
		if ( (int) $info[0] < self::MIN ) return 'La imagen debe medir al menos ' . self::MIN . ' × ' . self::MIN . ' px: mide ' . (int) $info[0] . ' px.';
		return '';
	}

	/** Archivo original de un adjunto de este sitio a partir de su URL. */
	private static function source_path( $url ) {
		$id = attachment_url_to_postid( $url );
		if ( ! $id ) return '';
		$path = function_exists( 'wp_get_original_image_path' ) ? wp_get_original_image_path( $id ) : '';
		if ( ! $path ) $path = get_attached_file( $id );
		return $path && is_readable( $path ) ? $path : '';
	}

	/**
	 * favicon.ico con varias imágenes PNG (formato ICO estándar desde Windows
	 * Vista, aceptado por todos los navegadores actuales).
	 *
	 * @param array $pngs lado en px => bytes del PNG.
	 */
	public static function ico( $pngs ) {
		ksort( $pngs );
		$count  = count( $pngs );
		$header = pack( 'vvv', 0, 1, $count );
		$offset = 6 + 16 * $count;
		$dir    = '';
		$data   = '';
		foreach ( $pngs as $size => $bytes ) {
			$side = (int) $size >= 256 ? 0 : (int) $size;
			$dir .= pack( 'CCCCvvVV', $side, $side, 0, 0, 1, 32, strlen( $bytes ), $offset );
			$offset += strlen( $bytes );
			$data .= $bytes;
		}
		return $header . $dir . $data;
	}

	/**
	 * Genera todos los archivos desde el PNG maestro.
	 *
	 * @param string   $path   PNG maestro.
	 * @param string   $dir    Carpeta de destino.
	 * @param callable $editor Fábrica de editores (por defecto wp_get_image_editor).
	 * @return string Error, o vacío si todo se generó.
	 */
	public static function generate( $path, $dir, $editor = 'wp_get_image_editor' ) {
		if ( ! wp_mkdir_p( $dir ) ) return 'No se pudo crear la carpeta del favicon en uploads.';
		$sizes = array_unique( array_merge( array_values( self::FILES ), self::ICO ) );
		sort( $sizes );
		$made = array();
		foreach ( $sizes as $size ) {
			$image = call_user_func( $editor, $path );
			if ( is_wp_error( $image ) ) return 'El editor de imágenes de WordPress no pudo abrir el PNG: ' . $image->get_error_message();
			$current = $image->get_size();
			// Del mismo tamaño no se redimensiona: WordPress lo rechaza como «sin cambios».
			if ( (int) ( $current['width'] ?? 0 ) !== $size ) {
				$resized = $image->resize( $size, $size, true );
				if ( is_wp_error( $resized ) ) return 'No se pudo generar ' . $size . ' px: ' . $resized->get_error_message();
			}
			$target = $dir . '/digitalisimo-' . $size . '.tmp.png';
			$saved  = $image->save( $target, 'image/png' );
			if ( is_wp_error( $saved ) || empty( $saved['path'] ) ) return 'No se pudo guardar ' . $size . ' px.';
			$made[ $size ] = $saved['path'];
		}
		foreach ( self::FILES as $file => $size ) {
			if ( ! @copy( $made[ $size ], $dir . '/' . $file ) ) return 'No se pudo escribir ' . $file . '.';
		}
		$pngs = array();
		foreach ( self::ICO as $size ) $pngs[ $size ] = (string) file_get_contents( $made[ $size ] );
		if ( false === file_put_contents( $dir . '/favicon.ico', self::ico( $pngs ) ) ) return 'No se pudo escribir favicon.ico.';
		foreach ( $made as $file ) @unlink( $file );
		return '';
	}

	private static function clear_files() {
		$dir = self::dir()[0];
		foreach ( array_merge( array_keys( self::FILES ), array( 'favicon.ico' ) ) as $file ) if ( is_file( $dir . '/' . $file ) ) @unlink( $dir . '/' . $file );
	}

	/** Regenera sólo si el PNG maestro cambió (otra URL o el mismo archivo con otro contenido). */
	public static function sync( $force = false, $editor = 'wp_get_image_editor' ) {
		$url   = self::source();
		$state = self::state();
		if ( '' === $url ) {
			if ( $state ) { self::clear_files(); delete_option( self::STATE ); }
			return;
		}
		$path  = self::source_path( $url );
		$error = $path ? self::validate( wp_getimagesize( $path ) ) : 'Elige el PNG desde la Biblioteca de Medios de este sitio.';
		$hash  = $path ? (string) md5_file( $path ) : '';
		if ( ! $error && ! $force && $hash === ( $state['hash'] ?? '' ) && $url === ( $state['source'] ?? '' ) && empty( $state['error'] ) && is_file( self::dir()[0] . '/favicon.ico' ) ) return false;
		if ( ! $error ) $error = self::generate( $path, self::dir()[0], $editor );
		update_option( self::STATE, array( 'source' => $url, 'hash' => $hash, 'version' => $error ? '' : substr( $hash, 0, 10 ), 'generated' => time(), 'error' => $error ), false );
		return true;
	}

	/** El gancho add_option_ pasa argumentos que no deben forzar la regeneración. */
	public static function added() {
		self::sync();
	}

	public static function settings_changed( $old, $new ) {
		$before = (string) ( ( (array) $old )[ self::KEY ] ?? '' );
		$after  = (string) ( ( (array) $new )[ self::KEY ] ?? '' );
		if ( $before !== $after || ( '' !== $after && empty( self::state()['version'] ) ) ) self::sync();
	}

	public static function regenerate_action() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'No autorizado.' );
		check_admin_referer( 'digitalisimo_favicon_regenerate' );
		self::sync( true );
		wp_safe_redirect( admin_url( 'admin.php?page=digitalisimo-seo-tools&favicon=1' ) );
		exit;
	}

	/* ------------------------------------------------------------------ *
	 * Salida
	 * ------------------------------------------------------------------ */

	/** Un solo juego de etiquetas: el icono del sitio de WordPress deja de imprimirse. */
	public static function takeover() {
		if ( ! self::active() ) return;
		foreach ( array( 'wp_head' => 99, 'login_head' => 99, 'admin_head' => 10 ) as $hook => $priority ) {
			remove_action( $hook, 'wp_site_icon', $priority );
			add_action( $hook, array( __CLASS__, 'print_tags' ), $priority );
		}
	}

	public static function tags() {
		$tags = array( '<link rel="icon" href="' . esc_url( self::url( 'favicon.ico' ) ) . '" sizes="16x16 32x32 48x48">' );
		foreach ( self::FILES as $file => $size ) {
			if ( 'apple-touch-icon.png' === $file ) continue;
			$tags[] = '<link rel="icon" type="image/png" sizes="' . $size . 'x' . $size . '" href="' . esc_url( self::url( $file ) ) . '">';
		}
		$tags[] = '<link rel="apple-touch-icon" sizes="180x180" href="' . esc_url( self::url( 'apple-touch-icon.png' ) ) . '">';
		return $tags;
	}

	public static function print_tags() {
		echo implode( "\n", self::tags() ) . "\n";
	}

	/** /favicon.ico del sitio (lo piden navegadores y buscadores aunque no lo declare el HTML). */
	public static function serve_ico() {
		$file = self::dir()[0] . '/favicon.ico';
		if ( ! self::active() || ! is_readable( $file ) ) return;
		header( 'Content-Type: image/x-icon' );
		header( 'Cache-Control: public, max-age=604800' );
		header( 'Content-Length: ' . filesize( $file ) );
		readfile( $file );
		exit;
	}

	/* ------------------------------------------------------------------ *
	 * Administración: SEO → Avanzado
	 * ------------------------------------------------------------------ */

	/** Etiquetas de icono en un HTML que no pertenecen a Digitalísimo. */
	public static function foreign_icons( $html ) {
		$found = array();
		if ( ! preg_match_all( '/<link\b[^>]*\brel\s*=\s*["\']?([^"\'>]*\b(?:icon|apple-touch-icon)\b[^"\'>]*)["\']?[^>]*>/i', (string) $html, $links, PREG_SET_ORDER ) ) return $found;
		foreach ( $links as $link ) {
			if ( ! preg_match( '/\bhref\s*=\s*["\']([^"\']+)["\']/i', $link[0], $href ) ) continue;
			if ( false !== strpos( $href[1], '/' . self::DIR . '/' ) ) continue;
			$found[] = html_entity_decode( $href[1], ENT_QUOTES, 'UTF-8' );
		}
		return array_values( array_unique( $found ) );
	}

	private static function home_html() {
		$args = array( 'timeout' => 8, 'redirection' => 2, 'user-agent' => 'Digitalisimo Favicon Check/1.0' );
		$response = wp_remote_get( home_url( '/' ), $args );
		if ( is_wp_error( $response ) && class_exists( 'Digitalisimo_Integrations_LLMS' ) && Digitalisimo_Integrations_LLMS::certificate_error( $response->get_error_message() ) ) $response = wp_remote_get( home_url( '/' ), $args + array( 'sslverify' => false ) );
		return is_wp_error( $response ) ? null : (string) wp_remote_retrieve_body( $response );
	}

	public static function render() {
		$state  = self::state();
		$source = self::source();
		echo '<h3>Generar favicon</h3><p>Sube un único PNG cuadrado de ' . self::MIN . ' × ' . self::MIN . ' px o mayor. Se generan favicon.ico (16, 32 y 48 px), PNG de 48, 96, 192 y 512 px y apple-touch-icon de 180 px, y se declaran en el &lt;head&gt;. Sólo se regenera cuando cambias la imagen. Mientras esté activo, sustituye al icono del sitio de WordPress y Elementor sin modificar su ajuste; al quitar la imagen vuelve el anterior.</p>';
		if ( isset( $_GET['favicon'] ) ) echo '<div class="notice notice-success inline"><p>Favicon regenerado.</p></div>';
		echo '<form method="post" action="options.php">';
		settings_fields( 'digitalisimo_integrations' );
		Digitalisimo_Media_Field::render( self::KEY, Digitalisimo_Integrations_Settings::OPTION . '[' . self::KEY . ']', $source );
		submit_button( 'Guardar favicon', 'primary', 'submit', false );
		echo '</form>';
		if ( '' === $source ) return;
		if ( ! empty( $state['error'] ) ) { echo '<div class="notice notice-error inline"><p>' . esc_html( $state['error'] ) . '</p></div>'; return; }
		if ( ! self::active() ) { echo '<p>El favicon se generará al guardar.</p>'; return; }
		echo '<p>Generado el ' . esc_html( date_i18n( 'Y-m-d H:i', (int) $state['generated'] ) ) . '.</p><p>';
		foreach ( array( 'favicon-48x48.png' => 48, 'favicon-96x96.png' => 48, 'apple-touch-icon.png' => 60, 'favicon-192x192.png' => 64 ) as $file => $show ) echo '<img src="' . esc_url( self::url( $file ) ) . '" width="' . (int) $show . '" height="' . (int) $show . '" alt="" style="margin-right:12px;vertical-align:middle;border:1px solid #dcdcde"> ';
		echo '</p><details><summary>Etiquetas que se imprimen en el &lt;head&gt;</summary><pre style="white-space:pre-wrap">' . esc_html( implode( "\n", self::tags() ) ) . '</pre></details>';
		$html = self::home_html();
		if ( null === $html ) echo '<p class="description">No se pudo leer la portada para comprobar duplicados.</p>';
		else {
			$foreign = self::foreign_icons( $html );
			if ( $foreign ) echo '<div class="notice notice-warning inline"><p>La portada todavía publica otros iconos, probablemente escritos por el tema, un plugin o una caché de página: ' . esc_html( implode( ' · ', $foreign ) ) . '. Purga la caché; si siguen, quítalos de su origen.</p></div>';
			else echo '<p>' . ( class_exists( 'Digitalisimo_Integrations_Quality_Audit' ) ? Digitalisimo_Integrations_Quality_Audit::badge( 'OK' ) : '✓' ) . ' La portada publica sólo el favicon de Digitalísimo.</p>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="digitalisimo_favicon_regenerate">';
		wp_nonce_field( 'digitalisimo_favicon_regenerate' );
		submit_button( 'Regenerar ahora', 'secondary', 'submit', false );
		echo '</form>';
	}
}
