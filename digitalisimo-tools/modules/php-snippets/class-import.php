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
	/** Marca con autoload: el índice del sitio ya existe y `…_ready` ya se disparó. */
	const READY_FLAG = 'digitalisimo_tools_php_index_ready';
	const SUSPICIOUS_NOTE = 'Los datos de origen contienen caracteres que podrían alterar el código generado; revisa el código línea por línea.';
	const HOOK_PATTERN    = '/^[A-Za-z0-9_\-\.\/]{1,100}$/D';
	const MAX_NOTES       = 20;

	public static function init() {
		add_filter( self::FILTER, array( __CLASS__, 'import' ), 10, 3 );
		add_filter( self::SOURCES, array( __CLASS__, 'sources' ), 10, 2 );
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
