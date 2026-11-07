<?php
namespace Digitalisimo\Elements\Import\Kits;

defined( 'ABSPATH' ) || exit;

/**
 * Widgets de PRO al importar un kit (§17 de `docs/fusion/k-kits.md`, programa «sin widgets de PRO», C1).
 *
 * Cada elemento del documento que se guarda cuyo `widgetType` tiene un mapa listo en el núcleo de conversión de
 * Elements (`Digitalisimo\Elements\Convert\Registry::map()`) pasa por `Convert\Converter::element()`, a cualquier
 * profundidad: páginas, plantillas, popups, Loop Items, contenedores anidados y los hijos de un widget anidado. El
 * mismo `id`, el mismo `elType` y los mismos hijos; los ajustes los traduce el mapa. Aquí no hay ninguna regla de
 * traducción: K sólo recorre el árbol, decide si el destino existe y lleva los códigos al informe del kit.
 *
 * - **Dónde corre.** Dentro de `Context::preserve()` (`elementor/document/save/data`, prioridad 5, sólo con una
 *   importación en curso), antes del marcador de `Preserve` y de la captura del núcleo en la prioridad 10, así que el
 *   núcleo guarda, remapea (prioridad 20) y vuelve a guardar el widget propio. Exportar no convierte nada.
 * - **Sólo con el núcleo de conversión cargado** (`available()`, con `class_exists`): sin él, o sin mapas listos, el
 *   documento queda exactamente igual.
 * - **Falla cerrado.** Si el widget propio no está registrado en este sitio, el widget de PRO se queda como llegó (sigue
 *   registrado y se ve igual) y el informe lo dice (`pro.target_missing`).
 * - **Idempotente.** Un widget ya traducido lleva la marca del traductor y no se vuelve a traducir; el segundo guardado
 *   del núcleo (`save_elements_of_imported_posts()`) no repite códigos.
 * - **Informe.** `pro.converted` por elemento y los códigos del traductor (`pro.unmapped`, `pro.dropped`,
 *   `pro.approx`, `pro.css`), todos en la sección «Widgets convertidos a widgets propios» (`Report::SECTION_PREFIXES`).
 *
 * Las plantillas sueltas de la biblioteca, las revisiones y los valores por defecto de elementos del kit no se
 * convierten: siguen con el widget de PRO, que queda registrado, y «Migrar» los convierte.
 *
 * **CSS que nombra clases de PRO** (corrección de fidelidad de C5 y C6): en cada documento que se guarda (también el kit,
 * con su CSS personalizado de «Ajustes del sitio»), el CSS personalizado de la página y el de los elementos gana, detrás
 * de cada regla que nombra clases de PRO, su traducción a las clases propias (`Convert\Site_Css` del núcleo de
 * conversión; la regla original no cambia). Se informa `pro.site_css`.
 */
final class Pro_Convert {
	const CONVERTER = '\\Digitalisimo\\Elements\\Convert\\Converter';
	const REGISTRY  = '\\Digitalisimo\\Elements\\Convert\\Registry';
	const SITE_CSS  = '\\Digitalisimo\\Elements\\Convert\\Site_Css';

	/** Prefijo de todos los códigos que esta pieza lleva al informe. */
	const PREFIX = 'pro.';

	/** Nivel de cada código del traductor en el informe del kit; uno desconocido es un aviso. */
	const LEVELS = array(
		'pro.converted'      => Part_Context::INFO,
		'pro.approx'         => Part_Context::INFO,
		'pro.css'            => Part_Context::INFO,
		'pro.dropped'        => Part_Context::WARNING,
		'pro.unmapped'       => Part_Context::WARNING,
		'pro.target_missing' => Part_Context::WARNING,
		'pro.site_css'       => Part_Context::INFO,
		'pro.global'         => Part_Context::INFO,
		'pro.global_missing' => Part_Context::WARNING,
	);

	/** Sustitución del widget global por su contenido (C9), en el núcleo de conversión de Elements. */
	const GLOBALS = '\\Digitalisimo\\Elements\\Convert\\Globals';

	/** Profundidad máxima que se recorre (la misma que el marcador). */
	const MAX_DEPTH = 256;

	/** @var array<string,bool> Widget propio => ¿registrado?, por petición. */
	private static $targets = array();

	/** ¿Está cargado el núcleo de conversión de Elements? */
	public static function available(): bool {
		return class_exists( self::CONVERTER ) && class_exists( self::REGISTRY );
	}

	/**
	 * Convierte los widgets de PRO de un árbol y devuelve el árbol y lo que pasó. Pura: no lee ni escribe la base de
	 * datos ni el informe.
	 *
	 * @param array $elements Árbol de elementos de un documento.
	 * @param array $env      Para pruebas: `known( string $widget ): bool` decide si el widget propio está registrado.
	 *
	 * @return array{0: array, 1: array[]} Árbol y lista de `{ code, level, data }`.
	 */
	public static function tree( array $elements, array $env = array() ): array {
		$log = array();
		if ( ! self::available() || ! $elements ) {
			return array( $elements, $log );
		}
		$known = isset( $env['known'] ) && is_callable( $env['known'] ) ? $env['known'] : array( __CLASS__, 'target_registered' );
		return array( self::walk( $elements, $known, $log, 0 ), $log );
	}

