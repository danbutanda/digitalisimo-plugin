<?php
namespace Digitalisimo\Elements\Floating;

use Elementor\Core\Base\Providers\Social_Network_Provider;
use Elementor\Modules\FloatingButtons\Base\Widget_Contact_Button_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Sonda de los Botones de contacto (C8): construye los controles de una presentación sobre la base del núcleo
 * (`Widget_Contact_Button_Base`). La configuración y las secciones de cada presentación repiten las de las variantes de
 * PRO `contact-buttons-var-<n>` (código GPL de PRO Elements, `modules/floating-buttons/widgets/contact-buttons-var-*.php`,
 * con los textos en español): 1 «Multi Chat», 3 «Info Button Box», 4 «Vertical Links», 5 «Classic», 6 «Quick Access
 * Bar», 7 «Mobile Bar», 8 «Resource Box», 9 «Animated Classic» y 10 «Interactive».
 */
class Contact_Probe extends Widget_Contact_Button_Base {
	use Probe;

	const FAMILY = 'contact-buttons';

	/** Presentaciones, en el orden del control `presentation`. */
	const PRESENTATIONS = array( '1', '3', '4', '5', '6', '7', '8', '9', '10' );

	/** Secciones de contenido y de estilo de cada presentación, en el orden de su variante de PRO. */
	const SECTIONS = array(
		'1'  => array( array( 'chat_button', 'top_bar', 'message_bubble', 'contact' ), array( 'chat_button', 'top_bar', 'message_bubble', 'contact', 'chat_box' ) ),
		'3'  => array( array( 'chat_button', 'top_bar', 'contact', 'send_button' ), array( 'chat_button', 'top_bar', 'info_links', 'send_button', 'chat_box' ) ),
		'4'  => array( array( 'chat_button', 'contact' ), array( 'chat_button', 'contact' ) ),
		'5'  => array( array( 'chat_button' ), array( 'chat_button' ) ),
		'6'  => array( array( 'contact' ), array( 'contact' ) ),
		'7'  => array( array( 'chat_button' ), array( 'chat_button' ) ),
		'8'  => array( array( 'chat_button', 'top_bar', 'contact' ), array( 'chat_button', 'top_bar', 'resource_links', 'chat_box' ) ),
		'9'  => array( array( 'chat_button' ), array( 'chat_button' ) ),
		'10' => array( array( 'contact' ), array( 'contact' ) ),
	);

	public static function get_configuration() {
		return self::configure( parent::get_configuration(), '' === self::presentation() ? '1' : self::presentation() );
	}

