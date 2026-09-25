<a class="skip-link" href="#contenu">Aller au contenu</a>
<header class="gx-entete gx-entete-cadree ed-tete">
    <img class="gx-entete-logo" src="../img-140/logo_school_monsters.png?v=20260906-01" alt="School Monsters">
    <nav class="fil" aria-label="Fil d'Ariane">
        <a href="/portail/">Portail</a><span aria-hidden="true">›</span>
        <a href="../enseignant.php">Espace enseignant</a><span aria-hidden="true">›</span>
        <?php if (basename($_SERVER['SCRIPT_FILENAME']) === 'index.php'): ?>
        <span class="actuel" aria-current="page">Vos leçons</span>
        <?php else: ?>
        <a href="index.php">Vos leçons</a><span aria-hidden="true">›</span>
        <span class="actuel" aria-current="page">Éditeur</span>
        <?php endif; ?>
    </nav>
    <?php echo smIdentiteBandeau(); ?>
</header>
