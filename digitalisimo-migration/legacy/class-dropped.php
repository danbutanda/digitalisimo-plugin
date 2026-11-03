<?php
namespace Digitalisimo\Elements\Legacy;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-retire.php';

/**
 * Lo que «Migrar» no convierte de las extensiones de Element Pack (F8, §3.C y V.9): las 10 extensiones descartadas
 * con la aprobación del propietario (2026-11-01) y las claves sin prefijo que podrían ser de otro plugin. No cambia
 * ningún documento: lo encuentra y lo anota en el informe de F8 (`Report`, códigos `ext.dropped.*` y
 * `ext.ambiguous`), que lo presenta en el análisis previo y en el informe final de cada sitio y de la red.
 *
 * Una extensión se da por usada cuando su interruptor (o su repetidor, o su código) tiene valor en el elemento o en los
 * ajustes de la página; un interruptor atómico (v4) apagado, `{"$$type":"boolean","value":false}`, no cuenta. Las
 * claves sin prefijo (`section_particles_on`…) sólo cuentan con una señal de Element Pack (`Effects\Dispatcher::signal()`,
 * C2-18). La conversión (`Effects\Dispatcher`, `Effects\Page`) retira las claves de las descartadas; lo retirado se sigue
 * leyendo de la copia original del documento (`_digitalisimo_ep_backup`) y de la de sus ajustes de página
 * (`_digitalisimo_ep_backup_page`), así que volver a pulsar Migrar o migrar la red no lo borra del informe.
 *
 * La tabla sale de `effects/keys.php` (una sola fuente con las 18 que sí se convierten): `extensions()`.
 *
 * Es información, no un asunto pendiente: el efecto no tiene equivalente y su dato queda en la copia.
 */
final class Dropped {
	/** Ajustes listados por documento y documentos listados (lo demás se cuenta): el informe vive en una opción. */
	const MAX_ITEMS     = 12;
	const MAX_DOCUMENTS = 100;

	/** Presentación común del análisis previo y del informe (en el sitio, en la red y en la terminal). */
	const INTRO = 'Efectos heredados sin equivalente en DIGITALÍSIMO Elements (descartados por su accesibilidad, su rendimiento, su privacidad o su licencia): al convertir se retiran del elemento o de los ajustes de la página y quedan en la copia original del documento, así que siguen en la lista después de migrar. Revisa cómo se ven estos documentos y, si hace falta, recrea el efecto con las opciones propias.';

	/** Copia original de un documento convertido (la misma meta que Migration::BACKUP). */
	const BACKUP = '_digitalisimo_ep_backup';

	/** Copia de los ajustes de la página antes de convertirlos (`{time, data}` en JSON, codificado una vez). */
	const BACKUP_PAGE = '_digitalisimo_ep_backup_page';

	/** Widgets de Prime Slider en `_elementor_data`. */
	const PRIME_SLIDER = '/^prime-slider-[a-z0-9-]+$/';

	/** @var array|null tabla cargada. */
	private static $extensions = null;

	/**
	 * Las 10 extensiones descartadas: nombre visible, claves que delatan su uso, si viven en los ajustes de la página y
	 * si su interruptor no lleva prefijo (sólo cuenta con una señal de Element Pack).
	 *
	 * @return array<string, array{label:string, keys:string[], page:bool, bare:bool}>
	 */
	public static function extensions() {
		if ( null === self::$extensions ) {
			self::$extensions = array();
			foreach ( self::table() as $slug => $spec ) {
				if ( 'dropped' !== ( $spec['treatment'] ?? '' ) ) {
					continue;
				}
				$bare = false;
				foreach ( (array) $spec['switch'] as $switch ) {
					foreach ( (array) ( $spec['bare'] ?? array() ) as $item ) {
						$bare = $bare || $switch === $item || ( '_' === substr( $item, -1 ) && 0 === strpos( $switch, $item ) );
					}
				}
				self::$extensions[ $slug ] = array(
					'label' => (string) $spec['label'],
					'keys'  => array_values( (array) $spec['switch'] ),
					'page'  => 'page' === ( $spec['scope'][0] ?? '' ),
					'bare'  => $bare,
				);
			}
		}
		return self::$extensions;
	}

