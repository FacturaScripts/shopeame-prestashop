# 1. Arquitectura

## 1.1 Archivos

| Archivo | Qué hace |
|---|---|
| `shopeame.php` | Clase `Shopeame`: datos del módulo, carga de `vendor/autoload.php`, instalación (hooks y pestaña), desinstalación, hooks de pedidos y del menú, y envío de los avisos de pedido |
| `src/Controller/ConfigController.php` | Pantalla de configuración (Symfony, extiende `PrestaShopAdminController`) y sus acciones: generar la clave del webservice, vincular, comprobar, desvincular, borrar datos, actualizar el módulo y buscar actualizaciones. También `serverUrl()`, `webHooksUrl()` y `deleteWebserviceKey()`, que usa la desinstalación |
| `src/Update/UpdateChecker.php` | Lee y valida el `info.json` de la última release de GitHub, lo guarda 12 horas y dice si hay una versión nueva compatible (ver [4. Distribución y actualizaciones](04-distribucion-y-actualizaciones.md)) |
| `src/Form/ConfigForm.php`, `ConfigFormProvider.php`, `ConfigFormManager.php` | Formulario de la opción 2 (UUID y token del ERP). `ConfigFormManager` define además las claves de configuración y `getString()` |
| `controllers/front/link.php` | Controlador frontal `ShopeameLinkModuleFrontController`, que recibe la vinculación firmada de ShopeameServer |
| `config/routes.yml`, `config/services.yml` | Rutas de la pantalla (`/shopeame/...`), servicios del formulario (con el traductor inyectado en `ConfigFormManager`) y registro de `ConfigController` como servicio (`autowire` y `autoconfigure`), que PrestaShop 9 no hace solo con los controladores que extienden `PrestaShopAdminController` |
| `views/templates/admin/form.html.twig` | Plantilla de la pantalla |
| `views/css/admin.css`, `views/js/admin.js` | Estilos de la pantalla, botones «Copiar» y confirmaciones |
| `views/css/menu.css`, `views/img/menu-icon.svg` | Logo de Shopeame en el menú lateral y en la miga de pan (ver [decisión 0002](Decisiones/0002-logo-en-el-menu-lateral.md)) |
| `translations/es-ES/ModulesShopeameAdmin.es-ES.xlf` | Traducción al español |
| `upgrade/upgrade-*.php` | Actualizaciones entre versiones; la 1.0.0 no tiene ninguna (ver [decisión 0004](Decisiones/0004-primera-version-publica-1-0-0.md)) |
| `logo.png` | Icono de 32 × 32 del gestor de módulos |
| `.htaccess` | Impide listar la carpeta del módulo y abrir sus PHP directamente (lo exige el Validator); `deploy.sh` no lo copia a la tienda local porque excluye los archivos ocultos, pero `build.sh` sí lo mete en el zip |

La pantalla imita la estructura y los textos de la app de Shopify de Shopeame, que es la base visual común de los
conectores de Shopify, WooCommerce y PrestaShop.

## 1.2 Configuración guardada

| Clave | Contenido |
|---|---|
| `SHOPEAME_UUID` | UUID del ERP mientras se vincula con la opción 2; después, el de la tienda en shopea.me (13 caracteres hexadecimales, de `uniqid()`) |
| `SHOPEAME_WEBHOOK_TOKEN` | Token del ERP mientras se vincula; después, el de la tienda (32 caracteres alfanuméricos) |
| `SHOPEAME_API_KEY` | Clave del webservice de PrestaShop creada por el módulo |
| `SHOPEAME_SHOP_REGISTERED` | Último estado de vinculación conocido; se refresca con `check_shop` cada vez que se abre la pantalla |
| `SHOPEAME_UPDATE_INFO` | Último `info.json` válido de las releases de GitHub, en JSON (global) |
| `SHOPEAME_UPDATE_CHECKED` | Hora (timestamp) de la última consulta del `info.json`, haya ido bien o no (global) |

## 1.3 Hooks, rutas y menú

| Hook | Función |
|---|---|
| `actionValidateOrder` | Aviso `new_order` |
| `actionOrderStatusPostUpdate`, `actionOrderEdited` | Aviso `update_order` |
| `actionAdminControllerSetMedia` | Carga `views/css/menu.css` en todo el back-office (logo del menú) |
| `actionListModules` | Anuncia al gestor de módulos la versión nueva de GitHub, con su zip y su changelog |

- La pestaña `Shopeame` cuelga de **Configurar** (`CONFIGURE`) y tiene `route_name = shopeame_config_form`: sin él,
  PrestaShop enlazaría la primera ruta con `_legacy_link` «Shopeame», que antes era la de generar la clave.
- `getContent()` (botón **Configurar** del gestor de módulos) redirige a esa misma ruta.
- Las rutas de `routes.yml` llevan el `_token` del back-office en la URL, que protege de CSRF también las de tipo GET
  (generar clave, vincular, comprobar). Desvincular y borrar datos son POST con su propio token CSRF
  (`shopeame_shop_actions`). **Actualizar ahora** (`shopeame_update_module`) es POST con el token
  `shopeame_update`; **Buscar actualizaciones** (`shopeame_check_updates`) es GET.

## 1.4 Autoload

El kernel de Symfony del back-office carga `vendor/autoload.php` de cada módulo, pero los hooks de pedidos también se
ejecutan en el front-office (checkout) y en la consola, y el controlador `link` es frontal. Por eso `shopeame.php` hace
`require_once __DIR__ . '/vendor/autoload.php'`, como indica la documentación de Composer de PrestaShop. Sin esa línea
el aviso de pedidos falla en esos contextos con «Class ConfigFormManager not found».

## 1.5 Relación con ShopeameServer

El servidor es el plugin ShopeameServer de FacturaScripts (`../facturascripts/Plugins/ShopeameServer`):

- `Controller/WebHooks.php` recibe en `POST /WebHooks` los eventos del módulo: `register_prestashop` (autenticado con
  el UUID y el token del ERP), `check_shop`, `unlink_shop`, `new_order` y `update_order` (autenticados con el UUID y el
  token de la tienda).
- `Controller/ConnectShop.php` es la pantalla «conectar tienda» de shopea (`/conectar-tienda/<uuid del ERP>`), donde el
  usuario pega la URL y la clave del webservice.
- `Lib/Shopeame/ShopLinkNotifier.php` entrega las credenciales de la tienda recién creada al controlador
  `index.php?fc=module&module=shopeame&controller=link`.
- ShopeameServer lee y actualiza el catálogo, el stock, los precios y los pedidos por el webservice de PrestaShop con
  la clave que genera el módulo, que solo tiene los permisos que necesita (ver
  [decisión 0003](Decisiones/0003-permisos-minimos-de-la-clave-del-webservice.md)).

Cualquier cambio de eventos, campos o firma se hace en los dos proyectos en el mismo trabajo. Si ShopeameServer
empieza a usar un recurso o un método nuevo del webservice de PrestaShop, también hay que añadirlo a
`ConfigController::WEBSERVICE_PERMISSIONS` con una versión y su upgrade.
