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

if (!defined('_PS_VERSION_')) {
    exit;
}

// el kernel de Symfony del back-office carga solo el autoload de los módulos, pero los hooks de
// pedidos también se ejecutan en el front-office (checkout) y en tareas de consola, donde no
// existe ese kernel: https://devdocs.prestashop-project.org/9/modules/concepts/composer/
require_once __DIR__ . '/vendor/autoload.php';

use PrestaShop\Module\Shopeame\Controller\ConfigController;
use PrestaShop\Module\Shopeame\Form\ConfigFormManager;
use PrestaShop\Module\Shopeame\Update\UpdateChecker;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Clase principal del módulo Shopeame.
 *
 * Registra la pestaña del menú del back-office, los hooks de pedidos que avisan a shopea.me de
 * cada pedido nuevo o modificado, el hook que pinta el logo de Shopeame en el menú lateral y el
 * que anuncia al gestor de módulos las versiones nuevas publicadas en GitHub.
 */
class Shopeame extends Module
{
    /** Nombre de la clase de la pestaña del módulo en el menú del back-office. */
    public const TAB_CLASS_NAME = 'Shopeame';

    /** Dominio de las traducciones de la parte administrativa del módulo. */
    public const TRANSLATION_DOMAIN = 'Modules.Shopeame.Admin';

    /** Hooks que el módulo registra al instalarse (y en las actualizaciones). */
    public const HOOKS = [
        'actionValidateOrder',
        'actionOrderStatusPostUpdate',
        'actionOrderEdited',
        'actionAdminControllerSetMedia',
        'actionListModules',
    ];

    /**
     * Define los datos del módulo que PrestaShop muestra en el gestor de módulos.
     */
    public function __construct()
    {
        $this->name = 'shopeame';
        // sección del gestor de módulos: https://devdocs.prestashop-project.org/9/modules/concepts/module-class/#tab
        $this->tab = 'administration';
        // formato de tres números: cada versión que cambie hooks, pestañas, configuración o permisos de la
        // clave del webservice lleva su upgrade/upgrade-X.Y.Z.php
        $this->version = '1.0.0';
        $this->author = 'Shopeame';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '9.0.0',
            'max' => '9.99.99',
        ];
        $this->bootstrap = true;
        // controlador frontal que recibe las credenciales firmadas que envía ShopeameServer
        $this->controllers = ['link'];

        parent::__construct();

