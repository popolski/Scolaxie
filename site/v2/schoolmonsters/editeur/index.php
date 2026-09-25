<?php
// La liste des lecons. C'est la porte de l'editeur : elle doit permettre de
// retrouver une lecon vite, et de voir d'un coup d'oeil celles qu'il reste a
// completer.
require(__DIR__ . '/garde.php');

$lecons = smListeLecons();
// Les pages ecrites avant l'editeur (poesies, grilles...) n'ont pas de donnees.
// Sans cet ajout elles n'apparaitraient nulle part ici, et on ne pourrait ni les
// retrouver ni les supprimer. Elles se gerent depuis la liste de leur matiere.
$dejaVues = array_flip(array_column($lecons, 'chemin'));
foreach (array_keys(smPeriodes()) as $p) {
    foreach (smMatieresCreables($p) as $m => $_) {
        foreach (smLiensMatiere($p, $m) as $suite) {
            foreach ($suite as $l) {
                $c = $p . '/' . $m . '/' . $l['fichier'];
                if ($l['ajoutee'] || isset($dejaVues[$c]) || !is_file(SM_RACINE . '/' . $c)) { continue; }
                $dejaVues[$c] = true;
                $lecons[] = array('chemin' => $c, 'periode' => $p, 'matiere' => $m,
                    'titre' => $l['libelle'] !== '' ? $l['libelle'] : $l['fichier'],
                    'pistes' => 0, 'sans_page' => 0, 'ancienne' => true);
            }
        }
    }
}
usort($lecons, function ($a, $b) {
    return strcmp($a['periode'] . $a['matiere'] . $a['titre'], $b['periode'] . $b['matiere'] . $b['titre']);
});
$parPeriode = array();
$comptePeriode = array();
foreach ($lecons as $l) {
    $parPeriode[$l['periode']][$l['matiere']][] = $l;
    if (!isset($comptePeriode[$l['periode']])) { $comptePeriode[$l['periode']] = 0; }
    $comptePeriode[$l['periode']]++;
}
$total = count($lecons);
$pistes = array_sum(array_column($lecons, 'pistes'));
$sansPage = array_sum(array_column($lecons, 'sans_page'));
$aCompleter = 0;
foreach ($lecons as $l) { if ($l['sans_page'] > 0) { $aCompleter++; } }
// Plus de message d'enregistrement ici : depuis le 01/09/2026 l'editeur ne se
// ferme plus apres un enregistrement, il reste sur la lecon et y affiche la
// confirmation. Plus rien ne renvoie donc vers cette page avec ?enregistre=.
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Éditeur de leçons — School Monsters</title>
    <link rel="stylesheet" href="/galaxie-tokens.css?v=20260912-conformite">
    <link rel="stylesheet" href="editeur.css?v=20260916-menus">
    <link rel="stylesheet" href="bibliotheque.css?v=20260916-menus">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700&display=swap">
</head>
<body class="gx-typo gx-app-schoolmonsters ed-bibliotheque">

<?php require(__DIR__.'/bandeau.php'); ?>

