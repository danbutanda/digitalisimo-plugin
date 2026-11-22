<?php
/**
 * Sólo Playground: un clon del sitio de tools-cache-salt.sh en el mismo servidor (la misma caché compartida). Con
 * DIGI_CS_STEP=preparar copia el wp-config.php del original (con su bloque de sal) cambiando sólo DB_NAME, como hacen WP
 * Toolkit o Softaculous al clonar a otra base, pone el drop-in y su propio nombre; con DIGI_CS_STEP=clon carga el clon
 * (sin escritorio: un clon suele no poder abrirlo) y deja en /digi-cs/clon.json qué sal y qué nombre ve.
 */
$step = getenv( 'DIGI_CS_STEP' );
require '/wordpress/wp-load.php';
if ( 'preparar' === $step ) {
	update_option( 'blogname', 'Clon de staging' );
	copy( '/digi-cs/object-cache.php', WP_CONTENT_DIR . '/object-cache.php' );
	$config = (string) file_get_contents( '/digi-cs/wp-config-despues.php' );
	$count  = 0;
	$config = preg_replace( "/define\(\s*'DB_NAME'\s*,\s*'[^']*'\s*\)/", "define( 'DB_NAME', 'staging_db' )", $config, 1, $count );
	if ( ! $count ) $config = preg_replace( '/^<\?php\s*/', "<?php\ndefine( 'DB_NAME', 'staging_db' );\n", $config, 1 );
	file_put_contents( ABSPATH . 'wp-config.php', $config );
	return;
}
$original = json_decode( (string) file_get_contents( '/digi-cs/dos.json' ), true );
$file     = '/digi-cache/' . md5( (string) $original['salt'] . $GLOBALS['table_prefix'] . 'options:alloptions' ) . '.txt';
file_put_contents( '/digi-cs/clon.json', json_encode( array(
	'salt'     => defined( 'WP_CACHE_KEY_SALT' ) ? WP_CACHE_KEY_SALT : null,
	'blogname' => (string) ( wp_load_alloptions()['blogname'] ?? '' ),
	'original' => is_file( $file ) ? (string) ( unserialize( (string) file_get_contents( $file ) )['blogname'] ?? '' ) : 'sin clave',
	'db_name'  => DB_NAME,
) ) );
