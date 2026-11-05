<?php
/**
 * Stripe Button de PRO (`stripe-button`) → Botón de Stripe (`digitalisimo-stripe-button`,
 * modules/digitalisimo-services/widgets/class-stripe-button.php).
 *
 * El Botón de Stripe hereda del Botón del núcleo, como el de PRO, y repite sus controles (los de la base de pagos menos
 * los que PRO quita, más los suyos) con el mismo nombre, los mismos valores por defecto y las mismas condiciones: el mapa
 * es todo `keep` y `groups`. La sesión de pago la sigue creando el mismo punto de PRO en el servidor, que lee estos
 * ajustes por nombre (`product_name`, `stripe_product_price`, `stripe_currency`, `stripe_quantity`, `shipping_amount`,
 * `redirect_after_success`, `sandbox_mode` y las tasas). Los textos por defecto propios están en español; aquí se completan
 * los de PRO, así un botón de PRO sin cambios conserva «Buy Now» y sus mensajes.
 *
 * Diferencias que no son de clave (no generan código): un `<button type="submit">` sin `role`, los mensajes ocultos con
 * `hidden` y `role="alert"`, el mensaje del servicio como texto (PRO lo pintaba como HTML saneado) y el foco visible
 * propio. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_pay_t = static function ( $text ) {
	// El valor por defecto que PRO guardaba en el idioma del sitio (Elementor no guarda lo que coincide con él).
	return function_exists( 'esc_html__' ) ? esc_html__( $text, 'elementor-pro' ) : $text; // phpcs:ignore WordPress.WP.I18n -- mismo texto que PRO.
};

return array(
	'source'   => 'stripe-button',
	'target'   => 'digitalisimo-stripe-button',
	'status'   => 'draft',
	'panel'    => 'hide',
	// Valores por defecto de PRO que difieren de los propios (textos en español).
	'defaults' => array(
		'text'                  => 'Buy Now',
		'error_message_global'  => $digi_pay_t( 'Something went wrong' ),
		'error_message_payment' => $digi_pay_t( 'Gateway not connected. Contact seller' ),
	),
	'keep'     => array(
		// Precio y pagos (lo que queda de la base de pagos de PRO tras sus quitas, y lo propio de Stripe).
		'test_environment_msg',
		'product_name',
		'donation_type',
		'donation_amount',
		'shipping_price',
		'tax_rate',
		'stripe_currency',
		'stripe_product_price',
		'stripe_quantity',
		'shipping_amount',
		'stripe_test_env_tax_rates_list',
		'stripe_live_env_tax_rates_list',
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
		'sandbox_mode_on_notice',
		'open_in_new_window',
		'custom_messages',
		'custom_error_on_notice',
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
		'.elementor-stripe-form'           => '.digi-pay--stripe',
		'.elementor-stripe-error-message'  => '.digi-pay__message--remote',
		'.elementor-payment-button'        => '.digi-pay__button',
		'.elementor-error-message-global'  => '.digi-pay__message--global',
		'.elementor-error-message-payment' => '.digi-pay__message--payment',
	),
);
