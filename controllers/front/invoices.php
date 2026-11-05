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
if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\Module\Shopeame\Invoice\InvoiceService;

/**
 * Facturas del ERP de los pedidos del cliente en Mi cuenta: la lista (JSON para la sección del
 * detalle del pedido y el botón del historial) y la descarga de cada PDF a través de shopea.me.
 *
 * Solo para clientes con sesión iniciada ($auth). Antes de leer nada comprueba el token del cliente
 * (Tools::getToken(false)) y, después, que el comercio permite las descargas en Mi cuenta y que el
 * pedido es del cliente ($order->id_customer). Los errores se muestran sin detalles del ERP.
 */
class ShopeameInvoicesModuleFrontController extends ModuleFrontController
{
    /** @var bool Exige un cliente con sesión iniciada; si no, PrestaShop lleva al inicio de sesión. */
    public $auth = true;

    /** @var bool Los invitados no tienen Mi cuenta. */
    public $guestAllowed = false;

    /** @var bool Fuerza el uso de HTTPS cuando la tienda lo tiene habilitado. */
    public $ssl = true;

    /**
     * Atiende la petición: action=list devuelve la lista en JSON y action=download, el PDF.
     *
     * @return void
     */
    public function postProcess(): void
    {
        $action = (string) Tools::getValue('action');
        if ($action !== 'list' && $action !== 'download') {
            Tools::redirect($this->context->link->getPageLink('history'));
        }

        // el token va primero: sin él no se mira ni el ajuste ni el pedido
        if (!hash_equals((string) Tools::getToken(false), (string) Tools::getValue('token'))
            || !InvoiceService::customersEnabled()) {
            $this->deny($action);
        }

        // solo el cliente dueño del pedido puede ver sus facturas
        $order = new Order((int) Tools::getValue('id_order'));
        if (!Validate::isLoadedObject($order) || (int) $order->id_customer !== (int) $this->context->customer->id) {
            $this->deny($action);
        }

        $service = new InvoiceService($this->context->getTranslator());
        if ($action === 'list') {
            $this->sendList($service, $order);
        }

        $this->sendPdf($service, $order, (int) Tools::getValue('invoice_id'));
    }

    /**
     * Rechaza la petición con el aviso genérico: en JSON para la lista y volviendo al historial de
     * pedidos para la descarga.
     *
     * @param string $action list o download
     *
     * @return void
     */
    private function deny(string $action): void
    {
        if ($action === 'list') {
            $this->sendJson(['success' => false, 'message' => $this->notAllowedMessage()], 403);
        }

        $this->errors[] = $this->notAllowedMessage();
        $this->redirectWithNotifications($this->context->link->getPageLink('history'));
    }

    /**
     * Envía en JSON las facturas del pedido con sus enlaces de descarga.
     *
     * @param InvoiceService $service
     * @param Order $order pedido del cliente, ya comprobado
     *
     * @return void
     */
    private function sendList(InvoiceService $service, Order $order): void
    {
        $result = $service->fetchInvoices((int) $order->id);
        if (!$result['ok']) {
            $this->sendJson(['success' => false, 'message' => $service->errorMessage($result['status'], $result['error'], true)], 502);
        }

        $currency = new Currency((int) $order->id_currency);
        $locale = $this->context->getCurrentLocale();
        $invoices = [];
        foreach ($result['invoices'] as $invoice) {
            $invoices[] = [
                'code' => $invoice['code'],
                'date' => $invoice['date'] !== '' ? Tools::displayDate($invoice['date']) : '',
                'total' => $locale->formatPrice($invoice['total'], $invoice['currency'] !== '' ? $invoice['currency'] : (string) $currency->iso_code),
                'is_refund' => $invoice['is_refund'],
                'refund_of' => $invoice['refund_of'],
                // getModuleLink() devuelve la URL con & sin escapar: va en JSON y el script la asigna tal cual
                'url' => $this->context->link->getModuleLink('shopeame', 'invoices', [
                    'action' => 'download',
                    'id_order' => (int) $order->id,
                    'invoice_id' => $invoice['id'],
                    'token' => Tools::getToken(false),
                ]),
            ];
        }

        $this->sendJson(['success' => true, 'invoices' => $invoices], 200);
    }

    /**
     * Entrega al navegador el PDF de una factura como descarga, sin guardarlo en PrestaShop. Si
     * falla, vuelve al detalle del pedido con un aviso genérico.
     *
     * @param InvoiceService $service
     * @param Order $order pedido del cliente, ya comprobado
     * @param int $idInvoice id de la factura en el ERP
     *
     * @return void
     */
    private function sendPdf(InvoiceService $service, Order $order, int $idInvoice): void
    {
        if ($idInvoice <= 0) {
            $this->deny('download');
        }

        $result = $service->fetchPdf((int) $order->id, $idInvoice);
        if (!$result['ok']) {
            $this->errors[] = $service->errorMessage($result['status'], $result['error'], true);
            $this->redirectWithNotifications($this->context->link->getPageLink('order-detail', null, null, ['id_order' => (int) $order->id]));
        }

        // descartamos cualquier salida previa para no corromper el PDF
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
        header('Content-Length: ' . strlen($result['body']));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        // contenido binario del PDF, comprobado en InvoiceService que empieza por %PDF
        echo $result['body'];
        exit;
    }

    /**
     * Mensaje para el cliente cuando no puede ver las facturas de un pedido. Es el mismo aviso
     * genérico que cuando el ERP no responde: no revela si el pedido existe ni de quién es.
     *
     * @return string
     */
    private function notAllowedMessage(): string
    {
        return $this->trans('The invoices are not available right now. Try again later.', [], 'Modules.Shopeame.Shop');
    }

    /**
     * Envía una respuesta JSON y termina la petición.
     *
     * @param array $data
     * @param int $status código HTTP
     *
     * @return void
     */
    private function sendJson(array $data, int $status): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }
}
