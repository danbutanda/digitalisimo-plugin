<?php
/**
 * «Info Button Box» de PRO (`contact-buttons-var-3`) → Botones de contacto (`digitalisimo-contact-buttons`, presentación 3;
 * modules/digitalisimo-floating/widgets/class-contact-buttons.php).
 * 
 * El widget propio hereda la base del núcleo y repite la unión de los controles de sus variantes con el mismo nombre,
 * valor por defecto y condición, así que el mapa es `keep` y `groups` más la presentación fija. Las clases del marcado
 * pasan de `e-contact-buttons__*` a `digi-cb__*` (y la de la variante a `digi-cb--p3`) para el CSS personalizado del elemento.
 * Formato: cabecera de `class-translator.php`. Generado desde los controles de PRO y del widget propio en WordPress
 * (la herramienta y los volcados se describen en `docs/pro/c8.md`).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'source'    => 'contact-buttons-var-3',
	'target'    => 'digitalisimo-contact-buttons',
	'status'    => 'ready',
	'panel'     => 'hide',
	// La presentación de la variante; el resto de controles tiene el mismo nombre en el widget propio.
	'set'       => array( 'presentation' => '3' ),
	// Valores por defecto de PRO que el widget propio no tiene iguales (textos en español o el de otra presentación).
	'defaults'  => array(
		'chat_aria_label' => 'Floating Element',
		'chat_button_icon' => array( 'value' => 'fas fa-info', 'library' => 'fa-solid' ),
		'top_bar_title' => 'Need help?',
		'send_button_text' => 'Shop Now',
	),
	'keep'      => array(
		'chat_aria_label',
		'chat_button_icon',
		'top_bar_title',
		'send_button_text',
		'send_button_url',
		'style_chat_button_size',
		'style_button_color_icon',
		'style_button_color_background',
		'style_button_color_icon_hover',
		'style_button_color_background_hover',
		'style_button_color_hover_animation',
		'style_button_color_icon_active',
		'style_button_color_background_active',
		'style_chat_button_animation',
		'style_chat_button_animation_duration',
		'style_chat_button_animation_delay',
		'style_chat_button_box_shadow_box_shadow',
		'style_chat_button_box_shadow_box_shadow_position',
		'style_top_bar_title_color',
		'style_top_bar_close_button_color',
		'style_info_links_icon_position',
		'style_info_links_icon_spacing',
		'style_info_links_link_spacing',
		'style_info_links_normal_text_color',
		'style_info_links_hover_text_color',
		'style_info_links_hover_animation',
		'style_info_links_dividers',
		'style_info_links_divider_color',
		'style_info_links_divider_weight',
		'style_send_normal_text_color',
		'style_send_normal_background_color',
		'style_send_hover_text_color',
		'style_send_hover_background_color',
		'style_send_hover_animation',
		'style_chat_button_padding',
		'style_chat_box_bg_color',
		'style_chat_box_corners',
		'style_chat_box_box_shadow_box_shadow',
		'style_chat_box_box_shadow_box_shadow_position',
		'style_chat_box_padding',
		'style_chat_box_entrance_animation',
		'style_chat_box_exit_animation',
		'style_chat_box_animation_duration',
		'advanced_horizontal_position',
		'advanced_horizontal_offset',
		'advanced_vertical_position',
		'advanced_vertical_offset',
		'advanced_custom_css_id',
		'advanced_custom_css_classes',
	),
	'groups'    => array(
		'style_chat_button_box_shadow' => 'style_chat_button_box_shadow',
		'style_top_bar_title_typography' => 'style_top_bar_title_typography',
		'style_info_links_typography' => 'style_info_links_typography',
		'style_send_typography' => 'style_send_typography',
		'style_chat_box_box_shadow' => 'style_chat_box_box_shadow',
	),
	'repeaters' => array(
		'contact_repeater' => array(
			'target'       => 'contact_repeater',
			'defaults'     => array( 'contact_tooltip' => 'Sample Link Text' ),
			'default_rows' => array( array( 'contact_icon_platform' => 'Telephone' ), array( 'contact_icon_platform' => 'Messenger' ), array( 'contact_icon_platform' => 'Url' ), array( 'contact_icon_platform' => 'Waze' ) ),
			'keep'         => array( 'contact_icon_platform', 'contact_tooltip', 'contact_icon_mail', 'contact_icon_mail_subject', 'contact_icon_mail_body', 'contact_icon_number', 'contact_icon_username', 'contact_icon_url', 'contact_icon_waze', 'contact_icon_viber_action' ),
		),
	),
	// Controles de interfaz (secciones, pestañas y encabezados): no guardan valores.
	'ui'        => array( 'chat_button_section', 'top_bar_section', 'contact_section', 'send_button_section', 'style_chat_button', 'style_button_color_tabs', 'style_button_color_tabs_normal', 'style_button_color_tabs_hover', 'style_button_color_tabs_active', 'style_top_bar_section', 'style_info_links_section', 'style_info_links_tabs', 'style_info_links_tabs_normal', 'style_info_links_tabs_hover', 'style_send_section', 'style_send_tabs', 'style_send_tabs_normal', 'style_send_tabs_hover', 'style_chat_box_section', 'advanced_layout_section', 'advanced_responsive_section', 'advanced_custom_controls_section' ),
	'classes'   => array(
		'.e-contact-buttons' => '.digi-cb',
		'.e-contact-buttons__chat-button' => '.digi-cb__chat-button',
		'.e-contact-buttons__chat-button-container' => '.digi-cb__chat-button-container',
		'.e-contact-buttons__chat-button-shadow' => '.digi-cb__chat-button-shadow',
		'.e-contact-buttons__close-button' => '.digi-cb__close-button',
		'.e-contact-buttons__contact-icon-container' => '.digi-cb__contact-icon-container',
		'.e-contact-buttons__contact-icon-link' => '.digi-cb__contact-icon-link',
		'.e-contact-buttons__contact-tooltip' => '.digi-cb__contact-tooltip',
		'.e-contact-buttons__content' => '.digi-cb__content',
		'.e-contact-buttons__content-wrapper' => '.digi-cb__content-wrapper',
		'.e-contact-buttons__cta-button' => '.digi-cb__cta-button',
		'.e-contact-buttons__links' => '.digi-cb__links',
		'.e-contact-buttons__links-container' => '.digi-cb__links-container',
		'.e-contact-buttons__top-bar' => '.digi-cb__top-bar',
		'.e-contact-buttons__top-bar-details' => '.digi-cb__top-bar-details',
		'.e-contact-buttons__top-bar-title' => '.digi-cb__top-bar-title',
		'.e-contact-buttons-var-3' => '.digi-cb--p3',
		'.elementor-widget-contact-buttons-var-3' => '.elementor-widget-digitalisimo-contact-buttons',
	),
);
