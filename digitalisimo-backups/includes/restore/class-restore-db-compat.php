<?php
defined( 'ABSPATH' ) || exit;

/**
 * Compatibilidad de la base de datos al restaurar en otro servidor (rama backups/restore-db-compat, brechas 4, 8, 10, 11,
 * 14, 15 y 16 del estudio frente a UpdraftPlus 1.26.14).
 *
 * Qué decide y cuándo: la pasada en seco del guardián (verificación, antes de la copia previa) anota lo que trae cada
 * CREATE TABLE (juegos de caracteres, intercalaciones, motores, opciones propias de MariaDB o de MySQL, columnas
 * generadas), las vistas, la sentencia más grande y, de las opciones, los plugins y temas activos. Con eso y lo que
 * este servidor tiene (`SHOW CHARACTER SET`, `SHOW COLLATION`, `SHOW ENGINES`, `@@max_allowed_packet`) plan() decide
 * la sustitución **antes de tocar nada**: lo que no tiene arreglo seguro (una fila que no cabe en el paquete, emojis en un
 * servidor sin utf8mb4) rechaza la restauración ahí, y lo demás se aplica en la importación sobre los tokens exactos que
 * leyó el guardián (nunca por búsqueda de texto) y se dice en la verificación, en el trabajo y en el informe.
 *
 * - Intercalación que el servidor no tiene (`utf8mb4_0900_ai_ci` en MariaDB, `utf8mb4_uca1400_ai_ci` en MySQL): la más
 *   cercana que sí tiene, con la misma sensibilidad (mayúsculas, acentos, binaria) y el mismo idioma si lo hay; las
 *   genéricas sin distinguir mayúsculas van a `utf8mb4_unicode_520_ci` (la de las tablas del núcleo de WordPress).
 *   El filtro `digitalisimo_backups_restore_collation` puede elegir otra que el servidor tenga.
 * - Juego de caracteres que no tiene: `utf8mb3` ↔ `utf8` (el mismo); `utf8mb4` → `utf8mb3` sólo si ninguna fila trae
 *   caracteres de 4 bytes (si los trae, se rechaza: se perderían); cualquier otro → `utf8mb4` (el SQL ya viene en utf8mb4).
 * - Motor que no tiene (Aria, ARCHIVE, TokuDB…) → InnoDB (no MyISAM como UpdraftPlus); un `ROW_FORMAT` que InnoDB no
 *   admite (`PAGE` de Aria, `FIXED`…) se quita.
 * - Opciones de tabla propias de MariaDB en MySQL (`PAGE_CHECKSUM`, `TRANSACTIONAL`…) y de MySQL en MariaDB
 *   (`SECONDARY_ENGINE`, `AUTOEXTEND_SIZE`…) se quitan; `PERSISTENT` de MariaDB es `STORED` en MySQL.
 * - Columnas generadas: el exportador ya no las escribe; un respaldo anterior que las trae importa `DEFAULT` en ellas.
 * - Vistas: se importan aparte y se crean al final del cambio sin `DEFINER` y con `SQL SECURITY INVOKER`. Disparadores,
 *   rutinas y eventos no se respaldan (se avisa al respaldar y en la verificación).
 * - `NO_AUTO_VALUE_ON_ZERO` (y sin `NO_BACKSLASH_ESCAPES`) en la sesión de cada tramo de la importación; nunca `SET GLOBAL`.
 * - Versiones del origen (PHP, WordPress, base de datos y lo que piden sus plugins y tema activos): avisos, sin bloquear.
 */
final class Digitalisimo_Backups_Db_Compat {
	/** La decisión de la verificación, en el área de trabajo de la restauración. */
	const FILE = 'db-compat.json';
	/** Lo que pasó con las vistas al final del cambio. */
	const VIEWS_FILE = 'db-views.json';
	/** Opciones de tabla que sólo entiende MariaDB (se quitan en MySQL) y sólo MySQL (se quitan en MariaDB). */
	const MARIADB_OPTIONS = array( 'PAGE_CHECKSUM', 'TRANSACTIONAL', 'PAGE_COMPRESSED', 'PAGE_COMPRESSION_LEVEL', 'IETF_QUOTES', 'ENCRYPTED', 'ENCRYPTION_KEY_ID', 'SEQUENCE' );
	const MYSQL_OPTIONS = array( 'SECONDARY_ENGINE', 'SECONDARY_ENGINE_ATTRIBUTE', 'ENGINE_ATTRIBUTE', 'AUTOEXTEND_SIZE', 'COMPRESSION', 'ENCRYPTION' );
	/** Códigos de idioma de las intercalaciones `_0900_` de MySQL y su nombre en las de siempre (y en `uca1400` de MariaDB). */
	const LANGUAGES = array( 'is' => 'icelandic', 'lv' => 'latvian', 'ro' => 'romanian', 'sl' => 'slovenian', 'pl' => 'polish', 'et' => 'estonian', 'es' => 'spanish', 'sv' => 'swedish', 'tr' => 'turkish', 'cs' => 'czech', 'da' => 'danish', 'lt' => 'lithuanian', 'sk' => 'slovak', 'es_trad' => 'spanish2', 'la' => 'roman', 'eo' => 'esperanto', 'hu' => 'hungarian', 'hr' => 'croatian', 'vi' => 'vietnamese', 'de_pb' => 'german2' );
	/** Holgura del protocolo sobre la longitud de la sentencia al compararla con max_allowed_packet. */
	const PACKET_MARGIN = 1024;
	/** Máximo de nombres que se guardan de cada cosa (tablas por intercalación, plugins activos…). */
	const CAP = 50;

	private static $server = null;

	/* ------------------------------------------------------------------------------------------- Este servidor */

	/** SQLite de WordPress (Playground): sin intercalaciones, motores ni sesión de MySQL; nada que sustituir. */
	public static function is_sqlite() {
		global $wpdb;
		return defined( 'SQLITE_DRIVER_VERSION' ) || defined( 'SQLITE_DB_DROPIN_VERSION' ) || ( defined( 'DB_ENGINE' ) && 'sqlite' === strtolower( (string) DB_ENGINE ) ) || ( is_object( $wpdb ) && class_exists( 'WP_SQLite_DB', false ) && $wpdb instanceof WP_SQLite_DB );
	}

