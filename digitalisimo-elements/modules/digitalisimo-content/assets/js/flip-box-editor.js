/* DIGITALÍSIMO Elements · Tarjeta giratoria en el editor (C2, frente B). Sólo se encola en el editor
   (Module::editor_scripts). Como el editor de PRO: al abrir las secciones del reverso («Reverso» en Contenido o en Estilo)
   muestra el reverso en el lienzo (clase `digi-flip-box--flipped` en el widget, sin transición) y al abrir otra sección
   vuelve al anverso. */
((w) => {
	const TYPE = 'digitalisimo-flip-box';
	const BACK = ['section_side_b_content', 'section_style_b'];
	const activated = (name, editor) => {
		const view = editor && editor.getOption ? editor.getOption('editedElementView') : null;
		if (!view || !view.model || TYPE !== view.model.get('widgetType') || !view.el) return;
		view.el.classList.toggle('digi-flip-box--flipped', BACK.includes(name));
	};
	const boot = () => {
		const e = w.elementor;
		if (e && e.channels && e.channels.editor) e.channels.editor.on('section:activated', activated);
	};
	if (w.elementor && w.elementor.channels) boot(); else w.addEventListener('elementor/init', boot, { once: true });
})(window);