	/** Interruptores sin prefijo de las extensiones que sí se convierten (sólo para `ext.ambiguous`). */
	private static function ambiguous_switches() {
		$out = array();
		foreach ( self::table() as $slug => $spec ) {
			if ( 'dropped' === ( $spec['treatment'] ?? '' ) || ! empty( $spec['generic'] ) || 'page' === ( $spec['scope'][0] ?? '' ) ) {
				continue;
			}
			foreach ( (array) $spec['switch'] as $switch ) {
				foreach ( (array) ( $spec['bare'] ?? array() ) as $item ) {
					if ( $switch === $item || ( '_' === substr( $item, -1 ) && 0 === strpos( $switch, $item ) ) ) {
						$out[ $switch ] = $slug;
					}
				}
			}
		}
		return $out;
	}

	/** `effects/keys.php`. */
	private static function table() {
		if ( class_exists( __NAMESPACE__ . '\\Effects\\Dispatcher' ) ) {
			return Effects\Dispatcher::keys();
		}
		static $keys = null;
		if ( null === $keys ) {
			$keys = include __DIR__ . '/effects/keys.php';
			$keys = is_array( $keys ) ? $keys : array();
		}
		return $keys;
	}

	/* ---------------------------------------------------------------- Detección (pura) */

	/**
	 * ¿Tiene valor el ajuste? Un interruptor encendido, un código o un repetidor con filas; nunca vacío, «no» ni un
	 * interruptor atómico apagado (`{"$$type":"boolean","value":false}`).
	 */
	private static function used( $value ) {
		while ( is_array( $value ) && array_key_exists( '$$type', $value ) && array_key_exists( 'value', $value ) ) {
			$value = $value['value'];
		}
		if ( is_array( $value ) ) {
			return (bool) $value;
		}
		if ( is_bool( $value ) || null === $value ) {
			return (bool) $value;
		}
		$value = trim( (string) $value );
		return '' !== $value && 'no' !== $value && 'false' !== $value;
	}

