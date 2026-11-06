<?php
namespace Digitalisimo\Elements\Woo\Store;

use Digitalisimo\Elements\Woo\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Imagen de la categoría (`digitalisimo-woo-category-image`): sustituye a «Category Image» de PRO
 * (`modules/woocommerce/widgets/category-image.php`, GPL; mapa `maps/woocommerce-category-image.php`). Como PRO, hereda la
 * Imagen del núcleo (`\Elementor\Widget_Image`: mismos controles, marcado y hoja `widget-image`, imagen por WordPress con
 * `srcset`, `loading` y `decoding`) y repite lo que PRO cambia: la imagen por defecto es la etiqueta dinámica de la imagen
 * de la categoría de WooCommerce (`woocommerce-category-image-tag`) y el envoltorio lleva `elementor-widget-image`.
 *
 * No puede heredar `Store\Base`: sin WooCommerce, `Inert` lo registra en el editor con `Inert_Widget`, que conserva sus
 * ajustes. La hoja propia sólo añade el foco visible del enlace.
 */
class Category_Image extends \Elementor\Widget_Image {

	public function get_name() {
		return 'digitalisimo-woo-category-image';
	}

	public function get_title() {
		return esc_html__( 'Imagen de la categoría', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-featured-image';
	}

	public function get_categories() {
		return array( 'woocommerce-elements', 'woocommerce-elements-single' );
	}

	public function get_keywords() {
		return array( 'woocommerce', 'categoría', 'imagen', 'miniatura', 'category', 'image', 'thumbnail' );
	}

	public function has_widget_inner_wrapper(): bool {
		return ! Base::optimized_markup();
	}

	public function get_style_depends(): array {
		return array_values( array_unique( array_merge( parent::get_style_depends(), Registry::style_depends( $this->get_name() ) ) ) );
	}

	protected function register_controls() {
		parent::register_controls();

		$this->update_control(
			'image',
			array(
				'dynamic' => array(
					'default' => \Elementor\Plugin::$instance->dynamic_tags->tag_data_to_tag_text( null, 'woocommerce-category-image-tag' ),
				),
			),
			array(
				'recursive' => true,
			)
		);
	}

	protected function get_html_wrapper_class() {
		return parent::get_html_wrapper_class() . ' elementor-widget-image';
	}

	public function get_group_name() {
		return 'woocommerce';
	}

	/** Como PRO: la de la Imagen del núcleo y, si no da nada, la imagen de la categoría consultada. */
	public function render_markdown(): string {
		$markdown = parent::render_markdown();
		if ( '' !== $markdown ) {
			return $markdown;
		}
		$term = get_queried_object();
		if ( ! $term || ! isset( $term->term_id ) ) {
			return '';
		}
		$thumbnail_id = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
		$url          = $thumbnail_id ? esc_url( (string) wp_get_attachment_image_url( $thumbnail_id, 'full' ) ) : '';
		if ( '' === $url ) {
			return '';
		}
		$alt = trim( (string) get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) );
		if ( '' === $alt ) {
			$attachment = get_post( $thumbnail_id );
			$alt        = $attachment ? trim( (string) $attachment->post_title ) : '';
		}
		return '![' . ( '' !== $alt ? $alt : 'image' ) . '](' . $url . ')';
	}
}
