<?php
/**
 * Corrige UNE manche du partage (fractions).
 *
 * La reponse n'est ni un index ni un point : c'est un dessin, resume par deux
 * entiers - en combien de parts la bande ou le disque a ete coupe, et combien
 * en ont ete coloriees. Voir fgPartageValider() dans includes/mecaniques.php.
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

// Bornes larges mais reelles : on ne coupe pas en zero part, et au-dela de
// vingt on ne partage plus, on hachure. Le nombre de parts coloriees ne peut
// pas depasser le nombre de parts.
$parts = isset($donnees['parts']) ? (int)$donnees['parts'] : 0;
$coloriees = isset($donnees['coloriees']) ? (int)$donnees['coloriees'] : -1;
if ($parts < 1 || $parts > 20 || $coloriees < 0 || $coloriees > $parts) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'erreur' => 'Réponse inattendue'));
    exit;
}

$correction = fgEnregistrerPartage($seance, (int)($donnees['question'] ?? -1), $parts, $coloriees);
if ($correction === null) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'erreur' => 'Réponse inattendue'));
    exit;
}

$correction['ok'] = true;
echo json_encode($correction, JSON_UNESCAPED_UNICODE);
