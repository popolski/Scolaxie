<?php
// Garde d'accès : un élève ne voit que les périodes ouvertes de SA classe.
// ---------------------------------------------------------------------------
// Pourquoi ce fichier existe. Jusqu'au 31/08/2026, la seule vérification faite
// par les pages était « est-ce que quelqu'un est connecté ». Un élève de CE1
// pouvait donc ouvrir n'importe quelle page de CE2, et n'importe quelle période
// que son enseignante n'avait pas encore ouverte, simplement en tapant
// l'adresse ou en gardant un ancien lien. Signalé par le responsable technique.
//
// Le plus gênant : les droits étaient DÉJÀ calculés à la connexion, dans
// sso.php, et rangés dans la session. Personne ne les relisait ensuite. Ce
// fichier ne fait donc qu'appliquer une règle qui existait déjà.
//
// La règle, telle que sso.php la pose :
//   - élève de CE1 : périodes 1 à son niveau, en CE1. Rien en CE2.
//   - élève de CE2 : les cinq périodes de CE1, plus 1 à son niveau en CE2.
//   - enseignante  : tout, y compris pour préparer une période à venir.
//
// Ce fichier doit être inclus AVANT toute sortie, puisqu'il peut rediriger.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Personne n'est connecté : chaque page s'en charge déjà avec sa propre
// redirection. On ne la double pas, sinon deux `header()` se disputeraient.
if (!isset($_SESSION['nom'])) {
    return;
}

if (isset($_SESSION['role']) && $_SESSION['role'] === 'enseignant') {
    return;
}

// La période et la classe se lisent dans le chemin de la page appelée :
// /v2/schoolmonsters/P3_CE1.php, ou /v2/schoolmonsters/P3_CE1/orthographe/xxx.php.
// Une page qui n'en porte pas - l'accueil, l'espace enseignant, une page
// d'outil - n'est pas concernée et passe sans contrôle. C'est volontaire :
// mieux vaut ne rien restreindre que restreindre au hasard.
$chemin = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
if (!preg_match('~/P([1-5])_CE([12])~i', $chemin, $trouve)) {
    return;
}

// sso.php pose '#' pour une période fermée, et l'adresse de la page sinon.
$cle = 'p' . (int) $trouve[1] . 'ce' . (int) $trouve[2];

if (!isset($_SESSION[$cle]) || $_SESSION[$cle] === '#') {
    header('Location: /v2/schoolmonsters/acceuil.php?acces=refuse');
    exit();
}
