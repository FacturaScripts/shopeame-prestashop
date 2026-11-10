# 5. Facturas del ERP de los pedidos

El módulo permite descargar las facturas que el ERP ha generado para cada pedido: al comercio, desde el listado y la
ficha de los pedidos del back-office, y al cliente, desde Mi cuenta (si el ajuste `SHOPEAME_CUSTOMER_INVOICES` está
activado). Es la misma función que el plugin de WooCommerce, con los mismos eventos de ShopeameServer. Por qué se hace
así: [decisión 0008](Decisiones/0008-facturas-bajo-demanda-a-traves-de-shopeame.md). Por qué la ficha y la configuración dicen «Facturas
del ERP» y el listado, «Facturas»: [decisión 0009](Decisiones/0009-facturas-del-erp-frente-a-las-facturas-de-prestashop.md).

## 5.1 Recorrido

1. **PrestaShop**: el botón **Facturas** del listado, o el cuadro **Facturas del ERP** al abrir la ficha, pide
   la ruta `shopeame_order_invoices` (`InvoiceController::list()`), que envía `order_invoices` a shopea.me con el
   `order_id`. En Mi cuenta, la sección y el botón **Facturas** piden la lista por POST al controlador frontal
   `invoices` (`action=list`). Cada enlace de factura apunta a `shopeame_order_invoice_pdf` (back-office) o a
   `invoices?action=download` (Mi cuenta), que envían `invoice_pdf` con `order_id` e `invoice_id` y reenvían el PDF
   al navegador sin guardarlo.
2. **ShopeameServer** (`Controller/WebHooks.php`): autentica la tienda, comprueba que el ERP está en línea y que su API
   tiene `exportarFacturaCliente` y `doctransformations`, busca el pedido Shopeame del ERP por `idshop` + `idorder`
   (o por el `idorder` alternativo guardado en `ShopOrder`) y le pasa su `id` a `Lib/FacturaScripts/OrderInvoiceFinder`.
3. **ERP**: solo se usa su API genérica (`facturaclientes`, `pedidoclientes`, `albaranclientes`, `doctransformations` y
   `exportarFacturaCliente`). El plugin Shopeame del ERP no interviene.

`src/Invoice/InvoiceService.php` hace las dos peticiones (30 s de espera), normaliza las facturas, comprueba que la
respuesta del PDF empieza por `%PDF`, toma el nombre del archivo del `Content-Disposition` de shopea.me (el código de la
factura, `FAC0001A1.pdf`), traduce los errores y los anota en el registro. La usan el controlador del back-office, el
frontal y los hooks.

## 5.2 Qué facturas se encuentran

| Caso | Cómo se encuentra |
|---|---|
| Factura completa o parcial generada desde el pedido | `facturascli.idshopeameorder` |
| Factura que agrupa este pedido con otros | `doctransformations` desde los pedidos y albaranes con el `idshopeameorder` del pedido (pedido → albarán → factura) |
| Rectificativa | `facturascli.idfacturarect` de cualquiera de las anteriores |

Una consulta hace unas 7 u 8 llamadas a la API del ERP, por eso el listado no pide las facturas al pintarse, solo al
pulsar el botón de un pedido.

## 5.3 Hooks

| Hook | Qué hace |
|---|---|
| `actionOrderGridDefinitionModifier` | Añade la columna `shopeame_invoices` (`HtmlColumn`, `clickable => false`) delante de **Acciones** del grid `order` |
| `actionOrderGridDataModifier` | Rellena la columna con el botón de cada pedido; reconstruye el `GridData` con los registros nuevos |
| `displayAdminOrderSide` | Cuadro **Facturas del ERP** en la columna lateral de la ficha (`views/templates/hook/admin_order_invoices.tpl`) |
| `actionAdminControllerSetMedia` | En `AdminOrders` (listado y ficha) carga `views/js/invoices.js`, `views/css/invoices.css` y los textos con `Media::addJsDef` |
| `displayOrderDetail` | Sección **Facturas** en el detalle del pedido de Mi cuenta (`views/templates/hook/order_detail_invoices.tpl`) |
| `actionFrontControllerSetMedia` | En `history` y `order-detail`, con el cliente con sesión iniciada y el ajuste activado, carga el script, los estilos, la URL del controlador frontal, el token del cliente y los textos |

Todos devuelven nada si la tienda no tiene UUID y token (`InvoiceService::isLinked()`), igual que los avisos de pedidos.

- Hummingbird vuelve a ejecutar `displayOrderDetail` desde la plantilla **sin parámetros** (`{hook h='displayOrderDetail'}`),
  aunque `OrderDetailController` ya lo ejecuta con `['order' => $order]`. Si falta `order`, el hook lo saca de
  `id_order` y comprueba que es del cliente. En el seguimiento de invitados (`guest-tracking`), que también ejecuta el
  hook, no se muestra nada.
- El historial de pedidos no tiene un hook por pedido (ni en Classic ni en Hummingbird). El script añade el botón al
  lado de cada enlace `a[data-link-action="view-order-details"]` («Detalles» o «Datos» según el tema) copiando ese
  enlace (`cloneNode`), así que tiene exactamente sus clases y su estilo en cualquier tema que conserve ese atributo.
  Si el enlace lleva un icono de Material Icons, el botón usa `receipt`. El id del pedido sale de `id_order` de su
  URL. El enlace apunta al detalle del pedido (`#shopeame-invoices`), así que sin JavaScript sigue llevando a la
  sección de facturas.
