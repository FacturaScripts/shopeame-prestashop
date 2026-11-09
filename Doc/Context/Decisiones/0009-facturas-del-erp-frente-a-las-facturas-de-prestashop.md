# 0009. «Facturas del ERP» frente a las facturas de PrestaShop

- **Fecha**: 05/11/2026
- **Aprobada por**: el usuario. La tarea pedía decidir con criterio el texto para que el comercio no confunda las
  facturas del ERP con las facturas nativas de PrestaShop; al revisarlo pidió que en el listado de pedidos la columna y
  el botón digan solo «Facturas» en vez de «Facturas del ERP».

## Contexto

PrestaShop genera sus propias facturas (`OrderInvoice`, numeradas `#FA000001`...) si está activado **Pedidos** →
**Facturas** → **Activar facturas**. Aparecen en el bloque **Pago** y en la pestaña **Documentos** de la ficha del
pedido, en el menú **Pedidos** → **Facturas** y, en Mi cuenta, en la columna **Factura** del historial (icono PDF).
En la tienda local estaban activadas: la ficha del pedido de prueba mostraba `#FA000004` a la vez que las facturas
del ERP `FAC0001A4` y `FAC0001A5`.

En WooCommerce no había este problema porque WooCommerce no genera facturas propias, y allí el botón se llama
**Facturas** (`Invoices`).

## Decisión

- **Listado de pedidos**: la columna y el botón se llaman **Facturas** (`Invoices`), a petición del usuario: es corto
  y cabe en una línea. Ahí no hay facturas de PrestaShop con las que mezclarlo, y al pulsarlo la lista solo trae
  códigos del ERP (`FAC0001A4`), distintos de los de PrestaShop (`#FA000004`).
- **Ficha del pedido y configuración**: el cuadro y la tarjeta se llaman **Facturas del ERP** (`ERP invoices`). En la
  ficha conviven con la factura nativa del bloque **Pago** y de la pestaña **Documentos**, así que el nombre explica de
  dónde salen; la tarjeta de la configuración lo dice además en texto: «No son las facturas de PrestaShop».
- **Mi cuenta**: el botón y la sección se llaman **Facturas** (`Invoices`). El cliente no sabe qué es el ERP y para
  él son sus facturas; la columna nativa del historial se llama **Factura**, en singular y con un icono, y la sección
  va en el detalle del pedido, donde PrestaShop no muestra sus facturas.
- Los textos de los dos lados van en dominios distintos (`Modules.Shopeame.Admin` y `Modules.Shopeame.Shop`), así que
  se pueden cambiar por separado.
- La documentación de usuario recomienda desactivar las facturas de PrestaShop si se factura en el ERP, para que el
  cliente no vea dos facturas del mismo pedido.

## Consecuencias

- Si un comercio factura en el ERP y deja activadas las facturas de PrestaShop, su cliente verá en el historial la
  columna **Factura** (la de PrestaShop) y el botón **Facturas** (las del ERP). No se puede evitar desde el módulo
  sin tocar las plantillas del tema; queda explicado en la página de usuario.

## Alternativas descartadas

- **Facturas del ERP** también en el listado (la primera propuesta): el usuario lo prefirió corto.
- **Facturas** también en la ficha del pedido: se confunde con la factura nativa del bloque **Pago** y de la pestaña
  **Documentos**, que es la de PrestaShop.
- **Facturas de Shopeame** o **Facturas de shopea.me**: shopea.me no factura, solo las trae del ERP; el comercio
  pensaría que es otro servicio.
- **Facturas del ERP** también en Mi cuenta: el cliente final no tiene por qué saber qué es un ERP.
