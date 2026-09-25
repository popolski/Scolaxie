<?php
/**
 * Corrige UNE manche de la droite graduee.
 *
 * Pendant de api/carte.php a une dimension : la reponse n'est pas un index
 * choisi parmi des propositions, mais l'endroit ou l'eleve a pose son repere
 * sur la droite. Voir fgDroiteValider() dans includes/mecaniques.php.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/seance.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('ok' => false, 'erreur' => 'Méthode non autorisée'));
    exit;
}

$donnees = json_decode(file_get_contents('php://input'), true);
if (!is_array($donnees) || !hash_equals($_SESSION['fastgames_csrf'], (string)($donnees['csrf'] ?? ''))) {
    http_response_code(403);
    echo json_encode(array('ok' => false, 'erreur' => 'Session expirée'));
    exit;
}

$seance = fgSeanceOuverte((string)($donnees['seance'] ?? ''));
if ($seance === null) {
    http_response_code(409);
    echo json_encode(array('ok' => false, 'erreur' => 'Cette partie n’est plus ouverte. Relance le jeu.'));
    exit;
}

// La position arrive sous la forme la plus simple : un nombre, dans l'unite de
// la droite. Reste-t-il dans les bornes, tombe-t-il assez pres du nombre
// demande : verifie cote serveur dans fgDroiteValider(), jamais suppose ici.
if (!isset($donnees['valeur']) || !is_numeric($donnees['valeur'])) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'erreur' => 'Réponse inattendue'));
    exit;
}

$correction = fgEnregistrerPositionDroite(
    $seance,
    (int)($donnees['question'] ?? -1),
    (float)$donnees['valeur']
);
if ($correction === null) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'erreur' => 'Réponse inattendue'));
    exit;
}

$correction['ok'] = true;
echo json_encode($correction, JSON_UNESCAPED_UNICODE);
