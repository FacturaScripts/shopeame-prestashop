<?php
declare(strict_types=1);

namespace PrestaShop\Module\Shopeame\Controller;

if (!defined('_PS_VERSION_')) {
    exit;
}

// añadir la librería de Utilities
require_once _PS_MODULE_DIR_ . 'shopeame/lib/Utilities.php';

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use PrestaShop\Module\Shopeame\Lib\Utilities;
use WebserviceKey;
use Configuration;

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
        // primero revisar si existe la api key para mostrar información actualizada
        $apiKey = Configuration::get('SHOEAME_API_KEY') ?? "";
        if($apiKey !== "" && false === WebserviceKey::keyExists($apiKey)) {
            Configuration::updateValue('SHOEAME_API_KEY', "");
        }


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

        // mostrar la vista con el formulario y la api key
        return $this->render('@Modules/shopeame/views/templates/admin/form.html.twig', [
            'configForm' => $textForm->createView(),
            'apiKey' => Configuration::get('SHOEAME_API_KEY') ?? "",
        ]);
    }

    /**
     * Genera una nueva clave de API para el webservice y vuelve a la configuración
     * @return RedirectResponse
     */
    public function generateApiKey(): RedirectResponse
    {
        $oldKey = Configuration::get('SHOEAME_API_KEY') ?? "";

        // eliminar la vieja key si existe
        if($oldKey !== "" && WebserviceKey::keyExists($oldKey)) {
            $oldKeyId = WebserviceKey::getIdFromKey($oldKey);
            $oldApiKey = new WebserviceKey($oldKeyId);
            if(false === $oldApiKey->delete()) {
                $this->addFlash('error', $this->trans('Failed to delete old API key.', 'Modules.Shopeame.Admin'));
                return $this->redirectToRoute('shopeame_config_form');
            }
        }

        // poner a null la key en la configuración
        Configuration::updateValue('SHOEAME_API_KEY', "");

        // generar la clave
        $webserviceKey = new WebserviceKey();
        $webserviceKey->description = 'Shopeame API Key (don\'t delete it and don\'t share it)';
        $webserviceKey->key = Utilities::generateApiKey();
        $webserviceKey->active = true;
        if(false === $webserviceKey->save()) {
            // si falla el guardado que vuelva a la página de config con el error
            $this->addFlash('error', $this->trans('Failed to generate API key.', 'Modules.Shopeame.Admin'));
            return $this->redirectToRoute('shopeame_config_form');
        }

        // obtener el array de permisos y marcar el check de todos
        // https://devdocs.prestashop-project.org/9/webservice/tutorials/creating-access/
        $permissions = [];
        foreach (\WebserviceRequest::getResources() as $resourceName => $descriptionAndClass) {
            $permissions[$resourceName] = [
                'GET' => 1,
                'POST' => 1,
                'PUT' => 1,
                'PATCH' => 1,
                'DELETE' => 1,
                'HEAD' => 1
            ];
        }

        // enviar los permisos
        if(false === WebserviceKey::setPermissionForAccount($webserviceKey->id, $permissions)) {
            // si falla el guardado que vuelva a la página de config con el error
            $this->addFlash('error', $this->trans('Failed to stablish API key permissions.', 'Modules.Shopeame.Admin'));
            return $this->redirectToRoute('shopeame_config_form');
        }

        // actualizar la configuración
        if(false === Configuration::updateValue('SHOEAME_API_KEY', $webserviceKey->key)) {
            // si falla el guardado que vuelva a la página de config con el error
            $this->addFlash('error', $this->trans('Failed to save the API key.', 'Modules.Shopeame.Admin'));
            return $this->redirectToRoute('shopeame_config_form');
        }

        // mostrar mensaje de éxito
        $this->addFlash('success', $this->trans('API Key generated successfully.', 'Modules.Shopeame.Admin'));

        return $this->redirectToRoute('shopeame_config_form');
    }
}
