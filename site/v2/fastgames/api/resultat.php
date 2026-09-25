<?php
/**
 * Clot une partie et enregistre son score.
 *
 * LE SCORE N'EST PLUS RECU DU NAVIGATEUR. Il est recompte ici a partir des
 * choix gardes en session pendant la partie, compares aux bonnes reponses
 * reconstruites par le generateur. Le client n'envoie que l'identifiant de la
 * seance : il n'a plus rien a declarer.
 *
 * La seance est ensuite fermee, ce qui interdit d'enregistrer deux fois la meme
 * partie, et une garde de cadence arrete les envois en boucle.
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
    echo json_encode(array('ok' => false, 'erreur' => 'Cette partie n’est plus ouverte.'));
    exit;
}

// Une partie ne compte que si elle a ete jouee jusqu'au bout : sans cela, il
// suffirait de la clore aussitot ouverte, ou de s'arreter des qu'une reponse
// est fausse pour ne garder que les bonnes.
if (count($seance['reponses']) < (int)$seance['total']) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'erreur' => 'Partie incomplète'));
    exit;
}

$refus = fgRefusDeCadence();
if ($refus !== '') {
    http_response_code(429);
    echo json_encode(array('ok' => false, 'erreur' => $refus), JSON_UNESCAPED_UNICODE);
    exit;
}

$score = fgScoreSeance($seance);
$total = (int)$seance['total'];
if ($score === null || $total <= 0) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'erreur' => 'Résultat invalide'));
    exit;
}

// Le niveau de depart de la PROCHAINE partie de compte est bon suit cette
// partie-ci. Volontairement APRES la garde ci-dessus : une seance invalide
// (score null ou totale a 0) ne doit pas faire bouger le niveau.
if ((string)$seance['jeu'] === 'compte-est-bon') {
    require_once dirname(__DIR__) . '/includes/compte-est-bon.php';
    fgCompteAjusterNiveau($score, $total);
}
if ((string)$seance['jeu'] === 'boutique') {
    require_once dirname(__DIR__) . '/includes/boutique.php';
    fgBoutiqueAjusterNiveau($score, $total);
}
if ((string)$seance['jeu'] === 'horloge') {
    require_once dirname(__DIR__) . '/includes/horloge.php';
    fgHorlogeAjusterNiveau($score, $total);
}

if (!isset($_SESSION['fastgames_resultats']) || !is_array($_SESSION['fastgames_resultats'])) {
    $_SESSION['fastgames_resultats'] = array();
}
$_SESSION['fastgames_resultats'][] = array(
    'jeu' => (string)$seance['jeu'],
    'score' => $score,
    'total' => $total,
    'date' => date('c'),
    'apercu' => fgEstEnseignant(),
    'programme' => (string)$seance['programme'],
    'theme' => (string)$seance['theme'],
    'categorie' => (string)$seance['categorie'],
);
// Cette liste sert au passeport, qui ne montre que la session en cours. Sans
// borne elle grossissait a chaque partie et etait relue a chaque page. Trente
// suffisent : il n'y a que trois jeux, donc le meilleur score reste juste.
if (count($_SESSION['fastgames_resultats']) > 30) {
    $_SESSION['fastgames_resultats'] = array_slice($_SESSION['fastgames_resultats'], -30);
}

$persistant = false;
if (fgEstEleve()) {
    require_once dirname(__DIR__) . '/includes/resultats.php';
    $dbResultat = bdd::connexion((string)$_SESSION['bdd']);
    $persistant = fgEnregistrerResultatDurable($dbResultat, array(
        'id_enseignant' => (int)$_SESSION['id_enseignant'],
        'id_eleve' => (int)$_SESSION['id_eleve'],
        'programme' => (string)$seance['programme'] ?: '2015',
        'theme' => (string)$seance['theme'] ?: 'libre',
        'categorie' => (string)$seance['categorie'] ?: (string)$seance['jeu'],
        'generateur' => (string)$seance['generateur'] ?: (string)$seance['jeu'],
        'type_reference' => (string)$seance['type_reference'],
        'id_reference' => (int)$seance['id_reference'],
        'jeu' => (string)$seance['jeu'],
        'niveau_depart' => (int)($seance['niveau'] ?? 0),
        'score' => $score,
        'total' => $total,
    ));
}

fgNoterCadence();
// Fermer CETTE partie-la, pas toutes : un autre onglet peut en tenir une
// autre encore en cours depuis le 10/09/2026 (FG-AUDIT-004).
fgFermerSeance((string)$seance['id']);

echo json_encode(array(
    'ok' => true,
    'score' => $score,
    'total' => $total,
    'persistant' => $persistant,
), JSON_UNESCAPED_UNICODE);
