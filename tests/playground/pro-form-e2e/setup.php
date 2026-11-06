<?php
/**
 * Segundo paso del blueprint de pro-form-e2e.sh (el constructor ya está cargado): las páginas de la prueba de punta a
 * punta de envíos de C6 (docs/pro/c6.md) y el mu-plugin que captura lo que hacen las acciones.
 *
 * - Por cada escenario, página A `e2e-<escenario>-a` con el Form de PRO y página B `e2e-<escenario>-b` con el mismo
 *   árbol convertido por `Converter::tree( $data, $log, true )` (el Formulario propio, con el mismo `id` de elemento).
 *   Escenarios: `basic` (nombre, correo y mensaje; validación propia, así que el servidor rechaza un envío incompleto) y
 *   `steps` (dos pasos con un campo condicional de F7b que sólo se muestra con «Empresa»).
 * - Acciones de los dos: guardar el envío (`save-to-database`, tabla `e_submissions`), correo y webhook.
 * - `digi-e2e-capture.php` (mu-plugin): cada `wp_mail` va a `mails.jsonl` (sin enviarse) y cada petición al receptor
 *   del arnés (`http://receptor.e2e.test/…`) a `hooks.jsonl` con su cuerpo, respondiendo 200; `/?digi-e2e=state`
 *   devuelve los envíos guardados con sus valores.
 * Deja en `pages.json` las páginas y el registro de la conversión.
 */
require '/wordpress/wp-load.php';

const DIGI_E2E_DIR = '/wordpress/wp-content/probe';

// Sin actualizaciones automáticas (como en los demás arneses): con una Release más nueva en GitHub, WordPress
// actualizaría DIGITALÍSIMO Elements (su actualizador las activa) y borraría la carpeta montada.
wp_mkdir_p( WPMU_PLUGIN_DIR );
file_put_contents( WPMU_PLUGIN_DIR . '/digi-no-autoupdate.php', "<?php\n// Arnés de pruebas: sin actualizaciones automáticas (borrarían el plugin montado).\nadd_filter( 'automatic_updater_disabled', '__return_true', PHP_INT_MAX );\nadd_filter( 'auto_update_plugin', '__return_false', PHP_INT_MAX );\nadd_filter( 'auto_update_theme', '__return_false', PHP_INT_MAX );\n" );
add_filter( 'automatic_updater_disabled', '__return_true', PHP_INT_MAX );

function digi_e2e_fail( $message ) {
	file_put_contents( DIGI_E2E_DIR . '/setup-error.txt', $message . "\n", FILE_APPEND );
}

file_put_contents(
	WPMU_PLUGIN_DIR . '/digi-e2e-capture.php',
	<<<'PHP'
<?php
// Captura de la prueba de punta a punta de envíos (pro-form-e2e.sh): nada sale del sitio.
add_filter( 'pre_wp_mail', static function ( $null, $atts ) {
	file_put_contents( '/wordpress/wp-content/probe/mails.jsonl', wp_json_encode( array( 'referer' => (string) ( $_POST['referrer'] ?? '' ), 'to' => $atts['to'], 'subject' => $atts['subject'], 'message' => $atts['message'], 'headers' => $atts['headers'] ) ) . "\n", FILE_APPEND );
	return true;
}, 10, 2 );
add_filter( 'pre_http_request', static function ( $pre, $args, $url ) {
	if ( 'receptor.e2e.test' !== wp_parse_url( (string) $url, PHP_URL_HOST ) ) {
		return $pre;
	}
	file_put_contents( '/wordpress/wp-content/probe/hooks.jsonl', wp_json_encode( array( 'referer' => (string) ( $_POST['referrer'] ?? '' ), 'url' => $url, 'method' => $args['method'] ?? 'POST', 'body' => $args['body'] ?? '' ) ) . "\n", FILE_APPEND );
	return array( 'headers' => array(), 'body' => 'ok', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array() );
}, 10, 3 );
add_action( 'init', static function () {
	if ( 'state' !== ( $_GET['digi-e2e'] ?? '' ) ) {
		return;
	}
	global $wpdb;
	$out   = array( 'table' => false, 'submissions' => array() );
	$table = $wpdb->prefix . 'e_submissions';
	if ( $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
		$out['table'] = true;
		foreach ( (array) $wpdb->get_results( "SELECT id, post_id, element_id, form_name, referer FROM {$table} ORDER BY id", ARRAY_A ) as $row ) {
			$row['values'] = array();
			foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT `key`, `value` FROM {$table}_values WHERE submission_id = %d ORDER BY id", $row['id'] ), ARRAY_A ) as $value ) {
				$row['values'][ $value['key'] ] = $value['value'];
			}
			$out['submissions'][] = $row;
		}
	}
	wp_send_json( $out );
} );
PHP
);

