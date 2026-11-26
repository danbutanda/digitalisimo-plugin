<?php
/**
 * Preparación de las capturas del Asistente IA junto a otras pantallas DIGITALÍSIMO (ia-assistant-estilo.sh; pedido del
 * propietario del 2026-11-26: «al asistente dale el mismo estilo de los plugins en colores y todo para que se vea igual
 * todo»). Sitio INVENTADO con IA Tools y Tools; un proveedor configurado con una clave de mentira (nada se llama: las
 * capturas sólo pintan) y una conversación guardada con lo que suele verse: un mensaje con fotos subidas, el trabajo
 * plegado, una respuesta con enlaces, un aviso y una tarjeta de confirmación. Deja /digi-st/ready.json.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
$dir = '/digi-st';
try {
	file_put_contents( WPMU_PLUGIN_DIR . '/digi-st-no-autoupdate.php', "<?php\nadd_filter( 'automatic_updater_disabled', '__return_true', PHP_INT_MAX );\nadd_filter( 'auto_update_plugin', '__return_false', PHP_INT_MAX );\nadd_filter( 'pre_http_request', static function ( \$pre, \$args, \$url ) { return false === strpos( (string) \$url, '127.0.0.1' ) ? new WP_Error( 'offline', 'Sin red en las capturas.' ) : \$pre; }, 10, 3 );\n" );
	update_option( 'blogname', 'Panadería La Espiga' );
	foreach ( array( 'digitalisimo.chatbot/digitalisimo-chatbot.php', 'digitalisimo-tools/digitalisimo-tools.php' ) as $plugin ) {
		$done = activate_plugin( $plugin );
		if ( is_wp_error( $done ) ) {
			throw new RuntimeException( 'No se activó ' . $plugin . ': ' . $done->get_error_message() );
		}
	}
	update_option( 'digitalisimo_ai_options', array( 'providers' => array( 'anthropic' => array( 'enabled' => 1, 'key' => 'sk-ant-solo-para-capturas-0000000000', 'url' => '' ) ) ), false );
	$admin = get_user_by( 'login', 'admin' );
	$files = array();
	foreach ( array( array( 'fachada', array( 170, 110, 60 ) ), array( 'mostrador', array( 210, 160, 90 ) ) ) as $i => $spec ) {
		$upload = wp_upload_dir();
		$file   = trailingslashit( $upload['path'] ) . $spec[0] . '.png';
		$im     = imagecreatetruecolor( 600, 400 );
		imagefill( $im, 0, 0, imagecolorallocate( $im, $spec[1][0], $spec[1][1], $spec[1][2] ) );
		imagefilledrectangle( $im, 180, 120, 420, 330, imagecolorallocate( $im, 250, 240, 225 ) );
		imagepng( $im, $file );
		$id = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => $spec[0], 'post_status' => 'inherit', 'post_author' => $admin->ID ), $file );
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
		$files[] = array( 'id' => (int) $id, 'name' => $spec[0] . '.webp', 'kind' => 'image', 'bytes' => 48000 + $i * 9000, 'before' => 2100000, 'thumb' => (string) wp_get_attachment_image_url( $id, 'thumbnail' ), 'url' => (string) wp_get_attachment_url( $id ) );
	}
	$conv = Digitalisimo_AI_Assistant_History::create( $admin->ID, 'Crea una página «Visítanos» con la foto de la fachada' );
	$m    = function ( $role, $fields ) { return Digitalisimo_AI_Assistant_History::message( $role, $fields ); };
	$conv['messages'] = array(
		$m( 'user', array( 'text' => 'Crea una página «Visítanos» con la foto de la fachada', 'files' => $files ) ),
		$m( 'assistant', array( 'text' => 'Reviso tus páginas y preparo el borrador.', 'calls' => array( array( 'id' => 'c1', 'ability' => 'elementor/create-page', 'input' => array() ) ) ) ),
		$m( 'tool', array( 'call' => 'c1', 'ability' => 'elementor/create-page', 'label' => 'Crear página', 'kind' => 'draft', 'status' => 'ok', 'summary' => '{}', 'detail' => '', 'content' => '{}' ) ),
		$m( 'assistant', array( 'text' => 'Listo: preparé la página «Visítanos» en borrador con la foto de la fachada como imagen principal. [Ver la página](' . home_url( '/?page_id=2&preview=true' ) . ') y [Editarla](' . admin_url( 'post.php?post=2&action=edit' ) . '). Revisa que el horario sea el correcto antes de publicarla.' ) ),
		$m( 'user', array( 'text' => 'Publícala' ) ),
		$m( 'notice', array( 'text' => 'Subí 2 imágenes a tu biblioteca (de 4 MB a 103 KB en total).', 'tone' => 'info' ) ),
	);
	$conv['pending'] = array(
		array( 'id' => 'act1', 'state' => 'pending', 'created' => time(), 'call' => array( 'id' => 'c2', 'ability' => 'core/publish', 'input' => array( 'post_id' => 2 ) ), 'kind' => 'write', 'label' => 'Publicar la página «Visítanos»', 'input' => "{\n  \"post_id\": 2,\n  \"status\": \"publish\"\n}", 'risks' => array(), 'idempotent' => true ),
	);
	Digitalisimo_AI_Assistant_History::save( $conv );
	file_put_contents( $dir . '/ready.json', wp_json_encode( array( 'admin' => (int) $admin->ID, 'conversation' => (int) $conv['id'], 'wp' => $GLOBALS['wp_version'], 'php' => PHP_VERSION ) ) );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
