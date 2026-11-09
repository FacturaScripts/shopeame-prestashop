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

namespace PrestaShop\Module\Shopeame\Controller;

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\Module\Shopeame\Form\ConfigFormManager;
use PrestaShop\Module\Shopeame\Update\UpdateChecker;
use PrestaShop\PrestaShop\Core\Form\FormHandlerInterface;
use PrestaShop\PrestaShop\Core\Module\ModuleManager;
use PrestaShopBundle\Controller\Admin\PrestaShopAdminController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controlador de la página de configuración del módulo Shopeame: vinculación con shopea.me,
 * clave del webservice, comprobación de la conexión y desvinculación.
 *
 * Extiende PrestaShopAdminController (PrestaShop 9): se registra como servicio en
 * config/services.yml y recibe los servicios como argumentos de cada acción, sin usar Context.
 */
class ConfigController extends PrestaShopAdminController
{
    /** Dominio de las traducciones del módulo. */
    private const DOMAIN = 'Modules.Shopeame.Admin';

    /** Descripción con la que se crea la clave del webservice. */
    private const WEBSERVICE_KEY_DESCRIPTION = 'Shopeame (shopea.me) - do not delete or share this key';

    /**
     * Permisos mínimos de la clave del webservice: solo los recursos y métodos que usa
     * ShopeameServer (Lib/PrestaShop/Client.php). Nunca necesita DELETE ni PATCH, y la raíz
     * /api (GET y HEAD, para la lista de recursos y la versión) no requiere permiso.
     *
     * Si ShopeameServer empieza a usar un recurso o un método nuevo, hay que añadirlo aquí y
     * sacar una versión con su upgrade que corrija las claves ya creadas.
     *
     * https://devdocs.prestashop-project.org/9/webservice/getting-started/
     *
     * @var array<string, string[]>
     */
    public const WEBSERVICE_PERMISSIONS = [
        // catálogo: el servidor crea y actualiza productos, variantes y stock y crea categorías y atributos
        'products' => ['GET', 'POST', 'PUT'],
        'combinations' => ['GET', 'POST', 'PUT'],
        'stock_availables' => ['GET', 'PUT'],
        'categories' => ['GET', 'POST'],
        'product_options' => ['GET', 'POST'],
        'product_option_values' => ['GET', 'POST'],
        'taxes' => ['GET'],
        'tax_rule_groups' => ['GET'],
        // pedidos y los datos necesarios para copiarlos al ERP
        'orders' => ['GET'],
        'order_states' => ['GET'],
        'order_payments' => ['GET'],
        'customers' => ['GET'],
        'addresses' => ['GET'],
        'countries' => ['GET'],
        'states' => ['GET'],
        'currencies' => ['GET'],
        'languages' => ['GET'],
        // idioma por defecto (PS_LANG_DEFAULT) y zona horaria (PS_TIMEZONE) de la tienda
        'configurations' => ['GET'],
    ];

    /**
     * URL base del servidor de shopea.me (sin ruta).
     *
     * Se puede sobrescribir definiendo la constante SHOPEAME_SERVER_URL en
     * config/defines_custom.inc.php para apuntar a una instalación de desarrollo.
     *
     * @return string
     */
    public static function serverUrl(): string
    {
        return defined('SHOPEAME_SERVER_URL') ? rtrim((string) SHOPEAME_SERVER_URL, '/') : 'https://shopea.me';
    }

    /**
     * URL del endpoint WebHooks de ShopeameServer, que recibe todos los eventos del módulo.
     *
     * @return string
     */
    public static function webHooksUrl(): string
    {
        return self::serverUrl() . '/WebHooks';
    }

    /**
     * Borra la clave del webservice creada por el módulo, si existe, y su valor en la configuración.
     *
     * @return bool false si la clave existe y no se pudo borrar
     */
    public static function deleteWebserviceKey(): bool
    {
        $key = ConfigFormManager::getString(ConfigFormManager::API_KEY);
        if ($key !== '' && \WebserviceKey::keyExists($key)) {
            $webserviceKey = new \WebserviceKey((int) \WebserviceKey::getIdFromKey($key));
            if (false === $webserviceKey->delete()) {
                return false;
            }
        }

        \Configuration::updateValue(ConfigFormManager::API_KEY, '');

        return true;
    }

