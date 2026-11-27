<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/class-image-markup.php';
require_once __DIR__ . '/class-carousel-engine.php';

/**
 * Capturas dentro de un dispositivo dibujado con CSS propio (celular con isla, tableta, laptop, monitor y ventana de
 * navegador), sobre el motor nativo compartido (`assets/js/carousel-engine.js`). La pantalla recorta la captura con
 * `object-fit: cover` al radio de sus esquinas; las flechas van fuera del dispositivo, a los lados y centradas en
 * vertical, y en pantallas estrechas pasan debajo junto con los puntos. Cada diapositiva puede ser una imagen, un color,
 * un vídeo MP4 o un vídeo de YouTube; los vídeos se reproducen silenciados y en bucle, con controles para poder
 * pausarlos. Con las fachadas de «Rendimiento» (`Front\Facades`), un vídeo de YouTube se pinta como portada con un botón
 * y YouTube no recibe ninguna petición hasta que se pulsa.
 */
class Device_Slider_Widget extends \Elementor\Widget_Base {
	/** Dispositivos, en el orden del panel. «Escritorio» es el valor de siempre de los widgets ya guardados. */
	const DEVICES = array( 'mobile' => 'Celular', 'tablet' => 'Tableta', 'laptop' => 'Laptop', 'desktop' => 'Escritorio (monitor)', 'browser' => 'Ventana de navegador' );

	/** Colores del marco; negro por defecto. */
	const FRAMES = array( 'black' => 'Negro', 'silver' => 'Plata', 'white' => 'Blanco' );

	/** Navegación (los mismos valores que guardaba Element Pack). */
	const NAVIGATION = array( 'arrows' => 'Flechas a los lados', 'dots' => 'Puntos debajo', 'arrows_dots' => 'Flechas y puntos', 'none' => 'Desactivada' );

	/** Flecha (trazo de 24 × 24) de los botones; la de «siguiente» es la misma reflejada. */
	const CHEVRON = '<svg class="digi-device-slider__chevron" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="M14.5 5.5 8 12l6.5 6.5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';

