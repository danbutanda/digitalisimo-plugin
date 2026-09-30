<?php
defined( 'ABSPATH' ) || exit;

/** Limpieza acotada a páginas Elementor confirmadas y sin contenido de bloques. */
class Digitalisimo_Integrations_Performance_Manager {
	const LOG_OPTION = 'digitalisimo_performance_log';
	/** Corre después de los enqueues normales y antes de imprimir el CSS del head. */
	public static function init() {
		add_action( 'wp_print_styles', array( __CLASS__, 'clean_styles' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'clean_scripts' ), 100 );
		add_action( 'wp', array( __CLASS__, 'clean_emoji_hooks' ), 20 );
		add_filter( 'elementor_pro/custom_fonts/font_display', array( __CLASS__, 'custom_font_display' ), 10, 1 );
		add_filter( 'pre_option_elementor_font_display', array( __CLASS__, 'google_font_display' ), 10, 1 );
		add_action( 'admin_post_digitalisimo_performance_clear_log', array( __CLASS__, 'clear_log' ) );
	}

	private static function enabled( $key ) {
		return (bool) Digitalisimo_Integrations_SEO_Resolver::option( $key );
	}

	/** Reservado para reglas avanzadas: el modo seguro impide ejecutarlas. */
	public static function advanced_allowed() {
		return self::frontend_safe() && ! self::enabled( 'perf_safe_mode' );
	}

	/** API oficial de Elementor Pro; el CSS existente requiere regeneración manual. */
	public static function custom_font_display( $current ) {
		return self::enabled( 'perf_font_swap' ) ? 'swap' : $current;
	}

	/** Elementor lee esta opción para generar URLs/CSS de Google Fonts. */
	public static function google_font_display( $current ) {
		return self::enabled( 'perf_font_swap' ) ? 'swap' : $current;
	}

