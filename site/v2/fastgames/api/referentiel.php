<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/referentiel.php';

header('Content-Type: application/json; charset=utf-8');
if (!fgEstEnseignant()) {
    http_response_code(403);
    echo json_encode(array('ok' => false, 'erreur' => 'Accès réservé à l’enseignant'), JSON_UNESCAPED_UNICODE);
    exit;
}

$type = isset($_GET['type']) ? (string)$_GET['type'] : 'tous';
if (!in_array($type, array('tous', 'competence', 'connaissance'), true)) {
    $type = 'tous';
}
$recherche = isset($_GET['q']) ? (string)$_GET['q'] : '';

try {
    $db = bdd::connexion((string)$_SESSION['bdd']);
    $resultat = fgChercherReferentiel($db, $recherche, $type, 50);
    echo json_encode(array(
        'ok' => true,
        'total' => $resultat['total'],
        'resultats' => $resultat['resultats'],
    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $erreur) {
    error_log('FastGames referentiel : ' . $erreur->getMessage());
    http_response_code(500);
    echo json_encode(array('ok' => false, 'erreur' => 'Référentiel momentanément indisponible'), JSON_UNESCAPED_UNICODE);
}
