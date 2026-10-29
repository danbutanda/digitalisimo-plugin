<?php
/**
 * F-front, frente B (y arranque G0): transición del valor deseado al aplicado (§3.2, §4, §6.1).
 *
 * Sin WordPress ni el constructor: stubs en memoria (opciones por sitio con su caché de inexistentes, `sitemeta`,
 * filtros, eventos y una tabla de opciones para el cerrojo). Las clases de los otros frentes (`Core_Defaults`,
 * `Pro_Gate`, `Lazy_Libs`, `Icon_Scan`) son dobles con su contrato. Comprueba:
 * - arranque: `pro-elements.php` carga el módulo una vez, después de las condiciones, el tema y los kits; la
 *   autocarga sólo resuelve clases del módulo; `Module::init()` inicia las clases en orden y la pantalla sólo en la
 *   administración;
 * - efectivo según el contexto: núcleo incluido (D3), fila del ajuste del núcleo ausente (también con un
 *   `pre_option_` delante), estado guardado del experimento y veredicto del análisis para lo heredado;
 * - evento único por sitio en `init`; ejecución en `admin_init` si sigue pendiente (no por AJAX);
 * - cerrojo `INSERT IGNORE` con token, robo a los 300 s y nunca soltar el de otra petición;
 * - una sola `clear_cache()` por transición con cambios y ninguna sin ellos; `Google_Font::clear_cache()` sólo si
 *   cambia `swap` y las fuentes son locales; el aplicado se escribe al final, y nada si no se pudo vaciar;
 * - el análisis heredado de la red: lotes en el evento, veredicto copiado al aplicado y sin reanálisis solo;
 * - en una red, cada sitio transita en sus peticiones y sólo vacía lo suyo;
 * - ninguna escritura de claves `elementor_` en el módulo (estática y en ejecución).
 */

namespace Elementor {
	class Plugin {
		public static $instance;
		public $files_manager;
	}
}

namespace Elementor\Core\Files\Fonts {
	class Google_Font {
		public static function clear_cache() {
			$GLOBALS['tr']['log'][] = 'fonts:' . $GLOBALS['tr']['blog'];
		}
	}
}

namespace Digitalisimo\Elements\Front {
	/** Dobles de los otros frentes: registran sus llamadas. */
	final class Core_Defaults {
		public static function init(): void {
			$GLOBALS['tr']['boot'][] = 'Core_Defaults';
		}
	}
	final class Pro_Gate {
		public static function init(): void {
			$GLOBALS['tr']['boot'][] = 'Pro_Gate';
		}
		public static function refresh_snippets(): void {
			$GLOBALS['tr']['log'][] = 'snippets:' . $GLOBALS['tr']['blog'];
		}
	}
	final class Lazy_Libs {
		public static function init(): void {
			$GLOBALS['tr']['boot'][] = 'Lazy_Libs';
		}
	}
	final class Icon_Scan {
		/** Evento propio que continúa el análisis (como el del frente C). */
		const HOOK = 'digitalisimo_elements_icon_scan';
		public static $verdicts = array();
		public static $batches  = 0;
		public static $throw    = false;
		public static function state(): array {
			return array();
		}
		public static function run_batch( int $limit = 200 ): array {
			if ( self::$throw ) {
				throw new \RuntimeException( 'fallo del análisis' );
			}
			++self::$batches;
			$GLOBALS['tr']['log'][] = 'batch:' . $limit;
			return array();
		}
		public static function verdict(): string {
			return self::$verdicts ? array_shift( self::$verdicts ) : 'pending';
		}
	}
}

namespace {
	define( 'ABSPATH', '/srv/sitio/' );

	final class Digitalisimo_Elements_Core {
		public static function bundled() {
			return ! empty( $GLOBALS['tr']['bundled'] );
		}
	}

	final class Tr_Files {
		public function clear_cache() {
			$blog = $GLOBALS['tr']['blog'];
			// El aplicado todavía no cambió cuando se vacía: se escribe al final.
			$GLOBALS['tr']['log'][] = 'files:' . $blog . ':' . md5( serialize( $GLOBALS['tr']['options'][ $blog ][ \Digitalisimo\Elements\Front\Transition::APPLIED ] ?? null ) );
			if ( is_callable( $GLOBALS['tr']['during_clear'] ) ) {
				call_user_func( $GLOBALS['tr']['during_clear'] );
			}
		}
	}

