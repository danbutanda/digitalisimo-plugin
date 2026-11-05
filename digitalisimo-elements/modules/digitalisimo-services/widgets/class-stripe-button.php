<?php
namespace Digitalisimo\Elements\Services\Widgets;

use Digitalisimo\Elements\Services\Payment_Button;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( Payment_Button::class ) ) {
	return;
}

/**
 * Botón de Stripe (`digitalisimo-stripe-button`): sustituye al widget «Stripe Button» de PRO
 * (`modules/payments/widgets/stripe-button.php`; mapa `modules/digitalisimo-convert/maps/stripe-button.php`). Repite sus
 * controles con el mismo nombre, valor por defecto, opciones y condiciones (derivados de PRO Elements, GPL), también los
 * que PRO quita y añade sobre la base, y el mismo flujo: al pulsar, el script propio (`assets/js/payment-button.js`, sin
 * jQuery) envía a `admin-ajax.php` la acción `submit_stripe_form` con `data[postId]`, `data[widgetId]`,
 * `data[pageUrl]` y `data[nonce]`, como el script de PRO. La sesión de pago la crea el servidor por el mismo punto de PRO
 * (`Payments\Module::submit_stripe_form()`): busca este elemento por su id en el documento y lee sus ajustes por nombre
 * (`product_name`, `stripe_product_price`, `stripe_currency`, `stripe_quantity`, `shipping_amount`,
 * `redirect_after_success`, `sandbox_mode` y las tasas), con la clave secreta de prueba o de producción guardada en las
 * opciones del sitio. Ninguna clave llega al navegador: el formulario sólo lleva el id del elemento y un nonce.
 */
class Stripe_Button extends Payment_Button {

	const STRIPE_PAYMENT_TYPE_CHECKOUT = 'payment';

	/** Acción AJAX y nonce del punto de PRO que crea la sesión de pago. */
	const AJAX_ACTION  = 'submit_stripe_form';
	const NONCE_ACTION = 'stripe_form_submit';

	public function get_name() {
		return 'digitalisimo-stripe-button';
	}

