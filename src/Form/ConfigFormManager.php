<?php
declare(strict_types=1);

namespace PrestaShop\Module\Shopeame\Form;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Configuration;
use PrestaShop\PrestaShop\Core\Configuration\DataConfigurationInterface;

/**
 * Clase encargada de rescatar, validar y actualizar la configuración
 */
final class ConfigFormManager implements DataConfigurationInterface
{
    // Claves de la configuración
    public const UIID_KEY = 'SHOPEAME_UUID';
    public const WEBHOOK_TOKEN_KEY = 'SHOPEAME_WEBHOOK_TOKEN';
    public const API_KEY = 'SHOEAME_API_KEY';
    public const SHOEAME_SHOP_REGISTERED_KEY = 'SHOEAME_SHOP_REGISTERED';
    public const UUID_LENGHT = 13;
    public const WEBHOOK_TOKEN_LENGHT = 32;

    // /**
    //  * Función para introducir datos en la configuración de forma manual
    //  * ES SOLO PARA CASOS ESPECIALES
    //  */
    // public function setFormValue(string $key, $value): void
    // {
    //     $this->configuration->set($key, $value);
    // }

    /**
     * Aquí se rescatan los datos de la configuración
     */
    public function getConfiguration(): array
    {
        $return = [];

        $return[self::UIID_KEY] = Configuration::get(self::UIID_KEY) ?? "";
        $return[self::WEBHOOK_TOKEN_KEY] = Configuration::get(self::WEBHOOK_TOKEN_KEY) ?? "";
        // injectar el valor de la api key para que aparezca en el formulario como no editable
        $return[self::API_KEY] = Configuration::get(self::API_KEY) ?? "";

        return $return;
    }

    /**
     * Aquí se comprueba que los datos cumplen con las reestricciones
     * y se introducen o muestran errores si no lo son
     */
    public function updateConfiguration(array $configuration): array
    {
        $errors = [];

        // filtrar y actualizar datos
        if ($this->validateConfiguration($configuration)) {
            $uuid = trim($configuration[self::UIID_KEY]);
            if (strlen($uuid) === self::UUID_LENGHT) {
                Configuration::updateValue(static::UIID_KEY, $uuid);
            } else {
                $errors[] = "The UUID is not valid";
            }
            $webhookToken = trim($configuration[self::WEBHOOK_TOKEN_KEY]);
            if(strlen($webhookToken) === self::WEBHOOK_TOKEN_LENGHT) {
                Configuration::updateValue(static::WEBHOOK_TOKEN_KEY, $webhookToken);
            } else {
                $errors[] = "The Webhook is not valid";
            }
        }

        return $errors;
    }

    /**
     * Para verificar que son tipos de datos válidos
     *
     * @return bool Returns true if no exception are thrown
     */
    public function validateConfiguration(array $configuration): bool
    {
        $issetUUID = isset($configuration[self::UIID_KEY]);
        $issetWebhookToken = isset($configuration[self::WEBHOOK_TOKEN_KEY]);
        if(!$issetUUID || !$issetWebhookToken) {
            return false;
        }

        return true;
    }
}