    /**
     * Sustituye los permisos de la clave del webservice indicada por los de WEBSERVICE_PERMISSIONS.
     *
     * @param int $idWebserviceKey
     *
     * @return bool
     */
    public static function setWebserviceKeyPermissions(int $idWebserviceKey): bool
    {
        // setPermissionForAccount() espera [recurso => [método => 1]] y borra antes los permisos anteriores
        $permissions = [];
        foreach (self::WEBSERVICE_PERMISSIONS as $resourceName => $methods) {
            $permissions[$resourceName] = array_fill_keys($methods, 1);
        }

        return (bool) \WebserviceKey::setPermissionForAccount($idWebserviceKey, $permissions);
    }

    /**
     * Pantalla de configuración. Procesa el formulario de la opción 2 (vincular con el UUID y el
     * token del ERP) y muestra el estado de la vinculación.
     *
     * @param Request $request
     * @param FormHandlerInterface $formDataHandler Manejador del formulario de config/services.yml.
     *
     * @return Response
     */
    public function index(
        Request $request,
        #[Autowire(service: 'prestashop.module.shopeame.form.config_form_handler')]
        FormHandlerInterface $formDataHandler,
    ): Response {
        // si alguien borró la clave del webservice desde PrestaShop, dejamos de mostrarla
        $apiKey = ConfigFormManager::getString(ConfigFormManager::API_KEY);
        if ($apiKey !== '' && false === \WebserviceKey::keyExists($apiKey)) {
            \Configuration::updateValue(ConfigFormManager::API_KEY, '');
        }

        $form = $formDataHandler->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $errors = $formDataHandler->save($form->getData());
            if (empty($errors)) {
                // un solo botón «Vincular tienda»: tras guardar el UUID y el token del ERP, generamos
                // la clave del webservice si aún no existe y registramos la tienda en shopea.me
                if (ConfigFormManager::getString(ConfigFormManager::API_KEY) === '') {
                    $this->doGenerateApiKey();
                }
                $this->doRegisterShop();

                return $this->redirectToRoute('shopeame_config_form');
            }

            $this->addFlashErrors($errors);
        }

        $uuid = ConfigFormManager::getString(ConfigFormManager::UUID_KEY);
        $webhookToken = ConfigFormManager::getString(ConfigFormManager::WEBHOOK_TOKEN_KEY);
        $apiKey = ConfigFormManager::getString(ConfigFormManager::API_KEY);
        $registeredShop = (bool) \Configuration::get(ConfigFormManager::SHOP_REGISTERED_KEY);

        // comprobamos la conexión solo cuando hay credenciales completas: así, borrar los datos
        // locales nunca provoca una petición posterior a ShopeameServer
        if ($uuid !== '' && $webhookToken !== '') {
            $result = $this->checkRegisteredShop($uuid, $webhookToken);
            if (null !== $result) {
                \Configuration::updateValue(ConfigFormManager::SHOP_REGISTERED_KEY, $result['exists']);
                $registeredShop = $result['exists'];
            }
        }

