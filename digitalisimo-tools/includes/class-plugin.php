<?php
namespace Digitalisimo\Tools;
defined( 'ABSPATH' ) || exit;

final class Plugin {
	public static function activate( $network_wide = false ) {
		if ( is_multisite() ) add_site_option( 'digitalisimo_tools_settings', array( 'master_blog_id' => get_main_site_id(), 'log_limit' => 200 ) );
	}
	public static function boot() { Plugin_Catalog::init(); Media_WebP::init(); Elementor_Network_Templates\Module::init(); }
}
