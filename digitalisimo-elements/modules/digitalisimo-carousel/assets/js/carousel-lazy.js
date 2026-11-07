/* DIGITALÍSIMO Elements · indicador de la «Carga diferida» de los carruseles (C1; el aro de Swiper en PRO): se retira al cargar la imagen, o si falla. Ver class-base-widget.php. */
(()=>{
const D=document,P='digi-crs__preloader',on=e=>e.target.tagName=='IMG'&&e.target.parentNode?.querySelector(':scope>.'+P)?.remove(),
scan=()=>D.querySelectorAll('.'+P).forEach(p=>{const i=p.parentNode.querySelector(':scope>img');(!i||i.complete)&&p.remove()});
D.addEventListener('load',on,!0);
D.addEventListener('error',on,!0);
D.readyState=='loading'?D.addEventListener('DOMContentLoaded',scan):scan();
})();
