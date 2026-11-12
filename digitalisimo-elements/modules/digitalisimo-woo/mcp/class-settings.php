<?php
namespace Digitalisimo\Elements\Woo\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * «WooCommerce en el MCP»: el modo de las herramientas de tienda del servidor MCP, por sitio y por red.
 *
 * - `off`: no se registra ninguna herramienta de tienda.
 * - `read` (predeterminado): sólo lectura; create, update, delete y batch devuelven un error que dice dónde se cambia.
 * - `write`: lectura y escritura, con la bitácora.
 *
 * Paridad sitio/red, los cuatro puntos de `AGENTS.md` como en `Front\Settings`:
 * 1. `defaults()` declara la clave con su valor neutro (`read`).
 * 2. `sanitize()` valida el envío **por sitio**: el modo dentro de `MODES` y `own` (la lista de claves que el sitio
 *    personaliza), siempre dentro de `KEYS`.
 * 3. `network_fields()` pinta el campo en la pantalla de red, sin «Heredar de la red».
 * 4. `save_network()` valida el envío **de red** con la misma regla, sin saneado genérico.
 *
 * Resolución `sitio → red → default`: fuera de `own` el sitio hereda de la red (por defecto, también si nunca guardó),
 * así que un valor de red recién definido rige de inmediato en los sitios que nunca lo personalizaron. En un sitio
 * único no hay red: `own` es todo. La lectura en tiempo de ejecución pasa siempre por `mode()`.
 */
final class Settings {
	const OPTION         = 'digitalisimo_elements_woo_mcp';
	const NETWORK_OPTION = 'digitalisimo_elements_woo_mcp_defaults';
	const KEYS           = array( 'mode' );
	const MODES          = array( 'off', 'read', 'write' );

	const LABELS = array(
		'off'   => 'Apagado: el MCP no ofrece herramientas de tienda',
		'read'  => 'Sólo lectura (predeterminado)',
		'write' => 'Lectura y escritura',
	);

	const HELP = 'Con el MCP de DIGITALÍSIMO Elements activo, ofrece a los asistentes conectados siete herramientas para la tienda WooCommerce de este sitio: productos y variaciones, catálogo, pedidos (notas y reembolsos), clientes, cupones, ajustes (envíos, impuestos y pasarelas) e informes. Cada una actúa con los permisos del usuario que se conecta. En «Sólo lectura» pueden consultar pero no cambiar nada; en «Lectura y escritura» cada cambio queda en una bitácora sin datos personales. Las claves de las pasarelas nunca se muestran.';

	/** Valores neutros. */
	public static function defaults(): array {
		return array( 'mode' => 'read' );
	}

	/** Un modo válido, o el predeterminado. */
	public static function normalize_mode( $mode ): string {
		return is_string( $mode ) && in_array( $mode, self::MODES, true ) ? $mode : self::defaults()['mode'];
	}

	/**
	 * Envío por sitio: el modo y `own`. Un modo que no llega (el desplegable deshabilitado por «Heredar de la red») o
	 * que no es válido conserva el guardado. `own` sale del mapa `inherit` (desmarcado = propio) o de una lista `own`.
	 *
	 * @param array $input Envío sin barras.
	 */
	public static function sanitize( array $input ): array {
		$stored = self::stored();
		$mode   = isset( $input['mode'] ) && is_string( $input['mode'] ) && in_array( $input['mode'], self::MODES, true )
			? $input['mode']
			: ( isset( $stored['mode'] ) ? $stored['mode'] : self::defaults()['mode'] );
		return array(
			'mode' => $mode,
			'own'  => self::own_from( $input ),
		);
	}

	/**
	 * Envío de red: el modo dentro de `MODES` (otro valor no se guarda). Sólo en una red, en `sitemeta`.
	 *
	 * @param array $input Envío sin barras.
	 */
	public static function save_network( array $input ): array {
		$out = array();
		if ( isset( $input['mode'] ) && is_string( $input['mode'] ) && in_array( $input['mode'], self::MODES, true ) ) {
			$out['mode'] = $input['mode'];
		} else {
			$out = self::network_stored();
		}
		if ( self::multisite() && function_exists( 'update_site_option' ) ) {
			update_site_option( self::NETWORK_OPTION, $out );
		}
		return $out;
	}

	/** El modo en vigor en el sitio actual: sitio → red → default. */
	public static function mode(): string {
		if ( ! self::inherits_network() ) {
			$site = self::stored();
			if ( isset( $site['mode'] ) ) {
				return $site['mode'];
			}
		}
		return self::network_values()['mode'];
	}

