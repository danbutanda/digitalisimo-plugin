<?php
/** Identidad visual de este derivado; no modifica las marcas del editor Elementor. */
defined( 'ABSPATH' ) || exit;

final class Digitalisimo_Elements_Brand {
	const PAGE = 'digitalisimo-elements-about';
	const FILE = 'digitalisimo-elements/pro-elements.php';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 99 );
		add_action( 'network_admin_menu', array( __CLASS__, 'network_menu' ), 99 );
		add_filter( 'plugin_action_links_' . self::FILE, array( __CLASS__, 'action_link' ) );
		add_filter( 'network_admin_plugin_action_links_' . self::FILE, array( __CLASS__, 'action_link' ) );
	}

	private static function has_parent( $slug ) {
		global $menu;
		foreach ( (array) $menu as $item ) if ( isset( $item[2] ) && $slug === $item[2] ) return true;
		return false;
	}

	public static function menu() {
		if ( self::has_parent( 'digitalisimo' ) ) {
			add_submenu_page( 'digitalisimo', 'DIGITALÍSIMO Elements', 'Elements', 'manage_options', self::PAGE, array( __CLASS__, 'page' ) );
		} else {
			add_menu_page( 'DIGITALÍSIMO Elements', 'DIGITALÍSIMO Elements', 'manage_options', self::PAGE, array( __CLASS__, 'page' ), plugins_url( 'assets/digitalisimo/icono.png', __FILE__ ), 56 );
		}
	}

	public static function network_menu() {
		if ( self::has_parent( 'digitalisimo-network' ) ) {
			add_submenu_page( 'digitalisimo-network', 'DIGITALÍSIMO Elements', 'Elements', 'manage_network_plugins', self::PAGE, array( __CLASS__, 'page' ) );
		} else {
			add_menu_page( 'DIGITALÍSIMO Elements', 'DIGITALÍSIMO Elements', 'manage_network_plugins', self::PAGE, array( __CLASS__, 'page' ), plugins_url( 'assets/digitalisimo/icono.png', __FILE__ ), 56 );
		}
	}

	public static function action_link( $links ) {
		$base = is_network_admin() ? network_admin_url( 'admin.php' ) : admin_url( 'admin.php' );
		$links[] = '<a href="' . esc_url( add_query_arg( 'page', self::PAGE, $base ) ) . '">Acerca de DIGITALÍSIMO Elements</a>';
		return $links;
	}

	public static function page() {
		$capability = is_network_admin() ? 'manage_network_plugins' : 'manage_options';
		if ( ! current_user_can( $capability ) ) wp_die( esc_html__( 'No autorizado.' ) );
		$logo = plugins_url( 'assets/digitalisimo/logo-blanco.webp', __FILE__ );
		$icon = plugins_url( 'assets/digitalisimo/icono.png', __FILE__ );
		echo '<div class="wrap digitalisimo-elements-about"><style>.digitalisimo-elements-about .digi-banner{background:#171b2e;border-radius:16px;color:#fff;padding:26px 30px;max-width:900px}.digitalisimo-elements-about .digi-banner img{display:block;max-width:320px;width:100%;height:auto}.digitalisimo-elements-about .digi-card{max-width:900px;background:#fff;border:1px solid #dce1ea;border-radius:12px;padding:24px 30px;margin-top:20px}.digitalisimo-elements-about .digi-icon{width:48px;height:48px;vertical-align:middle;margin-right:10px}.digitalisimo-elements-about p{font-size:14px;line-height:1.6}</style>';
		echo '<div class="digi-banner"><img src="' . esc_url( $logo ) . '" width="320" height="55" alt="DIGITALÍSIMO"><h1 style="color:#fff">Elements</h1><p>Funciones avanzadas para Elementor gratuito.</p></div>';
		echo '<div class="digi-card"><h2><img class="digi-icon" src="' . esc_url( $icon ) . '" width="48" height="48" alt="">DIGITALÍSIMO Elements ' . esc_html( DIGITALISIMO_ELEMENTS_VERSION ) . '</h2>';
		echo '<p>Esta versión deriva de PRO Elements 4.3.0, a su vez derivado del código GPLv3 de Elementor Pro. Conserva los créditos y la licencia originales. DIGITALÍSIMO no está afiliado con Elementor Ltd. ni con el equipo de PRO Elements.</p>';
		echo '<p>Requiere Elementor gratuito 4.0 o posterior. No se carga junto con Elementor Pro ni con otra copia de PRO Elements. Las funciones conectadas a servicios externos pueden requerir cuentas o permisos del proveedor.</p>';
		echo '<p><a href="' . esc_url( 'https://github.com/danbutanda/digitalisimo-plugin' ) . '" target="_blank" rel="noopener noreferrer">Código y actualizaciones</a> · <a href="' . esc_url( 'https://github.com/proelements/proelements/releases/tag/v4.3.0' ) . '" target="_blank" rel="noopener noreferrer">Proyecto de origen</a></p></div></div>';
	}
}
