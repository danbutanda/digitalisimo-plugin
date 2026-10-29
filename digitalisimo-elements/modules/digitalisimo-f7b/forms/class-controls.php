<?php
namespace Digitalisimo\Elements\F7b\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Controles de las reglas en cada campo del Formulario (F7b, frente J; D20, V.9 y V.12).
 *
 * Entran por el mismo camino que los tipos de campo de PRO (`modules/forms/fields/field-base.php:108-109`):
 * `elementor/element/form/section_form_fields/before_section_end` y `update_control( 'form_fields', [ 'fields' => … ] )`,
 * al final de la pestaña «Contenido» del campo, detrás de los controles que añaden los tipos de PRO (prioridad 20).
 *
 * - `digitalisimo_conditional`: interruptor que guarda `yes` (la aguja de `Flags`).
 * - `digitalisimo_logic`: '' (todas, sin `default`) o `any` (alguna).
 * - `digitalisimo_rules`: **repetidor dentro del repetidor** `form_fields`. El núcleo lo admite en PHP (recorren los
 *   repetidores de forma recursiva `Control_Repeater::get_value()`, `Controls_Stack::get_active_settings()`,
 *   `parse_dynamic_settings()` y el saneado de `sanitize_settings()`) y en el editor (cada fila es un `Container` que
 *   crea los suyos en `handleRepeaterChildren()`, y `BaseSettings` convierte en colección cualquier campo con
 *   `is_repeater`, `elementor/assets/js/editor-modules.js:1008-1013, 2049-2097`). Por eso el campo lleva los ajustes
 *   del control repetidor escritos (`is_repeater`, `item_actions`…): las filas del repetidor de fuera no los fusionan.
 *   Cada regla: `field` (ID de otro campo), `operator` (D20) y `value`.
 * - `digitalisimo_cond_notice`: aviso (C2-12 c: los valores se ven en el código; V.11: con una versión anterior el
 *   campo se vería siempre) y el hueco que `forms-conditions-editor.js` llena con los avisos de cada fila (una regla
 *   que mira a un campo posterior, C2-12 e; un ID que no existe; ninguna regla).
 *
 * Nada de esto tiene un `default` que cambie el HTML o el CSS: el interruptor nace apagado y lo demás sólo se ve con
 * él encendido. La única excepción es `operator` dentro de una regla (`is`, con `save_default` para que se guarde
 * siempre y el servidor nunca dependa de que los controles estén registrados). Todos llevan `style_transfer => false`
 * (V.12). Sin reglas en `Rules::EXCLUDED` (C2-12 d).
 */
final class Controls {
	const TOGGLE = 'digitalisimo_conditional';
	const LOGIC  = 'digitalisimo_logic';
	const RULES  = 'digitalisimo_rules';
	const NOTICE = 'digitalisimo_cond_notice';

	/** Campo del repetidor delante del cual se insertan (fin de la pestaña «Contenido»). */
	const BEFORE = 'form_fields_advanced_tab';

