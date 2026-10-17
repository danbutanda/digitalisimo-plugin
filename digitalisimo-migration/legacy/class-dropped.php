<?php
namespace Digitalisimo\Elements\Legacy;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-retire.php';

/**
 * Lo que «Migrar» todavía no convierte y antes se perdía sin aviso: las extensiones de Element Pack sin equivalente en
 * DIGITALÍSIMO Elements, los widgets de Prime Slider y los plugins de la misma familia que la migración no desactiva
 * (Element Pack Addon y Prime Slider). No cambia ningún documento: lo encuentra, lo anota y lo presenta igual en el
 * análisis previo (Herramientas y Red → Ajustes → Migrar a DIGITALÍSIMO Elements, `wp digitalisimo-migration`) y en el
 * informe final de cada sitio.
 *
 * Una extensión se da por usada cuando su interruptor (o su repetidor, o su código) tiene valor en el elemento o en los
 * ajustes de la página. Al convertir, Native retira del elemento las claves con prefijo heredado (`ep_`,
 * `element_pack_`, `bdt_`); las que no lo tienen (`section_sticky_on`…) y las de los ajustes de la página quedan
 * guardadas sin efecto. Lo retirado se sigue leyendo de la copia original del documento (Migration::BACKUP), así que
 * volver a pulsar Migrar o migrar la red no lo borra del informe. Las extensiones que sí se convierten (visibilidad,
 * enlace del elemento, filtro de fondo, degradado del texto, sombra de imagen, flotación, anotación, figuras, máscaras
 * y separadores) no se listan aquí. La tabla sale de los módulos sin widget de Element Pack 10.0.1.
 *
 * Es información, no un asunto pendiente: Elementor conserva al guardar las claves que ningún control declara, así que
 * nadie podría hacerlas desaparecer y el sitio nunca quedaría terminado. Sólo cuenta como pendiente el contenido que hoy
 * no se ve (widgets de Prime Slider con su plugin sin activar), porque una persona sí puede resolverlo.
 */
final class Dropped {
	/** Por extensión: nombre visible, claves que delatan su uso y si vive en los ajustes de la página. */
	const EXTENSIONS = array(
		'tooltip'                      => array( 'label' => 'Información emergente', 'keys' => array( 'element_pack_widget_tooltip' ) ),
		'transform-effects'            => array( 'label' => 'Efectos de transformación', 'keys' => array( 'element_pack_widget_transform', 'element_pack_section_transform', 'element_pack_column_transform' ) ),
		'parallax-effects'             => array( 'label' => 'Efectos de paralaje', 'keys' => array( 'ep_parallax_effects_show' ) ),
		'background-parallax'          => array( 'label' => 'Paralaje del fondo', 'keys' => array( 'section_parallax_on' ) ),
		'background-image-parallax'    => array( 'label' => 'Paralaje de la imagen', 'keys' => array( 'ep_background_image_parallax_on' ) ),
		'image-parallax'               => array( 'label' => 'Capas de imágenes y colores con paralaje', 'keys' => array( 'section_parallax_elements', 'element_pack_sbgc_parallax_show', 'element_pack_sbc_parallax_show' ) ),
		'background-overlay'           => array( 'label' => 'Superposición del fondo', 'keys' => array( 'element_pack_background_overlay' ) ),
		'background-expand'            => array( 'label' => 'Fondo que se expande al desplazar', 'keys' => array( 'ep_bg_expand_enable' ) ),
		'section-sticky'               => array( 'label' => 'Sección fija al desplazar', 'keys' => array( 'section_sticky_on' ) ),
		'particles'                    => array( 'label' => 'Partículas en el fondo', 'keys' => array( 'section_particles_on' ) ),
		'equal-height'                 => array( 'label' => 'Igualar alturas', 'keys' => array( 'section_equal_height_on' ) ),
		'cursor-effects'               => array( 'label' => 'Efectos del cursor', 'keys' => array( 'element_pack_cursor_effects_show' ) ),
		'reveal-effects'               => array( 'label' => 'Efecto de revelado', 'keys' => array( 'element_pack_reveal_effects_enable' ) ),
		'ripple-effects'               => array( 'label' => 'Onda al pulsar', 'keys' => array( 'ep_ripple_enable' ) ),
		'scroll-fill-effect'           => array( 'label' => 'Relleno del texto al desplazar', 'keys' => array( 'ep_widget_sf_fx_enable' ) ),
		'scroll-box'                   => array( 'label' => 'Caja con desplazamiento propio', 'keys' => array( 'ep_scroll_box_enable' ) ),
		'threed-text'                  => array( 'label' => 'Texto en 3D', 'keys' => array( 'ep_threed_text_active' ) ),
		'tile-scroll'                  => array( 'label' => 'Mosaico al desplazar', 'keys' => array( 'element_pack_tile_scroll_show' ) ),
		'global-badge'                 => array( 'label' => 'Insignia', 'keys' => array( 'ep_global_badge_enable' ) ),
		'image-hover-effects'          => array( 'label' => 'Efectos de la imagen al pasar el cursor', 'keys' => array( 'ep_image_hover_effects_on' ) ),
		'confetti-effects'             => array( 'label' => 'Confeti', 'keys' => array( 'ep_widget_cf_confetti' ) ),
		'sound-effects'                => array( 'label' => 'Efectos de sonido', 'keys' => array( 'ep_sound_effects_active' ) ),
		'animated-gradient-background' => array( 'label' => 'Fondo con degradado animado', 'keys' => array( 'element_pack_agbg_show' ) ),
		'grid-line'                    => array( 'label' => 'Líneas de cuadrícula', 'keys' => array( 'ep_grid_line_enable' ), 'page' => true ),
		'hash-link'                    => array( 'label' => 'Enlaces a anclas de la página', 'keys' => array( 'ep_hash_link_enable' ), 'page' => true ),
		'content-protector'            => array( 'label' => 'Protección contra copiar el contenido', 'keys' => array( 'ep_content_protector_enable' ), 'page' => true ),
		'custom-js'                    => array( 'label' => 'JavaScript personalizado de la página', 'keys' => array( 'ep_custom_header_script', 'ep_custom_footer_script' ), 'page' => true ),
		'adblock-detector'             => array( 'label' => 'Detector de bloqueadores de anuncios', 'keys' => array( 'ep_adblock_detector_enable' ), 'page' => true ),
	);

