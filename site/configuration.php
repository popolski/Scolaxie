<?php
// Configuration fournie par le serveur local ; aucun secret n'est conservé dans le dépôt.
function scolaxieConfig(string $nom): string
{
    $valeur = getenv($nom);
    if (!is_string($valeur) || $valeur === '' || str_starts_with($valeur, 'REPLACE_')) {
        throw new RuntimeException("Variable de configuration manquante : $nom");
    }
    return $valeur;
}

function scolaxieClassesConnexion(): array
{
    $classes = json_decode(scolaxieConfig('SCOLAXIE_CLASS_OPTIONS_JSON'), true);
    if (!is_array($classes)) {
        throw new RuntimeException('Liste de classes invalide');
    }
    foreach ($classes as $classe) {
        if (!is_array($classe) || !isset($classe['id'], $classe['label'])
            || !is_int($classe['id']) || $classe['id'] <= 0
            || !is_string($classe['label']) || trim($classe['label']) === '') {
            throw new RuntimeException('Classe de connexion invalide');
        }
    }
    return $classes;
}

if (!defined('CV_SSO_SECRET')) {
    $secret = scolaxieConfig('SCOLAXIE_SSO_SECRET');
    if (strlen($secret) < 32) {
        throw new RuntimeException('Le secret SSO local doit contenir au moins 32 caractères');
    }
    define('CV_SSO_SECRET', $secret);
}
