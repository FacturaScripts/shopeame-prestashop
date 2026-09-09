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
        $this->version = '1.1.0';
        // autor
        $this->author = 'Shopeame';
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
        $this->description = $this->trans('Plugin para conectar PrestaShop con Shopea.me', [], 'Modules.Shopeame.Admin');

        $this->confirmUninstall = $this->trans('¿Seguro que quieres desinstalar el módulo?', [], 'Modules.Shopeame.Admin');
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
            && $this->installTab()
        );
    }

    /**
     * Crea (o actualiza) la pestaña del módulo en el menú del back-office, bajo "Configurar".
     */
    public function installTab(): bool
    {
        $idTab = (int) Tab::getIdFromClassName('Shopeame');
        $tab = $idTab ? new Tab($idTab) : new Tab();
        $tab->class_name = 'Shopeame';
        $tab->module = $this->name;
        $tab->id_parent = (int) Tab::getIdFromClassName('CONFIGURE');
        $tab->active = 1;
        $tab->icon = 'settings';

        $names = [];
        foreach (Language::getLanguages(false) as $lang) {
            $names[$lang['id_lang']] = 'Shopeame';
        }
        $tab->name = $names;

        return $idTab ? (bool) $tab->save() : (bool) $tab->add();
    }

    /**
     * Elimina la pestaña del módulo del menú del back-office.
     */
    private function uninstallTab(): bool
    {
        $idTab = (int) Tab::getIdFromClassName('Shopeame');
        if ($idTab) {
            $tab = new Tab($idTab);
            return (bool) $tab->delete();
        }
        return true;
    }

    /**
     * Se ejecuta cada que se crea un pedido
     * https://devdocs.prestashop-project.org/9/modules/concepts/hooks/list-of-hooks/actionvalidateorder/
     *
     * @param array $params
     * @return void
     */
    public function hookActionValidateOrder($params)
    {
        // recoger todos los parámetros
        $order = $params['order'] ?? null;
        $hasValidParams = (
            Validate::isLoadedObject($params['cart'] ?? null)
            && Validate::isLoadedObject($order)
            && Validate::isLoadedObject($params['customer'] ?? null)
            && Validate::isLoadedObject($params['currency'] ?? null)
            && Validate::isLoadedObject($params['orderStatus'] ?? null)
        );

        // comprobar que esté todo ok
        if (!$hasValidParams) {
            PrestaShopLogger::addLog('Error en el evento new_order de Shopeame: parámetros del hook no válidos, se aborta.', 3);
            return;
        }

        $this->notifyOrderEvent('new_order', (int) $order->id);
    }

    /**
     * Se ejecuta al cambiar el ESTADO (status) de un pedido
     *
     * @param array $params
     * @return void
     */
    public function hookActionOrderStatusPostUpdate($params)
    {
        // recoger todos los parámetros
        $order = new Order((int) ($params['id_order'] ?? 0));

        // comprobar que esté todo ok
        if (!Validate::isLoadedObject($order)) {
            PrestaShopLogger::addLog('Error en el evento update_order de Shopeame: parámetros del hook no válidos, se aborta.', 3);
            return;
        }

        $this->notifyOrderEvent('update_order', (int) $order->id);
    }

    /**
     * Se ejecuta al editar el pedido manualmente en el BackOffice
     *
     * @param array $params
     * @return void
     */
    public function hookActionOrderEdited($params)
    {
        // recoger todos los parámetros
        $order = $params['order'] ?? null;

        // comprobar que esté todo ok
        if (!Validate::isLoadedObject($order)) {
            PrestaShopLogger::addLog('Error en el evento update_order de Shopeame: parámetros del hook no válidos, se aborta.', 3);
            return;
        }

        $this->notifyOrderEvent('update_order', (int) $order->id);
    }

    /**
     * Notifica a shopea.me que un pedido se ha creado o modificado, para que adelante la
     * sincronización de pedidos de esta tienda. Solo enviamos el id: ShopeameServer descarga
     * el pedido por la API y lo parsea.
     *
     * No miramos SHOEAME_SHOP_REGISTERED: ese flag solo se refresca cuando un administrador abre
     * la página de configuración del módulo, así que si se quedara a false (por ejemplo, porque
     * shopea.me estaba caído en ese momento) dejaríamos de notificar pedidos indefinidamente y en
     * silencio. Con tener UUID y token ya podemos intentarlo; si no son válidos, el servidor
     * responde 401 y queda registrado en los logs.
     *
     * @param string $event new_order o update_order
     * @param int $idOrder
     * @return void
     */
    private function notifyOrderEvent(string $event, int $idOrder): void
    {
        $shop = $this->getConfigString(ConfigFormManager::UIID_KEY);
        $token = $this->getConfigString(ConfigFormManager::WEBHOOK_TOKEN_KEY);
        if ($shop === '' || $token === '' || $idOrder <= 0) {
            return;
        }

        $this->sendEventRequest([
            'event' => $event,
            'shop' => $shop,
            'token' => $token,
            'order_id' => $idOrder,
        ]);
    }

    /**
     * Devuelve un valor de configuración como cadena.
     * Configuration::get() devuelve false (no null) cuando la clave no existe, así que
     * el operador ?? no sirve para normalizarla.
     *
     * @param string $key
     * @return string
     */
    private function getConfigString(string $key): string
    {
        $value = Configuration::get($key);
        return is_string($value) ? trim($value) : '';
    }

    /**
     * Se lanza cuando se desinstala el plugin
     */
    public function uninstall()
    {
        return $this->uninstallTab() && parent::uninstall();
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
     * Envía una request a shopea.me con el payload especificado.
     *
     * Nunca lanza excepciones: se ejecuta dentro de la validación del pedido (checkout) y del
     * guardado en el BackOffice, así que un fallo del servidor no puede romper la compra.
     *
     * @param array $payload
     * @return void
     */
    private function sendEventRequest(array $payload): void
    {
        // crear la request
        $client = HttpClient::create();

        try {
            // HttpClient es asíncrono: request() solo encola la petición. Los fallos de red, DNS,
            // timeout y TLS afloran al consumir la respuesta, así que todo el consumo tiene que ir
            // dentro de este try.
            $response = $client->request('POST', ConfigController::webHooksUrl(), array_merge([
                'json' => $payload,
                // timeout es de inactividad; max_duration es el tope total, para que el checkout
                // no pueda quedarse colgado esperando a shopea.me
                'timeout' => 5,
                'max_duration' => 8,
            ], ConfigController::httpTlsOptions()));

            $statusCode = (int) $response->getStatusCode();

            // decodificamos a mano en lugar de usar toArray(), que lanza una excepción cuando la
            // respuesta no es JSON (por ejemplo, el HTML de un error 502 del proxy)
            $content = json_decode($response->getContent(false), true);
            $content = is_array($content) ? $content : [];
        } catch (\Throwable $e) {
            // capturamos Throwable a propósito: además de los errores de transporte puede fallar
            // la decodificación de la respuesta, y nada de eso debe afectar al pedido
            // los log level son: 1 = info, 2 = warning, 3 = error
            PrestaShopLogger::addLog('Error al enviar el evento ' . $payload['event'] . ' de Shopeame: ' . $e->getMessage(), 3);
            return;
        }

        // loguear la respuesta
        if ($statusCode >= 200 && $statusCode < 300) {
            $message = key_exists('message', $content) ? $content['message'] : '';
            PrestaShopLogger::addLog('Evento ' . $payload['event'] . ' de Shopeame enviado correctamente: ' . $message, 1);
            return;
        }

        $error = key_exists('error', $content) ? $content['error'] : 'HTTP ' . $statusCode;
        PrestaShopLogger::addLog('Respuesta incorrecta al evento ' . $payload['event'] . ' de Shopeame: ' . $error, 2, $statusCode);
    }
}


