<?php
/**
 * IA Tools · el Asistente IA sin tecnicismos (caso plomerito 4 del 2026-11-27: «el asistente lo veo muy técnico, debe ser
 * fácil de usar para el usuario sin nada de conocimientos que sólo quiere un sitio o tienda web»; a «revisa si skydropx
 * funciona bien y mi carrito funciona bien», `digitalisimo-woo/skydropx` con `audit_products`, que sólo lee, mostró
 * «Este cambio no tiene vuelta atrás: confírmalo» con el JSON de la entrada). Sin WordPress y con el modelo simulado:
 *   S1  la clase de CADA acción de cada herramienta que conoce el Asistente, leída de su definición real (las de tienda,
 *       Skydropx y Hosting de Elements, el kit de ventas, SEO, IA, Tools y el constructor): ninguna lectura pide
 *       confirmación, ninguna escritura se cuela sin ella y la tabla del Asistente no deja acciones sin clasificar; una
 *       acción desconocida es un cambio con tarjeta llana; de punta a punta, la revisión de Skydropx del caso corre sin
 *       tarjeta;
 *   S2  la tarjeta la escribe el servidor en llano: la frase de lo que hará (precio, ocultar de Google, peso para
 *       Skydropx…), «Puedes deshacerlo después» sólo si es verdad, lo que de verdad no tiene vuelta atrás en una frase y
 *       la casilla sólo en lo que borra o mueve dinero (el servidor la exige); «Ver detalles» sin JSON;
 *   S3  las instrucciones: hacer en vez de mandar a hacer, una sola pregunta, revisar Skydropx y el carrito en llano;
 *   S5  ninguna palabra técnica (slug, meta, schema, noindex, canonical, plugin, API, JSON, ID, endpoint, MCP ni nombres
 *       de herramientas) en los textos de la pantalla ni en las frases de las tarjetas.
 * Falla con el código de 1.0.77 (46785b5b). Uso: php tests/ia-assistant-simple.php
 */
require __DIR__ . '/ia-assistant-agent/wp.php';
require dirname( __DIR__ ) . '/digitalisimo-ia-tools/includes/class-digitalisimo-ai.php';
foreach ( array( 'model', 'tools', 'history', 'guard', 'ability', 'uploads', '' ) as $part ) require dirname( __DIR__ ) . '/digitalisimo-ia-tools/includes/class-digitalisimo-ai-assistant' . ( $part ? '-' . $part : '' ) . '.php';

// Las definiciones reales de Elements (clases puras: no tocan WordPress al cargarse).
$els = dirname( __DIR__ ) . '/digitalisimo-elements/modules';
foreach ( array( 'digitalisimo-woo/mcp/class-module.php', 'digitalisimo-woo/mcp/class-tools.php', 'digitalisimo-woo/mcp/class-skydropx-tool.php', 'digitalisimo-hosting/mcp/class-tools.php', 'digitalisimo-sales/class-catalog.php', 'digitalisimo-sales/mcp/class-tools.php' ) as $file ) require_once $els . '/' . $file;
use Digitalisimo\Elements\Woo\Mcp\Tools as Woo;
use Digitalisimo\Elements\Woo\Mcp\Skydropx_Tool;
use Digitalisimo\Elements\Hosting\Mcp\Tools as Hosting;
use Digitalisimo\Elements\Sales\Mcp\Tools as Sales;

$GLOBALS['options']['digitalisimo_ai_options'] = array(
	'providers' => array( 'anthropic' => array( 'enabled' => 1, 'key' => KEY_ANTHROPIC, 'url' => '' ) ),
	'profiles'  => array( 'content' => array( 'provider' => 'auto', 'model' => '', 'fallback' => '' ) ),
);
add_filter( 'digitalisimo_ai_assistant_limits', function () { return array( 'per_minute' => 0, 'per_day' => 0, 'concurrent' => 1 ); } );
Digitalisimo_AI_Assistant::init();
$T = 'Digitalisimo_AI_Assistant_Tools';
// Con el código anterior (sin tabla ni tarjeta llana) la prueba falla en sus comprobaciones, no con un error de PHP.
$kinds = function ( $name ) use ( $T ) { return method_exists( $T, 'action_kinds' ) ? $T::action_kinds( $name ) : null; };
$build = function ( array $e, $in, $kind = 'destructive', $sim = null ) { return class_exists( 'Digitalisimo_AI_Assistant_Card' ) ? Digitalisimo_AI_Assistant_Card::build( $e, $in, $kind, $sim ) : array( 'text' => '', 'undo' => false, 'final' => '', 'ack' => false, 'ack_label' => '', 'warnings' => array(), 'details' => array() ); };

/** Una entrada del catálogo con la forma de Tools::catalog(), desde una definición. */
$entry = function ( $name, array $def ) {
	$schema = $def['input_schema'] ?? array();
	$meta   = $def['meta'] ?? array();
	return array(
		'name'        => $name,
		'tool'        => Digitalisimo_AI_Assistant_Tools::tool_name( $name ),
		'label'       => (string) ( $def['label'] ?? $name ),
		'description' => '',
		'category'    => (string) ( $def['category'] ?? '' ),
		'schema'      => $schema,
		'wrapped'     => false,
		'annotations' => (array) ( $meta['annotations'] ?? array() ),
		'dry_run'     => true === ( $meta['digitalisimo']['dry_run'] ?? null ) && 'boolean' === ( $schema['properties']['dry_run']['type'] ?? '' ),
	);
};

