<?php
namespace Digitalisimo\Elements\Woo\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * «WooCommerce en el MCP»: el modo de las herramientas de tienda del servidor MCP, por sitio y por red.
 *
 * - `off`: no se registra ninguna herramienta de tienda.
 * - `read` (predeterminado): sólo lectura para los clientes MCP externos; create, update, delete y batch devuelven un
 *   error que dice dónde se cambia. El Asistente IA del escritorio (IA Tools) sí crea o actualiza productos, variaciones
 *   y catálogo con la confirmación de la persona en pantalla (`Executor::assistant_writes()`); lo demás, tampoco él.
 * - `custom` (opt-in, tanda B de la auditoría de 2026-11-24): «Escritura sólo en lo elegido». Lee todo y escribe sólo
 *   con las herramientas marcadas en `write_tools` (productos, catálogo, pedidos, clientes, cupones, ajustes, Skydropx);
 *   las demás se comportan como en «Sólo lectura». El modo y la lista se heredan juntos, como una unidad (`own` = mode).
 * - `write`: lectura y escritura, con la bitácora.
 *
 * Quién puede escribir con una herramienta lo decide sólo `can_write()`: Executor, Skydropx, las descripciones y la
 * pantalla la consultan; nada compara el modo con `write` por su cuenta.
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
	const MODES          = array( 'off', 'read', 'custom', 'write' );

	const LABELS = array(
		'off'    => 'Apagado: el MCP no ofrece herramientas de tienda',
		'read'   => 'Sólo lectura (predeterminado)',
		'custom' => 'Escritura sólo en lo elegido',
		'write'  => 'Lectura y escritura',
	);

	/** Herramientas que pueden escribir en «Escritura sólo en lo elegido», con su nombre en la pantalla. */
	const WRITE_TOOLS = array(
		'products'       => 'Productos y variaciones',
		'catalog'        => 'Catálogo (categorías, etiquetas, marcas, atributos, clases de envío y reseñas)',
		'orders'         => 'Pedidos, notas y reembolsos',
		'customers'      => 'Clientes',
		'coupons'        => 'Cupones',
		'store-settings' => 'Ajustes, envíos, impuestos y pasarelas de pago',
		'skydropx'       => 'Envíos con Skydropx (si su plugin está activo)',
	);

	const HELP = 'Con el MCP de DIGITALÍSIMO Elements activo, ofrece a los asistentes conectados siete herramientas para la tienda WooCommerce de este sitio: productos y variaciones, catálogo, pedidos (notas y reembolsos), clientes, cupones, ajustes (envíos, impuestos y pasarelas) e informes; con el plugin de Skydropx activo, una octava para sus envíos (peso y medidas de los productos, cotización de prueba, rastreo y guías). Cada una actúa con los permisos del usuario que se conecta. En «Sólo lectura» los asistentes conectados por MCP pueden consultar pero no cambiar nada; el Asistente IA de tu escritorio sí puede crear o cambiar productos y su catálogo, siempre con tu confirmación en pantalla (borrar, deshacer, pedidos, clientes, cupones y ajustes, no: lo que hace queda en el registro de cambios y se deshace con la escritura permitida). En «Escritura sólo en lo elegido» pueden cambiar sólo lo que marques abajo y lo demás lo leen. En «Lectura y escritura» pueden cambiar todo. Antes de escribir, un asistente puede pedir una simulación (dry_run) que dice qué cambiaría sin tocar nada (en todas las herramientas salvo la de Skydropx), y los cambios sencillos de productos, catálogo y cupones se pueden deshacer. Cada cambio queda en una bitácora sin datos personales. Las contraseñas y claves de pasarelas, envíos y ajustes salen enmascaradas (no los metadatos de productos, pedidos y clientes).';

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
		$out = array(
			'mode' => $mode,
			'own'  => self::own_from( $input ),
		);
		$tools = self::tools_from( $input, isset( $stored['write_tools'] ) ? $stored['write_tools'] : null );
		if ( null !== $tools ) {
			$out['write_tools'] = $tools;
		}
		return $out;
	}

	/**
	 * La lista de herramientas de un envío: sólo si el formulario la mandó (`write_tools_sent`, un campo oculto junto a
	 * las casillas: una casilla desmarcada no viaja y un grupo deshabilitado por «Heredar de la red» no manda nada), o
	 * una lista `write_tools` explícita. Si no llegó, la guardada (o null si no había: no se guarda la clave). Siempre
	 * dentro de `WRITE_TOOLS`.
	 *
	 * @param array      $input  Envío sin barras.
	 * @param array|null $stored Lista guardada.
	 * @return array|null
	 */
	private static function tools_from( array $input, $stored ) {
		if ( ! array_key_exists( 'write_tools_sent', $input ) && ! array_key_exists( 'write_tools', $input ) ) {
			return $stored;
		}
		$tools = self::normalize_tools( isset( $input['write_tools'] ) ? $input['write_tools'] : array() );
		// Vacía y sin lista previa: nada que guardar (sin la clave es lo mismo que vacía; la opción queda como antes).
		return ( ! $tools && null === $stored ) ? null : $tools;
	}

	/** Lista de herramientas válida, sin repetir y en el orden de `WRITE_TOOLS`. Acepta lista o mapa `tool => 1`. */
	public static function normalize_tools( $tools ): array {
		if ( ! is_array( $tools ) ) {
			return array();
		}
		$picked = array();
		foreach ( $tools as $key => $value ) {
			if ( is_string( $value ) && isset( self::WRITE_TOOLS[ $value ] ) ) {
				$picked[ $value ] = true;
			} elseif ( is_string( $key ) && isset( self::WRITE_TOOLS[ $key ] ) && ! empty( $value ) && '0' !== $value ) {
				$picked[ $key ] = true;
			}
		}
		return array_values( array_filter( array_keys( self::WRITE_TOOLS ), static function ( $tool ) use ( $picked ) { return isset( $picked[ $tool ] ); } ) );
	}

	/**
	 * Envío de red: el modo dentro de `MODES` (otro valor no se guarda). Sólo en una red, en `sitemeta`.
	 *
	 * @param array $input Envío sin barras.
	 */
	public static function save_network( array $input ): array {
		$stored = self::network_stored();
		$out    = array();
		if ( isset( $input['mode'] ) && is_string( $input['mode'] ) && in_array( $input['mode'], self::MODES, true ) ) {
			$out['mode'] = $input['mode'];
		} elseif ( isset( $stored['mode'] ) ) {
			$out['mode'] = $stored['mode'];
		}
		$tools = self::tools_from( $input, isset( $stored['write_tools'] ) ? $stored['write_tools'] : null );
		if ( null !== $tools ) {
			$out['write_tools'] = $tools;
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

	/**
	 * Herramientas elegidas para escribir en «Escritura sólo en lo elegido», del mismo origen que el modo (el sitio que
	 * personaliza, o la red): el modo y la lista van juntos. Sin lista guardada, ninguna.
	 */
	public static function write_tools(): array {
		if ( ! self::inherits_network() ) {
			$site = self::stored();
			if ( isset( $site['mode'] ) ) {
				return isset( $site['write_tools'] ) ? $site['write_tools'] : array();
			}
		}
		return self::network_values()['write_tools'];
	}

	/**
	 * ¿Puede escribir esta herramienta en el sitio actual? `write`: todas; `custom`: las de `write_tools()`; `read` y
	 * `off`: ninguna. La única regla del modo para escribir (Executor, Skydropx, descripciones y pantalla).
	 */
	public static function can_write( string $tool ): bool {
		$mode = self::mode();
		if ( 'write' === $mode ) {
			return true;
		}
		return 'custom' === $mode && in_array( $tool, self::write_tools(), true );
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
		if ( isset( $raw['write_tools'] ) && is_array( $raw['write_tools'] ) ) {
			$out['write_tools'] = self::normalize_tools( $raw['write_tools'] );
		}
		$out['own'] = isset( $raw['own'] ) && is_array( $raw['own'] ) && in_array( 'mode', $raw['own'], true ) ? array( 'mode' ) : array();
		return $out;
	}

	/** Valores de red completos (los guardados sobre los neutros). Fuera de una red, los neutros. */
	public static function network_values(): array {
		return array_merge( self::defaults(), array( 'write_tools' => array() ), self::network_stored() );
	}

	/** Lo que la red guardó. Fuera de una red, nada. */
	public static function network_stored(): array {
		if ( ! self::multisite() || ! function_exists( 'get_site_option' ) ) {
			return array();
		}
		$raw = get_site_option( self::NETWORK_OPTION, array() );
		$out = array();
		if ( is_array( $raw ) && isset( $raw['mode'] ) && is_string( $raw['mode'] ) && in_array( $raw['mode'], self::MODES, true ) ) {
			$out['mode'] = $raw['mode'];
		}
		if ( is_array( $raw ) && isset( $raw['write_tools'] ) && is_array( $raw['write_tools'] ) ) {
			$out['write_tools'] = self::normalize_tools( $raw['write_tools'] );
		}
		return $out;
	}

	/** Fila del modo en la pantalla de red (sin «Heredar de la red»). */
	public static function network_fields(): void {
		$value      = self::network_values()['mode'];
		$is_default = ! isset( self::network_stored()['mode'] );
		echo '<tr><th scope="row"><label for="digitalisimo-woo-mcp-network-mode">' . esc_html( 'Herramientas de tienda' ) . '</label></th><td>';
		self::select( 'digitalisimo-woo-mcp-network-mode', self::NETWORK_OPTION . '[mode]', $value, $is_default ? 'digitalisimo-default-value' : '' );
		self::tool_boxes( 'digitalisimo-woo-mcp-network-tools', self::NETWORK_OPTION, self::network_values()['write_tools'] );
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

	/**
	 * Casillas de «Escritura sólo en lo elegido»: una por herramienta y el campo oculto `write_tools_sent`, que dice que
	 * la lista viajó (sin él, todas desmarcadas no se distinguirían de un grupo deshabilitado por «Heredar de la red»).
	 */
	public static function tool_boxes( string $id, string $option, array $checked ): void {
		echo '<fieldset id="' . esc_attr( $id ) . '" class="digitalisimo-woo-mcp-tools"><legend>' . esc_html( 'Con «Escritura sólo en lo elegido», pueden escribir:' ) . '</legend>';
		echo '<input type="hidden" name="' . esc_attr( $option . '[write_tools_sent]' ) . '" value="1">';
		foreach ( self::WRITE_TOOLS as $tool => $label ) {
			echo '<label style="display:block"><input type="checkbox" name="' . esc_attr( $option . '[write_tools][]' ) . '" value="' . esc_attr( $tool ) . '"' . ( in_array( $tool, $checked, true ) ? ' checked="checked"' : '' ) . '> ' . esc_html( $label ) . '</label>';
		}
		echo '<p class="description">' . esc_html( 'Sólo cuentan con ese modo. Lo no marcado se lee pero no se cambia; la simulación (dry_run) funciona en todas salvo Skydropx, que no la tiene.' ) . '</p></fieldset>';
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
