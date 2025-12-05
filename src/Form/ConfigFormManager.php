<?php
declare(strict_types=1);

namespace PrestaShop\Module\Shopeame\Form;

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Configuration\DataConfigurationInterface;
use PrestaShop\PrestaShop\Core\ConfigurationInterface;

/**
 * Clase encargada de rescatar, validar y actualizar la configuración
 */
final class ConfigFormManager implements DataConfigurationInterface
{
    public const UIID_KEY = 'UUID';
    public const WEBHOOK_TOKEN_KEY = 'webhookToken';
    public const UUID_LENGHT = 13;
    public const WEBHOOK_TOKEN_LENGHT = 32;

    /**
     * @var ConfigurationInterface
     */
    private $configuration;

    public function __construct(ConfigurationInterface $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * Aquí se rescatan los datos de la configuración
     */
    public function getConfiguration(): array
    {
        $return = [];

        $return[self::UIID_KEY] = $this->configuration->get(static::UIID_KEY);
        $return[self::WEBHOOK_TOKEN_KEY] = $this->configuration->get(static::WEBHOOK_TOKEN_KEY);

        print_r($return, false);

        return $return;
    }

    /**
     * Aquí se comprueba que los datos cumplen con las reestricciones
     * y se introducen o muestran errores si no lo son
     */
    public function updateConfiguration(array $configuration): array
    {
        $errors = [];

        if ($this->validateConfiguration($configuration)) {
            if (strlen($configuration[self::UIID_KEY]) === self::UUID_LENGHT) {
                $this->configuration->set(static::UIID_KEY, $configuration[static::UIID_KEY]);
            } else {
                $errors[] = "The UUID is not valid";
            }
            if(strlen($configuration[self::WEBHOOK_TOKEN_KEY]) === self::WEBHOOK_TOKEN_LENGHT) {
                $this->configuration->set(static::WEBHOOK_TOKEN_KEY, $configuration[static::WEBHOOK_TOKEN_KEY]);
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
