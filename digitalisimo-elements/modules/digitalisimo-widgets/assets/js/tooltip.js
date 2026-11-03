/* Información emergente */
(()=>{let D=document,H='[data-digi-tooltip]',P='digi-tooltip--',B='aria-describedby',L='addEventListener',A='querySelectorAll',n=0,i=h=>{let t=[...h[A]('.digi-tooltip')].pop(),k=h.dataset.digiTooltipTrigger,c,o,p,s,g,w,r;if(!t||h.d)return;c=t.classList;o=t.className.match(/--(\w+)/)[1];p=o<'l'?'top':o<'r'?'right':o<'t'?'left':'bottom';t.id='digi-tooltip-'+ ++n;
h[A]('a,button,input,select,textarea,[tabindex]').forEach(e=>e.closest(H)==h&&e.setAttribute(B,(e.getAttribute(B)||'')+' '+t.id));
h.d=s=v=>{c.toggle(P+'shut',!!v);c.toggle(P+'open',!v&&!!k)};
g=()=>{c.replace(P+p,P+o);r=t.getBoundingClientRect();(o<'l'?r.bottom>innerHeight:o<'r'?r.left<0:o<'t'?r.right>innerWidth:r.top<0)&&c.replace(P+o,P+p)};
w=()=>{k||s();setTimeout(g)};h[L]('pointerenter',w);h[L]('focusin',w);
k&&(s(1),h[L]('click',e=>t.contains(e.target)||(s(c.contains(P+'open')),g())))},q=()=>D[A](H).forEach(i);
D[L]('keydown',e=>e.keyCode==27&&D[A](H).forEach(h=>h.d?.(1)));self[L]('elementor/popup/show',q);q()})();
