<?php
namespace Digitalisimo\Elements\Hosting\Mcp;

use Elementor\Modules\Mcp\Abilities\Abstract_Ability as Core_Ability;
use Elementor\Modules\Mcp\Abilities\Ability_Definition as Core_Definition;

defined( 'ABSPATH' ) || exit;

/**
 * Una herramienta de DIGITALÍSIMO Hosting con el contrato de abilities del núcleo (`Abstract_Ability`), para su registro
 * del MCP. Mismo patrón que las de tienda y las del kit de ventas: ID sin el prefijo del núcleo
 * (`digitalisimo-hosting/settings` → `digitalisimo-hosting-settings`), `is_exposed_via_proxy()` false, permiso con un
 * `true` estricto y `register()` propio con la definición de `Tools::definition()` (fuera de la REST de abilities).
 */
final class Ability extends Core_Ability {
	/** @var string */
	private $tool;

	public function __construct( string $tool ) {
		$this->tool = $tool;
	}

	public function tool(): string {
		return $this->tool;
	}

	protected function get_ability_id(): string {
		return Tools::id( $this->tool );
	}

	protected function get_definition(): Core_Definition {
		$definition = Tools::definition( $this->tool );
		$tool       = $this->tool;
		return new Core_Definition(
			$definition['label'],
			$definition['description'],
			$definition['category'],
			$definition['output_schema'],
			$definition['meta'],
			static function ( $input = null ) use ( $tool ) {
				return Executor::permission( $tool, $input );
			},
			$definition['input_schema']
		);
	}

	public function execute( $input = array() ) {
		return Executor::run( $this->tool, $input );
	}

	public function is_exposed_via_proxy(): bool {
		return false;
	}

	public function check_permission(): bool {
		return true === Executor::permission( $this->tool );
	}

	public function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		$definition = Tools::definition( $this->tool );
		$tool       = $this->tool;
		wp_register_ability(
			$this->get_id(),
			array(
				'label'               => $definition['label'],
				'description'         => $definition['description'],
				'category'            => $definition['category'],
				'input_schema'        => $definition['input_schema'],
				'output_schema'       => $definition['output_schema'],
				'meta'                => $definition['meta'],
				'permission_callback' => static function ( $input = null ) use ( $tool ) {
					return Executor::permission( $tool, $input );
				},
				'execute_callback'    => array( $this, 'execute_guarded' ),
			)
		);
	}
}
