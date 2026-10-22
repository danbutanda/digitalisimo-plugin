<?php
namespace Digitalisimo\Elements\Design\Presets;

use Digitalisimo\Elements\Design\Module as Design;

defined( 'ABSPATH' ) || exit;

/**
 * Arranque de los presets de estilo (F7a, V.2 de la especificación). Lo llama `Design\Module::init()` en
 * `plugins_loaded`, antes de `init`, como pide el registro de partes de K.
 *
 * Guarda (V.9): con un núcleo anterior a 4.3 o sin alguno de los símbolos de `SYMBOLS`, no registra nada; los
 * vínculos guardados quedan inertes (el elemento se ve con su estilo local) y el editor avisa a quien administra.
 *
 * Inicia, si su archivo existe: `Guard` (guardado), `Ajax` (acciones del editor), `Controls` (el control del
 * vínculo), `Compiler` (aplanado en el CSS del documento), `Editor` (script del editor y kits, frentes D y E) y la
 * pestaña `Kit_Tab` de Ajustes del sitio.
 */
final class Module {
	/**
	 * Símbolos del núcleo que usan los presets.
	 *
	 * Sin `Element_Base`: el núcleo no la autocarga, la declara al crear su gestor de elementos en `init`, después de
	 * esta guarda (`plugins_loaded`), así que aquí siempre faltaría. `Compiler::flatten()` exige `instanceof
	 * Element_Base` al aplanar, cuando ya existe.
	 */
	const SYMBOLS = array(
		'Elementor\\Controls_Stack::get_data',
		'Elementor\\Controls_Stack::get_controls',
		'Elementor\\Core\\Base\\Base_Object::set_settings',
		'Elementor\\Core\\Frontend\\Performance::set_use_style_controls',
		'Elementor\\Core\\Frontend\\Performance::is_use_style_controls',
		'Elementor\\Modules\\KitElementsDefaults\\Utils\\Settings_Sanitizer::remove_invalid_settings',
		'Elementor\\Core\\Files\\CSS\\Post',
	);

	/** Clases del submódulo que se inician, en este orden. */
	const BOOT = array( 'Guard', 'Ajax', 'Controls', 'Compiler', 'Editor' );

	/** @var bool */
	private static $initialized = false;

	public static function init(): void {
		if ( self::$initialized || ! function_exists( 'add_action' ) ) {
			return;
		}
		self::$initialized = true;
		self::start( self::SYMBOLS );
	}

	/**
	 * Comprueba la guarda con `$symbols` y, si pasa, inicia las clases del submódulo. `init()` la llama con
	 * `SYMBOLS`; las pruebas, con un símbolo que falta para comprobar que entonces no se registra nada.
	 *
	 * @param string[] $symbols Símbolos del núcleo.
	 */
	public static function start( array $symbols ): bool {
		if ( ! Design::requires( 'presets', $symbols ) ) {
			return false;
		}
		foreach ( self::BOOT as $class ) {
			$class = __NAMESPACE__ . '\\' . $class;
			if ( '' !== Design::file_for( $class ) && class_exists( $class ) && method_exists( $class, 'init' ) ) {
				call_user_func( array( $class, 'init' ) );
			}
		}
		if ( '' !== Design::file_for( __NAMESPACE__ . '\Kit_Tab' ) ) {
			add_action( 'elementor/kit/register_tabs', array( __CLASS__, 'register_kit_tab' ) );
		}
		return true;
	}

	/**
	 * La pestaña «Presets de estilo» en Ajustes del sitio. La clase extiende `Tab_Base`, así que se carga aquí, cuando
	 * el kit ya existe.
	 *
	 * @param mixed $kit Documento del kit.
	 */
	public static function register_kit_tab( $kit ): void {
		if ( class_exists( 'Elementor\\Core\\Kits\\Documents\\Tabs\\Tab_Base' ) && class_exists( __NAMESPACE__ . '\Kit_Tab' ) ) {
			Kit_Tab::register( $kit );
		}
	}
}
