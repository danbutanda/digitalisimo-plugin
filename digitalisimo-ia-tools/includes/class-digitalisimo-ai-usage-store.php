<?php
defined( 'ABSPATH' ) || exit;

/**
 * Almacén del registro de uso de IA (docs/IA_USAGE.md): dos tablas propias de cada sitio, con su prefijo.
 *
 * - `{prefijo}digitalisimo_ai_usage`: una fila por llamada al proveedor (fecha UTC, día local, sitio, función,
 *   usuario, proveedor, modelo, tokens, unidades, costo, estado HTTP, error y reintento). Nunca texto, claves ni datos
 *   del visitante. Se conserva `retention_days` (90 por omisión) y la tarea diaria borra lo más viejo.
 * - `{prefijo}digitalisimo_ai_usage_daily`: el resumen por día, función, proveedor, modelo y usuario, que se suma al
 *   escribir (una consulta con ON DUPLICATE KEY UPDATE) y no se borra: es la historia larga, y de él salen los totales,
 *   los desgloses, el presupuesto y la Ability. Los detalles sólo sirven para «las llamadas más caras» y el CSV detallado.
 *
 * Escribir son dos INSERT pequeños tras una llamada al proveedor que tarda segundos: el chatbot público no lo nota.
 * Cada sitio de una red tiene sus tablas; la red las lee una por una (`network_sites()`), nunca las mezcla.
 */
final class Digitalisimo_AI_Usage_Store {
	const DB_VERSION = '1';
	const DB_OPTION  = 'digitalisimo_ai_usage_db';
	const SUMS       = array( 'input_tokens', 'output_tokens', 'cache_read_tokens', 'cache_write_tokens', 'cache_write_1h_tokens', 'units' );

	/** Tabla de detalle (o de resumen con `$daily`) del sitio en curso o del indicado. */
	public static function table( $daily = false, $blog_id = null ) {
		global $wpdb;
		$prefix = null === $blog_id || ! method_exists( $wpdb, 'get_blog_prefix' ) ? $wpdb->prefix : $wpdb->get_blog_prefix( (int) $blog_id );
		return $prefix . 'digitalisimo_ai_usage' . ( $daily ? '_daily' : '' );
	}

	/** Crea o actualiza las tablas de este sitio con dbDelta (una vez por versión de esquema). */
	public static function install() {
		global $wpdb;
		if ( ! function_exists( 'dbDelta' ) && defined( 'ABSPATH' ) && is_readable( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		$charset = method_exists( $wpdb, 'get_charset_collate' ) ? $wpdb->get_charset_collate() : '';
		$detail  = self::table();
		$daily   = self::table( true );
		$sql     = array(
			"CREATE TABLE {$detail} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  created datetime NOT NULL,
  day date NOT NULL,
  blog_id bigint(20) unsigned NOT NULL DEFAULT 0,
  feature varchar(32) NOT NULL DEFAULT '',
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  provider varchar(20) NOT NULL DEFAULT '',
  model varchar(80) NOT NULL DEFAULT '',
  input_tokens int(10) unsigned NOT NULL DEFAULT 0,
  output_tokens int(10) unsigned NOT NULL DEFAULT 0,
  cache_read_tokens int(10) unsigned NOT NULL DEFAULT 0,
  cache_write_tokens int(10) unsigned NOT NULL DEFAULT 0,
  cache_write_1h_tokens int(10) unsigned NOT NULL DEFAULT 0,
  units int(10) unsigned NOT NULL DEFAULT 0,
  cost_usd decimal(16,8) DEFAULT NULL,
  status smallint(5) unsigned NOT NULL DEFAULT 0,
  is_error tinyint(1) unsigned NOT NULL DEFAULT 0,
  is_retry tinyint(1) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  KEY day (day),
  KEY created (created)
) {$charset};",
			"CREATE TABLE {$daily} (
  day date NOT NULL,
  feature varchar(32) NOT NULL DEFAULT '',
  provider varchar(20) NOT NULL DEFAULT '',
  model varchar(80) NOT NULL DEFAULT '',
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  calls int(10) unsigned NOT NULL DEFAULT 0,
  errors int(10) unsigned NOT NULL DEFAULT 0,
  retries int(10) unsigned NOT NULL DEFAULT 0,
  input_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
  output_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
  cache_read_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
  cache_write_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
  cache_write_1h_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
  units bigint(20) unsigned NOT NULL DEFAULT 0,
  cost_usd decimal(18,8) NOT NULL DEFAULT 0,
  unpriced int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (day,feature,provider,model,user_id),
  KEY day (day)
) {$charset};",
		);
		if ( function_exists( 'dbDelta' ) ) {
			dbDelta( $sql );
		} else {
			foreach ( $sql as $query ) {
				$wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- esquema fijo.
			}
		}
		update_option( self::DB_OPTION, self::DB_VERSION, true );
	}

