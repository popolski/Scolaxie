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
$cyclesPossibles=array('Cycle 1','Cycle 2','Cycle 3');

// APRES UNE ECRITURE, ON REVIENT LA OU L'ON ETAIT.
//
// Avant le 06/09/2026, enregistrer une compétence renvoyait sur la page nue :
// filtres perdus, groupes repliés, retour tout en haut. Sur un référentiel de
// mille lignes, retrouver celle qu'on venait de reformuler était une petite
// expédition. Signalé par le responsable technique.
//
// Post/Redirect/Get : le traitement redirige vers la MEME recherche, avec une
// ancre sur la ligne touchée. Ça règle aussi le rechargement qui reproposait
// d'envoyer le formulaire une seconde fois.
//
// Le message ne survit pas à une redirection : il passe par la session, lu et
// effacé aussitôt ici.
if(!empty($_SESSION['biblio_message'])){
	$message=(string)$_SESSION['biblio_message'];
	unset($_SESSION['biblio_message']);
}

/**
 * Redirige vers la recherche d'où venait le formulaire, en pointant la ligne
 * concernée. $retour est la chaîne de requête d'origine, transportée par le
 * formulaire lui-même : la reconstruire ici demanderait de deviner les filtres.
 */
function retourApresEcriture($messageAAfficher,$retour,$idLigne=null,$categorieAOuvrir=null){
	$_SESSION['biblio_message']=$messageAAfficher;
	$parametres=array();
	parse_str((string)$retour,$parametres);
	// `modifier` rouvrirait le formulaire sur la ligne qu'on vient de quitter.
	unset($parametres['modifier'],$parametres['ouvrir']);
	if($idLigne){ $parametres['modifie']=$idLigne; }
	// APRES UNE SUPPRESSION il n'y a plus de ligne ou revenir, et sans repere la
	// page se rouvrait entierement repliee, tout en haut - « il supprime puis me
	// remet sur l'accueil » (l’enseignante, 06/09/2026). On rouvre donc la CATEGORIE
	// d'ou la ligne a ete retiree, qui est l'endroit ou le travail continue.
	if($categorieAOuvrir){ $parametres['ouvrir']=$categorieAOuvrir; }
	$url='liste-competences.php';
	if($parametres){ $url.='?'.http_build_query($parametres); }
	if($idLigne){ $url.='#ligne-comp-'.$idLigne; }
	header('Location: '.$url);
	exit();
}
$retourFormulaire=isset($_POST['retour'])?(string)$_POST['retour']:'';

// Qui peut retoucher une compétence des programmes officiels : un compte
// ADMINISTRATEUR, et lui seul. Demande de le responsable technique le 06/09/2026, formulée
// « donne la possibilité au compte l’enseignante » - traduite en droit et non en
// prénom : la règle du projet interdit de déduire un droit d'un identifiant,
// et l’enseignante est administratrice. Un autre administrateur, aujourd'hui ou
// demain, aura donc le même droit sans qu'une ligne soit à réécrire.
//
// LE RÉFÉRENTIEL EST PARTAGÉ ENTRE TOUS LES ENSEIGNANTS : une reformulation
// vaut pour toutes les classes, pas seulement celle de qui la fait. C'est
// exactement pourquoi ce droit ne peut pas être donné à tout le monde.
//
// Défini ICI, avant les traitements de formulaire, et pas seulement au moment
// de l'affichage : sans ça, la vérification vivrait après le POST qu'elle est
// censée autoriser.
$estAdministrateur=!empty($_SESSION['est_admin']);
$peutModifierOfficielles=$estAdministrateur;
// Deux droits nommes separement bien qu'ils vaillent la meme chose aujourd'hui :
// reformuler et supprimer n'ont pas la meme portee, et le jour ou l'un des deux
// devra etre restreint, il n'y aura pas a demeler un booleen unique.
// La suppression a ete ouverte le 06/09/2026, apres la reformulation, sur
// demande explicite de le responsable technique.
$peutSupprimerOfficielles=$estAdministrateur;

// « Ce menage retire-t-il a un mini-jeu de Fast Games sa derniere competence ? »
// Ce controle n'interdit pas la suppression, il la fait signaler. Il vit dans son
// propre fichier : partage par la suppression d'une ligne et celle d'une
// categorie, et testable sans lancer cette page.
require_once(__DIR__.'/utils/jeux-fastgames.php');

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
	return 'liste-competences.php?'.http_build_query($parametres);
}

function genererDesignation($dbh){
	$caracteres='ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
	do{
		$designation='';
		for($i=0;$i<9;$i++){
			$designation.=$caracteres[random_int(0,strlen($caracteres)-1)];
		}
		$req=$dbh->prepare('SELECT id_comp FROM comp_type WHERE designation=:designation');
		$req->bindParam(':designation',$designation);
		$req->execute();
		$existe=(bool)$req->fetch();
		$req->closeCursor();
	}while($existe);
	return $designation;
}

// LE NIVEAU D'UNE COMPETENCE, tel qu'on peut le choisir dans le formulaire.
//
// Ajoute le 06/09/2026 : le champ n'existait nulle part, alors que la colonne
// `niveau` est lue partout - par la recherche de cette page, et par Fast Games
// pour n'offrir a une classe que les jeux de son niveau. l’enseignante : « j'ai
// ajoute des competences mais je ne peux pas selectionner le niveau ».
//
// « Non precise » et « CE1 et CE2 » filtrent EXACTEMENT PAREIL : la recherche
// accepte une ligne si `niveau=''` OU si le niveau demande est dans la liste.
// Ce n'est donc pas une redondance a supprimer un jour - la difference est ce
// que la ligne AFFICHE : une competence explicitement rangee dans les deux
// niveaux porte son badge, une competence dont personne n'a rien dit n'en
// porte pas.
$niveauxCompetence=array(
	''        => 'Non précisé (visible aux deux niveaux)',
	'CE1'     => 'CE1',
	'CE2'     => 'CE2',
	'CE1,CE2' => 'CE1 et CE2',
);

/**
 * Le rang de la CATEGORIE, relu depuis les lignes qu'elle contient deja.
 *
 * `ordre_categorie` est stocke sur chaque competence, et l'affichage classe une
 * categorie par le MIN de ses lignes (voir la requete de l'arbre plus bas). Une
 * ligne neuve laissee a 0 tirait donc toute sa categorie en tete de la matiere :
 * « en ajoutant mes 3 competences, la categorie Produire des ecrits s'est
 * replacee toute seule en haut de la liste » - l’enseignante, 06/09/2026.
 *
 * Rendre 0 pour une categorie qui n'existe pas encore est le bon comportement :
 * 0 est justement la convention « jamais rangee », qui retombe sur l'ordre
 * alphabetique.
 */
function rangDeLaCategorie($dbh,$matiere,$categorie){
	$req=$dbh->prepare('SELECT MIN(ordre_categorie) FROM comp_type WHERE matiere=:matiere AND categorie=:categorie');
	$req->bindValue(':matiere',$matiere);
	$req->bindValue(':categorie',$categorie);
	$req->execute();
	$valeur=$req->fetchColumn();
	$req->closeCursor();
	return $valeur===null?0:(int)$valeur;
}

/**
 * Ou poser une competence neuve DANS sa categorie.
 *
 * Deux conventions cohabitent et il faut respecter les deux : une categorie
 * jamais rangee a la main n'a que des 0 et s'affiche dans l'ordre alphabetique
 * des intitules - la nouvelle ligne doit donc rester a 0 pour s'y ranger comme
 * les autres. Une categorie rangee a la main porte des rangs 1..n, et la
 * nouvelle ligne va A LA FIN, la seule place qui ne bouscule personne.
 */
function rangSuivantDansCategorie($dbh,$matiere,$categorie){
	$req=$dbh->prepare('SELECT MAX(ordre) FROM comp_type WHERE matiere=:matiere AND categorie=:categorie');
	$req->bindValue(':matiere',$matiere);
	$req->bindValue(':categorie',$categorie);
	$req->execute();
	$maximum=(int)$req->fetchColumn();
	$req->closeCursor();
	return $maximum>0?$maximum+1:0;
}

