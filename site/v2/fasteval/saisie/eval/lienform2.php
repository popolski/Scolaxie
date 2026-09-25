<?php
session_start();

require('../../utils/class/class_bdd.php');

bdd::connexion($_SESSION['bdd'])->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
$valeur = htmlspecialchars($_POST['nom']);
$valeursPossibles = [
    'note0.0', 'note0.5', 'note1.0', 'note1.5', 'note2.0', 'note2.5',
    'note3.0', 'note3.5', 'note4.0', 'note4.5', 'note5.0', 'note5.5',
    'note6.0', 'note6.5', 'note7.0', 'note7.5', 'note8.0', 'note8.5',
    'note9.0', 'note9.5', 'note10',
];

if (in_array($valeur, $valeursPossibles, true)) {
    $note = substr($valeur, 4);
    $req = bdd::connexion($_SESSION['bdd'])->prepare(
        'INSERT INTO eval_eleves(id_eleve,id_eval,niveau,date_acqui,id_enseignant) VALUES(:id_eleve,:id_eval,:niveau,NOW(),:id_enseignant)'
    );
    $req->bindParam(':id_eleve', $_SESSION['id_eleve']);
    $req->bindParam(':id_eval', $_SESSION['id_eval']);
    $req->bindParam(':niveau', $note);
    $req->bindParam(':id_enseignant', $_SESSION['id_enseignant']);
    $req->execute();
    $req->closeCursor();

    // Meme seuils que noteColor() dans pdf_bilan_eval_proto.php : <5 non
    // atteint, <8 partiellement atteint, sinon atteint. Pas de palier depasse
    // pour une note chiffree, contrairement au niveau de competence.
    $noteValeur = (float)$note;
    if ($noteValeur < 5) {
        $niveauNote = 'nonatteint';
    } elseif ($noteValeur < 8) {
        $niveauNote = 'patteint';
    } else {
        $niveauNote = 'atteint';
    }

    $_SESSION['confirmation_saisie'] = [
        'type' => $_SESSION['type_saisie'] ?? 'Connaissance',
        'intitule' => $_SESSION['intitule_saisie'] ?? 'Connaissance enregistrée',
        'resultat' => str_replace('.', ',', $note).' / 10',
        'niveau' => $niveauNote,
    ];
    $_SESSION['saisie'] = '';
    $_SESSION['lien'] = $_SESSION['role'] == 'eleve' ? 'lienform1.php' : 'lienform.php';
    header('location:../index.php');
    exit;
}

$_SESSION['message'] = 'Désolé '.$_SESSION['nom'].', ce résultat n’existe pas !';
$_SESSION['lien'] = 'eval/lienform2.php';
header('location:../index.php');
exit;
?>
