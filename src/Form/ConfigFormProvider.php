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

use PrestaShop\PrestaShop\Core\Configuration\DataConfigurationInterface;
use PrestaShop\PrestaShop\Core\Form\FormDataProviderInterface;

/**
 * Proveedor de datos del formulario de configuración: delega en ConfigFormManager.
 */
class ConfigFormProvider implements FormDataProviderInterface
{
    /**
     * @var DataConfigurationInterface Gestor que lee y guarda la configuración.
     */
    private $configFormManager;

    /**
     * @param DataConfigurationInterface $configFormManager
     */
    public function __construct(DataConfigurationInterface $configFormManager)
    {
        $this->configFormManager = $configFormManager;
    }

    /**
     * Devuelve los datos con los que se rellena el formulario.
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->configFormManager->getConfiguration();
    }

    /**
     * Guarda los datos enviados y devuelve los errores de validación.
     *
     * @param array $data
     * @return array
     */
    public function setData(array $data): array
    {
        return $this->configFormManager->updateConfiguration($data);
    }
}
