<?php
/**
 * Archive Posts de PRO → Entradas del archivo (`digitalisimo-archive-posts`,
 * modules/digitalisimo-loop/widgets/class-archive-posts.php).
 *
 * Mismos controles que PRO (los de `Posts_Base`, los de sus pieles `archive_classic_`, `archive_cards_` y
 * `archive_full_content_`, y el mensaje «Nada encontrado») con el mismo nombre y valores: `keep` y `groups`, como
 * Entradas (`maps/_posts.php`). La paginación sigue con «Números» por defecto, como en PRO. Formato: cabecera de
 * `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_archive = ( require __DIR__ . '/_posts.php' )( array( 'archive_classic', 'archive_cards', 'archive_full_content' ) );
$digi_archive_t = static function ( $text ) {
	return function_exists( 'esc_html__' ) ? esc_html__( $text, 'elementor-pro' ) : $text; // phpcs:ignore WordPress.WP.I18n -- mismo texto que PRO.
};

return array(
	'source'   => 'archive-posts',
	'target'   => 'digitalisimo-archive-posts',
	'status'   => 'ready',
	'panel'    => 'hide',
	'defaults' => $digi_archive['defaults'] + array(
		'pagination_type'       => 'numbers',
		'nothing_found_message' => $digi_archive_t( 'It seems we can’t find what you’re looking for.' ),
	),
	'keep'     => array_merge( $digi_archive['keep'], array( 'nothing_found_message', 'nothing_found_color' ) ),
	'groups'   => array( 'nothing_found_typography' => 'nothing_found_typography' ) + $digi_archive['groups'],
	'drop'     => $digi_archive['drop'],
	'classes'  => array( '.elementor-widget-archive-posts' => '.elementor-widget-digitalisimo-archive-posts' ) + $digi_archive['classes'],
);
