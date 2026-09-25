<?php
/**
 * Corrige UNE manche du son qui manque.
 *
 * La reponse est une chaine COMPOSEE a partir des graphies proposees - jamais
 * une saisie libre. Le serveur la compare a la graphie attendue, qu'il est le
 * seul a connaitre. Voir fgGraphieValider() dans includes/mecaniques.php.
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

// Une graphie francaise ne fait pas douze lettres : au-dela, ce n'est plus une
// reponse composee au clavier du jeu.
if (!isset($donnees['composee']) || !is_string($donnees['composee']) || mb_strlen($donnees['composee']) > 12) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'erreur' => 'Réponse inattendue'));
    exit;
}

$correction = fgEnregistrerGraphie($seance, (int)($donnees['question'] ?? -1), (string)$donnees['composee']);
if ($correction === null) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'erreur' => 'Réponse inattendue'));
    exit;
}

$correction['ok'] = true;
echo json_encode($correction, JSON_UNESCAPED_UNICODE);
