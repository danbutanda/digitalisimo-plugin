<?php
defined( 'ABSPATH' ) || exit;

/**
 * Importar un WordPress individual en una red (pedido del propietario del 2026-11-16: «déjalo igual que UpdraftPlus, sin
 * importar multisite o WP normal»). Un respaldo de un sitio individual, un sitio extraído de una red o la copia de un sitio
 * de una red (alcance `network-site`, la copia previa de un reemplazo) se restaura en una red desde su administración, con
 * el alcance de red explícito: **como sitio nuevo de la red** (por defecto, en la dirección que se elige) o **reemplazando
 * un sitio** que no es el principal. Es la restauración por pasos de siempre (Digitalisimo_Backups_Restore_Engine) con la
 * especificación `into` y estas reglas (docs/BACKUPS.md, «Importar un sitio en una red»):
 *
 * - Tablas: las del respaldo pasan al prefijo del sitio (`<base>N_`) con el motor de E1 (también las URL y las rutas, en
 *   datos serializados); `users` y `usermeta` del respaldo no se cambian por las de la red: se funden en ellas.
 * - Usuarios: un usuario del respaldo con el mismo nombre de usuario y el mismo correo que uno de la red es ese usuario;
 *   con el mismo correo y otro nombre, también (el correo identifica a la persona en WordPress); con el mismo nombre y otro
 *   correo es otra persona: entra con un nombre libre (`ana-2`) y su contraseña. Ningún usuario de la red pierde su
 *   contraseña, su correo, sus metas globales ni su rol en otro sitio; los administradores del sitio que se reemplaza
 *   siguen siéndolo y los superadministradores no cambian. Los roles del respaldo pasan a `<base>N_capabilities`, y la
 *   autoría (entradas, comentarios, enlaces, metas y tablas de WooCommerce) se reasigna a los ID de la red.
 * - Archivos: los medios pasan a `uploads/sites/N`; los plugins y temas del respaldo que faltan en la red se instalan
 *   (los que ya están, con otra versión, no se reemplazan: se avisa), el tema del sitio se habilita para ese sitio, y el
 *   núcleo, los mu-plugins, los drop-ins, los idiomas y el resto de wp-content nunca se mezclan en la red (se avisa).
 * - Copia previa: al reemplazar, sólo la de ese sitio (sus tablas, las metas de sus usuarios en ese sitio y sus medios);
 *   un sitio nuevo no existía: si la importación falla tras empezar el cambio, se borra con los usuarios que creó.
 * - Mantenimiento: sólo el sitio que cambia (el resto de la red sigue sirviéndose).
 *
 * Esta clase decide y aplica sobre la base de datos; el motor la llama en cada fase. Mejor que UpdraftPlus, que sólo
 * importa como sitio nuevo (Premium), descarta `users` y `usermeta` del respaldo (la autoría queda en otros ID), escribe
 * las tablas sin copia previa ni cambio atómico y reemplaza los plugins de la red con los del respaldo.
 */
final class Digitalisimo_Backups_Site_Import {
	// La copia de un sitio de una red (copia previa de un reemplazo): sus tablas, sus usuarios con metas del sitio y sus
	// medios, en la forma de un sitio extraído (medios en wp-content/uploads). Sólo se restaura en una red, sobre un sitio.
	const SCOPE = 'network-site';
	const FILE_PREFIX = 'site-copy-';
	const POLICY = 'pre-import';
	// Columnas con el ID de un usuario en tablas del sitio (sin prefijo): se reasignan a los ID de la red.
	const USER_COLUMNS = array(
		'posts' => array( 'post_author' ),
		'comments' => array( 'user_id' ),
		'links' => array( 'link_owner' ),
		'wc_orders' => array( 'customer_id' ),
		'wc_customer_lookup' => array( 'user_id' ),
		'woocommerce_payment_tokens' => array( 'user_id' ),
		'woocommerce_downloadable_product_permissions' => array( 'user_id' ),
		'wc_download_log' => array( 'user_id' ),
		'woocommerce_api_keys' => array( 'user_id' ),
		'wc_webhooks' => array( 'user_id' ),
	);
	// Metas de entradas con un ID de usuario (las de bloqueo de edición, `_edit_lock`, se retiran: caducan solas).
	const USER_POSTMETA = array( '_edit_last', '_customer_user' );
	// Metas de usuario que no viajan a la red: las sesiones del origen (sus cookies son de otro dominio).
	const DROP_USER_META = array( 'session_tokens' );
	// Opciones del sitio que nunca vienen del respaldo: la carpeta de medios es la del sitio de la red.
	const KEEP_OPTIONS = array( 'upload_path', 'upload_url_path', 'fileupload_url' );
	const MARK = 'dgbk-u:';

	// ------------------------------------------------------------------ Paquetes

	// Un paquete que se importa como sitio de una red: un WordPress individual, un sitio extraído o la copia de un sitio.
	public static function is_site_package( array $data ) {
		$scope = (string) ( $data['scope'] ?? '' );
		if ( self::SCOPE === $scope || Digitalisimo_Backups_Site_Extract::SCOPE === $scope ) return true;
		return 'installation' === $scope && empty( $data['multisite'] );
	}
	// Se importa aquí: esta instalación es una red y el paquete es de un sitio.
	public static function applies( array $data ) { return is_multisite() && self::is_site_package( $data ); }
	// Una fila del historial (o subida) que se importa aquí.
	public static function row_applies( array $row ) {
		if ( ! is_multisite() ) return false;
		$scope = (string) ( $row['scope'] ?? '' );
		return in_array( $scope, array( 'installation', self::SCOPE, Digitalisimo_Backups_Site_Extract::SCOPE ), true );
	}
	// El paquete exige el núcleo (un WordPress individual o un sitio extraído) o sólo su base de datos (copia de un sitio).
	public static function required_entries( array $data ) {
		if ( self::SCOPE === ( $data['scope'] ?? '' ) ) return array( 'database.sql' );
		return Digitalisimo_Backups_Site_Extract::SCOPE === ( $data['scope'] ?? '' ) ? Digitalisimo_Backups_Site_Extract::required_entries() : array( 'database.sql', 'wordpress/wp-load.php', 'wordpress/wp-settings.php', 'wordpress/wp-admin/index.php', 'wordpress/wp-includes/version.php' );
	}

	// ------------------------------------------------------------------ Petición

