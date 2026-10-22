<?php
namespace Digitalisimo\Elements\Design\Theme_Builder;

use Digitalisimo\Elements\Design\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Prioridad manual de las plantillas del Theme Builder (F7a, frente J; §3.5 y D20 de la especificación).
 *
 * - **Ajuste** `digitalisimo_tb_priority` (NUMBER, 1–99; vacío = automática) en las plantillas con ubicación no
 *   múltiple: cabecera, pie, entrada, archivo, búsqueda, 404, producto y archivo de producto. Viaja con la plantilla
 *   (sus ajustes de página) y, además, como campo `priority` de `digitalisimo_elements_kit_theme_builder_fields`.
 * - **Índice** en la opción `digitalisimo_elements_tb_priority` (`{ id: n }`, con carga automática y que existe
 *   siempre, `[]`). Se lee sólo si figura en `wp_load_alloptions()`: un sitio sin la opción no hace ninguna consulta.
 *   Lo mantiene `reindex()` al guardar el documento, al ir a la papelera, al salir de ella, al borrarse y al escribirse
 *   `_elementor_page_settings` de una plantilla por cualquier vía (copias de red, duplicador, WP-CLI, kits).
 * - **Aplicación:** la única línea nueva del derivado de PRO, en `Conditions_Manager::get_location_templates()` justo
 *   después del `asort`, pasa las candidatas por el filtro `digitalisimo_elements_theme_builder_priorities`. Sin
 *   ninguna manual entre ellas, `apply()` devuelve el mismo array (`===`): el orden es el de hoy, también en PHP 7.4.
 *   Con alguna, su número sustituye al calculado y el orden es estable y determinista: número ascendente; a igual
 *   número, primero la manual; entre manuales iguales, el ID más alto. Un `exclude` ya quitó antes la plantilla, así
 *   que la prioridad nunca la recupera.
 *
 * Es igual para todos los visitantes: no toca la caché de página, la de elementos ni la de condiciones.
 */
final class Priority {
	const SETTING = 'digitalisimo_tb_priority';
	const OPTION  = 'digitalisimo_elements_tb_priority';
	const FILTER  = 'digitalisimo_elements_theme_builder_priorities';
	const FIELD   = 'priority';
	const META    = '_elementor_page_settings';
	const CPT     = 'elementor_library';
	const MIN     = 1;
	const MAX     = 99;

	/** Ubicaciones del núcleo, no múltiples aunque el tema activo no las registre. */
	const CORE_LOCATIONS = array( 'header', 'footer', 'single', 'archive' );

	const THEME_DOCUMENT = '\ElementorPro\Modules\ThemeBuilder\Documents\Theme_Document';
	const THEME_BUILDER  = '\ElementorPro\Modules\ThemeBuilder\Module';

	/** @var array<int,array<int,int>> Índice leído, por sitio. */
	private static $memo = array();

