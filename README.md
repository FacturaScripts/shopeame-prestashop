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
- **Actualizaciones**: el módulo no está en PrestaShop Addons. El hook `actionListModules` anuncia al gestor de módulos
  la última versión publicada en las releases de GitHub (lee su `info.json`), así que aparece en **Módulos → Gestor de
  módulo → Actualizaciones** con el botón **Actualizar**. La pantalla de Shopeame muestra también el aviso con
  **Actualizar ahora**, la versión instalada y **Buscar actualizaciones**.

## Instalación

> ⚠️ **La carpeta del módulo debe llamarse `shopeame`** (`modules/shopeame/`). El repositorio se llama
> `shopeame-prestashop`, pero PrestaShop exige que la carpeta coincida con el nombre del módulo.

Los clientes descargan `shopeame.zip` de la
[última release](https://github.com/FacturaScripts/shopeame-prestashop/releases/latest) y lo suben en **Módulos →
Gestor de módulo → Subir un módulo**. El zip lo genera `~/Trabajos/FacturaScripts/scripts/build.sh`
(`zip-plugins/shopeame-prestashop-addons/shopeame.zip`): incluye `vendor/` con el autoload de Composer, que no está en git. Para
trabajar con el repositorio directamente, genera antes el autoload:

```bash
composer dump-autoload -o --no-dev
```

Al actualizar (desde el gestor de módulos, desde la pantalla de Shopeame o subiendo el zip nuevo), PrestaShop sustituye
los archivos y ejecuta los scripts de `upgrade/` que traiga la versión. En local se pueden ejecutar a mano con:

```bash
php bin/console prestashop:module upgrade shopeame
```

## Release en GitHub

Con los cambios de versión ya en `main`, lanza la acción **Crear Release** desde la pestaña *Actions* del
repositorio (o `gh workflow run release.yml`). Comprueba que `$this->version` de `shopeame.php` tiene tres números,
crea la etiqueta `v<versión>` con los commits desde la última etiqueta como
notas y adjunta el zip, el mismo que genera `build.sh`, dos veces (`shopeame-<versión>.zip` y `shopeame.zip`), más el
`info.json` del que dependen las actualizaciones. Si esa versión ya tiene release, no hace nada. Las tiendas ven la
versión nueva en su siguiente consulta (cada 12 horas, o al pulsar **Buscar actualizaciones**).

El enlace https://github.com/FacturaScripts/shopeame-prestashop/releases/latest/download/shopeame.zip descarga
siempre la última release.

## Desarrollo

La URL del servidor se controla con la constante `SHOPEAME_SERVER_URL` (por defecto `https://shopea.me`). Para apuntar
a una instalación local, créala en `config/defines_custom.inc.php` de la tienda:

```php
<?php
define('SHOPEAME_SERVER_URL', 'https://shopea.localhost');
```

La verificación TLS está siempre activa. Con el certificado de ServBay funciona sin hacer nada más. Para probar las
actualizaciones sin publicar una release, `SHOPEAME_UPDATE_INFO_URL` apunta a un `info.json` local (ver
`Doc/Dev/como-desarrollar-y-publicar-shopeame-para-prestashop.md`).

Las reglas de trabajo (textos en inglés y traducción XLIFF, versiones, modo debug, pruebas de extremo a extremo y
publicación) están en [`Doc/Context/00-reglas-de-trabajo.md`](Doc/Context/00-reglas-de-trabajo.md).
