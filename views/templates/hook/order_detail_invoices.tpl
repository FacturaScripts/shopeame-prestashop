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
  Sección «Facturas» del detalle de un pedido en Mi cuenta (hook displayOrderDetail).
  views/js/invoices.js pide las facturas al abrir la página (data-autoload) por POST al
  controlador frontal del módulo, que comprueba que el pedido es del cliente.
*}
<section class="shopeame-order-invoices" id="shopeame-invoices">
  <h3 class="h3">{l s='Invoices' d='Modules.Shopeame.Shop'}</h3>
  <div class="shopeame-invoices" data-autoload="1" data-order-id="{$shopeame_order_id|intval}">
    <div class="shopeame-invoices__panel" aria-live="polite"></div>
  </div>
</section>
