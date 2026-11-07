/* Formulario propio: envío con fetch, pasos, reCAPTCHA; sin jQuery. */
((w,d)=>{
const M='elementor-message',E=M+' '+M+'-danger',H=' elementor-help-inline elementor-form-help-inline',W='elementor-form-waiting',G='.elementor-field-group',SP='elementor-form-spinner',AI='aria-invalid',AD='aria-describedby',ER='elementor-error',DR='data-direction',
q=(n,s)=>n.querySelector(s),qa=(n,s)=>[...n.querySelectorAll(s)],at=(n,a)=>n.getAttribute(a),sa=(n,a,v)=>n.setAttribute(a,v),on=(n,t,f)=>n.addEventListener(t,f),
el=(t,c,h,id)=>{const n=d.createElement(t);n.className=c;sa(n,'role','alert');if(id)n.id=id;n.innerHTML=h;return n},
// Eventos de PRO (jQuery) y digi-form-*.
emit=(f,n,x)=>{const J=w.jQuery;if(J)J(f).trigger(n,[x]);f.dispatchEvent(new CustomEvent('digi-form-'+n,{bubbles:true,detail:x}))},
desc=(i,id,y)=>{const b=(at(i,AD)||'').split(' ').filter(x=>x&&x!==id&&!(!y&&/-error$/.test(x)));if(y)b.push(id);b.length?sa(i,AD,b.join(' ')):i.removeAttribute(AD)};
function bad(f,k,t){const c=q(f,'#'+CSS.escape('form-field-'+k)),g=c&&c.closest(G)||q(f,G+'-'+k);if(!g)return;const id='form-field-'+k+'-error';
g.classList.add(ER);g.appendChild(el('span',E+H,t,id));qa(g,'input,select,textarea').forEach(i=>{sa(i,AI,'true');desc(i,id,1)})}
function clear(f){qa(f,'.'+M).forEach(m=>m.remove());qa(f,'.'+ER).forEach(g=>g.classList.remove(ER));qa(f,'['+AI+']').forEach(i=>{sa(i,AI,'false');desc(i,'',0)})}
function steps(f){const st=qa(f,'[data-digi-step]'),n=st.length;if(!n)return null;let cur=0;
const ind=qa(f,'.digi-step'),bar=q(f,'.digi-steps__bar'),out=q(f,'[data-digi-status]'),P='digi-step--';
// Un solo paso: «siguiente» lo oculta, como PRO.
const go=(i,focus)=>{if(i<0||i>=(n>1?n:2))return;const t=st[i],name=t?at(t,'aria-label'):'';if(st[cur])st[cur].hidden=true;if(t)t.hidden=false;cur=i;
ind.forEach((x,k)=>{x.className='digi-step '+P+(k<i?'done':k>i?'off':'on')});
if(bar){const v=(i+1)/n*100|0;bar.style.setProperty('--dfs-pw',v+'%');sa(bar,'aria-valuenow',v);sa(bar,'aria-valuetext',name);bar.firstChild.textContent=v+'%'}
if(out)out.textContent=name;
if(focus&&t){const g=qa(t,':scope>'+G).find(x=>x.offsetParent);if(g)(q(g,'input,select,textarea,button')||g).focus()}},
valid=()=>qa(st[cur],G+' input,'+G+' select,'+G+' textarea').every(i=>i.checkValidity()||(i.reportValidity(),0)),
apply=r=>'next'===r?valid()&&go(cur+1,1):go(cur-1,1);
on(f,'click',e=>{const b=e.target.closest('['+DR+']');if(b&&f.contains(b))apply(at(b,DR))});
on(f,'keydown',e=>{if('Enter'===e.key&&cur<n-1&&'TEXTAREA'!==e.target.tagName){e.preventDefault();const b=e.target.closest('['+DR+']');apply(b?at(b,DR):'next')}});
return{reset:()=>go(0),error:()=>{const m=q(f,'.elementor-form-help-inline'),s=m&&m.closest('[data-digi-step]');if(s)go(st.indexOf(s),1)}}}
function captcha(f,send){const r=qa(f,'.elementor-g-recaptcha').pop();if(!r)return;
const ready=()=>{const g=w.grecaptcha;if(!g||!g.render)return setTimeout(ready,350);const s=Object.assign({},r.dataset),id=g.render(r,s);
['reset','digi-form-error'].forEach(t=>on(f,t,()=>g.reset(id)));const b=q(f,'button[type=submit]');
if('v3'===s.type&&b)on(b,'click',e=>{e.preventDefault();g.ready(()=>g.execute(id,{action:s.action}).then(t=>{
let i=q(f,'[name="g-recaptcha-response"]');if(!i){i=d.createElement('input');i.type='hidden';i.name='g-recaptcha-response';f.appendChild(i)}i.value=t;if(f.reportValidity())send()}))})};ready()}
function init(f){if(f.digiForm)return;f.digiForm=1;const s=steps(f),btn=q(f,'button[type=submit]'),F=w.flatpickr,X='data-digi-error';let busy=0;
const done=()=>{busy=0;f.classList.remove(W);f.removeAttribute('aria-busy');if(btn){btn.disabled=false;qa(btn,'.'+SP).forEach(x=>x.remove())}},
fail=t=>{done();f.appendChild(el('div',E,t));emit(f,'error');if(s)s.error()},
// Un envío bloquea otro; como PRO, paso 1 e indicador en el botón.
send=()=>{if(busy)return;busy=1;if(s)s.reset();clear(f);f.classList.add(W);sa(f,'aria-busy','true');const o=btn&&q(btn,':scope>span');if(btn)btn.disabled=true;
if(o)o.insertAdjacentHTML('afterbegin','<span class="elementor-button-text '+SP+'"><i class="fa fa-spinner fa-spin"></i>&nbsp;</span>');
const fd=new FormData(f);fd.append('action',at(f,'data-digi-form'));fd.append('referrer',location.href);
fetch(at(f,'data-digi-ajax'),{method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.ok?r.json().catch(()=>Promise.reject('parsererror')):Promise.reject('error')).then(r=>{const x=r&&r.data||{};
if(!r||!r.success){const e=x.errors||{};Object.keys(e).forEach(k=>bad(f,k,e[k]));return fail(x.message||at(f,X))}
done();emit(f,'submit_success',x);emit(f,'form_destruct',x);f.reset();if(s)s.reset();
const svg=((w.elementorFrontendConfig||{}).experimentalFeatures||{}).e_font_icon_svg,p=x.data||{},pop=((w.elementorProFrontend||{}).modules||{}).popup;
if(x.message)f.appendChild(el('div',M+' '+M+'-success'+(svg?' '+M+'-svg':''),x.message));
if(p.popup&&pop)'open'===p.popup.action?pop.showPopup(p.popup):setTimeout(()=>pop.closePopup(p.popup,{target:f}),1e3);
// Lo convertido (data-digi-pro), como PRO: textStatus y botón en texto plano.
if(p.redirect_url)location.href=p.redirect_url}).catch(e=>{if(!f.hasAttribute('data-digi-pro'))return fail(at(f,X));if(btn)btn.textContent=btn.textContent;fail('string'==typeof e?e:'error')})};
on(f,'submit',e=>{e.preventDefault();send()});
on(f,'change',e=>{const i=e.target,max=parseInt(at(i,'data-maxsize'))*1048576;if('file'!==i.type||!max||!i.files)return;
if([...i.files].some(x=>x.size>max)){bad(f,i.id.replace(/^form-field-/,''),at(i,'data-maxsize-message'));emit(f,'error');if(s)s.error()}});
captcha(f,send);
if(F){qa(f,'.elementor-date-field:not(.elementor-use-native)').forEach(i=>F(i,{minDate:i.min||null,maxDate:i.max||null,allowInput:!0}));
qa(f,'.elementor-time-field:not(.elementor-use-native)').forEach(i=>F(i,{noCalendar:!0,enableTime:!0,allowInput:!0}))}}
const scan=r=>qa(r,'form[data-digi-form]').forEach(init),
fe=()=>(w.elementorFrontend||{}).hooks,hook=()=>{const h=fe();if(h)h.addAction('frontend/element_ready/digitalisimo-form.default',e=>scan(e[0]||e))};
scan(d);fe()?hook():on(w,'elementor/frontend/init',hook);
})(window,document);
