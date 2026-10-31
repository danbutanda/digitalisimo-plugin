<?php
namespace Digitalisimo\Elements\F7b\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Lo que el Formulario pinta con campos condicionales (F7b, frente J; V.9 y C2-2).
 *
 * - `elementor_pro/forms/render/item` (prioridad 20, después de `form_fields_render_attributes()` y de los filtros de
 *   PRO): en el grupo de cada campo condicional, `data-digi-cond` con las reglas saneadas (`Rules::attribute()`); si
 *   las reglas no se cumplen con los valores iniciales (`Rules::defaults()`), `hidden` en el grupo y `disabled` en sus
 *   controles, que así no se envían, no se validan ni reciben el foco (estado inicial sin parpadeo). `required` y las
 *   clases de obligatorio se quedan como las pinta PRO: el script las retira y las guarda en `data-digi-required` al
 *   arrancar. Encola `forms-conditions.js` (en el pie, `defer`): el núcleo sólo lee `assets` de los controles de
 *   primer nivel (`elementor/core/base/elements-iteration-actions/assets.php:126-131`), no de un campo del
 *   repetidor; un script encolado al pintar lo guarda la caché de elementos del documento
 *   (`elementor/core/base/document.php:1858-1874`).
 * - `elementor/element/parse_css` del formulario con algún campo condicional: `Hidden_Rule::add()` (frente A), la
 *   regla `[hidden]` en el CSS del documento (`.elementor-column{display:flex}` del núcleo ganaría a la hoja del
 *   navegador; C2-2).
 *
 * Un formulario sin campos condicionales pinta exactamente lo mismo que en 4.3.0.168.
 */
final class Render {
	const HANDLE = 'digitalisimo-f7b-forms-conditions';
	const SCRIPT = 'modules/digitalisimo-f7b/assets/js/forms-conditions.js';

	/** Controles de un campo, por la clave de atributos que usan `Form_Base` y los tipos de campo de PRO. */
	const CONTROL_KEYS = array( 'input', 'textarea', 'select' );

	/** @var object|null Formulario cuyo estado está en `$state` (se recalcula con el índice 0 o con otro formulario). */
	private static $form = null;

	/** @var array{hidden:array<string,bool>} */
	private static $state = array( 'hidden' => array() );

	/**
	 * `elementor_pro/forms/render/item`.
	 *
	 * @param mixed      $item       Campo que se va a pintar.
	 * @param int|string $item_index Índice del campo.
	 * @param object     $form       Widget del formulario.
	 * @return mixed
	 */
	public static function item( $item, $item_index, $form ) {
		if ( ! is_array( $item ) || ! is_object( $form ) || ! method_exists( $form, 'add_render_attribute' ) ) {
			return $item;
		}
		$def = Rules::from_item( $item );
		if ( null === $def ) {
			return $item;
		}
		if ( self::$form !== $form || 0 === (int) $item_index ) {
			self::$form  = $form;
			self::$state = self::state( $form );
		}
		$group = 'field-group' . $item_index;
		$form->add_render_attribute( $group, 'data-digi-cond', Rules::attribute( $def ) );
		if ( ! empty( self::$state['hidden'][ $def['i'] ] ) ) {
			$form->add_render_attribute( $group, 'hidden', 'hidden' );
			foreach ( self::control_keys( $item, $item_index ) as $key ) {
				$form->add_render_attribute( $key, 'disabled', 'disabled' );
			}
		}
		self::enqueue();
		return $item;
	}

	/**
	 * Claves de atributos de los controles del campo: `input<n>`, `textarea<n>`, `select<n>` y, en radios y casillas,
	 * la de cada opción (`<custom_id><índice>`, `Form_Base::make_radio_checkbox_field()`).
	 */
	public static function control_keys( array $item, $item_index ): array {
		$keys = array();
		foreach ( self::CONTROL_KEYS as $key ) {
			$keys[] = $key . $item_index;
		}
		$type = isset( $item['field_type'] ) ? (string) $item['field_type'] : '';
		if ( in_array( $type, array( 'radio', 'checkbox' ), true ) && isset( $item['custom_id'] ) && is_scalar( $item['custom_id'] ) ) {
			$options = isset( $item['field_options'] ) && is_string( $item['field_options'] ) ? preg_split( "/\\r\\n|\\r|\\n/", $item['field_options'] ) : array();
			foreach ( array_keys( (array) $options ) as $index ) {
				$keys[] = esc_attr( (string) $item['custom_id'] ) . $index;
			}
		}
		return $keys;
	}

	/**
	 * Campos ocultos al pintar: las reglas con los valores iniciales.
	 *
	 * @param object $form Widget del formulario.
	 */
	private static function state( $form ): array {
		$fields = method_exists( $form, 'get_settings_for_display' ) ? $form->get_settings_for_display( 'form_fields' ) : array();
		$hidden = array();
		foreach ( Rules::hidden( Rules::defs( $fields ), Rules::defaults( $fields ) ) as $id ) {
			$hidden[ $id ] = true;
		}
		return array( 'hidden' => $hidden );
	}

	/** Registra (si hace falta) y encola el script. */
	public static function enqueue(): void {
		if ( ! function_exists( 'wp_enqueue_script' ) || ! defined( 'DIGITALISIMO_ELEMENTS_FILE' ) ) {
			return;
		}
		self::register();
		wp_enqueue_script( self::HANDLE );
	}

	/**
	 * Sólo registra el script (`elementor/frontend/after_register_scripts`). Con la caché de elementos, una visita servida
	 * desde la caché del documento no vuelve a pintar el formulario: el núcleo encola los handles que guardó al pintarlo
	 * (`elementor/core/base/document.php`, `print_elements()`), y un handle sin registrar no se imprime (f7b.sh y
	 * front-budget.sh con CACHE=1).
	 */
	public static function register(): void {
		if ( ! function_exists( 'wp_register_script' ) || ! defined( 'DIGITALISIMO_ELEMENTS_FILE' ) ) {
			return;
		}
		if ( ! wp_script_is( self::HANDLE, 'registered' ) ) {
			wp_register_script(
				self::HANDLE,
				plugins_url( self::SCRIPT, DIGITALISIMO_ELEMENTS_FILE ),
				array(),
				defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : false,
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);
		}
	}

	/**
	 * `elementor/element/parse_css`: la regla `[hidden]` en el CSS del documento con un formulario condicional.
	 *
	 * @param object $post_css Archivo CSS del documento.
	 * @param object $element  Elemento.
	 */
	public static function parse_css( $post_css, $element ): void {
		if ( ! is_object( $element ) || ! method_exists( $element, 'get_name' ) || 'form' !== $element->get_name() || ! method_exists( $element, 'get_settings' ) ) {
			return;
		}
		$hidden_rule = '\Digitalisimo\Elements\F7b\Hidden_Rule';
		if ( ! class_exists( $hidden_rule ) || ! Rules::has_conditional( $element->get_settings( 'form_fields' ) ) ) {
			return;
		}
		$hidden_rule::add( $post_css );
	}

	/** Para las pruebas: olvida el formulario memorizado. */
	public static function reset(): void {
		self::$form  = null;
		self::$state = array( 'hidden' => array() );
	}
}
