<?php
namespace Digitalisimo\Elements\Legacy\Platform;

defined( 'ABSPATH' ) || exit;

/**
 * Fuentes propias de Element Pack (`ep_custom_font`) → fuentes propias del constructor (`elementor_font`), §3.D.3.
 *
 * - Mismo título (la familia): los documentos y el kit sólo guardan el nombre de la familia, así que no hay nada que
 *   reescribir. Una familia con caracteres que el CSS no admite (comillas, barras, llaves, `<`…) no se crea: el
 *   `@font-face` del constructor la interpola tal cual.
 * - Término `custom` de `elementor_font_type` (sin él, el gestor de fuentes la ignora).
 * - `elementor_font_files` campo a campo (`font_type` = ''), con los **mismos adjuntos**: la URL se lee del adjunto,
 *   nunca de lo guardado por el origen. El OTF, que el destino no tiene, va al hueco `ttf` si está libre (se informa).
 * - `elementor_font_face` regenerado con `generate_font_face()` del destino si está cargado (o con el mismo formato).
 * - Si ya hay una fuente con esa familia, no se crea otra (`plat.font.conflict`).
 *
 * Se crea en `platform_prepare` y nace publicada (V.11): duplicar el `@font-face` mientras Element Pack sigue cargado
 * es inocuo. Al final, `clear_fonts_list()`; el CSS de los documentos lo vacía la etapa `finish` del runner.
 */
final class Fonts {
	const SOURCE      = 'ep_custom_font';
	const TARGET      = 'elementor_font';
	const TAXONOMY    = 'elementor_font_type';
	const FILES_META  = 'elementor_font_files';
	const FACE_META   = 'elementor_font_face';
	const SOURCE_META = 'ep_font_files';
	const KIND        = 'font';
	/** Formatos del destino, en el orden de su `@font-face`. */
	const FORMATS  = array( 'woff', 'woff2', 'ttf', 'svg', 'eot' );
	const WEIGHTS  = array( 'normal', 'bold', '100', '200', '300', '400', '500', '600', '700', '800', '900' );
	const STYLES   = array( 'normal', 'italic', 'oblique' );
	const VARIANTS = 50;
	/** Caracteres que no pueden ir en un nombre de familia que el destino interpola entre comillas simples. */
	const UNSAFE = '/[\'"\\\\<>{};\x00-\x1F\x7F]/';

	/** @var array<string, array{count:int, names:string[]}> lo creado en esta petición, por código. */
	private static $tally = array();

	/** Fuentes publicadas del origen, por ID ascendente. */
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

	/**
	 * Lo que se crearía para una fuente, sin escribir nada: `{ family, files[], otf, missing, unsafe, empty }`.
	 */
	public static function plan( int $id ): array {
		$post   = get_post( $id );
		$family = $post ? trim( (string) $post->post_title ) : '';
		$out    = array( 'family' => $family, 'files' => array(), 'otf' => 0, 'missing' => 0, 'unsafe' => false, 'empty' => false );
		if ( '' === $family || strlen( $family ) > 120 || preg_match( self::UNSAFE, $family ) ) {
			$out['unsafe'] = true;
			return $out;
		}
		$saved = get_post_meta( $id, self::SOURCE_META, true );
		foreach ( array_slice( is_array( $saved ) ? array_values( $saved ) : array(), 0, self::VARIANTS ) as $variant ) {
			if ( ! is_array( $variant ) ) {
				continue;
			}
			$weight = (string) ( $variant['font_weight'] ?? '400' );
			$style  = (string) ( $variant['font_style'] ?? 'normal' );
			$row    = array(
				'font_type'   => '',
				'font_weight' => in_array( $weight, self::WEIGHTS, true ) ? $weight : '400',
				'font_style'  => in_array( $style, self::STYLES, true ) ? $style : 'normal',
			);
			$any    = false;
			foreach ( self::FORMATS as $format ) {
				$file           = self::file( $variant[ $format ] ?? '', $out['missing'] );
				$row[ $format ] = $file ? $file : array( 'id' => '', 'url' => '' );
				$any            = $any || (bool) $file;
			}
			$otf = self::file( $variant['otf'] ?? '', $out['missing'] );
			if ( $otf && '' === $row['ttf']['url'] ) {
				$row['ttf'] = $otf;
				$any        = true;
				++$out['otf'];
			}
			if ( $any ) {
				$out['files'][] = $row;
			}
		}
		$out['empty'] = ! $out['files'];
		return $out;
	}

