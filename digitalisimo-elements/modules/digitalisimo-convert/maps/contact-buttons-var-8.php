<?php
/**
 * «Resource Box» de PRO (`contact-buttons-var-8`) → Botones de contacto (`digitalisimo-contact-buttons`, presentación 8;
 * modules/digitalisimo-floating/widgets/class-contact-buttons.php).
 * 
 * El widget propio hereda la base del núcleo y repite la unión de los controles de sus variantes con el mismo nombre,
 * valor por defecto y condición, así que el mapa es `keep` y `groups` más la presentación fija. Las clases del marcado
 * pasan de `e-contact-buttons__*` a `digi-cb__*` (y la de la variante a `digi-cb--p8`) para el CSS personalizado del elemento.
 * Formato: cabecera de `class-translator.php`. Generado desde los controles de PRO y del widget propio en WordPress
 * (la herramienta y los volcados se describen en `docs/pro/c8.md`).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'source'    => 'contact-buttons-var-8',
	'target'    => 'digitalisimo-contact-buttons',
	'status'    => 'draft',
	'panel'     => 'hide',
	// La presentación de la variante; el resto de controles tiene el mismo nombre en el widget propio.
	'set'       => array( 'presentation' => '8' ),
	// Valores por defecto de PRO que el widget propio no tiene iguales (textos en español o el de otra presentación).
	'defaults'  => array(
		'chat_aria_label' => 'Floating Element',
		'chat_button_icon' => array( 'value' => 'fas fa-life-ring', 'library' => 'fa-solid' ),
		'top_bar_title' => 'We\'re here for you',
		'top_bar_subtitle' => 'Explore our resources',
		'contact_aria_label' => 'Floating Element',
	),
	'keep'      => array(
		'chat_aria_label',
		'chat_button_icon',
		'top_bar_title',
		'top_bar_subtitle',
		'contact_aria_label',
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
		'style_top_bar_subtitle_color',
		'style_top_bar_background_color',
		'style_resource_links_button_size',
		'style_resource_links_color_select',
		'style_contact_icon_color',
		'style_resource_links_title_color',
		'style_resource_links_description_color',
		'style_resource_links_bg_color',
		'style_resource_links_hover_animation',
		'style_chat_box_bg_color',
		'style_chat_box_width',
		'style_chat_box_corners',
		'style_chat_box_box_shadow_box_shadow',
		'style_chat_box_box_shadow_box_shadow_position',
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
		'style_top_bar_subtitle_typography' => 'style_top_bar_subtitle_typography',
		'style_resource_links_title_typography' => 'style_resource_links_title_typography',
		'style_resource_links_description_typography' => 'style_resource_links_description_typography',
		'style_chat_box_box_shadow' => 'style_chat_box_box_shadow',
	),
	'repeaters' => array(
		'contact_repeater' => array(
			'target'       => 'contact_repeater',
			'default_rows' => array( array( 'contact_icon_platform' => 'WhatsApp' ), array( 'contact_icon_platform' => 'Messenger' ), array( 'contact_icon_platform' => 'Email' ), array( 'contact_icon_platform' => 'Telephone' ) ),
			'keep'         => array( 'contact_icon_platform', 'contact_title', 'contact_description', 'contact_icon_mail', 'contact_icon_mail_subject', 'contact_icon_mail_body', 'contact_icon_number', 'contact_icon_username', 'contact_icon_url', 'contact_icon_waze', 'contact_icon_viber_action' ),
		),
	),
	// Controles de interfaz (secciones, pestañas y encabezados): no guardan valores.
	'ui'        => array( 'chat_button_section', 'top_bar_section', 'contact_section', 'style_chat_button', 'style_button_color_tabs', 'style_button_color_tabs_normal', 'style_button_color_tabs_hover', 'style_button_color_tabs_active', 'style_top_bar_section', 'style_top_bar_title_heading', 'style_top_bar_subtitle_heading', 'style_resource_links_section', 'style_resource_links_icons_heading', 'style_resource_links_title_heading', 'style_resource_links_description_heading', 'style_chat_box_section', 'advanced_layout_section', 'advanced_responsive_section', 'advanced_custom_controls_section' ),
	'classes'   => array(
		'.e-contact-buttons' => '.digi-cb',
		'.e-contact-buttons__chat-button' => '.digi-cb__chat-button',
		'.e-contact-buttons__chat-button-container' => '.digi-cb__chat-button-container',
		'.e-contact-buttons__chat-button-shadow' => '.digi-cb__chat-button-shadow',
		'.e-contact-buttons__close-button' => '.digi-cb__close-button',
		'.e-contact-buttons__contact-description' => '.digi-cb__contact-description',
		'.e-contact-buttons__contact-icon-container' => '.digi-cb__contact-icon-container',
		'.e-contact-buttons__contact-icon-link' => '.digi-cb__contact-icon-link',
		'.e-contact-buttons__contact-links' => '.digi-cb__contact-links',
		'.e-contact-buttons__contact-title' => '.digi-cb__contact-title',
		'.e-contact-buttons__content' => '.digi-cb__content',
		'.e-contact-buttons__content-wrapper' => '.digi-cb__content-wrapper',
		'.e-contact-buttons__links-container' => '.digi-cb__links-container',
		'.e-contact-buttons__top-bar' => '.digi-cb__top-bar',
		'.e-contact-buttons__top-bar-details' => '.digi-cb__top-bar-details',
		'.e-contact-buttons__top-bar-subtitle' => '.digi-cb__top-bar-subtitle',
		'.e-contact-buttons__top-bar-title' => '.digi-cb__top-bar-title',
		'.e-contact-buttons-var-8' => '.digi-cb--p8',
		'.elementor-widget-contact-buttons-var-8' => '.elementor-widget-digitalisimo-contact-buttons',
	),
);
