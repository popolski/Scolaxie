<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/catalogue-competences.php';
require_once __DIR__ . '/includes/seance.php';

$dbCatalogue = bdd::connexion((string)$_SESSION['bdd']);
$catalogue = fgCatalogueCompetences($dbCatalogue);
$matiereChoisie = fgMatiereDepuisSlug((string)($_GET['matiere'] ?? '')) ?? fgMatieresCatalogue()[0];
function fgJeuxSpeciauxDeMatiere(string $matiere): array
{
    static $tous = null;
    if ($tous === null) {
        $tous = require __DIR__ . '/data/jeux-speciaux.php';
    }
    return $tous[$matiere] ?? array();
}

$banques = fgBanques();
$iconesJeux = require __DIR__ . '/data/icones-jeux.php';
$rechercheInitiale = is_string($_GET['recherche'] ?? null) ? mb_substr($_GET['recherche'], 0, 60, 'UTF-8') : '';
$rubriqueInitiale = is_string($_GET['rubrique'] ?? null) ? $_GET['rubrique'] : '';
$rubriquesDisponibles = array_keys(fgBanquesParCategorieDeMatiere($catalogue[$matiereChoisie] ?? array()));
if ($matiereChoisie === 'Mathématiques') {
    $rubriquesDisponibles[] = 'Défis';
}
if (!in_array($rubriqueInitiale, $rubriquesDisponibles, true)) {
    $rubriqueInitiale = '';
}
$libellesMecaniques = array('tri'=>'Classer','paires'=>'Associer','ordre'=>'Ranger','ecoute'=>'Écouter','qcm'=>'Choisir','carte'=>'Explorer','droite'=>'Placer','defi'=>'Défi','regle'=>'Règle','probleme'=>'Problème');
$mecaniquesDisponibles = array('defi' => 'Défi');
foreach ($catalogue as $categories) {
    foreach (fgBanquesJouablesDeMatiere($categories) as $cle) {
        $mecanique = $banques[$cle]['mecanique'];
        $mecaniquesDisponibles[$mecanique] = $libellesMecaniques[$mecanique] ?? ucfirst($mecanique);
    }
}
$iconesMecaniques = array('tri'=>'tri','paires'=>'paires','ordre'=>'ordre','ecoute'=>'ecoute','qcm'=>'qcm','carte'=>'carte','droite'=>'droite','defi'=>'defi');
$iconesSpeciaux = array('compte-est-bon'=>'calcul','boutique'=>'monnaie','horloge'=>'horloge');
$fgTitre = fgEstEnseignant() ? 'Choisir une activité' : 'Les jeux';
$fgPage = 'catalogue';
require __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="/v2/fastgames/assets/catalogue.css?v=20260923-lot4">
<section class="gx-ligne-titre">
    <div><p class="gx-ligne-titre-surtitre">Fast Games · <?php echo fgH(fgNiveauChoisi() === 'tous' ? 'CE1 et CE2' : fgNiveauChoisi()); ?></p>
    <h1 class="gx-ligne-titre-bonjour"><?php echo fgH($fgTitre); ?></h1>
    <p class="gx-ligne-titre-description"><?php echo fgEstEnseignant() ? 'Choisissez une matière ou recherchez une activité dans tout le catalogue.' : 'Choisis une matière ou cherche le jeu qui te plaît.'; ?></p></div>
