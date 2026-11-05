/* DIGITALÍSIMO Elements F4-Woo: en el editor, rellena «Loop Grid» de los filtros de precio y de atributo con los Loop Grid del documento (la lista de PRO sólo sirve a su widget). No cambia el valor guardado. */
(w=>{
'use strict';
const NAMES=['digitalisimo-woo-price-filter','digitalisimo-woo-attribute-filter'];
const fill=()=>{
const e=w.elementor,page=e&&e.getPanelView&&e.getPanelView().getCurrentPageView();
const m=page&&page.collection&&page.collection.findWhere({name:'selected_element'});
if(!m)return;
const doc=e.$previewContents&&e.$previewContents[0],opts={'':'Elige un Loop Grid'},value=page.model&&page.model.getSetting?page.model.getSetting('selected_element'):'';
if(doc)[...doc.querySelectorAll(`[data-elementor-id="${e.config.document.id}"] .elementor-widget-loop-grid`)].forEach((el,i)=>{opts[el.getAttribute('data-id')]='Loop Grid '+(i+1)});
if(value&&!opts[value])opts[value]='Loop Grid que ya no está en este documento';
m.set('options',opts);
const v=page.children&&page.children.findByModelCid(m.cid);
if(v)v.render();
};
const bind=()=>NAMES.forEach(n=>w.elementor.channels.editor.on(`editor:widget:${n}:section_filter:activated`,fill));
if(w.elementor&&w.elementor.channels&&w.elementor.channels.editor)bind();else w.addEventListener('elementor/init',bind,{once:true});
})(window);