	/**
	 * `Context::preserve()`: convierte el documento que se guarda y lleva los códigos al informe de la sesión.
	 *
	 * @param array  $data     `{ elements, settings }` del guardado.
	 * @param object $document Documento que se guarda.
	 * @param callable|null $report `( string $code, array $data, string $level )`; por defecto, `Context::report()`.
	 */
	public static function on_save_data( array $data, $document = null, ?callable $report = null ): array {
		if ( ! self::available() ) {
			return $data;
		}
		$log = array();
		if ( ! empty( $data['elements'] ) && is_array( $data['elements'] ) ) {
			list( $elements, $log ) = self::tree( $data['elements'] );
			if ( $log ) {
				$data['elements'] = $elements;
			}
		}
		list( $data, $css ) = self::site_css( $data );
		$log                = array_merge( $log, $css );
		if ( ! $log ) {
			return $data;
		}
		$post             = is_object( $document ) && method_exists( $document, 'get_main_id' ) ? (int) $document->get_main_id() : 0;
		$report           = null !== $report ? $report : array( __NAMESPACE__ . '\\Context', 'report' );
		foreach ( $log as $item ) {
			call_user_func( $report, $item['code'], ( $post > 0 ? array( 'post' => $post ) : array() ) + $item['data'], $item['level'] );
		}
		return $data;
	}

	/**
	 * CSS personalizado de la página (o del kit) y de los elementos que nombra clases de PRO: su traducción detrás de cada
	 * regla (`Convert\Site_Css`). Pura.
	 *
	 * @return array{0: array, 1: array[]} Datos del guardado y lista de `{ code, level, data }`.
	 */
	public static function site_css( array $data ): array {
		$log   = array();
		$class = self::SITE_CSS;
		if ( ! self::available() || ! class_exists( $class ) ) {
			return array( $data, $log );
		}
		if ( ! empty( $data['elements'] ) && is_array( $data['elements'] ) ) {
			list( $elements, $changed ) = $class::tree( $data['elements'] );
			if ( $changed ) {
				$data['elements'] = $elements;
				$log[]            = self::entry( 'pro.site_css', array( 'lugar' => 'custom_css', 'elementos' => $changed ) );
			}
		}
		if ( isset( $data['settings']['custom_css'] ) && is_string( $data['settings']['custom_css'] ) && '' !== $data['settings']['custom_css'] ) {
			$css = $class::rewrite( $data['settings']['custom_css'] );
			if ( $css !== $data['settings']['custom_css'] ) {
				$data['settings']['custom_css'] = $css;
				$log[]                          = self::entry( 'pro.site_css', array( 'lugar' => 'settings.custom_css' ) );
			}
		}
		return array( $data, $log );
	}

	/** ¿Está registrado el widget propio en este sitio? Sin núcleo cargado se responde que sí (lo decide `Preserve`). */
	public static function target_registered( string $widget ): bool {
		if ( ! isset( self::$targets[ $widget ] ) ) {
			self::$targets[ $widget ] = Preserve::is_registered( 'widget', $widget );
		}
		return self::$targets[ $widget ];
	}

	/** ¿Está la sustitución del widget global en el núcleo de conversión? */
	public static function globals_available(): bool {
		return self::available() && class_exists( self::GLOBALS );
	}

