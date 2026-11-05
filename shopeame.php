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
use PrestaShop\Module\Shopeame\Invoice\InvoiceService;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\HtmlColumn;
use PrestaShop\PrestaShop\Core\Grid\Data\GridData;
use PrestaShop\PrestaShop\Core\Grid\Exception\ColumnNotFoundException;
use PrestaShop\PrestaShop\Core\Grid\Record\RecordCollection;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Clase principal del módulo Shopeame.
 *
 * Registra la pestaña del menú del back-office, los hooks de pedidos que avisan a shopea.me de
 * cada pedido nuevo o modificado, el hook que pinta el logo de Shopeame en el menú lateral y los
 * que muestran las facturas del ERP de cada pedido: en el listado y la ficha de los pedidos del
 * back-office y en el historial y el detalle de los pedidos de Mi cuenta (ver src/Invoice).
 */
class Shopeame extends Module
{
    /** Nombre de la clase de la pestaña del módulo en el menú del back-office. */
    public const TAB_CLASS_NAME = 'Shopeame';

    /** Dominio de las traducciones de la parte administrativa del módulo. */
    public const TRANSLATION_DOMAIN = 'Modules.Shopeame.Admin';

    /** Dominio de las traducciones del front-office (Mi cuenta). */
    public const SHOP_TRANSLATION_DOMAIN = 'Modules.Shopeame.Shop';

    /** Columna de las facturas del ERP en el listado de pedidos del back-office. */
    public const INVOICES_COLUMN = 'shopeame_invoices';