/* ---------------- S1: la clase de cada acción ---------------- */
// Las herramientas con `action`: cada valor de su enum, contra lo que la propia herramienta dice que escribe.
$with_actions = array();
foreach ( array_merge( array_keys( Woo::TOOLS ), array( Woo::SKYDROPX ) ) as $tool ) {
	$def                                  = Woo::definition( $tool, 'read' );
	$with_actions[ Woo::id( $tool ) ] = array( $entry( Woo::id( $tool ), $def ), function ( $action ) use ( $tool ) { return Woo::is_write( $tool, $action ); } );
}
foreach ( Hosting::TOOLS as $tool ) {
	$with_actions[ Hosting::id( $tool ) ] = array( $entry( Hosting::id( $tool ), Hosting::definition( $tool ) ), function ( $action ) { return Hosting::is_write( $action ); } );
}
$total = 0;
foreach ( $with_actions as $name => list( $e, $writes ) ) {
	$enum  = $e['schema']['properties']['action']['enum'];
	$table = $kinds( $name );
	check( is_array( $table ), "S1: {$name} tiene su tabla de acciones en el Asistente" );
	check( is_array( $table ) && array() === array_diff( $enum, array_keys( $table ) ), "S1: ninguna acción de {$name} queda sin clasificar en el Asistente: " . implode( ', ', array_diff( $enum, array_keys( (array) $table ) ) ) );
	check( array() === array_diff( array_keys( (array) $table ), $enum ), "S1: la tabla de {$name} no inventa acciones: " . implode( ', ', array_diff( array_keys( (array) $table ), $enum ) ) );
	foreach ( $enum as $action ) {
		$kind = $T::kind( $e, array( 'action' => $action, 'per_page' => 50 ) );
		if ( $writes( $action ) ) {
			check( 'read' !== $kind, "S1: {$name} · {$action} escribe: pasa por la tarjeta (clase {$kind})" );
		} else {
			check( 'read' === $kind, "S1: {$name} · {$action} sólo lee: corre sin tarjeta (clase {$kind})" );
		}
		++$total;
	}
}
check( $total >= 55, "S1: se recorrieron todas las acciones ({$total})" );
// El caso, literal.
$sky = $with_actions['digitalisimo-woo/skydropx'][0];
check( 'read' === $T::kind( $sky, array( 'action' => 'audit_products', 'per_page' => 50 ) ), 'S1: la revisión de productos de Skydropx del caso (audit_products, per_page 50) es lectura' );
foreach ( Skydropx_Tool::READ_ACTIONS as $action ) check( 'read' === $T::kind( $sky, array( 'action' => $action ) ), "S1: Skydropx · {$action} es lectura" );
foreach ( Skydropx_Tool::WRITE_ACTIONS as $action ) check( 'destructive' === $T::kind( $sky, array( 'action' => $action ) ), "S1: Skydropx · {$action} pide tarjeta" );
// Los cambios sencillos del catálogo siguen siendo `write` (lo que Elements deja al Asistente en «Sólo lectura»); lo demás, destructivo.
$products = $with_actions['digitalisimo-woo/products'][0];
check( 'write' === $T::kind( $products, array( 'action' => 'update', 'product_id' => 5, 'data' => array( 'sale_price' => '7500' ) ) ), 'S1: cambiar el precio de un producto es un cambio sencillo (write)' );
check( 'destructive' === $T::kind( $products, array( 'action' => 'update', 'product_id' => 5, 'data' => array( 'meta_data' => array() ) ) ) && 'destructive' === $T::kind( $products, array( 'action' => 'create', 'data' => array( 'status' => 'publish' ) ) ), 'S1: metadatos o publicar siguen siendo destructivos' );
check( 'read' === $T::kind( $with_actions['digitalisimo-woo/reports'][0], array( 'action' => 'changes' ) ) && 'read' === $T::kind( $with_actions['digitalisimo-hosting/settings'][0], array( 'action' => 'log' ) ), 'S1: la bitácora de la tienda y la de Hosting son lecturas' );
// Una acción desconocida (o sin acción) nunca es lectura, aunque se parezca a una.
foreach ( array( array(), array( 'action' => 'export' ), array( 'action' => 'list_all' ), array( 'action' => 'GET ' ) ) as $odd ) check( 'read' !== $T::kind( $products, $odd ), 'S1: una acción desconocida es un cambio con tarjeta: ' . json_encode( $odd ) );
$reports_extra = $with_actions['digitalisimo-woo/reports'][0];
$reports_extra['schema']['properties']['action']['enum'][] = 'purge_log';
check( 'destructive' === $T::kind( $reports_extra, array( 'action' => 'purge_log' ) ), 'S1: una acción nueva que la tabla no conoce, aun en una herramienta de sólo lectura, pasa por la tarjeta' );
// Fuera de los espacios de confianza, la tabla no se aplica (un plugin ajeno con el mismo nombre no gana lecturas).
check( method_exists( $T, 'action_kinds' ) && null === $T::action_kinds( 'otro/skydropx' ), 'S1: sin tabla para herramientas ajenas' );

