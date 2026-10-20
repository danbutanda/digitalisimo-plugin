<?php
namespace Digitalisimo\Tools;

defined( 'ABSPATH' ) || exit;

/**
 * Sección «Tema» del catálogo Digitalisimo → Plugins (el de SEO o el de Tools, que disparan
 * `digitalisimo_plugin_catalog_after`): una fila por copia del tema DIGITALÍSIMO, con su estado y las acciones que el
 * contexto permite. Instala y actualiza; nunca activa ni habilita el tema (eso es siempre una decisión del
 * administrador en Temas). Con Hello activo añade el inventario «Antes de cambiar de tema», de sólo lectura: la regla
 * del modo de cabecera y pie y el inventario del hijo son los mismos que el tema registra al activarse
 * (`digitalisimo-theme/inc/switch.php`, T6 y T10), duplicados a propósito porque ningún módulo puede exigir otro.
 */
final class Theme_Catalog {
	const INSTALL = 'digitalisimo_tools_theme_install';
	const UPDATE  = 'digitalisimo_tools_theme_update';
	const NOTICE  = 'digitalisimo-theme-notice';
	/** Los seis ajustes de Hello 3.5.1 (`hello_elementor_settings_<clave>`); `'true'` = lo tenía apagado. */
	const HELLO_SETTINGS = array( 'description_meta_tag', 'skip_link', 'header_footer', 'page_title', 'hello_style', 'hello_theme' );
	/** Selectores de la cabecera y el pie de Hello que un hijo suele reescribir. */
	const CHILD_SELECTORS = array( '.site-header', '.site-branding', '.site-navigation', '.header-inner', '.site-footer' );