	/** Tabla de opciones en memoria para el cerrojo y la lectura de filas. */
	final class Tr_Wpdb {
		public $options = 'wp_options';
		public $log     = array();
		public function prepare( $sql, ...$args ) {
			return array( 'sql' => $sql, 'args' => $args );
		}
		public function esc_like( $text ) {
			return addcslashes( (string) $text, '_%\\' );
		}
		private function &table() {
			return $GLOBALS['tr']['options'][ $GLOBALS['tr']['blog'] ];
		}
		public function query( $query ) {
			$table       = &$this->table();
			$sql         = $query['sql'];
			$args        = $query['args'];
			$this->log[] = strtok( $sql, ' ' ) . ' ' . ( 0 === strpos( $sql, 'UPDATE' ) ? $args[1] : $args[0] );
			if ( 0 === strpos( $sql, 'INSERT IGNORE INTO' ) ) {
				if ( array_key_exists( $args[0], $table ) ) {
					return 0;
				}
				$table[ $args[0] ] = $args[1];
				return 1;
			}
			if ( 0 === strpos( $sql, 'UPDATE' ) ) {
				if ( isset( $table[ $args[1] ] ) && $table[ $args[1] ] === $args[2] ) {
					$table[ $args[1] ] = $args[0];
					return 1;
				}
				return 0;
			}
			if ( 0 === strpos( $sql, 'DELETE' ) ) {
				$suffix = stripcslashes( substr( $args[1], 1 ) );
				if ( isset( $table[ $args[0] ] ) && substr( (string) $table[ $args[0] ], -strlen( $suffix ) ) === $suffix ) {
					unset( $table[ $args[0] ] );
					return 1;
				}
				return 0;
			}
			throw new RuntimeException( 'Consulta no prevista: ' . $sql );
		}
		public function get_var( $query ) {
			$table       = $this->table();
			$this->log[] = 'SELECT ' . $query['args'][0];
			if ( 0 === strpos( $query['sql'], 'SELECT option_value' ) ) {
				return array_key_exists( $query['args'][0], $table ) ? (string) $table[ $query['args'][0] ] : null;
			}
			if ( 0 === strpos( $query['sql'], 'SELECT option_id' ) ) {
				return array_key_exists( $query['args'][0], $table ) ? '1' : null;
			}
			throw new RuntimeException( 'Consulta no prevista: ' . $query['sql'] );
		}
	}
	$GLOBALS['wpdb'] = new Tr_Wpdb();

	function tr_reset( array $over = array() ) {
		$GLOBALS['tr'] = array_merge(
			array(
				'multisite'    => true,
				'admin'        => false,
				'ajax'         => false,
				'installing'   => false,
				'bundled'      => true,
				'blog'         => 1,
				'options'      => array( 1 => array(), 2 => array(), 3 => array() ),
				'site'         => array(),
				'notopts'      => array(),
				'filters'      => array(),
				'actions'      => array(),
				'events'       => array(),
				'writes'       => array(),
				'log'          => array(),
				'boot'         => array(),
				'during_clear' => null,
			),
			$over
		);
		$GLOBALS['wpdb']->log                       = array();
		\Elementor\Plugin::$instance                = new \Elementor\Plugin();
		\Elementor\Plugin::$instance->files_manager = new Tr_Files();
		\Digitalisimo\Elements\Front\Icon_Scan::$verdicts = array();
		\Digitalisimo\Elements\Front\Icon_Scan::$batches  = 0;
		\Digitalisimo\Elements\Front\Icon_Scan::$throw    = false;
		\Digitalisimo\Elements\Front\Transition::forget();
	}
	function tr_blog( $id ) {
		$GLOBALS['tr']['blog'] = $id;
		\Digitalisimo\Elements\Front\Transition::forget();
	}
	/** Entradas del registro que empiezan por $prefix. */
	function tr_log( $prefix ) {
		return array_values(
			array_filter(
				$GLOBALS['tr']['log'],
				function ( $line ) use ( $prefix ) {
					return 0 === strpos( $line, $prefix );
				}
			)
		);
	}

