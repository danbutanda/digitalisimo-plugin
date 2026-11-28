<?php
/**
 * IA Tools · el contexto que el Asistente le manda a la IA (docs/ASSISTANT.md, «Contexto»), sin WordPress y con el
 * modelo simulado. Medido con el caso plomerito (2026-11-27): de unos 46 000 tokens del primer paso, 40 000 eran las
 * definiciones de 61 herramientas. Comprueba:
 *   C1  no se le ofrecen las que no necesita (abrir el propio Asistente, la información técnica de WordPress) ni las de
 *       WooCommerce cuando están las de la tienda de Elements; sin ésas, las de WooCommerce sí;
 *   C2  del constructor, sólo lo básico (crear, leer y editar una página, sus ajustes, publicar, listar, vista previa);
 *       las funciones de diseño se piden con una herramienta propia, llegan en el paso siguiente y siguen en la
 *       conversación (también en el turno siguiente); el catálogo completo sigue valiendo para ejecutar;
 *   C3  las herramientas van en orden fijo y no cambian entre pasos ni turnos mientras no se pidan las de diseño;
 *   C4  los resultados de turnos anteriores van recortados y sin sus bloques thinking (ver también ia-assistant-agent K2).
 * Uso: php tests/ia-assistant-contexto.php
 */
require __DIR__ . '/ia-assistant-agent/wp.php';
require dirname( __DIR__ ) . '/digitalisimo-ia-tools/includes/class-digitalisimo-ai.php';
foreach ( array( 'model', 'tools', 'history', 'guard', 'ability', 'uploads', '' ) as $part ) require dirname( __DIR__ ) . '/digitalisimo-ia-tools/includes/class-digitalisimo-ai-assistant' . ( $part ? '-' . $part : '' ) . '.php';

$T = 'Digitalisimo_AI_Assistant_Tools';
$GLOBALS['options']['digitalisimo_ai_options'] = array( 'providers' => array( 'anthropic' => array( 'enabled' => 1, 'key' => KEY_ANTHROPIC, 'url' => '' ) ) );
add_filter( 'digitalisimo_ai_assistant_limits', function () { return array( 'per_minute' => 0, 'per_day' => 0, 'concurrent' => 1 ); } );
$ab_meta = function ( $readonly ) { return array( 'annotations' => array( 'readonly' => $readonly, 'destructive' => false ), 'mcp' => array( 'public' => true, 'type' => 'tool' ) ); };
$reg  = function ( $name, $readonly = true ) use ( $ab_meta ) {
	wp_register_ability( $name, array( 'label' => $name, 'description' => 'Hace ' . $name . '.', 'category' => strtok( $name, '/' ), 'input_schema' => array( 'type' => 'object', 'properties' => array( 'n' => array( 'type' => 'integer' ) ) ), 'permission_callback' => function () { return current_user_can( 'edit_posts' ); }, 'execute_callback' => function ( $in ) use ( $name ) { $GLOBALS['ran'][] = $name; return array( 'ok' => true, 'de' => $name, 'relleno' => str_repeat( 'dato del sitio ', 400 ) ); }, 'meta' => $ab_meta( $readonly ) ) );
};
$skip    = array( 'digitalisimo-ia/open-assistant', 'core/get-user-info', 'core/get-environment-info', 'core/get-site-info' );
$base    = array( 'elementor/create-page', 'elementor/get-page-structure', 'elementor/manage-elements', 'elementor/update-page-settings', 'elementor/publish-document', 'elementor/list-posts', 'elementor/create-preview-link' );
$design  = array( 'elementor/build-composition', 'elementor/manage-classes', 'elementor/manage-global-variable', 'elementor/list-components', 'elementor/manage-site-parts' );
$woo     = array( 'woocommerce/products-query', 'woocommerce/product-update' );
$store   = array( 'digitalisimo-woo/products', 'digitalisimo-sales/create-page', 'digitalisimo-seo/get-site-status', 'digitalisimo-ia/read-page-text' );
foreach ( array_merge( $skip, $woo, $base, $design ) as $name ) $reg( $name );
// Sin la tienda de Elements, las de WooCommerce sí se ofrecen.
$catalog = $T::catalog();
$names   = array_keys( $T::offered( $catalog ) );
check( array() === array_intersect( $skip, $names ), 'C1: no se ofrecen abrir el Asistente ni la información técnica de WordPress: ' . json_encode( array_values( array_intersect( $skip, $names ) ) ) );
check( $woo === array_values( array_intersect( $woo, $names ) ), 'C1: sin la tienda de Elements, las de WooCommerce sí se ofrecen' );
foreach ( $store as $name ) $reg( $name );
$catalog = $T::catalog();
$names   = array_keys( $T::offered( $catalog ) );
check( array() === array_intersect( $woo, $names ), 'C1: con la tienda de Elements, las de WooCommerce (que hacen lo mismo) no' );
check( $store === array_values( array_intersect( $store, $names ) ), 'C1: las de la tienda, el kit, SEO y la lectura de páginas sí' );
check( $base === array_values( array_intersect( $base, $names ) ) && array() === array_intersect( $design, $names ), 'C2: del constructor, sólo lo básico; las funciones de diseño, no (todavía)' );
$more = array_keys( $T::offered( $catalog, true ) );
check( $design === array_values( array_intersect( $design, $more ) ) && array() === array_intersect( $skip, $more ), 'C2: pedidas, las funciones de diseño sí (lo que no sirve, nunca)' );
check( $design === $T::builder_extras( $catalog ), 'C2: las funciones de diseño son las del constructor fuera de lo básico' );

