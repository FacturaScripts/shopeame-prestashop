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

use PrestaShop\Module\Shopeame\Form\ConfigFormManager;

/**
 * Recibe la vinculación que envía ShopeameServer cuando el comercio da de alta la tienda desde
 * shopea.me (opción 1) y guarda el UUID y el token de la tienda.
 *
 * Solo acepta peticiones POST con una firma HMAC-SHA256 vigente, calculada con la clave del
 * webservice que generó el módulo: sin esa clave nadie puede vincular la tienda.
 */
class ShopeameLinkModuleFrontController extends ModuleFrontController
{
    /** Ventana máxima, en segundos, de una petición firmada. */
    private const TIMESTAMP_TOLERANCE = 300;

    /** @var bool Fuerza el uso de HTTPS cuando la tienda lo tiene habilitado. */
    public $ssl = true;

    /**
     * Rechaza cualquier acceso que no sea una petición POST.
     *
     * @return void
     */
    public function initContent(): void
    {
        $this->sendJson(['error' => 'method-not-allowed'], 405);
    }

    /**
     * Procesa la entrega de credenciales realizada por ShopeameServer.
     *
     * @return void
     */
    public function postProcess(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->sendJson(['error' => 'method-not-allowed'], 405);
        }

        // el cuerpo es JSON y forma parte de la firma, así que hay que leerlo en bruto
        $body = (string) file_get_contents('php://input');
        if (false === $this->hasValidSignature($body)) {
            $this->sendJson(['error' => 'unauthorized'], 401);
        }

        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            $this->sendJson(['error' => 'invalid-json'], 400);
        }

        $shopUuid = $payload['shop_uuid'] ?? '';
        $webhookToken = $payload['webhook_token'] ?? '';
        if (($payload['event'] ?? '') !== 'link_shop'
            || !is_string($shopUuid) || strlen($shopUuid) !== ConfigFormManager::UUID_LENGTH || !ctype_alnum($shopUuid)
            || !is_string($webhookToken) || strlen($webhookToken) !== ConfigFormManager::WEBHOOK_TOKEN_LENGTH || !ctype_alnum($webhookToken)) {
            $this->sendJson(['error' => 'invalid-payload'], 422);
        }

        Configuration::updateValue(ConfigFormManager::UUID_KEY, $shopUuid);
        Configuration::updateValue(ConfigFormManager::WEBHOOK_TOKEN_KEY, $webhookToken);
        Configuration::updateValue(ConfigFormManager::SHOP_REGISTERED_KEY, true);
        $this->sendJson(['success' => true], 200);
    }

    /**
     * Comprueba la firma HMAC y que la petición no haya caducado.
     *
     * @param string $body cuerpo en bruto de la petición
     *
     * @return bool
     */
    private function hasValidSignature(string $body): bool
    {
        $timestamp = $_SERVER['HTTP_X_SHOPEAME_TIMESTAMP'] ?? '';
        $signature = $_SERVER['HTTP_X_SHOPEAME_SIGNATURE'] ?? '';
        $secret = ConfigFormManager::getString(ConfigFormManager::API_KEY);
        if (!is_string($timestamp) || !ctype_digit($timestamp)
            || !is_string($signature) || !preg_match('/^[a-f0-9]{64}$/i', $signature)
            || $secret === ''
            || abs(time() - (int) $timestamp) > self::TIMESTAMP_TOLERANCE) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $body, $secret);

        return hash_equals($expected, strtolower($signature));
    }

    /**
     * Emite una respuesta JSON sin incluir credenciales ni detalles internos y termina.
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
