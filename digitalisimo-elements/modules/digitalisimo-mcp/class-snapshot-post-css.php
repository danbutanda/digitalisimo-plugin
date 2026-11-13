<?php
namespace Digitalisimo\Elements\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * El CSS de página (`post-<id>.css`: ajustes de la página, estilos clásicos, CSS propio) de la instantánea de un enlace
 * firmado, como el núcleo lo genera (`Post_CSS`, con sus ganchos `elementor/css-file/post/parse` y `…/enqueue`), pero
 * en memoria y en línea, igual que su `Post_Preview` del editor: la meta va en la propia instancia (`Post_Local_Cache`)
 * y nunca se escribe el archivo (`use_external_file()` falso). Los datos son los que el manejador del núcleo sirve
 * para la página (los de la revisión firmada). Se carga sólo cuando existe `Post_Local_Cache` (`Preview_Link`).
 */
final class Snapshot_Post_Css extends \Elementor\Core\Files\CSS\Post_Local_Cache {
	const HANDLE_PREFIX = 'digitalisimo-mcp-preview-post-';

	protected function get_post_id_for_data() {
		return $this->get_post_id();
	}

	/** Nunca un archivo: el CSS va en la meta en memoria (estado `inline`). */
	protected function use_external_file() {
		return false;
	}

	protected function get_file_handle_id() {
		return self::HANDLE_PREFIX . $this->get_post_id();
	}

	/** La hoja propia que `Preview_Link` registra donde el núcleo encola `post-<id>.css`. */
	protected function get_inline_dependency() {
		return $this->get_file_handle_id();
	}
}
