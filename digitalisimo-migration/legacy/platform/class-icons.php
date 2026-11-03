<?php
namespace Digitalisimo\Elements\Legacy\Platform;

defined( 'ABSPATH' ) || exit;

/**
 * Conjuntos de iconos propios de Element Pack (`bdt_custom_icons`) → conjuntos propios del constructor
 * (`elementor_icons`), §3.D.4 y V.11.
 *
 * - **Archivos:** la carpeta de origen se recalcula siempre como `uploads/element-pack/custom-icons/<ID>/` (nunca se
 *   usa la ruta que guardó el origen) y se comprueba con `realpath`. La copia es recursiva y conserva las rutas
 *   relativas (`fonts/…`, que la hoja usa), sin enlaces simbólicos, sólo `css`, `eot`, `svg`, `ttf`, `woff`, `woff2` y
 *   `json`, y **nunca sobrescribe**. El destino `uploads/elementor/custom-icons/<nombre>/` se valida con
 *   `Custom_Icons::is_icon_set_dir()`.
 * - **Nombre:** `^[a-z0-9-]{1,48}$` a partir del de origen; libre sólo si ningún conjunto lo usa y su carpeta no
 *   existe; si no, `<nombre>-<sha8>` (sha256 de «ID + nombre»).
 * - **Configuración** `elementor_custom_icon_set_config` construida aquí con el mismo `prefix` y `displayPrefix` que
 *   servía la pestaña de origen: el `value` guardado en los widgets no cambia, sólo `library`. Con más de 25 iconos,
 *   `e_icons.js` y `fetchJson`.
 * - **Contenido:** `rewrite()` cambia `library` en cada control de icono (`bdt-custom-<nombre>-<ID>` → nombre nuevo).
 * - **Alias:** mientras Element Pack no está cargado, la pestaña vieja apunta a la hoja nueva (`alias_tabs()`), así
 *   que nada queda vacío antes de convertir cada documento, en un marcador o en una revisión restaurada. El mapa vive
 *   en `digitalisimo_migration_icon_alias` (carga automática, ≤ 50 entradas) y lo borra `--revert`.
 *
 * Se crea en `platform_prepare` y nace publicado (V.11). Los iconos de dos tonos de Icofont se ven con un tono.
 */
final class Icons {
	const SOURCE      = 'bdt_custom_icons';
	const TARGET      = 'elementor_icons';
	const SOURCE_META = 'bdt_custom_icon_set_config';
	const TARGET_META = 'elementor_custom_icon_set_config';
	const PATH_META   = '_elementor_icon_set_path';
	const LIST_OPTION = 'elementor_custom_icon_sets_config';
	const KIND        = 'icons';
	const ALIAS       = 'digitalisimo_migration_icon_alias';
	const ALIAS_MAX   = 50;
	const EXTENSIONS  = array( 'css', 'eot', 'svg', 'ttf', 'woff', 'woff2', 'json' );
	const MAX_FILES   = 300;
	const MAX_BYTES   = 20971520;
	const MAX_DEPTH   = 4;
	const MAX_ICONS   = 5000;
	const TYPES       = array( 'icomoon', 'remixicon', 'icofont' );
	/** Biblioteca guardada en el contenido por la pestaña de origen. */
	const LIBRARY = '/^bdt-custom-[a-z0-9_-]+-\d+$/';

	/** @var array|null mapa de alias de esta petición. */
	private static $map = null;
	/** @var array|null iconos de dos tonos por biblioteca vieja. */
	private static $duotone = null;

	/* ================================================================ Arranque (alias) */

	/** Engancha la pestaña de alias sólo si hay conjuntos convertidos (opción con carga automática). */
	public static function init(): void {
		if ( get_option( self::ALIAS ) ) {
			add_filter( 'elementor/icons_manager/additional_tabs', array( __CLASS__, 'alias_tabs' ), 20 );
		}
	}

