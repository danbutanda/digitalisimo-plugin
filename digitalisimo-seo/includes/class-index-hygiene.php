<?php
defined( 'ABSPATH' ) || exit;

/**
 * Páginas que muestran un shortcode sin resolver, casi siempre de un plugin que ya no está (`[wpuf_account]` en una
 * página «Cuenta» que quedó publicada). Por omisión sólo se AVISA en SEO → Indexación y en el editor; el noindex y la
 * salida del sitemap sólo se aplican con «Sacar del índice las páginas con shortcodes sin resolver» encendido.
 * (Las páginas de proceso de WooCommerce no dependen de este módulo: se reconocen en vivo por la página que WooCommerce
 * tiene asignada, con «Carrito, checkout, gracias y mi cuenta».)
 *
 * Para no sacar de Google páginas sanas, un hallazgo exige las tres cosas:
 *   1. Forma de shortcode real en el texto que se ve: `[tag]` o `[tag atr="…"]` con la etiqueta en minúsculas, sin
 *      atributos sueltos ([Nota del editor]), sin enlace markdown ([texto](url)) y fuera de <code>, <pre>, <style>,
 *      <script> y comentarios. En una página de Elementor/Elements sólo se leen los campos de texto, HTML y shortcode
 *      de sus widgets: nunca las etiquetas dinámicas (__dynamic__, [elementor-tag …]) ni los formularios.
 *   2. Etiqueta no registrada ni en esta petición ni en el front: una petición normal del front anota (como mucho cada
 *      hora, y enseguida tras cambiar plugins o tema) sus shortcodes registrados, así cuentan también los que un
 *      plugin sólo registra en el front o carga un organizador de plugins por URL. Sin esa muestra no se decide.
 *   3. Nombre de un plugin conocido (lista fija de familias habituales) o que alguna vez estuvo registrado en el sitio.
 *      Nunca cuentan elementor-tag, field ni all-fields.
 *
 * Tope de seguridad: si lo detectado pasa de 20 páginas o del 10 % de las publicadas, no se aplica nada hasta que el
 * dueño lo confirme en SEO → Indexación. «Mantener indexable» en un contenido siempre gana: sólo se avisa.
 *
 * La detección nunca corre al servir una página: se hace al guardar y en segundo plano, por lotes de 25 (WP-Cron).
 */
