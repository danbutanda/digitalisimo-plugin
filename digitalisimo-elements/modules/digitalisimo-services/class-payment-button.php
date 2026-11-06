<?php
namespace Digitalisimo\Elements\Services;

defined( 'ABSPATH' ) || exit;

// Base genérica del núcleo: sin ella (un núcleo que aún no la cargó) la clase no se declara y el módulo omite el widget.
if ( ! class_exists( '\Elementor\Widget_Button' ) ) {
	return;
}

/**
 * Base de los botones de pago propios (C9 de `docs/PRO_WIDGETS_OBJECTIVE.md`, contrato `docs/pro/c9.md`): Botón de
 * PayPal (`digitalisimo-paypal-button`) y Botón de Stripe (`digitalisimo-stripe-button`). Deriva de la base de los
 * botones de pago de PRO Elements (`modules/payments/classes/payment-button.php`, GPL): extiende el widget Botón del
 * núcleo (`\Elementor\Widget_Button` con `Button_Trait`) y repite sus secciones, controles, quitas y cambios con los
 * mismos nombres, valores por defecto, opciones y condiciones, así que el mapa de conversión es todo `keep`.
 *
 * Propio frente a PRO:
 * - un `<button type="submit">` real dentro de su formulario (sin `role="button"` redundante), con las clases del
 *   botón del núcleo (`elementor-button`, tamaño y animación) y las propias `digi-pay__button`;
 * - los mensajes de error, con las clases de mensaje del núcleo (`elementor-message elementor-message-danger`) y las
 *   propias `digi-pay__message digi-pay__message--<id>`, ocultos con `hidden` y `role="alert"`; el script propio
 *   (`assets/js/payment-button.js`, sin jQuery) los muestra al pulsar;
 * - textos visibles y del panel en español (dominio `pro-elements`); el mapa completa los textos por defecto de PRO para
 *   lo que se convierte, así un botón de PRO sin cambios conserva los suyos.
 *
 * Ninguna clave ni secreto llega al navegador: las claves de Stripe se quedan en las opciones del sitio y el token SDK
 * de PayPal (control oculto de PRO) nunca se pinta.
 */
abstract class Payment_Button extends \Elementor\Widget_Button {

	// Tipos de pago.
	const PAYMENT_TYPE_CHECKOUT     = 'checkout';
	const PAYMENT_TYPE_SUBSCRIPTION = 'subscription';
	const PAYMENT_TYPE_DONATION     = 'donation';

	// Ciclos de facturación.
	const BILLING_CYCLE_DAYS   = 'days';
	const BILLING_CYCLE_WEEKS  = 'weeks';
	const BILLING_CYCLE_MONTHS = 'months';
	const BILLING_CYCLE_YEARS  = 'years';

	// Importe de la donación.
	const DONATION_TYPE_ANY   = 'any';
	const DONATION_TYPE_FIXED = 'fixed';

	// Mensajes de error.
	const ERROR_MESSAGE_GLOBAL         = 'global';
	const ERROR_MESSAGE_PAYMENT_METHOD = 'payment';

	/** Nombre del servicio de pago (etiquetas del panel). */
	abstract protected function get_merchant_name();

	/** Sección «Precio y pagos». */
	abstract protected function register_account_section();

	/** Controles del modo de prueba. */
	abstract protected function register_sandbox_controls();

	/** Variante del formulario (`paypal`, `stripe`): clase `digi-pay--<variante>`. */
	abstract protected function get_variant();

	public function get_categories() {
		return array( 'digitalisimo' );
	}

	/** Hoja y script compartidos de los dos botones de pago (`digitalisimo-payment-button`). */
	public function get_style_depends() {
		return Module::style_depends( $this->get_name() );
	}

	public function get_script_depends() {
		return Module::script_depends( $this->get_name() );
	}

	// Controles propios tras el tipo de pago.
	protected function after_product_type() {}

	// Controles propios tras «Mensajes personalizados».
	protected function after_custom_messages_toggle() {}

	// Textos y marcadores de los mensajes de error propios de un servicio.
	protected function update_error_massages() {}

