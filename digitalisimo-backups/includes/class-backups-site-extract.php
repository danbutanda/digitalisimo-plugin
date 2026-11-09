<?php
defined( 'ABSPATH' ) || exit;

/**
 * «Extraer como WordPress individual»: un sitio de una red sale como paquete restaurable en una instalación individual.
 *
 * Es un trabajo del mismo motor que los respaldos completos (cola, fases, reanudación, verificación, cifrado y firma) con
 * el alcance explícito `extracted-site` en el manifiesto v3. El paquete es un WordPress individual cuyo prefijo de tablas
 * es el del sitio en la red (`wp_N_`): sus tablas tal cual, más `wp_N_users` y `wp_N_usermeta` sólo con los usuarios del
 * sitio. Ese es el formato de una instalación individual con ese prefijo, así que sus metas (`wp_N_capabilities`…) y sus
 * roles (`wp_N_user_roles`) ya son los suyos. Al restaurar, el motor de reubicación (E1) pasa el prefijo, las URL y las
 * rutas a las del destino: este paquete no reescribe ninguna URL, ruta ni prefijo.
 *
 * Qué decide esta clase (y nada más):
 * - qué tablas, usuarios y metas viajan (`Digitalisimo_Backups_Site_Extract_Export`, que el exportador del motor llama
 *   fila a fila dentro de su instantánea);
 * - qué opciones del sitio cambian para que se comporte igual fuera de la red: plugins activos (los del sitio y los
 *   activados en la red), los ajustes de red de los plugins DIGITALÍSIMO que el sitio hereda (`materialize()`), y las
 *   rutas propias de los medios en la red (`upload_path`), que quedan en las de un sitio individual;
 * - qué archivos viajan (`file_map()`): núcleo, mu-plugins, idiomas, los plugins activos, el tema y su padre, y los
 *   medios del sitio (`uploads/sites/N/` pasa a `uploads/`);
 * - qué se conserva en el destino al restaurar (la configuración de Backups del servidor de destino).
 */
final class Digitalisimo_Backups_Site_Extract {
	const SCOPE = 'extracted-site';
	/** Acción admin-post de la pantalla de red. */
	const ACTION = 'digitalisimo_backups_extract';
	const POLICY = 'extract';
	const FILE_PREFIX = 'extracted-site-';
	/** Qué hacer con los superadministradores que no son miembros del sitio (ver `users()`). */
	const SUPER_ADMINS = array( 'managing', 'all', 'none' );
	/** Tablas de la red que no son de ningún sitio. */
	const GLOBAL_TABLES = array( 'users', 'usermeta', 'blogs', 'blogmeta', 'site', 'sitemeta', 'signups', 'registration_log', 'sitecategories', 'blog_versions' );
	/** Metas de usuario que WordPress guarda por sitio con el prefijo del sitio (`wp_` es el principal). */
	const SITE_USER_META = array( 'capabilities', 'user_level', 'user-settings', 'user-settings-time', 'dashboard_quick_press_last_post_id', 'persisted_preferences', 'media_library_mode' );
	/** Metas globales que sólo tienen sentido en una red (y nombran su dominio). */
	const NETWORK_USER_META = array( 'primary_blog', 'source_domain' );
	/** Credenciales de un usuario que entra sólo como autor (no era miembro del sitio). */
	const CREDENTIAL_USER_META = array( 'session_tokens', '_application_passwords' );
	/** `sitemeta` de WordPress: ni se materializa ni se lista como de otro plugin. */
	const CORE_SITEMETA = array( 'site_name', 'admin_email', 'admin_user_id', 'registration', 'upload_filetypes', 'blog_upload_space', 'fileupload_maxk', 'site_admins', 'allowedthemes', 'illegal_names', 'wpmu_upgrade_site', 'welcome_email', 'welcome_user_email', 'first_post', 'first_page', 'first_comment', 'first_comment_url', 'first_comment_author', 'siteurl', 'add_new_users', 'upload_space_check_disabled', 'subdomain_install', 'ms_files_rewriting', 'user_count', 'blog_count', 'initial_db_version', 'active_sitewide_plugins', 'WPLANG', 'registrationnotification', 'banned_email_domains', 'limited_email_domains', 'new_admin_email', 'global_terms_enabled', 'menu_items', 'recently_activated', 'can_compress_scripts', 'auto_update_plugins', 'auto_update_themes', 'auto_core_update_notified', 'wp_force_deactivated_plugins', 'site_meta_supported', 'db_version', 'dismissed_update_core', 'update_core', 'update_plugins', 'update_themes', 'user_count_ts', 'adminhash', 'customize_stashed_theme_mods', 'ms_files_rewriting', 'super_admins', 'recovery_keys', 'recovery_mode_email_last_sent', 'fresh_site', 'theme_switch_menu_locations' );
	/** Secretos de los ajustes de red de los plugins DIGITALÍSIMO: viajan sólo si se pide. */
	const SEO_OPTION = 'digitalisimo_integrations_options';
	const SEO_SECRETS = array( 'openai_api_key', 'namecheap_api_key', 'ipinfo_tokens' );
	const SEO_SITE_ONLY = array( 'seo_site_name', 'seo_default_image', 'seo_default_image_alt', 'seo_alt_keyword', 'seo_favicon_source' );
	const SEO_MODULE_KEYS = array( 'enable_woocommerce', 'require_domain_hosting', 'replace_hosting', 'simplify_checkout', 'namecheap_api_user', 'namecheap_username', 'namecheap_api_key', 'namecheap_client_ip', 'domain_products', 'hosting_product_ids', 'hosting_category_ids', 'enable_ipinfo', 'ipinfo_tokens', 'ipinfo_cache_minutes' );
	const TOOLS_SITE = 'digitalisimo_tools_site_options';
	const TOOLS_NETWORK = 'digitalisimo_tools_site_defaults';
	const TOOLS_DEFAULTS = array( 'login_slug' => '', 'redirect_attachments' => 1, 'mobile_scroll' => 0 );
	const TOOLS_LEGACY_KEYS = array( 'login_slug' => 'seo_hide_login_slug', 'redirect_attachments' => 'seo_redirect_attachments', 'mobile_scroll' => 'seo_mobile_prevent_horizontal_scroll' );
	const FRONT_SITE = 'digitalisimo_elements_front';
	const FRONT_NETWORK = 'digitalisimo_elements_front_defaults';
	const GOOGLE_KEY = 'digitalisimo_elements_google_places_key';
	const HOSTING = 'digitalisimo_hosting_options';
	const HOSTING_SECRETS = array( 'api_key' );
	const AI = 'digitalisimo_ai_options';
	/** Opciones del sitio que el exportador debe leer antes de recorrer la tabla (herencia y ajustes que se materializan). */
	const SITE_READS = array( 'active_plugins', 'stylesheet', 'template', 'admin_email', 'home', 'siteurl', 'upload_path', 'upload_url_path', self::SEO_OPTION, 'digitalisimo_seo_network_inherit', 'digitalisimo_module_network_inherit', self::TOOLS_SITE, self::FRONT_SITE, self::GOOGLE_KEY, self::HOSTING, self::AI );
	/** `sitemeta` que se leen dentro de la instantánea. */
	const NETWORK_READS = array( 'site_admins', 'active_sitewide_plugins', 'ms_files_rewriting', 'subdomain_install', self::SEO_OPTION, self::TOOLS_NETWORK, self::FRONT_NETWORK, self::GOOGLE_KEY, self::HOSTING, self::AI );
	/** Topes de las listas del informe dentro del manifiesto. */
	const REPORT_LIMIT = 200;

