<?php
defined( 'ABSPATH' ) || exit;

/**
 * Páginas que no deben estar en Google aunque nadie las marcó: las técnicas de WooCommerce (carrito, pago, pedido
 * recibido, mi cuenta, seguimiento) y las que muestran un shortcode sin resolver, casi siempre de un plugin que ya no
 * está (`[wpuf_account]` en una página «Cuenta» que quedó publicada). Salen del sitemap y llevan noindex.
 *
 * La detección nunca corre al servir una página: se hace al guardar el contenido y, para lo ya publicado, en segundo
 * plano por lotes (WP-Cron), y queda en la meta `digitalisimo_seo_auto_noindex` con sus motivos. El front sólo lee esa
 * meta del contenido consultado (ya en caché con el resto de sus metas).
 *
 * Un shortcode está sin resolver cuando su etiqueta no está en `$shortcode_tags`. Como hay plugins que sólo registran
 * los suyos fuera del escritorio, cuenta como registrada la que exista en la petición en curso o en la última pasada de
 * WP-Cron (`digitalisimo_seo_known_shortcodes`), que se vuelve a tomar cada vez que cambian los plugins o el tema.
 *
 * Lo que el dueño decide manda: la casilla «Mantener indexable» del contenido (meta `digitalisimo_seo_index_lock`) gana
 * a la detección, que entonces sólo avisa. Apagar «Páginas técnicas y shortcodes sin resolver» deja todo como antes.
 */
class Digitalisimo_Integrations_Index_Hygiene {
	const META    = 'digitalisimo_seo_auto_noindex';
	const LOCK    = 'digitalisimo_seo_index_lock';
	const KNOWN   = 'digitalisimo_seo_known_shortcodes';
	const STATE   = 'digitalisimo_seo_hygiene_state';
	const CRON    = 'digitalisimo_seo_hygiene_scan';
	/** Sube cuando cambian las reglas: obliga a repasar lo publicado. */
	const VERSION = 1;
	const BATCH   = 100;
	/** Shortcodes de WooCommerce que hacen técnica a una página aunque no sea la asignada en sus ajustes. */
	const WOO_SHORTCODES = array( 'woocommerce_cart', 'woocommerce_checkout', 'woocommerce_my_account', 'woocommerce_order_tracking' );
	const WOO_BLOCKS     = array( 'woocommerce/cart', 'woocommerce/checkout', 'woocommerce/classic-shortcode' );
	/** Del núcleo, aunque alguno no figure en $shortcode_tags en todas las peticiones. */
	const CORE_SHORTCODES = array( 'embed', 'caption', 'wp_caption', 'gallery', 'audio', 'video', 'playlist' );

