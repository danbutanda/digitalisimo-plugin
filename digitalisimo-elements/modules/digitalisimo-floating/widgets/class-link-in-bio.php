<?php
namespace Digitalisimo\Elements\Floating\Widgets;

use Digitalisimo\Elements\Floating\Bio_Probe;
use Digitalisimo\Elements\Floating\Bio_Render;
use Digitalisimo\Elements\Floating\Presentations;
use Elementor\Modules\LinkInBio\Base\Widget_Link_In_Bio_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Link in Bio (`digitalisimo-link-in-bio`, C8 de `docs/PRO_WIDGETS_OBJECTIVE.md`, contrato `docs/pro/c8.md`): sustituye a
 * las seis variantes de PRO `link-in-bio-var-<n>` (presentación n: 2 «Classic», 3 «Showcase», 4 «Links», 5 «Services»,
 * 6 «Portfolio Bio» y 7 «Business Card»). Hereda la base del núcleo y repite la unión de los controles de las seis
 * variantes (`Presentations`, `Variants`), con el mismo nombre, valor por defecto y condición; marcado (`Bio_Render`),
 * clases (`digi-lib`) y hojas por presentación propias, sin script: sin jQuery ni los paquetes de PRO. Como las variantes de
 * PRO, vive en páginas normales (el núcleo no tiene un tipo de documento propio para Link in Bio) y aparece en el panel
 * en la categoría del núcleo «Link in Bio».
 */
class Link_In_Bio extends Widget_Link_In_Bio_Base {
	use Presentations;

	const NAME  = 'digitalisimo-link-in-bio';
	const PROBE = Bio_Probe::class;

	/** Clases y variables del núcleo → propias (selectores de los controles). */
	const RENAME = array(
		'.e-link-in-bio-var-' => '.digi-lib--p',
		'.e-link-in-bio'      => '.digi-lib',
		'--e-link-in-bio-'    => '--digi-lib-',
	);

	public function get_name() {
		return self::NAME;
	}

	public function get_title(): string {
		return esc_html__( 'Link in Bio', 'pro-elements' );
	}

	public function get_keywords(): array {
		return array( 'link in bio', 'biografía', 'enlaces', 'perfil', 'tarjeta de presentación', 'redes' );
	}

	public static function presentation_titles(): array {
		return array(
			'2' => esc_html__( 'Clásica', 'pro-elements' ),
			'3' => esc_html__( 'Escaparate', 'pro-elements' ),
			'4' => esc_html__( 'Enlaces', 'pro-elements' ),
			'5' => esc_html__( 'Servicios', 'pro-elements' ),
			'6' => esc_html__( 'Portafolio', 'pro-elements' ),
			'7' => esc_html__( 'Tarjeta de presentación', 'pro-elements' ),
		);
	}

	/** Textos por defecto en español de lo que la base del núcleo trae en inglés. */
	public static function texts(): array {
		return array(
			'bio_title'       => esc_html__( 'Crónicas de cocina', 'pro-elements' ),
			'bio_about'       => esc_html__( 'Sobre mí', 'pro-elements' ),
		);
	}

	/** La presentación 7 pinta la descripción al pie, como su variante de PRO. */
	public function get_description_position() {
		if ( $this->is_type_instance() ) {
			return 'top';
		}
		return '7' === self::presentation_of( (array) $this->get_settings() ) ? 'bottom' : 'top';
	}

	public function get_style_depends(): array {
		$icons = class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->experiments->is_feature_active( 'e_font_icon_svg' )
			? array()
			: array( 'elementor-icons-fa-solid', 'elementor-icons-fa-brands', 'elementor-icons-fa-regular' );
		return array_merge( $icons, $this->presentation_styles() );
	}

	public function get_script_depends(): array {
		return array();
	}

	protected function render(): void {
		Bio_Render::make( $this )->render();
	}
}
