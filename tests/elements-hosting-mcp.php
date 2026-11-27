<?php
/**
 * DIGITALÍSIMO Hosting en el MCP de DIGITALÍSIMO Elements (`modules/digitalisimo-hosting/`, pedido del propietario del
 * 2026-11-26): `digitalisimo-hosting/settings` (get, update que combina, log) y `digitalisimo-hosting/domain-product`
 * (list, mark, unmark), registradas como las de tienda y del kit de ventas (con el registro de abilities del núcleo y
 * sin él), con el DIGITALÍSIMO Hosting real detrás (tests/fixtures/hosting-wp.php):
 *   - permiso: settings exige manage_options; domain-product, manage_woocommerce o manage_options (true estricto);
 *   - get nunca devuelve secretos; update combina y no borra; la cuenta de Namecheap no se escribe;
 *   - en una red se escribe la opción del sitio, nunca la de red;
 *   - bitácora de escrituras sin valores ni datos personales (también lo denegado);
 *   - esquemas cerrados, sin post_id; con el MCP apagado no se registra nada.
 * Falla con el código anterior: el módulo no existe.
 */
namespace Elementor\Modules\Mcp\Abilities {
	class Ability_Definition {
		public $label, $description, $category, $output_schema, $meta, $permission_callback, $input_schema;
		public function __construct( $label, $description, $category, $output_schema, $meta, $permission_callback, $input_schema = array() ) {
			$this->label = $label; $this->description = $description; $this->category = $category; $this->output_schema = $output_schema;
			$this->meta = $meta; $this->permission_callback = $permission_callback; $this->input_schema = $input_schema;
		}
	}
	abstract class Abstract_Ability {
		abstract protected function get_ability_id(): string;
		abstract protected function get_definition(): Ability_Definition;
		abstract public function execute( $input = array() );
		final public function execute_guarded( $input = array() ) { $GLOBALS['ab']['guarded'][] = $this->get_id(); return $this->execute( $input ); }
		public function check_permission(): bool { return (bool) call_user_func( $this->get_definition()->permission_callback ); }
		public function register(): void {}
		public function get_id(): string { return $this->get_ability_id(); }
		public function is_exposed_via_proxy(): bool { return true; }
		public function definition_for_test(): Ability_Definition { return $this->get_definition(); }
	}
}
namespace Elementor\Modules\Mcp\Registry {
	class Ability_Registry { public $abilities = array(); public function add( $ability ): void { $this->abilities[ $ability->get_id() ] = $ability; } }
}
namespace Elementor\Modules\Mcp {
	class Module { public $test_registry; public function registry() { return $this->test_registry; } }
}
namespace Elementor\MCP\Composer\Admin {
	class McpSettingsController { public static function is_enabled(): bool { return ! empty( $GLOBALS['ab']['mcp'] ); } }
}
namespace Elementor {
	class Plugin { public static $instance; }
}
namespace {
	require __DIR__ . '/fixtures/hosting-wp.php';
	use Digitalisimo\Elements\Hosting\Mcp\Executor;
	use Digitalisimo\Elements\Hosting\Mcp\Module as HMod;
	use Digitalisimo\Elements\Hosting\Mcp\Tools;

	class Digi_Test_Modules { public $mcp; public function get_modules( $n ) { return 'mcp' === $n ? $this->mcp : null; } }
	\Elementor\Plugin::$instance = (object) array( 'modules_manager' => new Digi_Test_Modules() );
	$GLOBALS['ab'] = array( 'mcp' => true, 'abilities' => array(), 'categories' => array(), 'guarded' => array() );
	function wp_register_ability( $id, $args ) { $GLOBALS['ab']['abilities'][ $id ] = $args; }
	function wp_register_ability_category( $id, $args ) { $GLOBALS['ab']['categories'][ $id ] = $args; }
	if ( ! function_exists( 'get_user_meta' ) ) { function get_user_meta( $u, $k = '', $s = false ) { return ''; } }
	$base = __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-hosting';
	require $base . '/class-module.php';
	\Digitalisimo\Elements\Hosting\Module::init();
	require_once $base . '/mcp/class-ability.php';

