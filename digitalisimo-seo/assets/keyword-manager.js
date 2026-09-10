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
        const chip = document.createElement('div'); chip.className = 'digitalisimo-keyword-chip'; chip.draggable = true; chip.dataset.index = index;
        const label = document.createElement('button'); label.type = 'button'; label.className = 'digitalisimo-keyword-label'; label.textContent = word; label.title = 'Editar palabra clave';
        label.addEventListener('click', () => { const next = window.prompt('Editar palabra clave', words[index]); if (next && next.trim()) { words[index] = next.trim(); sync(); render(); } });
        const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'digitalisimo-keyword-remove'; remove.textContent = '×'; remove.title = 'Eliminar palabra clave'; remove.addEventListener('click', () => { words.splice(index, 1); sync(); render(); });
        chip.append(label, remove); chips.appendChild(chip);
        chip.addEventListener('dragstart', (event) => event.dataTransfer.setData('text/plain', String(index)));
        chip.addEventListener('dragover', (event) => event.preventDefault());
        chip.addEventListener('drop', (event) => { event.preventDefault(); const from = Number(event.dataTransfer.getData('text/plain')); const moved = words.splice(from, 1)[0]; words.splice(index, 0, moved); sync(); render(); });
      });
    };
    const addWord = () => { const next = input.value.trim(); if (!next) return; if (words.length >= limit) { window.alert(`Máximo ${limit} palabras clave.`); return; } if (!words.includes(next)) words.push(next); input.value = ''; sync(); render(); };
    add.addEventListener('click', addWord); input.addEventListener('keydown', (event) => { if (event.key === 'Enter') { event.preventDefault(); addWord(); } }); render();
  });
});
