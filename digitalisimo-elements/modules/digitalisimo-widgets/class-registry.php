<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Registro de componentes propios; Elementor solicita los assets sólo al usar el widget. */
final class Widget_Registry {
	private const WIDGETS = array(
		'digitalisimo-comparison-list' => array(
			'file'  => 'class-comparison-list.php',
			'class' => Comparison_List_Widget::class,
			'css'   => 'assets/css/comparison-list.css',
		),
		'digitalisimo-content-switcher' => array(
			'file'  => 'class-content-switcher.php',
			'class' => Content_Switcher_Widget::class,
			'css'   => 'assets/css/content-switcher.css',
		),
		'digitalisimo-dual-button' => array(
			'file'  => 'class-dual-button.php',
			'class' => Dual_Button_Widget::class,
			'css'   => 'assets/css/dual-button.css',
		),
		'digitalisimo-call-out' => array(
			'file'  => 'class-call-out.php',
			'class' => Call_Out_Widget::class,
			'css'   => 'assets/css/call-out.css',
		),
		'digitalisimo-breadcrumbs' => array(
			'file'  => 'class-breadcrumbs.php',
			'class' => Breadcrumbs_Widget::class,
			'css'   => 'assets/css/breadcrumbs.css',
		),
		'digitalisimo-animated-heading' => array(
			'file'  => 'class-animated-heading.php',
			'class' => Animated_Heading_Widget::class,
			'css'   => 'assets/css/animated-heading.css',
		),
		'digitalisimo-advanced-icon-box' => array(
			'file'  => 'class-advanced-icon-box.php',
			'class' => Advanced_Icon_Box_Widget::class,
			'css'   => 'assets/css/advanced-icon-box.css',
		),
		'digitalisimo-advanced-divider' => array(
			'file'  => 'class-advanced-divider.php',
			'class' => Advanced_Divider_Widget::class,
			'css'   => 'assets/css/advanced-divider.css',
		),
		'digitalisimo-advanced-button' => array(
			'file'  => 'class-advanced-button.php',
			'class' => Advanced_Button_Widget::class,
			'css'   => 'assets/css/advanced-button.css',
		),
		'digitalisimo-accordion' => array(
			'file'  => 'class-accordion.php',
			'class' => Accordion_Widget::class,
			'css'   => 'assets/css/accordion.css',
		),
		'digitalisimo-animated-link' => array(
			'file'  => 'class-animated-link.php',
			'class' => Animated_Link_Widget::class,
			'css'   => 'assets/css/animated-link.css',
		),
		'digitalisimo-fancy-list' => array(
			'file'  => 'class-fancy-list.php',
			'class' => Fancy_List_Widget::class,
			'css'   => 'assets/css/fancy-list.css',
		),
		'digitalisimo-document-viewer' => array(
			'file'  => 'class-document-viewer.php',
			'class' => Document_Viewer_Widget::class,
			'css'   => 'assets/css/document-viewer.css',
		),
		'digitalisimo-brand-carousel' => array(
			'file'  => 'class-brand-carousel.php',
			'class' => Brand_Carousel_Widget::class,
			'css'   => 'assets/css/brand-carousel.css',
		),
		'digitalisimo-logo-carousel' => array(
			'file'  => 'class-logo-carousel.php',
			'class' => Logo_Carousel_Widget::class,
			'css'   => 'assets/css/logo-carousel.css',
		),
		'digitalisimo-advanced-heading' => array(
			'file'  => 'class-advanced-heading.php',
			'class' => Advanced_Heading_Widget::class,
			'css'   => 'assets/css/advanced-heading.css',
		),
		'digitalisimo-brand-grid' => array(
			'file'  => 'class-brand-grid.php',
			'class' => Brand_Grid_Widget::class,
			'css'   => 'assets/css/brand-grid.css',
		),
		'digitalisimo-logo-grid' => array(
			'file'  => 'class-logo-grid.php',
			'class' => Logo_Grid_Widget::class,
			'css'   => 'assets/css/logo-grid.css',
		),
		'digitalisimo-scroll-button' => array(
			'file'  => 'class-scroll-button.php',
			'class' => Scroll_Button_Widget::class,
			'css'   => 'assets/css/scroll-button.css',
		),
	);

