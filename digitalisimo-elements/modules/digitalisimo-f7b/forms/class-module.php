<?php
namespace Digitalisimo\Elements\F7b\Forms;

use Digitalisimo\Elements\F7b\Flags;
use Digitalisimo\Elements\F7b\Module as F7b;

defined( 'ABSPATH' ) || exit;

/**
 * Lógica condicional de los formularios (F7b, frente J; C9, D20 y V.9 de la especificación).
 *
 * En cada campo del Formulario de PRO, «Mostrar sólo si…» con reglas `{ campo, operador, valor }` y «todas» o
 * «alguna» (`Controls`). El servidor pinta el estado inicial (`Render`), `forms-conditions.js` lo mantiene en el
 * navegador y el servidor vuelve a calcular las reglas con lo enviado: un campo oculto nunca se valida ni llega a
 * las acciones, aunque el navegador lo envíe (`Validation`). Las reglas se evalúan igual en los dos lados (`Rules`).
 *
 * Qué se engancha:
 * - **Envío** (`admin-ajax.php`, acción `elementor_pro_forms_send_form`): `elementor_pro/forms/validation` con
 *   prioridad 5, siempre, porque es lo que garantiza el criterio 11 aunque los contadores de `Flags` aún no se hayan
 *   recalculado (corre sólo en esa petición y no hace nada sin campos condicionales).
 * - **Con `Flags::active( 'form_conditions' )`** (algún documento los usa, o el editor): el filtro del pintado y la
 *   regla `[hidden]` del CSS del documento. Con la bandera apagada, nada más en el front.
 * - **Sólo en el editor:** los controles del repetidor y `forms-conditions-editor.js` (avisos de cada fila). Fuera del
 *   editor no hacen falta: el núcleo deja pasar las claves desconocidas de un repetidor y `Rules` aplica las mismas
 *   condiciones que los controles.
 */
final class Module {
	const EDITOR_HANDLE = 'digitalisimo-f7b-forms-conditions-editor';
	const EDITOR_SCRIPT = 'assets/forms-conditions-editor.js';

	/** Acción del envío del formulario de PRO (`Ajax_Handler::is_form_submitted()`). */
	const SUBMIT_ACTION = 'elementor_pro_forms_send_form';

	/** Prioridad de la validación (V.9): antes que reCAPTCHA v3 y Akismet. */
	const VALIDATION_PRIORITY = 5;

	/** Prioridad del pintado y de los controles: después de los tipos de campo de PRO. */
	const PRIORITY = 20;

	/** @var bool */
	private static $initialized = false;

	/** Arranque desde `F7b\Module::BOOT`. Idempotente; con un símbolo ausente no registra nada. */
	public static function init(): void {
		if ( self::$initialized || ! function_exists( 'add_action' ) ) {
			return;
		}
		self::$initialized = true;
		if ( ! F7b::requires( array( 'Elementor\Controls_Manager', 'Elementor\Plugin', 'wp_register_script()', 'wp_json_encode()', 'Digitalisimo\Elements\F7b\Flags::active' ), 'form_conditions' ) ) {
			return;
		}
		if ( self::is_submission() && F7b::requires( array( 'ElementorPro\Modules\Forms\Classes\Form_Record::remove_field', 'ElementorPro\Modules\Forms\Classes\Ajax_Handler::set_success' ), 'form_conditions' ) ) {
			add_action( 'elementor_pro/forms/validation', array( Validation::class, 'validate' ), self::VALIDATION_PRIORITY, 2 );
		}
		if ( ! Flags::active( 'form_conditions' ) ) {
			return;
		}
		add_filter( 'elementor_pro/forms/render/item', array( Render::class, 'item' ), self::PRIORITY, 3 );
		add_action( 'elementor/element/parse_css', array( Render::class, 'parse_css' ), 10, 2 );
		if ( F7b::editing() ) {
			add_action( 'elementor/element/form/section_form_fields/before_section_end', array( Controls::class, 'register' ), self::PRIORITY );
			add_action( 'elementor/editor/after_enqueue_scripts', array( __CLASS__, 'editor_script' ) );
		}
	}

	/** ¿Es el envío de un formulario de PRO? Sólo lee la petición. */
	public static function is_submission(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Cualquier visitante envía el formulario (como PRO).
		$action = isset( $_POST['action'] ) && is_string( $_POST['action'] ) ? $_POST['action'] : '';
		return self::SUBMIT_ACTION === $action && ( ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) );
	}

	/** Script de los avisos del editor, con sus textos traducibles. Nunca en el front: el gancho es del editor. */
	public static function editor_script(): void {
		if ( ! function_exists( 'wp_enqueue_script' ) ) {
			return;
		}
		$version = defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : false;
		wp_enqueue_script( self::EDITOR_HANDLE, plugins_url( self::EDITOR_SCRIPT, __FILE__ ), array( 'elementor-editor' ), $version, true );
		wp_add_inline_script( self::EDITOR_HANDLE, 'window.digitalisimoFormConditions=' . wp_json_encode( array( 'i18n' => self::strings() ) ) . ';', 'before' );
	}

	/** Textos de `forms-conditions-editor.js`. `%s`: el ID de un campo. */
	public static function strings(): array {
		return array(
			'none'    => __( 'Sin reglas, el campo no se mostrará.', 'pro-elements' ),
			'invalid' => __( 'Una regla no tiene ID de campo: nunca se cumple.', 'pro-elements' ),
			'missing' => __( 'Ningún campo tiene el ID «%s».', 'pro-elements' ),
			'self'    => __( 'Una regla mira a este mismo campo.', 'pro-elements' ),
			'later'   => __( '«%s» va después de este campo: un campo debería aparecer detrás del que lo controla.', 'pro-elements' ),
		);
	}

	/** Para las pruebas: vuelve al estado anterior a `init()`. */
	public static function reset(): void {
		self::$initialized = false;
		Render::reset();
	}
}
