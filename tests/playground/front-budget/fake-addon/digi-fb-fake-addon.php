<?php
/**
 * Plugin Name: Complemento de prueba de la medición del front
 * Description: Sólo para tests/playground/front-budget.sh. Registra dos widgets cuya clase está fuera de los espacios de nombres propios (uno declarado libre de los paquetes de PRO con el filtro de la puerta) y, en la página `digi-fb-third-party-script`, encola un script que depende de `elementor-pro-frontend`.
 * Version: 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/** Widgets del complemento: nombre → clase (la prueba de CI de los casos clasifica con este mapa). */
const DIGI_FB_FAKE_WIDGETS = array(
	'digi-fb-fake-card' => 'Digi_Fb_Fake_Addon\\Card',
	'digi-fb-fake-free' => 'Digi_Fb_Fake_Addon\\Free_Card',
);

add_action(
	'elementor/widgets/register',
	static function ( $manager ) {
		require_once __DIR__ . '/widgets.php';
		foreach ( DIGI_FB_FAKE_WIDGETS as $class ) {
			$manager->register( new $class() );
		}
	}
);

// Un complemento que sabe que su widget no usa los paquetes de PRO lo declara libre (D2).
add_filter(
	'digitalisimo_elements_pro_frontend_third_party',
	static function ( $needs, $class = '', $type = '' ) {
		return 'Digi_Fb_Fake_Addon\\Free_Card' === ltrim( (string) $class, '\\' ) ? false : $needs;
	},
	10,
	3
);

// Script ajeno que depende del paquete de PRO, encolado al pintar el pie (antes de que la puerta decida).
add_action(
	'wp_footer',
	static function () {
		if ( is_page( 'digi-fb-third-party-script' ) ) {
			wp_enqueue_script( 'digi-fb-fake-addon-pro', plugins_url( 'fake-addon.js', __FILE__ ), array( 'elementor-pro-frontend' ), '1.0.0', true );
		}
	},
	15
);
