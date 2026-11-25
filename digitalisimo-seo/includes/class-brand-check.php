<?php
defined( 'ABSPATH' ) || exit;

/**
 * Imágenes de marca que leen Google y las redes: la imagen social por defecto (og:image y twitter:image) y el logo
 * (logo e image del schema). Auditoría SEO del 2026-11-24, punto A1: en un sitio de cliente todas apuntaban a un dominio
 * que ya no existía, así que ninguna página mostraba imagen al compartirse, y el logo del schema era el de una demo.
 *
 * - La comprobación HTTP nunca corre al pintar el front: va en el cron (`run()`), al guardar los ajustes o cuando el
 *   escritorio ve que cambiaron las imágenes (`refresh()`), y se repite cada día. Un archivo de la biblioteca de este
 *   sitio se comprueba en el disco, sin petición. El resultado queda en una opción con autoload y el front sólo lo lee.
 * - Sólo un fallo inequívoco (el dominio no existe, 404 o 410, no es una imagen, el archivo ya no está) que se repite
 *   en dos comprobaciones separadas deja de publicar la imagen (revisión del 2026-11-25): se usa la de reserva ya
 *   comprobada (el logo del tema y luego el icono del sitio; para la imagen social, antes el logo SEO) y, si no hay
 *   ninguna buena, no se publica imagen. Lo dudoso (403 de un cortafuegos, 401, 429, 5xx, sin respuesta, peticiones
 *   externas bloqueadas) nunca la quita: se publica y se avisa que no se pudo comprobar. Se pide con el agente de un
 *   navegador. Un logo con nombre de demo no se publica. Una imagen en otro dominio que sí responde se publica, con aviso.
 * - Mientras no hay comprobación, se publica lo configurado (como antes): un sitio sin imágenes de marca no cambia.
 * - Los avisos (`warnings()`) salen en el Escritorio y en las pantallas DIGITALÍSIMO, y en la ability
 *   `digitalisimo-seo/get-site-status` del MCP. Incluyen lo del schema que no se publica o que conviene confirmar (A2:
 *   nombre con emoji, datos del negocio que faltan, horario 24×7, perfiles para sameAs) y el texto fijo de las
 *   plantillas de título y descripción (A3), que configuró el sitio y por eso no se cambia solo.
 */
