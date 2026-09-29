<?php
namespace Digitalisimo\Tools\Elementor_Network_Templates;
defined( 'ABSPATH' ) || exit;
final class Sync {
	const CRON = 'digitalisimo_tools_sync_template'; const LOCK_PREFIX = 'digitalisimo_tools_sync_';
	public static function init() { add_action( self::CRON, array( __CLASS__, 'run_job' ), 10, 2 ); add_action( 'save_post_elementor_library', array( __CLASS__, 'auto_enqueue' ), 30, 3 ); }
	public static function enqueue( $uuid, $blog_id = 0 ) {
		$item = Registry::get( $uuid ); if ( ! $item ) return;
		$targets = $blog_id ? array( absint( $blog_id ) ) : Registry::destinations( $item );
		foreach ( $targets as $target ) if ( ! wp_next_scheduled( self::CRON, array( $uuid, absint( $target ) ) ) ) wp_schedule_single_event( time() + 5, self::CRON, array( $uuid, absint( $target ) ) );
	}
	public static function auto_enqueue( $post_id, $post, $update ) { if ( ! $update || get_current_blog_id() !== Registry::master_blog_id() ) return; $item = Registry::by_master_post( $post_id ); if ( $item && ! empty( $item['auto_sync'] ) ) self::enqueue( $item['uuid'] ); }
	public static function run_job( $uuid, $blog_id = 0 ) { $item = Registry::get( $uuid ); if ( ! $item || empty( $item['active'] ) || ! is_multisite() || ! Elementor_Adapter::available() || ! $blog_id ) return; self::sync_to_blog( $item, absint( $blog_id ), array(), array() ); }
	private static function sync_to_blog( array $item, $target, array $stack, array $resolved ) {
		if ( isset( $stack[ $item['uuid'] ] ) ) { Logger::add( $item['uuid'], $item['master_blog_id'], $target, 'error', 0, 'Dependencia circular detectada.' ); return new \WP_Error( 'digitalisimo_tools_cycle', 'Dependencia circular detectada.' ); }
		$lock = self::LOCK_PREFIX . $item['uuid'] . '_' . $target; if ( get_site_transient( $lock ) ) return new \WP_Error( 'digitalisimo_tools_locked', 'La sincronización ya está en curso.' ); set_site_transient( $lock, 1, 10 * MINUTE_IN_SECONDS ); $started = microtime( true );
		try { $stack[ $item['uuid'] ] = true; $ids = array(); switch_to_blog( absint( $item['master_blog_id'] ) ); try { $dependencies = Dependency_Resolver::dependencies( $item ); } finally { restore_current_blog(); }
			foreach ( $dependencies as $master_id => $dependency ) { $result = self::sync_to_blog( $dependency, $target, $stack, $resolved ); if ( is_wp_error( $result ) ) return $result; $ids[ absint( $master_id ) ] = absint( $result ); }
			switch_to_blog( absint( $item['master_blog_id'] ) ); try { $source = Elementor_Adapter::export( $item ); } finally { restore_current_blog(); } if ( is_wp_error( $source ) ) return $source;
			switch_to_blog( $target ); try { $result = Elementor_Adapter::copy( $item, $target, $ids, $source ); } finally { restore_current_blog(); }
			if ( is_wp_error( $result ) ) { Logger::add( $item['uuid'], $item['master_blog_id'], $target, 'error', microtime( true ) - $started, $result->get_error_message() ); return $result; }
			Registry::set_mapping( $item['uuid'], $target, $result ); Logger::add( $item['uuid'], $item['master_blog_id'], $target, 'synced', microtime( true ) - $started ); return $result;
		} finally { delete_site_transient( $lock ); }
	}
}
