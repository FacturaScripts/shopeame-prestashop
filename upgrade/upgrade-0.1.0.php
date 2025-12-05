<?php
/**
 * Simplemente para activar el auto-upgrade,
 * esto activaría si upgradeas de una versión menor a 0.1.0 a una superior
 * https://devdocs.prestashop-project.org/9/modules/creation/enabling-auto-update/
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * La función que se ejecutaría con el auto-upgrade de la versión 0.1.0
 */
function upgrade_module_0_1_0($module) {
    // Aquí irían los cambios que se tengan que hacer
    return true; // devolver true si está todo ok
}
