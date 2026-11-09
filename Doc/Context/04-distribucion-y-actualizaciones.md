# 4. Distribución y actualizaciones

El módulo **no se publica en PrestaShop Addons**: se descarga de las releases del repositorio público
https://github.com/FacturaScripts/shopeame-prestashop y se instala con **Módulos → Gestor de módulo → Subir un
módulo**. Las versiones nuevas aparecen en el gestor de módulos como las de cualquier otro módulo (ver
[decisión 0007](Decisiones/0007-distribucion-desde-github-con-actualizaciones.md)).

## 4.1 Cómo se actualiza

1. Cada release lleva un `info.json` (ver 4.2). `UpdateChecker` (`src/Update/UpdateChecker.php`) lo descarga de
   `.../releases/latest/download/info.json` y lo guarda en `SHOPEAME_UPDATE_INFO` con la hora en
   `SHOPEAME_UPDATE_CHECKED` (configuración global). Se reutiliza 12 horas (`CACHE_TTL`), porque el gestor de módulos
   ejecuta el hook en cada carga; si la consulta falla, se conserva el anterior y no se reintenta hasta que caduque.
2. `Shopeame::hookActionListModules()` devuelve `name`, `version_available`, `download_url` y `changeLog` cuando la
   versión publicada es mayor que la instalada y compatible (`ps_min`, `ps_max` y `php_min`). Es el hook con el que el
   núcleo reúne los módulos de otras fuentes (`ModuleRepository::getModulesFromHook()`, el mismo que usa
   `ps_distributionapiclient` para los módulos oficiales). Con él, **Módulos → Gestor de módulo → Actualizaciones**
   muestra Shopeame con «Actualización disponible», el changelog y el botón **Actualizar**, que descarga el zip
   (`RemoteZipSourceHandler`), lo descomprime y ejecuta los `upgrade/upgrade-X.Y.Z.php`.
3. La pantalla de Shopeame muestra el aviso «Hay una versión nueva…» con el changelog, **Actualizar ahora** (ruta
   `shopeame_update_module`, POST con token CSRF `shopeame_update`) y **Novedades** (la release en GitHub). La acción
   llama a `ModuleManager::upgrade('shopeame', <zip>)`, igual que el botón del gestor: el núcleo comprueba que el
   empleado puede actualizar módulos. Al pie se ven la versión instalada y **Buscar actualizaciones** (ruta
   `shopeame_check_updates`), que consulta GitHub en ese momento con `UpdateChecker::refresh()`.

Detalles:

- `download_url` tiene que terminar en `/shopeame.zip`: `RemoteZipSourceHandler` saca el nombre del módulo del nombre
  del archivo con `(\w+)\.zip`, así que `shopeame-1.0.1.zip` no serviría. Además tiene que empezar por
  `REPOSITORY_URL/releases/download/`, para que un `info.json` manipulado no instale un paquete de otro sitio.
- La constante `SHOPEAME_UPDATE_INFO_URL` (en `config/defines_custom.inc.php`) cambia la URL del `info.json` para
  pruebas y, solo entonces, admite cualquier URL de descarga (que siga terminando en `/shopeame.zip`).
- Una versión que añade hooks necesita su `upgrade/upgrade-X.Y.Z.php` que los registre. `actionListModules` no lo
  tiene porque la 1.0.0 no la había instalado nadie (como en la [decisión 0004](Decisiones/0004-primera-version-publica-1-0-0.md)).
- El gestor de módulos pide confirmar la actualización y recomienda el modo mantenimiento, como con cualquier módulo.

## 4.2 El zip y la release de GitHub

`~/Trabajos/FacturaScripts/scripts/build.sh` genera `zip-plugins/shopeame-prestashop-addons/shopeame.zip` con una
sola carpeta `shopeame`: copia el repositorio sin lo que lista `.distignore` (`Doc/`, `AGENTS.md`, `CLAUDE.md`,
`README.md`, `composer.lock`, `config*.xml` y archivos ocultos), regenera `vendor/` solo con el autoload, añade los
`index.php` que falten y normaliza permisos. Va en su propia carpeta para no pisar `Shopeame.zip` (el plugin de
FacturaScripts) en un disco que no distingue mayúsculas.

La acción de GitHub `.github/workflows/release.yml` (**Crear Release**, se lanza a mano) genera el mismo zip y lo
publica en una release `v<versión>` con tres archivos:

