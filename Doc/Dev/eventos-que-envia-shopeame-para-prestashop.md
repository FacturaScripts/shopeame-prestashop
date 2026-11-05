El módulo se comunica con shopea.me enviando eventos JSON por `POST` a `<servidor>/WebHooks`, donde los recibe el
controlador **`WebHooks`** del plugin ShopeameServer (`Controller/WebHooks.php`). El servidor es `https://shopea.me`
salvo que se defina la constante `SHOPEAME_SERVER_URL` (ver `ConfigController::serverUrl()`). Las peticiones salen con
el `HttpClient` de Symfony y siempre verifican el certificado TLS.

## 1. Eventos de la vinculación

Los envía **`PrestaShop\Module\Shopeame\Controller\ConfigController`** con `postEvent()` (10 segundos de espera), que
captura cualquier error de conexión y lo muestra en la pantalla.

| Evento | Cuándo | Campos | Autenticación |
|---|---|---|---|
| `register_prestashop` | Al pulsar **Vincular tienda** | `erp`, `token` y `new_shop` (`url`, `api_key`) | UUID y token del ERP |
| `check_shop` | Al abrir la página **Shopeame** y al pulsar **Comprobar conexión** | `shop`, `token` | UUID y token de la tienda |
| `unlink_shop` | Al pulsar **Desvincular tienda** | `shop`, `token` | UUID y token de la tienda |

Ejemplo de `register_prestashop`:

```json
{
    "event": "register_prestashop",
    "erp": "<UUID del ERP>",
    "token": "<token del ERP>",
    "new_shop": {
        "url": "https://mitienda.com/",
        "api_key": "<clave del webservice>"
    }
}
```

La respuesta trae `success`, `shop_uuid`, `webhook_token` y `message`: `shop-registered` (tienda nueva),
`shop-already-exists` (ya existía y está activa; no se cambia nada) o `shop-reactivated` (existía desvinculada y se
reactiva). `check_shop` responde `{"exists": true}` y `unlink_shop` da de baja la tienda en el servidor.

## 2. Eventos de pedidos

Los hooks `actionValidateOrder` (`new_order`), `actionOrderStatusPostUpdate` y `actionOrderEdited` (`update_order`)
llaman a **`Shopeame::notifyOrderEvent()`**, que envía:

```json
{
    "event": "new_order",
    "shop": "<UUID de la tienda>",
    "token": "<token de la tienda>",
    "order_id": 123
}
```

Si no hay UUID y token guardados, no se envía nada. El servidor no descarga el pedido en la petición: adelanta la
sincronización de pedidos de la tienda y responde `orders-sync-scheduled`, `orders-sync-pending`,
`orders-sync-deferred` u `orders-sync-disabled`. Después descarga los pedidos por el webservice de PrestaShop.

El envío tiene 5 segundos de espera por inactividad y 8 en total, y nunca lanza excepciones, para no afectar al
checkout. El resultado queda en **Parámetros Avanzados** → **Registros/Logs** (`PrestaShopLogger`):

```text
Shopeame: new_order event sent for order 123.                  (gravedad 1)
Shopeame: shopea.me rejected the new_order event: unauthorized (gravedad 2)
Shopeame: the new_order event could not be sent: <motivo>      (gravedad 3)
```

## 3. Eventos de facturas

Las facturas del ERP de cada pedido no se guardan en PrestaShop: se piden a shopea.me cada vez. Las peticiones las hace
**`PrestaShop\Module\Shopeame\Invoice\InvoiceService`** (30 segundos de espera), que nunca lanza excepciones.

| Evento | Cuándo | Campos | Autenticación |
|---|---|---|---|
| `order_invoices` | Al pulsar **Facturas** en el listado de pedidos o al abrir la ficha (ruta `shopeame_order_invoices`), y en Mi cuenta al abrir el detalle de un pedido o pulsar **Facturas** en el historial (controlador frontal `invoices`, `action=list`) | `shop`, `token`, `order_id` | UUID y token de la tienda |
| `invoice_pdf` | Al pulsar el código de una factura (ruta `shopeame_order_invoice_pdf` en el back-office; `invoices`, `action=download` en Mi cuenta) | `shop`, `token`, `order_id`, `invoice_id` | UUID y token de la tienda |

`order_invoices` responde:

```json
{
    "success": true,
    "invoices": [
        {"id": 519, "code": "FAC0001A3", "date": "2026-11-05", "total": 166.98, "currency": "EUR", "paid": true, "is_refund": false, "refund_of": ""},
        {"id": 520, "code": "F0001R2", "date": "2026-11-05", "total": -24.2, "currency": "EUR", "paid": true, "is_refund": true, "refund_of": "FAC0001A3"}
    ]
}
```

El servidor (`OrderInvoiceFinder` de ShopeameServer) busca en el ERP las facturas con el `idshopeameorder` del pedido,
las que reciben líneas de sus pedidos y albaranes según `doctransformations` (así aparecen las facturas que agrupan
varios pedidos) y las rectificativas de todas ellas. Si el pedido no está en el ERP, la lista llega vacía.

`invoice_pdf` responde con el PDF (`application/pdf`, con el código de la factura en `Content-Disposition`). El servidor
solo lo entrega si la factura está entre las del pedido; si no, responde `404 invoice-not-found`. El módulo solo lo
reenvía si es `200` y empieza por `%PDF`, como adjunto y con el nombre que indica el servidor (`FAC0001A3.pdf`).