	public static function init() {
		add_action( 'save_post', array( __CLASS__, 'on_save' ), 30, 2 );
		add_action( self::CRON, array( __CLASS__, 'run_batch' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_start' ), 20 );
		add_action( 'activated_plugin', array( __CLASS__, 'request_rescan' ), 10, 1 );
		add_action( 'deactivated_plugin', array( __CLASS__, 'request_rescan' ), 10, 1 );
		add_action( 'switch_theme', array( __CLASS__, 'request_rescan' ), 10, 1 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'request_rescan' ), 10, 1 );
		add_action( 'admin_post_digitalisimo_seo_index_lock', array( __CLASS__, 'handle_lock' ) );
	}

	public static function enabled() { return (bool) Digitalisimo_Integrations_SEO_Resolver::option( 'seo_noindex_technical', 1 ); }

	/* ------------------------------------------------------------------ */
	/* Reglas puras.                                                        */
	/* ------------------------------------------------------------------ */

	/**
	 * Etiquetas de shortcode que aparecen en un texto, sin las escapadas ([[tag]]), las de cierre, las que están dentro
	 * de <code>, <pre> o comentarios HTML (los delimitadores de bloques) ni las que no parecen una etiqueta ([1], [x]).
	 *
	 * @return string[]
	 */
	public static function shortcode_tags_in( $text ) {
		$text = (string) $text;
		if ( false === strpos( $text, '[' ) ) return array();
		$text = preg_replace( '/<!--.*?-->/s', ' ', $text );
		$text = preg_replace( '#<(code|pre|script|style)\b[^>]*>.*?</\1>#is', ' ', $text );
		$text = preg_replace( '/\[\[.*?\]\]/s', ' ', $text );
		if ( ! preg_match_all( '/\[([A-Za-z][A-Za-z0-9_-]+)(?=[\s\]\/])/', $text, $m ) ) return array();
		return array_values( array_unique( $m[1] ) );
	}

	/** Etiquetas sin resolver: las que no están entre las registradas (pura). */
	public static function unresolved( array $tags, array $registered ) {
		$known = array_flip( array_merge( $registered, self::CORE_SHORTCODES ) );
		return array_values( array_filter( $tags, function( $tag ) use ( $known ) { return ! isset( $known[ $tag ] ); } ) );
	}

	/** Bloques de WooCommerce técnicos presentes en un contenido de bloques (pura). */
	public static function woo_blocks_in( $text ) {
		$found = array();
		foreach ( self::WOO_BLOCKS as $block ) if ( false !== strpos( (string) $text, '<!-- wp:' . $block . ' ' ) || false !== strpos( (string) $text, '<!-- wp:' . $block . ' -->' ) || false !== strpos( (string) $text, '<!-- wp:' . $block . '/-->' ) ) $found[] = $block;
		return $found;
	}

	/**
	 * Motivos para sacar del índice un contenido (pura). $role es 'cart', 'checkout', 'myaccount' si es la página que
	 * WooCommerce tiene asignada; $registered, las etiquetas registradas, o null si todavía no se sabe (sin la pasada de
	 * WP-Cron no se juzga ningún shortcode).
	 *
	 * @return string[] Como 'woo:checkout', 'woo:woocommerce_checkout', 'shortcode:wpuf_account'.
	 */
	public static function reasons( $text, $role = '', $registered = null ) {
		$reasons = array();
		if ( '' !== (string) $role ) $reasons[] = 'woo:' . $role;
		$tags = self::shortcode_tags_in( $text );
		foreach ( array_intersect( $tags, self::WOO_SHORTCODES ) as $tag ) $reasons[] = 'woo:' . $tag;
		foreach ( self::woo_blocks_in( $text ) as $block ) $reasons[] = 'woo:' . $block;
		if ( is_array( $registered ) ) foreach ( self::unresolved( $tags, $registered ) as $tag ) $reasons[] = 'shortcode:' . $tag;
		return array_values( array_unique( $reasons ) );
	}

	/** Texto legible de un motivo. */
	public static function label( $reason ) {
		list( $kind, $value ) = array_pad( explode( ':', (string) $reason, 2 ), 2, '' );
		$roles = array( 'cart' => 'Carrito de WooCommerce', 'checkout' => 'Pago de WooCommerce (incluye «pedido recibido»)', 'myaccount' => 'Mi cuenta de WooCommerce' );
		if ( 'woo' === $kind ) return $roles[ $value ] ?? 'Proceso de WooCommerce (' . $value . ')';
		if ( 'shortcode' === $kind ) return 'Shortcode sin resolver [' . $value . ']';
		return $reason;
	}

	/* ------------------------------------------------------------------ */
	/* Lectura en el front y en el sitemap.                                 */
	/* ------------------------------------------------------------------ */

	/** ¿Debe llevar noindex este contenido por la detección? Respeta «Mantener indexable». */
	public static function auto_noindex( $post_id ) {
		if ( ! $post_id || ! self::enabled() ) return false;
		$reasons = get_post_meta( (int) $post_id, self::META, true );
		return is_array( $reasons ) && $reasons && '1' !== get_post_meta( (int) $post_id, self::LOCK, true );
	}

	/** Cláusula meta_query del sitemap: fuera lo detectado, salvo lo que el dueño mantiene indexable. */
	public static function sitemap_clause() {
		if ( ! self::enabled() ) return array();
		return array( 'relation' => 'OR', array( 'key' => self::META, 'compare' => 'NOT EXISTS' ), array( 'key' => self::LOCK, 'value' => '1' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Detección.                                                           */
	/* ------------------------------------------------------------------ */

	/** Tipos que se revisan: los públicos con URL, sin adjuntos ni plantillas. */
	public static function types() {
		$types = get_post_types( array( 'public' => true ) );
		return array_values( array_diff( $types, array( 'attachment', 'elementor_library', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation' ) ) );
	}

	/** Texto que pinta el contenido: el del editor y, si se hizo con Elementor/Elements, el de sus widgets. */
	private static function content_text( $post ) {
		$text = (string) $post->post_content;
		$data = get_post_meta( $post->ID, '_elementor_data', true );
		if ( is_string( $data ) && '' !== $data && 'builder' === get_post_meta( $post->ID, '_elementor_edit_mode', true ) ) {
			$decoded = json_decode( $data, true );
			if ( is_array( $decoded ) ) array_walk_recursive( $decoded, function( $value ) use ( &$text ) { if ( is_string( $value ) && false !== strpos( $value, '[' ) ) $text .= "\n" . $value; } );
		}
		return $text;
	}

	/** Etiquetas registradas: las de esta petición más las de la última pasada de WP-Cron; null si aún no hay pasada. */
	private static function registered() {
		global $shortcode_tags;
		$known = get_option( self::KNOWN, null );
		if ( ! is_array( $known ) ) return null;
		return array_merge( array_keys( (array) $shortcode_tags ), $known );
	}

	private static function woo_role( $post_id ) {
		if ( ! function_exists( 'wc_get_page_id' ) ) return '';
		foreach ( array( 'cart', 'checkout', 'myaccount' ) as $role ) if ( (int) wc_get_page_id( $role ) === (int) $post_id ) return $role;
		return '';
	}

	/** Revisa un contenido y guarda (o borra) sus motivos. Devuelve los motivos. */
	public static function scan_post( $post, $registered = false ) {
		$post = get_post( $post );
		if ( ! $post ) return array();
		if ( 'publish' !== $post->post_status || ! in_array( $post->post_type, self::types(), true ) ) { if ( '' !== get_post_meta( $post->ID, self::META, true ) ) delete_post_meta( $post->ID, self::META ); return array(); }
		$reasons = self::reasons( self::content_text( $post ), self::woo_role( $post->ID ), false === $registered ? self::registered() : $registered );
		// Quien apagó «Carrito, checkout, gracias y mi cuenta» decidió dejarlas indexables: la detección no lo contradice.
		if ( ! Digitalisimo_Integrations_SEO_Resolver::option( 'noindex_woo_pages', 1 ) ) $reasons = array_values( array_filter( $reasons, function( $r ) { return 0 !== strpos( $r, 'woo:' ); } ) );
		$stored  = get_post_meta( $post->ID, self::META, true );
		if ( ! $reasons ) { if ( '' !== $stored ) delete_post_meta( $post->ID, self::META ); }
		elseif ( $stored !== $reasons ) update_post_meta( $post->ID, self::META, $reasons );
		return $reasons;
	}

	public static function on_save( $post_id, $post ) {
		if ( ! self::enabled() || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;
		self::scan_post( $post );
		if ( ! is_array( get_option( self::KNOWN, null ) ) ) self::request_rescan();
	}

	/** Cambió el código que registra shortcodes: se vuelve a tomar la lista y a repasar lo publicado. */
	public static function request_rescan( $unused = null ) {
		if ( ! self::enabled() ) return;
		delete_option( self::KNOWN );
		update_option( self::STATE, array( 'version' => self::VERSION, 'cursor' => 0, 'done' => false ), false );
		if ( ! wp_next_scheduled( self::CRON ) ) wp_schedule_single_event( time() + 60, self::CRON );
	}

	/** Primera vez (o reglas nuevas): repasa lo publicado en segundo plano. */
	public static function maybe_start() {
		if ( ! self::enabled() ) return;
		$state = get_option( self::STATE, array() );
		if ( is_array( $state ) && (int) ( $state['version'] ?? 0 ) >= self::VERSION ) {
			if ( empty( $state['done'] ) && ! wp_next_scheduled( self::CRON ) ) wp_schedule_single_event( time() + 60, self::CRON );
			return;
		}
		self::request_rescan();
	}

	/** Un lote de WP-Cron: toma las etiquetas registradas en este contexto y revisa los siguientes contenidos. */
	public static function run_batch() {
		if ( ! self::enabled() ) return 0;
		global $wpdb, $shortcode_tags;
		$state = get_option( self::STATE, array() );
		$state = is_array( $state ) ? $state : array();
		$known = get_option( self::KNOWN, null );
		$tags  = array_keys( (array) $shortcode_tags );
		update_option( self::KNOWN, array_values( array_unique( array_merge( is_array( $known ) && ! empty( $state['cursor'] ) ? $known : array(), $tags ) ) ), false );
		$types = self::types();
		if ( ! $types ) return 0;
		$in  = implode( ', ', array_fill( 0, count( $types ), '%s' ) );
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ($in) AND ID > %d ORDER BY ID ASC LIMIT %d", array_merge( $types, array( (int) ( $state['cursor'] ?? 0 ), self::BATCH ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		$registered = self::registered();
		foreach ( $ids as $id ) self::scan_post( (int) $id, $registered );
		$done  = count( $ids ) < self::BATCH;
		update_option( self::STATE, array( 'version' => self::VERSION, 'cursor' => $done ? 0 : (int) end( $ids ), 'done' => $done, 'ran' => time() ), false );
		if ( ! $done ) wp_schedule_single_event( time() + 30, self::CRON );
		return count( $ids );
	}

	/* ------------------------------------------------------------------ */
	/* Administración.                                                      */
	/* ------------------------------------------------------------------ */

	public static function handle_lock() {
		$id   = absint( $_GET['post'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$lock = ! empty( $_GET['lock'] ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $id || ! current_user_can( 'edit_post', $id ) || ! check_admin_referer( 'digitalisimo_seo_index_lock_' . $id ) ) wp_die( 'No autorizado.' );
		if ( $lock ) update_post_meta( $id, self::LOCK, '1' ); else delete_post_meta( $id, self::LOCK );
		wp_safe_redirect( admin_url( 'admin.php?page=digitalisimo-seo-indexing&index_lock=' . ( $lock ? 'on' : 'off' ) ) );
		exit;
	}

	private static function lock_url( $id, $lock ) {
		return wp_nonce_url( add_query_arg( array( 'action' => 'digitalisimo_seo_index_lock', 'post' => (int) $id, 'lock' => $lock ? 1 : 0 ), admin_url( 'admin-post.php' ) ), 'digitalisimo_seo_index_lock_' . (int) $id );
	}

	/** Lista bajo los ajustes de Indexación (fuera de su formulario). */
	public static function render_report() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		echo '<h2>Páginas técnicas y shortcodes sin resolver</h2>';
		if ( ! self::enabled() ) { echo '<p class="description">La detección está apagada: estas páginas siguen como las dejó cada contenido.</p>'; return; }
		$state = get_option( self::STATE, array() );
		if ( ! is_array( $state ) || empty( $state['done'] ) ) echo '<p class="description">Revisando lo publicado en segundo plano; la lista se completa en unos minutos.</p>';
		$rows = get_posts( array( 'post_type' => self::types(), 'post_status' => 'publish', 'posts_per_page' => 200, 'no_found_rows' => true, 'meta_key' => self::META, 'orderby' => 'title', 'order' => 'ASC' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		if ( ! $rows ) { echo '<p class="description">Ninguna página publicada parece técnica ni muestra un shortcode sin resolver.</p>'; return; }
		echo '<p>Salen del sitemap y llevan <code>noindex</code> sin que nadie las marque. Si una debe estar en Google, márcala como indexable: la detección sólo te avisará. Al corregirla (quitar el shortcode o volver a activar su plugin) y guardarla, sale sola de esta lista.</p>';
		echo '<table class="widefat striped"><thead><tr><th>Contenido</th><th>Motivo</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>';
		foreach ( $rows as $post ) {
			$locked  = '1' === get_post_meta( $post->ID, self::LOCK, true );
			$reasons = (array) get_post_meta( $post->ID, self::META, true );
			echo '<tr><td><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ?: '(sin título)' ) . '</a><br><code>' . esc_html( (string) wp_parse_url( get_permalink( $post ), PHP_URL_PATH ) ) . '</code></td><td>' . esc_html( implode( ' · ', array_map( array( __CLASS__, 'label' ), $reasons ) ) ) . '</td>';
			echo '<td>' . ( $locked ? '<span class="digitalisimo-effective-state is-active">Indexable por decisión del dueño</span><br><span class="description">Aviso: parece una página técnica o rota.</span>' : '<span class="digitalisimo-effective-state is-inactive">noindex · fuera del sitemap</span>' ) . '</td>';
			echo '<td><a href="' . esc_url( get_edit_post_link( $post->ID ) ?: '#' ) . '">Editar</a> · <a href="' . esc_url( self::lock_url( $post->ID, ! $locked ) ) . '">' . ( $locked ? 'Dejar que decida la detección' : 'Mantener indexable' ) . '</a></td></tr>';
		}
		echo '</tbody></table>';
	}

	/** Línea del editor: qué detectó en este contenido (o nada). */
	public static function editor_note( $post ) {
		if ( ! self::enabled() || ! $post ) return '';
		$reasons = get_post_meta( $post->ID, self::META, true );
		if ( ! is_array( $reasons ) || ! $reasons ) return '';
		return implode( ' · ', array_map( array( __CLASS__, 'label' ), $reasons ) );
	}
}