	function hooks_named( $name ) { return $GLOBALS['hooks'][ $name ] ?? array(); }
	function opt() { return get_option( 'digitalisimo_hosting_options', array() ); }
	function log_entries() { return get_option( Executor::LOG, array() ); }

	digi_servers_reset();
	digi_store_settings( array( 'banxico_token' => 'TOKEN-BANXICO-SECRETO' ) );

	// ── Registro ──────────────────────────────────────────────────────────────────────────────────────────────
	check( Tools::hosting_ready(), 'Con DIGITALÍSIMO Hosting cargado, sus herramientas están listas' );
	check( isset( hooks_named( 'elementor/init' )[20] ), 'init: arranca en elementor/init' );
	$registry = new \Elementor\Modules\Mcp\Registry\Ability_Registry();
	$mcp = new \Elementor\Modules\Mcp\Module(); $mcp->test_registry = $registry;
	\Elementor\Plugin::$instance->modules_manager->mcp = $mcp;
	HMod::boot();
	check( array( 'digitalisimo-hosting/settings', 'digitalisimo-hosting/domain-product' ) === array_keys( $registry->abilities ), 'Con el registro del núcleo: las dos herramientas' );
	check( 'digitalisimo-hosting-settings' === str_replace( '/', '-', Tools::id( 'settings' ) ), 'El adaptador la publica como digitalisimo-hosting-settings' );
	$settings = $registry->abilities['digitalisimo-hosting/settings'];
	$product_tool = $registry->abilities['digitalisimo-hosting/domain-product'];
	check( false === $settings->is_exposed_via_proxy() && true === $settings->check_permission(), 'Fuera del proxy del editor; el administrador tiene permiso' );
	foreach ( Tools::TOOLS as $t ) {
		$schema = Tools::input_schema( $t );
		check( false === $schema['additionalProperties'] && ! isset( $schema['properties']['post_id'] ) && array( 'action' ) === $schema['required'], "Esquema cerrado de $t, sin post_id" );
		check( false === Tools::definition( $t )['meta']['show_in_rest'], "$t no sale en la REST de abilities" );
		check( true === Tools::definition( $t )['meta']['annotations']['destructive'] && false === Tools::definition( $t )['meta']['annotations']['idempotent'], "$t se anuncia como escritura que pide confirmación (destructive, no idempotente)" );
	}
	$GLOBALS['hooks']['wp_abilities_api_categories_init'][10][0][0]();
	check( 'Hosting y dominios' === $GLOBALS['ab']['categories']['digitalisimo-hosting']['label'], 'Categoría propia' );
	$GLOBALS['hooks'] = array();
	HMod::reset(); HMod::init();
	\Elementor\Plugin::$instance->modules_manager->mcp = null;
	HMod::boot();
	check( isset( hooks_named( 'wp_abilities_api_init' )[10] ) && isset( hooks_named( 'elementor/mcp/server/tools' )[10] ), 'Sin registro del núcleo: wp_abilities_api_init y el filtro del servidor' );
	HMod::register_abilities();
	check( isset( $GLOBALS['ab']['abilities']['digitalisimo-hosting/settings'] ) && array( $GLOBALS['ab']['abilities']['digitalisimo-hosting/settings']['execute_callback'][1] ) === array( 'execute_guarded' ), 'y wp_register_ability con execute_guarded' );
	check( array( 'x', 'digitalisimo-hosting/settings', 'digitalisimo-hosting/domain-product' ) === HMod::tool_slugs( array( 'x' ) ), 'tool_slugs' );
	$GLOBALS['hooks'] = array(); $GLOBALS['ab']['mcp'] = false;
	HMod::reset(); HMod::boot();
	check( ! HMod::abilities() && ! hooks_named( 'wp_abilities_api_init' ), 'Con el MCP del sitio apagado no se registra nada' );
	$GLOBALS['ab']['mcp'] = true;