	/** Monedas admitidas. */
	protected function get_currencies() {
		$out = array();
		foreach ( array( 'AUD', 'CAD', 'CZK', 'DKK', 'EUR', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'GBP', 'RUB', 'SGD', 'SEK', 'CHF', 'TWD', 'THB', 'TRY', 'USD' ) as $code ) {
			$out[ $code ] = $code;
		}
		return $out;
	}

	/** Mensajes de error por defecto. */
	protected function get_default_error_messages() {
		return array(
			self::ERROR_MESSAGE_GLOBAL         => esc_html__( 'Se produjo un error.', 'pro-elements' ),
			self::ERROR_MESSAGE_PAYMENT_METHOD => esc_html__( 'No hay ningún método de pago conectado. Contacta con el vendedor.', 'pro-elements' ),
		);
	}

	/** Texto de un mensaje (`error_message_<id>`): el del usuario o el de por defecto. */
	protected function get_custom_message( $id ) {
		$message = $this->get_settings_for_display( 'error_message_' . $id );
		if ( ! empty( $message ) ) {
			return $message;
		}
		$error_messages = $this->get_default_error_messages();
		return ! empty( $error_messages[ $id ] ) ? $error_messages[ $id ] : esc_html__( 'Error desconocido.', 'pro-elements' );
	}

