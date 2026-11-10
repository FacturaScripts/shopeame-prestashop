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

namespace PrestaShop\Module\Shopeame\Invoice;

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\Module\Shopeame\Controller\ConfigController;
use PrestaShop\Module\Shopeame\Form\ConfigFormManager;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Facturas del ERP de los pedidos de la tienda, pedidas a shopea.me en el momento.
 *
 * Las facturas no se guardan en PrestaShop: la lista se pide a shopea.me con el evento
 * order_invoices y cada PDF con invoice_pdf. shopea.me las busca en el ERP del cliente, así que
 * siempre aparecen las facturas parciales, las que agrupan varios pedidos y las rectificativas tal
 * como están en el ERP. No tienen nada que ver con las facturas nativas de PrestaShop.
 *
 * La usan el controlador del back-office (InvoiceController), el controlador frontal de Mi cuenta
 * (controllers/front/invoices.php) y los hooks de shopeame.php. No usa Context: el traductor se
 * recibe en el constructor.
 */
class InvoiceService
{
    /** Segundos máximos de espera de shopea.me, que a su vez consulta el ERP. */
    public const TIMEOUT = 30;

    /** Dominio de las traducciones del back-office. */
    private const ADMIN_DOMAIN = 'Modules.Shopeame.Admin';

    /** Dominio de las traducciones del front-office (Mi cuenta). */
    private const SHOP_DOMAIN = 'Modules.Shopeame.Shop';

    /**
     * @var TranslatorInterface traductor de PrestaShop
     */
    private $translator;

    /**
     * @param TranslatorInterface $translator traductor de PrestaShop
     */
    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Indica si la tienda tiene guardados el UUID y el token de shopea.me.
     *
     * Igual que en los avisos de pedidos, no se mira SHOPEAME_SHOP_REGISTERED (ver 3-pedidos en
     * Doc/Context): si las credenciales no son válidas, shopea.me responde 401 y se avisa al usuario.
     *
     * @return bool
     */
    public static function isLinked(): bool
    {
        return ConfigFormManager::getString(ConfigFormManager::UUID_KEY) !== ''
            && ConfigFormManager::getString(ConfigFormManager::WEBHOOK_TOKEN_KEY) !== '';
    }

    /**
     * Devuelve el ajuste «Permitir que los clientes descarguen las facturas de sus pedidos desde Mi
     * cuenta». Está activado por defecto: si la clave no existe (instalaciones anteriores a esta
     * función), se considera activado.
     *
     * @return bool
     */
    public static function customerSettingEnabled(): bool
    {
        $value = \Configuration::get(ConfigFormManager::CUSTOMER_INVOICES_KEY);

        return false === $value || '1' === (string) $value;
    }

    /**
     * Indica si los clientes pueden ver y descargar las facturas de sus pedidos desde Mi cuenta:
     * la tienda está vinculada y el ajuste, activado.
     *
     * @return bool
     */
    public static function customersEnabled(): bool
    {
        return self::isLinked() && self::customerSettingEnabled();
    }

    /**
     * Pide a shopea.me las facturas del ERP de un pedido.
     *
     * @param int $idOrder id del pedido en PrestaShop
     *
     * @return array ['ok' => bool, 'status' => int, 'error' => string, 'invoices' => array]; cada
     *               factura lleva id, code, date (Y-m-d), total, currency, is_refund y refund_of
     */
    public function fetchInvoices(int $idOrder): array
    {
        $result = $this->post([
            'event' => 'order_invoices',
            'order_id' => $idOrder,
        ]);
        $data = json_decode($result['body'], true);
        $data = is_array($data) ? $data : [];

        if (null === $result['error'] && $result['status'] === 200 && isset($data['invoices']) && is_array($data['invoices'])) {
            $invoices = [];
            foreach ($data['invoices'] as $invoice) {
                if (!is_array($invoice) || (int) ($invoice['id'] ?? 0) <= 0) {
                    continue;
                }

                $invoices[] = [
                    'id' => (int) $invoice['id'],
                    'code' => (string) ($invoice['code'] ?? ''),
                    'date' => (string) ($invoice['date'] ?? ''),
                    'total' => (float) ($invoice['total'] ?? 0),
                    'currency' => preg_match('/^[A-Z]{3}$/', (string) ($invoice['currency'] ?? '')) ? (string) $invoice['currency'] : '',
                    'is_refund' => !empty($invoice['is_refund']),
                    'refund_of' => (string) ($invoice['refund_of'] ?? ''),
                ];
            }

            return ['ok' => true, 'status' => 200, 'error' => '', 'invoices' => $invoices];
        }

        $error = $result['error'] ?? (is_string($data['error'] ?? null) ? $data['error'] : 'HTTP ' . $result['status']);
        $this->logError('order_invoices', $idOrder, $result['status'], $error);

        return ['ok' => false, 'status' => $result['status'], 'error' => $error, 'invoices' => []];
    }

