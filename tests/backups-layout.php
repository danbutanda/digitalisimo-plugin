<?php
/**
 * Carpetas de WordPress fuera de ABSPATH o detrás de un enlace simbólico: el respaldo completo no las incluiría, así
 * que se rechaza antes de exportar nada, con la carpeta, quién la define y qué hacer. Antes, plugins, medios o temas
 * externos se omitían sin aviso. Instalación de prueba con la estructura de Bedrock: ABSPATH en web/wp, wp-content en
 * web/app y wp-config.php en web/.
 */
$root = sys_get_temp_dir() . '/digitalisimo-layout-' . bin2hex( random_bytes( 4 ) );
mkdir( $root . '/web/wp', 0700, true );
$root = realpath( $root );
define( 'ABSPATH', $root . '/web/wp/' );
define( 'WP_CONTENT_DIR', $root . '/web/app' );
define( 'WP_PLUGIN_DIR', $root . '/web/app/plugins' );
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', $root . '/private' );
require __DIR__ . '/backups-coverage/env.php';
digi_make_wordpress( $root . '/web/wp', WP_CONTENT_DIR, false );
file_put_contents( $root . '/web/wp-config.php', "<?php // configuración de Bedrock\n" );
function check_ajax_referer( $action, $name ) { return 1; }
function wp_send_json_error( $data, $status = null ) { throw new RuntimeException( 'JSON-ERROR:' . $data['message'] ); }
function wp_send_json_success( $data ) { throw new RuntimeException( 'JSON-OK' ); }
function wp_generate_password() { return str_repeat( 'x', 40 ); }

/** Problemas de una estructura de carpetas creada al vuelo. */
function digi_layout( $abspath, array $folders ) {
	return Digitalisimo_Backups_Layout::problems( $abspath, $folders );
}

