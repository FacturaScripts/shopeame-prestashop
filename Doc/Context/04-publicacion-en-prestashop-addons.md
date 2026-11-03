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
| Claves de configuración con el prefijo del módulo | `SHOPEAME_*` (antes había dos `SHOEAME_*`; `upgrade-1.2.0.php` las migra) |
| Sin tocar tablas del núcleo ni otros módulos | Solo `Configuration`, `Tab` y `WebserviceKey` mediante sus clases |
| Textos en inglés y traducibles | Dominio `Modules.Shopeame.Admin` y XLIFF en español (ver [0.3](00-reglas-de-trabajo.md)) |
| Sin funciones prohibidas (`serialize`, `eval`), depuración ni código comentado | Revisado |
| Llamadas externas autenticadas | El controlador `link` exige firma HMAC con la clave del webservice y caducidad de 300 s |
| Sin desactivar TLS | Se quitó `SHOPEAME_DEV`, que ponía `verify_peer => false` |
| Composer con `prepend-autoloader: false` y `vendor/` sin dependencias de desarrollo | `composer.json`; `build.sh` ejecuta `composer dump-autoload -o --no-dev` |
| Nada descargado tras la instalación | Todo va en el zip; el módulo solo envía avisos a shopea.me |
| `logo.png` de 32 × 32 | Sí |
| Sin avisos en modo debug | Probado en PrestaShop 9.1.4 con PHP 8.5 |
| Desinstalación limpia (recomendado) | Borra la configuración, la clave del webservice y la pestaña |

## 4.3 El zip

`~/Trabajos/FacturaScripts/scripts/build.sh` genera `zip-plugins/shopeame-prestashop.zip` con una sola carpeta
`shopeame`: copia el repositorio sin lo que lista `.distignore` (`Doc/`, `AGENTS.md`, `CLAUDE.md`, `README.md`,
`composer.lock`, `config*.xml` y archivos ocultos), regenera `vendor/` solo con el autoload, añade los `index.php` que
falten y normaliza permisos. Se llama `shopeame-prestashop.zip` para no pisar `Shopeame.zip` (el plugin de
FacturaScripts) en un disco que no distingue mayúsculas; al subirlo a Addons el nombre del zip no importa, pero la
carpeta de dentro sí debe ser `shopeame`.

## 4.4 Pendiente al enviar

- Pasar el zip por el Validator y corregir lo que diga.
- **Comentarios en español**: la lista de 1.7 dice que el código enviado debe estar en inglés. Los textos visibles y
  los identificadores ya lo están; los comentarios y Docblocks siguen en español por la regla del equipo. Si el revisor
  lo pide, hay que traducirlos.
- **Permisos del webservice**: la clave tiene todos los permisos sobre todos los recursos. Si el revisor lo cuestiona,
  hay que limitarlos a los recursos que usa ShopeameServer (productos, combinaciones, stock, precios, categorías,
  pedidos, clientes, direcciones...) y probar toda la sincronización.
- **Soporte y enlaces externos**: las directrices piden el soporte solo por Addons y no enlazar webs propias desde el
  módulo. El módulo enlaza a shopea.me porque es el servicio que conecta; si el revisor lo marca, se explica que sin
  shopea.me el módulo no tiene función.
- **Documentación en el zip**: las buenas prácticas recomiendan incluirla (en PDF, en `docs/`). Ahora no va; la ficha
  de Addons puede enlazar la documentación publicada.
- **PrestaShop 8**: el módulo declara compatibilidad 8.0–9.x y no se ha probado en una instalación 8. En PrestaShop 9
  `FrameworkBundleAdminController::get()` deja un aviso de obsolescencia (nivel INFO) en el log de depuración; los
  módulos oficiales lo evitan con `PrestaShopAdminController`, que solo existe desde la 9.0 (ver
  [decisión 0001](Decisiones/0001-adaptacion-a-prestashop-addons.md)).
- `module_key`: Addons lo asigna al aceptar el módulo y hay que añadirlo en el constructor
  (`$this->module_key = '...'`) en las versiones siguientes.