### Rutas y controladores del módulo

| Ruta o URL | Método | Quién | Respuesta |
|---|---|---|---|
| `shopeame_order_invoices` (`/modules/shopeame/orders/{orderId}/invoices`) | GET | Empleado con permiso de lectura sobre `AdminOrders`, con el `_token` del back-office | `{"success": true, "invoices": [{"code", "date", "total", "is_refund", "refund_of", "url"}]}` con la fecha y el total ya formateados |
| `shopeame_order_invoice_pdf` (`/modules/shopeame/orders/{orderId}/invoices/{invoiceId}/pdf`) | GET | Igual | El PDF, o vuelta a la ficha del pedido con el error |
| `index.php?fc=module&module=shopeame&controller=invoices` | POST `action=list`, `id_order`, `token` | Cliente con sesión iniciada, dueño del pedido, con el ajuste activado | El mismo JSON, o `403` con el aviso genérico |
| La misma URL con `action=download`, `id_order`, `invoice_id`, `token` | GET | Igual | El PDF, o vuelta al detalle del pedido con el aviso genérico |

El `token` de Mi cuenta es `Tools::getToken(false)` del cliente y se comprueba antes que nada; después, el ajuste
`SHOPEAME_CUSTOMER_INVOICES` y que `$order->id_customer` sea el cliente de la sesión.

### Errores

| HTTP | `error` | Mensaje para el comercio |
|---|---|---|
| 0 | (sin conexión) | No se ha podido conectar con shopea.me. Inténtalo de nuevo en unos minutos. |
| 400 | `unknown-event` | shopea.me todavía no permite descargar facturas. |
| 401 / 410 | `unauthorized` / `shop-unlinked` | La tienda no está vinculada con shopea.me. |
| 404 | `invoice-not-found` | La factura ya no pertenece a este pedido. Vuelve a cargar las facturas. |
| 422 | `missing-order-id` | No se han podido cargar las facturas del ERP. Inténtalo de nuevo en unos minutos. |
| 501 | `erp-not-supported` | Tu ERP no permite descargar facturas. Actualiza FacturaScripts y el plugin Shopeame. |
| 502 | `erp-error` | No se han podido cargar las facturas del ERP. Inténtalo de nuevo en unos minutos. |
| 503 | `erp-offline` | Tu ERP no está disponible ahora mismo. Inténtalo más tarde. |

Al cliente siempre se le muestra **Las facturas no están disponibles ahora mismo. Inténtalo más tarde.** Todos los
fallos quedan en **Parámetros Avanzados** → **Registros/Logs**, con el pedido como objeto:

```text
Shopeame: the order_invoices event failed for order 7 (HTTP 503): erp-offline  (gravedad 2)
Shopeame: the invoice_pdf event failed for order 7 (HTTP 0): <motivo>           (gravedad 3)
```

## 4. Configuración y constantes

| Nombre | Tipo | Contenido |
|---|---|---|
| `SHOPEAME_UUID` | Configuración | UUID del ERP durante la opción 2; después, UUID de la tienda en shopea.me |
| `SHOPEAME_WEBHOOK_TOKEN` | Configuración | Token del ERP durante la opción 2; después, token de la tienda |
| `SHOPEAME_API_KEY` | Configuración | Clave del webservice generada por el módulo, con los permisos de `ConfigController::WEBSERVICE_PERMISSIONS` |
| `SHOPEAME_SHOP_REGISTERED` | Configuración | Último estado de vinculación conocido |
| `SHOPEAME_CUSTOMER_INVOICES` | Configuración | `'1'` (por defecto) si los clientes pueden descargar las facturas del ERP de sus pedidos desde Mi cuenta, `'0'` si no |
| `SHOPEAME_SERVER_URL` | Constante de `config/defines_custom.inc.php` | URL del servidor; por defecto `https://shopea.me` |

Las claves están en **`PrestaShop\Module\Shopeame\Form\ConfigFormManager`** (`CONFIG_KEYS`). Al desinstalar el módulo
se borran todas, junto con la clave del webservice y la entrada del menú.

## 5. Permisos de la clave del webservice

La clave solo tiene los recursos y métodos que usa ShopeameServer (`Lib/PrestaShop/Client.php`):

| Recursos | Métodos |
|---|---|
| `products`, `combinations` | GET, POST, PUT |
| `stock_availables` | GET, PUT |
| `categories`, `product_options`, `product_option_values` | GET, POST |
| `taxes`, `tax_rule_groups`, `orders`, `order_states`, `order_payments`, `customers`, `addresses`, `countries`, `states`, `currencies`, `languages`, `configurations` | GET |

La raíz `/api` no necesita permiso. Con cualquier otro recurso PrestaShop responde 401, y con un método no permitido,
405. Si ShopeameServer empieza a usar un recurso o un método nuevo, hay que añadirlo a
`ConfigController::WEBSERVICE_PERMISSIONS` y sacar una versión con su `upgrade/upgrade-X.Y.Z.php`, que llame a
`ConfigController::setWebserviceKeyPermissions()` para corregir las claves de las tiendas ya vinculadas.

## Ver también

- [Endpoint de vinculación de Shopeame para PrestaShop](endpoint-de-vinculacion-de-shopeame-para-prestashop.md)
- [Cómo desarrollar y publicar Shopeame para PrestaShop](como-desarrollar-y-publicar-shopeame-para-prestashop.md)
