<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/**
 * Amplía Display Conditions con condiciones de visitante, URL, contenido y WooCommerce.
 *
 * No crea otro motor: las condiciones se registran en el gestor nativo, se guardan en
 * `e_display_conditions` y se combinan con sus grupos Y/O, su interfaz y su caché.
 */
final class Display_Conditions {
	const GROUP_VISITOR     = 'digitalisimo_visitor';
	const GROUP_URL         = 'digitalisimo_url';
	const GROUP_WOOCOMMERCE = 'digitalisimo_woocommerce';

	/**
	 * Condiciones de F7a (`class-conditions-more.php`). Se registran siempre, también sin WooCommerce, y cada grupo
	 * que las usa lleva el centinela de `Conditions\Conditions_Guard`: volver a una versión que no las conoce, o
	 * llevarlas en un kit a ella, nunca amplía quién ve un elemento.
	 */
	const MORE = array(
		'digitalisimo_cookie',
		'digitalisimo_page_visit',
		'digitalisimo_field',
		'digitalisimo_user_field',
		'digitalisimo_author',
		'digitalisimo_product_stock_of',
		'digitalisimo_search',
	);

	public static function init() {
		add_action( 'elementor/display_conditions/register_groups', array( __CLASS__, 'register_groups' ) );
		add_action( 'elementor/display_conditions/register', array( __CLASS__, 'register_conditions' ) );
		add_action( 'wp_footer', array( Conditions\Visitor::class, 'print_timezone_script' ) );
		require_once __DIR__ . '/class-visibility.php';
		Visibility::init();
		require_once __DIR__ . '/class-visitor-content.php';
		Conditions\Visitor_Content::init();
		require_once __DIR__ . '/class-conditions-guard.php';
		Conditions\Conditions_Guard::init();
		require_once __DIR__ . '/class-page-visits.php';
		Conditions\Page_Visits::init();
		add_filter( 'digitalisimo_elements_kit_references', array( __CLASS__, 'kit_references' ) );
	}

	public static function register_groups( $manager ) {
		$manager->add_group( self::GROUP_VISITOR, array( 'label' => 'Visitante' ) );
		$manager->add_group( self::GROUP_URL, array( 'label' => 'URL y origen' ) );
		if ( self::woocommerce_active() ) {
			$manager->add_group( self::GROUP_WOOCOMMERCE, array( 'label' => 'WooCommerce' ) );
		}
	}

	public static function register_conditions( $manager ) {
		if ( ! class_exists( '\ElementorPro\Modules\DisplayConditions\Conditions\Base\Condition_Base' ) ) {
			return;
		}
		require_once __DIR__ . '/class-conditions.php';
		require_once __DIR__ . '/class-conditions-more.php';
		foreach ( self::condition_classes() as $class ) {
			$manager->register_condition_instance( new $class() );
		}
	}

	/** @return string[] */
	public static function condition_classes() {
		$classes = array(
			Conditions\User_Condition::class,
			Conditions\Operating_System_Condition::class,
			Conditions\Browser_Condition::class,
			Conditions\Language_Condition::class,
			Conditions\Country_Condition::class,
			Conditions\Url_Parameter_Condition::class,
			Conditions\Url_Path_Condition::class,
			Conditions\Search_Engine_Condition::class,
			Conditions\Post_Type_Condition::class,
			Conditions\Specific_Content_Condition::class,
			Conditions\Special_Page_Condition::class,
			Conditions\Shortcode_Condition::class,
		);
		if ( self::woocommerce_active() ) {
			$classes = array_merge( $classes, array(
				Conditions\Cart_Products_Condition::class,
				Conditions\Cart_Categories_Condition::class,
				Conditions\Cart_Tags_Condition::class,
				Conditions\Cart_Count_Condition::class,
				Conditions\Cart_Subtotal_Condition::class,
				Conditions\Customer_Bought_Condition::class,
				Conditions\Customer_Orders_Condition::class,
				Conditions\Customer_First_Purchase_Condition::class,
				Conditions\Customer_Last_Purchase_Condition::class,
				Conditions\Customer_Purchase_Date_Condition::class,
				Conditions\Product_Status_Condition::class,
				Conditions\Product_Type_Condition::class,
				Conditions\Product_Category_Condition::class,
				Conditions\Product_Price_Condition::class,
				Conditions\Product_Stock_Condition::class,
				Conditions\Product_Category_Archive_Condition::class,
			) );
		}
		return array_merge( $classes, self::more_classes() );
	}

	/** @return string[] Clases de las condiciones de F7a, en el orden de `MORE`. */
	public static function more_classes() {
		return array(
			Conditions\Cookie_Condition::class,
			Conditions\Page_Visit_Condition::class,
			Conditions\Field_Condition::class,
			Conditions\User_Field_Condition::class,
			Conditions\Author_Condition::class,
			Conditions\Product_Stock_Of_Condition::class,
			Conditions\Search_Condition::class,
		);
	}

	/**
	 * Referencias de kit de las condiciones de F7a con IDs (5.1): las dos deciden quién ve algo (`gate`), así que un
	 * destino que falta deja el documento en borrador con «revisar antes de publicar». Una lista que el remapeo vacía
	 * nunca se cumple.
	 *
	 * @param array $refs Referencias registradas.
	 */
	public static function kit_references( $refs ) {
		$refs   = is_array( $refs ) ? $refs : array();
		$refs[] = array( 'condition', 'digitalisimo_page_visit', 'pages.*', 'post', true );
		$refs[] = array( 'condition', 'digitalisimo_product_stock_of', 'products.*', 'post', true );
		return $refs;
	}

	private static function woocommerce_active() {
		return class_exists( 'WooCommerce' ) && function_exists( 'WC' );
	}
}
