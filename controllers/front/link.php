<?php
/**
 * Receptor frontal de las credenciales enviadas por ShopeameServer.
 * Copyright (C) 2026 Shopeame
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Recibe una vinculación firmada y la guarda en la configuración de la tienda actual.
 */
class ShopeameLinkModuleFrontController extends ModuleFrontController
{
    /** Ventana máxima, en segundos, de una petición firmada. */
    private const TIMESTAMP_TOLERANCE = 300;

    /** @var bool Fuerza el uso de HTTPS cuando la tienda lo tiene habilitado. */
    public $ssl = true;

    /**
     * Rechaza cualquier acceso que no sea una petición POST.
     */
    public function initContent(): void
    {
        $this->sendJson(['error' => 'method-not-allowed'], 405);
    }

    /**
     * Procesa la entrega de credenciales realizada por ShopeameServer.
     */
    public function postProcess(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->sendJson(['error' => 'method-not-allowed'], 405);
        }

        $body = (string)file_get_contents('php://input');
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
            || !is_string($shopUuid) || strlen($shopUuid) !== 13
            || !is_string($webhookToken) || strlen($webhookToken) !== 32) {
            $this->sendJson(['error' => 'invalid-payload'], 422);
        }

        Configuration::updateValue('SHOPEAME_UUID', $shopUuid);
        Configuration::updateValue('SHOPEAME_WEBHOOK_TOKEN', $webhookToken);
        Configuration::updateValue('SHOEAME_SHOP_REGISTERED', true);
        $this->sendJson(['success' => true], 200);
    }

    /**
     * Comprueba la firma HMAC y que la petición no haya caducado.
     */
    private function hasValidSignature(string $body): bool
    {
        $timestamp = $_SERVER['HTTP_X_SHOPEAME_TIMESTAMP'] ?? '';
        $signature = $_SERVER['HTTP_X_SHOPEAME_SIGNATURE'] ?? '';
        $secret = Configuration::get('SHOEAME_API_KEY');
        if (!is_string($timestamp) || !ctype_digit($timestamp)
            || !is_string($signature) || !preg_match('/^[a-f0-9]{64}$/i', $signature)
            || !is_string($secret) || $secret === ''
            || abs(time() - (int)$timestamp) > self::TIMESTAMP_TOLERANCE) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
        return hash_equals($expected, strtolower($signature));
    }

    /**
     * Emite una respuesta JSON sin incluir credenciales ni detalles internos.
     */
    private function sendJson(array $data, int $status): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }
}