/* ---------------- C2/C3 en una conversación ---------------- */
$GLOBALS['script'] = array(); $GLOBALS['model_calls'] = array(); $GLOBALS['ran'] = array();
add_filter( 'digitalisimo_ai_assistant_pre_model', function ( $pre, $request ) { $GLOBALS['model_calls'][] = $request; return $GLOBALS['script'] ? array_shift( $GLOBALS['script'] ) : 'Listo.'; } );
$req   = function ( array $params = array() ) { return new WP_REST_Request( $params, array( 'X-Digitalisimo-Assistant' => wp_create_nonce( Digitalisimo_AI_Assistant::NONCE ) ) ); };
$tools = function ( $request ) { return array_column( $request['tools'], 'name' ); };
$call  = function ( $name, array $input = array() ) { static $n = 0; return array( 'id' => 'toolu_c' . ( ++$n ), 'name' => $name, 'input' => $input ); };
$GLOBALS['script'] = array( array( 'calls' => array( $call( 'digitalisimo-seo-get-site-status' ) ) ), 'Tu sitio está bien.' );
$r   = Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'revisa mi sitio' ) ) );
$cid = (int) $r->data['conversation']['id'];
list( $s1, $s2 ) = array_slice( $GLOBALS['model_calls'], -2 );
check( $tools( $s1 ) === $tools( $s2 ), 'C3: las mismas herramientas en todos los pasos de un turno' );
check( in_array( $T::MORE_TOOL, $tools( $s1 ), true ) && ! in_array( 'elementor-manage-classes', $tools( $s1 ), true ) && ! in_array( 'core-get-site-info', $tools( $s1 ), true ), 'C2: el modelo ve la herramienta para pedir las de diseño, no las de diseño' );
$sorted = array_values( array_filter( $tools( $s1 ), function ( $n ) use ( $T ) { return $T::MORE_TOOL !== $n && 'digitalisimo-ia-remember' !== $n; } ) );
$copy   = $sorted; sort( $copy, SORT_STRING );
check( $copy === $sorted && $T::MORE_TOOL === end( $s1['tools'] )['name'], 'C3: en orden fijo por nombre, y la de pedir las de diseño al final' );
// Pide las funciones de diseño y usa una en el paso siguiente.
$GLOBALS['script'] = array( array( 'text' => 'Busco las funciones de diseño.', 'calls' => array( $call( $T::MORE_TOOL ) ) ), array( 'calls' => array( $call( 'elementor-list-components' ) ) ), 'Tus componentes: ninguno.' );
$before = count( $GLOBALS['model_calls'] );
$ev     = Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'cambia el color principal de mi sitio', 'conversation' => $cid ) ) )->data['events'];
list( $a, $b, $c ) = array_slice( $GLOBALS['model_calls'], $before, 3 );
check( ! in_array( 'elementor-list-components', $tools( $a ), true ) && in_array( 'elementor-list-components', $tools( $b ), true ) && in_array( 'elementor-manage-classes', $tools( $c ), true ), 'C2: pedidas, las funciones de diseño llegan en el paso siguiente' );
check( in_array( 'elementor/list-components', $GLOBALS['ran'], true ), 'C2: y se usan como cualquier otra (lectura sin tarjeta)' );
$step = array_values( array_filter( $ev, function ( $e ) use ( $T ) { return 'tool' === $e['type'] && $T::MORE_ABILITY === $e['message']['ability']; } ) );
check( $step && 'ok' === $step[0]['message']['status'] && 'read' === $step[0]['message']['kind'] && ! preg_match( '/elementor|MCP|herramienta|tool/i', $step[0]['message']['label'] ), 'C2: el paso se ve en llano («' . ( $step[0]['message']['label'] ?? '' ) . '»)' );
$conv = Digitalisimo_AI_Assistant_History::get( $cid, get_current_user_id() );
check( true === $conv['more_tools'], 'C2: queda anotado en la conversación' );
$GLOBALS['script'] = array( 'Claro.' );
Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'gracias', 'conversation' => $cid ) ) );
$d = end( $GLOBALS['model_calls'] );
check( in_array( 'elementor-manage-classes', $tools( $d ), true ) && in_array( $T::MORE_TOOL, $tools( $d ), true ), 'C2: en el turno siguiente siguen (y la de pedirlas, porque el historial la usa)' );
// Una conversación nueva empieza sin ellas.
$GLOBALS['script'] = array( 'Hola.' );
Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'hola' ) ) );
check( ! in_array( 'elementor-manage-classes', $tools( end( $GLOBALS['model_calls'] ) ), true ), 'C2: una conversación nueva empieza sin las funciones de diseño' );

