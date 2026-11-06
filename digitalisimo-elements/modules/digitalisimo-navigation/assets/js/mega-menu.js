/* DIGITALÍSIMO Elements · Menú con submenús de contenido (class-mega-menu.php). Sin dependencias. */
(() => {
const W = window, D = document, R = 'digi-mm', A = R + '__active', E = 'aria-expanded', H = D.documentElement;
const box = e => e.getBoundingClientRect(), rtl = () => getComputedStyle(H).direction == 'rtl', ef = () => W.elementorFrontend;
const edit = () => !!ef()?.isEditMode?.();
const small = () => {
let max = 1024;
const bp = ef()?.config?.responsive?.activeBreakpoints;
if (bp) { max = 0; ['mobile', 'mobile_extra', 'tablet', 'tablet_extra'].forEach(k => { bp[k] && (max = Math.max(max, bp[k].value)); }); }
return W.innerWidth <= max;
};
const init = root => {
if (!root || root.digiMm) return;
root.digiMm = 1;
const kid = (el, s) => el?.querySelector(':scope>.' + R + '__' + s);
const tog = kid(root, 'toggle'), wrap = kid(root, 'wrapper'), head = kid(wrap, 'heading'), ds = root.dataset;
if (!head) return;
const items = [...head.children].map(li => {
const title = kid(li, 'title'), content = kid(li, 'content');
return { li, title, content, btn: kid(title, 'dropdown-icon'), con: content?.querySelector(':scope>.e-con') };
}).filter(it => it.title);
const dd = () => ds.layout == 'dropdown', hover = () => ds.digiOpen == 'hover' && !dd() && !edit() && !small();
let timer;
const stretch = () => {
const s = wrap.style, back = rtl();
['width', 'left', 'right'].forEach(p => s.removeProperty('--stretch-' + p));
let sel = ef()?.getKitSettings?.('stretched_section_container'), c;
try { c = sel && D.querySelector(sel); } catch (x) {}
const cw = c ? c.clientWidth : H.clientWidth, r = box(wrap);
let off = Math.max(0, r.left - (c ? box(c).left : 0));
if (back) off = cw - r.width - off;
s.setProperty('--stretch-width', cw + 'px');
s.setProperty('--stretch-' + (back ? 'right' : 'left'), -off + 'px');
};
const place = it => {
root.classList.remove(R + '--above');
const c = it.con, st = c.style, back = rtl(), pos = ds.digiFit;
['left', 'right', 'width', 'max-width'].forEach(p => st.removeProperty(p));
if (dd()) return;
if (pos) {
const vw = H.clientWidth, t = box(it.title), w = c.clientWidth, a = back ? vw - t.right : t.left, b = back ? vw - t.left : t.right, mid = (a + b) / 2;
const x = (pos == 'left') != back && pos != 'center' ? Math.min(a, vw - w) : pos != 'center' ? b - w : mid + w / 2 > vw ? vw - w : mid - w / 2;
st.setProperty(back ? 'right' : 'left', Math.max(0, x) + 'px');
}
const r = box(c);
if (r.height < r.top - box(head).height && W.innerHeight - r.top < r.height) {
st.maxWidth = r.width + 'px';
if (pos) st.width = 'max-content';
root.classList.add(R + '--above');
}
};
const set = (it, on) => {
if (!it.btn || !it.con) return;
it.btn.setAttribute(E, on);
[it.con, it.content].forEach(x => x.classList.toggle(A, on));
it.con.classList.toggle(R + '__fade', on && ds.digiAnim == 'fadeIn');
on ? place(it) : root.classList.remove(R + '--above');
};
const show = it => { clearTimeout(timer); items.forEach(x => x != it && set(x, false)); set(it, true); };
const isOn = it => it.btn?.getAttribute(E) == 'true';
const close = () => items.forEach(x => set(x, false));
const menu = open => {
ds.layout = 'dropdown';
tog.setAttribute(E, open);
open && stretch();
wrap.style.setProperty('--dmm-dropdown-content-box-height', open && root.closest('.elementor-sticky') ? W.innerHeight - box(wrap).top + 'px' : '');
};
tog?.addEventListener('click', () => menu(tog.getAttribute(E) != 'true'));
items.forEach(it => {
if (!it.btn) return;
it.btn.addEventListener('click', e => { e.stopPropagation(); hover() && e.detail || !isOn(it) ? show(it) : set(it, false); });
it.title.addEventListener('click', e => {
edit() && e.preventDefault();
if (!(e.target.closest('a[href]') && !edit() || hover())) isOn(it) ? set(it, false) : show(it);
});
it.li.addEventListener('mouseenter', () => hover() && show(it));
it.li.addEventListener('mouseleave', () => { if (hover() && isOn(it)) timer = setTimeout(() => set(it, false), 120); });
it.li.addEventListener('focusout', e => { const to = e.relatedTarget; to && !it.li.contains(to) && !dd() && set(it, false); });
});
D.addEventListener('click', e => root.contains(e.target) || close());
root.addEventListener('keydown', e => {
const f = D.activeElement, it = items.find(x => x.li.contains(f)), k = e.key;
if (k == 'Escape') {
if (it && isOn(it)) { set(it, false); it.btn.focus(); }
else if (tog?.getAttribute(E) == 'true') { menu(false); tog.focus(); }
else return;
} else if ((k == 'Home' || k == 'End') && it && it.title.contains(f)) {
const l = items.reduce((l, x) => l.concat([...x.title.querySelectorAll('a[href],button')]), []);
l[k == 'Home' ? 0 : l.length - 1].focus();
} else return;
e.preventDefault();
e.stopPropagation();
});
root.addEventListener('digi-mm-go', e => { const it = items[e.detail - 1]; it ? show(it) : close(); });
if (W.IntersectionObserver && !edit()) items.forEach(it => {
const a = it.title.classList.contains(R + '__anchor') && it.title.querySelector('a[href]'), vh = W.innerHeight, m = -vh / 2;
let t;
if (!a || a.pathname != location.pathname || !a.hash) return;
try { t = D.querySelector(decodeURIComponent(a.hash)); } catch (x) {}
t && new IntersectionObserver(l => l.forEach(en => {
it.title.classList.toggle(R + '__current', en.isIntersecting);
en.isIntersecting ? a.setAttribute('aria-current', 'location') : a.removeAttribute('aria-current');
}), { rootMargin: `${t.offsetHeight > vh / 2 ? m : 0}px 0px ${m}px 0px` }).observe(t);
});
const fit = () => {
if (tog) ds.layout = getComputedStyle(tog).display == 'none' ? 'horizontal' : 'dropdown';
stretch();
const on = items.find(isOn);
on && place(on);
};
W.addEventListener('resize', fit);
fit();
root.classList.add(R + '--ready');
};
const scan = s => (s?.querySelectorAll ? s : D).querySelectorAll('.' + R).forEach(init);
const hook = () => ef().hooks.addAction('frontend/element_ready/digitalisimo-mega-menu.default', s => scan(s?.[0]));
ef()?.hooks ? hook() : W.addEventListener('elementor/frontend/init', hook);
D.readyState == 'loading' ? D.addEventListener('DOMContentLoaded', () => scan()) : scan();
})();
