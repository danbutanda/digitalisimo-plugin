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

	/** Botón y estado (`#digi-migrate`, el mismo que conduce migrate.js). */
	public static function control( $network, $status_html, $running ) {
		self::assets();
		$nonce = $network ? wp_create_nonce( Full::AJAX . '_network' ) : wp_create_nonce( Full::AJAX . '_' . get_current_blog_id() );
		$html  = '<div id="digi-migrate" class="digi-migrate" data-ajax="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" data-nonce="' . esc_attr( $nonce ) . '" data-network="' . ( $network ? '1' : '0' ) . '" data-running="' . ( $running ? '1' : '0' ) . '">';
		$html .= '<p><button type="button" class="button button-primary button-hero digi-migrate__button"' . ( $running ? ' disabled' : '' ) . '>' . esc_html( $network ? 'Migrar toda la red' : 'Migrar' ) . '</button></p>';
		$html .= '<div class="digi-migrate__status">' . $status_html . '</div></div>';
		return $html;
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
		$html .= '<tr><td>' . esc_html( 'Tema: ' . $theme['name'] ) . '</td><td>' . esc_html( $theme['hello'] || $theme['own'] ? 'Tema DIGITALÍSIMO' : '—' ) . '</td><td>' . esc_html( $theme['hello'] ? 'Logo, menús, CSS adicional y ajustes de Hello pasan al tema propio; la cabecera y el pie, a plantillas de DIGITALÍSIMO Elements.' : '—' ) . '</td><td>' . esc_html( self::theme_text( $network, $theme ) ) . '</td></tr>';
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
			return sprintf( 'Se queda %s: el tema DIGITALÍSIMO sólo reproduce a Hello Elementor, así que con otro tema podría cambiar lo que se ve.', $theme['name'] );
		}
		if ( $theme['child'] ) {
			return 'Se queda: es un tema hijo de Hello con CSS o PHP propios que no se trasladan solos.';
		}
		return ( $network ? 'En cada sitio con Hello Elementor sin hijo' : 'Se cambia' ) . ' al tema DIGITALÍSIMO, si el Theme Builder de DIGITALÍSIMO Elements carga (se comprueba después de instalarlo); si la portada no responde igual, se vuelve a Hello.';
	}

	public static function page() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		list( $status, $running ) = self::full_status( false );
		echo '<div class="wrap digi-migrate-wrap"><h1>' . esc_html( 'Migrar a DIGITALÍSIMO' ) . '</h1>';
		echo '<p>' . esc_html( self::intro( false ) ) . '</p>';
		if ( is_multisite() ) {
			echo '</div>';
			return;
		}
		echo self::control( false, $status, $running ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en control() y en Full::render().
		echo self::plan_html( false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en plan_html().
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
		echo self::plan_html( true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en plan_html().
		echo '</div>';
	}
}
