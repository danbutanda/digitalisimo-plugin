<?php
namespace Digitalisimo\Tools\Elementor_Network_Templates;
defined( 'ABSPATH' ) || exit;
/** Reconoce referencias por claves y shortcodes, sin acoplarse a un widget específico. */
final class Dependency_Resolver {
	public static function ids_from_data( $data ) { $found = array(); self::walk( $data, '', $found ); return array_values( array_unique( array_map( 'absint', $found ) ) ); }
	private static function walk( $value, $key, &$found ) {
		if ( is_array( $value ) ) { foreach ( $value as $child_key => $child ) self::walk( $child, (string) $child_key, $found ); return; }
		if ( ! is_scalar( $value ) ) return; $value = (string) $value;
		if ( preg_match( '/(?:template|library|global)[_-]?(?:id|template)?$/i', $key ) && ctype_digit( $value ) ) $found[] = $value;
		if ( preg_match_all( '/\[(?:elementor-template|elementor_library)[^\]]*\bid\s*=\s*["\']?(\d+)/i', $value, $m ) ) $found = array_merge( $found, $m[1] );
		if ( preg_match_all( '/(?:template_id|template-id|data-template-id)\s*[=:]\s*["\']?(\d+)/i', $value, $m ) ) $found = array_merge( $found, $m[1] );
	}
	public static function dependencies( array $item ) { $json = get_post_meta( absint( $item['master_post_id'] ), '_elementor_data', true ); $data = json_decode( $json, true ); return Registry::by_master_ids( self::ids_from_data( $data ) ); }
	public static function remap_data( $data, array $id_map, $key = '' ) {
		if ( is_array( $data ) ) { foreach ( $data as $k => $v ) $data[ $k ] = self::remap_data( $v, $id_map, (string) $k ); return $data; }
		if ( ! is_scalar( $data ) ) return $data; $value = (string) $data;
		if ( preg_match( '/(?:template|library|global)[_-]?(?:id|template)?$/i', $key ) && isset( $id_map[ absint( $value ) ] ) ) return is_int( $data ) ? $id_map[ absint( $value ) ] : (string) $id_map[ absint( $value ) ];
		return preg_replace_callback( '/(\[(?:elementor-template|elementor_library)[^\]]*\bid\s*=\s*["\']?)(\d+)/i', function( $m ) use ( $id_map ) { return $m[1] . ( $id_map[ absint( $m[2] ) ] ?? $m[2] ); }, $value );
	}
}
