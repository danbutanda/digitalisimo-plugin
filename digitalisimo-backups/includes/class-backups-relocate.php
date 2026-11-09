<?php
defined( 'ABSPATH' ) || exit;

/**
 * Reubicación de una base de datos de WordPress: otro dominio, otra ruta, otra carpeta del servidor y otro prefijo de
 * tablas, en un sitio individual y en una red por subdominios o por subdirectorios. Ver «Restaurar en otro dominio» en
 * docs/BACKUPS.md; el contrato completo para reutilizarla está en el PHPDoc de cada método público.
 *
 * Tres piezas:
 *
 * 1. **Reescritor de valores** (`add_url()`, `add_path()`, `rewrite()`): cambia las direcciones del origen por las del
 *    destino dentro de PHP serializado (también anidado y dentro de otro serializado, con las longitudes recalculadas en
 *    bytes), JSON con barras escapadas (`https:\/\/…`, también doblemente escapado), URL codificadas
 *    (`https%3A%2F%2F…`), `http` y `https`, protocolo relativo (`//dominio`), con y sin `www`, y rutas absolutas del
 *    sistema de archivos. **Nunca llama a unserialize()**: recorre el formato y sólo cambia el contenido de las cadenas,
 *    así que ningún objeto de un respaldo se instancia (ni `__wakeup` ni `__destruct`). Un dominio sólo coincide
 *    completo: `origen.com` no toca `origen.com.mx`, `miorigen.com`, `origen.com:8443` ni un correo `@origen.com`.
 * 2. **Plan** (`plan()`): con el origen y el destino (direcciones, rutas, prefijo y, en una red, cada sitio) calcula las
 *    tablas que cambian de prefijo, las claves que lo llevan (`{prefijo}user_roles`, `{prefijo}capabilities`…), el
 *    dominio y la ruta de cada sitio de la red y las comprobaciones previas (`problems()`), que dicen qué cambiar.
 * 3. **Aplicación**: en la importación, sentencia por sentencia (`attach()` sobre Digitalisimo_Backups_Sql_Guard:
 *    memoria constante, sin segunda pasada y sin exigir clave primaria), o sobre una base de datos ya importada
 *    (`rewrite_tables()`, por lotes de clave primaria). Después, `finish()` ajusta la red y las direcciones, vacía las
 *    cachés que guardan URL y cuenta lo que quedó del origen (`leftovers()`).
 *
 * No se reescriben: la columna `guid` de las entradas (WordPress pide no cambiarla), los correos, el texto sin esquema ni
 * `//` («visita origen.com»), las claves de arrays y los nombres de propiedades serializados (como WP-CLI), el contenido
 * de objetos `C:` (formato propio de su clase) y un serializado dañado (se deja intacto y se informa).
 */
final class Digitalisimo_Backups_Relocate {
	const VERSION = 1;
	/** Cadenas serializadas dentro de otras cadenas (s:…:"a:…") que se recorren. */
	const MAX_DEPTH = 16;
	/** Anidamiento máximo de arrays u objetos dentro de un valor serializado. */
	const MAX_NESTING = 512;
	/** Tamaño máximo de un valor que se reescribe; uno mayor se deja intacto y se informa (`too_large`). */
	const MAX_VALUE = 67108864;
	/** Filas por lote al leer una tabla ya importada y bytes por grupo de filas que se cargan a la vez. */
	const BATCH_ROWS = 500;
	const BATCH_BYTES = 4194304;
	/** Filas candidatas que se revisan por tabla en la comprobación final. */
	const CHECK_ROWS = 2000;
	/** Estilos en los que aparece una URL o una ruta: barras normales, JSON, JSON dentro de JSON y codificación de URL. */
	const STYLES = array(
		'json2' => array( 'slash' => '\\\\\\/', 'colon' => ':' ),
		'url' => array( 'slash' => '%2F', 'colon' => '%3A' ),
		'json' => array( 'slash' => '\\/', 'colon' => ':' ),
		'plain' => array( 'slash' => '/', 'colon' => ':' ),
	);
	/** Columnas que nunca se reescriben, por tabla base (sin prefijo ni número de sitio). */
	const KEEP_COLUMNS = array( 'posts' => array( 'guid' ) );

	/** host[:puerto] en minúsculas => lista de array( path, scheme, host, to_path ), de la ruta más larga a la más corta. */
	private $hosts = array();
	/** Pares de URL añadidos, para el informe. */
	private $url_pairs = array();
	/** Pares de rutas añadidos (origen => destino). */
	private $path_pairs = array();
	private $path_regex = null;
	private $path_map = array();
	/** Agujas para descartar rápido un valor o el texto SQL de un literal que no menciona el origen. */
	private $needles = array();
	private $sql_needles = array();
	/** Prefijos de tabla: origen => destino, del más largo al más corto. */
	private $prefixes = array();
	private $base_prefix = '';
	private $flags = array();
	private $stats = array();
	private $tables = array();
	private $max_value;
	// Plan.
	private $origin = array();
	private $destination = array();
	private $mode = 'current';
	private $problems = array();
	private $sites = array();
	private $table_map = array();
	private $report = array();

	/**
	 * Reescritor vacío. `$args`: `max_value` (bytes; por defecto MAX_VALUE). Se configura con add_url(), add_path() y
	 * set_prefixes(); plan() lo hace a partir del origen y el destino.
	 */
	public function __construct( array $args = array() ) {
		$this->max_value = max( 1, (int) ( $args['max_value'] ?? self::MAX_VALUE ) );
		$this->reset_stats();
	}

	/* ---------------------------------------------------------------- Reescritor de valores ------------------------- */

	/**
	 * Cambia las URL que empiezan por `$from` (http o https, sin barra final) por `$to`: con y sin esquema (`//host`),
	 * con y sin `www`, en texto, JSON escapado (una y dos veces) y codificación de URL. El host compara sin mayúsculas y
	 * sólo completo (con su puerto, si lo lleva); la ruta, con mayúsculas y hasta un límite de segmento (`/blog` no toca
	 * `/blogs`). Una URL del origen con otro esquema toma el del destino. Devuelve false si alguna no es una URL válida.
	 */
	public function add_url( $from, $to ) {
		$a = self::parse_url( $from );
		$b = self::parse_url( $to );
		if ( ! $a || ! $b ) return false;
		if ( $a['host'] === $b['host'] && $a['path'] === $b['path'] && $a['scheme'] === $b['scheme'] ) return true;
		$this->url_pairs[ $a['scheme'] . '://' . $a['host'] . $a['path'] ] = $b['scheme'] . '://' . $b['host'] . $b['path'];
		$variants = array( $a['host'] );
		$name = preg_replace( '/:[0-9]+$/', '', $a['host'] );
		// Con y sin «www» (no para una IP ni un nombre sin punto, como localhost).
		if ( false !== strpos( $name, '.' ) && ! preg_match( '/^[0-9.]+$/D', $name ) ) {
			$bare = preg_replace( '/^www\./', '', $a['host'] );
			$variants[] = $bare === $a['host'] ? 'www.' . $a['host'] : $bare;
		}
		foreach ( $variants as $host ) {
			if ( ! preg_match( '/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?(?::[0-9]{1,5})?$/D', $host ) ) continue;
			$entry = array( 'path' => $a['path'], 'scheme' => $b['scheme'], 'host' => $b['host'], 'to_path' => $b['path'] );
			$list = $this->hosts[ $host ] ?? array();
			$implied = false;
			foreach ( $list as $i => $known ) {
				if ( $known['path'] === $entry['path'] ) { unset( $list[ $i ] ); continue; } // El último manda.
				// Un par que otro más corto ya produce (el sitio /uno/ de una red cuyo raíz cambia igual) no se guarda: así
				// una red grande por subdirectorios queda en un solo par.
				if ( self::implies( $known, $entry ) ) $implied = true;
				elseif ( self::implies( $entry, $known ) ) unset( $list[ $i ] );
			}
			if ( ! $implied ) $list[] = $entry;
			usort( $list, function( $x, $y ) { return strlen( $y['path'] ) - strlen( $x['path'] ); } );
			$this->hosts[ $host ] = array_values( $list );
		}
		$this->compile();
		return true;
	}
	/** $short ya produce el mismo resultado que $long: su ruta es un prefijo de segmento y el resto se conserva igual. */
	private static function implies( array $short, array $long ) {
		if ( $short['scheme'] !== $long['scheme'] || $short['host'] !== $long['host'] || strlen( $short['path'] ) >= strlen( $long['path'] ) ) return false;
		if ( 0 !== strpos( $long['path'] . '/', $short['path'] . '/' ) ) return false;
		return $short['to_path'] . substr( $long['path'], strlen( $short['path'] ) ) === $long['to_path'];
	}

	/**
	 * Cambia una ruta absoluta del servidor (`/var/www/origen`) por otra, en texto y JSON escapado, sólo hasta un límite
	 * de segmento (`/var/www/origen2` no se toca). Se ignora una ruta vacía, la raíz o un par igual. Devuelve false si no
	 * es una ruta absoluta.
	 */
	public function add_path( $from, $to ) {
		$from = self::clean_path( $from );
		$to = self::clean_path( $to );
		if ( '' === $from || '' === $to ) return false;
		if ( $from !== $to ) {
			$this->path_pairs[ $from ] = $to;
			$this->compile();
		}
		return true;
	}

	/**
	 * Prefijos de tabla que cambian, origen => destino (por ejemplo `array( 'wp_' => 'nuevo_' )`, o para sacar el sitio 2
	 * de una red `array( 'wp_2_' => 'nuevo_', 'wp_' => 'nuevo_' )`). `$base` es el prefijo base del destino (el de
	 * `$wpdb->base_prefix`), que sirve para reconocer el tipo de cada tabla.
	 */
	public function set_prefixes( array $map, $base ) {
		$this->prefixes = array();
		foreach ( $map as $from => $to ) {
			$from = (string) $from;
			$to = (string) $to;
			if ( ! self::valid_prefix( $from ) || ! self::valid_prefix( $to ) ) throw new InvalidArgumentException( 'Prefijo de tablas no válido.' );
			if ( $from !== $to ) $this->prefixes[ $from ] = $to;
		}
		uksort( $this->prefixes, function( $x, $y ) { return strlen( $y ) - strlen( $x ); } );
		$this->base_prefix = (string) $base;
		return $this;
	}

	/** true si hay algo que reescribir en los valores (URL o rutas). */
	public function rewrites_values() { return (bool) ( $this->hosts || $this->path_pairs ); }

	/**
	 * Devuelve `$value` con las direcciones y rutas del origen cambiadas por las del destino. Un valor que no es cadena,
	 * que no menciona el origen o que supera `max_value` vuelve igual. Un serializado se recorre sin unserialize() y se
	 * vuelve a escribir con las longitudes en bytes; si está dañado vuelve intacto. `flags()` dice qué pasó con la última
	 * llamada (`malformed`, `too_large`, `opaque`, `deep`) y `stats()` acumula los totales.
	 */
	public function rewrite( $value ) {
		$this->flags = array();
		if ( ! is_string( $value ) || '' === $value || ! $this->rewrites_values() ) return $value;
		if ( ! $this->may_contain( $value ) ) return $value;
		if ( strlen( $value ) > $this->max_value ) {
			$this->flags['too_large'] = true;
			$this->stats['too_large']++;
			return $value;
		}
		$new = $this->rewrite_text( $value, 0 );
		if ( ! empty( $this->flags['malformed'] ) ) $this->stats['malformed']++;
		if ( ! empty( $this->flags['opaque'] ) ) $this->stats['opaque']++;
		if ( $new !== $value ) $this->stats['values']++;
		return $new;
	}
	/** Qué pasó con la última llamada a rewrite(). */
	public function flags() { return $this->flags; }
	/** Totales acumulados: values, urls, paths, serialized, malformed, too_large, opaque, keys. */
	public function stats() { return $this->stats; }
	public function reset_stats() { $this->stats = array( 'values' => 0, 'urls' => 0, 'paths' => 0, 'serialized' => 0, 'malformed' => 0, 'too_large' => 0, 'opaque' => 0, 'keys' => 0 ); }
	/** Pares públicos de URL y rutas que aplica este reescritor. */
	public function pairs() { return array( 'urls' => $this->url_pairs, 'paths' => $this->path_pairs ); }

