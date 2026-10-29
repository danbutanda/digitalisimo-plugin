<?php
namespace Digitalisimo\Tools;
defined( 'ABSPATH' ) || exit;

/**
 * Optimizar medios por lotes, y la insignia «Optimizada» de la Biblioteca.
 *
 * - JPEG y PNG pasan a WebP con calidad 75 y sus tamaños, como siempre.
 * - Un GIF estático pasa a WebP como un PNG. Un GIF animado pasa a WebP animado con todos sus fotogramas si el servidor
 *   tiene Imagick con WebP (o un plugin lo resuelve por el filtro `digitalisimo_tools_animated_webp`). Ese WebP no lleva
 *   tamaños reducidos: el servidor los crearía con un solo fotograma y, al pedir un tamaño, la imagen se quedaría quieta.
 *   Sin tamaños, WordPress sirve el archivo entero en todos y no imprime `srcset`. Si no se puede o no ahorra, el GIF se
 *   conserva y queda anotado como revisado para no volver a intentarlo.
 * - Un SVG se minimiza sin cambiar el dibujo (Media_SVG).
 * El archivo original se conserva en el disco para no romper enlaces antiguos.
 */
final class Media_WebP {
	const ACTION = 'digitalisimo_tools_webp_batch';
	const META_DONE = '_digitalisimo_tools_webp';
	const META_ERROR = '_digitalisimo_tools_webp_error';
	/** GIF y SVG que ya pasaron por el lote (convertidos, minimizados o conservados): no vuelven a estar pendientes. */
	const META_SEEN = '_digitalisimo_tools_media_seen';
	/** Tipos que siempre terminan en WebP: un JPG/PNG marcado por una versión anterior sin convertirse vuelve a procesarse. */
	const TO_WEBP = array( 'image/jpeg', 'image/png' );
	/** Tipos que pueden conservar su formato (GIF animado sin WebP animado, SVG ya mínimo). */
	const KEEPABLE = array( 'image/gif', 'image/svg+xml' );
	/** Tipos que entran en el lote. */
	const SOURCES = array( 'image/jpeg', 'image/png', 'image/gif', 'image/svg+xml' );
	const QUALITY = 75;
	/** Estado de cada tipo optimizado, para Medios. */
	const STATES = array( 'webp' => 'Optimizada · WebP', 'svg' => 'Optimizado · SVG', 'gif' => 'Revisado · GIF animado' );