	// Lo pedido (`into`: `new` o el ID de un sitio; `address`: la dirección del sitio nuevo) normalizado contra esta red, o
	// WP_Error. Sin `into`, como sitio nuevo en la dirección sugerida desde el respaldo ($home). Devuelve array( mode,
	// site_id, domain, path, url, label ).
	public static function request( array $args, $home = '' ) {
		if ( ! is_multisite() ) return new WP_Error( 'digitalisimo_backups_into', 'Esta instalación no es una red: el respaldo se restaura entero.', array( 'status' => 400 ) );
		$into = strtolower( trim( (string) ( $args['into'] ?? '' ) ) );
		if ( '' === $into || 'new' === $into || 'nuevo' === $into ) {
			$address = trim( (string) ( $args['address'] ?? '' ) );
			if ( '' === $address ) $address = self::suggest( $home );
			return self::new_target( $address );
		}
		if ( ! preg_match( '/^[0-9]+$/D', $into ) ) return new WP_Error( 'digitalisimo_backups_into', 'Elige cómo importarlo: into=new (como sitio nuevo de la red) o el número del sitio que se reemplaza.', array( 'status' => 400 ) );
		return self::existing_target( (int) $into );
	}
	// Un sitio de la red que se puede reemplazar: existe, es de la red principal y no es el sitio principal.
	public static function existing_target( $site_id ) {
		if ( (int) $site_id === (int) get_main_site_id() ) return new WP_Error( 'digitalisimo_backups_into_main', 'El sitio principal de la red no se reemplaza con el respaldo de un sitio: sus tablas y sus medios son los de la red. Impórtalo como sitio nuevo o elige otro sitio.', array( 'status' => 400 ) );
		$site = self::site_row( $site_id );
		if ( ! $site ) return new WP_Error( 'digitalisimo_backups_into_site', 'El sitio ' . (int) $site_id . ' no existe en esta red.', array( 'status' => 404 ) );
		if ( function_exists( 'get_main_network_id' ) && (int) $site->site_id !== (int) get_main_network_id() ) return new WP_Error( 'digitalisimo_backups_into_site', 'El sitio ' . (int) $site_id . ' es de otra red de esta instalación.', array( 'status' => 400 ) );
		$domain = strtolower( (string) $site->domain );
		$path = self::slash( (string) $site->path );
		return array( 'mode' => 'replace', 'site_id' => (int) $site_id, 'domain' => $domain, 'path' => $path, 'url' => self::site_url( $domain, $path, (int) $site_id ), 'label' => rtrim( $domain . $path, '/' ) );
	}
	// La dirección de un sitio nuevo: un nombre (`tienda`) que en una red por subdirectorios es la ruta (`red.test/tienda/`)
	// y en una por subdominios el subdominio (`tienda.red.test`). Como «Añadir sitio» de la red: letras, números y guiones,
	// sin los nombres reservados ni una dirección que ya exista.
	public static function new_target( $address ) {
		$slug = self::slug( $address );
		if ( '' === $slug ) return new WP_Error( 'digitalisimo_backups_address', 'Escribe la dirección del sitio nuevo: letras, números y guiones (por ejemplo, tienda).', array( 'status' => 400 ) );
		if ( strlen( $slug ) > 63 ) return new WP_Error( 'digitalisimo_backups_address', 'La dirección del sitio nuevo admite 63 caracteres como mucho.', array( 'status' => 400 ) );
		$network = self::network();
		$subdomain = function_exists( 'is_subdomain_install' ) && is_subdomain_install();
		if ( ! $subdomain && in_array( $slug, self::reserved(), true ) ) return new WP_Error( 'digitalisimo_backups_address', '«' . $slug . '» es un nombre reservado de WordPress en una red por subdirectorios: elige otro.', array( 'status' => 400 ) );
		$domain = $subdomain ? $slug . '.' . preg_replace( '/^www\./', '', $network['domain'] ) : $network['domain'];
		$path = $subdomain ? $network['path'] : $network['path'] . $slug . '/';
		if ( self::taken( $domain, $path ) ) return new WP_Error( 'digitalisimo_backups_address_taken', 'Ya hay un sitio en ' . rtrim( $domain . $path, '/' ) . ': elige otra dirección o reemplaza ese sitio.', array( 'status' => 409 ) );
		return array( 'mode' => 'new', 'site_id' => 0, 'domain' => $domain, 'path' => $path, 'url' => self::site_url( $domain, $path, 0 ), 'label' => rtrim( $domain . $path, '/' ), 'slug' => $slug );
	}
	// Dirección sugerida desde el respaldo: el primer nombre de su dominio (`www.mitienda.com` → `mitienda`) o el final de
	// su ruta (`ejemplo.com/blog` → `blog`); libre en esta red (`-2`, `-3`…).
	public static function suggest( $home ) {
		$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( (string) $home ) : parse_url( (string) $home );
		$path = trim( (string) ( $parts['path'] ?? '' ), '/' );
		$host = preg_replace( '/^www\./', '', strtolower( (string) ( $parts['host'] ?? '' ) ) );
		$base = '' !== $path ? self::slug( basename( $path ) ) : ( filter_var( $host, FILTER_VALIDATE_IP ) || 'localhost' === $host ? '' : self::slug( (string) strtok( $host, '.' ) ) );
		if ( '' === $base || ( ! ( function_exists( 'is_subdomain_install' ) && is_subdomain_install() ) && in_array( $base, self::reserved(), true ) ) ) $base = 'importado';
		$base = substr( $base, 0, 56 );
		for ( $n = 1; $n < 100; $n++ ) {
			$candidate = 1 === $n ? $base : $base . '-' . $n;
			if ( ! is_wp_error( self::new_target( $candidate ) ) ) return $candidate;
		}
		return $base . '-' . substr( bin2hex( random_bytes( 3 ) ), 0, 6 );
	}
	// Sitios que se pueden reemplazar (para la confirmación): ID, dirección y si es el principal (no se ofrece).
	public static function sites( $limit = 200 ) {
		global $wpdb;
		if ( ! is_multisite() ) return array();
		$out = array();
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT blog_id, domain, path FROM ' . Digitalisimo_Backups_Relocate::quote_name( $wpdb->base_prefix . 'blogs' ) . ' WHERE site_id = %d AND deleted = 0 ORDER BY blog_id LIMIT %d', function_exists( 'get_main_network_id' ) ? (int) get_main_network_id() : 1, (int) $limit ), ARRAY_A );
		foreach ( $rows as $site ) {
			if ( ! is_array( $site ) || ! isset( $site['blog_id'], $site['domain'], $site['path'] ) ) continue;
			$id = (int) $site['blog_id'];
			if ( $id <= 0 || $id === (int) get_main_site_id() ) continue;
			$out[ $id ] = rtrim( strtolower( (string) $site['domain'] ) . self::slash( (string) $site['path'] ), '/' );
		}
		return $out;
	}
	// La fila del sitio en `blogs` (blog_id, site_id, domain, path), o null: lo que dice la base de datos.
	public static function site_row( $site_id ) {
		global $wpdb;
		$site_id = (int) $site_id;
		if ( $site_id <= 0 ) return null;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT blog_id, site_id, domain, path FROM ' . Digitalisimo_Backups_Relocate::quote_name( $wpdb->base_prefix . 'blogs' ) . ' WHERE blog_id = %d', $site_id ), ARRAY_A );
		return is_array( $row ) && isset( $row['blog_id'], $row['domain'] ) && (int) $row['blog_id'] === $site_id ? (object) ( $row + array( 'site_id' => 1, 'path' => '/' ) ) : null;
	}
	private static function slug( $text ) {
		$text = strtolower( trim( (string) $text ) );
		$text = preg_replace( '#^[a-z]+://#', '', $text );
		$text = trim( (string) preg_replace( '#[/\s]+#', '-', trim( $text, '/' ) ), '-' );
		return preg_match( '/^[a-z0-9-]+$/D', $text ) ? $text : '';
	}
	private static function reserved() {
		$names = function_exists( 'get_subdirectory_reserved_names' ) ? (array) get_subdirectory_reserved_names() : array( 'page', 'comments', 'blog', 'files', 'feed', 'wp-admin', 'wp-content', 'wp-includes', 'wp-json', 'embed' );
		return array_map( 'strtolower', array_map( 'strval', $names ) );
	}
	private static function taken( $domain, $path ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT blog_id FROM ' . Digitalisimo_Backups_Relocate::quote_name( $wpdb->base_prefix . 'blogs' ) . ' WHERE domain = %s AND path = %s', $domain, $path ) );
	}
	private static function network() {
		$network = function_exists( 'get_network' ) ? get_network() : null;
		$domain = $network ? strtolower( (string) $network->domain ) : strtolower( (string) parse_url( (string) home_url(), PHP_URL_HOST ) );
		return array( 'domain' => $domain, 'path' => self::slash( $network ? (string) $network->path : '/' ) );
	}
	// URL de un sitio de la red con el esquema del sitio principal; la de un sitio que existe, la suya.
	private static function site_url( $domain, $path, $site_id ) {
		if ( $site_id > 0 && function_exists( 'get_home_url' ) ) {
			$home = untrailingslashit( (string) get_home_url( $site_id ) );
			if ( '' !== $home ) return $home;
		}
		$scheme = (string) ( parse_url( (string) ( function_exists( 'get_home_url' ) ? get_home_url( get_main_site_id() ) : home_url() ), PHP_URL_SCHEME ) ?: 'http' );
		return untrailingslashit( $scheme . '://' . $domain . $path );
	}
	private static function slash( $path ) { $path = '/' . trim( str_replace( '\\', '/', (string) $path ), '/' ); return '/' === $path ? '/' : $path . '/'; }

	// ------------------------------------------------------------------ Destino y plan (motor de E1)

