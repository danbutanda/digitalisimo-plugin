<?php
defined( 'ABSPATH' ) || exit;

/**
 * Acciones del análisis del sitio (tanda B de la auditoría SEO del 2026-11-24): B2, conservar una de las páginas que
 * compiten y redirigir las demás con 301; B3, noindex, 410 o 301 en lote para las páginas huérfanas o de relleno. Los
 * productos de la tienda nunca entran en un lote (`Site_Analysis::STORE_TYPES`): sólo se informan.
 *
 * Nada se aplica en un clic:
 *   1. «Revisar» arma el plan (qué pasa con cada URL y qué se omite y por qué) y lo guarda una hora para ese usuario;
 *   2. la pantalla de confirmación lo muestra entero y sólo «Confirmar y aplicar» lo ejecuta. Si son más de 20 páginas
 *      o más del 10 % de las publicadas (el mismo tope de A5), además hay que marcar la casilla del tope;
 *   3. antes de cambiar nada se guarda el respaldo del lote (la marca noindex que tenía cada página y, después, las
 *      reglas creadas), y «Deshacer» lo devuelve como estaba: borra sólo esas reglas y repone cada noindex.
 * Nunca se borra ni se despublica un contenido: un 301 o un 410 es una regla de Redirecciones (origen «cleanup») y la
 * página sale además del sitemap con noindex. Nunca se tocan la portada, la página del blog, la de privacidad ni las de
 * WooCommerce, y un 301 nunca va a la portada (Google lo trata como soft 404) ni fuera del sitio.
 */
class Digitalisimo_Integrations_SEO_Cleanup {
	const PLAN  = 'digitalisimo_seo_cleanup_plan_';
	const LOG   = 'digitalisimo_seo_cleanup_log';
	const KEEP  = 50;
	const KINDS = array( 'noindex', '410', '301' );
	const MAX   = 500;

