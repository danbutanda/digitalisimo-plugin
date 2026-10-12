<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/**
 * «Perfil ampliado» en el widget Caja de autor: usuario del sitio elegido, nombre de usuario,
 * distintivo, cifras, perfiles sociales y un menú de enlaces.
 *
 * Con un usuario elegido, la caja muestra su nombre, biografía, avatar y archivo de entradas en lugar de
 * los datos escritos a mano, y siguen al día si el usuario los cambia. Las piezas se añaden al HTML del
 * widget en puntos estables: el distintivo y el menú en una cabecera, el nombre de usuario tras el
 * nombre, y cifras y redes antes del botón. Una caja que no usa estas opciones se pinta igual que antes.
 */
final class Author_Box_Extras {
	const STYLE = 'digitalisimo-author-box-extras';

	private static $users = null;

	public static function init() {
		add_action( 'elementor/element/after_section_end', array( __CLASS__, 'after_section_end' ), 10, 2 );
		add_action( 'elementor/frontend/widget/before_render', array( __CLASS__, 'before_render' ) );
		add_filter( 'elementor/widget/render_content', array( __CLASS__, 'render_content' ), 10, 2 );
	}

	/** Usuarios que publican en el sitio actual; sólo hace falta en el editor. */
	private static function users() {
		if ( null !== self::$users ) {
			return self::$users;
		}
		self::$users = array( '' => 'Ninguno: datos escritos a mano' );
		if ( ! is_admin() || ! function_exists( 'get_users' ) ) {
			return self::$users;
		}
		foreach ( get_users( array( 'capability' => array( 'edit_posts' ), 'fields' => array( 'ID', 'display_name' ), 'number' => 200, 'orderby' => 'display_name' ) ) as $user ) {
			self::$users[ (string) $user->ID ] = $user->display_name;
		}
		return self::$users;
	}

	/** Gancho genérico: el específico usa el nombre del widget y no llega a otros nombres sobre la misma clase. */
	public static function after_section_end( $element, $section_id ) {
		if ( 'section_author_info' === $section_id && self::applies( $element ) ) {
			self::register_controls( $element );
		}
	}

	public static function register_controls( $widget ) {
		$c = '\\Elementor\\Controls_Manager';
		$widget->start_controls_section( 'digitalisimo_author_extras', array( 'label' => 'Perfil ampliado' ) );
		$widget->add_control( 'digitalisimo_author_user', array( 'label' => 'Usuario del sitio', 'type' => $c::SELECT2, 'options' => self::users(), 'default' => '', 'condition' => array( 'source' => 'custom' ), 'description' => 'Muestra su nombre, biografía, avatar y entradas en lugar de los datos escritos a mano.' ) );
		$widget->add_control( 'digitalisimo_author_username', array( 'label' => 'Nombre de usuario', 'type' => $c::TEXT, 'description' => 'Se muestra bajo el nombre. Con un usuario elegido y el campo vacío, se usa el suyo.' ) );
		$widget->add_control( 'digitalisimo_author_show_username', array( 'label' => 'Mostrar el nombre de usuario', 'type' => $c::SWITCHER, 'return_value' => 'yes' ) );
		$widget->add_control( 'digitalisimo_author_badge', array( 'label' => 'Distintivo', 'type' => $c::TEXT ) );
		$stat = new \Elementor\Repeater();
		$stat->add_control( 'stat_source', array( 'label' => 'Cifra', 'type' => $c::SELECT, 'default' => 'custom', 'options' => array( 'custom' => 'Escrita a mano', 'posts' => 'Entradas del autor', 'comments' => 'Comentarios aprobados del sitio' ) ) );
		$stat->add_control( 'stat_value', array( 'label' => 'Valor', 'type' => $c::TEXT, 'condition' => array( 'stat_source' => 'custom' ) ) );
		$stat->add_control( 'stat_label', array( 'label' => 'Etiqueta', 'type' => $c::TEXT, 'default' => 'Entradas' ) );
		$widget->add_control( 'digitalisimo_author_stats', array( 'label' => 'Cifras', 'type' => $c::REPEATER, 'fields' => $stat->get_controls(), 'default' => array(), 'title_field' => '{{{ stat_label }}}' ) );
		$social = new \Elementor\Repeater();
		$social->add_control( 'social_icon', array( 'label' => 'Icono', 'type' => $c::ICONS, 'default' => array( 'value' => 'fab fa-linkedin-in', 'library' => 'fa-brands' ) ) );
		$social->add_control( 'social_label', array( 'label' => 'Nombre', 'type' => $c::TEXT, 'default' => 'LinkedIn', 'description' => 'Lo anuncian los lectores de pantalla; sin icono se muestra como texto.' ) );
		$social->add_control( 'social_link', array( 'label' => 'Enlace', 'type' => $c::URL, 'dynamic' => array( 'active' => true ) ) );
		$widget->add_control( 'digitalisimo_author_social', array( 'label' => 'Perfiles sociales', 'type' => $c::REPEATER, 'fields' => $social->get_controls(), 'default' => array(), 'title_field' => '{{{ social_label }}}' ) );
		$menu = new \Elementor\Repeater();
		$menu->add_control( 'menu_label', array( 'label' => 'Texto', 'type' => $c::TEXT, 'default' => 'Enlace' ) );
		$menu->add_control( 'menu_link', array( 'label' => 'Enlace', 'type' => $c::URL, 'dynamic' => array( 'active' => true ) ) );
		$widget->add_control( 'digitalisimo_author_menu', array( 'label' => 'Menú de opciones', 'type' => $c::REPEATER, 'fields' => $menu->get_controls(), 'default' => array(), 'title_field' => '{{{ menu_label }}}', 'description' => 'Se abre con el botón «⋯» de la esquina.' ) );
		$widget->end_controls_section();
	}

