<?php
// Bloc "retour" affiché en haut de chaque fiche, vers la page de la matière.
// Le lien est déduit automatiquement du chemin de la fiche qui inclut ce
// fichier (Période/Matière/fiche.php -> ../Période_Matière.php) : plus
// besoin de le recopier à la main, donc plus de risque qu'un copier-coller
// laisse un lien retour pointant vers la mauvaise matière (bug déjà
// rencontré sur ce site avant cette factorisation).
$periodeRetour = basename(dirname(dirname($_SERVER['SCRIPT_FILENAME'])));
$matiereRetour = basename(dirname($_SERVER['SCRIPT_FILENAME']));
$hrefRetour = '../' . $periodeRetour . '_' . $matiereRetour . '.php';
?>
            <div class="grid_2 ">
                <a href="<?php echo htmlspecialchars($hrefRetour); ?>"><img alt="" src="../../img-140/108.png" /><p class="retour">retour</p>
                </a>
            </div>

<script src="../../javascript/titre-pedagogique.js?v=20260912-conformite" defer></script>
