<?php
/**
 * Reglas de visibilidad heredadas al convertir y repararlas: la extensión de Element Pack cuenta como activa salvo
 * «off» guardado, la conversión nunca descarta reglas (apagadas quedan guardadas sin aplicar) y la reparación devuelve
 * a cada elemento convertido las reglas que su copia original tenía, sin pisar las propias ya presentes.
 */
namespace Elementor { class Plugin { public static $instance; } }
namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'DIGITALISIMO_ELEMENTS_FILE', __DIR__ . '/../digitalisimo-elements/pro-elements.php' );
	$GLOBALS['digi'] = array( 'options' => array() );
	function get_option( $name, $default = false ) { return array_key_exists( $name, $GLOBALS['digi']['options'] ) ? $GLOBALS['digi']['options'][ $name ] : $default; }
	function apply_filters( $hook, $value ) { return $value; }
	function add_action() {} function add_filter() {}
	function check_repair( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }

	require __DIR__ . '/../digitalisimo-migration/legacy/conditions/class-legacy-rules.php';
	require __DIR__ . '/../digitalisimo-migration/legacy/class-native.php';
	require __DIR__ . '/../digitalisimo-migration/legacy/class-migration.php';
	use Digitalisimo\Elements\Conditions\Legacy_Rules;
	use Digitalisimo\Elements\Legacy\Native;
	use Digitalisimo\Elements\Legacy\Migration;

	$legacy = array( 'ep_display_conditions_enable' => 'yes', 'ep_display_conditions_to' => 'hide', 'ep_display_conditions_relation' => 'all', 'ep_display_conditions' => array( array( '_id' => 'r1', 'ep_condition_key' => 'static_page', 'ep_condition_operator' => 'is', 'ep_condition_static_page_value' => 'static' ) ) );
	$expected_rules = array( array( '_id' => 'r1', 'rule_key' => 'static_page', 'rule_operator' => 'is', 'rule_static_page_value' => 'static' ) );

	// Activación por sitio: como Element Pack (default_activation), sólo un «off» guardado la apaga.
	check_repair( Legacy_Rules::enabled_on_site(), 'Sin opción guardada la extensión está activa.' );
	$GLOBALS['digi']['options']['element_pack_elementor_extend'] = array( 'wrapper-link' => 'on' );
	check_repair( Legacy_Rules::enabled_on_site(), 'Con la opción guardada pero sin la clave sigue activa.' );
	$GLOBALS['digi']['options']['element_pack_elementor_extend'] = array( 'visibility-controls' => 'on' );
	check_repair( Legacy_Rules::enabled_on_site(), 'Con «on» está activa.' );
	$GLOBALS['digi']['options']['element_pack_elementor_extend'] = array( 'visibility-controls' => 'off' );
	check_repair( ! Legacy_Rules::enabled_on_site(), 'Sólo un «off» guardado la apaga.' );

	// Conversión: las reglas se conservan siempre; apagadas quedan guardadas sin aplicar.
	$converted = Native::visibility_settings( $legacy );
	check_repair( '' === $converted['digitalisimo_visibility_enable'] && 'hide' === $converted['digitalisimo_visibility_to'] && 'all' === $converted['digitalisimo_visibility_relation'] && $expected_rules === $converted['digitalisimo_visibility_rules'], 'Con la extensión apagada las reglas se convierten pero no se activan.' );
	$GLOBALS['digi']['options']['element_pack_elementor_extend'] = array( 'wrapper-link' => 'on' );
	$converted = Native::visibility_settings( $legacy );
	check_repair( 'yes' === $converted['digitalisimo_visibility_enable'] && $expected_rules === $converted['digitalisimo_visibility_rules'], 'Sin la clave guardada las reglas se convierten activas.' );
	check_repair( array() === Native::visibility_settings( array( 'title' => 'Hola' ) ), 'Sin reglas heredadas no se añade nada.' );
	check_repair( '' === Native::visibility_settings( array( 'ep_display_conditions_enable' => '', 'ep_display_conditions' => $legacy['ep_display_conditions'] ) )['digitalisimo_visibility_enable'], 'Un interruptor apagado en el elemento se respeta.' );

	// Reparación pura: por id de elemento, a cualquier profundidad, sin pisar reglas propias ya presentes.
	$original = array(
		array( 'id' => 'a1', 'elType' => 'container', 'settings' => array(), 'elements' => array(
			array( 'id' => 'b2', 'elType' => 'widget', 'widgetType' => 'bdt-advanced-heading', 'settings' => array( 'title' => 'Agencia' ) + $legacy ),
			array( 'id' => 'b3', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Sin reglas' ) ),
			array( 'id' => 'b4', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Ya convertido' ) + $legacy ),
		) ),
		array( 'id' => 'a5', 'elType' => 'container', 'settings' => $legacy, 'elements' => array() ),
	);
	$current = array(
		array( 'id' => 'a1', 'elType' => 'container', 'settings' => array(), 'elements' => array(
			array( 'id' => 'b2', 'elType' => 'widget', 'widgetType' => 'digitalisimo-advanced-heading', 'settings' => array( 'title' => 'Agencia' ) ),
			array( 'id' => 'b3', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Sin reglas' ) ),
			array( 'id' => 'b4', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Ya convertido', 'digitalisimo_visibility_enable' => '', 'digitalisimo_visibility_rules' => array() ) ),
		) ),
		array( 'id' => 'a5', 'elType' => 'container', 'settings' => array( 'otro' => 1 ), 'elements' => array() ),
	);
	list( $repaired, $count ) = Migration::repair_conditions_data( $current, $original );
	check_repair( 2 === $count, 'Se reparan los dos elementos que perdieron sus reglas (b2 y a5).' );
	$b2 = $repaired[0]['elements'][0]['settings'];
	check_repair( 'yes' === $b2['digitalisimo_visibility_enable'] && 'hide' === $b2['digitalisimo_visibility_to'] && $expected_rules === $b2['digitalisimo_visibility_rules'] && 'Agencia' === $b2['title'] && 'digitalisimo-advanced-heading' === $repaired[0]['elements'][0]['widgetType'], 'El widget convertido recupera sus reglas y conserva lo demás.' );
	check_repair( array( 'title' => 'Sin reglas' ) === $repaired[0]['elements'][1]['settings'], 'Un elemento sin reglas en el original no cambia.' );
	check_repair( '' === $repaired[0]['elements'][2]['settings']['digitalisimo_visibility_enable'] && array() === $repaired[0]['elements'][2]['settings']['digitalisimo_visibility_rules'], 'Un elemento con reglas propias ya presentes (aunque vacías o apagadas) no se pisa.' );
	check_repair( 'yes' === $repaired[1]['settings']['digitalisimo_visibility_enable'] && 1 === $repaired[1]['settings']['otro'], 'Un contenedor con reglas también se repara.' );
	list( $again, $count2 ) = Migration::repair_conditions_data( $repaired, $original );
	check_repair( 0 === $count2 && $again === $repaired, 'Reparar dos veces no cambia nada.' );
	list( $same, $count3 ) = Migration::repair_conditions_data( $current, array( array( 'id' => 'z', 'elType' => 'widget', 'settings' => array() ) ) );
	check_repair( 0 === $count3 && $same === $current, 'Sin reglas en el original no se toca el documento.' );
	echo "DIGITALÍSIMO Migración: activación por defecto, conversión sin pérdida y reparación de reglas validadas.\n";
}
