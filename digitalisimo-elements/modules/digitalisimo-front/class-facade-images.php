<?php
namespace Digitalisimo\Elements\Front;

defined( 'ABSPATH' ) || exit;

/**
 * Imágenes de las fachadas (`facades`, Fase 3 de `docs/ELEMENTS_FRONT_OBJECTIVE.md`): la portada de un vídeo de YouTube,
 * Vimeo, Dailymotion o Wistia y la imagen estática de un mapa de OpenStreetMap, guardadas en
 * `uploads/digitalisimo-elements/facades/` de cada sitio y servidas desde él. El visitante nunca pide nada al servicio
 * para verlas.
 *
 * - **En cola.** El front sólo mira si el archivo existe (`url()`); si falta, agenda el evento único `HOOK` con el
 *   trabajo y pinta la fachada sin imagen. La descarga corre en WP-Cron (tarea remota, nunca durante el render ni el
 *   guardado) con `wp_safe_remote_get()`, tope de tamaño y tiempo, y el agente que identifica al sitio.
 * - **Fallar cerrado.** Un trabajo se valida otra vez al ejecutarse (servicio de la lista, ID con `[A-Za-z0-9_-]`,
 *   coordenadas y acercamiento en rango); el nombre del archivo sale sólo de esos datos validados y la carpeta se
 *   comprueba con `realpath` dentro de `uploads`. Sólo se acepta una imagen JPEG, PNG o WebP; la portada se vuelve a
 *   codificar con el editor de imágenes de WordPress (recortada a 16:9, ≤ 1 280 px). Un fallo deja la marca
 *   `<nombre>.failed` y no se reintenta hasta `RETRY` segundos después.
 * - **Mapa.** Las teselas de `tile.openstreetmap.org` que cubren 1 024 × 576 px alrededor del punto, un nivel por encima
 *   del acercamiento del widget (lo que muestra su visor en ese ancho), se componen con GD una sola vez: veinte teselas
 *   como mucho por mapa, con la atribución en la fachada. Sin GD no hay imagen y la fachada queda sin ella.
 */
final class Facade_Images {
	const HOOK = 'digitalisimo_elements_facade_image';

	/** Carpeta relativa a `uploads`. */
	const FOLDER = 'digitalisimo-elements/facades';

	/** Servicios de vídeo con portada. */
	const SERVICES = array( 'youtube', 'vimeo', 'dailymotion', 'wistia' );

	/** Segundos hasta reintentar un trabajo que falló. */
	const RETRY = 86400;

	/** Tope de bytes de una imagen remota. */
	const MAX_BYTES = 3145728;

	/** Ancho máximo de una portada (16:9). */
	const WIDTH = 1280;

	/** Tamaño de la imagen de un mapa. */
	const MAP_WIDTH  = 1024;
	const MAP_HEIGHT = 576;

	/** Teselas de OpenStreetMap; sus condiciones piden identificar la aplicación y mostrar la atribución. */
	const TILES = 'https://tile.openstreetmap.org/%d/%d/%d.png';

