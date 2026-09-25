<?php
// Supprimer une lecon.
//
// Deux cas, et ils ne se ressemblent pas :
//
//  - UNE LECON CREEE ICI. Son lien vient d'un fichier de reglages, donc on
//    peut tout retirer proprement : la page, les donnees, le lien. Ses medias
//    partent avec elle SI aucune autre lecon ne s'en sert.
//
//  - UNE DES LECONS D'ORIGINE (y compris les pages sans donnees, comme les
//    poesies). Son lien est ecrit a la main dans la page de matiere, que
//    l'editeur ne touche jamais. On efface la page et ses donnees, et on
//    inscrit la lecon dans « supprimees » : liste-lecons-fin.php retire alors
//    le lien, donc aucun lien mort. Ses medias restent : ces pages ecrites a la
//    main peuvent partager un document ou des sons sans que l'inventaire des
//    donnees le sache.
//
// Dans les deux cas, la page et les donnees sont archivees avant de disparaitre.
require(__DIR__ . '/garde.php');

$chemin = isset($_POST['l']) ? (string)$_POST['l'] : (isset($_GET['l']) ? (string)$_GET['l'] : '');
$php = smCheminLecon($chemin);
if ($php === null) {
    http_response_code(404);
    exit('Leçon inconnue.');
}
$json = smFichierDonnees($chemin);
list($periode, $matiere, $nom) = explode('/', $chemin);
$lecon = $json !== null ? json_decode(file_get_contents($json), true) : null;
$titre = '';
foreach (is_array($lecon) ? (array)$lecon['blocs'] : array() as $b) {
    if ($b['type'] === 'titre') { $titre = smTitreLisible($b['contenu']); }
}
$creeeIci = smLeconAjoutee($chemin);
$capturee = smMatiereCapturee($periode, $matiere);
if ($titre === '') {
    foreach (smLiensMatiere($periode, $matiere) as $suite) {
        foreach ($suite as $l) { if ($l['fichier'] === $nom) { $titre = $l['libelle']; } }
    }
}
if ($titre === '') { $titre = $nom; }
$retour = $json !== null
    ? 'lecon.php?l=' . rawurlencode($chemin)
    : 'matiere.php?p=' . rawurlencode($periode) . '&m=' . rawurlencode($matiere);

// ------------------------------------------------------- la confirmation
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['confirme'])) {
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supprimer une leçon — Éditeur</title>
    <link rel="stylesheet" href="/galaxie-tokens.css?v=20260912-conformite">
    <link rel="stylesheet" href="editeur.css?v=20260912-conformite">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700&display=swap">
</head>
<body class="gx-typo gx-app-schoolmonsters">
<?php require(__DIR__.'/bandeau.php'); ?>
<main id="contenu" class="ed-liste-page ed-etroit gx-largeur-lecture">
    <div class="ed-accueil gx-ligne-titre">
        <h1>Supprimer « <?php echo smEch($titre); ?> » ?</h1>
    </div>

    <?php if ($creeeIci && $json !== null): ?>
    <article class="ed-brique">
        <div class="ed-corps">
            <p>Cette leçon a été créée depuis l’éditeur. Elle peut être supprimée entièrement.</p>
            <p class="ed-note">Ce qui disparaît : la page de la leçon, ses données, et son lien sur
               la page de <?php echo smEch($matiere); ?>.</p>
            <?php $medias = smMediasExclusifs($chemin, $lecon); ?>
            <p class="ed-note">
            <?php if ($medias['dossier_sons'] === null && $medias['pdf'] === null): ?>
                Son document et ses sons servent aussi à d’autres leçons : ils restent sur le serveur.
            <?php else: ?>
                Partent aussi, parce que rien d’autre ne s’en sert :
                <?php if ($medias['pdf'] !== null): ?>le document
                <code><?php echo smEch(basename($medias['pdf'])); ?></code><?php endif;
                if ($medias['pdf'] !== null && $medias['dossier_sons'] !== null) { echo ' et '; }
                if ($medias['dossier_sons'] !== null): ?>le dossier de sons
                <code><?php echo smEch(basename($medias['dossier_sons'])); ?></code><?php endif; ?>.
            <?php endif; ?>
            </p>
            <p class="ed-note">Une copie des données part dans les versions archivées avant
               d’effacer. Rien n’est perdu définitivement.</p>
            <form method="post" action="supprimer.php" class="ed-boutons">
                <input type="hidden" name="l" value="<?php echo smEch($chemin); ?>">
                <a class="ed-bouton" href="<?php echo smEch($retour); ?>">Annuler</a>
                <button type="submit" name="confirme" value="1" class="ed-bouton ed-danger">Supprimer pour de bon</button>
            </form>
        </div>
    </article>
    <?php elseif (!$capturee): ?>
    <article class="ed-brique">
        <div class="ed-corps">
            <p>La page de <?php echo smEch($matiere); ?> n’applique pas encore les réglages de
               l’éditeur : son lien vers cette leçon ne pourrait pas être retiré, et les élèves
               tomberaient sur une page introuvable. La suppression est donc refusée ici.</p>
            <div class="ed-boutons">
                <a class="ed-bouton" href="<?php echo smEch($retour); ?>">Revenir</a>
            </div>
        </div>
    </article>
    <?php else: ?>
    <article class="ed-brique">
        <div class="ed-corps">
            <p>Cette leçon fait partie de celles écrites avant l’éditeur.</p>
            <p class="ed-note">Ce qui disparaît : la page de la leçon<?php echo $json !== null ? ', ses données' : ''; ?>,
               et son lien sur la page de <?php echo smEch($matiere); ?>.</p>
            <p class="ed-note">Son document et ses sons restent sur le serveur : ils peuvent servir à
               d’autres pages.</p>
            <p class="ed-note">Une copie de la page<?php echo $json !== null ? ' et des données' : ''; ?>
               part dans les archives avant d’effacer. Rien n’est perdu définitivement.</p>
            <p class="ed-note">Pour la faire seulement disparaître aux yeux des élèves, sans rien
               effacer, retirez-la plutôt de la liste de la matière.</p>
            <form method="post" action="supprimer.php" class="ed-boutons">
                <input type="hidden" name="l" value="<?php echo smEch($chemin); ?>">
                <a class="ed-bouton" href="<?php echo smEch($retour); ?>">Annuler</a>
                <button type="submit" name="confirme" value="1" class="ed-bouton ed-danger">Supprimer pour de bon</button>
            </form>
        </div>
    </article>
    <?php endif; ?>
</main>
</body>
</html>
    <?php
    exit();
}

