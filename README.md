Flujo del programa:
- Cuando le das al botón de configurar plugin, se ejecuta shopeame.php y llama a la función index del controlador
- Cuando se pulsa el botón de generar api se ejecuta el controlador con la función indicada
- Lo mismo para con el botón de registrar tienda

Las rutas están definidas en `config/routes.php`

El nombre de la carpeta tiene que ser **shopeame**

Todos los elementos que parecen redundantes como 
```php
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}
```
Son redundantes a nivel de programa pero necesarias a nivel de seguridad (según la documentación de prestashop).

Respecto a las traducciones, se gestionan de manera extraña, aquí está la documentación:
https://devdocs.prestashop-project.org/9/modules/creation/module-translation/new-system/
Las traducciones en prestashop funcionan de manera diferente, según leí o si no recuerdo mal
1. Se escribe la traducción en inglés
2. Prestashop autodetecta que faltan traducciones
3. En el panel creas las traducciones y te agrega en el plugin los archivos de traducción??????
4. Exportas el plugin con las traducciones que eligas