<?php
namespace Digitalisimo\Elements\Woo\Filters;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\Digitalisimo\Elements\Filters\Types', false ) ) {
	require_once dirname( __DIR__, 2 ) . '/digitalisimo-filters/class-types.php';
}

/**
 * Tipos de filtro de F4-Woo (V.8.1 y V.15.2) sobre el registro neutro `Digitalisimo\Elements\Filters\Types` (F7b,
 * V.5 y C1-13): el registro, sus tres filtros del derivado `modules/loop-filter/module.php` y su comportamiento son los
 * de siempre; aquí sólo quedan los nombres y prefijos de precio y atributo.
 */
final class Types extends \Digitalisimo\Elements\Filters\Types {
	const PRICE            = 'digitalisimo_price';
	const PRICE_PREFIX     = 'digi_price_';
	const ATTRIBUTE        = 'digitalisimo_attribute';
	const ATTRIBUTE_PREFIX = 'digiattr_';
}
