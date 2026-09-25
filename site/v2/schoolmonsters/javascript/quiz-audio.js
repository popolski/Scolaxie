// Lecteur audio à plusieurs pistes (leçon + exercices), piloté par des
// boutons déclarés en data-attributes plutôt que par une fonction JS dédiée
// par bouton (FunctionQ1, FunctionQ2...) recopiée et adaptée dans chaque
// fiche - source d'erreurs de copier-coller (chemin oublié, mauvais indice).
//
// Utilisation dans une fiche :
//   <p id="LaQuestion">Leçon</p>
//   <audio controls id="lecteurAudioQuiz"></audio>
//   <button type="button" class="btn-audio-quiz" data-audio-src="dossier/Lecon.mp3">Leçon</button>
//   <button type="button" class="btn-audio-quiz" data-audio-src="dossier/Exercice1.mp3">Exercice 1</button>
//   <script src="../../javascript/quiz-audio.js"></script>
//
// Le libellé affiché dans #LaQuestion reprend directement le texte du
// bouton cliqué : pas besoin de le répéter dans un data-attribute séparé.
// Le premier bouton du groupe est chargé automatiquement au chargement.
document.addEventListener('DOMContentLoaded', function () {
	var lecteur = document.getElementById('lecteurAudioQuiz');
	if (!lecteur) { return; }

	var titre = document.getElementById('LaQuestion');
	var boutons = document.querySelectorAll('.btn-audio-quiz[data-audio-src]');
	var autres = null;
	if (document.querySelector('.sm-lecon-pistes') && boutons.length > 4) {
		var label = document.createElement('label');
		label.textContent = 'Autres exercices';
		autres = document.createElement('select');
		autres.setAttribute('aria-label', 'Autres exercices');
		autres.add(new Option('Choisir un exercice', ''));
		Array.from(boutons).slice(4).forEach(function (bouton, i) {
			autres.add(new Option(bouton.textContent.trim(), String(i + 4)));
			bouton.hidden = true;
		});
		autres.addEventListener('change', function () { if (autres.value !== '') boutons[Number(autres.value)].click(); });
		label.appendChild(autres);
		document.querySelector('.sm-lecon-pistes .gx-pistes').appendChild(label);
	}

	boutons.forEach(function (bouton) {
		bouton.addEventListener('click', function () {
			lecteur.src = bouton.getAttribute('data-audio-src');
			if (titre) { titre.textContent = bouton.textContent.trim(); }
			boutons.forEach(function (autre) { autre.classList.remove('actif'); autre.setAttribute('aria-pressed', 'false'); });
			bouton.classList.add('actif');
			bouton.setAttribute('aria-pressed', 'true');
			if (autres) { var index = Array.from(boutons).indexOf(bouton); autres.value = index >= 4 ? String(index) : ''; }

			// Le clic CHARGE la piste et fait sauter le document à sa page, mais
			// ne lance pas la lecture : demande de l’enseignante, pour qu'un son ne
			// démarre jamais sans que l'enfant l'ait voulu. C'est le bouton de
			// lecture du petit lecteur audio, ou « Écouter » dans la barre du
			// document, qui la lance.
		});
	});

	if (boutons.length > 0) {
		lecteur.src = boutons[0].getAttribute('data-audio-src');
		boutons[0].classList.add('actif');
		boutons.forEach(function (bouton, i) { bouton.setAttribute('aria-pressed', String(i === 0)); });
	}

	if (boutons.length === 0) { return; }

	// ----------------------------------------------------------------------
	// Pages réglées à la main
	// ----------------------------------------------------------------------
	// Les pages inscrites dans le fichier (data-page) ont été posées
	// automatiquement, quand le texte du PDF permettait de les retrouver. Pour
	// les autres, l'enseignante les règle elle-même depuis la fiche, et le
	// réglage est gardé EN BASE, pas dans le fichier : un transfert FTP
	// écraserait le fichier et effacerait son travail sans prévenir.
	//
	// La racine du site se déduit de l'adresse de ce script, plutôt que d'un
	// « ../../ » écrit en dur : les fiches ne sont pas toutes à la même
	// profondeur, et une constante finirait par être fausse quelque part.
	var moi = document.querySelector('script[src*="quiz-audio.js"]');
	var racine = moi ? moi.getAttribute('src').replace(/javascript\/quiz-audio\.js.*$/, '') : '';

	// Chemin de la fiche depuis la racine du site : c'est lui qui l'identifie
	// côté serveur. On le reconstruit à partir de l'adresse courante et du
	// nombre de « ../ » qui mènent à la racine.
	var profondeur = (racine.match(/\.\.\//g) || []).length;
	var morceaux = window.location.pathname.split('/').filter(function (m) { return m !== ''; });
	var fiche = morceaux.slice(Math.max(0, morceaux.length - profondeur - 1)).join('/');

	function appliquerLesPages(sauts) {
		boutons.forEach(function (bouton) {
			var src = bouton.getAttribute('data-audio-src');
			if (Object.prototype.hasOwnProperty.call(sauts, src)) {
				bouton.setAttribute('data-page', sauts[src]);
			}
		});
	}

	var requete = new XMLHttpRequest();
	requete.open('GET', racine + 'sauts-page.php?fiche=' + encodeURIComponent(fiche), true);
	requete.onload = function () {
		var reponse = null;
		try { reponse = JSON.parse(requete.responseText); } catch (e) { return; }
		if (!reponse) { return; }
		if (reponse.sauts) { appliquerLesPages(reponse.sauts); }
	};
	// Une panne ici ne doit rien casser : la fiche continue de fonctionner
	// avec les pages inscrites dans le fichier.
	requete.onerror = function () {};
	requete.send();

	// L'outil de reglage des pages vivait ICI, sur la fiche, sous un bouton
	// « Regler les pages » reserve a l'enseignante. Retire le 30/08/2026 :
	// le responsable technique prefere que ce reglage passe par l'editeur de lecons, ou la
	// piste et la page du PDF se voient cote a cote. Rien n'est perdu, la
	// table sm_saut_page etait vide : l'outil n'avait jamais pu servir,
	// l'acces enseignant aux fiches ayant ete ferme entre-temps.
	//
	// La LECTURE des sauts reste en place juste au-dessus : c'est l'editeur
	// qui remplira la table.
});
