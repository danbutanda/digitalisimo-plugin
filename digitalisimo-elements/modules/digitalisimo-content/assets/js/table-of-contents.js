/* Tabla de contenido (digitalisimo-table-of-contents): lista de los encabezados de la página como la de PRO (mismos
   selectores y exclusiones), sin jQuery ni innerHTML con texto de la página: anclas antes de los encabezados sin id
   (fuera del editor), vista jerárquica o plana, minimizar con su punto de corte, plegado y enlace activo. */
(()=>{
'use strict';
const W=window,D=document,done=new WeakSet(),T='digi-toc__',
DOCS='.elementor:not([data-elementor-type="header"]):not([data-elementor-type="footer"]):not([data-elementor-type="popup"])',
all=(r,s)=>{try{return s?[...r.querySelectorAll(s)]:[]}catch(e){return[]}},
within=(el,s)=>{try{return!!(s&&el.closest(s))}catch(e){return false}},
make=(t,c)=>{const e=D.createElement(t);if(c)e.className=c;return e},
editing=()=>!!W.elementorFrontend?.isEditMode?.()||D.body.classList.contains('elementor-editor-active');
const headings=(root,c)=>{
const doc=root.closest('.elementor'),seen=new Set(),out=[];
const scope=c.container?all(D,c.container):doc&&doc.getAttribute('data-elementor-type')=='popup'?[doc]:all(D,DOCS);
for(const s of scope)for(const h of all(s,(c.tags||[]).join(',')))if(!seen.has(h)&&!h.matches('.'+T+'title,.elementor-toc__header-title')&&!within(h,c.exclude)){seen.add(h);out.push(h)}
return out.sort((a,b)=>a.compareDocumentPosition(b)&4?-1:1);
};
const init=root=>{
if(done.has(root))return;
done.add(root);
let c;
try{c=JSON.parse(root.dataset.digiToc)||{}}catch(e){return}
const body=root.querySelector('.'+T+'body'),btn=root.querySelector('.'+T+'toggle'),host=root.closest('.elementor-element')||root.parentElement||root,
edit=editing(),flat=!c.nested,links=[],seen=new Set(),q=s=>root.querySelector('.'+T+s);
let active=[],open=[],clicked=false,last=0,p=0;
const collapse=on=>{host.classList.toggle('digi-toc--collapsed',on);btn&&btn.setAttribute('aria-expanded',!on)};
btn&&btn.addEventListener('click',()=>collapse(btn.getAttribute('aria-expanded')=='true'));
const list=headings(root,c);
if(!list.length){const e=q('empty');body.replaceChildren(e?e.content.cloneNode(true):'');return}
const data=list.map((h,i)=>{const w=h.closest('.elementor-widget'),id=h.id||w&&w.id||'';return{tag:+h.nodeName.slice(1),text:h.textContent,id:id||'elementor-toc__heading-anchor-'+i,own:!!id,el:h,level:0}});
if(!flat)data.forEach((h,i)=>{for(let j=i-1;j>=0;j--)if(data[j].tag<=h.tag){h.level=data[j].level+(data[j].tag<h.tag?1:0);break}});
if(!edit)for(const d of data){
if(d.own){d.anchor=D.getElementById(d.id);continue}
let a=d.el.previousElementSibling;
if(!a||a.id!=d.id){a=make('span',T+'anchor');a.id=d.id;d.el.before(a)}
d.anchor=a;
}
const marker=c.view=='bullets'&&q('marker');
const level=lv=>{
const ul=make(c.view=='numbers'?'ol':'ul',T+'list');
while(p<data.length){
const d=data[p];
if(lv>d.level)break;
const li=make('li',T+'item'),row=make('div',T+'row'),a=make('a',T+'link'+(!flat&&!d.level?' '+T+'link--top':''));
a.setAttribute('href','#'+d.id);
a.textContent=d.text;
a.digiAnchor=d.anchor;
links.push(a);
marker&&row.append(marker.content.cloneNode(true));
row.append(a);
li.append(row);
ul.append(li);
p++;
if(data[p]&&lv<data[p].level)li.append(level(data[p].level));
}
return ul;
};
body.replaceChildren(level(0));
// Lista que se despliega con un enlace activo (con «Plegar subelementos»): la suya si es de primer nivel; si no, la
// de su elemento de primer nivel.
const sub=a=>{
if(a.classList.contains(T+'link--top'))return a.parentNode.nextElementSibling;
const up=[];
for(let n=a.parentNode;n&&n!=body;n=n.parentNode)if(n.classList.contains(T+'list'))up.push(n);
return up[up.length-2]||null;
};
const activate=items=>{
for(const a of active)if(!items.includes(a)){a.classList.remove(T+'link--active');a.removeAttribute('aria-current')}
const show=[];
for(const a of items){
a.classList.add(T+'link--active');
a.setAttribute('aria-current','true');
const l=c.collapse&&sub(a);
if(l&&!show.some(x=>x.contains(l)))show.push(l);
}
for(const l of open)if(!show.some(x=>l.contains(x)))l.classList.remove(T+'list--open');
for(const l of show)l.classList.add(T+'list--open');
active=items;
open=show;
};
const update=dir=>{
const now=links.filter(a=>a.digiAnchor&&seen.has(a.digiAnchor));
if(now.length)return activate(now);
if(dir!='up')return;
const i=links.indexOf(active[0]);
if(!i)activate([]);else if(i>0)activate([links[i-1]]);
};
body.addEventListener('click',e=>{
const a=e.target.closest&&e.target.closest('.'+T+'link');
if(!a)return;
clicked=true;
const end=()=>{if(clicked){clicked=false;update()}},timer=setTimeout(end,2e3);
W.addEventListener('scrollend',()=>{clearTimeout(timer);end()},{once:true});
const l=a.parentNode.nextElementSibling,shut=c.collapse&&a.classList.contains(T+'link--top')&&l&&l.classList.contains(T+'list--open');
activate([a]);
if(shut)l.classList.remove(T+'list--open');
});
if(!edit&&W.IntersectionObserver){
const io=new IntersectionObserver(entries=>{
const top=D.documentElement.scrollTop,down=top>last;
last=top<=0?0:top;
for(const e of entries)e.isIntersecting?seen.add(e.target):seen.delete(e.target);
clicked||update(down?'down':'up');
},{rootMargin:'0px',threshold:0});
for(const a of links)a.digiAnchor&&io.observe(a.digiAnchor);
}
// Como PRO: con la caja minimizable, empieza minimizada en su punto de corte (se evalúa al cargar).
if(btn&&(c.minimize=='all'||c.minimize>0&&W.matchMedia('(max-width: '+c.minimize+'px)').matches))collapse(true);
};
const scan=s=>all(s?.querySelectorAll?s:D,'.digi-toc[data-digi-toc]').forEach(init);
const hook=()=>W.elementorFrontend.hooks.addAction('frontend/element_ready/global',s=>scan(s?.[0]));
W.elementorFrontend?.hooks?hook():W.addEventListener('elementor/frontend/init',hook);
D.readyState=='loading'?D.addEventListener('DOMContentLoaded',()=>scan()):scan();
})();