        return $this->render('@Modules/shopeame/views/templates/admin/form.html.twig', [
            // título de la cabecera del back-office, el mismo que el de la app de Shopify
            'layoutTitle' => $this->trans('Connect with shopea.me', [], self::DOMAIN),
            'configForm' => $form->createView(),
            'apiKey' => $apiKey,
            'shopUrl' => $this->getShopContext()->getBaseURL(),
            'shopLinkUrl' => self::serverUrl() . '/shop-link/' . rawurlencode($uuid),
            'enableGenerateApiKeyButton' => $apiKey === '',
            'registeredShop' => $registeredShop,
            'moduleVersion' => $this->moduleVersion(),
            // reutiliza la última consulta guardada: solo pregunta a GitHub si han pasado 12 horas
            // (UpdateChecker::CACHE_TTL), con un tope de 8 segundos
            'update' => UpdateChecker::getAvailableUpdate($this->moduleVersion()),
        ]);
    }

    /**
     * Actualiza el módulo a la última versión de GitHub (botón «Actualizar ahora» del aviso).
     *
     * Hace lo mismo que el botón «Actualizar» del gestor de módulos: ModuleManager::upgrade()
     * comprueba que el empleado puede actualizar módulos, descarga el zip, lo descomprime sobre la
     * carpeta del módulo y ejecuta los upgrade/upgrade-X.Y.Z.php.
     *
     * @param Request $request petición con el token CSRF del formulario
     * @param ModuleManager $moduleManager gestor de módulos de PrestaShop
     *
     * @return RedirectResponse
     */
    public function updateModule(
        Request $request,
        #[Autowire(service: ModuleManager::class)]
        ModuleManager $moduleManager,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('shopeame_update', (string) $request->request->get('_token'))) {
            $this->addFlash('error', $this->trans('The request is not valid. Please try again.', [], self::DOMAIN));

            return $this->redirectToRoute('shopeame_config_form');
        }

        $update = UpdateChecker::getAvailableUpdate($this->moduleVersion());
        if (null === $update) {
            $this->addFlash('success', $this->trans('Shopeame is up to date.', [], self::DOMAIN));

            return $this->redirectToRoute('shopeame_config_form');
        }

        try {
            $upgraded = $moduleManager->upgrade('shopeame', $update['download_url']);
            $error = $upgraded ? '' : (string) $moduleManager->getError('shopeame');
        } catch (\Throwable $e) {
            $upgraded = false;
            $error = $e->getMessage();
        }

        if ($upgraded) {
            $this->addFlash('success', $this->trans('Shopeame was updated to version %version%.', ['%version%' => $update['version']], self::DOMAIN));
        } else {
            \PrestaShopLogger::addLog('Shopeame: the module could not be updated to version ' . $update['version'] . ': ' . $error, 3);
            $this->addFlash('error', $this->trans('Shopeame could not be updated: %error%', ['%error%' => $error], self::DOMAIN));
        }

        return $this->redirectToRoute('shopeame_config_form');
    }

    /**
     * Consulta GitHub en ese momento, sin esperar a que caduque la caché (enlace «Buscar
     * actualizaciones»), y vuelve a la configuración, que muestra el aviso si hay versión nueva.
     *
     * @return RedirectResponse
     */
    public function checkUpdates(): RedirectResponse
    {
        if (!UpdateChecker::refresh()) {
            $this->addFlash('warning', $this->trans('Could not connect to GitHub to check for updates.', [], self::DOMAIN));
        } elseif (null === UpdateChecker::getAvailableUpdate($this->moduleVersion())) {
            $this->addFlash('success', $this->trans('Shopeame is up to date.', [], self::DOMAIN));
        }

        return $this->redirectToRoute('shopeame_config_form');
    }

    /**
     * Genera (o regenera) la clave del webservice y vuelve a la configuración.
     *
     * @return RedirectResponse
     */
    public function generateApiKey(): RedirectResponse
    {
        if ($this->doGenerateApiKey()) {
            $this->addFlash('success', $this->trans('API key generated successfully.', [], self::DOMAIN));
        }

        return $this->redirectToRoute('shopeame_config_form');
    }

    /**
     * Vincula la tienda en shopea.me con los datos guardados y vuelve a la configuración.
     *
     * @return RedirectResponse
     */
    public function registerShop(): RedirectResponse
    {
        $this->doRegisterShop();

        return $this->redirectToRoute('shopeame_config_form');
    }

    /**
     * Desvincula la tienda de shopea.me (botón «Desvincular tienda»): pide al servidor que la
     * desactive y borra las credenciales locales.
     *
     * @param Request $request petición con el token CSRF del formulario
     *
     * @return RedirectResponse
     */
    public function unlinkShop(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('shopeame_shop_actions', (string) $request->request->get('_token'))) {
            $this->addFlash('error', $this->trans('The request is not valid. Please try again.', [], self::DOMAIN));

            return $this->redirectToRoute('shopeame_config_form');
        }

        $response = $this->postEvent([
            'event' => 'unlink_shop',
            'shop' => ConfigFormManager::getString(ConfigFormManager::UUID_KEY),
            'token' => ConfigFormManager::getString(ConfigFormManager::WEBHOOK_TOKEN_KEY),
        ]);

        // borramos la configuración local aunque el servidor falle, para no dejar la tienda en un
        // estado intermedio; el comercio siempre puede volver a vincularla
        $this->clearLinkData();

        if (null !== $response && $response['status'] >= 200 && $response['status'] < 300) {
            $this->addFlash('success', $this->trans('Store unlinked from shopea.me.', [], self::DOMAIN));
        } else {
            $this->addFlash('warning', $this->trans('The local credentials were deleted, but shopea.me did not confirm the unlinking.', [], self::DOMAIN));
        }

        return $this->redirectToRoute('shopeame_config_form');
    }

    /**
     * Borra solo las credenciales de vinculación guardadas por el módulo (botón «Borrar datos de
     * la app»). No hace ninguna petición a ShopeameServer y conserva la clave del webservice.
     *
     * @param Request $request petición con el token CSRF del formulario
     *
     * @return RedirectResponse
     */
    public function clearLocalShop(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('shopeame_shop_actions', (string) $request->request->get('_token'))) {
            $this->addFlash('error', $this->trans('The request is not valid. Please try again.', [], self::DOMAIN));

            return $this->redirectToRoute('shopeame_config_form');
        }

        $this->clearLinkData();
        $this->addFlash('success', $this->trans('The connection data saved in this module was deleted without changing shopea.me.', [], self::DOMAIN));

        return $this->redirectToRoute('shopeame_config_form');
    }

    /**
     * Comprueba la conexión con shopea.me (botón «Comprobar conexión») y muestra el resultado.
     *
     * @return RedirectResponse
     */
    public function checkShop(): RedirectResponse
    {
        $result = $this->checkRegisteredShop(
            ConfigFormManager::getString(ConfigFormManager::UUID_KEY),
            ConfigFormManager::getString(ConfigFormManager::WEBHOOK_TOKEN_KEY)
        );

        if (null !== $result && $result['exists']) {
            $this->addFlash('success', $this->trans('The connection with shopea.me is working correctly.', [], self::DOMAIN));
        } else {
            $this->addFlash('warning', $this->trans('The connection with shopea.me could not be confirmed.', [], self::DOMAIN));
        }

        return $this->redirectToRoute('shopeame_config_form');
    }

    /**
     * Genera (o regenera) la clave del webservice con los permisos de WEBSERVICE_PERMISSIONS,
     * activa el webservice y guarda la clave. No redirige; muestra los errores que se produzcan.
     *
     * https://devdocs.prestashop-project.org/9/webservice/tutorials/creating-access/
     *
     * @return bool true si la clave quedó creada y guardada
     */
    private function doGenerateApiKey(): bool
    {
        \Configuration::updateValue('PS_WEBSERVICE', 1);

        if (false === self::deleteWebserviceKey()) {
            $this->addFlash('error', $this->trans('The previous API key could not be deleted.', [], self::DOMAIN));

            return false;
        }

        $webserviceKey = new \WebserviceKey();
        $webserviceKey->description = self::WEBSERVICE_KEY_DESCRIPTION;
        // 32 caracteres hexadecimales de un generador criptográficamente seguro
        $webserviceKey->key = strtoupper(bin2hex(random_bytes(16)));
        $webserviceKey->active = true;
        if (false === $webserviceKey->save()) {
            $this->addFlash('error', $this->trans('The API key could not be generated.', [], self::DOMAIN));

            return false;
        }

        // shopea.me lee y actualiza el catálogo, el stock, los precios y los pedidos de la tienda
        if (false === self::setWebserviceKeyPermissions((int) $webserviceKey->id)) {
            $this->addFlash('error', $this->trans('The API key permissions could not be set.', [], self::DOMAIN));

            return false;
        }

        if (false === \Configuration::updateValue(ConfigFormManager::API_KEY, $webserviceKey->key)) {
            $this->addFlash('error', $this->trans('The API key could not be saved.', [], self::DOMAIN));

            return false;
        }

        return true;
    }

    /**
     * Registra (vincula) la tienda en shopea.me con el UUID y el token del ERP y la clave del
     * webservice guardados. Si todo va bien, guarda el UUID y el token de la tienda que devuelve
     * el servidor. No redirige; muestra el resultado.
     *
     * @return void
     */
    private function doRegisterShop(): void
    {
        $response = $this->postEvent([
            'event' => 'register_prestashop',
            'erp' => ConfigFormManager::getString(ConfigFormManager::UUID_KEY),
            'token' => ConfigFormManager::getString(ConfigFormManager::WEBHOOK_TOKEN_KEY),
            'new_shop' => [
                'url' => $this->getShopContext()->getBaseURL(),
                'api_key' => ConfigFormManager::getString(ConfigFormManager::API_KEY),
            ],
        ]);
        if (null === $response) {
            return;
        }

        $content = $response['content'];
        if ($response['status'] < 200 || $response['status'] >= 300) {
            $error = isset($content['error']) && is_string($content['error']) ? $content['error'] : 'HTTP ' . $response['status'];
            $this->addFlash('error', $this->trans('The store could not be linked: %error%', ['%error%' => $error], self::DOMAIN));

            return;
        }

        if (($content['success'] ?? false) !== true
            || !is_string($content['shop_uuid'] ?? null)
            || !is_string($content['webhook_token'] ?? null)) {
            $this->addFlash('error', $this->trans('shopea.me returned an unexpected response.', [], self::DOMAIN));

            return;
        }

        \Configuration::updateValue(ConfigFormManager::UUID_KEY, $content['shop_uuid']);
        \Configuration::updateValue(ConfigFormManager::WEBHOOK_TOKEN_KEY, $content['webhook_token']);
        \Configuration::updateValue(ConfigFormManager::SHOP_REGISTERED_KEY, true);
        $this->addFlash('success', $this->linkSuccessMessage((string) ($content['message'] ?? '')));
    }

    /**
     * Pregunta a shopea.me si la tienda está vinculada con el UUID y el token indicados.
     *
     * @param string $uuid
     * @param string $webhookToken
     *
     * @return array|null ['exists' => bool, 'error' => string], o null si no se pudo conectar
     */
    private function checkRegisteredShop(string $uuid, string $webhookToken): ?array
    {
        $response = $this->postEvent([
            'event' => 'check_shop',
            'token' => $webhookToken,
            'shop' => $uuid,
        ]);
        if (null === $response) {
            return null;
        }

        return [
            'exists' => (bool) ($response['content']['exists'] ?? false),
            'error' => is_string($response['content']['error'] ?? null) ? $response['content']['error'] : '',
        ];
    }

    /**
     * Envía un evento al endpoint WebHooks de shopea.me.
     *
     * Si no se puede conectar, muestra el error y devuelve null. Nunca lanza excepciones: los
     * fallos de red y las respuestas que no son JSON (por ejemplo, la página de error de un proxy)
     * no deben romper la pantalla.
     *
     * @param array $payload
     *
     * @return array|null ['status' => int, 'content' => array], o null si falló la conexión
     */
    private function postEvent(array $payload): ?array
    {
        try {
            $response = HttpClient::create()->request('POST', self::webHooksUrl(), [
                'json' => $payload,
                'timeout' => 10,
            ]);
            $status = $response->getStatusCode();
            $content = json_decode($response->getContent(false), true);
        } catch (\Throwable $e) {
            $this->addFlash('error', $this->trans(
                'Could not connect to shopea.me. Error: %error%',
                ['%error%' => $e->getMessage()],
                self::DOMAIN
            ));

            return null;
        }

        return [
            'status' => (int) $status,
            'content' => is_array($content) ? $content : [],
        ];
    }

    /**
     * Borra las credenciales de vinculación locales (UUID, token y estado), sin tocar la clave
     * del webservice.
     *
     * @return void
     */
    private function clearLinkData(): void
    {
        \Configuration::updateValue(ConfigFormManager::UUID_KEY, '');
        \Configuration::updateValue(ConfigFormManager::WEBHOOK_TOKEN_KEY, '');
        \Configuration::updateValue(ConfigFormManager::SHOP_REGISTERED_KEY, false);
    }

    /**
     * Traduce el código de resultado de la vinculación devuelto por ShopeameServer.
     *
     * @param string $message código de resultado del servidor
     *
     * @return string
     */
    private function linkSuccessMessage(string $message): string
    {
        if ($message === 'shop-already-exists') {
            return $this->trans('The store was already linked. Its data was recovered without creating a new store.', [], self::DOMAIN);
        }
        if ($message === 'shop-reactivated') {
            return $this->trans('The store was reactivated and linked with shopea.me successfully.', [], self::DOMAIN);
        }

        return $this->trans('Store linked with shopea.me successfully.', [], self::DOMAIN);
    }

    /**
     * Versión instalada del módulo ($this->version de shopeame.php).
     *
     * @return string
     */
    private function moduleVersion(): string
    {
        $module = \Module::getInstanceByName('shopeame');

        return $module instanceof \Module ? (string) $module->version : '0.0.0';
    }
}
