# 4. Publicación en PrestaShop Addons

## 4.1 Fuentes

- Lista de comprobación de la validación técnica: https://devdocs.prestashop-project.org/1.7/modules/sell/techvalidation-checklist/
  (solo existe en la sección 1.7; en las de 8 y 9 no hay sección «Selling your modules»).
- Buenas prácticas: https://devdocs.prestashop-project.org/9/modules/creation/good-practices/
- Composer en módulos: https://devdocs.prestashop-project.org/9/modules/concepts/composer/
- Validator: https://validator.prestashop.com (acepta el zip o un repositorio público de GitHub). Es el primer filtro:
  algunos de sus avisos rechazan el envío automáticamente. Después el equipo de PrestaShop revisa el código a mano con
  el modo debug activado: cualquier aviso de PHP es motivo de rechazo.

## 4.2 Requisitos que cumple el módulo

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
| Textos en inglés y traducibles | Dominio `Modules.Shopeame.Admin` y XLIFF en español (ver [0.3](00-reglas-de-trabajo.md)) |
| Sin funciones prohibidas (`serialize`, `eval`), depuración ni código comentado | Revisado |
| Llamadas externas autenticadas | El controlador `link` exige firma HMAC con la clave del webservice y caducidad de 300 s |
| Sin desactivar TLS | Se quitó `SHOPEAME_DEV`, que ponía `verify_peer => false` |
| Composer con `prepend-autoloader: false` y `vendor/` sin dependencias de desarrollo | `composer.json`; `build.sh` ejecuta `composer dump-autoload -o --no-dev` |
| Nada descargado tras la instalación | Todo va en el zip; el módulo solo envía avisos a shopea.me |
| `logo.png` de 32 × 32 | Sí |
| Sin avisos en modo debug | Probado en PrestaShop 9.1.4 con PHP 8.5 (sin el aviso de obsolescencia de `get()` que dejaba `FrameworkBundleAdminController`) |
| Desinstalación limpia (recomendado) | Borra la configuración, la clave del webservice y la pestaña |
| Clave del webservice con los permisos justos | 17 recursos y solo los métodos que usa ShopeameServer, sin `DELETE` ni `PATCH` (ver [decisión 0003](Decisiones/0003-permisos-minimos-de-la-clave-del-webservice.md)) |

## 4.3 El zip

`~/Trabajos/FacturaScripts/scripts/build.sh` genera `zip-plugins/shopeame-prestashop.zip` con una sola carpeta
`shopeame`: copia el repositorio sin lo que lista `.distignore` (`Doc/`, `AGENTS.md`, `CLAUDE.md`, `README.md`,
`composer.lock`, `config*.xml` y archivos ocultos), regenera `vendor/` solo con el autoload, añade los `index.php` que
falten y normaliza permisos. Se llama `shopeame-prestashop.zip` para no pisar `Shopeame.zip` (el plugin de
FacturaScripts) en un disco que no distingue mayúsculas; al subirlo a Addons el nombre del zip no importa, pero la
carpeta de dentro sí debe ser `shopeame`.

## 4.4 Pendiente al enviar

- **Validator**: la versión 1.0.0 lo pasa sin errores ni avisos en ninguna sección (03/11/2026, cuarta pasada; ver
  4.5). En cada versión nueva hay que pasar antes el estándar en local y después el zip por el Validator.
- **Comentarios en español**: la lista de 1.7 dice que el código enviado debe estar en inglés. Los textos visibles y
  los identificadores ya lo están; los comentarios y Docblocks siguen en español por la regla del equipo. Si el revisor
  lo pide, hay que traducirlos.
- **Lectura de `configurations`**: la clave solo necesita `PS_LANG_DEFAULT` y `PS_TIMEZONE`, pero el permiso GET deja
  leer toda la configuración, incluida `PS_MAIL_PASSWD`. Si el revisor lo cuestiona, hay que cambiar los dos lados
  para que el módulo envíe esos dos valores y quitar el permiso (ver
  [decisión 0003](Decisiones/0003-permisos-minimos-de-la-clave-del-webservice.md)).
- **Soporte y enlaces externos**: las directrices piden el soporte solo por Addons y no enlazar webs propias desde el
  módulo. El módulo enlaza a shopea.me porque es el servicio que conecta; si el revisor lo marca, se explica que sin
  shopea.me el módulo no tiene función.
- **Documentación en el zip**: las buenas prácticas recomiendan incluirla (en PDF, en `docs/`). Ahora no va; la ficha
  de Addons puede enlazar la documentación publicada.
- **Solo PrestaShop 9**: desde la [decisión 0005](Decisiones/0005-solo-prestashop-9.md) el módulo pide PrestaShop
  9.0 o superior. En la ficha de Addons hay que marcar solo las versiones 9.x.
- `module_key`: Addons lo asigna al aceptar el módulo y hay que añadirlo en el constructor
  (`$this->module_key = '...'`) en las versiones siguientes.

## 4.5 Lo que aprendimos del Validator

Pasadas de la versión 1.0.0 el 03/11/2026:

| Pasada | Resultado | Qué se hizo |
|---|---|---|
| 1 | Errores: `FrameworkBundleAdminController` y dos usos de `Context` en `src/`. Además, faltaba el `.htaccess`, `views/js/admin.js` no tenía cabecera de licencia y `$tab->active = 1` | Mínimo PrestaShop 9 con `PrestaShopAdminController`, `.htaccess`, cabeceras y `true` ([decisión 0005](Decisiones/0005-solo-prestashop-9.md)) |
| 2 | 37 avisos «Standards» | Estándar de código de PrestaShop con php-cs-fixer: 38 correcciones en local, en 21 archivos |
| 3 | «Standards» a cero, pero 16 errores de licencias: «There must be no blank lines before the file comment» | Quitar la línea en blanco que había puesto `blank_line_after_opening_tag` y desactivar esa regla ([decisión 0006](Decisiones/0006-estandar-de-codigo-de-prestashop.md)) |
| 4 | Todo en verde | — |

- El reporte que se descarga en `.txt` puede llegar incompleto: en las dos primeras pasadas omitió la sección
  «Standards» («could not be rebuilt from stored data»). El detalle completo solo se ve en la web del Validator.
- El Validator da una API key al iniciar sesión, pero su API no está documentada públicamente (solo se menciona en su
  registro de cambios). Por eso el zip se sigue subiendo a mano.
