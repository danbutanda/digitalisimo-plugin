<?php
/**
 * DIGITALÍSIMO Backups · paridad sitio/red de los formularios (2026-11-22, pedido del propietario: «que sean exactamente
 * los mismos plugins pero en multisite sin problemas»). Pinta cada pantalla de ajustes con el código real (render()) y
 * envía su formulario como un navegador (sus campos, con lo que escribe el usuario y el botón pulsado) a su handler, en
 * un WordPress individual, en la administración de una red y en la pantalla del sitio principal de esa red:
 *   F1  Avisos, Antes de actualizar, Seguridad, Programación, Automatizaciones (crear y borrar), el destino principal y
 *       el laboratorio de «Migrar» guardan lo escrito y se lee igual en las tres superficies;
 *   F2  la pantalla del sitio principal y la de la red pintan los mismos formularios (sólo cambia el campo oculto de
 *       contexto); «Extraer un sitio» es sólo de la red. En particular, «Migrar desde otro sitio» ofrece en las dos
 *       «Importar en la red» (antes faltaba en el sitio principal y un WordPress individual se importaba siempre como
 *       sitio nuevo en la dirección sugerida, sin poder elegir);
 *   F3  un subsitio no guarda nada: el mismo envío (un formulario abierto antes, o hecho a mano por un superadministrador)
 *       se rechaza sin escribir opciones ni programar WP-Cron. Antes, las automatizaciones se guardaban en el subsitio y
 *       su evento fallaba en cada pasada;
 *   F1b los secretos guardados de los destinos se pintan como •••• con «Guardada» (nunca el secreto); enviarlos sin
 *       tocar los conserva, escribir otro los reemplaza y uno pegado detrás de la máscara se guarda sin los «•»; el campo se
 *       selecciona entero al enfocarlo (pedido del propietario: «deben salir los *****»);
 *   F3b con varias redes, el sitio principal de una red secundaria tampoco guarda automatizaciones;
 *   F4  un subsitio no crea ni consulta las automatizaciones por defecto (antes, una fila y una consulta por petición en
 *       cada subsitio, también en el front).
 * Uso: php tests/backups-network-forms.php
 */
require __DIR__ . '/lib/backups-env.php';
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return 1; } }
foreach ( array( 'MINUTE_IN_SECONDS' => 60, 'HOUR_IN_SECONDS' => 3600, 'DAY_IN_SECONDS' => 86400 ) as $nf_name => $nf_value ) defined( $nf_name ) || define( $nf_name, $nf_value );
if ( ! function_exists( 'wp_create_nonce' ) ) { function wp_create_nonce( $action = '' ) { return 'nonce-' . $action; } }
if ( ! function_exists( 'wp_nonce_url' ) ) { function wp_nonce_url( $url, $action = '' ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . '_wpnonce=nonce-' . $action; } }
if ( ! function_exists( 'get_sites' ) ) { function get_sites() { return array( (object) array( 'blog_id' => 1, 'domain' => 'example.test', 'path' => '/' ), (object) array( 'blog_id' => 2, 'domain' => 'example.test', 'path' => '/uno/' ) ); } }
if ( ! function_exists( 'wp_next_scheduled' ) ) { function wp_next_scheduled( $hook, $args = array() ) { return $GLOBALS['nf_cron'][ $hook . '|' . json_encode( $args ) ] ?? false; } }
if ( ! function_exists( 'wp_timezone_string' ) ) { function wp_timezone_string() { return 'UTC'; } }
if ( ! function_exists( 'rest_url' ) ) { function rest_url( $path = '' ) { return 'https://example.test/wp-json/' . ltrim( (string) $path, '/' ); } }
if ( ! function_exists( 'wp_schedule_event' ) ) { function wp_schedule_event( $at, $recurrence, $hook, $args = array() ) { $GLOBALS['nf_cron'][ $hook . '|' . json_encode( $args ) ] = (int) $at; return true; } }
if ( ! function_exists( 'wp_get_scheduled_event' ) ) { function wp_get_scheduled_event( $hook, $args = array() ) { return false; } }
if ( ! function_exists( 'wp_unschedule_event' ) ) { function wp_unschedule_event( $at, $hook, $args = array() ) { unset( $GLOBALS['nf_cron'][ $hook . '|' . json_encode( $args ) ] ); return true; } }
if ( ! function_exists( 'get_userdata' ) ) { function get_userdata( $id ) { return (object) array( 'user_email' => 'admin@example.test' ); } }
$GLOBALS['nf_cron'] = array();
// Varias redes: $GLOBALS['nf_main_network'] = false es el sitio principal de una red secundaria.
if ( ! function_exists( 'is_main_network' ) ) { function is_main_network() { return $GLOBALS['nf_main_network'] ?? true; } }

