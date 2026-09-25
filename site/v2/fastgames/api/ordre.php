<?php
/**
 * Corrige UNE manche de tri alphabetique.
 *
 * Pendant de api/compte.php et api/boutique.php pour cette mecanique : la
 * reponse n'est pas un index choisi parmi des propositions, mais les cinq
 * mots dans l'ordre ou l'eleve les a ranges. Voir includes/seance.php,
 * fgEnregistrerOrdreMots(), pour ce que « juste » verifie exactement.
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

// L'ordre arrive sous la forme la plus simple : une liste de mots, dans
// l'ordre ou l'eleve les a poses. Tout le reste - ces mots sont-ils bien ceux
// de cette manche, dans le bon ordre - est verifie cote serveur dans
// fgEnregistrerOrdreMots(), jamais suppose ici.
$motsRecus = $donnees['ordre'] ?? null;
if (!is_array($motsRecus)) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'erreur' => 'Réponse inattendue'));
    exit;
}
$ordre = array();
foreach ($motsRecus as $mot) {
    if (!is_string($mot) && !is_numeric($mot)) {
        http_response_code(422);
        echo json_encode(array('ok' => false, 'erreur' => 'Réponse inattendue'));
        exit;
    }
    $ordre[] = (string)$mot;
}

$correction = fgEnregistrerOrdreMots($seance, (int)($donnees['question'] ?? -1), $ordre);
if ($correction === null) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'erreur' => 'Réponse inattendue'));
    exit;
}

$correction['ok'] = true;
echo json_encode($correction, JSON_UNESCAPED_UNICODE);
