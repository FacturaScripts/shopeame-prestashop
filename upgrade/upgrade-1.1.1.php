<?php
/**
 * Actualización del módulo a la versión 1.1.1.
 *
 * La pestaña del menú se creaba sin route_name, así que PrestaShop enlazaba la primera ruta con
 * _legacy_link «Shopeame», que era la de generar la clave de API: entrar desde el menú borraba la
 * clave del webservice y creaba otra. Esta actualización guarda la ruta de la configuración.
 *
 * https://devdocs.prestashop-project.org/9/modules/creation/enabling-auto-update/
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Actualiza la pestaña del menú para que apunte a la página de configuración.
 *
 * @param Shopeame $module
 * @return bool true si está todo ok
 */
function upgrade_module_1_1_1($module)
{
    // installTab() actualiza la pestaña existente y le asigna el route_name de la configuración
    return $module->installTab();
}