	/** ¿Alguna clave con prefijo heredado en el elemento? (señal de Element Pack en el propio elemento) */
	private static function prefixed( array $settings ) {
		foreach ( array_keys( $settings ) as $key ) {
			if ( preg_match( '/^(ep_|element_pack_|bdt_)/', (string) $key ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Extensiones descartadas de una lista de elementos y sus hijos, en el orden del documento: una por extensión y
	 * elemento, con la primera clave que la delata. `$signal`: el documento tiene otra señal de Element Pack (sin ella,
	 * un interruptor sin prefijo sólo cuenta si el propio elemento tiene una clave con prefijo heredado).
	 *
	 * @return array<int, array{ext:string, key:string, el:string, id:string}>
	 */
	public static function scan_elements( array $elements, $signal = true ) {
		$out = array();
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$payload = self::marker_payload( $element );
			if ( null !== $payload ) {
				// Marcador de K (`digitalisimo-unconverted`): lo heredado está en su carga y la conversión lo tratará.
				$out = array_merge( $out, self::scan_elements( array( $payload ), $signal ) );
				continue;
			}
			$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
			$type     = self::type_of( $element );
			$bare_ok  = $signal || self::prefixed( $settings );
			foreach ( self::extensions() as $ext => $spec ) {
				if ( ! empty( $spec['page'] ) || ( ! empty( $spec['bare'] ) && ! $bare_ok ) ) {
					continue;
				}
				foreach ( $spec['keys'] as $key ) {
					if ( array_key_exists( $key, $settings ) && self::used( $settings[ $key ] ) ) {
						$out[] = array( 'ext' => $ext, 'key' => $key, 'el' => $type, 'id' => (string) ( $element['id'] ?? '' ) );
						break;
					}
				}
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$out = array_merge( $out, self::scan_elements( $element['elements'], $signal ) );
			}
		}
		return $out;
	}

	/**
	 * Elemento original guardado en un marcador de K (`digitalisimo-unconverted`, carga
	 * `settings.digitalisimo_unconverted.element`: `Preserve::marker()`), o null si no es un marcador.
	 */
	private static function marker_payload( array $element ) {
		if ( 'widget' !== ( $element['elType'] ?? '' ) || 'digitalisimo-unconverted' !== ( $element['widgetType'] ?? '' ) ) {
			return null;
		}
		$preserve = 'Digitalisimo\\Elements\\Import\\Kits\\Preserve';
		$setting  = class_exists( $preserve, false ) && defined( $preserve . '::SETTING' ) ? (string) constant( $preserve . '::SETTING' ) : 'digitalisimo_unconverted';
		$payload  = $element['settings'][ $setting ]['element'] ?? null;
		return is_array( $payload ) ? $payload : null;
	}

	/** Tipo visible de un elemento (el widget, o el tipo de elemento). */
	private static function type_of( array $element ) {
		$type = 'widget' === ( $element['elType'] ?? '' ) ? (string) ( $element['widgetType'] ?? 'widget' ) : (string) ( $element['elType'] ?? '' );
		return '' !== $type ? $type : 'elemento';
	}

	/**
	 * Interruptores sin prefijo de las extensiones que sí se convierten (`section_sticky_on`…) en elementos sin ninguna
	 * señal de Element Pack: pueden ser de otro plugin y la conversión no los toca (`ext.ambiguous`).
	 *
	 * @return array<int, array{ext:string, key:string, el:string, id:string}>
	 */
	public static function scan_ambiguous( array $elements ) {
		$out      = array();
		$switches = self::ambiguous_switches();
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$payload = self::marker_payload( $element );
			if ( null !== $payload ) {
				$out = array_merge( $out, self::scan_ambiguous( array( $payload ) ) );
				continue;
			}
			$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
			if ( ! self::prefixed( $settings ) ) {
				foreach ( $switches as $key => $ext ) {
					if ( array_key_exists( $key, $settings ) && self::used( $settings[ $key ] ) ) {
						$out[] = array( 'ext' => $ext, 'key' => $key, 'el' => self::type_of( $element ), 'id' => (string) ( $element['id'] ?? '' ) );
					}
				}
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$out = array_merge( $out, self::scan_ambiguous( $element['elements'] ) );
			}
		}
		return $out;
	}

	/**
	 * Extensiones descartadas de los ajustes de la página (`_elementor_page_settings`).
	 *
	 * @return array<int, array{ext:string, key:string, el:string, id:string}>
	 */
	public static function scan_page( array $settings ) {
		$out = array();
		foreach ( self::extensions() as $ext => $spec ) {
			if ( empty( $spec['page'] ) ) {
				continue;
			}
			foreach ( $spec['keys'] as $key ) {
				if ( array_key_exists( $key, $settings ) && self::used( $settings[ $key ] ) ) {
					$out[] = array( 'ext' => $ext, 'key' => $key, 'el' => '', 'id' => '' );
					break;
				}
			}
		}
		return $out;
	}

	/** ¿Tiene el documento una señal de Element Pack para las claves sin prefijo? (C2-18) */
	private static function signal( $post_id ) {
		if ( class_exists( __NAMESPACE__ . '\\Effects\\Dispatcher' ) ) {
			return Effects\Dispatcher::signal( (int) $post_id );
		}
		return false;
	}

	/**
	 * Extensiones descartadas del documento indicado: sus ajustes de página (los de hoy y su copia), lo que su copia
	 * original tenía antes de convertir (en el orden del documento) y lo que guarda hoy, sin repetir.
	 */
	public static function scan_post( $post_id ) {
		$post_id = (int) $post_id;
		$page    = get_metadata( 'post', $post_id, '_elementor_page_settings', true );
		$saved   = self::saved( $post_id );
		$signal  = self::signal( $post_id );
		$found   = array_merge(
			is_array( $page ) ? self::scan_page( $page ) : array(),
			self::scan_page( self::saved_page( $post_id ) ),
			null !== $saved ? self::scan_elements( $saved, $signal ) : array(),
			self::scan_elements( self::decode( get_metadata( 'post', $post_id, '_elementor_data', true ) ), $signal )
		);
		return self::unique( $found );
	}

	/** Sin repetir (misma extensión, clave y elemento), en el orden de llegada. */
	private static function unique( array $found ) {
		$out  = array();
		$seen = array();
		foreach ( $found as $item ) {
			$signature = self::signature( $item );
			if ( ! isset( $seen[ $signature ] ) ) {
				$seen[ $signature ] = true;
				$out[]              = $item;
			}
		}
		return $out;
	}

	/**
	 * Lo que la conversión de ahora retirará y la copia original del documento no recoge: con una copia ya guardada por
	 * una conversión anterior, lo añadido después. Sin copia, nada: la conversión la crea con estos mismos datos, y una
	 * conversión que no cambia nada deja las claves donde están.
	 *
	 * @return array<int, array{ext:string, key:string, el:string, id:string}>
	 */
	public static function uncovered( $post_id ) {
		$saved = self::saved( (int) $post_id );
		if ( null === $saved ) {
			return array();
		}
		$signal = self::signal( (int) $post_id );
		$known  = array();
		foreach ( self::scan_elements( $saved, $signal ) as $item ) {
			$known[ self::signature( $item ) ] = true;
		}
		$out = array();
		foreach ( self::scan_elements( self::decode( get_metadata( 'post', (int) $post_id, '_elementor_data', true ) ), $signal ) as $item ) {
			if ( ! isset( $known[ self::signature( $item ) ] ) ) {
				$out[] = $item;
			}
		}
		return $out;
	}

	/** Elementos de la copia original del documento, o null si no tiene copia. */
	private static function saved( $post_id ) {
		$raw = get_metadata( 'post', (int) $post_id, self::BACKUP, true );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return null;
		}
		$backup = json_decode( $raw, true );
		return is_array( $backup ) && isset( $backup['data'] ) ? self::decode( $backup['data'] ) : null;
	}

	/**
	 * Ajustes de la página de su copia (`_digitalisimo_ep_backup_page`: `{time, data}` en JSON con `data` como objeto;
	 * también se acepta `data` como texto JSON). Vacío sin copia.
	 */
	private static function saved_page( $post_id ) {
		$raw = get_metadata( 'post', (int) $post_id, self::BACKUP_PAGE, true );
		if ( is_string( $raw ) && '' !== $raw ) {
			$raw = json_decode( $raw, true );
		}
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$data = array_key_exists( 'data', $raw ) ? $raw['data'] : $raw;
		if ( is_string( $data ) ) {
			$data = json_decode( $data, true );
		}
		return is_array( $data ) ? $data : array();
	}

	/** `_elementor_data` (texto JSON o ya decodificado) como lista de elementos. */
	private static function decode( $raw ) {
		$data = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : null );
		return is_array( $data ) ? $data : array();
	}

