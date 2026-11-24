// Conductor del ensayo «el Asistente IA como agente» (lo llaman ia-assistant-agent.sh y ia-assistant-real.sh).
// Habla con la REST del Asistente como el navegador (sesión de admin, nonce wp_rest y el nonce propio) y manda la
// conversación real del caso plomerito (2026-11-23) mensaje por mensaje, siguiendo solo los turnos pausados
// (evento «continue»):
//   1 «En qué puedes ayudarme»
//   2 «créame una landing para blackfriday con la promoción del impact5000 a $1,000 MXN»
//   3 «que la promo sea para el purificador alcalinizador de 10000 a 7500»
//   4 «créala, la oferta es de hoy a fin de mes, el número es el de este sitio web, ya todo está en políticas»
//   5 «sí hazlo» (en el modo real, sólo si quedó algo pendiente)
// Modo simulado: comprueba la memoria (la petición tras 19 consultas aún lleva producto, precio y fecha), el contacto
// del sitio, el borrador creado sin confirmar, la confirmación escrita y el modelo. Modo real: guarda la conversación,
// la evaluación y una captura de la landing (con Chrome) en <dir>: conversacion.json, respuestas.md,
// evaluacion.json y landing.png.
// Uso: node run.cjs <puerto> <host> <dir> <simulado|real>
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');

const [port, host, dir, mode] = process.argv.slice(2);
const token = fs.readFileSync(`${dir}/probe/token.txt`, 'utf8').trim();
const ready = JSON.parse(fs.readFileSync(`${dir}/probe/ready.json`, 'utf8'));
const results = [];
let failures = 0;
const log = (line) => { fs.appendFileSync(`${dir}/result.txt`, `${line}\n`); console.log(line); };
const check = (id, ok, what, detail = '') => {
  results.push({ id, what, ok: !!ok, detail: ok ? '' : String(detail).slice(0, 800) });
  if (ok) log(`ok · ${id} · ${what}`); else { failures++; log(`FALLA · ${id} · ${what}${detail ? ` · ${String(detail).slice(0, 800)}` : ''}`); }
  return !!ok;
};
const cookies = {};
function request(method, p, { headers = {}, body } = {}) {
  return new Promise((resolve, reject) => {
    const data = body === undefined ? undefined : JSON.stringify(body);
    const all = { Host: host, Cookie: Object.entries(cookies).map(([k, v]) => `${k}=${v}`).join('; '), ...headers };
    if (data !== undefined) { all['Content-Type'] = 'application/json'; all['Content-Length'] = Buffer.byteLength(data); }
    const req = http.request({ host: '127.0.0.1', port: Number(port), method, path: p, headers: all, timeout: 40 * 60 * 1000 }, (res) => {
      for (const c of res.headers['set-cookie'] || []) { const [pair] = c.split(';'); const i = pair.indexOf('='); cookies[pair.slice(0, i)] = pair.slice(i + 1); }
      const chunks = [];
      res.on('data', (c) => chunks.push(c));
      res.on('end', () => { const text = Buffer.concat(chunks).toString('utf8'); let json = null; try { json = JSON.parse(text); } catch { /* no JSON */ } resolve({ status: res.statusCode, text, json }); });
    });
    req.on('error', reject);
    req.on('timeout', () => req.destroy(new Error(`tiempo agotado en ${p}`)));
    if (data !== undefined) req.write(data);
    req.end();
  });
}
const probe = async (what) => (await request('GET', `/?digi_ias=${what}&token=${token}`)).json;

const MESSAGES = [
  'En qué puedes ayudarme',
  'créame una landing para blackfriday con la promoción del impact5000 a $1,000 MXN',
  'que la promo sea para el purificador alcalinizador de 10000 a 7500',
  'créala, la oferta es de hoy a fin de mes, el número es el de este sitio web, ya todo está en políticas',
];

