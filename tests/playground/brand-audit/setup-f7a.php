<?php
/**
 * Segundo paso del blueprint de brand-audit.sh (F7a), en otra petición con DIGITALÍSIMO Elements ya cargado: siembra
 * el preset `marca-titulo` (encabezado) con `Store::replace()` dentro de los controles de estilo, como un kit, para que
 * la sección «Preset de estilo», el menú contextual y la pestaña «Presets de estilo» de Ajustes del sitio tengan datos.
 */
require '/wordpress/wp-load.php';
$digi_dir = '/wordpress/wp-content/probe';
$store    = '\\Digitalisimo\\Elements\\Design\\Presets\\Store';
$css      = '\\Digitalisimo\\Elements\\Design\\Css';
if ( ! class_exists( $store ) || ! class_exists( $css ) ) {
	file_put_contents( $digi_dir . '/setup-error.txt', "F7a: Presets\\Store no está cargado en el segundo paso\n", FILE_APPEND );
	return;
}
$css::with_style_controls(
	static function () use ( $store ) {
		$store::replace(
			array(
				'v'       => 1,
				'presets' => array(
					'marca-titulo' => array(
						'type'     => 'heading',
						'label'    => 'Título de marca',
						'settings' => array(
							'title_color'           => '#245EFF',
							'typography_typography' => 'custom',
							'typography_font_size'  => array( 'unit' => 'px', 'size' => 34, 'sizes' => array() ),
						),
					),
				),
			)
		);
	}
);
if ( ! $store::get( 'marca-titulo' ) ) {
	file_put_contents( $digi_dir . '/setup-error.txt', 'F7a: Store::replace() no guardó el preset marca-titulo: ' . wp_json_encode( $store::dropped() ) . "\n", FILE_APPEND );
}
