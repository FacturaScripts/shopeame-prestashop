/**
 * Anonimiza la página del back-office antes de capturarla para la ficha de PrestaShop Addons.
 *
 * Se ejecuta en la pestaña con `node Doc/Addons/cdp.mjs eval "$(cat Doc/Addons/anonimizar.js)"`:
 * - cambia el dominio de la tienda por mitienda.com y el nombre de la tienda por «Mi tienda»;
 * - cambia cualquier dominio de un servidor shopea.me local por shopea.me;
 * - cambia las claves del webservice (32 caracteres hexadecimales) por una ficticia, en textos, campos y atributos;
 * - quita las animaciones y los avisos flotantes de PrestaShop.
 *
 * Devuelve cuántos textos o campos ha cambiado. Hay que repetirlo después de cada navegación.
 */
(() => {
  const shopName = document.title.split(' • ').pop();
  const fakeKey = '0A1B2C3D4E5F60718293A4B5C6D7E8F9';
  const rules = [
    [new RegExp(location.hostname.replace(/\./g, '\\.'), 'g'), 'mitienda.com'],
    [/\b[\w.-]*shopea[\w-]*\.(?!me\b)[\w.-]+/g, 'shopea.me'],
    [/\b[A-F0-9]{32}\b/g, fakeKey],
  ];
  if (shopName && shopName !== document.title) {
    rules.push([new RegExp(shopName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g'), 'Mi tienda']);
  }
  const fix = (s) => rules.reduce((t, [re, v]) => t.replace(re, v), s);
  let changed = 0;

  // textos visibles
  const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
  while (walker.nextNode()) {
    const node = walker.currentNode;
    const value = fix(node.nodeValue);
    if (value !== node.nodeValue) {
      node.nodeValue = value;
      changed++;
    }
  }

  // campos y atributos que se copian al portapapeles o se muestran como valor
  document.querySelectorAll('input, textarea').forEach((input) => {
    const value = fix(input.value);
    if (value !== input.value) {
      input.value = value;
      changed++;
    }
  });
  document.querySelectorAll('[value], [data-clipboard-text], a[href]').forEach((el) => {
    for (const attr of el.attributes) {
      const value = fix(attr.value);
      if (value !== attr.value) el.setAttribute(attr.name, value);
    }
  });

  // sin animaciones ni avisos flotantes
  const style = document.createElement('style');
  style.textContent = '*{transition:none!important;animation:none!important}.growl,.onboarding{display:none!important}';
  document.head.appendChild(style);
  return changed;
})()
