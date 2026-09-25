(function () {
    'use strict';

    function mettreAJourCompteur(champ) {
        var compteur = document.querySelector('[data-compteur-pour="' + champ.id + '"]');
        if (compteur && champ.maxLength > -1) {
            compteur.textContent = champ.value.length + ' / ' + champ.maxLength;
        }
    }

    document.querySelectorAll('textarea[maxlength]').forEach(function (champ) {
        mettreAJourCompteur(champ);
        champ.addEventListener('input', function () { mettreAJourCompteur(champ); });
    });

    document.querySelectorAll('[data-symbole][data-cible]').forEach(function (bouton) {
        bouton.addEventListener('click', function () {
            var champ = document.getElementById(bouton.getAttribute('data-cible'));
            var symbole = bouton.getAttribute('data-symbole');
            if (!champ || !symbole) { return; }

            var debut = champ.selectionStart;
            var fin = champ.selectionEnd;
            var valeur = champ.value.slice(0, debut) + symbole + champ.value.slice(fin);
            if (champ.maxLength > -1) { valeur = valeur.slice(0, champ.maxLength); }
            champ.value = valeur;
            champ.focus();
            champ.selectionStart = champ.selectionEnd = Math.min(debut + symbole.length, champ.value.length);
            champ.dispatchEvent(new Event('input', { bubbles: true }));
        });
    });
}());
