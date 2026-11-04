<?php
/**
 * Posts de PRO → Entradas (`digitalisimo-posts`, modules/digitalisimo-loop/widgets/class-posts.php).
 *
 * Entradas repite los controles de PRO (los de `Posts_Base`, los de sus tres pieles —prefijados `classic_`, `cards_`,
 * `full_content_` como en PRO—, el grupo de consulta `posts_*` y los de categorías y etiquetas que Elements añade a la
 * piel clásica, `digitalisimo_*`, que pasan sin cambios) con el mismo nombre y los mismos valores, así que el mapa es
 * `keep` y `groups`. `_skin` pasa tal cual (clásica por defecto, como en PRO). Las clases de presentación del
 * envoltorio (`elementor-posts--thumbnail-*`, `--align-*`, `elementor-card-shadow-yes`, `elementor-posts__hover-*`) son
 * ahora `digi-posts--*`, en el mismo nodo; la rejilla sigue siendo la del núcleo (`elementor-grid-*`).
 *
 * Sin aproximaciones de valores: las diferencias (botón «Cargar más» como `<button>`, nombres accesibles, objetivos de
 * 24 px en los números) son de marcado y están en el informe del frente I. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_posts = ( require __DIR__ . '/_posts.php' )( array( 'classic', 'cards', 'full_content' ) );

return array(
	'source'   => 'posts',
	'target'   => 'digitalisimo-posts',
	'status'   => 'ready',
	'panel'    => 'hide',
	'defaults' => $digi_posts['defaults'],
	'keep'     => array_merge( $digi_posts['keep'], array( 'archive_query_note' ) ),
	// Grupo de consulta del constructor (mismo prefijo `posts_`), tamaños de imagen, tipografías, sombras y filtros.
	'groups'   => array( 'posts' => 'posts' ) + $digi_posts['groups'],
	'drop'     => $digi_posts['drop'],
	'classes'  => array( '.elementor-widget-posts' => '.elementor-widget-digitalisimo-posts' ) + $digi_posts['classes'],
);
