<?php
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

declare(strict_types=1);

namespace PrestaShop\Module\Shopeame\Controller;

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\Module\Shopeame\Invoice\InvoiceService;
use PrestaShopBundle\Controller\Admin\PrestaShopAdminController;
use PrestaShopBundle\Security\Attribute\AdminSecurity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Facturas del ERP de los pedidos en el back-office: la lista (JSON para el botón del listado de
 * pedidos y el cuadro de la ficha) y la descarga de cada PDF a través de shopea.me.
 *
 * Las rutas (config/routes.yml) llevan _legacy_controller AdminOrders, así que solo entran los
 * empleados con permiso de lectura sobre los pedidos. Como todas las rutas del back-office, llevan
 * el _token del empleado en la URL, que PrestaShop comprueba antes de llegar aquí (CSRF).
 */
class InvoiceController extends PrestaShopAdminController
{
    /**
     * Devuelve en JSON las facturas del ERP de un pedido con sus enlaces de descarga.
     *
     * @param int $orderId id del pedido en PrestaShop
     * @param InvoiceService $invoiceService servicio de config/services.yml
     *
     * @return JsonResponse
     */
    #[AdminSecurity("is_granted('read', request.get('_legacy_controller'))")]
    public function list(
        int $orderId,
        #[Autowire(service: 'prestashop.module.shopeame.invoice.invoice_service')]
        InvoiceService $invoiceService,
    ): JsonResponse {
        if (!\Validate::isLoadedObject(new \Order($orderId))) {
            return $this->json(['success' => false, 'message' => $this->trans('The order does not exist.', [], 'Modules.Shopeame.Admin')], Response::HTTP_NOT_FOUND);
        }

        if (!InvoiceService::isLinked()) {
            return $this->json(['success' => false, 'message' => $this->trans('The store is not linked with shopea.me.', [], 'Modules.Shopeame.Admin')], Response::HTTP_CONFLICT);
        }

        $result = $invoiceService->fetchInvoices($orderId);
        if (!$result['ok']) {
            return $this->json([
                'success' => false,
                'message' => $invoiceService->errorMessage($result['status'], $result['error'], false),
            ], Response::HTTP_BAD_GATEWAY);
        }

        $language = $this->getLanguageContext();
        $currency = $this->getCurrencyContext()->getIsoCode();
        $invoices = [];
        foreach ($result['invoices'] as $invoice) {
            $time = strtotime($invoice['date']);
            $invoices[] = [
                'code' => $invoice['code'],
                'date' => $time ? date($language->getDateFormat(), $time) : '',
                'total' => $language->formatPrice($invoice['total'], $invoice['currency'] !== '' ? $invoice['currency'] : $currency),
                'is_refund' => $invoice['is_refund'],
                'refund_of' => $invoice['refund_of'],
                // el router añade el _token del empleado; la URL va en JSON, sin escapar para HTML
                'url' => $this->generateUrl('shopeame_order_invoice_pdf', [
                    'orderId' => $orderId,
                    'invoiceId' => $invoice['id'],
                ]),
            ];
        }

        return $this->json(['success' => true, 'invoices' => $invoices]);
    }

    /**
     * Descarga el PDF de una factura del ERP a través de shopea.me, sin guardarlo en PrestaShop.
     * Si falla, vuelve a la ficha del pedido con el error.
     *
     * @param int $orderId id del pedido en PrestaShop
     * @param int $invoiceId id de la factura en el ERP
     * @param InvoiceService $invoiceService servicio de config/services.yml
     *
     * @return Response
     */
    #[AdminSecurity("is_granted('read', request.get('_legacy_controller'))")]
    public function download(
        int $orderId,
        int $invoiceId,
        #[Autowire(service: 'prestashop.module.shopeame.invoice.invoice_service')]
        InvoiceService $invoiceService,
    ): Response {
        if (!\Validate::isLoadedObject(new \Order($orderId))) {
            $this->addFlash('error', $this->trans('The order does not exist.', [], 'Modules.Shopeame.Admin'));

            return $this->redirectToRoute('admin_orders_index');
        }

        if (!InvoiceService::isLinked()) {
            $this->addFlash('error', $this->trans('The store is not linked with shopea.me.', [], 'Modules.Shopeame.Admin'));

            return $this->redirectToRoute('admin_orders_view', ['orderId' => $orderId]);
        }

        $result = $invoiceService->fetchPdf($orderId, $invoiceId);
        if (!$result['ok']) {
            $this->addFlash('error', $invoiceService->errorMessage($result['status'], $result['error'], false));

            return $this->redirectToRoute('admin_orders_view', ['orderId' => $orderId]);
        }

        return self::pdfResponse($result['body'], $result['filename']);
    }

    /**
     * Construye la respuesta que entrega el PDF al navegador como descarga.
     *
     * @param string $body contenido del PDF
     * @param string $filename nombre del archivo (el código de la factura)
     *
     * @return Response
     */
    private static function pdfResponse(string $body, string $filename): Response
    {
        $response = new Response($body);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition('attachment', $filename));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