	// El sitio de destino descrito como el WordPress individual que recibe el paquete (para Digitalisimo_Backups_Relocate):
	// su dirección, el prefijo de sus tablas y su carpeta de medios. $target lleva `site_id` (el de un sitio nuevo, una vez
	// creado; antes, el previsto).
	public static function destination( array $target ) {
		global $wpdb;
		$id = (int) $target['site_id'];
		$uploads = self::uploads( $id, $target );
		$out = Digitalisimo_Backups_Relocate::describe( false );
		unset( $out['network'], $out['networks'], $out['sites'], $out['config'] );
		$out['home'] = untrailingslashit( (string) $target['url'] );
		$out['siteurl'] = untrailingslashit( (string) ( $target['siteurl'] ?? $target['url'] ) );
		$out['uploads_dir'] = $uploads['basedir'];
		$out['prefix'] = (string) $wpdb->base_prefix . $id . '_';
		$out['multisite'] = false;
		$out['subdomain'] = false;
		return $out;
	}
	// Carpeta y URL de los medios del sitio $id: la de WordPress dentro del sitio; para uno que aún no existe, la que tendrá
	// (`uploads/sites/N`). La URL es la que WordPress escribe al subir desde el propio sitio (con su dirección).
	public static function uploads( $id, array $target = array() ) {
		$id = (int) $id;
		$exists = null !== self::site_row( $id ) && function_exists( 'switch_to_blog' );
		if ( $exists ) {
			$uploads = Digitalisimo_Backups_Site_Extract::uploads( $id );
		} else {
			$main = function_exists( 'wp_upload_dir' ) ? (array) wp_upload_dir( null, false ) : array();
			$basedir = untrailingslashit( wp_normalize_path( (string) ( $main['basedir'] ?? WP_CONTENT_DIR . '/uploads' ) ) );
			$uploads = array( 'basedir' => $basedir . '/sites/' . $id, 'baseurl' => untrailingslashit( (string) ( $main['baseurl'] ?? '' ) ) . '/sites/' . $id );
		}
		$content = untrailingslashit( wp_normalize_path( (string) WP_CONTENT_DIR ) );
		$root = untrailingslashit( wp_normalize_path( (string) ABSPATH ) );
		$url = untrailingslashit( (string) ( $target['siteurl'] ?? $target['url'] ?? '' ) );
		if ( '' !== $url && 0 === strpos( $uploads['basedir'] . '/', $content . '/' ) && 0 === strpos( $content . '/', $root . '/' ) ) $uploads['baseurl'] = $url . substr( $uploads['basedir'], strlen( $root ) );
		return function_exists( 'apply_filters' ) ? (array) apply_filters( 'digitalisimo_backups_site_import_uploads', $uploads, $id ) : $uploads;
	}
	// URL de los medios del origen que el plan de E1 no deduce de `home` y `siteurl`: la carpeta de medios de un WordPress
	// individual (`<siteurl>/wp-content/uploads`, también con `home`), la de un sitio extraído en su red (y sus alias) o la
	// de la copia de un sitio. Todas pasan a la URL de los medios del sitio de la red. Lista de array( desde, hacia ).
	public static function media_pairs( array $data, array $origin, array $uploads ) {
		$to = untrailingslashit( (string) $uploads['baseurl'] );
		if ( '' === $to ) return array();
		$from = array();
		$scope = (string) ( $data['scope'] ?? '' );
		if ( Digitalisimo_Backups_Site_Extract::SCOPE === $scope && is_array( $data['extract'] ?? null ) ) {
			$from[] = (string) ( $data['extract']['uploads']['baseurl'] ?? '' );
			foreach ( (array) ( $data['extract']['uploads']['aliases'] ?? array() ) as $alias ) $from[] = (string) $alias;
		} elseif ( self::SCOPE === $scope && is_array( $data['site_copy'] ?? null ) ) {
			$from[] = (string) ( $data['site_copy']['uploads']['baseurl'] ?? '' );
		}
		// De un WordPress individual: su carpeta de medios bajo la raíz, con su URL (siteurl y home).
		$abspath = untrailingslashit( (string) ( $origin['abspath'] ?? '' ) );
		$dir = untrailingslashit( (string) ( $origin['uploads_dir'] ?? '' ) );
		$tail = '' !== $abspath && '' !== $dir && 0 === strpos( $dir . '/', $abspath . '/' ) ? substr( $dir, strlen( $abspath ) ) : '/wp-content/uploads';
		if ( Digitalisimo_Backups_Site_Extract::SCOPE !== $scope && self::SCOPE !== $scope ) foreach ( array( 'siteurl', 'home' ) as $key ) if ( '' !== (string) ( $origin[ $key ] ?? '' ) ) $from[] = untrailingslashit( (string) $origin[ $key ] ) . $tail;
		$pairs = array();
		foreach ( $from as $url ) {
			$url = untrailingslashit( $url );
			if ( '' !== $url && preg_match( '#^https?://#i', $url ) && $url !== $to && ! isset( $pairs[ $url ] ) ) $pairs[ $url ] = array( $url, $to );
		}
		return array_values( $pairs );
	}
	// El plan de E1 del paquete hacia el sitio de la red, con los pares de los medios (que van antes que `home`: la ruta más
	// larga del mismo host manda) y sin vaciar las cachés de página de toda la red.
	public static function plan( array $source, array $destination, array $args, array $pairs ) {
		$args['addresses'] = 'current';
		$plan = Digitalisimo_Backups_Relocate::plan( $source, $destination, $args );
		foreach ( $pairs as $pair ) $plan->add_url( (string) $pair[0], (string) $pair[1] );
		if ( method_exists( $plan, 'site_scope' ) ) $plan->site_scope();
		return $plan;
	}

	// ------------------------------------------------------------------ Archivos

