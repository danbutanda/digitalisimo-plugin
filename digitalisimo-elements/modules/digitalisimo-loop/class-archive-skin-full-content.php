<?php
namespace Digitalisimo\Elements\Loop;

defined( 'ABSPATH' ) || exit;

/**
 * Piel «Contenido completo» de Entradas del archivo: la de Entradas sin «Entradas por página», como
 * `modules/theme-builder/skins/posts-archive-skin-full-content.php` de PRO (del que deriva, GPL). Su contenedor lleva
 * `digi-posts--skin-archive_full_content`, como PRO.
 */
class Archive_Skin_Full_Content extends Skin_Full_Content {
	use Skin_Content_Base;
	use Archive_Skin_Base;

	public function get_id() {
		return 'archive_full_content';
	}

	protected function register_post_count_control() {}
}