	// ── Permisos ──────────────────────────────────────────────────────────────────────────────────────────────
	$GLOBALS['caps'] = array( 'manage_woocommerce', 'edit_posts' );
	check( false === $settings->check_permission() && true === $product_tool->check_permission(), 'manage_woocommerce: marca productos pero no configura Hosting' );
	$snap = serialize( opt() );
	$e = Executor::run( 'settings', array( 'action' => 'update', 'data' => array( 'replace_hosting' => true ) ) );
	check( $e instanceof WP_Error && 'digitalisimo_hosting_mcp_forbidden' === $e->code && serialize( opt() ) === $snap, 'Sin manage_options, update se rechaza y no cambia nada' );
	check( 'denied' === log_entries()[0]['result'] && 'update' === log_entries()[0]['action'], 'Lo denegado queda en la bitácora' );
	$e = Executor::run( 'settings', array( 'action' => 'get' ) );
	check( $e instanceof WP_Error, 'Sin manage_options tampoco se leen los ajustes' );
	$GLOBALS['caps'] = array( 'edit_posts' );
	check( false === $product_tool->check_permission() && Executor::run( 'domain-product', array( 'action' => 'mark', 'product_id' => 20, 'tld' => '.com' ) ) instanceof WP_Error && '' === get_post_meta( 20, '_digitalisimo_tld', true ), 'Sin manage_woocommerce ni manage_options no se marca nada' );
	unset( $GLOBALS['caps'] );

	// ── get / update ──────────────────────────────────────────────────────────────────────────────────────────
	$g = $settings->execute_guarded( array( 'action' => 'get' ) );
	$json = wp_json_encode( $g );
	foreach ( array( 'CLAVE-NAMECHEAP', 'CLAVE-WEBUZO-OK', 'TOKEN-BANXICO-SECRETO', 'nc-api', '203.0.113.5' ) as $s ) check( false === strpos( $json, $s ), "get no devuelve «{$s}»" );
	check( true === $g['secrets_set']['api_key'] && in_array( 'digitalisimo-hosting/settings', $GLOBALS['ab']['guarded'], true ), 'get dice qué secretos están puestos (pasa por execute_guarded)' );
	$before = opt();
	$r = Executor::run( 'settings', array( 'action' => 'update', 'data' => array( 'domain_products' => ".com|20\n.net|\n.mx|21", 'hosting_category_ids' => '63, 64' ) ) );
	$after = opt();
	check( is_array( $r ) && ".com|20\n.net|\n.mx|21" === $after['domain_products'] && '63, 64' === $after['hosting_category_ids'], 'update guarda lo enviado' );
	foreach ( array( 'api_key', 'webuzo_api_key', 'banxico_token', 'provision_enabled', 'nameservers', 'grace_days' ) as $k ) check( $before[ $k ] === $after[ $k ], "update combina: «{$k}» sigue igual" );
	check( ".com|20\n.net|\n.mx|21" === $r['settings']['domain_products'] && in_array( 'domain_products', $r['changed'], true ), 'update devuelve el valor resultante' );
	$entry = log_entries()[0];
	check( 'ok' === $entry['result'] && array( 'domain_products', 'hosting_category_ids' ) === $entry['keys'] && 1 === $entry['user'] && 'settings' === $entry['tool'], 'La bitácora anota usuario, acción y los nombres de las claves' );
	check( false === strpos( serialize( log_entries() ), '.com|20' ) && false === strpos( serialize( log_entries() ), '63, 64' ), 'La bitácora nunca guarda valores' );
	$snap = serialize( opt() );
	$e = Executor::run( 'settings', array( 'action' => 'update', 'data' => array( 'api_key' => 'ROBADA', 'client_ip' => '1.1.1.1' ) ) );
	check( $e instanceof WP_Error && 'digitalisimo_hosting_read_only' === $e->code && serialize( opt() ) === $snap && false === strpos( serialize( log_entries() ), 'ROBADA' ), 'La cuenta de Namecheap no se escribe por MCP (ni queda la clave en la bitácora)' );
	check( 'error' === log_entries()[0]['result'] && 'digitalisimo_hosting_read_only' === log_entries()[0]['code'], 'El rechazo queda en la bitácora con su código' );
	$e = Executor::run( 'settings', array( 'action' => 'update', 'data' => array( 'enabled' => true ), 'post_id' => 5 ) );
	check( $e instanceof WP_Error && 'digitalisimo_hosting_mcp_input' === $e->code, 'Un parámetro fuera del esquema se rechaza' );
	check( Executor::run( 'settings', array( 'action' => 'borrar' ) ) instanceof WP_Error, 'Una acción fuera de la lista se rechaza' );

