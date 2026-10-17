<?php
/**
 * Identidad DIGITALÍSIMO del constructor cuando corre el núcleo incluido.
 *
 * El núcleo se distribuye sin cambios (se sincroniza con sus sumas oficiales para recibir sus correcciones de
 * seguridad), así que la marca se aplica en tiempo de ejecución, sólo en lo que ve el usuario: los textos de
 * PHP y de JavaScript (filtros de traducción, en cualquier idioma), los logotipos (hoja propia), el menú, la
 * pantalla de inicio y la meta generator. Los identificadores internos (clases, ganchos, metadatos, rutas,
 * tipos de contenido) no cambian: de ellos dependen el contenido existente, los kits, las plantillas y los
 * complementos de terceros. Si el plugin Elementor está activo, manda su propia marca.
 */
defined( 'ABSPATH' ) || exit;

final class Digitalisimo_Elements_Identity {
	const NAME   = 'DIGITALÍSIMO Elements';
	const STYLE  = 'digitalisimo-elements-identity';
	/** Paleta propia (assets/digitalisimo/palette*.css): administración y editor, tema oscuro y lienzo. */
	const PALETTE         = 'digitalisimo-elements-palette';
	const PALETTE_DARK    = 'digitalisimo-elements-palette-dark';
	const PALETTE_PREVIEW = 'digitalisimo-elements-palette-preview';
	const DOMAINS = array( 'elementor', 'elementor-pro' );

	/** Patrones (sin delimitadores, compartidos con JavaScript) y su sustitución, en orden. */
	const RULES = array(
		array( '\\bElementor Pro\\b', self::NAME ),
		array( '\\bElementor One\\b', self::NAME ),
		// La IA del editor es un servicio externo con su propia cuenta: se nombra sin marca.
		array( '\\bla IA de Elementor\\b', 'la IA' ),
		array( '\\bIA de Elementor\\b', 'IA' ),
		array( '\\bElementor AI\\b', 'AI' ),
		array( '\\bElementor([\'’])s\\b', self::NAME . '$1' ),
		array( '\\bElementor\\b', self::NAME ),
	);

