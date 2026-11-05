# 0007. Facturas de los pedidos bajo demanda a través de shopea.me

- **Fecha**: 05/11/2026
- **Aprobada por**: el usuario, al elegir para el plugin de WooCommerce la opción «bajo demanda» («Me gusta la opción A y
  me parece correcta») y pedir que se replique en este módulo con los mismos eventos de ShopeameServer: facturas
  parciales, agrupadas y rectificativas, en el listado y en la ficha del pedido, y para el cliente en Mi cuenta, con un
  ajuste para desactivarlo.

## Contexto

Se quería descargar desde PrestaShop las facturas que el ERP genera para cada pedido. Un pedido puede tener varias
facturas (facturación parcial), facturas rectificativas y facturas que agrupan varios pedidos del mismo cliente.
PrestaShop no conoce la URL ni la clave API del ERP; ShopeameServer sí (`ErpLink`) y ya autentica a la tienda con su
UUID y su token. ShopeameServer ya tenía los eventos `order_invoices` e `invoice_pdf` (hechos para WooCommerce), que no
dependen de la plataforma de la tienda.

## Decisión

- **Bajo demanda**: PrestaShop pide las facturas a shopea.me al pulsar el botón o al abrir la ficha (`order_invoices`)
  y cada PDF al descargarlo (`invoice_pdf`). No se guarda nada ni en shopea.me ni en PrestaShop; el PDF se reenvía al
  navegador como adjunto con el código de la factura como nombre.
- Back-office: columna con botón en el listado de pedidos (hooks del grid `order`; solo se piden al pulsar) y cuadro en
  la columna lateral de la ficha (`displayAdminOrderSide`; se cargan al abrirla). Las rutas exigen permiso de lectura
  sobre `AdminOrders`.
- Mi cuenta: botón junto a **Detalles** en el historial, copiado del enlace del tema porque no hay hook por pedido, y
  sección en el detalle del pedido (`displayOrderDetail`). Un controlador frontal propio con `$auth`, el token del
  cliente y la comprobación de `$order->id_customer` en el servidor.
- Ajuste `SHOPEAME_CUSTOMER_INVOICES`, activado por defecto, porque es el comercio quien decide si sus clientes ven las
  facturas.
- Los mismos mensajes de error que WooCommerce; al cliente, siempre el aviso genérico.
- ShopeameServer y el plugin Shopeame del ERP no cambian.

## Consecuencias

- Las facturas siempre están al día (rectificativas nuevas, facturas borradas), pero hace falta que el ERP esté
  encendido: si no, se avisa (`erp-offline`).
- Cada consulta hace varias llamadas a la API del ERP (unas 7 u 8 por pedido); por eso el listado no las pide al
  pintarse.
- Una factura que agrupa varios pedidos, y sus rectificativas, aparecen en todos ellos aunque la rectificativa solo
  afecte a las líneas de uno: es la misma factura del mismo cliente.
- El módulo registra cinco hooks nuevos y un controlador frontal sin cambiar de versión: aunque la regla 0.4 pide una
  versión nueva con su upgrade, el usuario decidió dejarlo en la 1.0.0 («Déjala en 1.0.0»), así que los registra la
  instalación y no hay upgrade. Una tienda con una copia anterior de la 1.0.0 instalada tiene que restablecer el módulo
  (**Restablecer** en el gestor de módulos) para registrar los hooks.
- Aparece un segundo dominio de traducción, `Modules.Shopeame.Shop`, para los textos de Mi cuenta.

## Alternativas descartadas

- **Enviar las facturas a PrestaShop** (el ERP avisa y shopea.me sube el PDF a la tienda): necesitaba un canal nuevo
  del ERP a shopea.me, un endpoint nuevo en el módulo, guardar PDF con datos personales en la tienda y mantenerlos al
  día al cambiar las facturas.
- **Guardarlas como facturas nativas de PrestaShop** (`OrderInvoice`): PrestaShop numera y genera sus propias
  facturas a partir del pedido y no admite un PDF externo; además duplicaría la numeración del ERP.
- **Que PrestaShop hable directamente con el ERP**: obligaría a guardar la URL y la clave API del ERP en la tienda.
- **Un hook por pedido en el historial**: no existe en PrestaShop 9 (ni en Classic ni en Hummingbird); sobrescribir
  `history.tpl` desde el módulo rompería los temas personalizados.
