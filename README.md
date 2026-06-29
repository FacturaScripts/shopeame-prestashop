# Shopeame para PrestaShop

Módulo que conecta una tienda **PrestaShop** (8.0 – 9.x) con **[shopea.me](https://shopea.me)**
(ShopeameServer), para que shopea.me sincronice el catálogo y los pedidos de la tienda con el ERP
(FacturaScripts / factura.city).

## Qué hace

- Añade una entrada **Shopeame** en el menú del back-office, bajo la sección **Configurar**.
- **Vinculación con shopea.me**, de dos formas:
  1. **Copiar credenciales**: la pantalla muestra la *URL de la tienda* y la *clave del webservice*
     de PrestaShop (que el módulo **genera automáticamente**, activando el webservice) con botones
     para copiarlas y pegarlas en shopea.me al conectar una tienda de tipo PrestaShop.
  2. **Vincular indicando el ERP**: el usuario introduce el *UUID* y el *token* de su ERP (los
     obtiene en shopea.me) y, con un solo botón **Vincular tienda**, el módulo guarda esos datos,
     genera la clave del webservice si hace falta y se registra en shopea.me (evento
     `register_prestashop`), guardando el `shop_uuid` y `webhook_token` devueltos.
- **Estado vinculada**: indica que la tienda está conectada, con enlace directo a su configuración
  en shopea.me, y botones para **comprobar conexión** y **desvincular**.
- **Pedidos en tiempo real**: mediante los hooks `actionValidateOrder`, `actionOrderStatusPostUpdate`
  y `actionOrderEdited` envía el `order_id` a ShopeameServer; el servidor descarga el pedido y lo
  sincroniza con el ERP.

## Instalación

> ⚠️ **La carpeta del módulo debe llamarse `shopeame`**, es decir, `modules/shopeame/`. El nombre
> del módulo es `shopeame` y PrestaShop exige que la carpeta coincida con él. (El repositorio se
> llama `shopeame-prestashop`, pero al desplegarlo la carpeta tiene que ser `shopeame`.)

Tras instalar/activar el módulo, aparecerá en **Configurar → Shopeame**. Si actualizas desde una
versión anterior, **reinstala** el módulo (desinstalar + instalar) para que la entrada de menú se
coloque bajo «Configurar».

## Configuración para desarrollo

La URL del servidor de shopea.me se controla con la constante `SHOPEAME_SERVER_URL`. Por defecto se
usa `https://shopea.me` (producción). Para apuntar a una instalación local, créala en
`config/defines_custom.inc.php` (PrestaShop carga ese archivo si existe):

```php
<?php
define('SHOPEAME_SERVER_URL', 'https://localshopea.danielfg.es');

// Modo desarrollo: acepta el certificado TLS local autofirmado (no verifica el SSL).
// Por defecto (sin definir) se asume PRODUCCIÓN. NO usar en producción.
define('SHOPEAME_DEV', true);
```

- **URL**: si `SHOPEAME_SERVER_URL` no se define, se usa `https://shopea.me`. La usan tanto el
  registro y la comprobación/desvinculación como los hooks de pedidos
  (`ConfigController::webHooksUrl()` y el enlace «ver tienda» `serverUrl()`).
- **TLS**: las peticiones usan el `HttpClient` de Symfony, que por defecto verifica el certificado.
  Con `SHOPEAME_DEV = true` se desactiva esa verificación (`verify_peer`/`verify_host => false`)
  para poder conectar con un servidor local con certificado autofirmado. Si tu CA local (p. ej. la
  de ServBay) ya está en el almacén del sistema, no necesitas `SHOPEAME_DEV`.
