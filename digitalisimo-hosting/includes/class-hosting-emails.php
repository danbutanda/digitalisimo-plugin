<?php
defined( 'ABSPATH' ) || exit;

/**
 * Correos de WooCommerce de la venta automática de hosting. Se cargan desde el filtro `woocommerce_email_classes`
 * (cuando WC_Email ya existe) y se activan, desactivan y editan (asunto, encabezado y contenido adicional) en
 * WooCommerce → Ajustes → Correos.
 */
if ( class_exists( 'WC_Email' ) && ! class_exists( 'Digitalisimo_Hosting_Email_Access' ) ) {

	/** Accesos al cliente: su dominio, el panel (puerto 2003), su usuario y su contraseña inicial. */
	class Digitalisimo_Hosting_Email_Access extends WC_Email {
		public $data = array();

		public function __construct() {
			$this->id             = 'digitalisimo_hosting_access';
			$this->customer_email = true;
			$this->title          = 'Accesos de hosting (DIGITALÍSIMO)';
			$this->description    = 'Se envía al cliente cuando su cuenta de hosting y su dominio quedan listos: dominio, panel, usuario y contraseña inicial.';
			$this->placeholders   = array( '{domain}' => '' );
			parent::__construct();
		}

		public function get_default_subject() { return 'Tus accesos de hosting para {domain}'; }
		public function get_default_heading() { return 'Tu hosting está listo'; }
		public function get_default_additional_content() { return 'Si tienes dudas, responde a este correo.'; }

		public function trigger( $order, array $data = array() ) {
			if ( ! $order || ! $this->is_enabled() ) return false;
			$this->object    = $order;
			$this->data      = $data;
			$this->recipient = $order->get_billing_email();
			$this->placeholders['{domain}'] = (string) ( $data['domain'] ?? '' );
			if ( ! $this->get_recipient() ) return false;
			if ( method_exists( $this, 'setup_locale' ) ) $this->setup_locale();
			$sent = $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			if ( method_exists( $this, 'restore_locale' ) ) $this->restore_locale();
			return (bool) $sent;
		}

		/** Las filas del correo: etiqueta => valor (texto plano; se escapan al pintarse). */
		public function rows() {
			$d = $this->data; $rows = array( 'Dominio' => (string) ( $d['domain'] ?? '' ) );
			if ( ! empty( $d['has_hosting'] ) ) {
				$rows['Panel de control'] = (string) ( $d['panel_url'] ?? '' );
				$rows['Usuario'] = (string) ( $d['user'] ?? '' );
				$rows['Contraseña'] = '' !== (string) ( $d['password'] ?? '' ) ? (string) $d['password'] : 'Usa «¿Olvidaste tu contraseña?» en el panel';
			}
			if ( ! empty( $d['nameservers'] ) ) $rows['Nameservers'] = implode( ', ', (array) $d['nameservers'] );
			return $rows;
		}

		private function intro() {
			$d = $this->data;
			if ( ! empty( $d['has_hosting'] ) && ! empty( $d['has_domain'] ) ) return 'Registramos tu dominio y creamos tu cuenta de hosting. El dominio puede tardar unas horas en apuntar al servidor.';
			if ( ! empty( $d['has_hosting'] ) ) return 'Creamos tu cuenta de hosting. Apunta tu dominio a los nameservers de abajo para que tu sitio cargue desde nuestro servidor.';
			return 'Registramos tu dominio y lo apuntamos a nuestros nameservers.';
		}

		public function get_content_html() {
			ob_start();
			do_action( 'woocommerce_email_header', $this->get_heading(), $this );
			echo '<p>' . esc_html( $this->intro() ) . '</p><table cellspacing="0" cellpadding="6" border="1" style="width:100%;border-collapse:collapse">';
			foreach ( $this->rows() as $label => $value ) {
				$cell = preg_match( '#^https://#', $value ) ? '<a href="' . esc_url( $value ) . '">' . esc_html( $value ) . '</a>' : '<code>' . esc_html( $value ) . '</code>';
				echo '<tr><th scope="row" style="text-align:left">' . esc_html( $label ) . '</th><td>' . $cell . '</td></tr>';
			}
			echo '</table><p>Te recomendamos cambiar la contraseña al entrar por primera vez y no compartirla.</p>';
			$extra = $this->get_additional_content();
			if ( $extra ) echo wp_kses_post( wpautop( wptexturize( $extra ) ) );
			do_action( 'woocommerce_email_footer', $this );
			return ob_get_clean();
		}

		public function get_content_plain() {
			$out = strtoupper( $this->get_heading() ) . "\n\n" . $this->intro() . "\n\n";
			foreach ( $this->rows() as $label => $value ) $out .= $label . ': ' . $value . "\n";
			$out .= "\nTe recomendamos cambiar la contraseña al entrar por primera vez y no compartirla.\n";
			$extra = $this->get_additional_content();
			if ( $extra ) $out .= "\n" . wp_strip_all_tags( wptexturize( $extra ) ) . "\n";
			return $out;
		}
	}

	/** Renovación: aviso a 30 y 7 días con el enlace de pago, suspensión por falta de pago y confirmación de renovación. */
	class Digitalisimo_Hosting_Email_Renewal extends WC_Email {
		public $data = array();

		public function __construct() {
			$this->id             = 'digitalisimo_hosting_renewal';
			$this->customer_email = true;
			$this->title          = 'Renovación de hosting (DIGITALÍSIMO)';
			$this->description    = 'Avisos al cliente: su servicio vence en 30 y en 7 días (con el enlace para pagar la renovación), se suspendió por falta de pago o quedó renovado.';
			$this->placeholders   = array( '{domain}' => '', '{aviso}' => '' );
			parent::__construct();
		}

		public function get_default_subject() { return '{aviso}: {domain}'; }
		public function get_default_heading() { return '{aviso}'; }
		public function get_default_additional_content() { return ''; }

		public static function label( $type ) {
			$labels = array( '30' => 'Tu hosting vence en 30 días', '7' => 'Tu hosting vence en 7 días', 'suspended' => 'Tu hosting está suspendido', 'renewed' => 'Tu hosting quedó renovado' );
			return $labels[ $type ] ?? 'Tu hosting';
		}

		public function trigger( $order, array $data = array() ) {
			if ( ! $order || ! $this->is_enabled() ) return false;
			$this->object    = $order;
			$this->data      = $data;
			$this->recipient = $order->get_billing_email();
			$this->placeholders['{domain}'] = (string) ( $data['domain'] ?? '' );
			$this->placeholders['{aviso}'] = self::label( (string) ( $data['type'] ?? '' ) );
			if ( ! $this->get_recipient() ) return false;
			if ( method_exists( $this, 'setup_locale' ) ) $this->setup_locale();
			$sent = $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			if ( method_exists( $this, 'restore_locale' ) ) $this->restore_locale();
			return (bool) $sent;
		}

		public function message() {
			$d = $this->data; $domain = (string) ( $d['domain'] ?? '' );
			$date = Digitalisimo_Hosting_Provisioning::date( (int) ( $d['expires'] ?? 0 ) );
			switch ( (string) ( $d['type'] ?? '' ) ) {
				case 'renewed': return 'Recibimos tu pago: ' . $domain . ' queda renovado hasta el ' . $date . '. ¡Gracias!';
				case 'suspended': return 'Tu servicio ' . $domain . ' venció el ' . $date . ' y lo suspendimos por falta de pago. Al pagar la renovación lo reactivamos automáticamente.';
				default: return 'Tu servicio ' . $domain . ' vence el ' . $date . '. Para no perder tu sitio ni tu dominio, paga la renovación anual' . ( ! empty( $d['grace'] ) ? '; si no se paga, la cuenta se suspende ' . (int) $d['grace'] . ' días después del vencimiento.' : '.' );
			}
		}

		public function get_content_html() {
			ob_start();
			do_action( 'woocommerce_email_header', $this->get_heading(), $this );
			echo '<p>' . esc_html( $this->message() ) . '</p>';
			if ( ! empty( $this->data['pay_url'] ) ) echo '<p><a class="button" href="' . esc_url( $this->data['pay_url'] ) . '">Pagar la renovación</a></p><p>' . esc_html( $this->data['pay_url'] ) . '</p>';
			$extra = $this->get_additional_content();
			if ( $extra ) echo wp_kses_post( wpautop( wptexturize( $extra ) ) );
			do_action( 'woocommerce_email_footer', $this );
			return ob_get_clean();
		}

		public function get_content_plain() {
			$out = strtoupper( $this->get_heading() ) . "\n\n" . $this->message() . "\n";
			if ( ! empty( $this->data['pay_url'] ) ) $out .= "\nPagar la renovación: " . $this->data['pay_url'] . "\n";
			$extra = $this->get_additional_content();
			if ( $extra ) $out .= "\n" . wp_strip_all_tags( wptexturize( $extra ) ) . "\n";
			return $out;
		}
	}
}