class Digitalisimo_Integrations_Index_Hygiene {
	const META    = 'digitalisimo_seo_auto_noindex';
	const LOCK    = 'digitalisimo_seo_index_lock';
	/** Etiquetas vistas registradas alguna vez en este sitio (no se vacía). */
	const HISTORY = 'digitalisimo_seo_shortcodes_seen';
	/** Etiquetas registradas en el front desde el último cambio de plugins o tema: etiqueta => última vez. */
	const FRONT   = 'digitalisimo_seo_shortcodes_front';
	/** Reloj (con autoload): última muestra del front y último cambio de plugins o tema. */
	const CLOCK   = 'digitalisimo_seo_shortcodes_clock';
	/** Con autoload: 1 si lo detectado puede aplicarse (no superó el tope o el dueño lo confirmó). */
	const APPLY   = 'digitalisimo_seo_hygiene_apply';
	const STATE   = 'digitalisimo_seo_hygiene_state';
	const CRON    = 'digitalisimo_seo_hygiene_scan';
	const VERSION = 2;
	const BATCH   = 25;
	/** Tope de seguridad: más de esto (en páginas o en proporción de las publicadas) espera la confirmación del dueño. */
	const CAP_PAGES = 20;
	const CAP_RATIO = 0.10;
	/** Sin muestra del front tras un cambio, se decide con lo de WP-Cron pasado este tiempo (sitio sin visitas). */
	const FRONT_WAIT = DAY_IN_SECONDS;
	/** Nunca son un shortcode roto: etiquetas dinámicas de Elementor y campos de formularios. */
	const EXCLUDED = array( 'elementor-tag', 'field', 'all-fields' );
	/** Del núcleo, aunque alguno no figure en $shortcode_tags en todas las peticiones. */
	const CORE_SHORTCODES = array( 'embed', 'caption', 'wp_caption', 'gallery', 'audio', 'video', 'playlist' );
	/** Shortcodes de plugins habituales que quedan huérfanos al desinstalarlos. Con * al final, la familia entera. */
	const KNOWN_PLUGINS = array(
		'wpuf_*', 'wpuf-*', 'tutor_*', 'tutor-*', 'rank_math_*', 'wpseo_*', 'yoast_*', 'vc_*', 'et_pb_*', 'fusion_*', 'su_*',
		'pmpro_*', 'mepr-*', 'ld_*', 'learndash_*', 'bbp-*', 'ultimatemember*', 'um_*', 'gravityform*', 'ninja_form*',
		'wpforms*', 'contact-form-7', 'mc4wp_form', 'rev_slider*', 'smartslider3', 'metaslider', 'layerslider',
		'foogallery', 'envira-gallery', 'yith_*', 'edd_*', 'purchase_link', 'download_checkout', 'give_*', 'tribe_*', 'tribe-*',
		'wcfm*', 'dokan-*', 'woocommerce_*', 'product_page', 'product_category', 'product_categories',
		'add_to_cart', 'add_to_cart_url', 'shop_messages', 'fluentform', 'formidable', 'caldera_form', 'popup_trigger',
		'wppb-*', 'profilepress-*', 'user_registration_*', 'mailpoet_form', 'newsletter_form', 'sibwp_form',
		'cmplz-*', 'borlabs-cookie', 'wpdm_*', 'wpdm-*', 'pdf-embedder', 'dflip', 'tablepress', 'wpdatatable',
		'elementor-template', 'jet_*', 'jet-*', 'ae_*', 'ultimate_*', 'bdp_*', 'sp_wpcarousel', 'wpgmza', 'wpgmp_map',
		'booked-*', 'ameliabooking', 'ameliaevents', 'bookly-*', 'wpbs', 'mphb_*', 'wp_bakery_*', 'themify_*', 'tm_*',
		'ux_*', 'flatsome_*', 'gdlr_*', 'qode_*', 'mk_*', 'vcex_*', 'kc_*', 'trx_*', 'cz_*', 'wpcf7*', 'cf7*', 'acfe_*',
		'toolset-*', 'wpv-*', 'show_tutor_*', 'tutor_student_registration_form',
	);