	// Stubs de WordPress.
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['tr']['actions'][] = array( $hook, $callback, $priority );
	}
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['tr']['filters'][ $hook ][] = $callback;
	}
	function apply_filters( $hook, $value, ...$args ) {
		foreach ( $GLOBALS['tr']['filters'][ $hook ] ?? array() as $callback ) {
			$value = $callback( $value, ...$args );
		}
		return $value;
	}
	function is_admin() {
		return $GLOBALS['tr']['admin'];
	}
	function is_multisite() {
		return $GLOBALS['tr']['multisite'];
	}
	function is_network_admin() {
		return false;
	}
	function wp_installing() {
		return $GLOBALS['tr']['installing'];
	}
	function wp_doing_ajax() {
		return $GLOBALS['tr']['ajax'];
	}
	function get_current_blog_id() {
		return $GLOBALS['tr']['blog'];
	}
	function get_option( $name, $default = false ) {
		$pre = apply_filters( "pre_option_{$name}", false, $name, $default );
		if ( false !== $pre ) {
			return $pre;
		}
		$options = $GLOBALS['tr']['options'][ $GLOBALS['tr']['blog'] ];
		if ( array_key_exists( $name, $options ) ) {
			return $options[ $name ];
		}
		$GLOBALS['tr']['notopts'][ $GLOBALS['tr']['blog'] ][ $name ] = true;
		return apply_filters( "default_option_{$name}", $default, $name, func_num_args() > 1 );
	}
	function update_option( $name, $value, $autoload = null ) {
		$GLOBALS['tr']['writes'][] = 'update_option:' . $name;
		$GLOBALS['tr']['log'][]    = 'write:' . $GLOBALS['tr']['blog'] . ':' . $name;
		$GLOBALS['tr']['options'][ $GLOBALS['tr']['blog'] ][ $name ] = $value;
		unset( $GLOBALS['tr']['notopts'][ $GLOBALS['tr']['blog'] ][ $name ] );
		return true;
	}
	function add_option( $name, $value = '', $deprecated = '', $autoload = null ) {
		$GLOBALS['tr']['writes'][] = 'add_option:' . $name;
		return true;
	}
	function delete_option( $name ) {
		$GLOBALS['tr']['writes'][] = 'delete_option:' . $name;
		return true;
	}
	function get_site_option( $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['tr']['site'] ) ? $GLOBALS['tr']['site'][ $name ] : $default;
	}
	function update_site_option( $name, $value ) {
		$GLOBALS['tr']['writes'][] = 'update_site_option:' . $name;
		$GLOBALS['tr']['site'][ $name ] = $value;
		return true;
	}
	function wp_load_alloptions() {
		return $GLOBALS['tr']['options'][ $GLOBALS['tr']['blog'] ];
	}
	function wp_cache_get( $key, $group = '' ) {
		if ( 'notoptions' === $key ) {
			return $GLOBALS['tr']['notopts'][ $GLOBALS['tr']['blog'] ] ?? false;
		}
		return false;
	}
	function wp_cache_delete( $key, $group = '' ) {
		return true;
	}
	function wp_next_scheduled( $hook ) {
		foreach ( $GLOBALS['tr']['events'] as $event ) {
			if ( $event[0] === $GLOBALS['tr']['blog'] && $event[1] === $hook ) {
				return $event[2];
			}
		}
		return false;
	}
	function wp_schedule_single_event( $time, $hook ) {
		$GLOBALS['tr']['events'][] = array( $GLOBALS['tr']['blog'], $hook, $time );
		return true;
	}
	function wp_generate_password( $length = 12, $special = true ) {
		static $n = 0;
		return substr( md5( 'token' . ( ++$n ) ), 0, $length );
	}
	function switch_to_blog( $id ) {
		$GLOBALS['tr']['blog'] = (int) $id;
	}
	function restore_current_blog() {
	}

	$check = function ( $ok, $message ) {
		if ( ! $ok ) {
			throw new RuntimeException( $message );
		}
	};

	$elements = __DIR__ . '/../digitalisimo-elements';
	$front    = realpath( $elements . '/modules/digitalisimo-front' );
	require $front . '/class-module.php';
	\Digitalisimo\Elements\Front\Module::register_autoloader();

	use Digitalisimo\Elements\Front\Icon_Scan;
	use Digitalisimo\Elements\Front\Module;
	use Digitalisimo\Elements\Front\Settings;
	use Digitalisimo\Elements\Front\Transition;

	$site    = 'digitalisimo_elements_front';
	$network = 'digitalisimo_elements_front_defaults';

	// 0. Arranque (G0).
	$loader = file_get_contents( $elements . '/pro-elements.php' );
	$start  = strpos( $loader, 'function pro_elements_load_plugin_func' );
	$body   = substr( $loader, $start, strpos( $loader, "\n}\n", $start ) - $start );
	$req    = strpos( $body, "require_once __DIR__ . '/modules/digitalisimo-front/class-module.php';" );
	$init   = strpos( $body, '\\Digitalisimo\\Elements\\Front\\Module::init();' );
	$after  = array( '\\Digitalisimo\\Elements\\Display_Conditions::init();', '\\Digitalisimo\\Elements\\Theme_Compat::init();', '\\Digitalisimo\\Elements\\Import\\Module::init();' );
	$check( false !== $req && false !== $init && $req < $init, 'pro-elements.php carga el módulo de rendimiento dentro de pro_elements_load_plugin_func() y luego llama Module::init().' );
	foreach ( $after as $previous ) {
		$check( false !== strpos( $body, $previous ) && strpos( $body, $previous ) < $req, "El módulo de rendimiento se carga después de {$previous}" );
	}
	$check( 1 === substr_count( $loader, 'modules/digitalisimo-front/class-module.php' ) && 1 === substr_count( $loader, 'Front\\Module::init()' ), 'El módulo se carga y se inicia una sola vez.' );
	$check( $front . '/class-settings-page.php' === Module::file_for( 'Digitalisimo\\Elements\\Front\\Settings_Page' ) && $front . '/class-transition.php' === Module::file_for( '\\Digitalisimo\\Elements\\Front\\Transition' ), 'La autocarga resuelve Settings_Page y Transition a sus archivos.' );
	foreach ( array( 'Digitalisimo\\Elements\\Front\\No_Existe', 'Digitalisimo\\Elements\\Import\\Module', 'Digitalisimo\\Elements\\Front\\..\\x', 'Digitalisimo\\Elements\\Front\\Sub\\Clase', 'Otra\\Settings' ) as $class ) {
		$check( '' === Module::file_for( $class ), "La autocarga ignora {$class} sin tocar el disco." );
	}
	tr_reset();
	Module::init();
	Module::init();
	$check( array( 'Core_Defaults', 'Pro_Gate', 'Lazy_Libs' ) === $GLOBALS['tr']['boot'], 'Module::init() inicia, una vez y en orden, las clases de los frentes que existen.' );
	$hooks = array_column( $GLOBALS['tr']['actions'], 0 );
	$check( in_array( Transition::HOOK, $hooks, true ) && in_array( 'init', $hooks, true ) && in_array( 'admin_init', $hooks, true ), 'Transition::init() engancha el evento, init y admin_init.' );
	$check( ! class_exists( 'Digitalisimo\\Elements\\Front\\Settings_Page', false ) && array() === preg_grep( '/^admin_post_/', $hooks ), 'Fuera de la administración la pantalla ni se carga.' );
	$check( 'digitalisimo_elements_front_transition' === Transition::HOOK && 'digitalisimo_elements_front_applied' === Transition::APPLIED && 'digitalisimo_elements_front_lock' === Transition::LOCK, 'Nombres del evento, del aplicado y del cerrojo (§4).' );

	// 1. Aplicado: [] mientras nada se aplicó; seis booleanos cuando sí.
	tr_reset();
	$check( array() === Transition::applied(), 'Sin aplicado, applied() es [] y rige lo de hoy.' );
	foreach ( array( 'basura', array( 'pro_conditional' => true ), array( 'hash' => 5 ) ) as $bad ) {
		$GLOBALS['tr']['options'][1][ Transition::APPLIED ] = $bad;
		$check( array() === Transition::applied(), 'Un aplicado dañado es [].' );
	}
	$GLOBALS['tr']['options'][1][ Transition::APPLIED ] = array( 'hash' => 'v1:100000', 'pro_conditional' => 1, 'font_swap' => '', 'icons' => 'raro', 'time' => '7', 'scanning' => 1 );
	$applied = Transition::applied();
	$check( true === $applied['pro_conditional'] && false === $applied['font_swap'] && false === $applied['svg_icons'] && '' === $applied['icons'] && 7 === $applied['time'] && true === $applied['scanning'], 'applied() normaliza: booleanos, veredicto conocido o vacío, análisis en curso y fecha entera.' );
	$GLOBALS['tr']['options'][1][ Transition::APPLIED ] = array( 'hash' => 'x', 'icons' => 'pending' );
	$check( '' === Transition::applied()['icons'] && false === Transition::applied()['scanning'], 'applied(): «pending» nunca es un veredicto final.' );

	// 2. Efectivo según el contexto.
	tr_reset();
	$check( array( 'pro_conditional' => true, 'font_swap' => true, 'svg_icons' => false, 'lazy_libs' => false, 'lazy_videos' => false, 'element_cache_off' => false, 'image_loading_off' => false ) === Transition::effective(), 'Efectivo con el núcleo incluido y sin filas del núcleo: los neutros.' );
	$GLOBALS['tr']['site'][ $network ] = array( 'element_cache_off' => true, 'image_loading_off' => true, 'lazy_libs' => true, 'svg_icons' => true );
	$effective = Transition::effective();
	$check( true === $effective['element_cache_off'] && true === $effective['image_loading_off'] && true === $effective['lazy_libs'] && false === $effective['svg_icons'], 'Lo heredado de la red rige; los iconos heredados esperan al análisis.' );
	tr_reset( array( 'bundled' => false ) );
	$GLOBALS['tr']['site'][ $network ] = array( 'element_cache_off' => true, 'image_loading_off' => true, 'lazy_libs' => true, 'svg_icons' => true );
	update_option( $site, Settings::sanitize( array( 'svg_icons' => '1', 'own' => array( 'svg_icons' ) ) ) );
	$effective = Transition::effective();
	$check( true === $effective['pro_conditional'] && true === $effective['lazy_libs'] && false === $effective['font_swap'] && false === $effective['svg_icons'] && false === $effective['element_cache_off'] && false === $effective['image_loading_off'], 'Con el constructor original activo sólo quedan la puerta y el cargador (D3).' );
	tr_reset();
	$GLOBALS['tr']['site'][ $network ] = array( 'element_cache_off' => true, 'image_loading_off' => true );
	$GLOBALS['tr']['options'][1]       = array( 'elementor_font_display' => 'auto', 'elementor_element_cache_ttl' => '12', 'elementor_optimized_image_loading' => '1' );
	$effective = Transition::effective();
	$check( false === $effective['font_swap'] && false === $effective['element_cache_off'] && false === $effective['image_loading_off'] && true === $effective['pro_conditional'], 'Con la fila del ajuste del núcleo guardada manda el sitio: ningún valor por defecto.' );
	tr_reset();
	add_filter( 'pre_option_elementor_font_display', function () {
		return 'swap';
	} );
	add_filter( 'default_option_elementor_font_display', function () {
		return 'swap';
	} );
	$check( true === Transition::effective()['font_swap'], 'Un pre_option_ (DIGITALÍSIMO SEO) y el propio default_option_ no confunden la fila ausente.' );
	$check( in_array( 'SELECT elementor_font_display', $GLOBALS['wpdb']->log, true ), 'Con un pre_option_ delante, la existencia de la fila se pregunta a la tabla (sólo lectura).' );
	tr_blog( 2 );
	$GLOBALS['tr']['options'][2]['elementor_font_display'] = '';
	$check( false === Transition::effective()['font_swap'], 'Una fila vacía también es elección del sitio.' );
	tr_reset();
	update_option( $site, Settings::sanitize( array( 'svg_icons' => '1', 'own' => array( 'svg_icons' ) ) ) );
	foreach ( array( 'default' => true, '' => true, 'active' => false, 'inactive' => false ) as $state => $expected ) {
		$GLOBALS['tr']['options'][1]['elementor_experiment-e_font_icon_svg'] = $state;
		Transition::forget();
		$check( $expected === Transition::effective()['svg_icons'], "Iconos elegidos por el sitio con el experimento guardado «{$state}»." );
	}
	unset( $GLOBALS['tr']['options'][1]['elementor_experiment-e_font_icon_svg'] );
	$check( true === Transition::effective()['svg_icons'], 'Sin estado guardado del experimento, «Aplicar» rige.' );
	tr_reset();
	$GLOBALS['tr']['site'][ $network ] = array( 'svg_icons' => true );
	foreach ( array( '' => false, 'pending' => false, 'findings' => false, 'n/a' => false, 'clean' => true ) as $verdict => $expected ) {
		$GLOBALS['tr']['options'][1][ Transition::APPLIED ] = array( 'hash' => 'x', 'icons' => $verdict );
		$check( $expected === Transition::effective()['svg_icons'], "Iconos heredados de la red con veredicto «{$verdict}»." );
	}
	update_option( $site, Settings::sanitize( array( 'svg_icons' => '1', 'own' => array( 'svg_icons' ) ) ) );
	$check( true === Transition::effective()['svg_icons'], 'Con hallazgos, «Aplicar» del sitio rige igual.' );

	// 3. Evento único por sitio en init.
	tr_reset();
	tr_blog( 2 );
	Transition::maybe_schedule();
	Transition::maybe_schedule();
	$check( array( array( 2, Transition::HOOK ) ) === array_map( function ( $e ) {
		return array( $e[0], $e[1] );
	}, $GLOBALS['tr']['events'] ), 'Pendiente en el sitio 2: un solo evento, en ese sitio.' );
	tr_blog( 1 );
	Transition::run_now();
	$GLOBALS['tr']['events'] = array();
	Transition::maybe_schedule();
	$check( array() === $GLOBALS['tr']['events'] && ! Transition::pending(), 'Sin cambios pendientes no se agenda nada.' );
	tr_reset( array( 'installing' => true ) );
	Transition::maybe_schedule();
	$check( array() === $GLOBALS['tr']['events'], 'Durante la instalación no se agenda.' );
	tr_reset();
	$GLOBALS['tr']['options'][1][ Transition::APPLIED ] = array( 'hash' => 'v0:000000' );
	$check( Transition::pending(), 'Una huella de otro formato obliga a transitar de nuevo.' );
	$check( 'v2:1100000' === Transition::fingerprint( Transition::effective() ), 'La huella anota el formato (2 desde lazy_videos) y los siete bits del efectivo.' );

	// 4. admin_init: si sigue pendiente, se transita ahí; nunca por AJAX.
	tr_reset( array( 'ajax' => true ) );
	Transition::maybe_run();
	$check( array() === Transition::applied() && array() === tr_log( 'files' ), 'Por AJAX, admin_init no transita.' );
	tr_reset( array( 'admin' => true ) );
	Transition::maybe_run();
	$check( true === Transition::applied()['pro_conditional'] && 1 === count( tr_log( 'files:1' ) ), 'En admin_init con pendiente, se transita en esa petición (redes sin cron).' );
	Transition::maybe_run();
	$check( 1 === count( tr_log( 'files:1' ) ) && 1 === count( tr_log( 'write:1:' . Transition::APPLIED ) ), 'Sin pendiente, admin_init no vuelve a vaciar ni a escribir.' );

	// 5. Cerrojo.
	tr_reset();
	$GLOBALS['tr']['options'][1][ Transition::LOCK ] = ( time() - 10 ) . '|ajeno';
	$check( array() === Transition::run_now() && Transition::busy() && array() === tr_log( 'files' ) && ! isset( $GLOBALS['tr']['options'][1][ Transition::APPLIED ] ), 'Con el cerrojo tomado por otra petición no se hace nada.' );
	$check( ( time() - 10 ) . '|ajeno' === $GLOBALS['tr']['options'][1][ Transition::LOCK ], 'Nunca se suelta el cerrojo de otra petición.' );
	$check( in_array( 'INSERT ' . Transition::LOCK, $GLOBALS['wpdb']->log, true ), 'El cerrojo se toma con INSERT IGNORE.' );
	$GLOBALS['tr']['options'][1][ Transition::LOCK ] = ( time() - 301 ) . '|ajeno';
	$changed = Transition::run_now();
	$check( array( 'pro_conditional', 'font_swap' ) === $changed && ! Transition::busy() && in_array( 'UPDATE ' . Transition::LOCK, $GLOBALS['wpdb']->log, true ), 'Un cerrojo de más de 300 s se roba y la transición corre.' );
	$check( ! isset( $GLOBALS['tr']['options'][1][ Transition::LOCK ] ), 'Al terminar se suelta el cerrojo propio.' );
	tr_reset();
	$GLOBALS['tr']['during_clear'] = function () {
		$GLOBALS['tr']['options'][1][ Transition::LOCK ] = time() . '|otro';
	};
	Transition::run_now();
	$check( time() . '|otro' === $GLOBALS['tr']['options'][1][ Transition::LOCK ], 'Al soltar, sólo se borra la fila con el token propio.' );
	tr_reset();
	$GLOBALS['tr']['site'][ $network ] = array( 'svg_icons' => true );
	Icon_Scan::$throw = true;
	$check( array() === Transition::run_now() && ! isset( $GLOBALS['tr']['options'][1][ Transition::LOCK ] ) && array() === Transition::applied(), 'Una excepción deja el sitio pendiente y suelta el cerrojo.' );

	// 6. Una sola clear_cache() por transición con cambios; ninguna sin ellos.
	tr_reset();
	$check( array( 'pro_conditional', 'font_swap' ) === Transition::run_now() && 1 === count( tr_log( 'files:1' ) ), 'Primera transición: una sola llamada a clear_cache() para los dos cambios.' );
	$check( true === Transition::applied()['pro_conditional'] && 'v2:1100000' === Transition::applied()['hash'], 'El aplicado queda con la huella del efectivo (contrato A: pro_conditional === true).' );
	$check( array() === Transition::run_now() && 1 === count( tr_log( 'files:1' ) ) && 1 === count( tr_log( 'write:1:' . Transition::APPLIED ) ), 'Sin cambios: ni vaciado ni escritura.' );
	update_option( $site, Settings::sanitize( array( 'pro_conditional' => '1', 'font_swap' => '1', 'lazy_libs' => '1', 'element_cache_off' => '1', 'own' => array( 'lazy_libs', 'element_cache_off' ) ) ) );
	$check( array( 'lazy_libs', 'element_cache_off' ) === Transition::run_now() && 2 === count( tr_log( 'files:1' ) ), 'Dos cambios más: una llamada más.' );

	// 7. Google_Font::clear_cache() sólo si cambia swap y las fuentes son locales.
	tr_reset();
	$GLOBALS['tr']['options'][1]['elementor_local_google_fonts'] = '0';
	Transition::run_now();
	$check( array() === tr_log( 'fonts' ), 'Con fuentes de Google remotas no se borran fuentes locales.' );
	$GLOBALS['tr']['options'][1]['elementor_local_google_fonts'] = '1';
	update_option( $site, Settings::sanitize( array( 'pro_conditional' => '1', 'lazy_libs' => '1', 'own' => array( 'lazy_libs' ) ) ) );
	Transition::run_now();
	$check( array() === tr_log( 'fonts' ), 'Con fuentes locales, un cambio que no es swap no las borra.' );
	update_option( $site, Settings::sanitize( array( 'pro_conditional' => '1', 'font_swap' => '0', 'lazy_libs' => '1', 'own' => array( 'lazy_libs', 'font_swap' ) ) ) );
	$GLOBALS['tr']['log'] = array();
	$check( array( 'font_swap' ) === Transition::run_now(), 'Apagar swap es un cambio.' );
	$order = array_map( function ( $line ) {
		return strtok( $line, ':' );
	}, $GLOBALS['tr']['log'] );
	$check( array( 'fonts', 'files', 'write', 'snippets' ) === $order, 'Orden: fuentes locales, una llamada a clear_cache(), el aplicado al final y la bandera de fragmentos: ' . implode( ',', $order ) );
	$check( 1 === count( tr_log( 'fonts:1' ) ), 'Con fuentes locales y swap cambiando, Google_Font::clear_cache() una vez.' );

	// 8. El aplicado se escribe al final y sólo si se pudo vaciar.
	tr_reset();
	$GLOBALS['tr']['options'][1][ Transition::APPLIED ] = array( 'hash' => 'viejo', 'pro_conditional' => false );
	$before = md5( serialize( $GLOBALS['tr']['options'][1][ Transition::APPLIED ] ) );
	Transition::run_now();
	$check( array( 'files:1:' . $before ) === tr_log( 'files' ), 'Mientras se vacía, el aplicado sigue siendo el anterior.' );
	tr_reset();
	\Elementor\Plugin::$instance = null;
	$check( array() === Transition::run_now() && array() === Transition::applied() && Transition::pending(), 'Sin poder vaciar, nada se aplica y el sitio sigue pendiente.' );

	// 9. Análisis heredado de la red: veredicto en el aplicado; durante el análisis rige el último final.
	tr_reset();
	$GLOBALS['tr']['site'][ $network ] = array( 'svg_icons' => true );
	Transition::run_now();
	$applied = Transition::applied();
	$check( 1 === Icon_Scan::$batches && array( 'batch:200' ) === tr_log( 'batch' ) && '' === $applied['icons'] && true === $applied['scanning'] && false === $applied['svg_icons'], 'Sin veredicto, la transición empieza el análisis con un lote de 200 y anota que sigue en curso.' );
	$check( false !== wp_next_scheduled( Transition::HOOK ) && Transition::pending(), 'Sin la continuación propia del análisis, la transición agenda su evento para empujarlo.' );
	Icon_Scan::$verdicts = array( 'clean' );
	$GLOBALS['tr']['log'] = array();
	$check( array( 'svg_icons' ) === Transition::run_now() && 2 === Icon_Scan::$batches && 'clean' === Transition::applied()['icons'] && false === Transition::applied()['scanning'] && true === Transition::applied()['svg_icons'] && 1 === count( tr_log( 'files' ) ), 'Con veredicto limpio, los iconos heredados se aplican con su vaciado.' );
	$check( ! Transition::pending(), 'Con veredicto final no queda nada pendiente.' );
	Transition::run_now();
	$check( 2 === Icon_Scan::$batches, 'Un veredicto final no se recalcula solo.' );
	// Un análisis nuevo (pedido con «Analizar») no apaga a medias lo que la red ya aplicó.
	Transition::request_scan();
	$GLOBALS['tr']['log'] = array();
	$check( array() === Transition::run_now() && 2 === Icon_Scan::$batches && 'clean' === Transition::applied()['icons'] && true === Transition::applied()['scanning'] && true === Transition::applied()['svg_icons'] && array() === tr_log( 'files' ), 'Durante un análisis nuevo rige el último veredicto final: sin cambios ni vaciado.' );
	$GLOBALS['tr']['events'] = array( array( 1, Icon_Scan::HOOK, time() ) );
	$check( ! Transition::pending(), 'Mientras la continuación propia del análisis está agendada, no hay nada pendiente.' );
	Transition::run_now();
	$check( 2 === Icon_Scan::$batches && true === Transition::applied()['scanning'] && false === wp_next_scheduled( Transition::HOOK ), 'Con la continuación agendada, la transición no empuja lotes ni se agenda.' );
	$GLOBALS['tr']['events'] = array();
	Icon_Scan::$verdicts      = array( 'findings' );
	$check( Transition::pending(), 'Al terminar el análisis (sin continuación), el sitio queda pendiente para copiar el veredicto.' );
	$check( array( 'svg_icons' ) === Transition::run_now() && 'findings' === Transition::applied()['icons'] && false === Transition::applied()['svg_icons'] && false === Transition::applied()['scanning'], 'Con hallazgos nuevos, los iconos heredados se retiran.' );
	tr_reset();
	$GLOBALS['tr']['site'][ $network ] = array( 'svg_icons' => true );
	Icon_Scan::$verdicts = array( 'findings' );
	Transition::run_now();
	$check( 'findings' === Transition::applied()['icons'] && false === Transition::applied()['svg_icons'] && ! Transition::pending(), 'Con hallazgos, los iconos heredados no se aplican y no se reintenta.' );
	tr_reset();
	$GLOBALS['tr']['site'][ $network ] = array( 'svg_icons' => true );
	Icon_Scan::$verdicts = array( 'n/a' );
	Transition::run_now();
	$check( 'n/a' === Transition::applied()['icons'] && false === Transition::applied()['svg_icons'] && ! Transition::pending() && 1 === Icon_Scan::$batches, 'Un análisis que no aplica (n/a) es final: ni iconos ni reintentos.' );
	tr_reset();
	$GLOBALS['tr']['options'][1]['elementor_experiment-e_font_icon_svg'] = 'inactive';
	$GLOBALS['tr']['site'][ $network ] = array( 'svg_icons' => true );
	Transition::run_now();
	$check( 0 === Icon_Scan::$batches && '' === Transition::applied()['icons'], 'Con el experimento elegido por el sitio no se analiza.' );
	tr_reset();
	Transition::run_now();
	$check( 0 === Icon_Scan::$batches, 'Sin iconos pedidos por la red, no se analiza nada solo.' );
	Transition::request_scan();
	Icon_Scan::$verdicts = array( 'findings' );
	Transition::run_now();
	$check( 0 === Icon_Scan::$batches && 'findings' === Transition::applied()['icons'], '«Analizar» (request_scan) sólo lee el veredicto del análisis que la pantalla ya empezó.' );
	Icon_Scan::$verdicts = array( 'clean' );
	Transition::run_now();
	$check( 'findings' === Transition::applied()['icons'], 'La petición de análisis vale para una sola transición.' );

	// 10. En una red, cada sitio transita en sus peticiones y sólo vacía lo suyo (N1, N2, N3, N6).
	tr_reset();
	foreach ( array( 1, 2 ) as $blog ) {
		tr_blog( $blog );
		Transition::run_now();
	}
	tr_blog( 1 );
	update_option( $site, Settings::sanitize( array( 'pro_conditional' => '1', 'inherit' => array( 'pro_conditional' => '0' ) ) ) );
	$GLOBALS['tr']['log']        = array();
	$GLOBALS['tr']['site'][ $network ] = array( 'pro_conditional' => false );
	$check( ! Transition::pending(), 'N2: el sitio 1 personalizó la puerta y no cambia con la red.' );
	tr_blog( 2 );
	$check( Transition::pending(), 'N1: el sitio 2 hereda el valor de red apagado y queda pendiente.' );
	Transition::maybe_schedule();
	do {
		$event = array_shift( $GLOBALS['tr']['events'] );
	} while ( $event && 2 !== $event[0] );
	$check( $event && Transition::HOOK === $event[1], 'N1: el evento queda en el sitio 2.' );
	Transition::run_event();
	$check( false === Transition::applied()['pro_conditional'] && array( 'files:2' ) === array_map( function ( $line ) {
		return substr( $line, 0, 7 );
	}, tr_log( 'files' ) ), 'N1/N6: el sitio 2 aplica en su evento con un vaciado; el sitio 1 no vacía nada.' );
	tr_blog( 1 );
	$check( true === Transition::applied()['pro_conditional'] && array() === tr_log( 'write:1' ), 'El sitio 1 conserva su aplicado.' );
	tr_blog( 3 );
	$check( Transition::pending() && false === Transition::effective()['pro_conditional'], 'N3: un sitio creado después hereda la red y queda pendiente.' );
	Transition::run_now();
	$check( false === Transition::applied()['pro_conditional'] && true === Transition::applied()['font_swap'] && 1 === count( tr_log( 'files:3' ) ), 'N3: y transita en sus propias peticiones.' );
	$check( array() === preg_grep( '/^update_site_option:/', $GLOBALS['tr']['writes'] ), 'Una transición nunca escribe la opción de red.' );

	// 11. Ninguna escritura de claves elementor_ en el módulo.
	foreach ( $GLOBALS['tr']['writes'] as $write ) {
		$check( 1 !== preg_match( '/:_?elementor/i', $write ), "En ejecución nadie del módulo escribe una clave del núcleo: {$write}" );
	}
	$files = glob( $front . '/*.php' );
	$check( count( $files ) >= 4, 'La estática recorre los archivos del módulo.' );
	// Primera pasada: código sin comentarios y constantes de cadena de cada clase del módulo.
	$sources = array();
	$classes = array();
	foreach ( $files as $file ) {
		$code = '';
		foreach ( token_get_all( file_get_contents( $file ) ) as $token ) {
			if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			$code .= is_array( $token ) ? $token[1] : $token;
		}
		$class = preg_match( '/^\s*(?:final\s+|abstract\s+)?class\s+([A-Za-z_]+)/m', $code, $m ) ? $m[1] : '';
		preg_match_all( "/const\s+([A-Z_]+)\s*=\s*'([^']*)'/", $code, $found, PREG_SET_ORDER );
		foreach ( $found as $const ) {
			$classes[ $class ][ $const[1] ] = $const[2];
		}
		$sources[ basename( $file ) ] = array( $code, $class );
	}
	foreach ( $sources as $name => $source ) {
		list( $code, $class ) = $source;
		preg_match_all( '/\b(update_option|add_option|delete_option|update_site_option|add_site_option|delete_site_option|update_network_option|add_network_option|delete_network_option|update_post_meta|add_post_meta|delete_post_meta|delete_post_meta_by_key|update_metadata|delete_metadata|set_transient|delete_transient)\s*\(\s*([^,)]+)/', $code, $calls, PREG_SET_ORDER );
		foreach ( $calls as $call ) {
			$arg = trim( $call[2] );
			if ( preg_match( '/^(self|static|[A-Za-z_\\\\]+)::([A-Z_]+)$/', $arg, $ref ) ) {
				$owner = in_array( $ref[1], array( 'self', 'static' ), true ) ? $class : substr( strrchr( '\\' . $ref[1], '\\' ), 1 );
				$check( isset( $classes[ $owner ][ $ref[2] ] ), "{$name}: {$call[1]}( {$arg} ) usa una constante que la estática no resuelve." );
				$arg = $classes[ $owner ][ $ref[2] ];
			}
			$check( false === stripos( $arg, 'elementor' ), "{$name}: {$call[1]}( {$call[2]} ) escribe una clave del núcleo." );
		}
		preg_match_all( '/\$wpdb->(query|insert|update|delete|replace)\s*\((.*?)\)\s*;/s', $code, $queries, PREG_SET_ORDER );
		foreach ( $queries as $query ) {
			$check( false === stripos( $query[2], 'elementor' ), "{$name}: \$wpdb->{$query[1]}() toca una clave del núcleo." );
			preg_match_all( '/\b(self|static|[A-Z][A-Za-z_]*)::([A-Z_]+)\b/', $query[2], $refs, PREG_SET_ORDER );
			foreach ( $refs as $ref ) {
				$owner = in_array( $ref[1], array( 'self', 'static' ), true ) ? $class : $ref[1];
				$value = $classes[ $owner ][ $ref[2] ] ?? '';
				$check( false === stripos( $value, 'elementor' ), "{$name}: \$wpdb->{$query[1]}() usa {$ref[0]} ({$value})." );
			}
		}
		$check( 0 === preg_match( '/["\'](?:INSERT|UPDATE|DELETE|REPLACE)\b[^"\']*elementor/i', $code ), "{$name}: ninguna consulta de escritura nombra una clave del núcleo." );
	}
	$transition = file_get_contents( $front . '/class-transition.php' );
	$check( false !== strpos( $transition, 'INSERT IGNORE INTO' ) && 0 === preg_match( '/\badd_option\s*\(/', $transition ), 'El cerrojo usa INSERT IGNORE, nunca add_option.' );
	$check( 1 === substr_count( $transition, '$files->clear_cache()' ) && 1 === substr_count( $transition, "array( \$fonts, 'clear_cache' )" ), 'La transición vacía sólo con las funciones del núcleo, una vez cada una.' );

	echo "F-front · transición: arranque, efectivo por contexto, evento por sitio, admin_init, cerrojo, un vaciado, fuentes locales, aplicado al final, análisis heredado y red en verde.\n";
}
