<?php
session_start();

if(!$_SESSION['permission']){
	header('location:index.php');
	exit();
}

if($_SESSION['role']!='enseignant'){
	header('location:presentation.php');
	exit();
}

require('utils/class/class_bdd.php');

$dbh=bdd::connexion($_SESSION['bdd']);
$dbh->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_WARNING);

$message='';

function nettoyerTexte($texte){
	if($texte===null){ return ''; }
	if(!mb_check_encoding($texte,'UTF-8')){
		$texte=mb_convert_encoding($texte,'UTF-8','ISO-8859-1');
	}
	return trim($texte);
}

function lienTri($colonne,$triActuel,$directionActuelle){
	$parametres=$_GET;
	unset($parametres['modifier']);
	$parametres['tri']=$colonne;
	$parametres['direction']=($triActuel==$colonne&&$directionActuelle=='asc')?'desc':'asc';
	return 'liste-connaissances.php?'.http_build_query($parametres);
}

function genererDesignationEval($dbh){
	$caracteres='ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
	do{
		$designation='';
		for($i=0;$i<9;$i++){
			$designation.=$caracteres[random_int(0,strlen($caracteres)-1)];
		}
		$req=$dbh->prepare('SELECT id_eval FROM eval_type WHERE designation=:designation');
		$req->bindParam(':designation',$designation);
		$req->execute();
		$existe=(bool)$req->fetch();
		$req->closeCursor();
	}while($existe);
	return $designation;
}

/**
 * Lit un couple « liste deroulante + champ libre » : les menus Matiere et
 * Categorie sont de vraies listes depuis le 06/09/2026, avec une entree
 * « Autre… » qui ouvre un champ de saisie. Meme fonction, meme raison et meme
 * commentaire detaille que dans liste-competences.php.
 */
function valeurListeOuLibre($nomListe,$nomLibre){
	$choix=isset($_POST[$nomListe])?trim((string)$_POST[$nomListe]):'';
	if($choix==='__autre__'){
		return isset($_POST[$nomLibre])?trim((string)$_POST[$nomLibre]):'';
	}
	return $choix;
}

if(isset($_POST['action'])&&$_POST['action']=='creer'){

	$matiere=valeurListeOuLibre('matiere','matiere_autre');
	$categorie=valeurListeOuLibre('categorie','categorie_autre');
	$commentaire=trim($_POST['commentaire']);

	if($matiere==''||$categorie==''||$commentaire==''){

		$message="Matière, catégorie et intitulé sont obligatoires.";

	}else{

		$designation=genererDesignationEval($dbh);

		$req=$dbh->prepare('INSERT INTO eval_type (designation,commentaire,matiere,categorie,niveau) VALUES (:designation,:commentaire,:matiere,:categorie,1)');
		$req->bindParam(':designation',$designation);
		$req->bindParam(':commentaire',$commentaire);
		$req->bindParam(':matiere',$matiere);
		$req->bindParam(':categorie',$categorie);
		$req->execute();

		$message="Évaluation créée. Code : ".$designation.".";
	}
}

if(isset($_POST['action'])&&$_POST['action']=='modifier'){

	$idEval=(int)$_POST['id_eval'];
	$matiere=valeurListeOuLibre('matiere','matiere_autre');
	$categorie=valeurListeOuLibre('categorie','categorie_autre');
	$commentaire=trim($_POST['commentaire']);

	if($matiere==''||$categorie==''||$commentaire==''){

		$message="Matière, catégorie et intitulé sont obligatoires.";

	}else{

		$req=$dbh->prepare('UPDATE eval_type SET commentaire=:commentaire,matiere=:matiere,categorie=:categorie WHERE id_eval=:id_eval');
		$req->bindParam(':commentaire',$commentaire);
		$req->bindParam(':matiere',$matiere);
		$req->bindParam(':categorie',$categorie);
		$req->bindParam(':id_eval',$idEval);
		$req->execute();

		$message="Connaissance modifiée.";
	}
}

if(isset($_POST['action'])&&$_POST['action']=='supprimer'){

	$idEval=(int)$_POST['id_eval'];

	$req=$dbh->prepare('SELECT COUNT(*) FROM eval_eleves WHERE id_eval=:id_eval');
	$req->bindParam(':id_eval',$idEval);
	$req->execute();
	$nbUtilisation=(int)$req->fetchColumn();
	$req->closeCursor();

	if($nbUtilisation>0){

		$message="Impossible de supprimer cette connaissance : elle a déjà été utilisée ".$nbUtilisation." fois.";

	}else{

		$req=$dbh->prepare('DELETE FROM eval_type WHERE id_eval=:id_eval');
		$req->bindParam(':id_eval',$idEval);
		$req->execute();

		$message="Connaissance supprimée.";
	}
}