// Las herramientas sin `action`: su anotación manda (leída de la definición real).
foreach ( Sales::TOOLS as $tool ) {
	$e = $entry( Sales::id( $tool ), Sales::definition( $tool ) );
	check( ( Sales::is_readonly( $tool ) ? 'read' : 'write' ) === $T::kind( $e, array() ), "S1: kit de ventas · {$tool}" );
}
// El constructor (elementor/*): cada ability con su id y sus anotaciones, leídos del código (no se toca la carpeta).
$abilities = glob( dirname( __DIR__ ) . '/digitalisimo-elements/elementor/modules/mcp/abilities/*-ability.php' );
$seen      = 0;
foreach ( $abilities as $file ) {
	$src = (string) file_get_contents( $file );
	if ( ! preg_match( "/return '(elementor\\/[a-z-]+)';/", $src, $id ) || ! preg_match( "/'readonly'\\s*=>\\s*(true|false)/", $src, $ro ) ) continue;
	preg_match( "/'destructive'\\s*=>\\s*(true|false)/", $src, $de );
	$ann  = array( 'readonly' => 'true' === $ro[1] ) + ( $de ? array( 'destructive' => 'true' === $de[1] ) : array() );
	$kind = $T::kind( array( 'name' => $id[1], 'schema' => array(), 'annotations' => $ann, 'label' => $id[1] ), array() );
	check( $ann['readonly'] ? 'read' === $kind : 'read' !== $kind, "S1: constructor · {$id[1]} (" . ( $ann['readonly'] ? 'lee' : 'escribe' ) . ", clase {$kind})" );
	++$seen;
}
check( $seen >= 18, "S1: se revisaron las abilities del constructor ({$seen})" );
// SEO, IA y Tools: sus lecturas anotadas se leen sin tarjeta y sus escrituras no.
$seo_src = (string) file_get_contents( dirname( __DIR__ ) . '/digitalisimo-seo/includes/class-seo-abilities.php' );
preg_match_all( "/^\\t\\t\\t'([a-z0-9-]+)'\\s*=> array\\( '/m", $seo_src, $seo_reads );
check( count( $seo_reads[1] ) >= 8, 'S1: se leyeron las lecturas de SEO (' . count( $seo_reads[1] ) . ')' );
foreach ( $seo_reads[1] as $n ) check( 'read' === $T::kind( array( 'name' => 'digitalisimo-seo/' . $n, 'schema' => array(), 'annotations' => array( 'readonly' => true, 'destructive' => false ) ), array() ), "S1: SEO · {$n} es lectura" );
foreach ( array( 'update-post-seo', 'revert-post-seo', 'create-redirection' ) as $n ) check( 'write' === $T::kind( array( 'name' => 'digitalisimo-seo/' . $n, 'schema' => array(), 'annotations' => array( 'readonly' => false, 'destructive' => false ) ), array( 'post_id' => 2, 'source' => '/a/' ) ), "S1: SEO · {$n} pasa por la tarjeta" );
foreach ( array( 'digitalisimo-ia/read-page-text', 'digitalisimo-ia/site-contact', 'digitalisimo-ia/open-assistant', 'digitalisimo-tools/plugin-health' ) as $n ) check( 'read' === $T::kind( array( 'name' => $n, 'schema' => array(), 'annotations' => array( 'readonly' => true, 'destructive' => false ) ), array() ), "S1: {$n} es lectura" );

// De punta a punta: el modelo pide la revisión del caso y no aparece ninguna tarjeta.
$req    = function ( array $params = array() ) { return new WP_REST_Request( $params, array( 'X-Digitalisimo-Assistant' => wp_create_nonce( Digitalisimo_AI_Assistant::NONCE ) ) ); };
$events = function ( $response ) { check( $response instanceof WP_REST_Response, 'La ruta responde con eventos: ' . ( is_wp_error( $response ) ? $response->get_error_message() : gettype( $response ) ) ); return $response->data['events']; };
$of     = function ( $events, $type ) { return array_values( array_filter( $events, function ( $e ) use ( $type ) { return $e['type'] === $type; } ) ); };
$call   = function ( $name, array $input = array() ) { static $n = 0; return array( 'id' => 'toolu_s' . ( ++$n ), 'name' => $name, 'input' => $input ); };
$GLOBALS['script'] = array(); $GLOBALS['model_calls'] = array(); $GLOBALS['ran'] = array();
add_filter( 'digitalisimo_ai_assistant_pre_model', function ( $pre, $request ) { $GLOBALS['model_calls'][] = $request; return $GLOBALS['script'] ? array_shift( $GLOBALS['script'] ) : 'Listo.'; } );
$can = function () { return current_user_can( 'edit_posts' ); };
$real = function () { return array_values( array_filter( $GLOBALS['ran'], function ( $r ) { return empty( $r[1]['dry_run'] ); } ) ); };
$register = function ( $name, array $def ) use ( $can ) {
	$def['meta']['mcp'] = array( 'public' => true, 'type' => 'tool' );
	wp_register_ability( $name, array( 'label' => $def['label'], 'category' => $def['category'], 'input_schema' => $def['input_schema'], 'permission_callback' => $can, 'execute_callback' => function ( $in ) use ( $name ) { $GLOBALS['ran'][] = array( $name, $in ); return array( 'ok' => true, 'action' => $in['action'] ?? '', 'data' => array( 'items' => array( array( 'name' => 'Bomba sin peso', 'missing' => array( 'weight' ) ) ), 'total' => 3 ) ); }, 'meta' => $def['meta'] ) );
};
$register( 'digitalisimo-woo/skydropx', Woo::definition( Woo::SKYDROPX, 'read' ) );
$register( 'digitalisimo-woo/store-settings', Woo::definition( 'store-settings', 'read' ) );
$register( 'digitalisimo-woo/products', Woo::definition( 'products', 'read' ) );
$GLOBALS['script'] = array(
	array( 'calls' => array( $call( 'digitalisimo-woo-skydropx', array( 'action' => 'status' ) ), $call( 'digitalisimo-woo-skydropx', array( 'action' => 'audit_products', 'per_page' => 50 ) ), $call( 'digitalisimo-woo-skydropx', array( 'action' => 'quote', 'address' => array( 'postcode' => '27000' ), 'cart' => array( array( 'product_id' => 5 ) ) ) ), $call( 'digitalisimo-woo-store-settings', array( 'action' => 'list', 'resource' => 'payment_gateway' ) ) ) ),
	'Revisé Skydropx y tu carrito. 3 productos no tienen peso, así que Skydropx no puede cotizar su envío. ¿Les pongo el peso?',
);
$ev = $events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'revisa si el plugin skydropx esta funcionando bien y mi carrito funciona bien' ) ) ) );
check( ! $of( $ev, 'confirm' ) && 4 === count( $real() ) && 4 === count( $GLOBALS['ran'] ), 'S1: el caso de punta a punta: las cuatro revisiones corren solas, sin ninguna tarjeta: ' . json_encode( array_map( function ( $e ) { return $e['type'] . ':' . ( $e['message']['kind'] ?? '' ) . ':' . ( $e['message']['status'] ?? '' ); }, $ev ) ) );
check( 4 === count( array_filter( $of( $ev, 'tool' ), function ( $t ) { return 'read' === $t['message']['kind'] && 'ok' === $t['message']['status']; } ) ), 'S1: y quedan como «Consulté…», listas' );

