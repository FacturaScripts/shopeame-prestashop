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

namespace PrestaShop\Module\Shopeame\Form;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Configuration;
use PrestaShop\PrestaShop\Core\Configuration\DataConfigurationInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Lee, valida y guarda la configuración del módulo (UUID y token del ERP o de la tienda).
 *
 * También define las claves de configuración del módulo, todas con el prefijo SHOPEAME_.
 */
final class ConfigFormManager implements DataConfigurationInterface
{
    /** UUID del ERP (antes de vincular) o de la tienda en shopea.me (después de vincular). */
    public const UUID_KEY = 'SHOPEAME_UUID';

    /** Token del ERP (antes de vincular) o token de webhooks de la tienda (después de vincular). */
    public const WEBHOOK_TOKEN_KEY = 'SHOPEAME_WEBHOOK_TOKEN';

    /** Clave del webservice de PrestaShop creada por el módulo para shopea.me. */
    public const API_KEY = 'SHOPEAME_API_KEY';

    /** Último estado de vinculación conocido (se refresca al abrir la configuración). */
    public const SHOP_REGISTERED_KEY = 'SHOPEAME_SHOP_REGISTERED';

    /** Todas las claves de configuración del módulo (se borran al desinstalar). */
    public const CONFIG_KEYS = [
        self::UUID_KEY,
        self::WEBHOOK_TOKEN_KEY,
        self::API_KEY,
        self::SHOP_REGISTERED_KEY,
    ];

    /** Longitud de los UUID de shopea.me. */
    public const UUID_LENGTH = 13;

    /** Longitud de los tokens de shopea.me. */
    public const WEBHOOK_TOKEN_LENGTH = 32;

    /**
     * @var TranslatorInterface Traductor de PrestaShop, inyectado desde config/services.yml.
     */
    private $translator;

    /**
     * @param TranslatorInterface $translator
     */
    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Devuelve un valor de configuración como cadena recortada.
     *
     * Configuration::get() devuelve false (no null) cuando la clave no existe, así que el operador
     * ?? no sirve para normalizarla.
     *
     * @param string $key
     *
     * @return string
     */
    public static function getString(string $key): string
    {
        $value = \Configuration::get($key);

        return is_string($value) ? trim($value) : '';
    }

    /**
     * Devuelve los datos con los que se rellena el formulario.
     *
     * @return array
     */
    public function getConfiguration(): array
    {
        return [
            self::UUID_KEY => self::getString(self::UUID_KEY),
            self::WEBHOOK_TOKEN_KEY => self::getString(self::WEBHOOK_TOKEN_KEY),
        ];
    }

    /**
     * Valida y guarda el UUID y el token del ERP enviados en el formulario.
     *
     * @param array $configuration
     *
     * @return array errores de validación (vacío si se guardó todo)
     */
    public function updateConfiguration(array $configuration): array
    {
        if (false === $this->validateConfiguration($configuration)) {
            return [$this->trans('Enter the ERP UUID and token.')];
        }

        $errors = [];
        $uuid = trim((string) $configuration[self::UUID_KEY]);
        if (strlen($uuid) === self::UUID_LENGTH && ctype_alnum($uuid)) {
            \Configuration::updateValue(self::UUID_KEY, $uuid);
        } else {
            $errors[] = $this->trans('The ERP UUID is not valid.');
        }

        $webhookToken = trim((string) $configuration[self::WEBHOOK_TOKEN_KEY]);
        if (strlen($webhookToken) === self::WEBHOOK_TOKEN_LENGTH && ctype_alnum($webhookToken)) {
            \Configuration::updateValue(self::WEBHOOK_TOKEN_KEY, $webhookToken);
        } else {
            $errors[] = $this->trans('The ERP token is not valid.');
        }

        return $errors;
    }

    /**
     * Comprueba que llegan los dos campos del formulario.
     *
     * @param array $configuration
     *
     * @return bool
     */
    public function validateConfiguration(array $configuration): bool
    {
        return isset($configuration[self::UUID_KEY], $configuration[self::WEBHOOK_TOKEN_KEY]);
    }

    /**
     * Traduce un mensaje del formulario con el dominio del módulo.
     *
     * @param string $message
     *
     * @return string
     */
    private function trans(string $message): string
    {
        return $this->translator->trans($message, [], 'Modules.Shopeame.Admin');
    }
}
