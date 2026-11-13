<?php
// Constantes comunes antes del entorno: el sitio y el almacén son fijos entre los tres procesos (revisión A1).
$S = getenv( 'DIGI_PARTS_UPGRADE' );
define( 'ABSPATH', $S . '/site/' );
define( 'WP_CONTENT_DIR', $S . '/site/wp-content' );
define( 'WP_PLUGIN_DIR', $S . '/site/wp-content/plugins' );
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', $S . '/store' );
error_reporting( E_ALL & ~E_WARNING & ~E_NOTICE );
function up_save( $file ) { file_put_contents( $file, serialize( array( 'o' => $GLOBALS['bk_options'], 's' => $GLOBALS['bk_site_options'], 'db' => $GLOBALS['wpdb'] ) ) ); }
function up_load( $file ) { $d = unserialize( file_get_contents( $file ) ); $GLOBALS['bk_options'] = $d['o']; $GLOBALS['bk_site_options'] = $d['s']; $GLOBALS['wpdb'] = $d['db']; }