	/* ------------------------------------------------------------------ Petición */

	/** Opciones de la extracción con sus valores por defecto; WP_Error si alguna no es válida. */
	public static function options( array $args ) {
		$mode = isset( $args['super_admins'] ) && '' !== (string) $args['super_admins'] ? sanitize_key( (string) $args['super_admins'] ) : 'managing';
		if ( ! in_array( $mode, self::SUPER_ADMINS, true ) ) return new WP_Error( 'digitalisimo_backups_super_admins', 'Elige qué hacer con los superadministradores que no son miembros del sitio: managing (sólo los que lo administran), all o none.', array( 'status' => 400 ) );
		return array( 'super_admins' => $mode, 'network_secrets' => ! empty( $args['network_secrets'] ) );
	}
	/**
	 * Sitio de la red que se puede extraer, o el motivo: no existe, o su base de datos o sus medios no tienen la forma de
	 * un sitio de una red. Devuelve array( id, domain, path, network_id, url, label ).
	 */
	public static function site( $site_id ) {
		$site_id = (int) $site_id;
		if ( ! is_multisite() ) return new WP_Error( 'digitalisimo_backups_scope', 'Esta instalación no es una red: no hay sitios que extraer.', array( 'status' => 400 ) );
		$site = $site_id > 0 && function_exists( 'get_site' ) ? get_site( $site_id ) : null;
		if ( ! $site ) return new WP_Error( 'digitalisimo_backups_site_missing', 'El sitio ' . $site_id . ' no existe en esta red.', array( 'status' => 404 ) );
		$network = (int) ( $site->network_id ?? ( $site->site_id ?? 1 ) );
		if ( function_exists( 'get_main_network_id' ) && $network !== (int) get_main_network_id() ) return new WP_Error( 'digitalisimo_backups_site_network', 'El sitio ' . $site_id . ' pertenece a otra red de esta instalación; se extrae desde la red principal sólo un sitio suyo.', array( 'status' => 400 ) );
		$url = untrailingslashit( (string) get_home_url( $site_id ) );
		return array( 'id' => $site_id, 'domain' => (string) $site->domain, 'path' => (string) $site->path, 'network_id' => $network, 'url' => $url, 'label' => self::label( (string) $site->domain, (string) $site->path ) );
	}
	public static function label( $domain, $path ) { return rtrim( $domain . $path, '/' ); }
	/** Nombre del paquete: nunca coincide con la rotación de los respaldos completos (`digitalisimo-*.zip`). */
	public static function file_name( array $site, $encrypt = false ) {
		$slug = substr( sanitize_title( str_replace( array( '.', '/' ), '-', $site['label'] ) ), 0, 80 );
		return self::FILE_PREFIX . ( '' !== $slug ? $slug : 'sitio-' . (int) $site['id'] ) . '-' . gmdate( 'Ymd-His' ) . '-' . substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 8 ) . ( $encrypt ? '.dgbk' : '.zip' );
	}
	/**
	 * Especificación inmutable del trabajo: la de un respaldo completo (Digitalisimo_Backups_Jobs::spec(), con su cifrado
	 * y su huella) con el alcance `extracted-site` y el sitio. El motor de la cola no cambia: las fases leen el alcance.
	 */
	public static function job_spec( array $spec, array $site, array $options ) {
		$spec['scope'] = self::SCOPE;
		$spec['extract'] = array( 'site_id' => (int) $site['id'], 'label' => (string) $site['label'], 'super_admins' => (string) $options['super_admins'], 'network_secrets' => (bool) $options['network_secrets'] );
		return $spec;
	}
	/** Etiqueta del trabajo en «Trabajos» (filtro `digitalisimo_backups_job_origin`). */
	public static function job_origin( $label, $spec ) {
		return is_array( $spec ) && self::is_extract( $spec ) ? 'Extracción de ' . (string) $spec['extract']['label'] . ' como WordPress individual' : $label;
	}
	/** Informe de la exportación junto al SQL del área de trabajo: `database.<tag>.sql.extract.json`. */
	public static function report_path( $sql_path ) { return preg_replace( '/\.part$/', '', (string) $sql_path ) . '.extract.json'; }
	public static function write_report( $path, array $report ) {
		$json = wp_json_encode( $report );
		$temp = $path . '.tmp';
		if ( ! is_string( $json ) || is_link( $path ) || is_link( $temp ) || strlen( $json ) !== @file_put_contents( $temp, $json ) || ! @chmod( $temp, 0600 ) || ! @rename( $temp, $path ) ) {
			@unlink( $temp );
			throw new Exception( 'No se pudo guardar el informe de la exportación del sitio en el área de trabajo.' );
		}
	}
	public static function read_report( $path ) {
		if ( is_link( $path ) || ! is_file( $path ) ) return array();
		$data = json_decode( (string) @file_get_contents( $path ), true );
		return is_array( $data ) ? $data : array();
	}
	public static function is_extract( array $spec ) { return self::SCOPE === ( $spec['scope'] ?? '' ) && is_array( $spec['extract'] ?? null ); }
	public static function policy_id( $site_id ) { return self::POLICY . '-' . (int) $site_id; }

	/* ------------------------------------------------------------------ Comprobaciones */

	/**
	 * Lo que impide extraer el sitio, antes de exportar nada: tablas esenciales, una tabla propia que chocaría con
	 * `wp_N_users`, y los medios en la forma de una red (`ms_files_rewriting`, que guarda los medios en blogs.dir, se
	 * acepta: la carpeta del sitio pasa igual a `uploads/`).
	 */
	public static function problems( array $site ) {
		global $wpdb;
		$problems = array();
		$prefix = $wpdb->get_blog_prefix( $site['id'] );
		$tables = self::site_tables( $site['id'] );
		foreach ( array( 'options', 'posts', 'postmeta' ) as $core ) if ( ! in_array( $prefix . $core, $tables, true ) ) $problems[] = 'Al sitio ' . $site['label'] . ' le falta la tabla ' . $prefix . $core . '.';
		if ( 1 !== (int) $site['id'] ) foreach ( array( 'users', 'usermeta' ) as $own ) if ( in_array( $prefix . $own, $tables, true ) ) $problems[] = 'El sitio ' . $site['label'] . ' tiene una tabla propia ' . $prefix . $own . ' que el paquete usaría para sus usuarios; renómbrala antes de extraerlo.';
		$uploads = self::uploads( $site['id'] );
		$root = trailingslashit( wp_normalize_path( (string) realpath( ABSPATH ) ) );
		if ( is_dir( $uploads['basedir'] ) ) {
			$real = realpath( $uploads['basedir'] );
			if ( false === $real || is_link( $uploads['basedir'] ) || 0 !== strpos( trailingslashit( wp_normalize_path( $real ) ), $root ) ) $problems[] = 'Los medios del sitio ' . $site['label'] . ' (' . $uploads['basedir'] . ') están fuera de WordPress o tras un enlace simbólico.';
		}
		return $problems;
	}
	/** Fase «preflight» del trabajo: los mismos motivos, como error permanente. */
	public static function preflight( array $spec ) {
		$site = self::site( (int) ( $spec['extract']['site_id'] ?? 0 ) );
		if ( is_wp_error( $site ) ) throw new Digitalisimo_Backups_Permanent_Error( $site->get_error_message() );
		foreach ( self::problems( $site ) as $problem ) throw new Digitalisimo_Backups_Permanent_Error( $problem );
		return $site;
	}

	/* ------------------------------------------------------------------ Base de datos */

	/**
	 * Tablas del sitio en la red: las de su prefijo y, en el sitio principal (que comparte el prefijo con la red), sin las
	 * globales ni las de los demás sitios (`wp_<n>_`).
	 */
	public static function site_tables( $site_id ) {
		global $wpdb;
		$base = (string) $wpdb->base_prefix;
		$prefix = (string) $wpdb->get_blog_prefix( $site_id );
		$all = (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $prefix ) . '%' ) );
		$tables = array();
		foreach ( $all as $table ) {
			$table = (string) $table;
			if ( 0 !== strpos( $table, $prefix ) ) continue;
			if ( $prefix === $base ) {
				$rest = substr( $table, strlen( $base ) );
				if ( in_array( $rest, self::GLOBAL_TABLES, true ) || preg_match( '/^[0-9]+_/', $rest ) ) continue;
			}
			$tables[] = $table;
		}
		sort( $tables );
		return $tables;
	}
	/** Tablas de la instalación que no son de ningún sitio ni del núcleo de la red: tablas globales de plugins (no viajan). */
	public static function global_plugin_tables() {
		global $wpdb;
		$base = (string) $wpdb->base_prefix;
		$out = array();
		foreach ( (array) $wpdb->get_col( 'SHOW TABLES' ) as $table ) {
			$table = (string) $table;
			if ( 0 !== strpos( $table, $base ) ) continue;
			$rest = substr( $table, strlen( $base ) );
			if ( in_array( $rest, self::GLOBAL_TABLES, true ) || preg_match( '/^[0-9]+_/', $rest ) ) continue;
			$out[] = $table;
		}
		return $out;
	}
	/** Carpeta y URL de los medios del sitio, como las calcula WordPress dentro del sitio. */
	public static function uploads( $site_id ) {
		$switched = function_exists( 'switch_to_blog' ) && (int) get_current_blog_id() !== (int) $site_id;
		if ( $switched ) switch_to_blog( (int) $site_id );
		try {
			$uploads = wp_upload_dir( null, false );
		} finally {
			if ( $switched ) restore_current_blog();
		}
		return array( 'basedir' => untrailingslashit( wp_normalize_path( (string) ( $uploads['basedir'] ?? '' ) ) ), 'baseurl' => untrailingslashit( (string) ( $uploads['baseurl'] ?? '' ) ) );
	}
	/**
	 * Exportador del sitio para `database_sql_to_file()`: sus tablas, y los usuarios y metas filtrados con el prefijo del
	 * sitio. `report()` dice qué hizo, sin valores ni datos personales.
	 */
	public static function exporter( array $spec ) {
		global $wpdb;
		$site = self::preflight( $spec );
		$tables = self::site_tables( $site['id'] );
		$base = (string) $wpdb->base_prefix;
		$tables[] = $base . 'users';
		$tables[] = $base . 'usermeta';
		return new Digitalisimo_Backups_Site_Extract_Export( $site, $spec['extract'], $tables, self::uploads( $site['id'] ) );
	}

	/* ------------------------------------------------------------------ Usuarios */

	/**
	 * Quién viaja y cómo. Cada usuario incluido conserva todos sus datos y el hash de su contraseña, salvo los autores sin
	 * cuenta en el sitio:
	 * - `member`: tiene `wp_N_capabilities` (WordPress lo da por miembro aunque no tenga rol). Entra con su rol.
	 * - `admin`: superadministrador que no es miembro. Con `managing` (por defecto) entra como administrador sólo si
	 *   administra el sitio: es su correo de administración o es autor de su contenido; con `all`, todos; con `none`,
	 *   ninguno. En la red ya podía hacerlo todo en ese sitio, así que no gana nada que no tuviera, y quien no lo
	 *   administra no se lleva una cuenta con su contraseña a otro servidor.
	 * - `author`: autor de entradas o comentarios del sitio que no es miembro (un usuario retirado del sitio, o un
	 *   superadministrador que no entra como administrador). Entra sin rol ni contraseña, sólo para que su contenido
	 *   conserve el autor: no puede iniciar sesión hasta que un administrador le dé una contraseña.
	 * Devuelve user_id => tipo.
	 */
	public static function users( array $members, array $super_admins, array $authors, array $admin_emails, $mode ) {
		$out = array();
		foreach ( $members as $id ) $out[ (int) $id ] = 'member';
		$authors = array_flip( array_map( 'intval', $authors ) );
		foreach ( $super_admins as $id => $email ) {
			$id = (int) $id;
			if ( isset( $out[ $id ] ) ) continue;
			$manages = isset( $authors[ $id ] ) || in_array( strtolower( (string) $email ), $admin_emails, true );
			if ( 'all' === $mode || ( 'managing' === $mode && $manages ) ) $out[ $id ] = 'admin';
		}
		foreach ( array_keys( $authors ) as $id ) if ( $id > 0 && ! isset( $out[ $id ] ) ) $out[ $id ] = 'author';
		ksort( $out );
		return $out;
	}
	/**
	 * Destino de una meta de usuario en el paquete: su nombre, o null si no viaja. El paquete usa el prefijo del sitio
	 * (`wp_N_`), así que sus metas por sitio quedan tal cual y las de los demás sitios se descartan. Las del sitio principal
	 * (`wp_` sin número) también, cuando no es el que se extrae: las de núcleo siempre (rol, nivel y preferencias), y las
	 * demás cuando el sitio tiene la suya con el mismo nombre. Una meta con el prefijo de la red y sin número que no es de
	 * WordPress viaja: puede ser global de un plugin (`wp_2fa_…`), y nunca da permisos.
	 */
	public static function user_meta_key( $key, $kind, $site_id, $base, array $own_suffixes ) {
		$key = (string) $key;
		if ( in_array( $key, self::NETWORK_USER_META, true ) ) return null;
		if ( 'author' === $kind && in_array( $key, self::CREDENTIAL_USER_META, true ) ) return null;
		if ( '' === $base || 0 !== strpos( $key, $base ) ) return $key;
		$rest = substr( $key, strlen( $base ) );
		if ( preg_match( '/^([0-9]+)_(.+)$/s', $rest, $match ) ) return ( (int) $match[1] === (int) $site_id && 1 !== (int) $site_id ) ? $key : null;
		if ( 1 === (int) $site_id ) return $key;
		if ( in_array( $rest, self::SITE_USER_META, true ) || isset( $own_suffixes[ $rest ] ) ) return null;
		return $key;
	}

	/* ------------------------------------------------------------------ Ajustes */

	/**
	 * Ajustes de red de los plugins DIGITALÍSIMO que el sitio usa, escritos en sus opciones con la misma resolución que
	 * cada plugin (`contenido → sitio → red → default`), para que fuera de la red se comporte igual. Funciona sin los
	 * plugins cargados: lee sus datos. $site y $network son nombre => valor ya leído (sin serializar). Un secreto heredado
	 * de la red (claves de API) sólo se copia con $secrets; si no, queda vacío y se nombra en el informe.
	 * Devuelve array( 'set' => nombre => valor, 'delete' => nombres, 'report' => array( 'materialized', 'secrets' ) ).
	 */
	public static function materialize( array $site, array $network, $secrets ) {
		$set = array();
		$delete = array();
		$materialized = array();
		$withheld = array();
		// SEO (también los ajustes de módulos que Hosting heredó de SEO): una clave heredada toma el valor de la red si la red lo tiene.
		if ( is_array( $network[ self::SEO_OPTION ] ?? null ) && $network[ self::SEO_OPTION ] ) {
			$own = is_array( $site[ self::SEO_OPTION ] ?? null ) ? $site[ self::SEO_OPTION ] : array();
			$inherit = is_array( $site['digitalisimo_seo_network_inherit'] ?? null ) ? $site['digitalisimo_seo_network_inherit'] : array();
			$module = is_array( $site['digitalisimo_module_network_inherit'] ?? null ) ? $site['digitalisimo_module_network_inherit'] : array();
			$count = 0;
			foreach ( $network[ self::SEO_OPTION ] as $key => $value ) {
				if ( in_array( $key, self::SEO_SITE_ONLY, true ) ) continue;
				$map = in_array( $key, self::SEO_MODULE_KEYS, true ) ? $module : $inherit;
				if ( array_key_exists( $key, $map ) && empty( $map[ $key ] ) ) continue;
				if ( in_array( $key, self::SEO_SECRETS, true ) && ! $secrets ) {
					if ( '' !== (string) ( is_scalar( $value ) ? $value : '' ) ) { $withheld[] = self::SEO_OPTION . ':' . $key; $own[ $key ] = ''; }
					continue;
				}
				$own[ $key ] = $value;
				$count++;
			}
			// Reglas de rastreadores IA: «inherit» por rastreador se resolvía con las de la red; fuera de ella sería el valor por defecto.
			if ( is_string( $own['seo_ai_crawler_rules'] ?? null ) ) {
				$rules = json_decode( $own['seo_ai_crawler_rules'], true );
				if ( is_array( $rules ) && in_array( 'inherit', $rules, true ) ) {
					$from = json_decode( (string) ( $network[ self::SEO_OPTION ]['seo_ai_crawler_rules'] ?? '' ), true );
					foreach ( $rules as $id => $state ) {
						if ( 'inherit' !== $state ) continue;
						if ( is_array( $from ) && in_array( $from[ $id ] ?? null, array( 'allow', 'disallow' ), true ) ) $rules[ $id ] = $from[ $id ];
						else unset( $rules[ $id ] );
					}
					$own['seo_ai_crawler_rules'] = wp_json_encode( $rules );
				}
			}
			$set[ self::SEO_OPTION ] = $own;
			$materialized[ self::SEO_OPTION ] = $count;
		}
		// Tools: ruta privada de acceso, redirección de adjuntos y desplazamiento en móvil (heredada salvo que el sitio la personalice).
		$tools_network = $network[ self::TOOLS_NETWORK ] ?? null;
		if ( ! is_array( $tools_network ) && is_array( $network[ self::SEO_OPTION ] ?? null ) ) $tools_network = self::tools_import( $network[ self::SEO_OPTION ] );
		if ( is_array( $tools_network ) ) {
			$net = array_merge( self::TOOLS_DEFAULTS, array_intersect_key( $tools_network, self::TOOLS_DEFAULTS ) );
			$data = $site[ self::TOOLS_SITE ] ?? null;
			if ( ! is_array( $data ) ) {
				// Sin la opción de Tools, Tools la importaba de SEO con la herencia del sitio y la red.
				$legacy = is_array( $site[ self::SEO_OPTION ] ?? null ) ? $site[ self::SEO_OPTION ] : array();
				$map = is_array( $site['digitalisimo_seo_network_inherit'] ?? null ) ? $site['digitalisimo_seo_network_inherit'] : array();
				$net_legacy = is_array( $network[ self::SEO_OPTION ] ?? null ) ? $network[ self::SEO_OPTION ] : array();
				$values = self::tools_import( $legacy );
				$inherit = array();
				foreach ( self::TOOLS_LEGACY_KEYS as $key => $old ) {
					$inherits = ! array_key_exists( $old, $map ) || ! empty( $map[ $old ] );
					if ( $inherits && ! array_key_exists( $old, $net_legacy ) && array_key_exists( $key, $values ) ) $inherits = false;
					$inherit[ $key ] = $inherits;
				}
				$data = array( 'values' => $values, 'inherit' => $inherit );
			}
			$values = array_intersect_key( (array) ( $data['values'] ?? array() ), self::TOOLS_DEFAULTS );
			$inherit = (array) ( $data['inherit'] ?? array() );
			$count = 0;
			foreach ( self::TOOLS_DEFAULTS as $key => $default ) {
				if ( array_key_exists( $key, $inherit ) && empty( $inherit[ $key ] ) ) continue;
				$values[ $key ] = $net[ $key ];
				$count++;
			}
			$set[ self::TOOLS_SITE ] = array( 'values' => $values, 'inherit' => $inherit );
			$materialized[ self::TOOLS_SITE ] = $count;
		}
		// Elements (front): cada interruptor heredado toma el de la red, o el neutro si la red no lo guardó.
		if ( is_array( $network[ self::FRONT_NETWORK ] ?? null ) ) {
			$own = is_array( $site[ self::FRONT_SITE ] ?? null ) ? $site[ self::FRONT_SITE ] : array();
			$mine = is_array( $own['own'] ?? null ) ? array_map( 'strval', $own['own'] ) : array();
			$keys = array_unique( array_merge( array_keys( $network[ self::FRONT_NETWORK ] ), array_diff( array_keys( $own ), array( 'own' ) ) ) );
			$count = 0;
			foreach ( $keys as $key ) {
				if ( 'own' === $key || in_array( (string) $key, $mine, true ) ) continue;
				if ( array_key_exists( $key, $network[ self::FRONT_NETWORK ] ) ) $own[ $key ] = ! empty( $network[ self::FRONT_NETWORK ][ $key ] );
				else unset( $own[ $key ] );
				$count++;
			}
			$set[ self::FRONT_SITE ] = $own;
			$materialized[ self::FRONT_SITE ] = $count;
		}
		// Clave de Google Places de Elements: la del sitio, o la de la red.
		$google_site = is_string( $site[ self::GOOGLE_KEY ] ?? null ) ? trim( $site[ self::GOOGLE_KEY ] ) : '';
		$google_network = is_string( $network[ self::GOOGLE_KEY ] ?? null ) ? trim( $network[ self::GOOGLE_KEY ] ) : '';
		if ( '' === $google_site && '' !== $google_network ) {
			if ( $secrets ) { $set[ self::GOOGLE_KEY ] = $google_network; $materialized[ self::GOOGLE_KEY ] = 1; }
			else $withheld[] = self::GOOGLE_KEY;
		}
		// Hosting e IA Tools heredan todo su ajuste cuando el sitio lo pide (IA, por defecto).
		foreach ( array( self::HOSTING => false, self::AI => true ) as $option => $default_inherit ) {
			$own = is_array( $site[ $option ] ?? null ) ? $site[ $option ] : array();
			$inherits = array_key_exists( 'inherit_network', $own ) ? ! empty( $own['inherit_network'] ) : $default_inherit;
			if ( ! $inherits || ( ! is_array( $network[ $option ] ?? null ) && ! $own ) ) continue;
			$value = is_array( $network[ $option ] ?? null ) ? $network[ $option ] : array();
			if ( ! $secrets ) {
				if ( self::HOSTING === $option ) {
					foreach ( self::HOSTING_SECRETS as $key ) if ( '' !== (string) ( is_scalar( $value[ $key ] ?? null ) ? $value[ $key ] : '' ) ) { $withheld[] = $option . ':' . $key; $value[ $key ] = ''; }
				} else {
					foreach ( (array) ( $value['providers'] ?? array() ) as $provider => $data ) {
						if ( is_array( $data ) && '' !== (string) ( is_scalar( $data['key'] ?? null ) ? $data['key'] : '' ) ) { $withheld[] = $option . ':providers.' . $provider . '.key'; $value['providers'][ $provider ]['key'] = ''; }
					}
				}
			}
			$value['inherit_network'] = $own['inherit_network'] ?? 1;
			$set[ $option ] = $value;
			$materialized[ $option ] = count( $value ) - 1;
		}
		return array( 'set' => $set, 'delete' => $delete, 'report' => array( 'materialized' => $materialized, 'secrets' => $withheld ) );
	}
	private static function tools_import( array $legacy ) {
		$out = array();
		foreach ( self::TOOLS_LEGACY_KEYS as $key => $old ) if ( array_key_exists( $old, $legacy ) ) $out[ $key ] = $legacy[ $old ];
		return $out;
	}
	/** `sitemeta` de otros plugins (no de WordPress ni DIGITALÍSIMO): sólo los nombres, para el informe del paquete. */
	public static function other_network_options( array $keys ) {
		$out = array();
		foreach ( $keys as $key ) {
			$key = (string) $key;
			if ( in_array( $key, self::CORE_SITEMETA, true ) || 0 === strpos( $key, '_site_transient_' ) || 0 === strpos( $key, 'digitalisimo_' ) ) continue;
			$out[] = $key;
		}
		sort( $out );
		return array_values( array_unique( $out ) );
	}

	/* ------------------------------------------------------------------ Archivos */

	/**
	 * Archivos del paquete: origen => nombre en el ZIP (carpetas, recursivas) y lo que no viaja (para el informe). Viajan
	 * el núcleo (sin wp-config.php de la red, que lleva sus credenciales, ni .htaccess con sus reglas de red), los archivos
	 * de wp-content (sin sunrise.php, que sólo existe en una red), mu-plugins, idiomas, los plugins activos (del sitio y de
	 * la red), el tema y su padre, y los medios del sitio, que pasan a `wp-content/uploads/`.
	 */
	public static function file_map( array $report ) {
		$root = trailingslashit( wp_normalize_path( (string) realpath( ABSPATH ) ) );
		$content = wp_normalize_path( (string) realpath( WP_CONTENT_DIR ) );
		$rel_content = 0 === strpos( trailingslashit( $content ), $root ) ? substr( $content, strlen( $root ) ) : 'wp-content';
		$map = array();
		$excluded = array();
		$add = function( $path, $name ) use ( &$map, $root ) {
			$path = wp_normalize_path( $path );
			if ( file_exists( $path ) || is_link( $path ) ) $map[] = array( 'from' => $path, 'to' => 'wordpress/' . ltrim( $name, '/' ) );
		};
		$relative = function( $path ) use ( $root ) {
			$real = realpath( $path );
			$path = wp_normalize_path( false === $real ? $path : $real );
			return 0 === strpos( $path, $root ) ? substr( $path, strlen( $root ) ) : null;
		};
		foreach ( (array) @scandir( $root ) as $name ) {
			if ( '.' === $name || '..' === $name || '' === $name ) continue;
			$path = $root . $name;
			if ( in_array( $name, array( 'wp-admin', 'wp-includes' ), true ) || ( is_file( $path ) && preg_match( '/^(?:index\.php|xmlrpc\.php|license\.txt|readme\.html|wp-(?!config\.php$)[a-z0-9-]+\.php)$/D', $name ) ) ) $add( $path, $name );
			elseif ( $root . $rel_content !== $path ) $excluded[] = $name;
		}
		foreach ( (array) @scandir( $content ) as $name ) {
			if ( '.' === $name || '..' === $name || '' === $name ) continue;
			$path = $content . '/' . $name;
			if ( is_file( $path ) && ! is_link( $path ) && '.php' === substr( $name, -4 ) && 'sunrise.php' !== $name ) $add( $path, $rel_content . '/' . $name );
			elseif ( ! in_array( $name, array( 'plugins', 'themes', 'mu-plugins', 'languages', 'uploads' ), true ) ) $excluded[] = $rel_content . '/' . $name;
		}
		$included_plugins = array();
		foreach ( (array) ( $report['plugins']['active'] ?? array() ) as $plugin ) {
			$plugin = wp_normalize_path( (string) $plugin );
			if ( '' === $plugin || false !== strpos( $plugin, '..' ) || '/' === $plugin[0] ) continue;
			$top = false === strpos( $plugin, '/' ) ? $plugin : substr( $plugin, 0, strpos( $plugin, '/' ) );
			$path = WP_PLUGIN_DIR . '/' . $top;
			$rel = $relative( WP_PLUGIN_DIR );
			if ( null !== $rel && ( is_dir( $path ) || is_file( $path ) ) && ! isset( $included_plugins[ $top ] ) ) { $included_plugins[ $top ] = true; $add( $path, $rel . '/' . $top ); }
		}
		$rel_plugins = $relative( WP_PLUGIN_DIR );
		if ( null !== $rel_plugins ) {
			$add( WP_PLUGIN_DIR . '/index.php', $rel_plugins . '/index.php' );
			foreach ( (array) @scandir( WP_PLUGIN_DIR ) as $name ) if ( '.' !== $name && '..' !== $name && 'index.php' !== $name && ! isset( $included_plugins[ $name ] ) ) $excluded[] = $rel_plugins . '/' . $name;
		}
		$mu = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
		if ( null !== ( $rel = $relative( $mu ) ) ) $add( $mu, $rel );
		$languages = defined( 'WP_LANG_DIR' ) ? WP_LANG_DIR : WP_CONTENT_DIR . '/languages';
		if ( null !== ( $rel = $relative( $languages ) ) ) $add( $languages, $rel );
		$themes = array();
		foreach ( array( 'stylesheet', 'template' ) as $which ) {
			$theme = (string) ( $report['themes'][ $which ] ?? '' );
			if ( '' === $theme || ! preg_match( '/^[A-Za-z0-9._-]+$/D', $theme ) || isset( $themes[ $theme ] ) ) continue;
			$theme_root = function_exists( 'get_theme_root' ) ? get_theme_root( $theme ) : WP_CONTENT_DIR . '/themes';
			if ( null !== ( $rel = $relative( $theme_root ) ) && is_dir( $theme_root . '/' . $theme ) ) { $themes[ $theme ] = true; $add( $theme_root . '/' . $theme, $rel . '/' . $theme ); }
		}
		$theme_root = WP_CONTENT_DIR . '/themes';
		if ( null !== ( $rel = $relative( $theme_root ) ) ) {
			$add( $theme_root . '/index.php', $rel . '/index.php' );
			foreach ( (array) @scandir( $theme_root ) as $name ) if ( '.' !== $name && '..' !== $name && 'index.php' !== $name && ! isset( $themes[ $name ] ) ) $excluded[] = $rel . '/' . $name;
		}
		// Los medios del sitio pasan a la carpeta de un sitio individual; en el principal, sin las carpetas de los demás.
		$basedir = (string) ( $report['uploads']['basedir'] ?? '' );
		$skip = array();
		if ( 1 === (int) ( $report['site']['id'] ?? 0 ) ) $skip[] = wp_normalize_path( $basedir ) . '/sites';
		if ( '' !== $basedir && is_dir( $basedir ) ) $map[] = array( 'from' => wp_normalize_path( $basedir ), 'to' => 'wordpress/' . $rel_content . '/uploads', 'skip' => $skip );
		return array( 'map' => $map, 'excluded' => array_slice( $excluded, 0, self::REPORT_LIMIT ), 'excluded_count' => count( $excluded ) );
	}
	/** Entradas que exige un paquete de este alcance (sin wp-config.php: el del destino nunca se toca). */
	public static function required_entries() { return array( 'database.sql', 'wordpress/wp-load.php', 'wordpress/wp-settings.php', 'wordpress/wp-admin/index.php', 'wordpress/wp-includes/version.php' ); }

	/* ------------------------------------------------------------------ Manifiesto */

	/** Bloque `extract` del manifiesto: lo que el destino y quien restaura necesitan saber, sin valores ni datos personales. */
	public static function manifest_block( array $report ) {
		$block = $report;
		unset( $block['internal'] );
		return $block;
	}

	/* ------------------------------------------------------------------ Restauración */

	/**
	 * La configuración de Backups del destino (destinos, credenciales cifradas con sus salts, automatizaciones, almacén):
	 * describe ese servidor y el paquete no trae la de la red. El historial, el llavero y «Seguridad» tienen su propio
	 * mecanismo. Nombre => valor.
	 */
	public static function destination_snapshot() {
		global $wpdb;
		$names = (array) $wpdb->get_col( $wpdb->prepare( 'SELECT option_name FROM `' . $wpdb->prefix . 'options` WHERE option_name LIKE %s', $wpdb->esc_like( 'digitalisimo_backups_' ) . '%' ) );
		$keep = array();
		foreach ( $names as $name ) {
			$name = (string) $name;
			if ( in_array( $name, array( Digitalisimo_Backups::HISTORY, Digitalisimo_Backups_Security::OPTION ), true ) || 0 === strpos( $name, Digitalisimo_Backups_Keys::SECRET_PREFIX ) ) continue;
			$keep[ $name ] = get_option( $name );
		}
		return $keep;
	}
	/** Lo mismo para el diario de la restauración: sin ningún secreto en claro de los ajustes. */
	public static function journal_snapshot( array $keep ) {
		if ( isset( $keep[ Digitalisimo_Backups::OPTION ] ) && is_array( $keep[ Digitalisimo_Backups::OPTION ] ) ) $keep[ Digitalisimo_Backups::OPTION ] = array_diff_key( $keep[ Digitalisimo_Backups::OPTION ], array_flip( Digitalisimo_Backups_Credentials::FIELDS ) );
		return $keep;
	}
	/** Tras el SQL: vuelve la configuración de Backups del destino y se retira la que no sea suya. */
	public static function reapply( $keep ) {
		if ( ! is_array( $keep ) ) return;
		global $wpdb;
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT option_name FROM `' . $wpdb->prefix . 'options` WHERE option_name LIKE %s', $wpdb->esc_like( 'digitalisimo_backups_' ) . '%' ) ) as $name ) {
			$name = (string) $name;
			if ( ! array_key_exists( $name, $keep ) && ! in_array( $name, array( Digitalisimo_Backups::HISTORY, Digitalisimo_Backups_Security::OPTION ), true ) && 0 !== strpos( $name, Digitalisimo_Backups_Keys::SECRET_PREFIX ) ) delete_option( $name );
		}
		foreach ( $keep as $name => $value ) {
			if ( ! is_string( $name ) || 0 !== strpos( $name, 'digitalisimo_backups_' ) ) continue;
			update_option( $name, $value, Digitalisimo_Backups::CREDENTIALS_CLEAN === $name ? true : false );
		}
	}
	/** Resumen público del bloque `extract` de un manifiesto (verify en REST y WP-CLI); null si no es un sitio extraído. */
	public static function summary( array $manifest ) {
		if ( self::SCOPE !== ( $manifest['scope'] ?? '' ) || ! is_array( $manifest['extract'] ?? null ) ) return null;
		$extract = $manifest['extract'];
		return array(
			'site' => (string) ( $extract['site']['label'] ?? '' ),
			'url' => (string) ( $extract['site']['url'] ?? '' ),
			'table_prefix' => (string) ( $extract['source_prefix'] ?? '' ),
			'users' => (array) ( $extract['users'] ?? array() ),
			'plugins' => array_values( (array) ( $extract['plugins']['active'] ?? array() ) ),
			'theme' => (string) ( $extract['themes']['stylesheet'] ?? '' ),
			'materialized' => (array) ( $extract['settings']['materialized'] ?? array() ),
			'secrets_withheld' => array_values( (array) ( $extract['settings']['secrets_withheld'] ?? array() ) ),
			'network_options' => array_values( (array) ( $extract['network_options'] ?? array() ) ),
			'excluded_tables' => array_values( (array) ( $extract['excluded_tables'] ?? array() ) ),
			'excluded_files' => (int) ( $extract['files']['excluded_count'] ?? 0 ),
		);
	}
	/** Texto para quien restaura: con qué usuarios se entra después. */
	public static function restored_notice( array $manifest ) {
		$users = (array) ( $manifest['extract']['users'] ?? array() );
		return 'Sitio extraído de ' . (string) ( $manifest['extract']['site']['label'] ?? 'una red' ) . ' restaurado: sus usuarios entran con su contraseña de la red' . ( ! empty( $users['authors'] ) ? ' (los autores que no eran miembros no tienen contraseña ni rol)' : '' ) . '; las cuentas que tenía este WordPress ya no existen.';
	}
}

