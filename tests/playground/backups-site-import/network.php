<?php
/**
 * Preparación de la red de backups-site-import.sh (paso runPHP del blueprint, tras enableMultisite): red por
 * subdirectorios `red.test` con el principal y el sitio 2 (`/dos/`, con su entrada, su medio en uploads/sites/2 y su
 * administrador «dueno2»), Backups activo en la red, la sonda como mu-plugin y, en la red, los usuarios con los que el
 * respaldo del origen choca: «edita» (mismo usuario y correo: la misma persona), «autora-red» (el correo de la autora del
 * origen), «socia» (el nombre de la suscriptora del origen, con otro correo: otra persona). Deja /digi-probe/red.json;
 * un fallo, en /digi-probe/setup-error.txt.
 */
$_SERVER['HTTP_HOST'] = 'red.test';
$_SERVER['REQUEST_URI'] = '/';
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-admin/includes/ms.php';
$dir = '/digi-probe';
try {
	if ( ! is_multisite() ) throw new RuntimeException( 'La red no quedó en Multisite.' );
	$activated = activate_plugin( 'digitalisimo-backups/digitalisimo-backups.php', '', true );
	if ( is_wp_error( $activated ) ) throw new RuntimeException( 'No se activó Backups en la red: ' . $activated->get_error_message() );
	wp_mkdir_p( WPMU_PLUGIN_DIR );
	if ( ! copy( $dir . '/probe.php', WPMU_PLUGIN_DIR . '/digi-si-probe.php' ) ) throw new RuntimeException( 'No se copió la sonda.' );
	$passwords = array( 'admin' => 'password' );
	$users = array( 'admin' => get_user_by( 'login', 'admin' )->ID );
	foreach ( array( 'dueno2' => 'dueno2@red.test', 'edita' => 'edita@ejemplo.test', 'autora-red' => 'autora@origen.test', 'socia' => 'socia@red.test' ) as $login => $email ) {
		$passwords[ $login ] = 'Red-' . $login . '-' . wp_generate_password( 10, false );
		$id = wp_insert_user( array( 'user_login' => $login, 'user_pass' => $passwords[ $login ], 'user_email' => $email, 'role' => '', 'display_name' => ucfirst( $login ) . ' de la red', 'first_name' => ucfirst( $login ) . ' de la red' ) );
		if ( is_wp_error( $id ) ) throw new RuntimeException( 'No se creó ' . $login . ': ' . $id->get_error_message() );
		$users[ $login ] = $id;
	}
	$network = get_network();
	$two = wp_insert_site( array( 'domain' => $network->domain, 'path' => $network->path . 'dos/', 'title' => 'Sitio dos', 'user_id' => $users['dueno2'] ) );
	if ( is_wp_error( $two ) ) throw new RuntimeException( 'No se creó el sitio 2: ' . $two->get_error_message() );
	add_user_to_blog( $two, $users['edita'], 'author' );
	add_user_to_blog( $two, $users['socia'], 'subscriber' );
	switch_to_blog( $two );
	$upload = wp_upload_dir( '2026/11' );
	wp_mkdir_p( $upload['path'] );
	file_put_contents( $upload['path'] . '/dos.png', base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==' ) );
	wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $users['dueno2'], 'post_title' => 'Entrada del sitio dos', 'post_content' => '<img src="' . esc_url( $upload['url'] . '/dos.png' ) . '">' ) );
	restore_current_blog();
	wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $users['admin'], 'post_title' => 'Entrada del sitio principal', 'post_content' => '<p>Principal.</p>' ) );
	file_put_contents( $dir . '/red.json', wp_json_encode( array( 'passwords' => $passwords, 'site2' => (int) $two ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
