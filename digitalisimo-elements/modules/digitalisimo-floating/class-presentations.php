<?php
namespace Digitalisimo\Elements\Floating;

use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Lo común de los tres widgets propios de C8 (Botones de contacto, Barra flotante y Link in Bio): el control
 * `presentation` (el número de la variante de PRO) y la unión de los controles de sus presentaciones
 * (`Variants`), con los selectores pasados a las clases y variables propias de la familia.
 *
 * El widget que lo usa declara `PROBE` (clase de su sonda), `RENAME` (`clase o variable del núcleo => propia`, en el
 * orden en que se sustituyen), `presentation_titles()` y `texts()` (textos por defecto en español de los controles que
 * los traen en inglés de la base del núcleo; el mapa de cada variante completa los de PRO al convertir).
 */
trait Presentations {
	/** Presentación de un ajuste: una de las de la sonda, o la primera. */
	public static function presentation_of( array $settings ): string {
		$probe = static::PROBE;
		$value = (string) ( $settings['presentation'] ?? '' );
		return in_array( $value, $probe::PRESENTATIONS, true ) ? $value : $probe::PRESENTATIONS[0];
	}

	protected function register_controls(): void {
		$probe = static::PROBE;
		$this->start_controls_section(
			'section_presentation',
			array(
				'label' => esc_html__( 'Presentación', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);
		$this->add_control(
			'presentation',
			array(
				'label'   => esc_html__( 'Presentación', 'pro-elements' ),
				'type'    => Controls_Manager::SELECT,
				'default' => $probe::PRESENTATIONS[0],
				'options' => static::presentation_titles(),
			)
		);
		$this->end_controls_section();
		$merged   = Variants::merge( Variants::collect( $probe, $probe::PRESENTATIONS ) );
		$controls = static::rewrite( $merged['controls'], static::RENAME );
		foreach ( static::texts() as $id => $text ) {
			if ( isset( $controls[ $id ] ) ) {
				$controls[ $id ]['default'] = $text;
			}
		}
		Variants::import( $this, $controls );
	}

	/**
	 * Pasa las clases y variables del núcleo a las propias en los selectores de los controles (`selectors`, `selector` y
	 * los de los campos de un repetidor).
	 *
	 * @param array<string, array>  $controls Controles.
	 * @param array<string, string> $rename   Sustituciones, en orden.
	 */
	public static function rewrite( array $controls, array $rename ): array {
		$from = array_keys( $rename );
		$to   = array_values( $rename );
		foreach ( $controls as $id => $control ) {
			$controls[ $id ] = self::rewrite_control( $control, $from, $to );
		}
		return $controls;
	}

	private static function rewrite_control( array $control, array $from, array $to ): array {
		if ( isset( $control['selector'] ) && is_string( $control['selector'] ) ) {
			$control['selector'] = str_replace( $from, $to, $control['selector'] );
		}
		if ( isset( $control['selectors'] ) && is_array( $control['selectors'] ) ) {
			$selectors = array();
			foreach ( $control['selectors'] as $selector => $value ) {
				$selectors[ str_replace( $from, $to, (string) $selector ) ] = is_string( $value ) ? str_replace( $from, $to, $value ) : $value;
			}
			$control['selectors'] = $selectors;
		}
		if ( isset( $control['fields'] ) && is_array( $control['fields'] ) ) {
			foreach ( $control['fields'] as $key => $field ) {
				$control['fields'][ $key ] = is_array( $field ) ? self::rewrite_control( $field, $from, $to ) : $field;
			}
		}
		return $control;
	}
}
