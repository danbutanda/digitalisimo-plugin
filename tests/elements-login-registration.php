<?php
namespace ElementorPro\Base { class Base_Widget {} }
namespace {
	define( 'ABSPATH', __DIR__ );
	function is_multisite() { return $GLOBALS['multisite']; }
	function get_site_option( $key ) { return $GLOBALS['network_registration']; }
	function get_option( $key ) { return $GLOBALS['site_registration']; }
	require __DIR__ . '/../digitalisimo-elements/modules/forms/widgets/login.php';
	$method = new \ReflectionMethod( \ElementorPro\Modules\Forms\Widgets\Login::class, 'registration_enabled' );
	foreach ( array(
		array( false, 'none', false, false ),
		array( false, 'none', true, true ),
		array( true, 'none', true, false ),
		array( true, 'blog', true, false ),
		array( true, 'user', false, true ),
		array( true, 'all', false, true ),
	) as $case ) {
		list( $GLOBALS['multisite'], $GLOBALS['network_registration'], $GLOBALS['site_registration'], $expected ) = $case;
		if ( $expected !== $method->invoke( null ) ) {
			throw new \RuntimeException( 'El enlace de alta debe obedecer la política efectiva de WordPress individual o de red.' );
		}
	}
	echo "DIGITALÍSIMO Elements: política de registro en login validada.\n";
}
