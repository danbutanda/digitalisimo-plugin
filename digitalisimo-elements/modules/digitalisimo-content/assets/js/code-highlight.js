/* Código resaltado (digitalisimo-code-highlight): carga Prism 1.23 del plugin (data-digi-prism) sólo cuando un bloque se
   acerca a la pantalla, y sólo los complementos que usa; botón «Copiar» propio. Sin dependencias ni peticiones a terceros. */
(()=>{'use strict';
const W=window,D=document,got={},R='digi-code';
const load=(u,a)=>got[u]||(got[u]=new Promise((ok,ko)=>{const s=D.createElement('script');s.src=u;s.async=false;if(a)s.setAttribute('data-autoloader-path',a);s.onload=ok;s.onerror=()=>ko(u);D.head.appendChild(s)}));
const prism=r=>{const b=r.dataset.digiPrism||'',P=W.Prism;
if(!b&&!(P&&P.highlightAllUnder))return Promise.reject();
let core=Promise.resolve();
if(!(P&&P.highlightAllUnder)){(W.Prism=P||{}).manual=true;core=load(b+'components/prism-core.min.js')}
return core.then(()=>{const p=W.Prism.plugins,l=[];
if(!p.autoloader)l.push(load(b+'plugins/autoloader/prism-autoloader.min.js',b+'components/'));
if(!p.NormalizeWhitespace)l.push(load(b+'plugins/normalize-whitespace/prism-normalize-whitespace.min.js'));
if(!p.lineNumbers&&r.querySelector('pre.line-numbers'))l.push(load(b+'plugins/line-numbers/prism-line-numbers.min.js'));
if(r.querySelector('pre[data-line]'))l.push(load(b+'plugins/line-highlight/prism-line-highlight.min.js'));
return Promise.all(l)})};
const run=r=>prism(r).then(()=>W.Prism.highlightAllUnder(r),()=>{});
const copy=r=>{const b=r.querySelector('.'+R+'__copy');if(!b||!(W.navigator.clipboard||D.queryCommandSupported?.('copy')))return;
const label=b.textContent;b.hidden=false;
b.addEventListener('click',()=>{const c=r.querySelector('code').cloneNode(true);c.querySelectorAll('.line-highlight,.line-numbers-rows').forEach(n=>n.remove());const t=c.textContent;
const end=ok=>{b.textContent=ok?b.dataset.digiDone:b.dataset.digiFail;clearTimeout(b.digiT);b.digiT=setTimeout(()=>{b.textContent=label},5000)};
const old=()=>{const a=D.createElement('textarea');a.value=t;a.setAttribute('readonly','');a.style.cssText='position:fixed;top:0;opacity:0';D.body.appendChild(a);a.select();let ok=false;try{ok=D.execCommand('copy')}catch(e){}a.remove();b.focus();end(ok)};
W.navigator.clipboard?W.navigator.clipboard.writeText(t).then(()=>end(true),old):old()})};
const I=W.IntersectionObserver,io=I&&new I(es=>es.forEach(e=>{if(e.isIntersecting){io.unobserve(e.target);run(e.target)}}),{rootMargin:'100% 0px'});
const init=r=>{if(r.digiCode)return;r.digiCode=1;copy(r);io?io.observe(r):run(r)};
const scan=s=>(s&&s.querySelectorAll?s:D).querySelectorAll('.'+R).forEach(init);
const hook=()=>W.elementorFrontend.hooks.addAction('frontend/element_ready/global',s=>scan(s&&s[0]));
W.elementorFrontend&&W.elementorFrontend.hooks?hook():W.addEventListener('elementor/frontend/init',hook);
D.readyState=='loading'?D.addEventListener('DOMContentLoaded',()=>scan()):scan();
})();
