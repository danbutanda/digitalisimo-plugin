<?php
/**
 * Element Pack Pro 9.9.1 `bdt-advanced-divider` → `digitalisimo-advanced-divider`.
 * Element Pack dibuja sus formas con SVG de su propia carpeta, que desaparecen al retirarlo: cada
 * forma pasa a la equivalente propia y una imagen elegida de Medios se conserva.
 */
defined( 'ABSPATH' ) || exit;

$shapes = array(
	'line' => 'line', 'line-circle' => 'circle', 'line-cross' => 'cross', 'line-star' => 'star', 'line-dashed' => 'dashed', 'dashed' => 'dashed',
	'wave' => 'wave', 'ripple' => 'wave', 'melody' => 'wave', 'kiss-curl' => 'wave', 'furrow' => 'wave', 'cable' => 'wave',
	'floret' => 'star', 'floweret' => 'star', 'blossom' => 'star', 'heart' => 'circle', 'leaf' => 'circle', 'rectangle' => 'double',
);

return array(
	'target'   => 'digitalisimo-advanced-divider',
	'classes'  => array( '.bdt-ep-advanced-divider' => '.digi-advanced-divider' ),
	'defaults' => array(
		'advanced_divider_type'   => 'select',
		'advanced_divider_select' => 'line',
		'divider_align'           => 'center',
		'divider_line_align'      => 'center',
		'divider_gap_top'         => array( 'size' => 15 ),
		'divider_gap_bottom'      => array( 'size' => 15 ),
	),
	'rename'   => array( 'max_width' => 'divider_width' ),
	'filter'   => static function ( array $out, array $ep ) use ( $shapes ) {
		$image = $out['advanced_divider_choose'] ?? array();
		$own   = is_array( $image ) && ! empty( $image['url'] ) && false === strpos( (string) $image['url'], '/bdthemes-element-pack/' );
		if ( 'choose' === $out['advanced_divider_type'] && $own ) {
			$out['divider_type']  = 'image';
			$out['divider_image'] = $image;
		} else {
			$out['divider_type'] = $shapes[ $out['advanced_divider_select'] ] ?? 'line';
		}
		$line = in_array( $out['advanced_divider_select'], array( 'line', 'dashed', 'line-circle', 'line-cross', 'line-star', 'line-dashed' ), true );
		$out['divider_align'] = $line ? $out['divider_line_align'] : $out['divider_align'];
		unset( $out['advanced_divider_type'], $out['advanced_divider_select'], $out['advanced_divider_choose'], $out['divider_line_align'] );
		return $out;
	},
);
