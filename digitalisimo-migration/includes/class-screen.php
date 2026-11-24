<?php
namespace Digitalisimo\Migration;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-full.php';

/**
 * Pantalla de «Migrar» para quien sólo instaló DIGITALÍSIMO Migración (desde el catálogo de Tools): Herramientas →
 * Migrar a DIGITALÍSIMO (y Red → Ajustes en una red), con el análisis del mapa de reemplazos, el botón único
 * y el estado de la tarea. Con DIGITALÍSIMO Elements activo, la pantalla de siempre (legacy/class-migration.php) pinta
 * lo mismo más el análisis del contenido.
 */
final class Screen {
	const PAGE = 'digitalisimo-ep-migration';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'network_admin_menu', array( __CLASS__, 'network_menu' ) );
	}

	public static function menu() {
		add_management_page( 'Migrar a DIGITALÍSIMO', 'Migrar a DIGITALÍSIMO', 'activate_plugins', self::PAGE, array( __CLASS__, 'page' ) );
	}

	public static function network_menu() {
		add_submenu_page( 'settings.php', 'Migrar a DIGITALÍSIMO', 'Migrar a DIGITALÍSIMO', 'manage_network_plugins', self::PAGE, array( __CLASS__, 'network_page' ) );
	}

	private static function assets() {
		if ( ! function_exists( 'wp_enqueue_script' ) || ! defined( 'DIGITALISIMO_MIGRATION_FILE' ) ) {
			return;
		}
		wp_enqueue_script( 'digitalisimo-migration-migrate', plugins_url( 'assets/js/migrate.js', DIGITALISIMO_MIGRATION_FILE ), array(), DIGITALISIMO_MIGRATION_VERSION, true );
	}

	/** Qué hace «Migrar», en una frase por superficie. */
	public static function intro( $network ) {
		if ( $network ) {
			return 'Un solo paso para toda la red. Migrar toda la red hace primero un respaldo completo de la red con DIGITALÍSIMO Backups; después instala y activa en toda la red los plugins DIGITALÍSIMO que reemplazan a los de los sitios, migra el contenido de cada sitio, importa sus datos (SEO), cambia al tema DIGITALÍSIMO en los sitios donde es seguro y, al final, si todo salió bien y las portadas responden igual, borra lo reemplazado que ya no usa ningún sitio. Si algo falla, se detiene sin borrar nada. Modifica todos los sitios de la red. Si cierras esta pantalla, sigue sola y se reanuda al volver.';
		}
		if ( is_multisite() ) {
			return 'En una red, la sustitución completa (respaldo, instalar, importar, tema y borrar lo reemplazado) se lanza desde Red → Ajustes → Migrar a DIGITALÍSIMO. Aquí, Migrar convierte el contenido de este sitio a DIGITALÍSIMO Elements y desactiva, sin borrarlos, los plugins que Elements sustituye en este sitio (Elementor, Elementor Pro, PRO Elements, Element Pack con su Addon y, al final, Prime Slider).';
		}
		return 'Un solo paso. Migrar hace primero un respaldo completo del sitio con DIGITALÍSIMO Backups; después instala y activa los plugins DIGITALÍSIMO que reemplazan a los de este sitio, migra el contenido a DIGITALÍSIMO Elements (desactiva Elementor, Elementor Pro, PRO Elements, Element Pack con su Addon y, al final, Prime Slider), importa sus datos (SEO), cambia al tema DIGITALÍSIMO si es seguro y, al final, si todo salió bien y la portada responde igual, borra lo reemplazado. Si algo falla, se detiene sin borrar nada, y el respaldo permite volver atrás. Si cierras esta pantalla, la migración sigue sola y se reanuda al volver.';
	}

	/**
	 * Un sitio de una red sin DIGITALÍSIMO Elements activo: el mismo motivo que da el paso AJAX (Full::ajax()) si se
	 * pulsara Migrar aquí.
	 */
	public static function subsite_waiting() {
		return 'DIGITALÍSIMO Elements no está activo en este sitio: la migración de la red lo instala y lo activa (Red → Ajustes → Migrar a DIGITALÍSIMO). Después aparece aquí el botón Migrar de este sitio.';
	}

	/** Botón y estado (`#digi-migrate`, el mismo que conduce migrate.js). */
	public static function control( $network, $status_html, $running ) {
		self::assets();
		$nonce = $network ? wp_create_nonce( Full::AJAX . '_network' ) : wp_create_nonce( Full::AJAX . '_' . get_current_blog_id() );
		$html  = '<div id="digi-migrate" class="digi-migrate" data-ajax="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" data-nonce="' . esc_attr( $nonce ) . '" data-network="' . ( $network ? '1' : '0' ) . '" data-running="' . ( $running ? '1' : '0' ) . '">';
		$html .= '<p><button type="button" class="button button-primary button-hero digi-migrate__button"' . ( $running ? ' disabled' : '' ) . '>' . esc_html( $network ? 'Migrar toda la red' : 'Migrar' ) . '</button></p>';
		$html .= '<div class="digi-migrate__status">' . $status_html . '</div></div>';
		return $html;
	}

	/** ¿Hay una migración completa terminada y ninguna en marcha? Entonces el análisis se pliega bajo su informe. */
	public static function finished( $network ) {
		$state = Full::load( $network );
		$last  = $state ? $state : Full::last( $network );
		return is_array( $last ) && ! empty( $last['done'] );
	}

	/**
	 * Apertura del desplegable del análisis (lo que hará Migrar, lo que hay en el sitio): abierto mientras no hay informe;
	 * cerrado debajo del informe cuando la migración terminó (migrate.js lo cierra al terminar en la pantalla). Se cierra
	 * con analysis_close().
	 */
	public static function analysis_open( $finished, $network = false ) {
		$before = $network ? 'Análisis de la red: lo que hará Migrar toda la red' : 'Análisis del sitio: lo que hará Migrar';
		$after  = $network ? 'Ver el análisis de la red (lo que haría Migrar toda la red si lo pulsas otra vez)' : 'Ver el análisis del sitio (lo que haría Migrar si lo pulsas otra vez)';
		return '<details class="digi-migrate__before"' . ( $finished ? '' : ' open' ) . ' data-done-label="' . esc_attr( $after ) . '"><summary>' . esc_html( $finished ? $after : $before ) . '</summary>';
	}

	public static function analysis_close() {
		return '</details>';
	}

	/** Estado de la tarea completa (en marcha o la última) para la pantalla. */
	public static function full_status( $network ) {
		$state = Full::load( $network );
		return array( Full::render( $network, $state ? $state : Full::last( $network ) ), $state && empty( $state['done'] ) );
	}

	/**
	 * Análisis previo: lo que hay, lo que lo reemplaza, lo que se importa, lo que se borrará al final y el tema. Lo que
	 * depende de DIGITALÍSIMO Elements (el contenido de Element Pack, Prime Slider y PRO) se calcula después de
	 * instalarlo. Sólo lee.
	 */
	public static function plan_html( $network ) {
		$plan  = Replacements::plan( $network );
		$html  = '<h2>' . esc_html( 'Lo que hará Migrar' ) . '</h2>';
		$old   = Full::wp_too_old( $plan );
		if ( '' !== $old ) {
			$html .= '<div class="notice notice-error inline"><p>' . esc_html( $old ) . '</p></div>';
		}
		$todo  = array();
		foreach ( $plan['needs'] as $slug ) {
			$file = Replacements::own_file( $slug );
			if ( ! Replacements::installed( $file ) ) {
				$todo[] = Replacements::own_label( $slug ) . ' (se instala y se activa)';
			} elseif ( ! Replacements::is_active( $file ) ) {
				$todo[] = Replacements::own_label( $slug ) . ' (se activa)';
			}
		}
		$html .= '<p>' . esc_html( 'Antes de tocar nada: un respaldo completo con DIGITALÍSIMO Backups. Si el respaldo falla, Migrar no sigue.' ) . '</p>';
		if ( $todo ) {
			$html .= '<p>' . esc_html( 'Plugins DIGITALÍSIMO, desde la Release publicada: ' . implode( ', ', $todo ) . '.' ) . '</p>';
		}
		$html .= '<table class="widefat striped" style="max-width:1100px"><thead><tr><th>' . esc_html( 'En este sitio' ) . '</th><th>' . esc_html( 'Lo reemplaza' ) . '</th><th>' . esc_html( 'Antes de retirarlo' ) . '</th><th>' . esc_html( 'Al final' ) . '</th></tr></thead><tbody>';
		$deleted = array();
		foreach ( $plan['rows'] as $row ) {
			$by    = '' !== (string) $row['by'] ? Replacements::own_label( $row['by'] ) : '—';
			$after = '' !== (string) $row['by'] ? ( Replacements::DELETE_TEXT[ $row['delete'] ] ?? '' ) : (string) $row['reason'];
			if ( ! empty( $row['dependents'] ) ) {
				$after = sprintf( 'Se desactiva, pero se queda instalado: lo necesita %s.', implode( ', ', (array) $row['dependents'] ) );
			} elseif ( '' !== (string) $row['by'] && 'never' !== $row['delete'] ) {
				$deleted[] = $row['label'];
			}
			$state = $row['active'] ? 'activo' : 'desactivado';
			$html .= '<tr><td>' . esc_html( $row['label'] . ' (' . $state . ')' ) . '</td><td>' . esc_html( $by ) . '</td><td>' . esc_html( '' !== (string) $row['by'] ? $row['imports'] : '—' ) . '</td><td>' . esc_html( $after ) . '</td></tr>';
		}
		$theme = $plan['theme'];
		if ( $theme['hello'] && ! $theme['child'] ) {
			$deleted[] = 'Hello Elementor';
		}
		// Hello o un hijo suyo pasan al tema propio donde es seguro (un hijo, si su PHP se puede trasladar).
		$moves = (bool) $theme['hello'];
		$carry = $theme['child'] ? 'Logo, menús, CSS adicional y ajustes del hijo y de Hello pasan al tema propio, que sigue cargando las hojas y fuentes que encola el hijo (desde su carpeta); la cabecera y el pie, a plantillas de DIGITALÍSIMO Elements.' : 'Logo, menús, CSS adicional y ajustes de Hello pasan al tema propio; la cabecera y el pie, a plantillas de DIGITALÍSIMO Elements.';
		$html .= '<tr><td>' . esc_html( 'Tema: ' . $theme['name'] ) . '</td><td>' . esc_html( $moves || $theme['own'] ? 'Tema DIGITALÍSIMO' : '—' ) . '</td><td>' . esc_html( $moves ? $carry : '—' ) . '</td><td>' . esc_html( self::theme_text( $network, $theme ) ) . '</td></tr>';
		if ( ! $plan['rows'] && ! $theme['hello'] ) {
			$html .= '<tr><td colspan="4">' . esc_html( 'Ningún plugin de este sitio tiene reemplazo DIGITALÍSIMO.' ) . '</td></tr>';
		}
		$html .= '</tbody></table>';
		if ( $deleted ) {
			$html .= '<p>' . esc_html( 'Se borrará al final, sólo si todo sale bien, la portada responde igual y nada lo necesita: ' . implode( ', ', array_values( array_unique( $deleted ) ) ) . '. Lo que no cumpla esas condiciones se queda desactivado, y el informe dirá por qué. Para volver atrás: restaurar el respaldo.' ) . '</p>';
		}
		if ( $network ) {
			$html .= '<p>' . esc_html( sprintf( 'En la red: se comparan la portada y una página de los primeros %d sitios (los demás se informan); los plugins y Hello se borran para toda la red, y sólo si ningún sitio los usa. En una instalación con varias redes no se borra nada: lo reemplazado queda desactivado.', Full::VERIFY_SITES ) ) . '</p>';
		}
		if ( ! defined( 'DIGITALISIMO_ELEMENTS_FILE' ) && in_array( 'digitalisimo-elements', $plan['needs'], true ) ) {
			$html .= '<p>' . esc_html( 'El análisis del contenido (widgets de Element Pack, Prime Slider y PRO) se calcula después de instalar DIGITALÍSIMO Elements, y el informe final lo incluye.' ) . '</p>';
		}
		return $html;
	}

	/** Qué pasará con el tema del sitio en curso. */
	private static function theme_text( $network, array $theme ) {
		if ( $theme['own'] ) {
			return 'Ya está activo.';
		}
		if ( ! $theme['hello'] ) {
			return sprintf( 'Se queda %s: el tema DIGITALÍSIMO reproduce a Hello Elementor, así que con otro tema cambiaría lo que se ve.', $theme['name'] );
		}
		$where = $network ? 'En cada sitio con Hello Elementor o un hijo suyo, Migrar' : 'Migrar';
		if ( $theme['child'] ) {
			return $where . ' instala o actualiza el tema DIGITALÍSIMO desde la Release y lo activa en la misma pasada. Las hojas y fuentes del hijo las carga el tema propio; el resto de su functions.php (ganchos, shortcodes, funciones) pasa a Código PHP de DIGITALÍSIMO Tools, que Migrar instala, actualiza o activa si hace falta, y queda activo. Después compara la portada, las páginas y las que usan plantillas del hijo: si algo no responde o no se pinta igual, vuelve solo al tema hijo y el informe dice qué página y qué cambió. Con el tema propio verificado, retira el hijo y Hello cuando nada los usa.';
		}
		return $where . ' instala o actualiza el tema DIGITALÍSIMO desde la Release y lo activa si el Theme Builder de DIGITALÍSIMO Elements carga; si la portada no responde o no se pinta igual, vuelve solo a Hello.';
	}

	public static function page() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		list( $status, $running ) = self::full_status( false );
		echo '<div class="wrap digi-migrate-wrap"><h1>' . esc_html( 'Migrar a DIGITALÍSIMO' ) . '</h1>';
		echo '<p>' . esc_html( self::intro( false ) ) . '</p>';
		if ( is_multisite() ) {
			// Sin DIGITALÍSIMO Elements en este sitio no hay botón: la pantalla lo dice (antes sólo quedaba la frase de
			// arriba, que promete un «Migrar» que aquí no aparece) y quien administra la red tiene el enlace a la suya.
			$link = current_user_can( 'manage_network_plugins' ) ? ' <a href="' . esc_url( network_admin_url( 'settings.php?page=' . self::PAGE ) ) . '">' . esc_html( 'Ir a Migrar a DIGITALÍSIMO en la red' ) . '</a>' : '';
			echo '<div class="notice notice-info inline"><p>' . esc_html( self::subsite_waiting() ) . $link . '</p></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $link se escapa al construirlo.
			return;
		}
		echo self::control( false, $status, $running ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en control() y en Full::render().
		echo self::analysis_open( self::finished( false ) ) . self::plan_html( false ) . self::analysis_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en analysis_open() y plan_html().
		echo '</div>';
	}

	public static function network_page() {
		if ( ! current_user_can( 'manage_network_plugins' ) ) {
			return;
		}
		list( $status, $running ) = self::full_status( true );
		echo '<div class="wrap digi-migrate-wrap"><h1>' . esc_html( 'Migrar a DIGITALÍSIMO en la red' ) . '</h1>';
		echo '<p>' . esc_html( self::intro( true ) ) . '</p>';
		echo self::control( true, $status, $running ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en control() y en Full::render().
		echo self::analysis_open( self::finished( true ), true ) . self::plan_html( true ) . self::analysis_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en analysis_open() y plan_html().
		echo '</div>';
	}
}
