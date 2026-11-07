/* DIGITALÍSIMO Elements · Lista de videos (C2): ver widgets/class-video-playlist.php. Deriva de PRO Elements (GPL). */
(()=>{
const W=window,D=document,YT=/^https:\/\/www\.youtube(-nocookie)?\.com$/,VM=/^https:\/\/player\.vimeo\.com$/,all=new Set(),
yt=s=>YT.test(new URL(s).origin),
send=(f,m)=>{try{f.contentWindow.postMessage(JSON.stringify(m),'*')}catch(x){}},
store=(k,v)=>{try{if(v===void 0)return JSON.parse(W.localStorage.getItem(k)||'[]');W.localStorage.setItem(k,JSON.stringify(v))}catch(x){}return[]},
move=(l,t,k,rtl)=>{const i=l.indexOf(t),s={ArrowDown:1,ArrowUp:-1,ArrowRight:rtl?-1:1,ArrowLeft:rtl?1:-1}[k];return k=='Home'?l[0]:k=='End'?l[l.length-1]:s?l[(i+s+l.length)%l.length]:null},
mark=(t,on)=>{t.classList.toggle(t.classList[0]+'--on',on);t.setAttribute('aria-selected',on);t.tabIndex=on?0:-1};
// Fin del vídeo de un servicio (postMessage).
W.addEventListener('message',e=>{
if(!YT.test(e.origin)&&!VM.test(e.origin))return;
let d=e.data;try{if(typeof d=='string')d=JSON.parse(d)}catch(x){return}
if(d&&(d.event=='ended'||d.event=='onStateChange'&&d.info===0||d.event=='infoDelivery'&&d.info&&d.info.playerState===0))all.forEach(p=>p.f&&p.f.contentWindow===e.source&&p.end());
});
const init=root=>{
if(root.digiVpl)return;
const id=root.dataset.digiVpl||'',key='digi-vpl-watched-'+id,has=a=>root.hasAttribute('data-'+a),
tabs=[...root.querySelectorAll('.digi-vpl__tab')],fold=root.querySelector('.digi-vpl__fold'),
rtl=()=>getComputedStyle(root).direction=='rtl',
panel=t=>D.getElementById(t.getAttribute('aria-controls')),
inner=t=>t&&root.querySelector('.digi-vpl__in[data-tab="'+t.dataset.tab+'"]'),
edit=W.elementorFrontend?.isEditMode?.();
let cur=tabs.find(t=>t.classList.contains('digi-vpl__tab--on'))||tabs[0];
// Reproductor: el <video> o el iframe, creado al reproducir.
const player=t=>{
const p=panel(t);if(!p)return null;if(p.digi)return p.digi;
const v=p.querySelector('video'),src=p.dataset.src,o={f:null,
end(){
if(has('watched')){t.classList.add('digi-vpl__tab--seen');const s=store(key);if(!s.includes(t.dataset.item))store(key,s.concat(t.dataset.item))}
const n=tabs[tabs.indexOf(t)+1];if(has('next')&&n&&t===cur)pick(n,1)},
play(muted){
p.querySelector('.digi-vpl__overlay')?.remove();
if(v){v.muted=!!muted;v.play()?.catch?.(()=>{});return}
if(!src)return;
if(o.f)return send(o.f,yt(src)?{event:'command',func:'playVideo',args:''}:{method:'play'});
const f=o.make(1,muted);if(!muted)f.focus()},
// El iframe del servicio: en marcha al reproducir; sin reproducir, el del primer vídeo al cargar (como PRO).
make(auto,muted){
const f=o.f=D.createElement('iframe'),y=yt(src);
f.src=src+(auto?'&autoplay=1':'')+(muted?y?'&mute=1':'&muted=1':'')+(y?'&origin='+encodeURIComponent(location.origin):'');
f.title=p.dataset.title||'';f.allow='autoplay; encrypted-media; picture-in-picture; fullscreen';f.allowFullscreen=true;
f.onload=()=>send(f,y?{event:'listening',id:1,channel:'widget'}:{method:'addEventListener',value:'ended'});
p.querySelector('.digi-vpl__player').replaceChildren(f);return f},
pause(){v?v.pause():o.f&&send(o.f,yt(src)?{event:'command',func:'pauseVideo',args:''}:{method:'pause'})}};
v?.addEventListener('ended',()=>o.end());
all.add(o);return p.digi=o};
const url=t=>{try{const q=new URLSearchParams(location.search);q.set('playlist',id);q.set('video',t.dataset.item);history.replaceState(history.state,'',location.pathname+'?'+q+location.hash)}catch(x){}};
const more=b=>b&&b.querySelectorAll('.digi-vpl__in-panel--fold:not([hidden])').forEach(p=>{const m=p.querySelector('.digi-vpl__more'),x=p.querySelector('.digi-vpl__in-text');if(m&&x)m.hidden=!p.classList.contains('digi-vpl__in-panel--open')&&x.firstElementChild.offsetHeight<=x.clientHeight});
const pick=(t,play)=>{
if(!t)return;
if(t!==cur){player(cur)?.pause();tabs.forEach(x=>{const on=x===t,p=panel(x),i=inner(x);mark(x,on);if(p)p.hidden=!on;if(i)i.hidden=!on});cur=t;more(inner(t))}
if(play){player(t)?.play();url(t)}};
tabs.forEach(t=>{
t.onclick=()=>pick(t,1);
t.onkeydown=e=>{if(e.key=='Enter'||e.key==' '){e.preventDefault();return pick(t,1)}const to=move(tabs,t,e.key,rtl());if(to){e.preventDefault();to.focus()}}});
root.querySelectorAll('.digi-vpl__play').forEach(b=>b.onclick=()=>pick(tabs.find(t=>panel(t)?.contains(b)),1));
root.querySelectorAll('.digi-vpl__in-list').forEach(l=>{
const own=[...l.children],mob=[...l.parentNode.querySelectorAll('.digi-vpl__in-mob')],ctl=x=>x.getAttribute('aria-controls'),
sel=t=>{own.forEach(x=>{const p=D.getElementById(ctl(x));mark(x,x===t);if(p)p.hidden=x!==t});mob.forEach(m=>m.setAttribute('aria-expanded',ctl(m)===ctl(t)));more(l.parentNode)};
own.forEach(t=>{t.onclick=()=>sel(t);t.onkeydown=e=>{const to=move(own,t,e.key,rtl());if(to){e.preventDefault();to.focus();sel(to)}}});
// Móvil, como PRO: el título de un panel cerrado lo abre en lugar del otro.
mob.forEach(m=>m.onclick=()=>{const t=own.find(x=>ctl(x)===ctl(m));if(t&&own.length>1)sel(t)})});
// Degradados de la lista, como PRO: abajo al iniciar si desborda; al desplazarla, arriba y abajo según quede contenido.
const items=root.querySelector('.digi-vpl__items'),shade=e=>{const c=items.parentNode.classList,t=items.scrollTop,h=items.scrollHeight,o=items.offsetHeight;if(!e)return o<h&&c.add('digi-vpl__list--bottom');c.toggle('digi-vpl__list--top',t>0);c.toggle('digi-vpl__list--bottom',o+t<h)};
if(items){shade();items.addEventListener('scroll',shade,{passive:!0})}
root.querySelectorAll('.digi-vpl__more').forEach(b=>b.onclick=()=>{const o=b.parentNode.classList.toggle('digi-vpl__in-panel--open');b.setAttribute('aria-expanded',o);b.textContent=b.dataset[o?'less':'more']});
if(fold)fold.onclick=()=>{const o=fold.getAttribute('aria-expanded')!='true';fold.setAttribute('aria-expanded',o);root.classList.toggle('digi-vpl--closed',!o)};
if(has('watched')){const s=store(key);tabs.forEach(t=>s.includes(t.dataset.item)&&t.classList.add('digi-vpl__tab--seen'))}
// ?playlist=&video=: se elige sin reproducir.
const q=new URLSearchParams(location.search);
if(!edit&&id&&q.get('playlist')===id){pick(tabs.find(t=>t.dataset.item===q.get('video'))||tabs[0]);root.scrollIntoView({behavior:W.matchMedia?.('(prefers-reduced-motion:reduce)').matches?'auto':'smooth'})}
more(inner(cur));
// Primer vídeo, como PRO, al cargar o, con «Carga diferida», al acercarse: con «Al cargar», silenciado y en marcha; sin
// él, el reproductor del servicio ya creado. Los de un servicio, sólo sin las fachadas del sitio (data-embed).
const first=cur&&panel(cur),auto=!edit&&has('onload');
if(first&&(auto&&first.querySelector('video')||has('embed')&&first.dataset.src)){
const go=()=>{const o=player(cur);if(auto){o.play(1);url(cur)}else if(!o.f)o.make()};
if(has('lazy')&&W.IntersectionObserver){const io=new IntersectionObserver(en=>{if(en.some(x=>x.isIntersecting)){io.disconnect();go()}});io.observe(root.querySelector('.digi-vpl__main')||root)}else go()}
root.digiVpl={pick:(n,play)=>pick(tabs[n-1],play)};
};
const scan=s=>(s||D).querySelectorAll('[data-digi-vpl]').forEach(init),
hook=()=>W.elementorFrontend.hooks.addAction('frontend/element_ready/digitalisimo-video-playlist.default',s=>scan(s?.[0]));
D.readyState=='loading'?D.addEventListener('DOMContentLoaded',()=>scan()):scan();
W.elementorFrontend?.hooks?hook():W.addEventListener('elementor/frontend/init',hook);
})();