	/**
	 * Clave con prefijo de tabla cambiada al prefijo del destino. `$base` es la tabla base donde vive: en `usermeta`
	 * cambian todas las que empiezan por el prefijo del origen (`wp_capabilities`, `wp_2_user_level`, `wp_user-settings`…,
	 * lo que escribe update_user_option()); en `options`, sólo `{prefijo}[N_]user_roles` (hay opciones del núcleo que
	 * empiezan por `wp_` sin ser del prefijo, como `wp_page_for_privacy_policy`).
	 */
	public function rename_key( $key, $base ) {
		if ( ! is_string( $key ) ) return $key;
		foreach ( $this->prefixes as $from => $to ) {
			if ( 0 !== strpos( $key, $from ) ) continue;
			$rest = (string) substr( $key, strlen( $from ) );
			if ( 'options' === $base && ! preg_match( '/^(?:[0-9]+_)?user_roles$/D', $rest ) ) return $key;
			if ( 'usermeta' !== $base && 'options' !== $base ) return $key;
			return $to . $rest;
		}
		return $key;
	}

	/**
	 * Nombres nuevos de las tablas: las que empiezan por un prefijo del origen toman el del destino; las demás conservan
	 * su nombre. Lanza una excepción si dos tablas acabarían con el mismo nombre (el respaldo trae ya una tabla con el
	 * nombre de destino de otra): nada se ha tocado todavía.
	 */
	public function table_map( array $tables ) {
		$map = array();
		foreach ( $tables as $table ) {
			$table = (string) $table;
			$new = $table;
			foreach ( $this->prefixes as $from => $to ) {
				if ( 0 === strpos( $table, $from ) ) { $new = $to . substr( $table, strlen( $from ) ); break; }
			}
			$map[ $table ] = $new;
		}
		$seen = array();
		foreach ( $map as $table => $new ) {
			if ( isset( $seen[ $new ] ) ) throw new Exception( 'Al cambiar el prefijo de tablas, «' . $table . '» y «' . $seen[ $new ] . '» del respaldo acabarían como «' . $new . '». Restaura el respaldo en una instalación con el prefijo del respaldo o retira antes la tabla sobrante en el origen.' );
			$seen[ $new ] = $table;
		}
		return $map;
	}

	/** Tabla base y número de sitio de una tabla del destino: array( 'wp_2_options' ) → array( 'options', 2 ). */
	public function kind( $table ) {
		$prefix = $this->base_prefix;
		if ( '' === $prefix || 0 !== strpos( $table, $prefix ) ) return array( '', 0 );
		$rest = (string) substr( $table, strlen( $prefix ) );
		if ( preg_match( '/^([0-9]+)_([a-z_]+)$/D', $rest, $m ) && in_array( $m[2], array( 'options', 'posts', 'postmeta', 'comments', 'commentmeta', 'terms', 'termmeta', 'term_taxonomy', 'term_relationships', 'links' ), true ) ) return array( $m[2], (int) $m[1] );
		return array( $rest, 1 );
	}

	/* ---------------------------------------------------------------- Aplicación en la importación ------------------ */

	/**
	 * Conecta el plan a un guardián SQL que va a importar el respaldo: renombra las tablas de `$tables` (lista del
	 * manifiesto) al prefijo del destino y reescribe, antes de ejecutarla, cada fila de cada INSERT (direcciones, rutas y
	 * claves con prefijo). Usa los literales tal como los leyó el guardián, así que lo que se ejecuta es exactamente lo
	 * que comprobó, con otros valores. Devuelve el guardián.
	 */
	public function attach( Digitalisimo_Backups_Sql_Guard $guard, array $tables ) {
		$this->table_map = $this->table_map( $tables );
		$renames = array_filter( $this->table_map, function( $new, $old ) { return $new !== $old; }, ARRAY_FILTER_USE_BOTH );
		if ( $renames ) $guard->rename_tables( $renames );
		if ( $renames || $this->rewrites_values() ) $guard->on_insert( array( $this, 'transform_insert' ) );
		return $guard;
	}

	/**
	 * @internal Gancho del guardián: recibe un INSERT ya comprobado (tabla, columnas y la posición de cada literal) y
	 * devuelve los tramos que cambian, array( inicio, fin, texto SQL nuevo ).
	 */
	public function transform_insert( array $insert, $sql ) {
		if ( ! is_array( $insert['columns'] ?? null ) ) return array();
		// Una tabla ajena a esta instalación (sin el prefijo del origen, de otra aplicación de la misma base de datos) se
		// restaura como siempre, sin cambios.
		$own = (string) ( $this->origin['prefix'] ?? '' );
		if ( '' !== $own && 0 !== strpos( (string) $insert['table'], $own ) ) { $this->count( (string) $insert['table'], 'foreign' ); return array(); }
		$table = $this->table_map[ $insert['table'] ] ?? $insert['table'];
		list( $base ) = $this->kind( $table );
		$keep = self::KEEP_COLUMNS[ $base ] ?? array();
		$key_column = 'usermeta' === $base ? 'meta_key' : ( 'options' === $base ? 'option_name' : '' );
		$spans = array();
		foreach ( (array) $insert['rows'] as $row ) {
			$changed = false;
			foreach ( $row as $index => $literal ) {
				if ( ! is_array( $literal ) || 'str' !== ( $literal['type'] ?? '' ) ) continue;
				$column = (string) ( $insert['columns'][ $index ] ?? '' );
				$raw = substr( $sql, $literal['start'], $literal['end'] - $literal['start'] );
				if ( '' !== $key_column && $key_column === $column && $this->prefixes ) {
					$value = self::sql_unescape( $raw, $literal['quote'] );
					$new = $this->rename_key( $value, $base );
					if ( $new !== $value ) { $this->stats['keys']++; $this->count( $table, 'keys' ); }
				} elseif ( in_array( $column, $keep, true ) ) {
					if ( $this->rewrites_values() && $this->may_contain_sql( $raw ) ) $this->count( $table, 'kept' );
					continue;
				} elseif ( $this->rewrites_values() && $this->may_contain_sql( $raw ) ) {
					$value = self::sql_unescape( $raw, $literal['quote'] );
					$new = $this->rewrite( $value );
					foreach ( array( 'malformed', 'too_large', 'opaque' ) as $flag ) if ( ! empty( $this->flags[ $flag ] ) ) $this->count( $table, $flag );
					if ( $new !== $value ) $this->count( $table, 'values' );
				} else {
					continue;
				}
				if ( $new === $value ) continue;
				$spans[] = array( $literal['start'], $literal['end'], self::sql_escape( $new ) );
				$changed = true;
			}
			if ( $changed ) $this->count( $table, 'rows' );
		}
		return $spans;
	}

	/* ---------------------------------------------------------------- Aplicación sobre una base ya importada --------- */

