<?php
namespace Digitalisimo\Tools;
defined( 'ABSPATH' ) || exit;

final class Media_WebP {
	const ACTION = 'digitalisimo_tools_webp_batch';
	const META_DONE = '_digitalisimo_tools_webp';
	const META_ERROR = '_digitalisimo_tools_webp_error';

	public static function init() {
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'process_next' ) );
		add_filter( 'display_media_states', array( __CLASS__, 'media_states' ), 10, 2 );
		add_filter( 'wp_prepare_attachment_for_js', array( __CLASS__, 'attachment_data' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'library_assets' ) );
	}

	private static function is_optimized( $attachment_id ) {
		$file = get_attached_file( $attachment_id );
		$optimized_file = get_post_meta( $attachment_id, self::META_DONE, true );
		return 'image/webp' === get_post_mime_type( $attachment_id ) && $file && $optimized_file && 'webp' === strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) && wp_normalize_path( $file ) === wp_normalize_path( $optimized_file );
	}

	public static function media_states( $states, $attachment ) {
		if ( self::is_optimized( $attachment->ID ) ) $states['digitalisimo_tools_webp'] = 'Optimizada · WebP';
		return $states;
	}

	public static function attachment_data( $response, $attachment ) {
		$response['digitalisimoOptimized'] = self::is_optimized( $attachment->ID );
		return $response;
	}

	public static function library_assets( $hook ) {
		if ( 'upload.php' !== $hook ) return;
		$base = plugin_dir_url( DIGITALISIMO_TOOLS_FILE );
		wp_enqueue_script( 'digitalisimo-tools-media-badges', $base . 'assets/media-badges.js', array( 'media-views' ), DIGITALISIMO_TOOLS_VERSION, true );
		wp_enqueue_style( 'digitalisimo-tools-media-badges', $base . 'assets/media-badges.css', array(), DIGITALISIMO_TOOLS_VERSION );
	}

	public static function page() {
		$network = is_multisite() && is_network_admin();
		if ( ! current_user_can( $network ? 'manage_network_options' : 'manage_options' ) ) return;
		$site_id = get_current_blog_id();
		$stats = self::stats( $network, $site_id );
		$nonce = wp_create_nonce( self::ACTION . ':' . ( $network ? 'network' : $site_id ) );
		$ajax_url = admin_url( 'admin-ajax.php' );
		echo '<h2>Optimizar imágenes WebP</h2><p>Convierte los adjuntos JPG y PNG a WebP con calidad 75 y actualiza la Biblioteca de Medios y sus tamaños. Los archivos anteriores se conservan para no romper enlaces antiguos.</p>';
		echo '<p class="description">' . esc_html( $network ? 'Alcance: todos los sitios de la red.' : 'Alcance: biblioteca de este sitio.' ) . '</p>';
		echo '<p id="digitalisimo-webp-summary">' . esc_html( self::summary( $stats ) ) . '</p><p><button type="button" id="digitalisimo-webp-start" class="button button-primary"' . disabled( 0 === ( $stats['pending'] + $stats['failed'] ), true, false ) . '>' . esc_html( $stats['pending'] ? 'Iniciar optimización' : 'Reintentar errores' ) . '</button></p><div id="digitalisimo-webp-progress" style="display:none;max-width:640px"><div style="height:18px;background:#dcdcde;border-radius:3px;overflow:hidden"><div id="digitalisimo-webp-bar" style="height:100%;width:0;background:#2271b1;transition:width .2s"></div></div><p id="digitalisimo-webp-status" aria-live="polite"></p></div>';
		echo '<script>document.addEventListener("DOMContentLoaded",function(){var button=document.getElementById("digitalisimo-webp-start"),box=document.getElementById("digitalisimo-webp-progress"),bar=document.getElementById("digitalisimo-webp-bar"),status=document.getElementById("digitalisimo-webp-status"),summary=document.getElementById("digitalisimo-webp-summary"),running=false,first=true;function step(){if(!running)return;var data=new URLSearchParams({action:"' . esc_js( self::ACTION ) . '",nonce:"' . esc_js( $nonce ) . '",scope:"' . ( $network ? 'network' : 'site' ) . '",site_id:"' . esc_js( (string) $site_id ) . '",retry:first?"1":"0"});first=false;fetch("' . esc_js( $ajax_url ) . '",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},body:data.toString()}).then(function(r){return r.json()}).then(function(r){if(!r.success)throw new Error((r.data&&r.data.message)||"No se pudo procesar la imagen.");var d=r.data,total=Math.max(1,Number(d.total)),percent=Math.min(100,Math.round((Number(d.processed)/total)*100));bar.style.width=percent+"%";summary.textContent=d.summary;status.textContent=d.message;if(d.complete){running=false;button.disabled=Number(d.failed)===0;button.textContent=Number(d.failed)>0?"Reintentar errores":"Optimización terminada";return}step()}).catch(function(error){running=false;button.disabled=false;button.textContent="Reintentar";status.textContent=error.message})}button.addEventListener("click",function(){if(running)return;running=true;first=true;button.disabled=true;button.textContent="Procesando…";box.style.display="block";step()})});</script>';
	}

	public static function process_next() {
		$network = is_multisite() && 'network' === sanitize_key( wp_unslash( $_POST['scope'] ?? '' ) );
		$site_id = absint( $_POST['site_id'] ?? 0 );
		if ( ! current_user_can( $network ? 'manage_network_options' : 'manage_options' ) || ( ! $network && $site_id !== get_current_blog_id() ) ) wp_send_json_error( array( 'message' => 'No autorizado para este sitio.' ), 403 );
		check_ajax_referer( self::ACTION . ':' . ( $network ? 'network' : $site_id ), 'nonce' );
		if ( ! empty( $_POST['retry'] ) ) self::clear_errors( $network, $site_id );
		$item = self::next_item( $network, $site_id );
		if ( ! $item ) {
			$stats = self::stats( $network, $site_id );
			wp_send_json_success( array( 'complete' => true, 'processed' => $stats['done'] + $stats['failed'], 'failed' => $stats['failed'], 'total' => $stats['total'], 'summary' => self::summary( $stats ), 'message' => 'No quedan imágenes pendientes.' ) );
		}

		$site_id = $item['site_id'];
		$switched = get_current_blog_id() !== $site_id;
		if ( $switched ) switch_to_blog( $site_id );
		try {
			$file = get_attached_file( $item['attachment_id'] );
			$result = self::convert( $item['attachment_id'], $file );
		} finally {
			if ( $switched ) restore_current_blog();
		}
		$stats = self::stats( $network, $site_id );
		wp_send_json_success( array( 'complete' => 0 === $stats['pending'], 'processed' => $stats['done'] + $stats['failed'], 'failed' => $stats['failed'], 'total' => $stats['total'], 'summary' => self::summary( $stats ), 'message' => $result['message'] ) );
	}

	private static function site_ids( $network, $site_id ) {
		if ( ! $network ) return array( $site_id );
		return array_map( 'intval', wp_list_pluck( get_sites( array( 'number' => 0, 'orderby' => 'blog_id' ) ), 'blog_id' ) );
	}

	private static function clear_errors( $network, $site_id ) {
		foreach ( self::site_ids( $network, $site_id ) as $target_id ) {
			$switched = get_current_blog_id() !== $target_id;
			if ( $switched ) switch_to_blog( $target_id );
			try {
				$ids = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => array( 'image/jpeg', 'image/png' ), 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => self::META_ERROR ) );
				foreach ( $ids as $id ) delete_post_meta( $id, self::META_ERROR );
			} finally {
				if ( $switched ) restore_current_blog();
			}
		}
	}

	private static function next_item( $network, $site_id ) {
		foreach ( self::site_ids( $network, $site_id ) as $target_id ) {
			$switched = get_current_blog_id() !== $target_id;
			if ( $switched ) switch_to_blog( $target_id );
			try {
				$ids = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => array( 'image/jpeg', 'image/png' ), 'posts_per_page' => 1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => self::META_ERROR, 'compare' => 'NOT EXISTS' ) ) ) );
				if ( $ids ) return array( 'site_id' => $target_id, 'attachment_id' => (int) $ids[0] );
			} finally {
				if ( $switched ) restore_current_blog();
			}
		}
		return null;
	}

	private static function convert( $attachment_id, $file ) {
		$name = $file ? wp_basename( $file ) : 'archivo no disponible';
		if ( ! $file || ! file_exists( $file ) ) {
			return self::failure( $attachment_id, $name, 'No se encontró el archivo original.' );
		}
		if ( 'webp' === strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) ) return self::failure( $attachment_id, $name, 'El archivo ya es WebP, pero WordPress lo registra como JPG/PNG; no se modificó.' );
		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) return self::failure( $attachment_id, $name, $editor->get_error_message() );
		if ( ! $editor->supports_mime_type( 'image/webp' ) ) return self::failure( $attachment_id, $name, 'El servidor no puede crear WebP.' );
		$editor->set_quality( 75 );
		$directory = dirname( $file );
		$output = $directory . '/' . wp_unique_filename( $directory, pathinfo( $file, PATHINFO_FILENAME ) . '.webp' );
		$saved = $editor->save( $output, 'image/webp' );
		if ( is_wp_error( $saved ) ) return self::failure( $attachment_id, $name, $saved->get_error_message() );
		if ( empty( $saved['path'] ) || ! is_file( $saved['path'] ) || ! filesize( $saved['path'] ) || 'image/webp' !== ( $saved['mime-type'] ?? '' ) ) {
			if ( is_file( $output ) ) wp_delete_file( $output );
			return self::failure( $attachment_id, $name, 'El archivo WebP generado no es válido.' );
		}
		$post = get_post( $attachment_id );
		$previous_metadata = wp_get_attachment_metadata( $attachment_id );
		$metadata = array();
		$updated = false;
		try {
			$change = array( 'ID' => $attachment_id, 'post_mime_type' => 'image/webp' );
			if ( $post && $post->post_title === $name ) $change['post_title'] = wp_basename( $saved['path'] );
			$result = wp_update_post( $change, true );
			if ( is_wp_error( $result ) || ! $result ) throw new \RuntimeException( 'No se pudo actualizar el adjunto.' );
			$updated = true;
			update_attached_file( $attachment_id, $saved['path'] );
			if ( get_attached_file( $attachment_id ) !== $saved['path'] ) throw new \RuntimeException( 'No se pudo cambiar el archivo principal del adjunto.' );
			require_once ABSPATH . 'wp-admin/includes/image.php';
			add_filter( 'wp_editor_set_quality', array( __CLASS__, 'webp_quality' ), 10, 2 );
			try {
				$metadata = wp_generate_attachment_metadata( $attachment_id, $saved['path'] );
			} finally {
				remove_filter( 'wp_editor_set_quality', array( __CLASS__, 'webp_quality' ), 10 );
			}
			if ( ! is_array( $metadata ) || empty( $metadata['file'] ) || empty( $metadata['width'] ) || empty( $metadata['height'] ) ) throw new \RuntimeException( 'No se pudieron crear los tamaños de la imagen.' );
			foreach ( (array) ( $metadata['sizes'] ?? array() ) as $size ) if ( 'webp' !== strtolower( pathinfo( $size['file'] ?? '', PATHINFO_EXTENSION ) ) ) throw new \RuntimeException( 'Algún tamaño no se generó como WebP.' );
			wp_update_attachment_metadata( $attachment_id, $metadata );
			$stored_metadata = wp_get_attachment_metadata( $attachment_id );
			$active_file = get_attached_file( $attachment_id );
			if ( ! is_array( $stored_metadata ) || ( $stored_metadata['file'] ?? '' ) !== $metadata['file'] || 'image/webp' !== get_post_mime_type( $attachment_id ) || ! $active_file || 'webp' !== strtolower( pathinfo( $active_file, PATHINFO_EXTENSION ) ) || ! is_file( $active_file ) ) throw new \RuntimeException( 'WordPress no conservó el WebP como archivo principal.' );
		} catch ( \Throwable $error ) {
			$generated_main = get_attached_file( $attachment_id );
			if ( $updated ) {
				wp_update_post( array( 'ID' => $attachment_id, 'post_mime_type' => $post->post_mime_type, 'post_title' => $post->post_title ) );
				update_attached_file( $attachment_id, $file );
				if ( is_array( $previous_metadata ) ) wp_update_attachment_metadata( $attachment_id, $previous_metadata );
				else delete_post_meta( $attachment_id, '_wp_attachment_metadata' );
			}
			self::delete_generated( $saved['path'], $metadata );
			if ( $generated_main && $generated_main !== $saved['path'] && $generated_main !== $file && dirname( $generated_main ) === dirname( $saved['path'] ) && 'webp' === strtolower( pathinfo( $generated_main, PATHINFO_EXTENSION ) ) && is_file( $generated_main ) ) wp_delete_file( $generated_main );
			return self::failure( $attachment_id, $name, $error->getMessage() );
		}
		update_post_meta( $attachment_id, self::META_DONE, get_attached_file( $attachment_id ) );
		delete_post_meta( $attachment_id, self::META_ERROR );
		return array( 'message' => $name . ' → ' . wp_basename( get_attached_file( $attachment_id ) ) . ' optimizada y aplicada en la biblioteca.' );
	}

	public static function webp_quality( $quality, $mime_type ) { return 'image/webp' === $mime_type ? 75 : $quality; }

	private static function delete_generated( $file, $metadata ) {
		$directory = dirname( $file );
		foreach ( (array) ( $metadata['sizes'] ?? array() ) as $size ) {
			$name = $size['file'] ?? '';
			if ( $name && 'webp' === strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) wp_delete_file( $directory . '/' . wp_basename( $name ) );
		}
		if ( is_file( $file ) ) wp_delete_file( $file );
	}

	private static function failure( $attachment_id, $name, $message ) {
		update_post_meta( $attachment_id, self::META_ERROR, $message );
		return array( 'message' => $name . ': ' . $message );
	}

	private static function stats( $network, $site_id ) {
		$stats = array( 'pending' => 0, 'done' => 0, 'failed' => 0 );
		foreach ( self::site_ids( $network, $site_id ) as $target_id ) {
			$switched = get_current_blog_id() !== $target_id;
			if ( $switched ) switch_to_blog( $target_id );
			try {
				$base = array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => array( 'image/jpeg', 'image/png' ), 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => false );
				$pending = new \WP_Query( $base + array( 'meta_query' => array( array( 'key' => self::META_ERROR, 'compare' => 'NOT EXISTS' ) ) ) );
				$done = new \WP_Query( array_merge( $base, array( 'post_mime_type' => 'image/webp', 'meta_key' => self::META_DONE, 'meta_compare' => 'EXISTS' ) ) );
				$failed = new \WP_Query( $base + array( 'meta_key' => self::META_ERROR, 'meta_compare' => 'EXISTS' ) );
				$stats['pending'] += (int) $pending->found_posts; $stats['done'] += (int) $done->found_posts; $stats['failed'] += (int) $failed->found_posts;
			} finally {
				if ( $switched ) restore_current_blog();
			}
		}
		$stats['total'] = $stats['pending'] + $stats['done'] + $stats['failed'];
		return $stats;
	}

	private static function summary( $stats ) { return sprintf( 'Total: %d · Optimizadas: %d · Pendientes: %d · Con error: %d', $stats['total'], $stats['done'], $stats['pending'], $stats['failed'] ); }
}
