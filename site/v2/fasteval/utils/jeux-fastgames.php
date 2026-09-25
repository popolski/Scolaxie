<?php
/**
 * CE QU'UNE SUPPRESSION DE COMPETENCE COUTE A FAST GAMES.
 *
 * Une banque de Fast Games vise une LISTE d'identifiants de competences, et
 * `fgReferenceDeBanque()` (fastgames/includes/referentiel.php) retient la
 * PREMIERE de cette liste qui existe encore et n'est pas archivee. Un jeu ne
 * devient donc injouable que le jour ou il ne lui reste PLUS AUCUNE competence
 * vivante - pas des qu'on lui en retire une.
 *
 * CE FICHIER N'INTERDIT RIEN, IL PREVIENT. Un garde-fou refusait la suppression
 * des qu'un jeu citait la competence, y compris quand la banque en visait cinq
 * autres. le responsable technique a tranche le 09/09/2026 : le referentiel appartient a
 * l'enseignante, et un mini-jeu ne lui interdit pas d'en retirer une ligne, meme
 * s'il s'agit de la derniere du jeu. Fast Games encaisse le vide proprement - la
 * banque sans reference sort du catalogue, jeu.php renvoie vers
 * « indisponible » - donc rien ne se perd. Ce qui manquait, c'etait de le DIRE :
 * ces titres nourrissent le message affiche apres la suppression.
 *
 * On raisonne sur un ENSEMBLE d'identifiants et non sur un seul : supprimer une
 * categorie entiere retire plusieurs competences d'un coup, et deux d'entre
 * elles peuvent nourrir la meme banque. Vue une par une, aucune ne semblait
 * fatale ; ensemble, elles vidaient le jeu.
 *
 * VOLONTAIREMENT TOLERANT si le fichier des banques est absent : ce controle est
 * une courtoisie entre deux applications. Fast Eval ne doit pas cesser de
 * fonctionner parce que Fast Games a demenage ; la vraie protection reste le
 * compte des saisies deja portees par la competence.
 */

/** Les banques de Fast Games, ou un tableau vide si l'application n'est pas la. */
function banquesFastGames(){
	$chemin=$_SERVER['DOCUMENT_ROOT'].'/v2/fastgames/data/banques.php';
	if(!is_readable($chemin)){ return array(); }
	$banques=@include $chemin;
	return is_array($banques)?$banques:array();
}

/**
 * Une competence encore jouable par Fast Games : elle existe, elle porte un
 * libelle et elle n'est pas archivee - exactement le filtre de
 * `fgConfigurationReferentiel('competence')`. Le niveau (CE1/CE2) n'entre pas en
 * compte : il restreint le public d'un jeu, pas son existence.
 */
function competenceEncoreJouable(PDO $dbh,$idComp){
	$req=$dbh->prepare("SELECT COUNT(*) FROM comp_type WHERE id_comp=:id AND archivee=0 AND commentaire IS NOT NULL AND commentaire!=''");
	$req->bindValue(':id',(int)$idComp,PDO::PARAM_INT);
	$req->execute();
	$nombre=(int)$req->fetchColumn();
	$req->closeCursor();
	return $nombre>0;
}

/**
 * Les titres des mini-jeux que la suppression de $idsSupprimes rendrait
 * injouables. Tableau vide : la suppression ne casse rien.
 *
 * $banques est injectable pour que le controle soit testable sans Fast Games
 * installe ; sans argument, on lit les banques reelles.
 */
function jeuxRendusInjouables(PDO $dbh,array $idsSupprimes,?array $banques=null){
	if($banques===null){ $banques=banquesFastGames(); }
	$idsSupprimes=array_map('intval',$idsSupprimes);
	$titres=array();
	foreach($banques as $banque){
		if(empty($banque['competences'])||!is_array($banque['competences'])){ continue; }
		$viseeParLaSuppression=false;
		$survivante=false;
		foreach($banque['competences'] as $idBanque){
			$idBanque=(int)$idBanque;
			if(in_array($idBanque,$idsSupprimes,true)){ $viseeParLaSuppression=true; continue; }
			if(competenceEncoreJouable($dbh,$idBanque)){ $survivante=true; }
		}
		if($viseeParLaSuppression&&!$survivante){
			$titres[]=isset($banque['titre'])?(string)$banque['titre']:'(sans titre)';
		}
	}
	return $titres;
}

/**
 * La phrase ajoutee au message de reussite, ou '' si aucun jeu n'est touche.
 * Meme texte apres la suppression d'une ligne et celle d'une categorie : c'est
 * la meme consequence, elle n'a pas a se raconter de deux facons.
 */
function avertissementJeuxFastGames(array $titres){
	if(!$titres){ return ''; }
	$nbJeux=count($titres);
	return " ".$nbJeux." mini-jeu".($nbJeux>1?'x':'')." de Fast Games (".apercuTitresJeux($titres).") "
		.($nbJeux>1?'perdent leur dernière compétence et disparaissent':'perd sa dernière compétence et disparaît')
		." du catalogue.";
}

/** « Cuisine des fractions, Le compte est bon et 3 autres » - une liste de dix-huit titres ne se lit pas. */
function apercuTitresJeux(array $titres){
	$apercu=implode(', ',array_slice($titres,0,3));
	$reste=count($titres)-3;
	if($reste>0){ $apercu.=' et '.$reste.' autre'.($reste>1?'s':''); }
	return $apercu;
}
