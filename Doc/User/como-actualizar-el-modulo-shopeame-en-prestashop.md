Cuando se publica una versión nueva del módulo **Shopeame**, PrestaShop la muestra en el gestor de módulos como la de
cualquier otro módulo, aunque Shopeame no esté en PrestaShop Addons: no hace falta entrar en GitHub ni volver a subir el
zip.

## ⚙️ Antes de empezar

- Entra en el panel de PrestaShop con un empleado que tenga permiso para gestionar módulos.
- El módulo busca versiones nuevas cada 12 horas. Si se acaba de publicar una, puede tardar en aparecer; para buscarla
  en ese momento, mira el último apartado.

## 🔔 Dónde aparece la versión nueva

1. **Página Shopeame** (**Configurar** → **Shopeame**): arriba aparece el aviso **Hay una versión nueva de Shopeame:
   …** con la versión instalada, la lista de cambios, el botón **Actualizar ahora** y el botón **Novedades**, que abre
   la página de la versión en GitHub.
2. **Módulos** → **Gestor de módulo** → pestaña **Actualizaciones**: Shopeame aparece con la etiqueta **Actualización
   disponible**, el paso de versión (por ejemplo, v1.0.0 → v1.0.1), la lista de cambios y el botón **Actualizar**.

## 🔄 Actualizar

- Desde la página Shopeame, pulsa **Actualizar ahora**. Al terminar verás **Shopeame se ha actualizado a la versión
  …** y, al pie, **Shopeame versión …** con la versión nueva.
- Desde la pestaña **Actualizaciones** del gestor de módulos, pulsa **Actualizar** en la tarjeta de Shopeame.
  PrestaShop pregunta si quieres actualizar el módulo y recomienda activar antes el modo mantenimiento; pulsa
  **Actualizar igualmente** o activa el mantenimiento primero.

En los dos casos PrestaShop descarga la versión nueva, sustituye la anterior y aplica los cambios que traiga. La tienda
sigue vinculada con shopea.me y la clave del webservice no cambia.

Si aparece **No se pudo actualizar Shopeame: …**, el mensaje indica el motivo (por ejemplo, que el empleado no tiene
permiso para actualizar módulos). También puedes descargar el zip de GitHub y subirlo en **Módulos** → **Gestor de
módulo** → **Subir un módulo**: PrestaShop detecta que ya está instalado y lo actualiza.

## 🔍 Buscar actualizaciones ahora

Al pie de la página Shopeame, pulsa **Buscar actualizaciones**. El módulo pregunta en ese momento por la última
versión:

- Si hay una nueva, aparece el aviso con **Actualizar ahora**.
- Si no, verás **Shopeame está actualizado.**
- Si no puede conectar, verás **No se pudo conectar con GitHub para buscar actualizaciones.** Vuelve a probar más
  tarde.

## Ver también

- [Cómo instalar el módulo Shopeame en PrestaShop](como-instalar-el-modulo-shopeame-en-prestashop.md)
- [Cómo resolver los errores de Shopeame en PrestaShop](como-resolver-los-errores-de-shopeame-en-prestashop.md)
