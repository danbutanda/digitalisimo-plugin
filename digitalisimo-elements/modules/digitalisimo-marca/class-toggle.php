<?php
namespace Digitalisimo\Elements\Marca;

defined( 'ABSPATH' ) || exit;

/**
 * Interruptor «Marca propia en el sitio» (docs/MARCA_PROPIA.md), por sitio y con valor por defecto de red, **apagado por
 * defecto**. Resolución: sitio → red → apagado. En un sitio de una red, sin valor propio (o con «Heredar de la red»)
 * vale el de la red.
 *
 * Estado en vigor (`status()`):
 * - `unavailable`: sin el núcleo incluido (el Elementor original manda con su marca) — D-5;
 * - `blocked`: hay complementos de terceros que extienden el motor (`Addons::detect()`) y el administrador no la forzó;
 * - `off` / `on`.
 * `active()` es la única regla para lo que gobierna: en la fase 1, los enlaces y la barra de direcciones del escritorio y
 * del editor (`Admin_Urls`) y el índice REST (`Rest_Index`); en la fase 2, la capa del front.
 *
 * **Sólo DIGITALÍSIMO la enciende** (N-1 de la segunda revisión), con dos constantes de `wp-config.php`:
 * - `DIGITALISIMO_ELEMENTS_MARCA_PANEL` en `true`: la sección «Marca propia en el sitio» aparece en «Rendimiento» (en el
 *   sitio y en la red) y se puede guardar (`panel()`). Sin ella no aparece y `Settings_Section` rechaza el envío.
 * - `DIGITALISIMO_ELEMENTS_MARCA` en `'on'` u `'off'` (o `true`/`false`): manda sobre la opción del sitio y la de la red
 *   (`constant_mode()`). Con `'on'`, la casilla «forzarla» sigue saliendo de la opción guardada; un valor no válido no
 *   cuenta. Con la constante no se lee ninguna opción para decidir el modo.
 * La opción guardada sigue valiendo sin la sección (quitar la constante del panel no apaga lo que ya se encendió).
 */
final class Toggle {
	const OPTION         = 'digitalisimo_elements_marca';
	const NETWORK_OPTION = 'digitalisimo_elements_marca_network';
	const MODES          = array( 'off', 'on' );
	/** Constante de wp-config.php que muestra y deja guardar la sección «Marca propia en el sitio». */
	const PANEL_CONSTANT = 'DIGITALISIMO_ELEMENTS_MARCA_PANEL';
	/** Constante de wp-config.php que fija la marca (`on` u `off`) por encima de la opción. */
	const MODE_CONSTANT = 'DIGITALISIMO_ELEMENTS_MARCA';

	/** @var array|null */
	private static $memo = null;
	/** @var bool|null */
	private static $active = null;

	public static function reset(): void {
		self::$memo   = null;
		self::$active = null;
	}

	/** ¿Se muestra y se guarda la sección «Marca propia en el sitio»? Sólo con DIGITALISIMO_ELEMENTS_MARCA_PANEL en true. */
	public static function panel(): bool {
		return defined( self::PANEL_CONSTANT ) && true === filter_var( constant( self::PANEL_CONSTANT ), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
	}

	/** `on` u `off` si wp-config.php fija la marca con DIGITALISIMO_ELEMENTS_MARCA; si no (o con otro valor), ''. */
	public static function constant_mode(): string {
		if ( ! defined( self::MODE_CONSTANT ) ) {
			return '';
		}
		$value = constant( self::MODE_CONSTANT );
		if ( is_bool( $value ) ) {
			return $value ? 'on' : 'off';
		}
		$value = is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';
		return in_array( $value, self::MODES, true ) ? $value : '';
	}

	/** @return array{mode:string,force:bool} valores de la red. */
	public static function network_values(): array {
		$raw = function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'get_site_option' ) ? get_site_option( self::NETWORK_OPTION, array() ) : array();
		return self::clean( is_array( $raw ) ? $raw : array() );
	}

	/**
	 * La opción del sitio, o null si no existe. Se guarda con carga automática (`Settings_Section`): si no está entre
	 * las opciones cargadas, no existe, y no se pregunta a la base (sin una consulta en cada petición de los sitios que
	 * nunca la guardaron).
	 *
	 * @return mixed
	 */
	private static function site_raw() {
		if ( ! function_exists( 'get_option' ) ) {
			return null;
		}
		if ( function_exists( 'wp_load_alloptions' ) ) {
			$all = wp_load_alloptions();
			if ( is_array( $all ) && ! array_key_exists( self::OPTION, $all ) ) {
				return null;
			}
		}
		return get_option( self::OPTION, null );
	}

	/** @return array{mode:string,force:bool,inherit:bool} valores guardados en el sitio. */
	public static function site_values(): array {
		$raw = self::site_raw();
		$has = is_array( $raw );
		$out = self::clean( $has ? $raw : array() );
		// En una red, un sitio sin valor propio hereda; con valor propio, sólo si lo pidió.
		$out['inherit'] = function_exists( 'is_multisite' ) && is_multisite() && ( ! $has || ! empty( $raw['inherit'] ) );
		return $out;
	}