	/**
	 * Pestañas con el nombre viejo que apuntan a la hoja nueva, mientras Element Pack no esté cargado (si lo está, él
	 * registra las suyas). Se copia la pestaña que el destino ya registró con el nombre nuevo.
	 */
	public static function alias_tabs( $tabs ) {
		if ( ! is_array( $tabs ) || Inventory::ep_loaded() ) {
			return $tabs;
		}
		foreach ( self::map() as $old => $new ) {
			if ( isset( $tabs[ $new ] ) && is_array( $tabs[ $new ] ) && ! isset( $tabs[ $old ] ) ) {
				$tabs[ $old ]         = $tabs[ $new ];
				$tabs[ $old ]['name'] = $old;
			}
		}
		return $tabs;
	}

	/** Mapa `bdt-custom-<nombre>-<ID>` → nombre nuevo: la opción de alias y, si se llenó, el registro. */
	public static function map(): array {
		if ( null !== self::$map ) {
			return self::$map;
		}
		$map = get_option( self::ALIAS, array() );
		$map = is_array( $map ) ? $map : array();
		if ( count( $map ) >= self::ALIAS_MAX ) {
			foreach ( Inventory::reg_all( self::KIND ) as $row ) {
				$old = (string) ( $row['extra']['old'] ?? '' );
				$new = (string) ( $row['extra']['name'] ?? '' );
				if ( '' !== $old && '' !== $new && in_array( $row['state'] ?? '', array( 'done', 'draft' ), true ) ) {
					$map[ $old ] = $new;
				}
			}
		}
		self::$map = array_filter(
			$map,
			static function ( $new, $old ) {
				return is_string( $new ) && preg_match( self::LIBRARY, (string) $old ) && preg_match( '/^[a-z0-9-]{1,48}$/', $new );
			},
			ARRAY_FILTER_USE_BOTH
		);
		return self::$map;
	}

	/** Vacía las cachés de la petición (pruebas y tras crear o revertir). */
	public static function flush(): void {
		self::$map     = null;
		self::$duotone = null;
	}

	/* ================================================================ Contenido */

	/**
	 * Reescribe `library` en los controles de icono de los ajustes de un elemento (sólo los suyos: los hijos los
	 * recorre quien llama; `rewrite_elements()` recorre un árbol). Pura: no escribe nada; informa por documento si hay
	 * un documento en curso.
	 */
	public static function rewrite( array $element ): array {
		$stats   = self::stats();
		$element = self::rewrite_one( $element, $stats );
		self::report( $stats );
		return $element;
	}

	/**
	 * Lo mismo en un árbol de elementos (documento, revisión o plantilla), con un solo recuento por documento: es la
	 * llamada recomendada para la conversión de un documento entero.
	 */
	public static function rewrite_elements( array $elements ): array {
		// Una sola búsqueda por documento: sin iconos del origen no se recorre nada.
		if ( false === strpos( (string) wp_json_encode( $elements ), '"library":"bdt-custom-' ) ) {
			return $elements;
		}
		$stats    = self::stats();
		$elements = self::rewrite_tree( $elements, $stats );
		self::report( $stats );
		return $elements;
	}

	/** Análisis de un documento, sin informe: `{ rewritten, duotone, unknown }`. */
	public static function describe( array $elements ): array {
		$stats = self::stats();
		if ( false !== strpos( (string) wp_json_encode( $elements ), '"library":"bdt-custom-' ) ) {
			self::rewrite_tree( $elements, $stats );
		}
		return array( 'rewritten' => $stats['n'], 'duotone' => array_sum( $stats['duo'] ), 'unknown' => array_sum( $stats['unknown'] ) );
	}

	/** Recuento de una reescritura: iconos reescritos y, por elemento, los de dos tonos y los desconocidos. */
	private static function stats(): array {
		return array( 'n' => 0, 'duo' => array(), 'unknown' => array(), 'label' => '' );
	}

