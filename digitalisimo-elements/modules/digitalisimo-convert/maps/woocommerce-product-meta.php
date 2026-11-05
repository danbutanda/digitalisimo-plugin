<?php
/**
 * Product Meta de PRO → Metadatos del producto (`digitalisimo-woo-product-meta`,
 * modules/digitalisimo-woo/single/class-product-meta.php).
 *
 * Mismos controles con el mismo nombre, valor por defecto y selectores: todo `keep` y `groups`. La vista cambia la clase
 * de PRO por la propia. Los rótulos que PRO pintaba en el idioma del sitio sin guardarlos («Category», «Tags», «SKU»,
 * «N/A»…) se completan en lo convertido, porque el widget propio tiene los suyos en español. Formato: cabecera de
 * `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_pm_t = static function ( $text ) {
	// Los textos por defecto que PRO pintaba en el idioma del sitio (no se guardan: el control no tiene valor).
	return function_exists( '__' ) ? __( $text, 'elementor-pro' ) : $text; // phpcs:ignore WordPress.WP.I18n -- mismo texto que PRO.
};

return array(
	'source'  => 'woocommerce-product-meta',
	'target'  => 'digitalisimo-woo-product-meta',
	'status'  => 'draft',
	'panel'   => 'hide',
	'keep'    => array( 'view', 'space_between', 'divider', 'divider_style', 'divider_weight', 'divider_width', 'divider_height', 'divider_color', 'text_color', 'link_color', 'category_caption_single', 'category_caption_plural', 'tag_caption_single', 'tag_caption_plural', 'sku_caption', 'sku_missing_caption' ),
	'groups'  => array(
		'text_typography' => 'text_typography',
		'link_typography' => 'link_typography',
	),
	'ui'      => array( 'wc_style_warning', 'heading_text_style', 'heading_link_style', 'heading_category_caption', 'heading_tag_caption', 'heading_sku_caption' ),
	'filter'  => static function ( array $out ) use ( $digi_pm_t ) {
		$texts = array(
			'category_caption_single' => 'Category',
			'category_caption_plural' => 'Categories',
			'tag_caption_single'      => 'Tag',
			'tag_caption_plural'      => 'Tags',
			'sku_caption'             => 'SKU',
			'sku_missing_caption'     => 'N/A',
		);
		foreach ( $texts as $key => $text ) {
			if ( ( ! isset( $out[ $key ] ) || '' === $out[ $key ] ) && empty( $out['__dynamic__'][ $key ] ) ) {
				$out[ $key ] = $digi_pm_t( $text );
			}
		}
		return $out;
	},
	'classes' => array(
		'.elementor-widget-woocommerce-product-meta' => '.elementor-widget-digitalisimo-woo-product-meta',
		'.elementor-woo-meta--view-inline'           => '.digi-product-meta--view-inline',
		'.elementor-woo-meta--view-table'            => '.digi-product-meta--view-table',
		'.elementor-woo-meta--view-stacked'          => '.digi-product-meta--view-stacked',
	),
);
