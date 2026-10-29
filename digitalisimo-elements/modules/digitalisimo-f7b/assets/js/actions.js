/* DIGITALÍSIMO Elements F7b: acciones con destino (V.3); reglas y formato en actions/class-rules.php. */
(w=>{'use strict';let io,st;
const d=w.document,R='data-digi-actions',RD='['+R+'-ready]',RM='(prefers-reduced-motion:reduce)',NAT='a[href],button,input,select,textarea,summary',
K={show:5,hide:5,toggle:5,toggle_class:3,set_attribute:3,scroll_to:3,offcanvas_toggle:3,load_more:1,popup_open:2,popup_close:2},
AT={'aria-pressed':/^(true|false|mixed)$/,'aria-current':/^(true|false|page|step|location|date|time)$/,'aria-label':/^[^<>\0-\x1f]{1,150}$/,'data-digi-state':/^[a-z0-9_-]{1,40}$/},
S=v=>typeof v=='string'?v:'',mq=q=>!!w.matchMedia?.(q).matches,ga=(n,k)=>n.getAttribute(k),sa=(n,k,v)=>n.setAttribute(k,v),
on=(n,t,f)=>n.addEventListener(t,f),cf=new WeakMap,cs=new WeakMap,op=new Set,fl=a=>+K[S(a.a)]||0,
ok=a=>{const f=a&&fl(a),c=f&&/^(click|hover)$/.test(a.t),r=f&&AT[S(a.n)];
return f&&(c||f&2&&/^(scroll_in|load)$/.test(a.t))&&(a.a!='toggle'||c)&&(!(f&1)||/^[a-z0-9]{1,24}$/i.test(S(a.g)))&&
(a.a!='toggle_class'||/^(?!elementor-|e-|digi-|wp-)[a-z0-9_-]{1,40}$/.test(S(a.c)))&&(a.a!='set_attribute'||r?.test?.(S(a.v)))&&
(a.a!='popup_open'||a.p>0)&&(!a.m||/^[a-zA-Z]{2,40}$/.test(S(a.m)))},
it=n=>{const t=n.localName,e=ga(n,'contenteditable');return/^(a|button|select|textarea|summary|iframe)$/.test(t)||t=='input'&&S(ga(n,'type')).toLowerCase()!='hidden'||
/^(video|audio)$/.test(t)&&n.hasAttribute('controls')||/^\s*\d/.test(S(ga(n,'tabindex')))||e!=null&&e!='false'},
ctl=el=>{if(el.matches('a[href],button'))return el;const c=[...el.querySelectorAll('*')].filter(it);
if(ga(el,'role')=='button'){if(!c.length)return el;el.removeAttribute('role');el.removeAttribute('tabindex')}
return c.length==1&&/^(a|button)$/.test(c[0].localName)?c[0]:null},
tg=(c,a)=>a.g==ga(c.el,'data-id')?c.el:c.r.querySelector('[data-id="'+a.g+'"]'),
sy=t=>(cs.get(t)||[]).forEach(k=>sa(k,'aria-expanded',''+!t.hidden)),
ln=(k,t)=>{if(!t.id){const i='digi-t-'+ga(t,'data-id');let n=i,j=1;while(d.getElementById(n))n=i+'-'+ ++j;t.id=n}
const l=S(ga(k,'aria-controls')).split(' ').filter(Boolean);l.indexOf(t.id)<0&&l.push(t.id);sa(k,'aria-controls',l.join(' '));
cs.has(t)||cs.set(t,new Set);cs.get(t).add(k);sy(t)},
fo=(n,o)=>{n.matches(NAT+',[tabindex]')||sa(n,'tabindex','-1');n.focus(o)},
vs=(c,t,v)=>{if(v==!t.hidden)return;if(!v&&t.contains(d.activeElement)){let k=c.k||c.el;if(t.contains(k))k=[...(cs.get(t)||[])].find(x=>!t.contains(x));k&&fo(k)}t.hidden=!v;sy(t)},
pl=(t,m)=>{if(mq(RM))return;const k=t.classList;k.remove('animated',m);void t.offsetWidth;k.add('animated',m);on(t,'animationend',()=>k.remove('animated',m))},
run=(el,a,e,u)=>{const c=cf.get(el);if(!c||a.o&&a.done&&!u)return;u||(a.done=1);
const t=a.g?tg(c,a):null,x=a.a,F=w.elementorFrontend,H=F?.utils?.urlActions,go=(n,o)=>H?H.runAction(H.createActionHash(n,o),e||{target:el}):console.warn(n);
if(fl(a)&1&&!t)return;
if(fl(a)&4)vs(c,t,x=='toggle'?t.hidden:(x=='show')!=!!u);
else if(x=='toggle_class')t.classList.toggle(a.c);
else if(x=='set_attribute'){if(!u){a.v0=ga(t,a.n);sa(t,a.n,a.v)}else a.v0==null?t.removeAttribute(a.n):sa(t,a.n,a.v0)}
else if(u)return;
else if(x=='scroll_to'){a.t=='click'&&fo(t,{preventScroll:true});t.scrollIntoView({behavior:mq(RM)?'auto':'smooth',block:'start'})}
else if(x=='load_more'){const b=t.querySelector('.e-load-more-anchor+.elementor-button-wrapper .elementor-button');b&&b.click()}
else if(x=='offcanvas_toggle')go('off_canvas:toggle',{id:a.g,displayMode:'toggle'});
else go('popup:'+(x=='popup_open'?'open':'close'),x=='popup_open'?{id:''+a.p,toggle:false}:{});
a.m&&t&&!u&&!t.hidden&&x!='hide'&&pl(t,a.m)},
hv=c=>c.l.forEach(a=>a.t=='hover'&&run(c.el,a,null,!c.h)),
en=c=>{clearTimeout(c.tm);if(!c.h){c.h=1;op.add(c);hv(c)}},
lv=(c,n)=>{clearTimeout(c.tm);c.tm=setTimeout(()=>{if(c.h){c.h=0;op.delete(c);hv(c)}},n?0:300)},
tc=e=>e.pointerType=='touch'||e.pointerType==null&&mq('(hover:none)'),
hb=c=>{const el=c.el,b=(n,f)=>{on(n,'pointerenter',e=>tc(e)||(f?en(c):clearTimeout(c.tm)));on(n,'pointerleave',e=>tc(e)||lv(c));
on(n,'focusin',()=>f?c.x||en(c):clearTimeout(c.tm));on(n,'focusout',e=>{const r=e.relatedTarget;if(!r||!(el.contains(r)||c.ht.some(t=>t.contains(r)))){c.x=0;lv(c,1)}})};
c.ht=c.l.filter(a=>a.t=='hover'&&a.g).map(a=>tg(c,a)).filter(t=>t&&!el.contains(t));b(el,c.hb=1);c.ht.forEach(t=>b(t,0))},
fi=el=>{const c=cf.get(el);c&&c.l.forEach(a=>a.t=='scroll_in'&&run(el,a))},
bd=el=>{if(!el||el.nodeType!=1||!el.hasAttribute(R)||el.matches(RD))return;sa(el,R+'-ready','');let l;try{l=JSON.parse(ga(el,R))}catch(e){}
const k=ctl(el),c={el,k,r:el.closest('[data-elementor-id]')||d,ht:[]},has=t=>c.l.some(a=>a.t==t);
c.l=(Array.isArray(l)?l:[]).filter(a=>ok(a)&&(a.t!='click'||k)).slice(0,20);
if(!c.l.length)return;cf.set(el,c);
c.l.forEach(a=>{const t=k&&fl(a)&4&&tg(c,a);t&&ln(k,t);a.t=='load'&&setTimeout(()=>run(el,a),Math.min(1e4,+a.d||0))});
has('hover')&&hb(c);
if(has('scroll_in'))w.IntersectionObserver?(io=io||new w.IntersectionObserver(l=>l.forEach(x=>{if(x.isIntersecting){io.unobserve(x.target);fi(x.target)}}))).observe(el):fi(el)},
up=(n,f)=>{for(let el=n;el=el?.closest?.(RD);el=el.parentNode){const c=cf.get(el);if(c&&f(el,c))return 1}},
boot=()=>{const F=w.elementorFrontend;if(st||!F||F.isEditMode?.())return;st=1;
on(d,'click',e=>up(e.target,(el,c)=>{let h=0;if(c.k&&c.k.contains(e.target))c.l.forEach(a=>{if(a.t=='click'){h=1;run(el,a,e)}});
if(c.hb&&tc(e)){h=1;c.h?lv(c,1):en(c)}
h&&c.k&&c.k.localName=='a'&&/^#?$/.test(S(ga(c.k,'href')))&&e.preventDefault();return h}));
on(d,'keydown',e=>{const k=e.target,y=e.key;if(y=='Escape')op.forEach(c=>{c.x=1;clearTimeout(c.tm);c.h=0;op.delete(c);hv(c)});
else if((y=='Enter'||y==' ')&&k.matches&&!k.matches(NAT)&&up(k,(el,c)=>c.k==k)){e.preventDefault();k.click()}});
F.hooks?.addAction('frontend/element_ready/global',$=>bd($&&$[0]||$));d.querySelectorAll('['+R+']').forEach(bd)};
w.elementorFrontend?.hooks?boot():on(w,'elementor/frontend/init',boot);
})(window);
