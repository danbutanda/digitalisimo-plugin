<?php
namespace Digitalisimo\Elements\Services\Widgets;

use Digitalisimo\Elements\Services\Payment_Button;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( Payment_Button::class ) ) {
	return;
}

/**
 * Botón de PayPal (`digitalisimo-paypal-button`): sustituye al widget «PayPal Button» de PRO
 * (`modules/payments/widgets/paypal-button.php`; mapa `modules/digitalisimo-convert/maps/paypal-button.php`). Repite sus
 * controles con el mismo nombre, valor por defecto, opciones y condiciones (derivados de PRO Elements, GPL) y el mismo
 * flujo: un formulario `POST` a PayPal (`cgi-bin/webscr`, o el de pruebas con «Modo de prueba») con los mismos campos
 * ocultos (`cmd`, `business`, `lc`, `item_name`, `item_number`, `currency_code`, importe, `no_note`, envío, impuesto y
 * cantidad en el pago; `src`, `p3`, `t3` y `no-shipping` en la suscripción; `return` con la redirección). Como en PRO,
 * sólo la cuenta «simple» pinta el formulario; el token SDK (control oculto) nunca se pinta.
 *
 * Con datos que faltan (cuenta, precio, importe o impuesto), los mensajes de error se pintan ocultos y el script propio
 * impide el envío y los muestra al pulsar, como PRO; en el editor no hay mensajes.
 */
class Paypal_Button extends Payment_Button {

	const API_TYPE_SIMPLE   = 'simple';
	const API_TYPE_ADVANCED = 'advanced';

	const PROD_URL         = 'https://www.paypal.com/cgi-bin/webscr';
	const SANDBOX_URL      = 'https://sandbox.paypal.com/cgi-bin/webscr';
	const CMD_CHECKOUT     = '_xclick';
	const CMD_DONATION     = '_donations';
	const CMD_SUBSCRIPTION = '_xclick-subscriptions';

	const BILLING_CYCLE_TYPES = array(
		self::BILLING_CYCLE_DAYS   => 'D',
		self::BILLING_CYCLE_WEEKS  => 'W',
		self::BILLING_CYCLE_MONTHS => 'M',
		self::BILLING_CYCLE_YEARS  => 'Y',
	);

	public function get_name() {
		return 'digitalisimo-paypal-button';
	}

