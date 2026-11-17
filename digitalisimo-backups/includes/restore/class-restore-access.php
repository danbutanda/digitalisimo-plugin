<?php
defined( 'ABSPATH' ) || exit;

/**
 * Quien restaura y los administradores de este sitio siguen entrando al restaurar el respaldo de otra instalación.
 *
 * Origen: el incidente del 2026-11-12 (el sitio extraído de la red de digitalisimo.mx restaurado en /wp dejó fuera a quien
 * lo restauró: su usuario ya no existía, la contraseña de aplicación de los usuarios del paquete respondía 401 y
 * wp-login.php daba 404 por la ruta privada de acceso de la red; desde 1.0.76 se conserva a quien restaura) y el pedido
 * del propietario del 2026-11-16 («respeta los usuarios existentes para no romper accesos admin»: también se conservan
 * los demás administradores de este sitio).
 *
 * **Cuándo.** Sólo con el respaldo de otra instalación (otro sitio, un sitio extraído de una red o una migración, con o
 * sin reubicación). Nunca en una restauración del mismo sitio (p. ej. tras un hackeo: las credenciales de ahora pueden
 * estar comprometidas y vuelven las del respaldo) ni en la vuelta a la copia previa. «Mismo sitio» (revisión A3 de
 * 1.0.79, Digitalisimo_Backups::restore_identity()): la fila hecha aquí o, subido, agregado o sin fila, la identidad de la
 * instalación firmada en su manifiesto; uno que sólo lo parece (misma dirección, prefijo y carpeta, sin esa identidad)
 * no conserva ninguna cuenta de ahora salvo que se pida.
 *
 * **Qué cuentas** (de este sitio, leídas con sus tablas antes del cambio):
 *  - **quien restaura** (`keep_access`, encendido por defecto; decide()): la cuenta que lanzó la restauración o la
 *    migración, nunca quien dé después un tick. Restaurar pide manage_options (en una red, manage_network_options), con lo
 *    que se puede reemplazar el sitio entero: queda administradora aunque su rol aquí tenga otro nombre (como en 1.0.76);
 *  - **los administradores de este sitio** (`keep_admins`, encendido por defecto; decide_admins()): en un individual, las
 *    cuentas con el rol `administrator`; en una red, los superadministradores y los administradores del sitio desde el que
 *    se restaura (admin_ids()). Con `keep_access` apagado, quien restaura no se conserva aunque sea administrador. Nunca
 *    una cuenta que aquí no sea administradora, ni una marcada como spam o borrada en la red.
 * De cada una: su fila (usuario, hash de la contraseña y correo), sus sesiones (`session_tokens`), sus contraseñas de
 * aplicación (`_application_passwords`) y, si hay que crearla, su perfil. Se apuntan en el diario de la restauración antes
 * del cambio de tablas, cifradas con la clave de este servidor (AUTH_KEY y SECURE_AUTH_KEY, que la restauración nunca
 * cambia porque nunca escribe wp-config.php), y vuelven tras el SQL, también si el proceso muere entre medias. Queda
 * administradora del sitio restaurado (y superadministradora en una red si lo era aquí). Con el mismo hash, las mismas
 * sesiones y las mismas claves de wp-config.php y de la base (SALTS), la cookie del navegador sigue valiendo.
 *
 * **Choques con una cuenta del respaldo** (plan_accounts(): se deciden juntas sobre las tablas restauradas antes de
 * escribir nada y la decisión va cifrada al diario, así que una segunda pasada tras una muerte sigue la misma; regla de la
 * revisión M5 de 1.0.79):
 *  - **mismo correo → la misma persona**, una sola cuenta (`merge`): la del respaldo, con su ID (la autoría no cambia),
 *    sus datos y sus metas, y el usuario, la contraseña, el correo, las sesiones, el segundo factor, el inicio social y el
 *    rol de aquí, sin marcas de bloqueo. Las metas de segundo factor e inicio social del respaldo (FACTOR_META y las
 *    tablas de USER_TABLES, p. ej. Wordfence) se retiran. Sus contraseñas de aplicación y sus claves de API sólo siguen si
 *    no abren más de lo que tenía en el respaldo (revisión A4): administradora allí y, si aquí es superadministradora,
 *    también allí; el nivel de superadministrador sólo lo da este sitio (una del respaldo que lo era y aquí no, deja de
 *    serlo). Si su usuario en el respaldo era otro, toma el de aquí y el informe lo dice;
 *  - **mismo usuario y otro correo → otra persona** (`rename`): no se funden; la del respaldo conserva todo (ID,
 *    contraseña, correo, rol, contraseñas de aplicación, segundo factor, inicio social; superadministradora si lo era) y
 *    sólo cambia su usuario a uno libre («<usuario>-respaldo»); la de aquí se crea con su usuario y un ID libre, y el
 *    informe lo dice;
 *  - sin coincidencia → se crea con el siguiente ID libre (`create`), sin nada que el respaldo dejara con ese ID (metas o
 *    filas de una cuenta borrada). Los IDs del respaldo nunca cambian; varias cuentas nuevas toman IDs consecutivos y un
 *    usuario cedido nunca choca con el de otra cuenta que se conserva. Una cuenta que se crea lleva su perfil, sus
 *    credenciales, su segundo factor y su inicio social de aquí.
 *
 * **Ruta de acceso**: la de este sitio (vacía si no tenía), nunca la del respaldo, y el informe dice siempre la URL de
 * acceso efectiva. En cualquier restauración, si un usuario tiene contraseñas de aplicación, `using_application_passwords`
 * queda activo: es una opción de red (en una red vive en `sitemeta`) y WordPress no comprueba ninguna contraseña de
 * aplicación sin ella (`WP_Application_Passwords::is_in_use()`): un sitio extraído de una red llegaba sin ella y
 * respondía 401 `rest_not_logged_in` aunque el hash viajara bien.
 *
 * **API** (estable; la llaman el motor de restauración y lo que restaure por su cuenta, p. ej. red ↔ individual):
 *  1. Antes del cambio de tablas, con las de este sitio: decide( $keep_access, $user_id, $otra_instalacion, $vuelta ) y
 *     decide_admins( $keep_admins, $otra_instalacion, $vuelta ) → 'on' o por qué no; capture_accounts( $user_id si
 *     decide() dio 'on' (si no, 0), decide_admins() dio 'on', $blog_prefix, $excluir: el usuario si decide() dio 'off' ) →
 *     las cuentas; login_capture(); salts_capture(); seal( array( 'users' => …, 'login' => …, 'salts' => … ) ) al diario.
 *  2. Tras el SQL, en las tablas restauradas (idempotente: el diario puede aplicarse dos veces): apply( array( 'access' =>
 *     …, 'admins' => … ), open( $sellado ), $reubicado, $remember, $options ) → el informe público. $remember( $plan )
 *     recibe la decisión (una lista, una entrada por cuenta) antes de la primera escritura, para anotarla sellada en el
 *     diario; una segunda pasada la trae en $secret['plan']. $options['blog_prefix'] y $options['blog_id']: el sitio
 *     restaurado donde las cuentas quedan administradoras (por defecto el prefijo base: el individual o el sitio principal).
 *  3. text( $informe ): «Cómo entrar ahora» para la pantalla, REST, WP-CLI y el correo. preview(): quiénes conservarían su
 *     acceso, para la confirmación antes de restaurar.
 * La forma de 1.0.76 sigue valiendo: $state como texto (el de decide()) con $secret['user'] (sólo quien restaura), y
 * plan_user() para una sola cuenta.
 *
 * Nada de esto va a registros ni respuestas: el informe sólo lleva nombres de usuario, cuántas contraseñas de aplicación y
 * la URL de acceso, nunca un hash, un correo, una sesión ni la ruta privada del respaldo.
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
	/** Cuántas cuentas de este sitio se conservan como mucho (el diario las lleva todas): las demás, el informe las cuenta. */
	const ACCOUNTS_MAX = 200;
	/**
	 * Metas de segundo factor y de inicio de sesión con otra cuenta (inicio social) de los plugins conocidos, por su
	 * comienzo (revisión M5): Two Factor (también Solid Security), WP 2FA, Two Factor Authentication / All-In-One Security,
	 * miniOrange, Google Authenticator; Nextend Social Login, WooCommerce Social Login, Super Socializer, OneAll,
	 * WordPress Social Login y el inicio con WordPress.com de Jetpack. Son un modo de entrar: van con la persona.
	 */
	const FACTOR_META = array( '_two_factor_', 'wp_2fa_', 'tfa_', 'mo2f_', 'googleauthenticator_', 'nsl_', '_wc_social_login_', 'thechamp_', 'heateor_ss_', 'oa_social_login_', 'wsl_', 'wpcom_user_id', 'wpcom_user_data' );
	/**
	 * Tablas de plugins con filas de una cuenta (nombre sin prefijo => columna del usuario, su clave propia, si es de la red
	 * y qué son): `factor` (un modo de entrar: el segundo factor de Wordfence, las cuentas enlazadas de Nextend) va con la
	 * persona; `credential` (las claves de la API REST de WooCommerce) son credenciales que actúan con los permisos de la
	 * cuenta (revisión A4).
	 */
	const USER_TABLES = array(
		'wfls_2fa_secrets' => array( 'column' => 'user_id', 'key' => 'id', 'network' => true, 'kind' => 'factor', 'label' => 'el segundo factor de Wordfence' ),
		'social_users' => array( 'column' => 'ID', 'key' => 'social_users_id', 'network' => false, 'kind' => 'factor', 'label' => 'el inicio social de Nextend' ),
		'woocommerce_api_keys' => array( 'column' => 'user_id', 'key' => 'key_id', 'network' => false, 'kind' => 'credential', 'label' => 'las claves de la API de WooCommerce' ),
	);

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
	/**
	 * Si se conservan los administradores de este sitio: 'on', o por qué no ('rollback', 'same_site', 'off'). Los mismos
	 * casos que decide(): sólo con el respaldo de otra instalación y nunca en la vuelta a la copia previa.
	 */
	public static function decide_admins( $keep, $other_site, $rollback ) {
		if ( $rollback ) return 'rollback';
		if ( ! $other_site ) return 'same_site';
		return $keep ? 'on' : 'off';
	}

	/* ------------------------------------------------------------------ Antes del cambio de tablas */

	/**
	 * Los IDs de los administradores de este sitio, leídos de la base: las cuentas con el rol `administrator` en
	 * $blog_prefix (por defecto el del sitio en curso) y, en una red, los superadministradores. Sin las marcadas como spam
	 * o borradas (no pueden entrar).
	 */
	public static function admin_ids( $blog_prefix = null ) {
		global $wpdb;
		$prefix = null === $blog_prefix || '' === (string) $blog_prefix ? (string) $wpdb->prefix : (string) $blog_prefix;
		$users = $wpdb->base_prefix . 'users';
		$meta = $wpdb->base_prefix . 'usermeta';
		$ids = array();
		// Sólo las filas que mencionan el rol (un sitio puede tener miles de usuarios); el rol se comprueba después.
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT user_id, meta_value FROM ' . self::q( $meta ) . ' WHERE meta_key = %s AND meta_value LIKE %s', $prefix . 'capabilities', '%' . $wpdb->esc_like( '"administrator"' ) . '%' ), ARRAY_A ) as $row ) {
			$caps = self::array_value( (string) ( $row['meta_value'] ?? '' ) );
			if ( ! empty( $caps['administrator'] ) ) $ids[ (int) $row['user_id'] ] = true;
		}
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			foreach ( (array) get_site_option( 'site_admins', array() ) as $login ) {
				if ( ! is_string( $login ) || '' === $login ) continue;
				$id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT ID FROM ' . self::q( $users ) . ' WHERE user_login = %s', $login ) );
				if ( $id > 0 ) $ids[ $id ] = true;
			}
		}
		$out = array();
		foreach ( array_keys( $ids ) as $id ) {
			if ( $id <= 0 ) continue;
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::q( $users ) . ' WHERE ID = %d', $id ), ARRAY_A );
			if ( is_array( $row ) && '' !== (string) ( $row['user_login'] ?? '' ) && ! self::blocked( $row ) ) $out[] = (int) $id;
		}
		sort( $out );
		return $out;
	}
	/**
	 * Las cuentas que se conservan, leídas antes del cambio (ver capture()): la de quien restaura ($restorer, su ID; 0 si no
	 * se conserva) y, con $admins, las de los administradores de este sitio (admin_ids() de $blog_prefix) salvo $exclude
	 * (quien restaura con `keep_access` apagado). Cada una lleva `restorer`, `site_admin` y `super_admin`. Secreto: sólo va,
	 * cifrado, al diario.
	 */
	public static function capture_accounts( $restorer, $admins, $blog_prefix = null, $exclude = 0 ) {
		$out = array();
		$at = array();
		$restorer = (int) $restorer;
		$admin_ids = $admins ? self::admin_ids( $blog_prefix ) : array();
		if ( $restorer > 0 ) {
			$one = self::capture( $restorer, $blog_prefix );
			if ( is_array( $one ) ) {
				$one['restorer'] = true;
				$one['site_admin'] = in_array( $restorer, $admin_ids, true );
				$at[ $restorer ] = count( $out );
				$out[] = $one;
			}
		}
		$skipped = 0;
		foreach ( $admin_ids as $id ) {
			if ( isset( $at[ $id ] ) || $id === (int) $exclude ) continue;
			if ( count( $out ) >= self::ACCOUNTS_MAX ) { $skipped++; continue; }
			$one = self::capture( $id, $blog_prefix );
			if ( ! is_array( $one ) ) continue;
			$one['restorer'] = false;
			$one['site_admin'] = true;
			$at[ $id ] = count( $out );
			$out[] = $one;
		}
		if ( $skipped && $out ) $out[0]['skipped'] = $skipped;
		return $out;
	}
	/**
	 * Para la confirmación (pantalla, `verify`): quién conservaría su acceso al restaurar aquí el respaldo de otra
	 * instalación. Sólo nombres de usuario: `you` (la cuenta de $user_id) y `admins` (los demás administradores de este
	 * sitio, como mucho $limit) con su total.
	 */
	public static function preview( $user_id = 0, $blog_prefix = null, $limit = 20 ) {
		global $wpdb;
		$users = $wpdb->base_prefix . 'users';
		$you = (int) $user_id > 0 ? (string) $wpdb->get_var( $wpdb->prepare( 'SELECT user_login FROM ' . self::q( $users ) . ' WHERE ID = %d', (int) $user_id ) ) : '';
		$logins = array();
		foreach ( self::admin_ids( $blog_prefix ) as $id ) {
			if ( $id === (int) $user_id ) continue;
			$login = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT user_login FROM ' . self::q( $users ) . ' WHERE ID = %d', $id ) );
			if ( '' !== $login ) $logins[] = $login;
		}
		sort( $logins );
		return array( 'you' => $you, 'admins' => array_slice( $logins, 0, max( 1, (int) $limit ) ), 'admins_total' => count( $logins ) );
	}

	/**
	 * La cuenta de quien restaura en las tablas de este sitio (antes del cambio): su fila, sus metas de credenciales y de
	 * perfil y si es superadministradora. null si no existe. Secreto: sólo va, cifrado, al diario.
	 */
	public static function capture( $user_id, $blog_prefix = null ) {
		global $wpdb;
		$users = $wpdb->base_prefix . 'users';
		$meta = $wpdb->base_prefix . 'usermeta';
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::q( $users ) . ' WHERE ID = %d', (int) $user_id ), ARRAY_A );
		if ( ! is_array( $row ) || '' === (string) ( $row['user_login'] ?? '' ) ) return null;
		$keys = array_merge( self::PROFILE_META, array( self::SESSIONS, self::APP_PASSWORDS ) );
		// Su perfil, sus credenciales y (revisión M5) su segundo factor y su inicio social, que van con la persona.
		$metas = array();
		$factor = array();
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT meta_key, meta_value FROM ' . self::q( $meta ) . ' WHERE user_id = %d', (int) $user_id ), ARRAY_A ) as $item ) {
			$key = (string) $item['meta_key'];
			if ( in_array( $key, $keys, true ) ) $metas[ $key ] = (string) $item['meta_value'];
			elseif ( self::factor_key( $key ) ) $factor[ $key ] = (string) $item['meta_value'];
		}
		$super = false;
		if ( function_exists( 'is_multisite' ) && is_multisite() ) $super = in_array( (string) $row['user_login'], array_map( 'strval', (array) get_site_option( 'site_admins', array() ) ), true );
		return array( 'format' => self::FORMAT, 'user' => $row, 'meta' => $metas, 'factor' => $factor, 'tables' => self::capture_tables( (int) $user_id, $blog_prefix ), 'super_admin' => $super );
	}
	/** Las filas de la cuenta en las tablas `factor` de USER_TABLES que existen aquí (sin su clave propia; valores en base64). */
	private static function capture_tables( $user_id, $blog_prefix = null ) {
		global $wpdb;
		$out = array();
		foreach ( self::USER_TABLES as $name => $table ) {
			if ( 'factor' !== $table['kind'] ) continue;
			$full = self::user_table( $name, $blog_prefix );
			if ( '' === $full ) continue;
			foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::q( $full ) . ' WHERE ' . self::q( $table['column'] ) . ' = %d', (int) $user_id ), ARRAY_A ) as $row ) {
				unset( $row[ $table['key'] ], $row[ $table['column'] ] );
				$out[ $name ][] = array_map( function( $value ) { return null === $value ? null : base64_encode( (string) $value ); }, (array) $row );
			}
		}
		return $out;
	}
	/** El nombre completo de una tabla de USER_TABLES si existe (de la red: prefijo base; si no, el del sitio), o ''. */
	private static function user_table( $name, $blog_prefix = null ) {
		global $wpdb;
		$table = self::USER_TABLES[ $name ];
		$prefix = ! empty( $table['network'] ) ? (string) $wpdb->base_prefix : ( null === $blog_prefix || '' === (string) $blog_prefix ? (string) $wpdb->prefix : (string) $blog_prefix );
		$full = $prefix . $name;
		return in_array( $full, array_map( 'strval', (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $full ) ) ) ), true ) ? $full : '';
	}
	/** Una meta de segundo factor o de inicio social (FACTOR_META). */
	private static function factor_key( $key ) {
		foreach ( self::FACTOR_META as $start ) if ( 0 === strpos( (string) $key, $start ) ) return true;
		return false;
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
	/**
	 * Para el diario: cifrado con la clave de este servidor; sin claves propias, tal cual (el diario es privado, 0600).
	 * Revisión B2: sin AUTH_KEY y SECURE_AUTH_KEY propias la confirmación lo avisa antes (pantalla y `verify`). Una clave
	 * sacada de las sales de la base no serviría: la restauración cambia la base (tras el cambio de tablas las sales son
	 * las del respaldo) y el diario se abre después, también tras una muerte.
	 */
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
	 * muere entre medias): las cuentas que se conservan, la ruta de acceso de este sitio y las contraseñas de aplicación.
	 * $state: array( 'access' => decide(), 'admins' => decide_admins() ), o sólo el de decide() (forma de 1.0.76). $secret,
	 * lo abierto del diario (array( users, login, salts, plan ) o, en la forma de 1.0.76, array( user, login, salts )) o
	 * null. $remember( $plan ) anota la decisión antes de la primera escritura. $options: `blog_prefix` y `blog_id` del sitio
	 * restaurado donde las cuentas quedan administradoras. Devuelve el informe público (sin secretos).
	 */
	public static function apply( $state, $secret, $relocated, $remember = null, array $options = array() ) {
		$states = self::states( $state );
		// Las tablas acaban de cambiar: lo que la caché de objetos tenga (`site_admins`, roles, usuarios) es de antes.
		if ( function_exists( 'wp_cache_flush' ) ) wp_cache_flush();
		$report = array( 'state' => $states['access'], 'admins_state' => $states['admins'], 'relocated' => (bool) $relocated, 'user' => '', 'account' => '', 'kept_accounts' => array(), 'merged_accounts' => array(), 'merged_with' => array(), 'created_accounts' => array(), 'renamed_backup' => array(), 'shared_emails' => array(), 'warnings' => array(), 'app_passwords' => array(), 'login_path' => null, 'login_url' => '', 'admins' => array(), 'admins_total' => 0, 'users_total' => 0, 'text' => '' );
		$accounts = self::accounts_of( $secret, $states );
		$login = is_array( $secret ) && is_array( $secret['login'] ?? null ) ? $secret['login'] : null;
		$restorer = false;
		foreach ( $accounts as $one ) if ( ! empty( $one['restorer'] ) ) $restorer = true;
		if ( 'on' === $states['access'] && ! $restorer ) {
			$report['state'] = 'missing_user';
			$report['warnings'][] = 'No se pudo leer la cuenta de quien restauró antes del cambio: entra con un usuario del respaldo.';
		}
		if ( $accounts && ! empty( $accounts[0]['skipped'] ) ) $report['warnings'][] = 'Este sitio tenía más de ' . self::ACCOUNTS_MAX . ' administradores: ' . (int) $accounts[0]['skipped'] . ' no se conservaron.';
		$plans = null;
		if ( $accounts ) {
			try {
				// La decisión de una pasada anterior (el diario tras una muerte) manda; si no hay, se toma ahora para todas las
				// cuentas sobre las tablas restauradas y se anota antes de la primera escritura (B1).
				$plans = self::plan_of( $secret, $accounts );
				if ( null === $plans ) {
					$plans = self::plan_accounts( $accounts, $options );
					if ( is_callable( $remember ) ) call_user_func( $remember, $plans );
				}
			} catch ( Throwable $e ) {
				$plans = null;
				if ( $restorer ) $report['state'] = 'failed';
				$report['warnings'][] = ( $restorer ? 'No se pudo conservar la cuenta de quien restauró: ' : 'No se pudieron conservar las cuentas de este sitio: ' ) . $e->getMessage();
			}
		}
		if ( is_array( $plans ) ) {
			foreach ( $accounts as $i => $one ) {
				try {
					self::restore_account( $one, (array) ( $plans[ $i ] ?? array() ), $options, $report );
				} catch ( Throwable $e ) {
					if ( ! empty( $one['restorer'] ) ) {
						$report['state'] = 'failed';
						$report['warnings'][] = 'No se pudo conservar la cuenta de quien restauró: ' . $e->getMessage();
					} else {
						$report['warnings'][] = 'No se pudo conservar la cuenta «' . (string) ( $one['user']['user_login'] ?? '' ) . '»: ' . $e->getMessage();
					}
				}
			}
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
	 * Qué se hará con cada cuenta que se conserva, decidido sobre las tablas restauradas ANTES de cambiar nada (revisión
	 * M1, B1), una entrada por cuenta y en su orden. La regla (revisión M5 de 1.0.79):
	 *  - **mismo correo → la misma persona** (`merge`): la cuenta del respaldo con su correo (antes la del mismo usuario;
	 *    una por cuenta) queda como la de aquí, con su ID (la autoría no cambia) y sus datos, y el usuario, la contraseña, el
	 *    correo, las sesiones, el segundo factor, el inicio social y el rol de aquí. Si su usuario era otro, toma el de aquí
	 *    (`merged_from`) y la cuenta del respaldo que tuviera ese usuario lo cede (`renamed`). Sus contraseñas de aplicación
	 *    y sus claves de API (USER_TABLES `credential`) sólo siguen si no abren más de lo que tenía en el respaldo
	 *    (revisión A4): administradora allí y, si aquí es superadministradora, también allí;
	 *  - **mismo usuario y otro correo → otra persona** (`rename`): no se funden; la del respaldo conserva todo (ID,
	 *    contraseña, correo, rol, contraseñas de aplicación, segundo factor, inicio social) y sólo cambia su usuario a uno
	 *    libre («<usuario>-respaldo»); la de aquí se crea con su usuario y un ID libre;
	 *  - sin coincidencia → `create` con el siguiente ID libre.
	 * Fija los IDs nuevos (consecutivos), los usuarios cedidos (que no chocan con el de ninguna cuenta que se conserva ni
	 * entre sí) y las contraseñas de aplicación de cada cuenta. Va al diario (cifrado) antes de la primera escritura: una
	 * segunda pasada tras una muerte sigue la misma decisión y nunca suma contraseñas ajenas ni renombra dos veces.
	 */
	public static function plan_accounts( array $accounts, array $options = array() ) {
		global $wpdb;
		$users = $wpdb->base_prefix . 'users';
		$accounts = array_values( $accounts );
		$next = (int) $wpdb->get_var( 'SELECT MAX(ID) FROM ' . self::q( $users ) ) + 1;
		$reserved = array();
		foreach ( $accounts as $one ) $reserved[ (string) ( $one['user']['user_login'] ?? '' ) ] = true;
		// 1. La misma persona: la cuenta del respaldo con el mismo correo (la del mismo usuario primero), una por cuenta.
		$matches = array();
		$claimed = array();
		foreach ( $accounts as $i => $one ) {
			$login = (string) ( $one['user']['user_login'] ?? '' );
			$email = self::email_key( $one['user']['user_email'] ?? '' );
			if ( '' === $email ) continue;
			$pick = null;
			foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::q( $users ) . ' WHERE user_email = %s ORDER BY ID', (string) $one['user']['user_email'] ), ARRAY_A ) as $candidate ) {
				if ( self::email_key( $candidate['user_email'] ?? '' ) !== $email || isset( $claimed[ (int) $candidate['ID'] ] ) ) continue;
				if ( (string) $candidate['user_login'] === $login ) { $pick = $candidate; break; }
				if ( ! $pick ) $pick = $candidate;
			}
			if ( $pick ) { $matches[ $i ] = $pick; $claimed[ (int) $pick['ID'] ] = true; }
		}
		// 2. Los usuarios de las cuentas fundidas que cambian al de aquí quedan libres (login => 0).
		$logins = array();
		foreach ( $matches as $i => $match ) if ( (string) $match['user_login'] !== (string) $accounts[ $i ]['user']['user_login'] ) $logins[ (string) $match['user_login'] ] = 0;
		$plans = array();
		foreach ( $accounts as $i => $one ) $plans[] = self::plan_one( (array) $one, $matches[ $i ] ?? null, $next, $reserved, $logins, $options );
		return $plans;
	}
	/** La decisión para una sola cuenta (la de quien restaura, como en 1.0.76): ver plan_accounts(). */
	public static function plan_user( array $captured ) {
		return self::plan_accounts( array( $captured ) )[0];
	}
	private static function plan_one( array $captured, $match, &$next, array &$reserved, array &$logins, array $options ) {
		global $wpdb;
		$row = (array) ( $captured['user'] ?? array() );
		$login = (string) ( $row['user_login'] ?? '' );
		if ( '' === $login ) throw new Exception( 'la cuenta apuntada no tiene usuario.' );
		$users = $wpdb->base_prefix . 'users';
		$mine = array_values( array_filter( self::array_value( (string) ( ( (array) ( $captured['meta'] ?? array() ) )[ self::APP_PASSWORDS ] ?? '' ) ), 'is_array' ) );
		$plan = array( 'format' => self::FORMAT, 'login' => $login, 'restorer' => ! empty( $captured['restorer'] ), 'mode' => 'create', 'target' => 0, 'renamed' => 0, 'renamed_to' => '', 'renamed_super' => false, 'merged_from' => '', 'was_admin' => false, 'backup_super' => false, 'backup_credentials' => false, 'apps' => $mine, 'kept_from_backup' => 0, 'dropped_from_backup' => 0, 'not_admin_apps' => 0 );
		// Quién tiene ahora ese usuario en el respaldo (tras las fusiones decididas): otra persona, que lo cede.
		$holder = null;
		if ( array_key_exists( $login, $logins ) ) {
			$id = (int) $logins[ $login ];
			if ( $id > 0 ) $holder = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::q( $users ) . ' WHERE ID = %d', $id ), ARRAY_A );
		} else {
			$holder = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::q( $users ) . ' WHERE user_login = %s', $login ), ARRAY_A );
		}
		if ( ! is_array( $holder ) || ( is_array( $match ) && (int) $holder['ID'] === (int) $match['ID'] ) ) $holder = null;
		if ( $holder ) {
			$plan['renamed'] = (int) $holder['ID'];
			$plan['renamed_to'] = self::free_login( $login, $users, $reserved );
			$plan['renamed_super'] = self::in_site_admins( (string) $holder['user_login'] );
			$plan['not_admin_apps'] = count( array_filter( self::array_value( (string) self::meta_of( (int) $holder['ID'], self::APP_PASSWORDS ) ), 'is_array' ) );
			$reserved[ $plan['renamed_to'] ] = true;
			$logins[ $plan['renamed_to'] ] = (int) $holder['ID'];
		}
		if ( is_array( $match ) ) {
			$id = (int) $match['ID'];
			$caps = self::array_value( (string) self::meta_of( $id, self::blog_prefix( $options ) . 'capabilities' ) );
			$plan['mode'] = 'merge';
			$plan['target'] = $id;
			$plan['merged_from'] = (string) $match['user_login'] !== $login ? (string) $match['user_login'] : '';
			$plan['backup_super'] = self::in_site_admins( (string) $match['user_login'] );
			$plan['was_admin'] = ! empty( $caps['administrator'] ) || $plan['backup_super'];
			// Revisión A4: lo del respaldo nunca abre más de lo que tenía allí; superadministradora sólo si aquí lo es.
			$here_super = is_multisite() && ! empty( $captured['super_admin'] );
			$plan['backup_credentials'] = $plan['was_admin'] && ( ! $here_super || $plan['backup_super'] );
			$theirs = array_values( array_filter( self::array_value( (string) self::meta_of( $id, self::APP_PASSWORDS ) ), 'is_array' ) );
			$uuids = array();
			foreach ( $mine as $item ) $uuids[ (string) ( $item['uuid'] ?? '' ) ] = true;
			foreach ( $theirs as $item ) {
				if ( isset( $uuids[ (string) ( $item['uuid'] ?? '' ) ] ) ) continue;
				if ( $plan['backup_credentials'] ) { $plan['apps'][] = $item; $plan['kept_from_backup']++; }
				else $plan['dropped_from_backup']++;
			}
			$logins[ $login ] = $id;
			return $plan;
		}
		$plan['mode'] = $holder ? 'rename' : 'create';
		$plan['target'] = $next++;
		$logins[ $login ] = $plan['target'];
		return $plan;
	}
	/**
	 * Una cuenta que se conserva según su plan (ver plan_accounts()), en las tablas restauradas. Cada paso es idempotente y
	 * las contraseñas de aplicación (ya filtradas) se escriben primero. Anota en $report sólo nombres de usuario.
	 */
	private static function restore_account( array $captured, array $plan, array $options, array &$report ) {
		global $wpdb;
		$row = (array) ( $captured['user'] ?? array() );
		$metas = (array) ( $captured['meta'] ?? array() );
		$login = (string) ( $plan['login'] ?? '' );
		$id = (int) ( $plan['target'] ?? 0 );
		$restorer = ! empty( $captured['restorer'] );
		if ( '' === $login || $id <= 0 || $login !== (string) ( $row['user_login'] ?? '' ) || $restorer !== ! empty( $plan['restorer'] ?? $restorer ) ) throw new Exception( 'el plan de la cuenta no corresponde a la cuenta apuntada.' );
		if ( ! in_array( (string) ( $plan['mode'] ?? '' ), array( 'create', 'merge', 'rename' ), true ) ) throw new Exception( 'el plan de la cuenta no se entiende.' );
		$merge = 'merge' === $plan['mode'];
		$users = $wpdb->base_prefix . 'users';
		$meta = $wpdb->base_prefix . 'usermeta';
		$blog = self::blog_prefix( $options );
		$cap_key = $blog . 'capabilities';
		$level_key = $blog . 'user_level';
		$columns = array();
		foreach ( (array) $wpdb->get_results( 'SHOW COLUMNS FROM ' . self::q( $users ), ARRAY_A ) as $column ) $columns[] = (string) $column['Field'];
		if ( ! in_array( 'user_login', $columns, true ) ) throw new Exception( 'la tabla de usuarios restaurada no se puede leer.' );
		$exists = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::q( $users ) . ' WHERE ID = %d', $id ) );
		// 0. Una cuenta que se crea: nada de lo que el respaldo dejó con su ID (metas o filas de una cuenta borrada) se le pega.
		if ( ! $merge && ! $exists ) self::purge_user( $meta, $id, $blog );
		// 1. Las contraseñas de aplicación, ya decididas (B1; sin las del respaldo que abrirían más, A4).
		if ( $plan['apps'] ) self::set_meta( $meta, $id, self::APP_PASSWORDS, serialize( array_values( (array) $plan['apps'] ) ) );
		else self::delete_meta( $meta, $id, self::APP_PASSWORDS );
		// 2. La cuenta del paquete de otra persona con este usuario lo cede y no cambia en nada más (M1, M5).
		if ( ! empty( $plan['renamed'] ) ) {
			$current = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT user_login FROM ' . self::q( $users ) . ' WHERE ID = %d', (int) $plan['renamed'] ) );
			if ( $current === $login && false === $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::q( $users ) . ' SET user_login = %s WHERE ID = %d', (string) $plan['renamed_to'], (int) $plan['renamed'] ) ) ) throw new Exception( (string) $wpdb->last_error );
		}
		// 3. La fila: la del paquete (fusión) con el usuario, la contraseña y el correo de aquí, sin marcas de bloqueo (B9);
		// o una nueva con el ID del plan.
		$flags = array();
		foreach ( array( 'user_status', 'spam', 'deleted' ) as $flag ) if ( in_array( $flag, $columns, true ) ) $flags[ $flag ] = 0;
		if ( $merge || $exists ) {
			$set = array( 'user_login = %s' => $login, 'user_pass = %s' => (string) ( $row['user_pass'] ?? '' ), 'user_email = %s' => (string) ( $row['user_email'] ?? '' ) );
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
			if ( is_multisite() ) {
				$blog_id = (int) ( $options['blog_id'] ?? ( function_exists( 'get_main_site_id' ) ? get_main_site_id() : 1 ) );
				self::set_meta( $meta, $id, 'primary_blog', (string) max( 1, $blog_id ) );
			}
		}
		// 4. Sus sesiones (la cookie de su navegador sigue valiendo) y administradora.
		if ( isset( $metas[ self::SESSIONS ] ) && '' !== (string) $metas[ self::SESSIONS ] ) self::set_meta( $meta, $id, self::SESSIONS, (string) $metas[ self::SESSIONS ] );
		else self::delete_meta( $meta, $id, self::SESSIONS );
		self::set_meta( $meta, $id, $cap_key, serialize( array( 'administrator' => true ) ) );
		self::set_meta( $meta, $id, $level_key, '10' );
		// 5. Su segundo factor y su inicio social: los de aquí (los del respaldo, en una cuenta fundida, se retiran: M5). Las
		// claves de API del respaldo sólo siguen si no abren más de lo que tenía allí (A4).
		self::restore_factor( $meta, $id, $captured, $plan, $blog, $report );
		// 6. Superadministradora sólo si aquí lo era (A4): la del respaldo con el mismo usuario no lo da, y la cuenta del
		// respaldo que cede su usuario sigue siéndolo con el nuevo; el usuario anterior de una fundida deja de contar.
		if ( is_multisite() ) {
			$admins = array_values( array_map( 'strval', (array) get_site_option( 'site_admins', array() ) ) );
			$before = $admins;
			if ( ! empty( $plan['renamed'] ) && ! empty( $plan['renamed_super'] ) ) $admins = array_map( function( $one ) use ( $login, $plan ) { return $one === $login ? (string) $plan['renamed_to'] : $one; }, $admins );
			if ( $merge && '' !== (string) ( $plan['merged_from'] ?? '' ) ) $admins = array_values( array_diff( $admins, array( (string) $plan['merged_from'] ) ) );
			$admins = array_values( array_diff( $admins, array( $login ) ) );
			if ( ! empty( $captured['super_admin'] ) ) $admins[] = $login;
			$admins = array_values( array_unique( $admins ) );
			if ( $admins !== $before ) update_site_option( 'site_admins', $admins );
			if ( $merge && ! empty( $plan['backup_super'] ) && empty( $captured['super_admin'] ) ) $report['warnings'][] = 'En el respaldo «' . ( '' !== (string) ( $plan['merged_from'] ?? '' ) ? (string) $plan['merged_from'] : $login ) . '» era superadministrador; fundida con «' . $login . '» de esta red, que no lo es, queda como administrador del sitio (el nivel de superadministrador sólo lo da esta red).';
		}
		// El informe: sólo nombres de usuario.
		$how = $merge ? 'merged' : ( 'rename' === $plan['mode'] ? 'renamed' : 'created' );
		if ( ! in_array( $login, $report['kept_accounts'], true ) ) $report['kept_accounts'][] = $login;
		if ( 'merged' === $how && ! in_array( $login, $report['merged_accounts'], true ) ) $report['merged_accounts'][] = $login;
		if ( 'merged' !== $how && ! in_array( $login, $report['created_accounts'], true ) ) $report['created_accounts'][] = $login;
		if ( $merge && '' !== (string) ( $plan['merged_from'] ?? '' ) ) {
			$pair = array( 'login' => $login, 'backup' => (string) $plan['merged_from'] );
			if ( ! in_array( $pair, $report['merged_with'], true ) ) $report['merged_with'][] = $pair;
		}
		if ( ! empty( $plan['renamed'] ) ) {
			$pair = array( 'from' => $login, 'to' => (string) $plan['renamed_to'] );
			if ( ! in_array( $pair, $report['renamed_backup'], true ) ) $report['renamed_backup'][] = $pair;
		}
		if ( $restorer ) {
			$report['user'] = $login;
			$report['user_id'] = $id;
			$report['account'] = $how;
		}
		if ( ! empty( $plan['renamed'] ) ) {
			if ( $restorer ) $report['renamed'] = (string) $plan['renamed_to'];
			$report['warnings'][] = 'El respaldo tenía otra cuenta «' . $login . '» con otro correo (otra persona): conserva todo (su contraseña, su correo, su rol, su segundo factor y lo demás) y ahora se llama «' . $plan['renamed_to'] . '» (también entra con su correo); la ' . ( $restorer ? 'de quien restauró' : 'de «' . $login . '» de este sitio' ) . ( $merge ? ' tomó su usuario' : ' se creó con su usuario' ) . '.';
		}
		if ( $merge && (int) ( $plan['dropped_from_backup'] ?? 0 ) > 0 ) $report['warnings'][] = 'Se retiraron ' . (int) $plan['dropped_from_backup'] . ' contraseña(s) de aplicación que «' . ( '' !== (string) ( $plan['merged_from'] ?? '' ) ? (string) $plan['merged_from'] : $login ) . '» tenía en el respaldo: allí no tenía los permisos que tiene «' . $login . '» aquí.';
		// Su correo también es el de otra cuenta del respaldo: WordPress entraría con una u otra por correo.
		$email = (string) ( $row['user_email'] ?? '' );
		if ( '' !== $email ) {
			$others = array();
			foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT ID, user_login FROM ' . self::q( $users ) . ' WHERE user_email = %s', $email ), ARRAY_A ) as $other ) if ( (int) $other['ID'] !== $id ) $others[] = (string) $other['user_login'];
			if ( $others ) {
				$others = array_slice( $others, 0, 3 );
				$report['shared_emails'][ $login ] = $others;
				if ( $restorer ) {
					$report['shared_email'] = $others;
					$report['warnings'][] = 'Tu correo también es el de ' . self::names( $others ) . ' en el respaldo: entra con tu nombre de usuario («' . $login . '»), no con el correo, y cambia uno de los dos correos antes de editar tu perfil.';
				} else {
					$report['warnings'][] = 'El correo de «' . $login . '» también es el de ' . self::names( $others ) . ' en el respaldo: «' . $login . '» entra con su nombre de usuario, no con el correo (cambia uno de los dos correos antes de editar su perfil).';
				}
			}
		}
		if ( $restorer ) {
			$mine = array_filter( self::array_value( (string) ( $metas[ self::APP_PASSWORDS ] ?? '' ) ), 'is_array' );
			$report['app_passwords'] = array( 'yours' => count( $mine ), 'kept_from_backup' => (int) $plan['kept_from_backup'], 'dropped_from_backup' => (int) ( $plan['dropped_from_backup'] ?? 0 ), 'left_on_renamed' => (int) $plan['not_admin_apps'] ) + (array) $report['app_passwords'];
		}
	}
	/**
	 * El segundo factor y el inicio social de la cuenta $id: los de aquí (metas de FACTOR_META y filas `factor` de
	 * USER_TABLES); en una cuenta fundida se retiran antes los del respaldo y, si sus credenciales abrirían más de lo que
	 * tenía allí, sus filas `credential`. Idempotente.
	 */
	private static function restore_factor( $meta, $id, array $captured, array $plan, $blog, array &$report ) {
		global $wpdb;
		$merge = 'merge' === ( $plan['mode'] ?? '' );
		$factor = array_filter( (array) ( $captured['factor'] ?? array() ), function( $value, $key ) { return self::factor_key( $key ); }, ARRAY_FILTER_USE_BOTH );
		if ( $merge ) foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT meta_key FROM ' . self::q( $meta ) . ' WHERE user_id = %d', (int) $id ) ) as $key ) if ( self::factor_key( $key ) && ! array_key_exists( (string) $key, $factor ) ) self::delete_meta( $meta, $id, (string) $key );
		foreach ( $factor as $key => $value ) self::set_meta( $meta, $id, (string) $key, (string) $value );
		$tables = (array) ( $captured['tables'] ?? array() );
		foreach ( self::USER_TABLES as $name => $table ) {
			$full = self::user_table( $name, $blog );
			$rows = array_filter( (array) ( $tables[ $name ] ?? array() ), 'is_array' );
			if ( '' === $full ) {
				if ( $rows ) $report['warnings'][] = 'No volvió ' . $table['label'] . ' de «' . (string) ( $plan['login'] ?? '' ) . '»: el respaldo no tiene su tabla (el plugin no estaba en el sitio del respaldo).';
				continue;
			}
			if ( 'credential' === $table['kind'] ) {
				$keep_backup = array_key_exists( 'backup_credentials', $plan ) ? ! empty( $plan['backup_credentials'] ) : ! empty( $plan['was_admin'] );
				if ( $merge && ! $keep_backup && false === $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::q( $full ) . ' WHERE ' . self::q( $table['column'] ) . ' = %d', (int) $id ) ) ) throw new Exception( (string) $wpdb->last_error );
				continue;
			}
			if ( false === $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::q( $full ) . ' WHERE ' . self::q( $table['column'] ) . ' = %d', (int) $id ) ) ) throw new Exception( (string) $wpdb->last_error );
			$known = array();
			foreach ( (array) $wpdb->get_results( 'SHOW COLUMNS FROM ' . self::q( $full ), ARRAY_A ) as $column ) $known[ (string) $column['Field'] ] = true;
			foreach ( $rows as $one ) {
				$insert = array( $table['column'] => (string) (int) $id );
				foreach ( $one as $column => $value ) if ( isset( $known[ $column ] ) && $column !== $table['key'] && $column !== $table['column'] ) $insert[ $column ] = null === $value ? null : base64_decode( (string) $value );
				$insert = array_filter( $insert, function( $value ) { return null !== $value; } );
				if ( false === $wpdb->query( $wpdb->prepare( 'INSERT INTO ' . self::q( $full ) . ' (' . implode( ', ', array_map( array( __CLASS__, 'q' ), array_keys( $insert ) ) ) . ') VALUES (' . implode( ', ', array_fill( 0, count( $insert ), '%s' ) ) . ')', array_values( $insert ) ) ) ) throw new Exception( (string) $wpdb->last_error );
			}
		}
	}
	/** Lo que el respaldo dejó con el ID de una cuenta que se crea (metas y filas de USER_TABLES de una cuenta borrada). */
	private static function purge_user( $meta, $id, $blog ) {
		global $wpdb;
		if ( false === $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::q( $meta ) . ' WHERE user_id = %d', (int) $id ) ) ) throw new Exception( (string) $wpdb->last_error );
		foreach ( self::USER_TABLES as $name => $table ) {
			$full = self::user_table( $name, $blog );
			if ( '' !== $full && false === $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::q( $full ) . ' WHERE ' . self::q( $table['column'] ) . ' = %d', (int) $id ) ) ) throw new Exception( (string) $wpdb->last_error );
		}
	}
	/** Los administradores de este sitio creados después de $since (fecha UTC del respaldo): sólo sus usuarios. */
	public static function newer_admins( $since, $blog_prefix = null, $limit = 10 ) {
		global $wpdb;
		$at = strtotime( (string) $since );
		if ( false === $at ) return array();
		$out = array();
		foreach ( self::admin_ids( $blog_prefix ) as $id ) {
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT user_login, user_registered FROM ' . self::q( $wpdb->base_prefix . 'users' ) . ' WHERE ID = %d', $id ), ARRAY_A );
			$registered = is_array( $row ) ? strtotime( (string) $row['user_registered'] . ' UTC' ) : false;
			if ( false !== $registered && $registered > $at ) $out[] = (string) $row['user_login'];
		}
		sort( $out );
		return array_slice( $out, 0, max( 1, (int) $limit ) );
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
		// Los demás administradores de este sitio que se conservaron (quien restaura tiene su propia frase).
		$others = array_values( array_diff( array_map( 'strval', (array) ( $report['kept_accounts'] ?? array() ) ), '' !== $login ? array( $login ) : array() ) );
		$admins_state = (string) ( $report['admins_state'] ?? '' );
		$kept_others = 'on' === $admins_state && $others;
		// Las cuentas fundidas cuyo usuario en el respaldo era otro (mismo correo: la misma persona): usuario => el del respaldo.
		$from = array();
		foreach ( (array) ( $report['merged_with'] ?? array() ) as $pair ) if ( is_array( $pair ) ) $from[ (string) ( $pair['login'] ?? '' ) ] = (string) ( $pair['backup'] ?? '' );
		// Revisión A3: un respaldo que sólo parece de este mismo sitio, sin pedir que se conserve nadie, se restauró como tal.
		$likely = (array) ( $report['likely_same'] ?? array() );
		if ( ! empty( $likely['access'] ) || ! empty( $likely['admins'] ) ) $lines[] = 'El respaldo parece de este mismo sitio (misma dirección, prefijo y carpeta, sin la identidad de esta instalación): ' . ( ! empty( $likely['access'] ) && ! empty( $likely['admins'] ) ? 'no se conservó ninguna cuenta de ahora' : ( ! empty( $likely['admins'] ) ? 'no se conservaron los demás administradores de ahora' : 'no se conservó la cuenta de quien restauró' ) ) . ' (vuelven las del respaldo con sus contraseñas, como al restaurar el mismo sitio).';
		switch ( (string) $report['state'] ) {
			case 'on':
				$yours = (int) ( $app['yours'] ?? 0 );
				$lines[] = 'Se conservó el acceso de quien restauró: «' . $login . '» entra con su contraseña de siempre' . ( $yours ? ' y sus ' . $yours . ' contraseña(s) de aplicación (REST y MCP)' : '' ) . ', como administrador' . ( 'merged' === ( $report['account'] ?? '' ) ? ( isset( $from[ $login ] ) ? '; el respaldo tenía su cuenta con su mismo correo como «' . $from[ $login ] . '» (la misma persona): ahora es una sola, «' . $login . '», con su contenido, y sus contraseñas de aplicación del respaldo que siguen valen con «' . $login . '» o con el correo' : '; el respaldo ya tenía su cuenta «' . $login . '» con su mismo correo y ahora tiene su contraseña (su contenido sigue siendo suyo)' ) : ( 'renamed' === ( $report['account'] ?? '' ) ? '; la cuenta se creó con su usuario' : '; la cuenta no estaba en el respaldo y se creó' ) ) . '.';
				break;
			case 'off':
				$lines[] = $kept_others ? 'No se pidió conservar el acceso de quien restauró.' : 'No se pidió conservar el acceso de quien restauró: entra con un usuario del respaldo.';
				break;
			case 'no_user':
				$lines[] = $kept_others ? 'Se lanzó sin un usuario de WordPress (p. ej. WP-CLI sin --user): no hay una cuenta de quien restauró que conservar (tampoco la de quien dio después un paso).' : 'Se lanzó sin un usuario de WordPress (p. ej. WP-CLI sin --user): no se conservó ninguna cuenta, tampoco la de quien dio después un paso; entra con un usuario del respaldo.';
				break;
			case 'same_site':
				$lines[] = 'El respaldo es de esta misma instalación: las cuentas, contraseñas y contraseñas de aplicación son las del respaldo (no se reviven las de ahora, que podrían estar comprometidas).';
				break;
		}
		if ( $kept_others ) {
			$lines[] = ( 'on' === (string) $report['state'] ? 'También conservaron' : 'Conservaron' ) . ' su acceso los administradores de este sitio: ' . self::names( array_slice( $others, 0, 10 ) ) . ( count( $others ) > 10 ? ' y ' . ( count( $others ) - 10 ) . ' más' : '' ) . ' (entran con su usuario y su contraseña de siempre, como administradores).';
			$merged = array_values( array_intersect( $others, array_map( 'strval', (array) ( $report['merged_accounts'] ?? array() ) ) ) );
			if ( $merged ) $lines[] = 'Del respaldo se fusionaron con ellos, por tener el mismo correo (la misma persona), ' . implode( ', ', array_map( function( $one ) use ( $from ) { return isset( $from[ $one ] ) ? '«' . $from[ $one ] . '» (ahora «' . $one . '»)' : '«' . $one . '»'; }, array_slice( $merged, 0, 10 ) ) ) . ': mismo ID (su contenido sigue siendo suyo), ahora con el usuario, la contraseña, el correo, el segundo factor y el rol de este sitio.';
		} elseif ( 'off' === $admins_state ) {
			$lines[] = 'No se pidió conservar a los demás administradores de este sitio.';
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
	/**
	 * Un usuario libre para la cuenta del paquete que cede el suyo: «<usuario>-respaldo», «…-respaldo-2»… (60 caracteres),
	 * que no use otra cuenta del paquete ni ninguna de $reserved (las que se conservan y los usuarios ya cedidos).
	 */
	private static function free_login( $login, $table, array $reserved = array() ) {
		global $wpdb;
		$base = substr( (string) $login, 0, 48 ) . '-respaldo';
		$candidate = $base;
		for ( $n = 2; $n < 1000 && ( isset( $reserved[ $candidate ] ) || null !== $wpdb->get_var( $wpdb->prepare( 'SELECT ID FROM ' . self::q( $table ) . ' WHERE user_login = %s', $candidate ) ) ); $n++ ) $candidate = $base . '-' . $n;
		return $candidate;
	}
	/** Un correo para compararlo (WordPress no distingue mayúsculas): en minúsculas y sin espacios; '' si no hay. */
	private static function email_key( $email ) {
		return strtolower( trim( (string) $email ) );
	}
	/** Si $login está en `site_admins` (en una red; en un individual, nunca). */
	private static function in_site_admins( $login ) {
		return is_multisite() && '' !== (string) $login && in_array( (string) $login, array_map( 'strval', (array) get_site_option( 'site_admins', array() ) ), true );
	}
	/** El valor de una meta de una cuenta en las tablas de ahora, o null. */
	private static function meta_of( $user_id, $key ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( 'SELECT meta_value FROM ' . self::q( $wpdb->base_prefix . 'usermeta' ) . ' WHERE user_id = %d AND meta_key = %s', (int) $user_id, (string) $key ) );
	}
	/** Las decisiones: array( access, admins ); la forma de 1.0.76 (sólo la de decide()) no conserva administradores. */
	private static function states( $state ) {
		if ( is_array( $state ) ) return array( 'access' => (string) ( $state['access'] ?? 'off' ), 'admins' => (string) ( $state['admins'] ?? '' ) );
		$state = (string) $state;
		return array( 'access' => $state, 'admins' => in_array( $state, array( 'same_site', 'rollback' ), true ) ? $state : '' );
	}
	/**
	 * Las cuentas apuntadas que se conservan según las decisiones: quien restaura sólo con `access` en 'on' y las demás sólo
	 * con `admins` en 'on' y si aquí eran administradoras (falla cerrado ante un diario incoherente). Acepta la forma de
	 * 1.0.76 (`user`: sólo quien restaura).
	 */
	private static function accounts_of( $secret, array $states ) {
		if ( ! is_array( $secret ) ) return array();
		$out = array();
		if ( is_array( $secret['users'] ?? null ) ) {
			foreach ( $secret['users'] as $one ) {
				if ( ! is_array( $one ) || ! is_array( $one['user'] ?? null ) || '' === (string) ( $one['user']['user_login'] ?? '' ) ) continue;
				if ( ! empty( $one['restorer'] ) ) {
					if ( 'on' === $states['access'] ) $out[] = $one;
				} elseif ( 'on' === $states['admins'] && ( ! empty( $one['site_admin'] ) || ! empty( $one['super_admin'] ) ) ) {
					$out[] = $one;
				}
			}
		} elseif ( is_array( $secret['user'] ?? null ) && 'on' === $states['access'] ) {
			$one = $secret['user'];
			$one['restorer'] = true;
			$out[] = $one;
		}
		return $out;
	}
	/** La decisión anotada por una pasada anterior si corresponde a estas cuentas (la de 1.0.76 era una sola), o null. */
	private static function plan_of( $secret, array $accounts ) {
		$plan = is_array( $secret ) && is_array( $secret['plan'] ?? null ) ? $secret['plan'] : null;
		if ( null === $plan ) return null;
		if ( array_key_exists( 'login', $plan ) ) $plan = array( $plan );
		$plan = array_values( $plan );
		if ( count( $plan ) !== count( $accounts ) ) return null;
		foreach ( array_values( $accounts ) as $i => $one ) if ( ! is_array( $plan[ $i ] ) || (string) ( $one['user']['user_login'] ?? '' ) !== (string) ( $plan[ $i ]['login'] ?? '' ) ) return null;
		return $plan;
	}
	/** El prefijo del sitio restaurado donde las cuentas quedan administradoras (por defecto el base). */
	private static function blog_prefix( array $options ) {
		global $wpdb;
		$prefix = (string) ( $options['blog_prefix'] ?? '' );
		return '' !== $prefix && preg_match( '/^[A-Za-z0-9_]+$/D', $prefix ) ? $prefix : (string) $wpdb->base_prefix;
	}
	/** Una fila de usuario marcada como spam o borrada en la red (no puede entrar). */
	private static function blocked( array $row ) {
		return ! empty( $row['spam'] ) || ! empty( $row['deleted'] );
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
