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

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Actualización del módulo a la versión 1.2.0.
 *
 * - Renombra las claves de configuración SHOEAME_API_KEY y SHOEAME_SHOP_REGISTERED (con la errata
 *   en el prefijo) a SHOPEAME_API_KEY y SHOPEAME_SHOP_REGISTERED: PrestaShop exige que todas las
 *   claves lleven el nombre del módulo como prefijo.
 * - Registra el hook actionAdminControllerSetMedia, que pinta el logo de Shopeame en el menú.
 *
 * https://devdocs.prestashop-project.org/9/modules/creation/enabling-auto-update/
 *
 * @param Shopeame $module
 * @return bool
 */
function upgrade_module_1_2_0($module)
{
    $renamedKeys = [
        'SHOEAME_API_KEY' => 'SHOPEAME_API_KEY',
        'SHOEAME_SHOP_REGISTERED' => 'SHOPEAME_SHOP_REGISTERED',
    ];
    foreach ($renamedKeys as $oldKey => $newKey) {
        $value = Configuration::get($oldKey);
        if (false !== $value && false === Configuration::get($newKey)) {
            Configuration::updateValue($newKey, $value);
        }
        Configuration::deleteByName($oldKey);
    }

    return $module->registerHook(Shopeame::HOOKS);
}