	/** Las tablas existen en este sitio (la opción va con autoload: no cuesta una consulta). */
	public static function ensure() {
		if ( self::DB_VERSION !== (string) get_option( self::DB_OPTION, '' ) ) {
			self::install();
		}
	}

	/** Guarda una llamada y la suma al resumen del día. `$row` viene de Digitalisimo_AI_Usage::record(). */
	public static function insert( array $row ) {
		global $wpdb;
		self::ensure();
		$suppress = $wpdb->suppress_errors( true );
		$detail   = array(
			'created'               => (string) $row['created'],
			'day'                   => (string) $row['day'],
			'blog_id'               => (int) $row['blog_id'],
			'feature'               => (string) $row['feature'],
			'user_id'               => (int) $row['user_id'],
			'provider'              => (string) $row['provider'],
			'model'                 => (string) $row['model'],
			'input_tokens'          => (int) $row['input_tokens'],
			'output_tokens'         => (int) $row['output_tokens'],
			'cache_read_tokens'     => (int) $row['cache_read_tokens'],
			'cache_write_tokens'    => (int) $row['cache_write_tokens'],
			'cache_write_1h_tokens' => (int) $row['cache_write_1h_tokens'],
			'units'                 => (int) $row['units'],
			'cost_usd'              => null === $row['cost_usd'] ? null : self::money( $row['cost_usd'] ),
			'status'                => (int) $row['status'],
			'is_error'              => $row['is_error'] ? 1 : 0,
			'is_retry'              => $row['is_retry'] ? 1 : 0,
		);
		$formats = array( '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', null === $detail['cost_usd'] ? null : '%s', '%d', '%d', '%d' );
		if ( null === $detail['cost_usd'] ) {
			unset( $detail['cost_usd'], $formats[13] );
			$formats = array_values( $formats );
		}
		$ok    = false !== $wpdb->insert( self::table(), $detail, $formats );
		$daily = self::table( true );
		$sql   = "INSERT INTO {$daily} (day, feature, provider, model, user_id, calls, errors, retries, input_tokens, output_tokens, cache_read_tokens, cache_write_tokens, cache_write_1h_tokens, units, cost_usd, unpriced) VALUES (%s, %s, %s, %s, %d, 1, %d, %d, %d, %d, %d, %d, %d, %d, %s, %d) ON DUPLICATE KEY UPDATE calls = calls + 1, errors = errors + VALUES(errors), retries = retries + VALUES(retries), input_tokens = input_tokens + VALUES(input_tokens), output_tokens = output_tokens + VALUES(output_tokens), cache_read_tokens = cache_read_tokens + VALUES(cache_read_tokens), cache_write_tokens = cache_write_tokens + VALUES(cache_write_tokens), cache_write_1h_tokens = cache_write_1h_tokens + VALUES(cache_write_1h_tokens), units = units + VALUES(units), cost_usd = cost_usd + VALUES(cost_usd), unpriced = unpriced + VALUES(unpriced)";
		$ok    = false !== $wpdb->query( $wpdb->prepare( $sql, $detail['day'], $detail['feature'], $detail['provider'], $detail['model'], $detail['user_id'], $detail['is_error'], $detail['is_retry'], $detail['input_tokens'], $detail['output_tokens'], $detail['cache_read_tokens'], $detail['cache_write_tokens'], $detail['cache_write_1h_tokens'], $detail['units'], self::money( $row['cost_usd'] ?? 0 ), null === $row['cost_usd'] ? 1 : 0 ) ) && $ok; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla propia.
		$wpdb->suppress_errors( $suppress );
		return $ok;
	}

