<?php
/**
 * Menú Digitalisimo → Plugins con Tools: una sola entrada «Digitalisimo», una sola entrada «Plugins», y la
 * pantalla pinta el catálogo. Se ejecuta tres veces: con Tools solo, con SEO activo (entonces manda el de SEO) y con
 * Backups en lugar de SEO (un módulo que crea el menú padre sin el núcleo `Digitalisimo_Core`).
 */
define( 'WP_ADMIN', true );
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$scenario = file_exists( '/wordpress/wp-content/probe/scenario' ) ? trim( file_get_contents( '/wordpress/wp-content/probe/scenario' ) ) : 'tools';
$notices  = array();
set_error_handler( static function ( $no, $str, $file, $line ) use ( &$notices ) { $notices[] = basename( $file ) . ':' . $line . ' ' . $str; return true; } );
wp_set_current_user( 1 );
$GLOBALS['menu'] = $GLOBALS['submenu'] = $GLOBALS['admin_page_hooks'] = $GLOBALS['_registered_pages'] = $GLOBALS['_parent_pages'] = array();
do_action( 'admin_menu', '' );
$problems = array();
$top = array();
foreach ( (array) $GLOBALS['menu'] as $item ) { if ( 'digitalisimo' === ( $item[2] ?? '' ) ) $top[] = $item[0]; }
if ( 1 !== count( $top ) ) $problems[] = 'menú Digitalisimo: ' . count( $top ) . ' entradas';
$subs = array_column( (array) ( $GLOBALS['submenu']['digitalisimo'] ?? array() ), 2 );
if ( 1 !== count( array_keys( $subs, 'digitalisimo-plugins', true ) ) ) $problems[] = 'submenú Plugins: ' . count( array_keys( $subs, 'digitalisimo-plugins', true ) ) . ' entradas';
if ( ! in_array( 'digitalisimo-tools-elementor-templates', $subs, true ) ) $problems[] = 'falta el submenú Herramientas';
if ( 'backups' === $scenario && ! preg_grep( '/backups/', $subs ) ) $problems[] = 'falta el submenú Backups';
$hook = get_plugin_page_hookname( 'digitalisimo-plugins', 'digitalisimo' );
$callbacks = array();
foreach ( (array) ( $GLOBALS['wp_filter'][ $hook ]->callbacks ?? array() ) as $group ) { foreach ( $group as $cb ) { $f = $cb['function']; $callbacks[] = is_array( $f ) ? ( is_object( $f[0] ) ? get_class( $f[0] ) : $f[0] ) . '::' . $f[1] : (string) $f; } }
$expected = 'seo' === $scenario ? 'Digitalisimo_Plugin_Catalog::page' : 'Digitalisimo\\Tools\\Plugin_Catalog::page';
if ( array( $expected ) !== $callbacks ) $problems[] = 'la página Plugins la pinta ' . implode( ',', $callbacks ) . ' y no ' . $expected;
ob_start();
if ( 'seo' === $scenario ) Digitalisimo_Plugin_Catalog::page(); else \Digitalisimo\Tools\Plugin_Catalog::page();
$html = (string) ob_get_clean();
if ( substr_count( $html, '<tr>' ) < 6 ) $problems[] = 'la tabla del catálogo tiene ' . substr_count( $html, '<tr>' ) . ' filas';
foreach ( array( 'Digitalisimo SEO', 'DIGITALÍSIMO Tools', 'DIGITALÍSIMO Elements' ) as $name ) { if ( false === strpos( $html, $name ) ) $problems[] = 'falta ' . $name; }
if ( $notices ) $problems[] = 'avisos PHP: ' . implode( ' | ', array_slice( $notices, 0, 5 ) );
$result = file_exists( '/wordpress/wp-content/probe/result.json' ) ? (array) json_decode( file_get_contents( '/wordpress/wp-content/probe/result.json' ), true ) : array();
$result[ $scenario ] = $problems;
file_put_contents( '/wordpress/wp-content/probe/result.json', wp_json_encode( $result ) );
