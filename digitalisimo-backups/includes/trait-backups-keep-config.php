<?php
defined( 'ABSPATH' ) || exit;

/**
 * La configuración de Backups es de esta instalación, como el historial (H23 del ensayo E4).
 *
 * Restaurar el respaldo de otra instalación (traído de un destino remoto o subido) traía en su SQL la configuración de
 * Backups del origen: desaparecía sin aviso la conexión con nombre creada aquí y
 * este sitio heredaba las automatizaciones del origen hacia su mismo destino (los dos respaldaban en la misma carpeta).
 *
 * Ahora, al restaurar el respaldo de otra instalación (sin fila en el historial de aquí, o con otra identidad), si este
 * sitio tiene configuración propia de Backups (una conexión con nombre, un destino principal con conexión completa,
 * automatizaciones distintas de las de serie, avisos o el respaldo antes de actualizar), la restauración la conserva: los ajustes y destinos, las conexiones con nombre y sus credenciales cifradas (el almacén de S4 de este
 * servidor, con sus claves y atado a sus conexiones: nunca se mezcla con el del origen), el catálogo de servidores, las
 * automatizaciones con sus eventos de WP-Cron, los avisos, el respaldo antes de actualizar y el índice de los destinos.
 * Viaja en el diario de la restauración (`config_kept`) y vuelve tras el SQL, también si el proceso muere entre medias.
 *
 * Un sitio sin configuración propia (una instalación nueva donde se restaura el respaldo de un sitio perdido) recibe la
 * del respaldo, como hasta ahora (S4: las credenciales del origen se vuelven a cifrar con las claves de este servidor).
 * Un respaldo de esta misma instalación (su fila del historial lleva la identidad de aquí) tampoco cambia: devuelve la
 * configuración de su momento, como decidió S4 («todo viaja en el respaldo»).
 * El historial, el llavero, «Seguridad», el registro del almacén, la migración y lo traído de un destino tienen su
 * propio mecanismo.
 */
trait Digitalisimo_Backups_Keep_Config {

	/** Opciones de la configuración propia de Backups (en una red, cada una en su ámbito: sitio principal o red). */
	private static function keep_config_options() {
		return array(
			self::OPTION,
			self::POLICIES,
			self::SERVERS,
			self::REMOTE_INDEX,
			Digitalisimo_Backups_Destinations::CONNECTIONS,
			Digitalisimo_Backups_Credentials::OPTION,
			Digitalisimo_Backups_Credentials::OPTION_MORE,
			Digitalisimo_Backups_Notify::OPTION,
			Digitalisimo_Backups_Pre_Update::OPTION,
			Digitalisimo_Backups_Pre_Update::STATE,
		);
	}
	/**
	 * El respaldo $file es de esta misma instalación: tiene fila en el historial y su identidad (`installation`) es la de
	 * aquí (una fila anterior a la identidad, sin ella, también: se registró aquí). Entonces su configuración es la de este
	 * sitio en otro momento y viaja como siempre (S4). Un archivo sin fila (traído, subido) o de otra identidad, no; uno
	 * subido con su fila «Subido» tampoco: estar en la lista no lo hace de esta instalación.
	 */
	private static function keep_config_same_installation( $file ) {
		$row = '' !== (string) $file ? self::history_item( wp_basename( (string) $file ) ) : array();
		if ( ! $row || self::is_uploaded_row( $row ) ) return false;
		$stamp = (array) ( $row['installation'] ?? array() );
		if ( empty( $stamp['id'] ) ) return true;
		$here = self::installation_stamp();
		return ! empty( $here['id'] ) && hash_equals( (string) $here['id'], (string) $stamp['id'] );
	}