if(isset($_POST['action'])&&$_POST['action']=='renommer_matiere'){

	$ancienneMatiere=trim($_POST['ancienne_matiere']);
	$nouvelleMatiere=trim($_POST['nouvelle_matiere']);

	if($ancienneMatiere==''||$nouvelleMatiere==''){

		$message="Choisis une matière et donne-lui un nouveau nom.";

	}else{

		// Comparaison sur la valeur nettoyée plutôt qu'un WHERE matiere=... exact :
		// d'anciennes lignes peuvent porter un encodage légèrement différent qui
		// s'affiche pourtant à l'identique une fois passé par nettoyerTexte().
		// Un simple UPDATE par égalité stricte les laisserait orphelines.
		$nbComp=0;
		foreach($dbh->query('SELECT DISTINCT matiere FROM comp_type')->fetchAll(PDO::FETCH_COLUMN) as $matiereBrute){
			if(nettoyerTexte($matiereBrute)!==$ancienneMatiere){ continue; }
			$maj=$dbh->prepare('UPDATE comp_type SET matiere=:nouvelle WHERE matiere=:brute');
			$maj->bindValue(':nouvelle',$nouvelleMatiere);
			$maj->bindValue(':brute',$matiereBrute);
			$maj->execute();
			$nbComp+=$maj->rowCount();
		}

		$nbEval=0;
		foreach($dbh->query('SELECT DISTINCT matiere FROM eval_type')->fetchAll(PDO::FETCH_COLUMN) as $matiereBrute){
			if(nettoyerTexte($matiereBrute)!==$ancienneMatiere){ continue; }
			$maj=$dbh->prepare('UPDATE eval_type SET matiere=:nouvelle WHERE matiere=:brute');
			$maj->bindValue(':nouvelle',$nouvelleMatiere);
			$maj->bindValue(':brute',$matiereBrute);
			$maj->execute();
			$nbEval+=$maj->rowCount();
		}

		$message="Matière renommée en \"".$nouvelleMatiere."\" (".$nbComp." compétence(s), ".$nbEval." connaissance(s)).";
	}
}

if(isset($_POST['action'])&&$_POST['action']=='renommer_categorie'){

	$matiereContexte=trim($_POST['matiere_contexte']);
	$ancienneCategorie=trim($_POST['ancienne_categorie']);
	$nouvelleCategorie=trim($_POST['nouvelle_categorie']);

	if($matiereContexte==''||$ancienneCategorie==''||$nouvelleCategorie==''){

		$message="Choisis une matière, une catégorie, et donne-lui un nouveau nom.";

	}else{

		// Même logique que le renommage de matière : on compare les valeurs
		// nettoyées, pas les octets bruts, pour attraper toutes les variantes
		// d'encodage qui s'affichent pourtant à l'identique.
		$nbComp=0;
		foreach($dbh->query('SELECT DISTINCT matiere,categorie FROM comp_type')->fetchAll(PDO::FETCH_ASSOC) as $combo){
			if(nettoyerTexte($combo['matiere'])!==$matiereContexte||nettoyerTexte($combo['categorie'])!==$ancienneCategorie){ continue; }
			$maj=$dbh->prepare('UPDATE comp_type SET categorie=:nouvelle WHERE matiere=:matiere AND categorie=:categorie');
			$maj->bindValue(':nouvelle',$nouvelleCategorie);
			$maj->bindValue(':matiere',$combo['matiere']);
			$maj->bindValue(':categorie',$combo['categorie']);
			$maj->execute();
			$nbComp+=$maj->rowCount();
		}

		$nbEval=0;
		foreach($dbh->query('SELECT DISTINCT matiere,categorie FROM eval_type')->fetchAll(PDO::FETCH_ASSOC) as $combo){
			if(nettoyerTexte($combo['matiere'])!==$matiereContexte||nettoyerTexte($combo['categorie'])!==$ancienneCategorie){ continue; }
			$maj=$dbh->prepare('UPDATE eval_type SET categorie=:nouvelle WHERE matiere=:matiere AND categorie=:categorie');
			$maj->bindValue(':nouvelle',$nouvelleCategorie);
			$maj->bindValue(':matiere',$combo['matiere']);
			$maj->bindValue(':categorie',$combo['categorie']);
			$maj->execute();
			$nbEval+=$maj->rowCount();
		}

		$message="Catégorie renommée en \"".$nouvelleCategorie."\" (".$nbComp." compétence(s), ".$nbEval." connaissance(s)).";
	}
}