	/**
	 * Lo que tiene este servidor. Una lista que no se pudo leer es null («no se sabe»): entonces no se sustituye nada de
	 * esa clase (nunca se da por ausente lo que no se pudo comprobar). `kind`: mysql, mariadb, sqlite o '' (no se sabe).
	 */
	public static function server( $refresh = false ) {
		if ( null !== self::$server && ! $refresh ) return self::$server;
		global $wpdb;
		$info = array( 'kind' => '', 'version' => '', 'info' => '', 'charsets' => null, 'collations' => null, 'engines' => null, 'default_engine' => '', 'packet' => 0 );
		if ( self::is_sqlite() ) {
			$info['kind'] = 'sqlite';
		} elseif ( is_object( $wpdb ) ) {
			$text = method_exists( $wpdb, 'db_server_info' ) ? (string) $wpdb->db_server_info() : '';
			$info['info'] = substr( preg_replace( '/[^\x20-\x7E]/', '', $text ), 0, 120 );
			$info['version'] = method_exists( $wpdb, 'db_version' ) ? (string) $wpdb->db_version() : '';
			if ( '' !== $text ) $info['kind'] = false !== stripos( $text, 'mariadb' ) ? 'mariadb' : 'mysql';
			$suppress = method_exists( $wpdb, 'suppress_errors' ) ? $wpdb->suppress_errors( true ) : null;
			try {
				$rows = self::rows( 'SHOW CHARACTER SET' );
				if ( $rows ) {
					$info['charsets'] = array();
					foreach ( $rows as $row ) {
						$name = strtolower( (string) ( $row['Charset'] ?? '' ) );
						if ( '' !== $name ) $info['charsets'][ $name ] = strtolower( (string) ( $row['Default collation'] ?? '' ) );
					}
				}
				$rows = self::rows( 'SHOW COLLATION' );
				if ( $rows ) {
					$info['collations'] = array();
					foreach ( $rows as $row ) {
						$name = strtolower( (string) ( $row['Collation'] ?? '' ) );
						$charset = strtolower( (string) ( $row['Charset'] ?? '' ) );
						// MariaDB 10.10+ lista las UCA 14.0 sin juego de caracteres (`uca1400_ai_ci`, Charset NULL): su nombre
						// completo (`utf8mb4_uca1400_ai_ci`) sale de COLLATION_CHARACTER_SET_APPLICABILITY.
						if ( '' !== $name && '' !== $charset ) $info['collations'][ $name ] = $charset;
					}
					foreach ( self::rows( 'SELECT * FROM information_schema.COLLATION_CHARACTER_SET_APPLICABILITY' ) as $row ) {
						$name = strtolower( (string) ( $row['FULL_COLLATION_NAME'] ?? $row['COLLATION_NAME'] ?? '' ) );
						$charset = strtolower( (string) ( $row['CHARACTER_SET_NAME'] ?? '' ) );
						if ( '' !== $name && '' !== $charset && 0 === strpos( $name, $charset . '_' ) ) $info['collations'][ $name ] = $charset;
					}
				}
				$rows = self::rows( 'SHOW ENGINES' );
				if ( $rows ) {
					$info['engines'] = array();
					foreach ( $rows as $row ) {
						$support = strtoupper( (string) ( $row['Support'] ?? '' ) );
						$name = strtoupper( (string) ( $row['Engine'] ?? '' ) );
						if ( '' === $name || ! in_array( $support, array( 'YES', 'DEFAULT' ), true ) ) continue;
						$info['engines'][ $name ] = true;
						if ( 'DEFAULT' === $support ) $info['default_engine'] = $name;
					}
				}
				$packet = method_exists( $wpdb, 'get_var' ) ? $wpdb->get_var( 'SELECT @@max_allowed_packet' ) : null;
				if ( is_numeric( $packet ) && (int) $packet > 0 ) $info['packet'] = (int) $packet;
			} catch ( Throwable $e ) {
				// Lo que no se pudo leer queda como «no se sabe».
				unset( $e );
			} finally {
				if ( null !== $suppress ) $wpdb->suppress_errors( $suppress );
			}
		}
		$filtered = function_exists( 'apply_filters' ) ? apply_filters( 'digitalisimo_backups_db_server', $info ) : $info;
		self::$server = is_array( $filtered ) ? $filtered + $info : $info;
		return self::$server;
	}
	private static function rows( $query ) {
		global $wpdb;
		if ( ! method_exists( $wpdb, 'get_results' ) ) return array();
		$rows = $wpdb->get_results( $query, ARRAY_A );
		return is_array( $rows ) && empty( $wpdb->last_error ) ? $rows : array();
	}

	/* ------------------------------------------------------------------------------------------- Juegos de caracteres e intercalaciones */