	/* ---------------------------------------------------------------- Informe de F8 */

	/**
	 * Anota en el informe de F8 lo que este documento tenía y no se convierte: los descartes (`ext.dropped.<extensión>`,
	 * de los datos de hoy y de sus copias) y las claves sin prefijo sin señal de Element Pack (`ext.ambiguous`). El
	 * mismo detalle que `Effects\Dispatcher` (elemento, clave), así que el informe nunca lo cuenta dos veces.
	 *
	 * @return int anotaciones.
	 */
	public static function report( $post_id ) {
		$post_id = (int) $post_id;
		$items   = self::report_items( $post_id );
		if ( $items && class_exists( __NAMESPACE__ . '\\Report' ) ) {
			foreach ( $items as $item ) {
				Report::add( $post_id, $item[0], $item[1] );
			}
		}
		return count( $items );
	}

	/**
	 * Lo que report() anotaría: `[código, detalle]`.
	 *
	 * @return array<int, array{0:string,1:array}>
	 */
	public static function report_items( $post_id ) {
		$post_id = (int) $post_id;
		$out     = array();
		foreach ( self::scan_post( $post_id ) as $item ) {
			$out[] = array( 'ext.dropped.' . str_replace( '-', '_', $item['ext'] ), array( 'element' => self::item_where( $item ), 'name' => $item['key'] ) );
		}
		if ( ! self::signal( $post_id ) ) {
			$labels = self::table();
			foreach ( self::unique( self::scan_ambiguous( self::decode( get_metadata( 'post', $post_id, '_elementor_data', true ) ) ) ) as $item ) {
				$out[] = array( 'ext.ambiguous', array( 'element' => self::item_where( $item ), 'type' => (string) ( $labels[ $item['ext'] ]['label'] ?? $item['ext'] ), 'name' => $item['key'] ) );
			}
		}
		return $out;
	}