	private static function user( $s ) {
		$id = 'custom' === ( $s['source'] ?? 'current' ) ? absint( $s['digitalisimo_author_user'] ?? 0 ) : 0;
		return $id && function_exists( 'get_userdata' ) ? get_userdata( $id ) : false;
	}

	/** Con un usuario elegido, sus datos sustituyen a los escritos a mano antes de pintar la caja. */
	/** Se reconoce por su clase, también cuando se registra con otro nombre sobre la misma clase. */
	private static function applies( $widget ) {
		return $widget instanceof \ElementorPro\Modules\ThemeElements\Widgets\Author_Box || ( is_object( $widget ) && method_exists( $widget, 'get_name' ) && 'author-box' === $widget->get_name() );
	}

	public static function before_render( $element ) {
		if ( ! self::applies( $element ) ) {
			return;
		}
		$s    = (array) $element->get_settings_for_display();
		$user = self::user( $s );
		if ( ! $user ) {
			return;
		}
		$name = trim( $user->first_name . ' ' . $user->last_name );
		$element->set_settings( 'author_name', '' !== $name ? $name : $user->display_name );
		$element->set_settings( 'author_bio', (string) $user->description );
		$element->set_settings( 'author_avatar', array( 'url' => (string) get_avatar_url( $user->ID, array( 'size' => 300 ) ) ) );
		if ( empty( $s['posts_url']['url'] ) ) {
			$element->set_settings( 'posts_url', array( 'url' => (string) get_author_posts_url( $user->ID ) ) );
		}
		if ( method_exists( $element, 'reset_render_state' ) ) {
			$element->reset_render_state();
		}
	}

	private static function link( $link, $class, $inner, $label = '' ) {
		$link = is_array( $link ) ? $link : array();
		$url  = (string) ( $link['url'] ?? '' );
		if ( '' === $url ) {
			return '<span class="' . esc_attr( $class ) . '">' . $inner . '</span>';
		}
		$rel = array_filter( array( ! empty( $link['nofollow'] ) ? 'nofollow' : '', ! empty( $link['is_external'] ) ? 'noopener noreferrer' : '' ) );
		return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '"' . ( ! empty( $link['is_external'] ) ? ' target="_blank"' : '' ) . ( $rel ? ' rel="' . esc_attr( implode( ' ', $rel ) ) . '"' : '' ) . ( '' !== $label ? ' aria-label="' . esc_attr( $label ) . '"' : '' ) . '>' . $inner . '</a>';
	}

