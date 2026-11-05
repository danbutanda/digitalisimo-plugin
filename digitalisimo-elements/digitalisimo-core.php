<?php
/**
 * Núcleo de Elementor incluido en DIGITALÍSIMO Elements (carpeta elementor/, copia sin cambios de la versión
 * publicada en wordpress.org; se actualiza con scripts/sync-elementor-core.sh).
 *
 * Se carga sólo si el plugin Elementor no está activo en el sitio ni en la red: con Elementor activo, éste
 * manda y el núcleo incluido no se toca. El contenido, los ajustes y las tablas son los mismos (mismas
 * claves `_elementor_*` y opciones `elementor_*`), así que basta con desactivar Elementor para que el
 * núcleo incluido siga pintando todo igual.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Digitalisimo_Elements_Core {
	const EXTERNAL     = 'elementor/elementor.php';
	const EXTERNAL_PRO = 'elementor-pro/elementor-pro.php';

	public static function dir() {
		return __DIR__ . '/elementor/';
	}

	/** Versión de Elementor incluida, leída del manifiesto que deja el script de sincronización. */
	public static function version() {
		$manifest = json_decode( (string) @file_get_contents( self::dir() . 'digitalisimo-core.json' ), true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- archivo local opcional.
		return is_array( $manifest ) && ! empty( $manifest['version'] ) ? (string) $manifest['version'] : '';
	}

	/** ¿Está activo el plugin Elementor en este sitio o en toda la red? */
	public static function external_active() {
		if ( in_array( self::EXTERNAL, (array) get_option( 'active_plugins', array() ), true ) ) {
			return true;
		}
		return is_multisite() && isset( ( (array) get_site_option( 'active_sitewide_plugins', array() ) )[ self::EXTERNAL ] );
	}

	/**
	 * ¿Esta petición activa el plugin Elementor? Entonces el núcleo incluido no se carga: WordPress incluye
	 * Elementor para activarlo y las dos copias chocarían. Desde la siguiente petición manda Elementor.
	 */
	public static function activating_external( ?array $request = null ) {
		$request = null === $request ? $_REQUEST : $request; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo se lee para no cargar código; WordPress valida la activación.
		$actions = array( (string) ( $request['action'] ?? '' ), (string) ( $request['action2'] ?? '' ) );
		if ( in_array( 'activate', $actions, true ) && self::EXTERNAL === wp_unslash( (string) ( $request['plugin'] ?? '' ) ) ) {
			return true;
		}
		return in_array( 'activate-selected', $actions, true ) && in_array( self::EXTERNAL, array_map( 'wp_unslash', (array) ( $request['checked'] ?? array() ) ), true );
	}

	/** Carga el núcleo incluido cuando hace falta; devuelve si quedó cargado. */
	public static function load() {
		add_action( 'init', array( __CLASS__, 'track_mode' ), 1 );
		if ( defined( 'ELEMENTOR_VERSION' ) || ! is_readable( self::dir() . 'elementor.php' ) || self::external_active() || ( is_admin() && self::activating_external() ) ) {
			return false;
		}
		define( 'DIGITALISIMO_ELEMENTS_CORE', self::version() );
		// Sin telemetría del proveedor en el editor (sólo para el núcleo incluido: el plugin original no se toca).
		if ( ! defined( 'ELEMENTOR_EDITOR_EVENTS_MIXPANEL_TOKEN' ) ) {
			define( 'ELEMENTOR_EDITOR_EVENTS_MIXPANEL_TOKEN', '' );
		}
		self::hooks();
		require self::dir() . 'elementor.php';
		return true;
	}

	public static function bundled() {
		return defined( 'DIGITALISIMO_ELEMENTS_CORE' );
	}

	private static function hooks() {
		// Traducciones: si el idioma de Elementor no está en wp-content/languages (por ejemplo, tras borrar el
		// plugin Elementor), se usan las que trae el núcleo incluido.
		add_filter( 'lang_dir_for_domain', array( __CLASS__, 'lang_dir' ), 10, 3 );
		add_filter( 'load_script_textdomain_relative_path', array( __CLASS__, 'script_relative_path' ), 10, 2 );
		add_filter( 'load_script_translation_file', array( __CLASS__, 'script_translation_file' ), 10, 3 );
		// Los plugins que declaran «Requires Plugins: elementor» se satisfacen con DIGITALÍSIMO Elements.
		add_filter( 'wp_plugin_dependencies_slug', array( __CLASS__, 'dependency_slug' ) );
		// La reversión de versiones y el canal beta de Elementor actualizarían la carpeta del plugin entero: la
		// versión del núcleo la decide cada publicación de DIGITALÍSIMO Elements.
		add_filter( 'elementor/settings/rollback/versions', '__return_empty_array', 99 );
		add_action( 'admin_post_elementor_rollback', array( __CLASS__, 'block_rollback' ), 1 );
		add_filter( 'pre_option_elementor_beta', array( __CLASS__, 'no_beta' ) );
		add_filter( 'site_transient_update_plugins', array( __CLASS__, 'drop_core_updates' ) );
		// Un sitio nuevo de la red recibe su kit por defecto, como hace Elementor activado en la red.
		add_action( 'wp_initialize_site', array( __CLASS__, 'initialize_site' ), 20 );
		// El contenido guardado con el plugin Elementor apunta a sus archivos (imágenes de ejemplo, por ejemplo):
		// al leerlo se apunta a los del núcleo incluido, y una petición que aún llegue a la ruta antigua se redirige.
		add_filter( 'get_post_metadata', array( __CLASS__, 'old_asset_urls' ), 10, 4 );
		add_action( 'init', array( __CLASS__, 'redirect_old_asset' ), 0 );
		// El plugin Elementor (y Elementor Pro) instalado pero desactivado no se toca: su fila en la lista de plugins muestra
		// sus propias cabeceras, sin que los filtros de identidad las conviertan en un segundo «DIGITALÍSIMO Elements»; una
		// nota aditiva explica que no hace falta; y borrarlo no carga su archivo principal, que chocaría con el núcleo incluido.
		add_filter( 'all_plugins', array( __CLASS__, 'external_rows' ) );
		foreach ( self::EXTERNAL_PLUGINS as $file ) {
			// Tras la fila de actualización de WordPress (prioridad 10), para que no se mezclen.
			add_action( 'after_plugin_row_' . $file, array( __CLASS__, 'external_row_note' ), 11, 2 );
		}
		add_filter( 'option_uninstall_plugins', array( __CLASS__, 'skip_external_uninstall' ) );
	}

	/** Plugins externos del constructor que, instalados junto a DIGITALÍSIMO Elements, deben seguir leyéndose como lo que son. */
	const EXTERNAL_PLUGINS = array( self::EXTERNAL, self::EXTERNAL_PRO );

	/**
	 * Con el núcleo incluido, los filtros de identidad reescriben «Elementor» en los textos del dominio `elementor`, y
	 * WordPress traduce con ese dominio las cabeceras del plugin Elementor al listarlo: su fila pasaba a leerse como un
	 * segundo «DIGITALÍSIMO Elements» por «DIGITALÍSIMO Elements.com». Las cabeceras se traducen aquí con su propio
	 * dominio y sin los filtros de identidad, y se entregan sin dominio para que WordPress no las vuelva a traducir: la
	 * fila dice exactamente lo que dice el plugin. Las demás filas no se tocan.
	 */
	public static function external_rows( $plugins ) {
		if ( ! is_array( $plugins ) ) {
			return $plugins;
		}
		$brand   = array( 'Digitalisimo_Elements_Identity', 'gettext' );
		$removed = false;
		foreach ( self::EXTERNAL_PLUGINS as $file ) {
			if ( ! isset( $plugins[ $file ] ) || ! is_array( $plugins[ $file ] ) ) {
				continue;
			}
			$row    = $plugins[ $file ];
			$domain = (string) ( $row['TextDomain'] ?? '' );
			if ( '' !== $domain && function_exists( 'translate' ) ) {
				// Como _get_plugin_data_markup_translate(): las traducciones del propio plugin, si aún no están cargadas.
				if ( function_exists( 'is_textdomain_loaded' ) && ! is_textdomain_loaded( $domain ) && function_exists( 'load_plugin_textdomain' ) ) {
					load_plugin_textdomain( $domain, false, dirname( $file ) . (string) ( $row['DomainPath'] ?? '' ) );
				}
				if ( ! $removed && function_exists( 'remove_filter' ) ) {
					$removed = remove_filter( 'gettext', $brand, 20 );
				}
				foreach ( array( 'Name', 'PluginURI', 'Description', 'Author', 'AuthorURI', 'Version' ) as $field ) {
					if ( isset( $row[ $field ] ) && is_string( $row[ $field ] ) && '' !== $row[ $field ] ) {
						$row[ $field ] = translate( $row[ $field ], $domain ); // phpcs:ignore WordPress.WP.I18n.LowLevelTranslationFunction,WordPress.WP.I18n.NonSingularStringLiteralText,WordPress.WP.I18n.NonSingularStringLiteralDomain -- como _get_plugin_data_markup_translate(), con el dominio del propio plugin.
					}
				}
			}
			$row['TextDomain'] = '';
			$row['DomainPath'] = '';
			$plugins[ $file ]  = $row;
		}
		if ( $removed ) {
			add_filter( 'gettext', $brand, 20, 3 );
		}
		return $plugins;
	}

	/**
	 * Al borrar un plugin con rutina de desinstalación registrada, WordPress incluye su archivo principal para ejecutarla;
	 * con el núcleo incluido ya cargado, el de Elementor (o el de Elementor Pro) redeclara sus funciones y clases y la
	 * petición muere con un error fatal: el plugin no se podía borrar. Mientras manda el núcleo incluido, esos dos se
	 * borran sin ejecutar su rutina (la de Elementor sólo quita una tarea programada y avisa a su API): se van sus
	 * archivos y nada más.
	 */
	public static function skip_external_uninstall( $value ) {
		if ( is_array( $value ) ) {
			foreach ( self::EXTERNAL_PLUGINS as $file ) {
				unset( $value[ $file ] );
			}
		}
		return $value;
	}

	/**
	 * Nota aditiva bajo la fila del plugin externo. Conserva data-plugin (así WordPress la retira al borrar el plugin por
	 * AJAX) pero no la clase update-message, que updates.js sobrescribe con «Actualizando…» al actualizar ese plugin.
	 */
	public static function external_row_note( $plugin_file, $plugin_data ) {
		if ( ! in_array( $plugin_file, self::EXTERNAL_PLUGINS, true ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$columns = 4;
		if ( function_exists( '_get_list_table' ) && function_exists( 'get_current_screen' ) && get_current_screen() ) {
			$table   = _get_list_table( 'WP_Plugins_List_Table', array( 'screen' => get_current_screen() ) );
			$columns = $table ? (int) $table->get_column_count() : $columns;
		}
		echo '<tr class="plugin-update-tr digitalisimo-plugin-note" data-plugin="' . esc_attr( $plugin_file ) . '"><td colspan="' . esc_attr( $columns ) . '" class="plugin-update colspanchange"><div class="notice inline notice-warning notice-alt"><p>' . esc_html( self::external_note_text( $plugin_file ) ) . '</p></div></td></tr>';
	}

	/**
	 * Texto de la nota según el estado real: activo aquí (Elementor Pro puede estarlo con el núcleo incluido cargado)
	 * manda él y no se da por borrable; en Red → Plugins se cuenta sitio por sitio dónde sigue activo, porque borrarlo
	 * desde la red dejaría esos sitios sin él; en un sitio de la red se indica dónde se borra.
	 */
	public static function external_note_text( $plugin_file ) {
		$what   = self::EXTERNAL_PRO === $plugin_file
			? 'el plugin Elementor Pro original: no hace falta, porque DIGITALÍSIMO Elements ya incluye las funciones avanzadas'
			: sprintf( 'el plugin Elementor original: no hace falta, porque DIGITALÍSIMO Elements ya incluye el constructor (versión %s)', self::version() );
		$active = self::active_sites( $plugin_file );
		if ( $active['here'] ) {
			return sprintf( 'Este es %s. Está activo, así que mientras tanto manda él: desactívalo para que DIGITALÍSIMO Elements lo sustituya y entonces podrás borrarlo.', $what );
		}
		if ( $active['sites'] ) {
			return sprintf( 'Este es %s. Sigue activo en %d sitios de la red: desactívalo allí antes de borrarlo, o esos sitios se quedarían sin él.', $what, $active['sites'] );
		}
		$where = is_multisite() && ! is_network_admin() ? ' (se borra desde Red → Plugins)' : '';
		return sprintf( 'Este es %s. Desactivado no hace nada y puedes borrarlo%s; si lo activas, manda él.', $what, $where );
	}

	/**
	 * ¿Está activo un plugin aquí (sitio actual o toda la red) y, en la administración de red, en cuántos sitios más?
	 *
	 * @return array{here:bool, sites:int}
	 */
	public static function active_sites( $plugin_file ) {
		$here  = in_array( $plugin_file, (array) get_option( 'active_plugins', array() ), true ) || ( is_multisite() && isset( ( (array) get_site_option( 'active_sitewide_plugins', array() ) )[ $plugin_file ] ) );
		$sites = 0;
		if ( ! $here && is_multisite() && is_network_admin() && function_exists( 'get_sites' ) ) {
			foreach ( get_sites( array( 'number' => 500, 'fields' => 'ids' ) ) as $site_id ) {
				$sites += in_array( $plugin_file, (array) get_blog_option( (int) $site_id, 'active_plugins', array() ), true ) ? 1 : 0;
			}
		}
		return array( 'here' => $here, 'sites' => $sites );
	}

	/**
	 * Dos carpetas instaladas del mismo plugin Digitalísimo (misma «Update URI» de este repositorio): pasa al subir a mano
	 * un ZIP con otra carpeta raíz o al dejar una copia antigua. Sólo una puede estar activa y la otra sobra; el aviso en
	 * Plugins dice cuáles son para borrar la inactiva. Vale para los siete plugins, con o sin el núcleo incluido.
	 */
	public static function duplicate_copies_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->id, array( 'plugins', 'plugins-network' ), true ) || ! current_user_can( 'activate_plugins' ) || ! function_exists( 'get_plugins' ) ) {
			return;
		}
		$plugins = get_plugins();
		foreach ( self::duplicate_copies( $plugins ) as $files ) {
			$list = array();
			foreach ( $files as $file ) {
				$list[] = dirname( $file ) . ( self::active_sites( $file )['here'] ? ' (activa)' : ' (inactiva)' );
			}
			echo '<div class="notice notice-warning"><p>' . esc_html( sprintf( 'Hay %d copias instaladas del plugin «%s»: %s. Sólo hace falta una; borra la inactiva desde esta lista.', count( $files ), (string) ( $plugins[ $files[0] ]['Name'] ?? $files[0] ), implode( ', ', $list ) ) ) . '</p></div>';
		}
	}

	/** Agrupa por «Update URI» de este repositorio los plugins instalados; devuelve sólo los grupos con más de una carpeta. */
	public static function duplicate_copies( array $plugins ) {
		$groups = array();
		foreach ( $plugins as $file => $data ) {
			$uri = (string) ( $data['UpdateURI'] ?? '' );
			if ( 0 === strpos( $uri, 'https://github.com/danbutanda/digitalisimo-plugin/' ) ) {
				$groups[ $uri ][] = (string) $file;
			}
		}
		return array_filter( $groups, static function ( $files ) {
			return count( $files ) > 1;
		} );
	}

	/** Ruta de los archivos del plugin Elementor y la del núcleo incluido (sólo la ruta: vale con cualquier dominio). */
	public static function asset_paths() {
		$old = (string) wp_parse_url( plugins_url( '/', WP_PLUGIN_DIR . '/elementor/elementor.php' ), PHP_URL_PATH );
		$new = (string) wp_parse_url( plugins_url( '/', self::dir() . 'elementor.php' ), PHP_URL_PATH );
		return array( trailingslashit( $old ), trailingslashit( $new ) );
	}

	/** Cambia la ruta antigua por la nueva en un valor guardado: texto (también JSON con barras escapadas) o lista. */
	public static function replace_asset_urls( $value, $old, $new ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = self::replace_asset_urls( $item, $old, $new );
			}
			return $value;
		}
		if ( ! is_string( $value ) || ( false === strpos( $value, $old ) && false === strpos( $value, str_replace( '/', '\\/', $old ) ) ) ) {
			return $value;
		}
		return str_replace( array( $old, str_replace( '/', '\\/', $old ) ), array( $new, str_replace( '/', '\\/', $new ) ), $value );
	}

	public static function old_asset_urls( $check, $object_id, $meta_key, $single ) {
		static $busy = false;
		if ( $busy || null !== $check || ! in_array( $meta_key, array( '_elementor_data', '_elementor_page_settings' ), true ) ) {
			return $check;
		}
		$busy  = true;
		$value = get_post_meta( $object_id, $meta_key, $single );
		$busy  = false;
		list( $old, $new ) = self::asset_paths();
		$value = $single ? self::replace_asset_urls( $value, $old, $new ) : array_map( static function ( $item ) use ( $old, $new ) {
			return self::replace_asset_urls( $item, $old, $new );
		}, (array) $value );
		return $single ? array( $value ) : $value;
	}

	/** Un archivo pedido en la ruta del plugin Elementor (que ya no existe) se sirve desde el núcleo incluido. */
	public static function redirect_old_asset() {
		$path = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sólo se compara con rutas propias.
		list( $old, $new ) = self::asset_paths();
		if ( 0 !== strpos( $path, $old ) ) {
			return;
		}
		$relative = substr( $path, strlen( $old ) );
		if ( false !== strpos( $relative, '..' ) || ! preg_match( '/\.(?:png|jpe?g|gif|svg|webp|css|js|json|woff2?|ttf|eot|mp4)$/i', $relative ) || ! is_file( self::dir() . $relative ) ) {
			return;
		}
		wp_safe_redirect( $new . $relative, 301 );
		exit;
	}

	/**
	 * Al pasar del plugin Elementor al núcleo incluido en un sitio se vacía una vez la caché de CSS de Elementor:
	 * los archivos generados con el plugin podían apuntar a su carpeta.
	 */
	public static function track_mode() {
		$mode = self::bundled() ? 'bundled' : ( defined( 'ELEMENTOR_VERSION' ) ? 'plugin' : '' );
		if ( '' === $mode || get_option( 'digitalisimo_elements_core_mode' ) === $mode ) {
			return;
		}
		$previous = get_option( 'digitalisimo_elements_core_mode' );
		update_option( 'digitalisimo_elements_core_mode', $mode, true );
		if ( 'bundled' === $mode && 'plugin' === $previous && class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
	}

	/** Carpeta de idiomas de Elementor para un idioma: la global si tiene archivos, si no la del núcleo. */
	public static function lang_dir( $path, $domain, $locale ) {
		if ( 'elementor' !== $domain || ( $path && glob( rtrim( $path, '/' ) . '/elementor-' . $locale . '.*' ) ) ) {
			return $path;
		}
		$own = self::dir() . 'languages/';
		return glob( $own . 'elementor-' . $locale . '.*' ) ? $own : $path;
	}

	/** Ruta relativa de un script del núcleo como la ve Elementor (sin «elementor/»), para encontrar su JSON de idioma. */
	public static function script_relative_path( $relative, $src ) {
		return 0 === strpos( (string) $relative, 'elementor/' ) && false !== strpos( (string) $src, '/' . basename( __DIR__ ) . '/elementor/' ) ? substr( $relative, 10 ) : $relative;
	}

	public static function script_translation_file( $file, $handle, $domain ) {
		if ( 'elementor' !== $domain || ! $file || is_readable( $file ) ) {
			return $file;
		}
		$own = self::dir() . 'languages/' . basename( $file );
		return is_readable( $own ) ? $own : $file;
	}

	public static function dependency_slug( $slug ) {
		return 'elementor' === $slug ? dirname( plugin_basename( DIGITALISIMO_ELEMENTS_FILE ) ) : $slug;
	}

	public static function block_rollback() {
		wp_die( esc_html( sprintf( 'La versión del constructor (%s) viene incluida en DIGITALÍSIMO Elements y cambia con cada actualización de este plugin.', self::version() ) ), '', array( 'back_link' => true ) );
	}

	public static function no_beta() {
		return 'no';
	}

	public static function drop_core_updates( $transient ) {
		if ( is_object( $transient ) && defined( 'ELEMENTOR_PLUGIN_BASE' ) ) {
			unset( $transient->response[ ELEMENTOR_PLUGIN_BASE ], $transient->no_update[ ELEMENTOR_PLUGIN_BASE ] );
		}
		return $transient;
	}

	/** Al activar DIGITALÍSIMO Elements con el núcleo incluido se hace lo mismo que al activar Elementor. */
	public static function activate( $network_wide ) {
		if ( self::bundled() && class_exists( '\Elementor\Maintenance' ) ) {
			\Elementor\Maintenance::activation( $network_wide );
		}
	}

	public static function initialize_site( $site ) {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! is_plugin_active_for_network( plugin_basename( DIGITALISIMO_ELEMENTS_FILE ) ) || ! class_exists( '\Elementor\Core\Kits\Manager' ) ) {
			return;
		}
		switch_to_blog( (int) $site->blog_id );
		\Elementor\Core\Kits\Manager::create_default_kit();
		restore_current_blog();
	}

	/** Aviso en Plugins cuando Elementor sigue activo: ya no hace falta. */
	public static function external_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( self::bundled() || ! $screen || ! in_array( $screen->id, array( 'plugins', 'plugins-network' ), true ) || ! current_user_can( 'activate_plugins' ) || ! self::version() ) {
			return;
		}
		echo '<div class="notice notice-info"><p>' . esc_html( sprintf( 'DIGITALÍSIMO Elements ya incluye su propio constructor (versión %s). Puedes desactivar el plugin Elementor (y después borrarlo): tus páginas, plantillas, kits y ajustes siguen igual.', self::version() ) ) . '</p></div>';
	}
}