<main id="contenu" class="ed-liste-page gx-largeur-grille">

    <div class="ed-accueil gx-ligne-titre">
        <h1>Vos leçons</h1>
        <p class="ed-intro">Recherchez une leçon pour la modifier. Vos enregistrements sont immédiatement visibles par les élèves.</p>
    </div>
        <div class="ed-bilan">
            <span class="ed-jeton"><b><?php echo $total; ?></b> leçons</span>
            <span class="ed-jeton"><b><?php echo $pistes; ?></b> pistes audio</span>
            <span class="ed-jeton ed-jeton-attention"><b><?php echo $sansPage; ?></b> pistes sans saut de page</span>
        </div>

    <nav class="ed-commandes" aria-label="Actions de la bibliothèque">
        <a class="ed-bouton ed-fort" href="nouvelle.php">+ Créer une leçon</a>
        <a class="ed-bouton" href="menage.php">Sons inutilisés</a>
    </nav>

    <details class="ed-aide">
        <summary>Aide de l’éditeur et limites des fichiers</summary>
        <div class="ed-mode-emploi">
        <div>
            <p class="ed-me-titre">Ce que vous pouvez modifier</p>
            <ul>
                <li>Le <b>titre</b> de la leçon et sa couleur</li>
                <li>La <b>mascotte</b> du bandeau</li>
                <li>Le <b>nom des pistes</b>, leur <b>ordre</b>, et les <b>masquer</b> sans les perdre</li>
                <li>La <b>page du document</b> où chaque piste envoie l’élève</li>
                <li>Le <b>document</b> : en déposer un nouveau, ou reprendre un de ceux du dossier</li>
                <li>L’<b>ordre des leçons</b> et le <b>nom de leur lien</b> sur la page de matière</li>
                <li><b>Revenir à une version précédente</b> d’une leçon, à tout moment</li>
                <li><b>Ajouter des sons</b>, en les déposant ou en reprenant ceux déjà sur le serveur</li>
                <li><b>Créer une leçon</b> entière, avec son document et ses sons</li>
                <li>Les <b>vidéos</b> : en déposer, les nommer, changer leur ordre et le nombre
                    affiché par ligne</li>
                <li>Les <b>images</b> : en déposer, les décrire, les retirer, et régler le nombre
                    affiché par ligne pour chaque série</li>
                <li>Les <b>textes libres</b> : un intertitre ou une légende, avec sa couleur</li>
            </ul>
        </div>
        <div class="ed-me-reste">
            <p class="ed-me-titre">Pas encore possible</p>
            <p>Modifier les titres dont chaque chiffre a sa couleur, comme en numération.
               Ces leçons-là s’ouvrent quand même : tout le reste s’y modifie normalement.</p>
            <p>Déposer une vidéo de plus de <?php echo round(smLimiteEnvoi() / 1048576); ?> Mo :
               le serveur la refuse. Celles-là passent encore par le FTP.</p>
            <p class="ed-me-filet">Une version précédente de chaque leçon est gardée à chaque
               enregistrement. Rien n’est perdu si vous vous trompez.</p>
        </div>
        </div>
    </details>

    <div class="ed-filtres">
        <input type="search" id="chercher" class="ed-recherche" placeholder="Chercher un titre, une matière…"
               aria-label="Chercher une leçon">
        <div class="ed-puces" role="group" aria-label="Filtrer par période">
            <button type="button" class="ed-puce active" data-periode="" aria-pressed="true">Toutes <i><?php echo $total; ?></i></button>
            <?php foreach ($comptePeriode as $p => $n): ?>
            <button type="button" class="ed-puce" data-periode="<?php echo smEch($p); ?>" aria-pressed="false"><?php
                echo smEch(str_replace('_', ' ', $p)); ?> <i><?php echo $n; ?></i></button>
            <?php endforeach; ?>
        </div>
        <label class="ed-coche">
            <input type="checkbox" id="acompleter">
            <span>À compléter seulement <i><?php echo $aCompleter; ?></i></span>
        </label>
        <span class="ed-compte" id="compte" aria-live="polite"></span>
    </div>

    <?php foreach ($parPeriode as $periode => $matieres): ?>
    <section class="ed-periode" data-periode="<?php echo smEch($periode); ?>">
        <h2><?php echo smEch(str_replace('_', ' ', $periode)); ?>
            <i><?php echo $comptePeriode[$periode]; ?> leçons</i></h2>
        <?php foreach ($matieres as $matiere => $liste): ?>
        <details class="ed-matiere" data-matiere>
            <summary><?php echo smEch(ucfirst($matiere)); ?> <span><?php echo count($liste); ?></span></summary>
            <a class="ed-lien-liste" href="matiere.php?p=<?php echo rawurlencode($periode);
                ?>&m=<?php echo rawurlencode($matiere); ?>">Ordre, noms et suppression des leçons de cette matière</a>
            <ul class="ed-cartes">
                <?php foreach ($liste as $l): ?>
                <li data-cherche="<?php echo smEch(mb_strtolower($l['titre'] . ' ' . $matiere . ' ' . str_replace('_', ' ', $l['periode']), 'UTF-8')); ?>"
                    data-manque="<?php echo $l['sans_page'] > 0 ? '1' : '0'; ?>">
                    <?php if (!empty($l['ancienne'])): ?>
                    <a href="matiere.php?p=<?php echo rawurlencode($periode); ?>&m=<?php echo rawurlencode($matiere); ?>"
                       title="Page écrite avant l’éditeur : elle se renomme, se retire ou se supprime depuis la liste de sa matière">
                        <span class="ed-titre-lecon"><?php echo smEch($l['titre']); ?></span>
                        <span class="ed-detail"><span>Page ancienne, sans données</span></span>
                    </a>
                    <?php else: ?>
                    <a href="lecon.php?l=<?php echo rawurlencode($l['chemin']); ?>">
                        <span class="ed-titre-lecon"><?php echo smEch($l['titre']); ?></span>
                        <span class="ed-detail">
                            <span><?php echo $l['pistes']; ?> piste<?php echo $l['pistes'] > 1 ? 's' : ''; ?></span>
                            <?php if ($l['sans_page'] > 0): ?>
                            <b class="ed-manque"><?php echo $l['sans_page']; ?> sans page</b>
                            <?php endif; ?>
                        </span>
                    </a>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </details>
        <?php endforeach; ?>
    </section>
    <?php endforeach; ?>

    <div class="ed-vide" id="rien" hidden>
        <p>Aucune leçon ne correspond à ces critères.</p>
        <button type="button" class="ed-bouton" id="reinitialiser-filtres">Afficher toutes les leçons</button>
    </div>

