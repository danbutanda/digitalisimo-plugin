<?php
/**
 * Inicio de sesión (`digitalisimo-login`), C6 (docs/pro/c6.md): el widget propio que sustituye al Login de PRO y su mapa
 * `modules/digitalisimo-convert/maps/login.php`.
 * - registro y recursos (hoja propia ≤ 4 KB sin `--e-global-*` ni `url()`, sin script, sin jQuery ni clases de PRO en el
 *   código; guarda de build-packages.sh);
 * - controles: se ejecutan los `register_controls()` de PRO y del propio con los mismos dobles y se comparan uno a uno
 *   (nombre, orden, tipo, valor por defecto, opciones, condiciones, dispositivos, unidades, rangos, selectores,
 *   prefijos de clase, grupos, secciones y pestañas), con y sin registros abiertos en el sitio;
 * - marcado del servidor: mismo destino que PRO (`wp-login.php`, `log`, `pwd`, `rememberme`, `wp-submit`,
 *   `redirect_to`), etiquetas asociadas con `autocomplete`, botón con nombre accesible, enlaces de contraseña perdida y
 *   registro, aviso con la sesión iniciada (y el formulario en el editor);
 * - conversión con `Converter::tree()` de ajustes reales (por defecto, completos, `__globals__`, `__dynamic__`,
 *   dispositivos, CSS personalizado): ningún `pro.unmapped` y cada clave cae en un control propio.
 *
 * Uso: php-wasm-cli tests/elements-pro-login.php. php-wasm sale con 0 aunque haya un error fatal: se juzga por la línea final.
 */
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public $controls = array();
		public $groups   = array();
		public $sections = array();
		public $tabs     = array();
		public $attrs    = array();
		public $id       = 'a1b2c3d';
		public function get_id() { return $this->id; }
		public function get_settings_for_display( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function get_settings( $key = null ) { return $this->get_settings_for_display( $key ); }
		public function start_controls_section( $name, $args ) { $this->sections[ $name ] = $args; }
		public function end_controls_section() {}
		public function start_controls_tabs( $name, $args = array() ) { $this->tabs[] = $name; }
		public function start_controls_tab( $name, $args ) { $this->tabs[] = $name; }
		public function end_controls_tab() {}
		public function end_controls_tabs() {}
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_responsive_control( $name, $args ) { $this->controls[ $name ] = $args + array( 'responsive' => true ); }
		public function add_group_control( $type, $args ) { $this->groups[ $args['name'] ] = array( 'type' => $type ) + $args; }
		public function add_render_attribute( $element, $key = null, $value = null ) {
			if ( is_array( $element ) ) {
				foreach ( $element as $el => $pairs ) {
					foreach ( $pairs as $k => $v ) {
						$this->add_render_attribute( $el, $k, $v );
					}
				}
				return $this;
			}
			foreach ( (array) $value as $v ) {
				$this->attrs[ $element ][ $key ][] = $v;
			}
			return $this;
		}
		public function get_render_attribute_string( $element ) {
			$out = array();
			foreach ( $this->attrs[ $element ] ?? array() as $k => $values ) {
				$out[] = $k . '="' . htmlspecialchars( implode( ' ', array_filter( array_map( 'strval', $values ), 'strlen' ) ), ENT_QUOTES, 'UTF-8' ) . '"';
			}
			return implode( ' ', $out );
		}
		public function print_render_attribute_string( $element ) { echo $this->get_render_attribute_string( $element ); }
	}
	class Controls_Manager {
		const TEXT = 'text'; const TEXTAREA = 'textarea'; const SELECT = 'select'; const SWITCHER = 'switcher'; const CHOOSE = 'choose'; const HEADING = 'heading'; const COLOR = 'color'; const SLIDER = 'slider'; const URL = 'url'; const DIMENSIONS = 'dimensions'; const HOVER_ANIMATION = 'hover_animation'; const RAW_HTML = 'raw_html';
		const TAB_STYLE = 'style'; const TAB_CONTENT = 'content';
	}
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	class Group_Control_Border { public static function get_type() { return 'border'; } }
	class Group_Control_Background { public static function get_type() { return 'background'; } }
	class Plugin {
		public static $instance;
		public $editor;
		public $experiments;
	}
}
namespace Elementor\Core\Kits\Documents\Tabs {
	class Global_Colors { const COLOR_PRIMARY = 'globals/colors?id=primary'; const COLOR_SECONDARY = 'globals/colors?id=secondary'; const COLOR_TEXT = 'globals/colors?id=text'; const COLOR_ACCENT = 'globals/colors?id=accent'; }
	class Global_Typography { const TYPOGRAPHY_PRIMARY = 'globals/typography?id=primary'; const TYPOGRAPHY_SECONDARY = 'globals/typography?id=secondary'; const TYPOGRAPHY_TEXT = 'globals/typography?id=text'; const TYPOGRAPHY_ACCENT = 'globals/typography?id=accent'; }
}
namespace ElementorPro\Base {
	class Base_Widget extends \Elementor\Widget_Base {}
	class Markdown_Utils {}
}
namespace ElementorPro {
	class Plugin { public static function elementor() { return \Elementor\Plugin::$instance; } }
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	$GLOBALS['lg'] = array( 'edit' => false, 'logged' => false, 'register' => true, 'optimized' => false );
	function __( $v ) { return $v; }
	function esc_html__( $v ) { return $v; }
	function esc_attr__( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function is_rtl() { return false; }
	function is_multisite() { return false; }
	function get_site_option( $name ) { return false; }
	function get_option( $name ) { return 'users_can_register' === $name ? ( $GLOBALS['lg']['register'] ? '1' : '0' ) : false; }
	function remove_query_arg( $key ) { return 'https://sitio.test/acceso/?a=1&b=2'; }
	function is_user_logged_in() { return $GLOBALS['lg']['logged']; }
	function wp_get_current_user() { return (object) array( 'display_name' => 'Ana <b>López</b>', 'user_login' => 'ana_secreta', 'user_email' => 'ana@correo.test', 'ID' => 7 ); }
	function wp_logout_url( $to ) { return 'https://sitio.test/wp-login.php?action=logout&redirect_to=' . rawurlencode( $to ) . '&_wpnonce=abc'; }
	function site_url( $path, $scheme = null ) { return 'https://sitio.test/' . $path . ( 'login_post' === $scheme ? '' : '?x' ); }
	function wp_lostpassword_url( $to = '' ) { return 'https://sitio.test/wp-login.php?action=lostpassword&redirect_to=' . rawurlencode( $to ); }
	function wp_registration_url() { return 'https://sitio.test/wp-login.php?action=register'; }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $v ) { return preg_match( '~^(https?://|/|#)~', (string) $v ) ? htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ) : ''; }
	function wp_strip_all_tags( $v ) { return trim( strip_tags( (string) $v ) ); }
	function wp_kses_post( $v ) { return preg_replace( '/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', preg_replace( '@<script[^>]*?>.*?</script>@si', '', (string) $v ) ); }
	function add_action() {}
	function check_lg( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( 'FALLA: ' . $message ); } }

	\Elementor\Plugin::$instance              = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->editor      = new class() { public function is_edit_mode() { return $GLOBALS['lg']['edit']; } };
	\Elementor\Plugin::$instance->experiments = new class() { public function is_feature_active( $f ) { return 'e_optimized_markup' === $f && $GLOBALS['lg']['optimized']; } };

	$root = __DIR__ . '/../digitalisimo-elements/';
	require $root . 'modules/forms/widgets/login.php';
	require $root . 'modules/digitalisimo-forms/class-module.php';
	\Digitalisimo\Elements\Forms\Module::init();
	require $root . 'modules/digitalisimo-convert/class-module.php';
	\Digitalisimo\Elements\Convert\Module::init();
	use Digitalisimo\Elements\Forms\Module as Forms;
	use Digitalisimo\Elements\Forms\Widgets\Login;
	use Digitalisimo\Elements\Convert\Converter;
	use Digitalisimo\Elements\Convert\Registry;
	use Digitalisimo\Elements\Convert\Translator;

	$render = static function ( $widget, array $settings ) {
		$widget->settings = $settings;
		$widget->attrs    = array();
		ob_start();
		( new ReflectionMethod( $widget, 'render' ) )->invoke( $widget );
		return (string) ob_get_clean();
	};
	$guard = '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i';
	$name  = 'digitalisimo-login';

	// 1. Registro y recursos.
	check_lg( Login::class === Forms::load( $name ), 'El módulo de formularios carga el Inicio de sesión.' );
	$widget = new Login();
	check_lg( $name === $widget->get_name() && 'Inicio de sesión' === $widget->get_title() && array( 'digitalisimo' ) === $widget->get_categories() && 'Inicio de sesión' === Forms::TITLES[ $name ], 'Nombre, título y categoría propios.' );
	check_lg( array( $name ) === $widget->get_style_depends() && array() === $widget->get_script_depends() && ! is_file( $root . Forms::WIDGETS[ $name ]['js'] ), 'Su hoja y ningún script.' );
	check_lg( $widget->has_widget_inner_wrapper(), 'Envoltorio interior como PRO sin el marcado optimizado.' );
	$css = (string) file_get_contents( $root . Forms::WIDGETS[ $name ]['css'] );
	check_lg( strlen( $css ) > 0 && strlen( $css ) <= 4096, 'La hoja pesa ' . strlen( $css ) . ' B (tope 4 096).' );
	check_lg( ! str_contains( $css, '--e-global' ) && ! preg_match( '/url\(|#[0-9a-f]{3,6}\b/i', $css ), 'La hoja no usa el kit, url() ni colores propios.' );
	check_lg( str_contains( $css, 'outline:2px solid;outline-offset:2px' ) && str_contains( $css, '.elementor-login .elementor-lost-password,.elementor-login .elementor-remember-me{font-size:.85em}' ), 'Foco visible y el tamaño de los enlaces de PRO.' );
	// Corrección de fidelidad (docs/pro/c6.md): como PRO, sin un alto mínimo en «Recordarme» y los enlaces (crecía 5 px a
	// 375 px) y los campos de texto con el foco del núcleo (`outline:0` y su sombra), que Chrome activa también con el ratón.
	check_lg( ! str_contains( $css, 'min-height' ) && ! preg_match( '/(^|[\s,])input:focus-visible|elementor-field-textual[^{]*:focus-visible|\s:focus-visible/', $css ) && str_contains( $css, '[type=checkbox]:focus-visible' ), 'Sin alto mínimo propio y sin contorno propio en los campos de texto: ' . $css );
	$code = '';
	foreach ( token_get_all( (string) file_get_contents( $root . 'modules/digitalisimo-forms/' . Forms::WIDGETS[ $name ]['file'] ) ) as $token ) {
		$code .= is_array( $token ) ? ( in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ? '' : $token[1] ) : $token;
	}
	// El dominio de PRO sólo en sus textos fijos para lo convertido (corrección de fidelidad, abajo): `esc_*__( '…', 'elementor-pro' )`.
	$code_pro = preg_replace( "/esc_(html|attr)__\\( '[^']+', 'elementor-pro' \\)/", '', $code );
	check_lg( ! preg_match( '/jquery|swiper|wp_enqueue_script|wp_register_script|elementor-pro-frontend|ElementorPro\\\\|\'elementor-pro\'/i', $code_pro ), 'Sin jQuery, scripts ni clases o textos de PRO en el código (salvo los textos fijos de lo convertido).' );
	foreach ( array( 'modules/digitalisimo-forms/widgets/class-login.php', Forms::WIDGETS[ $name ]['css'], 'modules/digitalisimo-convert/maps/login.php' ) as $file ) {
		check_lg( ! preg_match( $guard, (string) file_get_contents( $root . $file ) ), "$file pasa la guarda de build-packages.sh." );
	}

	// 2. Los controles de PRO, uno a uno (con registros abiertos y cerrados).
	foreach ( array( true, false ) as $register ) {
		foreach ( array( false, true ) as $optimized ) {
			$GLOBALS['lg']['register']  = $register;
			$GLOBALS['lg']['optimized'] = $optimized;
			$pro = new \ElementorPro\Modules\Forms\Widgets\Login();
			$own = new Login();
			( new ReflectionMethod( $pro, 'register_controls' ) )->invoke( $pro );
			( new ReflectionMethod( $own, 'register_controls' ) )->invoke( $own );
			$tag = ( $register ? 'con' : 'sin' ) . ' registros' . ( $optimized ? ', marcado optimizado' : '' );
			check_lg( array_keys( $pro->controls ) === array_keys( $own->controls ), "Los mismos controles en el mismo orden ($tag)." );
			check_lg( $register === isset( $own->controls['show_register'] ), "«Registrarse» sólo con registros abiertos ($tag)." );
			foreach ( $pro->controls as $control => $a ) {
				$b = $own->controls[ $control ];
				foreach ( array( 'type', 'default', 'condition', 'conditions', 'responsive', 'size_units', 'range', 'tablet_default', 'mobile_default', 'label_block', 'separator', 'render_type', 'show_label', 'placeholder', 'global', 'prefix_class', 'selectors', 'selectors_dictionary', 'dynamic', 'ai' ) as $key ) {
					$va = $a[ $key ] ?? null;
					$vb = $b[ $key ] ?? null;
					if ( 'default' === $key && 'text' === ( $a['type'] ?? '' ) && is_string( $va ) && '' !== $va ) {
						check_lg( is_string( $vb ) && '' !== $vb && $va !== $vb, "$control: texto por defecto propio, en español ($vb)." );
						continue;
					}
					check_lg( $va === $vb, "$control: «{$key}» distinto de PRO (" . json_encode( $va ) . ' / ' . json_encode( $vb ) . ") ($tag)." );
				}
				check_lg( array_keys( (array) ( $a['options'] ?? array() ) ) === array_keys( (array) ( $b['options'] ?? array() ) ) && ( isset( $a['options'] ) === isset( $b['options'] ) ), "$control: las mismas opciones." );
				foreach ( array( 'label_on', 'label_off' ) as $k ) {
					check_lg( isset( $a[ $k ] ) === isset( $b[ $k ] ), "$control: $k." );
				}
			}
			check_lg( array_keys( $pro->groups ) === array_keys( $own->groups ), "Los mismos grupos en el mismo orden ($tag)." );
			foreach ( $pro->groups as $group => $a ) {
				check_lg( $a === $own->groups[ $group ], "$group: el grupo es idéntico al de PRO (tipo, selector, globales, opciones de campo) ($tag)." );
			}
			check_lg( array_keys( $pro->sections ) === array_keys( $own->sections ) && $pro->tabs === $own->tabs, "Las mismas secciones y pestañas ($tag)." );
			foreach ( $pro->sections as $section => $args ) {
				check_lg( ( $args['tab'] ?? null ) === ( $own->sections[ $section ]['tab'] ?? null ) && ( $args['condition'] ?? null ) === ( $own->sections[ $section ]['condition'] ?? null ), "$section: misma pestaña y condición." );
			}
			$pro_controls = $pro->controls;
			$pro_groups   = $pro->groups;
			$own_controls = $own->controls;
			$own_groups   = $own->groups;
		}
	}
	$GLOBALS['lg']['register']  = true;
	$GLOBALS['lg']['optimized'] = false;
	$GLOBALS['lg']['register']  = true;
	$pro = new \ElementorPro\Modules\Forms\Widgets\Login();
	( new ReflectionMethod( $pro, 'register_controls' ) )->invoke( $pro );
	( new ReflectionMethod( $widget, 'register_controls' ) )->invoke( $widget );
	check_lg( isset( $widget->controls['message_color']['selectors']['{{WRAPPER}} .elementor-widget-container .elementor-login__logged-in-message'] ), 'Sin marcado optimizado, el aviso se estiliza dentro del envoltorio interior (como PRO).' );

	// 3. El mapa: declara cada control y sus valores por defecto son los de PRO.
	$map = require $root . 'modules/digitalisimo-convert/maps/login.php';
	check_lg( 'login' === $map['source'] && $name === $map['target'] && in_array( $map['status'], array( 'draft', 'ready' ), true ) && 'hide' === $map['panel'], 'Mapa hacia el Inicio de sesión, que oculta el widget de PRO.' );
	$declared = array_merge( $map['keep'], array_keys( $map['groups'] ), $map['ui'] );
	foreach ( array_merge( array_keys( $pro->controls ), array_keys( $pro->groups ) ) as $control ) {
		check_lg( in_array( $control, $declared, true ), "login.$control: el mapa no lo declara." );
	}
	foreach ( $map['keep'] as $control ) {
		check_lg( isset( $widget->controls[ $control ] ), "login.$control: se conserva en un control propio." );
	}
	foreach ( $map['groups'] as $from => $to ) {
		check_lg( isset( $widget->groups[ $to ] ) && $from === $to, "login.$from: grupo propio con el mismo prefijo." );
	}
	foreach ( $map['defaults'] as $control => $value ) {
		check_lg( $value === $pro->controls[ $control ]['default'], "login.$control: el valor por defecto del mapa es el de PRO." );
	}
	check_lg( array( '.elementor-widget-login' => '.elementor-widget-digitalisimo-login' ) === $map['classes'], 'Sólo cambia la clase del envoltorio del widget.' );

	// 4. Marcado del servidor.
	$defaults = array();
	foreach ( $widget->controls as $control => $args ) {
		if ( array_key_exists( 'default', $args ) ) {
			$defaults[ $control ] = $args['default'];
		}
	}
	$html = $render( $widget, $defaults );
	check_lg( str_contains( $html, '<form class="elementor-login elementor-form" method="post" action="https://sitio.test/wp-login.php" aria-label="Formulario de acceso">' ), 'El formulario va a wp-login.php (esquema login_post), como PRO: ' . $html );
	check_lg( str_contains( $html, '<input type="hidden" name="redirect_to" value="https://sitio.test/acceso/?a=1&amp;b=2">' ), 'Sin redirección elegida, vuelve a la página actual.' );
	check_lg( str_contains( $html, '<label for="user-a1b2c3d" class="elementor-field-label">Nombre de usuario o correo electrónico</label>' ) && str_contains( $html, '<input size="1" type="text" name="log" id="user-a1b2c3d" autocomplete="username" placeholder="Nombre de usuario o correo electrónico" class="elementor-field elementor-field-textual elementor-size-sm">' ), 'Usuario: etiqueta asociada, autocompletado y las clases del núcleo.' );
	check_lg( str_contains( $html, '<label for="password-a1b2c3d" class="elementor-field-label">Contraseña</label>' ) && str_contains( $html, 'type="password" name="pwd" id="password-a1b2c3d" autocomplete="current-password"' ), 'Contraseña: etiqueta asociada y autocompletado.' );
	check_lg( 2 === substr_count( $html, 'class="elementor-field-type-text elementor-field-group elementor-column elementor-col-100 elementor-field-required"' ), 'Los dos grupos con las clases de PRO.' );
	check_lg( str_contains( $html, '<label for="elementor-login-remember-me-a1b2c3d"><input type="checkbox" id="elementor-login-remember-me-a1b2c3d" name="rememberme" value="forever"> Recordarme</label>' ), 'Recordarme, con el mismo nombre y valor que PRO.' );
	check_lg( str_contains( $html, '<button type="submit" class="elementor-size-sm elementor-button" name="wp-submit">' ) && str_contains( $html, '<span class="elementor-button-text">Acceder</span>' ), 'Botón wp-submit con su texto.' );
	check_lg( str_contains( $html, '<a class="elementor-lost-password" href="https://sitio.test/wp-login.php?action=lostpassword&amp;redirect_to=https%3A%2F%2Fsitio.test%2Facceso%2F%3Fa%3D1%26b%3D2">¿Olvidaste tu contraseña?</a>' ) && str_contains( $html, '<span class="elementor-login-separator" aria-hidden="true"> | </span>' ) && str_contains( $html, '<a class="elementor-register" href="https://sitio.test/wp-login.php?action=register">Registrarse</a>' ), 'Enlaces de contraseña perdida y registro, con el separador oculto a los lectores.' );
	check_lg( ! str_contains( $html, 'required' . '="' ) && ! str_contains( $html, 'ana' ), 'Como PRO, los campos no son obligatorios en el navegador; nada del usuario.' );
	$GLOBALS['lg']['register'] = false;
	$closed = $render( $widget, $defaults );
	check_lg( ! str_contains( $closed, 'elementor-register' ) && ! str_contains( $closed, 'separator' ) && str_contains( $closed, 'elementor-lost-password' ), 'Sin registros abiertos no hay enlace de registro ni separador.' );
	$GLOBALS['lg']['register'] = true;
	$bare = $render( $widget, array( 'show_labels' => '', 'show_lost_password' => '', 'show_register' => '', 'show_remember_me' => '', 'button_text' => '', 'input_size' => 'lg', 'button_size' => 'xl', 'button_hover_animation' => 'grow', 'redirect_after_login' => 'yes', 'redirect_url' => array( 'url' => 'https://sitio.test/panel/' ), 'user_label' => 'U', 'password_label' => 'P', 'user_placeholder' => '', 'password_placeholder' => '' ) );
	check_lg( 2 === substr_count( $bare, 'class="elementor-field-label elementor-screen-only"' ) && str_contains( $bare, 'elementor-size-lg' ), 'Etiquetas ocultas a la vista pero presentes para los lectores; tamaño de los campos.' );
	check_lg( str_contains( $bare, 'class="elementor-size-xl elementor-animation-grow elementor-button"' ) && str_contains( $bare, '<span class="elementor-screen-only">Acceder</span>' ) && ! str_contains( $bare, 'elementor-button-text' ), 'Sin texto, el botón conserva un nombre accesible; tamaño y animación.' );
	check_lg( str_contains( $bare, 'name="redirect_to" value="https://sitio.test/panel/"' ) && ! str_contains( $bare, 'rememberme' ) && ! str_contains( $bare, '<a ' ), 'Redirección elegida, sin Recordarme ni enlaces.' );
	$empty = $render( $widget, array( 'redirect_after_login' => 'yes', 'redirect_url' => array( 'url' => '' ) ) + $defaults );
	check_lg( str_contains( $empty, 'value="https://sitio.test/acceso/?a=1&amp;b=2"' ), 'Una redirección vacía vuelve a la página actual.' );
	$GLOBALS['lg']['logged'] = true;
	$logged = $render( $widget, array( 'redirect_after_logout' => 'yes', 'redirect_logout_url' => array( 'url' => 'https://sitio.test/adios/' ) ) + $defaults );
	check_lg( '<div class="elementor-login elementor-login__logged-in-message">Has iniciado sesión como Ana <b>López</b> (<a href="https://sitio.test/wp-login.php?action=logout&amp;redirect_to=https%3A%2F%2Fsitio.test%2Fadios%2F&amp;_wpnonce=abc" target="_blank" rel="noopener">Cerrar sesión</a>)</div>' === $logged, 'Con la sesión iniciada, el aviso de PRO con el nombre público y el cierre de sesión hacia la URL elegida: ' . $logged );
	check_lg( ! str_contains( $logged, 'ana_secreta' ) && ! str_contains( $logged, 'ana@correo' ) && ! str_contains( $logged, '<form' ), 'Nunca el usuario ni el correo; sin formulario.' );
	check_lg( '' === $render( $widget, array( 'show_logged_in_message' => '' ) + $defaults ), 'Sin aviso, nada.' );
	$GLOBALS['lg']['edit'] = true;
	check_lg( str_contains( $render( $widget, $defaults ), '<form class="elementor-login' ), 'En el editor se ve el formulario aunque haya sesión (como PRO).' );
	$GLOBALS['lg']['edit']   = false;
	$GLOBALS['lg']['logged'] = false;
	$widget->settings = $defaults;
	check_lg( "## Formulario de acceso\n\n- **Nombre de usuario o correo electrónico** (text)\n- **Contraseña** (password)\n- **Recordarme** (checkbox)\n\n[Acceder]" === $widget->render_markdown(), 'Markdown del formulario: ' . $widget->render_markdown() );

	// 4 bis. Lo convertido de PRO (marca `_digitalisimo_pro`): los textos fijos de PRO con su msgid y su dominio.
	$pro_text = array( '_digitalisimo_pro' => 'login' ) + $defaults;
	$converted_html = $render( $widget, $pro_text );
	check_lg( str_contains( $converted_html, 'aria-label="Login form">' ) && str_contains( $converted_html, 'value="forever"> Remember Me</label>' ) && str_contains( $converted_html, '>Lost your password?</a>' ) && str_contains( $converted_html, '>Register</a>' ) && ! preg_match( '/Recordarme|Olvidaste|Registrarse|Formulario de acceso/', $converted_html ), 'Lo convertido dice los textos de PRO: ' . $converted_html );
	$GLOBALS['lg']['logged'] = true;
	check_lg( str_starts_with( $render( $widget, $pro_text ), '<div class="elementor-login elementor-login__logged-in-message">You are Logged in as Ana <b>López</b> (<a ' ) && str_contains( $render( $widget, $pro_text ), '>Logout</a>)</div>' ), 'Lo convertido, con la sesión iniciada: el aviso de PRO.' );
	$GLOBALS['lg']['logged'] = false;
	$widget->settings = $pro_text;
	check_lg( str_starts_with( $widget->render_markdown(), "## Login Form\n" ) && str_contains( $widget->render_markdown(), '- **Remember Me** (checkbox)' ), 'Markdown de lo convertido con los textos de PRO.' );
	$widget->settings = $defaults;
	$code_login = (string) file_get_contents( $root . 'modules/digitalisimo-forms/' . Forms::WIDGETS[ $name ]['file'] );
	foreach ( array( "'You are Logged in as %1\$s (%2\$sLogout%3\$s)', 'elementor-pro'", "'Login form', 'elementor-pro'", "'Remember Me', 'elementor-pro'", "'Lost your password?', 'elementor-pro'", "'Register', 'elementor-pro'", "'Login Form', 'elementor-pro'" ) as $msgid ) {
		check_lg( str_contains( $code_login, $msgid ), 'El msgid y el dominio de PRO: ' . $msgid );
	}

	// 5. Conversión con el mapa.
	Registry::reset();
	$lands = static function ( $key ) use ( $widget ) {
		$base = Translator::base( (string) $key );
		if ( isset( $widget->controls[ $base ] ) || Translator::common( (string) $key ) || in_array( $key, array( '__globals__', '__dynamic__', Translator::MARKER ), true ) ) {
			return true;
		}
		foreach ( array_keys( $widget->groups ) as $group ) {
			if ( 0 === strpos( (string) $key, $group . '_' ) ) {
				return true;
			}
		}
		return false;
	};
	$cases = array(
		'lg-defaults' => array(),
		'lg-full'     => array(
			'show_labels' => '', 'input_size' => 'md', 'button_text' => 'Entrar', 'button_size' => 'lg', 'align' => 'center', 'align_tablet' => 'stretch', 'align_mobile' => 'start',
			'redirect_after_login' => 'yes', 'redirect_url' => array( 'url' => 'https://sitio.test/cuenta/', 'is_external' => '', 'nofollow' => '' ), 'redirect_after_logout' => 'yes', 'redirect_logout_url' => array( 'url' => 'https://sitio.test/' ),
			'show_lost_password' => '', 'show_register' => '', 'show_remember_me' => '', 'show_logged_in_message' => '', 'custom_labels' => 'yes', 'user_label' => 'Correo', 'user_placeholder' => 'tu@correo', 'password_label' => 'Clave', 'password_placeholder' => '••••',
			'row_gap' => array( 'unit' => 'px', 'size' => 20 ), 'links_color' => '#333333', 'links_hover_color' => '#000000', 'label_spacing' => array( 'unit' => 'px', 'size' => 6 ), 'label_color' => '#111111',
			'label_typography_typography' => 'custom', 'label_typography_font_weight' => '600', 'field_text_color' => '#222222', 'field_typography_typography' => 'custom', 'field_typography_font_size' => array( 'unit' => 'px', 'size' => 18 ), 'field_typography_font_size_mobile' => array( 'unit' => 'px', 'size' => 16 ),
			'field_background_color' => '#f5f5f5', 'field_border_color' => '#cccccc', 'field_border_width' => array( 'top' => '0', 'right' => '0', 'bottom' => '2', 'left' => '0', 'unit' => 'px', 'isLinked' => false ), 'field_border_radius' => array( 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'unit' => 'px', 'isLinked' => true ),
			'button_text_color' => '#ffffff', 'button_typography_typography' => 'custom', 'button_typography_text_transform' => 'uppercase', 'button_background_background' => 'gradient', 'button_background_color' => '#ff0000', 'button_background_color_b' => '#0000ff',
			'button_border_border' => 'solid', 'button_border_width' => array( 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'unit' => 'px' ), 'button_border_color' => '#000000', 'button_border_radius' => array( 'top' => '30', 'right' => '30', 'bottom' => '30', 'left' => '30', 'unit' => 'px' ),
			'button_text_padding' => array( 'top' => '12', 'right' => '24', 'bottom' => '12', 'left' => '24', 'unit' => 'px' ), 'button_text_padding_mobile' => array( 'top' => '8', 'right' => '16', 'bottom' => '8', 'left' => '16', 'unit' => 'px' ),
			'button_hover_color' => '#000000', 'button_background_hover_background' => 'classic', 'button_background_hover_color' => '#ffff00', 'button_hover_border_color' => '#ff00ff', 'button_hover_animation' => 'grow', 'button_hover_transition_duration' => array( 'unit' => 'ms', 'size' => 300 ),
			'message_color' => '#555555', 'message_typography_typography' => 'custom', 'message_typography_font_style' => 'italic',
			'custom_css' => "selector .elementor-login .elementor-button{letter-spacing:1px}\n.elementor-widget-login .elementor-field{box-shadow:none}",
			'_css_classes' => 'mi-acceso', '_element_id' => 'acceso', '_margin' => array( 'top' => '10', 'right' => '0', 'bottom' => '10', 'left' => '0', 'unit' => 'px' ),
		),
		'lg-globals'  => array( '__globals__' => array( 'label_color' => 'globals/colors?id=text', 'links_hover_color' => 'globals/colors?id=accent', 'button_background_color' => 'globals/colors?id=primary', 'button_typography_typography' => 'globals/typography?id=accent', 'field_typography_typography' => 'globals/typography?id=text', 'message_color' => 'globals/colors?id=secondary' ) ),
		'lg-dynamic'  => array( 'redirect_after_login' => 'yes', 'custom_labels' => 'yes', '__dynamic__' => array( 'redirect_url' => '[elementor-tag id="d1" name="site-url" settings="%7B%7D"]', 'button_text' => '[elementor-tag id="d2" name="site-title" settings="%7B%7D"]', 'user_label' => '[elementor-tag id="d3" name="shortcode" settings="%7B%7D"]' ) ),
	);
	$tree = array( array( 'id' => 'c1', 'elType' => 'container', 'settings' => array(), 'elements' => array() ) );
	foreach ( $cases as $id => $settings ) {
		$tree[0]['elements'][] = array( 'id' => $id, 'elType' => 'widget', 'widgetType' => 'login', 'settings' => $settings, 'elements' => array() );
	}
	$converted = Converter::tree( $tree, $log, true );
	check_lg( count( $cases ) === count( $log ), 'Converter::tree() convierte cada Login (con los mapas en borrador).' );
	$out = array();
	foreach ( $converted[0]['elements'] as $element ) {
		check_lg( $name === $element['widgetType'] && 'widget' === $element['elType'], $element['id'] . ": pasa a $name con el mismo id." );
		foreach ( $element['settings'] as $key => $value ) {
			check_lg( $lands( $key ), $element['id'] . ": la clave $key no cae en un control propio." );
		}
		foreach ( array( '__globals__', '__dynamic__' ) as $bag ) {
			foreach ( array_keys( $element['settings'][ $bag ] ?? array() ) as $key ) {
				check_lg( $lands( $key ), $element['id'] . ": $bag.$key no cae en un control propio." );
			}
		}
		$out[ $element['id'] ] = $element['settings'];
	}
	$codes = array();
	foreach ( $log as $entry ) {
		foreach ( $entry['codes'] as $code ) {
			$codes[] = $entry['element'] . ':' . $code['code'] . ':' . $code['key'];
		}
	}
	check_lg( array( 'lg-full:pro.css:custom_css' ) === $codes, 'Ningún pro.unmapped, pro.dropped ni aproximación; sólo el CSS convertido: ' . implode( ', ', $codes ) );
	$d = $out['lg-defaults'];
	check_lg( 'Log In' === $d['button_text'] && 'Username or Email Address' === $d['user_label'] && 'Password' === $d['password_placeholder'] && 'login' === $d['_digitalisimo_pro'], 'Un Login sin cambios conserva los textos de PRO.' );
	$painted = $render( $widget, $d + $defaults );
	check_lg( str_contains( $painted, '>Log In</span>' ) && str_contains( $painted, 'placeholder="Username or Email Address"' ), 'Lo convertido se pinta con los textos de PRO.' );
	$f = $out['lg-full'];
	foreach ( $cases['lg-full'] as $key => $value ) {
		if ( 'custom_css' !== $key ) {
			check_lg( array_key_exists( $key, $f ) && $value === $f[ $key ], "lg-full: $key pasa sin cambios." );
		}
	}
	check_lg( "selector .elementor-login .elementor-button{letter-spacing:1px}\n.elementor-widget-digitalisimo-login .elementor-field{box-shadow:none}" === $f['custom_css'], 'CSS personalizado con la clase del envoltorio propio: ' . $f['custom_css'] );
	check_lg( $cases['lg-globals']['__globals__'] === $out['lg-globals']['__globals__'], 'Colores y tipografías globales siguen a su control.' );
	check_lg( array_keys( $cases['lg-dynamic']['__dynamic__'] ) === array_keys( $out['lg-dynamic']['__dynamic__'] ), 'Las etiquetas dinámicas siguen a su control.' );
	check_lg( $converted === Converter::tree( $converted, $again, true ) && array() === $again, 'Lo convertido no se convierte otra vez.' );

	echo 'DIGITALÍSIMO Elements: Inicio de sesión (' . count( $widget->controls ) . ' controles, ' . count( $widget->groups ) . ' grupos, hoja de ' . strlen( $css ) . ' B, sin script) y su mapa desde PRO (' . count( $cases ) . " casos de conversión) validados.\n";
}
