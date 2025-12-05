<?php
declare(strict_types=1);

namespace PrestaShop\Module\Shopeame\Controller;

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controlador encargado de la página de configuración del plugin Shopeame
 */
class ConfigController extends FrameworkBundleAdminController
{
    /**
     * Flujo principal de la página de configuración
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $textFormDataHandler = $this->get('prestashop.module.shopeame.form.config_form_handler');

        $textForm = $textFormDataHandler->getForm();
        $textForm->handleRequest($request);

        if ($textForm->isSubmitted() && $textForm->isValid()) {
            /** You can return array of errors in form handler and they can be displayed to user with flashErrors */
            $errors = $textFormDataHandler->save($textForm->getData());

            if (empty($errors)) {
                $this->addFlash('success', $this->trans('Successful update.', 'Admin.Notifications.Success'));

                return $this->redirectToRoute('shopeame_config_form');
            }

            $this->flashErrors($errors);
        }

        return $this->render('@Modules/shopeame/views/templates/admin/form.html.twig', [
            'configForm' => $textForm->createView()
        ]);
    }
}
