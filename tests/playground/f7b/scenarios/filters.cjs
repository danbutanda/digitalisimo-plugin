// B5 (filtros nuevos sobre un Loop Grid), B6 (destino «Archivo» con `digi_f`) y B7 (convivencia con F4-Woo, WOO=1)
// de f7b.sh. Cada conjunto se compara con un WP_Query directo con los mismos argumentos (`?digi_f7b=wpquery`).
'use strict';

module.exports = (api) => {
  const { check, pending, note, control, request, open, ids, url, loopIds, domLoopIds, sleep, shot } = api;
  const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);
  const q = (params) => '?' + Object.entries(params).map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`).join('&');
  const wp = async (args) => (await control('wpquery', { args: JSON.stringify({ post_type: 'post', ...args }) })).ids;

  async function B5(s) {
    const m = await ids(true);
    const page = await url('b5-filters');
    const L = 'b5loop1';
    const p = (name) => `e-filter-${L}-${name}`;
    const base = { posts_per_page: 6, orderby: 'date', order: 'DESC' };
    const cases = [
      ['B5.1', 'búsqueda «Gamma»', { [p('digi_s_b5srch1')]: 'Gamma' }, { ...base, s: 'Gamma' }],
      ['B5.2', 'rango de precio 10–60 (DECIMAL)', { [p('digi_m_b5prec1')]: '10', [p('digi_m_b5prec1-max')]: '60' }, { ...base, meta_query: [{ key: 'precio', value: [10, 60], compare: 'BETWEEN', type: 'DECIMAL(20,6)' }] }],
      ['B5.3', 'color «rojo» o «azul» (varios valores de la lista)', { [p('digi_m_b5colr1')]: 'rojo~azul' }, { ...base, meta_query: [{ key: 'color', value: ['rojo', 'azul'], compare: 'IN' }] }],
      ['B5.4', 'orden «Título (A-Z)»', { [p('digi_o_b5ordr1')]: 'title_asc' }, { ...base, orderby: 'title', order: 'ASC' }],
      ['B5.5', 'orden por el campo precio, de menor a mayor', { [p('digi_o_b5ordr1')]: 'meta_asc' }, { ...base, meta_key: 'precio', orderby: 'meta_value_num', order: 'ASC' }],
      ['B5.6', '3 por página (de la lista del widget)', { [p('digi_pp_b5pp001')]: '3' }, { ...base, posts_per_page: 3 }],
      ['B5.7', 'combinados: búsqueda «F7b», precio 10–100, rojo o azul, Z-A, 12 por página', { [p('digi_s_b5srch1')]: 'F7b', [p('digi_m_b5prec1')]: '10', [p('digi_m_b5prec1-max')]: '100', [p('digi_m_b5colr1')]: 'rojo~azul', [p('digi_o_b5ordr1')]: 'title_desc', [p('digi_pp_b5pp001')]: '12' }, { posts_per_page: 12, s: 'F7b', orderby: 'title', order: 'DESC', meta_query: [{ key: 'precio', value: [10, 100], compare: 'BETWEEN', type: 'DECIMAL(20,6)' }, { key: 'color', value: ['rojo', 'azul'], compare: 'IN' }] }],
    ];
    const none = loopIds((await request('GET', page)).text, L);
    for (const [id, what, params, args] of cases) {
      const res = await request('GET', page + q(params));
      const got = loopIds(res.text, L);
      const want = await wp(args);
      check(s, id, `por GET, ${what}: el bucle = WP_Query directo`, res.status === 200 && got && same(got, want) && (id === 'B5.6' || !same(got, none) || same(want, none)), JSON.stringify({ got, want }));
    }
    // Valores no declarados por GET: se descartan (lo que llega en la URL nunca decide).
    const bad = await request('GET', page + q({ [p('digi_pp_b5pp001')]: '1000', [p('digi_o_b5ordr1')]: 'rand', [p('digi_m_b5colr1')]: 'morado', [p('digi_m_zzzz999')]: 'x' }));
    check(s, 'B5.8', 'por GET, valores fuera de la lista del widget (1000 por página, `rand`, un color no declarado, un widget que no existe): sin efecto', same(loopIds(bad.text, L), none), JSON.stringify({ got: loopIds(bad.text, L), none }));

    // En Chrome: el coordinador, el teclado, la región viva y la petición REST (que después se forja).
    const v = await open(page);
    const posts = [];
    v.page.on('request', (rq) => { if (/refresh-loop/.test(rq.url())) posts.push({ url: rq.url(), body: rq.postData() || '', headers: rq.headers() }); });
    const input = '.elementor-element-b5srch1 input[type="search"]';
    await v.page.focus(input);
    await v.page.keyboard.type('G');
    await v.page.keyboard.press('Enter');
    await sleep(1500);
    check(s, 'B5.9a', 'buscador: un solo carácter no envía (mínimo 2) y escribir no busca', posts.length === 0, `${posts.length} peticiones`);
    await v.page.keyboard.type('amma');
    await sleep(800);
    const typed = posts.length;
    await v.page.keyboard.press('Enter');
    await v.page.waitForFunction(() => document.querySelectorAll('.elementor-element-b5loop1 .e-loop-item').length === 1, { timeout: 30000 }).catch(() => {});
    await sleep(800);
    const afterSearch = await domLoopIds(v.page, L);
    const live = await v.page.evaluate(() => [...document.querySelectorAll('[aria-live], [role="status"]')].map((n) => n.textContent.trim()).filter(Boolean));
    const busy = await v.page.$eval('.elementor-element-b5loop1', (el) => el.getAttribute('aria-busy')).catch(() => null);
    check(s, 'B5.9b', 'Intro envía: una petición `refresh-loop`, el bucle = «Gamma F7b» y la región viva (`role=status`) lo anuncia («1 resultado»), sin `aria-busy`', typed === 0 && posts.length === 1 && same(afterSearch, await wp({ ...base, s: 'Gamma' })) && live.some((t) => /1 resultado/.test(t)) && busy !== 'true', JSON.stringify({ typed, posts: posts.length, afterSearch, live, busy }));
    const search = await v.page.evaluate(() => { const f = document.querySelector('.elementor-element-b5srch1 form'); const i = f.querySelector('input[type=search]'); return { role: f.getAttribute('role'), label: f.getAttribute('aria-label'), labelled: !!document.querySelector(`label[for="${i.id}"]`), clear: f.querySelector('.digi-filter__clear')?.getAttribute('aria-label') }; });
    check(s, 'B5.9c', 'buscador accesible: `form[role=search]` con nombre, etiqueta visible ligada y «Limpiar» con nombre propio', search.role === 'search' && !!search.label && search.labelled && /Limpiar/.test(search.clear || ''), JSON.stringify(search));
    // Botones de color con aria-pressed (árbol de accesibilidad).
    const btn = await v.page.$('.elementor-element-b5colr1 [aria-pressed]');
    if (btn) {
      await btn.click();
      await v.page.waitForFunction((n) => true, { timeout: 1000 }, 0).catch(() => {});
      await sleep(2500);
      const ax = await v.page.accessibility.snapshot({ root: btn });
      const pressed = await btn.evaluate((b) => b.getAttribute('aria-pressed'));
      check(s, 'B5.9d', 'grupo de botones: `aria-pressed` cambia al elegir y el árbol de accesibilidad lo expone', pressed === 'true' && ax && ax.role === 'button' && ax.pressed === true, JSON.stringify({ pressed, ax: ax && { role: ax.role, name: ax.name, pressed: ax.pressed } }));
    } else check(s, 'B5.9d', 'grupo de botones con `aria-pressed`', false, 'sin botones en el filtro de color');
    check(s, 'B5.9e', 'la URL del navegador refleja el filtro y ninguna petición lleva IDs ajenos; sin errores de JavaScript', /e-filter-b5loop1-digi_s_b5srch1=Gamma/.test(v.page.url()) && !v.facts.errors.length && !v.facts.failed.length, JSON.stringify({ url: v.page.url(), errors: v.facts.errors.slice(0, 3), failed: v.facts.failed.slice(0, 3) }));
    // Petición forjada: claves, cantidades y órdenes no declarados, `<fid>` de otro documento y tipo cambiado.
    if (posts[0]) {
      note(s, `cuerpo de refresh-loop: ${posts[0].body.slice(0, 400)}`);
      const replay = (mutate) => v.page.evaluate(async (req, mut) => {
        const body = JSON.parse(req.body);
        const fn = new Function('b', mut); // eslint-disable-line no-new-func
        fn(body);
        const headers = {};
        for (const [k, val] of Object.entries(req.headers || {})) if (/^(content-type|x-wp-nonce)$/i.test(k)) headers[k] = val;
        const res = await fetch(req.url, { method: 'POST', headers, body: JSON.stringify(body), credentials: 'same-origin' });
        const text = await res.text();
        return { status: res.status, ids: [...text.matchAll(/e-loop-item-(\d+)/g)].map((x) => Number(x[1])).filter((x, i, a) => a.indexOf(x) === i) };
      }, posts[0], mutate);
      const clean = await replay('b.widget_filters = {};');
      const forged = await replay(`b.widget_filters = {
        digitalisimo_meta: { digi_m_zzzz999: { terms: ['x'], logicalJoin: 'OR' }, digi_m_b5prec1: { terms: ['abc'], logicalJoin: 'OR' } },
        digitalisimo_per_page: { digi_pp_b5pp001: { terms: ['1000'], logicalJoin: 'OR' } },
        digitalisimo_orderby: { digi_o_b5ordr1: { terms: ['rand'], logicalJoin: 'OR' }, digi_o_b5oord1: { terms: ['title_desc'], logicalJoin: 'OR' } },
        digitalisimo_search: { digi_m_b5colr1: { terms: ['rojo'], logicalJoin: 'OR' } }
      };`);
      check(s, 'B5.10', 'REST forjada (widget inexistente, valor no numérico, 1000 por página, `rand`, `<fid>` de otro documento, tipo cambiado): el mismo HTML que sin filtros', clean.status === 200 && clean.ids.length > 0 && same(forged.ids, clean.ids), JSON.stringify({ clean, forged }));
      const other = await replay('b.post_id = 0; b.widget_filters = {};');
      check(s, 'B5.10b', 'REST sin documento válido: PRO la rechaza (no hay bucle que pintar)', other.status >= 400 || other.ids.length === 0, JSON.stringify(other));
    } else pending(s, 'B5.10', 'REST forjada', 'el coordinador no envió ninguna petición refresh-loop');
    await v.close();
    // Recursos: hoja propia y coordinador sólo donde hay filtros.
    const res = await request('GET', page);
    const sheet = /id=['"]digitalisimo-filters-css['"]/.test(res.text);
    const elsewhere = await request('GET', await url('b8-plain'));
    check(s, 'B5.11', 'la hoja `f7b-filters.css` y el coordinador sólo en las páginas con filtros', sheet && /digitalisimo-woo-filters-js/.test(res.text) && !/digitalisimo-filters-css|digitalisimo-woo-filters-js/.test(elsewhere.text));
  }

  async function B6(s) {
    const m = await ids(true);
    const doc = m.docs['b6-archive'];
    const cat = m.terms['digi-f7b-a'];
    const arch = '/category/digi-f7b-a/';
    const A = 'b6post1';
    const base = { cat, orderby: 'date', order: 'DESC', posts_per_page: 10 };
    const plain = await request('GET', arch);
    const plainIds = loopIds(plain.text, A);
    check(s, 'B6.1', 'el archivo con la plantilla del Theme Builder pinta la consulta principal sin `digi_f`', plain.status === 200 && same(plainIds, await wp(base)), JSON.stringify({ plainIds }));
    const f = (fid) => `${doc}-${fid}`;
    const tries = [
      ['B6.2', 'con `digi_f`, orden «Título (A-Z)» en la consulta principal', { digi_f: f('b6ordr1'), digi_o_b6ordr1: 'title_asc' }, { ...base, orderby: 'title', order: 'ASC' }],
      ['B6.3', 'con `digi_f`, 2 por página', { digi_f: f('b6pp001'), digi_pp_b6pp001: '2' }, { ...base, posts_per_page: 2 }],
      ['B6.4', 'con `digi_f`, color «rojo»', { digi_f: f('b6colr1'), digi_m_b6colr1: 'rojo' }, { ...base, meta_query: [{ key: 'color', value: ['rojo'], compare: 'IN' }] }],
      ['B6.5', 'con `digi_f` de un widget, los parámetros de los otros widgets del mismo documento también (orden y color)', { digi_f: f('b6ordr1'), digi_o_b6ordr1: 'title_asc', digi_m_b6colr1: 'rojo,azul' }, { ...base, orderby: 'title', order: 'ASC', meta_query: [{ key: 'color', value: ['rojo', 'azul'], compare: 'IN' }] }],
    ];
    for (const [id, what, params, args] of tries) {
      const res = await request('GET', arch + q(params));
      const got = loopIds(res.text, A);
      const want = await wp(args);
      check(s, id, what, res.status === 200 && same(got, want) && !same(got, plainIds), JSON.stringify({ got, want }));
    }
    const ignored = [
      ['B6.6', 'sin `digi_f`, los parámetros propios no hacen nada', { digi_o_b6ordr1: 'title_asc', digi_pp_b6pp001: '2' }],
      ['B6.7', '`digi_f` de una plantilla en borrador: no se aplica', { digi_f: `${m.docs['b6-draft']}-b6drft1`, digi_o_b6drft1: 'title_asc' }],
      ['B6.8', '`digi_f` de un widget con destino Loop Grid: no se aplica', { digi_f: `${m.docs['b5-filters']}-b5ordr1`, digi_o_b5ordr1: 'title_asc' }],
      ['B6.9', 'un parámetro de un widget de otro documento con el `digi_f` del archivo: no se aplica', { digi_f: f('b6ordr1'), digi_o_b5ordr1: 'title_asc' }],
      ['B6.10', 'un valor fuera de la lista del widget (`rand`, 1000 por página, un color no declarado): no se aplica', { digi_f: f('b6ordr1'), digi_o_b6ordr1: 'rand', digi_pp_b6pp001: '1000', digi_m_b6colr1: 'verde' }],
    ];
    for (const [id, what, params] of ignored) {
      const res = await request('GET', arch + q(params));
      check(s, id, what, res.status === 200 && same(loopIds(res.text, A), plainIds), JSON.stringify({ got: loopIds(res.text, A), plainIds }));
    }
    // Formularios: el de orden envía `digi_f`; el buscador de destino «Archivo» va a la búsqueda nativa.
    const v = await open(arch);
    const forms = await v.page.evaluate(() => ({
      order: (() => { const f = document.querySelector('.elementor-element-b6ordr1 form'); return f ? { method: f.method, action: f.getAttribute('action'), hidden: [...f.querySelectorAll('input[type=hidden]')].map((i) => `${i.name}=${i.value}`) } : null; })(),
      search: (() => { const f = document.querySelector('.elementor-element-b6srch1 form'); return f ? { action: f.getAttribute('action'), input: f.querySelector('input[type=search]')?.name, role: f.getAttribute('role') } : null; })(),
    }));
    check(s, 'B6.11', 'el formulario de orden es GET a la misma dirección con `digi_f=<documento>-<widget>`; el buscador va a `/?s=` (búsqueda nativa)', !!forms.order && forms.order.method === 'get' && forms.order.hidden.includes(`digi_f=${doc}-b6ordr1`) && !!forms.search && forms.search.input === 's' && /\/$/.test(forms.search.action || '') && forms.search.role === 'search', JSON.stringify(forms));
    await v.page.select('.elementor-element-b6ordr1 select', 'title_asc');
    await Promise.all([v.page.waitForNavigation({ waitUntil: 'load', timeout: 60000 }).catch(() => {}), v.page.click('.elementor-element-b6ordr1 button[type="submit"]').catch(() => v.page.$eval('.elementor-element-b6ordr1 form', (f) => f.submit()))]);
    await sleep(500);
    const sorted = await v.page.evaluate(() => [...document.querySelectorAll('.elementor-element-b6post1 article')].map((a) => Number((a.className.match(/\bpost-(\d+)\b/) || [])[1])));
    check(s, 'B6.12', 'en Chrome: elegir «Título (A-Z)» y aplicar recarga el archivo ordenado', /digi_f=/.test(v.page.url()) && same(sorted, await wp({ ...base, orderby: 'title', order: 'ASC' })), JSON.stringify({ url: v.page.url(), sorted }));
    await v.close();
    const found = await request('GET', '/?s=Gamma');
    check(s, 'B6.13', 'la búsqueda nativa encuentra la entrada (sin `s` sobre un archivo de término)', found.status === 200 && found.text.includes('Gamma F7b'));
  }

  async function B7(s) {
    if (!api.MODE.woo) { pending(s, 'B7.0', 'filtros de F4-Woo y de F7b en el mismo bucle de productos', 'sólo con WOO=1'); return; }
    pending(s, 'B7.1', 'precio y atributo de F4-Woo junto a búsqueda y orden por precio de F7b en un bucle de productos', 'cubierto por woo-flow.sh W9 y W10 con el registro ampliado (regresión); el caso combinado en f7b.sh queda para la siguiente iteración');
  }

  return { B5, B6, B7 };
};
