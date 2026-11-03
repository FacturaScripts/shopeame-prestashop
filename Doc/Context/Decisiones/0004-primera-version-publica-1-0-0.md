# 0004. Primera versión pública: 1.0.0

- **Fecha**: 03/11/2026
- **Aprobada por**: el usuario, al preguntar si tenía sentido empezar en la 1.0.0 porque el módulo aún no se ha
  publicado y nadie lo tiene instalado.

## Contexto

Durante el desarrollo el módulo llegó a la 1.2.0 (y se preparó una 1.2.1 para los permisos de la clave del
webservice, [decisión 0003](0003-permisos-minimos-de-la-clave-del-webservice.md)), con cuatro scripts en `upgrade/`:
registrar hooks y crear la pestaña (1.1.0), apuntar la pestaña a la configuración (1.1.1), renombrar las claves
`SHOEAME_*` a `SHOPEAME_*` y registrar `actionAdminControllerSetMedia` (1.2.0), y recortar los permisos de la clave
(1.2.1). Esos scripts solo se ejecutan en una tienda que ya tenga instalada una versión anterior, y no hay ninguna:
el módulo no se ha publicado en PrestaShop Addons ni se ha distribuido.

## Decisión

- `$this->version = '1.0.0'` en `shopeame.php`: es la primera versión que se envía a Addons.
- Se borra la carpeta `upgrade/`. `install()` ya registra todos los hooks (`Shopeame::HOOKS`), crea la pestaña y
  usa las claves `SHOPEAME_*`, y la clave del webservice nace con los permisos mínimos.
- Las siguientes versiones siguen la regla de [0.4](../00-reglas-de-trabajo.md): la que cambie hooks, pestañas,
  configuración o permisos de la clave lleva su `upgrade/upgrade-X.Y.Z.php` (y la carpeta vuelve con su `index.php`).

## Alternativas descartadas

- **Seguir en la 1.2.1**: el código de los upgrades no lo ejecutaría nunca nadie y obligaría a mantenerlo y a
  explicarlo en la revisión de Addons.

## Consecuencias

Una instalación con una versión de desarrollo (1.1.x o 1.2.x), como la tienda local `ps.localhost`, no se actualiza a
la 1.0.0 porque el número es menor: hay que desinstalar y volver a instalar el módulo, lo que borra la clave del
webservice y obliga a vincular de nuevo.
