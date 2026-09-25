<?php session_start();

if (empty($_SESSION['permission'])) {
	header('Location: ../index.php');
	exit;
}

// Après un enregistrement, la confirmation reste affichée aussi longtemps que
// nécessaire. Le bouton remet ensuite proprement le lecteur au début du parcours.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['continuer_saisie']) && !empty($_SESSION['confirmation_saisie'])) {
	unset($_SESSION['confirmation_saisie'], $_SESSION['type_saisie'], $_SESSION['intitule_saisie']);
	$_SESSION['saisie'] = 'debut';
	if (($_SESSION['role'] ?? '') !== 'eleve') {
		unset($_SESSION['id_eleve'], $_SESSION['prenom_eleve'], $_SESSION['nom_eleve'], $_SESSION['nom']);
	}
	header('Location: index.php');
	exit;
}

//var_dump($_SESSION);
if($_SESSION['saisie']=='debut')
{
	if($_SESSION['role']=='eleve'){

		$_SESSION['saisie']='';
		$_SESSION['nom']=$_SESSION['nom_client'];
		$_SESSION['message']="Bonjour ".$_SESSION['prenom_client'].", sélectionne ton évaluation.";
		$_SESSION['lien']="lienform1.php";

	}else{

		$_SESSION['message']="Présentez la carte de l’élève ou saisissez son code.";
		$_SESSION['lien']="lienform.php";
	}
}

$estEleve=($_SESSION['role']=='eleve');
$confirmation = isset($_SESSION['confirmation_saisie']) && is_array($_SESSION['confirmation_saisie'])
	? $_SESSION['confirmation_saisie']
	: null;

// Étape en cours pour l'indicateur visuel. Côté élève, la carte liée au compte
// est déjà reconnue : le parcours commence donc naturellement à l'étape 2.
$lien=isset($_SESSION['lien'])?$_SESSION['lien']:'';
if($lien=='lienform.php'){
	$etape=1;
	$aideSaisie='Carte élève : code à 6 chiffres';
	$placeholderSaisie='Bipe ou tape les 6 chiffres';
	$libelleEtape='Bipez la carte';
}elseif($lien=='lienform1.php'){
	$etape=2;
	$aideSaisie='Compétence ou connaissance : code à 9 caractères';
	$placeholderSaisie='Bipe ou tape les 9 caractères';
	$libelleEtape='Bipez la compétence';
}else{
	$etape=3;
	$libelleEtape='Bipez le résultat';
	if($lien=='comp/lienform2.php'){
		$aideSaisie='Résultat de la compétence';
		$placeholderSaisie='Bipe ou tape le résultat';
	}else{
		$aideSaisie='Note de la connaissance : de 0 à 10';
		$placeholderSaisie='Bipe ou tape la note';
	}
}
if (!$estEleve) $placeholderSaisie = str_replace('Bipe ou tape', 'Scannez ou saisissez', $placeholderSaisie ?? '');
if ($confirmation) {
	$etape = 4;
	$libelleEtape = 'Résultat enregistré';
}
$etatResultat = $confirmation
	? 'Résultat enregistré.'
	: ($etape === 3 ? 'En attente du résultat.' : 'Aucun résultat enregistré.');

