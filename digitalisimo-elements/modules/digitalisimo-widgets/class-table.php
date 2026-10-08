<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Tabla de datos estáticos con encabezados reales y sin dependencias de navegador. */
final class Table_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-table'; }
	public function get_title() { return 'Tabla de datos'; }
	public function get_icon() { return 'eicon-table'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'tabla', 'csv', 'datos', 'comparar' ); }
	public function get_style_depends() { return array( 'digitalisimo-table' ); }
	public function get_script_depends() { return array(); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_data', array( 'label' => 'Datos' ) );
		$this->add_control( 'caption', array( 'label' => 'Título de la tabla', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'content', array( 'label' => 'Filas CSV', 'type' => $c::TEXTAREA, 'rows' => 12, 'default' => "Nombre,Valor\nEjemplo,1", 'description' => 'La primera fila contiene los encabezados. Usa comillas para celdas con comas.' ) );
		$this->add_control( 'delimiter', array( 'label' => 'Separador', 'type' => $c::SELECT, 'default' => 'comma', 'options' => array( 'comma' => 'Coma', 'semicolon' => 'Punto y coma', 'tab' => 'Tabulación' ) ) );
		$this->add_control( 'first_column_header', array( 'label' => 'Primera columna como encabezado de fila', 'type' => $c::SWITCHER, 'default' => '', 'return_value' => 'yes' ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_control( 'header_background', array( 'label' => 'Fondo de encabezado', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-table thead' => 'background-color:{{VALUE}};' ) ) );
		$this->add_control( 'border_color', array( 'label' => 'Color de bordes', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-table th, {{WRAPPER}} .digi-table td' => 'border-color:{{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	private static function rows( $csv, $delimiter ) {
		$csv = substr( (string) $csv, 0, 20000 );
		$separator = array( 'comma' => ',', 'semicolon' => ';', 'tab' => "\t" );
		$separator = $separator[ $delimiter ] ?? ',';
		$stream = fopen( 'php://temp', 'w+' );
		if ( ! $stream ) { return array(); }
		fwrite( $stream, $csv );
		rewind( $stream );
		$rows = array();
		while ( count( $rows ) < 101 && false !== ( $cells = fgetcsv( $stream, 0, $separator, '"', '' ) ) ) {
			if ( ! is_array( $cells ) ) { continue; }
			$cells = array_slice( $cells, 0, 20 );
			$cells = array_map( static function ( $cell ) { return trim( wp_strip_all_tags( (string) $cell ) ); }, $cells );
			if ( count( $cells ) === 1 && '' === $cells[0] ) { continue; }
			$rows[] = $cells;
		}
		fclose( $stream );
		return $rows;
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$rows = self::rows( $s['content'] ?? '', $s['delimiter'] ?? 'comma' );
		if ( count( $rows ) < 2 ) { return; }
		$headers = array_shift( $rows );
		if ( ! array_filter( $headers, 'strlen' ) ) { return; }
		$caption = trim( wp_strip_all_tags( (string) ( $s['caption'] ?? '' ) ) );
		$first_header = 'yes' === ( $s['first_column_header'] ?? '' );
		echo '<div class="digi-table-wrap" role="region" aria-label="' . esc_attr( '' !== $caption ? $caption : 'Tabla de datos' ) . '" tabindex="0"><table class="digi-table">';
		if ( '' !== $caption ) { echo '<caption>' . esc_html( $caption ) . '</caption>'; }
		echo '<thead><tr>';
		foreach ( $headers as $header ) { echo '<th scope="col">' . esc_html( $header ) . '</th>'; }
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr>';
			foreach ( $headers as $column => $_header ) {
				$cell = (string) ( $row[ $column ] ?? '' );
				if ( $first_header && 0 === $column ) { echo '<th scope="row">' . esc_html( $cell ) . '</th>'; }
				else { echo '<td>' . esc_html( $cell ) . '</td>'; }
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	protected function content_template() {
		?>
		<# var source = (settings.content || '').slice(0, 20000); var separator = settings.delimiter === 'semicolon' ? ';' : (settings.delimiter === 'tab' ? '\t' : ','); var rows = [], row = [], cell = '', quoted = false;
		for (var i = 0; i < source.length; i++) { var ch = source[i]; if (ch === '"') { if (quoted && source[i + 1] === '"') { cell += '"'; i++; } else { quoted = !quoted; } } else if (ch === separator && !quoted) { row.push(cell.trim()); cell = ''; } else if ((ch === '\n' || ch === '\r') && !quoted) { if (ch === '\r' && source[i + 1] === '\n') i++; row.push(cell.trim()); cell = ''; if (row.some(Boolean)) rows.push(row); row = []; } else { cell += ch; } }
		row.push(cell.trim()); if (row.some(Boolean)) rows.push(row); rows = rows.slice(0, 101); var headers = rows.shift() || []; #>
		<# if (headers.length && rows.length) { #><div class="digi-table-wrap" role="region" tabindex="0"><table class="digi-table"><# if (settings.caption) { #><caption>{{ settings.caption }}</caption><# } #><thead><tr><# _.each(headers, function(header) { #><th scope="col">{{ header }}</th><# }); #></tr></thead><tbody><# _.each(rows, function(row) { #><tr><# _.each(headers, function(header, col) { var value = row[col] || ''; if (0 === col && settings.first_column_header === 'yes') { #><th scope="row">{{ value }}</th><# } else { #><td>{{ value }}</td><# } }); #></tr><# }); #></tbody></table></div><# } #>
		<?php
	}
}
