<?php
/**
 * B8: exclusiones por defecto del respaldo completo.
 *
 * Un sitio que viene de Backuply (o de UpdraftPlus, All-in-One WP Migration, Backup Migration o Duplicator) tiene sus
 * respaldos en wp-content: el primer respaldo de Backups los arrastraba todos, con la caché de páginas y los registros.
 * Ahora no entran (y el manifiesto lo declara en `excluded`), el filtro `digitalisimo_backups_default_exclusions` permite
 * incluirlos, y la restauración nunca los retira, aunque el respaldo no los traiga; lo demás de las raíces gestionadas que
 * el respaldo no tiene se sigue retirando.
 */
$root = sys_get_temp_dir() . '/digitalisimo-exclusions-' . bin2hex( random_bytes( 4 ) );
mkdir( $root . '/public', 0700, true );
$root = realpath( $root );
define( 'ABSPATH', $root . '/public/' );
define( 'WP_CONTENT_DIR', $root . '/public/wp-content' );
define( 'WP_PLUGIN_DIR', $root . '/public/wp-content/plugins' );
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', $root . '/private' );
require __DIR__ . '/backups-coverage/env.php';
digi_make_wordpress( $root . '/public' );
$GLOBALS['filters'] = array();
function apply_filters( $hook, $value, ...$args ) { return isset( $GLOBALS['filters'][ $hook ] ) ? call_user_func( $GLOBALS['filters'][ $hook ], $value, ...$args ) : $value; }

$B = 'Digitalisimo_Backups';
$wp = $root . '/public/';
$store = $root . '/private';
$put = function( $relative, $content = 'x' ) use ( $wp ) {
	@mkdir( dirname( $wp . $relative ), 0700, true );
	file_put_contents( $wp . $relative, $content );
};
// Lo que no debe entrar: respaldos de otros plugins, la caché de páginas y los registros.
$excluded = array(
	'wp-content/backuply/backups-a1b2c3/backup_2026-11-01.tar.gz' => random_bytes( 2048 ),
	'wp-content/backuply/backups-a1b2c3/backup_2026-11-01.php' => '<?php // info de Backuply',
	'wp-content/updraft/backup_2026-11-01-db.gz' => 'updraft',
	'wp-content/ai1wm-backups/sitio.wpress' => 'ai1wm',
	'wp-content/backup-migration/backups/BM_Backup.zip' => 'bm',
	'wp-content/backups-dup-lite/paquete.zip' => 'dup',
	'wp-content/cache/page_enhanced/example.test/index.html' => '<html>caché</html>',
	'wp-content/debug.log' => 'PHP Notice',
	'wp-content/uploads/wc-logs/fatal-errors-2026-11-01.log' => 'registro de WooCommerce',
	'wp-content/uploads/registro/ERRORES.LOG' => 'mayúsculas',
	'error_log' => 'registro del servidor',
	// Backup Migration con su carpeta aleatoria, WPvivid, BackWPup y BackupBuddy (estos dos, en los medios).
	'wp-content/backup-migration-Xy12ab/backups/BM_Backup_2026.zip' => 'bm aleatorio',
	'wp-content/wpvividbackups/sitio_wpvivid_backup.zip' => 'wpvivid',
	'wp-content/uploads/backwpup-abc123-backups/backup_2026.zip' => 'backwpup',
	'wp-content/uploads/backupbuddy_backups/backup-sitio.zip' => 'backupbuddy',
);
// Lo que sí entra, aunque se parezca: otros nombres y extensiones.
$included = array(
	'wp-content/uploads/2026/11/foto.jpg' => random_bytes( 1024 ),
	'wp-content/uploads/2026/11/catalogo.log.pdf' => 'pdf',
	'wp-content/plugins/ejemplo/ejemplo.php' => '<?php // plugin',
	'wp-content/plugins/ejemplo/cache/datos.php' => '<?php // caché propia del plugin (no es wp-content/cache)',
	'wp-content/themes/ejemplo/backuply.css' => '/* un archivo, no la carpeta */',
	'wp-content/mis-cache/notas.txt' => 'otra carpeta',
	// Un `.log` que es parte del código (C12) y archivos con el nombre de una carpeta excluida (una regla de carpeta sólo
	// coincide con carpetas, C8).
	'wp-content/plugins/ejemplo/datos/palabras.log' => 'diccionario que el plugin necesita',
	'wp-admin/error_log' => 'registro dentro del núcleo: entra con su carpeta',
	'wp-content/cache.php' => '<?php // no es la carpeta cache',
	'wp-content/object-cache.php' => '<?php // drop-in',
	'wp-content/backup-migration-notas.txt' => 'un archivo, no la carpeta',
);
foreach ( $excluded + $included as $relative => $content ) $put( $relative, $content );
$entries = function( $file ) use ( $store ) {
	$zip = new ZipArchive();
	digi_assert( true === $zip->open( $store . '/' . $file ), 'No se pudo abrir el ZIP del respaldo.' );
	$names = array();
	for ( $i = 0; $i < $zip->numFiles; $i++ ) $names[] = $zip->getNameIndex( $i );
	$manifest = json_decode( (string) $zip->getFromName( 'manifest.json' ), true );
	$zip->close();
	return array( $names, $manifest );
};