</section>
<?php if (fgEstEnseignant()) {
    // MEME SELECTEUR QUE LA PAGE « Programme ». Le catalogue affichait le
    // niveau courant dans son surtitre et filtrait les jeux dessus, mais ne
    // permettait pas d'en changer : il fallait passer par une autre page
    // (FG-AUDIT-022). Le choix vit deja en session et fgNoterNiveauDemande()
    // le prend en compte partout, il n'y a rien d'autre a cabler. L'eleve, lui,
    // n'a pas ce choix : son niveau vient de sa classe.
?>
<nav class="fg-choix-niveau" aria-label="Niveau des activités affichées">
    <span>Afficher</span>
    <?php foreach (array('CE1' => 'CE1', 'CE2' => 'CE2', 'tous' => 'Les deux niveaux') as $cleNiveau => $libelleNiveau) { ?>
    <a href="?matiere=<?php echo fgH(fgSlugMatiere($matiereChoisie)); ?>&amp;niveau=<?php echo $cleNiveau; ?>"<?php echo fgNiveauChoisi() === $cleNiveau ? ' class="est-choisi" aria-current="true"' : ''; ?>><?php echo $libelleNiveau; ?></a>
    <?php } ?>
</nav>
<?php } ?>
<?php if (($seanceAReprendre = fgSeanceAReprendre()) !== null) {
    $titreReprise = $banques[$seanceAReprendre['banque'] ?? '']['titre'] ?? null;
    if ($titreReprise === null) {
        foreach (fgJeuxSpeciauxDeMatiere('Mathématiques') as $jeuSpecial) {
            if ($jeuSpecial['special'] === ($seanceAReprendre['jeu'] ?? '')) {
                $titreReprise = $jeuSpecial['titre'];
                break;
            }
        }
    }
    $questionReprise = count($seanceAReprendre['reponses'] ?? array()) + 1;
?>
<a class="mj-reprendre" href="jeu.php?reprendre=1<?php echo fgSuffixeApercu('&amp;'); ?>"><span aria-hidden="true">↺</span><span><strong><?php echo fgH($titreReprise ?? 'Reprendre ma partie'); ?></strong><small><?php echo fgH('Question ' . $questionReprise . ' sur ' . (int)$seanceAReprendre['total']); ?></small></span><b aria-hidden="true">→</b></a>
<?php } ?>
<?php if (!empty($_GET['indisponible'])) { ?>
<div class="fg-message-info" role="status"><strong>Ce mini-jeu n’est plus disponible.</strong><span>Choisissez une autre activité.</span></div>
<?php } ?>
<div class="fg-catalogue">
<?php require __DIR__ . '/includes/navigation-matieres.php'; ?>
<div class="fg-catalogue-contenu">
<form class="mj-outils" role="search" method="get">
    <input type="hidden" name="matiere" value="<?php echo fgH(fgSlugMatiere($matiereChoisie)); ?>">
    <label for="rechercheJeux">Rechercher dans toutes les matières<input id="rechercheJeux" name="recherche" type="search" maxlength="60" value="<?php echo fgH($rechercheInitiale); ?>" placeholder="Un jeu, une notion…" autocomplete="off"></label>
    <details class="fg-filtre-type"><summary>Type de jeu</summary><label for="filtreMecanique">Type de jeu<select id="filtreMecanique"><option value="">Tous les types</option><?php foreach ($mecaniquesDisponibles as $cle=>$libelle) { ?><option value="<?php echo fgH($cle); ?>"><?php echo fgH($libelle); ?></option><?php } ?></select></label></details>
    <p class="mj-resultats" id="resultatsJeux" aria-live="polite"></p>
