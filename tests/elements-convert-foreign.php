<?php
/**
 * Ajustes guardados que no son del widget de PRO en el núcleo de conversión de DIGITALÍSIMO Elements
 * (`Convert\Translator::foreign()`, `Converter::element()`/`tree()` con `$kept` y el importador de kits).
 *
 * Caso real (2026-11-18): una migración dejó «Clave que el mapa no declara» en 92 ajustes que no eran de los widgets de
 * PRO. Cubre, con fixtures sintéticos y los mapas reales:
 * - `content_width` (ajuste de contenedor guardado en un widget) y la clave sin nombre: inertes (`pro.inert`); el primero
 *   pasa tal cual (también por dispositivo y en `__dynamic__`), la segunda se quita; un mapa que declara
 *   `content_width` (Megamenú) sigue su regla;
 * - condiciones de visualización de otro plugin (`display_condition_*`): apagadas pasan tal cual; encendidas, el widget
 *   NO se convierte (`pro.condition_kept`, `keep`), ni con `Converter::element()` ni con `tree()` (que las deja en
 *   `$kept`, a cualquier profundidad, sin tocar el resto del árbol); una condición o visibilidad desconocida con valor
 *   también deja el widget como está (falla cerrado) y vacía pasa;
 * - subcampos de repetidores declarados `inert` en los mapas reales (`form_fields.dynamic`, `price_list.old_price` y
 *   `price_list.item_badge`): pasan en su fila;
 * - una clave desconocida sigue en «Pendiente de revisar» (`pro.unmapped`) con un motivo que dice qué se pierde;
 * - los kits: el nivel de los códigos nuevos y el aviso de un widget que se queda como widget de PRO.
 *
 * Con el código anterior falla: los inertes y las condiciones salían como `pro.unmapped` y no pasaban, y una condición
 * encendida se perdía al convertir.
 */

namespace {
	require_once __DIR__ . '/fixtures/php74.php'; // PHP 7.4: polyfills de WordPress y reflexión abierta.
}
namespace Elementor {
	class Plugin {
		public static $instance;
		public $widgets_manager;
	}
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	$GLOBALS['cv'] = array( 'filters' => array(), 'locale' => array( 'current' => 'es_ES', 'stack' => array(), 'switches' => 0 ) );
	function get_locale() { return 'es_ES'; }
	function switch_to_locale( $locale ) {
		if ( $locale === $GLOBALS['cv']['locale']['current'] ) {
			return false;
		}
		$GLOBALS['cv']['locale']['stack'][] = $GLOBALS['cv']['locale']['current'];
		$GLOBALS['cv']['locale']['current'] = $locale;
		return true;
	}
	function restore_previous_locale() { $GLOBALS['cv']['locale']['current'] = array_pop( $GLOBALS['cv']['locale']['stack'] ); return $GLOBALS['cv']['locale']['current']; }
	function add_filter( $hook, $cb, $priority = 10, $args = 1 ) { $GLOBALS['cv']['filters'][ $hook ][] = $cb; }
	function get_post_meta( $id, $key = '', $single = false ) { return ''; }