	/**
	 * Reescribe tablas ya importadas, por lotes de clave primaria (simple o compuesta) y con memoria acotada: primero
	 * busca en la base de datos las filas cuyo texto menciona el origen (LIKE), después las carga en grupos de hasta
	 * BATCH_BYTES y actualiza sólo las columnas que cambian. Las columnas de la clave primaria, `guid` y las que no son de
	 * texto no se tocan. Una tabla sin clave primaria se informa (`no_primary_key`) sin tocarla. Devuelve el informe por
	 * tabla; un error de la base de datos (salvo una clave duplicada, que se cuenta en `conflicts`) lanza una excepción.
	 */
	public function rewrite_tables( array $tables ) {
		global $wpdb;
		foreach ( $tables as $table ) {
			$table = (string) $table;
			list( $base ) = $this->kind( $table );
			$columns = $this->columns( $table );
			if ( null === $columns ) { $this->count( $table, 'unreadable' ); continue; }
			$primary = array_keys( array_filter( $columns, function( $c ) { return $c['primary']; } ) );
			$keep = array_merge( self::KEEP_COLUMNS[ $base ] ?? array(), $primary );
			$text = array_keys( array_filter( $columns, function( $c ) { return $c['text']; } ) );
			$text = array_values( array_diff( $text, $keep ) );
			$key_column = 'usermeta' === $base ? 'meta_key' : ( 'options' === $base ? 'option_name' : '' );
			if ( ! $text ) continue;
			$where = $this->like_clause( $text, $key_column && $this->prefixes && in_array( $key_column, $text, true ) ? $key_column : '' );
			if ( '' === $where ) continue;
			if ( ! $primary ) {
				$found = $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::quote_name( $table ) . ' WHERE ' . $where );
				if ( (int) $found ) $this->count( $table, 'no_primary_key', (int) $found );
				continue;
			}
			$size = implode( ' + ', array_map( function( $c ) { return 'COALESCE(LENGTH(' . self::quote_name( $c ) . '), 0)'; }, $text ) );
			$last = null;
			while ( true ) {
				$after = null === $last ? '' : self::after_clause( $primary, $last ) . ' AND ';
				$candidates = $wpdb->get_results( 'SELECT ' . implode( ', ', array_map( array( __CLASS__, 'quote_name' ), $primary ) ) . ', ' . $size . ' AS ' . self::quote_name( '__digi_size' ) . ' FROM ' . self::quote_name( $table ) . ' WHERE ' . $after . '(' . $where . ') ORDER BY ' . implode( ', ', array_map( array( __CLASS__, 'quote_name' ), $primary ) ) . ' LIMIT ' . self::BATCH_ROWS, ARRAY_A );
				if ( ! is_array( $candidates ) || ( isset( $wpdb->last_error ) && '' !== $wpdb->last_error ) ) throw new Exception( 'No se pudo leer la tabla ' . $table . ' para reescribirla: ' . $wpdb->last_error );
				if ( ! $candidates ) break;
				$group = array();
				$bytes = 0;
				foreach ( $candidates as $index => $candidate ) {
					$group[] = $candidate;
					$bytes += (int) $candidate['__digi_size'];
					if ( $bytes >= self::BATCH_BYTES || count( $candidates ) - 1 === $index ) {
						$this->rewrite_group( $table, $base, $primary, $text, $key_column, $group );
						$group = array();
						$bytes = 0;
					}
				}
				$tail = end( $candidates );
				$last = array();
				foreach ( $primary as $column ) $last[ $column ] = $tail[ $column ];
				if ( count( $candidates ) < self::BATCH_ROWS ) break;
			}
		}
		return $this->tables;
	}
	private function rewrite_group( $table, $base, array $primary, array $text, $key_column, array $group ) {
		global $wpdb;
		$keys = array();
		foreach ( $group as $candidate ) {
			$parts = array();
			foreach ( $primary as $column ) $parts[] = self::quote_name( $column ) . ' = ' . self::sql_literal( $candidate[ $column ] );
			$keys[] = '(' . implode( ' AND ', $parts ) . ')';
		}
		$rows = $wpdb->get_results( 'SELECT ' . implode( ', ', array_map( array( __CLASS__, 'quote_name' ), array_unique( array_merge( $primary, $text ) ) ) ) . ' FROM ' . self::quote_name( $table ) . ' WHERE ' . implode( ' OR ', $keys ), ARRAY_A );
		if ( ! is_array( $rows ) || ( isset( $wpdb->last_error ) && '' !== $wpdb->last_error ) ) throw new Exception( 'No se pudo leer la tabla ' . $table . ' para reescribirla: ' . $wpdb->last_error );
		foreach ( $rows as $row ) {
			$set = array();
			foreach ( $text as $column ) {
				$value = $row[ $column ];
				if ( null === $value ) continue;
				if ( $column === $key_column && $this->prefixes ) {
					$new = $this->rename_key( $value, $base );
					if ( $new !== $value ) { $this->stats['keys']++; $this->count( $table, 'keys' ); }
				} else {
					$new = $this->rewrite( $value );
					foreach ( array( 'malformed', 'too_large', 'opaque' ) as $flag ) if ( ! empty( $this->flags[ $flag ] ) ) $this->count( $table, $flag );
					if ( $new !== $value ) $this->count( $table, 'values' );
				}
				if ( $new !== $value ) $set[] = self::quote_name( $column ) . ' = ' . self::sql_literal( $new );
			}
			if ( ! $set ) continue;
			$where = array();
			foreach ( $primary as $column ) $where[] = self::quote_name( $column ) . ' = ' . self::sql_literal( $row[ $column ] );
			$done = $wpdb->query( 'UPDATE ' . self::quote_name( $table ) . ' SET ' . implode( ', ', $set ) . ' WHERE ' . implode( ' AND ', $where ) );
			if ( false === $done ) {
				if ( false !== stripos( (string) $wpdb->last_error, 'duplicate' ) || false !== stripos( (string) $wpdb->last_error, 'unique' ) ) { $this->count( $table, 'conflicts' ); continue; }
				throw new Exception( 'No se pudo reescribir una fila de ' . $table . ': ' . $wpdb->last_error );
			}
			$this->count( $table, 'rows' );
		}
	}

	/**
	 * Cuenta lo que quedó del origen en las tablas (ya importadas y reescritas): la base de datos busca con LIKE las
	 * filas que mencionan un host o una ruta del origen y cada candidata (hasta CHECK_ROWS por tabla) se clasifica con
	 * el mismo criterio estricto que la reescritura. `urls` y `paths` (lo que la reescritura habría cambiado y sigue ahí)
	 * deben quedar en 0; se informan `guid` (sin tocar a propósito), `kept` (lo que las reglas dejan a propósito:
	 * serializado dañado, objeto C:, clave que chocaría, anidamiento o tamaño excesivos), `emails` (`@origen.com`) y
	 * `other` (el host sin esquema ni `//`, como texto). `complete` es false si alguna tabla tenía más candidatas de las
	 * que se revisaron.
	 */
	public function leftovers( array $tables ) {
		global $wpdb;
		$total = array( 'urls' => 0, 'paths' => 0, 'guid' => 0, 'kept' => 0, 'emails' => 0, 'other' => 0, 'complete' => true, 'tables' => array() );
		if ( ! $this->rewrites_values() ) return $total;
		foreach ( $tables as $table ) {
			$table = (string) $table;
			list( $base ) = $this->kind( $table );
			$columns = $this->columns( $table );
			if ( null === $columns ) continue;
			$text = array_keys( array_filter( $columns, function( $c ) { return $c['text']; } ) );
			$keep = self::KEEP_COLUMNS[ $base ] ?? array();
			$counts = array( 'urls' => 0, 'paths' => 0, 'guid' => 0, 'kept' => 0, 'emails' => 0, 'other' => 0 );
			foreach ( $text as $column ) {
				$where = $this->like_clause( array( $column ), '' );
				if ( '' === $where ) continue;
				if ( in_array( $column, $keep, true ) ) {
					$counts['guid'] += (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::quote_name( $table ) . ' WHERE ' . $where );
					continue;
				}
				$values = $wpdb->get_col( 'SELECT ' . self::quote_name( $column ) . ' FROM ' . self::quote_name( $table ) . ' WHERE ' . $where . ' LIMIT ' . ( self::CHECK_ROWS + 1 ) );
				if ( ! is_array( $values ) ) continue;
				if ( count( $values ) > self::CHECK_ROWS ) { $total['complete'] = false; array_pop( $values ); }
				foreach ( $values as $value ) foreach ( $this->classify( (string) $value ) as $what => $n ) $counts[ $what ] += $n;
			}
			if ( array_sum( $counts ) ) $total['tables'][ $table ] = $counts;
			foreach ( $counts as $what => $n ) $total[ $what ] += $n;
		}
		return $total;
	}

	/**
	 * Clasifica las menciones del origen que quedan en un valor: `urls` y `paths` (lo que la reescritura cambiaría: un
	 * resto que no debía quedar), `kept` (lo que deja a propósito), `emails` y `other` (el host del origen en otro
	 * contexto). Una URL del destino que empieza igual que una del origen (mismo host con otra ruta) no cuenta.
	 */
	public function classify( $value ) {
		$out = array( 'urls' => 0, 'paths' => 0, 'kept' => 0, 'emails' => 0, 'other' => 0 );
		if ( ! is_string( $value ) || '' === $value ) return $out;
		$this->scan_urls( $value, true, $out['urls'] );
		if ( $this->path_regex ) $out['paths'] += preg_match_all( $this->path_regex, $value );
		if ( $out['urls'] || $out['paths'] ) {
			// Lo que la reescritura deja a propósito (serializado dañado, objeto C:, clave que chocaría, anidamiento o tamaño
			// excesivos) es `kept`; sólo lo que todavía cambiaría es un resto que no debía quedar.
			$stats = $this->stats;
			$flags = $this->flags;
			$after = $this->rewrite( $value );
			$this->stats = $stats;
			$this->flags = $flags;
			$urls = 0;
			$this->scan_urls( $after, true, $urls );
			$paths = $this->path_regex ? preg_match_all( $this->path_regex, $after ) : 0;
			$kept = min( $out['urls'], $urls ) + min( $out['paths'], $paths );
			$out['urls'] -= min( $out['urls'], $urls );
			$out['paths'] -= min( $out['paths'], $paths );
			$out['kept'] = $kept;
		}
		foreach ( array_keys( $this->hosts ) as $host ) {
			$out['emails'] += preg_match_all( '~@' . preg_quote( $host, '~' ) . '(?![a-z0-9-]|\.[a-z0-9])~i', $value );
			$out['other'] += preg_match_all( '~(?<![a-z0-9.@/-])' . preg_quote( $host, '~' ) . '(?![a-z0-9-]|\.[a-z0-9])~i', $value );
		}
		return $out;
	}

	/** Informe por tabla de lo reescrito: rows, values, keys, kept, malformed, too_large, opaque, conflicts… */
	public function tables_report() { return $this->tables; }
	/** Nombres de las tablas en el destino tras attach() (los del respaldo con el prefijo nuevo). */
	public function table_names() { return array_values( $this->table_map ); }

	/* ---------------------------------------------------------------- Plan ------------------------------------------ */

	/**
	 * Plan de reubicación. `$origin` y `$destination` describen cada instalación (ver describe()):
	 *
	 *     array(
	 *       'home' => 'https://origen.com', 'siteurl' => 'https://origen.com/wp',          // sin barra final
	 *       'abspath' => '/var/www/origen', 'abspath_real' => '/srv/releases/7',            // rutas absolutas, opcionales
	 *       'content_dir' => '/var/www/origen/wp-content', 'uploads_dir' => '/var/www/origen/wp-content/uploads',
	 *       'prefix' => 'wp_',
	 *       'multisite' => true, 'subdomain' => false,
	 *       'network' => array( 'domain' => 'origen.com', 'path' => '/' ),
	 *       'sites' => array( 1 => array( 'domain' => 'origen.com', 'path' => '/', 'home' => '…', 'siteurl' => '…' ), 2 => … ),
	 *       'config' => array( 'DOMAIN_CURRENT_SITE' => …, 'PATH_CURRENT_SITE' => …, 'SUBDOMAIN_INSTALL' => … ), // sólo el destino
	 *     )
	 *
	 * `$args`: `addresses` = `current` (por defecto: el sitio conserva sus direcciones, las del destino) o `backup`
	 * (conserva las del respaldo: no se cambian URL); `sites` = dominio y ruta de destino explícitos por sitio
	 * (`array( 2 => array( 'domain' => …, 'path' => … ) )`); `prefixes` = mapa de prefijos explícito (por defecto el del
	 * origen al del destino). Las rutas del servidor y el prefijo siguen siempre al destino. Lo que impide aplicarlo
	 * queda en problems(), con qué cambiar; nada se toca al construirlo.
	 */
	public static function plan( array $origin, array $destination, array $args = array() ) {
		$plan = new self( $args );
		$plan->origin = self::normalize( $origin );
		$plan->destination = self::normalize( $destination );
		// Una red sin su fila de `site` (o un respaldo que no la describe): su dominio y su ruta son los de su sitio principal.
		foreach ( array( 'origin', 'destination' ) as $side ) {
			if ( ! $plan->{$side}['multisite'] || '' !== $plan->{$side}['network']['domain'] ) continue;
			$home = self::parse_url( $plan->{$side}['home'] );
			if ( $home ) $plan->{$side}['network'] = array( 'domain' => $home['host'], 'path' => self::slash_path( $home['path'] ) );
		}
		$plan->mode = 'backup' === ( $args['addresses'] ?? 'current' ) ? 'backup' : 'current';
		$o = $plan->origin;
		$d = $plan->destination;
		$prefixes = isset( $args['prefixes'] ) && is_array( $args['prefixes'] ) ? $args['prefixes'] : ( '' !== $o['prefix'] && '' !== $d['prefix'] ? array( $o['prefix'] => $d['prefix'] ) : array() );
		$plan->set_prefixes( $prefixes, $d['prefix'] );
		$plan->check_installations( $args );
		// Las tablas que cambian de nombre se conocen por la lista del manifiesto; sin ella sólo vale el mismo prefijo.
		if ( $plan->prefixes && array_key_exists( 'tables', $args ) ) {
			if ( ! is_array( $args['tables'] ) ) $plan->problems[] = 'Este respaldo es de un formato anterior que no lista sus tablas: sólo se puede restaurar en una instalación con su mismo prefijo (' . $o['prefix'] . '). Cambia $table_prefix en wp-config.php a \'' . $o['prefix'] . '\' en una instalación nueva, o crea antes un respaldo nuevo del origen.';
			else {
				try { $plan->table_map( $args['tables'] ); } catch ( Exception $e ) { $plan->problems[] = $e->getMessage(); }
			}
		}
		if ( $plan->problems ) return $plan;
		// Rutas del servidor: siempre las del destino, de la más larga a la más corta.
		foreach ( array( 'uploads_dir', 'content_dir', 'abspath' ) as $key ) {
			if ( '' !== $o[ $key ] && '' !== $d[ $key ] ) $plan->add_path( $o[ $key ], $d[ $key ] );
			// La ruta real de un despliegue con enlace (current → releases/N) también, salvo que sea la misma aquí.
			if ( 'abspath' === $key && '' !== $o['abspath_real'] && '' !== $d['abspath'] && $o['abspath_real'] !== $d['abspath_real'] ) $plan->add_path( $o['abspath_real'], $d['abspath'] );
		}
		if ( 'current' === $plan->mode ) {
			if ( $o['multisite'] ) $plan->plan_network( (array) ( $args['sites'] ?? array() ) );
			else {
				foreach ( array( 'siteurl', 'home' ) as $key ) if ( '' !== $o[ $key ] && '' !== $d[ $key ] ) $plan->add_url( $o[ $key ], $d[ $key ] );
				foreach ( (array) $o['extra_urls'] as $url ) if ( '' !== $d['home'] ) $plan->add_url( $url, $d['home'] );
			}
		}
		return $plan;
	}
	/** Lo que impide aplicar el plan, con qué cambiar (vacío si se puede). */
	public function problems() { return $this->problems; }
	/** true si la base de datos importada necesita algún cambio. */
	public function needed() { return (bool) ( $this->prefixes || $this->rewrites_values() || $this->network_changes() ); }
	public function mode() { return $this->mode; }
	/** Dominio y ruta de cada sitio de la red: id => array( from => array( domain, path ), to => array( domain, path ) ). */
	public function sites() { return $this->sites; }
	public function origin() { return $this->origin; }
	public function destination() { return $this->destination; }

	/**
	 * Resumen público para la pantalla, REST y WP-CLI: «Origen → este sitio» y qué se reescribirá. Sin secretos.
	 */
	public function summary() {
		$o = $this->origin;
		$d = $this->destination;
		$changes = array();
		if ( $this->prefixes ) $changes[] = 'prefijo de tablas ' . implode( ', ', array_map( function( $from, $to ) { return $from . ' → ' . $to; }, array_keys( $this->prefixes ), $this->prefixes ) ) . ' (también las claves que lo llevan)';
		if ( $this->url_pairs ) $changes[] = 'direcciones ' . implode( ', ', array_map( function( $from, $to ) { return $from . ' → ' . $to; }, array_keys( $this->url_pairs ), $this->url_pairs ) ) . ' (también en datos serializados, JSON y URL codificadas; no la columna guid ni los correos)';
		if ( $this->path_pairs ) $changes[] = 'rutas del servidor ' . implode( ', ', array_map( function( $from, $to ) { return $from . ' → ' . $to; }, array_keys( $this->path_pairs ), $this->path_pairs ) );
		$moved = array_filter( $this->sites, function( $site ) { return $site['from'] !== $site['to']; } );
		if ( $moved ) $changes[] = count( $moved ) . ' ' . ( 1 === count( $moved ) ? 'sitio' : 'sitios' ) . ' de la red con otro dominio o ruta';
		return array(
			'needed' => $this->needed(),
			'addresses' => $this->mode,
			'origin' => array( 'home' => $o['home'], 'prefix' => $o['prefix'], 'multisite' => $o['multisite'], 'subdomain' => $o['subdomain'], 'sites' => count( $o['sites'] ) ),
			'destination' => array( 'home' => $d['home'], 'prefix' => $d['prefix'], 'multisite' => $d['multisite'], 'subdomain' => $d['subdomain'] ),
			'rewrites' => $changes,
			'problems' => $this->problems,
			'text' => $this->summary_text( $changes ),
		);
	}
	private function summary_text( array $changes ) {
		$o = $this->origin;
		$d = $this->destination;
		$from = '' !== $o['home'] ? $o['home'] : 'origen desconocido';
		$head = 'Origen ' . $from . ( '' !== $o['prefix'] ? ' (prefijo ' . $o['prefix'] . ')' : '' ) . ' → este sitio ' . $d['home'] . ' (prefijo ' . $d['prefix'] . ').';
		if ( $this->problems ) return $head . ' No se puede restaurar aquí: ' . implode( ' ', $this->problems );
		if ( ! $changes ) return $head . ' Mismas direcciones, rutas y prefijo: no se reescribe nada.';
		$keep = 'backup' === $this->mode && '' !== $o['home'] && $o['home'] !== $d['home'] ? ' Se conservan las direcciones del respaldo: el sitio quedará en ' . $o['home'] . '.' : '';
		return $head . ' Se reescribirá: ' . implode( '; ', $changes ) . '.' . $keep;
	}

	/** Comprobaciones que no dependen de la base de datos: tipo de instalación, red del mismo tipo y wp-config.php. */
	private function check_installations( array $args ) {
		$o = $this->origin;
		$d = $this->destination;
		if ( $o['multisite'] !== $d['multisite'] ) {
			$this->problems[] = $o['multisite']
				? 'El respaldo es una red (Multisite ' . ( $o['subdomain'] ? 'por subdominios' : 'por subdirectorios' ) . ') y este WordPress es un sitio individual: convierte esta instalación en una red ' . ( $o['subdomain'] ? 'por subdominios' : 'por subdirectorios' ) . ' (Herramientas → Configuración de la red) y vuelve a restaurar.'
				: 'El respaldo es un sitio individual y este WordPress es una red: restáuralo en una instalación individual (o crea el sitio en la red e importa su contenido).';
			return;
		}
		if ( ! $o['multisite'] ) return;
		if ( $o['subdomain'] !== $d['subdomain'] ) {
			$this->problems[] = 'El respaldo es una red por ' . ( $o['subdomain'] ? 'subdominios' : 'subdirectorios' ) . ' y esta red es por ' . ( $d['subdomain'] ? 'subdominios' : 'subdirectorios' ) . ': en wp-config.php define SUBDOMAIN_INSTALL como ' . ( $o['subdomain'] ? 'true' : 'false' ) . ' en una red de ese tipo y vuelve a restaurar.';
			return;
		}
		// La red queda con el dominio y la ruta de este sitio (o con los del respaldo): wp-config.php debe declararlos.
		$target = 'backup' === ( $args['addresses'] ?? 'current' ) ? $o['network'] : $d['network'];
		$config = (array) ( $d['config'] ?? array() );
		$domain = isset( $config['DOMAIN_CURRENT_SITE'] ) ? strtolower( (string) $config['DOMAIN_CURRENT_SITE'] ) : null;
		$path = isset( $config['PATH_CURRENT_SITE'] ) ? self::slash_path( (string) $config['PATH_CURRENT_SITE'] ) : null;
		if ( '' === $target['domain'] ) {
			$this->problems[] = 'No se pudo leer el dominio de la red del respaldo (tabla site): no se puede restaurar como red en otro lugar.';
			return;
		}
		// Sin las constantes, WordPress busca la red por el dominio de la petición en las tablas, que la restauración deja
		// con el dominio y la ruta de destino; definidas con otro valor, la red no cargaría.
		if ( ( null !== $domain && $domain !== $target['domain'] ) || ( null !== $path && $path !== $target['path'] ) ) {
			$this->problems[] = 'En wp-config.php, DOMAIN_CURRENT_SITE debe ser \'' . $target['domain'] . '\' y PATH_CURRENT_SITE \'' . $target['path'] . '\' (hoy son \'' . (string) $domain . '\' y \'' . (string) $path . '\')' . ( 'backup' === ( $args['addresses'] ?? 'current' ) ? ', porque se conservan las direcciones del respaldo' : '' ) . '. Corrígelo y vuelve a restaurar.';
		}
		if ( isset( $config['SITE_ID_CURRENT_SITE'] ) && $o['networks'] && ! isset( $o['networks'][ (int) $config['SITE_ID_CURRENT_SITE'] ] ) ) $this->problems[] = 'SITE_ID_CURRENT_SITE es ' . (int) $config['SITE_ID_CURRENT_SITE'] . ' en wp-config.php y el respaldo no tiene esa red: usa el identificador de su red (' . implode( ', ', array_keys( $o['networks'] ) ) . ').';
		if ( isset( $config['BLOG_ID_CURRENT_SITE'] ) && $o['sites'] && ! isset( $o['sites'][ (int) $config['BLOG_ID_CURRENT_SITE'] ] ) ) $this->problems[] = 'BLOG_ID_CURRENT_SITE es ' . (int) $config['BLOG_ID_CURRENT_SITE'] . ' en wp-config.php y el respaldo no tiene ese sitio: usa el de su sitio principal.';
	}

	/** Dominio y ruta de destino de cada sitio y sus URL (sólo con `addresses` = current). */
	private function plan_network( array $explicit ) {
		$o = $this->origin;
		$d = $this->destination;
		$from = $o['network'];
		$to = $d['network'];
		$scheme = (string) ( self::parse_url( $d['home'] )['scheme'] ?? 'https' );
		$sites = $o['sites'] ? $o['sites'] : array( 1 => array( 'domain' => $from['domain'], 'path' => $from['path'], 'home' => $o['home'], 'siteurl' => $o['siteurl'] ) );
		foreach ( $sites as $id => $site ) {
			$domain = strtolower( (string) $site['domain'] );
			$path = self::slash_path( (string) $site['path'] );
			$new_domain = $domain;
			$new_path = $path;
			$own = isset( $explicit[ $id ]['domain'] ) || $domain === $from['domain'] || 'www.' . $from['domain'] === $domain || ( $o['subdomain'] && self::ends_with( $domain, '.' . $from['domain'] ) );
			if ( ! $own ) {
				// Un sitio con dominio propio (cliente.com) se queda tal cual: su dominio no es de la red que se muda.
				$this->sites[ (int) $id ] = array( 'from' => array( 'domain' => $domain, 'path' => $path ), 'to' => array( 'domain' => $domain, 'path' => $path ) );
				continue;
			}
			if ( isset( $explicit[ $id ]['domain'] ) ) {
				$new_domain = strtolower( (string) $explicit[ $id ]['domain'] );
				$new_path = self::slash_path( (string) ( $explicit[ $id ]['path'] ?? '/' ) );
			} elseif ( $domain === $from['domain'] || 'www.' . $from['domain'] === $domain ) {
				$new_domain = $to['domain'];
				if ( 0 === strpos( $path, $from['path'] ) ) $new_path = $to['path'] . substr( $path, strlen( $from['path'] ) );
			} elseif ( $o['subdomain'] && self::ends_with( $domain, '.' . $from['domain'] ) ) {
				$new_domain = substr( $domain, 0, -strlen( $from['domain'] ) ) . $to['domain'];
				if ( 0 === strpos( $path, $from['path'] ) ) $new_path = $to['path'] . substr( $path, strlen( $from['path'] ) );
			}
			$this->sites[ (int) $id ] = array( 'from' => array( 'domain' => $domain, 'path' => $path ), 'to' => array( 'domain' => $new_domain, 'path' => $new_path ) );
			$base_from = $domain . self::untrail( $path );
			$base_to = $scheme . '://' . $new_domain . self::untrail( $new_path );
			$this->sites[ (int) $id ]['home'] = $this->site_url_for( (string) ( $site['home'] ?? '' ), $domain, $path, $base_to );
			$this->sites[ (int) $id ]['siteurl'] = $this->site_url_for( (string) ( $site['siteurl'] ?? '' ), $domain, $path, $base_to );
			// La URL propia del sitio (home y siteurl, que pueden diferir de dominio + ruta) y la de dominio + ruta.
			foreach ( array( 'siteurl', 'home' ) as $key ) if ( '' !== (string) ( $site[ $key ] ?? '' ) && '' !== $this->sites[ (int) $id ][ $key ] ) $this->add_url( $site[ $key ], $this->sites[ (int) $id ][ $key ] );
			$this->add_url( 'http://' . $base_from, $base_to );
		}
		if ( $from['domain'] !== $to['domain'] || $from['path'] !== $to['path'] ) $this->add_url( 'http://' . $from['domain'] . self::untrail( $from['path'] ), $scheme . '://' . $to['domain'] . self::untrail( $to['path'] ) );
	}
	/** URL de destino de una URL de un sitio: si empieza por su dominio + ruta, la parte cambia; si no (dominio propio), se queda. */
	private function site_url_for( $url, $domain, $path, $base_to ) {
		$parsed = self::parse_url( $url );
		if ( ! $parsed ) return $base_to;
		$base = self::untrail( $path );
		if ( $parsed['host'] !== $domain && $parsed['host'] !== 'www.' . $domain ) return '';
		if ( '' !== $base && 0 !== strpos( $parsed['path'] . '/', $base . '/' ) ) return '';
		return $base_to . substr( $parsed['path'], strlen( $base ) );
	}
	private function network_changes() {
		foreach ( $this->sites as $site ) if ( $site['from'] !== $site['to'] ) return true;
		return false;
	}

	/**
	 * Después de importar el SQL (y de extraer los archivos): en una red, el dominio y la ruta de la red (`site`,
	 * `sitemeta.siteurl`) y de cada sitio (`blogs`); las direcciones `home` y `siteurl` de cada sitio; las cachés que
	 * guardan URL (transitorios, CSS generado de Elements/Elementor en la base de datos y en uploads, reglas de
	 * reescritura de cada sitio, la caché de objetos) y la comprobación final. Lo que no es la base de datos (borrar un
	 * archivo de caché, regenerar .htaccess, la comprobación) nunca hace fallar la restauración: se informa en
	 * `warnings`. Devuelve el informe completo (ver report()).
	 */
	public function finish( array $tables ) {
		global $wpdb;
		$d = $this->destination;
		$report = array( 'network' => array(), 'addresses' => array(), 'caches' => array(), 'warnings' => array() );
		$prefix = $d['prefix'];
		$names = array_values( array_filter( array_map( 'strval', $tables ), function( $table ) use ( $prefix ) { return '' === $prefix || 0 === strpos( $table, $prefix ); } ) );
		$has = array_flip( $names );
		// Red: dominio y ruta (la base de datos manda; sin esto la red no carga en su nuevo dominio).
		if ( 'current' === $this->mode && $this->origin['multisite'] ) {
			$from = $this->origin['network'];
			$to = $d['network'];
			$scheme = (string) ( self::parse_url( $d['home'] )['scheme'] ?? 'https' );
			// La red del respaldo que se muda: la que tiene su dominio y su ruta (con varias redes, las demás se quedan).
			$ids = array();
			foreach ( $this->origin['networks'] as $id => $row ) if ( $row['domain'] === $from['domain'] && $row['path'] === $from['path'] ) $ids[] = (int) $id;
			if ( ! $ids ) $ids = array( 1 );
			foreach ( $ids as $id ) {
				if ( isset( $has[ $prefix . 'site' ] ) && $from !== $to ) {
					$done = $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::quote_name( $prefix . 'site' ) . ' SET domain = %s, path = %s WHERE id = %d', $to['domain'], $to['path'], $id ) );
					if ( false === $done ) throw new Exception( 'No se pudo cambiar el dominio de la red: ' . $wpdb->last_error );
					$report['network']['site'] = (int) $done;
				}
				if ( isset( $has[ $prefix . 'sitemeta' ] ) ) {
					$done = $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::quote_name( $prefix . 'sitemeta' ) . " SET meta_value = %s WHERE meta_key = 'siteurl' AND site_id = %d", $scheme . '://' . $to['domain'] . $to['path'], $id ) );
					if ( false === $done ) throw new Exception( 'No se pudo cambiar la dirección de la red: ' . $wpdb->last_error );
					$report['network']['sitemeta'] = (int) $done;
				}
			}
			if ( isset( $has[ $prefix . 'blogs' ] ) ) {
				$moved = 0;
				foreach ( $this->sites as $id => $site ) {
					if ( $site['from'] === $site['to'] ) continue;
					$done = $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::quote_name( $prefix . 'blogs' ) . ' SET domain = %s, path = %s WHERE blog_id = %d', $site['to']['domain'], $site['to']['path'], $id ) );
					if ( false === $done ) throw new Exception( 'No se pudo cambiar el dominio del sitio ' . $id . ': ' . $wpdb->last_error );
					$moved += (int) $done;
				}
				$report['network']['blogs'] = $moved;
			}
		}
		// home y siteurl de cada sitio: exactamente los del destino (la reescritura ya los cambió; esto lo garantiza).
		if ( 'current' === $this->mode ) {
			$targets = array();
			if ( $this->origin['multisite'] ) {
				foreach ( $this->sites as $id => $site ) if ( isset( $site['home'] ) ) $targets[ 1 === (int) $id ? $prefix . 'options' : $prefix . $id . '_options' ] = array( 'home' => $site['home'], 'siteurl' => $site['siteurl'] );
			} elseif ( '' !== $d['home'] && ( $this->url_pairs || $this->prefixes ) ) {
				$targets[ $prefix . 'options' ] = array( 'home' => $d['home'], 'siteurl' => '' !== $d['siteurl'] ? $d['siteurl'] : $d['home'] );
			}
			foreach ( $targets as $table => $values ) {
				if ( ! isset( $has[ $table ] ) ) continue;
				foreach ( $values as $name => $value ) {
					if ( '' === (string) $value ) continue;
					$done = $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::quote_name( $table ) . ' SET option_value = %s WHERE option_name = %s', $value, $name ) );
					if ( false === $done ) throw new Exception( 'No se pudo fijar ' . $name . ' en ' . $table . ': ' . $wpdb->last_error );
					$report['addresses'][ $table . ':' . $name ] = $value;
				}
			}
		}
		if ( $this->needed() ) $report['caches'] = $this->flush_caches( $names, $report['warnings'] );
		try {
			$report['check'] = $this->leftovers( $names );
		} catch ( Throwable $e ) {
			$report['check'] = null;
			$report['warnings'][] = 'No se pudo hacer la comprobación final: ' . $e->getMessage();
		}
		if ( ! empty( $report['check']['urls'] ) || ! empty( $report['check']['paths'] ) ) $report['warnings'][] = 'Quedan ' . (int) $report['check']['urls'] . ' URL y ' . (int) $report['check']['paths'] . ' rutas del origen (ver la comprobación por tabla).';
		// Lo último: escribir .htaccess recarga el servidor web en algunos hostings (OpenLiteSpeed de Webuzo), y una
		// recarga corta la petición; aquí ya no queda nada de la base de datos ni de los archivos por hacer.
		$this->rewrite_rules_file( $report );
		$this->report = $report + array( 'summary' => $this->summary(), 'tables' => $this->tables, 'stats' => $this->stats );
		return $this->report;
	}
	/**
	 * Si cambió la ruta del sitio (/a → /b), las reglas de reescritura del servidor que trajo el respaldo apuntan a la
	 * anterior. En un sitio individual con Apache, el bloque de WordPress de .htaccess se regenera como al guardar los
	 * enlaces permanentes; en una red o con otro servidor (nginx) se avisa.
	 */
	private function rewrite_rules_file( array &$report ) {
		if ( 'current' !== $this->mode ) return;
		$from = self::parse_url( $this->origin['home'] );
		$to = self::parse_url( $this->destination['home'] );
		$moved = $this->origin['multisite'] ? $this->origin['network']['path'] !== $this->destination['network']['path'] : ( $from && $to && $from['path'] !== $to['path'] );
		if ( ! $moved ) return;
		$report['caches']['htaccess'] = false;
		// Sólo con las funciones que ya cargó esta petición (la pantalla de administración): incluir ahora
		// wp-admin/includes/misc.php cargaría el que acaban de traer los archivos del respaldo.
		if ( ! $this->origin['multisite'] && function_exists( 'save_mod_rewrite_rules' ) && function_exists( 'got_mod_rewrite' ) && function_exists( 'get_home_path' ) ) {
			try {
				if ( isset( $GLOBALS['wp_rewrite'] ) && is_object( $GLOBALS['wp_rewrite'] ) && method_exists( $GLOBALS['wp_rewrite'], 'init' ) ) $GLOBALS['wp_rewrite']->init();
				if ( got_mod_rewrite() ) $report['caches']['htaccess'] = (bool) save_mod_rewrite_rules();
			} catch ( Throwable $e ) {
				$report['warnings'][] = 'No se pudo regenerar .htaccess: ' . $e->getMessage();
			}
		}
		if ( ! $report['caches']['htaccess'] ) $report['warnings'][] = 'La ruta del sitio cambió: si el servidor usa reglas de reescritura propias (.htaccess de una red, nginx), actualízalas para la ruta nueva y guarda los enlaces permanentes.';
	}

	/** El informe de finish(): resumen, tablas, red, direcciones, cachés, comprobación y avisos. */
	public function report() { return $this->report; }

	/** Transitorios, reglas de reescritura, CSS de Elements/Elementor y caché de objetos de cada sitio restaurado. */
	private function flush_caches( array $names, array &$warnings ) {
		global $wpdb;
		$prefix = $this->destination['prefix'];
		$out = array( 'transients' => 0, 'rewrite_rules' => 0, 'elementor_meta' => 0, 'elementor_options' => 0, 'elementor_files' => 0, 'font_css' => 0 );
		foreach ( $names as $table ) {
			list( $base, $site ) = $this->kind( $table );
			try {
				if ( 'options' === $base ) {
					$done = $wpdb->query( 'DELETE FROM ' . self::quote_name( $table ) . " WHERE option_name LIKE '!_transient!_%' ESCAPE '!' OR option_name LIKE '!_site!_transient!_%' ESCAPE '!'" );
					$out['transients'] += (int) $done;
					$out['rewrite_rules'] += (int) $wpdb->query( 'DELETE FROM ' . self::quote_name( $table ) . " WHERE option_name = 'rewrite_rules'" );
					$out['elementor_options'] += (int) $wpdb->query( 'DELETE FROM ' . self::quote_name( $table ) . " WHERE option_name IN ('_elementor_assets_data', 'elementor-custom-breakpoints-files', '_elementor_global_css')" );
					$this->clear_upload_caches( $site, $out, $warnings );
				} elseif ( 'postmeta' === $base ) {
					$out['elementor_meta'] += (int) $wpdb->query( 'DELETE FROM ' . self::quote_name( $table ) . " WHERE meta_key IN ('_elementor_css', '_elementor_element_cache', '_elementor_page_assets')" );
				} elseif ( 'sitemeta' === $base ) {
					$out['transients'] += (int) $wpdb->query( 'DELETE FROM ' . self::quote_name( $table ) . " WHERE meta_key LIKE '!_site!_transient!_%' ESCAPE '!'" );
				}
			} catch ( Throwable $e ) {
				$warnings[] = 'No se pudo vaciar la caché de ' . $table . ': ' . $e->getMessage();
			}
		}
		if ( function_exists( 'wp_cache_flush' ) ) wp_cache_flush();
		return $out;
	}
	/** CSS generado de Elementor de un sitio (se regenera al visitarlo) y las URL de sus fuentes locales de Google. */
	private function clear_upload_caches( $site, array &$out, array &$warnings ) {
		$uploads = $this->destination['uploads_dir'];
		if ( '' === $uploads ) return;
		$dir = $this->destination['multisite'] && $site > 1 ? $uploads . '/sites/' . (int) $site : $uploads;
		foreach ( (array) glob( $dir . '/elementor/css/*' ) as $file ) {
			if ( ! is_file( $file ) || is_link( $file ) ) continue;
			if ( @unlink( $file ) ) $out['elementor_files']++;
			else $warnings[] = 'No se pudo borrar el CSS generado ' . basename( $file ) . '; regenera el CSS desde Elements.';
		}
		foreach ( (array) glob( $dir . '/elementor/google-fonts/css/*.css' ) as $file ) {
			if ( ! is_file( $file ) || is_link( $file ) || filesize( $file ) > 4194304 ) continue;
			$css = (string) @file_get_contents( $file );
			$new = $this->rewrite( $css );
			if ( $new === $css ) continue;
			if ( false !== @file_put_contents( $file, $new ) ) $out['font_css']++;
			else $warnings[] = 'No se pudo actualizar ' . basename( $file ) . ' de las fuentes locales.';
		}
	}

	/* ---------------------------------------------------------------- Descripción de una instalación ----------------- */

	/**
	 * Descripción de esta instalación para plan() (el destino) y para el manifiesto de un respaldo (el origen):
	 * direcciones sin barra final, rutas del servidor, prefijo, red (tipo, dominio y ruta) y, sólo para el destino, lo
	 * que declara wp-config.php. Con `$with_config` = false no incluye `config`.
	 */
	public static function describe( $with_config = true ) {
		global $wpdb;
		$home = function_exists( 'get_option' ) ? (string) get_option( 'home' ) : '';
		$siteurl = function_exists( 'get_option' ) ? (string) get_option( 'siteurl' ) : '';
		$uploads = function_exists( 'wp_upload_dir' ) ? (array) wp_upload_dir( null, false ) : array();
		if ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'get_main_site_id' ) && function_exists( 'switch_to_blog' ) && (int) get_current_blog_id() !== (int) get_main_site_id() ) {
			switch_to_blog( get_main_site_id() );
			$home = (string) get_option( 'home' );
			$siteurl = (string) get_option( 'siteurl' );
			$uploads = (array) wp_upload_dir( null, false );
			restore_current_blog();
		}
		if ( '' === $home && function_exists( 'home_url' ) ) $home = (string) home_url();
		if ( '' === $siteurl && function_exists( 'site_url' ) ) $siteurl = (string) site_url();
		$real = realpath( ABSPATH );
		$out = array(
			'version' => self::VERSION,
			'home' => self::untrail( $home ),
			'siteurl' => self::untrail( $siteurl ),
			'abspath' => self::clean_path( ABSPATH ),
			'abspath_real' => false === $real ? '' : self::clean_path( $real ),
			'content_dir' => defined( 'WP_CONTENT_DIR' ) ? self::clean_path( WP_CONTENT_DIR ) : '',
			'uploads_dir' => self::clean_path( (string) ( $uploads['basedir'] ?? '' ) ),
			'prefix' => (string) ( $wpdb->base_prefix ?? '' ),
			'multisite' => function_exists( 'is_multisite' ) && is_multisite(),
			'subdomain' => function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'is_subdomain_install' ) && is_subdomain_install(),
		);
		if ( $out['multisite'] && function_exists( 'get_network' ) ) {
			$network = get_network();
			if ( $network ) $out['network'] = array( 'domain' => strtolower( (string) $network->domain ), 'path' => self::slash_path( (string) $network->path ) );
		}
		if ( $out['abspath_real'] === $out['abspath'] ) $out['abspath_real'] = '';
		if ( $with_config ) {
			$out['config'] = array();
			foreach ( array( 'DOMAIN_CURRENT_SITE', 'PATH_CURRENT_SITE', 'SUBDOMAIN_INSTALL', 'SITE_ID_CURRENT_SITE', 'BLOG_ID_CURRENT_SITE' ) as $constant ) if ( defined( $constant ) ) $out['config'][ $constant ] = constant( $constant );
		}
		return $out;
	}

	/**
	 * Origen de un respaldo: el bloque `origin` de su manifiesto (desde esta versión) completado con lo que su propio SQL
	 * dice (Digitalisimo_Backups_Relocate_Probe: `home` y `siteurl` de cada sitio, `blogs`, `site` y `sitemeta`), que
	 * manda para las direcciones porque es lo que guardan los datos. Un respaldo anterior no trae las rutas del servidor:
	 * esas no se reescriben (límite declarado).
	 */
	public static function origin_of( array $manifest, $probe = null ) {
		$block = is_array( $manifest['origin'] ?? null ) ? $manifest['origin'] : array();
		$first = function( ...$values ) { foreach ( $values as $value ) if ( is_string( $value ) && '' !== $value ) return $value; return ''; };
		$origin = array(
			'home' => $first( $block['home'] ?? null, $manifest['home_url'] ?? null ),
			'siteurl' => $first( $block['siteurl'] ?? null, $manifest['site_url'] ?? null ),
			'abspath' => (string) ( $block['abspath'] ?? '' ),
			'abspath_real' => (string) ( $block['abspath_real'] ?? '' ),
			'content_dir' => (string) ( $block['content_dir'] ?? '' ),
			'uploads_dir' => (string) ( $block['uploads_dir'] ?? '' ),
			'prefix' => (string) ( $manifest['table_prefix'] ?? $block['prefix'] ?? '' ),
			'multisite' => ! empty( $manifest['multisite'] ),
			'subdomain' => ! empty( $block['subdomain'] ),
			'network' => is_array( $block['network'] ?? null ) ? $block['network'] : array(),
			'extra_urls' => array(),
		);
		// $probe: la sonda, o su result() ya reunido (una restauración por tramos recorre el SQL en varias peticiones).
		if ( $probe instanceof Digitalisimo_Backups_Relocate_Probe || is_array( $probe ) ) {
			$found = is_array( $probe ) ? $probe + array( 'sites' => array(), 'networks' => array(), 'subdomain' => null ) : $probe->result();
			$main = $found['sites'][1] ?? array();
			// Las direcciones que guarda la base de datos mandan; las del manifiesto (home_url() con WP_HOME, por ejemplo)
			// se reescriben también si son otras.
			foreach ( array( 'home', 'siteurl' ) as $key ) {
				if ( '' === (string) ( $main[ $key ] ?? '' ) ) continue;
				if ( '' !== $origin[ $key ] && self::untrail( $origin[ $key ] ) !== self::untrail( $main[ $key ] ) ) $origin['extra_urls'][] = $origin[ $key ];
				$origin[ $key ] = $main[ $key ];
			}
			if ( null !== $found['subdomain'] ) $origin['subdomain'] = (bool) $found['subdomain'];
			if ( $found['networks'] ) {
				$origin['networks'] = $found['networks'];
				$first = $found['networks'][ min( array_keys( $found['networks'] ) ) ];
				if ( ! $origin['network'] || ! isset( $origin['network']['domain'] ) ) $origin['network'] = $first;
			}
			$origin['sites'] = array();
			foreach ( $found['sites'] as $id => $site ) if ( isset( $site['domain'] ) ) $origin['sites'][ $id ] = $site;
			// Sin la marca de sitemeta: es por subdominios si algún sitio vive en otro host con la ruta de la red.
			if ( null === $found['subdomain'] && $origin['multisite'] && isset( $origin['network']['domain'] ) ) {
				foreach ( $origin['sites'] as $site ) if ( strtolower( $site['domain'] ) !== strtolower( $origin['network']['domain'] ) && self::ends_with( strtolower( $site['domain'] ), '.' . strtolower( $origin['network']['domain'] ) ) ) $origin['subdomain'] = true;
			}
		}
		return $origin;
	}

	/* ---------------------------------------------------------------- Utilidades ------------------------------------ */

	/** Literal SQL de un valor de cadena (como el exportador, más la comilla doble): no depende de la conexión. */
	public static function sql_escape( $value ) {
		return str_replace( array( '\\', "\0", "\n", "\r", "\x1a", "'", '"' ), array( '\\\\', '\\0', '\\n', '\\r', '\\Z', "\\'", '\\"' ), (string) $value );
	}
	/** Contenido de un literal SQL (entre comillas `$quote`) tal como lo lee MySQL con las barras invertidas activas. */
	public static function sql_unescape( $raw, $quote = "'" ) {
		if ( false === strpos( $raw, '\\' ) && false === strpos( $raw, $quote . $quote ) ) return $raw;
		$map = array( '0' => "\0", 'n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'Z' => "\x1a" );
		$out = '';
		$i = 0;
		$n = strlen( $raw );
		while ( $i < $n ) {
			$run = strcspn( $raw, '\\' . $quote, $i );
			$out .= substr( $raw, $i, $run );
			$i += $run;
			if ( $i >= $n ) break;
			if ( '\\' === $raw[ $i ] ) {
				$next = $raw[ $i + 1 ] ?? '';
				if ( isset( $map[ $next ] ) ) $out .= $map[ $next ];
				elseif ( '%' === $next || '_' === $next ) $out .= '\\' . $next;
				else $out .= $next;
				$i += 2;
			} else {
				$out .= $quote;
				$i += 2;
			}
		}
		return $out;
	}
	public static function quote_name( $name ) { return '`' . str_replace( '`', '``', (string) $name ) . '`'; }
	/** Literal SQL de un valor para la conexión actual ($wpdb->prepare): igual en MySQL y en el SQLite de WordPress. */
	private static function sql_literal( $value ) {
		global $wpdb;
		if ( null === $value ) return 'NULL';
		if ( is_int( $value ) || ( is_string( $value ) && preg_match( '/^-?[1-9][0-9]{0,17}$|^0$/D', $value ) ) ) return (string) $value;
		return $wpdb->prepare( '%s', (string) $value );
	}
	private static function after_clause( array $primary, array $last ) {
		$parts = array();
		$equal = array();
		foreach ( $primary as $column ) {
			$parts[] = '(' . implode( ' AND ', array_merge( $equal, array( self::quote_name( $column ) . ' > ' . self::sql_literal( $last[ $column ] ) ) ) ) . ')';
			$equal[] = self::quote_name( $column ) . ' = ' . self::sql_literal( $last[ $column ] );
		}
		return '(' . implode( ' OR ', $parts ) . ')';
	}
	/** Condición LIKE (con `!` como escape, igual en MySQL y SQLite) que encuentra las filas que mencionan el origen. */
	private function like_clause( array $columns, $key_column ) {
		$needles = array();
		foreach ( array_keys( $this->hosts ) as $host ) $needles[ $host ] = true;
		foreach ( array_keys( $this->path_pairs ) as $path ) foreach ( array( 'plain', 'json', 'json2' ) as $style ) $needles[ str_replace( '/', self::STYLES[ $style ]['slash'], $path ) ] = true;
		$parts = array();
		$like = function( $text ) { return strtr( $text, array( '!' => '!!', '%' => '!%', '_' => '!_' ) ); };
		foreach ( $columns as $column ) {
			foreach ( array_keys( $needles ) as $needle ) $parts[] = self::quote_name( $column ) . ' LIKE ' . self::sql_literal( '%' . $like( $needle ) . '%' ) . " ESCAPE '!'";
		}
		foreach ( array_keys( $this->prefixes ) as $prefix ) if ( '' !== $key_column ) $parts[] = self::quote_name( $key_column ) . ' LIKE ' . self::sql_literal( $like( $prefix ) . '%' ) . " ESCAPE '!'";
		return implode( ' OR ', $parts );
	}
	/**
	 * Columnas de una tabla: nombre => array( text, primary ). Texto: char, varchar, text, json, binary, varbinary y blob
	 * (no enum ni set). null si no se pudo leer.
	 */
	private function columns( $table ) {
		global $wpdb;
		$rows = $wpdb->get_results( 'SHOW COLUMNS FROM ' . self::quote_name( $table ), ARRAY_A );
		if ( ! is_array( $rows ) || ! $rows ) return null;
		$out = array();
		foreach ( $rows as $row ) {
			$type = strtolower( (string) ( $row['Type'] ?? $row['type'] ?? '' ) );
			$out[ (string) ( $row['Field'] ?? $row['field'] ?? '' ) ] = array(
				'text' => (bool) preg_match( '/^(?:var)?char|text|^json|^(?:var)?binary|blob/', $type ),
				'primary' => 'PRI' === strtoupper( (string) ( $row['Key'] ?? $row['key'] ?? '' ) ),
			);
		}
		return $out;
	}
	private function count( $table, $what, $n = 1 ) {
		if ( ! isset( $this->tables[ $table ][ $what ] ) ) $this->tables[ $table ][ $what ] = 0;
		$this->tables[ $table ][ $what ] += $n;
	}
	private static function normalize( array $d ) {
		$network = (array) ( $d['network'] ?? array() );
		$sites = array();
		foreach ( (array) ( $d['sites'] ?? array() ) as $id => $site ) {
			if ( ! is_array( $site ) || ! isset( $site['domain'], $site['path'] ) ) continue;
			$sites[ (int) $id ] = array( 'domain' => strtolower( (string) $site['domain'] ), 'path' => self::slash_path( (string) $site['path'] ), 'home' => self::untrail( (string) ( $site['home'] ?? '' ) ), 'siteurl' => self::untrail( (string) ( $site['siteurl'] ?? '' ) ) );
		}
		ksort( $sites );
		$networks = array();
		foreach ( (array) ( $d['networks'] ?? array() ) as $id => $row ) if ( is_array( $row ) && isset( $row['domain'] ) ) $networks[ (int) $id ] = array( 'domain' => strtolower( (string) $row['domain'] ), 'path' => self::slash_path( (string) ( $row['path'] ?? '/' ) ) );
		$url = function( $value ) { $value = self::untrail( (string) $value ); return self::parse_url( $value ) ? $value : ''; };
		return array(
			'home' => $url( $d['home'] ?? '' ),
			'siteurl' => $url( $d['siteurl'] ?? '' ),
			'abspath' => self::clean_path( $d['abspath'] ?? '' ),
			'abspath_real' => self::clean_path( $d['abspath_real'] ?? '' ),
			'content_dir' => self::clean_path( $d['content_dir'] ?? '' ),
			'uploads_dir' => self::clean_path( $d['uploads_dir'] ?? '' ),
			'prefix' => self::valid_prefix( (string) ( $d['prefix'] ?? '' ) ) ? (string) $d['prefix'] : '',
			'multisite' => ! empty( $d['multisite'] ),
			'subdomain' => ! empty( $d['subdomain'] ),
			'network' => array( 'domain' => strtolower( (string) ( $network['domain'] ?? '' ) ), 'path' => self::slash_path( (string) ( $network['path'] ?? '/' ) ) ),
			'networks' => $networks,
			'sites' => $sites,
			'config' => (array) ( $d['config'] ?? array() ),
			'extra_urls' => array_values( array_filter( array_map( $url, (array) ( $d['extra_urls'] ?? array() ) ) ) ),
		);
	}
	private static function untrail( $text ) { return rtrim( (string) $text, '/\\' ); }
	private static function valid_prefix( $prefix ) { return is_string( $prefix ) && (bool) preg_match( '/^[A-Za-z0-9_]{1,64}$/D', $prefix ); }
	private static function slash_path( $path ) { $path = '/' . trim( str_replace( '\\', '/', (string) $path ), '/' ); return '/' === $path ? '/' : $path . '/'; }
	private static function ends_with( $text, $end ) { return '' !== $end && strlen( $text ) > strlen( $end ) && substr( $text, -strlen( $end ) ) === $end; }
	/** Ruta absoluta normalizada sin barra final ('' si no es absoluta o es la raíz). */
	private static function clean_path( $path ) {
		$path = str_replace( '\\', '/', (string) $path );
		if ( '' === $path || '/' !== $path[0] || false !== strpos( $path, "\0" ) ) return '';
		$path = preg_replace( '#/+#', '/', $path );
		$path = rtrim( $path, '/' );
		return strlen( $path ) > 1 ? $path : '';
	}
	/** array( scheme, host[:puerto] en minúsculas, path sin barra final ) de una URL http(s), o null. */
	private static function parse_url( $url ) {
		$url = (string) $url;
		if ( ! preg_match( '~^(https?)://([A-Za-z0-9](?:[A-Za-z0-9.-]*[A-Za-z0-9])?)(:[0-9]{1,5})?(/[^?#]*)?$~iD', $url, $m ) ) return null;
		$path = rtrim( (string) ( $m[4] ?? '' ), '/' );
		if ( preg_match( '~[\s\\\\"\'<>]~', $path ) ) return null;
		return array( 'scheme' => strtolower( $m[1] ), 'host' => strtolower( $m[2] ) . ( $m[3] ?? '' ), 'path' => $path );
	}

	/* ---------------------------------------------------------------- Recorrido de texto y serializado -------------- */

	/** Prepara las expresiones y las agujas tras añadir pares. */
	private function compile() {
		$this->needles = array();
		$this->sql_needles = array();
		foreach ( array_keys( $this->hosts ) as $host ) { $this->needles[] = array( $host, true ); $this->sql_needles[] = array( $host, true ); }
		$alternatives = array();
		$this->path_map = array();
		foreach ( $this->path_pairs as $from => $to ) {
			foreach ( array( 'plain', 'json', 'json2' ) as $style ) {
				$slash = self::STYLES[ $style ]['slash'];
				$styled = str_replace( '/', $slash, $from );
				$this->path_map[ $styled ] = str_replace( '/', $slash, $to );
				$alternatives[ $styled ] = preg_quote( $styled, '#' );
				$this->needles[] = array( $styled, false );
				$this->sql_needles[] = array( str_replace( '\\', '\\\\', $styled ), false );
			}
		}
		uksort( $alternatives, function( $x, $y ) { return strlen( $y ) - strlen( $x ); } );
		$this->path_regex = $alternatives ? '#(?<![A-Za-z0-9._~-])(?:' . implode( '|', $alternatives ) . ')(?![A-Za-z0-9_~-]|\.[A-Za-z0-9])#' : null;
		// Con muchos hosts (una red grande), el primer filtro es la presencia de «//» en alguno de sus estilos.
		if ( count( $this->hosts ) > 32 ) {
			$this->needles = array_values( array_filter( $this->needles, function( $n ) { return ! $n[1]; } ) );
			$this->sql_needles = array_values( array_filter( $this->sql_needles, function( $n ) { return ! $n[1]; } ) );
			foreach ( array( '//', '\\/\\/', '%2F%2F' ) as $token ) { $this->needles[] = array( $token, true ); $this->sql_needles[] = array( str_replace( '\\', '\\\\', $token ), true ); }
		}
	}
	private function may_contain( $value ) {
		foreach ( $this->needles as $needle ) if ( false !== ( $needle[1] ? stripos( $value, $needle[0] ) : strpos( $value, $needle[0] ) ) ) return true;
		return false;
	}
	/** Lo mismo sobre el texto SQL de un literal, antes de quitarle los escapes (las barras invertidas van dobladas). */
	private function may_contain_sql( $raw ) {
		foreach ( $this->sql_needles as $needle ) if ( false !== ( $needle[1] ? stripos( $raw, $needle[0] ) : strpos( $raw, $needle[0] ) ) ) return true;
		return false;
	}
	private function rewrite_text( $s, $depth ) {
		$trimmed = trim( $s );
		if ( self::looks_serialized( $trimmed ) ) {
			if ( $depth >= self::MAX_DEPTH ) { $this->flags['deep'] = true; return $s; }
			$i = 0;
			$walked = $this->walk( $trimmed, strlen( $trimmed ), $i, $depth, 0 );
			if ( null === $walked || strlen( $trimmed ) !== $i ) {
				// Un serializado dañado se deja intacto: cambiarlo como texto rompería aún más sus longitudes.
				$this->flags['malformed'] = true;
				return $s;
			}
			if ( $walked === $trimmed ) return $s;
			$this->stats['serialized']++;
			$lead = strspn( $s, " \t\n\r\0\x0B" );
			return substr( $s, 0, $lead ) . $walked . substr( $s, $lead + strlen( $trimmed ) );
		}
		if ( ! $this->may_contain( $s ) ) return $s;
		$s = $this->scan_urls( $s, false, $this->stats['urls'] );
		if ( $this->path_regex ) {
			$map = $this->path_map;
			$count = 0;
			$s = preg_replace_callback( $this->path_regex, function( $m ) use ( $map ) { return $map[ $m[0] ] ?? $m[0]; }, $s, -1, $count );
			$this->stats['paths'] += $count;
		}
		return $s;
	}
	/** Como is_serialized() de WordPress (estricto), más `C:` (Serializable) y `E:` (enum). */
	private static function looks_serialized( $s ) {
		$n = strlen( $s );
		if ( $n < 4 ) return 'N;' === $s;
		if ( ':' !== $s[1] ) return false;
		$last = $s[ $n - 1 ];
		if ( ';' !== $last && '}' !== $last ) return false;
		switch ( $s[0] ) {
			case 's': return (bool) preg_match( '/^s:[0-9]+:"/', $s ) && '"' === $s[ $n - 2 ];
			case 'a': case 'O': case 'C': case 'E': return (bool) preg_match( '/^[aOCE]:[0-9]+:/', $s );
			case 'b': case 'i': case 'd': return (bool) preg_match( '/^[bid]:[0-9.E+-]+;$/D', $s );
		}
		return false;
	}
	/**
	 * Recorre un valor serializado desde $i y devuelve su texto con las cadenas reescritas (o null si no es válido). Las
	 * claves de arrays, los nombres de clase y de propiedades se copian igual; `C:` (formato propio de su clase), `E:`,
	 * `r:` y `R:` se copian sin tocar. Nunca instancia nada.
	 */
	private function walk( $s, $n, &$i, $depth, $nest ) {
		if ( $i >= $n ) return null;
		$start = $i;
		switch ( $s[ $i ] ) {
			case 'N':
				if ( ';' !== ( $s[ $i + 1 ] ?? '' ) ) return null;
				$i += 2;
				return 'N;';
			case 'b': case 'i': case 'd': case 'r': case 'R':
				if ( ':' !== ( $s[ $i + 1 ] ?? '' ) ) return null;
				$end = strpos( $s, ';', $i + 2 );
				if ( false === $end ) return null;
				$body = substr( $s, $i + 2, $end - $i - 2 );
				$type = $s[ $i ];
				$ok = 'b' === $type ? ( '0' === $body || '1' === $body ) : ( 'd' === $type ? (bool) preg_match( '/^(?:-?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?|-?INF|NAN)$/D', $body ) : (bool) preg_match( '/^[+-]?[0-9]+$/D', $body ) );
				if ( ! $ok ) return null;
				$i = $end + 1;
				return substr( $s, $start, $i - $start );
			case 's':
			case 'E':
				if ( ! preg_match( '/\G[sE]:([0-9]{1,10}):"/', $s, $m, 0, $i ) ) return null;
				$length = (int) $m[1];
				$from = $i + strlen( $m[0] );
				if ( $from + $length + 2 > $n || '";' !== substr( $s, $from + $length, 2 ) ) return null;
				$i = $from + $length + 2;
				if ( 'E' === $s[ $start ] ) return substr( $s, $start, $i - $start );
				$inner = substr( $s, $from, $length );
				$new = $this->rewrite_text( $inner, $depth + 1 );
				return $new === $inner ? substr( $s, $start, $i - $start ) : 's:' . strlen( $new ) . ':"' . $new . '";';
			case 'S':
				// Cadena con escapes \hh: se copia igual.
				if ( ! preg_match( '/\GS:([0-9]{1,10}):"/', $s, $m, 0, $i ) ) return null;
				$i += strlen( $m[0] );
				for ( $k = 0, $length = (int) $m[1]; $k < $length; $k++ ) {
					if ( $i >= $n ) return null;
					$i += '\\' === $s[ $i ] ? 3 : 1;
				}
				if ( '";' !== substr( $s, $i, 2 ) ) return null;
				$i += 2;
				return substr( $s, $start, $i - $start );
			case 'a':
				if ( $nest >= self::MAX_NESTING || ! preg_match( '/\Ga:([0-9]{1,10}):\{/', $s, $m, 0, $i ) ) return null;
				$i += strlen( $m[0] );
				$out = $m[0];
				$keys = array();
				$values = array();
				for ( $k = 0, $count = (int) $m[1]; $k < $count; $k++ ) {
					$key = $this->skip_key( $s, $n, $i );
					if ( null === $key ) return null;
					$value = $this->walk( $s, $n, $i, $depth, $nest + 1 );
					if ( null === $value ) return null;
					$keys[] = $key;
					$values[] = $value;
				}
				if ( '}' !== ( $s[ $i ] ?? '' ) ) return null;
				$i++;
				// Una clave que es una URL (un mapa de redirecciones, por ejemplo) también cambia, salvo que chocara con
				// otra clave del mismo array: entonces se queda como estaba.
				$present = array_flip( $keys );
				foreach ( $keys as $index => $key ) {
					if ( 's' !== $key[0] || ! $this->may_contain( $key ) ) continue;
					$inner = substr( $key, strpos( $key, ':"' ) + 2, -2 );
					$new = $this->rewrite_text( $inner, $depth + 1 );
					$text = 's:' . strlen( $new ) . ':"' . $new . '";';
					if ( $new === $inner || isset( $present[ $text ] ) ) continue;
					$present[ $text ] = $index;
					$keys[ $index ] = $text;
				}
				foreach ( $keys as $index => $key ) $out .= $key . $values[ $index ];
				return $out . '}';
			case 'O':
				if ( $nest >= self::MAX_NESTING || ! preg_match( '/\GO:([0-9]{1,10}):"/', $s, $m, 0, $i ) ) return null;
				$from = $i + strlen( $m[0] );
				$class_end = $from + (int) $m[1];
				if ( $class_end + 2 > $n || '":' !== substr( $s, $class_end, 2 ) ) return null;
				if ( ! preg_match( '/\G([0-9]{1,10}):\{/', $s, $c, 0, $class_end + 2 ) ) return null;
				$i = $class_end + 2 + strlen( $c[0] );
				$out = substr( $s, $start, $i - $start );
				for ( $k = 0, $count = (int) $c[1]; $k < $count; $k++ ) {
					$key = $this->skip_key( $s, $n, $i );
					if ( null === $key ) return null;
					$value = $this->walk( $s, $n, $i, $depth, $nest + 1 );
					if ( null === $value ) return null;
					$out .= $key . $value;
				}
				if ( '}' !== ( $s[ $i ] ?? '' ) ) return null;
				$i++;
				return $out . '}';
			case 'C':
				// Serializable: su contenido es un formato propio de la clase. Se copia sin tocarlo y se informa.
				if ( ! preg_match( '/\GC:([0-9]{1,10}):"/', $s, $m, 0, $i ) ) return null;
				$class_end = $i + strlen( $m[0] ) + (int) $m[1];
				if ( $class_end + 2 > $n || '":' !== substr( $s, $class_end, 2 ) ) return null;
				if ( ! preg_match( '/\G([0-9]{1,10}):\{/', $s, $c, 0, $class_end + 2 ) ) return null;
				$body = $class_end + 2 + strlen( $c[0] );
				if ( $body + (int) $c[1] >= $n || '}' !== $s[ $body + (int) $c[1] ] ) return null;
				$i = $body + (int) $c[1] + 1;
				$copied = substr( $s, $start, $i - $start );
				if ( $this->may_contain( $copied ) ) $this->flags['opaque'] = true;
				return $copied;
		}
		return null;
	}
	/** Una clave de array o propiedad (i: o s:), copiada igual. */
	private function skip_key( $s, $n, &$i ) {
		$start = $i;
		if ( 'i' === ( $s[ $i ] ?? '' ) ) {
			if ( ! preg_match( '/\Gi:[+-]?[0-9]+;/', $s, $m, 0, $i ) ) return null;
			$i += strlen( $m[0] );
			return $m[0];
		}
		if ( 's' !== ( $s[ $i ] ?? '' ) || ! preg_match( '/\Gs:([0-9]{1,10}):"/', $s, $m, 0, $i ) ) return null;
		$end = $i + strlen( $m[0] ) + (int) $m[1];
		if ( $end + 2 > $n || '";' !== substr( $s, $end, 2 ) ) return null;
		$i = $end + 2;
		return substr( $s, $start, $i - $start );
	}

	/**
	 * Recorre las URL de $s (`[esquema:]//host[:puerto]` en cada estilo) y cambia las del origen. Con $count_only no
	 * cambia nada y suma en $found las URL del origen que no son del destino. El host se lee completo, así que
	 * `origen.com.mx` y `miorigen.com` nunca coinciden con `origen.com`; la ruta del par debe terminar en un límite de
	 * segmento.
	 */
	private function scan_urls( $s, $count_only, &$found ) {
		if ( ! $this->hosts ) return $s;
		static $regex = null;
		if ( null === $regex ) {
			$slashes = array();
			foreach ( self::STYLES as $style ) $slashes[] = '(?i:' . preg_quote( $style['slash'] . $style['slash'], '~' ) . ')';
			$regex = '~(?:(?<scheme>(?i:https?))(?<colon>:|(?i:%3A)))?(?<slashes>' . implode( '|', $slashes ) . ')(?<host>[A-Za-z0-9](?:[A-Za-z0-9.-]*[A-Za-z0-9])?)(?<port>(?::|(?i:%3A))[0-9]{1,5}(?![0-9]))?~';
		}
		$out = '';
		$offset = 0;
		$n = strlen( $s );
		$last = 0;
		while ( $offset < $n && preg_match( $regex, $s, $m, PREG_OFFSET_CAPTURE, $offset ) ) {
			$start = $m[0][1];
			$end = $start + strlen( $m[0][0] );
			$offset = $end;
			$style = $this->style_of( $m['slashes'][0] );
			$has_scheme = isset( $m['scheme'] ) && -1 !== $m['scheme'][1] && '' !== $m['scheme'][0];
			$slash_at = $m['slashes'][1];
			// «ftp://origen.com»: otro esquema delante de «//» no es una URL nuestra.
			if ( ! $has_scheme ) {
				$colon = self::STYLES[ $style ]['colon'];
				if ( $slash_at >= strlen( $colon ) && 0 === substr_compare( $s, $colon, $slash_at - strlen( $colon ), strlen( $colon ), true ) ) continue;
			} elseif ( ( 'url' === $style ) !== ( 0 === strcasecmp( '%3A', $m['colon'][0] ) ) ) {
				continue;
			}
			$port = isset( $m['port'] ) && -1 !== $m['port'][1] ? $m['port'][0] : '';
			if ( '' !== $port && 0 !== strncasecmp( $port, self::STYLES[ $style ]['colon'], strlen( self::STYLES[ $style ]['colon'] ) ) ) continue;
			$host = strtolower( $m['host'][0] ) . ( '' === $port ? '' : ':' . substr( $port, strlen( self::STYLES[ $style ]['colon'] ) ) );
			if ( ! isset( $this->hosts[ $host ] ) ) continue;
			$slash = self::STYLES[ $style ]['slash'];
			foreach ( $this->hosts[ $host ] as $entry ) {
				$path = str_replace( '/', $slash, $entry['path'] );
				$length = strlen( $path );
				if ( $length && ( $end + $length > $n || 0 !== substr_compare( $s, $path, $end, $length, 'url' === $style ) ) ) continue;
				if ( $length && ! $this->segment_ends( $s, $end + $length, $style ) ) continue;
				if ( $count_only ) {
					// Una URL del destino con el mismo host (otra ruta) no es un resto del origen.
					$to = str_replace( '/', $slash, $entry['to_path'] );
					$same = $entry['host'] === $host && strlen( $to ) > $length && 0 === substr_compare( $s, $to, $end, strlen( $to ) ) && $this->segment_ends( $s, $end + strlen( $to ), $style );
					if ( ! $same ) $found++;
					break;
				}
				// En una URL codificada se conserva la caja de los escapes (%2F o %2f), también en el puerto.
				$colon = self::STYLES[ $style ]['colon'];
				$out_slash = $slash;
				if ( 'url' === $style && false !== strpos( $m['slashes'][0], '%2f' ) ) { $colon = strtolower( $colon ); $out_slash = strtolower( $slash ); }
				$replacement = ( $has_scheme ? $entry['scheme'] . $colon : '' ) . $out_slash . $out_slash . str_replace( ':', $colon, $entry['host'] ) . str_replace( '/', $out_slash, $entry['to_path'] );
				$out .= substr( $s, $last, $start - $last ) . $replacement;
				$last = $end + $length;
				$offset = $last;
				$found++;
				break;
			}
		}
		if ( $count_only || 0 === $last ) return $s;
		return $out . substr( $s, $last );
	}
	private function style_of( $slashes ) {
		foreach ( self::STYLES as $name => $style ) if ( 0 === strcasecmp( $slashes, $style['slash'] . $style['slash'] ) ) return $name;
		return 'plain';
	}
	/** La ruta de un par termina en un límite de segmento: fin, barra del estilo, «?», «#», comillas, espacio… */
	private function segment_ends( $s, $at, $style ) {
		if ( $at >= strlen( $s ) ) return true;
		$slash = self::STYLES[ $style ]['slash'];
		if ( 0 === substr_compare( $s, $slash, $at, strlen( $slash ), true ) ) return true;
		$c = $s[ $at ];
		if ( 'url' === $style && '%' === $c ) return true;
		// Un punto final de frase («…/blog.») es límite; «/blog.html» no.
		if ( '.' === $c ) return ! preg_match( '/[A-Za-z0-9]/', $s[ $at + 1 ] ?? '' );
		return ! preg_match( '/[A-Za-z0-9_~%+-]/', $c );
	}
}

