<?php
namespace Digitalisimo\Tools\Php_Snippets;
defined( 'ABSPATH' ) || exit;

/**
 * Importación de fragmentos desactivados y lectura para otros módulos (§8), sin interfaz propia.
 *
 *     $r = apply_filters( 'digitalisimo_tools_php_import', null, $items, $context );
 *     // null → Tools no está activo; array( '<source:id>' => 'created'|'updated'|'unchanged'|'conflict'|'skipped' )
 *
 * Reglas que impone Tools, diga lo que diga el emisor:
 * - siempre de sitio, inactivo y con revisión obligatoria (cualquier `status` que llegue se ignora);
 * - `can_code` del autor y del último editor lo calcula Tools (edit_themes + unfiltered_html, el nivel que exige para editar);
 * - los metadatos se sanean y nunca se insertan en el código: sólo se pintan, escapados; si los crudos traen salto de línea,
 *   `?>`, `<?` o `*` seguido de `/`, el fragmento lleva una nota para revisarlo línea por línea;
 * - idempotencia por `source:id` (mismo hash → unchanged; origen nuevo con el fragmento intacto e inactivo → updated; si no,
 *   conflict, y el original nuevo entra al historial como «Nueva versión del origen»); lo eliminado queda en `gone` → skipped;
 * - topes: 500 elementos y 64 KB por elemento.
 *
 * `digitalisimo_tools_php_ready` se dispara una vez por sitio, al crear su índice, para que un emisor reenvíe lo pendiente:
 * sólo en una petición del escritorio (no AJAX) de alguien que puede detener los fragmentos, para que el emisor importe con un
 * usuario real. La marca `digitalisimo_tools_php_index_ready` (con autoload) evita cualquier consulta en las demás peticiones.
 * `digitalisimo_tools_php_sources` añade { scope, id, title, active, hash } y `code` sólo con can_edit del ámbito, en cron o
 * en WP-CLI.
 */
final class Import {
	const FILTER  = 'digitalisimo_tools_php_import';
	const SOURCES = 'digitalisimo_tools_php_sources';
	const READY   = 'digitalisimo_tools_php_ready';
	/** Activar o desactivar un importado del tema hijo desde Migración (ver activate_import()). */
	const ACTIVATE   = 'digitalisimo_tools_php_activate_import';
	const DEACTIVATE = 'digitalisimo_tools_php_deactivate_import';
	/** Único origen que puede activarse sin la casilla: el functions.php del tema hijo que traslada el tema propio. */
	const CHILD_SOURCE = 'digitalisimo-theme';
	/** Marca con autoload: el índice del sitio ya existe y `…_ready` ya se disparó. */
	const READY_FLAG = 'digitalisimo_tools_php_index_ready';
	const SUSPICIOUS_NOTE = 'Los datos de origen contienen caracteres que podrían alterar el código generado; revisa el código línea por línea.';
	const HOOK_PATTERN    = '/^[A-Za-z0-9_\-\.\/]{1,100}$/D';
	const MAX_NOTES       = 20;

	public static function init() {
		add_filter( self::FILTER, array( __CLASS__, 'import' ), 10, 3 );
		add_filter( self::SOURCES, array( __CLASS__, 'sources' ), 10, 2 );
		add_filter( self::ACTIVATE, array( __CLASS__, 'activate_import' ), 10, 3 );
		add_filter( self::DEACTIVATE, array( __CLASS__, 'deactivate_import' ), 10, 3 );
		add_action( 'admin_init', array( __CLASS__, 'ready' ) );
	}

