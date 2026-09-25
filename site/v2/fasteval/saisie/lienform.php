<?php
session_start();

require('../utils/class/class_bdd.php');

bdd::connexion($_SESSION['bdd'])->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
$codeCarte = htmlspecialchars(trim($_POST['nom']));

$req = bdd::connexion($_SESSION['bdd'])->prepare(
    'SELECT id_eleve,nom,prenom FROM classe WHERE code_carte=:code_carte AND id_enseignant=:id_enseignant'
);
$req->bindParam(':code_carte', $codeCarte);
$req->bindParam(':id_enseignant', $_SESSION['id_enseignant']);
$req->execute();
$resultat = $req->fetch(PDO::FETCH_NUM);

if (!$resultat) {
    $_SESSION['message'] = "Désolé, cette carte n'est pas reconnue dans la classe !";
    $_SESSION['saisie'] = 'erreur';
    header('location:index.php');
} else {
    $_SESSION['saisie'] = '';
    $_SESSION['message'] = 'Bonjour '.$resultat[2].' '.$resultat[1].', sélectionne ton évaluation.';
    $_SESSION['id_eleve'] = $resultat[0];
    $_SESSION['lien'] = 'lienform1.php';
    // Ces clés dédiées évitent de confondre l'élève scanné avec le compte
    // connecté et permettent à tous les résumés d'afficher le prénom.
    $_SESSION['prenom_eleve'] = $resultat[2];
    $_SESSION['nom_eleve'] = $resultat[1];
    $_SESSION['nom'] = $resultat[1];
    header('location:index.php');
}
?>
