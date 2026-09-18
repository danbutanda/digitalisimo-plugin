<?php
defined( 'ABSPATH' ) || exit;

/**
 * Teléfono y horario del negocio local.
 *
 * El teléfono se pedía en un campo libre, sin decir si había que escribir la
 * lada: ahora el país se elige de una lista y sólo se teclea el número.
 *
 * El horario era un textarea con la sintaxis de schema.org («Mo-Fr 09:00-18:00»)
 * que además nunca llegaba a publicarse. Ahora se elige por día, admite horario
 * partido y se emite como openingHoursSpecification, que es lo que Google lee.
 */
class Digitalisimo_Integrations_Local_Business {
	const PHONE_CC = 'seo_local_phone_cc';
	const PHONE    = 'seo_local_phone';
	const SCHEDULE = 'seo_local_schedule';
	const LEGACY   = 'seo_local_hours';

	/** Intervalo de los desplegables de hora, en minutos. */
	const STEP = 30;

	/** Ladas disponibles. Varios países comparten prefijo, así que se agrupan. */
	public static function country_codes() {
		return array(
			'+52'  => 'México (+52)',
			'+54'  => 'Argentina (+54)',
			'+591' => 'Bolivia (+591)',
			'+55'  => 'Brasil (+55)',
			'+56'  => 'Chile (+56)',
			'+57'  => 'Colombia (+57)',
			'+506' => 'Costa Rica (+506)',
			'+53'  => 'Cuba (+53)',
			'+593' => 'Ecuador (+593)',
			'+503' => 'El Salvador (+503)',
			'+34'  => 'España (+34)',
			'+1'   => 'Estados Unidos / Canadá (+1)',
			'+502' => 'Guatemala (+502)',
			'+504' => 'Honduras (+504)',
			'+505' => 'Nicaragua (+505)',
			'+507' => 'Panamá (+507)',
			'+595' => 'Paraguay (+595)',
			'+51'  => 'Perú (+51)',
			'+1809' => 'República Dominicana (+1 809)',
			'+598' => 'Uruguay (+598)',
			'+58'  => 'Venezuela (+58)',
		);
	}

	/** Días en orden de semana laboral, con su nombre en schema.org. */
	public static function days() {
		return array(
			'mon' => array( 'Lunes', 'Monday' ),
			'tue' => array( 'Martes', 'Tuesday' ),
			'wed' => array( 'Miércoles', 'Wednesday' ),
			'thu' => array( 'Jueves', 'Thursday' ),
			'fri' => array( 'Viernes', 'Friday' ),
			'sat' => array( 'Sábado', 'Saturday' ),
			'sun' => array( 'Domingo', 'Sunday' ),
		);
	}

	/** Horas seleccionables, de 00:00 al final del día. */
	public static function times() {
		$times = array();
		for ( $minute = 0; $minute < 1440; $minute += self::STEP ) {
			$value           = sprintf( '%02d:%02d', intdiv( $minute, 60 ), $minute % 60 );
			$times[ $value ] = $value;
		}
		$times['23:59'] = '23:59';
		return $times;
	}

	/** Teléfono completo tal como se publica: lada y número. */
	public static function phone() {
		$number = trim( (string) Digitalisimo_Integrations_SEO_Resolver::option( self::PHONE, '' ) );
		if ( '' === $number ) return '';
		// Un número guardado antes de existir la lista ya traía su prefijo.
		if ( 0 === strpos( $number, '+' ) ) return $number;
		$code = trim( (string) Digitalisimo_Integrations_SEO_Resolver::option( self::PHONE_CC, '' ) );
		return $code ? $code . ' ' . $number : $number;
	}

	/** Horario efectivo, con todos los días presentes y normalizados. */
	public static function schedule() {
		$stored = Digitalisimo_Integrations_SEO_Resolver::option( self::SCHEDULE, array() );
		$stored = is_array( $stored ) ? $stored : array();
		// Nada elegido todavía: se aprovecha lo que hubiera en el textarea anterior.
		if ( ! array_filter( $stored ) ) $stored = self::from_legacy();
		$out    = array();
		foreach ( self::days() as $day => $unused ) {
			$row            = is_array( $stored[ $day ] ?? null ) ? $stored[ $day ] : array();
			$out[ $day ] = array(
				'state' => in_array( $row['state'] ?? '', array( 'open', 'closed', 'always' ), true ) ? $row['state'] : 'closed',
				'from'  => self::time( $row['from'] ?? '09:00', '09:00' ),
				'to'    => self::time( $row['to'] ?? '18:00', '18:00' ),
				'from2' => self::time( $row['from2'] ?? '', '' ),
				'to2'   => self::time( $row['to2'] ?? '', '' ),
			);
		}
		return $out;
	}

