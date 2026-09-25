// Logique commune à liste-competences.php et liste-connaissances.php.
// Suppose deux variables globales injectées par la page PHP juste avant ce
// script : arbreBibliotheque (matiere -> [categories]) et
// valeurInitialeCategorieBibliotheque (categorie presélectionnée dans l'URL).
//
// L'ORDRE DES CATEGORIES VIENT DU SERVEUR, on ne le retrie pas ici. Ce script
// appelait `.sort()` sur chaque liste : le rangement à la main de
// l'administratrice était donc rétabli en alphabétique dans les sélecteurs,
// alors que le tableau, lui, le respectait. Signalé par le responsable technique le 06/09/2026.
// Sur la page des connaissances, l'ordre servi est alphabétique de toute façon
// (pas de rangement manuel là-bas), le rendu n'y change donc pas.

var matiereSel=document.getElementById('matiere');
var categorieSel=document.getElementById('categorie');

function remplirCategories(garderSelection){
	var m=matiereSel.value;
	categorieSel.innerHTML='<option value="">Toutes</option>';
	if(m!==''&&arbreBibliotheque[m]){
		arbreBibliotheque[m].forEach(function(c){
			var opt=document.createElement('option');
			opt.value=c;
			opt.textContent=c;
			if(garderSelection&&c===valeurInitialeCategorieBibliotheque){ opt.selected=true; }
			categorieSel.appendChild(opt);
		});
	}
}

matiereSel.addEventListener('change',function(){ remplirCategories(false); });

remplirCategories(true);

// ---------------------------------------------------------------------------
// Formulaire de creation : matiere -> categorie, en vraies listes deroulantes
// ---------------------------------------------------------------------------
// C'etaient deux `<input list=datalist>`. Des qu'un champ portait une valeur
// complete, la liste se filtrait sur cette seule entree : la fleche n'ouvrait
// plus rien et il fallait effacer au clavier pour changer d'avis. Signale par
// l’enseignante le 06/09/2026. Ce sont maintenant des `<select>` avec une entree
// « Autre… » qui ouvre un champ libre - c'est par lui qu'on cree une matiere ou
// une categorie qui n'existe pas encore, ce que la liste seule ne permettrait
// plus.
var matiereAjout=document.getElementById('matiere_ajout');
var categorieAjout=document.getElementById('categorie_ajout');
var matiereAutre=document.getElementById('matiere_autre');
var categorieAutre=document.getElementById('categorie_autre');

// `vider` distingue le premier affichage d'un vrai changement : au chargement,
// le champ libre peut arriver DEJA REMPLI par le serveur (une ligne dont la
// matiere ne figure plus dans la liste), et il ne faut surtout pas l'effacer.
function majChampLibre(select,champ,vider){
	if(!select||!champ){ return; }
	var libre=(select.value==='__autre__');
	champ.hidden=!libre;
	// `required` seulement quand il est visible : un champ obligatoire et cache
	// bloque l'envoi du formulaire sans que le navigateur puisse montrer ou.
	champ.required=libre;
	if(vider&&!libre){ champ.value=''; }
	if(vider&&libre){ champ.focus(); }
}

function remplirCategoriesAjout(valeurAGarder){
	if(!matiereAjout||!categorieAjout){ return; }
	var m=matiereAjout.value;
	categorieAjout.innerHTML='';
	var vide=document.createElement('option');
	vide.value='';
	vide.textContent='-- Choisir --';
	categorieAjout.appendChild(vide);

	var trouvee=false;
	if(arbreBibliotheque[m]){
		arbreBibliotheque[m].forEach(function(c){
			var opt=document.createElement('option');
			opt.value=c;
			opt.textContent=c;
			if(valeurAGarder&&c===valeurAGarder){ opt.selected=true; trouvee=true; }
			categorieAjout.appendChild(opt);
		});
	}

	var autre=document.createElement('option');
	autre.value='__autre__';
	autre.textContent='Autre catégorie…';
	categorieAjout.appendChild(autre);

	// Une categorie absente de la liste (matiere archivee, orthographe unique)
	// ne doit pas disparaitre en silence de la ligne qu'on modifie : on rouvre
	// « Autre… » avec elle plutot que de la remplacer par du vide.
	if(valeurAGarder&&!trouvee){
		autre.selected=true;
		if(categorieAutre){ categorieAutre.value=valeurAGarder; }
	}
}

