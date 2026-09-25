<?php
/**
 * Corrige UNE manche de l'horloge.
 *
 * Pendant de api/boutique.php pour cette mecanique : la reponse n'est pas un
 * index choisi parmi des propositions, mais la position des deux aiguilles que
 * l'eleve a posees. Voir includes/horloge.php pour ce que « correct » verifie
 * exactement.
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

// Les aiguilles arrivent sous la forme la plus simple : deux entiers. Le reste
// - sont-ils dans les bornes, correspondent-ils a l'heure demandee - est
// verifie cote serveur dans fgHorlogeValider(), jamais suppose ici.
if (!isset($donnees['heures']) || !isset($donnees['minutes'])
        || !is_numeric($donnees['heures']) || !is_numeric($donnees['minutes'])) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'erreur' => 'Réponse inattendue'));
    exit;
}

$correction = fgEnregistrerHeureHorloge(
    $seance,
    (int)($donnees['question'] ?? -1),
    (int)$donnees['heures'],
    (int)$donnees['minutes']
);
if ($correction === null) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'erreur' => 'Réponse inattendue'));
    exit;
}

$correction['ok'] = true;
echo json_encode($correction, JSON_UNESCAPED_UNICODE);
