<?php
/*
 * Entree de Fast Éval depuis le portail : ouvre la session a partir du
 * cookie commun, puis conduit l'utilisateur a la page demandee.
 *
 * L'ouverture de session elle-meme est passee dans utils/session-sso.php,
 * pour qu'un ecran atteint par lien direct depuis un autre site puisse
 * l'ouvrir lui-meme au lieu de renvoyer le visiteur sur l'ancien portail.
 */
session_start();
require_once __DIR__ . '/utils/session-sso.php';

$identity = ssoRequireIdentity();

if (!fastevalOuvrirSession($identity)) {
    header('Location: /portail/?erreur=liaison');
    exit;
}

/*
 * « vers » permet aux autres sites de viser une page precise sans court-
 * circuiter l'ouverture de session. La liste est fermee : sans elle, le
 * parametre serait une redirection ouverte, utilisable pour envoyer un
 * visiteur n'importe ou depuis une adresse du site.
 */
$pagesAutorisees = array(
    'presentation.php',
    'gestion-classe.php',
    'liste-competences.php',
    'liste-connaissances.php',
    'coupons.php',
    'bilan/index.php',
    'bulletin/index.php',
    'saisie/index.php',
);

$vers = isset($_GET['vers']) ? (string)$_GET['vers'] : '';
if (!in_array($vers, $pagesAutorisees, true)) {
    $vers = 'presentation.php';
}

// Un eleve n'a rien a faire sur les ecrans enseignants.
if (($_SESSION['role'] ?? '') !== 'enseignant' && $vers !== 'presentation.php') {
    $vers = 'presentation.php';
}

header('Location: ' . $vers);
exit;
