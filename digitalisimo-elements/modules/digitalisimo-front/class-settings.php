<?php
namespace Digitalisimo\Elements\Front;

defined( 'ABSPATH' ) || exit;

/**
 * Ajustes de rendimiento del front (§3.2): seis interruptores booleanos, por sitio y por red.
 *
 * Paridad sitio/red, los cuatro puntos de `AGENTS.md` trasladados a Elements:
 * 1. `defaults()` declara las seis claves con su valor neutro.
 * 2. `sanitize()` valida el envío **por sitio**: cada clave con `! empty()` y `own`, la lista de claves que el sitio
 *    personaliza, siempre dentro de las seis.
 * 3. `network_fields()` pinta las seis en la pantalla de red, sin «Heredar de la red».
 * 4. `save_network()` valida el envío **de red** con la misma regla, clave por clave, sin saneado genérico.
 *
 * Resolución `sitio → red → default`: una clave fuera de `own` hereda de la red (por defecto, también la que el
 * sitio nunca guardó), así que un valor de red recién definido rige de inmediato en los sitios que nunca lo
 * personalizaron. En un sitio único no hay red: `own` son las seis y no hay nada que heredar.
 *
 * Esta clase guarda lo **deseado**. Lo que leen la puerta, los valores por defecto y el cargador en el front es lo
 * **aplicado** (`Transition::applied()`), que la transición calcula con el contexto y escribe tras vaciar cachés.
 */
final class Settings {
	const OPTION         = 'digitalisimo_elements_front';
	const NETWORK_OPTION = 'digitalisimo_elements_front_defaults';
	const KEYS           = array( 'pro_conditional', 'font_swap', 'svg_icons', 'lazy_libs', 'element_cache_off', 'image_loading_off' );

	/** Claves cuyo efecto sólo existe con el núcleo incluido (D3); la puerta y el cargador funcionan en los dos modos. */
	const CORE_KEYS = array( 'font_swap', 'svg_icons', 'element_cache_off', 'image_loading_off' );

	/** Etiqueta de cada interruptor (la de la red cambia sólo en `svg_icons`). */
	const LABELS = array(
		'pro_conditional'   => 'Funciones avanzadas sólo donde se usan',
		'font_swap'         => 'Mostrar el texto mientras cargan las fuentes de Google (font-display: swap)',
		'svg_icons'         => 'Iconos en línea (SVG)',
		'lazy_libs'         => 'Cargar Lottie y el resaltado de código al acercarse a la pantalla',
		'element_cache_off' => 'Desactivar la caché de elementos donde el sitio no la eligió',
		'image_loading_off' => 'Desactivar la carga optimizada de imágenes donde el sitio no la eligió',
	);

	const NETWORK_SVG_LABEL = 'Aplicar los iconos en línea (SVG) en los sitios cuyo análisis salga limpio';

	/** Qué hace cada interruptor, en una frase para el administrador. */
	const HELP = array(
		'pro_conditional'   => 'Los scripts de las funciones avanzadas (popups, formularios, menús, carruseles, efectos de movimiento, elementos fijos…) sólo se cargan en las páginas que las usan. Ante cualquier duda se cargan como siempre.',
		'font_swap'         => 'Valor por defecto sólo donde el sitio nunca eligió cómo mostrar las fuentes en los ajustes del constructor. Las fuentes personalizadas siguen la opción de DIGITALÍSIMO SEO.',
		'svg_icons'         => 'Los iconos se pintan como SVG y no se cargan las fuentes de iconos. Nunca se aplican solos en un sitio que ya existe: se analizan antes y se aplican con «Aplicar».',
		'lazy_libs'         => 'La animación y el resaltado de código piden su librería cuando el elemento está a una pantalla de distancia. Una animación visible al abrir la página puede tardar un poco más en empezar.',
		'element_cache_off' => 'El constructor vuelve a pintar cada elemento en cada visita, como antes de su caché. Sólo donde el sitio no eligió el valor en los ajustes del constructor.',
		'image_loading_off' => 'Las imágenes dejan de recibir la prioridad y la carga diferida que decide el constructor. Sólo donde el sitio no eligió el valor en los ajustes del constructor.',
	);

