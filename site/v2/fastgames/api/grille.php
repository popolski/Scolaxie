<?php
/**
 * Corrige UNE manche du robot sur la grille.
 *
 * Pendant de api/droite.php et api/carte.php pour une reponse qui n'est ni un
 * index ni un point, mais une SUITE D'ORDRES. Le serveur la rejoue lui-meme -
 * le client anime le robot, il ne juge rien. Voir fgGrilleValider() dans
 * includes/mecaniques.php.
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

// Le programme arrive sous sa forme la plus simple : une liste de mots. Le tri
// des ordres reconnus se fait dans fgEnregistrerProgrammeGrille(), avec le
// reste de la regle du jeu, plutot qu'ici - un seul endroit qui decide ce
// qu'est un ordre valable.
if (!isset($donnees['programme']) || !is_array($donnees['programme'])) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'erreur' => 'Réponse inattendue'));
    exit;
}

$correction = fgEnregistrerProgrammeGrille(
    $seance,
    (int)($donnees['question'] ?? -1),
    $donnees['programme']
);
if ($correction === null) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'erreur' => 'Réponse inattendue'));
    exit;
}

$correction['ok'] = true;
echo json_encode($correction, JSON_UNESCAPED_UNICODE);
