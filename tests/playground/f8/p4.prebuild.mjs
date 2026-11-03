// P4 de f8.sh: construye el kit sintético de los marcadores (widgets del origen que el sitio no puede instanciar) con el
// generador de los kits de K (tests/sources/kits/build.mjs → buildSpec). Uso: node p4.prebuild.mjs <carpeta de la corrida>
// Sintético: los ajustes salen de los fixtures de tests/legacy (casos `custom`), sin nada real ni del proveedor.
import { readFileSync, mkdirSync, writeFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { buildSpec } from '../../sources/kits/build.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const out = process.argv[2];
const fixture = (id, c) => { const f = JSON.parse(readFileSync(join(HERE, '../../legacy', `${id}.json`), 'utf8')); const s = { ...f[c] }; for (const k of Object.keys(s)) if (/^_(widget|page|section|options|known|expect|acf|family|comentario)/.test(k)) delete s[k]; return s; };
const widget = (id, type, settings) => ({ id, elType: 'widget', widgetType: type, settings, elements: [] });
const page = (n, title, widgets) => ({ content: [{ id: `p4c${n}`, elType: 'container', settings: {}, elements: widgets, isInner: false }], settings: [], metadata: [] });
const spec = {
  zip: 'f8-marcadores.zip',
  format: '3.0',
  assets: {},
  entries: {
    'manifest.json': {
      name: 'f8-marcadores', title: 'Marcadores de F8', description: 'Sintético', author: '', version: '3.0', elementor_version: '4.3.5', created: '2026-11-01 10:00:00', thumbnail: false, site: 'http://origen.test',
      'site-settings': { theme: false, globalColors: false, globalFonts: false, themeStyleSettings: false, generalSettings: false, experiments: false, customCode: false, customIcons: false, customFonts: false, classes: false, variables: false },
      plugins: [], templates: {},
      content: { page: {
        1401: { title: 'Marcadores en el editor', excerpt: '', doc_type: 'wp-page', thumbnail: false, url: 'http://origen.test/marcadores-editor/', terms: [] },
        1402: { title: 'Marcadores para Migrar', excerpt: '', doc_type: 'wp-page', thumbnail: false, url: 'http://origen.test/marcadores-migrar/', terms: [] },
      } },
      'wp-content': {},
    },
    'content/page/1401.json': page(1, 'editor', [widget('p4e001', 'bdt-accordion', fixture('bdt-accordion', 'custom')), widget('p4e002', 'prime-slider-general', fixture('prime-slider-general', 'custom'))]),
    'content/page/1402.json': page(2, 'migrar', [widget('p4m001', 'bdt-accordion', fixture('bdt-accordion', 'custom')), widget('p4m002', 'prime-slider-general', fixture('prime-slider-general', 'custom')), widget('p4m003', 'prime-slider-blog', fixture('prime-slider-blog', 'custom'))]),
  },
};
const built = buildSpec(spec, spec.zip);
mkdirSync(join(out, 'kit'), { recursive: true });
writeFileSync(join(out, 'kit', spec.zip), built.zip);
console.log(`kit de P4: ${spec.zip} (${built.zip.length} B)`);
