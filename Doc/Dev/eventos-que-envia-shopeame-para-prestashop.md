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

## 3. Configuración y constantes

| Nombre | Tipo | Contenido |
|---|---|---|
| `SHOPEAME_UUID` | Configuración | UUID del ERP durante la opción 2; después, UUID de la tienda en shopea.me |
| `SHOPEAME_WEBHOOK_TOKEN` | Configuración | Token del ERP durante la opción 2; después, token de la tienda |
| `SHOPEAME_API_KEY` | Configuración | Clave del webservice generada por el módulo |
| `SHOPEAME_SHOP_REGISTERED` | Configuración | Último estado de vinculación conocido |
| `SHOPEAME_SERVER_URL` | Constante de `config/defines_custom.inc.php` | URL del servidor; por defecto `https://shopea.me` |

Las claves están en **`PrestaShop\Module\Shopeame\Form\ConfigFormManager`** (`CONFIG_KEYS`). Al desinstalar el módulo
se borran todas, junto con la clave del webservice y la entrada del menú.

## Ver también

- [Endpoint de vinculación de Shopeame para PrestaShop](endpoint-de-vinculacion-de-shopeame-para-prestashop.md)
- [Cómo desarrollar y publicar Shopeame para PrestaShop](como-desarrollar-y-publicar-shopeame-para-prestashop.md)
