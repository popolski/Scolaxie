<?php
/**
 * Interrupteur de publication de Fast Games.
 *
 * - off     : aucune carte sur le portail, tout accès direct revient au portail ;
 * - preview : uniquement le compte administrateur de la classe CE1 ;
 * - teachers : tous les comptes enseignants reconnus par le portail ;
 * - all     : tous les comptes enseignants et élèves reconnus par le portail.
 *
 * Le retour arrière immédiat consiste à remettre cette valeur à « off ».
 */
require_once dirname(__DIR__, 2) . '/configuration.php';
$modeFastGames = scolaxieConfig('SCOLAXIE_FASTGAMES_MODE');
if (!in_array($modeFastGames, array('off', 'preview', 'teachers', 'all'), true)) {
    throw new RuntimeException('Mode Fast Games invalide');
}
define('FASTGAMES_MODE', $modeFastGames);

function fastgamesCompteApercu(): int
{
    $id = (int)scolaxieConfig('SCOLAXIE_PREVIEW_TEACHER_ID');
    if ($id <= 0) {
        throw new RuntimeException('Compte de prévisualisation invalide');
    }
    return $id;
}

/**
 * Décide si le jeton signé du portail donne accès à Fast Games.
 */
function fastgamesIdentiteAutorisee(?array $identity): bool
{
    if (!$identity || FASTGAMES_MODE === 'off') {
        return false;
    }

    if (FASTGAMES_MODE === 'all') {
        return in_array($identity['role'] ?? '', array('teacher', 'student'), true);
    }

    if (FASTGAMES_MODE === 'teachers') {
        return ($identity['role'] ?? '') === 'teacher';
    }

    return FASTGAMES_MODE === 'preview'
        && ($identity['role'] ?? '') === 'teacher'
        && (int)($identity['tid'] ?? 0) === fastgamesCompteApercu();
}

/**
 * Même décision une fois l'identité traduite dans la session Fast Éval.
 */
function fastgamesSessionAutorisee(array $session): bool
{
    if (FASTGAMES_MODE === 'off' || empty($session['permission'])) {
        return false;
    }

    if (FASTGAMES_MODE === 'all') {
        return in_array($session['role'] ?? '', array('enseignant', 'eleve'), true);
    }

    if (FASTGAMES_MODE === 'teachers') {
        return ($session['role'] ?? '') === 'enseignant';
    }

    return FASTGAMES_MODE === 'preview'
        && ($session['role'] ?? '') === 'enseignant'
        && (int)($session['id_enseignant'] ?? 0) === fastgamesCompteApercu();
}
