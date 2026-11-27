<?php
namespace Digitalisimo\Elements\Marca;

use Digitalisimo\Elements\Mcp\Brand_Server;
use Digitalisimo\Elements\Mcp\Legacy_Route;

defined( 'ABSPATH' ) || exit;

/**
 * Sección «Marca propia en el sitio» de la pantalla «Rendimiento» (en cada sitio y en la red), con su propio formulario,
 * acción y nonce, como «WooCommerce en el MCP»: `manage_options` en el sitio y `manage_network_options` en la red, sólo
 * `POST`, `wp_safe_redirect()` a la misma superficie y «Heredar de la red» sólo en el sitio de una red.
 *
 * Muestra el estado en vigor (apagada, encendida, bloqueada con la lista de complementos, o no disponible con el motivo),
 * la casilla para forzarla bajo responsabilidad del administrador (D-5), la dirección del MCP con nombre propio y, si la
 * ruta vieja se siguió usando, cuándo y hasta cuándo funciona (D-7).
 */
final class Settings_Section {
	const ANCHOR              = 'digitalisimo-marca';
	const CAPABILITY          = 'manage_options';
	const NETWORK_CAPABILITY  = 'manage_network_options';
	const ACTION_SAVE         = 'digitalisimo_elements_marca_save';
	const ACTION_SAVE_NETWORK = 'digitalisimo_elements_marca_save_network';
	const NOTICE_PARAM        = 'marca_notice';
	const FRONT_PAGE          = 'digitalisimo-elements-front';
	const LABELS              = array(
		'off' => 'Apagada',
		'on'  => 'Encendida',
	);
	const STATES              = array(
		'off'         => 'Apagada: el sitio se muestra como siempre.',
		'on'          => 'Encendida.',
		'blocked'     => 'Bloqueada por complementos de terceros.',
		'unavailable' => 'No disponible.',
	);
	const NOTICES             = array(
		'saved'         => array( 'success', 'Marca propia guardada en este sitio.' ),
		'network-saved' => array( 'success', 'Marca propia de la red guardada. La aplican los sitios que la heredan.' ),
		'forbidden'     => array( 'error', 'No tienes permiso para cambiar este ajuste.' ),
		'nonce'         => array( 'error', 'El formulario caducó. Vuelve a la sección «Marca propia en el sitio» e inténtalo de nuevo.' ),
		'method'        => array( 'error', 'Este ajuste sólo se guarda desde su formulario.' ),
	);

