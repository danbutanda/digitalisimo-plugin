<?php
/**
 * Plugin Name: Caché compartida de prueba (DIGITALÍSIMO)
 *
 * Drop-in sólo para Playground: una caché de objetos persistente en /digi-cache (un archivo por clave), compartida por
 * todos los WordPress que monten esa carpeta, con la clave `<WP_CACHE_KEY_SALT><$table_prefix o id del sitio><grupo>:<clave>`
 * como Memcached y Redis Object Cache: dos sitios con `wp_` y sin sal leen las mismas claves (incidente de plomerito).
 * Respeta los grupos no persistentes; wp_cache_flush() vacía todo y lo anota en /digi-cache/GLOBAL_FLUSH.
 */
defined( 'WP_CACHE_KEY_SALT' ) || define( 'WP_CACHE_KEY_SALT', '' );

class WP_Object_Cache {
	public $cache = array();
	private $np = array();
	private $global = array();
	private $prefix = '';

	public function __construct() {
		$this->switch_to_blog( function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 1 );
	}
	private function key( $key, $group ) {
		$group = '' === (string) $group ? 'default' : (string) $group;
		return WP_CACHE_KEY_SALT . ( in_array( $group, $this->global, true ) ? '' : $this->prefix ) . $group . ':' . $key;
	}
	private function file( $full ) {
		return '/digi-cache/' . md5( $full ) . '.txt';
	}
	private function persistent( $group ) {
		return ! in_array( '' === (string) $group ? 'default' : (string) $group, $this->np, true );
	}
	public function get( $key, $group = 'default', $force = false, &$found = null ) {
		$full = $this->key( $key, $group );
		if ( ( ! $force || ! $this->persistent( $group ) ) && array_key_exists( $full, $this->cache ) ) {
			$found = true;
			return is_object( $this->cache[ $full ] ) ? clone $this->cache[ $full ] : $this->cache[ $full ];
		}
		$raw = $this->persistent( $group ) && is_file( $this->file( $full ) ) ? (string) @file_get_contents( $this->file( $full ) ) : '';
		if ( '' === $raw ) {
			$found = false;
			return false;
		}
		$value               = unserialize( $raw );
		$this->cache[ $full ] = $value;
		$found               = true;
		return $value;
	}
	public function set( $key, $value, $group = 'default', $expire = 0 ) {
		$full                = $this->key( $key, $group );
		$this->cache[ $full ] = is_object( $value ) ? clone $value : $value;
		if ( $this->persistent( $group ) ) file_put_contents( $this->file( $full ), serialize( $value ), LOCK_EX );
		return true;
	}
	public function add( $key, $value, $group = 'default', $expire = 0 ) {
		if ( function_exists( 'wp_suspend_cache_addition' ) && wp_suspend_cache_addition() ) return false;
		$this->get( $key, $group, false, $found );
		return $found ? false : $this->set( $key, $value, $group, $expire );
	}
	public function replace( $key, $value, $group = 'default', $expire = 0 ) {
		$this->get( $key, $group, false, $found );
		return $found ? $this->set( $key, $value, $group, $expire ) : false;
	}
	public function delete( $key, $group = 'default' ) {
		$full = $this->key( $key, $group );
		unset( $this->cache[ $full ] );
		if ( $this->persistent( $group ) ) @unlink( $this->file( $full ) );
		return true;
	}
	public function incr( $key, $offset = 1, $group = 'default' ) {
		$value = max( 0, (int) $this->get( $key, $group ) + (int) $offset );
		$this->set( $key, $value, $group );
		return $value;
	}
	public function flush() {
		$this->cache = array();
		foreach ( (array) glob( '/digi-cache/*.txt' ) as $file ) @unlink( $file );
		file_put_contents( '/digi-cache/GLOBAL_FLUSH', "1\n", FILE_APPEND );
		return true;
	}
	public function flush_runtime() {
		$this->cache = array();
		return true;
	}
	public function add_global_groups( $groups ) {
		$this->global = array_values( array_unique( array_merge( $this->global, (array) $groups ) ) );
	}
	public function add_non_persistent_groups( $groups ) {
		$this->np = array_values( array_unique( array_merge( $this->np, (array) $groups ) ) );
	}
	public function switch_to_blog( $blog_id ) {
		global $table_prefix;
		$this->prefix = function_exists( 'is_multisite' ) && is_multisite() ? (int) $blog_id . ':' : (string) $table_prefix;
	}
}

function wp_cache_init() { $GLOBALS['wp_object_cache'] = new WP_Object_Cache(); }
function wp_cache_add( $key, $data, $group = '', $expire = 0 ) { return $GLOBALS['wp_object_cache']->add( $key, $data, $group, (int) $expire ); }
function wp_cache_set( $key, $data, $group = '', $expire = 0 ) { return $GLOBALS['wp_object_cache']->set( $key, $data, $group, (int) $expire ); }
function wp_cache_replace( $key, $data, $group = '', $expire = 0 ) { return $GLOBALS['wp_object_cache']->replace( $key, $data, $group, (int) $expire ); }
function wp_cache_get( $key, $group = '', $force = false, &$found = null ) { return $GLOBALS['wp_object_cache']->get( $key, $group, $force, $found ); }
function wp_cache_delete( $key, $group = '' ) { return $GLOBALS['wp_object_cache']->delete( $key, $group ); }
function wp_cache_incr( $key, $offset = 1, $group = '' ) { return $GLOBALS['wp_object_cache']->incr( $key, $offset, $group ); }
function wp_cache_decr( $key, $offset = 1, $group = '' ) { return $GLOBALS['wp_object_cache']->incr( $key, -$offset, $group ); }
function wp_cache_flush() { return $GLOBALS['wp_object_cache']->flush(); }
function wp_cache_flush_runtime() { return $GLOBALS['wp_object_cache']->flush_runtime(); }
function wp_cache_supports( $feature ) { return 'flush_runtime' === $feature; }
function wp_cache_close() { return true; }
function wp_cache_add_global_groups( $groups ) { $GLOBALS['wp_object_cache']->add_global_groups( $groups ); }
function wp_cache_add_non_persistent_groups( $groups ) { $GLOBALS['wp_object_cache']->add_non_persistent_groups( $groups ); }
function wp_cache_switch_to_blog( $blog_id ) { $GLOBALS['wp_object_cache']->switch_to_blog( $blog_id ); }
function wp_cache_reset() { $GLOBALS['wp_object_cache']->flush_runtime(); }