	/** Archivo de una variante: el adjunto existente con su URL actual, o null (y se cuenta si había algo). */
	private static function file( $value, int &$missing ): ?array {
		if ( ! is_array( $value ) || ( empty( $value['id'] ) && empty( $value['url'] ) ) ) {
			return null;
		}
		$id   = absint( $value['id'] ?? 0 );
		$post = $id ? get_post( $id ) : null;
		$url  = $post && 'attachment' === $post->post_type ? (string) wp_get_attachment_url( $id ) : '';
		if ( '' === $url || ! preg_match( '#^https?://[^\s\'"\\\\<>()]+$#i', $url ) ) {
			++$missing;
			return null;
		}
		return array( 'id' => $id, 'url' => $url );
	}

	/** Análisis previo: qué se crearía y qué no. */
	public static function analyze(): array {
		$out = array();
		foreach ( self::sources() as $id ) {
			if ( self::done( $id ) ) {
				continue;
			}
			$plan = self::plan( $id );
			foreach ( self::codes( $plan, self::existing( $plan['family'] ) ) as $pair ) {
				$out[] = $pair;
			}
		}
		return $out;
	}

	/** Códigos de un plan: `[ [code, detail], … ]`. */
	private static function codes( array $plan, int $existing, int $dest = 0 ): array {
		$name = Inventory::plain( $plan['family'], 80 );
		if ( $plan['unsafe'] ) {
			return array( array( 'plat.font.unsafe', array( 'name' => $name ) ) );
		}
		if ( $plan['empty'] ) {
			return array( array( 'plat.font.empty', array( 'name' => $name ) ) );
		}
		if ( $existing ) {
			return array( array( 'plat.font.conflict', array( 'name' => $name, 'id' => $existing ) ) );
		}
		$out = array( array( 'plat.font.created', array( 'name' => $name ) + ( $dest ? array( 'id' => $dest ) : array() ) ) );
		if ( $plan['otf'] ) {
			$out[] = array( 'plat.font.otf', array( 'name' => $name, 'count' => $plan['otf'] ) );
		}
		if ( $plan['missing'] ) {
			$out[] = array( 'plat.font.file_missing', array( 'name' => $name, 'count' => $plan['missing'] ) );
		}
		return $out;
	}

	/** ¿Ya convertida en una tarea anterior (fila del registro con su destino vivo)? */
	private static function done( int $id ): bool {
		$row = Inventory::reg_get( self::KIND, (string) $id );
		return $row && in_array( $row['state'] ?? '', array( 'done', 'draft' ), true ) && Inventory::exists( (int) ( $row['dest'] ?? 0 ) );
	}

	/** Fuente del destino con esa familia, que no haya creado esta migración (0 si no hay). */
	private static function existing( string $family ): int {
		if ( '' === $family ) {
			return 0;
		}
		// Las fuentes de un sitio son pocas: se comparan los títulos aquí (sin depender de la búsqueda por título).
		$ids = get_posts(
			array(
				'post_type'        => self::TARGET,
				'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'numberposts'      => 500,
				'fields'           => 'ids',
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => true,
			)
		);
		foreach ( (array) $ids as $id ) {
			$post = get_post( (int) $id );
			if ( $post && $family === (string) $post->post_title && 0 !== strpos( (string) get_post_meta( (int) $id, Inventory::TAG, true ), self::KIND . ':' ) ) {
				return (int) $id;
			}
		}
		return 0;
	}

