<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (!fgEstEnseignant()) {
    header('Location: index.php');
    exit;
}
require_once __DIR__ . '/includes/catalogue-competences.php';

$dbCatalogue = bdd::connexion((string)$_SESSION['bdd']);
$catalogue = fgCatalogueCompetences($dbCatalogue);

$matiereChoisie = fgMatiereDepuisSlug((string)($_GET['matiere'] ?? '')) ?? fgMatieresCatalogue()[0];
$fgTitre = 'Programme';
$fgPage = 'couverture';
require __DIR__ . '/includes/header.php';
?>
<section class="gx-ligne-titre"><div><p class="gx-ligne-titre-surtitre">Programme 2026/2027</p><h1 class="gx-ligne-titre-bonjour">Les compétences et leurs activités</h1><p class="gx-ligne-titre-description">Retrouvez les activités disponibles pour chaque compétence de votre référentiel.</p></div></section>
<nav class="fg-choix-niveau" aria-label="Niveau des compétences affichées">
    <span>Afficher</span>
    <?php foreach (array('CE1'=>'CE1','CE2'=>'CE2','tous'=>'Les deux niveaux') as $cle=>$libelle) { ?>
    <a href="?matiere=<?php echo fgH(fgSlugMatiere($matiereChoisie)); ?>&amp;niveau=<?php echo $cle; ?>"<?php echo fgNiveauChoisi() === $cle ? ' class="est-choisi" aria-current="true"' : ''; ?>><?php echo $libelle; ?></a>
    <?php } ?>
</nav>
<?php if (!empty($_GET['indisponible'])) { ?><div class="fg-message-info" role="status"><strong>Ce mini-jeu n’est plus disponible.</strong><span>Choisissez une autre compétence.</span></div><?php } ?>
<div class="fg-catalogue fg-programme">
<?php require __DIR__ . '/includes/navigation-matieres.php'; ?>
<section class="fg-catalogue-contenu">
    <h2><?php echo fgH($matiereChoisie); ?></h2>
    <?php $categories = $catalogue[$matiereChoisie] ?? array(); $resume = fgResumeMatiere($categories); ?>
    <p class="fg-note"><?php echo (int)$resume['jouables']; ?> compétences avec une activité sur <?php echo (int)$resume['total']; ?>.</p>
    <?php if (!$categories) { ?><p class="mj-vide">Aucune compétence pour ce niveau dans le référentiel actuel.</p><?php } ?>
    <?php foreach ($categories as $nomCategorie=>$lignes) { ?>
    <section class="cc-categorie">
        <h3><?php echo fgH($nomCategorie); ?></h3>
        <div class="cc-competences">
        <?php foreach ($lignes as $c) {
            $jouable = $c['etat'] === 'jouable';
            $statut = $jouable ? 'Activité disponible' : ($c['etat'] === 'bientot' ? 'À venir' : 'Hors écran');
        ?>
            <div class="cc-ligne">
                <div><strong><?php echo fgH($c['libelle']); ?></strong><?php if ($c['raison']) { ?><small><?php echo fgH($c['raison']); ?></small><?php } ?></div>
                <span class="cc-statut<?php echo $jouable ? ' cc-statut-jouable' : ''; ?>"><?php echo $statut; ?></span>
                <?php if ($jouable) {
                    $lienJeu = $c['special'] !== '' ? 'jeu.php?special='.rawurlencode($c['special']) : 'jeu.php?banque='.rawurlencode((string)$c['banque']);
                ?><a class="fg-bouton fg-bouton-secondaire" href="<?php echo fgH($lienJeu); ?>&amp;apercu=eleve" aria-label="Ouvrir l’activité : <?php echo fgH($c['libelle']); ?>">Ouvrir →</a><?php } ?>
            </div>
        <?php } ?>
        </div>
    </section>
    <?php } ?>
    <p class="fg-note-referentiel">Les intitulés viennent directement de Fast Éval. <a href="catalogue-generateurs.php">Accéder aux générateurs historiques →</a></p>
</section></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
