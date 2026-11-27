<?php
namespace Digitalisimo\Migration;

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__ ) . '/legacy/class-retire.php';

use Digitalisimo\Elements\Legacy\Retire;

/**
 * Mapa de reemplazos de «Migrar» (regla del propietario del 2026-11-18): qué plugin o tema del sitio sustituye de verdad
 * cada plugin DIGITALÍSIMO, qué se importa antes de retirarlo y cuándo puede borrarse. Sólo lo que el repositorio
 * sustituye con evidencia (código o documentación); lo demás no está aquí y nunca se toca.
 *
 * - La familia de Elementor (Elementor, Elementor Pro, PRO Elements, Element Pack Pro, Lite y Addon, Prime Slider Pro y
 *   Lite) la reconoce y la desactiva `Retire` (una sola fuente por regla); aquí sólo se añade cuándo puede borrarse.
 * - SEO: Rank Math (importador completo RM1, `Digitalisimo_Integrations_RankMath_Import`, que lee sus metas, opciones y
 *   tabla de redirecciones aunque esté desactivado) y Yoast SEO (importador de metas de contenido de DIGITALÍSIMO SEO).
 *   Yoast SEO Premium no tiene importador de sus redirecciones: con él, Yoast se queda como está.
 * - Backups: UpdraftPlus y Backuply. Backups lee sus conjuntos de respaldos en sus carpetas de `wp-content`
 *   (`Digitalisimo_Backups_Updraft_Import`, `Digitalisimo_Backups_Backuply_Import`) y los importa cuando se pide; Migrar
 *   nunca toca esas carpetas. La desinstalación de UpdraftPlus 1.26 sólo borra opciones (y sólo si su ajuste
 *   «borrar al desinstalar» está encendido); la de Backuply no está en las copias de referencia y no se borra nunca.
 * - Tema: Hello Elementor → `digitalisimo-theme`, que reproduce a Hello (paridad) y traslada sus ajustes.
 *
 * Reglas de borrado (`delete`), además de las barreras generales de Full::cleanup() (respaldo verificado, ninguna etapa
 * fallida, portada verificada, plugin desactivado en todas partes y su reemplazo activo):
 * - `content`: cuando la conversión de contenido terminó sin nada que lo necesite (informe de la tarea de Migrar).
 * - `import`: cuando su importador terminó en esta tarea.
 * - `kept_data`: sólo si su desinstalación conserva lo que Backups necesita para seguir viendo sus respaldos.
 * - `never`: se desactiva y se queda instalado.
 * - `theme`: con el tema propio activo y verificado, si ningún sitio ni tema hijo lo usa.
 */
final class Replacements {
	/** Plugins DIGITALÍSIMO que Migrar puede instalar: slug del índice de la Release => archivo y nombre (los de su ZIP). */
	const OWN = array(
		'digitalisimo-backups'  => array( 'file' => 'digitalisimo-backups/digitalisimo-backups.php', 'label' => 'DIGITALÍSIMO Backups' ),
		'digitalisimo-elements' => array( 'file' => 'digitalisimo-elements/pro-elements.php', 'label' => 'DIGITALÍSIMO Elements' ),
		'digitalisimo-seo'      => array( 'file' => 'digitalisimo-seo/digitalisimo-integrations.php', 'label' => 'DIGITALÍSIMO SEO' ),
		// Dueño de «Código PHP»: lo instala, activa o actualiza la etapa del tema cuando el functions.php de un hijo de Hello
		// tiene PHP que pasar al tema propio (no reemplaza a ningún plugin, así que no está en ORDER).
		'digitalisimo-tools'    => array( 'file' => 'digitalisimo-tools/digitalisimo-tools.php', 'label' => 'DIGITALÍSIMO Tools' ),
	);
	/** WordPress mínimo de cada paquete (su «Requires at least»): Migrar no empieza por debajo del de lo que instalará. */
	const REQUIRES_WP = array( 'digitalisimo-backups' => '6.0', 'digitalisimo-elements' => '6.8', 'digitalisimo-seo' => '6.0', 'digitalisimo-tools' => '6.0', 'digitalisimo-theme' => '6.8' );
	/** Orden de instalación y activación: Backups (el respaldo va primero) y Elements antes que lo demás. */
	const ORDER = array( 'digitalisimo-backups', 'digitalisimo-elements', 'digitalisimo-seo' );
	/** Tema propio y el que reemplaza. */
	const THEME      = 'digitalisimo-theme';
	const THEME_URI  = 'https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-theme';
	const HELLO      = 'hello-elementor';

