// Comprobaciones en Chrome de la página digi-slider: ver slider.sh.
const puppeteer = require('puppeteer-core');
const base = process.argv[2];
const out = process.env.OUT || '/tmp';
const problems = [];
const notes = [];
const expect = (ok, message) => { if (!ok) problems.push(message); };

async function open(browser, { width, height, mobile = false, js = true }) {
  const page = await browser.newPage();
  if (!js) await page.setJavaScriptEnabled(false);
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
        // Hueco fantasma: lo que el grupo mide de más respecto a sus elementos y separaciones (Firefox lo dejaba al medir por contenido).
        phantom: +(groupWidth - widths.reduce((a, b) => a + b, 0) - items.length * (parseFloat(getComputedStyle(groups[0]).gap) || 0)).toFixed(2),
        fitScale: track.style.getPropertyValue('--digi-fit-scale'), duration: getComputedStyle(track).animationDuration, playState: getComputedStyle(track).animationPlayState, fillMode: getComputedStyle(track).animationFillMode,
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


(async () => {
  const browser = await puppeteer.launch({ executablePath: '/usr/bin/google-chrome', headless: 'new', args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage'] });
  for (const mode of [{ name: 'escritorio', width: 1440, height: 900 }, { name: 'móvil', width: 390, height: 844, mobile: true }]) {
    const { page, errors, failed, status } = await open(browser, mode);
    expect(status === 200, `${mode.name}: la página respondió ${status}`);
    await new Promise((r) => setTimeout(r, 1500));
    const state = await inspect(page);
    await page.screenshot({ path: `${out}/${mode.name}.png` }).catch(() => {});
    const near = ['Onda', 'Logos', 'Onda repetida'];
    for (const key of near.concat(['Logos una pasada'])) expect(state[key] && !state[key].preparing, `${mode.name} · ${key}: debe arrancar (${JSON.stringify(state[key] && state[key].preparing)})`);
    expect(Object.keys(state).length === 5, `${mode.name}: deben pintarse cinco sliders (${Object.keys(state).join(', ')})`);
    for (const key of near) {
      const s = state[key] || {};
      expect(!s.preparing && s.started !== null && s.started < 6000, `${mode.name} · ${key}: debe arrancar pronto (${JSON.stringify({ preparing: s.preparing, started: s.started })})`);
      expect(s.playState === 'running', `${mode.name} · ${key}: la pista debe estar en marcha (${s.playState})`);
      expect(Math.abs(s.trackMinus2Groups) <= 1, `${mode.name} · ${key}: la pista debe medir dos grupos exactos (${s.trackMinus2Groups})`);
      expect(Math.abs(s.phantom) <= 1, `${mode.name} · ${key}: el grupo debe medir exactamente sus elementos y separaciones, sin hueco (${s.phantom} px de más)`);
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
    expect(wave.fitScale === '0.2500', `${mode.name} · Onda: la escala del recorte es 2/8 (${wave.fitScale})`);
    // Logos: doce distintos se conservan enteros en cualquier pantalla, a la duración configurada.
    const logos = state.Logos || {};
    expect(logos.kept && logos.kept[0] === 12 && logos.kept[1] === 12 && logos.fitScale === '' && parseFloat(logos.duration) === 100, `${mode.name} · Logos: los doce distintos enteros a 100 s (${JSON.stringify(logos.kept)}, ${logos.fitScale}, ${logos.duration})`);
    // Onda repetida con espejo: dos mitades de siempre, con la duración equivalente de 6 s.
    const loop = state['Onda repetida'] || {};
    expect(loop.kept && loop.kept[0] === 2 && loop.fitScale === '' && parseFloat(loop.duration) === 6, `${mode.name} · Onda repetida: dos mitades a 6 s (${JSON.stringify(loop.kept)}, ${loop.duration})`);
    // Una sola pasada: arranca, conserva el final al terminar (fill both) y no se recorta.
    const once = state['Logos una pasada'] || {};
    expect(!once.preparing && once.kept && once.kept[0] === 12 && once.fillMode === 'both', `${mode.name} · una pasada: doce logos, en marcha y con relleno «both» (${JSON.stringify({ preparing: once.preparing, kept: once.kept, fillMode: once.fillMode })})`);
    const onceEnd = await page.evaluate(async () => {
      const slider = document.querySelector('.digi-slider[aria-label="Logos una pasada"]');
      const track = slider.querySelector('.digi-slider__track');
      const anim = track.getAnimations()[0];
      if (!anim) return null;
      anim.currentTime = anim.effect.getTiming().duration - 30;
      await Promise.race([anim.finished, new Promise((r) => setTimeout(r, 2000))]);
      await new Promise((r) => setTimeout(r, 100));
      return { state: anim.playState, x: new DOMMatrixReadOnly(getComputedStyle(track).transform).m41, viewport: slider.querySelector('.digi-slider__viewport').clientWidth, group: track.getBoundingClientRect().width };
    });
    expect(onceEnd && onceEnd.state === 'finished' && onceEnd.x < -10 && Math.abs(onceEnd.x - (onceEnd.viewport - onceEnd.group)) < 2, `${mode.name} · una pasada: al terminar se queda en el final (${JSON.stringify(onceEnd)})`);
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
      expect(tablet.Onda && tablet.Onda.kept[0] === 8 && tablet.Onda.fitScale === '' && parseFloat(tablet.Onda.duration) === 60, `tableta · Onda: vuelven las ocho copias a 60 s (${JSON.stringify(tablet.Onda)})`);
      expect(tablet.Logos && tablet.Logos.kept[0] === 12, `tableta · Logos: los doce siguen (${JSON.stringify(tablet.Logos.kept)})`);
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
  // Firefox (si está instalado): mide el ancho de los grupos por sus variables y no por el contenido, así que las uniones
  // y el cierre del bucle deben ser tan exactos como en Chrome.
  const firefoxPath = process.env.FIREFOX_PATH || ['/snap/bin/firefox', '/usr/bin/firefox'].find((p) => require('fs').existsSync(p));
  if (firefoxPath) {
    try {
      const profile = firefoxPath.startsWith('/snap/') ? `${process.env.HOME}/snap/firefox/common/digi-slider-profile` : `${out}/firefox-profile`;
      require('fs').mkdirSync(profile, { recursive: true });
      const firefox = await puppeteer.launch({ browser: 'firefox', executablePath: firefoxPath, headless: true, protocol: 'webDriverBiDi', userDataDir: profile, args: ['--width=1440', '--height=900'] });
      const { page, errors, failed, status } = await open(firefox, { width: 1440, height: 900 });
      expect(status === 200, `firefox: la página respondió ${status}`);
      await new Promise((r) => setTimeout(r, 2500));
      const state = await inspect(page);
      for (const key of ['Onda', 'Logos', 'Onda repetida']) {
        const s = state[key] || {};
        expect(!s.preparing && s.playState === 'running' && s.moved > 0, `firefox · ${key}: debe estar en marcha (${JSON.stringify({ preparing: s.preparing, playState: s.playState, moved: s.moved })})`);
        expect(Math.abs(s.phantom) <= 1 && Math.abs(s.trackMinus2Groups) <= 1, `firefox · ${key}: grupo sin hueco y pista de dos grupos (${s.phantom} px de más, ${s.trackMinus2Groups})`);
        expect(Array.isArray(s.wrapJumps) && s.wrapJumps.length === 1 && Math.abs(Math.abs(s.wrapJumps[0]) - s.groupWidth) <= 2 * s.wrapStep + 2, `firefox · ${key}: el cierre del bucle debe saltar un grupo exacto (${JSON.stringify(s.wrapJumps)} frente a ${s.groupWidth})`);
      }
      expect(state.Onda && state.Onda.kept[0] === 2 && parseFloat(state.Onda.duration) === 15, `firefox · Onda: dos mitades a 15 s (${JSON.stringify(state.Onda && [state.Onda.kept, state.Onda.duration])})`);
      expect(state.Logos && state.Logos.kept[0] === 12, `firefox · Logos: los doce enteros (${JSON.stringify(state.Logos && state.Logos.kept)})`);
      expect(errors.length === 0 && failed.length === 0, `firefox: errores o recursos no encontrados: ${errors.concat(failed).join(' | ')}`);
      notes.push(`firefox: Onda ${state.Onda.kept[0]}/grupo (${state.Onda.phantom} px de más), ${state.Onda.duration}; Logos ${state.Logos.kept[0]}/grupo (${state.Logos.phantom} px de más)`);
      await page.close();
      await firefox.close();
    } catch (e) {
      problems.push(`firefox: no se pudo completar la pasada: ${String(e.message || e).slice(0, 300)}`);
    }
  } else {
    notes.push('firefox: no instalado, pasada omitida');
  }
  require('fs').writeFileSync(`${out}/slider.json`, JSON.stringify({ problems, notes }, null, 1));
  for (const n of notes) console.log(`INFO   ${n}`);
  for (const p of problems) console.log(`FALLA  ${p}`);
  console.log(problems.length ? `\n${problems.length} comprobaciones fallan.` : '\nSlider Optimizado en navegador: arranque, recorte, velocidad, bucle, fluidez, instancia lejana, tableta y arranque sin JavaScript correctos.');
  process.exit(problems.length ? 1 : 0);
})().catch((e) => { console.error('FALLO', e); process.exit(1); });
