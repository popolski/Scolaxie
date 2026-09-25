<?php
/**
 * Corrige UNE manche du compte est bon.
 *
 * Pendant de api/reponse.php pour cette seule mecanique : la reponse n'est pas
 * un index choisi parmi des propositions, mais une expression composee par
 * l'eleve (une suite de nombres et d'operateurs). Voir
 * includes/compte-est-bon.php pour ce que « correct » verifie exactement.
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

// Les etapes arrivent du navigateur sous la forme la plus simple possible :
// un tableau ordonne de { nombre, operateur }. Tout le reste - est-ce que ces
// nombres existent vraiment dans cette manche, sont-ils pris une seule fois,
// le calcul reste-t-il positif, tombe-t-il juste - est verifie cote serveur
// dans fgCompteEstBonValider(), jamais suppose ici.
$etapesRecues = $donnees['etapes'] ?? null;
if (!is_array($etapesRecues)) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'erreur' => 'Réponse inattendue'));
    exit;
}
$etapes = array();
foreach ($etapesRecues as $etape) {
    if (!is_array($etape) || !isset($etape['nombre']) || !is_numeric($etape['nombre'])) {
        http_response_code(422);
        echo json_encode(array('ok' => false, 'erreur' => 'Réponse inattendue'));
        exit;
    }
    $operateur = $etape['operateur'] ?? null;
    $etapes[] = array(
        'nombre' => (int)$etape['nombre'],
        'operateur' => in_array($operateur, array('+', '-', '×', '÷'), true) ? $operateur : null,
    );
}

$correction = fgEnregistrerEtapesCompte($seance, (int)($donnees['question'] ?? -1), $etapes);
if ($correction === null) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'erreur' => 'Réponse inattendue'));
    exit;
}

$correction['ok'] = true;
echo json_encode($correction, JSON_UNESCAPED_UNICODE);