	/** Grupos de `Retire` (familia de Elementor) => fila del mapa. */
	const ELEMENTS_GROUPS = array(
		'element-pack' => array( 'key' => 'element-pack', 'label' => 'Element Pack', 'import' => 'Su contenido, plantillas, fuentes, iconos y ajustes pasan al formato propio (con copia de cada documento).' ),
		'pro'          => array( 'key' => 'elementor-pro', 'label' => 'Elementor Pro / PRO Elements', 'import' => 'Sus widgets pasan a los propios cuando tienen equivalente; sus funciones avanzadas ya vienen en DIGITALÍSIMO Elements.' ),
		'core'         => array( 'key' => 'elementor', 'label' => 'Elementor', 'import' => 'Nada: DIGITALÍSIMO Elements trae el mismo núcleo y lee el mismo contenido.' ),
		'prime-slider' => array( 'key' => 'prime-slider', 'label' => 'Prime Slider', 'import' => 'Sus sliders pasan a los widgets propios (con copia de cada documento).' ),
	);

	/**
	 * Resto del mapa: clave => archivo principal (basename), «Plugin Name» y carpeta, qué lo reemplaza, qué se importa y
	 * cuándo puede borrarse. `group`: se desactivan y se borran juntos. `blocks`: su presencia deja ese grupo como está.
	 * Sin `by`, no hay reemplazo con importador: sólo se informa.
	 */
	const ROWS = array(
		'rank-math'     => array( 'file' => 'rank-math.php', 'folder' => 'seo-by-rank-math', 'name' => '/^Rank Math/i', 'label' => 'Rank Math SEO', 'by' => 'digitalisimo-seo', 'import' => 'seo_rankmath', 'delete' => 'import', 'group' => 'rank-math', 'imports' => 'Metas de cada contenido y término, ajustes, redirecciones y bases de URL (importador de Rank Math de DIGITALÍSIMO SEO; lo que no tiene equivalente queda en su informe).' ),
		'rank-math-pro' => array( 'file' => 'rank-math-pro.php', 'folder' => 'seo-by-rank-math-pro', 'name' => '/^Rank Math/i', 'label' => 'Rank Math SEO PRO', 'by' => 'digitalisimo-seo', 'import' => 'seo_rankmath', 'delete' => 'import', 'group' => 'rank-math', 'imports' => 'Lo mismo que Rank Math (PRO guarda sus datos en las mismas metas y opciones).' ),
		'yoast'         => array( 'file' => 'wp-seo.php', 'folder' => 'wordpress-seo', 'name' => '/^Yoast SEO$/i', 'label' => 'Yoast SEO', 'by' => 'digitalisimo-seo', 'import' => 'seo_yoast', 'delete' => 'import', 'group' => 'yoast', 'imports' => 'Título SEO, meta descripción, palabra clave, canonical, noindex y contenido pilar de cada contenido. Sus plantillas globales de títulos no se importan: DIGITALÍSIMO SEO usa las suyas.' ),
		'yoast-premium' => array( 'file' => 'wp-seo-premium.php', 'folder' => 'wordpress-seo-premium', 'name' => '/^Yoast SEO Premium/i', 'label' => 'Yoast SEO Premium', 'by' => '', 'blocks' => 'yoast', 'group' => 'yoast-premium', 'reason' => 'Sus redirecciones y ajustes de Premium no tienen importador en DIGITALÍSIMO SEO: Yoast SEO y Yoast SEO Premium se quedan como están.' ),
		'aioseo'        => array( 'file' => 'all_in_one_seo_pack.php', 'folder' => 'all-in-one-seo-pack', 'name' => '/^All in One SEO/i', 'label' => 'All in One SEO', 'by' => '', 'group' => 'aioseo', 'reason' => 'DIGITALÍSIMO SEO no tiene importador de All in One SEO: se queda, y DIGITALÍSIMO SEO no publica señales mientras esté activo.' ),
		'seopress'      => array( 'file' => 'seopress.php', 'folder' => 'wp-seopress', 'name' => '/^SEOPress/i', 'label' => 'SEOPress', 'by' => '', 'group' => 'seopress', 'reason' => 'DIGITALÍSIMO SEO no tiene importador de SEOPress: se queda, y DIGITALÍSIMO SEO no publica señales mientras esté activo.' ),
		'seo-framework' => array( 'file' => 'autodescription.php', 'folder' => 'autodescription', 'name' => '/^The SEO Framework/i', 'label' => 'The SEO Framework', 'by' => '', 'group' => 'seo-framework', 'reason' => 'DIGITALÍSIMO SEO no tiene importador de The SEO Framework: se queda, y DIGITALÍSIMO SEO no publica señales mientras esté activo.' ),
		'updraftplus'   => array( 'file' => 'updraftplus.php', 'folder' => 'updraftplus', 'name' => '/^UpdraftPlus/i', 'label' => 'UpdraftPlus', 'by' => 'digitalisimo-backups', 'import' => 'backups_sets', 'delete' => 'kept_data', 'group' => 'updraftplus', 'imports' => 'Sus respaldos se quedan en su carpeta y DIGITALÍSIMO Backups los muestra para importarlos o restaurarlos; sus respaldos programados los hace desde ahora DIGITALÍSIMO Backups.' ),
		'backuply'      => array( 'file' => 'backuply.php', 'folder' => 'backuply', 'name' => '/^Backuply/i', 'label' => 'Backuply', 'by' => 'digitalisimo-backups', 'import' => 'backups_sets', 'delete' => 'never', 'group' => 'backuply', 'imports' => 'Sus respaldos se quedan en wp-content/backuply y DIGITALÍSIMO Backups los muestra para importarlos o restaurarlos.' ),
	);

