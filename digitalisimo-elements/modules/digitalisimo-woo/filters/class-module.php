<?php
namespace Digitalisimo\Elements\Woo\Filters;

use Digitalisimo\Elements\Woo\Module as Woo;

defined( 'ABSPATH' ) || exit;

/**
 * Filtros de precio y de atributo (F4-Woo, frente F; V.8 de la especificación).
 *
 * Arranca en `plugins_loaded` desde `Woo\Module` (sólo con WooCommerce compatible), nunca en `init`: el módulo
 * `loop-filter` de PRO lee la URL al construirse en `elementor/init` (`init` 0), y para entonces los tipos ya deben estar
 * registrados (lección 11 de F7a, V-M3).
 *
 * - Registra los tipos `digitalisimo_price` (`digi_price_*`) y `digitalisimo_attribute` (`digiattr_*`) en `Types`, que
 *   engancha los tres filtros del derivado de PRO (V.8.2).
 * - Invalida los transitorios de `Bounds` con los eventos de producto.
 * - Registra `filters-form.js` (`digitalisimo-woo-filters-form`, con `defer` y detrás del coordinador): el
 *   comportamiento de los dos formularios. El coordinador (`filters.js`, `digitalisimo-woo-filters`) lo registra
 *   `Woo\Assets` con la dependencia `elementor-frontend`. Son dos archivos para que cada uno quepa en 8 KB; los dos
 *   widgets declaran los dos handles y su hoja (`digitalisimo-woo-filters`). En el front no se encola nada más.
 * - Encola `filters-editor.js` en el editor, que rellena la lista de Loop Grid del documento (la de PRO sólo sirve a
 *   su widget).
 */
final class Module {
	const EDITOR_HANDLE = 'digitalisimo-woo-filters-editor';
	const EDITOR_SCRIPT = 'assets/js/filters-editor.js';
	const FORM_HANDLE   = 'digitalisimo-woo-filters-form';
	const FORM_SCRIPT   = 'assets/js/filters-form.js';

	/** @var bool */
	private static $initialized = false;

	/** Con WooCommerce: tipos, invalidación y script del editor. Idempotente. */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		$symbols = array( 'wc_get_attribute_taxonomies()', 'wc_get_attribute_taxonomy_names()', 'wc_attribute_taxonomy_name()', 'wc_get_price_decimals()', 'WC_Tax::get_rates' );
		if ( ! Woo::requires( 'filters', $symbols ) ) {
			return;
		}
		Types::register( Types::PRICE, Types::PRICE_PREFIX, array( Query::class, 'apply_price' ) );
		Types::register( Types::ATTRIBUTE, Types::ATTRIBUTE_PREFIX, array( Query::class, 'apply_attribute' ) );
		Bounds::init();
		add_action( 'elementor/frontend/after_register_scripts', array( __CLASS__, 'register_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_scripts' ), 5 );
		add_action( 'elementor/editor/after_enqueue_scripts', array( __CLASS__, 'editor_script' ) );
	}

	/** Registra `filters-form.js` detrás del coordinador. Idempotente. */
	public static function register_scripts(): void {
		if ( ! defined( 'DIGITALISIMO_ELEMENTS_FILE' ) || wp_script_is( self::FORM_HANDLE, 'registered' ) || ! is_file( dirname( DIGITALISIMO_ELEMENTS_FILE ) . '/' . self::FORM_SCRIPT ) ) {
			return;
		}
		$version = defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : false;
		wp_register_script( self::FORM_HANDLE, plugins_url( self::FORM_SCRIPT, DIGITALISIMO_ELEMENTS_FILE ), array( 'digitalisimo-woo-filters' ), $version, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	}

	/** `elementor/editor/after_enqueue_scripts`: el script que rellena «Loop Grid» en los dos widgets. */
	public static function editor_script(): void {
		if ( ! defined( 'DIGITALISIMO_ELEMENTS_FILE' ) || ! is_file( dirname( DIGITALISIMO_ELEMENTS_FILE ) . '/' . self::EDITOR_SCRIPT ) ) {
			return;
		}
		$version = defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : false;
		wp_enqueue_script( self::EDITOR_HANDLE, plugins_url( self::EDITOR_SCRIPT, DIGITALISIMO_ELEMENTS_FILE ), array( 'elementor-editor' ), $version, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	}

	/** Para las pruebas. */
	public static function reset(): void {
		self::$initialized = false;
	}
}
