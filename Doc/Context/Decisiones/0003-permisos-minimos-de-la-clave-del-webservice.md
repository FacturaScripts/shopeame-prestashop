# 0003. Permisos mínimos de la clave del webservice

- **Fecha**: 03/11/2026
- **Aprobada por**: el usuario, tras una revisión de seguridad que marcó la clave como privilegio excesivo (MEDIUM).

## Contexto

Hasta la versión 1.2.0, `ConfigController::doGenerateApiKey()` daba a la clave `GET`, `POST`, `PUT`, `PATCH`, `DELETE`
y `HEAD` sobre todos los recursos de `WebserviceRequest::getResources()` (64 en PrestaShop 9.1.4, más los que añadan
otros módulos). El webservice no pide contraseña: quien tuviera la clave podía leer empleados y toda la
configuración, o borrar pedidos, productos y clientes. La [decisión 0001](0001-adaptacion-a-prestashop-addons.md) lo
dejó pendiente porque no había una lista cerrada de lo que usa ShopeameServer.

## Decisión

Se revisó el cliente de PrestaShop de ShopeameServer (`Lib/PrestaShop/Client.php`) y todo lo que lo llama: los
importadores y exportadores de `Lib/PrestaShop`, los crons `MonitorShops` y `Sync*`, `ConnectShop`, `WebHooks` y
`ShopApiExplorer`. La clave queda con estos permisos (`ConfigController::WEBSERVICE_PERMISSIONS`):

| Recursos | Métodos | Para qué |
|---|---|---|
| `products`, `combinations` | GET, POST, PUT | Leer, crear y actualizar productos, variantes y precios |
| `stock_availables` | GET, PUT | Leer y actualizar el stock |
| `categories`, `product_options`, `product_option_values` | GET, POST | Leer y crear categorías y atributos |
| `taxes`, `tax_rule_groups` | GET | Impuestos del producto y de los pedidos |
| `orders`, `order_states`, `order_payments`, `customers`, `addresses`, `countries`, `states`, `currencies`, `languages` | GET | Copiar los pedidos al ERP |
| `configurations` | GET | Idioma por defecto (`PS_LANG_DEFAULT`) y zona horaria (`PS_TIMEZONE`) |

- Nada usa `DELETE`, `PATCH` ni `HEAD` sobre un recurso. La raíz `/api` (lista de recursos con GET y versión con HEAD)
  no necesita permiso: `WebserviceRequest::checkResource()` y `checkHTTPMethod()` la dejan pasar y solo listan los
  recursos permitidos.
- `Client::setAttribute()` (PUT de `product_options`), `customers()` y `totalCustomers()` existen en el cliente, pero
  nada los llama; no tienen permiso.
- `ConfigController::setWebserviceKeyPermissions()` aplica la lista al generar la clave (`doGenerateApiKey()`). Se
  pensó también un `upgrade-1.2.1.php` que corrigiera la clave de las tiendas ya vinculadas sin cambiarla, pero el
  módulo pasó a la 1.0.0 sin upgrades porque nadie lo tenía instalado (ver
  [decisión 0004](0004-primera-version-publica-1-0-0.md)). Un upgrade futuro que cambie la lista debe llamar a este
  mismo método.
- Si ShopeameServer empieza a usar un recurso o un método nuevo de PrestaShop, hay que añadirlo a la lista y sacar una
  versión con su upgrade, en el mismo trabajo (ver [2.1](../02-vinculacion.md)).
- **Ampliación (05/11/2026)**: se añade `GET` de `order_payments` (pagos de cada pedido) para la sincronización de
  pedidos. Queda en 18 recursos y 26 permisos. Se hace dentro de la 1.0.0 y sin upgrade porque todavía nadie tiene el
  módulo instalado; quien generó la clave antes tiene que pulsar **Regenerar clave de API**.

## Alternativas descartadas

- **Quitar también `configurations`**: GET sobre ese recurso deja leer toda la configuración, incluida
  `PS_MAIL_PASSWD` y las claves que otros módulos guardan ahí. Evitarlo exige cambiar los dos lados (por ejemplo, que
  el módulo envíe la zona horaria y el idioma por defecto en `register_prestashop` y `check_shop`). Queda pendiente en
  [4.3](../04-distribucion-y-actualizaciones.md#43-requisitos-técnicos-que-se-mantienen).
- **Dejar todos los permisos**: era el riesgo que marcó la revisión y lo más probable es que el revisor de Addons lo
  cuestione.

## Resultado

Probado en PrestaShop 9.1.4 (ps.localhost) con el modo debug activado:

- `setWebserviceKeyPermissions()` sobre una clave con los 384 permisos anteriores (probado con el upgrade, que después
  se quitó) la deja con los 25 de la lista, con la misma clave.
- **Regenerar clave de API** crea la clave con los 25 permisos.
- Con esa clave, el cliente de ShopeameServer responde 200 en todas sus lecturas (pedidos, clientes, direcciones,
  catálogo, variantes, atributos, stock, impuestos, configuración, versión y raíz) y en un PUT real de stock con la
  misma cantidad. POST y PUT de los recursos permitidos llegan a la validación (400 con un cuerpo vacío), y `DELETE`,
  `PATCH`, `HEAD` y `PUT` de `orders` o `categories` dan 405; `employees` y `carts`, 401.
- Prueba de extremo a extremo contra shopea.localhost con la versión 1.0.0 recién instalada: vincular con el UUID y
  el token del ERP (`shop-reactivated` y `shop-already-exists`), comprobar la conexión, aviso de pedido
  (`update_order`), `MonitorShops` y `SyncShopOrders` solo para esa tienda (formas de pago, impuestos, totales y
  pedidos, sin errores), desvincular, vincular desde «conectar tienda» con la URL y la clave (la prueba de la tienda
  de `ConnectShop` y la recepción firmada en el controlador `link`), borrar datos del módulo y desinstalar (sin clave,
  permisos, configuración ni pestaña). No se ejecutaron las sincronizaciones de productos, stock, precios, categorías
  ni atributos, que estaban desactivadas en la tienda de pruebas y escribirían en el ERP; sus llamadas se probaron una
  a una con el cliente.
- La raíz `/api` en JSON da un error fatal en `WebserviceOutputJSON::overrideContent()` de PrestaShop 9.1.4 con
  cualquier clave, también con todos los permisos; no tiene que ver con este cambio y solo afecta a `ShopApiExplorer`.
