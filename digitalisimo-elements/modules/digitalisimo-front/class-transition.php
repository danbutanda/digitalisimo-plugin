<?php
namespace Digitalisimo\Elements\Front;

defined( 'ABSPATH' ) || exit;

/**
 * Transición por sitio del valor deseado al aplicado (§3.2, D15).
 *
 * La puerta, los valores por defecto del núcleo y el cargador de librerías leen sólo el valor **aplicado**
 * (`digitalisimo_elements_front_applied`, con carga automática), nunca el deseado. Así ninguna página cacheada queda
 * con la lista de scripts de otro modo: el aplicado sólo cambia después de vaciar las cachés.
 *
 * 1. **Efectivo** = deseado (`Settings::get()`) condicionado por el contexto: núcleo incluido para `font_swap`,
 *    `svg_icons`, `element_cache_off` e `image_loading_off` (D3); fila del ajuste del núcleo ausente (el filtro
 *    `default_option_` sólo corre entonces); estado guardado del experimento de iconos vacío o `default`; y, para
 *    `svg_icons` heredado de la red, veredicto `clean` del análisis (vive en el aplicado: sin consulta extra).
 * 2. En `init` se compara la huella del efectivo con la del aplicado. Si difieren, evento único
 *    `digitalisimo_elements_front_transition` en ese sitio; en `admin_init`, si sigue pendiente, se ejecuta ahí
 *    (redes con `DISABLE_WP_CRON`). Guardar la pantalla del sitio lo ejecuta en el momento.
 * 3. Bajo cerrojo (`INSERT IGNORE` con token, robo a los 300 s): si cambia `font_swap` y las fuentes de Google son
 *    locales, `Google_Font::clear_cache()`; ante cualquier cambio efectivo, una sola llamada a
 *    `files_manager->clear_cache()` (CSS, caché de elementos, recursos por página y, por su acción, cachés de página
 *    y de DIGITALÍSIMO SEO). Al final se escribe el aplicado.
 * 4. Mientras no corre, rige lo de hoy: sin aplicado (`[]`), ningún frente cambia nada.
 * 5. En una red, cada sitio transita en sus peticiones; la red sólo escribe su opción.
 *
 * Nunca escribe un ajuste ni un experimento del núcleo: sólo lee si existen y vacía cachés con sus funciones.
 */
final class Transition {
	const HOOK    = 'digitalisimo_elements_front_transition';
	const APPLIED = 'digitalisimo_elements_front_applied';
	const LOCK    = 'digitalisimo_elements_front_lock';

	/** Segundos tras los que un cerrojo abandonado se puede robar. */
	const STALE = 300;

	/** Cambiarlo obliga a transitar de nuevo todos los sitios (nueva huella). 2: entra `lazy_videos` (Fase 3). */
	const FORMAT = 2;

	/** Entradas por lote del análisis de iconos. */
	const BATCH = 200;

	/** Ajustes del núcleo cuya fila, si existe, es la elección del sitio. */
	const CORE_OPTIONS = array(
		'font_swap'         => 'elementor_font_display',
		'element_cache_off' => 'elementor_element_cache_ttl',
		'image_loading_off' => 'elementor_optimized_image_loading',
	);

	/** Estado guardado del experimento de iconos en línea. */
	const EXPERIMENT_OPTION = 'elementor_experiment-e_font_icon_svg';

	/** Fuentes de Google servidas desde el sitio. */
	const LOCAL_FONTS_OPTION = 'elementor_local_google_fonts';

	/** Veredictos del análisis que no se vuelven a calcular solos. */
	const FINAL_VERDICTS = array( 'clean', 'findings', 'n/a' );

	/** @var bool */
	private static $initialized = false;

	/** @var array<string,string> Token del cerrojo propio por sitio. */
	private static $tokens = array();

	/** @var bool El análisis se pidió en esta petición («Analizar»). */
	private static $scan_requested = false;

	/** @var bool La última `run_now()` no pudo tomar el cerrojo. */
	private static $busy = false;

	/** @var array<string,bool> Filas del núcleo ya comprobadas en esta petición, por sitio y opción. */
	private static $rows = array();