	/** Texto de cada regla de borrado para el análisis y el informe. */
	const DELETE_TEXT = array(
		'content'   => 'Se borra al final si todo salió bien y ningún contenido lo necesita.',
		'import'    => 'Se borra al final si todo salió bien y su importación terminó.',
		'kept_data' => 'Se borra al final si todo salió bien y su desinstalación conserva sus respaldos; si no, se queda desactivado.',
		'never'     => 'Se desactiva y se queda instalado: su desinstalación no está verificada y podría borrar sus respaldos.',
		'theme'     => 'Se borra al final si el tema propio quedó activo, la portada responde igual y ningún sitio lo usa.',
	);

	/** Etiqueta de un plugin DIGITALÍSIMO o del tema propio. */
	public static function own_label( $slug ) {
		if ( self::THEME === $slug ) {
			return 'Tema DIGITALÍSIMO';
		}
		return self::OWN[ $slug ]['label'] ?? (string) $slug;
	}

	/** Archivo de un plugin DIGITALÍSIMO del mapa. */
	public static function own_file( $slug ) {
		return self::OWN[ $slug ]['file'] ?? '';
	}

	/** ¿Casa un plugin instalado (archivo y cabecera) con una fila de ROWS? Por archivo principal, nombre y carpeta. */
	public static function matches( array $rule, $file, array $data ) {
		$file   = (string) $file;
		$folder = false !== strpos( $file, '/' ) ? strstr( $file, '/', true ) : '';
		if ( basename( $file ) !== $rule['file'] || ! preg_match( (string) $rule['name'], (string) ( $data['Name'] ?? '' ) ) ) {
			return false;
		}
		return '' === (string) ( $rule['folder'] ?? '' ) || 0 === strpos( (string) $folder, (string) $rule['folder'] );
	}

