<?php
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
            ->add('UUID', TextType::class, [
                'label' => $this->trans('ERP UUID', 'Modules.Shopeame.Admin'),
                'help' => $this->trans('It is 13 characters long', 'Modules.Shopeame.Admin'),
            ])
            ->add('webhookToken', TextType::class, [
                'label' => $this->trans('Webhook Token', 'Modules.Shopeame.Admin'),
                'help' => $this->trans('It is 32 characters long', 'Modules.Shopeame.Admin'),
            ]);
    }
}
