(function (w) {
	w.addEventListener('elementor/frontend/init', function () {
		var f = w.elementorFrontend, s = f && f.storage;
		if (!s || w.elementorProFrontend || f.isEditMode() || f.isWPPreviewMode()) {
			return;
		}
		s.set('pageViews', (s.get('pageViews') || 0) + 1);
		if (!s.get('activeSession', { session: true })) {
			s.set('activeSession', true, { session: true });
			s.set('sessions', (s.get('sessions') || 0) + 1);
		}
	});
}(window));
