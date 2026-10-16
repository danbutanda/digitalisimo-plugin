// Comprobaciones en Chrome de la página digi-slider: ver slider.sh.
const puppeteer = require('puppeteer-core');
const base = process.argv[2];
const out = process.env.OUT || '/tmp';
const problems = [];
const notes = [];
const expect = (ok, message) => { if (!ok) problems.push(message); };

async function open(browser, { width, height, mobile = false, js = true }) {
  const page = await browser.newPage();
  await page.setJavaScriptEnabled(js);
  if (mobile) await page.setViewport({ width, height, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
  else await page.setViewport({ width, height });
  const errors = [];
  const failed = [];
  page.on('pageerror', (e) => errors.push(String(e.message || e)));
  page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
  page.on('response', (r) => { if (r.status() >= 400 && r.url().startsWith(base)) failed.push(`${r.status()} ${r.url()}`); });
  if (js) {
    await page.evaluateOnNewDocument(() => {
      window.__digi = { started: {} };
      const observer = new MutationObserver((records) => {
        records.forEach((record) => {
          const el = record.target;
          if (el.classList && el.classList.contains('digi-slider') && !el.classList.contains('digi-slider--preparing')) {
            const key = el.getAttribute('aria-label') || 'sin-nombre';
            if (!(key in window.__digi.started)) window.__digi.started[key] = performance.now();
          }
        });
      });
      const attach = () => { if (document.documentElement) observer.observe(document.documentElement, { attributes: true, subtree: true, attributeFilter: ['class'] }); else setTimeout(attach, 0); };
      attach();
    });
  }
  const response = await page.goto(`${base}/digi-slider/`, { waitUntil: 'networkidle0', timeout: 120000 });
  return { page, errors, failed, status: response && response.status() };
}

// Estado de cada slider, la unión del bucle (salto al final del ciclo) y la fluidez (muestras por fotograma).
async function inspect(page, { sample = true } = {}) {
  return page.evaluate(async (sample) => {
    const result = {};
    for (const slider of document.querySelectorAll('.digi-slider')) {
      const key = slider.getAttribute('aria-label') || 'sin-nombre';
      const track = slider.querySelector('.digi-slider__track');
      const viewport = slider.querySelector('.digi-slider__viewport');
      const groups = Array.from(track.children).filter((c) => c.classList.contains('digi-slider__group'));
      const items = Array.from(groups[0].children);
      const widths = items.map((i) => i.getBoundingClientRect().width);
      const groupWidth = groups[0].getBoundingClientRect().width;
      const images = Array.from(slider.querySelectorAll('img'));
      const anim = track.getAnimations ? track.getAnimations()[0] : null;
      const entry = {
        preparing: slider.classList.contains('digi-slider--preparing'),
        kept: groups.map((g) => g.children.length),
        itemWidth: +(widths[0] || 0).toFixed(2), gapEach: +((groupWidth - widths.reduce((a, b) => a + b, 0)) / Math.max(1, items.length)).toFixed(2),
        viewport: viewport.clientWidth, groupWidth: +groupWidth.toFixed(2), trackMinus2Groups: +(track.getBoundingClientRect().width - 2 * groupWidth).toFixed(2),
        inlineDuration: track.style.animationDuration, duration: getComputedStyle(track).animationDuration, playState: getComputedStyle(track).animationPlayState,
        imagesComplete: images.filter((i) => i.complete && i.naturalWidth > 0).length, images: images.length,
        visibleIncomplete: images.filter((i) => { const r = i.getBoundingClientRect(); return r.width > 0 && r.right > 0 && r.left < innerWidth && !(i.complete && i.naturalWidth > 0); }).length,
        started: window.__digi ? window.__digi.started[key] : null,
      };
      if (sample && anim && entry.playState === 'running' && !entry.preparing) {
        const dur = anim.effect.getTiming().duration;
        const read = () => new DOMMatrixReadOnly(getComputedStyle(track).transform).m41;
        // Fluidez: 1.2 s de muestras.
        const samples = [];
        await new Promise((resolve) => { let last = performance.now(); const start = last; (function step(now) { samples.push({ dt: now - last, x: read() }); last = now; if (now - start < 1200) requestAnimationFrame(step); else resolve(); })(performance.now()); });
        const dts = samples.slice(1).map((s) => s.dt);
        const moved = Math.abs(samples[samples.length - 1].x - samples[0].x);
        entry.frames = samples.length;
        entry.longFrames = dts.filter((d) => d > 50).length;
        entry.moved = +moved.toFixed(1);
        // Unión del bucle: salto al final del ciclo y lectura de la vuelta.
        anim.currentTime = dur - 250;
        const around = [];
        await new Promise((resolve) => { const start = performance.now(); (function step(now) { around.push(read()); if (now - start < 600) requestAnimationFrame(step); else resolve(); })(performance.now()); });
        const deltas = around.slice(1).map((v, i) => v - around[i]);
        const step = deltas.filter((d) => Math.abs(d) <= 40).map(Math.abs).sort((a, b) => a - b);
        entry.wrapStep = +(step[Math.floor(step.length / 2)] || 0).toFixed(2);
        entry.wrapJumps = deltas.filter((d) => Math.abs(d) > 40).map((d) => +d.toFixed(1));
        anim.currentTime = 1000;
      }
      result[key] = entry;
    }
    return result;
  }, sample);
}

function need(entry) {
  // La regla del script: elementos cuyo ancho acumulado (con separación) alcanza el viewport, más uno.
  const unit = entry.itemWidth + entry.gapEach;
  return Math.min(12, Math.ceil(entry.viewport / unit) + 1);
}

(async () => {
  const browser = await puppeteer.launch({ executablePath: '/usr/bin/google-chrome', headless: 'new', args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage'] });
  for (const mode of [{ name: 'escritorio', width: 1440, height: 900 }, { name: 'móvil', width: 390, height: 844, mobile: true }]) {
    const { page, errors, failed, status } = await open(browser, mode);
    expect(status === 200, `${mode.name}: la página respondió ${status}`);
    await new Promise((r) => setTimeout(r, 1500));
    const state = await inspect(page);
    await page.screenshot({ path: `${out}/${mode.name}.png` }).catch(() => {});
    const near = ['Onda', 'Logos', 'Onda repetida'];
    expect(Object.keys(state).length === 4, `${mode.name}: deben pintarse cuatro sliders (${Object.keys(state).join(', ')})`);
    for (const key of near) {
      const s = state[key] || {};
      expect(!s.preparing && s.started !== null && s.started < 6000, `${mode.name} · ${key}: debe arrancar pronto (${JSON.stringify({ preparing: s.preparing, started: s.started })})`);
      expect(s.playState === 'running', `${mode.name} · ${key}: la pista debe estar en marcha (${s.playState})`);
      expect(Math.abs(s.trackMinus2Groups) <= 1, `${mode.name} · ${key}: la pista debe medir dos grupos exactos (${s.trackMinus2Groups})`);
      // El salto del cierre es el ancho de un grupo más, como mucho, el avance de un fotograma.
      expect(Array.isArray(s.wrapJumps) && s.wrapJumps.length === 1 && Math.abs(Math.abs(s.wrapJumps[0]) - s.groupWidth) <= 2 * s.wrapStep + 2, `${mode.name} · ${key}: el cierre del bucle debe saltar un grupo exacto (${JSON.stringify(s.wrapJumps)} frente a ${s.groupWidth}, paso ${s.wrapStep})`);
      expect(s.frames >= 50 && s.longFrames <= 3 && s.moved > 0, `${mode.name} · ${key}: el movimiento debe ser fluido (${s.frames} fotogramas, ${s.longFrames} largos, ${s.moved} px)`);
      expect(s.visibleIncomplete === 0, `${mode.name} · ${key}: las imágenes a la vista deben estar cargadas (${s.visibleIncomplete} sin cargar de ${s.images})`);
    }
    const far = state['Logos lejanos'] || {};
    expect(far.preparing === true, `${mode.name} · lejana: debe esperar a entrar en vista (preparing=${far.preparing})`);
    // Onda: diez copias en modo múltiple, uno visible: dos por grupo y velocidad igual a la de ocho a 60 s (192 px/s a 1440).
    const wave = state.Onda || {};
    expect(wave.kept && wave.kept[0] === 2 && wave.kept[1] === 2, `${mode.name} · Onda: dos mitades por grupo (${JSON.stringify(wave.kept)})`);
    // El viewport del slider es el del contenedor de Elementor, no el de la ventana.
    const waveSpeed = wave.groupWidth / parseFloat(wave.duration);
    const waveExpected = (8 * wave.viewport) / 60;
    expect(Math.abs(waveSpeed - waveExpected) < 1, `${mode.name} · Onda: la velocidad debe ser la de ocho copias en 60 s (${waveSpeed.toFixed(1)} frente a ${waveExpected.toFixed(1)} px/s; duración ${wave.duration})`);
    // Logos: doce distintos, recortados a los que cubren el viewport más uno, con la duración en proporción.
    const logos = state.Logos || {};
    const logosNeed = need(logos);
    expect(logos.kept && logos.kept[0] === logosNeed && logos.kept[1] === logosNeed, `${mode.name} · Logos: ${logosNeed} por grupo (${JSON.stringify(logos.kept)}; unidad ${logos.itemWidth}+${logos.gapEach} en ${logos.viewport})`);
    expect(logosNeed === 12 ? logos.inlineDuration === '' : Math.abs(parseFloat(logos.inlineDuration) - 100 * logosNeed / 12) < 0.5, `${mode.name} · Logos: duración en proporción (${logos.inlineDuration} para ${logosNeed}/12)`);
    // Onda repetida con espejo: dos mitades de siempre, con la duración equivalente de 6 s.
    const loop = state['Onda repetida'] || {};
    expect(loop.kept && loop.kept[0] === 2 && loop.inlineDuration === '' && parseFloat(loop.duration) === 6, `${mode.name} · Onda repetida: dos mitades a 6 s (${JSON.stringify(loop.kept)}, ${loop.duration})`);
    // La lejana arranca al llegar.
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await new Promise((r) => setTimeout(r, 2500));
    const after = await inspect(page, { sample: false });
    expect(after['Logos lejanos'] && !after['Logos lejanos'].preparing && after['Logos lejanos'].playState === 'running', `${mode.name} · lejana: debe arrancar al llegar (${JSON.stringify(after['Logos lejanos'])})`);
    if (!mode.mobile) {
      // Tableta: seis visibles en la onda, vuelven las ocho copias y la duración configurada (60 s).
      await page.setViewport({ width: 1024, height: 900 });
      await new Promise((r) => setTimeout(r, 800));
      const tablet = await inspect(page, { sample: false });
      expect(tablet.Onda && tablet.Onda.kept[0] === 8 && tablet.Onda.inlineDuration === '' && parseFloat(tablet.Onda.duration) === 60, `tableta · Onda: vuelven las ocho copias a 60 s (${JSON.stringify(tablet.Onda)})`);
      const tabletNeed = need(tablet.Logos);
      expect(tablet.Logos && tablet.Logos.kept[0] === tabletNeed, `tableta · Logos: ${tabletNeed} por grupo (${JSON.stringify(tablet.Logos.kept)})`);
    }
    expect(errors.length === 0, `${mode.name}: errores de JavaScript: ${errors.join(' | ')}`);
    expect(failed.length === 0, `${mode.name}: recursos no encontrados: ${failed.join(' | ')}`);
    notes.push(`${mode.name}: ${near.map((k) => `${k} ${state[k].kept[0]}/grupo, ${state[k].duration}, arranque ${Math.round(state[k].started)} ms, ${state[k].longFrames} fotogramas largos`).join('; ')}`);
    await page.close();
  }
  // Sin JavaScript: a los tres segundos el movimiento arranca solo.
  {
    const { page, status } = await open(browser, { width: 1440, height: 900, js: false });
    expect(status === 200, `sin JavaScript: la página respondió ${status}`);
    const read = () => page.evaluate(() => Array.from(document.querySelectorAll('.digi-slider')).slice(0, 3).map((s) => [s.classList.contains('digi-slider--preparing'), new DOMMatrixReadOnly(getComputedStyle(s.querySelector('.digi-slider__track')).transform).m41]));
    const early = await read();
    await new Promise((r) => setTimeout(r, 400));
    const still = await read();
    await new Promise((r) => setTimeout(r, 3400));
    const later = await read();
    await new Promise((r) => setTimeout(r, 600));
    const moving = await read();
    expect(early.every((v, i) => v[0] === true && Math.abs(v[1] - still[i][1]) < 0.5), `sin JavaScript: al cargar, la pista espera (${JSON.stringify(early)} → ${JSON.stringify(still)})`);
    expect(later.every((v, i) => v[0] === true && Math.abs(v[1] - moving[i][1]) > 2), `sin JavaScript: a los tres segundos el movimiento arranca solo (${JSON.stringify(later)} → ${JSON.stringify(moving)})`);
    await page.close();
  }
  await browser.close();
  require('fs').writeFileSync(`${out}/slider.json`, JSON.stringify({ problems, notes }, null, 1));
  for (const n of notes) console.log(`INFO   ${n}`);
  for (const p of problems) console.log(`FALLA  ${p}`);
  console.log(problems.length ? `\n${problems.length} comprobaciones fallan.` : '\nSlider Optimizado en navegador: arranque, recorte, velocidad, bucle, fluidez, instancia lejana, tableta y arranque sin JavaScript correctos.');
  process.exit(problems.length ? 1 : 0);
})().catch((e) => { console.error('FALLO', e); process.exit(1); });
