<?php
namespace Digitalisimo\Elements\Floating\Widgets;

use Digitalisimo\Elements\Floating\Bar_Probe;
use Digitalisimo\Elements\Floating\Bar_Render;
use Digitalisimo\Elements\Floating\Module;
use Digitalisimo\Elements\Floating\Presentations;
use Elementor\Modules\FloatingButtons\Base\Widget_Floating_Bars_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Barra flotante (`digitalisimo-floating-bar`, C8 de `docs/PRO_WIDGETS_OBJECTIVE.md`, contrato `docs/pro/c8.md`):
 * sustituye a las barras flotantes de PRO «Ticker» (`floating-bars-var-2`, presentación 2) y «Coupon»
 * (`floating-bars-var-3`, presentación 3). Hereda la base del núcleo y repite la unión de los controles de las dos
 * variantes (`Presentations`, `Variants`), con el mismo nombre, valor por defecto y condición; marcado, clases
 * (`digi-fbar`), hojas por presentación y script propios (`Bar_Render`, `assets/js/floating-bar.js`): sin jQuery ni los
 * paquetes de PRO. Vive en los documentos de botones flotantes del constructor, como las variantes de PRO.
 */
class Floating_Bar extends Widget_Floating_Bars_Base {
	use Presentations;

	const NAME  = 'digitalisimo-floating-bar';
	const PROBE = Bar_Probe::class;

	/** Clases y variables del núcleo → propias (selectores de los controles). */
	const RENAME = array(
		'.e-floating-bars-var-' => '.digi-fbar--p',
		'.e-floating-bars'      => '.digi-fbar',
		'--e-floating-bars-'    => '--digi-fbar-',
	);

	public function get_name() {
		return self::NAME;
	}

	public function get_title(): string {
		return esc_html__( 'Barra flotante', 'pro-elements' );
	}

	public function get_keywords(): array {
		return array( 'barra', 'flotante', 'cinta', 'cupón', 'anuncio', 'banner' );
	}

	public static function presentation_titles(): array {
		return array(
			'2' => esc_html__( 'Cinta de titulares', 'pro-elements' ),
			'3' => esc_html__( 'Cupón', 'pro-elements' ),
		);
	}

	/** Textos por defecto en español de lo que la base del núcleo trae en inglés. */
	public static function texts(): array {
		return array(
			'announcement_text' => esc_html__( '¡Recién llegadas! Camisetas de verano', 'pro-elements' ),
			'cta_text'          => esc_html__( 'Comprar ahora', 'pro-elements' ),
		);
	}

	public function get_style_depends(): array {
		$icons = class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->experiments->is_feature_active( 'e_font_icon_svg' )
			? array()
			: array( 'elementor-icons-fa-solid', 'elementor-icons-fa-brands', 'elementor-icons-fa-regular' );
		return array_merge( $icons, $this->presentation_styles() );
	}

	public function get_script_depends(): array {
		return Module::script_depends( self::NAME );
	}

	protected function render(): void {
		( new Bar_Render( $this ) )->render();
	}
}
