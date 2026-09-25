<?php
// Les deux catalogues parcourent le même référentiel et gardent le niveau en session.
$pageMatieres = $fgPage === 'couverture' ? 'couverture-programme.php' : 'catalogue.php';
?>
<nav class="fg-matieres" aria-label="Matières">
    <?php foreach (fgMatieresCatalogue() as $matiereNavigation) {
        $vitrineNavigation = fgVitrineMatiere($matiereNavigation);
    ?>
    <a class="fg-matiere-lien" href="<?php echo $pageMatieres; ?>?matiere=<?php echo fgH(fgSlugMatiere($matiereNavigation)); ?>" data-matiere="<?php echo fgH(fgSlugMatiere($matiereNavigation)); ?>"<?php echo $matiereNavigation === $matiereChoisie ? ' aria-current="page"' : ''; ?>>
        <span aria-hidden="true"><?php echo gxIcone($vitrineNavigation['icone'] ?? 'picto-defaut'); ?></span>
        <strong><?php echo fgH($matiereNavigation); ?></strong>
    </a>
    <?php } ?>
</nav>
