<?php
/**
 * Corrige UNE manche de la balance.
 *
 * La reponse est la LISTE des masses posees sur le plateau, gardee telle quelle
 * pour que le score final la rejoue. Les masses etrangeres a la banque sont
 * ecartees cote serveur - voir fgEnregistrerBalance() dans includes/seance.php.
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

// Un plateau vide est une reponse recevable - fausse, mais recevable : c'est
// « je ne pose rien ». Au-dela de trente masses, ce n'est plus une pesee.
$posees = isset($donnees['posees']) && is_array($donnees['posees'])
    ? array_values($donnees['posees'])
    : null;
if ($posees === null || count($posees) > 30) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'erreur' => 'Réponse inattendue'));
    exit;
}

// PESER N'EST PAS REPONDRE. Le mode « peser » rend seulement le cote qui
// descend, sans enregistrer quoi que ce soit et sans jamais dire la masse :
// c'est ce que fait une vraie balance, et c'est ce qui permet au fleau de
// pencher honnetement cote client. Reserve au mode « equilibre » - voir
// fgPeserBalance() dans includes/seance.php.
if ((string)($donnees['action'] ?? '') === 'peser') {
    $pesee = fgPeserBalance($seance, (int)($donnees['question'] ?? -1), $posees);
    if ($pesee === null) {
        http_response_code(422);
        echo json_encode(array('ok' => false, 'erreur' => 'Pesée impossible'));
        exit;
    }
    $pesee['ok'] = true;
    echo json_encode($pesee, JSON_UNESCAPED_UNICODE);
    exit;
}

$correction = fgEnregistrerBalance($seance, (int)($donnees['question'] ?? -1), $posees);
if ($correction === null) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'erreur' => 'Réponse inattendue'));
    exit;
}

$correction['ok'] = true;
echo json_encode($correction, JSON_UNESCAPED_UNICODE);