	const NETWORK_SVG_HELP = 'Cada sitio que lo hereda analiza primero su CSS, su contenido y sus fragmentos de código: si encuentra usos de los iconos de fuente, no se aplica y el sitio lo muestra en su pantalla «Rendimiento».';

	const CORE_NOTE = 'Sólo con el constructor incluido en DIGITALÍSIMO Elements: con el constructor original activo lo decide su configuración.';

	/** Los seis valores neutros. */
	public static function defaults(): array {
		return array(
			'pro_conditional'   => true,
			'font_swap'         => true,
			'svg_icons'         => false,
			'lazy_libs'         => false,
			'element_cache_off' => false,
			'image_loading_off' => false,
		);
	}

	/**
	 * Envío por sitio: seis booleanos y `own`.
	 *
	 * `own` sale del mapa `inherit` del formulario (una clave con «Heredar de la red» desmarcado es propia; una que no
	 * figura en el mapa hereda), o de una lista `own` explícita (las acciones «Aplicar» y «Revertir»). Siempre queda
	 * dentro de las seis y en su orden. En un sitio único son las seis.
	 *
	 * @param array $input Envío sin barras.
	 */
	public static function sanitize( array $input ): array {
		$out = array();
		foreach ( self::KEYS as $key ) {
			$out[ $key ] = ! empty( $input[ $key ] );
		}
		$out['own'] = self::own_from( $input );
		return $out;
	}

	/**
	 * Los seis interruptores de la pantalla de red (filas de `form-table`). La red define lo que todos heredan: nunca
	 * muestra «Heredar de la red».
	 */
	public static function network_fields(): void {
		$values = self::network_values();
		$stored = self::network_stored();
		foreach ( self::KEYS as $key ) {
			$id         = 'digitalisimo-front-network-' . str_replace( '_', '-', $key );
			$name       = self::NETWORK_OPTION . '[' . $key . ']';
			$is_default = ! array_key_exists( $key, $stored );
			echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( self::label( $key, true ) ) . '</label></th><td>';
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="0">';
			echo '<label><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . ( $is_default ? ' class="digitalisimo-default-value"' : '' ) . ( $values[ $key ] ? ' checked="checked"' : '' ) . '> ' . esc_html( 'Activar' ) . '</label>';
			echo '<p class="description">' . esc_html( self::help( $key, true ) ) . '</p>';
			if ( in_array( $key, self::CORE_KEYS, true ) ) {
				echo '<p class="description">' . esc_html( self::CORE_NOTE ) . '</p>';
			}
			if ( $is_default ) {
				echo '<p class="description digitalisimo-default-note">' . esc_html( 'Valor predeterminado del plugin.' ) . '</p>';
			}
			echo '</td></tr>';
		}
	}

	/**
	 * Envío de red: cada clave con `! empty()`, sin saneado genérico; lo demás se descarta. Se guarda en `sitemeta`
	 * (sólo en una red) y devuelve lo guardado. No transita ningún sitio: cada uno aplica en sus propias peticiones.
	 *
	 * @param array $input Envío sin barras.
	 */
	public static function save_network( array $input ): array {
		$out = array();
		foreach ( self::KEYS as $key ) {
			$out[ $key ] = ! empty( $input[ $key ] );
		}
		if ( self::multisite() && function_exists( 'update_site_option' ) ) {
			update_site_option( self::NETWORK_OPTION, $out );
		}
		return $out;
	}

	/** Valor deseado de una clave: sitio → red → default. Una clave desconocida es `false`. */
	public static function get( string $key ): bool {
		if ( ! in_array( $key, self::KEYS, true ) ) {
			return false;
		}
		if ( ! self::inherits_network( $key ) ) {
			$site = self::stored();
			if ( array_key_exists( $key, $site ) ) {
				return $site[ $key ];
			}
		}
		return self::network_values()[ $key ];
	}

