<?php

// Source de donnees commune a la page HTML et a son export PDF. Garder les
// deux rendus branches sur les memes requetes evite qu'un journal affiche une
// saisie que l'autre oublierait.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['permission']) || !in_array($_SESSION['role'] ?? '', array('enseignant', 'eleve'), true)) {
    header('Location: ../index.php');
    exit;
}

if (empty($_SESSION['bdd']) || empty($_SESSION['id_enseignant'])) {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__.'/../../utils/class/class_bdd.php';

date_default_timezone_set('Europe/Paris');

$estEleve = (($_SESSION['role'] ?? '') === 'eleve');
if ($estEleve && empty($_SESSION['id_eleve'])) {
    header('Location: ../index.php');
    exit;
}

$dbh = bdd::connexion($_SESSION['bdd']);
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);

$filtreEleveEval = $estEleve ? ' AND ee.id_eleve = :id_eleve' : '';
$filtreEleveComp = $estEleve ? ' AND ce.id_eleve = :id_eleve' : '';

$sqlEval = 'SELECT c.nom, c.prenom, et.commentaire AS intitule,
                   ee.niveau AS resultat, ee.id_test
            FROM eval_eleves ee
            INNER JOIN classe c ON c.id_eleve = ee.id_eleve
            INNER JOIN eval_type et ON et.id_eval = ee.id_eval
            WHERE ee.date_acqui = DATE(NOW())
              AND ee.id_enseignant = :id_enseignant'.$filtreEleveEval.'
            ORDER BY c.nom ASC, c.prenom ASC, ee.id_test ASC';

$sqlComp = 'SELECT c.nom, c.prenom, ct.commentaire AS intitule,
                   ce.niveau AS resultat, ce.id_test
            FROM comp_eleves ce
            INNER JOIN classe c ON c.id_eleve = ce.id_eleve
            INNER JOIN comp_type ct ON ct.id_comp = ce.id_comp
            WHERE ce.date_enr = DATE(NOW())
              AND ce.id_enseignant = :id_enseignant'.$filtreEleveComp.'
            ORDER BY c.nom ASC, c.prenom ASC, ce.id_test ASC';

function chargerJournalDuJour($dbh, $sql, $estEleve)
{
    $requete = $dbh->prepare($sql);
    $requete->bindValue(':id_enseignant', (int)$_SESSION['id_enseignant'], PDO::PARAM_INT);
    if ($estEleve) {
        $requete->bindValue(':id_eleve', (int)$_SESSION['id_eleve'], PDO::PARAM_INT);
    }
    $requete->execute();
    $lignes = $requete->fetchAll(PDO::FETCH_ASSOC);
    $requete->closeCursor();
    return $lignes;
}

$journalEval = chargerJournalDuJour($dbh, $sqlEval, $estEleve);
$journalComp = chargerJournalDuJour($dbh, $sqlComp, $estEleve);
$dbh = null;