if(matiereAjout&&categorieAjout){
	remplirCategoriesAjout(typeof valeurInitialeCategorieAjout!=='undefined'?valeurInitialeCategorieAjout:'');
	majChampLibre(matiereAjout,matiereAutre,false);
	majChampLibre(categorieAjout,categorieAutre,false);

	matiereAjout.addEventListener('change',function(){
		remplirCategoriesAjout('');
		majChampLibre(matiereAjout,matiereAutre,true);
		majChampLibre(categorieAjout,categorieAutre,true);
	});
	categorieAjout.addEventListener('change',function(){
		majChampLibre(categorieAjout,categorieAutre,true);
	});
}

// cascade pour le formulaire de renommage de categorie
var matiereContexte=document.getElementById('matiere_contexte');
var ancienneCategorieSel=document.getElementById('ancienne_categorie');

// La meme cascade sert au renommage ET a la suppression d'une categorie : une
// seule fonction, appelee pour chaque paire de listes, plutot qu'un copier-
// coller qui finirait par diverger. Les elements manquants sont ignores - la
// page des connaissances n'a pas le meme jeu de formulaires.
function cascadeCategories(selectMatiere,selectCategorie){
	if(!selectMatiere||!selectCategorie){ return; }
	selectMatiere.addEventListener('change',function(){
		var m=selectMatiere.value;
		selectCategorie.innerHTML='<option value="">-- Choisir --</option>';
		if(arbreBibliotheque[m]){
			arbreBibliotheque[m].forEach(function(c){
				var opt=document.createElement('option');
				opt.value=c;
				opt.textContent=c;
				selectCategorie.appendChild(opt);
			});
		}
	});
}

cascadeCategories(matiereContexte,ancienneCategorieSel);
cascadeCategories(document.getElementById('matiere_suppression'),document.getElementById('categorie_a_supprimer'));

// insertion d'un symbole mathematique dans le champ cible, a l'endroit du curseur
document.querySelectorAll('.btn-symbole').forEach(function(btn){
	btn.addEventListener('click',function(){
		var champ=document.getElementById(btn.getAttribute('data-cible') || 'commentaire');
		var symbole=btn.getAttribute('data-symbole');
		var debut=champ.selectionStart;
		var fin=champ.selectionEnd;
		champ.value=champ.value.slice(0,debut)+symbole+champ.value.slice(fin);
		champ.focus();
		champ.selectionStart=champ.selectionEnd=debut+symbole.length;
	});
});

// ---------------------------------------------------------------------------
// Repliage matière > catégorie
// ---------------------------------------------------------------------------
// Une ligne de données n'est visible que si SA matière ET SA catégorie sont
// toutes les deux ouvertes. L'état vit dans l'attribut aria-expanded des
// boutons, qui sert donc à la fois à l'accessibilité et de source de vérité.

function categorieEstOuverte(idCategorie){
	var bouton=document.querySelector('.bascule-categorie[data-categorie="'+idCategorie+'"]');
	return bouton ? bouton.getAttribute('aria-expanded')==='true' : false;
}

function matiereEstOuverte(idGroupe){
	var bouton=document.querySelector('.bascule-matiere[data-groupe="'+idGroupe+'"]');
	return bouton ? bouton.getAttribute('aria-expanded')==='true' : false;
}

