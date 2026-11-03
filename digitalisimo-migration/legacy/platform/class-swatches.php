<?php
namespace Digitalisimo\Elements\Legacy\Platform;

defined( 'ABSPATH' ) || exit;

/**
 * Muestras de variación de Element Pack → muestras propias de DIGITALÍSIMO Elements (F4-Woo), §3.D.9 y V.11.
 *
 * - Sólo atributos globales con términos que tienen metas `swatches_*` de Element Pack (el tipo de atributo, por sí
 *   solo, es ambiguo: otros plugins usan `color` e `image`). `color`/`image` → `visual`; `label`/`button` → `label`.
 * - La entrada va a `digitalisimo_elements_swatches` con `Data::put()` (con su cerrojo y su máximo de 100); nunca se
 *   escribe `attribute_type` ni una meta propia. Una entrada que ya existe no se pisa.
 * - Por término, las metas `color` e `image` de WooCommerce, sólo si el término no tiene ninguna de las dos y el valor
 *   es válido (un hexadecimal; un adjunto imagen). Cada escritura se anota para revertirla.
 * - Forma y tamaño globales de Element Pack → `shape` y `size` (cuadrado → redondeado, tamaño a 24–64, informados).
 * - Con otro plugin de muestras activo, o con las muestras de Element Pack apagadas, no se convierte nada.
 *
 * Etapa `platform`, sólo si Element Pack estaba activo al empezar (V.0.13): entre la desactivación y esta etapa se ven
 * los desplegables (límite declarado).
 */
final class Swatches {
	const KIND      = 'swatch';
	const DATA      = '\\Digitalisimo\\Elements\\Woo\\Swatches\\Data';
	const TYPES     = array( 'color' => 'visual', 'image' => 'visual', 'label' => 'label', 'button' => 'label' );
	const SHAPES    = array( 'round' => 'circle', 'rounded' => 'rounded', 'square' => 'rounded', 'default' => 'circle' );
	const MAX_TERMS = 1000;

	/** Ajuste global de las muestras de Element Pack (el de sus ajustes generales manda, como en el origen). */
	private static function setting( string $name ) {
		$other = get_option( 'element_pack_other_settings', array() );
		if ( is_array( $other ) && isset( $other[ 'ep_variation_swatches_' . $name ] ) ) {
			return $other[ 'ep_variation_swatches_' . $name ];
		}
		return get_option( 'ep_variation_swatches_' . $name, false );
	}

	/** ¿Estaban encendidas las muestras de Element Pack? */
	private static function enabled(): bool {
		$other = get_option( 'element_pack_other_settings', array() );
		return is_array( $other ) && 'on' === ( $other['variation-swatches'] ?? '' );
	}

	/** ¿Hay otro plugin de muestras activo en el sitio o en la red? */
	public static function other_plugin(): bool {
		$active = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}
		foreach ( $active as $file ) {
			$file = (string) $file;
			if ( preg_match( '/swatch/i', $file ) && 0 !== strpos( $file, 'bdthemes-' ) && 0 !== strpos( $file, 'digitalisimo' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Lo que se convertiría, sin escribir: `[ { slug, taxonomy, type, terms: [ { id, color, image, label, name } ] } ]`.
	 */
	public static function plan(): array {
		if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
			return array();
		}
		$out = array();
		foreach ( (array) wc_get_attribute_taxonomies() as $attribute ) {
			$slug = (string) ( $attribute->attribute_name ?? '' );
			$type = self::TYPES[ (string) ( $attribute->attribute_type ?? '' ) ] ?? null;
			if ( '' === $slug || null === $type || ! preg_match( '/^[a-z0-9_-]{1,28}$/', $slug ) ) {
				continue;
			}
			$taxonomy = 'pa_' . $slug;
			$terms    = array();
			foreach ( (array) get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'fields' => 'ids', 'number' => self::MAX_TERMS ) ) as $term_id ) {
				$term_id = (int) $term_id;
				$color   = (string) get_term_meta( $term_id, 'swatches_color', true );
				$image   = absint( get_term_meta( $term_id, 'swatches_image', true ) );
				$label   = (string) get_term_meta( $term_id, 'swatches_label', true );
				if ( '' === $color && ! $image && '' === $label ) {
					continue;
				}
				$term    = get_term( $term_id, $taxonomy );
				$terms[] = array( 'id' => $term_id, 'color' => $color, 'image' => $image, 'label' => $label, 'name' => $term && ! is_wp_error( $term ) ? (string) $term->name : '' );
			}
			if ( $terms ) {
				$out[] = array( 'slug' => $slug, 'taxonomy' => $taxonomy, 'type' => $type, 'source' => (string) $attribute->attribute_type, 'terms' => $terms );
			}
		}
		return $out;
	}

