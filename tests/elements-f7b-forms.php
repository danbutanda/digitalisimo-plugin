<?php
/**
 * F7b, frente J · lógica condicional de los formularios (C9, D20, V.9, C1-2, C2-12, criterio 11).
 *
 * Con el núcleo real (`tests/elements-design/core.php`) y el **Formulario real de PRO** (`tests/elements-f7b-forms/pro.php`):
 *
 * 0. Vectores compartidos de `tests/fixtures/f7b-form-rules.json` (normalización, números, operadores, cascada), los
 *    mismos que `tests/elements-f7b-forms.mjs` pasa por `forms-conditions.js`.
 * 1. Saneado de las reglas (`Rules::from_item()`): interruptor, tipos excluidos, topes, IDs, operadores desconocidos.
 * 2. Arranque: qué se engancha en el front con y sin la bandera, en el envío y en el editor; guarda de símbolos.
 * 3. Controles en el repetidor `form_fields` (repetidor dentro del repetidor): posición, `style_transfer`, sin `default`
 *    que cambie nada, condiciones; idempotentes; los ajustes anidados pasan por `get_settings_for_display()`.
 * 4. Pintado: sin campos condicionales, el mismo HTML que sin F7b; con ellos, `data-digi-cond`, estado inicial con los
 *    valores por defecto (`hidden` + `disabled` en todos los controles, también en cada radio), script encolado; la
 *    fixture de Chrome es el HTML de hoy.
 * 5. CSS del documento: la regla `[hidden]` una sola vez y sólo con un formulario condicional.
 * 6. Envío real por `Ajax_Handler::ajax_send_form()`: «obligatorio, oculto y vacío» se envía; «obligatorio, visible y
 *    vacío» falla; un campo oculto con valor no llega a las acciones (campos, datos enviados, atajos, datos con
 *    formato); otros errores se conservan; una subida oculta.
 */
require __DIR__ . '/elements-f7b-forms/pro.php';
require __DIR__ . '/elements-f7b-forms/fixture-fields.php';

use Digitalisimo\Elements\F7b\Forms\Controls;
use Digitalisimo\Elements\F7b\Forms\Module as Forms;
use Digitalisimo\Elements\F7b\Forms\Render;
use Digitalisimo\Elements\F7b\Forms\Rules;
use Digitalisimo\Elements\F7b\Forms\Validation;
use Digitalisimo\Elements\F7b\Hidden_Rule;

$checks = 0;
$check  = static function ( $ok, string $message ) use ( &$checks ): void {
	++$checks;
	if ( ! $ok ) {
		throw new RuntimeException( $message );
	}
};

// ── 0 · Vectores compartidos ─────────────────────────────────────────────────────────────────────────────────────
$vectors = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/f7b-form-rules.json' ), true );
$check( is_array( $vectors ) && 10 <= count( $vectors['normalize'] ?? array() ) && 30 <= count( $vectors['rules'] ?? array() ) && 10 <= count( $vectors['forms'] ?? array() ), 'tests/fixtures/f7b-form-rules.json debe tener los vectores compartidos.' );
foreach ( $vectors['normalize'] as $i => $case ) {
	$check( $case['out'] === Rules::values( $case['in'] ), 'normalize #' . $i . ': ' . json_encode( $case['in'], JSON_UNESCAPED_UNICODE ) . ' → ' . json_encode( Rules::values( $case['in'] ), JSON_UNESCAPED_UNICODE ) );
}
foreach ( $vectors['number'] as $i => $case ) {
	$got = Rules::number( $case['in'] );
	$check( null === $case['out'] ? null === $got : ( null !== $got && abs( (float) $case['out'] - $got ) < 1e-12 ), 'number #' . $i . ': ' . $case['in'] . ' → ' . var_export( $got, true ) );
}
foreach ( $vectors['rules'] as $i => $case ) {
	$check( $case['expect'] === Rules::test( $case['rule'], Rules::values( $case['value'] ) ), 'rules #' . $i . ': ' . json_encode( $case, JSON_UNESCAPED_UNICODE ) );
}
foreach ( $vectors['forms'] as $case ) {
	$got = Rules::hidden( $case['defs'], $case['values'] );
	sort( $got );
	$check( $case['hidden'] === $got, 'forms «' . $case['name'] . '»: ' . json_encode( $got ) );
}

