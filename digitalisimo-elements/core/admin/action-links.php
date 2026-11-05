<?php
namespace ElementorPro\Core\Admin;

use ElementorPro\Core\Utils;
use ElementorPro\License\API;

class Action_Links {
	public static function get_links( array $links ): array {
		unset( $links['go_pro'] );

		return $links;
	}

	public static function get_pro_links( array $links ): array {
		// Sin promoción de pago del proveedor en la fila del plugin.
		return $links;
	}
}