try {
	// 1. Casos de la comprobación, con carpetas y enlaces reales.
	$lab = $root . '/lab';
	foreach ( array( 'wp/wp-content/plugins', 'wp/wp-content/uploads', 'wp/media', 'app/plugins', 'shared/uploads', 'themes-extra', 'releases/1/wp-content' ) as $dir ) mkdir( $lab . '/' . $dir, 0700, true );
	$wp = $lab . '/wp';
	$inside = array( 'wp-content' => array( $wp . '/wp-content', 'wp-content', 'WP_CONTENT_DIR' ), 'plugins' => array( $wp . '/wp-content/plugins', 'La carpeta de plugins', 'WP_PLUGIN_DIR' ), 'mu-plugins' => array( $wp . '/wp-content/mu-plugins', 'La carpeta de plugins obligatorios', 'WPMU_PLUGIN_DIR' ), 'uploads' => array( $wp . '/wp-content/uploads', 'La carpeta de medios', 'UPLOADS o la opción upload_path' ) );
	digi_assert( array() === digi_layout( $wp . '/', $inside ), 'Una instalación con todo dentro de ABSPATH tuvo problemas.' );

	$problems = digi_layout( $wp . '/', array( 'wp-content' => array( $lab . '/app', 'wp-content', 'WP_CONTENT_DIR' ), 'plugins' => array( $lab . '/app/plugins', 'La carpeta de plugins', 'WP_PLUGIN_DIR' ) ) );
	digi_assert( array( 'wp-content (WP_CONTENT_DIR) está en ' . $lab . '/app, fuera de la carpeta de WordPress (' . $wp . '). El respaldo completo sólo recorre esa carpeta y todavía no incluye carpetas externas, así que quedaría incompleto y no se crea. Para respaldar esta instalación, mueve la carpeta dentro de ' . $wp . ' y ajusta WP_CONTENT_DIR.' ) === $problems, 'wp-content fuera de ABSPATH no se explicó una sola vez: ' . json_encode( $problems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );

	$problems = digi_layout( $wp . '/', array( 'wp-content' => $inside['wp-content'], 'plugins' => array( $lab . '/app/plugins', 'La carpeta de plugins', 'WP_PLUGIN_DIR' ) ) );
	digi_assert( 1 === count( $problems ) && 0 === strpos( $problems[0], 'La carpeta de plugins (WP_PLUGIN_DIR) está en ' . $lab . '/app/plugins, fuera de' ), 'Una carpeta de plugins externa, que antes se omitía sin aviso, no se rechazó.' );

	$problems = digi_layout( $wp . '/', array( 'wp-content' => array( $wp . '/../app', 'wp-content', 'WP_CONTENT_DIR' ) ) );
	digi_assert( 1 === count( $problems ) && false !== strpos( $problems[0], 'está en ' . $lab . '/app, fuera de' ), 'WP_CONTENT_DIR con «..» no se resolvió a su carpeta real.' );

	rmdir( $wp . '/wp-content/uploads' );
	symlink( $lab . '/shared/uploads', $wp . '/wp-content/uploads' );
	$problems = digi_layout( $wp . '/', $inside );
	digi_assert( array( 'La carpeta de medios (UPLOADS o la opción upload_path) pasa por el enlace simbólico ' . $wp . '/wp-content/uploads, que apunta a ' . $lab . '/shared/uploads. El respaldo completo no sigue enlaces simbólicos, así que quedaría incompleto y no se crea. Para respaldar esta instalación, sustituye el enlace por la carpeta real dentro de ' . $wp . '.' ) === $problems, 'Una carpeta de medios enlazada fuera de ABSPATH no se explicó: ' . json_encode( $problems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	unlink( $wp . '/wp-content/uploads' );
	symlink( $wp . '/media', $wp . '/wp-content/uploads' );
	$problems = digi_layout( $wp . '/', $inside );
	digi_assert( 1 === count( $problems ) && false !== strpos( $problems[0], 'pasa por el enlace simbólico ' . $wp . '/wp-content/uploads' ), 'Un enlace dentro de ABSPATH, que el respaldo tampoco sigue, se aceptó.' );
	unlink( $wp . '/wp-content/uploads' );

	$problems = digi_layout( $wp . '/', array( 'wp-content' => $inside['wp-content'], 'themes-1' => array( $lab . '/themes-extra', 'Un directorio de temas', 'register_theme_directory()' ) ) );
	digi_assert( 1 === count( $problems ) && 0 === strpos( $problems[0], 'Un directorio de temas (register_theme_directory()) está en ' . $lab . '/themes-extra' ), 'Un directorio de temas registrado fuera de ABSPATH no se rechazó.' );

	symlink( $lab . '/releases/1', $lab . '/current' );
	digi_assert( array() === digi_layout( $lab . '/current/', array( 'wp-content' => array( $lab . '/current/wp-content', 'wp-content', 'WP_CONTENT_DIR' ) ) ), 'Un ABSPATH que es enlace de despliegue (current → releases/N) se rechazó.' );
	digi_assert( array() === digi_layout( $lab . '/current/', array( 'wp-content' => array( $lab . '/releases/1/wp-content', 'wp-content', 'WP_CONTENT_DIR' ) ) ), 'wp-content definido con la ruta real del despliegue se rechazó.' );

	$problems = digi_layout( $wp . '/', array( 'wp-content' => array( $wp . '/no-existe', 'wp-content', 'WP_CONTENT_DIR' ) ) );
	digi_assert( 1 === count( $problems ) && false !== strpos( $problems[0], 'No se pudo resolver wp-content' ), 'Un wp-content inexistente no se informó.' );
	$problems = digi_layout( $lab . '/no-existe/', $inside );
	digi_assert( 1 === count( $problems ) && false !== strpos( $problems[0], 'No se pudo resolver la carpeta de WordPress' ), 'Un ABSPATH inexistente no se informó.' );

	// folders() toma los directorios de temas registrados con register_theme_directory(), además de los de WordPress.
	$GLOBALS['wp_theme_directories'] = array( WP_CONTENT_DIR . '/themes', $lab . '/themes-extra' );
	$folders = Digitalisimo_Backups_Layout::folders();
	digi_assert( isset( $folders['wp-content'], $folders['plugins'], $folders['mu-plugins'], $folders['uploads'], $folders['themes-1'] ) && $lab . '/themes-extra' === $folders['themes-1'][0], 'folders() no reúne las carpetas que WordPress carga: ' . json_encode( array_keys( $folders ) ) );
	digi_assert( (bool) preg_grep( '#^Un directorio de temas \(register_theme_directory\(\)\) está en ' . preg_quote( $lab, '#' ) . '/themes-extra, fuera#', Digitalisimo_Backups_Layout::problems() ), 'Un directorio de temas registrado fuera de ABSPATH no llegó a la comprobación del respaldo.' );

	// En una red, la carpeta de medios de cada sitio con upload_path propio (Red → Sitios → Editar → Ajustes): fuera de
	// ABSPATH se rechaza, con /sites/<id> salvo en el sitio principal, como la calcula _wp_upload_dir().
	$GLOBALS['digi']['multisite'] = true;
	$GLOBALS['wp_theme_directories'] = array( WP_CONTENT_DIR . '/themes' );
	mkdir( $lab . '/medios-red/sites/2', 0700, true );
	mkdir( $lab . '/medios-principal', 0700, true );
	$GLOBALS['wpdb']->upload_paths = array( 1 => $lab . '/medios-principal', 2 => $lab . '/medios-red' );
	$folders = Digitalisimo_Backups_Layout::folders();
	digi_assert( $lab . '/medios-principal' === ( $folders['uploads-1'][0] ?? '' ) && $lab . '/medios-red/sites/2' === ( $folders['uploads-2'][0] ?? '' ), 'folders() no calculó la carpeta de medios de cada sitio de la red: ' . json_encode( $folders, JSON_UNESCAPED_SLASHES ) );
	$problems = Digitalisimo_Backups_Layout::problems();
	digi_assert( (bool) preg_grep( '#^La carpeta de medios del sitio 2 \(la opción upload_path de ese sitio\) está en ' . preg_quote( $lab, '#' ) . '/medios-red/sites/2, fuera#', $problems ) && (bool) preg_grep( '#^La carpeta de medios del sitio 1 .* está en ' . preg_quote( $lab, '#' ) . '/medios-principal, fuera#', $problems ), 'Los medios de un sitio de la red fuera de ABSPATH, que el respaldo omitiría, no se rechazaron: ' . json_encode( $problems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	// Relativa a ABSPATH (blogs.dir de las redes antiguas, con ms_files_rewriting y sin /sites/<id>) queda dentro.
	mkdir( ABSPATH . 'wp-content/blogs.dir/2/files', 0700, true );
	$GLOBALS['wpdb']->upload_paths = array( 2 => 'wp-content/blogs.dir/2/files' );
	$GLOBALS['digi']['site_options']['ms_files_rewriting'] = 1;
	$folders = Digitalisimo_Backups_Layout::folders();
	digi_assert( ABSPATH . 'wp-content/blogs.dir/2/files' === ( $folders['uploads-2'][0] ?? '' ) && ! preg_grep( '#del sitio#', Digitalisimo_Backups_Layout::problems() ), 'Una carpeta de medios relativa a ABSPATH se rechazó o no se resolvió como en WordPress.' );
	$GLOBALS['digi']['multisite'] = false;
	$GLOBALS['wpdb']->upload_paths = array();
	unset( $GLOBALS['digi']['site_options']['ms_files_rewriting'] );
	digi_rmtree( ABSPATH . 'wp-content' );

	// 2. Bedrock de verdad: wp-content fuera de ABSPATH se rechaza en cada superficie, antes de exportar nada.
	$GLOBALS['wp_theme_directories'] = array( WP_CONTENT_DIR . '/themes' );
	$expected = 'wp-content (WP_CONTENT_DIR) está en ' . $root . '/web/app, fuera de la carpeta de WordPress (' . $root . '/web/wp). El respaldo completo sólo recorre esa carpeta y todavía no incluye carpetas externas, así que quedaría incompleto y no se crea. Para respaldar esta instalación, mueve la carpeta dentro de ' . $root . '/web/wp y ajusta WP_CONTENT_DIR.';
	$status = Digitalisimo_Backups::service_status();
	digi_assert( false === $status['ready'] && array( $expected ) === $status['problems'] && 'parent' === $status['wp_config'], 'El estado no explicó wp-content fuera de ABSPATH: ' . json_encode( $status['problems'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	$error = Digitalisimo_Backups::service_create();
	digi_assert( is_wp_error( $error ) && 422 === $error->get_error_data()['status'] && $expected === $error->get_error_message(), 'El caso de uso no rechazó wp-content fuera de ABSPATH con el mensaje claro.' );
	$query = digi_admin_post( 'run', Digitalisimo_Backups::ACTION_RUN, array( 'digitalisimo_backup_destination' => 'local' ) );
	digi_assert( 'error' === $query['backup'] && $expected === $query['message'], 'La pantalla no mostró el motivo: ' . json_encode( $query, JSON_UNESCAPED_UNICODE ) );
	$_POST = array( 'nonce' => 'ok', 'context' => 'site', 'destination' => 'local' );
	try {
		Digitalisimo_Backups::manual_start();
		throw new RuntimeException( 'FALLA: el respaldo con avance (AJAX) empezó con wp-content fuera de ABSPATH.' );
	} catch ( RuntimeException $response ) {
		digi_assert( 'JSON-ERROR:' . $expected === $response->getMessage(), 'El respaldo con avance no mostró el motivo: ' . $response->getMessage() );
	}
	Digitalisimo_Backups_Rest::routes();
	list( $code, $body ) = digi_rest( 'POST', 'digitalisimo-backups/v1/backups' );
	digi_assert( 422 === $code && 'digitalisimo_backups_not_ready' === $body['code'] && $expected === $body['message'], 'La API no rechazó wp-content fuera de ABSPATH con 422.' );
	$create = new ReflectionMethod( 'Digitalisimo_Backups', 'create_network' );
	if ( PHP_VERSION_ID < 80100 ) $create->setAccessible( true );
	try {
		$create->invoke( null );
		throw new RuntimeException( 'FALLA: el motor creó un respaldo sin wp-content.' );
	} catch ( Exception $e ) {
		digi_assert( $expected === $e->getMessage(), 'El motor no aplicó la misma comprobación: ' . $e->getMessage() );
	}
	digi_assert( array() === digi_stored( $root . '/private' ) && array() === get_option( Digitalisimo_Backups::HISTORY, array() ) && array() === $GLOBALS['wpdb']->queries, 'Un intento rechazado exportó la base de datos, dejó un ZIP o tocó el historial.' );

	// 3. La restauración también se detiene antes de tocar el sitio: la copia previa no puede ser completa.
	$content = array(
		'database.sql' => "SET NAMES utf8mb4;\nDROP TABLE IF EXISTS `wp_options`;\nCREATE TABLE `wp_options` (`option_id` bigint);\nINSERT INTO `wp_options` (`option_id`,`option_name`,`option_value`) VALUES ('1','blogname','Del respaldo');\n",
		'wordpress/wp-config.php' => '<?php',
		'wordpress/wp-load.php' => 'Contenido de wp-load.php',
		'wordpress/wp-settings.php' => 'Contenido de wp-settings.php',
		'wordpress/wp-admin/index.php' => 'Contenido de wp-admin/index.php',
		'wordpress/wp-includes/version.php' => 'Respaldo que no debe escribirse',
	);
	$entries = array();
	foreach ( $content as $name => $bytes ) $entries[ $name ] = array( 'size' => strlen( $bytes ), 'sha256' => hash( 'sha256', $bytes ) );
	$manifest = array( 'format' => 'digitalisimo-backup/v3', 'backup_format_version' => 3, 'scope' => 'installation', 'type' => 'full', 'multisite' => false, 'table_prefix' => 'wp_', 'file_count' => count( $entries ) - 1, 'uncompressed_size' => array_sum( array_column( $entries, 'size' ) ), 'entries' => $entries );
	$zip = new ZipArchive();
	$zip->open( $root . '/private/digitalisimo-backup-otro.zip', ZipArchive::CREATE );
	foreach ( $content as $name => $bytes ) $zip->addFromString( $name, $bytes );
	$zip->addFromString( 'manifest.json', json_encode( $manifest ) );
	$zip->close();
	$verified = Digitalisimo_Backups::service_verify( 'digitalisimo-backup-otro.zip' );
	digi_assert( ! is_wp_error( $verified ) && true === $verified['verified'], 'El paquete de prueba no es un respaldo íntegro.' );
	$error = Digitalisimo_Backups::service_restore( 'digitalisimo-backup-otro.zip', array( 'confirm' => 'RESTAURAR' ) );
	digi_assert( is_wp_error( $error ) && 422 === $error->get_error_data()['status'] && 'digitalisimo_backups_not_ready' === $error->get_error_code() && $expected === $error->get_error_message(), 'La restauración no se detuvo con el mismo motivo (422): ' . ( is_wp_error( $error ) ? $error->get_error_message() : 'terminó' ) );
	digi_assert( 'Contenido de wp-includes/version.php' === file_get_contents( ABSPATH . 'wp-includes/version.php' ) && 'Sitio de prueba' === $GLOBALS['wpdb']->value( 'blogname' ) && array() === $GLOBALS['wpdb']->queries, 'La restauración rechazada tocó archivos o la base de datos.' );
	digi_assert( array( 'digitalisimo-backup-otro.zip' ) === digi_stored( $root . '/private' ) && array() === get_option( Digitalisimo_Backups::HISTORY, array() ), 'La restauración rechazada dejó una copia previa incompleta.' );
} finally {
	digi_rmtree( $root );
}
echo "DIGITALÍSIMO Backups: carpetas fuera de ABSPATH o enlazadas se rechazan antes de exportar, con un mensaje claro en cada superficie.\n";
