<?php
namespace Digitalisimo\Elements\Loop;

defined( 'ABSPATH' ) || exit;

/**
 * Piel «Contenido completo» de Entradas: la clásica con el rasgo `Skin_Content_Base`, como
 * `modules/posts/skins/skin-full-content.php` de PRO (del que deriva, GPL).
 */
class Skin_Full_Content extends Skin_Classic {
	use Skin_Content_Base;

	public function get_id() {
		return 'full_content';
	}
}