/* ---------------- S2: la tarjeta en llano ---------------- */
$GLOBALS['posts'][501] = new WP_Post( array( 'ID' => 501, 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Sample Page' ) );
$GLOBALS['posts'][252] = new WP_Post( array( 'ID' => 252, 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => 'Purificador AguaClara' ) );
$seo_w  = array( 'name' => 'digitalisimo-seo/update-post-seo', 'label' => 'Cambiar los metadatos SEO de un contenido', 'schema' => array(), 'annotations' => array( 'readonly' => false, 'destructive' => false ) );
$card   = $build( $seo_w, array( 'post_id' => 501, 'noindex' => true ), 'write' );
check( 'Voy a ocultar de Google la página «Sample Page».' === $card['text'] && $card['undo'] && ! $card['ack'] && '' === $card['final'], 'S2: «Voy a ocultar de Google la página "Sample Page"», que se puede deshacer y sin casilla: ' . json_encode( $card, JSON_UNESCAPED_UNICODE ) );
$card = $build( $seo_w, array( 'post_id' => 501, 'title' => 'Ferretería en Torreón', 'description' => 'Plomería y ferretería en Torreón con envío a todo México.' ), 'write' );
check( false !== strpos( $card['text'], 'el título que muestra Google a «Ferretería en Torreón»' ) && false !== strpos( $card['text'], 'la descripción que muestra Google a «Plomería' ), 'S2: título y descripción para Google, con sus textos: ' . $card['text'] );
$card = $build( $products, array( 'action' => 'update', 'product_id' => 252, 'data' => array( 'sale_price' => '7500' ) ), 'write', array( 'ok' => true, 'dry_run' => true, 'would' => array( 'changes' => array( array( 'field' => 'sale_price', 'from' => '8000', 'to' => '7500' ) ) ) ) );
check( 'Voy a cambiar en el producto «Purificador AguaClara» el precio de oferta de $8,000 a $7,500.' === $card['text'] && ! $card['ack'], 'S2: el precio, con el de antes que dio la simulación: ' . $card['text'] );
$items = array();
for ( $i = 1; $i <= 12; $i++ ) $items[] = array( 'product_id' => $i, 'weight' => 2 );
$card = $build( $sky, array( 'action' => 'update_products', 'items' => $items ), 'destructive' );
check( 'Voy a poner el peso a 12 productos para que Skydropx pueda cotizar su envío.' === $card['text'] && ! $card['ack'] && ! $card['undo'], 'S2: el peso de 12 productos para Skydropx, sin casilla y sin prometer deshacer: ' . $card['text'] );
$card = $build( $products, array( 'action' => 'delete', 'product_id' => 252, 'force' => true ), 'destructive' );
check( 'Voy a borrar el producto «Purificador AguaClara».' === $card['text'] && $card['ack'] && false !== strpos( $card['final'], 'para siempre' ), 'S2: borrar lleva la casilla y lo dice en una frase simple: ' . json_encode( $card, JSON_UNESCAPED_UNICODE ) );
$orders = $entry( 'digitalisimo-woo/orders', Woo::definition( 'orders', 'read' ) );
$card   = $build( $orders, array( 'action' => 'create', 'resource' => 'refund', 'order_id' => 1201, 'data' => array( 'amount' => '350' ), 'api_refund' => true ), 'destructive' );
check( false !== strpos( $card['text'], 'reembolso de $350 en el pedido 1201' ) && $card['ack'] && false !== strpos( $card['final'], 'devuelve el dinero' ), 'S2: devolver dinero lleva la casilla: ' . json_encode( $card, JSON_UNESCAPED_UNICODE ) );
$card = $build( $orders, array( 'action' => 'update', 'order_id' => 1201, 'data' => array( 'status' => 'completed' ) ), 'destructive' );
check( 'Voy a marcar el pedido 1201 como «Completado».' === $card['text'] && ! $card['ack'] && false !== strpos( $card['final'], 'correo' ), 'S2: un pedido completado avisa del correo al cliente, sin casilla: ' . json_encode( $card, JSON_UNESCAPED_UNICODE ) );
$settings = $entry( 'digitalisimo-woo/store-settings', Woo::definition( 'store-settings', 'read' ) );
$card     = $build( $settings, array( 'action' => 'update', 'resource' => 'payment_gateway', 'gateway_id' => 'bacs', 'data' => array( 'enabled' => true ) ), 'destructive' );
check( 'Voy a activar la forma de pago «Transferencia bancaria» en tu tienda.' === $card['text'] && ! $card['ack'], 'S2: activar una forma de pago: ' . $card['text'] );
// Lo que la plantilla no nombra no se calla.
$card = $build( $products, array( 'action' => 'update', 'product_id' => 252, 'data' => array( 'sale_price' => '7500', 'rarísimo_x' => 1, 'otro_y' => 2 ) ), 'write' );
check( false !== strpos( $card['text'], 'y otros 2 datos (en «Ver detalles»)' ), 'S2: lo que no tiene nombre se cuenta en la frase: ' . $card['text'] );
// Respaldo genérico llano para lo desconocido; borrar o dinero llevan casilla aun ahí.
$odd  = array( 'name' => 'acme/cosas', 'label' => 'Acme: limpiar registros', 'schema' => array(), 'annotations' => array() );
$card = $build( $odd, array( 'mode' => 'x' ), 'write' );
check( 0 === strpos( $card['text'], 'Voy a hacer un cambio en tu sitio' ) && false !== strpos( $card['text'], 'Ver detalles' ) && ! $card['ack'], 'S2: lo desconocido sale con una frase llana: ' . $card['text'] );
$card = $build( $odd, array( 'mode' => 'x' ), 'destructive' );
check( $card['ack'], 'S2: lo desconocido y destructivo conserva la casilla (revisión: no se pierde lo que antes la tenía)' );
$card = $build( $odd, array( 'action' => 'delete', 'id' => 3 ), 'write' );
check( $card['ack'] && '' !== $card['final'], 'S2: «delete» como acción de primer nivel se reconoce como borrar (revisión: el separador era un espacio)' );
$card = $build( array( 'name' => 'acme/delete-logs' ) + $odd, array(), 'destructive' );
check( $card['ack'] && '' !== $card['final'], 'S2: una acción desconocida que borra lleva la casilla' );
$card = $build( $products, array( 'action' => 'export' ), 'destructive' );
check( 0 === strpos( $card['text'], 'Voy a hacer un cambio en tu tienda' ), 'S2: una acción desconocida de una herramienta conocida, también llana: ' . $card['text'] );
// «Ver detalles»: nombres legibles, valores completos, nada de JSON; lo arriesgado, en llano.
$card = $build( $products, array( 'action' => 'update', 'product_id' => 252, 'data' => array( 'sale_price' => '7500', 'description' => '<p>Visita https://atacante.test/x</p>' ) ), 'write' );
$flat = json_encode( $card['details'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
check( false === strpos( $flat, '{\"' ) && false !== strpos( $flat, 'Precio de oferta' ) && false !== strpos( $flat, 'atacante.test/x' ), 'S2: «Ver detalles» con nombres legibles y los valores completos, sin JSON: ' . $flat );
check( 1 === count( $card['warnings'] ) && false !== strpos( $card['warnings'][0], 'otros sitios: atacante.test' ), 'S2: un enlace a otro sitio se avisa en llano: ' . json_encode( $card['warnings'], JSON_UNESCAPED_UNICODE ) );

// Revisión adversarial: lo que borra, mueve dinero o deja de verse no pierde la casilla ni se calla.
$seo_r = array( 'name' => 'digitalisimo-seo/create-redirection', 'label' => 'Crear una redirección', 'schema' => array(), 'annotations' => array( 'readonly' => false, 'destructive' => false ) );
$card  = $build( $seo_r, array( 'source' => '.*', 'comparison' => 'regex', 'code' => 410 ), 'destructive' );
check( $card['ack'] && false !== strpos( $card['text'], 'las direcciones que cumplen el patrón' ), 'S2: un 410 con patrón (todo el sitio) lleva la casilla y lo dice: ' . $card['text'] );
$card = $build( $seo_r, array( 'source' => '/vieja/', 'target' => '/nueva/' ), 'write' );
check( ! $card['ack'] && false !== strpos( $card['text'], 'sitio.test/vieja/ llegue a sitio.test/nueva/' ), 'S2: una redirección de una página a otra del sitio, sin casilla: ' . $card['text'] );
$card = $build( $settings, array( 'action' => 'update', 'resource' => 'payment_gateway', 'gateway_id' => 'paypal', 'data' => array( 'settings' => array( 'email' => 'otro@dominio.test', 'api_password' => 'x1' ) ) ), 'destructive' );
check( $card['ack'] && false !== strpos( $card['text'], '«email» a «otro@dominio.test»' ) && false !== strpos( $card['text'], 'oculto' ) && false === strpos( $card['text'], 'x1' ) && preg_grep( '/correo/', $card['warnings'] ), 'S2: cambiar a dónde llega el dinero de una forma de pago nombra cada dato y lleva la casilla: ' . json_encode( $card, JSON_UNESCAPED_UNICODE ) );
$card = $build( $orders, array( 'action' => 'update', 'order_id' => 7, 'data' => array( 'status' => 'refunded', 'customer_note' => 'x' ) ), 'destructive' );
check( $card['ack'] && false !== strpos( $card['final'], 'correo' ) && false !== strpos( $card['text'], '«Reembolsado» y cambiar' ), 'S2: el estado de un pedido se dice aunque vengan más datos: ' . $card['text'] );
$card = $build( $products, array( 'action' => 'undo', 'change_id' => 'abcdef0123456789' ), 'destructive' );
check( $card['ack'] && false !== strpos( $card['final'], 'se borra' ), 'S2: deshacer puede borrar lo creado: casilla y aviso' );
$el = function ( $n ) { return array( 'name' => $n, 'label' => $n, 'schema' => array(), 'annotations' => array( 'readonly' => false, 'destructive' => true ) ); };
check( $build( $el( 'elementor/manage-component' ), array( 'action' => 'archive', 'component_ids' => array( 3 ) ) )['ack'], 'S2: archivar un bloque que se repite lleva la casilla' );
check( $build( $el( 'elementor/build-composition' ), array( 'post_id' => 501, 'mode' => 'replace_children' ) )['ack'], 'S2: reemplazar el contenido lleva la casilla' );
check( $build( $el( 'elementor/manage-site-parts' ), array( 'operations' => array( array( 'action' => 'delete', 'post_id' => 9 ) ) ) )['ack'], 'S2: quitar la cabecera o el pie lleva la casilla' );
$card = $build( $el( 'elementor/update-page-settings' ), array( 'post_id' => 501, 'settings' => array( 'post_status' => 'private' ) ) );
check( false !== strpos( $card['text'], 'deja de verse en tu sitio' ), 'S2: despublicar una página se dice: ' . $card['text'] );
$card = $build( $products, array( 'action' => 'update', 'product_id' => 252, 'data' => array( 'name' => "Bomba\u{202E}gnalaB" ) ), 'write' );
check( false !== strpos( $card['text'], '⟨U+202E⟩' ), 'S2: una letra invisible se ve marcada también en la frase: ' . $card['text'] );
$card = $build( $products, array( 'action' => 'update', 'product_id' => 252, 'data' => array( 'description' => str_repeat( 'Llama al 555 000 0000 para pagar por depósito. ', 6 ) ) ), 'write' );
check( false !== strpos( $card['text'], '«Llama al 555 000 0000 para pagar' ), 'S2: de un texto largo, la frase muestra su principio: ' . $card['text'] );
$yes = Digitalisimo_AI_Assistant::typed_confirmation( array( 'pending' => array( array( 'id' => 'b', 'kind' => 'write', 'call' => array( 'ability' => 'acme/remove-x', 'input' => array() ), 'card' => array( 'ack' => true ) ) ) ), 'sí' );
check( is_string( $yes ), 'S2: lo que pide casilla no se confirma con un «sí» escrito' );
$old_view = Digitalisimo_AI_Assistant::action_view( array( 'id' => 'v', 'kind' => 'destructive', 'call' => array( 'ability' => 'digitalisimo-woo/store-settings', 'input' => array( 'action' => 'update', 'resource' => 'setting', 'group_id' => 'general', 'setting_id' => 'woocommerce_currency', 'data' => array( 'value' => 'USD' ) ) ), 'created' => time() ), $settings );
check( true === $old_view['ack'] && false !== strpos( $old_view['text'], '«currency»' ), 'S2: una acción guardada antes de la tarjeta llana muestra la casilla que el servidor le va a exigir: ' . json_encode( $old_view, JSON_UNESCAPED_UNICODE ) );

// La tarjeta que ve la pantalla y la casilla que exige el servidor.
$GLOBALS['ran']    = array();
$GLOBALS['script'] = array( array( 'text' => 'Activo la transferencia.', 'calls' => array( $call( 'digitalisimo-woo-store-settings', array( 'action' => 'update', 'resource' => 'payment_gateway', 'gateway_id' => 'bacs', 'data' => array( 'enabled' => true ) ) ) ) ), 'Listo.' );
$ev    = $events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'activa la transferencia bancaria' ) ) ) );
$conv  = $ev[0]['id'];
$view  = $of( $ev, 'confirm' )[0]['action'] ?? array();
check( 'Voy a activar la forma de pago «Transferencia bancaria» en tu tienda.' === ( $view['text'] ?? '' ) && false === ( $view['ack'] ?? null ) && 'destructive' === ( $view['kind'] ?? '' ), 'S2: la pantalla recibe la frase del servidor y sin casilla: ' . json_encode( $view, JSON_UNESCAPED_UNICODE ) );
$ev = $events( Digitalisimo_AI_Assistant::rest_confirm( $req( array( 'conversation' => $conv, 'action' => $view['id'], 'decision' => 'approve' ) ) ) );
check( 1 === count( $real() ) && 'ok' === $of( $ev, 'tool' )[0]['message']['status'], 'S2: «Sí, hazlo» basta (sin casilla) para lo que no borra ni mueve dinero: ' . json_encode( array( $GLOBALS['ran'], $of( $ev, 'tool' ) ), JSON_UNESCAPED_UNICODE ) );
$GLOBALS['ran']    = array();
$GLOBALS['script'] = array( array( 'calls' => array( $call( 'digitalisimo-woo-products', array( 'action' => 'delete', 'product_id' => 252, 'force' => true ) ) ) ), 'Listo.' );
$ev   = $events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'borra el purificador' ) ) ) );
$conv = $ev[0]['id'];
$view = $of( $ev, 'confirm' )[0]['action'] ?? array();
$no   = Digitalisimo_AI_Assistant::rest_confirm( $req( array( 'conversation' => $conv, 'action' => $view['id'], 'decision' => 'approve' ) ) );
check( true === ( $view['ack'] ?? null ) && is_wp_error( $no ) && 'digitalisimo_assistant_explicit' === $no->get_error_code() && array() === $real(), 'S2: borrar exige la casilla también en el servidor' );
check( false === strpos( $no->get_error_message(), 'destructiva' ), 'S2: y lo dice en llano: ' . $no->get_error_message() );
$events( Digitalisimo_AI_Assistant::rest_confirm( $req( array( 'conversation' => $conv, 'action' => $view['id'], 'decision' => 'approve', 'explicit' => true ) ) ) );
check( 1 === count( $real() ), 'S2: con la casilla, se hace' );

