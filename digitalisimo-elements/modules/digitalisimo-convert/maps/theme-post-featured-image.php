<?php
/**
 * Featured Image de PRO → Imagen destacada (`digitalisimo-featured-image`,
 * modules/digitalisimo-theme-elements/widgets/class-featured-image.php).
 *
 * La Imagen destacada hereda, como la de PRO, la Imagen del núcleo (imagen con la etiqueta dinámica
 * `post-featured-image` por defecto), así que todo pasa con `keep`. El envoltorio cambia
 * `elementor-widget-theme-post-featured-image` por `elementor-widget-digitalisimo-featured-image` y conserva
 * `elementor-widget-image`, como PRO. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_ti = (array) require __DIR__ . '/_theme-image.php';

return array(
	'source'   => 'theme-post-featured-image',
	'target'   => 'digitalisimo-featured-image',
	'status'   => 'draft',
	'panel'    => 'hide',
	'keep'     => $digi_ti['keep'],
	'groups'   => $digi_ti['groups'],
	'ui'       => $digi_ti['ui'],
	'classes'  => array( '.elementor-widget-theme-post-featured-image' => '.elementor-widget-digitalisimo-featured-image' ),
);
