<?php
namespace Digitalisimo\Tools\Elementor_Network_Templates;
defined( 'ABSPATH' ) || exit;
final class Lock {
	public static function init() { add_action( 'admin_notices', array( __CLASS__, 'notice' ) ); add_action( 'load-post.php', array( __CLASS__, 'guard_editor' ) ); }
	private static function item_for_current_post() { $id = absint( $_GET['post'] ?? 0 ); if ( ! $id || 'elementor_library' !== get_post_type( $id ) ) return null; $uuid = get_post_meta( $id, Id_Mapper::UUID_META, true ); $item = $uuid ? Registry::get( $uuid ) : null; return $item && ! empty( $item['locked'] ) ? $item : null; }
	public static function notice() { if ( ! is_multisite() || get_current_blog_id() === Registry::master_blog_id() ) return; if ( self::item_for_current_post() ) echo '<div class="notice notice-warning"><p>Esta plantilla es administrada desde DIGITALÍSIMO Tools a nivel de Red. La edición local está bloqueada.</p></div>'; }
	public static function guard_editor() { if ( current_user_can( 'manage_network_options' ) || ! self::item_for_current_post() ) return; if ( ! empty( $_GET['action'] ) && 'elementor' === $_GET['action'] ) wp_die( esc_html__( 'Esta plantilla es administrada desde DIGITALÍSIMO Tools a nivel de Red.', 'digitalisimo-tools' ), esc_html__( 'Edición local bloqueada', 'digitalisimo-tools' ), array( 'back_link' => true ) ); }
}
