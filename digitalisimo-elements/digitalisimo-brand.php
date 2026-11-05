<?php
/** Página «Acerca de» y menú propios; la marca del constructor la aplica Digitalisimo_Elements_Identity. */
defined( 'ABSPATH' ) || exit;

final class Digitalisimo_Elements_Brand {
	const PAGE = 'digitalisimo-elements-about';
	const FILE = 'digitalisimo-elements/pro-elements.php';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 99 );
		add_action( 'network_admin_menu', array( __CLASS__, 'network_menu' ), 99 );
		add_action( 'admin_head', array( __CLASS__, 'menu_icon_css' ) );
		add_filter( 'plugin_action_links_' . self::FILE, array( __CLASS__, 'action_link' ) );
		add_filter( 'network_admin_plugin_action_links_' . self::FILE, array( __CLASS__, 'action_link' ) );
	}

	private static function has_parent( $slug ) {
		global $menu;
		foreach ( (array) $menu as $item ) if ( isset( $item[2] ) && $slug === $item[2] ) return true;
		return false;
	}

	public static function menu_icon_css() {
		echo '<style id="digitalisimo-elements-menu-icon">#adminmenu .toplevel_page_digitalisimo-elements-about .wp-menu-image img{display:block;width:20px;height:20px;max-width:20px;max-height:20px;object-fit:contain;margin:0 auto;padding:7px 0 0}</style>';
	}

	public static function menu() {
		if ( self::has_parent( 'digitalisimo' ) ) {
			add_submenu_page( 'digitalisimo', 'DIGITALÍSIMO Elements', 'Elements', 'manage_options', self::PAGE, array( __CLASS__, 'page' ) );
		} elseif ( self::builder_menu() ) {
			// Con el núcleo incluido, el menú del constructor ya se llama DIGITALÍSIMO Elements: la página va dentro.
			add_submenu_page( self::builder_menu(), 'DIGITALÍSIMO Elements', 'Acerca de', 'manage_options', self::PAGE, array( __CLASS__, 'page' ) );
		} else {
			add_menu_page( 'DIGITALÍSIMO Elements', 'DIGITALÍSIMO Elements', 'manage_options', self::PAGE, array( __CLASS__, 'page' ), plugins_url( 'assets/digitalisimo/icono.png', __FILE__ ), 56 );
		}
	}

	/** Menú del constructor cuando lleva la identidad propia (núcleo incluido), o '' si no. */
	private static function builder_menu() {
		if ( ! class_exists( 'Digitalisimo_Elements_Core' ) || ! Digitalisimo_Elements_Core::bundled() ) {
			return '';
		}
		foreach ( array( 'elementor-home', 'elementor' ) as $slug ) {
			if ( self::has_parent( $slug ) ) {
				return $slug;
			}
		}
		return '';
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
		$logo = plugins_url( 'assets/digitalisimo/logo-negro.webp', __FILE__ );
		// El diseño compartido de los plugins Digitalisimo (digitalisimo-ui): armazón, encabezado, tarjeta y enlaces.
		echo '<div class="wrap digitalisimo-elements-about"><h1>Elements</h1>';
		echo '<p><img src="' . esc_url( $logo ) . '" width="320" height="55" alt="DIGITALÍSIMO" style="display:block;max-width:320px;width:100%;height:auto"></p>';
		echo '<p>Constructor visual completo: editor, funciones avanzadas, kits, plantillas y widgets propios en un solo plugin.</p>';
		echo '<div class="card" style="max-width:900px"><h2>DIGITALÍSIMO Elements ' . esc_html( DIGITALISIMO_ELEMENTS_VERSION ) . '</h2>';
		echo '<p>Abre cualquier página con «Editar con DIGITALÍSIMO Elements». Funciona con el contenido, los kits, las plantillas y los complementos ya creados para el constructor, y no necesita ningún otro plugin.</p>';
		echo '<p>Se basa en software libre bajo GPLv3 de terceros, distribuido sin modificar; los créditos y las licencias originales están en <code>license.txt</code> y en la documentación del proyecto. Todo funciona en tu sitio: no se conecta con servicios ni cuentas externas.</p>';
		// Las actualizaciones llegan por la pantalla Plugins de WordPress: sin enlaces a sitios externos.
		echo '<p><a class="button button-primary" href="' . esc_url( is_network_admin() ? network_admin_url( 'plugins.php' ) : admin_url( 'plugins.php' ) ) . '">Ver actualizaciones</a></p></div></div>';
	}
}
