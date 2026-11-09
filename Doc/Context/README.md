# Shopeame para PrestaShop: contexto del equipo

Esta carpeta guarda **por qué el código es como es**: las decisiones que no se ven leyendo una sola clase, para que
todo el equipo y los agentes trabajen con el mismo contexto. Lee este índice y abre solo el tema o la decisión que
necesites.

Se puede dividir en tantos archivos y subcarpetas como haga falta; este índice dice qué hay en cada uno y se actualiza
en el mismo trabajo que cambia el código.

## Temas

| Tema | Qué explica |
|---|---|
| [0. Reglas de trabajo](00-reglas-de-trabajo.md) | Documentación oficial, convenciones de PrestaShop, traducciones, versiones, entorno local y pruebas contra shopea.localhost |
| [1. Arquitectura](01-arquitectura.md) | Archivos del módulo, configuración guardada, hooks, rutas, menú y relación con ShopeameServer |
| [2. Vinculación con shopea.me](02-vinculacion.md) | La clave del webservice, las dos formas de vincular, el controlador firmado con HMAC, desvincular y borrar datos |
| [3. Pedidos](03-pedidos.md) | Qué se envía al crear o modificar un pedido, qué hace el servidor y dónde quedan los errores |
| [4. Distribución y actualizaciones](04-distribucion-y-actualizaciones.md) | Descarga desde GitHub, cómo el gestor de módulos ofrece las versiones nuevas (`actionListModules` e `info.json`), zip y release, requisitos técnicos que se mantienen, lo aprendido del Validator y el material de la ficha de Addons, ya borrado |

## Decisiones

| Registro | Fecha | Resumen |
|---|---|---|
| [0001. Adaptación a la validación técnica de PrestaShop Addons](Decisiones/0001-adaptacion-a-prestashop-addons.md) | 03/11/2026 | Licencia AFL-3.0, textos en inglés con traducción XLIFF al español, claves `SHOPEAME_*` con migración, verificación TLS siempre activa, `index.php` en cada carpeta, autoload en el archivo principal y desinstalación que limpia todo |
| [0002. Logo de Shopeame en el menú lateral](Decisiones/0002-logo-en-el-menu-lateral.md) | 03/11/2026 | El icono de la pestaña es una ligadura de Material Icons; una hoja de estilos mínima la sustituye por el logo como máscara SVG |
| [0003. Permisos mínimos de la clave del webservice](Decisiones/0003-permisos-minimos-de-la-clave-del-webservice.md) | 03/11/2026 | La clave solo tiene los 18 recursos y los métodos que usa ShopeameServer, sin `DELETE` ni `PATCH` |
| [0004. Primera versión pública: 1.0.0](Decisiones/0004-primera-version-publica-1-0-0.md) | 03/11/2026 | Como nadie tenía instalado el módulo, la primera versión que se publica es la 1.0.0 y se quitan los upgrades de las versiones internas |
| [0005. Solo PrestaShop 9](Decisiones/0005-solo-prestashop-9.md) | 03/11/2026 | Por el reporte del Validator, `ConfigController` pasa a `PrestaShopAdminController` sin `Context`, el mínimo sube a PrestaShop 9.0 y PHP 8.1, y se añaden el `.htaccess` y las cabeceras de licencia que faltaban |
| [0006. Estándar de código de PrestaShop](Decisiones/0006-estandar-de-codigo-de-prestashop.md) | 03/11/2026 | Los PHP siguen php-cs-fixer con las reglas de `prestashop/php-dev-tools` salvo `blank_line_after_opening_tag`, porque el Validator exige la cabecera de licencia justo después de `<?php` |
| [0007. Distribución desde GitHub con actualizaciones en el gestor de módulos](Decisiones/0007-distribucion-desde-github-con-actualizaciones.md) | 09/11/2026 | No se publica en Addons: hook `actionListModules` con el `info.json` de cada release para que el gestor de módulos ofrezca **Actualizar**, y aviso con **Actualizar ahora** en la pantalla de Shopeame |
