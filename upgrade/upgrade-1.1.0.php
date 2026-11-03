<?php
/**
 * Shopeame - PrestaShop connector for shopea.me
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 *
 * @author    FacturaCity SL
 * @copyright 2025-2026 Carlos García Gómez
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

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
