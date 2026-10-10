<?php
/** Element Pack Pro 9.9.1 `bdt-document-viewer` → `digitalisimo-document-viewer`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-document-viewer',
	'classes'   => array( '.bdt-document-viewer' => '.digi-document-viewer' ),
	'defaults'  => array(
		'document_height' => array(
			'size' => 800,
		),
		'viewer_type' => 'google_docs',
	),
	// El visor propio enlaza además el documento para abrirlo fuera del marco.
	'set'       => array( 'frame_title' => '' ),
);