	/** Etapa `platform_prepare`: crea las fuentes, con cursor por ID. */
	public static function prepare( array &$cursor, float $deadline ): bool {
		foreach ( self::sources() as $id ) {
			if ( $id <= (int) ( $cursor['after'] ?? 0 ) ) {
				continue;
			}
			if ( Inventory::late( $deadline ) ) {
				self::emit();
				return false;
			}
			if ( self::convert( $id ) ) {
				$cursor['changed'] = 1;
			}
			$cursor['after'] = $id;
		}
		if ( ! empty( $cursor['changed'] ) ) {
			self::clear_lists();
		}
		self::emit();
		return true;
	}

	/** Pasos sin trabajo en `platform`: la caché del CSS la vacía `finish` del runner. */
	public static function finish( array &$cursor, float $deadline ): bool {
		return true;
	}

	/** Convierte una fuente. Devuelve true si creó algo. */
	public static function convert( int $id ): bool {
		if ( self::done( $id ) ) {
			return false;
		}
		$tag  = self::KIND . ':' . $id;
		$plan = self::plan( $id );
		$dest = Inventory::created( self::TARGET, $tag );
		if ( ! $dest ) {
			$codes = self::codes( $plan, self::existing( $plan['family'] ) );
			if ( 'plat.font.created' !== $codes[0][0] ) {
				Inventory::site_code( $codes[0][0], $codes[0][1] );
				return false;
			}
			// Sin el gestor de fuentes del destino cargado, la fuente se crearía sin su tipo y nadie la vería.
			if ( function_exists( 'taxonomy_exists' ) && ! taxonomy_exists( self::TAXONOMY ) ) {
				Inventory::site_code( 'plat.font.failed', array( 'name' => Inventory::plain( $plan['family'], 80 ), 'note' => __( 'El gestor de fuentes propias no está cargado.', 'digitalisimo-migration' ) ) );
				return false;
			}
			Inventory::reg_put( self::KIND, (string) $id, array( 'state' => 'pending', 'extra' => array( 'family' => $plan['family'] ) ) );
			$dest = wp_insert_post(
				array(
					'post_type'   => self::TARGET,
					'post_status' => 'publish',
					'post_title'  => wp_slash( $plan['family'] ),
					'meta_input'  => array( Inventory::TAG => $tag ),
				),
				true
			);
			if ( ! $dest || is_wp_error( $dest ) ) {
				Inventory::site_code( 'plat.font.failed', array( 'name' => Inventory::plain( $plan['family'], 80 ) ) );
				return false;
			}
			$dest = (int) $dest;
		}
		wp_set_object_terms( $dest, 'custom', self::TAXONOMY );
		update_post_meta( $dest, self::FILES_META, wp_slash( $plan['files'] ) );
		update_post_meta( $dest, self::FACE_META, wp_slash( self::font_face( $dest, $plan ) ) );
		$post = get_post( $dest );
		if ( $post && (string) $post->post_title !== $plan['family'] ) {
			Inventory::site_code( 'plat.font.renamed', array( 'name' => Inventory::plain( $plan['family'], 80 ), 'id' => $dest ) );
		}
		Inventory::reg_put(
			self::KIND,
			(string) $id,
			array(
				'dest'  => $dest,
				'hash'  => self::hash( $dest ),
				'state' => 'done',
				'extra' => array( 'family' => $plan['family'] ),
			)
		);
		foreach ( self::codes( $plan, 0, $dest ) as $pair ) {
			self::$tally[ $pair[0] ]            = self::$tally[ $pair[0] ] ?? array( 'count' => 0, 'names' => array() );
			self::$tally[ $pair[0] ]['count']  += max( 1, (int) ( $pair[1]['count'] ?? 1 ) );
			self::$tally[ $pair[0] ]['names'][] = (string) $pair[1]['name'];
		}
		return true;
	}

	/** Lo creado, un elemento del informe por código (con las familias en la nota), no uno por fuente. */
	private static function emit(): void {
		foreach ( self::$tally as $code => $row ) {
			Inventory::site_code( $code, array( 'count' => $row['count'], 'note' => Inventory::plain( implode( ', ', array_unique( $row['names'] ) ), 200 ) ) );
		}
		self::$tally = array();
	}

