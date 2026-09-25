<?php
require_once __DIR__ . '/sso.php';
require_once dirname(__DIR__) . '/fasteval/utils/class/class_bdd.php';

function portailNomComplet(string $prenom, string $nom): string
{
    $prenom = mb_convert_case(mb_strtolower(trim($prenom), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    $nom = mb_strtoupper(trim($nom), 'UTF-8');
    return trim($prenom . ' ' . $nom);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . CV_PORTAIL_V2_PATH);
    exit;
}

$role = ($_POST['role'] ?? '') === 'teacher' ? 'teacher' : 'student';
$identifiant = trim((string)($_POST['identifiant'] ?? ''));
$password = trim((string)($_POST['password'] ?? ''));
$teacherId = (int)($_POST['teacherId'] ?? 0);

// L'identifiant ne repart jamais dans l'URL : il pourrait contenir une donnée
// personnelle et finir dans l'historique du navigateur ou les journaux serveur.
function portailRetourErreur(string $role, int $teacherId): void
{
    $parametres = array('erreur' => '1', 'role' => $role);
    if ($role === 'student' && $teacherId > 0) {
        $parametres['classe'] = (string)$teacherId;
    }
    header('Location: ' . CV_PORTAIL_V2_PATH . '?' . http_build_query($parametres));
    exit;
}

if ($identifiant === '' || $password === '') {
    portailRetourErreur($role, $teacherId);
}

$db = bdd::connexion(scolaxieConfig('SCOLAXIE_DB_NAME'));
if ($role === 'teacher') {
    $stmt = $db->prepare(
        'SELECT ad.id_enseignant,ad.identifiant,ad.est_admin,e.nom,e.prenom
           FROM ayant_droit ad
           JOIN enseignant e ON e.id_enseignant=ad.id_enseignant
          WHERE LOWER(ad.identifiant)=LOWER(:identifiant) AND ad.code=:code LIMIT 2'
    );
    $stmt->execute(array(':identifiant' => $identifiant, ':code' => $password));
} else {
    $stmt = $db->prepare(
        'SELECT id_eleve,id_enseignant,nom,prenom
           FROM classe
          WHERE id_enseignant=:teacher AND LOWER(prenom)=LOWER(:identifiant)
            AND code=:code AND actif=1 LIMIT 2'
    );
    $stmt->execute(array(':teacher' => $teacherId, ':identifiant' => $identifiant, ':code' => $password));
}

$matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (count($matches) !== 1) {
    portailRetourErreur($role, $teacherId);
}

$row = $matches[0];
$identity = $role === 'teacher'
    ? array('role' => 'teacher', 'tid' => (int)$row['id_enseignant'], 'label' => portailNomComplet($row['prenom'], $row['nom']), 'admin' => !empty($row['est_admin']))
    : array('role' => 'student', 'tid' => (int)$row['id_enseignant'], 'sid' => (int)$row['id_eleve'], 'label' => portailNomComplet($row['prenom'], $row['nom']));

ssoSetCookie(ssoCreateToken($identity));
header('Location: ' . CV_PORTAIL_V2_PATH);
exit;
