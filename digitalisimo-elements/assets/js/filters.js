/* DIGITALÍSIMO Elements F4-Woo V.8.3: coordinador de filtros del Loop Grid (fetch propio, AbortController, popstate, foco). */
((w,d)=>{
'use strict';
if(w.digitalisimoWooFilters)return;
const fe=w.elementorFrontend,F=[],E=encodeURIComponent;
let installed=0,silent=0;
const J=s=>{try{return JSON.parse(s||'{}')||{}}catch(e){return{}}};
const Q=(el,s)=>[...el.querySelectorAll(s)];
const at=(el,n)=>el.getAttribute(n);
const split=v=>v?v.split(v.indexOf('~')>-1?'~':' ').filter(Boolean):[];
const rest=()=>{const c=w.ElementorProFrontendConfig;return c&&c.urls&&c.urls.rest};
const edit=()=>!!(fe&&fe.isEditMode&&fe.isEditMode());
const active=()=>!!(installed&&!edit()&&rest()&&w.fetch&&w.AbortController);
const docOf=el=>el&&el.closest?el.closest('[data-elementor-id]'):null;
const findLoop=(id,src)=>{const s='.elementor-element-'+id,doc=docOf(src);return(doc&&doc.querySelector(s))||d.querySelector(s)};
const coordinated=id=>active()&&F.some(f=>f.loop===id);
const inDoc=(doc,list)=>{const m=doc?list.filter(x=>doc.contains(x.el)):[];return m.length?m:list};
// Filtros de PRO del bucle, por su marcado público.
const pros=(id,doc)=>inDoc(doc,Q(d,'.elementor-widget-taxonomy-filter').map(el=>{
const s=J(at(el,'data-settings'));
return{el,id:at(el,'data-id'),tax:s.taxonomy,loop:s.selected_element,join:s.multiple_selection==='yes'&&/^(AND|OR)$/.test(s.logical_combination)?s.logical_combination:'DISABLED'};
}).filter(p=>p.loop===id&&p.tax));
// Estado en el formato del servidor.
const state=(id,L)=>{
const doc=docOf(L),wf={},flat={},put=(t,k,v)=>{(wf[t]=wf[t]||{})[k]=v;flat[k]=v};
inDoc(doc,F.filter(f=>f.loop===id)).forEach(f=>{const r=f.read();Object.keys(r).forEach(k=>put(f.type,k,r[k]))});
pros(id,doc).forEach(p=>{
const prev=(wf.taxonomy||{})[p.tax],t=Q(p.el,'.e-filter-item[aria-pressed="true"][data-filter]').map(b=>at(b,'data-filter')).filter(x=>x!=='__all'&&!(prev&&prev.terms.includes(x))),all=prev?prev.terms.concat(t):t;
if(all.length)put('taxonomy',p.tax,{terms:all,logicalJoin:p.join==='DISABLED'?'AND':p.join});
});
return{wf,flat};
};
const help=src=>{const h=src&&src.querySelector('.e-filter');return{base:h?at(h,'data-base-url'):location.href,page:h?+at(h,'data-page-num')||1:1}};
const push=(id,flat,src)=>{
const pre='e-filter-'+id+'-',h=help(src),parts=[];
let path=location.pathname;
new URLSearchParams(location.search).forEach((v,k)=>{if(k.indexOf(pre)&&k!=='e-page-'+id&&!(h.page>1&&/^(page|paged)$/.test(k)))parts.push(E(k)+'='+E(v))});
Object.keys(flat).forEach(k=>parts.push(E(pre+k)+'='+flat[k].terms.map(E).join(flat[k].logicalJoin==='OR'?'~':'+')));
if(h.page>1&&h.base)try{path=new URL(h.base,location.href).pathname}catch(e){}
const url=path+(parts.length?'?'+parts.join('&'):'')+location.hash;
if(url!==location.pathname+location.search+location.hash)history.pushState(null,'',url);
};
const region=L=>{
if(!L.__digiStatus&&L.parentNode){
const r=L.__digiStatus=d.createElement('div');
r.className='digi-filter-status';
r.setAttribute('role','status');
L.parentNode.insertBefore(r,L.nextSibling);
}
return L.__digiStatus;
};
const busy=(el,on)=>{el.setAttribute('aria-busy',String(on));el.classList.toggle('e-loading-overlay',on);el.classList.toggle('e-loading',on)};
const insert=(id,L,html)=>{
const old=L.querySelector(':scope > .elementor-widget-container')||L.querySelector('.elementor-widget-container'),box=d.createElement('div');
box.innerHTML=html.trim();
let neu=box.firstElementChild;
if(!neu){neu=d.createElement('div');neu.className='elementor-widget-container'}
const a=d.activeElement,had=!!(old&&a&&old.contains(a)),h=fe&&fe.elementsHandler,c=w.ElementorProFrontendConfig;
if(old)L.replaceChild(neu,old);else L.appendChild(neu);
if(h)Q(neu,'.e-loop-item').forEach(it=>Q(it,'.elementor-element').forEach(el=>h.runReadyTrigger(el)));
if(c&&c.settings&&c.settings.lazy_load_background_images)d.dispatchEvent(new Event('elementor/lazyload/observe'));
if(h)h.runReadyTrigger(L);
if(had){L.setAttribute('tabindex','-1');L.focus()}
const f=F.find(x=>x.loop===id),t=f?J(at(f.el,'data-status')):{},n=neu.querySelectorAll('.e-loop-item').length;
const paged=neu.querySelector('.elementor-pagination,.e-load-more-anchor[data-max-page]:not([data-max-page="1"])');
region(L).textContent=(!n?t.none:paged?t.updated:n===1?t.one:(t.many||'').replace('%d',n))||'';
};
const run=(id,L,src,o)=>{
const r=L.__digi,st=state(id,L),sig=JSON.stringify(st.wf);
if(o.ifChanged&&sig===r.last)return;
r.last=sig;
if(o.push)push(id,st.flat,src);
if(r.ctrl)r.ctrl.abort();
const ctrl=r.ctrl=new AbortController(),seq=++r.seq,doc=docOf(L),post=fe&&fe.config&&fe.config.post;
const body={post_id:+(doc&&at(doc,'data-elementor-id'))||(post&&post.id)||0,widget_id:id,widget_filters:st.wf,pagination_base_url:help(src).base||location.href};
busy(L,true);
return w.fetch(rest()+'elementor-pro/v1/refresh-loop',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body),signal:ctrl.signal})
.then(res=>res.ok?res.json():null).catch(()=>null)
.then(data=>{
if(seq!==r.seq)return;
r.ctrl=null;
busy(L,false);
if(data&&typeof data.data==='string')insert(id,L,data.data);
});
};
const schedule=(id,src,delay,o)=>{
const L=findLoop(id,src);
if(!L)return;
const r=L.__digi||(L.__digi={seq:0});
clearTimeout(r.timer);
r.timer=setTimeout(()=>run(id,L,src,o||{push:1}),delay||0);
};
const api=w.digitalisimoWooFilters={
version:2,
active,
edit,
split,
// f: { type, keys, read(), write(params) }.
register(loopId,el,f){
if(!/^[a-z0-9]{7}$/.test(loopId)||!el||F.some(x=>x.el===el))return false;
F.push(Object.assign({keys:[],read:()=>({}),write:()=>{}},f,{el:el,loop:loopId}));
const L=findLoop(loopId,el);
if(L)region(L);
return true;
},
state:loopId=>state(loopId,findLoop(loopId,null)).wf,
refresh(loopId,filterId,delay){
if(silent||!coordinated(loopId))return;
schedule(loopId,filterId&&filterId.nodeType?filterId:d.querySelector('[data-id="'+filterId+'"]'),delay||0);
},
};
const mine=k=>F.some(f=>!k.indexOf('e-filter-'+f.loop+'-'));
// Subclase del módulo de PRO por su filtro público, antes de que PRO lo cree.
if(fe&&fe.hooks)fe.hooks.addFilter('elementor-pro/frontend/handlers',h=>{
const B=h&&h.taxonomyFilter,P=B&&B.prototype;
if(!P||['refreshLoopWidget','getQueryStringInObjectForm','setFilterDataForLoopWidget','removeFilterFromLoopWidget'].some(m=>typeof P[m]!=='function'))return h;
installed=1;
return Object.assign({},h,{taxonomyFilter:class extends B{
refreshLoopWidget(l,id){return coordinated(l)?api.refresh(l,id):super.refreshLoopWidget(l,id)}
getQueryStringInObjectForm(){
const q=super.getQueryStringInObjectForm();
Object.keys(q).forEach(k=>{if(mine(k))delete q[k]});
new URLSearchParams(location.search).forEach((v,k)=>{if(mine(k))q[k]=v});
return q;
}
}});
},20);
// Atrás y Adelante, sin pushState.
w.addEventListener('popstate',()=>{
if(!active()||!F.length)return;
const p=new URLSearchParams(location.search),mod=((w.elementorProFrontend||{}).modules||{}).taxonomyFilter,todo=[];
let unknown=0;
F.forEach(f=>f.write(p));
[...new Set(F.map(f=>f.loop))].forEach(id=>{
const pre='e-filter-'+id+'-',known={},list=pros(id,null);
F.forEach(f=>{if(f.loop===id){f.keys.forEach(k=>{known[k]=1});todo.push([id,f.el])}});
list.forEach(x=>{known[x.tax]=1});
p.forEach((v,k)=>{if(!k.indexOf(pre)&&!known[k.slice(pre.length)])unknown=1});
list.forEach(x=>{
const t=split(p.get(pre+x.tax));
Q(x.el,'.e-filter-item').forEach(b=>{const v=at(b,'data-filter');b.setAttribute('aria-pressed',String(t.length?t.includes(v):v==='__all'))});
if(!mod)return;
silent=1;
try{
mod.removeFilterFromLoopWidget(id,x.id,'','');
if(t.length)mod.setFilterDataForLoopWidget(id,x.id,{filterType:'taxonomy',filterData:{selectedTaxonomy:x.tax,terms:t}},false,x.join);
}catch(e){}
silent=0;
});
});
if(unknown)return location.reload();
todo.forEach(t=>schedule(t[0],t[1],0,{push:0,ifChanged:1}));
});
})(window,document);
