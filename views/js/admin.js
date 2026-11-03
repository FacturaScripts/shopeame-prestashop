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

/*
 * Comportamiento de la pantalla de administración de Shopeame.
 *
 * - Botones "Copiar": copian al portapapeles el valor del campo indicado en data-shopeame-copy.
 * - Formularios con data-shopeame-confirm: piden confirmación antes de enviarse.
 */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-shopeame-copy]').forEach(function (button) {
        button.addEventListener('click', function () {
            var field = document.getElementById(button.getAttribute('data-shopeame-copy'));
            if (!field || !navigator.clipboard) {
                return;
            }

            var label = button.textContent;
            navigator.clipboard.writeText(field.textContent.trim()).then(function () {
                // indicamos brevemente que se ha copiado y restauramos el texto original
                button.textContent = button.getAttribute('data-shopeame-copied') || label;
                setTimeout(function () {
                    button.textContent = label;
                }, 1500);
            });
        });
    });

    document.querySelectorAll('form[data-shopeame-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.getAttribute('data-shopeame-confirm'))) {
                event.preventDefault();
            }
        });
    });
});
