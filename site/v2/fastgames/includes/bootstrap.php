<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__, 2) . '/fasteval/utils/session-sso.php';
require_once dirname(__DIR__, 2) . '/galaxie-role.php';
require_once __DIR__ . '/helpers.php';

if (empty($_SESSION['permission'])) {
    $fgIdentity = ssoReadToken();
    if (!$fgIdentity) {
        header('Location: /portail/?session=expiree');
        exit;
    }
    if (!fastgamesIdentiteAutorisee($fgIdentity)) {
        header('Location: /portail/?fastgames=indisponible');
        exit;
    }
    if (!fastevalOuvrirSession($fgIdentity)) {
        header('Location: /portail/?erreur=liaison-fastgames');
        exit;
    }
}

if (!fastgamesSessionAutorisee($_SESSION)) {
    header('Location: /portail/?fastgames=indisponible');
    exit;
}

if (empty($_SESSION['fastgames_csrf'])) {
    $_SESSION['fastgames_csrf'] = bin2hex(random_bytes(24));
}

// Le choix de niveau de l'enseignante est note ICI, pour toutes les pages a la
// fois : le catalogue le propose, mais c'est jeu.php qui verifie ensuite qu'un
// jeu est disponible. Le lire a un seul endroit evite qu'une page l'applique et
// une autre pas.
require_once __DIR__ . '/referentiel.php';
fgNoterNiveauDemande();

$fgApercuEleve = fgApercuEleve();
