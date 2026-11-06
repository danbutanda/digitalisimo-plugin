<?php
namespace Digitalisimo\Elements\Legacy\Prime_Slider;

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__ ) . '/class-demo-media.php';

/**
 * Imágenes de demostración de Prime Slider (§3.E.5, V.10.3, decisión 4 del propietario): la clase común de Element Pack
 * y Prime Slider (`Legacy\Demo_Media`, F8-D17), con el nombre que usan el convertidor, `Runner::REVERT_EXTRA` y sus
 * pruebas. Comparte con ella sus recuentos, el registro `demo` y la reversión.
 */
final class Demo_Media extends \Digitalisimo\Elements\Legacy\Demo_Media {
}
