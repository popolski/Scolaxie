<?php
/**
 * Corrige UNE manche du graphique.
 *
 * La reponse n'est ni un index ni un point : c'est une hauteur par barre, dans
 * l'ordre des series de l'enquete. Voir fgGraphiqueValider() dans
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

// Bornes larges mais reelles : une barre ne descend pas sous zero, et l'axe le
// plus haut du jeu monte a 20. Une enquete ne compte jamais plus de huit
// series - au-dela le diagramme ne se lit plus d'un coup d'oeil.
$hauteurs = isset($donnees['hauteurs']) && is_array($donnees['hauteurs'])
    ? array_map('intval', array_values($donnees['hauteurs']))
    : array();
if (!$hauteurs || count($hauteurs) > 8) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'erreur' => 'Réponse inattendue'));
    exit;
}
foreach ($hauteurs as $h) {
    if ($h < 0 || $h > 20) {
        http_response_code(422);
        echo json_encode(array('ok' => false, 'erreur' => 'Réponse inattendue'));
        exit;
    }
}

$correction = fgEnregistrerGraphique($seance, (int)($donnees['question'] ?? -1), $hauteurs);
if ($correction === null) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'erreur' => 'Réponse inattendue'));
    exit;
}

$correction['ok'] = true;
echo json_encode($correction, JSON_UNESCAPED_UNICODE);