// Recalcule ce qui doit être visible pour une matière donnée.
function rafraichirGroupe(idGroupe){
	var ouverte=matiereEstOuverte(idGroupe);

	document.querySelectorAll('.ligne-categorie[data-groupe="'+idGroupe+'"]').forEach(function(ligne){
		ligne.hidden=!ouverte;
	});

	document.querySelectorAll('.ligne-donnee[data-groupe="'+idGroupe+'"]').forEach(function(ligne){
		var visible=ouverte&&categorieEstOuverte(ligne.getAttribute('data-categorie'));
		ligne.hidden=!visible;
		// Un code-barres ouvert est SUPPRIMÉ quand sa ligne disparaît, au lieu
		// d'être seulement masqué : sinon on refermait une matière puis on la
		// rouvrait et le code-barres était encore là. Il est de toute façon
		// recréé au clic, donc rien n'est perdu.
		var suivante=ligne.nextElementSibling;
		if(suivante&&suivante.classList.contains('ligne-code-barre')&&!visible){
			suivante.parentNode.removeChild(suivante);
		}
	});
}

document.querySelectorAll('.bascule-matiere').forEach(function(bouton){
	bouton.addEventListener('click',function(){
		var idGroupe=bouton.getAttribute('data-groupe');
		bouton.setAttribute('aria-expanded',bouton.getAttribute('aria-expanded')==='true'?'false':'true');
		rafraichirGroupe(idGroupe);
	});
});

document.querySelectorAll('.bascule-categorie').forEach(function(bouton){
	bouton.addEventListener('click',function(){
		bouton.setAttribute('aria-expanded',bouton.getAttribute('aria-expanded')==='true'?'false':'true');
		rafraichirGroupe(bouton.getAttribute('data-groupe'));
	});
});

function toutBasculer(ouvrir){
	document.querySelectorAll('.bascule-matiere,.bascule-categorie').forEach(function(bouton){
		bouton.setAttribute('aria-expanded',ouvrir?'true':'false');
	});
	document.querySelectorAll('.bascule-matiere').forEach(function(bouton){
		rafraichirGroupe(bouton.getAttribute('data-groupe'));
	});
}

var boutonToutDeplier=document.getElementById('tout-deplier');
if(boutonToutDeplier){ boutonToutDeplier.addEventListener('click',function(){ toutBasculer(true); }); }

var boutonToutReplier=document.getElementById('tout-replier');
if(boutonToutReplier){ boutonToutReplier.addEventListener('click',function(){ toutBasculer(false); }); }

// ---------------------------------------------------------------------------
// Codes-barres : la ligne est CRÉÉE au clic, pas envoyée par le serveur.
// ---------------------------------------------------------------------------
// Avant, chaque résultat émettait une 2e ligne masquée contenant un <canvas> :
// à 533 compétences cela faisait plus de 1000 <tr> et 533 <canvas> chargés
// pour rien. Ici on n'en crée qu'au moment où l'enseignante clique.

function creerLigneCodeBarre(ligneParente,code,nbColonnes){
	var ligne=document.createElement('tr');
	ligne.className='ligne-code-barre';

	var cellule=document.createElement('td');
	cellule.colSpan=nbColonnes;

	var canvas=document.createElement('canvas');
	canvas.className='canvas-code-barre';

	var boutonCopier=document.createElement('button');
	boutonCopier.type='button';
	boutonCopier.className='btn-copier-barre';
	boutonCopier.textContent="Copier l'image";
	boutonCopier.addEventListener('click',function(){ copierCodeBarre(canvas); });

	cellule.appendChild(canvas);
	cellule.appendChild(document.createElement('br'));
	cellule.appendChild(boutonCopier);
	ligne.appendChild(cellule);
	ligneParente.parentNode.insertBefore(ligne,ligneParente.nextSibling);

	JsBarcode(canvas,code,{format:'CODE128',displayValue:false,width:2,height:50,margin:6});
	return ligne;
}