class Digitalisimo_Integrations_Brand_Check {
	const OPTION   = 'digitalisimo_seo_brand_check';
	const HOOK     = 'digitalisimo_seo_brand_check';
	const DAILY    = 'daily';
	const RETRY    = 'retry';
	/** Un fallo inequívoco deja de publicar la imagen sólo si se repite en otra comprobación al menos así de después. */
	const CONFIRM_AFTER = 1800;
	/** Navegador normal: un cortafuegos que bloquea agentes desconocidos daría un falso «roto». */
	const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
	/** Google pide un logo de al menos 112×112 px. */
	const MIN_LOGO = 112;
	/**
	 * Archivos de demo o de relleno con certeza: los que dejan los instaladores y kits conocidos (Softaculous, el
	 * marcador de WooCommerce, «logo-demo», «dummy-image», «lorem ipsum»). Sólo éstos se dejan de publicar.
	 */
	const DEMO = '/softaculous|woocommerce[-_]placeholder|^placeholder(?:[-_]\d+x\d+)?\.[a-z]+$|lorem[-_]?ipsum|dummy[-_]?(?:image|img|logo)|(?:^|[-_.])(?:logo[-_]demo|demo[-_]logo)(?:[-_.]|$)/i';
	/** Servicios de imágenes de relleno y subdominios de demo («demo.tema.com»): también con certeza. */
	const DEMO_HOSTS = '/(?:^|\.)(?:placehold\.co|placehold\.it|placeholder\.com|dummyimage\.com|picsum\.photos|loremflickr\.com)$|^(?:demos?\d*|demo-[a-z0-9-]+)\./i';
	/** Nombres que sugieren una demo sin certeza («sample-bakery-logo.png», «demo-day.jpg»): sólo se avisa. */
	const MAYBE_DEMO = '/(?:^|[-_.])(?:demo|sample|dummy|placeholder)(?:[-_.]|$)/i';

	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		add_action( 'update_option_digitalisimo_integrations_options', array( __CLASS__, 'changed' ) );
		add_action( 'add_option_digitalisimo_integrations_options', array( __CLASS__, 'changed' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
	}

	private static function o( $key, $default = '' ) { return Digitalisimo_Integrations_SEO_Resolver::option( $key, $default ); }

	/* ------------------------------------------------------------------ *
	 * Front: sólo lee el resultado guardado
	 * ------------------------------------------------------------------ */

	/** Resultado de la última comprobación. */
	public static function state() {
		$state = get_option( self::OPTION, array() );
		return is_array( $state ) ? $state : array();
	}

	/** ¿Es con certeza una imagen de demo o de relleno (por su archivo o por su dominio)? */
	public static function looks_demo( $url ) {
		$path = (string) wp_parse_url( (string) $url, PHP_URL_PATH );
		$host = strtolower( (string) wp_parse_url( (string) $url, PHP_URL_HOST ) );
		return ( '' !== $path && 1 === preg_match( self::DEMO, basename( $path ) ) ) || ( '' !== $host && 1 === preg_match( self::DEMO_HOSTS, $host ) );
	}

	/** ¿El nombre sugiere una demo, sin certeza? Sólo se avisa: la imagen se sigue publicando. */
	public static function maybe_demo( $url ) {
		$path = (string) wp_parse_url( (string) $url, PHP_URL_PATH );
		return ! self::looks_demo( $url ) && '' !== $path && 1 === preg_match( self::MAYBE_DEMO, basename( $path ) );
	}

	/** Una fila de la comprobación que no se puede publicar. */
	public static function bad( $row ) {
		return is_array( $row ) && ( false === ( $row['ok'] ?? null ) || ! empty( $row['demo'] ) );
	}

	/**
	 * URL que se publica para una imagen de marca: 'image' (og:image, twitter:image, image del schema) o 'logo'. La
	 * configurada si está bien o aún no se comprobó; si falla, la de reserva comprobada; si no hay, ''.
	 */
	public static function url( $kind ) {
		$configured = trim( (string) self::o( 'logo' === $kind ? 'seo_logo' : 'seo_default_image' ) );
		if ( '' === $configured ) return '';
		$state = self::state();
		$row   = $state['urls'][ $configured ] ?? null;
		// El nombre de demo se ve sin petición: no se publica ni antes de la primera comprobación.
		if ( ! self::bad( $row ) && ! self::looks_demo( $configured ) ) return $configured;
		$fallback = (string) ( $state[ 'fallback_' . ( 'logo' === $kind ? 'logo' : 'image' ) ] ?? '' );
		return $fallback !== $configured ? $fallback : '';
	}

	/** Emojis y pictogramas (no ©, ® ni ™, que son parte de muchos nombres) y sus modificadores. */
	const EMOJI = '/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{2300}-\x{23FF}\x{1F1E6}-\x{1F1FF}\x{FE0E}\x{FE0F}\x{200D}\x{20E3}\x{E0020}-\x{E007F}]/u';

	public static function has_emoji( $text ) { return 1 === preg_match( self::EMOJI, (string) $text ); }

	/**
	 * Nombre sin emojis para el schema y og:site_name (auditoría A2: «DIGITALÍSIMO 🥇»). Un nombre que sólo era emoji
	 * se deja como estaba, para no publicar un nombre vacío.
	 */
	public static function plain_name( $name ) {
		$name  = (string) $name;
		$plain = trim( (string) preg_replace( '/\s+/u', ' ', (string) preg_replace( self::EMOJI, '', $name ) ) );
		$plain = trim( (string) preg_replace( '/^[\s|·•\-–—]+|[\s|·•\-–—]+$/u', '', $plain ) );
		return '' !== $plain ? $plain : $name;
	}

	/* ------------------------------------------------------------------ *
	 * Comprobación en segundo plano
	 * ------------------------------------------------------------------ */

	/** Imágenes a comprobar: las de SEO y las de reserva (logo del tema e icono del sitio). */
	public static function configured() {
		return array(
			'image'     => trim( (string) self::o( 'seo_default_image' ) ),
			'logo'      => trim( (string) self::o( 'seo_logo' ) ),
			'site_logo' => self::attachment_url( (int) get_theme_mod( 'custom_logo' ) ),
			'site_icon' => self::attachment_url( (int) get_option( 'site_icon' ) ),
		);
	}

	private static function attachment_url( $id ) {
		if ( $id <= 0 || ! function_exists( 'wp_get_attachment_image_url' ) ) return '';
		return (string) ( wp_get_attachment_image_url( $id, 'full' ) ?: '' );
	}

	public static function fingerprint( array $urls ) {
		return md5( (string) wp_json_encode( array( $urls, home_url( '/' ) ) ) );
	}

	/** Al guardar los ajustes: si cambiaron las imágenes, se comprueban en segundo plano. */
	public static function changed() { self::refresh(); }

	/**
	 * Programa la comprobación si cambiaron las imágenes (también las heredadas de la red o el logo del tema) o si la
	 * última tiene más de dos días. Barato: lee opciones, no hace peticiones.
	 */
	public static function refresh() {
		$urls  = self::configured();
		$state = self::state();
		$stale = (int) ( $state['at'] ?? 0 ) < time() - 2 * DAY_IN_SECONDS;
		// Sin imagen social ni logo de SEO no hay nada que comprobar: el logo del tema y el icono sólo son reservas.
		if ( '' === $urls['image'] && '' === $urls['logo'] && empty( $state['urls'] ) ) return false;
		if ( ( $state['fp'] ?? '' ) === self::fingerprint( $urls ) && ! $stale ) return false;
		if ( wp_next_scheduled( self::HOOK ) ) return false;
		return (bool) wp_schedule_single_event( time() + 5, self::HOOK );
	}

	/** Comprueba cada imagen, elige las de reserva y guarda el resultado. Corre en el cron. */
	public static function run( $mode = '' ) {
		$urls     = self::configured();
		$previous = self::state();
		$home     = self::host( home_url( '/' ) );
		$rows     = array();
		$retry    = false;
		foreach ( array_unique( array_filter( $urls ) ) as $url ) {
			$row = self::confirm( self::probe( $url, $home ), $previous['urls'][ $url ] ?? null );
			if ( ! empty( $row['pending'] ) ) $retry = true;
			$rows[ $url ] = $row;
		}
		$state = array(
			'fp'             => self::fingerprint( $urls ),
			'at'             => time(),
			'urls'           => $rows,
			'fallback_logo'  => self::first_good( array( $urls['site_logo'], $urls['site_icon'] ), $rows ),
			'fallback_image' => self::first_good( array( $urls['logo'], $urls['site_logo'], $urls['site_icon'] ), $rows ),
		);
		update_option( self::OPTION, $state, true );
		if ( $retry && ! wp_next_scheduled( self::HOOK, array( self::RETRY ) ) ) wp_schedule_single_event( time() + HOUR_IN_SECONDS, self::HOOK, array( self::RETRY ) );
		if ( ( '' !== $urls['image'] || '' !== $urls['logo'] ) && ! wp_next_scheduled( self::HOOK, array( self::DAILY ) ) ) wp_schedule_single_event( time() + DAY_IN_SECONDS, self::HOOK, array( self::DAILY ) );
		return $state;
	}

	/**
	 * Sólo un fallo inequívoco (el dominio no existe, 404 o 410, o no es una imagen) deja de publicar la imagen, y sólo
	 * si la comprobación anterior, de hace al menos media hora, dio lo mismo: la primera vez queda pendiente y se
	 * reintenta en una hora. Lo dudoso (403, 401, 429, 5xx, sin respuesta, peticiones externas bloqueadas) nunca la
	 * quita: se publica y se avisa que no se pudo comprobar.
	 */
	public static function confirm( array $row, $before ) {
		if ( 'gone' !== ( $row['verdict'] ?? '' ) ) return $row;
		$since = is_array( $before ) && 'gone' === ( $before['verdict'] ?? '' ) ? (int) ( $before['gone_since'] ?? 0 ) : 0;
		$row['gone_since'] = $since ?: time();
		if ( $since && $since <= time() - self::CONFIRM_AFTER ) return $row;
		$row['ok'] = null;
		$row['pending'] = 1;
		return $row;
	}

	private static function first_good( array $candidates, array $rows ) {
		foreach ( $candidates as $url ) if ( '' !== $url && isset( $rows[ $url ] ) && true === ( $rows[ $url ]['ok'] ?? null ) && empty( $rows[ $url ]['demo'] ) && empty( $rows[ $url ]['offsite'] ) ) return $url;
		return '';
	}

	public static function host( $url ) {
		return (string) preg_replace( '/^www\./', '', strtolower( (string) wp_parse_url( (string) $url, PHP_URL_HOST ) ) );
	}

	/** Mismo sitio: el mismo host o un subdominio suyo (cdn.ejemplo.com para ejemplo.com), en los dos sentidos. */
	public static function same_site( $host, $home ) {
		if ( '' === $host || '' === $home ) return true;
		if ( $host === $home ) return true;
		$tail = function( $a, $b ) { return strlen( $a ) > strlen( $b ) && '.' . $b === substr( $a, - ( strlen( $b ) + 1 ) ); };
		return $tail( $host, $home ) || $tail( $home, $host );
	}

	/** Archivo local de una URL de la biblioteca o de wp-content de este sitio; '' si no lo es. */
	public static function local_file( $url ) {
		$strip = function( $u ) { return (string) preg_replace( '#^https?:#i', '', (string) $u ); };
		$path  = $strip( strtok( (string) $url, '?#' ) );
		$roots = array();
		if ( function_exists( 'wp_get_upload_dir' ) ) { $up = wp_get_upload_dir(); if ( ! empty( $up['baseurl'] ) && ! empty( $up['basedir'] ) ) $roots[ $strip( $up['baseurl'] ) ] = $up['basedir']; }
		if ( function_exists( 'content_url' ) && defined( 'WP_CONTENT_DIR' ) ) $roots[ $strip( content_url() ) ] = WP_CONTENT_DIR;
		foreach ( $roots as $base => $dir ) {
			$base = rtrim( $base, '/' ) . '/';
			if ( 0 !== strpos( $path, $base ) ) continue;
			$rel = rawurldecode( substr( $path, strlen( $base ) ) );
			if ( '' === $rel || false !== strpos( $rel, '..' ) || false !== strpos( $rel, "\0" ) ) return '';
			return rtrim( $dir, '/\\' ) . '/' . $rel;
		}
		return '';
	}

	/** Comprueba una imagen: en el disco si es de este sitio; si no, por HTTP (HEAD y, si no lo admite, un GET corto). */
	public static function probe( $url, $home_host ) {
		$host = self::host( $url );
		$row  = array( 'ok' => null, 'code' => 0, 'error' => '', 'offsite' => ! self::same_site( $host, $home_host ), 'demo' => self::looks_demo( $url ), 'width' => 0, 'height' => 0 );
		$file = $row['offsite'] ? '' : self::local_file( $url );
		if ( '' !== $file ) {
			$exists      = is_file( $file ) && is_readable( $file ) && filesize( $file ) > 0;
			$row['code'] = $exists ? 200 : 404;
			if ( ! $exists ) return self::verdict( $row, 'gone', 'el archivo no está en el servidor' );
			$size = function_exists( 'getimagesize' ) ? @getimagesize( $file ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- un archivo que no es imagen devuelve false.
			if ( $size ) { $row['width'] = (int) $size[0]; $row['height'] = (int) $size[1]; }
			elseif ( ! preg_match( '/\.svg$/i', $file ) ) return self::verdict( $row, 'gone', 'el archivo no es una imagen' );
			return self::verdict( $row, 'ok', '' );
		}
		$args = array( 'timeout' => 5, 'redirection' => 3, 'user-agent' => self::USER_AGENT );
		$res  = wp_safe_remote_head( $url, $args );
		$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		if ( 200 !== $code ) {
			$res  = wp_safe_remote_get( $url, $args + array( 'limit_response_size' => 4096 ) );
			$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		}
		$row['code'] = $code;
		if ( is_wp_error( $res ) ) {
			// Sin respuesta: sólo es inequívoco si el dominio no existe; un corte de red o WP_HTTP_BLOCK_EXTERNAL, no.
			if ( '' !== $host && ! self::resolves( $host ) ) return self::verdict( $row, 'gone', 'el dominio ' . $host . ' no existe' );
			return self::verdict( $row, 'unknown', $res->get_error_message() );
		}
		if ( in_array( $code, array( 404, 410 ), true ) ) return self::verdict( $row, 'gone', 'responde ' . $code );
		if ( 200 !== $code ) return self::verdict( $row, 'unknown', 'responde ' . $code );
		// Un dominio estacionado responde 200 con una página: eso no es una imagen.
		$type = strtolower( (string) wp_remote_retrieve_header( $res, 'content-type' ) );
		if ( '' !== $type && 0 !== strpos( $type, 'image/' ) ) return self::verdict( $row, 'gone', 'no devuelve una imagen (' . $type . ')' );
		return self::verdict( $row, 'ok', '' );
	}

	/** ok (true), gone (false, si se confirma) o unknown (null: se publica). */
	private static function verdict( array $row, $verdict, $error ) {
		$row['verdict'] = $verdict;
		$row['ok']      = 'ok' === $verdict ? true : ( 'gone' === $verdict ? false : null );
		$row['error']   = (string) $error;
		return $row;
	}

	/** ¿El nombre resuelve en DNS? Se pregunta sólo tras una petición sin respuesta. */
	public static function resolves( $host ) {
		if ( preg_match( '/^[0-9.]+$|:/', $host ) ) return true;
		return function_exists( 'gethostbynamel' ) ? (bool) @gethostbynamel( $host ) : true; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- un nombre que no existe sólo devuelve false.
		return $row;
	}

	/* ------------------------------------------------------------------ *
	 * Avisos
	 * ------------------------------------------------------------------ */

	/**
	 * Lo que hay que revisar, como lista de array( code, message[, level] ); level 'info' no sale en el Escritorio. Sin peticiones: lee la última comprobación y los
	 * ajustes. Lo usan el aviso del escritorio y el MCP.
	 */
	public static function warnings() {
		$out   = array();
		$state = self::state();
		$home  = self::host( home_url( '/' ) );
		$names = array( 'image' => 'La imagen social por defecto (og:image, twitter:image e image del schema)', 'logo' => 'El logo del schema' );
		foreach ( $names as $kind => $label ) {
			$url = trim( (string) self::o( 'logo' === $kind ? 'seo_logo' : 'seo_default_image' ) );
			if ( '' === $url ) continue;
			$row       = $state['urls'][ $url ] ?? null;
			$published = self::url( $kind );
			$instead   = '' !== $published ? ' Mientras tanto se publica ' . $published . '.' : ' Mientras tanto no se publica ' . ( 'logo' === $kind ? 'logo' : 'imagen' ) . '.';
			if ( self::looks_demo( $url ) ) $out[] = array( 'code' => $kind . '_demo', 'message' => $label . ' parece de una demo o de relleno (' . basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) . '): sube el de la marca en SEO → Identidad.' . $instead );
			elseif ( is_array( $row ) && false === ( $row['ok'] ?? null ) ) $out[] = array( 'code' => $kind . '_broken', 'message' => $label . ' no se puede usar: ' . $url . ' (' . ( $row['error'] ?: 'no responde' ) . ', dos veces seguidas). Súbela a la biblioteca de este sitio y elígela en SEO → Identidad.' . $instead );
			elseif ( is_array( $row ) && ! empty( $row['pending'] ) ) $out[] = array( 'code' => $kind . '_pending', 'message' => $label . ' parece rota: ' . $url . ' (' . $row['error'] . '). Se sigue publicando y se vuelve a comprobar en una hora; si falla otra vez, se usa la de reserva.' );
			elseif ( is_array( $row ) && 'unknown' === ( $row['verdict'] ?? '' ) ) $out[] = array( 'code' => $kind . '_unchecked', 'message' => $label . ': no pudimos comprobarla; puede estar bien (' . $url . ', ' . $row['error'] . '). Se sigue publicando.' );
			if ( self::maybe_demo( $url ) ) $out[] = array( 'code' => $kind . '_maybe_demo', 'message' => $label . ' podría ser de una demo por su nombre (' . basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) . '). Se sigue publicando: si no es la de la marca, cámbiala en SEO → Identidad.' );
			if ( ! self::same_site( self::host( $url ), $home ) ) $out[] = array( 'code' => $kind . '_offsite', 'message' => $label . ' está en otro dominio (' . self::host( $url ) . '). Si ese dominio deja de responder, se pierde: súbela a la biblioteca de este sitio.' );
			if ( 'logo' === $kind && is_array( $row ) && ! empty( $row['width'] ) && ( $row['width'] < self::MIN_LOGO || $row['height'] < self::MIN_LOGO ) ) $out[] = array( 'code' => 'logo_small', 'message' => 'El logo del schema mide ' . (int) $row['width'] . '×' . (int) $row['height'] . ' px; Google pide al menos ' . self::MIN_LOGO . '×' . self::MIN_LOGO . '.' );
		}
		return array_merge( $out, self::identity_warnings(), self::local_warnings(), self::template_warnings() );
	}

