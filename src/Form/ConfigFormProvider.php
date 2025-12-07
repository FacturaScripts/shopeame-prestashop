<?php
/**
 * This file is part of FacturaScripts
 * Copyright (C) 2025-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 * 
 * @author Abderrahim Darghal Belkacemi <abdedarghal111@gmail.com>
 */

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