if(isset($_POST['action'])&&$_POST['action']=='supprimer_matiere'){

	$matiereASupprimer=trim($_POST['matiere_a_supprimer']);

	if($matiereASupprimer==''){

		$message="Choisis une matière à supprimer.";

	}else{

		// Même comparaison nettoyée que le renommage, pour retrouver toutes les
		// variantes d'encodage d'une même matière avant de vérifier/supprimer.
		$variantesComp=array();
		foreach($dbh->query('SELECT DISTINCT matiere FROM comp_type')->fetchAll(PDO::FETCH_COLUMN) as $matiereBrute){
			if(nettoyerTexte($matiereBrute)===$matiereASupprimer){ $variantesComp[]=$matiereBrute; }
		}
		$variantesEval=array();
		foreach($dbh->query('SELECT DISTINCT matiere FROM eval_type')->fetchAll(PDO::FETCH_COLUMN) as $matiereBrute){
			if(nettoyerTexte($matiereBrute)===$matiereASupprimer){ $variantesEval[]=$matiereBrute; }
		}

		$nbUtilisees=0;
		foreach($variantesComp as $matiereBrute){
			$req=$dbh->prepare('SELECT COUNT(*) FROM comp_type INNER JOIN comp_eleves ON comp_type.id_comp=comp_eleves.id_comp WHERE comp_type.matiere=:matiere');
			$req->bindValue(':matiere',$matiereBrute);
			$req->execute();
			$nbUtilisees+=(int)$req->fetchColumn();
		}
		foreach($variantesEval as $matiereBrute){
			$req=$dbh->prepare('SELECT COUNT(*) FROM eval_type INNER JOIN eval_eleves ON eval_type.id_eval=eval_eleves.id_eval WHERE eval_type.matiere=:matiere');
			$req->bindValue(':matiere',$matiereBrute);
			$req->execute();
			$nbUtilisees+=(int)$req->fetchColumn();
		}

		if($nbUtilisees>0){

			$message="Impossible de supprimer \"".$matiereASupprimer."\" : ".$nbUtilisees." saisie(s) existante(s) l'utilisent déjà. Renomme-la plutôt si besoin.";

		}else{

			$nbComp=0;
			foreach($variantesComp as $matiereBrute){
				$req=$dbh->prepare('DELETE FROM comp_type WHERE matiere=:matiere');
				$req->bindValue(':matiere',$matiereBrute);
				$req->execute();
				$nbComp+=$req->rowCount();
			}
			$nbEval=0;
			foreach($variantesEval as $matiereBrute){
				$req=$dbh->prepare('DELETE FROM eval_type WHERE matiere=:matiere');
				$req->bindValue(':matiere',$matiereBrute);
				$req->execute();
				$nbEval+=$req->rowCount();
			}

			$message="Matière \"".$matiereASupprimer."\" supprimée (".$nbComp." compétence(s), ".$nbEval." connaissance(s)).";
		}
	}
}

$rechercheMatiere=isset($_GET['matiere'])?htmlspecialchars(trim($_GET['matiere'])):'';
$rechercheCategorie=isset($_GET['categorie'])?htmlspecialchars(trim($_GET['categorie'])):'';
$rechercheTexte=isset($_GET['texte'])?htmlspecialchars(trim($_GET['texte'])):'';
$rechercheUtilisation=(isset($_GET['utilisation'])&&$_GET['utilisation']=='zero')?'zero':'';
$tri=isset($_GET['tri'])?$_GET['tri']:'intitule';
$direction=(isset($_GET['direction'])&&strtolower($_GET['direction'])=='desc')?'desc':'asc';
// Matière et catégorie ne sont plus des colonnes triables : elles servent
// maintenant de niveaux de regroupement, toujours dans l'ordre alphabétique.
// Le tri choisi ci-dessous s'applique À L'INTÉRIEUR de chaque catégorie.
$trisAutorises=array('code'=>'designation','intitule'=>'commentaire','utilisation'=>'nb_utilisation');
if(!isset($trisAutorises[$tri])){ $tri='intitule'; }

$sql='SELECT eval_type.id_eval,eval_type.designation,eval_type.commentaire,eval_type.matiere,eval_type.categorie,eval_type.niveau,
	(SELECT COUNT(*) FROM eval_eleves WHERE eval_eleves.id_eval=eval_type.id_eval) AS nb_utilisation
	FROM eval_type WHERE commentaire IS NOT NULL AND commentaire!=\'\' AND niveau=1';
$parametres=array();

// Une matiere archivee disparait aussi des connaissances : l'archivage porte
// sur la matiere entiere, pas sur une page.
$voirArchivees=isset($_GET['archivees'])&&$_GET['archivees']==='1';
if(!$voirArchivees){ $sql.=' AND archivee=0'; }

if($rechercheMatiere!==''){ $sql.=' AND matiere=:matiere'; $parametres[':matiere']=$rechercheMatiere; }
if($rechercheCategorie!==''){ $sql.=' AND categorie=:categorie'; $parametres[':categorie']=$rechercheCategorie; }
if($rechercheTexte!==''){ $sql.=' AND commentaire LIKE :texte'; $parametres[':texte']='%'.$rechercheTexte.'%'; }
if($rechercheUtilisation==='zero'){ $sql.=' AND (SELECT COUNT(*) FROM eval_eleves WHERE eval_eleves.id_eval=eval_type.id_eval)=0'; }

// Les groupes matière > catégorie restent toujours alphabétiques (collation
// MySQL, donc accents corrects) ; le tri de colonne ordonne les lignes à
// l'intérieur de chaque catégorie.
$sql.=' ORDER BY matiere ASC, categorie ASC, '.$trisAutorises[$tri].' '.strtoupper($direction);

$req=$dbh->prepare($sql);
foreach($parametres as $cle=>$valeur){ $req->bindValue($cle,$valeur); }
$req->execute();
$resultats=$req->fetchAll(PDO::FETCH_ASSOC);
$req->closeCursor();

// Diagnostic en lecture seule : mêmes codes présents sur plusieurs lignes.
// Deux requêtes au total (les codes en double, puis toutes leurs lignes d'un
// coup) au lieu d'une requête par doublon comme avant.
$doublons=array();
$req=$dbh->query("SELECT designation FROM eval_type WHERE designation IS NOT NULL AND designation!='' GROUP BY designation HAVING COUNT(*)>1 ORDER BY designation");
$designationsDoublons=$req->fetchAll(PDO::FETCH_COLUMN);
$req->closeCursor();

