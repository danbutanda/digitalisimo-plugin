/* Avisos de la tienda (digitalisimo-woo-notices): lleva a la envoltura del widget los avisos de WooCommerce de la página,
   también los que lleguen después (carrito, pago), y desplaza la vista hasta ellos, sin jQuery ni dependencias. */
(() => {
	const S = ':not(.woocommerce-NoticeGroup) .wc-block-components-notice-banner, .woocommerce-NoticeGroup, :not(.woocommerce-NoticeGroup) .woocommerce-error, :not(.woocommerce-NoticeGroup) .woocommerce-message, :not(.woocommerce-NoticeGroup) .woocommerce-info';
	const start = () => {
		const w = document.querySelector('[data-digi-woo-notices]');
		if (!w || w.dataset.digiReady) return;
		w.dataset.digiReady = '1';
		const move = () => {
			const all = [...document.body.querySelectorAll(S)];
			if (!all.some((n) => n.parentElement !== w)) return;
			w.prepend(...all);
			const top = all[0].getBoundingClientRect().top + window.scrollY - document.documentElement.clientHeight / 2;
			window.scrollTo({ top, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
		};
		move();
		let queued = 0;
		new MutationObserver(() => {
			if (!queued) queued = requestAnimationFrame(() => { queued = 0; move(); });
		}).observe(document.body, { childList: true, subtree: true });
	};
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
	else start();
})();
