/**
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