	public static function init(): void {
		if ( ! function_exists( 'is_admin' ) || ! is_admin() ) {
			return;
		}
		add_action( 'admin_post_' . self::ACTION_SAVE, array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_' . self::ACTION_SAVE_NETWORK, array( __CLASS__, 'handle_save_network' ) );
	}

	public static function url( bool $network = false, array $args = array() ): string {
		$base = $network ? network_admin_url( 'admin.php' ) : admin_url( 'admin.php' );
		return add_query_arg( array_merge( array( 'page' => self::FRONT_PAGE ), $args ), $base ) . '#' . self::ANCHOR;
	}

	public static function nonce_action( string $action ): string {
		return self::ACTION_SAVE_NETWORK === $action ? $action . '|network' : $action . '|' . ( function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 1 );
	}

	public static function render( bool $network ): void {
		echo '<h2 id="' . esc_attr( self::ANCHOR ) . '">' . esc_html( 'Marca propia en el sitio' ) . '</h2>';
		self::render_notice();
		echo '<p>' . esc_html( 'Quita el nombre del motor de lo que ven tus visitantes, tus clientes en el escritorio y las IA por MCP. Por ahora cambia las direcciones del escritorio y del editor y el índice de la API; el resto del sitio llega en la próxima versión. Los datos no se tocan: apagarla deja todo como estaba.' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="digitalisimo-marca-form">';
		echo '<input type="hidden" name="action" value="' . esc_attr( $network ? self::ACTION_SAVE_NETWORK : self::ACTION_SAVE ) . '">';
		wp_nonce_field( self::nonce_action( $network ? self::ACTION_SAVE_NETWORK : self::ACTION_SAVE ) );
		$values = $network ? Toggle::network_values() : Toggle::values();
		$site   = $network ? null : Toggle::site_values();
		$status = $network ? null : Toggle::status();
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row"><label for="digitalisimo-marca-mode">' . esc_html( 'Marca propia' ) . '</label></th><td>';
		echo '<select id="digitalisimo-marca-mode" name="' . esc_attr( Toggle::OPTION ) . '[mode]">';
		foreach ( self::LABELS as $mode => $label ) {
			echo '<option value="' . esc_attr( $mode ) . '"' . selected( $values['mode'], $mode, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p><label><input type="hidden" name="' . esc_attr( Toggle::OPTION ) . '[force]" value="0"><input type="checkbox" name="' . esc_attr( Toggle::OPTION ) . '[force]" value="1"' . checked( $values['force'], true, false ) . '> ' . esc_html( 'Encenderla aunque haya complementos de terceros que extienden el constructor (bajo mi responsabilidad)' ) . '</label></p>';
		if ( $status ) {
			echo '<p class="description digitalisimo-marca-state" data-state="' . esc_attr( $status['state'] ) . '">' . esc_html( 'En vigor en este sitio: ' . self::STATES[ $status['state'] ] . ( '' !== $status['reason'] ? ' ' . $status['reason'] : '' ) ) . '</p>';
			if ( $status['addons'] ) {
				echo '<ul class="digitalisimo-marca-addons">';
				foreach ( $status['addons'] as $addon ) {
					echo '<li>' . esc_html( $addon['name'] . ': ' . $addon['why'] ) . '</li>';
				}
				echo '</ul>';
			}
		}
		if ( $site && is_multisite() ) {
			$field = Toggle::OPTION . '[inherit]';
			$net   = Toggle::network_values();
			echo '<p class="digitalisimo-inheritance"><input type="hidden" name="' . esc_attr( $field ) . '" value="0"><label><input type="checkbox" class="digitalisimo-front-inherit" data-field="digitalisimo-marca-mode" name="' . esc_attr( $field ) . '" value="1"' . checked( $site['inherit'], true, false ) . '> ' . esc_html( 'Heredar de la red' ) . '</label><br>';
			echo '<span class="description">' . esc_html( 'Valor de la red: ' . self::LABELS[ $net['mode'] ] . ( $net['force'] ? ' (forzada)' : '' ) ) . '</span></p>';
		}
		echo '</td></tr>';
		echo '<tr><th scope="row">' . esc_html( 'Servidor MCP' ) . '</th><td><code>' . esc_html( Brand_Server::url() ) . '</code>';
		echo '<p class="description">' . esc_html( 'Las herramientas se llaman digitalisimo-* y los recursos digitalisimo://. Se activa en la pantalla «MCP».' ) . '</p>';
		if ( ! $network && class_exists( Legacy_Route::class ) ) {
			$legacy = Legacy_Route::state();
			$until  = Legacy_Route::until();
			if ( '' !== $legacy['last'] ) {
				echo '<p class="description digitalisimo-marca-legacy">' . esc_html( 'La dirección vieja (/wp-json/elementor/mcp) se usó por última vez el ' . $legacy['last'] . ' y funciona hasta el ' . $until . ( '' !== $legacy['extended'] ? ' (prorrogada porque se seguía usando)' : '' ) . '. Cambia la dirección en tus clientes MCP.' ) . '</p>';
			} else {
				echo '<p class="description">' . esc_html( 'La dirección vieja (/wp-json/elementor/mcp) funciona hasta el ' . $until . ' y no se ha usado desde que se cuenta.' ) . '</p>';
			}
		}
		echo '</td></tr>';
		echo '</tbody></table>';
		echo '<p><button type="submit" class="button button-primary">' . esc_html( $network ? 'Guardar la marca propia de la red' : 'Guardar la marca propia' ) . '</button></p>';
		echo '</form>';
	}

	private static function render_notice(): void {
		$code = isset( $_GET[ self::NOTICE_PARAM ] ) ? sanitize_key( wp_unslash( $_GET[ self::NOTICE_PARAM ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo elige el texto del aviso.
		if ( ! isset( self::NOTICES[ $code ] ) ) {
			return;
		}
		list( $type, $text ) = self::NOTICES[ $code ];
		echo '<div class="notice notice-' . esc_attr( $type ) . ' inline"><p>' . esc_html( $text ) . '</p></div>';
	}

	/** @return array{mode:string,force:bool} lo enviado, limpio. */
	private static function posted(): array {
		$raw = isset( $_POST[ Toggle::OPTION ] ) && is_array( $_POST[ Toggle::OPTION ] ) ? wp_unslash( $_POST[ Toggle::OPTION ] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- el nonce se comprueba antes; Toggle::clean() valida.
		$raw = is_array( $raw ) ? array_map( 'sanitize_text_field', array_map( 'strval', $raw ) ) : array();
		return Toggle::clean( $raw ) + array( 'inherit' => ! empty( $raw['inherit'] ) && '0' !== $raw['inherit'] );
	}

	public static function handle_save(): void {
		self::guard( self::ACTION_SAVE, self::CAPABILITY, false );
		$values = self::posted();
		$save   = array( 'mode' => $values['mode'], 'force' => $values['force'] );
		if ( is_multisite() ) {
			$save['inherit'] = $values['inherit'];
		}
		update_option( Toggle::OPTION, $save, true );
		Toggle::reset();
		// Encendida en un sitio individual: los originales desplegados ya no hacen falta (opción A de DECISIONES).
		if ( 'on' === $save['mode'] && 'on' === Toggle::status( true )['state'] && class_exists( Package::class ) ) {
			Package::remove_originals();
		}
		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( Addons::CACHE );
		}
		wp_safe_redirect( self::url( false, array( self::NOTICE_PARAM => 'saved' ) ) );
		exit;
	}

	public static function handle_save_network(): void {
		self::guard( self::ACTION_SAVE_NETWORK, self::NETWORK_CAPABILITY, true );
		$values = self::posted();
		update_site_option( Toggle::NETWORK_OPTION, array( 'mode' => $values['mode'], 'force' => $values['force'] ) );
		Toggle::reset();
		wp_safe_redirect( self::url( true, array( self::NOTICE_PARAM => 'network-saved' ) ) );
		exit;
	}

	private static function guard( string $action, string $cap, bool $network ): void {
		$fail = static function ( $code ) use ( $network ) {
			wp_safe_redirect( self::url( $network, array( self::NOTICE_PARAM => $code ) ) );
			exit;
		};
		if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sólo se compara.
			$fail( 'method' );
		}
		if ( ( $network && ! is_multisite() ) || ! current_user_can( $cap ) ) {
			$fail( 'forbidden' );
		}
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::nonce_action( $action ) ) ) {
			$fail( 'nonce' );
		}
	}
}
