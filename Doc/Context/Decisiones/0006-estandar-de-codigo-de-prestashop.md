# 0006. Estándar de código de PrestaShop

- **Fecha**: 03/11/2026
- **Aprobada por**: el usuario, al pedir que se corrigieran los avisos «Standards» y después los errores de licencias
  del Validator, y al confirmar que la cuarta pasada salió en verde.

## Contexto

Con los errores ya corregidos ([decisión 0005](0005-solo-prestashop-9.md)), la segunda pasada del Validator dio 37
avisos en «Standards». El reporte en `.txt` no los detallaba. Son las reglas del estándar de código de PrestaShop, que
es php-cs-fixer con la configuración de `prestashop/php-dev-tools` (`@Symfony` más unos ajustes). En local salieron
38 correcciones en 21 archivos.

Tras aplicarlas, «Standards» quedó a cero, pero la comprobación de licencias dio 16 errores: «There must be no blank
lines before the file comment». La regla `blank_line_after_opening_tag` del estándar había puesto una línea en blanco
entre `<?php` y la cabecera de la licencia, y las dos comprobaciones del Validator se contradicen en ese punto. Los
módulos oficiales (`ps_mbo`, `blockwishlist`, `ps_facetedsearch`, `gsitemap`) ponen la cabecera justo después de
`<?php`.

## Decisión

- Los PHP siguen las reglas de `PrestaShop\CodingStandards\CsFixer\Config` **salvo `blank_line_after_opening_tag`**:
  la cabecera de la licencia va justo después de `<?php`.
- Las herramientas no se añaden al `composer.json` del módulo. Se instalan aparte y se pasan con la configuración de
  la [página de desarrollo](../../Dev/como-desarrollar-y-publicar-shopeame-para-prestashop.md#4-estándar-de-código-de-prestashop).
  Esa configuración copia las reglas a un `PhpCsFixer\Config` normal, porque la clase de PrestaShop sobrescribe
  `getRules()` y no deja desactivar reglas con `setRules()`.
- Las reglas que se notan al escribir código están en [0.2](../00-reglas-de-trabajo.md#02-convenciones-del-código).

## Alternativas descartadas

- **Añadir `prestashop/php-dev-tools` como `require-dev`** y un `.php-cs-fixer.dist.php` al repositorio, como hacen
  algunos módulos oficiales: obligaría a excluir del zip el archivo de configuración y a instalar las dependencias de
  desarrollo en `vendor/`, que `deploy.sh` copia a la tienda local. Queda como opción si se automatiza con una GitHub
  Action.
- **Dejar la línea en blanco tras `<?php`**: el Validator lo trata como error de licencias.

## Resultado

La cuarta pasada del Validator (03/11/2026) salió en verde en todas las secciones. Los cambios son solo de formato:
todos los PHP pasan `php -l`, y la pantalla de configuración y su formulario funcionan igual en PrestaShop 9.1.4.
