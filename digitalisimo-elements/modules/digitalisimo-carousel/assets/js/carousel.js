/* DIGITALÍSIMO Elements · motor de carrusel digi-crs (C1); ver class-engine.php. */
(()=>{
const W=window,D=document,M=Math,A=M.abs,R='digi-crs',E=R+'-',C=R+'__',cs=getComputedStyle,moves=()=>!W.matchMedia?.('(prefers-reduced-motion:reduce)').matches,each=(l,f)=>[].forEach.call(l,f),sa=(e,a,v)=>e?.setAttribute(a,v),F=()=>W.elementorFrontend;
let io,ro;
const init=root=>{
const cl=root?.classList,kid=n=>root.querySelector(':scope>.'+C+n),attr=n=>root.getAttribute('data-digi-'+n),track=kid('track');
if(!track||cl.contains(R+'--ready'))return;
const all=track.children,box=kid('pagination'),one=n=>box?.querySelector('.'+C+n),dots=box?box.getElementsByClassName(C+'dot'):[],pause=kid('pause'),prev=kid('prev'),next=kid('next'),
now=one('now'),total=one('total'),fill=one('fill'),icon=pause?.querySelector('path'),pd=icon?.getAttribute('d'),
ed=F()?.isEditMode?.(),fade=attr('effect')=='fade',center=attr('center'),loop=attr('loop')&&!(ed&&all[0]?.matches('.elementor-element')),quit=attr('interaction'),
delay=ed?0:+attr('autoplay')||0,
css=n=>cs(root).getPropertyValue('--'+E+n),rtl=()=>cs(track).direction=='rtl';
let slides=all,view=1,step=1,cur=0,page=0,lc=0,timer=0,wait=0,anim=0,touch=0,lw=-1,stop=!delay||!moves(),held=0,focus=0,seen=1,g=0,drag=0,moved=0,ran=0,tpl,main;
const n=()=>slides.length,max=()=>M.max(0,n()-(center?1:view)),pages=()=>lc?M.ceil(n()/step):max()?M.ceil(max()/step)+1:1,pos=p=>M.min(p*step,loop?n()-1:max()),
real=k=>((k-lc)%n()+n())%n(),pg=()=>lc?M.ceil(real(cur)/step)%pages():cur>=max()?pages()-1:M.round(cur/step),
off=k=>(k=all[k])?k.offsetLeft-all[0].offsetLeft:0,
emit=(n,d,el)=>(el||root).dispatchEvent(new CustomEvent(E+n,{bubbles:n=='change',detail:d}));
const paint=()=>{
const N=pages(),p=page=pg(),lo=cur-(center?view-1>>1:0),label=box?.dataset.digiDot;
cl.toggle(R+'--locked',N<2&&!lc);
each(all,(s,k)=>{s.toggleAttribute('inert',k<lo||k>=lo+view);s.classList.toggle(C+'current',k==cur)});
each(dots,(d,k)=>{d.hidden=k>=N;k==p?sa(d,'aria-current','true'):d.removeAttribute('aria-current');label&&sa(d,'aria-label',label.replace('%d',pos(k)+1))});
if(now){now.textContent=p+1;total.textContent=N}
if(fill)fill.style.width=(p+1)/N*100+'%';
sa(prev,'aria-disabled',!loop&&p<1);sa(next,'aria-disabled',!loop&&p>=N-1);
};
const ss=v=>track.style.scrollSnapType=v,halt=()=>{cancelAnimationFrame(anim);anim=0;ss('')},glide=jump=>{
const x=off(cur),from=track.scrollLeft,sp=css('speed'),ms=jump||D.hidden||!moves()?0:parseFloat(sp)*(/ms/.test(sp)?1:1e3)||0,t0=performance.now(),
end=()=>{halt();track.scrollLeft=x},go=()=>anim=requestAnimationFrame(f),f=t=>{const k=M.min(1,(t-t0)/ms);track.scrollLeft=from+(x-from)*(k<.5?2*k*k:1-(2-2*k)**2/2);k<1?go():end()};
halt();
if(!ms||A(x-from)<1)return end();
ss('none');
go();
};
const fix=()=>{const k=lc+real(cur),x=off(k)-off(cur);if(x){anim&&(halt(),ss('none'));track.scrollLeft+=x;cur=k}return x};
const to=(k,jump)=>{
const old=real(cur);paint(cur=k);
fade?jump||cl.add(R+'--anim'):glide(jump);
real(cur)!=old&&emit('change',{index:real(cur)});
lc&&!ran++&&each(track.querySelectorAll(`.${C}clone.elementor-element,.${C}clone .elementor-element`),e=>F()?.elementsHandler?.runReadyTrigger(e));
};
const near=()=>{const x=A(track.scrollLeft),d=k=>A(A(off(k))-x);let b=cur;if(!fade)for(let k=0,N=lc?all.length:pages();k<N;k++){const t=lc?k:pos(k);d(t)<d(b)&&(b=t)}return b};
const mv=(d,auto)=>{const N=pages(),p=pg()+d;fix();to(lc?cur+d*step:pos(loop||auto?(p%N+N)%N:p<0?0:p<N?p:N-1))},
pa=p=>{fix();to(lc+pos(p))},
sl=k=>{const c=cur;fix();k+=cur-c;to(lc?k:pos(k>=max()?pages()-1:M.floor(k/step)))};
const run=()=>{
clearTimeout(timer);
timer=delay&&!stop&&!held&&!focus&&!drag&&seen&&!D.hidden&&pages()>1?setTimeout(()=>{mv(1,1);run()},delay):0;
sa(track,'aria-live',timer?'off':'polite');
cl.toggle(R+'--paused',stop);
sa(pause,'aria-label',pause?.dataset[stop?'digiPlay':'digiPause']);
sa(icon,'d',stop?'M8 5v14l11-7z':pd);
};
const user=()=>quit&&delay&&(stop=1);
const fit=()=>{
view=fade?1:M.min(parseInt(css('view'))||1,parseInt(css('n'))||99);
step=fade?1:parseInt(css('scroll'))||1;
const L=loop&&!fade&&n()>1?view+step:0,r=real(cur);
if(L!=lc){
[...all].map(s=>s.$c&&s.remove());
slides=L?[...all]:all;
lc=L;tpl=tpl||L&&slides.map(s=>s.cloneNode(true));for(let j=0;j<L;j++)for(const b of [0,1]){const c=tpl[b?n()-1-j%n():j%n()].cloneNode(true);c.$c=1;c.classList.add(C+'clone','slick-cloned');track[b?'prepend':'append'](c);scan(c)}
cur=lc+r;lw=-1;
}
lc||(cur=pos(cur>=max()?pages()-1:M.floor(cur/step)));
each(all,(s,k)=>{s.style.scrollSnapAlign=(lc?real(k)%step:k>max()||k%step&&k!=max())?'none':''});
paint();
if(track.clientWidth!=lw){lw=track.clientWidth;fade||glide(1)}
};
const on=(t,f,el,c)=>(el||root).addEventListener(t,f,c),ctl=b=>b?.tagName=='BUTTON'&&(b.parentNode==root||b.parentNode==box);
on('click',e=>{
const b=e.target.closest?.('button');
if(!ctl(b))return;
if(b==pause)stop=!stop;
else if(b.getAttribute('aria-disabled')!='true'){user();b==prev?mv(-1):b==next?mv(1):pa([].indexOf.call(dots,b))}
run();
});
on('keydown',e=>{
const b=e.target,k=e.key,h=/^(Home|End)$/.test(k);
if(!ctl(b)||!h&&!/^Arrow[LR]/.test(k))return;
e.preventDefault();
user();
h?pa(k=='Home'?0:pages()-1):mv((k=='ArrowRight')!=rtl()?1:-1);
run();
b.parentNode==box&&dots[page]?.focus();
});
on('focusin',e=>{focus=e.target!=pause;run()});
on('focusout',e=>{if(!root.contains(e.relatedTarget)){focus=0;run()}});
if(attr('hover'))for(const [t,v] of [['pointerenter',1],['pointerleave',0]])on(t,e=>{if(e.pointerType=='mouse'){held=v;run()}});
for(const t of ['pointerdown','wheel','touchstart','keydown'])on(t,()=>{touch=1;halt()},track);
on('pointerdown',e=>{if(e.pointerType=='mouse'&&!e.button&&!ed&&!e.target.closest('input,select,textarea,button,video,label')){e.preventDefault();g={x:e.clientX,t:e.timeStamp}}},track);
on('pointermove',e=>{
if(!g)return;
if(!drag){if(A(e.clientX-g.x)<5)return;drag=moved=1;fix()&&paint();g.x=e.clientX;g.s=track.scrollLeft;track.setPointerCapture(e.pointerId);cl.add(R+'--drag');run()}
fade||(ss('none'),track.scrollLeft=g.s-e.clientX+g.x);
},D);
on('pointerup',e=>{
const s=g;g=0;if(!drag)return;
const best=near();drag=touch=0;cl.remove(R+'--drag');setTimeout(()=>moved=0);user();
const q=e.timeStamp-s.t<300;
q&&attr('long')?to(cur):best!=cur?to(best):fade||q?mv(fade?(e.clientX<s.x)!=rtl()?1:-1:A(track.scrollLeft)>A(s.s)?1:-1):to(cur);
run();
},D);
ed||on('dragstart',e=>e.preventDefault(),track);
on('click',e=>{moved&&(e.preventDefault(),e.stopPropagation())},track,1);
fade||on('scroll',()=>{
if(anim||drag)return;
clearTimeout(wait);
wait=setTimeout(()=>{
const lost=all[cur]?.contains(D.activeElement),best=near(),was=touch;
touch=0;
if(best==cur)return;
if(!was)return glide(1);
user();
to(best,1);
fix()&&paint();
run();
lost&&(dots[page]&&!dots[page].hidden?dots[page]:next)?.focus({preventScroll:true});
},120);
},track);
const api=root.digiCrs={go:i=>{i=+i||0;fix();let t=lc+i;lc&&(t+=n()*M.round((cur-t)/n()),t<0||t>all.length-view)&&(t=lc+i);sl(t);run()},index:()=>real(cur),count:()=>n(),fit,see:v=>{seen=v;run()}};
on(E+'go',e=>e.target==root&&api.go(e.detail));
on('click',e=>{const k=[].indexOf.call(all,e.target.closest?.('.'+C+'track>*'));if(k<0)return;main&&emit('go',real(k),main);if(attr('click')&&k!=cur){user();sl(k);run()}},track);
try{main=D.querySelector(attr('sync'))}catch(x){}
if(delay){
on('visibilitychange',run,D);
(io=io||new IntersectionObserver(l=>l.forEach(en=>en.target.digiCrs?.see(en.isIntersecting)))).observe(root);
}
(ro=ro||new ResizeObserver(l=>l.forEach(en=>en.target.parentNode.digiCrs?.fit()))).observe(track);
delay&&pause&&(pause.hidden=!1);
fit();
if(main){
const mark=i=>{each(all,(s,k)=>s.classList.toggle(C+'synced',real(k)==i));api.go(i)};
main.addEventListener(E+'change',e=>e.target==main&&mark(e.detail.index));
mark(main.digiCrs?.index()||0);
}
run();
cl.add(R+'--ready');
};
const scan=s=>each((s?.querySelectorAll?s:D).querySelectorAll('.'+R),init);
const hook=()=>F().hooks.addAction('frontend/element_ready/global',s=>scan(s?.[0]));
F()?.hooks?hook():W.addEventListener('elementor/frontend/init',hook);
D.readyState=='loading'?D.addEventListener('DOMContentLoaded',()=>scan()):scan();
})();
