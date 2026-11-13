<?php
namespace Digitalisimo\Elements\Sales\Mcp;

use Elementor\Modules\Mcp\Abilities\Abstract_Ability as Core_Ability;
use Elementor\Modules\Mcp\Abilities\Ability_Definition as Core_Definition;

defined( 'ABSPATH' ) || exit;

/**
 * Una herramienta del kit de ventas con el contrato de abilities del núcleo (`Abstract_Ability`), para su registro del
 * MCP. Sólo se carga cuando esa clase existe (`Module::boot()` lo comprueba antes de nombrarla). Mismo patrón que las
 * herramientas de tienda (`modules/digitalisimo-woo/mcp/class-ability.php`):
 * - ID sin el prefijo del núcleo (`digitalisimo-sales/create-page`): el adaptador lo publica como
 *   `digitalisimo-sales-create-page`; `is_exposed_via_proxy()` es false (el proxy del editor supone el prefijo del
 *   núcleo);
 * - `check_permission()` propio, con un `true` estricto (el del núcleo convierte un `WP_Error` en true);
 * - `register()` propio: la definición de `Tools::definition()` tal cual (fuera de la REST de abilities y del servidor
 *   MCP genérico) y `execute_guarded()` del núcleo como ejecución (la entrada nunca lleva `post_id`).
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
