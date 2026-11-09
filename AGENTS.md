Módulo **Shopeame** para PrestaShop 9: conecta una tienda PrestaShop con el servicio shopea.me, que hace de
intermediario entre las tiendas online y el ERP FacturaScripts del cliente. No está en PrestaShop Addons: se descarga
de las releases del repositorio público de GitHub (nombre técnico `shopeame`) y el gestor de módulos ofrece sus
versiones nuevas gracias al hook `actionListModules`.

Antes de tocar nada lee [`Doc/Context/README.md`](Doc/Context/README.md): es el índice del contexto (temas y
decisiones) y dice qué archivo abrir para cada tema; no hace falta leerlo todo. Las reglas de trabajo están en
[`Doc/Context/00-reglas-de-trabajo.md`](Doc/Context/00-reglas-de-trabajo.md): léelas siempre.

Lo que no se salta nunca:
- El otro extremo es el plugin **ShopeameServer** de FacturaScripts (`../facturascripts/Plugins/ShopeameServer`,
  controladores `WebHooks` y `ConnectShop` y clase `Lib/Shopeame/ShopLinkNotifier`): cualquier cambio en los eventos,
  los datos enviados o la firma se hace a la vez en los dos lados.
- Antes de hacer o explicar algo, contrasta la forma correcta en la documentación oficial de PrestaShop
  (https://devdocs.prestashop-project.org).
- El módulo mantiene los requisitos técnicos de la validación de PrestaShop Addons como buenas prácticas, y cada
  release de GitHub lleva el `info.json` del que dependen las actualizaciones: la versión se publica siempre con la
  acción **Crear Release** (ver
  [`Doc/Context/04-distribucion-y-actualizaciones.md`](Doc/Context/04-distribucion-y-actualizaciones.md)).

## Documentación

| Ruta | Para quién | Qué guarda |
|---|---|---|
| [`Doc/User/`](Doc/User/) | Quien usa el módulo | Páginas `.md` que explican cómo se usa |
| [`Doc/Dev/`](Doc/Dev/) | Quien programa | Páginas `.md` sobre el endpoint de vinculación, los eventos que envía y cómo desarrollarlo y publicarlo |
| [`Doc/Context/`](Doc/Context/) | El equipo y los agentes | Por qué el código es como es: las decisiones tomadas, con su índice en `README.md` |
| [`Doc/Assets/`](Doc/Assets/) | Toda la documentación | Imágenes enlazadas desde las páginas |
| [`Doc/forja.json`](Doc/forja.json) | La forja de facturascripts.com | Datos de cada página de `User` y `Dev` para publicarlas |

- La documentación se actualiza en el mismo trabajo que cambia el código; una decisión nueva se anota en
  `Doc/Context/Decisiones/` y en su índice.
- `Doc/`, `.github/`, `AGENTS.md`, `CLAUDE.md`, `README.md` y `composer.lock` no van en el zip del módulo
  (`.distignore`). La acción **Crear Release** (`.github/workflows/release.yml`) genera el mismo zip que `build.sh` y
  lo publica en GitHub con su `info.json` (ver [`Doc/Context/04-distribucion-y-actualizaciones.md`](Doc/Context/04-distribucion-y-actualizaciones.md)).
- `Doc/forja.json` agrupa en `User` y `Dev` las páginas de esas carpetas por su nombre de archivo, con campos
  opcionales: `title`, `title_short`, `permalink` (enlace en la web, máx. 200 caracteres; no se puede cambiar una vez
  publicada la página), `parent` (nombre de archivo de la página padre, con `.md`), `ordernum`, `youtubeid`,
  `imageurl`, `icon`, `color`, `keywords` y `controllers` (máx. 300 caracteres cada uno; `controllers`, separados por
  comas sin espacios) y `visibility` (`true` o `false`, sin comillas).
- `CLAUDE.md` solo importa este archivo (`@AGENTS.md`). Las instrucciones locales que no se versionan van en
  `CLAUDE.local.md` (Claude Code) o `AGENTS.override.md` (Codex).
