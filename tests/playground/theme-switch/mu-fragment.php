<?php
/**
 * Plugin Name: DIGI A/B · fragmento de Hello (sólo pruebas)
 * Description: Un fragmento típico de un sitio con Hello (FR-9): oculta el título de una sola página con el filtro de
 *              Hello. Con el tema DIGITALÍSIMO en paridad debe seguir aplicándose. No se publica.
 */
defined( 'ABSPATH' ) || exit;
add_filter(
	'hello_elementor_page_title',
	static function ( $show ) {
		return is_page( 'digi-ts-sin-titulo' ) ? false : $show;
	}
);
