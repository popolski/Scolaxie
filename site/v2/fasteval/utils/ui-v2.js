(function () {
    'use strict';

    document.querySelectorAll('details').forEach(function (details) {
        details.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape' || !details.open) {
                return;
            }

            event.preventDefault();
            details.open = false;

            var summary = details.querySelector('summary');
            if (summary) {
                summary.focus();
            }
        });
    });

    var tableEleves = document.querySelector('[data-liste-eleves]');
    var rechercheEleves = document.getElementById('rechercheEleves');
    var filtreAccesEleves = document.getElementById('filtreAccesEleves');

    if (tableEleves && rechercheEleves && filtreAccesEleves) {
        var lignesEleves = Array.prototype.slice.call(tableEleves.querySelectorAll('tbody tr[data-recherche]'));
        var compteurEleves = document.getElementById('compteurElevesFiltres');
        var texteResultats = document.getElementById('texteResultatsEleves');
        var aucunResultat = document.getElementById('aucunEleveFiltre');

        function normaliser(texte) {
            return texte.toLocaleLowerCase('fr').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }

        function filtrerEleves() {
            var recherche = normaliser(rechercheEleves.value.trim());
            var acces = filtreAccesEleves.value;
            var visibles = 0;

            lignesEleves.forEach(function (ligne) {
                var correspondAuNom = !recherche || normaliser(ligne.dataset.recherche || '').indexOf(recherche) !== -1;
                var correspondALAcces = acces === 'tous' || ligne.dataset.acces === acces;
                ligne.hidden = !(correspondAuNom && correspondALAcces);
                if (!ligne.hidden) { visibles += 1; }
            });

            if (compteurEleves) { compteurEleves.textContent = String(visibles); }
            if (texteResultats) {
                texteResultats.textContent = visibles === lignesEleves.length
                    ? visibles + ' élève' + (visibles > 1 ? 's' : '')
                    : visibles + ' sur ' + lignesEleves.length + ' élève' + (lignesEleves.length > 1 ? 's' : '');
            }
            if (aucunResultat) { aucunResultat.hidden = visibles !== 0; }
        }

        rechercheEleves.addEventListener('input', filtrerEleves);
        filtreAccesEleves.addEventListener('change', filtrerEleves);
    }
}());
