<?php
namespace Digitalisimo\Elements\Convert;

defined( 'ABSPATH' ) || exit;

/**
 * Catálogo de los widgets derivados de PRO Elements que trae DIGITALÍSIMO Elements (`docs/PRO_WIDGETS_OBJECTIVE.md`):
 * nombre del widget (`get_name()`) => [familia del programa, módulo de PRO]. Es la fuente única que usan el inventario
 * de DIGITALÍSIMO Migración, el ocultamiento del panel y las pruebas; `tests/elements-convert.php` comprueba que todo
 * widget de PRO registrado en Elements está aquí, así que una sincronización que traiga uno nuevo no pasa en silencio.
 *
 * Familias: C1 carruseles, C2 contenido, C3 navegación y búsqueda, C4 Theme Builder y elementos de tema, C5 entradas y
 * bucles, C6 formularios, C7 WooCommerce, C8 botones flotantes y Link in Bio, C9 otros.
 */
final class Catalog {
	const FAMILIES = array(
		'C1' => 'Carruseles',
		'C2' => 'Contenido',
		'C3' => 'Navegación y búsqueda',
		'C4' => 'Theme Builder y elementos de tema',
		'C5' => 'Entradas y bucles',
		'C6' => 'Formularios',
		'C7' => 'WooCommerce',
		'C8' => 'Botones flotantes y Link in Bio',
		'C9' => 'Otros',
	);

