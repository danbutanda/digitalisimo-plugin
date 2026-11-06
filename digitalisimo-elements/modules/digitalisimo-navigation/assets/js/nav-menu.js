/* DIGITALÍSIMO Elements · Menú de WordPress: submenús de divulgación, hamburguesa y anclas (class-nav-menu.php). */
(() => {
const W = window, D = document, R = 'digi-nav', OPEN = R + '__item--open', E = 'aria-expanded';
const C = s => '.' + R + '__' + s, each = (l, f) => Array.prototype.forEach.call(l, f), box = e => e.getBoundingClientRect();
const rtl = () => getComputedStyle(D.documentElement).direction == 'rtl', vw = () => D.documentElement.clientWidth;
let n = 0;
const init = root => {
if (!root || root.digiNav) return;
root.digiNav = 1;
const wrap = root.closest('.elementor-element') || root.parentNode, main = root.querySelector(C('main'));
const toggle = root.querySelector(C('toggle')), dd = root.querySelector(C('container') + C('dropdown')), timers = new WeakMap();
const later = (li, f, ms) => { clearTimeout(timers.get(li)); ms ? timers.set(li, setTimeout(f, ms)) : f(); };
const kid = (li, s) => li.querySelector(':scope>' + s), btnOf = li => kid(li, C('sub-toggle')), isOpen = li => li.classList.contains(OPEN);
const place = scope => each((scope || root).querySelectorAll(C('sub-toggle')), b => {
const li = b.parentNode, a = b.previousElementSibling;
if (!a || !a.offsetParent) return;
const ar = a.querySelector(C('arrow')), l = box(li), r = box(a), s = ar && box(ar), back = rtl();
let x0 = back ? r.left : s ? s.left : r.right - 34, x1 = back ? (s ? s.right : r.left + 34) : r.right;
if (x1 - x0 < 24) x0 = x1 - 24;
const h = Math.max(24, r.height);
b.hidden = false;
b.style.cssText = `left:${x0 - l.left - li.clientLeft}px;top:${r.top - l.top - li.clientTop + (r.height - h) / 2}px;width:${x1 - x0}px;height:${h}px`;
});
const set = (li, open) => {
const b = btnOf(li), a = kid(li, 'a'), ul = kid(li, 'ul');
if (!ul || isOpen(li) == open) return;
li.classList.toggle(OPEN, open);
a && a.classList.toggle(R + '__open', open);
b && b.setAttribute(E, open);
if (!open) return each(li.querySelectorAll('.' + OPEN), x => set(x, false));
if (main && main.contains(li)) {
each(li.parentNode.children, x => x != li && set(x, false));
ul.classList.remove(R + '__flip');
const u = box(ul);
if (rtl() ? u.left < 0 : u.right > vw()) ul.classList.add(R + '__flip');
}
place(ul);
};
const closeAll = scope => each(scope.querySelectorAll('.' + OPEN), li => set(li, false));
each(root.querySelectorAll(C('sub-toggle')), b => {
const ul = kid(b.parentNode, 'ul');
if (ul) { ul.id = ul.id || R + '-sub-' + ++n; b.setAttribute('aria-controls', ul.id); }
});
root.addEventListener('click', e => {
const b = e.target.closest(C('sub-toggle')), a = e.target.closest('a');
if (b) return set(b.parentNode, !isOpen(b.parentNode));
const t = a && btnOf(a.parentNode), r = t && box(t);
if (t && (!isOpen(a.parentNode) || a.getAttribute('href') == '#' || e.clientX >= r.left && e.clientX <= r.right && e.clientY >= r.top && e.clientY <= r.bottom)) { e.preventDefault(); return set(a.parentNode, !isOpen(a.parentNode)); }
if (a && dd && dd.contains(a) && wrap.classList.contains(R + '--stretch') && !btnOf(a.parentNode)) menu(false);
});
if (main) {
each(main.querySelectorAll('li'), li => {
if (!kid(li, 'ul')) return;
li.addEventListener('pointerenter', e => e.pointerType == 'mouse' && later(li, () => set(li, true), isOpen(li) ? 0 : 250));
li.addEventListener('pointerleave', e => e.pointerType == 'mouse' && later(li, () => set(li, false), 500));
});
main.addEventListener('focusout', e => { const to = e.relatedTarget; to && each(main.querySelectorAll('.' + OPEN), li => li.contains(to) || set(li, false)); });
D.addEventListener('click', e => root.contains(e.target) || closeAll(main));
}
root.addEventListener('keydown', e => {
if (e.key != 'Escape') return;
const f = D.activeElement, li = f && f.closest && f.closest('.' + OPEN);
if (li && root.contains(li)) { set(li, false); const b = btnOf(li); b && b.focus(); }
else if (toggle && toggle.getAttribute(E) == 'true') { menu(false); toggle.focus(); }
else return;
e.preventDefault();
e.stopPropagation();
});
const stretch = () => {
if (!dd || !wrap.classList.contains(R + '--stretch')) return;
const side = rtl() ? 'right' : 'left';
dd.style[side] = '0px';
dd.style.width = vw() + 'px';
const r = box(dd);
dd.style[side] = (side == 'left' ? -r.left : r.right - vw()) + 'px';
if (toggle) dd.style.top = toggle.offsetHeight + 'px';
};
const menu = open => {
if (!toggle) return;
toggle.setAttribute(E, open);
dd.inert = !open;
if (open) stretch(); else closeAll(dd);
root.style.setProperty('--digi-nav-h', open ? (root.closest('.elementor-sticky') ? W.innerHeight - box(dd).top + 'px' : '1000vmax') : '');
open && place(dd);
};
toggle && toggle.addEventListener('click', () => menu(toggle.getAttribute(E) != 'true'));
const edit = W.elementorFrontend && W.elementorFrontend.isEditMode && W.elementorFrontend.isEditMode();
if (W.IntersectionObserver && main && !edit) each(main.querySelectorAll('a' + C('anchor')), a => {
let t;
if (a.pathname != location.pathname || !a.hash) return;
try { t = D.querySelector(decodeURIComponent(a.hash)); } catch (x) {}
if (!t) return;
const vh = W.innerHeight, m = -vh / 2;
new IntersectionObserver(l => l.forEach(en => {
a.classList.toggle(R + '__active', en.isIntersecting);
en.isIntersecting ? a.setAttribute('aria-current', 'location') : a.removeAttribute('aria-current');
}), { rootMargin: `${t.offsetHeight > vh / 2 ? m : 0}px 0px ${m}px 0px` }).observe(t);
});
const fit = () => { place(); stretch(); };
W.ResizeObserver ? new ResizeObserver(fit).observe(root.parentNode) : W.addEventListener('resize', fit);
root.classList.add(R + '--ready');
fit();
};
const scan = s => each((s && s.querySelectorAll ? s : D).querySelectorAll('.' + R), init);
const hook = () => W.elementorFrontend.hooks.addAction('frontend/element_ready/digitalisimo-nav-menu.default', s => scan(s && s[0]));
W.elementorFrontend && W.elementorFrontend.hooks ? hook() : W.addEventListener('elementor/frontend/init', hook);
D.readyState == 'loading' ? D.addEventListener('DOMContentLoaded', () => scan()) : scan();
})();