/* ---------------- C5: una herramienta que no existe ---------------- */
$GLOBALS['script'] = array( array( 'calls' => array( $call( 'digitalisimo-site-contact-placeholder' ) ) ), 'No pude.' );
$ev   = Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'dame el contacto' ) ) )->data['events'];
$miss = array_values( array_filter( $ev, function ( $e ) { return 'tool' === $e['type']; } ) );
check( $miss && 'error' === $miss[0]['message']['status'] && Digitalisimo_AI_Assistant::UNKNOWN_STEP === $miss[0]['message']['label'] && false === strpos( json_encode( $miss[0]['message']['label'] ), 'placeholder' ), 'C5: si el modelo pide una herramienta que no existe, el paso se ve en llano, sin el nombre inventado («' . ( $miss[0]['message']['label'] ?? '' ) . '»)' );

/* ---------------- C4: resultados de turnos anteriores ---------------- */
$old = array_values( array_filter( $d['messages'], function ( $m ) { return 'tool' === $m['role']; } ) );
check( count( $old ) >= 3 && max( array_map( function ( $m ) { return strlen( $m['content'] ); }, $old ) ) <= Digitalisimo_AI_Assistant_History::OLD_RESULT_BYTES, 'C4: los resultados de los turnos anteriores van recortados (' . implode( ', ', array_map( function ( $m ) { return strlen( $m['content'] ); }, $old ) ) . ' bytes)' );
check( false !== strpos( $old[0]['content'], '[resultado anterior recortado:' ) && false !== strpos( $old[0]['content'], Digitalisimo_AI_Assistant_Tools::DATA_NOTICE ), 'C4: con su marca y su aviso de dato' );

/* ---------------- C6: Haiku 5.5 no pasa a la tarifa cara ---------------- */
$chars = Digitalisimo_AI_Assistant_History::context_chars( 'anthropic', 'claude-haiku-5-5' );
check( $chars <= 200000 && $chars > 100000, "C6: con Haiku 5.5 la conversación se compacta antes de que la petición pase de 100 000 tokens (su tarifa sube 5 veces): $chars caracteres" );
check( Digitalisimo_AI_Assistant_History::context_chars( 'anthropic', 'claude-opus-5-5' ) > 1000000, 'C6: Opus y Sonnet conservan su ventana de 1M' );

echo "Asistente IA, contexto: $checks comprobaciones correctas.\n";
