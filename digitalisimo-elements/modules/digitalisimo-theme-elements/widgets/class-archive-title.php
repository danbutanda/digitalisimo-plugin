<?php
namespace Digitalisimo\Elements\Theme_Elements\Widgets;

defined( 'ABSPATH' ) || exit;

/**
 * Título del archivo (`digitalisimo-archive-title`): sustituye al widget «Archive Title» de PRO
 * (`modules/theme-builder/widgets/archive-title.php`, mapa `modules/digitalisimo-convert/maps/theme-archive-title.php`).
 * Los controles del Encabezado del núcleo, con el título por defecto de la etiqueta dinámica `archive-title`
 * (`get_the_archive_title()` con sus filtros, como en PRO). Ver `Title_Base`.
 */
class Archive_Title extends Title_Base {

	protected function get_dynamic_tag_name() {
		return 'archive-title';
	}

	public function get_name() {
		return 'digitalisimo-archive-title';
	}

	public function get_title() {
		return esc_html__( 'Título del archivo', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-archive-title';
	}

	public function get_categories() {
		return array( 'theme-elements-archive' );
	}

	public function get_keywords() {
		return array( 'título', 'archivo', 'categoría', 'encabezado', 'title', 'heading', 'archive' );
	}
}
