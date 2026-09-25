(function () {
    'use strict';

    document.querySelectorAll('form[data-confirmation]').forEach(function (formulaire) {
        formulaire.addEventListener('submit', function (event) {
            var message = formulaire.getAttribute('data-confirmation') || 'Confirmer la suppression ?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });
}());
