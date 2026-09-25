'use strict';

function fgJeuCorrespond(titre, mecanique, recherche, type) {
    const normaliser = texte => texte.toLocaleLowerCase('fr').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    return normaliser(titre).includes(normaliser(recherche.trim())) && (!type || mecanique === type);
}

if (typeof module !== 'undefined') module.exports = fgJeuCorrespond;
if (typeof document !== 'undefined') {
    const recherche = document.getElementById('rechercheJeux');
    const type = document.getElementById('filtreMecanique');
    const matieres = [...document.querySelectorAll('.fg-catalogue-matiere')];
    const matiereInitiale = matieres.find(matiere => !matiere.hidden)?.dataset.matiere;
    let categorie = matieres.find(matiere => matiere.dataset.matiere === matiereInitiale)
        ?.querySelector('.fg-rubriques button[aria-pressed="true"]')?.dataset.categorie || '';
    const liensJeux = [...document.querySelectorAll('[data-jeu], .mj-reprendre')];
    liensJeux.forEach(lien => { lien.dataset.baseHref = lien.getAttribute('href'); });

    function actualiserLiens(globale) {
        liensJeux.forEach(lien => {
            const url = new URL(lien.dataset.baseHref, location.href);
            ['matiere', 'rubrique', 'recherche'].forEach(cle => url.searchParams.delete(cle));
            if (!globale && matiereInitiale) url.searchParams.set('matiere', matiereInitiale);
            if (!globale && categorie) url.searchParams.set('rubrique', categorie);
            if (globale) url.searchParams.set('recherche', recherche.value.trim().slice(0, 60));
            lien.setAttribute('href', `${url.pathname.split('/').pop()}${url.search}`);
        });
    }

    function filtrer() {
        const globale = recherche.value.trim() !== '';
        const jeuxVisibles = new Set();
        matieres.forEach(matiere => {
            let visibles = 0;
            matiere.querySelectorAll('.mj-groupe').forEach(groupe => {
                let nombre = 0;
                groupe.querySelectorAll('[data-jeu]').forEach(jeu => {
                    jeu.hidden = !((globale || matiere.dataset.matiere === matiereInitiale)
                        && (globale || !categorie || groupe.dataset.categorie === categorie)
                        && fgJeuCorrespond(jeu.dataset.titre || '', jeu.dataset.mecanique, recherche.value, type.value));
                    if (!jeu.hidden) { nombre++; jeuxVisibles.add(jeu.dataset.jeu); }
                });
                groupe.hidden = nombre === 0;
                visibles += nombre;
            });
            matiere.hidden = globale ? visibles === 0 : matiere.dataset.matiere !== matiereInitiale;
            matiere.querySelector('.fg-rubriques').hidden = globale;
        });
        document.querySelectorAll('.fg-matiere-lien').forEach(lien => {
            if (!globale && lien.dataset.matiere === matiereInitiale) lien.setAttribute('aria-current', 'page');
            else lien.removeAttribute('aria-current');
        });
        const total = jeuxVisibles.size;
        document.getElementById('resultatsJeux').textContent = `${total} jeu${total > 1 ? 'x' : ''}${globale ? ' · toutes les matières' : ''}`;
        document.getElementById('aucunJeuFiltre').hidden = total !== 0;
        document.getElementById('aucunJeuMessage').textContent = globale
            ? `Aucun jeu trouvé pour « ${recherche.value.trim()} ».`
            : 'Aucun jeu ne correspond à ces filtres.';
        actualiserLiens(globale);
    }
    recherche.addEventListener('input', filtrer);
    recherche.form.addEventListener('submit', event => { event.preventDefault(); filtrer(); });
    type.addEventListener('change', filtrer);
    document.querySelectorAll('.fg-rubriques button').forEach(bouton => bouton.addEventListener('click', () => {
        categorie = bouton.dataset.categorie;
        bouton.parentElement.querySelectorAll('button').forEach(autre => autre.setAttribute('aria-pressed', String(autre === bouton)));
        filtrer();
    }));
    document.getElementById('effacerRechercheJeux').addEventListener('click', () => {
        recherche.value = ''; type.value = ''; categorie = '';
        document.querySelectorAll('.fg-rubriques button').forEach(bouton => bouton.setAttribute('aria-pressed', String(!bouton.dataset.categorie)));
        filtrer(); recherche.focus();
    });
    filtrer();
}
