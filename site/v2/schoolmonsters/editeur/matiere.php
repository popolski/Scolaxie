<?php
// La liste d'une matiere, telle que les eleves la voient.
//
// C'est ici qu'on change l'ordre des lecons, le nom de leur lien, et qu'on en
// retire de la liste. La page de matiere n'est jamais modifiee : ce qui est
// enregistre ici est un fichier de reglages que la page applique en s'affichant.
require(__DIR__ . '/garde.php');

$periode = isset($_GET['p']) ? (string)$_GET['p'] : '';
$matiere = isset($_GET['m']) ? (string)$_GET['m'] : '';
if (smListeFichier($periode, $matiere) === null
        || !is_file(SM_RACINE . '/' . $periode . '/' . $periode . '_' . $matiere . '.php')) {
    http_response_code(404);
    exit('Matière inconnue.');
}
$capturee = smMatiereCapturee($periode, $matiere);
$suites = $capturee ? smLiensMatiere($periode, $matiere) : array();
$infos = smMatieres();
$nomMatiere = isset($infos[$matiere]) ? $infos[$matiere][0] : ucfirst($matiere);
$avis = isset($_GET['avis']) ? explode('|', $_GET['avis']) : array();
$total = 0;
foreach ($suites as $s) { $total += count($s); }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo smEch($nomMatiere); ?> — Éditeur</title>
    <link rel="stylesheet" href="/galaxie-tokens.css?v=20260912-conformite">
    <link rel="stylesheet" href="editeur.css?v=20260916-menus">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700&display=swap">
</head>
<body class="gx-typo gx-app-schoolmonsters">

<form method="post" action="matiere-enregistrer.php" id="formulaire">
<input type="hidden" name="p" value="<?php echo smEch($periode); ?>">
<input type="hidden" name="m" value="<?php echo smEch($matiere); ?>">

