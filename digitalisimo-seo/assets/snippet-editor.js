document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.digitalisimo-snippet-editor').forEach((editor) => {
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
});
