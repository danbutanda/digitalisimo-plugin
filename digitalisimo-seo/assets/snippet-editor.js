document.addEventListener('click', (event) => {
	const save = event.target.closest('[data-snippet-save]');
	if (save) {
	  event.preventDefault();
	  save.disabled = true;
	  save.textContent = 'Guardando…';
	  if (window.wp && window.wp.data && window.wp.data.dispatch) window.wp.data.dispatch('core/editor').savePost();
	  else { const form = document.getElementById('post'); if (form) form.requestSubmit(); }
	  window.setTimeout(() => { save.disabled = false; save.textContent = 'Guardar cambios'; }, 1200);
	  return;
	}
  const expand = event.target.closest('[data-snippet-expand]');
  const close = event.target.closest('[data-snippet-modal-close], .digitalisimo-snippet-modal-backdrop');
  if (!expand && !close) return;
  event.preventDefault();
  const editor = expand ? (expand.closest('.digitalisimo-snippet-editor') || document.getElementById(expand.dataset.snippetTarget || '')) : document.querySelector('.digitalisimo-snippet-editor.is-expanded');
  if (!editor) return;
  const isOpen = Boolean(expand) && !editor.classList.contains('is-expanded');
  if (isOpen) {
    const placeholder = document.createComment('digitalisimo-snippet-origin');
    editor.parentNode.insertBefore(placeholder, editor);
    editor.dataset.digitalisimoSnippetOrigin = 'open';
    editor.__digitalisimoSnippetPlaceholder = placeholder;
    const form = editor.closest('form') || document.getElementById('post');
    if (form && form.id) editor.querySelectorAll('input, select, textarea').forEach((field) => {
      field.dataset.digitalisimoOriginalForm = field.getAttribute('form') || '';
      field.setAttribute('form', form.id);
    });
    document.body.appendChild(editor);
  } else if (editor.__digitalisimoSnippetPlaceholder) {
    editor.__digitalisimoSnippetPlaceholder.parentNode.insertBefore(editor, editor.__digitalisimoSnippetPlaceholder);
    editor.__digitalisimoSnippetPlaceholder.remove();
    editor.querySelectorAll('input, select, textarea').forEach((field) => {
      const original = field.dataset.digitalisimoOriginalForm || '';
      if (original) field.setAttribute('form', original); else field.removeAttribute('form');
      delete field.dataset.digitalisimoOriginalForm;
    });
    delete editor.__digitalisimoSnippetPlaceholder;
    delete editor.dataset.digitalisimoSnippetOrigin;
  }
  editor.classList.toggle('is-expanded', isOpen);
  document.body.classList.toggle('digitalisimo-snippet-modal-open', isOpen);
  let backdrop = document.querySelector('.digitalisimo-snippet-modal-backdrop');
  if (isOpen && !backdrop) { backdrop = document.createElement('div'); backdrop.className = 'digitalisimo-snippet-modal-backdrop'; backdrop.setAttribute('aria-hidden', 'true'); document.body.appendChild(backdrop); }
  if (!isOpen && backdrop) backdrop.remove();
  if (isOpen) { expand.dataset.snippetModalClose = '1'; expand.setAttribute('aria-label', 'Cerrar ventana amplia'); expand.setAttribute('title', 'Cerrar ventana amplia'); expand.querySelector('[aria-hidden]').textContent = '×'; }
  else { const button = editor.querySelector('[data-snippet-expand]'); if (button) { button.dataset.snippetModalClose = ''; button.setAttribute('aria-label', 'Abrir editor de snippet en ventana amplia'); button.setAttribute('title', 'Abrir ventana amplia'); button.querySelector('[aria-hidden]').textContent = '⤢'; } }
});

document.addEventListener('keydown', (event) => { if ('Escape' === event.key && document.querySelector('.digitalisimo-snippet-editor.is-expanded')) document.querySelector('.digitalisimo-snippet-modal-backdrop')?.click(); });

const digitalisimoInitSnippetEditors = () => {
  document.querySelectorAll('.digitalisimo-snippet-editor').forEach((editor) => {
	if (editor.dataset.digitalisimoSnippetReady) return;
	editor.dataset.digitalisimoSnippetReady = '1';
    const tabs = editor.querySelectorAll('[data-snippet-tab]');
    const panels = editor.querySelectorAll('[data-snippet-panel]');
    const preview = (name) => editor.querySelector(`[data-snippet-preview="${name}"]`);
    const input = (name) => editor.querySelector(`[data-snippet-input="${name}"]`);
    const count = (name) => editor.querySelector(`[data-snippet-count="${name}"]`);
    const limits = { title: [50, 60], description: [120, 160], slug: [0, 75] };

    const current = (name) => {
      const field = input(name);
      const fallback = editor.dataset[`default${name.charAt(0).toUpperCase()}${name.slice(1)}`] || '';
      return field && field.value.trim() ? field.value.trim() : fallback;
    };
    const updateCount = (name, value) => {
      const target = count(name);
      if (!target) return;
      const [, maximum] = limits[name];
      target.textContent = `${value.length} / ${maximum}`;
      target.className = 'digitalisimo-snippet-count';
      if (value.length > maximum) target.classList.add('is-bad');
      else if (value.length < limits[name][0]) target.classList.add('is-warn');
      else target.classList.add('is-good');
    };
    const refresh = () => {
      const title = current('title');
      const description = current('description');
      const slug = input('slug') ? input('slug').value.trim().replace(/^\/+|\/+$/g, '') : '';
      const base = editor.dataset.siteUrl.replace(/\/$/, '');
      preview('title').textContent = title || 'Título de la publicación';
      preview('description').textContent = description || 'Descripción de la publicación';
      preview('url').textContent = slug ? `${base}/${slug}/` : base;
      updateCount('title', title);
      updateCount('description', description);
      updateCount('slug', slug);
    };

    tabs.forEach((tab) => tab.addEventListener('click', () => {
      const name = tab.dataset.snippetTab;
      tabs.forEach((item) => item.classList.toggle('is-active', item === tab));
      panels.forEach((panel) => panel.classList.toggle('is-active', panel.dataset.snippetPanel === name));
    }));
    ['title', 'description', 'slug'].forEach((name) => {
      const field = input(name);
      if (field) field.addEventListener('input', refresh);
    });
    refresh();
  });
};

if ('loading' === document.readyState) document.addEventListener('DOMContentLoaded', digitalisimoInitSnippetEditors);
else digitalisimoInitSnippetEditors();
