<?php
require_once __DIR__ . '/includes/bootstrap.php';

// L'ancienne adresse d'aperçu ne représente aucun élève réel.
if (fgEstEnseignant() && ($_GET['apercu'] ?? '') === 'eleve') {
    header('Location: passeport.php', true, 303);
    exit;
}

$apercu = fgEstEnseignant() && ($_GET['vue'] ?? '') === 'passeport'
    && ($_GET['format'] ?? '') !== 'pdf';
if (fgEstEnseignant() && !$apercu) {
    require __DIR__ . '/includes/page-rapport.php';
    exit;
}
if (fgEstEleve() && (int)($_SESSION['id_eleve'] ?? 0) <= 0) {
    require __DIR__ . '/includes/page-rapport.php';
    exit;
}

require_once __DIR__ . '/includes/passeport-eleve.php';
require_once __DIR__ . '/includes/mecaniques.php';
$enseignant = (int)($_SESSION['id_enseignant'] ?? 0);
$eleve = $apercu ? (int)($_GET['eleve'] ?? 0) : (int)($_SESSION['id_eleve'] ?? 0);
$donnees = null;
$erreur = '';
try {
    $db = bdd::connexion((string)$_SESSION['bdd']);
    $donnees = fgChargerPasseport(
        $db, $enseignant, $eleve,
        new DateTimeImmutable('now', new DateTimeZone('Europe/Paris')),
        fgBanques()
    );
} catch (DomainException $e) {
    http_response_code(400);
    $erreur = $e->getMessage();
} catch (PDOException $e) {
    http_response_code(503);
    $erreur = 'Le passeport est temporairement indisponible. Vos données n’ont pas été modifiées.';
}

$fgTitre = $apercu ? 'Aperçu du passeport' : 'Mon passeport';
$fgPage = 'passeport';
require __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="/v2/fastgames/assets/passeport.css?v=20260924-lot6-final">
<section class="gx-ligne-titre"><div>
    <p class="gx-ligne-titre-surtitre">Fast Games · <?php echo $apercu ? 'aperçu enseignant' : 'espace personnel'; ?></p>
    <h1 class="gx-ligne-titre-bonjour"><?php echo fgH($fgTitre); ?></h1>
    <p class="gx-ligne-titre-description">Les jeux pratiqués et leurs résultats, sans moyenne ni classement.</p>
</div></section>
<?php if ($erreur !== '') { ?><p role="alert"><?php echo fgH($erreur); ?></p><?php } ?>
<?php if ($donnees !== null) {
    if ($apercu) {
        $choix = is_string($_GET['periode'] ?? null) ? $_GET['periode'] : 'annee';
        $retour = 'passeport.php?' . http_build_query(array('eleve' => $eleve, 'periode' => $choix));
        ?>
<div class="fg-passeport-apercu" role="status">
    <strong>Aperçu : le passeport de <?php echo fgH($donnees['identite']['prenom']); ?>, tel qu’il le voit.</strong>
    <a class="fg-bouton fg-bouton-secondaire" href="<?php echo fgH($retour); ?>">Retour au rapport</a>
</div>
    <?php }
    echo fgRenduPasseport($donnees);
} ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
