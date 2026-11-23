<?php
namespace Digitalisimo\Tools\Elementor_Network_Templates;
defined( 'ABSPATH' ) || exit;
final class Elementor_Adapter {
	public static function available() { return did_action( 'elementor/loaded' ) || defined( 'ELEMENTOR_VERSION' ); }
	/**
	 * ¿Está el constructor activo en ese sitio? La petición sólo carga los plugins de la red y los del sitio que la
	 * atiende (en la red, el principal): para otro sitio maestro se mira su lista de plugins activos, sin cargarlo.
	 * DIGITALÍSIMO Elements se reconoce por su archivo (`pro-elements.php`, en cualquier carpeta) y Elementor por el suyo.
	 */
	public static function active_on( $blog_id ) {
		$blog_id = absint( $blog_id );
		if ( ! is_multisite() || $blog_id === get_current_blog_id() ) return self::available();
		$files = array_merge( array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ), (array) get_blog_option( $blog_id, 'active_plugins', array() ) );
		foreach ( $files as $file ) if ( preg_match( '#(^|/)(pro-elements|elementor)\.php$#', (string) $file ) ) return true;
		return false;
	}
	public static function template_posts() { return get_posts( array( 'post_type' => 'elementor_library', 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ); }
	public static function export( array $item ) {
		$master = get_post( absint( $item['master_post_id'] ) ); if ( ! $master || 'elementor_library' !== $master->post_type ) return new \WP_Error( 'digitalisimo_tools_master', 'La plantilla maestra no existe o no es una plantilla guardada de DIGITALÍSIMO Elements.' );
		$data = json_decode( (string) get_post_meta( $master->ID, '_elementor_data', true ), true ); if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) return new \WP_Error( 'digitalisimo_tools_data', 'La plantilla maestra contiene datos de diseño inválidos.' );
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
		// El CSS y el HTML guardados de la copia son de la versión anterior: se borran aquí, sin el constructor (la cola
		// corre en un sitio que puede no cargarlo, y su clear_cache() sólo actúa una vez por petición). Se regeneran en
		// la siguiente visita a la copia.
		delete_post_meta( $post_id, '_elementor_css' );
		delete_post_meta( $post_id, '_elementor_element_cache' );
		clean_post_cache( $post_id ); if ( class_exists( '\Elementor\Plugin' ) ) { $plugin = \Elementor\Plugin::$instance; if ( isset( $plugin->files_manager ) && method_exists( $plugin->files_manager, 'clear_cache' ) ) $plugin->files_manager->clear_cache(); }
		do_action( 'digitalisimo_tools_elementor_template_synced', $post_id );
	}
}
