<?php
session_start();
require_once __DIR__ . '/sso-lib.php';
require_once dirname(__DIR__) . '/fasteval/utils/class/class_bdd.php';
$identity=ssoRequireIdentity();
$db=bdd::connexion(scolaxieConfig('SCOLAXIE_DB_NAME'));

if($identity['role']==='teacher'){
    $stmt=$db->prepare('SELECT ad.id_enseignant,e.classe1,e.nom,e.prenom,ad.identifiant,ad.est_admin FROM ayant_droit ad LEFT JOIN enseignant e ON e.id_enseignant=ad.id_enseignant WHERE ad.id_enseignant=:tid LIMIT 2');
    $stmt->execute(array(':tid'=>(int)$identity['tid'])); $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    if(count($rows)!==1){header('Location: /portail/?erreur=liaison-school');exit;}
    $row=$rows[0]; session_regenerate_id(true); $_SESSION['role']='enseignant';
    $_SESSION['id_enseignant']=(int)$row['id_enseignant']; $_SESSION['classe_enseignant']=strtolower($row['classe1']);
    $_SESSION['identifiant_enseignant']=$row['identifiant']; $_SESSION['est_admin']=(bool)$row['est_admin']; $_SESSION['justification_enseignant']='';
    // Sans 'nom', chaque fiche renvoie a l'accueil : c'est cette cle, et
    // elle seule, que garde tout le site. La poser rend les lecons a
    // l'enseignante, et fait reapparaitre son outil de reglage des pages.
    // La jointure est un LEFT JOIN : on retombe sur l'identifiant si la
    // fiche enseignant manque, plutot que d'ecrire un nom vide.
    $_SESSION['nom']=(isset($row['nom'])&&trim($row['nom'])!=='')?$row['nom']:$row['identifiant'];
    $_SESSION['prenom']=(isset($row['prenom'])&&trim($row['prenom'])!=='')?$row['prenom']:'';
    // L'enseignante arrive sur son palier, pas dans les periodes :
    // choisir ce qu'on vient faire et choisir une periode sont deux
    // decisions differentes.
    header('Location: enseignant.php');exit;
} else {
    // ------------------------------------------------------------------
    // Le profil local d'un eleve, retrouve ou cree a partir de Fast Eval.
    //
    // Fast Eval est la source de verite : c'est la que les eleves sont
    // inscrits. School Monsters ne garde de son cote que ce qui lui est
    // propre (le niveau, qui commande les periodes accessibles, et le code
    // de connexion local historique).
    //
    // AVANT : quand aucun profil local n'existait, le code retombait sur un
    // rattrapage ecrit en dur pour les deux comptes de demonstration, avec
    // les noms nom d’exemple et LEGUILLET dans la requete. Tout eleve reel
    // absent de droitsite arrivait donc sur une page d'erreur. Ce rattrapage
    // est supprime et remplace par une vraie creation.
    // ------------------------------------------------------------------
    $sid=(int)$identity['sid'];
    $tid=(int)$identity['tid'];

    $lire=$db->prepare('SELECT Id,nom,prenom,sexe,classe,niveau,id_eleve_fasteval,id_enseignant_fasteval FROM droitsite WHERE id_eleve_fasteval=:sid LIMIT 2');
    $lire->execute(array(':sid'=>$sid));
    $rows=$lire->fetchAll(PDO::FETCH_ASSOC);

    if(count($rows)===0){
        // Premiere venue : on lit l'eleve dans Fast Eval et on cree son
        // profil. La classe vient de l'enseignante (colonne classe1), et non
        // d'une saisie separee : deux sources pour la meme information, c'est
        // deux occasions de diverger.
        $source=$db->prepare(
            'SELECT c.nom, c.prenom, LOWER(e.classe1) AS classe
               FROM classe c
               JOIN enseignant e ON e.id_enseignant=c.id_enseignant
              WHERE c.id_eleve=:sid AND c.id_enseignant=:tid AND c.actif=1
              LIMIT 1'
        );
        $source->execute(array(':sid'=>$sid,':tid'=>$tid));
        $eleve=$source->fetch(PDO::FETCH_ASSOC);

        if($eleve){
            try{
                // Le code local est aleatoire et n'est communique a personne :
                // l'entree se fait par le portail. Il n'est la que parce que
                // la colonne l'exige et que la connexion locale historique
                // existe encore.
                $codeLocal=substr(bin2hex(random_bytes(8)),0,16);
                $creer=$db->prepare(
                    'INSERT INTO droitsite (nom,prenom,sexe,code,classe,niveau,id_eleve_fasteval,id_enseignant_fasteval)
                     VALUES (:nom,:prenom,0,:code,:classe,1,:sid,:tid)'
                );
                $creer->execute(array(
                    ':nom'=>$eleve['nom'], ':prenom'=>$eleve['prenom'],
                    ':code'=>$codeLocal, ':classe'=>$eleve['classe'],
                    ':sid'=>$sid, ':tid'=>$tid
                ));
            }catch(PDOException $e){
                // Deux onglets ouverts en meme temps : la cle unique sur
                // id_eleve_fasteval tranche, la relecture ci-dessous donne
                // le gagnant.
            }
            $lire->execute(array(':sid'=>$sid));
            $rows=$lire->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    if(count($rows)!==1){
        header('Location: /portail/?erreur=liaison-school');
        exit;
    }
    $row=$rows[0];

    // Changement de classe en cours d'annee ou a la rentree : Fast Eval fait
    // autorite. On suit l'enseignante indiquee par le jeton, et on remet la
    // classe a celle de cette enseignante. Le niveau, lui, appartient a
    // School Monsters : on n'y touche pas.
    if((int)$row['id_enseignant_fasteval']!==$tid){
        $nouvelleClasse=$db->prepare('SELECT LOWER(classe1) AS classe FROM enseignant WHERE id_enseignant=:tid LIMIT 1');
        $nouvelleClasse->execute(array(':tid'=>$tid));
        $classeCible=$nouvelleClasse->fetchColumn();
        $maj=$db->prepare('UPDATE droitsite SET id_enseignant_fasteval=:tid'.($classeCible?', classe=:classe':'').' WHERE Id=:id');
        $params=array(':tid'=>$tid, ':id'=>(int)$row['Id']);
        if($classeCible){ $params[':classe']=$classeCible; }
        $maj->execute($params);
        $row['id_enseignant_fasteval']=$tid;
        if($classeCible){ $row['classe']=$classeCible; }
    }
}
session_regenerate_id(true); $_SESSION['role']='eleve'; $_SESSION['nom']=$row['nom']; $_SESSION['classe']=strtolower($row['classe']);
$_SESSION['sexe']=$row['sexe']; $_SESSION['prenom']=$row['prenom']; $_SESSION['niveau']=(int)$row['niveau'];
$_SESSION['id_eleve']=(int)$row['id_eleve_fasteval']; $_SESSION['id_enseignant']=(int)$row['id_enseignant_fasteval']; $_SESSION['auth_source']='sso';
for($i=1;$i<=5;$i++){
    $_SESSION['p'.$i.'ce1']='#'; $_SESSION['p'.$i.'ce2']='#';
    $_SESSION['couleur'.$i]='sombre'; $_SESSION['couleur'.($i+5)]='sombre';
}
$niveau=max(1,min(5,(int)$row['niveau']));
if($_SESSION['classe']==='ce1'){
    for($i=1;$i<=$niveau;$i++){$_SESSION['p'.$i.'ce1']='P'.$i.'_CE1.php';$_SESSION['couleur'.$i]='clair';}
}elseif($_SESSION['classe']==='ce2'){
    for($i=1;$i<=5;$i++){$_SESSION['p'.$i.'ce1']='P'.$i.'_CE1.php';$_SESSION['couleur'.$i]='clair';}
    for($i=1;$i<=$niveau;$i++){$_SESSION['p'.$i.'ce2']='P'.$i.'_CE2.php';$_SESSION['couleur'.($i+5)]='clair';}
}else{header('Location: /portail/?erreur=liaison');exit;}
header('Location: acceuil.php');exit;