(async () => {
  const transcript = [];
  let nonces;
  let conversation = 0;
  const call = async (route, payload) => {
    const started = Date.now();
    const res = await request('POST', `/?rest_route=${encodeURIComponent(`/digitalisimo-ai/v1/assistant/${route}`)}`, { headers: { 'X-WP-Nonce': nonces.rest, 'X-Digitalisimo-Assistant': nonces.assistant }, body: payload });
    return { ...res, seconds: Math.round((Date.now() - started) / 1000) };
  };
  const send = async (text) => {
    const turn = { text, events: [], seconds: 0, requests: 0 };
    let res = await call('message', { conversation, message: text, provider: 'auto' });
    for (;;) {
      turn.requests++;
      turn.seconds += res.seconds;
      if (res.status !== 200 || !res.json || !Array.isArray(res.json.events)) { turn.error = `HTTP ${res.status}: ${res.text.slice(0, 400)}`; break; }
      turn.events.push(...res.json.events);
      conversation = res.json.conversation?.id || conversation;
      if (!res.json.events.some((e) => e.type === 'continue')) break;
      res = await call('continue', { conversation });
    }
    transcript.push(turn);
    const said = turn.events.filter((e) => e.type === 'message').map((e) => `${e.message.role === 'system' ? '[aviso] ' : ''}${e.message.text}`);
    const tools = turn.events.filter((e) => e.type === 'tool').map((e) => `${e.message.kind}:${e.message.ability}:${e.message.status}`);
    log(`\n» ${text}\n  (${turn.seconds} s, ${turn.requests} petición(es), ${tools.length} herramienta(s): ${tools.join(', ')})\n  ${said.join('\n  ').slice(0, 3000)}${turn.error ? `\n  ERROR ${turn.error}` : ''}`);
    return turn;
  };
  // Lo que quedó esperando confirmación al final del último mensaje.
  const pendingOf = (turns) => (turns.length ? turns[turns.length - 1].events.filter((e) => e.type === 'confirm').map((e) => e.action) : []);

  try {
    await probe('login');
    nonces = await probe('nonces');
    check('S0', nonces && nonces.user === ready.admin && nonces.rest && nonces.assistant, 'sesión de admin y los dos nonces del Asistente', JSON.stringify(nonces));
    check('G1', nonces.model === 'claude-opus-5-5', 'el Asistente usa claude-opus-5-5 por defecto con Anthropic', nonces.model);
    for (const text of MESSAGES) await send(text);
    const t4 = transcript[3];
    const pending = pendingOf(transcript);
    if (mode !== 'real' || pending.length) await send('sí hazlo');
    // El «sí hazlo» del caso no debe aplicar un cambio propuesto tras leer el sitio: queda en su tarjeta (la tienda
    // sigue igual) y sólo el botón lo aplica. En el modo simulado se pulsa después el botón para comprobarlo.
    let before = await probe('state');
    let confirmed = null;
    if (mode !== 'real' && pending.length) {
      const res = await call('confirm', { conversation, action: pending[0].id, decision: 'approve' });
      confirmed = { status: res.status, events: res.json?.events || [] };
      transcript.push({ text: '[botón «Confirmar»]', events: confirmed.events, seconds: res.seconds, requests: 1 });
    }
    let state = await probe('state');
    if (!state || state.error) {
      check('S1', false, 'la sonda devuelve lo creado', state ? state.error : 'respuesta vacía');
      state = { made: [], seen: {}, log: [], product: {} };
    }
    fs.writeFileSync(`${dir}/conversacion.json`, JSON.stringify({ mode, ready, transcript, state: { ...state, log: (state.log || []).slice(-80) } }, null, 1));
    // Respuestas legibles.
    const md = [`# Conversación del caso plomerito (${mode})`, ''];
    for (const t of transcript) {
      md.push(`## » ${t.text}`, '', `_${t.seconds} s · ${t.requests} petición(es)_`, '');
      for (const e of t.events) {
        if (e.type === 'message') md.push(`${e.message.role === 'system' ? '> **Aviso:** ' : ''}${e.message.text}`, '');
        if (e.type === 'tool') md.push(`- \`${e.message.kind}\` ${e.message.label || e.message.ability} → ${e.message.status}${e.message.summary ? ` · ${e.message.summary.slice(0, 160)}` : ''}`);
        if (e.type === 'confirm') md.push(`- **Pide confirmación:** ${e.action.label} (${e.action.kind})`);
      }
      md.push('');
    }
    md.push('## Lo creado', '');
    for (const p of state.made || []) md.push(`### ${p.title} (ID ${p.id}, ${p.status})`, '', `WhatsApp: ${p.whatsapp.join(', ') || '—'} · marcadores visibles: ${p.placeholders.length} · enlaces rotos: ${p.broken.join(', ') || 'ninguno'}`, '', '```', p.text.slice(0, 6000), '```', '');
    fs.writeFileSync(`${dir}/respuestas.md`, md.join('\n'));

    const requests = state.seen?.requests || [];
    check('G2', requests.length > 0 && requests.every((r) => r.model === 'claude-opus-5-5' && r.cache), 'cada petición a Anthropic va con claude-opus-5-5 y caché automática', JSON.stringify(requests.map((r) => r.model)));
    // Memoria: toda petición desde el mensaje 4 lleva producto, precio y fecha.
    const fromFour = requests.filter((r) => /fin de mes|hazlo/.test(r.last_user));
    check('K1', fromFour.length > 0 && fromFour.every((r) => r.purificador && r.precio && r.fin_de_mes), `desde el mensaje 4, cada petición al modelo lleva lo que dijo la persona (${fromFour.length} peticiones; hasta ${Math.max(0, ...fromFour.map((r) => r.results))} resultados)`, JSON.stringify(fromFour.filter((r) => !(r.purificador && r.precio && r.fin_de_mes)).slice(0, 3)));
    const draftTools = transcript.flatMap((t) => t.events).filter((e) => e.type === 'tool' && e.message.kind === 'draft');
    const asked = transcript.slice(3).flatMap((t) => t.events).filter((e) => e.type === 'message' && e.message.role === 'assistant').map((e) => e.message.text).join('\n');
    if (mode !== 'real') {
      check('K2', state.seen?.memory_after_19?.results >= 19 && state.seen.memory_after_19.purificador && state.seen.memory_after_19.precio && state.seen.memory_after_19.fin_de_mes, 'tras 19 consultas grandes, la petición siguiente aún lleva «purificador», «7500» y «fin de mes»', JSON.stringify(state.seen?.memory_after_19));
      check('R1', state.seen?.contact_has_whatsapp === true, 'digitalisimo-ia/site-contact encontró el WhatsApp del pie (wa.me/5218711234567)');
      check('D1', draftTools.some((e) => e.message.ability === 'digitalisimo-sales/create-page' && e.message.status === 'ok') && !t4.events.some((e) => e.type === 'confirm' && e.action.ability === 'digitalisimo-sales/create-page'), 'el borrador de la landing se creó sin pedir confirmación', JSON.stringify(t4.events.filter((e) => e.type === 'tool' || e.type === 'confirm').map((e) => e.message || e.action).map((m) => `${m.kind}:${m.ability}:${m.status || ''}`)));
      check('D2', t4.events.some((e) => e.type === 'confirm' && e.action.ability === 'digi-ias/apply-offer'), 'cambiar el precio en la tienda sí pidió confirmación');
      const t5 = transcript[4];
      check('Y1', t5 && !t5.events.some((e) => e.type === 'tool' && e.message.ability === 'digi-ias/apply-offer') && t5.events.some((e) => e.type === 'message' && /bot[oó]n/.test(e.message.text)) && before && before.product && before.product.sale === '', '«sí hazlo» NO aplicó el cambio de precio propuesto tras leer el sitio: sigue en su tarjeta y la tienda no cambió', JSON.stringify({ product: before && before.product, events: t5 && t5.events.map((e) => e.type) }));
      check('Y2', confirmed && confirmed.events.some((e) => e.type === 'tool' && e.message.ability === 'digi-ias/apply-offer' && e.message.status === 'ok') && state.product.sale === '7500', 'con el botón de la tarjeta, sí (precio de oferta 7500 en la tienda)', JSON.stringify({ product: state.product, confirmed: confirmed && confirmed.status }));
      check('R2', (state.made || []).some((p) => p.whatsapp.includes('5218711234567')), 'la landing lleva el WhatsApp del sitio', JSON.stringify((state.made || []).map((p) => p.whatsapp)));
      check('T1', transcript.every((t) => !t.error), 'ninguna petición falló', JSON.stringify(transcript.map((t) => t.error).filter(Boolean)));
    } else {
      // Evaluación de la conversación real.
      const landing = (state.made || [])[0];
      const reasked = /(qu[ée]|cu[áa]l) (producto|oferta)|precio de (la )?oferta\s*\?|qu[ée] fecha|cu[áa]ndo (termina|cierra)|n[úu]mero de whatsapp\s*\?|me (das|compartes|pasas) (el|tu) (n[úu]mero|whatsapp)/i;
      check('E1', !reasked.test(asked), 'desde el mensaje 4 no vuelve a preguntar producto, precio, fecha ni el WhatsApp', asked.slice(0, 1500));
      check('E2', !!landing, 'creó la landing (borrador nuevo)', JSON.stringify(state.made));
      if (landing) {
        check('E3', landing.product && landing.price, 'la landing usa el producto (Purificador ROTOPLAS) y el precio de oferta 7,500', landing.text.slice(0, 600));
        check('E4', landing.placeholders.length === 0, 'sin marcadores visibles ni datos de muestra', landing.placeholders.join(' · '));
        check('E5', landing.whatsapp.length > 0 && landing.whatsapp.every((n) => n === '5218711234567'), 'sus botones de WhatsApp van al número del sitio', landing.whatsapp.join(', '));
        check('E6', landing.broken.length === 0, 'ningún enlace a páginas que no existen', landing.broken.join(', '));
      }
      check('E7', fromFour.every((r) => r.purificador && r.precio && r.fin_de_mes), 'el modelo nunca perdió lo que dijo la persona');
      // El cambio de precio de la tienda termina propuesto en su tarjeta: un «sí hazlo» ambiguo (después de leer el sitio,
      // o con más de una cosa pendiente) no lo aplica.
      const priced = pending.filter((a) => /products|precio|price/i.test(`${a.ability} ${a.input}`));
      check('E8', priced.length > 0, 'el cambio de precio de la tienda quedó propuesto en una tarjeta', JSON.stringify(pending.map((a) => `${a.label} [${a.kind}]`)));
      const t5 = transcript[4];
      check('E9', state.product.sale !== '7500' && (!t5 || !t5.events.some((e) => e.type === 'tool' && e.message.status === 'ok' && priced.some((a) => a.ability === e.message.ability))), '«sí hazlo» no bajó el precio real del producto: sigue esperando el botón', JSON.stringify({ product: state.product, said: t5 && t5.events.filter((e) => e.type === 'message').map((e) => e.message.text) }));
      fs.writeFileSync(`${dir}/evaluacion.json`, JSON.stringify({ results, landing: landing ? { id: landing.id, title: landing.title, whatsapp: landing.whatsapp, placeholders: landing.placeholders, broken: landing.broken } : null, requests: requests.length, seconds: transcript.reduce((n, t) => n + t.seconds, 0) }, null, 1));
      if (landing) {
        try {
          const puppeteer = require('puppeteer-core');
          const browser = await puppeteer.launch({ executablePath: process.env.CHROME_PATH || '/usr/bin/google-chrome', headless: 'new', args: ['--no-sandbox', `--host-resolver-rules=MAP ${host.split(':')[0]} 127.0.0.1`] });
          const page = await browser.newPage();
          await page.setViewport({ width: 1280, height: 900 });
          const base = `http://${host}`;
          await page.setCookie(...Object.entries(cookies).map(([name, value]) => ({ name, value: decodeURIComponent(value), url: base })));
          await page.goto(`${base}/?page_id=${landing.id}&preview=true`, { waitUntil: 'networkidle2', timeout: 180000 });
          await page.screenshot({ path: `${dir}/landing.png`, fullPage: true });
          await page.setViewport({ width: 390, height: 844, isMobile: true });
          await page.goto(`${base}/?page_id=${landing.id}&preview=true`, { waitUntil: 'networkidle2', timeout: 180000 });
          await page.screenshot({ path: `${dir}/landing-movil.png`, fullPage: true });
          await browser.close();
          log('captura: landing.png y landing-movil.png');
        } catch (e) { log(`sin captura: ${e.message}`); }
      }
    }
  } catch (e) {
    check('X', false, 'el ensayo terminó sin errores', e.stack || e.message);
  } finally {
    try { await probe('wipe'); } catch { /* el servidor ya no responde */ }
    fs.writeFileSync(`${dir}/result.json`, JSON.stringify({ checks: results.length, failures, results }, null, 1));
    log(failures ? `\n${failures} falla(s)` : `\nTodo bien (${results.length} comprobaciones).`);
    process.exit(failures ? 1 : 0);
  }
})();