	/** «heading #abc» o «Ajustes de la página». */
	private static function item_where( array $item ) {
		if ( '' === (string) $item['el'] ) {
			return function_exists( '__' ) ? __( 'Ajustes de la página', 'digitalisimo-migration' ) : 'Ajustes de la página';
		}
		return trim( (string) $item['el'] . ( '' !== (string) $item['id'] ? ' #' . $item['id'] : '' ) );
	}

	/* ---------------------------------------------------------------- Conjuntos por documento */

	/** Conjunto vacío: documentos (con sus ajustes y cuántos más hay) y documentos que ya no caben. */
	public static function none() {
		return array( 'docs' => array(), 'omitted' => 0 );
	}

	private static function signature( array $item ) {
		return ( $item['ext'] ?? '' ) . '|' . ( $item['key'] ?? '' ) . '|' . ( $item['id'] ?? '' );
	}

	/** Añade a un conjunto lo encontrado en un documento, sin repetir ni pasar de los topes. */
	public static function add( array $set, $post_id, array $items, $more = 0 ) {
		$set    += self::none();
		$post_id = (int) $post_id;
		if ( ! $items && ! $more ) {
			return $set;
		}
		if ( ! isset( $set['docs'][ $post_id ] ) ) {
			if ( count( $set['docs'] ) >= self::MAX_DOCUMENTS ) {
				++$set['omitted'];
				return $set;
			}
			$set['docs'][ $post_id ] = array( 'items' => array(), 'more' => 0 );
		}
		$doc  = $set['docs'][ $post_id ];
		$seen = array();
		foreach ( $doc['items'] as $item ) {
			$seen[ self::signature( $item ) ] = true;
		}
		$overflow = 0;
		foreach ( $items as $item ) {
			$signature = self::signature( $item );
			if ( isset( $seen[ $signature ] ) ) {
				continue;
			}
			$seen[ $signature ] = true;
			if ( count( $doc['items'] ) < self::MAX_ITEMS ) {
				$doc['items'][] = array( 'ext' => (string) $item['ext'], 'key' => (string) $item['key'], 'el' => (string) $item['el'], 'id' => (string) $item['id'] );
			} else {
				++$overflow;
			}
		}
		// Lo que no cupo en otra pasada (o en el conjunto que se une) puede coincidir con lo de ésta, y sus firmas no se
		// guardan: se cuenta lo mayor, nunca la suma, para no contar dos veces lo mismo.
		$doc['more']             = max( (int) $doc['more'], $overflow, (int) $more );
		$set['docs'][ $post_id ] = $doc;
		ksort( $set['docs'] );
		return $set;
	}

	/** Une dos conjuntos (lo anotado antes de convertir y lo que sigue guardado después). */
	public static function merge( array $a, array $b ) {
		$a += self::none();
		$b += self::none();
		foreach ( (array) $b['docs'] as $post_id => $doc ) {
			$a = self::add( $a, $post_id, (array) ( $doc['items'] ?? array() ), (int) ( $doc['more'] ?? 0 ) );
		}
		$a['omitted'] = max( (int) $a['omitted'], (int) $b['omitted'] );
		return $a;
	}

	/* ---------------------------------------------------------------- Sitio en curso */

