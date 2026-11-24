<?php
/**
 * Avisos de administración de los plugins DIGITALÍSIMO.
 *
 * Queja del propietario (2026-11-22): «se están mostrando alertas así y son molestas, no poder quitarlas; mándalas a las
 * alertas globales de WP pero no las pongas en el contenido». Por eso todo aviso propio de `admin_notices` pasa por aquí:
 *  - lleva la X de WordPress (`is-dismissible`) y, al pulsarla, el cierre queda guardado para ese usuario (user meta
 *    `digitalisimo_notice_<id>`, una fila por aviso: dos cierres a la vez no se pisan) con la huella de la situación; si
 *    la situación cambia (otra huella), el aviso vuelve a salir. La user meta es global en una red: un aviso de toda la
 *    red se cierra una vez (en la red o en cualquier sitio); uno que depende de cada sitio lleva el sitio en su id
 *    (`site()`), y cerrarlo en un sitio no lo oculta en otro;
 *  - lo informativo y los éxitos («no hace falta hacer nada») quedan cerrados mientras la huella no cambie; una
 *    advertencia que pide algo vuelve a los 7 días y un error a 1 día si el problema sigue (`snooze`);
 *  - el cierre va por AJAX (`wp_ajax_digitalisimo_notice_dismiss`) con un nonce atado al id, la huella y la capacidad
 *    del aviso, y sólo lo guarda quien tiene esa capacidad. Si el cierre no se guarda (nonce vencido, sin sesión), el
 *    aviso se queda y dice por qué.
 * El aviso no se pinta dentro del contenido: cada pantalla propia lleva `<hr class="wp-header-end">` tras su título y
 * el JavaScript común de WordPress mueve ahí los avisos (sin esa marca los ponía tras el primer h1/h2 de `.wrap`, que en
 * nuestras pantallas podía caer dentro de una tarjeta o de una pestaña).
 *
 * Copia idéntica en los siete plugins (`includes/class-digitalisimo-notices.php`; en Elements, `digitalisimo-notices.php`)
 * con guardia `class_exists`: cualquiera funciona sola y se usa la primera que carga. tests/admin-notices.php comprueba
 * las copias. El tema y el instalador de Elements (sin JavaScript propio) la usan si algún plugin la cargó y, si no,
 * pintan el aviso con la X de WordPress sin recordar el cierre.
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Digitalisimo_Notices' ) ) {
	final class Digitalisimo_Notices {
		const VERSION = 1;
		/** Prefijo de la user meta de cada cierre: `digitalisimo_notice_<id>` => array( 'h' => huella, 't' => cuándo ). */
		const META = 'digitalisimo_notice_';
		/** Acción AJAX del cierre. */
		const ACTION = 'digitalisimo_notice_dismiss';
		/** Cuánto dura el cierre de una advertencia y de un error (segundos); éxitos e informativos: hasta que cambie la huella. */
		const WARNING_SNOOZE = 604800;
		const ERROR_SNOOZE   = 86400;

		private static $booted = false;
		private static $script = false;

		/** Registra el cierre por AJAX (una vez, aunque carguen varias copias; en el front no engancha nada). */
		public static function boot() {
			if ( self::$booted || ! function_exists( 'add_action' ) || ( function_exists( 'is_admin' ) && ! is_admin() ) ) return;
			self::$booted = true;
			add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'ajax' ) );
		}

		/** Lo que dura un cierre según el tipo del aviso: 0 = hasta que cambie la huella. */
		public static function snooze_for( $type ) {
			if ( 'error' === $type ) return self::ERROR_SNOOZE;
			if ( 'warning' === $type ) return self::WARNING_SNOOZE;
			return 0;
		}

		/** Id de un aviso: minúsculas, dígitos, «-» y «_», hasta 80 caracteres. */
		public static function key( $id ) {
			return substr( preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $id ) ), 0, 80 );
		}

		/** Id de un aviso que depende del sitio: en una red lleva el sitio (cerrarlo en un sitio no lo oculta en otro). */
		public static function site( $id ) {
			return function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'get_current_blog_id' ) ? $id . '-s' . (int) get_current_blog_id() : (string) $id;
		}

		/** Huella de la situación (12 hex). Sin los nonces, que cambian solos cada pocas horas. */
		public static function fingerprint( $value ) {
			if ( ! is_string( $value ) ) $value = function_exists( 'wp_json_encode' ) ? (string) wp_json_encode( $value ) : (string) json_encode( $value );
			$value = (string) preg_replace( '/((?:_wpnonce|nonce)(?:=|"\s+value="|\'\s+value=\'))[0-9a-f]{6,}/i', '$1', $value );
			return substr( md5( $value ), 0, 12 );
		}

		/**
		 * HTML de un aviso de WordPress con la X, o '' si este usuario lo cerró y la situación sigue igual.
		 *
		 * @param string $id      Id estable del aviso (p. ej. 'tools-cache').
		 * @param string $type    success, info, warning o error.
		 * @param string $content HTML ya escapado de dentro del aviso (`<p>…</p>`, formularios…).
		 * @param array  $args    hash (lo que define la situación; por defecto, el contenido), snooze (segundos que dura el
		 *                        cierre; 0 = hasta que cambie la huella), cap (capacidad para cerrarlo; por defecto `read`),
		 *                        class (clases de más), persist (false: sólo la X, sin recordar; para resultados de un
		 *                        formulario que salen una vez).
		 */
		public static function html( $id, $type, $content, array $args = array() ) {
			$type    = in_array( $type, array( 'success', 'info', 'warning', 'error' ), true ) ? $type : 'info';
			$extra   = trim( (string) ( $args['class'] ?? '' ) );
			$classes = 'notice notice-' . $type . ' is-dismissible' . ( '' !== $extra ? ' ' . $extra : '' );
			$id      = self::key( $id );
			if ( false === ( $args['persist'] ?? true ) || '' === $id ) return '<div class="' . self::attr( $classes ) . '">' . $content . '</div>';
			$hash   = self::fingerprint( array_key_exists( 'hash', $args ) ? $args['hash'] : $content );
			$snooze = isset( $args['snooze'] ) ? max( 0, (int) $args['snooze'] ) : self::snooze_for( $type );
			$cap    = self::cap( $args['cap'] ?? 'read' );
			if ( self::dismissed( $id, $hash, $snooze ) ) return '';
			self::script();
			$nonce = function_exists( 'wp_create_nonce' ) ? wp_create_nonce( self::nonce_action( $id, $hash, $cap ) ) : '';
			return '<div class="' . self::attr( $classes ) . '" data-digitalisimo-notice="' . self::attr( $id ) . '" data-digitalisimo-notice-hash="' . self::attr( $hash ) . '" data-digitalisimo-notice-cap="' . self::attr( $cap ) . '" data-digitalisimo-notice-nonce="' . self::attr( $nonce ) . '">' . $content . '</div>';
		}

		/** Pinta el aviso (ver html()); devuelve si se pintó. */
		public static function show( $id, $type, $content, array $args = array() ) {
			$html = self::html( $id, $type, $content, $args );
			if ( '' === $html ) return false;
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- el contenido llega escapado y los atributos se escapan arriba.
			return true;
		}

		/** ¿Cerró este usuario el aviso con esta huella y aún no vence el cierre? */
		public static function dismissed( $id, $hash, $snooze = 0, $user = null ) {
			$user = null === $user ? ( function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0 ) : (int) $user;
			if ( $user <= 0 || ! function_exists( 'get_user_meta' ) ) return false;
			$entry = get_user_meta( $user, self::META . self::key( $id ), true );
			if ( ! is_array( $entry ) || (string) ( $entry['h'] ?? '' ) !== (string) $hash ) return false;
			return 0 === (int) $snooze || time() - (int) ( $entry['t'] ?? 0 ) < (int) $snooze;
		}

		/** Guarda el cierre de un aviso para un usuario. */
		public static function dismiss( $user, $id, $hash ) {
			$user = (int) $user;
			$id   = self::key( $id );
			if ( $user <= 0 || '' === $id || ! function_exists( 'update_user_meta' ) ) return false;
			// Una fila por aviso: sin leer y reescribir una lista, dos cierres a la vez (dos pestañas) no se pisan.
			return false !== update_user_meta( $user, self::META . $id, array( 'h' => (string) $hash, 't' => time() ) );
		}

		/** `wp_ajax_digitalisimo_notice_dismiss`: nonce del aviso (id, huella y capacidad) y la capacidad que pide. */
		public static function ajax() {
			$field = static function ( $name ) { $value = $_POST[ $name ] ?? ''; return is_string( $value ) ? (string) wp_unslash( $value ) : ''; }; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- el nonce se comprueba abajo.
			$id    = self::key( $field( 'id' ) );
			$hash  = $field( 'hash' );
			$cap   = self::cap( $field( 'cap' ) );
			$valid = '' !== $id && 1 === preg_match( '/^[0-9a-f]{12}$/D', $hash ) && false !== check_ajax_referer( self::nonce_action( $id, $hash, $cap ), 'nonce', false );
			if ( ! $valid ) wp_send_json_error( array( 'message' => 'El aviso caducó: recarga la página y vuelve a cerrarlo.' ), 403 );
			if ( ! current_user_can( $cap ) ) wp_send_json_error( array( 'message' => 'No autorizado.' ), 403 );
			self::dismiss( get_current_user_id(), $id, $hash );
			wp_send_json_success();
		}

		private static function nonce_action( $id, $hash, $cap ) {
			return self::ACTION . '|' . $id . '|' . $hash . '|' . $cap;
		}

		private static function cap( $cap ) {
			$cap = preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $cap ) );
			return '' !== $cap ? $cap : 'read';
		}

		private static function attr( $value ) {
			return function_exists( 'esc_attr' ) ? esc_attr( (string) $value ) : htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
		}

		/** El script del cierre, una vez por página y sólo si hay un aviso que recordar. */
		private static function script() {
			if ( self::$script || ! function_exists( 'add_action' ) ) return;
			self::$script = true;
			add_action( 'admin_print_footer_scripts', array( __CLASS__, 'print_script' ) );
		}

		/**
		 * La X de un aviso propio (fase de captura, antes que WordPress lo quite): manda id, huella, capacidad y nonce y
		 * sólo quita el aviso cuando el cierre quedó guardado; si no (nonce vencido, sesión cerrada, sin red), el aviso se
		 * queda con una línea que dice por qué. Sin jQuery ni dependencias; sin `fetch`, WordPress lo cierra en la página.
		 */
		public static function print_script() {
			$url  = function_exists( 'admin_url' ) ? admin_url( 'admin-ajax.php' ) : '/wp-admin/admin-ajax.php';
			$json = static function ( $value ) { return function_exists( 'wp_json_encode' ) ? wp_json_encode( $value ) : json_encode( $value ); };
			echo '<script id="digitalisimo-notices-js">(function(){var u=' . $json( $url ) . ',a=' . $json( self::ACTION ) . ',m=' . $json( 'No se pudo guardar el cierre de este aviso: recarga la página y vuelve a cerrarlo.' ) . ';'
				. 'function g(n,k){return n.getAttribute("data-digitalisimo-notice"+k)||"";}'
				. 'function say(n,t){var p=n.querySelector(".digitalisimo-notice-failed");if(!p){p=document.createElement("p");p.className="digitalisimo-notice-failed";p.setAttribute("role","alert");n.appendChild(p);}p.textContent=t;n.removeAttribute("aria-busy");}'
				. 'document.addEventListener("click",function(e){var t=e.target,b=t&&t.closest?t.closest(".notice-dismiss"):null,n=b?b.closest("[data-digitalisimo-notice]"):null;if(!n||!window.fetch||!window.FormData)return;'
				. 'e.preventDefault();e.stopPropagation();if(n.getAttribute("aria-busy"))return;n.setAttribute("aria-busy","true");'
				. 'var d=new FormData();d.append("action",a);d.append("id",g(n,""));d.append("hash",g(n,"-hash"));d.append("cap",g(n,"-cap"));d.append("nonce",g(n,"-nonce"));'
				. 'fetch(u,{method:"POST",credentials:"same-origin",body:d}).then(function(r){return r.json().catch(function(){return{};}).then(function(j){if(r.ok&&j&&j.success){n.style.transition="opacity .1s";n.style.opacity="0";setTimeout(function(){if(n.parentNode)n.parentNode.removeChild(n);},120);}else{say(n,j&&j.data&&j.data.message?j.data.message:m);}});}).catch(function(){say(n,m);});'
				. '},true);})();</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON de valores propios.
		}
	}
}
Digitalisimo_Notices::boot();
