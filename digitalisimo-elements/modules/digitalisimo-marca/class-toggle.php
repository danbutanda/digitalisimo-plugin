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
 */
final class Toggle {
	const OPTION         = 'digitalisimo_elements_marca';
	const NETWORK_OPTION = 'digitalisimo_elements_marca_network';
	const MODES          = array( 'off', 'on' );

	/** @var array|null */
	private static $memo = null;

	public static function reset(): void {
		self::$memo = null;
	}

	/** @return array{mode:string,force:bool} valores de la red. */
	public static function network_values(): array {
		$raw = function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'get_site_option' ) ? get_site_option( self::NETWORK_OPTION, array() ) : array();
		return self::clean( is_array( $raw ) ? $raw : array() );
	}

	/** @return array{mode:string,force:bool,inherit:bool} valores guardados en el sitio. */
	public static function site_values(): array {
		$raw = function_exists( 'get_option' ) ? get_option( self::OPTION, null ) : null;
		$has = is_array( $raw );
		$out = self::clean( $has ? $raw : array() );
		// En una red, un sitio sin valor propio hereda; con valor propio, sólo si lo pidió.
		$out['inherit'] = function_exists( 'is_multisite' ) && is_multisite() && ( ! $has || ! empty( $raw['inherit'] ) );
		return $out;
	}

	/** @return array{mode:string,force:bool,origin:string} valores en vigor y su origen (site, network, default). */
	public static function values(): array {
		if ( null !== self::$memo ) {
			return self::$memo;
		}
		$site = self::site_values();
		if ( ! $site['inherit'] && is_array( function_exists( 'get_option' ) ? get_option( self::OPTION, null ) : null ) ) {
			return self::$memo = array( 'mode' => $site['mode'], 'force' => $site['force'], 'origin' => 'site' );
		}
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			$net = function_exists( 'get_site_option' ) ? get_site_option( self::NETWORK_OPTION, null ) : null;
			if ( is_array( $net ) ) {
				$net = self::clean( $net );
				return self::$memo = array( 'mode' => $net['mode'], 'force' => $net['force'], 'origin' => 'network' );
			}
		}
		return self::$memo = array( 'mode' => 'off', 'force' => false, 'origin' => 'default' );
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
		$values = self::values();
		$addons = Addons::detect( $fresh );
		if ( $addons && ! $values['force'] ) {
			return array( 'state' => 'blocked', 'reason' => 'Hay complementos de terceros que extienden el constructor: con ellos activos la marca propia podría romper partes del sitio.', 'addons' => $addons );
		}
		return array( 'state' => $values['mode'], 'reason' => '', 'addons' => $addons );
	}

	/** ¿Está en vigor la marca propia en esta petición? */
	public static function active(): bool {
		if ( 'on' !== self::values()['mode'] || ! self::bundled() ) {
			return false;
		}
		/**
		 * Filtra si la marca propia está en vigor (pruebas, o un sitio que la quiera apagar por código).
		 *
		 * @param bool $active
		 */
		return (bool) apply_filters( 'digitalisimo_elements_marca_active', 'on' === self::status()['state'] );
	}
}
