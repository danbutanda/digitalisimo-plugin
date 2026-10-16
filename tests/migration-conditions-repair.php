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
	function has_filter( $hook ) { return isset( $GLOBALS['digi']['has_filter'][ $hook ] ); }
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
	// La conversión no pisa reglas propias ya presentes salvo que las heredadas estuvieran activas.
	$own_active = array( 'digitalisimo_visibility_enable' => 'yes', 'digitalisimo_visibility_to' => 'hide', 'digitalisimo_visibility_rules' => array( array( 'rule_key' => 'authentication' ) ) );
	$GLOBALS['digi']['options']['element_pack_elementor_extend'] = array( 'visibility-controls' => 'off' );
	$mixed = Native::apply_visibility( $own_active + array( 'ep_display_conditions' => $legacy['ep_display_conditions'] ) );
	check_repair( 'yes' === $mixed['digitalisimo_visibility_enable'] && 'hide' === $mixed['digitalisimo_visibility_to'] && 'authentication' === $mixed['digitalisimo_visibility_rules'][0]['rule_key'], 'Reglas heredadas inactivas no pisan las propias.' );
	$GLOBALS['digi']['options']['element_pack_elementor_extend'] = array();
	$mixed = Native::apply_visibility( $own_active + $legacy );
	check_repair( 'yes' === $mixed['digitalisimo_visibility_enable'] && 'hide' === $mixed['digitalisimo_visibility_to'] && 'static_page' === $mixed['digitalisimo_visibility_rules'][0]['rule_key'], 'Reglas heredadas activas (las que Element Pack aplicaba) sí sustituyen a las propias.' );
	check_repair( array( 'title' => 'x' ) === Native::apply_visibility( array( 'title' => 'x' ) ) && Native::has_own_rules( $own_active ) && ! Native::has_own_rules( array( 'title' => 'x' ) ), 'Sin reglas heredadas no cambia nada.' );

	// Interruptor encendido sin filas: Element Pack no pintaba el elemento; la reparación lo recupera.
	$no_rows = array( array( 'id' => 'q1', 'elType' => 'widget', 'settings' => array( 'ep_display_conditions_enable' => 'yes', 'ep_display_conditions_to' => 'show', 'ep_display_conditions' => array() ) ) );
	list( $fixed, $n ) = Migration::repair_conditions_data( array( array( 'id' => 'q1', 'elType' => 'widget', 'settings' => array( 'title' => 'x' ) ) ), $no_rows );
	check_repair( 1 === $n && 'yes' === $fixed[0]['settings']['digitalisimo_visibility_enable'] && array() === $fixed[0]['settings']['digitalisimo_visibility_rules'], 'Un interruptor encendido sin filas también se recupera.' );

	// Reparación por documento: prefiltro de texto, corte temporal y escritura sólo cuando cambia algo.
	$GLOBALS['digi']['meta'] = array(); $GLOBALS['digi']['writes'] = array();
	function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['digi']['meta'][ $id ][ $key ] ?? ''; }
	function update_post_meta( $id, $key, $value ) { $GLOBALS['digi']['meta'][ $id ][ $key ] = $value; $GLOBALS['digi']['writes'][] = $id . ':' . $key; return true; }
	function delete_post_meta( $id, $key ) { unset( $GLOBALS['digi']['meta'][ $id ][ $key ] ); }
	function get_metadata( $type, $id, $key, $single = false ) { return get_post_meta( $id, $key, $single ); }
	function update_metadata( $type, $id, $key, $value ) { return update_post_meta( $id, $key, $value ); }
	function add_metadata( $type, $id, $key, $value, $unique = false ) { return $unique && isset( $GLOBALS['digi']['meta'][ $id ][ $key ] ) ? false : update_post_meta( $id, $key, $value ); }
	function delete_metadata( $type, $id, $key ) { delete_post_meta( $id, $key ); return true; }
	function wp_slash( $v ) { return $v; }
	function wp_json_encode( $v ) { return json_encode( $v ); }
	function update_option( $n, $v, $a = true ) { $GLOBALS['digi']['options'][ $n ] = $v; return true; }
	function delete_option( $n ) { unset( $GLOBALS['digi']['options'][ $n ] ); return true; }
	function wp_doing_ajax() { return $GLOBALS['digi']['ajax'] ?? false; }
	function wp_doing_cron() { return false; }
	function current_user_can( $cap ) { return in_array( $cap, (array) ( $GLOBALS['digi']['caps'] ?? array() ), true ); }
	$GLOBALS['wpdb'] = new class() { public $postmeta = 'wp_postmeta'; public $posts = 'wp_posts'; public function prepare( $q ) { return $q; } public function esc_like( $s ) { return $s; } public function get_col( $q ) { return false !== strpos( $q, "'_elementor_data'" ) ? ( $GLOBALS['digi']['documents'] ?? array() ) : $GLOBALS['digi']['backups']; } };
	$make = static function ( $id, $time, $with_rules = true ) use ( $legacy ) {
		$settings = $with_rules ? array( 'title' => 'Agencia' ) + $legacy : array( 'title' => 'Agencia' );
		$GLOBALS['digi']['meta'][ $id ][ Migration::BACKUP ] = json_encode( array( 'time' => $time, 'data' => json_encode( array( array( 'id' => 'w' . $id, 'elType' => 'widget', 'settings' => $settings ) ) ) ) );
		$GLOBALS['digi']['meta'][ $id ]['_elementor_data'] = json_encode( array( array( 'id' => 'w' . $id, 'elType' => 'widget', 'settings' => array( 'title' => 'Agencia' ) ) ) );
	};
	$make( 1, 1000 ); $make( 2, 1000, false ); $make( 3, 5000 );
	check_repair( 1 === Migration::repair_conditions( 1, 2000 ) && 0 === Migration::repair_conditions( 2, 2000 ) && 0 === Migration::repair_conditions( 3, 2000 ), 'Se repara el documento convertido antes del corte con reglas en la copia; sin reglas o posterior al corte, no.' );
	check_repair( array( '1:_elementor_data' ) === array_values( array_filter( $GLOBALS['digi']['writes'], static function ( $w ) { return false !== strpos( $w, '_elementor_data' ); } ) ), 'Sólo se escribe el documento que cambió.' );
	check_repair( 1 === Migration::repair_conditions( 3, 0 ), 'Sin corte (botón y WP-CLI) también se repara el posterior.' );

	// Recuperación automática: por lotes reanudables, nunca en AJAX, sólo administradores, aviso al terminar.
	$GLOBALS['digi']['options'] = array(); $GLOBALS['digi']['backups'] = range( 1, 60 );
	for ( $i = 1; $i <= 60; $i++ ) { $make( $i, 1000 ); }
	$GLOBALS['digi']['caps'] = array( 'edit_posts' );
	Migration::repair_once();
	check_repair( ! isset( $GLOBALS['digi']['options'][ Migration::REPAIR_CURSOR ] ), 'Un usuario sin manage_options no dispara la recuperación.' );
	$GLOBALS['digi']['caps'] = array( 'manage_options' ); $GLOBALS['digi']['ajax'] = true;
	Migration::repair_once();
	check_repair( ! isset( $GLOBALS['digi']['options'][ Migration::REPAIR_CURSOR ] ), 'En AJAX no se repara nada.' );
	$GLOBALS['digi']['ajax'] = false;
	Migration::repair_once();
	check_repair( 25 === (int) $GLOBALS['digi']['options'][ Migration::REPAIR_CURSOR ] && '1' !== (string) ( $GLOBALS['digi']['options'][ Migration::REPAIRED ] ?? '' ) && 25 === (int) $GLOBALS['digi']['options'][ Migration::REPAIR_TOTALS ]['documents'], 'Primer lote: 25 documentos y cursor guardado, sin dar por terminado.' );
	Migration::repair_once();
	Migration::repair_once();
	check_repair( '1' === (string) $GLOBALS['digi']['options'][ Migration::REPAIRED ] && ! isset( $GLOBALS['digi']['options'][ Migration::REPAIR_CURSOR ] ) && 60 === (int) $GLOBALS['digi']['options'][ Migration::REPAIR_TOTALS ]['documents'], 'Tras tres lotes termina, borra el cursor y acumula el total.' );
	$writes = count( $GLOBALS['digi']['writes'] );
	Migration::repair_once();
	check_repair( $writes === count( $GLOBALS['digi']['writes'] ), 'Terminada, no vuelve a tocar nada.' );
	$GLOBALS['digi']['options'][ Migration::REPAIR_CUTOFF ] = 0;
	check_repair( array( 'documents' => 0, 'elements' => 0 ) === Migration::repair_all( 10 ), 'Repetir sobre documentos ya reparados no cambia nada.' );

	// Lotes de conversión: un widget sin adaptador se queda tal cual, así que su documento es heredado pero no convertible y los lotes lo saltan.
	$blocked_widget = array( 'id' => 'u1', 'elType' => 'widget', 'widgetType' => 'bdt-barcode', 'settings' => array( 'barcode_text' => 'x' ) );
	$mapped_widget  = array( 'id' => 'u3', 'elType' => 'widget', 'widgetType' => 'bdt-advanced-heading', 'settings' => array( 'title' => 'x' ) );
	check_repair( Native::is_legacy( $blocked_widget ) && ! Native::is_convertible( $blocked_widget ), 'Un widget sin adaptador es heredado pero no convertible.' );
	check_repair( Native::is_convertible( array( 'settings' => array( 'barcode_text' => 'x' ) + $legacy ) + $blocked_widget ), 'Sin adaptador pero con reglas heredadas, las reglas sí se convierten.' );
	check_repair( Native::is_convertible( $mapped_widget ), 'Un widget con adaptador es convertible.' );
	check_repair( Native::is_convertible( array( 'id' => 'c1', 'elType' => 'container', 'settings' => array(), 'elements' => array( $blocked_widget, array( 'id' => 'u4', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( '_mask_shape' => 'shape-3' ) ) ) ) ), 'Una máscara de Element Pack en un hijo hace convertible el contenedor.' );
	check_repair( ! Native::is_convertible( array( 'id' => 'c2', 'elType' => 'container', 'settings' => array(), 'elements' => array( $blocked_widget ) ) ), 'Un contenedor cuyo único heredado es un widget sin adaptador no es convertible.' );
	$GLOBALS['digi']['documents'] = array( 101, 102, 103 );
	$GLOBALS['digi']['meta'][101]['_elementor_data'] = json_encode( array( $blocked_widget ) );
	$GLOBALS['digi']['meta'][102]['_elementor_data'] = json_encode( array( $mapped_widget ) );
	$GLOBALS['digi']['meta'][103]['_elementor_data'] = json_encode( array( array( 'id' => 'h1', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'ep_ en un texto' ) ) ) );
	$groups = Migration::classify();
	check_repair( array( 102 ) === $groups['convertible'] && array( 101 ) === $groups['blocked'] && array( 102 ) === Migration::convertible() && array( 101, 102 ) === Migration::pending(), 'Los lotes toman sólo los convertibles; el bloqueado cuenta aparte y el que no tiene nada heredado no cuenta.' );

	// Botón de recuperación: lotes reanudables con el cursor de la recuperación automática; terminada la pasada, el botón la reinicia.
	$GLOBALS['digi']['options'] = array( Migration::REPAIR_CUTOFF => 2000 ); $GLOBALS['digi']['backups'] = range( 1, 5 );
	for ( $i = 1; $i <= 5; $i++ ) { $make( $i, 1000 ); }
	$step = Migration::repair_step( 2, true );
	check_repair( 2 === $step['documents'] && 3 === $step['remaining'] && ! $step['done'] && 2 === (int) $GLOBALS['digi']['options'][ Migration::REPAIR_CURSOR ] && 3 === Migration::repair_remaining(), 'El primer lote repara dos y deja tres por revisar.' );
	$step = Migration::repair_step( 2, true );
	check_repair( 2 === $step['documents'] && 1 === $step['remaining'] && 4 === (int) $GLOBALS['digi']['options'][ Migration::REPAIR_CURSOR ], 'El segundo lote continúa donde quedó el primero.' );
	$step = Migration::repair_step( 2, true );
	check_repair( 1 === $step['documents'] && 0 === $step['remaining'] && $step['done'] && '1' === (string) $GLOBALS['digi']['options'][ Migration::REPAIRED ] && ! isset( $GLOBALS['digi']['options'][ Migration::REPAIR_CURSOR ] ) && 5 === (int) $GLOBALS['digi']['options'][ Migration::REPAIR_TOTALS ]['documents'] && 5 === Migration::repair_remaining(), 'El tercer lote termina la pasada y acumula el total.' );
	$step = Migration::repair_step( 2, true );
	check_repair( 0 === $step['documents'] && 3 === $step['remaining'] && ! $step['done'] && ! isset( $GLOBALS['digi']['options'][ Migration::REPAIRED ] ), 'Terminada, el botón reinicia la pasada desde el principio sin cambiar lo ya reparado.' );
	Migration::repair_once();
	check_repair( '1' === (string) $GLOBALS['digi']['options'][ Migration::REPAIRED ], 'La recuperación automática continúa la pasada reiniciada hasta terminarla.' );

	// Conversión en memoria: idempotente y sin claves heredadas; la conversión guardada escribe en el id indicado (también una revisión).
	$doc = array( array( 'id' => 'k1', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'x' ) + $legacy ) );
	$GLOBALS['digi']['options']['element_pack_elementor_extend'] = array();
	list( $out, $n ) = Migration::convert_data( $doc, 7 );
	check_repair( 1 === $n && 'yes' === $out[0]['settings']['digitalisimo_visibility_enable'] && ! isset( $out[0]['settings']['ep_display_conditions'] ) && ! Native::is_convertible( $out[0] ), 'La conversión en memoria pasa las reglas y no deja claves heredadas.' );
	list( $again, $n2 ) = Migration::convert_data( $out, 7 );
	check_repair( 0 === $n2 && $again === $out, 'Convertir dos veces no cambia nada.' );
	$GLOBALS['digi']['meta'][200] = array( '_elementor_data' => json_encode( $doc ) ); $GLOBALS['digi']['writes'] = array();
	check_repair( 1 === Migration::convert_document( 200 )['native'] && array( '200:' . Migration::BACKUP, '200:_elementor_data' ) === $GLOBALS['digi']['writes'] && 'yes' === json_decode( $GLOBALS['digi']['meta'][200]['_elementor_data'], true )[0]['settings']['digitalisimo_visibility_enable'], 'La conversión guarda copia y resultado en el documento indicado, sin redirigir al padre.' );
	echo "DIGITALÍSIMO Migración: activación por defecto, conversión sin pérdida, reparación de reglas (por lotes reanudables), lotes que saltan los documentos sin adaptador y escritura en el documento indicado validados.\n";
}
