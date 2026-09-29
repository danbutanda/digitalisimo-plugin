<?php
namespace Digitalisimo\Tools\Elementor_Network_Templates;
defined( 'ABSPATH' ) || exit;

/** Registro de red. El UUID es la identidad estable; los IDs sólo son ubicaciones. */
final class Registry {
	const OPTION = 'digitalisimo_tools_network_templates';
	const SETTINGS = 'digitalisimo_tools_settings';
	public static function settings() { return wp_parse_args( (array) get_site_option( self::SETTINGS, array() ), array( 'master_blog_id' => is_multisite() ? get_main_site_id() : 1, 'log_limit' => 200 ) ); }
	public static function master_blog_id() { return absint( self::settings()['master_blog_id'] ); }
	public static function all() { return (array) get_site_option( self::OPTION, array() ); }
	public static function get( $uuid ) { $all = self::all(); return isset( $all[ $uuid ] ) ? $all[ $uuid ] : null; }
	public static function by_master_post( $post_id ) { foreach ( self::all() as $item ) if ( absint( $item['master_post_id'] ?? 0 ) === absint( $post_id ) ) return $item; return null; }
	public static function by_master_ids( array $ids ) { $found = array(); foreach ( $ids as $id ) { $item = self::by_master_post( $id ); if ( $item ) $found[ $id ] = $item; } return $found; }
	public static function save( array $item ) {
		$uuid = sanitize_key( $item['uuid'] ?? '' ); if ( ! $uuid ) return new \WP_Error( 'digitalisimo_tools_uuid', 'Falta el identificador global.' );
		$all = self::all(); $old = $all[ $uuid ] ?? array();
		$all[ $uuid ] = wp_parse_args( $item, array( 'uuid' => $uuid, 'master_blog_id' => self::master_blog_id(), 'master_post_id' => 0, 'auto_sync' => false, 'targets' => array( 'mode' => 'all', 'include' => array(), 'exclude' => array() ), 'locked' => false, 'collection' => '', 'last_sync' => array(), 'active' => true ) );
		if ( ! empty( $old['sites'] ) ) $all[ $uuid ]['sites'] = $old['sites']; elseif ( empty( $all[ $uuid ]['sites'] ) ) $all[ $uuid ]['sites'] = array();
		update_site_option( self::OPTION, $all ); return $all[ $uuid ];
	}
	public static function update( $uuid, array $changes ) { $item = self::get( $uuid ); return $item ? self::save( array_merge( $item, $changes ) ) : new \WP_Error( 'digitalisimo_tools_missing', 'La plantilla global no existe.' ); }
	public static function destinations( array $item ) {
		if ( ! is_multisite() ) return array(); $targets = (array) ( $item['targets'] ?? array() ); $ids = 'selected' === ( $targets['mode'] ?? 'all' ) ? array_map( 'absint', (array) ( $targets['include'] ?? array() ) ) : wp_list_pluck( get_sites( array( 'number' => 0 ) ), 'blog_id' );
		$ids = array_diff( $ids, array_map( 'absint', (array) ( $targets['exclude'] ?? array() ) ), array( absint( $item['master_blog_id'] ) ) ); return array_values( array_unique( array_filter( $ids ) ) );
	}
	public static function set_mapping( $uuid, $blog_id, $post_id ) { $item = self::get( $uuid ); if ( ! $item ) return; $item['sites'][ absint( $blog_id ) ] = absint( $post_id ); $item['last_sync'][ absint( $blog_id ) ] = time(); self::save( $item ); }
}
