/**
 * Formulario propio en el editor (digitalisimo-form). El módulo de formularios del constructor ata sus ayudas del panel
 * al widget de PRO por su nombre («form»); esto las ata también al propio, sin copiarlas:
 * - `panel/open_editor/widget/form` (opciones de «Responder a» del correo y demás) se reenvía al abrir el propio;
 * - las integraciones (listas de Mailchimp, Drip, ActiveCampaign, GetResponse, ConvertKit, MailerLite y el aviso de
 *   reCAPTCHA) se crean otra vez con el nombre del propio, con la misma clase que usa el constructor;
 * - al elegir el tipo «Paso» en un campo que no es el primero, se inserta un paso al principio (como en el de PRO).
 */
( function () {
	'use strict';
	var NAME = 'digitalisimo-form';
	var INTEGRATIONS = [ 'mailchimp', 'recaptcha', 'drip', 'activecampaign', 'getresponse', 'convertkit', 'mailerlite' ];

	function start() {
		var forms = window.elementorPro && elementorPro.modules && elementorPro.modules.forms;
		elementor.hooks.addAction( 'panel/open_editor/widget/' + NAME, function ( panel, model, view ) {
			elementor.hooks.doAction( 'panel/open_editor/widget/form', panel, model, view );
		} );
		if ( forms ) {
			INTEGRATIONS.forEach( function ( key ) {
				var module = forms[ key ];
				if ( module && 'function' === typeof module.constructor ) {
					try {
						forms[ key + 'Digitalisimo' ] = new module.constructor( NAME );
					} catch ( error ) {
						// Una integración que no se puede atar sigue funcionando en el envío; sólo pierde la ayuda del panel.
					}
				}
			} );
		}
		if ( window.$e && $e.modules && $e.modules.hookData && $e.hooks ) {
			var FirstStep = class extends $e.modules.hookData.After {
				getCommand() {
					return 'document/elements/settings';
				}
				getId() {
					return 'digitalisimo-form-fields-first-step';
				}
				getContainerType() {
					return 'repeater';
				}
				getConditions( args ) {
					var containers = args.containers || [ args.container ];
					return NAME === containers[ 0 ].parent.parent.model.get( 'widgetType' ) && 'step' === args.settings.field_type;
				}
				apply( args ) {
					( args.containers || [ args.container ] ).forEach( function ( container ) {
						if ( 'step' === container.parent.children[ 0 ].settings.get( 'field_type' ) ) {
							return;
						}
						$e.run( 'document/repeater/insert', {
							container: container.parent.parent,
							name: 'form_fields',
							model: { field_type: 'step' },
							options: { at: 0, external: true },
						} );
					} );
					return true;
				}
			};
			$e.hooks.registerDataAfter( new FirstStep() );
		}
	}

	if ( window.elementor && elementor.hooks ) {
		start();
	} else {
		// El editor emite `elementor/init` también como evento nativo de `window`.
		window.addEventListener( 'elementor/init', start, { once: true } );
	}
}() );
