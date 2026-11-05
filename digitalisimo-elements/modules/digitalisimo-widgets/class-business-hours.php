<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/**
 * Horario por día como lista de descripción; el navegador resalta el día de hoy.
 *
 * Con «Estado en vivo» cada fila puede declarar su hora de apertura y cierre: el servidor pinta si el
 * negocio está abierto ahora en la zona horaria elegida y el script lo recalcula en el navegador, para
 * que una página en caché no muestre un estado viejo. El mensaje que no corresponde viaja en un
 * <template> y el script sólo intercambia nodos ya escapados.
 */
class Business_Hours_Widget extends \Elementor\Widget_Base {
	const DAYS = array( 'sunday' => 0, 'domingo' => 0, 'monday' => 1, 'lunes' => 1, 'tuesday' => 2, 'martes' => 2, 'wednesday' => 3, 'miércoles' => 3, 'miercoles' => 3, 'thursday' => 4, 'jueves' => 4, 'friday' => 5, 'viernes' => 5, 'saturday' => 6, 'sábado' => 6, 'sabado' => 6 );
	const OFFSETS = array( -12, -11, -10, -9.5, -9, -8, -7, -6, -5, -4, -3.5, -3, -2, -1, 0, 1, 2, 3, 3.5, 4, 4.5, 5, 5.5, 5.75, 6, 6.5, 7, 8, 8.75, 9, 9.5, 10, 10.5, 11, 12, 12.75, 13, 14 );

	/** Momento fijo para las pruebas; en producción siempre es null. */
	public static $now = null;