	/** Ajustes listados por documento y documentos listados (lo demás se cuenta): el informe vive en una opción. */
	const MAX_ITEMS     = 12;
	const MAX_DOCUMENTS = 100;

	/** Presentación común del análisis previo y del informe (en el sitio, en la red y en la terminal). */
	const INTRO = 'Efectos heredados sin equivalente en DIGITALÍSIMO Elements: sin el plugin anterior no se aplican, y al convertir se retiran del elemento (los que no llevan prefijo heredado y los de los ajustes de la página quedan guardados sin efecto). En los documentos ya convertidos se leen de su copia original, así que siguen en la lista después de migrar. Revisa cómo se ven estos documentos y, si hace falta, recrea el efecto con las opciones propias.';

	/** Copia original de un documento convertido (la misma meta que Migration::BACKUP). */
	const BACKUP = '_digitalisimo_ep_backup';

	/** Widgets de Prime Slider en `_elementor_data`. */
	const PRIME_SLIDER = '/^prime-slider-[a-z0-9-]+$/';

	/* ---------------------------------------------------------------- Detección (pura) */

	/** ¿Tiene valor el ajuste? Un interruptor encendido, un código o un repetidor con filas; nunca vacío ni «no». */
	private static function used( $value ) {
		if ( is_array( $value ) ) {
			return (bool) $value;
		}
		$value = trim( (string) $value );
		return '' !== $value && 'no' !== $value;
	}

