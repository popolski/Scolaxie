<?php
session_start();

if(!isset($_SESSION['nom'])){
	header('location:index.php');
	exit();
}

$fichier=isset($_GET['fichier'])?rawurldecode($_GET['fichier']):'';
$fichier=str_replace('\\','/',$fichier);

if($fichier===''||strpos($fichier,'..')!==false||strpos($fichier,'://')!==false||$fichier[0]=='/'||!preg_match('/^[A-Za-z0-9_\/.\-]+\.pdf$/i',$fichier)){
	http_response_code(400);
	die('Fichier PDF invalide.');
}

$cheminRelatif=str_replace('/',DIRECTORY_SEPARATOR,$fichier);
$racinesReelles=array_filter([
	realpath(__DIR__),
	// Le V2 mutualise les médias lourds avec le site stable. Cette seconde
	// racine permet uniquement de valider l'existence du PDF avant que la
	// règle de repli de /v2/.htaccess ne le serve depuis School Monsters V1.
	realpath(dirname(__DIR__,2).DIRECTORY_SEPARATOR.'schoolmonsters')
]);
$cheminReel=false;
foreach($racinesReelles as $racineReelle){
	$candidat=realpath($racineReelle.DIRECTORY_SEPARATOR.$cheminRelatif);
	if($candidat!==false&&strpos($candidat,$racineReelle.DIRECTORY_SEPARATOR)===0&&is_file($candidat)){
		$cheminReel=$candidat;
		break;
	}
}
if($cheminReel===false){
	http_response_code(404);
	die('Fichier PDF introuvable.');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Lecteur de document</title>
	<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700&display=swap">
	<link rel="stylesheet" href="/galaxie-tokens.css?v=20260912-conformite-3">
	<link rel="stylesheet" href="css/lecteur-pdf.css?v=20260909-audio-integre">

</head>
<body class="gx-app-schoolmonsters">
	<div class="lecteur" id="lecteur">
		<div class="barre" aria-label="Commandes du document">

			<div class="groupe outils-gauche">
				<button type="button" id="reduire" title="Réduire">−</button>
				<button type="button" id="ajuster" class="bouton-principal" title="Ajuster le document à la largeur">Ajuster</button>
				<button type="button" id="agrandir" title="Agrandir">+</button>
				<button type="button" id="pleinEcran" title="Plein écran">⛶ <span class="masquable-mobile">Plein écran</span></button>
				<a class="bouton masquable-mobile" href="<?php echo htmlspecialchars($fichier,ENT_QUOTES,'UTF-8'); ?>" target="_blank" rel="noopener">Imprimer</a>
				<a class="bouton masquable-mobile" href="<?php echo htmlspecialchars($fichier,ENT_QUOTES,'UTF-8'); ?>" download>Télécharger</a>
			</div>
			<div class="groupe outils-droite">
				<div class="groupe pagination" aria-label="Pages du document">
					<button type="button" id="precedente" title="Page précédente">← <span class="masquable-mobile">Précédente</span></button>
					<div class="numero-page"><label for="page">Page</label><input id="page" type="number" min="1" value="1"><span>sur <strong id="total">…</strong></span></div>
					<button type="button" id="suivante" title="Page suivante"><span class="masquable-mobile">Suivante</span> →</button>
				</div>
				<!-- Écoute de la consigne sans quitter le document : le groupe reste
				     masqué tant que la fiche parente n'a pas de pistes audio. -->
				<div class="groupe" id="groupeAudio" hidden>
					<select id="choixAudio" aria-label="Choisir la piste à écouter"></select>
					<button type="button" id="lireAudio" class="bouton-audio" title="Écouter la consigne"><span class="icone-audio">🎧</span> <span class="masquable-mobile libelle-audio">Écouter</span></button>
				</div>
			</div>
		</div>
		<div class="zone-document" id="zoneDocument">
			<div class="feuille"><canvas id="pagePdf"></canvas></div>
			<div class="etat" id="etat" role="status">Chargement du document…</div>
		</div>
	</div>

	<script type="module">
		import * as pdfjsLib from './utils/pdfjs/build/pdf.mjs';

		pdfjsLib.GlobalWorkerOptions.workerSrc='./utils/pdfjs/build/pdf.worker.mjs';

		const urlPdf=<?php echo json_encode($fichier,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;
		const baseLecteur=new URL('./',window.location.href);

		// -------------------------------------------------------------------
		// Écoute de la consigne depuis la barre du lecteur
		// -------------------------------------------------------------------
		// Le lecteur audio vit dans la fiche parente (au-dessus du document) :
		// en plein écran il devient inaccessible. On le pilote donc d'ici.
		// On ne recopie PAS sa logique : on clique ses propres boutons, ce qui
		// met à jour d'un coup la piste, le titre affiché et le bouton actif
		// de la page parente. Les chemins des mp3 sont ainsi résolus dans le
		// contexte de la fiche, pas dans celui du lecteur.
		const groupeAudio=document.getElementById('groupeAudio');
		const choixAudio=document.getElementById('choixAudio');
		const boutonAudio=document.getElementById('lireAudio');
		let audioParent=null;
		let pistesParent=[];

		try{
			if(window.parent&&window.parent!==window&&window.parent.document){
				audioParent=window.parent.document.getElementById('lecteurAudioQuiz');
				pistesParent=Array.prototype.slice.call(
					window.parent.document.querySelectorAll('.btn-audio-quiz[data-audio-src]')
				);
			}
		}catch(erreur){
			// Lecteur ouvert seul, ou page d'une autre origine : on n'affiche rien.
		}

		function majEtatBoutonAudio(){
			const enLecture=audioParent&&!audioParent.paused&&!audioParent.ended;
			boutonAudio.classList.toggle('en-lecture',enLecture);
			boutonAudio.title=enLecture?'Mettre en pause':'Écouter la consigne';
			const icone=boutonAudio.querySelector('.icone-audio');
			const libelle=boutonAudio.querySelector('.libelle-audio');
			if(icone){ icone.textContent=enLecture?'⏸':'🎧'; }
			if(libelle){ libelle.textContent=enLecture?'Pause':'Écouter'; }
		}

		function choisirPiste(index){
			if(index<0||index>=pistesParent.length){ return; }
			// Le clic déclenche le gestionnaire de la fiche : src + titre + surbrillance.
			pistesParent[index].click();
			allerALaPageDeLaPiste(index);
		}

		// Saut à la page du PDF correspondant à la piste choisie.
		//
		// C'est le sens piste -> page, le seul qui ne soit jamais ambigu :
		// une page peut porter deux exercices, donc page -> piste ne saurait
		// pas lequel jouer. Dans ce sens-ci la question ne se pose pas.
		//
		// L'attribut data-page n'est posé que sur les pistes dont la page a
		// été déduite SANS ambiguïté (libellés « Exercice N » et « Leçon »,
		// mesurés comme ne figurant jamais sur plus d'une page). Une piste
		// sans cet attribut ne provoque aucun saut : le son est joué et la
		// page affichée ne bouge pas, exactement comme aujourd'hui.
		// Ne compare PAS avec pageActuelle avant de sauter : cette variable est
		// déclarée plus bas avec let, donc toute lecture faite avant que le
		// module n'ait atteint sa déclaration lève une ReferenceError — erreur
		// silencieuse pour l'utilisateur, qui ne voit que « le PDF ne bouge
		// pas ». Réafficher une page déjà affichée ne coûte qu'un rendu.
		function allerALaPageDeLaPiste(index){
			const bouton=pistesParent[index];
			if(!bouton){ return; }
			const page=parseInt(bouton.getAttribute('data-page'),10);
			if(!page||page<1){ return; }
			try{ afficherPage(page); }
			catch(erreur){ console.error('saut de page impossible',erreur); }
		}

		if(audioParent&&pistesParent.length>0){
			groupeAudio.hidden=false;

			pistesParent.forEach(function(bouton,index){
				const option=document.createElement('option');
				option.textContent=bouton.textContent.trim();
				choixAudio.appendChild(option);

				// Les boutons de piste sont AUSSI affichés sur la fiche, à côté
				// du lecteur, et c'est le geste le plus naturel : l'élève clique
				// directement dessus. Sans ce branchement, seule la liste
				// déroulante du lecteur déclenchait le saut de page, et un clic
				// sur la fiche ne faisait rien — c'est exactement ce qui a été
				// constaté à l'usage.
				bouton.addEventListener('click',function(){
					choixAudio.selectedIndex=index;
					allerALaPageDeLaPiste(index);
				});
			});

			// Une seule piste : la liste déroulante n'apporte rien.
			if(pistesParent.length===1){ choixAudio.hidden=true; }

			// Refléter la piste déjà sélectionnée sur la fiche.
			const dejaActive=pistesParent.findIndex(function(b){ return b.classList.contains('actif'); });
			choixAudio.selectedIndex=dejaActive>=0?dejaActive:0;

			// La liste est étroite pour tenir dans la barre : l'infobulle donne
			// le nom entier de la piste choisie, quand il est coupé.
			function rappelerLeNomEntier(){
				var choisie=choixAudio.options[choixAudio.selectedIndex];
				choixAudio.title=choisie?choisie.textContent:'';
			}
			rappelerLeNomEntier();

			// Choisir une piste la CHARGE et fait sauter le document a sa page.
			// Elle ne demarre pas toute seule : demande de l’enseignante, pour que le
			// son ne parte jamais sans que l'enfant l'ait voulu. Le bouton
			// « Ecouter » reste la pour jouer et mettre en pause.
			choixAudio.addEventListener('change',function(){
				choisirPiste(choixAudio.selectedIndex);
				rappelerLeNomEntier();
			});

			boutonAudio.addEventListener('click',function(){
				if(audioParent.paused||audioParent.ended){
					const voulu=pistesParent[choixAudio.selectedIndex];
					if(voulu&&!voulu.classList.contains('actif')){ choisirPiste(choixAudio.selectedIndex); }
					audioParent.play().catch(function(){ afficherEtat('Le son ne peut pas être lu. Réessayez.',true); });
				}else{
					audioParent.pause();
				}
			});

			audioParent.addEventListener('play',majEtatBoutonAudio);
			audioParent.addEventListener('pause',majEtatBoutonAudio);
			audioParent.addEventListener('ended',majEtatBoutonAudio);
			majEtatBoutonAudio();
		}

		const canvas=document.getElementById('pagePdf');
		const contexte=canvas.getContext('2d');
		const zone=document.getElementById('zoneDocument');
		const etat=document.getElementById('etat');
		const saisiePage=document.getElementById('page');
		const total=document.getElementById('total');
		let documentPdf=null;
		let pageActuelle=1;
		let facteurZoom=1;
		let renduEnCours=null;
		let numeroDemande=0;
		let temporisation=null;

		function afficherEtat(message,erreur=false){
			etat.textContent=message;
			etat.classList.toggle('erreur',erreur);
			etat.style.display='block';
		}

		function actualiserCommandes(){
			document.getElementById('precedente').disabled=pageActuelle<=1;
			document.getElementById('suivante').disabled=!documentPdf||pageActuelle>=documentPdf.numPages;
			saisiePage.value=pageActuelle;
		}

		async function afficherPage(numero){
			if(!documentPdf){ return; }
			const demande=++numeroDemande;
			pageActuelle=Math.max(1,Math.min(numero,documentPdf.numPages));
			actualiserCommandes();
			afficherEtat('Affichage de la page…');

			if(renduEnCours){
				renduEnCours.cancel();
				try{ await renduEnCours.promise; }catch(erreur){}
			}
			if(demande!==numeroDemande){ return; }
			const page=await documentPdf.getPage(pageActuelle);
			if(demande!==numeroDemande){ return; }
			const viewportOriginal=page.getViewport({scale:1});
			const largeurDisponible=Math.max(120,zone.clientWidth-36);
			const echelle=(largeurDisponible/viewportOriginal.width)*facteurZoom;
			const viewport=page.getViewport({scale:echelle});
			const densite=Math.min(window.devicePixelRatio||1,2);

			canvas.width=Math.floor(viewport.width*densite);
			canvas.height=Math.floor(viewport.height*densite);
			canvas.style.width=Math.floor(viewport.width)+'px';
			canvas.style.height=Math.floor(viewport.height)+'px';
			const transformation=densite!==1?[densite,0,0,densite,0,0]:null;

			const tacheRendu=page.render({canvasContext:contexte,viewport:viewport,transform:transformation});
			renduEnCours=tacheRendu;
			try{
				await tacheRendu.promise;
				etat.style.display='none';
			}catch(erreur){
				if(erreur&&erreur.name!=='RenderingCancelledException'){
					afficherEtat('Impossible d’afficher cette page.',true);
				}
			}finally{
				if(renduEnCours===tacheRendu){ renduEnCours=null; }
			}
		}

		async function charger(){
			try{
				documentPdf=await pdfjsLib.getDocument({
					url:urlPdf,
					cMapUrl:new URL('utils/pdfjs/web/cmaps/',baseLecteur).href,
					cMapPacked:true,
					standardFontDataUrl:new URL('utils/pdfjs/web/standard_fonts/',baseLecteur).href,
					wasmUrl:new URL('utils/pdfjs/web/wasm/',baseLecteur).href
				}).promise;
				total.textContent=documentPdf.numPages;
				saisiePage.max=documentPdf.numPages;
				await afficherPage(1);
				if(audioParent && window.parent.document.querySelector('.sm-lecon-contenu')) window.parent.document.body.classList.add('sm-pdf-pret');
			}catch(erreur){
				console.error(erreur);
				afficherEtat('Le document n’a pas pu être chargé.',true);
				if(window.parent!==window) window.parent.document.body.classList.remove('sm-pdf-pret');
			}
		}

		document.getElementById('precedente').addEventListener('click',()=>afficherPage(pageActuelle-1));
		document.getElementById('suivante').addEventListener('click',()=>afficherPage(pageActuelle+1));
		document.getElementById('reduire').addEventListener('click',()=>{facteurZoom=Math.max(.25,facteurZoom-.15);afficherPage(pageActuelle);});
		document.getElementById('agrandir').addEventListener('click',()=>{facteurZoom=Math.min(2.5,facteurZoom+.15);afficherPage(pageActuelle);});
		document.getElementById('ajuster').addEventListener('click',()=>{facteurZoom=1;afficherPage(pageActuelle);});
		saisiePage.addEventListener('change',()=>afficherPage(parseInt(saisiePage.value,10)||1));
		document.getElementById('pleinEcran').addEventListener('click',async()=>{
			if(!document.fullscreenElement){ await document.documentElement.requestFullscreen(); }
			else{ await document.exitFullscreen(); }
		});
		document.addEventListener('keydown',event=>{
			if(event.target.matches('input,select,textarea')) return;
			if(event.key==='ArrowLeft'){ afficherPage(pageActuelle-1); }
			if(event.key==='ArrowRight'){ afficherPage(pageActuelle+1); }
		});
		window.addEventListener('resize',()=>{
			clearTimeout(temporisation);
			temporisation=setTimeout(()=>afficherPage(pageActuelle),180);
		});

		charger();
	</script>
</body>
</html>