	/** El juego de caracteres de una intercalación (`utf8mb4_0900_ai_ci` → utf8mb4), según este servidor o su nombre. */
	public static function charset_of( $collation, array $server = array() ) {
		$collation = strtolower( (string) $collation );
		if ( isset( $server['collations'][ $collation ] ) ) return $server['collations'][ $collation ];
		if ( 'binary' === $collation ) return 'binary';
		foreach ( array( 'utf8mb4', 'utf8mb3', 'utf8', 'utf16le', 'utf16', 'utf32', 'ucs2', 'latin1', 'latin2', 'ascii', 'binary' ) as $charset ) if ( 0 === strpos( $collation, $charset . '_' ) ) return $charset;
		$cut = strpos( $collation, '_' );
		return false === $cut ? $collation : substr( $collation, 0, $cut );
	}
	/** Nombre con el que el servidor tiene un juego de caracteres (`utf8` y `utf8mb3` son el mismo), o null. */
	public static function charset_available( $charset, array $server ) {
		$charset = strtolower( (string) $charset );
		if ( ! is_array( $server['charsets'] ?? null ) ) return $charset;
		if ( isset( $server['charsets'][ $charset ] ) ) return $charset;
		$alias = array( 'utf8' => 'utf8mb3', 'utf8mb3' => 'utf8' );
		return isset( $alias[ $charset ], $server['charsets'][ $alias[ $charset ] ] ) ? $alias[ $charset ] : null;
	}
	/** Nombre con el que el servidor tiene una intercalación (`utf8_x` y `utf8mb3_x` son la misma), o null. */
	public static function collation_available( $collation, array $server ) {
		$collation = strtolower( (string) $collation );
		if ( ! is_array( $server['collations'] ?? null ) ) return $collation;
		if ( isset( $server['collations'][ $collation ] ) ) return $collation;
		if ( 0 === strpos( $collation, 'utf8mb3_' ) && isset( $server['collations'][ 'utf8_' . substr( $collation, 8 ) ] ) ) return 'utf8_' . substr( $collation, 8 );
		if ( 0 === strpos( $collation, 'utf8_' ) && isset( $server['collations'][ 'utf8mb3_' . substr( $collation, 5 ) ] ) ) return 'utf8mb3_' . substr( $collation, 5 );
		return null;
	}
	/**
	 * El juego de caracteres de este servidor que sustituye a uno que no tiene, y si se pierde algo (utf8mb4 → utf8mb3:
	 * los caracteres de 4 bytes). null si no hay ninguno.
	 */
	public static function closest_charset( $charset, array $server ) {
		$charset = strtolower( (string) $charset );
		$candidates = 'utf8mb4' === $charset ? array( 'utf8mb3', 'utf8' ) : array( 'utf8mb4', 'utf8mb3', 'utf8' );
		foreach ( $candidates as $candidate ) {
			$name = self::charset_available( $candidate, $server );
			if ( null !== $name && $name !== $charset ) return array( 'charset' => $name, 'lossy' => 'utf8mb4' === $charset );
		}
		return null;
	}
	/**
	 * La intercalación de `$charset` en este servidor más parecida a `$from`: misma sensibilidad (sin distinguir
	 * mayúsculas, con acentos, con mayúsculas, binaria) y mismo idioma si lo hay; nunca una que distinga menos que la
	 * original salvo la genérica sin mayúsculas, que va a `unicode_520_ci` como las tablas del núcleo. null si no hay.
	 */
	public static function closest_collation( $from, $charset, array $server ) {
		$from = strtolower( (string) $from );
		$charset = strtolower( (string) $charset );
		$source = self::charset_of( $from, $server );
		$rest = 0 === strpos( $from, $source . '_' ) ? substr( $from, strlen( $source ) + 1 ) : $from;
		$binary = 'bin' === $rest || '_bin' === substr( $rest, -4 ) || 'binary' === $from;
		$sense = $binary ? 'bin' : ( preg_match( '/(?:^|_)(?:as_)?cs$/', $rest ) ? 'cs' : ( preg_match( '/(?:^|_)as_ci$/', $rest ) ? 'as_ci' : 'ci' ) );
		$language = '';
		$code = '';
		if ( preg_match( '/^([a-z]{2}(?:_[a-z]+)?)_0900_/', $rest, $m ) ) {
			$code = $m[1];
			$language = self::LANGUAGES[ $code ] ?? '';
		} elseif ( preg_match( '/^uca1400_([a-z0-9]+)_(?:nopad_)?a[is]_c[is]$/', $rest, $m ) ) {
			$language = $m[1];
		} elseif ( preg_match( '/^([a-z]+[0-9]?)_(?:520_)?(?:nopad_)?ci$/', $rest, $m ) && ! in_array( $m[1], array( 'general', 'unicode', 'nopad', 'uca1400', 'swedish' === $m[1] && 'latin1' === $source ? 'swedish' : '' ), true ) ) {
			$language = $m[1];
		}
		if ( '' !== $language && '' === $code ) $code = (string) array_search( $language, self::LANGUAGES, true );
		$c = $charset . '_';
		$candidates = array();
		if ( 'bin' === $sense ) {
			$candidates = array( $c . 'bin', $c . '0900_bin', $c . 'nopad_bin' );
		} else {
			$suffix = array( 'ci' => 'ai_ci', 'as_ci' => 'as_ci', 'cs' => 'as_cs' )[ $sense ];
			if ( '' !== $language ) {
				$candidates[] = $c . 'uca1400_' . $language . '_' . $suffix;
				if ( '' !== $code ) $candidates[] = $c . $code . '_0900_' . $suffix;
				if ( 'ci' === $sense ) { $candidates[] = $c . $language . '_520_ci'; $candidates[] = $c . $language . '_ci'; }
			}
			if ( 'ci' === $sense ) $candidates = array_merge( $candidates, array( $c . 'unicode_520_ci', $c . 'unicode_ci', $c . 'uca1400_ai_ci', $c . '0900_ai_ci', $c . 'general_ci' ) );
			else $candidates = array_merge( $candidates, array( $c . 'uca1400_' . $suffix, $c . '0900_' . $suffix, $c . 'bin' ) );
		}
		$candidates[] = (string) ( $server['charsets'][ $charset ] ?? '' );
		if ( 'ci' === $sense ) $candidates[] = $c . 'bin';
		$chosen = null;
		foreach ( $candidates as $candidate ) {
			if ( '' === $candidate || $candidate === $from ) continue;
			$name = self::collation_available( $candidate, $server );
			if ( null !== $name && is_array( $server['collations'] ?? null ) && $name !== $from ) { $chosen = $name; break; }
		}
		// Quien administra el sitio puede elegir otra, siempre que este servidor la tenga y sea del mismo juego.
		if ( function_exists( 'apply_filters' ) ) {
			$forced = apply_filters( 'digitalisimo_backups_restore_collation', $chosen, $from, $charset, $server );
			if ( is_string( $forced ) && $forced !== $chosen && preg_match( '/^[a-z0-9_]{1,64}$/D', strtolower( $forced ) ) ) {
				$name = self::collation_available( $forced, $server );
				if ( null !== $name && self::same_charset( self::charset_of( $name, $server ), $charset ) ) $chosen = $name;
			}
		}
		return $chosen;
	}
	private static function same_charset( $a, $b ) {
		$normal = function( $name ) { return 'utf8mb3' === $name ? 'utf8' : $name; };
		return $normal( strtolower( (string) $a ) ) === $normal( strtolower( (string) $b ) );
	}

	/* ------------------------------------------------------------------------------------------- Decisión */

	/** Une lo anotado por dos tramos de la pasada en seco (Digitalisimo_Backups_Sql_Guard::found()). */
	public static function merge( array $a, array $b ) {
		foreach ( array( 'charsets', 'collations', 'engines', 'options' ) as $kind ) {
			foreach ( (array) ( $b[ $kind ] ?? array() ) as $name => $tables ) {
				$a[ $kind ][ $name ] = array_slice( array_values( array_unique( array_merge( (array) ( $a[ $kind ][ $name ] ?? array() ), (array) $tables ) ) ), 0, 20 );
			}
		}
		foreach ( (array) ( $b['generated'] ?? array() ) as $table => $columns ) $a['generated'][ $table ] = array_values( array_unique( array_merge( (array) ( $a['generated'][ $table ] ?? array() ), (array) $columns ) ) );
		foreach ( (array) ( $b['row_formats'] ?? array() ) as $engine => $formats ) foreach ( (array) $formats as $format => $tables ) $a['row_formats'][ $engine ][ $format ] = array_slice( array_values( array_unique( array_merge( (array) ( $a['row_formats'][ $engine ][ $format ] ?? array() ), (array) $tables ) ) ), 0, 20 );
		foreach ( array( 'views', 'plugins', 'themes', 'four_byte' ) as $kind ) {
			foreach ( (array) ( $b[ $kind ] ?? array() ) as $name => $value ) if ( isset( $a[ $kind ][ $name ] ) || count( (array) ( $a[ $kind ] ?? array() ) ) < self::CAP * 4 ) $a[ $kind ][ $name ] = $value;
		}
		$a['persistent'] = (int) ( $a['persistent'] ?? 0 ) + (int) ( $b['persistent'] ?? 0 );
		$largest = (array) ( $a['largest'] ?? array() );
		if ( (int) ( $b['largest'][0] ?? 0 ) > (int) ( $largest[0] ?? 0 ) ) $largest = (array) $b['largest'];
		if ( $largest ) $a['largest'] = $largest;
		return $a;
	}

