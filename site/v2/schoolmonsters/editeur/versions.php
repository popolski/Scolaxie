<?php
// Les versions precedentes d'une lecon, et le retour a l'une d'elles.
//
// Le filet existait depuis le premier jour : chaque enregistrement met de cote
// la version d'avant. Il manquait la poignee pour le tirer. La voici.
//
// Restaurer n'efface rien : la version actuelle est archivee a son tour avant
// d'etre remplacee. On peut donc revenir en arriere, puis revenir en avant.
require(__DIR__ . '/garde.php');

$chemin = isset($_POST['l']) ? (string)$_POST['l'] : (isset($_GET['l']) ? (string)$_GET['l'] : '');
$json = smFichierDonnees($chemin);
if ($json === null) {
    http_response_code(404);
    exit('Leçon inconnue.');
}

/** Un resume lisible d'une version : titre, nombre de pistes, document. */
function smResumeVersion($fichier)
{
    $d = json_decode((string)@file_get_contents($fichier), true);
    if (!is_array($d) || !isset($d['blocs'])) {
        return null;
    }
    $r = array('titre' => '', 'pistes' => 0, 'masquees' => 0, 'sansPage' => 0, 'document' => '');
    foreach ($d['blocs'] as $b) {
        if ($b['type'] === 'titre') { $r['titre'] = smTitreLisible($b['contenu']); }
        if ($b['type'] === 'pdf') { $r['document'] = basename($b['fichier']); }
        if ($b['type'] === 'pistes') {
            foreach ($b['pistes'] as $p) {
                $r['pistes']++;
                if (isset($p['actif']) && !$p['actif']) { $r['masquees']++; }
                if (empty($p['page'])) { $r['sansPage']++; }
            }
        }
    }
    return $r;
}

/** Une date en francais, sans dependre de la locale du serveur. */
function smDateFr($t)
{
    $jours = array('dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi');
    $mois = array('', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
                  'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre');
    return $jours[(int)date('w', $t)] . ' ' . (int)date('j', $t) . ' ' . $mois[(int)date('n', $t)]
         . ' à ' . date('H\hi', $t);
}

// ------------------------------------------------------------ la restauration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['version'])) {
    $voulue = (string)$_POST['version'];
    $connue = null;
    foreach (smVersions($chemin) as $v) {
        if ($v['fichier'] === $voulue) { $connue = $v; }
    }
    if ($connue === null) {
        http_response_code(400);
        exit('Version inconnue.');
    }
    $source = SM_VERSIONS . '/' . $connue['fichier'];
    if (smResumeVersion($source) === null) {
        http_response_code(400);
        exit('Cette version est illisible : rien n’a été modifié.');
    }
    // La version actuelle part d'abord dans les archives : restaurer ne doit
    // jamais etre un aller sans retour.
    smArchiver($json, str_replace('/', '__', substr($chemin, 0, -4)));
    $contenu = @file_get_contents($source);
    $provisoire = $json . '.tmp';
    $ecrit = $contenu !== false && @file_put_contents($provisoire, $contenu, LOCK_EX) !== false;
    if ($ecrit && !@rename($provisoire, $json)) {
        $ecrit = @unlink($json) && @rename($provisoire, $json);
    }
    if (!$ecrit) {
        @unlink($provisoire);
        http_response_code(500);
        exit('Restauration impossible. Rien n’a été modifié.');
    }
    header('location:lecon.php?l=' . rawurlencode($chemin) . '&avis='
         . rawurlencode('+Version du ' . smDateFr($connue['quand'])
                      . ' restaurée. L’état précédent a été archivé, vous pouvez y revenir.'));
    exit();
}

$versions = smVersions($chemin);
$actuelle = smResumeVersion($json);
list($periode, $matiere) = explode('/', $chemin);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Versions précédentes — Éditeur</title>
    <link rel="stylesheet" href="/galaxie-tokens.css?v=20260912-conformite">
    <link rel="stylesheet" href="editeur.css?v=20260912-conformite">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700&display=swap">
</head>
<body class="gx-typo gx-app-schoolmonsters">

<?php require(__DIR__.'/bandeau.php'); ?>

<main id="contenu" class="ed-liste-page ed-etroit gx-largeur-grille">

    <div class="ed-accueil gx-ligne-titre">
        <h1>Versions précédentes</h1>
        <p class="ed-intro">Chaque enregistrement met de côté la version d’avant. Revenir à l’une
           d’elles n’efface rien : l’état actuel est archivé à son tour, vous pourrez donc revenir
           en avant.</p>
    </div>

    <article class="ed-brique ed-brique-large">
        <header><span class="ed-nom">Version actuelle</span><span class="ed-compte-brique">en ligne</span></header>
        <div class="ed-corps">
            <?php if ($actuelle): ?>
            <p class="ed-version-titre"><?php echo smEch($actuelle['titre']); ?></p>
            <p class="ed-note"><?php echo $actuelle['pistes']; ?> piste<?php echo $actuelle['pistes'] > 1 ? 's' : ''; ?><?php
                if ($actuelle['masquees']) { echo ', dont ' . $actuelle['masquees'] . ' masquée' . ($actuelle['masquees'] > 1 ? 's' : ''); }
                if ($actuelle['sansPage']) { echo ' · ' . $actuelle['sansPage'] . ' sans page'; } ?>
                · document <code><?php echo smEch($actuelle['document']); ?></code></p>
            <?php else: ?>
            <p class="ed-note">Les données actuelles sont illisibles.</p>
            <?php endif; ?>
        </div>
    </article>

    <?php if (!$versions): ?>
    <div class="gx-etat-vide">
        <span class="gx-etat-vide-icone" aria-hidden="true">↶</span>
        <div><h2>Aucune version précédente</h2><p>Cette leçon n’a pas encore été modifiée depuis l’éditeur.</p></div>
        <a class="gx-bouton gx-bouton-secondaire" href="lecon.php?l=<?php echo rawurlencode($chemin); ?>">Modifier la leçon</a>
    </div>
    <?php else: ?>
    <ol class="ed-versions">
        <?php foreach ($versions as $v): $r = smResumeVersion(SM_VERSIONS . '/' . $v['fichier']); ?>
        <li class="ed-version">
            <span class="ed-version-quand"><?php echo smEch(smDateFr($v['quand'])); ?></span>
            <span class="ed-version-quoi">
                <?php if ($r): ?>
                <b><?php echo smEch($r['titre']); ?></b>
                <i><?php echo $r['pistes']; ?> piste<?php echo $r['pistes'] > 1 ? 's' : ''; ?><?php
                    if ($r['masquees']) { echo ', ' . $r['masquees'] . ' masquée' . ($r['masquees'] > 1 ? 's' : ''); }
                    if ($r['sansPage']) { echo ' · ' . $r['sansPage'] . ' sans page'; } ?></i>
                <?php else: ?>
                <b>Version illisible</b><i>elle ne peut pas être restaurée</i>
                <?php endif; ?>
            </span>
            <?php if ($r): ?>
            <form method="post" action="versions.php" class="ed-version-action"
                  onsubmit="return confirm('Revenir à la version du <?php echo smEch(smDateFr($v['quand'])); ?> ?');">
                <input type="hidden" name="l" value="<?php echo smEch($chemin); ?>">
                <input type="hidden" name="version" value="<?php echo smEch($v['fichier']); ?>">
                <button type="submit" class="ed-bouton">Revenir à celle-ci</button>
            </form>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ol>
    <?php endif; ?>

</main>
</body>
</html>
