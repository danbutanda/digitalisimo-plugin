<?php
defined( 'ABSPATH' ) || exit;

/** Optimizaciones CSS explícitas y reversibles; nunca descarga CSS de Elementor. */
class Digitalisimo_Integrations_Performance_CSS {
	public static function init() {
		add_filter( 'style_loader_tag', array( __CLASS__, 'defer_widget_css' ), 9999, 4 );
		add_action( 'wp_head', array( __CLASS__, 'caption_rule' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'custom_css' ), 21 );
	}

	/** CSS técnico sin HTML ni solicitudes remotas; el valor vacío desactiva la regla. */
	public static function sanitize_custom_css( $raw ) {
		$css = trim( (string) $raw );
		if ( strlen( $css ) > 20000 || preg_match( '/[<\x00-\x08\x0B\x0C\x0E-\x1F]|@import\b|url\s*\(|expression\s*\(/i', $css ) ) return '';
		return $css;
	}

	/** Acepta sólo handles de widgets; la fuente se verifica de nuevo al imprimir. */
	public static function sanitize_handles( $raw ) {
		$items = preg_split( '/[\s,]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY );
		$allowed = array();
		foreach ( $items as $item ) {
			$item = sanitize_key( $item );
			if ( preg_match( '/^widget-[a-z0-9-]+$/', $item ) && ! in_array( $item, array( 'widget-heading', 'widget-image-carousel', 'widget-nav-menu', 'widget-nested-tabs', 'widget-slides', 'widget-counter', 'widget-call-to-action' ), true ) ) $allowed[ $item ] = true;
		}
		return implode( "\n", array_slice( array_keys( $allowed ), 0, 20 ) );
	}

	public static function canonical_handle( $handle ) {
		return preg_replace( '/^(?:elementor-pro-|elementor-)/', '', (string) $handle );
	}

	private static function selected( $handle ) {
		$raw = (string) Digitalisimo_Integrations_SEO_Resolver::option( 'perf_css_defer_handles' );
		return in_array( self::canonical_handle( $handle ), explode( "\n", self::sanitize_handles( $raw ) ), true );
	}

	private static function elementor_widget_source( $handle, $href ) {
		$widget = self::canonical_handle( $handle );
		if ( ! preg_match( '/^widget-[a-z0-9-]+$/', $widget ) ) return false;
		$path = wp_parse_url( $href, PHP_URL_PATH );
		return is_string( $path ) && (bool) preg_match( '#/plugins/elementor(?:-pro)?/assets/css/' . preg_quote( $widget, '#' ) . '(?:\.min)?\.css$#i', $path );
	}

	public static function configured( $handle, $href ) {
		return (bool) Digitalisimo_Integrations_SEO_Resolver::option( 'perf_css_defer' ) && self::selected( $handle ) && self::elementor_widget_source( $handle, $href );
	}

	public static function defer_widget_css( $html, $handle, $href, $media ) {
		if ( ! Digitalisimo_Integrations_Performance_Manager::frontend_safe() || ! self::configured( $handle, $href ) ) return $html;
		if ( ! in_array( strtolower( (string) $media ), array( '', 'all', 'screen' ), true ) || ! preg_match( '/\brel\s*=\s*[\'\"]stylesheet[\'\"]/i', $html ) || false !== stripos( $html, 'onload=' ) ) return $html;
		$styles = wp_styles();
		$asset = $styles->registered[ $handle ] ?? null;
		if ( ! $asset || ! empty( $asset->extra['after'] ) ) return $html;
		// Conserva el enlace original íntegro para navegadores sin JavaScript.
		$deferred = preg_replace( '/\smedia\s*=\s*([\'\"])(?:all|screen)\1/i', '', $html, 1 );
		$deferred = preg_replace( '/\s*\/?>(?!.*<)/s', ' media="print" onload="this.onload=null;this.media=\'all\'" />', $deferred, 1, $count );
		if ( ! $count || ! is_string( $deferred ) ) return $html;
		return $deferred . '<noscript>' . $html . '</noscript>';
	}

	public static function caption_rule() {
		if ( ! Digitalisimo_Integrations_Performance_Manager::frontend_safe() || ! Digitalisimo_Integrations_SEO_Resolver::option( 'perf_caption_normal' ) ) return;
		echo '<style id="digitalisimo-performance-caption">.elementor-image-carousel-caption{font-style:normal!important}</style>' . "\n";
	}

	public static function custom_css() {
		if ( ! Digitalisimo_Integrations_Performance_Manager::frontend_safe() || Digitalisimo_Integrations_SEO_Resolver::option( 'perf_custom_css_paused' ) ) return;
		$css = self::sanitize_custom_css( Digitalisimo_Integrations_SEO_Resolver::option( 'perf_custom_css' ) );
		if ( $css ) echo '<style id="digitalisimo-performance-custom">' . $css . '</style>' . "\n";
	}
}