	public static function init() {
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'process_next' ) );
		add_filter( 'display_media_states', array( __CLASS__, 'media_states' ), 10, 2 );
		add_filter( 'wp_prepare_attachment_for_js', array( __CLASS__, 'attachment_data' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'library_assets' ) );
	}

	/** webp, svg o gif si el adjunto ya pasó por el lote y su archivo es el que dejó; si no, ''. */
	public static function optimized_kind( $attachment_id ) {
		$file = get_attached_file( $attachment_id );
		if ( ! $file ) return '';
		$mime = get_post_mime_type( $attachment_id );
		$ext  = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
		$optimized_file = get_post_meta( $attachment_id, self::META_DONE, true );
		if ( 'image/webp' === get_post_mime_type( $attachment_id ) && 'webp' === $ext ) return $optimized_file && wp_normalize_path( $file ) === wp_normalize_path( $optimized_file ) ? 'webp' : '';
		$seen = get_post_meta( $attachment_id, self::META_SEEN, true );
		if ( ! $seen || wp_normalize_path( $file ) !== wp_normalize_path( $seen ) ) return '';
		if ( 'image/svg+xml' === $mime && 'svg' === $ext ) return 'svg';
		if ( 'image/gif' === $mime && 'gif' === $ext ) return 'gif';
		return '';
	}

	private static function is_optimized( $attachment_id ) { return in_array( self::optimized_kind( $attachment_id ), array( 'webp', 'svg' ), true ); }

	public static function media_states( $states, $attachment ) {
		$kind = self::optimized_kind( $attachment->ID );
		if ( $kind ) $states['digitalisimo_tools_webp'] = self::STATES[ $kind ];
		return $states;
	}

	public static function attachment_data( $response, $attachment ) {
		$kind = self::optimized_kind( $attachment->ID );
		$response['digitalisimoOptimized']      = self::is_optimized( $attachment->ID );
		$response['digitalisimoOptimizedLabel'] = $kind ? self::STATES[ $kind ] : '';
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
		echo '<h2>Optimizar medios</h2><p>Convierte los adjuntos JPG, PNG y GIF a WebP con calidad 75 y actualiza la Biblioteca de Medios y sus tamaños. Un GIF animado pasa a WebP animado con todos sus fotogramas si el servidor tiene Imagick con WebP; si no, o si no ahorra, se conserva. Los SVG se minimizan sin cambiar el dibujo. Los archivos anteriores se conservan para no romper enlaces antiguos.</p>';
		echo '<p class="description">' . esc_html( $network ? 'Alcance: todos los sitios de la red.' : 'Alcance: biblioteca de este sitio.' ) . ( self::can_animate() ? ' Este servidor puede crear WebP animado.' : ' Este servidor no puede crear WebP animado: los GIF animados se conservan.' ) . '</p>';
		echo '<p id="digitalisimo-webp-summary">' . esc_html( self::summary( $stats ) ) . '</p><p><button type="button" id="digitalisimo-webp-start" class="button button-primary"' . disabled( 0 === ( $stats['pending'] + $stats['failed'] ), true, false ) . '>' . esc_html( $stats['pending'] ? 'Iniciar optimización' : 'Reintentar errores' ) . '</button></p><div id="digitalisimo-webp-progress" style="display:none;max-width:640px"><div class="digitalisimo-progress"><div id="digitalisimo-webp-bar" class="digitalisimo-progress__bar"></div></div><p id="digitalisimo-webp-status" aria-live="polite"></p></div>';
		echo '<script>document.addEventListener("DOMContentLoaded",function(){var button=document.getElementById("digitalisimo-webp-start"),box=document.getElementById("digitalisimo-webp-progress"),bar=document.getElementById("digitalisimo-webp-bar"),status=document.getElementById("digitalisimo-webp-status"),summary=document.getElementById("digitalisimo-webp-summary"),running=false,first=true;function step(){if(!running)return;var data=new URLSearchParams({action:"' . esc_js( self::ACTION ) . '",nonce:"' . esc_js( $nonce ) . '",scope:"' . ( $network ? 'network' : 'site' ) . '",site_id:"' . esc_js( (string) $site_id ) . '",retry:first?"1":"0"});first=false;fetch("' . esc_js( $ajax_url ) . '",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},body:data.toString()}).then(function(r){return r.json()}).then(function(r){if(!r.success)throw new Error((r.data&&r.data.message)||"No se pudo procesar el archivo.");var d=r.data,total=Math.max(1,Number(d.total)),percent=Math.min(100,Math.round((Number(d.processed)/total)*100));bar.style.width=percent+"%";summary.textContent=d.summary;status.textContent=d.message;if(d.complete){running=false;button.disabled=Number(d.failed)===0;button.textContent=Number(d.failed)>0?"Reintentar errores":"Optimización terminada";return}step()}).catch(function(error){running=false;button.disabled=false;button.textContent="Reintentar";status.textContent=error.message})}button.addEventListener("click",function(){if(running)return;running=true;first=true;button.disabled=true;button.textContent="Procesando…";box.style.display="block";step()})});</script>';
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
			wp_send_json_success( array( 'complete' => true, 'processed' => $stats['done'] + $stats['failed'], 'failed' => $stats['failed'], 'total' => $stats['total'], 'summary' => self::summary( $stats ), 'message' => 'No quedan archivos pendientes.' ) );
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
				$ids = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => self::SOURCES, 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => self::META_ERROR ) );
				foreach ( $ids as $id ) delete_post_meta( $id, self::META_ERROR );
			} finally {
				if ( $switched ) restore_current_blog();
			}
		}
	}

	/**
	 * Pendientes, en dos consultas: los JPG/PNG sin error (lo convertido ya es WebP, y un marcado sin convertir por una
	 * versión anterior vuelve a procesarse) y los GIF/SVG sin error que aún no pasaron por el lote.
	 */
	private static function pending_queries() {
		return array(
			array( 'post_mime_type' => self::TO_WEBP, 'meta_query' => array( array( 'key' => self::META_ERROR, 'compare' => 'NOT EXISTS' ) ) ),
			array( 'post_mime_type' => self::KEEPABLE, 'meta_query' => array( array( 'key' => self::META_ERROR, 'compare' => 'NOT EXISTS' ), array( 'key' => self::META_SEEN, 'compare' => 'NOT EXISTS' ) ) ),
		);
	}

	private static function next_item( $network, $site_id ) {
		foreach ( self::site_ids( $network, $site_id ) as $target_id ) {
			$switched = get_current_blog_id() !== $target_id;
			if ( $switched ) switch_to_blog( $target_id );
			try {
				foreach ( self::pending_queries() as $query ) {
					$ids = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 1, 'fields' => 'ids' ) + $query );
					if ( $ids ) return array( 'site_id' => $target_id, 'attachment_id' => (int) $ids[0] );
				}
			} finally {
				if ( $switched ) restore_current_blog();
			}
		}
		return null;
	}

	/** Procesa un adjunto según su tipo. Devuelve array( 'message' => … ). */
	public static function convert( $attachment_id, $file ) {
		$name = $file ? wp_basename( $file ) : 'archivo no disponible';
		if ( ! $file || ! file_exists( $file ) ) {
			return self::failure( $attachment_id, $name, 'No se encontró el archivo original.' );
		}
		$mime = (string) get_post_mime_type( $attachment_id );
		$ext  = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
		if ( 'image/svg+xml' === $mime || 'svg' === $ext ) return self::optimize_svg( $attachment_id, $file, $name );
		if ( 'webp' === $ext ) return self::failure( $attachment_id, $name, 'El archivo ya es WebP, pero WordPress lo registra como JPG/PNG; no se modificó.' );
		if ( ( 'image/gif' === $mime || 'gif' === $ext ) && self::gif_frames( $file ) > 1 ) return self::convert_animated( $attachment_id, $file, $name );
		return self::convert_with_editor( $attachment_id, $file, $name );
	}

	private static function optimize_svg( $attachment_id, $file, $name ) {
		$result = Media_SVG::optimize_attachment( $attachment_id, $file );
		if ( 'failed' === $result['status'] ) return self::failure( $attachment_id, $name, $result['message'] );
		$current = 'done' === $result['status'] ? $result['path'] : $file;
		update_post_meta( $attachment_id, self::META_SEEN, $current );
		if ( 'done' === $result['status'] ) update_post_meta( $attachment_id, self::META_DONE, $current );
		delete_post_meta( $attachment_id, self::META_ERROR );
		return array( 'message' => $result['message'] );
	}

	/** JPEG, PNG y GIF estático: WebP con sus tamaños, por el editor de imágenes de WordPress. */
	private static function convert_with_editor( $attachment_id, $file, $name ) {
		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) return self::failure( $attachment_id, $name, $editor->get_error_message() );
		if ( ! $editor->supports_mime_type( 'image/webp' ) ) return self::failure( $attachment_id, $name, 'El servidor no puede crear WebP.' );
		$editor->set_quality( self::QUALITY );
		$output = self::output_path( $file );
		$saved = $editor->save( $output, 'image/webp' );
		if ( is_wp_error( $saved ) ) return self::failure( $attachment_id, $name, $saved->get_error_message() );
		if ( empty( $saved['path'] ) || ! is_file( $saved['path'] ) || ! filesize( $saved['path'] ) || 'image/webp' !== ( $saved['mime-type'] ?? '' ) ) {
			if ( is_file( $output ) ) wp_delete_file( $output );
			return self::failure( $attachment_id, $name, 'El archivo WebP generado no es válido.' );
		}
		return self::apply( $attachment_id, $file, $name, $saved, null );
	}

	/** GIF animado: WebP animado entero, sin tamaños reducidos; si no se puede o no ahorra, se conserva. */
	private static function convert_animated( $attachment_id, $file, $name ) {
		$output  = self::output_path( $file );
		$written = apply_filters( 'digitalisimo_tools_animated_webp', null, $file, $output, self::QUALITY );
		if ( null === $written ) $written = self::animated_webp( $file, $output );
		if ( true !== $written || ! is_file( $output ) || ! self::is_animated_webp( $output ) || filesize( $output ) >= filesize( $file ) ) {
			if ( is_file( $output ) ) wp_delete_file( $output );
			update_post_meta( $attachment_id, self::META_SEEN, $file );
			delete_post_meta( $attachment_id, self::META_ERROR );
			return array( 'message' => $name . ': GIF animado; ' . ( true === $written && is_file( $file ) ? 'el WebP animado no ahorra o no conserva la animación, se conserva el GIF.' : 'este servidor no puede crear WebP animado (hace falta Imagick con WebP), se conserva.' ) );
		}
		$size = @getimagesize( $output ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! $size ) $size = @getimagesize( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$metadata = array( 'width' => (int) ( $size[0] ?? 0 ), 'height' => (int) ( $size[1] ?? 0 ), 'file' => _wp_relative_upload_path( $output ), 'filesize' => (int) filesize( $output ), 'sizes' => array(), 'image_meta' => array() );
		return self::apply( $attachment_id, $file, $name, array( 'path' => $output ), $metadata );
	}

	/** ¿Puede este servidor escribir WebP animado? Imagick con el formato WebP, o un plugin por el filtro. */
	public static function can_animate() {
		if ( has_filter( 'digitalisimo_tools_animated_webp' ) ) return true;
		if ( ! class_exists( '\Imagick' ) ) return false;
		try { return in_array( 'WEBP', (array) \Imagick::queryFormats( 'WEBP' ), true ); } catch ( \Throwable $e ) { return false; }
	}

	/** Fotogramas de un GIF: Imagick si existe; si no, las extensiones de control gráfico del archivo. */
	public static function gif_frames( $file ) {
		if ( class_exists( '\Imagick' ) ) {
			try {
				$image = new \Imagick( $file );
				$count = (int) $image->getNumberImages();
				$image->clear();
				if ( $count > 0 ) return $count;
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			}
		}
		return max( 1, substr_count( (string) file_get_contents( $file ), "\x21\xF9\x04" ) );
	}

	/** Un WebP con el bloque ANIM: contenedor RIFF/WEBP con la animación declarada en su cabecera extendida. */
	public static function is_animated_webp( $path ) {
		$head = (string) file_get_contents( $path, false, null, 0, 128 );
		return 'RIFF' === substr( $head, 0, 4 ) && 'WEBP' === substr( $head, 8, 4 ) && false !== strpos( $head, 'ANIM' );
	}

	private static function animated_webp( $file, $output ) {
		if ( ! self::can_animate() || ! class_exists( '\Imagick' ) ) return false;
		try {
			$source = new \Imagick( $file );
			$frames = $source->coalesceImages();
			$loops  = (int) $source->getImageIterations();
			foreach ( $frames as $frame ) { $frame->setImageFormat( 'webp' ); $frame->setImageCompressionQuality( self::QUALITY ); }
			$frames->setImageIterations( $loops );
			$ok = (bool) $frames->writeImages( $output, true );
			$frames->clear();
			$source->clear();
			return $ok;
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	private static function output_path( $file ) {
		$directory = dirname( $file );
		return $directory . '/' . wp_unique_filename( $directory, pathinfo( $file, PATHINFO_FILENAME ) . '.webp' );
	}

	/**
	 * Aplica el WebP al adjunto: tipo, archivo principal y metadatos (los dados, o los que genera WordPress con sus
	 * tamaños). Cualquier fallo deshace todo y deja el adjunto como estaba.
	 */
	private static function apply( $attachment_id, $file, $name, array $saved, $given ) {
		$post = get_post( $attachment_id );
		$previous_metadata = wp_get_attachment_metadata( $attachment_id );
		$metadata = is_array( $given ) ? $given : array();
		$updated = false;
		try {
			$change = array( 'ID' => $attachment_id, 'post_mime_type' => 'image/webp' );
			if ( $post && $post->post_title === $name ) $change['post_title'] = wp_basename( $saved['path'] );
			$result = wp_update_post( $change, true );
			if ( is_wp_error( $result ) || ! $result ) throw new \RuntimeException( 'No se pudo actualizar el adjunto.' );
			$updated = true;
			update_attached_file( $attachment_id, $saved['path'] );
			if ( get_attached_file( $attachment_id ) !== $saved['path'] ) throw new \RuntimeException( 'No se pudo cambiar el archivo principal del adjunto.' );
			if ( null === $given ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
				add_filter( 'wp_editor_set_quality', array( __CLASS__, 'webp_quality' ), 10, 2 );
				try {
					$metadata = wp_generate_attachment_metadata( $attachment_id, $saved['path'] );
				} finally {
					remove_filter( 'wp_editor_set_quality', array( __CLASS__, 'webp_quality' ), 10 );
				}
				if ( ! is_array( $metadata ) || empty( $metadata['file'] ) || empty( $metadata['width'] ) || empty( $metadata['height'] ) ) throw new \RuntimeException( 'No se pudieron crear los tamaños de la imagen.' );
				foreach ( (array) ( $metadata['sizes'] ?? array() ) as $size ) if ( 'webp' !== strtolower( pathinfo( $size['file'] ?? '', PATHINFO_EXTENSION ) ) ) throw new \RuntimeException( 'Algún tamaño no se generó como WebP.' );
			} elseif ( empty( $metadata['width'] ) || empty( $metadata['height'] ) ) {
				throw new \RuntimeException( 'No se pudieron leer las dimensiones del WebP animado.' );
			}
			wp_update_attachment_metadata( $attachment_id, $metadata );
			$stored_metadata = wp_get_attachment_metadata( $attachment_id );
			$active_file = get_attached_file( $attachment_id );
			if ( ! is_array( $stored_metadata ) || ( $stored_metadata['file'] ?? '' ) !== $metadata['file'] || 'image/webp' !== get_post_mime_type( $attachment_id ) || ! $active_file || 'webp' !== strtolower( pathinfo( $active_file, PATHINFO_EXTENSION ) ) ) throw new \RuntimeException( 'La biblioteca no quedó apuntando al WebP.' );
		} catch ( \Throwable $error ) {
			$generated_main = get_attached_file( $attachment_id );
			if ( $updated ) {
				wp_update_post( array( 'ID' => $attachment_id, 'post_mime_type' => $post->post_mime_type, 'post_title' => $post->post_title ) );
				update_attached_file( $attachment_id, $file );
				if ( is_array( $previous_metadata ) ) wp_update_attachment_metadata( $attachment_id, $previous_metadata );
				else delete_post_meta( $attachment_id, '_wp_attachment_metadata' );
			}
			self::delete_generated( $saved['path'], $metadata );
			if ( $generated_main && $generated_main !== $saved['path'] && $generated_main !== $file && dirname( $generated_main ) === dirname( $saved['path'] ) && 'webp' === strtolower( pathinfo( $generated_main, PATHINFO_EXTENSION ) ) ) wp_delete_file( $generated_main );
			return self::failure( $attachment_id, $name, $error->getMessage() );
		}
		update_post_meta( $attachment_id, self::META_DONE, get_attached_file( $attachment_id ) );
		if ( is_array( $given ) ) update_post_meta( $attachment_id, self::META_SEEN, get_attached_file( $attachment_id ) );
		delete_post_meta( $attachment_id, self::META_ERROR );
		return array( 'message' => $name . ' → ' . wp_basename( get_attached_file( $attachment_id ) ) . ( is_array( $given ) ? ' (WebP animado, ' . size_format( max( 0, (int) filesize( $file ) - (int) $metadata['filesize'] ) ) . ' menos)' : '' ) . ' optimizada y aplicada en la biblioteca.' );
	}

	public static function webp_quality( $quality, $mime_type ) { return 'image/webp' === $mime_type ? self::QUALITY : $quality; }

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
				$base = array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => false );
				foreach ( self::pending_queries() as $query ) { $pending = new \WP_Query( $base + $query ); $stats['pending'] += (int) $pending->found_posts; }
				$done = new \WP_Query( array_merge( $base, array( 'post_mime_type' => 'image/webp', 'meta_key' => self::META_DONE, 'meta_compare' => 'EXISTS' ) ) );
				$seen = new \WP_Query( $base + array( 'post_mime_type' => self::KEEPABLE, 'meta_key' => self::META_SEEN, 'meta_compare' => 'EXISTS' ) );
				$failed = new \WP_Query( $base + array( 'post_mime_type' => self::SOURCES, 'meta_key' => self::META_ERROR, 'meta_compare' => 'EXISTS' ) );
				$stats['done'] += (int) $done->found_posts + (int) $seen->found_posts; $stats['failed'] += (int) $failed->found_posts;
			} finally {
				if ( $switched ) restore_current_blog();
			}
		}
		$stats['total'] = $stats['pending'] + $stats['done'] + $stats['failed'];
		return $stats;
	}

	private static function summary( $stats ) { return sprintf( 'Total: %d · Procesados: %d · Pendientes: %d · Con error: %d', $stats['total'], $stats['done'], $stats['pending'], $stats['failed'] ); }
}
