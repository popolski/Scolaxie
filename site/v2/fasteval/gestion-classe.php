<?php
session_start();

/*
 * Cette page est atteinte par lien direct depuis Clic & Mots et depuis
 * School Monsters (« Gérer la classe dans Fast Éval »). Une enseignante
 * connectée au portail mais qui n'a pas encore ouvert Fast Éval dans ce
 * navigateur n'avait pas de session ici, et se retrouvait renvoyée sur
 * index.php, c'est-à-dire sur l'ancien portail. Le garde-fou ouvre
 * maintenant la session à partir du cookie commun.
 */
require_once __DIR__ . '/utils/session-sso.php';
fastevalExigerEnseignant();

// require_once et non require : utils/session-sso.php charge deja cette
// classe, et une seconde inclusion la redeclarait, ce qui coupait la page
// avec une erreur 500.
require_once __DIR__ . '/utils/class/class_bdd.php';

$dbh=bdd::connexion($_SESSION['bdd']);
$dbh->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_WARNING);

$idEnseignant=$_SESSION['id_enseignant'];
$message='';

function genererCodeCarte($dbh,$idEnseignant){
	do{
		$code=(string)random_int(100000,999999);
		$req=$dbh->prepare('SELECT id_eleve FROM classe WHERE id_enseignant=:id_enseignant AND code_carte=:code_carte');
		$req->bindParam(':id_enseignant',$idEnseignant);
		$req->bindParam(':code_carte',$code);
		$req->execute();
		$existe=(bool)$req->fetch();
		$req->closeCursor();
	}while($existe);
	return $code;
}

