<?php
namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'DIGITALISIMO_ELEMENTS_FILE', __DIR__ . '/../digitalisimo-elements/pro-elements.php' );
	define( 'DIGITALISIMO_ELEMENTS_VERSION', 'test' );
	$GLOBALS['registered_assets'] = array();
	function wp_style_is( $handle, $state ) { return isset( $GLOBALS['registered_assets']['style'][ $handle ] ); }
	function wp_script_is( $handle, $state ) { return isset( $GLOBALS['registered_assets']['script'][ $handle ] ); }
	function plugins_url( $path, $file ) { return 'https://example.test/wp-content/plugins/digitalisimo-elements/' . $path; }
	function wp_register_style( $handle, $url, $deps, $version ) { $GLOBALS['registered_assets']['style'][ $handle ] = $url; }
	function wp_register_script( $handle, $url, $deps, $version, $footer ) { $GLOBALS['registered_assets']['script'][ $handle ] = $url; }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-registry.php';
	\Digitalisimo\Elements\Widget_Registry::styles();
	\Digitalisimo\Elements\Widget_Registry::scripts();
	foreach ( $GLOBALS['registered_assets'] as $type => $assets ) {
		foreach ( $assets as $handle => $url ) {
			$path = substr( $url, strlen( 'https://example.test/wp-content/plugins/digitalisimo-elements/' ) );
			if ( false === $path || ! is_file( __DIR__ . '/../digitalisimo-elements/' . $path ) ) {
				throw new \RuntimeException( "El asset {$type} {$handle} apunta a un archivo inexistente: {$url}" );
			}
		}
	}
	echo "DIGITALÍSIMO Elements: todos los assets de widgets existen.\n";
}
