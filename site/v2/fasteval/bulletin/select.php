<?php

session_start();
include ('../utils/class/class_bdd.php');

if(empty($_POST['date_debut']) || empty($_POST['date_fin']) || $_POST['date_debut']>$_POST['date_fin']){
	header('location:index.php');
	exit();
}

$_SESSION['bilan']='ok';
$_SESSION['date_debut']=$_POST['date_debut'];
$_SESSION['date_fin']=$_POST['date_fin'];
$_SESSION['eleve']=$_POST['eleve'];
$idEleve=$_SESSION['eleve'];
$type=isset($_POST['type']) ? $_POST['type'] : '';
$choix=isset($_POST['choix']) ? $_POST['choix'] : '';
$modele=isset($_POST['modele']) ? $_POST['modele'] : 'actuel';

if (!in_array($type, array('comp', 'eval'), true)
    || !in_array($modele, array('actuel', 'charte'), true)
    || !in_array($choix, array(
        'Voir bilan des acquis avec enregistrement des observations',
        'Voir bilan des acquis sans enregistrement des observations',
    ), true)) {
    header('location:index.php');
    exit();
}

// Le modele est choisi une fois et conserve pendant le passage eventuel par
// l'ecran d'observations, pour les deux types de bulletin.
$_SESSION['modele_bulletin']=$modele;
$req=bdd::connexion($_SESSION['bdd'])->prepare('SELECT id_eleve,prenom,date_naiss FROM classe WHERE id_eleve=:id_eleve AND  id_enseignant=:id_enseignant');
$req->bindParam(':id_eleve',$idEleve);
$req->bindParam(':id_enseignant',$_SESSION['id_enseignant']);


$req->execute();
$info_eleve=$req->fetch(PDO::FETCH_NUM);
$prenom=ucfirst($info_eleve['1']);
$_SESSION['prenom']=$prenom;


if ($type=='comp' and $choix=='Voir bilan des acquis sans enregistrement des observations') {

	header('location:bilan_comp_proto.php');
	exit();
}
	
 if($choix=="Voir bilan des acquis avec enregistrement des observations" and $type=='comp'){

	header('location:observation_comp.php');
}
if($type=='eval' and $choix=='Voir bilan des acquis sans enregistrement des observations') {
	header('location:bilan_eval_proto.php');
	exit();
}
if($type=='eval' and $choix=='Voir bilan des acquis avec enregistrement des observations') {
	
	header('location:observations.php');
	exit();
}

?>