	/** @var bool */
	private static $initialized = false;

	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		$symbols = array(
			'Elementor\Controls_Manager',
			'Elementor\Core\Base\Document::start_injection',
			'ElementorPro\Modules\ThemeBuilder\Documents\Theme_Document::get_location',
			'ElementorPro\Modules\ThemeBuilder\Module::instance',
			'wp_load_alloptions()',
		);
		if ( ! Module::requires( 'theme-builder', $symbols ) ) {
			return;
		}
		self::$initialized = true;
		add_filter( self::FILTER, array( __CLASS__, 'apply' ), 10, 2 );
		add_action( 'elementor/documents/register_controls', array( __CLASS__, 'register_controls' ) );
		add_action( 'elementor/document/after_save', array( __CLASS__, 'after_save' ), 10, 1 );
		add_action( 'trashed_post', array( __CLASS__, 'on_post' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'on_post' ) );
		add_action( 'deleted_post', array( __CLASS__, 'on_post' ) );
		add_action( 'added_post_meta', array( __CLASS__, 'on_meta' ), 10, 3 );
		add_action( 'updated_post_meta', array( __CLASS__, 'on_meta' ), 10, 3 );
		add_action( 'deleted_post_meta', array( __CLASS__, 'on_meta' ), 10, 3 );
		add_filter( 'digitalisimo_elements_kit_theme_builder_fields', array( __CLASS__, 'kit_fields' ), 10, 2 );
		add_action( 'admin_init', array( __CLASS__, 'install' ) );
	}

	/**
	 * Crea la opción vacía con carga automática si aún no existe (primera petición de administración tras actualizar).
	 * Nunca en el front: allí la lectura protegida ya no consulta nada sin la opción.
	 */
	public static function install(): void {
		$all = wp_load_alloptions();
		if ( is_array( $all ) && array_key_exists( self::OPTION, $all ) ) {
			return;
		}
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, array(), '', true );
		} else {
			// Existe sin carga automática: la lectura protegida la ignoraría.
			update_option( self::OPTION, self::clean( get_option( self::OPTION, array() ) ), true );
		}
		self::$memo = array();
	}

	/**
	 * Filtro del derivado de PRO. Puro salvo la lectura del índice.
	 *
	 * @param array  $priorities `{ id_plantilla: prioridad }`, ya ordenado por `asort`.
	 * @param string $location   Ubicación.
	 */
	public static function apply( array $priorities, string $location = '' ): array {
		if ( ! $priorities ) {
			return $priorities;
		}
		$index = self::index();
		if ( ! $index || ! array_intersect_key( $priorities, $index ) || self::is_multiple( $location ) ) {
			return $priorities;
		}
		$rows     = array();
		$position = 0;
		foreach ( $priorities as $id => $priority ) {
			$manual = isset( $index[ $id ] );
			$rows[] = array(
				'id'       => $id,
				'priority' => $manual ? $index[ $id ] : $priority,
				'order'    => $manual ? $index[ $id ] : (int) $priority,
				'manual'   => $manual,
				'position' => $position++,
			);
		}
		usort(
			$rows,
			static function ( array $a, array $b ): int {
				if ( $a['order'] !== $b['order'] ) {
					return $a['order'] <=> $b['order'];
				}
				if ( $a['manual'] !== $b['manual'] ) {
					return $a['manual'] ? -1 : 1;
				}
				if ( $a['manual'] ) {
					return (int) $b['id'] <=> (int) $a['id'];
				}
				return $a['position'] <=> $b['position'];
			}
		);
		$sorted = array();
		foreach ( $rows as $row ) {
			$sorted[ $row['id'] ] = $row['priority'];
		}
		return $sorted;
	}

	/**
	 * Recalcula la entrada de una plantilla en el índice.
	 *
	 * @param int        $template_id Plantilla.
	 * @param mixed|null $value       Valor recibido (campo de kit): se usa tal cual en lugar de releer los ajustes, que
	 *                                aún pueden no estar guardados. `null` = leer `_elementor_page_settings`.
	 */
	public static function reindex( int $template_id, $value = null ): void {
		if ( $template_id <= 0 ) {
			return;
		}
		$priority = 0;
		$status   = get_post_status( $template_id );
		if ( self::CPT === get_post_type( $template_id ) && false !== $status && 'trash' !== $status ) {
			if ( null === $value ) {
				$settings = get_post_meta( $template_id, self::META, true );
				$value    = is_array( $settings ) && isset( $settings[ self::SETTING ] ) ? $settings[ self::SETTING ] : null;
			}
			$priority = self::sanitize( $value );
		}
		$index = self::index();
		if ( $priority ) {
			if ( isset( $index[ $template_id ] ) && $index[ $template_id ] === $priority ) {
				return;
			}
			$index[ $template_id ] = $priority;
		} elseif ( isset( $index[ $template_id ] ) ) {
			unset( $index[ $template_id ] );
		} elseif ( self::stored() ) {
			return;
		}
		ksort( $index );
		update_option( self::OPTION, $index, true );
		self::$memo = array();
	}

	/** Índice saneado del sitio actual: `{ id: 1–99 }`. Vacío, sin consulta, si la opción no tiene carga automática. */
	public static function index(): array {
		$blog = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 0;
		if ( ! isset( self::$memo[ $blog ] ) ) {
			self::$memo[ $blog ] = self::stored() ? self::clean( get_option( self::OPTION, array() ) ) : array();
		}
		return self::$memo[ $blog ];
	}

	/** Número válido de prioridad manual (1–99), o 0 (automática). */
	public static function sanitize( $value ): int {
		if ( is_int( $value ) ) {
			$number = $value;
		} elseif ( is_string( $value ) && 1 === preg_match( '/^\s*\d{1,3}\s*$/', $value ) ) {
			$number = (int) $value;
		} elseif ( is_float( $value ) && floor( $value ) === $value ) {
			$number = (int) $value;
		} else {
			return 0;
		}
		return $number >= self::MIN && $number <= self::MAX ? $number : 0;
	}

	/** Control en los ajustes de documento de las plantillas con ubicación no múltiple. */
	public static function register_controls( $document ): void {
		if ( ! self::is_template( $document ) ) {
			return;
		}
		if ( null === $document->get_controls( 'post_status' ) && null === $document->get_controls( 'post_title' ) ) {
			return;
		}
		$document->start_injection(
			array(
				'of'       => 'post_status',
				'fallback' => array( 'of' => 'post_title' ),
			)
		);
		$document->add_control(
			self::SETTING,
			array(
				'label'       => 'Prioridad',
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'min'         => self::MIN,
				'max'         => self::MAX,
				'step'        => 1,
				'default'     => '',
				'placeholder' => 'Automática',
				'description' => 'Número menor, más prioridad. Las automáticas van de 5 (error 404) a 100 (todo el sitio); una entrada concreta es 20. Decide qué plantilla gana cuando varias se aplican a la misma página; una exclusión sigue mandando. Vacío: automática.',
				'separator'   => 'before',
			)
		);
		$document->end_injection();
	}

	/** `elementor/document/after_save`. */
	public static function after_save( $document ): void {
		if ( is_object( $document ) && method_exists( $document, 'get_main_id' ) ) {
			self::reindex( (int) $document->get_main_id() );
		}
	}

	/** Papelera, restauración y borrado. */
	public static function on_post( $post_id ): void {
		$post_id = (int) $post_id;
		$index   = self::index();
		if ( isset( $index[ $post_id ] ) || self::CPT === get_post_type( $post_id ) ) {
			self::reindex( $post_id );
		}
	}

	/** Escritura de `_elementor_page_settings` por cualquier vía. */
	public static function on_meta( $meta_id, $post_id, $meta_key = '' ): void {
		if ( self::META === $meta_key && self::CPT === get_post_type( (int) $post_id ) ) {
			self::reindex( (int) $post_id );
		}
	}

	/** Campo `priority` de la parte `theme-builder` de los kits. */
	public static function kit_fields( $fields, $template_id = 0 ): array {
		$fields                = is_array( $fields ) ? $fields : array();
		$fields[ self::FIELD ] = array(
			'export' => array( __CLASS__, 'kit_export' ),
			'import' => array( __CLASS__, 'kit_import' ),
		);
		return $fields;
	}

	/** Número de la plantilla de origen, o `null` si es automática. */
	public static function kit_export( $template_id ): ?int {
		$settings = get_post_meta( (int) $template_id, self::META, true );
		$priority = self::sanitize( is_array( $settings ) && isset( $settings[ self::SETTING ] ) ? $settings[ self::SETTING ] : null );
		return $priority ? $priority : null;
	}

	/**
	 * Aplica el número importado: lo escribe en los ajustes de la plantilla si faltan y reindexa con el valor recibido.
	 * En «sólo agregar» la plantilla es un borrador: no cambia nada hasta que se publica.
	 */
	public static function kit_import( $template_id, $value, $context = null ): void {
		$template_id = (int) $template_id;
		$priority    = self::sanitize( $value );
		if ( $template_id <= 0 || ! $priority ) {
			return;
		}
		$settings = get_post_meta( $template_id, self::META, true );
		if ( '' === $settings || array() === $settings || ( is_array( $settings ) && ! isset( $settings[ self::SETTING ] ) ) ) {
			$settings                  = is_array( $settings ) ? $settings : array();
			$settings[ self::SETTING ] = $priority;
			update_post_meta( $template_id, self::META, $settings );
		}
		self::reindex( $template_id, $priority );
	}

	/** ¿Es una plantilla del Theme Builder con ubicación no múltiple? */
	private static function is_template( $document ): bool {
		$class = self::THEME_DOCUMENT;
		if ( ! is_object( $document ) || ! class_exists( $class ) || ! $document instanceof $class ) {
			return false;
		}
		$location = (string) $document->get_location();
		if ( '' === $location ) {
			return false;
		}
		$settings = self::location_settings( $location );
		if ( null === $settings ) {
			return in_array( $location, self::CORE_LOCATIONS, true );
		}
		return empty( $settings['multiple'] );
	}

	/** Ubicación múltiple (popups, ganchos): se imprimen todas, así que el orden manual no aplica. */
	private static function is_multiple( string $location ): bool {
		if ( '' === $location ) {
			return false;
		}
		$settings = self::location_settings( $location );
		return is_array( $settings ) && ! empty( $settings['multiple'] );
	}

	/** Ajustes registrados de una ubicación, o `null`. */
	private static function location_settings( string $location ): ?array {
		$class = self::THEME_BUILDER;
		if ( ! class_exists( $class ) || ! method_exists( $class, 'instance' ) ) {
			return null;
		}
		$module  = call_user_func( array( $class, 'instance' ) );
		$manager = is_object( $module ) && method_exists( $module, 'get_locations_manager' ) ? $module->get_locations_manager() : null;
		if ( ! is_object( $manager ) || ! method_exists( $manager, 'get_location' ) ) {
			return null;
		}
		$settings = $manager->get_location( $location );
		return is_array( $settings ) ? $settings : null;
	}

	/** ¿Figura la opción en `alloptions`? Sólo entonces se lee (sin consulta). */
	private static function stored(): bool {
		$all = wp_load_alloptions();
		return is_array( $all ) && array_key_exists( self::OPTION, $all );
	}

	/** `{ id > 0: 1–99 }`; lo demás se descarta. */
	private static function clean( $raw ): array {
		$index = array();
		foreach ( is_array( $raw ) ? $raw : array() as $id => $priority ) {
			$id       = is_int( $id ) ? $id : ( is_string( $id ) && ctype_digit( $id ) ? (int) $id : 0 );
			$priority = self::sanitize( $priority );
			if ( $id > 0 && $priority ) {
				$index[ $id ] = $priority;
			}
		}
		return $index;
	}
}
