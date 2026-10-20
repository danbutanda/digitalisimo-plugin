<?php
/**
 * DIGITALÍSIMO: paso desde el tema anterior.
 *
 * Al activarse, el tema copia lo que el sitio ya tenía (logo, menús, CSS adicional, ajustes del tema anterior) sin
 * pisar nada que se haya editado después, y deja dos opciones del sitio: un registro pequeño con carga automática,
 * que el front lee para la paridad, y una bitácora sin carga automática. Después avisa a DIGITALÍSIMO Elements.
 * Nunca activa, habilita ni borra temas: la única llamada que cambia de tema es la reversión explícita.
 */
defined( 'ABSPATH' ) || exit;

add_action( 'after_switch_theme', 'digitalisimo_theme_after_switch', 20, 2 ); // Después de _wp_menus_changed (10).

/** Registro del paso (opción del sitio, carga automática, ~1 KB): origen, último tema, ajustes y modo de cabecera y pie. */
function digitalisimo_theme_switch_record() {
	$record = get_option( 'digitalisimo_theme_switch', array() );
	return is_array( $record ) ? $record : array();
}

/** Bitácora del paso (opción del sitio sin carga automática): hashes, omisiones, inventario y avisos. */
function digitalisimo_theme_switch_log() {
	$log = get_option( 'digitalisimo_theme_switch_log', array() );
	return is_array( $log ) ? $log : array();
}

/** Los seis interruptores de la pantalla de ajustes del tema anterior (`hello_elementor_settings_<clave>`). */
function digitalisimo_theme_hello_keys() {
	return array( 'description_meta_tag', 'skip_link', 'header_footer', 'page_title', 'hello_style', 'hello_theme' );
}

/**
 * Plantilla del tema que el Personalizador tiene activo mientras previsualiza éste: así la vista previa ya muestra la
 * paridad antes de activar. Fuera de la vista previa, cadena vacía.
 */
function digitalisimo_theme_preview_template() {
	$manager = $GLOBALS['wp_customize'] ?? null;
	if ( ! is_object( $manager ) || ! function_exists( 'is_customize_preview' ) || ! is_customize_preview() ) {
		return '';
	}
	$reader   = function () {
		return isset( $this->original_stylesheet ) ? (string) $this->original_stylesheet : '';
	};
	$original = (string) $reader->call( $manager );
	if ( '' === $original ) {
		return '';
	}
	$theme = wp_get_theme( $original );
	return $theme->exists() ? (string) $theme->get_template() : '';
}

/** Modo paridad: el sitio venía del tema Hello y no se revirtió (filtrable con `digitalisimo_theme_parity`). */
function digitalisimo_theme_parity() {
	$record = digitalisimo_theme_switch_record();
	if ( $record ) {
		$parity = 'hello' === ( $record['origin']['family'] ?? '' ) && empty( $record['reverted'] );
	} else {
		$parity = 'hello-elementor' === digitalisimo_theme_preview_template();
	}
	return (bool) apply_filters( 'digitalisimo_theme_parity', $parity, $record );
}

/** Ajustes del tema anterior: true significa que estaba apagado allí. En la vista previa sin registro se leen en vivo. */
function digitalisimo_theme_hello_settings() {
	$settings = array_fill_keys( digitalisimo_theme_hello_keys(), false );
	$record   = digitalisimo_theme_switch_record();
	if ( ! $record && 'hello-elementor' === digitalisimo_theme_preview_template() ) {
		foreach ( array_keys( $settings ) as $key ) {
			$settings[ $key ] = 'true' === get_option( 'hello_elementor_settings_' . $key );
		}
		return $settings;
	}
	$saved = isset( $record['hello'] ) && is_array( $record['hello'] ) ? $record['hello'] : array();
	foreach ( array_keys( $settings ) as $key ) {
		$settings[ $key ] = ! empty( $saved[ $key ] );
	}
	return $settings;
}

/* ------------------------------------------------------------------ Cerrojo */

/** Token del cerrojo que tiene esta petición ('' si ninguno). */
function digitalisimo_theme_lock_token( $token = null ) {
	static $held = '';
	if ( null !== $token ) {
		$held = (string) $token;
	}
	return $held;
}

/**
 * Toma el cerrojo del paso de forma atómica: INSERT IGNORE con un token propio (add_option no sirve, porque usa
 * INSERT … ON DUPLICATE KEY UPDATE y dos peticiones lo «tomarían» a la vez). Uno de más de 300 s se roba sólo si
 * nadie lo cambió entre la lectura y el robo.
 */
