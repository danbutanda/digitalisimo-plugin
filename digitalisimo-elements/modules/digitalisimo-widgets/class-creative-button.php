<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/class-advanced-button.php';

/** Botón creativo: alias oculto del Botón para el contenido guardado; sus efectos siguen disponibles allí. */
class Creative_Button_Widget extends Advanced_Button_Widget {
	use Widget_Alias;

	const ALIAS_ID       = 'digitalisimo-creative-button';
	const LEGACY_KEYS    = array( 'effect' => false, 'icon' => false, 'button_color' => false, 'text_color' => false, 'padding' => true );
	const LEGACY_CLASSES = array( 'prefix' => 'digi-creative-button', 'elements' => array( 'content' => false, 'text' => false, 'icon' => false, 'badge' => false ) );
	const EFFECT_MAP     = array( 'fill' => 'fill', 'outline' => 'outline', 'lift' => 'g', 'underline' => 'underline', 'glow' => 'glow' );

	public static function upgrade_settings( array $s ) {
		if ( ! isset( $s['button_effect'] ) && '' !== (string) ( $s['effect'] ?? '' ) ) {
			$s['button_effect'] = self::EFFECT_MAP[ (string) $s['effect'] ] ?? 'fill';
		}
		unset( $s['effect'] );
		foreach ( array( 'icon' => 'button_icon', 'button_color' => 'button_background_color', 'text_color' => 'advanced_button_text_color', 'padding' => 'advanced_button_padding' ) as $from => $to ) {
			Settings_Upgrade::rename( $s, $from, $to );
		}
		// El aspecto que tenía el botón creativo por defecto: relleno, borde y radio de su hoja de estilos.
		Settings_Upgrade::fill( $s, array(
			'button_effect'           => 'fill',
			'icon_align'              => 'left',
			'button_size'             => 'md',
			'advanced_button_padding' => array( 'top' => '0.8', 'right' => '1.3', 'bottom' => '0.8', 'left' => '1.3', 'unit' => 'em', 'isLinked' => false ),
			'button_border_width'     => array( 'top' => '2', 'right' => '2', 'bottom' => '2', 'left' => '2', 'unit' => 'px', 'isLinked' => true ),
			'advanced_button_radius'  => array( 'size' => 6, 'unit' => 'px' ),
		) );
		return $s;
	}
}