	/** Piezas del perfil ampliado: cabecera (distintivo y menú), nombre de usuario y cuerpo (cifras y redes). */
	public static function parts( array $s ) {
		$user  = self::user( $s );
		$parts = array( 'header' => '', 'username' => '', 'body' => '' );
		$badge = trim( wp_strip_all_tags( (string) ( $s['digitalisimo_author_badge'] ?? '' ) ) );
		$menu  = '';
		foreach ( is_array( $s['digitalisimo_author_menu'] ?? null ) ? $s['digitalisimo_author_menu'] : array() as $row ) {
			$label = trim( wp_strip_all_tags( (string) ( $row['menu_label'] ?? '' ) ) );
			if ( '' !== $label ) {
				$menu .= '<li>' . self::link( $row['menu_link'] ?? array(), 'digi-author-box__menu-link', esc_html( $label ) ) . '</li>';
			}
		}
		if ( '' !== $badge || '' !== $menu ) {
			$parts['header'] = '<div class="digi-author-box__header">'
				. ( '' !== $badge ? '<span class="digi-author-box__badge">' . esc_html( $badge ) . '</span>' : '' )
				. ( '' !== $menu ? '<details class="digi-author-box__menu"><summary aria-label="' . esc_attr( 'Más opciones' ) . '"></summary><ul>' . $menu . '</ul></details>' : '' )
				. '</div>';
		}
		if ( 'yes' === ( $s['digitalisimo_author_show_username'] ?? '' ) ) {
			$username = trim( wp_strip_all_tags( (string) ( $s['digitalisimo_author_username'] ?? '' ) ) );
			if ( '' === $username && $user ) {
				$username = (string) $user->user_nicename;
			}
			if ( '' !== $username ) {
				$parts['username'] = '<span class="digi-author-box__username">' . esc_html( $username ) . '</span>';
			}
		}
		$author = $user ? (int) $user->ID : ( 'current' === ( $s['source'] ?? 'current' ) && function_exists( 'get_the_ID' ) ? (int) get_post_field( 'post_author', get_the_ID() ) : 0 );
		$stats  = '';
		foreach ( is_array( $s['digitalisimo_author_stats'] ?? null ) ? $s['digitalisimo_author_stats'] : array() as $row ) {
			$source = (string) ( $row['stat_source'] ?? 'custom' );
			if ( 'posts' === $source ) {
				$value = $author ? (string) count_user_posts( $author ) : '';
			} elseif ( 'comments' === $source ) {
				$value = (string) wp_count_comments()->approved;
			} else {
				$value = trim( wp_strip_all_tags( (string) ( $row['stat_value'] ?? '' ) ) );
			}
			$label = trim( wp_strip_all_tags( (string) ( $row['stat_label'] ?? '' ) ) );
			if ( '' !== $value || '' !== $label ) {
				$stats .= '<li><span class="digi-author-box__stat">' . esc_html( $value ) . '</span> <span class="digi-author-box__stat-label">' . esc_html( $label ) . '</span></li>';
			}
		}
		$social = '';
		foreach ( is_array( $s['digitalisimo_author_social'] ?? null ) ? $s['digitalisimo_author_social'] : array() as $row ) {
			$label = trim( wp_strip_all_tags( (string) ( $row['social_label'] ?? '' ) ) );
			if ( empty( $row['social_link']['url'] ) ) {
				continue;
			}
			$icon = '';
			if ( ! empty( $row['social_icon']['value'] ) ) {
				ob_start();
				\Elementor\Icons_Manager::render_icon( $row['social_icon'], array( 'aria-hidden' => 'true' ) );
				$icon = (string) ob_get_clean();
			}
			if ( '' !== $icon || '' !== $label ) {
				$social .= '<li>' . self::link( $row['social_link'], 'digi-author-box__social-link', '' !== $icon ? $icon : esc_html( $label ), '' !== $icon ? $label : '' ) . '</li>';
			}
		}
		$parts['body'] = ( '' !== $stats ? '<ul class="digi-author-box__stats">' . $stats . '</ul>' : '' ) . ( '' !== $social ? '<ul class="digi-author-box__social">' . $social . '</ul>' : '' );
		return $parts;
	}

	/** Inserta las piezas en el HTML de la caja: cabecera al inicio, nombre de usuario tras el nombre y cuerpo antes del botón. */
	public static function insert( $content, array $parts ) {
		if ( '' !== $parts['header'] ) {
			$content = preg_replace( '#<div class="elementor-author-box">#', '<div class="elementor-author-box digi-author-box--header">' . str_replace( array( '\\', '$' ), array( '\\\\', '\\$' ), $parts['header'] ), $content, 1 );
		}
		if ( '' !== $parts['username'] ) {
			$done    = 0;
			$content = preg_replace_callback( '#<(h[1-6]|div|span|p)\b[^>]*class="elementor-author-box__name"[^>]*>.*?</\1>\s*(?:</a>)?#s', static function ( $m ) use ( $parts ) {
				return $m[0] . $parts['username'];
			}, $content, 1, $done );
			if ( ! $done ) {
				$content = preg_replace( '#<div class="elementor-author-box__text">#', '$0' . str_replace( array( '\\', '$' ), array( '\\\\', '\\$' ), $parts['username'] ), $content, 1 );
			}
		}
		if ( '' !== $parts['body'] ) {
			$done    = 0;
			$body    = str_replace( array( '\\', '$' ), array( '\\\\', '\\$' ), $parts['body'] );
			$content = preg_replace( '#<a\b[^>]*class="[^"]*elementor-author-box__button#', $body . '$0', $content, 1, $done );
			if ( ! $done ) {
				$content = preg_replace( '#</div>(\s*)</div>(\s*)$#', $body . '</div>$1</div>$2', $content, 1 );
			}
		}
		return $content;
	}

	public static function render_content( $content, $widget ) {
		if ( ! self::applies( $widget ) ) {
			return $content;
		}
		$parts = self::parts( (array) $widget->get_settings_for_display() );
		if ( '' === $parts['header'] . $parts['username'] . $parts['body'] ) {
			return $content;
		}
		if ( function_exists( 'wp_enqueue_style' ) ) {
			if ( ! wp_style_is( self::STYLE, 'registered' ) ) {
				wp_register_style( self::STYLE, plugins_url( 'assets/css/author-box-extras.css', DIGITALISIMO_ELEMENTS_FILE ), array(), DIGITALISIMO_ELEMENTS_VERSION );
			}
			wp_enqueue_style( self::STYLE );
		}
		return self::insert( (string) $content, $parts );
	}
}