	/**
	 * Añade los controles al repetidor `form_fields` del widget. Idempotente.
	 *
	 * @param object $widget Widget `form`.
	 */
	public static function register( $widget ): void {
		if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_unique_name' ) || ! method_exists( $widget, 'update_control' ) || ! class_exists( '\Elementor\Plugin' ) ) {
			return;
		}
		$plugin = \Elementor\Plugin::$instance;
		if ( ! is_object( $plugin ) || ! isset( $plugin->controls_manager ) ) {
			return;
		}
		$control = $plugin->controls_manager->get_control_from_stack( $widget->get_unique_name(), 'form_fields' );
		if ( ! is_array( $control ) || ! isset( $control['fields'] ) || ! is_array( $control['fields'] ) || isset( $control['fields'][ self::TOGGLE ] ) ) {
			return;
		}
		$control['fields'] = self::inject( $control['fields'], self::fields() );
		$widget->update_control( 'form_fields', $control );
	}

	/**
	 * Inserta `$new` delante de `BEFORE` (o al final si no existe), conservando las claves.
	 *
	 * @param array $fields Campos del repetidor.
	 * @param array $new    Campos nuevos por nombre.
	 */
	public static function inject( array $fields, array $new ): array {
		$keys  = array_keys( $fields );
		$index = array_search( self::BEFORE, $keys, true );
		if ( false === $index ) {
			return $fields + $new;
		}
		return array_slice( $fields, 0, $index, true ) + $new + array_slice( $fields, $index, null, true );
	}

	/** Campos nuevos del repetidor, por nombre. */
	public static function fields(): array {
		$c    = '\Elementor\Controls_Manager';
		$tabs = array(
			'tab'            => 'content',
			'inner_tab'      => 'form_fields_content_tab',
			'tabs_wrapper'   => 'form_fields_tabs',
		);
		$type = array(
			'name'     => 'field_type',
			'operator' => '!in',
			'value'    => Rules::EXCLUDED,
		);
		$on   = array(
			'terms' => array(
				$type,
				array(
					'name'     => self::TOGGLE,
					'operator' => '===',
					'value'    => 'yes',
				),
			),
		);

		return array(
			self::TOGGLE => array(
				'name'           => self::TOGGLE,
				'label'          => __( 'Mostrar sólo si…', 'pro-elements' ),
				'type'           => $c::SWITCHER,
				'return_value'   => 'yes',
				'label_on'       => __( 'Sí', 'pro-elements' ),
				'label_off'      => __( 'No', 'pro-elements' ),
				'separator'      => 'before',
				'conditions'     => array( 'terms' => array( $type ) ),
				'style_transfer' => false,
			) + $tabs,
			self::LOGIC  => array(
				'name'           => self::LOGIC,
				'label'          => __( 'Se muestra si se cumplen', 'pro-elements' ),
				'type'           => $c::SELECT,
				'options'        => array(
					''    => __( 'Todas las reglas', 'pro-elements' ),
					'any' => __( 'Alguna regla', 'pro-elements' ),
				),
				'conditions'     => $on,
				'style_transfer' => false,
			) + $tabs,
			self::RULES  => array(
				'name'          => self::RULES,
				'label'         => __( 'Reglas', 'pro-elements' ),
				'type'          => $c::REPEATER,
				'is_repeater'   => true,
				'fields'        => self::rule_fields(),
				'title_field'   => self::title_field(),
				'prevent_empty' => false,
				'min_items'     => 0,
				'max_items'     => Rules::MAX_RULES,
				'item_actions'  => array(
					'add'       => true,
					'duplicate' => true,
					'remove'    => true,
					'sort'      => true,
				),
				'conditions'     => $on,
				'style_transfer' => false,
			) + $tabs,
			self::NOTICE => array(
				'name'            => self::NOTICE,
				'type'            => $c::RAW_HTML,
				'raw'             => self::notice(),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
				'conditions'      => $on,
				'style_transfer'  => false,
			) + $tabs,
		);
	}

	/** Campos de una regla: un repetidor del núcleo, como el de los campos (`modules/forms/widgets/form.php:109`). */
	private static function rule_fields(): array {
		$c        = '\Elementor\Controls_Manager';
		$repeater = new \Elementor\Repeater();
		$repeater->add_control(
			'field',
			array(
				'label'          => __( 'ID del campo', 'pro-elements' ),
				'type'           => $c::TEXT,
				'placeholder'    => __( 'p. ej. pais', 'pro-elements' ),
				'description'    => __( 'El ID que el otro campo muestra en su pestaña Avanzado.', 'pro-elements' ),
				'label_block'    => true,
				'ai'             => array( 'active' => false ),
				'style_transfer' => false,
			)
		);
		$repeater->add_control(
			'operator',
			array(
				'label'          => __( 'Condición', 'pro-elements' ),
				'type'           => $c::SELECT,
				'options'        => self::operators(),
				'default'        => 'is',
				'save_default'   => true,
				'style_transfer' => false,
			)
		);
		$repeater->add_control(
			'value',
			array(
				'label'          => __( 'Valor', 'pro-elements' ),
				'type'           => $c::TEXT,
				'description'    => __( 'Sin distinguir mayúsculas ni espacios al principio o al final. Para «mayor que» y «menor que», un número con coma o punto decimal.', 'pro-elements' ),
				'label_block'    => true,
				'ai'             => array( 'active' => false ),
				'style_transfer' => false,
				'conditions'     => array(
					'terms' => array(
						array(
							'name'     => 'operator',
							'operator' => '!in',
							'value'    => array( 'empty', 'not_empty' ),
						),
					),
				),
			)
		);
		return $repeater->get_controls();
	}

	/** Nombres de los operadores de D20. */
	public static function operators(): array {
		return array(
			'is'           => __( 'es igual a', 'pro-elements' ),
			'is_not'       => __( 'no es igual a', 'pro-elements' ),
			'contains'     => __( 'contiene', 'pro-elements' ),
			'not_contains' => __( 'no contiene', 'pro-elements' ),
			'empty'        => __( 'está vacío', 'pro-elements' ),
			'not_empty'    => __( 'no está vacío', 'pro-elements' ),
			'greater'      => __( 'es mayor que', 'pro-elements' ),
			'less'         => __( 'es menor que', 'pro-elements' ),
		);
	}

	/** Título de cada regla en el panel («pais es igual a mx»), siempre escapado. */
	private static function title_field(): string {
		$names = function_exists( 'wp_json_encode' ) ? wp_json_encode( self::operators() ) : json_encode( self::operators() );
		return '<# var digiOps = ' . $names . '; #>{{ field }} {{ digiOps[ operator ] || operator }} {{ value }}';
	}

	/** Aviso fijo (C2-12 c y V.11) y el hueco de los avisos de cada fila (C2-12 e). */
	private static function notice(): string {
		return esc_html__( 'Los valores de las reglas se ven en el código de la página: no escribas datos privados. Un campo oculto no se envía ni se valida, aunque sea obligatorio. Con una versión anterior de DIGITALÍSIMO Elements las reglas no se aplican: el campo se vería siempre y, si es obligatorio, lo sería siempre.', 'pro-elements' )
			. '<span class="digi-cond-warn" role="status" style="display:block;margin-block-start:.5em;font-weight:600"></span>';
	}
}
