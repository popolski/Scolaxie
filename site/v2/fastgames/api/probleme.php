<?php
/**
 * Corrige UNE manche du probleme du jour.
 *
 * La reponse est un CALCUL CONSTRUIT, sous la meme forme d'etapes que le compte
 * est bon - c'est le meme geste, et le client en partage le rendu multi-lignes.
 * Le serveur rejoue le calcul lui-meme : le client ne juge rien, et ne connait
 * meme pas la reponse attendue. Voir fgProblemeValider() dans
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

// Une manche de cycle 2 ne demande jamais douze operations : au-dela, ce n'est
// plus un raisonnement mais un envoi construit a la main.
if (!isset($donnees['etapes']) || !is_array($donnees['etapes']) || count($donnees['etapes']) > 12) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'erreur' => 'Réponse inattendue'));
    exit;
}

// Mise en forme stricte avant d'entrer en session : chaque etape se reduit a un
// operateur connu et un entier. Le controle du fond - le nombre appartient-il a
// l'enonce - reste dans fgProblemeValider(), avec le reste de la regle du jeu.
$etapes = array();
foreach ($donnees['etapes'] as $etape) {
    if (!is_array($etape) || !isset($etape['n']) || !is_numeric($etape['n'])) {
        http_response_code(422);
        echo json_encode(array('ok' => false, 'erreur' => 'Réponse inattendue'));
        exit;
    }
    $op = isset($etape['op']) ? (string)$etape['op'] : '';
    $etapes[] = array(
        'op' => in_array($op, array('+', '-', '*'), true) ? $op : '',
        'n' => (int)$etape['n'],
    );
}

$correction = fgEnregistrerCalculProbleme($seance, (int)($donnees['question'] ?? -1), $etapes);
if ($correction === null) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'erreur' => 'Réponse inattendue'));
    exit;
}

$correction['ok'] = true;
echo json_encode($correction, JSON_UNESCAPED_UNICODE);
