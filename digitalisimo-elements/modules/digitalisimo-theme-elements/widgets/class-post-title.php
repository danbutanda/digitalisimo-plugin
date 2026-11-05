<?php
namespace Digitalisimo\Elements\Theme_Elements\Widgets;

defined( 'ABSPATH' ) || exit;

/**
 * Título de la entrada (`digitalisimo-post-title`): sustituye al widget «Post Title» de PRO
 * (`modules/theme-builder/widgets/post-title.php`, mapa `modules/digitalisimo-convert/maps/theme-post-title.php`). Los
 * controles del Encabezado del núcleo, con el título por defecto de la etiqueta dinámica `post-title`. Repite también
 * `get_common_args()` de PRO (clase `entry-title`), que el núcleo actual ya no lee: el resultado es el mismo que en PRO.
 * Ver `Title_Base`.
 */
class Post_Title extends Title_Base {

	protected function get_dynamic_tag_name() {
		return 'post-title';
	}

	public function get_name() {
		return 'digitalisimo-post-title';
	}

	public function get_title() {
		return esc_html__( 'Título de la entrada', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-post-title';
	}

	public function get_categories() {
		return array( 'theme-elements-single' );
	}

	public function get_keywords() {
		return array( 'título', 'entrada', 'encabezado', 'title', 'heading', 'post' );
	}

	public function get_common_args() {
		return array(
			'_css_classes' => array(
				'default' => 'entry-title',
			),
		);
	}
}