</form>
<?php foreach (fgMatieresCatalogue() as $matiere) {
    $vitrine = fgVitrineMatiere($matiere);
    $groupesBanques = fgBanquesParCategorieDeMatiere($catalogue[$matiere] ?? array());
    $jeuxSpeciaux = fgJeuxSpeciauxDeMatiere($matiere);
?>
<section class="fg-catalogue-matiere" data-matiere="<?php echo fgH(fgSlugMatiere($matiere)); ?>"<?php echo $matiere !== $matiereChoisie ? ' hidden' : ''; ?>>
    <h2><?php echo fgH($matiere); ?></h2>
    <nav class="fg-rubriques" aria-label="Rubriques de <?php echo fgH($matiere); ?>">
        <button type="button" data-categorie="" aria-pressed="<?php echo $rubriqueInitiale === '' ? 'true' : 'false'; ?>">Tout</button>
        <?php if ($jeuxSpeciaux) { ?><button type="button" data-categorie="Défis" aria-pressed="<?php echo $matiere === $matiereChoisie && $rubriqueInitiale === 'Défis' ? 'true' : 'false'; ?>">Défis</button><?php } ?>
        <?php foreach ($groupesBanques as $categorie=>$cles) { ?><button type="button" data-categorie="<?php echo fgH((string)$categorie); ?>" aria-pressed="<?php echo $matiere === $matiereChoisie && $rubriqueInitiale === (string)$categorie ? 'true' : 'false'; ?>"><?php echo fgH(fgLibelleRubriqueEleve((string)$categorie)); ?></button><?php } ?>
    </nav>
<?php if ($jeuxSpeciaux) { ?>
<section class="mj-groupe" data-categorie="Défis">
    <h3 class="mj-groupe-titre"><span aria-hidden="true">⚡</span> Les défis</h3>
    <div class="fg-cartes-jeux">
    <?php foreach ($jeuxSpeciaux as $special) { ?>
        <a class="fg-carte-jeu mj-ton-<?php echo fgH($vitrine['ton']); ?>" data-jeu="special:<?php echo fgH($special['special']); ?>" data-mecanique="defi" data-titre="<?php echo fgH($special['titre'] . ' ' . $special['consigne']); ?>" href="jeu.php?special=<?php echo rawurlencode($special['special']); ?><?php echo fgEstEnseignant() ? '&amp;apercu=eleve' : ''; ?>">
            <span class="fg-carte-jeu-icone" aria-hidden="true"><?php echo gxIcone($iconesSpeciaux[$special['special']] ?? 'defi'); ?></span>
            <span class="fg-carte-jeu-texte">
                <strong><?php echo fgH($special['titre']); ?></strong>
                <small><?php echo fgH($special['consigne']); ?></small>
            </span>
            <b aria-hidden="true">›</b>
        </a>
    <?php } ?>
    </div>
</section>
<?php } ?>
<?php

foreach ($groupesBanques as $categorie => $clesCategorie) {
?>
<section class="mj-groupe" data-categorie="<?php echo fgH((string)$categorie); ?>">
    <h3 class="mj-groupe-titre"><span aria-hidden="true"><?php echo gxIcone(fgPictoCategorie((string)$categorie)); ?></span> <?php echo fgH(fgLibelleRubriqueEleve((string)$categorie)); ?></h3>
    <div class="fg-cartes-jeux">
    <?php foreach ($clesCategorie as $cle) {
        $banque = $banques[$cle];
        $iconeMecanique = $iconesMecaniques[$banque['mecanique']] ?? 'defi';
    ?>
        <a class="fg-carte-jeu mj-ton-<?php echo fgH($vitrine['ton']); ?>" data-jeu="banque:<?php echo fgH($cle); ?>" data-mecanique="<?php echo fgH($banque['mecanique']); ?>" data-titre="<?php echo fgH($banque['titre'] . ' ' . $banque['consigne']); ?>" href="jeu.php?banque=<?php echo rawurlencode($cle); ?><?php echo fgEstEnseignant() ? '&amp;apercu=eleve' : ''; ?>">
            <span class="fg-carte-jeu-icone" aria-hidden="true"><?php echo gxIcone($iconesJeux[$cle] ?? '') ?: gxIcone($iconeMecanique); ?></span>
            <span class="fg-carte-jeu-texte">
                <strong><?php echo fgH($banque['titre']); ?></strong>
                <small><?php echo fgH($banque['consigne']); ?></small>
            </span>
            <b aria-hidden="true">›</b>
        </a>
    <?php } ?>
    </div>
</section>
<?php } ?>
<?php if (!$groupesBanques && !$jeuxSpeciaux) { ?><p class="mj-vide">De nouveaux jeux arrivent bientôt dans cette matière.</p><?php } ?>
</section>
<?php } ?>
<section class="mj-vide-filtre" id="aucunJeuFiltre" hidden><strong id="aucunJeuMessage"></strong><button type="button" class="fg-bouton fg-bouton-secondaire" id="effacerRechercheJeux">Effacer la recherche</button></section>
<noscript><p>Choisissez une matière avec les liens ci-dessus. Activez JavaScript pour rechercher dans toutes les matières.</p></noscript>
</div></div>
<script src="assets/catalogue.js?v=20260923-lot4" defer></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
