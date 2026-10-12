/* Mantiene las actualizaciones de DIGITALÍSIMO en la lista de Plugins. */
(function () {
	'use strict';
	if (window.digitalisimoInlinePluginUpdate) return;
	window.digitalisimoInlinePluginUpdate = true;

	document.addEventListener('click', function (event) {
		if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
		var link = event.target.closest && event.target.closest('#bulk-action-form tr[data-plugin] a.update-link');
		if (!link) return;
		var row = link.closest('tr[data-plugin]');
		var file = row && row.getAttribute('data-plugin');
		if (!window.digitalisimoPluginUpdateFiles || !window.digitalisimoPluginUpdateFiles[file] || !window.wp || !wp.updates || typeof wp.updates.updatePlugin !== 'function') return;

		event.preventDefault();
		event.stopImmediatePropagation();
		if (link.classList.contains('updating-message') || link.classList.contains('button-disabled')) return;
		if (typeof wp.updates.maybeRequestFilesystemCredentials === 'function') wp.updates.maybeRequestFilesystemCredentials(event);
		if (window.jQuery) wp.updates.$elToReturnFocusToFromCredentialsModal = jQuery(row).find('.check-column input');
		wp.updates.updatePlugin({ plugin: file, slug: row.getAttribute('data-slug') });
	}, true);
})();
