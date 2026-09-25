/* Rangement manuel des compétences par glisser-déposer.
 *
 * Chargé uniquement quand la page a décidé que l'utilisateur peut ranger
 * (compte administrateur, tri manuel, ordre croissant). Le script ne
 * cherche pas à le redeviner : il regarde s'il y a des poignées.
 *
 * Aucune bibliothèque : le glisser-déposer natif du navigateur suffit ici,
 * et le site n'a pas de jQuery UI. Contrepartie assumée : sur tablette, le
 * glisser natif ne fonctionne pas. À revoir le jour où l’enseignante rangera
 * depuis un écran tactile.
 *
 * Règle de fond : une ligne ne peut se déplacer QUE dans sa propre
 * catégorie. La liste est groupée par matière puis catégorie, et un ordre
 * qui traverserait ces groupes n'aurait aucun sens à l'affichage.
 */
(function () {
	'use strict';

	var tableau = document.querySelector('.tableau-groupe');
	if (!tableau) { return; }
	if (!tableau.querySelector('.poignee-rangement')) { return; }

	var etat = document.querySelector('.etat-rangement');
	var ligneTiree = null;

	function afficher(texte, classe) {
		if (!etat) { return; }
		etat.textContent = texte;
		etat.className = 'etat-rangement' + (classe ? ' ' + classe : '');
	}

	function memeCategorie(a, b) {
		return a && b
			&& a.getAttribute('data-groupe') === b.getAttribute('data-groupe')
			&& a.getAttribute('data-categorie') === b.getAttribute('data-categorie');
	}

	function effacerReperes() {
		var marquees = tableau.querySelectorAll('.cible-avant, .cible-apres');
		for (var i = 0; i < marquees.length; i++) {
			marquees[i].classList.remove('cible-avant', 'cible-apres');
		}
	}

	// Toutes les lignes de la même catégorie, dans leur ordre d'affichage
	// actuel : c'est exactement ce qu'on enverra au serveur.
	function lignesDeLaCategorie(ligne) {
		var toutes = tableau.querySelectorAll('.ligne-donnee');
		var retenues = [];
		for (var i = 0; i < toutes.length; i++) {
			if (memeCategorie(toutes[i], ligne)) { retenues.push(toutes[i]); }
		}
		return retenues;
	}

	function enregistrer(ligne) {
		var lignes = lignesDeLaCategorie(ligne);
		var corps = 'table=competences';
		for (var i = 0; i < lignes.length; i++) {
			corps += '&ids%5B%5D=' + encodeURIComponent(lignes[i].getAttribute('data-id'));
		}

		afficher('Enregistrement...');

		var requete = new XMLHttpRequest();
		requete.open('POST', 'enregistrer-ordre-competences.php', true);
		requete.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		requete.onload = function () {
			var reponse = null;
			try { reponse = JSON.parse(requete.responseText); } catch (e) { reponse = null; }
			if (reponse && reponse.ok) {
				afficher('Ordre enregistré.', 'succes');
			} else {
				afficher(
					(reponse && reponse.message) ? reponse.message : 'Enregistrement impossible.',
					'echec'
				);
			}
		};
		requete.onerror = function () {
			afficher('Serveur injoignable, l’ordre n’est pas enregistré.', 'echec');
		};
		requete.send(corps);
	}

	// --- mise en place ----------------------------------------------------
	var poignees = tableau.querySelectorAll('.poignee-rangement');
	for (var i = 0; i < poignees.length; i++) {
		(function (poignee) {
			var ligne = poignee.closest('tr');
			if (!ligne) { return; }
			// La ligne n'est rendue déplaçable qu'au moment où l'on saisit la
			// poignée : sinon le moindre glissement sur le texte d'une cellule
			// emporte la ligne, et on ne peut plus sélectionner un intitulé.
			poignee.addEventListener('mousedown', function () { ligne.draggable = true; });
			poignee.addEventListener('mouseup', function () { ligne.draggable = false; });
		}(poignees[i]));
	}

	tableau.addEventListener('dragstart', function (evenement) {
		var ligne = evenement.target.closest ? evenement.target.closest('.ligne-donnee') : null;
		if (!ligne) { return; }
		ligneTiree = ligne;
		ligne.classList.add('en-deplacement');
		if (evenement.dataTransfer) {
			evenement.dataTransfer.effectAllowed = 'move';
			// Firefox refuse de démarrer un glisser sans donnée transportée.
			evenement.dataTransfer.setData('text/plain', ligne.getAttribute('data-id'));
		}
	});

	tableau.addEventListener('dragover', function (evenement) {
		if (!ligneTiree) { return; }
		var cible = evenement.target.closest ? evenement.target.closest('.ligne-donnee') : null;
		if (!cible || cible === ligneTiree || !memeCategorie(cible, ligneTiree)) { return; }

		evenement.preventDefault();
		effacerReperes();
		var cadre = cible.getBoundingClientRect();
		var avant = (evenement.clientY - cadre.top) < (cadre.height / 2);
		cible.classList.add(avant ? 'cible-avant' : 'cible-apres');
	});

	tableau.addEventListener('drop', function (evenement) {
		if (!ligneTiree) { return; }
		var cible = evenement.target.closest ? evenement.target.closest('.ligne-donnee') : null;
		if (!cible || cible === ligneTiree || !memeCategorie(cible, ligneTiree)) { return; }

		evenement.preventDefault();
		var cadre = cible.getBoundingClientRect();
		var avant = (evenement.clientY - cadre.top) < (cadre.height / 2);
		if (avant) {
			cible.parentNode.insertBefore(ligneTiree, cible);
		} else {
			cible.parentNode.insertBefore(ligneTiree, cible.nextSibling);
		}
		effacerReperes();
		enregistrer(ligneTiree);
	});

	tableau.addEventListener('dragend', function () {
		if (ligneTiree) {
			ligneTiree.classList.remove('en-deplacement');
			ligneTiree.draggable = false;
		}
		effacerReperes();
		ligneTiree = null;
	});

	// ----------------------------------------------------------------------
	// Défilement automatique en approchant du bord, pendant un glisser
	// ----------------------------------------------------------------------
	// PENDANT UN GLISSER NATIF, LA MOLETTE NE FONCTIONNE PAS : le navigateur
	// confisque les entrées le temps de l'opération et n'envoie aucun événement
	// `wheel` à la page. Demande de le responsable technique le 06/09/2026 - remonter une ligne
	// dans une longue catégorie était impossible sans lâcher la poignée.
	// Le seul signal disponible est la position du pointeur, donnée par
	// `dragover` : on défile donc quand il approche du haut ou du bas.
	//
	// La vitesse suit la profondeur d'entrée dans la marge, plutôt qu'une valeur
	// fixe : on effleure le bord pour avancer doucement, on s'y enfonce pour
	// aller vite, et on garde le contrôle du geste.
	var MARGE_DEFILEMENT = 110;
	var vitesseDefilement = 0;
	var animationDefilement = null;

	function pasDeDefilement() {
		if (vitesseDefilement === 0) { animationDefilement = null; return; }
		window.scrollBy(0, vitesseDefilement);
		animationDefilement = window.requestAnimationFrame(pasDeDefilement);
	}

	function reglerDefilement(clientY) {
		// `categorieTiree` est déclarée plus bas ; `var` la remonte, donc elle
		// vaut `undefined` tant que le bloc des catégories n'est pas atteint -
		// faux, ce qui est exactement le comportement voulu.
		if (!ligneTiree && !categorieTiree) { vitesseDefilement = 0; return; }
		var hauteur = window.innerHeight;
		if (clientY < MARGE_DEFILEMENT) {
			vitesseDefilement = -Math.max(3, Math.round((MARGE_DEFILEMENT - clientY) / 5));
		} else if (clientY > hauteur - MARGE_DEFILEMENT) {
			vitesseDefilement = Math.max(3, Math.round((clientY - (hauteur - MARGE_DEFILEMENT)) / 5));
		} else {
			vitesseDefilement = 0;
		}
		if (vitesseDefilement !== 0 && animationDefilement === null) {
			animationDefilement = window.requestAnimationFrame(pasDeDefilement);
		}
	}

	function arreterDefilement() { vitesseDefilement = 0; dernierGlisser = Date.now(); }

	// Sur `document` et non sur le tableau : au bord de la fenêtre, le pointeur
	// est souvent déjà sorti du tableau, et c'est précisément là qu'on a besoin
	// de défiler.
	document.addEventListener('dragover', function (evenement) { reglerDefilement(evenement.clientY); });
	document.addEventListener('dragend', arreterDefilement);
	document.addEventListener('drop', arreterDefilement);

	// ----------------------------------------------------------------------
	// « Déplacer vers » : ranger sans glisser
	// ----------------------------------------------------------------------
	// Le glisser reste le geste court. Pour remonter de quarante rangs dans une
	// catégorie qui en compte cent dix, il ne suffit pas, même avec le
	// défilement automatique : on demande donc la position visée.
	// Bénéfice de bord, non demandé mais gratuit : ce chemin fonctionne au
	// clavier et sur tablette, là où le glisser natif ne marche pas du tout.
	var menuOuvert = null;
	var dernierGlisser = 0;

	function fermerMenu() {
		if (menuOuvert && menuOuvert.parentNode) { menuOuvert.parentNode.removeChild(menuOuvert); }
		menuOuvert = null;
	}

	function deplacerVers(ligne, position) {
		var lignes = lignesDeLaCategorie(ligne);
		var depart = lignes.indexOf(ligne);
		var cible = Math.max(0, Math.min(lignes.length - 1, position));
		if (depart === cible) { fermerMenu(); return; }

		// On retire la ligne de la liste avant de choisir son voisin, sinon
		// viser « la place 5 » depuis la place 2 compterait la ligne elle-meme
		// et la poserait un rang trop haut.
		lignes.splice(depart, 1);
		if (cible >= lignes.length) {
			lignes[lignes.length - 1].parentNode.insertBefore(ligne, lignes[lignes.length - 1].nextSibling);
		} else {
			lignes[cible].parentNode.insertBefore(ligne, lignes[cible]);
		}
		fermerMenu();
		ligne.classList.add('est-mise-en-avant');
		enregistrer(ligne);
	}

	function ouvrirMenu(poignee, ligne) {
		fermerMenu();
		var lignes = lignesDeLaCategorie(ligne);
		var rang = lignes.indexOf(ligne) + 1;
		var intitule = ligne.querySelector('td:nth-child(2)');

		var menu = document.createElement('div');
		menu.className = 'menu-position';
		menu.setAttribute('role', 'dialog');
		menu.setAttribute('aria-label', 'Déplacer cette ligne dans sa catégorie');
		menu.innerHTML =
			'<p class="menu-position-titre"></p>'
			+ '<div class="menu-position-rapide">'
			+ '<button type="button" data-vers="haut">Tout en haut</button>'
			+ '<button type="button" data-vers="bas">Tout en bas</button>'
			+ '</div>'
			+ '<label class="menu-position-champ">Position <input type="number" min="1" max="' + lignes.length + '" value="' + rang + '"></label>'
			+ '<div class="menu-position-actions">'
			+ '<button type="button" class="btn btn-sm btn-color" data-vers="numero">Déplacer</button>'
			+ '<button type="button" class="btn btn-sm btn-secondary" data-vers="annuler">Annuler</button>'
			+ '</div>';
		menu.querySelector('.menu-position-titre').textContent =
			'Place ' + rang + ' sur ' + lignes.length + ' dans cette catégorie.';

		poignee.parentNode.appendChild(menu);
		menuOuvert = menu;
		var champ = menu.querySelector('input');
		champ.focus();
		champ.select();

		menu.addEventListener('click', function (evenement) {
			var bouton = evenement.target.closest ? evenement.target.closest('[data-vers]') : null;
			if (!bouton) { return; }
			var vers = bouton.getAttribute('data-vers');
			if (vers === 'annuler') { fermerMenu(); return; }
			if (vers === 'haut') { deplacerVers(ligne, 0); return; }
			if (vers === 'bas') { deplacerVers(ligne, lignes.length - 1); return; }
			var demandee = parseInt(champ.value, 10);
			if (isNaN(demandee)) { fermerMenu(); return; }
			deplacerVers(ligne, demandee - 1);
		});
		// Entrée vaut « Déplacer », Échap vaut « Annuler » : on ne quitte pas le
		// clavier pour valider un chiffre qu'on vient d'y taper.
		menu.addEventListener('keydown', function (evenement) {
			if (evenement.key === 'Enter') {
				evenement.preventDefault();
				var demandee = parseInt(champ.value, 10);
				if (!isNaN(demandee)) { deplacerVers(ligne, demandee - 1); }
			} else if (evenement.key === 'Escape') {
				evenement.preventDefault();
				fermerMenu();
				poignee.focus();
			}
		});
	}

	for (var p = 0; p < poignees.length; p++) {
		(function (poignee) {
			var ligne = poignee.closest('tr');
			if (!ligne || !ligne.classList.contains('ligne-donnee')) { return; }
			// La poignée devient atteignable au clavier et annonce ce qu'elle
			// fait : jusqu'ici c'était un caractère décoratif, invisible pour
			// qui n'a pas de souris.
			poignee.setAttribute('tabindex', '0');
			poignee.setAttribute('role', 'button');
			poignee.removeAttribute('aria-hidden');
			poignee.setAttribute('title', 'Glisser pour déplacer, ou cliquer pour choisir une position');

			poignee.addEventListener('click', function (evenement) {
				evenement.preventDefault();
				// Un glisser qui se termine ne doit pas ouvrir le menu par-dessus.
				// Chrome n'envoie pas de `click` apres un glisser natif, mais tous
				// les navigateurs ne s'accordent pas la-dessus : on ignore donc un
				// clic qui suit immediatement un depot.
				if (Date.now() - dernierGlisser < 300) { return; }
				if (menuOuvert && menuOuvert.parentNode === poignee.parentNode) { fermerMenu(); return; }
				ouvrirMenu(poignee, ligne);
			});
			poignee.addEventListener('keydown', function (evenement) {
				if (evenement.key === 'Enter' || evenement.key === ' ') {
					evenement.preventDefault();
					ouvrirMenu(poignee, ligne);
				}
			});
		}(poignees[p]));
	}

	document.addEventListener('click', function (evenement) {
		if (!menuOuvert) { return; }
		if (menuOuvert.contains(evenement.target)) { return; }
		if (evenement.target.classList && evenement.target.classList.contains('poignee-rangement')) { return; }
		fermerMenu();
	});

	// ----------------------------------------------------------------------
	// Rangement des CATÉGORIES dans le menu repliable
	// ----------------------------------------------------------------------
	// Même geste, une échelle au-dessus : on déplace une catégorie entière à
	// l'intérieur de sa matière. Une catégorie ne voyage jamais d'une matière
	// à l'autre — ce serait un déplacement de contenu, pas un rangement.
	//
	// Le tableau est plat : la ligne de catégorie est suivie de ses lignes de
	// compétences, toutes sœurs dans le même <tbody>. Déplacer la catégorie
	// seule laisserait ses compétences derrière elle, sous la catégorie
	// précédente. On déplace donc le bloc entier.
	var lignesCategorie = tableau.querySelectorAll('.ligne-categorie[data-matiere]');
	if (lignesCategorie.length === 0) { return; }

	var categorieTiree = null;

	function memeMatiere(a, b) {
		return a && b && a.getAttribute('data-groupe') === b.getAttribute('data-groupe');
	}

	// La catégorie et toutes les lignes qui lui appartiennent, dans l'ordre.
	function blocDeLaCategorie(ligne) {
		var bloc = [ligne];
		var suivante = ligne.nextElementSibling;
		while (suivante && !suivante.classList.contains('ligne-categorie')) {
			bloc.push(suivante);
			suivante = suivante.nextElementSibling;
		}
		return bloc;
	}

	function categoriesDeLaMatiere(ligne) {
		var toutes = tableau.querySelectorAll('.ligne-categorie[data-matiere]');
		var retenues = [];
		for (var i = 0; i < toutes.length; i++) {
			if (memeMatiere(toutes[i], ligne)) { retenues.push(toutes[i]); }
		}
		return retenues;
	}

	function enregistrerLesCategories(ligne) {
		var lignes = categoriesDeLaMatiere(ligne);
		var corps = 'matiere=' + encodeURIComponent(ligne.getAttribute('data-matiere'));
		for (var i = 0; i < lignes.length; i++) {
			corps += '&categories%5B%5D=' + encodeURIComponent(lignes[i].getAttribute('data-categorie-nom'));
		}

		afficher('Enregistrement...');

		var requete = new XMLHttpRequest();
		requete.open('POST', 'enregistrer-ordre-categories.php', true);
		requete.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		requete.onload = function () {
			var reponse = null;
			try { reponse = JSON.parse(requete.responseText); } catch (e) { reponse = null; }
			if (reponse && reponse.ok) {
				afficher('Ordre des catégories enregistré.', 'succes');
			} else {
				afficher(
					(reponse && reponse.message) ? reponse.message : 'Enregistrement impossible.',
					'echec'
				);
			}
		};
		requete.onerror = function () {
			afficher('Serveur injoignable, l’ordre n’est pas enregistré.', 'echec');
		};
		requete.send(corps);
	}

	var poigneesCategorie = tableau.querySelectorAll('.poignee-categorie');
	for (var j = 0; j < poigneesCategorie.length; j++) {
		(function (poignee) {
			var ligne = poignee.closest('tr');
			if (!ligne) { return; }
			poignee.addEventListener('mousedown', function () { ligne.draggable = true; });
			poignee.addEventListener('mouseup', function () { ligne.draggable = false; });
		}(poigneesCategorie[j]));
	}

	tableau.addEventListener('dragstart', function (evenement) {
		var ligne = evenement.target.closest ? evenement.target.closest('.ligne-categorie') : null;
		if (!ligne || !ligne.getAttribute('data-matiere')) { return; }
		categorieTiree = ligne;
		ligne.classList.add('en-deplacement');
		if (evenement.dataTransfer) {
			evenement.dataTransfer.effectAllowed = 'move';
			evenement.dataTransfer.setData('text/plain', ligne.getAttribute('data-categorie-nom'));
		}
	});

	tableau.addEventListener('dragover', function (evenement) {
		if (!categorieTiree) { return; }
		var cible = evenement.target.closest ? evenement.target.closest('.ligne-categorie') : null;
		if (!cible || cible === categorieTiree || !memeMatiere(cible, categorieTiree)) { return; }

		evenement.preventDefault();
		effacerReperes();
		var cadre = cible.getBoundingClientRect();
		var avant = (evenement.clientY - cadre.top) < (cadre.height / 2);
		cible.classList.add(avant ? 'cible-avant' : 'cible-apres');
	});

	tableau.addEventListener('drop', function (evenement) {
		if (!categorieTiree) { return; }
		var cible = evenement.target.closest ? evenement.target.closest('.ligne-categorie') : null;
		if (!cible || cible === categorieTiree || !memeMatiere(cible, categorieTiree)) { return; }

		evenement.preventDefault();
		var cadre = cible.getBoundingClientRect();
		var avant = (evenement.clientY - cadre.top) < (cadre.height / 2);

		var bloc = blocDeLaCategorie(categorieTiree);
		var parent = cible.parentNode;

		if (avant) {
			for (var i = 0; i < bloc.length; i++) { parent.insertBefore(bloc[i], cible); }
		} else {
			// Après la cible veut dire après TOUT son bloc, sinon la catégorie
			// déplacée s'intercalerait entre la cible et ses compétences.
			var blocCible = blocDeLaCategorie(cible);
			var repere = blocCible[blocCible.length - 1].nextElementSibling;
			for (var k = 0; k < bloc.length; k++) { parent.insertBefore(bloc[k], repere); }
		}

		effacerReperes();
		enregistrerLesCategories(categorieTiree);
	});

	tableau.addEventListener('dragend', function () {
		if (categorieTiree) {
			categorieTiree.classList.remove('en-deplacement');
			categorieTiree.draggable = false;
		}
		effacerReperes();
		categorieTiree = null;
	});
}());