	/**
	 * Un costo como texto con 8 decimales y punto: `%f` de wpdb::prepare() deja 6, y una llamada barata (un embedding)
	 * cuesta menos de un millonésimo de dólar. MySQL lo guarda exacto en DECIMAL.
	 */
	public static function money( $usd ) {
		return sprintf( '%.8F', max( 0, (float) $usd ) );
	}

	/** Columnas sumadas de un SELECT sobre el resumen. */
	private static function sum_columns() {
		$cols = 'SUM(calls) AS calls, SUM(errors) AS errors, SUM(retries) AS retries, SUM(cost_usd) AS cost_usd, SUM(unpriced) AS unpriced';
		foreach ( self::SUMS as $sum ) {
			$cols .= ', SUM(' . $sum . ') AS ' . $sum;
		}
		return $cols;
	}

	/** Una fila de totales con números (ceros si no hay nada). */
	public static function shape( $row ) {
		$row = is_object( $row ) ? get_object_vars( $row ) : (array) $row;
		$out = array();
		foreach ( array_merge( array( 'calls', 'errors', 'retries', 'unpriced' ), self::SUMS ) as $key ) {
			$out[ $key ] = (int) ( $row[ $key ] ?? 0 );
		}
		$out['cost_usd'] = round( (float) ( $row['cost_usd'] ?? 0 ), 8 );
		$out['tokens']   = $out['input_tokens'] + $out['output_tokens'] + $out['cache_read_tokens'] + $out['cache_write_tokens'];
		return $out;
	}

