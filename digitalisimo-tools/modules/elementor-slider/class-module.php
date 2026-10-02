<?php
namespace Digitalisimo\Tools;

defined( 'ABSPATH' ) || exit;

final class Elementor_Slider {
	public static function init() {
		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'category' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'widget' ) );
		add_action( 'elementor/frontend/after_register_styles', array( __CLASS__, 'style' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'style' ), 5 );
	}

	public static function category( $manager ) {
		$manager->add_category( 'digitalisimo', array( 'title' => 'DIGITALÍSIMO', 'icon' => 'eicon-slider-album' ) );
	}

	public static function style() {
		if ( ! wp_style_is( 'digitalisimo-slider-optimizado', 'registered' ) ) {
			wp_register_style( 'digitalisimo-slider-optimizado', plugins_url( 'assets/slider-optimizado.css', DIGITALISIMO_TOOLS_FILE ), array(), DIGITALISIMO_TOOLS_VERSION );
		}
	}

	public static function widget( $manager ) {
		if ( ! class_exists( '\\Elementor\\Widget_Base' ) ) return;
		require_once __DIR__ . '/class-widget.php';
		self::style();
		$manager->register( new Elementor_Slider_Widget() );
	}
}
