<?php
/**
 * Sonda de la auditoría del escritorio (mu-plugin, la carga digi-ap-probe.php): por cada petición anota en
 * wp-content/probe/requests.jsonl tiempo, consultas (por origen), memoria pico, fases, peticiones HTTP
 * salientes, avisos de PHP, scripts y estilos impresos y la pantalla. Lo controlan cabeceras de la petición:
 *   X-Digi-Ap-Set    conjunto de plugins activo en esta petición (ver SETS); sin ella, los de la base de datos
 *   X-Digi-Ap-Id     identificador de la medida (lo pone el conductor) · X-Digi-Ap-Label  etiqueta legible
 *   X-Digi-Ap-Profile=1  envuelve cada callback de cada gancho para atribuir el tiempo propio por origen (más lento)
 *   X-Digi-Ap-Github=ok|down  el índice de la Release responde con las versiones instaladas (ok, por defecto) o falla
 *   X-Digi-Ap-Report=autoload|cron  devuelve en JSON las opciones con carga automática o los eventos de WP-Cron
 *   X-Digi-Ap-Cron=<gancho>  ejecuta ahora ese evento de WP-Cron (cada instancia programada) y mide como una petición
 * Las cookies digi_ap_set / digi_ap_profile / digi_ap_github sirven igual para Chrome (que no pone cabeceras a todo).
 * Nada sale a la red: GitHub se simula y el resto de peticiones externas se cortan con un WP_Error (y se anotan).
 */
defined( 'ABSPATH' ) || exit;

final class Digi_AP_Probe {
	/** Carpeta de la sonda: wp-content/probe (en Playground, montada; en el modo nativo, un enlace). */
	public static function dir() {
		return WP_CONTENT_DIR . '/probe';
	}
	/** Conjuntos de plugins: nombre => archivos. `antes` son las copias del código base (montadas aparte). */
	const SETS = array(
		'none'       => array(),
		'el'         => array( 'digitalisimo-elements/pro-elements.php' ),
		'elmig'      => array( 'digitalisimo-elements/pro-elements.php', 'digitalisimo-migration/digitalisimo-migration.php' ),
		'el-antes'   => array( 'digitalisimo-elements-antes/pro-elements.php' ),
		'elmig-antes'=> array( 'digitalisimo-elements-antes/pro-elements.php', 'digitalisimo-migration-antes/digitalisimo-migration.php' ),
	);
	const OURS = '#/(digitalisimo-elements(?:-antes)?|digitalisimo-migration(?:-antes)?)/#';

	public static $set = null;
	public static $marks = array();
	public static $errors = array();
	public static $notices = array();
	public static $http = array();
	public static $profile = false;
	public static $prof = array();
	public static $stack = array();
	public static $files = array();
	public static $plugin_loaded = array();
	public static $t0 = 0.0;

	public static function header( $name ) {
		$key = 'HTTP_X_DIGI_AP_' . strtoupper( str_replace( '-', '_', $name ) );
		if ( isset( $_SERVER[ $key ] ) ) {
			return (string) $_SERVER[ $key ];
		}
		$cookie = 'digi_ap_' . strtolower( str_replace( '-', '_', $name ) );
		return isset( $_COOKIE[ $cookie ] ) ? (string) $_COOKIE[ $cookie ] : '';
	}

	public static function now() {
		return function_exists( 'hrtime' ) ? hrtime( true ) / 1e6 : microtime( true ) * 1000;
	}

