<?php
declare(strict_types=1);

namespace PrestaShop\Module\Shopeame\Form;

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Configuration\DataConfigurationInterface;
use PrestaShop\PrestaShop\Core\Form\FormDataProviderInterface;

/**
 * Clase encargada de pasar el formulario de la configuración
 *
 * Class ConfigFormProvider
 */
class ConfigFormProvider implements FormDataProviderInterface
{
    /**
     * @var DataConfigurationInterface
     */
    private $configFormManager;

    public function __construct(DataConfigurationInterface $configFormManager)
    {
        $this->configFormManager = $configFormManager;
    }

    public function getData(): array
    {
        return $this->configFormManager->getConfiguration();
    }

    public function setData(array $data): array
    {
        return $this->configFormManager->updateConfiguration($data);
    }
}
