<?php
namespace Digitalisimo\Elements\Sales\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * El kit de ventas en el servidor MCP de DIGITALÍSIMO Elements (`POST /wp-json/elementor/mcp`, apagado por defecto con
 * la opción del núcleo `elementor_mcp_enabled`): `digitalisimo-sales/list-templates`, `create-page` y `guide`, en su
 * categoría `digitalisimo-sales`. Registro sin editar nada del núcleo incluido, como las herramientas de tienda:
 * - con el registro de abilities del núcleo (`Ability_Registry`), cada herramienta se añade a él en `elementor/init`;
 *   el núcleo las vuelca al servidor en `init` 5 y las registra en `wp_abilities_api_init` sólo con el MCP activo;
 * - sin ese registro pero con el contrato de abilities, `wp_register_ability()` en `wp_abilities_api_init` (si el MCP
 *   está activo) y el filtro `elementor/mcp/server/tools`;
 * - sin contrato de abilities o sin la Abilities API de WordPress (6.9), nada.
 * Con el MCP del sitio apagado no se lee nada del kit.
 */
final class Module {
	const CORE_ABILITY = 'Elementor\\Modules\\Mcp\\Abilities\\Abstract_Ability';
	const CORE_MODULE  = 'Elementor\\Modules\\Mcp\\Module';
	const CORE_SERVER  = 'Elementor\\MCP\\Composer\\Admin\\McpSettingsController';

	/** @var bool */
	private static $initialized = false;

	/** @var Ability[] */
	private static $abilities = array();

	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		add_action( 'elementor/init', array( __CLASS__, 'boot' ), 20 );
	}

	public static function boot(): void {
		if ( ! function_exists( 'wp_register_ability' ) || ! class_exists( self::CORE_ABILITY ) || ! self::mcp_enabled() ) {
			return;
		}
		require_once __DIR__ . '/class-ability.php';
		self::$abilities = array();
		foreach ( Tools::TOOLS as $tool ) {
			self::$abilities[] = new Ability( $tool );
		}
		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_category' ) );
		$registry = self::core_registry();
		if ( $registry ) {
			foreach ( self::$abilities as $ability ) {
				$registry->add( $ability );
			}
			return;
		}
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_abilities' ) );
		add_filter( 'elementor/mcp/server/tools', array( __CLASS__, 'tool_slugs' ) );
	}

	public static function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) || ! self::mcp_enabled() ) {
			return;
		}
		wp_register_ability_category(
			Tools::CATEGORY,
			array(
				'label'       => 'Páginas de venta',
				'description' => 'Herramientas de DIGITALÍSIMO Elements para crear páginas de venta en borrador con el kit de ventas y su guía.',
			)
		);
	}

	public static function register_abilities(): void {
		if ( ! self::mcp_enabled() ) {
			return;
		}
		foreach ( self::$abilities as $ability ) {
			$ability->register();
		}
	}

	public static function tool_slugs( $tools ): array {
		$tools = is_array( $tools ) ? $tools : array();
		foreach ( Tools::TOOLS as $tool ) {
			$tools[] = Tools::id( $tool );
		}
		return $tools;
	}

	public static function mcp_enabled(): bool {
		$class = self::CORE_SERVER;
		return class_exists( $class ) && method_exists( $class, 'is_enabled' ) && (bool) call_user_func( array( $class, 'is_enabled' ) );
	}

	private static function core_registry() {
		$module_class = self::CORE_MODULE;
		if ( ! class_exists( 'Elementor\\Plugin' ) || ! class_exists( $module_class ) || ! method_exists( $module_class, 'registry' ) ) {
			return null;
		}
		$plugin = \Elementor\Plugin::$instance;
		if ( ! $plugin || ! isset( $plugin->modules_manager ) || ! is_object( $plugin->modules_manager ) ) {
			return null;
		}
		$module = $plugin->modules_manager->get_modules( 'mcp' );
		return $module instanceof $module_class ? $module->registry() : null;
	}

	/** Para las pruebas. */
	public static function reset(): void {
		self::$initialized = false;
		self::$abilities   = array();
	}

	/** Para las pruebas. */
	public static function abilities(): array {
		return self::$abilities;
	}
}
