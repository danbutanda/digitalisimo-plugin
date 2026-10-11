<?php
/**
 * Plugin Name: Digitalisimo A/B fake ACF PRO
 * Description: Sólo para el A/B en Playground; no se publica. Element Pack exige `advanced-custom-fields-pro/acf.php`
 * para sus widgets ACF: esta ruta carga Secure Custom Fields (EXTRA_PLUGINS=secure-custom-fields), que trae repetidor y galería.
 */
defined( 'ABSPATH' ) || exit;
if ( is_file( WP_PLUGIN_DIR . '/secure-custom-fields/secure-custom-fields.php' ) ) {
	require_once WP_PLUGIN_DIR . '/secure-custom-fields/secure-custom-fields.php';
}