	/**
	 * Filas del mapa presentes en esta instalación, con sus archivos. Pura respecto de sus argumentos: `$plugins` es lo de
	 * get_plugins(), `$elements` lo de Retire::installed() (archivo => datos con `group` y `label`).
	 *
	 * @return array<string,array> clave => fila.
	 */
	public static function rows( array $plugins, array $elements ) {
		$rows = array();
		foreach ( $elements as $file => $info ) {
			$group = (string) ( $info['group'] ?? '' );
			if ( ! isset( self::ELEMENTS_GROUPS[ $group ] ) ) {
				continue;
			}
			$def = self::ELEMENTS_GROUPS[ $group ];
			$key = $def['key'];
			if ( ! isset( $rows[ $key ] ) ) {
				$rows[ $key ] = array( 'key' => $key, 'kind' => 'plugin', 'label' => $def['label'], 'files' => array(), 'by' => 'digitalisimo-elements', 'import' => 'content', 'imports' => $def['import'], 'delete' => 'content', 'group' => $group, 'reason' => '' );
			}
			$rows[ $key ]['files'][ (string) $file ] = (string) ( $info['label'] ?? $file );
		}
		// Una fila por grupo (Rank Math con su PRO): se desactivan en una escritura, PRO antes, y se borran juntos.
		foreach ( self::ROWS as $rule ) {
			$key = (string) $rule['group'];
			foreach ( $plugins as $file => $data ) {
				if ( ! self::matches( $rule, (string) $file, (array) $data ) ) {
					continue;
				}
				if ( ! isset( $rows[ $key ] ) ) {
					$rows[ $key ] = array( 'key' => $key, 'kind' => 'plugin', 'label' => $rule['label'], 'files' => array(), 'by' => (string) $rule['by'], 'import' => (string) ( $rule['import'] ?? '' ), 'imports' => (string) ( $rule['imports'] ?? '' ), 'delete' => '' === (string) $rule['by'] ? '' : (string) ( $rule['delete'] ?? 'never' ), 'group' => $key, 'reason' => (string) ( $rule['reason'] ?? '' ) );
				}
				$rows[ $key ]['files'][ (string) $file ] = $rule['label'];
				$rows[ $key ]['label']                   = implode( ' y ', array_values( array_unique( $rows[ $key ]['files'] ) ) );
			}
		}
		// Un plugin que bloquea un grupo (Yoast Premium) deja ese grupo como está, con su motivo.
		foreach ( self::ROWS as $rule ) {
			if ( isset( $rows[ $rule['group'] ] ) && ! empty( $rule['blocks'] ) && isset( $rows[ $rule['blocks'] ] ) ) {
				$rows[ $rule['blocks'] ]['by']     = '';
				$rows[ $rule['blocks'] ]['delete'] = '';
				$rows[ $rule['blocks'] ]['reason'] = (string) $rule['reason'];
			}
		}
		return $rows;
	}

	/**
	 * Plugins instalados fuera de la fila que la declaran como dependencia (`Requires Plugins`, WordPress 6.5+): con
	 * alguno, la fila no se desactiva ni se borra (el dependiente se quedaría sin ella). Pura: `$plugins` es get_plugins().
	 *
	 * @return string[] nombres de los dependientes.
	 */
	public static function dependents( array $row, array $plugins ) {
		$slugs = array();
		foreach ( array_keys( (array) $row['files'] ) as $file ) {
			$slugs[] = false !== strpos( (string) $file, '/' ) ? strstr( (string) $file, '/', true ) : basename( (string) $file, '.php' );
		}
		$out = array();
		foreach ( $plugins as $file => $data ) {
			if ( isset( $row['files'][ $file ] ) ) {
				continue;
			}
			$requires = array_filter( array_map( 'trim', explode( ',', (string) ( $data['RequiresPlugins'] ?? '' ) ) ) );
			if ( array_intersect( $slugs, $requires ) ) {
				$out[] = (string) ( $data['Name'] ?? $file );
			}
		}
		return $out;
	}

