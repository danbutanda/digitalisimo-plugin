<?php
namespace Digitalisimo\Tools\Elementor_Network_Templates;
defined( 'ABSPATH' ) || exit;
final class Elementor_Adapter {
	public static function available() { return did_action( 'elementor/loaded' ) || defined( 'ELEMENTOR_VERSION' ); }
	public static function template_posts() { return get_posts( array( 'post_type' => 'elementor_library', 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ); }
	public static function export( array $item ) {
		$master = get_post( absint( $item['master_post_id'] ) ); if ( ! $master || 'elementor_library' !== $master->post_type ) return new \WP_Error( 'digitalisimo_tools_master', 'La plantilla maestra no existe o no es una Saved Template de Elementor.' );
		$data = json_decode( (string) get_post_meta( $master->ID, '_elementor_data', true ), true ); if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) return new \WP_Error( 'digitalisimo_tools_data', 'La plantilla maestra contiene _elementor_data inválido.' );
		$meta = array(); foreach ( array( '_elementor_edit_mode', '_elementor_template_type', '_elementor_page_settings', '_elementor_version', '_elementor_pro_version' ) as $key ) $meta[ $key ] = get_post_meta( $master->ID, $key, true );
		return array( 'post' => $master, 'data' => $data, 'meta' => $meta );
	}
	public static function copy( array $item, $destination_blog, array $dependency_ids, array $source ) {
		$master = $source['post'];
		$existing = Id_Mapper::local_id( $item['uuid'], $destination_blog );
		if ( $existing && ! Id_Mapper::belongs_to( $existing, $item['uuid'] ) ) return new \WP_Error( 'digitalisimo_tools_integrity', 'La copia destino no pertenece a esta plantilla global.' );
		$postarr = array( 'ID' => $existing, 'post_type' => 'elementor_library', 'post_status' => $master->post_status, 'post_title' => $master->post_title, 'post_name' => $master->post_name, 'post_excerpt' => $master->post_excerpt, 'post_content' => $master->post_content );
		$local_id = $existing ? wp_update_post( $postarr, true ) : wp_insert_post( $postarr, true ); if ( is_wp_error( $local_id ) ) return $local_id;
		foreach ( $source['meta'] as $key => $value ) if ( '' !== $value && null !== $value ) update_post_meta( $local_id, $key, $value );
		update_post_meta( $local_id, '_elementor_data', wp_slash( wp_json_encode( Dependency_Resolver::remap_data( $source['data'], $dependency_ids ) ) ) );
		Id_Mapper::mark( $local_id, $item ); self::clear_cache( $local_id ); return $local_id;
	}
	public static function clear_cache( $post_id ) {
		clean_post_cache( $post_id ); if ( class_exists( '\Elementor\Plugin' ) ) { $plugin = \Elementor\Plugin::$instance; if ( isset( $plugin->files_manager ) && method_exists( $plugin->files_manager, 'clear_cache' ) ) $plugin->files_manager->clear_cache(); }
		do_action( 'digitalisimo_tools_elementor_template_synced', $post_id );
	}
}