if(isset($_POST['action'])){

	if($_POST['action']=='ajouter'){

		$nom=strtoupper(htmlspecialchars(trim($_POST['nom'])));
		$prenom=htmlspecialchars(trim($_POST['prenom']));
		$dateNaiss=htmlspecialchars($_POST['date_naiss']);
		$code=htmlspecialchars(trim($_POST['code']));

		if($nom==''||$prenom==''){
			$message="Le nom et le prénom sont obligatoires.";
		}else{

			$req=$dbh->prepare('SELECT id_eleve FROM classe WHERE nom=:nom AND LOWER(prenom)=LOWER(:prenom) AND id_enseignant=:id_enseignant');
			$req->bindParam(':nom',$nom);
			$req->bindParam(':prenom',$prenom);
			$req->bindParam(':id_enseignant',$idEnseignant);
			$req->execute();

			if($req->fetch()){
				$message="Un élève de ce nom et prénom existe déjà dans la classe.";
			}else{
				$collision=false;
				if($code!==''){
					$reqCollision=$dbh->prepare('SELECT id_eleve FROM classe WHERE id_enseignant=:id_enseignant AND LOWER(prenom)=LOWER(:prenom) AND code=:code');
					$reqCollision->bindParam(':id_enseignant',$idEnseignant);
					$reqCollision->bindParam(':prenom',$prenom);
					$reqCollision->bindParam(':code',$code);
					$reqCollision->execute();
					$collision=(bool)$reqCollision->fetch();
					$reqCollision->closeCursor();
				}

				if($collision){
					$message="Un autre élève prénommé ".$prenom." utilise déjà ce mot de passe. Choisissez-en un différent pour éviter toute confusion à la connexion.";
				}else{
					$codeCarte=genererCodeCarte($dbh,$idEnseignant);
					$req=$dbh->prepare('INSERT INTO classe (nom,prenom,date_naiss,id_enseignant,code,code_carte) VALUES (:nom,:prenom,:date_naiss,:id_enseignant,:code,:code_carte)');
					$req->bindParam(':nom',$nom);
					$req->bindParam(':prenom',$prenom);
					$req->bindParam(':date_naiss',$dateNaiss);
					$req->bindParam(':id_enseignant',$idEnseignant);
					$req->bindParam(':code',$code);
					$req->bindParam(':code_carte',$codeCarte);
					$req->execute();
					$message=ucfirst(strtolower($prenom))." ".$nom." a été ajouté(e) à la classe. Code de sa carte : ".$codeCarte.".";
				}
			}
			$req->closeCursor();
		}
	}

	if($_POST['action']=='modifier_code'&&isset($_POST['id_eleve'])){

		$code=htmlspecialchars(trim($_POST['code']));
		$idEleve=$_POST['id_eleve'];

		$req=$dbh->prepare('SELECT prenom FROM classe WHERE id_eleve=:id_eleve AND id_enseignant=:id_enseignant');
		$req->bindParam(':id_eleve',$idEleve);
		$req->bindParam(':id_enseignant',$idEnseignant);
		$req->execute();
		$ligne=$req->fetch(PDO::FETCH_ASSOC);
		$req->closeCursor();

		$collision=false;
		if($code!==''&&$ligne){
			$reqCollision=$dbh->prepare('SELECT id_eleve FROM classe WHERE id_enseignant=:id_enseignant AND LOWER(prenom)=LOWER(:prenom) AND code=:code AND id_eleve!=:id_eleve');
			$reqCollision->bindParam(':id_enseignant',$idEnseignant);
			$reqCollision->bindParam(':prenom',$ligne['prenom']);
			$reqCollision->bindParam(':code',$code);
			$reqCollision->bindParam(':id_eleve',$idEleve);
			$reqCollision->execute();
			$collision=(bool)$reqCollision->fetch();
			$reqCollision->closeCursor();
		}

		if($collision){
			$message="Un autre élève prénommé ".$ligne['prenom']." utilise déjà ce mot de passe. Choisissez-en un différent pour éviter toute confusion à la connexion.";
		}else{
			$req=$dbh->prepare('UPDATE classe SET code=:code WHERE id_eleve=:id_eleve AND id_enseignant=:id_enseignant');
			$req->bindParam(':code',$code);
			$req->bindParam(':id_eleve',$idEleve);
			$req->bindParam(':id_enseignant',$idEnseignant);
			$req->execute();
			$req->closeCursor();

			$message="Le mot de passe a été mis à jour.";
		}
	}

	if($_POST['action']=='modifier_eleve'&&isset($_POST['id_eleve'])){

		$idEleve=filter_var($_POST['id_eleve'],FILTER_VALIDATE_INT)?:0;
		$nom=strtoupper(is_string($_POST['nom']??null)?trim($_POST['nom']):'');
		$prenom=is_string($_POST['prenom']??null)?trim($_POST['prenom']):'';
		$dateNaiss=is_string($_POST['date_naiss']??null)?trim($_POST['date_naiss']):'';

		$dateValide=$dateNaiss==='';
		if(!$dateValide&&preg_match('/^(\d{4})-(\d{2})-(\d{2})$/',$dateNaiss,$date)){
			$dateValide=checkdate((int)$date[2],(int)$date[3],(int)$date[1]);
		}

		if($nom===''||$prenom===''){
			$message="Le nom et le prénom sont obligatoires.";
		}elseif(!$dateValide){
			$message="La date de naissance n'est pas valide.";
		}else{
			$req=$dbh->prepare('SELECT code FROM classe WHERE id_eleve=:id_eleve AND id_enseignant=:id_enseignant');
			$req->bindValue(':id_eleve',$idEleve,PDO::PARAM_INT);
			$req->bindParam(':id_enseignant',$idEnseignant);
			$req->execute();
			$cible=$req->fetch(PDO::FETCH_ASSOC);
			$req->closeCursor();

			$req=$dbh->prepare('SELECT id_eleve FROM classe WHERE nom=:nom AND LOWER(prenom)=LOWER(:prenom) AND id_enseignant=:id_enseignant AND id_eleve!=:id_eleve');
			$req->bindParam(':nom',$nom);
			$req->bindParam(':prenom',$prenom);
			$req->bindParam(':id_enseignant',$idEnseignant);
			$req->bindValue(':id_eleve',$idEleve,PDO::PARAM_INT);
			$req->execute();
			$identiteExiste=(bool)$req->fetch();
			$req->closeCursor();

			$collisionCode=false;
			if($cible&&$cible['code']!==''){
				$req=$dbh->prepare('SELECT id_eleve FROM classe WHERE id_enseignant=:id_enseignant AND LOWER(prenom)=LOWER(:prenom) AND code=:code AND id_eleve!=:id_eleve');
				$req->bindParam(':id_enseignant',$idEnseignant);
				$req->bindParam(':prenom',$prenom);
				$req->bindParam(':code',$cible['code']);
				$req->bindValue(':id_eleve',$idEleve,PDO::PARAM_INT);
				$req->execute();
				$collisionCode=(bool)$req->fetch();
				$req->closeCursor();
			}

			if(!$cible){
				$message="Élève introuvable.";
			}elseif($identiteExiste){
				$message="Un élève de ce nom et prénom existe déjà dans la classe.";
			}elseif($collisionCode){
				$message="Un autre élève prénommé ".$prenom." utilise déjà ce mot de passe. Modifiez d'abord son mot de passe.";
			}else{
				$req=$dbh->prepare('UPDATE classe SET nom=:nom,prenom=:prenom,date_naiss=:date_naiss WHERE id_eleve=:id_eleve AND id_enseignant=:id_enseignant');
				$req->bindParam(':nom',$nom);
				$req->bindParam(':prenom',$prenom);
				$req->bindValue(':date_naiss',$dateNaiss===''?null:$dateNaiss,$dateNaiss===''?PDO::PARAM_NULL:PDO::PARAM_STR);
				$req->bindValue(':id_eleve',$idEleve,PDO::PARAM_INT);
				$req->bindParam(':id_enseignant',$idEnseignant);
				$req->execute();
				$req->closeCursor();
				$message="Les informations de ".ucfirst($prenom)." ".$nom." ont été mises à jour.";
			}
		}
	}

	if($_POST['action']=='regenerer_carte'&&isset($_POST['id_eleve'])){

		$idEleve=$_POST['id_eleve'];
		$codeCarte=genererCodeCarte($dbh,$idEnseignant);

		$req=$dbh->prepare('UPDATE classe SET code_carte=:code_carte WHERE id_eleve=:id_eleve AND id_enseignant=:id_enseignant');
		$req->bindParam(':code_carte',$codeCarte);
		$req->bindParam(':id_eleve',$idEleve);
		$req->bindParam(':id_enseignant',$idEnseignant);
		$req->execute();
		$req->closeCursor();

		$message="Nouveau code de carte : ".$codeCarte.".";
	}

	if($_POST['action']=='generer_codes_manquants'){

		$req=$dbh->prepare('SELECT id_eleve FROM classe WHERE id_enseignant=:id_enseignant AND (code_carte IS NULL OR code_carte=\'\')');
		$req->bindParam(':id_enseignant',$idEnseignant);
		$req->execute();
		$sansCode=$req->fetchAll(PDO::FETCH_ASSOC);
		$req->closeCursor();

		foreach($sansCode as $ligneEleve){
			$codeCarte=genererCodeCarte($dbh,$idEnseignant);
			$reqMaj=$dbh->prepare('UPDATE classe SET code_carte=:code_carte WHERE id_eleve=:id_eleve');
			$reqMaj->bindParam(':code_carte',$codeCarte);
			$reqMaj->bindParam(':id_eleve',$ligneEleve['id_eleve']);
			$reqMaj->execute();
			$reqMaj->closeCursor();
		}

		$message=count($sansCode)." élève(s) ont reçu un nouveau code de carte.";
	}

	// -----------------------------------------------------------------
	// Désactiver un élève, plutôt que le supprimer.
	//
	// La colonne « actif » existe depuis la migration v1 et le portail la
	// respecte déjà : un élève désactivé ne peut plus se connecter, ni ici,
	// ni sur School Monsters, ni sur Clic & Mots. Mais rien ne permettait de
	// la mettre à 0 : le seul geste offert était la suppression définitive,
	// qui emporte aussi toutes les évaluations.
	//
	// Un départ en cours d'année, une fin d'année, un élève qui change
	// d'école : dans tous ces cas on veut fermer l'accès sans effacer le
	// travail. C'est ce que fait ce bouton, et il se défait.
	// -----------------------------------------------------------------
	if($_POST['action']=='basculer_actif'&&isset($_POST['id_eleve'])){

		$idEleve=(int)$_POST['id_eleve'];

		$req=$dbh->prepare('SELECT prenom,actif FROM classe WHERE id_eleve=:id_eleve AND id_enseignant=:id_enseignant');
		$req->bindParam(':id_eleve',$idEleve,PDO::PARAM_INT);
		$req->bindParam(':id_enseignant',$idEnseignant);
		$req->execute();
		$cible=$req->fetch(PDO::FETCH_ASSOC);
		$req->closeCursor();

		if(!$cible){
			$message="Élève introuvable.";
		}else{
			$nouvelEtat=((int)$cible['actif']===1)?0:1;
			$req=$dbh->prepare('UPDATE classe SET actif=:actif WHERE id_eleve=:id_eleve AND id_enseignant=:id_enseignant');
			$req->bindParam(':actif',$nouvelEtat,PDO::PARAM_INT);
			$req->bindParam(':id_eleve',$idEleve,PDO::PARAM_INT);
			$req->bindParam(':id_enseignant',$idEnseignant);
			$req->execute();
			$req->closeCursor();
			$message=$nouvelEtat===1
				?ucfirst($cible['prenom'])." peut à nouveau se connecter aux trois sites."
				:ucfirst($cible['prenom'])." ne peut plus se connecter. Ses évaluations et ses résultats sont conservés ; tu peux le réactiver quand tu veux.";
		}
	}

	if($_POST['action']=='supprimer'&&isset($_POST['id_eleve'])){

		$idEleve=$_POST['id_eleve'];

		$req=$dbh->prepare('DELETE FROM eval_eleves WHERE id_eleve=:id_eleve AND id_enseignant=:id_enseignant');
		$req->bindParam(':id_eleve',$idEleve);
		$req->bindParam(':id_enseignant',$idEnseignant);
		$req->execute();

		$req=$dbh->prepare('DELETE FROM comp_eleves WHERE id_eleve=:id_eleve AND id_enseignant=:id_enseignant');
		$req->bindParam(':id_eleve',$idEleve);
		$req->bindParam(':id_enseignant',$idEnseignant);
		$req->execute();

		$req=$dbh->prepare('DELETE FROM classe WHERE id_eleve=:id_eleve AND id_enseignant=:id_enseignant');
		$req->bindParam(':id_eleve',$idEleve);
		$req->bindParam(':id_enseignant',$idEnseignant);
		$req->execute();
		$nb=$req->rowCount();
		$req->closeCursor();

		$message=$nb>0?"L'élève et toutes ses évaluations ont été supprimés.":"Élève introuvable.";
	}

	if($_POST['action']=='supprimer_classe'){

		$req=$dbh->prepare('DELETE FROM eval_eleves WHERE id_enseignant=:id_enseignant');
		$req->bindParam(':id_enseignant',$idEnseignant);
		$req->execute();

		$req=$dbh->prepare('DELETE FROM comp_eleves WHERE id_enseignant=:id_enseignant');
		$req->bindParam(':id_enseignant',$idEnseignant);
		$req->execute();

		$req=$dbh->prepare('DELETE FROM classe WHERE id_enseignant=:id_enseignant');
		$req->bindParam(':id_enseignant',$idEnseignant);
		$req->execute();
		$nb=$req->rowCount();
		$req->closeCursor();

		$message=$nb." élève(s) supprimé(s) pour la fin d'année.";
	}
}

