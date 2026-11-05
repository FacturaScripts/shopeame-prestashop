/**
 * Shopeame - PrestaShop connector for shopea.me
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 *
 * @author    FacturaCity SL
 * @copyright 2025-2026 Carlos García Gómez
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

/*
 * Facturas del ERP de los pedidos, en el back-office y en Mi cuenta.
 *
 * - Listado de pedidos del back-office: el botón de cada pedido pide sus facturas la primera vez
 *   que se pulsa (nunca al pintar el listado) y las muestra en un desplegable.
 * - Ficha del pedido y detalle del pedido en Mi cuenta: el cuadro o la sección (data-autoload) las
 *   pide al abrir la página.
 * - Historial de pedidos de Mi cuenta: el tema no tiene un hook por pedido, así que se añade junto
 *   al botón «Detalles» de cada pedido una copia suya, con las mismas clases del tema, que abre el
 *   desplegable. El id del pedido sale del enlace de «Detalles» (id_order).
 *
 * Los textos y las URL llegan desde PHP en window.shopeameInvoices (Media::addJsDef):
 * - listUrl y token: solo en Mi cuenta; la lista se pide por POST al controlador frontal.
 * - history: solo en el historial de Mi cuenta, donde se añaden los botones junto a «Detalles».
 * - En el back-office cada contenedor lleva su URL en data-url (con el _token del empleado).
 */
