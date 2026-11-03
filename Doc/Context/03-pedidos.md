# 3. Pedidos

`actionValidateOrder` envía `new_order`, y `actionOrderStatusPostUpdate` y `actionOrderEdited` envían
`update_order`, con `Shopeame::notifyOrderEvent()`. Si no hay UUID y token guardados no se envía nada.

No se mira `SHOPEAME_SHOP_REGISTERED`: solo se refresca al abrir la configuración, y si se quedara en `false` (por
ejemplo, porque shopea.me estaba caído en ese momento) la tienda dejaría de avisar en silencio. Con UUID y token ya se
puede intentar; si no son válidos, el servidor responde 401 y queda en el registro.

El evento lleva solo `shop`, `token` y `order_id`. ShopeameServer no procesa el pedido dentro de la petición: adelanta
a ahora la siguiente sincronización de pedidos de la tienda (`nextordersync`) y su cron descarga los pedidos por el
webservice. Las respuestas posibles son `orders-sync-scheduled`, `orders-sync-pending`, `orders-sync-deferred` y
`orders-sync-disabled`.

El envío no puede romper el checkout ni el guardado del pedido: `sendEventRequest()` captura `Throwable`, usa
`timeout` 5 s y `max_duration` 8 s y decodifica la respuesta a mano (`toArray()` lanza una excepción si no es JSON).
Todo queda en **Parámetros Avanzados** → **Registros/Logs** (`PrestaShopLogger`), con mensajes que empiezan por
`Shopeame: `: nivel 1 si se envió, 2 si shopea.me lo rechazó (con el error o el código HTTP) y 3 si no hubo conexión.