<?php require(__DIR__.'/bandeau.php'); ?>
<main id="contenu" class="ed-liste-page gx-largeur-grille">
<div class="ed-commandes-page">
        <span class="ed-etat" id="etat"></span>
        <a class="ed-bouton" href="../<?php echo smEch($periode . '/' . $periode . '_' . $matiere); ?>.php"
           target="_blank" rel="noopener">Voir la page élève</a>
        <?php if ($capturee): ?>
        <button type="submit" class="ed-bouton ed-fort">Enregistrer</button>
        <?php endif; ?>
    </div>



    <?php foreach ($avis as $a): if (trim($a) === '') { continue; } ?>
    <p class="ed-avis <?php echo substr($a, 0, 1) === '+' ? 'ed-avis-ok' : 'ed-avis-info'; ?>"><?php
        echo smEch(ltrim($a, '+-')); ?></p>
    <?php endforeach; ?>

    <div class="ed-accueil gx-ligne-titre">
        <h1><?php echo smEch($nomMatiere); ?></h1>
        <p class="ed-intro">Voici la liste que les élèves voient, dans l’ordre où ils la voient.
           Glissez une leçon par sa poignée pour la déplacer, changez le nom du lien, ou retirez-la
           de la liste sans la supprimer.</p>
    </div>

    <?php if (!$capturee): ?>
    <article class="ed-brique">
        <div class="ed-corps">
            <p>La page de cette matière a une mise en page particulière, et n’applique pas encore
               les réglages de l’éditeur. Rien de ce qui serait enregistré ici n’apparaîtrait aux
               élèves : mieux vaut que l’éditeur le dise plutôt que de vous laisser travailler
               pour rien.</p>
            <p class="ed-note">La liste de cette matière se modifie donc encore à la main, comme
               avant. Les leçons elles-mêmes, en revanche, se modifient normalement depuis
               l’éditeur.</p>
        </div>
    </article>
    <?php elseif ($total === 0): ?>
    <div class="gx-etat-vide ed-etat-vide">
        <span class="gx-etat-vide-icone" aria-hidden="true">+</span>
        <div>
            <h2>Cette matière n'a encore aucune leçon</h2>
            <p>Créez-en une, elle apparaîtra ici et sur la page des élèves.</p>
        </div>
        <a class="ed-bouton ed-fort" href="nouvelle.php">+ Créer une leçon</a>
    </div>
    <?php endif; ?>

    <?php foreach ($suites as $s => $lignes): if (!$lignes) { continue; } ?>
    <section class="ed-suite">
        <?php if (count($suites) > 1): ?>
        <p class="ed-suite-titre">Groupe <?php echo $s + 1; ?> sur <?php echo count($suites); ?>
            <?php // le rappel une seule fois : repete a chaque groupe, il devient du bruit ?>
            <?php if ($s === 0): ?>
            <span>une leçon ne se déplace qu’à l’intérieur de son groupe</span>
            <?php endif; ?></p>
        <?php endif; ?>
        <ol class="ed-rangs" data-suite>
        <?php foreach ($lignes as $l): ?>
            <li class="ed-rang<?php echo $l['masquee'] ? ' eteinte' : ''; ?>">
                <span class="ed-poignee" title="Glisser pour déplacer" draggable="true">⠿</span>
                <input type="hidden" name="ordre[]" value="<?php echo smEch($l['fichier']); ?>">
                <span class="ed-nommage">
                    <input type="text" class="ed-libelle" name="libelle[<?php echo smEch($l['fichier']); ?>]"
                           value="<?php echo smEch($l['libelle']); ?>" aria-label="Nom du lien">
                    <span class="ed-son" title="<?php echo smEch($l['fichier']); ?>"><?php
                        echo smEch($l['fichier']); ?><?php echo $l['ajoutee'] ? ' · créée ici' : ''; ?></span>
                </span>
                <?php $cheminLecon = $periode . '/' . $matiere . '/' . $l['fichier']; ?>
                <details class="ed-menu-rang">
                    <summary title="Actions" aria-label="Actions pour la leçon <?php echo smEch($l['libelle']); ?>">⋯</summary>
                    <div class="ed-menu-liste">
                        <?php // une page ecrite avant l'editeur (poesie...) n'a pas de donnees : rien a modifier ?>
                        <?php if (smFichierDonnees($cheminLecon) !== null): ?>
                        <a href="lecon.php?l=<?php echo rawurlencode($cheminLecon); ?>">Modifier</a>
                        <?php endif; ?>
                        <a class="ed-menu-danger" href="supprimer.php?l=<?php echo rawurlencode($cheminLecon); ?>">Supprimer</a>
                    </div>
                </details>
                <label class="ed-bascule" title="Afficher ou retirer de la liste">
                    <input type="checkbox" name="visible[]" value="<?php echo smEch($l['fichier']); ?>"<?php
                        echo $l['masquee'] ? '' : ' checked'; ?>>
                    <span></span>
                </label>
            </li>
        <?php endforeach; ?>
        </ol>
    </section>
    <?php endforeach; ?>

    <?php if ($total > 0): ?>
    <p class="ed-note">Une leçon retirée de la liste n’apparaît plus aux élèves, mais son adresse
       continue de fonctionner et rien n’est effacé. « Supprimer » efface la leçon pour de bon,
       après confirmation.</p>
    <?php endif; ?>

</main>
</form>

<script src="editeur.js?v=20260901-01"></script>
<script>
// Un seul menu d'actions ouvert a la fois ; un clic ailleurs ou Echap le ferme.
document.addEventListener('click', function (e) {
    document.querySelectorAll('.ed-menu-rang[open]').forEach(function (m) {
        if (!m.contains(e.target)) { m.removeAttribute('open'); }
    });
});
document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') { return; }
    document.querySelectorAll('.ed-menu-rang[open]').forEach(function (m) {
        m.removeAttribute('open');
        m.querySelector('summary').focus();
    });
});
</script>
</body>
</html>
