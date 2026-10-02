<?php
defined( 'ABSPATH' ) || exit;

/** Reglas editables de robots.txt, además de las que generan WordPress y SEO AI. */
class Digitalisimo_Integrations_Robots {
	const KEY = 'seo_robots_rules';
	const MAX_BYTES = 16384;
	const MAX_LINES = 200;

	public static function init() {
		add_filter( 'robots_txt', array( __CLASS__, 'append' ), 50, 2 );
	}

	/** Admite sólo grupos User-agent y rutas Allow/Disallow; el Sitemap lo gestiona SEO. */
	public static function sanitize( $input ) {
		if ( ! is_scalar( $input ) ) return '';
		$input = str_replace( array( "\r\n", "\r" ), "\n", substr( (string) $input, 0, self::MAX_BYTES ) );
		$input = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input );
		$lines = array();
		$first_directive = false;
		foreach ( array_slice( explode( "\n", $input ), 0, self::MAX_LINES ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) { if ( $lines && '' !== end( $lines ) ) $lines[] = ''; continue; }
			if ( '#' === $line[0] ) { $lines[] = $line; continue; }
			if ( ! preg_match( '/^(User-agent|Allow|Disallow)\s*:\s*(.*)$/i', $line, $parts ) ) continue;
			$directive = strtolower( $parts[1] );
			$value = trim( explode( '#', $parts[2], 2 )[0] );
			if ( 'user-agent' === $directive ) {
				if ( ! preg_match( '/^[A-Za-z0-9*_.-]{1,100}$/', $value ) ) continue;
				$lines[] = 'User-agent: ' . $value;
			} else {
				if ( ! preg_match( '~^/[^\x00-\x20<>]*$~u', $value ) ) continue;
				if ( ! $first_directive ) array_unshift( $lines, 'User-agent: *' );
				$lines[] = ( 'allow' === $directive ? 'Allow: ' : 'Disallow: ' ) . $value;
			}
			$first_directive = true;
		}
		return trim( implode( "\n", $lines ) );
	}

	public static function append( $output, $public ) {
		if ( ! $public ) return $output;
		$rules = self::sanitize( Digitalisimo_Integrations_SEO_Resolver::option( self::KEY, '' ) );
		return '' === $rules ? $output : rtrim( $output ) . "\n\n# Reglas personalizadas de Digitalisimo\n" . $rules . "\n";
	}

	/** Vista previa con la misma base que do_robots() y los filtros activos del sitio. */
	public static function preview() {
		$base = "User-agent: *\nDisallow: " . wp_parse_url( admin_url(), PHP_URL_PATH ) . "\nAllow: " . wp_parse_url( admin_url( 'admin-ajax.php' ), PHP_URL_PATH ) . "\n";
		return apply_filters( 'robots_txt', $base, (bool) get_option( 'blog_public' ) );
	}
}
