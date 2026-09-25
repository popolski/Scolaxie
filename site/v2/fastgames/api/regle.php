<?php
/**
 * Corrige UNE manche de la regle graduee.
 *
 * La reponse est un entier en centimetres : la longueur lue, l'ecart entre deux
 * segments, ou l'estimation, selon le mode de la banque. L'alignement du zero
 * n'est PAS envoye et n'a pas a l'etre - voir fgQuestionsRegle() dans
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

// La plus longue regle du jeu fait 15 cm ; zero est une reponse recevable en
// mode « ecart », deux segments pouvant etre de meme longueur.
$cm = isset($donnees['cm']) ? (int)$donnees['cm'] : -1;
if ($cm < 0 || $cm > 30) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'erreur' => 'Réponse inattendue'));
    exit;
}

$correction = fgEnregistrerRegle($seance, (int)($donnees['question'] ?? -1), $cm);
if ($correction === null) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'erreur' => 'Réponse inattendue'));
    exit;
}

$correction['ok'] = true;
echo json_encode($correction, JSON_UNESCAPED_UNICODE);
