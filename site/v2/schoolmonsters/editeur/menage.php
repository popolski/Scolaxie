<?php
// Les fichiers son qui ne servent a aucune lecon.
//
// Retirer une piste laisse volontairement son fichier sur le serveur : c'est
// ce qui permet de la remettre d'un clic. Mais sans un endroit ou les voir,
// ces fichiers s'accumulent sans que personne ne le sache. Cette page les
// montre, et permet de les effacer quand on est sur.
//
// Elle ne montre QUE les dossiers reellement utilises par une lecon : on ne
// part pas a la peche dans tout le site.
require(__DIR__ . '/garde.php');

/** Les sons presents dans les dossiers des lecons, et cites par aucune. */
function smSonsOrphelins()
{
    $utilises = array();     // dossier => array(fichier => true)
    $dossiers = array();     // dossier => array(titres des lecons qui s'en servent)
    foreach (smListeLecons() as $l) {
        $json = SM_LECONS . '/' . substr($l['chemin'], 0, -4) . '.json';
        $d = json_decode((string)@file_get_contents($json), true);
        if (!is_array($d)) { continue; }
        $dossier = smDossierSons($l['chemin'], $d);
        if (!isset($utilises[$dossier])) {
            $utilises[$dossier] = array();
            $dossiers[$dossier] = array();
        }
        $dossiers[$dossier][] = $l['titre'];
        foreach ($d['blocs'] as $b) {
            if ($b['type'] !== 'pistes') { continue; }
            foreach ($b['pistes'] as $p) { $utilises[$dossier][basename(trim($p['son']))] = true; }
        }
    }
    $out = array();
    foreach ($utilises as $dossier => $cites) {
        $absolu = SM_RACINE . '/' . $dossier;
        if (!is_dir($absolu)) { continue; }
        $libres = array();
        foreach ((array)scandir($absolu) as $f) {
            if ($f === '.' || $f === '..' || isset($cites[$f])) { continue; }
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (!isset(smFormatsAudio()[$ext]) || !is_file($absolu . '/' . $f)) { continue; }
            $libres[] = array('nom' => $f, 'taille' => filesize($absolu . '/' . $f),
                              'quand' => filemtime($absolu . '/' . $f));
        }
        if ($libres) {
            sort($libres);
            $out[] = array('dossier' => $dossier, 'lecons' => $dossiers[$dossier], 'fichiers' => $libres);
        }
    }
    return $out;
}

$orphelins = smSonsOrphelins();

// ------------------------------------------------------------- l'effacement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // On ne fait confiance qu'a la liste calculee : un chemin recu du
    // formulaire n'est jamais efface pour lui-meme.
    $permis = array();
    foreach ($orphelins as $o) {
        foreach ($o['fichiers'] as $f) { $permis[$o['dossier'] . '/' . $f['nom']] = true; }
    }
    $faits = 0; $rates = 0;
    foreach (smTableau('effacer') as $c) {
        if (!isset($permis[$c])) { continue; }
        if (@unlink(SM_RACINE . '/' . $c)) { $faits++; } else { $rates++; }
    }
    $avis = $faits === 0 && $rates === 0
        ? '-Aucun fichier sélectionné.'
        : '+' . $faits . ' fichier' . ($faits > 1 ? 's effacés' : ' effacé')
          . ($rates ? ', ' . $rates . ' n’a pas pu l’être' : '') . '.';
    header('location:menage.php?avis=' . rawurlencode($avis));
    exit();
}

$avis = isset($_GET['avis']) ? explode('|', $_GET['avis']) : array();
$nb = 0; $octets = 0;
foreach ($orphelins as $o) {
    foreach ($o['fichiers'] as $f) { $nb++; $octets += $f['taille']; }
}
function smPoids($o)
{
    return $o > 1048576 ? round($o / 1048576, 1) . ' Mo' : max(1, round($o / 1024)) . ' Ko';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sons inutilisés — Éditeur</title>
    <link rel="stylesheet" href="/galaxie-tokens.css?v=20260912-conformite">
    <link rel="stylesheet" href="editeur.css?v=20260912-conformite">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700&display=swap">
</head>
<body class="gx-typo gx-app-schoolmonsters">

<form method="post" action="menage.php" id="formulaire"
      onsubmit="return confirm('Effacer les fichiers cochés ? Cette action est définitive.');">

<?php require(__DIR__.'/bandeau.php'); ?>

<main id="contenu" class="ed-liste-page gx-largeur-grille">

    <?php foreach ($avis as $a): if (trim($a) === '') { continue; } ?>
    <p class="ed-avis <?php echo substr($a, 0, 1) === '+' ? 'ed-avis-ok' : 'ed-avis-info'; ?>"><?php
        echo smEch(ltrim($a, '+-')); ?></p>
    <?php endforeach; ?>

    <div class="ed-accueil gx-ligne-titre">
        <h1>Sons inutilisés</h1>
        <p class="ed-intro">Ces fichiers sont dans les dossiers de vos leçons mais aucune ne s’en
           sert. Souvent ce sont des pistes retirées, parfois des enregistrements plus anciens.
           Vous pouvez les remettre dans une leçon depuis « Ajouter des sons », ou les effacer ici.</p>
        <div class="ed-bilan">
            <span class="ed-jeton"><b><?php echo $nb; ?></b> fichier<?php echo $nb > 1 ? 's' : ''; ?></span>
            <span class="ed-jeton"><b><?php echo smPoids($octets); ?></b> au total</span>
            <span class="ed-jeton"><b><?php echo count($orphelins); ?></b> dossier<?php
                echo count($orphelins) > 1 ? 's' : ''; ?></span>
        </div>
    </div>

    <?php if (!$orphelins): ?>
    <p class="ed-vide">Aucun son inutilisé. Tous les fichiers de vos dossiers servent à une leçon.</p>
    <?php endif; ?>

    <?php foreach ($orphelins as $o): ?>
    <section class="ed-suite">
        <p class="ed-suite-titre"><?php echo smEch($o['dossier']); ?>
            <span><?php echo smEch(implode(' · ', array_slice($o['lecons'], 0, 3)));
                        echo count($o['lecons']) > 3 ? '…' : ''; ?></span></p>
        <div class="ed-libres">
            <?php foreach ($o['fichiers'] as $f): ?>
            <label class="ed-libre">
                <input type="checkbox" name="effacer[]"
                       value="<?php echo smEch($o['dossier'] . '/' . $f['nom']); ?>">
                <span><?php echo smEch($f['nom']); ?></span>
                <em><?php echo smPoids($f['taille']); ?></em>
            </label>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endforeach; ?>

    <?php if ($nb): ?>
    <p class="ed-note">Rien n’est coché au départ, et l’effacement demande une confirmation.
       Un fichier effacé ici ne peut pas être récupéré : dans le doute, laissez-le, il ne gêne
       personne.</p>
    <?php endif; ?>

</main>
</form>
</body>
</html>