	/**
	 * Lo que Yoast SEO tiene en uso y el importador de DIGITALÍSIMO SEO no trae (sólo trae seis metas de cada
	 * contenido). Con algo de esto, retirar Yoast cambiaría URLs, metas o verificaciones: Yoast se queda activo.
	 *
	 * @return string[]
	 */
	public static function yoast_gaps() {
		global $wpdb;
		$gaps   = array();
		$titles = (array) get_option( 'wpseo_titles', array() );
		if ( ! empty( $titles['stripcategorybase'] ) ) {
			$gaps[] = 'quitar la base de las categorías (sin ella, sus URL darían 404)';
		}
		if ( array_filter( (array) get_option( 'wpseo_taxonomy_meta', array() ) ) ) {
			$gaps[] = 'títulos y descripciones de categorías y etiquetas';
		}
		$main = (array) get_option( 'wpseo', array() );
		foreach ( array( 'googleverify', 'msverify', 'yandexverify', 'baiduverify', 'pinterestverify' ) as $key ) {
			if ( '' !== trim( (string) ( $main[ $key ] ?? '' ) ) ) {
				$gaps[] = 'códigos de verificación de buscadores';
				break;
			}
		}
		if ( isset( $wpdb->postmeta ) && $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM {$wpdb->postmeta} WHERE ( meta_key LIKE %s OR meta_key LIKE %s ) AND meta_value <> '' LIMIT 1", $wpdb->esc_like( '_yoast_wpseo_primary_' ) . '%', $wpdb->esc_like( '_yoast_wpseo_opengraph-' ) . '%' ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$gaps[] = 'categoría principal u Open Graph propios de algunos contenidos';
		}
		return $gaps;
	}

	/**
	 * Estado de UpdraftPlus que decide si puede borrarse: destino remoto, conjuntos de su historial y programación.
	 *
	 * @return array{remote:bool, history:int, interval:string}
	 */
	public static function updraft_state() {
		$get     = static function ( $key, $default ) {
			return is_multisite() ? get_site_option( $key, $default ) : get_option( $key, $default );
		};
		$service = $get( 'updraft_service', '' );
		$remote  = array_diff( array_filter( array_map( 'strval', (array) $service ) ), array( 'none', '' ) );
		$history = $get( 'updraft_backup_history', array() );
		$interval = (string) $get( 'updraft_interval', 'manual' );
		return array( 'remote' => (bool) $remote, 'history' => is_array( $history ) ? count( $history ) : 0, 'interval' => '' === $interval ? 'manual' : $interval );
	}

	/** Plugins DIGITALÍSIMO que hacen falta para las filas con reemplazo (y Backups siempre: el respaldo va primero). */
	public static function needs( array $rows, $theme_row = false ) {
		$needs = array( 'digitalisimo-backups' => true );
		foreach ( $rows as $row ) {
			if ( '' !== (string) $row['by'] && isset( self::OWN[ $row['by'] ] ) ) {
				$needs[ $row['by'] ] = true;
			}
		}
		if ( $theme_row ) {
			// El paso desde Hello convierte su cabecera y su pie en plantillas del Theme Builder de Elements.
			$needs['digitalisimo-elements'] = true;
		}
		return array_values( array_filter( self::ORDER, static function ( $slug ) use ( $needs ) {
			return isset( $needs[ $slug ] );
		} ) );
	}

