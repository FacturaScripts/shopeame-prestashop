El módulo tiene un controlador frontal para que shopea.me (el plugin **ShopeameServer**) le entregue las credenciales
de la tienda cuando se conecta desde la pantalla de shopea.me con la URL y la clave del webservice. Así la tienda queda
vinculada en PrestaShop sin que el usuario copie nada de vuelta.

## 1. La petición

```text
POST https://<tienda>/index.php?fc=module&module=shopeame&controller=link
Content-Type: application/json; charset=utf-8
X-Shopeame-Timestamp: <segundos Unix>
X-Shopeame-Signature: <HMAC-SHA256 en hexadecimal>
```

Cuerpo:

```json
{
    "event": "link_shop",
    "shop_uuid": "6a1b2c3d4e5f6",
    "webhook_token": "AbCdEfGhIjKlMnOpQrStUvWxYz012345"
}
```

- `shop_uuid`: 13 caracteres alfanuméricos (hexadecimales en minúscula, de `uniqid()`).
- `webhook_token`: 32 caracteres alfanuméricos.

Lo atiende la clase **`ShopeameLinkModuleFrontController`** (`controllers/front/link.php`), declarada en
`$this->controllers` del módulo. Cualquier método que no sea `POST` recibe **405**.

## 2. La firma

La firma es el HMAC-SHA256 de la marca de tiempo, un punto y el cuerpo exacto de la petición, con la **clave del
webservice** que generó el módulo (la que el usuario pegó en shopea.me). Es el único secreto que ya comparten la
tienda y shopea.me antes de vincular.

```php
$timestamp = (string) time();
$body = json_encode(['event' => 'link_shop', 'shop_uuid' => $uuid, 'webhook_token' => $token]);
$signature = hash_hmac('sha256', $timestamp . '.' . $body, $webserviceKey);
```

El controlador rechaza la petición con **401** (`unauthorized`) si:

- falta la cabecera de la marca de tiempo o no son solo dígitos;
- la firma no tiene 64 caracteres hexadecimales o no coincide (se compara con `hash_equals()`);
- la marca de tiempo se aleja más de 300 segundos de la hora del servidor (`TIMESTAMP_TOLERANCE`);
- el módulo todavía no tiene clave del webservice guardada.

## 3. Las respuestas

| Código | Cuerpo | Cuándo |
|---|---|---|
| 200 | `{"success": true}` | Credenciales guardadas: la tienda queda vinculada |
| 400 | `{"error": "invalid-json"}` | El cuerpo no es JSON |
| 401 | `{"error": "unauthorized"}` | Firma o marca de tiempo no válidas |
| 405 | `{"error": "method-not-allowed"}` | La petición no es `POST` |
| 422 | `{"error": "invalid-payload"}` | `event` no es `link_shop` o el UUID o el token no tienen el formato esperado |

Las credenciales se guardan en `SHOPEAME_UUID` y `SHOPEAME_WEBHOOK_TOKEN`, y `SHOPEAME_SHOP_REGISTERED` pasa a `true`;
si la tienda ya estaba vinculada, se sustituyen.

## 4. Quién lo llama

En ShopeameServer, la clase **`ShopLinkNotifier`** (`Lib/Shopeame/ShopLinkNotifier.php`) lo llama al guardar una tienda
PrestaShop desde la pantalla de conectar tienda. Solo lo intenta si la URL de la tienda usa HTTPS, espera como máximo
4 segundos y no sigue redirecciones. Si falla, la tienda sigue creada en shopea.me y el usuario puede terminar la
vinculación desde PrestaShop con el UUID y el token del ERP.

⚠️ Cualquier cambio en el cuerpo, las cabeceras o la firma se hace a la vez en este módulo y en `ShopLinkNotifier`.

## Ver también

- [Eventos que envía Shopeame para PrestaShop](eventos-que-envia-shopeame-para-prestashop.md)
- [Cómo desarrollar y publicar Shopeame para PrestaShop](como-desarrollar-y-publicar-shopeame-para-prestashop.md)