	public function get_title() {
		return esc_html__( 'Botón de PayPal', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-paypal-button';
	}

	public function get_keywords() {
		return array( 'paypal', 'pago', 'payment', 'vender', 'donar', 'donación', 'comprar' );
	}

	protected function get_merchant_name() {
		return 'PayPal';
	}

	protected function get_variant() {
		return 'paypal';
	}

	/** Número de un ajuste, o $min si es menor. */
	protected function get_numeric_setting( $key, $min = 0 ) {
		$num = doubleval( $this->get_settings_for_display( $key ) );
		return ( $min > $num ) ? $min : $num;
	}

	/** Método de la cuenta: `legacy` (formulario) o `sdk` (sin implementar en PRO). */
	protected function get_api_method() {
		return self::API_TYPE_ADVANCED === $this->get_settings_for_display( 'merchant_account' ) ? 'sdk' : 'legacy';
	}

	/** Errores de validación (ninguno en el editor), reducidos a uno global si hay varios. */
	protected function get_errors( $squash_errors = true ) {
		$settings = $this->get_settings_for_display();
		$errors   = array();
		if ( $this->is_edit_mode() ) {
			return $errors;
		}
		if ( 'legacy' === $this->get_api_method() ) {
			$empty_email         = empty( $settings['email'] );
			$empty_sandbox_email = $this->is_sandbox() && empty( $settings['sandbox_email'] );
			if ( $empty_email || $empty_sandbox_email ) {
				$errors[ self::ERROR_MESSAGE_PAYMENT_METHOD ] = $this->get_custom_message( self::ERROR_MESSAGE_PAYMENT_METHOD );
			}
		}
		$empty_product_price   = ( self::PAYMENT_TYPE_DONATION !== ( $settings['type'] ?? '' ) && empty( $settings['product_price'] ) );
		$empty_donation_amount = ( self::DONATION_TYPE_FIXED === ( $settings['donation_type'] ?? '' ) && empty( $settings['donation_amount'] ) );
		$empty_tax             = ( ! empty( $settings['tax_type'] ) && empty( $settings['tax_rate'] ) );
		if ( $empty_product_price || $empty_donation_amount || $empty_tax ) {
			$errors[ self::ERROR_MESSAGE_GLOBAL ] = $this->get_custom_message( self::ERROR_MESSAGE_GLOBAL );
		}
		if ( $squash_errors && 1 < count( $errors ) ) {
			return array( self::ERROR_MESSAGE_GLOBAL => $this->get_custom_message( self::ERROR_MESSAGE_GLOBAL ) );
		}
		return $errors;
	}

	/** Un campo oculto del formulario. */
	private static function hidden( string $name, $value ): string {
		return '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '">';
	}

	protected function render_form( array $settings ): void {
		if ( 'legacy' !== $this->get_api_method() ) {
			return;
		}
		if ( $this->is_sandbox() ) {
			$action = self::SANDBOX_URL;
			$email  = $settings['sandbox_email'] ?? '';
			$this->add_render_attribute( 'button', 'class', 'digi-pay__button--sandbox' );
		} else {
			$action = self::PROD_URL;
			$email  = $settings['email'] ?? '';
		}
		$target = 'yes' === ( $settings['open_in_new_window'] ?? '' ) ? '_blank' : '_top';
		$type   = (string) ( $settings['type'] ?? '' );
		$fields = '';
		switch ( $type ) {
			case self::PAYMENT_TYPE_DONATION:
				$cmd   = self::CMD_DONATION;
				$price = array( 'amount', self::DONATION_TYPE_FIXED === ( $settings['donation_type'] ?? '' ) ? ( $settings['donation_amount'] ?? '' ) : '' );
				break;
			case self::PAYMENT_TYPE_SUBSCRIPTION:
				$cmd    = self::CMD_SUBSCRIPTION;
				$price  = array( 'a3', $settings['product_price'] ?? '' );
				$cycle  = self::BILLING_CYCLE_TYPES[ $settings['billing_cycle'] ?? '' ] ?? 'M';
				$fields = self::hidden( 'src', 'yes' === ( $settings['auto_renewal'] ?? '' ) ? 1 : 0 ) . self::hidden( 'p3', 1 ) . self::hidden( 't3', $cycle ) . self::hidden( 'no-shipping', 1 );
				break;
			default:
				$cmd    = self::CMD_CHECKOUT;
				$price  = array( 'amount', $settings['product_price'] ?? '' );
				$fields = self::hidden( 'shipping', $this->get_numeric_setting( 'shipping_price' ) ) . self::hidden( 'tax_rate', $this->get_numeric_setting( 'tax_rate' ) ) . self::hidden( 'quantity', $this->get_numeric_setting( 'quantity', 1 ) );
		}
		echo '<form class="digi-pay digi-pay--paypal" action="' . esc_url( $action ) . '" method="post" target="' . esc_attr( $target ) . '" data-digi-pay="paypal">';
		echo self::hidden( 'cmd', $cmd ) . self::hidden( 'business', $email ) . self::hidden( 'lc', 'US' ) . self::hidden( 'item_name', $settings['product_name'] ?? '' ) . self::hidden( 'item_number', $settings['product_sku'] ?? '' ) . self::hidden( 'currency_code', $settings['currency'] ?? '' ) . self::hidden( $price[0], $price[1] ) . self::hidden( 'no_note', 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en hidden().
		echo $fields; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en hidden().
		if ( ! empty( $settings['redirect_after_success']['url'] ) ) {
			echo '<input type="hidden" name="return" value="' . esc_url( $settings['redirect_after_success']['url'] ) . '">';
		}
		$this->print_button();
		$this->print_errors( $this->get_errors() );
		echo '</form>';
	}

	/** Sección «Precio y pagos»: cuenta del vendedor y producto. */
	protected function register_account_section() {
		$this->start_controls_section(
			'section_account',
			array( 'label' => esc_html__( 'Precio y pagos', 'pro-elements' ) )
		);

		$this->add_control(
			'merchant_account',
			array(
				'label'              => esc_html__( 'Cuenta del vendedor', 'pro-elements' ),
				'type'               => \Elementor\Controls_Manager::HIDDEN,
				'default'            => self::API_TYPE_SIMPLE,
				'options'            => array(
					self::API_TYPE_SIMPLE   => esc_html__( 'Predeterminada (simple)', 'pro-elements' ),
					self::API_TYPE_ADVANCED => esc_html__( 'Personalizada (avanzada)', 'pro-elements' ),
				),
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'email',
			array(
				'label'       => esc_html__( 'Cuenta de PayPal', 'pro-elements' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'dynamic'     => array( 'active' => true ),
				'ai'          => array( 'active' => false ),
				'description' => esc_html__( 'Los pagos hechos con este botón se registran en esta cuenta.', 'pro-elements' ),
				'label_block' => true,
				'condition'   => array( 'merchant_account' => self::API_TYPE_SIMPLE ),
				'placeholder' => 'yours@email.com',
			)
		);

		$this->add_control(
			'sdk_token',
			array(
				'label'       => esc_html__( 'Token del SDK', 'pro-elements' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'dynamic'     => array( 'active' => true ),
				'ai'          => array( 'active' => false ),
				'label_block' => true,
				'condition'   => array( 'merchant_account' => self::API_TYPE_ADVANCED ),
			)
		);

		$this->register_product_controls();

		$this->end_controls_section();
	}

	/** Controles del botón con el icono y el color de PayPal. */
	public function register_paypal_button_controls() {
		parent::register_controls();

		$this->update_control(
			'selected_icon',
			array(
				'default' => array(
					'value'   => 'fab fa-paypal',
					'library' => 'fa-brands',
				),
			)
		);

		$this->update_control( 'background_color', array( 'default' => '#032E82' ) );
	}

	protected function register_controls() {
		$this->register_paypal_button_controls();
	}

	/** Cuenta de pruebas. */
	protected function register_sandbox_controls() {
		$this->add_control(
			'sandbox_email',
			array(
				'label'       => esc_html__( 'Cuenta de correo de pruebas', 'pro-elements' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'dynamic'     => array( 'active' => true ),
				'ai'          => array( 'active' => false ),
				'description' => esc_html__( 'La dirección que te da PayPal al crear un entorno de pruebas con tu cuenta de desarrollador: sirve para probar la compra.', 'pro-elements' ),
				'label_block' => true,
				'condition'   => array( 'sandbox_mode' => 'yes' ),
			)
		);
	}
}