    /** Hooks que el módulo registra al instalarse (y en las actualizaciones). */
    public const HOOKS = [
        'actionValidateOrder',
        'actionOrderStatusPostUpdate',
        'actionOrderEdited',
        'actionAdminControllerSetMedia',
        // facturas del ERP de los pedidos
        'actionOrderGridDefinitionModifier',
        'actionOrderGridDataModifier',
        'displayAdminOrderSide',
        'displayOrderDetail',
        'actionFrontControllerSetMedia',
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
        // controladores frontales: link recibe las credenciales firmadas que envía ShopeameServer e
        // invoices entrega al cliente las facturas del ERP de sus pedidos en Mi cuenta
        $this->controllers = ['link', 'invoices'];

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
     * Instala el módulo: registra los hooks, crea la pestaña del menú y activa por defecto la
     * descarga de las facturas del ERP en Mi cuenta.
     *
     * El webservice no se activa aquí: se activa al generar la clave de API desde la configuración.
     *
     * @return bool
     */
    public function install(): bool
    {
        return parent::install()
            && $this->registerHook(self::HOOKS)
            && Configuration::updateValue(ConfigFormManager::CUSTOMER_INVOICES_KEY, '1')
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

        // listado y ficha de los pedidos (páginas Symfony con _legacy_controller AdminOrders)
        if ($this->context->controller->controller_name !== 'AdminOrders' || !InvoiceService::isLinked()) {
            return;
        }

        $this->context->controller->addCSS($this->_path . 'views/css/invoices.css');
        $this->context->controller->addJS($this->_path . 'views/js/invoices.js');
        Media::addJsDef(['shopeameInvoices' => [
            'i18n' => [
                'loading' => $this->trans('Loading invoices…', [], self::TRANSLATION_DOMAIN),
                'empty' => $this->trans('This order has no invoices in the ERP yet.', [], self::TRANSLATION_DOMAIN),
                'error' => $this->trans('The ERP invoices could not be loaded.', [], self::TRANSLATION_DOMAIN),
                'refund' => $this->trans('Corrective', [], self::TRANSLATION_DOMAIN),
                'refundOf' => $this->trans('Corrective invoice of %s', [], self::TRANSLATION_DOMAIN),
                'download' => $this->trans('Download PDF', [], self::TRANSLATION_DOMAIN),
                'reload' => $this->trans('Reload', [], self::TRANSLATION_DOMAIN),
            ],
        ]]);
    }

    /**
     * Añade la columna «Facturas» (las del ERP) al listado de pedidos del back-office, delante de
     * las acciones. No es clicable para que pulsar el botón no abra el pedido.
     *
     * https://devdocs.prestashop-project.org/9/development/components/grid/tutorials/modify-grid-in-module/
     *
     * @param array $params ['definition' => GridDefinitionInterface]
     *
     * @return void
     */
    public function hookActionOrderGridDefinitionModifier($params): void
    {
        if (!InvoiceService::isLinked() || !isset($params['definition'])) {
            return;
        }

        $column = (new HtmlColumn(self::INVOICES_COLUMN))
            ->setName($this->trans('Invoices', [], self::TRANSLATION_DOMAIN))
            ->setOptions([
                'field' => self::INVOICES_COLUMN,
                'clickable' => false,
            ]);

        $columns = $params['definition']->getColumns();
        try {
            $columns->addBefore('actions', $column);
        } catch (ColumnNotFoundException $e) {
            // otro módulo ha quitado la columna de acciones: la ponemos al final
            $columns->add($column);
        }
    }

    /**
     * Rellena la columna de facturas del listado de pedidos con el botón de cada pedido. El botón
     * no pide nada al pintarse: views/js/invoices.js pide las facturas al pulsarlo.
     *
     * @param array $params ['data' => GridData], por referencia
     *
     * @return void
     */
    public function hookActionOrderGridDataModifier($params): void
    {
        if (!InvoiceService::isLinked() || !($params['data'] ?? null) instanceof GridData) {
            return;
        }

        $label = htmlspecialchars($this->trans('Invoices', [], self::TRANSLATION_DOMAIN), ENT_QUOTES, 'UTF-8');
        $router = $this->get('router');
        $records = [];
        foreach ($params['data']->getRecords()->all() as $record) {
            $idOrder = (int) ($record['id_order'] ?? 0);
            $record[self::INVOICES_COLUMN] = $idOrder <= 0 ? '' : sprintf(
                '<div class="shopeame-invoices" data-order-id="%d" data-url="%s">'
                . '<button type="button" class="btn btn-sm btn-outline-secondary shopeame-invoices__toggle" aria-expanded="false">%s</button>'
                . '<div class="shopeame-invoices__panel" hidden aria-live="polite"></div></div>',
                $idOrder,
                htmlspecialchars($router->generate('shopeame_order_invoices', ['orderId' => $idOrder]), ENT_QUOTES, 'UTF-8'),
                $label
            );
            $records[] = $record;
        }

        $params['data'] = new GridData(
            new RecordCollection($records),
            $params['data']->getRecordsTotal(),
            $params['data']->getQuery()
        );
    }

    /**
     * Muestra el cuadro «Facturas del ERP» en la columna lateral de la ficha del pedido; carga las
     * facturas al abrir la ficha.
     *
     * https://devdocs.prestashop-project.org/9/modules/concepts/hooks/list-of-hooks/displayadminorderside/
     *
     * @param array $params ['id_order' => int]
     *
     * @return string
     */
    public function hookDisplayAdminOrderSide($params): string
    {
        $idOrder = (int) ($params['id_order'] ?? 0);
        if ($idOrder <= 0 || !InvoiceService::isLinked()) {
            return '';
        }

        $this->context->smarty->assign([
            'shopeame_order_id' => $idOrder,
            'shopeame_list_url' => $this->get('router')->generate('shopeame_order_invoices', ['orderId' => $idOrder]),
        ]);

        return $this->display(__FILE__, 'views/templates/hook/admin_order_invoices.tpl');
    }

    /**
     * Carga el script y los estilos de las facturas en el historial y en el detalle de los
     * pedidos de Mi cuenta, si el comercio permite que los clientes las descarguen.
     *
     * https://devdocs.prestashop-project.org/9/modules/concepts/hooks/list-of-hooks/actionfrontcontrollersetmedia/
     *
     * @param array $params
     *
     * @return void
     */
    public function hookActionFrontControllerSetMedia($params): void
    {
        $page = (string) ($this->context->controller->php_self ?? '');
        if (!in_array($page, ['history', 'order-detail'], true)
            || !$this->context->customer->isLogged()
            || !InvoiceService::customersEnabled()) {
            return;
        }

        $this->context->controller->registerStylesheet('module-shopeame-invoices', 'modules/' . $this->name . '/views/css/invoices.css');
        $this->context->controller->registerJavascript('module-shopeame-invoices', 'modules/' . $this->name . '/views/js/invoices.js', [
            'position' => 'bottom',
            'priority' => 200,
        ]);
        Media::addJsDef(['shopeameInvoices' => [
            // solo en el historial se añade el botón junto a «Detalles»; en el detalle está la sección
            'listUrl' => $this->context->link->getModuleLink($this->name, 'invoices'),
            'history' => $page === 'history',
            'token' => Tools::getToken(false),
            'i18n' => [
                'invoices' => $this->trans('Invoices', [], self::SHOP_TRANSLATION_DOMAIN),
                'orderInvoices' => $this->trans('Invoices of order %s', [], self::SHOP_TRANSLATION_DOMAIN),
                'loading' => $this->trans('Loading invoices…', [], self::SHOP_TRANSLATION_DOMAIN),
                'empty' => $this->trans('This order has no invoices yet.', [], self::SHOP_TRANSLATION_DOMAIN),
                'error' => $this->trans('The invoices could not be loaded.', [], self::SHOP_TRANSLATION_DOMAIN),
                'refund' => $this->trans('Corrective', [], self::SHOP_TRANSLATION_DOMAIN),
                'refundOf' => $this->trans('Corrective invoice of %s', [], self::SHOP_TRANSLATION_DOMAIN),
                'download' => $this->trans('Download PDF', [], self::SHOP_TRANSLATION_DOMAIN),
                'reload' => $this->trans('Reload', [], self::SHOP_TRANSLATION_DOMAIN),
            ],
        ]]);
    }

    /**
     * Muestra la sección «Facturas» en el detalle de un pedido de Mi cuenta; carga las facturas al
     * abrir la página. Solo para el cliente dueño del pedido.
     *
     * El controlador pasa el pedido en $params['order'], pero Hummingbird vuelve a ejecutar el hook
     * desde la plantilla sin parámetros; en ese caso el pedido sale de id_order, que es el que
     * OrderDetailController ya ha comprobado. En el seguimiento de invitados (guest-tracking) no
     * se muestra nada, porque no hay Mi cuenta.
     *
     * https://devdocs.prestashop-project.org/9/modules/concepts/hooks/list-of-hooks/displayorderdetail/
     *
     * @param array $params ['order' => Order] o vacío
     *
     * @return string
     */
    public function hookDisplayOrderDetail($params): string
    {
        if (($this->context->controller->php_self ?? '') !== 'order-detail'
            || !$this->context->customer->isLogged()
            || !InvoiceService::customersEnabled()) {
            return '';
        }

        $order = $params['order'] ?? new Order((int) Tools::getValue('id_order'));
        if (!Validate::isLoadedObject($order) || (int) $order->id_customer !== (int) $this->context->customer->id) {
            return '';
        }

        $this->context->smarty->assign('shopeame_order_id', (int) $order->id);

        return $this->display(__FILE__, 'views/templates/hook/order_detail_invoices.tpl');
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
