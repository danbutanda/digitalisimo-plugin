<?php
defined( 'ABSPATH' ) || exit;

/**
 * Reparación de la metadata de adjuntos.
 *
 * Entre SEO 1.0.2xx (`0f2cc10`, 2026-10-01) y 1.0.246, un filtro enganchado como acción hizo que
 * WordPress borrara `_wp_attachment_metadata` en cada subida: los archivos quedaron sin tamaños,
 * ancho, alto ni duración. Esta tarea, que inicia el administrador, vuelve a generarla en cola de
 * WP-cron, pocos archivos por lote, sólo en el sitio en curso y sólo para adjuntos cuyo archivo
 * existe y no tienen metadata. No toca los que ya la tienen.
 */
class Digitalisimo_Integrations_Media_Repair {
	const JOB   = 'digitalisimo_seo_media_repair';
	const HOOK  = 'digitalisimo_seo_media_repair_batch';
	const BATCH = 5;

	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'run_batch' ) );
		add_action( 'admin_post_digitalisimo_seo_media_repair', array( __CLASS__, 'handle_start' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
	}

	/** Tipos con metadata propia: imágenes, vídeo, audio y PDF (miniaturas). */
	private static function mime_sql() { return "( p.post_mime_type LIKE 'image/%' OR p.post_mime_type LIKE 'video/%' OR p.post_mime_type LIKE 'audio/%' OR p.post_mime_type = 'application/pdf' )"; }

	/** Adjuntos sin metadata a partir de un ID. */
	public static function missing( $after = 0, $limit = self::BATCH ) {
		global $wpdb;
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_wp_attachment_metadata' WHERE p.post_type = 'attachment' AND p.ID > %d AND m.meta_id IS NULL AND " . self::mime_sql() . ' ORDER BY p.ID ASC LIMIT %d', (int) $after, (int) $limit ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function count_missing() {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_wp_attachment_metadata' WHERE p.post_type = 'attachment' AND m.meta_id IS NULL AND " . self::mime_sql() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function job() { $job = get_option( self::JOB ); return is_array( $job ) ? $job : array(); }

	public static function start() {
		update_option( self::JOB, array( 'status' => 'running', 'cursor' => 0, 'done' => 0, 'failed' => array(), 'total' => self::count_missing(), 'started' => time() ), false );
		wp_clear_scheduled_hook( self::HOOK );
		wp_schedule_single_event( time(), self::HOOK );
	}

	public static function handle_start() {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'upload_files' ) || ! check_admin_referer( 'digitalisimo_seo_media_repair' ) ) wp_die( 'No autorizado.' );
		self::start();
		wp_safe_redirect( wp_get_referer() ?: admin_url( 'upload.php' ) );
		exit;
	}

	/** Un lote: genera la metadata de cada adjunto sin ella; uno sin archivo se anota y se salta. */
	public static function run_batch() {
		$job = self::job();
		if ( 'running' !== ( $job['status'] ?? '' ) ) return;
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		$started = time();
		foreach ( self::missing( (int) $job['cursor'] ) as $id ) {
			$job['cursor'] = $id;
			$file = get_attached_file( $id );
			$meta = $file && file_exists( $file ) ? wp_generate_attachment_metadata( $id, $file ) : array();
			if ( is_array( $meta ) && $meta ) { wp_update_attachment_metadata( $id, $meta ); $job['done']++; }
			elseif ( count( $job['failed'] ) < 200 ) $job['failed'][] = $id;
			if ( time() - $started > 20 ) break;
		}
		if ( ! self::missing( (int) $job['cursor'], 1 ) ) { $job['status'] = 'done'; $job['finished'] = time(); }
		update_option( self::JOB, $job, false );
		if ( 'running' === $job['status'] ) wp_schedule_single_event( time() + 30, self::HOOK );
	}

	/** En Medios y en SEO: cuántos adjuntos faltan, el progreso y el botón. */
	public static function notice() {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'upload_files' ) ) return;
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$page   = sanitize_key( $_GET['page'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! ( $screen && 'upload' === $screen->id ) && 0 !== strpos( $page, 'digitalisimo-seo' ) ) return;
		$job = self::job();
		if ( 'running' === ( $job['status'] ?? '' ) ) {
			echo '<div class="notice notice-info"><p>' . esc_html( sprintf( 'Reparando la metadata de los archivos: %1$d de %2$d. Sigue en segundo plano; puedes cerrar esta página.', (int) $job['done'], (int) $job['total'] ) ) . '</p></div>';
			return;
		}
		$missing = self::count_missing();
		if ( 'done' === ( $job['status'] ?? '' ) && (int) ( $job['finished'] ?? 0 ) > time() - DAY_IN_SECONDS ) {
			$failed = count( $job['failed'] ?? array() );
			echo '<div class="notice notice-success"><p>' . esc_html( sprintf( 'Metadata reparada en %d archivos.', (int) $job['done'] ) . ( $failed ? sprintf( ' %d no se pudieron reparar porque su archivo no está en el servidor.', $failed ) : '' ) ) . '</p></div>';
			if ( $missing <= $failed ) return;
		}
		if ( ! $missing ) return;
		echo '<div class="notice notice-warning"><p><strong>' . esc_html( sprintf( '%d archivos de la biblioteca no tienen metadata', $missing ) ) . '</strong> (tamaños, ancho, alto o duración). Sin ella WordPress no puede servir versiones adaptadas a cada pantalla. Si se subieron entre el 1 de octubre de 2026 y DIGITALÍSIMO SEO 1.0.246, la borró un fallo ya corregido.</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="digitalisimo_seo_media_repair">';
		wp_nonce_field( 'digitalisimo_seo_media_repair' );
		echo '<p><button class="button button-primary">Reparar en segundo plano</button> <span class="description">' . esc_html( self::BATCH ) . ' archivos cada 30 segundos, sólo en este sitio. No cambia los archivos que ya tienen metadata.</span></p></form></div>';
	}
}