	public static function init() {
		add_action( 'admin_post_digitalisimo_seo_cleanup_preview', array( __CLASS__, 'handle_preview' ) );
		add_action( 'admin_post_digitalisimo_seo_cleanup_apply', array( __CLASS__, 'handle_apply' ) );
		add_action( 'admin_post_digitalisimo_seo_cleanup_undo', array( __CLASS__, 'handle_undo' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Reglas.                                                              */
	/* ------------------------------------------------------------------ */

	/** Por qué un contenido nunca se redirige ni se saca del índice desde aquí ('' si se puede). */
	public static function protected_reason( $id ) {
		$id = (int) $id;
		if ( $id === (int) get_option( 'page_on_front' ) ) return 'Es la portada';
		if ( $id === (int) get_option( 'page_for_posts' ) ) return 'Es la página del blog';
		if ( $id === (int) get_option( 'wp_page_for_privacy_policy' ) ) return 'Es la política de privacidad';
		if ( function_exists( 'wc_get_page_id' ) ) foreach ( array( 'shop', 'cart', 'checkout', 'myaccount', 'terms' ) as $role ) if ( $id === (int) wc_get_page_id( $role ) ) return 'Es una página de WooCommerce';
		return '';
	}

	/** ¿Pasa del tope de seguridad? El mismo de A5: más de 20 páginas o del 10 % de las publicadas. */
	public static function over_cap( $count, $total ) {
		return class_exists( 'Digitalisimo_Integrations_Index_Hygiene' ) ? Digitalisimo_Integrations_Index_Hygiene::over_cap( (int) $count, (int) $total ) : ( $count > 20 || $count > 0.1 * max( 1, $total ) );
	}

	/** Ruta de un contenido, relativa al sitio, como la guardan las reglas. */
	private static function path( $id ) {
		list( $path ) = Digitalisimo_Integrations_Redirections::relative( (string) get_permalink( $id ), (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
		return $path;
	}

	/** Regla activa con ese origen exacto, si existe (id). */
	private static function existing_rule( $path ) {
		$index = self::rules_index();
		return (int) ( $index[ Digitalisimo_Integrations_Redirections::signature( array( 'pattern' => $path, 'comparison' => 'exact', 'ignore' => '' ) ) ] ?? 0 );
	}

	/** @var array|null Firma del origen => id de la regla activa, leído una sola vez por lote. */
	private static $rules = null;

	/** Reglas activas por la firma de cada origen (una consulta por lote, no una por página). */
	private static function rules_index( $fresh = false ) {
		global $wpdb;
		if ( null !== self::$rules && ! $fresh ) return self::$rules;
		Digitalisimo_Integrations_Redirections::maybe_install();
		self::$rules = array();
		foreach ( (array) $wpdb->get_results( 'SELECT id, sources FROM ' . Digitalisimo_Integrations_Redirections::table() . " WHERE status = 'active'", ARRAY_A ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			foreach ( (array) json_decode( (string) $row['sources'], true ) as $source ) if ( is_array( $source ) && ! isset( self::$rules[ Digitalisimo_Integrations_Redirections::signature( $source ) ] ) ) self::$rules[ Digitalisimo_Integrations_Redirections::signature( $source ) ] = (int) $row['id'];
		}
		return self::$rules;
	}

	/** Publicados que cuentan para el tope. */
	private static function published_total() {
		global $wpdb;
		$types = Digitalisimo_Integrations_Site_Analysis::types();
		if ( ! $types ) return 0;
		$in = implode( ', ', array_fill( 0, count( $types ), '%s' ) );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ($in)", $types ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Destino de un 301: un contenido publicado de este sitio (por ID o por su ruta), nunca la portada ni otro dominio.
	 *
	 * @return array{id:int,url:string}|WP_Error
	 */
	public static function target( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) return new WP_Error( 'target', 'Falta el destino del 301.' );
		$id = ctype_digit( $raw ) ? (int) $raw : 0;
		if ( ! $id ) {
			$clean = Digitalisimo_Integrations_Redirections::sanitize_target( $raw );
			if ( '' === $clean ) return new WP_Error( 'target', 'El destino no es una dirección válida.' );
			$url = 0 === strpos( $clean, '/' ) ? home_url( $clean ) : $clean;
			if ( Digitalisimo_Integrations_Site_Analysis::host( $url ) !== Digitalisimo_Integrations_Site_Analysis::host( home_url( '/' ) ) ) return new WP_Error( 'target', 'El destino debe ser una página de este sitio.' );
			list( $path ) = Digitalisimo_Integrations_Redirections::relative( $url, (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
			if ( '' === $path ) return new WP_Error( 'target', 'Nunca a la portada: Google trata esa redirección como una página que no existe (soft 404). Elige la página que sustituye a éstas.' );
			$id = (int) url_to_postid( $url );
		}
		$post = $id ? get_post( $id ) : null;
		if ( ! $post || 'publish' !== $post->post_status || ! in_array( $post->post_type, Digitalisimo_Integrations_Site_Analysis::types(), true ) ) return new WP_Error( 'target', 'El destino debe ser una página publicada de este sitio.' );
		if ( (int) get_option( 'page_on_front' ) === $id ) return new WP_Error( 'target', 'Nunca a la portada: Google trata esa redirección como una página que no existe (soft 404). Elige la página que sustituye a éstas.' );
		// Un destino que ya redirige (por una limpieza anterior o por una regla a mano) cerraría un bucle A→B→A.
		if ( self::existing_rule( self::path( $id ) ) ) return new WP_Error( 'target', 'El destino ya redirige a otra dirección: elige la página que de verdad se queda.' );
		return array( 'id' => $id, 'url' => (string) get_permalink( $id ) );
	}

	/**
	 * Plan de un lote: qué pasa con cada contenido y qué se omite y por qué. No cambia nada.
	 *
	 * @return array|WP_Error
	 */
	public static function plan( $kind, array $ids, $target_raw = '', $source = '' ) {
		self::rules_index( true );
		$kind = (string) $kind;
		if ( ! in_array( $kind, self::KINDS, true ) ) return new WP_Error( 'kind', 'Acción no admitida.' );
		$target = null;
		if ( '301' === $kind ) { $target = self::target( $target_raw ); if ( is_wp_error( $target ) ) return $target; }
		$ids   = array_slice( array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ), 0, self::MAX );
		$types = Digitalisimo_Integrations_Site_Analysis::types();
		$items = array(); $skipped = array();
		foreach ( $ids as $id ) {
			if ( $target && $id === $target['id'] ) continue; // La que se queda.
			$post = get_post( $id );
			$why  = '';
			if ( $post && Digitalisimo_Integrations_Site_Analysis::store_type( $post->post_type ) ) $why = 'Es un producto de la tienda: sólo se informa, nunca se acciona desde aquí';
			elseif ( ! $post || 'publish' !== $post->post_status || ! in_array( $post->post_type, $types, true ) ) $why = 'No está publicada';
			elseif ( '' !== ( $p = self::protected_reason( $id ) ) ) $why = $p;
			else {
				$path = self::path( $id );
				if ( '' === $path ) $why = 'No tiene dirección propia';
				elseif ( 'noindex' === $kind && '1' === (string) get_post_meta( $id, 'digitalisimo_seo_noindex', true ) ) $why = 'Ya lleva noindex';
				elseif ( 'noindex' !== $kind && self::existing_rule( $path ) ) $why = 'Ya tiene una regla de redirección';
			}
			if ( '' !== $why ) { $skipped[] = array( $id, $why ); continue; }
			$items[] = array( 'id' => $id, 'path' => self::path( $id ), 'title' => (string) get_the_title( $id ) );
		}
		$total = self::published_total();
		return array( 'kind' => $kind, 'source' => sanitize_key( (string) $source ), 'target' => $target, 'items' => $items, 'skipped' => $skipped, 'total' => $total, 'over_cap' => self::over_cap( count( $items ), $total ), 'ids' => $ids, 'target_raw' => (string) $target_raw );
	}

	/** Lo que verá cada URL al aplicar. */
	public static function describe( array $plan ) {
		if ( '301' === $plan['kind'] ) return '301 → ' . (string) wp_parse_url( (string) $plan['target']['url'], PHP_URL_PATH ) . ' · fuera del sitemap';
		if ( '410' === $plan['kind'] ) return '410 (contenido eliminado) · fuera del sitemap';
		return 'noindex · fuera de Google y del sitemap; la página sigue visible';
	}

	/* ------------------------------------------------------------------ */
	/* Aplicar y deshacer.                                                  */
	/* ------------------------------------------------------------------ */

	public static function log_entries() { $log = get_option( self::LOG, array() ); return is_array( $log ) ? $log : array(); }

	private static function save_log( array $log ) { update_option( self::LOG, array_slice( $log, -self::KEEP ), false ); }

	/**
	 * Aplica un plan ya confirmado. El respaldo (la marca noindex de cada página) se guarda ANTES de cambiar nada, y las
	 * reglas creadas se anotan en él al terminar. Devuelve el id del lote.
	 */
	public static function apply( array $plan ) {
		$batch = array( 'id' => substr( md5( uniqid( '', true ) ), 0, 12 ), 'time' => time(), 'user' => (int) get_current_user_id(), 'kind' => $plan['kind'], 'source' => $plan['source'], 'target' => $plan['target'] ? $plan['target']['url'] : '', 'items' => array(), 'undone' => 0 );
		foreach ( $plan['items'] as $item ) $batch['items'][ (int) $item['id'] ] = array( 'path' => $item['path'], 'title' => $item['title'], 'noindex' => (string) get_post_meta( (int) $item['id'], 'digitalisimo_seo_noindex', true ), 'rule' => 0 );
		// El respaldo se escribe UNA vez y ANTES de crear nada: si PHP se corta a mitad, «Deshacer» encuentra las reglas
		// por su ruta (origen «cleanup», creadas desde la hora del lote) aunque aún no tengan su id anotado.
		self::store( $batch );
		foreach ( $batch['items'] as $id => $item ) {
			if ( 'noindex' !== $plan['kind'] ) {
				$rule = Digitalisimo_Integrations_Redirections::save( array( 'sources' => array( array( 'pattern' => $item['path'], 'comparison' => 'exact' ) ), 'url_to' => '301' === $plan['kind'] ? $plan['target']['url'] : '', 'header_code' => (int) $plan['kind'], 'origin' => 'cleanup', 'category' => 'Limpieza SEO' ), 0, false );
				if ( is_wp_error( $rule ) ) { $batch['items'][ $id ]['error'] = $rule->get_error_message(); continue; }
				$batch['items'][ $id ]['rule'] = (int) $rule;
			}
			update_post_meta( $id, 'digitalisimo_seo_noindex', '1' );
		}
		if ( 'noindex' !== $plan['kind'] ) Digitalisimo_Integrations_Redirections::refresh_index();
		self::$rules = null;
		self::store( $batch ); // Una segunda y última escritura: los ids de las reglas y los errores.
		self::analysis_changed();
		return $batch['id'];
	}

	/** Guarda (o reemplaza) un lote en el respaldo. */
	private static function store( array $batch ) {
		$log = self::log_entries();
		foreach ( $log as $i => $row ) if ( ( $row['id'] ?? '' ) === $batch['id'] ) { $log[ $i ] = $batch; self::save_log( $log ); return; }
		$log[] = $batch;
		self::save_log( $log );
	}

	/**
	 * Deshace un lote: borra sólo las reglas que creó (y que siguen siendo de la limpieza) y repone la marca noindex que
	 * tenía cada página, pero sólo si sigue como la dejó el lote: con dos lotes sobre la misma página, deshacerlos en
	 * cualquier orden la deja como estaba antes de los dos.
	 */
	public static function undo( $batch_id ) {
		global $wpdb;
		$log = self::log_entries();
		foreach ( $log as $i => $batch ) {
			if ( ( $batch['id'] ?? '' ) !== $batch_id || ! empty( $batch['undone'] ) ) continue;
			$rules = false;
			// Reglas de la limpieza creadas desde la hora del lote, por la firma de su origen: las de un lote cortado a mitad
			// (sin id anotado) también se encuentran.
			$since   = gmdate( 'Y-m-d H:i:s', (int) $batch['time'] - 1 );
			$by_path = array();
			if ( in_array( $batch['kind'] ?? '', array( '301', '410' ), true ) ) {
				Digitalisimo_Integrations_Redirections::maybe_install();
				foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT id, sources, created FROM ' . Digitalisimo_Integrations_Redirections::table() . " WHERE origin = 'cleanup' AND created >= %s", $since ), ARRAY_A ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					foreach ( (array) json_decode( (string) $row['sources'], true ) as $source ) if ( is_array( $source ) ) $by_path[ Digitalisimo_Integrations_Redirections::signature( $source ) ] = (int) $row['id'];
				}
			}
			foreach ( (array) $batch['items'] as $id => $item ) {
				$rule = (int) ( $item['rule'] ?? 0 );
				if ( ! $rule ) $rule = (int) ( $by_path[ Digitalisimo_Integrations_Redirections::signature( array( 'pattern' => (string) $item['path'], 'comparison' => 'exact', 'ignore' => '' ) ) ] ?? 0 );
				if ( $rule ) {
					$row = Digitalisimo_Integrations_Redirections::get( $rule );
					if ( $row && 'cleanup' === ( $row['origin'] ?? '' ) ) { $wpdb->delete( Digitalisimo_Integrations_Redirections::table(), array( 'id' => $rule ) ); $rules = true; }
				}
				if ( '1' !== (string) get_post_meta( (int) $id, 'digitalisimo_seo_noindex', true ) ) continue; // Otro lote o el dueño ya la cambió.
				if ( '' === (string) $item['noindex'] ) delete_post_meta( (int) $id, 'digitalisimo_seo_noindex' );
				else update_post_meta( (int) $id, 'digitalisimo_seo_noindex', (string) $item['noindex'] );
			}
			if ( $rules ) Digitalisimo_Integrations_Redirections::refresh_index();
			$log[ $i ]['undone'] = time();
			self::save_log( $log );
			self::analysis_changed();
			return true;
		}
		return false;
	}

	/** Contenidos que un lote vigente redirigió (301 o 410): ya no se sirven, el análisis no los cuenta. */
	public static function retired_ids() {
		$out = array();
		foreach ( self::log_entries() as $batch ) if ( empty( $batch['undone'] ) && in_array( $batch['kind'] ?? '', array( '301', '410' ), true ) ) foreach ( (array) $batch['items'] as $id => $item ) if ( empty( $item['error'] ) ) $out[] = (int) $id;
		return $out;
	}

	private static function analysis_changed() {
		$state = Digitalisimo_Integrations_Site_Analysis::state();
		if ( empty( $state['version'] ) ) return;
		$state['dirty'] = 1;
		update_option( Digitalisimo_Integrations_Site_Analysis::STATE, $state, false );
		if ( ! wp_next_scheduled( Digitalisimo_Integrations_Site_Analysis::CRON ) ) wp_schedule_single_event( time() + 10, Digitalisimo_Integrations_Site_Analysis::CRON );
	}

	/* ------------------------------------------------------------------ */
	/* Peticiones.                                                          */
	/* ------------------------------------------------------------------ */

	private static function back( array $args ) { wp_safe_redirect( Digitalisimo_Integrations_Site_Analysis::url( $args ) ); exit; }

	/** El motivo de un error viaja en un transitorio del usuario, nunca en la URL (que cualquiera podría armar). */
	private static function fail( $message, array $args = array() ) {
		set_transient( self::PLAN . 'msg_' . get_current_user_id(), (string) $message, 5 * MINUTE_IN_SECONDS );
		self::back( $args + array( 'cleanup_error' => 1 ) );
	}

	public static function handle_preview() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'digitalisimo_seo_cleanup_preview' ) ) wp_die( 'No autorizado.' );
		$ids  = array_map( 'absint', (array) wp_unslash( $_POST['ids'] ?? array() ) );
		$plan = self::plan( sanitize_key( wp_unslash( $_POST['kind'] ?? '' ) ), $ids, sanitize_text_field( wp_unslash( $_POST['target'] ?? '' ) ), sanitize_key( wp_unslash( $_POST['source'] ?? '' ) ) );
		if ( is_wp_error( $plan ) ) self::fail( $plan->get_error_message() );
		if ( ! $plan['items'] ) self::fail( $plan['skipped'] ? 'Ninguna de las páginas elegidas se puede cambiar: ' . implode( '; ', array_map( function( $s ) { return get_the_title( $s[0] ) . ' (' . $s[1] . ')'; }, array_slice( $plan['skipped'], 0, 5 ) ) ) . '.' : 'No elegiste ninguna página.' );
		$token = substr( md5( wp_generate_password( 20, false ) ), 0, 16 );
		set_transient( self::PLAN . get_current_user_id(), array( 'token' => $token, 'plan' => $plan ), HOUR_IN_SECONDS );
		self::back( array( 'plan' => $token ) );
	}

	public static function handle_apply() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'digitalisimo_seo_cleanup_apply' ) ) wp_die( 'No autorizado.' );
		$saved = get_transient( self::PLAN . get_current_user_id() );
		$token = sanitize_key( wp_unslash( $_POST['plan'] ?? '' ) );
		if ( ! is_array( $saved ) || '' === $token || ! hash_equals( (string) $saved['token'], $token ) ) self::fail( 'La revisión venció o ya se aplicó. Vuelve a elegir las páginas.' );
		$old = $saved['plan'];
		// Se vuelve a comprobar todo: entre la revisión y la confirmación pudo cambiar algo.
		$plan = self::plan( $old['kind'], (array) $old['ids'], (string) $old['target_raw'], (string) $old['source'] );
		if ( is_wp_error( $plan ) ) self::fail( $plan->get_error_message() );
		if ( $plan['over_cap'] && empty( $_POST['confirm_cap'] ) ) self::fail( 'Son más páginas de las que se cambian sin una confirmación extra: marca la casilla del tope.', array( 'plan' => $token ) );
		if ( ! $plan['items'] ) self::fail( 'Ya no queda ninguna página que cambiar.' );
		delete_transient( self::PLAN . get_current_user_id() );
		$id = self::apply( $plan );
		self::back( array( 'cleanup_done' => count( $plan['items'] ), 'batch' => $id ) );
	}

