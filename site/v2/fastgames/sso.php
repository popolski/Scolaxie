<?php

session_start();
require_once __DIR__ . '/config.php';
require_once dirname(__DIR__) . '/fasteval/utils/session-sso.php';

$identity = ssoRequireIdentity();
if (!fastgamesIdentiteAutorisee($identity)) {
    header('Location: /portail/?fastgames=indisponible');
    exit;
}
if (!fastevalOuvrirSession($identity)) {
    header('Location: /portail/?erreur=liaison-fastgames');
    exit;
}

header('Location: ' . (($_SESSION['role'] ?? '') === 'enseignant' ? 'studio.php' : 'index.php'));
exit;
