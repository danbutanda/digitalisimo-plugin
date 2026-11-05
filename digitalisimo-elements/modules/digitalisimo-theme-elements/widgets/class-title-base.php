<?php
namespace Digitalisimo\Elements\Theme_Elements\Widgets;

use Digitalisimo\Elements\Theme_Elements\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Base de los títulos de tema propios (Título de la página, de la entrada y del archivo). Sustituye a `Title_Widget_Base`
 * de PRO (`modules/theme-builder/widgets/title-widget-base.php`) y, como él, hereda el Encabezado del núcleo
 * (`\Elementor\Widget_Heading`): mismos controles, marcado y hoja (`widget-heading`), con lo que PRO cambia repetido
 * igual: el título toma por defecto la etiqueta dinámica del tipo (`get_dynamic_tag_name()`), el nivel por defecto es
 * `h1`, el envoltorio lleva `elementor-page-title` y `elementor-widget-heading`, y no se pinta nada si el contenido
 * consultado tiene «Ocultar título» (`hide_title`).
 *
 * El dato sale de las mismas etiquetas dinámicas que usa PRO (son funciones del constructor, no widgets), así que es el
 * mismo en el editor y en el front. Un encabezado nunca es `<title>` ni lleva señales SEO.
 *
 * Propio: la hoja del widget sólo añade el foco visible del enlace (contorno de 2 px con separación de 2 px).
 */
abstract class Title_Base extends \Elementor\Widget_Heading {

	/** Etiqueta dinámica del título (`post-title`, `page-title`, `archive-title`). */
	abstract protected function get_dynamic_tag_name();

	public function get_style_depends(): array {
		return array_values( array_unique( array_merge( parent::get_style_depends(), Module::style_depends( $this->get_name() ) ) ) );
	}

	/** Como PRO: nada si el documento del contenido consultado oculta su título. */
	protected function should_show_page_title() {
		$document = \Elementor\Plugin::$instance->documents->get( get_the_ID() );
		return ! ( $document && 'yes' === $document->get_settings( 'hide_title' ) );
	}

	protected function register_controls() {
		parent::register_controls();

		$this->update_control(
			'title',
			array(
				'dynamic' => array(
					'default' => \Elementor\Plugin::$instance->dynamic_tags->tag_data_to_tag_text( null, $this->get_dynamic_tag_name() ),
				),
			),
			array(
				'recursive' => true,
			)
		);

		$this->update_control(
			'header_size',
			array(
				'default' => 'h1',
			)
		);
	}

	protected function get_html_wrapper_class() {
		return parent::get_html_wrapper_class() . ' elementor-page-title elementor-widget-heading';
	}

	public function render() {
		if ( $this->should_show_page_title() ) {
			parent::render();
		}
	}
}
