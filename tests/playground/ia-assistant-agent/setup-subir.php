<?php
/**
 * Preparación del ensayo «subir archivos al Asistente» (ia-assistant-subir.sh; pedido del propietario del 2026-11-26:
 * «agrégale la función de agregar las imágenes y todas las debe comprimir a .webp al subirse, ya debemos tener un
 * plugin que hace eso, solo úsalo»). Un sitio INVENTADO con DIGITALÍSIMO Elements (el MCP para armar el borrador),
 * IA Tools (el Asistente) y DIGITALÍSIMO Tools (el conversor WebP de «Optimizar medios»):
 *   - Anthropic con la clave que el arnés deja en /digi-ias/k (se lee, se guarda en la opción y el archivo se borra);
 *   - dos fotos JPG y un PNG generados aquí con GD (nada se descarga) en /digi-ias/samples, FUERA de la biblioteca: el
 *     conductor los suelta en el Asistente desde Chrome.
 * Deja /digi-ias/ready.json; un fallo queda en setup-error.txt.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$dir = '/digi-ias';
try {
	$mode = trim( (string) @file_get_contents( $dir . '/mode.txt' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	$key  = trim( (string) @file_get_contents( $dir . '/k' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	@unlink( $dir . '/k' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	if ( 'real' !== $mode || strlen( $key ) < 20 ) {
		throw new RuntimeException( 'El ensayo «subir» sólo corre con la API real y su clave.' );
	}
	if ( ! function_exists( 'imagecreatetruecolor' ) ) {
		throw new RuntimeException( 'El PHP de Playground no trae GD: no se pueden crear las fotos del ensayo.' );
	}
	file_put_contents( WPMU_PLUGIN_DIR . '/digi-ias-no-autoupdate.php', "<?php\nadd_filter( 'automatic_updater_disabled', '__return_true', PHP_INT_MAX );\nadd_filter( 'auto_update_plugin', '__return_false', PHP_INT_MAX );\n" );
	update_option( 'permalink_structure', '/%postname%/' );
	update_option( 'blogname', 'Panadería La Espiga' );
	update_option( 'blogdescription', 'Pan dulce y de sal hecho cada mañana' );
	foreach ( array( 'digitalisimo-elements/pro-elements.php', 'digitalisimo.chatbot/digitalisimo-chatbot.php', 'digitalisimo-tools/digitalisimo-tools.php' ) as $plugin ) {
		$done = activate_plugin( $plugin );
		if ( is_wp_error( $done ) ) {
			throw new RuntimeException( 'No se activó ' . $plugin . ': ' . $done->get_error_message() );
		}
	}
	update_option( 'elementor_mcp_enabled', true );
	update_option( 'elementor_onboarded', true );

	// Las fotos del ensayo: degradados con ruido (pesan como fotos de verdad), fuera de la biblioteca.
	@mkdir( $dir . '/samples', 0777, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	$photo = function ( $name, $type, array $rgb, $w = 1600, $h = 1200 ) use ( $dir ) {
		$im = imagecreatetruecolor( $w, $h );
		mt_srand( crc32( $name ) );
		for ( $y = 0; $y < $h; $y += 2 ) {
			for ( $x = 0; $x < $w; $x += 2 ) {
				$c = imagecolorallocate( $im, ( $rgb[0] + (int) ( $x * 90 / $w ) + mt_rand( 0, 50 ) ) % 256, ( $rgb[1] + (int) ( $y * 90 / $h ) + mt_rand( 0, 50 ) ) % 256, ( $rgb[2] + mt_rand( 0, 50 ) ) % 256 );
				imagefilledrectangle( $im, $x, $y, $x + 1, $y + 1, $c );
			}
		}
		$file = $dir . '/samples/' . $name;
		if ( 'png' === $type ) {
			imagepng( $im, $file );
		} else {
			imagejpeg( $im, $file, 95 );
		}
		imagedestroy( $im );
		return array( 'name' => $name, 'bytes' => (int) filesize( $file ) );
	};
	$samples = array(
		$photo( 'fachada-panaderia.jpg', 'jpg', array( 150, 90, 40 ) ),
		$photo( 'mostrador-pan-dulce.jpg', 'jpg', array( 190, 140, 80 ) ),
		$photo( 'logo-la-espiga.png', 'png', array( 40, 60, 120 ), 900, 900 ),
	);

	$ai = array(
		'providers'             => array( 'anthropic' => array( 'enabled' => 1, 'key' => $key, 'url' => '' ) ),
		'assistant_rate_minute' => 0,
		'assistant_daily_calls' => 0,
		'assistant_concurrency' => 1,
	);
	update_option( 'digitalisimo_ai_options', $ai, false );
	$key   = '';
	$admin = get_user_by( 'login', 'admin' );
	file_put_contents(
		$dir . '/ready.json',
		wp_json_encode(
			array(
				'mode'      => $mode,
				'scenario'  => 'subir',
				'admin'     => (int) $admin->ID,
				'samples'   => $samples,
				'pages'     => array(),
				'product'   => 0,
				'footer'    => 0,
				'last_id'   => (int) $GLOBALS['wpdb']->get_var( "SELECT MAX(ID) FROM {$GLOBALS['wpdb']->posts}" ),
				'wp'        => $GLOBALS['wp_version'],
				'php'       => PHP_VERSION,
				'gd_webp'   => function_exists( 'imagewebp' ),
				'imagick'   => class_exists( 'Imagick' ),
				'max'       => (int) wp_max_upload_size(),
				'tools'     => class_exists( '\Digitalisimo\Tools\Media_WebP' ),
				'abilities' => function_exists( 'wp_register_ability' ),
			)
		)
	);
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