	// ── Multisitio ────────────────────────────────────────────────────────────────────────────────────────────
	$GLOBALS['multisite'] = true; $GLOBALS['site_options'] = array( 'digitalisimo_hosting_options' => array( 'domain_products' => '.com|999', 'api_key' => 'CLAVE-RED' ) );
	$net = serialize( $GLOBALS['site_options'] );
	$r = Executor::run( 'settings', array( 'action' => 'update', 'data' => array( 'simplify_checkout' => true ) ) );
	check( is_array( $r ) && 1 === opt()['simplify_checkout'] && serialize( $GLOBALS['site_options'] ) === $net, 'En una red: escribe el sitio, nunca la red' );
	$GLOBALS['multisite'] = false; $GLOBALS['site_options'] = array();

	// ── domain-product ────────────────────────────────────────────────────────────────────────────────────────
	$GLOBALS['caps'] = array( 'manage_woocommerce' );
	digi_product( 33, 'Dominio .net', 280 );
	$r = Executor::run( 'domain-product', array( 'action' => 'mark', 'product_id' => 33, 'tld' => '.net' ) );
	check( is_array( $r ) && '.net' === get_post_meta( 33, '_digitalisimo_tld', true ) && 33 === $r['domain_products']['resolved']['.net']['product_id'], 'mark: la línea .net sin ID queda resuelta con el producto marcado' );
	check( 33 === log_entries()[0]['product'] && 'mark' === log_entries()[0]['action'], 'La bitácora anota el producto marcado' );
	digi_product( 34, 'Transferencia', 250 );
	$r = Executor::run( 'domain-product', array( 'action' => 'mark', 'product_id' => 34, 'kind' => 'transfer' ) );
	check( is_array( $r ) && '*' === Digitalisimo_Hosting_Products::transfer_ext( 34 ), 'mark de transferencia sin extensión = cualquiera' );
	$r = Executor::run( 'domain-product', array( 'action' => 'list' ) );
	check( 2 === count( $r['marked'] ), 'list: los productos marcados' );
	Executor::run( 'domain-product', array( 'action' => 'unmark', 'product_id' => 34 ) );
	check( '' === Digitalisimo_Hosting_Products::transfer_ext( 34 ), 'unmark quita la marca' );
	check( Executor::run( 'domain-product', array( 'action' => 'mark', 'product_id' => 33 ) ) instanceof WP_Error, 'mark de registro sin tld se rechaza' );
	unset( $GLOBALS['caps'] );
	$page = Executor::run( 'settings', array( 'action' => 'log', 'per_page' => 3 ) );
	check( 3 === count( $page['data'] ) && $page['total'] >= 6, 'log: la bitácora paginada' );

	echo "Elements, Hosting en el MCP: {$GLOBALS['checks']} comprobaciones correctas.\n";
}
