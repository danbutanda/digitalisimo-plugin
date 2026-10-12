<?php
/**
 * Element Pack Pro 9.9.1 `bdt-creative-button` → `digitalisimo-creative-button`.
 * Las animaciones de Element Pack pasan al efecto propio más parecido.
 */
defined( 'ABSPATH' ) || exit;

$effects = array(
	'anthe' => 'fill', 'atlas' => 'fill', 'bestia' => 'fill', 'calypso' => 'fill', 'dione' => 'fill', 'greip' => 'fill', 'janus' => 'fill', 'pandora' => 'fill', 'pallene' => 'fill', 'rhea' => 'fill', 'reveal' => 'fill', 'gooey' => 'fill',
	'hati' => 'outline', 'helene' => 'outline', 'kari' => 'outline', 'fenrir' => 'outline', 'surtur' => 'outline', 'elon' => 'outline',
	'hyperion' => 'lift', 'mimas' => 'lift', 'narvi' => 'lift', 'pan' => 'lift', 'skoll' => 'lift', 'reklo' => 'lift',
	'telesto' => 'underline',
	'aura' => 'glow', 'glitch' => 'glow',
);

return array(
	'target'   => 'digitalisimo-creative-button',
	'classes'  => array( '.bdt-ep-creative-button' => '.digi-creative-button' ),
	'strip'    => array( 'text' ),
	'defaults' => array( 'button_style' => 'anthe', 'text' => 'Read More', 'link' => array( 'url' => '#' ), 'alignment' => '' ),
	'rename'   => array( 'button_style' => 'effect', 'alignment' => 'align' ),
	'values'   => array( 'effect' => $effects ),
	'drop'     => array( 'shape_alignment', 'gooey_direction', 'reveal_direction', 'creative_button_aura_line_toggle', 'hover_animation' ),
);