(function () {
    'use strict';

    var config = window.shopeameInvoices;
    if (!config || !config.i18n) {
        return;
    }

    /**
     * Crea un elemento con una clase y un texto opcionales.
     *
     * @param {string} tag Etiqueta HTML.
     * @param {string} className Clase CSS.
     * @param {string} text Texto del elemento.
     * @returns {HTMLElement}
     */
    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text) {
            node.textContent = text;
        }

        return node;
    }

    /**
     * Pinta un mensaje en el panel, con un botón para volver a cargar si es un error.
     *
     * @param {HTMLElement} widget Contenedor de las facturas del pedido.
     * @param {string} message Mensaje que se muestra.
     * @param {boolean} isError Indica si es un error.
     */
    function renderMessage(widget, message, isError) {
        var panel = widget.querySelector('.shopeame-invoices__panel');
        panel.textContent = '';
        panel.appendChild(el('p', 'shopeame-invoices__message' + (isError ? ' shopeame-invoices__message--error' : ''), message));

        if (isError) {
            var retry = el('button', 'btn btn-link btn-sm shopeame-invoices__reload', config.i18n.reload);
            retry.type = 'button';
            retry.addEventListener('click', function () {
                load(widget);
            });
            panel.appendChild(retry);
        }
        reposition(widget);
    }

    /**
     * Pinta la lista de facturas con su enlace de descarga.
     *
     * @param {HTMLElement} widget Contenedor de las facturas del pedido.
     * @param {Array} invoices Facturas devueltas por el servidor.
     */
    function renderInvoices(widget, invoices) {
        if (!invoices.length) {
            renderMessage(widget, config.i18n.empty, false);

            return;
        }

        var panel = widget.querySelector('.shopeame-invoices__panel');
        var list = el('ul', 'shopeame-invoices__list');
        invoices.forEach(function (invoice) {
            var item = el('li', 'shopeame-invoices__item' + (invoice.is_refund ? ' shopeame-invoices__item--refund' : ''));

            // la URL llega en JSON sin escapar para HTML: se asigna tal cual a href
            var link = el('a', 'shopeame-invoices__link', invoice.code);
            link.href = invoice.url;
            link.title = config.i18n.download;
            item.appendChild(link);

            if (invoice.is_refund) {
                var badge = el('span', 'shopeame-invoices__badge', config.i18n.refund);
                if (invoice.refund_of) {
                    badge.title = config.i18n.refundOf.replace('%s', invoice.refund_of);
                }
                item.appendChild(badge);
            }

            var meta = el('span', 'shopeame-invoices__meta');
            meta.appendChild(el('span', 'shopeame-invoices__date', invoice.date));
            meta.appendChild(el('span', 'shopeame-invoices__total', invoice.total));
            item.appendChild(meta);

            list.appendChild(item);
        });

        panel.textContent = '';
        panel.appendChild(list);
        reposition(widget);
    }

    /**
     * Hace la petición de la lista: GET a data-url en el back-office y POST al controlador frontal
     * con el token del cliente en Mi cuenta.
     *
     * @param {HTMLElement} widget Contenedor de las facturas del pedido.
     * @returns {Promise<Response>}
     */
    function request(widget) {
        var url = widget.getAttribute('data-url');
        if (url) {
            return fetch(url, {credentials: 'same-origin', headers: {Accept: 'application/json'}});
        }

        var body = new FormData();
        body.append('ajax', '1');
        body.append('action', 'list');
        body.append('token', config.token);
        body.append('id_order', widget.getAttribute('data-order-id'));

        return fetch(config.listUrl, {method: 'POST', body: body, credentials: 'same-origin', headers: {Accept: 'application/json'}});
    }

    /**
     * Pide al servidor las facturas del pedido y las pinta.
     *
     * @param {HTMLElement} widget Contenedor de las facturas del pedido.
     */
    function load(widget) {
        widget.setAttribute('data-loaded', 'loading');
        renderMessage(widget, config.i18n.loading, false);

        request(widget)
            .then(function (response) {
                return response.json();
            })
            .then(function (result) {
                if (result && result.success && Array.isArray(result.invoices)) {
                    widget.setAttribute('data-loaded', '1');
                    renderInvoices(widget, result.invoices);

                    return;
                }

                widget.removeAttribute('data-loaded');
                renderMessage(widget, (result && result.message) || config.i18n.error, true);
            })
            .catch(function () {
                widget.removeAttribute('data-loaded');
                renderMessage(widget, config.i18n.error, true);
            });
    }

    /**
     * Coloca el desplegable bajo su botón. Usa posición fija para que no lo recorte el
     * contenedor con desplazamiento horizontal de la tabla de pedidos.
     *
     * @param {HTMLElement} toggle Botón del desplegable.
     * @param {HTMLElement} panel Desplegable.
     */
    function place(toggle, panel) {
        var rect = toggle.getBoundingClientRect();
        var width = panel.offsetWidth;
        var left = Math.max(8, Math.min(rect.right - width, window.innerWidth - width - 8));
        panel.style.top = (rect.bottom + 4) + 'px';
        panel.style.left = left + 'px';
    }

    /**
     * Vuelve a colocar el desplegable de un contenedor si está abierto (su ancho cambia al llegar
     * la lista). Los cuadros que se cargan solos no tienen botón y no se mueven.
     *
     * @param {HTMLElement} widget Contenedor de las facturas del pedido.
     */
    function reposition(widget) {
        var toggle = widget.querySelector('.shopeame-invoices__toggle[aria-expanded="true"]');
        if (toggle) {
            place(toggle, widget.querySelector('.shopeame-invoices__panel'));
        }
    }

    /**
     * Cierra los desplegables abiertos, salvo el indicado.
     *
     * @param {HTMLElement|null} except Contenedor que no se cierra.
     */
    function closeAll(except) {
        document.querySelectorAll('.shopeame-invoices__toggle[aria-expanded="true"]').forEach(function (toggle) {
            var widget = toggle.closest('.shopeame-invoices');
            if (widget === except) {
                return;
            }
            toggle.setAttribute('aria-expanded', 'false');
            widget.querySelector('.shopeame-invoices__panel').hidden = true;
        });
    }

    /**
     * Añade el botón de facturas junto a «Detalles» en cada pedido del historial de Mi cuenta. Es
     * una copia del enlace del tema, así que tiene su mismo estilo en cualquier tema.
     */
    function enhanceOrderHistory() {
        if (!config.history) {
            return;
        }

        document.querySelectorAll('a[data-link-action="view-order-details"]').forEach(function (details) {
            var match = /[?&]id_order=(\d+)/.exec(details.getAttribute('href') || '');
            if (!match || details.parentElement.querySelector('.shopeame-invoices')) {
                return;
            }

            var widget = el('span', 'shopeame-invoices shopeame-invoices--account');
            widget.setAttribute('data-order-id', match[1]);

            var toggle = details.cloneNode(true);
            toggle.removeAttribute('data-link-action');
            toggle.removeAttribute('title');
            toggle.classList.add('shopeame-invoices__toggle');
            toggle.setAttribute('href', details.getAttribute('href') + '#shopeame-invoices');
            toggle.setAttribute('role', 'button');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', config.i18n.orderInvoices.replace('%s', match[1]));

            // mismo contenido que «Detalles»: el icono del tema (si lo tiene) y el texto
            var icon = toggle.querySelector('.material-icons');
            var hasText = details.textContent.replace(icon ? icon.textContent : '', '').trim() !== '';
            Array.prototype.slice.call(toggle.childNodes).forEach(function (node) {
                if (node !== icon) {
                    toggle.removeChild(node);
                }
            });
            if (icon) {
                // icono «receipt» de Material Icons
                icon.textContent = 'receipt';
            }
            if (hasText) {
                toggle.appendChild(document.createTextNode((icon ? ' ' : '') + config.i18n.invoices));
            } else {
                toggle.title = config.i18n.invoices;
            }

            var panel = el('div', 'shopeame-invoices__panel');
            panel.hidden = true;
            panel.setAttribute('aria-live', 'polite');

            widget.appendChild(toggle);
            widget.appendChild(panel);
            details.insertAdjacentElement('afterend', widget);
        });
    }

    /**
     * Arranca el script cuando el documento está listo.
     */
    function init() {
        enhanceOrderHistory();
        document.querySelectorAll('.shopeame-invoices[data-autoload]').forEach(load);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    document.addEventListener('click', function (event) {
        var toggle = event.target.closest('.shopeame-invoices__toggle');
        if (!toggle) {
            // un clic fuera de los desplegables los cierra
            if (!event.target.closest('.shopeame-invoices')) {
                closeAll(null);
            }

            return;
        }

        // el botón de Mi cuenta es un enlace al detalle del pedido y el del listado está en una
        // fila que abre el pedido: abrimos el desplegable sin seguir el enlace ni abrir la fila
        event.preventDefault();
        event.stopPropagation();

        var widget = toggle.closest('.shopeame-invoices');
        var panel = widget.querySelector('.shopeame-invoices__panel');
        var open = toggle.getAttribute('aria-expanded') !== 'true';

        closeAll(widget);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        panel.hidden = !open;

        if (open) {
            if (!widget.getAttribute('data-loaded')) {
                load(widget);
            }
            place(toggle, panel);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeAll(null);
        }
    });

    // el desplegable tiene posición fija: al desplazar la página o cambiar su tamaño se cierra
    window.addEventListener('resize', function () {
        closeAll(null);
    });
    window.addEventListener('scroll', function (event) {
        // salvo si lo que se desplaza es la propia lista de facturas
        if (event.target.closest && event.target.closest('.shopeame-invoices__panel')) {
            return;
        }
        closeAll(null);
    }, true);
})();
