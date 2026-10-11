<?php
/**
 * Envío real de los formularios heredados de Element Pack (sin Element Pack): una página con
 * `bdt-contact-form`, otra con `bdt-webhook-form` y otra con `bdt-mailchimp` se envían por el AJAX de
 * PRO Elements en cada sitio de la red. Captura el correo, la petición al webhook y las llamadas a la API
 * de Mailchimp (con la clave y la audiencia que Element Pack guardaba en sus ajustes) en result.json.
 */
define( 'DOING_AJAX', true );
$_POST['action'] = 'elementor_pro_forms_send_form'; // PRO Elements sólo registra su manejador en una petición de envío.
require '/wordpress/wp-load.php';
$result = array();
add_filter( 'pre_wp_mail', static function ( $null, $atts ) use ( &$result ) {
	$result[ get_current_blog_id() ]['mail'] = array( 'to' => $atts['to'], 'subject' => $atts['subject'], 'reply_to' => implode( ' ', (array) $atts['headers'] ), 'has_message' => false !== strpos( (string) $atts['message'], 'Hola desde el A/B' ) );
	return true;
}, 10, 2 );
add_filter( 'pre_http_request', static function ( $pre, $args, $url ) use ( &$result ) {
	if ( false !== strpos( (string) $url, 'api.mailchimp.com' ) ) {
		$result[ get_current_blog_id() ]['mailchimp'][] = array( 'method' => (string) ( $args['method'] ?? 'GET' ), 'url' => $url, 'body' => is_array( $args['body'] ) ? $args['body'] : (string) $args['body'] );
		return array( 'headers' => array(), 'body' => wp_json_encode( array( 'id' => md5( 'ana@example.com' ), 'email_address' => 'ana@example.com', 'status' => 'subscribed', 'tags' => array() ) ), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array() );
	}
	$result[ get_current_blog_id() ]['webhook'] = array( 'url' => $url, 'body' => is_array( $args['body'] ) ? $args['body'] : (string) $args['body'] );
	return array( 'headers' => array(), 'body' => '', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array() );
}, 10, 3 );
add_filter( 'wp_die_ajax_handler', static function () {
	return static function () { throw new RuntimeException( 'sent' ); };
} );
foreach ( array( 1, 2 ) as $blog ) {
	switch_to_blog( $blog );
	// La clave y la audiencia que Element Pack guardaba en sus ajustes del sitio.
	update_option( 'element_pack_api_settings', array( 'mailchimp_api_key' => 'abc-us1', 'mailchimp_list_id' => 'L1' ) );
	foreach ( array( 'bdt-contact-form' => array( 'name' => 'Ana', 'email' => 'ana@example.com', 'subject' => 'Consulta', 'message' => 'Hola desde el A/B' ), 'bdt-webhook-form' => array( 'full_name' => 'Ana', 'email' => 'ana@example.com' ), 'bdt-mailchimp' => array( 'fname' => 'Ana', 'email' => 'ana@example.com' ) ) as $type => $fields ) {
		$settings = array( 'bdt-webhook-form' => array( 'webhook_url' => 'https://example.com/hook' ), 'bdt-mailchimp' => array( 'show_fname' => 'yes' ) )[ $type ] ?? array();
		$id       = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $type ) );
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( array( array( 'id' => 'c1', 'elType' => 'container', 'settings' => (object) array(), 'elements' => array(
			array( 'id' => 'f1', 'elType' => 'widget', 'widgetType' => $type, 'settings' => (object) $settings, 'elements' => array() ),
		) ) ) ) ) );
		$_POST = $_REQUEST = array( 'action' => 'elementor_pro_forms_send_form', 'post_id' => (string) $id, 'form_id' => 'f1', 'form_fields' => $fields );
		ob_start();
		try {
			do_action( 'wp_ajax_nopriv_elementor_pro_forms_send_form' );
		} catch ( RuntimeException $e ) {
			unset( $e );
		}
		$raw      = (string) ob_get_clean();
		$response = json_decode( $raw, true );
		$result[ $blog ][ $type ] = array( 'success' => ! empty( $response['success'] ), 'data' => $response['data'] ?? null, 'raw' => is_array( $response ) ? '' : substr( $raw, 0, 300 ), 'handler' => has_action( 'wp_ajax_nopriv_elementor_pro_forms_send_form' ) );
	}
	restore_current_blog();
}
file_put_contents( __DIR__ . '/result.json', wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
