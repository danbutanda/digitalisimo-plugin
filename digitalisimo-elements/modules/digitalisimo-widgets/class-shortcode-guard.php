<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/**
 * «Ocultar si falta el shortcode»: opción del widget Shortcode del núcleo para que un shortcode cuyo plugin no está activo
 * no aparezca en la página como texto entre corchetes (WordPress deja tal cual un shortcode que nadie registró).
 *
 * - **Opt-in:** un interruptor sin `default` y con `style_transfer => false` al final de la sección «Shortcode»
 *   (`elementor/element/shortcode/section_shortcode/before_section_end`). Una instancia existente no cambia.
 * - **Front:** `elementor/frontend/widget/should_render` (prioridad 20, después de Visibilidad): si el interruptor está
 *   encendido y algún shortcode del campo no está registrado en el sitio (`shortcode_exists()`), el widget no se pinta,
 *   ni su envoltorio. Sin shortcodes en el campo, o todos registrados, no hace nada. Cubre también a las clases que
 *   heredan del widget (`instanceof \Elementor\Widget_Shortcode`), aunque se registren con otro nombre.
 * - **Editor:** en el lienzo se pinta siempre, para poder ver y corregir el shortcode.
 * - **Caché:** depende sólo de los plugins activos del sitio, nunca del visitante; el widget Shortcode del núcleo ya se
 *   pinta en cada petición (`is_dynamic_content()`).
 * - **Kits:** viaja como un ajuste más del widget.
 */
final class Shortcode_Guard {
	const KEY = 'digitalisimo_shortcode_if_registered';

	/**
	 * Etiqueta de apertura de un shortcode: `[` sin escapar (`[[x]]` es texto literal en WordPress) seguido del nombre,
	 * con los caracteres que `add_shortcode()` admite. Un cierre (`[/x]`) no casa porque el nombre no empieza por `/`.
	 */
	const TAG = '/\[(\[?)([^<>&\/\[\]\x00-\x20=]+)/';

	/** @var bool */
	private static $initialized = false;

	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		add_action( 'elementor/element/shortcode/section_shortcode/before_section_end', array( __CLASS__, 'register_controls' ), 10, 2 );
		add_filter( 'elementor/frontend/widget/should_render', array( __CLASS__, 'should_render' ), 20, 2 );
	}

	/**
	 * El interruptor, al final de la sección «Shortcode». Una sola vez por pila.
	 *
	 * @param object $element Widget Shortcode.
	 * @param array  $args    Argumentos de la sección.
	 */
	public static function register_controls( $element, $args = array() ): void {
		if ( ! is_object( $element ) || ! method_exists( $element, 'add_control' ) || ( method_exists( $element, 'get_controls' ) && null !== $element->get_controls( self::KEY ) ) ) {
			return;
		}
		$c = '\\Elementor\\Controls_Manager';
		$element->add_control(
			self::KEY,
			array(
				'label'          => __( 'Ocultar si falta el shortcode', 'pro-elements' ),
				'description'    => __( 'Si el plugin que registra el shortcode no está activo, no se muestra nada en lugar de su texto entre corchetes. En el editor siempre se ve.', 'pro-elements' ),
				'type'           => $c::SWITCHER,
				'return_value'   => 'yes',
				'separator'      => 'before',
				'style_transfer' => false,
			)
		);
	}

	/**
	 * Nombres de los shortcodes que abre el texto, en orden y sin repetir.
	 *
	 * @param mixed $text Valor del campo.
	 * @return string[]
	 */
	public static function tags( $text ): array {
		if ( ! is_string( $text ) || false === strpos( $text, '[' ) ) {
			return array();
		}
		preg_match_all( self::TAG, $text, $found, PREG_SET_ORDER );
		$tags = array();
		foreach ( $found as $match ) {
			if ( '' === $match[1] ) {
				$tags[ $match[2] ] = true;
			}
		}
		return array_keys( $tags );
	}

	/**
	 * ¿Abre el texto algún shortcode que el sitio no tiene registrado?
	 *
	 * @param mixed $text Valor del campo.
	 */
	public static function missing( $text ): bool {
		foreach ( self::tags( $text ) as $tag ) {
			if ( ! shortcode_exists( $tag ) ) {
				return true;
			}
		}
		return false;
	}

	/** ¿Se está pintando el lienzo del editor? Allí el widget se ve siempre. */
	private static function editing(): bool {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance->editor ) || ! is_object( \Elementor\Plugin::$instance->editor ) ) {
			return false;
		}
		return method_exists( \Elementor\Plugin::$instance->editor, 'is_edit_mode' ) && (bool) \Elementor\Plugin::$instance->editor->is_edit_mode();
	}

	/**
	 * `elementor/frontend/widget/should_render`: el widget Shortcode con el interruptor encendido no se pinta si le falta
	 * un shortcode. Cualquier otro widget, o uno ya oculto, no cambia.
	 *
	 * @param bool   $should_render Decisión hasta aquí.
	 * @param object $element       Widget.
	 */
	public static function should_render( $should_render, $element = null ) {
		if ( ! $should_render || ! $element instanceof \Elementor\Widget_Shortcode || ! method_exists( $element, 'get_settings' ) ) {
			return $should_render;
		}
		if ( 'yes' !== $element->get_settings( self::KEY ) || self::editing() ) {
			return $should_render;
		}
		$shortcode = method_exists( $element, 'get_settings_for_display' ) ? $element->get_settings_for_display( 'shortcode' ) : $element->get_settings( 'shortcode' );
		return self::missing( $shortcode ) ? false : $should_render;
	}

	/** Para las pruebas. */
	public static function reset(): void {
		self::$initialized = false;
	}
}