if(count($designationsDoublons)>0){
	$marqueurs=implode(',',array_fill(0,count($designationsDoublons),'?'));
	$reqDetails=$dbh->prepare('SELECT eval_type.id_eval,eval_type.designation,eval_type.commentaire,eval_type.matiere,eval_type.categorie,eval_type.niveau,(SELECT COUNT(*) FROM eval_eleves WHERE eval_eleves.id_eval=eval_type.id_eval) AS nb_utilisation FROM eval_type WHERE designation IN ('.$marqueurs.') ORDER BY designation ASC, id_eval ASC');
	$reqDetails->execute($designationsDoublons);
	$lignesParDesignation=array();
	foreach($reqDetails->fetchAll(PDO::FETCH_ASSOC) as $detail){
		$lignesParDesignation[$detail['designation']][]=$detail;
	}
	$reqDetails->closeCursor();

	foreach($designationsDoublons as $designation){
		if(isset($lignesParDesignation[$designation])){
			$doublons[]=array('designation'=>$designation,'lignes'=>$lignesParDesignation[$designation]);
		}
	}
}

// Clé de regroupement reproduisant la collation MySQL utf8mb4_unicode_ci,
// qui ignore À LA FOIS la casse ET les accents : « Éducation Physique »,
// « Education Physique » et « éducation physique » y sont un seul et même
// nom. La table de correspondance est explicite plutôt que de dépendre de
// iconv//TRANSLIT, dont le résultat varie selon le serveur.
function cleGroupe($texte){
	$texte=mb_strtolower($texte,'UTF-8');
	return strtr($texte,array(
		'à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a',
		'ç'=>'c',
		'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
		'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i',
		'ñ'=>'n',
		'ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
		'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u',
		'ý'=>'y','ÿ'=>'y',
		'œ'=>'oe','æ'=>'ae',
	));
}

// Regroupement matière > catégorie pour l'affichage repliable.
// Sans cette normalisation, la page afficherait deux matières là où le
// filtre, la liste déroulante et le renommage n'en voient qu'une seule.
$groupes=array();
$libellesGroupes=array();
foreach($resultats as $ligne){
	$m=nettoyerTexte($ligne['matiere']);
	$c=nettoyerTexte($ligne['categorie']);
	if($m===''){ $m='(sans matière)'; }
	if($c===''){ $c='(sans catégorie)'; }

	$cleMatiere=cleGroupe($m);
	$cleCategorie=cleGroupe($c);

	if(!isset($groupes[$cleMatiere])){ $groupes[$cleMatiere]=array(); }
	if(!isset($groupes[$cleMatiere][$cleCategorie])){ $groupes[$cleMatiere][$cleCategorie]=array(); }
	$groupes[$cleMatiere][$cleCategorie][]=$ligne;

	// On compte les orthographes rencontrées pour afficher la plus courante.
	if(!isset($libellesGroupes[$cleMatiere][$m])){ $libellesGroupes[$cleMatiere][$m]=0; }
	$libellesGroupes[$cleMatiere][$m]++;
	$cleComplete=$cleMatiere.'|'.$cleCategorie;
	if(!isset($libellesGroupes[$cleComplete][$c])){ $libellesGroupes[$cleComplete][$c]=0; }
	$libellesGroupes[$cleComplete][$c]++;
}

// Entre « Mathématiques » (163 lignes) et « mathématiques » (1 ligne), on
// affiche l'orthographe majoritaire plutôt qu'une au hasard.
function libelleGroupe($variantes){
	arsort($variantes);
	return key($variantes);
}

// Un filtre est-il posé ?
$unFiltreEstPose=($rechercheMatiere!==''||$rechercheCategorie!==''||$rechercheTexte!==''||$rechercheUtilisation!=='');

// Un filtre n'ouvre les groupes que si le résultat tient raisonnablement à
// l'écran. Chercher un mot précis et devoir encore cliquer serait absurde ;
// mais déplier une liste de plusieurs centaines de lignes reproduirait
// exactement le mur qu'on cherchait à supprimer.
$seuilDepliage=50;
$filtreActif=($unFiltreEstPose&&count($resultats)<=$seuilDepliage);

// arbre matiere -> [categories] pour le menu deroulant en cascade (niveau 2 exclu, plus utilise)
$req=$dbh->query('SELECT DISTINCT matiere,categorie FROM eval_type WHERE commentaire IS NOT NULL AND commentaire!=\'\' AND niveau=1'
	.($voirArchivees?'':' AND archivee=0').' ORDER BY matiere ASC, categorie ASC');
$combos=$req->fetchAll(PDO::FETCH_ASSOC);

$arbre=array();
foreach($combos as $combo){
	$m=nettoyerTexte($combo['matiere']);
	$c=nettoyerTexte($combo['categorie']);
	if($m==''){ continue; }
	if(!isset($arbre[$m])){ $arbre[$m]=array(); }
	if($c!=''&&!in_array($c,$arbre[$m])){ $arbre[$m][]=$c; }
}

$listeMatieres=array_keys($arbre);

$ligneAModifier=null;
if(isset($_GET['modifier'])){
	$req=$dbh->prepare('SELECT id_eval,designation,commentaire,matiere,categorie FROM eval_type WHERE id_eval=:id_eval');
	$req->bindParam(':id_eval',$_GET['modifier']);
	$req->execute();
	$ligneAModifier=$req->fetch(PDO::FETCH_ASSOC);
	$req->closeCursor();
}

