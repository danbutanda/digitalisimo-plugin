<?php
namespace Digitalisimo\Elements\Woo\Kit;

use Digitalisimo\Elements\Import\Kits\Context;
use Elementor\App\Modules\ImportExportCustomization\Runners\Export\Export_Runner_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Runner de exportación `digitalisimo-woo-media` (frente H de F4-Woo).
 *
 * El WXR del núcleo sólo lleva la imagen destacada de cada entrada exportada; las variaciones no se exportan con él,
 * así que sus imágenes no entraban en el kit. Este runner corre después de los de K y antes del final (que exporta
 * las partes, `media` antes que `woo-variations`) y anota esas imágenes con `Export_Context::add_exported(
 * 'attachment', … )`: la parte `media` de K las incrusta con las mismas reglas que los demás adjuntos (sólo del sitio
 * actual, dentro de los topes) y al importar publica su mapa por ID, que usa `woo-variations`.
 *
 * No escribe archivos ni manifiesto propios.
 */
class Media_Runner extends Export_Runner_Base {
	public static function get_name(): string {
		return 'digitalisimo-woo-media';
	}

	public function should_export( array $data ) {
		$context = Context::export_context();
		return null !== $context && $context->includes( 'content' ) && Variations_Part::available();
	}

	public function export( array $data ) {
		$context = Context::export_context();
		if ( null !== $context ) {
			$ids = Variations_Part::image_ids( $context );
			if ( $ids ) {
				$context->add_exported( 'attachment', $ids );
			}
		}
		return array(
			'files'    => array(),
			'manifest' => array(),
		);
	}
}