	/** Ganchos: el evento, la comparación en `init` y la ejecución en `admin_init`. Idempotente. */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		add_action( self::HOOK, array( __CLASS__, 'run_event' ) );
		add_action( 'init', array( __CLASS__, 'maybe_schedule' ), 20 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_run' ), 20 );
	}

	/**
	 * Valor aplicado: los seis booleanos, el último veredicto final del análisis de iconos (`icons`: `clean`,
	 * `findings`, `n/a` o `''`), si hay un análisis en curso (`scanning`), la huella (`hash`) y la fecha (`time`).
	 * `[]` mientras nada se aplicó en este sitio. Es lo único que leen la puerta, los valores por defecto y el cargador.
	 */
	public static function applied(): array {
		$stored = function_exists( 'get_option' ) ? get_option( self::APPLIED, array() ) : array();
		if ( ! is_array( $stored ) || ! isset( $stored['hash'] ) || ! is_string( $stored['hash'] ) ) {
			return array();
		}
		$out = array();
		foreach ( Settings::KEYS as $key ) {
			$out[ $key ] = ! empty( $stored[ $key ] );
		}
		$icons           = isset( $stored['icons'] ) ? self::verdict_of( $stored['icons'], '' ) : '';
		$out['icons']    = 'pending' === $icons ? '' : $icons;
		$out['scanning'] = ! empty( $stored['scanning'] );
		$out['hash']     = $stored['hash'];
		$out['time']     = isset( $stored['time'] ) ? (int) $stored['time'] : 0;
		return $out;
	}

	/** Valor efectivo: lo deseado condicionado por el contexto (ver la cabecera). Seis booleanos. */
	public static function effective(): array {
		$applied = self::applied();
		return self::compute( isset( $applied['icons'] ) ? $applied['icons'] : '' );
	}