// ── 1 · Saneado ──────────────────────────────────────────────────────────────────────────────────────────────────
$base = array( 'custom_id' => 'rfc', 'field_type' => 'text', 'digitalisimo_conditional' => 'yes', 'digitalisimo_rules' => array( array( 'field' => 'tipo', 'operator' => 'is', 'value' => 'Empresa' ) ) );
$check( array( 'i' => 'rfc', 'l' => 'all', 'r' => array( array( 'tipo', 'is', 'Empresa' ) ) ) === Rules::from_item( $base ), 'Reglas saneadas de un campo condicional.' );
$check( null === Rules::from_item( array( 'digitalisimo_conditional' => '' ) + $base ), 'Interruptor apagado: sin reglas.' );
$check( null === Rules::from_item( array( 'digitalisimo_conditional' => 'true' ) + $base ), 'Sólo «yes» enciende el interruptor (la aguja de Flags).' );
foreach ( Rules::EXCLUDED as $type ) {
	$check( null === Rules::from_item( array( 'field_type' => $type ) + $base ), 'Sin reglas en el tipo ' . $type . ' (C2-12 d).' );
}
$check( array( 'recaptcha', 'recaptcha_v3', 'honeypot', 'step', 'hidden' ) === Rules::EXCLUDED, 'Los cinco tipos excluidos de V.9.' );
$check( 'any' === Rules::from_item( array( 'digitalisimo_logic' => 'any' ) + $base )['l'] && 'all' === Rules::from_item( array( 'digitalisimo_logic' => 'cualquiera' ) + $base )['l'] && 'all' === Rules::from_item( array( 'digitalisimo_logic' => 'all' ) + $base )['l'], '«alguna» sólo con any; lo demás es «todas».' );
$messy = Rules::sanitize_rules(
	array(
		array( 'field' => 'ti po<script>', 'operator' => 'is', 'value' => "a\x00b" ),
		array( 'field' => '', 'operator' => 'is', 'value' => 'x' ),
		array( 'field' => 'a', 'operator' => 'between', 'value' => '1' ),
		array( 'field' => 'a', 'operator' => array( 'is' ), 'value' => array( 'x' ) ),
		array( 'field' => 'a', 'operator' => 'contains', 'value' => "\xC3\x28" ),
		array( 'field' => str_repeat( 'x', 90 ), 'operator' => 'less', 'value' => str_repeat( 'ñ', 250 ) ),
		'no es una regla',
	)
);
$check( array( 'tiposcript', 'is', 'ab' ) === $messy[0], 'El ID sólo con [A-Za-z0-9_] y sin caracteres de control en el valor: ' . json_encode( $messy[0] ) );
$check( array( '', '', 'x' ) === $messy[1], 'Sin campo, la regla nunca se cumple (operador vacío).' );
$check( array( 'a', '', '1' ) === $messy[2] && array( 'a', '', '' ) === $messy[3], 'Operador desconocido o no textual: vacío; valor no textual: vacío.' );
$check( array( 'a', 'contains', '' ) === $messy[4], 'Un valor que no es UTF-8 válido queda vacío.' );
$check( 64 === strlen( $messy[5][0] ) && Rules::MAX_VALUE === mb_strlen( $messy[5][2] ), 'Topes del ID (64) y del valor (200 caracteres).' );
$check( 6 === count( $messy ), 'Lo que no es una regla se ignora.' );
$many = array_fill( 0, 30, array( 'field' => 'a', 'operator' => 'empty', 'value' => '' ) );
$check( Rules::MAX_RULES === count( Rules::sanitize_rules( $many ) ), 'Como mucho 20 reglas por campo.' );
$check( '{"i":"rfc","l":"all","r":[["tipo","is","Empresa"]]}' === Rules::attribute( Rules::from_item( $base ) ), 'data-digi-cond en JSON compacto.' );
$check( array( 'tipo' => array( 'persona' ) ) === Rules::defaults( array( array( 'custom_id' => 'tipo', 'field_type' => 'radio', 'field_options' => "Persona|persona\nEmpresa|empresa", 'field_value' => 'persona' ) ) ), 'Valor inicial de un radio marcado por defecto.' );
$check( array( 's' => array( 'b' ) ) === Rules::defaults( array( array( 'custom_id' => 's', 'field_type' => 'select', 'field_options' => "A|a\nB|b", 'field_value' => 'b' ) ) ), 'select con valor por defecto.' );
$check( array( 's' => array( 'a' ) ) === Rules::defaults( array( array( 'custom_id' => 's', 'field_type' => 'select', 'field_options' => "A|a\nB|b" ) ) ), 'select simple sin valor: la primera opción, como el navegador.' );
$check( array( 's' => array( 'a', 'b' ) ) === Rules::defaults( array( array( 'custom_id' => 's', 'field_type' => 'select', 'allow_multiple' => 'true', 'field_options' => "A|a\nB|b\nC|c", 'field_value' => 'a,b' ) ) ), 'select múltiple: las marcadas.' );
$check( array( 'c' => array( 'on' ) ) === Rules::defaults( array( array( 'custom_id' => 'c', 'field_type' => 'acceptance', 'checked_by_default' => 'yes' ) ) ), 'Aceptación marcada por defecto: «on», como el navegador.' );
$check( array( 't' => array() ) === Rules::defaults( array( array( 'custom_id' => 't', 'field_type' => 'text', 'field_value' => '0' ) ) ), 'Un valor por defecto «0» no se pinta en PRO (empty()): vacío.' );