	/** Entrada del atributo y lo que se aproxima: `{ entry, shape_changed, size_changed }`. */
	private static function entry( string $type ): array {
		$shape = (string) self::setting( 'shape' );
		$size  = self::setting( 'size' );
		$size  = is_numeric( $size ) ? (int) $size : 26;
		$fit   = max( 24, min( 64, $size ) );
		return array(
			'entry'         => array(
				'type'  => $type,
				'shape' => 'label' === $type ? 'pill' : ( self::SHAPES[ $shape ] ?? 'circle' ),
				'size'  => $fit,
			),
			'shape_changed' => 'label' !== $type && 'square' === $shape,
			'size_changed'  => $fit !== $size,
		);
	}

	public static function analyze(): array {
		$plan = self::plan();
		if ( ! $plan ) {
			return array();
		}
		if ( ! Inventory::ep_was_active() ) {
			return array( array( 'plat.swatch.inactive', array( 'count' => count( $plan ) ) ) );
		}
		if ( ! self::enabled() ) {
			return array( array( 'plat.swatch.disabled', array( 'count' => count( $plan ) ) ) );
		}
		if ( self::other_plugin() ) {
			return array( array( 'plat.swatch.other_plugin', array( 'count' => count( $plan ) ) ) );
		}
		$out = array();
		foreach ( $plan as $attribute ) {
			$out[] = array( 'plat.swatch.attribute', array( 'name' => $attribute['slug'], 'count' => count( $attribute['terms'] ) ) );
		}
		return $out;
	}

	public static function prepare( array &$cursor, float $deadline ): bool {
		return true;
	}

	/** Etapa `platform`. */
	public static function finish( array &$cursor, float $deadline ): bool {
		$plan = self::plan();
		if ( ! $plan || ! empty( $cursor['done'] ) ) {
			return true;
		}
		$cursor['done'] = 1;
		if ( ! Inventory::ep_was_active() ) {
			Inventory::site_code( 'plat.swatch.inactive', array( 'count' => count( $plan ) ) );
			return true;
		}
		if ( ! self::enabled() ) {
			Inventory::site_code( 'plat.swatch.disabled', array( 'count' => count( $plan ) ) );
			return true;
		}
		if ( self::other_plugin() ) {
			Inventory::site_code( 'plat.swatch.other_plugin', array( 'count' => count( $plan ) ) );
			return true;
		}
		$data = self::DATA;
		if ( ! class_exists( $data ) ) {
			Inventory::site_code( 'plat.swatch.no_target', array( 'count' => count( $plan ) ) );
			return true;
		}
		$shape = false;
		$size  = false;
		foreach ( $plan as $attribute ) {
			$entry = self::entry( $attribute['type'] );
			$shape = $shape || $entry['shape_changed'];
			$size  = $size || $entry['size_changed'];
			self::attribute( $attribute, $entry['entry'] );
			self::terms( $attribute );
		}
		if ( $shape ) {
			Inventory::site_code( 'plat.swatch.shape' );
		}
		if ( $size ) {
			Inventory::site_code( 'plat.swatch.size' );
		}
		self::lost();
		return true;
	}

	/** Entrada del atributo, sin pisar una existente. */
	private static function attribute( array $attribute, array $entry ): void {
		$data    = self::DATA;
		$current = $data::attribute( $attribute['taxonomy'] );
		if ( 'select' !== ( $current['type'] ?? 'select' ) ) {
			if ( null === Inventory::reg_get( self::KIND, 'attr:' . $attribute['slug'] ) ) {
				Inventory::site_code( 'plat.swatch.kept', array( 'name' => $attribute['slug'] ) );
			}
			return;
		}
		$data::put( $attribute['slug'], $entry );
		$saved = $data::attribute( $attribute['taxonomy'] );
		if ( ( $saved['type'] ?? 'select' ) !== $entry['type'] ) {
			Inventory::site_code( 'plat.swatch.limit', array( 'name' => $attribute['slug'] ) );
			return;
		}
		Inventory::reg_put( self::KIND, 'attr:' . $attribute['slug'], array( 'hash' => md5( (string) wp_json_encode( $saved ) ), 'state' => 'done', 'extra' => array( 'entry' => $saved ) ) );
		Inventory::site_code( 'plat.swatch.attribute', array( 'name' => $attribute['slug'], 'count' => count( $attribute['terms'] ) ) );
	}

