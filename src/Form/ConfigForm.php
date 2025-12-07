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

use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
/**
 * Clase encargada de construir el formulario de la configuración
 */
class ConfigForm extends TranslatorAwareType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // injectar el campo de la api key para que aparezca en el formulario como no editable
            ->add(ConfigFormManager::API_KEY, TextType::class, [
                'label' => $this->trans('API Key', 'Modules.Shopeame.Admin'),
                'disabled' => true,
            ])
            ->add(ConfigFormManager::UIID_KEY, TextType::class, [
                'label' => $this->trans('ERP UUID', 'Modules.Shopeame.Admin'),
                'help' => $this->trans('It is 13 characters long', 'Modules.Shopeame.Admin'),
            ])
            ->add(ConfigFormManager::WEBHOOK_TOKEN_KEY, TextType::class, [
                'label' => $this->trans('Webhook Token', 'Modules.Shopeame.Admin'),
                'help' => $this->trans('It is 32 characters long', 'Modules.Shopeame.Admin'),
            ]);
    }
}