	public function get_name() { return 'digitalisimo-business-hours'; }
	public function get_title() { return 'Horario'; }
	public function get_icon() { return 'eicon-clock'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'horario', 'apertura', 'negocio', 'días', 'abierto' ); }
	public function get_style_depends() { return array( 'digitalisimo-business-hours' ); }
	/** El script resalta el día de hoy y recalcula estado, hora y fecha: sin esas opciones no se carga. */
	public function get_script_depends() {
		if ( ! method_exists( $this, 'is_type_instance' ) || $this->is_type_instance() || ! method_exists( $this, 'get_settings' ) ) { return array( 'digitalisimo-business-hours' ); }
		$s = (array) $this->get_settings();
		foreach ( array( 'highlight_today' => 'yes', 'live_status' => '', 'show_clock' => '', 'show_date' => '' ) as $key => $default ) {
			if ( 'yes' === ( $s[ $key ] ?? $default ) ) { return array( 'digitalisimo-business-hours' ); }
		}
		return array();
	}

	protected function register_controls() {
		$c    = '\\Elementor\\Controls_Manager';
		$time = array( 'type' => $c::DATE_TIME, 'picker_options' => array( 'enableTime' => true, 'noCalendar' => true, 'dateFormat' => 'H:i', 'time_24hr' => true ) );
		$this->start_controls_section( 'section_hours', array( 'label' => 'Horario' ) );
		$this->add_control( 'heading', array( 'label' => 'Título', 'type' => $c::TEXT, 'default' => '' ) );
		$repeater = new \Elementor\Repeater();
		$repeater->add_control( 'day', array( 'label' => 'Día', 'type' => $c::TEXT, 'default' => 'Lunes' ) );
		$repeater->add_control( 'weekday', array( 'label' => 'Día de la semana', 'type' => $c::SELECT, 'default' => '', 'options' => array( '' => 'Según el nombre del día', '1' => 'Lunes', '2' => 'Martes', '3' => 'Miércoles', '4' => 'Jueves', '5' => 'Viernes', '6' => 'Sábado', '0' => 'Domingo' ), 'description' => 'Para resaltar hoy y calcular el estado cuando el nombre no es un día (por ejemplo, «Lun»).' ) );
		$repeater->add_control( 'hours', array( 'label' => 'Horario', 'type' => $c::TEXT, 'default' => '9:00 – 18:00', 'description' => 'Vacío: se escribe con la apertura y el cierre.' ) );
		$repeater->add_control( 'opens', array( 'label' => 'Abre' ) + $time );
		$repeater->add_control( 'closes', array( 'label' => 'Cierra', 'description' => 'Sin apertura y cierre el día cuenta como cerrado en el estado en vivo. Un cierre anterior a la apertura termina al día siguiente.' ) + $time );
		$repeater->add_control( 'highlight', array( 'label' => 'Destacar', 'type' => $c::SWITCHER ) );
		$this->add_control( 'rows', array( 'label' => 'Días', 'type' => $c::REPEATER, 'fields' => $repeater->get_controls(), 'default' => array( array( 'day' => 'Lunes a viernes', 'hours' => '9:00 – 18:00' ), array( 'day' => 'Sábado', 'hours' => '10:00 – 14:00' ), array( 'day' => 'Domingo', 'hours' => 'Cerrado' ) ), 'title_field' => '{{{ day }}} · {{{ hours }}}' ) );
		$this->add_control( 'highlight_today', array( 'label' => 'Resaltar el día de hoy', 'type' => $c::SWITCHER, 'default' => 'yes' ) );
		$this->add_control( 'striped', array( 'label' => 'Filas alternas', 'type' => $c::SWITCHER ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_live', array( 'label' => 'Estado en vivo' ) );
		$zones = array( '' => 'Zona horaria del sitio' );
		foreach ( self::OFFSETS as $offset ) {
			$zones[ (string) $offset ] = self::offset_label( $offset );
		}
		$this->add_control( 'timezone', array( 'label' => 'Zona horaria', 'type' => $c::SELECT, 'default' => '', 'options' => $zones ) );
		$this->add_control( 'live_status', array( 'label' => 'Indicar si está abierto ahora', 'type' => $c::SWITCHER, 'description' => 'Usa el día de la semana y las horas de apertura y cierre de cada fila.' ) );
		$this->add_control( 'open_text', array( 'label' => 'Mensaje si está abierto', 'type' => $c::TEXTAREA, 'default' => 'Abierto ahora', 'condition' => array( 'live_status' => 'yes' ) ) );
		$this->add_control( 'closed_text', array( 'label' => 'Mensaje si está cerrado', 'type' => $c::TEXTAREA, 'default' => 'Cerrado ahora', 'condition' => array( 'live_status' => 'yes' ) ) );
		$this->add_control( 'show_clock', array( 'label' => 'Mostrar la hora actual', 'type' => $c::SWITCHER ) );
		$this->add_control( 'show_date', array( 'label' => 'Mostrar la fecha actual', 'type' => $c::SWITCHER ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_style', array( 'label' => 'Filas', 'tab' => $c::TAB_STYLE ) );
		$this->add_control( 'highlight_color', array( 'label' => 'Color destacado', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-hours' => '--digi-hours-accent: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'row_padding', array( 'label' => 'Relleno', 'type' => $c::DIMENSIONS, 'size_units' => array( 'px', 'em' ), 'selectors' => array( '{{WRAPPER}} .digi-hours__row' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_control( 'open_color', array( 'label' => 'Color de «abierto»', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-hours__status.is-open' => '--digi-hours-status: {{VALUE}};' ) ) );
		$this->add_control( 'closed_color', array( 'label' => 'Color de «cerrado»', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-hours__status.is-closed' => '--digi-hours-status: {{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	/** Número de día (0 = domingo) si el texto nombra un solo día; -1 si no. */
	public static function weekday( $label ) {
		$key = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( (string) $label ) ) : strtolower( trim( (string) $label ) );
		return self::DAYS[ $key ] ?? -1;
	}

	/** «HH:MM» de 24 horas, o '' si el valor no es una hora. */
	public static function clock_value( $value ) {
		return preg_match( '/^([01]?\d|2[0-3]):([0-5]\d)/', trim( (string) $value ), $m ) ? sprintf( '%02d:%02d', $m[1], $m[2] ) : '';
	}

	/** ¿Abierto en ese momento? Cada fila trae weekday (0-6 o -1), opens y closes en «HH:MM». */
	public static function is_open( array $rows, \DateTimeInterface $now ) {
		$day     = (int) $now->format( 'w' );
		$prev    = ( $day + 6 ) % 7;
		$minutes = (int) $now->format( 'G' ) * 60 + (int) $now->format( 'i' );
		foreach ( $rows as $row ) {
			if ( '' === $row['opens'] || '' === $row['closes'] || $row['weekday'] < 0 ) {
				continue;
			}
			$open  = (int) substr( $row['opens'], 0, 2 ) * 60 + (int) substr( $row['opens'], 3, 2 );
			$close = (int) substr( $row['closes'], 0, 2 ) * 60 + (int) substr( $row['closes'], 3, 2 );
			if ( $row['weekday'] === $day && ( $open < $close ? $minutes >= $open && $minutes < $close : $minutes >= $open ) ) {
				return true;
			}
			// Un cierre igual o anterior a la apertura termina al día siguiente.
			if ( $row['weekday'] === $prev && $close <= $open && $minutes < $close ) {
				return true;
			}
		}
		return false;
	}

	private static function offset_label( $offset ) {
		$minutes = (int) round( abs( $offset ) * 60 );
		return sprintf( 'UTC%s%02d:%02d', $offset < 0 ? '−' : '+', intdiv( $minutes, 60 ), $minutes % 60 );
	}

	/** Zona horaria y atributos para el script: nombre IANA o desplazamiento fijo en minutos. */
	private static function zone( $choice ) {
		if ( '' !== (string) $choice && is_numeric( $choice ) ) {
			$minutes = (int) round( (float) $choice * 60 );
			return array( new \DateTimeZone( sprintf( '%s%02d:%02d', $minutes < 0 ? '-' : '+', intdiv( abs( $minutes ), 60 ), abs( $minutes ) % 60 ) ), array( 'data-offset' => (string) $minutes ) );
		}
		$zone = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
		if ( preg_match( '/^([+-])(\d{2}):(\d{2})$/', $zone->getName(), $m ) ) {
			return array( $zone, array( 'data-offset' => (string) ( ( '-' === $m[1] ? -1 : 1 ) * ( (int) $m[2] * 60 + (int) $m[3] ) ) ) );
		}
		return array( $zone, array( 'data-tz' => $zone->getName() ) );
	}

	private static function option( $name, $fallback ) {
		$value = function_exists( 'get_option' ) ? (string) get_option( $name ) : '';
		return '' !== $value ? $value : $fallback;
	}

	/** Fecha u hora con el formato de WordPress, traducida cuando wp_date() está disponible. */
	private static function local( $format, \DateTimeImmutable $moment ) {
		return function_exists( 'wp_date' ) ? (string) wp_date( $format, $moment->getTimestamp(), $moment->getTimezone() ) : $moment->format( $format );
	}

	private static function kses( $html ) {
		return function_exists( 'wp_kses_post' ) ? wp_kses_post( $html ) : esc_html( $html );
	}

	protected function render() {
		$s           = $this->get_settings_for_display();
		$live        = 'yes' === ( $s['live_status'] ?? '' );
		$clock       = 'yes' === ( $s['show_clock'] ?? '' );
		$date        = 'yes' === ( $s['show_date'] ?? '' );
		$time_format = self::option( 'time_format', 'H:i' );
		$out         = '';
		$parsed      = array();
		foreach ( is_array( $s['rows'] ?? null ) ? $s['rows'] : array() as $index => $row ) {
			if ( ! is_array( $row ) ) { continue; }
			$day    = trim( wp_strip_all_tags( (string) ( $row['day'] ?? '' ) ) );
			$hours  = trim( wp_strip_all_tags( (string) ( $row['hours'] ?? '' ) ) );
			$opens  = self::clock_value( $row['opens'] ?? '' );
			$closes = self::clock_value( $row['closes'] ?? '' );
			if ( '' === $hours && '' !== $opens && '' !== $closes ) {
				$hours = gmdate( $time_format, strtotime( '1970-01-01 ' . $opens . ' UTC' ) ) . ' – ' . gmdate( $time_format, strtotime( '1970-01-01 ' . $closes . ' UTC' ) );
			}
			if ( '' === $day && '' === $hours ) { continue; }
			$chosen   = (string) ( $row['weekday'] ?? '' );
			$wd       = preg_match( '/^[0-6]$/', $chosen ) ? (int) $chosen : self::weekday( $day );
			$parsed[] = array( 'weekday' => $wd, 'opens' => $opens, 'closes' => $closes );
			$attrs    = $wd >= 0 ? ' data-weekday="' . esc_attr( (string) $wd ) . '"' : '';
			if ( $live && '' !== $opens && '' !== $closes ) {
				$attrs .= ' data-opens="' . esc_attr( $opens ) . '" data-closes="' . esc_attr( $closes ) . '"';
			}
			$out .= '<div class="digi-hours__row' . ( 'yes' === ( $row['highlight'] ?? '' ) ? ' is-highlighted' : '' ) . ' elementor-repeater-item-' . esc_attr( sanitize_html_class( (string) ( $row['_id'] ?? $index ) ) ) . '"' . $attrs . '><dt>' . esc_html( $day ) . '</dt><dd>' . esc_html( $hours ) . '</dd></div>';
		}
		if ( '' === $out ) { return; }
		$attrs = '';
		$now   = '';
		$state = '';
		if ( $live || $clock || $date ) {
			list( $zone, $data ) = self::zone( $s['timezone'] ?? '' );
			$moment              = ( new \DateTimeImmutable( '@' . ( self::$now instanceof \DateTimeInterface ? self::$now->getTimestamp() : time() ) ) )->setTimezone( $zone );
			$data['data-time-format'] = $time_format;
			$data['data-today']       = $moment->format( 'Y-m-d' );
			foreach ( $data as $name => $value ) {
				$attrs .= ' ' . $name . '="' . esc_attr( $value ) . '"';
			}
			if ( $clock || $date ) {
				$now = '<p class="digi-hours__now">'
					. ( $clock ? '<time class="digi-hours__clock">' . esc_html( self::local( $time_format, $moment ) ) . '</time>' : '' )
					. ( $date ? '<span class="digi-hours__date">' . esc_html( self::local( self::option( 'date_format', 'Y-m-d' ), $moment ) ) . '</span>' : '' )
					. '</p>';
			}
			if ( $live ) {
				$open   = self::is_open( $parsed, $moment );
				$shown  = self::kses( (string) ( $s[ $open ? 'open_text' : 'closed_text' ] ?? '' ) );
				$hidden = self::kses( (string) ( $s[ $open ? 'closed_text' : 'open_text' ] ?? '' ) );
				$state  = '<div class="digi-hours__status ' . ( $open ? 'is-open' : 'is-closed' ) . '" role="status" data-state="' . ( $open ? 'open' : 'closed' ) . '">' . $shown . '</div>'
					. '<template class="digi-hours__alt" data-state="' . ( $open ? 'closed' : 'open' ) . '">' . $hidden . '</template>';
			}
		}
		$heading = trim( wp_strip_all_tags( (string) ( $s['heading'] ?? '' ) ) );
		echo '<div class="digi-hours' . ( 'yes' === ( $s['striped'] ?? '' ) ? ' digi-hours--striped' : '' ) . '"' . ( 'yes' === ( $s['highlight_today'] ?? 'yes' ) ? ' data-digi-hours-today' : '' ) . $attrs . '>'
			. $now
			. ( '' !== $heading ? '<p class="digi-hours__heading">' . esc_html( $heading ) . '</p>' : '' )
			. '<dl class="digi-hours__list">' . $out . '</dl>' . $state . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cada parte se escapa al construirse.
	}
}