	/**
	 * Crea el índice del sitio la primera vez y avisa a los emisores (una vez por sitio). Sólo en el escritorio, fuera de
	 * AJAX y con can_stop: un visitante anónimo de admin-ajax.php no lo dispara ni provoca ninguna consulta (la marca va con
	 * autoload).
	 */
	public static function ready() {
		if ( ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) || ! is_user_logged_in() || ! Access::can_stop() ) return false;
		$all = wp_load_alloptions();
		if ( isset( $all[ self::READY_FLAG ] ) ) return false;
		$created = Store::mutate( 'site', static function () {
			$new = false === Store::get( 'site', Store::SITE_INDEX, false );
			if ( $new ) update_option( Store::SITE_INDEX, Store::normalize_index( array() ), false );
			update_option( self::READY_FLAG, '1', true );
			return $new;
		} );
		if ( true !== $created ) return false;
		do_action( self::READY );
		return true;
	}

	/**
	 * Filtro de importación.
	 *
	 * @param mixed $result  Lo que devolvió otro oyente (null si ninguno).
	 * @param array $items   [ { title, code, original, origin:{ source, version, id, site, hook, hash, author_id, last_editor_id,
	 *                       executed_in_origin, reason }, notes[] } ]
	 * @param array $context Datos del emisor (no se usan para decidir nada).
	 * @return array '<source:id>' → resultado.
	 */
	public static function import( $result, $items, $context = array() ) {
		unset( $context );
		if ( ! is_array( $items ) ) return $result;
		$out   = is_array( $result ) ? $result : array();
		$by    = ( ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) ? 0 : (int) get_current_user_id();
		$count = 0;
		foreach ( $items as $raw ) {
			if ( ! is_array( $raw ) ) continue;
			$origin = is_array( $raw['origin'] ?? null ) ? $raw['origin'] : array();
			$source = self::text( $origin['source'] ?? '', 60 );
			$oid    = self::text( $origin['id'] ?? '', 60 );
			if ( '' === $source || '' === $oid ) continue;
			$key = $source . ':' . $oid;
			if ( ++$count > Store::MAX_ITEMS ) {
				$out[ $key ] = 'skipped';
				continue;
			}
			$out[ $key ] = self::one( $key, $raw, $origin, $by );
		}
		return $out;
	}

	/**
	 * Activa, desde «Migrar» de DIGITALÍSIMO Migración, el functions.php del tema hijo que importó el tema propio
	 * (`digitalisimo-theme:<carpeta del hijo>`), sin la casilla «Revisé este código». Regla del propietario del 2026-11-20
	 * («quedamos que migrar debe hacer todo, haz que haga todo; solo quiero migrar y que quede todo perfecto con tecnología
	 * DIGITALÍSIMO plugins»): no es código nuevo, es el que ya corría en el sitio a través del hijo, y va dentro de la
	 * condición que sólo lo deja correr con el tema propio activo. Sólo si el código es exactamente el que se importó
	 * (`origin.code_hash`: nadie lo editó ni hay conflicto), la revisión sin ejecutar no lo bloquea, la edición no está
	 * cerrada en el servidor (DISALLOW_FILE_EDIT) ni en modo seguro, y cabe en los topes. Lo firma y lo activa
	 * Store::activate(), el mismo paso final de «Guardar y activar»; desde ahí lo vigila el guardián como a cualquier otro
	 * (un error fatal atribuido lo desactiva). La prueba con el código corriendo es la de Migrar: la primera petición con
	 * el tema propio no puede dar un 500 ni un error de PHP, el fragmento debe seguir activo y la portada pintarse igual;
	 * si no, Migrar vuelve al hijo y lo desactiva (deactivate_import()). Quién puede hacerlo lo decide Migrar (edit_themes
	 * + unfiltered_html de quien pulsó). Devuelve 'activated', 'active' (ese código ya estaba activo) o el motivo:
	 * 'missing', 'changed', 'mismatch' (no es el functions.php que hay en disco), 'signature' (el borrador no lleva la firma de
	 * la importación), 'failed', 'blocked', 'reduced', 'check', 'error'. Sólo activa un importado en «sin revisar»: uno
	 * que alguien (o el guardián, o la vuelta atrás de Migrar) desactivó no se vuelve a activar solo.
	 *
	 * @param mixed  $result  Lo que devolvió otro oyente.
	 * @param string $key     `digitalisimo-theme:<carpeta del hijo>`.
	 * @param array  $context { by: usuario que pulsó Migrar, hash: sha256 del functions.php del hijo envuelto, leído ahora del disco }
	 */
	public static function activate_import( $result, $key, $context = array() ) {
		unset( $result );
		$key = (string) $key;
		if ( 0 !== strpos( $key, self::CHILD_SOURCE . ':' ) ) return 'missing';
		if ( Access::editing_blocked() ) return 'blocked';
		if ( Access::reduced() ) return 'reduced';
		$by    = absint( is_array( $context ) ? ( $context['by'] ?? 0 ) : 0 );
		$want  = is_array( $context ) ? (string) ( $context['hash'] ?? '' ) : '';
		$found = self::find( $key );
		if ( '' === $found ) return 'missing';
		$index = Store::index( 'site' );
		$item  = Store::item( 'site', $found );
		$meta  = $index['items'][ $found ];
		if ( ! $item || ! empty( $meta['conflict'] ) ) return 'changed';
		$hash = hash( 'sha256', $item['code'] );
		// El código debe ser el functions.php del hijo tal como está ahora en disco (Migrar lo recalcula y pasa su hash).
		if ( 1 !== preg_match( '/^[0-9a-f]{64}$/D', $want ) || ! hash_equals( $want, $hash ) ) return 'mismatch';
		$run   = Store::run( 'site' );
		$state = Store::state( 'site', $found, $index, $run );
		// Ya activo con ese código y por Migrar (la marca `migration`): una pasada que se repite lo reconoce como suyo.
		if ( 'active' === $state && hash_equals( (string) $run['items'][ $found ]['hash'], $hash ) ) return hash_equals( (string) ( $meta['migration'] ?? '' ), $hash ) ? 'active' : 'changed';
		// Sólo un importado que nadie revisó ni tocó: uno que se desactivó (a mano o por el guardián) o que tuvo un error se
		// queda como está; activarlo es de una persona.
		if ( 'review' !== $state ) return 'failed' === $state ? 'failed' : 'changed';
		// El borrador tiene que llevar la firma que le puso la importación (`origin`): uno cambiado en la base de datos
		// (`database`, `keys`) o guardado desde el editor no se activa sin la casilla.
		if ( 'origin' !== Store::draft_status( 'site', $found, $item ) ) return 'signature';
		$check = Tester::check( $item['code'] );
		if ( empty( $check['ok'] ) ) return 'check';
		Store::set_check( 'site', $found, $check );
		$done = Store::activate( 'site', $found, $hash, array( 'by' => $by ) );
		if ( is_wp_error( $done ) ) return 'error';
		self::mark( $found, $hash );
		return 'activated';
	}

	/**
	 * Desactiva el importado `digitalisimo-theme:<carpeta del hijo>` si sigue activo con el código que activó Migrar
	 * ($context['hash']; sin él, cualquiera). Lo usa la vuelta atrás de Migrar. true si quedó inactivo; false si no existe
	 * o alguien lo cambió (entonces no se toca).
	 */
	public static function deactivate_import( $result, $key, $context = array() ) {
		unset( $result );
		$found = 0 === strpos( (string) $key, self::CHILD_SOURCE . ':' ) ? self::find( (string) $key ) : '';
		if ( '' === $found ) return false;
		$run  = Store::run( 'site' );
		$want = is_array( $context ) ? (string) ( $context['hash'] ?? '' ) : '';
		if ( ! isset( $run['items'][ $found ] ) ) return true;
		if ( '' !== $want && ! hash_equals( $want, (string) $run['items'][ $found ]['hash'] ) ) return false;
		return true === Store::deactivate( 'site', $found, absint( is_array( $context ) ? ( $context['by'] ?? 0 ) : 0 ) );
	}

	/** Anota en el índice que Migrar activó ese código (`migration` = su hash); '' la quita. */
	private static function mark( $id, $hash ) {
		Store::mutate( 'site', static function () use ( $id, $hash ) {
			$index = Store::index( 'site' );
			if ( ! isset( $index['items'][ $id ] ) ) return false;
			if ( '' === $hash ) {
				unset( $index['items'][ $id ]['migration'] );
			} else {
				$index['items'][ $id ]['migration'] = $hash;
			}
			update_option( Store::SITE_INDEX, $index, false );
			return true;
		} );
	}

	/** Id del fragmento de sitio importado con ese `source:id` ('' si no hay). */
	private static function find( $key ) {
		foreach ( Store::index( 'site' )['items'] as $id => $meta ) {
			if ( (string) ( $meta['origin_key'] ?? '' ) === (string) $key ) return (string) $id;
		}
		return '';
	}

	/** Importa un elemento. */
	private static function one( $key, array $raw, array $origin_raw, $by ) {
		$code = Store::normalize_code( is_string( $raw['code'] ?? null ) ? $raw['code'] : '' );
		if ( '' === trim( $code ) || strlen( $code ) > Store::MAX_CODE ) return 'skipped';
		$title = self::text( $raw['title'] ?? '', Store::MAX_TITLE );
		if ( '' === $title ) $title = 'Importado de ' . self::text( $origin_raw['source'] ?? '', 60 );
		$origin = self::origin( $origin_raw, $raw, $code, $by );
		$check  = Tester::check( $code );
		$done   = Store::mutate( 'site', static function () use ( $key, $title, $code, $origin, $check, $by ) {
			$index = Store::index( 'site' );
			if ( isset( $index['gone'][ $key ] ) ) return 'skipped';
			$found = '';
			foreach ( $index['items'] as $id => $meta ) {
				if ( $meta['origin_key'] === $key ) {
					$found = (string) $id;
					break;
				}
			}
			$hash = hash( 'sha256', $code );
			if ( '' === $found ) {
				$id = Store::create( 'site', $title, $code, $by, array( 'index' => array( 'origin_key' => $key, 'review' => true ), 'item' => array( 'origin' => $origin, 'check' => $check ), 'event' => 'origin', 'log' => false ) );
				if ( is_wp_error( $id ) ) return 'skipped';
				Store::log( 'site', 'imported', array( 'id' => $id, 'hash' => $hash, 'user' => $by, 'msg' => $key ) );
				return 'created';
			}
			$item = Store::item( 'site', $found );
			if ( ! $item ) return 'skipped';
			$old = $item['origin'];
			if ( hash_equals( (string) ( $old['hash'] ?? '' ), $origin['hash'] ) ) return 'unchanged';
			$run    = Store::run( 'site' );
			$intact = '' !== (string) ( $old['code_hash'] ?? '' ) && hash_equals( (string) $old['code_hash'], hash( 'sha256', $item['code'] ) ) && ! isset( $run['items'][ $found ] );
			$meta   = $index['items'][ $found ];
			if ( $intact ) {
				$item['code']    = $code;
				$item['origin']  = $origin;
				$item['check']   = $check;
				$item['history'] = Store::remember( 'site', $found, $item['history'], array( 'code' => $code, 'hash' => $hash, 'by' => $by, 'event' => 'origin' ) );
				$item            = Store::sign_draft( 'site', $found, $item, 'origin' );
				$meta            = array_merge( $meta, array( 'title' => $title, 'status' => 'inactive', 'draft_hash' => $hash, 'review' => true, 'conflict' => false, 'modified' => time(), 'by' => $by ) );
				unset( $meta['error'] );
				$event  = 'origin_changed';
				$status = 'updated';
			} else {
				// El código guardado no se toca: el original nuevo entra al historial como «Nueva versión del origen».
				$item['history'] = Store::remember( 'site', $found, $item['history'], array( 'code' => $code, 'hash' => $hash, 'by' => $by, 'event' => 'origin' ) );
				$item['origin']  = array_merge( $origin, array( 'code_hash' => (string) ( $old['code_hash'] ?? '' ) ) );
				$meta            = array_merge( $meta, array( 'review' => true, 'conflict' => true, 'modified' => time() ) );
				$event  = 'conflict';
				$status = 'conflict';
			}
			$index['items'][ $found ] = $meta;
			update_option( Store::option_name( 'site', 'item', $found ), $item, false );
			update_option( Store::SITE_INDEX, $index, false );
			Store::log( 'site', $event, array( 'id' => $found, 'hash' => $hash, 'user' => $by, 'msg' => $key ) );
			return $status;
		} );
		return is_string( $done ) ? $done : 'skipped';
	}

	/** Origen saneado, con `can_code` calculado por Tools, notas, el original y el hash del código generado. */
	private static function origin( array $raw, array $item, $code, $by ) {
		$author = absint( $raw['author_id'] ?? 0 );
		$editor = absint( $raw['last_editor_id'] ?? 0 );
		$hook   = is_string( $raw['hook'] ?? null ) && preg_match( self::HOOK_PATTERN, $raw['hook'] ) ? $raw['hook'] : '';
		$orig   = is_string( $item['original'] ?? null ) ? $item['original'] : '';
		if ( strlen( $orig ) > Store::MAX_SAVE ) $orig = substr( $orig, 0, Store::MAX_SAVE );
		$hash = is_string( $raw['hash'] ?? null ) && preg_match( '/^[A-Za-z0-9:_\-]{8,128}$/D', $raw['hash'] ) ? $raw['hash'] : hash( 'sha256', $orig . "\0" . $code );
		$notes = array();
		foreach ( array_slice( is_array( $item['notes'] ?? null ) ? $item['notes'] : array(), 0, self::MAX_NOTES ) as $note ) {
			$note = self::text( $note, 300 );
			if ( '' !== $note ) $notes[] = $note;
		}
		if ( self::suspicious( $raw ) || self::suspicious( array( $item['title'] ?? '' ) ) ) array_unshift( $notes, self::SUSPICIOUS_NOTE );
		return array(
			'source'             => self::text( $raw['source'] ?? '', 60 ),
			'version'            => self::text( $raw['version'] ?? '', 40 ),
			'id'                 => self::text( $raw['id'] ?? '', 60 ),
			'site'               => self::text( $raw['site'] ?? '', 200 ),
			'hook'               => $hook,
			'hash'               => $hash,
			'author_id'          => $author,
			'last_editor_id'     => $editor,
			'author'             => self::person( $author ),
			'last_editor'        => self::person( $editor ),
			'executed_in_origin' => ! empty( $raw['executed_in_origin'] ),
			'reason'             => self::text( $raw['reason'] ?? '', 300 ),
			'by'                 => (int) $by,
			'at'                 => time(),
			'notes'              => $notes,
			'original'           => $orig,
			'code_hash'          => hash( 'sha256', $code ),
		);
	}

	/** { name, role, can_code } de un usuario del sitio en el momento de importar. */
	private static function person( $uid ) {
		$user = $uid ? get_userdata( $uid ) : false;
		$roles = $user && isset( $user->roles ) ? (array) $user->roles : array();
		return array(
			'name'     => $user ? self::text( $user->display_name ?? $user->user_login ?? '', 100 ) : ( $uid ? '#' . $uid : '' ),
			'role'     => self::text( (string) reset( $roles ), 60 ),
			'can_code' => $uid > 0 && user_can( $uid, 'edit_themes' ) && user_can( $uid, 'unfiltered_html' ),
		);
	}

	/** Si algún dato crudo trae salto de línea, `?>`, `<?` o el cierre de un comentario. */
	private static function suspicious( array $values ) {
		foreach ( $values as $value ) {
			if ( is_array( $value ) ) {
				if ( self::suspicious( $value ) ) return true;
				continue;
			}
			if ( ! is_scalar( $value ) ) continue;
			$value = (string) $value;
			if ( false !== strpbrk( $value, "\r\n" ) || false !== strpos( $value, '?>' ) || false !== strpos( $value, '<?' ) || false !== strpos( $value, '*/' ) ) return true;
		}
		return false;
	}

	private static function text( $value, $max ) {
		if ( ! is_scalar( $value ) ) return '';
		$value = sanitize_text_field( (string) $value );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
	}

	/**
	 * Lectura para otros módulos (SEO, Migración): { scope, id, title, active, hash } y `code` sólo con can_edit del ámbito,
	 * en cron o en WP-CLI. Quien lo consume nunca pinta el código.
	 *
	 * @param array $sources Lo que ya añadieron otros oyentes.
	 * @param array $args    { scope: 'site'|'network'|[ … ] } para limitar los ámbitos.
	 */
	public static function sources( $sources, $args = array() ) {
		$sources = is_array( $sources ) ? $sources : array();
		$scopes  = is_multisite() ? array( 'network', 'site' ) : array( 'site' );
		if ( is_array( $args ) && isset( $args['scope'] ) ) $scopes = array_values( array_intersect( $scopes, (array) $args['scope'] ) );
		$trusted = ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) || ( defined( 'WP_CLI' ) && WP_CLI );
		foreach ( $scopes as $scope ) {
			$with_code = $trusted || Access::can_edit( $scope );
			$index     = Store::index( $scope );
			$run       = Store::run( $scope );
			foreach ( $index['items'] as $id => $meta ) {
				$id     = (string) $id;
				$state  = Store::state( $scope, $id, $index, $run );
				$active = in_array( $state, array( 'active', 'changed' ), true );
				$row    = array( 'scope' => $scope, 'id' => (string) $id, 'title' => $meta['title'], 'active' => $active, 'hash' => $active ? $run['items'][ $id ]['hash'] : $meta['draft_hash'] );
				if ( $with_code ) {
					if ( $active ) {
						$row['code'] = $run['items'][ $id ]['code'];
					} else {
						$item        = Store::item( $scope, $id );
						$row['code'] = $item ? $item['code'] : '';
					}
				}
				$sources[] = $row;
			}
		}
		return $sources;
	}
}