	/** Plan de este sitio (o de la red con $network): filas, lo que hay que instalar y el tema. Sólo lee. */
	public static function plan( $network = false ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = (array) get_plugins();
		$rows    = self::rows( $plugins, Retire::installed() );
		foreach ( $rows as $key => $row ) {
			$rows[ $key ]['active'] = self::active_files( array_keys( $row['files'] ), $network );
			// Un plugin de terceros que depende de esta fila impide borrarla y, salvo en la familia de Elementor (cuyos
			// complementos siguen funcionando con el núcleo incluido en Elements), también desactivarla.
			$dependents                  = '' !== (string) $row['by'] ? self::dependents( $row, $plugins ) : array();
			$rows[ $key ]['dependents'] = $dependents;
			if ( $dependents && 'content' !== $row['import'] ) {
				$rows[ $key ]['by']     = '';
				$rows[ $key ]['delete'] = '';
				$rows[ $key ]['reason'] = sprintf( 'Lo necesita %s («Requires Plugins»): se queda como está.', implode( ', ', $dependents ) );
			}
		}
		// Yoast con ajustes que su importador no trae se queda activo, en cada sitio de la red que los use.
		if ( isset( $rows['yoast'] ) && '' !== (string) $rows['yoast']['by'] ) {
			$gaps = array();
			foreach ( $network && is_multisite() ? Retire::sites() : array( (int) get_current_blog_id() ) as $site_id ) {
				$switch = is_multisite() && (int) $site_id !== (int) get_current_blog_id();
				if ( $switch ) {
					switch_to_blog( (int) $site_id );
				}
				$gaps = array_merge( $gaps, self::yoast_gaps() );
				if ( $switch ) {
					restore_current_blog();
				}
			}
			if ( $gaps ) {
				$rows['yoast']['by']     = '';
				$rows['yoast']['delete'] = '';
				$rows['yoast']['reason'] = 'Yoast SEO tiene en uso lo que el importador de DIGITALÍSIMO SEO no trae (' . implode( '; ', array_values( array_unique( $gaps ) ) ) . '): se queda activo para no cambiar URL ni metas.';
			}
		}
		$theme = self::theme_state();
		return array(
			'rows'  => $rows,
			'needs' => self::needs( $rows, $theme['hello'] ),
			'theme' => $theme,
		);
	}

	/** Archivos activos en este sitio o en toda la red (con $network, también activados por sitio en algún sitio). */
	public static function active_files( array $files, $network = false ) {
		$active = array();
		foreach ( $files as $file ) {
			if ( self::is_active( $file ) || ( $network && is_multisite() && Retire::active_on_any_site( $file ) ) ) {
				$active[] = $file;
			}
		}
		return $active;
	}

	/** ¿Activo en este sitio o en toda la red? */
	public static function is_active( $file ) {
		if ( in_array( $file, (array) get_option( 'active_plugins', array() ), true ) ) {
			return true;
		}
		return is_multisite() && isset( ( (array) get_site_option( 'active_sitewide_plugins', array() ) )[ $file ] );
	}

	/** ¿Instalado? */
	public static function installed( $file ) {
		return '' !== (string) $file && defined( 'WP_PLUGIN_DIR' ) && is_file( WP_PLUGIN_DIR . '/' . $file );
	}

	/** Tema del sitio en curso: plantilla, hoja, si es Hello sin hijo, si es un hijo de Hello y si ya es el propio. */
	public static function theme_state() {
		$template   = function_exists( 'get_template' ) ? (string) get_template() : '';
		$stylesheet = function_exists( 'get_stylesheet' ) ? (string) get_stylesheet() : '';
		$own        = self::is_own_theme( $stylesheet );
		return array(
			'template'   => $template,
			'stylesheet' => $stylesheet,
			'name'       => function_exists( 'wp_get_theme' ) ? (string) wp_get_theme()->get( 'Name' ) : $stylesheet,
			'own'        => $own,
			'hello'      => ! $own && self::HELLO === $template,
			'child'      => ! $own && self::HELLO === $template && $stylesheet !== $template,
		);
	}

	/** ¿Es el tema propio? Por su carpeta habitual o por su `Update URI` (una copia en otra carpeta es el mismo tema). */
	public static function is_own_theme( $stylesheet ) {
		if ( self::THEME === (string) $stylesheet ) {
			return true;
		}
		if ( '' === (string) $stylesheet || ! function_exists( 'wp_get_theme' ) ) {
			return false;
		}
		$theme = wp_get_theme( (string) $stylesheet );
		return $theme->exists() && self::THEME_URI === (string) $theme->get( 'UpdateURI' );
	}

