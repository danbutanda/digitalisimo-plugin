<?php
namespace Digitalisimo\Elements\Mcp;

use Elementor\Modules\AtomicWidgets\PropsResolver\Props_Resolver_Context;
use Elementor\Modules\AtomicWidgets\PropsResolver\Transformer_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Transformador de `font-family` de los estilos atómicos: una lista de familias se escribe con `Font_Stack`; una sola
 * familia (y cualquier otro valor) sigue en el transformador del núcleo. Se carga sólo desde `Font_Stack::register()`,
 * con la clase base del núcleo ya disponible.
 */
final class Font_Family_Transformer extends Transformer_Base {
	/** @var Transformer_Base|null */
	private $inner;

	public function __construct( ?Transformer_Base $inner = null ) {
		$this->inner = $inner;
	}

	public function transform( $value, Props_Resolver_Context $context ) {
		$stack = Font_Stack::normalize( $value );
		if ( null !== $stack ) {
			return $stack;
		}
		if ( $this->inner ) {
			return $this->inner->transform( $value, $context );
		}
		return is_string( $value ) ? trim( $value ) : null;
	}
}
