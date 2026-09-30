<?php
/** Valida aislamiento Multisite y limpieza sin vaciar la caché externa. */
define( 'ABSPATH', __DIR__ );
define( 'WP_CONTENT_DIR', __DIR__ . '/fixtures/wp-content' );
$blog_id = 1;
$options = array();
$network_options = array();
$cache = array();
function get_current_blog_id() { global $blog_id; return $blog_id; }
function sanitize_key( $key ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', $key ) ); }
function get_option( $key, $default = false ) { global $options, $blog_id; return $options[ $blog_id ][ $key ] ?? $default; }
function update_option( $key, $value ) { global $options, $blog_id; $options[ $blog_id ][ $key ] = $value; return true; }
function get_site_option( $key, $default = false ) { global $network_options; return $network_options[ $key ] ?? $default; }
function update_site_option( $key, $value ) { global $network_options; $network_options[ $key ] = $value; return true; }
function is_multisite() { return true; }
function wp_cache_get( $key, $group, $force = false, &$found = null ) { global $cache; $found = array_key_exists( $group . ':' . $key, $cache ); return $found ? $cache[ $group . ':' . $key ] : false; }
function wp_cache_set( $key, $value, $group, $ttl = 0 ) { global $cache; $cache[ $group . ':' . $key ] = $value; return true; }
function wp_cache_delete( $key, $group ) { global $cache; unset( $cache[ $group . ':' . $key ] ); return true; }
function wp_using_ext_object_cache() { return true; }
require __DIR__ . '/../digitalisimo-seo/includes/performance/class-performance-cache.php';
$class = Digitalisimo_Integrations_Performance_Cache::class;
$class::set( 'kit', array( 'site' => 1 ) );
$found = false;
if ( array( 'site' => 1 ) !== $class::get( 'kit', $found ) || ! $found ) throw new RuntimeException( 'Debe encontrar su valor del sitio 1.' );
$blog_id = 2;
$found = true;
if ( false !== $class::get( 'kit', $found ) || $found ) throw new RuntimeException( 'No debe mezclar claves de los sitios 1 y 2.' );
$class::set( 'kit', array( 'site' => 2 ) );
$blog_id = 1;
$class::invalidate();
$found = true;
if ( false !== $class::get( 'kit', $found ) || $found ) throw new RuntimeException( 'La invalidación debe ocultar datos antiguos del sitio 1.' );
$blog_id = 2;
if ( array( 'site' => 2 ) !== $class::get( 'kit', $found ) || ! $found ) throw new RuntimeException( 'Limpiar sitio 1 no debe afectar sitio 2.' );
if ( count( $cache ) !== 1 ) throw new RuntimeException( 'La invalidación sólo debe borrar las claves conocidas del sitio 1.' );
$class::invalidate_network();
$found = true;
if ( false !== $class::get( 'kit', $found ) || $found ) throw new RuntimeException( 'Cambiar defaults de red debe invalidar también los datos heredados del sitio 2.' );
echo "Caché de rendimiento: aislamiento e invalidación selectiva correctos.\n";