$forms = array(
	'basic' => array(
		'form_name'       => 'Contacto e2e',
		'form_validation' => 'custom',
		'form_fields'     => array(
			array( '_id' => 'b1', 'custom_id' => 'name', 'field_type' => 'text', 'field_label' => 'Nombre', 'placeholder' => 'Nombre', 'required' => 'true', 'width' => '100' ),
			array( '_id' => 'b2', 'custom_id' => 'email', 'field_type' => 'email', 'field_label' => 'Correo', 'placeholder' => 'Correo', 'required' => 'true', 'width' => '100' ),
			array( '_id' => 'b3', 'custom_id' => 'message', 'field_type' => 'textarea', 'field_label' => 'Mensaje', 'placeholder' => 'Mensaje', 'width' => '100' ),
		),
	),
	'steps' => array(
		'form_name'   => 'Pasos e2e',
		'step_type'   => 'number_text',
		'form_fields' => array(
			array( '_id' => 's1', 'custom_id' => 'paso1', 'field_type' => 'step', 'field_label' => 'Datos' ),
			array( '_id' => 's2', 'custom_id' => 'name', 'field_type' => 'text', 'field_label' => 'Nombre', 'required' => 'true', 'width' => '100' ),
			array( '_id' => 's3', 'custom_id' => 'tipo', 'field_type' => 'radio', 'field_label' => 'Tipo', 'field_options' => "Particular|particular\nEmpresa|empresa", 'required' => 'true', 'width' => '100' ),
			array( '_id' => 's4', 'custom_id' => 'empresa', 'field_type' => 'text', 'field_label' => 'Empresa', 'required' => 'true', 'width' => '100', 'digitalisimo_conditional' => 'yes', 'digitalisimo_logic' => 'all', 'digitalisimo_rules' => array( array( 'field' => 'tipo', 'operator' => 'is', 'value' => 'empresa' ) ) ),
			array( '_id' => 's5', 'custom_id' => 'paso2', 'field_type' => 'step', 'field_label' => 'Mensaje' ),
			array( '_id' => 's6', 'custom_id' => 'email', 'field_type' => 'email', 'field_label' => 'Correo', 'required' => 'true', 'width' => '100' ),
			array( '_id' => 's7', 'custom_id' => 'message', 'field_type' => 'textarea', 'field_label' => 'Mensaje', 'width' => '100' ),
		),
	),
);
$common = array(
	'submit_actions' => array( 'save-to-database', 'email', 'webhook' ),
	'email_to'       => 'destino@sitio.test',
	'email_subject'  => 'E2E [field id="name"]',
	'email_content'  => '[all-fields]',
	'email_from'     => 'web@sitio.test',
	'webhooks'       => 'http://receptor.e2e.test/hook',
	'custom_messages' => 'yes',
	'success_message' => 'Recibido.',
);

$pages = array();
foreach ( $forms as $slug => $settings ) {
	$tree = array(
		array(
			'id'       => 'c' . substr( md5( $slug ), 0, 6 ),
			'elType'   => 'container',
			'settings' => (object) array(),
			'elements' => array( array( 'id' => 'f' . substr( md5( $slug ), 0, 6 ), 'elType' => 'widget', 'widgetType' => 'form', 'settings' => $settings + $common, 'elements' => array() ) ),
		),
	);
	$log = array();
	$b   = \Digitalisimo\Elements\Convert\Converter::tree( json_decode( wp_json_encode( $tree ), true ), $log, true );
	foreach ( array( 'a' => $tree, 'b' => $b ) as $side => $data ) {
		$name = "e2e-$slug-$side";
		$id   = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $name, 'post_name' => $name ) );
		if ( ! $id ) {
			digi_e2e_fail( "No se pudo crear $name" );
			continue;
		}
		update_post_meta( $id, '_wp_page_template', 'elementor_canvas' );
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_template_type', 'wp-page' );
		update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
		update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
		\Elementor\Core\Files\CSS\Post::create( $id )->update();
		$pages[ $slug ][ $side ] = array( 'id' => $id, 'url' => get_permalink( $id ), 'widget' => $data[0]['elements'][0]['widgetType'], 'element' => $data[0]['elements'][0]['id'] );
	}
	$pages[ $slug ]['log'] = $log;
}
if ( class_exists( '\Digitalisimo\Elements\F7b\Flags' ) ) {
	\Digitalisimo\Elements\F7b\Flags::ensure();
	\Digitalisimo\Elements\F7b\Flags::refresh();
}
// Ajustes de «Rendimiento» de un sitio recién instalado (la puerta de PRO entre ellos), como en pro-ab/setup.php: su
// transición corre por WP-Cron, que aquí no se dispara. Sin la puerta, B cargaría los paquetes de PRO igual que A.
$transition = '\\Digitalisimo\\Elements\\Front\\Transition';
if ( class_exists( $transition ) ) {
	for ( $i = 0; $i < 10 && $transition::pending(); $i++ ) {
		$transition::run_now();
	}
	$pages['front'] = $transition::applied();
}
flush_rewrite_rules( false );
$widgets = \Elementor\Plugin::$instance->widgets_manager->get_widget_types();
$pages['registered'] = array( 'form' => isset( $widgets['form'] ), 'digitalisimo-form' => isset( $widgets['digitalisimo-form'] ) );
file_put_contents( DIGI_E2E_DIR . '/pages.json', wp_json_encode( $pages, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
file_put_contents( DIGI_E2E_DIR . '/ready.json', '{}' );