try {
	// 1. El respaldo por defecto no lleva nada de lo excluido y lo declara en el manifiesto.
	$backup = digi_backup();
	digi_assert( ! is_wp_error( $backup ), 'No se creó el respaldo: ' . ( is_wp_error( $backup ) ? $backup->get_error_message() : '' ) );
	list( $names, $manifest ) = $entries( $backup['file'] );
	foreach ( array_keys( $excluded ) as $relative ) digi_assert( ! in_array( 'wordpress/' . $relative, $names, true ), 'El respaldo arrastró ' . $relative . '.' );
	foreach ( array_keys( $included ) as $relative ) digi_assert( in_array( 'wordpress/' . $relative, $names, true ), 'El respaldo dejó fuera ' . $relative . ', que no está en las exclusiones.' );
	digi_assert( ! preg_grep( '#^wordpress/wp-content/(backuply|updraft|ai1wm-backups|backup-migration|backups-dup-lite|cache)/#', $names ), 'El respaldo lleva carpetas excluidas: ' . json_encode( array_values( preg_grep( '#^wordpress/wp-content/(backuply|updraft|cache)#', $names ) ) ) );
	$declared = (array) ( $manifest['excluded'] ?? array() );
	foreach ( array( 'wp-content/backuply/', 'wp-content/updraft/', 'wp-content/ai1wm-backups/', 'wp-content/backup-migration/', 'wp-content/cache/', '*.log', 'error_log' ) as $rule ) digi_assert( in_array( $rule, $declared, true ), 'El manifiesto no declara la exclusión ' . $rule . ': ' . json_encode( $declared ) );
	digi_assert( (int) $manifest['file_count'] === count( preg_grep( '#^wordpress/#', $names ) ), 'El recuento de archivos del manifiesto no coincide con el ZIP.' );
	// Verificar el respaldo (el validador estricto acepta el campo nuevo).
	$verified = $B::service_verify( $backup['file'] );
	digi_assert( ! is_wp_error( $verified ) && true === $verified['verified'], 'El respaldo con exclusiones no se verifica: ' . json_encode( is_wp_error( $verified ) ? $verified->get_error_message() : $verified ) );

	// 2. Restaurar nunca retira lo excluido (aunque el respaldo no lo traiga); lo ajeno de las raíces gestionadas, sí.
	$put( 'wp-content/uploads/wc-logs/nuevo-2026-11-02.log', 'registro posterior' );
	$put( 'wp-content/backuply/backups-a1b2c3/backup_2026-11-02.tar.gz', 'respaldo posterior de Backuply' );
	$put( 'wp-content/uploads/2026/11/ajeno.jpg', 'medio posterior al respaldo' );
	$put( 'wp-content/plugins/ejemplo/registro/otro.log', 'registro posterior en un plugin' );
	$restored = $B::service_restore( $backup['file'], array( 'confirm' => 'RESTAURAR' ) );
	digi_assert( ! is_wp_error( $restored ) && ! empty( $restored['restored'] ), 'No se restauró el respaldo: ' . ( is_wp_error( $restored ) ? $restored->get_error_message() : json_encode( $restored ) ) );
	foreach ( array_merge( array_keys( $excluded ), array( 'wp-content/uploads/wc-logs/nuevo-2026-11-02.log', 'wp-content/backuply/backups-a1b2c3/backup_2026-11-02.tar.gz' ) ) as $relative ) {
		if ( 0 === strpos( $relative, 'wp-content/plugins/ejemplo/' ) ) continue; // la carpeta del plugin cambia entera si algo suyo cambia
		digi_assert( is_file( $wp . $relative ), 'La restauración retiró ' . $relative . ', que el respaldo excluye por defecto.' );
	}
	digi_assert( $excluded['wp-content/backuply/backups-a1b2c3/backup_2026-11-01.tar.gz'] === file_get_contents( $wp . 'wp-content/backuply/backups-a1b2c3/backup_2026-11-01.tar.gz' ), 'La restauración cambió un respaldo de Backuply.' );
	digi_assert( ! is_file( $wp . 'wp-content/uploads/2026/11/ajeno.jpg' ), 'La restauración ya no retira lo ajeno de los medios (la prueba no distinguiría nada).' );

	// 3. El filtro permite incluirlo todo (o reducir la lista); nunca una carpeta de código ni wp-config.php.
	$GLOBALS['filters']['digitalisimo_backups_default_exclusions'] = function( $rules ) { return array(); };
	$all = digi_backup();
	list( $names, $manifest ) = $entries( $all['file'] );
	foreach ( array( 'wp-content/backuply/backups-a1b2c3/backup_2026-11-01.tar.gz', 'wp-content/cache/page_enhanced/example.test/index.html', 'wp-content/debug.log', 'error_log' ) as $relative ) digi_assert( in_array( 'wordpress/' . $relative, $names, true ), 'Con el filtro vacío no se incluyó ' . $relative . '.' );
	digi_assert( ! isset( $manifest['excluded'] ), 'Sin exclusiones, el manifiesto declara alguna.' );
	$GLOBALS['filters']['digitalisimo_backups_default_exclusions'] = function( $rules ) { return array( 'folders' => array( 'backuply', 'plugins', '../wp-admin', 'uploads' ), 'files' => array( 'wp-config.php', '*.php/x', '*.tar.gz' ) ); };
	$some = digi_backup();
	list( $names, $manifest ) = $entries( $some['file'] );
	digi_assert( ! preg_grep( '#^wordpress/wp-content/backuply/#', $names ) && in_array( 'wordpress/wp-content/updraft/backup_2026-11-01-db.gz', $names, true ) && in_array( 'wordpress/wp-content/plugins/ejemplo/ejemplo.php', $names, true ) && in_array( 'wordpress/wp-content/uploads/2026/11/foto.jpg', $names, true ) && in_array( 'wordpress/wp-config.php', $names, true ) && in_array( 'wordpress/wp-content/debug.log', $names, true ), 'Una lista reducida por el filtro no se aplicó bien o excluyó código: ' . json_encode( $manifest['excluded'] ?? null ) );
	digi_assert( array( 'wp-content/backuply/' ) === $manifest['excluded'], 'El manifiesto no declara sólo la regla válida del filtro: ' . json_encode( $manifest['excluded'] ?? null ) );

	// 4. El filtro sólo QUITA reglas (C6): lo que añade se ignora, así que la restauración nunca borra algo que el respaldo
	// dejó fuera; y nunca excluye código (C8).
	$put( 'wp-content/uploads/2026/11/importante.bak', 'copia manual del usuario' );
	$put( 'wp-content/wflogs/config-livewaf.php', '<?php // Wordfence' );
	$GLOBALS['filters']['digitalisimo_backups_default_exclusions'] = function( $rules ) { $rules['files'][] = '*.bak'; $rules['files'][] = '*.php'; $rules['files'][] = 'functions.php'; $rules['folders'][] = 'wflogs'; $rules['folders'][] = 'object-cache.php'; $rules['folders'][] = 'plugins'; return $rules; };
	$added = digi_backup();
	list( $names, $manifest ) = $entries( $added['file'] );
	foreach ( array( 'wp-content/uploads/2026/11/importante.bak', 'wp-content/wflogs/config-livewaf.php', 'wp-content/object-cache.php', 'wp-content/plugins/ejemplo/ejemplo.php', 'wp-content/themes/ejemplo/style.css' ) as $relative ) digi_assert( in_array( 'wordpress/' . $relative, $names, true ), 'Una regla añadida por el filtro dejó fuera ' . $relative . '.' );
	digi_assert( ! array_diff( (array) $manifest['excluded'], (array) $declared ) && ! in_array( '*.bak', (array) $manifest['excluded'], true ), 'El manifiesto declara reglas que no son de la lista fija: ' . json_encode( $manifest['excluded'] ) );
	$restored = $B::service_restore( $added['file'], array( 'confirm' => 'RESTAURAR' ) );
	digi_assert( ! is_wp_error( $restored ) && is_file( $wp . 'wp-content/uploads/2026/11/importante.bak' ), 'La restauración borró un medio que el filtro intentó excluir.' );
	unset( $GLOBALS['filters']['digitalisimo_backups_default_exclusions'] );
	$code = array( 'folders' => array( 'wp-content/object-cache.php' => true ), 'files' => array( '*.php', '*.phtml', 'functions.php', '*.log' ), 'code' => array( 'wp-admin', 'wp-content/plugins' ) );
	foreach ( array( 'wp-content/object-cache.php', 'wp-content/uploads/a.php', 'wp-content/themes/t/functions.php', 'wp-content/uploads/x.phtml', 'wp-content/plugins/p/debug.log', 'wp-admin/error_log' ) as $relative ) digi_assert( ! Digitalisimo_Backups_Exclusions::excluded( $relative, false, $code ), 'Una regla excluye código o un archivo de código: ' . $relative . '.' );
	digi_assert( Digitalisimo_Backups_Exclusions::excluded( 'wp-content/object-cache.php', true, $code ) && Digitalisimo_Backups_Exclusions::excluded( 'wp-content/uploads/debug.log', false, $code ), 'Las reglas válidas dejaron de aplicarse.' );
	foreach ( array( 'wp-content/backup-migration-Ab12/x.zip', 'wp-content/uploads/backwpup-1-logs/l.html', 'wp-content/uploads/wc-logs/a.log' ) as $relative ) digi_assert( Digitalisimo_Backups_Exclusions::kept( $relative ), 'La restauración no conserva ' . $relative . '.' );
	foreach ( array( 'wp-content/plugins/p/debug.log', 'wp-content/backup-migration-', 'wp-content/cache.php', 'wp-content/uploads/foto.jpg' ) as $relative ) digi_assert( ! Digitalisimo_Backups_Exclusions::kept( $relative ), 'La restauración conserva sin motivo ' . $relative . '.' );

	echo "OK: exclusiones por defecto del respaldo completo (B8)\n";
} finally {
	digi_rmtree( $root );
}