$req=$dbh->prepare('SELECT id_eleve,nom,prenom,date_naiss,code,code_carte,actif FROM classe WHERE id_enseignant=:id_enseignant ORDER BY actif DESC, nom ASC');
$req->bindParam(':id_enseignant',$idEnseignant);
$req->execute();
$eleves=$req->fetchAll(PDO::FETCH_ASSOC);
$req->closeCursor();

// Stats réelles pour l'en-tête - pas de statut inventé : le seul état
// existant en base est actif/inactif (voir plus haut, colonne "actif").
$nbActifs = 0;
foreach ($eleves as $e) { if ((int)$e['actif'] === 1) { $nbActifs++; } }
$nbDesactives = count($eleves) - $nbActifs;

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Gérer ma classe</title>
<link rel="stylesheet" href="/galaxie-tokens.css?v=20260912-conformite">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="utils/style-v2.css?v=20260912-conformite">
</head>

<body id="haut-page" class="gx-typo gx-app-fasteval v2-fasteval">
<a class="skip-link" href="#contenu">Aller au contenu</a>

<header class="v2-app-header">
    <img class="logo" src="utils/img/logofasteval.png" alt="Fast Éval">
        <nav class="fil" aria-label="Fil d'Ariane">
        <a href="/portail/">Portail</a><span aria-hidden="true">›</span>
        <a href="presentation.php">Accueil</a>
        <span aria-hidden="true">›</span>
        <span class="actuel">Gérer ma classe</span>
    </nav>
    <?php require_once $_SERVER['DOCUMENT_ROOT'].'/v2/galaxie-role.php'; echo gxMenuIdentite('Fast Éval'); ?>
