/* DIGITALÍSIMO Elements · Menú con submenús de contenido: «Desplazamiento horizontal» como PRO (class-mega-menu.php). Sin dependencias. */
(() => {
const W = window, D = document, H = '.digi-mm__heading', S = 'digi-mm__scroll', A = S + '-active', J = '--dmm-heading-justify-content';
const v = (h, p) => getComputedStyle(h).getPropertyValue(p).trim(), on = h => v(h, '--dmm-heading-overflow-x') == 'scroll';
// Posición inicial: si los elementos no caben, la fila empieza por el primero, o por el último con «Final».
const align = h => {
h.style.removeProperty(J);
let w = 0;
const g = parseFloat(getComputedStyle(h).gap) || 0, end = v(h, J) == 'flex-end';
for (const c of h.children) w += c.offsetWidth + g;
if (!on(h) || h.clientWidth >= w) return;
h.style.setProperty(J, 'start');
h.scrollLeft = end ? (getComputedStyle(h).direction == 'rtl' ? -w : w) : 0;
};
// Arrastre con el ratón: hasta 20 px sigue al puntero y después avanza de 5 en 5; el velo evita abrir un enlace al soltar.
const init = h => {
if (h.digiMmScroll) return;
h.digiMmScroll = 1;
const stop = () => { h.classList.remove(S, A); h.dataset.pageX = ''; };
h.addEventListener('mousedown', e => { h.classList.add(S); h.dataset.pageX = e.pageX; });
h.addEventListener('mouseup', stop);
h.addEventListener('mouseleave', stop);
h.addEventListener('mousemove', e => {
if (!h.classList.contains(S) || !on(h) || h.scrollWidth <= h.clientWidth) return;
e.preventDefault();
const d = e.pageX - h.dataset.pageX;
h.scrollLeft -= d > 20 ? 5 : d < -20 ? -5 : d;
h.classList.add(A);
});
align(h);
};
const scan = s => (s?.querySelectorAll ? s : D).querySelectorAll(H).forEach(init);
const ef = () => W.elementorFrontend, hook = () => ef().hooks.addAction('frontend/element_ready/digitalisimo-mega-menu.default', s => scan(s?.[0]));
ef()?.hooks ? hook() : W.addEventListener('elementor/frontend/init', hook);
W.addEventListener('resize', () => D.querySelectorAll(H).forEach(h => h.digiMmScroll && align(h)));
D.readyState == 'loading' ? D.addEventListener('DOMContentLoaded', () => scan()) : scan();
})();