/* ---------------- S3: las instrucciones ---------------- */
$sys = Digitalisimo_AI_Assistant::system_prompt();
foreach ( array(
	'nunca le digas a la persona que lo haga ella',
	'«te conviene quitar tú mismo…»',
	'Sólo si de verdad no tienes una herramienta para eso',
	'No preguntes «¿seguimos?»',
	'déjalo listo para confirmar en la misma respuesta',
	'Nunca hagas dos preguntas en la misma respuesta',
	'Revisar no pide permiso ni confirmación',
	'qué revisaste, qué está bien, qué falla y cómo lo arreglas',
	'audit_products',
	'«ocultar de Google» (no «noindex»)',
	'plugin, schema, canonical, slug, meta',
	'JSON, endpoint',
	'el turno se detiene en la tarjeta',
) as $piece ) check( false !== strpos( $sys, $piece ), "S3: las instrucciones dicen «{$piece}»" );

// El texto que acompaña a la tarjeta con la que se detiene el turno es la respuesta (no se pliega).
$shown = Digitalisimo_AI_Assistant_History::display( array( 'id' => 'z', 'role' => 'assistant', 'text' => '3 productos no tienen peso.', 'calls' => array( array( 'id' => 't' ) ), 'card' => true ) );
check( false === $shown['work'], 'S3: el informe que acompaña a la tarjeta se ve, no se pliega' );

