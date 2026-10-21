<?php
namespace Digitalisimo\Elements\Import\Kits\Export;

use Digitalisimo\Elements\Import\Kits\Export_Context;
use Elementor\App\Modules\ImportExportCustomization\Runners\Export\Templates as Core_Templates;

defined( 'ABSPATH' ) || exit;

/**
 * Runner `templates` de exportación de K (§8.3). Sustituye al del núcleo por su nombre y en su misma posición.
 *
 * Exporta lo mismo que el núcleo, incluido lo que añaden el Theme Builder y los widgets globales por el filtro
 * `…/export/templates_data`, y después repone los IDs de elemento (D20), pone las miniaturas en tamaño completo
 * (CC-23), quita del `metadata` la lista negra (§8.2) y anota lo exportado en el contexto para las partes.
 */
final class Templates extends Core_Templates {
	/** @var Export_Context|null */
	private $kit_context;

	public function __construct( ?Export_Context $context = null ) {
		$this->kit_context = $context;
	}

	public function export( array $data ) {
		$this->kit_context = Documents::context( $this->kit_context );
		$result = parent::export( $data );
		Documents::count_unpublished( $this->kit_context, array( 'elementor_library' ), null );
		return Documents::finish( is_array( $result ) ? $result : array(), $this->kit_context );
	}
}
