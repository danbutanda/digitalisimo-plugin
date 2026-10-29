<?php
/**
 * F7b, frente J · campos del formulario de `tests/fixtures/f7b-forms.html` (prueba en Chrome) y de
 * `tests/elements-f7b-forms.php`, que comprueba que el HTML de la fixture es el que pinta hoy el Formulario real.
 *
 * - `tipo` (radios obligatorios, sin reglas) controla `rfc` (empresa), `plan` (persona, radios obligatorios) y `nota`
 *   (HTML, «no está vacío»).
 * - `intereses` (casillas múltiples) controla `stack` (`select` obligatorio, «contiene codigo»).
 * - `presupuesto` (número) controla `urgente` (aceptación obligatoria, «mayor que 1000») y `comentario` (área de texto,
 *   «alguna»: empresa o más de 5000).
 * - `pais` (`select` con `mx` por defecto) controla `estado` (visible al cargar: estado inicial con los valores por
 *   defecto).
 */
function forms_fixture_fields(): array {
	$rule = static function ( string $field, string $operator, string $value = '' ): array {
		static $n = 0;
		return array( '_id' => 'r' . ( ++$n ), 'field' => $field, 'operator' => $operator, 'value' => $value );
	};
	$cond = static function ( array $rules, string $logic = '' ) {
		return array( 'digitalisimo_conditional' => 'yes', 'digitalisimo_logic' => $logic, 'digitalisimo_rules' => $rules );
	};
	return array(
		array( '_id' => 'a01', 'custom_id' => 'tipo', 'field_type' => 'radio', 'field_label' => 'Tipo', 'field_options' => "Persona|persona\nEmpresa|empresa", 'required' => 'true' ),
		array( '_id' => 'a02', 'custom_id' => 'rfc', 'field_type' => 'text', 'field_label' => 'RFC', 'required' => 'true' ) + $cond( array( $rule( 'tipo', 'is', 'Empresa' ) ) ),
		array( '_id' => 'a03', 'custom_id' => 'plan', 'field_type' => 'radio', 'field_label' => 'Plan', 'field_options' => "Básico|basico\nCompleto|completo", 'required' => 'true' ) + $cond( array( $rule( 'tipo', 'is', 'persona' ) ) ),
		array( '_id' => 'a04', 'custom_id' => 'nota', 'field_type' => 'html', 'field_html' => '<p>Gracias por elegir un tipo.</p>' ) + $cond( array( $rule( 'tipo', 'not_empty' ) ) ),
		array( '_id' => 'a05', 'custom_id' => 'intereses', 'field_type' => 'checkbox', 'field_label' => 'Intereses', 'field_options' => "Diseño|diseno\nCódigo|codigo\nMarketing|marketing" ),
		array( '_id' => 'a06', 'custom_id' => 'stack', 'field_type' => 'select', 'field_label' => 'Lenguaje', 'field_options' => "Elige uno|\nPHP|php\nJavaScript|js", 'required' => 'true' ) + $cond( array( $rule( 'intereses', 'contains', 'codigo' ) ) ),
		array( '_id' => 'a07', 'custom_id' => 'presupuesto', 'field_type' => 'number', 'field_label' => 'Presupuesto' ),
		array( '_id' => 'a08', 'custom_id' => 'urgente', 'field_type' => 'acceptance', 'field_label' => 'Urgente', 'acceptance_text' => 'Es urgente', 'required' => 'true' ) + $cond( array( $rule( 'presupuesto', 'greater', '1000' ) ) ),
		array( '_id' => 'a09', 'custom_id' => 'comentario', 'field_type' => 'textarea', 'field_label' => 'Comentario', 'required' => 'true', 'rows' => 3 ) + $cond( array( $rule( 'tipo', 'is', 'empresa' ), $rule( 'presupuesto', 'greater', '5000' ) ), 'any' ),
		array( '_id' => 'a10', 'custom_id' => 'pais', 'field_type' => 'select', 'field_label' => 'País', 'field_options' => "México|mx\nChile|cl", 'field_value' => 'mx' ),
		array( '_id' => 'a11', 'custom_id' => 'estado', 'field_type' => 'text', 'field_label' => 'Estado', 'required' => 'true' ) + $cond( array( $rule( 'pais', 'is', 'mx' ) ) ),
		array( '_id' => 'a12', 'custom_id' => 'email', 'field_type' => 'email', 'field_label' => 'Correo', 'required' => 'true' ),
	);
}

/** Página de `tests/fixtures/f7b-forms.html` alrededor del HTML del formulario pintado. */
function forms_fixture_page( string $form ): string {
	return "<!doctype html>\n<html lang=\"es\">\n<head>\n<meta charset=\"utf-8\">\n<title>F7b · formulario condicional</title>\n"
		. "<!-- Generada por tests/elements-f7b-forms/fixture.php con el Formulario real de PRO (no editar a mano); tests/elements-f7b-forms.php comprueba que coincide. -->\n"
		. "<link rel=\"stylesheet\" href=\"../../digitalisimo-elements/elementor/assets/css/frontend.min.css\">\n"
		. "<link rel=\"stylesheet\" href=\"../../digitalisimo-elements/assets/css/widget-form.min.css\">\n"
		. '<style id="digi-hidden-rule">' . \Digitalisimo\Elements\F7b\Hidden_Rule::RULE . "</style>\n"
		. "</head>\n<body>\n<div class=\"elementor elementor-10\">\n"
		. "<div class=\"elementor-element elementor-element-f0001 elementor-widget elementor-widget-form\" data-id=\"f0001\" data-element_type=\"widget\" data-widget_type=\"form.default\">\n<div class=\"elementor-widget-container\">\n"
		. $form
		. "</div>\n</div>\n</div>\n<script src=\"../../digitalisimo-elements/modules/digitalisimo-f7b/assets/js/forms-conditions.js\" defer></script>\n</body>\n</html>\n";
}
