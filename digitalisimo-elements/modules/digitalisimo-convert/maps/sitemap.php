<?php
/**
 * Sitemap de PRO → Mapa del sitio (`digitalisimo-sitemap`, modules/digitalisimo-theme-elements/widgets/class-sitemap.php).
 *
 * El widget propio repite los controles del de PRO (también los campos del repetidor) con el mismo nombre, valores por
 * defecto, opciones y condiciones, así que el mapa es `keep` y `groups`. Mismas consultas que PRO. Las clases que PRO
 * armaba con el tipo de contenido o la taxonomía (`.elementor-sitemap-item-<tipo>`, `-<tipo>-list`, `-<tipo>-title`) se
 * traducen para cualquier tipo, también los de un plugin (`class_patterns`). «Excluir → Entrada actual» no se aplicaba
 * en PRO (su consulta no la leía): lo convertido llega sin ella, para listar lo mismo. Formato: cabecera de
 * `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_sm_t = static function ( $text ) {
	return function_exists( 'esc_html__' ) ? esc_html__( $text, 'elementor-pro' ) : $text; // phpcs:ignore WordPress.WP.I18n -- mismo texto que PRO.
};

$digi_sm_classes = array(
	'.elementor-widget-sitemap'  => '.elementor-widget-digitalisimo-sitemap',
	'.elementor-sitemap-wrap'    => '.digi-sitemap',
	'.elementor-sitemap-section' => '.digi-sitemap__section',
	'.elementor-sitemap-title'   => '.digi-sitemap__title',
	'.elementor-sitemap-list'    => '.digi-sitemap__list',
	'.elementor-sitemap-item'    => '.digi-sitemap__item',
);
// Las clases con el tipo de contenido o la taxonomía, para cualquier tipo (el grupo exige al menos un carácter, así que
// no tocan `.elementor-sitemap-list` ni `-title`, que traduce `classes`).
$digi_sm_patterns = array(
	'/\.elementor-sitemap-item-([a-z0-9_-]+)(?![\w-])/' => '.digi-sitemap__item--$1',
	'/\.elementor-sitemap-([a-z0-9_-]+)-list(?![\w-])/' => '.digi-sitemap__list--$1',
	'/\.elementor-sitemap-([a-z0-9_-]+)-title(?![\w-])/' => '.digi-sitemap__title--$1',
);

return array(
	'source'    => 'sitemap',
	'target'    => 'digitalisimo-sitemap',
	'status'    => 'ready',
	'panel'     => 'hide',
	'keep'      => array(
		'sitemap_columns',
		'sitemap_title_tag',
		'sitemap_add_nofollow',
		'sitemap_exclude',
		'sitemap_exclude_ids',
		'sitemap_password_protected',
		'sitemap_list_indent',
		'sitemap_section_padding',
		'sitemap_title_color',
		'sitemap_list_item_color',
		'sitemap_bullet_color',
		'sitemap_list_item_bullet_style',
	),
	'groups'    => array(
		'sitemap_title_typography'     => 'sitemap_title_typography',
		'sitemap_list_item_typography' => 'sitemap_list_item_typography',
	),
	// «Entrada actual»: PRO la ofrecía pero no la aplicaba; el widget propio sí, así que lo convertido llega sin ella.
	'values'    => array(
		'sitemap_exclude' => static function ( $value ) {
			return is_array( $value ) ? array_values( array_diff( $value, array( 'current_post' ) ) ) : $value;
		},
	),
	'repeaters' => array(
		'sitemap_items' => array(
			'target'       => 'sitemap_items',
			'defaults'     => array(
				'sitemap_type_selector'     => 'post_type',
				'sitemap_source_post_type'  => 'page',
				'sitemap_source_taxonomy'   => 'category',
				'sitemap_orderby_post_type' => 'post_date',
				'sitemap_orderby_taxonomy'  => 'name',
				'sitemap_order'             => 'desc',
				'sitemap_hide_empty'        => 'yes',
				'sitemap_hierarchical'      => 'no',
				'sitemap_depth'             => '0',
			),
			'default_rows' => array(
				array(
					'sitemap_type_selector'    => 'post_type',
					'sitemap_title'            => $digi_sm_t( 'Pages' ),
					'sitemap_source_post_type' => 'page',
				),
				array(
					'sitemap_type_selector'   => 'taxonomy',
					'sitemap_title'           => $digi_sm_t( 'Categories' ),
					'sitemap_source_taxonomy' => 'category',
				),
			),
			'keep'         => array( 'sitemap_type_selector', 'sitemap_source_post_type', 'sitemap_source_taxonomy', 'sitemap_title', 'sitemap_orderby_post_type', 'sitemap_orderby_taxonomy', 'sitemap_order', 'sitemap_hide_empty', 'sitemap_hierarchical', 'sitemap_depth' ),
		),
	),
	// Separador y encabezados del panel: no guardan valor.
	'ui'        => array( 'sitemap_layout_divider', 'sitemap_title_style', 'sitemap_list_style', 'sitemap_bullet_style' ),
	'classes'        => $digi_sm_classes,
	'class_patterns' => $digi_sm_patterns,
);