	/**
	 * La decisión, con lo anotado por la pasada en seco ($found) y este servidor. Devuelve:
	 * `charsets`, `collations` (origen → sustituto, en minúsculas), `engines` (ORIGEN → sustituto), `drop_options`,
	 * `persistent` (PERSISTENT → STORED), `generated` (tabla → columnas), `views` (nombres), `notes` (lo que se cambia,
	 * para la verificación y el informe), `warnings` y `problems` (lo que impide restaurar sin perder datos).
	 * $context: `grown` (la sentencia más grande ya reubicada), `parts` (null es todo).
	 */
	public static function plan( array $found, array $server, array $context = array() ) {
		$plan = array( 'charsets' => array(), 'collations' => array(), 'engines' => array(), 'drop_options' => array(), 'persistent' => false, 'generated' => array(), 'views' => array(), 'notes' => array(), 'warnings' => array(), 'problems' => array(), 'server' => array( 'kind' => (string) ( $server['kind'] ?? '' ), 'version' => (string) ( $server['version'] ?? '' ) ) );
		$list = function( array $tables ) { $tables = array_values( array_unique( array_map( 'strval', $tables ) ) ); return implode( ', ', array_slice( $tables, 0, 5 ) ) . ( count( $tables ) > 5 ? '…' : '' ); };
		$label = self::server_label( $server );
		// Juegos de caracteres.
		if ( is_array( $server['charsets'] ?? null ) && $server['charsets'] ) {
			foreach ( (array) ( $found['charsets'] ?? array() ) as $charset => $tables ) {
				$charset = strtolower( (string) $charset );
				$name = self::charset_available( $charset, $server );
				if ( null !== $name ) { if ( $name !== $charset ) $plan['charsets'][ $charset ] = $name; continue; }
				$closest = self::closest_charset( $charset, $server );
				if ( null === $closest ) { $plan['problems'][] = 'El respaldo usa el juego de caracteres ' . $charset . ' (' . $list( (array) $tables ) . ') y ' . $label . ' no tiene ninguno que lo sustituya sin perder datos.'; continue; }
				$plan['charsets'][ $charset ] = $closest['charset'];
				$lossy = $closest['lossy'] ? array_values( array_intersect( array_keys( (array) ( $found['four_byte'] ?? array() ) ), (array) $tables ) ) : array();
				if ( $lossy ) $plan['problems'][] = $label . ' no tiene utf8mb4 y el respaldo guarda caracteres de 4 bytes (emojis, algunos símbolos) en ' . $list( $lossy ) . ': con ' . $closest['charset'] . ' se perderían. Restaura en un servidor con utf8mb4 (MySQL 5.5.3 o MariaDB 5.5 en adelante).';
				elseif ( $closest['lossy'] ) $plan['warnings'][] = $label . ' no tiene utf8mb4: las tablas ' . $list( (array) $tables ) . ' se crean con ' . $closest['charset'] . ' (ninguna fila del respaldo trae caracteres de 4 bytes, pero no podrán guardar emojis).';
				else $plan['notes'][] = 'Juego de caracteres ' . $charset . ' → ' . $closest['charset'] . ' (' . $list( (array) $tables ) . '): ' . $label . ' no tiene ' . $charset . '.';
			}
		}
		// Intercalaciones: las que el servidor no tiene y las de un juego de caracteres que cambió.
		if ( is_array( $server['collations'] ?? null ) && $server['collations'] ) {
			foreach ( (array) ( $found['collations'] ?? array() ) as $collation => $tables ) {
				$collation = strtolower( (string) $collation );
				$charset = self::charset_of( $collation, $server );
				$target = $plan['charsets'][ $charset ] ?? $charset;
				$name = self::collation_available( $collation, $server );
				if ( null !== $name && self::same_charset( self::charset_of( $name, $server ), $target ) ) { if ( $name !== $collation ) $plan['collations'][ $collation ] = $name; continue; }
				$closest = self::closest_collation( $collation, $target, $server );
				if ( null === $closest ) { $plan['problems'][] = 'El respaldo usa la intercalación ' . $collation . ' (' . $list( (array) $tables ) . ') y ' . $label . ' no tiene ninguna parecida en ' . $target . '.'; continue; }
				$plan['collations'][ $collation ] = $closest;
				$plan['notes'][] = 'Intercalación ' . $collation . ' → ' . $closest . ' (' . $list( (array) $tables ) . '): ' . $label . ' no la tiene.';
			}
		}
		// Motores.
		if ( is_array( $server['engines'] ?? null ) && $server['engines'] ) {
			$replacement = isset( $server['engines']['INNODB'] ) ? 'InnoDB' : (string) ( $server['default_engine'] ?? '' );
			foreach ( (array) ( $found['engines'] ?? array() ) as $engine => $tables ) {
				$engine = strtoupper( (string) $engine );
				if ( isset( $server['engines'][ $engine ] ) || ( 'MARIA' === $engine && isset( $server['engines']['ARIA'] ) ) ) continue;
				if ( '' === $replacement ) { $plan['problems'][] = 'El respaldo usa el motor ' . $engine . ' (' . $list( (array) $tables ) . ') y ' . $label . ' no lo tiene.'; continue; }
				$plan['engines'][ $engine ] = $replacement;
				$plan['notes'][] = 'Motor ' . $engine . ' → ' . $replacement . ' (' . $list( (array) $tables ) . '): ' . $label . ' no lo tiene' . ( in_array( $engine, array( 'ARIA', 'MARIA' ), true ) ? '; se quitan también sus opciones (PAGE_CHECKSUM, TRANSACTIONAL)' : '' ) . '.';
				// Y el ROW_FORMAT que el motor de destino no admite (H5): FIXED o PAGE en InnoDB harían fallar el CREATE TABLE.
				foreach ( (array) ( $found['row_formats'][ $engine ] ?? array() ) as $format => $with ) {
					if ( ! Digitalisimo_Backups_Sql_Guard::row_format_dropped( $format, $replacement ) ) continue;
					$plan['notes'][] = 'Se quita ROW_FORMAT=' . strtoupper( (string) $format ) . ' (' . $list( (array) $with ) . '): ' . $replacement . ' no lo admite.';
				}
			}
		}
		// Opciones de tabla del otro servidor.
		$kind = (string) ( $server['kind'] ?? '' );
		$foreign = 'mysql' === $kind ? self::MARIADB_OPTIONS : ( 'mariadb' === $kind ? self::MYSQL_OPTIONS : array() );
		if ( $plan['engines'] ) $foreign = array_values( array_unique( array_merge( $foreign, array( 'PAGE_CHECKSUM', 'TRANSACTIONAL' ) ) ) );
		foreach ( (array) ( $found['options'] ?? array() ) as $option => $tables ) {
			$option = strtoupper( (string) $option );
			if ( ! in_array( $option, $foreign, true ) ) continue;
			$plan['drop_options'][] = $option;
			$plan['notes'][] = 'Se quita la opción de tabla ' . $option . ' (' . $list( (array) $tables ) . '), que ' . $label . ' no entiende.';
		}
		if ( 'mysql' === $kind && ! empty( $found['persistent'] ) ) {
			$plan['persistent'] = true;
			$plan['notes'][] = 'Columnas generadas PERSISTENT (de MariaDB) → STORED, como las llama MySQL.';
		}
		// Columnas generadas: el valor lo calcula este servidor.
		foreach ( (array) ( $found['generated'] ?? array() ) as $table => $columns ) $plan['generated'][ (string) $table ] = array_values( array_map( 'strval', (array) $columns ) );
		if ( $plan['generated'] ) $plan['notes'][] = 'Columnas generadas (' . $list( array_map( function( $table, $columns ) { return $table . '.' . implode( '/', $columns ); }, array_keys( $plan['generated'] ), $plan['generated'] ) ) . '): las calcula este servidor (un respaldo anterior que trae su valor importa DEFAULT).';
		// Vistas: se crean al final del cambio.
		$plan['views'] = array_values( array_map( 'strval', array_keys( (array) ( $found['views'] ?? array() ) ) ) );
		if ( $plan['views'] ) $plan['notes'][] = 'Vistas (' . $list( $plan['views'] ) . '): se crean al final del cambio, sin DEFINER y con SQL SECURITY INVOKER.';
		// La sentencia más grande (una fila: el exportador escribe un INSERT por fila) contra max_allowed_packet.
		$packet = (int) ( $server['packet'] ?? 0 );
		$largest = (int) max( (int) ( $found['largest'][0] ?? 0 ), (int) ( $context['grown'] ?? 0 ) );
		if ( $packet > 0 && $largest > 0 && $largest + self::PACKET_MARGIN > $packet ) {
			$needed = (int) ceil( ( $largest + self::PACKET_MARGIN ) / 1048576 );
			$plan['problems'][] = 'La fila más grande del respaldo' . ( '' !== (string) ( $found['largest'][1] ?? '' ) ? ' (tabla ' . $found['largest'][1] . ')' : '' ) . ' mide ' . self::megabytes( $largest ) . ' y no cabe en max_allowed_packet de ' . $label . ' (' . self::megabytes( $packet ) . '). Súbelo a ' . max( $needed, 1 ) . ' MB o más (max_allowed_packet en la configuración de MySQL o MariaDB, o pídelo al hosting) y vuelve a restaurar.';
		}
		$plan['drop_options'] = array_values( array_unique( $plan['drop_options'] ) );
		return $plan;
	}
	private static function server_label( array $server ) {
		$kind = array( 'mysql' => 'MySQL', 'mariadb' => 'MariaDB', 'sqlite' => 'SQLite' )[ (string) ( $server['kind'] ?? '' ) ] ?? '';
		$version = (string) ( $server['version'] ?? '' );
		return '' === $kind ? 'este servidor de base de datos' : 'este servidor (' . $kind . ( '' !== $version ? ' ' . $version : '' ) . ')';
	}
	private static function megabytes( $bytes ) { return number_format( $bytes / 1048576, 1, ',', '.' ) . ' MB'; }
	/** Lo que el guardián aplica en la importación: sólo las claves de sustitución. */
	public static function guard_plan( array $plan ) {
		return array_intersect_key( $plan, array_flip( array( 'charsets', 'collations', 'engines', 'drop_options', 'persistent', 'generated' ) ) );
	}
	/** Si la decisión cambia algo de las sentencias (para no tocar el guardián cuando no hace falta). */
	public static function changes( array $plan ) {
		foreach ( self::guard_plan( $plan ) as $value ) if ( ! empty( $value ) ) return true;
		return false;
	}