	/** Evento único en este sitio, si no hay ya uno. */
	public static function schedule(): void {
		if ( function_exists( 'wp_next_scheduled' ) && function_exists( 'wp_schedule_single_event' ) && ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time(), self::HOOK );
		}
	}

	/**
	 * Transita ahora bajo el cerrojo del sitio. Devuelve las claves cuyo valor aplicado cambió ([] si nada cambió, si
	 * el cerrojo está tomado o si algo falló: en esos casos sigue pendiente).
	 */
	public static function run_now(): array {
		self::$busy = false;
		if ( ! self::lock() ) {
			self::$busy = true;
			self::$scan_requested = false;
			return array();
		}
		try {
			return self::transit();
		} catch ( \Throwable $error ) {
			return array();
		} finally {
			self::$scan_requested = false;
			self::unlock();
		}
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Ganchos y ayudas públicas (pantalla, pruebas y arnés).

	/** `init`: si el efectivo difiere del aplicado (o falta un lote del análisis), agenda el evento. */
	public static function maybe_schedule(): void {
		if ( function_exists( 'wp_installing' ) && wp_installing() ) {
			return;
		}
		if ( self::pending() ) {
			self::schedule();
		}
	}

	/** `admin_init`: si sigue pendiente, transita en esta petición (no en las de AJAX). */
	public static function maybe_run(): void {
		if ( ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) || ( function_exists( 'wp_installing' ) && wp_installing() ) ) {
			return;
		}
		if ( self::pending() ) {
			self::run_now();
		}
	}

	/** Callback del evento. */
	public static function run_event(): void {
		self::run_now();
	}

	/** ¿Queda algo por aplicar en este sitio? Sin consultas salvo las filas del núcleo que haga falta mirar. */
	public static function pending(): bool {
		$applied = self::applied();
		if ( ! isset( $applied['hash'] ) ) {
			return true;
		}
		if ( self::fingerprint( self::compute( $applied['icons'] ) ) !== $applied['hash'] ) {
			return true;
		}
		if ( $applied['scanning'] ) {
			// Mientras el análisis avanza con su propio evento no hay nada que hacer; al terminar, se copia su veredicto.
			return ! self::scan_continues();
		}
		return self::scan_needed( $applied['icons'] );
	}

	/** «Analizar» ya empezó un análisis: la siguiente `run_now()` de esta petición lee su veredicto aunque haya otro. */
	public static function request_scan(): void {
		self::$scan_requested = true;
	}

	/** ¿La última `run_now()` encontró el cerrojo tomado por otra petición? */
	public static function busy(): bool {
		return self::$busy;
	}

	/** ¿Existe la fila de esta opción en el sitio? Nunca devuelve el valor por defecto de un filtro. */
	public static function row_exists( string $option ): bool {
		$memo = self::blog() . '|' . $option;
		if ( isset( self::$rows[ $memo ] ) ) {
			return self::$rows[ $memo ];
		}
		$known = self::cached_row( $option );
		if ( null === $known && function_exists( 'get_option' ) ) {
			// `get_option()` consulta la fila y anota su existencia en la caché de opciones; su valor no se usa.
			get_option( $option );
			$known = self::cached_row( $option );
		}
		if ( null === $known ) {
			// Un `pre_option_` salta la caché: se pregunta a la tabla (sólo lectura).
			$known = self::row_in_table( $option );
		}
		self::$rows[ $memo ] = $known;
		return $known;
	}

	/** Olvida las filas del núcleo memorizadas en esta petición (tras escribirlas otro código, y en las pruebas). */
	public static function forget(): void {
		self::$rows = array();
	}

	/** Estado guardado del experimento de iconos vacío o `default`: el sitio no lo eligió. */
	public static function experiment_unsaved(): bool {
		$state = function_exists( 'get_option' ) ? get_option( self::EXPERIMENT_OPTION, '' ) : '';
		return '' === $state || false === $state || null === $state || 'default' === $state;
	}

	/** ¿Está cargado el núcleo incluido (no el plugin del constructor original)? */
	public static function bundled(): bool {
		return class_exists( '\Digitalisimo_Elements_Core' ) && \Digitalisimo_Elements_Core::bundled();
	}

	/** Huella de un efectivo. */
	public static function fingerprint( array $effective ): string {
		$bits = '';
		foreach ( Settings::KEYS as $key ) {
			$bits .= empty( $effective[ $key ] ) ? '0' : '1';
		}
		return 'v' . self::FORMAT . ':' . $bits;
	}

	/** Suelta los cerrojos propios que quedaran tomados (registrada al tomar el primero). */
	public static function unlock_all(): void {
		foreach ( array_keys( self::$tokens ) as $blog ) {
			$switch = function_exists( 'is_multisite' ) && is_multisite() && (int) $blog && (string) $blog !== self::blog();
			if ( $switch ) {
				switch_to_blog( (int) $blog );
			}
			self::unlock();
			if ( $switch ) {
				restore_current_blog();
			}
		}
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Transición.

	/** Trabajo de `run_now()`, ya con el cerrojo. */
	private static function transit(): array {
		$applied = self::applied();
		$before  = isset( $applied['icons'] ) ? $applied['icons'] : '';
		$was     = ! empty( $applied['scanning'] );
		list( $icons, $scanning ) = self::scan_step( $before, $was );
		// Sin la continuación del análisis (otra versión del frente C o un evento perdido), lo empuja este evento.
		$more      = $scanning && ! self::scan_continues();
		$effective = self::compute( $icons );
		$hash      = self::fingerprint( $effective );
		$changed   = array();
		foreach ( Settings::KEYS as $key ) {
			// Sin aplicado rige lo de hoy: ninguno de los seis comportamientos existía.
			if ( $effective[ $key ] !== ! empty( $applied[ $key ] ) ) {
				$changed[] = $key;
			}
		}
		if ( ! $changed && isset( $applied['hash'] ) && $hash === $applied['hash'] && $icons === $before && $scanning === $was ) {
			if ( $more ) {
				self::schedule();
			}
			return array();
		}
		if ( $changed ) {
			if ( in_array( 'font_swap', $changed, true ) && self::local_fonts() ) {
				self::clear_fonts();
			}
			if ( ! self::clear_cache() ) {
				return array(); // Sin poder vaciar, nada se aplica: sigue pendiente.
			}
		}
		$record = $effective + array(
			'icons'    => $icons,
			'scanning' => $scanning,
			'hash'     => $hash,
			'time'     => time(),
		);
		update_option( self::APPLIED, $record, true );
		self::refresh_gate();
		if ( $more ) {
			self::schedule();
		}
		return $changed;
	}

	/** Efectivo con un veredicto de iconos dado. */
	private static function compute( string $icons ): array {
		$bundled = self::bundled();
		$out     = array();
		foreach ( Settings::KEYS as $key ) {
			$want = Settings::get( $key );
			if ( $want && in_array( $key, Settings::CORE_KEYS, true ) && ! $bundled ) {
				$want = false;
			}
			if ( $want && isset( self::CORE_OPTIONS[ $key ] ) && self::row_exists( self::CORE_OPTIONS[ $key ] ) ) {
				$want = false;
			}
			if ( $want && 'svg_icons' === $key ) {
				$want = self::experiment_unsaved() && ( ! Settings::inherits_network( 'svg_icons' ) || 'clean' === $icons );
			}
			$out[ $key ] = $want;
		}
		return $out;
	}

	/**
	 * Paso del análisis de iconos dentro de la transición. Devuelve el veredicto final que rige (`icons`) y si queda
	 * un análisis en curso. Mientras dura, rige el último veredicto final: un nuevo análisis nunca apaga a medias los
	 * iconos que la red ya aplicó.
	 *
	 * - «Analizar» (`request_scan()`): la pantalla ya empezó el análisis; aquí sólo se lee su veredicto.
	 * - La red pide los iconos a un sitio que los hereda y no tiene veredicto: se empieza con un lote.
	 * - Hay uno en curso: se lee su veredicto y, si su continuación falta, se empuja con un lote.
	 *
	 * @return array{0:string,1:bool}
	 */
	private static function scan_step( string $icons, bool $scanning ): array {
		if ( ! self::scanner() ) {
			return array( $icons, false );
		}
		$scan    = __NAMESPACE__ . '\\Icon_Scan';
		$observe = self::$scan_requested || $scanning;
		if ( ! $observe && self::scan_needed( $icons ) ) {
			call_user_func( array( $scan, 'run_batch' ), self::BATCH );
			$observe = true;
		} elseif ( $scanning && ! self::$scan_requested && ! self::scan_continues() ) {
			call_user_func( array( $scan, 'run_batch' ), self::BATCH );
		}
		if ( ! $observe ) {
			return array( $icons, false );
		}
		$verdict = self::verdict_of( call_user_func( array( $scan, 'verdict' ) ), '' );
		if ( 'pending' === $verdict ) {
			return array( $icons, true );
		}
		return array( '' !== $verdict ? $verdict : $icons, false );
	}

	/** ¿Pide la red los iconos a este sitio, que los hereda, sin veredicto todavía? Lo barato primero. */
	private static function scan_needed( string $icons ): bool {
		if ( in_array( $icons, self::FINAL_VERDICTS, true ) ) {
			return false;
		}
		return Settings::inherits_network( 'svg_icons' ) && Settings::get( 'svg_icons' ) && self::bundled() && self::experiment_unsaved() && self::scanner();
	}

	/** ¿Sigue agendada la continuación propia del análisis (su evento `HOOK`, si la clase lo declara)? */
	private static function scan_continues(): bool {
		$hook = __NAMESPACE__ . '\\Icon_Scan::HOOK';
		return self::scanner() && defined( $hook ) && function_exists( 'wp_next_scheduled' ) && false !== wp_next_scheduled( constant( $hook ) );
	}

	/** ¿Está el análisis de iconos (frente C) con su contrato? */
	private static function scanner(): bool {
		$scan = __NAMESPACE__ . '\\Icon_Scan';
		return class_exists( $scan ) && method_exists( $scan, 'run_batch' ) && method_exists( $scan, 'verdict' );
	}

	/** Veredicto conocido o el valor dado. */
	private static function verdict_of( $value, string $fallback ): string {
		$value = is_string( $value ) ? $value : '';
		return in_array( $value, array( 'clean', 'findings', 'pending', 'n/a' ), true ) ? $value : $fallback;
	}

	private static function local_fonts(): bool {
		return function_exists( 'get_option' ) && '1' === (string) get_option( self::LOCAL_FONTS_OPTION, '0' );
	}

	/** Borra las fuentes de Google locales con la función del núcleo; la siguiente visita las vuelve a descargar. */
	private static function clear_fonts(): void {
		$fonts = '\Elementor\Core\Files\Fonts\Google_Font';
		if ( class_exists( $fonts ) && method_exists( $fonts, 'clear_cache' ) ) {
			call_user_func( array( $fonts, 'clear_cache' ) );
		}
	}

	/** Una sola llamada a `files_manager->clear_cache()`. Devuelve si se pudo. */
	private static function clear_cache(): bool {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance ) || ! is_object( \Elementor\Plugin::$instance ) ) {
			return false;
		}
		$files = isset( \Elementor\Plugin::$instance->files_manager ) ? \Elementor\Plugin::$instance->files_manager : null;
		if ( ! is_object( $files ) || ! method_exists( $files, 'clear_cache' ) ) {
			return false;
		}
		$files->clear_cache();
		return true;
	}

	/** La bandera de fragmentos de la puerta (D12) se recalcula en cada transición, si la puerta la ofrece. */
	private static function refresh_gate(): void {
		$gate = __NAMESPACE__ . '\\Pro_Gate';
		if ( class_exists( $gate ) && method_exists( $gate, 'refresh_snippets' ) ) {
			call_user_func( array( $gate, 'refresh_snippets' ) );
		}
	}

	/** ¿Sabe la caché de opciones si existe la fila? `null` si no lo sabe. */
	private static function cached_row( string $option ): ?bool {
		if ( function_exists( 'wp_load_alloptions' ) ) {
			$all = wp_load_alloptions();
			if ( is_array( $all ) && isset( $all[ $option ] ) ) {
				return true;
			}
		}
		if ( ! function_exists( 'wp_cache_get' ) ) {
			return null;
		}
		if ( false !== wp_cache_get( $option, 'options' ) ) {
			return true;
		}
		$missing = wp_cache_get( 'notoptions', 'options' );
		return is_array( $missing ) && isset( $missing[ $option ] ) ? false : null;
	}

	private static function row_in_table( string $option ): bool {
		global $wpdb;
		if ( ! is_object( $wpdb ) ) {
			return false;
		}
		return null !== $wpdb->get_var( $wpdb->prepare( "SELECT option_id FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", $option ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Cerrojo (como `digitalisimo-theme/inc/switch.php` y la conversión de cabecera y pie del tema).

	/** `INSERT IGNORE` con token propio; uno de más de 300 s se roba sólo si nadie lo cambió entre la lectura y el robo. */
	private static function lock(): bool {
		global $wpdb;
		$blog = self::blog();
		if ( isset( self::$tokens[ $blog ] ) || ! is_object( $wpdb ) ) {
			return false; // Ya lo tiene esta petición: no se reentra.
		}
		$token = function_exists( 'wp_generate_password' ) ? wp_generate_password( 20, false ) : bin2hex( random_bytes( 10 ) );
		$value = time() . '|' . $token;
		$taken = (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} ( option_name, option_value, autoload ) VALUES ( %s, %s, 'off' )", self::LOCK, $value ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( 1 !== $taken ) {
			$held = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( '' !== $held && time() - (int) $held < self::STALE ) {
				return false;
			}
			if ( 1 !== (int) $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $value, self::LOCK, $held ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				return false;
			}
		}
		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( self::LOCK, 'options' );
		}
		if ( ! self::$tokens ) {
			register_shutdown_function( array( __CLASS__, 'unlock_all' ) );
		}
		self::$tokens[ $blog ] = $token;
		return true;
	}

	/** Suelta el cerrojo propio; nunca el de otra petición. */
	private static function unlock(): void {
		global $wpdb;
		$blog  = self::blog();
		$token = isset( self::$tokens[ $blog ] ) ? self::$tokens[ $blog ] : '';
		unset( self::$tokens[ $blog ] );
		if ( '' !== $token && is_object( $wpdb ) ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value LIKE %s", self::LOCK, '%|' . $wpdb->esc_like( $token ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( function_exists( 'wp_cache_delete' ) ) {
				wp_cache_delete( self::LOCK, 'options' );
			}
		}
	}

	private static function blog(): string {
		return function_exists( 'get_current_blog_id' ) ? (string) get_current_blog_id() : '0';
	}
}
