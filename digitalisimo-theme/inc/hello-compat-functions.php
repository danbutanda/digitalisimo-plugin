<?php
/**
 * DIGITALÍSIMO: funciones públicas de Hello 3.5.1 que usan los hijos, con un comportamiento equivalente y seguro.
 * Sólo lo carga digitalisimo_theme_hello_compat() (inc/hello-compat.php), en paridad; cada una sólo si no existe.
 */
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'hello_elementor_get_setting' ) ) {
	/** Un ajuste del kit activo del constructor (filtrable con `hello_elementor_<ajuste>`), o '' sin constructor. */
	function hello_elementor_get_setting( $setting_id ) {
		static $settings = null;
		if ( null === $settings ) {
			$settings = array();
			if ( did_action( 'elementor/loaded' ) && class_exists( 'Elementor\Plugin', false ) && isset( \Elementor\Plugin::$instance->kits_manager ) ) {
				$kit      = \Elementor\Plugin::$instance->kits_manager->get_active_kit();
				$settings = $kit && is_callable( array( $kit, 'get_settings' ) ) ? (array) $kit->get_settings() : array();
			}
		}
		return apply_filters( 'hello_elementor_' . $setting_id, $settings[ $setting_id ] ?? '' );
	}
}

if ( ! function_exists( 'hello_show_or_hide' ) ) {
	/** 'show' si el interruptor del kit está encendido; si no, 'hide'. */
	function hello_show_or_hide( $setting_id ) {
		return 'yes' === hello_elementor_get_setting( $setting_id ) ? 'show' : 'hide';
	}
}

if ( ! function_exists( 'hello_get_header_layout_class' ) ) {
	/** Clases de la cabecera configurable según el kit. */
	function hello_get_header_layout_class() {
		$map     = array(
			'hello_header_layout'        => array( 'inverted' => 'header-inverted', 'stacked' => 'header-stacked' ),
			'hello_header_width'         => array( 'full-width' => 'header-full-width' ),
			'hello_header_menu_dropdown' => array( 'tablet' => 'menu-dropdown-tablet', 'mobile' => 'menu-dropdown-mobile', 'none' => 'menu-dropdown-none' ),
			'hello_header_menu_layout'   => array( 'dropdown' => 'menu-layout-dropdown' ),
		);
		$classes = array();
		foreach ( $map as $setting => $values ) {
			$value = (string) hello_elementor_get_setting( $setting );
			if ( isset( $values[ $value ] ) ) {
				$classes[] = $values[ $value ];
			}
		}
		return implode( ' ', $classes );
	}
}

if ( ! function_exists( 'hello_get_footer_layout_class' ) ) {
	/** Clases del pie configurable según el kit. */
	function hello_get_footer_layout_class() {
		$map     = array(
			'hello_footer_layout' => array( 'inverted' => 'footer-inverted', 'stacked' => 'footer-stacked' ),
			'hello_footer_width'  => array( 'full-width' => 'footer-full-width' ),
		);
		$classes = array();
		foreach ( $map as $setting => $values ) {
			$value = (string) hello_elementor_get_setting( $setting );
			if ( isset( $values[ $value ] ) ) {
				$classes[] = $values[ $value ];
			}
		}
		if ( hello_elementor_get_setting( 'hello_footer_copyright_display' ) && '' !== hello_elementor_get_setting( 'hello_footer_copyright_text' ) ) {
			$classes[] = 'footer-has-copyright';
		}
		return implode( ' ', $classes );
	}
}

if ( ! function_exists( 'hello_get_header_display' ) ) {
	/** ¿Se pinta la cabecera configurable? En el editor, siempre. */
	function hello_get_header_display() {
		return isset( $_GET['elementor-preview'] ) || (bool) hello_elementor_get_setting( 'hello_header_logo_display' ) || (bool) hello_elementor_get_setting( 'hello_header_tagline_display' ) || (bool) hello_elementor_get_setting( 'hello_header_menu_display' ); // phpcs:ignore WordPress.Security.NonceVerification
	}
}

if ( ! function_exists( 'hello_get_footer_display' ) ) {
	/** ¿Se pinta el pie configurable? En el editor, siempre. */
	function hello_get_footer_display() {
		$display = isset( $_GET['elementor-preview'] ); // phpcs:ignore WordPress.Security.NonceVerification
		foreach ( array( 'hello_footer_logo_display', 'hello_footer_tagline_display', 'hello_footer_menu_display', 'hello_footer_copyright_display' ) as $setting ) {
			$display = $display || (bool) hello_elementor_get_setting( $setting );
		}
		return $display;
	}
}

if ( ! function_exists( 'hello_header_footer_experiment_active' ) ) {
	/** ¿Está activa la cabecera y pie configurables? Con el tema propio, lo que guardó el paso desde Hello. */
	function hello_header_footer_experiment_active() {
		$record = digitalisimo_theme_switch_record();
		return ! empty( $record['header_footer']['experiment'] );
	}
}

if ( ! function_exists( 'hello_elementor_display_header_footer' ) ) {
	/** ¿Se pintan cabecera y pie? (filtro `hello_elementor_header_footer`). */
	function hello_elementor_display_header_footer() {
		return (bool) apply_filters( 'hello_elementor_header_footer', true );
	}
}

if ( ! function_exists( 'hello_elementor_check_hide_title' ) ) {
	/** Apaga el título si el documento del constructor lo oculta. */
	function hello_elementor_check_hide_title( $val ) {
		if ( did_action( 'elementor/loaded' ) && class_exists( 'Elementor\Plugin', false ) && isset( \Elementor\Plugin::$instance->documents ) ) {
			$document = \Elementor\Plugin::$instance->documents->get( get_the_ID() );
			if ( $document && 'yes' === $document->get_settings( 'hide_title' ) ) {
				return false;
			}
		}
		return $val;
	}
}

if ( ! function_exists( 'hello_elementor_body_open' ) ) {
	/** Compatibilidad de Hello con plantillas antiguas: wp_body_open(). */
	function hello_elementor_body_open() {
		wp_body_open();
	}
}

if ( ! function_exists( 'hello_elementor_content_width' ) ) {
	/** El ancho del contenido: lo fija el tema (inc/setup.php), con el filtro de Hello en paridad. */
	function hello_elementor_content_width() {
		digitalisimo_theme_content_width();
	}
}

if ( ! function_exists( 'hello_elementor_register_elementor_locations' ) ) {
	/** Ubicaciones del Theme Builder: las registra el tema. */
	function hello_elementor_register_elementor_locations( $manager ) {
		digitalisimo_theme_register_locations( $manager );
	}
}

// Las que Hello engancha y el tema ya hace a su manera: llamarlas no hace nada más (sin efectos dobles).
if ( ! function_exists( 'hello_elementor_setup' ) ) {
	/** La configuración del tema la hace inc/setup.php. */
	function hello_elementor_setup() {}
}

if ( ! function_exists( 'hello_elementor_scripts_styles' ) ) {
	/** Las hojas de paridad las encola inc/assets.php. */
	function hello_elementor_scripts_styles() {}
}

if ( ! function_exists( 'hello_elementor_add_description_meta_tag' ) ) {
	/** El tema no imprime meta description (es de SEO). */
	function hello_elementor_add_description_meta_tag() {}
}

if ( ! function_exists( 'hello_elementor_customizer' ) ) {
	/** El tema no tiene controles propios en el Personalizador. */
	function hello_elementor_customizer() {}
}
