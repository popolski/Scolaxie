<?php
/**
 * Corrige UNE manche des paquets de dix.
 *
 * La reponse arrive DECOMPOSEE en dizaines et unites, jamais en un seul nombre :
 * c'est la numeration decimale qui est travaillee, pas le comptage. Voir
 * fgQuestionsCollection() dans includes/mecaniques.php.
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

// Le CE1 va jusqu'a mille : neuf dizaines et neuf unites suffisent largement
// aux collections de ce jeu, qui ne depassent pas quatre-vingts jetons.
$dizaines = isset($donnees['dizaines']) ? (int)$donnees['dizaines'] : -1;
$unites = isset($donnees['unites']) ? (int)$donnees['unites'] : -1;
if ($dizaines < 0 || $dizaines > 9 || $unites < 0 || $unites > 9) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'erreur' => 'Réponse inattendue'));
    exit;
}

$correction = fgEnregistrerCollection($seance, (int)($donnees['question'] ?? -1), $dizaines, $unites);
if ($correction === null) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'erreur' => 'Réponse inattendue'));
    exit;
}

$correction['ok'] = true;
echo json_encode($correction, JSON_UNESCAPED_UNICODE);