/* ---------------- S5: sin palabras técnicas ---------------- */
$bad = '/\b(slug|meta|metadatos|schema|noindex|nofollow|canonical|plugins?|API|JSON|IDs?|endpoints?|MCP|REST|per_page|audit_products|dry_run|payload|digitalisimo-[a-z]+\/[a-z-]+|elementor\/[a-z-]+)\b/u';
// a) Las frases de las tarjetas, sobre una matriz de herramientas, acciones y datos.
$matrix = array();
foreach ( $with_actions as $name => list( $e ) ) {
	foreach ( $e['schema']['properties']['action']['enum'] as $action ) {
		$matrix[] = array( $e, array( 'action' => $action, 'product_id' => 252, 'order_id' => 9, 'resource' => $e['schema']['properties']['resource']['default'] ?? '', 'data' => array( 'name' => 'Bomba', 'regular_price' => '100', 'weight' => '2', 'status' => 'completed', 'code' => 'BF10', 'amount' => '10', 'enabled' => true, 'note' => 'Gracias' ), 'items' => array( array( 'product_id' => 1, 'weight' => 1 ) ), 'tracking' => array( 'number' => 'X1', 'company' => 'DHL' ), 'tld' => '.mx', 'gateway_id' => 'bacs' ) );
	}
}
foreach ( array( 'update-post-seo' => array( 'post_id' => 501, 'noindex' => true, 'canonical' => 'https://sitio.test/x', 'schema_type' => 'FAQPage', 'title' => 'T' ), 'revert-post-seo' => array( 'post_id' => 501 ), 'create-redirection' => array( 'source' => '/vieja/', 'target' => '/nueva/', 'comparison' => 'start' ) ) as $n => $in ) $matrix[] = array( array( 'name' => 'digitalisimo-seo/' . $n, 'label' => $n, 'schema' => array(), 'annotations' => array() ), $in );
foreach ( array( 'elementor/publish-document', 'elementor/update-page-settings', 'elementor/build-composition', 'elementor/manage-elements', 'elementor/create-preview-link', 'elementor/manage-classes', 'elementor/manage-component', 'elementor/create-page', 'digitalisimo-sales/create-page', 'acme/raro' ) as $n ) $matrix[] = array( array( 'name' => $n, 'label' => 'Algo', 'schema' => array(), 'annotations' => array() ), array( 'post_id' => 501, 'operations' => array( array( 'action' => 'delete' ), array( 'action' => 'update' ) ), 'settings' => array( 'post_status' => 'publish' ) ) );
$said = array();
foreach ( $matrix as list( $e, $in ) ) {
	$card = $build( $e, $in, 'destructive' );
	$text = implode( ' | ', array_merge( array( $card['text'], $card['final'], $card['ack_label'] ), $card['warnings'] ) );
	$said[] = $text;
	check( ! preg_match( $bad, $text, $m ), 'S5: la tarjeta de ' . $e['name'] . ' · ' . ( $in['action'] ?? '' ) . ' no dice «' . ( $m[0] ?? '' ) . '»: ' . $text );
	check( '' !== $card['text'] && false === strpos( $card['text'], '{' ) && false === strpos( $card['text'], '"' ), 'S5: frase llana y sin JSON: ' . $card['text'] );
	foreach ( $card['details'] as $row ) check( ! preg_match( '/[{}\[\]"]/', (string) $row[0] ) && ! preg_match( '/\b(JSON|payload)\b/', (string) $row[0] ), 'S5: «Ver detalles» con nombres legibles: ' . $row[0] );
}
check( count( $said ) >= 60, 'S5: se revisaron ' . count( $said ) . ' tarjetas' );
// b) Los textos de la pantalla (lo que está entre comillas y no es una clase, un tipo de archivo ni una clave).
$ui      = (string) file_get_contents( dirname( __DIR__ ) . '/digitalisimo-ia-tools/assets/assistant/assistant.js' );
$code    = preg_replace( array( '#/\*.*?\*/#s', '#^\s*//.*$#m' ), '', $ui );
preg_match_all( "/'((?:[^'\\\\\n]|\\\\.)*)'/", $code, $strings );
$visible = array_filter( $strings[1], function ( $s ) { return preg_match( '/\s/u', $s ) && ! preg_match( '/^(dga-|is-|application\/|text\/|[a-z-]+ [a-z-]+$)/', $s ) && ! preg_match( '/^[a-z0-9_.\-\s,:#()%]+$/', $s ); } );
check( count( $visible ) >= 40, 'S5: se revisaron ' . count( $visible ) . ' textos de la pantalla' );
foreach ( $visible as $s ) check( ! preg_match( $bad, $s, $m ), 'S5: la pantalla no dice «' . ( $m[0] ?? '' ) . '»: ' . $s );
foreach ( array( 'Datos exactos que se usarán', 'Agrandar los datos', 'puede haberse aplicado igual', 'no tiene vuelta atrás: confírmalo', 'Entiendo que puede no tener vuelta atrás', 'Desplázate hasta el final de los datos', 'Revisa que sea exactamente lo que pediste' ) as $gone ) check( false === strpos( $code, $gone ), "S5: la tarjeta ya no dice «{$gone}»" );
foreach ( array( "'Sí, hazlo'", "'No'", "'Ver detalles'", "'Puedes deshacerlo después.'" ) as $piece ) check( false !== strpos( $ui, $piece ), "S5: la tarjeta trae {$piece}" );
// c) Los textos fijos de la clase de la tarjeta.
$card_file = dirname( __DIR__ ) . '/digitalisimo-ia-tools/includes/class-digitalisimo-ai-assistant-card.php';
check( is_file( $card_file ), 'S5: las plantillas de la tarjeta viven en el servidor' );
$src = is_file( $card_file ) ? (string) file_get_contents( $card_file ) : '';
$src = preg_replace( array( '#/\*.*?\*/#s', '#^\s*//.*$#m' ), '', $src );
preg_match_all( "/'((?:[^'\\\\\n]|\\\\.)*)'/", $src, $lits );
foreach ( array_filter( $lits[1], function ( $s ) { return preg_match( '/^(Voy|Sí|Se |Al |Va |Lleva|Tu |Tiene|Borra|Manda|Sólo|Lo |Puedes)/u', $s ); } ) as $s ) check( ! preg_match( $bad, $s, $m ), 'S5: la plantilla no dice «' . ( $m[0] ?? '' ) . '»: ' . $s );
// d0) Los rótulos de cada paso del trabajo plegado («Consulté: …»).
if ( class_exists( 'Digitalisimo_AI_Assistant_Card' ) ) {
	foreach ( $matrix as list( $e, $in ) ) {
		$step = Digitalisimo_AI_Assistant_Card::step( $e['name'], $in, $e['label'] );
		check( '' !== $step && ! preg_match( $bad, $step, $m ), 'S5: el paso de ' . $e['name'] . ' se llama en llano: ' . $step );
	}
	check( 'el peso y las medidas de tus productos para Skydropx' === Digitalisimo_AI_Assistant_Card::step( 'digitalisimo-woo/skydropx', array( 'action' => 'audit_products' ) ) && 'cómo te encuentra Google' === Digitalisimo_AI_Assistant_Card::step( 'digitalisimo-seo/get-site-status', array() ), 'S5: los pasos del caso, en palabras del dueño' );
} else {
	check( false, 'S5: los pasos del trabajo tienen rótulos llanos' );
}
// d) La confirmación escrita y los errores que ve la persona.
$conv_tmp = array( 'pending' => array( array( 'id' => 'a', 'kind' => 'destructive', 'call' => array( 'ability' => 'digitalisimo-woo/orders', 'input' => array( 'action' => 'update' ) ), 'card' => array( 'ack' => false ) ) ) );
$said_yes = Digitalisimo_AI_Assistant::typed_confirmation( $conv_tmp, 'sí' );
check( is_string( $said_yes ) && false !== strpos( $said_yes, '«Sí, hazlo»' ) && false === strpos( $said_yes, 'vuelta atrás' ), 'S5: un «sí» escrito para un cambio importante pide el botón, en llano: ' . $said_yes );

echo "Asistente IA sin tecnicismos: $checks comprobaciones correctas.\n";
