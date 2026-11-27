<?php
/**
 * El MCP con acceso total por defecto, sin interruptores (decisión del dueño del 2026-11-27):
 *   A1  el servidor MCP del sitio siempre activo (`elementor_mcp_enabled` vale 1 aunque se guardara 0 o nunca se guardara);
 *   A2  la migración borra los ajustes viejos de la tienda (sitio y red), una vez, en cada sitio de una red, sin tocar
 *       nada más;
 *   A3  «Mostrar el prompt para vincular» es la única opción: se muestra por defecto con la dirección del servidor, la
 *       configuración del cliente (Claude Code y Claude Desktop) y dónde pegarla; guardarlo exige manage_options, POST y
 *       nonce; ocultarlo no apaga nada; en la red sólo hay una nota;
 *   A4  la bitácora única: anota quién, vía, herramienta, acción, objeto (sólo IDs) y resultado de cada escritura de
 *       cualquier ability (también con error o excepción), nunca valores ni secretos; no anota lecturas ni simulaciones;
 *       se recorta por número y por días; se consulta con una herramienta de lectura que exige manage_options;
 *   A5  sin autenticación el transporte del adaptador responde 401 (su permiso por defecto pide `read`).
 * Falla con el código anterior (no existían `Access` ni `Write_Log`). Uso: php tests/elements-mcp-access.php
 */
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'DAY_IN_SECONDS', 86400 );
	$checks = 0;
	function check( $ok, $message ) {
		global $checks;
		if ( ! $ok ) {
			fwrite( STDERR, "FALLA: $message\n" );
			exit( 1 );
		}
		++$checks;
	}
	$GLOBALS['t'] = array();
	function t_reset( array $over = array() ) {
		$GLOBALS['t'] = array_merge(
			array(
				'options'   => array(),
				'autoload'  => array(),
				'site'      => array(),
				'multisite' => false,
				'blog'      => 1,
				'caps'      => array(),
				'user'      => 7,
				'login'     => 'ana',
				'filters'   => array(),
				'actions'   => array(),
				'writes'    => array(),
			),
			$over
		);
	}
	t_reset();
	function add_filter( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['t']['filters'][ $hook ][] = $cb; return true; }
	function add_action( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['t']['actions'][ $hook ][] = $cb; return true; }
	function apply_filters( $hook, $value, ...$args ) { foreach ( $GLOBALS['t']['filters'][ $hook ] ?? array() as $cb ) { $value = call_user_func_array( $cb, array_merge( array( $value ), $args ) ); } return $value; }
	function get_option( $name, $default = false ) {
		$pre = apply_filters( 'pre_option_' . $name, false, $name, $default );
		if ( false !== $pre ) return $pre;
		return array_key_exists( $name, $GLOBALS['t']['options'] ) ? $GLOBALS['t']['options'][ $name ] : $default;
	}
	function update_option( $name, $value, $autoload = null ) { $GLOBALS['t']['writes'][] = $name; $GLOBALS['t']['options'][ $name ] = $value; if ( null !== $autoload ) $GLOBALS['t']['autoload'][ $name ] = $autoload; return true; }
	function add_option( $name, $value, $dep = '', $autoload = true ) { $GLOBALS['t']['writes'][] = $name; $GLOBALS['t']['options'][ $name ] = $value; $GLOBALS['t']['autoload'][ $name ] = $autoload; return true; }
	function delete_option( $name ) { $GLOBALS['t']['writes'][] = 'delete:' . $name; unset( $GLOBALS['t']['options'][ $name ] ); return true; }
	function get_site_option( $name, $default = false ) { return array_key_exists( $name, $GLOBALS['t']['site'] ) ? $GLOBALS['t']['site'][ $name ] : $default; }
	function delete_site_option( $name ) { $GLOBALS['t']['writes'][] = 'delete-site:' . $name; unset( $GLOBALS['t']['site'][ $name ] ); return true; }
	function is_multisite() { return $GLOBALS['t']['multisite']; }
	function get_current_blog_id() { return $GLOBALS['t']['blog']; }
	function current_user_can( $cap ) { return in_array( $cap, $GLOBALS['t']['caps'], true ); }
	function wp_get_current_user() { return (object) array( 'ID' => $GLOBALS['t']['user'], 'user_login' => $GLOBALS['t']['login'] ); }
	function is_wp_error( $v ) { return $v instanceof WP_Error; }
	class WP_Error { public $code; public $message; public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->message = $m; } public function get_error_code() { return $this->code; } }
	function rest_url( $path = '' ) { return 'https://tienda.test/wp-json/' . $path; }
	function home_url( $path = '' ) { return 'https://tienda.test' . $path; }
	function admin_url( $path = '' ) { return 'https://tienda.test/wp-admin/' . $path; }
	function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
	function sanitize_title( $t ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $t ) ), '-' ); }
	function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); }
	function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
	function esc_textarea( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $u ) { return (string) $u; }
	function add_query_arg( array $args, $url ) { return $url . '?' . http_build_query( $args ); }
	function wp_nonce_field( $action ) { echo '<input type="hidden" name="_wpnonce" value="nonce:' . esc_attr( $action ) . '">'; }
	function wp_verify_nonce( $nonce, $action ) { return 'nonce:' . $action === $nonce; }
	function sanitize_text_field( $v ) { return trim( (string) $v ); }
	function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); }
	function wp_unslash( $v ) { return $v; }

	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-mcp/class-access.php';
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-mcp/class-write-log.php';
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-woo/mcp/class-settings.php';
	$A = 'Digitalisimo\\Elements\\Mcp\\Access';
	$L = 'Digitalisimo\\Elements\\Mcp\\Write_Log';

	// ------------------------------------------------------------------------------------------------- A1
	$A::init();
	check( isset( $GLOBALS['t']['filters']['pre_option_elementor_mcp_enabled'] ), 'A1: engancha pre_option_elementor_mcp_enabled.' );
	check( 1 === get_option( 'elementor_mcp_enabled' ), 'A1: sin nada guardado, el MCP está activo.' );
	$GLOBALS['t']['options']['elementor_mcp_enabled'] = false;
	check( 1 === get_option( 'elementor_mcp_enabled' ), 'A1: con 0 guardado (el interruptor del núcleo apagado), también.' );
	check( isset( $GLOBALS['t']['actions']['init'], $GLOBALS['t']['actions'][ 'admin_post_' . $A::ACTION_SAVE ] ), 'A1: la migración en init y el guardado del prompt en admin-post.' );

	// ------------------------------------------------------------------------------------------------- A2
	$woo = 'Digitalisimo\\Elements\\Woo\\Mcp\\Settings';
	check( array( $woo::OPTION ) === $A::LEGACY_SITE_OPTIONS && array( $woo::NETWORK_OPTION ) === $A::LEGACY_NETWORK_OPTIONS, 'A2: las opciones que se borran son las de «WooCommerce en el MCP».' );
	foreach ( array( false, true ) as $multi ) {
		$filters = $GLOBALS['t']['filters'];
		t_reset( array( 'multisite' => $multi, 'filters' => $filters, 'options' => array( $woo::OPTION => array( 'mode' => 'read', 'own' => array( 'mode' ) ), 'otra' => 1 ), 'site' => array( $woo::NETWORK_OPTION => array( 'mode' => 'off' ), 'otra_red' => 2 ) ) );
		check( true === $A::migrate(), 'A2: migra la primera vez.' );
		check( ! array_key_exists( $woo::OPTION, $GLOBALS['t']['options'] ) && 1 === $GLOBALS['t']['options']['otra'], 'A2: borra el modo del sitio y nada más.' );
		check( $multi === ! array_key_exists( $woo::NETWORK_OPTION, $GLOBALS['t']['site'] ) && 2 === $GLOBALS['t']['site']['otra_red'], 'A2: en una red borra también el modo de red (y sólo en una red).' );
		check( $A::VERSION === $GLOBALS['t']['options'][ $A::VERSION_OPTION ] && true === $GLOBALS['t']['autoload'][ $A::VERSION_OPTION ], 'A2: deja la marca con carga automática.' );
		$GLOBALS['t']['writes'] = array();
		check( false === $A::migrate() && array() === $GLOBALS['t']['writes'], 'A2: idempotente: la segunda vez no escribe nada.' );
	}
	// Cada sitio de una red migra en su primera petición (su marca es suya).
	t_reset( array( 'multisite' => true, 'blog' => 2, 'options' => array( $woo::OPTION => array( 'mode' => 'read' ) ) ) );
	check( true === $A::migrate() && ! array_key_exists( $woo::OPTION, $GLOBALS['t']['options'] ), 'A2: otro sitio de la red migra con su propia marca.' );

	// ------------------------------------------------------------------------------------------------- A3
	t_reset( array( 'caps' => array( 'manage_options' ) ) );
	check( true === $A::show_prompt(), 'A3: el prompt se muestra por defecto.' );
	ob_start();
	$A::render( false );
	$html = (string) ob_get_clean();
	check( 1 === substr_count( $html, 'type="checkbox"' ) && false !== strpos( $html, 'Mostrar el prompt para vincular' ) && false !== strpos( $html, 'checked="checked"' ), 'A3: una sola opción visible, marcada.' );
	check( false !== strpos( $html, 'https://tienda.test/wp-json/elementor/mcp' ), 'A3: la dirección del servidor de ESTE sitio.' );
	check( false !== strpos( $html, '&quot;mcpServers&quot;' ) && false !== strpos( $html, '&quot;type&quot;: &quot;http&quot;' ) && false !== strpos( $html, 'mcp-remote' ) && false !== strpos( $html, 'Authorization' ), 'A3: la configuración de Claude Code y de Claude Desktop, con la cabecera de autenticación.' );
	check( false !== strpos( $html, '.mcp.json' ) && false !== strpos( $html, 'claude_desktop_config.json' ) && false !== strpos( $html, 'Contraseñas de aplicación' ), 'A3: dice dónde pegarla y cómo crear la contraseña de aplicación.' );
	check( false !== strpos( $html, 'Ocultarlo no apaga nada' ) && ! preg_match( '/s[oó]lo lectura|Lectura y escritura»|Escritura s[oó]lo en lo elegido/iu', $html ), 'A3: sin modos; ocultar no apaga nada.' );
	$config = json_decode( $A::client_config( 'code', 'ABC' ), true );
	check( array( 'type' => 'http', 'url' => 'https://tienda.test/wp-json/elementor/mcp', 'headers' => array( 'Authorization' => 'Basic ABC' ) ) === $config['mcpServers']['tienda-test'], 'A3: JSON válido de Claude Code con el nombre del sitio: ' . $A::client_config( 'code', 'ABC' ) );
	$config = json_decode( $A::client_config( 'desktop', 'ABC' ), true );
	check( array( '-y', 'mcp-remote', 'https://tienda.test/wp-json/elementor/mcp', '--header', 'Authorization: Basic ABC' ) === $config['mcpServers']['tienda-test']['args'], 'A3: JSON válido de Claude Desktop.' );
	check( false === strpos( $html, 'ana:' ) && false !== strpos( $html, 'USUARIO_Y_CONTRASEÑA_EN_BASE64' ), 'A3: ninguna credencial en la página: la completa el navegador.' );
	// Guardar: ocultar, mostrar; capacidad, POST y nonce.
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_POST = array( '_wpnonce' => 'nonce:' . $A::nonce_action(), 'show_prompt' => '0' );
	check( array( 'notice' => 'saved' ) === $A::process_save() && false === $A::show_prompt() && false === $GLOBALS['t']['autoload'][ $A::PROMPT_OPTION ], 'A3: ocultar se guarda (sin carga automática).' );
	ob_start();
	$A::render( false );
	$html = (string) ob_get_clean();
	check( false === strpos( $html, 'mcpServers' ) && false !== strpos( $html, 'Mostrar el prompt para vincular' ) && false === strpos( $html, 'checked="checked"' ), 'A3: oculto, sólo queda la casilla.' );
	check( 1 === get_option( 'elementor_mcp_enabled' ), 'A3: ocultar el prompt no apaga el MCP.' );
	$_POST['show_prompt'] = '1';
	check( 'saved' === $A::process_save()['notice'] && true === $A::show_prompt() && ! array_key_exists( $A::PROMPT_OPTION, $GLOBALS['t']['options'] ), 'A3: mostrar borra la opción.' );
	$_POST['_wpnonce'] = 'nonce:' . $A::ACTION_SAVE . '|2';
	check( 'nonce' === $A::process_save()['notice'], 'A3: un nonce de otro sitio no vale.' );
	$_POST['_wpnonce'] = 'nonce:' . $A::nonce_action();
	$GLOBALS['t']['caps'] = array( 'edit_posts' );
	check( 'forbidden' === $A::process_save()['notice'], 'A3: sin manage_options, rechazado.' );
	$GLOBALS['t']['caps']      = array( 'manage_options' );
	$_SERVER['REQUEST_METHOD'] = 'GET';
	check( 'method' === $A::process_save()['notice'], 'A3: sólo por POST.' );
	$_POST = array();
	ob_start();
	$A::render( true );
	$net = (string) ob_get_clean();
	check( false === strpos( $net, '<form' ) && false !== strpos( $net, 'no hay ajustes de red' ), 'A3: en la red, sólo una nota.' );

	// ------------------------------------------------------------------------------------------------- A4
	t_reset( array( 'caps' => array( 'manage_options' ) ) );
	$L::init();
	check( isset( $GLOBALS['t']['filters']['wp_register_ability_args'], $GLOBALS['t']['actions']['wp_abilities_api_init'], $GLOBALS['t']['filters']['elementor/mcp/server/tools'] ), 'A4: envuelve abilities, registra su herramienta y la pone en el servidor.' );
	$ran   = array();
	$write = array( 'label' => 'x', 'execute_callback' => function ( $in ) use ( &$ran ) { $ran[] = $in; return array( 'ok' => true ); }, 'meta' => array( 'annotations' => array( 'readonly' => false, 'destructive' => true ) ) );
	$read  = array( 'label' => 'y', 'execute_callback' => function ( $in ) { return array(); }, 'meta' => array( 'annotations' => array( 'readonly' => true ) ) );
	check( $read === $L::wrap( $read, 'digitalisimo-seo/get-post-seo' ), 'A4: una ability de lectura no se envuelve.' );
	$wrapped = $L::wrap( $write, 'digitalisimo-woo/products' );
	$secret  = array( 'action' => 'update', 'product_id' => 12, 'resource' => 'product', 'data' => array( 'name' => 'Mesa', 'api_key' => 'sk_live_SECRETO', 'password' => 'p4ss' ) );
	call_user_func( $wrapped['execute_callback'], $secret );
	check( array( $secret ) === $ran, 'A4: la ability se ejecuta igual.' );
	$entry = $L::page()['entries'][0];
	check( 7 === $entry['user'] && 'ana' === $entry['login'] && 'mcp' === $entry['via'] && 'digitalisimo-woo/products' === $entry['ability'] && 'update' === $entry['action'] && array( 'resource' => 'product', 'product_id' => 12 ) === $entry['object'] && 'ok' === $entry['result'] && '' !== $entry['date'], 'A4: quién, vía, herramienta, acción, objeto y resultado: ' . json_encode( $entry ) );
	$stored = json_encode( get_option( $L::OPTION ) );
	check( false === strpos( $stored, 'SECRETO' ) && false === strpos( $stored, 'p4ss' ) && false === strpos( $stored, 'Mesa' ), 'A4: ni secretos ni valores en la bitácora.' );
	check( false === $GLOBALS['t']['autoload'][ $L::OPTION ], 'A4: la bitácora no tiene carga automática.' );
	call_user_func( $wrapped['execute_callback'], array( 'action' => 'list' ) );
	call_user_func( $wrapped['execute_callback'], array( 'action' => 'delete', 'product_id' => 12, 'dry_run' => true ) );
	check( 1 === $L::page()['total'], 'A4: lecturas y simulaciones no se anotan.' );
	$err = $L::wrap( array( 'execute_callback' => function () { return new WP_Error( 'digitalisimo_hosting_price_loss', 'Vendería con pérdida.' ); }, 'meta' => array() ), 'digitalisimo-hosting/settings' );
	call_user_func( $err['execute_callback'], array( 'action' => 'update' ) );
	check( 'digitalisimo_hosting_price_loss' === $L::page()['entries'][0]['result'], 'A4: un error (por ejemplo, una guarda de negocio) queda con su código; una ability sin anotaciones cuenta como escritura.' );
	$boom = $L::wrap( array( 'execute_callback' => function () { throw new RuntimeException( 'x' ); }, 'meta' => array() ), 'elementor/manage-elements' );
	try { call_user_func( $boom['execute_callback'], array( 'post_id' => 5 ) ); check( false, 'A4: la excepción debía seguir.' ); } catch ( RuntimeException $e ) { /* sigue */ }
	check( 'exception' === $L::page()['entries'][0]['result'] && array( 'post_id' => 5 ) === $L::page()['entries'][0]['object'], 'A4: una excepción queda anotada y sigue su curso.' );
	class Digitalisimo_AI_Assistant_Context { public static $c = null; public static function current() { return self::$c; } }
	Digitalisimo_AI_Assistant_Context::$c = array( 'ability' => 'digitalisimo-woo/products' );
	call_user_func( $wrapped['execute_callback'], array( 'action' => 'create' ) );
	Digitalisimo_AI_Assistant_Context::$c = null;
	check( 'assistant' === $L::page()['entries'][0]['via'] && 1 === $L::page( 1, 50, '', 'assistant' )['total'] && 2 === $L::page( 1, 50, 'digitalisimo-woo/' )['total'], 'A4: el Asistente queda como vía; filtros por vía y por herramienta.' );
	check( array() === $L::object_of( array( 'resource' => 'product; DROP', 'post_id' => 'abc', 'data' => array( 'id' => 3 ) ) ) && array( 'post_id' => 4 ) === $L::object_of( array( 'post_id' => '4' ) ), 'A4: del objeto sólo salen IDs numéricos y nombres limpios.' );
	// Tope por número y por días.
	$old = array();
	for ( $i = 0; $i < $L::MAX + 20; $i++ ) $old[] = array( 'time' => time(), 'ability' => 'a', 'result' => 'ok' );
	array_unshift( $old, array( 'time' => time() - ( $L::DAYS + 1 ) * DAY_IN_SECONDS, 'ability' => 'vieja', 'result' => 'ok' ) );
	$GLOBALS['t']['options'][ $L::OPTION ] = $old;
	call_user_func( $wrapped['execute_callback'], array( 'action' => 'update', 'product_id' => 1 ) );
	$all = get_option( $L::OPTION );
	check( $L::MAX === count( $all ) && 'vieja' !== $all[0]['ability'], 'A4: se recorta a ' . $L::MAX . ' entradas y ' . $L::DAYS . ' días.' );
	// La herramienta de lectura.
	$def = $L::definition();
	check( true === $def['meta']['annotations']['readonly'] && true === $def['meta']['mcp']['public'] && false === $def['input_schema']['additionalProperties'], 'A4: herramienta de lectura, pública para MCP, entrada cerrada.' );
	check( true === $L::permission() && 3 === count( $L::execute( array( 'per_page' => 3 ) )['entries'] ), 'A4: un administrador la consulta, paginada.' );
	$GLOBALS['t']['caps'] = array( 'edit_posts' );
	check( false === $L::permission(), 'A4: sin manage_options, no.' );
	check( array( 'elementor/otra', $L::ABILITY ) === $L::server_tools( array( 'elementor/otra' ) ), 'A4: en el servidor MCP del sitio.' );
	$self = array( 'execute_callback' => 'strlen', 'meta' => array() );
	check( $self === $L::wrap( $self, $L::ABILITY ), 'A4: la propia bitácora no se envuelve (no se anota a sí misma).' );

	// ------------------------------------------------------------------------------------------------- A5
	$transport = (string) file_get_contents( __DIR__ . '/../digitalisimo-elements/elementor/vendor/wordpress/mcp-adapter/includes/Transport/HttpTransport.php' );
	check( false !== strpos( $transport, "apply_filters( 'mcp_adapter_default_transport_permission_user_capability', 'read'" ) && false !== strpos( $transport, 'current_user_can( $user_capability )' ), 'A5: el transporte exige un usuario con «read» (sin autenticación, 401).' );
	$src = (string) file_get_contents( __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-mcp/class-access.php' ) . (string) file_get_contents( __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-mcp/class-write-log.php' );
	check( false === strpos( $src, 'mcp_adapter_default_transport_permission_user_capability' ) && false === strpos( $src, 'transport_permission_callback' ) && false === strpos( $src, 'determine_current_user' ), 'A5: nada de esto toca la autenticación del transporte.' );

	echo "MCP con acceso total: $checks comprobaciones.\n";
}
