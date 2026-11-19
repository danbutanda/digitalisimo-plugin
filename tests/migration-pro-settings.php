<?php
/**
 * Ajustes de los widgets de PRO que no son suyos, recuperación de lo que una versión anterior no pasó, aviso de
 * WooCommerce y restos de migraciones viejas, en DIGITALÍSIMO Migración (`legacy/class-pro.php`, `class-migration.php`,
 * `class-native.php`). Caso real del 2026-11-18, con fixtures sintéticos y los mapas reales de Elements:
 *
 * 1. Un documento con los widgets y las claves del informe real (extensiones de Element Pack con sus valores por defecto,
 *    `content_width`, condiciones apagadas de otro plugin, `form_fields.dynamic`, `price_list.old_price` e
 *    `item_badge`): Migrar no deja ningún `pro.unmapped`; las claves de Element Pack las convierte su etapa (nunca salen
 *    como ajustes de PRO) y las demás pasan tal cual. El análisis tampoco las da por perdidas.
 * 2. Condición de otro plugin encendida: el widget se queda como widget de PRO (`pro.condition_kept`), en la tarea y en
 *    el análisis; con un Elements sin esa corrección, la red de seguridad de Migración hace lo mismo.
 * 3. Recuperación desde la copia de un documento que convirtió la versión anterior: lo que pasa tal cual vuelve sólo
 *    donde falta (también en las filas, por `_id`), sin cambiar nada editado; una fila borrada se informa; la condición
 *    encendida perdida devuelve el widget de PRO con la huella F8 (o el elemento intacto, en una copia de ≤ 1.0.20) y si
 *    no, se informa por documento; la etapa `pro` toma esos documentos; repetir no cambia nada; `--revert` no lo toma
 *    por editado.
 * 4. WooCommerce inactivo: `pro.target_dependency` (sin pedir actualizar Elements) en la tarea, el análisis y el estado.
 * 5. Restos de una migración vieja (modo restringido): los inertes en una sola línea del sitio; lo encendido, por
 *    documento con el nombre de la extensión.
 *
 * Con el código anterior falla en cada sección.
 */

namespace Elementor {
	if ( ! class_exists( Plugin::class, false ) ) {
		class Plugin {
			public static $instance;
		}
	}
}

namespace Digitalisimo\Elements {
	final class Widget_Registry {}
}

namespace {
	require __DIR__ . '/fixtures/f8-wp.php';
	f8_wpdb( 'F8_Wpdb' );
	f8_load_migration( array( 'translator', 'context', 'report', 'native', 'migration', 'retire', 'aliases', 'runner', 'pro' ) );
	if ( ! defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ) {
		define( 'DIGITALISIMO_ELEMENTS_VERSION', '4.3.0.999' ); // El sello de la recuperación lleva la versión de Elements.
	}
	require_once dirname( __DIR__ ) . '/digitalisimo-elements/modules/digitalisimo-convert/class-module.php';
	\Digitalisimo\Elements\Convert\Module::init();

	use Digitalisimo\Elements\Convert\Registry;
	use Digitalisimo\Elements\Convert\Translator as Pro_Translator;
	use Digitalisimo\Elements\Legacy\Context;
	use Digitalisimo\Elements\Legacy\Migration;
	use Digitalisimo\Elements\Legacy\Native;
	use Digitalisimo\Elements\Legacy\Pro;
	use Digitalisimo\Elements\Legacy\Report;
	use Digitalisimo\Elements\Legacy\Runner;
	use Digitalisimo\Elements\Legacy\Aliases;
	use Digitalisimo\Elements\Legacy\Effects\Dispatcher;

	$check = 'f8_check';

