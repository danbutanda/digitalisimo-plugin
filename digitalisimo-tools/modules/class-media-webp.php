<?php
namespace Digitalisimo\Tools;
defined( 'ABSPATH' ) || exit;

final class Media_WebP {
	const ACTION = 'digitalisimo_tools_webp_batch';
	const META_DONE = '_digitalisimo_tools_webp';
	const META_ERROR = '_digitalisimo_tools_webp_error';

	public static function init() { add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'process_next' ) ); }

	public static function page() {
		$network = is_multisite() && is_network_admin();
		if ( ! current_user_can( $network ? 'manage_network_options' : 'manage_options' ) ) return;
		$site_id = get_current_blog_id();
		$stats = self::stats( $network, $site_id );
		$nonce = wp_create_nonce( self::ACTION . ':' . ( $network ? 'network' : $site_id ) );
		$ajax_url = admin_url( 'admin-ajax.php' );
		echo '<h2>Optimizar imágenes WebP</h2><p>Procesa una imagen por solicitud con calidad 75. El original se conserva y cada resultado queda registrado.</p>';
		echo '<p class="description">' . esc_html( $network ? 'Alcance: todos los sitios de la red.' : 'Alcance: biblioteca de este sitio.' ) . '</p>';
		echo '<p id="digitalisimo-webp-summary">' . esc_html( self::summary( $stats ) ) . '</p><p><button type="button" id="digitalisimo-webp-start" class="button button-primary"' . disabled( 0 === $stats['pending'], true, false ) . '>Iniciar optimización</button></p><div id="digitalisimo-webp-progress" style="display:none;max-width:640px"><div style="height:18px;background:#dcdcde;border-radius:3px;overflow:hidden"><div id="digitalisimo-webp-bar" style="height:100%;width:0;background:#2271b1;transition:width .2s"></div></div><p id="digitalisimo-webp-status" aria-live="polite"></p></div>';
		echo '<script>document.addEventListener("DOMContentLoaded",function(){var button=document.getElementById("digitalisimo-webp-start"),box=document.getElementById("digitalisimo-webp-progress"),bar=document.getElementById("digitalisimo-webp-bar"),status=document.getElementById("digitalisimo-webp-status"),summary=document.getElementById("digitalisimo-webp-summary"),running=false;function step(){if(!running)return;var data=new URLSearchParams({action:"' . esc_js( self::ACTION ) . '",nonce:"' . esc_js( $nonce ) . '",scope:"' . ( $network ? 'network' : 'site' ) . '",site_id:"' . esc_js( (string) $site_id ) . '"});fetch("' . esc_js( $ajax_url ) . '",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},body:data.toString()}).then(function(r){return r.json()}).then(function(r){if(!r.success)throw new Error((r.data&&r.data.message)||"No se pudo procesar la imagen.");var d=r.data,total=Math.max(1,Number(d.total)),percent=Math.min(100,Math.round((Number(d.processed)/total)*100));bar.style.width=percent+"%";summary.textContent=d.summary;status.textContent=d.message;if(d.complete){running=false;button.disabled=false;button.textContent="Optimización terminada";return}step()}).catch(function(error){running=false;button.disabled=false;button.textContent="Reintentar";status.textContent=error.message})}button.addEventListener("click",function(){if(running)return;running=true;button.disabled=true;button.textContent="Procesando…";box.style.display="block";step()})});</script>';
	}

	public static function process_next() {
		$network = is_multisite() && 'network' === sanitize_key( wp_unslash( $_POST['scope'] ?? '' ) );
		$site_id = absint( $_POST['site_id'] ?? 0 );
		if ( ! current_user_can( $network ? 'manage_network_options' : 'manage_options' ) || ( ! $network && $site_id !== get_current_blog_id() ) ) wp_send_json_error( array( 'message' => 'No autorizado para este sitio.' ), 403 );
		check_ajax_referer( self::ACTION . ':' . ( $network ? 'network' : $site_id ), 'nonce' );
		$item = self::next_item( $network, $site_id );
		if ( ! $item ) {
			$stats = self::stats( $network, $site_id );
			wp_send_json_success( array( 'complete' => true, 'processed' => $stats['done'] + $stats['failed'], 'total' => $stats['total'], 'summary' => self::summary( $stats ), 'message' => 'No quedan imágenes pendientes.' ) );
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
		wp_send_json_success( array( 'complete' => 0 === $stats['pending'], 'processed' => $stats['done'] + $stats['failed'], 'total' => $stats['total'], 'summary' => self::summary( $stats ), 'message' => $result['message'] ) );
	}

	private static function site_ids( $network, $site_id ) {
		if ( ! $network ) return array( $site_id );
		return array_map( 'intval', wp_list_pluck( get_sites( array( 'number' => 0, 'orderby' => 'blog_id' ) ), 'blog_id' ) );
	}

	private static function next_item( $network, $site_id ) {
		foreach ( self::site_ids( $network, $site_id ) as $target_id ) {
			$switched = get_current_blog_id() !== $target_id;
			if ( $switched ) switch_to_blog( $target_id );
			try {
				$ids = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => array( 'image/jpeg', 'image/png' ), 'posts_per_page' => 1, 'fields' => 'ids', 'meta_query' => array( 'relation' => 'AND', array( 'key' => self::META_DONE, 'compare' => 'NOT EXISTS' ), array( 'key' => self::META_ERROR, 'compare' => 'NOT EXISTS' ) ) ) );
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
			update_post_meta( $attachment_id, self::META_ERROR, 'No se encontró el archivo original.' );
			return array( 'message' => $name . ': no se encontró el archivo original.' );
		}
		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) {
			$message = $editor->get_error_message();
			update_post_meta( $attachment_id, self::META_ERROR, $message );
			return array( 'message' => $name . ': ' . $message );
		}
		$editor->set_quality( 75 );
		$output = dirname( $file ) . '/' . pathinfo( $file, PATHINFO_FILENAME ) . '.webp';
		$saved = $editor->save( $output, 'image/webp' );
		if ( is_wp_error( $saved ) ) {
			$message = $saved->get_error_message();
			update_post_meta( $attachment_id, self::META_ERROR, $message );
			return array( 'message' => $name . ': ' . $message );
		}
		update_post_meta( $attachment_id, self::META_DONE, $saved['path'] );
		delete_post_meta( $attachment_id, self::META_ERROR );
		return array( 'message' => $name . ' optimizada correctamente.' );
	}

	private static function stats( $network, $site_id ) {
		$stats = array( 'pending' => 0, 'done' => 0, 'failed' => 0 );
		foreach ( self::site_ids( $network, $site_id ) as $target_id ) {
			$switched = get_current_blog_id() !== $target_id;
			if ( $switched ) switch_to_blog( $target_id );
			try {
				$base = array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => array( 'image/jpeg', 'image/png' ), 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => false );
				$pending = new \WP_Query( $base + array( 'meta_query' => array( 'relation' => 'AND', array( 'key' => self::META_DONE, 'compare' => 'NOT EXISTS' ), array( 'key' => self::META_ERROR, 'compare' => 'NOT EXISTS' ) ) ) );
				$done = new \WP_Query( $base + array( 'meta_key' => self::META_DONE, 'meta_compare' => 'EXISTS' ) );
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