// ── 2 · Arranque ─────────────────────────────────────────────────────────────────────────────────────────────────
forms_boot( false, false );
$before = forms_hooks();
Forms::init();
Forms::init();
$check( $before === forms_hooks(), 'Front sin la bandera form_conditions: nada enganchado (criterio 2): ' . json_encode( array_diff_key( forms_hooks(), $before ) ) );

forms_boot( false, true );
$before = forms_hooks();
Forms::init();
$added = array_diff_assoc( forms_hooks(), $before );
$check( array( 'elementor_pro/forms/render/item' => 1, 'elementor/element/parse_css' => 1 ) === $added, 'Front con la bandera: sólo el pintado y la regla [hidden]: ' . json_encode( $added ) );
$check( 20 === has_filter( 'elementor_pro/forms/render/item', array( Render::class, 'item' ) ), 'El pintado corre con prioridad 20, después de PRO.' );
$check( false === has_action( 'elementor/element/form/section_form_fields/before_section_end', array( Controls::class, 'register' ) ), 'Fuera del editor no se registran los controles.' );

forms_boot( false, false );
$_POST['action'] = 'elementor_pro_forms_send_form';
Forms::init();
$check( 5 === has_action( 'elementor_pro/forms/validation', array( \Digitalisimo\Elements\F7b\Forms\Validation::class, 'validate' ) ), 'En el envío, la validación con prioridad 5 (V.9), aunque la bandera esté apagada.' );
$check( false === has_filter( 'elementor_pro/forms/render/item' ), 'En el envío sin bandera, nada del pintado.' );