	public function get_name() { return 'digitalisimo-device-slider'; }
	public function get_title() { return 'Slider de dispositivos'; }
	public function get_icon() { return 'eicon-slider-device'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'dispositivo', 'capturas', 'slider', 'móvil', 'celular', 'tableta', 'laptop' ); }
	/** Hoja de cada dispositivo (hojas por variante, ≤ 4 KB cada una: docs/ELEMENTS_FRONT_OBJECTIVE.md, criterio 7). */
	const DEVICE_STYLES = array( 'mobile' => 'digitalisimo-device-slider-mobile', 'tablet' => 'digitalisimo-device-slider-tablet', 'laptop' => 'digitalisimo-device-slider-laptop', 'desktop' => 'digitalisimo-device-slider-desktop', 'browser' => 'digitalisimo-device-slider-browser' );

	/**
	 * Cada instancia declara sólo lo que pinta: la base (marco, pantalla y capturas), `-nav` con más de una captura
	 * (fila de puntos y arrastre), `-arrows` con flechas, la hoja de su dispositivo y, con YouTube, la de la fachada.
	 * El tipo (panel y editor) declara todas. El orden importa: base, nav, flechas y dispositivo.
	 */
	public function get_style_depends() {
		if ( ! method_exists( $this, 'is_type_instance' ) || $this->is_type_instance() || ! method_exists( $this, 'get_settings_for_display' ) ) {
			return array_merge( array( 'digitalisimo-carousel-engine', 'digitalisimo-device-slider', 'digitalisimo-device-slider-nav', 'digitalisimo-device-slider-arrows' ), array_values( self::DEVICE_STYLES ) );
		}
		$s     = (array) $this->get_settings_for_display();
		$count = count( self::items( $s ) );
		$o     = self::options( $s, $count );
		$out   = array( 'digitalisimo-carousel-engine', 'digitalisimo-device-slider' );
		if ( $count > 1 ) { $out[] = 'digitalisimo-device-slider-nav'; }
		if ( $o['arrows'] ) { $out[] = 'digitalisimo-device-slider-arrows'; }
		$out[] = self::DEVICE_STYLES[ $o['type'] ];
		return array_merge( $out, self::facade_depends( $s ) );
	}
	/**
	 * El motor mueve flechas y puntos y arrastra con el ratón: con una sola captura no se carga. Con la navegación
	 * desactivada también hace falta, para arrastrar (la pista ya no muestra barra de desplazamiento). El script de la
	 * fachada, sólo con YouTube.
	 */
	public function get_script_depends() {
		if ( ! method_exists( $this, 'is_type_instance' ) || $this->is_type_instance() || ! method_exists( $this, 'get_settings_for_display' ) ) { return array( 'digitalisimo-carousel-engine', 'digitalisimo-device-slider' ); }
		$s     = (array) $this->get_settings_for_display();
		$items = self::items( $s );
		$image = false;
		foreach ( $items as $item ) { $image = $image || 'image' === $item['background']; }
		// `digitalisimo-device-slider` tiñe la barra de estado y el fondo con los colores de cada captura.
		return array_merge( count( $items ) > 1 ? array( 'digitalisimo-carousel-engine' ) : array(), $image ? array( 'digitalisimo-device-slider' ) : array(), self::facade_depends( $s ) );
	}

	/** ¿Están aplicadas las fachadas en esta petición? */
	private static function facades() {
		return class_exists( '\\Digitalisimo\\Elements\\Front\\Facades' ) && \Digitalisimo\Elements\Front\Facades::active();
	}

	/** Trabajos de las portadas de las diapositivas de YouTube (caché de elementos y dependencias). */
	public static function facade_jobs( array $settings ) {
		if ( ! class_exists( '\\Digitalisimo\\Elements\\Front\\Facade_Images' ) ) { return array(); }
		$jobs = array();
		foreach ( self::items( $settings ) as $item ) {
			if ( 'youtube' === $item['background'] ) { $jobs[] = \Digitalisimo\Elements\Front\Facade_Images::video( 'youtube', self::youtube_id( $item['youtube_url'] ?? '' ) ); }
		}
		return array_values( array_filter( $jobs ) );
	}

	private static function facade_depends( array $settings ) {
		if ( ! self::facades() ) { return array(); }
		$youtube = false;
		foreach ( self::items( $settings ) as $item ) { $youtube = $youtube || 'youtube' === $item['background']; }
		return \Digitalisimo\Elements\Front\Facades::depends( $youtube );
	}

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_device', array( 'label' => 'Dispositivo y capturas' ) );
		$this->add_control( 'device_type', array( 'label' => 'Dispositivo', 'type' => $c::SELECT, 'default' => 'desktop', 'options' => self::DEVICES ) );
		$this->add_control( 'orientation', array( 'label' => 'Orientación', 'type' => $c::SELECT, 'default' => '', 'options' => array( '' => 'Automática (celular vertical, tableta horizontal)', 'portrait' => 'Vertical', 'landscape' => 'Horizontal' ), 'condition' => array( 'device_type' => array( 'mobile', 'tablet' ) ) ) );
		$this->add_control( 'frame_style', array( 'label' => 'Color del dispositivo', 'type' => $c::SELECT, 'default' => 'black', 'options' => self::FRAMES ) );
		$this->add_control( 'show_notch', array( 'label' => 'Isla de la cámara', 'type' => $c::SWITCHER, 'default' => 'yes', 'condition' => array( 'device_type' => 'mobile' ) ) );
		$this->add_control( 'status_bar', array( 'label' => 'Barra de estado', 'description' => 'Franja con la hora, la señal y la batería, del color de la parte de arriba de cada captura, para que la cámara no tape el contenido. Quítala si tus capturas ya la traen.', 'type' => $c::SWITCHER, 'default' => 'yes', 'condition' => array( 'device_type' => array( 'mobile', 'tablet' ) ) ) );
		$this->add_control( 'show_buttons', array( 'label' => 'Botones laterales', 'type' => $c::SWITCHER, 'default' => 'yes', 'condition' => array( 'device_type' => array( 'mobile', 'tablet' ) ) ) );
		$this->add_control( 'gallery_images', array( 'label' => 'Seleccionar varias capturas', 'type' => $c::GALLERY ) );
		$items = new \Elementor\Repeater();
		$items->add_control( 'background', array( 'label' => 'Contenido', 'type' => $c::SELECT, 'default' => 'image', 'options' => array( 'image' => 'Imagen', 'color' => 'Color', 'video' => 'Vídeo MP4', 'youtube' => 'Vídeo de YouTube' ) ) );
		$items->add_control( 'image', array( 'label' => 'Captura', 'type' => $c::MEDIA, 'dynamic' => array( 'active' => true ), 'condition' => array( 'background' => 'image' ) ) );
		$items->add_control( 'color', array( 'label' => 'Color', 'type' => $c::COLOR, 'default' => '#14ABF4', 'condition' => array( 'background' => 'color' ), 'selectors' => array( '{{WRAPPER}} {{CURRENT_ITEM}} .digi-device-slider__color' => 'background-color:{{VALUE}};' ) ) );
		$items->add_control( 'video', array( 'label' => 'Vídeo', 'type' => $c::MEDIA, 'media_types' => array( 'video' ), 'dynamic' => array( 'active' => true ), 'condition' => array( 'background' => 'video' ) ) );
		$items->add_control( 'youtube_url', array( 'label' => 'Dirección de YouTube', 'type' => $c::TEXT, 'placeholder' => 'https://youtu.be/…', 'dynamic' => array( 'active' => true ), 'condition' => array( 'background' => 'youtube' ) ) );
		$items->add_control( 'title', array( 'label' => 'Título', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ) ) );
		$items->add_control( 'link', array( 'label' => 'Enlace', 'type' => $c::URL, 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'slides', array( 'label' => 'Capturas con datos individuales', 'type' => $c::REPEATER, 'fields' => $items->get_controls(), 'title_field' => '{{{ title }}}' ) );
		$this->add_control( 'image_size', array( 'label' => 'Tamaño de archivo', 'type' => $c::SELECT, 'default' => 'large', 'options' => array( 'medium' => 'Mediano', 'large' => 'Grande', 'full' => 'Original' ) ) );
		$this->add_control( 'image_position', array( 'label' => 'Encuadre de la captura', 'type' => $c::SELECT, 'default' => 'width', 'options' => array( 'width' => 'Ajustar al ancho, desde arriba (sitios web)', 'top' => 'Llenar la pantalla, desde arriba', 'center' => 'Llenar la pantalla, centrado (fotos)' ) ) );
		$this->add_control( 'navigation', array( 'label' => 'Navegación', 'type' => $c::SELECT, 'default' => 'arrows', 'options' => self::NAVIGATION ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_device_style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_responsive_control( 'width', array( 'label' => 'Ancho máximo del dispositivo', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 200, 'max' => 1400 ) ), 'selectors' => array( '{{WRAPPER}} .digi-device-slider' => 'max-width:{{SIZE}}px;' ) ) );
		$this->add_control( 'align', array( 'label' => 'Alineación', 'type' => $c::CHOOSE, 'options' => array( 'left' => array( 'title' => 'Izquierda', 'icon' => 'eicon-text-align-left' ), 'center' => array( 'title' => 'Centro', 'icon' => 'eicon-text-align-center' ), 'right' => array( 'title' => 'Derecha', 'icon' => 'eicon-text-align-right' ) ), 'selectors_dictionary' => array( 'left' => 'margin-inline:0 auto', 'center' => 'margin-inline:auto', 'right' => 'margin-inline:auto 0' ), 'selectors' => array( '{{WRAPPER}} .digi-device-slider' => '{{VALUE}};' ) ) );
		$this->add_control( 'frame_color', array( 'label' => 'Color propio del marco', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-device-slider' => '--digi-ds-frame:{{VALUE}};' ) ) );
		$this->add_control( 'device_buttons_color', array( 'label' => 'Color de los botones laterales', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-device-slider' => '--digi-ds-buttons:{{VALUE}};' ), 'condition' => array( 'device_type' => array( 'mobile', 'tablet' ) ) ) );
		$this->add_control( 'arrows_color', array( 'label' => 'Color de las flechas y del punto activo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-device-slider' => '--digi-ds-arrow-bg:{{VALUE}};' ), 'condition' => array( 'navigation!' => 'none' ) ) );
		$this->add_control( 'arrows_hover_color', array( 'label' => 'Color de las flechas al pasar el cursor', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-device-slider' => '--digi-ds-arrow-bg-hover:{{VALUE}};' ), 'condition' => array( 'navigation' => array( 'arrows', 'arrows_dots' ) ) ) );
		$this->add_responsive_control( 'arrows_size', array( 'label' => 'Tamaño de las flechas', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 32, 'max' => 72 ) ), 'selectors' => array( '{{WRAPPER}} .digi-device-slider' => '--digi-ds-arrow:{{SIZE}}px;' ), 'condition' => array( 'navigation' => array( 'arrows', 'arrows_dots' ) ) ) );
		$this->end_controls_section();
	}

	/** Identificador de 11 caracteres de un enlace de YouTube, o ''. */
	public static function youtube_id( $url ) {
		return preg_match( '%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?|shorts)/|.*[?&]v=)|youtu\.be/)([A-Za-z0-9_-]{11})%i', (string) $url, $m ) ? $m[1] : '';
	}

	private static function items( $settings ) {
		$items = array();
		foreach ( is_array( $settings['gallery_images'] ?? null ) ? $settings['gallery_images'] : array() as $image ) { if ( is_array( $image ) && ( ! empty( $image['id'] ) || ! empty( $image['url'] ) ) ) { $items[] = array( 'background' => 'image', 'image' => $image, 'title' => '', 'link' => array() ); } }
		foreach ( is_array( $settings['slides'] ?? null ) ? $settings['slides'] : array() as $item ) {
			if ( ! is_array( $item ) ) { continue; }
			$kind = in_array( $item['background'] ?? 'image', array( 'image', 'color', 'video', 'youtube' ), true ) ? ( $item['background'] ?? 'image' ) : 'image';
			$item['background'] = $kind;
			if ( 'color' === $kind
				|| ( 'image' === $kind && is_array( $item['image'] ?? null ) && ( ! empty( $item['image']['id'] ) || ! empty( $item['image']['url'] ) ) )
				|| ( 'video' === $kind && ! empty( $item['video']['url'] ) )
				|| ( 'youtube' === $kind && '' !== self::youtube_id( $item['youtube_url'] ?? '' ) ) ) {
				$items[] = $item;
			}
		}
		return $items;
	}

	/**
	 * Opciones del dispositivo ya validadas: tipo, orientación, color, isla, botones, encuadre y navegación, con las
	 * clases del contenedor. La usan render() y las pruebas; el editor repite la misma lógica en content_template().
	 */
	public static function options( array $s, $count ) {
		$type   = isset( self::DEVICES[ $s['device_type'] ?? '' ] ) ? $s['device_type'] : 'desktop';
		$frame  = isset( self::FRAMES[ $s['frame_style'] ?? '' ] ) ? $s['frame_style'] : 'black';
		$nav    = isset( self::NAVIGATION[ $s['navigation'] ?? '' ] ) ? $s['navigation'] : 'arrows';
		// «Automática»: la tableta va en horizontal, como la pintaba el widget antes del rediseño (4:3 apaisado).
		$turn   = (string) ( $s['orientation'] ?? '' );
		$rotate = ( 'mobile' === $type && 'landscape' === $turn ) || ( 'tablet' === $type && 'portrait' !== $turn );
		$notch  = 'mobile' === $type && '' !== (string) ( $s['show_notch'] ?? 'yes' );
		$keys   = in_array( $type, array( 'mobile', 'tablet' ), true ) && '' !== (string) ( $s['show_buttons'] ?? 'yes' );
		$status = in_array( $type, array( 'mobile', 'tablet' ), true ) && '' !== (string) ( $s['status_bar'] ?? 'yes' );
		$fit    = in_array( $s['image_position'] ?? '', array( 'top', 'center' ), true ) ? $s['image_position'] : 'width';
		$arrows = $count > 1 && in_array( $nav, array( 'arrows', 'arrows_dots' ), true );
		$dots   = $count > 1 && in_array( $nav, array( 'dots', 'arrows_dots' ), true );
		$class  = array( 'digi-device-slider', 'digi-device-slider--' . $type, 'digi-device-slider--frame-' . $frame );
		if ( $rotate ) { $class[] = 'digi-device-slider--landscape'; }
		if ( $keys ) { $class[] = 'digi-device-slider--keys'; }
		if ( $status ) { $class[] = 'digi-device-slider--status'; }
		if ( 'width' !== $fit ) { $class[] = 'digi-device-slider--fit-' . $fit; }
		if ( $arrows ) { $class[] = 'digi-device-slider--arrows'; }
		if ( $dots ) { $class[] = 'digi-device-slider--dots'; }
		return array( 'type' => $type, 'frame' => $frame, 'navigation' => $nav, 'landscape' => $rotate, 'notch' => $notch, 'buttons' => $keys, 'status' => $status, 'fit' => $fit, 'arrows' => $arrows, 'dots' => $dots, 'class' => implode( ' ', $class ) );
	}

	/**
	 * Barra de estado propia (hora, señal, wifi y batería) en un SVG que escala con la pantalla. En el celular, las dos
	 * orejas a los lados de la isla (393 × 54, como un iPhone); en la tableta, una franja fina (820 o 1180 × 24). El
	 * celular en horizontal deja sólo la franja de color junto a la isla, como iOS.
	 */
	public static function status_svg( array $o ) {
		if ( 'mobile' === $o['type'] && $o['landscape'] ) { return ''; }
		$icons = '<path d="M0 8h3v3H0zM4.5 6h3v5h-3zM9 4h3v7H9zM13.5 2h3v9h-3z"/><path d="M19 5.4a9 9 0 0 1 12 0M21.6 8a5.2 5.2 0 0 1 6.8 0" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/><circle cx="25" cy="10.4" r="1.3"/><rect x="36.5" y="1.5" width="23" height="10" rx="3" fill="none" stroke="currentColor" stroke-opacity=".45"/><rect x="38.5" y="3.5" width="17" height="6" rx="1.6"/><rect x="61" y="4.7" width="1.8" height="3.6" rx=".9" opacity=".45"/>';
		$font  = 'font-family="system-ui,-apple-system,Segoe UI,Roboto,sans-serif" font-weight="600"';
		if ( 'mobile' === $o['type'] ) {
			return '<svg viewBox="0 0 393 54" preserveAspectRatio="xMidYMid meet" focusable="false"><text x="68" y="35" font-size="17" text-anchor="middle" ' . $font . '>9:41</text><g transform="translate(287 22.5) scale(1.2)">' . $icons . '</g></svg>';
		}
		$w = $o['landscape'] ? 1180 : 820;
		return '<svg viewBox="0 0 ' . $w . ' 24" preserveAspectRatio="xMidYMid meet" focusable="false"><text x="22" y="16.5" font-size="12" ' . $font . '>9:41</text><g transform="translate(' . ( $w - 86 ) . ' 5.5) scale(.95)">' . $icons . '</g></svg>';
	}

	/** `sizes` de la captura según el ancho que de verdad ocupa la pantalla del dispositivo. */
	private static function image_sizes( array $o ) {
		if ( 'mobile' === $o['type'] && ! $o['landscape'] ) { return '(max-width: 600px) 90vw, 400px'; }
		if ( 'mobile' === $o['type'] || 'tablet' === $o['type'] ) { return '(max-width: 600px) 90vw, 820px'; }
		return '';
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$items = self::items( $s );
		if ( ! $items ) { return; }
		$size  = in_array( $s['image_size'] ?? 'large', array( 'medium', 'large', 'full' ), true ) ? ( $s['image_size'] ?? 'large' ) : 'large';
		$sizes = self::image_sizes( self::options( $s, 1 ) );
		// Primero el contenido de cada captura: las que no producen nada (un adjunto borrado) no cuentan para los
		// puntos, las flechas ni el arrastre.
		$slides = array();
		foreach ( $items as $index => $item ) {
			$title = trim( wp_strip_all_tags( (string) ( $item['title'] ?? '' ) ) );
			$markup = '';
			if ( 'color' === $item['background'] ) {
				$markup = '<span class="digi-device-slider__color" aria-hidden="true"></span>';
			} elseif ( 'video' === $item['background'] ) {
				$markup = '<video class="digi-device-slider__video" src="' . esc_url( $item['video']['url'] ) . '" autoplay muted loop playsinline controls preload="metadata"' . ( '' !== $title ? ' aria-label="' . esc_attr( $title ) . '"' : '' ) . '></video>';
			} elseif ( 'youtube' === $item['background'] ) {
				$yt = self::youtube_id( $item['youtube_url'] ?? '' );
				$src = 'https://www.youtube-nocookie.com/embed/' . $yt . '?autoplay=1&mute=1&loop=1&playlist=' . $yt . '&playsinline=1&rel=0';
				$markup = self::facades()
					? \Digitalisimo\Elements\Front\Facades::video( $src, '' !== $title ? $title : 'Vídeo de YouTube', 'digi-device-slider__video', \Digitalisimo\Elements\Front\Facade_Images::video( 'youtube', $yt ), 'autoplay; encrypted-media; picture-in-picture' )
					: Image_Markup::preconnect( 'https://www.youtube-nocookie.com/' ) . '<iframe class="digi-device-slider__video" src="' . esc_url( $src ) . '" title="' . esc_attr( '' !== $title ? $title : 'Vídeo de YouTube' ) . '" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen' . ( $index > 0 ? ' loading="lazy"' : '' ) . '></iframe>';
			} else {
				$args = array( 'size' => $size, 'class' => 'digi-device-slider__image', 'alt' => $title, 'attrs' => array( 'draggable' => 'false' ) );
				if ( '' !== $sizes ) { $args['sizes'] = $sizes; } else { $args['columns'] = array( 1 ); }
				$markup = Image_Markup::img( $item['image'], $args );
			}
			if ( $markup ) { $slides[] = array( $index, $item, $title, $markup ); }
		}
		if ( ! $slides ) { return; }
		$count = count( $slides );
		$o     = self::options( $s, $count );
		echo '<div class="' . esc_attr( $o['class'] ) . '">';
		echo '<section class="digi-carousel digi-device-slider__carousel" role="region" aria-roledescription="carrusel" aria-label="Capturas de dispositivo" style="--digi-carousel-count:' . $count . '"' . ( $o['dots'] ? ' data-digi-carousel-dots' : '' ) . ( $count > 1 ? ' data-digi-carousel-drag' : '' ) . '>';
		echo '<div class="digi-device-slider__device"><div class="digi-device-slider__frame">';
		echo '<div class="digi-device-slider__screen"><div class="digi-carousel__viewport" tabindex="0" data-digi-carousel-track><ul class="digi-carousel__slides">';
		foreach ( $slides as list( $index, $item, $title, $markup ) ) {
			echo '<li class="digi-carousel__slide digi-device-slider__slide' . ( ! empty( $item['_id'] ) ? ' elementor-repeater-item-' . esc_attr( sanitize_html_class( (string) $item['_id'] ) ) : '' ) . '">';
			$link = is_array( $item['link'] ?? null ) ? $item['link'] : array();
			if ( ! empty( $link['url'] ) ) {
				$key = 'device_link_' . $index;
				$this->add_link_attributes( $key, $link );
				if ( ! empty( $link['is_external'] ) ) { $this->add_render_attribute( $key, 'rel', array( 'noopener', 'noreferrer' ) ); }
				// Un vídeo con controles no va dentro de un enlace: el enlace pasa al título.
				if ( in_array( $item['background'], array( 'video', 'youtube' ), true ) ) {
					echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- construido y escapado arriba.
					if ( $title ) { echo '<a class="digi-device-slider__title" ' . $this->get_render_attribute_string( $key ) . '>' . esc_html( $title ) . '</a>'; $title = ''; }
				} else {
					echo '<a ' . $this->get_render_attribute_string( $key ) . '>' . $markup . '</a>';
				}
			}
			else { echo $markup; }
			if ( $title ) { echo '<span class="digi-device-slider__title">' . esc_html( $title ) . '</span>'; }
			// Al final del marcado; la hoja la sube arriba (order: -1). Oscura sobre los vídeos.
			if ( $o['status'] ) { echo '<span class="digi-device-slider__status' . ( 'image' === $item['background'] || 'color' === $item['background'] ? '' : ' digi-device-slider__status--dark' ) . '" aria-hidden="true">' . self::status_svg( $o ) . '</span>'; } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG constante.
			echo '</li>';
		}
		echo '</ul></div>';
		if ( $o['notch'] ) { echo '<span class="digi-device-slider__island" aria-hidden="true"></span>'; }
		echo '</div>';
		if ( in_array( $o['type'], array( 'tablet', 'laptop' ), true ) ) { echo '<span class="digi-device-slider__camera" aria-hidden="true"></span>'; }
		if ( 'browser' === $o['type'] ) { echo '<span class="digi-device-slider__bar" aria-hidden="true"></span>'; }
		if ( $o['arrows'] ) {
			echo '<button class="digi-carousel__button digi-device-slider__arrow digi-device-slider__arrow--prev" type="button" data-digi-carousel-prev aria-label="Captura anterior" disabled aria-disabled="true">' . self::CHEVRON . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG constante.
			echo '<button class="digi-carousel__button digi-device-slider__arrow digi-device-slider__arrow--next" type="button" data-digi-carousel-next aria-label="Captura siguiente">' . self::CHEVRON . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG constante.
		}
		echo '</div>';
		if ( 'laptop' === $o['type'] ) { echo '<span class="digi-device-slider__base" aria-hidden="true"></span>'; }
		if ( 'desktop' === $o['type'] ) { echo '<span class="digi-device-slider__stand" aria-hidden="true"></span>'; }
		echo '</div>';
		if ( $o['arrows'] || $o['dots'] ) {
			echo '<div class="digi-device-slider__nav">';
			if ( $o['dots'] ) {
				for ( $index = 0; $index < $count; $index++ ) {
					echo '<button class="digi-device-slider__dot" type="button" data-digi-carousel-dot="' . $index . '" aria-label="' . esc_attr( sprintf( 'Captura %d de %d', $index + 1, $count ) ) . '"' . ( 0 === $index ? ' aria-current="true"' : '' ) . '></button>';
				}
			}
			echo '</div>';
		}
		echo '</section></div>';
	}

	protected function content_template() {
		?>
		<# var devices = ['mobile','tablet','laptop','desktop','browser'], type = _.contains(devices, settings.device_type) ? settings.device_type : 'desktop';
		var frame = _.contains(['black','silver','white'], settings.frame_style) ? settings.frame_style : 'black';
		var nav = _.contains(['arrows','dots','arrows_dots','none'], settings.navigation) ? settings.navigation : 'arrows';
		var ytId = function(url){ var m = String(url || '').match(/(?:youtube(?:-nocookie)?\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?|shorts)\/|.*[?&]v=)|youtu\.be\/)([A-Za-z0-9_-]{11})/i); return m ? m[1] : ''; };
		var items = (settings.gallery_images || []).map(function(image){ return { background:'image', image:image }; }).concat(settings.slides || []).filter(function(item){ if (!item) return false; var kind = item.background || 'image'; return kind === 'color' || (kind === 'image' && item.image && item.image.url) || (kind === 'video' && item.video && item.video.url) || (kind === 'youtube' && ytId(item.youtube_url)); });
		var linkRel = function(link){ return [ link && link.nofollow ? 'nofollow' : '', link && link.is_external ? 'noopener noreferrer' : '' ].filter(Boolean).join(' '); };
		var handheld = type === 'mobile' || type === 'tablet';
		var landscape = (type === 'mobile' && settings.orientation === 'landscape') || (type === 'tablet' && settings.orientation !== 'portrait');
		var notch = type === 'mobile' && settings.show_notch !== '';
		var keys = handheld && settings.show_buttons !== '';
		var arrows = items.length > 1 && (nav === 'arrows' || nav === 'arrows_dots');
		var dots = items.length > 1 && (nav === 'dots' || nav === 'arrows_dots');
		var classes = ['digi-device-slider', 'digi-device-slider--' + type, 'digi-device-slider--frame-' + frame];
		if (landscape) { classes.push('digi-device-slider--landscape'); }
		if (keys) { classes.push('digi-device-slider--keys'); }
		var status = handheld && settings.status_bar !== '';
		var statusSvgs = <?php echo json_encode( array( 'mobile' => self::status_svg( array( 'type' => 'mobile', 'landscape' => false ) ), 'tablet' => self::status_svg( array( 'type' => 'tablet', 'landscape' => false ) ), 'tabletWide' => self::status_svg( array( 'type' => 'tablet', 'landscape' => true ) ) ) ); // phpcs:ignore ?>;
		var statusSvg = type === 'mobile' ? (landscape ? '' : statusSvgs.mobile) : (landscape ? statusSvgs.tabletWide : statusSvgs.tablet);
		if (status) { classes.push('digi-device-slider--status'); }
		if (settings.image_position === 'center' || settings.image_position === 'top') { classes.push('digi-device-slider--fit-' + settings.image_position); }
		if (arrows) { classes.push('digi-device-slider--arrows'); }
		if (dots) { classes.push('digi-device-slider--dots'); }
		var chevron = '<svg class="digi-device-slider__chevron" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="M14.5 5.5 8 12l6.5 6.5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>'; #>
		<# if ( items.length ) { #><div class="{{ classes.join(' ') }}"><section class="digi-carousel digi-device-slider__carousel" role="region" aria-roledescription="carrusel" aria-label="Capturas de dispositivo" style="--digi-carousel-count:{{ items.length }}"<# if (dots) { #> data-digi-carousel-dots<# } #><# if (items.length > 1) { #> data-digi-carousel-drag<# } #>><div class="digi-device-slider__device"><div class="digi-device-slider__frame"><div class="digi-device-slider__screen"><div class="digi-carousel__viewport" tabindex="0" data-digi-carousel-track><ul class="digi-carousel__slides"><# _.each(items,function(item){ var link = item.link || {}; var rel = linkRel(link); #><# var kind = item.background || 'image'; #><li class="digi-carousel__slide digi-device-slider__slide<# if (item._id) { #> elementor-repeater-item-{{ item._id }}<# } #>"><# if (kind === 'color') { #><span class="digi-device-slider__color" aria-hidden="true"></span><# } else if (kind === 'video') { #><video class="digi-device-slider__video" src="{{ item.video.url }}" autoplay muted loop playsinline controls preload="metadata"></video><# } else if (kind === 'youtube') { var yt = ytId(item.youtube_url); #><iframe class="digi-device-slider__video" src="https://www.youtube-nocookie.com/embed/{{ yt }}?autoplay=1&mute=1&loop=1&playlist={{ yt }}&playsinline=1&rel=0" title="{{ item.title || 'Vídeo de YouTube' }}" allowfullscreen></iframe><# } else { #><# if (link.url) { #><a href="{{ link.url }}"<# if (link.is_external) { #> target="_blank"<# } #><# if (rel) { #> rel="{{ rel }}"<# } #>><# } #><img class="digi-device-slider__image" src="{{ item.image.url }}" alt="{{ item.image.alt || item.title || '' }}" draggable="false"><# if (link.url) { #></a><# } #><# } #><# if (item.title) { #><span class="digi-device-slider__title">{{ item.title }}</span><# } #><# if (status) { #><span class="digi-device-slider__status<# if (kind === 'video' || kind === 'youtube') { #> digi-device-slider__status--dark<# } #>" aria-hidden="true">{{{ statusSvg }}}</span><# } #></li><# }); #></ul></div><# if (notch) { #><span class="digi-device-slider__island" aria-hidden="true"></span><# } #></div><# if (type === 'tablet' || type === 'laptop') { #><span class="digi-device-slider__camera" aria-hidden="true"></span><# } #><# if (type === 'browser') { #><span class="digi-device-slider__bar" aria-hidden="true"></span><# } #><# if (arrows) { #><button class="digi-carousel__button digi-device-slider__arrow digi-device-slider__arrow--prev" type="button" data-digi-carousel-prev aria-label="Captura anterior" disabled aria-disabled="true">{{{ chevron }}}</button><button class="digi-carousel__button digi-device-slider__arrow digi-device-slider__arrow--next" type="button" data-digi-carousel-next aria-label="Captura siguiente">{{{ chevron }}}</button><# } #></div><# if (type === 'laptop') { #><span class="digi-device-slider__base" aria-hidden="true"></span><# } #><# if (type === 'desktop') { #><span class="digi-device-slider__stand" aria-hidden="true"></span><# } #></div><# if (arrows || dots) { #><div class="digi-device-slider__nav"><# if (dots) { _.each(items, function(item, index){ #><button class="digi-device-slider__dot" type="button" data-digi-carousel-dot="{{ index }}" aria-label="Captura {{ index + 1 }} de {{ items.length }}"<# if (!index) { #> aria-current="true"<# } #>></button><# }); } #></div><# } #></section></div><# } #>
		<?php
	}
}
