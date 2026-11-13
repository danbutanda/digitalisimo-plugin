<?php
/**
 * Preparación del ensayo «restaurar por partes» (paso runPHP después de backups-restore/setup.php, que activa Backups,
 * escribe el mu-plugin del laboratorio y crea entradas, el plugin «demo», un archivo del tema y medios). Añade un plugin
 * «otro», un archivo propio en el sitio (solo-<sitio>.txt en los medios), los medios del subsitio en una red y el
 * mu-plugin de este ensayo: `?digi_parts=mutate&round=N&token=…` cambia lo que el laboratorio no cambia (el plugin
 * «otro», un medio subido después, un tema instalado después y, en la red, los medios del subsitio) y
 * `?digi_parts=info&token=…` da las direcciones y el nombre del sitio. Con /digi-probe/site.txt = «b», el sitio de destino
 * del ensayo de otra dirección (otro nombre). Un fallo queda en parts-error.txt.
 */
require '/wordpress/wp-load.php';
$dir = '/digi-probe';
try {
	$site = trim( (string) @file_get_contents( $dir . '/site.txt' ) ) ?: 'a';
	update_option( 'blogname', 'Sitio ' . strtoupper( $site ) . ' del ensayo por partes' );
	foreach ( array( 'otro.php' => "<?php\n/* Plugin Name: Otro del ensayo */\n", 'inc/c.php' => "<?php // c original\n" ) as $name => $content ) { wp_mkdir_p( dirname( WP_PLUGIN_DIR . '/otro/' . $name ) ); file_put_contents( WP_PLUGIN_DIR . '/otro/' . $name, $content ); }
	$uploads = wp_upload_dir( null, false )['basedir'];
	file_put_contents( $uploads . '/solo-' . $site . '.txt', 'sólo existe en el sitio ' . $site . "\n" );
	// Sin los ocho archivos de 1 MiB del laboratorio: aquí cuenta qué se toca, y con 64 MB el empaquetado de esos archivos
	// va al límite de la memoria también con el código de main (ver «Restaurar por partes» → «Memoria» en docs/BACKUPS.md).
	foreach ( (array) glob( $uploads . '/2026/11/grande-*.bin' ) as $big ) @unlink( $big );
	$sub = 0;
	if ( is_multisite() ) {
		foreach ( get_sites( array( 'number' => 10 ) ) as $blog ) if ( (int) $blog->blog_id !== (int) get_main_site_id() ) $sub = (int) $blog->blog_id;
		switch_to_blog( $sub );
		$base = wp_upload_dir( null, false )['basedir'];
		restore_current_blog();
		wp_mkdir_p( $base . '/2026/05' );
		for ( $i = 0; $i < 10; $i++ ) file_put_contents( $base . '/2026/05/sub-' . $i . '.jpg', random_bytes( 1024 ) );
	}
	$mu = <<<'PHP'
<?php
// Ensayo «restaurar por partes» de DIGITALÍSIMO Backups: cambios que el laboratorio de la restauración no hace.
// Crear el respaldo de referencia no es lo que se ensaya aquí: con la marca /digi-ctl/backup-memory (la pone el ensayo sólo
// mientras se crea), esas peticiones corren con el límite que dice la marca; las de la restauración, siempre con 64 MB.
add_action( 'muplugins_loaded', static function () {
	$limit = is_file( '/digi-ctl/backup-memory' ) ? trim( (string) @file_get_contents( '/digi-ctl/backup-memory' ) ) : '';
	if ( ! preg_match( '/^[0-9]+M$/D', $limit ) ) return;
	@ini_set( 'memory_limit', $limit );
	foreach ( array( 'admin_memory_limit', 'cron_memory_limit' ) as $filter ) add_filter( $filter, static function () use ( $limit ) { return $limit; }, 99 );
} );
// Memoria exacta de cada petición de la cola (el laboratorio anota memory_get_peak_usage( true ), en bloques de 2 MB), también
// justo después de cargar los plugins (lo que ocupa el código compilado antes de trabajar).
add_action( 'plugins_loaded', static function () { $GLOBALS['digi_parts_loaded'] = array( memory_get_usage(), memory_get_usage( true ), memory_get_peak_usage(), memory_get_peak_usage( true ) ); }, PHP_INT_MAX );
register_shutdown_function( static function () {
	$action = (string) ( $_REQUEST['action'] ?? '' );
	if ( ! in_array( $action, array( 'digitalisimo_backups_job_tick', 'digitalisimo_backups_manual_start', 'digitalisimo_backups_restore_tick' ), true ) && false === strpos( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), 'wp-cron.php' ) ) return;
	file_put_contents( '/digi-ctl/memory.jsonl', json_encode( array( 'action' => '' !== $action ? $action : 'wp-cron', 'peak' => memory_get_peak_usage(), 'peak_real' => memory_get_peak_usage( true ), 'limit' => ini_get( 'memory_limit' ), 'loaded' => $GLOBALS['digi_parts_loaded'] ?? null, 'included' => count( get_included_files() ), 'error' => (string) ( error_get_last()['message'] ?? '' ) ) ) . "\n", FILE_APPEND );
} );
add_action( 'init', static function () {
	if ( empty( $_GET['digi_parts'] ) || ! hash_equals( trim( (string) @file_get_contents( '/digi-probe/token.txt' ) ), (string) ( $_GET['token'] ?? '' ) ) ) return;
	header( 'Content-Type: application/json' );
	if ( 'info' === $_GET['digi_parts'] ) {
		echo wp_json_encode( array( 'home' => get_option( 'home' ), 'siteurl' => get_option( 'siteurl' ), 'blogname' => get_option( 'blogname' ), 'active' => get_option( 'active_plugins' ) ) );
		exit;
	}
	$round = (int) ( $_GET['round'] ?? 1 );
	file_put_contents( WP_PLUGIN_DIR . '/otro/inc/c.php', "<?php // c cambiado " . $round . "\n" );
	$uploads = wp_upload_dir( null, false )['basedir'];
	wp_mkdir_p( $uploads . '/2026/12' );
	file_put_contents( $uploads . '/2026/12/subido-despues-' . $round . '.jpg', random_bytes( 1500 ) );
	wp_mkdir_p( get_theme_root() . '/tema-nuevo-' . $round );
	file_put_contents( get_theme_root() . '/tema-nuevo-' . $round . '/style.css', "/* Theme Name: Tema nuevo " . $round . " */\n" );
	$sub = 0;
	if ( is_multisite() ) {
		foreach ( get_sites( array( 'number' => 10 ) ) as $blog ) if ( (int) $blog->blog_id !== (int) get_main_site_id() ) $sub = (int) $blog->blog_id;
		switch_to_blog( $sub );
		$base = wp_upload_dir( null, false )['basedir'];
		restore_current_blog();
		@unlink( $base . '/2026/05/sub-1.jpg' );
		file_put_contents( $base . '/2026/05/sub-2.jpg', 'cambiado ' . $round );
		file_put_contents( $base . '/2026/05/sub-inyectado-' . $round . '.php', "<?php // inyectado\n" );
	}
	echo wp_json_encode( array( 'round' => $round, 'sub' => $sub ) );
	exit;
}, 1 );
PHP;
	if ( false === file_put_contents( WPMU_PLUGIN_DIR . '/digi-parts-lab.php', $mu ) ) throw new RuntimeException( 'No se escribió el mu-plugin del ensayo por partes.' );
	file_put_contents( $dir . '/parts.json', wp_json_encode( array( 'site' => $site, 'sub' => $sub, 'home' => home_url( '/' ) ) ) );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/parts-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
