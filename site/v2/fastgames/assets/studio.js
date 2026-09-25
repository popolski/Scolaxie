(function () {
    'use strict';

    var source = document.getElementById('fg-jeux-studio');
    var generer = document.getElementById('fg-generer');
    if (!source || !generer) {
        return;
    }

    var jeux = JSON.parse(source.textContent);
    var competence = document.getElementById('fg-competence');
    var recherche = document.getElementById('fg-recherche');
    var filtreType = document.getElementById('fg-type-reference');
    var etat = document.getElementById('fg-etat-referentiel');
    var vide = document.getElementById('fg-apercu-vide');
    var contenu = document.getElementById('fg-apercu-contenu');
    var apercu = document.getElementById('fg-apercu');
    var references = {};
    var minuteur = null;
    var controleur = null;

    function formatChoisi() {
        return document.querySelector('input[name="format"]:checked').value;
    }

    function afficherApercu() {
        var id = formatChoisi();
        var jeu = jeux[id];
        var reference = references[competence.value];
        if (!reference) {
            return;
        }
        var carte = contenu.querySelector('.fg-carte-jeu');
        vide.hidden = true;
        contenu.hidden = false;
        carte.className = 'fg-carte-jeu fg-ton-' + jeu.ton;
        document.getElementById('fg-apercu-icone').className = 'fg-icone fg-icone-' + id;
        document.getElementById('fg-apercu-meta').textContent = [reference.matiere, reference.categorie].filter(Boolean).join(' · ');
        document.getElementById('fg-apercu-competence').textContent = reference.libelle;
        document.getElementById('fg-apercu-format').textContent = 'Format choisi : ' + jeu.titre;
        document.getElementById('fg-apercu-description').textContent = jeu.description;
        document.getElementById('fg-apercu-code').textContent = (reference.type === 'competence' ? 'Compétence' : 'Connaissance') + (reference.code ? ' · ' + reference.code : '');
        document.getElementById('fg-tester').href = 'jeu.php?jeu=' + encodeURIComponent(id) + '&apercu=eleve&ref=' + encodeURIComponent(reference.type + ':' + reference.id);
        apercu.classList.add('est-rempli');
    }

    function libelleOption(reference) {
        var type = reference.type === 'competence' ? 'Compétence' : 'Connaissance';
        var chemin = [reference.matiere, reference.categorie].filter(Boolean).join(' › ');
        return '[' + type + '] ' + (chemin ? chemin + ' - ' : '') + reference.libelle;
    }

    function chargerReferentiel() {
        if (controleur) {
            controleur.abort();
        }
        controleur = new AbortController();
        etat.textContent = 'Recherche dans Fast Éval…';
        competence.disabled = true;
        generer.disabled = true;
        var url = '/v2/fastgames/api/referentiel.php?q=' + encodeURIComponent(recherche.value.trim()) + '&type=' + encodeURIComponent(filtreType.value);
        fetch(url, {credentials: 'same-origin', signal: controleur.signal})
            .then(function (reponse) {
                if (!reponse.ok) {
                    throw new Error('Réponse invalide');
                }
                return reponse.json();
            })
            .then(function (donnees) {
                references = {};
                competence.innerHTML = '';
                donnees.resultats.forEach(function (reference) {
                    var cle = reference.type + ':' + reference.id;
                    references[cle] = reference;
                    var option = document.createElement('option');
                    option.value = cle;
                    option.textContent = libelleOption(reference);
                    competence.appendChild(option);
                });
                var affiches = donnees.resultats.length;
                etat.textContent = donnees.total
                    ? affiches + ' résultat' + (affiches > 1 ? 's' : '') + ' affiché' + (affiches > 1 ? 's' : '') + ' sur ' + donnees.total + '. Affinez la recherche si nécessaire.'
                    : 'Aucune entrée du référentiel ne correspond à cette recherche.';
                competence.disabled = affiches === 0;
                generer.disabled = affiches === 0;
            })
            .catch(function (erreur) {
                if (erreur.name === 'AbortError') {
                    return;
                }
                etat.textContent = 'Le référentiel est momentanément indisponible.';
                competence.innerHTML = '';
                competence.disabled = true;
                generer.disabled = true;
            });
    }

    generer.addEventListener('click', afficherApercu);
    recherche.addEventListener('input', function () {
        window.clearTimeout(minuteur);
        minuteur = window.setTimeout(chargerReferentiel, 280);
    });
    filtreType.addEventListener('change', chargerReferentiel);
    competence.addEventListener('change', function () {
        generer.disabled = !references[competence.value];
    });
    document.getElementById('fg-modifier').addEventListener('click', function () {
        contenu.hidden = true;
        vide.hidden = false;
        apercu.classList.remove('est-rempli');
        competence.focus();
    });
    chargerReferentiel();
}());
