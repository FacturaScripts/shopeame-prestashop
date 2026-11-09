# 0001. Adaptación a la validación técnica de PrestaShop Addons

- **Fecha**: 03/11/2026
- **Aprobada por**: el usuario, al pedir revisar el módulo para publicarlo en PrestaShop Addons sin rechazos.
- **Estado**: el módulo ya no se publica en Addons ([decisión 0007](0007-distribucion-desde-github-con-actualizaciones.md)),
  pero lo de esta decisión se mantiene como buena práctica.

## Contexto

La versión 1.1.1 tenía varios motivos de rechazo de la lista de validación técnica: licencia LGPL/GPL (no compatible
con OSL/AFL), textos visibles en español sin pasar por inglés, claves `SHOEAME_*` con el prefijo mal escrito, la
constante `SHOPEAME_DEV` que desactivaba la verificación TLS, carpetas sin `index.php`, código comentado, un
`views/js/forms.js` sin usar y una desinstalación que dejaba la configuración y la clave del webservice. Además, el
hook de pedidos fallaba fuera del back-office porque el autoload de `src/` solo lo carga el kernel de Symfony, y algunos
errores de shopea.me rompían la pantalla (`toArray()` sin capturar, un acceso `$content["error"]` sobre una cadena) o no
eran compatibles con PHP 7 (tipo de retorno `false|array`).

## Decisión

- Licencia **AFL-3.0**, la de los módulos de PrestaShop, en `LICENSE`, `composer.json` y una cabecera común en cada
  archivo. Se conserva el titular del copyright y se quitan los correos personales de las cabeceras.
- Textos visibles en inglés con el dominio `Modules.Shopeame.Admin` y traducción al español en
  `translations/es-ES/ModulesShopeameAdmin.es-ES.xlf` (`isUsingNewTranslationSystem()`). Los textos ingleses son los
  mismos que los del plugin de WooCommerce, cambiando «plugin» por «module».
- Claves renombradas a `SHOPEAME_API_KEY` y `SHOPEAME_SHOP_REGISTERED`; `upgrade-1.2.0.php` copia el valor de las
  antiguas y las borra. Versión **1.2.0**. (La [decisión 0004](0004-primera-version-publica-1-0-0.md) quitó después
  los upgrades y dejó el módulo en la 1.0.0, porque nadie lo tenía instalado.)
- Se quita `SHOPEAME_DEV`; se mantiene `SHOPEAME_SERVER_URL` para desarrollo.
- `index.php` en todas las carpetas.
- `require_once __DIR__ . '/vendor/autoload.php'` en `shopeame.php`.
- Todas las llamadas a shopea.me pasan por `postEvent()`, que captura cualquier error y decodifica la respuesta a mano.
- La clave del webservice se genera con `random_bytes()` en lugar de `mt_rand()`, y se elimina `lib/Utilities.php`.
- La desinstalación borra la configuración, la clave del webservice y la pestaña; no desactiva el webservice porque
  otras integraciones pueden usarlo.
- Se registra el controlador frontal (`$this->controllers = ['link']`) y se validan el UUID y el token con
  `ctype_alnum()`.

## Alternativas descartadas

- **Pasar `ConfigController` a `PrestaShopAdminController`** con inyección de servicios para quitar el aviso de
  obsolescencia de `get()` en PrestaShop 9: esa clase no existe en PrestaShop 8 y obligaría a subir el mínimo a 9.0,
  como hacen los módulos oficiales. El aviso solo va al log de depuración (nivel INFO), no se muestra, y `get()`
  funciona hasta PrestaShop 10. Se revisará cuando se deje de dar soporte a PrestaShop 8. (La
  [decisión 0005](0005-solo-prestashop-9.md) lo cambió: el Validator lo marca como error y el módulo pasó a pedir
  PrestaShop 9.)
- **Traducir también los comentarios al inglés**: la regla del equipo es comentar en español. Quedó como riesgo para
  la revisión de Addons, que ya no se hace (ver [decisión 0007](0007-distribucion-desde-github-con-actualizaciones.md)).
- **Limitar los permisos de la clave del webservice**: ShopeameServer escribe en varios recursos y no hay una lista
  cerrada; limitarlos sin probar toda la sincronización podía romperla. Queda anotado en 4.4. Se resolvió en la
  [decisión 0003](0003-permisos-minimos-de-la-clave-del-webservice.md).

## Resultado

Sin avisos del módulo en modo debug (PrestaShop 9.1.4, PHP 8.5) y prueba de extremo a extremo contra shopea.localhost
en los dos sentidos: vincular desde PrestaShop con el UUID y el token del ERP, aviso de pedido, desvincular, vincular
desde la pantalla «conectar tienda» con la recepción firmada, borrar datos del módulo, desinstalar y reinstalar.