	/** Nombre del sitio y de la organización con emoji (A2) y perfiles para sameAs. */
	private static function identity_warnings() {
		$out  = array();
		$site = (string) ( self::o( 'seo_site_name' ) ?: get_bloginfo( 'name' ) );
		$org  = (string) self::o( 'seo_organization_name' );
		foreach ( array_unique( array_filter( array( $site, $org ) ) ) as $name ) {
			if ( self::has_emoji( $name ) ) $out[] = array( 'code' => 'name_emoji', 'message' => 'El nombre «' . $name . '» lleva emoji: en el schema y en og:site_name se publica como «' . self::plain_name( $name ) . '», pero en los títulos (%sitename%) sí aparece. Quítalo en SEO → Identidad o en Ajustes → Generales.' );
		}
		if ( class_exists( 'Digitalisimo_Integrations_Social_Profiles' ) ) {
			if ( ! Digitalisimo_Integrations_Social_Profiles::all_urls() ) $out[] = array( 'code' => 'same_as_empty', 'message' => 'No hay perfiles oficiales (redes y Google Business): el schema no puede enlazar la marca con ellos (sameAs). Agrégalos en SEO → Identidad → Perfiles oficiales.' );
			elseif ( self::o( 'seo_local_enabled' ) && '' === trim( (string) ( Digitalisimo_Integrations_Social_Profiles::values()['google_business'] ?? '' ) ) ) $out[] = array( 'code' => 'same_as_gbp', 'message' => 'SEO local está activo pero falta el enlace al Perfil de Google Business en los perfiles oficiales: agrégalo para que el schema lo publique en sameAs.' );
		}
		return $out;
	}