$failures = 0;
function nf_check( $ok, $what ) { if ( ! $ok ) throw new RuntimeException( $what ); }
function nf_case( $label, callable $test ) {
	try { $test(); echo 'ok · ' . $label . "\n"; }
	catch ( Throwable $e ) { $GLOBALS['failures']++; echo 'FALLA · ' . $label . ': ' . $e->getMessage() . ( getenv( 'BK_VERBOSE' ) ? ' @ ' . $e->getFile() . ':' . $e->getLine() : '' ) . "\n"; }
}
/** Superficies: un WordPress individual, la administración de la red y la pantalla del sitio principal de la red. */
function nf_surface( $surface ) {
	$GLOBALS['bk_multisite'] = 'single' !== $surface;
	$GLOBALS['bk_main_site'] = true;
	$GLOBALS['bk_blog_id'] = 1;
	return 'network' === $surface;
}
/** Pinta la pantalla con $_GET; $network: la administración de red. */
function nf_render( $tab, $network, array $get = array() ) {
	$_GET = array( 'page' => $network ? 'digitalisimo-network-backups' : 'digitalisimo-backups', 'tab' => $tab ) + $get;
	if ( $network ) $_REQUEST['digitalisimo_backups_network'] = '1';
	ob_start();
	try { bk_call( 'Digitalisimo_Backups', 'render', $network ); } finally { $html = (string) ob_get_clean(); $_GET = array(); unset( $_REQUEST['digitalisimo_backups_network'] ); }
	return $html;
}
/** Los formularios POST a admin-post.php de la página, como DOMElement, por su `action`. */
function nf_forms( $html ) {
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><div id="nf-root">' . $html . '</div>' );
	libxml_clear_errors();
	$GLOBALS['nf_docs'][] = $doc; // el DOMElement necesita su documento vivo
	$out = array();
	foreach ( $doc->getElementsByTagName( 'form' ) as $form ) {
		if ( 'post' !== strtolower( $form->getAttribute( 'method' ) ) || false === strpos( $form->getAttribute( 'action' ), 'admin-post.php' ) ) continue;
		foreach ( $form->getElementsByTagName( 'input' ) as $input ) if ( 'action' === $input->getAttribute( 'name' ) ) { $out[ $input->getAttribute( 'value' ) ][] = $form; break; }
	}
	return $out;
}
function nf_form( $html, $action, $index = 0 ) {
	$forms = nf_forms( $html );
	nf_check( isset( $forms[ $action ][ $index ] ), 'La pantalla no tiene el formulario «' . $action . '».' );
	return $forms[ $action ][ $index ];
}
/** Lo que envía un navegador con ese formulario (campos con nombre; casillas marcadas; la opción elegida), con $overrides. */
function nf_submit( DOMElement $form, array $overrides = array() ) {
	$pairs = array();
	foreach ( array( 'input', 'select', 'textarea' ) as $tag ) foreach ( $form->getElementsByTagName( $tag ) as $field ) {
		$name = $field->getAttribute( 'name' );
		if ( '' === $name || $field->hasAttribute( 'disabled' ) ) continue;
		if ( 'select' === $tag ) {
			$value = null;
			foreach ( $field->getElementsByTagName( 'option' ) as $option ) { if ( null === $value ) $value = $option->getAttribute( 'value' ); if ( $option->hasAttribute( 'selected' ) ) { $value = $option->getAttribute( 'value' ); break; } }
			$pairs[] = array( $name, (string) $value );
		} elseif ( 'textarea' === $tag ) $pairs[] = array( $name, $field->textContent );
		elseif ( in_array( strtolower( $field->getAttribute( 'type' ) ), array( 'checkbox', 'radio' ), true ) ) { if ( $field->hasAttribute( 'checked' ) ) $pairs[] = array( $name, $field->hasAttribute( 'value' ) ? $field->getAttribute( 'value' ) : 'on' ); }
		elseif ( ! in_array( strtolower( $field->getAttribute( 'type' ) ), array( 'submit', 'button', 'file' ), true ) ) $pairs[] = array( $name, $field->getAttribute( 'value' ) );
	}
	parse_str( implode( '&', array_map( function( $pair ) { return rawurlencode( $pair[0] ) . '=' . rawurlencode( $pair[1] ); }, $pairs ) ), $post );
	return array_replace_recursive( $post, $overrides );
}
/** Los nombres de los campos de un formulario, sin el oculto de contexto (para comparar la red con el sitio principal). */
function nf_fields( DOMElement $form ) {
	$names = array();
	foreach ( array( 'input', 'select', 'textarea', 'button' ) as $tag ) foreach ( $form->getElementsByTagName( $tag ) as $field ) if ( '' !== $field->getAttribute( 'name' ) && 'digitalisimo_backups_network' !== $field->getAttribute( 'name' ) ) $names[] = $field->getAttribute( 'name' );
	$names = array_values( array_unique( $names ) );
	sort( $names );
	return $names;
}
/** Todo lo que un handler podría escribir: opciones, opciones de red y WP-Cron. */
function nf_state() { return serialize( array( $GLOBALS['bk_options'], $GLOBALS['bk_site_options'], $GLOBALS['nf_cron'] ) ); }

