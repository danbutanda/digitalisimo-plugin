/* DIGITALÍSIMO Elements F4-Woo V.8: formularios de los filtros de precio y de atributo. Sin JS funcionan por GET; con el coordinador, el Loop Grid se refresca sin recargar. */
((w,d)=>{
'use strict';
const fe=w.elementorFrontend,C=w.digitalisimoWooFilters||{active:()=>0,edit:()=>0,register:()=>0,refresh:()=>0};
if(C.form)return;
C.form=1;
const Q=(el,s)=>[...el.querySelectorAll(s)],at=(el,n)=>el.getAttribute(n),edges=['min','max'];
const J=s=>{try{return JSON.parse(s||'{}')||{}}catch(e){return{}}};
const split=v=>v?v.split(v.indexOf('~')>-1?'~':' ').filter(Boolean):[];
const key=(ds,e)=>e==='min'?ds.keyMin:ds.keyMax;
// Importe para aria-valuetext con el formato de la tienda (data-currency).
const money=el=>{
const c=J(at(el,'data-currency'));
Q(el,'input[type=range]').forEach(r=>{
const p=(+r.value).toFixed(c.d==null?2:c.d).split('.');
p[0]=p[0].replace(/\B(?=(\d{3})+(?!\d))/g,c.ts||'');
r.setAttribute('aria-valuetext',(c.f||'%1$s%2$s').replace('%1$s',c.s||'').replace('%2$s',p.join(c.ds||'.')));
});
};
// Estado del formulario: { nombre: { terms, logicalJoin } }. Un deslizador en su límite no filtra.
const read=el=>{
const ds=el.dataset,o={};
if(ds.digiFilter==='price'){
edges.forEach(e=>{
const i=el.querySelector(`input[type=number][data-digi-edge=${e}]`)||el.querySelector(`input[type=range][data-digi-edge=${e}]`);
let v=i?i.value.trim():'';
if(i&&i.type==='range'&&+v===+ds[e])v='';
if(v!==''&&isFinite(v))o[key(ds,e)]={terms:[v],logicalJoin:'AND'};
});
return o;
}
const t=[...Q(el,'input:checked').map(i=>i.value),...Q(el,'select').map(s=>s.value),...Q(el,'[aria-pressed="true"][data-value]').map(b=>at(b,'data-value'))].filter(Boolean);
if(t.length)o[ds.key]={terms:t,logicalJoin:t.length>1?ds.logic:'AND'};
return o;
};
const write=(el,pre,p)=>{
const ds=el.dataset;
if(ds.digiFilter==='price'){
edges.forEach(e=>{const v=p.get(pre+key(ds,e))||'';Q(el,`[data-digi-edge=${e}]`).forEach(i=>{i.value=i.type==='range'?v||ds[e]:v})});
return money(el);
}
const t=split(p.get(pre+ds.key));
Q(el,'input[type=checkbox],input[type=radio]').forEach(i=>{i.checked=i.value?t.includes(i.value):!t.length});
Q(el,'select').forEach(s=>{s.value=t[0]||''});
Q(el,'[data-value]').forEach(b=>b.setAttribute('aria-pressed',String(t.includes(at(b,'data-value')))));
};
// Tienda: los parámetros nativos de WooCommerce, con las casillas de un atributo unidas por comas.
const shopUrl=form=>{
const out=new URLSearchParams(),lists={},ds=form.dataset;
new FormData(form).forEach((v,k)=>{
const i=form.querySelector(`[name="${k}"]`);
if(v===''||(i&&i.type==='range'&&+v===+ds[at(i,'data-digi-edge')]))return;
if(i&&i.type==='checkbox')(lists[k]=lists[k]||[]).push(v);else out.append(k,v);
});
Object.keys(lists).forEach(k=>out.set(k,lists[k].join(',')));
const q=out.toString();
return at(form,'action').split('?')[0]+(q?'?'+q:'');
};
const init=form=>{
if(at(form,'data-digi-ready'))return;
form.setAttribute('data-digi-ready','1');
const ds=form.dataset,price=ds.digiFilter==='price',loop=ds.target==='loop'&&ds.loop?ds.loop:'',pre='e-filter-'+loop+'-';
const ajax=()=>!!loop&&C.active(),go=t=>{if(ajax())C.refresh(loop,form,t)};
if(loop)C.register(loop,form,{type:price?'digitalisimo_price':'digitalisimo_attribute',keys:price?[ds.keyMin,ds.keyMax]:[ds.key],read:()=>read(form),write:p=>write(form,pre,p)});
if(ajax()?!price||ds.apply==='change':!loop&&price&&ds.apply==='change')form.classList.add('digi-filter--live');
form.addEventListener('submit',ev=>{
if(C.edit())return ev.preventDefault();
if(!loop){ev.preventDefault();location.assign(shopUrl(form))}else if(ajax()){ev.preventDefault();go(0)}
});
form.addEventListener('input',ev=>{
const i=ev.target,e=at(i,'data-digi-edge');
if(!e)return;
const o=form.querySelector(`input[type=range][data-digi-edge=${e==='min'?'max':'min'}]`);
if(i.type==='range'&&o&&(e==='min'?+i.value>+o.value:+i.value<+o.value))i.value=o.value;
Q(form,`[data-digi-edge=${e}]`).forEach(x=>{if(x!==i)x.value=x.type==='range'?(i.value===''?ds[e]:i.value):(+i.value===+ds[e]?'':i.value)});
money(form);
if(ds.apply==='change')go(300);
});
form.addEventListener('change',ev=>{
const t=ev.target;
if(!loop&&ds.apply==='change'&&at(t,'data-digi-edge'))location.assign(shopUrl(form));
else if(t.matches('select'))go(0);
else if(t.matches('input[type=checkbox],input[type=radio]'))go(300);
});
form.addEventListener('click',ev=>{
const b=ev.target.closest('[data-value],.digi-filter__clear');
if(!b||!form.contains(b))return;
if(C.edit())return ev.preventDefault();
if(!ajax())return;
ev.preventDefault();
if(b.matches('.digi-filter__clear'))write(form,pre,new URLSearchParams());
else{
const on=at(b,'aria-pressed')!=='true';
if(ds.multiple!=='1')Q(form,'[data-value]').forEach(x=>x.setAttribute('aria-pressed','false'));
b.setAttribute('aria-pressed',String(on));
}
go(0);
});
form.addEventListener('keydown',ev=>{if(ev.key===' '&&ev.target.matches('[role=button]')){ev.preventDefault();ev.target.click()}});
if(price)money(form);
};
const scan=root=>Q(root,'form.digi-filter').forEach(init);
// Con el núcleo, en element_ready (sus `hooks` existen desde init(), que avisa con elementor/frontend/init) y, por si
// algún widget no lo recibe, al cargar la página; sin el núcleo, al estar el DOM.
let bound=0;
const bind=()=>{if(bound||!fe.hooks)return;bound=1;['price','attribute'].forEach(n=>fe.hooks.addAction(`frontend/element_ready/digitalisimo-woo-${n}-filter.default`,$s=>scan($s[0]||$s)))};
if(fe){bind();if(!bound)w.addEventListener('elementor/frontend/init',bind);w.addEventListener('load',()=>scan(d))}
else if(d.readyState==='loading')d.addEventListener('DOMContentLoaded',()=>scan(d));else scan(d);
})(window,document);
