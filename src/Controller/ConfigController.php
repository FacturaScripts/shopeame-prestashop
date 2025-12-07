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
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
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
        $apiKey = Configuration::get(ConfigFormManager::API_KEY) ?? "";
        if($apiKey !== "" && false === WebserviceKey::keyExists($apiKey)) {
            Configuration::updateValue(ConfigFormManager::API_KEY, "");
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

        // preparar la vista
        $uuid = Configuration::get(ConfigFormManager::UIID_KEY) ?? "";
        $webhookToken = Configuration::get(ConfigFormManager::WEBHOOK_TOKEN_KEY) ?? "";
        $apiKey = Configuration::get(ConfigFormManager::API_KEY) ?? "";
        $validFields = (!empty($uuid) && !empty($webhookToken) && $apiKey !== "");
        // esta configuración para revisar si está sincronizado el negocio
        $registeredShop = Configuration::get(ConfigFormManager::SHOEAME_SHOP_REGISTERED_KEY) ?? false;
        $errored = false;

        // comprobar si la tienda está conectada
        $requestReturn = $this->checkRegisteredShop($uuid, $webhookToken);
        if($requestReturn !== false) {
            // registrar el error
            $errored = $requestReturn['error'] === "" ? false : $requestReturn['error'];

            // si no hay error entonces comprobar si la tienda esta registrada
            Configuration::updateValue(ConfigFormManager::SHOEAME_SHOP_REGISTERED_KEY, $requestReturn['exists']);
            $registeredShop = $requestReturn['exists'];
        }

        // mostrar la vista con el formulario y la api key
        return $this->render('@Modules/shopeame/views/templates/admin/form.html.twig', [
            'configForm' => $form->createView(),
            'apiKey' => $apiKey,
            'enableGenerateApiKeyButton' => $apiKey === "",
            'enableRegisterButton' => $validFields && !$registeredShop,
            'registeredShop' => $registeredShop,
            'errored' => $errored // si unauthorized entonces mostrar no autorizado si no mostrar: "Error: %error%"
        ]);
    }

    /**
     * Genera una nueva clave de API para el webservice y vuelve a la configuración
     * @return RedirectResponse
     */
    public function generateApiKey(): RedirectResponse
    {
        // primero activar el webservice
        Configuration::updateValue('PS_WEBSERVICE', 1);

        // luego rescatar la última clave
        $oldKey = Configuration::get(ConfigFormManager::API_KEY) ?? "";

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
        Configuration::updateValue(ConfigFormManager::API_KEY, "");

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
        if(false === Configuration::updateValue(ConfigFormManager::API_KEY, $webserviceKey->key)) {
            // si falla el guardado que vuelva a la página de config con el error
            $this->addFlash('error', $this->trans('Failed to save the API key.', 'Modules.Shopeame.Admin'));
            return $this->redirectToRoute('shopeame_config_form');
        }

        // mostrar mensaje de éxito
        $this->addFlash('success', $this->trans('API Key generated successfully.', 'Modules.Shopeame.Admin'));

        return $this->redirectToRoute('shopeame_config_form');
    }

    /**
     * Registra la tienda en shopea.me (entry del boton de registrar tienda)
     * @return RedirectResponse
     */
    public function registerShop(): RedirectResponse
    {
        // obtener parámetros
        $uuid = Configuration::get(ConfigFormManager::UIID_KEY) ?? "";
        $webhookToken = Configuration::get(ConfigFormManager::WEBHOOK_TOKEN_KEY) ?? "";
        $apiKey = Configuration::get(ConfigFormManager::API_KEY) ?? "";
        $shopUrl = Context::getContext()->shop->getBaseURL(true);

        // cuerpo de la request
        $payload = [
            "event" => "register_prestashop",
            "erp" => $uuid,
            "token" => $webhookToken,
            "shop" => [
                "url" => $shopUrl,
                "api_key" => $apiKey 
            ]
        ];

        // crear la request
        $client = HttpClient::create();
        try {
            // enviar la request
            $response = $client->request('POST', self::SHOPEAME_URL, [
                'json' => $payload,
                'timeout' => 10,
            ]);

        } catch (TransportExceptionInterface $e) {
            // si la request falla, mostrar error
            $this->addFlash('error', $this->trans(
                'Failed connecting to Shopeame. Error: %error%', 
                'Modules.Shopeame.Admin',
                ['%error%' => $e->getMessage()]
            ));
        }

        // obtener el código de estado
        $http_code = $response->getStatusCode();

        // Si la request es exitosa (200-299)
        if ($http_code >= 200 && $http_code < 300) {
            $this->addFlash('success', $this->trans('Shop registered successfully.', 'Modules.Shopeame.Admin'));

            // actualizar si está correcta la respuesta
            $content = $response->toArray();
            if(isset($content['success']) && $content['success'] === true) {

                // actualizar la configuración
                Configuration::updateValue(ConfigFormManager::UIID_KEY, $content['shop_uuid']);
                Configuration::updateValue(ConfigFormManager::WEBHOOK_TOKEN_KEY, $content['webhook_token']);
            }

        } else {
            // Si no, mostrar error.
            $content = $response->getContent(false); 
            
            $this->addFlash('error', $this->trans(
                'Failed to register the shop: %response%',
                'Modules.Shopeame.Admin',
                ['%response%' => $content["error"]]
            ));
        }
        
        //debug line: $this->addFlash('info', print_r($response->getContent(false), true));

        // volver al formulario
        return $this->redirectToRoute('shopeame_config_form');
    }

    /**
     * Función que conecta con shopea.me y verifica si la tienda esta registrada
     * @param string $uuid
     * @param string $webhookToken
     * @return string "registered" o "unregistered" o "error"
     */
    private function checkRegisteredShop($uuid, $webhookToken): false|array
    {
        // cuerpo de la request
        $payload = [
            "event" => "check_shop",
            "token" => $webhookToken,
            "shop" => $uuid
        ];

        // crear la request
        $client = HttpClient::create();
        try {
            // enviar la request
            $response = $client->request('POST', self::SHOPEAME_URL, [
                'json' => $payload,
                'timeout' => 10,
            ]);
        } catch (TransportExceptionInterface $e) {
            // si la request falla, mostrar error
            $this->addFlash('error', $this->trans(
                'Failed connecting to Shopeame. Error: %error%',
                'Modules.Shopeame.Admin',
                ['%error%' => $e->getMessage()]
            ));

            return false;
        }

        $content = $response->toArray(false);
        $existsShop = (bool)$content["exists"];
        $error = key_exists("error", $content) ? $content["error"] : "";

        return [
            "exists" => $existsShop,
            "error" => $error
        ];
    }
}