/**
 * Exportador fila a fila de un sitio (lo llama `Digitalisimo_Backups::database_sql_to_file()` dentro de su instantánea):
 * `prepare()` lee lo necesario antes de recorrer las tablas (con `MYSQLI_USE_RESULT` no se puede consultar mientras
 * se lee una tabla) y `rows()`/`finish()` deciden cada fila sin consultar nada.
 */
final class Digitalisimo_Backups_Site_Extract_Export {
	private $site;
	private $options;
	private $tables;
	private $uploads;
	private $base;
	private $prefix;
	private $users = array();
	private $own_suffixes = array();
	private $site_values = array();
	private $network_values = array();
	private $materialized = array( 'set' => array(), 'delete' => array(), 'report' => array( 'materialized' => array(), 'secrets' => array() ) );
	private $written = array();
	private $max_option = 0;
	private $max_meta = 0;
	private $option_columns = array( 'option_id', 'option_name', 'option_value', 'autoload' );
	private $report = array();
	private $active = array();
	private $network_active = array();

	public function __construct( array $site, array $options, array $tables, array $uploads ) {
		global $wpdb;
		$this->site = $site;
		$this->options = $options;
		$this->tables = $tables;
		$this->uploads = $uploads;
		$this->base = (string) $wpdb->base_prefix;
		$this->prefix = (string) $wpdb->get_blog_prefix( $site['id'] );
	}
	public function tables() { return $this->tables; }
	/** Tablas que se leen además de las exportadas (para el bloqueo de lectura sin InnoDB). */
	public function read_tables() { return array( $this->base . 'sitemeta' ); }
	/** Nombre de cada tabla en el paquete: el prefijo del sitio también para usuarios y metas. */
	public function name( $table ) {
		if ( $table === $this->base . 'users' ) return $this->prefix . 'users';
		if ( $table === $this->base . 'usermeta' ) return $this->prefix . 'usermeta';
		return $table;
	}
	public function create( $table, $sql ) {
		$name = $this->name( $table );
		if ( $name === $table ) return $sql;
		return (string) preg_replace( '/^(\s*CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?)`' . preg_quote( str_replace( '`', '``', $table ), '/' ) . '`/i', '${1}`' . str_replace( '`', '``', $name ) . '`', $sql, 1 );
	}
	/** Una sola vez, ya dentro de la instantánea. */
	public function prepare() {
		global $wpdb;
		$users = $this->base . 'users';
		$usermeta = $this->base . 'usermeta';
		$options = $this->prefix . 'options';
		$network_id = (int) $this->site['network_id'];
		// Opciones del sitio que deciden algo (herencia, plugins, tema, medios).
		$rows = (array) $wpdb->get_results( 'SELECT option_name, option_value FROM `' . $options . '` WHERE option_name IN (' . self::in( Digitalisimo_Backups_Site_Extract::SITE_READS ) . ')', ARRAY_A );
		foreach ( $rows as $row ) $this->site_values[ (string) $row['option_name'] ] = self::value( $row['option_value'] );
		$this->max_option = (int) $wpdb->get_var( 'SELECT MAX(option_id) FROM `' . $options . '`' );
		// Ajustes de la red que el sitio usa, y los nombres de los demás `sitemeta` para el informe.
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT meta_key, meta_value FROM `' . $this->base . 'sitemeta` WHERE site_id = %d AND meta_key IN (' . self::in( Digitalisimo_Backups_Site_Extract::NETWORK_READS ) . ')', $network_id ), ARRAY_A );
		foreach ( $rows as $row ) $this->network_values[ (string) $row['meta_key'] ] = self::value( $row['meta_value'] );
		$network_keys = (array) $wpdb->get_col( $wpdb->prepare( 'SELECT meta_key FROM `' . $this->base . 'sitemeta` WHERE site_id = %d', $network_id ) );
		// Usuarios: miembros, superadministradores y autores.
		$members = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT user_id FROM `' . $usermeta . '` WHERE meta_key = %s', $this->prefix . 'capabilities' ) ) );
		$logins = array_values( array_filter( array_map( 'strval', (array) ( $this->network_values['site_admins'] ?? array() ) ), 'strlen' ) );
		$super = array();
		if ( $logins ) foreach ( (array) $wpdb->get_results( 'SELECT ID, user_email FROM `' . $users . '` WHERE user_login IN (' . self::in( $logins ) . ')', ARRAY_A ) as $row ) $super[ (int) $row['ID'] ] = (string) $row['user_email'];
		$authors = array_map( 'intval', (array) $wpdb->get_col( 'SELECT DISTINCT post_author FROM `' . $this->prefix . 'posts` WHERE post_author > 0' ) );
		if ( in_array( $this->prefix . 'comments', $this->tables, true ) ) $authors = array_merge( $authors, array_map( 'intval', (array) $wpdb->get_col( 'SELECT DISTINCT user_id FROM `' . $this->prefix . 'comments` WHERE user_id > 0' ) ) );
		$admin_emails = array_filter( array( strtolower( (string) ( $this->site_values['admin_email'] ?? '' ) ) ), 'strlen' );
		$this->users = Digitalisimo_Backups_Site_Extract::users( $members, $super, array_unique( $authors ), $admin_emails, (string) $this->options['super_admins'] );
		// Metas por sitio que cada usuario ya tiene: la del sitio principal con el mismo nombre no viaja.
		if ( 1 !== (int) $this->site['id'] ) {
			foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT user_id, meta_key FROM `' . $usermeta . '` WHERE meta_key LIKE %s', $wpdb->esc_like( $this->prefix ) . '%' ), ARRAY_A ) as $row ) {
				$this->own_suffixes[ (int) $row['user_id'] ][ substr( (string) $row['meta_key'], strlen( $this->prefix ) ) ] = true;
			}
		}
		$this->max_meta = (int) $wpdb->get_var( 'SELECT MAX(umeta_id) FROM `' . $usermeta . '`' );
		// Plugins: los del sitio, los activados en la red y este mismo, que es quien restaura en el destino.
		$this->active = array_values( array_filter( array_map( 'strval', (array) ( $this->site_values['active_plugins'] ?? array() ) ), 'strlen' ) );
		$this->network_active = array_values( array_filter( array_map( 'strval', array_keys( (array) ( $this->network_values['active_sitewide_plugins'] ?? array() ) ) ), 'strlen' ) );
		$self = defined( 'DIGITALISIMO_BACKUPS_FILE' ) && function_exists( 'plugin_basename' ) ? (string) plugin_basename( DIGITALISIMO_BACKUPS_FILE ) : '';
		$active = array_values( array_unique( array_merge( $this->active, $this->network_active, '' !== $self ? array( $self ) : array() ) ) );
		sort( $active );
		$this->materialized = Digitalisimo_Backups_Site_Extract::materialize( $this->site_values, $this->network_values, ! empty( $this->options['network_secrets'] ) );
		$this->materialized['set']['active_plugins'] = $active;
		foreach ( array( 'upload_path', 'upload_url_path' ) as $name ) if ( '' !== trim( (string) ( is_scalar( $this->site_values[ $name ] ?? null ) ? $this->site_values[ $name ] : '' ) ) ) $this->materialized['set'][ $name ] = '';
		$kinds = array_count_values( $this->users );
		$this->report = array(
			'version' => 1,
			'site' => array( 'id' => (int) $this->site['id'], 'domain' => (string) $this->site['domain'], 'path' => (string) $this->site['path'], 'label' => (string) $this->site['label'], 'url' => (string) $this->site['url'], 'site_url' => untrailingslashit( (string) ( $this->site_values['siteurl'] ?? $this->site['url'] ) ), 'subdomain_install' => ! empty( $this->network_values['subdomain_install'] ), 'ms_files_rewriting' => ! empty( $this->network_values['ms_files_rewriting'] ) ),
			'source_prefix' => $this->prefix,
			'network_prefix' => $this->base,
			'uploads' => array( 'basedir' => $this->uploads['basedir'], 'baseurl' => $this->uploads['baseurl'] ),
			'users' => array( 'policy' => (string) $this->options['super_admins'], 'total' => count( $this->users ), 'members' => (int) ( $kinds['member'] ?? 0 ), 'super_admins' => (int) ( $kinds['admin'] ?? 0 ), 'authors' => (int) ( $kinds['author'] ?? 0 ), 'super_admins_left' => count( array_diff_key( $super, $this->users ) ) ),
			'plugins' => array( 'active' => $active, 'site' => $this->active, 'network' => $this->network_active ),
			'themes' => array( 'stylesheet' => (string) ( $this->site_values['stylesheet'] ?? '' ), 'template' => (string) ( $this->site_values['template'] ?? '' ) ),
			'settings' => array( 'materialized' => $this->materialized['report']['materialized'], 'secrets_withheld' => $this->materialized['report']['secrets'], 'network_secrets' => ! empty( $this->options['network_secrets'] ), 'reset' => array_values( array_intersect( array( 'upload_path', 'upload_url_path' ), array_keys( $this->materialized['set'] ) ) ) ),
			'network_options' => array_slice( Digitalisimo_Backups_Site_Extract::other_network_options( $network_keys ), 0, Digitalisimo_Backups_Site_Extract::REPORT_LIMIT ),
			'excluded_tables' => array_slice( Digitalisimo_Backups_Site_Extract::global_plugin_tables(), 0, Digitalisimo_Backups_Site_Extract::REPORT_LIMIT ),
		);
	}
	public function report() { return $this->report; }
	/** Filas del paquete para una fila de origen (ninguna si no viaja). */
	public function rows( $table, array $row ) {
		if ( $table === $this->base . 'users' ) {
			$id = (int) ( $row['ID'] ?? 0 );
			if ( ! isset( $this->users[ $id ] ) ) return array();
			// Un autor que no era miembro no se lleva su contraseña: su contenido conserva el autor, pero no puede entrar.
			if ( 'author' === $this->users[ $id ] ) { $row['user_pass'] = ''; if ( array_key_exists( 'user_activation_key', $row ) ) $row['user_activation_key'] = ''; }
			return array( $row );
		}
		if ( $table === $this->base . 'usermeta' ) {
			$id = (int) ( $row['user_id'] ?? 0 );
			if ( ! isset( $this->users[ $id ] ) ) return array();
			$key = Digitalisimo_Backups_Site_Extract::user_meta_key( (string) ( $row['meta_key'] ?? '' ), $this->users[ $id ], (int) $this->site['id'], $this->base, $this->own_suffixes[ $id ] ?? array() );
			if ( null === $key ) return array();
			$row['meta_key'] = $key;
			return array( $row );
		}
		if ( $table === $this->prefix . 'options' ) {
			$name = (string) ( $row['option_name'] ?? '' );
			$this->option_columns = array_keys( $row );
			// La configuración de Backups de la red (destinos, historial, automatizaciones) no es del sitio.
			if ( 0 === strpos( $name, 'digitalisimo_backups_' ) || in_array( $name, $this->materialized['delete'], true ) ) return array();
			if ( array_key_exists( $name, $this->materialized['set'] ) ) {
				$this->written[ $name ] = true;
				$row['option_value'] = self::store( $this->materialized['set'][ $name ] );
			}
			return array( $row );
		}
		return array( $row );
	}
	/** Filas nuevas al final de una tabla: ajustes materializados que el sitio no tenía y el rol de los superadministradores. */
	public function finish( $table ) {
		$out = array();
		if ( $table === $this->prefix . 'options' ) {
			foreach ( $this->materialized['set'] as $name => $value ) {
				if ( isset( $this->written[ $name ] ) || in_array( $name, array( 'upload_path', 'upload_url_path' ), true ) ) continue;
				$row = array();
				foreach ( $this->option_columns as $column ) $row[ $column ] = null;
				$row['option_id'] = (string) ++$this->max_option;
				$row['option_name'] = $name;
				$row['option_value'] = self::store( $value );
				if ( array_key_exists( 'autoload', $row ) ) $row['autoload'] = 'yes';
				$out[] = $row;
			}
		}
		if ( $table === $this->base . 'usermeta' ) {
			foreach ( $this->users as $id => $kind ) {
				if ( 'admin' !== $kind ) continue;
				$out[] = array( 'umeta_id' => (string) ++$this->max_meta, 'user_id' => (string) $id, 'meta_key' => $this->prefix . 'capabilities', 'meta_value' => serialize( array( 'administrator' => true ) ) );
				$out[] = array( 'umeta_id' => (string) ++$this->max_meta, 'user_id' => (string) $id, 'meta_key' => $this->prefix . 'user_level', 'meta_value' => '10' );
			}
		}
		return $out;
	}

	/** Valor de una opción leída de esta misma base de datos, sin instanciar objetos. */
	public static function value( $raw ) {
		if ( ! is_string( $raw ) ) return $raw;
		if ( function_exists( 'is_serialized' ) ? ! is_serialized( $raw ) : ! preg_match( '/^(?:a|s|i|d|b|N|O|C):/', $raw ) ) return $raw;
		$value = @unserialize( $raw, array( 'allowed_classes' => false ) );
		return false === $value && 'b:0;' !== $raw ? $raw : $value;
	}
	/** Como maybe_serialize(): matrices serializadas; un escalar, como texto (true es «1»). */
	private static function store( $value ) { return is_array( $value ) || is_object( $value ) ? serialize( $value ) : (string) $value; }
	private static function in( array $values ) {
		global $wpdb;
		return implode( ', ', array_map( function( $value ) use ( $wpdb ) { return $wpdb->prepare( '%s', (string) $value ); }, $values ) );
	}
}

// Ganchos propios, al cargar el archivo: así el alta no toca Digitalisimo_Backups::init() ni el motor de la cola.
if ( function_exists( 'add_action' ) ) add_action( 'admin_post_' . Digitalisimo_Backups_Site_Extract::ACTION, array( 'Digitalisimo_Backups', 'extract_request' ) );
if ( function_exists( 'add_filter' ) ) add_filter( 'digitalisimo_backups_job_origin', array( 'Digitalisimo_Backups_Site_Extract', 'job_origin' ), 10, 2 );