- En el detalle del pedido no se añade el botón: `history` solo llega como `true` en el historial.

## 5.4 Seguridad

- **Back-office**: las rutas `shopeame_order_invoices` y `shopeame_order_invoice_pdf` llevan `_legacy_controller:
  AdminOrders` y las acciones el atributo `#[AdminSecurity("is_granted('read', request.get('_legacy_controller'))")]`,
  como `OrderController` del núcleo: solo entran los empleados con permiso de lectura sobre los pedidos. Como todas
  las rutas del back-office, llevan el `_token` del empleado en la URL, que PrestaShop comprueba antes del controlador.
  El router lo añade al generar la URL (`Router::generateTokenizedUrl()`, con `&` sin escapar); las URL van a
  `data-url` escapadas con `htmlspecialchars` y el navegador las devuelve sin escapar al leer el atributo.
- **Mi cuenta**: el controlador frontal `invoices` tiene `$auth = true` y `$guestAllowed = false`. Antes de leer nada
  comprueba el token del cliente (`Tools::getToken(false)`, con `hash_equals`); después, que el ajuste esté activado y
  que el pedido sea del cliente (`$order->id_customer === $this->context->customer->id`). Si algo falla, responde el
  aviso genérico, sin decir si el pedido existe.
- A los clientes nunca se les muestran los detalles del error (ERP apagado, ERP antiguo...): ven **Las facturas no
  están disponibles ahora mismo. Inténtalo más tarde.**
- `getModuleLink()` y el router de Symfony devuelven las URL con `&` sin escapar y van en JSON, que el script asigna
  tal cual a `href`. Es el fallo que tuvo el plugin de WooCommerce con `wp_nonce_url()` (`&amp;`).
- ShopeameServer solo entrega el PDF de una factura que esté entre las del pedido de esa tienda (si no,
  `404 invoice-not-found`), así que no se pueden pedir facturas ajenas cambiando el id.
- El módulo solo reenvía la respuesta si es `200` y empieza por `%PDF`, como adjunto (`Content-Disposition:
  attachment`) y con `X-Content-Type-Options: nosniff` y `Cache-Control: no-store`. El nombre del archivo solo
  conserva letras, números, `.`, `-` y `_`.

## 5.5 Interfaz

- La columna no es clicable: el grid de PrestaShop abre el pedido al pulsar las celdas con la clase `clickable`. El
  script además corta la propagación del clic.
- El desplegable usa `position: fixed` y se coloca con JavaScript bajo el botón: la tabla de pedidos está dentro de un
  contenedor con desplazamiento horizontal que recortaría un desplegable absoluto. Se cierra con un clic fuera, con
  **Esc**, al desplazar la página o al cambiar su tamaño.
- Las rectificativas llevan la etiqueta **Rectificativa** (con la factura que rectifican en el `title`) y el total en
  rojo.
- Las fechas se formatean con el formato de fecha del idioma (`LanguageContext::getDateFormat()` en el back-office,
  `Tools::displayDate()` en Mi cuenta) y los totales con la divisa de la factura (`formatPrice()`).
- Si la descarga falla, el back-office vuelve a la ficha del pedido con el error como aviso, y Mi cuenta, al detalle del
  pedido con el aviso genérico (`redirectWithNotifications()`).

## 5.6 Ajuste

**Permitir que los clientes descarguen las facturas de sus pedidos desde Mi cuenta** (`SHOPEAME_CUSTOMER_INVOICES`,
`'1'` o `'0'`) está en la tarjeta **Facturas del ERP** de la configuración, visible con la tienda vinculada, y se guarda
con la ruta `shopeame_save_invoice_settings` (POST con token CSRF `shopeame_invoice_settings`). Se activa al instalar;
si la clave no existe se considera activado. Desactivado, en Mi cuenta no se carga ni el script
ni la sección, y el controlador frontal rechaza las peticiones.

## 5.7 Errores

Los errores de shopea.me (`erp-offline`, `erp-not-supported`, `erp-error`, `invoice-not-found`, `unknown-event` de un
servidor antiguo...) se traducen en `InvoiceService::errorMessage()` y se anotan en **Parámetros Avanzados** →
**Registros/Logs** con el pedido como objeto: `Shopeame: the order_invoices event failed for order 7 (HTTP 503):
erp-offline`, gravedad 2 si shopea.me respondió y 3 si no hubo conexión. La tabla completa está en
[`Doc/Dev/eventos-que-envia-shopeame-para-prestashop.md`](../Dev/eventos-que-envia-shopeame-para-prestashop.md).

## 5.8 Limitación conocida del ERP

El plugin Shopeame del ERP calcula el estado del pedido (`ShopeameOrder::checkStatusInvoiced()`) solo con
`idshopeameorder` y con el total de cada factura: con una factura que agrupa varios pedidos, el segundo pedido se queda
«pendiente» y las rectificativas se cuentan en el primero. No afecta a la descarga de facturas, que no usa ese estado.
