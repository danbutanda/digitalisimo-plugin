<?php
namespace Digitalisimo\Elements\Import\Sources;

defined( 'ABSPATH' ) || exit;

/**
 * Fuentes, iconos y medios remotos de un kit (§10.5), para el análisis y el informe. Sólo informa: no descarga,
 * no encola y no cambia nada.
 *
 * - **Fuentes.** Cada `*_font_family` del contenido y de los ajustes del kit se clasifica con el registro de
 *   fuentes del núcleo de este sitio: Google (no se descargan al importar; se listan), del sistema, propias del
 *   sitio o que faltan (una fuente propia del kit que el sitio no tiene). Como en el blindaje de fuentes de
 *   DIGITALÍSIMO SEO, el código no nombra familias: lo decide el registro del sitio, y una fuente de iconos sigue
 *   la regla de exclusión de SEO (`icon_family()`) cuando SEO está activo.
 * - **Iconos.** Cada control de iconos (`{ value, library }`) cuenta para su biblioteca, que existe en este sitio
 *   o falta. Los SVG son medios.
 * - **Medios remotos.** Las URL de medios (controles de medios, galerías, fondos, iconos SVG y `url(…)` del CSS
 *   propio) se agrupan por host: del origen del kit o externas, y marcadas si son del proveedor del constructor,
 *   que el proxy de la importación bloquea (§5.4). Los enlaces (controles URL) no son medios.
 */
final class Asset_Scan {
	const MAX_DEPTH = 256;

	/** Entradas por lista del resultado; el resto sólo suma en `truncated`. */
	const MAX_ITEMS = 200;

	const FONT_GOOGLE  = 'google';
	const FONT_SYSTEM  = 'system';
	const FONT_CUSTOM  = 'custom';
	const FONT_MISSING = 'missing';
	const FONT_ICON    = 'icon';

	/** Tipos del registro de fuentes del núcleo que se sirven desde Google. */
	const GOOGLE_TYPES = array( 'googlefonts', 'earlyaccess' );

	/** @var array */
	private $env;

	/** @var array */
	private $result;

	/** @var array Familia => clase, para no preguntar dos veces. */
	private $fonts_seen = array();

	/**
	 * Analiza los documentos de un kit.
	 *
	 * @param array $documents    Árboles de elementos (`content` de cada documento), en una lista o por nombre.
	 * @param array $kit_settings Ajustes del kit (`site-settings.json` → `settings`).
	 * @param array $env {
	 *     Para pruebas; por defecto, lo de este sitio.
	 *
	 *     @type callable $font_type    `( string $family ): string|false`, tipo en el registro de fuentes del núcleo.
	 *     @type callable $icon_family  `( string $family ): bool`, ¿es una fuente de iconos?
	 *     @type callable $icon_library `( string $library ): ?bool`, ¿existe la biblioteca? null si no se sabe.
	 *     @type callable $blocked      `( string $url ): bool`, ¿es del proveedor?
	 *     @type string[] $origin_hosts Hosts del origen del kit (`home`, `site`, `uploads`).
	 * }
	 *
	 * @return array {
	 *     @type array $fonts `google|system|custom|missing|icon` => familia => usos.
	 *     @type array $icons `libraries` => biblioteca => `{ count, status }` (`available`, `missing`, `unknown`) y `svg`.
	 *     @type array $media `hosts` => host => `{ count, origin, blocked }`, `external` y `relative`.
	 *     @type int   $truncated Entradas que no cupieron.
	 * }
	 */
	public static function scan( array $documents, array $kit_settings = array(), array $env = array() ): array {
		$scan = new self( $env );
		foreach ( $documents as $elements ) {
			if ( is_array( $elements ) ) {
				$scan->elements( $elements, 0 );
			}
		}
		if ( $kit_settings ) {
			$scan->settings( $kit_settings, 0 );
		}
		return $scan->finish();
	}

	/** Hosts de un origen (`home`, `site`, `uploads`), en minúsculas y sin repetir. */
	public static function hosts_of( array $origin ): array {
		$hosts = array();
		foreach ( $origin as $url ) {
			$host = is_string( $url ) ? self::host( $url ) : '';
			if ( '' !== $host ) {
				$hosts[ $host ] = true;
			}
		}
		return array_keys( $hosts );
	}

	private function __construct( array $env ) {
		$this->env    = self::env( $env );
		$this->result = array(
			'fonts'     => array(
				self::FONT_GOOGLE  => array(),
				self::FONT_SYSTEM  => array(),
				self::FONT_CUSTOM  => array(),
				self::FONT_MISSING => array(),
				self::FONT_ICON    => array(),
			),
			'icons'     => array(
				'libraries' => array(),
				'svg'       => 0,
			),
			'media'     => array(
				'hosts'    => array(),
				'external' => 0,
				'relative' => 0,
			),
			'truncated' => 0,
		);
	}

