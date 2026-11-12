<?php
/**
 * DIGITALÍSIMO Backups · pantalla «interfaz simple» (1.0.74). Pinta cada pestaña con el código real (render()) en un
 * sitio individual, en la administración de red y en un subsitio, y comprueba lo que ve el usuario con lo plegado
 * cerrado (sin el contenido de un <details> cerrado, de lo oculto ni de los campos ocultos):
 *   T1  cuatro pestañas (Respaldos, Ajustes, Destinos, Herramientas) y las de antes (security, notifications, migrate)
 *       abren su sitio nuevo;
 *   T2  «Respaldos»: estado, «Respaldar ahora», ningún campo a la vista, ≤ 150 palabras, ningún texto de más de 140
 *       caracteres; cada fila con Restaurar, Descargar y Borrar (y Bloquear en «⋯»); el panel de restaurar bajo su
 *       fila, oculto salvo ?restore=<archivo>; «Trabajos» una sola vez y oculto sin trabajos en curso;
 *   T3  «Ajustes»: la programación edita la automatización principal con su handler y vuelve a «Ajustes»; avisos,
 *       antes de actualizar (plegado) y cifrado (plegado);
 *   T4  «Destinos»: la lista sin ningún campo; un solo formulario al pedirlo (añadir u otra conexión, o la principal);
 *   T5  «Herramientas»: todo plegado; «Extraer un sitio» sólo en la red; ?open= abre su sección;
 *   T6  una sola franja de avisos (y el aviso de escritorio no se repite en la pantalla);
 *   T7  subsitio: una línea y el enlace a la red, sin formularios;
 *   T8  ningún secreto ni ruta del almacén en el HTML, y los textos de antes que nombraban otra pantalla ya no están;
 *   T14 (H26 del ensayo E5) con la configuración de un respaldo pendiente, la franja dice a la vista lo que se pierde al
 *       aplicarla (≤ 140 caracteres) y «Aplicar la del respaldo» sólo aplica con APLICAR escrito (lo comprueba el handler).
 * Con --fixture <carpeta> deja las pantallas en HTML para tests/backups-simple-screen-chrome.mjs.
 * Uso: php tests/backups-simple-screen.php [--fixture <carpeta>]
 */
require __DIR__ . '/lib/backups-env.php';
// Lo que la pantalla necesita y el entorno común no simula.
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return 1; } }
foreach ( array( 'MINUTE_IN_SECONDS' => 60, 'HOUR_IN_SECONDS' => 3600, 'DAY_IN_SECONDS' => 86400 ) as $ss_name => $ss_value ) defined( $ss_name ) || define( $ss_name, $ss_value );
if ( ! function_exists( 'wp_create_nonce' ) ) { function wp_create_nonce( $action = '' ) { return 'nonce-' . $action; } }
if ( ! function_exists( 'wp_nonce_url' ) ) { function wp_nonce_url( $url, $action = '' ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . '_wpnonce=nonce-' . $action; } }
if ( ! function_exists( 'get_sites' ) ) { function get_sites() { return array( (object) array( 'blog_id' => 1, 'domain' => 'example.test', 'path' => '/' ), (object) array( 'blog_id' => 2, 'domain' => 'example.test', 'path' => '/uno/' ) ); } }
if ( ! function_exists( 'wp_next_scheduled' ) ) { function wp_next_scheduled( $hook, $args = array() ) { return $GLOBALS['ss_cron'][ $hook . '|' . json_encode( $args ) ] ?? false; } }
if ( ! function_exists( 'wp_timezone_string' ) ) { function wp_timezone_string() { return 'UTC'; } }
if ( ! function_exists( 'rest_url' ) ) { function rest_url( $path = '' ) { return 'https://example.test/wp-json/' . ltrim( (string) $path, '/' ); } }
if ( ! function_exists( 'wp_schedule_event' ) ) { function wp_schedule_event( $at, $recurrence, $hook, $args = array() ) { $GLOBALS['ss_cron'][ $hook . '|' . json_encode( $args ) ] = (int) $at; return true; } }
if ( ! function_exists( 'wp_get_scheduled_event' ) ) { function wp_get_scheduled_event( $hook, $args = array() ) { return false; } }
if ( ! function_exists( 'wp_unschedule_event' ) ) { function wp_unschedule_event( $at, $hook, $args = array() ) { unset( $GLOBALS['ss_cron'][ $hook . '|' . json_encode( $args ) ] ); return true; } }
if ( ! function_exists( 'get_userdata' ) ) { function get_userdata( $id ) { return (object) array( 'user_email' => 'admin@example.test' ); } }

$failures = 0;
function ss_check( $ok, $what ) { if ( ! $ok ) throw new RuntimeException( $what ); }
function ss_case( $label, callable $test ) {
	try {
		$test();
		echo 'ok · ' . $label . "\n";
	} catch ( Throwable $e ) {
		$GLOBALS['failures']++;
		echo 'FALLA · ' . $label . ': ' . $e->getMessage() . ( getenv( 'BK_VERBOSE' ) ? ' @ ' . $e->getFile() . ':' . $e->getLine() : '' ) . "\n";
	}
}
$fixture = null;
foreach ( $argv as $i => $arg ) if ( '--fixture' === $arg ) $fixture = rtrim( (string) ( $argv[ $i + 1 ] ?? '' ), '/' );

/** Pinta la pantalla con $_GET; $network: la administración de red. */
function ss_render( $tab, $network = false, array $get = array() ) {
	$_GET = array( 'page' => $network ? 'digitalisimo-network-backups' : 'digitalisimo-backups' ) + ( null === $tab ? array() : array( 'tab' => $tab ) ) + $get;
	if ( $network ) $_REQUEST['digitalisimo_backups_network'] = '1';
	ob_start();
	try { bk_call( 'Digitalisimo_Backups', 'render', $network ); } finally { $html = (string) ob_get_clean(); $_GET = array(); unset( $_REQUEST['digitalisimo_backups_network'] ); }
	return $html;
}
/**
 * Lo que se ve: textos (con el elemento que los contiene), campos y botones, sin lo que está dentro de un <details>
 * cerrado (salvo su <summary>), de un elemento con `hidden`, de <script> ni los campos ocultos.
 */
