<?php
/**
 * Post Title de PRO → Título de la entrada (`digitalisimo-post-title`,
 * modules/digitalisimo-theme-elements/widgets/class-post-title.php).
 *
 * El Título de la entrada hereda, como el de PRO (`Title_Widget_Base`), el Encabezado del núcleo: mismos controles con el
 * mismo nombre (título con la etiqueta dinámica `post-title` por defecto, nivel `h1`), así que todo pasa con `keep`. El envoltorio cambia `elementor-widget-theme-post-title` por `elementor-widget-digitalisimo-post-title` y conserva
 * `elementor-widget-heading` y `elementor-page-title`, como PRO. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_th = (array) require __DIR__ . '/_theme-heading.php';

return array(
	'source'   => 'theme-post-title',
	'target'   => 'digitalisimo-post-title',
	'status'   => 'draft',
	'panel'    => 'hide',
	// Valor por defecto que PRO cambia (Elementor no guarda lo que coincide con él).
	'defaults' => array( 'header_size' => 'h1' ),
	'keep'     => $digi_th['keep'],
	'groups'   => $digi_th['groups'],
	'ui'       => $digi_th['ui'],
	'classes'  => array( '.elementor-widget-theme-post-title' => '.elementor-widget-digitalisimo-post-title' ),
);