	private function elements( array $elements, int $depth ): void {
		if ( $depth > self::MAX_DEPTH ) {
			return;
		}
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			if ( isset( $element['settings'] ) && is_array( $element['settings'] ) ) {
				$this->settings( $element['settings'], $depth + 1 );
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$this->elements( $element['elements'], $depth + 1 );
			}
		}
	}

	/** Recorre ajustes (repetidores, grupos, la carga de un marcador…) buscando fuentes, iconos y medios. */
	private function settings( array $settings, int $depth ): void {
		if ( $depth > self::MAX_DEPTH ) {
			return;
		}
		if ( $this->is_icon( $settings ) ) {
			$this->icon( $settings );
			return;
		}
		if ( isset( $settings['url'] ) && is_string( $settings['url'] ) && ! $this->is_link( $settings ) ) {
			$this->media( $settings['url'] );
		}
		foreach ( $settings as $key => $value ) {
			$key = (string) $key;
			if ( is_string( $value ) ) {
				if ( '' !== $value && '_font_family' === substr( $key, -12 ) ) {
					$this->font( $value );
				} elseif ( 'custom_css' === $key ) {
					$this->css( $value );
				}
				continue;
			}
			if ( is_array( $value ) && 'url' !== $key ) {
				if ( isset( $value['elType'] ) ) {
					// La carga de un marcador o un elemento anidado en un ajuste.
					$this->elements( array( $value ), $depth + 1 );
					continue;
				}
				$this->settings( $value, $depth + 1 );
			}
		}
	}

	private function font( string $family ): void {
		$family = trim( $family, " \t'\"" );
		if ( '' === $family || strlen( $family ) > 200 ) {
			return;
		}
		if ( ! isset( $this->fonts_seen[ $family ] ) ) {
			$this->fonts_seen[ $family ] = $this->classify_font( $family );
		}
		$this->add( $this->result['fonts'][ $this->fonts_seen[ $family ] ], $family );
	}

	private function classify_font( string $family ): string {
		if ( call_user_func( $this->env['icon_family'], $family ) ) {
			return self::FONT_ICON;
		}
		$type = call_user_func( $this->env['font_type'], $family );
		if ( false === $type || null === $type || '' === $type ) {
			return self::FONT_MISSING;
		}
		$type = strtolower( (string) $type );
		if ( in_array( $type, self::GOOGLE_TYPES, true ) ) {
			return self::FONT_GOOGLE;
		}
		return 'system' === $type ? self::FONT_SYSTEM : self::FONT_CUSTOM;
	}

	/** Un control de iconos guarda `{ value, library }`; un SVG lleva en `value` el medio `{ id, url }`. */
	private function is_icon( array $settings ): bool {
		return array_key_exists( 'library', $settings ) && array_key_exists( 'value', $settings ) && is_string( $settings['library'] );
	}

	private function icon( array $settings ): void {
		$library = trim( $settings['library'] );
		if ( '' === $library ) {
			return;
		}
		if ( 'svg' === $library ) {
			++$this->result['icons']['svg'];
			if ( is_array( $settings['value'] ) && isset( $settings['value']['url'] ) && is_string( $settings['value']['url'] ) ) {
				$this->media( $settings['value']['url'] );
			}
			return;
		}
		if ( 1 !== preg_match( '/^[A-Za-z0-9_-]{1,100}$/', $library ) ) {
			return;
		}
		$libraries =& $this->result['icons']['libraries'];
		if ( ! isset( $libraries[ $library ] ) ) {
			if ( count( $libraries ) >= self::MAX_ITEMS ) {
				++$this->result['truncated'];
				return;
			}
			$exists                = call_user_func( $this->env['icon_library'], $library );
			$libraries[ $library ] = array(
				'count'  => 0,
				'status' => null === $exists ? 'unknown' : ( $exists ? 'available' : 'missing' ),
			);
		}
		++$libraries[ $library ]['count'];
	}

	/** Un control URL (enlace) lleva `is_external` o `nofollow`; un medio, no. */
	private function is_link( array $settings ): bool {
		return array_key_exists( 'is_external', $settings ) || array_key_exists( 'nofollow', $settings ) || array_key_exists( 'custom_attributes', $settings );
	}

	private function css( string $css ): void {
		if ( false === stripos( $css, 'url(' ) ) {
			return;
		}
		if ( preg_match_all( '/url\(\s*([\'"]?)([^\'")\s]+)\1\s*\)/i', $css, $matches ) ) {
			foreach ( $matches[2] as $url ) {
				$this->media( $url );
			}
		}
	}

	private function media( string $url ): void {
		$url = trim( $url );
		if ( '' === $url || 0 === strpos( $url, 'data:' ) || 0 === strpos( $url, '#' ) ) {
			return;
		}
		$host = self::host( $url );
		if ( '' === $host ) {
			++$this->result['media']['relative'];
			return;
		}
		$hosts =& $this->result['media']['hosts'];
		if ( ! isset( $hosts[ $host ] ) ) {
			if ( count( $hosts ) >= self::MAX_ITEMS ) {
				++$this->result['truncated'];
				return;
			}
			$hosts[ $host ] = array(
				'count'   => 0,
				'origin'  => in_array( $host, $this->env['origin_hosts'], true ),
				'blocked' => (bool) call_user_func( $this->env['blocked'], $url ),
			);
		}
		++$hosts[ $host ]['count'];
		if ( ! $hosts[ $host ]['origin'] ) {
			++$this->result['media']['external'];
		}
	}

	private function add( array &$list, string $family ): void {
		if ( ! isset( $list[ $family ] ) ) {
			if ( count( $list ) >= self::MAX_ITEMS ) {
				++$this->result['truncated'];
				return;
			}
			$list[ $family ] = 0;
		}
		++$list[ $family ];
	}

	private function finish(): array {
		foreach ( $this->result['fonts'] as &$list ) {
			ksort( $list, SORT_STRING );
		}
		unset( $list );
		ksort( $this->result['icons']['libraries'], SORT_STRING );
		ksort( $this->result['media']['hosts'], SORT_STRING );
		return $this->result;
	}

	/** Host en minúsculas de una URL absoluta (`http`, `https` o `//`), o ''. */
	private static function host( string $url ): string {
		if ( 1 !== preg_match( '#^(?:https?:)?//([^/?\#:@\s]+)#i', trim( $url ), $match ) ) {
			return '';
		}
		return strtolower( rtrim( $match[1], '.' ) );
	}

	private static function env( array $env ): array {
		if ( ! isset( $env['font_type'] ) || ! is_callable( $env['font_type'] ) ) {
			$env['font_type'] = array( __CLASS__, 'site_font_type' );
		}
		if ( ! isset( $env['icon_family'] ) || ! is_callable( $env['icon_family'] ) ) {
			$env['icon_family'] = array( __CLASS__, 'site_icon_family' );
		}
		if ( ! isset( $env['icon_library'] ) || ! is_callable( $env['icon_library'] ) ) {
			$env['icon_library'] = array( __CLASS__, 'site_icon_library' );
		}
		if ( ! isset( $env['blocked'] ) || ! is_callable( $env['blocked'] ) ) {
			$env['blocked'] = array( __CLASS__, 'vendor_url' );
		}
		$hosts = isset( $env['origin_hosts'] ) && is_array( $env['origin_hosts'] ) ? $env['origin_hosts'] : array();
		$env['origin_hosts'] = array_values( array_filter( array_map( 'strtolower', array_map( 'strval', $hosts ) ) ) );
		return $env;
	}

	/**
	 * Tipo de una familia en el registro de fuentes del núcleo de este sitio, o false si no la tiene.
	 *
	 * @return string|false
	 */
	public static function site_font_type( string $family ) {
		return class_exists( '\Elementor\Fonts' ) ? \Elementor\Fonts::get_font_type( $family ) : false;
	}

	/** Regla de fuentes de iconos de DIGITALÍSIMO SEO, si está activo; sin SEO, ninguna familia lo es. */
	public static function site_icon_family( string $family ): bool {
		$class = 'Digitalisimo_Integrations_Performance_Font_Guard';
		return class_exists( $class, false ) && is_callable( array( $class, 'icon_family' ) ) && (bool) call_user_func( array( $class, 'icon_family' ), $family );
	}

	/** ¿Existe la biblioteca de iconos en este sitio? null si el núcleo no la puede decir en esta petición. */
	public static function site_icon_library( string $library ): ?bool {
		if ( ! class_exists( '\Elementor\Icons_Manager' ) ) {
			return null;
		}
		$tabs = \Elementor\Icons_Manager::get_icon_manager_tabs();
		return is_array( $tabs ) && $tabs ? isset( $tabs[ $library ] ) : null;
	}

	/** ¿Es una URL del proveedor del constructor? Lo decide el escudo de Elements, con los dos núcleos (V-32). */
	public static function vendor_url( string $url ): bool {
		return class_exists( '\Digitalisimo_Elements_Vendor_Free', false ) && (bool) \Digitalisimo_Elements_Vendor_Free::vendor_url( $url );
	}
}