// L'élève scanné est connu dès l'étape 2. Les nouveaux scans conservent son
// prénom et son nom séparément. Le repli sur le message de confirmation permet
// aussi de réparer immédiatement une saisie déjà ouverte avant cette mise à
// jour, sans obliger l'enseignant à rebiper la carte.
$eleveReconnu = null;
if ($estEleve) {
	$prenomEleve = trim((string)($_SESSION['prenom_client'] ?? ''));
	$nomEleve = trim((string)($_SESSION['nom_client'] ?? ''));
	if ($prenomEleve !== '') {
		$prenomEleve = mb_convert_case(mb_strtolower($prenomEleve, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
	}
	$eleveReconnu = trim($prenomEleve.' '.mb_strtoupper($nomEleve, 'UTF-8'));
} elseif ($etape >= 2 && !empty($_SESSION['nom'])) {
	$prenomEleve = trim((string)($_SESSION['prenom_eleve'] ?? ''));
	$nomEleve = trim((string)($_SESSION['nom_eleve'] ?? $_SESSION['nom']));
	if ($prenomEleve !== '') {
		$prenomEleve = mb_convert_case(mb_strtolower($prenomEleve, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
		$eleveReconnu = trim($prenomEleve.' '.mb_strtoupper($nomEleve, 'UTF-8'));
	} elseif (preg_match('/^Bonjour\s+(.+?),\s+sélectionne/u', (string)($_SESSION['message'] ?? ''), $nomDansMessage)) {
		$eleveReconnu = trim($nomDansMessage[1]);
	} else {
		$eleveReconnu = mb_strtoupper($nomEleve, 'UTF-8');
	}
}

// La recherche dans le référentiel (voir recherche-referentiel.php) n'a de
// sens que pour l'étape "compétence/connaissance" : à l'étape carte, on
// cherche un élève par son code, pas par du texte ; à l'étape résultat, le
// code attendu est fixe.
$rechercheDisponible = (!$confirmation && $lien==='lienform1.php');

$moisFr = [1=>'janvier',2=>'février',3=>'mars',4=>'avril',5=>'mai',6=>'juin',7=>'juillet',8=>'août',9=>'septembre',10=>'octobre',11=>'novembre',12=>'décembre'];
$dateAujourdhui = (int)date('j').' '.$moisFr[(int)date('n')].' '.date('Y');

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8" />
<title>Saisie des évaluations</title>
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<link rel="stylesheet" href="/galaxie-tokens.css?v=20260912-conformite">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="../utils/style-v2.css?v=20260912-conformite">
</head>

<body class="gx-typo gx-app-fasteval v2-fasteval">
<a class="skip-link" href="#contenu">Aller au contenu</a>

<header class="v2-app-header">
    <img class="logo" src="utils/logofasteval.png" alt="Fast Éval">
        <nav class="fil" aria-label="Fil d'Ariane"><a href="/portail/">Portail</a><span aria-hidden="true">›</span><a href="../presentation.php">Accueil</a><span aria-hidden="true">›</span><span class="actuel">Saisie</span></nav>
    <?php require_once $_SERVER['DOCUMENT_ROOT'].'/v2/galaxie-role.php'; echo gxMenuIdentite('Fast Éval', array(
        array('label' => 'Réinitialiser la saisie', 'href' => 'reset.php'),
    )); ?>
</header>

<main id="contenu" class="v2-page fe-saisie-focus gx-largeur-lecture">
    <section class="v2-page-hero gx-ligne-titre" aria-labelledby="titre-page">
        <span class="v2-page-hero-surtitre">Évaluations</span>
        <h1 id="titre-page">Saisie des évaluations</h1>
        <p><?php echo $estEleve
            ? 'Bipe le code de ton évaluation, puis ton résultat.'
            : 'Bipez la carte de l’élève, la compétence, puis le résultat.'; ?></p>
    </section>

    <div class="v2-etapes" role="list" aria-label="Étapes de la saisie">
        <div class="v2-etape<?php echo $etape>1?' faite':($etape==1?' active':''); ?>" role="listitem"<?php echo $etape === 1 ? ' aria-current="step"' : ''; ?>>
            <span class="puce"><?php echo $etape>1?'':'1'; ?></span><span class="label">Élève</span>
        </div>
        <div class="v2-etape<?php echo $etape>2?' faite':($etape==2?' active':''); ?>" role="listitem"<?php echo $etape === 2 ? ' aria-current="step"' : ''; ?>>
            <?php /* « Connaissances / Compétences » et pas « Compétence » : cette etape
                     accepte les deux, le champ le disait deja (« code a 9 caracteres »)
                     mais l'etape, elle, n'en annoncait qu'une. Signale par l’enseignante le
                     06/09/2026, capture a l'appui. */ ?>
            <span class="puce"><?php echo $etape>2?'':'2'; ?></span><span class="label">Connaissances / Compétences</span>
        </div>
        <div class="v2-etape<?php echo $etape>3?' faite':($etape==3?' active':''); ?>" role="listitem"<?php echo $etape === 3 ? ' aria-current="step"' : ''; ?>>
            <span class="puce"><?php echo $etape>3?'':'3'; ?></span><span class="label">Résultat</span>
        </div>
    </div>

    <div class="fe-contexte-saisie">
        <strong><?php echo $eleveReconnu ? htmlspecialchars($eleveReconnu) : 'Élève à identifier'; ?></strong>
        <span><?php echo htmlspecialchars($_SESSION['classe_client'] ?? ''); ?></span>
        <span><?php echo htmlspecialchars($dateAujourdhui); ?></span>
        <?php if (!$estEleve && $eleveReconnu) { ?><a href="reset.php">Changer d’élève</a><?php } ?>
    </div>
    <div class="v2-grille-saisie">
        <div class="v2-scanner<?php echo $confirmation?' confirmation':''; ?>">
            <?php if($confirmation){ ?>
            <p class="message">Résultat enregistré</p>
            <?php /* Seule ligne de cet ecran a ne pas suivre $estEleve : restait au
                     vouvoiement meme en session eleve, alors que la phrase juste
                     au-dessus du champ ("Bipe le code de ton evaluation") tutoie deja.
                     Trouve en comparant a la maquette Sol eleve du 05/09/2026, qui
                     tutoie ("Tu peux verifier ce resultat tranquillement"). */ ?>
            <p class="detail"><?php echo $estEleve
                ? 'Tu peux prendre le temps de le vérifier avant de continuer.'
                : 'Vous pouvez prendre le temps de le vérifier avant de continuer.'; ?></p>
            <?php
            // Niveau de maitrise (Non atteint/Partiel/Atteint/Depasse) : meme
            // classe que .journal-maitrise dans journal.php, pour que la couleur
            // du resultat dise la meme chose partout. Une note chiffree /10
            // (eval/lienform2.php) porte aussi ce niveau, calcule par seuils
            // (memes seuils que noteColor() du bulletin PDF), sans palier depasse.
            $niveauResultat = $confirmation['niveau'] ?? null;
            $classesNiveau = ['nonatteint' => 'non-atteint', 'patteint' => 'partiel', 'atteint' => 'atteint', 'depasse' => 'depasse'];
            $classeResultat = $niveauResultat && isset($classesNiveau[$niveauResultat]) ? ' ' . $classesNiveau[$niveauResultat] : '';
            ?>
            <div class="v2-recapitulatif-resultat">
                <span class="type"><?php echo htmlspecialchars($confirmation['type'] ?? 'Évaluation'); ?></span>
                <strong><?php echo htmlspecialchars($confirmation['intitule'] ?? 'Évaluation enregistrée'); ?></strong>
                <span class="resultat<?php echo $classeResultat; ?>"><?php echo htmlspecialchars($confirmation['resultat'] ?? 'Enregistré'); ?></span>
            </div>
            <form method="post" action="index.php" class="v2-continuer-saisie">
                <button type="submit" name="continuer_saisie" value="1">
                    <?php echo $estEleve?'Choisir une autre évaluation':'Biper la carte suivante'; ?>
                </button>
            </form>
            <?php } else { ?>
            <p class="message"><?php echo $_SESSION['message'] ?></p>
            <?php if(!empty($_SESSION['detail'])){ ?>
            <p class="detail"><?php echo $_SESSION['detail']; unset($_SESSION['detail']); ?></p>
            <?php } ?>

            <form method="post" action="<?php echo $_SESSION['lien']?>" name="formsaisie" id="formsaisie" onsubmit="return valider()">
                <label for="nom" class="aide-format"><?php echo htmlspecialchars($aideSaisie); ?></label>
                <input type="text" class="v2-champ-saisie" id="nom" name="nom" autofocus autocomplete="off" placeholder="<?php echo htmlspecialchars($placeholderSaisie); ?>" />
                <button type="button" class="fe-continuer" onclick="this.form.submit()">Continuer</button>
            </form>

            <?php if($rechercheDisponible){ ?>
            <div class="v2-separateur-ou">ou</div>
            <div class="v2-recherche-referentiel" id="rechercheReferentiel">
                <button type="button" class="lien-ouvrir" id="ouvrirRecherche" aria-expanded="false" aria-controls="champRechercheReferentiel">🔍 Rechercher dans le référentiel</button>
                <input type="text" class="champ-recherche" id="champRechercheReferentiel" placeholder="Tapez un mot de l’intitulé…" aria-label="Rechercher une compétence ou une connaissance" aria-describedby="etatRechercheReferentiel" autocomplete="off">
                <div class="resultats-recherche" id="resultatsRechercheReferentiel"></div>
                <p class="etat-recherche" id="etatRechercheReferentiel" role="status" aria-live="polite" hidden></p>
            </div>
            <?php } ?>
            <?php } ?>
        </div>

    </div>
    <p class="fe-etat-saisie" role="status"><?php echo htmlspecialchars($etatResultat); ?></p>

    <div class="v2-actions-saisie">
        <a href="reset.php">
            <img src="utils/douchette_grand.png" alt="">
            <span>Réinitialiser la saisie</span>
        </a>
        <a class="principale" href="journal.php">
            <img src="utils/journal.png" alt="">
            <span>Voir les enregistrements du jour</span>
        </a>
    </div>
</main>

<script src="../utils/ui-v2.js?v=20260903-01"></script>
<?php if(!$confirmation){ ?>
<script src="utils/douchette.js?v=20260903-02"></script>
<?php } ?>
<?php if($rechercheDisponible){ ?>
<script>
(function(){
	var zone = document.getElementById('rechercheReferentiel');
	var bouton = document.getElementById('ouvrirRecherche');
	var champ = document.getElementById('champRechercheReferentiel');
	var resultats = document.getElementById('resultatsRechercheReferentiel');
	var etat = document.getElementById('etatRechercheReferentiel');
	var champPrincipal = document.getElementById('nom');
	var minuteur = null;
	var numeroRecherche = 0;

	bouton.addEventListener('click', function(){
		zone.classList.toggle('ouverte');
		var ouverte = zone.classList.contains('ouverte');
		bouton.setAttribute('aria-expanded', ouverte ? 'true' : 'false');
		if (ouverte) { champ.focus(); }
	});

	function afficherEtat(texte){
		etat.textContent = texte;
		etat.hidden = !texte;
	}

	function rechercher(){
		var q = champ.value.trim();
		var rechercheCourante = ++numeroRecherche;
		resultats.classList.remove('visible');
		resultats.innerHTML = '';
		if (q.length < 2) { afficherEtat(''); return; }
		afficherEtat('Recherche…');
		fetch('recherche-referentiel.php?q=' + encodeURIComponent(q))
			.then(function(r){
				if (!r.ok) { throw new Error('Réponse HTTP ' + r.status); }
				return r.json();
			})
			.then(function(data){
				if (rechercheCourante !== numeroRecherche) { return; }
				var liste = data.resultats || [];
				if (liste.length === 0) { afficherEtat('Aucun résultat pour « ' + q + ' ».'); return; }
				afficherEtat('');
				liste.forEach(function(item){
					var b = document.createElement('button');
					b.type = 'button';
					b.className = 'resultat';
					var texte = document.createElement('span');
					texte.className = 'texte';
					texte.textContent = item.commentaire;
					var contexte = document.createElement('span');
					contexte.className = 'contexte';
					contexte.textContent = (item.type === 'competence' ? 'Compétence' : 'Connaissance') + ' · ' + item.matiere + (item.categorie ? ' · ' + item.categorie : '');
					b.appendChild(texte);
					b.appendChild(contexte);
					b.addEventListener('click', function(){
						// Un resultat choisi doit se comporter EXACTEMENT comme un
						// bipage reussi : meme champ, meme soumission - aucun
						// chemin different qui pourrait un jour diverger.
						champPrincipal.value = item.designation;
						document.getElementById('formsaisie').submit();
					});
					resultats.appendChild(b);
				});
				resultats.classList.add('visible');
			})
			.catch(function(){
				if (rechercheCourante === numeroRecherche) {
					afficherEtat('Recherche indisponible pour le moment.');
				}
			});
	}

	champ.addEventListener('input', function(){
		clearTimeout(minuteur);
		minuteur = setTimeout(rechercher, 300);
	});
})();
</script>
<?php } ?>

</body>
</html>