        $this->displayName = $this->trans('Shopeame', [], self::TRANSLATION_DOMAIN);
        $this->description = $this->trans(
            'Connect your PrestaShop store with shopea.me to sync its orders with your ERP.',
            [],
            self::TRANSLATION_DOMAIN
        );
        $this->confirmUninstall = $this->trans(
            'Are you sure you want to uninstall Shopeame? The link with shopea.me and the webservice key created by the module will be deleted.',
            [],
            self::TRANSLATION_DOMAIN
        );
    }

    /**
     * Indica a PrestaShop que el módulo usa el sistema de traducciones nuevo (archivos XLIFF de
     * la carpeta translations/ y dominio Modules.Shopeame.Admin).
     *
     * @return bool
     */
    public function isUsingNewTranslationSystem(): bool
    {
        return true;
    }

    /**
     * Instala el módulo: registra los hooks y crea la pestaña del menú.
     *
     * El webservice no se activa aquí: se activa al generar la clave de API desde la configuración.
     *
     * @return bool
     */
    public function install(): bool
    {
        return parent::install()
            && $this->registerHook(self::HOOKS)
            && $this->installTab();
    }

    /**
     * Desinstala el módulo y borra todo lo que ha creado: la pestaña, la configuración y la clave
     * del webservice. No desactiva el webservice, porque otras integraciones pueden usarlo.
     *
     * @return bool
     */
    public function uninstall(): bool
    {
        if (false === parent::uninstall()) {
            return false;
        }

        ConfigController::deleteWebserviceKey();
        foreach (ConfigFormManager::CONFIG_KEYS as $key) {
            Configuration::deleteByName($key);
        }

        return $this->uninstallTab();
    }

    /**
     * Crea (o actualiza) la pestaña del módulo en el menú del back-office, bajo «Configurar».
     *
     * @return bool
     */
    public function installTab(): bool
    {
        $idTab = (int) Tab::getIdFromClassName(self::TAB_CLASS_NAME);
        $tab = $idTab ? new Tab($idTab) : new Tab();
        $tab->class_name = self::TAB_CLASS_NAME;
        // sin route_name PrestaShop toma la primera ruta con _legacy_link «Shopeame»; la fijamos
        // para que el menú abra siempre la configuración y nunca la generación de la clave de API
        $tab->route_name = 'shopeame_config_form';
        $tab->module = $this->name;
        $tab->id_parent = (int) Tab::getIdFromClassName('CONFIGURE');
        $tab->active = true;
        // icono de reserva: views/css/menu.css lo sustituye por el logo de Shopeame
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
     *
     * @return bool
     */
    private function uninstallTab(): bool
    {
        $idTab = (int) Tab::getIdFromClassName(self::TAB_CLASS_NAME);
        if ($idTab) {
            $tab = new Tab($idTab);

            return (bool) $tab->delete();
        }

        return true;
    }

    /**
     * Carga en el back-office la hoja de estilos que pinta el logo de Shopeame como icono de la
     * entrada del menú lateral. El menú está en todas las páginas, así que se carga en todas; es
     * una regla CSS mínima, sin JavaScript.
     *
     * https://devdocs.prestashop-project.org/9/modules/concepts/hooks/list-of-hooks/actionadmincontrollersetmedia/
     *
     * @param array $params
     *
     * @return void
     */
    public function hookActionAdminControllerSetMedia($params): void
    {
        $this->context->controller->addCSS($this->_path . 'views/css/menu.css');
    }

    /**
     * Anuncia al gestor de módulos la última versión del módulo publicada en GitHub.
     *
     * El módulo no está en PrestaShop Addons. El gestor de módulos reúne con este hook los módulos
     * de otras fuentes (es el mismo que usa ps_distributionapiclient con los módulos oficiales): si
     * version_available es mayor que la instalada, muestra «Actualizar», descarga el zip de
     * download_url, lo descomprime y ejecuta los upgrade/upgrade-X.Y.Z.php. changeLog es lo que
     * pinta la tarjeta de la pestaña «Actualizaciones» ([versión => [líneas]]). La consulta a GitHub
     * se guarda 12 horas (ver UpdateChecker).
     *
     * @param array $params
     *
     * @return array<array<string, mixed>>
     */
    public function hookActionListModules($params): array
    {
        $update = UpdateChecker::getAvailableUpdate($this->version);
        if (null === $update) {
            return [];
        }

        $module = [
            'name' => $this->name,
            'version_available' => $update['version'],
            'download_url' => $update['download_url'],
        ];
        if (!empty($update['changelog'])) {
            $module['changeLog'] = [$update['version'] => $update['changelog']];
        }

        return [$module];
    }

    /**
     * Avisa a shopea.me cada vez que se crea un pedido.
     *
     * https://devdocs.prestashop-project.org/9/modules/concepts/hooks/list-of-hooks/actionvalidateorder/
     *
     * @param array $params
     *
     * @return void
     */
    public function hookActionValidateOrder($params): void
    {
        $order = $params['order'] ?? null;
        $hasValidParams = (
            Validate::isLoadedObject($params['cart'] ?? null)
            && Validate::isLoadedObject($order)
            && Validate::isLoadedObject($params['customer'] ?? null)
            && Validate::isLoadedObject($params['currency'] ?? null)
            && Validate::isLoadedObject($params['orderStatus'] ?? null)
        );

        if (!$hasValidParams) {
            PrestaShopLogger::addLog('Shopeame: invalid actionValidateOrder parameters, the new_order event was not sent.', 3);

            return;
        }

        $this->notifyOrderEvent('new_order', (int) $order->id);
    }

    /**
     * Avisa a shopea.me cada vez que cambia el estado de un pedido.
     *
     * @param array $params
     *
     * @return void
     */
    public function hookActionOrderStatusPostUpdate($params): void
    {
        $idOrder = (int) ($params['id_order'] ?? 0);
        if ($idOrder <= 0) {
            PrestaShopLogger::addLog('Shopeame: invalid actionOrderStatusPostUpdate parameters, the update_order event was not sent.', 3);

            return;
        }

        $this->notifyOrderEvent('update_order', $idOrder);
    }

    /**
     * Avisa a shopea.me cuando se edita un pedido a mano en el back-office.
     *
     * @param array $params
     *
     * @return void
     */
    public function hookActionOrderEdited($params): void
    {
        $order = $params['order'] ?? null;
        if (!Validate::isLoadedObject($order)) {
            PrestaShopLogger::addLog('Shopeame: invalid actionOrderEdited parameters, the update_order event was not sent.', 3);

            return;
        }

        $this->notifyOrderEvent('update_order', (int) $order->id);
    }

    /**
     * Abre la página de configuración del módulo (botón «Configurar» del gestor de módulos).
     *
     * @return string
     */
    public function getContent(): string
    {
        Tools::redirectAdmin($this->get('router')->generate('shopeame_config_form'));

        return '';
    }

    /**
     * Notifica a shopea.me que un pedido se ha creado o modificado, para que adelante la
     * sincronización de pedidos de esta tienda. Solo enviamos el id: ShopeameServer descarga
     * el pedido por el webservice.
     *
     * No miramos SHOPEAME_SHOP_REGISTERED: ese valor solo se refresca cuando un administrador abre
     * la configuración del módulo, así que si se quedara a false (por ejemplo, porque shopea.me
     * estaba caído en ese momento) dejaríamos de notificar pedidos indefinidamente y en silencio.
     * Con tener UUID y token ya podemos intentarlo; si no son válidos, el servidor responde 401 y
     * queda registrado en los logs.
     *
     * @param string $event new_order o update_order
     * @param int $idOrder
     *
     * @return void
     */
    private function notifyOrderEvent(string $event, int $idOrder): void
    {
        $shop = ConfigFormManager::getString(ConfigFormManager::UUID_KEY);
        $token = ConfigFormManager::getString(ConfigFormManager::WEBHOOK_TOKEN_KEY);
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
     * Envía a shopea.me el aviso de un pedido.
     *
     * Nunca lanza excepciones: se ejecuta dentro de la validación del pedido (checkout) y del
     * guardado en el back-office, así que un fallo del servidor no puede romper la compra.
     *
     * @param array $payload
     *
     * @return void
     */
    private function sendEventRequest(array $payload): void
    {
        try {
            // HttpClient es asíncrono: request() solo encola la petición. Los fallos de red, DNS,
            // timeout y TLS afloran al consumir la respuesta, así que todo el consumo va en el try.
            $response = HttpClient::create()->request('POST', ConfigController::webHooksUrl(), [
                'json' => $payload,
                // timeout es de inactividad; max_duration es el tope total, para que el checkout
                // no pueda quedarse colgado esperando a shopea.me
                'timeout' => 5,
                'max_duration' => 8,
            ]);

            $statusCode = (int) $response->getStatusCode();

            // decodificamos a mano en lugar de usar toArray(), que lanza una excepción cuando la
            // respuesta no es JSON (por ejemplo, el HTML de un error 502 del proxy)
            $content = json_decode($response->getContent(false), true);
            $content = is_array($content) ? $content : [];
        } catch (Throwable $e) {
            // capturamos Throwable a propósito: además de los errores de transporte puede fallar
            // la decodificación de la respuesta, y nada de eso debe afectar al pedido
            PrestaShopLogger::addLog('Shopeame: the ' . $payload['event'] . ' event could not be sent: ' . $e->getMessage(), 3);

            return;
        }

        // niveles de log: 1 = info, 2 = warning, 3 = error
        if ($statusCode >= 200 && $statusCode < 300) {
            PrestaShopLogger::addLog('Shopeame: ' . $payload['event'] . ' event sent for order ' . $payload['order_id'] . '.', 1);

            return;
        }

        $error = isset($content['error']) && is_string($content['error']) ? $content['error'] : 'HTTP ' . $statusCode;
        PrestaShopLogger::addLog('Shopeame: shopea.me rejected the ' . $payload['event'] . ' event: ' . $error, 2);
    }
}
