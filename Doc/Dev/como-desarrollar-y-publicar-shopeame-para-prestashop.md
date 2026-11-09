Esta página explica cómo probar el módulo contra una instalación propia de ShopeameServer, cómo mantener sus textos y
traducciones, cómo publicar una versión en las releases de GitHub y cómo probar que las tiendas reciben la
actualización. El módulo no está en PrestaShop Addons.

## 1. Apuntar a otro servidor

Por defecto el módulo habla con `https://shopea.me`. Para probarlo contra otra instalación de ShopeameServer, se crea
o se edita el archivo `config/defines_custom.inc.php` de la tienda de pruebas, que PrestaShop carga si existe:

```php
<?php
define('SHOPEAME_SERVER_URL', 'https://shopea.localhost');
```

La verificación del certificado TLS está siempre activa (es uno de los requisitos de Addons que el módulo mantiene). Si el
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
2. Se prueba la tienda con el modo depuración activado (`_PS_MODE_DEV_` en `config/defines.inc.php`): cualquier aviso
   de PHP se corrige.
3. Se pasa el estándar de código de PrestaShop (ver abajo). El Validator de PrestaShop es opcional.
4. Se genera el zip con `build.sh` (`zip-plugins/shopeame-prestashop-addons/shopeame.zip`): una sola carpeta
   `shopeame` con los archivos del módulo, sin lo que lista `.distignore` (documentación, `README.md`, `AGENTS.md`,
   `CLAUDE.md`, `composer.lock` y los archivos ocultos de desarrollo; el `.htaccess` sí va), con `vendor/` regenerado
   solo con el autoload (`composer dump-autoload -o --no-dev`) y un `index.php` en cada carpeta.
5. Con los cambios en `main`, se lanza la acción **Crear Release** de GitHub (pestaña *Actions* o
   `gh workflow run release.yml`). Falla si `$this->version` no tiene tres números y no hace nada si esa versión ya
   tiene release. Si todo está bien, crea la etiqueta `v<versión>` con los commits desde la última etiqueta como
   notas, agrupados en funcionalidades (`feat`), correcciones (`fix`) y otros cambios, y adjunta:
   - el mismo zip del paso 4 con dos nombres, `shopeame-<versión>.zip` y `shopeame.zip`. Este último permite un enlace
     fijo a la última versión: `https://github.com/FacturaScripts/shopeame-prestashop/releases/latest/download/shopeame.zip`.
   - `info.json`, del que dependen las actualizaciones (ver el apartado siguiente).

Desde ese momento, cada tienda ve la versión nueva en su siguiente consulta (cada 12 horas, o al pulsar **Buscar
actualizaciones** en la pantalla de Shopeame).

## 4. Cómo llegan las actualizaciones a las tiendas

`UpdateChecker` (`src/Update/UpdateChecker.php`) descarga
`https://github.com/FacturaScripts/shopeame-prestashop/releases/latest/download/info.json`:

```json
{
    "name": "Shopeame",
    "slug": "shopeame",
    "version": "1.0.1",
    "download_url": "https://github.com/FacturaScripts/shopeame-prestashop/releases/download/v1.0.1/shopeame.zip",
    "details_url": "https://github.com/FacturaScripts/shopeame-prestashop/releases/tag/v1.0.1",
    "homepage": "https://github.com/FacturaScripts/shopeame-prestashop",
    "released": "2026-11-10",
    "ps_min": "9.0.0",
    "ps_max": "9.99.99",
    "php_min": "8.1",
    "changelog": ["feat: line 1 of the changelog", "fix: line 2 of the changelog"]
}
```

- `version` (tres números) y `download_url` son obligatorios. El zip tiene que estar en las releases del repositorio y
  llamarse `shopeame.zip`: PrestaShop saca el nombre del módulo del nombre del archivo.
- Si la versión es mayor que la instalada y la tienda cumple `ps_min`, `ps_max` y `php_min`, el hook
  `actionListModules` se la pasa al gestor de módulos (con `changelog` como lista de cambios) y la pantalla de
  Shopeame muestra el aviso con **Actualizar ahora**.
- Una versión que registre hooks nuevos necesita su `upgrade/upgrade-X.Y.Z.php` que los registre en las tiendas que
  actualizan.

### Probar una actualización sin publicar una release

1. Se genera el zip con una versión mayor que la instalada (por ejemplo, cambiando `$this->version` a `1.0.1` solo en
   la copia que se comprime, con `vendor/` incluido). El archivo tiene que llamarse `shopeame.zip`.
2. Se copian el zip y un `info.json` como el de arriba a una carpeta servida por una web local (por ejemplo,
   `https://wp.localhost/shopeame-update-test/ps/`), con `download_url` apuntando a ese zip.
3. Se añade a `config/defines_custom.inc.php` de la tienda de pruebas
   `define('SHOPEAME_UPDATE_INFO_URL', 'https://wp.localhost/shopeame-update-test/ps/info.json');`. Con ella, el
   módulo admite cualquier URL de descarga que termine en `/shopeame.zip`.
4. Se pulsa **Buscar actualizaciones** al pie de la pantalla de Shopeame (o se espera a que caduque la consulta
   guardada) y se actualiza desde el aviso o desde **Módulos** → **Gestor de módulo** → **Actualizaciones**.
5. Al terminar se quita la constante, se vuelve a copiar el módulo del repositorio, se devuelve la versión en la tabla
   `module` (`UPDATE <prefijo>module SET version = '<la del repositorio>' WHERE name = 'shopeame'`), se borran
   `SHOPEAME_UPDATE_INFO` y `SHOPEAME_UPDATE_CHECKED` y se vacía `var/cache/`.

## 5. Estándar de código de PrestaShop

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

- [Cómo actualizar el módulo Shopeame en PrestaShop](../User/como-actualizar-el-modulo-shopeame-en-prestashop.md)
- [Endpoint de vinculación de Shopeame para PrestaShop](endpoint-de-vinculacion-de-shopeame-para-prestashop.md)
- [Eventos que envía Shopeame para PrestaShop](eventos-que-envia-shopeame-para-prestashop.md)