	/** SEO local: lo que falta no se publica (A2), y el horario 24 horas los 7 días se confirma. */
	private static function local_warnings() {
		if ( ! self::o( 'seo_local_enabled' ) || ! class_exists( 'Digitalisimo_Integrations_Local_Business' ) ) return array();
		$out     = array();
		$missing = array();
		if ( '' === trim( (string) self::o( 'seo_local_city' ) ) || ( '' === trim( (string) self::o( 'seo_local_address' ) ) && '' === trim( (string) self::o( 'seo_local_postal' ) ) ) ) $missing[] = 'la dirección (calle o código postal, y ciudad)';
		if ( '' === Digitalisimo_Integrations_Local_Business::phone() ) $missing[] = 'el teléfono';
		if ( ! Digitalisimo_Integrations_Local_Business::schema_hours() ) $missing[] = 'el horario';
		if ( $missing ) $out[] = array( 'code' => 'local_missing', 'message' => 'SEO local está activo pero falta ' . implode( ', ', $missing ) . ': no se publica' . ( count( $missing ) > 1 ? 'n' : '' ) . ' en el schema. Complétalo en SEO → Local con los datos reales del negocio.' );
		// 24×7 puede ser real (lo es en negocios de urgencias y en la propia agencia): se publica como se eligió y sólo se
		// informa en el MCP, sin aviso en el Escritorio. Lo que no se hace es ponerlo por omisión.
		if ( self::always_open() ) $out[] = array( 'code' => 'local_24x7', 'level' => 'info', 'message' => 'El horario dice abierto las 24 horas los 7 días y así se publica. Si no es real, cámbialo en SEO → Local: Google lo muestra tal cual en la ficha del negocio.' );
		return $out;
	}

