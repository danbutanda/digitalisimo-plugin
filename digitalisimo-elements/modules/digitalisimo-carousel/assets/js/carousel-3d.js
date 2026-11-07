/* DIGITALÍSIMO Elements · «Coverflow» y «Cubo» del Carrusel multimedia como en PRO (ver class-media-carousel.php): `--digi-k` es la distancia de cada diapositiva al centro de la pista, en anchos de diapositiva; la hoja la gira. Sin imán (Chrome lo calcula con la caja girada): un desplazamiento que queda a medias vuelve a la actual. */
(()=>{
const W=window,D=document,A=Math.abs,F=()=>W.elementorFrontend,Q=new Set,S='[data-digi-fx]>.digi-crs__track';
let raf=0,ro;
const draw=()=>{
raf=0;
Q.forEach(T=>{
const P=T.parentNode,cube=P.dataset.digiFx=='cube',w=T.clientWidth,c=w/2+T.scrollLeft;
let r=0;
for(const s of T.children){
const n=s.offsetWidth||1,k=(c-s.offsetLeft-n/2)/n,y=s.style;
y.setProperty('--digi-k',+k.toFixed(4));
if(cube){y.visibility=A(k)<1?'':'hidden';A(k)<=.5&&(r=k)}else y.zIndex=1-A(Math.round(k));
}
if(cube)for(const [n,v] of [['w',w+'px'],['b',T.offsetTop+T.offsetHeight+'px'],['y',T.offsetTop+T.offsetHeight/2+'px'],['r',(r*90).toFixed(2)+'deg']])P.style.setProperty('--digi-cube-'+n,v);
});
Q.clear();
};
const ask=T=>{Q.add(T);raf||(raf=requestAnimationFrame(draw))};
const scan=s=>(s?.querySelectorAll?s:D).querySelectorAll(S).forEach(T=>{(ro=ro||new ResizeObserver(l=>l.forEach(e=>ask(e.target)))).observe(T);ask(T)});
const settle=T=>{clearTimeout(T.$s);T.$s=setTimeout(()=>{const P=T.parentNode,c=T.querySelector(':scope>.digi-crs__current'),f=T.firstElementChild;P.digiCrs&&c&&!P.classList.contains('digi-crs--drag')&&A(T.scrollLeft-c.offsetLeft+f.offsetLeft)>1&&P.digiCrs.go(P.digiCrs.index())},200)};
D.addEventListener('scroll',e=>{const T=e.target;T.matches?.(S)&&(ask(T),settle(T))},!0);
const hook=()=>F().hooks.addAction('frontend/element_ready/global',s=>scan(s?.[0]));
F()?.hooks?hook():W.addEventListener('elementor/frontend/init',hook);
D.readyState=='loading'?D.addEventListener('DOMContentLoaded',()=>scan()):scan();
})();