	public function get_title() {
		return esc_html__( 'Botón de Stripe', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-stripe-button';
	}

	public function get_keywords() {
		return array( 'stripe', 'pago', 'payment', 'vender', 'donar', 'comprar', 'tarjeta' );
	}

	protected function get_merchant_name() {
		return 'Stripe';
	}

	protected function get_variant() {
		return 'stripe';
	}

	/** El formulario lleva un nonce: no entra en la caché de elementos (en PRO quedaba guardado con la página). */
	protected function is_dynamic_content(): bool {
		return true;
	}

	/** Monedas que admite Stripe. */
	protected function get_stripe_currencies() {
		$out = array();
		foreach ( array( 'AED', 'AFN', 'ALL', 'AMD', 'ANG', 'AOA', 'ARS', 'AUD', 'AWG', 'AZN', 'BAM', 'BBD', 'BDT', 'BGN', 'BIF', 'BMD', 'BND', 'BOB', 'BRL', 'BSD', 'BWP', 'BYN', 'BZD', 'CAD', 'CDF', 'CHF', 'CLP', 'CNY', 'COP', 'CRC', 'CVE', 'CZK', 'DJF', 'DKK', 'DOP', 'DZD', 'EGP', 'ETB', 'EUR', 'FJD', 'FKP', 'GBP', 'GEL', 'GIP', 'GMD', 'GNF', 'GTQ', 'GYD', 'HKD', 'HNL', 'HRK', 'HTG', 'IDR', 'ILS', 'INR', 'ISK', 'JMD', 'JPY', 'KES', 'KGS', 'KHR', 'KMF', 'KRW', 'KYD', 'KZT', 'LAK', 'LBP', 'LKR', 'LRD', 'LSL', 'MAD', 'MDL', 'MGA', 'MKD', 'MMK', 'MNT', 'MOP', 'MRO', 'MUR', 'MVR', 'MWK', 'MXN', 'MYR', 'MZN', 'NAD', 'NGN', 'NIO', 'NOK', 'NPR', 'NZD', 'PAB', 'PEN', 'PGK', 'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RSD', 'RUB', 'RWF', 'SAR', 'SBD', 'SCR', 'SEK', 'SGD', 'SHP', 'SLL', 'SOS', 'SRD', 'STD', 'SZL', 'THB', 'TJS', 'TOP', 'TRY', 'TTD', 'TWD', 'TZS', 'UAH', 'UYU', 'UZS', 'VND', 'VUV', 'WST', 'XAF', 'XCD', 'XOF', 'XPF', 'YER', 'ZAR', 'ZMW', 'USD' ) as $code ) {
			$out[ $code ] = $code;
		}
		return $out;
	}

	protected function stripe_global_error_massage() {
		return esc_html__( 'Algo salió mal.', 'pro-elements' );
	}

	protected function stripe_gateway_error_massage() {
		return esc_html__( 'La pasarela no está conectada. Contacta con el vendedor.', 'pro-elements' );
	}

	/** Errores de validación: sin nombre o sin precio. */
	protected function get_errors() {
		$settings = $this->get_settings_for_display();
		$errors   = array();
		if ( empty( $settings['product_name'] ) || empty( $settings['stripe_product_price'] ) ) {
			$errors[ self::ERROR_MESSAGE_GLOBAL ] = $this->get_custom_message( self::ERROR_MESSAGE_GLOBAL );
		}
		return $errors;
	}

	protected function render_form( array $settings ): void {
		$data = array(
			'data-digi-pay'         => 'stripe',
			'data-digi-pay-url'     => admin_url( 'admin-ajax.php' ),
			'data-digi-pay-action'  => self::AJAX_ACTION,
			'data-digi-pay-widget'  => $this->get_id(),
			'data-digi-pay-nonce'   => wp_create_nonce( self::NONCE_ACTION ),
			'data-digi-pay-target'  => 'yes' === ( $settings['open_in_new_window'] ?? '' ) ? '_blank' : '_self',
			'data-digi-pay-custom'  => ! empty( $settings['custom_messages'] ) ? 'yes' : '',
			'data-digi-pay-global'  => (string) ( $settings['error_message_global'] ?? '' ),
			'data-digi-pay-payment' => (string) ( $settings['error_message_payment'] ?? '' ),
		);
		echo '<form class="digi-pay digi-pay--stripe"';
		foreach ( $data as $name => $value ) {
			echo ' ' . esc_attr( $name ) . '="' . ( 'data-digi-pay-url' === $name ? esc_url( $value ) : esc_attr( $value ) ) . '"';
		}
		echo '>';
		$this->print_button();
		$this->print_errors( $this->get_errors() );
		echo '</form>';
	}

	/** Sección «Precio y pagos»: aviso de las claves y producto. */
	protected function register_account_section() {
		$this->start_controls_section(
			'section_stripe_account',
			array( 'label' => esc_html__( 'Precio y pagos', 'pro-elements' ) )
		);

		$this->add_control(
			'test_environment_msg',
			array(
				'type'       => \Elementor\Controls_Manager::ALERT,
				'alert_type' => 'info',
				'content'    => sprintf(
					/* translators: 1: apertura del enlace a los ajustes de integraciones, 2: cierre. */
					esc_html__( 'Para que este widget funcione, escribe las claves de la API de Stripe en los %1$sajustes de integraciones%2$s.', 'pro-elements' ),
					self::settings_link(),
					'' !== self::settings_link() ? '</a>' : ''
				),
				'separator'  => 'after',
			)
		);

		$this->register_product_controls();

		$this->remove_control( 'type' );

		$this->update_control(
			'type',
			array(
				'default' => self::STRIPE_PAYMENT_TYPE_CHECKOUT,
				'options' => array( self::STRIPE_PAYMENT_TYPE_CHECKOUT => esc_html__( 'Pago', 'pro-elements' ) ),
			)
		);

		$this->update_control(
			'product_name',
			array(
				'label'    => esc_html__( 'Nombre del producto', 'pro-elements' ),
				'required' => true,
			)
		);

		$this->add_control(
			'stripe_currency',
			array(
				'label'              => esc_html__( 'Moneda', 'pro-elements' ),
				'type'               => \Elementor\Controls_Manager::SELECT2,
				'options'            => $this->get_stripe_currencies(),
				'frontend_available' => true,
				'multiple'           => false,
				'default'            => 'USD',
				'description'        => esc_html__( 'Comprueba que el importe cumple el mínimo de cobro que exige Stripe.', 'pro-elements' ),
				'render_type'        => 'none',
				'required'           => true,
				'select2options'     => array( 'placeholder' => 'USD' ),
			)
		);

		$this->add_control(
			'stripe_product_price',
			array(
				'label'   => esc_html__( 'Precio del producto', 'pro-elements' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'default' => '0.00',
				'dynamic' => array( 'active' => true ),
				'min'     => 0,
			)
		);

		$this->remove_control( 'product_price' );
		$this->remove_control( 'currency' );
		$this->remove_control( 'billing_cycle' );
		$this->remove_control( 'auto_renewal' );
		$this->remove_control( 'product_sku' );
		$this->remove_control( 'quantity' );

		$this->add_control(
			'stripe_quantity',
			array(
				'label'   => esc_html__( 'Cantidad', 'pro-elements' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'default' => 1,
			)
		);

		$this->remove_control( 'tax_type' );

		$this->add_control(
			'shipping_amount',
			array(
				'label'   => esc_html__( 'Precio del envío', 'pro-elements' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'default' => 0,
				'dynamic' => array( 'active' => true ),
				'min'     => 0,
			)
		);

		$this->add_control(
			'stripe_test_env_tax_rates_list',
			array(
				'label'       => esc_html__( 'Tasa de impuesto', 'pro-elements' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'options'     => array( '' => esc_html__( 'Ninguna', 'pro-elements' ) ),
				'condition'   => array( 'sandbox_mode[value]' => 'yes' ),
				'description' => esc_html__( 'Se gestionan en tu cuenta de Stripe, en Productos > Tasas de impuesto.', 'pro-elements' ),
			)
		);

		$this->add_control(
			'stripe_live_env_tax_rates_list',
			array(
				'label'       => esc_html__( 'Tasa de impuesto', 'pro-elements' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'options'     => array( '' => esc_html__( 'Ninguna', 'pro-elements' ) ),
				'condition'   => array( 'sandbox_mode[value]!' => 'yes' ),
				'description' => esc_html__( 'Se gestionan en tu cuenta de Stripe, en Productos > Tasas de impuesto.', 'pro-elements' ),
			)
		);

		$this->end_controls_section();
	}

	/** Apertura del enlace a la pestaña de integraciones de los ajustes del constructor, o '' sin ella. */
	private static function settings_link(): string {
		if ( ! class_exists( '\Elementor\Settings' ) || ! method_exists( '\Elementor\Settings', 'get_settings_tab_url' ) ) {
			return '';
		}
		return '<a href="' . esc_url( \Elementor\Settings::get_settings_tab_url( 'integrations' ) ) . '" target="_blank">';
	}

	/** Controles del botón con el icono y el color de Stripe. */
	public function register_stripe_button_controls() {
		parent::register_controls();

		$this->update_control(
			'selected_icon',
			array(
				'default'     => array(
					'value'   => 'fab fa-stripe-s',
					'library' => 'fa-brands',
				),
				'recommended' => array(
					'fa-brands' => array( 'stripe-s', 'stripe', 'cc-stripe' ),
				),
			)
		);

		$this->update_control( 'background_color', array( 'default' => '#635bff' ) );
	}

	protected function register_controls() {
		$this->register_stripe_button_controls();
	}

	/** Textos y marcadores de los mensajes de Stripe. */
	protected function update_error_massages() {
		$this->update_control(
			'error_message_' . self::ERROR_MESSAGE_GLOBAL,
			array(
				'placeholder' => $this->stripe_global_error_massage(),
				'default'     => $this->stripe_global_error_massage(),
			)
		);
		$this->update_control(
			'error_message_' . self::ERROR_MESSAGE_PAYMENT_METHOD,
			array(
				'placeholder' => $this->stripe_gateway_error_massage(),
				'default'     => $this->stripe_gateway_error_massage(),
			)
		);
	}

	/** Aviso de que los mensajes propios sustituyen a los de Stripe. */
	protected function after_custom_messages_toggle() {
		$this->add_control(
			'custom_error_on_notice',
			array(
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Estos mensajes sustituyen a los de Stripe.', 'pro-elements' ) . '<br/>' . esc_html__( 'Úsalos en el sitio en producción, no mientras pruebas.', 'pro-elements' ),
				'content_classes' => 'elementor-descriptor',
				'condition'       => array( 'custom_messages!' => '' ),
			)
		);
	}

	/** Modo de prueba de Stripe: la clave secreta de prueba de los ajustes de integraciones. */
	protected function register_sandbox_controls() {
		$this->update_control(
			'sandbox_mode',
			array(
				'label'              => esc_html__( 'Entorno de prueba de Stripe', 'pro-elements' ),
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'sandbox_mode_on_notice',
			array(
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'raw'             => sprintf(
					/* translators: 1: apertura del enlace a los ajustes de integraciones, 2: cierre. */
					esc_html__( 'Completa todo el pago en tu sitio con un método de pago simulado, usando la clave de prueba de Stripe de los %1$sajustes de integraciones%2$s.', 'pro-elements' ),
					self::settings_link(),
					'' !== self::settings_link() ? '</a>' : ''
				),
				'content_classes' => 'elementor-descriptor',
				'condition'       => array( 'sandbox_mode' => 'yes' ),
			)
		);

		$this->remove_control( 'sandbox_email' );
	}
}