?>
<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Liste des connaissances</title>

    <link href="utils/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="utils/style.css" rel="stylesheet">
    <link href="utils/style-bibliotheque.css?v=20260906-06" rel="stylesheet">
    <link rel="stylesheet" href="/galaxie-tokens.css?v=20260912-conformite">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="utils/refonte-pages-v2.css?v=20260912-conformite">

    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>

    </head>

    <body class="gx-typo gx-app-fasteval fe-page fe-page-bibliotheque">

     <a class="skip-link" href="#contenu">Aller au contenu</a>



        <!-- En-tete commun aux trois sites. Le grand logo centre et le bouton
             « Retour » en avion de papier laissent la place a la barre du
             socle : meme composition que sur la page d'accueil, School
             Monsters et Clic & Mots. Le titre de la page descend juste en
             dessous, dans son cartouche. -->
        <header class="gx-entete gx-entete-cadree">
            <img class="gx-entete-logo" src="utils/img/logofasteval.png" alt="Fast Éval">
            <nav class="fil" aria-label="Fil d'Ariane"><a href="/portail/">Portail</a><span aria-hidden="true">›</span><a href="presentation.php">Accueil</a><span aria-hidden="true">›</span><span class="actuel">Liste des connaissances</span></nav>
            <?php
            require_once $_SERVER['DOCUMENT_ROOT'].'/v2/galaxie-role.php';
            echo gxMenuIdentite('Fast Éval');
            ?>
        </header>