	/** Totales de un rango de días locales (inclusive). */
	public static function totals( $from, $to ) {
		global $wpdb;
		self::ensure();
		$daily = self::table( true );
		$row   = $wpdb->get_row( $wpdb->prepare( 'SELECT ' . self::sum_columns() . " FROM {$daily} WHERE day >= %s AND day <= %s", $from, $to ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla propia.
		return self::shape( $row );
	}

	/** Desglose por `feature`, `model` (con su proveedor), `user_id` o `day`, de mayor a menor costo (por día, en orden). */
	public static function breakdown( $by, $from, $to, $limit = 100 ) {
		global $wpdb;
		self::ensure();
		$by    = in_array( $by, array( 'feature', 'model', 'user_id', 'day' ), true ) ? $by : 'feature';
		$group = 'model' === $by ? 'provider, model' : $by;
		$order = 'day' === $by ? 'day ASC' : 'cost_usd DESC, calls DESC';
		$daily = self::table( true );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT {$group}, " . self::sum_columns() . " FROM {$daily} WHERE day >= %s AND day <= %s GROUP BY {$group} ORDER BY {$order} LIMIT %d", $from, $to, max( 1, (int) $limit ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- columnas de una lista cerrada.
		$out   = array();
		foreach ( (array) $rows as $row ) {
			$shaped = self::shape( $row );
			foreach ( explode( ', ', $group ) as $key ) {
				$shaped[ $key ] = 'user_id' === $key ? (int) $row[ $key ] : (string) $row[ $key ];
			}
			$out[] = $shaped;
		}
		return $out;
	}

	/** Las llamadas más caras del rango (del detalle, que se conserva `retention_days`). */
	public static function top( $from, $to, $limit = 20 ) {
		return self::details( $from, $to, $limit, 0, 'cost_usd DESC, id DESC' );
	}

	/** Filas de detalle del rango (para el CSV y las más caras). */
	public static function details( $from, $to, $limit = 1000, $offset = 0, $order = 'id ASC' ) {
		global $wpdb;
		self::ensure();
		$order  = in_array( $order, array( 'id ASC', 'cost_usd DESC, id DESC' ), true ) ? $order : 'id ASC';
		$detail = self::table();
		$where  = 'cost_usd DESC, id DESC' === $order ? ' AND cost_usd IS NOT NULL' : '';
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, created, day, blog_id, feature, user_id, provider, model, input_tokens, output_tokens, cache_read_tokens, cache_write_tokens, cache_write_1h_tokens, units, cost_usd, status, is_error, is_retry FROM {$detail} WHERE day >= %s AND day <= %s{$where} ORDER BY {$order} LIMIT %d OFFSET %d", $from, $to, max( 1, (int) $limit ), max( 0, (int) $offset ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla y orden de una lista cerrada.
	}

	/** Modelos con llamadas sin precio en el rango: [ 'proveedor|modelo' => llamadas ]. */
	public static function unpriced_models( $from, $to ) {
		global $wpdb;
		self::ensure();
		$daily = self::table( true );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT provider, model, SUM(unpriced) AS n FROM {$daily} WHERE day >= %s AND day <= %s AND unpriced > 0 GROUP BY provider, model", $from, $to ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla propia.
		$out   = array();
		foreach ( (array) $rows as $row ) {
			$out[ $row['provider'] . '|' . $row['model'] ] = (int) $row['n'];
		}
		return $out;
	}

	/** Gasto en USD desde un día local (el presupuesto lo pide desde el día 1 del mes). */
	public static function spend_since( $from ) {
		global $wpdb;
		self::ensure();
		$daily = self::table( true );
		return round( (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(cost_usd) FROM {$daily} WHERE day >= %s", $from ) ), 8 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla propia.
	}

	/** Borra los detalles anteriores a `$before` (día local) por tandas; el resumen diario se queda. */
	public static function purge( $before, $batch = 5000 ) {
		global $wpdb;
		if ( self::DB_VERSION !== (string) get_option( self::DB_OPTION, '' ) ) {
			return 0;
		}
		$detail = self::table();
		$total  = 0;
		for ( $i = 0; $i < 20; $i++ ) {
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$detail} WHERE day < %s ORDER BY id ASC LIMIT %d", $before, (int) $batch ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla propia.
			if ( ! $ids ) {
				break;
			}
			$ids    = array_map( 'intval', $ids );
			$total += (int) $wpdb->query( "DELETE FROM {$detail} WHERE id IN (" . implode( ',', $ids ) . ')' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- enteros.
			if ( count( $ids ) < $batch ) {
				break;
			}
		}
		return $total;
	}

	/**
	 * Tras agregar o corregir el precio de un modelo: pone costo a sus llamadas «sin precio». Las del detalle (dentro de
	 * la retención) se calculan una por una y se suman a su día; un día cuyas llamadas eran todas sin precio y ya no
	 * tiene detalle se calcula con sus tokens. Las llamadas que ya tenían costo no cambian: la historia guarda el precio
	 * con el que se registraron.
	 */
	public static function reprice_unpriced() {
		global $wpdb;
		self::ensure();
		$detail  = self::table();
		$daily   = self::table( true );
		$fixed   = 0;
		$rows    = (array) $wpdb->get_results( "SELECT id, day, feature, provider, model, user_id, input_tokens, output_tokens, cache_read_tokens, cache_write_tokens, cache_write_1h_tokens, units FROM {$detail} WHERE cost_usd IS NULL LIMIT 5000", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla propia.
		$covered = array();
		foreach ( $rows as $row ) {
			$cost = Digitalisimo_AI_Usage_Prices::cost( Digitalisimo_AI_Usage_Prices::find( $row['model'] ), self::usage_of( $row ) );
			$key  = $row['day'] . '|' . $row['feature'] . '|' . $row['provider'] . '|' . $row['model'] . '|' . $row['user_id'];
			$covered[ $key ] = true;
			if ( null === $cost ) {
				continue;
			}
			$wpdb->query( $wpdb->prepare( "UPDATE {$detail} SET cost_usd = %s WHERE id = %d AND cost_usd IS NULL", self::money( $cost ), (int) $row['id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla propia.
			$wpdb->query( $wpdb->prepare( "UPDATE {$daily} SET cost_usd = cost_usd + %s, unpriced = CASE WHEN unpriced > 0 THEN unpriced - 1 ELSE 0 END WHERE day = %s AND feature = %s AND provider = %s AND model = %s AND user_id = %d", self::money( $cost ), $row['day'], $row['feature'], $row['provider'], $row['model'], (int) $row['user_id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla propia.
			$fixed++;
		}
		$days = (array) $wpdb->get_results( "SELECT day, feature, provider, model, user_id, calls, unpriced, input_tokens, output_tokens, cache_read_tokens, cache_write_tokens, cache_write_1h_tokens, units FROM {$daily} WHERE unpriced > 0 AND unpriced = calls", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla propia.
		foreach ( $days as $row ) {
			if ( isset( $covered[ $row['day'] . '|' . $row['feature'] . '|' . $row['provider'] . '|' . $row['model'] . '|' . $row['user_id'] ] ) ) {
				continue;
			}
			$cost = Digitalisimo_AI_Usage_Prices::cost( Digitalisimo_AI_Usage_Prices::find( $row['model'] ), self::usage_of( $row ) );
			if ( null === $cost ) {
				continue;
			}
			$wpdb->query( $wpdb->prepare( "UPDATE {$daily} SET cost_usd = %s, unpriced = 0 WHERE day = %s AND feature = %s AND provider = %s AND model = %s AND user_id = %d", self::money( $cost ), $row['day'], $row['feature'], $row['provider'], $row['model'], (int) $row['user_id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla propia.
			$fixed += (int) $row['calls'];
		}
		return $fixed;
	}

	private static function usage_of( array $row ) {
		return array( 'input' => (int) $row['input_tokens'], 'output' => (int) $row['output_tokens'], 'cache_read' => (int) $row['cache_read_tokens'], 'cache_write' => (int) $row['cache_write_tokens'], 'cache_write_1h' => (int) $row['cache_write_1h_tokens'], 'units' => (int) $row['units'] );
	}

	/**
	 * Totales de cada sitio de la red, leídos de la tabla de resumen de cada uno (sólo los que la tienen):
	 * [ blog_id => [ 'today' => totales, 'week' => …, 'month' => …, 'range' => … ] ]. `$days` lleva los días locales.
	 */
	public static function network_sites( array $blog_ids, array $days ) {
		global $wpdb;
		$like     = $wpdb->esc_like( $wpdb->base_prefix ) . '%' . $wpdb->esc_like( 'digitalisimo_ai_usage_daily' );
		$existing = array_flip( (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) ) );
		$out      = array();
		foreach ( array_chunk( array_map( 'intval', $blog_ids ), 40 ) as $chunk ) {
			$parts = array();
			foreach ( $chunk as $blog_id ) {
				$table = self::table( true, $blog_id );
				if ( ! isset( $existing[ $table ] ) ) {
					continue;
				}
				$sel = array();
				foreach ( array( 'today' => array( $days['today'], $days['today'] ), 'week' => array( $days['week'], $days['today'] ), 'month' => array( $days['month'], $days['today'] ), 'range' => array( $days['from'], $days['to'] ) ) as $name => $span ) {
					$cond  = $wpdb->prepare( 'day >= %s AND day <= %s', $span[0], $span[1] );
					$sel[] = "SUM(CASE WHEN {$cond} THEN cost_usd ELSE 0 END) AS {$name}_cost, SUM(CASE WHEN {$cond} THEN calls ELSE 0 END) AS {$name}_calls, SUM(CASE WHEN {$cond} THEN unpriced ELSE 0 END) AS {$name}_unpriced, SUM(CASE WHEN {$cond} THEN input_tokens + output_tokens + cache_read_tokens + cache_write_tokens ELSE 0 END) AS {$name}_tokens";
				}
				$parts[] = 'SELECT ' . (int) $blog_id . ' AS blog_id, ' . implode( ', ', $sel ) . " FROM {$table}";
			}
			if ( ! $parts ) {
				continue;
			}
			foreach ( (array) $wpdb->get_results( implode( ' UNION ALL ', $parts ), ARRAY_A ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- condiciones preparadas arriba, tablas propias.
				$site = array();
				foreach ( array( 'today', 'week', 'month', 'range' ) as $name ) {
					$site[ $name ] = array( 'cost_usd' => round( (float) $row[ $name . '_cost' ], 8 ), 'calls' => (int) $row[ $name . '_calls' ], 'unpriced' => (int) $row[ $name . '_unpriced' ], 'tokens' => (int) $row[ $name . '_tokens' ] );
				}
				$out[ (int) $row['blog_id'] ] = $site;
			}
		}
		return $out;
	}

	/** Desglose de toda la red por función o modelo en un rango (suma de las tablas de resumen de cada sitio). */
	public static function network_breakdown( array $blog_ids, $by, $from, $to ) {
		global $wpdb;
		$by       = 'model' === $by ? 'provider, model' : 'feature';
		$like     = $wpdb->esc_like( $wpdb->base_prefix ) . '%' . $wpdb->esc_like( 'digitalisimo_ai_usage_daily' );
		$existing = array_flip( (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) ) );
		$acc      = array();
		foreach ( array_chunk( array_map( 'intval', $blog_ids ), 40 ) as $chunk ) {
			$parts = array();
			foreach ( $chunk as $blog_id ) {
				$table = self::table( true, $blog_id );
				if ( isset( $existing[ $table ] ) ) {
					$parts[] = $wpdb->prepare( "SELECT {$by}, SUM(calls) AS calls, SUM(cost_usd) AS cost_usd, SUM(unpriced) AS unpriced, SUM(input_tokens + output_tokens + cache_read_tokens + cache_write_tokens) AS tokens FROM {$table} WHERE day >= %s AND day <= %s GROUP BY {$by}", $from, $to ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tablas propias y columnas de una lista cerrada.
				}
			}
			if ( ! $parts ) {
				continue;
			}
			foreach ( (array) $wpdb->get_results( implode( ' UNION ALL ', $parts ), ARRAY_A ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- partes preparadas arriba.
				$key = 'feature' === $by ? $row['feature'] : $row['provider'] . '|' . $row['model'];
				if ( ! isset( $acc[ $key ] ) ) {
					$acc[ $key ] = array( 'key' => $key, 'calls' => 0, 'cost_usd' => 0.0, 'unpriced' => 0, 'tokens' => 0 );
				}
				$acc[ $key ]['calls']    += (int) $row['calls'];
				$acc[ $key ]['cost_usd'] += (float) $row['cost_usd'];
				$acc[ $key ]['unpriced'] += (int) $row['unpriced'];
				$acc[ $key ]['tokens']   += (int) $row['tokens'];
			}
		}
		usort(
			$acc,
			function ( $a, $b ) {
				return $b['cost_usd'] <=> $a['cost_usd'];
			}
		);
		return $acc;
	}

	/** Borra las tablas y la marca de esquema de este sitio (desinstalar, sólo si el administrador lo pidió). */
	public static function drop() {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- tabla propia.
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table( true ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- tabla propia.
		delete_option( self::DB_OPTION );
	}
}
