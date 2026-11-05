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
 */
final class Pro_Convert {
	const CONVERTER = '\\Digitalisimo\\Elements\\Convert\\Converter';
	const REGISTRY  = '\\Digitalisimo\\Elements\\Convert\\Registry';

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
	);

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
		if ( empty( $data['elements'] ) || ! is_array( $data['elements'] ) || ! self::available() ) {
			return $data;
		}
		list( $elements, $log ) = self::tree( $data['elements'] );
		if ( ! $log ) {
			return $data;
		}
		$data['elements'] = $elements;
		$post             = is_object( $document ) && method_exists( $document, 'get_main_id' ) ? (int) $document->get_main_id() : 0;
		$report           = null !== $report ? $report : array( __NAMESPACE__ . '\\Context', 'report' );
		foreach ( $log as $item ) {
			call_user_func( $report, $item['code'], ( $post > 0 ? array( 'post' => $post ) : array() ) + $item['data'], $item['level'] );
		}
		return $data;
	}

	/** ¿Está registrado el widget propio en este sitio? Sin núcleo cargado se responde que sí (lo decide `Preserve`). */
	public static function target_registered( string $widget ): bool {
		if ( ! isset( self::$targets[ $widget ] ) ) {
			self::$targets[ $widget ] = Preserve::is_registered( 'widget', $widget );
		}
		return self::$targets[ $widget ];
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