<div class="container bibliotheque-page gx-largeur-grille" id="haut-page">

        <div class="gx-cartouche gx-entete-cadree fe-hero gx-ligne-titre" id="contenu">
            <span class="fe-surtitre">Bibliothèque pédagogique</span>
            <h1>Liste des connaissances</h1>
            <p>Référentiel partagé entre tous les enseignants.</p>
        </div>

		<?php if($message!=''){ ?>
		<div class="message-bibliotheque text-center"><?php echo htmlspecialchars($message); ?></div>
		<?php } ?>

		<?php if(count($doublons)>0){ ?>
		<div class="alerte-doublons">
			<strong>Doublons de code détectés (diagnostic uniquement, rien n’a été supprimé) :</strong>
			<ul>
			<?php foreach($doublons as $doublon){ ?>
				<li><strong><?php echo htmlspecialchars(nettoyerTexte($doublon['designation'])); ?></strong> —
				<ul>
				<?php foreach($doublon['lignes'] as $detail){ ?>
					<li>ID <?php echo (int)$detail['id_eval']; ?> — <?php echo htmlspecialchars(nettoyerTexte($detail['matiere'])); ?> / <?php echo htmlspecialchars(nettoyerTexte($detail['categorie'])); ?> / niveau <?php echo (int)$detail['niveau']; ?> — « <?php echo htmlspecialchars(nettoyerTexte($detail['commentaire'])); ?> » — <?php echo (int)$detail['nb_utilisation']; ?> utilisation(s)</li>
				<?php } ?>
				</ul>
				</li>
			<?php } ?>
			</ul>
		</div>
		<?php } ?>

		<div class="raccourcis-page">
			<a href="#form-ajout">Créer une connaissance</a>
			<a href="#form-renommer">Organiser les matières et les catégories</a>
		</div>

		<div class="section-encadree">

			<div class="section-header">
				<div>
					<h2>Rechercher</h2>
					<p class="section-description">Affinez la liste par matière, catégorie ou texte.</p>
				</div>
			</div>

			<form method="get" action="liste-connaissances.php" class="form-recherche">
				<div class="fe-filtres-principaux"><div class="row fe-filtre-texte">
					<div class="col-md-12">
						<label for="texte">Texte dans l'intitulé</label>
						<input type="text" name="texte" id="texte" value="<?php echo htmlspecialchars($rechercheTexte); ?>">
						<details class="fe-symboles-recherche"><summary>Caractères utiles</summary><div class="symboles-maths">
							<?php foreach(array('≠','≤','≥','×','÷','±','œ','Œ','«','»') as $symbole){ ?>
							<button type="button" class="btn-symbole" data-cible="texte" data-symbole="<?php echo $symbole; ?>"><?php echo $symbole; ?></button>
							<?php } ?>
						</div></details>
					</div>
				</div><div class="row">
					<div class="col-md-4">
						<label for="matiere">Matière</label>
						<select name="matiere" id="matiere" class="form-control">
							<option value="">Toutes</option>
							<?php foreach($listeMatieres as $m){ ?>
							<option value="<?php echo htmlspecialchars($m); ?>" <?php echo $rechercheMatiere==$m?'selected':''; ?>><?php echo htmlspecialchars($m); ?></option>
							<?php } ?>
						</select>
					</div>
					<div class="col-md-4">
						<label for="categorie">Catégorie</label>
						<select name="categorie" id="categorie" class="form-control">
							<option value="">Toutes</option>
						</select>
					</div>
					<div class="col-md-4">
						<label for="utilisation">Utilisation</label>
						<select name="utilisation" id="utilisation" class="form-control">
							<option value="">Toutes</option>
							<option value="zero" <?php echo $rechercheUtilisation==='zero'?'selected':''; ?>>Jamais utilisées (0 fois)</option>
						</select>
					</div>
				</div>
				</div>
				<div class="form-actions">
					<button type="submit" class="btn btn-color">Rechercher</button>
					<a href="liste-connaissances.php" class="btn btn-secondary">Réinitialiser</a>
					<?php
					// Les coupons reprennent la selection affichee : on repasse les
					// memes parametres, coupons.php les relit de la meme facon.
					$parametresCoupons=$_GET;
					$parametresCoupons['type']='eval';
					?>
					<a href="coupons.php?<?php echo htmlspecialchars(http_build_query($parametresCoupons)); ?>" class="btn btn-secondary" target="_blank" rel="noopener">Imprimer les coupons</a>
				</div>
			</form>

		</div>

		<div class="section-encadree">

			<div class="section-header">
				<div>
					<h2>Résultats</h2>
					<p class="section-description">Les connaissances utilisées ne peuvent pas être supprimées.</p>
				</div>
				<span class="result-count"><?php echo count($resultats); ?></span>
			</div>
			<p class="legende-utilisation"><strong>« X fois »</strong> indique le nombre total de saisies enregistrées avec cette connaissance, toutes classes et tous enseignants confondus. Ce n’est pas le nombre de fois où elle apparaît dans un livret.</p>

			<?php if(count($resultats)>0){ ?>
			<div class="barre-depliage">
				<button type="button" class="btn btn-sm btn-secondary" id="tout-deplier">Tout déplier</button>
				<button type="button" class="btn btn-sm btn-secondary" id="tout-replier">Tout replier</button>
				<span class="aide-depliage"><?php
					if($filtreActif){ echo 'Résultats de la recherche, déjà dépliés.'; }
					elseif($unFiltreEstPose){ echo 'Beaucoup de résultats : cliquez sur une matière, ou sur « Tout déplier ».'; }
					else{ echo 'Cliquez sur une matière pour l\'ouvrir.'; }
				?></span>
			</div>
			<?php } ?>

			<div class="table-responsive-fasteval">
			<table class="table table-bordered tableau-groupe">
				<thead>
					<tr>
						<th><a class="tri-tableau" href="<?php echo htmlspecialchars(lienTri('code',$tri,$direction)); ?>">Code</a></th>
						<th><a class="tri-tableau" href="<?php echo htmlspecialchars(lienTri('intitule',$tri,$direction)); ?>">Intitulé</a></th>
						<th><a class="tri-tableau" href="<?php echo htmlspecialchars(lienTri('utilisation',$tri,$direction)); ?>">Utilisation</a></th>
						<th colspan="2">Actions</th>
					</tr>
				</thead>
				<?php $numMatiere=0; foreach($groupes as $cleMatiere=>$categories){
					$numMatiere++;
					$idMatiere='mat'.$numMatiere;
					$nomMatiere=libelleGroupe($libellesGroupes[$cleMatiere]);
					$nbLignesMatiere=0;
					foreach($categories as $lignesCategorie){ $nbLignesMatiere+=count($lignesCategorie); }
				?>
				<tbody class="groupe-matiere">
					<tr class="ligne-matiere">
						<th colspan="5">
							<button type="button" class="bascule bascule-matiere" data-groupe="<?php echo $idMatiere; ?>" aria-expanded="<?php echo $filtreActif?'true':'false'; ?>">
								<span class="chevron" aria-hidden="true"></span>
								<span class="nom-groupe"><?php echo htmlspecialchars($nomMatiere); ?></span>
								<span class="compteur-groupe"><?php echo $nbLignesMatiere; ?></span>
							</button>
						</th>
					</tr>
					<?php $numCategorie=0; foreach($categories as $cleCategorie=>$lignesCategorie){
						$numCategorie++;
						$idCategorie=$idMatiere.'cat'.$numCategorie;
						$nomCategorie=libelleGroupe($libellesGroupes[$cleMatiere.'|'.$cleCategorie]);
					?>
					<tr class="ligne-categorie" data-groupe="<?php echo $idMatiere; ?>" <?php echo $filtreActif?'':'hidden'; ?>>
						<th colspan="5">
							<button type="button" class="bascule bascule-categorie" data-groupe="<?php echo $idMatiere; ?>" data-categorie="<?php echo $idCategorie; ?>" aria-expanded="<?php echo $filtreActif?'true':'false'; ?>">
								<span class="chevron" aria-hidden="true"></span>
								<span class="nom-groupe"><?php echo htmlspecialchars($nomCategorie); ?></span>
								<span class="compteur-groupe"><?php echo count($lignesCategorie); ?></span>
							</button>
						</th>
					</tr>
					<?php foreach($lignesCategorie as $ligne){ ?>
					<tr class="ligne-donnee" data-groupe="<?php echo $idMatiere; ?>" data-categorie="<?php echo $idCategorie; ?>" <?php echo $filtreActif?'':'hidden'; ?>>
						<td data-intitule="Code"><?php echo htmlspecialchars(nettoyerTexte($ligne['designation'])); ?></td>
						<td data-intitule="Intitulé"><?php echo htmlspecialchars(nettoyerTexte($ligne['commentaire'])); ?></td>
						<td class="centre" data-intitule="Utilisation"><span class="badge-utilisation <?php echo $ligne['nb_utilisation']==0?'zero':''; ?>"><?php echo $ligne['nb_utilisation']; ?> fois</span></td>
						<td class="centre" data-intitule="Code-barres">
							<button type="button" class="btn-code-barre" data-code="<?php echo htmlspecialchars(nettoyerTexte($ligne['designation'])); ?>" data-colonnes="5">Code</button>
						</td>
						<td data-intitule="Actions">
							<div class="cellule-actions">
								<a href="liste-connaissances.php?modifier=<?php echo (int)$ligne['id_eval']; ?>#form-ajout" class="btn btn-sm btn-secondary">Modifier</a>
								<form method="post" action="liste-connaissances.php" onsubmit="return confirm('Supprimer cette connaissance ?');" style="margin:0;">
									<input type="hidden" name="action" value="supprimer">
									<input type="hidden" name="id_eval" value="<?php echo (int)$ligne['id_eval']; ?>">
									<button type="submit" class="btn-supprimer" title="Disponible uniquement si cette connaissance n’a jamais été utilisée.">Supprimer</button>
								</form>
							</div>
						</td>
					</tr>
					<?php } ?>
					<?php } ?>
				</tbody>
				<?php } ?>
				<?php if(count($resultats)==0){ ?>
				<tbody>
					<tr><td colspan="5" class="text-center">Aucune connaissance ne correspond à cette recherche.</td></tr>
				</tbody>
				<?php } ?>
			</table>
			</div>

		</div>

		<div class="section-encadree" id="form-ajout">

			<?php /* Meme principe que la page des competences : la page sert d'abord a
			         consulter, le formulaire se deplie - et s'ouvre de lui-meme quand on
			         arrive par le bouton « Modifier » d'une ligne. */ ?>
			<details class="bloc-action bloc-formulaire" <?php echo $ligneAModifier?'open':''; ?>>
			<summary><?php echo $ligneAModifier?'Modifier une connaissance':'Créer une connaissance'; ?></summary>

			<div class="section-header">
				<div>
					<?php if(!$ligneAModifier){ ?>
					<p class="section-description">Le code sera généré automatiquement.</p>
					<?php }else{ ?>
					<p class="section-description">Code : <?php echo htmlspecialchars(nettoyerTexte($ligneAModifier['designation'])); ?> (inchangé).</p>
					<?php } ?>
				</div>
			</div>

			<form method="post" action="liste-connaissances.php" class="form-ajout">
				<input type="hidden" name="action" value="<?php echo $ligneAModifier?'modifier':'creer'; ?>">
				<?php if($ligneAModifier){ ?>
				<input type="hidden" name="id_eval" value="<?php echo (int)$ligneAModifier['id_eval']; ?>">
				<?php } ?>

				<?php
				/* Memes listes deroulantes que sur la page des competences, et pour la
				   meme raison : un `<input list=datalist>` deja rempli se filtrait sur
				   sa propre valeur, la fleche ne rouvrait plus rien. Voir le commentaire
				   detaille dans liste-competences.php. */
				$matiereCourante=$ligneAModifier?nettoyerTexte($ligneAModifier['matiere']):'';
				$matiereHorsListe=($matiereCourante!==''&&!in_array($matiereCourante,$listeMatieres,true));
				?>
				<div class="row">
					<div class="col-md-6 champ">
						<label for="matiere_ajout">Matière</label>
						<select class="form-control" name="matiere" id="matiere_ajout" required>
							<option value="">-- Choisir --</option>
							<?php foreach($listeMatieres as $m){ ?>
							<option value="<?php echo htmlspecialchars($m); ?>" <?php echo $matiereCourante===$m?'selected':''; ?>><?php echo htmlspecialchars($m); ?></option>
							<?php } ?>
							<option value="__autre__" <?php echo $matiereHorsListe?'selected':''; ?>>Autre matière…</option>
						</select>
						<input type="text" class="form-control champ-autre" name="matiere_autre" id="matiere_autre" placeholder="Nom de la nouvelle matière" value="<?php echo $matiereHorsListe?htmlspecialchars($matiereCourante):''; ?>" <?php echo $matiereHorsListe?'':'hidden'; ?>>
					</div>
					<div class="col-md-6 champ">
						<label for="categorie_ajout">Catégorie</label>
						<select class="form-control" name="categorie" id="categorie_ajout" required>
							<option value="">-- Choisir --</option>
							<option value="__autre__">Autre catégorie…</option>
						</select>
						<input type="text" class="form-control champ-autre" name="categorie_autre" id="categorie_autre" placeholder="Nom de la nouvelle catégorie" hidden>
					</div>
				</div>

				<div class="champ">
					<label for="commentaire">Intitulé de l'évaluation</label>
					<textarea class="form-control" name="commentaire" id="commentaire" rows="4" lang="fr" spellcheck="true" autocorrect="on" required><?php echo $ligneAModifier?htmlspecialchars(nettoyerTexte($ligneAModifier['commentaire'])):''; ?></textarea>
					<div class="symboles-maths">
						<span>Symboles :</span>
						<button type="button" class="btn-symbole" data-cible="commentaire" data-symbole="≠">≠</button>
						<button type="button" class="btn-symbole" data-symbole="≤">≤</button>
						<button type="button" class="btn-symbole" data-symbole="≥">≥</button>
						<button type="button" class="btn-symbole" data-symbole="×">×</button>
						<button type="button" class="btn-symbole" data-symbole="÷">÷</button>
						<button type="button" class="btn-symbole" data-symbole="±">±</button>
						<button type="button" class="btn-symbole" data-symbole="œ">œ</button>
						<button type="button" class="btn-symbole" data-symbole="Œ">Œ</button>
						<button type="button" class="btn-symbole" data-symbole="«">«</button>
						<button type="button" class="btn-symbole" data-symbole="»">»</button>
					</div>
				</div>

				<div class="form-actions">
					<button type="submit" class="btn btn-color"><?php echo $ligneAModifier?'Enregistrer les modifications':'Créer'; ?></button>
					<?php if($ligneAModifier){ ?>
					<a href="liste-connaissances.php" class="btn btn-secondary">Annuler</a>
					<?php } ?>
				</div>
			</form>
			</details>

		</div>

		<div class="section-encadree" id="form-renommer">

			<div class="section-header">
				<div>
					<h3>Organiser les matières et les catégories</h3>
					<p class="section-description">Dépliez l'action dont vous avez besoin. Le changement s'applique partout où le nom est utilisé, dans les connaissances <strong>et</strong> les compétences.</p>
				</div>
			</div>

			<details class="bloc-action">
				<summary>Renommer une matière</summary>
			<form method="post" action="liste-connaissances.php" class="form-renommer">
				<input type="hidden" name="action" value="renommer_matiere">
				<div class="champ">
					<label for="ancienne_matiere">Matière à renommer</label>
					<select class="form-control" name="ancienne_matiere" id="ancienne_matiere" required>
						<option value="">-- Choisir --</option>
						<?php foreach($listeMatieres as $m){ ?>
						<option value="<?php echo htmlspecialchars($m); ?>"><?php echo htmlspecialchars($m); ?></option>
						<?php } ?>
					</select>
				</div>
				<div class="champ">
					<label for="nouvelle_matiere">Nouveau nom</label>
					<input type="text" class="form-control" name="nouvelle_matiere" id="nouvelle_matiere" required>
				</div>
				<button type="submit" class="btn btn-color">Renommer la matière</button>
			</form>
			</details>

			<details class="bloc-action">
				<summary>Renommer une catégorie</summary>
			<form method="post" action="liste-connaissances.php" class="form-renommer">
				<input type="hidden" name="action" value="renommer_categorie">
				<div class="champ">
					<label for="matiere_contexte">Matière</label>
					<select class="form-control" name="matiere_contexte" id="matiere_contexte" required>
						<option value="">-- Choisir --</option>
						<?php foreach($listeMatieres as $m){ ?>
						<option value="<?php echo htmlspecialchars($m); ?>"><?php echo htmlspecialchars($m); ?></option>
						<?php } ?>
					</select>
				</div>
				<div class="champ">
					<label for="ancienne_categorie">Catégorie à renommer</label>
					<select class="form-control" name="ancienne_categorie" id="ancienne_categorie" required>
						<option value="">-- Choisir une matière d'abord --</option>
					</select>
				</div>
				<div class="champ">
					<label for="nouvelle_categorie">Nouveau nom</label>
					<input type="text" class="form-control" name="nouvelle_categorie" id="nouvelle_categorie" required>
				</div>
				<button type="submit" class="btn btn-color">Renommer la catégorie</button>
			</form>
			</details>

			<details class="bloc-action est-dangereux">
				<summary>Supprimer définitivement une matière <span class="bloc-action-note">irréversible</span></summary>
			<form method="post" action="liste-connaissances.php" class="form-renommer" onsubmit="return confirm('Supprimer définitivement cette matière et toutes ses compétences/connaissances (uniquement si elles n\'ont jamais été utilisées) ?');">
				<input type="hidden" name="action" value="supprimer_matiere">
				<div class="champ">
					<label for="matiere_a_supprimer">Matière à supprimer</label>
					<select class="form-control" name="matiere_a_supprimer" id="matiere_a_supprimer" required>
						<option value="">-- Choisir --</option>
						<?php foreach($listeMatieres as $m){ ?>
						<option value="<?php echo htmlspecialchars($m); ?>"><?php echo htmlspecialchars($m); ?></option>
						<?php } ?>
					</select>
				</div>
				<button type="submit" class="btn-supprimer" title="Disponible uniquement si aucune compétence/connaissance de cette matière n'a jamais été utilisée.">Supprimer la matière</button>
			<p class="section-description">Une matière déjà utilisée ne s'efface pas d'ici. Pour la faire disparaitre des listes sans rien perdre, ou pour l'effacer pour de bon, passe par <a href="liste-competences.php#form-renommer">la page des compétences</a>, section « Organiser les matières et les catégories ».</p>
			</form>
			</details>

		</div>

		<a href="#haut-page" class="retour-haut">↑ Haut de page</a>

	</div>

<script src="utils/jquery/jquery-1.11.3.min.js"></script>
<script src="utils/bootstrap/js/bootstrap.min.js"></script>

<script>
var arbreBibliotheque=<?php echo json_encode($arbre); ?>;
var valeurInitialeCategorieBibliotheque="<?php echo addslashes($rechercheCategorie); ?>";
// Categorie de la ligne en cours de modification : la liste du formulaire est
// remplie par le script, elle ne peut pas etre preselectionnee en PHP.
var valeurInitialeCategorieAjout="<?php echo addslashes($ligneAModifier?nettoyerTexte($ligneAModifier['categorie']):''); ?>";
</script>
<script src="utils/js/bibliotheque.js?v=20260906-05"></script>

</body>

</html>
