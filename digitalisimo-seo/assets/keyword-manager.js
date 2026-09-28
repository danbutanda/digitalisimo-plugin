document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.digitalisimo-keyword-manager').forEach((manager) => {
    const chips = manager.querySelector('.digitalisimo-keyword-chips');
    const input = manager.querySelector('.digitalisimo-keyword-add input');
    const add = manager.querySelector('.digitalisimo-keyword-add button');
    const value = manager.querySelector('.digitalisimo-keyword-value');
    const limit = Number(manager.dataset.limit || 5);
    let words = value.value.split(',').map((word) => word.trim()).filter(Boolean);
    const sync = () => { value.value = words.join(', '); };
    const render = () => {
      chips.innerHTML = '';
      words.forEach((word, index) => {
        const chip = document.createElement('div'); chip.className = `digitalisimo-keyword-chip${index === 0 ? ' is-primary' : ''}`; chip.draggable = true; chip.dataset.index = index; chip.title = index === 0 ? 'Keyword principal. Arrastra otra ficha aquí para cambiarla.' : 'Arrastra esta ficha al inicio para convertirla en la keyword principal.';
        const label = document.createElement('button'); label.type = 'button'; label.className = 'digitalisimo-keyword-label'; label.textContent = word; label.title = 'Editar palabra clave';
        label.addEventListener('click', () => { const next = window.prompt('Editar palabra clave', words[index]); if (next && next.trim()) { words[index] = next.trim(); sync(); render(); } });
        const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'digitalisimo-keyword-remove'; remove.textContent = '×'; remove.title = 'Eliminar palabra clave'; remove.addEventListener('click', () => { words.splice(index, 1); sync(); render(); });
        if (index === 0) { const badge = document.createElement('span'); badge.className = 'digitalisimo-keyword-primary-badge'; badge.textContent = 'Principal'; chip.append(badge); }
        chip.append(label, remove); chips.appendChild(chip);
        chip.addEventListener('dragstart', (event) => { chip.classList.add('is-dragging'); event.dataTransfer.effectAllowed = 'move'; event.dataTransfer.setData('text/plain', String(index)); });
        chip.addEventListener('dragend', () => chip.classList.remove('is-dragging'));
        chip.addEventListener('dragover', (event) => event.preventDefault());
        chip.addEventListener('drop', (event) => { event.preventDefault(); const from = Number(event.dataTransfer.getData('text/plain')); if (!Number.isInteger(from) || from === index) return; const moved = words.splice(from, 1)[0]; words.splice(index, 0, moved); sync(); render(); });
      });
    };
    const addWord = () => { const next = input.value.trim(); if (!next) return; if (words.length >= limit) { window.alert(`Máximo ${limit} palabras clave.`); return; } if (!words.includes(next)) words.push(next); input.value = ''; sync(); render(); };
    add.addEventListener('click', addWord); input.addEventListener('keydown', (event) => { if (event.key === 'Enter') { event.preventDefault(); addWord(); } }); render();

    const generator = manager.querySelector('.digitalisimo-keyword-ai-generate');
    const result = manager.querySelector('.digitalisimo-keyword-ai-result');
    const editorContent = () => (window.wp?.data?.select('core/editor')?.getEditedPostContent?.() || '');
    const editorTitle = () => (window.wp?.data?.select('core/editor')?.getEditedPostAttribute?.('title') || document.querySelector('#title')?.value || '');
    const applyExcerpt = (text) => {
      if (window.wp?.data?.dispatch) window.wp.data.dispatch('core/editor').editPost({ excerpt: text });
      const field = document.querySelector('#excerpt, textarea[name="excerpt"]'); if (field) { field.value = text; field.dispatchEvent(new Event('input', { bubbles: true })); }
    };
    const applyMeta = (text) => {
      const field = document.querySelector('[name="digitalisimo_native_description"]');
      if (!field) return;
      field.value = text; field.dispatchEvent(new Event('input', { bubbles: true })); field.dispatchEvent(new Event('change', { bubbles: true }));
    };
    const button = (label, className, handler) => { const b = document.createElement('button'); b.type = 'button'; b.className = className; b.textContent = label; b.addEventListener('click', handler); return b; };
    const renderAi = (data) => {
      result.innerHTML = '';
      const keywordTitle = document.createElement('strong'); keywordTitle.textContent = 'Keywords alternativas'; result.append(keywordTitle);
      const keywordList = document.createElement('div'); keywordList.className = 'digitalisimo-keyword-ai-chips';
      (data.keywords || []).forEach((word) => keywordList.append(button(`+ ${word}`, 'digitalisimo-keyword-ai-chip', () => { if (words.length >= limit) return window.alert(`Máximo ${limit} palabras clave.`); if (!words.includes(word)) { words.push(word); sync(); render(); } })));
      result.append(keywordList);
      [['Meta descripción', data.meta_description, applyMeta], ['Descripción corta', data.excerpt, applyExcerpt]].forEach(([label, text, apply]) => {
        if (!text) return;
        const card = document.createElement('div'); card.className = 'digitalisimo-keyword-ai-copy';
        const heading = document.createElement('strong'); heading.textContent = label;
        const paragraph = document.createElement('p'); paragraph.textContent = text;
        card.append(heading, paragraph, button(`Usar ${label.toLowerCase()}`, 'button button-secondary', () => apply(text))); result.append(card);
      });
    };
    if (generator) generator.addEventListener('click', async () => {
      const keyword = words[0] || input.value.trim();
      if (!keyword) { window.alert('Define primero la keyword principal.'); return; }
      if (!window.digitalisimoSeoAi?.url) { result.textContent = 'No se pudo iniciar la herramienta IA.'; return; }
      generator.disabled = true; generator.textContent = 'Generando…'; result.textContent = 'Analizando el contenido actual…';
      try {
        const response = await fetch(window.digitalisimoSeoAi.url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.digitalisimoSeoAi.nonce }, body: JSON.stringify({ post_id: Number(manager.dataset.postId), keyword, title: editorTitle(), content: editorContent() }) });
        const data = await response.json(); if (!response.ok) throw new Error(data.message || 'No fue posible generar sugerencias.'); renderAi(data);
      } catch (error) { result.textContent = error.message || 'No fue posible generar sugerencias.'; }
      finally { generator.disabled = false; generator.textContent = 'Generar sugerencias con IA'; }
    });
  });
});