	/** ¿El horario efectivo es 24 horas los 7 días (por día o con 00:00–23:59)? */
	public static function always_open() {
		$days = Digitalisimo_Integrations_Local_Business::schedule();
		if ( count( $days ) < 7 ) return false;
		foreach ( $days as $row ) {
			$all_day = 'open' === $row['state'] && '00:00' === $row['from'] && in_array( $row['to'], array( '23:59', '23:30' ), true );
			if ( 'always' !== $row['state'] && ! $all_day ) return false;
		}
		return true;
	}

	/**
	 * Plantillas de título y descripción con texto fijo (A3): «%title% 🤖 Asegura tu Éxito Hoy» añade el mismo relleno a
	 * cada página. Lo configuró el sitio, así que no se cambia: se avisa. La portada queda fuera (su título es uno solo).
	 */
	private static function template_warnings() {
		$labels = array( 'post' => 'entradas', 'page' => 'páginas', 'archive' => 'archivos', 'taxonomy' => 'categorías y etiquetas', 'author' => 'autores' );
		$found  = array();
		foreach ( array( 'title' => 'títulos', 'description' => 'descripciones' ) as $field => $what ) {
			foreach ( $labels as $key => $label ) {
				$literal = self::template_literal( self::o( 'seo_' . $field . '_' . $key ) );
				if ( '' !== $literal ) $found[ $what . '|' . $literal ][] = $label;
			}
			foreach ( array( 'seo_type_templates' => array( $field, 'archive_' . $field ), 'seo_tax_templates' => array( $field ) ) as $option => $fields ) {
				$rows = self::o( $option, array() );
				foreach ( is_array( $rows ) ? $rows : array() as $name => $row ) foreach ( $fields as $f ) {
					$literal = self::template_literal( is_array( $row ) ? ( $row[ $f ] ?? '' ) : '' );
					if ( '' !== $literal ) $found[ $what . '|' . $literal ][] = sanitize_key( (string) $name );
				}
			}
		}
		$out = array();
		foreach ( $found as $key => $where ) {
			list( $what, $literal ) = explode( '|', $key, 2 );
			$out[] = array( 'code' => 'template_literal', 'message' => 'La plantilla de ' . $what . ' de ' . implode( ', ', array_unique( $where ) ) . ' añade «' . $literal . '» a cada una. Si no es a propósito, déjala en SEO → Títulos como «%title% %sep% %sitename%» (títulos) o «%excerpt%» (descripciones): Google reescribe los títulos con relleno.' );
		}
		return $out;
	}

