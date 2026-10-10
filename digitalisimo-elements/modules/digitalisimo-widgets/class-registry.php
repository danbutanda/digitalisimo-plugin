<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Registro de componentes propios; Elementor solicita los assets sólo al usar el widget. */
final class Widget_Registry {
	private const WIDGETS = array(
		'digitalisimo-video-player' => array(
			'file'  => 'class-video-player.php',
			'class' => Video_Player_Widget::class,
			'css'   => 'modules/digitalisimo-widgets/assets/css/video-player.css',
		),
		'digitalisimo-user-register' => array(
			'file'  => 'class-user-register.php',
			'class' => User_Register_Widget::class,
			'css'   => 'modules/digitalisimo-widgets/assets/css/user-register.css',
		),
		'digitalisimo-total-count' => array(
			'file'  => 'class-total-count.php',
			'class' => Total_Count_Widget::class,
			'css'   => 'modules/digitalisimo-widgets/assets/css/total-count.css',
		),
		'digitalisimo-tags-cloud' => array(
			'file'  => 'class-tags-cloud.php',
			'class' => Tags_Cloud_Widget::class,
			'css'   => 'modules/digitalisimo-widgets/assets/css/tags-cloud.css',
		),
		'digitalisimo-table' => array(
			'file'  => 'class-table.php',
			'class' => Table_Widget::class,
			'css'   => 'modules/digitalisimo-widgets/assets/css/table.css',
		),
		'digitalisimo-qr-code' => array(
			'file'  => 'class-qr-code.php',
			'class' => QR_Code_Widget::class,
			'css'   => 'modules/digitalisimo-widgets/assets/css/qr-code.css',
		),
		'digitalisimo-product-grid' => array(
			'file'  => 'class-product-grid.php',
			'class' => Product_Grid_Widget::class,
			'css'   => 'modules/digitalisimo-widgets/assets/css/product-grid.css',
		),
		'digitalisimo-notification' => array(
			'file'  => 'class-notification.php',
			'class' => Notification_Widget::class,
			'css'   => 'assets/css/notification.css',
		),
		'digitalisimo-icon-nav' => array(
			'file'  => 'class-icon-nav.php',
			'class' => Icon_Nav_Widget::class,
			'css'   => 'assets/css/icon-nav.css',
		),
		'digitalisimo-chart' => array(
			'file'  => 'class-chart.php',
			'class' => Chart_Widget::class,
			'css'   => 'assets/css/chart.css',
		),
		'digitalisimo-map' => array(
			'file'  => 'class-map.php',
			'class' => Map_Widget::class,
			'css'   => 'assets/css/map.css',
		),
		'digitalisimo-marquee' => array(
			'file'  => 'class-marquee.php',
			'class' => Marquee_Widget::class,
			'css'   => 'assets/css/marquee.css',
		),
		'digitalisimo-timeline' => array(
			'file'  => 'class-timeline.php',
			'class' => Timeline_Widget::class,
			'css'   => 'assets/css/timeline.css',
		),
		'digitalisimo-audio-player' => array(
			'file'  => 'class-audio-player.php',
			'class' => Audio_Player_Widget::class,
			'css'   => 'assets/css/audio-player.css',
		),
		'digitalisimo-image-compare' => array(
			'file'  => 'class-image-compare.php',
			'class' => Image_Compare_Widget::class,
			'css'   => 'assets/css/image-compare.css',
		),
		'digitalisimo-business-hours' => array(
			'file'  => 'class-business-hours.php',
			'class' => Business_Hours_Widget::class,
			'css'   => 'assets/css/business-hours.css',
		),
		'digitalisimo-progress-bars' => array(
			'file'  => 'class-progress-bars.php',
			'class' => Progress_Bars_Widget::class,
			'css'   => 'assets/css/progress-bars.css',
		),
		'digitalisimo-reading-time' => array(
			'file'  => 'class-reading-time.php',
			'class' => Reading_Time_Widget::class,
			'css'   => 'assets/css/reading-time.css',
		),
		'digitalisimo-reading-progress' => array(
			'file'  => 'class-reading-progress.php',
			'class' => Reading_Progress_Widget::class,
			'css'   => 'assets/css/reading-progress.css',
		),
		'digitalisimo-offcanvas' => array(
			'file'  => 'class-offcanvas.php',
			'class' => Offcanvas_Widget::class,
			'css'   => 'assets/css/offcanvas.css',
		),
		'digitalisimo-vertical-menu' => array(
			'file'  => 'class-vertical-menu.php',
			'class' => Vertical_Menu_Widget::class,
			'css'   => 'assets/css/vertical-menu.css',
		),
		'digitalisimo-icon-mobile-menu' => array(
			'file'  => 'class-icon-mobile-menu.php',
			'class' => Icon_Mobile_Menu_Widget::class,
			'css'   => 'assets/css/icon-mobile-menu.css',
		),
		'digitalisimo-google-reviews' => array(
			'file'  => 'class-google-reviews.php',
			'class' => Google_Reviews_Widget::class,
			'css'   => 'assets/css/google-reviews.css',
		),
		'digitalisimo-featured-box' => array(
			'file'  => 'class-featured-box.php',
			'class' => Featured_Box_Widget::class,
			'css'   => 'assets/css/featured-box.css',
		),
		'digitalisimo-fancy-tabs' => array(
			'file'  => 'class-fancy-tabs.php',
			'class' => Fancy_Tabs_Widget::class,
			'css'   => 'assets/css/fancy-tabs.css',
		),
		'digitalisimo-fancy-slider' => array(
			'file'  => 'class-fancy-slider.php',
			'class' => Fancy_Slider_Widget::class,
			'css'   => 'assets/css/fancy-slider.css',
		),
		'digitalisimo-fancy-icons' => array(
			'file'  => 'class-fancy-icons.php',
			'class' => Fancy_Icons_Widget::class,
			'css'   => 'assets/css/fancy-icons.css',
		),
		'digitalisimo-device-slider' => array(
			'file'  => 'class-device-slider.php',
			'class' => Device_Slider_Widget::class,
			'css'   => 'assets/css/device-slider.css',
		),
		'digitalisimo-fancy-card' => array(
			'file'  => 'class-fancy-card.php',
			'class' => Fancy_Card_Widget::class,
			'css'   => 'assets/css/fancy-card.css',
		),
		'digitalisimo-custom-gallery' => array(
			'file'  => 'class-custom-gallery.php',
			'class' => Custom_Gallery_Widget::class,
			'css'   => 'assets/css/custom-gallery.css',
		),
		'digitalisimo-creative-button' => array(
			'file'  => 'class-creative-button.php',
			'class' => Creative_Button_Widget::class,
			'css'   => 'assets/css/creative-button.css',
		),
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
		require_once __DIR__ . '/class-backdrop-filter.php';
		Backdrop_Filter_Extension::init();
		require_once __DIR__ . '/class-floating-effects.php';
		Floating_Effects_Extension::init();
		require_once __DIR__ . '/class-notation.php';
		Notation_Extension::init();
		require_once __DIR__ . '/class-shape-builder.php';
		Shape_Builder_Extension::init();
		require_once __DIR__ . '/class-text-gradient.php';
		Text_Gradient_Extension::init();
		require_once __DIR__ . '/class-image-shadow.php';
		Image_Shadow_Extension::init();
		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'category' ), 20 );
		add_action( 'elementor/frontend/after_register_styles', array( __CLASS__, 'styles' ) );
		add_action( 'elementor/frontend/after_register_scripts', array( __CLASS__, 'scripts' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'styles' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'scripts' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'widgets' ), 20 );
		require_once dirname( __DIR__ ) . '/digitalisimo-legacy/class-extensions.php';
		Legacy\Extensions::init();
		require_once dirname( __DIR__ ) . '/digitalisimo-legacy/class-shapes.php';
		Legacy\Shapes::init();
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			require_once dirname( __DIR__ ) . '/digitalisimo-legacy/class-migration.php';
			Legacy\Migration::init();
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once dirname( __DIR__ ) . '/digitalisimo-legacy/class-cli.php';
			\WP_CLI::add_command( 'digitalisimo-elements ep-migrate', Legacy\Cli::class );
		}
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
		if ( ! wp_script_is( 'digitalisimo-qr-vendor', 'registered' ) ) {
			wp_register_script( 'digitalisimo-qr-vendor', plugins_url( 'modules/digitalisimo-widgets/assets/js/third-party/jquery-qrcode.min.js', DIGITALISIMO_ELEMENTS_FILE ), array( 'jquery' ), DIGITALISIMO_ELEMENTS_VERSION, true );
		}
		if ( ! wp_script_is( 'digitalisimo-qr-code', 'registered' ) ) {
			wp_register_script( 'digitalisimo-qr-code', plugins_url( 'modules/digitalisimo-widgets/assets/js/qr-code.js', DIGITALISIMO_ELEMENTS_FILE ), array( 'digitalisimo-qr-vendor' ), DIGITALISIMO_ELEMENTS_VERSION, true );
		}
		if ( ! wp_script_is( 'digitalisimo-notification', 'registered' ) ) {
			wp_register_script( 'digitalisimo-notification', plugins_url( 'assets/js/notification.js', DIGITALISIMO_ELEMENTS_FILE ), array(), DIGITALISIMO_ELEMENTS_VERSION, true );
		}
		if ( ! wp_script_is( 'digitalisimo-google-reviews', 'registered' ) ) {
			wp_register_script( 'digitalisimo-google-reviews', plugins_url( 'assets/js/google-reviews.js', DIGITALISIMO_ELEMENTS_FILE ), array(), DIGITALISIMO_ELEMENTS_VERSION, true );
		}
		foreach ( array( 'image-compare', 'business-hours', 'marquee' ) as $script ) {
			if ( ! wp_script_is( 'digitalisimo-' . $script, 'registered' ) ) {
				wp_register_script( 'digitalisimo-' . $script, plugins_url( 'assets/js/' . $script . '.js', DIGITALISIMO_ELEMENTS_FILE ), array(), DIGITALISIMO_ELEMENTS_VERSION, true );
			}
		}
		if ( ! wp_script_is( 'digitalisimo-reading-progress', 'registered' ) ) {
			wp_register_script( 'digitalisimo-reading-progress', plugins_url( 'assets/js/reading-progress.js', DIGITALISIMO_ELEMENTS_FILE ), array(), DIGITALISIMO_ELEMENTS_VERSION, true );
		}
		if ( ! wp_script_is( 'digitalisimo-offcanvas', 'registered' ) ) {
			wp_register_script( 'digitalisimo-offcanvas', plugins_url( 'assets/js/offcanvas.js', DIGITALISIMO_ELEMENTS_FILE ), array(), DIGITALISIMO_ELEMENTS_VERSION, true );
		}
		if ( ! wp_script_is( 'digitalisimo-vertical-menu', 'registered' ) ) {
			wp_register_script( 'digitalisimo-vertical-menu', plugins_url( 'assets/js/vertical-menu.js', DIGITALISIMO_ELEMENTS_FILE ), array(), DIGITALISIMO_ELEMENTS_VERSION, true );
		}
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
		require_once dirname( __DIR__ ) . '/digitalisimo-legacy/class-adapters.php';
		Legacy\Adapters::register( $manager );
		if ( method_exists( $manager, 'get_widget_types' ) && $manager->get_widget_types( 'bdt-animated-link' ) ) {
			return;
		}
		require_once __DIR__ . '/class-animated-link.php';
		$manager->register( new Legacy_Animated_Link_Widget() );
	}
}