function ss_visible( $html ) {
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><div id="ss-root">' . $html . '</div>' );
	libxml_clear_errors();
	$out = array( 'text' => '', 'blocks' => array(), 'fields' => 0, 'buttons' => 0, 'forms' => 0 );
	$walk = function( DOMNode $node ) use ( &$walk, &$out ) {
		foreach ( $node->childNodes as $child ) {
			if ( $child instanceof DOMText ) { $out['text'] .= ' ' . $child->nodeValue; continue; }
			if ( ! $child instanceof DOMElement ) continue;
			$tag = strtolower( $child->tagName );
			if ( in_array( $tag, array( 'script', 'style' ), true ) || $child->hasAttribute( 'hidden' ) ) continue;
			if ( 'input' === $tag && in_array( strtolower( $child->getAttribute( 'type' ) ), array( 'hidden' ), true ) ) continue;
			if ( 'span' === $tag && false !== strpos( $child->getAttribute( 'class' ), 'screen-reader-text' ) ) continue;
			if ( in_array( $tag, array( 'input', 'select', 'textarea' ), true ) && ! in_array( strtolower( $child->getAttribute( 'type' ) ), array( 'submit', 'button' ), true ) ) $out['fields']++;
			if ( in_array( $tag, array( 'select', 'textarea' ), true ) ) continue;
			if ( 'button' === $tag || ( 'a' === $tag && false !== strpos( ' ' . $child->getAttribute( 'class' ) . ' ', ' button ' ) ) ) $out['buttons']++;
			if ( 'form' === $tag ) $out['forms']++;
			if ( 'details' === $tag && ! $child->hasAttribute( 'open' ) ) {
				foreach ( $child->childNodes as $inner ) if ( $inner instanceof DOMElement && 'summary' === strtolower( $inner->tagName ) ) { $before = $out['text']; $walk( $inner ); $out['blocks'][] = array( 'summary', trim( preg_replace( '/\s+/u', ' ', substr( $out['text'], strlen( $before ) ) ) ) ); }
				continue;
			}
			$before = $out['text'];
			$walk( $child );
			if ( in_array( $tag, array( 'p', 'li', 'td', 'th', 'label', 'summary', 'h1', 'h2', 'h3', 'dd', 'dt', 'legend', 'small' ), true ) && ! ss_has_block( $child ) ) $out['blocks'][] = array( $tag, trim( preg_replace( '/\s+/u', ' ', substr( $out['text'], strlen( $before ) ) ) ) );
		}
	};
	$walk( $doc->getElementById( 'ss-root' ) );
	$out['text'] = trim( preg_replace( '/\s+/u', ' ', $out['text'] ) );
	$out['words'] = preg_match_all( '/[\p{L}\p{N}][\p{L}\p{N}\'’._-]*/u', $out['text'] );
	$out['longest'] = array( 0, '' );
	foreach ( $out['blocks'] as $block ) { $length = mb_strlen( $block[1], 'UTF-8' ); if ( $length > $out['longest'][0] ) $out['longest'] = array( $length, $block[0] . ': ' . $block[1] ); }
	return $out;
}
/** Un elemento con otros bloques dentro (párrafos, formularios, tablas…) no es un texto: lo son sus bloques. */
function ss_has_block( DOMElement $element ) {
	foreach ( array( 'p', 'div', 'ul', 'ol', 'table', 'form', 'details', 'section', 'fieldset', 'dl' ) as $tag ) if ( $element->getElementsByTagName( $tag )->length ) return true;
	return false;
}
function ss_count( $html, $needle ) { return substr_count( $html, $needle ); }
/** Los formularios POST a admin-post.php dentro del elemento $id (o de toda la página), como DOMElement. */
function ss_forms( $html, $id = '' ) {
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><div id="ss-root">' . $html . '</div>' );
	libxml_clear_errors();
	$root = $doc->getElementById( '' !== $id ? $id : 'ss-root' );
	$out = array();
	if ( $root ) foreach ( $root->getElementsByTagName( 'form' ) as $form ) if ( 'post' === strtolower( $form->getAttribute( 'method' ) ) && false !== strpos( $form->getAttribute( 'action' ), 'admin-post.php' ) ) $out[] = $form;
	return $out;
}
/** Lo que envía un navegador con ese formulario (campos con nombre; casillas marcadas; la opción elegida), con $overrides. */
function ss_submit( DOMElement $form, array $overrides = array() ) {
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
	$query = implode( '&', array_map( function( $pair ) { return rawurlencode( $pair[0] ) . '=' . rawurlencode( $pair[1] ); }, $pairs ) );
	parse_str( $query, $post );
	return array_replace_recursive( $post, $overrides );
}
/** El valor de digitalisimo_backups_return de un formulario ('' si no lo lleva). */
function ss_return_of( DOMElement $form ) {
	foreach ( $form->getElementsByTagName( 'input' ) as $input ) if ( 'digitalisimo_backups_return' === $input->getAttribute( 'name' ) ) return $input->getAttribute( 'value' );
	return '';
}
/** La pestaña y la sección de la URL a la que redirigió un handler. */
function ss_landing( $url ) {
	parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $query );
	return array( (string) ( $query['tab'] ?? '' ), (string) ( $query['open'] ?? '' ), (string) parse_url( $url, PHP_URL_FRAGMENT ) );
}
$B = 'Digitalisimo_Backups';
$pages = array();
try {
	// Un sitio con dos respaldos registrados, la automatización mensual por defecto programada y un secreto guardado.
	bk_call( $B, 'activate_default_policies' );
	$first = basename( bk_backup( true ) );
	sleep( 1 );
	$second = basename( bk_backup( true ) );
	$history = (array) get_option( $B::HISTORY, array() );
	$GLOBALS['ss_cron'][ 'digitalisimo_backups_policy|' . json_encode( array( 'monthly-full', 1 ) ) ] = strtotime( '2026-12-01 03:00:00 UTC' );
	$secret = 'Secreto-Del-Destino-' . bin2hex( random_bytes( 6 ) );
	bk_call( $B, 'store_settings', array_merge( bk_call( $B, 'settings' ), array( 'sftp_host' => 'sftp.ejemplo.test', 'sftp_user' => 'ana', 'sftp_password' => $secret, 'sftp_fingerprint' => 'SHA256:' . str_repeat( 'A', 43 ) ) ) );
	$storage = bk_storage();

	ss_case( 'T1 · cuatro pestañas y las de antes abren su sitio nuevo', function() use ( $B ) {
		$html = ss_render( null );
		ss_check( preg_match( '#<nav class="digitalisimo-backups-tabs"[^>]*>(.*?)</nav>#s', $html, $nav ), 'No hay navegación de pestañas.' );
		preg_match_all( '#<a [^>]*href="[^"]*tab=([a-z]+)[^"]*"[^>]*>([^<]+)</a>#', $nav[1], $tabs, PREG_SET_ORDER );
		ss_check( array( 'backups' => 'Respaldos', 'settings' => 'Ajustes', 'servers' => 'Destinos', 'tools' => 'Herramientas' ) === array_combine( array_column( $tabs, 1 ), array_column( $tabs, 2 ) ), 'Las pestañas no son Respaldos, Ajustes, Destinos y Herramientas: ' . json_encode( $tabs ) );
		ss_check( false !== strpos( $nav[1], 'class="is-active" aria-current="page" href="https://example.test/wp-admin/admin.php?page=digitalisimo-backups&amp;tab=backups"' ), '«Respaldos» no es la pestaña inicial.' );
		foreach ( array( 'security' => array( 'settings', 'security' ), 'notifications' => array( 'settings', 'notifications' ), 'migrate' => array( 'tools', 'migrate' ), 'servers' => array( 'servers', '' ), 'nada' => array( 'backups', '' ) ) as $old => $want ) ss_check( $want === $B::screen_route( $old, '' ), 'La pestaña «' . $old . '» no abre ' . json_encode( $want ) . ': ' . json_encode( $B::screen_route( $old, '' ) ) );
		$security = ss_render( 'security' );
		ss_check( false !== strpos( $security, 'data-tab="settings"' ) && preg_match( '#<details class="digitalisimo-backups-section" id="digitalisimo-backups-section-security" open>#', $security ), '?tab=security no abre «Ajustes» con «Cifrado y firma» abierto.' );
		$migrate = ss_render( 'migrate' );
		ss_check( false !== strpos( $migrate, 'data-tab="tools"' ) && preg_match( '#id="digitalisimo-backups-section-migrate" open>#', $migrate ), '?tab=migrate no abre «Herramientas» con «Migrar» abierto.' );
	} );

	$home = ss_render( 'backups' );
	$pages['respaldos'] = $home;
	ss_case( 'T2 · «Respaldos»: estado, botón y nada más a la vista', function() use ( $home, $B, $history ) {
		$seen = ss_visible( $home );
		ss_check( 0 === $seen['fields'], 'La pantalla principal enseña ' . $seen['fields'] . ' campos.' );
		ss_check( $seen['words'] <= 150, 'La pantalla principal tiene ' . $seen['words'] . ' palabras (máximo 150): ' . $seen['text'] );
		ss_check( $seen['longest'][0] <= 140, 'Un texto visible pasa de 140 caracteres: ' . $seen['longest'][1] );
		ss_check( false !== strpos( $home, '<form id="digitalisimo-backups-manual-form"' ) && false !== strpos( $home, 'value="' . $B::ACTION_RUN . '"' ) && false !== strpos( $home, '>Respaldar ahora</button>' ), 'Falta «Respaldar ahora» con su formulario de siempre.' );
		ss_check( false !== strpos( $seen['text'], 'Último respaldo ' . substr( (string) $history[0]['date'], 0, 16 ) ) && false !== strpos( $seen['text'], 'Próximo 2026-12-01 03:00 · Mensual · se conservan 4' ), 'El estado no dice el último respaldo ni el próximo: ' . $seen['text'] );
		foreach ( array( 'Antes de actualizar', 'Subir un respaldo</h2>', 'Restaurar respaldo local', 'Automatizaciones de respaldo', 'UpdraftPlus', 'Genera un ZIP integral' ) as $gone ) ss_check( false === strpos( $home, $gone ), 'La pantalla principal sigue mostrando «' . $gone . '».' );
		ss_check( 1 === ss_count( $home, 'id="digitalisimo-backups-jobs"' ) && preg_match( '#<section class="digitalisimo-backups-card digitalisimo-backups-jobs" id="digitalisimo-backups-jobs" data-active="0" data-hide-idle="1" hidden>#', $home ), '«Trabajos» no está una sola vez y oculta sin trabajos en curso.' );
		ss_check( 1 === ss_count( $home, 'id="digitalisimo-backups-manual-progress"' ), 'El avance del botón no está una sola vez.' );
	} );
	ss_case( 'T2 · cada fila: Restaurar, Descargar y Borrar; Bloquear en «⋯»; restaurar bajo la fila', function() use ( $home, $B, $first, $second ) {
		ss_check( 2 === ss_count( $home, '<tr class="digitalisimo-backups-row"' ), 'La lista no tiene las dos filas.' );
		foreach ( array( $first, $second ) as $file ) {
			ss_check( preg_match( '#<tr class="digitalisimo-backups-row" data-file="' . preg_quote( $file, '#' ) . '">(.*?)</tr>#s', $home, $row ), 'Falta la fila de ' . $file );
			ss_check( preg_match( '#<a class="button button-small" data-restore-toggle="(digitalisimo-backups-restore-\d+)"[^>]*href="[^"]*restore=[^"]*">Restaurar</a>#', $row[1], $toggle ), 'La fila no tiene «Restaurar»: ' . $file );
			ss_check( false !== strpos( $row[1], 'action=' . $B::ACTION_DOWNLOAD ) && false !== strpos( $row[1], '>Descargar</a>' ), 'La fila no tiene «Descargar»: ' . $file );
			ss_check( false !== strpos( $row[1], 'name="action" value="' . $B::ACTION_DELETE . '"><input type="hidden" name="file" value="' . $file . '"><button class="button button-small">Borrar</button>' ), 'La fila no tiene «Borrar»: ' . $file );
			ss_check( preg_match( '#<details class="digitalisimo-backups-more"><summary[^>]*>⋯</summary>.*value="' . $B::ACTION_LOCK . '".*>Bloquear</button>.*<dt>Archivo</dt><dd>' . preg_quote( $file, '#' ) . '</dd>#s', $row[1] ), '«⋯» no tiene «Bloquear» ni los detalles: ' . $file );
			ss_check( preg_match( '#<tr class="digitalisimo-backups-restore-row" id="' . $toggle[1] . '" hidden><td colspan="4"><form [^>]*>.*name="action" value="' . $B::ACTION_RESTORE . '"><input type="hidden" name="backup_file" value="' . preg_quote( $file, '#' ) . '">.*escribe RESTAURAR para confirmar.*name="confirmation" placeholder="RESTAURAR".*name="addresses" value="current" checked.*name="backup_secret".*</form></td></tr>#s', $home ), 'El panel de restaurar de ' . $file . ' no está oculto bajo su fila con el formulario de siempre.' );
		}
		$open = ss_render( 'backups', false, array( 'restore' => $first ) );
		ss_check( preg_match( '#data-restore-toggle="(digitalisimo-backups-restore-\d+)"[^>]*aria-expanded="true"[^>]*restore=' . preg_quote( rawurlencode( $first ), '#' ) . '#', $open, $m ) && preg_match( '#<tr class="digitalisimo-backups-restore-row" id="' . $m[1] . '"><td#', $open ), 'Con ?restore=<archivo> el panel no se pinta abierto (sin JavaScript).' );
		$seen = ss_visible( $open );
		ss_check( 1 === $seen['fields'] && $seen['longest'][0] <= 140, 'El panel abierto enseña algo más que la confirmación (' . $seen['fields'] . ' campos) o un texto largo: ' . $seen['longest'][1] );
	} );

	$settings = ss_render( 'settings' );
	$pages['ajustes'] = $settings;
	ss_case( 'T3 · «Ajustes»: programación con su handler, avisos, antes de actualizar y cifrado plegados', function() use ( $settings, $B ) {
		ss_check( preg_match( '#<form method="post"[^>]*class="digitalisimo-backups-schedule">.*name="action" value="' . $B::ACTION_UPDATE_AUTOMATION . '"><input type="hidden" name="policy_id" value="monthly-full">.*name="digitalisimo_backups_return" value="settings:schedule".*<option value="monthly" selected="selected">Mensual</option>.*name="digitalisimo_backup_automation\[retain\]" value="4".*</form>#s', $settings ), 'La programación no edita la automatización mensual con su handler de siempre.' );
		ss_check( false !== strpos( $settings, 'name="digitalisimo_backups_notify[email]"' ) && false !== strpos( $settings, 'value="' . Digitalisimo_Backups_Notify::ACTION_SAVE . '"' ), 'Faltan los avisos por correo.' );
		ss_check( preg_match( '#<details class="digitalisimo-backups-help digitalisimo-backups-pre-update-form"><summary>Cambiar</summary>.*digitalisimo_backups_return" value="settings:pre-update".*digitalisimo_backups_pre_update\[core\]#s', $settings ), '«Antes de actualizar» no está plegado en «Cambiar».' );
		ss_check( preg_match( '#<details class="digitalisimo-backups-section" id="digitalisimo-backups-section-security"><summary><strong>Cifrado y firma</strong>#', $settings ), '«Cifrado y firma» no está plegado.' );
		$seen = ss_visible( $settings );
		ss_check( $seen['words'] <= 250 && $seen['longest'][0] <= 140, 'Ajustes: ' . $seen['words'] . ' palabras (máximo 250) o un texto de ' . $seen['longest'][0] . ' caracteres: ' . $seen['longest'][1] );
		ss_check( preg_match( '#id="digitalisimo-backups-section-pre-update".*<details class="digitalisimo-backups-help digitalisimo-backups-pre-update-form" open>#s', ss_render( 'settings', false, array( 'open' => 'pre-update' ) ) ), '?open=pre-update no abre «Cambiar».' );
	} );
	ss_case( 'T3 · guardar la programación vuelve a «Ajustes» y sólo cambia esa automatización', function() use ( $B ) {
		$before = $B::policies();
		$_GET = array();
		list( $ok, $message, $url ) = bk_post( array( $B, 'update_automation' ), array( '_wpnonce' => 'nonce-' . $B::ACTION_UPDATE_AUTOMATION, 'policy_id' => 'monthly-full', 'digitalisimo_backups_return' => 'settings:schedule', 'digitalisimo_backup_automation' => array( 'type' => 'full', 'enabled' => '1', 'schedule' => 'weekly', 'time' => '03:30', 'retain' => '6', 'destination' => 'local' ) ) );
		ss_check( $ok && false !== strpos( $url, 'tab=settings' ) && false !== strpos( $url, 'open=schedule' ) && '#digitalisimo-backups-section-schedule' === substr( $url, -strlen( '#digitalisimo-backups-section-schedule' ) ), 'Guardar no volvió a «Ajustes» → «Programación»: ' . $url );
		$after = $B::policies();
		ss_check( 'weekly' === $after[0]['schedule'] && '03:30' === $after[0]['time'] && 6 === $after[0]['retain'] && ! empty( $after[0]['enabled'] ) && count( $before ) === count( $after ), 'La automatización no quedó semanal a las 03:30 conservando 6: ' . json_encode( $after ) );
		list( , , $plain ) = bk_post( array( $B, 'update_automation' ), array( '_wpnonce' => 'nonce-' . $B::ACTION_UPDATE_AUTOMATION, 'policy_id' => 'monthly-full', 'digitalisimo_backups_return' => 'malicioso:<script>', 'digitalisimo_backup_automation' => array( 'type' => 'full', 'enabled' => '1', 'schedule' => 'monthly', 'retain' => '4', 'destination' => 'local' ) ) );
		ss_check( false === strpos( $plain, 'tab=' ) && false === strpos( $plain, 'script' ), 'Un destino de vuelta desconocido cambió la redirección: ' . $plain );
	} );

	$servers = ss_render( 'servers' );
	$pages['destinos'] = $servers;
	ss_case( 'T4 · «Destinos»: la lista sin campos; un solo formulario al pedirlo', function() use ( $servers, $B ) {
		$seen = ss_visible( $servers );
		ss_check( 0 === $seen['fields'] && 0 === ss_count( $servers, 'digitalisimo-backups-connection-form' ), '«Destinos» enseña formularios o campos sin pedirlos (' . $seen['fields'] . ').' );
		ss_check( false !== strpos( $servers, '<td>Hosting / local</td><td>Este servidor</td>' ) && preg_match( '#<a class="button button-primary" href="[^"]*tab=servers&amp;add=1[^"]*">Añadir destino</a>#', $servers ) && preg_match( '#href="[^"]*tab=servers&amp;edit=sftp[^"]*">Editar</a>#', $servers ), 'La lista no tiene el hosting, «Añadir destino» ni «Editar» de la principal.' );
		ss_check( $seen['words'] <= 80 && $seen['longest'][0] <= 140, '«Destinos»: ' . $seen['words'] . ' palabras o un texto largo: ' . $seen['longest'][1] );
		$add = ss_render( 'servers', false, array( 'add' => '1' ) );
		ss_check( 1 === ss_count( $add, 'class="digitalisimo-backups-connection-form"' ) && false !== strpos( $add, 'value="' . $B::ACTION_SAVE_CONNECTION . '"' ) && false === strpos( $add, 'value="' . $B::ACTION_SAVE . '"' ), '«Añadir destino» no abre un solo formulario (el de otra conexión).' );
		$edit = ss_render( 'servers', false, array( 'edit' => 'sftp' ) );
		ss_check( 1 === ss_count( $edit, 'class="digitalisimo-backups-connection-form"' ) && false !== strpos( $edit, 'name="action" value="' . $B::ACTION_SAVE . '"' ) && false !== strpos( $edit, '<option value="sftp" selected="selected">SFTP</option>' ), '«Editar» de la principal no abre su formulario con SFTP elegido.' );
		ss_check( false === strpos( $edit, 'value="' . $B::ACTION_SAVE_CONNECTION . '"' ), '«Editar» de la principal enseña también el formulario de otra conexión.' );
		ss_check( preg_match( '#<tr data-server="sftp" data-advanced="1"><th><label for="sftp_engine">#', $edit ) && false !== strpos( $edit, '<details class="digitalisimo-backups-advanced"><summary>Opciones avanzadas</summary></details>' ), 'El motor SFTP no va en «Opciones avanzadas».' );
		ss_check( preg_match( '#<details class="digitalisimo-backups-help"><summary>Cómo obtener SFTP</summary><ol>#', $edit ), '«Cómo obtener SFTP» no está plegado.' );
		foreach ( array( 'edit' => $edit, 'add' => $add ) as $name => $form ) { $seen = ss_visible( $form ); ss_check( $seen['longest'][0] <= 140, 'El formulario «' . $name . '» enseña un texto de ' . $seen['longest'][0] . ' caracteres: ' . $seen['longest'][1] ); }
	} );

	$tools = ss_render( 'tools' );
	$pages['herramientas'] = $tools;
	ss_case( 'T5 · «Herramientas»: todo plegado y ?open= abre su sección', function() use ( $tools ) {
		foreach ( array( 'migrate', 'import', 'upload', 'restore-file', 'automations', 'jobs' ) as $id ) ss_check( preg_match( '#<details class="digitalisimo-backups-section" id="digitalisimo-backups-section-' . $id . '"><summary>#', $tools ), 'La sección «' . $id . '» no está plegada en «Herramientas».' );
		ss_check( false === strpos( $tools, 'id="digitalisimo-backups-section-extract"' ), '«Extraer un sitio» aparece fuera de la red.' );
		ss_check( 1 === ss_count( $tools, 'id="digitalisimo-backups-jobs"' ), '«Trabajos» no está una sola vez.' );
		$seen = ss_visible( $tools );
		ss_check( 0 === $seen['fields'] && $seen['words'] <= 120 && $seen['longest'][0] <= 140, '«Herramientas»: ' . $seen['fields'] . ' campos, ' . $seen['words'] . ' palabras o un texto largo: ' . $seen['longest'][1] );
		ss_check( preg_match( '#id="digitalisimo-backups-section-automations" open>#', ss_render( 'tools', false, array( 'open' => 'automations' ) ) ), '?open=automations no abre su sección.' );
		// Abierta, cada sección tampoco enseña textos de más de 140 caracteres: lo largo, en «Más».
		foreach ( array( 'migrate', 'import', 'upload', 'restore-file', 'automations', 'jobs' ) as $id ) {
			$seen = ss_visible( ss_render( 'tools', false, array( 'open' => $id ) ) );
			ss_check( $seen['longest'][0] <= 140, '«' . $id . '» abierta enseña un texto de ' . $seen['longest'][0] . ' caracteres: ' . $seen['longest'][1] );
		}
	} );

	// Revisión de 1.0.74 (H2): cada acción de «Herramientas», de «Respaldos guardados en los destinos» y de la conexión
	// principal vuelve a su pestaña y sección, no a «Respaldos». Con un archivo suelto, un conjunto de UpdraftPlus,
	// respaldos de Backuply (carpeta y almacén), una subida sin terminar, un trabajo en cola y el índice de un destino.
	ss_case( 'T9 · las acciones de Herramientas y de los destinos vuelven a su sección', function() use ( $B, $first ) {
		$store = bk_storage();
		$loose = 'digitalisimo-suelto-2026.zip';
		copy( $store . '/' . $first, $store . '/' . $loose );
		@mkdir( WP_CONTENT_DIR . '/updraft', 0700, true );
		file_put_contents( WP_CONTENT_DIR . '/updraft/backup_2026-11-01-1000_Sitio_abcdef123456-db.gz', 'x' );
		@mkdir( WP_CONTENT_DIR . '/backuply/backups-abc', 0700, true );
		file_put_contents( WP_CONTENT_DIR . '/backuply/backups-abc/backup_sitio_2026.tar.gz', 'x' );
		$backuply_store = Digitalisimo_Backups_Backuply_Import::store_directory( true );
		file_put_contents( $backuply_store . '/backup_almacen_2026.tar.gz', 'x' );
		$upload = $B::service_upload_start( array( 'name' => 'digitalisimo-subida.zip', 'size' => 1024 ) );
		$queued = Digitalisimo_Backups_Jobs::enqueue( Digitalisimo_Backups_Jobs::spec( array( 'policy' => 'manual', 'destination' => 'local', 'retention' => 'none', 'via' => 'test' ) ) );
		$index = get_option( $B::REMOTE_INDEX );
		update_option( $B::REMOTE_INDEX, array( 'sftp' => array( 'destination' => 'sftp', 'name' => 'SFTP', 'synced_at' => time(), 'error' => '', 'items' => array( array( 'name' => 'digitalisimo-otro-sitio-2026.zip', 'destination' => 'sftp', 'size' => 1234, 'date' => '2026-11-01 10:00', 'origin' => 'other' ) ) ) ) );
		try {
			// Cuántos formularios POST tiene cada sección con estos datos (al menos), y que cada uno pide volver a ella.
			$want = array( 'tools:import' => 3, 'tools:upload' => 1, 'tools:restore-file' => 2, 'tools:automations' => 4, 'tools:jobs' => 1, 'servers:remote' => 2, 'tools:extract' => 1 );
			$forms = array();
			foreach ( $want as $target => $min ) {
				list( $tab, $id ) = explode( ':', $target );
				if ( 'extract' === $id ) $GLOBALS['bk_multisite'] = true;
				try { $html = ss_render( $tab, 'extract' === $id, array( 'open' => $id ) ); } finally { $GLOBALS['bk_multisite'] = false; }
				$found = ss_forms( $html, 'digitalisimo-backups-section-' . $id );
				ss_check( count( $found ) >= $min, '«' . $target . '» tiene ' . count( $found ) . ' formularios (se esperaban al menos ' . $min . ').' );
				foreach ( $found as $form ) ss_check( $target === ss_return_of( $form ), 'Un formulario de «' . $target . '» (' . ss_submit( $form )['action'] . ') no vuelve a su sección: «' . ss_return_of( $form ) . '».' );
				$forms[ $target ] = $found;
				// La vuelta que pide cada formulario es válida: redirect() la sigue (pestaña, sección y ancla).
				list( , , $url ) = bk_post( function() use ( $B ) { $B::redirect( true, 'x' ); }, ss_submit( $found[0] ) );
				ss_check( array( $tab, $id, 'digitalisimo-backups-section-' . $id ) === ss_landing( $url ), '«' . $target . '» no vuelve a su sección: ' . $url );
			}
			$pick = function( $target, $action ) use ( &$forms ) {
				foreach ( $forms[ $target ] as $form ) if ( $action === ss_submit( $form )['action'] ) return $form;
				throw new RuntimeException( 'No hay formulario «' . $action . '» en «' . $target . '».' );
			};
			// Con los handlers de siempre: una hora inválida en una automatización (error), borrar el archivo suelto,
			// cancelar el trabajo y descartar la subida.
			$update = $pick( 'tools:automations', $B::ACTION_UPDATE_AUTOMATION );
			list( $ok, $message, $url ) = bk_post( array( $B, 'update_automation' ), ss_submit( $update, array( 'digitalisimo_backup_automation' => array( 'time' => '25:00' ) ) ) );
			ss_check( ! $ok && false !== strpos( $message, 'HH:MM' ) && array( 'tools', 'automations', 'digitalisimo-backups-section-automations' ) === ss_landing( $url ), 'La hora 25:00 no vuelve a Herramientas → Automatizaciones con su error: ' . $url );
			list( $ok, , $url ) = bk_post( array( $B, 'delete_loose' ), ss_submit( $pick( 'tools:restore-file', $B::ACTION_DELETE_LOOSE ) ) );
			ss_check( $ok && ! file_exists( $store . '/' . $loose ) && array( 'tools', 'restore-file', 'digitalisimo-backups-section-restore-file' ) === ss_landing( $url ), 'Borrar un archivo suelto no vuelve a «Restaurar un archivo suelto»: ' . $url );
			list( $ok, , $url ) = bk_post( array( 'Digitalisimo_Backups_Jobs', 'admin_cancel' ), ss_submit( $pick( 'tools:jobs', Digitalisimo_Backups_Jobs::ACTION_CANCEL ) ) );
			ss_check( $ok && array( 'tools', 'jobs', 'digitalisimo-backups-section-jobs' ) === ss_landing( $url ), 'Cancelar un trabajo no vuelve a «Trabajos y registros»: ' . $url );
			list( $ok, , $url ) = bk_post( array( 'Digitalisimo_Backups_Uploads', 'discard' ), ss_submit( $pick( 'tools:upload', Digitalisimo_Backups_Uploads::ACTION_DISCARD ) ) );
			ss_check( $ok && array( 'tools', 'upload', 'digitalisimo-backups-section-upload' ) === ss_landing( $url ), 'Descartar una subida no vuelve a «Subir un respaldo»: ' . $url );
			// La conexión principal: guardar vuelve a «Destinos» (antes, a «Respaldos»), sin perder lo guardado.
			$principal = ss_forms( ss_render( 'servers', false, array( 'edit' => 'sftp' ) ), 'digitalisimo-backups-principal' );
			ss_check( 1 === count( $principal ) && 'servers' === ss_return_of( $principal[0] ), 'El formulario de la conexión principal no vuelve a «Destinos».' );
			$before = bk_call( $B, 'settings' );
			list( $ok, , $url ) = bk_post( array( $B, 'save' ), ss_submit( $principal[0] ) );
			$after = bk_call( $B, 'settings' );
			ss_check( $ok && 'servers' === ss_landing( $url )[0] && $before['sftp_host'] === $after['sftp_host'] && $before['sftp_password'] === $after['sftp_password'], 'Guardar la conexión principal no vuelve a «Destinos» o cambió lo guardado: ' . $url );
			// Una sección desconocida o de otra pestaña se ignora (la pestaña, sí).
			foreach ( array( 'tools:nada' => array( 'tools', '' ), 'servers:migrate' => array( 'servers', '' ), 'settings:schedule' => array( 'settings', 'schedule' ) ) as $raw => $landing ) {
				list( , , $url ) = bk_post( function() use ( $B ) { $B::redirect( true, 'x' ); }, array( 'digitalisimo_backups_return' => $raw ) );
				ss_check( $landing === array_slice( ss_landing( $url ), 0, 2 ), 'La vuelta «' . $raw . '» no lleva a ' . json_encode( $landing ) . ': ' . $url );
			}
		} finally {
			@unlink( $store . '/' . $loose );
			bk_remove_tree( WP_CONTENT_DIR . '/updraft' );
			bk_remove_tree( WP_CONTENT_DIR . '/backuply' );
			bk_remove_tree( $backuply_store );
			try { $B::service_upload_cancel( $upload['id'] ?? '' ); } catch ( Throwable $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
			try { Digitalisimo_Backups_Jobs::cancel( (string) $queued['job']['id'] ); } catch ( Throwable $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
			update_option( $B::REMOTE_INDEX, $index );
		}
	} );

	// Revisión de 1.0.74 (H4, H17 del ensayo E2): abrir la pantalla, en cualquier pestaña, deja en 0600 un ZIP que una
	// versión anterior dejó en 0644 (hasta 1.0.73 lo hacía «Restaurar», que se pintaba siempre).
	ss_case( 'T10 · abrir la pantalla deja el almacén en 0600', function() {
		if ( DIRECTORY_SEPARATOR !== '/' ) return;
		$old = bk_storage() . '/digitalisimo-backup-antiguo-20250101-000000-00000001.zip';
		try {
			foreach ( array( 'backups', 'settings', 'servers' ) as $tab ) {
				file_put_contents( $old, 'respaldo de 1.0.60' );
				chmod( $old, 0644 );
				ss_render( $tab );
				clearstatcache( true, $old );
				ss_check( 0600 === ( fileperms( $old ) & 0777 ), 'Tras abrir «' . $tab . '», el ZIP antiguo sigue en ' . sprintf( '%o', fileperms( $old ) & 0777 ) . '.' );
			}
		} finally { @unlink( $old ); }
	} );

	// Revisión de 1.0.74 (H5, H6, H7): fold_long() no pliega advertencias (<strong>, clase de aviso o «notice»), no vuelve
	// a plegar lo que ya plegó help_html() («Más» dentro de «Más») y, si la expresión regular falla, devuelve el HTML.
	ss_case( 'T11 · «Más» nunca esconde una advertencia, no se anida y no borra el HTML', function() use ( $B ) {
		$long = str_repeat( 'Una frase larga de ayuda que sigue. ', 6 );
		$bad = '<p class="description">' . $long . "\xff</p>";
		ss_check( $bad === $B::fold_long( $bad ), 'Con UTF-8 inválido fold_long() no devuelve el HTML tal cual: ' . json_encode( substr( (string) $B::fold_long( $bad ), 0, 80 ) ) );
		ss_check( 1 === substr_count( $B::fold_long( $B::help_html( $long ) ), '<summary>Más</summary>' ) && 1 === substr_count( $B::fold_long( $B::fold_long( '<p>' . $long . '</p>' ) ), '<summary>Más</summary>' ), 'fold_long() vuelve a plegar lo ya plegado («Más» dentro de «Más»).' );
		foreach ( array( '<p class="description digitalisimo-backups-warning">' . $long . '</p>', '<p class="notice notice-warning inline">' . $long . '</p>', '<p class="description">' . $long . '<strong>sin la frase no se puede restaurar.</strong></p>', '<p role="alert">' . $long . '</p>' ) as $warning ) ss_check( $warning === $B::fold_long( $warning ), 'fold_long() pliega una advertencia: ' . substr( $warning, 0, 60 ) );
		ss_check( 1 === substr_count( $B::fold_long( '<p class="description">' . $long . '</p>' ), '<summary>Más</summary>' ), 'fold_long() ya no pliega un párrafo largo corriente.' );
		// En la pantalla: ninguna sección abierta pliega una advertencia ni anida «Más», y las de antes se leen sin abrir nada.
		$screens = array( 'settings:security' => ss_render( 'settings', false, array( 'open' => 'security' ) ), 'servers:edit' => ss_render( 'servers', false, array( 'edit' => 'sftp' ) ), 'servers:remote' => ss_render( 'servers', false, array( 'open' => 'remote' ) ) );
		foreach ( array( 'migrate', 'import', 'upload', 'restore-file', 'automations', 'jobs' ) as $id ) $screens[ 'tools:' . $id ] = ss_render( 'tools', false, array( 'open' => $id ) );
		foreach ( $screens as $name => $html ) {
			ss_check( ! preg_match( '#<summary>Más</summary>(?:(?!</details>).)*<summary>Más</summary>#s', $html ), '«' . $name . '» tiene un «Más» dentro de otro.' );
			preg_match_all( '#<details class="digitalisimo-backups-help"><summary>Más</summary>(.*?)</details>#s', $html, $folds );
			foreach ( $folds[1] as $body ) ss_check( ! preg_match( '#<strong|warning|class="notice#', $body ), '«' . $name . '» pliega una advertencia en «Más»: ' . substr( strip_tags( $body ), 0, 120 ) );
			ss_check( ss_visible( $html )['longest'][0] <= 140, '«' . $name . '» enseña un texto de más de 140 caracteres: ' . ss_visible( $html )['longest'][1] );
		}
		$seen = ss_visible( $screens['settings:security'] )['text'];
		ss_check( false !== strpos( $seen, 'Sin la frase (o la clave de wp-config.php) no se pueden restaurar: nadie puede recuperarlos, tampoco DIGITALÍSIMO.' ), '«Cifrar los respaldos nuevos» esconde en «Más» que sin la frase no se pueden restaurar.' );
		$seen = ss_visible( $screens['tools:migrate'] )['text'];
		ss_check( false !== strpos( $seen, 'se reemplaza por completo con el del origen y conserva su dirección' ) && false !== strpos( $seen, 'No se fusionan cambios entre dos sitios vivos' ), '«Migrar» esconde en «Más» que el sitio se reemplaza o que no se fusionan cambios.' );
		foreach ( array( 'Sin verificar el certificado, cualquiera en la red puede suplantar al servidor' ) as $text ) ss_check( false !== strpos( ss_visible( $screens['servers:edit'] )['text'], $text ), 'La advertencia «' . $text . '…» no se lee entera.' );
	} );

	// Revisión de 1.0.74 (H9): «Programación» enseña el estado real de una automatización en pausa y guardarla no la
	// reactiva ni pierde su frecuencia; «Desactivado» dice que es sólo esta automatización; sin ninguna, no se ofrece.
	ss_case( 'T12 · «Programación»: en pausa sigue en pausa con su frecuencia; «Desactivado» sólo esta; sin ninguna, no crea', function() use ( $B ) {
		$saved = get_option( $B::POLICIES );
		$schedule = function() { $forms = ss_forms( ss_render( 'settings' ), 'digitalisimo-backups-section-schedule' ); ss_check( 1 === count( $forms ), 'No hay un solo formulario en «Programación».' ); return $forms[0]; };
		$option = function( DOMElement $form, $value ) { foreach ( $form->getElementsByTagName( 'option' ) as $option ) if ( $value === $option->getAttribute( 'value' ) ) return $option; return null; };
		try {
			update_option( $B::POLICIES, array( array( 'id' => 'pausada', 'label' => '', 'type' => 'full', 'schedule' => 'weekly', 'destination' => 'local', 'retain' => 4, 'enabled' => false, 'time' => '02:00', 'remote_only' => false ) ) );
			$form = $schedule();
			$weekly = $option( $form, 'weekly' );
			ss_check( $weekly && $weekly->hasAttribute( 'selected' ), 'Una automatización semanal en pausa no se enseña como «Semanal»: ' . json_encode( ss_submit( $form ) ) );
			$disabled = $option( $form, 'disabled' );
			ss_check( $disabled && false !== strpos( $disabled->textContent, 'sólo esta automatización' ), '«Desactivado» no dice que se aplica sólo a esta automatización.' );
			// Cambiar sólo «Conservar» y guardar, como en el navegador.
			list( $ok, , $url ) = bk_post( array( $B, 'update_automation' ), ss_submit( $form, array( 'digitalisimo_backup_automation' => array( 'retain' => '7' ) ) ) );
			$after = $B::policy( 'pausada' );
			ss_check( $ok && 'settings' === ss_landing( $url )[0] && empty( $after['enabled'] ) && 'weekly' === $after['schedule'] && 7 === (int) $after['retain'] && '02:00' === $after['time'], 'Guardar «Conservar» de una automatización en pausa la cambió: ' . json_encode( $after ) );
			// Activarla desde ahí: la casilla «Activa» sin tocar la frecuencia.
			list( $ok ) = bk_post( array( $B, 'update_automation' ), ss_submit( $schedule(), array( 'digitalisimo_backup_automation' => array( 'enabled' => '1' ) ) ) );
			$after = $B::policy( 'pausada' );
			ss_check( $ok && ! empty( $after['enabled'] ) && 'weekly' === $after['schedule'], 'Marcar «Activa» no reanudó la automatización semanal: ' . json_encode( $after ) );
			// Sin ninguna automatización, «Desactivado» no se ofrece (guardar crearía una) y lo que se guarda la crea programada.
			update_option( $B::POLICIES, array() );
			$form = $schedule();
			ss_check( null === $option( $form, 'disabled' ), 'Sin automatizaciones, «Programación» ofrece «Desactivado», que crearía una.' );
			list( $ok ) = bk_post( array( $B, 'create_automation' ), ss_submit( $form ) );
			$created = $B::policies();
			ss_check( $ok && 1 === count( $created ) && 'weekly' === $created[0]['schedule'] && ! empty( $created[0]['enabled'] ), 'Guardar «Programación» sin automatizaciones no creó una semanal: ' . json_encode( $created ) );
		} finally {
			update_option( $B::POLICIES, $saved );
			bk_call( $B, 'sync_policy_schedules' );
		}
	} );

	// Revisión de 1.0.74 (H13): «Herramientas» busca una sola vez los respaldos de UpdraftPlus y de Backuply (el resumen y
	// la sección usan lo mismo; sources() retira además las descargas cortadas), y «Respaldos (N)» cuenta todos.
	ss_case( 'T13 · una sola búsqueda en «Importar» y el contador con el total', function() use ( $B ) {
		// Los paneles pintan lo que se les da, sin volver a buscar en el disco (aquí no hay nada de UpdraftPlus ni Backuply).
		$set = array( 'id' => 'backup_2026-01-01-0000_Dado_0123456789ab', 'files' => array( 'db' => array( array( 'path' => '/no/existe-db.gz', 'part' => 0, 'encrypted' => false ) ) ), 'complete' => true );
		ob_start(); bk_call( $B, 'updraft_panel', array( $set['id'] => $set ) ); $updraft = (string) ob_get_clean();
		ss_check( false !== strpos( $updraft, '<option value="' . $set['id'] . '">' ), 'updraft_panel() vuelve a buscar en el disco en lugar de usar los conjuntos que ya calculó tools_tab().' );
		$item = array( 'source' => 'store', 'name' => 'backup_dado_2026.tar.gz', 'size' => 10, 'created' => time(), 'date' => '2026-01-01 00:00', 'database' => null, 'files' => null, 'site_url' => '', 'imported' => '' );
		ob_start(); bk_call( $B, 'backuply_panel', false, array( $item ) ); $backuply = (string) ob_get_clean();
		ss_check( false !== strpos( $backuply, 'value="backup_dado_2026.tar.gz"' ), 'backuply_panel() vuelve a llamar a sources() en lugar de usar los respaldos que ya calculó tools_tab().' );
		// «Respaldos (12)» con 12 en el historial, aunque la lista enseñe los 10 últimos.
		$saved = get_option( $B::HISTORY );
		try {
			$rows = (array) $saved;
			for ( $i = count( $rows ); $i < 12; $i++ ) $rows[] = array_merge( (array) $rows[0], array( 'file' => sprintf( 'digitalisimo-backup-antiguo-%02d.zip', $i ), 'locked' => false ) );
			update_option( $B::HISTORY, $rows );
			$home = ss_render( 'backups' );
			ss_check( 10 === ss_count( $home, '<tr class="digitalisimo-backups-row"' ) && false !== strpos( $home, 'Respaldos <span class="count">(12)</span>' ), 'Con 12 respaldos la lista no enseña 10 con el contador en 12: ' . ( preg_match( '#<h2>Respaldos[^<]*<span class="count">\([0-9]+\)#', $home, $m ) ? $m[0] : '' ) );
			$seen = ss_visible( $home );
			ss_check( $seen['longest'][0] <= 140, '«Respaldos» con 12 enseña un texto de ' . $seen['longest'][0] . ' caracteres: ' . $seen['longest'][1] );
		} finally { update_option( $B::HISTORY, $saved ); }
	} );

	ss_case( 'T6 · una sola franja de avisos y sin repetir el aviso del escritorio', function() use ( $B ) {
		Digitalisimo_Backups_Keys::put( bk_call( $B, 'restore_notice_key' ), array( 'no_active' => true ) );
		$html = ss_render( 'backups' );
		ss_check( 1 === ss_count( $html, 'digitalisimo-backups-strip"' ) && preg_match( '#<div class="notice notice-warning digitalisimo-backups-strip" role="status"><ul><li class="digitalisimo-backups-restore-notice is-warning"><span class="digitalisimo-backups-strip-text">Ninguna automatización activa: el sitio no se respalda solo.</span>.*value="discard".*</li></ul></div>#s', $html ), 'El aviso tras restaurar no va en la franja con «Descartar»: ' . substr( strip_tags( $html ), 0, 400 ) );
		ss_check( 1 === preg_match_all( '#class="notice #', $html ), 'Hay avisos fuera de la franja.' );
		$_GET = array( 'page' => 'digitalisimo-backups' );
		ob_start(); $B::restore_config_admin_notice(); $mine = (string) ob_get_clean();
		$_GET = array( 'page' => 'otra' );
		ob_start(); $B::restore_config_admin_notice(); $other = (string) ob_get_clean();
		$_GET = array();
		ss_check( '' === $mine && false !== strpos( $other, 'digitalisimo-backups-restore-notice' ), 'El aviso del escritorio se repite en la pantalla de Backups o desaparece de las demás.' );
		Digitalisimo_Backups_Keys::put( bk_call( $B, 'restore_notice_key' ), array() );
		bk_call( $B, 'restore_notice_save', array() );
	} );

	// H26 (ensayo E5): con la configuración de un respaldo pendiente, la franja dice a la vista lo que se pierde al aplicarla
	// y «Aplicar la del respaldo» pide escribir APLICAR, comprobado por el handler (nada de confirm() de JavaScript).
	ss_case( 'T14 · aviso pendiente: lo que se pierde, a la vista, y APLICAR para aplicar la del respaldo', function() use ( $B, &$pages ) {
		$D = 'Digitalisimo_Backups_Destinations';
		$saved = array( get_option( $D::CONNECTIONS, null ), get_option( $B::POLICIES, null ) );
		try {
			update_option( $D::CONNECTIONS, array( 'sftp-abcdef12' => array( 'type' => 'sftp', 'name' => 'SFTP de este sitio', 'settings' => array( 'sftp_host' => 'sftp.aqui.test', 'sftp_user' => 'ana' ) ) ), false );
			update_option( $B::POLICIES, array_merge( $B::default_policies(), array( array( 'id' => 'automation-aqui', 'label' => '', 'type' => 'full', 'schedule' => 'weekly', 'time' => '04:45', 'retain' => 3, 'destination' => 'local', 'enabled' => true ) ) ), false );
			$origin = array( 'format' => 1, 'site' => array( $B::POLICIES => array( 'value' => array_merge( $B::default_policies(), array( array( 'id' => 'automation-origen', 'label' => '', 'type' => 'full', 'schedule' => 'daily', 'retain' => 2, 'destination' => 'local', 'enabled' => true ) ) ) ), $D::CONNECTIONS => array( 'absent' => true ) ), 'network' => null );
			Digitalisimo_Backups_Keys::put( bk_call( $B, 'restore_notice_key' ), array( 'at' => time(), 'pending' => bk_call( $B, 'restore_pending_seal', $origin ), 'automations' => 2, 'connections' => 0, 'no_active' => false, 'paused' => 0 ) );
			$html = ss_render( 'backups' );
			$pages['respaldos-aviso'] = $html;
			$loses = 'Aplicarla reemplaza la de este sitio: se pierden 1 conexión («SFTP de este sitio») y 1 automatización («Semanal 04:45»).';
			$seen = ss_visible( $html );
			ss_check( false !== strpos( $seen['text'], $loses ), 'La franja no dice a la vista lo que se pierde al aplicar la del respaldo: ' . substr( $seen['text'], 0, 600 ) );
			ss_check( $seen['longest'][0] <= 140 && 1 === $seen['fields'], 'Con el aviso pendiente se ven ' . $seen['fields'] . ' campos (sólo la confirmación) o un texto de ' . $seen['longest'][0] . ' caracteres: ' . $seen['longest'][1] );
			ss_check( 1 === ss_count( $html, 'digitalisimo-backups-strip"' ) && preg_match( '#<div class="notice notice-warning digitalisimo-backups-strip".*?</ul></div>#s', $html, $strip ) && false === strpos( $strip[0], 'confirm(' ), 'Más de una franja, o «Aplicar» con confirm() de JavaScript.' );
			$apply = null;
			foreach ( ss_forms( $html ) as $form ) if ( 'apply' === ( ss_submit( $form )['do'] ?? '' ) ) $apply = $form;
			ss_check( $apply && 1 === preg_match( '#<input type="text" name="digitalisimo_backups_config_confirmation"[^>]*required#', $apply->ownerDocument->saveHTML( $apply ) ), '«Aplicar la del respaldo» no tiene el campo para escribir APLICAR.' );
			// Enviado tal cual (sin la palabra), el handler no cambia nada; con APLICAR, aplica (la misma acción y el mismo nonce).
			list( $ok, $message ) = bk_post( array( $B, 'restore_config_request' ), ss_submit( $apply ) );
			ss_check( ! $ok && false !== strpos( $message, 'Escribe APLICAR' ) && array( 'sftp-abcdef12' ) === array_keys( (array) get_option( $D::CONNECTIONS, array() ) ), '«Aplicar la del respaldo» se aplicó sin escribir APLICAR: ' . $message );
			list( $ok, $message ) = bk_post( array( $B, 'restore_config_request' ), ss_submit( $apply, array( 'digitalisimo_backups_config_confirmation' => 'APLICAR' ) ) );
			ss_check( $ok && false !== strpos( $message, 'con 2 automatizaciones en pausa' ) && ! get_option( $D::CONNECTIONS, array() ), 'Con APLICAR no se aplicó la configuración del respaldo: ' . $message );
		} finally {
			bk_call( $B, 'restore_notice_save', array() );
			foreach ( array( $D::CONNECTIONS => $saved[0], $B::POLICIES => $saved[1] ) as $name => $value ) { if ( null === $value ) delete_option( $name ); else update_option( $name, $value, false ); }
		}
	} );

	ss_case( 'T8 · sin secretos ni rutas del almacén; textos que nombran la pantalla de antes', function() use ( $secret, $storage, $pages ) {
		foreach ( $pages as $name => $html ) {
			ss_check( false === strpos( $html, $secret ), 'El secreto del destino aparece en «' . $name . '».' );
			ss_check( false === strpos( $html, $storage ), 'La ruta del almacén aparece en «' . $name . '».' );
		}
		$root = dirname( __DIR__ ) . '/digitalisimo-backups';
		$old = array();
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
			if ( ! preg_match( '/\.(php|js)$/', $file->getFilename() ) ) continue;
			foreach ( file( $file->getPathname() ) as $n => $line ) {
				if ( preg_match( '#^\s*(\*|//|/\*)#', $line ) ) continue;
				// También una ruta nueva mal nombrada (revisión de 1.0.74, H10): «Tablas apartadas» está en Herramientas → Migrar.
				if ( preg_match( '/Backups → (Migrar|Seguridad|Servidores|Trabajos|Automatizaciones|Antes de actualizar)\b|«(Servidores|Restaurar respaldo local|Respaldos de Backuply|Conexiones externas|Añadir otra conexión)»|Herramientas → «Tablas apartadas»/u', $line ) ) $old[] = substr( $file->getPathname(), strlen( $root ) + 1 ) . ':' . ( $n + 1 );
			}
		}
		ss_check( ! $old, 'Quedan textos que nombran la pantalla de antes: ' . implode( ', ', $old ) );
	} );

	// La red: la administración de red, el sitio principal y un subsitio.
	$GLOBALS['bk_multisite'] = true;
	ss_case( 'T5/T7 · red: mismas pestañas, «Extraer un sitio» y el subsitio sólo enlaza a la red', function() use ( &$pages ) {
		$network = ss_render( 'backups', true );
		$pages['red-respaldos'] = $network;
		ss_check( preg_match( '#<form id="digitalisimo-backups-manual-form"[^>]*>.*<input type="hidden" name="digitalisimo_backups_network" value="1">#s', $network ) && false !== strpos( $network, '>Herramientas</a>' ), 'La pantalla de red no conserva su contexto o sus pestañas.' );
		$tools = ss_render( 'tools', true );
		ss_check( preg_match( '#<details class="digitalisimo-backups-section" id="digitalisimo-backups-section-extract"><summary><strong>Extraer un sitio de la red</strong>#', $tools ), 'Falta «Extraer un sitio de la red» en la red.' );
		$GLOBALS['bk_main_site'] = false;
		try {
			$sub = ss_render( 'backups' );
			$pages['subsitio'] = $sub;
		} finally { $GLOBALS['bk_main_site'] = true; }
		$seen = ss_visible( $sub );
		ss_check( false !== strpos( $sub, 'href="https://example.test/wp-admin/network/admin.php?page=digitalisimo-network-backups">Ir a Backups de la red</a>' ) && 0 === $seen['forms'] && 0 === $seen['fields'] && false === strpos( $sub, 'digitalisimo-backups-tabs' ), 'El subsitio no es una línea con el enlace a la red.' );
		ss_check( $seen['words'] <= 40, 'El subsitio tiene ' . $seen['words'] . ' palabras.' );
		ss_check( false === strpos( $sub, 'sin registro' ), 'El subsitio lista los ZIP de la red como «sin registro».' );
	} );
	$GLOBALS['bk_multisite'] = false;

	if ( $fixture ) {
		$plugin = dirname( __DIR__ ) . '/digitalisimo-backups';
		$head = '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Backups</title>'
			. '<!-- Generado por tests/backups-simple-screen.php --fixture: lo usa backups-simple-screen-chrome.mjs. -->'
			. '<link rel="stylesheet" href="file://' . $plugin . '/digitalisimo-ui/digitalisimo-ui.css"><link rel="stylesheet" href="file://' . $plugin . '/assets/backups-admin.css">'
			. '<style>body{margin:0;font:13px/1.4 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f6f8ff}#wpcontent{padding:32px 0 0}.screen-reader-text{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)}.button{display:inline-block;padding:0 10px;line-height:2.15;text-decoration:none;cursor:pointer}.widefat{border-collapse:collapse}.widefat td,.widefat th{padding:8px 10px;text-align:left}</style></head>'
			. '<body class="wp-admin digitalisimo-admin"><div id="wpcontent">';
		$tail = '</div><script src="file://' . $plugin . '/assets/screen.js"></script></body></html>';
		if ( ! is_dir( $fixture ) ) mkdir( $fixture, 0700, true );
		foreach ( $pages as $name => $html ) file_put_contents( $fixture . '/' . $name . '.html', $head . $html . $tail );
		echo 'Pantallas escritas en ' . $fixture . ': ' . implode( ', ', array_keys( $pages ) ) . "\n";
	}
} finally {
	bk_cleanup();
}
if ( $failures ) { echo "FALLA: {$failures} casos de la pantalla «interfaz simple»\n"; exit( 1 ); }
echo "DIGITALÍSIMO Backups: pantalla simple (Respaldos, Ajustes, Destinos y Herramientas; franja de avisos; restaurar bajo la fila; red y subsitio) OK\n";
