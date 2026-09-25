<?php

session_start();

if (empty($_SESSION['permission']) || ($_SESSION['role'] ?? '') !== 'enseignant') {
    header('Location: ../presentation.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || empty($_POST['jeton'])
    || empty($_SESSION['jeton_journal'])
    || !hash_equals($_SESSION['jeton_journal'], (string)$_POST['jeton'])) {
    header('Location: journal.php?etat=invalide');
    exit;
}

$type = $_POST['type'] ?? '';
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, array('options'=>array('min_range'=>1)));
if (!in_array($type, array('eval', 'comp'), true) || $id === false || $id === null || empty($_SESSION['bdd']) || empty($_SESSION['id_enseignant'])) {
    header('Location: journal.php?etat=invalide');
    exit;
}

require_once __DIR__.'/../utils/class/class_bdd.php';
$dbh = bdd::connexion($_SESSION['bdd']);
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);

// L'identifiant seul ne suffit pas : le filtre enseignant empêche la
// suppression d'une saisie appartenant a un autre compte.
$table = ($type === 'comp') ? 'comp_eleves' : 'eval_eleves';
$requete = $dbh->prepare('DELETE FROM '.$table.' WHERE id_test = :id AND id_enseignant = :id_enseignant');
$requete->bindValue(':id', (int)$id, PDO::PARAM_INT);
$requete->bindValue(':id_enseignant', (int)$_SESSION['id_enseignant'], PDO::PARAM_INT);
$requete->execute();
$supprime = ($requete->rowCount() === 1);
$requete->closeCursor();
$dbh = null;

header('Location: journal.php?etat='.($supprime ? 'supprime' : 'invalide'));
exit;
