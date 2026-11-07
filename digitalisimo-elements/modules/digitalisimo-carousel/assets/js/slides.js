/* DIGITALÍSIMO Elements · Diapositivas: animación del contenido (class-slides.php). */
(()=>{
const W=window,D=document,A='data-digi-animation',F=()=>W.elementorFrontend;
if(W.matchMedia?.('(prefers-reduced-motion: reduce)').matches)return;
const scan=s=>(s?.querySelectorAll?s:D).querySelectorAll('.digi-slides['+A+']').forEach(r=>{r.querySelector(':scope>.digi-crs__track')?.children.length==1&&r.classList.add('digi-slides--anim')});
D.addEventListener('digi-crs-change',e=>{
const r=e.target,c=r.classList;
if(!c?.contains('digi-slides')||!r.hasAttribute(A))return;
clearTimeout(r.digiSlidesOut);
c.add('digi-slides--out','digi-slides--anim');
r.digiSlidesOut=setTimeout(()=>c.remove('digi-slides--out'),parseFloat(r.style.getPropertyValue('--digi-crs-speed'))||500);
});
const hook=()=>F().hooks.addAction('frontend/element_ready/global',s=>scan(s?.[0]));
F()?.hooks?hook():W.addEventListener('elementor/frontend/init',hook);
D.readyState=='loading'?D.addEventListener('DOMContentLoaded',()=>scan()):scan();
})();
