<?php
namespace Digitalisimo\Elements\Import\Kits;

defined( 'ABSPATH' ) || exit;

/**
 * Guardas del importador heredado 2.0 (§7.6, D4, D19).
 *
 * El 2.0 instala y activa plugins, cambia el tema y los experimentos y descarga kits por URL. Con el núcleo incluido
 * se corta en cada camino, con un mensaje que manda al importador de kits:
 * - AJAX `elementor_import_kit`, `elementor_import_kit__runner`, `elementor_upload_kit` y `elementor_export_kit`, en
 *   `admin_init` con prioridad 9 (antes que el módulo heredado), con 409;
 * - `admin_post_elementor_revert_kit`, con prioridad 1, con `wp_die()` (ahí el núcleo no captura nada, V-4);
 * - las acciones `elementor/import-export/{import,export,revert}-kit` con prioridad 1, como respaldo.
 * WP-CLI lo guarda `Cli_Guard`. Con el plugin Elementor original activo nada de esto se instala: manda él (D19).
 */
final class Legacy_Guard {
	const AJAX = array( 'elementor_import_kit', 'elementor_import_kit__runner', 'elementor_upload_kit', 'elementor_export_kit' );

	const MESSAGE = 'DIGITALÍSIMO Elements importa y exporta kits desde Herramientas → Importar/Exportar (o con «wp digitalisimo-elements kit»). El flujo antiguo de kits está desactivado porque instalaba plugins y cambiaba el tema.';

	/** ¿Se instalan las guardas? Sólo con el núcleo incluido (D19). */
	public static function applies(): bool {
		return class_exists( '\Digitalisimo_Elements_Core' ) && \Digitalisimo_Elements_Core::bundled();
	}

	/** Registra las guardas; coste cero fuera de esas acciones. */
	public static function register(): void {
		if ( ! self::applies() ) {
			return;
		}
		add_action( 'admin_init', array( __CLASS__, 'ajax' ), 9 );
		add_action( 'admin_post_elementor_revert_kit', array( __CLASS__, 'revert' ), 1 );
		add_action( 'elementor/import-export/import-kit', array( __CLASS__, 'abort' ), 1 );
		add_action( 'elementor/import-export/export-kit', array( __CLASS__, 'abort' ), 1 );
		add_action( 'elementor/import-export/revert-kit', array( __CLASS__, 'abort_revert' ), 1 );
	}

	/** `admin_init` (9): las acciones AJAX del 2.0 responden 409. */
	public static function ajax(): void {
		if ( ! function_exists( 'wp_doing_ajax' ) || ! wp_doing_ajax() || ! isset( $_POST['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- sólo se rechaza.
			return;
		}
		$action = sanitize_key( wp_unslash( (string) $_POST['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( in_array( $action, self::AJAX, true ) ) {
			wp_send_json_error(
				array(
					'message' => self::MESSAGE,
					'tools'   => admin_url( 'admin.php?page=elementor-tools#tab-import-export-kit' ),
				),
				409
			);
		}
	}

	/** `admin_post_elementor_revert_kit` (1). */
	public static function revert(): void {
		wp_die(
			esc_html( self::MESSAGE ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=elementor-tools#tab-import-export-kit' ) ) . '">' . esc_html__( 'Ir a Herramientas', 'pro-elements' ) . '</a>',
			esc_html__( 'Kits', 'pro-elements' ),
			array( 'response' => 409 )
		);
	}

	/**
	 * `elementor/import-export/{import,export}-kit` (1): respaldo si algo llega al 2.0. El AJAX heredado sólo captura
	 * `\Error` (V-4) y WP-CLI captura los dos.
	 *
	 * @throws \Error Siempre.
	 */
	public static function abort(): void {
		throw new \Error( self::MESSAGE ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}

	/** `elementor/import-export/revert-kit` (1): la reversión del 2.0 no captura nada; se corta con `wp_die()`. */
	public static function abort_revert(): void {
		wp_die( esc_html( self::MESSAGE ), esc_html__( 'Kits', 'pro-elements' ), array( 'response' => 409 ) );
	}
}
