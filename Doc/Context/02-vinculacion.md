# 2. Vinculación con shopea.me

## 2.1 Clave del webservice

`ConfigController::doGenerateApiKey()` activa el webservice (`PS_WEBSERVICE`), borra la clave anterior del módulo y
crea una `WebserviceKey` nueva de 32 caracteres hexadecimales en mayúscula (`random_bytes()`), activa y solo con los
permisos de `ConfigController::WEBSERVICE_PERMISSIONS`: lectura de pedidos, clientes, direcciones, países, provincias,
monedas, estados de pedido, idiomas, impuestos y configuración; lectura, creación y actualización de productos y
variantes; lectura y actualización del stock; y lectura y creación de categorías y atributos. Nunca `DELETE` ni
`PATCH`. La lista sale de lo que usa ShopeameServer (ver [decisión 0003](Decisiones/0003-permisos-minimos-de-la-clave-del-webservice.md));
si el servidor empieza a usar un recurso o un método nuevo de PrestaShop, se añade a la lista con una versión nueva y
su `upgrade/upgrade-X.Y.Z.php`, que llama a `ConfigController::setWebserviceKeyPermissions()` para corregir las claves
ya creadas. La descripción de la clave es
`Shopeame (shopea.me) - do not delete or share this key`. Si alguien la borra desde **Parámetros Avanzados** →
**Webservice**, la pantalla lo detecta al abrirse y deja de mostrarla.

## 2.2 Opción 1: copiar los datos en shopea.me

1. El usuario genera la clave y copia la URL de la tienda y la clave del webservice.
2. En shopea, la pantalla «conectar tienda» (`ConnectShop`, plataforma PrestaShop, «El módulo me muestra la URL de la
   tienda y la clave del webservice») comprueba la tienda, crea el enlace y llama a `ShopLinkNotifier::notify()`.
3. El notificador envía a `POST https://<tienda>/index.php?fc=module&module=shopeame&controller=link` el cuerpo
   `{"event":"link_shop","shop_uuid":"…","webhook_token":"…"}` con las cabeceras `X-Shopeame-Timestamp` (segundos
   Unix) y `X-Shopeame-Signature` = `hash_hmac('sha256', timestamp + '.' + cuerpo, clave del webservice)`.
4. `ShopeameLinkModuleFrontController` exige una firma hexadecimal de 64 caracteres, la clave guardada y una marca de
   tiempo con menos de 300 segundos de diferencia, compara con `hash_equals()`, valida el formato de las credenciales
   y las guarda con `SHOPEAME_SHOP_REGISTERED = true`.

El envío automático es opcional: el servidor solo lo intenta si la URL es HTTPS y espera 4 segundos sin seguir
redirecciones. Si falla, el usuario termina la vinculación con la opción 2. La firma usa la clave del webservice porque
es el único secreto que ya comparten la tienda y shopea.me antes de vincular.

## 2.3 Opción 2: vincular con el UUID y el token del ERP

El formulario guarda el UUID y el token del ERP (validados: 13 y 32 caracteres alfanuméricos), genera la clave del
webservice si falta y envía a `POST /WebHooks` el evento `register_prestashop` con `erp`, `token` y `new_shop`
(`url`, `api_key`). El servidor:

- crea la tienda y responde `shop-registered`;
- si ya existe una tienda activa del mismo ERP con la misma URL, devuelve sus credenciales sin cambiar nada
  (`shop-already-exists`);
- si existe pero estaba desvinculada, la reactiva (`shop-reactivated`).

En los tres casos el módulo sustituye el UUID y el token del ERP por los de la tienda (`shop_uuid` y `webhook_token`)
y muestra el mensaje correspondiente. Si el servidor responde con error, se muestra
`The store could not be linked: <error>`.

## 2.4 Estado de la pantalla

Al abrir la pantalla, si hay UUID y token se envía `check_shop` y el resultado (`exists`) decide si se muestra
**Linked store** o las dos opciones. Mientras no está vinculada, lo guardado son los datos del ERP y el `check_shop`
responde `unauthorized`, que no se muestra porque es lo esperado.

Hasta el 03/11/2026 ShopeameServer respondía `exists: true` aunque la tienda estuviera desvinculada (`softDelete`,
estado `deleted`), y la pantalla la seguía mostrando como vinculada. La corrección (rama
`fix-check-shop-tienda-desvinculada`, PR #74 de shopeame-server) responde 404 con `exists: false` y
`error: shop-unlinked`, que el módulo ya interpreta como tienda no vinculada; también rechaza con ese error los avisos
de pedidos de una tienda desvinculada, que quedan en el registro con nivel 2.

## 2.5 Comprobar, desvincular y borrar datos

- **Check connection**: evento `check_shop`.
- **Unlink store**: evento `unlink_shop`, que en el servidor hace `softDelete()` de la tienda. El módulo borra sus
  credenciales locales aunque el servidor falle y avisa si no hubo confirmación.
- **Delete module data**: solo borra las credenciales locales, sin llamar al servidor ni tocar la clave del webservice.

La clave del webservice no se borra al desvincular (se reutiliza al volver a vincular); solo la desinstalación la
elimina, junto con toda la configuración y la pestaña.