	/**
	 * Documentos del sitio (no revisiones) cuyos datos, ajustes de página o copias nombran alguna extensión descartada
	 * o un interruptor sin prefijo de las que se convierten (para `ext.ambiguous`). Por trozos: los IDs mayores que
	 * `$after`, como mucho `$limit` (0: todos). Sin `SELECT DISTINCT`: una fila por entrada con `EXISTS`.
	 *
	 * @return int[]
	 */
	public static function documents( $after = 0, $limit = 0 ) {
		global $wpdb;
		$keys = array();
		foreach ( self::extensions() as $spec ) {
			$keys = array_merge( $keys, $spec['keys'] );
		}
		$keys  = array_values( array_unique( array_merge( $keys, array_keys( self::ambiguous_switches() ) ) ) );
		$live  = array();
		$saved = array();
		foreach ( $keys as $key ) {
			// Las claves van entre comillas en el JSON de los elementos, en los ajustes serializados y en la copia de la
			// página (JSON codificado una vez); en la copia original, ese JSON va dentro de otro y sus comillas llevan
			// barra invertida.
			$live[]  = '%' . $wpdb->esc_like( '"' . $key . '"' ) . '%';
			$saved[] = '%' . $wpdb->esc_like( '\\"' . $key . '\\"' ) . '%';
		}
		$any = static function ( $count ) {
			return '( ' . implode( ' OR ', array_fill( 0, $count, 'm.meta_value LIKE %s' ) ) . ' )';
		};
		$sql = "SELECT p.ID FROM {$wpdb->posts} p WHERE p.post_type <> 'revision' AND p.ID > %d AND EXISTS ( SELECT 1 FROM {$wpdb->postmeta} m WHERE m.post_id = p.ID AND ( ( m.meta_key IN ( '_elementor_data', '_elementor_page_settings', '" . self::BACKUP_PAGE . "' ) AND " . $any( count( $live ) ) . " ) OR ( m.meta_key = '" . self::BACKUP . "' AND " . $any( count( $saved ) ) . ' ) ) ) ORDER BY p.ID' . ( $limit ? ' LIMIT ' . absint( $limit ) : '' );
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( $sql, array_merge( array( (int) $after ), $live, $saved ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/** Análisis del sitio en curso: lo que guarda cada documento y lo que la conversión le retiró. */
	public static function analyze() {
		$set = self::none();
		foreach ( self::documents() as $post_id ) {
			$set = self::add( $set, $post_id, self::scan_post( $post_id ) );
		}
		return $set;
	}

	/** Cuántos documentos y ajustes tiene un conjunto. */
	public static function totals( array $set ) {
		$set     += self::none();
		$settings = 0;
		foreach ( (array) $set['docs'] as $doc ) {
			$settings += count( (array) ( $doc['items'] ?? array() ) ) + (int) ( $doc['more'] ?? 0 );
		}
		return array( 'documents' => count( (array) $set['docs'] ) + (int) $set['omitted'], 'settings' => $settings );
	}

	/**
	 * El conjunto para el informe: cada documento con su título y su enlace al editor, guardados para que el informe se
	 * pueda leer después aunque cambien.
	 *
	 * @return array<int, array{title:string, link:string, items:array, more:int}>
	 */
	public static function describe( array $set ) {
		$out = array();
		foreach ( (array) ( $set['docs'] ?? array() ) as $post_id => $doc ) {
			$title           = function_exists( 'get_the_title' ) ? (string) get_the_title( (int) $post_id ) : '';
			$out[ $post_id ] = array(
				'title' => '' !== $title ? $title : '#' . (int) $post_id,
				'link'  => admin_url( 'post.php?post=' . (int) $post_id . '&action=elementor' ),
				'items' => (array) ( $doc['items'] ?? array() ),
				'more'  => (int) ( $doc['more'] ?? 0 ),
			);
		}
		return $out;
	}

	/** Inventario de widgets de Prime Slider del sitio: por tipo, elementos y documentos (sin revisiones). */
	public static function prime_slider() {
		global $wpdb;
		$ids   = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p WHERE p.post_type <> 'revision' AND EXISTS ( SELECT 1 FROM {$wpdb->postmeta} m WHERE m.post_id = p.ID AND m.meta_key = '_elementor_data' AND m.meta_value LIKE %s ) ORDER BY p.ID", '%' . $wpdb->esc_like( '"widgetType":"prime-slider-' ) . '%' ) ) );
		$types = array();
		$docs  = 0;
		foreach ( $ids as $post_id ) {
			$raw  = get_metadata( 'post', $post_id, '_elementor_data', true );
			$data = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
			$seen = array();
			self::count_prime_slider( is_array( $data ) ? $data : array(), $types, $seen );
			$docs += $seen ? 1 : 0;
		}
		ksort( $types );
		return array( 'types' => $types, 'elements' => array_sum( array_column( $types, 'elements' ) ), 'documents' => $docs );
	}

	private static function count_prime_slider( array $elements, array &$types, array &$seen ) {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$type = (string) ( $element['widgetType'] ?? '' );
			if ( 'widget' === ( $element['elType'] ?? '' ) && preg_match( self::PRIME_SLIDER, $type ) ) {
				$types[ $type ] = $types[ $type ] ?? array( 'elements' => 0, 'documents' => 0 );
				++$types[ $type ]['elements'];
				if ( empty( $seen[ $type ] ) ) {
					++$types[ $type ]['documents'];
					$seen[ $type ] = true;
				}
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				self::count_prime_slider( $element['elements'], $types, $seen );
			}
		}
	}

	/**
	 * Plugins de la familia que la migración detectaba sin desactivarlos (hasta 1.0.20). Desde F8 el Element Pack Addon
	 * y Prime Slider se retiran con los demás (`Retire`, frente A) y sus widgets se convierten: no queda ninguno. Se
	 * conservan las firmas para los informes guardados por versiones anteriores.
	 *
	 * @return array
	 */
	public static function kept() {
		return array();
	}

	/** Sin plugins de la familia pendientes (ver kept()). */
	public static function kept_pending( array $row ) {
		return false;
	}

	/** Sin contenido oculto por plugins de la familia (ver kept()). */
	public static function kept_hidden( array $row ) {
		return false;
	}

	/* ---------------------------------------------------------------- Presentación (análisis, informe, red y terminal) */

	/** Nombre visible de una extensión heredada. */
	public static function label( $ext ) {
		return (string) ( self::extensions()[ $ext ]['label'] ?? $ext );
	}

	/**
	 * Tabla por documento: efecto heredado, ajuste detectado y elemento. $docs viene de describe() (informe) o de un
	 * conjunto con títulos ya puestos.
	 */
	public static function render( array $docs, $omitted, $intro ) {
		if ( ! $docs && ! $omitted ) {
			return '';
		}
		$html = '<p>' . esc_html( (string) $intro ) . '</p><table class="widefat striped digi-migrate__dropped" style="max-width:860px"><thead><tr><th>Documento</th><th>Efecto heredado</th><th>Ajuste detectado</th><th>Elemento</th></tr></thead><tbody>';
		foreach ( $docs as $post_id => $doc ) {
			$title = (string) ( $doc['title'] ?? '#' . (int) $post_id );
			$link  = (string) ( $doc['link'] ?? admin_url( 'post.php?post=' . (int) $post_id . '&action=elementor' ) );
			$cell  = '<td><a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a></td>';
			foreach ( (array) ( $doc['items'] ?? array() ) as $item ) {
				$where = '' === (string) ( $item['el'] ?? '' ) ? esc_html( 'Ajustes de la página' ) : '<code>' . esc_html( (string) $item['el'] ) . '</code>' . ( '' !== (string) ( $item['id'] ?? '' ) ? ' ' . esc_html( '#' . $item['id'] ) : '' );
				$html .= '<tr>' . $cell . '<td>' . esc_html( self::label( (string) ( $item['ext'] ?? '' ) ) ) . '</td><td><code>' . esc_html( (string) ( $item['key'] ?? '' ) ) . '</code></td><td>' . $where . '</td></tr>';
			}
			if ( ! empty( $doc['more'] ) ) {
				$html .= '<tr>' . $cell . '<td colspan="3">' . esc_html( sprintf( 'y %d ajustes heredados más en este documento', (int) $doc['more'] ) ) . '</td></tr>';
			}
		}
		$html .= '</tbody></table>';
		if ( $omitted ) {
			$html .= '<p>' . esc_html( sprintf( 'Y %d documentos más con efectos heredados sin equivalente (la lista se corta en %d).', (int) $omitted, self::MAX_DOCUMENTS ) ) . '</p>';
		}
		return $html;
	}

	/** Sin avisos de plugins de la familia (ver kept()). */
	public static function kept_messages( array $rows ) {
		return array( 'pending' => array(), 'info' => array(), 'idle' => array() );
	}

	/** Sin plugins de la familia instalados y sin uso que anunciar (ver kept()). */
	public static function idle_message( array $names ) {
		return '';
	}

	/** Sin aviso de red de plugins de la familia (ver kept()). */
	public static function kept_notice( array $lines, $more ) {
		return '';
	}

	/** Una línea por sitio para la pantalla de red y la terminal: efectos descartados. */
	public static function summary( array $set, array $kept = array() ) {
		$totals = self::totals( $set );
		return $totals['documents'] ? sprintf( 'efectos heredados sin equivalente: %d en %d documentos', $totals['settings'], $totals['documents'] ) : '';
	}
}