	private static $initialized = false;

	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
	}

	/** Trabajo de la portada de un vídeo, o [] si el servicio o el ID no son válidos. */
	public static function video( string $service, string $id ): array {
		$job = array( 'kind' => 'video', 'service' => $service, 'id' => $id );
		return '' !== self::name( $job ) ? $job : array();
	}

	/** Trabajo de la imagen de un mapa, o [] si el punto o el acercamiento no son válidos. */
	public static function map( float $lat, float $lng, int $zoom ): array {
		$job = array( 'kind' => 'map', 'lat' => sprintf( '%.5f', $lat ), 'lng' => sprintf( '%.5f', $lng ), 'zoom' => $zoom );
		return '' !== self::name( $job ) ? $job : array();
	}

	/**
	 * URL de la imagen ya guardada de un trabajo, o '' mientras no exista; en ese caso agenda su descarga (salvo que
	 * haya fallado hace menos de `RETRY` o ya esté agendada).
	 */
	public static function url( array $job ): string {
		$job  = self::normalize( $job );
		$name = self::name( $job );
		if ( '' === $name || '' === self::uploads()['dir'] ) {
			return '';
		}
		$dir = self::dir( false );
		if ( '' !== $dir ) {
			foreach ( array( 'webp', 'jpg' ) as $ext ) {
				if ( is_file( $dir . $name . '.' . $ext ) ) {
					return self::base_url() . $name . '.' . $ext;
				}
			}
			if ( self::failed( $dir . $name ) ) {
				return '';
			}
		}
		// La carpeta puede no existir aún: la crea la descarga.
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::HOOK, array( $job ) ) ) {
			wp_schedule_single_event( time(), self::HOOK, array( $job ) );
		}
		return '';
	}

	/** ¿Falta la imagen de un trabajo y puede llegar (sin un fallo reciente)? No agenda nada: lo usa la caché de elementos. */
	public static function pending( array $job ): bool {
		$name = self::name( $job );
		if ( '' === $name || '' === self::uploads()['dir'] ) {
			return false;
		}
		$dir = self::dir( false );
		return '' === $dir || ( ! is_file( $dir . $name . '.webp' ) && ! is_file( $dir . $name . '.jpg' ) && ! self::failed( $dir . $name ) );
	}

	/** Callback del evento: descarga y guarda la imagen de un trabajo. Devuelve si quedó guardada. */
	public static function run( $job ): bool {
		$job  = is_array( $job ) ? self::normalize( $job ) : array();
		$name = self::name( $job );
		$dir  = '' !== $name ? self::dir( true ) : '';
		if ( '' === $dir ) {
			return false;
		}
		if ( is_file( $dir . $name . '.webp' ) || is_file( $dir . $name . '.jpg' ) ) {
			return true;
		}
		try {
			$saved = 'map' === $job['kind'] ? self::save_map( $job, $dir . $name ) : self::save_cover( $job, $dir . $name );
		} catch ( \Throwable $error ) {
			$saved = false;
		}
		if ( ! $saved ) {
			@touch( $dir . $name . '.failed' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Sin la marca sólo se reintenta antes.
		} elseif ( is_file( $dir . $name . '.failed' ) ) {
			@unlink( $dir . $name . '.failed' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		return $saved;
	}

	/**
	 * Nombre del archivo (sin extensión) de un trabajo válido, o ''. Sólo sale de datos validados: `<servicio>-<id>` o
	 * `map-<16 hex>` de las coordenadas con cinco decimales y el acercamiento.
	 */
	public static function name( array $job ): string {
		$job = self::normalize( $job );
		if ( 'video' === $job['kind'] ) {
			return in_array( $job['service'], self::SERVICES, true ) && preg_match( '/^[A-Za-z0-9_-]{1,64}$/', $job['id'] ) ? $job['service'] . '-' . $job['id'] : '';
		}
		if ( 'map' === $job['kind'] ) {
			$lat = $job['lat'];
			$lng = $job['lng'];
			if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) || abs( (float) $lat ) > 90 || abs( (float) $lng ) > 180 || $job['zoom'] < 1 || $job['zoom'] > 19 ) {
				return '';
			}
			return 'map-' . substr( sha1( sprintf( '%.5f,%.5f,%d', (float) $lat, (float) $lng, $job['zoom'] ) ), 0, 16 );
		}
		return '';
	}

	/** Trabajo con sus claves y tipos (los argumentos del evento deben coincidir para no agendarlo dos veces). */
	private static function normalize( array $job ): array {
		if ( 'map' === ( $job['kind'] ?? '' ) ) {
			return array( 'kind' => 'map', 'lat' => (string) ( $job['lat'] ?? '' ), 'lng' => (string) ( $job['lng'] ?? '' ), 'zoom' => (int) ( $job['zoom'] ?? 0 ) );
		}
		return array( 'kind' => (string) ( $job['kind'] ?? '' ), 'service' => (string) ( $job['service'] ?? '' ), 'id' => (string) ( $job['id'] ?? '' ) );
	}

	private static function failed( string $base ): bool {
		return is_file( $base . '.failed' ) && (int) filemtime( $base . '.failed' ) > time() - self::RETRY;
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Portadas de vídeo.

	/** Descarga la portada de un vídeo, la recorta a 16:9 y la guarda como WebP (o JPEG). */
	private static function save_cover( array $job, string $base ): bool {
		foreach ( self::cover_sources( $job['service'], $job['id'] ) as $source ) {
			$body = self::fetch_image( $source );
			if ( '' !== $body ) {
				return self::write_cover( $body, $base );
			}
		}
		return false;
	}

	/** Direcciones candidatas de la portada, en orden; las que salen de una API se comprueban por su dominio. */
	private static function cover_sources( string $service, string $id ): array {
		if ( 'youtube' === $service ) {
			return array( 'https://i.ytimg.com/vi/' . $id . '/maxresdefault.jpg', 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg' );
		}
		if ( 'vimeo' === $service ) {
			$data = self::fetch_json( 'https://vimeo.com/api/oembed.json?width=1280&url=' . rawurlencode( 'https://vimeo.com/' . $id ) );
			return self::from_host( $data['thumbnail_url'] ?? '', array( 'vimeocdn.com' ) );
		}
		if ( 'dailymotion' === $service ) {
			$data = self::fetch_json( 'https://api.dailymotion.com/video/' . $id . '?fields=thumbnail_720_url' );
			return self::from_host( $data['thumbnail_720_url'] ?? '', array( 'dmcdn.net', 'dailymotion.com' ) );
		}
		$data = self::fetch_json( 'https://fast.wistia.com/oembed?url=' . rawurlencode( 'https://home.wistia.com/medias/' . $id ) );
		return self::from_host( $data['thumbnail_url'] ?? '', array( 'wistia.com', 'wistia.net' ) );
	}

	/** La dirección en una lista si es HTTPS y de uno de esos dominios (o de un subdominio suyo). */
	private static function from_host( $url, array $hosts ): array {
		$url  = is_string( $url ) ? $url : '';
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( 'https' !== strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) || '' === $host ) {
			return array();
		}
		foreach ( $hosts as $allowed ) {
			if ( $host === $allowed || substr( $host, -strlen( '.' . $allowed ) ) === '.' . $allowed ) {
				return array( $url );
			}
		}
		return array();
	}

	/** Vuelve a codificar la portada recortada a 16:9 (centrada, ≤ `WIDTH`) con el editor de imágenes de WordPress. */
	private static function write_cover( string $body, string $base ): bool {
		$info = @getimagesizefromstring( $body ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Ya validada en fetch_image().
		$ext  = array( IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp' )[ (int) ( $info[2] ?? 0 ) ] ?? '';
		$tmp  = $base . '.src.' . $ext;
		if ( '' === $ext || false === file_put_contents( $tmp, $body ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return false;
		}
		$saved = false;
		try {
			$editor = function_exists( 'wp_get_image_editor' ) ? wp_get_image_editor( $tmp ) : null;
			if ( is_object( $editor ) && ! is_wp_error( $editor ) ) {
				$size = $editor->get_size();
				$w    = (int) ( $size['width'] ?? 0 );
				$h    = (int) ( $size['height'] ?? 0 );
				if ( $w >= 160 && $h >= 90 ) {
					$src_w = $w * 9 > $h * 16 ? (int) round( $h * 16 / 9 ) : $w;
					$src_h = $w * 9 > $h * 16 ? $h : (int) round( $w * 9 / 16 );
					$dst_w = min( $src_w, self::WIDTH );
					$dst_h = (int) round( $dst_w * 9 / 16 );
					$crop  = $editor->crop( (int) floor( ( $w - $src_w ) / 2 ), (int) floor( ( $h - $src_h ) / 2 ), $src_w, $src_h, $dst_w, $dst_h );
					if ( ! is_wp_error( $crop ) ) {
						$editor->set_quality( 82 );
						$saved = self::save_editor( $editor, $base );
					}
				}
			}
		} finally {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		return $saved;
	}

	/**
	 * Guarda con el editor como WebP si lo admite, si no como JPEG; primero en un temporal y luego se renombra. Un filtro
	 * de formato de salida (`image_editor_output_format`) puede cambiar el tipo: vale lo que el editor devuelve.
	 */
	private static function save_editor( $editor, string $base ): bool {
		$webp = function_exists( 'wp_image_editor_supports' ) && wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) );
		$out  = $editor->save( $base . '.part.' . ( $webp ? 'webp' : 'jpg' ), $webp ? 'image/webp' : 'image/jpeg' );
		$path = is_array( $out ) && isset( $out['path'] ) ? (string) $out['path'] : '';
		$ext  = strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) );
		$ext  = 'jpeg' === $ext ? 'jpg' : $ext;
		if ( '' === $path || ! is_file( $path ) || ! in_array( $ext, array( 'webp', 'jpg' ), true ) || 0 !== strpos( $path, $base . '.part.' ) ) {
			if ( '' !== $path && is_file( $path ) ) {
				@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
			return false;
		}
		return rename( $path, $base . '.' . $ext );
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Mapas.

	/** Compone las teselas alrededor del punto y guarda la imagen. */
	private static function save_map( array $job, string $base ): bool {
		if ( ! function_exists( 'imagecreatetruecolor' ) || ! function_exists( 'imagecreatefromstring' ) ) {
			return false;
		}
		$plan   = self::tiles( (float) $job['lat'], (float) $job['lng'], (int) $job['zoom'] );
		$canvas = imagecreatetruecolor( self::MAP_WIDTH, self::MAP_HEIGHT );
		imagefill( $canvas, 0, 0, imagecolorallocate( $canvas, 0xee, 0xf0, 0xf3 ) );
		foreach ( $plan['tiles'] as $tile ) {
			$body  = self::fetch_image( sprintf( self::TILES, $plan['z'], $tile['x'], $tile['y'] ) );
			$image = '' !== $body ? @imagecreatefromstring( $body ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Validada antes; GD avisa con datos raros.
			if ( ! $image ) {
				return false;
			}
			imagecopy( $canvas, $image, $tile['left'], $tile['top'], 0, 0, 256, 256 );
		}
		$webp = function_exists( 'imagewebp' );
		$ext  = $webp ? 'webp' : 'jpg';
		$part = $base . '.part.' . $ext;
		$ok   = $webp ? imagewebp( $canvas, $part, 80 ) : imagejpeg( $canvas, $part, 82 );
		return $ok && rename( $part, $base . '.' . $ext );
	}

	/**
	 * Teselas que cubren `MAP_WIDTH` × `MAP_HEIGHT` centrados en el punto, en el nivel `zoom + 1` (≤ 19): el nivel con el
	 * que el visor del widget llena ese ancho con su recuadro. Cada una con su posición en la imagen; las que quedarían
	 * fuera del mundo (por encima de los polos) no se piden y la longitud da la vuelta.
	 *
	 * @return array{z:int,tiles:array<int,array{x:int,y:int,left:int,top:int}>}
	 */
	public static function tiles( float $lat, float $lng, int $zoom ): array {
		$z    = max( 1, min( 19, $zoom + 1 ) );
		$n    = 2 ** $z;
		$lat  = max( -85.05112878, min( 85.05112878, $lat ) );
		$rad  = deg2rad( $lat );
		$px   = ( $lng + 180 ) / 360 * 256 * $n;
		$py   = ( 1 - log( tan( $rad ) + 1 / cos( $rad ) ) / M_PI ) / 2 * 256 * $n;
		$left = (int) floor( $px - self::MAP_WIDTH / 2 );
		$top  = (int) floor( $py - self::MAP_HEIGHT / 2 );
		$out  = array();
		for ( $ty = (int) floor( $top / 256 ); $ty * 256 < $top + self::MAP_HEIGHT; $ty++ ) {
			if ( $ty < 0 || $ty >= $n ) {
				continue;
			}
			for ( $tx = (int) floor( $left / 256 ); $tx * 256 < $left + self::MAP_WIDTH; $tx++ ) {
				$out[] = array( 'x' => ( ( $tx % $n ) + $n ) % $n, 'y' => $ty, 'left' => $tx * 256 - $left, 'top' => $ty * 256 - $top );
			}
		}
		return array( 'z' => $z, 'tiles' => $out );
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Red y disco.

	private static function request_args( int $limit ): array {
		$version = defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : '';
		return array(
			'timeout'             => 8,
			'redirection'         => 2,
			'limit_response_size' => $limit,
			'user-agent'          => 'DIGITALISIMO-Elements/' . $version . ' (+' . home_url( '/' ) . ')',
		);
	}

	/** JSON de una API (≤ 64 KB), o []. */
	private static function fetch_json( string $url ): array {
		$response = wp_safe_remote_get( $url, self::request_args( 65536 ) );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		return is_array( $data ) ? $data : array();
	}

	/** Cuerpo de una imagen JPEG, PNG o WebP (≤ `MAX_BYTES`), o ''. */
	private static function fetch_image( string $url ): string {
		$response = wp_safe_remote_get( $url, self::request_args( self::MAX_BYTES ) );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}
		$body = (string) wp_remote_retrieve_body( $response );
		$info = '' !== $body && strlen( $body ) < self::MAX_BYTES ? @getimagesizefromstring( $body ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$ok   = is_array( $info ) && in_array( (int) $info[2], array( IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP ), true ) && $info[0] > 0 && $info[1] > 0;
		return $ok ? $body : '';
	}

	/** Carpeta real de las imágenes con barra final, comprobada dentro de `uploads`; la crea si se pide. '' si no hay. */
	private static function dir( bool $create ): string {
		$uploads = self::uploads();
		if ( '' === $uploads['dir'] ) {
			return '';
		}
		$path = $uploads['dir'] . '/' . self::FOLDER;
		if ( ! is_dir( $path ) && ( ! $create || ! wp_mkdir_p( $path ) ) ) {
			return '';
		}
		$real = realpath( $path );
		if ( false === $real ) {
			return '';
		}
		$real = str_replace( '\\', '/', $real );
		return 0 === strpos( $real . '/', $uploads['dir'] . '/' ) ? $real . '/' : '';
	}

	private static function base_url(): string {
		return self::uploads()['url'] . '/' . self::FOLDER . '/';
	}

	/** `basedir` real y `baseurl` del sitio actual (cada sitio de una red tiene los suyos). */
	private static function uploads(): array {
		$uploads = function_exists( 'wp_upload_dir' ) ? wp_upload_dir( null, false ) : array();
		if ( ! is_array( $uploads ) || ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return array( 'dir' => '', 'url' => '' );
		}
		$real = realpath( (string) $uploads['basedir'] );
		return array(
			'dir' => false === $real ? '' : rtrim( str_replace( '\\', '/', $real ), '/' ),
			'url' => rtrim( (string) $uploads['baseurl'], '/' ),
		);
	}
}