	/** Texto fijo de una plantilla: lo que queda sin variables ni separadores. */
	public static function template_literal( $template ) {
		$text = (string) preg_replace( '/%[a-z_]+(?:\([^)%]*\))?%/i', ' ', (string) $template );
		$text = (string) preg_replace( '/(?<!\S)[|\-–—·•\/»«~*⋆:](?!\S)/u', ' ', $text );
		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	/** Aviso en el Escritorio y en las pantallas DIGITALÍSIMO, para quien administra el sitio. */
	public static function notice() {
		if ( ! current_user_can( 'manage_options' ) || ( function_exists( 'is_network_admin' ) && is_network_admin() ) ) return;
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$page   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo decide si se muestra el aviso.
		if ( ! ( $screen && 'dashboard' === $screen->id ) && 0 !== strpos( $page, 'digitalisimo' ) ) return;
		if ( Digitalisimo_Integrations_SEO_Resolver::foreign_seo() ) return;
		self::refresh();
		// Lo informativo (p. ej. un horario 24×7 elegido a propósito) sólo va al MCP.
		$warnings = array_values( array_filter( self::warnings(), function( $w ) { return 'info' !== ( $w['level'] ?? 'warning' ); } ) );
		if ( ! $warnings ) return;
		$items = '';
		foreach ( $warnings as $warning ) $items .= '<li>' . esc_html( $warning['message'] ) . '</li>';
		$link = '<a href="' . esc_url( admin_url( 'admin.php?page=digitalisimo-seo-general' ) ) . '">' . esc_html( 'Abrir SEO → Identidad' ) . '</a>';
		Digitalisimo_Notices::show( Digitalisimo_Notices::site( 'seo-brand' ), 'warning', '<p><strong>' . esc_html( 'DIGITALÍSIMO SEO: datos de marca por revisar' ) . '</strong></p><ul style="list-style:disc;padding-left:1.5em">' . $items . '</ul><p>' . $link . '</p>', array( 'hash' => wp_list_pluck( $warnings, 'message' ), 'cap' => 'manage_options' ) );
	}
}
