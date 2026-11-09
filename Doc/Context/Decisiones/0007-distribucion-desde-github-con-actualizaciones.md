# 0007. Distribución desde GitHub con actualizaciones en el gestor de módulos

- **Fecha**: 09/11/2026
- **Aprobada por**: el usuario («finalmente no se va a hacer», sobre publicar en las tiendas oficiales, y «modifica lo
  que tengas que modificar […] para que el cliente final pueda actualizar su aplicación directamente desde la
  aplicación de la tienda online»).

## Contexto

El módulo se preparó para PrestaShop Addons ([decisión 0001](0001-adaptacion-a-prestashop-addons.md)), pero se
distribuye solo desde las releases del repositorio público de GitHub. El gestor de módulos solo conoce las versiones
nuevas de los módulos de Addons y de los nativos (por `ps_distributionapiclient`): sin más, el cliente tendría que
entrar en GitHub, descargar el zip y subirlo a mano. La versión 1.0.0 no la había instalado nadie, así que se podía
cambiar sin `upgrade/`.

## Decisión

- Hook `actionListModules` en `Shopeame` con `name`, `version_available`, `download_url` y `changeLog`. Es el hook con
  el que PrestaShop 8 y 9 reúnen los módulos de otras fuentes: el gestor muestra la actualización, descarga el zip con
  `RemoteZipSourceHandler` y ejecuta los `upgrade/`. Se comprobó en el código de PrestaShop 9.1.4
  (`ModuleRepository::getModulesFromHook()`, `AdminModuleDataProvider` y `ModuleManager::upgrade()`), porque la
  documentación oficial solo lo describe para la Distribution API.
- `UpdateChecker` lee el `info.json` que la acción **Crear Release** adjunta a cada release, desde
  `releases/latest/download/info.json` (sin la API de GitHub ni token), y lo guarda 12 horas en la configuración.
- La pantalla de Shopeame muestra el aviso con **Actualizar ahora**, que llama a `ModuleManager::upgrade()` con el zip
  (lo mismo que el botón del gestor, con su comprobación de permisos), la versión instalada y **Buscar
  actualizaciones**.
- El zip anunciado es el `shopeame.zip` de la etiqueta y tiene que estar en las releases del repositorio.
- Se borra el material de la ficha de Addons (`Doc/Addons` y los PDF de `docs/`) y la comprobación de la versión de
  esos PDF en la acción **Crear Release** (el usuario: «Sí, bórralo»). La documentación de uso queda en `Doc/User`.

## Alternativas descartadas

- Seguir con Addons: el usuario decidió no publicar en las tiendas oficiales.
- Sustituir `ps_distributionapiclient` por un cliente propio: la documentación lo permite, pero quitaría las
  actualizaciones de los módulos nativos.
- Solo un aviso con enlace a GitHub o al gestor de módulos: el cliente tendría que descargar y subir el zip a mano.
- Descomprimir el zip con código propio: `ModuleManager::upgrade()` ya descarga, valida, descomprime, ejecuta los
  `upgrade/` y comprueba los permisos.
- Consultar `api.github.com`: límite de 60 peticiones por hora e IP sin token.

## Resultado

Probado en ps.localhost (PrestaShop 9.1.4, con y sin modo debug) con una versión 1.0.1 de prueba servida en local
mediante `SHOPEAME_UPDATE_INFO_URL`: la pestaña **Actualizaciones** del gestor muestra Shopeame con el changelog y su
botón **Actualizar** instala la 1.0.1; desde la pantalla de Shopeame, **Actualizar ahora** hace lo mismo y deja la
tienda vinculada; **Buscar actualizaciones** dice «Shopeame está actualizado» o avisa si no puede conectar con GitHub.
Sin avisos de `modules/shopeame` en `var/logs/dev-*.log`.
