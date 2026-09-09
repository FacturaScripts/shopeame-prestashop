<?php
/**
 * Actualización del módulo a la versión 1.1.0.
 *
 * Las instalaciones anteriores quedaron registradas en ps_module con la versión '1' o '0.0.1',
 * y su install() no llegó a registrar todos los hooks ni a crear la pestaña del BackOffice.
 * PrestaShop solo ejecuta un upgrade-X.Y.Z.php cuando esa versión es mayor que la instalada y
 * menor o igual que la del módulo, así que este archivo cubre ambos casos.
 *
 * https://devdocs.prestashop-project.org/9/modules/creation/enabling-auto-update/
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Registra los hooks de pedidos y recoloca la pestaña del menú.
 *
 * @param Shopeame $module
 * @return bool true si está todo ok
 */
function upgrade_module_1_1_0($module)
{
    // registerHook() es idempotente: devuelve true si el hook ya estaba registrado,
    // así que podemos lanzarlos todos sin comprobar nada antes
    $module->registerHook('actionValidateOrder');
    $module->registerHook('actionOrderStatusPostUpdate');
    $module->registerHook('actionOrderEdited');

    // la pestaña bajo «Configurar» no existía en las primeras versiones del módulo
    $module->installTab();

    return true;
}
