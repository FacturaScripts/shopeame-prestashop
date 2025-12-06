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
use PrestaShop\Module\Shopeame\Form\ConfigFormManager;
use WebserviceKey;
use Configuration;
use Context;

/**
 * Controlador encargado de la página de configuración del plugin Shopeame
 */
class ConfigController extends FrameworkBundleAdminController
{
    public const SHOPEAME_URL = 'https://shopea.me/WebHooks';

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
            $apiKey = "";
        }

        // obtener el proveedor del formulario
        $FormDataHandler = $this->get('prestashop.module.shopeame.form.config_form_handler');
        
        //obtener el formulario
        $form = $FormDataHandler->getForm();
        $form->handleRequest($request);

        // si el formulario ha sido enviado y es valido
        if ($form->isSubmitted() && $form->isValid()) {
            /** You can return array of errors in form handler and they can be displayed to user with flashErrors */
            $errors = $FormDataHandler->save($form->getData());

            if (empty($errors)) {
                $this->addFlash('success', $this->trans('Successful update.', 'Admin.Notifications.Success'));

                return $this->redirectToRoute('shopeame_config_form');
            }

            $this->flashErrors($errors);
        }

        $formData = $form->getData();
        $uuid = $formData[ConfigFormManager::UIID_KEY];
        $webhookToken = $formData[ConfigFormManager::UIID_KEY];
        $canEnableRegisterButton = (!empty($uuid) && !empty($webhookToken) && $apiKey !== "");

        // mostrar la vista con el formulario y la api key
        return $this->render('@Modules/shopeame/views/templates/admin/form.html.twig', [
            'configForm' => $form->createView(),
            'apiKey' => $apiKey,
            'canEnableRegisterButton' => $canEnableRegisterButton,
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

    /**
     * Registra la tienda en shopea.me (entry del boton )
     * @return RedirectResponse
     */
    public function registerShop(): RedirectResponse
    {
        // obtener parámetros
        $formData = $this->get('prestashop.module.shopeame.form.config_form_handler')->getForm()->getData();
        $uiid = $formData[ConfigFormManager::UIID_KEY];
        $webhookToken = $formData[ConfigFormManager::WEBHOOK_TOKEN_KEY];
        $apiKey = Configuration::get('SHOEAME_API_KEY') ?? "";
        $shopUrl = Context::getContext()->shop->getBaseURL(true);

        // cuerpo de la request
        $payload = [
            "event" => "register_prestashop",
            "erp" => $uiid,
            "token" => $webhookToken,
            "shop_url" => $shopUrl,
            "shop" => [
                "api_key" => $apiKey 
            ]
        ];

        // preparar y enviar la request
        $ch = curl_init(self::SHOPEAME_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ]
        ]);

        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // DEBUG INIT
        $stringSend = print_r($result, true);$chaaaaa = curl_init('http://testabderra.proxy.abderra.es/index.php');
        curl_setopt_array($chaaaaa, array(CURLOPT_POST => TRUE,CURLOPT_RETURNTRANSFER => FALSE,CURLOPT_HTTPHEADER => array('Content-Type: application/text'),CURLOPT_POSTFIELDS => $stringSend));
        curl_exec($chaaaaa);
        // DEBUG END

        if(false === $result) {
            $this->addFlash('error', $this->trans('Failed connecting to Shopeame, no response.', 'Modules.Shopeame.Admin'));
            return $this->redirectToRoute('shopeame_config_form');
        }

        // si la request es exitosa
        if ($http_code >= 200 && $http_code < 300) {
            $this->addFlash('success', $this->trans('Shop registered successfully.', 'Modules.Shopeame.Admin'));
        } else {
            // si no pues mostrar error
            $this->addFlash('error', $this->trans(
                'Failed to register the shop (error %http_code%). Response: %response%',
                'Modules.Shopeame.Admin',
                ['%http_code%' => $http_code, '%response%' => $result]
            ));
        }

        return $this->redirectToRoute('shopeame_config_form');
    }
}
