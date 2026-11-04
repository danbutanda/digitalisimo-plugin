<?php
/**
 * Clases comunes de los carruseles de PRO (Swiper y las de PRO para flechas y paginación) → clases del motor propio
 * `digi-crs` (`modules/digitalisimo-carousel/class-engine.php`), para el CSS personalizado del elemento. No es un mapa
 * (`Registry` ignora `maps/_*.php`): cada mapa de carrusel lo suma a sus `classes` (`$propias + require …`, así lo
 * propio del widget gana). `Translator::rewrite_classes()` cambia sólo clases completas, la más larga primero; las que
 * en el motor son atributos (punto actual, flecha desactivada) pasan a su selector de atributo.
 */
defined( 'ABSPATH' ) || exit;

return array(
	// Raíz y pista.
	'.elementor-swiper'                     => '.digi-crs',
	'.elementor-main-swiper'                => '.digi-crs',
	'.e-n-carousel'                         => '.digi-crs',
	'.swiper-container'                     => '.digi-crs',
	'.swiper'                               => '.digi-crs',
	'.swiper-wrapper'                       => '.digi-crs__track',
	// Diapositivas.
	'.swiper-slide'                         => '.digi-crs__slide',
	'.swiper-slide-active'                  => '.digi-crs__current',
	'.swiper-slide-thumb-active'            => '.digi-crs__synced',
	// Flechas.
	'.elementor-swiper-button'              => '.digi-crs__arrow',
	'.elementor-swiper-button-prev'         => '.digi-crs__prev',
	'.elementor-swiper-button-next'         => '.digi-crs__next',
	'.swiper-button-prev'                   => '.digi-crs__prev',
	'.swiper-button-next'                   => '.digi-crs__next',
	'.swiper-button-disabled'               => '[aria-disabled=true]',
	// Paginación.
	'.swiper-pagination'                    => '.digi-crs__pagination',
	'.swiper-pagination-bullets'            => '.digi-crs__pagination',
	'.swiper-pagination-bullet'             => '.digi-crs__dot',
	'.swiper-pagination-bullet-active'      => '.digi-crs__dot[aria-current=true]',
	'.swiper-pagination-fraction'           => '.digi-crs__fraction',
	'.swiper-pagination-current'            => '.digi-crs__now',
	'.swiper-pagination-total'              => '.digi-crs__total',
	'.swiper-pagination-progressbar'        => '.digi-crs__bar',
	'.swiper-pagination-progressbar-fill'   => '.digi-crs__fill',
);