	/**
	 * @return array{mode:string,force:bool,origin:string} valores en vigor y su origen (constant, site, network, default):
	 * la constante DIGITALISIMO_ELEMENTS_MARCA manda sobre las opciones.
	 */
	public static function values(): array {
		if ( null !== self::$memo ) {
			return self::$memo;
		}
		$const = self::constant_mode();
		if ( 'off' === $const ) {
			return self::$memo = array( 'mode' => 'off', 'force' => false, 'origin' => 'constant' );
		}
		$stored = self::stored_values();
		if ( 'on' === $const ) {
			return self::$memo = array( 'mode' => 'on', 'force' => $stored['force'], 'origin' => 'constant' );
		}
		return self::$memo = $stored;
	}

	/**
	 * @return array{mode:string,force:bool,origin:string} lo guardado (sitio → red → apagado), sin mirar la constante. En
	 * una red, la opción de red ya viene en la caché de la petición: se pide junto con las demás opciones de red de
	 * Elements en `Digitalisimo_Elements_Admin_Load::prime()` (M-3), sin una consulta propia aunque no exista.
	 */
	public static function stored_values(): array {
		$site = self::site_values();
		if ( ! $site['inherit'] && is_array( self::site_raw() ) ) {
			return array( 'mode' => $site['mode'], 'force' => $site['force'], 'origin' => 'site' );
		}
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			$net = function_exists( 'get_site_option' ) ? get_site_option( self::NETWORK_OPTION, null ) : null;
			if ( is_array( $net ) ) {
				$net = self::clean( $net );
				return array( 'mode' => $net['mode'], 'force' => $net['force'], 'origin' => 'network' );
			}
		}
		return array( 'mode' => 'off', 'force' => false, 'origin' => 'default' );
	}

	/** @return array{mode:string,force:bool} */
	public static function clean( array $raw ): array {
		$mode = isset( $raw['mode'] ) && in_array( $raw['mode'], self::MODES, true ) ? $raw['mode'] : 'off';
		return array( 'mode' => $mode, 'force' => ! empty( $raw['force'] ) && '0' !== $raw['force'] );
	}

	public static function bundled(): bool {
		return class_exists( 'Digitalisimo_Elements_Core', false ) && \Digitalisimo_Elements_Core::bundled();
	}

	/**
	 * @return array{state:string,reason:string,addons:array} `unavailable`, `blocked`, `off` u `on`.
	 */
	public static function status( bool $fresh = false ): array {
		if ( ! self::bundled() ) {
			return array( 'state' => 'unavailable', 'reason' => 'El plugin Elementor original está activo (o el núcleo incluido no cargó): manda su propia marca.', 'addons' => array() );
		}
		// Con el paquete transformado, el JS del editor ya va renombrado: la marca propia sólo se enciende cuando la capa del
		// escritorio y del editor (D-12) esté validada en Playground; hasta entonces lo habilita sólo el filtro, para
		// probarla. Apagada, el sitio usa los originales desplegados y funciona como siempre.
		if ( class_exists( Package::class ) && Package::transformed() && ! apply_filters( 'digitalisimo_elements_marca_editor_ready', false ) ) {
			return array( 'state' => 'unavailable', 'reason' => 'La marca propia en el escritorio y el editor todavía se está validando: llega en una próxima versión.', 'addons' => array() );
		}
		$values = self::values();
		$addons = Addons::detect( $fresh );
		if ( $addons && ! $values['force'] ) {
			return array( 'state' => 'blocked', 'reason' => 'Hay complementos de terceros que extienden el constructor: con ellos activos la marca propia podría romper partes del sitio.', 'addons' => $addons );
		}
		return array( 'state' => $values['mode'], 'reason' => '', 'addons' => $addons );
	}

	/** ¿Está en vigor la marca propia en esta petición? */
	public static function active(): bool {
		if ( null !== self::$active ) {
			return self::$active;
		}
		// Con el paquete transformado, la respuesta es la que decidió el paquete al cargar Elements (I-4): las URL, la capa
		// del front, la del editor y los alias nunca discrepan en una misma petición. Su modo de respaldo (faltan los
		// originales) no es la marca en vigor.
		if ( class_exists( Package::class, false ) && Package::decided() && '' !== Package::mode() ) {
			return 'on' === Package::mode() && ! Package::fallback();
		}
		// Primero lo que no lee la base: sin el núcleo incluido no hay marca propia.
		if ( ! self::bundled() || 'on' !== self::values()['mode'] ) {
			return false;
		}
		// Antes de `init`, sin la lista de complementos en caché no se sabe si hay alguno (sus ganchos aún no existen): se
		// decide «apagada», salvo que el administrador la haya forzado (I-4).
		if ( function_exists( 'did_action' ) && ! did_action( 'init' ) && ! self::values()['force'] && null === Addons::cached() ) {
			return false;
		}
		/**
		 * Filtra si la marca propia está en vigor (pruebas, o un sitio que la quiera apagar por código).
		 *
		 * @param bool $active
		 */
		$active = (bool) apply_filters( 'digitalisimo_elements_marca_active', 'on' === self::status()['state'] );
		// Una sola respuesta por petición desde que todo cargó: el paquete decide sus URL con ella al cargar Elements.
		if ( did_action( 'init' ) ) {
			self::$active = $active;
		}
		return $active;
	}
}
