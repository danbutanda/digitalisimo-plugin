// Revisa las páginas de brand-b1-race.sh: cada CSS y JS del plugin o de los originales responde 200, y el HTML y el CSS
// del núcleo (frontend*.css) usan las mismas clases. Uso: node check.cjs <base> <carpeta> <página>…
const fs = require('fs');
const [base, dir, ...pages] = process.argv.slice(2);
(async () => {
  let bad = 0;
  for (const name of pages) {
    const html = fs.readFileSync(`${dir}/${name}.html`, 'utf8');
    const urls = [...new Set([...html.matchAll(/(?:href|src)=["']([^"']+\.(?:css|js)(?:\?[^"']*)?)["']/g)].map((m) => m[1].replace(/&#038;|&amp;/g, '&')))]
      .filter((u) => /\/(plugins\/digitalisimo-elements|uploads\/digitalisimo-elements)\//.test(u));
    const htmlKind = /class="[^"]*\belementor-(?:element|widget)/.test(html) ? 'elementor' : /class="[^"]*\bdg-(?:element|widget)/.test(html) ? 'dg' : '?';
    const broken = [];
    let cssKind = '';
    for (const u of urls) {
      const url = u.startsWith('http') ? u : base + u;
      const r = await fetch(url);
      const body = await r.text();
      if (r.status !== 200) broken.push(`${r.status} ${u}`);
      if (/\/frontend(\.min)?\.css/.test(u)) cssKind = /\.elementor-(?:element|widget)/.test(body) ? 'elementor' : /\.dg-(?:element|widget)/.test(body) ? 'dg' : cssKind;
    }
    const nocache = /no-cache|no-store/.test(html) ? '' : '';
    const ok = !broken.length && htmlKind !== '?' && (!cssKind || cssKind === htmlKind);
    if (!ok) bad++;
    console.log(`${ok ? 'ok   ' : 'FALLA'} ${name}: HTML ${htmlKind}, CSS del núcleo ${cssKind || 'no pedido'}, ${urls.length} assets${broken.length ? ', rotos: ' + broken.slice(0, 5).join(' · ') : ''}${nocache}`);
  }
  console.log(bad ? `${bad} páginas rotas` : 'Ninguna página rota');
  process.exit(bad ? 1 : 0);
})();