	/** ¿Hereda el sitio esta clave de la red? Sólo en una red, y por defecto sí (clave fuera de `own`). */
	public static function inherits_network( string $key ): bool {
		if ( ! self::multisite() || ! in_array( $key, self::KEYS, true ) ) {
			return false;
		}
		return ! in_array( $key, self::stored()['own'], true );
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Lecturas auxiliares (las usan la pantalla y la transición).

	/** Opción del sitio normalizada: las claves guardadas (booleanas) y `own` acotado a las seis. */
	public static function stored(): array {
		$raw = function_exists( 'get_option' ) ? get_option( self::OPTION, array() ) : array();
		$raw = is_array( $raw ) ? $raw : array();
		$out = array();
		foreach ( self::KEYS as $key ) {
			if ( array_key_exists( $key, $raw ) ) {
				$out[ $key ] = ! empty( $raw[ $key ] );
			}
		}
		$out['own'] = isset( $raw['own'] ) && is_array( $raw['own'] ) ? self::keys_in( $raw['own'] ) : array();
		return $out;
	}

	/** Valores de red completos (los guardados sobre los neutros). Fuera de una red, los neutros. */
	public static function network_values(): array {
		$out = self::defaults();
		foreach ( self::network_stored() as $key => $value ) {
			$out[ $key ] = $value;
		}
		return $out;
	}

	/** Claves que la red guardó, booleanas. Fuera de una red, ninguna. Una lectura de `sitemeta` por petición. */
	public static function network_stored(): array {
		if ( ! self::multisite() || ! function_exists( 'get_site_option' ) ) {
			return array();
		}
		$raw = get_site_option( self::NETWORK_OPTION, array() );
		$out = array();
		if ( is_array( $raw ) ) {
			foreach ( self::KEYS as $key ) {
				if ( array_key_exists( $key, $raw ) ) {
					$out[ $key ] = ! empty( $raw[ $key ] );
				}
			}
		}
		return $out;
	}

	/** De dónde sale el valor deseado: `site`, `network` o `default`. */
	public static function origin( string $key ): string {
		if ( ! in_array( $key, self::KEYS, true ) ) {
			return 'default';
		}
		if ( ! self::inherits_network( $key ) && array_key_exists( $key, self::stored() ) ) {
			return 'site';
		}
		return array_key_exists( $key, self::network_stored() ) ? 'network' : 'default';
	}

	/** Etiqueta de un interruptor en el sitio o en la red. */
	public static function label( string $key, bool $network = false ): string {
		if ( $network && 'svg_icons' === $key ) {
			return self::NETWORK_SVG_LABEL;
		}
		return isset( self::LABELS[ $key ] ) ? self::LABELS[ $key ] : $key;
	}

	/** Ayuda de un interruptor en el sitio o en la red. */
	public static function help( string $key, bool $network = false ): string {
		if ( $network && 'svg_icons' === $key ) {
			return self::NETWORK_SVG_HELP;
		}
		return isset( self::HELP[ $key ] ) ? self::HELP[ $key ] : '';
	}

	/** Claves de una lista que son de las seis, sin repetir y en su orden. */
	private static function keys_in( array $list ): array {
		$asked = array();
		foreach ( $list as $item ) {
			if ( is_string( $item ) ) {
				$asked[] = $item;
			}
		}
		return array_values( array_intersect( self::KEYS, $asked ) );
	}

	/** `own` de un envío (ver `sanitize()`). */
	private static function own_from( array $input ): array {
		if ( ! self::multisite() ) {
			return self::KEYS;
		}
		if ( isset( $input['inherit'] ) && is_array( $input['inherit'] ) ) {
			$own = array();
			foreach ( self::KEYS as $key ) {
				if ( array_key_exists( $key, $input['inherit'] ) && empty( $input['inherit'][ $key ] ) ) {
					$own[] = $key;
				}
			}
			return $own;
		}
		if ( isset( $input['own'] ) && is_array( $input['own'] ) ) {
			return self::keys_in( $input['own'] );
		}
		return array();
	}

	private static function multisite(): bool {
		return function_exists( 'is_multisite' ) && is_multisite();
	}
}