	public static function init() {
		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'category' ), 20 );
		add_action( 'elementor/frontend/after_register_styles', array( __CLASS__, 'styles' ) );
		add_action( 'elementor/frontend/after_register_scripts', array( __CLASS__, 'scripts' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'styles' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'scripts' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'widgets' ), 20 );
	}

	public static function category( $manager ) {
		$categories = method_exists( $manager, 'get_categories' ) ? $manager->get_categories() : array();
		if ( ! isset( $categories['digitalisimo'] ) ) {
			$manager->add_category( 'digitalisimo', array( 'title' => 'DIGITALÍSIMO', 'icon' => 'eicon-star' ) );
		}
	}

	public static function styles() {
		if ( ! wp_style_is( 'digitalisimo-carousel-engine', 'registered' ) ) {
			wp_register_style( 'digitalisimo-carousel-engine', plugins_url( 'assets/css/carousel-engine.css', DIGITALISIMO_ELEMENTS_FILE ), array(), DIGITALISIMO_ELEMENTS_VERSION );
		}
		foreach ( self::WIDGETS as $handle => $widget ) {
			if ( ! wp_style_is( $handle, 'registered' ) ) {
				wp_register_style( $handle, plugins_url( $widget['css'], DIGITALISIMO_ELEMENTS_FILE ), array(), DIGITALISIMO_ELEMENTS_VERSION );
			}
		}
	}

	public static function scripts() {
		if ( ! wp_script_is( 'digitalisimo-content-switcher', 'registered' ) ) {
			wp_register_script( 'digitalisimo-content-switcher', plugins_url( 'assets/js/content-switcher.js', DIGITALISIMO_ELEMENTS_FILE ), array(), DIGITALISIMO_ELEMENTS_VERSION, true );
		}
		if ( ! wp_script_is( 'digitalisimo-animated-heading', 'registered' ) ) {
			wp_register_script( 'digitalisimo-animated-heading', plugins_url( 'assets/js/animated-heading.js', DIGITALISIMO_ELEMENTS_FILE ), array(), DIGITALISIMO_ELEMENTS_VERSION, true );
		}
		if ( ! wp_script_is( 'digitalisimo-carousel-engine', 'registered' ) ) {
			wp_register_script( 'digitalisimo-carousel-engine', plugins_url( 'assets/js/carousel-engine.js', DIGITALISIMO_ELEMENTS_FILE ), array( 'elementor-frontend' ), DIGITALISIMO_ELEMENTS_VERSION, true );
		}
		if ( ! wp_script_is( 'digitalisimo-scroll-button', 'registered' ) ) {
			wp_register_script( 'digitalisimo-scroll-button', plugins_url( 'assets/js/scroll-button.js', DIGITALISIMO_ELEMENTS_FILE ), array(), DIGITALISIMO_ELEMENTS_VERSION, true );
		}
	}

	public static function widgets( $manager ) {
		if ( ! class_exists( '\\Elementor\\Widget_Base' ) ) {
			return;
		}
		self::styles();
		self::scripts();
		foreach ( self::WIDGETS as $id => $widget ) {
			if ( method_exists( $manager, 'get_widget_types' ) && $manager->get_widget_types( $id ) ) {
				continue;
			}
			require_once __DIR__ . '/' . $widget['file'];
			$manager->register( new $widget['class']() );
		}
		// Element Pack conserva sus IDs mientras esté activo; nunca lo reemplazamos.
		if ( defined( 'BDTEP_VER' ) || class_exists( '\\ElementPack\\Element_Pack_Loader', false ) ) {
			return;
		}
		if ( method_exists( $manager, 'get_widget_types' ) && $manager->get_widget_types( 'bdt-animated-link' ) ) {
			return;
		}
		require_once __DIR__ . '/class-animated-link.php';
		$manager->register( new Legacy_Animated_Link_Widget() );
	}
}