	public static function handle_undo() {
		$id = sanitize_key( wp_unslash( $_GET['batch'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- se verifica abajo.
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'digitalisimo_seo_cleanup_undo_' . $id ) ) wp_die( 'No autorizado.' );
		if ( ! self::undo( $id ) ) self::fail( 'Ese cambio ya se había deshecho o no existe.' );
		self::back( array( 'cleanup_undone' => 1 ) );
	}

	/* ------------------------------------------------------------------ */
	/* Pantalla.                                                            */
	/* ------------------------------------------------------------------ */

	public static function render_messages() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- sólo mensajes.
		if ( isset( $_GET['cleanup_error'] ) ) {
			$message = get_transient( self::PLAN . 'msg_' . get_current_user_id() );
			delete_transient( self::PLAN . 'msg_' . get_current_user_id() );
			if ( is_string( $message ) && '' !== $message ) echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
		}
		if ( isset( $_GET['cleanup_done'] ) ) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( 'Listo: se aplicó a %d páginas. El respaldo quedó en «Cambios aplicados», abajo, por si quieres deshacerlo.', absint( $_GET['cleanup_done'] ) ) ) . '</p></div>';
		if ( isset( $_GET['cleanup_undone'] ) ) echo '<div class="notice notice-success is-dismissible"><p>Deshecho: las páginas quedaron como estaban.</p></div>';
		// phpcs:enable
	}

	/** Pantalla de confirmación del plan guardado. */
	public static function render_plan() {
		$token = sanitize_key( wp_unslash( $_GET['plan'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' === $token ) return;
		$saved = get_transient( self::PLAN . get_current_user_id() );
		if ( ! is_array( $saved ) || ! hash_equals( (string) $saved['token'], $token ) ) { echo '<div class="notice notice-warning inline"><p>Esta revisión venció o ya se aplicó.</p></div>'; return; }
		$plan  = $saved['plan'];
		$names = array( 'noindex' => 'Sacar del índice (noindex)', '410' => 'Responder 410 (ya no existe)', '301' => 'Redirigir con 301' );
		echo '<div class="digitalisimo-card" style="border:2px solid var(--digi-blue,#245eff);border-radius:10px;padding:14px;margin:12px 0"><h3 style="margin-top:0">Confirma el cambio: ' . esc_html( $names[ $plan['kind'] ] ) . '</h3>';
		if ( '301' === $plan['kind'] ) echo '<p>Se quedan los visitantes y el posicionamiento en <a href="' . esc_url( $plan['target']['url'] ) . '" target="_blank" rel="noopener">' . esc_html( get_the_title( $plan['target']['id'] ) ) . '</a> <code>' . esc_html( (string) wp_parse_url( $plan['target']['url'], PHP_URL_PATH ) ) . '</code>.</p>';
		if ( 'noindex' !== $plan['kind'] && ! Digitalisimo_Integrations_SEO_Resolver::option( 'seo_redirect_enabled', 1 ) ) echo '<div class="notice notice-warning inline"><p>Las redirecciones están apagadas en SEO → Redirecciones: las reglas se guardan, pero no se aplican hasta que las enciendas.</p></div>';
		echo '<table class="widefat striped"><thead><tr><th>Página</th><th>Qué pasará</th></tr></thead><tbody>';
		foreach ( $plan['items'] as $item ) echo '<tr><td>' . esc_html( $item['title'] ) . '<br><code>/' . esc_html( $item['path'] ) . '/</code></td><td>' . esc_html( self::describe( $plan ) ) . '</td></tr>';
		echo '</tbody></table>';
		if ( $plan['skipped'] ) { echo '<p><strong>Se omiten:</strong></p><ul style="list-style:disc;padding-left:1.5em">'; foreach ( $plan['skipped'] as $s ) echo '<li>' . esc_html( ( get_the_title( $s[0] ) ?: '#' . (int) $s[0] ) . ': ' . $s[1] ) . '</li>'; echo '</ul>'; }
		echo '<p>Antes de aplicar se guarda un respaldo de cada página (su marca noindex y las reglas que se crean) y podrás deshacerlo desde «Cambios aplicados». Ningún contenido se borra ni se despublica.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="digitalisimo_seo_cleanup_apply"><input type="hidden" name="plan" value="' . esc_attr( $token ) . '">';
		wp_nonce_field( 'digitalisimo_seo_cleanup_apply' );
		if ( $plan['over_cap'] ) echo '<p><label><input type="checkbox" name="confirm_cap" value="1" required> ' . esc_html( sprintf( 'Son %1$d de %2$d páginas publicadas, más del tope de seguridad (20 páginas o el 10 %%). Revisé la lista y quiero aplicarlo a todas.', count( $plan['items'] ), (int) $plan['total'] ) ) . '</label></p>';
		submit_button( sprintf( 'Confirmar y aplicar a %d páginas', count( $plan['items'] ) ), 'primary', 'submit', false );
		echo ' <a class="button" href="' . esc_url( Digitalisimo_Integrations_Site_Analysis::url() ) . '">Cancelar</a></form></div>';
	}

	/** Respaldo de los cambios aplicados, con «Deshacer». */
	public static function render_log() {
		$log = array_reverse( self::log_entries() );
		echo '<h3>Cambios aplicados (' . count( $log ) . ')</h3>';
		if ( ! $log ) { echo '<p class="description">Todavía no se aplicó ningún cambio desde este análisis.</p>'; return; }
		$names = array( 'noindex' => 'noindex', '410' => '410', '301' => '301' );
		echo '<table class="widefat striped"><thead><tr><th>Fecha</th><th>Quién</th><th>Acción</th><th>Páginas</th><th></th></tr></thead><tbody>';
		foreach ( $log as $batch ) {
			$user  = get_userdata( (int) ( $batch['user'] ?? 0 ) );
			$paths = array_map( function( $item ) { return '/' . $item['path'] . '/' . ( empty( $item['error'] ) ? '' : ' (no se aplicó: ' . $item['error'] . ')' ); }, (array) $batch['items'] );
			$undo  = empty( $batch['undone'] ) ? '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=digitalisimo_seo_cleanup_undo&batch=' . rawurlencode( (string) $batch['id'] ) ), 'digitalisimo_seo_cleanup_undo_' . $batch['id'] ) ) . '" onclick="return confirm(\'¿Deshacer este cambio? Las páginas vuelven a como estaban.\')">Deshacer</a>' : esc_html( 'Deshecho el ' . wp_date( 'Y-m-d H:i', (int) $batch['undone'] ) );
			echo '<tr><td>' . esc_html( wp_date( 'Y-m-d H:i', (int) $batch['time'] ) ) . '</td><td>' . esc_html( $user ? $user->display_name : '—' ) . '</td><td>' . esc_html( ( $names[ $batch['kind'] ] ?? $batch['kind'] ) . ( '' !== (string) $batch['target'] ? ' → ' . wp_parse_url( (string) $batch['target'], PHP_URL_PATH ) : '' ) ) . '</td><td><details><summary>' . count( $paths ) . '</summary><code>' . implode( '</code><br><code>', array_map( 'esc_html', $paths ) ) . '</code></details></td><td>' . $undo . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</tbody></table>';
	}
}
