<?php
/**
 * Sonda de tests/playground/tools-php.sh (mu-plugin, sólo en Playground).
 *
 * - Banderas por sitio en la opción `digi_php_probe_flags` (?digi_php_probe=flag&name=…&on=1|0): `safe-mode`
 *   (DIGITALISIMO_TOOLS_PHP_SAFE_MODE), `disallow-edit` (DISALLOW_FILE_EDIT), `savequeries` (SAVEQUERIES; con
 *   ?digi_php_queries=<nombre> se guardan las consultas que nombran los fragmentos) y `redirect` (el plugin falso). Van en
 *   una opción y no en archivos: Playground conserva entre peticiones la caché de rutas de PHP y un archivo borrado seguiría
 *   «existiendo».
 * - Captura de correos con pre_wp_mail, como brand-audit/probe-http.php: nunca sale un correo.
 * - ?digi_php_probe=state|tamper|tamper-draft|import|cron|mails|clear-mails lee o prepara el estado sin pasar por la
 *   interfaz (`tamper` cambia el código de `_run`; `tamper-draft`, sólo el del borrador de la ficha).
 */
defined( 'ABSPATH' ) || exit;
$digi_probe = WP_CONTENT_DIR . '/probe/';
$digi_flags = (array) get_option( 'digi_php_probe_flags', array() );
if ( ! empty( $digi_flags['safe-mode'] ) ) define( 'DIGITALISIMO_TOOLS_PHP_SAFE_MODE', true );
if ( ! empty( $digi_flags['disallow-edit'] ) ) define( 'DISALLOW_FILE_EDIT', true );
if ( ! empty( $digi_flags['savequeries'] ) && ! defined( 'SAVEQUERIES' ) ) define( 'SAVEQUERIES', true );
// El cron lo lanza la prueba (?digi_php_probe=cron): así el correo de la red sale cuando se espera.
if ( ! defined( 'DISABLE_WP_CRON' ) ) define( 'DISABLE_WP_CRON', true );

add_filter( 'pre_wp_mail', static function ( $null, $atts ) use ( $digi_probe ) {
	$file  = $digi_probe . 'mails.json';
	$all   = file_exists( $file ) ? (array) json_decode( (string) file_get_contents( $file ), true ) : array();
	$all[] = array( 'to' => $atts['to'], 'subject' => $atts['subject'], 'body' => $atts['message'], 'headers' => $atts['headers'], 'blog' => get_current_blog_id() );
	file_put_contents( $file, wp_json_encode( $all ) );
	return true;
}, 10, 2 );

if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES && isset( $_GET['digi_php_queries'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
	add_action( 'shutdown', static function () use ( $digi_probe ) {
		global $wpdb;
		$all = array_column( (array) $wpdb->queries, 0 );
		$php = array_values( array_filter( $all, static function ( $q ) { return false !== strpos( $q, 'digitalisimo_tools_php' ); } ) );
		$tools = array_values( array_filter( $all, static function ( $q ) { return false !== strpos( $q, 'digitalisimo_tools_' ); } ) );
		file_put_contents( $digi_probe . 'queries-' . preg_replace( '/[^a-z0-9_-]/', '', (string) $_GET['digi_php_queries'] ) . '.json', wp_json_encode( array( 'total' => count( $all ), 'php' => $php, 'tools' => $tools ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
	}, PHP_INT_MAX );
}

add_action( 'init', static function () use ( $digi_probe ) {
	$cmd = isset( $_GET['digi_php_probe'] ) ? (string) $_GET['digi_php_probe'] : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( '' === $cmd || ! class_exists( 'Digitalisimo\\Tools\\Php_Snippets\\Store' ) ) return;
	$S   = 'Digitalisimo\\Tools\\Php_Snippets\\Store';
	$out = array( 'blog' => get_current_blog_id() );
	global $wpdb;
	switch ( $cmd ) {
		case 'state':
			$rows = static function ( $scope ) use ( $S ) {
				return array_map( static function ( $r ) { return array( 'id' => (string) $r['id'], 'title' => $r['title'], 'state' => $r['state'], 'line' => $r['error']['line'] ?? null, 'path' => $r['error']['path'] ?? null, 'message' => $r['error']['message'] ?? null ); }, $S::overview( $scope ) );
			};
			$out['site']    = $rows( 'site' );
			$out['network'] = is_multisite() ? $rows( 'network' ) : array();
			$out['log']     = array_column( $S::log_entries( 'site', 15 ), 'event' );
			$out['cron']    = (bool) wp_next_scheduled( $S::CRON );
			$out['off']     = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'digitalisimo\\_tools\\_php\\_off\\_%' OR option_name LIKE 'digitalisimo\\_tools\\_php\\_badsig\\_%'" ); // phpcs:ignore WordPress.DB
			$out['flag']    = get_option( $S::FLAG );
			break;
		case 'flag':
			$flags = (array) get_option( 'digi_php_probe_flags', array() );
			$flags[ sanitize_key( (string) ( $_GET['name'] ?? '' ) ) ] = ! empty( $_GET['on'] ); // phpcs:ignore WordPress.Security.NonceVerification
			update_option( 'digi_php_probe_flags', array_filter( $flags ), true );
			$out['flags'] = array_keys( array_filter( $flags ) );
			break;
		case 'tamper':
			// Una escritura directa en la base de datos: cambia el código y su hash, pero no la firma.
			$id  = (string) ( $_GET['id'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
			$run = get_option( $S::SITE_RUN );
			if ( isset( $run['items'][ $id ] ) ) {
				$run['items'][ $id ]['code'] = "<?php\nadd_action( 'send_headers', static function () { header( 'X-Tampered: 1' ); } );\n";
				$run['items'][ $id ]['hash'] = hash( 'sha256', $run['items'][ $id ]['code'] );
				update_option( $S::SITE_RUN, $run, false );
				$out['tampered'] = true;
			}
			break;
		case 'tamper-draft':
			// Una escritura directa en la base de datos: cambia el código del borrador, pero no su firma.
			$name = $S::SITE_ITEM . (string) ( $_GET['id'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
			$item = get_option( $name );
			if ( is_array( $item ) ) {
				$item['code'] = "<?php\nadd_action( 'send_headers', static function () { header( 'X-Draft-Tampered: 1' ); } );\n";
				update_option( $name, $item, false );
				$out['tampered'] = true;
				$out['code']     = $item['code'];
			}
			break;
		case 'import':
			$out['result'] = apply_filters( 'digitalisimo_tools_php_import', null, array( array( 'title' => 'Hook importado', 'status' => 'active', 'code' => "<?php\nadd_action( 'send_headers', static function () { header( 'X-Imported: 1' ); } );\n", 'original' => '<?php header( "X-Imported: 1" ); ?>', 'origin' => array( 'source' => 'generatepress', 'version' => '2.5.6', 'id' => '77', 'hook' => 'generate_before_header', 'hash' => 'gp-77-v1', 'author_id' => 1, 'last_editor_id' => 1, 'executed_in_origin' => true ) ) ), array( 'by' => 'tools-php.sh' ) );
			break;
		case 'cron':
			$S::cron();
			$out['done'] = true;
			break;
		case 'mails':
			$file          = $digi_probe . 'mails.json';
			$out['mails']  = file_exists( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : array();
			break;
		case 'clear-mails':
			@unlink( $digi_probe . 'mails.json' ); // phpcs:ignore
			$out['done'] = true;
			break;
	}
	nocache_headers();
	header( 'Content-Type: application/json' );
	echo wp_json_encode( $out );
	exit;
}, 0 );
