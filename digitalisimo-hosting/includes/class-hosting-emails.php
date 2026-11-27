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
			// «Reenviar accesos» pasa el correo con el que se contrató el servicio; si no, el de facturación del pedido.
			$this->recipient = '' !== (string) ( $data['to'] ?? '' ) ? (string) $data['to'] : $order->get_billing_email();
			$this->placeholders['{domain}'] = (string) ( $data['domain'] ?? '' );
			if ( ! $this->get_recipient() ) return false;
			if ( method_exists( $this, 'setup_locale' ) ) $this->setup_locale();
			$sent = $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			if ( method_exists( $this, 'restore_locale' ) ) $this->restore_locale();
			return (bool) $sent;
		}

		/** Los demás dominios del pedido: dominio => cómo quedó (texto plano). */
		public function extras() { return array_filter( (array) ( $this->data['extras'] ?? array() ), 'is_string' ); }

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
			// Transferencia (2026-11-26): se hace a mano; el correo no dice que el dominio ya está registrado.
			if ( ! empty( $d['has_transfer'] ) ) return ! empty( $d['has_hosting'] ) ? 'Creamos tu cuenta de hosting. Estamos transfiriendo tu dominio: puede tardar hasta 7 días; al llegar lo apuntamos a los nameservers de abajo.' : 'Recibimos tu transferencia de dominio y ya la estamos tramitando: puede tardar hasta 7 días. Al llegar lo apuntamos a los nameservers de abajo.';
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
			echo '</table>';
			// Los demás dominios del pedido, cada uno con su estado (segunda revisión, N2).
			if ( $this->extras() ) {
				echo '<p>' . esc_html( 'Otros dominios de tu pedido:' ) . '</p><ul>';
				foreach ( $this->extras() as $domain => $text ) echo '<li><strong>' . esc_html( $domain ) . '</strong>: ' . esc_html( $text ) . '</li>';
				echo '</ul>';
			}
			if ( ! empty( $this->data['has_hosting'] ) ) echo '<p>Te recomendamos cambiar la contraseña al entrar por primera vez y no compartirla.</p>';
			$extra = $this->get_additional_content();
			if ( $extra ) echo wp_kses_post( wpautop( wptexturize( $extra ) ) );
			do_action( 'woocommerce_email_footer', $this );
			return ob_get_clean();
		}

		public function get_content_plain() {
			$out = strtoupper( $this->get_heading() ) . "\n\n" . $this->intro() . "\n\n";
			foreach ( $this->rows() as $label => $value ) $out .= $label . ': ' . $value . "\n";
			if ( $this->extras() ) { $out .= "\nOtros dominios de tu pedido:\n"; foreach ( $this->extras() as $domain => $text ) $out .= '- ' . $domain . ': ' . $text . "\n"; }
			if ( ! empty( $this->data['has_hosting'] ) ) $out .= "\nTe recomendamos cambiar la contraseña al entrar por primera vez y no compartirla.\n";
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
			$this->description    = 'Avisos al cliente: su servicio vence pronto (30 y 7 días antes; en un plan mensual 7 y 3, en uno trimestral 15 y 7; el dominio, 30 y 7), con el enlace para pagar la renovación; se suspendió por falta de pago o quedó renovado.';
			$this->placeholders   = array( '{domain}' => '', '{aviso}' => '' );
			parent::__construct();
		}

		public function get_default_subject() { return '{aviso}: {domain}'; }
		public function get_default_heading() { return '{aviso}'; }
		public function get_default_additional_content() { return ''; }

		/** El asunto: $type son los días que faltan (30, 15, 7, 3…), «suspended» o «renewed»; $what, hosting o domain. */
		public static function label( $type, $what = 'hosting' ) {
			$thing = 'domain' === $what ? 'Tu dominio' : 'Tu hosting';
			if ( ctype_digit( (string) $type ) ) return $thing . ' vence en ' . (int) $type . ' días';
			$labels = array( 'suspended' => $thing . ' está suspendido', 'renewed' => $thing . ' quedó renovado' );
			return $labels[ $type ] ?? $thing;
		}

		public function trigger( $order, array $data = array() ) {
			if ( ! $order || ! $this->is_enabled() ) return false;
			$this->object    = $order;
			$this->data      = $data;
			$this->recipient = $order->get_billing_email();
			$this->placeholders['{domain}'] = (string) ( $data['domain'] ?? '' );
			$this->placeholders['{aviso}'] = self::label( (string) ( $data['type'] ?? '' ), (string) ( $data['what'] ?? 'hosting' ) );
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
				default:
					// El dominio es anual; el hosting, del periodo de su plan (mensual, trimestral…).
					if ( 'domain' === ( $d['what'] ?? '' ) ) return 'Tu dominio ' . $domain . ' vence el ' . $date . '. Para no perderlo, paga su renovación por un año.';
					// El pedido lleva también el dominio (vencen el mismo día): se dice, para que el aviso del dominio no se pierda.
					$with = ! empty( $d['with_domain'] );
					return 'Tu servicio ' . $domain . ' vence el ' . $date . '. Para no perder tu sitio' . ( $with ? ' ni tu dominio' : '' ) . ', paga la renovación' . ( ! empty( $d['period'] ) ? ' (' . ( $with ? 'hosting ' : '' ) . strtolower( (string) $d['period'] ) . ( $with ? ' y dominio por un año' : '' ) . ')' : ( $with ? ' (hosting y dominio)' : '' ) ) . ( ! empty( $d['grace'] ) ? '; si no se paga, la cuenta se suspende ' . (int) $d['grace'] . ' días después del vencimiento' . ( $with ? ' y el dominio puede perderse' : '' ) . '.' : '.' );
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
