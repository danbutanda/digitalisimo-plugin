<?php
namespace Digitalisimo\Elements\Theme_Elements\Widgets;

use Digitalisimo\Elements\Theme_Elements\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Imagen destacada (`digitalisimo-featured-image`): sustituye al widget «Featured Image» de PRO
 * (`modules/theme-builder/widgets/post-featured-image.php`, mapa
 * `modules/digitalisimo-convert/maps/theme-post-featured-image.php`). Como PRO, hereda la Imagen del núcleo
 * (`\Elementor\Widget_Image`: mismos controles, marcado y hoja `widget-image`, imagen por `wp_get_attachment_image()` con
 * `srcset`, `loading` y `decoding`) y repite lo que PRO cambia: la imagen por defecto es la etiqueta dinámica
 * `post-featured-image` (la imagen destacada del contenido consultado) y el envoltorio lleva `elementor-widget-image`.
 *
 * Propio: la hoja sólo añade el foco visible del enlace y quita la transición del efecto al pasar el cursor con
 * `prefers-reduced-motion`.
 */
class Featured_Image extends \Elementor\Widget_Image {

	public function get_name() {
		return 'digitalisimo-featured-image';
	}

	public function get_title() {
		return esc_html__( 'Imagen destacada', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-featured-image';
	}

	public function get_categories() {
		return array( 'theme-elements-single' );
	}

	public function get_keywords() {
		return array( 'imagen', 'destacada', 'miniatura', 'image', 'featured', 'thumbnail' );
	}

	public function get_style_depends(): array {
		return array_values( array_unique( array_merge( parent::get_style_depends(), Module::style_depends( $this->get_name() ) ) ) );
	}

	protected function register_controls() {
		parent::register_controls();

		$this->update_control(
			'image',
			array(
				'dynamic' => array(
					'default' => \Elementor\Plugin::$instance->dynamic_tags->tag_data_to_tag_text( null, 'post-featured-image' ),
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

	/** Como PRO: la de la Imagen del núcleo y, si no da nada, la imagen destacada con el título del contenido. */
	public function render_markdown(): string {
		$markdown = parent::render_markdown();
		if ( '' !== $markdown ) {
			return $markdown;
		}
		$url = esc_url( (string) get_the_post_thumbnail_url() );
		if ( '' === $url ) {
			return '';
		}
		$alt = trim( (string) get_the_title() );
		return '![' . ( '' !== $alt ? $alt : 'image' ) . '](' . $url . ')';
	}
}
