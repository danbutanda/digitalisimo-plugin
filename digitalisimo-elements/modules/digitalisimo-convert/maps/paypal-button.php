<?php
/**
 * PayPal Button de PRO (`paypal-button`) → Botón de PayPal (`digitalisimo-paypal-button`,
 * modules/digitalisimo-services/widgets/class-paypal-button.php).
 *
 * El Botón de PayPal hereda del Botón del núcleo, como el de PRO, y repite los controles de la base de pagos de PRO con
 * el mismo nombre, los mismos valores por defecto y las mismas condiciones: el mapa es todo `keep` y `groups`. Los textos
 * por defecto propios están en español; aquí se completan los de PRO (Elementor no guarda lo que coincide con el valor por
 * defecto), así un botón de PRO sin cambios conserva «Buy Now» y sus mensajes.
 *
 * El formulario a PayPal tiene los mismos campos y el mismo destino. Diferencias que no son de clave (no generan código):
 * un `<button type="submit">` sin `role`, los mensajes ocultos con `hidden` y `role="alert"`, y el foco visible propio.
 * Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_pay_t = static function ( $text ) {
	// El valor por defecto que PRO guardaba en el idioma del sitio (Elementor no guarda lo que coincide con él).
	return function_exists( 'esc_html__' ) ? esc_html__( $text, 'elementor-pro' ) : $text; // phpcs:ignore WordPress.WP.I18n -- mismo texto que PRO.
};

return array(
	'source'   => 'paypal-button',
	'target'   => 'digitalisimo-paypal-button',
	'status'   => 'draft',
	'panel'    => 'hide',
	// Valores por defecto de PRO que difieren de los propios (textos en español).
	'defaults' => array(
		'text'                  => 'Buy Now',
		'error_message_global'  => $digi_pay_t( 'An error occurred.' ),
		'error_message_payment' => $digi_pay_t( 'No payment method connected. Contact seller.' ),
	),
	'keep'     => array(
		// Precio y pagos.
		'merchant_account',
		'email',
		'sdk_token',
		'type',
		'product_name',
		'product_sku',
		'product_price',
		'donation_type',
		'donation_amount',
		'currency',
		'billing_cycle',
		'auto_renewal',
		'quantity',
		'shipping_price',
		'tax_type',
		'tax_rate',
		// Botón del núcleo.
		'text',
		'selected_icon',
		'icon_align',
		'icon_indent',
		'button_css_id',
		'align',
		'content_align',
		'button_text_color',
		'hover_color',
		'button_hover_border_color',
		'button_hover_transition_duration',
		'hover_animation',
		'border_radius',
		'text_padding',
		// Opciones adicionales.
		'redirect_after_success',
		'sandbox_mode',
		'sandbox_email',
		'open_in_new_window',
		'custom_messages',
		'error_message_global',
		'error_message_payment',
		// Mensajes.
		'message_color_global',
		'message_color_payment',
	),
	// Grupos del botón del núcleo y de los mensajes, con el mismo prefijo.
	'groups'   => array(
		'typography'              => 'typography',
		'text_shadow'             => 'text_shadow',
		'background'              => 'background',
		'button_box_shadow'       => 'button_box_shadow',
		'button_background_hover' => 'button_background_hover',
		'button_hover_box_shadow' => 'button_hover_box_shadow',
		'border'                  => 'border',
		'message_typography'      => 'message_typography',
	),
	// Clases de PRO en el CSS personalizado del elemento. Las del botón y los mensajes del núcleo
	// (`elementor-button`, `elementor-message`, `elementor-message-danger`) no cambian: el widget propio las conserva.
	'classes'  => array(
		'.elementor-payment-button'       => '.digi-pay__button',
		'.elementor-paypal-legacy'        => '.digi-pay__button',
		'.elementor-payment-sandbox-mode' => '.digi-pay__button--sandbox',
		'.elementor-error-message-global'  => '.digi-pay__message--global',
		'.elementor-error-message-payment' => '.digi-pay__message--payment',
	),
);