forms_boot( true, false );
Forms::init();
$check( 20 === has_action( 'elementor/element/form/section_form_fields/before_section_end', array( Controls::class, 'register' ) ), 'En el editor, los controles (prioridad 20, después de los tipos de PRO).' );
$check( false !== has_action( 'elementor/editor/after_enqueue_scripts', array( Forms::class, 'editor_script' ) ), 'En el editor, su script de avisos.' );
Forms::editor_script();
$check( ! empty( $GLOBALS['dh']['scripts'][ Forms::EDITOR_HANDLE ]['enqueued'] ) && array( 'elementor-editor' ) === $GLOBALS['dh']['scripts'][ Forms::EDITOR_HANDLE ]['deps'], 'Script de avisos del editor encolado con elementor-editor.' );
$inline = implode( "\n", array_column( $GLOBALS['dh']['inline'], 1 ) );
$config = json_decode( (string) preg_replace( '/^.*window\.digitalisimoFormConditions=(\{.*\});.*$/s', '$1', $inline ), true );
$check( Forms::strings() === ( $config['i18n'] ?? null ) && false !== strpos( $config['i18n']['later'], '«%s» va después' ), 'Textos del editor traducibles en la configuración.' );
$check( is_file( __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-f7b/forms/' . Forms::EDITOR_SCRIPT ), 'El script del editor existe.' );

forms_boot( false, true );
$was = class_exists( 'Elementor\Controls_Manager' );
$check( $was, 'El núcleo está cargado.' );
$check( false === \Digitalisimo\Elements\F7b\Module::requires( array( 'ElementorPro\No\Existe::metodo' ), 'form_conditions' ) && isset( \Digitalisimo\Elements\F7b\Module::$missing['form_conditions'] ), 'La guarda anota lo que falta.' );

// Contrato con las piezas reales del frente A (se leen como texto: aquí corren sus dobles).
$f7b_dir = __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-f7b/';
if ( is_file( $f7b_dir . 'class-flags.php' ) ) {
	$check( false !== strpos( (string) file_get_contents( $f7b_dir . 'class-flags.php' ), "'form_conditions' => '\"digitalisimo_conditional\":\"yes\"'" ), 'Flags::NEEDLES busca "digitalisimo_conditional":"yes", lo que guarda el interruptor.' );
}
$check( false !== strpos( json_encode( array( 'digitalisimo_conditional' => 'yes' ) ), '"digitalisimo_conditional":"yes"' ), 'El interruptor guardado en _elementor_data contiene la aguja.' );
if ( is_file( $f7b_dir . 'class-hidden-rule.php' ) ) {
	$check( false !== strpos( (string) file_get_contents( $f7b_dir . 'class-hidden-rule.php' ), "const RULE = '" . Hidden_Rule::RULE . "';" ), 'La regla del doble (y de la fixture de Chrome) es la de Hidden_Rule::RULE.' );
}
$check( false !== strpos( (string) file_get_contents( $f7b_dir . 'class-module.php' ), "'Forms\\\\Module'" ), 'Forms\\Module está en F7b\\Module::BOOT.' );

// ── 3 · Controles ────────────────────────────────────────────────────────────────────────────────────────────────
forms_boot( true, false );
$plain        = forms_widget( forms_fixture_fields() );
$plain_fields = $plain->get_controls( 'form_fields' )['fields'];
$check( ! isset( $plain_fields[ Controls::TOGGLE ] ), 'Sin el gancho, el repetidor es el de PRO.' );
forms_boot( true, false );
Forms::init();
$widget = forms_widget( forms_fixture_fields() );
$fields = $widget->get_controls( 'form_fields' )['fields'];
$keys   = array_keys( $fields );
$at     = array_search( Controls::TOGGLE, $keys, true );
$check( array( 'css_classes', Controls::TOGGLE, Controls::LOGIC, Controls::RULES, Controls::NOTICE, 'form_fields_advanced_tab' ) === array_slice( $keys, $at - 1, 6 ), 'Al final de la pestaña Contenido: ' . implode( ',', array_slice( $keys, $at - 1, 6 ) ) );
$check( array_values( array_diff( $keys, array( Controls::TOGGLE, Controls::LOGIC, Controls::RULES, Controls::NOTICE ) ) ) === array_keys( $plain_fields ), 'Los campos de PRO quedan iguales y en su orden.' );
foreach ( array( Controls::TOGGLE, Controls::LOGIC, Controls::RULES, Controls::NOTICE ) as $name ) {
	$control = $fields[ $name ];
	$check( false === ( $control['style_transfer'] ?? null ), $name . ' declara style_transfer => false (V.12).' );
	$check( ! array_key_exists( 'default', $control ) || in_array( $control['default'], array( '', array() ), true ), $name . ' no tiene un default que cambie nada.' );
	$check( 'content' === $control['tab'] && 'form_fields_content_tab' === $control['inner_tab'] && 'form_fields_tabs' === $control['tabs_wrapper'], $name . ' en la pestaña Contenido del campo.' );
	$terms = $control['conditions']['terms'];
	$check( array( 'name' => 'field_type', 'operator' => '!in', 'value' => Rules::EXCLUDED ) === $terms[0], $name . ': nunca en los tipos excluidos.' );
	if ( Controls::TOGGLE !== $name ) {
		$check( array( 'name' => Controls::TOGGLE, 'operator' => '===', 'value' => 'yes' ) === $terms[1], $name . ': sólo con el interruptor encendido.' );
	}
}
$check( 'switcher' === $fields[ Controls::TOGGLE ]['type'] && 'yes' === $fields[ Controls::TOGGLE ]['return_value'], 'El interruptor guarda «yes» (la aguja "digitalisimo_conditional":"yes").' );
$check( array( '', 'any' ) === array_keys( $fields[ Controls::LOGIC ]['options'] ), 'Lógica: todas («», sin default) o alguna.' );
$rules_control = $fields[ Controls::RULES ];
$check( 'repeater' === $rules_control['type'] && true === $rules_control['is_repeater'] && false === $rules_control['prevent_empty'] && Rules::MAX_RULES === $rules_control['max_items'], 'Repetidor de reglas dentro del repetidor, sin fila obligatoria y con tope.' );
$check( array( '_id', 'field', 'operator', 'value' ) === array_keys( $rules_control['fields'] ), 'Cada regla: _id, field, operator, value.' );
foreach ( array( 'field', 'operator', 'value' ) as $name ) {
	$check( false === ( $rules_control['fields'][ $name ]['style_transfer'] ?? null ), 'La regla ' . $name . ' declara style_transfer => false.' );
}
$check( array_keys( Controls::operators() ) === Rules::OPERATORS && array_keys( $rules_control['fields']['operator']['options'] ) === Rules::OPERATORS, 'Los ocho operadores de D20.' );
$check( 'is' === $rules_control['fields']['operator']['default'] && true === $rules_control['fields']['operator']['save_default'], 'El operador de una regla nueva se guarda siempre (save_default).' );
$check( ! isset( $rules_control['fields']['field']['default'] ) || '' === $rules_control['fields']['field']['default'], 'El campo de la regla sin default.' );
$check( false !== strpos( $rules_control['title_field'], '{{ field }}' ) && false === strpos( $rules_control['title_field'], '{{{' ), 'El título de cada regla siempre escapado.' );
$check( false !== strpos( $fields[ Controls::NOTICE ]['raw'], 'se ven en el código de la página' ) && false !== strpos( $fields[ Controls::NOTICE ]['raw'], 'versión anterior' ) && false !== strpos( $fields[ Controls::NOTICE ]['raw'], 'digi-cond-warn' ), 'Aviso de privacidad (C2-12 c), de la vuelta atrás (V.11) y hueco de los avisos.' );
Controls::register( $widget );
$check( $keys === array_keys( $widget->get_controls( 'form_fields' )['fields'] ), 'Registrar dos veces no duplica nada.' );
$display = $widget->get_settings_for_display( 'form_fields' );
$check( array( array( '_id' => 'r1', 'field' => 'tipo', 'operator' => 'is', 'value' => 'Empresa' ) ) === $display[1]['digitalisimo_rules'], 'Las reglas anidadas pasan por get_settings_for_display(): ' . json_encode( $display[1]['digitalisimo_rules'] ) );
$off = forms_widget( array( array( 'custom_id' => 'x', 'field_type' => 'text', 'digitalisimo_rules' => array( array( 'field' => 'y', 'operator' => 'empty' ) ) ), array( 'custom_id' => 'z', 'field_type' => 'recaptcha', 'digitalisimo_conditional' => 'yes', 'digitalisimo_rules' => array( array( 'field' => 'y', 'operator' => 'empty' ) ) ) ), array(), 'f0002' );
$off_display = $off->get_settings_for_display( 'form_fields' );
$check( null === $off_display[0]['digitalisimo_rules'] && null === $off_display[1]['digitalisimo_rules'], 'Con el interruptor apagado o en un tipo excluido, las reglas quedan inactivas.' );
$check( ! $GLOBALS['dh']['wrong'], 'Sin avisos del núcleo: ' . implode( ' | ', $GLOBALS['dh']['wrong'] ) );

// ── 4 · Pintado ──────────────────────────────────────────────────────────────────────────────────────────────────
$no_rules = array(
	array( '_id' => 'b1', 'custom_id' => 'name', 'field_type' => 'text', 'field_label' => 'Nombre' ),
	array( '_id' => 'b2', 'custom_id' => 'email', 'field_type' => 'email', 'field_label' => 'Correo', 'required' => 'true' ),
	array( '_id' => 'b3', 'custom_id' => 'tema', 'field_type' => 'radio', 'field_label' => 'Tema', 'field_options' => "A|a\nB|b", 'required' => 'true' ),
);
forms_boot( false, false );
$html_plain = forms_render( forms_widget( $no_rules ) );
$cond_plain = forms_render( forms_widget( forms_fixture_fields() ) );
forms_boot( false, true );
Forms::init();
$html_f7b = forms_render( forms_widget( $no_rules ) );
$check( $html_plain === $html_f7b, 'Un formulario sin campos condicionales pinta exactamente lo mismo (criterio 1).' );
$check( empty( $GLOBALS['dh']['scripts'][ Render::HANDLE ]['enqueued'] ), 'Sin campos condicionales, el script no se encola.' );
$check( false === strpos( $cond_plain, 'data-digi-cond' ) && false === strpos( $cond_plain, 'hidden="hidden"' ), 'Con la bandera apagada, un formulario con reglas pinta como en 4.3.0.168.' );

$html = forms_render( forms_widget( forms_fixture_fields() ) );
$check( ! empty( $GLOBALS['dh']['scripts'][ Render::HANDLE ]['enqueued'] ), 'Con campos condicionales, el script se encola.' );
$script = $GLOBALS['dh']['scripts'][ Render::HANDLE ];
$check( array( 'in_footer' => true, 'strategy' => 'defer' ) === $script['args'] && array() === $script['deps'] && false !== strpos( $script['src'], 'modules/digitalisimo-f7b/assets/js/forms-conditions.js' ), 'En el pie, defer y sin dependencias (ni jQuery).' );
$doc = new DOMDocument();
libxml_use_internal_errors( true );
$doc->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>' );
libxml_clear_errors();
$xp    = new DOMXPath( $doc );
$group = static function ( string $id ) use ( $xp ) {
	return $xp->query( '//div[contains(concat(" ", @class, " "), " elementor-field-group-' . $id . ' ")]' )->item( 0 );
};
$controls_of = static function ( $node ) use ( $xp ) {
	return iterator_to_array( $xp->query( './/input|.//select|.//textarea', $node ) );
};
$hidden_now = array();
foreach ( forms_fixture_fields() as $item ) {
	$node = $group( $item['custom_id'] );
	$check( null !== $node, 'Grupo de ' . $item['custom_id'] . '.' );
	$def = Rules::from_item( $item );
	if ( null === $def ) {
		$check( ! $node->hasAttribute( 'data-digi-cond' ) && ! $node->hasAttribute( 'hidden' ), $item['custom_id'] . ' sin reglas: ni data-digi-cond ni hidden.' );
		continue;
	}
	$check( Rules::attribute( $def ) === $node->getAttribute( 'data-digi-cond' ), $item['custom_id'] . ': data-digi-cond con las reglas saneadas.' );
	if ( $node->hasAttribute( 'hidden' ) ) {
		$hidden_now[] = $item['custom_id'];
		foreach ( $controls_of( $node ) as $control ) {
			$check( $control->hasAttribute( 'disabled' ), $item['custom_id'] . ': todos sus controles disabled al pintar (' . $control->getAttribute( 'name' ) . ').' );
		}
	} else {
		foreach ( $controls_of( $node ) as $control ) {
			$check( ! $control->hasAttribute( 'disabled' ), $item['custom_id'] . ' visible: controles activos.' );
		}
	}
}
sort( $hidden_now );
$check( array( 'comentario', 'nota', 'plan', 'rfc', 'stack', 'urgente' ) === $hidden_now, 'Estado inicial con los valores por defecto (estado visible: pais = mx por defecto): ' . implode( ',', $hidden_now ) );
$plan = $controls_of( $group( 'plan' ) );
$check( 2 === count( $plan ) && $plan[0]->hasAttribute( 'required' ) && $plan[1]->hasAttribute( 'required' ) && $plan[0]->hasAttribute( 'disabled' ) && $plan[1]->hasAttribute( 'disabled' ), 'Radios: PRO pone required en cada opción (form-base.php:180-182) y el servidor deshabilita todas.' );
$check( false !== strpos( $html, 'data-digi-cond="{&quot;i&quot;:&quot;rfc&quot;' ), 'El JSON va escapado con esc_attr.' );
$evil = forms_render( forms_widget( array( array( '_id' => 'e1', 'custom_id' => 'x', 'field_type' => 'text', 'digitalisimo_conditional' => 'yes', 'digitalisimo_rules' => array( array( 'field' => 'y', 'operator' => 'is', 'value' => '"><script>alert(1)</script>' ) ) ) ), array(), 'f0003' ) );
$check( false === strpos( $evil, '<script>alert' ) && false !== strpos( $evil, '&lt;script&gt;' ), 'Un valor con HTML no sale del atributo.' );

// La fixture de la prueba en Chrome es el HTML que pinta hoy el Formulario real.
$fixture = (string) file_get_contents( __DIR__ . '/fixtures/f7b-forms.html' );
$check( forms_fixture_page( $html ) === $fixture, 'tests/fixtures/f7b-forms.html está al día (regenérala con tests/elements-f7b-forms/fixture.php).' );

// ── 5 · Regla [hidden] en el CSS del documento ───────────────────────────────────────────────────────────────────
forms_boot( false, true );
Forms::init();
$form_element = static function ( string $id, array $fields ): array {
	return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => 'form', 'settings' => array( 'form_fields' => $fields ), 'elements' => array() );
};
$css_one = design_css( array( array( 'id' => 'c1', 'elType' => 'container', 'settings' => array(), 'elements' => array( $form_element( 'f1', forms_fixture_fields() ), $form_element( 'f2', forms_fixture_fields() ) ) ) ), 21 );
$check( 1 === substr_count( $css_one, Hidden_Rule::RULE ), 'La regla [hidden] una sola vez en el CSS del documento con dos formularios condicionales.' );
$css_none = design_css( array( array( 'id' => 'c2', 'elType' => 'container', 'settings' => array(), 'elements' => array( $form_element( 'f3', $no_rules ) ) ) ), 22 );
$check( false === strpos( $css_none, '[hidden]' ), 'Sin formulario condicional, el CSS del documento no cambia.' );
forms_boot( false, false );
Forms::init();
$css_off = design_css( array( array( 'id' => 'c3', 'elType' => 'container', 'settings' => array(), 'elements' => array( $form_element( 'f4', forms_fixture_fields() ) ) ) ), 23 );
$check( false === strpos( $css_off, '[hidden]' ), 'Con la bandera apagada no se engancha parse_css.' );

// ── 6 · Envío real ───────────────────────────────────────────────────────────────────────────────────────────────
$sub_fields = array(
	array( '_id' => 's1', 'custom_id' => 'tipo', 'field_type' => 'radio', 'field_label' => 'Tipo', 'field_options' => "Persona|persona\nEmpresa|empresa", 'required' => 'true' ),
	array( '_id' => 's2', 'custom_id' => 'rfc', 'field_type' => 'text', 'field_label' => 'RFC', 'required' => 'true', 'digitalisimo_conditional' => 'yes', 'digitalisimo_rules' => array( array( '_id' => 'q1', 'field' => 'tipo', 'operator' => 'is', 'value' => 'empresa' ) ) ),
	array( '_id' => 's3', 'custom_id' => 'email', 'field_type' => 'email', 'field_label' => 'Correo', 'required' => 'true' ),
);
$submit = static function ( array $sent, array $fields = array(), array $files = array() ) use ( $sub_fields ) {
	forms_boot( false, true );
	$_POST['action'] = 'elementor_pro_forms_send_form';
	Forms::init();
	return forms_submit( $fields ? $fields : $sub_fields, $sent, $files );
};

$r = $submit( array( 'tipo' => 'persona', 'email' => 'ana@example.com' ) );
$check( true === $r['success'] && 1 === count( $r['runs'] ), '«Obligatorio, oculto y vacío» se envía y la acción corre (C1-2): ' . json_encode( $r['data'] ) );
$check( array( 'tipo', 'email' ) === array_keys( $r['runs'][0]['fields'] ), 'El campo oculto no está en el registro.' );
$check( array() === $r['handler']->errors, 'Su error de obligatorio se borró.' );

$r = $submit( array( 'tipo' => 'empresa', 'email' => 'ana@example.com' ) );
$check( false === $r['success'] && isset( $r['data']['errors']['rfc'] ) && ! $r['runs'], '«Obligatorio, visible y vacío» falla y no corre ninguna acción.' );

$r = $submit( array( 'tipo' => 'persona', 'rfc' => 'XAXX010101000', 'email' => 'ana@example.com' ) );
$run = $r['runs'][0] ?? array();
$check( true === $r['success'], 'Un campo oculto enviado igual no impide el envío.' );
$check( ! isset( $run['fields']['rfc'] ) && ! isset( $run['sent_data']['rfc'] ), 'Un campo oculto con valor no llega a la acción (ni en fields ni en sent_data).' );
$check( 'RFC:  · Correo: ana@example.com' === $run['shortcode'], 'El atajo [field id="rfc"] queda vacío: ' . $run['shortcode'] );
$check( false === strpos( json_encode( $run, JSON_UNESCAPED_UNICODE ), 'XAXX010101000' ), 'El valor colado no aparece en nada de lo que recibe la acción.' );

$r = $submit( array( 'tipo' => 'Empresa ', 'rfc' => 'XAXX010101000', 'email' => 'ana@example.com' ) );
$check( true === $r['success'] && 'XAXX010101000' === ( $r['runs'][0]['fields']['rfc']['value'] ?? '' ), 'Visible con valor: llega (mayúsculas y espacios normalizados como en el navegador).' );

$r = $submit( array( 'tipo' => 'persona', 'email' => '' ) );
$check( false === $r['success'] && isset( $r['data']['errors']['email'] ) && ! isset( $r['data']['errors']['rfc'] ), 'Otro error se conserva: no se fuerza el éxito, y el del oculto no aparece.' );

$r = $submit( array( 'tipo' => '', 'email' => 'ana@example.com' ) );
$check( false === $r['success'] && isset( $r['data']['errors']['tipo'] ) && ! isset( $r['data']['errors']['rfc'] ), 'El controlador obligatorio vacío falla; el campo que depende de él no añade error.' );

// Un validador anterior que añade un mensaje general: no se fuerza el éxito.
forms_boot( false, true );
$_POST['action'] = 'elementor_pro_forms_send_form';
Forms::init();
add_action(
	'elementor_pro/forms/validation',
	static function ( $record, $handler ) {
		$handler->add_error_message( 'Rechazado por otro validador.' );
	},
	1,
	2
);
$r = forms_submit( $sub_fields, array( 'tipo' => 'persona', 'email' => 'ana@example.com' ) );
$check( false === $r['success'] && 'Rechazado por otro validador.' === ( $r['data']['message'] ?? '' ), 'Con un mensaje de error de otro validador no se fuerza el éxito (PRO sigue con las acciones sólo cuando no quedan errores de campo, como siempre).' );

// Sin campos condicionales: igual que PRO.
$r = $submit( array( 'tipo' => 'persona' ), array( $sub_fields[0], array( '_id' => 's2', 'custom_id' => 'rfc', 'field_type' => 'text', 'required' => 'true' ), $sub_fields[2] ) );
$check( false === $r['success'] && isset( $r['data']['errors']['rfc'] ) && isset( $r['data']['errors']['email'] ), 'Sin reglas, los obligatorios vacíos fallan como siempre.' );

// Cadena: un campo oculto cuenta como vacío para el que depende de él, también en el servidor.
$chain = array_merge( $sub_fields, array( array( '_id' => 's4', 'custom_id' => 'giro', 'field_type' => 'text', 'required' => 'true', 'digitalisimo_conditional' => 'yes', 'digitalisimo_rules' => array( array( 'field' => 'rfc', 'operator' => 'not_empty' ) ) ) ) );
$r = $submit( array( 'tipo' => 'persona', 'rfc' => 'colado', 'email' => 'ana@example.com' ), $chain );
$check( true === $r['success'] && array( 'tipo', 'email' ) === array_keys( $r['runs'][0]['fields'] ), 'Cadena: rfc oculto (colado) cuenta como vacío y giro también se oculta.' );

// Subida oculta: sus nombres se leen de $_FILES en las dos formas; oculta, no llega ni se procesa.
$check( array( 'a.pdf', 'b.pdf' ) === ( static function () {
	$_FILES = array( 'form_fields' => array( 'name' => array( 'cv' => array( 'a.pdf', 'b.pdf' ) ) ) );
	return Validation::upload_names( 'cv' );
} )(), 'Nombres de una subida en la forma de PHP.' );
$check( array( 'c.pdf' ) === ( static function () {
	$_FILES = array( 'form_fields' => array( 'cv' => array( array( 'name' => 'c.pdf', 'error' => 0 ) ) ) );
	return Validation::upload_names( 'cv' );
} )(), 'Nombres de una subida tras fix_file_indices().' );
$upload_fields = array( $sub_fields[0], array( '_id' => 's5', 'custom_id' => 'cv', 'field_type' => 'upload', 'field_label' => 'CV', 'digitalisimo_conditional' => 'yes', 'digitalisimo_rules' => array( array( 'field' => 'tipo', 'operator' => 'is', 'value' => 'empresa' ) ) ), $sub_fields[2] );
$r = $submit( array( 'tipo' => 'persona', 'email' => 'ana@example.com' ), $upload_fields );
$check( true === $r['success'] && ! isset( $r['runs'][0]['fields']['cv'] ), 'Una subida oculta no llega a las acciones.' );
$dep = array( $sub_fields[0], array( '_id' => 's5', 'custom_id' => 'cv', 'field_type' => 'upload', 'field_label' => 'CV' ), array( '_id' => 's6', 'custom_id' => 'nota', 'field_type' => 'text', 'required' => 'true', 'digitalisimo_conditional' => 'yes', 'digitalisimo_rules' => array( array( 'field' => 'cv', 'operator' => 'not_empty' ) ) ), $sub_fields[2] );
$r = $submit( array( 'tipo' => 'persona', 'email' => 'ana@example.com' ), $dep );
$check( true === $r['success'] && ! isset( $r['runs'][0]['fields']['nota'] ), 'Sin archivo, la regla «no está vacío» sobre la subida no se cumple.' );

echo 'DIGITALÍSIMO Elements F7b · formularios condicionales: ' . $checks . " comprobaciones en verde (vectores, saneado, arranque, controles, pintado, CSS y envío real).\n";