	// Qué archivos del paquete van a la red y dónde, con el inventario del ZIP (decidido al verificar, con la red intacta):
	// - los medios (`<contenido>/uploads/…` del paquete) → la carpeta de medios del sitio de la red;
	// - cada plugin y cada tema del respaldo que falta en la red → su carpeta (los que ya están no se tocan: si su versión
	//   es otra, se avisa);
	// - nada más: el núcleo, mu-plugins, drop-ins, idiomas, el resto de wp-content y los archivos de la raíz se cuentan.
	// $origin: la descripción del origen (rutas). $layout: raíces de esta instalación (Digitalisimo_Backups::restore_layout()).
	public static function file_rules( $zip, array $data, array $origin, array $layout, $uploads_dir ) {
		$root = rtrim( (string) $layout['root'], '/' ) . '/';
		$abspath = untrailingslashit( (string) ( $origin['abspath'] ?? '' ) );
		$content = untrailingslashit( (string) ( $origin['content_dir'] ?? '' ) );
		$from_content = '' !== $abspath && '' !== $content && 0 === strpos( $content . '/', $abspath . '/' ) ? substr( $content, strlen( $abspath ) + 1 ) : 'wp-content';
		$dir = untrailingslashit( (string) ( $origin['uploads_dir'] ?? '' ) );
		// Un sitio extraído o la copia de un sitio llevan sus medios en <contenido>/uploads (los pasó ahí su exportación).
		$from_uploads = self::is_site_package( $data ) && 'installation' === ( $data['scope'] ?? '' ) && '' !== $abspath && '' !== $dir && 0 === strpos( $dir . '/', $abspath . '/' ) ? substr( $dir, strlen( $abspath ) + 1 ) : $from_content . '/uploads';
		$to_uploads = 0 === strpos( wp_normalize_path( (string) $uploads_dir ) . '/', $root ) ? rtrim( substr( wp_normalize_path( (string) $uploads_dir ), strlen( $root ) ), '/' ) : '';
		$rules = array( 'from_uploads' => $from_uploads, 'to_uploads' => $to_uploads, 'units' => array(), 'skipped' => array(), 'kept' => array(), 'installed' => array( 'plugins' => array(), 'themes' => array() ), 'mu_plugins' => array(), 'drop_ins' => array(), 'versions' => array() );
		$kinds = array( 'plugins' => array( $from_content . '/plugins', (string) $layout['plugins'] ), 'themes' => array( $from_content . '/themes', (string) $layout['themes'] ) );
		$seen = array();
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$name = str_replace( '\\', '/', (string) $zip->getNameIndex( $i ) );
			if ( 0 !== strpos( $name, 'wordpress/' ) || '/' === substr( $name, -1 ) ) continue;
			$relative = substr( $name, strlen( 'wordpress/' ) );
			if ( '' !== $to_uploads && 0 === strpos( $relative, $from_uploads . '/' ) ) continue;
			$what = 'core';
			foreach ( $kinds as $kind => $pair ) {
				if ( '' === $pair[1] || 0 !== strpos( $relative, $pair[0] . '/' ) ) continue;
				$rest = substr( $relative, strlen( $pair[0] ) + 1 );
				$unit = false === strpos( $rest, '/' ) ? $rest : substr( $rest, 0, (int) strpos( $rest, '/' ) );
				if ( 'index.php' === $unit || ! Digitalisimo_Backups_Site_Extract::safe_name( $unit ) ) { $what = 'skip'; break; }
				$key = $kind . '/' . $unit;
				if ( ! isset( $seen[ $key ] ) ) {
					$live = $root . $pair[1] . '/' . $unit;
					$seen[ $key ] = file_exists( $live ) || is_link( $live ) ? 'kept' : 'install';
					if ( 'install' === $seen[ $key ] ) {
						$rules['units'][ $pair[1] . '/' . $unit ] = $pair[0] . '/' . $unit;
						$rules['installed'][ $kind ][] = $unit;
					} else {
						$rules['kept'][ $kind ][] = $unit;
						$version = self::version_difference( $zip, $kind, $pair[0] . '/' . $unit, $live );
						if ( $version ) $rules['versions'][] = $version + array( 'kind' => $kind, 'name' => $unit );
					}
				}
				$what = 'unit';
				break;
			}
			if ( 'unit' === $what || 'skip' === $what ) continue;
			if ( 0 === strpos( $relative, $from_content . '/mu-plugins/' ) ) {
				$mu = strtok( substr( $relative, strlen( $from_content ) + 12 ), '/' );
				if ( count( $rules['mu_plugins'] ) < 50 && ! in_array( $mu, $rules['mu_plugins'], true ) ) $rules['mu_plugins'][] = $mu;
				$what = 'mu-plugins';
			} elseif ( 0 === strpos( $relative, $from_content . '/languages/' ) ) {
				$what = 'languages';
			} elseif ( 0 === strpos( $relative, $from_content . '/' ) ) {
				$rest = substr( $relative, strlen( $from_content ) + 1 );
				if ( false === strpos( $rest, '/' ) && in_array( $rest, Digitalisimo_Backups_Site_Extract::DROP_INS, true ) ) { $rules['drop_ins'][] = $rest; $what = 'drop-ins'; }
				else $what = 'content';
			}
			$rules['skipped'][ $what ] = (int) ( $rules['skipped'][ $what ] ?? 0 ) + 1;
		}
		foreach ( array( 'plugins', 'themes' ) as $kind ) { sort( $rules['installed'][ $kind ] ); if ( isset( $rules['kept'][ $kind ] ) ) sort( $rules['kept'][ $kind ] ); }
		return $rules;
	}
	// Versión del plugin o el tema del respaldo frente a la de la red, si es otra (se avisa; el de la red no se toca).
	private static function version_difference( $zip, $kind, $unit, $live ) {
		$read = function( $text ) { return preg_match( '/^[ \t\/*#@]*Version:(.*)$/mi', (string) $text, $m ) ? trim( preg_replace( '/\s*(?:\*\/|\?>).*$/', '', $m[1] ) ) : ''; };
		$files = array();
		if ( 'themes' === $kind ) $files[] = 'style.css';
		elseif ( is_file( $live ) ) $files[] = '';
		elseif ( is_dir( $live ) ) foreach ( (array) @scandir( $live ) as $name ) if ( '.php' === substr( (string) $name, -4 ) ) $files[] = (string) $name;
		foreach ( array_slice( $files, 0, 20 ) as $file ) {
			$path = '' === $file ? $live : $live . '/' . $file;
			$here = is_file( $path ) ? (string) @file_get_contents( $path, false, null, 0, 8192 ) : '';
			if ( 'plugins' === $kind && false === stripos( $here, 'Plugin Name:' ) ) continue;
			$theirs = @$zip->getFromName( 'wordpress/' . $unit . ( '' === $file ? '' : '/' . $file ), 8192 );
			if ( ! is_string( $theirs ) ) return null;
			$a = $read( $theirs );
			$b = $read( $here );
			return '' !== $a && $a !== $b ? array( 'backup' => $a, 'network' => $b ) : null;
		}
		return null;
	}
	// Ruta en el sitio (relativa a la raíz) de una entrada del ZIP, o null si no va a la red.
	public static function target_of( $relative, array $rules ) {
		$relative = (string) $relative;
		if ( '' !== (string) $rules['to_uploads'] && 0 === strpos( $relative, $rules['from_uploads'] . '/' ) ) return $rules['to_uploads'] . substr( $relative, strlen( $rules['from_uploads'] ) );
		foreach ( (array) $rules['units'] as $live => $packaged ) if ( $relative === $packaged || 0 === strpos( $relative, $packaged . '/' ) ) return $live . substr( $relative, strlen( $packaged ) );
		return null;
	}
	// Entrada del ZIP (sin `wordpress/`) de una ruta del sitio, o null si no es de la importación.
	public static function source_of( $relative, array $rules ) {
		$relative = (string) $relative;
		if ( '' !== (string) $rules['to_uploads'] && 0 === strpos( $relative, $rules['to_uploads'] . '/' ) ) return $rules['from_uploads'] . substr( $relative, strlen( $rules['to_uploads'] ) );
		foreach ( (array) $rules['units'] as $live => $packaged ) if ( $relative === $live || 0 === strpos( $relative, $live . '/' ) ) return $packaged . substr( $relative, strlen( $live ) );
		return null;
	}

	// ------------------------------------------------------------------ Usuarios

	// Funde los usuarios del respaldo en los de la red. $a: `users`, `usermeta` (tablas temporales del respaldo, ya con las
	// claves del sitio en el prefijo `<base>N_`), `site_id`, `domain`, `mode` (new|replace), `rollback` (la copia de un sitio
	// vuelve exacta: sólo usuarios que existen, sin crear ninguno ni conservar administradores) y `journal` (lo hecho, que
	// $save guarda: una muerte a mitad sigue donde quedó sin crear dos veces a nadie). Por tramos: devuelve el diario; su
	// `step` es `done` al terminar. $deadline: microtime en que debe soltar el tramo.
	public static function merge_users( array $a, array $journal, callable $save, $deadline = INF ) {
		global $wpdb;
		$q = array( 'Digitalisimo_Backups_Relocate', 'quote_name' );
		$users = (string) ( $wpdb->users ?? '' ) ? (string) $wpdb->users : (string) $wpdb->base_prefix . 'users';
		$usermeta = (string) ( $wpdb->usermeta ?? '' ) ? (string) $wpdb->usermeta : (string) $wpdb->base_prefix . 'usermeta';
		$prefix = (string) $wpdb->base_prefix . (int) $a['site_id'] . '_';
		$like = $wpdb->esc_like( $prefix ) . '%';
		$journal += array( 'v' => 1, 'step' => 'start', 'cursor' => 0, 'map' => array(), 'kind' => array(), 'created' => array(), 'pending' => array(), 'renamed' => array(), 'admins' => array(), 'meta_cursor' => 0, 'skipped' => array() );
		$run = function( $sql ) use ( $wpdb ) { if ( false === $wpdb->query( $sql ) ) throw new Exception( 'No se pudieron fundir los usuarios en la red: ' . $wpdb->last_error ); };
		// 1. Antes de cambiar nada: quién administra el sitio que se reemplaza (sigue siéndolo).
		if ( 'start' === $journal['step'] ) {
			if ( 'replace' === $a['mode'] && empty( $a['rollback'] ) ) {
				foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT user_id, meta_value FROM ' . call_user_func( $q, $usermeta ) . ' WHERE meta_key = %s', $prefix . 'capabilities' ), ARRAY_A ) as $row ) {
					$caps = self::unserialize( (string) $row['meta_value'] );
					if ( is_array( $caps ) && ! empty( $caps['administrator'] ) ) $journal['admins'][] = (int) $row['user_id'];
				}
			}
			$journal['step'] = 'users';
			$save( $journal );
		}
		$columns = array();
		foreach ( (array) $wpdb->get_results( 'SHOW COLUMNS FROM ' . call_user_func( $q, $users ), ARRAY_A ) as $column ) $columns[ (string) ( $column['Field'] ?? '' ) ] = true;
		// 2. Cada usuario del respaldo, por ID: el de la red que es él, o uno nuevo con su contraseña.
		while ( 'users' === $journal['step'] ) {
			if ( microtime( true ) > $deadline ) return $journal;
			$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . call_user_func( $q, $a['users'] ) . ' WHERE ID > %d ORDER BY ID LIMIT 100', (int) $journal['cursor'] ), ARRAY_A );
			if ( ! $rows ) { $journal['step'] = 'metas'; $save( $journal ); break; }
			$create = array();
			foreach ( $rows as $row ) {
				$old = (int) $row['ID'];
				if ( isset( $journal['map'][ $old ] ) ) continue;
				$login = (string) ( $row['user_login'] ?? '' );
				$email = strtolower( trim( (string) ( $row['user_email'] ?? '' ) ) );
				// Lo que una pasada anterior creó y no llegó a anotar: está en la red con el nombre que se le dio.
				if ( isset( $journal['pending'][ $old ] ) ) {
					$id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT ID FROM ' . call_user_func( $q, $users ) . ' WHERE user_login = %s', (string) $journal['pending'][ $old ] ) );
					if ( $id > 0 ) { self::note_user( $journal, $old, $id, (string) $journal['pending'][ $old ] !== $login ? 'renamed' : 'created', true ); unset( $journal['pending'][ $old ] ); continue; }
					unset( $journal['pending'][ $old ] );
				}
				$by_email = '' !== $email ? (array) $wpdb->get_results( $wpdb->prepare( 'SELECT ID, user_login FROM ' . call_user_func( $q, $users ) . ' WHERE LOWER(user_email) = %s ORDER BY ID LIMIT 5', $email ), ARRAY_A ) : array();
				$same = null;
				foreach ( $by_email as $match ) if ( 0 === strcasecmp( (string) $match['user_login'], $login ) ) $same = $match;
				if ( $same ) { self::note_user( $journal, $old, (int) $same['ID'], 'matched' ); continue; }
				if ( ! empty( $a['rollback'] ) ) { $journal['map'][ $old ] = 0; $journal['kind'][ $old ] = 'missing'; $journal['skipped'][] = $login; continue; }
				if ( $by_email ) { self::note_user( $journal, $old, (int) $by_email[0]['ID'], 'email' ); continue; }
				$name = self::free_login( $login, $users, array_values( $journal['pending'] ) );
				$journal['pending'][ $old ] = $name;
				$create[ $old ] = array( $row, $name );
			}
			if ( $create ) {
				// Primero lo que se va a crear (una muerte entre el INSERT y su anotación lo encuentra por su nombre).
				$save( $journal );
				foreach ( $create as $old => $pair ) {
					list( $row, $name ) = $pair;
					$values = array(
						'user_login' => $name,
						'user_pass' => (string) ( $row['user_pass'] ?? '' ),
						'user_nicename' => self::free_nicename( (string) ( $row['user_nicename'] ?? $name ), $users ),
						'user_email' => (string) ( $row['user_email'] ?? '' ),
						'user_url' => (string) ( $row['user_url'] ?? '' ),
						'user_registered' => (string) ( $row['user_registered'] ?? '' ) ?: gmdate( 'Y-m-d H:i:s' ),
						'user_activation_key' => '',
						'user_status' => (int) ( $row['user_status'] ?? 0 ),
						'display_name' => (string) ( $row['display_name'] ?? $name ),
					);
					if ( isset( $columns['spam'] ) ) $values['spam'] = 0;
					if ( isset( $columns['deleted'] ) ) $values['deleted'] = 0;
					$values = array_intersect_key( $values, $columns ? $columns : $values );
					$run( 'INSERT INTO ' . call_user_func( $q, $users ) . ' (' . implode( ', ', array_map( $q, array_keys( $values ) ) ) . ') VALUES (' . implode( ', ', array_map( function( $value ) use ( $wpdb ) { return $wpdb->prepare( '%s', (string) $value ); }, $values ) ) . ')' );
					$id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT ID FROM ' . call_user_func( $q, $users ) . ' WHERE user_login = %s', $name ) );
					if ( $id <= 0 ) throw new Exception( 'No se pudo crear en la red el usuario ' . $name . '.' );
					self::note_user( $journal, (int) $old, $id, $name !== (string) ( $row['user_login'] ?? '' ) ? 'renamed' : 'created', true );
					unset( $journal['pending'][ $old ] );
				}
			}
			$journal['cursor'] = (int) end( $rows )['ID'];
			$save( $journal );
		}
		// 3. Las metas: las del sitio (con su prefijo) de cada usuario del respaldo; las globales, sólo de los creados.
		while ( 'metas' === $journal['step'] ) {
			if ( microtime( true ) > $deadline ) return $journal;
			$olds = array_values( array_filter( array_map( 'intval', array_keys( $journal['map'] ) ), function( $old ) use ( $journal ) { return $old > (int) $journal['meta_cursor']; } ) );
			sort( $olds );
			$olds = array_slice( $olds, 0, 50 );
			if ( ! $olds ) { $journal['step'] = 'others'; $save( $journal ); break; }
			foreach ( $olds as $old ) {
				$new = (int) $journal['map'][ $old ];
				$journal['meta_cursor'] = $old;
				if ( $new <= 0 ) continue;
				$created = in_array( $new, (array) $journal['created'], true );
				$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT meta_key, meta_value FROM ' . call_user_func( $q, $a['usermeta'] ) . ' WHERE user_id = %d ORDER BY umeta_id', $old ), ARRAY_A );
				// Repetible: lo del sitio (o, de un usuario creado aquí, todo) se borra antes de escribirlo.
				if ( $created ) $run( $wpdb->prepare( 'DELETE FROM ' . call_user_func( $q, $usermeta ) . ' WHERE user_id = %d', $new ) );
				else $run( $wpdb->prepare( 'DELETE FROM ' . call_user_func( $q, $usermeta ) . ' WHERE user_id = %d AND meta_key LIKE %s', $new, $like ) );
				$write = array();
				foreach ( $rows as $row ) {
					$key = (string) $row['meta_key'];
					if ( in_array( $key, self::DROP_USER_META, true ) || in_array( $key, Digitalisimo_Backups_Site_Extract::NETWORK_USER_META, true ) ) continue;
					if ( 0 === strpos( $key, $prefix ) || $created ) $write[] = array( $key, (string) $row['meta_value'] );
				}
				if ( $created ) {
					$write[] = array( 'primary_blog', (string) (int) $a['site_id'] );
					$write[] = array( 'source_domain', (string) $a['domain'] );
				}
				// Un administrador del sitio que se reemplaza lo sigue siendo, aunque el respaldo le dé otro rol.
				if ( in_array( $new, (array) $journal['admins'], true ) ) $write = self::keep_admin( $write, $prefix );
				foreach ( $write as $pair ) $run( $wpdb->prepare( 'INSERT INTO ' . call_user_func( $q, $usermeta ) . ' (`user_id`, `meta_key`, `meta_value`) VALUES (%s, %s, %s)', (string) $new, $pair[0], $pair[1] ) );
				$journal['meta_cursor'] = $old;
			}
			$save( $journal );
		}
		// 4. Los usuarios de la red que no son del respaldo dejan de ser miembros del sitio que se reemplaza (salvo sus
		// administradores); en la vuelta a la copia, todos los que no están en ella.
		if ( 'others' === $journal['step'] ) {
			if ( 'replace' === $a['mode'] ) {
				$keep = array_values( array_unique( array_filter( array_merge( array_map( 'intval', array_values( $journal['map'] ) ), empty( $a['rollback'] ) ? array_map( 'intval', (array) $journal['admins'] ) : array() ) ) ) );
				$where = $keep ? ' AND user_id NOT IN (' . implode( ',', $keep ) . ')' : '';
				$run( $wpdb->prepare( 'DELETE FROM ' . call_user_func( $q, $usermeta ) . ' WHERE meta_key LIKE %s', $like ) . $where );
				// Un administrador que el respaldo no trae sigue siéndolo (con sus metas del sitio, que nunca se borraron).
			}
			$journal['step'] = 'done';
			$save( $journal );
		}
		return $journal;
	}
	private static function note_user( array &$journal, $old, $new, $kind, $created = false ) {
		$journal['map'][ (int) $old ] = (int) $new;
		$journal['kind'][ (int) $old ] = $kind;
		if ( $created && ! in_array( (int) $new, $journal['created'], true ) ) $journal['created'][] = (int) $new;
	}
	private static function keep_admin( array $write, $prefix ) {
		$found = false;
		foreach ( $write as $n => $pair ) {
			if ( $prefix . 'capabilities' === $pair[0] ) {
				$caps = self::unserialize( $pair[1] );
				$caps = is_array( $caps ) ? $caps : array();
				$caps['administrator'] = true;
				$write[ $n ][1] = serialize( $caps );
				$found = true;
			} elseif ( $prefix . 'user_level' === $pair[0] ) {
				$write[ $n ][1] = '10';
			}
		}
		if ( ! $found ) {
			$write[] = array( $prefix . 'capabilities', serialize( array( 'administrator' => true ) ) );
			$write[] = array( $prefix . 'user_level', '10' );
		}
		return $write;
	}
	// Un nombre de usuario libre en la red: el mismo si nadie lo usa; si no, con `-2`, `-3`… (60 caracteres como mucho).
	private static function free_login( $login, $users, array $taken = array() ) {
		global $wpdb;
		$login = '' !== trim( (string) $login ) ? (string) $login : 'usuario';
		$taken = array_map( 'strtolower', array_map( 'strval', $taken ) );
		for ( $n = 1; $n < 1000; $n++ ) {
			$candidate = 1 === $n ? $login : substr( $login, 0, 60 - strlen( '-' . $n ) ) . '-' . $n;
			if ( in_array( strtolower( $candidate ), $taken, true ) ) continue;
			if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT ID FROM ' . Digitalisimo_Backups_Relocate::quote_name( $users ) . ' WHERE LOWER(user_login) = %s', strtolower( $candidate ) ) ) ) return $candidate;
		}
		return substr( $login, 0, 45 ) . '-' . bin2hex( random_bytes( 6 ) );
	}
	private static function free_nicename( $nicename, $users ) {
		global $wpdb;
		$nicename = '' !== trim( (string) $nicename ) ? (string) $nicename : 'usuario';
		for ( $n = 1; $n < 1000; $n++ ) {
			$candidate = 1 === $n ? $nicename : substr( $nicename, 0, 50 - strlen( '-' . $n ) ) . '-' . $n;
			if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT ID FROM ' . Digitalisimo_Backups_Relocate::quote_name( $users ) . ' WHERE user_nicename = %s', $candidate ) ) ) return $candidate;
		}
		return substr( $nicename, 0, 36 ) . '-' . bin2hex( random_bytes( 6 ) );
	}
	// PHP serializado de un respaldo: nunca instancia objetos.
	public static function unserialize( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^(?:a|s|i|d|b|N)[:;]/', $value ) ) return $value;
		$out = @unserialize( $value, array( 'allowed_classes' => false ) );
		return false === $out && 'b:0;' !== $value ? $value : $out;
	}

	// Reasigna la autoría en las tablas temporales del sitio (antes del cambio): cada ID del respaldo pasa al de la red; un
	// ID que el respaldo no tiene (un usuario borrado en el origen) pasa a 0, porque en la red sería el de otra persona.
	// Repetible tras una muerte: los ID nuevos se escriben primero desplazados (o marcados) y se quitan al final, así que
	// un valor ya reasignado nunca se vuelve a reasignar. $temp: tabla del sitio (`<base>N_posts`) => su temporal.
	public static function remap( array $map, array $temp, $prefix, array $journal, callable $save ) {
		global $wpdb;
		$q = array( 'Digitalisimo_Backups_Relocate', 'quote_name' );
		$journal += array( 'offsets' => array(), 'done' => array() );
		$map = array_filter( array_map( 'intval', $map ), function( $new ) { return $new >= 0; } );
		$columns = function_exists( 'apply_filters' ) ? (array) apply_filters( 'digitalisimo_backups_site_import_user_columns', self::USER_COLUMNS ) : self::USER_COLUMNS;
		$run = function( $sql ) use ( $wpdb ) { if ( false === $wpdb->query( $sql ) ) throw new Exception( 'No se pudo reasignar la autoría: ' . $wpdb->last_error ); };
		$report = array();
		foreach ( $columns as $base => $names ) {
			$table = $temp[ $prefix . $base ] ?? '';
			if ( '' === $table ) continue;
			$have = array();
			foreach ( (array) $wpdb->get_results( 'SHOW COLUMNS FROM ' . call_user_func( $q, $table ), ARRAY_A ) as $column ) $have[ (string) ( $column['Field'] ?? '' ) ] = true;
			foreach ( (array) $names as $column ) {
				$key = $base . '.' . $column;
				if ( ! isset( $have[ $column ] ) || ! empty( $journal['done'][ $key ] ) ) continue;
				$t = call_user_func( $q, $table );
				$c = call_user_func( $q, $column );
				if ( ! isset( $journal['offsets'][ $key ] ) ) {
					$max = (int) $wpdb->get_var( 'SELECT MAX(' . $c . ') FROM ' . $t );
					$journal['offsets'][ $key ] = max( $max, $map ? max( max( array_keys( $map ) ), max( $map ) ) : 0 ) + 1;
					$save( $journal );
				}
				$offset = (int) $journal['offsets'][ $key ];
				foreach ( array_chunk( $map, 200, true ) as $chunk ) {
					$case = '';
					foreach ( $chunk as $old => $new ) $case .= ' WHEN ' . (int) $old . ' THEN ' . ( $offset + (int) $new );
					$run( 'UPDATE ' . $t . ' SET ' . $c . ' = CASE ' . $c . $case . ' ELSE ' . $c . ' END WHERE ' . $c . ' IN (' . implode( ',', array_map( 'intval', array_keys( $chunk ) ) ) . ')' );
				}
				$orphans = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $t . ' WHERE ' . $c . ' > 0 AND ' . $c . ' < ' . $offset );
				$run( 'UPDATE ' . $t . ' SET ' . $c . ' = 0 WHERE ' . $c . ' > 0 AND ' . $c . ' < ' . $offset );
				$run( 'UPDATE ' . $t . ' SET ' . $c . ' = ' . $c . ' - ' . $offset . ' WHERE ' . $c . ' >= ' . $offset );
				$report[ $key ] = array( 'orphans' => $orphans );
				$journal['done'][ $key ] = true;
				$save( $journal );
			}
		}
		$meta = $temp[ $prefix . 'postmeta' ] ?? '';
		if ( '' !== $meta && empty( $journal['done']['postmeta'] ) ) {
			$t = call_user_func( $q, $meta );
			$keys = '(' . implode( ', ', array_map( function( $key ) use ( $wpdb ) { return $wpdb->prepare( '%s', $key ); }, self::USER_POSTMETA ) ) . ')';
			foreach ( array_chunk( $map, 200, true ) as $chunk ) {
				$case = '';
				foreach ( $chunk as $old => $new ) $case .= $wpdb->prepare( ' WHEN %s THEN %s', (string) (int) $old, self::MARK . (int) $new );
				$run( 'UPDATE ' . $t . ' SET meta_value = CASE meta_value' . $case . ' ELSE meta_value END WHERE meta_key IN ' . $keys . ' AND meta_value IN (' . implode( ',', array_map( function( $old ) use ( $wpdb ) { return $wpdb->prepare( '%s', (string) (int) $old ); }, array_keys( $chunk ) ) ) . ')' );
			}
			$run( 'UPDATE ' . $t . " SET meta_value = '0' WHERE meta_key IN " . $keys . " AND meta_value NOT LIKE '" . self::MARK . "%' AND meta_value <> '0' AND meta_value <> ''" );
			$run( 'UPDATE ' . $t . ' SET meta_value = SUBSTR(meta_value, ' . ( strlen( self::MARK ) + 1 ) . ') WHERE meta_key IN ' . $keys . " AND meta_value LIKE '" . self::MARK . "%'" );
			$run( 'DELETE FROM ' . $t . " WHERE meta_key = '_edit_lock'" );
			$journal['done']['postmeta'] = true;
			$save( $journal );
		}
		$journal['report'] = array_merge( (array) ( $journal['report'] ?? array() ), $report );
		return $journal;
	}

	// ------------------------------------------------------------------ Opciones del sitio

	// La tabla temporal de opciones del sitio antes del cambio: la carpeta de medios y la configuración de Backups son las de
	// la red (las del respaldo no se aplican, como H23: el historial, las automatizaciones y los destinos son de la red), sus
	// eventos de WP-Cron de Backups se retiran, y el tema del respaldo queda habilitado para ese sitio si la red no lo
	// habilita (se instaló con él). $live: la tabla de opciones del sitio de ahora (o la del sitio recién creado).
	public static function prepare_options( $temp, $live, array $themes ) {
		global $wpdb;
		$q = array( 'Digitalisimo_Backups_Relocate', 'quote_name' );
		$t = call_user_func( $q, $temp );
		$run = function( $sql ) use ( $wpdb ) { if ( false === $wpdb->query( $sql ) ) throw new Exception( 'No se pudieron preparar las opciones del sitio: ' . $wpdb->last_error ); };
		$report = array( 'backups_options' => 0, 'cron' => 0, 'themes' => array() );
		$keep = array();
		if ( '' !== (string) $live ) foreach ( self::KEEP_OPTIONS as $name ) $keep[ $name ] = $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM ' . call_user_func( $q, $live ) . ' WHERE option_name = %s', $name ) );
		foreach ( self::KEEP_OPTIONS as $name ) {
			$run( $wpdb->prepare( 'DELETE FROM ' . $t . ' WHERE option_name = %s', $name ) );
			if ( null !== ( $keep[ $name ] ?? null ) ) $run( $wpdb->prepare( 'INSERT INTO ' . $t . " (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, 'yes')", $name, (string) $keep[ $name ] ) );
		}
		$report['backups_options'] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $t . ' WHERE option_name LIKE %s', $wpdb->esc_like( 'digitalisimo_backups_' ) . '%' ) );
		$run( $wpdb->prepare( 'DELETE FROM ' . $t . ' WHERE option_name LIKE %s', $wpdb->esc_like( 'digitalisimo_backups_' ) . '%' ) );
		$cron = $wpdb->get_var( 'SELECT option_value FROM ' . $t . " WHERE option_name = 'cron'" );
		$events = self::unserialize( (string) $cron );
		if ( is_array( $events ) ) {
			foreach ( $events as $at => $hooks ) {
				if ( ! is_array( $hooks ) ) continue;
				foreach ( array_keys( $hooks ) as $hook ) if ( 0 === strpos( (string) $hook, 'digitalisimo_backups' ) ) { unset( $events[ $at ][ $hook ] ); $report['cron']++; }
				if ( isset( $events[ $at ] ) && array() === $events[ $at ] ) unset( $events[ $at ] );
			}
			if ( $report['cron'] ) $run( $wpdb->prepare( 'UPDATE ' . $t . " SET option_value = %s WHERE option_name = 'cron'", serialize( $events ) ) );
		}
		// El tema activo del respaldo (y su padre), habilitado sólo en este sitio si la red no lo habilita para todos.
		$network = function_exists( 'get_site_option' ) ? (array) get_site_option( 'allowedthemes', array() ) : array();
		$allowed = self::unserialize( (string) $wpdb->get_var( 'SELECT option_value FROM ' . $t . " WHERE option_name = 'allowedthemes'" ) );
		$allowed = is_array( $allowed ) ? $allowed : array();
		$changed = false;
		foreach ( array_unique( array_filter( array_map( 'strval', $themes ) ) ) as $theme ) {
			if ( ! empty( $network[ $theme ] ) || ! empty( $allowed[ $theme ] ) || ! Digitalisimo_Backups_Site_Extract::safe_name( $theme ) ) continue;
			$allowed[ $theme ] = true;
			$report['themes'][] = $theme;
			$changed = true;
		}
		if ( $changed ) {
			$run( 'DELETE FROM ' . $t . " WHERE option_name = 'allowedthemes'" );
			$run( $wpdb->prepare( 'INSERT INTO ' . $t . " (`option_name`, `option_value`, `autoload`) VALUES ('allowedthemes', %s, 'yes')", serialize( $allowed ) ) );
		}
		return $report;
	}
	// Tema activo y su padre según la tabla de opciones (`stylesheet` y `template`).
	public static function themes_of( $table ) {
		global $wpdb;
		$out = array();
		foreach ( array( 'stylesheet', 'template' ) as $name ) $out[] = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM ' . Digitalisimo_Backups_Relocate::quote_name( $table ) . ' WHERE option_name = %s', $name ) );
		return array_values( array_unique( array_filter( $out ) ) );
	}

	// ------------------------------------------------------------------ Deshacer

	// Retira lo que una importación añadió a la red y no está en ninguna copia: los usuarios que creó (con sus metas; nunca
	// uno que ya existía) y las carpetas de plugins y temas que instaló. Devuelve lo hecho.
	public static function undo_additions( array $created, array $units, $root ) {
		global $wpdb;
		$q = array( 'Digitalisimo_Backups_Relocate', 'quote_name' );
		$users = (string) ( $wpdb->users ?? '' ) ? (string) $wpdb->users : (string) $wpdb->base_prefix . 'users';
		$usermeta = (string) ( $wpdb->usermeta ?? '' ) ? (string) $wpdb->usermeta : (string) $wpdb->base_prefix . 'usermeta';
		$created = array_values( array_filter( array_map( 'intval', $created ) ) );
		$out = array( 'users' => 0, 'units' => array() );
		if ( $created ) {
			$list = implode( ',', $created );
			if ( false === $wpdb->query( 'DELETE FROM ' . call_user_func( $q, $usermeta ) . ' WHERE user_id IN (' . $list . ')' ) ) throw new Exception( 'No se pudieron retirar las metas de los usuarios importados: ' . $wpdb->last_error );
			$done = $wpdb->query( 'DELETE FROM ' . call_user_func( $q, $users ) . ' WHERE ID IN (' . $list . ')' );
			if ( false === $done ) throw new Exception( 'No se pudieron retirar los usuarios importados: ' . $wpdb->last_error );
			$out['users'] = count( $created );
			if ( function_exists( 'clean_user_cache' ) ) foreach ( $created as $id ) clean_user_cache( $id );
		}
		$root = rtrim( wp_normalize_path( (string) $root ), '/' ) . '/';
		foreach ( $units as $unit ) {
			$unit = (string) $unit;
			if ( '' === $unit || false !== strpos( $unit, '..' ) || ! preg_match( '#/[A-Za-z0-9_][A-Za-z0-9._-]*$#', $unit ) ) continue;
			$path = $root . $unit;
			if ( ! file_exists( $path ) && ! is_link( $path ) ) continue;
			self::remove_tree( $path );
			$out['units'][] = $unit;
		}
		return $out;
	}
	// Borra el sitio nuevo de una importación que no terminó: el de WordPress (sus tablas, su carpeta de medios y su fila,
	// con sus ganchos) y las tablas de la importación que queden con su prefijo.
	public static function delete_site( $site_id, array $tables ) {
		global $wpdb;
		$site_id = (int) $site_id;
		if ( $site_id <= 0 || $site_id === (int) get_main_site_id() ) return false;
		if ( function_exists( 'wp_delete_site' ) && null !== self::site_row( $site_id ) ) {
			$done = wp_delete_site( $site_id );
			if ( is_wp_error( $done ) ) throw new Exception( 'No se pudo borrar el sitio ' . $site_id . ': ' . $done->get_error_message() );
		}
		$prefix = (string) $wpdb->base_prefix . $site_id . '_';
		foreach ( $tables as $table ) if ( 0 === strpos( (string) $table, $prefix ) ) $wpdb->query( 'DROP TABLE IF EXISTS ' . Digitalisimo_Backups_Relocate::quote_name( (string) $table ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . Digitalisimo_Backups_Relocate::quote_name( (string) ( $wpdb->usermeta ?? '' ) ? (string) $wpdb->usermeta : (string) $wpdb->base_prefix . 'usermeta' ) . ' WHERE meta_key LIKE %s', $wpdb->esc_like( $prefix ) . '%' ) );
		return true;
	}
	public static function remove_tree( $path ) {
		if ( is_link( $path ) || is_file( $path ) ) { @unlink( $path ); return; }
		if ( ! is_dir( $path ) ) return;
		foreach ( (array) @scandir( $path ) as $name ) if ( '.' !== $name && '..' !== $name && '' !== $name ) self::remove_tree( $path . '/' . $name );
		@rmdir( $path );
	}

	// ------------------------------------------------------------------ Textos

	// Qué pasa al importar (la confirmación, verify y la respuesta): sin rutas ni datos personales.
	public static function summary( array $target, array $rules = array() ) {
		$where = 'new' === $target['mode'] ? 'como sitio nuevo de la red en ' . $target['label'] : 'reemplazando el sitio ' . $target['label'] . ' (sitio ' . (int) $target['site_id'] . ')';
		$text = 'Se importa ' . $where . ': sus tablas pasan al prefijo de ese sitio, sus usuarios se funden con los de la red (nadie de la red pierde su contraseña ni su rol; quien coincide en usuario y correo es la misma cuenta), sus medios van a su carpeta del sitio y sus direcciones a las de ese sitio. El núcleo de WordPress y el resto de la red no se tocan.';
		if ( 'replace' === $target['mode'] ) $text .= ' Antes se guarda una copia sólo de ese sitio (sus tablas y sus medios); sus administradores siguen siéndolo.';
		if ( ! empty( $rules['installed']['plugins'] ) || ! empty( $rules['installed']['themes'] ) ) $text .= ' Se instalan en la red ' . self::list_text( array_merge( (array) ( $rules['installed']['plugins'] ?? array() ), (array) ( $rules['installed']['themes'] ?? array() ) ) ) . ' (activos sólo en ese sitio).';
		return $text;
	}
	// Avisos de lo que no se mezcla en la red o no se reemplaza (la respuesta final y el registro).
	public static function warnings( array $rules ) {
		$out = array();
		foreach ( (array) ( $rules['versions'] ?? array() ) as $version ) $out[] = ( 'themes' === $version['kind'] ? 'El tema ' : 'El plugin ' ) . $version['name'] . ' ya estaba en la red con otra versión (' . ( '' !== $version['network'] ? $version['network'] : 'sin versión' ) . '; el respaldo trae ' . $version['backup'] . '): se conserva el de la red, que comparten todos sus sitios.';
		if ( ! empty( $rules['mu_plugins'] ) ) $out[] = 'Los mu-plugins del respaldo (' . self::list_text( $rules['mu_plugins'] ) . ') no se instalan: se cargarían en todos los sitios de la red. Si el sitio los necesita, revísalos y cópialos a mano.';
		if ( ! empty( $rules['drop_ins'] ) ) $out[] = 'Los drop-ins del respaldo (' . self::list_text( $rules['drop_ins'] ) . ') no se instalan: son del servidor de la red.';
		$skipped = (array) ( $rules['skipped'] ?? array() );
		if ( ! empty( $skipped['content'] ) || ! empty( $skipped['languages'] ) ) $out[] = 'No se mezclan en la red ' . (int) ( $skipped['content'] ?? 0 ) . ' archivos del resto de wp-content ni ' . (int) ( $skipped['languages'] ?? 0 ) . ' de idiomas del respaldo (cachés, registros y traducciones de su servidor).';
		return $out;
	}
	private static function list_text( array $items ) {
		$items = array_values( array_map( 'strval', $items ) );
		return implode( ', ', array_slice( $items, 0, 8 ) ) . ( count( $items ) > 8 ? ' y ' . ( count( $items ) - 8 ) . ' más' : '' );
	}
	// Lo que la confirmación de la pantalla pinta para elegir: «como sitio nuevo» (con su dirección) o «reemplazando el
	// sitio». $suggest: la dirección sugerida.
	public static function field( $suggest, $id = '' ) {
		$network = self::network();
		$subdomain = function_exists( 'is_subdomain_install' ) && is_subdomain_install();
		$id = '' !== $id ? $id : 'digitalisimo-backups-into-' . substr( md5( (string) $suggest . microtime() ), 0, 6 );
		$html = '<fieldset class="digitalisimo-backups-into"><legend>Importar en la red</legend>';
		$html .= '<p><label><input type="radio" name="into" value="new" checked> Como sitio nuevo de la red</label> <label><span class="screen-reader-text">Dirección del sitio nuevo</span>' . ( $subdomain ? '' : '<span class="description">' . esc_html( $network['domain'] . $network['path'] ) . '</span>' ) . '<input type="text" name="address" value="' . esc_attr( (string) $suggest ) . '" pattern="[a-z0-9-]+" maxlength="63" autocomplete="off" aria-describedby="' . esc_attr( $id ) . '-help">' . ( $subdomain ? '<span class="description">.' . esc_html( preg_replace( '/^www\./', '', $network['domain'] ) ) . '</span>' : '' ) . '</label></p>';
		$sites = self::sites();
		if ( $sites ) {
			$html .= '<p><label><input type="radio" name="into" value="replace"> Reemplazando el sitio</label> <label><span class="screen-reader-text">Sitio que se reemplaza</span><select name="into_site">';
			foreach ( $sites as $site_id => $label ) $html .= '<option value="' . (int) $site_id . '">' . esc_html( $label . ' (sitio ' . (int) $site_id . ')' ) . '</option>';
			$html .= '</select></label></p>';
		}
		$html .= '<p class="description" id="' . esc_attr( $id ) . '-help">Es el respaldo de un WordPress individual: se importa como un sitio de esta red. Sus usuarios se funden con los de la red (nadie de la red pierde su contraseña ni su rol), sus medios van a la carpeta del sitio, los plugins y temas que falten se instalan activos sólo en ese sitio y el núcleo y el resto de la red no se tocan. Al reemplazar un sitio, antes se guarda una copia sólo de ese sitio.</p></fieldset>';
		return $html;
	}
	// Lo que envió la confirmación: `into` (new o el ID del sitio) y `address`.
	public static function from_request( array $post ) {
		$into = sanitize_key( (string) ( $post['into'] ?? '' ) );
		if ( 'replace' === $into ) $into = (string) absint( $post['into_site'] ?? 0 );
		$address = isset( $post['address'] ) && is_string( $post['address'] ) ? sanitize_text_field( wp_unslash( $post['address'] ) ) : '';
		return array( 'into' => $into, 'address' => $address );
	}
}

