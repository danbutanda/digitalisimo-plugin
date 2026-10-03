<?php
defined( 'ABSPATH' ) || exit;

/**
 * SEO → Auditoría: un solo lugar para todas las auditorías, pruebas y
 * análisis del plugin. Las que analizan el HTML publicado se ejecutan aquí
 * mismo (Schema, SEO Front, llms.txt); las de Rendimiento y calidad, que miden
 * la página en el navegador, se abren en su consola.
 */
class Digitalisimo_Integrations_SEO_Audit_Hub {
	const PAGE = 'digitalisimo-seo-audit';

	public static function url( $anchor = '' ) {
		return admin_url( 'admin.php?page=' . self::PAGE ) . ( $anchor ? '#' . $anchor : '' );
	}

	/** Pruebas de Rendimiento y calidad que se abren en su consola. */
	private static function performance_audits() {
		return array(
			'audit'        => array( 'Auditoría frontend', 'Enlaces, encabezados, nombres accesibles, contraste, objetivos táctiles, imágenes y CSS de una URL, en escritorio y móvil.' ),
			'a11y'         => array( 'Accesibilidad', 'Nombres accesibles, contraste y objetivos táctiles de la última auditoría.' ),
			'seo'          => array( 'SEO técnico', 'Enlaces, destinos y jerarquía de encabezados.' ),
			'images'       => array( 'Imágenes y ALT', 'Tamaños, srcset, sizes y textos alternativos.' ),
			'css'          => array( 'CSS', 'Hojas de estilo por página y cuáles se pueden diferir.' ),
			'javascript'   => array( 'JavaScript', 'Dependencias de jQuery y carga diferida.' ),
			'fonts'        => array( 'Fuentes', 'Familias, pesos y variantes que carga cada sitio.' ),
			'status'       => array( 'Diagnóstico de assets', 'Recursos que carga la página y su origen.' ),
			'measurements' => array( 'PageSpeed manual', 'Mediciones externas registradas.' ),
			'debug'        => array( 'Debug', 'Estado de fuentes, precargas, CSS, tracking y caché.' ),
		);
	}

	public static function render() {
		$sections = array( 'schema' => 'Schema', 'front' => 'SEO Front', 'llms' => 'llms.txt', 'quality' => 'Rendimiento y calidad', 'files' => 'Archivos públicos' );
		if ( class_exists( 'Digitalisimo_AI' ) ) $sections['ai'] = 'SEO AI';
		echo '<h2>Auditoría</h2><p>Todas las auditorías, pruebas y análisis del plugin. Sólo informan: ninguna modifica contenido ni configuración.</p><p>';
		$links = array();
		foreach ( $sections as $id => $label ) $links[] = '<a href="#digitalisimo-audit-' . esc_attr( $id ) . '">' . esc_html( $label ) . '</a>';
		echo implode( ' · ', $links ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- enlaces escapados arriba.

		echo '<hr id="digitalisimo-audit-schema">';
		Digitalisimo_Integrations_Schema_Audit::render();

		echo '<hr id="digitalisimo-audit-front">';
		Digitalisimo_Integrations_SEO_Front_Inspector::render();

		echo '<hr id="digitalisimo-audit-llms">';
		if ( Digitalisimo_Integrations_LLMS::status()['enabled'] ) Digitalisimo_Integrations_LLMS::render_status( false );
		else echo '<h2>Estado de llms.txt</h2><p>' . Digitalisimo_Integrations_Quality_Audit::badge( 'NO APLICABLE', 'SIN ACTIVAR' ) . ' El sitio no publica /llms.txt. ' . ( class_exists( 'Digitalisimo_AI' ) ? 'Se activa en <a href="' . esc_url( Digitalisimo_Integrations_LLMS::admin_url() ) . '">SEO AI → llms.txt</a>.' : 'Se configura en SEO AI, que requiere el módulo AI y Chatbot.' ) . '</p>';

		echo '<hr id="digitalisimo-audit-quality"><h2>Rendimiento y calidad</h2><p>Estas pruebas abren la página en el navegador para medirla como la ve un visitante; se ejecutan en la consola de Rendimiento.</p><table class="widefat striped" style="max-width:900px"><tbody>';
		$record = Digitalisimo_Integrations_Quality_Audit::current_record( false );
		foreach ( self::performance_audits() as $section => $item ) {
			$detail = $item[1];
			if ( 'audit' === $section && $record ) $detail .= ' Última: ' . wp_parse_url( $record['url'], PHP_URL_PATH ) . ' · ' . date_i18n( 'Y-m-d H:i', (int) $record['time'] ) . '.';
			echo '<tr><th><a href="' . esc_url( admin_url( 'admin.php?page=digitalisimo-performance&section=' . $section ) ) . '">' . esc_html( $item[0] ) . '</a></th><td>' . esc_html( $detail ) . '</td></tr>';
		}
		echo '</tbody></table>';

		echo '<hr id="digitalisimo-audit-files"><h2>Archivos públicos</h2><p>Lo que leen buscadores y agentes en este sitio.</p><p>';
		foreach ( array( '/sitemap.xml' => 'Ver sitemap XML', '/robots.txt' => 'Ver robots.txt', '/llms.txt' => 'Ver llms.txt' ) as $path => $label ) echo '<a class="button button-secondary" href="' . esc_url( home_url( $path ) ) . '" target="_blank" rel="noopener">' . esc_html( $label ) . '</a> ';
		echo '</p>';

		if ( isset( $sections['ai'] ) ) echo '<hr id="digitalisimo-audit-ai"><h2>SEO AI</h2><p>Accesibilidad de las URL para buscadores y agentes de IA: HTTP, robots, noindex, canonical y entidades. <a class="button" href="' . esc_url( admin_url( 'admin.php?page=digitalisimo-seo-ai-audit' ) ) . '">Abrir auditoría SEO AI</a></p>';
	}

	/** En la red: cada auditoría analiza un sitio, así que se abre desde él. */
	public static function render_network() {
		echo '<tr><td colspan="2"><p>Las auditorías analizan el HTML publicado de cada sitio. Ábrelas desde el sitio que quieras revisar:</p><ul style="list-style:disc;padding-left:20px">';
		foreach ( get_sites( array( 'number' => 200, 'orderby' => 'domain' ) ) as $site ) echo '<li><a href="' . esc_url( get_admin_url( (int) $site->blog_id, 'admin.php?page=' . self::PAGE ) ) . '">' . esc_html( get_home_url( (int) $site->blog_id, '/' ) ) . '</a></li>';
		echo '</ul></td></tr>';
	}
}