	/** Detalles del producto. */
	protected function register_product_controls() {
		$this->add_control(
			'type',
			array(
				'label'     => esc_html__( 'Tipo de transacción', 'pro-elements' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'checkout',
				'options'   => array(
					self::PAYMENT_TYPE_CHECKOUT     => esc_html__( 'Pago', 'pro-elements' ),
					self::PAYMENT_TYPE_DONATION     => esc_html__( 'Donación', 'pro-elements' ),
					self::PAYMENT_TYPE_SUBSCRIPTION => esc_html__( 'Suscripción', 'pro-elements' ),
				),
				'separator' => 'before',
			)
		);

		$this->after_product_type();

		$this->add_control(
			'product_name',
			array(
				'label'       => esc_html__( 'Nombre del artículo', 'pro-elements' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'dynamic'     => array( 'active' => true ),
				'label_block' => true,
			)
		);

		$this->add_control(
			'product_sku',
			array(
				'label'   => esc_html__( 'SKU', 'pro-elements' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'dynamic' => array( 'active' => true ),
				'ai'      => array( 'active' => false ),
			)
		);

		$this->add_control(
			'product_price',
			array(
				'label'     => esc_html__( 'Precio', 'pro-elements' ),
				'type'      => \Elementor\Controls_Manager::NUMBER,
				'default'   => '0.00',
				'dynamic'   => array( 'active' => true ),
				'condition' => array( 'type!' => self::PAYMENT_TYPE_DONATION ),
			)
		);

		$this->add_control(
			'donation_type',
			array(
				'label'     => esc_html__( 'Importe de la donación', 'pro-elements' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => self::DONATION_TYPE_FIXED,
				'options'   => array(
					self::DONATION_TYPE_ANY   => esc_html__( 'Cualquier importe', 'pro-elements' ),
					self::DONATION_TYPE_FIXED => esc_html__( 'Fijo', 'pro-elements' ),
				),
				'condition' => array( 'type' => self::PAYMENT_TYPE_DONATION ),
			)
		);

		$this->add_control(
			'donation_amount',
			array(
				'label'     => esc_html__( 'Importe', 'pro-elements' ),
				'type'      => \Elementor\Controls_Manager::NUMBER,
				'default'   => '1',
				'dynamic'   => array( 'active' => true ),
				'condition' => array(
					'type'          => self::PAYMENT_TYPE_DONATION,
					'donation_type' => self::DONATION_TYPE_FIXED,
				),
			)
		);

		$this->add_control(
			'currency',
			array(
				'label'   => esc_html__( 'Moneda', 'pro-elements' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'USD',
				'options' => $this->get_currencies(),
			)
		);

		$this->add_control(
			'billing_cycle',
			array(
				'label'     => esc_html__( 'Ciclo de facturación', 'pro-elements' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => self::BILLING_CYCLE_MONTHS,
				'options'   => array(
					self::BILLING_CYCLE_DAYS   => esc_html__( 'Diario', 'pro-elements' ),
					self::BILLING_CYCLE_WEEKS  => esc_html__( 'Semanal', 'pro-elements' ),
					self::BILLING_CYCLE_MONTHS => esc_html__( 'Mensual', 'pro-elements' ),
					self::BILLING_CYCLE_YEARS  => esc_html__( 'Anual', 'pro-elements' ),
				),
				'condition' => array( 'type' => self::PAYMENT_TYPE_SUBSCRIPTION ),
			)
		);

		$this->add_control(
			'auto_renewal',
			array(
				'type'      => \Elementor\Controls_Manager::SWITCHER,
				'label'     => esc_html__( 'Renovación automática', 'pro-elements' ),
				'default'   => 'yes',
				'label_off' => esc_html__( 'No', 'pro-elements' ),
				'label_on'  => esc_html__( 'Sí', 'pro-elements' ),
				'condition' => array( 'type' => self::PAYMENT_TYPE_SUBSCRIPTION ),
			)
		);

		$this->add_control(
			'quantity',
			array(
				'label'     => esc_html__( 'Cantidad', 'pro-elements' ),
				'type'      => \Elementor\Controls_Manager::NUMBER,
				'default'   => 1,
				'condition' => array( 'type' => self::PAYMENT_TYPE_CHECKOUT ),
			)
		);

		$this->add_control(
			'shipping_price',
			array(
				'label'     => esc_html__( 'Precio del envío', 'pro-elements' ),
				'type'      => \Elementor\Controls_Manager::NUMBER,
				'default'   => 0,
				'dynamic'   => array( 'active' => true ),
				'condition' => array( 'type' => self::PAYMENT_TYPE_CHECKOUT ),
			)
		);

		$this->add_control(
			'tax_type',
			array(
				'label'     => esc_html__( 'Impuesto', 'pro-elements' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => '',
				'options'   => array(
					''           => esc_html__( 'Ninguno', 'pro-elements' ),
					'percentage' => esc_html__( 'Porcentaje', 'pro-elements' ),
				),
				'condition' => array( 'type' => self::PAYMENT_TYPE_CHECKOUT ),
			)
		);

		$this->add_control(
			'tax_rate',
			array(
				'label'     => esc_html__( 'Porcentaje de impuesto', 'pro-elements' ),
				'type'      => \Elementor\Controls_Manager::NUMBER,
				'default'   => '0',
				'dynamic'   => array( 'active' => true ),
				'condition' => array(
					'type'     => self::PAYMENT_TYPE_CHECKOUT,
					'tax_type' => 'percentage',
				),
			)
		);
	}

	/** Opciones adicionales. */
	protected function register_settings_section() {
		$this->start_controls_section(
			'section_settings',
			array( 'label' => esc_html__( 'Opciones adicionales', 'pro-elements' ) )
		);

		$this->add_control(
			'redirect_after_success',
			array(
				'label'       => esc_html__( 'Redirigir tras el pago', 'pro-elements' ),
				'type'        => \Elementor\Controls_Manager::URL,
				'options'     => false,
				'placeholder' => esc_html__( 'Elige una página o escribe una URL', 'pro-elements' ),
				'dynamic'     => array( 'active' => true ),
				'label_block' => true,
				'render_type' => 'none',
			)
		);

		$this->add_control(
			'sandbox_mode',
			array(
				'type'      => \Elementor\Controls_Manager::SWITCHER,
				'label'     => esc_html__( 'Modo de prueba', 'pro-elements' ),
				'default'   => 'no',
				'label_off' => esc_html__( 'No', 'pro-elements' ),
				'label_on'  => esc_html__( 'Sí', 'pro-elements' ),
			)
		);

		$this->register_sandbox_controls();

		$this->add_control(
			'open_in_new_window',
			array(
				'type'      => \Elementor\Controls_Manager::SWITCHER,
				/* translators: %s: servicio de pago. */
				'label'     => sprintf( esc_html__( 'Abrir %s en una pestaña nueva', 'pro-elements' ), $this->get_merchant_name() ),
				'default'   => 'yes',
				'label_off' => esc_html__( 'No', 'pro-elements' ),
				'label_on'  => esc_html__( 'Sí', 'pro-elements' ),
			)
		);

		$this->add_control(
			'custom_messages',
			array(
				'label'   => esc_html__( 'Mensajes personalizados', 'pro-elements' ),
				'type'    => \Elementor\Controls_Manager::SWITCHER,
				'default' => '',
			)
		);

		$this->after_custom_messages_toggle();

		$error_messages = $this->get_default_error_messages();

		$this->add_control(
			'error_message_' . self::ERROR_MESSAGE_GLOBAL,
			array(
				'label'       => esc_html__( 'Mensaje de error', 'pro-elements' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => $error_messages[ self::ERROR_MESSAGE_GLOBAL ],
				'placeholder' => $error_messages[ self::ERROR_MESSAGE_GLOBAL ],
				'label_block' => true,
				'condition'   => array( 'custom_messages!' => '' ),
				'dynamic'     => array( 'active' => true ),
			)
		);

		$this->add_control(
			'error_message_' . self::ERROR_MESSAGE_PAYMENT_METHOD,
			array(
				/* translators: %s: servicio de pago. */
				'label'       => sprintf( esc_html__( '%s no conectado', 'pro-elements' ), $this->get_merchant_name() ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => $error_messages[ self::ERROR_MESSAGE_PAYMENT_METHOD ],
				'placeholder' => $error_messages[ self::ERROR_MESSAGE_PAYMENT_METHOD ],
				'label_block' => true,
				'condition'   => array( 'custom_messages!' => '' ),
				'dynamic'     => array( 'active' => true ),
			)
		);

		$this->update_error_massages();

		$this->end_controls_section();
	}

	/** El botón del núcleo, sin tipo, enlace ni tamaño, con el texto y los colores de un botón de pago. */
	protected function register_button_controls() {
		parent::register_controls();

		$this->remove_control( 'button_type' );
		$this->remove_control( 'link' );
		$this->remove_control( 'size' );

		$this->update_control( 'text', array( 'default' => esc_html__( 'Comprar ahora', 'pro-elements' ) ) );
		$this->update_control( 'button_text_color', array( 'default' => '#FFF' ) );
		$this->update_control( 'border_border', array( 'default' => 'none' ) );
	}

	/** Tipografía y colores de los mensajes. */
	protected function register_messages_style_section() {
		$this->start_controls_section(
			'section_messages_style',
			array(
				'label' => esc_html__( 'Mensajes', 'pro-elements' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'message_typography',
				'global'   => array( 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_TEXT ),
				'selector' => '{{WRAPPER}} .digi-pay__message',
			)
		);

		$this->add_control(
			'message_color_' . self::ERROR_MESSAGE_GLOBAL,
			array(
				'label'     => esc_html__( 'Color del mensaje de error', 'pro-elements' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .digi-pay__message--' . self::ERROR_MESSAGE_GLOBAL => 'color: {{COLOR}};' ),
			)
		);

		$this->add_control(
			'message_color_' . self::ERROR_MESSAGE_PAYMENT_METHOD,
			array(
				/* translators: %s: servicio de pago. */
				'label'     => sprintf( esc_html__( 'Color de «%s no conectado»', 'pro-elements' ), $this->get_merchant_name() ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .digi-pay__message--' . self::ERROR_MESSAGE_PAYMENT_METHOD => 'color: {{COLOR}};' ),
			)
		);

		$this->end_controls_section();
	}

	protected function register_controls() {
		$this->register_account_section();
		$this->register_button_controls();
		$this->register_settings_section();
		$this->register_messages_style_section();
	}

	/** El editor repinta desde el servidor (como PRO): la plantilla del botón del núcleo pintaría un enlace. */
	protected function content_template() {}

	/** ¿Está activo el marcado optimizado del núcleo? */
	protected function optimized_markup(): bool {
		$plugin      = class_exists( '\Elementor\Plugin' ) ? \Elementor\Plugin::$instance : null;
		$experiments = is_object( $plugin ) && isset( $plugin->experiments ) ? $plugin->experiments : null;
		return is_object( $experiments ) && method_exists( $experiments, 'is_feature_active' ) && $experiments->is_feature_active( 'e_optimized_markup' );
	}

	/** ¿Se está editando en el constructor? */
	protected function is_edit_mode(): bool {
		$plugin = class_exists( '\Elementor\Plugin' ) ? \Elementor\Plugin::$instance : null;
		$editor = is_object( $plugin ) && isset( $plugin->editor ) ? $plugin->editor : null;
		return is_object( $editor ) && method_exists( $editor, 'is_edit_mode' ) && $editor->is_edit_mode();
	}

	/** ¿Modo de prueba? */
	protected function is_sandbox() {
		return 'yes' === $this->get_settings_for_display( 'sandbox_mode' );
	}

	/** Atributos del botón del núcleo (clases de botón, tamaño, animación e id), como PRO, sin `role`. */
	protected function prepare_button( array $settings ): void {
		$this->add_render_attribute( 'button', 'class', array( 'elementor-button', 'digi-pay__button' ) );
		if ( ! empty( $settings['button_css_id'] ) ) {
			$this->add_render_attribute( 'button', 'id', $settings['button_css_id'] );
		}
		$this->add_render_attribute( 'button', 'class', 'elementor-size-' . ( ! empty( $settings['size'] ) ? $settings['size'] : 'sm' ) );
		if ( ! empty( $settings['hover_animation'] ) ) {
			$this->add_render_attribute( 'button', 'class', 'elementor-animation-' . $settings['hover_animation'] );
		}
		$this->add_render_attribute( 'button', 'type', 'submit' );
	}

	/** El `<button type="submit">` con el texto y el icono del botón del núcleo. */
	protected function print_button(): void {
		echo '<button ';
		$this->print_render_attribute_string( 'button' );
		echo '>';
		$this->render_text();
		echo '</button>';
	}

	/** Mensajes de error, ocultos hasta que se pulsa. */
	protected function print_errors( array $errors ): void {
		foreach ( $errors as $type => $message ) {
			$type = preg_replace( '/[^a-z]/', '', (string) $type );
			echo '<div class="elementor-message elementor-message-danger digi-pay__message digi-pay__message--' . esc_attr( $type ) . '" role="alert" hidden>' . esc_html( $message ) . '</div>';
		}
	}

	/** Formulario del servicio. */
	abstract protected function render_form( array $settings ): void;

	protected function render() {
		$settings  = $this->get_settings_for_display();
		$optimized = $this->optimized_markup();
		$this->prepare_button( $settings );
		if ( ! $optimized ) {
			echo '<div class="elementor-button-wrapper">';
		}
		$this->render_form( $settings );
		if ( ! $optimized ) {
			echo '</div>';
		}
	}

	/** Resumen en Markdown (producto, precio, tipo, botón y modo de prueba), sin enlaces. */
	public function render_markdown(): string {
		$settings = $this->get_settings_for_display();
		$lines    = array();
		$plain    = static function ( $value ) {
			return trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $value ) ) );
		};
		if ( '' !== $plain( $settings['product_name'] ?? '' ) ) {
			$lines[] = '- **' . esc_html__( 'Producto', 'pro-elements' ) . ':** ' . $plain( $settings['product_name'] );
		}
		$price    = (string) ( $settings['product_price'] ?? ( $settings['stripe_product_price'] ?? '' ) );
		$currency = (string) ( $settings['currency'] ?? ( $settings['stripe_currency'] ?? '' ) );
		if ( '' !== $price || '' !== $currency ) {
			$lines[] = '- **' . esc_html__( 'Precio', 'pro-elements' ) . ':** ' . trim( $price . ' ' . strtoupper( $currency ) );
		}
		if ( '' !== $plain( $settings['text'] ?? '' ) ) {
			$lines[] = '- **' . esc_html__( 'Botón', 'pro-elements' ) . ':** ' . $plain( $settings['text'] );
		}
		if ( $this->is_sandbox() ) {
			$lines[] = '- **' . esc_html__( 'Modo', 'pro-elements' ) . ':** ' . esc_html__( 'prueba', 'pro-elements' );
		}
		return implode( "\n", $lines );
	}
}
