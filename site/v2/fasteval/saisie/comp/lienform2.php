<?php
session_start();

require('../../utils/class/class_bdd.php');

bdd::connexion($_SESSION['bdd'])->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
$nom = strtolower(trim(htmlspecialchars($_POST['nom'])));
$niveaux = ['nonatteint', 'patteint', 'atteint', 'depasse'];

if (in_array($nom, $niveaux, true)) {
    $req = bdd::connexion($_SESSION['bdd'])->prepare(
        'SELECT nb FROM comp_eleves WHERE id_eleve=:id_eleve and id_comp=:id_comp and niveau=:niveau AND id_enseignant=:id_enseignant'
    );
    $req->bindParam(':id_eleve', $_SESSION['id_eleve']);
    $req->bindParam(':id_comp', $_SESSION['id_comp']);
    $req->bindParam(':niveau', $nom);
    $req->bindParam(':id_enseignant', $_SESSION['id_enseignant']);
    $req->execute();
    $resultat = $req->fetch(PDO::FETCH_NUM);
    $req->closeCursor();

    if (!$resultat) {
        $req = bdd::connexion($_SESSION['bdd'])->prepare(
            'INSERT INTO comp_eleves(id_eleve,id_comp,niveau,date_acqui,date_enr,id_enseignant) VALUES(:id_eleve,:id_comp,:niveau,NOW(),NOW(),:id_enseignant)'
        );
        $req->bindParam(':id_eleve', $_SESSION['id_eleve']);
        $req->bindParam(':id_comp', $_SESSION['id_comp']);
        $req->bindParam(':niveau', $nom);
        $req->bindParam(':id_enseignant', $_SESSION['id_enseignant']);
        $req->execute();
        $req->closeCursor();
    } else {
        $nb = $resultat[0] + 1;
        $req = bdd::connexion($_SESSION['bdd'])->prepare(
            'UPDATE comp_eleves SET nb=:nb, date_enr=NOW() WHERE id_eleve=:id_eleve and id_comp=:id_comp and niveau=:niveau AND id_enseignant=:id_enseignant'
        );
        $req->bindParam(':id_eleve', $_SESSION['id_eleve']);
        $req->bindParam(':id_comp', $_SESSION['id_comp']);
        $req->bindParam(':niveau', $nom);
        $req->bindParam(':nb', $nb);
        $req->bindParam(':id_enseignant', $_SESSION['id_enseignant']);
        $req->execute();
        $req->closeCursor();
    }

    $libelles = [
        'nonatteint' => 'Non atteint',
        'patteint' => 'Partiellement atteint',
        'atteint' => 'Atteint',
        'depasse' => 'Dépassé',
    ];
    $_SESSION['confirmation_saisie'] = [
        'type' => $_SESSION['type_saisie'] ?? 'Compétence',
        'intitule' => $_SESSION['intitule_saisie'] ?? 'Compétence enregistrée',
        'resultat' => $libelles[$nom],
        'niveau' => $nom,
    ];
    $_SESSION['saisie'] = '';
    $_SESSION['lien'] = $_SESSION['role'] == 'eleve' ? 'lienform1.php' : 'lienform.php';
    header('location:../index.php');
    exit;
}

$_SESSION['message'] = 'Désolé '.$_SESSION['nom'].', ce résultat n’existe pas !';
$_SESSION['lien'] = 'comp/lienform2.php';
header('location:../index.php');
exit;
?>
