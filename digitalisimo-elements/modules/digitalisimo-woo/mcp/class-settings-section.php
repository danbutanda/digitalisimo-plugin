<?php
namespace Digitalisimo\Elements\Woo\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * Sección «WooCommerce en el MCP» de la pantalla «Rendimiento» de DIGITALÍSIMO Elements, en cada sitio y en la red
 * (la única pantalla de Elements con ajustes de sitio y de red; ver `Front\Settings_Page`).
 *
 * Un formulario propio (no el de los interruptores de rendimiento: guardar el modo no vacía cachés), con su acción y su
 * nonce: `ACTION_SAVE` en el sitio y `ACTION_SAVE_NETWORK` en la red. Capacidad por acción, nunca por un parámetro de
 * contexto (`manage_options` en el sitio; `is_multisite()` y `manage_network_options` en la red); sólo `POST`;
 * `wp_safe_redirect()` a la misma superficie. «Heredar de la red» sólo en el sitio de una red; el script de herencia de
 * la pantalla (`.digitalisimo-front-inherit` con `data-field`) deshabilita el desplegable heredado.
 *
 * `Woo\Module::load()` llama a `init()` con o sin WooCommerce (la red puede definir el modo aunque su sitio principal no
 * tenga tienda); la pantalla pinta la sección con `render()` si esta clase existe.
 */
final class Settings_Section {
	const ANCHOR              = 'digitalisimo-woo-mcp';
	const CAPABILITY          = 'manage_options';
	const NETWORK_CAPABILITY  = 'manage_network_options';
	const ACTION_SAVE         = 'digitalisimo_elements_woo_mcp_save';
	const ACTION_SAVE_NETWORK = 'digitalisimo_elements_woo_mcp_save_network';
	const NOTICE_PARAM        = 'woo_mcp_notice';
	const FRONT_PAGE          = 'digitalisimo-elements-front';

	const NOTICES = array(
		'saved'         => array( 'success', 'Modo de las herramientas de tienda guardado en este sitio.' ),
		'network-saved' => array( 'success', 'Modo de red de las herramientas de tienda guardado. Lo aplican los sitios que lo heredan.' ),
		'forbidden'     => array( 'error', 'No tienes permiso para cambiar este ajuste.' ),
		'nonce'         => array( 'error', 'El formulario caducó. Vuelve a la sección «WooCommerce en el MCP» e inténtalo de nuevo.' ),
		'method'        => array( 'error', 'Este ajuste sólo se guarda desde su formulario.' ),
	);

	const ORIGINS = array(
		'site'    => 'este sitio',
		'network' => 'la red',
		'default' => 'valor predeterminado del plugin',
	);

	/** @var bool */
	private static $initialized = false;