	$GLOBALS['fx'] = array( 'n' => 0, 'fail' => 0 );
	function fx_ok( $ok, string $message ): void {
		++$GLOBALS['fx']['n'];
		if ( ! $ok ) {
			++$GLOBALS['fx']['fail'];
			echo 'FALLA: ' . $message . "\n";
		}
	}
	/** `código:clave` de unos códigos del traductor, en orden. */
	function fx_codes( array $codes ): array {
		$out = array();
		foreach ( $codes as $code ) {
			$out[] = $code['code'] . ':' . $code['key'] . ( ! empty( $code['keep'] ) ? ':keep' : '' );
		}
		sort( $out );
		return $out;
	}
	function fx_widget( string $type, array $settings, string $id = 'w1' ): array {
		return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => array() );
	}

	$elements = __DIR__ . '/../digitalisimo-elements/';
	require $elements . 'modules/digitalisimo-convert/class-module.php';
	\Digitalisimo\Elements\Convert\Module::init();
	use Digitalisimo\Elements\Convert\Converter;
	use Digitalisimo\Elements\Convert\Registry;
	use Digitalisimo\Elements\Convert\Translator;

	$quote = Registry::map( 'blockquote' );
	fx_ok( is_array( $quote ) && 'digitalisimo-blockquote' === $quote['target'], 'El mapa real de la Cita está listo.' );

	/* 1. content_width y la clave sin nombre: inertes. */
	list( $out, $codes ) = Translator::translate( array( 'blockquote_content' => 'Hola', 'content_width' => 'boxed', 'content_width_tablet' => 'full', '' => 'roto', '__dynamic__' => array( 'content_width_mobile' => '[elementor-tag id="x" name="post-title"]' ) ), $quote );
	fx_ok( 'boxed' === ( $out['content_width'] ?? null ) && 'full' === ( $out['content_width_tablet'] ?? null ) && isset( $out['__dynamic__']['content_width_mobile'] ), 'content_width pasa tal cual, por dispositivo y en __dynamic__: ' . json_encode( $out ) );
	fx_ok( ! array_key_exists( '', $out ), 'La clave sin nombre se quita.' );
	fx_ok( array( 'pro.inert:', 'pro.inert:content_width', 'pro.inert:content_width_mobile', 'pro.inert:content_width_tablet' ) === fx_codes( $codes ),'Sólo pro.inert (ningún pro.unmapped): ' . implode( ', ', fx_codes( $codes ) ) );
	foreach ( $codes as $code ) {
		fx_ok( 'converted' === $code['section'] && false === strpos( $code['reason'], 'mapa no declara' ) && '' !== $code['reason'], 'Cada inerte en «Convertido», con su motivo: ' . $code['reason'] );
	}
	$mega = Registry::map( 'mega-menu' );
	if ( is_array( $mega ) ) {
		list( $out, $codes ) = Translator::translate( array( 'content_width' => 'fit_to_content' ), $mega );
		fx_ok( 'fit_to_content' === ( $out['content_width'] ?? null ) && ! in_array( 'pro.inert:content_width', fx_codes( $codes ), true ), 'El Megamenú declara content_width: sigue su regla, sin pro.inert: ' . implode( ', ', fx_codes( $codes ) ) );
	}

	/* 2. Condiciones de visualización de otro plugin. */
	$rules = array( array( '_id' => 'r1', 'display_condition_key' => 'browser', 'display_condition_operator' => 'is', 'display_condition_browser' => 'chrome' ) );
	list( $out, $codes ) = Translator::translate( array( 'blockquote_content' => 'Hola', 'display_condition_list' => $rules ), $quote );
	fx_ok( $rules === ( $out['display_condition_list'] ?? null ) && array( 'pro.inert:display_condition_list' ) === fx_codes( $codes ), 'Apagadas (sin el interruptor, que Elementor no guarda): pasan tal cual: ' . implode( ', ', fx_codes( $codes ) ) );
	list( , $codes ) = Translator::translate( array( 'display_condition_enable' => '', 'display_condition_list' => $rules ), $quote );
	fx_ok( array( 'pro.inert:display_condition_enable', 'pro.inert:display_condition_list' ) === fx_codes( $codes ), 'Con el interruptor vacío, también.' );
	$on = array( 'blockquote_content' => 'Oculta en Chrome', 'display_condition_enable' => 'yes', 'display_condition_list' => $rules );
	list( , $codes ) = Translator::translate( $on, $quote );
	fx_ok( array( 'pro.condition_kept:display_condition_enable:keep', 'pro.condition_kept:display_condition_list:keep' ) === fx_codes( $codes ), 'Encendidas: keep: ' . implode( ', ', fx_codes( $codes ) ) );
	$codes     = array();
	$converted = Converter::element( fx_widget( 'blockquote', $on, 'q9' ), $codes );
	fx_ok( null === $converted && 2 === count( $codes ) && 'pro.condition_kept' === $codes[0]['code'] && 'review' === $codes[0]['section'] && 'q9' === $codes[0]['element'] && 'blockquote' === $codes[0]['source'], 'Converter::element(): no lo convierte y deja sólo el motivo, con el elemento: ' . json_encode( $codes ) );
	fx_ok( false !== strpos( $codes[0]['reason'], 'display_condition_enable' ), 'El motivo dice qué interruptor lo impide.' );
	$tree = array(
		array( 'id' => 'c1', 'elType' => 'container', 'settings' => array(), 'elements' => array(
			fx_widget( 'blockquote', array( 'blockquote_content' => 'Se convierte', 'content_width' => 'boxed' ), 'q1' ),
			array( 'id' => 'c2', 'elType' => 'container', 'settings' => array(), 'elements' => array( fx_widget( 'blockquote', $on, 'q2' ) ) ),
		) ),
	);
	$log  = array();
	$kept = array();
	$new  = Converter::tree( $tree, $log, false, $kept );
	fx_ok( 1 === count( $log ) && 'q1' === $log[0]['element'] && 'digitalisimo-blockquote' === $new[0]['elements'][0]['widgetType'], 'tree(): el que no tiene condición se convierte.' );
	fx_ok( 1 === count( $kept ) && 'q2' === $kept[0]['element'] && 'blockquote' === $kept[0]['source'] && 'digitalisimo-blockquote' === $kept[0]['target'] && $tree[0]['elements'][1] === $new[0]['elements'][1], 'tree(): el anidado con la condición encendida queda idéntico, en $kept: ' . json_encode( $kept ) );
	$log = array();
	Converter::tree( $tree, $log );
	fx_ok( 1 === count( $log ), 'Sin $kept (quien llama con la firma anterior), el que se queda tampoco sale como convertido.' );
	// Genéricas: un nombre de condición o visibilidad desconocido falla cerrado con valor, y pasa vacío.
	list( , $codes ) = Translator::translate( array( 'xa_visibility_rules' => array( array( 'role' => 'guest' ) ) ), $quote );
	fx_ok( array( 'pro.condition_kept:xa_visibility_rules:keep' ) === fx_codes( $codes ), 'Visibilidad desconocida con reglas: keep.' );
	list( $out, $codes ) = Translator::translate( array( 'xa_conditions' => 'no', 'xb_condition_rules' => array() ), $quote );
	fx_ok( array( 'pro.inert:xa_conditions', 'pro.inert:xb_condition_rules' ) === fx_codes( $codes ) && 'no' === $out['xa_conditions'] && array() === $out['xb_condition_rules'], 'Condiciones desconocidas vacías o en «no»: pasan.' );
	list( , $codes ) = Translator::translate( array( '__dynamic__' => array( 'display_condition_value' => '[tag]' ), 'display_condition_enable' => 'yes' ), $quote );
	fx_ok( in_array( 'pro.condition_kept:display_condition_value:keep', fx_codes( $codes ), true ), 'Una condición sólo dinámica, con el interruptor encendido, también: ' . implode( ', ', fx_codes( $codes ) ) );

	/* 3. Subcampos de repetidores declarados en los mapas reales. */
	$price = Registry::map( 'price-list' );
	list( $out, $codes ) = Translator::translate( array( 'price_list' => array( array( '_id' => 'p1', 'title' => 'Plan', 'price' => '$9', 'old_price' => '$12', 'item_badge' => 'Oferta' ) ) ), $price );
	fx_ok( '$12' === ( $out['price_list'][0]['old_price'] ?? null ) && 'Oferta' === ( $out['price_list'][0]['item_badge'] ?? null ) && 'p1' === $out['price_list'][0]['_id'], 'Lista de precios: precio anterior y distintivo pasan en su fila: ' . json_encode( $out['price_list'] ?? null ) );
	fx_ok( array( 'pro.inert:price_list.item_badge', 'pro.inert:price_list.old_price' ) === fx_codes( $codes ), 'Con pro.inert y su ruta: ' . implode( ', ', fx_codes( $codes ) ) );
	$form = Registry::map( 'form' );
	list( $out, $codes ) = Translator::translate( array( 'form_name' => 'Contacto', 'form_fields' => array( array( '_id' => 'f1', 'custom_id' => 'name', 'field_type' => 'text', 'dynamic' => array( 'active' => 'yes' ) ) ) ), $form );
	fx_ok( array( 'active' => 'yes' ) === ( $out['form_fields'][0]['dynamic'] ?? null ), 'Formulario: «dynamic» pasa en su fila.' );
	fx_ok( in_array( 'pro.inert:form_fields.dynamic', fx_codes( $codes ), true ) && ! preg_grep( '/^pro\.unmapped/', fx_codes( $codes ) ), 'Formulario: pro.inert y ningún pro.unmapped: ' . implode( ', ', fx_codes( $codes ) ) );

	/* 4. Una clave desconocida sigue en «Pendiente de revisar», sin pasar y diciendo qué se pierde. */
	list( $out, $codes ) = Translator::translate( array( 'zz_misterio' => 'x' ), $quote );
	fx_ok( ! array_key_exists( 'zz_misterio', $out ) && array( 'pro.unmapped:zz_misterio' ) === fx_codes( $codes ) && 'review' === $codes[0]['section'], 'Desconocida: pro.unmapped.' );
	fx_ok( false === strpos( $codes[0]['reason'], 'mapa no declara' ) && false !== strpos( $codes[0]['reason'], 'se pierde' ), 'El motivo ya no dice «clave que el mapa no declara»: ' . $codes[0]['reason'] );
	fx_ok( null === Translator::foreign( 'zz_misterio', array() ) && 'keep' === Translator::foreign( 'display_condition_list', array( 'display_condition_enable' => 'yes' ) )['state'], 'foreign() es la única regla (la usa también Migración).' );

	/* 6. Otros plugins: familias declaradas (repetidor guardado con su fila por defecto y el interruptor apagado) y la
	 * regla del interruptor del mismo prefijo para lo que no está declarado. */
	$families = array(
		'Premium Addons' => array( array( 'pa_condition_repeater' => array( array( '_id' => 'a1', 'pa_condition_key' => 'browser' ) ) ), 'pa_display_conditions_switcher' ),
		'PowerPack'      => array( array( 'pp_display_conditions' => array( array( '_id' => 'b1', 'pp_condition_key' => 'login_status' ) ) ), 'pp_display_conditions_enable' ),
		'JetEngine'      => array( array( 'jedv_conditions' => array( array( '_id' => 'c1', 'jedv_condition' => 'equal' ) ) ), 'jedv_enabled' ),
	);
	foreach ( $families as $name => $case ) {
		list( $settings, $switch ) = $case;
		$key = (string) array_keys( $settings )[0];
		list( $out, $codes ) = Translator::translate( $settings, $quote );
		fx_ok( array( 'pro.inert:' . $key ) === fx_codes( $codes ) && $settings[ $key ] === $out[ $key ] && ! empty( $codes[0]['condition'] ), "$name: la fila por defecto con el interruptor apagado pasa tal cual, marcada como condición: " . implode( ', ', fx_codes( $codes ) ) );
		list( , $codes ) = Translator::translate( $settings + array( $switch => 'yes' ), $quote );
		fx_ok( in_array( 'pro.condition_kept:' . $key . ':keep', fx_codes( $codes ), true ), "$name: con $switch encendido, el widget se queda como de PRO: " . implode( ', ', fx_codes( $codes ) ) );
	}
	$logics = array( array( '_id' => 'e1', 'logic_type' => 'login_status' ) );
	list( , $codes ) = Translator::translate( array( 'eael_cl_logics' => $logics, 'eael_cl_enable' => 'yes' ), $quote );
	fx_ok( array( 'pro.condition_kept:eael_cl_enable:keep', 'pro.condition_kept:eael_cl_logics:keep' ) === fx_codes( $codes ), 'Sin declarar y sin «condition» en el nombre: un interruptor encendido del mismo prefijo deja el widget como de PRO: ' . implode( ', ', fx_codes( $codes ) ) );
	list( $out, $codes ) = Translator::translate( array( 'eael_cl_logics' => $logics, 'eael_cl_enable' => '' ), $quote );
	fx_ok( array( 'pro.inert:eael_cl_enable', 'pro.inert:eael_cl_logics' ) === fx_codes( $codes ) && $logics === $out['eael_cl_logics'], 'Con ese interruptor apagado, pasan tal cual: ' . implode( ', ', fx_codes( $codes ) ) );
	list( , $codes ) = Translator::translate( array( 'eael_cl_logics' => $logics ), $quote );
	fx_ok( array( 'pro.unmapped:eael_cl_logics' ) === fx_codes( $codes ), 'Sin interruptor ni nombre de condición: sigue en pro.unmapped.' );
	list( , $codes ) = Translator::translate( array( 'zz_fx_enable' => 'yes' ), $quote );
	fx_ok( array( 'pro.condition_kept:zz_fx_enable:keep' ) === fx_codes( $codes ), 'Un interruptor ajeno encendido, solo: keep.' );
	list( , $codes ) = Translator::translate( array( 'zz_rules' => 'a', 'zz_show' => 'yes' ), array( 'source' => 'zz', 'target' => 'digitalisimo-zz', 'keep' => array( 'zz_show' ) ) );
	fx_ok( array( 'pro.unmapped:zz_rules' ) === fx_codes( $codes ), 'Un ajuste declarado del mapa nunca hace de interruptor de una clave ajena.' );
	list( , $codes ) = Translator::translate( array( 'xv_conditions' => array( array( '_id' => 'x' ) ), 'xv_switcher' => '' ), $quote );
	fx_ok( array( 'pro.inert:xv_conditions', 'pro.inert:xv_switcher' ) === fx_codes( $codes ), 'Una condición desconocida con su interruptor del mismo prefijo apagado: pasa (no falla cerrado por la fila por defecto).' );

	/* 5. Kits: niveles de los códigos nuevos y el aviso de lo que se queda como widget de PRO. */
	$kits = (string) file_get_contents( $elements . 'modules/digitalisimo-import/kits/class-pro-convert.php' );
	fx_ok( 1 === preg_match( "/'pro\\.inert'\\s*=>\\s*Part_Context::INFO/", $kits ) && 1 === preg_match( "/'pro\\.condition_kept'\\s*=>\\s*Part_Context::WARNING/", $kits ), 'El informe del kit da nivel a pro.inert (información) y pro.condition_kept (aviso).' );
	fx_ok( 1 === preg_match( '/null === \$converted && \$codes/', $kits ), 'El kit informa el motivo de un widget que se queda como widget de PRO.' );

	if ( $GLOBALS['fx']['fail'] ) {
		echo 'FALLO elements-convert-foreign: ' . $GLOBALS['fx']['fail'] . ' de ' . $GLOBALS['fx']['n'] . " comprobaciones.\n";
		exit( 1 );
	}
	echo 'elements-convert-foreign: ' . $GLOBALS['fx']['n'] . " comprobaciones en verde (ajustes que no son del widget de PRO).\n";
}