	/**
	 * ¿Puede borrarse ya esta fila? Pura: `$facts` trae lo que la tarea comprobó. Devuelve [bool, motivo]. Las barreras
	 * generales (respaldo, etapas, portada) las comprueba antes Full::cleanup().
	 *
	 * Hechos: active (algún archivo sigue activo en algún sitio), by_active (su reemplazo está activo), imported (su
	 * importación terminó en esta tarea), content (resumen del informe de contenido: done, ep_needed, late_kept, blocked,
	 * failed, review), keeps_data (UpdraftPlus: su desinstalación conserva lo necesario), uninstall_safe (ninguna rutina
	 * de desinstalación cargaría código junto al núcleo incluido).
	 *
	 * @return array{0:bool,1:string}
	 */
	public static function deletable( array $row, array $facts ) {
		if ( '' === (string) ( $row['by'] ?? '' ) || '' === (string) ( $row['delete'] ?? '' ) ) {
			return array( false, '' !== (string) ( $row['reason'] ?? '' ) ? (string) $row['reason'] : 'No tiene reemplazo en DIGITALÍSIMO.' );
		}
		$dependents = array_values( array_unique( array_merge( (array) ( $row['dependents'] ?? array() ), (array) ( $facts['dependents'] ?? array() ) ) ) );
		if ( $dependents ) {
			return array( false, sprintf( 'Lo necesita %s («Requires Plugins»): se queda instalado.', implode( ', ', $dependents ) ) );
		}
		if ( ! empty( $facts['active'] ) ) {
			return array( false, 'Sigue activo en algún sitio.' );
		}
		if ( empty( $facts['by_active'] ) ) {
			return array( false, sprintf( '%s no está activo.', self::own_label( $row['by'] ) ) );
		}
		switch ( $row['delete'] ) {
			case 'never':
				return array( false, self::DELETE_TEXT['never'] );
			case 'import':
				return empty( $facts['imported'] ) ? array( false, 'Su importación no terminó.' ) : array( true, '' );
			case 'kept_data':
				if ( empty( $facts['imported'] ) ) {
					return array( false, 'DIGITALÍSIMO Backups no comprobó sus respaldos.' );
				}
				if ( ! empty( $facts['remote'] ) ) {
					return array( false, 'Tiene un destino remoto configurado: sus copias remotas y su programación no pasan a DIGITALÍSIMO Backups. Se queda desactivado, sin borrar, para que puedas recuperar esas copias desde él.' );
				}
				if ( (int) ( $facts['unseen'] ?? 0 ) > 0 ) {
					return array( false, sprintf( 'Su historial tiene %d respaldos que DIGITALÍSIMO Backups no ve en su carpeta. Se queda desactivado, sin borrar.', (int) $facts['unseen'] ) );
				}
				return empty( $facts['keeps_data'] ) ? array( false, 'Su ajuste «borrar los datos al desinstalar» está encendido y su carpeta de respaldos no es la habitual: al borrarlo, DIGITALÍSIMO Backups dejaría de encontrar sus respaldos. Se queda desactivado.' ) : array( true, '' );
			case 'content':
				$content = (array) ( $facts['content'] ?? array() );
				if ( empty( $content['done'] ) ) {
					return array( false, 'La conversión del contenido no terminó.' );
				}
				if ( empty( $facts['uninstall_safe'] ) ) {
					return array( false, 'Su rutina de desinstalación cargaría su código junto al núcleo incluido en DIGITALÍSIMO Elements.' );
				}
				if ( 'element-pack' === $row['group'] && ( ! empty( $content['ep_needed'] ) || ! empty( $content['blocked'] ) || ! empty( $content['failed'] ) || ! empty( $content['review'] ) ) ) {
					return array( false, 'Queda contenido de Element Pack por convertir o por revisar (el informe dice cuál): Element Pack sigue haciendo falta.' );
				}
				if ( 'prime-slider' === $row['group'] && ( ! empty( $content['late_kept'] ) || ! empty( $content['failed'] ) ) ) {
					return array( false, 'Quedan widgets de Prime Slider sin convertir (el informe dice cuáles).' );
				}
				if ( 'core' === $row['group'] && empty( $facts['core_ready'] ) ) {
					return array( false, 'El núcleo incluido en DIGITALÍSIMO Elements no está disponible en esta instalación.' );
				}
				return array( true, '' );
			case 'theme':
				return empty( $facts['theme_ok'] ) ? array( false, (string) ( $facts['theme_reason'] ?? 'El tema propio no quedó activo.' ) ) : array( true, '' );
		}
		return array( false, 'Regla de borrado desconocida.' );
	}
}
