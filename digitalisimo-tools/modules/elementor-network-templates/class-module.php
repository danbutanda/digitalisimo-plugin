<?php
namespace Digitalisimo\Tools\Elementor_Network_Templates;
defined( 'ABSPATH' ) || exit;
final class Module {
	public static function init() { if ( ! is_multisite() ) return; Sync::init(); Lock::init(); Network_Admin::init(); }
}