	/**
	 * openingHoursSpecification para el schema.
	 *
	 * Los días cerrados se omiten, que es como Google espera declararlos, y un
	 * horario partido produce dos entradas del mismo día.
	 */
	public static function schema_hours() {
		$spec = array();
		foreach ( self::schedule() as $day => $row ) {
			if ( 'closed' === $row['state'] ) continue;
			$name = self::days()[ $day ][1];
			if ( 'always' === $row['state'] ) {
				$spec[] = array( '@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $name, 'opens' => '00:00', 'closes' => '23:59' );
				continue;
			}
			if ( $row['from'] && $row['to'] ) $spec[] = array( '@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $name, 'opens' => $row['from'], 'closes' => $row['to'] );
			if ( $row['from2'] && $row['to2'] ) $spec[] = array( '@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $name, 'opens' => $row['from2'], 'closes' => $row['to2'] );
		}
		return $spec;
	}

	/**
	 * Convierte el textarea anterior («Mo-Fr 09:00-18:00») en el horario por día.
	 *
	 * Sólo se usa para prellenar: en cuanto se guarda una vez, manda el horario
	 * elegido y esta lectura deja de aplicarse.
	 */
	private static function from_legacy() {
		$raw = (string) Digitalisimo_Integrations_SEO_Resolver::option( self::LEGACY, '' );
		if ( '' === trim( $raw ) ) return array();
		$codes = array( 'mo' => 'mon', 'tu' => 'tue', 'we' => 'wed', 'th' => 'thu', 'fr' => 'fri', 'sa' => 'sat', 'su' => 'sun' );
		$order = array_keys( self::days() );
		$out   = array();

		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			if ( ! preg_match( '/^\s*([A-Za-z,\-]+)\s+(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})\s*$/', $line, $m ) ) continue;
			$from = self::time( sprintf( '%02d:%s', ...array_map( 'trim', explode( ':', $m[2] ) ) ), '' );
			$to   = self::time( sprintf( '%02d:%s', ...array_map( 'trim', explode( ':', $m[3] ) ) ), '' );
			if ( ! $from || ! $to ) continue;

			foreach ( explode( ',', $m[1] ) as $token ) {
				$parts = array_map( function ( $day ) use ( $codes ) { return $codes[ strtolower( substr( trim( $day ), 0, 2 ) ) ] ?? ''; }, explode( '-', $token ) );
				if ( empty( $parts[0] ) ) continue;
				$start = array_search( $parts[0], $order, true );
				$end   = isset( $parts[1] ) && $parts[1] ? array_search( $parts[1], $order, true ) : $start;
				if ( false === $start || false === $end ) continue;
				// Un rango que cruza el domingo se recorre dando la vuelta.
				for ( $i = $start; ; $i = ( $i + 1 ) % count( $order ) ) {
					$day = $order[ $i ];
					if ( isset( $out[ $day ] ) ) { $out[ $day ]['from2'] = $from; $out[ $day ]['to2'] = $to; }
					else $out[ $day ] = array( 'state' => 'open', 'from' => $from, 'to' => $to );
					if ( $i === $end ) break;
				}
			}
		}
		return $out;
	}

	private static function time( $value, $fallback ) {
		$value = trim( (string) $value );
		return preg_match( '/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $value ) ? $value : $fallback;
	}

	/** Sólo dígitos y separadores habituales; la lada va aparte. */
	public static function sanitize_phone( $value ) {
		$value = trim( (string) $value );
		return trim( preg_replace( '/[^0-9+()\-. ]/', '', $value ) );
	}

	public static function sanitize_code( $value ) {
		$value = trim( (string) $value );
		return array_key_exists( $value, self::country_codes() ) ? $value : '';
	}

	public static function sanitize_schedule( $input ) {
		if ( ! is_array( $input ) ) return array();
		$out = array();
		foreach ( self::days() as $day => $unused ) {
			$row   = is_array( $input[ $day ] ?? null ) ? $input[ $day ] : array();
			$state = in_array( $row['state'] ?? '', array( 'open', 'closed', 'always' ), true ) ? $row['state'] : 'closed';
			$entry = array( 'state' => $state );
			if ( 'open' === $state ) {
				$entry['from'] = self::time( $row['from'] ?? '', '09:00' );
				$entry['to']   = self::time( $row['to'] ?? '', '18:00' );
				$from2         = self::time( $row['from2'] ?? '', '' );
				$to2           = self::time( $row['to2'] ?? '', '' );
				// Un tramo incompleto no se guarda a medias.
				if ( $from2 && $to2 ) { $entry['from2'] = $from2; $entry['to2'] = $to2; }
			}
			$out[ $day ] = $entry;
		}
		return $out;
	}

	/** Teléfono: lada desplegable y número, en el formulario por sitio. */
	public static function phone_field() {
		$option   = Digitalisimo_Integrations_Settings::OPTION;
		$inherit  = is_multisite() && Digitalisimo_Integrations_SEO_Resolver::inherits_network( self::PHONE );
		$disabled = $inherit ? ' disabled' : '';
		self::phone_markup( $option . '[' . self::PHONE_CC . ']', $option . '[' . self::PHONE . ']', Digitalisimo_Integrations_SEO_Resolver::option( self::PHONE_CC, '' ), Digitalisimo_Integrations_SEO_Resolver::option( self::PHONE, '' ), $disabled, 'digitalisimo-inheritable' );
		if ( is_multisite() ) {
			echo '<p><input type="hidden" name="' . esc_attr( $option ) . '[network_inherit][' . esc_attr( self::PHONE ) . ']" value="0"><label><input class="digitalisimo-network-inherit" data-group="#digitalisimo-phone-group" type="checkbox" name="' . esc_attr( $option ) . '[network_inherit][' . esc_attr( self::PHONE ) . ']" value="1" ' . checked( $inherit, true, false ) . '> Heredar de la red</label></p>';
		}
	}

	/** Teléfono en la configuración de red. */
	public static function network_phone_field() {
		$all = (array) get_site_option( Digitalisimo_Integrations_Settings::OPTION, array() );
		self::phone_markup( 'digitalisimo_network[' . self::PHONE_CC . ']', 'digitalisimo_network[' . self::PHONE . ']', (string) ( $all[ self::PHONE_CC ] ?? '' ), (string) ( $all[ self::PHONE ] ?? '' ), '', '' );
	}

	private static function phone_markup( $code_name, $number_name, $code, $number, $disabled, $classes ) {
		echo '<span class="digitalisimo-phone" id="digitalisimo-phone-group">';
		echo '<select class="' . esc_attr( $classes ) . '" name="' . esc_attr( $code_name ) . '"' . $disabled . '>';
		echo '<option value="">' . esc_html__( 'Lada del país', 'digitalisimo-integrations' ) . '</option>';
		foreach ( self::country_codes() as $value => $label ) echo '<option value="' . esc_attr( $value ) . '" ' . selected( (string) $code, (string) $value, false ) . '>' . esc_html( $label ) . '</option>';
		echo '</select> ';
		echo '<input class="regular-text ' . esc_attr( $classes ) . '" type="tel" name="' . esc_attr( $number_name ) . '" value="' . esc_attr( $number ) . '" placeholder="' . esc_attr__( 'Número sin lada', 'digitalisimo-integrations' ) . '"' . $disabled . '>';
		echo '</span>';
		echo '<p class="description">' . esc_html__( 'Escribe sólo el número: la lada se antepone al publicarlo.', 'digitalisimo-integrations' ) . '</p>';
	}

	/** Horario semanal en el formulario por sitio. */
	public static function schedule_field() {
		$option   = Digitalisimo_Integrations_Settings::OPTION;
		$inherit  = is_multisite() && Digitalisimo_Integrations_SEO_Resolver::inherits_network( self::SCHEDULE );
		self::schedule_markup( $option . '[' . self::SCHEDULE . ']', self::schedule(), $inherit ? ' disabled' : '', 'digitalisimo-inheritable' );
		if ( is_multisite() ) {
			echo '<p><input type="hidden" name="' . esc_attr( $option ) . '[network_inherit][' . esc_attr( self::SCHEDULE ) . ']" value="0"><label><input class="digitalisimo-network-inherit" data-group="#digitalisimo-schedule-group" type="checkbox" name="' . esc_attr( $option ) . '[network_inherit][' . esc_attr( self::SCHEDULE ) . ']" value="1" ' . checked( $inherit, true, false ) . '> Heredar de la red</label></p>';
		}
	}

	/** Horario semanal en la configuración de red. */
	public static function network_schedule_field() {
		$all    = (array) get_site_option( Digitalisimo_Integrations_Settings::OPTION, array() );
		$stored = self::sanitize_schedule( is_array( $all[ self::SCHEDULE ] ?? null ) ? $all[ self::SCHEDULE ] : array() );
		self::schedule_markup( 'digitalisimo_network[' . self::SCHEDULE . ']', $stored, '', '' );
	}

	private static function schedule_markup( $name, $schedule, $disabled, $classes ) {
		$times  = self::times();
		$states = array( 'open' => __( 'Abierto', 'digitalisimo-integrations' ), 'closed' => __( 'Cerrado', 'digitalisimo-integrations' ), 'always' => __( 'Abierto 24 horas', 'digitalisimo-integrations' ) );

		echo '<div class="digitalisimo-hours" id="digitalisimo-schedule-group">';
		echo '<p class="digitalisimo-hours__actions">';
		echo '<button type="button" class="button digitalisimo-hours__copy" data-source="mon" data-targets="tue,wed,thu,fri,sat,sun">' . esc_html__( 'Copiar lunes a toda la semana', 'digitalisimo-integrations' ) . '</button> ';
		echo '<button type="button" class="button digitalisimo-hours__copy" data-source="mon" data-targets="tue,wed,thu,fri">' . esc_html__( 'Copiar lunes a lunes–viernes', 'digitalisimo-integrations' ) . '</button> ';
		echo '<button type="button" class="button digitalisimo-hours__copy" data-source="sat" data-targets="sun">' . esc_html__( 'Copiar sábado al fin de semana', 'digitalisimo-integrations' ) . '</button>';
		echo '</p>';

		foreach ( self::days() as $day => $meta ) {
			$row   = $schedule[ $day ] ?? array( 'state' => 'closed', 'from' => '09:00', 'to' => '18:00', 'from2' => '', 'to2' => '' );
			$field = $name . '[' . $day . ']';
			$split = ! empty( $row['from2'] ) && ! empty( $row['to2'] );

			echo '<div class="digitalisimo-hours__day" data-day="' . esc_attr( $day ) . '">';
			echo '<span class="digitalisimo-hours__label">' . esc_html( $meta[0] ) . '</span>';

			echo '<select class="digitalisimo-hours__state ' . esc_attr( $classes ) . '" name="' . esc_attr( $field ) . '[state]"' . $disabled . '>';
			foreach ( $states as $value => $label ) echo '<option value="' . esc_attr( $value ) . '" ' . selected( $row['state'], $value, false ) . '>' . esc_html( $label ) . '</option>';
			echo '</select>';

			echo '<span class="digitalisimo-hours__range">';
			self::time_select( $field . '[from]', $row['from'], $times, $disabled, $classes . ' digitalisimo-hours__from' );
			echo ' <span class="digitalisimo-hours__sep">–</span> ';
			self::time_select( $field . '[to]', $row['to'], $times, $disabled, $classes . ' digitalisimo-hours__to' );
			echo '</span>';

			// Horario partido: se pide sólo cuando el día lo usa.
			echo '<span class="digitalisimo-hours__range digitalisimo-hours__range--second"' . ( $split ? '' : ' hidden' ) . '>';
			self::time_select( $field . '[from2]', $row['from2'], $times, $disabled, $classes . ' digitalisimo-hours__from2', true );
			echo ' <span class="digitalisimo-hours__sep">–</span> ';
			self::time_select( $field . '[to2]', $row['to2'], $times, $disabled, $classes . ' digitalisimo-hours__to2', true );
			echo '</span>';

			echo '<button type="button" class="button-link digitalisimo-hours__split" aria-pressed="' . ( $split ? 'true' : 'false' ) . '">' . esc_html__( 'Horario partido', 'digitalisimo-integrations' ) . '</button>';
			echo '</div>';
		}
		echo '<p class="description">' . esc_html__( 'Los días cerrados no se publican: así es como Google espera declararlos.', 'digitalisimo-integrations' ) . '</p>';
		echo '</div>';
	}

	private static function time_select( $name, $value, $times, $disabled, $classes, $optional = false ) {
		echo '<select class="' . esc_attr( $classes ) . '" name="' . esc_attr( $name ) . '"' . $disabled . '>';
		// El tramo opcional necesita un valor vacío: aunque esté plegado, se envía.
		if ( $optional ) echo '<option value="">—</option>';
		foreach ( $times as $time => $label ) echo '<option value="' . esc_attr( $time ) . '" ' . selected( (string) $value, (string) $time, false ) . '>' . esc_html( $label ) . '</option>';
		echo '</select>';
	}
}