</header>

<main id="contenu" class="v2-page gx-largeur-grille">
    <section class="v2-page-hero gx-ligne-titre" aria-labelledby="titre-page">
        <span class="v2-page-hero-surtitre">La classe</span>
        <h1 id="titre-page">Gérer ma classe</h1>
        <p>Gérez les accès des élèves, leurs cartes de membre et les arrivées en cours d'année.</p>
    </section>

    <div class="v2-actions-hautes">
        <a href="#ajouter-eleve" onclick="document.getElementById('ajouter-eleve').open=true" class="v2-bouton-principal">+ Ajouter un élève</a>
    </div>

    <div class="v2-stats">
        <div class="v2-stat"><b><?php echo count($eleves); ?></b><span>élève<?php echo count($eleves)>1?'s':''; ?></span></div>
        <div class="v2-stat"><b><?php echo $nbActifs; ?></b><span>accès actif<?php echo $nbActifs>1?'s':''; ?></span></div>
        <?php if($nbDesactives>0){ ?>
        <div class="v2-stat attention"><b><?php echo $nbDesactives; ?></b><span>accès désactivé<?php echo $nbDesactives>1?'s':''; ?></span></div>
        <?php } ?>
    </div>

    <?php if($message!=''){ ?>
    <div class="v2-message"><?php echo htmlspecialchars($message); ?></div>
    <?php } ?>

    <div class="v2-section">
        <div class="v2-section-entete">
            <div>
                <h2>Élèves de la classe</h2>
                <p>Modifiez les accès et préparez les cartes de membre de chaque élève.</p>
            </div>
            <span class="v2-compteur" id="compteurElevesFiltres"><?php echo count($eleves); ?></span>
        </div>

        <?php if(count($eleves)>0){ ?>
        <div class="v2-table-outils" role="search" aria-label="Rechercher et filtrer les élèves">
            <div class="v2-champ-outil">
                <label for="rechercheEleves">Rechercher un élève</label>
                <input type="search" id="rechercheEleves" placeholder="Nom ou prénom" autocomplete="off">
            </div>
            <div class="v2-champ-outil">
                <label for="filtreAccesEleves">Filtrer les accès</label>
                <select id="filtreAccesEleves">
                    <option value="tous">Tous les élèves</option>
                    <option value="actif">Accès actifs</option>
                    <option value="desactive">Accès désactivés</option>
                </select>
            </div>
            <p class="v2-resultats-compteur" id="texteResultatsEleves" aria-live="polite"><?php echo count($eleves); ?> élève<?php echo count($eleves)>1?'s':''; ?></p>
        </div>
        <?php } ?>

        <div class="v2-table-scroll">
        <table class="v2-table" data-liste-eleves>
            <caption class="sr-only">Élèves de la classe et gestion de leurs accès</caption>
            <thead>
                <tr><th>Nom</th><th>Prénom</th><th>Naissance</th><th>Mot de passe</th><th>Code carte</th><th>Accès</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach($eleves as $eleve){ ?>
                <?php $estActif=((int)$eleve['actif']===1); ?>
                <tr<?php echo $estActif?'':' class="desactive"'; ?> data-recherche="<?php echo htmlspecialchars($eleve['prenom'].' '.$eleve['nom'],ENT_QUOTES,'UTF-8'); ?>" data-acces="<?php echo $estActif?'actif':'desactive'; ?>">
                    <td data-label="Nom"><?php echo strtoupper(htmlspecialchars($eleve['nom'])); ?></td>
                    <td data-label="Prénom"><?php echo ucfirst(htmlspecialchars($eleve['prenom'])); ?></td>
                    <td data-label="Naissance"><?php echo $eleve['date_naiss']!=null?date("d/m/Y",strtotime($eleve['date_naiss'])):''; ?></td>
                    <td data-label="Mot de passe">
                        <form method="post" action="gestion-classe.php" class="v2-code-form">
                            <input type="hidden" name="action" value="modifier_code">
                            <input type="hidden" name="id_eleve" value="<?php echo (int)$eleve['id_eleve']; ?>">
                            <input type="password" name="code" id="code-<?php echo (int)$eleve['id_eleve']; ?>" value="<?php echo htmlspecialchars($eleve['code']); ?>" placeholder="mot de passe" aria-label="Mot de passe de <?php echo htmlspecialchars($eleve['prenom'].' '.$eleve['nom']); ?>" autocomplete="off">
                            <button type="button" class="v2-afficher-code" data-cible="code-<?php echo (int)$eleve['id_eleve']; ?>" aria-label="Afficher le mot de passe de <?php echo htmlspecialchars($eleve['prenom'].' '.$eleve['nom']); ?>">Afficher</button>
                            <button type="submit" aria-label="Enregistrer le mot de passe de <?php echo htmlspecialchars($eleve['prenom'].' '.$eleve['nom']); ?>">OK</button>
                        </form>
                    </td>
                    <td data-label="Code carte">
                        <div class="v2-carte-zone">
                            <span class="v2-code-carte"><?php echo $eleve['code_carte']!=null?htmlspecialchars($eleve['code_carte']):'aucun'; ?></span>
                        </div>
                    </td>
                    <td class="v2-colonne-acces" data-label="Accès">
                        <form method="post" action="gestion-classe.php">
                            <input type="hidden" name="action" value="basculer_actif">
                            <input type="hidden" name="id_eleve" value="<?php echo (int)$eleve['id_eleve']; ?>">
                            <?php if($estActif){ ?>
                            <button type="submit" class="v2-btn-mini" title="Ferme l'accès aux trois sites sans rien effacer. Réversible.">Désactiver</button>
                            <?php }else{ ?>
                            <span class="v2-etat-desactive">Désactivé</span>
                            <button type="submit" class="v2-btn-mini" title="Rouvre l'accès aux trois sites.">Réactiver</button>
                            <?php } ?>
                        </form>
                    </td>
                    <td data-label="Actions">
                        <details class="v2-actions-contextuelles">
                            <summary>Actions <span aria-hidden="true">⌄</span></summary>
                            <div class="v2-menu-actions">
								<button type="button" class="v2-btn-mini" data-ouvrir-dialogue="modifier-eleve-<?php echo (int)$eleve['id_eleve']; ?>">Modifier l'élève</button>
                                <?php if($eleve['code_carte']!=null){ ?>
                                <a href="ressources/carte-photo.php?id_eleve=<?php echo (int)$eleve['id_eleve']; ?>" class="v2-lien-mini">Préparer la photo et la carte</a>
                                <?php } ?>
                                <form method="post" action="gestion-classe.php" onsubmit="return confirm('Générer un nouveau code de carte pour cet élève ? L\'ancienne carte ne fonctionnera plus.');">
                                    <input type="hidden" name="action" value="regenerer_carte">
                                    <input type="hidden" name="id_eleve" value="<?php echo (int)$eleve['id_eleve']; ?>">
                                    <button type="submit" class="v2-btn-mini" title="Crée uniquement un nouveau code de carte à 6 chiffres. L’élève, sa photo et ses évaluations restent inchangés.">Régénérer la carte</button>
                                </form>
                                <form method="post" action="gestion-classe.php" onsubmit="return confirm('Supprimer définitivement cet élève et toutes ses évaluations ? Cette action est irréversible et efface son travail. Pour lui fermer l'accès en gardant ses évaluations, utilise plutôt « Désactiver ».');">
                                    <input type="hidden" name="action" value="supprimer">
                                    <input type="hidden" name="id_eleve" value="<?php echo (int)$eleve['id_eleve']; ?>">
                                    <button type="submit" class="v2-btn-mini discret">Supprimer définitivement</button>
                                </form>
                            </div>
                        </details>
                    </td>
                </tr>
                <?php } ?>
                <tr class="v2-ligne-vide" id="aucunEleveFiltre" hidden><td colspan="7">Aucun élève ne correspond à ces critères.</td></tr>
                <?php if(count($eleves)==0){ ?>
                <tr><td colspan="7" style="text-align:center;color:var(--v2-muted)">Aucun élève.</td></tr>
                <?php } ?>
            </tbody>
        </table>
        </div>

		<?php foreach($eleves as $eleve){ ?>
		<dialog id="modifier-eleve-<?php echo (int)$eleve['id_eleve']; ?>" class="v2-section" style="width:min(620px,calc(100% - 32px));box-sizing:border-box">
			<h2 id="titre-modifier-eleve-<?php echo (int)$eleve['id_eleve']; ?>">Modifier l'élève</h2>
			<form method="post" action="gestion-classe.php" class="v2-form-large" aria-labelledby="titre-modifier-eleve-<?php echo (int)$eleve['id_eleve']; ?>">
				<input type="hidden" name="action" value="modifier_eleve">
				<input type="hidden" name="id_eleve" value="<?php echo (int)$eleve['id_eleve']; ?>">
				<div class="v2-champ-bloc">
					<label for="nom-<?php echo (int)$eleve['id_eleve']; ?>">Nom</label>
					<input type="text" name="nom" id="nom-<?php echo (int)$eleve['id_eleve']; ?>" value="<?php echo htmlspecialchars($eleve['nom'],ENT_QUOTES,'UTF-8'); ?>" required>
				</div>
				<div class="v2-champ-bloc">
					<label for="prenom-<?php echo (int)$eleve['id_eleve']; ?>">Prénom</label>
					<input type="text" name="prenom" id="prenom-<?php echo (int)$eleve['id_eleve']; ?>" value="<?php echo htmlspecialchars($eleve['prenom'],ENT_QUOTES,'UTF-8'); ?>" required>
				</div>
				<div class="v2-champ-bloc">
					<label for="date-naiss-<?php echo (int)$eleve['id_eleve']; ?>">Date de naissance</label>
					<input type="date" name="date_naiss" id="date-naiss-<?php echo (int)$eleve['id_eleve']; ?>" value="<?php echo htmlspecialchars((string)$eleve['date_naiss'],ENT_QUOTES,'UTF-8'); ?>">
				</div>
				<div class="v2-actions-bas">
					<button type="submit" class="v2-bouton-principal">Enregistrer</button>
					<button type="button" class="v2-bouton" data-fermer-dialogue>Annuler</button>
				</div>
			</form>
		</dialog>
		<?php } ?>

        <div class="v2-actions-bas">
            <form method="post" action="gestion-classe.php">
                <input type="hidden" name="action" value="generer_codes_manquants">
                <button type="submit" class="v2-bouton">Générer les codes carte manquants</button>
            </form>
            <a href="ressources/cartes-eleves.php" class="v2-bouton-principal" target="_blank" rel="noopener">Prévisualiser et imprimer les cartes</a>
        </div>
    </div>

    <details class="v2-section fe-ajout-eleve" id="ajouter-eleve">
        <summary>Ajouter un élève</summary>
        <p>Le code de sa carte sera créé automatiquement.</p>
        <form method="post" action="gestion-classe.php" class="v2-form-large">
            <input type="hidden" name="action" value="ajouter">
            <div class="v2-champ-bloc">
                <label for="nom">Nom</label>
                <input type="text" name="nom" id="nom" required>
            </div>
            <div class="v2-champ-bloc">
                <label for="prenom">Prénom</label>
                <input type="text" name="prenom" id="prenom" required>
            </div>
            <div class="v2-champ-bloc">
                <label for="date_naiss">Date de naissance</label>
                <input type="date" name="date_naiss" id="date_naiss">
            </div>
            <div class="v2-champ-bloc">
                <label for="code">Mot de passe (accès élève)</label>
                <input type="text" name="code" id="code" placeholder="ex : un mot simple ou un chiffre" autocomplete="off">
            </div>
            <button type="submit" class="v2-bouton-principal">Ajouter</button>
        </form>
    </details>

    <details class="v2-section v2-zone-danger">
        <summary>
            <div class="v2-section-entete">
                <div>
                    <h3>Fin d'année</h3>
                    <p>Action exceptionnelle, à utiliser avec prudence.</p>
                </div>
            </div>
            <span class="chevron" aria-hidden="true">⌄</span>
        </summary>
        <div class="contenu-danger">
            <p style="margin:0 0 14px;color:var(--v2-muted);font-size:0.9rem">Supprime tous les élèves de la classe et toutes leurs évaluations, d'un coup et définitivement.</p>
            <form method="post" action="gestion-classe.php" onsubmit="return confirm('Supprimer DÉFINITIVEMENT tous les élèves de la classe et toutes leurs évaluations ? Cette action est irréversible.');">
                <input type="hidden" name="action" value="supprimer_classe">
                <button type="submit" class="v2-bouton-danger">Supprimer toute la classe (fin d'année)</button>
            </form>
        </div>
    </details>

    <a href="#haut-page" class="v2-retour-haut">↑ Haut de page</a>
</main>

<script src="utils/ui-v2.js?v=20260903-01"></script>
<script>
document.querySelectorAll('.v2-afficher-code').forEach(function (bouton) {
    bouton.addEventListener('click', function () {
        var champ = document.getElementById(bouton.dataset.cible);
        if (!champ) return;
        var masque = champ.type === 'password';
        champ.type = masque ? 'text' : 'password';
        bouton.textContent = masque ? 'Masquer' : 'Afficher';
        bouton.setAttribute('aria-label', (masque ? 'Masquer ' : 'Afficher ') + champ.getAttribute('aria-label').replace(/^(Afficher|Masquer) /, ''));
    });
});
document.querySelectorAll('[data-ouvrir-dialogue]').forEach(function (bouton) {
	 bouton.addEventListener('click', function () {
		 document.getElementById(bouton.dataset.ouvrirDialogue).showModal();
	 });
});
document.querySelectorAll('[data-fermer-dialogue]').forEach(function (bouton) {
	 bouton.addEventListener('click', function () {
		 bouton.closest('dialog').close();
	 });
});
</script>

</body>
</html>
