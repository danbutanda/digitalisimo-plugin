<?php
/**
 * Rasgos propios del núcleo que sustituyen a la capa heredada: origen ACF de los widgets y reglas de
 * visibilidad `digitalisimo_visibility_*`, con sus nombres propios y sin depender de otro plugin.
 */
namespace {
	define( 'ABSPATH', __DIR__ );
	function check_native( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
	function wp_strip_all_tags( $text ) { return strip_tags( (string) $text ); }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8', false ); }
	function apply_filters( $hook, $value ) { return $value; }
	function is_user_logged_in() { return ! empty( $GLOBALS['digi_logged'] ); }
	function get_option( $key, $default = false ) { return $default; }
	function get_field_object( $key, $post = false ) {
		return 'field_faq' === $key ? array( 'parent' => 0, 'value' => array( array( 'q' => 'Pregunta', 'a' => 'Respuesta' ), array( 'q' => '', 'a' => '' ) ) ) : false;
	}

	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-acf-source.php';
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-conditions/class-visibility-rules.php';
	use Digitalisimo\Elements\Acf_Source;
	use Digitalisimo\Elements\Conditions\Visibility_Rules;

	$tabs = Acf_Source::settings( 'digitalisimo-accordion', array( 'digitalisimo_acf_field' => 'field_faq', 'digitalisimo_acf_title' => 'q', 'digitalisimo_acf_content' => 'a' ) );
	check_native( 1 === count( $tabs['tabs'] ) && 'Pregunta' === $tabs['tabs'][0]['tab_title'] && 'Respuesta' === $tabs['tabs'][0]['tab_content'], 'El acordeón toma sus elementos del repetidor de ACF y omite filas vacías.' );
	check_native( null === Acf_Source::settings( 'digitalisimo-accordion', array() ), 'Sin campo ACF el widget conserva sus elementos.' );
	$list = Acf_Source::settings( 'icon-list', array( 'digitalisimo_acf_field' => 'field_faq', 'digitalisimo_acf_title' => 'q', 'digitalisimo_acf_text' => 'a' ) );
	check_native( '<strong>Pregunta</strong> Respuesta' === $list['icon_list'][0]['text'], 'La lista de iconos separa título y texto.' );

	$rule  = array( 'rule_key' => 'authentication', 'rule_operator' => 'is', 'rule_authentication_value' => 'login' );
	$hide  = array( 'digitalisimo_visibility_enable' => 'yes', 'digitalisimo_visibility_to' => 'show', 'digitalisimo_visibility_rules' => array( $rule ) );
	$GLOBALS['digi_logged'] = false;
	$guest = Visibility_Rules::should_show( $hide, 'e1' );
	$GLOBALS['digi_logged'] = true;
	$user  = Visibility_Rules::should_show( $hide, 'e1' );
	check_native( null === Visibility_Rules::should_show( array(), 'e1' ) && in_array( 'authentication', Visibility_Rules::supported_keys(), true ) && is_bool( $guest ) && is_bool( $user ) && $guest !== $user, 'Las reglas propias se evalúan con sus nombres y dependen de la sesión.' );
	$empty = Visibility_Rules::should_show( array( 'digitalisimo_visibility_enable' => 'yes', 'digitalisimo_visibility_rules' => array() ), 'e1' );
	check_native( false === $empty, 'Reglas activas sin condiciones ocultan el elemento.' );
	echo "DIGITALÍSIMO Elements: rasgos propios (origen ACF y visibilidad) validados.\n";
}
