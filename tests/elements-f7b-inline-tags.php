<?php
/**
 * F7b · frente I: etiquetas dinámicas dentro del texto (V.8, V.14, C1-10, C1-17, C1-18, C2-8 a C2-11, C2-26 a C2-28).
 *
 * Con dobles de WordPress, de ACF, del gestor de etiquetas del núcleo y de `F7b\Flags`/`F7b\Module` (frente A), y la
 * clase real `Conditions\Visitor_Content`:
 * - arranque: nada enganchado con la bandera apagada; en el front sólo el render y la caché; controles, AJAX y script
 *   sólo en la administración o el editor;
 * - controles: interruptor sin `default`, con `style_transfer => false` y la aguja de `Flags` (`yes`), «Insertar dato»
 *   sólo con la lista blanca;
 * - sintaxis y lista blanca antes de instanciar; desconocidos intactos; escape del valor; sólo en el texto (nunca en
 *   atributos, URLs ni `<script>`); sustitución en los ajustes declarados y no en el enlace del Botón;
 * - lista negra por fragmentos (`api_key` sí, `keywords` no), `{acf:…}` sin `options:`, `password` ni `email`,
 *   `{query:…}` con y sin lista y `utm_source`, `{user:…}` con respaldo, `flag_page()` sólo con sesión;
 * - el elemento con etiquetas es dinámico; la vista previa del editor exige `edit_post`.
 */
namespace Elementor {
	abstract class Controls_Stack {}
	class Controls_Manager {
		const SWITCHER = 'switcher';
		const RAW_HTML = 'raw_html';
	}
	class Plugin {
		public static $instance;
		public $dynamic_tags;
		public $editor;
		public $preview;
	}
}

namespace Digitalisimo\Elements\F7b {
	// Dobles del frente A con la firma del contrato.
	final class Flags {
		public static function has( string $key ): bool { return ! empty( $GLOBALS['t']['flags'][ $key ] ); }
		public static function active( string $key ): bool { return self::has( $key ) || Module::editing(); }
	}
	final class Module {
		public static function requires( array $symbols, string $who ): bool { $GLOBALS['t']['requires'][] = $who; return empty( $GLOBALS['t']['missing'] ); }
		public static function editing(): bool { return ! empty( $GLOBALS['t']['editing'] ); }
	}
}

