<?php
namespace Digitalisimo\Tools\Elementor_Network_Templates;
defined( 'ABSPATH' ) || exit;
final class Id_Mapper {
	const UUID_META = '_digitalisimo_tools_network_template_uuid'; const MANAGED_META = '_digitalisimo_tools_network_managed'; const MASTER_BLOG_META = '_digitalisimo_tools_network_master_blog_id'; const MASTER_POST_META = '_digitalisimo_tools_network_master_post_id'; const SYNC_META = '_digitalisimo_tools_network_last_sync';
	public static function local_id( $uuid, $blog_id = 0 ) { $blog_id = $blog_id ?: get_current_blog_id(); $item = Registry::get( $uuid ); $id = absint( $item['sites'][ $blog_id ] ?? 0 ); if ( $id && get_post( $id ) && get_post_meta( $id, self::UUID_META, true ) === $uuid ) return $id; $posts = get_posts( array( 'post_type' => 'elementor_library', 'post_status' => 'any', 'meta_key' => self::UUID_META, 'meta_value' => $uuid, 'fields' => 'ids', 'numberposts' => 1 ) ); return $posts ? absint( $posts[0] ) : 0; }
	public static function mark( $post_id, array $item ) { update_post_meta( $post_id, self::UUID_META, $item['uuid'] ); update_post_meta( $post_id, self::MANAGED_META, '1' ); update_post_meta( $post_id, self::MASTER_BLOG_META, absint( $item['master_blog_id'] ) ); update_post_meta( $post_id, self::MASTER_POST_META, absint( $item['master_post_id'] ) ); update_post_meta( $post_id, self::SYNC_META, current_time( 'mysql', true ) ); }
	public static function belongs_to( $post_id, $uuid ) { return $uuid === get_post_meta( $post_id, self::UUID_META, true ); }
}
