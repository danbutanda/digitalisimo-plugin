<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/**
 * Amplía Display Conditions con las reglas de Visibility Controls de Element Pack.
 *
 * No crea otro motor: las condiciones se registran en el gestor nativo, se guardan en
 * `e_display_conditions` y se combinan con sus grupos Y/O, su interfaz y su caché.
 */
final class Display_Conditions {
	const GROUP_VISITOR     = 'digitalisimo_visitor';
	const GROUP_URL         = 'digitalisimo_url';
	const GROUP_WOOCOMMERCE = 'digitalisimo_woocommerce';

	public static function init() {
		add_action( 'elementor/display_conditions/register_groups', array( __CLASS__, 'register_groups' ) );
		add_action( 'elementor/display_conditions/register', array( __CLASS__, 'register_conditions' ) );
		add_action( 'wp_footer', array( Conditions\Visitor::class, 'print_timezone_script' ) );
		require_once __DIR__ . '/class-legacy-visibility.php';
		Legacy_Visibility::init();
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
		return $classes;
	}

	private static function woocommerce_active() {
		return class_exists( 'WooCommerce' ) && function_exists( 'WC' );
	}
}
