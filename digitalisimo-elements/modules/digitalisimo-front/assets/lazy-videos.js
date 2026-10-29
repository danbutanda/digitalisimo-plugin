/* Carga diferida de los vídeos alojados que arrancan solos (lazy_videos): el servidor les quitó autoplay y puso
   preload="none"; a una pantalla de distancia recuperan autoplay y preload y se reproducen. Sin IntersectionObserver,
   al cargar. Los nodos que llegan después (popups, bucles) también se observan. */
(function (d, w) {
	var S = 'video[data-digi-lazy-video]';
	function go(v) {
		if (v.__digiLazy) return;
		v.__digiLazy = 1;
		v.preload = 'auto';
		v.autoplay = true;
		var p = v.play();
		if (p && p.catch) p.catch(function () {});
	}
	if (!('IntersectionObserver' in w)) { d.querySelectorAll(S).forEach(go); return; }
	var io = new w.IntersectionObserver(function (es) {
		es.forEach(function (e) { if (e.isIntersecting || e.intersectionRatio > 0) { io.unobserve(e.target); go(e.target); } });
	}, { rootMargin: '100% 0px' });
	function scan() { d.querySelectorAll(S).forEach(function (v) { if (!v.__digiSeen) { v.__digiSeen = 1; io.observe(v); } }); }
	scan();
	if ('MutationObserver' in w && d.body) new w.MutationObserver(scan).observe(d.body, { childList: true, subtree: true });
})(document, window);