function digitalisimo_theme_lock() {
	global $wpdb;
	static $shutdown = false;
	if ( '' !== digitalisimo_theme_lock_token() || ! is_object( $wpdb ) ) {
		return false;
	}
	$key   = 'digitalisimo_theme_switch_lock';
	$token = function_exists( 'wp_generate_password' ) ? wp_generate_password( 20, false ) : bin2hex( random_bytes( 10 ) );
	$value = time() . '|' . $token;
	$taken = (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} ( option_name, option_value, autoload ) VALUES ( %s, %s, 'off' )", $key, $value ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	if ( 1 !== $taken ) {
		$held = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( '' !== $held && time() - (int) $held < 300 ) {
			return false;
		}
		if ( 1 !== (int) $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $value, $key, $held ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return false;
		}
	}
	wp_cache_delete( $key, 'options' );
	digitalisimo_theme_lock_token( $token );
	if ( ! $shutdown ) {
		$shutdown = true;
		register_shutdown_function( 'digitalisimo_theme_unlock' ); // Si la petición termina con un error, el cerrojo no queda tomado.
	}
	return true;
}

/** Suelta el cerrojo propio; nunca el de otra petición. */
function digitalisimo_theme_unlock() {
	global $wpdb;
	$token = digitalisimo_theme_lock_token();
	if ( '' === $token || ! is_object( $wpdb ) ) {
		return;
	}
	digitalisimo_theme_lock_token( '' );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value LIKE %s", 'digitalisimo_theme_switch_lock', '%|' . $wpdb->esc_like( $token ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	wp_cache_delete( 'digitalisimo_theme_switch_lock', 'options' );
}

/* ------------------------------------------------------------------ Paso desde el tema anterior */

/**
 * WordPress dispara el gancho en la petición siguiente al cambio (init 99), sea de la administración o del front, y
 * sólo después vacía `theme_switched`. Una excepción que escapara dejaría esa opción puesta y cada petición volvería a
 * fallar: se recoge aquí, queda en la bitácora y la importación se repite con «wp digitalisimo-theme import».
 */
function digitalisimo_theme_after_switch( $old_name, $old_theme = null ) {
	try {
		if ( ! $old_theme instanceof WP_Theme ) {
			$old_theme = wp_get_theme( (string) $old_name );
		}
		digitalisimo_theme_import_previous( $old_theme );
	} catch ( Throwable $e ) {
		digitalisimo_theme_log_failure( 'switch_failed', 'El paso desde el tema anterior se interrumpió', $e );
	}
}

/** Anota en la bitácora un error que no se dejó escapar (código, momento y mensaje acotado). */
function digitalisimo_theme_log_failure( $code, $what, Throwable $e ) {
	try {
		$log             = digitalisimo_theme_switch_log();
		$detail          = get_class( $e ) . ': ' . $e->getMessage();
		$detail          = function_exists( 'mb_substr' ) ? mb_substr( $detail, 0, 200 ) : substr( $detail, 0, 200 );
		$log['warnings'] = digitalisimo_theme_merge_warnings(
			$log['warnings'] ?? array(),
			array(
				array(
					'code'    => $code,
					'message' => sprintf( '%s (%s). El sitio sigue funcionando; repite la importación con «wp digitalisimo-theme import».', $what, $detail ),
				),
			)
		);
		update_option( 'digitalisimo_theme_switch_log', $log, false );
	} catch ( Throwable $ignored ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- la bitácora nunca debe tumbar la petición.
	}
}

/** Dónde ocurrió el paso: admin, cli, front o cron. */
function digitalisimo_theme_switch_via() {
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return 'cli';
	}
	if ( wp_doing_cron() ) {
		return 'cron';
	}
	return is_admin() ? 'admin' : 'front';
}

/** Añade avisos sin duplicar códigos (el último gana) y deja como mucho 50. */
function digitalisimo_theme_merge_warnings( $previous, array $current ) {
	$merged = array();
	foreach ( array_merge( array_values( (array) $previous ), array_values( $current ) ) as $warning ) {
		if ( is_array( $warning ) && ! empty( $warning['code'] ) ) {
			unset( $merged[ (string) $warning['code'] ] );
			$merged[ (string) $warning['code'] ] = array( 'code' => (string) $warning['code'], 'message' => (string) ( $warning['message'] ?? '' ) );
		}
	}
	return array_slice( array_values( $merged ), -50 );
}