	/** Configuración de la base del núcleo con los cambios de cada variante de PRO. */
	public static function configure( array $config, string $presentation ): array {
		if ( '1' === $presentation ) {
			$config['style']['has_platform_colors'] = false;
			$config['content']['chat_button_section']['has_platform'] = false;
			$config['content']['chat_button_section']['has_icon'] = true;
			$config['content']['message_bubble_section']['has_typing_animation'] = false;
			$config['style']['message_bubble_section']['has_chat_background'] = false;
			$config['style']['send_button_section']['has_typography'] = false;
			$config['content']['contact_section']['has_accessible_name'] = false;

		}
		if ( '3' === $presentation ) {
			$config['style']['has_platform_colors'] = false;
			$config['content']['chat_button_section']['section_name'] = esc_html__( 'Botón de información', 'pro-elements' );
			$config['content']['chat_button_section']['has_platform'] = false;
			$config['content']['chat_button_section']['has_notification_dot'] = false;
			$config['content']['chat_button_section']['has_active_tab'] = true;
			$config['content']['chat_button_section']['has_icon'] = true;
			$config['content']['chat_button_section']['icon_default'] = [
				'value' => 'fas fa-info',
				'library' => 'fa-solid',
			];
			$config['content']['chat_button_section']['icons_recommended'] = [
				'fa-solid' => [
					'info',
					'info-circle',
					'question',
					'question-circle',
				],
			];
			$config['content']['top_bar_section']['section_name'] = esc_html__( 'Encabezado', 'pro-elements' );
			$config['content']['top_bar_section']['title']['label'] = esc_html__( 'Saludo', 'pro-elements' );
			$config['content']['top_bar_section']['title']['placeholder'] = esc_html__( 'Escribe tu texto aquí', 'pro-elements' );
			$config['content']['top_bar_section']['title']['dynamic'] = true;
			$config['content']['top_bar_section']['title']['ai'] = true;
			$config['content']['top_bar_section']['title']['label_block'] = true;
			$config['content']['top_bar_section']['title']['default'] = esc_html__( '¿Necesitas ayuda?', 'pro-elements' );
			$config['content']['top_bar_section']['has_image'] = false;
			$config['content']['top_bar_section']['has_active_dot'] = false;
			$config['content']['top_bar_section']['has_subtitle'] = false;
			$config['style']['top_bar_section']['has_title_heading'] = false;
			$config['style']['top_bar_section']['has_close_button_heading'] = false;
			$config['style']['top_bar_section']['has_background'] = false;

			$config['content']['contact_section']['section_name'] = esc_html__( 'Enlaces de información', 'pro-elements' );
			$config['content']['contact_section']['has_cta_text'] = false;
			$config['content']['contact_section']['platform']['limit'] = null;
			$config['content']['contact_section']['repeater']['has_tooltip'] = true;
			$config['content']['contact_section']['repeater']['tooltip_default'] = esc_html__( 'Texto del enlace', 'pro-elements' );
			$config['content']['contact_section']['repeater']['tooltip_placeholder'] = esc_html__( 'Escribe tu texto aquí', 'pro-elements' );
			$config['content']['contact_section']['platform']['group-1'] = [
				Social_Network_Provider::EMAIL,
				Social_Network_Provider::TELEPHONE,
				Social_Network_Provider::SMS,
				Social_Network_Provider::WHATSAPP,
				Social_Network_Provider::SKYPE,
				Social_Network_Provider::MESSENGER,
				Social_Network_Provider::VIBER,
				Social_Network_Provider::WAZE,
				Social_Network_Provider::URL,
			];
			$config['content']['contact_section']['default'] = [
				[
					'contact_icon_platform' => Social_Network_Provider::TELEPHONE,
				],
				[
					'contact_icon_platform' => Social_Network_Provider::MESSENGER,
				],
				[
					'contact_icon_platform' => Social_Network_Provider::URL,
				],
				[
					'contact_icon_platform' => Social_Network_Provider::WAZE,
				],
			];
			$config['content']['contact_section']['has_accessible_name'] = false;

			$config['style']['chat_box_section']['section_name'] = esc_html__( 'Caja', 'pro-elements' );
			$config['style']['chat_box_section']['has_width'] = false;
			$config['style']['chat_box_section']['has_padding'] = true;

			$config['content']['send_button_section']['section_name'] = esc_html__( 'Botón de acción', 'pro-elements' );
			$config['content']['send_button_section']['text']['default'] = esc_html__( 'Comprar ahora', 'pro-elements' );
			$config['content']['send_button_section']['has_link'] = true;
			$config['style']['send_button_section']['has_platform_colors'] = false;
			$config['style']['send_button_section']['has_icon_color'] = false;
			$config['style']['send_button_section']['has_background_color'] = true;
			$config['style']['send_button_section']['has_text_color'] = true;
			$config['style']['send_button_section']['typography_selector'] = '{{WRAPPER}} .e-contact-buttons__cta-button';

		}
		if ( '4' === $presentation ) {
			$config['style']['has_platform_colors'] = false;
			$config['content']['chat_button_section']['section_name'] = esc_html__( 'Botón de menú', 'pro-elements' );
			$config['content']['chat_button_section']['has_platform'] = false;
			$config['content']['chat_button_section']['has_icon'] = true;
			$config['content']['chat_button_section']['icon_default'] = [
				'value' => 'fas fa-ellipsis-v',
				'library' => 'fa-solid',
			];
			$config['content']['chat_button_section']['has_notification_dot'] = false;
			$config['content']['chat_button_section']['has_active_tab'] = true;
			$config['content']['chat_button_section']['chat_aria_label'] = esc_html__( 'Enlaces', 'pro-elements' );
			$config['content']['contact_section']['platform']['group-1'] = [
				Social_Network_Provider::EMAIL,
				Social_Network_Provider::TELEPHONE,
				Social_Network_Provider::SMS,
				Social_Network_Provider::WHATSAPP,
				Social_Network_Provider::SKYPE,
				Social_Network_Provider::MESSENGER,
				Social_Network_Provider::VIBER,
				Social_Network_Provider::WAZE,
				Social_Network_Provider::URL,
			];
			$config['content']['contact_section']['default'] = [
				[
					'contact_icon_platform' => Social_Network_Provider::WHATSAPP,
				],
				[
					'contact_icon_platform' => Social_Network_Provider::MESSENGER,
				],
				[
					'contact_icon_platform' => Social_Network_Provider::EMAIL,
				],
				[
					'contact_icon_platform' => Social_Network_Provider::TELEPHONE,
				],
			];
			$config['content']['contact_section']['has_cta_text'] = false;
			$config['content']['contact_section']['repeater']['has_tooltip'] = true;
			$config['content']['contact_section']['repeater']['tooltip_label'] = esc_html__( 'Información emergente', 'pro-elements' );
			$config['content']['contact_section']['platform']['limit'] = null;
			$config['content']['contact_section']['has_accessible_name'] = false;
			$config['style']['contact_section']['has_buttons_heading'] = false;
			$config['style']['contact_section']['has_buttons_size'] = false;
			$config['style']['contact_section']['has_box_shadow'] = true;
			$config['style']['contact_section']['has_buttons_spacing'] = true;
			$config['style']['contact_section']['has_hover_animation'] = false;
			$config['style']['contact_section']['has_chat_box_animation'] = true;
			$config['style']['send_button_section']['has_typography'] = false;

		}
		if ( '5' === $presentation ) {
			$config['content']['chat_button_section']['has_notification_dot'] = true;
			$config['content']['chat_button_section']['has_notification_dot_default_enabled'] = false;
			$config['content']['chat_button_section']['platform']['group'] = [
				Social_Network_Provider::EMAIL,
				Social_Network_Provider::TELEPHONE,
				Social_Network_Provider::SMS,
				Social_Network_Provider::WHATSAPP,
				Social_Network_Provider::SKYPE,
				Social_Network_Provider::MESSENGER,
				Social_Network_Provider::VIBER,
				Social_Network_Provider::WAZE,
			];
			$config['content']['chat_button_section']['chat_aria_label'] = esc_html__( 'Chat', 'pro-elements' );
			$config['style']['send_button_section']['has_typography'] = false;

		}
		if ( '6' === $presentation ) {
			$config['style']['has_platform_colors'] = false;
			$config['content']['contact_section']['section_name'] = esc_html__( 'Botones de contacto', 'pro-elements' );
			$config['content']['contact_section']['has_cta_text'] = false;
			$config['content']['contact_section']['platform']['limit'] = 4;
			$config['content']['contact_section']['platform']['min_items'] = 2;
			$config['content']['contact_section']['platform']['group-1'] = [
				Social_Network_Provider::EMAIL,
				Social_Network_Provider::TELEPHONE,
				Social_Network_Provider::SMS,
				Social_Network_Provider::WHATSAPP,
				Social_Network_Provider::SKYPE,
				Social_Network_Provider::MESSENGER,
				Social_Network_Provider::VIBER,
				Social_Network_Provider::WAZE,
			];
			$config['content']['contact_section']['default'] = [
				[
					'contact_icon_platform' => Social_Network_Provider::TELEPHONE,
				],
				[
					'contact_icon_platform' => Social_Network_Provider::EMAIL,
				],
				[
					'contact_icon_platform' => Social_Network_Provider::WHATSAPP,
				],
				[
					'contact_icon_platform' => Social_Network_Provider::WAZE,
				],
			];
			$config['style']['contact_section']['has_buttons_heading'] = true;
			$config['style']['contact_section']['buttons_heading_label'] = esc_html__( 'Iconos', 'pro-elements' );
			$config['style']['contact_section']['has_icon_bg_color'] = false;
			$config['style']['contact_section']['has_button_bar'] = true;
			$config['advanced']['horizontal_position_default'] = 'center';
			$config['style']['send_button_section']['has_typography'] = false;

		}
		if ( '7' === $presentation ) {
			$config['content']['chat_button_section']['has_notification_dot'] = false;
			$config['content']['chat_button_section']['platform']['group'] = [
				Social_Network_Provider::EMAIL,
				Social_Network_Provider::TELEPHONE,
				Social_Network_Provider::SMS,
				Social_Network_Provider::WHATSAPP,
				Social_Network_Provider::SKYPE,
				Social_Network_Provider::MESSENGER,
				Social_Network_Provider::VIBER,
				Social_Network_Provider::WAZE,
				Social_Network_Provider::URL,
			];
			$config['content']['chat_button_section']['platform']['default'] = Social_Network_Provider::TELEPHONE;
			$config['content']['chat_button_section']['defaults']['number'] = esc_html__( '+1.212.555.7979', 'pro-elements' );
			$config['content']['chat_button_section']['has_display_text'] = true;
			$config['style']['chat_button_section']['has_entrance_animation'] = false;
			$config['style']['chat_button_section']['has_box_shadow'] = false;
			$config['style']['chat_button_section']['has_padding'] = true;
			$config['style']['chat_button_section']['has_button_size'] = false;
			$config['style']['chat_button_section']['has_typography'] = true;
			$config['style']['chat_button_section']['has_icon_position'] = true;
			$config['style']['chat_button_section']['has_icon_spacing'] = true;
			$config['advanced']['has_mobile_full_width'] = true;
			$config['style']['send_button_section']['has_typography'] = false;

		}
		if ( '8' === $presentation ) {
			$config['style']['has_platform_colors'] = false;
			$config['content']['chat_button_section']['section_name'] = esc_html__( 'Botón flotante', 'pro-elements' );
			$config['content']['chat_button_section']['has_platform'] = false;
			$config['content']['chat_button_section']['has_icon'] = true;
			$config['content']['chat_button_section']['icon_default'] = [
				'value' => 'fas fa-life-ring',
				'library' => 'fa-solid',
			];
			$config['content']['chat_button_section']['icons_recommended'] = [
				'fa-solid' => [
					'life-ring',
					'info',
					'question',
				],
				'fa-regular' => [
					'info',
					'question',
					'comment-alt',
				],
			];
			$config['content']['chat_button_section']['has_notification_dot'] = false;
			$config['content']['chat_button_section']['has_active_tab'] = true;

			$config['content']['top_bar_section']['title']['label'] = esc_html__( 'Saludo', 'pro-elements' );
			$config['content']['top_bar_section']['title']['default'] = __( 'Estamos para ayudarte', 'pro-elements' );
			$config['content']['top_bar_section']['title']['placeholder'] = esc_html__( 'Escribe tu texto aquí', 'pro-elements' );
			$config['content']['top_bar_section']['title']['dynamic'] = true;
			$config['content']['top_bar_section']['title']['ai'] = true;
			$config['content']['top_bar_section']['title']['label_block'] = true;
			$config['content']['top_bar_section']['subtitle']['label'] = esc_html__( 'Llamada a la acción', 'pro-elements' );
			$config['content']['top_bar_section']['subtitle']['default'] = esc_html__( 'Explora nuestros recursos', 'pro-elements' );
			$config['content']['top_bar_section']['subtitle']['placeholder'] = esc_html__( 'Escribe tu texto aquí', 'pro-elements' );
			$config['content']['top_bar_section']['subtitle']['dynamic'] = true;
			$config['content']['top_bar_section']['subtitle']['ai'] = true;
			$config['content']['top_bar_section']['subtitle']['label_block'] = true;
			$config['content']['top_bar_section']['has_image'] = false;
			$config['content']['top_bar_section']['has_active_dot'] = false;
			$config['style']['top_bar_section']['title_heading_label'] = esc_html__( 'Saludo', 'pro-elements' );
			$config['style']['top_bar_section']['subtitle_heading_label'] = esc_html__( 'Llamada a la acción', 'pro-elements' );
			$config['style']['top_bar_section']['has_style_close_button'] = false;

			$config['content']['contact_section']['platform']['limit'] = null;
			$config['content']['contact_section']['section_name'] = esc_html__( 'Enlaces de recursos', 'pro-elements' );
			$config['content']['contact_section']['platform']['group-1'] = [
				Social_Network_Provider::EMAIL,
				Social_Network_Provider::TELEPHONE,
				Social_Network_Provider::SMS,
				Social_Network_Provider::WHATSAPP,
				Social_Network_Provider::SKYPE,
				Social_Network_Provider::MESSENGER,
				Social_Network_Provider::VIBER,
				Social_Network_Provider::WAZE,
				Social_Network_Provider::URL,
			];
			$config['content']['contact_section']['default'] = [
				[
					'contact_icon_platform' => Social_Network_Provider::WHATSAPP,
				],
				[
					'contact_icon_platform' => Social_Network_Provider::MESSENGER,
				],
				[
					'contact_icon_platform' => Social_Network_Provider::EMAIL,
				],
				[
					'contact_icon_platform' => Social_Network_Provider::TELEPHONE,
				],
			];
			$config['content']['contact_section']['has_cta_text'] = false;
			$config['content']['contact_section']['repeater']['has_title'] = true;
			$config['content']['contact_section']['repeater']['has_description'] = true;

			$config['style']['chat_box_section']['section_name'] = esc_html__( 'Caja', 'pro-elements' );
			$config['style']['send_button_section']['has_typography'] = false;

		}
		if ( '9' === $presentation ) {
			$config['content']['chat_button_section']['has_notification_dot'] = false;
			$config['content']['chat_button_section']['has_display_text'] = true;
			$config['content']['chat_button_section']['display_text_label'] = esc_html__( 'Contáctanos', 'pro-elements' );
			$config['content']['chat_button_section']['has_display_text_select'] = false;
			$config['content']['chat_button_section']['platform']['group'] = [
				Social_Network_Provider::EMAIL,
				Social_Network_Provider::TELEPHONE,
				Social_Network_Provider::SMS,
				Social_Network_Provider::WHATSAPP,
				Social_Network_Provider::SKYPE,
				Social_Network_Provider::MESSENGER,
				Social_Network_Provider::VIBER,
				Social_Network_Provider::WAZE,
				Social_Network_Provider::URL,
			];
			$config['style']['chat_button_section']['has_box_shadow'] = false;
			$config['style']['chat_button_section']['has_drop_shadow'] = true;
			$config['style']['chat_button_section']['has_padding'] = true;
			$config['style']['chat_button_section']['has_tabs'] = false;
			$config['style']['chat_button_section']['has_platform_color_controls'] = true;
			$config['style']['chat_button_section']['has_entrance_animation'] = false;
			$config['style']['chat_button_section']['hover_animation_type'] = 'custom';
			$config['style']['chat_button_section']['icon_color_label'] = esc_html__( 'Color del texto y del icono', 'pro-elements' );
			$config['style']['chat_button_section']['has_typography'] = true;
			$config['style']['chat_button_section']['button_size_default'] = 'medium';
			$config['style']['send_button_section']['has_typography'] = false;

		}
		if ( '10' === $presentation ) {
			$config['content']['contact_section']['has_cta_text'] = false;
			$config['content']['contact_section']['repeater']['has_title'] = true;
			$config['content']['contact_section']['platform']['limit'] = null;
			$config['content']['contact_section']['platform']['group-1'] = [
				Social_Network_Provider::EMAIL,
				Social_Network_Provider::TELEPHONE,
				Social_Network_Provider::SMS,
				Social_Network_Provider::WHATSAPP,
				Social_Network_Provider::SKYPE,
				Social_Network_Provider::MESSENGER,
				Social_Network_Provider::VIBER,
				Social_Network_Provider::WAZE,
				Social_Network_Provider::URL,
			];
			$config['style']['contact_section']['has_buttons_size'] = true;
			$config['style']['contact_section']['has_hover_animation'] = false;
			$config['style']['contact_section']['has_buttons_spacing'] = true;
			$config['style']['contact_section']['has_box_shadow'] = true;
			$config['style']['contact_section']['has_padding'] = true;
			$config['style']['contact_section']['has_button_corners'] = true;
			$config['style']['contact_section']['has_typography'] = true;
			$config['style']['contact_section']['has_text_color'] = true;
			$config['style']['contact_section']['has_bg_color'] = true;
			$config['style']['contact_section']['has_tabs'] = false;
			$config['style']['contact_section']['has_hover_transition_duration'] = true;
			$config['style']['contact_section']['icon_color_label'] = esc_html__( 'Color del texto y del icono', 'pro-elements' );
			$config['advanced']['horizontal_position_default'] = 'start';
			$config['style']['send_button_section']['has_typography'] = false;

		}
		return $config;
	}

	protected function add_content_tab(): void {
		foreach ( self::SECTIONS[ '' === self::presentation() ? '1' : self::presentation() ][0] as $section ) {
			$this->{'add_' . $section . '_section'}();
		}
	}

	protected function add_style_tab(): void {
		foreach ( self::SECTIONS[ '' === self::presentation() ? '1' : self::presentation() ][1] as $section ) {
			$this->{'add_style_' . $section . '_section'}();
		}
	}
}