- `shopeame-<versión>.zip` y `shopeame.zip`. El segundo no cambia de nombre entre versiones para que
  `.../releases/latest/download/shopeame.zip` sea un enlace fijo a la última (es el que usa ShopeameServer).
- `info.json`: `version`, `download_url` (el `shopeame.zip` de esa etiqueta, no el de `latest`, para que el paquete
  coincida siempre con la versión anunciada), `details_url`, `homepage`, `released`, `ps_min` y `ps_max` (de
  `ps_versions_compliancy`), `php_min` (de `composer.json`) y `changelog` (las líneas de las notas). Se genera con `jq`.

Es la plantilla de release de fsmaker adaptada, igual que en shopeame-woocommerce:

- La versión sale de `$this->version` en `shopeame.php`. `config.xml` no se mira: es una caché que genera PrestaShop
  y no está en git.
- El módulo no tiene changelog, así que las notas son los commits desde la última etiqueta `v*` (todos, en la
  primera), agrupados por su tipo de Conventional Commits: `feat`, `fix` y el resto. Esas mismas líneas son el
  `changelog` del `info.json`.
- Reproduce el bloque del módulo de `build.sh` paso a paso (rsync con `.distignore` y sin `vendor`,
  `composer dump-autoload --optimize --no-dev` con PHP 8.1, `index.php` en las carpetas de `vendor/` y permisos
  755/644). Se comprobó en local que el zip tiene los mismos archivos, permisos y contenido que el de `build.sh`; solo
  cambia el sufijo aleatorio de la clase del autoload, que Composer genera de nuevo en cada `dump-autoload` porque el
  zip no lleva `composer.lock`.
- `.github` está en `.distignore` para que no vaya en el zip.

## 4.3 Requisitos técnicos que se mantienen

Se cumplían para la validación técnica de PrestaShop Addons y se mantienen como buenas prácticas (y por si algún día
se publica allí):

| Requisito | Cómo se cumple |
|---|---|
| Un módulo por zip, en una carpeta con el nombre técnico (`[a-z0-9]`) y el archivo principal con el mismo nombre | `shopeame/shopeame.php`; el zip lo genera `build.sh` |
| `index.php` en todas las carpetas | En el repositorio en cada carpeta; `build.sh` los añade también en `vendor/` |
| Licencia compatible con OSL/AFL (Apache, AFL, MIT, BSD, ISC, EUPL) | AFL-3.0 en `LICENSE`, `composer.json` y la cabecera de cada archivo |
| Comprobación de `_PS_VERSION_` en cada PHP | Todos los archivos salvo los `index.php` |
| Cabecera de licencia en los JS (Validator) | En los PHP, JS, CSS y Twig |
| `.htaccess` en la raíz para que no se pueda listar el módulo (Validator) | Deniega el acceso directo a los `*.php`, como `ps_eventbus` o `psshipping` |
| Sin `FrameworkBundleAdminController` ni `Context` en `src/` (Validator) | `ConfigController` extiende `PrestaShopAdminController`; el traductor y el form handler se inyectan y la URL sale de `ShopContext` (ver [decisión 0005](Decisiones/0005-solo-prestashop-9.md)) |
| Tipos correctos de las propiedades del núcleo (Validator) | `$tab->active = true` (bool, no int) |
| Estándar de código de PrestaShop (sección «Standards» del Validator) | php-cs-fixer con la configuración de `prestashop/php-dev-tools` salvo `blank_line_after_opening_tag`, sin nada que corregir (ver [0.2](00-reglas-de-trabajo.md)) |
| Claves de configuración con el prefijo del módulo | `SHOPEAME_*` |
| Sin tocar tablas del núcleo ni otros módulos | Solo `Configuration`, `Tab` y `WebserviceKey` mediante sus clases |
| Textos en inglés y traducibles | Dominios `Modules.Shopeame.Admin` (back-office) y `Modules.Shopeame.Shop` (Mi cuenta) con su XLIFF en español (ver [0.3](00-reglas-de-trabajo.md)) |
| Sin funciones prohibidas (`serialize`, `eval`), depuración ni código comentado | Revisado |
| Llamadas externas autenticadas | El controlador `link` exige firma HMAC con la clave del webservice y caducidad de 300 s |
| Controladores con permisos y CSRF | Las rutas de facturas del back-office exigen lectura sobre `AdminOrders` (`#[AdminSecurity]`) y el `_token` del empleado; el controlador frontal `invoices` exige cliente con sesión (`$auth`), su token (`Tools::getToken(false)`) y que el pedido sea suyo (ver [5. Facturas](05-facturas.md)) |
| Sin datos personales guardados de más | Las facturas del ERP no se guardan en la tienda: el PDF se reenvía al navegador |
| Sin desactivar TLS | Se quitó `SHOPEAME_DEV`, que ponía `verify_peer => false` |
| Composer con `prepend-autoloader: false` y `vendor/` sin dependencias de desarrollo | `composer.json`; `build.sh` ejecuta `composer dump-autoload -o --no-dev` |
| Nada descargado tras la instalación | Todo va en el zip. Lo único que se descarga es el `info.json` de GitHub y, cuando un empleado actualiza, el zip de la release, que instala el propio `ModuleManager` |
| `logo.png` de 32 × 32 | Sí |
| Sin avisos en modo debug | Probado en PrestaShop 9.1.4 con PHP 8.5 (sin el aviso de obsolescencia de `get()` que dejaba `FrameworkBundleAdminController`) |
| Desinstalación limpia (recomendado) | Borra la configuración (también `SHOPEAME_CUSTOMER_INVOICES`), la clave del webservice y la pestaña |
| Clave del webservice con los permisos justos | 18 recursos y solo los métodos que usa ShopeameServer, sin `DELETE` ni `PATCH` (ver [decisión 0003](Decisiones/0003-permisos-minimos-de-la-clave-del-webservice.md)) |

