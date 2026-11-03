Esta página explica cómo probar el módulo contra una instalación propia de ShopeameServer, cómo mantener sus textos y
traducciones y cómo preparar una versión para PrestaShop Addons.

## 1. Apuntar a otro servidor

Por defecto el módulo habla con `https://shopea.me`. Para probarlo contra otra instalación de ShopeameServer, se crea
o se edita el archivo `config/defines_custom.inc.php` de la tienda de pruebas, que PrestaShop carga si existe:

```php
<?php
define('SHOPEAME_SERVER_URL', 'https://shopea.localhost');
```

La verificación del certificado TLS está siempre activa: PrestaShop Addons no admite módulos que la desactiven. Si el
servidor de pruebas usa un certificado propio, se añade su autoridad de certificación al almacén del sistema o al
`curl.cainfo` de PHP.

Al terminar, se desvincula la tienda y se quita la constante, para que la tienda no envíe pedidos a shopea.me con
credenciales de pruebas.

## 2. Textos y traducciones

- Las cadenas se escriben en inglés con el dominio `Modules.Shopeame.Admin`:
  `$this->trans('Text', [], 'Modules.Shopeame.Admin')` en la clase del módulo,
  `$this->trans('Text', 'Modules.Shopeame.Admin')` en `ConfigController` y `|trans` en la plantilla Twig, que declara
  `{% trans_default_domain 'Modules.Shopeame.Admin' %}`.
- La traducción al español está en `translations/es-ES/ModulesShopeameAdmin.es-ES.xlf`. Cada texto nuevo se añade como
  un `trans-unit` cuyo `id` es el `md5` del texto en inglés:

```xml
<trans-unit id="<md5 del texto en inglés>" approved="yes">
  <source>Link store</source>
  <target state="final">Vincular tienda</target>
</trans-unit>
```

- Tras cambiar el XLIFF se vacía `var/cache/` para ver la traducción. Si falta, PrestaShop muestra el texto en inglés.

## 3. Publicar una versión

1. Se sube el número de versión en `$this->version` de `shopeame.php` (tres números). Si la versión registra hooks,
   cambia la pestaña del menú, migra la configuración o cambia los permisos de la clave del webservice, se añade su
   `upgrade/upgrade-X.Y.Z.php` con la función `upgrade_module_X_Y_Z($module)`.
2. Se prueba la tienda con el modo depuración activado (`_PS_MODE_DEV_` en `config/defines.inc.php`): los revisores de
   PrestaShop Addons lo usan siempre y cualquier aviso de PHP es motivo de rechazo.
3. Se genera el zip con `build.sh`: una sola carpeta `shopeame` con los archivos del módulo, sin lo que lista
   `.distignore` (documentación, `README.md`, `AGENTS.md`, `CLAUDE.md`, `composer.lock` y archivos ocultos), con
   `vendor/` regenerado solo con el autoload (`composer dump-autoload -o --no-dev`) y un `index.php` en cada carpeta.
4. Se pasa el zip por el Validator de PrestaShop y se corrige lo que indique.
5. La primera vez se envía el zip desde la cuenta de vendedor de PrestaShop Addons, con la ficha y la documentación;
   las versiones siguientes se suben como actualización indicando los cambios y las versiones de PrestaShop
   compatibles.

## Ver también

- [Endpoint de vinculación de Shopeame para PrestaShop](endpoint-de-vinculacion-de-shopeame-para-prestashop.md)
- [Eventos que envía Shopeame para PrestaShop](eventos-que-envia-shopeame-para-prestashop.md)