namespace {
	define( 'ABSPATH', __DIR__ );
	$GLOBALS['t'] = array(
		'flags'   => array( 'inline_tags' => true ),
		'editing' => false,
		'admin'   => false,
		'edit'    => false,
		'user'    => 0,
		'caps'    => array(),
		'actions' => array(),
		'hooks'   => array(),
		'nocache' => 0,
		'setup'   => array(),
	);
	$GLOBALS['wp_filter_t'] = array();
	function add_filter( $hook, $cb, $priority = 10, $args = 1 ) { $GLOBALS['wp_filter_t'][ $hook ][ $priority ][] = $cb; $GLOBALS['t']['hooks'][] = $hook; return true; }
	function add_action( $hook, $cb, $priority = 10, $args = 1 ) { return add_filter( $hook, $cb, $priority, $args ); }
	function remove_filter( $hook, $cb, $priority = 10 ) {
		foreach ( $GLOBALS['wp_filter_t'][ $hook ][ $priority ] ?? array() as $i => $registered ) {
			if ( $registered === $cb ) { unset( $GLOBALS['wp_filter_t'][ $hook ][ $priority ][ $i ] ); return true; }
		}
		return false;
	}
	function apply_filters( $hook, $value, ...$args ) {
		$list = $GLOBALS['wp_filter_t'][ $hook ] ?? array();
		ksort( $list );
		foreach ( $list as $callbacks ) { foreach ( $callbacks as $cb ) { $value = $cb( $value, ...$args ); } }
		return $value;
	}
	function do_action( $hook, ...$args ) { $GLOBALS['t']['actions'][] = $hook; }
	function __( $s, $d = '' ) { return $s; }
	function esc_html__( $s, $d = '' ) { return $s; }
	function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8', false ); }
	function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8', false ); }
	function wp_strip_all_tags( $s ) { return trim( strip_tags( preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $s ) ) ); }
	function wp_kses_post( $s ) { return (string) $s; }
	function wp_unslash( $s ) { return is_string( $s ) ? stripslashes( $s ) : $s; }
	function absint( $v ) { return abs( (int) $v ); }
	function is_admin() { return $GLOBALS['t']['admin']; }
	function is_user_logged_in() { return $GLOBALS['t']['user'] > 0; }
	function current_user_can( $cap, $id = 0 ) { return in_array( $cap . ':' . $id, $GLOBALS['t']['caps'], true ); }
	function nocache_headers() { $GLOBALS['t']['nocache']++; }
	function get_the_ID() { return isset( $GLOBALS['post'] ) && is_object( $GLOBALS['post'] ) ? (int) $GLOBALS['post']->ID : 0; }
	function get_post( $id ) { return $GLOBALS['t']['posts'][ (int) $id ] ?? null; }
	function setup_postdata( $post ) { $GLOBALS['t']['setup'][] = (int) $post->ID; return true; }
	function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['t']['meta'][ (int) $id ][ $key ] ?? ''; }
	function plugins_url( $path, $file ) { return 'https://sitio.test/wp-content/plugins/' . basename( dirname( $file ) ) . '/' . $path; }
	function wp_enqueue_script( ...$a ) { $GLOBALS['t']['enqueued'][] = $a; }
	function wp_add_inline_script( ...$a ) { $GLOBALS['t']['inline'][] = $a; return true; }
	function wp_json_encode( $v ) { return json_encode( $v ); }
	// ACF: definición de campos por nombre y lectura con `acf/pre_load_post_id`, como `acf_get_valid_post_id()`.
	function get_field_object( $selector, $post_id = false, $format = true, $load = true ) {
		$pre = apply_filters( 'acf/pre_load_post_id', null, $post_id );
		$id  = null !== $pre ? $pre : ( is_object( $post_id ) ? $post_id->ID : $post_id );
		// Como `acf_maybe_get_field()`: por clave, directo; por nombre, sólo si la entrada guardó su referencia `_<nombre>`.
		foreach ( $GLOBALS['t']['acf'] as $field ) {
			if ( $field['key'] === $selector || ( $field['name'] === $selector && ( $GLOBALS['t']['meta'][ (int) $id ][ '_' . $selector ] ?? '' ) === $field['key'] ) ) {
				$GLOBALS['t']['acf_reads'][] = $id;
				return $field + array( 'value' => $load ? ( $GLOBALS['t']['meta'][ (int) $id ][ $field['name'] ] ?? '' ) : null );
			}
		}
		return false;
	}
	function acf_get_field( $key ) { foreach ( $GLOBALS['t']['acf'] as $field ) { if ( $field['key'] === $key || $field['name'] === $key ) { return $field; } } return false; }

	/** Gestor de etiquetas: registra cada instancia (para comprobar la lista blanca antes de instanciar). */
	final class Fake_Tags {
		public $created = array();
		public $missing = array();
		public function get_tag_info( $name ) { return in_array( $name, $this->missing, true ) || ! isset( $this->render()[ $name ] ) ? null : array( 'class' => 'X' ); }
		public function get_tag_data_content( $id, $name, array $settings = array() ) {
			$this->created[] = $name;
			$render = $this->render();
			return isset( $render[ $name ] ) ? $render[ $name ]( $settings ) : null;
		}
		private function render(): array {
			$post = $GLOBALS['post'] ?? null;
			$user = $GLOBALS['t']['users'][ $GLOBALS['t']['user'] ] ?? null;
			return array(
				'post-title'        => function () use ( $post ) { return $post ? $post->post_title : ''; },
				'post-excerpt'      => function () use ( $post ) { return $post ? $post->post_excerpt : ''; },
				'author-name'       => function () { return 'Ana Autora'; },
				'post-date'         => function ( $s ) { return 'custom' === $s['format'] ? 'fecha:' . $s['custom_format'] : 'fecha:defecto'; },
				'post-custom-field' => function ( $s ) { return get_post_meta( get_the_ID(), $s['key'], true ); },
				// Como `acf-text`: el campo por su clave con el objeto consultado (aquí, otra entrada).
				'acf-text'          => function ( $s ) { list( $key ) = explode( ':', $s['key'] ); $f = get_field_object( $key, $GLOBALS['t']['posts'][99] ); return $f ? (string) $f['value'] : ''; },
				'user-info'         => function ( $s ) use ( $user ) {
					if ( ! $user || empty( $s['type'] ) ) { return ''; }
					return 'meta' === $s['type'] ? ( $user['meta'][ $s['meta_key'] ] ?? '' ) : ( $user[ $s['type'] ] ?? '' );
				},
				'site-title'        => function () { return 'Mi sitio'; },
				'site-tagline'      => function () { return 'Lema <b>breve</b>'; },
				'current-date-time' => function ( $s ) { return 'hoy:' . $s['custom_format']; },
			);
		}
	}

	/** Widget con la memoria de ajustes para mostrar del núcleo (`get_settings_for_display()`), atributos y controles. */
	final class Fake_Widget extends \Elementor\Controls_Stack {
		public $name;
		public $settings;
		public $controls = array();
		public $attrs    = array();
		public $parsed   = null;
		public $resets   = 0;
		public function __construct( string $name, array $settings ) { $this->name = $name; $this->settings = $settings; }
		public function get_name() { return $this->name; }
		public function get_settings( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function set_settings( $key, $value = null ) { $this->settings[ $key ] = $value; }
		public function get_settings_for_display( $key = null ) {
			if ( null === $this->parsed ) { $this->parsed = $this->settings; }
			return null === $key ? $this->parsed : ( $this->parsed[ $key ] ?? null );
		}
		public function reset_render_state() { $this->parsed = null; $this->attrs = array(); $this->resets++; }
		public function get_render_attributes( $element = '', $key = '' ) { return $this->attrs; }
		public function add_render_attribute( $element, $key = null, $value = null ) { $this->attrs[ $element ][ $key ][] = $value; return $this; }
		public function set_render_attribute( $element, $key = null, $value = null ) { $this->attrs[ $element ][ $key ] = (array) $value; return $this; }
		public function get_controls( $name = null ) { return null === $name ? $this->controls : ( $this->controls[ $name ] ?? null ); }
		public function add_control( $name, array $args ) { $this->controls[ $name ] = $args; }
	}

	\Elementor\Plugin::$instance               = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->dynamic_tags = new Fake_Tags();
	\Elementor\Plugin::$instance->editor       = new class() { public function is_edit_mode() { return $GLOBALS['t']['edit']; } };
	\Elementor\Plugin::$instance->preview      = new class() { public function is_preview_mode() { return false; } };

	$GLOBALS['t']['posts'] = array(
		12 => (object) array( 'ID' => 12, 'post_title' => 'Hola <em>mundo</em> & [gallery] {site:name}', 'post_excerpt' => 'Resumen' ),
		30 => (object) array( 'ID' => 30, 'post_title' => 'Borrador del editor', 'post_excerpt' => '' ),
		99 => (object) array( 'ID' => 99, 'post_title' => 'Otro objeto consultado', 'post_excerpt' => '' ),
	);
	$GLOBALS['t']['meta'] = array(
		12 => array( 'keywords' => 'diseño, web', 'api_key' => 'sk-123', '_secret' => 'x', 'precio' => '10', 'subtitulo' => 'Sub <i>12</i>', '_subtitulo' => 'field_sub', 'codigo' => 'clave-1', '_codigo' => 'field_codigo', 'contacto' => 'a@b.test', '_contacto' => 'field_contacto', 'Api-Key' => 'x', 'telefono' => '555' ),
		99 => array( 'subtitulo' => 'Sub de la 99', '_subtitulo' => 'field_sub' ),
		30 => array( 'keywords' => 'editor' ),
	);
	$GLOBALS['t']['acf'] = array(
		array( 'key' => 'field_sub', 'name' => 'subtitulo', 'type' => 'text' ),
		array( 'key' => 'field_codigo', 'name' => 'codigo', 'type' => 'password' ),
		array( 'key' => 'field_contacto', 'name' => 'contacto', 'type' => 'email' ),
		array( 'key' => 'field_mapa', 'name' => 'mapa', 'type' => 'google_map' ),
		array( 'key' => 'field_resumen', 'name' => 'resumen', 'type' => 'textarea' ),
	);
	$GLOBALS['t']['users'] = array(
		7 => array( 'display_name' => 'Ana P.', 'first_name' => 'Ana', 'last_name' => 'Pérez', 'description' => '', 'url' => 'https://ana.test', 'meta' => array( 'nickname' => 'anita' ) ),
	);
	$GLOBALS['post'] = $GLOBALS['t']['posts'][12];

	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-conditions/class-visitor-content.php';
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-f7b/inline-tags/class-engine.php';
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-f7b/inline-tags/class-module.php';

	use Digitalisimo\Elements\F7b\Inline_Tags\Engine;
	use Digitalisimo\Elements\F7b\Inline_Tags\Module;
	use Digitalisimo\Elements\Conditions\Visitor_Content;

	$checks = 0;
	function check( $ok, $message ) {
		global $checks;
		if ( ! $ok ) { throw new RuntimeException( 'FALLO: ' . $message ); }
		$checks++;
	}
	function boot( array $state ) {
		foreach ( $state as $k => $v ) { $GLOBALS['t'][ $k ] = $v; }
		$GLOBALS['t']['hooks']  = array();
		$GLOBALS['wp_filter_t'] = array();
		$r = new ReflectionProperty( Module::class, 'initialized' );
		if ( PHP_VERSION_ID < 80100 ) { $r->setAccessible( true ); }
		$r->setValue( null, false );
		Module::init();
		return $GLOBALS['t']['hooks'];
	}
	function tags() { return \Elementor\Plugin::$instance->dynamic_tags; }
	function created_since( int $from ) { return array_slice( tags()->created, $from ); }

	// ── Arranque ───────────────────────────────────────────────────────────────────────────────────────────────────
	check( array() === boot( array( 'flags' => array(), 'editing' => false, 'admin' => false ) ), 'con Flags::active( inline_tags ) falso no se engancha nada' );
	check( array() === boot( array( 'flags' => array( 'inline_tags' => true ), 'missing' => true ) ), 'sin sus símbolos (F7b\Module::requires) no se engancha nada' );
	$GLOBALS['t']['missing'] = false;
	$front = boot( array( 'flags' => array( 'inline_tags' => true ), 'editing' => false, 'admin' => false ) );
	check( array( 'elementor/widget/before_render_content', 'elementor/element/is_dynamic_content' ) === $front, 'en el front, sólo la sustitución y la caché de elementos: ni controles, ni AJAX, ni scripts' );
	$editor = boot( array( 'flags' => array(), 'editing' => true, 'admin' => true ) );
	foreach ( Module::WIDGETS as $widget => $def ) {
		check( in_array( "elementor/element/{$widget}/{$def['section']}/before_section_end", $editor, true ), "en el editor, el interruptor entra en {$widget}/{$def['section']}" );
	}
	check( in_array( 'elementor/ajax/register_actions', $editor, true ) && in_array( 'elementor/editor/after_enqueue_scripts', $editor, true ) && ! in_array( 'wp_enqueue_scripts', $editor, true ), 'en el editor: la acción AJAX y el script del editor; nunca wp_enqueue_scripts' );
	check( array( 'heading', 'text-editor', 'button', 'digitalisimo-advanced-button', 'icon-box', 'digitalisimo-advanced-icon-box', 'call-to-action', 'digitalisimo-call-out' ) === array_keys( Module::WIDGETS ), 'Encabezado, Editor de texto, los dos Botón, las dos Cajas de icono y las dos Llamadas a la acción' );

	// ── Controles ──────────────────────────────────────────────────────────────────────────────────────────────────
	$icon_box = new Fake_Widget( 'icon-box', array() );
	Module::register_controls( $icon_box );
	$control = $icon_box->get_controls( Module::CONTROL );
	check( 'switcher' === $control['type'] && 'yes' === $control['return_value'] && false === $control['style_transfer'] && ! array_key_exists( 'default', $control ), 'interruptor sin default, que guarda «yes» (la aguja de Flags) y con style_transfer => false' );
	check( false !== strpos( $control['description'], 'versión anterior' ), 'el control avisa de la vuelta atrás (V.11)' );
	$help = $icon_box->get_controls( Module::HELP );
	check( 'raw_html' === $help['type'] && array( Module::CONTROL => 'yes' ) === $help['condition'] && false === $help['style_transfer'] && ! array_key_exists( 'default', $help ), '«Insertar dato» sólo con el interruptor encendido y sin valor guardado' );
	preg_match_all( '/<option value="([^"]*)"/', $help['raw'], $values );
	check( array( 'post:title', 'post:excerpt', 'post:author', 'post:date', 'post:meta', 'acf', 'user:display_name', 'user:first_name', 'user:last_name', 'user:nickname', 'user:description', 'user:url', 'site:name', 'site:tagline', 'date', 'query', 'title_text', 'description_text' ) === $values[1], '«Insertar dato» ofrece sólo la lista blanca y los dos textos de la Caja de icono' );
	check( false === strpos( $help['raw'], 'user:email' ) && false === strpos( $help['raw'], 'options:' ) && false !== strpos( $help['raw'], 'aria-live="polite"' ) && false !== strpos( $help['raw'], 'sesión iniciada' ), 'sin datos sensibles; vista previa anunciada y aviso de la caché con sesión' );
	Module::register_controls( $icon_box );
	check( 2 === count( $icon_box->controls ), 'registrar dos veces no duplica' );
	$unknown = new Fake_Widget( 'image', array() );
	Module::register_controls( $unknown );
	check( array() === $unknown->controls, 'un widget que no está en la lista no recibe el control' );
	$heading = new Fake_Widget( 'heading', array() );
	Module::register_controls( $heading );
	check( false !== strpos( $heading->get_controls( Module::HELP )['raw'], '<input type="hidden" data-digi-inline-target value="title">' ), 'con un solo texto, el destino va oculto' );

	// ── Sintaxis, lista blanca y escape ────────────────────────────────────────────────────────────────────────────
	$from = count( tags()->created );
	check( 'Hola mundo &amp; &#91;gallery&#93; &#123;site:name&#125;' === Engine::render( '{post:title}' ), 'el valor pasa por esc_html sin HTML; corchetes y llaves como entidades (ni shortcodes ni otra etiqueta)' );
	check( 'Resumen · Ana Autora · fecha:defecto · fecha:j F Y · Mi sitio · Lema breve · hoy:F Y' === Engine::render( '{post:excerpt} · {post:author} · {post:date} · {post:date:j F Y} · {site:name} · {site:tagline} · {date:F Y}' ), 'post, site y date con las etiquetas de PRO' );
	check( array( 'post-title', 'post-excerpt', 'author-name', 'post-date', 'post-date', 'site-title', 'site-tagline', 'current-date-time' ) === created_since( $from ), 'cada etiqueta se instancia por nombre con el gestor del núcleo' );
	$from = count( tags()->created );
	$unknown_tokens = '{post:content} {term:name} {user:email} {user:login} {user:id} {user:meta} {site:url} {foo:bar} { post:title } {post:title {"a":1} {POST:title} {post:date:<b>} {date:Y|m} {acf:} {post:meta:}';
	check( $unknown_tokens === Engine::render( $unknown_tokens ), 'lo desconocido (también {term:name}, retirado) y lo mal formado queda tal cual' );
	check( array() === created_since( $from ), 'lista blanca antes de instanciar: nada desconocido llega al gestor' );
	tags()->missing = array( 'site-tagline' );
	check( '{site:tagline}' === Engine::render( '{site:tagline}' ), 'una etiqueta que el sitio no tiene registrada queda tal cual' );
	tags()->missing = array();

	// Sólo en el texto: nunca dentro de una etiqueta HTML, en una URL ni en <script>/<style>.
	$html = '<p class="{site:name}"><a href="https://x.test/?p={post:title}" title="{site:name}">{site:name}</a></p><script>var s="{site:name}";</script><style>.a{content:"{site:name}"}</style><p>{site:name}</p>';
	check( '<p class="{site:name}"><a href="https://x.test/?p={post:title}" title="{site:name}">Mi sitio</a></p><script>var s="{site:name}";</script><style>.a{content:"{site:name}"}</style><p>Mi sitio</p>' === Engine::render( $html ), 'en HTML sólo cambia el texto: atributos, URLs, script y style intactos' );

	// ── Lista negra por fragmentos (C2-28) ─────────────────────────────────────────────────────────────────────────
	$from = count( tags()->created );
	check( '{post:meta:api_key}' === Engine::render( '{post:meta:api_key}' ) && '{post:meta:Api-Key}' === Engine::render( '{post:meta:Api-Key}' ), 'api_key y Api-Key se bloquean (fragmento key, sin distinguir mayúsculas, con _ o -)' );
	check( '{post:meta:_secret}' === Engine::render( '{post:meta:_secret}' ) && '{post:meta:user_email}' === Engine::render( '{post:meta:user_email}' ), 'metas protegidas (_) y de la lista negra, intactas' );
	check( array() === created_since( $from ), 'lo bloqueado nunca se instancia' );
	check( 'diseño, web' === Engine::render( '{post:meta:keywords}' ), 'keywords no se bloquea: key no es un fragmento' );
	check( Engine::denied( 'API_KEY' ) && Engine::denied( 'billing-phone' ) && Engine::denied( 'mi_nombre' ) && ! Engine::denied( 'keywords' ) && ! Engine::denied( 'passage' ) && Engine::denied( 'telefono' ) && Engine::denied( 'correo_contacto' ) && ! Engine::denied( 'telefonos' ), 'coincidencia por fragmentos completos, también en español' );
	add_filter( Engine::FILTER_DENIED, function ( $list ) { $list[] = 'PRECIO'; return $list; } );
	check( '{post:meta:precio}' === Engine::render( '{post:meta:precio}' ), 'la lista negra se amplía con su filtro' );
	$GLOBALS['wp_filter_t'] = array();
	check( '10' === Engine::render( '{post:meta:precio}' ), 'sin el filtro, la meta vuelve a leerse' );

	// ── ACF (C2-8) ─────────────────────────────────────────────────────────────────────────────────────────────────
	$from = count( tags()->created );
	foreach ( array( '{acf:options:api}', '{acf:options:subtitulo}', '{acf:codigo}', '{acf:contacto}', '{acf:mapa}', '{acf:mi_password}', '{acf:no_existe}' ) as $token ) {
		check( $token === Engine::render( $token ), "{$token}: sin sustituir (opciones, password, email, tipo no permitido, lista negra o inexistente)" );
	}
	check( array() === created_since( $from ), 'ningún campo rechazado llega a instanciar acf-text' );
	$GLOBALS['t']['acf_reads'] = array();
	check( 'Sub 12' === Engine::render( '{acf:subtitulo}' ), '{acf:campo} de texto se sustituye, sin HTML' );
	check( array( 12, 12 ) === $GLOBALS['t']['acf_reads'] && empty( $GLOBALS['wp_filter_t']['acf/pre_load_post_id'][99] ), 'ACF lee siempre la entrada del contexto (no el objeto consultado) y el filtro se retira' );
	check( '{post:meta:codigo}' === Engine::render( '{post:meta:codigo}' ), 'un campo password de ACF tampoco se cuela por {post:meta:…}' );
	check( '' === Engine::render( '{acf:resumen}' ) && 'acf-text' === end( tags()->created ), 'un campo permitido que esta entrada no guardó imprime vacío, no la etiqueta' );

	// ── {query:…} (C2-9, C2-27) ────────────────────────────────────────────────────────────────────────────────────
	$_GET = array( 'plan' => 'pro', 'utm_source' => 'a', 'gclid' => 'a', 'email' => 'a@b.test', 'x' => 'a' );
	$from = count( tags()->created );
	check( 'pro' === Engine::render( '{query:plan|basico,pro}' ), 'con lista: imprime el valor de la lista que coincide' );
	check( '{query:plan}' === Engine::render( '{query:plan}' ), 'sin lista no se sustituye' );
	foreach ( array( '{query:utm_source|a,b}', '{query:UTM_campaign|a}', '{query:gclid|a}', '{query:fbclid|a}', '{query:mc_eid|a}', '{query:email|a}', '{query:api-key|a}' ) as $token ) {
		check( $token === Engine::render( $token ), "{$token}: parámetro prohibido, sin sustituir" );
	}
	$_GET['plan'] = 'enterprise';
	check( '' === Engine::render( '{query:plan|basico,pro}' ), 'un valor fuera de la lista no imprime nada' );
	$_GET['plan'] = '<script>alert(1)</script>';
	check( '' === Engine::render( '{query:plan|basico,pro}' ), 'lo que llega en la URL nunca se imprime' );
	$_GET['plan'] = array( 'pro' );
	check( '' === Engine::render( '{query:plan|basico,pro}' ), 'un parámetro en forma de lista no coincide' );
	$_GET['plan'] = 'a&b';
	check( 'a&amp;b' === Engine::render( '{query:plan|a&b,c}' ), 'el valor de la lista también se escapa' );
	unset( $_GET['plan'] );
	check( '' === Engine::render( '{query:plan|basico,pro}' ), 'sin el parámetro no se imprime nada' );
	check( array() === created_since( $from ), '{query:…} no usa request-arg ni ninguna otra etiqueta' );

	// ── {user:…} y caché de página (C2-10, C1-17) ──────────────────────────────────────────────────────────────────
	Visitor_Content::reset();
	$GLOBALS['t']['admin']   = false;
	$GLOBALS['t']['edit']    = false;
	$GLOBALS['t']['user']    = 0;
	$GLOBALS['t']['actions'] = array();
	check( '' === Engine::render( '{user:first_name}' ) && 'visitante' === Engine::render( '{user:first_name|visitante}' ) && 'Hola, visitante' === Engine::render( 'Hola, {user:first_name|visitante}' ), 'sin sesión: vacío o el respaldo' );
	check( ! in_array( 'litespeed_control_set_nocache', $GLOBALS['t']['actions'], true ) && 0 === $GLOBALS['t']['nocache'], 'sin sesión, flag_page() no se llama: la página sigue en caché' );
	$GLOBALS['t']['user'] = 7;
	check( 'Ana' === Engine::render( '{user:first_name|visitante}' ) && 'anita' === Engine::render( '{user:nickname}' ) && 'Ana P.' === Engine::render( '{user:display_name}' ), 'con sesión: el dato del usuario (el apodo, como meta nickname)' );
	check( in_array( 'litespeed_control_set_nocache', $GLOBALS['t']['actions'], true ) && defined( 'DONOTCACHEPAGE' ), 'con sesión, flag_page() saca la página de la caché' );
	Visitor_Content::reset();
	$GLOBALS['t']['actions'] = array();
	Engine::render( '{post:title} {site:name} {query:plan|a,b} {date:Y}' );
	check( ! in_array( 'litespeed_control_set_nocache', $GLOBALS['t']['actions'], true ), 'post, site, date y query no tocan la caché de página, aun con sesión' );
	check( '{user:first_name|a|b}' === Engine::render( '{user:first_name|a|b}' ) && '{user:first_name|' . str_repeat( 'x', 61 ) . '}' === Engine::render( '{user:first_name|' . str_repeat( 'x', 61 ) . '}' ), 'un respaldo mal formado o demasiado largo queda tal cual' );
	$GLOBALS['t']['user'] = 0;

	// ── Sustitución en los ajustes (C1-10) ─────────────────────────────────────────────────────────────────────────
	boot( array( 'flags' => array( 'inline_tags' => true ), 'editing' => false, 'admin' => false ) );
	$_GET = array( 'x' => 'a' );
	$link   = array( 'url' => 'https://x.test/?q={query:x|a,b}&t={post:title}', 'is_external' => '' );
	$button = new Fake_Widget( 'button', array( 'digitalisimo_inline_tags' => 'yes', 'text' => 'Ir a {site:name} ({query:x|a,b})', 'link' => $link, 'css_id' => '{site:name}' ) );
	$button->add_render_attribute( '_wrapper', 'data-x', '1' );
	$button->get_settings_for_display();
	Module::before_render_content( $button );
	check( 'Ir a Mi sitio (a)' === $button->get_settings_for_display( 'text' ), 'el texto del Botón se sustituye y get_settings_for_display() lo ve aunque ya se hubiera leído' );
	check( $link === $button->get_settings( 'link' ) && '{site:name}' === $button->get_settings( 'css_id' ), '{query:x} en el enlace del Botón no se sustituye; ningún otro control cambia' );
	check( array( '_wrapper' => array( 'data-x' => array( '1' ) ) ) === $button->attrs && 1 === $button->resets, 'los atributos que otros ganchos ya añadieron se conservan' );
	$plain = new Fake_Widget( 'heading', array( 'title' => 'Sin etiquetas {site:name}' ) );
	Module::before_render_content( $plain );
	check( 'Sin etiquetas {site:name}' === $plain->get_settings( 'title' ) && 0 === $plain->resets, 'con el interruptor apagado no cambia nada' );
	$other = new Fake_Widget( 'image', array( 'digitalisimo_inline_tags' => 'yes', 'caption' => '{site:name}' ) );
	Module::before_render_content( $other );
	check( '{site:name}' === $other->get_settings( 'caption' ), 'un widget fuera de la lista no cambia' );
	$box = new Fake_Widget( 'digitalisimo-advanced-icon-box', array( 'digitalisimo_inline_tags' => 'yes', 'title_text' => '{post:excerpt}', 'sub_title_text' => '{site:name}', 'description_text' => '<p>De {post:author}</p>', 'readmore_text' => '{site:name}' ) );
	Module::before_render_content( $box );
	check( 'Resumen' === $box->get_settings( 'title_text' ) && 'Mi sitio' === $box->get_settings( 'sub_title_text' ) && '<p>De Ana Autora</p>' === $box->get_settings( 'description_text' ) && '{site:name}' === $box->get_settings( 'readmore_text' ), 'sólo los controles de texto declarados de cada widget' );
	$GLOBALS['t']['edit'] = true;
	$in_editor = new Fake_Widget( 'heading', array( 'digitalisimo_inline_tags' => 'yes', 'title' => '{site:name}' ) );
	Module::before_render_content( $in_editor );
	check( '{site:name}' === $in_editor->get_settings( 'title' ), 'en el editor se ve lo escrito: la edición en línea nunca guarda un valor resuelto' );
	$GLOBALS['t']['edit'] = false;

	// ── Caché de elementos (C1-17) ─────────────────────────────────────────────────────────────────────────────────
	check( true === Module::is_dynamic_content( false, array( 'widgetType' => 'heading', 'settings' => array( 'digitalisimo_inline_tags' => 'yes', 'title' => 'Hola {query:plan|a,b}' ) ) ), 'un elemento con etiquetas es dinámico' );
	check( false === Module::is_dynamic_content( false, array( 'widgetType' => 'heading', 'settings' => array( 'title' => 'Hola {post:title}' ) ) ), 'sin el interruptor, no' );
	check( false === Module::is_dynamic_content( false, array( 'widgetType' => 'heading', 'settings' => array( 'digitalisimo_inline_tags' => 'yes', 'title' => 'Hola {otra}' ) ) ), 'con el interruptor pero sin etiquetas, no' );
	check( false === Module::is_dynamic_content( false, array( 'widgetType' => 'button', 'settings' => array( 'digitalisimo_inline_tags' => 'yes', 'text' => 'Ir', 'link' => array( 'url' => '{post:title}' ) ) ) ), 'una etiqueta en el enlace no cuenta' );
	check( true === Module::is_dynamic_content( true, array( 'widgetType' => 'image' ) ) && false === Module::is_dynamic_content( false, 'x' ), 'lo demás conserva la decisión previa' );

	// ── Vista previa del editor (C2-26, C2-18) ─────────────────────────────────────────────────────────────────────
	$registered = array();
	Module::register_ajax( new class( $registered ) { public $r; public function __construct( &$r ) { $this->r = &$r; } public function register_ajax_action( $n, $cb ) { $this->r[ $n ] = $cb; } } );
	check( array( 'digitalisimo_f7b_inline_preview' ) === array_keys( $registered ), 'una sola acción AJAX, registrada con elementor/ajax/register_actions' );
	$denied = function ( $data ) { try { Module::ajax_preview( $data ); } catch ( Exception $e ) { return 403 === $e->getCode(); } return false; };
	$GLOBALS['t']['caps'] = array();
	check( $denied( array( 'editor_post_id' => 30, 'token' => '{post:title}' ) ), 'un suscriptor no puede pedir la vista previa' );
	$GLOBALS['t']['caps'] = array( 'edit_post:12' );
	check( $denied( array( 'editor_post_id' => 30, 'token' => '{post:title}' ) ) && $denied( array( 'token' => '{post:title}' ) ) && $denied( array( 'editor_post_id' => array( 30 ), 'token' => '{post:title}' ) ), 'un autor sin permiso sobre ese documento, o sin documento, tampoco' );
	$GLOBALS['t']['caps'] = array( 'edit_post:30' );
	$GLOBALS['t']['user'] = 7;
	$GLOBALS['t']['actions'] = array();
	Visitor_Content::reset();
	check( array( 'state' => 'ok', 'value' => 'Borrador del editor' ) === Module::ajax_preview( array( 'editor_post_id' => 30, 'token' => '{post:title}' ) ), 'vista previa con el documento del editor como contexto' );
	check( 12 === get_the_ID(), 'después, la entrada global vuelve a ser la de antes' );
	check( array( 'state' => 'ok', 'value' => 'Ana' ) === Module::ajax_preview( array( 'editor_post_id' => 30, 'token' => '{user:first_name}' ) ) && ! in_array( 'litespeed_control_set_nocache', $GLOBALS['t']['actions'], true ), 'la vista previa de {user:…} no toca la caché' );
	check( array( 'state' => 'empty', 'value' => '' ) === Module::ajax_preview( array( 'editor_post_id' => 30, 'token' => '{post:excerpt}' ) ), 'un dato vacío se anuncia' );
	check( 'blocked' === Module::ajax_preview( array( 'editor_post_id' => 30, 'token' => '{post:meta:api_key}' ) )['state'] && 'blocked' === Module::ajax_preview( array( 'editor_post_id' => 30, 'token' => '{acf:options:x}' ) )['state'] && 'blocked' === Module::ajax_preview( array( 'editor_post_id' => 30, 'token' => '{acf:codigo}' ) )['state'], 'lo protegido se anuncia como bloqueado' );
	check( 'unknown' === Module::ajax_preview( array( 'editor_post_id' => 30, 'token' => '{user:email}' ) )['state'] && 'unknown' === Module::ajax_preview( array( 'editor_post_id' => 30, 'token' => 'texto {post:title}' ) )['state'], 'lo que no es una etiqueta se anuncia como desconocido' );
	check( array( 'state' => 'url', 'value' => 'basico, pro' ) === Module::ajax_preview( array( 'editor_post_id' => 30, 'token' => '{query:plan|basico,pro}' ) ), '{query:…} anuncia que depende de la URL y su lista' );
	$GLOBALS['t']['user'] = 0;

	// ── Script del editor ──────────────────────────────────────────────────────────────────────────────────────────
	$GLOBALS['t']['enqueued'] = array();
	$GLOBALS['t']['inline']   = array();
	Module::enqueue();
	$script = $GLOBALS['t']['enqueued'][0];
	check( 'digitalisimo-f7b-inline-tags' === $script[0] && '/digitalisimo-f7b/assets/js/inline-tags-editor.js' === substr( $script[1], -strlen( '/digitalisimo-f7b/assets/js/inline-tags-editor.js' ) ) && array( 'elementor-editor', 'jquery' ) === $script[2] && true === $script[4], 'inline-tags-editor.js, sólo en el editor, en el pie' );
	check( 0 === strpos( $GLOBALS['t']['inline'][0][1], 'window.digitalisimoInlineTags={"action":"digitalisimo_f7b_inline_preview"' ) && 'before' === $GLOBALS['t']['inline'][0][2], 'configuración con la acción y los textos' );
	$js = __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-f7b/assets/js/inline-tags-editor.js';
	$source = (string) file_get_contents( $js );
	check( filesize( $js ) <= 8192, 'inline-tags-editor.js pesa ' . filesize( $js ) . ' bytes; el tope es 8 KB' );
	$code = implode( "\n", array_filter( explode( "\n", $source ), function ( $l ) { return ! preg_match( '#^\s*(//|/\*)#', $l ); } ) );
	preg_match_all( "/'([^'\\\\]*(?:\\\\.[^'\\\\]*)*)'/", $code, $literals );
	check( array() === array_values( array_filter( $literals[1], function ( $s ) { return preg_match( '/[áéíóúñ¿¡«»]|Elementor/u', $s ); } ) ), 'el script no tiene textos visibles propios: vienen de PHP' );
	check( ! preg_match( '/\?\.|\?\?|\basync\b|\bclass\s+\w|\{\s*\.\.\./', $source ), 'el script es ES2017' );

	// ── Textos sin proveedores ─────────────────────────────────────────────────────────────────────────────────────
	$texts = implode( ' ', array_merge( Module::strings(), array( $control['label'], $control['description'], $help['raw'] ) ) );
	check( ! preg_match( '/Elementor\b|Bricks|Element Pack|ACF Pro|request-arg/', $texts ), 'los textos visibles no nombran a otros constructores ni detalles internos' );

	echo "F7b · etiquetas dentro del texto (frente I): {$checks} comprobaciones en verde.\n";
}