	function w( string $type, array $settings = array(), string $id = 'w1' ): array {
		return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => array() );
	}
	function box( array $children, string $id, array $settings = array() ): array {
		return array( 'id' => $id, 'elType' => 'container', 'settings' => $settings, 'elements' => $children, 'isInner' => false );
	}
	function doc( array $elements, array $meta = array(), array $post = array() ): int {
		return f8_post( $post + array( 'post_type' => 'page', 'post_title' => 'Página' ), array( '_elementor_data' => wp_json_encode( $elements ) ) + $meta );
	}
	function tree( int $id ): array {
		$out = json_decode( (string) get_metadata( 'post', $id, '_elementor_data', true ), true );
		return is_array( $out ) ? $out : array();
	}
	function find( array $elements, string $id ): ?array {
		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			if ( (string) ( $el['id'] ?? '' ) === $id ) {
				return $el;
			}
			$hit = find( (array) ( $el['elements'] ?? array() ), $id );
			if ( $hit ) {
				return $hit;
			}
		}
		return null;
	}
	/** `código:widget:nombre` de un documento en un informe (sólo `$prefix`). */
	function codes_of( array $report, int $id, string $prefix = 'pro.' ): array {
		$out = array();
		foreach ( (array) ( $report['docs'][ $id ]['items'] ?? array() ) as $item ) {
			if ( 0 === strpos( $item['code'], $prefix ) ) {
				$out[] = $item['code'] . ':' . (string) ( $item['detail']['widget'] ?? '' ) . ':' . (string) ( $item['detail']['name'] ?? '' );
			}
		}
		sort( $out );
		return array_values( array_unique( $out ) );
	}
	function item_of( array $report, int $id, string $code ): ?array {
		foreach ( (array) ( $report['docs'][ $id ]['items'] ?? array() ) as $item ) {
			if ( $code === $item['code'] ) {
				return $item;
			}
		}
		return null;
	}
	class Test_Manager {
		public $known;
		public function __construct( array $known ) {
			$this->known = $known;
		}
		public function get_widget_types( $name = null ) {
			return null === $name ? array() : ( in_array( $name, $this->known, true ) ? new stdClass() : null );
		}
	}
	function manager( ?array $known ): void {
		\Elementor\Plugin::$instance = null === $known ? null : (object) array( 'widgets_manager' => new Test_Manager( $known ) );
		Pro::reset();
	}
	function fresh(): void {
		f8_reset();
		f8_wpdb( 'F8_Wpdb' );
		Runner::init();
		Aliases::set_map( array() );
		Report::reset_catalog();
		Report::clear();
		Report::begin( 'run' );
		Context::reset();
		Registry::reset();
		manager( null );
	}
	/** Convierte un documento como la tarea y devuelve el informe de la petición. */
	function run_doc( int $id ): array {
		Report::clear();
		Report::begin( 'run' );
		Pro::convert_document( $id );
		return Report::take();
	}
	/** La etapa `pro` del runner, como en una petición, hasta que pasa a la siguiente. */
	function run_pro_stage(): array {
		$state          = Runner::fresh( true, 0, 1 );
		$state['stage'] = 'pro';
		$stage          = new ReflectionMethod( Runner::class, 'stage_pro' );
		if ( PHP_VERSION_ID < 80100 ) {
			$stage->setAccessible( true );
		}
		for ( $i = 0; $i < 50 && 'pro' === $state['stage']; $i++ ) {
			$state = $stage->invoke( null, $state, microtime( true ) + 30 );
		}
		return $state;
	}
	/**
	 * Lo que dejaba la versión anterior (1.0.28 con Elements 4.3.0.187): el widget convertido sin lo que hoy pasa tal
	 * cual ni las condiciones (el traductor no las declaraba y no pasaban), con la marca del traductor.
	 */
	function old_convert( array $element ): array {
		$map = Registry::map( (string) $element['widgetType'] );
		list( $settings, $codes ) = Pro_Translator::translate( (array) $element['settings'], $map );
		foreach ( $codes as $code ) {
			if ( 'pro.inert' !== $code['code'] && empty( $code['keep'] ) ) {
				continue;
			}
			$key = (string) $code['key'];
			$dot = strpos( $key, '.' );
			if ( false === $dot ) {
				unset( $settings[ $key ], $settings['__dynamic__'][ $key ], $settings['__globals__'][ $key ] );
				continue;
			}
			$to = (string) ( $map['repeaters'][ substr( $key, 0, $dot ) ]['target'] ?? substr( $key, 0, $dot ) );
			foreach ( array_keys( (array) ( $settings[ $to ] ?? array() ) ) as $i ) {
				unset( $settings[ $to ][ $i ][ substr( $key, $dot + 1 ) ] );
			}
		}
		$element['widgetType'] = (string) $map['target'];
		$element['settings']   = $settings;
		return $element;
	}
	/** Documento que convirtió la versión anterior: copia original y contenido convertido, con o sin huella F8. */
	function migrated_doc( array $original, array $current, bool $mark = true, array $meta = array() ): int {
		$raw    = wp_json_encode( $current );
		$backup = array( 'time' => 1, 'data' => wp_json_encode( $original ) );
		if ( $mark ) {
			$backup['f8'] = array( 'v' => 1, 'time' => 1, 'hash' => md5( $raw ), 'page_hash' => '', 'restricted' => 0 );
		}
		return doc( $current, array( Migration::BACKUP => wp_json_encode( $backup ) ) + $meta );
	}

	/* -------------------------------------------------------------- Ajustes del informe real (sintéticos) */

	/** Valores por defecto que Element Pack guardaba en cualquier widget con sus extensiones apagadas. */
	function ep_defaults(): array {
		return array(
			'ep_widget_cf_colors'                    => '#26ccff,#a25afd',
			'ep_widget_cf_shapes'                    => array( 'circle' ),
			'ep_widget_cf_shapes_svg'                => '',
			'ep_widget_cf_shapes_emoji'              => '',
			'ep_notation_list'                       => array( array( '_id' => 'n1', 'ep_notation_type' => 'underline' ) ),
			'ep_sound_effects_element_selector'      => '',
			'element_pack_widget_tooltip_text'       => 'This is Tooltip',
			'ep_display_conditions'                  => array( array( '_id' => 'd1', 'ep_condition_key' => 'login_status' ) ),
			'ep_parallax_effects_transition_for'     => 'all',
			'ep_parallax_effects_transition_duration' => array( 'unit' => 'ms', 'size' => 300 ),
			'ep_ripple_custom_selector'              => '',
			'element_pack_cursor_effects_icons'      => array( 'value' => '', 'library' => '' ),
		);
	}
	function foreign(): array {
		return array(
			'content_width'          => 'boxed',
			'display_condition_list' => array( array( '_id' => 'u1', 'display_condition_key' => 'browser', 'display_condition_browser' => 'chrome' ) ),
		);
	}
	function real_site(): array {
		return array(
			box( array(
				w( 'blockquote', array( 'blockquote_content' => 'Cita' ) + foreign() + ep_defaults(), 'q1' ),
				w( 'price-list', array( 'price_list' => array( array( '_id' => 'p1', 'title' => 'Plan', 'price' => '$9', 'old_price' => '$12', 'item_badge' => 'Oferta' ), array( '_id' => 'p2', 'title' => 'Otro', 'price' => '$5', 'old_price' => '$7' ) ) ), 'l1' ),
				w( 'form', array( 'form_name' => 'Contacto', 'form_fields' => array( array( '_id' => 'f1', 'custom_id' => 'name', 'field_type' => 'text', 'dynamic' => array( 'active' => 'yes' ) ) ) ) + ep_defaults(), 'f1' ),
				w( 'code-highlight', array( 'code' => 'echo 1;', 'pa_condition_repeater' => array( array( '_id' => 'pa1', 'pa_condition_key' => 'browser' ) ) ) + foreign(), 'k1' ),
				w( 'table-of-contents', array( 'title' => 'Índice' ) + ep_defaults(), 't1' ),
			), 'c1' ),
		);
	}

	/* ================================================================ 1. El documento del informe real */
	fresh();
	$id     = doc( real_site() );
	$report = run_doc( $id );
	$data   = tree( $id );
	$codes  = codes_of( $report, $id );
	$check( ! preg_grep( '/^pro\.unmapped/', $codes ), 'Ningún «ajuste que la conversión no reconoce»: ' . implode( ', ', $codes ) );
	$check( 'digitalisimo-blockquote' === find( $data, 'q1' )['widgetType'] && 'digitalisimo-price-list' === find( $data, 'l1' )['widgetType'] && 'digitalisimo-form' === find( $data, 'f1' )['widgetType'] && 'digitalisimo-code-highlight' === find( $data, 'k1' )['widgetType'] && 'digitalisimo-table-of-contents' === find( $data, 't1' )['widgetType'], 'Los cinco widgets se convierten.' );
	$q1 = find( $data, 'q1' )['settings'];
	$check( 'boxed' === $q1['content_width'] && foreign()['display_condition_list'] === $q1['display_condition_list'], 'content_width y las condiciones apagadas de otro plugin pasan tal cual.' );
	$check( ! preg_grep( '/^(ep_|element_pack_)/', array_keys( $q1 ) ) && ! preg_grep( '/^(ep_|element_pack_)/', array_keys( find( $data, 'f1' )['settings'] ) ), 'Las claves de Element Pack no quedan en el widget propio (las trata su etapa): ' . implode( ', ', array_keys( $q1 ) ) );
	$check( '' === ( $q1['digitalisimo_visibility_enable'] ?? null ) && 1 === count( (array) ( $q1['digitalisimo_visibility_rules'] ?? array() ) ), 'Las reglas de visibilidad de Element Pack pasan a las propias, apagadas como estaban (como en cualquier otro elemento).' );
	$rows = find( $data, 'l1' )['settings']['price_list'];
	$check( '$12' === $rows[0]['old_price'] && 'Oferta' === $rows[0]['item_badge'] && '$7' === $rows[1]['old_price'], 'Precio anterior y distintivo pasan en sus filas.' );
	$check( array( 'active' => 'yes' ) === find( $data, 'f1' )['settings']['form_fields'][0]['dynamic'], '«dynamic» pasa en la fila del formulario.' );
	$check( in_array( 'pro.inert:blockquote:content_width, display_condition_list', $codes, true ) && in_array( 'pro.inert:price-list:price_list.old_price, price_list.item_badge', $codes, true ) && in_array( 'pro.inert:form:form_fields.dynamic', $codes, true ) && in_array( 'pro.inert:code-highlight:pa_condition_repeater, content_width, display_condition_list', $codes, true ), 'El informe dice en «Convertido», en una línea por widget, qué pasó tal cual: ' . implode( ' | ', $codes ) );
	$check( count( (array) $report['docs'][ $id ]['items'] ) <= 12 && 0 === (int) ( $report['docs'][ $id ]['more'] ?? 0 ), 'Cabe entero en el informe del documento (nada contado aparte).' );
	$check( ! preg_grep( '/:(ep_|element_pack_)/', $codes ), 'Ninguna clave de Element Pack sale como ajuste de PRO.' );
	// El análisis («Lo que hará Migrar»), sobre el documento sin convertir.
	fresh();
	$id = doc( real_site() );
	Report::begin( 'analysis' );
	Context::begin( $id, 'analysis' );
	list( $out, $log, $blocked, , $kept ) = Pro::convert_data( real_site() );
	Context::end();
	$all = array();
	foreach ( Pro::codes( $log ) as $item ) {
		$all[] = $item[0] . ':' . $item[1]['widget'] . ':' . ( $item[1]['name'] ?? '' );
	}
	$check( ! $blocked && ! $kept && ! preg_grep( '/^pro\.unmapped/', $all ) && ! preg_grep( '/:(ep_|element_pack_)/', $all ), 'El análisis tampoco las da por perdidas: ' . implode( ', ', $all ) );
	$check( 'This is Tooltip' === find( $out, 'q1' )['settings']['element_pack_widget_tooltip_text'] && 'digitalisimo-blockquote' === find( $out, 'q1' )['widgetType'], 'Sin la etapa de Element Pack (análisis), sus claves vuelven intactas al elemento.' );
	Report::begin( 'run' );

	/* ================================================================ 2. Condición encendida de otro plugin */
	fresh();
	$on = array( 'blockquote_content' => 'Sólo en Chrome', 'display_condition_enable' => 'yes' ) + foreign();
	$id = doc( array( box( array( w( 'blockquote', $on, 'q1' ), w( 'blockquote', array( 'blockquote_content' => 'Libre' ), 'q2' ), w( 'code-highlight', array( 'code' => 'x', 'xa_visibility_rules' => array( array( 'role' => 'guest' ) ) ), 'k1' ) ), 'c1' ) ) );
	$report = run_doc( $id );
	$data   = tree( $id );
	$check( 'blockquote' === find( $data, 'q1' )['widgetType'] && $on === find( $data, 'q1' )['settings'], 'El widget con la condición encendida se queda como widget de PRO, idéntico.' );
	$check( 'code-highlight' === find( $data, 'k1' )['widgetType'], 'Una visibilidad desconocida con reglas, también (falla cerrado).' );
	$check( 'digitalisimo-blockquote' === find( $data, 'q2' )['widgetType'], 'Los demás del documento se convierten.' );
	$codes = codes_of( $report, $id );
	$check( in_array( 'pro.condition_kept:blockquote:display_condition_enable', $codes, true ) && in_array( 'pro.condition_kept:code-highlight:xa_visibility_rules', $codes, true ), 'El informe lo dice en «Pendiente de revisar»: ' . implode( ', ', $codes ) );
	$check( 'review' === Report::section_of( item_of( $report, $id, 'pro.condition_kept' ) ), 'pro.condition_kept va en «Pendiente de revisar».' );
	$check( in_array( $id, Pro::documents(), true ), 'El documento sigue entre los que tienen widgets de PRO por convertir (no se oculta nada).' );
	Report::begin( 'analysis' );
	Context::begin( $id, 'analysis' );
	list( , , , , $kept ) = Pro::convert_data( array( w( 'blockquote', $on, 'q1' ) ) );
	Context::end();
	Report::begin( 'run' );
	$check( 1 === count( $kept ) && 'q1' === $kept[0]['element'], 'El análisis también lo deja como está.' );
	// Red de seguridad: un Elements sin la corrección informa la condición como `pro.unmapped` y la convierte.
	$net = new ReflectionMethod( Pro::class, 'keep_conditions' );
	if ( PHP_VERSION_ID < 80100 ) {
		$net->setAccessible( true );
	}
	$before = array( box( array( w( 'blockquote', $on, 'q1' ), w( 'blockquote', array( 'display_condition_list' => array( array( '_id' => 'a' ) ) ), 'q3' ) ), 'c1' ) );
	$after  = array( box( array( w( 'digitalisimo-blockquote', array( '_digitalisimo_pro' => 'blockquote' ), 'q1' ), w( 'digitalisimo-blockquote', array( '_digitalisimo_pro' => 'blockquote' ), 'q3' ) ), 'c1' ) );
	$old    = array(
		array( 'source' => 'blockquote', 'target' => 'digitalisimo-blockquote', 'element' => 'q1', 'codes' => array( array( 'code' => 'pro.unmapped', 'key' => 'display_condition_enable' ), array( 'code' => 'pro.unmapped', 'key' => 'display_condition_list' ) ) ),
		array( 'source' => 'blockquote', 'target' => 'digitalisimo-blockquote', 'element' => 'q3', 'codes' => array( array( 'code' => 'pro.unmapped', 'key' => 'display_condition_list' ) ) ),
	);
	list( $safe, $log, $kept ) = $net->invoke( null, $after, $before, $old, array() );
	$check( 'blockquote' === find( $safe, 'q1' )['widgetType'] && $on === find( $safe, 'q1' )['settings'] && 1 === count( $kept ) && 'q1' === $kept[0]['element'] && 1 === count( $log ) && 'q3' === $log[0]['element'], 'Con un Elements anterior, la red de seguridad devuelve el original del que tenía la condición encendida y deja convertido el de la apagada.' );

	/* ================================================================ 3. Recuperación desde la copia */
	fresh();
	$quote    = w( 'blockquote', array( 'blockquote_content' => 'Cita' ) + foreign(), 'q1' );
	$prices   = w( 'price-list', array( 'price_list' => array( array( '_id' => 'p1', 'title' => 'Plan', 'price' => '$9', 'old_price' => '$12' ), array( '_id' => 'p2', 'title' => 'Otro', 'price' => '$5', 'item_badge' => 'Nuevo' ) ) ), 'l1' );
	$original = array( box( array( $quote, $prices ), 'c1' ) );
	$current  = array( box( array( old_convert( $quote ), old_convert( $prices ) ), 'c1' ) );
	$check( ! isset( $current[0]['elements'][0]['settings']['content_width'] ) && ! isset( $current[0]['elements'][1]['settings']['price_list'][0]['old_price'] ), 'Fixture: la versión anterior perdió esos ajustes.' );
	// 3a. Sin editar desde la migración (huella F8 al día).
	$id     = migrated_doc( $original, $current );
	$backup = get_metadata( 'post', $id, Migration::BACKUP, true );
	$check( in_array( $id, Pro::stage_documents(), true ) && ! in_array( $id, Pro::documents(), true ), 'La etapa toma el documento convertido para recuperarlo; no cuenta como pendiente de convertir.' );
	$report = run_doc( $id );
	$data   = tree( $id );
	$q1     = find( $data, 'q1' )['settings'];
	$rows   = find( $data, 'l1' )['settings']['price_list'];
	$check( 'boxed' === $q1['content_width'] && foreign()['display_condition_list'] === $q1['display_condition_list'] && '$12' === $rows[0]['old_price'] && 'Nuevo' === $rows[1]['item_badge'], 'Vuelven del original: content_width, las condiciones apagadas y los subcampos de cada fila.' );
	$codes = codes_of( $report, $id );
	$check( array( 'pro.recovered:blockquote:content_width', 'pro.recovered:blockquote:display_condition_list', 'pro.recovered:price-list:price_list.item_badge', 'pro.recovered:price-list:price_list.old_price' ) === $codes, 'El informe dice qué volvió: ' . implode( ', ', $codes ) );
	$check( $backup === get_metadata( 'post', $id, Migration::BACKUP, true ), 'La copia original no se toca.' );
	$mark = Migration::f8_mark( $id );
	$check( is_array( $mark ) && md5( (string) get_metadata( 'post', $id, '_elementor_data', true ) ) === $mark['hash'], 'La huella F8 queda al día: --revert no lo toma por editado.' );
	$raw    = get_metadata( 'post', $id, '_elementor_data', true );
	$report = run_doc( $id );
	$check( $raw === get_metadata( 'post', $id, '_elementor_data', true ) && ! codes_of( $report, $id ), 'Repetir Migrar no cambia nada ni informa de nuevo.' );
	$check( Migration::revert_document( $id ) && $original === tree( $id ), '--revert devuelve el original.' );
	// 3b. Editado después: sólo se añade lo que falta; lo editado no cambia.
	fresh();
	$edited                                             = $current;
	$edited[0]['elements'][0]['settings']['blockquote_content'] = 'Cita editada';
	$edited[0]['elements'][0]['settings']['content_width']      = 'full';
	array_pop( $edited[0]['elements'][1]['settings']['price_list'] ); // La fila p2 se borró después.
	$id     = migrated_doc( $original, $current, true );
	update_metadata( 'post', $id, '_elementor_data', wp_slash( wp_json_encode( $edited ) ) );
	$report = run_doc( $id );
	$data   = tree( $id );
	$q1     = find( $data, 'q1' )['settings'];
	$check( 'Cita editada' === $q1['blockquote_content'] && 'full' === $q1['content_width'] && ! isset( $q1['display_condition_list'] ), 'Editado: lo editado se queda (content_width «full») y la condición apagada en la copia no vuelve: pudo encenderse antes de convertir.' );
	$rows = find( $data, 'l1' )['settings']['price_list'];
	$check( 1 === count( $rows ) && '$12' === $rows[0]['old_price'], 'La fila que sigue recupera su subcampo; la borrada no vuelve.' );
	$codes = codes_of( $report, $id );
	$check( in_array( 'pro.recover_skipped:price-list:price_list.item_badge', $codes, true ) && ! in_array( 'pro.recovered:blockquote:content_width', $codes, true ), 'El informe dice la fila que no se pudo recuperar: ' . implode( ', ', $codes ) );
	$check( in_array( 'pro.condition_lost:blockquote:display_condition_list', $codes, true ) && '' === (string) get_metadata( 'post', $id, Pro::RECOVER_STAMP, true ), 'Y que no se puede saber la condición (por documento), sin sellarlo: se vuelve a informar en cada Migrar.' );
	// 3c. Condición encendida perdida: vuelve el widget de PRO si el documento sigue como lo dejó la migración.
	fresh();
	$hidden   = w( 'blockquote', array( 'blockquote_content' => 'Oculta' ) + $on, 'q1' );
	$original = array( box( array( $hidden ), 'c1' ) );
	$lost     = old_convert( $hidden );
	$lost['settings']['digitalisimo_visibility_enable'] = '';
	$current  = array( box( array( $lost ), 'c1' ) );
	$check( 'digitalisimo-blockquote' === $current[0]['elements'][0]['widgetType'] && ! isset( $current[0]['elements'][0]['settings']['display_condition_enable'] ), 'Fixture: la versión anterior convirtió el widget y perdió su condición encendida (se veía).' );
	$id     = migrated_doc( $original, $current );
	$report = run_doc( $id );
	$back   = find( tree( $id ), 'q1' );
	$check( 'blockquote' === $back['widgetType'] && 'yes' === $back['settings']['display_condition_enable'] && foreign()['display_condition_list'] === $back['settings']['display_condition_list'] && '' === $back['settings']['digitalisimo_visibility_enable'] && ! isset( $back['settings']['_digitalisimo_pro'] ), 'Vuelve el widget de PRO original con su condición (y los rasgos que le dio la etapa de Element Pack): ' . wp_json_encode( $back ) );
	$check( array( 'pro.condition_kept:blockquote:display_condition_enable', 'pro.condition_kept:blockquote:display_condition_list', 'pro.condition_restored:blockquote:display_condition_enable', 'pro.condition_restored:blockquote:display_condition_list' ) === codes_of( $report, $id ), 'El informe lo dice: ' . implode( ', ', codes_of( $report, $id ) ) );
	// 3d. Lo mismo, editado después: no se pisa y queda por revisar.
	fresh();
	$id = migrated_doc( $original, $current );
	$ed = $current;
	$ed[0]['elements'][0]['settings']['blockquote_content'] = 'Editada después';
	update_metadata( 'post', $id, '_elementor_data', wp_slash( wp_json_encode( $ed ) ) );
	$report = run_doc( $id );
	$check( $ed === tree( $id ), 'Editado: el elemento no se toca.' );
	$item = item_of( $report, $id, 'pro.condition_lost' );
	$check( $item && 'review' === Report::section_of( $item ) && 'q1' === $item['detail']['element'], 'Y el informe lo dice por documento, con el elemento, en «Pendiente de revisar».' );
	// 3e. Copia de ≤ 1.0.20 (sin huella): lo que pasa tal cual vuelve; la condición, sólo con el elemento intacto.
	fresh();
	$id     = migrated_doc( $original, $current, false );
	$report = run_doc( $id );
	$check( 'blockquote' === find( tree( $id ), 'q1' )['widgetType'] && in_array( 'pro.condition_restored:blockquote:display_condition_enable', codes_of( $report, $id ), true ), 'Sin huella, el elemento idéntico a la traducción de su original basta para devolverlo.' );
	// 3f. La etapa `pro` del runner los recorre.
	fresh();
	$quote    = w( 'blockquote', array( 'blockquote_content' => 'Cita' ) + foreign(), 'q1' );
	$a        = migrated_doc( array( box( array( $quote ), 'c1' ) ), array( box( array( old_convert( $quote ) ), 'c1' ) ) );
	$b        = doc( array( box( array( w( 'blockquote', array( 'blockquote_content' => 'Nueva', 'content_width' => 'boxed' ), 'q9' ) ), 'c9' ) ) );
	$kit      = doc( array( box( array( old_convert( $quote ) ), 'c1' ) ) ); // Convertido por un kit: sin copia, no se toca.
	$kit_raw  = get_metadata( 'post', $kit, '_elementor_data', true );
	$check( in_array( $a, Pro::recover_documents(), true ) && ! in_array( $kit, Pro::recover_documents(), true ), 'Sólo son candidatos los convertidos con copia de la migración.' );
	$state    = run_pro_stage();
	$check( 'boxed' === find( tree( $a ), 'q1' )['settings']['content_width'] && 'boxed' === find( tree( $b ), 'q9' )['settings']['content_width'] && 'digitalisimo-blockquote' === find( tree( $b ), 'q9' )['widgetType'], 'La etapa recupera el convertido antes y convierte el nuevo: ' . wp_json_encode( $state['counts'] ?? null ) );
	$check( $kit_raw === get_metadata( 'post', $kit, '_elementor_data', true ), 'Un documento sin copia de la migración no se toca.' );

	// 3g. El informe de la etapa `report` relee la copia original de un documento convertido antes (sin huella, como las
	// copias de ≤ 1.0.20): sus claves de Element Pack ya las trató su etapa y no salen como ajustes de PRO perdidos (de
	// ahí venían las filas `ep_*` del informe real).
	fresh();
	$id = migrated_doc( real_site(), array( box( array( w( 'digitalisimo-blockquote', array( '_digitalisimo_pro' => 'blockquote', 'blockquote_content' => 'Cita', 'content_width' => 'boxed' ), 'q1' ) ), 'c1' ) ), false );
	Report::clear();
	Report::begin( 'run' );
	Runner::describe_backup( $id );
	$codes = codes_of( Report::take(), $id );
	$check( in_array( 'pro.converted:blockquote:', $codes, true ) && ! preg_grep( '/^pro\.unmapped/', $codes ) && ! preg_grep( '/:(ep_|element_pack_)/', $codes ), 'La relectura de la copia no da por perdidas las claves de Element Pack ni las inertes: ' . implode( ', ', $codes ) );

	/* ================================================================ 3h. Hallazgos de la revisión */
	// Copia anterior a la conversión con la huella al día: la copia tenía la condición encendida y otro texto; antes de
	// convertir se apagó y se cambió el texto. La huella coincide, pero el elemento no es la traducción de su copia.
	fresh();
	$copy  = w( 'blockquote', array( 'blockquote_content' => 'Texto viejo', 'display_condition_enable' => 'yes' ) + foreign(), 'q1' );
	$later = w( 'blockquote', array( 'blockquote_content' => 'Texto nuevo' ) + foreign(), 'q1' );
	$now   = array( box( array( old_convert( $later ) ), 'c1' ) );
	$id    = migrated_doc( array( box( array( $copy ), 'c1' ) ), $now );
	$report = run_doc( $id );
	$check( $now === tree( $id ) && in_array( 'pro.condition_lost:blockquote:display_condition_enable', codes_of( $report, $id ), true ), 'Copia vieja con la huella al día: no se devuelve la copia (ni se oculta ni se pierde el texto); el informe dice que no se puede saber: ' . implode( ', ', codes_of( $report, $id ) ) );
	// El CSS de efectos que escribió la etapa de Element Pack: tampoco es la traducción de la copia.
	fresh();
	$copy                                  = w( 'blockquote', array( 'blockquote_content' => 'Cita', 'display_condition_enable' => 'yes' ) + foreign(), 'q1' );
	$fx                                    = old_convert( $copy );
	$fx['settings']['custom_css']          = 'selector{filter:drop-shadow(0 0 4px #000)}';
	$fx['settings']['motion_fx_motion_fx_scrolling'] = 'yes';
	$id     = migrated_doc( array( box( array( $copy ), 'c1' ) ), array( box( array( $fx ), 'c1' ) ) );
	$report = run_doc( $id );
	$check( 'digitalisimo-blockquote' === find( tree( $id ), 'q1' )['widgetType'] && 'yes' === find( tree( $id ), 'q1' )['settings']['motion_fx_motion_fx_scrolling'] && in_array( 'pro.condition_lost:blockquote:display_condition_enable', codes_of( $report, $id ), true ), 'Con el CSS de efectos y el movimiento de la etapa de Element Pack, no se devuelve (no se pierden).' );
	// Widgets anidados: al devolver cambian sólo el tipo y los ajustes (los hijos actuales se quedan) y los hijos se
	// recorren siempre.
	fresh();
	$child_copy = w( 'blockquote', array( 'blockquote_content' => 'Hija' ), 'qh' );
	$child_now  = old_convert( $child_copy );
	$child_now['settings']['blockquote_content'] = 'Hija editada después';
	$nc_copy    = array( 'id' => 'nc', 'elType' => 'widget', 'widgetType' => 'nested-carousel', 'settings' => array( 'display_condition_enable' => 'yes' ) + foreign(), 'elements' => array( box( array( $child_copy ), 'cc' ) ) );
	$nc_now     = old_convert( $nc_copy );
	$nc_now['elements'] = array( box( array( $child_now ), 'cc' ) );
	$id     = migrated_doc( array( $nc_copy ), array( $nc_now ) );
	$report = run_doc( $id );
	$nc     = find( tree( $id ), 'nc' );
	$check( 'nested-carousel' === $nc['widgetType'] && 'yes' === $nc['settings']['display_condition_enable'] && 'digitalisimo-blockquote' === find( $nc['elements'], 'qh' )['widgetType'] && 'Hija editada después' === find( $nc['elements'], 'qh' )['settings']['blockquote_content'], 'Anidado devuelto: vuelve el carrusel de PRO con sus hijos actuales (convertidos y editados), no los de la copia: ' . wp_json_encode( $nc['elements'] ) );
	fresh();
	$child_copy = w( 'blockquote', array( 'blockquote_content' => 'Hija oculta', 'display_condition_enable' => 'yes' ) + foreign(), 'qh' );
	$nc_copy    = array( 'id' => 'nc', 'elType' => 'widget', 'widgetType' => 'nested-carousel', 'settings' => array( 'content_width' => 'boxed' ), 'elements' => array( box( array( $child_copy ), 'cc' ) ) );
	$nc_now     = old_convert( $nc_copy );
	$nc_now['elements'] = array( box( array( old_convert( $child_copy ) ), 'cc' ) );
	$id     = migrated_doc( array( $nc_copy ), array( $nc_now ) );
	$report = run_doc( $id );
	$check( 'blockquote' === find( tree( $id ), 'qh' )['widgetType'] && 'boxed' === find( tree( $id ), 'nc' )['settings']['content_width'], 'Dentro de un anidado que también se recupera, la hija con la condición perdida se revisa y vuelve a ser de PRO.' );
	// restore_legacy(): un widget anidado de PRO con claves de Element Pack que llega a la etapa `pro` conserva sus hijos.
	fresh();
	$id = doc( array( array( 'id' => 'nc', 'elType' => 'widget', 'widgetType' => 'nested-carousel', 'settings' => array( 'bdt_custom_option' => 'x' ), 'elements' => array( box( array( w( 'heading', array( 'title' => 'Diapositiva' ), 'h1' ) ), 'cc' ) ) ) ) );
	run_doc( $id );
	$nc = find( tree( $id ), 'nc' );
	$check( 'digitalisimo-nested-carousel' === $nc['widgetType'] && find( (array) $nc['elements'], 'h1' ) && ! isset( $nc['settings']['bdt_custom_option'] ), 'El anidado convertido conserva sus diapositivas: ' . wp_json_encode( $nc['elements'] ?? null ) );
	// IDs repetidos: en la copia o en el documento no se sabe cuál es cuál; no se toca y se informa.
	fresh();
	$a = w( 'blockquote', array( 'blockquote_content' => 'Primera', 'content_width' => 'boxed' ), 'qd' );
	$b = w( 'blockquote', array( 'blockquote_content' => 'Segunda' ), 'qd' );
	$now    = array( box( array( old_convert( $a ), old_convert( $b ) ), 'c1' ) );
	$id     = migrated_doc( array( box( array( $a, $b ), 'c1' ) ), $now );
	$report = run_doc( $id );
	$check( $now === tree( $id ) && in_array( 'pro.recover_ambiguous:blockquote:', codes_of( $report, $id ), true ), 'Un id repetido: nada se cruza de un elemento a otro y el informe lo dice: ' . implode( ', ', codes_of( $report, $id ) ) );
	// El id repetido sólo en la copia (uno se borró después): tampoco se sabe cuál era.
	fresh();
	$id     = migrated_doc( array( box( array( $a, $b ), 'c1' ) ), array( box( array( old_convert( $b ) ), 'c1' ) ) );
	$report = run_doc( $id );
	$check( ! isset( find( tree( $id ), 'qd' )['settings']['content_width'] ) && in_array( 'pro.recover_ambiguous:blockquote:', codes_of( $report, $id ), true ), 'Repetido sólo en la copia: no recibe lo del otro y se informa.' );
	// Las mismas medidas guardadas en otro orden (el editor reordena al guardar) siguen siendo el mismo elemento.
	fresh();
	$copy  = w( 'blockquote', array( 'blockquote_content' => 'Orden', '_margin' => array( 'unit' => 'px', 'top' => '4', 'right' => '0', 'bottom' => '4', 'left' => '0', 'isLinked' => false ), 'display_condition_enable' => 'yes' ) + foreign(), 'q1' );
	$now   = old_convert( $copy );
	$now['settings']['_margin'] = array( 'top' => '4', 'right' => '0', 'bottom' => '4', 'left' => '0', 'isLinked' => false, 'unit' => 'px' );
	$id    = migrated_doc( array( box( array( $copy ), 'c1' ) ), array( box( array( $now ), 'c1' ) ) );
	run_doc( $id );
	$check( 'blockquote' === find( tree( $id ), 'q1' )['widgetType'], 'Medidas en otro orden: se compara a fondo y vuelve el widget de PRO.' );
	list( $safe, $log, $kept ) = $net->invoke( null,
		array( box( array( w( 'digitalisimo-blockquote', array( '_digitalisimo_pro' => 'blockquote' ), 'qd' ), w( 'digitalisimo-blockquote', array( '_digitalisimo_pro' => 'blockquote', 'blockquote_content' => 'Segunda' ), 'qd' ) ), 'c1' ) ),
		array( box( array( w( 'blockquote', $on, 'qd' ), w( 'blockquote', array( 'blockquote_content' => 'Segunda', 'display_condition_list' => array( array( '_id' => 'z' ) ) ), 'qd' ) ), 'c1' ) ),
		array(
			array( 'source' => 'blockquote', 'target' => 'digitalisimo-blockquote', 'element' => 'qd', 'codes' => array( array( 'code' => 'pro.unmapped', 'key' => 'display_condition_enable' ), array( 'code' => 'pro.unmapped', 'key' => 'display_condition_list' ) ) ),
			array( 'source' => 'blockquote', 'target' => 'digitalisimo-blockquote', 'element' => 'qd', 'codes' => array( array( 'code' => 'pro.unmapped', 'key' => 'display_condition_list' ) ) ),
		),
		array()
	);
	$check( 'blockquote' === $safe[0]['elements'][0]['widgetType'] && $on === $safe[0]['elements'][0]['settings'] && 'digitalisimo-blockquote' === $safe[0]['elements'][1]['widgetType'] && 'Segunda' === $safe[0]['elements'][1]['settings']['blockquote_content'] && 1 === count( $kept ), 'La red de seguridad, por posición: con ids repetidos devuelve sólo el de la condición encendida y no duplica nada.' );
	$check( Pro::condition_active( 'eael_cl_logics', array( 'eael_cl_logics' => array( 1 ), 'eael_cl_enable' => 'yes' ), array( 'eael_cl_logics', 'eael_cl_enable' ) ) && ! Pro::condition_active( 'eael_cl_logics', array( 'eael_cl_logics' => array( 1 ), 'eael_cl_enable' => '' ), array( 'eael_cl_logics', 'eael_cl_enable' ) ) && ! Pro::condition_active( 'pa_condition_repeater', array( 'pa_condition_repeater' => array( 1 ) ) ), 'La red también usa el interruptor del mismo prefijo y las familias declaradas.' );
	// Filas sin `_id`: se casan por su posición, sin avisos falsos.
	fresh();
	$rows_copy = w( 'price-list', array( 'price_list' => array( array( 'title' => 'Uno', 'price' => '$1', 'old_price' => '$2' ), array( 'title' => 'Dos', 'price' => '$3' ) ) ), 'l1' );
	$id        = migrated_doc( array( box( array( $rows_copy ), 'c1' ) ), array( box( array( old_convert( $rows_copy ) ), 'c1' ) ) );
	$report    = run_doc( $id );
	$check( '$2' === find( tree( $id ), 'l1' )['settings']['price_list'][0]['old_price'] && ! preg_grep( '/recover_skipped/', codes_of( $report, $id ) ), 'Filas sin _id: vuelve el subcampo por posición y no hay aviso falso.' );
	// `__globals__` de un ajuste que pasa tal cual.
	fresh();
	$gcopy  = w( 'blockquote', array( 'blockquote_content' => 'G', '__globals__' => array( 'content_width' => 'globals/x' ) ), 'q1' );
	$id     = migrated_doc( array( box( array( $gcopy ), 'c1' ) ), array( box( array( old_convert( $gcopy ) ), 'c1' ) ) );
	run_doc( $id );
	$check( 'globals/x' === ( find( tree( $id ), 'q1' )['settings']['__globals__']['content_width'] ?? null ), 'También vuelve su valor global.' );
	// Muchos documentos: la etapa los revisa una vez y los sella; la siguiente Migrar ya no los lee.
	fresh();
	$many = array();
	for ( $i = 0; $i < 60; $i++ ) {
		$q      = w( 'blockquote', array( 'blockquote_content' => 'Cita ' . $i, 'content_width' => 'boxed' ), 'q' . $i );
		$many[] = migrated_doc( array( box( array( $q ), 'c' . $i ) ), array( box( array( old_convert( $q ) ), 'c' . $i ) ) );
	}
	$check( 60 === count( Pro::recover_documents() ), 'Los 60 convertidos con copia son candidatos.' );
	run_pro_stage();
	$check( 'boxed' === find( tree( $many[59] ), 'q59' )['settings']['content_width'] && Pro::stamp() === get_metadata( 'post', $many[0], Pro::RECOVER_STAMP, true ), 'La etapa los recupera y los sella.' );
	$check( array() === Pro::recover_documents() && array() === Pro::stage_documents(), 'La siguiente Migrar no vuelve a leerlos.' );
	$check( Pro::RECOVER_VERSION . '|4.3.0.999' === Pro::stamp(), 'El sello lleva la versión de Elements: con otra, se vuelven a revisar.' );
	update_metadata( 'post', $many[1], Pro::RECOVER_STAMP, Pro::RECOVER_VERSION . '|0.0.0' );
	$check( array( $many[1] ) === Pro::recover_documents(), 'Un sello de otra versión de Elements no cuenta.' );
	$check( Migration::revert_document( $many[0] ) && '' === (string) get_metadata( 'post', $many[0], Pro::RECOVER_STAMP, true ), '--revert quita el sello.' );

	/* ================================================================ 3i. Segunda revisión */
	// A. Una conversión hecha por esta versión, seguida de otra Migrar con la copia vieja: la copia tenía la condición
	// encendida; después la persona la apagó (quitó el interruptor; el repetidor queda con su fila por defecto).
	fresh();
	$copy = w( 'blockquote', array( 'blockquote_content' => 'Visible', 'display_condition_enable' => 'yes' ) + foreign(), 'q1' );
	$off  = w( 'blockquote', array( 'blockquote_content' => 'Visible' ) + foreign(), 'q1' );
	$id   = doc( array( box( array( $off ), 'c1' ) ), array( Migration::BACKUP => wp_json_encode( array( 'time' => 1, 'data' => wp_json_encode( array( box( array( $copy ), 'c1' ) ) ) ) ) ) );
	run_doc( $id );
	$q1 = find( tree( $id ), 'q1' );
	$check( 'digitalisimo-blockquote' === $q1['widgetType'] && isset( $q1['settings']['display_condition_list'] ) && Pro::stamp() === get_metadata( 'post', $id, Pro::RECOVER_STAMP, true ), 'Esta versión convierte el widget con la condición apagada, tal cual, y sella el documento.' );
	$check( ! in_array( $id, Pro::recover_documents(), true ), 'La siguiente Migrar no lo vuelve a revisar.' );
	delete_metadata( 'post', $id, Pro::RECOVER_STAMP ); // Aun sin el sello (otra versión de Elements), nada se deshace.
	$raw    = get_metadata( 'post', $id, '_elementor_data', true );
	$report = run_doc( $id );
	$check( $raw === get_metadata( 'post', $id, '_elementor_data', true ) && ! codes_of( $report, $id ), 'Con la copia vieja (encendida), el widget ya convertido no vuelve a PRO ni se informa nada: ' . implode( ', ', codes_of( $report, $id ) ) );
	// Lo mismo editado después: tampoco un «no se puede saber» falso, y queda sellado.
	$ed = tree( $id );
	$ed[0]['elements'][0]['settings']['blockquote_content'] = 'Editada';
	update_metadata( 'post', $id, '_elementor_data', wp_slash( wp_json_encode( $ed ) ) );
	delete_metadata( 'post', $id, Pro::RECOVER_STAMP );
	$report = run_doc( $id );
	$check( $ed === tree( $id ) && ! codes_of( $report, $id ) && Pro::stamp() === get_metadata( 'post', $id, Pro::RECOVER_STAMP, true ), 'Editado: ningún pro.condition_lost falso y el documento queda sellado.' );
	// B. Un ajuste propio editado después (la leyenda del punto de interés) cuenta como edición: no se devuelve a PRO.
	fresh();
	$hot  = w( 'hotspot', array( 'display_condition_enable' => 'yes' ) + foreign(), 'h1' );
	$conv = old_convert( $hot );
	$conv['settings']['digitalisimo_hotspot_caption'] = 'Leyenda añadida después';
	$id     = migrated_doc( array( box( array( $hot ), 'c1' ) ), array( box( array( $conv ), 'c1' ) ) );
	$report = run_doc( $id );
	$check( 'digitalisimo-hotspot' === find( tree( $id ), 'h1' )['widgetType'] && 'Leyenda añadida después' === find( tree( $id ), 'h1' )['settings']['digitalisimo_hotspot_caption'] && in_array( 'pro.condition_lost:hotspot:display_condition_enable', codes_of( $report, $id ), true ), 'La leyenda editada se queda: no vuelve a PRO y se informa que no se puede saber.' );
	// C. --revert sobre un documento editado y luego recuperado: la recuperación no pone la huella al día.
	fresh();
	$quote = w( 'blockquote', array( 'blockquote_content' => 'Cita' ) + foreign(), 'q1' );
	$id    = migrated_doc( array( box( array( $quote ), 'c1' ) ), array( box( array( old_convert( $quote ) ), 'c1' ) ) );
	$ed    = tree( $id );
	$ed[0]['elements'][0]['settings']['blockquote_content'] = 'Editada después de migrar';
	update_metadata( 'post', $id, '_elementor_data', wp_slash( wp_json_encode( $ed ) ) );
	run_doc( $id );
	$check( 'boxed' === find( tree( $id ), 'q1' )['settings']['content_width'] && md5( (string) get_metadata( 'post', $id, '_elementor_data', true ) ) !== Migration::f8_mark( $id )['hash'], 'Editado y recuperado: content_width vuelve, pero la huella no se pone al día.' );
	$rev = Runner::revert();
	$check( $rev['skipped'] >= 1 && 'Editada después de migrar' === find( tree( $id ), 'q1' )['settings']['blockquote_content'], '--revert lo sigue viendo editado y no devuelve la copia.' );
	// D. Una condición con valor y un interruptor apagado de otro efecto del mismo plugin: falla cerrado.
	list( , $codes ) = Pro_Translator::translate( array( 'xw_conditions' => array( array( '_id' => 'r' ) ), 'xw_tooltip_enable' => '' ), Registry::map( 'blockquote' ) );
	$check( in_array( 'xw_conditions', array_column( array_filter( $codes, static function ( $c ) { return ! empty( $c['keep'] ); } ), 'key' ), true ), 'Sólo un interruptor de condiciones apaga una condición.' );
	$check( Pro::condition_active( 'xw_conditions', array( 'xw_conditions' => array( 1 ), 'xw_tooltip_enable' => '' ), array( 'xw_conditions', 'xw_tooltip_enable' ) ), 'Lo mismo en la red de seguridad.' );
	// F. Ids repetidos sin original en la copia: nada que avisar; y en la red, la fila del registro que corresponde.
	fresh();
	$id = migrated_doc( array( box( array( w( 'heading', array(), 'zz' ) ), 'c1' ) ), array( box( array( w( 'digitalisimo-blockquote', array( '_digitalisimo_pro' => 'blockquote' ), 'qd' ), w( 'digitalisimo-blockquote', array( '_digitalisimo_pro' => 'blockquote' ), 'qd' ) ), 'c1' ) ) );
	$report = run_doc( $id );
	$check( ! codes_of( $report, $id ) && Pro::stamp() === get_metadata( 'post', $id, Pro::RECOVER_STAMP, true ), 'Repetidos que la copia no tiene: ni aviso ni documento sin sellar.' );
	list( , $log, $kept ) = $net->invoke( null,
		array( box( array( w( 'digitalisimo-blockquote', array( '_digitalisimo_pro' => 'blockquote' ), 'qd' ), w( 'digitalisimo-blockquote', array( '_digitalisimo_pro' => 'blockquote' ), 'qd' ) ), 'c1' ) ),
		array( box( array( w( 'blockquote', array( 'display_condition_list' => array( array( '_id' => 'z' ) ) ), 'qd' ), w( 'blockquote', $on, 'qd' ) ), 'c1' ) ),
		array(
			array( 'source' => 'blockquote', 'target' => 'digitalisimo-blockquote', 'element' => 'qd', 'codes' => array( array( 'code' => 'pro.unmapped', 'key' => 'display_condition_list' ) ), 'first' => 1 ),
			array( 'source' => 'blockquote', 'target' => 'digitalisimo-blockquote', 'element' => 'qd', 'codes' => array( array( 'code' => 'pro.unmapped', 'key' => 'display_condition_enable' ), array( 'code' => 'pro.unmapped', 'key' => 'display_condition_list' ) ) ),
		),
		array()
	);
	$check( 1 === count( $log ) && ! empty( $log[0]['first'] ) && 1 === count( $kept ), 'Con ids repetidos, la red quita del registro la fila del elemento devuelto (la segunda), no la primera.' );

	/* ================================================================ 4. WooCommerce inactivo */
	fresh();
	manager( array( 'digitalisimo-blockquote' ) );
	$id     = doc( array( box( array( w( 'wc-add-to-cart', array( 'product_id' => '7' ), 'a1' ), w( 'blockquote', array(), 'q1' ) ), 'c1' ) ) );
	$report = run_doc( $id );
	$item   = item_of( $report, $id, 'pro.target_dependency' );
	$check( $item && 'WooCommerce' === $item['detail']['name'] && 'wc-add-to-cart' === $item['detail']['widget'] && ! item_of( $report, $id, 'pro.target_missing' ), 'Sin WooCommerce: pro.target_dependency con el plugin que falta.' );
	$labels = include dirname( __DIR__ ) . '/digitalisimo-migration/legacy/report/codes-pro.php';
	$check( false === strpos( $labels['pro.target_dependency']['label'], 'Actualiza DIGITALÍSIMO Elements' ) && false !== strpos( $labels['pro.target_dependency']['label'], 'No hace falta actualizar' ), 'El aviso no pide actualizar Elements.' );
	$check( 'wc-add-to-cart' === find( tree( $id ), 'a1' )['widgetType'] && 'blockquote' === find( tree( $id ), 'q1' )['widgetType'], 'El documento se queda como estaba (nunca a medias).' );
	$check( 'Pendiente: digitalisimo-woo-add-to-cart sólo se registra con WooCommerce activo' === Pro::status( 'wc-add-to-cart', '', 'C7' ), 'El inventario lo dice igual: ' . Pro::status( 'wc-add-to-cart', '', 'C7' ) );
	$check( '' === Pro::missing_dependency( 'blockquote' ), 'Un widget sin dependencia no la informa.' );
	if ( ! class_exists( 'WooCommerce', false ) ) {
		eval( 'class WooCommerce {} function WC() { return null; }' ); // phpcs:ignore Squiz.PHP.Eval
	}
	$report = run_doc( $id );
	$check( item_of( $report, $id, 'pro.target_missing' ) && ! item_of( $report, $id, 'pro.target_dependency' ), 'Con WooCommerce activo y el widget propio sin registrar, el aviso de siempre.' );

	/* ================================================================ 5. Restos de una migración vieja (modo restringido) */
	fresh();
	$old_backup = wp_json_encode( array( 'time' => 1, 'data' => '[]' ) );
	$inert      = doc( array( box( array(), 'c1' ) ), array( Migration::BACKUP => $old_backup, '_elementor_page_settings' => array( 'element_pack_lightbox_bg' => '#000', 'element_pack_global_tooltip_color' => '#fff', 'ep_grid_line_color' => '#eee' ) ) );
	$active     = doc( array( box( array(), 'c1', array( 'section_sticky_on' => 'yes' ) ) ), array( Migration::BACKUP => $old_backup, '_elementor_page_settings' => array( 'ep_content_protector_enable' => 'yes', 'element_pack_lightbox_bg' => '#000' ) ) );
	$check( Migration::restricted( $inert ) && Migration::restricted( $active ), 'Fixture: documentos migrados con una versión anterior (modo restringido).' );
	Report::clear();
	Report::begin( 'run' );
	Migration::convert_document( $inert );
	Migration::convert_document( $active );
	$report = Report::take();
	$check( ! codes_of( $report, $inert, 'core.residual' ), 'Inertes: ninguna línea por documento.' );
	$site = array_column( (array) ( $report['site']['items'] ?? array() ), 'code' );
	$check( 1 === count( array_keys( $site, 'core.residual_inert', true ) ), 'Una sola línea del sitio: ' . implode( ', ', $site ) );
	$names = array();
	foreach ( (array) ( $report['docs'][ $active ]['items'] ?? array() ) as $it ) {
		if ( 'core.residual' === $it['code'] ) {
			$names[] = (string) $it['detail']['name'];
		}
	}
	$protector = Dispatcher::label( 'content-protector' );
	$sticky    = Dispatcher::label( (string) Dispatcher::extension_of( 'section_sticky_on', true ) );
	$check( in_array( $protector, $names, true ) && in_array( $sticky, $names, true ), 'Lo encendido, por documento con el nombre de la extensión (qué revisar): ' . implode( ' | ', $names ) );
	$check( array( 'element_pack_lightbox_bg' => '#000', 'element_pack_global_tooltip_color' => '#fff', 'ep_grid_line_color' => '#eee' ) === get_metadata( 'post', $inert, '_elementor_page_settings', true ), 'Los restos no se tocan.' );
	$catalog = Report::catalog();
	$check( 'converted' === $catalog['core.residual_inert']['section'] && false !== strpos( $catalog['core.residual_inert']['label'], 'no hace falta revisarlos' ), 'Los inertes van en «Convertido», diciendo que no hace falta revisarlos.' );

	f8_done( 'Ajustes de los widgets de PRO, recuperación, WooCommerce y restos heredados', 50 );
}
