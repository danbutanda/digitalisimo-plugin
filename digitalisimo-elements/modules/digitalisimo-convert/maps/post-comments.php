<?php
/**
 * Post Comments de PRO → Comentarios (`digitalisimo-post-comments`,
 * modules/digitalisimo-theme-elements/widgets/class-post-comments.php).
 *
 * Mismos controles con el mismo nombre (también la piel oculta `_skin`), así que el mapa es todo `keep`. Los dos pintan
 * la plantilla de comentarios del tema con `comments_template()`: el marcado es el del tema y el CSS personalizado del
 * elemento sólo cambia la clase del envoltorio. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

return array(
	'source'  => 'post-comments',
	'target'  => 'digitalisimo-post-comments',
	'status'  => 'draft',
	'panel'   => 'hide',
	'keep'    => array( '_skin', 'skin_temp', 'source_type', 'source_custom' ),
	'classes' => array( '.elementor-widget-post-comments' => '.elementor-widget-digitalisimo-post-comments' ),
);
