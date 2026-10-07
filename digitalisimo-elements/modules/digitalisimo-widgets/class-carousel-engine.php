<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Estructura común para carruseles de contenido; el widget proporciona las tarjetas. */
final class Carousel_Engine {
	public static function open( $label, $count ) {
		echo '<section class="digi-carousel" role="region" aria-roledescription="carrusel" aria-label="' . esc_attr( $label ) . '" style="--digi-carousel-count:' . max( 1, absint( $count ) ) . '">';
		echo '<div class="digi-carousel__viewport" tabindex="0" data-digi-carousel-track><ul class="digi-carousel__slides">';
	}

	public static function close( $navigation ) {
		echo '</ul></div>';
		if ( $navigation ) {
			echo '<div class="digi-carousel__controls">';
			echo '<button class="digi-carousel__button" type="button" data-digi-carousel-prev aria-label="Anterior" disabled aria-disabled="true">&#x2039;</button>';
			echo '<button class="digi-carousel__button" type="button" data-digi-carousel-next aria-label="Siguiente">&#x203a;</button>';
			echo '</div>';
		}
		echo '</section>';
	}
}