	/* ------------------------------------------------------------------------------------------- Guardián y sesión */

	/**
	 * La pasada en seco anota lo necesario. $data: el manifiesto (vistas declaradas). Con un servidor sin utf8mb4 mira
	 * además qué tablas traen caracteres de 4 bytes.
	 */
	public static function attach_scan( $guard, array $data = array() ) {
		if ( ! method_exists( $guard, 'collect' ) ) return $guard;
		$server = self::is_sqlite() ? array() : self::server();
		$four = is_array( $server['charsets'] ?? null ) && $server['charsets'] && null === self::charset_available( 'utf8mb4', $server );
		$guard->collect( array( 'four_byte' => $four ) );
		$guard->views( 'defer', self::declared_views( $data ) );
		return $guard;
	}
	/** Tras cada sentencia de la pasada en seco: dónde está la de una vista (posición y longitud en source.sql). */
	public static function scanned( $guard, array $cursor, $start, $end ) {
		$view = method_exists( $guard, 'last_view' ) ? $guard->last_view() : null;
		if ( null !== $view && $end > $start && count( (array) ( $cursor['compat_views'] ?? array() ) ) < 200 ) $cursor['compat_views'][] = array( (int) $start, (int) ( $end - $start ), (string) $view );
		return $cursor;
	}
	/** La importación aplica la decisión y deja las vistas para el final. */
	public static function attach_import( $guard, array $plan, array $data = array() ) {
		if ( ! method_exists( $guard, 'compat' ) ) return $guard;
		if ( self::changes( $plan ) ) $guard->compat( self::guard_plan( $plan ) );
		$guard->views( 'defer', array_merge( self::declared_views( $data ), (array) ( $plan['views'] ?? array() ) ) );
		// Un respaldo anterior a esta versión escribía una vista como tabla (DROP TABLE, su CREATE VIEW y sus filas): sólo
		// cuenta la definición, que se crea al final.
		if ( ! empty( $plan['views'] ) ) $guard->skip_tables( (array) $plan['views'] );
		return $guard;
	}
	public static function declared_views( array $data ) {
		return array_values( array_filter( array_map( 'strval', (array) ( $data['database_views'] ?? array() ) ), 'strlen' ) );
	}

