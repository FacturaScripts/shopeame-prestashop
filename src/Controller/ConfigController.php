<?php
/**
 * This file is part of FacturaScripts
 * Copyright (C) 2025-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
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
 * @author FacturaCity SL <abdedarghal111@gmail.com>
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
     * URL del endpoint WebHooks de ShopeameServer. Se puede sobreescribir definiendo la constante
     * SHOPEAME_SERVER_URL (p. ej. en config/settings.inc.php) para apuntar a una instalación local.
     */
    public static function webHooksUrl(): string
    {
        return self::serverUrl() . '/WebHooks';
    }

    /**
     * URL base del servidor de shopea.me (sin ruta). Sobreescribible con la constante
     * SHOPEAME_SERVER_URL (p. ej. en config/defines_custom.inc.php) para desarrollo local.
     */
    public static function serverUrl(): string
    {
        return defined('SHOPEAME_SERVER_URL') ? rtrim(SHOPEAME_SERVER_URL, '/') : 'https://shopea.me';
    }

    /**
     * Indica si estamos en modo desarrollo (constante SHOPEAME_DEV en config/defines_custom.inc.php).
     */
    public static function isDev(): bool
    {
        return defined('SHOPEAME_DEV') && SHOPEAME_DEV;
    }

    /**
     * Opciones TLS para el HttpClient: en desarrollo no verifica el certificado autofirmado.
     */
    public static function httpTlsOptions(): array
    {
        return self::isDev() ? ['verify_peer' => false, 'verify_host' => false] : [];
    }

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
                // un solo botón "Vincular tienda": tras guardar el UUID/token del ERP, generamos la
                // clave de API si aún no existe y registramos (vinculamos) la tienda en shopea.me
                if (Configuration::get(ConfigFormManager::API_KEY) === false
                    || Configuration::get(ConfigFormManager::API_KEY) === "") {
                    $this->doGenerateApiKey();
                }
                $this->doRegisterShop();

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

        // Comprobar la conexión únicamente cuando existen credenciales locales completas.
        // Así, borrar los datos locales nunca provoca una petición posterior a ShopeameServer.
        if (!empty($uuid) && !empty($webhookToken)) {
            $requestReturn = $this->checkRegisteredShop($uuid, $webhookToken);
            if($requestReturn !== false) {
                // registrar el error
                $errored = $requestReturn['error'] === "" ? false : $requestReturn['error'];

                // si no hay error entonces comprobar si la tienda esta registrada
                Configuration::updateValue(ConfigFormManager::SHOEAME_SHOP_REGISTERED_KEY, $requestReturn['exists']);
                $registeredShop = $requestReturn['exists'];
            }
        }

        // mostrar la vista con el formulario y la api key
        return $this->render('@Modules/shopeame/views/templates/admin/form.html.twig', [
            // título de la cabecera del admin, el mismo que el de la app de Shopify
            'layoutTitle' => $this->trans('Conectar con shopea.me', 'Modules.Shopeame.Admin'),
            'configForm' => $form->createView(),
            'apiKey' => $apiKey,
            'shopUrl' => Context::getContext()->shop->getBaseURL(true),
            'shopLinkUrl' => self::serverUrl() . '/shop-link/' . $uuid,
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
        if ($this->doGenerateApiKey()) {
            $this->addFlash('success', $this->trans('Clave de API generada correctamente.', 'Modules.Shopeame.Admin'));
        }

        return $this->redirectToRoute('shopeame_config_form');
    }

    /**
     * Genera (o regenera) la clave del webservice y la guarda. Devuelve true si tuvo éxito.
     * No redirige; flashea los errores que se produzcan.
     */
    private function doGenerateApiKey(): bool
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
                $this->addFlash('error', $this->trans('No se pudo eliminar la clave de API anterior.', 'Modules.Shopeame.Admin'));
                return false;
            }
        }

        // poner a null la key en la configuración
        Configuration::updateValue(ConfigFormManager::API_KEY, "");

        // generar la clave
        $webserviceKey = new WebserviceKey();
        $webserviceKey->description = 'Clave de API de Shopeame (no la elimines ni la compartas)';
        $webserviceKey->key = Utilities::generateApiKey();
        $webserviceKey->active = true;
        if(false === $webserviceKey->save()) {
            $this->addFlash('error', $this->trans('No se pudo generar la clave de API.', 'Modules.Shopeame.Admin'));
            return false;
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
            $this->addFlash('error', $this->trans('No se pudieron establecer los permisos de la clave de API.', 'Modules.Shopeame.Admin'));
            return false;
        }

        // actualizar la configuración
        if(false === Configuration::updateValue(ConfigFormManager::API_KEY, $webserviceKey->key)) {
            $this->addFlash('error', $this->trans('No se pudo guardar la clave de API.', 'Modules.Shopeame.Admin'));
            return false;
        }

        return true;
    }

    /**
     * Registra la tienda en shopea.me (entry del boton de registrar tienda)
     * @return RedirectResponse
     */
    public function registerShop(): RedirectResponse
    {
        $this->doRegisterShop();
        return $this->redirectToRoute('shopeame_config_form');
    }

    /**
     * Registra (vincula) la tienda en shopea.me usando el UUID/token del ERP y la clave de API
     * guardados en la configuración. No redirige; flashea el resultado.
     */
    private function doRegisterShop(): void
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
            "new_shop" => [
                "url" => $shopUrl,
                "api_key" => $apiKey
            ]
        ];

        // crear la request
        $client = HttpClient::create();
        try {
            // enviar la request
            $response = $client->request('POST', self::webHooksUrl(), array_merge([
                'json' => $payload,
                'timeout' => 10,
            ], self::httpTlsOptions()));

        } catch (TransportExceptionInterface $e) {
            // si la request falla, mostrar error y volver (sin $response no podemos continuar)
            $this->addFlash('error', $this->trans(
                'Error al conectar con Shopea.me. Error: %error%',
                'Modules.Shopeame.Admin',
                ['%error%' => $e->getMessage()]
            ));
            return;
        }

        // obtener el código de estado
        $http_code = $response->getStatusCode();

        // Si la request es exitosa (200-299)
        if ($http_code >= 200 && $http_code < 300) {
            // actualizar si está correcta la respuesta
            $content = $response->toArray();
            if(isset($content['success']) && $content['success'] === true) {
                // actualizar la configuración
                Configuration::updateValue(ConfigFormManager::UIID_KEY, $content['shop_uuid']);
                Configuration::updateValue(ConfigFormManager::WEBHOOK_TOKEN_KEY, $content['webhook_token']);
                $this->addFlash('success', $this->linkSuccessMessage($content['message'] ?? ''));
            }

        } else {
            // Si no, mostrar error.
            $content = $response->getContent(false); 
            
            $this->addFlash('error', $this->trans(
                'No se pudo registrar la tienda: %response%',
                'Modules.Shopeame.Admin',
                ['%response%' => $content["error"]]
            ));
        }
        
    }

    /**
     * Desvincula la tienda de shopea.me (entry del botón de desvincular).
     * Pide al servidor el softDelete de la tienda y limpia la configuración local.
     * @param Request $request Petición que contiene el token CSRF de confirmación.
     * @return RedirectResponse
     */
    public function unlinkShop(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('shopeame_shop_actions', (string) $request->request->get('_token'))) {
            $this->addFlash('error', $this->trans('La solicitud no es válida. Vuelve a intentarlo.', 'Modules.Shopeame.Admin'));
            return $this->redirectToRoute('shopeame_config_form');
        }

        $uuid = Configuration::get(ConfigFormManager::UIID_KEY) ?? "";
        $webhookToken = Configuration::get(ConfigFormManager::WEBHOOK_TOKEN_KEY) ?? "";

        $payload = [
            "event" => "unlink_shop",
            "shop" => $uuid,
            "token" => $webhookToken
        ];

        $client = HttpClient::create();
        try {
            $response = $client->request('POST', self::webHooksUrl(), array_merge([
                'json' => $payload,
                'timeout' => 10,
            ], self::httpTlsOptions()));
            $ok = $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (TransportExceptionInterface $e) {
            $ok = false;
        }

        // limpiamos la configuración local aunque el servidor falle, para no dejar la tienda
        // en un estado intermedio; el usuario siempre podrá volver a vincularla
        Configuration::updateValue(ConfigFormManager::UIID_KEY, "");
        Configuration::updateValue(ConfigFormManager::WEBHOOK_TOKEN_KEY, "");
        Configuration::updateValue(ConfigFormManager::SHOEAME_SHOP_REGISTERED_KEY, false);

        if ($ok) {
            $this->addFlash('success', $this->trans('Tienda desvinculada correctamente.', 'Modules.Shopeame.Admin'));
        } else {
            $this->addFlash('warning', $this->trans('Se borraron las credenciales locales, pero Shopea.me no confirmó la desvinculación.', 'Modules.Shopeame.Admin'));
        }

        return $this->redirectToRoute('shopeame_config_form');
    }

    /**
     * Borra únicamente las credenciales de vinculación guardadas por el módulo.
     *
     * No realiza ninguna petición a ShopeameServer y conserva la clave del webservice.
     *
     * @param Request $request Petición que contiene el token CSRF de confirmación.
     * @return RedirectResponse
     */
    public function clearLocalShop(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('shopeame_shop_actions', (string) $request->request->get('_token'))) {
            $this->addFlash('error', $this->trans('La solicitud no es válida. Vuelve a intentarlo.', 'Modules.Shopeame.Admin'));
            return $this->redirectToRoute('shopeame_config_form');
        }

        Configuration::updateValue(ConfigFormManager::UIID_KEY, "");
        Configuration::updateValue(ConfigFormManager::WEBHOOK_TOKEN_KEY, "");
        Configuration::updateValue(ConfigFormManager::SHOEAME_SHOP_REGISTERED_KEY, false);

        $this->addFlash('success', $this->trans(
            'Se borraron los datos de conexión de la app sin modificar ShopeameServer.',
            'Modules.Shopeame.Admin'
        ));

        return $this->redirectToRoute('shopeame_config_form');
    }

    /**
     * Comprueba la conexión con shopea.me (entry del botón "Comprobar conexión") y muestra el
     * resultado. La comprobación real la hace checkRegisteredShop().
     * @return RedirectResponse
     */
    public function checkShop(): RedirectResponse
    {
        $uuid = Configuration::get(ConfigFormManager::UIID_KEY) ?? "";
        $webhookToken = Configuration::get(ConfigFormManager::WEBHOOK_TOKEN_KEY) ?? "";

        $result = $this->checkRegisteredShop($uuid, $webhookToken);
        if (is_array($result) && !empty($result['exists'])) {
            $this->addFlash('success', $this->trans('La conexión con Shopea.me funciona correctamente.', 'Modules.Shopeame.Admin'));
        } else {
            $this->addFlash('warning', $this->trans('No se pudo confirmar la conexión con Shopea.me.', 'Modules.Shopeame.Admin'));
        }

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
            $response = $client->request('POST', self::webHooksUrl(), array_merge([
                'json' => $payload,
                'timeout' => 10,
            ], self::httpTlsOptions()));
        } catch (TransportExceptionInterface $e) {
            // si la request falla, mostrar error
            $this->addFlash('error', $this->trans(
                'Error al conectar con Shopea.me. Error: %error%',
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

    /**
     * Traduce el código devuelto por ShopeameServer al mensaje para el comercio.
     *
     * @param string $message Código de resultado del servidor.
     * @return string
     */
    private function linkSuccessMessage(string $message): string
    {
        if ($message === 'shop-already-exists') {
            return $this->trans(
                'La tienda ya estaba vinculada. Se recuperaron sus datos sin crear una tienda nueva.',
                'Modules.Shopeame.Admin'
            );
        }
        if ($message === 'shop-reactivated') {
            return $this->trans(
                'La tienda se reactivó y quedó vinculada con Shopea.me correctamente.',
                'Modules.Shopeame.Admin'
            );
        }

        return $this->trans('Tienda vinculada con Shopea.me correctamente.', 'Modules.Shopeame.Admin');
    }
}
