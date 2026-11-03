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

use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Formulario de la opción 2 de la configuración: UUID y token del ERP en shopea.me.
 */
class ConfigForm extends TranslatorAwareType
{
    /**
     * Añade los campos del UUID y del token del ERP.
     *
     * @param FormBuilderInterface $builder
     * @param array $options
     *
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // la clave de API (clave del webservice) se muestra en la tarjeta "Opción 1" de la
            // vista, no como campo del formulario; aquí solo van los datos del ERP (Opción 2)
            ->add(ConfigFormManager::UUID_KEY, TextType::class, [
                'label' => $this->trans('ERP UUID', 'Modules.Shopeame.Admin'),
            ])
            ->add(ConfigFormManager::WEBHOOK_TOKEN_KEY, TextType::class, [
                'label' => $this->trans('ERP token', 'Modules.Shopeame.Admin'),
            ]);
    }
}