	public static function boot() {
		self::$t0 = self::now();
		$set      = self::header( 'set' );
		if ( '' !== $set && isset( self::SETS[ $set ] ) ) {
			self::$set = $set;
			$only      = self::SETS[ $set ];
			$all       = array();
			foreach ( self::SETS as $files ) {
				$all = array_merge( $all, $files );
			}
			add_filter( 'option_active_plugins', static function ( $plugins ) use ( $only, $all ) {
				$plugins = array_values( array_diff( (array) $plugins, $all ) );
				return is_multisite() ? $plugins : array_values( array_unique( array_merge( $plugins, $only ) ) );
			}, 1 );
			add_filter( 'site_option_active_sitewide_plugins', static function ( $plugins ) use ( $only, $all ) {
				$plugins = (array) $plugins;
				foreach ( $all as $file ) {
					unset( $plugins[ $file ] );
				}
				foreach ( $only as $file ) {
					$plugins[ $file ] = 1;
				}
				return $plugins;
			}, 1 );
		}
		self::$profile = '1' === self::header( 'profile' );
		set_error_handler( array( __CLASS__, 'on_error' ) ); // phpcs:ignore -- sonda de pruebas.
		foreach ( array( 'deprecated_function_run', 'deprecated_constructor_run', 'deprecated_file_included', 'deprecated_argument_run', 'deprecated_class_run', 'deprecated_hook_run', 'doing_it_wrong_run' ) as $hook ) {
			add_action( $hook, static function ( ...$args ) use ( $hook ) {
				$where = self::caller( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 ) ); // phpcs:ignore
				$key   = $hook . ': ' . substr( implode( ' | ', array_map( 'strval', array_filter( $args, 'is_scalar' ) ) ), 0, 300 );
				self::$notices[ $key ] = array( 'n' => ( self::$notices[ $key ]['n'] ?? 0 ) + 1, 'origin' => $where );
			}, 10, 4 );
		}
		add_filter( 'pre_http_request', array( __CLASS__, 'http_first' ), -PHP_INT_MAX, 3 );
		add_filter( 'pre_http_request', array( __CLASS__, 'http_last' ), PHP_INT_MAX, 3 );
		foreach ( array( 'plugins_loaded', 'setup_theme', 'after_setup_theme', 'init', 'wp_loaded', 'admin_menu', 'network_admin_menu', 'admin_init', 'current_screen', 'admin_enqueue_scripts', 'admin_head', 'admin_notices', 'all_admin_notices', 'admin_footer', 'admin_print_footer_scripts', 'rest_api_init', 'parse_request', 'wp_dashboard_setup', 'heartbeat_received', 'admin_bar_menu', 'elementor/init', 'elementor/editor/init' ) as $hook ) {
			add_action( $hook, static function ( $value = null ) use ( $hook ) {
				self::$marks[ $hook ]['a'] = self::$marks[ $hook ]['a'] ?? self::now() - self::$t0;
				return $value;
			}, PHP_INT_MIN );
			add_action( $hook, static function ( $value = null ) use ( $hook ) {
				self::$marks[ $hook ]['b'] = self::now() - self::$t0;
				return $value;
			}, PHP_INT_MAX );
		}
		add_action( 'plugin_loaded', array( __CLASS__, 'plugin_loaded' ), PHP_INT_MAX );
		add_action( 'network_plugin_loaded', array( __CLASS__, 'plugin_loaded' ), PHP_INT_MAX );
		if ( self::$profile ) {
			add_action( 'all', array( __CLASS__, 'wrap' ) );
		}
		add_action( 'wp_loaded', array( __CLASS__, 'actions' ), PHP_INT_MAX );
		add_filter( 'site_transient_update_plugins', array( __CLASS__, 'dummy_update' ), PHP_INT_MAX );
		register_shutdown_function( array( __CLASS__, 'shutdown' ) );
	}

	public static function plugin_loaded( $file ) {
		$prev                         = self::$plugin_loaded ? end( self::$plugin_loaded )['t'] : self::$marks['muplugins_loaded'] ?? 0;
		$t                            = self::now() - self::$t0;
		self::$plugin_loaded[]        = array( 'file' => self::rel( $file ), 't' => $t, 'ms' => $t - $prev );
	}

	public static function rel( $file ) {
		$file = str_replace( '\\', '/', (string) $file );
		$pos  = strpos( $file, '/wp-content/' );
		return false === $pos ? $file : substr( $file, $pos + 12 );
	}

	/** Origen de un archivo: núcleo de Elementor incluido, derivado de PRO, propio, Migración, sonda o WordPress. */
	public static function origin_of_file( $file ) {
		$file = str_replace( '\\', '/', (string) $file );
		// Sin «/plugins/» delante: en el modo nativo los plugins son enlaces y PHP ve la ruta real de la copia.
		if ( preg_match( '#/digitalisimo-migration(?:-antes)?/#', $file ) ) {
			return 'migracion';
		}
		if ( preg_match( '#/digitalisimo-elements(?:-antes)?/(.*)$#', $file, $m ) ) {
			$rel = $m[1];
			if ( 0 === strpos( $rel, 'elementor/' ) ) {
				return 'nucleo';
			}
			if ( preg_match( '#^(digitalisimo-[^/]*\.php|digitalisimo-ui/|modules/digitalisimo-|assets/digitalisimo/)#', $rel ) ) {
				return 'propio';
			}
			return 'pro';
		}
		if ( false !== strpos( $file, '/mu-plugins/' ) || false !== strpos( $file, '/wp-content/probe/' ) || 0 === strpos( $file, str_replace( '\\', '/', (string) realpath( __DIR__ ) ) . '/' ) ) {
			return 'sonda';
		}
		if ( false !== strpos( $file, '/wp-content/plugins/' ) ) {
			return 'otro';
		}
		return 'wp';
	}

	/** Primer archivo de un plugin en la pila (el más interno), o wp. */
	public static function caller( array $trace ) {
		foreach ( $trace as $frame ) {
			$o = self::origin_of_file( $frame['file'] ?? '' );
			if ( ! in_array( $o, array( 'wp', 'sonda' ), true ) ) {
				return $o . ' ' . self::rel( $frame['file'] ?? '' ) . ':' . ( $frame['line'] ?? 0 );
			}
		}
		return 'wp';
	}

	public static function on_error( $type, $message, $file = '', $line = 0 ) {
		if ( ! ( error_reporting() & $type ) && ! in_array( $type, array( E_DEPRECATED, E_USER_DEPRECATED, E_NOTICE, E_USER_NOTICE, E_WARNING, E_USER_WARNING ), true ) ) {
			return false;
		}
		$names = array( E_WARNING => 'warning', E_NOTICE => 'notice', E_USER_ERROR => 'user_error', E_USER_WARNING => 'user_warning', E_USER_NOTICE => 'user_notice', E_DEPRECATED => 'deprecated', E_USER_DEPRECATED => 'user_deprecated', E_RECOVERABLE_ERROR => 'recoverable' );
		$key   = ( $names[ $type ] ?? (string) $type ) . ': ' . $message . ' @ ' . self::rel( $file ) . ':' . $line;
		if ( ! isset( self::$errors[ $key ] ) ) {
			self::$errors[ $key ] = array( 'n' => 0, 'origin' => self::origin_of_file( $file ) );
		}
		++self::$errors[ $key ]['n'];
		return false;
	}

	public static function http_first( $pre, $args, $url ) {
		$trace       = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 30 ); // phpcs:ignore
		self::$http[] = array( 'url' => preg_replace( '#\?.*$#', '?…', (string) $url ), 'blocking' => ! isset( $args['blocking'] ) || $args['blocking'], 'timeout' => $args['timeout'] ?? null, 'origin' => self::caller( $trace ), 'result' => 'red' );
		return $pre;
	}

	public static function http_last( $pre, $args, $url ) {
		$i = count( self::$http ) - 1;
		if ( false !== $pre ) {
			if ( $i >= 0 ) {
				self::$http[ $i ]['result'] = 'cortada por un plugin';
			}
			return $pre;
		}
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
		if ( false !== strpos( (string) $url, 'digitalisimo-plugins.json' ) || ( 'api.github.com' === $host && false !== strpos( (string) $url, 'digitalisimo-plugin' ) ) ) {
			if ( 'down' === self::header( 'github' ) ) {
				if ( $i >= 0 ) {
					self::$http[ $i ]['result'] = 'github caído (simulado)';
				}
				return new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out (simulado por la auditoría)' );
			}
			if ( $i >= 0 ) {
				self::$http[ $i ]['result'] = 'github simulado';
			}
			return array( 'headers' => array(), 'body' => wp_json_encode( self::manifest() ), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
		}
		if ( false !== strpos( (string) $url, 'digi-ap-dummy' ) && ! empty( $args['filename'] ) ) {
			copy( self::dir() . '/digi-ap-dummy-2.zip', $args['filename'] );
			if ( $i >= 0 ) {
				self::$http[ $i ]['result'] = 'paquete de prueba (local)';
			}
			return array( 'headers' => array(), 'body' => '', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => $args['filename'] );
		}
		if ( $i >= 0 ) {
			self::$http[ $i ]['result'] = 'cortada por la sonda';
		}
		return new WP_Error( 'http_request_failed', 'Petición externa cortada por la auditoría del escritorio.' );
	}

	/** Índice como el de la Release, con la versión instalada de cada plugin Digitalisimo (no hay actualización). */
	public static function manifest() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = array();
		foreach ( get_plugins() as $data ) {
			$uri = (string) ( $data['UpdateURI'] ?? '' );
			if ( 0 === strpos( $uri, 'https://github.com/danbutanda/digitalisimo-plugin/' ) ) {
				$slug             = substr( $uri, strlen( 'https://github.com/danbutanda/digitalisimo-plugin/' ) );
				$plugins[ $slug ] = array( 'version' => $data['Version'], 'url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v1/' . $slug . '-' . $data['Version'] . '.zip' );
			}
		}
		return array( 'release_url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/tag/v1', 'plugins' => $plugins, 'themes' => array() );
	}

	/** Perfil: cada callback de cada gancho, envuelto con su reloj; el tiempo propio descuenta los ganchos anidados. */
	public static function wrap( $tag ) {
		global $wp_filter;
		if ( ! isset( $wp_filter[ $tag ] ) || ! is_object( $wp_filter[ $tag ] ) ) {
			return;
		}
		foreach ( $wp_filter[ $tag ]->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $key => $cb ) {
				if ( ! empty( $cb['digi_ap'] ) ) {
					continue;
				}
				$orig   = $cb['function'];
				$origin = self::origin_of_callable( $orig );
				if ( 'sonda' === $origin ) {
					$wp_filter[ $tag ]->callbacks[ $priority ][ $key ]['digi_ap'] = true;
					continue;
				}
				$name = $tag . ' ← ' . self::callable_name( $orig );
				$wp_filter[ $tag ]->callbacks[ $priority ][ $key ]['function'] = static function ( ...$args ) use ( $orig, $origin, $name ) {
					self::$stack[] = 0.0;
					$t             = self::now();
					try {
						return call_user_func_array( $orig, $args );
					} finally {
						$elapsed = self::now() - $t;
						$child   = array_pop( self::$stack );
						$own     = $elapsed - $child;
						if ( self::$stack ) {
							self::$stack[ count( self::$stack ) - 1 ] += $elapsed;
						}
						$p = &self::$prof[ $origin ];
						$p['ms'] = ( $p['ms'] ?? 0 ) + $own;
						$p['n']  = ( $p['n'] ?? 0 ) + 1;
						$c = &self::$prof['callbacks'][ $origin . ' ' . $name ];
						$c['ms'] = ( $c['ms'] ?? 0 ) + $own;
						$c['n']  = ( $c['n'] ?? 0 ) + 1;
						unset( $p, $c );
					}
				};
				$wp_filter[ $tag ]->callbacks[ $priority ][ $key ]['digi_ap'] = true;
			}
		}
	}

	public static function origin_of_callable( $fn ) {
		try {
			if ( is_string( $fn ) && false !== strpos( $fn, '::' ) ) {
				$fn = explode( '::', $fn, 2 );
			}
			if ( is_array( $fn ) ) {
				$r = new ReflectionMethod( is_object( $fn[0] ) ? get_class( $fn[0] ) : $fn[0], $fn[1] );
			} elseif ( $fn instanceof Closure || is_string( $fn ) ) {
				$r = new ReflectionFunction( $fn );
			} elseif ( is_object( $fn ) ) {
				$r = new ReflectionMethod( get_class( $fn ), '__invoke' );
			} else {
				return 'wp';
			}
			$file = (string) $r->getFileName();
			return '' === $file ? 'wp' : self::origin_of_file( $file );
		} catch ( Throwable $e ) {
			return 'wp';
		}
	}

	public static function callable_name( $fn ) {
		if ( is_string( $fn ) ) {
			return $fn;
		}
		if ( is_array( $fn ) ) {
			return ( is_object( $fn[0] ) ? get_class( $fn[0] ) . '->' : $fn[0] . '::' ) . $fn[1];
		}
		if ( $fn instanceof Closure ) {
			try {
				$r = new ReflectionFunction( $fn );
				return 'closure ' . self::rel( (string) $r->getFileName() ) . ':' . $r->getStartLine();
			} catch ( Throwable $e ) {
				return 'closure';
			}
		}
		return is_object( $fn ) ? get_class( $fn ) : '?';
	}

	/** El plugin de prueba en 1.0 tiene la 2.0 disponible (el ZIP lo sirve http_last desde el disco). */
	public static function dummy_update( $value ) {
		$file = 'digi-ap-dummy/digi-ap-dummy.php';
		if ( ! is_object( $value ) || ! is_file( WP_PLUGIN_DIR . '/' . $file ) || false === strpos( (string) file_get_contents( WP_PLUGIN_DIR . '/' . $file ), 'Version: 1.0' ) ) {
			return $value;
		}
		$value->response          = isset( $value->response ) && is_array( $value->response ) ? $value->response : array();
		$value->response[ $file ] = (object) array( 'id' => 'digi-ap-dummy', 'slug' => 'digi-ap-dummy', 'plugin' => $file, 'new_version' => '2.0', 'url' => '', 'package' => 'https://ejemplo.test/digi-ap-dummy-2.zip', 'tested' => '', 'requires_php' => '7.4', 'icons' => array(), 'banners' => array(), 'banners_rtl' => array(), 'compatibility' => new stdClass() );
		return $value;
	}

	/** Informes y eventos de cron a demanda (sólo administradores conectados o desde runPHP). */
	public static function actions() {
		$report = self::header( 'report' );
		$cron   = self::header( 'cron' );
		if ( '' === $report && '' === $cron ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( 'autoload' === $report ) {
			wp_send_json( self::autoload() );
		}
		if ( 'cron' === $report ) {
			wp_send_json( self::cron() );
		}
		if ( 'clear-github' === $report ) {
			global $wpdb;
			// Como en un sitio donde WordPress ya consultó sus actualizaciones (`checked` con cada plugin): así los
			// filtros de lectura de los actualizadores trabajan igual que en producción.
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$updates               = new stdClass();
			$updates->last_checked = time();
			$updates->checked      = array();
			$updates->response     = array();
			$updates->no_update    = array();
			$updates->translations = array();
			foreach ( get_plugins() as $file => $data ) {
				$updates->checked[ $file ] = $data['Version'];
			}
			set_site_transient( 'update_plugins', $updates );
			$names = is_multisite()
				? $wpdb->get_col( "SELECT meta_key FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_site_transient_digitalisimo%'" ) // phpcs:ignore
				: $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_digitalisimo%'" ); // phpcs:ignore
			foreach ( (array) $names as $name ) {
				delete_site_transient( substr( $name, strlen( '_site_transient_' ) ) );
			}
			wp_send_json( array( 'cleared' => $names ) );
		}
		if ( 'reset-dummy' === $report ) {
			wp_send_json( array( 'ok' => (bool) copy( self::dir() . '/digi-ap-dummy-1.php', WP_PLUGIN_DIR . '/digi-ap-dummy/digi-ap-dummy.php' ) ) );
		}
		if ( '' !== $cron ) {
			$ran = 0;
			foreach ( (array) _get_cron_array() as $time => $hooks ) {
				foreach ( (array) $hooks as $hook => $events ) {
					if ( $hook !== $cron ) {
						continue;
					}
					foreach ( $events as $event ) {
						do_action_ref_array( $hook, (array) $event['args'] );
						++$ran;
					}
				}
			}
			self::$marks['cron'] = array( 'ran' => $ran );
			wp_send_json( array( 'hook' => $cron, 'ran' => $ran ) );
		}
	}

	public static function autoload() {
		global $wpdb;
		$values = function_exists( 'wp_autoload_values_to_autoload' ) ? wp_autoload_values_to_autoload() : array( 'yes' );
		$in     = "'" . implode( "','", array_map( 'esc_sql', $values ) ) . "'";
		$rows   = $wpdb->get_results( "SELECT option_name, LENGTH(option_value) AS size, autoload FROM {$wpdb->options} WHERE autoload IN ($in) ORDER BY option_name", ARRAY_A ); // phpcs:ignore
		$out    = array();
		foreach ( (array) $rows as $row ) {
			$out[ $row['option_name'] ] = (int) $row['size'];
		}
		$site = array();
		if ( is_multisite() ) {
			foreach ( (array) $wpdb->get_results( "SELECT meta_key, LENGTH(meta_value) AS size FROM {$wpdb->sitemeta} WHERE site_id = " . (int) get_current_network_id(), ARRAY_A ) as $row ) { // phpcs:ignore
				$site[ $row['meta_key'] ] = (int) $row['size'];
			}
		}
		return array( 'blog' => get_current_blog_id(), 'options' => $out, 'sitemeta' => $site );
	}

	public static function cron() {
		$out = array();
		foreach ( (array) _get_cron_array() as $time => $hooks ) {
			foreach ( (array) $hooks as $hook => $events ) {
				foreach ( $events as $event ) {
					$out[] = array( 'hook' => $hook, 'next' => (int) $time - time(), 'schedule' => $event['schedule'] ?: 'única', 'interval' => $event['interval'] ?? null );
				}
			}
		}
		return array( 'blog' => get_current_blog_id(), 'events' => $out );
	}

	public static function shutdown() {
		$id = self::header( 'id' );
		if ( '' === $id ) {
			return;
		}
		global $wpdb;
		$total   = self::now() - self::$t0;
		$queries = array();
		$sql     = array();
		if ( isset( $wpdb ) && is_array( $wpdb->queries ) ) {
			foreach ( $wpdb->queries as $q ) {
				$stack  = (string) ( $q[2] ?? '' );
				$origin = self::origin_of_stack( $stack );
				$queries[ $origin ]['n']  = ( $queries[ $origin ]['n'] ?? 0 ) + 1;
				$queries[ $origin ]['ms'] = ( $queries[ $origin ]['ms'] ?? 0 ) + 1000 * (float) ( $q[1] ?? 0 );
				if ( ! in_array( $origin, array( 'wp', 'sonda' ), true ) ) {
					// Las lecturas de una opción o de una meta de red conservan su nombre (es lo que se quiere ver).
					$norm = preg_match( "/^\\s*SELECT [^']*FROM \\S+(options|sitemeta) WHERE (option_name|meta_key) = '[^']*'( AND site_id = \\d+)? LIMIT \\d+\\s*$/", (string) $q[0] ) ? preg_replace( '/\\s+/', ' ', (string) $q[0] ) : preg_replace( array( "/'[^']*'/", '/\\b\\d+\\b/' ), array( "'?'", 'N' ), (string) $q[0] );
					$k    = $origin . ' | ' . substr( (string) $norm, 0, 260 );
					$sql[ $k ]['n']    = ( $sql[ $k ]['n'] ?? 0 ) + 1;
					$sql[ $k ]['ms']   = ( $sql[ $k ]['ms'] ?? 0 ) + 1000 * (float) ( $q[1] ?? 0 );
					$sql[ $k ]['from'] = $sql[ $k ]['from'] ?? substr( self::last_frames( $stack ), 0, 300 );
				}
			}
		}
		$assets = array();
		if ( function_exists( 'wp_scripts' ) && did_action( 'wp_print_scripts' ) + did_action( 'admin_print_scripts' ) + did_action( 'admin_print_footer_scripts' ) ) {
			foreach ( array( 'js' => wp_scripts(), 'css' => wp_styles() ) as $kind => $deps ) {
				foreach ( (array) $deps->done as $handle ) {
					$item = $deps->registered[ $handle ] ?? null;
					$src  = $item && is_string( $item->src ) ? $item->src : '';
					$own  = preg_match( self::OURS, $src ) ? self::origin_of_file( '/wp-content/plugins/' . preg_replace( '#^.*?/wp-content/plugins/#', '', $src ) ) : '';
					$inl  = 0;
					foreach ( array( 'before', 'after' ) as $pos ) {
						$data = $item ? $deps->get_data( $handle, $pos ) : '';
						$inl += is_array( $data ) ? strlen( implode( '', array_filter( $data, 'is_string' ) ) ) : 0;
					}
					$extra = $item && 'js' === $kind ? strlen( (string) $deps->get_data( $handle, 'data' ) ) : 0;
					if ( '' !== $own ) {
						$file  = WP_CONTENT_DIR . '/plugins/' . preg_replace( '#^.*?/wp-content/plugins/#', '', preg_replace( '#\?.*$#', '', $src ) );
						$assets[] = array( 'kind' => $kind, 'handle' => $handle, 'origin' => $own, 'bytes' => is_file( $file ) ? filesize( $file ) : null, 'inline' => $inl + $extra );
					} elseif ( $inl + $extra > 0 ) {
						$assets[] = array( 'kind' => $kind, 'handle' => $handle, 'origin' => 'inline', 'bytes' => 0, 'inline' => $inl + $extra );
					}
				}
			}
		}
		$screen = function_exists( 'get_current_screen' ) && get_current_screen() ? get_current_screen()->id : '';
		$line   = array(
			'id'        => $id,
			'label'     => self::header( 'label' ),
			'set'       => self::$set,
			'uri'       => (string) ( $_SERVER['REQUEST_URI'] ?? '' ),
			'method'    => (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ),
			'status'    => http_response_code(),
			'screen'    => $screen,
			'ms'        => round( $total, 2 ),
			'wp_ms'     => function_exists( 'timer_float' ) ? round( 1000 * timer_float(), 2 ) : null,
			'queries'   => isset( $wpdb ) ? (int) $wpdb->num_queries : 0,
			'q_origin'  => $queries,
			'sql'       => $sql,
			'mem_peak'  => memory_get_peak_usage(),
			'mem_real'  => memory_get_peak_usage( true ),
			'marks'     => self::$marks,
			'plugins'   => self::$plugin_loaded,
			'files'     => count( get_included_files() ),
			'files_by'  => self::files_by_origin(),
			'errors'    => self::$errors,
			'notices'   => self::$notices,
			'http'      => self::$http,
			'assets'    => $assets,
			'prof'      => self::$profile ? self::top_profile() : null,
			'opcache'   => function_exists( 'opcache_get_status' ) && is_array( @opcache_get_status( false ) ) ? (bool) ( @opcache_get_status( false )['opcache_enabled'] ?? false ) : false, // phpcs:ignore
			'php'       => PHP_VERSION,
			'fatal'     => error_get_last() && in_array( error_get_last()['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ? error_get_last()['message'] . ' @ ' . self::rel( error_get_last()['file'] ) . ':' . error_get_last()['line'] : null,
		);
		file_put_contents( self::dir() . '/requests.jsonl', wp_json_encode( $line ) . "\n", FILE_APPEND | LOCK_EX );
	}

	public static function files_by_origin() {
		$out = array();
		foreach ( get_included_files() as $file ) {
			$o         = self::origin_of_file( $file );
			$out[ $o ] = ( $out[ $o ] ?? 0 ) + 1;
		}
		return $out;
	}

	public static function top_profile() {
		$callbacks = self::$prof['callbacks'] ?? array();
		uasort( $callbacks, static function ( $a, $b ) {
			return $b['ms'] <=> $a['ms'];
		} );
		$out = self::$prof;
		unset( $out['callbacks'] );
		foreach ( $out as $k => $v ) {
			$out[ $k ]['ms'] = round( $v['ms'], 2 );
		}
		$top = array();
		foreach ( array_slice( $callbacks, 0, 60, true ) as $k => $v ) {
			$top[ $k ] = array( 'ms' => round( $v['ms'], 2 ), 'n' => $v['n'] );
		}
		return array( 'by_origin' => $out, 'top' => $top );
	}

	/** Origen de una consulta por su pila de llamadas (nombres de funciones): el primer marco de un plugin. */
	public static function origin_of_stack( $stack ) {
		foreach ( array_map( 'trim', explode( ',', $stack ) ) as $frame ) {
			if ( false !== strpos( $frame, 'Digi_AP_Probe' ) ) {
				return 'sonda';
			}
			if ( preg_match( '#Digitalisimo\\\\(Elements\\\\Legacy|Migration)\\\\#', $frame ) ) {
				return 'migracion';
			}
			if ( preg_match( '#^(Digitalisimo\\\\Elements\\\\|Digitalisimo_Elements_|digitalisimo_elements_|Digitalisimo_UI|digitalisimo_ui)#', $frame ) ) {
				return 'propio';
			}
			if ( preg_match( '#^(ElementorPro\\\\|pro_elements_)#', $frame ) ) {
				return 'pro';
			}
			if ( preg_match( '#^Elementor\\\\#', $frame ) ) {
				return 'nucleo';
			}
		}
		return 'wp';
	}

	public static function last_frames( $stack ) {
		$frames = array_map( 'trim', explode( ',', $stack ) );
		$keep   = array();
		foreach ( $frames as $frame ) {
			if ( preg_match( '#(Digitalisimo|Elementor|digitalisimo|pro_elements)#', $frame ) ) {
				$keep[] = $frame;
			}
		}
		return implode( ' > ', array_slice( $keep, -4 ) );
	}
}
Digi_AP_Probe::boot();
add_action( 'muplugins_loaded', static function () {
	Digi_AP_Probe::$marks['muplugins_loaded'] = Digi_AP_Probe::now() - Digi_AP_Probe::$t0;
}, PHP_INT_MAX );