/**
 * La copia de un sitio de la red (alcance `network-site`) para `database_sql_to_file()`: sus tablas tal cual, y de la red
 * los usuarios que tienen alguna meta del sitio (como `<base>N_users`) con sólo esas metas (`<base>N_usermeta`). Así la
 * misma importación la devuelve a su sitio: los mismos usuarios (usuario y correo) con sus roles de antes.
 */
final class Digitalisimo_Backups_Site_Copy_Export {
	private $site_id;
	private $tables;
	private $users;
	private $usermeta;
	private $prefix;
	private $members = array();

	public function __construct( $site_id ) {
		global $wpdb;
		$this->site_id = (int) $site_id;
		$this->prefix = (string) $wpdb->base_prefix . $this->site_id . '_';
		$this->users = (string) ( $wpdb->users ?? '' ) ? (string) $wpdb->users : (string) $wpdb->base_prefix . 'users';
		$this->usermeta = (string) ( $wpdb->usermeta ?? '' ) ? (string) $wpdb->usermeta : (string) $wpdb->base_prefix . 'usermeta';
		$this->tables = array_values( array_filter( Digitalisimo_Backups_Site_Extract::site_tables( $this->site_id ), function( $table ) { return ! in_array( (string) $table, array( $this->prefix . 'users', $this->prefix . 'usermeta' ), true ); } ) );
		$this->tables[] = $this->users;
		$this->tables[] = $this->usermeta;
	}
	public function tables() { return $this->tables; }
	public function read_tables() { return array(); }
	public function name( $table ) {
		if ( $table === $this->users ) return $this->prefix . 'users';
		if ( $table === $this->usermeta ) return $this->prefix . 'usermeta';
		return $table;
	}
	public function create( $table, $sql ) {
		if ( $table !== $this->users && $table !== $this->usermeta ) return $sql;
		return preg_replace( '/^CREATE\s+TABLE\s+`(?:[^`]|``)+`/i', 'CREATE TABLE `' . str_replace( '`', '``', $this->name( $table ) ) . '`', (string) $sql, 1 );
	}
	public function prepare() {
		global $wpdb;
		$this->members = array_values( array_unique( array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT user_id FROM ' . Digitalisimo_Backups_Relocate::quote_name( $this->usermeta ) . ' WHERE meta_key LIKE %s', $wpdb->esc_like( $this->prefix ) . '%' ) ) ) ) );
	}
	public function where( $table ) {
		global $wpdb;
		if ( $table === $this->users ) return $this->members ? 'ID IN (' . implode( ',', $this->members ) . ')' : '1 = 0';
		if ( $table === $this->usermeta ) return $wpdb->prepare( 'meta_key LIKE %s', $wpdb->esc_like( $this->prefix ) . '%' );
		return null;
	}
	public function rows( $table, array $row ) { return array( $row ); }
	public function finish( $table ) { return array(); }
	public function members() { return $this->members; }
}
