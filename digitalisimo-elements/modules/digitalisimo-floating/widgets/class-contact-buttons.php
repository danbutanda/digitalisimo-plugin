<?php
namespace Digitalisimo\Elements\Floating\Widgets;

use Digitalisimo\Elements\Floating\Contact_Probe;
use Digitalisimo\Elements\Floating\Contact_Render;
use Digitalisimo\Elements\Floating\Module;
use Digitalisimo\Elements\Floating\Presentations;
use Elementor\Modules\FloatingButtons\Base\Widget_Contact_Button_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Botones de contacto (`digitalisimo-contact-buttons`, C8 de `docs/PRO_WIDGETS_OBJECTIVE.md`, contrato `docs/pro/c8.md`):
 * sustituye a los nueve botones de contacto de PRO (`contact-buttons-var-<n>`, presentación n). Hereda la base del
 * núcleo y repite la unión de los controles de las nueve variantes (`Presentations`, `Variants`), con el mismo nombre,
 * valor por defecto y condición; marcado (`Contact_Render`), clases (`digi-cb`), hojas por presentación y script propios
 * (`assets/js/contact-buttons.js`): sin jQuery ni los paquetes de PRO. Los enlaces de contacto son enlaces normales con su
 * destino; ningún script ni petición de esos servicios.
 */
class Contact_Buttons extends Widget_Contact_Button_Base {
	use Presentations;

	const NAME  = 'digitalisimo-contact-buttons';
	const PROBE = Contact_Probe::class;

	/** Clases y variables del núcleo → propias (selectores de los controles). */
	const RENAME = array(
		'.e-contact-buttons-var-' => '.digi-cb--p',
		'.e-contact-buttons'      => '.digi-cb',
		'--e-contact-buttons-'    => '--digi-cb-',
		'--e-contact-button-'     => '--digi-cb-',
	);

	public function get_name() {
		return self::NAME;
	}

	public function get_title(): string {
		return esc_html__( 'Botones de contacto', 'pro-elements' );
	}

	public function get_keywords(): array {
		return array( 'contacto', 'chat', 'whatsapp', 'flotante', 'botones', 'enlaces' );
	}

	public static function presentation_titles(): array {
		return array(
			'1'  => esc_html__( 'Chat múltiple', 'pro-elements' ),
			'3'  => esc_html__( 'Caja de información', 'pro-elements' ),
			'4'  => esc_html__( 'Enlaces verticales', 'pro-elements' ),
			'5'  => esc_html__( 'Clásico', 'pro-elements' ),
			'6'  => esc_html__( 'Barra de acceso rápido', 'pro-elements' ),
			'7'  => esc_html__( 'Barra móvil', 'pro-elements' ),
			'8'  => esc_html__( 'Caja de recursos', 'pro-elements' ),
			'9'  => esc_html__( 'Clásico animado', 'pro-elements' ),
			'10' => esc_html__( 'Interactivo', 'pro-elements' ),
		);
	}

	/** Textos por defecto en español de lo que la base del núcleo trae en inglés. */
	public static function texts(): array {
		return array(
			'chat_aria_label'     => esc_html__( 'Elemento flotante', 'pro-elements' ),
			'contact_aria_label'  => esc_html__( 'Elemento flotante', 'pro-elements' ),
			'top_bar_subtitle'    => esc_html__( 'Encargado de la tienda', 'pro-elements' ),
			'message_bubble_body' => esc_html__( 'Hola, ¿en qué puedo ayudarte hoy?', 'pro-elements' ),
			'contact_cta_text'    => esc_html__( 'Inicia la conversación:', 'pro-elements' ),
			'send_button_text'    => esc_html__( 'Haz clic para chatear', 'pro-elements' ),
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
		Contact_Render::make( $this )->render();
	}
}