	public static function init() {
		add_action( 'save_post', array( __CLASS__, 'on_save' ), 30, 2 );
		add_action( self::CRON, array( __CLASS__, 'run_batch' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_start' ), 20 );
		add_action( 'activated_plugin', array( __CLASS__, 'request_rescan' ), 10, 1 );
		add_action( 'deactivated_plugin', array( __CLASS__, 'request_rescan' ), 10, 1 );
		add_action( 'switch_theme', array( __CLASS__, 'request_rescan' ), 10, 1 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'request_rescan' ), 10, 1 );
		add_action( 'admin_post_digitalisimo_seo_index_lock', array( __CLASS__, 'handle_lock' ) );
		add_action( 'admin_post_digitalisimo_seo_hygiene_confirm', array( __CLASS__, 'handle_confirm' ) );
		if ( ! is_admin() ) add_action( 'wp_footer', array( __CLASS__, 'sample_front' ), 9999 );
	}

	/** ¿Se aplica noindex a lo detectado? Apagado de fábrica: sólo se avisa. */
	public static function applies() { return (bool) Digitalisimo_Integrations_SEO_Resolver::option( 'seo_noindex_unresolved', 0 ); }

	/* ------------------------------------------------------------------ */
	/* Reglas puras.                                                        */
	/* ------------------------------------------------------------------ */

	/**
	 * Etiquetas con forma de shortcode real en un texto: [tag], [tag/], [tag atr="v"], [tag atr=v] (atributos con
	 * nombre), etiqueta en minúsculas, sin pegarse a una palabra (a[hidden]) ni seguir con «(» ([texto](url)). Fuera
	 * de <code>, <pre>, <script>, <style>, comentarios HTML (los delimitadores de bloques) y [[escapados]].
	 *
	 * @return string[]
	 */
	public static function shortcode_tags_in( $text ) {
		$text = (string) $text;
		if ( false === strpos( $text, '[' ) ) return array();
		$text = preg_replace( '/<!--.*?-->/s', ' ', $text );
		$text = preg_replace( '#<(code|pre|script|style)\b[^>]*>.*?</\1>#is', ' ', $text );
		$text = preg_replace( '/\[\[.*?\]\]/s', ' ', $text );
		$attr = '(?:\s+[a-z_][a-z0-9_-]*\s*=\s*(?:"[^"\]]*"|\'[^\'\]]*\'|[^\s\]"\']+))*';
		if ( ! preg_match_all( '/(?<![\w\]\-])\[([a-z][a-z0-9_-]+)(' . $attr . ')\s*\/?\](?!\()/', $text, $m ) ) return array();
		return array_values( array_unique( $m[1] ) );
	}

	/** ¿Es el nombre de un shortcode de plugin conocido? (pura) */
	public static function known_plugin_tag( $tag ) {
		foreach ( self::KNOWN_PLUGINS as $known ) {
			if ( '*' === substr( $known, -1 ) ? 0 === strpos( $tag, substr( $known, 0, -1 ) ) : $tag === $known ) return true;
		}
		return false;
	}

	/**
	 * Etiquetas rotas (pura): ni registradas ni del núcleo ni excluidas, y con nombre de plugin conocido o que estuvo
	 * registrado alguna vez ($history).
	 */
	public static function unresolved( array $tags, array $registered, array $history = array() ) {
		$skip = array_flip( array_merge( $registered, self::CORE_SHORTCODES, self::EXCLUDED ) );
		$seen = array_flip( $history );
		return array_values( array_filter( $tags, function( $tag ) use ( $skip, $seen ) { return ! isset( $skip[ $tag ] ) && ( isset( $seen[ $tag ] ) || self::known_plugin_tag( $tag ) ); } ) );
	}

	/**
	 * Texto que se ve de una página hecha con Elementor/Elements (pura): sólo los campos de texto, HTML y shortcode de
	 * sus widgets. Nunca __dynamic__ (etiquetas dinámicas), formularios ni ajustes de estilo.
	 */
	public static function elementor_text( $elements ) {
		$fields = array( 'text-editor' => array( 'editor' ), 'html' => array( 'html' ), 'shortcode' => array( 'shortcode' ), 'heading' => array( 'title' ) );
		$text   = '';
		foreach ( (array) $elements as $element ) {
			if ( ! is_array( $element ) ) continue;
			$type = (string) ( $element['widgetType'] ?? '' );
			if ( isset( $fields[ $type ] ) ) foreach ( $fields[ $type ] as $key ) if ( isset( $element['settings'][ $key ] ) && is_string( $element['settings'][ $key ] ) ) $text .= "\n" . $element['settings'][ $key ];
			if ( ! empty( $element['elements'] ) ) $text .= self::elementor_text( $element['elements'] );
		}
		return $text;
	}

	/** Motivos de un contenido (pura). $registered null = aún no se puede decidir. */
	public static function reasons( $text, $registered = null, array $history = array() ) {
		if ( ! is_array( $registered ) ) return array();
		return array_map( function( $tag ) { return 'shortcode:' . $tag; }, self::unresolved( self::shortcode_tags_in( $text ), $registered, $history ) );
	}

	/** ¿Supera el tope de seguridad? (pura) */
	public static function over_cap( $flagged, $total ) {
		return $flagged > self::CAP_PAGES || ( $flagged > 0 && $flagged > self::CAP_RATIO * max( 1, (int) $total ) );
	}

	/** Texto legible de un motivo. */
	public static function label( $reason ) {
		list( $kind, $value ) = array_pad( explode( ':', (string) $reason, 2 ), 2, '' );
		return 'shortcode' === $kind ? 'Shortcode sin resolver [' . $value . ']' : (string) $reason;
	}

	/* ------------------------------------------------------------------ */
	/* Lectura en el front y en el sitemap.                                 */
	/* ------------------------------------------------------------------ */

	/** ¿Lleva noindex por la detección? Sólo con el ajuste encendido, bajo el tope y sin «Mantener indexable». */
	public static function auto_noindex( $post_id ) {
		if ( ! $post_id || ! self::applies() ) return false;
		$reasons = get_post_meta( (int) $post_id, self::META, true );
		return is_array( $reasons ) && $reasons && '1' !== get_post_meta( (int) $post_id, self::LOCK, true ) && (int) get_option( self::APPLY, 1 );
	}

	/** Cláusula meta_query del sitemap: fuera lo detectado, salvo lo indexable a propósito. Vacía si no se aplica. */
	public static function sitemap_clause() {
		if ( ! self::applies() || ! (int) get_option( self::APPLY, 1 ) ) return array();
		return array( 'relation' => 'OR', array( 'key' => self::META, 'compare' => 'NOT EXISTS' ), array( 'key' => self::LOCK, 'value' => '1' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Etiquetas registradas.                                               */
	/* ------------------------------------------------------------------ */

	private static function clock() { $clock = get_option( self::CLOCK, array() ); return is_array( $clock ) ? $clock : array(); }

	/** Añade etiquetas al historial (sólo escribe si hay alguna nueva). */
	private static function remember( array $tags ) {
		$history = get_option( self::HISTORY, array() );
		$history = is_array( $history ) ? $history : array();
		$new     = array_diff( $tags, $history );
		if ( $new ) update_option( self::HISTORY, array_values( array_merge( $history, $new ) ), false );
	}

	/**
	 * Muestra del front, en una petición normal (wp_footer): como mucho cada hora, y enseguida tras un cambio de
	 * plugins o tema. Sólo lee una opción con autoload; escribe sólo cuando toca la muestra.
	 */
	public static function sample_front() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) return;
		$clock = self::clock();
		$at    = (int) ( $clock['at'] ?? 0 );
		if ( $at > (int) ( $clock['changed'] ?? 0 ) && time() - $at < HOUR_IN_SECONDS ) return;
		global $shortcode_tags;
		$tags  = array_keys( (array) $shortcode_tags );
		$front = $at > (int) ( $clock['changed'] ?? 0 ) ? get_option( self::FRONT, array() ) : array(); // Nueva época tras un cambio.
		$front = is_array( $front ) ? $front : array();
		foreach ( $tags as $tag ) $front[ $tag ] = time();
		update_option( self::FRONT, $front, false );
		self::remember( $tags );
		$clock['at'] = time();
		update_option( self::CLOCK, $clock, true );
	}

	/**
	 * Etiquetas registradas para decidir: las de esta petición más las del front desde el último cambio. null si el
	 * front aún no dio una muestra posterior al cambio (salvo que pasara FRONT_WAIT: sitio sin visitas).
	 */
	public static function registered() {
		global $shortcode_tags;
		$clock = self::clock();
		$ready = (int) ( $clock['at'] ?? 0 ) > (int) ( $clock['changed'] ?? 0 );
		if ( ! $ready && ( empty( $clock['changed'] ) || time() - (int) $clock['changed'] < self::FRONT_WAIT ) ) return null;
		$front = $ready ? get_option( self::FRONT, array() ) : array();
		return array_values( array_unique( array_merge( array_keys( (array) $shortcode_tags ), array_keys( is_array( $front ) ? $front : array() ) ) ) );
	}

	private static function history() { $history = get_option( self::HISTORY, array() ); return is_array( $history ) ? $history : array(); }

	/* ------------------------------------------------------------------ */
	/* Detección.                                                           */
	/* ------------------------------------------------------------------ */

	/** Tipos que se revisan: los públicos con URL, sin adjuntos ni plantillas. */
	public static function types() {
		$types = get_post_types( array( 'public' => true ) );
		return array_values( array_diff( $types, array( 'attachment', 'elementor_library', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation' ) ) );
	}

	/** Texto que se ve: el de los widgets si la página se hace con Elementor/Elements; si no, el del editor. */
	private static function visible_text( $post ) {
		if ( 'builder' === get_post_meta( $post->ID, '_elementor_edit_mode', true ) ) {
			$data = get_post_meta( $post->ID, '_elementor_data', true );
			$data = is_string( $data ) ? json_decode( $data, true ) : $data;
			return is_array( $data ) ? self::elementor_text( $data ) : '';
		}
		return (string) $post->post_content;
	}

	/** Revisa un contenido y guarda (o borra) sus motivos. Devuelve si cambió la marca. */
	public static function scan_post( $post, $registered = false, $history = null ) {
		$post = get_post( $post );
		if ( ! $post ) return false;
		$stored = get_post_meta( $post->ID, self::META, true );
		if ( 'publish' !== $post->post_status || ! in_array( $post->post_type, self::types(), true ) ) {
			if ( '' === $stored ) return false;
			delete_post_meta( $post->ID, self::META );
			return true;
		}
		$registered = false === $registered ? self::registered() : $registered;
		if ( ! is_array( $registered ) ) return false; // Sin muestra del front no se decide nada.
		$reasons = self::reasons( self::visible_text( $post ), $registered, null === $history ? self::history() : $history );
		if ( ! $reasons ) { if ( '' === $stored ) return false; delete_post_meta( $post->ID, self::META ); return true; }
		if ( $stored === $reasons ) return false;
		update_post_meta( $post->ID, self::META, $reasons );
		return true;
	}

	public static function on_save( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;
		global $shortcode_tags;
		self::remember( array_keys( (array) $shortcode_tags ) );
		if ( self::scan_post( $post ) ) self::recount();
		if ( ! is_array( self::registered() ) && ! wp_next_scheduled( self::CRON ) ) wp_schedule_single_event( time() + 15 * MINUTE_IN_SECONDS, self::CRON );
	}

	/** Cambió el código que registra shortcodes: nueva época del front y repaso de lo publicado. */
	public static function request_rescan( $unused = null ) {
		$clock = self::clock();
		$clock['changed'] = time();
		update_option( self::CLOCK, $clock, true );
		$state = get_option( self::STATE, array() );
		$state = is_array( $state ) ? $state : array();
		update_option( self::STATE, array_merge( $state, array( 'version' => self::VERSION, 'cursor' => 0, 'done' => false ) ), false );
		if ( ! wp_next_scheduled( self::CRON ) ) wp_schedule_single_event( time() + 10 * MINUTE_IN_SECONDS, self::CRON );
	}

	/** Primera vez (o reglas nuevas): repasa lo publicado en segundo plano. */
	public static function maybe_start() {
		$state = get_option( self::STATE, array() );
		if ( is_array( $state ) && (int) ( $state['version'] ?? 0 ) >= self::VERSION ) {
			if ( empty( $state['done'] ) && ! wp_next_scheduled( self::CRON ) ) wp_schedule_single_event( time() + 10 * MINUTE_IN_SECONDS, self::CRON );
			return;
		}
		self::request_rescan();
	}

	/** Un lote de WP-Cron: espera la muestra del front, revisa 25 contenidos y libera su caché. */
	public static function run_batch() {
		global $wpdb, $shortcode_tags;
		self::remember( array_keys( (array) $shortcode_tags ) );
		$registered = self::registered();
		if ( ! is_array( $registered ) ) { wp_schedule_single_event( time() + 15 * MINUTE_IN_SECONDS, self::CRON ); return 0; }
		$state = get_option( self::STATE, array() );
		$state = is_array( $state ) ? $state : array();
		$types = self::types();
		if ( ! $types ) return 0;
		$in      = implode( ', ', array_fill( 0, count( $types ), '%s' ) );
		$ids     = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ($in) AND ID > %d ORDER BY ID ASC LIMIT %d", array_merge( $types, array( (int) ( $state['cursor'] ?? 0 ), self::BATCH ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		$history = self::history();
		foreach ( $ids as $id ) {
			self::scan_post( (int) $id, $registered, $history );
			// Sin caché de objetos persistente, cada contenido revisado deja sus metas en memoria: se liberan.
			wp_cache_delete( (int) $id, 'post_meta' );
			wp_cache_delete( (int) $id, 'posts' );
		}
		$done = count( $ids ) < self::BATCH;
		update_option( self::STATE, array_merge( $state, array( 'version' => self::VERSION, 'cursor' => $done ? 0 : (int) end( $ids ), 'done' => $done, 'ran' => time() ) ), false );
		if ( $done ) self::recount();
		else wp_schedule_single_event( time() + 30, self::CRON );
		return count( $ids );
	}

	/** Cuenta lo detectado y decide si se puede aplicar (tope de seguridad). */
	public static function recount() {
		global $wpdb;
		$types = self::types();
		if ( ! $types ) return;
		$in      = implode( ', ', array_fill( 0, count( $types ), '%s' ) );
		$total   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ($in)", $types ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		$flagged = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s WHERE p.post_status = 'publish' AND p.post_type IN ($in)", array_merge( array( self::META ), $types ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		$state   = get_option( self::STATE, array() );
		$state   = is_array( $state ) ? $state : array();
		$held    = self::over_cap( $flagged, $total ) && $flagged > (int) ( $state['confirmed'] ?? 0 );
		update_option( self::STATE, array_merge( $state, array( 'flagged' => $flagged, 'total' => $total, 'held' => $held ) ), false );
		update_option( self::APPLY, $held ? 0 : 1, true );
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

	/** El dueño confirma que lo detectado (por encima del tope) es correcto. */
	public static function handle_confirm() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'digitalisimo_seo_hygiene_confirm' ) ) wp_die( 'No autorizado.' );
		$state = get_option( self::STATE, array() );
		$state = is_array( $state ) ? $state : array();
		$state['confirmed'] = (int) ( $state['flagged'] ?? 0 );
		update_option( self::STATE, $state, false );
		self::recount();
		wp_safe_redirect( admin_url( 'admin.php?page=digitalisimo-seo-indexing&hygiene_confirmed=1' ) );
		exit;
	}

	private static function lock_url( $id, $lock ) {
		return wp_nonce_url( add_query_arg( array( 'action' => 'digitalisimo_seo_index_lock', 'post' => (int) $id, 'lock' => $lock ? 1 : 0 ), admin_url( 'admin-post.php' ) ), 'digitalisimo_seo_index_lock_' . (int) $id );
	}

	/** Lista bajo los ajustes de Indexación (fuera de su formulario). */
	public static function render_report() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		echo '<h2>Páginas técnicas y shortcodes sin resolver</h2>';
		if ( function_exists( 'wc_get_page_id' ) ) {
			$woo = array();
			foreach ( array( 'cart' => 'Carrito', 'checkout' => 'Pago (incluye «pedido recibido»)', 'myaccount' => 'Mi cuenta' ) as $role => $name ) { $id = (int) wc_get_page_id( $role ); if ( $id > 0 && get_post( $id ) ) $woo[] = $name . ': <code>' . esc_html( (string) wp_parse_url( get_permalink( $id ), PHP_URL_PATH ) ) . '</code>'; }
			if ( $woo ) echo '<p>' . ( Digitalisimo_Integrations_SEO_Resolver::option( 'noindex_woo_pages', 1 ) ? 'Páginas de WooCommerce con noindex y fuera del sitemap (las que WooCommerce tiene asignadas): ' : 'Páginas de WooCommerce indexables porque «Carrito, checkout, gracias y mi cuenta» está apagado: ' ) . implode( ' · ', $woo ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		$state = get_option( self::STATE, array() );
		$state = is_array( $state ) ? $state : array();
		if ( empty( $state['done'] ) ) echo '<p class="description">Revisando lo publicado en segundo plano; espera la primera visita al sitio y unos minutos.</p>';
		$rows = get_posts( array( 'post_type' => self::types(), 'post_status' => 'publish', 'posts_per_page' => 200, 'no_found_rows' => true, 'meta_key' => self::META, 'orderby' => 'title', 'order' => 'ASC' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		if ( ! $rows ) { echo '<p class="description">Ninguna página publicada muestra un shortcode sin resolver.</p>'; return; }
		$applies = self::applies();
		if ( ! empty( $state['held'] ) ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html( sprintf( 'Se detectaron %1$d de %2$d páginas publicadas, más de lo razonable para un shortcode roto: no se aplica nada hasta que lo confirmes. Revisa la lista; si es correcta, confírmala.', (int) $state['flagged'], (int) $state['total'] ) ) . '</p><p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=digitalisimo_seo_hygiene_confirm' ), 'digitalisimo_seo_hygiene_confirm' ) ) . '">Confirmar la lista</a></p></div>';
		}
		echo '<p>' . ( $applies ? 'Llevan <code>noindex</code> y salen del sitemap, salvo las que mantengas indexables.' : 'Sólo es un aviso: siguen en Google y en el sitemap. Para sacarlas del índice enciende «Sacar del índice las páginas con shortcodes sin resolver».' ) . ' Al corregirlas (quitar el shortcode o volver a activar su plugin) y guardarlas, salen solas de esta lista.</p>';
		echo '<table class="widefat striped"><thead><tr><th>Contenido</th><th>Motivo</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>';
		foreach ( $rows as $post ) {
			$locked  = '1' === get_post_meta( $post->ID, self::LOCK, true );
			$reasons = (array) get_post_meta( $post->ID, self::META, true );
			$status  = $locked ? '<span class="digitalisimo-effective-state is-active">Indexable por decisión del dueño</span>' : ( $applies && empty( $state['held'] ) ? '<span class="digitalisimo-effective-state is-inactive">noindex · fuera del sitemap</span>' : '<span class="digitalisimo-effective-state is-active">Indexable · sólo aviso</span>' );
			echo '<tr><td><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ?: '(sin título)' ) . '</a><br><code>' . esc_html( (string) wp_parse_url( get_permalink( $post ), PHP_URL_PATH ) ) . '</code></td><td>' . esc_html( implode( ' · ', array_map( array( __CLASS__, 'label' ), $reasons ) ) ) . '</td><td>' . $status . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<td><a href="' . esc_url( get_edit_post_link( $post->ID ) ?: '#' ) . '">Editar</a> · <a href="' . esc_url( self::lock_url( $post->ID, ! $locked ) ) . '">' . ( $locked ? 'Dejar que decida la detección' : 'Mantener indexable' ) . '</a></td></tr>';
		}
		echo '</tbody></table>';
	}

	/** Línea del editor: qué detectó en este contenido (o nada). */
	public static function editor_note( $post ) {
		if ( ! $post ) return '';
		$reasons = get_post_meta( $post->ID, self::META, true );
		if ( ! is_array( $reasons ) || ! $reasons ) return '';
		return implode( ' · ', array_map( array( __CLASS__, 'label' ), $reasons ) );
	}
}