	const WIDGETS = array(
		'media-carousel' => array( 'C1', 'carousel' ),
		'reviews' => array( 'C1', 'carousel' ),
		'testimonial-carousel' => array( 'C1', 'carousel' ),
		'nested-carousel' => array( 'C1', 'nested-carousel' ),
		'slides' => array( 'C1', 'slides' ),
		'animated-headline' => array( 'C2', 'animated-headline' ),
		'blockquote' => array( 'C2', 'blockquote' ),
		'call-to-action' => array( 'C2', 'call-to-action' ),
		'code-highlight' => array( 'C2', 'code-highlight' ),
		'countdown' => array( 'C2', 'countdown' ),
		'flip-box' => array( 'C2', 'flip-box' ),
		'gallery' => array( 'C2', 'gallery' ),
		'hotspot' => array( 'C2', 'hotspot' ),
		'lottie' => array( 'C2', 'lottie' ),
		'price-list' => array( 'C2', 'pricing' ),
		'price-table' => array( 'C2', 'pricing' ),
		'progress-tracker' => array( 'C2', 'progress-tracker' ),
		'share-buttons' => array( 'C2', 'share-buttons' ),
		'table-of-contents' => array( 'C2', 'table-of-contents' ),
		'video-playlist' => array( 'C2', 'video-playlist' ),
		'mega-menu' => array( 'C3', 'mega-menu' ),
		'nav-menu' => array( 'C3', 'nav-menu' ),
		'off-canvas' => array( 'C3', 'off-canvas' ),
		'search' => array( 'C3', 'search' ),
		'archive-posts' => array( 'C4', 'theme-builder' ),
		'theme-archive-title' => array( 'C4', 'theme-builder' ),
		'theme-page-title' => array( 'C4', 'theme-builder' ),
		'theme-post-content' => array( 'C4', 'theme-builder' ),
		'theme-post-excerpt' => array( 'C4', 'theme-builder' ),
		'theme-post-featured-image' => array( 'C4', 'theme-builder' ),
		'theme-post-title' => array( 'C4', 'theme-builder' ),
		'theme-site-logo' => array( 'C4', 'theme-builder' ),
		'theme-site-title' => array( 'C4', 'theme-builder' ),
		'author-box' => array( 'C4', 'theme-elements' ),
		'breadcrumbs' => array( 'C4', 'theme-elements' ),
		'post-comments' => array( 'C4', 'theme-elements' ),
		'post-info' => array( 'C4', 'theme-elements' ),
		'post-navigation' => array( 'C4', 'theme-elements' ),
		'search-form' => array( 'C4', 'theme-elements' ),
		'sitemap' => array( 'C4', 'theme-elements' ),
		'loop-carousel' => array( 'C5', 'loop-builder' ),
		'loop-grid' => array( 'C5', 'loop-builder' ),
		'taxonomy-filter' => array( 'C5', 'loop-filter' ),
		'portfolio' => array( 'C5', 'posts' ),
		'posts' => array( 'C5', 'posts' ),
		'form' => array( 'C6', 'forms' ),
		'login' => array( 'C6', 'forms' ),
		'wc-add-to-cart' => array( 'C7', 'woocommerce' ),
		'wc-archive-products' => array( 'C7', 'woocommerce' ),
		'wc-categories' => array( 'C7', 'woocommerce' ),
		'wc-elements' => array( 'C7', 'woocommerce' ),
		'wc-products' => array( 'C7', 'woocommerce' ),
		'wc-single-elements' => array( 'C7', 'woocommerce' ),
		'woocommerce-archive-description' => array( 'C7', 'woocommerce' ),
		'woocommerce-archive-products' => array( 'C7', 'woocommerce' ),
		'woocommerce-breadcrumb' => array( 'C7', 'woocommerce' ),
		'woocommerce-cart' => array( 'C7', 'woocommerce' ),
		'woocommerce-category-image' => array( 'C7', 'woocommerce' ),
		'woocommerce-checkout-page' => array( 'C7', 'woocommerce' ),
		'woocommerce-menu-cart' => array( 'C7', 'woocommerce' ),
		'woocommerce-my-account' => array( 'C7', 'woocommerce' ),
		'woocommerce-notices' => array( 'C7', 'woocommerce' ),
		'woocommerce-product-add-to-cart' => array( 'C7', 'woocommerce' ),
		'woocommerce-product-additional-information' => array( 'C7', 'woocommerce' ),
		'woocommerce-product-content' => array( 'C7', 'woocommerce' ),
		'woocommerce-product-data-tabs' => array( 'C7', 'woocommerce' ),
		'woocommerce-product-images' => array( 'C7', 'woocommerce' ),
		'woocommerce-product-meta' => array( 'C7', 'woocommerce' ),
		'woocommerce-product-price' => array( 'C7', 'woocommerce' ),
		'woocommerce-product-rating' => array( 'C7', 'woocommerce' ),
		'woocommerce-product-related' => array( 'C7', 'woocommerce' ),
		'woocommerce-product-short-description' => array( 'C7', 'woocommerce' ),
		'woocommerce-product-stock' => array( 'C7', 'woocommerce' ),
		'woocommerce-product-title' => array( 'C7', 'woocommerce' ),
		'woocommerce-product-upsell' => array( 'C7', 'woocommerce' ),
		'woocommerce-products' => array( 'C7', 'woocommerce' ),
		'woocommerce-purchase-summary' => array( 'C7', 'woocommerce' ),
		'contact-buttons-var-1' => array( 'C8', 'floating-buttons' ),
		'contact-buttons-var-10' => array( 'C8', 'floating-buttons' ),
		'contact-buttons-var-3' => array( 'C8', 'floating-buttons' ),
		'contact-buttons-var-4' => array( 'C8', 'floating-buttons' ),
		'contact-buttons-var-5' => array( 'C8', 'floating-buttons' ),
		'contact-buttons-var-6' => array( 'C8', 'floating-buttons' ),
		'contact-buttons-var-7' => array( 'C8', 'floating-buttons' ),
		'contact-buttons-var-8' => array( 'C8', 'floating-buttons' ),
		'contact-buttons-var-9' => array( 'C8', 'floating-buttons' ),
		'floating-bars-var-2' => array( 'C8', 'floating-buttons' ),
		'floating-bars-var-3' => array( 'C8', 'floating-buttons' ),
		'link-in-bio-var-2' => array( 'C8', 'link-in-bio' ),
		'link-in-bio-var-3' => array( 'C8', 'link-in-bio' ),
		'link-in-bio-var-4' => array( 'C8', 'link-in-bio' ),
		'link-in-bio-var-5' => array( 'C8', 'link-in-bio' ),
		'link-in-bio-var-6' => array( 'C8', 'link-in-bio' ),
		'link-in-bio-var-7' => array( 'C8', 'link-in-bio' ),
		'global' => array( 'C9', 'global-widget' ),
		'template' => array( 'C9', 'library' ),
		'paypal-button' => array( 'C9', 'payments' ),
		'stripe-button' => array( 'C9', 'payments' ),
		'facebook-button' => array( 'C9', 'social' ),
		'facebook-comments' => array( 'C9', 'social' ),
		'facebook-embed' => array( 'C9', 'social' ),
		'facebook-page' => array( 'C9', 'social' ),
	);

	/** ¿Es un widget de PRO del catálogo? */
	public static function is_pro( $type ): bool {
		return is_string( $type ) && isset( self::WIDGETS[ $type ] );
	}

	/** Familia de un widget de PRO (`C1`…`C9`), o ''. */
	public static function family( $type ): string {
		return self::is_pro( $type ) ? self::WIDGETS[ $type ][0] : '';
	}

	/** Módulo de PRO que lo registra, o ''. */
	public static function module( $type ): string {
		return self::is_pro( $type ) ? self::WIDGETS[ $type ][1] : '';
	}

	/** Nombres de todos los widgets de PRO, en el orden del catálogo. */
	public static function names(): array {
		return array_keys( self::WIDGETS );
	}
}
