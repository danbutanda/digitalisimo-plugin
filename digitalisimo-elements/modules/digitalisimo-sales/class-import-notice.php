<?php
namespace Digitalisimo\Elements\Sales;

defined( 'ABSPATH' ) || exit;

/**
 * Aviso al importar un ZIP de plantillas que el núcleo importaría vacío.
 *
 * «Plantillas → Plantillas guardadas → Importar plantillas» (la acción AJAX `elementor_library_direct_actions` con
 * `direct_import_template`) extrae el ZIP y valida cada JSON como un archivo sin filtrar: sin la carga de archivos sin
 * filtrar (`elementor_unfiltered_files_upload`, y que el usuario pueda subir JSON y el servidor sanear SVG), ninguno
 * pasa, el núcleo no importa nada, deja avisos de PHP (`local.php`, `$extracted_files['files']` sin definir) y vuelve
 * a la lista sin decir por qué. Lo mismo, sin ZIP, si el usuario no puede subir JSON: el núcleo termina en blanco.
 *
 * Antes que el núcleo (prioridad 1), con el mismo nonce, este gancho reconoce esos casos y vuelve a la biblioteca con
 * un aviso claro que dice qué falta y dónde se enciende, sin intentar una importación que daría cero. Con la opción
 * encendida (o con un JSON suelto, que el núcleo importa sin esa validación) no hace nada. El editor del constructor
 * ya pregunta antes de importar; este es el camino de la administración. Fuera de `elementor/`.
 */
final class Import_Notice {
	const QUERY = 'digitalisimo-import';

	/** Motivo → aviso. */
	const MESSAGES = array(
		'opcion'   => 'El ZIP de plantillas no se importó: DIGITALÍSIMO Elements sólo importa plantillas desde un ZIP con «Habilitar cargas de archivos sin filtrar» encendida (Configuración → Avanzado). Enciéndela, vuelve a importar el ZIP y apágala al terminar: deja subir archivos JSON y SVG. Un archivo JSON suelto sí se importa sin ella.',
		'usuario'  => 'Las plantillas no se importaron: tu usuario no puede subir archivos JSON (sólo los administradores o los roles a los que se les permite en el gestor de roles del constructor). Pide a un administrador que las importe.',
		'servidor' => 'El ZIP de plantillas no se importó: el servidor no tiene las extensiones DOM y SimpleXML de PHP, que hacen falta para revisar los archivos sin filtrar. Pide a tu hosting que las active, o importa los archivos JSON uno por uno.',
		'filtro'   => 'El ZIP de plantillas no se importó: otro plugin o un fragmento de código desactiva las cargas de archivos sin filtrar en este sitio. Importa los archivos JSON uno por uno.',
	);

	public static function init(): void {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		add_action( 'wp_ajax_elementor_library_direct_actions', array( __CLASS__, 'guard' ), 1 );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
	}

	/**
	 * Por qué el núcleo importaría vacío un archivo con ese nombre: `opcion`, `usuario`, `servidor`, `filtro`, o ''
	 * si lo importaría. Repite, en orden, las condiciones de `Uploads_Manager::are_unfiltered_uploads_enabled()`.
	 */
	public static function reason( string $file_name ): string {
		if ( ! class_exists( '\Elementor\User' ) || ! class_exists( '\Elementor\Core\Files\Uploads_Manager' ) ) {
			return '';
		}
		if ( ! \Elementor\User::is_current_user_can_upload_json() ) {
			return 'usuario';
		}
		if ( 'zip' !== strtolower( (string) pathinfo( $file_name, PATHINFO_EXTENSION ) ) ) {
			return '';
		}
		if ( \Elementor\Core\Files\Uploads_Manager::are_unfiltered_uploads_enabled() ) {
			return '';
		}
		if ( ! get_option( \Elementor\Core\Files\Uploads_Manager::UNFILTERED_FILE_UPLOADS_KEY ) ) {
			return 'opcion';
		}
		if ( ! class_exists( 'DOMDocument' ) || ! class_exists( 'SimpleXMLElement' ) ) {
			return 'servidor';
		}
		return 'filtro';
	}

	/** En `wp_ajax_elementor_library_direct_actions`, antes del núcleo. */
	public static function guard(): void {
		// phpcs:disable WordPress.Security.NonceVerification -- se verifica abajo con el nonce del núcleo.
		$action = isset( $_REQUEST['library_action'] ) ? sanitize_key( wp_unslash( $_REQUEST['library_action'] ) ) : '';
		if ( 'direct_import_template' !== $action || ! isset( $_FILES['file']['name'] ) ) {
			return;
		}
		$nonce = isset( $_REQUEST['_nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_nonce'] ) ) : '';
		// phpcs:enable
		if ( ! wp_verify_nonce( $nonce, 'elementor_ajax' ) || ! class_exists( '\Elementor\User' ) || ! \Elementor\User::is_current_user_can_edit_post_type( 'elementor_library' ) ) {
			return; // El núcleo decide (y responde «Access Denied» o nada, como siempre).
		}
		$reason = self::reason( sanitize_file_name( (string) $_FILES['file']['name'] ) );
		if ( '' === $reason ) {
			return;
		}
		wp_safe_redirect( add_query_arg( self::QUERY, $reason, admin_url( 'edit.php?post_type=elementor_library&tabs_group=library' ) ) );
		exit;
	}

	/** El aviso, en la lista de plantillas, después de volver. */
	public static function notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo elige un texto fijo.
		$reason = isset( $_GET[ self::QUERY ] ) ? sanitize_key( wp_unslash( $_GET[ self::QUERY ] ) ) : '';
		if ( ! isset( self::MESSAGES[ $reason ] ) || ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$link = 'opcion' === $reason && current_user_can( 'manage_options' )
			? ' <a href="' . esc_url( admin_url( 'admin.php?page=elementor-settings#tab-advanced' ) ) . '">Ir a Configuración → Avanzado</a>'
			: '';
		echo '<div class="notice notice-error digitalisimo-import-notice"><p>' . esc_html( self::MESSAGES[ $reason ] ) . $link . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $link se arma escapado.
	}
}
