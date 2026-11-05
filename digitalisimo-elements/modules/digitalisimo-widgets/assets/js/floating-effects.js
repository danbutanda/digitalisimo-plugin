(function () {
	'use strict';
	var selector = '[data-digi-float="1"]';
	var animations = new Map();
	var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)');
	var observer = window.IntersectionObserver ? new IntersectionObserver(function (entries) {
		entries.forEach(function (entry) {
			var animation = animations.get(entry.target);
			if (!animation) { return; }
			if (entry.isIntersecting && !document.hidden && !(reduced && reduced.matches)) { animation.play(); }
			else { animation.pause(); }
		});
	}, { rootMargin: '80px' }) : null;

	function clear() {
		animations.forEach(function (animation, element) {
			animation.cancel();
			if (observer) { observer.unobserve(element); }
		});
		animations.clear();
	}
	function add(element) {
		if (animations.has(element) || !element.animate || (reduced && reduced.matches)) { return; }
		var distance = Math.max(1, Math.min(40, parseInt(element.getAttribute('data-digi-float-distance'), 10) || 12));
		var duration = Math.max(2, Math.min(12, parseFloat(element.getAttribute('data-digi-float-duration')) || 5));
		var animation;
		try { animation = element.animate([{ translate: '0 0' }, { translate: '0 -' + distance + 'px' }, { translate: '0 0' }], { duration: duration * 1000, iterations: Infinity, easing: 'ease-in-out' }); }
		catch (error) { return; }
		animations.set(element, animation);
		if (observer) { animation.pause(); observer.observe(element); }
	}
	function scan(root) {
		if (!root) { return; }
		if (root.matches && root.matches(selector)) { add(root); }
		if (root.querySelectorAll) { root.querySelectorAll(selector).forEach(add); }
	}
	function onReady() {
		scan(document);
		if (window.elementorFrontend && window.elementorFrontend.hooks) {
			window.elementorFrontend.hooks.addAction('frontend/element_ready/global', function (scope) { scan(scope && (scope[0] || scope)); });
		}
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', onReady, { once: true }); }
	else { onReady(); }
	if (reduced && reduced.addEventListener) { reduced.addEventListener('change', function () { clear(); if (!reduced.matches) { scan(document); } }); }
	document.addEventListener('visibilitychange', function () {
		animations.forEach(function (animation, element) {
			if (document.hidden) { animation.pause(); }
			else if (!observer || element.getBoundingClientRect().bottom >= 0 && element.getBoundingClientRect().top <= window.innerHeight) { animation.play(); }
		});
	});
}());
