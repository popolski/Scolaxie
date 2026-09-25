<?php
/**
 * Corrige UNE manche de la carte.
 *
 * Pendant de api/horloge.php pour cette mecanique : la reponse n'est pas un
 * index choisi parmi des propositions, mais le point que l'eleve a pose sur
 * le planisphere. Voir fgCarteValider() dans includes/mecaniques.php pour ce
 * que « correct » verifie exactement.
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

// Le point arrive sous la forme la plus simple : deux nombres en degres. Le
// reste - est-il sur le planisphere, tombe-t-il assez pres du lieu demande -
// est verifie cote serveur dans fgCarteValider(), jamais suppose ici.
if (!isset($donnees['lat']) || !isset($donnees['lon'])
        || !is_numeric($donnees['lat']) || !is_numeric($donnees['lon'])) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'erreur' => 'Réponse inattendue'));
    exit;
}

$correction = fgEnregistrerPositionCarte(
    $seance,
    (int)($donnees['question'] ?? -1),
    (float)$donnees['lat'],
    (float)$donnees['lon']
);
if ($correction === null) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'erreur' => 'Réponse inattendue'));
    exit;
}

$correction['ok'] = true;
echo json_encode($correction, JSON_UNESCAPED_UNICODE);