    /**
     * Pide a shopea.me el PDF de una factura del ERP de un pedido. shopea.me solo lo entrega si la
     * factura está entre las de ese pedido de esta tienda.
     *
     * @param int $idOrder id del pedido en PrestaShop
     * @param int $idInvoice id de la factura en el ERP
     *
     * @return array ['ok' => bool, 'status' => int, 'error' => string, 'body' => string, 'filename' => string]
     */
    public function fetchPdf(int $idOrder, int $idInvoice): array
    {
        $result = $this->post([
            'event' => 'invoice_pdf',
            'order_id' => $idOrder,
            'invoice_id' => $idInvoice,
        ]);

        // solo reenviamos la respuesta si de verdad es un PDF
        if (null === $result['error'] && $result['status'] === 200 && str_starts_with($result['body'], '%PDF')) {
            return [
                'ok' => true,
                'status' => 200,
                'error' => '',
                'body' => $result['body'],
                'filename' => self::filenameFromHeader($result['disposition'], $idInvoice),
            ];
        }

        $data = json_decode($result['body'], true);
        $error = $result['error'] ?? (is_array($data) && is_string($data['error'] ?? null) ? $data['error'] : 'HTTP ' . $result['status']);
        $this->logError('invoice_pdf', $idOrder, $result['status'], $error);

        return ['ok' => false, 'status' => $result['status'], 'error' => $error, 'body' => '', 'filename' => ''];
    }

    /**
     * Traduce un error de shopea.me a un mensaje para el usuario.
     *
     * @param int $status código HTTP de la respuesta (0 si no hubo conexión)
     * @param string $error código de error que devuelve shopea.me
     * @param bool $forCustomer si el mensaje es para el cliente: sin detalles del ERP ni de shopea.me
     *
     * @return string
     */
    public function errorMessage(int $status, string $error, bool $forCustomer): string
    {
        if ($forCustomer) {
            return $this->translator->trans('The invoices are not available right now. Try again later.', [], self::SHOP_DOMAIN);
        }

        if ($status === 0) {
            return $this->translator->trans('Could not connect with shopea.me. Try again in a few minutes.', [], self::ADMIN_DOMAIN);
        }
        if ($error === 'unknown-event') {
            return $this->translator->trans('shopea.me does not support invoice downloads yet.', [], self::ADMIN_DOMAIN);
        }
        if ($error === 'erp-offline') {
            return $this->translator->trans('Your ERP is not available right now. Try again later.', [], self::ADMIN_DOMAIN);
        }
        if ($error === 'erp-not-supported') {
            return $this->translator->trans('Your ERP does not allow downloading invoices. Update FacturaScripts and the Shopeame plugin.', [], self::ADMIN_DOMAIN);
        }
        if ($error === 'invoice-not-found') {
            return $this->translator->trans('The invoice no longer belongs to this order. Reload the invoices.', [], self::ADMIN_DOMAIN);
        }
        if ($status === 401 || $status === 410) {
            return $this->translator->trans('The store is not linked with shopea.me.', [], self::ADMIN_DOMAIN);
        }

        return $this->translator->trans('The ERP invoices could not be loaded. Try again in a few minutes.', [], self::ADMIN_DOMAIN);
    }

    /**
     * Envía un evento de facturas a shopea.me con las credenciales de la tienda.
     *
     * Nunca lanza excepciones: los fallos de red, DNS, TLS y tiempo de espera se devuelven en error.
     *
     * @param array $payload evento y datos del pedido
     *
     * @return array ['status' => int, 'body' => string, 'disposition' => string, 'error' => string|null]
     */
    private function post(array $payload): array
    {
        $payload['shop'] = ConfigFormManager::getString(ConfigFormManager::UUID_KEY);
        $payload['token'] = ConfigFormManager::getString(ConfigFormManager::WEBHOOK_TOKEN_KEY);

        try {
            // HttpClient es asíncrono: los errores de transporte afloran al leer la respuesta
            $response = HttpClient::create()->request('POST', ConfigController::webHooksUrl(), [
                'json' => $payload,
                'timeout' => self::TIMEOUT,
                'max_duration' => self::TIMEOUT,
            ]);
            $status = (int) $response->getStatusCode();
            $headers = $response->getHeaders(false);
            $body = $response->getContent(false);
        } catch (\Throwable $e) {
            return ['status' => 0, 'body' => '', 'disposition' => '', 'error' => $e->getMessage()];
        }

        return [
            'status' => $status,
            'body' => $body,
            'disposition' => (string) ($headers['content-disposition'][0] ?? ''),
            'error' => null,
        ];
    }

    /**
     * Devuelve el nombre del archivo que indica shopea.me (el código de la factura), o uno
     * genérico si no lo indica. Solo deja letras, números, guiones, guiones bajos y puntos.
     *
     * @param string $header cabecera Content-Disposition de la respuesta
     * @param int $idInvoice id de la factura en el ERP
     *
     * @return string
     */
    private static function filenameFromHeader(string $header, int $idInvoice): string
    {
        $filename = '';
        if (preg_match('/filename="?([^";]+)"?/i', $header, $matches)) {
            $filename = (string) preg_replace('/[^A-Za-z0-9._-]/', '', basename(trim($matches[1])));
        }

        if ($filename === '' || strtolower(substr($filename, -4)) !== '.pdf') {
            $filename = 'invoice-' . $idInvoice . '.pdf';
        }

        return $filename;
    }

    /**
     * Anota un fallo en Parámetros Avanzados → Registros/Logs, como los avisos de pedidos.
     *
     * @param string $event evento enviado a shopea.me
     * @param int $idOrder id del pedido
     * @param int $status código HTTP de la respuesta (0 si no hubo conexión)
     * @param string $error detalle del error
     *
     * @return void
     */
    private function logError(string $event, int $idOrder, int $status, string $error): void
    {
        // nivel 3 si no hubo conexión, 2 si shopea.me rechazó la petición (igual que en los pedidos)
        \PrestaShopLogger::addLog(
            'Shopeame: the ' . $event . ' event failed for order ' . $idOrder . ' (HTTP ' . $status . '): ' . $error,
            $status === 0 ? 3 : 2,
            null,
            'Order',
            $idOrder,
            true
        );
    }
}