	/**
	 * Widget global (C9 del programa «sin widgets de PRO»): después del remapeo (`Context::remap()`, prioridad 20, petición
	 * final), cada instancia (`widgetType: global` con `templateID` ya del sitio) pasa al widget global propio
	 * (`digitalisimo-global`, `Convert\Globals::link()`), que sigue leyendo su plantilla al pintar (el vínculo de PRO se
	 * conserva; la plantilla, un documento más del kit, se convierte al guardarse). Con una versión del núcleo de
	 * conversión sin `link()`, la instancia pasa a ser el elemento guardado en su plantilla (convertido con los mapas
	 * listos). Sin plantilla (no viajó en el kit, o falla cerrado el remapeo) la instancia se queda como está y no pinta
	 * nada, como antes, y se informa `pro.global_missing`. La plantilla nunca se borra ni se cambia.
	 *
	 * @param array         $data     `{ elements, settings }` ya remapeados.
	 * @param int           $post     Documento.
	 * @param callable      $resolve  `( int $plantilla ): ?array` (`Context::global_template()`).
	 * @param callable|null $report   `( string $code, array $data, string $level )`; por defecto, `Context::report()`.
	 */
	public static function on_remapped_data( array $data, int $post, callable $resolve, ?callable $report = null ): array {
		if ( empty( $data['elements'] ) || ! is_array( $data['elements'] ) || ! self::globals_available() ) {
			return $data;
		}
		$globals = self::GLOBALS;
		if ( false === strpos( (string) json_encode( $data['elements'] ), '"widgetType":"global"' ) ) {
			return $data;
		}
		$log      = array();
		$contents = array();
		$resolver = static function ( int $template ) use ( $resolve, &$contents, &$log ) {
			if ( ! array_key_exists( $template, $contents ) ) {
				$content = call_user_func( $resolve, $template );
				if ( is_array( $content ) ) {
					list( $content, $converted ) = self::tree( $content );
					$log = array_merge( $log, $converted );
				}
				$contents[ $template ] = is_array( $content ) ? $content : null;
			}
			return $contents[ $template ];
		};
		$rows = array();
		if ( method_exists( $globals, 'link' ) && self::target_registered( 'digitalisimo-global' ) ) {
			$read             = array();
			$lookup           = static function ( int $template ) use ( $resolve, &$read ) {
				if ( ! array_key_exists( $template, $read ) ) {
					$read[ $template ] = call_user_func( $resolve, $template );
				}
				return $read[ $template ];
			};
			$data['elements'] = $globals::link( $data['elements'], $lookup, $rows );
		} else {
			$data['elements'] = $globals::tree( $data['elements'], $resolver, $rows );
		}
		foreach ( $rows as $row ) {
			$entry = array( 'widget' => 'global', 'plantilla' => (int) $row['template'], 'elemento' => (string) $row['element'] );
			if ( ! empty( $row['expanded'] ) ) {
				$log[] = self::entry( 'pro.global', $entry + array( 'destino' => (string) $row['target'] ) );
			} else {
				$log[] = self::entry( 'pro.global_missing', $entry );
			}
		}
		$report = null !== $report ? $report : array( __NAMESPACE__ . '\\Context', 'report' );
		foreach ( $log as $item ) {
			call_user_func( $report, $item['code'], ( $post > 0 ? array( 'post' => $post ) : array() ) + $item['data'], $item['level'] );
		}
		return $data;
	}

	/** @internal Para pruebas. */
	public static function reset(): void {
		self::$targets = array();
	}

	private static function walk( array $elements, callable $known, array &$log, int $depth ): array {
		if ( $depth > self::MAX_DEPTH ) {
			return $elements;
		}
		$converter = self::CONVERTER;
		$registry  = self::REGISTRY;
		foreach ( $elements as $index => $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$type = isset( $element['widgetType'] ) && is_string( $element['widgetType'] ) ? $element['widgetType'] : '';
			if ( 'widget' === ( $element['elType'] ?? '' ) && '' !== $type ) {
				$target = (string) $registry::target( $type );
				if ( '' !== $target ) {
					$id = isset( $element['id'] ) && is_scalar( $element['id'] ) ? (string) $element['id'] : '';
					if ( ! call_user_func( $known, $target ) ) {
						$log[] = self::entry( 'pro.target_missing', array( 'widget' => $type, 'destino' => $target, 'elemento' => $id ) );
					} else {
						$codes     = array();
						$converted = $converter::element( $element, $codes );
						if ( is_array( $converted ) && $converted['widgetType'] !== $type ) {
							$element = $converted;
							$log[]   = self::entry( 'pro.converted', array( 'widget' => $type, 'destino' => $target, 'elemento' => $id ) );
							foreach ( (array) $codes as $code ) {
								$log[] = self::code_entry( (array) $code, $type, $target, $id );
							}
						}
					}
				}
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = self::walk( $element['elements'], $known, $log, $depth + 1 );
			}
			$elements[ $index ] = $element;
		}
		return $elements;
	}

	/** Un código del traductor con su clave y su motivo; uno que no empiece por `pro.` se informa como sin declarar. */
	private static function code_entry( array $code, string $type, string $target, string $id ): array {
		$name = isset( $code['code'] ) && is_string( $code['code'] ) && 1 === preg_match( '/^pro\.[a-z0-9_.-]{1,40}$/', $code['code'] ) ? $code['code'] : 'pro.unmapped';
		$data = array(
			'widget'   => $type,
			'destino'  => $target,
			'elemento' => $id,
		);
		if ( isset( $code['key'] ) && is_scalar( $code['key'] ) && '' !== (string) $code['key'] ) {
			$data['ajuste'] = (string) $code['key'];
		}
		if ( isset( $code['reason'] ) && is_scalar( $code['reason'] ) && '' !== (string) $code['reason'] ) {
			$data['motivo'] = (string) $code['reason'];
		}
		return self::entry( $name, $data );
	}

	private static function entry( string $code, array $data ): array {
		return array(
			'code'  => $code,
			'level' => self::LEVELS[ $code ] ?? Part_Context::WARNING,
			'data'  => $data,
		);
	}
}