	/** Metas `color`/`image` de WooCommerce de cada término, sólo donde no hay ninguna. */
	private static function terms( array $attribute ): void {
		$data    = self::DATA;
		$written = 0;
		$invalid = 0;
		$labels  = 0;
		$kept    = 0;
		foreach ( $attribute['terms'] as $term ) {
			if ( '' !== $term['label'] && $term['label'] !== $term['name'] ) {
				++$labels;
			}
			if ( 'visual' !== self::TYPES[ $attribute['source'] ] ) {
				continue;
			}
			$has = '' !== (string) get_term_meta( $term['id'], 'color', true ) || absint( get_term_meta( $term['id'], 'image', true ) );
			if ( $has ) {
				$kept += ( '' !== $term['color'] || $term['image'] ) ? 1 : 0;
				continue;
			}
			if ( 'image' === $attribute['source'] && $term['image'] && wp_attachment_is_image( $term['image'] ) ) {
				update_term_meta( $term['id'], 'image', $term['image'] );
				Inventory::reg_put( self::KIND, 'term:' . $term['id'] . ':image', array( 'dest' => $term['id'], 'hash' => (string) $term['image'], 'state' => 'done' ) );
				++$written;
				continue;
			}
			if ( '' === $term['color'] ) {
				continue;
			}
			$color = $data::color( $term['color'] );
			if ( '' === $color ) {
				++$invalid;
				continue;
			}
			update_term_meta( $term['id'], 'color', $color );
			Inventory::reg_put( self::KIND, 'term:' . $term['id'] . ':color', array( 'dest' => $term['id'], 'hash' => $color, 'state' => 'done' ) );
			++$written;
		}
		if ( $written ) {
			Inventory::site_code( 'plat.swatch.term', array( 'name' => $attribute['slug'], 'count' => $written ) );
		}
		if ( $kept ) {
			Inventory::site_code( 'plat.swatch.kept', array( 'name' => $attribute['slug'], 'count' => $kept ) );
		}
		if ( $invalid ) {
			Inventory::site_code( 'plat.swatch.color_invalid', array( 'name' => $attribute['slug'], 'count' => $invalid ) );
		}
		if ( $labels ) {
			Inventory::site_code( 'plat.swatch.label_lost', array( 'name' => $attribute['slug'], 'count' => $labels ) );
		}
	}

	/** Lo que no tiene equivalente: ajustes por producto y opciones globales. */
	private static function lost(): void {
		global $wpdb;
		$products = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", 'ep_variation_swatches' ) );
		if ( $products ) {
			Inventory::site_code( 'plat.swatch.products', array( 'count' => $products ) );
		}
		$options = array();
		foreach ( array( 'tooltip', 'auto_button', 'show_selected_label' ) as $name ) {
			$value = self::setting( $name );
			if ( in_array( $value, array( 'yes', 'on', '1', 1, true ), true ) ) {
				$options[] = $name;
			}
		}
		if ( $options ) {
			Inventory::site_code( 'plat.swatch.options', array( 'note' => implode( ', ', $options ) ) );
		}
	}

	/** `--revert`: borra sólo las entradas y metas que escribió la migración y nadie cambió. */
	public static function revert( bool $force = false ): array {
		$data  = self::DATA;
		$count = 0;
		$out   = array();
		foreach ( Inventory::reg_all( self::KIND ) as $source => $row ) {
			$source = (string) $source;
			if ( preg_match( '/^attr:([a-z0-9_-]+)$/', $source, $m ) && class_exists( $data ) ) {
				$current = $data::attribute( 'pa_' . $m[1] );
				if ( 'select' === ( $current['type'] ?? 'select' ) ) {
					Inventory::reg_forget( self::KIND, $source );
					continue;
				}
				if ( ! $force && md5( (string) wp_json_encode( $current ) ) !== (string) ( $row['hash'] ?? '' ) ) {
					$out[] = array( 'core.revert_skipped', array( 'type' => 'swatch', 'name' => $m[1] ) );
					continue;
				}
				$data::remove( $m[1] );
				++$count;
			} elseif ( preg_match( '/^term:(\d+):(color|image)$/', $source, $m ) ) {
				$current = (string) get_term_meta( (int) $m[1], $m[2], true );
				if ( '' !== $current && ! $force && $current !== (string) ( $row['hash'] ?? '' ) ) {
					$out[] = array( 'core.revert_skipped', array( 'type' => 'term_' . $m[2], 'id' => (int) $m[1] ) );
					continue;
				}
				if ( '' !== $current ) {
					delete_term_meta( (int) $m[1], $m[2] );
					++$count;
				}
			}
			Inventory::reg_forget( self::KIND, $source );
		}
		if ( $count ) {
			$out[] = array( 'plat.swatch.reverted', array( 'count' => $count ) );
		}
		return $out;
	}
}
