<?php
session_start();

require('../utils/class/class_bdd.php');

bdd::connexion($_SESSION['bdd'])->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
$nom = htmlspecialchars($_POST['nom']);

$req = bdd::connexion($_SESSION['bdd'])->prepare('SELECT id_comp,designation,commentaire FROM comp_type WHERE designation =:nom ');
$req->bindParam(':nom', $nom);
$req->execute();
$resultat = $req->fetch(PDO::FETCH_NUM);

$req = bdd::connexion($_SESSION['bdd'])->prepare('SELECT id_eval,designation,commentaire,niveau FROM eval_type WHERE designation =:nom ');
$req->bindParam(':nom', $nom);
$req->execute();
$resultat1 = $req->fetch(PDO::FETCH_NUM);

if ((!$resultat) && (!$resultat1)) {
    $_SESSION['message'] = "Désolé ".$_SESSION['nom'].", cette compétence ou connaissance n'existe pas !";
    header('location:index.php');
    exit;
}

if (!$resultat1) {
    $_SESSION['message'] = 'Indiquez le positionnement pour la compétence :';
    $_SESSION['detail'] = $resultat[2];
    $_SESSION['id_comp'] = $resultat[0];
    $_SESSION['type_saisie'] = 'Compétence';
    $_SESSION['intitule_saisie'] = $resultat[2];
    $_SESSION['lien'] = 'comp/lienform2.php';
    header('location:index.php');
    exit;
}

$_SESSION['message'] = "Indiquez le résultat de l'évaluation :";
$_SESSION['detail'] = $resultat1[2].'.';
$_SESSION['id_eval'] = $resultat1[0];
$_SESSION['type_saisie'] = 'Connaissance';
$_SESSION['intitule_saisie'] = $resultat1[2];
$_SESSION['lien'] = 'eval/lienform2.php';
header('location:index.php');
exit;
?>