	/** Huella de lo creado: familia y archivos. */
	public static function hash( int $id ): string {
		$post = get_post( $id );
		return md5( ( $post ? (string) $post->post_title : '' ) . '|' . wp_json_encode( get_post_meta( $id, self::FILES_META, true ) ) );
	}

	/**
	 * `@font-face` de la fuente: el generador del destino si está cargado; si no, el mismo formato
	 * (`Custom_Fonts::get_font_face_from_data()`), con valores ya validados.
	 */
	private static function font_face( int $id, array $plan ): string {
		$custom = self::custom_fonts();
		if ( $custom && method_exists( $custom, 'generate_font_face' ) ) {
			$face = $custom->generate_font_face( $id );
			if ( is_string( $face ) ) {
				return $face;
			}
		}
		$out = '';
		foreach ( $plan['files'] as $data ) {
			$src = array();
			foreach ( array( 'eot', 'woff2', 'woff', 'ttf', 'svg' ) as $type ) {
				if ( '' === (string) ( $data[ $type ]['url'] ?? '' ) ) {
					continue;
				}
				$url    = (string) $data[ $type ]['url'] . ( 'svg' === $type ? '#' . str_replace( ' ', '', $plan['family'] ) : '' );
				$format = array( 'eot' => 'embedded-opentype', 'ttf' => 'truetype' )[ $type ] ?? $type;
				$src[]  = "url('" . esc_attr( $url . ( 'eot' === $type ? '?#iefix' : '' ) ) . "') format('" . $format . "')";
			}
			$face = '@font-face {' . PHP_EOL . "\tfont-family: '" . $plan['family'] . "';" . PHP_EOL . "\tfont-style: " . $data['font_style'] . ';' . PHP_EOL . "\tfont-weight: " . $data['font_weight'] . ';' . PHP_EOL . "\tfont-display: auto;" . PHP_EOL;
			if ( '' !== (string) ( $data['eot']['url'] ?? '' ) ) {
				$face .= "\tsrc: url('" . esc_attr( (string) $data['eot']['url'] ) . "');" . PHP_EOL;
			}
			$out .= $face . "\tsrc: " . implode( ',' . PHP_EOL . "\t\t", $src ) . ';' . PHP_EOL . '}' . PHP_EOL;
		}
		return $out;
	}

	/** Gestor de fuentes propias del destino, si está cargado. */
	private static function custom_fonts() {
		if ( ! class_exists( '\\ElementorPro\\Plugin', false ) ) {
			return null;
		}
		try {
			$module  = \ElementorPro\Plugin::instance()->modules_manager->get_modules( 'assets-manager' );
			$manager = $module ? $module->get_assets_manager( 'font' ) : null;
			return $manager ? $manager->get_font_type_object( 'custom' ) : null;
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/** Cachés de la lista de fuentes (las regenera el destino), como `clear_fonts_list()`. */
	public static function clear_lists(): void {
		delete_option( 'elementor_fonts_manager_fonts' );
		delete_option( 'elementor_fonts_manager_font_types' );
	}

	/** `--revert`: retira las fuentes creadas que nadie cambió; las demás se omiten y se informan. */
	public static function revert( bool $force = false ): array {
		$out = array();
		foreach ( Inventory::reg_all( self::KIND ) as $source => $row ) {
			$dest = (int) ( $row['dest'] ?? 0 );
			$name = Inventory::plain( (string) ( $row['extra']['family'] ?? '' ), 80 );
			if ( ! Inventory::exists( $dest ) ) {
				Inventory::reg_forget( self::KIND, (string) $source );
				continue;
			}
			if ( ! $force && self::hash( $dest ) !== (string) ( $row['hash'] ?? '' ) ) {
				$out[] = array( 'core.revert_skipped', array( 'type' => self::TARGET, 'id' => $dest, 'name' => $name ) );
				continue;
			}
			Inventory::retire_post( $dest );
			Inventory::reg_forget( self::KIND, (string) $source );
			$out[] = array( 'plat.font.reverted', array( 'id' => $dest, 'name' => $name ) );
		}
		self::clear_lists();
		return $out;
	}
}
