<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

final class Elementor_Slider {
	public static function init() {
		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'category' ), 20 );
		// Tools anterior registraba el mismo widget y estilo con prioridad 10.
		add_action( 'elementor/widgets/register', array( __CLASS__, 'widget' ), 20 );
		add_action( 'elementor/frontend/after_register_styles', array( __CLASS__, 'style' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'style' ), 20 );
	}

	public static function category( $manager ) {
		$categories = method_exists( $manager, 'get_categories' ) ? $manager->get_categories() : array();
		if ( ! isset( $categories['digitalisimo'] ) ) {
			$manager->add_category( 'digitalisimo', array( 'title' => 'DIGITALÍSIMO', 'icon' => 'eicon-slider-album' ) );
		}
	}

	public static function style() {
		$handle = 'digitalisimo-slider-optimizado';
		$url = plugins_url( 'assets/css/slider-optimizado.css', DIGITALISIMO_ELEMENTS_FILE );
		if ( wp_style_is( $handle, 'registered' ) ) {
			global $wp_styles;
			$current = isset( $wp_styles->registered[ $handle ] ) ? $wp_styles->registered[ $handle ] : null;
			if ( $current && $current->src === $url && $current->ver === DIGITALISIMO_ELEMENTS_VERSION ) return;
			wp_deregister_style( $handle );
		}
		wp_register_style( $handle, $url, array(), DIGITALISIMO_ELEMENTS_VERSION );
	}

	public static function widget( $manager ) {
		if ( ! class_exists( '\\Elementor\\Widget_Base' ) ) return;
		require_once __DIR__ . '/class-widget.php';
		self::style();
		if ( method_exists( $manager, 'get_widget_types' ) && $manager->get_widget_types( 'digitalisimo-slider-optimizado' ) && method_exists( $manager, 'unregister' ) ) {
			$manager->unregister( 'digitalisimo-slider-optimizado' );
		}
		$manager->register( new Elementor_Slider_Widget() );
	}
}