	/**
	 * Sesión de un tramo de la importación: `NO_AUTO_VALUE_ON_ZERO` (una fila con 0 en su columna AUTO_INCREMENT conserva
	 * el 0, como mysqldump y phpMyAdmin) y sin `NO_BACKSLASH_ESCAPES` (el SQL escapa con barra invertida, como lo lee el
	 * guardián). Sólo la sesión (nunca `SET GLOBAL`). Devuelve el modo anterior para devolverlo al terminar el tramo.
	 */
	public static function session_begin() {
		global $wpdb;
		if ( self::is_sqlite() || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'query' ) ) return null;
		// Una conexión que no responde a esto (un drop-in, un doble de pruebas estricto) sigue como estaba.
		try {
			$previous = method_exists( $wpdb, 'get_var' ) ? $wpdb->get_var( 'SELECT @@SESSION.sql_mode' ) : null;
			if ( is_string( $previous ) ) {
				$modes = array_filter( array_map( 'trim', explode( ',', strtoupper( $previous ) ) ), 'strlen' );
				$modes = array_values( array_diff( $modes, array( 'NO_BACKSLASH_ESCAPES' ) ) );
				if ( ! in_array( 'NO_AUTO_VALUE_ON_ZERO', $modes, true ) ) $modes[] = 'NO_AUTO_VALUE_ON_ZERO';
				$wpdb->query( "SET SESSION sql_mode = '" . implode( ',', array_filter( $modes, function( $mode ) { return (bool) preg_match( '/^[A-Z_]+$/D', $mode ); } ) ) . "'" );
				return $previous;
			}
			$wpdb->query( "SET SESSION sql_mode = CONCAT_WS(',', NULLIF(REPLACE(@@SESSION.sql_mode, 'NO_BACKSLASH_ESCAPES', ''), ''), 'NO_AUTO_VALUE_ON_ZERO')" );
		} catch ( Throwable $e ) {
			unset( $e );
		}
		return null;
	}
	public static function session_end( $previous ) {
		global $wpdb;
		if ( ! is_string( $previous ) || ! preg_match( '/^[A-Za-z_,]*$/D', $previous ) || ! is_object( $wpdb ) ) return;
		try { $wpdb->query( "SET SESSION sql_mode = '" . $previous . "'" ); } catch ( Throwable $e ) { unset( $e ); }
	}

	/* ------------------------------------------------------------------------------------------- Exportación */

	/**
	 * Columnas generadas (VIRTUAL, STORED o PERSISTENT) de un CREATE TABLE de SHOW CREATE TABLE, leídas con los tokens del
	 * guardián: el exportador no escribe su valor (como mysqldump), porque MySQL lo rechaza al importar.
	 */
	public static function generated_columns( $create ) {
		if ( false === stripos( (string) $create, ' AS (' ) && false === stripos( (string) $create, 'GENERATED' ) ) return array();
		try {
			$guard = new Digitalisimo_Backups_Sql_Guard( Digitalisimo_Backups_Sql_Guard::EXPORTER, true );
			$guard->collect();
			$guard->check( (string) $create );
			$found = (array) ( $guard->found()['generated'] ?? array() );
			return $found ? array_values( array_map( 'strval', (array) reset( $found ) ) ) : array();
		} catch ( Exception $e ) {
			return array();
		}
	}
	/** Las vistas de esta base de datos entre $tables (SHOW FULL TABLES); vacío si el servidor no lo dice (SQLite). */
	public static function views_among( array $tables ) {
		global $wpdb;
		if ( self::is_sqlite() || ! $tables || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_results' ) ) return array();
		$suppress = method_exists( $wpdb, 'suppress_errors' ) ? $wpdb->suppress_errors( true ) : null;
		try {
			$rows = $wpdb->get_results( "SHOW FULL TABLES WHERE Table_type = 'VIEW'", ARRAY_N );
		} catch ( Throwable $e ) {
			$rows = array();
		} finally {
			if ( null !== $suppress ) $wpdb->suppress_errors( $suppress );
		}
		if ( ! is_array( $rows ) || ! empty( $wpdb->last_error ) ) return array();
		$views = array();
		foreach ( $rows as $row ) if ( isset( $row[0] ) ) $views[] = (string) $row[0];
		return array_values( array_intersect( array_map( 'strval', $tables ), $views ) );
	}
	/**
	 * La definición de una vista para el respaldo: la de SHOW CREATE VIEW sin DEFINER (la cuenta de la base de datos del
	 * origen no existe en otro servidor) y sin el nombre de esta base de datos delante de sus tablas. null si no se puede.
	 */
	public static function view_definition( $view ) {
		global $wpdb;
		$suppress = method_exists( $wpdb, 'suppress_errors' ) ? $wpdb->suppress_errors( true ) : null;
		try {
			$row = $wpdb->get_row( 'SHOW CREATE VIEW `' . str_replace( '`', '``', (string) $view ) . '`', ARRAY_N );
		} finally {
			if ( null !== $suppress ) $wpdb->suppress_errors( $suppress );
		}
		if ( ! is_array( $row ) || empty( $row[1] ) || ! empty( $wpdb->last_error ) ) return null;
		$sql = (string) $row[1];
		if ( ! preg_match( '/^CREATE\s/i', $sql ) || false !== strpos( $sql, "\0" ) ) return null;
		$sql = preg_replace( '/^(CREATE\s+(?:OR\s+REPLACE\s+)?(?:ALGORITHM\s*=\s*\w+\s+)?)DEFINER\s*=\s*(?:`(?:[^`]|``)*`|\'(?:[^\'\\\\]|\\\\.)*\'|[^\s@]+)(?:@(?:`(?:[^`]|``)*`|\'(?:[^\'\\\\]|\\\\.)*\'|\S+))?\s+/i', '$1', $sql, 1 );
		if ( defined( 'DB_NAME' ) && '' !== (string) DB_NAME ) $sql = str_replace( '`' . str_replace( '`', '``', (string) DB_NAME ) . '`.', '', $sql );
		return $sql;
	}

	/* ------------------------------------------------------------------------------------------- Vistas al final del cambio */

	/**
	 * Crea las vistas del respaldo (posición y longitud de su sentencia en source.sql, anotadas en la verificación) con los
	 * nombres de este sitio, sin DEFINER y con SQL SECURITY INVOKER. Una vista puede usar otra: se reintenta en pasadas
	 * mientras alguna avance. Nunca hace fallar la restauración: lo que no se puede crear se informa. $sources (nombres en
	 * el respaldo): en un archivo sin firma, las únicas tablas que una vista puede leer, las que trae de verdad esta
	 * restauración; las vistas de $views se añaden solas (lo que sólo declare el manifiesto sin firma no vale).
	 */
	public static function create_views( $sql_path, array $views, array $data, $trusted, array $renames, $sources = null ) {
		global $wpdb;
		$result = array( 'created' => array(), 'failed' => array() );
		if ( ! $views ) return $result;
		$handle = @fopen( $sql_path, 'rb' );
		if ( ! $handle ) { foreach ( $views as $view ) $result['failed'][ (string) ( $view[2] ?? '' ) ] = 'no se pudo leer la base de datos del respaldo'; return $result; }
		$pending = array();
		try {
			foreach ( $views as $view ) {
				$name = (string) ( $view[2] ?? '' );
				$length = (int) ( $view[1] ?? 0 );
				if ( $length <= 0 || $length > 4194304 || 0 !== fseek( $handle, (int) $view[0] ) ) { $result['failed'][ $name ] = 'no se encontró su definición'; continue; }
				$reader = new Digitalisimo_Backups_Sql_Reader();
				$statements = array_merge( $reader->feed( (string) fread( $handle, $length ) ), $reader->finish() );
				try {
					$guard = Digitalisimo_Backups_Sql_Guard::for_backup( $data, $trusted );
					$names = array_map( function( $v ) { return (string) ( $v[2] ?? '' ); }, $views );
					$guard->views( 'rewrite', array_merge( self::declared_views( $data ), $names ) );
					if ( is_array( $sources ) && method_exists( $guard, 'view_sources' ) ) $guard->view_sources( array_merge( array_map( 'strval', $sources ), $names ) );
					if ( $renames ) $guard->rename_tables( $renames );
					$sql = '';
					foreach ( $statements as $statement ) if ( $guard->check( $statement ) ) $sql = $guard->rewritten();
					if ( '' === $sql || null === $guard->last_view() ) throw new Exception( 'no es la definición de una vista' );
					$pending[ $name ] = $sql;
				} catch ( Exception $e ) {
					$result['failed'][ $name ] = $e->getMessage();
				}
			}
		} finally {
			fclose( $handle );
		}
		$errors = array();
		while ( $pending ) {
			$progress = false;
			foreach ( $pending as $name => $sql ) {
				if ( false !== $wpdb->query( $sql ) ) { $result['created'][] = $name; unset( $pending[ $name ] ); $progress = true; continue; }
				$errors[ $name ] = (string) $wpdb->last_error;
			}
			if ( ! $progress ) break;
		}
		foreach ( $pending as $name => $sql ) $result['failed'][ $name ] = '' !== ( $errors[ $name ] ?? '' ) ? $errors[ $name ] : 'la base de datos no la aceptó';
		return $result;
	}
	/**
	 * Si una vista de un respaldo de red es del sitio que se convierte (prefijo `<base>N_`, o el de la red sin ser de otro
	 * sitio para el principal): las del sitio viajan con él; las de otros sitios, no.
	 */
	public static function site_owns( $view, $prefix, $base ) {
		$view = (string) $view;
		$prefix = (string) $prefix;
		$base = (string) $base;
		if ( '' === $prefix || 0 !== strncmp( $view, $prefix, strlen( $prefix ) ) ) return false;
		return $prefix !== $base || ! preg_match( '/^' . preg_quote( $base, '/' ) . '[0-9]+_/', $view );
	}
	/** Nombres en este sitio de las tablas y vistas del respaldo: los de tables.json y, por el mismo prefijo, las vistas. */
	public static function view_renames( array $tables, array $views, $from_prefix, $to_prefix ) {
		$map = array();
		foreach ( $tables as $row ) if ( (string) $row['backup'] !== (string) $row['dest'] ) $map[ (string) $row['backup'] ] = (string) $row['dest'];
		$from_prefix = (string) $from_prefix;
		$to_prefix = (string) $to_prefix;
		if ( '' !== $from_prefix && $from_prefix !== $to_prefix ) {
			foreach ( $views as $view ) {
				$name = (string) ( $view[2] ?? '' );
				if ( 0 === strncmp( $name, $from_prefix, strlen( $from_prefix ) ) ) $map[ $name ] = $to_prefix . substr( $name, strlen( $from_prefix ) );
			}
		}
		return $map;
	}

	/* ------------------------------------------------------------------------------------------- Versiones del origen (brecha 8) */

	/**
	 * Avisos (nunca bloquean) sobre las versiones del origen: PHP de otra serie, lo que piden el núcleo, los plugins y el
	 * tema activos del respaldo (cabeceras `Requires PHP` dentro del ZIP), WordPress más nuevo al restaurar sólo la base y
	 * otro servidor de base de datos. $found: lo anotado por la pasada en seco (`plugins`, `themes`). $parts: null es todo.
	 */
	public static function version_warnings( array $data, $zip = null, array $found = array(), $parts = null ) {
		$warnings = array();
		$php = (string) ( $data['php_version'] ?? '' );
		$here = PHP_VERSION;
		$series = function( $version ) { return preg_match( '/^(\d+)\.(\d+)/', (string) $version, $m ) ? $m[1] . '.' . $m[2] : ''; };
		$needs = array();
		$with = function( $part ) use ( $parts ) { return null === $parts || ( class_exists( 'Digitalisimo_Backups_Restore_Parts' ) && Digitalisimo_Backups_Restore_Parts::has( $parts, $part ) ); };
		if ( $zip instanceof ZipArchive ) {
			if ( $with( 'core' ) ) {
				$version = self::zip_head( $zip, 'wordpress/wp-includes/version.php', 16384 );
				if ( preg_match( '/\$required_php_version\s*=\s*[\'"]([0-9.]+)[\'"]/', $version, $m ) && version_compare( $here, $m[1], '<' ) ) $needs[] = 'el WordPress del respaldo pide PHP ' . $m[1];
			}
			$plugins = array_keys( (array) ( $found['plugins'] ?? array() ) );
			if ( $with( 'plugins' ) || $with( 'database' ) ) {
				foreach ( array_slice( $plugins, 0, self::CAP * 2 ) as $file ) {
					if ( ! preg_match( '#^[A-Za-z0-9._ -]+(?:/[A-Za-z0-9._ -]+)?\.php$#D', (string) $file ) || false !== strpos( (string) $file, '..' ) ) continue;
					$head = self::zip_head( $zip, 'wordpress/wp-content/plugins/' . $file, 8192 );
					$requires = self::header( $head, 'Requires PHP' );
					if ( '' !== $requires && version_compare( $here, $requires, '<' ) ) $needs[] = 'el plugin ' . ( '' !== self::header( $head, 'Plugin Name' ) ? self::header( $head, 'Plugin Name' ) : $file ) . ' (activo en el respaldo) pide PHP ' . $requires;
				}
			}
			foreach ( array_slice( array_keys( (array) ( $found['themes'] ?? array() ) ), 0, 5 ) as $theme ) {
				if ( ! preg_match( '/^[A-Za-z0-9._-]+$/D', (string) $theme ) || false !== strpos( (string) $theme, '..' ) || ! ( $with( 'themes' ) || $with( 'database' ) ) ) continue;
				$head = self::zip_head( $zip, 'wordpress/wp-content/themes/' . $theme . '/style.css', 8192 );
				$requires = self::header( $head, 'Requires PHP' );
				if ( '' !== $requires && version_compare( $here, $requires, '<' ) ) $needs[] = 'el tema ' . ( '' !== self::header( $head, 'Theme Name' ) ? self::header( $head, 'Theme Name' ) : $theme ) . ' (activo en el respaldo) pide PHP ' . $requires;
			}
		}
		if ( $needs ) $warnings[] = 'Este servidor tiene PHP ' . $here . ' y ' . implode( '; ', array_slice( array_unique( $needs ), 0, 8 ) ) . ( count( $needs ) > 8 ? '…' : '' ) . ': tras restaurar, eso daría error al cargarse. Sube la versión de PHP antes de restaurar (o desactiva después lo que la pide).';
		if ( '' !== $series( $php ) && $series( $php ) !== $series( $here ) ) $warnings[] = 'El respaldo se hizo con PHP ' . $php . ' y este servidor tiene PHP ' . $here . ( version_compare( $series( $php ), $series( $here ), '>' ) ? ': es más antiguo que el del origen; sólo sigue si los plugins y el tema del respaldo funcionan con él.' : '.' );
		$wordpress = (string) ( $data['wordpress_version'] ?? '' );
		$current = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'version' ) : '';
		if ( '' !== $wordpress && '' !== $current && null !== $parts && $with( 'database' ) && ! $with( 'core' ) && version_compare( $wordpress, $current, '>' ) ) $warnings[] = 'La base de datos del respaldo es de WordPress ' . $wordpress . ' y aquí está WordPress ' . $current . ': restaurarla sin el núcleo deja una base de datos más nueva que el código. Restaura también el núcleo o actualiza WordPress antes.';
		$server = self::is_sqlite() ? array( 'kind' => 'sqlite' ) : self::server();
		$origin = (string) ( $data['database_server'] ?? '' );
		$origin_kind = '' === $origin ? '' : ( false !== stripos( $origin, 'mariadb' ) ? 'mariadb' : 'mysql' );
		$origin_version = (string) ( $data['database_version'] ?? '' );
		if ( '' !== $origin_kind && in_array( (string) ( $server['kind'] ?? '' ), array( 'mysql', 'mariadb' ), true ) && ( $origin_kind !== $server['kind'] || $series( $origin_version ) !== $series( (string) ( $server['version'] ?? '' ) ) ) ) {
			$warnings[] = 'La base de datos del respaldo es de ' . ( 'mariadb' === $origin_kind ? 'MariaDB' : 'MySQL' ) . ' ' . $origin_version . ' y este servidor tiene ' . ( 'mariadb' === $server['kind'] ? 'MariaDB' : 'MySQL' ) . ' ' . (string) ( $server['version'] ?? '' ) . ': lo que no tenga se sustituye antes de importar y se dice en el informe.';
		}
		return $warnings;
	}
	private static function zip_head( $zip, $name, $bytes ) {
		$stream = @$zip->getStream( $name );
		if ( ! $stream ) return '';
		$head = (string) fread( $stream, $bytes );
		fclose( $stream );
		return $head;
	}
	/** Una cabecera de plugin o tema, como get_file_data() de WordPress. */
	private static function header( $text, $name ) {
		if ( ! preg_match( '/^(?:[ \t]*<\?php)?[ \t\/*#@]*' . preg_quote( $name, '/' ) . ':(.*)$/mi', (string) $text, $m ) ) return '';
		$value = trim( preg_replace( '/\s*(?:\*\/|\?>).*/', '', $m[1] ) );
		return substr( $value, 0, 100 );
	}

	/**
	 * Disparadores, rutinas y eventos de esta base de datos, que el respaldo no lleva: `array( kind => nombres )`, sólo lo
	 * que hay. Necesitan permisos especiales (TRIGGER, CREATE ROUTINE y, con el registro binario, SUPER o
	 * log_bin_trust_function_creators, que no se cambia), su código es SQL procedimental que el guardián no puede
	 * comprobar en un archivo sin firma y corre con los permisos de su DEFINER; en WordPress casi no existen. Se avisa.
	 */
	public static function database_objects() {
		global $wpdb;
		if ( self::is_sqlite() || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_results' ) ) return array();
		$objects = array();
		$suppress = method_exists( $wpdb, 'suppress_errors' ) ? $wpdb->suppress_errors( true ) : null;
		try {
			foreach ( array(
				'triggers' => 'SELECT TRIGGER_NAME AS n FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()',
				'routines' => 'SELECT CONCAT(ROUTINE_TYPE, \' \', ROUTINE_NAME) AS n FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE()',
				'events' => 'SELECT EVENT_NAME AS n FROM information_schema.EVENTS WHERE EVENT_SCHEMA = DATABASE()',
			) as $kind => $query ) {
				$rows = $wpdb->get_results( $query, ARRAY_A );
				if ( ! is_array( $rows ) || ! empty( $wpdb->last_error ) ) continue;
				$names = array();
				foreach ( $rows as $row ) if ( '' !== (string) ( $row['n'] ?? '' ) ) $names[] = substr( (string) $row['n'], 0, 128 );
				if ( $names ) $objects[ $kind ] = array_slice( $names, 0, self::CAP );
			}
		} catch ( Throwable $e ) {
			unset( $e );
		} finally {
			if ( null !== $suppress ) $wpdb->suppress_errors( $suppress );
		}
		return $objects;
	}
	/** El aviso de los disparadores, rutinas y eventos que no viajan (al respaldar y en la verificación). */
	public static function objects_text( array $objects ) {
		$labels = array( 'triggers' => 'disparador(es)', 'routines' => 'procedimiento(s) o función(es)', 'events' => 'evento(s)' );
		$parts = array();
		foreach ( $labels as $kind => $label ) if ( ! empty( $objects[ $kind ] ) ) $parts[] = count( (array) $objects[ $kind ] ) . ' ' . $label . ' (' . implode( ', ', array_slice( (array) $objects[ $kind ], 0, 5 ) ) . ( count( (array) $objects[ $kind ] ) > 5 ? '…' : '' ) . ')';
		if ( ! $parts ) return '';
		return 'La base de datos del origen tiene ' . implode( ', ', $parts ) . ' que el respaldo no lleva: necesitan permisos especiales y su código no se puede comprobar al restaurar en otro sitio. Si el sitio los usa, vuelve a crearlos a mano después de restaurar.';
	}

	/* ------------------------------------------------------------------------------------------- Informe */

	/** Para `verify` (REST y WP-CLI): lo que se cambiará, lo que se avisa y lo que impide restaurar. */
	public static function preview( array $data, array $found, $zip = null, $parts = null ) {
		$server = self::is_sqlite() ? array( 'kind' => 'sqlite' ) : self::server();
		$plan = self::plan( $found, $server );
		$warnings = array_merge( self::version_warnings( $data, $zip, $found, $parts ), $plan['warnings'] );
		$objects = self::objects_text( (array) ( $data['database_objects'] ?? array() ) );
		if ( '' !== $objects ) $warnings[] = $objects;
		$text = $plan['notes'] ? 'Para la base de datos de este servidor: ' . implode( ' ', $plan['notes'] ) : '';
		return array( 'changes' => $plan['notes'], 'warnings' => $warnings, 'problems' => $plan['problems'], 'views' => $plan['views'], 'text' => $text );
	}
	/** El aviso del final de la restauración: lo sustituido, las vistas y lo que no se pudo. */
	public static function notice( array $decision, array $views ) {
		$text = '';
		if ( ! empty( $decision['notes'] ) ) {
			$notes = array_values( array_filter( (array) $decision['notes'], function( $note ) { return 0 !== strpos( (string) $note, 'Vistas (' ); } ) );
			if ( $notes ) $text .= ' Base de datos adaptada a este servidor: ' . implode( ' ', $notes );
		}
		foreach ( (array) ( $decision['warnings'] ?? array() ) as $warning ) $text .= ' ' . $warning;
		if ( ! empty( $views['created'] ) ) $text .= ' Se crearon ' . count( (array) $views['created'] ) . ' vista(s) del respaldo sin DEFINER y con SQL SECURITY INVOKER (' . implode( ', ', array_slice( (array) $views['created'], 0, 5 ) ) . ').';
		foreach ( array_slice( (array) ( $views['failed'] ?? array() ), 0, 5, true ) as $name => $why ) $text .= ' No se pudo crear la vista ' . $name . ': ' . rtrim( (string) $why, '.' ) . '; créala a mano.';
		return trim( $text );
	}
}
