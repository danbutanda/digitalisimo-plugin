<?php
/**
 * Site Title de PRO → Título del sitio (`digitalisimo-site-title`,
 * modules/digitalisimo-theme-elements/widgets/class-site-title.php).
 *
 * El Título del sitio hereda, como el de PRO, el Encabezado del núcleo: mismos controles con el mismo nombre (título y
 * enlace con las etiquetas dinámicas `site-title` y `site-url` por defecto), así que todo pasa con `keep`. El aviso de la
 * identidad del sitio no guarda valor. El envoltorio cambia `elementor-widget-theme-site-title` por `elementor-widget-digitalisimo-site-title` y conserva
 * `elementor-widget-heading`, como PRO. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_th = (array) require __DIR__ . '/_theme-heading.php';

return array(
	'source'   => 'theme-site-title',
	'target'   => 'digitalisimo-site-title',
	'status'   => 'draft',
	'panel'    => 'hide',
	'keep'     => $digi_th['keep'],
	'groups'   => $digi_th['groups'],
	'ui'       => array_merge( $digi_th['ui'], array( 'site_identity_notice' ) ),
	'classes'  => array( '.elementor-widget-theme-site-title' => '.elementor-widget-digitalisimo-site-title' ),
);