	public static function init() {
		if ( ! class_exists( 'Digitalisimo_Elements_Core' ) || ! Digitalisimo_Elements_Core::bundled() ) {
			return;
		}
		add_filter( 'gettext', array( __CLASS__, 'gettext' ), 20, 3 );
		add_filter( 'gettext_with_context', array( __CLASS__, 'gettext_with_context' ), 20, 4 );
		add_filter( 'ngettext', array( __CLASS__, 'ngettext' ), 20, 5 );
		add_filter( 'ngettext_with_context', array( __CLASS__, 'ngettext_with_context' ), 20, 6 );
		if ( did_action( 'wp_default_scripts' ) && function_exists( 'wp_scripts' ) ) {
			self::script_filters( wp_scripts() );
		} else {
			add_action( 'wp_default_scripts', array( __CLASS__, 'script_filters' ), 20 );
		}
		foreach ( array( 'admin_enqueue_scripts', 'elementor/editor/after_enqueue_styles', 'elementor/app/init' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'enqueue_style' ) );
		}
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_front_style' ) );
		// La paleta, después de las hojas del núcleo (en la administración se encolan con prioridad 10).
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_palette' ), 100 );
		add_action( 'elementor/editor/after_enqueue_styles', array( __CLASS__, 'enqueue_editor_palette' ), 100 );
		add_action( 'elementor/app/init', array( __CLASS__, 'enqueue_app_palette' ) );
		add_action( 'elementor/preview/enqueue_styles', array( __CLASS__, 'enqueue_preview_palette' ), 100 );
		add_filter( 'pre_option_elementor_meta_generator_tag', array( __CLASS__, 'no_generator' ) );
		add_filter( 'elementor/core/admin/notifications', '__return_empty_array', 99 );
		add_filter( 'elementor/announcements/raw_announcements', '__return_empty_array', 99 );
		add_action( 'admin_init', array( __CLASS__, 'home_screen' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 9999 );
		add_filter( 'admin_footer_text', array( __CLASS__, 'footer_text' ), 9999 );
		add_filter( 'elementor_one/upgrade_available', '__return_false' );
		add_filter( 'elementor/admin/dashboard_overview_widget/footer_actions', '__return_empty_array', 99 );
		add_filter( 'elementor/core/admin/homescreen', array( __CLASS__, 'home_data' ), 99 );
		// Noticias del blog del proveedor en el panel del escritorio: no se muestran.
		add_filter( 'pre_option_elementor_remote_info_feed_data', '__return_empty_array' );
		add_action( 'admin_print_footer_scripts', array( __CLASS__, 'mcp_name' ), 9 );
		add_action( 'admin_init', array( __CLASS__, 'system_info' ) );
		// El asistente de bienvenida del núcleo vende sus planes y su tema: se da por completado.
		add_filter( 'pre_option_elementor_onboarded', array( __CLASS__, 'onboarded' ) );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'default_title' ), 10, 1 );
		add_action( 'wp_before_admin_bar_render', array( __CLASS__, 'admin_bar' ) );
		add_action( 'admin_print_footer_scripts-edit.php', array( __CLASS__, 'new_post_dropdown' ), 20 );
	}

	/** Texto con la marca propia; lo que no la menciona sale tal cual. */
	public static function brand( $text ) {
		if ( ! is_string( $text ) || false === strpos( $text, 'lementor' ) ) {
			return $text;
		}
		foreach ( self::RULES as $rule ) {
			$text = (string) preg_replace( '/' . str_replace( '/', '\\/', $rule[0] ) . '/u', $rule[1], $text );
		}
		return $text;
	}

	public static function gettext( $translation, $text, $domain ) {
		return in_array( $domain, self::DOMAINS, true ) && ! self::external_header( $text ) ? self::brand( $translation ) : $translation;
	}

	/**
	 * Cabeceras del plugin Elementor (o Elementor Pro) original que siga instalado junto a DIGITALÍSIMO Elements. WordPress
	 * las traduce con el dominio del plugin en _get_plugin_data_markup_translate() (lista de plugins, Escritorio →
	 * Actualizaciones, confirmación de borrado, correo de actualización automática, pantallas de actualización) y la marca
	 * no debe tocarlas: ese plugin es lo que es. Sólo cuenta si el texto es exactamente una de esas cabeceras (se leen una
	 * vez por petición) y sólo mientras se traduce desde esa función para uno de esos plugins.
	 */
	public static function external_header( $text ) {
		static $headers = null;
		if ( null === $headers ) {
			$headers = array();
			if ( defined( 'Digitalisimo_Elements_Core::EXTERNAL_PLUGINS' ) && defined( 'WP_PLUGIN_DIR' ) && function_exists( 'get_file_data' ) ) {
				foreach ( Digitalisimo_Elements_Core::EXTERNAL_PLUGINS as $file ) {
					$path = WP_PLUGIN_DIR . '/' . $file;
					if ( is_readable( $path ) ) {
						foreach ( get_file_data( $path, array( 'Plugin Name', 'Description', 'Author', 'Plugin URI', 'Author URI' ), 'plugin' ) as $value ) {
							if ( '' !== $value ) {
								$headers[ $value ] = true;
							}
						}
					}
				}
			}
		}
		if ( ! isset( $headers[ $text ] ) ) {
			return false;
		}
		foreach ( debug_backtrace( 0, 10 ) as $frame ) { // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- distingue la traducción de cabeceras de cualquier otro texto igual.
			if ( '_get_plugin_data_markup_translate' !== (string) ( $frame['function'] ?? '' ) ) {
				continue;
			}
			$file = str_replace( '\\', '/', (string) ( $frame['args'][0] ?? '' ) );
			foreach ( Digitalisimo_Elements_Core::EXTERNAL_PLUGINS as $external ) {
				if ( $file === $external || substr( $file, -strlen( '/' . $external ) ) === '/' . $external ) {
					return true;
				}
			}
			return false;
		}
		return false;
	}

	public static function gettext_with_context( $translation, $text, $context, $domain ) {
		return in_array( $domain, self::DOMAINS, true ) ? self::brand( $translation ) : $translation;
	}

	public static function ngettext( $translation, $single, $plural, $number, $domain ) {
		return in_array( $domain, self::DOMAINS, true ) ? self::brand( $translation ) : $translation;
	}

	public static function ngettext_with_context( $translation, $single, $plural, $number, $context, $domain ) {
		return in_array( $domain, self::DOMAINS, true ) ? self::brand( $translation ) : $translation;
	}

	/**
	 * Los scripts traducen con wp.i18n, que aplica los filtros `i18n.*` de wp.hooks a cada texto, con o sin
	 * archivo de traducción: el mismo cambio de marca llega a todas sus pantallas y a cualquier idioma.
	 */
	public static function script_filters( $scripts ) {
		$js = '(function(h){if(!h||!h.addFilter){return;}' . self::js_brand() . '["elementor","elementor-pro"].forEach(function(d){["i18n.gettext_","i18n.gettext_with_context_","i18n.ngettext_","i18n.ngettext_with_context_"].forEach(function(f){h.addFilter(f+d,"digitalisimo/identity",b);});});'
			. '})(window.wp&&window.wp.hooks);';
		$scripts->add_inline_script( 'wp-i18n', $js, 'after' );
	}

	/** `function b(texto)` en JavaScript con las mismas reglas que brand(). */
	private static function js_brand() {
		return 'var r=' . wp_json_encode( self::RULES, JSON_UNESCAPED_UNICODE ) . '.map(function(x){return[new RegExp(x[0],"gu"),x[1]];});'
			. 'function b(t){if(typeof t!=="string"||t.indexOf("lementor")<0){return t;}for(var i=0;i<r.length;i++){t=t.replace(r[i][0],r[i][1]);}return t;}';
	}

	public static function no_generator() {
		return '1';
	}

	public static function enqueue_style() {
		if ( ! wp_style_is( self::STYLE, 'enqueued' ) ) {
			wp_enqueue_style( self::STYLE, plugins_url( 'assets/digitalisimo/identity.css', DIGITALISIMO_ELEMENTS_FILE ), array(), DIGITALISIMO_ELEMENTS_VERSION );
		}
	}

	/**
	 * Paleta propia en la administración: los colores del núcleo (variables, reglas fijas y componentes MUI) pasan a
	 * los tokens de digitalisimo-ui.css, que se carga como dependencia para que los tokens sean los de los plugins.
	 *
	 * `admin_enqueue_scripts` pasa el sufijo de la pantalla: no se toma como dependencia.
	 */
	public static function enqueue_palette() {
		self::add_palette( array() );
	}

	/** @param string[] $deps Hojas registradas que deben imprimirse antes. */
	private static function add_palette( array $deps ) {
		if ( ! wp_style_is( self::PALETTE, 'enqueued' ) ) {
			$shared = function_exists( 'digitalisimo_ui_dependency' ) ? digitalisimo_ui_dependency() : array();
			wp_enqueue_style( self::PALETTE, plugins_url( 'assets/digitalisimo/palette.css', DIGITALISIMO_ELEMENTS_FILE ), array_merge( $shared, $deps ), DIGITALISIMO_ELEMENTS_VERSION );
		}
	}

	/**
	 * En el editor, además, el tema oscuro: palette-dark.css lleva el mismo atributo media que la hoja oscura del
	 * núcleo (auto, claro u oscuro según la preferencia) y un script lo copia cuando la preferencia cambia en vivo.
	 */
	public static function enqueue_editor_palette() {
		self::add_palette( wp_style_is( 'elementor-editor', 'registered' ) ? array( 'elementor-editor' ) : array() );
		$styles = wp_styles();
		$media  = isset( $styles->registered['e-theme-ui-dark'] ) && is_string( $styles->registered['e-theme-ui-dark']->args ) && '' !== $styles->registered['e-theme-ui-dark']->args ? $styles->registered['e-theme-ui-dark']->args : 'none';
		wp_enqueue_style( self::PALETTE_DARK, plugins_url( 'assets/digitalisimo/palette-dark.css', DIGITALISIMO_ELEMENTS_FILE ), array( self::PALETTE ), DIGITALISIMO_ELEMENTS_VERSION, $media );
		wp_register_script( self::PALETTE_DARK, false, array(), DIGITALISIMO_ELEMENTS_VERSION, true );
		wp_add_inline_script( self::PALETTE_DARK, self::palette_dark_sync() );
		wp_enqueue_script( self::PALETTE_DARK );
	}

	/** Copia en palette-dark.css el atributo media que el editor cambia en #e-theme-ui-dark-css. */
	public static function palette_dark_sync() {
		return '(function(){var s=document.getElementById("e-theme-ui-dark-css"),d=document.getElementById(' . wp_json_encode( self::PALETTE_DARK . '-css' ) . ');'
			. 'if(!s||!d){return;}d.media=s.media||"none";if(window.MutationObserver){new MutationObserver(function(){d.media=s.media||"none";}).observe(s,{attributes:true,attributeFilter:["media"]});}})();';
	}

	/** En la App la acción corre antes de que se encolen sus hojas: la dependencia la imprime después de app.css. */
	public static function enqueue_app_palette() {
		self::add_palette( array( 'elementor-app' ) );
	}

	/** El lienzo de vista previa: sólo la interfaz del editor, sin la hoja compartida (es la página real). */
	public static function enqueue_preview_palette() {
		wp_enqueue_style( self::PALETTE_PREVIEW, plugins_url( 'assets/digitalisimo/palette-preview.css', DIGITALISIMO_ELEMENTS_FILE ), wp_style_is( 'editor-preview', 'registered' ) ? array( 'editor-preview' ) : array(), DIGITALISIMO_ELEMENTS_VERSION );
	}

	/** En la página sólo hace falta para la barra de administración («Editar con…»). */
	public static function enqueue_front_style() {
		if ( is_admin_bar_showing() ) {
			self::enqueue_style();
		}
	}

	/**
	 * La pantalla «Home» sólo muestra contenido promocional remoto: se retira del menú y su dirección lleva al
	 * inicio rápido del editor (`page=elementor`), con los accesos a ajustes del sitio, logotipo, colores y fuentes.
	 */
	public static function home_screen() {
		global $pagenow;
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo decide a qué pantalla ir.
		if ( 'admin.php' === $pagenow && 'elementor-home' === $page ) {
			wp_safe_redirect( add_query_arg( 'page', 'elementor', admin_url( 'admin.php' ) ) );
			exit;
		}
	}

	public static function menu() {
		// El paquete de la administración registra «Home» con la dirección completa como identificador.
		remove_submenu_page( 'elementor-home', 'admin.php?page=elementor-home' );
		remove_submenu_page( 'elementor-home', 'elementor-home' );
	}

	/**
	 * Datos remotos del inicio rápido: textos con la marca propia y sin lo que promociona productos, vídeos o
	 * comunidades del proveedor. Se conservan los accesos (ajustes del sitio, logotipo, colores, fuentes…).
	 */
	public static function home_data( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}
		// Llega como el JSON remoto completo o ya sin su envoltorio `home-screen`, según la versión del núcleo.
		$wrapped = isset( $data['home-screen'] ) && is_array( $data['home-screen'] );
		$screen  = $wrapped ? $data['home-screen'] : ( isset( $data['top_with_licences'] ) ? $data : null );
		if ( null !== $screen ) {
			foreach ( (array) ( $screen['top_with_licences'] ?? array() ) as $i => $top ) {
				if ( is_array( $top ) ) {
					$screen['top_with_licences'][ $i ] = array_merge( $top, array( 'button_watch_title' => '', 'button_watch_url' => '', 'youtube_embed_id' => '' ) );
				}
			}
			if ( isset( $screen['add_ons'] ) && is_array( $screen['add_ons'] ) ) {
				$screen['add_ons']['hide_section'] = true;
				$screen['add_ons']['repeater']     = array();
			}
			foreach ( (array) ( $screen['sidebar_promotion_variants'] ?? array() ) as $i => $promotion ) {
				if ( is_array( $promotion ) ) {
					$screen['sidebar_promotion_variants'][ $i ]['is_enabled'] = false;
				}
			}
			// Enlaces propios en lugar de los de la comunidad y el blog del proveedor.
			$icon                     = function_exists( 'plugins_url' ) ? plugins_url( 'assets/digitalisimo/icono.png', DIGITALISIMO_ELEMENTS_FILE ) : '';
			$screen['external_links'] = array(
				array( 'label' => 'Acerca de ' . self::NAME, 'image' => $icon, 'url' => function_exists( 'admin_url' ) ? admin_url( 'admin.php?page=digitalisimo-elements-about' ) : '' ),
				array( 'label' => 'Código y actualizaciones', 'image' => $icon, 'url' => 'https://github.com/danbutanda/digitalisimo-plugin' ),
			);
			if ( $wrapped ) {
				$data['home-screen'] = $screen;
			} else {
				$data = $screen;
			}
		}
		return self::brand_deep( $data );
	}

	/** Marca en los textos de una estructura, sin tocar direcciones ni identificadores. */
	private static function brand_deep( $value, $key = '' ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $k => $v ) {
				$value[ $k ] = self::brand_deep( $v, (string) $k );
			}
			return $value;
		}
		return is_string( $value ) && ! preg_match( '/(url|image|link|id|slug|license|type)$/i', $key ) && ! preg_match( '#^(https?:)?//#', $value ) ? self::brand( $value ) : $value;
	}

	/**
	 * La página MCP trae su propia copia de la librería de traducción (no pasa por wp.i18n): su contenedor recibe
	 * la marca sobre lo que pinta. El nombre del servidor es también su clave en la configuración que se copia a
	 * los clientes (Claude, Cursor, Codex…), así que va en ASCII.
	 */
	public static function mcp_name() {
		if ( ! wp_script_is( 'elementor-mcp-page', 'enqueued' ) ) {
			return;
		}
		wp_add_inline_script( 'elementor-mcp-page', 'window.elementorMcpData&&(window.elementorMcpData.serverName="DIGITALISIMO Elements");', 'before' );
		wp_add_inline_script( 'elementor-mcp-page', '(function(){' . self::js_brand()
			. 'function n(x){if(x.nodeType===3){var v=b(x.nodeValue);if(v!==x.nodeValue){x.nodeValue=v;}return;}if(x.nodeType!==1){return;}'
			. '["title","aria-label","placeholder","alt"].forEach(function(a){var v=x.getAttribute(a);if(v&&v.indexOf("lementor")>=0){x.setAttribute(a,b(v));}});'
			. 'for(var c=x.firstChild;c;c=c.nextSibling){n(c);}}'
			. 'var root=document.getElementById("elementor-mcp-root");if(!root){return;}n(root);'
			. 'new MutationObserver(function(l){l.forEach(function(m){if(m.type==="characterData"){n(m.target);}else{m.addedNodes.forEach(n);if(m.type==="attributes"){n(m.target);}}});})'
			. '.observe(root,{childList:true,subtree:true,characterData:true,attributes:true,attributeFilter:["title","aria-label","placeholder","alt"]});})();', 'after' );
	}

	/** «Información del sistema» rotula en inglés y sin traducción la conexión con la biblioteca de plantillas. */
	public static function system_info() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo decide si se reescribe una etiqueta.
		if ( 'elementor-system-info' === $page ) {
			ob_start( static function ( $html ) {
				return str_replace( array( '>Elementor Library:', '>Elementor Library<', 'Elementor uploads directory' ), array( '>Template Library:', '>Template Library<', 'Builder uploads directory' ), $html );
			} );
		}
	}

	public static function onboarded() {
		return '1';
	}

	/**
	 * Una página creada con el constructor sin título recibe «Elementor #<ID>» desde sus scripts: se guarda con
	 * el nombre propio. Sólo cambia ese título automático exacto, nunca uno escrito por el usuario.
	 */
	public static function default_title( $data ) {
		if ( is_array( $data ) && isset( $data['post_title'] ) && preg_match( '/^Elementor #(\d+)$/', (string) $data['post_title'], $m ) ) {
			$data['post_title'] = self::NAME . ' #' . $m[1];
		}
		return $data;
	}

	/** Elementos del constructor en la barra de administración (incluido su depurador) con el nombre propio. */
	public static function admin_bar() {
		global $wp_admin_bar;
		if ( ! is_object( $wp_admin_bar ) || ! method_exists( $wp_admin_bar, 'get_nodes' ) ) {
			return;
		}
		foreach ( (array) $wp_admin_bar->get_nodes() as $node ) {
			if ( false !== strpos( (string) $node->id, 'elementor' ) && is_string( $node->title ) && false !== strpos( $node->title, 'lementor' ) ) {
				$wp_admin_bar->add_node( array( 'id' => $node->id, 'title' => self::brand( $node->title ) ) );
			}
		}
	}

	/** Con Classic Editor, «Añadir nueva ▾» ofrece el constructor con un nombre fijo en un script del núcleo. */
	public static function new_post_dropdown() {
		// El núcleo inserta la opción al cargar el documento; este oyente se registra después y corre tras el suyo.
		echo '<script>document.addEventListener("DOMContentLoaded",function(){var d=document.querySelector("#split-page-title-action .dropdown");if(!d){return;}d.querySelectorAll("a").forEach(function(a){if(a.textContent==="Elementor"){a.textContent=' . wp_json_encode( self::NAME, JSON_UNESCAPED_UNICODE ) . ';}});});</script>';
	}

	/** El pie de las pantallas del constructor pide valorar el núcleo en wordpress.org: no corresponde. */
	public static function footer_text( $text ) {
		return is_string( $text ) && ( false !== strpos( $text, 'go.elementor.com/admin-review' ) || false !== strpos( $text, 'wordpress.org/support/plugin/elementor' ) ) ? '' : $text;
	}
}