/** Regla de escritura idempotente: destino ausente, igual al origen o igual a lo que escribió la importación anterior. */
function digitalisimo_theme_may_write( $key, $exists, $current, $value, array $hashes ) {
	if ( ! $exists ) {
		return true;
	}
	$hash = md5( serialize( $current ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
	return md5( serialize( $value ) ) === $hash || ( isset( $hashes[ $key ] ) && $hashes[ $key ] === $hash ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
}

/**
 * Importa lo que el sitio tenía en el tema anterior. Opciones: `dry_run` (no escribe ni avisa: devuelve lo que
 * guardaría), `child_css` (añade al CSS adicional el style.css del tema hijo) y `force` (permite llamarla fuera del
 * cambio de tema, como hace `wp digitalisimo-theme import`). Devuelve `array( record, log, restore, dry_run )` o WP_Error.
 */
function digitalisimo_theme_import_previous( $old, array $opts = array() ) {
	$opts = array_merge(
		array(
			'force'     => false,
			'dry_run'   => false,
			'child_css' => false,
			'via'       => '',
		),
		$opts
	);
	if ( ! $old instanceof WP_Theme ) {
		return new WP_Error( 'digitalisimo_theme_no_theme', 'Falta el tema anterior.' );
	}
	if ( ! $opts['force'] && ! doing_action( 'after_switch_theme' ) ) {
		return new WP_Error( 'digitalisimo_theme_not_switching', 'La importación automática sólo corre al activar el tema; para repetirla usa «wp digitalisimo-theme import».' );
	}
	$dry = ! empty( $opts['dry_run'] );
	$via = '' !== (string) $opts['via'] ? sanitize_key( (string) $opts['via'] ) : digitalisimo_theme_switch_via();
	if ( ! $dry && ! digitalisimo_theme_lock() ) {
		$log             = digitalisimo_theme_switch_log();
		$log['warnings'] = digitalisimo_theme_merge_warnings(
			$log['warnings'] ?? array(),
			array( array( 'code' => 'locked', 'message' => 'Otra petición estaba importando el tema anterior; ésta no escribió nada.' ) )
		);
		update_option( 'digitalisimo_theme_switch_log', $log, false );
		return new WP_Error( 'digitalisimo_theme_locked', 'Otra petición está importando el tema anterior. Inténtalo en unos minutos.' );
	}
	try {
		$result = digitalisimo_theme_import_work( $old, ! empty( $opts['child_css'] ), $dry, $via );
	} finally {
		if ( ! $dry ) {
			digitalisimo_theme_unlock(); // También si la importación lanzó una excepción.
		}
	}
	if ( ! $dry && $result['event'] ) {
		// Con el cerrojo ya suelto: DIGITALÍSIMO Elements convierte la cabecera y el pie con su propio cerrojo. Un fallo
		// suyo no deshace la importación ni deja el cambio de tema a medias.
		try {
			do_action( 'digitalisimo_theme_switched', $result['record'] + array( 'restore' => $result['restore'] ), $old );
		} catch ( Throwable $e ) {
			digitalisimo_theme_log_failure( 'switched_event_failed', 'La conversión de la cabecera y el pie del tema anterior se interrumpió', $e );
		}
	}
	return array(
		'record'  => $result['record'],
		'log'     => $result['log'],
		'restore' => $result['restore'],
		'dry_run' => $dry,
	);
}

/** Cuerpo de la importación (con el cerrojo tomado, salvo en dry_run). */
function digitalisimo_theme_import_work( WP_Theme $old, $child_css, $dry, $via ) {
	$now    = time();
	$record = digitalisimo_theme_switch_record();
	$log    = digitalisimo_theme_switch_log();
	$user   = (int) get_current_user_id();
	if ( ! $user && ! empty( $record['user'] ) ) {
		$user = (int) $record['user']; // En el front o en cron no hay usuario: se conserva el que activó antes.
	}

	// Sin origen: el tema anterior ya no existe o es este mismo tema.
	if ( ! $old->exists() || $old->get_template() === get_template() ) {
		if ( ! $record ) {
			$record = array(
				'v'      => 1,
				'state'  => 'done',
				'user'   => $user,
				'origin' => array(
					'family'     => 'none',
					'stylesheet' => '',
					'template'   => '',
					'version'    => '',
					'child'      => false,
					'time'       => $now,
				),
			);
			if ( ! $dry ) {
				update_option( 'digitalisimo_theme_switch', $record, true );
			}
		}
		return array( 'record' => $record, 'log' => $log, 'restore' => false, 'event' => false );
	}

	$stylesheet = (string) $old->get_stylesheet();
	$template   = (string) $old->get_template();
	$family     = 'hello-elementor' === $template ? 'hello' : 'other';
	$child      = $stylesheet !== $template;
	$parent     = $old->parent();
	$version    = (string) ( $parent ? $parent->get( 'Version' ) : $old->get( 'Version' ) );
	$warnings   = array();
	$warn       = function ( $code, $message ) use ( &$warnings ) {
		$warnings[ $code ] = array( 'code' => $code, 'message' => $message );
	};
	if ( in_array( $template, array( 'hello-biz', 'hello-commerce' ), true ) ) {
		$warn( 'hello_variant', sprintf( 'El tema anterior (%s) es una variante de Hello: se importó como un tema cualquiera, sin paridad.', $template ) );
	}
	if ( defined( 'HELLOPLUS_VERSION' ) ) {
		$warn( 'hello_plus', 'El plugin Hello+ está activo: sus widgets sólo funcionan con los temas Hello y no se convierten.' );
	}
	if ( 'hello' === $family && '' !== $version && 0 !== strpos( $version, '3.5.' ) ) {
		$warn( 'hello_version', sprintf( 'El tema anterior estaba en la versión %s; la paridad copia la 3.5.1 y puede haber diferencias.', $version ) );
	}

	// El origen no cambia una vez es Hello: un rodeo por otro tema conserva la paridad.
	$return_trip = 'hello' === ( $record['origin']['family'] ?? '' );
	if ( ! $return_trip ) {
		$record['origin'] = array(
			'family'     => $family,
			'stylesheet' => $stylesheet,
			'template'   => $template,
			'version'    => $version,
			'child'      => $child,
			'time'       => $now,
		);
	}
	$record['v']    = 1;
	$record['user'] = $user;
	$record['last'] = array(
		'stylesheet' => $stylesheet,
		'template'   => $template,
		'family'     => $family,
		'time'       => $now,
		'via'        => $via,
	);
	unset( $record['reverted'] ); // El tema vuelve a estar activo.
	$restore      = $return_trip; // Vuelta al tema: DIGITALÍSIMO Elements restaura las plantillas que retiró.
	$origin_hello = 'hello' === $record['origin']['family'];

	$hashes  = isset( $log['mods'] ) && is_array( $log['mods'] ) ? $log['mods'] : array();
	$skipped = array();
	$source  = get_option( 'theme_mods_' . $stylesheet, array() );
	$source  = is_array( $source ) ? $source : array();
	$target  = 'theme_mods_' . get_option( 'stylesheet' );
	$mods    = get_option( $target, array() );
	$mods    = is_array( $mods ) ? $mods : array();
	$changed = false;

	// T1 · Logo: sólo un adjunto de imagen.
	$logo = (int) ( $source['custom_logo'] ?? 0 );
	if ( $logo > 0 && wp_attachment_is_image( $logo ) ) {
		if ( digitalisimo_theme_may_write( 'custom_logo', ! empty( $mods['custom_logo'] ), $mods['custom_logo'] ?? null, $logo, $hashes ) ) {
			$changed               = $changed || ( $mods['custom_logo'] ?? null ) !== $logo;
			$mods['custom_logo']   = $logo;
			$hashes['custom_logo'] = md5( serialize( $logo ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		} else {
			$skipped['custom_logo'] = 'editado en destino';
		}
	}

	// T2 · Menús de las ubicaciones menu-1 y menu-2 (WordPress no los traslada si el cambio vino del Personalizador).
	$from_locations = isset( $source['nav_menu_locations'] ) && is_array( $source['nav_menu_locations'] ) ? $source['nav_menu_locations'] : array();
	$locations      = isset( $mods['nav_menu_locations'] ) && is_array( $mods['nav_menu_locations'] ) ? $mods['nav_menu_locations'] : array();
	foreach ( array( 'menu-1', 'menu-2' ) as $location ) {
		$menu = (int) ( $from_locations[ $location ] ?? 0 );
		if ( $menu <= 0 || ! is_nav_menu( $menu ) ) {
			continue;
		}
		$key = 'nav_menu_locations[' . $location . ']';
		if ( digitalisimo_theme_may_write( $key, ! empty( $locations[ $location ] ), (int) ( $locations[ $location ] ?? 0 ), $menu, $hashes ) ) {
			$changed                = $changed || (int) ( $locations[ $location ] ?? 0 ) !== $menu;
			$locations[ $location ] = $menu;
			$hashes[ $key ]         = md5( serialize( $menu ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		} else {
			$skipped[ $key ] = 'editado en destino';
		}
	}
	if ( $locations ) {
		$mods['nav_menu_locations'] = $locations;
	}

	// T4 · Resto de los ajustes del Personalizador.
	$excluded = array( 'sidebars_widgets', 'custom_css_post_id', 'hello-header-footer', 'wp_classic_sidebars', 'custom_logo', 'nav_menu_locations' );
	foreach ( $source as $key => $value ) {
		if ( is_int( $key ) || ctype_digit( (string) $key ) || in_array( (string) $key, $excluded, true ) ) {
			continue;
		}
		$exists = array_key_exists( $key, $mods );
		if ( digitalisimo_theme_may_write( (string) $key, $exists, $exists ? $mods[ $key ] : null, $value, $hashes ) ) {
			$changed      = $changed || ! $exists || serialize( $mods[ $key ] ) !== serialize( $value ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
			$mods[ $key ] = $value;
			$hashes[ (string) $key ] = md5( serialize( $value ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		} else {
			$skipped[ (string) $key ] = 'editado en destino';
		}
	}
	if ( $skipped ) {
		$warn( 'mods_kept', sprintf( 'Se conservaron %d ajustes que ya se habían editado en este tema: %s.', count( $skipped ), implode( ', ', array_keys( $skipped ) ) ) );
	}
	if ( $changed && ! $dry ) {
		update_option( $target, $mods );
	}

	// T3 · CSS adicional (sólo desde Hello) y, si se pide, el style.css del tema hijo.
	$css_log = isset( $log['custom_css'] ) && is_array( $log['custom_css'] ) ? $log['custom_css'] : array();
	$current = (string) wp_get_custom_css( get_stylesheet() );
	$write   = null;
	$own     = false;
	if ( 'hello' === $family ) {
		$from_css = (string) wp_get_custom_css( $stylesheet );
		if ( '' !== trim( $from_css ) ) {
			$blocks = digitalisimo_theme_child_css_blocks( $current );
			$body   = trim( digitalisimo_theme_child_css_blocks( $current, true ) );
			if ( '' === $body || $body === trim( $from_css ) || ( ! empty( $css_log['hash'] ) && md5( $current ) === $css_log['hash'] ) ) {
				$own   = true;
				$write = rtrim( $from_css ) . ( $blocks ? "\n\n" . implode( "\n\n", $blocks ) . "\n" : '' );
			} else {
				$skipped['custom_css'] = 'editado en destino';
				$warn( 'custom_css_kept', 'Este tema ya tenía otro CSS adicional: se conservó y no se copió el del tema anterior.' );
			}
		}
	}

	// T10 · Inventario del hijo y de los filtros del tema anterior.
	if ( $child ) {
		$log['child'] = digitalisimo_theme_child_inventory( $old );
		if ( ! empty( $log['child']['php_files'] ) ) {
			$warn( 'child_php', sprintf( 'El tema hijo %s tenía %d archivos PHP que este tema no carga: pásalos a fragmentos de DIGITALÍSIMO Tools.', $stylesheet, count( $log['child']['php_files'] ) ) );
		}
		if ( $child_css ) {
			$style = digitalisimo_theme_child_style( $old );
			if ( preg_match( '/@import\b/i', $style ) ) {
				$warn( 'child_css_import', 'El style.css del tema hijo usa @import: no se copió nada al CSS adicional.' );
			} elseif ( '' !== trim( $style ) ) {
				$base  = null !== $write ? $write : $current;
				$write = digitalisimo_theme_add_child_css( $base, $stylesheet, digitalisimo_theme_absolute_urls( $style, trailingslashit( get_theme_root_uri( $stylesheet ) ) . $stylesheet . '/' ) );
			}
		} elseif ( ! empty( $log['child']['style_bytes'] ) ) {
			$warn( 'child_css', 'El style.css del tema hijo no se copió: «wp digitalisimo-theme import --child-css» lo añade al CSS adicional.' );
		}
	} elseif ( 'hello' === $family ) {
		$log['child'] = array();
	}
	if ( $origin_hello ) {
		$log['filters'] = digitalisimo_theme_hello_filters();
	}
	if ( null !== $write && $write !== $current ) {
		$post_id = 0;
		if ( ! $dry ) {
			$preprocessed = '';
			if ( $own && function_exists( 'wp_get_custom_css_post' ) ) {
				$from_post    = wp_get_custom_css_post( $stylesheet );
				$preprocessed = $from_post && rtrim( (string) $from_post->post_content ) === rtrim( $write ) ? (string) $from_post->post_content_filtered : '';
			}
			$saved   = wp_update_custom_css_post( $write, array( 'stylesheet' => get_stylesheet(), 'preprocessed' => $preprocessed ) );
			$post_id = is_object( $saved ) && isset( $saved->ID ) ? (int) $saved->ID : 0;
		}
		if ( $own ) {
			$from_post         = function_exists( 'wp_get_custom_css_post' ) ? wp_get_custom_css_post( $stylesheet ) : null;
			$log['custom_css'] = array(
				'from_post' => $from_post && isset( $from_post->ID ) ? (int) $from_post->ID : 0,
				'to_post'   => $post_id,
				'hash'      => md5( $write ),
			);
		}
	}

	// T5 · Ajustes del tema anterior (true = estaba apagado allí). T6 · Modo de su cabecera y su pie.
	if ( 'hello' === $family ) {
		$hello = array();
		foreach ( digitalisimo_theme_hello_keys() as $key ) {
			$hello[ $key ] = 'true' === get_option( 'hello_elementor_settings_' . $key );
		}
		$record['hello']         = $hello;
		$record['header_footer'] = digitalisimo_theme_header_footer_modes( $hello );
		foreach ( array( 'header', 'footer' ) as $part ) {
			if ( 'tb_partial' === $record['header_footer'][ $part ] ) {
				$warn( 'tb_partial_' . $part, 'La plantilla de ' . ( 'header' === $part ? 'cabecera' : 'pie' ) . ' del constructor tiene exclusiones: en esas páginas se verá la ' . ( 'header' === $part ? 'cabecera' : 'pie' ) . ' sencilla del tema.' );
			}
		}
	} elseif ( ! $origin_hello ) {
		unset( $record['hello'], $record['header_footer'] );
	}

	// T9 · Meta description: el tema nunca la imprime.
	if ( 'hello' === $family ) {
		$hello_printed      = empty( $record['hello']['description_meta_tag'] );
		$seo_prints         = class_exists( 'Digitalisimo_Integrations_Settings' ) && Digitalisimo_Integrations_Settings::get( 'enable_seo' );
		$log['description'] = array( 'hello_printed' => $hello_printed, 'seo_prints' => (bool) $seo_prints );
		if ( $hello_printed && ! $seo_prints ) {
			$warn( 'description_lost', 'El tema anterior imprimía la meta description con el extracto y este tema no lo hace: activa el SEO de Digitalisimo SEO para conservarla.' );
		}
	}

	$pending         = ! empty( $record['header_footer']['pending'] );
	$record['state'] = ( $warnings || $pending ) ? 'partial' : 'done';
	$log['time']     = $now;
	$log['user']     = $user;
	$log['via']      = $via;
	$log['mods']     = $hashes;
	$log['skipped']  = $skipped;
	$log['warnings'] = digitalisimo_theme_merge_warnings( $log['warnings'] ?? array(), $warnings );
	if ( ! $dry ) {
		update_option( 'digitalisimo_theme_switch', $record, true );
		update_option( 'digitalisimo_theme_switch_log', $log, false );
	}
	return array( 'record' => $record, 'log' => $log, 'restore' => $restore, 'event' => true );
}

/* ------------------------------------------------------------------ T6 · cabecera y pie del tema anterior */

/** ¿Estaba activo el experimento «cabecera y pie» del tema anterior? Lee la opción y el historial de instalación. */
function digitalisimo_theme_hello_experiment() {
	$state = (string) get_option( 'elementor_experiment-hello-theme-header-footer', '' );
	if ( 'active' === $state ) {
		return true;
	}
	if ( '' !== $state && 'default' !== $state ) {
		return false;
	}
	$history = get_option( 'elementor_install_history', array() );
	$first   = is_array( $history ) && $history ? (string) key( $history ) : '';
	$first   = (string) preg_replace( '/-(beta|cloud|dev)\d*$/', '', $first );
	return '' !== $first && version_compare( $first, '3.3.0', '>=' );
}

/** 'tb' si una plantilla publicada de la ubicación se aplica a todo el sitio, 'tb_partial' si tiene exclusiones, '' si no. */
function digitalisimo_theme_builder_mode( $part ) {
	$conditions = get_option( 'elementor_pro_theme_builder_conditions', array() );
	$templates  = is_array( $conditions ) && isset( $conditions[ $part ] ) && is_array( $conditions[ $part ] ) ? $conditions[ $part ] : array();
	$mode       = '';
	foreach ( $templates as $post_id => $listed ) {
		if ( 'publish' !== get_post_status( (int) $post_id ) ) {
			continue;
		}
		$saved = get_post_meta( (int) $post_id, '_elementor_conditions', true );
		$list  = array_map( 'strval', (array) ( is_array( $saved ) && $saved ? $saved : $listed ) );
		if ( ! in_array( 'include/general', $list, true ) ) {
			continue;
		}
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

/**
 * Modo de la cabecera y del pie con el tema anterior: disabled (apagados en sus ajustes), tb / tb_partial (plantilla
 * del constructor), dynamic (cabecera y pie configurables del tema), hidden (sólo la cabecera: dynamic con todo
 * apagado) o classic.
 */
function digitalisimo_theme_header_footer_modes( array $hello ) {
	$experiment = digitalisimo_theme_hello_experiment();
	$kit_id     = (int) get_option( 'elementor_active_kit', 0 );
	$kit        = $kit_id ? get_post_meta( $kit_id, '_elementor_page_settings', true ) : array();
	$kit        = is_array( $kit ) ? $kit : array();
	$switches   = array(
		'header' => array( 'hello_header_logo_display', 'hello_header_tagline_display', 'hello_header_menu_display' ),
		'footer' => array( 'hello_footer_logo_display', 'hello_footer_tagline_display', 'hello_footer_menu_display', 'hello_footer_copyright_display' ),
	);
	$modes      = array(
		'experiment' => $experiment,
		'header'     => 'classic',
		'footer'     => 'classic',
		'kit_id'     => $kit_id,
		'pending'    => false,
		'blocked'    => '',
	);
	foreach ( $switches as $part => $keys ) {
		if ( ! empty( $hello['header_footer'] ) ) {
			$modes[ $part ] = 'disabled';
			continue;
		}
		$builder = digitalisimo_theme_builder_mode( $part );
		if ( '' !== $builder ) {
			$modes[ $part ] = $builder;
			continue;
		}
		if ( $experiment ) {
			// Sólo la cabecera se oculta del todo (hello_get_header_display()); el pie, con todo apagado, Hello lo sigue
			// imprimiendo vacío (footer.site-footer.dynamic-footer), y su plantilla generada es ese contenedor vacío.
			$hidden = 'header' === $part;
			foreach ( $keys as $key ) {
				$hidden = $hidden && array_key_exists( $key, $kit ) && '' === $kit[ $key ]; // Uno ausente vale «yes».
			}
			$modes[ $part ] = $hidden ? 'hidden' : 'dynamic';
		}
	}
	$modes['pending'] = 'dynamic' === $modes['header'] || 'dynamic' === $modes['footer'];
	return $modes;
}

/* ------------------------------------------------------------------ T10 · tema hijo y filtros */

/** style.css del tema hijo sin su cabecera. */
function digitalisimo_theme_child_style( WP_Theme $child ) {
	$file = trailingslashit( $child->get_stylesheet_directory() ) . 'style.css';
	$text = is_readable( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	return (string) preg_replace( '#^\s*/\*.*?Theme Name\s*:.*?\*/#is', '', $text, 1 );
}

/** Lo que el hijo aportaba y este tema no carga. Sólo lee: nunca ejecuta ni copia su PHP. */
function digitalisimo_theme_child_inventory( WP_Theme $child ) {
	$dir   = untrailingslashit( (string) $child->get_stylesheet_directory() );
	$php   = array();
	$deps  = false;
	if ( is_dir( $dir ) ) {
		// Una subcarpeta ilegible se salta (CATCH_GET_CHILD); si ni la carpeta del hijo se puede abrir, no hay lista.
		try {
			$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD );
			foreach ( $files as $file ) {
				if ( count( $php ) >= 200 ) {
					break;
				}
				if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) ) {
					continue;
				}
				$php[] = ltrim( str_replace( '\\', '/', substr( $file->getPathname(), strlen( $dir ) ) ), '/' );
				if ( ! $deps && $file->getSize() <= MB_IN_BYTES && $file->isReadable() ) {
					$deps = false !== strpos( (string) file_get_contents( $file->getPathname() ), 'hello-elementor' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				}
			}
		} catch ( UnexpectedValueException $e ) {
			$php = array();
		}
		sort( $php );
	}
	$style     = digitalisimo_theme_child_style( $child );
	$selectors = array();
	foreach ( array( '.site-header', '.site-branding', '.site-navigation', '.header-inner', '.site-footer' ) as $selector ) {
		if ( false !== strpos( $style, $selector ) ) {
			$selectors[] = $selector;
		}
	}
	return array(
		'php_files'           => $php,
		'style_bytes'         => strlen( trim( $style ) ),
		'page_templates'      => digitalisimo_theme_child_page_templates( $child ),
		'selectors'           => $selectors,
		'enqueues_hello_deps' => $deps,
	);
}

/** Plantillas de página que sólo existen en el hijo y cuántos contenidos las usan. */
function digitalisimo_theme_child_page_templates( WP_Theme $child ) {
	$own = array();
	foreach ( (array) $child->get_post_templates() as $list ) {
		$own += (array) $list;
	}
	$parent = $child->parent();
	if ( $parent ) {
		foreach ( (array) $parent->get_post_templates() as $list ) {
			foreach ( array_keys( (array) $list ) as $file ) {
				unset( $own[ $file ] );
			}
		}
	}
	$out = array();
	foreach ( array_keys( $own ) as $file ) {
		$ids   = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => '_wp_page_template', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => (string) $file, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		$out[] = array( 'template' => (string) $file, 'pages' => count( (array) $ids ) );
	}
	return $out;
}

/** Callbacks enganchados ahora a los filtros del tema anterior (fragmentos y plugins que dejarían de aplicarse). */
function digitalisimo_theme_hello_filters() {
	$out = array();
	foreach ( (array) ( $GLOBALS['wp_filter'] ?? array() ) as $hook => $object ) {
		$hook = (string) $hook;
		if ( 0 !== strpos( $hook, 'hello_elementor_' ) && 0 !== strpos( $hook, 'hello-plus-theme/' ) ) {
			continue;
		}
		$count     = 0;
		$callbacks = is_object( $object ) && isset( $object->callbacks ) ? $object->callbacks : ( is_array( $object ) ? $object : array() );
		foreach ( (array) $callbacks as $list ) {
			$count += count( (array) $list );
		}
		if ( $count ) {
			$out[ $hook ] = $count;
		}
	}
	ksort( $out );
	return $out;
}

/** Bloques del hijo ya añadidos al CSS adicional (o, con $rest, el CSS sin ellos). */
function digitalisimo_theme_child_css_blocks( $css, $rest = false ) {
	$pattern = '#/\* digitalisimo-theme: hijo ([^*]+?) \*/.*?/\* /digitalisimo-theme: hijo \1 \*/#s';
	if ( $rest ) {
		return (string) preg_replace( $pattern, '', (string) $css );
	}
	return preg_match_all( $pattern, (string) $css, $found ) ? $found[0] : array();
}

/** Añade (o sustituye) el bloque delimitado del hijo al final del CSS adicional. */
function digitalisimo_theme_add_child_css( $css, $stylesheet, $style ) {
	$open  = '/* digitalisimo-theme: hijo ' . $stylesheet . ' */';
	$close = '/* /digitalisimo-theme: hijo ' . $stylesheet . ' */';
	$css   = rtrim( (string) preg_replace( '#' . preg_quote( $open, '#' ) . '.*?' . preg_quote( $close, '#' ) . '#s', '', (string) $css ) );
	return ( '' !== $css ? $css . "\n\n" : '' ) . $open . "\n" . trim( $style ) . "\n" . $close . "\n";
}

/** Los url() relativos del style.css del hijo pasan a absolutos (el CSS adicional se imprime en otra dirección). */
function digitalisimo_theme_absolute_urls( $css, $base ) {
	return (string) preg_replace_callback(
		'#url\(\s*([\'"]?)(?![a-z][a-z0-9+.-]*:|/|\#|%23)([^\'")]+)\1\s*\)#i',
		function ( $match ) use ( $base ) {
			$path = 0 === strpos( $match[2], './' ) ? substr( $match[2], 2 ) : $match[2]; // «../» lo resuelve el navegador.
			return 'url(' . $match[1] . $base . $path . $match[1] . ')';
		},
		(string) $css
	);
}

/* ------------------------------------------------------------------ Reversión */

/** Plantillas generadas que siguen publicadas (parte => id). */
function digitalisimo_theme_live_generated( array $record ) {
	$live = array();
	foreach ( (array) ( $record['generated'] ?? array() ) as $part => $data ) {
		$id = (int) ( is_array( $data ) ? ( $data['id'] ?? 0 ) : 0 );
		if ( $id > 0 && 'publish' === get_post_status( $id ) ) {
			$live[ (string) $part ] = $id;
		}
	}
	return $live;
}

/**
 * Vuelve al tema anterior (`last`). Sin DIGITALÍSIMO Elements no puede retirar las plantillas que éste generó:
 * devuelve un error, salvo con `force`, que las deja en borrador. Nunca escribe ajustes del tema anterior.
 */
function digitalisimo_theme_revert( array $opts = array() ) {
	$force  = ! empty( $opts['force'] );
	$record = digitalisimo_theme_switch_record();
	$target = (string) ( $record['last']['stylesheet'] ?? '' );
	if ( '' === $target ) {
		return new WP_Error( 'digitalisimo_theme_no_previous', 'No hay un tema anterior registrado al que volver.' );
	}
	$theme = wp_get_theme( $target );
	if ( ! $theme->exists() ) {
		return new WP_Error( 'digitalisimo_theme_missing', sprintf( 'El tema anterior (%s) ya no está instalado.', $target ) );
	}
	if ( is_multisite() && ! $theme->is_allowed( 'both', get_current_blog_id() ) ) {
		return new WP_Error( 'digitalisimo_theme_not_allowed', sprintf( 'El tema anterior (%s) no está habilitado en este sitio de la red.', $target ) );
	}
	if ( ! digitalisimo_theme_lock() ) {
		return new WP_Error( 'digitalisimo_theme_locked', 'Otra petición está importando o revirtiendo el tema. Inténtalo en unos minutos.' );
	}
	$live = digitalisimo_theme_live_generated( $record );
	if ( $live && ! has_action( 'digitalisimo_theme_reverted' ) ) {
		if ( ! $force ) {
			digitalisimo_theme_unlock();
			return new WP_Error( 'digitalisimo_theme_needs_elements', 'Las plantillas de cabecera y pie generadas al activar el tema sólo las retira DIGITALÍSIMO Elements: actívalo, o repite con --force para dejarlas en borrador.' );
		}
		foreach ( $live as $part => $id ) {
			wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
			$record['generated'][ $part ]['retired'] = 'draft';
		}
	}
	$record['reverted'] = time();
	$record['state']    = 'reverted';
	update_option( 'digitalisimo_theme_switch', $record, true );
	do_action( 'digitalisimo_theme_reverted', $record );
	digitalisimo_theme_unlock();
	switch_theme( $target ); // La única llamada que cambia de tema: la pide el administrador.
	return true;
}
