<?php
namespace Digitalisimo\Elements\F7b\Actions;

use Digitalisimo\Elements\F7b\Flags;
use Digitalisimo\Elements\F7b\Module as F7b;

defined( 'ABSPATH' ) || exit;

/**
 * Acciones con destino e interacciones PRO (F7b, frente B; C1, D6 y V.3 de la especificación).
 *
 * - `Interactions_State` arranca siempre (cron del recorrido, aviso y acción de la administración): decide si el
 *   módulo derivado `modules/interactions/module.php` registra las interacciones PRO.
 * - Con `Flags::active( 'actions' )` y `Flags::active( 'hidden' )` falsos (ningún documento las usa y no es una
 *   petición del editor) no se engancha nada más: ni controles ni render ni scripts.
 * - Si no, `Controls` (sección «Acciones»), `Render` (marcado, `hidden`, popups y regla `[hidden]`) y, con acciones,
 *   el registro de `actions.js` (sólo se encola por la declaración `assets` del interruptor) y de `actions-editor.js`
 *   en el editor.
 */
final class Module {
	const HANDLE        = 'digitalisimo-f7b-actions';
	const EDITOR_HANDLE = 'digitalisimo-f7b-actions-editor';
	const SCRIPT        = 'modules/digitalisimo-f7b/assets/js/actions.js';
	const EDITOR_SCRIPT = 'modules/digitalisimo-f7b/assets/js/actions-editor.js';

	/** Símbolos que se pueden comprobar en `plugins_loaded` (las clases de elementos llegan en `init`). */
	const SYMBOLS = array(
		'Elementor\Controls_Manager',
		'Elementor\Plugin',
		'add_action()',
		'wp_register_script()',
		'plugins_url()',
	);

	/** @var bool */
	private static $initialized = false;

	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		Interactions_State::init();
		if ( ! class_exists( F7b::class ) || ! F7b::requires( self::SYMBOLS, 'actions' ) ) {
			return;
		}
		$actions = class_exists( Flags::class ) && Flags::active( 'actions' );
		$hidden  = class_exists( Flags::class ) && Flags::active( 'hidden' );
		if ( ! $actions && ! $hidden ) {
			return;
		}
		Controls::init();
		Render::init( $actions, $hidden );
		if ( $actions ) {
			add_action( 'elementor/frontend/after_register_scripts', array( __CLASS__, 'register_script' ) );
			add_action( 'elementor/editor/after_enqueue_scripts', array( __CLASS__, 'enqueue_editor' ) );
		}
	}

	/** Registra `actions.js` (`defer`, en el pie, sin jQuery); lo encola el cargador del núcleo por `assets`. */
	public static function register_script(): void {
		wp_register_script(
			self::HANDLE,
			plugins_url( self::SCRIPT, self::file() ),
			array( 'elementor-frontend' ),
			self::version(),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/** `actions-editor.js`: destinos del documento abierto y `click` según los descendientes interactivos. */
	public static function enqueue_editor(): void {
		wp_enqueue_script( self::EDITOR_HANDLE, plugins_url( self::EDITOR_SCRIPT, self::file() ), array( 'elementor-editor' ), self::version(), true );
		$texts = array(
			'none'    => __( 'Elige un elemento', 'pro-elements' ),
			'gone'    => __( 'Elemento que ya no está en este documento', 'pro-elements' ),
			'noClick' => __( ' (no disponible: el elemento tiene varios enlaces, botones o campos)', 'pro-elements' ),
		);
		wp_add_inline_script( self::EDITOR_HANDLE, 'window.digitalisimoActionsTexts=' . wp_json_encode( $texts ) . ';', 'before' );
	}

	private static function file(): string {
		return defined( 'DIGITALISIMO_ELEMENTS_FILE' ) ? DIGITALISIMO_ELEMENTS_FILE : dirname( __DIR__, 3 ) . '/pro-elements.php';
	}

	private static function version() {
		return defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : false;
	}

	/** Para las pruebas: vuelve al estado anterior a `init()` (también el de sus piezas). */
	public static function reset(): void {
		self::$initialized = false;
		Controls::reset();
		Render::reset( true );
		Interactions_State::reset();
	}
}
