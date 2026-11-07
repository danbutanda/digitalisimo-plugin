/* Animación Lottie (digitalisimo-lottie): librería local al acercarse a la pantalla; disparadores, bucle, tramos e
   inversión como PRO. Sin jQuery. */
(()=>{
'use strict';
const W=window,D=document,E=D.documentElement;
let lib=null;
const reduced=()=>!!(W.matchMedia&&W.matchMedia('(prefers-reduced-motion: reduce)').matches),
edit=()=>!!W.elementorFrontend?.isEditMode?.(),
// La librería, una sola vez para toda la página.
load=src=>lib||(lib=new Promise((ok,ko)=>{
	if(W.lottie)return ok(W.lottie);
	const s=D.createElement('script');
	s.src=src;s.async=true;s.onload=()=>W.lottie?ok(W.lottie):ko();s.onerror=ko;
	D.head.appendChild(s);
})),
// Como `Scroll.scrollObserver` del núcleo; sin IntersectionObserver, en el momento.
watch=(el,margin,cb)=>{
	if(!W.IntersectionObserver)return cb({isIntersecting:true,boundingClientRect:el.getBoundingClientRect()});
	const o=new IntersectionObserver(en=>cb(en[0],o),{rootMargin:margin});
	o.observe(el);
},
clamp=n=>Math.min(100,Math.max(0,+n||0)),
// `getElementViewportPercentage` y `getPageScrollPercentage` del núcleo.
inView=(el,off)=>{
	const r=el.getBoundingClientRect(),h=W.innerHeight,ws=h*off.start/100,y1=r.top-h;
	return Math.max(0,Math.min((ws-y1)/(r.top+ws+el.clientHeight-y1+h*off.end/100),1))*100;
},
onPage=(off,lim)=>{
	const h=lim||E.scrollHeight-E.clientHeight,ho=h*off.start/100;
	return(E.scrollTop+D.body.scrollTop+ho)/(h+ho+h*off.end/100)*100;
};

function create(L,box,c){
	const root=box.closest('.elementor-element')||box,
	st={back:0,n:0,fresh:0,raf:0,y:0,iy:0,first:1},
	off={start:-c.viewport[0]||0,end:-(100-c.viewport[1])||0},
	margin=off.end+'% 0% '+off.start+'%';
	L.destroy(c.name);
	const a=L.loadAnimation({container:box.querySelector('.digi-lottie__animation'),path:c.path,renderer:c.renderer,autoplay:false,name:c.name});
	let F0=0,T=0;
	const at=p=>F0+(T-F0)*clamp(p)/100,
	cur=()=>0===a.firstFrame?a.currentFrame:a.firstFrame+a.currentFrame,
	rev=()=>c.reverse||('reverse'===c.hoverOut&&st.back),
	frames=()=>{
		const s=at(c.start),e=at(c.end),now=cur();
		let f=F0,l=0===F0?T:F0+T;
		if(s&&s>f)f=s;
		if(e&&e<l)l=e;
		if(!st.fresh&&'bind_to_scroll'!==c.trigger)f=s&&s>now?s:now;
		if(st.back&&rev()){f=now;l=s&&s>F0?s:F0}
		return{f,l};
	},
	stop=n=>a.goToAndStop(n-F0,true),
	play=()=>{const r=frames();a.stop();a.playSegments([r.f,r.l],true);st.fresh=0},
	scrub=()=>{
		const p='page'===c.relative?onPage(off):'fixed'===getComputedStyle(root).position?onPage(off,W.innerHeight):inView(root,off),r=frames();
		stop(r.f+(r.l-r.f)*clamp(p)/100);
	},
	tick=()=>{if(W.scrollY!==st.y){scrub();st.y=W.scrollY}st.raf=requestAnimationFrame(tick)};
	a.addEventListener('complete',()=>{
		st.fresh=1;
		const r=rev();
		if(c.loop&&!r){if(c.times>0){if(++st.n<c.times)play();else st.n=0}else play()}
		else if(c.loop){if(!c.times||st.n<c.times){st.back=!st.back;play();if(st.back)st.n++}else{st.n=0;st.back=0}}
		else if(r){if(st.n<1){st.n++;st.back=1;play()}else if(!st.back){st.back=1;play()}else{st.n=0;st.back=0}}
	});
	a.addEventListener('DOMLoaded',()=>{
		F0=a.firstFrame;T=a.totalFrames;
		if(c.speed)a.setSpeed(c.speed);
		const link=box.parentNode?.closest?.('.digi-lottie__link');
		if(c.timeout&&link)link.addEventListener('click',ev=>{
			ev.preventDefault();
			if(!edit())setTimeout(()=>W.open(c.link.url,c.link.external?'_blank':'_self'),c.timeout);
		});
		// Movimiento reducido: el estado final, fijo (último fotograma del tramo, o el primero si se invierte); lo convertido
		// de PRO (c.motion) se anima como en PRO.
		if(reduced()&&!c.motion){st.fresh=1;const r=frames();return stop(c.reverse?r.f:r.l)}
		stop(frames().f);
		const t=c.trigger;
		if('none'===t)play();
		else if('arriving_to_viewport'===t)watch(root,margin,e=>e.isIntersecting?play():a.pause());
		else if('bind_to_scroll'===t)watch('viewport'===c.relative?root:E,margin,e=>{
			const y=e.boundingClientRect.y,up=y>=st.iy;
			cancelAnimationFrame(st.raf);
			if(e.isIntersecting){if(st.first){st.first=0;scrub()}st.raf=requestAnimationFrame(tick)}
			else{st.first=0;const r=frames();stop(up?r.f:r.l)}
			st.iy=y;
		});
		else if('on_click'===t){
			box.addEventListener('click',play);
			if('button'===box.getAttribute('role'))box.addEventListener('keydown',ev=>{if('Enter'===ev.key||' '===ev.key){ev.preventDefault();play()}});
		}else if('on_hover'===t){
			const sel={section:'.elementor-section',column:'.elementor-column',container:'.e-con'}[c.area],el=sel?box.closest(sel):box;
			if(el){
				el.addEventListener('mouseenter',()=>{st.back=0;play()});
				if('pause'===c.hoverOut)el.addEventListener('mouseleave',()=>a.pause());
				else if('reverse'===c.hoverOut)el.addEventListener('mouseleave',()=>{st.back=1;play()});
			}
		}
	});
}

function init(box){
	if(box.digiLottie)return;
	let c;
	try{c=JSON.parse(box.getAttribute('data-digi-lottie'))}catch(e){return}
	if(!c||!c.lib||!c.path)return;
	box.digiLottie=1;
	// La librería a una pantalla de distancia; con «Carga diferida», la animación a 200 px (como PRO).
	watch(box,'100% 0px',(e,o)=>{
		if(!e.isIntersecting)return;
		o?.disconnect();
		load(c.lib).then(L=>c.lazy?watch(box,'0px 0px 200px',(e2,o2)=>{if(e2.isIntersecting){o2?.disconnect();create(L,box,c)}}):create(L,box,c),()=>{});
	});
}

const scan=s=>(s||D).querySelectorAll('.digi-lottie__container[data-digi-lottie]').forEach(init),
hook=()=>W.elementorFrontend.hooks.addAction('frontend/element_ready/digitalisimo-lottie.default',s=>scan(s?.[0]));
'loading'===D.readyState?D.addEventListener('DOMContentLoaded',()=>scan()):scan();
W.elementorFrontend?.hooks?hook():W.addEventListener('elementor/frontend/init',hook);
})();
