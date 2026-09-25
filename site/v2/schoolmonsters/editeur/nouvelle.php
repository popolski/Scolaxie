<?php
// Creer une lecon.
//
// Le formulaire ne demande que ce qui ne peut pas etre choisi ailleurs : ou la
// ranger, son titre, son document. Couleur, mascotte, nom du lien et sons se
// reglent ensuite dans l'editeur, la ou on les modifie de toute facon plus
// tard - les demander ici aussi doublait la question. Tout le reste (nom de
// fichier, dossier des sons, talon PHP, lien sur la page de matiere) est
// calcule, parce que c'est precisement la partie que l'enseignante faisait a
// la main et ou les erreurs se glissaient. Allege le 01/09/2026 : l'ancien
// formulaire posait huit questions, celui-ci n'en garde que trois.
require(__DIR__ . '/garde.php');

$periodes = smPeriodes();
$creables = array();
foreach ($periodes as $p => $_) {
    $creables[$p] = smMatieresCreables($p);
}
$sansPlace = array();
foreach ($periodes as $p => $_) {
    foreach (smMatieres() as $cle => $info) {
        if (is_file(SM_RACINE . '/' . $p . '/' . $p . '_' . $cle . '.php')
                && !isset($creables[$p][$cle])) {
            $sansPlace[] = str_replace('_', ' ', $p) . ' / ' . $info[0];
        }
    }
}
$erreurs = isset($_GET['erreur']) ? explode('|', $_GET['erreur']) : array();
$repris = isset($_SESSION['ed_nouvelle']) ? $_SESSION['ed_nouvelle'] : array();
unset($_SESSION['ed_nouvelle']);
function smRepris($cle, $defaut = '')
{
    global $repris;
    return isset($repris[$cle]) ? $repris[$cle] : $defaut;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer une leçon — Éditeur</title>
    <link rel="stylesheet" href="/galaxie-tokens.css?v=20260912-conformite">
    <link rel="stylesheet" href="editeur.css?v=20260912-conformite">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700&display=swap">
</head>
<body class="gx-typo gx-app-schoolmonsters">

<form method="post" action="creer.php" id="formulaire" enctype="multipart/form-data">

<?php require(__DIR__.'/bandeau.php'); ?>
<main id="contenu" class="ed-liste-page gx-largeur-lecture">
<div class="ed-commandes-page">
        <span class="ed-etat" id="etat"></span>
        <button type="submit" class="ed-bouton ed-fort">Créer la leçon</button>
    </div>



    <?php foreach ($erreurs as $e): if (trim($e) === '') { continue; } ?>
    <p class="ed-avis ed-avis-info"><?php echo smEch($e); ?></p>
    <?php endforeach; ?>

    <div class="ed-accueil gx-ligne-titre">
        <h1>Créer une leçon</h1>
        <p class="ed-intro">La page, son adresse et le lien depuis la page de matière sont créés
           pour vous. La couleur du titre, la mascotte, le nom du lien et les sons ont une valeur
           de départ : vous les réglerez juste après, dans l’éditeur de cette leçon.</p>
    </div>

    <div class="ed-deux-colonnes">

        <article class="ed-brique">
            <header><span class="ed-nom">Où la ranger</span></header>
            <div class="ed-corps">
                <div class="ed-ligne">
                    <label class="ed-champ-large">
                        <span class="ed-etiquette">Période</span>
                        <select name="periode" class="ed-champ" id="choix-periode" required>
                            <?php foreach ($periodes as $p => $nom): if (!$creables[$p]) { continue; } ?>
                            <option value="<?php echo smEch($p); ?>"<?php
                                echo smRepris('periode') === $p ? ' selected' : ''; ?>><?php echo smEch($nom); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="ed-champ-large">
                        <span class="ed-etiquette">Matière</span>
                        <select name="matiere" class="ed-champ" id="choix-matiere" required></select>
                    </label>
                </div>
                <?php if ($sansPlace): ?>
                <p class="ed-note"><b><?php echo count($sansPlace); ?> pages</b> ont une mise en page
                   particulière et n’acceptent pas encore d’ajout automatique :
                   <?php echo smEch(implode(', ', array_slice($sansPlace, 0, 4)));
                         echo count($sansPlace) > 4 ? '…' : ''; ?>.
                   Elles n’apparaissent pas dans la liste ci-dessus.</p>
                <?php endif; ?>
            </div>
        </article>

        <article class="ed-brique">
            <header><span class="ed-nom">Comment elle s’appelle</span></header>
            <div class="ed-corps">
                <label class="ed-bloc">
                    <span class="ed-etiquette">Titre affiché en haut de la leçon</span>
                    <input type="text" name="titre" class="ed-champ" id="champ-titre" required
                           maxlength="90" value="<?php echo smEch(smRepris('titre')); ?>"
                           placeholder="Les pronoms personnels">
                </label>
                <p class="ed-note">C’est aussi ce que les élèves liront dans la liste de la
                   matière. La couleur du titre et la mascotte se choisissent juste après,
                   dans l’éditeur de la leçon.</p>
            </div>
        </article>

        <article class="ed-brique">
            <header><span class="ed-nom">Le document</span><span class="ed-compte-brique">obligatoire</span></header>
            <div class="ed-corps">
                <label class="ed-depot" for="depot-pdf">
                    <input type="file" id="depot-pdf" name="document" accept=".pdf,application/pdf" required>
                    <span class="ed-depot-mot">
                        <b>Choisir le document de la leçon</b>
                        <i>PDF &middot; jusqu’à 60 Mo</i>
                    </span>
                    <span class="ed-depot-choix" id="choix-pdf"></span>
                </label>
                <p class="ed-note">Les sons s’ajoutent ensuite, dans l’éditeur : chacun devient
                   un bouton que vous nommez et reliez à sa page.</p>
            </div>
        </article>

    </div>

</main>
</form>

<script>
// La liste des matieres depend de la periode : on la reconstruit sans
// aller-retour au serveur, les combinaisons tiennent dans la page.
(function () {
    var CREABLES = <?php echo json_encode($creables, JSON_UNESCAPED_UNICODE); ?>;
    var VOULUE = <?php echo json_encode(smRepris('matiere'), JSON_UNESCAPED_UNICODE); ?>;
    var periode = document.getElementById('choix-periode');
    var matiere = document.getElementById('choix-matiere');
    if (!periode || !matiere) { return; }

    function remplir() {
        var liste = CREABLES[periode.value] || {};
        var garde = matiere.value || VOULUE;
        matiere.innerHTML = '';
        Object.keys(liste).forEach(function (cle) {
            var o = document.createElement('option');
            o.value = cle;
            o.textContent = liste[cle];
            if (cle === garde) { o.selected = true; }
            matiere.appendChild(o);
        });
    }

    periode.addEventListener('change', remplir);
    remplir();
})();
</script>
<script src="editeur.js?v=20260901-01"></script>
</body>
</html>