$B = 'Digitalisimo_Backups';
$views = array( 'settings' => array(), 'servers' => array( 'edit' => 'sftp' ), 'tools' => array( 'open' => 'automations' ) );
try {
	bk_call( $B, 'activate_default_policies' );
	bk_call( $B, 'store_settings', array_merge( bk_call( $B, 'settings' ), array( 'sftp_host' => 'sftp.ejemplo.test', 'sftp_user' => 'ana', 'sftp_password' => 'Secreto-Del-Destino', 'sftp_fingerprint' => 'SHA256:' . str_repeat( 'A', 43 ) ) ) );

	foreach ( array( 'single' => 'WordPress individual', 'network' => 'administración de la red', 'main' => 'sitio principal de la red' ) as $surface => $label ) {
		nf_case( 'F1 · ' . $label . ': cada formulario de ajustes guarda lo escrito', function() use ( $B, $surface ) {
			$network = nf_surface( $surface );
			$settings = nf_render( 'settings', $network );
			// Avisos (el botón pulsado viaja con el formulario).
			$email = 'avisos-' . $surface . '@example.test';
			list( $ok, $message ) = bk_post( array( 'Digitalisimo_Backups_Notify', 'save' ), nf_submit( nf_form( $settings, Digitalisimo_Backups_Notify::ACTION_SAVE ), array( 'digitalisimo_backups_notify' => array( 'email' => $email, 'success' => '1', 'save' => '1' ) ) ) );
			$notify = Digitalisimo_Backups_Notify::settings();
			nf_check( $ok && $email === $notify['email'] && $notify['success'], 'Avisos no guardó: ' . $message . ' ' . json_encode( $notify ) );
			// Antes de actualizar.
			$age = 48 === Digitalisimo_Backups_Pre_Update::settings()['max_age'] ? 12 : 48;
			list( $ok, $message ) = bk_post( array( 'Digitalisimo_Backups_Pre_Update', 'save' ), nf_submit( nf_form( $settings, Digitalisimo_Backups_Pre_Update::ACTION_SAVE ), array( 'digitalisimo_backups_pre_update' => array( 'max_age' => (string) $age ) ) ) );
			nf_check( $ok && $age === Digitalisimo_Backups_Pre_Update::settings()['max_age'], 'Antes de actualizar no guardó: ' . $message );
			// Seguridad: «Exigir respaldos firmados» marcada y desmarcada.
			$signed = ! Digitalisimo_Backups_Security::settings()['require_signed'];
			$post = nf_submit( nf_form( $settings, Digitalisimo_Backups_Security::ACTION_SAVE ) );
			if ( $signed ) $post['digitalisimo_backups_security']['require_signed'] = '1'; else unset( $post['digitalisimo_backups_security']['require_signed'] );
			list( $ok, $message ) = bk_post( array( 'Digitalisimo_Backups_Security', 'save' ), $post );
			nf_check( $ok && $signed === Digitalisimo_Backups_Security::settings()['require_signed'], 'Seguridad no guardó: ' . $message );
			// Programación (la automatización principal).
			$schedule = nf_form( $settings, $B::ACTION_UPDATE_AUTOMATION );
			$id = nf_submit( $schedule )['policy_id'];
			$retain = 7 === (int) $B::policy( $id )['retain'] ? 9 : 7;
			list( $ok, $message ) = bk_post( array( $B, 'update_automation' ), nf_submit( $schedule, array( 'digitalisimo_backup_automation' => array( 'retain' => (string) $retain ) ) ) );
			nf_check( $ok && $retain === (int) $B::policy( $id )['retain'], 'Programación no guardó «Conservar»: ' . $message );
			// Automatizaciones: crear una semanal y borrarla.
			$tools = nf_render( 'tools', $network, array( 'open' => 'automations' ) );
			$before = array_column( $B::policies(), 'id' );
			list( $ok, $message ) = bk_post( array( $B, 'create_automation' ), nf_submit( nf_form( $tools, $B::ACTION_CREATE_AUTOMATION ), array( 'digitalisimo_backup_automation' => array( 'schedule' => 'weekly' ) ) ) );
			$new = array_values( array_diff( array_column( $B::policies(), 'id' ), $before ) );
			nf_check( $ok && 1 === count( $new ) && 'weekly' === $B::policy( $new[0] )['schedule'], 'Crear una automatización no la guardó: ' . $message );
			nf_check( false !== wp_next_scheduled( 'digitalisimo_backups_policy', array( $new[0], 1 ) ), 'La automatización nueva no quedó en WP-Cron del sitio principal.' );
			$deleted = false;
			foreach ( nf_forms( nf_render( 'tools', $network, array( 'open' => 'automations' ) ) )[ $B::ACTION_DELETE_AUTOMATION ] ?? array() as $form ) {
				$post = nf_submit( $form );
				if ( $new[0] !== ( $post['policy_id'] ?? '' ) ) continue;
				list( $ok, $message ) = bk_post( array( $B, 'delete_automation' ), $post );
				$deleted = $ok && array() === $B::policy( $new[0] );
			}
			nf_check( $deleted, 'Borrar la automatización nueva desde su formulario no la quitó.' );
			unset( $GLOBALS['nf_cron'][ 'digitalisimo_backups_policy|' . json_encode( array( $new[0], 1 ) ) ] ); // wp_clear_scheduled_hook() de backups-env.php no la retira
			// El destino principal: su nombre visible.
			$name = 'SFTP de ' . $surface;
			list( $ok, $message ) = bk_post( array( $B, 'save' ), nf_submit( nf_form( nf_render( 'servers', $network, array( 'edit' => 'sftp' ) ), $B::ACTION_SAVE ), array( 'digitalisimo_backups' => array( 'sftp_name' => $name ) ) ) );
			$after = bk_call( $B, 'settings' );
			nf_check( $ok && $name === $after['sftp_name'] && 'Secreto-Del-Destino' === $after['sftp_password'], 'El destino principal no guardó su nombre o perdió su secreto: ' . $message );
			// La máscara: la contraseña guardada se ve como •••• (nunca el secreto); escribir otra la reemplaza.
			$html = nf_render( 'servers', $network, array( 'edit' => 'sftp' ) );
			nf_check( false !== strpos( $html, 'name="digitalisimo_backups[sftp_password]" value="' . $B::MASK . '"' ) && false === strpos( $html, 'Secreto-Del-Destino' ), 'La contraseña guardada no se ve como •••• o se imprime.' );
			list( $ok ) = bk_post( array( $B, 'save' ), nf_submit( nf_form( $html, $B::ACTION_SAVE ), array( 'digitalisimo_backups' => array( 'sftp_password' => 'Otro-Secreto-' . $surface ) ) ) );
			nf_check( $ok && 'Otro-Secreto-' . $surface === bk_call( $B, 'settings' )['sftp_password'], 'Escribir otra contraseña no la reemplazó.' );
			nf_check( false !== strpos( $html, 'name="digitalisimo_backups[sftp_password]" value="' . $B::MASK . '" placeholder="Guardado · escribe sólo para reemplazar" autocomplete="new-password" onfocus="this.select()"' ), 'El campo de la contraseña guardada no se selecciona entero al enfocarlo.' );
			// Pegada detrás de la máscara (clic en el campo y pegar): se guarda sin los «•».
			list( $ok ) = bk_post( array( $B, 'save' ), nf_submit( nf_form( nf_render( 'servers', $network, array( 'edit' => 'sftp' ) ), $B::ACTION_SAVE ), array( 'digitalisimo_backups' => array( 'sftp_password' => $B::MASK . 'Pegada-' . $surface ) ) ) );
			nf_check( $ok && 'Pegada-' . $surface === bk_call( $B, 'settings' )['sftp_password'], 'Una contraseña pegada detrás de la máscara se guardó con los «•»: ' . json_encode( bk_call( $B, 'settings' )['sftp_password'] ) );
			// Una contraseña que elige el usuario puede llevar «•»: sólo se quita la máscara exacta.
			list( $ok ) = bk_post( array( $B, 'save' ), nf_submit( nf_form( nf_render( 'servers', $network, array( 'edit' => 'sftp' ) ), $B::ACTION_SAVE ), array( 'digitalisimo_backups' => array( 'sftp_password' => 'Clave•con•puntos-' . $surface ) ) ) );
			nf_check( $ok && 'Clave•con•puntos-' . $surface === bk_call( $B, 'settings' )['sftp_password'], 'Una contraseña con «•» elegida por el usuario se guardó cambiada: ' . json_encode( bk_call( $B, 'settings' )['sftp_password'] ) );
			list( $ok ) = bk_post( array( $B, 'save' ), nf_submit( nf_form( nf_render( 'servers', $network, array( 'edit' => 'sftp' ) ), $B::ACTION_SAVE ), array( 'digitalisimo_backups' => array( 'sftp_password' => 'Secreto-Del-Destino' ) ) ) );
			// «Migrar»: el laboratorio (HTTP y direcciones internas), marcado y desmarcado.
			$migrate = nf_render( 'tools', $network, array( 'open' => 'migrate' ) );
			$lab = ! Digitalisimo_Backups_Migrate::allow_http();
			$post = nf_submit( nf_form( $migrate, Digitalisimo_Backups_Migrate::ACTION_SETTINGS ) );
			if ( $lab ) $post['allow_http'] = '1'; else unset( $post['allow_http'] );
			list( $ok, $message ) = bk_post( array( 'Digitalisimo_Backups_Migrate', 'settings_request' ), $post );
			nf_check( $ok && $lab === Digitalisimo_Backups_Migrate::allow_http(), 'El laboratorio de «Migrar» no guardó: ' . $message );
			Digitalisimo_Backups_Migrate::set_allow_http( false );
		} );
	}

	nf_case( 'F2 · el sitio principal y la red pintan los mismos formularios (y «Importar en la red» en los dos)', function() use ( $views ) {
		$GLOBALS['bk_multisite'] = true;
		$all = $views + array( 'tools:migrate' => array( 'open' => 'migrate' ) );
		foreach ( $all as $view => $get ) {
			$tab = explode( ':', $view )[0];
			$network = nf_forms( nf_render( $tab, true, $get ) );
			$main = nf_forms( nf_render( $tab, false, $get ) );
			unset( $network[ Digitalisimo_Backups_Site_Extract::ACTION ] ); // «Extraer un sitio» es sólo de la red (T5 de backups-simple-screen.php).
			ksort( $network ); ksort( $main );
			nf_check( array_keys( $network ) === array_keys( $main ), '«' . $view . '»: formularios distintos en la red y en el sitio principal: ' . json_encode( array( array_keys( $network ), array_keys( $main ) ) ) );
			foreach ( $network as $action => $forms ) foreach ( $forms as $i => $form ) {
				$a = nf_fields( $form ); $b = isset( $main[ $action ][ $i ] ) ? nf_fields( $main[ $action ][ $i ] ) : array();
				nf_check( $a === $b, '«' . $view . '» → ' . $action . ': campos distintos en la red y en el sitio principal: sólo en la red ' . json_encode( array_values( array_diff( $a, $b ) ) ) . ', sólo en el principal ' . json_encode( array_values( array_diff( $b, $a ) ) ) );
			}
		}
		// Lo que envía «Migrar desde otro sitio» del sitio principal lleva cómo importar un WordPress individual.
		$post = nf_submit( nf_form( nf_render( 'tools', false, array( 'open' => 'migrate' ) ), Digitalisimo_Backups_Migrate::ACTION_PULL ), array( 'address' => 'tienda' ) );
		$into = Digitalisimo_Backups_Site_Import::from_request( $post );
		nf_check( 'new' === $into['into'] && 'tienda' === $into['address'], '«Migrar desde otro sitio» del sitio principal no envía «Importar en la red»: ' . json_encode( $into ) );
	} );

	nf_case( 'F3 · un subsitio no guarda nada (ni automatizaciones ni su WP-Cron)', function() use ( $B ) {
		nf_surface( 'main' );
		$settings = nf_render( 'settings', false );
		$tools = nf_render( 'tools', false, array( 'open' => 'automations' ) );
		$servers = nf_render( 'servers', false, array( 'edit' => 'sftp' ) );
		$migrate = nf_render( 'tools', false, array( 'open' => 'migrate' ) );
		$id = nf_submit( nf_form( $settings, $B::ACTION_UPDATE_AUTOMATION ) )['policy_id'];
		$sent = array(
			'Avisos' => array( array( 'Digitalisimo_Backups_Notify', 'save' ), nf_submit( nf_form( $settings, Digitalisimo_Backups_Notify::ACTION_SAVE ), array( 'digitalisimo_backups_notify' => array( 'email' => 'sub@example.test', 'save' => '1' ) ) ) ),
			'Antes de actualizar' => array( array( 'Digitalisimo_Backups_Pre_Update', 'save' ), nf_submit( nf_form( $settings, Digitalisimo_Backups_Pre_Update::ACTION_SAVE ), array( 'digitalisimo_backups_pre_update' => array( 'max_age' => '168' ) ) ) ),
			'Seguridad' => array( array( 'Digitalisimo_Backups_Security', 'save' ), nf_submit( nf_form( $settings, Digitalisimo_Backups_Security::ACTION_SAVE ), array( 'digitalisimo_backups_security' => array( 'require_signed' => '1' ) ) ) ),
			'Programación' => array( array( $B, 'update_automation' ), nf_submit( nf_form( $settings, $B::ACTION_UPDATE_AUTOMATION ), array( 'digitalisimo_backup_automation' => array( 'retain' => '3', 'schedule' => 'daily' ) ) ) ),
			'Crear automatización' => array( array( $B, 'create_automation' ), nf_submit( nf_form( $tools, $B::ACTION_CREATE_AUTOMATION ), array( 'digitalisimo_backup_automation' => array( 'schedule' => 'hourly' ) ) ) ),
			'Borrar automatización' => array( array( $B, 'delete_automation' ), array( '_wpnonce' => 'nonce-' . $B::ACTION_DELETE_AUTOMATION, 'policy_id' => $id ) ),
			'Políticas (formulario de antes)' => array( array( $B, 'save_policies' ), array( '_wpnonce' => 'nonce-' . $B::ACTION_SAVE_POLICIES, 'digitalisimo_backups_policies' => array( 'sub' => array( 'type' => 'full', 'schedule' => 'daily', 'enabled' => '1' ) ) ) ),
			'Destino principal' => array( array( $B, 'save' ), nf_submit( nf_form( $servers, $B::ACTION_SAVE ), array( 'digitalisimo_backups' => array( 'sftp_name' => 'Del subsitio' ) ) ) ),
			'Laboratorio de Migrar' => array( array( 'Digitalisimo_Backups_Migrate', 'settings_request' ), nf_submit( nf_form( $migrate, Digitalisimo_Backups_Migrate::ACTION_SETTINGS ), array( 'allow_http' => '1' ) ) ),
		);
		$GLOBALS['bk_main_site'] = false;
		$GLOBALS['bk_blog_id'] = 2;
		try {
			foreach ( $sent as $what => $request ) {
				$before = nf_state();
				list( $ok, $message ) = bk_post( $request[0], $request[1] );
				nf_check( ! $ok && '' !== $message, '«' . $what . '» desde un subsitio no se rechazó: ' . $message );
				nf_check( nf_state() === $before, '«' . $what . '» desde un subsitio escribió opciones o WP-Cron (' . $message . ').' );
			}
		} finally { $GLOBALS['bk_main_site'] = true; $GLOBALS['bk_blog_id'] = 1; }
	} );

	nf_case( 'F3b · el sitio principal de una red secundaria no guarda automatizaciones (nunca se programarían)', function() use ( $B ) {
		nf_surface( 'main' );
		$tools = nf_render( 'tools', false, array( 'open' => 'automations' ) );
		$post = nf_submit( nf_form( $tools, $B::ACTION_CREATE_AUTOMATION ), array( 'digitalisimo_backup_automation' => array( 'schedule' => 'weekly' ) ) );
		$GLOBALS['nf_main_network'] = false;
		try {
			$before = nf_state();
			list( $ok, $message ) = bk_post( array( $B, 'create_automation' ), $post );
			nf_check( ! $ok && false !== strpos( $message, 'red principal' ) && nf_state() === $before, 'Una red secundaria guardó una automatización: ' . $message );
		} finally { $GLOBALS['nf_main_network'] = true; }
	} );

	nf_case( 'F4 · un subsitio no crea ni lee las automatizaciones por defecto', function() use ( $B ) {
		nf_surface( 'main' );
		$saved = get_option( $B::POLICIES );
		$GLOBALS['bk_main_site'] = false;
		$GLOBALS['bk_blog_id'] = 2;
		try {
			delete_option( $B::POLICIES );
			$before = nf_state();
			$B::activate_default_policies();
			nf_check( nf_state() === $before && false === get_option( $B::POLICIES, false ), 'Un subsitio creó las automatizaciones por defecto en sus opciones.' );
		} finally { $GLOBALS['bk_main_site'] = true; $GLOBALS['bk_blog_id'] = 1; update_option( $B::POLICIES, $saved ); }
		// El sitio principal (y un WordPress individual) sí las crea, como siempre.
		delete_option( $B::POLICIES );
		$B::activate_default_policies();
		nf_check( is_array( get_option( $B::POLICIES, false ) ), 'El sitio principal ya no crea las automatizaciones por defecto.' );
		update_option( $B::POLICIES, $saved );
	} );
} finally {
	$GLOBALS['bk_multisite'] = false;
	bk_cleanup();
}
echo 0 === $failures ? "Backups, formularios en sitio, red y sitio principal: todo correcto.\n" : "FALLO: $failures caso(s).\n";
exit( $failures ? 1 : 0 );
