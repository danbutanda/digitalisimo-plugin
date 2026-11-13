<?php
defined( 'ABSPATH' ) || exit;

/**
 * Quien restaura sigue entrando (incidente del 2026-11-12: el sitio extraído de la red de digitalisimo.mx restaurado en
 * /wp dejó fuera a quien lo restauró: su usuario ya no existía, la contraseña de aplicación de los usuarios del paquete
 * respondía 401 y wp-login.php daba 404 por la ruta privada de acceso de la red).
 *
 * Al restaurar con reubicación (otra URL, otro prefijo u otra ruta), un sitio extraído de una red o una migración:
 *  - **Acceso de quien restaura** (`keep_access`, encendido por defecto): su fila de usuario (hash de la contraseña y
 *    correo), sus sesiones (`session_tokens`) y sus contraseñas de aplicación (`_application_passwords`) se apuntan en el
 *    diario de la restauración antes del cambio de tablas, cifradas con la clave de este servidor (AUTH_KEY y
 *    SECURE_AUTH_KEY, que la restauración nunca cambia porque nunca escribe wp-config.php), y vuelven tras el SQL, también
 *    si el proceso muere entre medias. Sólo esa cuenta, nunca las demás del destino. Si el paquete tiene el mismo usuario,
 *    esa cuenta pasa a ser la suya (mismo ID, así que la autoría no cambia); si no, se crea con un ID libre. Queda como
 *    administradora (y superadministradora en una red). Con el mismo hash, las mismas sesiones y las mismas claves de
 *    wp-config.php, la cookie del navegador sigue valiendo.
 *  - **Ruta de acceso**: la de este sitio (vacía si no tenía), nunca la del respaldo, y el informe dice siempre la URL de
 *    acceso efectiva.
 * En cualquier restauración, si un usuario tiene contraseñas de aplicación, `using_application_passwords` queda activo:
 * es una opción de red (en una red vive en `sitemeta`) y WordPress no comprueba ninguna contraseña de aplicación sin ella
 * (`WP_Application_Passwords::is_in_use()`): un sitio extraído de una red llegaba sin ella y respondía 401
 * `rest_not_logged_in` aunque el hash viajara bien.
 *
 * En el mismo sitio (sin reubicar: p. ej. tras un hackeo) no se conserva nada: las credenciales actuales pueden estar
 * comprometidas y vuelven las del respaldo, como siempre. Nada de esto va a registros ni respuestas: el informe sólo
 * dice nombres de usuario, cuántas contraseñas y la URL de acceso.
 */
final class Digitalisimo_Backups_Restore_Access {
	const FORMAT = 1;
	const SESSIONS = 'session_tokens';
	const APP_PASSWORDS = '_application_passwords';
	const IN_USE = 'using_application_passwords';
	/** Metas de perfil que acompañan a la cuenta de quien restaura cuando hay que crearla. */
	const PROFILE_META = array( 'nickname', 'first_name', 'last_name', 'description', 'locale', 'rich_editing', 'syntax_highlighting', 'comment_shortcuts', 'admin_color', 'use_ssl', 'show_admin_bar_front' );
	const TOOLS_FILE = 'digitalisimo-tools.php';
	const SEO_FILE = 'digitalisimo-integrations.php';
	const TOOLS_SITE = 'digitalisimo_tools_site_options';
	const TOOLS_NETWORK = 'digitalisimo_tools_site_defaults';
	const SEO_OPTION = 'digitalisimo_integrations_options';
	const SEO_KEY = 'seo_hide_login_slug';
	const SEO_INHERIT = 'digitalisimo_seo_network_inherit';
	/**
	 * Las claves y sales que WordPress guarda en la base cuando wp-config.php no las define (wp_salt(): opciones de red,
	 * en un sitio individual las del sitio). Son de esta instalación, como wp-config.php: al restaurar en otro sitio se
	 * conservan, o la cookie de quien restaura y los nonces dejarían de valer.
	 */
	const SALTS = array( 'auth_key', 'auth_salt', 'secure_auth_key', 'secure_auth_salt', 'logged_in_key', 'logged_in_salt', 'nonce_key', 'nonce_salt', 'secret_key' );
	/** Las rutas que Tools y SEO no aceptan (las que se ocultan o reserva WordPress). */
	const RESERVED = array( 'wp-admin', 'wp-login', 'wp-login-php', 'admin', 'dashboard', 'login', 'wp-content', 'wp-includes', 'wp-json', 'wp-signup', 'wp-activate', 'feed', 'robots-txt', 'sitemap', 'wp-sitemap-xml' );
	/** Ficha de lectura de un trabajo: cabecera HTTP (nunca la URL, que queda en los registros del servidor). */
	const READER_HEADER = 'X-Digitalisimo-Backups-Job-Token';
	/** La ficha vale mientras el trabajo sigue y hasta 24 h después de terminar; como mucho 7 días desde que se entregó. */
	const READER_AFTER = 86400;
	const READER_MAX = 604800;

	/* ------------------------------------------------------------------ Decisión */

	/**
	 * Si se conserva el acceso de quien restaura: 'on', o por qué no ('rollback', 'same_site', 'off', 'no_user').
	 * $other_site: el respaldo no es de esta instalación (sin su identidad de H23: otro sitio, un sitio extraído o una
	 * migración); $user_id: quien la lanzó (0 sin usuario, p. ej. WP-CLI sin --user).
	 */
	public static function decide( $keep, $user_id, $other_site, $rollback ) {
		if ( $rollback ) return 'rollback';
		if ( ! $other_site ) return 'same_site';
		if ( ! $keep ) return 'off';
		if ( (int) $user_id <= 0 ) return 'no_user';
		return 'on';
	}

