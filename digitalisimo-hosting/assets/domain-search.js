document.addEventListener('submit', async (event) => {
  const form = event.target.closest('.digitalisimo-domain-search');
  if (!form) return;
  event.preventDefault();
  const result = form.querySelector('.digitalisimo-domain-result');
  const domain = form.elements.domain.value.trim().toLowerCase();
  const extension = form.elements.extension.value;
  if (!/^[a-z0-9-]+$/.test(domain)) { result.textContent = 'Escribe un dominio sin espacios ni caracteres especiales.'; return; }
  const fullDomain = domain + extension;
  result.textContent = 'Verificando disponibilidad…';
  try {
    const check = await fetch(digitalisimoDomainSearch.endpoint + 'check-domain?domain=' + encodeURIComponent(fullDomain));
    const data = await check.json();
    if (!check.ok) throw new Error(data.message || 'No fue posible verificar el dominio.');
    if (!data.available) { result.textContent = 'El dominio ' + fullDomain + ' no está disponible.'; return; }
    const productId = form.elements.extension.selectedOptions[0].dataset.productId;
    const price = await fetch(digitalisimoDomainSearch.endpoint + 'product-price?product_id=' + productId).then(r => r.json());
    result.innerHTML = '<strong>' + fullDomain + '</strong> está disponible. ' + (price.price_html || '') + ' <a class="button" href="' + window.location.origin + '/?add-to-cart=' + encodeURIComponent(productId) + '&digitalisimo_domain=' + encodeURIComponent(fullDomain) + '">Añadir al carrito</a>';
  } catch (error) { result.textContent = error.message || 'Error al verificar el dominio.'; }
});
