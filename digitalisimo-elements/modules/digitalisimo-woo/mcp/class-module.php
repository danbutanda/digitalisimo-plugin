<?php
namespace Digitalisimo\Elements\Woo\Mcp;

use Digitalisimo\Elements\Woo\Module as Woo_Module;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce en el servidor MCP de DIGITALÍSIMO Elements (`POST /wp-json/elementor/mcp`, apagado por defecto con la
 * opción del núcleo `elementor_mcp_enabled`): siete herramientas `digitalisimo-woo/<recurso>` sobre la API REST v3 de
 * WooCommerce. Submódulo de `Woo\Module` (lista `BOOT`): sólo arranca con WooCommerce ≥ `MIN_WC`.
 *
 * Registro, sin editar nada del núcleo incluido (como `modules/mcp/module.php`):
 * - con el registro de abilities del núcleo (`Ability_Registry`, también con el plugin Elementor original activo), cada
 *   herramienta se añade a él en `elementor/init`; el núcleo las vuelca al servidor en `init` 5 y las registra en
 *   `wp_abilities_api_init` sólo si el MCP del sitio está activo;
 * - sin ese registro pero con el contrato de abilities, `wp_register_ability()` en `wp_abilities_api_init` (si el MCP
 *   está activo) y el filtro `elementor/mcp/server/tools`;
 * - sin contrato de abilities o sin la Abilities API de WordPress (6.9), nada.
 * Con el modo `off` («WooCommerce en el MCP») no se registra ninguna herramienta ni la categoría. Las abilities propias
 * de WooCommerce (`woocommerce/…`) y su servidor experimental no se tocan ni se exponen aquí.
 *
 * Cada sitio de una red decide en sus propias peticiones (su modo, su MCP, su tienda): nada tiene alcance de red salvo
 * el modo heredado.
 */
final class Module {
	const CORE_ABILITY = 'Elementor\\Modules\\Mcp\\Abilities\\Abstract_Ability';
	const CORE_MODULE  = 'Elementor\\Modules\\Mcp\\Module';
	const CORE_SERVER  = 'Elementor\\MCP\\Composer\\Admin\\McpSettingsController';

	/** @var bool */
	private static $initialized = false;

	/** @var Ability[] */
	private static $abilities = array();

	/** Punto de entrada desde `Woo\Module::load()` (con WooCommerce compatible). Idempotente. */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		if ( ! Woo_Module::woocommerce_ok() || ! function_exists( 'add_action' ) ) {
			return;
		}
		add_action( 'elementor/init', array( __CLASS__, 'boot' ), 20 );
	}

	/**
	 * En `elementor/init`: el registro del núcleo ya existe y aún no se volcó al servidor (`init` 5). Primero lo que no
	 * cuesta nada (la Abilities API, el contrato del núcleo y su opción `elementor_mcp_enabled`, que el núcleo ya leyó en
	 * esta petición); sólo con el MCP del sitio activo se lee el modo, una opción sin carga automática (y, en una red,
	 * la de red): con el MCP apagado, ninguna petición la consulta.
	 */
	public static function boot(): void {
		if ( ! function_exists( 'wp_register_ability' ) || ! class_exists( self::CORE_ABILITY ) || ! self::mcp_enabled() ) {
			return;
		}
		if ( 'off' === Settings::mode() ) {
			return;
		}
		self::$abilities = array();
		foreach ( Tools::tools() as $tool ) {
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

	/** Categoría propia, sólo si el MCP del sitio está activo. */
	public static function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) || ! self::mcp_enabled() ) {
			return;
		}
		wp_register_ability_category(
			Tools::CATEGORY,
			array(
				'label'       => 'Tienda WooCommerce',
				'description' => 'Herramientas de DIGITALÍSIMO Elements para administrar la tienda WooCommerce del sitio.',
			)
		);
	}

	/** Respaldo sin registro del núcleo: registra cada herramienta si el MCP del sitio está activo. */
	public static function register_abilities(): void {
		if ( ! self::mcp_enabled() ) {
			return;
		}
		foreach ( self::$abilities as $ability ) {
			$ability->register();
		}
	}

	/** Respaldo sin registro del núcleo: los IDs de las herramientas en la lista del servidor. */
	public static function tool_slugs( $tools ): array {
		$tools = is_array( $tools ) ? $tools : array();
		foreach ( Tools::tools() as $tool ) {
			$tools[] = Tools::id( $tool );
		}
		return $tools;
	}

	/** ¿Está activo el MCP del sitio? (la opción del núcleo, leída por su propia clase). */
	public static function mcp_enabled(): bool {
		$class = self::CORE_SERVER;
		return class_exists( $class ) && method_exists( $class, 'is_enabled' ) && (bool) call_user_func( array( $class, 'is_enabled' ) );
	}

	/** Registro de abilities del módulo MCP del núcleo, o null (como `ElementorPro\Modules\Mcp\Module`). */
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

	/** Para las pruebas: vuelve al estado anterior a `init()`. */
	public static function reset(): void {
		self::$initialized = false;
		self::$abilities   = array();
	}

	/** Para las pruebas: las herramientas creadas en `boot()`. */
	public static function abilities(): array {
		return self::$abilities;
	}
}
