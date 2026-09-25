<?php
/**
 * Corrige UNE manche du loto des sons.
 *
 * Pendant de api/ordre.php pour cette mecanique : la reponse n'est pas un
 * index ni un tableau, mais le texte tape par l'eleve apres avoir ecoute un
 * son. Voir includes/seance.php, fgEnregistrerReponseEcoute(), pour ce que
 * « juste » verifie exactement - et includes/mecaniques.php,
 * fgEcouteNormaliser(), pour la tolerance sur les accents et la ponctuation.
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

// La reponse arrive sous la forme la plus simple : le texte tape, tel quel.
// Une borne de longueur ecarte les envois absurdes sans juger du contenu -
// la comparaison elle-meme reste entierement dans fgEnregistrerReponseEcoute().
$reponseRecue = $donnees['reponse'] ?? null;
if (!is_string($reponseRecue) || mb_strlen($reponseRecue) > 200) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'erreur' => 'Réponse inattendue'));
    exit;
}

$correction = fgEnregistrerReponseEcoute($seance, (int)($donnees['question'] ?? -1), $reponseRecue);
if ($correction === null) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'erreur' => 'Réponse inattendue'));
    exit;
}

$correction['ok'] = true;
echo json_encode($correction, JSON_UNESCAPED_UNICODE);
