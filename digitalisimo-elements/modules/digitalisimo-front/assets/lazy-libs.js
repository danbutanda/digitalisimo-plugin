/* DIGITALÍSIMO Elements: lazy_libs */
(function(w,d){
var c=w.digitalisimoLazyLibs,I=w.IntersectionObserver,got={},own,L;
function load(k){var u=k.src;return got[u]||(got[u]=new Promise(function(ok,ko){
	var s=d.createElement('script');s.src=u;s.async=!1;s.onload=ok;s.onerror=function(){ko(u)};d.body.appendChild(s)}))}
function has(q){return d.querySelector('[data-widget_type^=code-highlight] '+q)}
function prism(){var l=[0,1,2],f=!own;
	if(f){own=1;(w.Prism=w.Prism||{}).manual=!0}
	has('pre.line-numbers')&&l.push(3);
	has('pre[data-line]:not([data-line=""])')&&l.push(4);
	has('.copy-to-clipboard')&&l.push(5,6,7);
	return Promise.all(l.map(function(k){return load(c.prism[k])})).then(function(){f&&w.Prism.highlightAll()})}
L={'_lottie.default':[function(){return w.bodymovin},function(){return load(c.lottie)}],
	'_code-highlight.default':[function(){return!own&&w.Prism&&w.Prism.highlightAll},prism]};
function wrap(){var h=w.elementorFrontend.elementsHandler,orig=h.addHandler;
	h.addHandler=function(C,o){var a=arguments,b=L['_'+o.elementName],el=o.$element[0],io;
		function go(){b[1]().then(function(){orig.apply(h,a)},function(u){console.warn('DIGITALÍSIMO Elements: no se pudo cargar',u)})}
		if(!b||b[0]())return orig.apply(h,a);
		if(!I||!el)return go();
		io=new I(function(e){e.pop().isIntersecting&&(io.disconnect(),go())},{rootMargin:'100% 0px'});
		io.observe(el)}}
if(c)(w.elementorFrontend||{}).elementsHandler?wrap():w.addEventListener('elementor/frontend/init',wrap)
})(window,document);
