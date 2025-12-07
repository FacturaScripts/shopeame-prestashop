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

if (!defined('_PS_VERSION_')) {
    exit;
}

// $autoloadPath = __DIR__ . '/vendor/autoload.php';
// if (file_exists($autoloadPath)) {
//     require_once $autoloadPath;
// }

use PrestaShop\Module\Shopeame\Controller\ConfigController;
use PrestaShop\Module\Shopeame\Form\ConfigFormManager;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class Shopeame extends Module
{
    /**
     * Constructor, el inicio de toda la aplicación
     */
    public function __construct()
    {
        // nombre de aplicación en [a-z,0-9]
        $this->name = 'shopeame';
        // sección de la aplicación https://devdocs.prestashop-project.org/9/modules/concepts/module-class/#tab
        $this->tab = 'administration';
        // versión de la app
        $this->version = '0.0.1';
        // autor
        $this->author = 'Abderrahim Darghal Belkacemi';
        // para que cargue en la página modules
        $this->need_instance = 0; // si está a 1, lo carga cuando está en la página modules
        // versiones de prestashop compatibles
        $this->ps_versions_compliancy = [
            'min' => '8.0.0',
            'max' => '9.99.99',
        ];
        // si usa el sistema de estilos de prestashop
        $this->bootstrap = true;

        // inicializador de la clase
        parent::__construct();

        // traducciones
        $this->displayName = $this->trans('Shopeame', [], 'Modules.Shopeame.Admin');
        $this->description = $this->trans('Plugin to connect PrestaShop to Shopea.me', [], 'Modules.Shopeame.Admin');

        $this->confirmUninstall = $this->trans('Are you sure you want to uninstall?', [], 'Modules.Shopeame.Admin');
    }

    /**
     * Se lanza cuando se instala el plugin
     */
    public function install()
    {
        // por si hay que activar alguna funcionalidad
        // https://devdocs.prestashop-project.org/9/development/multistore/
        // if (Shop::isFeatureActive()) {
        //     Shop::setContext(Shop::CONTEXT_ALL);
        // }

        // el webservice se activa al momento de crear la api key

        // realizar instalación del padre y añadir hooks o eventos
        return (
            parent::install()
            && $this->registerHook('actionValidateOrder')
            && $this->registerHook('actionOrderStatusPostUpdate')
            && $this->registerHook('actionOrderEdited')
        );
    }

    /**
     * Se ejecuta cada que se crea un pedido
     * https://devdocs.prestashop-project.org/9/modules/concepts/hooks/list-of-hooks/actionvalidateorder/
     */
    public function hookActionValidateOrder($params)
    {
        // si no se ha registrado la tienda entonces no hacer nada
        $registeredShop = Configuration::get(ConfigFormManager::SHOEAME_SHOP_REGISTERED_KEY) ?? false;
        if(!$registeredShop) {
            return;
        }

        // recoger todos los parámetros
        $cart = $params['cart'];
        $order = $params['order'];
        $customer = $params['customer'];
        $currency = $params['currency'];
        $orderStatus = $params['orderStatus'];
        $hasValidParams = (
            Validate::isLoadedObject($cart) 
            && Validate::isLoadedObject($order)
            && Validate::isLoadedObject($customer)
            && Validate::isLoadedObject($currency)
            && Validate::isLoadedObject($orderStatus)
        );
        // comprobar que esté todo ok
        if (!$hasValidParams) {
            PrestaShopLogger::addLog('Shopeame new_order event error: Invalid hook params received, aborting.', 3);
            return;
        }

        // notificar shopeame del pedido
        // cuerpo de la request
        // el objeto order contiene todos los ID: https://devdocs.prestashop-project.org/9/webservice/resources/orders/#order
        $payload = [
            "event" => "new_order",
            "shop" => Configuration::get(ConfigFormManager::UIID_KEY),
            "token" => Configuration::get(ConfigFormManager::WEBHOOK_TOKEN_KEY),
            "order" => $order,
            "products" => $order->getProducts()
        ];

        // enviar la request
        $this->sendEventRequest($payload);

        return true;
    }

    /**
     * Se ejecuta al cambiar el ESTADO (status) de un pedido
     */
    public function hookActionOrderStatusPostUpdate($params)
    {
        // si no se ha registrado la tienda entonces no hacer nada
        $registeredShop = Configuration::get(ConfigFormManager::SHOEAME_SHOP_REGISTERED_KEY) ?? false;
        if(!$registeredShop) {
            return;
        }

        // recoger todos los parámetros
        $order = new Order($params['id_order']);

        // comprobar que esté todo ok
        if (!Validate::isLoadedObject($order)) {
            PrestaShopLogger::addLog('Shopeame new_order event error: Invalid hook params received, aborting.', 3);
            return;
        }

        // notificar shopeame de la modificación del pedido
        // cuerpo de la request
        $payload = [
            "event" => "update_order",
            "shop" => Configuration::get(ConfigFormManager::UIID_KEY),
            "token" => Configuration::get(ConfigFormManager::WEBHOOK_TOKEN_KEY),
            "order" => $order,
            "products" => $order->getProducts()
        ];

        // enviar la request
        $this->sendEventRequest($payload);

        return true;
    }

    /**
     * Se ejecuta al editar el pedido manualmente en el BackOffice
     */
    public function hookActionOrderEdited($params)
    {
        // si no se ha registrado la tienda entonces no hacer nada
        $registeredShop = Configuration::get(ConfigFormManager::SHOEAME_SHOP_REGISTERED_KEY) ?? false;
        if(!$registeredShop) {
            return;
        }

        // recoger todos los parámetros
        $order = $params['order'];
        // comprobar que esté todo ok
        if (!Validate::isLoadedObject($order)) {
            PrestaShopLogger::addLog('Shopeame new_order event error: Invalid hook params received, aborting.', 3);
            return;
        }

        // notificar shopeame de la modificación del pedido
        $payload = [
            "event" => "update_order",
            "shop" => Configuration::get(ConfigFormManager::UIID_KEY),
            "token" => Configuration::get(ConfigFormManager::WEBHOOK_TOKEN_KEY),
            "order" => $order,
            "products" => $order->getProducts()
        ];

        // enviar la request
        $this->sendEventRequest($payload);

        return true;
    }

    /**
     * Se lanza cuando se desinstala el plugin
     */
    public function uninstall()
    {
        return parent::uninstall();
    }

    /**
     * This method handles the module's configuration page
     * @return string The page's HTML content 
     */
    public function getContent()
    {
        $route = $this->get('router')->generate('shopeame_config_form');
        Tools::redirectAdmin($route);
    }

    /**
     * Envía una request a shopea.me con el payload especificado
     */
    private function sendEventRequest($payload) {
        // crear la request
        $client = HttpClient::create();
        try {
            // enviar la request
            $response = $client->request('POST', ConfigController::SHOPEAME_URL, [
                'json' => $payload,
                'timeout' => 10,
            ]);
        } catch (TransportExceptionInterface $e) {
            // si la request falla, añadirlo a los logs
            // los log level son: 1 = info, 2 = warning, 3 = error
            PrestaShopLogger::addLog('Shopeame ' . $payload['event'] . ' event send error: ' . $e->getMessage(), 3, $e->getCode());
            return;
        }

        $content = $response->toArray(false);
        $message = key_exists("message", $content) ? $content["message"] : "";
        $error = key_exists("error", $content) ? $content["error"] : "";

        // loguear la respuesta
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            PrestaShopLogger::addLog('Shopeame ' . $payload['event'] . ' event success: ' . $message, 1);
        } else {
            PrestaShopLogger::addLog('Shopeame ' . $payload['event'] . ' event bad response: ' . $error, 2, $response->getStatusCode());
        }
    }
}