	/** Nunca cambia editor, preview, administrador, REST, AJAX, Customizer o feed. */
	public static function frontend_safe() {
		if ( is_admin() || wp_doing_ajax() || is_feed() || is_preview() ) return false;
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) return false;
		if ( defined( 'WP_CLI' ) && WP_CLI ) return false;
		if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) return false;
		if ( isset( $_GET['elementor-preview'] ) || isset( $_GET['preview'] ) || isset( $_GET['customize_changeset_uuid'] ) ) return false;
		if ( current_user_can( 'manage_options' ) ) return false;
		return (bool) apply_filters( 'digitalisimo_performance_frontend_safe', true );
	}

	/** Sólo una página construida con Elementor y sin bloques/shortcodes visibles. */
	public static function plain_elementor_page() {
		if ( ! self::frontend_safe() || ! is_singular( 'page' ) || is_home() ) return false;
		if ( 'hello-elementor' !== get_template() ) return false;
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post || 'builder' !== get_post_meta( $post->ID, '_elementor_edit_mode', true ) ) return false;
		if ( has_blocks( $post ) || has_shortcode( $post->post_content, 'embed' ) || false !== strpos( $post->post_content, '[' ) ) return false;
		// Un widget shortcode o de bloques puede introducir dependencias invisibles.
		$data = (string) get_post_meta( $post->ID, '_elementor_data', true );
		if ( false !== strpos( $data, 'shortcode' ) || false !== strpos( $data, 'wp-widget' ) || false !== strpos( $data, 'woocommerce' ) ) return false;
		return (bool) apply_filters( 'digitalisimo_performance_plain_elementor_page', true, $post );
	}

	/** Un dependiente encolado impide retirar su requisito. */
	public static function has_queued_dependent( $registry, $handle ) {
		if ( ! $registry || ! isset( $registry->registered[ $handle ] ) ) return true;
		foreach ( (array) $registry->queue as $queued ) {
			if ( $queued === $handle || ! isset( $registry->registered[ $queued ] ) ) continue;
			if ( self::depends_on( $registry, $queued, $handle, array() ) ) return true;
		}
		return false;
	}

	private static function depends_on( $registry, $candidate, $target, $seen ) {
		if ( isset( $seen[ $candidate ] ) || ! isset( $registry->registered[ $candidate ] ) ) return false;
		$seen[ $candidate ] = true;
		foreach ( (array) $registry->registered[ $candidate ]->deps as $dependency ) {
			if ( $dependency === $target || self::depends_on( $registry, $dependency, $target, $seen ) ) return true;
		}
		return false;
	}

	private static function log( $handle, $reason, $registry ) {
		if ( ! ( defined( 'DIGITALISIMO_PERFORMANCE_DEBUG' ) && DIGITALISIMO_PERFORMANCE_DEBUG ) && ! apply_filters( 'digitalisimo_performance_debug', false ) ) return;
		$registered = $registry->registered[ $handle ] ?? null;
		if ( ! $registered ) return;
		$record = array( 'time' => current_time( 'mysql' ), 'handle' => $handle, 'reason' => $reason, 'url' => is_singular() ? get_permalink( get_queried_object_id() ) : home_url( '/' ), 'dependencies' => $registered->deps, 'module' => 'Digitalisimo SEO Rendimiento' );
		$log = (array) get_option( self::LOG_OPTION, array() );
		$log[] = $record;
		update_option( self::LOG_OPTION, array_slice( $log, -100 ), false );
		do_action( 'digitalisimo_performance_log', $record );
	}

	public static function render_log() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$log = array_reverse( (array) get_option( self::LOG_OPTION, array() ) );
		echo '<h2>Registro opcional</h2><p>Se guarda por sitio, hasta 100 eventos, sólo cuando está activa la constante <code>DIGITALISIMO_PERFORMANCE_DEBUG</code> o el filtro <code>digitalisimo_performance_debug</code>.</p>';
		if ( ! $log ) { echo '<p>Sin eventos registrados.</p>'; return; }
		echo '<table class="widefat striped"><thead><tr><th>Fecha</th><th>Handle</th><th>Razón</th><th>URL</th><th>Dependencias</th></tr></thead><tbody>';
		foreach ( $log as $row ) echo '<tr><td>' . esc_html( $row['time'] ?? '' ) . '</td><td>' . esc_html( $row['handle'] ?? '' ) . '</td><td>' . esc_html( $row['reason'] ?? '' ) . '</td><td>' . esc_html( $row['url'] ?? '' ) . '</td><td>' . esc_html( implode( ', ', (array) ( $row['dependencies'] ?? array() ) ) ) . '</td></tr>';
		echo '</tbody></table><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="digitalisimo_performance_clear_log">';
		wp_nonce_field( 'digitalisimo_performance_clear_log' );
		submit_button( 'Vaciar registro', 'secondary' );
		echo '</form>';
	}

	public static function clear_log() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'No autorizado.' );
		check_admin_referer( 'digitalisimo_performance_clear_log' );
		delete_option( self::LOG_OPTION );
		wp_safe_redirect( admin_url( 'admin.php?page=digitalisimo-performance&section=debug' ) );
		exit;
	}

	public static function clean_styles() {
		$styles = wp_styles();
		if ( self::enabled( 'perf_gutenberg' ) && self::plain_elementor_page() ) {
			// global-styles y classic-theme-styles pueden contener reglas del tema;
			// se diagnostican, pero no se retiran por defecto.
			foreach ( array( 'wp-block-library', 'wp-block-library-theme' ) as $handle ) {
				if ( ! wp_style_is( $handle, 'enqueued' ) || self::has_queued_dependent( $styles, $handle ) ) continue;
				wp_dequeue_style( $handle );
				self::log( $handle, 'Página Elementor sin bloques ni dependientes encolados', $styles );
			}
		}
		if ( self::frontend_safe() && self::enabled( 'perf_dashicons' ) && ! is_user_logged_in() && ! is_admin_bar_showing() && wp_style_is( 'dashicons', 'enqueued' ) && ! self::has_queued_dependent( $styles, 'dashicons' ) ) {
			wp_dequeue_style( 'dashicons' );
			self::log( 'dashicons', 'Visitante sin barra de administración ni dependientes', $styles );
		}
	}

	public static function clean_scripts() {
		if ( ! self::frontend_safe() ) return;
		$scripts = wp_scripts();
		if ( self::enabled( 'perf_embeds' ) && self::plain_elementor_page() && wp_script_is( 'wp-embed', 'enqueued' ) && ! self::has_queued_dependent( $scripts, 'wp-embed' ) ) {
			wp_dequeue_script( 'wp-embed' );
			self::log( 'wp-embed', 'Página Elementor sin bloques, shortcode embed ni dependientes', $scripts );
		}
	}

	public static function clean_emoji_hooks() {
		if ( ! self::enabled( 'perf_emojis' ) || ! self::frontend_safe() ) return;
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	}
}
