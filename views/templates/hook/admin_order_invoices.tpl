{**
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
 *}
{*
  Cuadro «Facturas del ERP» de la ficha del pedido en el back-office (hook displayAdminOrderSide).
  views/js/invoices.js pide las facturas al abrir la ficha (data-autoload) a la URL de data-url,
  que lleva el _token del empleado. No son las facturas de PrestaShop (pestaña Documentos).
*}
<div class="card mt-2" id="shopeame-order-invoices">
  <div class="card-header">
    <h3 class="card-header-title">{l s='ERP invoices' d='Modules.Shopeame.Admin'}</h3>
  </div>
  <div class="card-body">
    <div class="shopeame-invoices" data-autoload="1" data-order-id="{$shopeame_order_id|intval}" data-url="{$shopeame_list_url|escape:'html':'UTF-8'}">
      <div class="shopeame-invoices__panel" aria-live="polite"></div>
    </div>
  </div>
</div>