</main>

<script>
// Filtrage a la frappe : les 191 lecons tiennent dans la page, il n'y a aucune
// raison de faire un aller-retour au serveur pour chercher.
(function () {
    var champ = document.getElementById('chercher');
    var coche = document.getElementById('acompleter');
    var compte = document.getElementById('compte');
    var rien = document.getElementById('rien');
    var puces = [].slice.call(document.querySelectorAll('.ed-puce'));
    var periodes = [].slice.call(document.querySelectorAll('.ed-periode'));
    var periode = '';

    function filtrer() {
        var q = champ.value.trim().toLowerCase();
        var vus = 0;
        periodes.forEach(function (section) {
            var dedans = periode === '' || section.dataset.periode === periode;
            var vusIci = 0;
            section.querySelectorAll('.ed-cartes li').forEach(function (li) {
                var ok = dedans
                      && (q === '' || li.dataset.cherche.indexOf(q) !== -1)
                      && (!coche.checked || li.dataset.manque === '1');
                li.hidden = !ok;
                if (ok) { vusIci++; }
            });
            section.querySelectorAll('[data-matiere]').forEach(function (m) {
                m.hidden = !m.querySelector('.ed-cartes li:not([hidden])');
                // Pendant une recherche, les matieres qui ont un resultat se
                // deplient : repliees, on ne verrait pas ce qu'on cherche.
                if (q !== '' || coche.checked) { m.open = !m.hidden; }
            });
            section.hidden = vusIci === 0;
            vus += vusIci;
        });
        rien.hidden = vus > 0;
        var filtre = q !== '' || coche.checked || periode !== '';
        compte.textContent = filtre ? vus + ' leçon' + (vus > 1 ? 's' : '') : '';
    }

    champ.addEventListener('input', filtrer);
    coche.addEventListener('change', filtrer);
    puces.forEach(function (bouton) {
        bouton.addEventListener('click', function () {
            periode = bouton.dataset.periode;
            puces.forEach(function (autre) {
                autre.classList.toggle('active', autre === bouton);
                autre.setAttribute('aria-pressed', autre === bouton ? 'true' : 'false');
            });
            filtrer();
        });
    });
    document.getElementById('reinitialiser-filtres').addEventListener('click', function () {
        champ.value = '';
        coche.checked = false;
        periode = '';
        puces.forEach(function (bouton) {
            var actif = bouton.dataset.periode === '';
            bouton.classList.toggle('active', actif);
            bouton.setAttribute('aria-pressed', actif ? 'true' : 'false');
        });
        filtrer();
        champ.focus();
    });
})();
</script>
</body>
</html>