	/** ¿Hereda el sitio el modo de la red? Sólo en una red, y por defecto sí. */
	public static function inherits_network(): bool {
		if ( ! self::multisite() ) {
			return false;
		}
		return ! in_array( 'mode', self::stored()['own'], true );
	}

	/** De dónde sale el modo: `site`, `network` o `default`. */
	public static function origin(): string {
		if ( ! self::inherits_network() && isset( self::stored()['mode'] ) ) {
			return 'site';
		}
		return isset( self::network_stored()['mode'] ) ? 'network' : 'default';
	}

	/** Opción del sitio normalizada: el modo guardado (si es válido) y `own` acotado a `KEYS`. */
	public static function stored(): array {
		$raw = function_exists( 'get_option' ) ? get_option( self::OPTION, array() ) : array();
		$raw = is_array( $raw ) ? $raw : array();
		$out = array();
		if ( isset( $raw['mode'] ) && is_string( $raw['mode'] ) && in_array( $raw['mode'], self::MODES, true ) ) {
			$out['mode'] = $raw['mode'];
		}
		$out['own'] = isset( $raw['own'] ) && is_array( $raw['own'] ) && in_array( 'mode', $raw['own'], true ) ? array( 'mode' ) : array();
		return $out;
	}

	/** Valores de red completos (los guardados sobre los neutros). Fuera de una red, los neutros. */
	public static function network_values(): array {
		return array_merge( self::defaults(), self::network_stored() );
	}

	/** Lo que la red guardó. Fuera de una red, nada. */
	public static function network_stored(): array {
		if ( ! self::multisite() || ! function_exists( 'get_site_option' ) ) {
			return array();
		}
		$raw = get_site_option( self::NETWORK_OPTION, array() );
		if ( is_array( $raw ) && isset( $raw['mode'] ) && is_string( $raw['mode'] ) && in_array( $raw['mode'], self::MODES, true ) ) {
			return array( 'mode' => $raw['mode'] );
		}
		return array();
	}

	/** Fila del modo en la pantalla de red (sin «Heredar de la red»). */
	public static function network_fields(): void {
		$value      = self::network_values()['mode'];
		$is_default = ! isset( self::network_stored()['mode'] );
		echo '<tr><th scope="row"><label for="digitalisimo-woo-mcp-network-mode">' . esc_html( 'Herramientas de tienda' ) . '</label></th><td>';
		self::select( 'digitalisimo-woo-mcp-network-mode', self::NETWORK_OPTION . '[mode]', $value, $is_default ? 'digitalisimo-default-value' : '' );
		echo '<p class="description">' . esc_html( self::HELP ) . '</p>';
		echo '<p class="description">' . esc_html( 'Cada sitio que lo hereda aplica este modo en su propio servidor MCP; un sitio sin WooCommerce no ofrece ninguna herramienta de tienda.' ) . '</p>';
		if ( $is_default ) {
			echo '<p class="description digitalisimo-default-note">' . esc_html( 'Valor predeterminado del plugin.' ) . '</p>';
		}
		echo '</td></tr>';
	}

	/** Desplegable de modos. */
	public static function select( string $id, string $name, string $value, string $class ): void {
		echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . ( '' !== $class ? ' class="' . esc_attr( $class ) . '"' : '' ) . '>';
		foreach ( self::MODES as $mode ) {
			echo '<option value="' . esc_attr( $mode ) . '"' . ( $mode === $value ? ' selected="selected"' : '' ) . '>' . esc_html( self::LABELS[ $mode ] ) . '</option>';
		}
		echo '</select>';
	}

	/** `own` de un envío (ver `sanitize()`). En un sitio único, todo. */
	private static function own_from( array $input ): array {
		if ( ! self::multisite() ) {
			return self::KEYS;
		}
		if ( isset( $input['inherit'] ) && is_array( $input['inherit'] ) ) {
			return array_key_exists( 'mode', $input['inherit'] ) && empty( $input['inherit']['mode'] ) ? array( 'mode' ) : array();
		}
		if ( isset( $input['own'] ) && is_array( $input['own'] ) ) {
			return in_array( 'mode', $input['own'], true ) ? array( 'mode' ) : array();
		}
		return array();
	}

	private static function multisite(): bool {
		return function_exists( 'is_multisite' ) && is_multisite();
	}
}
