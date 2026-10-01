<?php
defined( 'ABSPATH' ) || exit;

/** Filtra @font-face locales en copias preparadas por un administrador; falla abierto. */
class Digitalisimo_Integrations_Performance_Font_Guard {
	const OPTION = 'digitalisimo_performance_font_guard_manifest';

	public static function init() {
		add_action( 'admin_post_digitalisimo_performance_build_fonts', array( __CLASS__, 'build_action' ) );
		add_filter( 'style_loader_src', array( __CLASS__, 'style_src' ), 20, 2 );
	}

	public static function sanitize_mode( $value ) {
		return in_array( $value, array( 'off', 'auto', 'manual' ), true ) ? $value : 'off';
	}

	public static function sanitize_allowlist( $value ) {
		$lines = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $value ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line ) );
			if ( 3 !== count( $parts ) || ! preg_match( '/^[\p{L}\p{N} ._-]{1,80}$/u', $parts[0] ) ) continue;
			$weights = array();
			foreach ( explode( ',', $parts[1] ) as $weight ) {
				$weight = trim( $weight );
				if ( preg_match( '/^[1-9]00$/', $weight ) ) $weights[ $weight ] = true;
			}
			$styles = array();
			foreach ( explode( ',', $parts[2] ) as $style ) {
				$style = strtolower( trim( $style ) );
				if ( in_array( $style, array( 'normal', 'italic', 'oblique' ), true ) ) $styles[ $style ] = true;
			}
			if ( ! $weights || ! $styles ) continue;
			$lines[ strtolower( $parts[0] ) ] = $parts[0] . '|' . implode( ',', array_keys( $weights ) ) . '|' . implode( ',', array_keys( $styles ) );
			if ( count( $lines ) >= 40 ) break;
		}
		return implode( "\n", array_values( $lines ) );
	}

	private static function policy() {
		$mode = self::sanitize_mode( Digitalisimo_Integrations_SEO_Resolver::option( 'perf_font_guard_mode' ) );
		$allowlist = self::sanitize_allowlist( Digitalisimo_Integrations_SEO_Resolver::option( 'perf_font_guard_allowlist' ) );
		return array( $mode, $allowlist, hash( 'sha256', $mode . "\n" . $allowlist ) );
	}

	private static function rules( $mode, $allowlist, $report ) {
		$rules = array();
		if ( 'manual' === $mode ) {
			foreach ( explode( "\n", $allowlist ) as $line ) {
				$parts = explode( '|', $line );
				if ( 3 === count( $parts ) ) $rules[ strtolower( $parts[0] ) ] = array( 'weights' => explode( ',', $parts[1] ), 'styles' => explode( ',', $parts[2] ) );
			}
		} elseif ( 'auto' === $mode ) {
			foreach ( (array) ( $report['families'] ?? array() ) as $family ) {
				$key = strtolower( (string) ( $family['family'] ?? '' ) );
				if ( $key ) $rules[ $key ] = array( 'weights' => array_map( 'strval', (array) ( $family['weights'] ?? array() ) ), 'styles' => (array) ( $family['styles'] ?? array() ) );
			}
		}
		return $rules;
	}

	private static function icon_family( $family ) {
		return (bool) preg_match( '/(?:^|[\s_-])(?:eicons|font[\s_-]?awesome|[a-z0-9_-]*icons?)(?:$|[\s_-])/i', $family );
	}

	/** Devuelve CSS original si un bloque no se puede interpretar con seguridad. */
	public static function filter_css( $css, $rules, $mode ) {
		$removed = 0;
		$filtered = preg_replace_callback( '/@font-face\s*\{([^{}]*)\}/i', function( $match ) use ( $rules, $mode, &$removed ) {
			$block = $match[1];
			if ( ! preg_match( '/font-family\s*:\s*([^;]+)/i', $block, $found ) ) return $match[0];
			$family = trim( $found[1], " \t\n\r\0\x0B'\"" );
			if ( self::icon_family( $family ) ) return $match[0];
			$key = strtolower( $family );
			if ( ! isset( $rules[ $key ] ) ) {
				if ( 'auto' === $mode ) return $match[0]; // Kit incompleto: conservar familias declaradas por páginas o tema.
				++$removed;
				return '';
			}
			$weight = preg_match( '/font-weight\s*:\s*([^;]+)/i', $block, $found ) ? strtolower( trim( $found[1] ) ) : '400';
			$style = preg_match( '/font-style\s*:\s*([^;]+)/i', $block, $found ) ? strtolower( trim( $found[1] ) ) : 'normal';
			if ( 'normal' === $weight ) $weight = '400';
			if ( 'bold' === $weight ) $weight = '700';
			if ( ! preg_match( '/^[1-9]00$/', $weight ) || ! in_array( $style, array( 'normal', 'italic', 'oblique' ), true ) ) return $match[0];
			$rule = $rules[ $key ];
			if ( ( ! empty( $rule['weights'] ) && ! in_array( $weight, $rule['weights'], true ) ) || ( ! empty( $rule['styles'] ) && ! in_array( $style, $rule['styles'], true ) ) ) { ++$removed; return ''; }
			return $match[0];
		}, $css );
		return array( is_string( $filtered ) ? $filtered : $css, $removed );
	}

	/** Se ejecuta sólo en administración y escribe junto al CSS de Elementor para preservar URLs relativas. */
	public static function build() {
		list( $mode, $allowlist, $fingerprint ) = self::policy();
		if ( 'off' === $mode ) { delete_option( self::OPTION ); return array( 'built' => 0, 'removed' => 0 ); }
		$report = get_option( Digitalisimo_Integrations_Performance_Fonts::OPTION, array() );
		if ( empty( $report['scanned_at'] ) ) return array( 'error' => 'Analiza primero las fuentes.' );
		$rules = self::rules( $mode, $allowlist, $report );
		if ( ! $rules ) return array( 'error' => 'Configura al menos una familia válida.' );
		$uploads = wp_upload_dir();
		$root = realpath( $uploads['basedir'] ?? '' );
		$directory = realpath( trailingslashit( $uploads['basedir'] ?? '' ) . 'elementor/google-fonts/css' );
		if ( ! $root || ! $directory || 0 !== strpos( $directory, trailingslashit( $root ) ) || ! is_writable( $directory ) ) return array( 'error' => 'No se puede escribir en el CSS local de este sitio.' );
		$manifest = array( 'fingerprint' => $fingerprint, 'rows' => array() );
		$removed = 0;
		$files = glob( $directory . '/*.css' );
		$files = is_array( $files ) ? array_values( array_filter( $files, function( $path ) { return 0 !== strpos( basename( $path ), 'digitalisimo-' ); } ) ) : array();
		foreach ( array_slice( $files, 0, 50 ) as $path ) {
			$real = realpath( $path );
			if ( ! $real || 0 !== strpos( $real, trailingslashit( $directory ) ) || ! is_readable( $real ) || filesize( $real ) > 512 * KB_IN_BYTES ) continue;
			$css = file_get_contents( $real );
			if ( ! is_string( $css ) || false === stripos( $css, '@font-face' ) ) continue;
			list( $filtered, $count ) = self::filter_css( $css, $rules, $mode );
			if ( ! $count ) continue;
			$name = 'digitalisimo-' . substr( hash( 'sha256', $fingerprint . $css ), 0, 16 ) . '-' . basename( $real );
			$target = $directory . '/' . $name;
			if ( false === file_put_contents( $target, $filtered, LOCK_EX ) ) continue;
			$source_url = trailingslashit( $uploads['baseurl'] ) . 'elementor/google-fonts/css/' . basename( $real );
			$manifest['rows'][ $source_url ] = array( 'original' => $real, 'mtime' => filemtime( $real ), 'generated' => $target, 'url' => trailingslashit( $uploads['baseurl'] ) . 'elementor/google-fonts/css/' . $name );
			$removed += $count;
		}
		update_option( self::OPTION, $manifest, false );
		return array( 'built' => count( $manifest['rows'] ), 'removed' => $removed );
	}

	public static function style_src( $src, $handle ) {
		if ( ! Digitalisimo_Integrations_Performance_Manager::advanced_allowed() ) return $src;
		list( $mode, , $fingerprint ) = self::policy();
		if ( 'off' === $mode ) return $src;
		$manifest = get_option( self::OPTION, array() );
		if ( $fingerprint !== ( $manifest['fingerprint'] ?? '' ) ) return $src;
		$key = strtok( (string) $src, '?#' );
		$row = $manifest['rows'][ $key ] ?? array();
		if ( ! $row || ! is_file( $row['original'] ?? '' ) || ! is_file( $row['generated'] ?? '' ) || filemtime( $row['original'] ) !== ( $row['mtime'] ?? null ) ) return $src;
		return $row['url'];
	}

	public static function build_action() {
		$network = ! empty( $_POST['network_context'] );
		if ( $network ? ! is_multisite() || ! current_user_can( 'manage_network_options' ) : ! current_user_can( 'manage_options' ) ) wp_die( 'No autorizado.' );
		$site_id = absint( $_POST['site_id'] ?? 0 );
		if ( ! $site_id || ( $network ? ! get_site( $site_id ) : $site_id !== get_current_blog_id() ) ) wp_die( 'Sitio inválido.' );
		check_admin_referer( 'digitalisimo_performance_build_fonts_' . $site_id );
		$switched = $site_id !== get_current_blog_id();
		if ( $switched ) switch_to_blog( $site_id );
		try { $result = self::build(); }
		finally { if ( $switched ) restore_current_blog(); }
		$target = $network ? network_admin_url( 'admin.php?page=digitalisimo-network-performance&section=fonts&site_id=' . $site_id ) : admin_url( 'admin.php?page=digitalisimo-performance&section=fonts' );
		wp_safe_redirect( add_query_arg( isset( $result['error'] ) ? 'font_guard_error' : 'font_guard_built', isset( $result['error'] ) ? $result['error'] : $result['removed'], $target ) );
		exit;
	}

	public static function render_build( $network, $site_id ) {
		if ( isset( $_GET['font_guard_error'] ) ) echo '<div class="notice notice-error"><p>' . esc_html( wp_unslash( $_GET['font_guard_error'] ) ) . '</p></div>';
		if ( isset( $_GET['font_guard_built'] ) ) echo '<div class="notice notice-success"><p>Copias CSS generadas. Reglas de fuente retiradas: ' . esc_html( absint( $_GET['font_guard_built'] ) ) . '.</p></div>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="digitalisimo_performance_build_fonts"><input type="hidden" name="network_context" value="' . ( $network ? '1' : '0' ) . '"><input type="hidden" name="site_id" value="' . esc_attr( $site_id ) . '">';
		wp_nonce_field( 'digitalisimo_performance_build_fonts_' . $site_id );
		submit_button( 'Generar copias CSS según política efectiva', 'secondary', 'submit', false );
		echo '</form>';
	}
}