function copierCodeBarre(canvas){
	canvas.toBlob(function(blob){
		if(navigator.clipboard&&window.ClipboardItem){
			navigator.clipboard.write([new ClipboardItem({'image/png':blob})]).then(function(){
				alert("Code-barres copié ! Tu peux le coller dans ton document.");
			}).catch(function(){
				alert("La copie automatique n'a pas fonctionné. Fais un clic droit sur le code-barres puis \"Copier l'image\".");
			});
		}else{
			alert("Ton navigateur ne permet pas la copie automatique. Fais un clic droit sur le code-barres puis \"Copier l'image\".");
		}
	});
}

document.querySelectorAll('.btn-code-barre').forEach(function(btn){
	btn.addEventListener('click',function(){
		var ligneParente=btn.closest('tr');
		var suivante=ligneParente.nextElementSibling;

		if(suivante&&suivante.classList.contains('ligne-code-barre')){
			suivante.parentNode.removeChild(suivante);
			return;
		}

		creerLigneCodeBarre(
			ligneParente,
			btn.getAttribute('data-code'),
			parseInt(btn.getAttribute('data-colonnes'),10)||6
		);
	});
});

// ---------------------------------------------------------------------------
// Retour sur la ligne qu'on vient d'enregistrer
// ---------------------------------------------------------------------------
// La page redirige apres un enregistrement (voir retourApresEcriture en PHP) et
// pose une ancre sur la ligne. Mais une ancre ne suffit pas ici : une ligne dont
// la matiere ou la categorie est repliee est `hidden`, et le navigateur ne
// defile pas vers un element cache. On rouvre donc son groupe avant de l'amener
// a l'ecran.
(function(){
	var ligne=document.querySelector('.ligne-donnee.est-mise-en-avant');
	if(!ligne||typeof rafraichirGroupe!=='function'){ return; }

	var idGroupe=ligne.getAttribute('data-groupe');
	var idCategorie=ligne.getAttribute('data-categorie');
	[ '.bascule-matiere[data-groupe="'+idGroupe+'"]',
	  '.bascule-categorie[data-categorie="'+idCategorie+'"]' ].forEach(function(selecteur){
		var bouton=document.querySelector(selecteur);
		if(bouton){ bouton.setAttribute('aria-expanded','true'); }
	});
	rafraichirGroupe(idGroupe);

	// APRES le chargement complet, pas tout de suite : la page continue de
	// grandir apres l'execution du script (polices, codes-barres), et un
	// defilement calcule trop tot laisse la ligne hors de l'ecran - mesure le
	// 06/09/2026, elle se retrouvait a 1345 px du haut de la fenetre.
	// `center` plutot que le haut : la ligne se lit avec ses voisines, on
	// retrouve son contexte et pas seulement elle.
	window.addEventListener('load',function(){
		ligne.scrollIntoView({block:'center'});
	});
})();

// Meme chose APRES UNE SUPPRESSION, ou il n'y a plus de ligne ou revenir : c'est
// la categorie d'ou la competence a ete retiree qu'on rouvre et qu'on ramene a
// l'ecran. Sans ca, la page se rouvrait entierement repliee, tout en haut -
// « il supprime puis me remet sur l'accueil » (l’enseignante, 06/09/2026).
(function(){
	var categorie=document.querySelector('.ligne-categorie.est-mise-en-avant');
	if(!categorie||typeof rafraichirGroupe!=='function'){ return; }

	var idGroupe=categorie.getAttribute('data-groupe');
	var idCategorie=categorie.getAttribute('data-categorie-id');
	[ '.bascule-matiere[data-groupe="'+idGroupe+'"]',
	  '.bascule-categorie[data-categorie="'+idCategorie+'"]' ].forEach(function(selecteur){
		var bouton=document.querySelector(selecteur);
		if(bouton){ bouton.setAttribute('aria-expanded','true'); }
	});
	rafraichirGroupe(idGroupe);

	window.addEventListener('load',function(){
		categorie.scrollIntoView({block:'center'});
	});
})();
