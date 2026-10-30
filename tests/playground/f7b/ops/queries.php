<?php
/**
 * Operaciones de B3 y B4 (f7b.sh): borrado de un documento o una consulta como el administrador, para comprobar que
 * `pre_trash_post` lo detiene cuando tiene usos (V.6). Con `dry=1` sólo devuelve su estado.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'trash' => static function ( $arg, $map ) {
		$id = (int) $arg( 'id' );
		if ( $id <= 0 ) {
			return array( 'error' => 'sin id' );
		}
		$result = null;
		if ( '1' !== $arg( 'dry' ) ) {
			$result = wp_trash_post( $id );
			$result = is_object( $result ) ? 'trashed' : $result;
		}
		clean_post_cache( $id );
		return array( 'id' => $id, 'result' => $result, 'status' => (string) get_post_status( $id ) );
	},
);