	public static function init() {
		add_action( 'digitalisimo_plugin_catalog_after', array( __CLASS__, 'render' ) );
		add_action( 'admin_post_' . self::INSTALL, array( __CLASS__, 'install' ) );
		add_action( 'admin_post_' . self::UPDATE, array( __CLASS__, 'update' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'network_admin_notices', array( __CLASS__, 'notices' ) );
	}

	/** ¿WordPress escribe en la carpeta de temas sin pedir credenciales? Si no, se enlaza a Actualizaciones. */
	private static function direct_filesystem() {
		if ( ! function_exists( 'get_filesystem_method' ) ) require_once ABSPATH . 'wp-admin/includes/file.php';
		return 'direct' === get_filesystem_method( array(), get_theme_root() );
	}

	/** Enlace firmado de una acción para una copia, con el contexto de la pantalla donde se pinta. */
	private static function action_url( $action, $stylesheet, $network ) {
		return wp_nonce_url( add_query_arg( array( 'action' => $action, 'theme' => rawurlencode( (string) $stylesheet ), 'digitalisimo_context' => $network ? 'network' : 'site' ), admin_url( 'admin-post.php' ) ), $action . '_' . $stylesheet );
	}

	private static function link( $url, $text, $class = 'button' ) {
		return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a>';
	}

	private static function details_link( $stylesheet ) {
		add_thickbox();
		$url = add_query_arg( array( 'TB_iframe' => 'true', 'width' => 772, 'height' => 560 ), Theme_Updates::details_url( $stylesheet ) );
		return '<a class="button thickbox open-plugin-details-modal" href="' . esc_url( $url ) . '">Ver detalles</a>';
	}

	/** Estado de la versión publicada frente a la instalada. */
	private static function update_state( $installed, array $release ) {
		if ( empty( $release['version'] ) ) return 'Sin datos de la Release';
		if ( '' === $installed || ! version_compare( $release['version'], $installed, '>' ) ) return '' === $installed ? '' : 'Al día';
		if ( ! Theme_Updates::compatible( $release ) ) return sprintf( 'Incompatible: la versión %1$s requiere WordPress %2$s y PHP %3$s', $release['version'], $release['requires'] ?? Theme_Updates::REQUIRES, $release['requires_php'] ?? Theme_Updates::REQUIRES_PHP );
		return 'Actualización disponible';
	}

	/** Pinta la sección. `$context` es `network` en la administración de red y `site` en la de un sitio. */
	public static function render( $context = 'site' ) {
		$network = is_multisite() && 'network' === $context;
		$subsite = is_multisite() && ! $network;
		$copies  = Theme_Updates::installed();
		$release = Theme_Updates::release();
		$direct  = self::direct_filesystem();
		echo '<h2 id="digitalisimo-theme">Tema</h2>';
		if ( $network ) echo '<p>' . esc_html( 'Instala y actualiza el tema DIGITALÍSIMO para toda la red. Habilitarlo para los sitios y activarlo es siempre una decisión tuya, en Temas de la red y en cada sitio.' ) . '</p>';
		elseif ( $subsite ) echo '<p>' . esc_html( 'El tema DIGITALÍSIMO lo instala y actualiza el administrador de la red.' ) . '</p>';
		else echo '<p>' . esc_html( 'Instala y actualiza el tema DIGITALÍSIMO. Activarlo es siempre una decisión tuya, en Apariencia → Temas.' ) . '</p>';
		if ( ! $direct && ! $subsite ) echo '<p>' . esc_html( 'Este servidor pide credenciales para escribir archivos: instala o actualiza el tema desde ' ) . self::link( self_admin_url( 'update-core.php' ), 'Escritorio → Actualizaciones', '' ) . '.</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- link() escapa.
		echo '<table class="widefat striped digitalisimo-theme-catalog"><thead><tr><th>Tema</th><th>Descripción</th><th>Instalada</th><th>Publicada</th><th>Estado</th><th>Acción</th></tr></thead><tbody>';
		if ( $copies ) {
			foreach ( $copies as $stylesheet => $info ) echo self::row( (string) $stylesheet, $info, $release, $network, $subsite, $direct ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- row() escapa.
		} else {
			echo self::row( '', null, $release, $network, $subsite, $direct ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- row() escapa.
		}
		echo '</tbody></table>';
		if ( ! $network ) self::render_inventory();
	}

	/** Una fila: una copia instalada o, si no hay ninguna, «No instalado». */
	private static function row( $stylesheet, $info, array $release, $network, $subsite, $direct ) {
		$installed = $info ? (string) $info['version'] : '';
		$theme     = '' !== $stylesheet ? wp_get_theme( $stylesheet ) : null;
		$active    = '' !== $stylesheet && get_template() === $stylesheet;
		$pending   = '' !== $installed && ! empty( $release['version'] ) && version_compare( $release['version'], $installed, '>' );
		$state     = array();
		if ( $subsite ) {
			if ( $info ) {
				$state[] = $theme && $theme->is_allowed( 'both', get_current_blog_id() ) ? 'Disponible en este sitio' : 'No disponible en este sitio';
				if ( $active ) $state[] = 'Activo aquí';
			} else {
				$state[] = 'No instalado';
			}
			$state[] = 'La instala y actualiza el administrador de la red';
		} else {
			if ( ! $info ) $state[] = 'No instalado';
			elseif ( $network ) $state[] = $theme && $theme->is_allowed( 'network' ) ? 'Habilitado en la red' : 'No habilitado en la red';
			else $state[] = $active ? 'Activo' : 'Inactivo';
			if ( $network && $active ) $state[] = 'Activo en el sitio principal';
			$version_state = self::update_state( $installed, $release );
			if ( '' !== $version_state ) $state[] = $version_state;
			if ( $info ) $state[] = in_array( $stylesheet, (array) get_site_option( 'auto_update_themes', array() ), true ) ? 'Actualización automática activada' : 'Actualización automática desactivada';
		}
		$version = $info ? $installed . ( Theme_Updates::SLUG !== $stylesheet ? ' (carpeta ' . $stylesheet . ')' : '' ) : '—';
		$html    = '<tr><td><strong>DIGITALÍSIMO</strong></td><td>' . esc_html( 'Tema clásico mínimo para DIGITALÍSIMO Elements: cede cabecera, pie, entradas y archivos al constructor de temas.' ) . '</td>';
		$html   .= '<td>' . esc_html( $version ) . '</td><td>' . esc_html( ! empty( $release['version'] ) ? $release['version'] : 'Sin publicar' ) . '</td>';
		$html   .= '<td>' . implode( '<br>', array_map( 'esc_html', $state ) ) . '</td><td>' . implode( ' ', self::actions( $stylesheet, $info, $release, $network, $subsite, $direct, $pending ) ) . '</td></tr>';
		return $html;
	}

	/** Acciones de la fila según el contexto y las capacidades. Nunca «Activar» ni «Habilitar». */
	private static function actions( $stylesheet, $info, array $release, $network, $subsite, $direct, $pending ) {
		$actions = array();
		if ( $subsite ) {
			if ( current_user_can( 'manage_network_themes' ) ) $actions[] = self::link( network_admin_url( 'admin.php?page=digitalisimo-plugins' ), 'Abrir en la red' );
			return $actions;
		}
		$manage = $network ? current_user_can( 'manage_network_themes' ) : ( current_user_can( 'install_themes' ) || current_user_can( 'update_themes' ) );
		if ( $manage ) {
			if ( ! $info ) {
				if ( ! empty( $release['package'] ) && current_user_can( 'install_themes' ) ) {
					$actions[] = $direct ? self::link( self::action_url( self::INSTALL, Theme_Updates::SLUG, $network ), 'Instalar', 'button button-primary' ) : self::link( self_admin_url( 'update-core.php' ), 'Ir a Actualizaciones' );
				}
			} else {
				if ( $pending && current_user_can( 'update_themes' ) && Theme_Updates::compatible( $release ) ) {
					$actions[] = $direct ? self::link( self::action_url( self::UPDATE, $stylesheet, $network ), 'Actualizar', 'button button-primary' ) : self::link( self_admin_url( 'update-core.php' ), 'Ir a Actualizaciones' );
				}
				if ( current_user_can( 'update_themes' ) ) $actions[] = self::link( Theme_Updates::check_url( $stylesheet, $network ? 'network' : 'site' ), 'Buscar actualizaciones' );
			}
		}
		if ( current_user_can( 'switch_themes' ) && ( $info || ! empty( $release['version'] ) ) ) $actions[] = self::details_link( $info ? $stylesheet : Theme_Updates::SLUG );
		$actions[] = $network ? self::link( network_admin_url( 'themes.php' ), 'Temas de la red' ) : self::link( admin_url( 'themes.php' ), 'Ir a Temas' );
		return $actions;
	}

	/* ------------------------------------------------------------------------------------------------------------
	 * Inventario previo (sólo lectura): lo que el tema registrará al activarse, visto antes de cambiar.
	 * ---------------------------------------------------------------------------------------------------------- */

	/** Ajustes guardados de Hello: `true` = Hello lo tenía apagado (T5). */
	public static function hello_settings() {
		$settings = array();
		foreach ( self::HELLO_SETTINGS as $key ) $settings[ $key ] = 'true' === get_option( 'hello_elementor_settings_' . $key );
		return $settings;
	}

	/** ¿Está activo el experimento de cabecera y pie de Hello? Como el gestor de experimentos del constructor. */
	public static function header_footer_experiment() {
		$state = (string) get_option( 'elementor_experiment-hello-theme-header-footer', '' );
		if ( 'active' === $state ) return true;
		if ( '' !== $state && 'default' !== $state ) return false;
		$history = get_option( 'elementor_install_history', array() );
		if ( ! is_array( $history ) || ! $history ) return false;
		$first = (string) preg_replace( '/-(beta|cloud|dev)\d*$/', '', (string) array_key_first( $history ) );
		return version_compare( $first, '3.3.0', '>=' );
	}

	/** `tb`, `tb_partial` o '' según las plantillas publicadas de esa ubicación con `include/general`. */
	private static function theme_builder_mode( $part ) {
		$cache = get_option( 'elementor_pro_theme_builder_conditions', array() );
		$mode  = '';
		foreach ( ( is_array( $cache ) && isset( $cache[ $part ] ) && is_array( $cache[ $part ] ) ) ? $cache[ $part ] : array() as $id => $cached ) {
			if ( 'publish' !== get_post_status( (int) $id ) ) continue;
			$saved = get_post_meta( (int) $id, '_elementor_conditions', true );
			$list  = array_map( 'strval', is_array( $saved ) && $saved ? $saved : (array) $cached );
			if ( ! in_array( 'include/general', $list, true ) ) continue;
			foreach ( $list as $condition ) {
				if ( 0 === strpos( $condition, 'exclude/' ) ) {
					$mode = 'tb_partial';
					continue 2;
				}
			}
			return 'tb';
		}
		return $mode;
	}

	/** ¿Están guardados en '' todos los interruptores de visualización de la parte en el kit activo? (ausente = `yes`). */
	private static function all_hidden( $part ) {
		$kit      = (int) get_option( 'elementor_active_kit', 0 );
		$settings = $kit ? get_post_meta( $kit, '_elementor_page_settings', true ) : array();
		$settings = is_array( $settings ) ? $settings : array();
		foreach ( 'footer' === $part ? array( 'logo', 'tagline', 'menu', 'copyright' ) : array( 'logo', 'tagline', 'menu' ) as $toggle ) {
			$key = 'hello_' . $part . '_' . $toggle . '_display';
			if ( ! array_key_exists( $key, $settings ) || '' !== $settings[ $key ] ) return false;
		}
		return true;
	}

	/**
	 * Modo de cabecera o pie (T6): disabled, tb, tb_partial, dynamic, hidden (sólo la cabecera) o classic. Lee
	 * opciones; no llama al constructor. Con todo apagado, Hello sigue imprimiendo el pie vacío: es dynamic.
	 */
	public static function header_footer_mode( $part, array $hello, $experiment ) {
		if ( ! empty( $hello['header_footer'] ) ) return 'disabled';
		$builder = self::theme_builder_mode( $part );
		if ( '' !== $builder ) return $builder;
		if ( $experiment ) return 'header' === $part && self::all_hidden( $part ) ? 'hidden' : 'dynamic';
		return 'classic';
	}

	/** El style.css del hijo sin su cabecera (la misma expresión que el tema). */
	private static function child_style( $child ) {
		$file = trailingslashit( (string) $child->get_stylesheet_directory() ) . 'style.css';
		$text = is_readable( $file ) ? (string) file_get_contents( $file ) : '';
		return (string) preg_replace( '#^\s*/\*.*?Theme Name\s*:.*?\*/#is', '', $text, 1 );
	}

	/** Plantillas de página que sólo existen en el hijo y cuántos contenidos las usan. */
	private static function child_templates( $child ) {
		$own = array();
		foreach ( (array) $child->get_post_templates() as $templates ) $own += (array) $templates;
		$parent = $child->parent();
		if ( $parent ) {
			foreach ( (array) $parent->get_post_templates() as $templates ) {
				foreach ( array_keys( (array) $templates ) as $file ) unset( $own[ $file ] );
			}
		}
		$out = array();
		foreach ( array_keys( $own ) as $file ) {
			$ids   = get_posts( array( 'post_type' => 'any', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true, 'meta_key' => '_wp_page_template', 'meta_value' => (string) $file ) ); // phpcs:ignore WordPress.DB.SlowDBQuery -- recuento de sólo lectura.
			$out[] = array( 'template' => (string) $file, 'pages' => count( (array) $ids ) );
		}
		return $out;
	}

	/**
	 * Inventario del hijo (T10): archivos PHP (como mucho 200), bytes de las reglas de su style.css, selectores de la
	 * cabecera y el pie de Hello que reescribe, plantillas propias y si su código nombra los recursos de Hello.
	 */
	public static function child_inventory( $child ) {
		$dir  = untrailingslashit( (string) $child->get_stylesheet_directory() );
		$php  = array();
		$deps = false;
		if ( is_dir( $dir ) ) {
			// Una subcarpeta ilegible se salta (CATCH_GET_CHILD); si ni la carpeta del hijo se puede abrir, no hay lista.
			try {
				foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::LEAVES_ONLY, \RecursiveIteratorIterator::CATCH_GET_CHILD ) as $file ) {
					if ( count( $php ) >= 200 ) break;
					if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) ) continue;
					$php[] = ltrim( str_replace( '\\', '/', substr( $file->getPathname(), strlen( $dir ) ) ), '/' );
					if ( ! $deps && $file->getSize() <= ( defined( 'MB_IN_BYTES' ) ? MB_IN_BYTES : 1048576 ) && $file->isReadable() ) $deps = false !== strpos( (string) file_get_contents( $file->getPathname() ), 'hello-elementor' );
				}
			} catch ( \UnexpectedValueException $e ) {
				$php = array();
			}
			sort( $php );
		}
		$style     = self::child_style( $child );
		$selectors = array();
		foreach ( self::CHILD_SELECTORS as $selector ) {
			if ( false !== strpos( $style, $selector ) ) $selectors[] = $selector;
		}
		return array( 'php_files' => $php, 'style_bytes' => strlen( trim( $style ) ), 'page_templates' => self::child_templates( $child ), 'selectors' => $selectors, 'enqueues_hello_deps' => $deps );
	}

	/** Archivo donde se definió un callback, o ''. */
	private static function callback_file( $callback ) {
		try {
			if ( is_string( $callback ) && false !== strpos( $callback, '::' ) ) $callback = explode( '::', $callback, 2 );
			if ( is_array( $callback ) && 2 === count( $callback ) ) return (string) ( new \ReflectionMethod( $callback[0], (string) $callback[1] ) )->getFileName();
			if ( $callback instanceof \Closure || ( is_string( $callback ) && function_exists( $callback ) ) ) return (string) ( new \ReflectionFunction( $callback ) )->getFileName();
			if ( is_object( $callback ) && method_exists( $callback, '__invoke' ) ) return (string) ( new \ReflectionMethod( $callback, '__invoke' ) )->getFileName();
		} catch ( \ReflectionException $e ) {
			return '';
		}
		return '';
	}

	/**
	 * Callbacks presentes en los filtros `hello_elementor_*` y `hello-plus-theme/*`, sin contar los del propio Hello
	 * (no estarán después del cambio). `child` cuenta los que vienen del hijo, que tampoco se cargarán.
	 *
	 * @return array{filters: array<string,int>, child: array<string,int>}
	 */
	public static function filters( $hello_dir = '', $child_dir = '' ) {
		$hello_dir = '' !== $hello_dir ? trailingslashit( wp_normalize_path( $hello_dir ) ) : '';
		$child_dir = '' !== $child_dir ? trailingslashit( wp_normalize_path( $child_dir ) ) : '';
		$filters   = array();
		$child     = array();
		foreach ( (array) ( $GLOBALS['wp_filter'] ?? array() ) as $hook => $object ) {
			$hook = (string) $hook;
			if ( 0 !== strpos( $hook, 'hello_elementor_' ) && 0 !== strpos( $hook, 'hello-plus-theme/' ) ) continue;
			$callbacks = is_object( $object ) && isset( $object->callbacks ) ? $object->callbacks : ( is_array( $object ) ? $object : array() );
			foreach ( (array) $callbacks as $list ) {
				foreach ( (array) $list as $callback ) {
					$file = wp_normalize_path( self::callback_file( is_array( $callback ) ? ( $callback['function'] ?? null ) : null ) );
					if ( '' !== $hello_dir && 0 === strpos( $file, $hello_dir ) ) continue;
					$filters[ $hook ] = ( $filters[ $hook ] ?? 0 ) + 1;
					if ( '' !== $child_dir && 0 === strpos( $file, $child_dir ) ) $child[ $hook ] = ( $child[ $hook ] ?? 0 ) + 1;
				}
			}
		}
		ksort( $filters );
		ksort( $child );
		return array( 'filters' => $filters, 'child' => $child );
	}

	/** ¿Imprime SEO la meta description en este sitio? */
	private static function seo_prints_description() {
		return class_exists( 'Digitalisimo_Integrations_Settings' ) && (bool) \Digitalisimo_Integrations_Settings::get( 'enable_seo' );
	}

	/**
	 * Lo que el tema registraría al activarse en este sitio, con Hello (o un hijo suyo) activo; array() con otro tema.
	 * Las claves siguen las del registro y la bitácora del tema (`origin`, `hello`, `header_footer`, `child`, `filters`,
	 * `description`); `child_filters` es propia de esta pantalla.
	 */
	public static function inventory() {
		if ( 'hello-elementor' !== get_template() ) return array();
		$current    = wp_get_theme();
		$parent     = $current->parent() ? $current->parent() : $current;
		$is_child   = get_stylesheet() !== get_template();
		$hello      = self::hello_settings();
		$experiment = self::header_footer_experiment();
		$filters    = self::filters( (string) $parent->get_stylesheet_directory(), $is_child ? (string) $current->get_stylesheet_directory() : '' );
		return array(
			'origin'        => array( 'family' => 'hello', 'stylesheet' => (string) get_stylesheet(), 'template' => (string) get_template(), 'version' => (string) $parent->get( 'Version' ), 'child' => $is_child ),
			'hello'         => $hello,
			'header_footer' => array( 'experiment' => $experiment, 'header' => self::header_footer_mode( 'header', $hello, $experiment ), 'footer' => self::header_footer_mode( 'footer', $hello, $experiment ) ),
			'child'         => $is_child ? self::child_inventory( $current ) : array(),
			'filters'       => $filters['filters'],
			'child_filters' => $filters['child'],
			'description'   => array( 'hello_printed' => empty( $hello['description_meta_tag'] ), 'seo_prints' => self::seo_prints_description() ),
		);
	}

	/** Explicación de cada modo de cabecera y pie. */
	private static function mode_text( $mode, $part ) {
		$texts = 'footer' === $part ? array(
			'disabled'   => 'El pie está desactivado en los ajustes de Hello: tampoco se pintará después.',
			'tb'         => 'El pie es una plantilla del constructor de temas para todo el sitio: se conserva tal cual.',
			'tb_partial' => 'El pie es una plantilla del constructor con exclusiones: en las páginas excluidas se verá el pie sencillo del tema.',
			'dynamic'    => 'El pie se configura en los ajustes del sitio del constructor: al activar el tema, DIGITALÍSIMO Elements lo convierte en una plantilla equivalente.',
			'hidden'     => 'El pie se configura en los ajustes del sitio y tiene todos sus elementos ocultos: como en Hello, se pinta su contenedor vacío, con su fondo y su relleno, en una plantilla que DIGITALÍSIMO Elements genera al activar el tema.',
			'classic'    => 'El pie es el sencillo del tema: el tema DIGITALÍSIMO pinta el mismo.',
		) : array(
			'disabled'   => 'La cabecera está desactivada en los ajustes de Hello: tampoco se pintará después.',
			'tb'         => 'La cabecera es una plantilla del constructor de temas para todo el sitio: se conserva tal cual.',
			'tb_partial' => 'La cabecera es una plantilla del constructor con exclusiones: en las páginas excluidas se verá la cabecera sencilla del tema.',
			'dynamic'    => 'La cabecera se configura en los ajustes del sitio del constructor: al activar el tema, DIGITALÍSIMO Elements la convierte en una plantilla equivalente.',
			'hidden'     => 'La cabecera se configura en los ajustes del sitio y tiene todos sus elementos ocultos: seguirá sin pintarse.',
			'classic'    => 'La cabecera es la sencilla del tema: el tema DIGITALÍSIMO pinta la misma.',
		);
		return $texts[ $mode ] ?? $texts['classic'];
	}

	/** Lista «<code>clave</code>: n» (los identificadores de Hello van en <code>); `$plural` se usa con n distinto de 1. */
	private static function code_counts( array $counts, $singular, $plural ) {
		$items = array();
		foreach ( $counts as $key => $count ) $items[] = '<code>' . esc_html( (string) $key ) . '</code>: ' . esc_html( sprintf( 1 === (int) $count ? $singular : $plural, (int) $count ) );
		return implode( '<br>', $items );
	}

	/** «Antes de cambiar de tema»: sólo con Hello o un hijo suyo activo; no ejecuta nada. */
	public static function render_inventory() {
		$inventory = self::inventory();
		if ( ! $inventory ) return;
		$origin = $inventory['origin'];
		$rows   = array();
		if ( $origin['child'] ) {
			$child  = $inventory['child'];
			$rows[] = array( 'Tema hijo', '<code>' . esc_html( $origin['stylesheet'] ) . '</code>' );
			$rows[] = array( 'Archivos PHP del hijo', $child['php_files'] ? esc_html( sprintf( '%d: ', count( $child['php_files'] ) ) ) . implode( ', ', array_map( static function ( $file ) { return '<code>' . esc_html( $file ) . '</code>'; }, array_slice( $child['php_files'], 0, 20 ) ) ) . ( count( $child['php_files'] ) > 20 ? esc_html( '…' ) : '' ) : esc_html( 'Ninguno' ) );
			$style  = $child['style_bytes'] ? sprintf( '%s bytes de reglas propias', number_format_i18n( $child['style_bytes'] ) ) : 'Sin reglas propias';
			$rows[] = array( 'style.css del hijo', esc_html( $style ) . ( $child['selectors'] ? esc_html( '; reescribe ' ) . implode( ', ', array_map( static function ( $selector ) { return '<code>' . esc_html( $selector ) . '</code>'; }, $child['selectors'] ) ) : '' ) . '<br>' . esc_html( 'No se copia solo: si lo necesitas, pégalo en el CSS adicional después de revisar el resultado.' ) );
			$templates = array();
			foreach ( $child['page_templates'] as $template ) $templates[ $template['template'] ] = $template['pages'];
			$rows[] = array( 'Plantillas propias del hijo', $templates ? self::code_counts( $templates, '%d contenido la usa', '%d contenidos la usan' ) . '<br>' . esc_html( 'Esos contenidos se verán con la plantilla por defecto.' ) : esc_html( 'Ninguna' ) );
			if ( $child['enqueues_hello_deps'] ) $rows[] = array( 'Hojas y scripts del hijo', esc_html( 'Su código nombra los recursos de Hello; en el modo equivalente siguen existiendo con el mismo nombre.' ) );
		}
		$from_child = array_sum( $inventory['child_filters'] );
		$rows[]     = array( 'Filtros del tema anterior', $inventory['filters'] ? self::code_counts( $inventory['filters'], '%d función', '%d funciones' ) . ( $from_child ? '<br>' . esc_html( sprintf( 1 === $from_child ? '%d viene del hijo y dejará de aplicarse.' : '%d vienen del hijo y dejarán de aplicarse.', $from_child ) ) : '' ) : esc_html( 'Ninguno fuera de Hello' ) );
		$description = $inventory['description'];
		if ( $description['hello_printed'] && ! $description['seo_prints'] ) $text = 'Hello imprime la meta description con el extracto y Digitalisimo SEO no está activo para hacerlo: después del cambio no se imprimirá. Activa SEO si la necesitas.';
		elseif ( $description['hello_printed'] ) $text = 'Hello y Digitalisimo SEO la imprimen; después del cambio sólo la imprime SEO.';
		else $text = $description['seo_prints'] ? 'La imprime Digitalisimo SEO; no cambia.' : 'Nadie la imprime; no cambia.';
		$rows[] = array( 'Meta description', esc_html( $text ) );
		$rows[] = array( 'Cabecera', esc_html( self::mode_text( $inventory['header_footer']['header'], 'header' ) ) );
		// El pie con todo oculto es «dynamic» (como en el tema), pero merece su propia explicación: se ve vacío.
		$footer = $inventory['header_footer']['footer'];
		if ( 'dynamic' === $footer && self::all_hidden( 'footer' ) ) $footer = 'hidden';
		$rows[] = array( 'Pie', esc_html( self::mode_text( $footer, 'footer' ) ) );
		echo '<h2 id="digitalisimo-theme-before">Antes de cambiar de tema</h2><p>' . esc_html( 'El tema activo es Hello' . ( $origin['version'] ? ' ' . $origin['version'] : '' ) . ( $origin['child'] ? ', con un tema hijo' : '' ) . '. Esto es lo que conviene revisar antes de activar el tema DIGITALÍSIMO; esta lista sólo informa y no cambia nada.' ) . '</p>';
		echo '<table class="widefat striped digitalisimo-theme-before"><tbody>';
		foreach ( $rows as $row ) echo '<tr><th scope="row">' . esc_html( $row[0] ) . '</th><td>' . $row[1] . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cada celda se escapó al armarla.
		echo '</tbody></table>';
		if ( $origin['child'] ) echo '<p><strong>' . esc_html( 'El código PHP del hijo no se carga con el tema DIGITALÍSIMO: pásalo a fragmentos PHP de Tools antes de activar.' ) . '</strong></p>';
	}

	/* ------------------------------------------------------------------------------------------------------------
	 * Handlers (admin-post.php): instalar y actualizar, sólo desde la red en Multisite.
	 * ---------------------------------------------------------------------------------------------------------- */

	/** Comprobaciones comunes: capacidad, copia exacta, nonce de esa copia y contexto de red en Multisite. */
	private static function guard( $action, $capability, $valid ) {
		$stylesheet = Theme_Updates::requested_theme();
		if ( ! current_user_can( $capability ) || ( is_multisite() && ! current_user_can( 'manage_network_themes' ) ) ) wp_die( 'No autorizado.' );
		if ( ! $valid( $stylesheet ) ) wp_die( 'El tema indicado no es una copia del tema DIGITALÍSIMO.' );
		check_admin_referer( $action . '_' . $stylesheet );
		if ( is_multisite() && 'network' !== Theme_Updates::context() ) wp_die( 'No autorizado.' );
		return $stylesheet;
	}

	private static function load_upgrader() {
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';
	}

	/** Vuelve al catálogo de la red (Multisite) o del sitio, con el aviso del resultado. */
	private static function back( $code ) {
		$url = is_multisite() ? network_admin_url( 'admin.php?page=digitalisimo-plugins' ) : admin_url( 'admin.php?page=digitalisimo-plugins' );
		wp_safe_redirect( add_query_arg( self::NOTICE, $code, $url ) . '#digitalisimo-theme' );
		exit;
	}

	public static function install() {
		self::guard( self::INSTALL, 'install_themes', static function ( $stylesheet ) {
			return Theme_Updates::SLUG === $stylesheet && ! wp_get_theme( $stylesheet )->exists();
		} );
		if ( ! self::direct_filesystem() ) self::back( 'filesystem' );
		$release = Theme_Updates::release();
		if ( empty( $release['package'] ) ) self::back( 'no_release' );
		self::load_upgrader();
		$upgrader = new \Theme_Upgrader( new \Automatic_Upgrader_Skin() );
		$result   = $upgrader->install( $release['package'] );
		Theme_Updates::forget();
		if ( is_wp_error( $result ) || ! $result ) self::back( 'install_failed' );
		// Theme_Upgrader comprueba el paquete; además debe ser el tema DIGITALÍSIMO. Si no, se informa y no se borra nada.
		$theme = $upgrader->theme_info();
		if ( ! $theme || Theme_Updates::PREFIX . Theme_Updates::SLUG !== (string) $theme->get( 'UpdateURI' ) ) self::back( 'install_foreign' );
		self::back( 'installed' );
	}

	public static function update() {
		$stylesheet = self::guard( self::UPDATE, 'update_themes', static function ( $stylesheet ) {
			return isset( Theme_Updates::installed()[ $stylesheet ] );
		} );
		if ( ! self::direct_filesystem() ) self::back( 'filesystem' );
		// La entrada de esa copia se rehace con el índice nuevo antes de actualizar.
		Theme_Updates::refresh( $stylesheet );
		$release = Theme_Updates::release();
		if ( empty( $release['version'] ) ) self::back( 'no_release' );
		if ( ! Theme_Updates::compatible( $release ) ) self::back( 'incompatible' );
		self::load_upgrader();
		// bulk_upgrade(): modo de mantenimiento en la red, requisitos, comprobación del paquete y `theme` en hook_extra.
		$results = ( new \Theme_Upgrader( new \Automatic_Upgrader_Skin() ) )->bulk_upgrade( array( $stylesheet ) );
		$result  = is_array( $results ) && array_key_exists( $stylesheet, $results ) ? $results[ $stylesheet ] : false;
		Theme_Updates::forget();
		if ( true === $result ) self::back( 'up_to_date' );
		if ( ! $result || is_wp_error( $result ) ) self::back( 'update_failed' );
		self::back( 'updated' );
	}

	/** Avisos del resultado de instalar, actualizar o buscar actualizaciones. */
	public static function notices() {
		$code    = isset( $_GET[ self::NOTICE ] ) && is_string( $_GET[ self::NOTICE ] ) ? sanitize_key( wp_unslash( $_GET[ self::NOTICE ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo muestra el resultado.
		$checked = ! empty( $_GET['digitalisimo-theme-checked'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ( '' === $code && ! $checked ) || ! ( current_user_can( 'install_themes' ) || current_user_can( 'update_themes' ) ) ) return;
		$release = Theme_Updates::release();
		if ( $checked ) {
			$pending = false;
			foreach ( Theme_Updates::installed() as $info ) $pending = $pending || ( ! empty( $release['version'] ) && version_compare( $release['version'], $info['version'], '>' ) );
			$code = empty( $release['version'] ) ? 'no_release' : ( $pending ? 'available' : 'up_to_date' );
		}
		$version  = (string) ( $release['version'] ?? '' );
		$activate = is_multisite() ? 'Para usarlo, habilítalo en Temas de la red y actívalo en cada sitio.' : 'Para usarlo, actívalo en Apariencia → Temas.';
		$messages = array(
			'installed'       => array( 'success', 'El tema DIGITALÍSIMO quedó instalado. ' . $activate ),
			'updated'         => array( 'success', 'El tema DIGITALÍSIMO quedó actualizado' . ( '' !== $version ? ' a la versión ' . $version : '' ) . '.' ),
			'up_to_date'      => array( 'success', 'El tema DIGITALÍSIMO está al día.' ),
			'available'       => array( 'warning', 'El tema DIGITALÍSIMO ' . $version . ' está disponible para actualizar.' ),
			'no_release'      => array( 'warning', 'No se pudo consultar la versión publicada del tema DIGITALÍSIMO: inténtalo de nuevo en unos minutos.' ),
			'incompatible'    => array( 'warning', 'La versión publicada del tema DIGITALÍSIMO requiere una versión más nueva de WordPress o de PHP: no se actualizó.' ),
			'filesystem'      => array( 'warning', 'Este servidor pide credenciales para escribir archivos: instala o actualiza el tema desde Escritorio → Actualizaciones.' ),
			'install_failed'  => array( 'error', 'No se pudo instalar el tema DIGITALÍSIMO. No se cambió nada.' ),
			'install_foreign' => array( 'error', 'El paquete instalado no es el tema DIGITALÍSIMO. No se borró nada: revísalo en Temas.' ),
			'update_failed'   => array( 'error', 'No se pudo actualizar el tema DIGITALÍSIMO. La versión instalada sigue en su lugar.' ),
		);
		if ( ! isset( $messages[ $code ] ) ) return;
		echo '<div class="notice notice-' . esc_attr( $messages[ $code ][0] ) . ' is-dismissible"><p>' . esc_html( $messages[ $code ][1] ) . '</p></div>';
	}
}
