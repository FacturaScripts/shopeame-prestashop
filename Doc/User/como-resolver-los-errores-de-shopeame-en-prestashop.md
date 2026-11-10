Los problemas más habituales al vincular la tienda o al sincronizar los pedidos tienen solución desde la propia página
**Shopeame** o desde los registros de PrestaShop. Un fallo al avisar a shopea.me nunca bloquea el pedido en la tienda:
el cliente termina su compra igual.

## 🔑 No se genera la clave del webservice

Avisos: **No se pudo generar la clave de API.**, **No se pudieron establecer los permisos de la clave de API.** o **No
se pudo guardar la clave de API.**

- Comprueba que entras con un empleado que tenga permisos de administrador y vuelve a pulsar **Generar clave de API**.
- Revisa en **Parámetros Avanzados** → **Webservice** que el webservice está activado y que no hay una clave antigua de
  Shopeame que no se pueda borrar.

## 🔗 La tienda no se vincula

- **El UUID del ERP no es válido.** o **El token del ERP no es válido.**: copia de nuevo el UUID y el token desde la
  pantalla de conectar PrestaShop de shopea.me, sin espacios al principio ni al final.
- **No se pudo vincular la tienda: …**: shopea.me no acepta los datos del ERP; el texto que sigue indica el motivo.
- **No se pudo conectar con shopea.me. Error: …**: el servidor de la tienda no puede conectarse con shopea.me. Comprueba
  que tiene salida a internet y repite.
- Con la **Opción 1**, shopea.me conecta la tienda pero la página **Shopeame** sigue sin vincular: shopea.me no ha
  podido enviar los datos a PrestaShop. La tienda debe usar HTTPS y no bloquear las peticiones de shopea.me. Termina la
  vinculación con la **Opción 2**, que recupera la tienda ya creada.

## 🔍 Falla la comprobación de la conexión

Aviso: **No se pudo confirmar la conexión con shopea.me.**

- Si la tienda se borró o se desvinculó en shopea.me, pulsa **Borrar datos del módulo** y vuelve a vincularla.
- Si el servidor no tiene conexión, espera y repite la comprobación.

## 📦 Los pedidos no llegan al ERP

1. Pulsa **Comprobar conexión** en la página **Shopeame**.
2. Revisa en shopea.me que la tienda está activa y que tiene activada la copia de pedidos al ERP.
3. Ve a **Parámetros Avanzados** → **Registros/Logs** y busca los mensajes que empiezan por `Shopeame:`. Cada aviso de
   pedido deja uno:
   - **Gravedad 1** (`event sent for order …`): el aviso llegó a shopea.me.
   - **Gravedad 2** (`shopea.me rejected the … event`): shopea.me lo rechazó. Si indica `unauthorized` o `HTTP 401`, no
     reconoce la tienda: pulsa **Borrar datos del módulo** y vuelve a vincularla.
   - **Gravedad 3** (`could not be sent`): no hubo conexión con shopea.me (sin internet, cortafuegos o tiempo de espera
     agotado). Los pedidos se recuperan en la siguiente sincronización.

## 🧾 No se ven las facturas de un pedido

Si la lista de facturas de un pedido muestra un aviso en lugar de las facturas, la tabla de
[Cómo descargar las facturas del ERP de un pedido](como-descargar-las-facturas-de-un-pedido-en-prestashop.md#-si-no-aparecen-las-facturas)
explica qué hacer con cada uno. Los fallos quedan también en **Registros/Logs** (`Shopeame: the order_invoices event
failed…` o `the invoice_pdf event failed…`).

⚠️ Si regeneras la clave del webservice o la borras en **Parámetros Avanzados** → **Webservice**, shopea.me deja de
poder leer los pedidos hasta que vuelvas a vincular la tienda.

## Ver también

- [Cómo comprobar, desvincular o borrar los datos de Shopeame en PrestaShop](como-comprobar-desvincular-o-borrar-los-datos-de-shopeame-en-prestashop.md)
- [Cómo vincular PrestaShop con el UUID y el token del ERP](como-vincular-prestashop-con-el-uuid-y-el-token-del-erp.md)
- [Cómo funciona el módulo Shopeame para PrestaShop](como-funciona-shopeame-para-prestashop.md)
- [Cómo descargar las facturas del ERP de un pedido en PrestaShop](como-descargar-las-facturas-de-un-pedido-en-prestashop.md)