	// ------------------------------------------------------------------ De qué instalación es un respaldo (revisión A3)
	//
	// Para «Conservar mi acceso» y «Conservar los administradores» (no para H23, que sigue con
	// keep_config_same_installation()): 'same' (de esta misma instalación), 'likely' (parece de esta misma) u 'other'.
	//  - 'same': su fila hecha aquí (keep_config_same_installation()) o, subido, agregado o sin fila, la identidad de esta
	//    instalación en su manifiesto firmado (`installation`: id de installation_stamp(), su dirección y su prefijo) con la
	//    misma dirección y el mismo prefijo de ahora. Se restaura como el mismo sitio: tras un hackeo vuelven las cuentas
	//    del respaldo y no se conserva ninguna de ahora.
	//  - 'likely': la misma identidad con otro prefijo o, sin identidad (respaldos de antes de 1.0.79) o con otra (el
	//    almacén privado se borró y su identificador cambió), la misma dirección, el mismo prefijo y la misma carpeta de
	//    WordPress o el mismo nombre de base de datos. La confirmación lo dice y por defecto (`keep_access` y `keep_admins`
	//    sin decir) no se conserva ninguna cuenta de ahora; marcándolas, sí.
	//  - 'other': lo demás (otro sitio, un sitio extraído, una migración).
	public static function manifest_installation() {
		global $wpdb;
		$stamp = self::installation_stamp();
		if ( empty( $stamp['id'] ) ) return array();
		// `owner`: la instalación sin su dirección (installation_owner()), para reconocerla aunque cambie de http a https.
		return array( 'id' => (string) $stamp['id'], 'home' => (string) ( $stamp['home'] ?? '' ), 'prefix' => (string) ( $wpdb->base_prefix ?? '' ), 'owner' => self::installation_owner() );
	}
	private static function restore_identity( $file, $manifest = null ) {
		$file = wp_basename( (string) $file );
		if ( '' !== $file && self::keep_config_same_installation( $file ) ) return 'same';
		if ( ! is_array( $manifest ) ) $manifest = '' !== $file ? self::identity_manifest( $file ) : null;
		if ( is_array( $manifest ) ) return self::restore_identity_of( $manifest );
		// Sin manifiesto legible (un .dgbk subido, antes de descifrarlo): firmado con una clave de aquí y sin nada que reubicar.
		$row = '' !== $file ? self::history_item( $file ) : array();
		return ! empty( $row['upload']['same_site'] ) ? 'likely' : 'other';
	}
	public static function restore_identity_of( array $data ) {
		global $wpdb;
		if ( ! in_array( (string) ( $data['scope'] ?? 'installation' ), array( 'installation', 'network' ), true ) || ! empty( $data['multisite'] ) !== is_multisite() ) return 'other';
		$prefix = (string) ( $wpdb->base_prefix ?? '' );
		$stamp = is_array( $data['installation'] ?? null ) ? $data['installation'] : array();
		$mine = self::installation_stamp();
		if ( is_string( $stamp['id'] ?? null ) && '' !== $stamp['id'] && ! empty( $mine['id'] ) && hash_equals( (string) $mine['id'], $stamp['id'] ) ) {
			return self::identity_url( (string) ( $stamp['home'] ?? '' ) ) === self::identity_url( (string) ( $mine['home'] ?? '' ) ) && (string) ( $stamp['prefix'] ?? $data['table_prefix'] ?? '' ) === $prefix ? 'same' : 'likely';
		}
		$origin = is_array( $data['origin'] ?? null ) ? $data['origin'] : array();
		$here = Digitalisimo_Backups_Relocate::describe( false );
		$home = self::identity_url( (string) ( $origin['home'] ?? $data['home_url'] ?? '' ) );
		if ( '' === $home || $home !== self::identity_url( (string) $here['home'] ) || (string) ( $origin['prefix'] ?? $data['table_prefix'] ?? '' ) !== $prefix ) return 'other';
		$paths = array_filter( array( (string) ( $origin['abspath'] ?? '' ), (string) ( $origin['abspath_real'] ?? '' ) ) );
		$same_path = (bool) array_intersect( $paths, array_filter( array( (string) $here['abspath'], (string) $here['abspath_real'] ) ) );
		$same_db = defined( 'DB_NAME' ) && '' !== (string) ( $data['database_name'] ?? '' ) && (string) $data['database_name'] === (string) DB_NAME;
		return $same_path || $same_db ? 'likely' : 'other';
	}
	// Una dirección para compararla: sin esquema, en minúsculas y sin la barra final.
	/**
	 * Restaurar encima de este sitio el respaldo de otro: sólo si la persona escribe a mano el dominio de origen. Pasa sin
	 * escribirlo el respaldo de esta misma instalación (su identidad, o su fila hecha aquí) con la misma dirección que tiene
	 * este sitio en su base; con otra dirección o de otra instalación, '' sólo si `origin_domain` es el host del origen.
	 * Devuelve el motivo ('' si puede seguir). Sin manifiesto legible no decide (la apertura del paquete ya lo rechaza).
	 */
	public static function restore_origin_problem( $file, $manifest, array $args = array() ) {
		if ( ! is_array( $manifest ) ) return '';
		$origin = is_array( $manifest['origin'] ?? null ) ? $manifest['origin'] : array();
		$from = (string) ( $origin['home'] ?? $manifest['home_url'] ?? '' );
		$here = (string) Digitalisimo_Backups_Relocate::describe( false )['home'];
		$stamp = is_array( $manifest['installation'] ?? null ) ? $manifest['installation'] : array();
		$mine = self::installation_stamp();
		$same_id = ( is_string( $stamp['id'] ?? null ) && '' !== $stamp['id'] && ! empty( $mine['id'] ) && hash_equals( (string) $mine['id'], $stamp['id'] ) ) || ( '' !== (string) $file && self::keep_config_same_installation( wp_basename( (string) $file ) ) );
		// La misma instalación con otra forma de su dirección (http → https, con o sin www): su id se recalcula con la
		// dirección que guardó el manifiesto, o se compara `owner`, que no la lleva. Decide después la dirección ($place).
		$owner = self::installation_owner();
		if ( ! $same_id && '' !== $owner && is_string( $stamp['owner'] ?? null ) && hash_equals( $owner, $stamp['owner'] ) ) $same_id = true;
		if ( ! $same_id && is_string( $stamp['id'] ?? null ) && '' !== $stamp['id'] && is_string( $stamp['home'] ?? null ) ) {
			$id = self::installation_id();
			if ( '' !== $id && hash_equals( hash( 'sha256', $id . "\n" . self::journal_owner() . "\n" . rtrim( $stamp['home'], '/' ) ), $stamp['id'] ) ) $same_id = true;
		}
		$place = function( $url ) {
			$path = rtrim( (string) wp_parse_url( (string) $url, PHP_URL_PATH ), '/' );
			return Digitalisimo_Same_Site::host( $url ) . $path;
		};
		// Sin identidad (respaldos anteriores a 1.0.79 e importados de UpdraftPlus, Backuply o Webuzo) no hay identidad que
		// no coincida: basta la misma dirección. Con otra identidad o con otra dirección, el dominio escrito.
		$no_id = ! is_string( $stamp['id'] ?? null ) || '' === $stamp['id'];
		// La dirección de este sitio: la de su base (describe()) o la que da home_url() (la que registra el manifiesto).
		$heres = array_unique( array_filter( array( $place( $here ), function_exists( 'home_url' ) ? $place( home_url() ) : '' ) ) );
		if ( ( $same_id || $no_id ) && '' !== $from && in_array( $place( $from ), $heres, true ) ) return '';
		$host = Digitalisimo_Same_Site::host( $from );
		$unknown = '' === $host || '.invalid' === substr( $host, -8 );
		// Un origen desconocido (un Backuply sin su dirección): se confirma escribiendo el dominio de este sitio.
		$expect = $unknown ? Digitalisimo_Same_Site::host( $here ) : $host;
		$typed = Digitalisimo_Same_Site::host( (string) ( $args['origin_domain'] ?? '' ) );
		if ( '' !== $typed && '' !== $expect && hash_equals( $expect, $typed ) ) return '';
		if ( $unknown ) return 'Este respaldo no dice de qué sitio es: para restaurarlo encima de este sitio, escribe a mano el dominio de este sitio (' . $expect . ') en «Dominio de origen»' . ( '' !== $typed ? ' (escribiste ' . $typed . ')' : '' ) . '. No se tocó nada.';
		$what = $same_id ? 'Este respaldo es de ' . $host . ' y este sitio es ' . Digitalisimo_Same_Site::host( $here ) : 'Este respaldo es de otra instalación (' . $host . ')';
		return $what . ': para restaurarlo aquí, escribe a mano su dominio de origen (' . $host . ') en «Dominio de origen»' . ( '' !== $typed ? ' (escribiste ' . $typed . ')' : '' ) . '. No se tocó nada.';
	}
	/**
	 * El dominio que hay que escribir en «Dominio de origen» para restaurar $file (un respaldo del almacén sin cifrar): el
	 * host de su origen o, si no lo dice, el de este sitio. '' si no se puede leer.
	 */
	public static function restore_origin_host( $file ) {
		$manifest = self::identity_manifest( wp_basename( (string) $file ) );
		if ( ! is_array( $manifest ) ) return '';
		$host = Digitalisimo_Same_Site::host( (string) ( $manifest['origin']['home'] ?? $manifest['home_url'] ?? '' ) );
		return '' === $host || '.invalid' === substr( $host, -8 ) ? Digitalisimo_Same_Site::host( (string) Digitalisimo_Backups_Relocate::describe( false )['home'] ) : $host;
	}
	private static function identity_url( $url ) {
		return (string) preg_replace( '#^[a-z][a-z0-9+.-]*://#', '', rtrim( strtolower( trim( (string) $url ) ), '/' ) );
	}
	// El comienzo del manifiesto de un respaldo del almacén (sin descifrar: un .dgbk da null), una vez por petición.
	private static function identity_manifest( $file ) {
		static $cache = array();
		$path = self::local_path( (string) $file );
		if ( '' === $path ) return null;
		$key = $path . '|' . @filemtime( $path ) . '|' . @filesize( $path );
		if ( array_key_exists( $key, $cache ) ) return $cache[ $key ];
		$cache[ $key ] = null;
		if ( ! class_exists( 'ZipArchive' ) || Digitalisimo_Backups_Encrypted_File::is_encrypted( $path ) ) return null;
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) return null;
		try {
			$head = self::manifest_head( $zip );
		} finally {
			$zip->close();
		}
		return $cache[ $key ] = is_array( $head ) ? $head : null;
	}
	// « · parece de este mismo sitio» en la lista de «Restaurar un archivo suelto» (con las casillas marcadas, `auto`, no
	// conserva ninguna cuenta de ahora).
	private static function keep_identity_label( $file ) {
		try {
			return 'likely' === self::restore_identity( (string) $file ) ? ' · parece de este mismo sitio: vuelven sus cuentas' : '';
		} catch ( Throwable $e ) {
			return '';
		}
	}
	// `keep_access` o `keep_admins` de una petición o de un trabajo: true o false si se pidió; null si no (o 'auto': la
	// casilla marcada de serie de la pantalla), que la restauración resuelve con keep_resolved().
	private static function keep_choice( array $args, $name ) {
		if ( ! array_key_exists( $name, $args ) || null === $args[ $name ] || 'auto' === $args[ $name ] ) return null;
		return ! empty( $args[ $name ] );
	}
	// Por defecto se conservan, salvo que el respaldo parezca de este mismo sitio ('likely'): ahí, sólo si se pidió.
	private static function keep_resolved( $choice, $identity ) {
		return null === $choice ? 'likely' !== $identity : (bool) $choice;
	}
	/** Este sitio tiene configuración de Backups propia (no la de una instalación recién hecha). */
	private static function keep_config_wanted() {
		try {
			if ( Digitalisimo_Backups_Destinations::connections() ) return true;
			foreach ( array_keys( self::manual_destinations( self::settings() ) ) as $id ) if ( 'local' !== (string) $id ) return true;
			$policies = get_option( self::POLICIES, false );
			if ( false !== $policies && $policies != self::default_policies() ) return true;
			foreach ( array( Digitalisimo_Backups_Notify::OPTION, Digitalisimo_Backups_Pre_Update::OPTION ) as $name ) if ( Digitalisimo_Backups_Keys::get( $name, array() ) ) return true;
		} catch ( Throwable $e ) {
			// Sin poder decidirlo, se conserva lo de aquí: perder una configuración propia es peor que no heredar la del origen.
			return true;
		}
		return false;
	}
	/** Foto de la configuración: valor de cada opción en el sitio y, en una red, en la red (`absent` si no existe). */
	private static function keep_config_snapshot() {
		$absent = '__digitalisimo_backups_absent__';
		$out = array( 'format' => 1, 'site' => array(), 'network' => null );
		foreach ( self::keep_config_options() as $name ) {
			// De la base, no de la caché de objetos (que podría dar los de otro sitio): esto se repone tras restaurar.
			$value = Digitalisimo_Same_Site::db_value( $name, $absent );
			$out['site'][ $name ] = $absent === $value ? array( 'absent' => true ) : array( 'value' => $value );
		}
		if ( is_multisite() ) {
			$out['network'] = array();
			foreach ( self::keep_config_options() as $name ) {
				$value = Digitalisimo_Same_Site::db_site_value( $name, $absent );
				$out['network'][ $name ] = $absent === $value ? array( 'absent' => true ) : array( 'value' => $value );
			}
		}
		return $out;
	}
	/**
	 * Tras el SQL (y tras volver a cifrar las credenciales que trajo): la configuración de este sitio vuelve tal cual, la
	 * del origen se retira y las automatizaciones recuperan sus eventos (los del origen se quitan de `cron`).
	 */
	private static function keep_config_reapply( $snapshot, $mark = '' ) {
		if ( ! is_array( $snapshot ) || 1 !== ( $snapshot['format'] ?? null ) || ! is_array( $snapshot['site'] ?? null ) ) return;
		// Lo que trajo el respaldo (sus credenciales ya cifradas con las claves de aquí): queda como «configuración del
		// respaldo pendiente» si traía automatizaciones o conexiones que no se aplican, con un aviso que permite aplicarla.
		// Se anota ANTES de devolver la de aquí y marcado con el diario de esta restauración: si el proceso muere después,
		// la operación siguiente (que vuelve a aplicar el diario y ya ve aquí la configuración de este sitio) no lo
		// recalcula ni lo borra (revisión D3).
		$notice = self::restore_notice();
		$noted = '' !== (string) $mark && is_string( $notice['journal'] ?? null ) && hash_equals( (string) $mark, $notice['journal'] );
		if ( ! $noted ) self::restore_notice_pending( self::keep_config_snapshot(), $snapshot, (string) $mark );
		self::keep_config_write( $snapshot );
	}
	/** Escribe una foto de configuración (cada opción en su ámbito) y realinea los eventos de las automatizaciones. */
	private static function keep_config_write( array $snapshot ) {
		$allowed = array_flip( self::keep_config_options() );
		foreach ( (array) ( $snapshot['site'] ?? array() ) as $name => $entry ) {
			if ( ! isset( $allowed[ $name ] ) || ! is_array( $entry ) ) continue;
			if ( ! empty( $entry['absent'] ) ) delete_option( $name );
			elseif ( array_key_exists( 'value', $entry ) ) update_option( $name, $entry['value'], false );
		}
		if ( is_multisite() && is_array( $snapshot['network'] ?? null ) ) {
			foreach ( $snapshot['network'] as $name => $entry ) {
				if ( ! isset( $allowed[ $name ] ) || ! is_array( $entry ) ) continue;
				if ( ! empty( $entry['absent'] ) ) delete_site_option( $name );
				elseif ( array_key_exists( 'value', $entry ) ) update_site_option( $name, $entry['value'] );
			}
		}
		// Sólo las opciones repuestas (de este sitio y de su red), nunca la caché global compartida.
		Digitalisimo_Same_Site::invalidate( array_keys( (array) ( $snapshot['site'] ?? array() ) ), is_multisite() ? array_keys( (array) ( $snapshot['network'] ?? array() ) ) : array() );
		self::keep_config_events();
	}

	/* ------------------------------------------------------------------ Configuración del respaldo pendiente y aviso */

	/** Opción del aviso tras restaurar (de la red en Multisite, como la configuración). */
	private static function restore_notice_key() { return 'digitalisimo_backups_restore_notice'; }
	private static function restore_notice() {
		$value = Digitalisimo_Backups_Keys::get( self::restore_notice_key(), array() );
		return is_array( $value ) ? $value : array();
	}
	private static function restore_notice_save( array $notice ) {
		if ( empty( $notice['pending'] ) && empty( $notice['automations'] ) && empty( $notice['connections'] ) && empty( $notice['no_active'] ) && empty( $notice['paused'] ) && empty( $notice['access'] ) ) {
			if ( is_multisite() ) delete_site_option( self::restore_notice_key() );
			else delete_option( self::restore_notice_key() );
			return;
		}
		Digitalisimo_Backups_Keys::put( self::restore_notice_key(), $notice );
	}
	/** Valor de una opción de una foto (la del sitio, o la de la red en Multisite para las que viven ahí). */
	private static function keep_config_value( array $snapshot, $name ) {
		foreach ( array( 'site', 'network' ) as $scope ) {
			$entry = $snapshot[ $scope ][ $name ] ?? null;
			if ( is_array( $entry ) && array_key_exists( 'value', $entry ) && ! empty( $entry['value'] ) ) return $entry['value'];
		}
		return null;
	}
	/** Automatizaciones activas (respaldo completo con programación) de una lista de políticas. */
	private static function active_policies( $policies ) {
		return array_values( array_filter( (array) $policies, function( $policy ) { return is_array( $policy ) && ! empty( $policy['enabled'] ) && 'full' === ( $policy['type'] ?? 'full' ) && 'disabled' !== (string) ( $policy['schedule'] ?? 'disabled' ); } ) );
	}
	/**
	 * Las automatizaciones que «Aplicar la del respaldo» escribe de una foto de configuración, de los dos ámbitos: las de
	 * serie si el respaldo no traía la opción (revisión D2). El aviso antes de aplicar y el de después cuentan con esta
	 * misma lista las que quedan en pausa (H26 del ensayo E5: antes uno contaba sólo las distintas de las de aquí y el
	 * otro, todas).
	 */
	private static function restore_config_policies( array $config ) {
		$out = array();
		foreach ( array( 'site', 'network' ) as $scope ) {
			$value = $config[ $scope ][ self::POLICIES ]['value'] ?? null;
			if ( 'site' === $scope && ! is_array( $value ) ) $value = self::default_policies();
			if ( is_array( $value ) ) foreach ( $value as $policy ) if ( is_array( $policy ) ) $out[] = $policy;
		}
		return $out;
	}
	/**
	 * Lo de este sitio que «Aplicar la del respaldo» reemplaza (H26), en una frase de 140 caracteres como mucho: sus
	 * conexiones con nombre y sus automatizaciones que la configuración del respaldo no trae, con su nombre si cabe.
	 */
	private static function restore_config_replaces( array $pending ) {
		$theirs = array();
		foreach ( array( 'site', 'network' ) as $scope ) {
			$value = $pending[ $scope ][ Digitalisimo_Backups_Destinations::CONNECTIONS ]['value'] ?? null;
			if ( is_array( $value ) ) $theirs += $value;
		}
		$lost = array( 'conexión' => array(), 'automatización' => array() );
		foreach ( Digitalisimo_Backups_Destinations::connections() as $id => $connection ) if ( ! isset( $theirs[ $id ] ) ) $lost['conexión'][] = (string) $connection['name'];
		$ids = array();
		foreach ( self::restore_config_policies( $pending ) as $policy ) $ids[ (string) ( $policy['id'] ?? '' ) ] = true;
		foreach ( self::policies() as $policy ) if ( is_array( $policy ) && ! isset( $ids[ (string) ( $policy['id'] ?? '' ) ] ) ) $lost['automatización'][] = self::restore_automation_name( $policy );
		$plural = array( 'conexión' => 'conexiones', 'automatización' => 'automatizaciones' );
		$counted = array();
		$named = array();
		$total = 0;
		foreach ( $lost as $word => $names ) {
			$n = count( $names );
			if ( ! $n ) continue;
			$total += $n;
			$counted[] = $n . ' ' . ( 1 === $n ? $word : $plural[ $word ] );
			$named[] = end( $counted ) . ' (' . implode( ', ', array_map( function( $name ) { return '«' . $name . '»'; }, $names ) ) . ')';
		}
		if ( ! $total ) return 'Aplicarla reemplaza los ajustes y el destino principal de este sitio.';
		$start = 'Aplicarla reemplaza la de este sitio: se ' . ( 1 === $total ? 'pierde ' : 'pierden ' );
		$long = $start . implode( ' y ', $named ) . '.';
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $long, 'UTF-8' ) : strlen( $long );
		return $length <= 140 ? $long : $start . implode( ' y ', $counted ) . '.';
	}
	/** Nombre corto de una automatización: su nombre, o la frecuencia y la hora fija («Semanal 04:45»). */
	private static function restore_automation_name( array $policy ) {
		$label = trim( (string) ( $policy['label'] ?? '' ) );
		if ( '' !== $label ) return $label;
		$time = self::fixed_time( $policy );
		return ( self::schedule_options()[ (string) ( $policy['schedule'] ?? '' ) ] ?? 'Automatización' ) . ( '' !== $time ? ' ' . $time : '' );
	}
	/**
	 * Lo pendiente, cifrado entero con la clave de este servidor (de AUTH_KEY y SECURE_AUTH_KEY, como las credenciales):
	 * ni el correo de los avisos ni los hosts, usuarios y rutas del origen quedan en claro en la base (revisión I1). null
	 * si este servidor no puede cifrar.
	 */
	private static function restore_pending_seal( array $pending ) {
		$json = wp_json_encode( $pending );
		$key = Digitalisimo_Backups_Credentials::key();
		if ( ! is_string( $json ) || false === $key ) return null;
		try {
			$box = sodium_crypto_generichash( 'DGBK1/restore-pending', $key, 32 );
			$nonce = random_bytes( 24 );
			$out = array( 'v' => 1, 'kw' => Digitalisimo_Backups_Credentials::key_id( $key ), 'box' => Digitalisimo_Backups_Keys::b64url_encode( $nonce . sodium_crypto_aead_xchacha20poly1305_ietf_encrypt( $json, 'DGBK1/restore-pending|v1', $nonce, $box ) ) );
			Digitalisimo_Backups_Sodium::memzero( $box );
			return $out;
		} catch ( Throwable $e ) {
			return null;
		} finally {
			Digitalisimo_Backups_Sodium::memzero( $key );
		}
	}
	/** Lo pendiente descifrado, o null si no abre con la clave de este servidor (cambió, o fue alterado). */
	private static function restore_pending_open( $sealed ) {
		if ( ! is_array( $sealed ) || 1 !== ( $sealed['v'] ?? null ) ) return null;
		$key = Digitalisimo_Backups_Credentials::key();
		if ( false === $key ) return null;
		$plain = false;
		try {
			$raw = Digitalisimo_Backups_Keys::b64url_decode( $sealed['box'] ?? null );
			if ( false !== $raw && strlen( $raw ) > 24 + 16 && hash_equals( Digitalisimo_Backups_Credentials::key_id( $key ), (string) ( $sealed['kw'] ?? '' ) ) ) {
				$box = sodium_crypto_generichash( 'DGBK1/restore-pending', $key, 32 );
				$plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt( substr( $raw, 24 ), 'DGBK1/restore-pending|v1', substr( $raw, 0, 24 ), $box );
				Digitalisimo_Backups_Sodium::memzero( $box );
			}
		} catch ( Throwable $e ) {
			$plain = false;
		} finally {
			Digitalisimo_Backups_Sodium::memzero( $key );
		}
		$pending = is_string( $plain ) ? json_decode( $plain, true ) : null;
		return is_array( $pending ) && 1 === ( $pending['format'] ?? null ) && is_array( $pending['site'] ?? null ) ? $pending : null;
	}
	/**
	 * Tras conservar la configuración de aquí: si la del respaldo traía automatizaciones activas o conexiones que no se
	 * aplicaron, se guarda entera como pendiente, cifrada (sus credenciales, además, con las claves de este servidor; las
	 * que no se pudieron abrir siguen sin abrirse), y el aviso lo dice. $mark: el diario de esta restauración.
	 */
	private static function restore_notice_pending( array $incoming, array $kept, $mark = '' ) {
		$local = array();
		foreach ( (array) self::keep_config_value( $kept, self::POLICIES ) as $policy ) if ( is_array( $policy ) ) $local[ (string) ( $policy['id'] ?? '' ) ] = $policy;
		$automations = 0;
		foreach ( self::active_policies( self::keep_config_value( $incoming, self::POLICIES ) ) as $policy ) if ( ( $local[ (string) ( $policy['id'] ?? '' ) ] ?? null ) != $policy ) $automations++;
		$connections = count( array_diff_key( (array) self::keep_config_value( $incoming, Digitalisimo_Backups_Destinations::CONNECTIONS ), (array) self::keep_config_value( $kept, Digitalisimo_Backups_Destinations::CONNECTIONS ) ) );
		$notice = array( 'at' => time(), 'journal' => (string) $mark, 'pending' => null, 'automations' => 0, 'connections' => 0, 'no_active' => false, 'paused' => 0 );
		// Hay aviso si el respaldo trae algo distinto de lo de aquí; el aviso cuenta sus automatizaciones como las contará
		// «Aplicar la del respaldo»: todas las activas de su configuración, que quedarían en pausa (H26).
		if ( $automations || $connections ) $notice = array_merge( $notice, array( 'pending' => self::restore_pending_seal( $incoming ), 'automations' => count( self::active_policies( self::restore_config_policies( $incoming ) ) ), 'connections' => $connections ) );
		self::restore_notice_save( $notice );
	}
	/**
	 * Tras cualquier restauración: si el sitio queda sin ninguna automatización activa, el aviso lo dice. Un aviso que
	 * llegó con el SQL (el del respaldo) nunca vale: sólo cuenta el que anotó esta restauración.
	 */
	private static function restore_notice_after( $fresh ) {
		$notice = $fresh ? self::restore_notice() : array();
		$notice += array( 'at' => time(), 'pending' => null, 'automations' => 0, 'connections' => 0, 'paused' => 0 );
		$notice['no_active'] = ! self::active_policies( self::policies() );
		self::restore_notice_save( $notice );
	}
	/** El aviso en texto (escritorio, tarjeta de Backups, REST y WP-CLI). '' si no hay nada que decir. */
	private static function restore_notice_text( array $notice ) {
		$lines = array();
		// Tras restaurar en otro sitio (otra URL, prefijo o ruta, un sitio extraído o una migración): cómo se entra ahora.
		if ( '' !== (string) ( $notice['access'] ?? '' ) ) $lines[] = (string) $notice['access'];
		if ( ! empty( $notice['automations'] ) || ! empty( $notice['connections'] ) ) {
			$n = (int) $notice['automations'];
			$m = (int) $notice['connections'];
			$what = array();
			if ( $n ) $what[] = $n . ' ' . ( 1 === $n ? 'automatización' : 'automatizaciones' );
			if ( $m ) $what[] = $m . ' ' . ( 1 === $m ? 'conexión' : 'conexiones' );
			// El verbo concuerda con lo que se cuenta: «1 automatización … que no se aplicó» (H26).
			$lines[] = 'Este sitio conservó su configuración de Backups; el respaldo restaurado traía ' . implode( ' y ', $what ) . ' del sitio de origen que no se ' . ( 1 === $n + $m ? 'aplicó' : 'aplicaron' ) . '.';
			if ( empty( $notice['pending'] ) ) $lines[] = 'No se guardó para aplicarla después: este servidor no tiene AUTH_KEY y SECURE_AUTH_KEY propias en wp-config.php con las que cifrarla.';
			elseif ( $n ) $lines[] = 1 === $n ? 'Si la aplicas, esa automatización queda en pausa hasta que la actives.' : 'Si la aplicas, esas ' . $n . ' automatizaciones quedan en pausa hasta que las actives.';
		}
		$p = (int) ( $notice['paused'] ?? 0 );
		if ( $p ) $lines[] = 'Se aplicó la configuración del respaldo con ' . $p . ' ' . ( 1 === $p ? 'automatización en pausa: actívala en Backups → Herramientas → Automatizaciones cuando confirmes que no respalda' : 'automatizaciones en pausa: actívalas en Backups → Herramientas → Automatizaciones cuando confirmes que no respaldan' ) . ' a la misma carpeta que el sitio de origen.';
		if ( ! empty( $notice['no_active'] ) && empty( $notice['paused'] ) ) $lines[] = 'Tras la restauración, este sitio no tiene ninguna automatización de respaldo activa.';
		return implode( ' ', $lines );
	}
	/**
	 * El aviso tal como se enseña (H26). Con lo pendiente abierto, sus automatizaciones se cuentan como las contará
	 * «Aplicar la del respaldo» (también en un aviso que anotó 1.0.73 o 1.0.74, que sólo contaba las distintas de las de
	 * aquí) y `replaces` dice lo de este sitio que se pierde al aplicarla. Sin poder abrirlo, lo anotado.
	 */
	private static function restore_notice_view() {
		$notice = self::restore_notice() + array( 'automations' => 0, 'connections' => 0, 'paused' => 0 );
		$notice['replaces'] = '';
		$pending = empty( $notice['pending'] ) ? null : self::restore_pending_open( $notice['pending'] );
		if ( null === $pending ) return $notice;
		$notice['automations'] = count( self::active_policies( self::restore_config_policies( $pending ) ) );
		$notice['replaces'] = self::restore_config_replaces( $pending );
		return $notice;
	}
	/** Estado del aviso (REST y WP-CLI); el mensaje dice también lo de este sitio que «Aplicar» reemplaza. */
	public static function service_restore_config() {
		$allowed = self::service_scope( false, false );
		if ( is_wp_error( $allowed ) ) return $allowed;
		$notice = self::restore_notice_view();
		$message = self::restore_notice_text( $notice );
		if ( '' !== $message && '' !== $notice['replaces'] ) $message .= ' ' . $notice['replaces'];
		return array( 'pending' => ! empty( $notice['pending'] ), 'automations' => (int) $notice['automations'], 'connections' => (int) $notice['connections'], 'no_active' => ! empty( $notice['no_active'] ), 'paused' => (int) $notice['paused'], 'message' => $message );
	}
	/**
	 * «Aplicar la del respaldo»: la configuración que trajo el respaldo pasa a ser la de este sitio (ajustes y destino
	 * manual, conexiones, avisos por correo y automatizaciones), con sus automatizaciones EN PAUSA (que no respalden dos
	 * sitios a la misma carpeta sin querer) hasta que se activen a mano. Nada se activa sin el usuario: si el respaldo no
	 * traía la opción de automatizaciones (el origen usaba las de serie), las de serie también quedan en pausa (borrar la
	 * opción las volvería a activar, revisión D2).
	 */
	public static function service_restore_config_apply( array $args = array() ) {
		$allowed = self::service_scope( ! empty( $args['network'] ), true );
		if ( is_wp_error( $allowed ) ) return $allowed;
		$notice = self::restore_notice();
		if ( empty( $notice['pending'] ) ) return self::service_error( 'no_pending', 'No hay configuración de un respaldo restaurado pendiente de aplicar.', 404 );
		$pending = self::restore_pending_open( $notice['pending'] );
		if ( null === $pending ) return self::service_error( 'pending_locked', 'La configuración pendiente del respaldo no abre con las claves de este servidor (cambiaron AUTH_KEY o SECURE_AUTH_KEY en wp-config.php): descártala.', 409 );
		if ( ! is_array( $pending['site'][ self::POLICIES ]['value'] ?? null ) ) $pending['site'][ self::POLICIES ] = array( 'value' => self::default_policies() );
		// Las mismas que contó el aviso antes de aplicar (H26).
		$paused = count( self::active_policies( self::restore_config_policies( $pending ) ) );
		foreach ( array( 'site', 'network' ) as $scope ) {
			if ( ! is_array( $pending[ $scope ][ self::POLICIES ]['value'] ?? null ) ) continue;
			foreach ( $pending[ $scope ][ self::POLICIES ]['value'] as &$policy ) if ( is_array( $policy ) ) $policy['enabled'] = false;
			unset( $policy );
		}
		self::keep_config_write( $pending );
		self::restore_notice_save( array( 'at' => time(), 'pending' => null, 'automations' => 0, 'connections' => 0, 'no_active' => ! self::active_policies( self::policies() ), 'paused' => $paused ) );
		return array( 'applied' => true, 'paused' => $paused, 'message' => 'Se aplicó la configuración de Backups del respaldo restaurado (ajustes y destino manual, conexiones, avisos por correo y automatizaciones)' . ( $paused ? ', con ' . $paused . ' ' . ( 1 === $paused ? 'automatización en pausa: actívala' : 'automatizaciones en pausa: actívalas' ) . ' en Automatizaciones' : '' ) . '.' );
	}
	/** «Descartar»: se olvida la configuración del respaldo y el aviso (la de este sitio sigue como está). */
	public static function service_restore_config_discard( array $args = array() ) {
		$allowed = self::service_scope( ! empty( $args['network'] ), true );
		if ( is_wp_error( $allowed ) ) return $allowed;
		self::restore_notice_save( array() );
		return array( 'discarded' => true, 'message' => 'Se descartó la configuración del respaldo restaurado; la de este sitio sigue como estaba.' );
	}
	/**
	 * «Aplicar la del respaldo» se confirma escribiendo APLICAR (H26), como RESTAURAR o QUITAR: lo comprueba el handler,
	 * no el navegador. El campo va en el formulario de la acción, en el escritorio y en la franja de Backups.
	 */
	private static function restore_config_confirm_field() {
		return '<input type="text" name="digitalisimo_backups_config_confirmation" placeholder="APLICAR" size="10" autocomplete="off" required aria-label="Escribe APLICAR para confirmar"> ';
	}
	/** Aviso con sus acciones (escritorio y tarjeta de Backups). */
	private static function restore_notice_html() {
		$notice = self::restore_notice_view();
		$text = self::restore_notice_text( $notice );
		if ( '' === $text ) return '';
		$form = function( $do, $label, $primary ) {
			$out = '<form style="display:inline" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			ob_start();
			wp_nonce_field( 'digitalisimo_backups_restore_config' );
			$out .= (string) ob_get_clean();
			return $out . '<input type="hidden" name="action" value="digitalisimo_backups_restore_config"><input type="hidden" name="do" value="' . esc_attr( $do ) . '">' . ( is_multisite() ? '<input type="hidden" name="digitalisimo_backups_network" value="1">' : '' ) . ( 'apply' === $do ? self::restore_config_confirm_field() : '' ) . '<button class="button' . ( $primary ? ' button-primary' : '' ) . '">' . esc_html( $label ) . '</button></form> ';
		};
		$pending = ! empty( $notice['pending'] );
		$actions = ( $pending ? $form( 'apply', 'Aplicar la del respaldo', true ) : '' ) . $form( 'discard', 'Descartar', false );
		// Lo que se pierde al aplicarla, a la vista antes del botón.
		$warning = $pending && '' !== $notice['replaces'] ? '<p class="digitalisimo-backups-warning"><strong>' . esc_html( $notice['replaces'] ) . '</strong></p>' : '';
		// Con la X (queja del propietario del 2026-11-22): cerrado, vuelve a los 7 días mientras siga pendiente, o antes si
		// cambia; en la franja de la pantalla de Backups sigue siempre a la vista.
		return Digitalisimo_Notices::html( 'backups-restore-config', 'warning', '<p><strong>Backups:</strong> ' . esc_html( $text ) . '</p>' . $warning . '<p>' . $actions . '</p>', array( 'hash' => array( $text, $pending ), 'cap' => is_multisite() ? 'manage_network_options' : 'manage_options', 'class' => 'digitalisimo-backups-restore-notice' ) );
	}
	/** admin_notices y network_admin_notices: sólo a quien gestiona los respaldos, en el sitio principal o la red. */
	public static function restore_config_admin_notice() {
		// En la pantalla de Backups va en su franja de avisos (no dos veces).
		if ( ! self::can_manage() || self::is_backups_screen() ) return;
		if ( is_multisite() && ! ( function_exists( 'is_network_admin' ) && is_network_admin() ) && ! is_main_site() ) return;
		try { echo self::restore_notice_html(); } catch ( Throwable $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	}
	/**
	 * admin-post de las dos acciones del aviso (la misma acción y el mismo nonce). «Aplicar la del respaldo» reemplaza
	 * las conexiones con nombre y las automatizaciones de este sitio: sin APLICAR escrito no cambia nada (H26). REST y
	 * WP-CLI no lo piden: su orden ya es explícita y siguen como estaban.
	 */
	public static function restore_config_request() {
		if ( ! self::can_manage() ) wp_die( 'No autorizado.' );
		check_admin_referer( 'digitalisimo_backups_restore_config' );
		$args = array( 'network' => is_multisite() );
		$apply = 'apply' === ( $_POST['do'] ?? '' );
		if ( $apply && 'APLICAR' !== strtoupper( trim( (string) wp_unslash( $_POST['digitalisimo_backups_config_confirmation'] ?? '' ) ) ) ) self::redirect( false, 'Escribe APLICAR para confirmar: la configuración del respaldo reemplaza la de este sitio. No se cambió nada.' );
		$result = $apply ? self::service_restore_config_apply( $args ) : self::service_restore_config_discard( $args );
		if ( is_wp_error( $result ) ) self::redirect( false, $result->get_error_message() );
		self::redirect( true, $result['message'] );
	}
	/** Eventos de WP-Cron de las automatizaciones: fuera los de las que no son de este sitio; las suyas, a su hora. */
	private static function keep_config_events() {
		try {
			$ids = array();
			foreach ( self::policies() as $policy ) $ids[ (string) ( $policy['id'] ?? '' ) ] = true;
			if ( function_exists( '_get_cron_array' ) && function_exists( 'wp_unschedule_event' ) ) {
				foreach ( (array) _get_cron_array() as $timestamp => $hooks ) {
					foreach ( (array) ( $hooks['digitalisimo_backups_policy'] ?? array() ) as $event ) {
						$args = (array) ( $event['args'] ?? array() );
						if ( ! isset( $ids[ (string) ( $args[0] ?? '' ) ] ) ) wp_unschedule_event( (int) $timestamp, 'digitalisimo_backups_policy', $args );
					}
				}
			}
			if ( function_exists( 'wp_schedule_event' ) && ( ! is_multisite() || is_main_site() ) ) self::sync_policy_schedules();
		} catch ( Throwable $e ) {
			if ( function_exists( 'error_log' ) ) error_log( 'DIGITALÍSIMO Backups: automatizaciones tras restaurar: ' . $e->getMessage() );
		}
	}
	/** La foto que anotó el diario de esta restauración (o null); $mark, la marca de ese diario ('' si no la lleva). */
	private static function keep_config_from_journal( &$mark = null ) {
		$path = trailingslashit( self::local_directory() ) . self::RESTORE_JOURNAL;
		$data = is_file( $path ) && ! is_link( $path ) ? json_decode( (string) @file_get_contents( $path ), true ) : null;
		$mark = self::keep_config_mark( $data );
		return is_array( $data ) && is_array( $data['config_kept'] ?? null ) ? $data['config_kept'] : null;
	}
	/** La marca de un diario de restauración (aleatoria, una por restauración), o ''. */
	private static function keep_config_mark( $journal ) {
		$mark = is_array( $journal ) ? ( $journal['restore_id'] ?? '' ) : '';
		return is_string( $mark ) && preg_match( '/^[0-9a-f]{32}$/D', $mark ) ? $mark : '';
	}
}
// El aviso tras restaurar (H23): en el escritorio y sus dos acciones.
if ( function_exists( 'add_action' ) ) {
	add_action( 'admin_notices', array( 'Digitalisimo_Backups', 'restore_config_admin_notice' ) );
	add_action( 'network_admin_notices', array( 'Digitalisimo_Backups', 'restore_config_admin_notice' ) );
	add_action( 'admin_post_digitalisimo_backups_restore_config', array( 'Digitalisimo_Backups', 'restore_config_request' ) );
}
