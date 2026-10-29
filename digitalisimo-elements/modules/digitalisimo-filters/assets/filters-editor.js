/* DIGITALÍSIMO Elements F7b V.5: en el editor, los Loop Grid de este documento y las claves de campo de los filtros ampliados. No cambia el valor guardado. */
(w=>{
'use strict';
const NAMES=['digitalisimo-loop-search','digitalisimo-loop-meta-filter','digitalisimo-loop-orderby','digitalisimo-loop-per-page'],KEYS={'digitalisimo-loop-meta-filter':'meta_key','digitalisimo-loop-orderby':'order_meta_key'},cache={};
const view=()=>{const e=w.elementor;return e&&e.getPanelView&&e.getPanelView().getCurrentPageView()};
const fill=(p,name,opts,missing)=>{
const m=p.collection&&p.collection.findWhere({name});
if(!m)return;
const v=p.model.getSetting(name);
if(v&&!opts[v])opts[v]=missing;
m.set('options',opts);
const c=p.children&&p.children.findByModelCid(m.cid);
if(c)c.render();
};
// Sólo los Loop Grid de este documento: los de una plantilla incrustada son de otro y el servidor los descartaría.
const loops=()=>{
const e=w.elementor,doc=e.$previewContents&&e.$previewContents[0],id=String(e.config.document.id),o={'':'Elige un Loop Grid'};
let n=0;
if(doc)[...doc.querySelectorAll('.elementor-widget-loop-grid')].forEach(el=>{const d=el.closest('[data-elementor-id]');if(d&&d.getAttribute('data-elementor-id')===id)o[el.getAttribute('data-id')]='Loop Grid '+(++n)});
return o;
};
// Tipo de contenido de las claves: el del bucle elegido o, con «Archivo», el del control.
const postType=p=>{
const s=k=>p.model.getSetting(k);
if(s('destination')==='archive')return s('fields_post_type')||'post';
const c=s('selected_element')&&w.elementor.getContainer&&w.elementor.getContainer(s('selected_element')),st=c&&c.settings;
if(!st)return'post';
if(st.get('_skin')==='product')return'product';
const t=st.get('post_query_post_type');
return t&&!/^(current_query|by_id|related)$/.test(t)?t:'post';
};
const keys=(p,name)=>{
const t=postType(p),done=list=>{const o={'':'Elige un campo','__custom':'Otra clave…'};list.forEach(k=>{o[k]=k});fill(p,name,o,'Campo que no está en la lista')};
if(cache[t])return done(cache[t]);
const a=w.elementorCommon&&w.elementorCommon.ajax;
if(a)a.addRequest('digitalisimo_filters_meta_keys',{data:{post_type:t},success:d=>{cache[t]=d&&Array.isArray(d.keys)?d.keys:[];if(view()===p)done(cache[t])}});
};
const run=()=>{
const p=view();
if(!p||!p.model||!p.model.getSetting)return;
fill(p,'selected_element',loops(),'Loop Grid que ya no está en este documento');
const k=KEYS[p.model.get('widgetType')];
if(k)keys(p,k);
};
const bind=()=>NAMES.forEach(n=>w.elementor.channels.editor.on(`editor:widget:${n}:section_filter:activated`,run));
if(w.elementor&&w.elementor.channels&&w.elementor.channels.editor)bind();else w.addEventListener('elementor/init',bind,{once:true});
})(window);