// ------------------------------------------------------------ la suppression
if (!$capturee && !$creeeIci) {
    http_response_code(400);
    exit('Cette leçon ne peut pas être supprimée depuis l’éditeur.');
}

$etiquette = str_replace('/', '__', substr($chemin, 0, -4)) . '-SUPPRIMEE';
if ($json !== null) { smArchiver($json, $etiquette); }
// La page d'une lecon d'origine EST la lecon quand elle n'a pas de donnees :
// on la garde aussi. lecons/ est ferme au navigateur, le .php n'y est jamais
// execute.
if (is_file($php) && (is_dir(SM_VERSIONS) || @mkdir(SM_VERSIONS, 0775, true))) {
    @copy($php, SM_VERSIONS . '/' . $etiquette . '-PAGE-' . date('Ymd-His') . '.php');
}

// Ce qui n'est utilise que par cette lecon s'en va avec elle. On le calcule
// AVANT d'effacer les donnees, sinon la lecon ne compte plus dans l'inventaire.
$medias = ($creeeIci && is_array($lecon))
    ? smMediasExclusifs($chemin, $lecon) : array('pdf' => null, 'dossier_sons' => null);

$ok = true;
if ($json !== null) { $ok = @unlink($json); }
if (is_file($php)) { $ok = @unlink($php) && $ok; }

$partis = array();
if ($medias['pdf'] !== null && @unlink(SM_RACINE . '/' . $medias['pdf'])) {
    $partis[] = basename($medias['pdf']);
}
if ($medias['dossier_sons'] !== null) {
    $n = smEffacerDossierSons($medias['dossier_sons']);
    if ($n) { $partis[] = $n . ' fichier' . ($n > 1 ? 's son' : ' son'); }
}

$reglages = smListeReglages($periode, $matiere);
$restantes = array();
foreach ($reglages['ajoutees'] as $a) {
    if (isset($a['fichier']) && $a['fichier'] === $nom) { continue; }
    $restantes[] = $a;
}
$reglages['ajoutees'] = $restantes;
$reglages['ordre'] = array_values(array_diff($reglages['ordre'], array($nom)));
$reglages['masquees'] = array_values(array_diff($reglages['masquees'], array($nom)));
unset($reglages['libelles'][$nom]);
if (!$creeeIci && !in_array($nom, $reglages['supprimees'], true)) {
    $reglages['supprimees'][] = $nom;
}
smListeEcrire($periode, $matiere, $reglages);

$avis = $ok
    ? '+La leçon « ' . $titre . ' » est supprimée'
      . ($partis ? ', avec ' . implode(' et ', $partis) : '')
      . '. Une copie reste dans les archives.'
    : '-La leçon « ' . $titre . ' » a été retirée de la liste, mais certains fichiers n’ont pas pu être effacés.';
header('location:matiere.php?p=' . rawurlencode($periode) . '&m=' . rawurlencode($matiere)
     . '&avis=' . rawurlencode($avis));
exit();
