<?php
/**
 * Archive Title de PRO → Título del archivo (`digitalisimo-archive-title`,
 * modules/digitalisimo-theme-elements/widgets/class-archive-title.php).
 *
 * El Título del archivo hereda, como el de PRO (`Title_Widget_Base`), el Encabezado del núcleo: mismos controles con el
 * mismo nombre (título con la etiqueta dinámica `archive-title` por defecto, nivel `h1`), así que todo pasa con `keep`. El envoltorio cambia `elementor-widget-theme-archive-title` por `elementor-widget-digitalisimo-archive-title` y conserva
 * `elementor-widget-heading` y `elementor-page-title`, como PRO. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_th = (array) require __DIR__ . '/_theme-heading.php';

return array(
	'source'   => 'theme-archive-title',
	'target'   => 'digitalisimo-archive-title',
	'status'   => 'draft',
	'panel'    => 'hide',
	// Valor por defecto que PRO cambia (Elementor no guarda lo que coincide con él).
	'defaults' => array( 'header_size' => 'h1' ),
	'keep'     => $digi_th['keep'],
	'groups'   => $digi_th['groups'],
	'ui'       => $digi_th['ui'],
	'classes'  => array( '.elementor-widget-theme-archive-title' => '.elementor-widget-digitalisimo-archive-title' ),
);
