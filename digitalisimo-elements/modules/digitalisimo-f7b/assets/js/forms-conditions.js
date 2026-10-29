/* F7b: campos condicionales del Formulario; reglas de forms/class-rules.php */
((w,d)=>{
const A='data-digi-cond',Q='['+A+']',R='data-digi-required',C=['field','mark'].map(k=>'elementor-'+k+'-required'),K='input,select,textarea',
O=()=>Object.create(null),I=Array.isArray,
qa=(n,s)=>n.querySelectorAll(s),
E=(l,f)=>[].forEach.call(l||[],f),
N=v=>v==null?'':String(v).trim().toLocaleLowerCase('und'),
num=s=>/^[+-]?(\d+([.,]\d+)?|[.,]\d+)$/.test(s)?parseFloat(s.replace(',','.')):null,
T=([,o,v],l)=>{
v=N(v);
const e=!l.length,n=num(v),is=v?l.includes(v):e,has=!!v&&l.some(x=>x.includes(v)),
cmp=g=>n!==null&&l.some(x=>(x=num(x))!==null&&(g?x>n:x<n));
return{empty:e,not_empty:!e,is,is_not:!is,contains:has,not_contains:!has,greater:cmp(1),less:cmp(0)}[o]===true;
},
solve=(D,V)=>{
const h=O();
for(let p=0,ch=1;ch&&p<=D.length;p++){ch=0;
for(const c of D){
const f=q=>T(q,h[q[0]]?[]:V[q[0]]||[]),x=!(c.r.length&&c.r[c.l=='any'?'some':'every'](f));
if(x!=!!h[c.i])h[c.i]=x,ch=1;
}}
return h;
},
vals=f=>{
const v=O();
E(qa(f,K),e=>{
const m=/^form_fields\[(\w+)\]/.exec(e.name||''),t=e.type,a=m&&(v[m[1]]=v[m[1]]||[]);
a&&(t=='checkbox'||t=='radio'?e.checked&&a.push(e.value):t=='file'?E(e.files,x=>a.push(x.name)):e.localName=='select'?E(e.options,o=>o.selected&&a.push(o.value)):a.push(e.value));
});
for(const k in v)v[k]=v[k].map(N).filter(x=>x);
return v;
},
hide=(g,x)=>{
let s=C.filter(k=>g.classList.contains(k));
if(x&&s.length)g.setAttribute(R,s.join(' ')),g.classList.remove(...s);
if(!x&&(s=g.getAttribute(R)))g.classList.add(...s.split(' ')),g.removeAttribute(R);
E(qa(g,K+',button'),e=>{
if(x&&e.required)e.setAttribute(R,''),e.required=false;
if(!x&&e.hasAttribute(R))e.removeAttribute(R),e.required=true;
if(e.disabled!=x)e.disabled=x;
});
if(g.hidden!=x)g.hidden=x;
},
run=f=>{
const D=[];
E(qa(f,Q),g=>{
let c;try{c=JSON.parse(g.getAttribute(A))}catch(e){}c=c||{};
D.push({i:''+(c.i||''),l:c.l,r:I(c.r)?c.r.map(q=>I(q)?q:[]):[],g});
});
const h=solve(D,vals(f));
D.forEach(c=>hide(c.g,!!h[c.i]));
},
init=f=>{
const go=()=>run(f),y=A+'-ready';
if(!f.hasAttribute(y)){
f.setAttribute(y,'');
['input','change'].map(t=>f.addEventListener(t,go));
f.addEventListener('reset',()=>setTimeout(go));
}
go();
},
scan=r=>E(qa(r&&r.querySelectorAll?r:d,Q),g=>(g=g.closest('form'))&&init(g)),
hook=()=>{
const h=(w.elementorFrontend||0).hooks;
h&&!hook.on&&(hook.on=1,h.addAction('frontend/element_ready/form.default',s=>scan(s&&s[0]||s)));
};
scan(d);hook();
w.addEventListener('elementor/frontend/init',()=>{hook();scan(d)});
})(window,document);