	/**
	 * Extensiones descartadas de una lista de elementos y sus hijos, en el orden del documento: una por extensión y
	 * elemento, con la primera clave que la delata.
	 *
	 * @return array<int, array{ext:string, key:string, el:string, id:string}>
	 */
	public static function scan_elements( array $elements ) {
		$out = array();
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
			$type     = 'widget' === ( $element['elType'] ?? '' ) ? (string) ( $element['widgetType'] ?? 'widget' ) : (string) ( $element['elType'] ?? '' );
			foreach ( self::EXTENSIONS as $ext => $spec ) {
				if ( ! empty( $spec['page'] ) ) {
					continue;
				}
				foreach ( $spec['keys'] as $key ) {
					if ( array_key_exists( $key, $settings ) && self::used( $settings[ $key ] ) ) {
						$out[] = array( 'ext' => $ext, 'key' => $key, 'el' => '' !== $type ? $type : 'elemento', 'id' => (string) ( $element['id'] ?? '' ) );
						break;
					}
				}
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$out = array_merge( $out, self::scan_elements( $element['elements'] ) );
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
		foreach ( self::EXTENSIONS as $ext => $spec ) {
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

	/**
	 * Extensiones descartadas del documento indicado: sus ajustes de página, lo que su copia original tenía antes de
	 * convertir (en el orden del documento) y lo que guarda hoy, sin repetir.
	 */
	public static function scan_post( $post_id ) {
		$post_id = (int) $post_id;
		$page    = get_metadata( 'post', $post_id, '_elementor_page_settings', true );
		$saved   = self::saved( $post_id );
		$found   = array_merge(
			is_array( $page ) ? self::scan_page( $page ) : array(),
			null !== $saved ? self::scan_elements( $saved ) : array(),
			self::scan_elements( self::decode( get_metadata( 'post', $post_id, '_elementor_data', true ) ) )
		);
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
	 * conversión que no cambia nada deja las claves donde están. Es lo único que la tarea tiene que anotar.
	 *
	 * @return array<int, array{ext:string, key:string, el:string, id:string}>
	 */
	public static function uncovered( $post_id ) {
		$saved = self::saved( (int) $post_id );
		if ( null === $saved ) {
			return array();
		}
		$known = array();
		foreach ( self::scan_elements( $saved ) as $item ) {
			$known[ self::signature( $item ) ] = true;
		}
		$out = array();
		foreach ( self::scan_elements( self::decode( get_metadata( 'post', (int) $post_id, '_elementor_data', true ) ) ) as $item ) {
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

	/** `_elementor_data` (texto JSON o ya decodificado) como lista de elementos. */
	private static function decode( $raw ) {
		$data = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : null );
		return is_array( $data ) ? $data : array();
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
	 * Documentos del sitio (no revisiones) cuyos datos, ajustes de página o copia original nombran alguna extensión
	 * descartada.
	 */
	public static function documents() {
		global $wpdb;
		$live  = array();
		$saved = array();
		foreach ( self::EXTENSIONS as $spec ) {
			foreach ( $spec['keys'] as $key ) {
				// Las claves van entre comillas tanto en el JSON de los elementos como en los ajustes serializados; en la
				// copia original, ese JSON va dentro de otro y sus comillas llevan barra invertida.
				$live[]  = '%' . $wpdb->esc_like( '"' . $key . '"' ) . '%';
				$saved[] = '%' . $wpdb->esc_like( '\\"' . $key . '\\"' ) . '%';
			}
		}
		$any = static function ( $count ) {
			return '( ' . implode( ' OR ', array_fill( 0, $count, 'm.meta_value LIKE %s' ) ) . ' )';
		};
		$sql = "SELECT DISTINCT m.post_id FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE p.post_type <> 'revision' AND ( ( m.meta_key IN ( '_elementor_data', '_elementor_page_settings' ) AND " . $any( count( $live ) ) . " ) OR ( m.meta_key = '" . self::BACKUP . "' AND " . $any( count( $saved ) ) . ' ) ) ORDER BY m.post_id';
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( $sql, array_merge( $live, $saved ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
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
		$ids   = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT m.post_id FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = '_elementor_data' AND p.post_type <> 'revision' AND m.meta_value LIKE %s ORDER BY m.post_id", '%' . $wpdb->esc_like( '"widgetType":"prime-slider-' ) . '%' ) ) );
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
	 * Plugins de la familia que la migración detecta y todavía no desactiva, con su estado en este sitio y, para Prime
	 * Slider, cuántos widgets suyos hay (también si el plugin ya no está instalado y quedan widgets que no se ven).
	 *
	 * @return array<int, array{slug:string, label:string, version:string, installed:bool, site:bool, network:bool, elements:int, documents:int}>
	 */
	public static function kept() {
		$rows = array();
		foreach ( Retire::kept_status() as $info ) {
			$rows[] = array( 'slug' => $info['slug'], 'label' => $info['label'], 'version' => $info['version'], 'installed' => true, 'site' => $info['site'], 'network' => $info['network'], 'elements' => 0, 'documents' => 0 );
		}
		$slider = self::prime_slider();
		if ( $slider['elements'] ) {
			$found = false;
			foreach ( $rows as $index => $row ) {
				if ( 'prime-slider' === $row['slug'] && ! $found ) {
					$rows[ $index ]['elements']  = (int) $slider['elements'];
					$rows[ $index ]['documents'] = (int) $slider['documents'];
					$found                       = true;
				}
			}
			if ( ! $found ) {
				$rows[] = array( 'slug' => 'prime-slider', 'label' => 'Prime Slider', 'version' => '', 'installed' => false, 'site' => false, 'network' => false, 'elements' => (int) $slider['elements'], 'documents' => (int) $slider['documents'] );
			}
		}
		return $rows;
	}

	/** ¿Hay algo que decir de esta fila? Un plugin activo, o widgets de Prime Slider. */
	public static function kept_pending( array $row ) {
		return ! empty( $row['site'] ) || ! empty( $row['network'] ) || ! empty( $row['elements'] );
	}

	/**
	 * ¿Deja esta fila contenido que hoy no se ve? Widgets de Prime Slider con el plugin sin activar en el sitio ni en la
	 * red: es lo único de esta clase que cuenta como asunto pendiente, porque una persona puede resolverlo (activar el
	 * plugin o sustituir los widgets). Un plugin activo, con o sin widgets, es sólo información.
	 */
	public static function kept_hidden( array $row ) {
		return ! empty( $row['elements'] ) && empty( $row['site'] ) && empty( $row['network'] );
	}

	/* ---------------------------------------------------------------- Presentación (análisis, informe, red y terminal) */

	/** Nombre visible de una extensión heredada. */
	public static function label( $ext ) {
		return (string) ( self::EXTENSIONS[ $ext ]['label'] ?? $ext );
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

	/** Dónde está activo un plugin de la familia, en palabras. */
	private static function where( array $row ) {
		if ( ! empty( $row['network'] ) ) {
			return 'activo en toda la red';
		}
		if ( ! empty( $row['site'] ) ) {
			return 'activo en este sitio';
		}
		return empty( $row['installed'] ) ? 'no instalado' : 'instalado y desactivado';
	}

	/**
	 * Avisos de los plugins de la familia que la migración no desactiva todavía: `pending` el contenido que hoy no se ve
	 * (kept_hidden()), `info` los plugins activos y `idle` los instalados, desactivados y sin uso.
	 *
	 * @return array{pending:string[], info:string[], idle:string[]}
	 */
	public static function kept_messages( array $rows ) {
		$out = array( 'pending' => array(), 'info' => array(), 'idle' => array() );
		foreach ( $rows as $row ) {
			$name = trim( (string) $row['label'] . ( '' !== (string) ( $row['version'] ?? '' ) ? ' ' . $row['version'] : '' ) );
			if ( ! self::kept_pending( $row ) ) {
				$out['idle'][] = $name;
				continue;
			}
			if ( self::kept_hidden( $row ) ) {
				$out['pending'][] = sprintf( 'Hay %d widgets de Prime Slider en %d documentos y el plugin está %s: no se muestran. Migrar todavía no los convierte; actívalo de nuevo o sustitúyelos en el editor.', (int) $row['elements'], (int) $row['documents'], empty( $row['installed'] ) ? 'sin instalar' : 'desactivado' );
				continue;
			}
			if ( 'prime-slider' === $row['slug'] ) {
				$out['info'][] = $row['elements']
					? sprintf( '%s (%s): Migrar todavía no lo desactiva ni convierte sus widgets (%d elementos en %d documentos), que se siguen viendo con él. No lo desactives ni lo borres mientras los uses.', $name, self::where( $row ), (int) $row['elements'], (int) $row['documents'] )
					: sprintf( '%s (%s): Migrar todavía no lo desactiva. Ningún documento usa sus widgets, así que puedes desactivarlo y borrarlo desde Plugins cuando hayas comprobado el sitio.', $name, self::where( $row ) );
				continue;
			}
			$out['info'][] = sprintf( '%s (%s): Migrar todavía no lo desactiva. Reúne widgets que Element Pack 10 separó del plugin principal y depende de Element Pack Pro, que la migración sí desactiva. Sus widgets con adaptador se convierten como los de Element Pack, y los que no lo tienen figuran en la tabla de widgets: comprueba las páginas que los usan antes de desactivarlo o borrarlo desde Plugins.', $name, self::where( $row ) );
		}
		return $out;
	}

	/** Los plugins de la familia instalados, desactivados y sin uso. */
	public static function idle_message( array $names ) {
		return 'También instalados y desactivados (Migrar no cambia su estado): ' . implode( ', ', $names ) . '.';
	}

	/**
	 * Aviso de red de los plugins de la familia que la migración no desactiva. $lines: «Etiqueta (estado)», de
	 * Runner::kept_network(). Dice qué se convierte de cada uno.
	 */
	public static function kept_notice( array $lines, $more ) {
		$text   = implode( ' ', $lines );
		$addon  = false !== strpos( $text, 'Element Pack Addon' );
		$slider = false !== strpos( $text, 'Prime Slider' );
		$what   = array();
		if ( $addon ) {
			$what[] = 'los widgets del Element Pack Addon con adaptador se convierten como los de Element Pack';
		}
		if ( $slider ) {
			$what[] = 'los de Prime Slider todavía no se convierten';
		}
		return 'Detectados y sin desactivar (Migrar todavía no los desactiva' . ( $what ? '; ' . implode( ', y ', $what ) : '' ) . '): ' . implode( ', ', $lines ) . '. ' . (string) $more;
	}

	/** Una línea por sitio para la pantalla de red y la terminal: efectos descartados y plugins de la familia. */
	public static function summary( array $set, array $kept ) {
		$parts  = array();
		$totals = self::totals( $set );
		if ( $totals['documents'] ) {
			$parts[] = sprintf( 'efectos heredados sin equivalente: %d en %d documentos', $totals['settings'], $totals['documents'] );
		}
		foreach ( $kept as $row ) {
			if ( self::kept_pending( $row ) ) {
				$parts[] = $row['label'] . ' (' . self::where( $row ) . ( ! empty( $row['elements'] ) ? sprintf( ', %d widgets', (int) $row['elements'] ) : '' ) . ')';
			}
		}
		return implode( '; ', $parts );
	}
}
