// Lista de proveedores (vendors.json) y su clasificación por host, compartida por la auditoría en el navegador.
// probe-http.php repite la misma lógica en PHP. Uso como programa: node vendors.cjs <raíz del repositorio> imprime
// vendors.json con la lista de vídeos del proveedor regenerada desde el código (el arnés la copia al sitio).
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const VIDEO_RE = 'youtube(-nocookie)?\\.com/(watch\\?v=|embed/|shorts/)[A-Za-z0-9_-]{11}|youtu\\.be/[A-Za-z0-9_-]{11}|vimeo\\.com/(video/)?[0-9]{6,}|dailymotion\\.com/(embed/)?video/x[a-z0-9]{4,}|dai\\.ly/x[a-z0-9]{4,}|videopress\\.com/(v|embed)/[A-Za-z0-9]{8}';

// Vídeos que el código del proveedor trae de ejemplo, de ayuda o como valor por defecto. Las bibliotecas de terceros
// (assets/lib) no cuentan: sus comentarios citan vídeos que no son del proveedor.
function scanVideos(root, dirs) {
  const existing = dirs.map((d) => path.join(root, d)).filter((d) => fs.existsSync(d));
  if (!existing.length) return [];
  let out = '';
  try {
    out = execFileSync('grep', ['-rIhoE', '--exclude-dir=lib', '--exclude-dir=node_modules', VIDEO_RE, ...existing], { encoding: 'utf8', maxBuffer: 64 << 20 });
  } catch (e) {
    out = e.stdout || '';
  }
  return [...new Set(out.split('\n').filter(Boolean).map((m) => m.split(/[=/]/).pop()))];
}

function load(root, file = path.join(__dirname, 'vendors.json')) {
  const V = JSON.parse(fs.readFileSync(file, 'utf8'));
  if (root) V.vendorVideos = [...new Set([...(V.vendorVideos || []), ...scanVideos(root, V.videoSources || [])])].sort();
  return V;
}

function parse(raw) {
  let s = String(raw || '').trim().replace(/\\\//g, '/').replace(/&amp;/g, '&');
  if (s.startsWith('//')) s = 'https:' + s;
  let u;
  try { u = new URL(s); } catch (e) { return null; }
  return /^(https?|wss?):$/.test(u.protocol) ? u : null;
}

const cache = new WeakMap();
function compiled(V) {
  if (!cache.has(V)) {
    cache.set(V, {
      paths: (V.paths || []).map((p) => ({ host: new RegExp(p.host, 'i'), re: new RegExp(p.re, 'i'), motivo: p.motivo })),
      video: new RegExp(V.videoHosts, 'i'),
      callers: Object.entries(V.callerHosts || {}).map(([h, r]) => ({ host: h, caller: new RegExp(r.caller), motivo: r.motivo })),
    });
  }
  return cache.get(V);
}

/**
 * ¿La URL va a un plugin de origen? Devuelve { host, domain, why } o null.
 * opts.content: la URL está en el contenido del usuario (frontal o vista previa): ahí YouTube o Vimeo son un servicio
 * y sólo cuenta un vídeo del proveedor. opts.caller: quién la pide (archivo o script); vacío = desconocido.
 */
function classify(V, raw, opts = {}) {
  const u = parse(raw);
  if (!u) return null;
  const C = compiled(V);
  const host = u.hostname.toLowerCase().replace(/\.$/, '');
  const under = (h) => host === h || host.endsWith('.' + h);
  for (const h of V.hosts) if (under(h)) return { host, domain: h, why: 'dominio del proveedor' };
  for (const h of V.future || []) if (under(h)) return { host, domain: h, why: 'dominio de un constructor de origen futuro' };
  for (const c of C.callers) {
    if (under(c.host) && (!opts.caller || c.caller.test(opts.caller))) return { host, domain: c.host, why: c.motivo };
  }
  let pq = u.pathname + u.search;
  try { pq = decodeURIComponent(pq); } catch (e) { /* se usa tal cual */ }
  for (const p of C.paths) if (p.host.test(host) && p.re.test(pq)) return { host, domain: host, why: p.motivo };
  if (C.video.test(host)) {
    const id = (V.vendorVideos || []).find((v) => u.href.includes(v));
    if (id) return { host, domain: host, why: `vídeo del proveedor (${id})` };
    if (!opts.content) return { host, domain: host, why: 'vídeo en la administración o el editor' };
  }
  return null;
}

// Regex de hosts candidatos para filtrar dentro de la página antes de clasificar en Node.
function candidateHosts(V) {
  const hosts = [...V.hosts, ...(V.future || []), ...Object.keys(V.callerHosts || {}), 'wordpress.org', 'w.org', 'googleapis.com'];
  const video = V.videoHosts.replace(/^\(\^\|\\\.\)\(/, '').replace(/\)\$$/, '');
  return `(^|\\.)(${hosts.map((h) => h.replace(/\./g, '\\.')).join('|')}|${video})$`;
}

module.exports = { load, classify, parse, candidateHosts, scanVideos };

if (require.main === module) process.stdout.write(JSON.stringify(load(process.argv[2] || path.resolve(__dirname, '../../..')), null, 1) + '\n');