Pendiente, aunque ya no lo vaya a revisar Addons:

- **Lectura de `configurations`**: la clave solo necesita `PS_LANG_DEFAULT` y `PS_TIMEZONE`, pero el permiso GET deja
  leer toda la configuración, incluida `PS_MAIL_PASSWD`. Para quitarlo hay que cambiar los dos lados: que el módulo
  envíe esos dos valores y quitar el permiso (ver
  [decisión 0003](Decisiones/0003-permisos-minimos-de-la-clave-del-webservice.md)).

## 4.4 Lo que aprendimos del Validator

Pasadas de la versión 1.0.0 el 03/11/2026:

| Pasada | Resultado | Qué se hizo |
|---|---|---|
| 1 | Errores: `FrameworkBundleAdminController` y dos usos de `Context` en `src/`. Además, faltaba el `.htaccess`, `views/js/admin.js` no tenía cabecera de licencia y `$tab->active = 1` | Mínimo PrestaShop 9 con `PrestaShopAdminController`, `.htaccess`, cabeceras y `true` ([decisión 0005](Decisiones/0005-solo-prestashop-9.md)) |
| 2 | 37 avisos «Standards» | Estándar de código de PrestaShop con php-cs-fixer: 38 correcciones en local, en 21 archivos |
| 3 | «Standards» a cero, pero 16 errores de licencias: «There must be no blank lines before the file comment» | Quitar la línea en blanco que había puesto `blank_line_after_opening_tag` y desactivar esa regla ([decisión 0006](Decisiones/0006-estandar-de-codigo-de-prestashop.md)) |
| 4 | Todo en verde | Prueba de extremo a extremo contra shopea.localhost con el código final, sin fallos (ver el resultado de la [decisión 0005](Decisiones/0005-solo-prestashop-9.md#resultado)) |

- El reporte que se descarga en `.txt` puede llegar incompleto: en las dos primeras pasadas omitió la sección
  «Standards» («could not be rebuilt from stored data»). El detalle completo solo se ve en la web del Validator.
- El Validator da una API key al iniciar sesión, pero su API no está documentada públicamente (solo se menciona en su
  registro de cambios). Por eso el zip se sigue subiendo a mano.

## 4.5 Material de la ficha de Addons (borrado)

Para enviar el módulo a Addons se prepararon dos PDF de documentación (`docs/readme_es.pdf` y `docs/readme_en.pdf`, que
iban dentro del zip y cuya versión comprobaba la acción **Crear Release**), las imágenes de 1200 × 1200 de la ficha y
los scripts y capturas que los generaban (`Doc/Addons`). Al dejar de publicar en Addons se borraron del repositorio y
se quitó la comprobación de la acción (ver [decisión 0007](Decisiones/0007-distribucion-desde-github-con-actualizaciones.md)).
La documentación de uso está en las páginas de `Doc/User`. Si algún día se vuelve a Addons, siguen en el historial de
git.
