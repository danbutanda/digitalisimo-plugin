<?php
/**
 * Preparación del ensayo del kit de ventas (paso runPHP del blueprint de sales-kit.sh): instala la sonda (mu-plugin),
 * activa DIGITALÍSIMO Elements (núcleo incluido, sin el plugin Elementor), enciende el MCP del sitio, deja la zona
 * horaria de la Ciudad de México y los enlaces permanentes, y crea los usuarios del ensayo con su contraseña de
 * aplicación: admin, una editora (edit_pages), una autora (sin edit_pages) y una editora a la que se le quitó
 * edit_pages. Las contraseñas van a /digi-probe/secrets.json (el arnés lo borra). Deja /digi-probe/setup.json; un fallo
 * queda en setup-error.txt.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
$dir = '/digi-probe';
try {
	wp_mkdir_p( WPMU_PLUGIN_DIR );
	if ( ! copy( $dir . '/probe.php', WPMU_PLUGIN_DIR . '/digi-sk-probe.php' ) ) {
		throw new RuntimeException( 'No se copió la sonda.' );
	}
	file_put_contents( WPMU_PLUGIN_DIR . '/digi-sk-no-autoupdate.php', "<?php\nadd_filter( 'automatic_updater_disabled', '__return_true', PHP_INT_MAX );\nadd_filter( 'auto_update_plugin', '__return_false', PHP_INT_MAX );\n" );
	$activated = activate_plugin( 'digitalisimo-elements/pro-elements.php' );
	if ( is_wp_error( $activated ) ) {
		throw new RuntimeException( 'No se activó Elements: ' . $activated->get_error_message() );
	}
	update_option( 'elementor_mcp_enabled', true );
	update_option( 'elementor_onboarded', true );
	update_option( 'timezone_string', 'America/Mexico_City' );
	update_option( 'permalink_structure', '/%postname%/' );
	update_option( 'blogname', 'Ensayo del kit de ventas' );
	delete_option( 'elementor_unfiltered_files_upload' );
	$secrets = array();
	$users   = array(
		'admin'               => 'administrator',
		'editora'             => 'editor',
		'autora'              => 'author',
		'editora-sin-paginas' => 'editor',
	);
	foreach ( $users as $login => $role ) {
		$user = get_user_by( 'login', $login );
		$id   = $user ? (int) $user->ID : (int) wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => wp_generate_password( 24 ),
				'user_email' => $login . '@example.test',
				'role'       => $role,
			)
		);
		if ( ! $id ) {
			throw new RuntimeException( 'No se creó ' . $login );
		}
		if ( 'editora-sin-paginas' === $login ) {
			// Una editora a la que el sitio le quitó edit_pages (el rol sigue siendo editor).
			$wp_user = new WP_User( $id );
			$wp_user->add_cap( 'edit_pages', false );
		}
		$created = WP_Application_Passwords::create_new_application_password( $id, array( 'name' => 'digi-sales-kit' ) );
		if ( is_wp_error( $created ) ) {
			throw new RuntimeException( 'Sin contraseña de aplicación para ' . $login . ': ' . $created->get_error_message() );
		}
		$secrets[ 'password:' . $login ] = $created[0];
	}
	file_put_contents( $dir . '/secrets.json', wp_json_encode( $secrets ) );
	flush_rewrite_rules( false );
	file_put_contents(
		$dir . '/setup.json',
		wp_json_encode(
			array(
				'versions'  => 'WordPress ' . $GLOBALS['wp_version'] . ' · PHP ' . PHP_VERSION,
				'elementor' => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : null,
			)
		)
	);
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
