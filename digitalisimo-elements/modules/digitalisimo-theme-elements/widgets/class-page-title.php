<?php
namespace Digitalisimo\Elements\Theme_Elements\Widgets;

defined( 'ABSPATH' ) || exit;

/**
 * Título de la página (`digitalisimo-page-title`): sustituye al widget «Page Title» de PRO
 * (`modules/theme-builder/widgets/page-title.php`, mapa `modules/digitalisimo-convert/maps/theme-page-title.php`). Los
 * controles del Encabezado del núcleo, con el título por defecto de la etiqueta dinámica `page-title` (el título del
 * contenido o del archivo consultado, como en PRO). Ver `Title_Base`.
 */
class Page_Title extends Title_Base {

	protected function get_dynamic_tag_name() {
		return 'page-title';
	}

	public function get_name() {
		return 'digitalisimo-page-title';
	}

	public function get_title() {
		return esc_html__( 'Título de la página', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-archive-title';
	}

	public function get_categories() {
		return array( 'theme-elements' );
	}

	public function get_keywords() {
		return array( 'título', 'página', 'encabezado', 'title', 'heading', 'page' );
	}
}
