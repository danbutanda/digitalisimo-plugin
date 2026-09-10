<?php
defined( 'ABSPATH' ) || exit;

/** Operación de IndexNow: clave, cola y registro, reutilizando el emisor SEO AI. */
class Digitalisimo_Integrations_SEO_AI_IndexNow_Tools {
	const OPTION = 'digitalisimo_integrations_options';
	public static function init() { add_action( 'admin_menu', array( __CLASS__, 'menu' ), 33 ); add_action( 'admin_post_digitalisimo_indexnow_tools', array( __CLASS__, 'action' ) ); }
	public static function menu() { add_submenu_page( 'digitalisimo', 'SEO AI · IndexNow', 'SEO AI · IndexNow', 'manage_options', 'digitalisimo-seo-ai-indexnow', array( __CLASS__, 'page' ) ); }
	public static function action() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'digitalisimo_indexnow_tools' ) ) wp_die( esc_html__( 'Solicitud no autorizada.', 'digitalisimo-integrations' ) );
		$action = sanitize_key( wp_unslash( $_POST['indexnow_action'] ?? '' ) ); $option = (array) get_option( self::OPTION, array() );
		if ( 'generate' === $action ) { $option['seo_ai_indexnow_key'] = wp_generate_password( 32, false, false ); $option['seo_ai_indexnow_enabled'] = 1; update_option( self::OPTION, $option, false ); Digitalisimo_Integrations_SEO_AI::refresh_rewrites(); }
		if ( 'send' === $action ) wp_schedule_single_event( time() + 1, 'digitalisimo_seo_ai_indexnow' );
		if ( 'resend' === $action ) { $url = esc_url_raw( wp_unslash( $_POST['indexnow_url'] ?? '' ) ); $kind = sanitize_key( wp_unslash( $_POST['indexnow_kind'] ?? 'update' ) ); if ( $url ) Digitalisimo_Integrations_SEO_AI::queue_url( $url, $kind, true ); wp_schedule_single_event( time() + 1, 'digitalisimo_seo_ai_indexnow' ); }
		wp_safe_redirect( add_query_arg( array( 'page' => 'digitalisimo-seo-ai-indexnow', 'updated' => $action ), admin_url( 'admin.php' ) ) ); exit;
	}
	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) return; $option = (array) get_option( self::OPTION, array() ); $key = $option['seo_ai_indexnow_key'] ?? ''; $queue = (array) get_option( 'digitalisimo_seo_ai_indexnow_queue', array() ); $log = (array) get_option( 'digitalisimo_seo_ai_indexnow_log', array() );
		echo '<div class="wrap"><h1>SEO AI · IndexNow</h1><p>IndexNow notifica cambios; una respuesta correcta confirma recepción, no indexación.</p><p><strong>Clave:</strong> ' . ( $key ? '<code>' . esc_html( $key ) . '</code>' : 'Sin configurar' ) . '</p>';
		if ( $key ) echo '<p><strong>Endpoint:</strong> <a href="' . esc_url( home_url( '/' . $key . '.txt' ) ) . '" target="_blank" rel="noopener">' . esc_html( home_url( '/' . $key . '.txt' ) ) . '</a></p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">'; wp_nonce_field( 'digitalisimo_indexnow_tools' ); echo '<input type="hidden" name="action" value="digitalisimo_indexnow_tools"><input type="hidden" name="indexnow_action" value="generate">'; submit_button( 'Generar clave', 'secondary', 'submit', false ); echo '</form> <form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">'; wp_nonce_field( 'digitalisimo_indexnow_tools' ); echo '<input type="hidden" name="action" value="digitalisimo_indexnow_tools"><input type="hidden" name="indexnow_action" value="send">'; submit_button( 'Procesar cola', 'primary', 'submit', false ); echo '</form><h2>Cola (' . count( $queue ) . ')</h2><p>Los cambios se procesan en segundo plano por lotes, con hasta cuatro reintentos para errores transitorios y backoff exponencial.</p><h2>Últimos envíos</h2><table class="widefat striped"><thead><tr><th>URL</th><th>Acción</th><th>HTTP</th><th>Intentos</th><th>Resultado</th><th>Fecha</th><th></th></tr></thead><tbody>';
		foreach ( array_slice( array_reverse( $log ), 0, 50 ) as $row ) { echo '<tr><td>' . esc_html( $row['url'] ?? '' ) . '</td><td>' . esc_html( $row['action'] ?? '' ) . '</td><td>' . esc_html( $row['status'] ?? '' ) . '</td><td>' . esc_html( $row['tries'] ?? 0 ) . '</td><td>' . esc_html( $row['result'] ?? '' ) . '</td><td>' . esc_html( date_i18n( 'Y-m-d H:i', (int) ( $row['time'] ?? 0 ) ) ) . '</td><td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'; wp_nonce_field( 'digitalisimo_indexnow_tools' ); echo '<input type="hidden" name="action" value="digitalisimo_indexnow_tools"><input type="hidden" name="indexnow_action" value="resend"><input type="hidden" name="indexnow_url" value="' . esc_attr( $row['url'] ?? '' ) . '"><input type="hidden" name="indexnow_kind" value="' . esc_attr( $row['action'] ?? 'update' ) . '"><button class="button button-small">Reenviar</button></form></td></tr>'; } echo '</tbody></table></div>';
	}
}
