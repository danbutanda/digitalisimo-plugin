<?php
/**
 * F7b, frente J · imprime `tests/fixtures/f7b-forms.html`: el formulario de `fixture-fields.php` pintado por el
 * Formulario real de PRO con los ganchos de F7b, dentro de una página con `frontend.min.css` del núcleo, la hoja del
 * formulario y la regla `[hidden]` del CSS del documento.
 *
 *   node <php-wasm-cli> tests/elements-f7b-forms/fixture.php > tests/fixtures/f7b-forms.html
 */
require __DIR__ . '/pro.php';
require __DIR__ . '/fixture-fields.php';
forms_boot( false, true );
\Digitalisimo\Elements\F7b\Forms\Module::init();
echo forms_fixture_page( forms_render( forms_widget( forms_fixture_fields() ) ) );
