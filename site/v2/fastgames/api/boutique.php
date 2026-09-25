<?php
/**
 * Corrige UNE manche de la petite boutique.
 *
 * Pendant de api/compte.php pour cette mecanique : la reponse n'est pas un
 * index choisi parmi des propositions, mais l'ensemble des pieces que l'eleve
 * a posees sur le comptoir. Voir includes/boutique.php pour ce que
 * « correct » verifie exactement.
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

// Les pieces arrivent sous la forme la plus simple : une liste de valeurs en
// centimes. Tout le reste - ces pieces sont-elles vraiment dans ce
// porte-monnaie, une seule fois chacune, la somme tombe-t-elle juste, la
// limite est-elle respectee - est verifie cote serveur dans
// fgBoutiqueValider(), jamais suppose ici.
$piecesRecues = $donnees['pieces'] ?? null;
if (!is_array($piecesRecues)) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'erreur' => 'Réponse inattendue'));
    exit;
}
$pieces = array();
foreach ($piecesRecues as $piece) {
    if (!is_numeric($piece)) {
        http_response_code(422);
        echo json_encode(array('ok' => false, 'erreur' => 'Réponse inattendue'));
        exit;
    }
    $pieces[] = (int)$piece;
}

$correction = fgEnregistrerPiecesBoutique($seance, (int)($donnees['question'] ?? -1), $pieces);
if ($correction === null) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'erreur' => 'Réponse inattendue'));
    exit;
}

$correction['ok'] = true;
echo json_encode($correction, JSON_UNESCAPED_UNICODE);
