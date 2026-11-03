<?php
/**
 * F8 · Detección y conversión del frente A (V.12, V.9, §3.A.3): agujas nuevas sobre `_elementor_data` codificado de
 * verdad (también `name=\"element-pack-`), `page_documents()` sobre PHP serializado, patrones de IDs, bolsa con claves
 * de efectos en un widget con mapa (hueco 1), destino no registrado → marcador (hueco 2), claves sin prefijo (hueco 3),
 * fragmentos aplanados, Prime Slider por su convertidor, reescritores de iconos y etiquetas, marcadores en la detección,
 * ajustes de página con su copia, marca F8 y modo restringido, recuento de marcadores, IDs del Addon y análisis previo
 * por trozos. Las clases de los demás frentes son dobles con las firmas de V.14 (lo que aquí se prueba es la llamada).
 */
namespace Elementor {
	class Plugin { public static $instance; }
}
namespace Digitalisimo\Elements\Import\Kits {
	/** Doble de K: el marcador y su recuento, con la forma real. */
	final class Preserve {
		public static function marker( array $element, string $reason = 'unknown', string $source = 'kit' ): array {
			return array( 'id' => (string) ( $element['id'] ?? 'm' ), 'elType' => 'widget', 'widgetType' => 'digitalisimo-unconverted', 'settings' => array( 'digitalisimo_unconverted' => array( 'v' => 1, 'type' => 'widget/' . ( $element['widgetType'] ?? '' ), 'reason' => $reason, 'source' => $source, 'element' => $element ) ), 'elements' => array() );
		}
		public static function info( $marker ): ?array {
			return is_array( $marker['settings']['digitalisimo_unconverted'] ?? null ) ? $marker['settings']['digitalisimo_unconverted'] : null;
		}
		public static function contains_markers( array $elements ): bool {
			return false !== strpos( (string) json_encode( $elements ), '"widgetType":"digitalisimo-unconverted"' );
		}
		public static function recount_document( int $post_id, array $elements ): int {
			$GLOBALS['wp']['recounted'][] = $post_id;
			return 0;
		}
	}
}
namespace Digitalisimo\Elements\Legacy\Effects {
	/** Doble de C: `ep_tooltip_x` (con prefijo) y `section_sticky_on` (sin prefijo) son suyas. */
	final class Dispatcher {
		public static function is_key( string $key ): bool { return in_array( $key, array( 'ep_tooltip_x', 'section_sticky_on' ), true ); }
		public static function detect( array $settings ): bool { return isset( $settings['section_sticky_on'] ) || isset( $settings['ep_tooltip_x'] ); }
		public static function element( array $element, int $post_id ): array {
			$GLOBALS['wp']['dispatched'][] = array( (string) ( $element['id'] ?? '' ), $post_id );
			$GLOBALS['wp']['refs'][ (string) ( $element['id'] ?? '' ) ] = array( $element['settings']['__dynamic__'] ?? array(), $element['settings']['__globals__'] ?? array() );
			if ( self::is_atomic( $element ) ) {
				return $element; // v4: se informa y no se toca.
			}
			foreach ( array( 'ep_tooltip_x', 'section_sticky_on' ) as $key ) {
				if ( isset( $element['settings'][ $key ] ) ) {
					$element['settings'][ 'digi_' . $key ] = $element['settings'][ $key ];
					unset( $element['settings'][ $key ] );
				}
			}
			return $element;
		}
		public static function describe( array $elements ): array { return false !== strpos( json_encode( $elements ), 'section_sticky_on' ) ? array( 'ext.converted.sticky' => 1 ) : array(); }
		/** Lo que Dropped (de C, real) consulta: sin extensiones en el doble y siempre con señal. */
		public static function keys(): array { return array(); }
		public static function signal( int $post_id ): bool { return true; }
		public static function is_atomic( array $element ): bool { return 0 === strpos( (string) ( $element['widgetType'] ?? '' ), 'e-' ); }
	}
	final class Page {
		public static function convert( array $settings, int $post_id ): array {
			$GLOBALS['wp']['page_calls'][] = 'page:' . $post_id;
			unset( $settings['ep_hash_link_enable'] );
			return $settings;
		}
	}
}
namespace Digitalisimo\Elements\Legacy\Platform {
	final class Kit {
		public static function clean_page( array $settings, int $post_id ): array {
			$GLOBALS['wp']['page_calls'][] = 'kit:' . $post_id;
			unset( $settings['element_pack_lightbox'] );
			return $settings;
		}
	}
	final class Icons {
		public static function rewrite( array $element ): array {
			if ( isset( $element['settings']['icon']['library'] ) && 'bdt-custom-uno' === $element['settings']['icon']['library'] ) {
				$element['settings']['icon']['library'] = 'uno';
			}
			return $element;
		}
	}
	final class Dynamic_Tags {
		public static function rewrite( array $element ): array {
			if ( isset( $element['settings']['__dynamic__']['title'] ) ) {
				$element['settings']['__dynamic__']['title'] = str_replace( 'element-pack-site-title', 'site-title', $element['settings']['__dynamic__']['title'] );
			}
			return $element;
		}
	}
}
namespace Digitalisimo\Elements\Legacy\Prime_Slider {
	/** Doble de E: un fragmento (slider + hermano) para `prime-slider-general`, null para `prime-slider-none`. */
	final class Converter {
		public static function element( array $element, int $post_id, string $parent ): ?array {
			$GLOBALS['wp']['ps_calls'][] = array( $element['widgetType'], $post_id, $parent );
			if ( 'prime-slider-none' === $element['widgetType'] ) {
				return null;
			}
			return array( 'elType' => '__fragment', 'elements' => array(
				array( 'id' => 's1', 'elType' => 'widget', 'widgetType' => 'digitalisimo-nested-slider', 'settings' => array(), 'elements' => array() ),
				array( 'id' => 's2', 'elType' => 'widget', 'widgetType' => 'social-icons', 'settings' => array(), 'elements' => array() ),
			) );
		}
		public static function describe( array $element ): array { return array( 'ps.converted' => 1 ); }
	}
	final class Maps {
		public static function get( string $id ): ?array {
			return array( 'prime-slider-general' => array( 'decision' => 'convert' ), 'prime-slider-gone' => array( 'decision' => 'discard' ) )[ $id ] ?? null;
		}
	}
}
namespace Digitalisimo\Elements\Legacy {
	/** Doble de I: un marcador de `bdt-accordion` es convertible; uno de `bdt-weather`, bloqueado. */
	final class Markers {
		public static function classify( array $elements ): array {
			$out = array( 'convertible' => array(), 'blocked' => array(), 'declared' => array() );
			foreach ( $elements as $el ) {
				$type = (string) ( $el['settings']['digitalisimo_unconverted']['type'] ?? '' );
				if ( 'widget/bdt-accordion' === $type ) {
					$out['convertible'][] = $type;
				} elseif ( '' !== $type ) {
					$out['blocked'][] = $type;
				}
			}
			return $out;
		}
	}
}
namespace {
	require __DIR__ . '/fixtures/f8-wp.php';
	f8_load_migration( array( 'translator', 'context', 'report', 'native', 'migration', 'retire', 'aliases', 'runner' ) );
	use Digitalisimo\Elements\Legacy\Migration;
	use Digitalisimo\Elements\Legacy\Native;
	use Digitalisimo\Elements\Legacy\Report;
	use Digitalisimo\Elements\Legacy\Translator;

