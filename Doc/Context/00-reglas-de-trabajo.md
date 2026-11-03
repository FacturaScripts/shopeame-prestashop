# 0. Reglas de trabajo

## 0.1 Documentación oficial

Cada tarea se contrasta antes con la documentación oficial de PrestaShop (https://devdocs.prestashop-project.org), no
con la memoria: creación de módulos, buenas prácticas, hooks, traducciones, webservice y la lista de comprobación de la
validación técnica de Addons (solo existe en la sección 1.7: `modules/sell/techvalidation-checklist`). Si la
documentación no aclara algo, se mira el código de PrestaShop de la instalación local (`/Applications/ServBay/www/prestashop`)
o de un módulo oficial que haga lo mismo.

## 0.2 Convenciones del código

- Todo lo global lleva el prefijo del módulo: claves de configuración `SHOPEAME_*`, clases fuera de un espacio de
  nombres `Shopeame*`, clases CSS `shopeame-*`, rutas `shopeame_*`. Las clases de `src/` van en el espacio de nombres
  `PrestaShop\Module\Shopeame` y se cargan con el autoload de Composer (`vendor/`, generado por `build.sh`).
- Cada archivo PHP empieza con la cabecera de la licencia AFL-3.0 y con `if (!defined('_PS_VERSION_')) { exit; }`
  (salvo los `index.php`, que redirigen a la carpeta superior). Cada carpeta tiene su `index.php`.
- Nada de `serialize()`, `eval()`, código de depuración (`var_dump`, `dump`, `console.log`) ni código comentado.
- Las peticiones salen con el `HttpClient` de Symfony y nunca desactivan la verificación TLS.
- Comentarios y Docblocks en español; los textos visibles, en inglés y traducibles (ver 0.3).

## 0.3 Textos y traducciones

- Toda cadena visible usa el sistema de traducciones nuevo con el dominio `Modules.Shopeame.Admin`:
  `$this->trans('Text', [], 'Modules.Shopeame.Admin')` en el módulo, `$this->trans('Text', 'Modules.Shopeame.Admin')`
  en `ConfigController` (firma de `FrameworkBundleAdminController`), `|trans` en Twig con
  `{% trans_default_domain 'Modules.Shopeame.Admin' %}`.
- La traducción al español va en `translations/es-ES/ModulesShopeameAdmin.es-ES.xlf`; el `id` de cada `trans-unit` es
  el `md5` del texto en inglés. Al añadir o cambiar un texto, se añade o cambia su entrada en el XLIFF y se vacía
  `var/cache/` para verla. PrestaShop muestra el texto en inglés si falta la traducción.
- Los mensajes de `PrestaShopLogger` van en inglés y empiezan por `Shopeame: `.

## 0.4 Versiones

Una versión nueva cambia `$this->version` en `shopeame.php` (siempre tres números). Si registra hooks nuevos, cambia
pestañas o migra configuración, lleva su `upgrade/upgrade-X.Y.Z.php`, que PrestaShop ejecuta una vez cuando la
versión instalada es menor. Para aplicarlo en local: `php bin/console prestashop:module upgrade shopeame`.

## 0.5 Entorno local

- `~/Trabajos/deploy.sh` copia el repositorio a `/Applications/ServBay/www/prestashop/modules/shopeame`
  (https://ps.localhost) con las mismas exclusiones que el zip (`.distignore` y archivos ocultos) y vacía
  `var/cache/`. `vendor/` no está en git: si falta, `composer dump-autoload -o --no-dev` en el repositorio.
- El dominio de la tienda local es `localps.danielfg.es`, que el navegador integrado de Claude Code no abre. Para
  probar con él hay que cambiar temporalmente el dominio de la tienda a `ps.localhost` (tabla `shop_url` y
  `PS_SHOP_DOMAIN`/`PS_SHOP_DOMAIN_SSL`) y devolverlo al terminar.
- Para probar contra ShopeameServer local se crea `config/defines_custom.inc.php` con
  `define('SHOPEAME_SERVER_URL', 'https://shopea.localhost');`. El certificado de ServBay se acepta sin desactivar la
  verificación TLS, en los dos sentidos. Al terminar se desvincula la tienda y se restaura el archivo: si no, la
  tienda local enviaría sus pedidos a shopea.me con credenciales de pruebas.

## 0.6 Antes de dar un cambio por terminado

1. `php -l` de los archivos tocados.
2. Probar con el modo debug activado (`_PS_MODE_DEV_` en `config/defines.inc.php`): los revisores de Addons lo usan
   siempre y cualquier aviso es motivo de rechazo. En `var/logs/dev-*.log` no debe aparecer nada de
   `modules/shopeame` salvo el aviso conocido de `FrameworkBundleAdminController::get()` (ver
   [decisión 0001](Decisiones/0001-adaptacion-a-prestashop-addons.md)).
3. Si el cambio toca la vinculación o los pedidos, la prueba de extremo a extremo contra shopea.localhost en los dos
   sentidos (desde PrestaShop con el UUID y el token del ERP, y desde la pantalla «conectar tienda» de shopea con la
   URL y la clave del webservice), más desvincular, borrar datos y desinstalar.
4. Pasar el zip de `build.sh` por el Validator de PrestaShop (https://validator.prestashop.com).
5. Actualizar `Doc/Context`, las páginas de `Doc/User` o `Doc/Dev` afectadas y el XLIFF si cambian textos.