/**
 * Lee del SQL de un respaldo, mientras el guardián lo comprueba en seco, lo que la reubicación necesita saber del origen:
 * `home` y `siteurl` de cada sitio (`{prefijo}options`, `{prefijo}N_options`), los sitios de la red (`blogs`), las redes
 * (`site`) y si es por subdominios (`sitemeta`). No cambia ninguna sentencia. Gancho de Sql_Guard::on_insert().
 */
final class Digitalisimo_Backups_Relocate_Probe {
	private $prefix;
	private $sites = array();
	private $networks = array();
	private $subdomain = null;

	public function __construct( $prefix ) { $this->prefix = (string) $prefix; }
	public function __invoke( array $insert, $sql ) {
		$table = (string) ( $insert['table'] ?? '' );
		if ( '' === $this->prefix || 0 !== strpos( $table, $this->prefix ) || ! is_array( $insert['columns'] ?? null ) ) return array();
		$rest = substr( $table, strlen( $this->prefix ) );
		if ( 'options' === $rest ) $site = 1;
		elseif ( preg_match( '/^([0-9]+)_options$/D', $rest, $m ) ) $site = (int) $m[1];
		elseif ( in_array( $rest, array( 'blogs', 'site', 'sitemeta' ), true ) ) $site = 0;
		else return array();
		foreach ( (array) $insert['rows'] as $row ) {
			$values = array();
			foreach ( $insert['columns'] as $index => $column ) {
				$literal = $row[ $index ] ?? null;
				$values[ $column ] = is_array( $literal ) && 'str' === ( $literal['type'] ?? '' ) ? Digitalisimo_Backups_Relocate::sql_unescape( substr( $sql, $literal['start'], $literal['end'] - $literal['start'] ), $literal['quote'] ) : ( is_array( $literal ) && isset( $literal['raw'] ) ? $literal['raw'] : null );
			}
			if ( $site ) {
				$name = (string) ( $values['option_name'] ?? '' );
				if ( 'home' === $name || 'siteurl' === $name ) $this->sites[ $site ][ $name ] = rtrim( (string) $values['option_value'], '/' );
			} elseif ( 'blogs' === $rest && isset( $values['blog_id'], $values['domain'], $values['path'] ) ) {
				$this->sites[ (int) $values['blog_id'] ]['domain'] = strtolower( (string) $values['domain'] );
				$this->sites[ (int) $values['blog_id'] ]['path'] = (string) $values['path'];
				$this->sites[ (int) $values['blog_id'] ]['network'] = (int) ( $values['site_id'] ?? 1 );
			} elseif ( 'site' === $rest && isset( $values['id'], $values['domain'] ) ) {
				$this->networks[ (int) $values['id'] ] = array( 'domain' => strtolower( (string) $values['domain'] ), 'path' => (string) ( $values['path'] ?? '/' ) );
			} elseif ( 'sitemeta' === $rest && 'subdomain_install' === ( $values['meta_key'] ?? '' ) ) {
				$this->subdomain = (bool) (int) ( $values['meta_value'] ?? 0 );
			}
		}
		return array();
	}
	/** array( sites => id => array( home, siteurl, domain, path, network ), networks => id => array( domain, path ), subdomain => bool|null ). */
	public function result() {
		ksort( $this->sites );
		ksort( $this->networks );
		return array( 'sites' => $this->sites, 'networks' => $this->networks, 'subdomain' => $this->subdomain );
	}
}
