#!/usr/bin/env python3
"""Genera docs/element-pack-decisions.md desde docs/migration-map.json (no editar la tabla a mano)."""
import json
from collections import Counter
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
entries = json.loads((ROOT / 'docs/migration-map.json').read_text())['entries']
rows, counts, legacy = [], Counter(), Counter()
for wid, e in sorted(entries.items()):
    decision = e.get('decision', '')
    state = e.get('legacy_documents') or ('adaptador publicado' if e.get('legacy_adapter') else '')
    counts[decision] += 1
    legacy[state] += 1
    target = e.get('decision_target') or e.get('target') or ''
    reason = e.get('decision_reason') or e.get('status', '')
    rows.append('| `%s` | %s | %s | %s | %s |' % (wid, decision, ('`%s`' % target) if target else '—', state or '—', reason.replace('|', '\\|')))
out = ['# Element Pack → DIGITALÍSIMO Elements · decisiones por ID', '',
       'Tabla generada por `scripts/legacy-decisions-doc.py` desde `docs/migration-map.json`; `tests/elements-legacy-decisions.mjs` exige una decisión válida en cada uno de los %d IDs del inventario.' % len(entries), '',
       'Decisiones: ' + ', '.join('%s %d' % (k, v) for k, v in sorted(counts.items())) + '.', '',
       'Documentos heredados: ' + ', '.join('%s %d' % (k or 'sin dato', v) for k, v in sorted(legacy.items())) + '.', '',
       '- **ADAPTAR**: los documentos `bdt-*` siguen funcionando sin Element Pack sobre el destino (adaptador con mapa, estilos heredados y migración reversible).',
       '- **RECONSTRUIR**: widget propio nuevo; los documentos heredados pasan a él con adaptador.',
       '- **COMPARTIR**: el contenido nuevo usa un widget existente; los documentos heredados no se convierten (motivo en la tabla).',
       '- **DESCARTAR**: no se reproduce; el motivo explica la dependencia (API externa, servicio de Element Pack, comportamiento de página) y la alternativa.', '',
       '| ID | Decisión | Destino | Documentos heredados | Motivo o estado |', '| --- | --- | --- | --- | --- |'] + rows
(ROOT / 'docs/element-pack-decisions.md').write_text('\n'.join(out) + '\n', encoding='utf-8')
print('\n'.join(out[4:7]))
