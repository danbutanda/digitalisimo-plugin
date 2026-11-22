<?php
/** Una «petición» del escritorio de un administrador; deja en /digi-cs/<paso>.json lo que vio. */
$step = getenv( 'DIGI_CS_STEP' );
define( 'WP_ADMIN', true );
require '/wordpress/wp-load.php';
wp_set_current_user( 1 );
global $wpdb;
$before = array( 'ext' => wp_using_ext_object_cache(), 'salt' => defined( 'WP_CACHE_KEY_SALT' ) && '' !== WP_CACHE_KEY_SALT ? WP_CACHE_KEY_SALT : null, 'home' => (string) ( wp_load_alloptions()['home'] ?? '' ) ); // Crudo, sin WP_HOME.
do_action( 'admin_init' );
$config   = (string) file_get_contents( ABSPATH . 'wp-config.php' );
$neighbor = '/digi-cache/' . md5( $GLOBALS['table_prefix'] . 'options:alloptions' ) . '.txt';
$out      = $before + array(
	'result'   => class_exists( 'Digitalisimo_Cache_Salt', false ) ? Digitalisimo_Cache_Salt::result() : null,
	'notice'   => class_exists( 'Digitalisimo\\Tools\\Cache_Notice', false ) ? Digitalisimo\Tools\Cache_Notice::status() : null,
	'marks'    => substr_count( $config, '/* DIGITALÍSIMO: sal propia de la caché de objetos */' ),
	'copies'   => glob( ABSPATH . 'wp-config-antes-de-la-sal-*.php' ),
	'lint'     => ( function ( $c ) { try { token_get_all( $c, TOKEN_PARSE ); return 'ok'; } catch ( Throwable $e ) { return $e->getMessage(); } } )( $config ),
	'db_home'  => $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'home'" ),
	'neighbor' => is_file( $neighbor ) ? ( unserialize( (string) file_get_contents( $neighbor ) )['home'] ?? '' ) : '',
	'flushed'  => is_file( '/digi-cache/GLOBAL_FLUSH' ),
);
file_put_contents( '/digi-cs/' . $step . '.json', json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
if ( 'uno' === $step ) file_put_contents( '/digi-cs/wp-config-despues.php', $config );