/**
 * Lit un couple « liste deroulante + champ libre ».
 *
 * Les menus Matiere et Categorie du formulaire sont de vraies listes deroulantes
 * depuis le 06/09/2026, avec une entree « Autre… » qui ouvre un champ de saisie.
 * Avant, c'etait un `<input list=datalist>` : des qu'il portait une valeur
 * complete, la liste se filtrait sur cette seule entree et la fleche ne servait
 * plus a rien - « si je clique par erreur sur une mauvaise matiere je ne peux
 * plus appuyer sur la fleche, je dois supprimer avec la touche du clavier »
 * (l’enseignante, 06/09/2026).
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
	$cycle=trim($_POST['cycle']);
	$commentaire=trim($_POST['commentaire']);
	$niveau=isset($_POST['niveau'])&&array_key_exists($_POST['niveau'],$niveauxCompetence)?(string)$_POST['niveau']:'';
	// L'officialite ne se coche que par une administratrice, et le controle est
	// ici, pas seulement a l'affichage : sans lui, un POST bricole suffirait a
	// s'attribuer le picto des programmes officiels - donc, du meme coup, la
	// protection qui reserve la modification de ces lignes aux administratrices.
	$officiel=($estAdministrateur&&!empty($_POST['officiel']))?1:0;

	if($matiere==''||$categorie==''||!in_array($cycle,$cyclesPossibles)||$commentaire==''){

		$message="Matière, catégorie, cycle et intitulé sont obligatoires.";

	}else{

		$designation=genererDesignation($dbh);
		// Les deux rangs sont HERITES de la categorie d'accueil plutot que laisses
		// a 0 : voir rangDeLaCategorie() et rangSuivantDansCategorie().
		$rangCategorie=rangDeLaCategorie($dbh,$matiere,$categorie);
		$rangDansCategorie=rangSuivantDansCategorie($dbh,$matiere,$categorie);

		$req=$dbh->prepare('INSERT INTO comp_type (designation,commentaire,matiere,categorie,cycle,niveau,officiel,ordre,ordre_categorie) VALUES (:designation,:commentaire,:matiere,:categorie,:cycle,:niveau,:officiel,:ordre,:ordre_categorie)');
		$req->bindParam(':designation',$designation);
		$req->bindParam(':commentaire',$commentaire);
		$req->bindParam(':matiere',$matiere);
		$req->bindParam(':categorie',$categorie);
		$req->bindParam(':cycle',$cycle);
		$req->bindParam(':niveau',$niveau);
		$req->bindValue(':officiel',$officiel,PDO::PARAM_INT);
		$req->bindValue(':ordre',$rangDansCategorie,PDO::PARAM_INT);
		$req->bindValue(':ordre_categorie',$rangCategorie,PDO::PARAM_INT);
		$req->execute();

		// On pointe la compétence qui vient de naître : sans ça, elle se perd
		// au milieu des autres et il faut la chercher pour vérifier.
		retourApresEcriture("Compétence créée. Code : ".$designation.".",$retourFormulaire,(int)$dbh->lastInsertId());
	}
}

// Une compétence officielle reprend mot pour mot un texte des programmes.
// Depuis le 06/09/2026, un compte administrateur peut la REFORMULER - l’enseignante
// connaît ses programmes mieux que l'import qui les a chargés, et certains
// intitulés arrivent tronqués ou illisibles. La SUPPRESSION, elle, reste
// interdite à tout le monde : une compétence officielle sert de référence
// commune, et rien ne garantit qu'aucun bilan n'y renvoie.
// Le contrôle est fait ICI et pas seulement à l'affichage, sinon un ancien
// lien « ?modifier=... » mis en favori suffirait à contourner.
function estOfficielle($dbh,$idComp){
	$req=$dbh->prepare('SELECT officiel FROM comp_type WHERE id_comp=:id_comp');
	$req->bindValue(':id_comp',$idComp,PDO::PARAM_INT);
	$req->execute();
	$valeur=$req->fetchColumn();
	$req->closeCursor();
	return !empty($valeur);
}

if(isset($_POST['action'])&&$_POST['action']=='modifier'){

	$idComp=(int)$_POST['id_comp'];
	$matiere=valeurListeOuLibre('matiere','matiere_autre');
	$categorie=valeurListeOuLibre('categorie','categorie_autre');
	$cycle=trim($_POST['cycle']);
	$commentaire=trim($_POST['commentaire']);
	$niveau=isset($_POST['niveau'])&&array_key_exists($_POST['niveau'],$niveauxCompetence)?(string)$_POST['niveau']:'';

	if(estOfficielle($dbh,$idComp)&&!$peutModifierOfficielles){

		$message="Cette compétence est issue des programmes officiels : seule une administratrice peut la reformuler. Créez plutôt votre propre compétence si vous souhaitez l'adapter.";

	}elseif($matiere==''||$categorie==''||!in_array($cycle,$cyclesPossibles)||$commentaire==''){

		$message="Matière, catégorie, cycle et intitulé sont obligatoires.";

	}else{

		// L'officialite est relue AVANT l'écriture pour savoir quoi annoncer :
		// une reformulation officielle vaut pour toutes les classes, ça ne se
		// dit pas de la même façon qu'une correction sur sa propre compétence.
		$etaitOfficielle=estOfficielle($dbh,$idComp);

		// DEMENAGEMENT : si la competence change de categorie, elle emporterait
		// sinon les rangs de son ancienne place. Une ligne venue d'une categorie
		// classee 2e, deposee dans une categorie classee 7e, ramenerait toute sa
		// nouvelle categorie au rang 2 par le MIN() de l'affichage - le meme
		// defaut que celui signale a la creation, mais par un autre chemin.
		$req=$dbh->prepare('SELECT matiere,categorie FROM comp_type WHERE id_comp=:id_comp');
		$req->bindValue(':id_comp',$idComp,PDO::PARAM_INT);
		$req->execute();
		$placeActuelle=$req->fetch(PDO::FETCH_ASSOC);
		$req->closeCursor();
		$aDemenage=($placeActuelle&&($placeActuelle['matiere']!==$matiere||$placeActuelle['categorie']!==$categorie));

		// `officiel` n'est PAS remis à zéro tout seul : la compétence reste celle
		// du programme, avec son identifiant et sa place dans le référentiel -
		// seule sa formulation change. La basculer en « compétence
		// d'enseignant » la sortirait du filtre « Programmes officiels » et
		// casserait le rattachement des jeux Fast Games, qui visent des
		// identifiants de compétences officielles.
		// Une ADMINISTRATRICE, elle, voit la case et peut donc le decider
		// explicitement - c'est la demande de l’enseignante du 06/09/2026. Pour tous
		// les autres comptes la case n'est pas rendue, et la colonne n'entre
		// meme pas dans la requete : rien ne peut donc etre perdu par megarde.
		$colonnes='commentaire=:commentaire,matiere=:matiere,categorie=:categorie,cycle=:cycle,niveau=:niveau';
		if($estAdministrateur){ $colonnes.=',officiel=:officiel'; }
		if($aDemenage){ $colonnes.=',ordre=:ordre,ordre_categorie=:ordre_categorie'; }
		$req=$dbh->prepare('UPDATE comp_type SET '.$colonnes.' WHERE id_comp=:id_comp');
		$req->bindParam(':commentaire',$commentaire);
		$req->bindParam(':matiere',$matiere);
		$req->bindParam(':categorie',$categorie);
		$req->bindParam(':cycle',$cycle);
		$req->bindParam(':niveau',$niveau);
		if($estAdministrateur){ $req->bindValue(':officiel',!empty($_POST['officiel'])?1:0,PDO::PARAM_INT); }
		if($aDemenage){
			$req->bindValue(':ordre',rangSuivantDansCategorie($dbh,$matiere,$categorie),PDO::PARAM_INT);
			$req->bindValue(':ordre_categorie',rangDeLaCategorie($dbh,$matiere,$categorie),PDO::PARAM_INT);
		}
		$req->bindParam(':id_comp',$idComp);
		$req->execute();

		retourApresEcriture($etaitOfficielle
			?"Compétence officielle reformulée. Le référentiel étant partagé, le nouvel intitulé s'affiche pour tous les enseignants."
			:"Compétence modifiée.",$retourFormulaire,$idComp);
	}
}

if(isset($_POST['action'])&&$_POST['action']=='supprimer'){

	$idComp=(int)$_POST['id_comp'];

	$req=$dbh->prepare('SELECT COUNT(*) FROM comp_eleves WHERE id_comp=:id_comp');
	$req->bindParam(':id_comp',$idComp);
	$req->execute();
	$nbUtilisation=(int)$req->fetchColumn();
	$req->closeCursor();

	$jeuxCasses=jeuxRendusInjouables($dbh,array($idComp));

	// Releve AVANT le DELETE : apres, la ligne n'existe plus et on ne saurait
	// plus ou ramener l'ecran.
	$req=$dbh->prepare('SELECT matiere,categorie FROM comp_type WHERE id_comp=:id_comp');
	$req->bindValue(':id_comp',$idComp,PDO::PARAM_INT);
	$req->execute();
	$placeSupprimee=$req->fetch(PDO::FETCH_ASSOC);
	$req->closeCursor();

	if(estOfficielle($dbh,$idComp)&&!$peutSupprimerOfficielles){

		$message="Cette compétence est issue des programmes officiels : seule une administratrice peut la supprimer.";

	}elseif($nbUtilisation>0){

		// Ce garde-fou vaut pour TOUT LE MONDE, administratrice comprise : une
		// compétence déjà évaluée porte des résultats d'élèves, et les effacer
		// ne se rattrape pas.
		$message="Impossible de supprimer cette compétence : elle a déjà été utilisée ".$nbUtilisation." fois.";

	}else{

		$req=$dbh->prepare('DELETE FROM comp_type WHERE id_comp=:id_comp');
		$req->bindParam(':id_comp',$idComp);
		$req->execute();

		// FAST GAMES NE BLOQUE PLUS RIEN, IL PREVIENT. Demande de le responsable technique du
		// 09/09/2026 : le referentiel appartient a l'enseignante, un mini-jeu ne
		// lui interdit pas de faire le menage - meme quand il s'agit de la
		// derniere competence du jeu. Fast Games encaisse d'ailleurs le vide
		// proprement : la banque sans reference sort du catalogue et jeu.php
		// renvoie vers « indisponible », sans perte de donnee ni erreur.
		//
		// Ce qui restait vrai, c'est « sans que rien ne le signale » : c'est donc
		// le signalement qu'on garde, apres coup, avec le nom des jeux touches.
		$avertissement=avertissementJeuxFastGames($jeuxCasses);

		// Pas d'ancre : la ligne n'existe plus. On revient sur la meme recherche
		// ET sur la categorie d'ou elle a ete retiree, rouverte et amenee a
		// l'ecran - sinon la page se rouvrait entierement repliee, tout en haut.
		retourApresEcriture("Compétence supprimée.".$avertissement,$retourFormulaire,null,
			$placeSupprimee?($placeSupprimee['matiere'].'|'.$placeSupprimee['categorie']):null);
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

// Supprimer une CATEGORIE entiere dans une matiere.
//
// Une categorie n'existe pas en tant que ligne : c'est une valeur de colonne
// sur comp_type et eval_type. La supprimer, c'est donc supprimer TOUTES les
// competences et connaissances qui la portent - il faut le dire, et surtout
// refuser tant qu'une seule d'entre elles est retenue par un garde-fou.
//
// DEUX REFUS POSSIBLES, dans cet ordre, et chacun dit son motif :
//   1. une competence ou une connaissance a deja servi (comp_eleves/eval_eleves) ;
//   2. la categorie contient des competences officielles et le compte n'est pas
//      administrateur - meme regle qu'a la ligne.
// Fast Games ne refuse pas : il avertit apres coup, comme a la ligne.
// Rien de partiel : on ne supprime pas « ce qui peut l'etre » en laissant le
// reste, ce serait une categorie a moitie effacee que personne n'a demandee.
if(isset($_POST['action'])&&$_POST['action']=='supprimer_categorie'){

	$matiereVisee=trim($_POST['matiere_suppression']);
	$categorieVisee=trim($_POST['categorie_a_supprimer']);

	if($matiereVisee==''||$categorieVisee==''){

		$message="Choisis une matière et la catégorie à supprimer.";

	}else{

		// Meme comparaison nettoyee que les renommages : d'anciennes lignes
		// portent un encodage different qui s'affiche pourtant a l'identique.
		$competencesVisees=array();
		$officiellesVisees=0;
		$req=$dbh->query('SELECT id_comp,matiere,categorie,officiel FROM comp_type');
		foreach($req->fetchAll(PDO::FETCH_ASSOC) as $ligneComp){
			if(nettoyerTexte($ligneComp['matiere'])!==$matiereVisee||nettoyerTexte($ligneComp['categorie'])!==$categorieVisee){ continue; }
			$competencesVisees[]=(int)$ligneComp['id_comp'];
			if(!empty($ligneComp['officiel'])){ $officiellesVisees++; }
		}
		$connaissancesVisees=array();
		$req=$dbh->query('SELECT id_eval,matiere,categorie FROM eval_type');
		foreach($req->fetchAll(PDO::FETCH_ASSOC) as $ligneEval){
			if(nettoyerTexte($ligneEval['matiere'])!==$matiereVisee||nettoyerTexte($ligneEval['categorie'])!==$categorieVisee){ continue; }
			$connaissancesVisees[]=(int)$ligneEval['id_eval'];
		}

		$nbUtilisees=0;
		foreach($competencesVisees as $idComp){
			$req=$dbh->prepare('SELECT COUNT(*) FROM comp_eleves WHERE id_comp=:id');
			$req->bindValue(':id',$idComp,PDO::PARAM_INT);
			$req->execute();
			if((int)$req->fetchColumn()>0){ $nbUtilisees++; }
			$req->closeCursor();
		}
		foreach($connaissancesVisees as $idEval){
			$req=$dbh->prepare('SELECT COUNT(*) FROM eval_eleves WHERE id_eval=:id');
			$req->bindValue(':id',$idEval,PDO::PARAM_INT);
			$req->execute();
			if((int)$req->fetchColumn()>0){ $nbUtilisees++; }
			$req->closeCursor();
		}

		// Tout l'ensemble d'un coup, et non une competence apres l'autre : deux lignes
		// de la meme categorie peuvent nourrir la meme banque, et chacune paraitrait
		// inoffensive prise isolement alors qu'ensemble elles vident le jeu.
		$jeuxCasses=jeuxRendusInjouables($dbh,$competencesVisees);

		if(!$competencesVisees&&!$connaissancesVisees){

			$message="Aucune compétence ni connaissance dans cette catégorie : rien à supprimer.";

		}elseif($nbUtilisees>0){

			$message="Impossible de supprimer « ".$categorieVisee." » : ".$nbUtilisees." de ses lignes ont déjà été utilisées dans des évaluations. Renommez-la, ou déplacez ces lignes ailleurs.";

		}elseif($officiellesVisees>0&&!$peutSupprimerOfficielles){

			$message="Impossible de supprimer « ".$categorieVisee." » : elle contient ".$officiellesVisees." compétence(s) des programmes officiels, que seule une administratrice peut supprimer.";

		}else{

			foreach($competencesVisees as $idComp){
				$req=$dbh->prepare('DELETE FROM comp_type WHERE id_comp=:id');
				$req->bindValue(':id',$idComp,PDO::PARAM_INT);
				$req->execute();
			}
			foreach($connaissancesVisees as $idEval){
				$req=$dbh->prepare('DELETE FROM eval_type WHERE id_eval=:id');
				$req->bindValue(':id',$idEval,PDO::PARAM_INT);
				$req->execute();
			}
			$message="Catégorie « ".$categorieVisee." » supprimée (".count($competencesVisees)." compétence(s), ".count($connaissancesVisees)." connaissance(s)).";
			$message.=avertissementJeuxFastGames($jeuxCasses);
		}
	}
}

// Archiver / désarchiver une matière.
//
// Archiver ne supprime rien : la matière disparait simplement des listes et
// des menus. C'est réversible, et c'est le préalable obligatoire à toute
// suppression définitive.
if(isset($_POST['action'])&&($_POST['action']=='archiver_matiere'||$_POST['action']=='desarchiver_matiere')){

	$matiereVisee=trim($_POST['matiere_a_archiver']);
	$archiver=($_POST['action']=='archiver_matiere')?1:0;

	if($matiereVisee==''){
		$message='Choisis une matière.';
	}else{
		// Même précaution que pour le renommage : la collation MySQL ignore
		// casse et accents, mais plusieurs orthographes peuvent coexister.
		$touchees=0;
		foreach(array('comp_type','eval_type') as $table){
			foreach($dbh->query('SELECT DISTINCT matiere FROM '.$table)->fetchAll(PDO::FETCH_COLUMN) as $matiereBrute){
				if(nettoyerTexte($matiereBrute)!==$matiereVisee){ continue; }
				$req=$dbh->prepare('UPDATE '.$table.' SET archivee=:etat WHERE matiere=:matiere');
				$req->bindValue(':etat',$archiver,PDO::PARAM_INT);
				$req->bindValue(':matiere',$matiereBrute);
				$req->execute();
				$touchees+=$req->rowCount();
			}
		}
		$message=$archiver
			?'Matière "'.$matiereVisee.'" archivée ('.$touchees.' ligne(s)). Elle n\'apparait plus dans les listes ; rien n\'est perdu.'
			:'Matière "'.$matiereVisee.'" réaffichée ('.$touchees.' ligne(s)).';
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

		// Une matière contenant des compétences officielles ne peut pas être
		// supprimée en bloc : ce serait contourner la protection posée sur
		// chacune d'elles.
		$nbOfficielles=0;
		foreach($variantesComp as $matiereBrute){
			$req=$dbh->prepare('SELECT COUNT(*) FROM comp_type WHERE matiere=:matiere AND officiel=1');
			$req->bindValue(':matiere',$matiereBrute);
			$req->execute();
			$nbOfficielles+=(int)$req->fetchColumn();
			$req->closeCursor();
		}

		// Une matière archivée peut être supprimée MÊME si elle porte des
		// saisies : c'est tout l'intérêt de l'archivage, qui sépare « je ne
		// veux plus la voir » de « je veux l'effacer pour de bon ».
		// La confirmation par saisie du nom est exigée côté formulaire.
		// Archivee = plus AUCUNE ligne visible, ni en competences ni en
		// connaissances. Compter les lignes visibles plutot que les lignes
		// archivees evite de declarer archivee une matiere a moitie masquee.
		$nbVisibles=0;
		$nbVariantes=count($variantesComp)+count($variantesEval);
		foreach($variantesComp as $matiereBrute){
			$req=$dbh->prepare('SELECT COUNT(*) FROM comp_type WHERE matiere=:matiere AND archivee=0');
			$req->bindValue(':matiere',$matiereBrute);
			$req->execute();
			$nbVisibles+=(int)$req->fetchColumn();
			$req->closeCursor();
		}
		foreach($variantesEval as $matiereBrute){
			$req=$dbh->prepare('SELECT COUNT(*) FROM eval_type WHERE matiere=:matiere AND archivee=0');
			$req->bindValue(':matiere',$matiereBrute);
			$req->execute();
			$nbVisibles+=(int)$req->fetchColumn();
			$req->closeCursor();
		}
		$estArchivee=($nbVariantes>0&&$nbVisibles===0);

		$nomConfirme=isset($_POST['confirmation_nom'])?trim($_POST['confirmation_nom']):'';

		if($nbOfficielles>0){

			$message="Impossible de supprimer \"".$matiereASupprimer."\" : elle contient ".$nbOfficielles." compétence(s) issue(s) des programmes officiels.";

		}elseif($nbUtilisees>0&&!$estArchivee){

			$message="Impossible de supprimer \"".$matiereASupprimer."\" : ".$nbUtilisees." saisie(s) existante(s) l'utilisent déjà. Archive-la d'abord si tu veux vraiment l'effacer.";

		}elseif($nbUtilisees>0&&$nomConfirme!==$matiereASupprimer){

			$message="Suppression annulée : pour effacer \"".$matiereASupprimer."\" et ses ".$nbUtilisees." saisie(s), il faut retaper son nom exactement.";

		}else{

			// Les saisies d'abord : les effacer après aurait laissé des lignes
			// orphelines dans comp_eleves, pointant vers des compétences qui
			// n'existent plus.
			$nbSaisies=0;
			foreach($variantesComp as $matiereBrute){
				$req=$dbh->prepare('DELETE comp_eleves FROM comp_eleves INNER JOIN comp_type ON comp_type.id_comp=comp_eleves.id_comp WHERE comp_type.matiere=:matiere');
				$req->bindValue(':matiere',$matiereBrute);
				$req->execute();
				$nbSaisies+=$req->rowCount();
			}
			foreach($variantesEval as $matiereBrute){
				$req=$dbh->prepare('DELETE eval_eleves FROM eval_eleves INNER JOIN eval_type ON eval_type.id_eval=eval_eleves.id_eval WHERE eval_type.matiere=:matiere');
				$req->bindValue(':matiere',$matiereBrute);
				$req->execute();
				$nbSaisies+=$req->rowCount();
			}

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

			$message="Matière \"".$matiereASupprimer."\" supprimée (".$nbComp." compétence(s), ".$nbEval." connaissance(s)"
				.($nbSaisies>0?", et ".$nbSaisies." saisie(s) d'élèves effacée(s) définitivement":"").").";
		}
	}
}

$rechercheMatiere=isset($_GET['matiere'])?htmlspecialchars(trim($_GET['matiere'])):'';
$rechercheCategorie=isset($_GET['categorie'])?htmlspecialchars(trim($_GET['categorie'])):'';
$rechercheCycle=isset($_GET['cycle'])?htmlspecialchars(trim($_GET['cycle'])):'';
$rechercheTexte=isset($_GET['texte'])?htmlspecialchars(trim($_GET['texte'])):'';
$rechercheUtilisation=(isset($_GET['utilisation'])&&$_GET['utilisation']=='zero')?'zero':'';
$rechercheOrigine=(isset($_GET['origine'])&&in_array($_GET['origine'],array('officielle','enseignant')))?$_GET['origine']:'';

// Pas de CP : FastEval n'est pas utilisé à ce niveau, et les compétences
// officielles importées l'excluent. Le proposer dans le menu ne mènerait
// qu'à une liste vide.
$niveauxPossibles=array('CE1','CE2');
if(isset($_GET['niveau'])){
	$rechercheNiveau=in_array($_GET['niveau'],$niveauxPossibles)?$_GET['niveau']:'';
}else{
	$rechercheNiveau='';
}
$tri=isset($_GET['tri'])?$_GET['tri']:'manuel';
$direction=(isset($_GET['direction'])&&strtolower($_GET['direction'])=='desc')?'desc':'asc';
// Matière et catégorie ne sont plus des colonnes triables : elles servent
// maintenant de niveaux de regroupement, toujours dans l'ordre alphabétique.
// Le tri choisi ci-dessous s'applique À L'INTÉRIEUR de chaque catégorie.
// « manuel » = l'ordre range a la main par glisser-deposer. Les lignes jamais
// rangees valent 0 et retombent donc sur l'ordre alphabetique de l'intitule :
// tant que personne n'a rien deplace, l'ecran est identique a avant.
$trisAutorises=array('manuel'=>'ordre','code'=>'designation','intitule'=>'commentaire','cycle'=>'cycle','utilisation'=>'nb_utilisation');
if(!isset($trisAutorises[$tri])){ $tri='manuel'; }

$sql='SELECT comp_type.id_comp,comp_type.designation,comp_type.commentaire,comp_type.matiere,comp_type.categorie,comp_type.cycle,comp_type.officiel,comp_type.niveau,
	(SELECT COUNT(*) FROM comp_eleves WHERE comp_eleves.id_comp=comp_type.id_comp) AS nb_utilisation
	FROM comp_type WHERE commentaire IS NOT NULL AND commentaire!=\'\'';
$parametres=array();

// Les matières archivées sont masquées, sauf demande explicite. C'est tout
// l'objet de l'archivage : faire disparaitre un jeu de compétences devenu
// inutile sans rien effacer, et sans perdre l'historique des élèves.
$voirArchivees=isset($_GET['archivees'])&&$_GET['archivees']==='1';

// Le glisser-deposer n'est propose qu'a un compte administrateur, et
// seulement sur le tri manuel : ranger a la main une liste triee par une
// colonne donnerait un geste perdu au rechargement.
// $estAdministrateur est defini en tete de fichier, avant les traitements de
// formulaire : il y sert aussi au droit de reformuler une competence
// officielle, et deux definitions du meme droit finiraient par diverger.
$peutRanger=($estAdministrateur&&$tri==='manuel'&&$direction==='asc');

// La recherche en cours, telle qu'elle sera renvoyee apres un enregistrement.
// `modifier` et `modifie` en sont retires : le premier rouvrirait le
// formulaire, le second est repose par la redirection elle-meme.
$filtresCourants=$_GET;
unset($filtresCourants['modifier'],$filtresCourants['modifie'],$filtresCourants['ouvrir']);
$retourCourant=http_build_query($filtresCourants);
// La ligne a mettre en evidence au retour, s'il y en a une.
$ligneMiseEnAvant=isset($_GET['modifie'])?(int)$_GET['modifie']:0;
// La categorie a rouvrir au retour, apres une suppression : « matiere|categorie ».
// Elle est comparee aux LIBELLES et non a un identifiant, parce que les
// identifiants de groupe de cette page (mat1cat2) sont des numeros de position,
// recalcules a chaque recherche - ils ne survivraient pas a une redirection.
$categorieMiseEnAvant=isset($_GET['ouvrir'])?(string)$_GET['ouvrir']:'';

if(!$voirArchivees){ $sql.=' AND archivee=0'; }

if($rechercheMatiere!==''){ $sql.=' AND matiere=:matiere'; $parametres[':matiere']=$rechercheMatiere; }
if($rechercheCategorie!==''){ $sql.=' AND categorie=:categorie'; $parametres[':categorie']=$rechercheCategorie; }
if($rechercheCycle!==''){ $sql.=' AND cycle=:cycle'; $parametres[':cycle']=$rechercheCycle; }
if($rechercheTexte!==''){ $sql.=' AND commentaire LIKE :texte'; $parametres[':texte']='%'.$rechercheTexte.'%'; }
if($rechercheOrigine==='officielle'){ $sql.=' AND officiel=1'; }
if($rechercheOrigine==='enseignant'){ $sql.=' AND officiel=0'; }
// Les compétences sans niveau renseigné (toutes celles d'avant l'import)
// restent visibles quel que soit le filtre : elles ne portent pas cette
// information, les masquer reviendrait à les faire disparaître.
if($rechercheNiveau!==''){ $sql.=' AND (niveau=\'\' OR FIND_IN_SET(:niveau,niveau))'; $parametres[':niveau']=$rechercheNiveau; }
if($rechercheUtilisation==='zero'){ $sql.=' AND (SELECT COUNT(*) FROM comp_eleves WHERE comp_eleves.id_comp=comp_type.id_comp)=0'; }

// Les matières restent alphabétiques. Les catégories, elles, suivent l'ordre
// rangé à la main s'il existe : une catégorie jamais déplacée vaut 0 et
// retombe donc sur son nom, c'est-à-dire sur l'ordre alphabétique d'avant.
// Le tri de colonne, lui, ordonne les lignes à l'intérieur de chaque
// catégorie.
$sql.=' ORDER BY matiere ASC, ordre_categorie ASC, categorie ASC, '.$trisAutorises[$tri].' '.strtoupper($direction);
// Deux lignes de meme rang (typiquement les 0 de depart) restent departagees
// par leur intitule, sinon MySQL les rendrait dans un ordre arbitraire, qui
// peut changer d'un chargement a l'autre.
if($tri==='manuel'){ $sql.=', commentaire ASC'; }

$req=$dbh->prepare($sql);
foreach($parametres as $cle=>$valeur){ $req->bindValue($cle,$valeur); }
$req->execute();
$resultats=$req->fetchAll(PDO::FETCH_ASSOC);
$req->closeCursor();

// Diagnostic en lecture seule : mêmes codes présents sur plusieurs lignes.
// Deux requêtes au total (les codes en double, puis toutes leurs lignes d'un
// coup) au lieu d'une requête par doublon comme avant.
$doublons=array();
$req=$dbh->query("SELECT designation FROM comp_type WHERE designation IS NOT NULL AND designation!='' GROUP BY designation HAVING COUNT(*)>1 ORDER BY designation");
$designationsDoublons=$req->fetchAll(PDO::FETCH_COLUMN);
$req->closeCursor();

if(count($designationsDoublons)>0){
	$marqueurs=implode(',',array_fill(0,count($designationsDoublons),'?'));
	$reqDetails=$dbh->prepare('SELECT comp_type.id_comp,comp_type.designation,comp_type.commentaire,comp_type.matiere,comp_type.categorie,comp_type.cycle,(SELECT COUNT(*) FROM comp_eleves WHERE comp_eleves.id_comp=comp_type.id_comp) AS nb_utilisation FROM comp_type WHERE designation IN ('.$marqueurs.') ORDER BY designation ASC, id_comp ASC');
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

// La categorie a rouvrir apres une suppression, ramenee a la meme cle de
// regroupement que le tableau : deux orthographes d'une meme matiere doivent y
// mener au meme endroit, exactement comme pour l'affichage.
$cleCategorieMiseEnAvant='';
if($categorieMiseEnAvant!==''){
	$morceaux=explode('|',$categorieMiseEnAvant,2);
	if(count($morceaux)===2){
		$cleCategorieMiseEnAvant=cleGroupe(nettoyerTexte($morceaux[0])).'|'.cleGroupe(nettoyerTexte($morceaux[1]));
	}
}

// Un filtre est-il posé ?
$unFiltreEstPose=($rechercheMatiere!==''||$rechercheCategorie!==''||$rechercheCycle!==''||$rechercheTexte!==''||$rechercheUtilisation!==''||$rechercheOrigine!==''
	||$rechercheNiveau!=='');

// Un filtre n'ouvre les groupes que si le résultat tient raisonnablement à
// l'écran. Chercher un mot précis et devoir encore cliquer serait absurde ;
// mais déplier « Programmes officiels », qui renvoie des centaines de lignes,
// reproduirait exactement le mur qu'on cherchait à supprimer.
$seuilDepliage=50;
$filtreActif=($unFiltreEstPose&&count($resultats)<=$seuilDepliage);

// arbre matiere -> [categories] pour les menus deroulants en cascade (le cycle est independant, pas imbrique)
// Le menu suit la liste : une matière archivée n'y figure plus non plus.
//
// ET IL SUIT AUSSI SON ORDRE. Cette requete triait par nom, alors que le
// tableau au-dessus trie par `ordre_categorie` depuis que le rangement a la
// main existe : une categorie deplacee changeait de place dans la liste mais
// pas dans le selecteur, qui restait alphabetique. Signale par le responsable technique le
// 06/09/2026. Les MATIERES, elles, restent alphabetiques - decision deja prise
// et inchangee, seules les categories se rangent a la main.
//
// GROUP BY plutot que DISTINCT : ajouter `ordre_categorie` a un SELECT DISTINCT
// rendrait deux fois la meme categorie si ses lignes ne portaient pas toutes le
// meme rang. MIN() tranche, et le dedoublonnage PHP plus bas reste un filet.
$req=$dbh->query('SELECT matiere,categorie,MIN(ordre_categorie) AS ordre_categorie FROM comp_type WHERE commentaire IS NOT NULL AND commentaire!=\'\''
	.($voirArchivees?'':' AND archivee=0').' GROUP BY matiere,categorie ORDER BY matiere ASC, ordre_categorie ASC, categorie ASC');
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

// Liste a part pour le formulaire de reaffichage : uniquement ce qui est archive.
$matieresArchivees=$dbh->query('SELECT DISTINCT matiere FROM comp_type WHERE archivee=1 ORDER BY matiere ASC')->fetchAll(PDO::FETCH_COLUMN);

$ligneAModifier=null;
if(isset($_GET['modifier'])){
	$req=$dbh->prepare('SELECT id_comp,designation,commentaire,matiere,categorie,cycle,officiel,niveau FROM comp_type WHERE id_comp=:id_comp');
	$req->bindParam(':id_comp',$_GET['modifier']);
	$req->execute();
	$ligneAModifier=$req->fetch(PDO::FETCH_ASSOC);
	$req->closeCursor();

	// Un lien de modification vers une compétence officielle (favori, retour
	// arrière) ne doit pas ouvrir le formulaire prérempli - sauf pour un
	// compte administrateur, qui a le droit de la reformuler depuis le
	// 06/09/2026.
	if($ligneAModifier&&!empty($ligneAModifier['officiel'])&&!$peutModifierOfficielles){
		$ligneAModifier=null;
		if($message===''){ $message="Cette compétence est issue des programmes officiels : seule une administratrice peut la reformuler."; }
	}
}

?>
<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Liste des compétences</title>

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
            <nav class="fil" aria-label="Fil d'Ariane"><a href="/portail/">Portail</a><span aria-hidden="true">›</span><a href="presentation.php">Accueil</a><span aria-hidden="true">›</span><span class="actuel">Liste des compétences</span></nav>
            <?php
            require_once $_SERVER['DOCUMENT_ROOT'].'/v2/galaxie-role.php';
            echo gxMenuIdentite('Fast Éval');
            ?>
        </header>
<div class="container bibliotheque-page gx-largeur-grille" id="haut-page">

        <div class="gx-cartouche gx-entete-cadree fe-hero gx-ligne-titre" id="contenu">
            <span class="fe-surtitre">Bibliothèque pédagogique</span>
            <h1>Liste des compétences</h1>
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
					<li>ID <?php echo (int)$detail['id_comp']; ?> — <?php echo htmlspecialchars(nettoyerTexte($detail['matiere'])); ?> / <?php echo htmlspecialchars(nettoyerTexte($detail['categorie'])); ?> / <?php echo htmlspecialchars(nettoyerTexte($detail['cycle'])); ?> — « <?php echo htmlspecialchars(nettoyerTexte($detail['commentaire'])); ?> » — <?php echo (int)$detail['nb_utilisation']; ?> utilisation(s)</li>
				<?php } ?>
				</ul>
				</li>
			<?php } ?>
			</ul>
		</div>
		<?php } ?>

		<div class="raccourcis-page">
			<a href="#form-renommer">Gérer les compétences</a>
		</div>

		<div class="section-encadree">

			<div class="section-header">
				<div>
					<h2>Rechercher</h2>
					<p class="section-description">Affinez la liste par matière, catégorie, cycle ou texte.</p>
				</div>
			</div>

			<form method="get" action="liste-competences.php" class="form-recherche">
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
						<label for="niveau">Niveau</label>
						<select name="niveau" id="niveau" class="form-control">
							<option value="">Tous les niveaux</option>
							<?php foreach($niveauxPossibles as $n){ ?>
							<option value="<?php echo htmlspecialchars($n); ?>" <?php echo $rechercheNiveau===$n?'selected':''; ?>><?php echo htmlspecialchars($n); ?></option>
							<?php } ?>
						</select>
					</div>
				</div>
				</div>

				<?php
				/* SIX FILTRES A L'ECRAN, dont trois qui ne servent presque jamais.
				   Matiere, categorie et niveau restent visibles ; cycle, utilisation
				   et origine se deplient.
				   LE BLOC S'OUVRE DE LUI-MEME DES QU'UN DE CES TROIS EST ACTIF :
				   une liste filtree doit toujours montrer POURQUOI elle l'est,
				   sinon on cherche longtemps une compétence qu'un filtre oublie
				   masque.
				   LE NIVEAU RESTE VISIBLE, exprès : il vaut la classe de
				   l'enseignante par defaut, il filtre donc DEJA la liste a
				   l'arrivee - mesure le 06/09/2026, 27 compétences d'« Espace et
				   geometrie » tous niveaux contre 13 en CE2. Le cacher rendrait ce
				   retrait invisible. */
				$filtresAvancesActifs=($rechercheCycle!==''||$rechercheUtilisation!==''||$rechercheOrigine!=='');
				?>
				<details class="bloc-action bloc-filtres" <?php echo $filtresAvancesActifs?'open':''; ?>>
					<summary>Plus de filtres<?php if($filtresAvancesActifs){ ?> <span class="bloc-action-note">actifs</span><?php } ?></summary>
					<div class="row">
						<div class="col-md-4">
							<label for="cycle">Cycle</label>
							<select name="cycle" id="cycle" class="form-control">
								<option value="">Tous</option>
								<?php foreach($cyclesPossibles as $c){ ?>
								<option value="<?php echo htmlspecialchars($c); ?>" <?php echo $rechercheCycle==$c?'selected':''; ?>><?php echo htmlspecialchars($c); ?></option>
								<?php } ?>
							</select>
						</div>
						<div class="col-md-4">
							<label for="utilisation">Utilisation</label>
							<select name="utilisation" id="utilisation" class="form-control">
								<option value="">Toutes</option>
								<option value="zero" <?php echo $rechercheUtilisation==='zero'?'selected':''; ?>>Jamais utilisées (0 fois)</option>
							</select>
						</div>
						<div class="col-md-4">
							<label for="origine">Origine</label>
							<select name="origine" id="origine" class="form-control">
								<option value="">Toutes</option>
								<option value="officielle" <?php echo $rechercheOrigine==='officielle'?'selected':''; ?>>Programmes officiels</option>
								<option value="enseignant" <?php echo $rechercheOrigine==='enseignant'?'selected':''; ?>>Créées par les enseignants</option>
							</select>
						</div>
					</div>
				</details>
				<div class="form-actions">
					<button type="submit" class="btn btn-color">Rechercher</button>
					<a href="liste-competences.php" class="btn btn-secondary">Réinitialiser</a>
					<?php
					// Les coupons reprennent la selection affichee : on repasse les
					// memes parametres, coupons.php les relit de la meme facon.
					$parametresCoupons=$_GET;
					$parametresCoupons['type']='comp';
					?>
					<a href="coupons.php?<?php echo htmlspecialchars(http_build_query($parametresCoupons)); ?>" class="btn btn-secondary" target="_blank" rel="noopener">Imprimer les coupons</a>
				</div>
			</form>

		</div>

		<div class="section-encadree">

			<div class="section-header">
				<div>
					<h2>Résultats</h2>
					<p class="section-description">Les compétences utilisées ne peuvent pas être supprimées.</p>
				</div>
				<span class="result-count"><?php echo count($resultats); ?></span>
			</div>
			<p class="legende-utilisation"><strong>« X fois »</strong> indique le nombre total de saisies enregistrées avec cette compétence, toutes classes et tous enseignants confondus. Ce n’est pas le nombre de fois où elle apparaît dans un livret.</p>

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

			<?php // Le bandeau sert a deux choses : expliquer la poignee a qui peut
			// ranger, et offrir a tout le monde le chemin de retour vers l'ordre
			// manuel, qu'aucun en-tete de colonne ne propose.
			if($estAdministrateur||$tri!=='manuel'){ ?>
			<div class="bandeau-rangement">
				<?php if($peutRanger){ ?>
				<span>Tu peux ranger les compétences à la main : attrape la poignée <span class="poignee-rangement" aria-hidden="true">&#8942;</span> tout à droite de la ligne et fais-la glisser. Le déplacement ne sort jamais de sa catégorie, et il est enregistré aussitôt.</span>
				<span class="etat-rangement"></span>
				<?php } else { ?>
				<span><?php if($estAdministrateur){ ?>Le rangement à la main n'est possible que sur l'ordre manuel. <?php } ?><a href="<?php echo htmlspecialchars(lienTri('manuel','','desc')); ?>">Revenir à l'ordre manuel</a><?php if($estAdministrateur){ ?> pour déplacer des lignes.<?php } ?></span>
				<?php } ?>
			</div>
			<?php } ?>

			<div class="table-responsive-fasteval">
			<table class="table table-bordered tableau-groupe">
				<thead>
					<tr>
						<th><a class="tri-tableau" href="<?php echo htmlspecialchars(lienTri('code',$tri,$direction)); ?>">Code</a></th>
						<th><a class="tri-tableau" href="<?php echo htmlspecialchars(lienTri('intitule',$tri,$direction)); ?>">Intitulé</a></th>
						<th><a class="tri-tableau" href="<?php echo htmlspecialchars(lienTri('cycle',$tri,$direction)); ?>">Cycle</a></th>
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
						<th colspan="6">
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
					<?php $categorieEnAvant=($cleCategorieMiseEnAvant!==''&&$cleCategorieMiseEnAvant===$cleMatiere.'|'.$cleCategorie); ?>
					<tr class="ligne-categorie<?php echo $categorieEnAvant?' est-mise-en-avant':''; ?>" data-groupe="<?php echo $idMatiere; ?>" data-categorie-id="<?php echo $idCategorie; ?>" data-matiere="<?php echo htmlspecialchars($nomMatiere); ?>" data-categorie-nom="<?php echo htmlspecialchars($nomCategorie); ?>" <?php echo $filtreActif?'':'hidden'; ?>>
						<th colspan="6"<?php echo $peutRanger?' class="avec-poignee"':''; ?>>
							<button type="button" class="bascule bascule-categorie" data-groupe="<?php echo $idMatiere; ?>" data-categorie="<?php echo $idCategorie; ?>" aria-expanded="<?php echo $filtreActif?'true':'false'; ?>">
								<span class="chevron" aria-hidden="true"></span>
								<span class="nom-groupe"><?php echo htmlspecialchars($nomCategorie); ?></span>
								<span class="compteur-groupe"><?php echo count($lignesCategorie); ?></span>
							</button>
							<?php if($peutRanger){ ?>
							<span class="poignee-rangement poignee-categorie" title="Glisser pour déplacer cette catégorie dans sa matière" aria-hidden="true">&#8942;</span>
							<?php } ?>
						</th>
					</tr>
					<?php foreach($lignesCategorie as $ligne){ ?>
					<?php $estOfficielle=!empty($ligne['officiel']); ?>
					<tr class="ligne-donnee<?php echo $ligneMiseEnAvant===(int)$ligne["id_comp"]?" est-mise-en-avant":""; ?>" id="ligne-comp-<?php echo (int)$ligne["id_comp"]; ?>" data-groupe="<?php echo $idMatiere; ?>" data-categorie="<?php echo $idCategorie; ?>" data-id="<?php echo (int)$ligne["id_comp"]; ?>" <?php echo $filtreActif?'':'hidden'; ?>>
						<td data-intitule="Code"><?php echo htmlspecialchars(nettoyerTexte($ligne['designation'])); ?></td>
						<td data-intitule="Intitulé">
							<?php if($estOfficielle){ ?><span class="picto-officiel" title="<?php echo $peutModifierOfficielles?'Compétence des programmes officiels. En tant qu’administratrice, vous pouvez la reformuler ou la supprimer ; elle est partagée par toutes les classes.':'Compétence officielle, reprise mot pour mot des programmes. Elle ne peut être ni modifiée ni supprimée.'; ?>">🏛</span> <?php } ?>
							<?php echo htmlspecialchars(nettoyerTexte($ligne['commentaire'])); ?>
						</td>
						<td class="centre" data-intitule="Cycle">
							<?php echo htmlspecialchars($ligne['cycle']); ?>
							<?php if(!empty($ligne['niveau'])){ ?>
							<span class="badge-niveau"><?php echo htmlspecialchars(str_replace(',',' · ',$ligne['niveau'])); ?></span>
							<?php } ?>
						</td>
						<td class="centre" data-intitule="Utilisation"><span class="badge-utilisation <?php echo $ligne['nb_utilisation']==0?'zero':''; ?>"><?php echo $ligne['nb_utilisation']; ?> fois</span></td>
						<td class="centre" data-intitule="Code-barres">
							<button type="button" class="btn-code-barre" data-code="<?php echo htmlspecialchars(nettoyerTexte($ligne['designation'])); ?>" data-colonnes="6">Code</button>
						</td>
						<td data-intitule="Actions"<?php echo $peutRanger?' class="cellule-rangeable"':''; ?>>
							<div class="cellule-actions">
								<?php if($estOfficielle){ ?>
								<span class="mention-officielle">Programmes officiels</span>
								<?php if($peutModifierOfficielles){ ?>
								<a href="liste-competences.php?modifier=<?php echo (int)$ligne["id_comp"]; ?><?php echo $retourCourant!==""?"&amp;".htmlspecialchars($retourCourant):""; ?>#form-ajout" class="btn btn-sm btn-secondary">Reformuler</a>
								<?php } ?>
								<?php if($peutSupprimerOfficielles){ ?>
								<?php /* La confirmation dit ce qu'une compétence officielle a de
								         particulier : elle est partagée par toutes les classes. Le
								         serveur refuse de toute façon si elle a déjà servi - le clic
								         n'est pas la seule barrière. */ ?>
								<form method="post" action="liste-competences.php" onsubmit="return confirm('Supprimer cette compétence des programmes officiels ? Elle disparaîtra pour tous les enseignants.');" style="margin:0;">
									<input type="hidden" name="action" value="supprimer">
									<input type="hidden" name="retour" value="<?php echo htmlspecialchars($retourCourant); ?>">
									<input type="hidden" name="id_comp" value="<?php echo (int)$ligne['id_comp']; ?>">
									<button type="submit" class="btn-supprimer" title="Disponible uniquement si cette compétence n’a jamais été utilisée. Un mini-jeu de Fast Games ne l’empêche pas : le site vous dit lesquels quittent le catalogue.">Supprimer</button>
								</form>
								<?php } ?>
								<?php }else{ ?>
								<a href="liste-competences.php?modifier=<?php echo (int)$ligne["id_comp"]; ?><?php echo $retourCourant!==""?"&amp;".htmlspecialchars($retourCourant):""; ?>#form-ajout" class="btn btn-sm btn-secondary">Modifier</a>
								<form method="post" action="liste-competences.php" onsubmit="return confirm('Supprimer cette compétence ?');" style="margin:0;">
									<input type="hidden" name="action" value="supprimer">
									<input type="hidden" name="retour" value="<?php echo htmlspecialchars($retourCourant); ?>">
									<input type="hidden" name="id_comp" value="<?php echo (int)$ligne['id_comp']; ?>">
									<button type="submit" class="btn-supprimer" title="Disponible uniquement si cette compétence n’a jamais été utilisée. Un mini-jeu de Fast Games ne l’empêche pas : le site vous dit lesquels quittent le catalogue.">Supprimer</button>
								</form>
								<?php } ?>
								<?php if($peutRanger){ ?>
								<span class="poignee-rangement" title="Glisser pour déplacer cette compétence dans sa catégorie" aria-hidden="true">&#8942;</span>
								<?php } ?>
							</div>
						</td>
					</tr>
					<?php } ?>
					<?php } ?>
				</tbody>
				<?php } ?>
				<?php if(count($resultats)==0){ ?>
				<tbody>
					<tr><td colspan="6" class="text-center">Aucune compétence ne correspond à cette recherche.</td></tr>
				</tbody>
				<?php } ?>
			</table>
			</div>

		</div>

		<div class="section-encadree" id="form-ajout">

			<?php
			/* LE MENU DE GESTION EST REPLIE PAR DEFAUT. La page sert d'abord a
			   CONSULTER le referentiel ; creation et organisation vivent dans ce
			   panneau unique. Il s'ouvre de lui-meme avec ?modifier=... - sinon
			   le bouton « Modifier » d'une ligne menerait a un bloc ferme, ce qui
			   ressemblerait a un lien casse. */
			$modifieUneOfficielle=($ligneAModifier&&!empty($ligneAModifier['officiel']));
			?>
			<details class="bloc-action bloc-formulaire" id="form-renommer" <?php echo $ligneAModifier?'open':''; ?>>
			<summary>Gérer les compétences</summary>

			<div class="section-header">
				<div>
					<h3><?php echo $ligneAModifier?($modifieUneOfficielle?'Reformuler une compétence officielle':'Modifier une compétence'):'Créer une compétence'; ?></h3>
					<?php if(!$ligneAModifier){ ?>
					<p class="section-description">Le code sera généré automatiquement.</p>
					<?php }else{ ?>
					<p class="section-description">Code : <?php echo htmlspecialchars(nettoyerTexte($ligneAModifier['designation'])); ?> (inchangé).</p>
					<?php if($modifieUneOfficielle){ ?>
					<?php /* Dire AVANT l'ecriture ce que la modification touche : le referentiel
					         est partage, et le code ne change pas - c'est lui qui relie la
					         competence aux bilans deja saisis et aux jeux de Fast Games. */ ?>
					<p class="section-description">Cette compétence vient des programmes officiels. Son code ne change pas : les évaluations déjà saisies et les mini-jeux qui s’y rattachent la suivent. Le référentiel étant partagé, le nouvel intitulé s’affichera pour tous les enseignants.</p>
					<?php } ?>
					<?php } ?>
				</div>
			</div>

			<form method="post" action="liste-competences.php" class="form-ajout">
				<input type="hidden" name="retour" value="<?php echo htmlspecialchars($retourCourant); ?>">
				<input type="hidden" name="action" value="<?php echo $ligneAModifier?'modifier':'creer'; ?>">
				<?php if($ligneAModifier){ ?>
				<input type="hidden" name="id_comp" value="<?php echo (int)$ligneAModifier['id_comp']; ?>">
				<?php } ?>

				<?php
				/* MATIERE ET CATEGORIE SONT DE VRAIES LISTES DEROULANTES depuis le
				   06/09/2026, avec une entree « Autre… » qui ouvre un champ libre.
				   C'etaient des `<input list=datalist>` : des qu'un choix etait fait,
				   la liste se filtrait sur cette seule valeur et la fleche ne servait
				   plus a rien - il fallait effacer le champ au clavier pour pouvoir en
				   choisir un autre. Le champ libre reste indispensable : c'est par lui
				   qu'on cree une matiere ou une categorie qui n'existe pas encore.
				   La valeur d'une ligne en cours de modification peut ne plus figurer
				   dans la liste (matiere archivee, orthographe unique) : dans ce cas on
				   ouvre « Autre… » deja rempli, plutot que de la perdre en silence. */
				$matiereCourante=$ligneAModifier?nettoyerTexte($ligneAModifier['matiere']):'';
				$categorieCourante=$ligneAModifier?nettoyerTexte($ligneAModifier['categorie']):'';
				$matiereHorsListe=($matiereCourante!==''&&!in_array($matiereCourante,$listeMatieres,true));
				$niveauCourant=$ligneAModifier&&isset($ligneAModifier['niveau'])?(string)$ligneAModifier['niveau']:'';
				if(!array_key_exists($niveauCourant,$niveauxCompetence)){ $niveauCourant=''; }
				?>
				<div class="row">
					<div class="col-md-3 champ">
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
					<div class="col-md-3 champ">
						<label for="categorie_ajout">Catégorie</label>
						<select class="form-control" name="categorie" id="categorie_ajout" required>
							<option value="">-- Choisir --</option>
							<option value="__autre__">Autre catégorie…</option>
						</select>
						<input type="text" class="form-control champ-autre" name="categorie_autre" id="categorie_autre" placeholder="Nom de la nouvelle catégorie" hidden>
					</div>
					<div class="col-md-3 champ">
						<label for="cycle_ajout">Cycle</label>
						<select class="form-control" name="cycle" id="cycle_ajout" required>
							<?php foreach($cyclesPossibles as $c){ ?>
							<option value="<?php echo htmlspecialchars($c); ?>" <?php echo ($ligneAModifier&&$ligneAModifier['cycle']==$c)?'selected':''; ?>><?php echo htmlspecialchars($c); ?></option>
							<?php } ?>
						</select>
					</div>
					<div class="col-md-3 champ">
						<label for="niveau_ajout">Niveau</label>
						<select class="form-control" name="niveau" id="niveau_ajout">
							<?php foreach($niveauxCompetence as $valeur=>$libelle){ ?>
							<option value="<?php echo htmlspecialchars($valeur); ?>" <?php echo $niveauCourant===(string)$valeur?'selected':''; ?>><?php echo htmlspecialchars($libelle); ?></option>
							<?php } ?>
						</select>
						<small class="aide-champ">Le niveau décide de ce que Fast Games propose à une classe, et filtre cette page.</small>
					</div>
				</div>

				<?php if($estAdministrateur){ ?>
				<?php /* Reserve a une administratrice, et le serveur le revérifie : cocher
				         cette case pose le picto 🏛 et fait entrer la ligne dans le filtre
				         « Programmes officiels », mais surtout elle la place sous le droit
				         qui reserve sa modification aux administratrices. */ ?>
				<div class="champ champ-officiel">
					<label for="officiel_ajout">
						<input type="checkbox" name="officiel" id="officiel_ajout" value="1" <?php echo ($ligneAModifier&&!empty($ligneAModifier['officiel']))?'checked':''; ?>>
						Issue des programmes officiels
					</label>
					<small class="aide-champ">Ajoute le repère 🏛 et range la ligne dans « Programmes officiels ». Une compétence officielle n'est modifiable que par une administratrice.</small>
				</div>
				<?php } ?>

				<div class="champ">
					<label for="commentaire">Intitulé de la compétence</label>
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
					<a href="liste-competences.php<?php echo $retourCourant!==""?"?".htmlspecialchars($retourCourant):""; ?>" class="btn btn-secondary">Annuler</a>
					<?php } ?>
				</div>
			</form>

			<?php
			/* QUATRE ACTIONS DEPLIABLES PLUTOT QUE QUATRE FORMULAIRES EMPILES.
			   Avant le 06/09/2026, cette section faisait 762 px de haut : quatre
			   formulaires a la suite, cinq listes de matieres presque identiques,
			   huit champs - et il fallait tout parcourir pour trouver le bon.
			   Son titre annoncait « Renommer une matiere ou une categorie » alors
			   qu'elle contenait aussi l'archivage ET la suppression definitive :
			   le geste le plus destructeur du referentiel se trouvait au bas d'un
			   bloc qui promettait un renommage.
			   Chaque action est maintenant une ligne qu'on deplie. Un <details>
			   natif plutot qu'un accordeon en JavaScript : c'est deja le motif de
			   la maison (menu de compte, referentiels de l'accueil), et il marche
			   au clavier sans une ligne de script. */
			?>
			<div class="section-header">
				<div>
				<h3>Organiser les matières et les catégories</h3>
				<p class="section-description">Dépliez l'action dont vous avez besoin. Un changement de nom s'applique partout où il est utilisé, dans les compétences <strong>et</strong> les connaissances.</p>
				</div>
			</div>

			<details class="bloc-action">
				<summary>Renommer une matière</summary>
			<form method="post" action="liste-competences.php" class="form-renommer">
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
			<form method="post" action="liste-competences.php" class="form-renommer">
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

			<details class="bloc-action">
				<summary>Archiver une matière <span class="bloc-action-note">réversible</span></summary>
			<form method="post" action="liste-competences.php" class="form-renommer">
				<input type="hidden" name="action" value="archiver_matiere">
				<div class="champ">
					<label for="matiere_a_archiver">Archiver une matière</label>
					<select class="form-control" name="matiere_a_archiver" id="matiere_a_archiver" required>
						<option value="">-- Choisir --</option>
						<?php foreach($listeMatieres as $m){ ?>
						<option value="<?php echo htmlspecialchars($m); ?>"><?php echo htmlspecialchars($m); ?></option>
						<?php } ?>
					</select>
					<p class="section-description">La matière disparait des listes et des menus. <strong>Rien n'est perdu</strong>, l'historique des élèves reste en place, et le geste s'annule : le lien « Voir les matières archivées » ci-dessous la fait réapparaitre.</p>
				</div>
				<button type="submit" class="btn btn-color">Archiver la matière</button>
			</form>

			<p style="margin-top:8px;">
				<?php if($voirArchivees){ ?>
				<a href="liste-competences.php">Masquer les matières archivées</a>
				<?php } else { ?>
				<a href="liste-competences.php?archivees=1">Voir les matières archivées</a>
				<?php } ?>
			</p>

			<?php /* Le lien et le desarchivage vivent DANS le bloc d'archivage : ils
			         flottaient entre deux formulaires sans appartenir a aucun. */ ?>
			<?php if($voirArchivees){ ?>
			<form method="post" action="liste-competences.php" class="form-renommer" style="margin-top:16px;">
				<input type="hidden" name="action" value="desarchiver_matiere">
				<div class="champ">
					<label for="matiere_a_desarchiver">Réafficher une matière archivée</label>
					<select class="form-control" name="matiere_a_archiver" id="matiere_a_desarchiver" required>
						<option value="">-- Choisir --</option>
						<?php foreach($matieresArchivees as $m){ ?>
						<option value="<?php echo htmlspecialchars($m); ?>"><?php echo htmlspecialchars($m); ?></option>
						<?php } ?>
					</select>
				</div>
				<button type="submit" class="btn btn-color">Réafficher la matière</button>
			</form>
			<?php } ?>
			</details>

			<details class="bloc-action est-dangereux">
				<summary>Supprimer une catégorie <span class="bloc-action-note">irréversible</span></summary>
			<form method="post" action="liste-competences.php" class="form-renommer" onsubmit="return confirm('Supprimer cette catégorie et toutes les compétences et connaissances qu\'elle contient ? Geste irréversible.');">
				<input type="hidden" name="action" value="supprimer_categorie">
				<div class="champ">
					<label for="matiere_suppression">Matière</label>
					<select class="form-control" name="matiere_suppression" id="matiere_suppression" required>
						<option value="">-- Choisir --</option>
						<?php foreach($listeMatieres as $m){ ?>
						<option value="<?php echo htmlspecialchars($m); ?>"><?php echo htmlspecialchars($m); ?></option>
						<?php } ?>
					</select>
				</div>
				<div class="champ">
					<label for="categorie_a_supprimer">Catégorie à supprimer</label>
					<select class="form-control" name="categorie_a_supprimer" id="categorie_a_supprimer" required>
						<option value="">-- Choisir une matière d'abord --</option>
					</select>
				</div>
				<p class="section-description">Une catégorie n'est pas une ligne à part : la supprimer efface <strong>toutes les compétences et connaissances</strong> qu'elle contient. Le site refuse tant qu'une seule d'entre elles a déjà servi dans une évaluation — et il dit laquelle. Un mini-jeu de Fast Games n'empêche rien : s'il perd sa dernière compétence, il quitte simplement le catalogue et le site vous le nomme.</p>
				<button type="submit" class="btn-supprimer">Supprimer la catégorie</button>
			</form>
			</details>

			<details class="bloc-action est-dangereux">
				<summary>Supprimer définitivement une matière <span class="bloc-action-note">irréversible</span></summary>
			<form method="post" action="liste-competences.php" class="form-renommer" onsubmit="return confirm('Suppression définitive et irréversible. Les saisies d\'élèves de cette matière partent avec elle. Continuer ?');">
				<input type="hidden" name="action" value="supprimer_matiere">
				<div class="champ">
					<label for="matiere_a_supprimer">Supprimer définitivement une matière</label>
					<select class="form-control" name="matiere_a_supprimer" id="matiere_a_supprimer" required>
						<option value="">-- Choisir --</option>
						<?php foreach($listeMatieres as $m){ ?>
						<option value="<?php echo htmlspecialchars($m); ?>"><?php echo htmlspecialchars($m); ?></option>
						<?php } ?>
					</select>
				</div>
				<div class="champ">
					<label for="confirmation_nom">Retaper le nom de la matière pour confirmer</label>
					<input type="text" class="form-control" name="confirmation_nom" id="confirmation_nom" autocomplete="off">
				</div>
				<p class="section-description"><strong>Geste irréversible.</strong> Une matière jamais utilisée s'efface directement. Une matière qui porte déjà des saisies d'élèves doit d'abord être <strong>archivée</strong>, puis son nom retapé ci-dessus : ses saisies et l'historique correspondant seront alors effacés eux aussi.</p>
				<button type="submit" class="btn-supprimer">Supprimer la matière</button>
			</form>
			</details>

			</details>

		</div>

		<a href="#haut-page" class="retour-haut">↑ Haut de page</a>

	</div>

<script src="utils/jquery/jquery-1.11.3.min.js"></script>
<script src="utils/bootstrap/js/bootstrap.min.js"></script>

<script>
var arbreBibliotheque=<?php echo json_encode($arbre); ?>;
var valeurInitialeCategorieBibliotheque="<?php echo addslashes($rechercheCategorie); ?>";
// La categorie de la ligne en cours de modification : la liste des categories du
// formulaire est remplie par le script, elle ne peut donc pas etre preselectionnee
// en PHP comme les autres champs.
var valeurInitialeCategorieAjout="<?php echo addslashes($ligneAModifier?nettoyerTexte($ligneAModifier['categorie']):''); ?>";
</script>
<script src="utils/js/bibliotheque.js?v=20260906-05"></script>
<?php if($peutRanger){ ?>
<script src="utils/js/rangement-competences.js?v=20260906-01"></script>
<?php } ?>

</body>

</html>
