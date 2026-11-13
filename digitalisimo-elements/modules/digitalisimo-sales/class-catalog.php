<?php
namespace Digitalisimo\Elements\Sales;

defined( 'ABSPATH' ) || exit;

/**
 * El kit de ventas que viaja con DIGITALÍSIMO Elements: el manifiesto, las plantillas de las dos variantes y la guía.
 *
 * Todo sale de `templates/` y `guide.md`, que escribe `scripts/sales-kit/kit_paginas.py` (no se editan a mano:
 * `tests/elements-sales-kit.mjs` lo comprueba). Esta clase sólo lee archivos del propio módulo con nombres de su
 * manifiesto y de una lista cerrada de variantes: ninguna ruta sale de la entrada del cliente.
 */
final class Catalog {
	const VARIANTS = array( 'containers', 'sections' );

	/** Partes de la guía: id de la entrada → título de su sección `## ` en guide.md. */
	const GUIDE_SECTIONS = array(
		'reglas'            => 'Reglas',
		'plantillas'        => 'Embudo y plantillas',
		'casos'             => 'Qué landing armar según el caso',
		'orden'             => 'El orden de una página de venta',
		'formulas'          => 'Fórmulas de redacción',
		'paginas'           => 'Las páginas, una por una',
		'antes-de-publicar' => 'Antes de publicar',
	);

	/** @var array|null */
	private static $manifest = null;

	/** @var array<string, array> */
	private static $templates = array();

	public static function dir(): string {
		return __DIR__ . '/templates';
	}

	/** El manifiesto (`templates/manifest.json`), o un arreglo vacío si falta o no se lee. */
	public static function manifest(): array {
		if ( null === self::$manifest ) {
			$file           = self::dir() . '/manifest.json';
			$data           = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			self::$manifest = is_array( $data ) && isset( $data['templates'] ) && is_array( $data['templates'] ) ? $data : array();
		}
		return self::$manifest;
	}

	/** IDs de las plantillas, en orden (`venta-completa`, `venta-corta`…). */
	public static function ids(): array {
		$ids = array();
		foreach ( self::manifest()['templates'] ?? array() as $template ) {
			$ids[] = (string) $template['id'];
		}
		return $ids;
	}

	/** Lo que el manifiesto dice de una plantilla, o null. */
	public static function meta( string $id ): ?array {
		foreach ( self::manifest()['templates'] ?? array() as $template ) {
			if ( $id === $template['id'] ) {
				return $template;
			}
		}
		return null;
	}

	/**
	 * La plantilla (formato de exportación 0.4) de una variante, o null. El archivo sale del manifiesto y la variante
	 * de `VARIANTS`; el nombre se comprueba otra vez para que nunca salga de la carpeta.
	 */
	public static function load( string $id, string $variant ): ?array {
		$meta = self::meta( $id );
		if ( ! $meta || ! in_array( $variant, self::VARIANTS, true ) || ! preg_match( '/^[0-9]{2}-[a-z0-9-]+\.json$/', (string) $meta['file'] ) ) {
			return null;
		}
		$key = $variant . '/' . $meta['file'];
		if ( ! isset( self::$templates[ $key ] ) ) {
			$file = self::dir() . '/' . $key;
			$data = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( ! is_array( $data ) || ! isset( $data['content'] ) || ! is_array( $data['content'] ) ) {
				return null;
			}
			self::$templates[ $key ] = $data;
		}
		return self::$templates[ $key ];
	}

	/**
	 * La variante que usa este sitio: contenedores si tiene activo el experimento de contenedores del constructor (lo
	 * normal en Elements 4.3), secciones clásicas si no (sitios instalados antes de 3.16 que no lo encendieron).
	 */
	public static function variant(): string {
		$plugin = class_exists( '\Elementor\Plugin' ) ? \Elementor\Plugin::$instance : null;
		$exp    = $plugin && isset( $plugin->experiments ) && is_object( $plugin->experiments ) ? $plugin->experiments : null;
		return $exp && method_exists( $exp, 'is_feature_active' ) && $exp->is_feature_active( 'container' ) ? 'containers' : 'sections';
	}

	/** La guía entera o una de sus partes (`GUIDE_SECTIONS`), en Markdown; '' si no existe. */
	public static function guide( string $section = '' ): string {
		$file = __DIR__ . '/guide.md';
		$text = is_readable( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( '' === $section || '' === $text ) {
			return $text;
		}
		if ( ! isset( self::GUIDE_SECTIONS[ $section ] ) ) {
			return '';
		}
		$parts = preg_split( '/^## /m', $text );
		foreach ( (array) $parts as $part ) {
			if ( 0 === strpos( $part, self::GUIDE_SECTIONS[ $section ] . "\n" ) ) {
				return '## ' . rtrim( $part ) . "\n";
			}
		}
		return '';
	}

	/** Para las pruebas: olvida lo leído. */
	public static function reset(): void {
		self::$manifest  = null;
		self::$templates = array();
	}
}
