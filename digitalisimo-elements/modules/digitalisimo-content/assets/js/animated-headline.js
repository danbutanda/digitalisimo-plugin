/* Titular animado (digitalisimo-animated-headline): resaltado con trazo SVG o texto que rota (escritura, recorte,
   giro, remolino, persianas, caída, ola, deslizamiento). Mismos tiempos que el widget de PRO, sin dependencias. Arranca al
   entrar en la pantalla; con «reducir movimiento» el trazo aparece ya dibujado y el texto no rota (salvo lo convertido de
   PRO, `digi-ah--motion`, que rota como en PRO). */
(()=>{'use strict';
const W=window,D=document,on=new WeakSet(),R=W.matchMedia&&W.matchMedia('(prefers-reduced-motion: reduce)');
const C='digi-ah__',ON=C+'word--on',OFF=C+'word--off',IN=C+'letter--in',T=(f,ms)=>setTimeout(f,ms);
function start(h){
const d=h.dataset,dyn=h.querySelector('.'+C+'dyn'),type=d.digiAhType,loop='1'===d.digiAhLoop;
if(!dyn)return;
const ready=()=>h.classList.add('digi-ah--ready');
if('highlight'===d.digiAhStyle){ready();
const dur=+d.digiAhDuration||1200,wait=+d.digiAhWait||8e3;
const draw=()=>{if(!h.isConnected)return;h.classList.add('digi-ah--drawn');if(R&&R.matches)return;h.classList.remove('digi-ah--hide');if(!loop)return;T(()=>h.classList.add('digi-ah--hide'),dur+.8*wait);T(draw,dur+wait);};
return draw();}
const words=[...dyn.querySelectorAll('.'+C+'word')],del=+d.digiAhDelay||0,s={anim:del||2500,letters:.02*del||50,typeL:.06*del||150,sel:.2*del||500,reveal:.24*del||600,revealWait:.6*del||1500};
s.typeAnim=s.sel+800;
const letters=h.classList.contains('digi-ah--letters'),next=w=>words[(words.indexOf(w)+1)%words.length];
const width=w=>parseFloat(getComputedStyle(w).width)||0;
// Letras como PRO (también con movimiento reducido, que sólo deja de rotar).
if(letters)words.forEach(w=>{
const text=w.textContent,b=D.createElement('span'),sr=D.createElement('span'),act=w.classList.contains(ON);
b.setAttribute('aria-hidden','true');sr.className='elementor-screen-only';sr.textContent=text;
[...text].forEach(ch=>{const l=D.createElement('span');l.className=C+'letter'+(act?' '+IN:'');l.textContent=ch;b.append(l);});
w.replaceChildren(sr,b);w.style.opacity=1;});
ready();
if(!words.length||R&&R.matches&&!h.classList.contains('digi-ah--motion'))return;
const fit=w=>{if('clip'!==type&&'typing'!==type)dyn.style.width=width(w)+'px';};
const box=w=>w.querySelector('[aria-hidden]');
const first=w=>{const b=box(w);return b?b.firstElementChild:null;};
const swap=(a,b)=>{a.classList.replace(ON,OFF)||a.classList.add(OFF);b.classList.remove(OFF);b.classList.add(ON);fit(b);};
const clip=(to,done)=>{const a=dyn.animate([{width:width(dyn)+'px'},{width:to+'px'}],{duration:s.reveal,easing:'ease-in-out'});a.onfinish=()=>{dyn.style.width=to+'px';done();};};
function show(l,w,bool,ms){if(!l)return;l.classList.add(IN);if(l.nextElementSibling)T(()=>show(l.nextElementSibling,w,bool,ms),ms);else if(!bool)T(()=>hide(w),s.anim);}
function unshow(l,w,bool,ms){if(!l)return;l.classList.remove(IN);if(l.nextElementSibling)T(()=>unshow(l.nextElementSibling,w,bool,ms),ms);else if(bool)T(()=>hide(next(w)),s.anim);}
function reveal(w,ms){
if('typing'===type){show(first(w),w,!1,ms);w.classList.add(ON);w.classList.remove(OFF);}
else if('clip'===type)clip(width(w)+10,()=>T(()=>hide(w),s.revealWait));}
function hide(w){
if(!h.isConnected||!loop&&w===words[words.length-1])return;
const n=next(w);
if('typing'===type){dyn.classList.add('digi-ah--sel');T(()=>{dyn.classList.remove('digi-ah--sel');w.classList.add(OFF);w.classList.remove(ON);(box(w)||w).querySelectorAll('.'+IN).forEach(l=>l.classList.remove(IN));},s.sel);T(()=>reveal(n,s.typeL),s.typeAnim);}
else if(letters){const c=x=>(box(x)||x).children.length,bool=c(w)>=c(n);unshow(first(w),w,bool,s.letters);show(first(n),n,bool,s.letters);fit(n);}
else if('clip'===type)clip(2,()=>{swap(w,n);reveal(n);});
else{swap(w,n);T(()=>hide(n),s.anim);}}
if('clip'===type)dyn.style.width=width(dyn)+10+'px';else if('typing'!==type)fit(words[0]);
T(()=>hide(words[0]),s.anim);}
function scan(root){
(root||D).querySelectorAll('.digi-ah[data-digi-ah-style]').forEach(h=>{
if(on.has(h))return;on.add(h);
if(!('IntersectionObserver'in W))return start(h);
const io=new IntersectionObserver(e=>{if(e.some(x=>x.isIntersecting)){io.disconnect();start(h);}},{rootMargin:'0px 0px -100px 0px'});
io.observe(h);});}
const hook=()=>W.elementorFrontend.hooks.addAction('frontend/element_ready/global',s=>scan(s&&s[0]));
'loading'===D.readyState?D.addEventListener('DOMContentLoaded',()=>scan()):scan();
if(W.elementorFrontend&&W.elementorFrontend.hooks)hook();else W.addEventListener('elementor/frontend/init',()=>W.elementorFrontend&&W.elementorFrontend.hooks&&hook());
})();
