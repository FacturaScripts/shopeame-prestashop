# Shopeame para PrestaShop

Módulo que conecta una tienda **PrestaShop** 9 con **[shopea.me](https://shopea.me)**
(ShopeameServer), para que shopea.me sincronice el catálogo y los pedidos de la tienda con el ERP
(FacturaScripts / factura.city).

La documentación de uso y de programación está en [`Doc/`](Doc/) y el contexto del equipo (por qué el código es como
es) en [`Doc/Context/README.md`](Doc/Context/README.md).

## Qué hace

- Añade la entrada **Shopeame**, con el logo de Shopeame, en el menú del back-office, bajo **Configurar**.
- **Vinculación con shopea.me**, de dos formas:
  1. **Copiar los datos**: la pantalla muestra la *URL de la tienda* y la *clave del webservice* (que el módulo
     genera, activando el webservice) para pegarlas en shopea.me. Después del alta, ShopeameServer devuelve las
     credenciales firmadas al controlador `index.php?fc=module&module=shopeame&controller=link`.
  2. **Indicar el ERP**: el usuario introduce el *UUID* y el *token* de su ERP y pulsa **Vincular tienda**; el módulo
     genera la clave si hace falta y registra la tienda en shopea.me (evento `register_prestashop`).
- **Tienda vinculada**: botones para **comprobar la conexión**, **desvincular** o **borrar los datos del módulo**.
- **Pedidos en tiempo real**: los hooks `actionValidateOrder`, `actionOrderStatusPostUpdate` y `actionOrderEdited`
  envían el `order_id` a ShopeameServer, que descarga el pedido y lo sincroniza con el ERP.

## Instalación

> ⚠️ **La carpeta del módulo debe llamarse `shopeame`** (`modules/shopeame/`). El repositorio se llama
> `shopeame-prestashop`, pero PrestaShop exige que la carpeta coincida con el nombre del módulo.

El zip para instalar o publicar lo genera `~/Trabajos/FacturaScripts/scripts/build.sh`
(`zip-plugins/shopeame-prestashop.zip`): incluye `vendor/` con el autoload de Composer, que no está en git. Para
trabajar con el repositorio directamente, genera antes el autoload:

```bash
composer dump-autoload -o --no-dev
```

Las actualizaciones son automáticas: PrestaShop detecta la versión nueva y, si la versión los trae, ejecuta los
scripts de `upgrade/` al entrar en **Módulos → Gestor de módulo**, o a mano con:

```bash
php bin/console prestashop:module upgrade shopeame
```

## Desarrollo

La URL del servidor se controla con la constante `SHOPEAME_SERVER_URL` (por defecto `https://shopea.me`). Para apuntar
a una instalación local, créala en `config/defines_custom.inc.php` de la tienda:

```php
<?php
define('SHOPEAME_SERVER_URL', 'https://shopea.localhost');
```

La verificación TLS está siempre activa (PrestaShop Addons rechaza los módulos que la desactivan). Con el certificado de
ServBay funciona sin hacer nada más.

Las reglas de trabajo (textos en inglés y traducción XLIFF, versiones, modo debug, pruebas de extremo a extremo y
publicación en Addons) están en [`Doc/Context/00-reglas-de-trabajo.md`](Doc/Context/00-reglas-de-trabajo.md).