	/** Widgets registrados en el doble de Elementor (sin la clave = no registrado). */
	final class F8_Widgets_Manager {
		public $types = array();
		public function get_widget_types( $name = null ) {
			return null === $name ? $this->types : ( $this->types[ $name ] ?? null );
		}
	}
	final class F8_Widget {
		public $inner;
		public function __construct( $inner ) { $this->inner = $inner; }
		public function has_widget_inner_wrapper() { return $this->inner; }
	}
	function w( $type, array $settings = array(), $id = 'w' ) {
		return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => array() );
	}
	function doc( array $elements, array $post = array(), array $meta = array() ) {
		return f8_post( $post + array( 'post_type' => 'page' ), array( '_elementor_data' => wp_json_encode( $elements ) ) + $meta );
	}
	$GLOBALS['wp']['dispatched'] = array();

	/* ================================================================ IDs */
	foreach ( array( 'bdt-accordion', 'bdt-x9', 'fooevents-calendar', 'lightbox' ) as $id ) {
		f8_check( Translator::is_legacy_id( $id ) && Translator::is_known_id( $id ) && ! Translator::is_prime_slider_id( $id ), 'ID de Element Pack: ' . $id );
	}
	foreach ( array( 'prime-slider-general', 'prime-slider-blog' ) as $id ) {
		f8_check( Translator::is_prime_slider_id( $id ) && Translator::is_known_id( $id ) && ! Translator::is_legacy_id( $id ), 'ID de Prime Slider (sólo conocido, no de Element Pack): ' . $id );
	}
	foreach ( array( 'prime-slider', 'heading', 'bdt-', 'prime-slider-General', 'xbdt-accordion' ) as $id ) {
		f8_check( ! Translator::is_known_id( $id ), 'No es un ID heredado: ' . $id );
	}
	f8_check( ! Translator::prime_slider_active(), 'Sin BDTPS_CORE_VER, Prime Slider no está cargado.' );
	f8_check( 'b' === Translator::translate_map( array( 'target' => 'x', 'rename' => array() ), 'bdt-z', array( Translator::MARKER => 'bdt-z', 'a' => 'b' ) )['a'], 'translate_map() no vuelve a traducir lo ya traducido.' );
	$q = Translator::posts_query( array( 'posts_include_term_ids' => array( 5, '7' ) ), true );
	f8_check( array( '5', '7' ) === $q['posts_include_term_ids'], 'Con term_taxonomy_id (Prime Slider) los términos no se traducen.' );
	f8_check( 'product' === Translator::loop_query( array(), true, 'product' )['_skin'], 'loop_query() con piel de producto.' );

	/* ================================================================ Agujas */
	$ids                 = array();
	$ids['ps']           = doc( array( w( 'prime-slider-general' ) ) );
	$ids['sticky']       = doc( array( array( 'id' => 's', 'elType' => 'section', 'settings' => array( 'section_sticky_on' => 'yes' ), 'elements' => array() ) ) );
	$ids['parallax']     = doc( array( array( 'id' => 's', 'elType' => 'section', 'settings' => array( 'section_parallax_on' => 'yes' ), 'elements' => array() ) ) );
	$ids['layers']       = doc( array( array( 'id' => 's', 'elType' => 'section', 'settings' => array( 'section_parallax_elements' => array() ), 'elements' => array() ) ) );
	$ids['equal']        = doc( array( array( 'id' => 's', 'elType' => 'section', 'settings' => array( 'section_equal_height_on' => 'yes' ), 'elements' => array() ) ) );
	$ids['particles']    = doc( array( array( 'id' => 's', 'elType' => 'section', 'settings' => array( 'section_particles_on' => 'yes' ), 'elements' => array() ) ) );
	$ids['icon']         = doc( array( w( 'icon', array( 'selected_icon' => array( 'value' => 'x', 'library' => 'bdt-custom-uno' ) ) ) ) );
	$ids['tag']          = doc( array( w( 'heading', array( '__dynamic__' => array( 'title' => '[elementor-tag id="a" name="element-pack-site-title" settings="%7B%7D"]' ) ) ) ) );
	$ids['none']         = doc( array( w( 'heading', array( 'title' => 'Hola' ) ) ) );
	$raw = (string) f8_meta( $ids['tag'], '_elementor_data' );
	f8_check( false !== strpos( $raw, 'name=\\"element-pack-' ), 'El JSON guardado lleva la comilla de la etiqueta escapada (name=\"…).' );
	$found = Migration::documents();
	foreach ( array( 'ps', 'sticky', 'parallax', 'layers', 'equal', 'particles', 'icon', 'tag' ) as $key ) {
		f8_check( in_array( $ids[ $key ], $found, true ), 'La aguja encuentra el documento: ' . $key );
	}
	f8_check( ! in_array( $ids['none'], $found, true ), 'Un documento sin nada heredado no se encuentra.' );
	$rev = f8_post( array( 'post_type' => 'revision', 'post_parent' => $ids['ps'] ), array( '_elementor_data' => wp_json_encode( array( w( 'prime-slider-general' ) ) ) ) );
	f8_check( ! in_array( $rev, $found, true ) && in_array( $rev, Migration::legacy_revisions(), true ), 'Las revisiones van aparte.' );
	f8_check( array( $ids['ps'] ) === Migration::documents_with( array( '"widgetType":"prime-slider-' ) ), 'documents_with() busca sólo las agujas pedidas, sin revisiones.' );
	// Ajustes de página: PHP serializado.
	$pages = array();
	foreach ( Migration::PAGE_NEEDLES as $i => $needle ) {
		$key           = trim( $needle, '"' );
		$key           = '_' === substr( $key, -1 ) ? $key . 'x' : $key;
		$pages[ $key ] = f8_post( array( 'post_type' => 'page' ), array( '_elementor_page_settings' => array( $key => 'yes', 'hide_title' => 'yes' ) ) );
	}
	$clean_page = f8_post( array( 'post_type' => 'page' ), array( '_elementor_page_settings' => array( 'hide_title' => 'yes' ) ) );
	$page_docs  = Migration::page_documents();
	foreach ( $pages as $key => $id ) {
		f8_check( in_array( $id, $page_docs, true ), 'page_documents() encuentra la clave serializada: ' . $key );
	}
	f8_check( ! in_array( $clean_page, $page_docs, true ), 'Una página sin claves heredadas no.' );
	$all = Migration::all_documents();
	f8_check( $all === array_values( array_unique( $all ) ) && in_array( $ids['ps'], $all, true ) && in_array( $pages['ep_hash_link_enable'], $all, true ), 'all_documents(): unión ordenada sin repetir.' );

	/* ================================================================ Native: Prime Slider, fragmentos y reescritores */
	$GLOBALS['wp']['ps_calls'] = array();
	$tree = array( array( 'id' => 'c', 'elType' => 'container', 'settings' => array(), 'elements' => array( w( 'heading', array(), 'h' ), w( 'prime-slider-general', array(), 'p' ), w( 'prime-slider-none', array(), 'n' ) ) ) );
	list( $out, $changes ) = Migration::convert_data( $tree, 42 );
	f8_check( array( array( 'prime-slider-general', 42, 'container' ), array( 'prime-slider-none', 42, 'container' ) ) === $GLOBALS['wp']['ps_calls'], 'Prime Slider se convierte con su convertidor, con el documento y el elType del padre.' );
	f8_check( array( 'h', 's1', 's2', 'n' ) === array_column( $out[0]['elements'], 'id' ), 'El fragmento se aplana en el padre (slider y hermano) y lo que devuelve null queda igual.' );
	f8_check( 3 === $changes, 'Un fragmento cuenta sus elementos; null, ninguno: ' . $changes );
	list( $root ) = Migration::convert_data( array( w( 'prime-slider-general', array(), 'p' ) ), 42 );
	f8_check( array( 's1', 's2' ) === array_column( $root, 'id' ), 'Un fragmento en la raíz del documento también se aplana.' );
	list( $icon, $n ) = Native::element( w( 'icon', array( 'icon' => array( 'value' => 'x', 'library' => 'bdt-custom-uno' ) ) ), 1 );
	f8_check( 'uno' === $icon['settings']['icon']['library'] && 1 === $n, 'Los iconos de Element Pack se reescriben en cualquier elemento.' );
	list( $tag, $n ) = Native::element( w( 'heading', array( '__dynamic__' => array( 'title' => '[elementor-tag name="element-pack-site-title"]' ) ) ), 1 );
	f8_check( str_contains( $tag['settings']['__dynamic__']['title'], 'name="site-title"' ) && 1 === $n, 'Y las etiquetas dinámicas.' );
	list( $same, $n ) = Native::element( w( 'heading', array( 'title' => 'x' ) ), 1 );
	f8_check( 0 === $n && 'x' === $same['settings']['title'], 'Lo que no es heredado no cambia.' );

	/* ================================================================ Native: efectos (huecos 1 y 3) */
	$GLOBALS['wp']['dispatched'] = array();
	list( $acc, $n ) = Native::element( w( 'bdt-accordion', array( 'tabs' => array(), 'ep_tooltip_x' => 'Ayuda' ), 'acc' ), 7 );
	f8_check( 'digitalisimo-accordion' === $acc['widgetType'], 'El widget con mapa se convierte.' );
	f8_check( 'Ayuda' === ( $acc['settings']['digi_ep_tooltip_x'] ?? null ) && ! isset( $acc['settings']['ep_tooltip_x'] ), 'La clave de efecto entra en la bolsa y la convierte su frente (hueco 1: no se pierde).' );
	f8_check( array( array( 'acc', 7 ) ) === $GLOBALS['wp']['dispatched'], 'Dispatcher::element() recibe el elemento y el documento.' );
	list( $sec, $n ) = Native::element( array( 'id' => 'sx', 'elType' => 'section', 'settings' => array( 'section_sticky_on' => 'yes' ), 'elements' => array() ), 7 );
	f8_check( 1 === $n && 'yes' === ( $sec['settings']['digi_section_sticky_on'] ?? null ), 'Una clave sin prefijo detectada por su frente convierte el elemento (hueco 3).' );
	f8_check( Native::is_legacy( array( 'id' => 's', 'elType' => 'section', 'settings' => array( 'section_sticky_on' => 'yes' ) ) ) && Native::is_convertible( array( 'id' => 's', 'elType' => 'section', 'settings' => array( 'section_sticky_on' => 'yes' ) ) ), 'Y cuenta como heredado y convertible.' );
	// Modo restringido: sin conversión de efectos, con aviso.
	$GLOBALS['wp']['dispatched'] = array();
	Report::begin( 'run' );
	Native::$restricted = true;
	list( $kept, $n ) = Native::element( array( 'id' => 'sr', 'elType' => 'section', 'settings' => array( 'section_sticky_on' => 'yes' ), 'elements' => array() ), 8 );
	Native::$restricted = false;
	$t = Report::take();
	f8_check( 0 === $n && 'yes' === $kept['settings']['section_sticky_on'] && array() === $GLOBALS['wp']['dispatched'], 'En modo restringido los efectos no se convierten.' );
	f8_check( 1 === Report::count_code( $t, 'core.residual' ), 'Y se informan (core.residual).' );

	/* ================================================================ Native: destino no registrado y sin adaptador (hueco 2) */
	f8_check( null === Native::target_ready( 'digitalisimo-accordion' ) && null === Native::inner_wrapper( 'digitalisimo-accordion' ), 'Sin Elementor cargado, no se sabe (null) y cuenta como disponible.' );
	\Elementor\Plugin::$instance                  = new \stdClass();
	\Elementor\Plugin::$instance->widgets_manager = new F8_Widgets_Manager();
	\Elementor\Plugin::$instance->widgets_manager->types['digitalisimo-logos'] = new F8_Widget( false );
	f8_check( false === Native::target_ready( 'digitalisimo-accordion' ) && true === Native::target_ready( 'digitalisimo-logos' ), 'Con Elementor, target_ready() dice si el destino está registrado.' );
	f8_check( false === Native::inner_wrapper( 'digitalisimo-logos' ) && null === Native::inner_wrapper( 'digitalisimo-accordion' ), 'inner_wrapper() lee has_widget_inner_wrapper() del destino.' );
	Report::begin( 'run' );
	list( $wrapped, $n ) = Native::element( w( 'bdt-accordion', array( 'tabs' => array() ), 'a1' ), 9 );
	$t = Report::take();
	f8_check( 'digitalisimo-unconverted' === $wrapped['widgetType'] && 'bdt-accordion' === $wrapped['settings']['digitalisimo_unconverted']['element']['widgetType'] && 'migration' === $wrapped['settings']['digitalisimo_unconverted']['source'], 'Un destino no registrado deja el widget en un marcador de K (source: migration), sin convertir.' );
	f8_check( 1 === $n && 1 === Report::count_code( $t, 'core.target_missing' ), 'Y lo informa (core.target_missing).' );
	list( $noad, $n ) = Native::element( w( 'bdt-weather-x', array( 'city' => 'x' ), 'b1' ), 9 );
	$t = Report::take();
	f8_check( 'digitalisimo-unconverted' === $noad['widgetType'] && 1 === Report::count_code( $t, 'core.no_adapter' ), 'Un widget de Element Pack sin adaptador va a un marcador con core.no_adapter.' );
	f8_check( Native::is_convertible( w( 'bdt-weather-x' ) ), 'Con K cargado, envolverlo cuenta como convertible (una vez).' );
	// Globales y dinámicos de las extensiones en un widget con mapa: widget() filtra por los controles del destino, la
	// bolsa los conserva para su frente.
	final class F8_Target_Widget {
		public function get_controls() { return array( 'tabs' => array( 'type' => 'repeater' ) ); }
	}
	$target = Translator::target( 'bdt-advanced-heading' );
	\Elementor\Plugin::$instance->widgets_manager->types[ $target ] = new F8_Target_Widget();
	list( $refs, $n ) = Native::element( w( 'bdt-advanced-heading', array( 'ep_tooltip_x' => 'a', '__dynamic__' => array( 'ep_tooltip_x' => '[elementor-tag name="post-title"]', 'otra' => 'x' ), '__globals__' => array( 'ep_tooltip_x' => 'globals/colors?id=primary' ) ), 'acc2' ), 7 );
	$seen = $GLOBALS['wp']['refs']['acc2'] ?? array( array(), array() );
	f8_check( $target === $refs['widgetType'] && isset( $seen[0]['ep_tooltip_x'], $seen[1]['ep_tooltip_x'] ), 'Los textos dinámicos y colores globales de la extensión llegan a su frente aunque el destino no tenga esos controles.' );
	f8_check( ! isset( $seen[0]['otra'] ), 'Las demás referencias siguen filtradas por los controles del destino (como en 1.0.20).' );
	\Elementor\Plugin::$instance = null;
	// Un elemento atómico (v4) con sólo extensiones de Element Pack: se informa y no se toca.
	$GLOBALS['wp']['dispatched'] = array();
	$atomic = array( 'id' => 'v4', 'elType' => 'widget', 'widgetType' => 'e-heading', 'settings' => array( 'ep_tooltip_x' => array( '$$type' => 'string', 'value' => 'a' ) ), 'elements' => array() );
	list( $same4, $n4 ) = Native::element( $atomic, 7 );
	f8_check( 0 === $n4 && $same4 === $atomic && array( array( 'v4', 7 ) ) === $GLOBALS['wp']['dispatched'], 'Un elemento v4 con extensiones no pierde sus claves; su frente lo informa.' );
	$atomic['settings']['ep_display_conditions_enable'] = 'yes';
	list( $mixed, $nm ) = Native::element( $atomic, 7 );
	f8_check( 1 === $nm && ! isset( $mixed['settings']['ep_display_conditions_enable'] ), 'Con otras claves heredadas (visibilidad), sigue la conversión de 1.0.20.' );

	/* ================================================================ Detección de marcadores y de Prime Slider */
	$preserve = '\\Digitalisimo\\Elements\\Import\\Kits\\Preserve';
	$conv     = $preserve::marker( w( 'bdt-accordion' ) );
	$block    = $preserve::marker( w( 'bdt-weather' ) );
	f8_check( Native::is_legacy( $conv ) && Native::is_convertible( $conv ), 'Un marcador que Markers clasifica como convertible cuenta como convertible.' );
	f8_check( Native::is_legacy( $block ) && ! Native::is_convertible( $block ), 'Uno bloqueado cuenta como heredado, no convertible (ya no queda invisible).' );
	f8_check( Native::is_convertible( w( 'prime-slider-general' ) ) && Native::is_legacy( w( 'prime-slider-gone' ) ) && ! Native::is_convertible( w( 'prime-slider-gone' ) ), 'Prime Slider: convertible con mapa no descartado; un descarte es heredado, no convertible.' );
	f8_check( 'bdt-weather' === Migration::marker_type( $block ), 'marker_type() lee el tipo guardado en el marcador.' );
	$mdoc = doc( array( $block, w( 'bdt-weather', array(), 'z' ) ) );
	$inv  = Migration::inventory();
	f8_check( 2 === $inv['bdt-weather']['elements'] && 1 === $inv['bdt-weather']['markers'], 'El inventario cuenta también los widgets guardados en marcadores.' );
	$only   = doc( array( $block ) );
	$groups = Migration::classify();
	f8_check( in_array( $only, $groups['blocked'], true ) && in_array( $mdoc, $groups['convertible'], true ), 'classify(): un documento con sólo el marcador bloqueado va a «blocked»; con un widget suelto que envolver, a «convertible».' );

	/* ================================================================ convert_document: página, marca F8, modo restringido, recuento */
	$GLOBALS['wp']['page_calls'] = array();
	$GLOBALS['wp']['recounted']  = array();
	$p1 = f8_post( array( 'post_type' => 'page' ), array(
		'_elementor_data'          => wp_json_encode( array( w( 'bdt-accordion', array( 'tabs' => array() ), 'a' ) ) ),
		'_elementor_page_settings' => array( 'ep_hash_link_enable' => 'yes', 'element_pack_lightbox' => 'x', 'hide_title' => 'yes' ),
	) );
	$result = Migration::convert_document( $p1 );
	f8_check( 1 === $result['native'] && true === $result['page'], 'Convierte datos y página.' );
	f8_check( array( 'page:' . $p1, 'kit:' . $p1 ) === $GLOBALS['wp']['page_calls'], 'La página pasa por Effects\\Page y después por Platform\\Kit.' );
	f8_check( array( 'hide_title' => 'yes' ) === f8_meta( $p1, '_elementor_page_settings' ), 'Las claves heredadas de la página se retiran.' );
	$bp = json_decode( (string) f8_meta( $p1, Migration::BACKUP_PAGE ), true );
	f8_check( is_array( $bp['data'] ) && 'yes' === $bp['data']['ep_hash_link_enable'], 'La copia de la página guarda el array original, con una sola codificación.' );
	$backup = json_decode( (string) f8_meta( $p1, Migration::BACKUP ), true );
	f8_check( is_string( $backup['data'] ) && is_array( $backup['f8'] ) && md5( (string) f8_meta( $p1, '_elementor_data' ) ) === $backup['f8']['hash'], 'La primera conversión lleva la marca F8 (huella de lo escrito) en su copia.' );
	f8_check( null === f8_meta( $p1, Migration::F8_MARK ) && $backup['f8'] === Migration::f8_mark( $p1 ), 'Sin meta aparte: f8_mark() la lee de la copia.' );
	f8_check( ! Migration::restricted( $p1 ), 'Un documento migrado por F8 no va en modo restringido.' );
	$again = Migration::convert_document( $p1 );
	f8_check( 0 === $again['native'] && false === $again['page'], 'Volver a migrar un documento F8 sin cambios no hace nada.' );
	// Documento migrado por 1.0.20: copia sin marca → modo restringido.
	$p2 = f8_post( array( 'post_type' => 'page' ), array(
		'_elementor_data'          => wp_json_encode( array( array( 'id' => 's', 'elType' => 'section', 'settings' => array( 'section_sticky_on' => 'yes' ), 'elements' => array( w( 'icon', array( 'icon' => array( 'library' => 'bdt-custom-uno', 'value' => 'v' ) ), 'i' ) ) ) ) ),
		'_elementor_page_settings' => array( 'ep_hash_link_enable' => 'yes' ),
		Migration::BACKUP          => wp_json_encode( array( 'time' => 1, 'data' => '[]' ) ),
	) );
	f8_check( Migration::restricted( $p2 ), 'Copia de 1.0.20 sin marca F8: modo restringido.' );
	$GLOBALS['wp']['dispatched'] = array();
	$GLOBALS['wp']['page_calls'] = array();
	Report::clear();
	Report::begin( 'run' );
	$r2 = Migration::convert_document( $p2 );
	$t  = Report::take();
	$d2 = json_decode( (string) f8_meta( $p2, '_elementor_data' ), true );
	f8_check( 1 === $r2['native'] && 'uno' === $d2[0]['elements'][0]['settings']['icon']['library'] && 'yes' === $d2[0]['settings']['section_sticky_on'], 'En modo restringido sólo se reparan referencias (el icono), sin tocar los efectos.' );
	f8_check( array() === $GLOBALS['wp']['dispatched'] && array() === $GLOBALS['wp']['page_calls'] && array( 'ep_hash_link_enable' => 'yes' ) === f8_meta( $p2, '_elementor_page_settings' ), 'Ni los efectos ni la página.' );
	f8_check( 2 === Report::count_code( $t, 'core.residual' ), 'Lo que queda se informa (efectos y página).' );
	$bf8 = json_decode( (string) f8_meta( $p2, Migration::BACKUP_F8 ), true );
	f8_check( is_array( $bf8 ) && str_contains( $bf8['data'], 'bdt-custom-uno' ), 'Antes de escribir, copia el estado de 1.0.20 (una vez).' );
	f8_check( ! empty( Migration::f8_mark( $p2 )['restricted'] ) && Migration::restricted( $p2 ), 'La marca recuerda el modo restringido para la próxima pasada.' );
	f8_check( '[]' === json_decode( (string) f8_meta( $p2, Migration::BACKUP ), true )['data'], 'La copia original de 1.0.20 no se toca.' );
	// Recuento de marcadores: con marcadores en el resultado o con la meta previa; nunca en revisiones.
	\Elementor\Plugin::$instance                  = new \stdClass();
	\Elementor\Plugin::$instance->widgets_manager = new F8_Widgets_Manager();
	$GLOBALS['wp']['recounted'] = array();
	$p3   = doc( array( w( 'bdt-accordion', array(), 'a3' ) ) );
	Migration::convert_document( $p3 );
	f8_check( array( $p3 ) === $GLOBALS['wp']['recounted'], 'Un resultado con marcadores se recuenta.' );
	$r3 = f8_post( array( 'post_type' => 'revision', 'post_parent' => $p3 ), array( '_elementor_data' => wp_json_encode( array( w( 'bdt-accordion', array(), 'a4' ) ) ) ) );
	Migration::convert_document( $r3 );
	f8_check( array( $p3 ) === $GLOBALS['wp']['recounted'], 'Una revisión nunca se recuenta.' );
	\Elementor\Plugin::$instance = null;
	// Restaurar devuelve datos y página, y quita la marca.
	f8_check( Migration::revert_document( $p1 ), 'revert_document() restaura.' );
	f8_check( 'bdt-accordion' === json_decode( (string) f8_meta( $p1, '_elementor_data' ), true )[0]['widgetType'] && 'yes' === f8_meta( $p1, '_elementor_page_settings' )['ep_hash_link_enable'], 'Datos y página vuelven a su original.' );
	f8_check( null === f8_meta( $p1, Migration::BACKUP ) && null === f8_meta( $p1, Migration::BACKUP_PAGE ) && null === Migration::f8_mark( $p1 ), 'Sin copias ni marca después.' );
	f8_check( Migration::revert_document( $p2 ) && str_contains( (string) f8_meta( $p2, '_elementor_data' ), 'bdt-custom-uno' ) && null !== f8_meta( $p2, Migration::BACKUP ), 'Un documento restringido vuelve a su estado de 1.0.20 (BACKUP_F8), conservando la copia original.' );

	/* ================================================================ IDs del Addon y análisis previo por trozos */
	f8_plugin( 'bdthemes-element-pack/bdthemes-element-pack.php', array( 'Name' => 'Element Pack Pro', 'Version' => '10.0.1' ) );
	$moved = Migration::moved_to_addon();
	f8_check( in_array( 'bdt-age-gate', $moved, true ) && count( $moved ) > 50, 'Element Pack 10 sin el Addon: se cargan los IDs que pasaron al Addon.' );
	f8_plugin( 'bdthemes-element-pack-addon/bdthemes-element-pack-addon.php', array( 'Name' => 'Element Pack Addon', 'Author' => 'BdThemes' ) );
	f8_check( array() === Migration::moved_to_addon(), 'Con el Addon instalado, nada que avisar.' );
	unset( $GLOBALS['wp']['plugins']['bdthemes-element-pack-addon/bdthemes-element-pack-addon.php'] );
	$gate = doc( array( w( 'bdt-age-gate', array(), 'g' ), array( 'id' => 's', 'elType' => 'section', 'settings' => array( 'section_sticky_on' => 'yes' ), 'elements' => array() ), $conv ) );
	delete_option( Migration::ANALYSIS );
	$first = Migration::analyze_step( 0.000001 );
	f8_check( ! $first['done'] && 1 === $first['processed'] && $first['total'] > 1, 'El análisis avanza por trozos: «N de M».' );
	f8_check( str_contains( Migration::analysis_html( $first ), 'Analizando… (1 de ' ), 'Y lo dice mientras dura.' );
	$cache = get_option( Migration::ANALYSIS );
	f8_check( false === f8_autoload( Migration::ANALYSIS ) && 1 === (int) $cache['processed'], 'Su caché no se carga automáticamente y guarda el cursor.' );
	$all_done = Migration::analyze_step( 0 );
	f8_check( $all_done['done'] && $all_done['processed'] === $all_done['total'], 'Sigue donde quedó hasta el final.' );
	$rep = $all_done['report'];
	f8_check( isset( $rep['docs'][ $gate ] ) && 1 === Report::count_code( array( 'docs' => array( $gate => $rep['docs'][ $gate ] ) ), 'core.addon_missing' ), 'Cuenta los widgets que pasaron al Addon (core.addon_missing).' );
	f8_check( 1 <= Report::count_code( array( 'docs' => array( $gate => $rep['docs'][ $gate ] ) ), 'ext.converted.sticky' ) && 1 <= Report::count_code( array( 'docs' => array( $gate => $rep['docs'][ $gate ] ) ), 'mk.convertible' ), 'Y describe efectos (C) y marcadores (I) por documento.' );
	f8_check( null === get_option( Report::OPTION, null ), 'El análisis no escribe en el informe de la tarea.' );
	$queries = count( $GLOBALS['wpdb']->queries );
	$cached  = Migration::analyze_step( 5 );
	f8_check( $cached['done'] && $cached['report'] === $rep, 'Sin cambios en los documentos, el análisis guardado vale.' );
	update_post_meta( $gate, '_elementor_data', wp_slash( wp_json_encode( array( w( 'bdt-age-gate', array(), 'g' ) ) ) ) );
	f8_post( array( 'post_type' => 'page' ), array( '_elementor_data' => wp_json_encode( array( w( 'bdt-accordion', array(), 'nuevo' ) ) ) ) );
	$fresh = Migration::analyze_step( 0 );
	f8_check( $fresh['done'] && $fresh['total'] === $all_done['total'] + 1, 'Un documento nuevo cambia la huella y el análisis se rehace.' );

	f8_done( 'DIGITALÍSIMO Migración: detección y conversión de F8', 70 );
}