	/** Ganchos de los guardados; fuera de la administración no engancha nada. */
	public static function init(): void {
		if ( self::$initialized || ! function_exists( 'is_admin' ) || ! is_admin() ) {
			return;
		}
		self::$initialized = true;
		add_action( 'admin_post_' . self::ACTION_SAVE, array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_' . self::ACTION_SAVE_NETWORK, array( __CLASS__, 'handle_save_network' ) );
	}

	/** URL de la pantalla «Rendimiento» en la misma superficie, en la sección. */
	public static function url( bool $network = false, array $args = array() ): string {
		$base = $network ? network_admin_url( 'admin.php' ) : admin_url( 'admin.php' );
		return add_query_arg( array_merge( array( 'page' => self::FRONT_PAGE ), $args ), $base ) . '#' . self::ANCHOR;
	}

	/** Acción del nonce: acción y sitio, o acción y red. */
	public static function nonce_action( string $action ): string {
		if ( self::ACTION_SAVE_NETWORK === $action ) {
			return $action . '|network';
		}
		return $action . '|' . ( function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 1 );
	}

	/** La sección: en el sitio o en la red. */
	public static function render( bool $network ): void {
		echo '<h2 id="' . esc_attr( self::ANCHOR ) . '">' . esc_html( 'WooCommerce en el MCP' ) . '</h2>';
		self::render_notice();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="digitalisimo-woo-mcp-form">';
		echo '<input type="hidden" name="action" value="' . esc_attr( $network ? self::ACTION_SAVE_NETWORK : self::ACTION_SAVE ) . '">';
		wp_nonce_field( self::nonce_action( $network ? self::ACTION_SAVE_NETWORK : self::ACTION_SAVE ) );
		echo '<table class="form-table" role="presentation"><tbody>';
		if ( $network ) {
			Settings::network_fields();
		} else {
			self::site_fields();
		}
		echo '</tbody></table>';
		echo '<p><button type="submit" class="button button-primary">' . esc_html( $network ? 'Guardar el modo de red' : 'Guardar el modo' ) . '</button></p>';
		echo '</form>';
	}

	/** Fila del sitio: desplegable, estado y, en una red, «Heredar de la red». */
	private static function site_fields(): void {
		$inherit_ui = function_exists( 'is_multisite' ) && is_multisite();
		$inherit    = $inherit_ui && Settings::inherits_network();
		$origin     = Settings::origin();
		$mode       = Settings::mode();
		$class      = $inherit ? 'digitalisimo-inherited-value' : ( 'default' === $origin ? 'digitalisimo-default-value' : '' );
		echo '<tr><th scope="row"><label for="digitalisimo-woo-mcp-mode">' . esc_html( 'Herramientas de tienda' ) . '</label></th><td>';
		Settings::select( 'digitalisimo-woo-mcp-mode', Settings::OPTION . '[mode]', $mode, $class );
		echo '<p class="description">' . esc_html( Settings::HELP ) . '</p>';
		echo '<p class="description">' . esc_html( self::state() ) . '</p>';
		if ( 'default' === $origin && ! $inherit ) {
			echo '<p class="description digitalisimo-default-note">' . esc_html( 'Valor predeterminado del plugin.' ) . '</p>';
		}
		if ( $inherit_ui ) {
			$field = Settings::OPTION . '[inherit][mode]';
			echo '<p class="digitalisimo-inheritance"><input type="hidden" name="' . esc_attr( $field ) . '" value="0">';
			echo '<label><input type="checkbox" class="digitalisimo-front-inherit" data-field="digitalisimo-woo-mcp-mode" name="' . esc_attr( $field ) . '" value="1"' . ( $inherit ? ' checked="checked"' : '' ) . '> ' . esc_html( 'Heredar de la red' ) . '</label><br>';
			echo '<span class="description">' . esc_html( 'Valor de la red: ' . Settings::LABELS[ Settings::network_values()['mode'] ] . ' · Origen: ' . self::ORIGINS[ $origin ] ) . '</span></p>';
		}
		echo '</td></tr>';
	}

	/** Estado en vigor: WooCommerce, el MCP del sitio y la dirección del servidor. */
	private static function state(): string {
		$woo = class_exists( '\Digitalisimo\Elements\Woo\Module' ) && \Digitalisimo\Elements\Woo\Module::woocommerce_ok();
		$mcp = (bool) ( function_exists( 'get_option' ) ? get_option( 'elementor_mcp_enabled', false ) : false );
		if ( ! $woo ) {
			return 'En vigor en este sitio: ninguna herramienta de tienda (WooCommerce no está activo o es anterior a la versión ' . \Digitalisimo\Elements\Woo\Module::MIN_WC . ').';
		}
		if ( ! $mcp ) {
			return 'En vigor en este sitio: ninguna herramienta (el MCP de DIGITALÍSIMO Elements está apagado; se activa en su pantalla «MCP»).';
		}
		if ( 'off' === Settings::mode() ) {
			return 'En vigor en este sitio: ninguna herramienta de tienda.';
		}
		return 'En vigor en este sitio: ' . Settings::LABELS[ Settings::mode() ] . '. Servidor MCP: ' . rest_url( 'elementor/mcp' );
	}

	private static function render_notice(): void {
		$code = isset( $_GET[ self::NOTICE_PARAM ] ) ? sanitize_key( wp_unslash( $_GET[ self::NOTICE_PARAM ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo elige el texto del aviso.
		if ( ! isset( self::NOTICES[ $code ] ) ) {
			return;
		}
		list( $type, $text ) = self::NOTICES[ $code ];
		echo '<div class="notice notice-' . esc_attr( $type ) . ' inline"><p>' . esc_html( $text ) . '</p></div>';
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Guardados.

	public static function handle_save(): void {
		self::finish( self::process_save() );
	}

	public static function handle_save_network(): void {
		self::finish( self::process_save_network() );
	}

	/** Guardar en el sitio: sanea y guarda (sin carga automática: sólo lo leen el MCP y esta pantalla). */
	public static function process_save(): array {
		$check = self::check_request( self::ACTION_SAVE, false );
		if ( $check ) {
			return $check;
		}
		$input = isset( $_POST[ Settings::OPTION ] ) && is_array( $_POST[ Settings::OPTION ] ) ? wp_unslash( $_POST[ Settings::OPTION ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- saneado en Settings::sanitize(); nonce en check_request().
		update_option( Settings::OPTION, Settings::sanitize( is_array( $input ) ? $input : array() ), false );
		return array( 'notice' => 'saved' );
	}

	/** Guardar en la red: sólo la opción de red. */
	public static function process_save_network(): array {
		$check = self::check_request( self::ACTION_SAVE_NETWORK, true );
		if ( $check ) {
			return $check;
		}
		$input = isset( $_POST[ Settings::NETWORK_OPTION ] ) && is_array( $_POST[ Settings::NETWORK_OPTION ] ) ? wp_unslash( $_POST[ Settings::NETWORK_OPTION ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- saneado en Settings::save_network(); nonce en check_request().
		Settings::save_network( is_array( $input ) ? $input : array() );
		return array(
			'notice'  => 'network-saved',
			'network' => true,
		);
	}

	/** Capacidad, método y nonce. `[]` si vale, o el rechazo. */
	private static function check_request( string $action, bool $network ): array {
		$allowed = $network ? is_multisite() && current_user_can( self::NETWORK_CAPABILITY ) : current_user_can( self::CAPABILITY );
		if ( ! $allowed ) {
			return array(
				'status'  => 403,
				'notice'  => 'forbidden',
				'network' => $network,
			);
		}
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sólo se compara.
			return array(
				'notice'  => 'method',
				'network' => $network,
			);
		}
		$nonce = isset( $_POST['_wpnonce'] ) && is_string( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::nonce_action( $action ) ) ) {
			return array(
				'status'  => 403,
				'notice'  => 'nonce',
				'network' => $network,
			);
		}
		return array();
	}

	/** Redirige a la sección en la misma superficie; un 403 termina con `wp_die()`. */
	private static function finish( array $result ): void {
		$notice = isset( $result['notice'] ) && isset( self::NOTICES[ $result['notice'] ] ) ? (string) $result['notice'] : '';
		if ( isset( $result['status'] ) && 403 === (int) $result['status'] ) {
			wp_die( esc_html( '' !== $notice ? self::NOTICES[ $notice ][1] : self::NOTICES['forbidden'][1] ), '', array( 'response' => 403, 'back_link' => true ) );
		}
		wp_safe_redirect( self::url( ! empty( $result['network'] ), '' !== $notice ? array( self::NOTICE_PARAM => $notice ) : array() ) );
		exit;
	}
}
