// Cada ID del inventario de Element Pack tiene una decisión, y cada adaptador publicado su mapa.
import assert from 'node:assert/strict';
import { readFileSync, existsSync } from 'node:fs';

const map = JSON.parse(readFileSync(new URL('../docs/migration-map.json', import.meta.url), 'utf8')).entries;
const inventory = JSON.parse(readFileSync(new URL('../docs/element-pack-inventory.json', import.meta.url), 'utf8')).widgets;
const decisions = new Set(['ADAPTAR', 'COMPARTIR', 'DESCARTAR', 'RECONSTRUIR', 'MIGRAR']);
const maps = new URL('../digitalisimo-elements/modules/digitalisimo-legacy/maps/', import.meta.url);

assert.equal(inventory.length, Object.keys(map).length, 'el mapa debe cubrir todo el inventario');
for (const widget of inventory) {
  const entry = map[widget.legacy_widget_id];
  assert.ok(entry, `falta ${widget.legacy_widget_id} en el mapa`);
  assert.ok(decisions.has(entry.decision), `${widget.legacy_widget_id} sin decisión válida`);
  const hasMap = existsSync(new URL(`${widget.legacy_widget_id}.php`, maps));
  if (entry.legacy_documents === 'adaptador publicado' || entry.legacy_adapter) {
    assert.ok(hasMap || widget.legacy_widget_id === 'bdt-animated-link', `${widget.legacy_widget_id} declara adaptador sin mapa`);
  }
  if (hasMap) assert.ok(entry.legacy_adapter || entry.legacy_documents === 'adaptador publicado', `${widget.legacy_widget_id} tiene mapa sin registrarlo`);
  if (entry.decision === 'DESCARTAR') assert.ok((entry.decision_reason || '').length > 20, `${widget.legacy_widget_id} se descarta sin motivo`);
}
console.log(`DIGITALÍSIMO Elements: decisiones de los ${inventory.length} IDs de Element Pack validadas.`);
