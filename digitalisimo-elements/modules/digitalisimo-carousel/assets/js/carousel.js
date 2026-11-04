/* DIGITALÍSIMO Elements · motor de carrusel digi-crs (C1; deriva de nested-slider.js de F7b). Marcado, variables y API: class-engine.php. */
(()=>{
const W=window,D=document,R='digi-crs',E=R+'-',C=R+'__',cs=getComputedStyle,moves=()=>!W.matchMedia?.('(prefers-reduced-motion: reduce)').matches,each=(l,f)=>[].forEach.call(l,f),sa=(e,a,v)=>e?.setAttribute(a,v);
let io,ro;
const init=root=>{
const cl=root?.classList,kid=n=>root.querySelector(':scope>.'+C+n),attr=n=>root.getAttribute('data-digi-'+n),track=kid('track');
if (!track||cl.contains(R+'--ready')) return;
const slides=track.children,box=kid('pagination'),one=n=>box?.querySelector('.'+C+n),dots=box?box.getElementsByClassName(C+'dot'):[],pause=kid('pause'),prev=kid('prev'),next=kid('next'),
now=one('now'),total=one('total'),fill=one('fill'),icon=pause?.querySelector('path'),pd=icon?.getAttribute('d'),
fade=attr('effect')=='fade',center=attr('center'),loop=attr('loop'),quit=attr('interaction'),
delay=W.elementorFrontend?.isEditMode?.()?0:+attr('autoplay')||0,
css=n=>cs(root).getPropertyValue('--'+E+n);
let view=1,step=1,page=0,timer=0,wait=0,lw=-1,stop=!delay||!moves(),held=0,focus=0,seen=1;
const max=()=>Math.max(0,slides.length-(center?1:view)),pages=()=>Math.ceil(max() / step)+1,pos=p=>Math.min(p * step,max()),
off=i=>slides[i]?slides[i].offsetLeft-slides[0].offsetLeft:0,
emit=(n,d,el)=>(el||root).dispatchEvent(new CustomEvent(E+n,{bubbles: n=='change',detail: d}));
// Estado: inert fuera de la ventana visible, actual, puntos, fracción, barra y extremos sin bucle.
const paint=()=>{
const n=pages(),p0=pos(page=Math.max(0,Math.min(page,n-1))),lo=center?p0-(view-1 >> 1):p0,label=box?.getAttribute('data-digi-dot');
cl.toggle(R+'--locked',n<2);
each(slides,(s,k)=>{s.toggleAttribute('inert',k<lo||k>=lo+view); s.classList.toggle(C+'current',k==p0);});
each(dots,(d,k)=>{d.hidden=k>=n; k==page?sa(d,'aria-current','true'):d.removeAttribute('aria-current'); label&&sa(d,'aria-label',label.replace('%d',pos(k)+1));});
if (now){now.textContent=page+1; total.textContent=n;}
if (fill) fill.style.width=(page+1) / n * 100+'%';
sa(prev,'aria-disabled',!loop&&page<1);
sa(next,'aria-disabled',!loop&&page>=n-1);
};
const glide=jump=>track.scrollTo({left: off(pos(page)),behavior: jump||!moves()?'instant':'smooth'});
const to=(p,auto,jump)=>{
const old=pos(page),n=pages();
page=loop||auto?(p % n+n) % n:p;
paint();
fade?jump||cl.add(R+'--anim'):glide(jump);
pos(page)!=old&&emit('change',{index: pos(page)});
};
const run=()=>{
clearTimeout(timer);
timer=delay&&!stop&&!held&&!focus&&seen&&!D.hidden&&pages()>1?setTimeout(()=>{to(page+1,1); run();},delay):0;
sa(track,'aria-live',timer?'off':'polite');
cl.toggle(R+'--paused',stop);
sa(pause,'aria-label',pause?.getAttribute(stop?'data-digi-play':'data-digi-pause'));
sa(icon,'d',stop?'M8 5v14l11-7z':pd);
};
const user=()=>{if (quit&&delay) stop=1;};
// Visibles y paso: variables calculadas por dispositivo; imán sólo al inicio de cada página.
const fit=()=>{
view=fade?1:parseInt(css('view'))||1;
step=fade?1:parseInt(css('scroll'))||1;
each(slides,(s,k)=>{s.style.scrollSnapAlign=k>max()||k % step&&k!=max()?'none':'';});
paint();
if (track.clientWidth!=lw){lw=track.clientWidth; fade||glide(1);}
};
const on=(t,f,el)=>(el||root).addEventListener(t,f),ctl=b=>b?.tagName=='BUTTON'&&(b.parentNode==root||b.parentNode==box);
on('click',e=>{
const b=e.target.closest?.('button');
if (!ctl(b)) return;
if (b==pause) stop=!stop;
else if (b.getAttribute('aria-disabled')!='true'){user(); to(b==prev?page-1:b==next?page+1:[].indexOf.call(dots,b));}
run();
});
on('keydown',e=>{
const b=e.target,k=e.key,i=k=='Home'?0:k=='End'?pages()-1:/^Arrow[LR]/.test(k)?page+((k=='ArrowRight')==(cs(track).direction!='rtl')?1:-1):null;
if (!ctl(b)||i==null) return;
e.preventDefault();
user();
to(i);
run();
b.parentNode==box&&dots[page]?.focus();
});
on('focusin',e=>{focus=e.target!=pause; run();});
on('focusout',e=>{if (!root.contains(e.relatedTarget)){focus=0; run();}});
if (attr('hover')) for (const [t,v] of [['pointerenter',1],['pointerleave',0]]) on(t,e=>{if (e.pointerType=='mouse'){held=v; run();}});
// Deslizamiento del usuario: al asentarse, la página más cercana; el foco sale de lo inerte.
fade||on('scroll',()=>{
clearTimeout(wait);
wait=setTimeout(()=>{
const x=Math.abs(track.scrollLeft),d=p=>Math.abs(Math.abs(off(pos(p)))-x),lost=slides[pos(page)]?.contains(D.activeElement);
let best=0;
for (let p=1; p<pages(); p++) if (d(p)<d(best)) best=p;
if (best==page) return;
user();
to(best,0,1);
run();
lost&&(dots[page]&&!dots[page].hidden?dots[page]:next)?.focus({preventScroll: true});
},120);
},track);
const api=root.digiCrs={go: i=>{i=+i||0; to(i>=max()?pages()-1:Math.floor(i / step)); run();},index: ()=>pos(page),count: ()=>slides.length,fit,see: v=>{seen=v; run();}};
on(E+'go',e=>e.target==root&&api.go(e.detail));
// Miniaturas sincronizadas con el carrusel principal (data-digi-sync="#id").
let main;
try{main=D.querySelector(attr('sync'));} catch (x){}
if (main){
const mark=i=>{each(slides,(s,k)=>s.classList.toggle(C+'synced',k==i)); api.go(i);};
main.addEventListener(E+'change',e=>e.target==main&&mark(e.detail.index));
on('click',e=>{const s=[].indexOf.call(slides,e.target.closest?.('.'+C+'track>*')); s<0||emit('go',s,main);});
mark(main.digiCrs?.index()||0);
}
if (delay){
on('visibilitychange',run,D);
if (W.IntersectionObserver) (io=io||new IntersectionObserver(l=>l.forEach(en=>en.target.digiCrs?.see(en.isIntersecting)))).observe(root);
}
if (W.ResizeObserver) (ro=ro||new ResizeObserver(l=>l.forEach(en=>en.target.parentNode.digiCrs?.fit()))).observe(track);
if (pause) pause.hidden=false;
fit();
run();
cl.add(R+'--ready');
};
const scan=s=>each((s?.querySelectorAll?s:D).querySelectorAll('.'+R),init);
const hook=()=>W.elementorFrontend.hooks.addAction('frontend/element_ready/global',s=>scan(s?.[0]));
W.elementorFrontend?.hooks?hook():W.addEventListener('elementor/frontend/init',hook);
D.readyState=='loading'?D.addEventListener('DOMContentLoaded',()=>scan()):scan();
})();
