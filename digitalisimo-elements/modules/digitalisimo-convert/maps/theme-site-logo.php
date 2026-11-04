<?php
/**
 * Site Logo de PRO → Logotipo del sitio (`digitalisimo-site-logo`,
 * modules/digitalisimo-theme-elements/widgets/class-site-logo.php).
 *
 * El Logotipo del sitio hereda, como el de PRO, la Imagen del núcleo con los mismos cambios (logotipo del
 * Personalizador con la etiqueta dinámica `site-logo`, tamaño `full`, enlace `site_url` por defecto, leyenda sólo del
 * adjunto), así que todo pasa con `keep`. El botón «Cambiar el logotipo» no guarda valor. El envoltorio cambia
 * `elementor-widget-theme-site-logo` por `elementor-widget-digitalisimo-site-logo` y conserva
 * `elementor-widget-image`, como PRO. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_ti = (array) require __DIR__ . '/_theme-image.php';

return array(
	'source'   => 'theme-site-logo',
	'target'   => 'digitalisimo-site-logo',
	'status'   => 'ready',
	'panel'    => 'hide',
	// Valores por defecto que PRO cambia (Elementor no guarda lo que coincide con ellos).
	'defaults' => array( 'image_size' => 'full', 'link_to' => 'site_url' ),
	'keep'     => $digi_ti['keep'],
	'groups'   => $digi_ti['groups'],
	'ui'       => array_merge( $digi_ti['ui'], array( 'change_logo_cta' ) ),
	'classes'  => array( '.elementor-widget-theme-site-logo' => '.elementor-widget-digitalisimo-site-logo' ),
);
