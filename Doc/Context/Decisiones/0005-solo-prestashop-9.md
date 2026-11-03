# 0005. Solo PrestaShop 9

- **Fecha**: 03/11/2026
- **Aprobada por**: el usuario, al elegir subir el mínimo a PrestaShop 9.0 tras recibir el reporte del Validator de
  la versión 1.0.0.

## Contexto

El primer reporte del Validator de PrestaShop Addons (03/11/2026) dio estos resultados:

- **Errores**: `ConfigController` extiende `FrameworkBundleAdminController`, que está obsoleta («Use
  PrestaShopAdminController instead»), y el código de `src/` usa `Context` (`Context::getContext()->shop` en
  `ConfigController` y `Context::getContext()->getTranslator()` en `ConfigFormManager`).
- **Seguridad**: falta un `.htaccess` en la raíz que impida listar el módulo.
- **Licencias**: `views/js/admin.js` no tiene la cabecera de la licencia.
- **Compatibilidad**: `$tab->active = 1`, pero la propiedad `TabCore::$active` es `bool`.

`PrestaShopAdminController` y `ShopContext` solo existen desde PrestaShop 9.0, así que no hay forma de quitar el
primer error sin dejar de ser compatible con PrestaShop 8. La [decisión 0001](0001-adaptacion-a-prestashop-addons.md)
lo había aplazado porque solo dejaba un aviso en el log, pero el Validator lo trata como error.

## Decisión

- `ps_versions_compliancy` pasa a `min => 9.0.0` y `composer.json` a `php >= 8.1`, que es lo que pide PrestaShop 9.
  La versión sigue siendo la 1.0.0, porque aún no se ha publicado.
- `ConfigController` extiende `PrestaShopAdminController`, como los módulos oficiales de PrestaShop 9
  (`ps_classic_edition`):
  - Se registra como servicio en `config/services.yml` con `autowire` y `autoconfigure`. PrestaShop 9 solo registra por su cuenta
    (`ModuleControllerRegisterPass`) los controladores de módulo que extienden `FrameworkBundleAdminController`.
  - El form handler llega como argumento de `index()` con `#[Autowire(service: ...)]`, en lugar de `$this->get()`.
  - La URL de la tienda sale de `$this->getShopContext()->getBaseURL()`, que usa HTTPS en las mismas condiciones que
    `Shop::getBaseURL(true)` (SSL activado y petición segura).
  - `trans()` usa la firma `(id, parámetros, dominio)` y los errores del formulario se muestran con
    `addFlashErrors()` (`flashErrors()` no existe en la clase nueva).
- `ConfigFormManager` recibe el traductor (`@translator`) por el constructor desde `config/services.yml`.
- `.htaccess` en la raíz que deniega el acceso directo a los `*.php`, como `ps_eventbus` o `psshipping`. El módulo no
  necesita que se abra ningún PHP directamente: el controlador frontal `link` se carga desde el `index.php` de
  PrestaShop.
- Cabecera de la licencia AFL-3.0 en `views/js/admin.js` y, por coherencia, en `views/css/admin.css` y en la plantilla
  Twig.
- `$tab->active = true`.

## Alternativas descartadas

- **Mantener PrestaShop 8** y quitar solo `Context` con los servicios del contenedor que existen en las dos versiones:
  el error de `FrameworkBundleAdminController` seguiría en el reporte y habría que justificarlo ante el revisor.
- **Elegir la clase base en tiempo de ejecución** (`class_alias` según exista `PrestaShopAdminController`): el
  Validator analiza el código y seguiría viendo `FrameworkBundleAdminController`, y las dos clases tienen firmas
  distintas de `trans()` y formas distintas de obtener servicios.

## Resultado

Probado en PrestaShop 9.1.4 con PHP 8.5: el contenedor registra `ConfigController` con
`controller.service_arguments`, la pantalla de configuración se abre con la URL de la tienda correcta y el formulario
muestra traducidos los errores de validación. Ya no aparece en el log el aviso de obsolescencia de `get()`. El
Validator dejó de dar estos errores en la segunda pasada (ver
[4.5](../04-publicacion-en-prestashop-addons.md#45-lo-que-aprendimos-del-validator)).

Prueba de extremo a extremo contra shopea.localhost con el código final, ya en verde en el Validator (03/11/2026, modo
debug activado):

- Opción 2 (UUID y token del ERP de pruebas): vincula con `shop-reactivated` y, al repetirla, con
  `shop-already-exists` sin crear otra tienda. La clave nace con los 25 permisos (solo GET, POST y PUT).
- **Comprobar conexión**, aviso de pedido (`actionOrderEdited` → `update-order received` y sincronización
  adelantada), y `MonitorShops` y `SyncShopOrders` solo para esa tienda, sin errores.
- **Desvincular tienda** (`unlink-shop`; la clave se conserva) y **Borrar datos del módulo** (solo lo local, sin
  ningún evento en shopea).
- Opción 1 desde «conectar tienda» (asistente nuevo de la rama `feature/connect-shop-plugins-como-shopify` de
  ShopeameServer, opción «El módulo me muestra la URL de la tienda y la clave del webservice»): crea la tienda, y el
  controlador `link` recibe y guarda las credenciales firmadas.
- Desinstalar borra la configuración, la clave, sus permisos, la pestaña y los hooks (el webservice sigue activo), y
  reinstalar los vuelve a crear.
- En `var/logs/dev-*.log` no hay ningún aviso del módulo: los avisos de obsolescencia que aparecen son del núcleo de
  PrestaShop y de Symfony/Doctrine con PHP 8.5.
