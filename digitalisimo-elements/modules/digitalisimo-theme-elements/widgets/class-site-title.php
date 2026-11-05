<?php
namespace Digitalisimo\Elements\Theme_Elements\Widgets;

use Digitalisimo\Elements\Theme_Elements\Module;
use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Título del sitio (`digitalisimo-site-title`): sustituye al widget «Site Title» de PRO
 * (`modules/theme-builder/widgets/site-title.php`, mapa `modules/digitalisimo-convert/maps/theme-site-title.php`). Como
 * PRO, hereda el Encabezado del núcleo (`\Elementor\Widget_Heading`: mismos controles, marcado y hoja `widget-heading`)
 * y repite lo que PRO cambia: el título por defecto es la etiqueta dinámica `site-title` (el nombre del sitio de
 * Ajustes → Generales), el enlace por defecto `site-url` (el inicio), el aviso `site_identity_notice` que abre la
 * identidad del sitio en el editor y la clase `elementor-widget-heading` en el envoltorio.
 *
 * Propio: la hoja sólo añade el foco visible del enlace (contorno de 2 px con separación de 2 px).
 */
class Site_Title extends \Elementor\Widget_Heading {

	public function get_name() {
		return 'digitalisimo-site-title';
	}

	public function get_title() {
		return esc_html__( 'Título del sitio', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-site-title';
	}

	public function get_categories() {
		return array( 'theme-elements' );
	}

	public function get_keywords() {
		return array( 'sitio', 'título', 'nombre', 'site', 'title', 'name' );
	}

	public function get_style_depends(): array {
		return array_values( array_unique( array_merge( parent::get_style_depends(), Module::style_depends( $this->get_name() ) ) ) );
	}

	protected function register_controls() {
		parent::register_controls();

		$tags = \Elementor\Plugin::$instance->dynamic_tags;
		$this->update_control(
			'title',
			array(
				'dynamic' => array(
					'default' => $tags->tag_data_to_tag_text( null, 'site-title' ),
				),
			),
			array(
				'recursive' => true,
			)
		);

		$this->update_control(
			'link',
			array(
				'dynamic' => array(
					'default' => $tags->tag_data_to_tag_text( null, 'site-url' ),
				),
			),
			array(
				'recursive' => true,
			)
		);

		$this->add_control(
			'site_identity_notice',
			array(
				'type'       => Controls_Manager::ALERT,
				'alert_type' => 'info',
				'content'    => sprintf(
					/* translators: 1: Etiqueta de apertura del enlace, 2: etiqueta de cierre. */
					esc_html__( 'El título del sitio se cambia en %1$sIdentidad del sitio%2$s.', 'pro-elements' ),
					'<a href="#" onclick="elementorPro.modules.themeBuilder.openSiteIdentity( event )" >',
					'</a>'
				),
			),
			array(
				'position' => array(
					'of'   => 'title',
					'type' => 'control',
					'at'   => 'before',
				),
			)
		);
	}

	protected function get_html_wrapper_class() {
		return parent::get_html_wrapper_class() . ' elementor-widget-heading';
	}
}
