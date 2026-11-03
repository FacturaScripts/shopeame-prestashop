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
  `$this->trans('Text', [], 'Modules.Shopeame.Admin')` también en `ConfigController` (firma de `PrestaShopAdminController`) y `|trans` en la plantilla Twig, que declara
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
3. Se genera el zip con `build.sh` (`zip-plugins/shopeame-prestashop-addons/shopeame.zip`, con el nombre del módulo
   como pide Addons): una sola carpeta `shopeame` con los archivos del módulo, sin lo que lista
   `.distignore` (documentación, `README.md`, `AGENTS.md`, `CLAUDE.md`, `composer.lock` y los archivos ocultos de desarrollo; el `.htaccess` sí va), con
   `vendor/` regenerado solo con el autoload (`composer dump-autoload -o --no-dev`) y un `index.php` en cada carpeta.
4. Se pasa el estándar de código de PrestaShop (ver abajo) y después el zip por el Validator de PrestaShop, y se
   corrige lo que indique.
5. La primera vez se envía el zip desde la cuenta de vendedor de PrestaShop Addons, con la ficha y la documentación;
   las versiones siguientes se suben como actualización indicando los cambios y las versiones de PrestaShop
   compatibles.
6. Antes de generar el zip se regeneran los PDF de documentación con `python3 Doc/Addons/readme.py`, que deja
   `readme_es.pdf` y `readme_en.pdf` con la versión nueva en `docs/`: van dentro del zip y se suben también a la
   ficha. Si la
   interfaz ha cambiado, se repiten las capturas de `Doc/Addons/capturas` y se regeneran las imágenes con
   `python3 Doc/Addons/imagenes.py` (ver la sección 4.6 del contexto de publicación).

## 4. Estándar de código de PrestaShop

El Validator cuenta en su sección «Standards» lo que no cumple el estándar de código de PrestaShop, que es php-cs-fixer
con la configuración de `prestashop/php-dev-tools`. Se puede pasar en local antes de enviar el zip, con las herramientas
instaladas fuera del módulo para no tocar su `composer.json`:

```bash
mkdir -p ~/prestashop-cs && cd ~/prestashop-cs
composer require prestashop/php-dev-tools friendsofphp/php-cs-fixer
```

Se crea en esa carpeta `.php-cs-fixer.dist.php` apuntando al módulo. Usa las reglas de PrestaShop salvo
`blank_line_after_opening_tag`, porque la comprobación de licencias del Validator exige la cabecera justo después de
`<?php`. La clase `PrestaShop\CodingStandards\CsFixer\Config` sobrescribe `getRules()` y no deja cambiar reglas con
`setRules()`, así que se copian sus reglas a una configuración normal:

```php
<?php
require __DIR__ . '/vendor/autoload.php';

$rules = array_merge(
    (new PrestaShop\CodingStandards\CsFixer\Config())->getRules(),
    ['blank_line_after_opening_tag' => false]
);
$config = (new PhpCsFixer\Config('PrestaShop coding standard'))
    ->setRiskyAllowed(true)
    ->setRules($rules)
    ->setUsingCache(false);
$config->getFinder()
    ->in(getenv('HOME') . '/Trabajos/FacturaScripts/shopeame-prestashop')
    ->exclude(['vendor', 'Doc']);

return $config;
```

Y se revisa y corrige con:

```bash
vendor/bin/php-cs-fixer fix --dry-run --diff   # lista lo que no cumple, sin cambiar nada
vendor/bin/php-cs-fixer fix                    # lo corrige
```

Si php-cs-fixer avisa de que la versión de PHP no está soportada, se ejecuta con `PHP_CS_FIXER_IGNORE_ENV=1` delante.
Los cambios son solo de formato; después hay que pasar `php -l` y probar la pantalla.

## Ver también

- [Endpoint de vinculación de Shopeame para PrestaShop](endpoint-de-vinculacion-de-shopeame-para-prestashop.md)
- [Eventos que envía Shopeame para PrestaShop](eventos-que-envia-shopeame-para-prestashop.md)