	/* ------------------------------------------------------------------ Antes del cambio de tablas */

	/**
	 * La cuenta de quien restaura en las tablas de este sitio (antes del cambio): su fila, sus metas de credenciales y de
	 * perfil y si es superadministradora. null si no existe. Secreto: sólo va, cifrado, al diario.
	 */
	public static function capture( $user_id ) {
		global $wpdb;
		$users = $wpdb->base_prefix . 'users';
		$meta = $wpdb->base_prefix . 'usermeta';
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::q( $users ) . ' WHERE ID = %d', (int) $user_id ), ARRAY_A );
		if ( ! is_array( $row ) || '' === (string) ( $row['user_login'] ?? '' ) ) return null;
		$keys = array_merge( self::PROFILE_META, array( self::SESSIONS, self::APP_PASSWORDS ) );
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT meta_key, meta_value FROM ' . self::q( $meta ) . ' WHERE user_id = %d AND meta_key IN (' . implode( ', ', array_fill( 0, count( $keys ), '%s' ) ) . ')', array_merge( array( (int) $user_id ), $keys ) ), ARRAY_A );
		$metas = array();
		foreach ( $rows as $item ) $metas[ (string) $item['meta_key'] ] = (string) $item['meta_value'];
		$super = false;
		if ( function_exists( 'is_multisite' ) && is_multisite() ) $super = in_array( (string) $row['user_login'], array_map( 'strval', (array) get_site_option( 'site_admins', array() ) ), true );
		return array( 'format' => self::FORMAT, 'user' => $row, 'meta' => $metas, 'super_admin' => $super );
	}
	/**
	 * La ruta de acceso de este sitio antes del cambio: la efectiva (como la resuelven Tools o SEO, sin la constante de
	 * emergencia, que es de wp-config.php y se queda) y, en una red, los valores de la red.
	 */
	public static function login_capture() {
		$out = array( 'slug' => self::effective_slug( false ), 'network' => null );
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			$tools = get_site_option( self::TOOLS_NETWORK, false );
			$seo = get_site_option( self::SEO_OPTION, false );
			$out['network'] = array( 'tools' => is_array( $tools ) ? (string) ( $tools['login_slug'] ?? '' ) : '', 'seo' => is_array( $seo ) ? (string) ( $seo[ self::SEO_KEY ] ?? '' ) : '' );
		}
		return $out;
	}
	/** Las claves y sales de esta instalación guardadas en la base (ver SALTS): nombre => valor; secreto, sólo al diario. */
	public static function salts_capture() {
		$out = array();
		foreach ( self::SALTS as $name ) {
			$value = get_site_option( $name, '' );
			if ( is_string( $value ) && '' !== $value ) $out[ $name ] = $value;
		}
		return $out;
	}
	/** Para el diario: cifrado con la clave de este servidor; sin claves propias, tal cual (el diario es privado, 0600). */
	public static function seal( array $data ) {
		$json = wp_json_encode( $data );
		if ( ! is_string( $json ) ) return null;
		$key = class_exists( 'Digitalisimo_Backups_Credentials' ) ? Digitalisimo_Backups_Credentials::key() : false;
		if ( false === $key ) return array( 'v' => 0, 'plain' => $data );
		try {
			$box = sodium_crypto_generichash( 'DGBK1/restore-access', $key, 32 );
			$nonce = random_bytes( 24 );
			$out = array( 'v' => 1, 'kw' => Digitalisimo_Backups_Credentials::key_id( $key ), 'box' => Digitalisimo_Backups_Keys::b64url_encode( $nonce . sodium_crypto_aead_xchacha20poly1305_ietf_encrypt( $json, 'DGBK1/restore-access|v1', $nonce, $box ) ) );
			Digitalisimo_Backups_Sodium::memzero( $box );
			return $out;
		} catch ( Throwable $e ) {
			return array( 'v' => 0, 'plain' => $data );
		} finally {
			Digitalisimo_Backups_Sodium::memzero( $key );
		}
	}
	/** Lo sellado, o null si no abre (otra clave o alterado). */
	public static function open( $sealed ) {
		if ( ! is_array( $sealed ) ) return null;
		if ( 0 === ( $sealed['v'] ?? null ) ) return is_array( $sealed['plain'] ?? null ) ? $sealed['plain'] : null;
		if ( 1 !== ( $sealed['v'] ?? null ) ) return null;
		$key = Digitalisimo_Backups_Credentials::key();
		if ( false === $key ) return null;
		$plain = false;
		try {
			$raw = Digitalisimo_Backups_Keys::b64url_decode( $sealed['box'] ?? null );
			if ( false !== $raw && strlen( $raw ) > 40 && hash_equals( Digitalisimo_Backups_Credentials::key_id( $key ), (string) ( $sealed['kw'] ?? '' ) ) ) {
				$box = sodium_crypto_generichash( 'DGBK1/restore-access', $key, 32 );
				$plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt( substr( $raw, 24 ), 'DGBK1/restore-access|v1', substr( $raw, 0, 24 ), $box );
				Digitalisimo_Backups_Sodium::memzero( $box );
			}
		} catch ( Throwable $e ) {
			$plain = false;
		} finally {
			Digitalisimo_Backups_Sodium::memzero( $key );
		}
		$data = is_string( $plain ) ? json_decode( $plain, true ) : null;
		return is_array( $data ) ? $data : null;
	}

	/* ------------------------------------------------------------------ Después del SQL */

	/**
	 * Tras el SQL (en las tablas ya restauradas; idempotente, porque el diario puede aplicarse dos veces si el proceso
	 * muere entre medias): la cuenta de quien restaura, la ruta de acceso de este sitio y las contraseñas de aplicación.
	 * $state es el de decide(); $secret, lo abierto del diario (array( user, login ) o null). Devuelve el informe público.
	 */
	public static function apply( $state, $secret, $relocated, $remember = null ) {
		$report = array( 'state' => (string) $state, 'relocated' => (bool) $relocated, 'user' => '', 'account' => '', 'warnings' => array(), 'app_passwords' => array(), 'login_path' => null, 'login_url' => '', 'admins' => array(), 'admins_total' => 0, 'users_total' => 0, 'text' => '' );
		$user = is_array( $secret ) && is_array( $secret['user'] ?? null ) ? $secret['user'] : null;
		$login = is_array( $secret ) && is_array( $secret['login'] ?? null ) ? $secret['login'] : null;
		if ( 'on' === $state && null === $user ) {
			$report['state'] = 'missing_user';
			$report['warnings'][] = 'No se pudo leer la cuenta de quien restauró antes del cambio: entra con un usuario del respaldo.';
		}
		try {
			if ( 'on' === $state && $user ) {
				// La decisión de una pasada anterior (el diario tras una muerte) manda; si no hay, se toma ahora y se anota
				// antes de la primera escritura (B1).
				$plan = is_array( $secret['plan'] ?? null ) && $user['user']['user_login'] === ( $secret['plan']['login'] ?? null ) ? $secret['plan'] : null;
				if ( null === $plan ) {
					$plan = self::plan_user( $user );
					if ( is_callable( $remember ) ) call_user_func( $remember, $plan );
				}
				self::restore_user( $user, $plan, $report );
			}
		} catch ( Throwable $e ) {
			$report['state'] = 'failed';
			$report['warnings'][] = 'No se pudo conservar la cuenta de quien restauró: ' . $e->getMessage();
		}
		try {
			if ( $login ) self::restore_login_path( $login, $report );
		} catch ( Throwable $e ) {
			$report['warnings'][] = 'No se pudo conservar la ruta de acceso de este sitio: ' . $e->getMessage();
		}
		// Las claves y sales de la base de esta instalación (las de wp-config.php nunca cambian): la cookie de quien
		// restauró y los nonces siguen valiendo; las del origen no sirven aquí.
		$salts = is_array( $secret ) && is_array( $secret['salts'] ?? null ) ? $secret['salts'] : array();
		try {
			foreach ( $salts as $name => $value ) if ( in_array( $name, self::SALTS, true ) && is_string( $value ) && '' !== $value ) update_site_option( $name, $value );
			$report['salts_kept'] = count( $salts );
		} catch ( Throwable $e ) {
			$report['warnings'][] = 'No se pudieron conservar las claves de la base de este sitio: ' . $e->getMessage();
		}
		try {
			$report['app_passwords'] += self::app_passwords_in_use();
		} catch ( Throwable $e ) {
			$report['warnings'][] = 'No se pudo comprobar las contraseñas de aplicación: ' . $e->getMessage();
		}
		if ( function_exists( 'wp_cache_flush' ) ) wp_cache_flush();
		try {
			$report['login_url'] = self::login_url();
			$who = self::who_can_log_in();
			$report['admins'] = $who['admins'];
			$report['admins_total'] = $who['admins_total'];
			$report['users_total'] = $who['users_total'];
		} catch ( Throwable $e ) {
			$report['warnings'][] = 'No se pudo leer quién puede entrar: ' . $e->getMessage();
		}
		$report['text'] = self::text( $report );
		return $report;
	}
	/**
	 * Qué se hará con la cuenta de quien restaura, decidido sobre las tablas restauradas ANTES de cambiar nada (revisión M1,
	 * B1): el paquete tiene una cuenta con el mismo usuario y era administradora (del sitio o superadministradora) →
	 * `merge`, esa cuenta (mismo ID) pasa a ser la de quien restaura; la tiene pero NO era administradora → `rename`, esa
	 * cuenta conserva todo (ID, contraseña, correo, metas, rol, inicio social, segundo factor) y sólo cambia su usuario a uno
	 * libre («<usuario>-respaldo»), y la de quien restaura se crea con el suyo; no la tiene → `create`. También fija el ID
	 * de la cuenta nueva y las contraseñas de aplicación que tendrá. Va al diario (cifrado) antes de la primera escritura:
	 * una segunda pasada tras una muerte sigue la misma decisión y nunca suma contraseñas ajenas.
	 */
	public static function plan_user( array $captured ) {
		global $wpdb;
		$row = (array) ( $captured['user'] ?? array() );
		$login = (string) ( $row['user_login'] ?? '' );
		if ( '' === $login ) throw new Exception( 'la cuenta apuntada no tiene usuario.' );
		$users = $wpdb->base_prefix . 'users';
		$meta = $wpdb->base_prefix . 'usermeta';
		$existing = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::q( $users ) . ' WHERE user_login = %s', $login ), ARRAY_A );
		$mine = array_values( array_filter( self::array_value( (string) ( ( (array) ( $captured['meta'] ?? array() ) )[ self::APP_PASSWORDS ] ?? '' ) ), 'is_array' ) );
		$plan = array( 'format' => self::FORMAT, 'login' => $login, 'mode' => 'create', 'target' => 0, 'renamed' => 0, 'renamed_to' => '', 'was_admin' => false, 'apps' => $mine, 'kept_from_backup' => 0, 'not_admin_apps' => 0 );
		$next = (int) $wpdb->get_var( 'SELECT MAX(ID) FROM ' . self::q( $users ) ) + 1;
		if ( ! is_array( $existing ) ) {
			$plan['target'] = $next;
			return $plan;
		}
		$id = (int) $existing['ID'];
		$caps = self::array_value( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT meta_value FROM ' . self::q( $meta ) . ' WHERE user_id = %d AND meta_key = %s', $id, $wpdb->base_prefix . 'capabilities' ) ) );
		$plan['was_admin'] = ! empty( $caps['administrator'] ) || ( is_multisite() && in_array( $login, array_map( 'strval', (array) get_site_option( 'site_admins', array() ) ), true ) );
		$theirs = array_values( array_filter( self::array_value( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT meta_value FROM ' . self::q( $meta ) . ' WHERE user_id = %d AND meta_key = %s', $id, self::APP_PASSWORDS ) ) ), 'is_array' ) );
		if ( $plan['was_admin'] ) {
			$plan['mode'] = 'merge';
			$plan['target'] = $id;
			$uuids = array();
			foreach ( $mine as $item ) $uuids[ (string) ( $item['uuid'] ?? '' ) ] = true;
			foreach ( $theirs as $item ) if ( ! isset( $uuids[ (string) ( $item['uuid'] ?? '' ) ] ) ) { $plan['apps'][] = $item; $plan['kept_from_backup']++; }
			return $plan;
		}
		$plan['mode'] = 'rename';
		$plan['target'] = $next;
		$plan['renamed'] = $id;
		$plan['renamed_to'] = self::free_login( $login, $users );
		$plan['not_admin_apps'] = count( $theirs );
		return $plan;
	}
	/**
	 * La cuenta de quien restaura según su plan (ver plan_user()), en las tablas restauradas. Cada paso es idempotente y
	 * las contraseñas de aplicación (ya filtradas) se escriben primero.
	 */
	private static function restore_user( array $captured, array $plan, array &$report ) {
		global $wpdb;
		$row = (array) ( $captured['user'] ?? array() );
		$metas = (array) ( $captured['meta'] ?? array() );
		$login = (string) $plan['login'];
		$id = (int) $plan['target'];
		if ( '' === $login || $id <= 0 || $login !== (string) ( $row['user_login'] ?? '' ) ) throw new Exception( 'el plan de la cuenta no corresponde a la cuenta apuntada.' );
		$users = $wpdb->base_prefix . 'users';
		$meta = $wpdb->base_prefix . 'usermeta';
		$cap_key = $wpdb->base_prefix . 'capabilities';
		$level_key = $wpdb->base_prefix . 'user_level';
		$columns = array();
		foreach ( (array) $wpdb->get_results( 'SHOW COLUMNS FROM ' . self::q( $users ), ARRAY_A ) as $column ) $columns[] = (string) $column['Field'];
		if ( ! in_array( 'user_login', $columns, true ) ) throw new Exception( 'la tabla de usuarios restaurada no se puede leer.' );
		// 1. Las contraseñas de aplicación, ya decididas (B1).
		if ( $plan['apps'] ) self::set_meta( $meta, $id, self::APP_PASSWORDS, serialize( array_values( (array) $plan['apps'] ) ) );
		else self::delete_meta( $meta, $id, self::APP_PASSWORDS );
		// 2. La cuenta del paquete que no era administradora cede su usuario y no cambia en nada más (M1).
		if ( 'rename' === $plan['mode'] ) {
			$current = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT user_login FROM ' . self::q( $users ) . ' WHERE ID = %d', (int) $plan['renamed'] ) );
			if ( $current === $login && false === $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::q( $users ) . ' SET user_login = %s WHERE ID = %d', (string) $plan['renamed_to'], (int) $plan['renamed'] ) ) ) throw new Exception( (string) $wpdb->last_error );
		}
		// 3. La fila: la del paquete (fusión) con la contraseña y el correo de quien restaura, sin marcas de bloqueo (B9);
		// o una nueva con el ID del plan.
		$flags = array();
		foreach ( array( 'user_status', 'spam', 'deleted' ) as $flag ) if ( in_array( $flag, $columns, true ) ) $flags[ $flag ] = 0;
		$exists = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::q( $users ) . ' WHERE ID = %d', $id ) );
		if ( 'merge' === $plan['mode'] || $exists ) {
			$set = array( 'user_pass = %s' => (string) ( $row['user_pass'] ?? '' ), 'user_email = %s' => (string) ( $row['user_email'] ?? '' ) );
			if ( in_array( 'user_activation_key', $columns, true ) ) $set['user_activation_key = %s'] = '';
			foreach ( $flags as $flag => $zero ) $set[ self::q( $flag ) . ' = %s' ] = '0';
			if ( false === $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::q( $users ) . ' SET ' . implode( ', ', array_keys( $set ) ) . ' WHERE ID = %d', array_merge( array_values( $set ), array( $id ) ) ) ) ) throw new Exception( (string) $wpdb->last_error );
		} else {
			$insert = array();
			foreach ( $columns as $column ) if ( array_key_exists( $column, $row ) ) $insert[ $column ] = $row[ $column ];
			$insert['ID'] = $id;
			if ( in_array( 'user_activation_key', $columns, true ) ) $insert['user_activation_key'] = '';
			$insert = $flags + $insert;
			if ( in_array( 'user_nicename', $columns, true ) ) $insert['user_nicename'] = self::free_nicename( (string) ( $row['user_nicename'] ?? $login ), $users );
			$values = array();
			foreach ( $insert as $value ) $values[] = null === $value ? '' : (string) $value;
			if ( false === $wpdb->query( $wpdb->prepare( 'INSERT INTO ' . self::q( $users ) . ' (' . implode( ', ', array_map( array( __CLASS__, 'q' ), array_keys( $insert ) ) ) . ') VALUES (' . implode( ', ', array_fill( 0, count( $insert ), '%s' ) ) . ')', $values ) ) ) throw new Exception( (string) $wpdb->last_error );
			foreach ( self::PROFILE_META as $key ) if ( array_key_exists( $key, $metas ) ) self::set_meta( $meta, $id, $key, $metas[ $key ] );
			if ( is_multisite() && function_exists( 'get_main_site_id' ) ) self::set_meta( $meta, $id, 'primary_blog', (string) get_main_site_id() );
		}
		// 4. Sus sesiones (la cookie de su navegador sigue valiendo) y administradora (y de la red, si lo era).
		if ( isset( $metas[ self::SESSIONS ] ) && '' !== (string) $metas[ self::SESSIONS ] ) self::set_meta( $meta, $id, self::SESSIONS, (string) $metas[ self::SESSIONS ] );
		else self::delete_meta( $meta, $id, self::SESSIONS );
		self::set_meta( $meta, $id, $cap_key, serialize( array( 'administrator' => true ) ) );
		self::set_meta( $meta, $id, $level_key, '10' );
		if ( is_multisite() && ! empty( $captured['super_admin'] ) ) {
			$admins = array_values( array_map( 'strval', (array) get_site_option( 'site_admins', array() ) ) );
			if ( ! in_array( $login, $admins, true ) ) { $admins[] = $login; update_site_option( 'site_admins', $admins ); }
		}
		$report['user'] = $login;
		$report['user_id'] = $id;
		$report['account'] = 'merge' === $plan['mode'] ? 'merged' : ( 'rename' === $plan['mode'] ? 'renamed' : 'created' );
		if ( 'rename' === $plan['mode'] ) {
			$report['renamed'] = (string) $plan['renamed_to'];
			$report['warnings'][] = 'El respaldo tenía otra cuenta «' . $login . '» que no era administradora: conserva todo (su contraseña, su correo, su rol y lo demás) y ahora se llama «' . $plan['renamed_to'] . '» (también entra con su correo); la de quien restauró se creó con su usuario.';
		}
		// El correo de quien restaura también es de otra cuenta del respaldo: WordPress entraría con una u otra por correo.
		$email = (string) ( $row['user_email'] ?? '' );
		if ( '' !== $email ) {
			$others = array();
			foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT ID, user_login FROM ' . self::q( $users ) . ' WHERE user_email = %s', $email ), ARRAY_A ) as $other ) if ( (int) $other['ID'] !== $id ) $others[] = (string) $other['user_login'];
			if ( $others ) {
				$report['shared_email'] = array_slice( $others, 0, 3 );
				$report['warnings'][] = 'Tu correo también es el de ' . self::names( $report['shared_email'] ) . ' en el respaldo: entra con tu nombre de usuario («' . $login . '»), no con el correo, y cambia uno de los dos correos antes de editar tu perfil.';
			}
		}
		$mine = array_filter( self::array_value( (string) ( $metas[ self::APP_PASSWORDS ] ?? '' ) ), 'is_array' );
		$report['app_passwords'] = array( 'yours' => count( $mine ), 'kept_from_backup' => (int) $plan['kept_from_backup'], 'left_on_renamed' => (int) $plan['not_admin_apps'] );
	}
	/**
	 * La ruta de acceso de este sitio (la de antes del cambio) en lo restaurado: Tools y SEO (que la aplica mientras Tools
	 * no está activo) quedan con ella; en una red, también los valores de la red. Lo que el respaldo no trae no se crea,
	 * salvo la ruta de Tools si este sitio tenía una y el respaldo ninguna de las dos opciones.
	 */
	private static function restore_login_path( array $login, array &$report ) {
		$slug = self::sanitize_slug( (string) ( $login['slug'] ?? '' ) );
		$before = self::effective_slug( false );
		$tools = get_option( self::TOOLS_SITE, false );
		$seo = get_option( self::SEO_OPTION, false );
		if ( is_array( $tools ) ) {
			$tools['values'] = (array) ( $tools['values'] ?? array() );
			$tools['inherit'] = (array) ( $tools['inherit'] ?? array() );
			$tools['values']['login_slug'] = $slug;
			$tools['inherit']['login_slug'] = false;
			update_option( self::TOOLS_SITE, $tools, true );
		}
		if ( is_array( $seo ) && ( array_key_exists( self::SEO_KEY, $seo ) || '' !== $slug ) ) {
			$seo[ self::SEO_KEY ] = $slug;
			update_option( self::SEO_OPTION, $seo );
			if ( is_multisite() ) {
				$inherit = get_option( self::SEO_INHERIT, false );
				if ( is_array( $inherit ) || array_key_exists( self::SEO_KEY, $seo ) ) { $inherit = (array) $inherit; $inherit[ self::SEO_KEY ] = false; update_option( self::SEO_INHERIT, $inherit ); }
			}
		}
		if ( ! is_array( $tools ) && ! ( is_array( $seo ) && array_key_exists( self::SEO_KEY, $seo ) ) && '' !== $slug ) update_option( self::TOOLS_SITE, array( 'values' => array( 'login_slug' => $slug ), 'inherit' => array( 'login_slug' => false ) ), true );
		if ( is_multisite() && is_array( $login['network'] ?? null ) ) {
			$network = get_site_option( self::TOOLS_NETWORK, false );
			if ( is_array( $network ) ) { $network['login_slug'] = self::sanitize_slug( (string) ( $login['network']['tools'] ?? '' ) ); update_site_option( self::TOOLS_NETWORK, $network ); }
			$seo_network = get_site_option( self::SEO_OPTION, false );
			if ( is_array( $seo_network ) && array_key_exists( self::SEO_KEY, $seo_network ) ) { $seo_network[ self::SEO_KEY ] = self::sanitize_slug( (string) ( $login['network']['seo'] ?? '' ) ); update_site_option( self::SEO_OPTION, $seo_network ); }
		}
		if ( function_exists( 'wp_cache_flush' ) ) wp_cache_flush();
		$after = self::effective_slug( false );
		$report['login_path'] = array( 'kept' => '' === $slug ? 'standard' : 'private', 'backup_differed' => $before !== $slug, 'effective' => '' === $after ? 'standard' : 'private' );
		if ( $after !== $slug ) $report['warnings'][] = 'La ruta de acceso no quedó como la de este sitio: revisa Herramientas → Ajustes del sitio (Tools) antes de cerrar la sesión.';
	}
	/**
	 * Si algún usuario tiene contraseñas de aplicación, `using_application_passwords` queda activo (opción de red: en un
	 * sitio individual, de sus opciones). Devuelve cuántos usuarios las tienen y si se activó aquí.
	 */
	public static function app_passwords_in_use() {
		global $wpdb;
		$meta = $wpdb->base_prefix . 'usermeta';
		$users = 0;
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT meta_value FROM ' . self::q( $meta ) . ' WHERE meta_key = %s', self::APP_PASSWORDS ) ) as $value ) if ( self::array_value( (string) $value ) ) $users++;
		$enabled = false;
		if ( $users ) {
			$on = function_exists( 'get_network_option' ) && function_exists( 'get_main_network_id' ) ? get_network_option( get_main_network_id(), self::IN_USE ) : get_site_option( self::IN_USE );
			if ( ! $on ) {
				if ( function_exists( 'update_network_option' ) && function_exists( 'get_main_network_id' ) ) update_network_option( get_main_network_id(), self::IN_USE, true );
				else update_site_option( self::IN_USE, true );
				$enabled = true;
			}
		}
		return array( 'users_with' => $users, 'enabled_here' => $enabled );
	}

	/* ------------------------------------------------------------------ Cómo se entra */

	/** La URL de acceso efectiva de este sitio, como la resuelven Tools o SEO (con la constante de emergencia). */
	public static function login_url() {
		$slug = self::effective_slug( true );
		if ( '' === $slug ) return (string) site_url( 'wp-login.php', 'login' );
		$home = (string) home_url( '/' );
		return get_option( 'permalink_structure' ) ? trailingslashit( $home . $slug ) : $home . '?' . $slug;
	}
	/**
	 * La ruta privada de acceso efectiva leída de los datos (los ticks de una restauración cargan WordPress mínimo, sin
	 * Tools ni SEO): Tools si está activo; si no, SEO si está activo; si no, ninguna.
	 */
	public static function effective_slug( $constant = true ) {
		if ( $constant && defined( 'DIGITALISIMO_HIDE_LOGIN_DISABLE' ) && DIGITALISIMO_HIDE_LOGIN_DISABLE ) return '';
		$active = self::active_files();
		if ( isset( $active[ self::TOOLS_FILE ] ) ) return self::sanitize_slug( self::tools_value() );
		if ( isset( $active[ self::SEO_FILE ] ) ) return self::sanitize_slug( self::seo_value() );
		return '';
	}
	/** Nombres de archivo de los plugins activos del sitio y de la red, leídos de la base (sin el filtro de WordPress mínimo). */
	private static function active_files() {
		global $wpdb;
		$out = array();
		$options = $wpdb->prefix . 'options';
		$list = self::array_value( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM ' . self::q( $options ) . ' WHERE option_name = %s', 'active_plugins' ) ) );
		foreach ( $list as $plugin ) if ( is_string( $plugin ) ) $out[ basename( $plugin ) ] = true;
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			$network = function_exists( 'get_current_network_id' ) ? (int) get_current_network_id() : 1;
			$sitewide = self::array_value( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT meta_value FROM ' . self::q( $wpdb->base_prefix . 'sitemeta' ) . ' WHERE meta_key = %s AND site_id = %d', 'active_sitewide_plugins', $network ) ) );
			foreach ( array_keys( $sitewide ) as $plugin ) if ( is_string( $plugin ) ) $out[ basename( $plugin ) ] = true;
		}
		return $out;
	}
	/** Como Digitalisimo\Tools\Site_Options::get( 'login_slug' ), con su importación desde SEO. */
	private static function tools_value() {
		$multisite = function_exists( 'is_multisite' ) && is_multisite();
		$site = get_option( self::TOOLS_SITE, false );
		if ( false === $site ) {
			$legacy = (array) get_option( self::SEO_OPTION, array() );
			$map = (array) get_option( self::SEO_INHERIT, array() );
			$network_legacy = $multisite ? (array) get_site_option( self::SEO_OPTION, array() ) : array();
			$values = array_key_exists( self::SEO_KEY, $legacy ) ? array( 'login_slug' => $legacy[ self::SEO_KEY ] ) : array();
			$inherits = ! array_key_exists( self::SEO_KEY, $map ) || ! empty( $map[ self::SEO_KEY ] );
			if ( $inherits && ! array_key_exists( self::SEO_KEY, $network_legacy ) && array_key_exists( 'login_slug', $values ) ) $inherits = false;
			$inherit = array( 'login_slug' => $inherits );
		} else {
			$values = (array) ( ( (array) $site )['values'] ?? array() );
			$inherit = (array) ( ( (array) $site )['inherit'] ?? array() );
		}
		if ( $multisite && ( ! array_key_exists( 'login_slug', $inherit ) || ! empty( $inherit['login_slug'] ) ) ) {
			$network = get_site_option( self::TOOLS_NETWORK, false );
			if ( false === $network ) {
				$legacy = (array) get_site_option( self::SEO_OPTION, array() );
				return is_string( $legacy[ self::SEO_KEY ] ?? null ) ? $legacy[ self::SEO_KEY ] : '';
			}
			return is_string( ( (array) $network )['login_slug'] ?? null ) ? ( (array) $network )['login_slug'] : '';
		}
		return is_string( $values['login_slug'] ?? null ) ? $values['login_slug'] : '';
	}
	/** Como Digitalisimo_Integrations_SEO_Resolver::option( 'seo_hide_login_slug' ). */
	private static function seo_value() {
		$site = (array) get_option( self::SEO_OPTION, array() );
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			$map = (array) get_option( self::SEO_INHERIT, array() );
			if ( ! array_key_exists( self::SEO_KEY, $map ) || ! empty( $map[ self::SEO_KEY ] ) ) {
				$network = (array) get_site_option( self::SEO_OPTION, array() );
				if ( array_key_exists( self::SEO_KEY, $network ) ) return is_string( $network[ self::SEO_KEY ] ) ? $network[ self::SEO_KEY ] : '';
			}
		}
		return is_string( $site[ self::SEO_KEY ] ?? null ) ? $site[ self::SEO_KEY ] : '';
	}
	/** Como Hide_Login::sanitize_slug() de Tools y SEO. */
	public static function sanitize_slug( $value ) {
		$value = (string) $value;
		if ( function_exists( 'remove_accents' ) ) $value = remove_accents( $value );
		$slug = function_exists( 'sanitize_title_with_dashes' ) ? sanitize_title_with_dashes( $value ) : trim( preg_replace( '/[^a-z0-9_-]+/', '-', strtolower( $value ) ), '-' );
		return ( '' === $slug || in_array( $slug, self::RESERVED, true ) ) ? '' : $slug;
	}
	/** Quién puede entrar: los administradores del sitio (y de la red) por su nombre de usuario, y cuántos usuarios hay. */
	public static function who_can_log_in() {
		global $wpdb;
		$users = $wpdb->base_prefix . 'users';
		$meta = $wpdb->base_prefix . 'usermeta';
		$ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT user_id FROM ' . self::q( $meta ) . ' WHERE meta_key = %s AND meta_value LIKE %s', $wpdb->base_prefix . 'capabilities', '%' . $wpdb->esc_like( '"administrator"' ) . '%' ) ) );
		$logins = array();
		foreach ( array_slice( array_unique( $ids ), 0, 50 ) as $id ) {
			$login = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT user_login FROM ' . self::q( $users ) . ' WHERE ID = %d', $id ) );
			if ( '' !== $login ) $logins[] = $login;
		}
		if ( function_exists( 'is_multisite' ) && is_multisite() ) foreach ( (array) get_site_option( 'site_admins', array() ) as $login ) if ( is_string( $login ) && ! in_array( $login, $logins, true ) ) $logins[] = $login;
		sort( $logins );
		return array( 'admins' => array_slice( $logins, 0, 10 ), 'admins_total' => count( $logins ), 'users_total' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::q( $users ) ) );
	}
	/** «Cómo entrar ahora», en una frase para la pantalla, REST, WP-CLI y el correo (sin secretos). */
	public static function text( array $report ) {
		$lines = array();
		if ( '' !== (string) $report['login_url'] ) $lines[] = 'Cómo entrar ahora: ' . $report['login_url'] . '.';
		$login = (string) ( $report['user'] ?? '' );
		$app = (array) ( $report['app_passwords'] ?? array() );
		switch ( (string) $report['state'] ) {
			case 'on':
				$yours = (int) ( $app['yours'] ?? 0 );
				$lines[] = 'Se conservó el acceso de quien restauró: «' . $login . '» entra con su contraseña de siempre' . ( $yours ? ' y sus ' . $yours . ' contraseña(s) de aplicación (REST y MCP)' : '' ) . ', como administrador' . ( 'merged' === ( $report['account'] ?? '' ) ? '; el respaldo ya tenía una cuenta administradora «' . $login . '» y ahora tiene esa contraseña y ese correo (su contenido sigue siendo suyo)' : ( 'renamed' === ( $report['account'] ?? '' ) ? '; la cuenta se creó con su usuario' : '; la cuenta no estaba en el respaldo y se creó' ) ) . '.';
				break;
			case 'off':
				$lines[] = 'No se pidió conservar el acceso de quien restauró: entra con un usuario del respaldo.';
				break;
			case 'no_user':
				$lines[] = 'Se lanzó sin un usuario de WordPress (p. ej. WP-CLI sin --user): no se conservó ninguna cuenta, tampoco la de quien dio después un paso; entra con un usuario del respaldo.';
				break;
			case 'same_site':
				$lines[] = 'El respaldo es de esta misma instalación: las cuentas, contraseñas y contraseñas de aplicación son las del respaldo (no se reviven las de ahora, que podrían estar comprometidas).';
				break;
		}
		if ( ! empty( $report['admins'] ) ) $lines[] = 'Pueden entrar como administradores: ' . self::names( (array) $report['admins'] ) . ( (int) $report['admins_total'] > count( (array) $report['admins'] ) ? ' y ' . ( (int) $report['admins_total'] - count( (array) $report['admins'] ) ) . ' más' : '' ) . ( (int) $report['users_total'] > (int) $report['admins_total'] ? '; hay ' . (int) $report['users_total'] . ' usuarios en total' : '' ) . '.';
		if ( is_array( $report['login_path'] ?? null ) ) $lines[] = 'Ruta de acceso: se conservó la de este sitio (' . ( 'private' === $report['login_path']['kept'] ? 'privada' : 'wp-login.php' ) . ')' . ( ! empty( $report['login_path']['backup_differed'] ) ? '; la del respaldo no se aplicó' : '' ) . '.';
		if ( ! empty( $app['enabled_here'] ) ) $lines[] = 'Las contraseñas de aplicación de los ' . (int) $app['users_with'] . ' usuario(s) que las traen vuelven a funcionar (WordPress las tenía desactivadas en este sitio: using_application_passwords estaba en la red de origen).';
		foreach ( (array) ( $report['warnings'] ?? array() ) as $warning ) $lines[] = rtrim( (string) $warning, '.' ) . '.';
		return implode( ' ', $lines );
	}

	/* ------------------------------------------------------------------ Ficha de lectura de un trabajo */

	/** Una ficha nueva: el valor (sólo para la respuesta que inicia el trabajo) y su SHA-256 (lo único que se guarda). */
	public static function new_reader() {
		$token = bin2hex( random_bytes( 24 ) );
		return array( 'token' => $token, 'hash' => hash( 'sha256', $token ), 'issued' => time() );
	}
	/** Las huellas de las fichas que pueden leer el trabajo $job (la suya y las que hereda, p. ej. de su migración). */
	public static function readers( array $job ) {
		$out = array();
		$own = $job['spec']['reader']['hash'] ?? '';
		if ( is_string( $own ) && preg_match( '/^[0-9a-f]{64}$/D', $own ) ) $out[] = $own;
		foreach ( (array) ( $job['spec']['readers'] ?? array() ) as $hash ) if ( is_string( $hash ) && preg_match( '/^[0-9a-f]{64}$/D', $hash ) ) $out[] = $hash;
		return array_values( array_unique( $out ) );
	}
	/** Si $token es una ficha vigente del trabajo $job: suya o heredada, sin caducar (ver READER_AFTER y READER_MAX). */
	public static function reader_ok( array $job, $token ) {
		if ( ! is_string( $token ) || ! preg_match( '/^[0-9a-f]{48}$/D', $token ) ) return false;
		$hash = hash( 'sha256', $token );
		$known = false;
		foreach ( self::readers( $job ) as $candidate ) if ( hash_equals( $candidate, $hash ) ) $known = true;
		if ( ! $known ) return false;
		$issued = (int) ( $job['spec']['reader']['issued'] ?? $job['created_at'] ?? 0 );
		if ( $issued > 0 && time() - $issued > self::READER_MAX ) return false;
		$finished = (int) ( $job['finished_at'] ?? 0 );
		return ! ( $finished > 0 && time() - $finished > self::READER_AFTER );
	}

	/* ------------------------------------------------------------------ Ayudas */

	private static function set_meta( $table, $user_id, $key, $value ) {
		global $wpdb;
		self::delete_meta( $table, $user_id, $key );
		if ( false === $wpdb->query( $wpdb->prepare( 'INSERT INTO ' . self::q( $table ) . ' (' . self::q( 'user_id' ) . ', ' . self::q( 'meta_key' ) . ', ' . self::q( 'meta_value' ) . ') VALUES (%s, %s, %s)', (string) (int) $user_id, (string) $key, (string) $value ) ) ) throw new Exception( (string) $wpdb->last_error );
	}
	private static function delete_meta( $table, $user_id, $key ) {
		global $wpdb;
		if ( false === $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::q( $table ) . ' WHERE user_id = %d AND meta_key = %s', (int) $user_id, (string) $key ) ) ) throw new Exception( (string) $wpdb->last_error );
	}
	/** Un usuario libre para la cuenta del paquete que cede el suyo: «<usuario>-respaldo», «…-respaldo-2»… (60 caracteres). */
	private static function free_login( $login, $table ) {
		global $wpdb;
		$base = substr( (string) $login, 0, 48 ) . '-respaldo';
		$candidate = $base;
		for ( $n = 2; $n < 100 && null !== $wpdb->get_var( $wpdb->prepare( 'SELECT ID FROM ' . self::q( $table ) . ' WHERE user_login = %s', $candidate ) ); $n++ ) $candidate = $base . '-' . $n;
		return $candidate;
	}
	/** Un user_nicename que no use otra cuenta (el de la URL de autor). */
	private static function free_nicename( $nicename, $table ) {
		global $wpdb;
		$base = '' !== $nicename ? substr( $nicename, 0, 45 ) : 'usuario';
		$candidate = $base;
		for ( $n = 2; $n < 100 && null !== $wpdb->get_var( $wpdb->prepare( 'SELECT ID FROM ' . self::q( $table ) . ' WHERE user_nicename = %s', $candidate ) ); $n++ ) $candidate = $base . '-' . $n;
		return $candidate;
	}
	/** Un array serializado de esta base (nunca instancia objetos), o array(). */
	private static function array_value( $raw ) {
		if ( ! is_string( $raw ) || '' === $raw || ! preg_match( '/^a:\d+:\{/', $raw ) ) return array();
		$value = @unserialize( $raw, array( 'allowed_classes' => false ) );
		return is_array( $value ) ? $value : array();
	}
	private static function names( array $logins ) {
		return implode( ', ', array_map( function( $login ) { return '«' . $login . '»'; }, $logins ) );
	}
	/** Nombre de tabla o columna entre acentos graves (MySQL y el SQLite de WordPress). */
	public static function q( $name ) { return '`' . str_replace( '`', '``', (string) $name ) . '`'; }
}
