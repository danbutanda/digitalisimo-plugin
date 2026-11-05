/* Formulario propio: envío con fetch, pasos, reCAPTCHA; sin jQuery. */
((w,d)=>{'use strict';
const M='elementor-message',E=M+' '+M+'-danger',H=' elementor-help-inline elementor-form-help-inline',W='elementor-form-waiting',G='.elementor-field-group',
qa=(n,s)=>[...n.querySelectorAll(s)],at=(n,a)=>n.getAttribute(a),
el=(t,c,h,id)=>{const n=d.createElement(t);n.className=c;n.setAttribute('role','alert');if(id)n.id=id;n.innerHTML=h;return n},
// Eventos por jQuery si está (los de PRO) y propios digi-form-*.
emit=(f,n,x)=>{const J=w.jQuery;if(J)J(f).trigger(n,[x]);f.dispatchEvent(new CustomEvent('digi-form-'+n,{bubbles:true,detail:x}))},
desc=(i,id,on)=>{const b=(at(i,'aria-describedby')||'').split(' ').filter(x=>x&&x!==id&&!(!on&&/-error$/.test(x)));if(on)b.push(id);b.length?i.setAttribute('aria-describedby',b.join(' ')):i.removeAttribute('aria-describedby')};
function bad(f,k,t){const c=d.getElementById('form-field-'+k),g=c&&c.closest(G)||f.querySelector(G+'-'+k);if(!g)return;const id='form-field-'+k+'-error';
g.classList.add('elementor-error');g.appendChild(el('span',E+H,t,id));qa(g,'input,select,textarea').forEach(i=>{i.setAttribute('aria-invalid','true');desc(i,id,1)})}
function clear(f){qa(f,'.'+M).forEach(m=>m.remove());qa(f,'.elementor-error').forEach(g=>g.classList.remove('elementor-error'));qa(f,'[aria-invalid]').forEach(i=>{i.setAttribute('aria-invalid','false');desc(i,'',0)})}
function steps(f){const st=qa(f,'[data-digi-step]'),n=st.length;if(!n)return null;let cur=0;
const ind=qa(f,'.digi-step'),bar=f.querySelector('.digi-steps__bar'),out=f.querySelector('[data-digi-status]'),P='digi-step--';
const go=(i,focus)=>{if(i<0||i>=n)return;st[cur].hidden=true;st[i].hidden=false;cur=i;const name=at(st[i],'aria-label');
ind.forEach((x,k)=>{x.classList.remove(P+'off',P+'on',P+'done');x.classList.add(P+(k<i?'done':k>i?'off':'on'))});
if(bar){const v=Math.floor((i+1)/n*100);bar.style.setProperty('--dfs-pw',v+'%');bar.setAttribute('aria-valuenow',v);bar.setAttribute('aria-valuetext',name);bar.firstChild.textContent=v+'%'}
if(out)out.textContent=name;
if(focus){const g=qa(st[i],':scope>'+G).find(x=>x.offsetParent);if(g)(g.querySelector('input,select,textarea,button')||g).focus()}},
valid=()=>qa(st[cur],G+' input,'+G+' select,'+G+' textarea').every(i=>i.checkValidity()||(i.reportValidity(),0)),
apply=r=>'next'===r?valid()&&go(cur+1,1):go(cur-1,1);
f.addEventListener('click',e=>{const b=e.target.closest('[data-direction]');if(b&&f.contains(b))apply(at(b,'data-direction'))});
f.addEventListener('keydown',e=>{if('Enter'===e.key&&cur<n-1&&'TEXTAREA'!==e.target.tagName){e.preventDefault();const b=e.target.closest('[data-direction]');apply(b?at(b,'data-direction'):'next')}});
return{reset:()=>go(0),error:()=>{const m=f.querySelector('.elementor-form-help-inline'),s=m&&m.closest('[data-digi-step]');if(s)go(st.indexOf(s),1)}}}
function captcha(f,send){const r=qa(f,'.elementor-g-recaptcha').pop();if(!r)return;
const ready=()=>{const g=w.grecaptcha;if(!g||!g.render)return setTimeout(ready,350);const s=Object.assign({},r.dataset),id=g.render(r,s);
['reset','digi-form-error'].forEach(t=>f.addEventListener(t,()=>g.reset(id)));const b=f.querySelector('button[type="submit"]');
if('v3'===s.type&&b)b.addEventListener('click',e=>{e.preventDefault();g.ready(()=>g.execute(id,{action:s.action}).then(t=>{
let i=f.querySelector('[name="g-recaptcha-response"]');if(!i){i=d.createElement('input');i.type='hidden';i.name='g-recaptcha-response';f.appendChild(i)}i.value=t;if(f.reportValidity())send()}))})};ready()}
function init(f){if(f.digiForm)return;f.digiForm=1;const s=steps(f),btn=f.querySelector('button[type="submit"]'),F=w.flatpickr;let busy=0;
const done=()=>{busy=0;f.classList.remove(W);f.removeAttribute('aria-busy');if(btn)btn.disabled=false},
fail=t=>{done();f.appendChild(el('div',E,t));emit(f,'error');if(s)s.error()},
// Un envío en curso bloquea otro.
send=()=>{if(busy)return;busy=1;clear(f);f.classList.add(W);f.setAttribute('aria-busy','true');if(btn)btn.disabled=true;
const fd=new FormData(f);fd.append('action',at(f,'data-digi-form'));fd.append('referrer',location.toString());
fetch(at(f,'data-digi-ajax'),{method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.json()).then(r=>{const x=r&&r.data||{};
if(!r||!r.success){const e=x.errors||{};Object.keys(e).forEach(k=>bad(f,k,e[k]));return fail(x.message||at(f,'data-digi-error'))}
done();emit(f,'submit_success',x);emit(f,'form_destruct',x);f.reset();if(s)s.reset();
const svg=((w.elementorFrontendConfig||{}).experimentalFeatures||{}).e_font_icon_svg,p=x.data||{},pop=((w.elementorProFrontend||{}).modules||{}).popup;
if(x.message)f.appendChild(el('div',M+' '+M+'-success'+(svg?' '+M+'-svg':''),x.message));
if(p.popup&&pop)'open'===p.popup.action?pop.showPopup(p.popup):setTimeout(()=>pop.closePopup(p.popup,{target:f}),1e3);
if(p.redirect_url)location.href=p.redirect_url}).catch(()=>fail(at(f,'data-digi-error')))};
f.addEventListener('submit',e=>{e.preventDefault();send()});
f.addEventListener('change',e=>{const i=e.target,max=parseInt(at(i,'data-maxsize'),10)*1048576;if('file'!==i.type||!max||!i.files)return;
if([...i.files].some(x=>x.size>max)){bad(f,i.id.replace(/^form-field-/,''),at(i,'data-maxsize-message'));emit(f,'error');if(s)s.error()}});
captcha(f,send);
if(F){qa(f,'.elementor-date-field:not(.elementor-use-native)').forEach(i=>F(i,{minDate:i.min||null,maxDate:i.max||null,allowInput:true}));
qa(f,'.elementor-time-field:not(.elementor-use-native)').forEach(i=>F(i,{noCalendar:true,enableTime:true,allowInput:true}))}}
const scan=r=>qa(r,'form[data-digi-form]').forEach(init),
// Editor: element_ready.
hook=()=>{const h=(w.elementorFrontend||{}).hooks;if(h)h.addAction('frontend/element_ready/digitalisimo-form.default',e=>scan(e[0]||e))};
scan(d);(w.elementorFrontend||{}).hooks?hook():w.addEventListener('elementor/frontend/init',hook);
})(window,document);
