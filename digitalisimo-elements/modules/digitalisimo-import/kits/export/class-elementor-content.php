<?php
namespace Digitalisimo\Elements\Import\Kits\Export;

use Digitalisimo\Elements\Import\Kits\Export_Context;
use Elementor\App\Modules\ImportExportCustomization\Runners\Export\Elementor_Content as Core_Elementor_Content;

defined( 'ABSPATH' ) || exit;

/**
 * Runner `elementor-content` de exportación de K (§8.3). Sustituye al del núcleo por su nombre.
 *
 * Además de lo que hace `Documents::finish()` (IDs de elemento, miniaturas completas, lista negra y anotación),
 * deja fuera las entradas protegidas con contraseña: el kit no lleva la contraseña y la copia importada sería
 * pública (A14). Se cuentan en `not_exported` con los borradores, los privados y los componentes v4 (CC-13).
 */
final class Elementor_Content extends Core_Elementor_Content {
	const QUERY_FILTER = 'elementor/import-export-customization/export/elementor-content/query-args/customization';
	const TYPES_FILTER = 'elementor/import-export-customization/elementor-content/post-types/customization';

	/** @var Export_Context|null */
	private $kit_context;

	/** @var string[] Tipos de entrada que exportó el núcleo. */
	private $kit_types = array();

	public function __construct( ?Export_Context $context = null ) {
		parent::__construct();
		$this->kit_context = $context;
	}

	public function export( array $data ) {
		$this->kit_context = Documents::context( $this->kit_context );
		$this->kit_types = array();
		$query           = array( $this, 'without_password' );
		$types           = array( $this, 'remember_types' );
		add_filter( self::QUERY_FILTER, $query, 20 );
		add_filter( self::TYPES_FILTER, $types, PHP_INT_MAX );
		try {
			$result = parent::export( $data );
		} finally {
			remove_filter( self::QUERY_FILTER, $query, 20 );
			remove_filter( self::TYPES_FILTER, $types, PHP_INT_MAX );
		}
		if ( $this->kit_context && $this->kit_types ) {
			Documents::count_unpublished( $this->kit_context, $this->kit_types, true );
			$protected = Documents::count( $this->kit_types, array( 'publish' ), true, array( 'has_password' => true ) );
			if ( $protected > 0 ) {
				$this->kit_context->not_exported( 'protected', $protected );
			}
		}
		Documents::count_components( $this->kit_context );
		return Documents::finish( is_array( $result ) ? $result : array(), $this->kit_context );
	}

	/** @internal Filtro de la consulta: sin entradas con contraseña. */
	public function without_password( $args ) {
		if ( is_array( $args ) ) {
			$args['has_password'] = false;
		}
		return $args;
	}

	/** @internal Anota los tipos de entrada que exporta el núcleo, ya filtrados. */
	public function remember_types( $types ) {
		$this->kit_types = is_array( $types ) ? array_values( array_filter( $types, 'is_string' ) ) : array();
		return $types;
	}
}