	/** Cómo se nombra un elemento en el informe: `tipo #id`. */
	public static function element_label( array $element ): string {
		$type = (string) ( 'widget' === ( $element['elType'] ?? '' ) ? ( $element['widgetType'] ?? 'widget' ) : ( $element['elType'] ?? '' ) );
		return trim( $type . ( isset( $element['id'] ) ? ' #' . (string) $element['id'] : '' ) );
	}

	private static function rewrite_tree( array $elements, array &$stats ): array {
		foreach ( $elements as $index => $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$element = self::rewrite_one( $element, $stats );
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = self::rewrite_tree( $element['elements'], $stats );
			}
			$elements[ $index ] = $element;
		}
		return $elements;
	}

	private static function rewrite_one( array $element, array &$stats ): array {
		if ( ! isset( $element['settings'] ) || ! is_array( $element['settings'] ) || ! self::has_legacy( $element['settings'] ) ) {
			return $element;
		}
		$stats['label']      = self::element_label( $element );
		$element['settings'] = self::rewrite_value( $element['settings'], $stats );
		$stats['label']      = '';
		return $element;
	}

	/** Lo mismo en ajustes sueltos (los de una página o del kit). */
	public static function rewrite_settings( array $settings ): array {
		if ( ! self::has_legacy( $settings ) ) {
			return $settings;
		}
		$stats = self::stats();
		$out   = self::rewrite_value( $settings, $stats );
		self::report( $stats );
		return $out;
	}

	private static function has_legacy( array $settings ): bool {
		return false !== strpos( (string) wp_json_encode( $settings ), '"library":"bdt-custom-' );
	}

	private static function rewrite_value( array $value, array &$stats ): array {
		foreach ( $value as $key => $item ) {
			if ( is_array( $item ) ) {
				$value[ $key ] = self::rewrite_value( $item, $stats );
			}
		}
		$library = $value['library'] ?? null;
		if ( ! is_string( $library ) || 0 !== strpos( $library, 'bdt-custom-' ) || ! array_key_exists( 'value', $value ) ) {
			return $value;
		}
		$map = self::map();
		if ( ! isset( $map[ $library ] ) ) {
			$stats['unknown'][ $stats['label'] ] = ( $stats['unknown'][ $stats['label'] ] ?? 0 ) + 1;
			return $value;
		}
		if ( self::is_duotone( $library, (string) ( is_string( $value['value'] ) ? $value['value'] : '' ) ) ) {
			$stats['duo'][ $stats['label'] ] = ( $stats['duo'][ $stats['label'] ] ?? 0 ) + 1;
		}
		$value['library'] = $map[ $library ];
		++$stats['n'];
		return $value;
	}

	/** ¿Es un icono de dos tonos de Icofont (por su clase o por la lista del conjunto)? */
	private static function is_duotone( string $library, string $value ): bool {
		if ( false !== strpos( $value, 'icofont-duotone' ) ) {
			return true;
		}
		if ( null === self::$duotone ) {
			self::$duotone = array();
			foreach ( Inventory::reg_all( self::KIND ) as $row ) {
				$old = (string) ( $row['extra']['old'] ?? '' );
				if ( '' !== $old && ! empty( $row['extra']['duo'] ) && is_array( $row['extra']['duo'] ) ) {
					self::$duotone[ $old ] = array_flip( $row['extra']['duo'] );
				}
			}
		}
		if ( empty( self::$duotone[ $library ] ) ) {
			return false;
		}
		$name = (string) preg_replace( '/^.*\bicofont-/', '', $value );
		return isset( self::$duotone[ $library ][ $name ] );
	}

	private static function report( array $stats ): void {
		$post_id = Inventory::context_post();
		if ( $post_id <= 0 ) {
			return;
		}
		// Lo convertido se anota una vez por documento (sin recuento: la conversión puede ir elemento a elemento); lo que
		// pide revisión, por elemento.
		if ( $stats['n'] ) {
			Inventory::doc_code( $post_id, 'plat.icons.rewritten' );
		}
		foreach ( array( 'duo' => 'plat.icons.duotone', 'unknown' => 'plat.icons.unknown' ) as $key => $code ) {
			foreach ( $stats[ $key ] as $label => $count ) {
				Inventory::doc_code( $post_id, $code, array( 'count' => $count ) + ( '' !== (string) $label ? array( 'element' => (string) $label ) : array() ) );
			}
		}
	}

	/* ================================================================ Conjuntos */

	/** Conjuntos publicados del origen, por ID ascendente. */
	public static function sources(): array {
		return array_map(
			'intval',
			(array) get_posts(
				array(
					'post_type'        => self::SOURCE,
					'post_status'      => 'publish',
					'numberposts'      => -1,
					'fields'           => 'ids',
					'orderby'          => 'ID',
					'order'            => 'ASC',
					'suppress_filters' => true,
				)
			)
		);
	}

	/** Configuración guardada por el origen, o null. */
	private static function source_config( int $id ): ?array {
		$raw    = get_post_meta( $id, self::SOURCE_META, true );
		$config = is_string( $raw ) ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : null );
		return is_array( $config ) && ! empty( $config['name'] ) ? $config : null;
	}

	/** Nombre de la pestaña de origen: `bdt-custom-<sanitize_key(name)>-<ID>`. */
	public static function old_library( int $id, string $name ): string {
		$key = function_exists( 'sanitize_key' ) ? sanitize_key( $name ) : preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $name ) );
		return 'bdt-custom-' . $key . '-' . $id;
	}

	/** Raíz de los conjuntos del destino y su URL (con los filtros del destino). */
	private static function target_root(): ?array {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return null;
		}
		return array(
			'dir' => (string) apply_filters( 'elementor_pro/icons_manager/custom_icons/dir', $uploads['basedir'] . '/elementor/custom-icons' ),
			'url' => untrailingslashit( (string) $uploads['baseurl'] ) . '/elementor/custom-icons/',
		);
	}

	/** URL pública de un archivo del conjunto (con el filtro del destino). */
	private static function file_url( array $root, string $file ): string {
		return (string) apply_filters( 'elementor_pro/icons_manager/custom_icons/url', $root['url'] . $file, $file );
	}

	/** Carpeta de origen recalculada y comprobada, o null. */
	private static function source_dir( int $id ): ?string {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return null;
		}
		$base = realpath( $uploads['basedir'] . '/element-pack/custom-icons' );
		$path = $uploads['basedir'] . '/element-pack/custom-icons/' . $id;
		if ( false === $base || is_link( $path ) ) {
			return null;
		}
		$real = realpath( $path );
		if ( false === $real || ! is_dir( $real ) || 0 !== strpos( self::norm( $real ) . '/', self::norm( $base ) . '/' ) || self::norm( $real ) === self::norm( $base ) ) {
			return null;
		}
		return $real;
	}

	private static function norm( string $path ): string {
		return rtrim( str_replace( '\\', '/', $path ), '/' );
	}

	/** Nombre base `^[a-z0-9-]{1,39}$` (deja sitio al sufijo). */
	public static function base_name( string $name ): string {
		$base = trim( (string) preg_replace( '/-+/', '-', (string) preg_replace( '/[^a-z0-9-]+/', '-', strtolower( $name ) ) ), '-' );
		$base = trim( substr( $base, 0, 39 ), '-' );
		return '' !== $base ? $base : 'iconos';
	}

	/** ¿Nombre libre? Ningún conjunto del destino lo usa (en ningún estado) y su carpeta no existe. */
	private static function free( string $name, array $root ): bool {
		if ( file_exists( rtrim( $root['dir'], '/' ) . '/' . $name ) ) {
			return false;
		}
		$ids = get_posts(
			array(
				'post_type'        => self::TARGET,
				'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);
		foreach ( (array) $ids as $set ) {
			$config = json_decode( (string) get_post_meta( (int) $set, self::TARGET_META, true ), true );
			if ( is_array( $config ) && $name === (string) ( $config['name'] ?? '' ) ) {
				return false;
			}
		}
		return true;
	}

	/** Nombre de destino: el base si está libre; si no, con el sufijo; null si tampoco. */
	public static function target_name( int $id, string $name, array $root ): ?string {
		$base = self::base_name( $name );
		if ( self::free( $base, $root ) ) {
			return $base;
		}
		$alt = $base . '-' . substr( hash( 'sha256', $id . '|' . $name ), 0, 8 );
		return self::free( $alt, $root ) ? $alt : null;
	}

	/** Análisis previo. */
	public static function analyze(): array {
		$out  = array();
		$root = self::target_root();
		foreach ( self::sources() as $id ) {
			if ( self::done( $id ) ) {
				continue;
			}
			$config = self::source_config( $id );
			$label  = self::label( $id, $config );
			if ( ! $config ) {
				$out[] = array( 'plat.icons.no_config', array( 'name' => $label ) );
				continue;
			}
			if ( null === self::source_dir( $id ) ) {
				$out[] = array( 'plat.icons.missing_files', array( 'name' => $label ) );
				continue;
			}
			$name = $root ? self::target_name( $id, (string) $config['name'], $root ) : null;
			if ( null === $name ) {
				$out[] = array( 'plat.icons.conflict', array( 'name' => $label ) );
				continue;
			}
			$out[] = array( $name === self::base_name( (string) $config['name'] ) ? 'plat.icons.created' : 'plat.icons.renamed', array( 'name' => $label, 'target' => $name ) );
		}
		return $out;
	}

	private static function label( int $id, ?array $config ): string {
		$post = get_post( $id );
		$name = $post && '' !== trim( (string) $post->post_title ) ? (string) $post->post_title : (string) ( $config['label'] ?? $config['name'] ?? '#' . $id );
		return Inventory::plain( $name, 80 );
	}

	private static function done( int $id ): bool {
		$row = Inventory::reg_get( self::KIND, (string) $id );
		return $row && in_array( $row['state'] ?? '', array( 'done', 'draft' ), true ) && Inventory::exists( (int) ( $row['dest'] ?? 0 ) );
	}

	/** Etapa `platform_prepare`, con cursor por ID. */
	public static function prepare( array &$cursor, float $deadline ): bool {
		foreach ( self::sources() as $id ) {
			if ( $id <= (int) ( $cursor['after'] ?? 0 ) ) {
				continue;
			}
			if ( Inventory::late( $deadline ) ) {
				return false;
			}
			if ( self::convert( $id ) ) {
				$cursor['changed'] = 1;
			}
			$cursor['after'] = $id;
		}
		if ( ! empty( $cursor['changed'] ) ) {
			delete_option( self::LIST_OPTION );
		}
		return true;
	}

	public static function finish( array &$cursor, float $deadline ): bool {
		return true;
	}

	/** Convierte un conjunto. Devuelve true si lo creó. */
	public static function convert( int $id ): bool {
		if ( self::done( $id ) ) {
			return false;
		}
		$config = self::source_config( $id );
		$label  = self::label( $id, $config );
		if ( ! $config ) {
			Inventory::site_code( 'plat.icons.no_config', array( 'name' => $label ) );
			return false;
		}
		$from = self::source_dir( $id );
		$root = self::target_root();
		if ( null === $from || null === $root || ! is_file( $from . '/style.css' ) ) {
			Inventory::site_code( 'plat.icons.missing_files', array( 'name' => $label ) );
			return false;
		}
		$old     = self::old_library( $id, (string) $config['name'] );
		$tag     = self::KIND . ':' . $id;
		$pending = Inventory::reg_get( self::KIND, (string) $id );
		$name    = $pending && 'pending' === ( $pending['state'] ?? '' ) && preg_match( '/^[a-z0-9-]{1,48}$/', (string) ( $pending['extra']['name'] ?? '' ) ) ? (string) $pending['extra']['name'] : self::target_name( $id, (string) $config['name'], $root );
		if ( null === $name ) {
			Inventory::site_code( 'plat.icons.conflict', array( 'name' => $label ) );
			return false;
		}
		$built = self::build_config( $config, $name, $root );
		if ( null === $built ) {
			Inventory::site_code( 'plat.icons.unsafe', array( 'name' => $label ) );
			return false;
		}
		// Primero se lee y valida todo el árbol: un conjunto inseguro no deja ni una carpeta en el destino.
		$skipped = array();
		$plan    = self::scan_tree( $from, $skipped );
		if ( null === $plan || ! isset( $plan['style.css'] ) ) {
			Inventory::site_code( 'plat.icons.unsafe', array( 'name' => $label ) );
			return false;
		}
		// Se anota antes de escribir: una petición que muera reanuda con el mismo nombre y su carpeta.
		Inventory::reg_put( self::KIND, (string) $id, array( 'state' => 'pending', 'extra' => array( 'old' => $old, 'name' => $name ) ) );
		if ( ! wp_mkdir_p( rtrim( $root['dir'], '/' ) . '/' . $name ) ) {
			Inventory::site_code( 'plat.icons.conflict', array( 'name' => $label ) );
			return false;
		}
		$to = realpath( rtrim( $root['dir'], '/' ) . '/' . $name );
		if ( false === $to || ! self::is_set_dir( $to, $root ) ) {
			Inventory::site_code( 'plat.icons.unsafe', array( 'name' => $label ) );
			return false;
		}
		$copied = self::write_tree( $plan, $to );
		if ( null === $copied || ! is_file( $to . '/style.css' ) ) {
			Inventory::site_code( 'plat.icons.conflict', array( 'name' => $label ) );
			return false;
		}
		if ( $built['count'] > 25 ) {
			$js = $to . '/e_icons.js';
			$content = (string) wp_json_encode( array( 'icons' => $built['icons'] ) );
			if ( file_exists( $js ) && md5_file( $js ) !== md5( $content ) ) {
				Inventory::site_code( 'plat.icons.conflict', array( 'name' => $label ) );
				return false;
			}
			if ( ! file_exists( $js ) ) {
				file_put_contents( $js, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- archivo nuevo del conjunto, en uploads.
			}
			$copied[]            = array( 'e_icons.js', $js );
			$built['fetchJson'] = self::file_url( $root, $name . '/e_icons.js' );
		}
		$dest = Inventory::created( self::TARGET, $tag );
		if ( ! $dest ) {
			$dest = wp_insert_post(
				array(
					'post_type'   => self::TARGET,
					'post_status' => 'publish',
					'post_title'  => wp_slash( $label ),
					'meta_input'  => array( Inventory::TAG => $tag ),
				),
				true
			);
			if ( ! $dest || is_wp_error( $dest ) ) {
				Inventory::site_code( 'plat.icons.conflict', array( 'name' => $label ) );
				return false;
			}
			$dest = (int) $dest;
			foreach ( $copied as $file ) {
				wp_insert_attachment(
					array(
						'file'        => $file[1],
						'guid'        => self::file_url( $root, $name . '/' . $file[0] ),
						'post_parent' => $dest,
						'post_type'   => 'attachment',
					)
				);
			}
		}
		$json = (string) wp_json_encode( $built );
		update_post_meta( $dest, self::TARGET_META, wp_slash( $json ) );
		update_post_meta( $dest, self::PATH_META, wp_slash( trailingslashit( $to ) ) );
		$duo = array_values( array_filter( array_map( 'strval', (array) ( $config['duotone_icons'] ?? array() ) ), static function ( $icon ) {
			return 1 === preg_match( '/^[A-Za-z0-9_-]{1,80}$/', $icon );
		} ) );
		Inventory::reg_put(
			self::KIND,
			(string) $id,
			array(
				'dest'  => $dest,
				'hash'  => md5( $json ),
				'state' => 'done',
				'extra' => array( 'old' => $old, 'name' => $name, 'duo' => array_slice( $duo, 0, 2000 ) ),
			)
		);
		$alias = get_option( self::ALIAS, array() );
		$alias = is_array( $alias ) ? $alias : array();
		if ( isset( $alias[ $old ] ) || count( $alias ) < self::ALIAS_MAX ) {
			$alias[ $old ] = $name;
			update_option( self::ALIAS, $alias, true );
		}
		self::flush();
		Inventory::site_code( $name === self::base_name( (string) $config['name'] ) ? 'plat.icons.created' : 'plat.icons.renamed', array( 'name' => $label, 'target' => $name, 'id' => $dest ) );
		if ( $skipped ) {
			Inventory::site_code( 'plat.icons.skipped_file', array( 'name' => $label, 'count' => count( $skipped ), 'note' => Inventory::plain( implode( ', ', array_slice( $skipped, 0, 5 ) ), 200 ) ) );
		}
		return true;
	}

	/** ¿Carpeta de un conjunto dentro de la raíz del destino (nunca la raíz)? Con la comprobación del destino si existe. */
	private static function is_set_dir( string $dir, array $root ): bool {
		$class = '\\ElementorPro\\Modules\\AssetsManager\\AssetTypes\\Icons\\Custom_Icons';
		if ( class_exists( $class, false ) && method_exists( $class, 'is_icon_set_dir' ) ) {
			return (bool) $class::is_icon_set_dir( $dir );
		}
		$base = realpath( $root['dir'] );
		$real = realpath( $dir );
		return false !== $base && false !== $real && self::norm( $real ) !== self::norm( $base ) && 0 === strpos( self::norm( $real ) . '/', self::norm( $base ) . '/' );
	}

	/** Configuración del destino con el prefijo de la pestaña de origen, o null si no es válida. */
	private static function build_config( array $config, string $name, array $root ): ?array {
		$prefix = rtrim( (string) ( $config['prefix'] ?? '' ), '-' );
		if ( ! preg_match( '/^[A-Za-z0-9_-]{0,40}$/', $prefix ) ) {
			return null;
		}
		$icons = array();
		foreach ( array_slice( (array) ( $config['icons'] ?? array() ), 0, self::MAX_ICONS ) as $icon ) {
			if ( is_string( $icon ) && preg_match( '/^[A-Za-z0-9_-]{1,80}$/', $icon ) ) {
				$icons[] = $icon;
			}
		}
		if ( ! $icons ) {
			return null;
		}
		$icons = array_values( array_unique( $icons ) );
		$type  = in_array( $config['type'] ?? '', self::TYPES, true ) ? (string) $config['type'] : 'icomoon';
		$ver   = (string) preg_replace( '/[^0-9A-Za-z._-]/', '', (string) ( $config['ver'] ?? '' ) );
		return array(
			'name'             => $name,
			'label'            => Inventory::plain( (string) ( $config['label'] ?? $name ), 80 ),
			'url'              => self::file_url( $root, $name . '/style.css' ),
			'enqueue'          => false,
			'prefix'           => '' !== $prefix ? $prefix . '-' : '',
			'displayPrefix'    => $prefix,
			'labelIcon'        => 'eicon eicon-folder',
			'ver'              => '' !== $ver ? substr( $ver, 0, 20 ) : '1.0.0',
			'custom_icon_type' => $type,
			'count'            => count( $icons ),
			'icons'            => $icons,
		);
	}

	/**
	 * Lee y valida el árbol de origen sin escribir nada: `relativa => [ real, contenido ]`, o null si algo impide el
	 * conjunto entero (una hoja insegura, topes superados). Los enlaces simbólicos, los tipos no admitidos y las rutas
	 * raras se saltan y se anotan.
	 */
	private static function scan_tree( string $from, array &$skipped ): ?array {
		$root  = self::norm( $from );
		$files = array();
		$bytes = 0;
		$items = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $from, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::SELF_FIRST );
		$items->setMaxDepth( self::MAX_DEPTH );
		foreach ( $items as $item ) {
			$path = $item->getPathname();
			$rel  = ltrim( substr( self::norm( $path ), strlen( $root ) ), '/' );
			if ( is_link( $path ) ) {
				$skipped[] = $rel;
				continue;
			}
			$valid = true;
			foreach ( explode( '/', $rel ) as $segment ) {
				$valid = $valid && 1 === preg_match( '/^[A-Za-z0-9_-][A-Za-z0-9._-]{0,99}$/', $segment );
			}
			if ( ! $valid ) {
				$skipped[] = $rel;
				continue;
			}
			if ( $item->isDir() ) {
				continue;
			}
			$ext  = strtolower( (string) pathinfo( $rel, PATHINFO_EXTENSION ) );
			$real = realpath( $path );
			if ( ! in_array( $ext, self::EXTENSIONS, true ) || false === $real || 0 !== strpos( self::norm( $real ), $root . '/' ) ) {
				$skipped[] = $rel;
				continue;
			}
			$bytes += (int) filesize( $real );
			if ( count( $files ) >= self::MAX_FILES || $bytes > self::MAX_BYTES ) {
				return null;
			}
			$content = (string) file_get_contents( $real ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- archivo local del sitio.
			if ( 'css' === $ext && ! Snippets::validate_css( $content ) ) {
				return null;
			}
			if ( 'svg' === $ext && preg_match( '/<script|\son[a-z]+\s*=|javascript:|<foreignObject|<iframe|<embed|<object/i', $content ) ) {
				$skipped[] = $rel;
				continue;
			}
			$files[ $rel ] = $content;
		}
		return $files;
	}

	/**
	 * Escribe el árbol validado sin sobrescribir nunca: `[ [relativa, destino], … ]`, o null si un archivo distinto ya
	 * ocupa su lugar (al reanudar, el mismo archivo ya copiado vale).
	 */
	private static function write_tree( array $plan, string $to ): ?array {
		$files = array();
		foreach ( $plan as $rel => $content ) {
			$target = $to . '/' . $rel;
			if ( file_exists( $target ) || is_link( $target ) ) {
				if ( is_link( $target ) || md5_file( $target ) !== md5( $content ) ) {
					return null;
				}
				$files[] = array( $rel, $target );
				continue;
			}
			if ( ! wp_mkdir_p( dirname( $target ) ) ) {
				return null;
			}
			$dir = realpath( dirname( $target ) );
			if ( false === $dir || 0 !== strpos( self::norm( $dir ) . '/', self::norm( $to ) . '/' ) ) {
				return null;
			}
			if ( false === file_put_contents( $target, $content ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- copia en uploads del sitio.
				return null;
			}
			$files[] = array( $rel, $target );
		}
		return $files;
	}

	/** `--revert`: retira los conjuntos creados que nadie cambió (sus archivos quedan; la papelera los conserva). */
	public static function revert( bool $force = false ): array {
		$out   = array();
		$alias = get_option( self::ALIAS, array() );
		$alias = is_array( $alias ) ? $alias : array();
		foreach ( Inventory::reg_all( self::KIND ) as $source => $row ) {
			$dest = (int) ( $row['dest'] ?? 0 );
			$old  = (string) ( $row['extra']['old'] ?? '' );
			if ( Inventory::exists( $dest ) ) {
				if ( ! $force && md5( (string) get_post_meta( $dest, self::TARGET_META, true ) ) !== (string) ( $row['hash'] ?? '' ) ) {
					$out[] = array( 'core.revert_skipped', array( 'type' => self::TARGET, 'id' => $dest ) );
					continue;
				}
				Inventory::retire_post( $dest );
				$out[] = array( 'plat.icons.reverted', array( 'id' => $dest, 'target' => (string) ( $row['extra']['name'] ?? '' ) ) );
			}
			unset( $alias[ $old ] );
			Inventory::reg_forget( self::KIND, (string) $source );
		}
		if ( $alias ) {
			update_option( self::ALIAS, $alias, true );
		} else {
			delete_option( self::ALIAS );
		}
		delete_option( self::LIST_OPTION );
		self::flush();
		return $out;
	}
}
