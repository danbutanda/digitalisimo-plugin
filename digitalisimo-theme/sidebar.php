<?php defined( 'ABSPATH' ) || exit;
/*
 * Barra lateral vacía a propósito: sin este archivo, get_sidebar() (WooCommerce lo llama en la tienda y en el
 * producto) cargaría la plantilla de compatibilidad de WordPress, que avisa de un archivo obsoleto e imprime
 * listas de widgets que el sitio no tenía.
 */
