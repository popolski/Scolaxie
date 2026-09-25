<?php
if (!isset($typeBulletin, $pdfAction) || !in_array($typeBulletin, array('comp', 'eval'), true)) {
    http_response_code(404);
    exit();
}

session_start();

if (empty($_SESSION['permission'])) {
    header('location:../index.php');
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'enseignant') {
    header('location:../presentation.php');
    exit();
}

foreach (array('date_debut', 'date_fin', 'eleve', 'id_enseignant', 'bdd') as $cleParcours) {
    if (!isset($_SESSION[$cleParcours]) || $_SESSION[$cleParcours] === '') {
        header('location:index.php');
        exit();
    }
}

require_once __DIR__.'/../../utils/class/class_bdd.php';

$debutPeriodeFr = date('d/m/Y', strtotime($_SESSION['date_debut']));
$finPeriodeFr = date('d/m/Y', strtotime($_SESSION['date_fin']));
$dbh = bdd::connexion($_SESSION['bdd']);
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
$req = $dbh->prepare('SELECT nom, prenom FROM classe WHERE id_eleve=:id_eleve AND id_enseignant=:id_enseignant');
$req->bindParam(':id_eleve', $_SESSION['eleve']);
$req->bindParam(':id_enseignant', $_SESSION['id_enseignant']);
$req->execute();
$eleveInfo = $req->fetch(PDO::FETCH_ASSOC);
$req->closeCursor();

if (!$eleveInfo) {
    header('location:index.php');
    exit();
}

$estCompetences = $typeBulletin === 'comp';
$nomEleve = trim(($eleveInfo['prenom'] ?? '').' '.mb_strtoupper($eleveInfo['nom'] ?? '', 'UTF-8'));
$nomCompte = trim(($_SESSION['prenom_client'] ?? '').' '.mb_strtoupper($_SESSION['nom_client'] ?? '', 'UTF-8'));
$titrePage = $estCompetences ? 'Observation du livret de compétences' : 'Observations du livret de connaissances';
$titrePrincipal = $estCompetences ? 'Ajouter une observation' : 'Ajouter les observations';
$typeAffiche = $estCompetences ? 'Compétences' : 'Connaissances';
$modeleAffiche = (isset($_SESSION['modele_bulletin']) && $_SESSION['modele_bulletin'] === 'charte')
    ? 'Charte Fast Éval'
    : 'Modèle actuel';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $titrePage; ?></title>
    <link rel="stylesheet" href="/galaxie-tokens.css?v=20260912-conformite">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="../utils/style-v2.css?v=20260912-conformite">
    <link rel="stylesheet" href="utils/observations-v2.css?v=20260909-fg3">
</head>
<body class="gx-typo gx-app-fasteval v2-fasteval v2-observations">
<a class="skip-link" href="#contenu">Aller au contenu</a>

<header class="v2-app-header">
    <img class="logo" src="../utils/img/logofasteval.png" alt="Fast Éval">
        <nav class="fil bulletin-fil" aria-label="Fil d'Ariane">
        <a href="/portail/">Portail</a><span aria-hidden="true">›</span>
        <a href="../presentation.php">Accueil</a><span aria-hidden="true">›</span>
        <a href="index.php">Livret scolaire</a><span aria-hidden="true">›</span>
        <span class="actuel">Observation</span>
    </nav>
    <?php require_once $_SERVER['DOCUMENT_ROOT'].'/v2/galaxie-role.php'; echo gxMenuIdentite('Fast Éval'); ?>
</header>

<main id="contenu" class="v2-page bulletin-observations-page gx-largeur-grille">
    <section class="v2-page-hero gx-ligne-titre" aria-labelledby="titre-page">
        <span class="v2-page-hero-surtitre">Bulletin scolaire · Étape finale</span>
        <h1 id="titre-page"><?php echo $titrePrincipal; ?></h1>
        <p><?php echo $estCompetences
            ? 'Rédigez une appréciation générale avant de générer le bulletin de compétences.'
            : 'Rédigez les appréciations avant de générer le bulletin de connaissances.'; ?></p>
    </section>

    <dl class="bulletin-contexte" aria-label="Récapitulatif du livret">
        <div><dt>Élève</dt><dd><?php echo htmlspecialchars($nomEleve, ENT_QUOTES, 'UTF-8'); ?></dd></div>
        <div><dt>Contenu</dt><dd><?php echo $typeAffiche; ?></dd></div>
        <div><dt>Présentation</dt><dd><?php echo $modeleAffiche; ?></dd></div>
        <div><dt>Période</dt><dd><?php echo htmlspecialchars($debutPeriodeFr.' au '.$finPeriodeFr, ENT_QUOTES, 'UTF-8'); ?></dd></div>
    </dl>

    <form method="post" action="<?php echo htmlspecialchars($pdfAction, ENT_QUOTES, 'UTF-8'); ?>" class="observation-v2-card">
        <div class="observation-v2-entete">
            <div>
                <h2><?php echo $estCompetences ? 'Observation générale' : 'Observations par domaine'; ?></h2>
                <p><?php echo $estCompetences
                    ? 'Ce texte apparaîtra dans le livret de compétences.'
                    : 'Chaque texte apparaîtra dans la partie correspondante du livret.'; ?></p>
            </div>
            <span class="observation-facultative">Facultatif</span>
        </div>

        <?php if ($estCompetences) { ?>
            <label class="sr-only" for="observations">Observation générale</label>
            <textarea name="observations" id="observations" rows="12" placeholder="Écrivez votre observation ici..."></textarea>
            <div class="symboles-v2" role="group" aria-label="Insérer un caractère dans l'observation">
                <span>Caractères utiles</span>
                <?php foreach (array('≠', '≤', '≥', '×', '÷', '±', 'œ', 'Œ', '«', '»') as $symbole) { ?>
                    <button type="button" data-cible="observations" data-symbole="<?php echo $symbole; ?>" aria-label="Insérer <?php echo $symbole; ?>"><?php echo $symbole; ?></button>
                <?php } ?>
            </div>
        <?php } else { ?>
            <div class="observation-v2-grille">
                <section class="observation-v2-champ">
                    <div class="observation-v2-champ-entete">
                        <label for="observations_francais">Français</label>
                        <span data-compteur-pour="observations_francais">0 / 340</span>
                    </div>
                    <p>Observation générale pour les connaissances en français.</p>
                    <textarea name="francais" id="observations_francais" rows="8" maxlength="340" placeholder="Écrivez votre observation ici..."></textarea>
                    <div class="symboles-v2" role="group" aria-label="Insérer un caractère dans l'observation de français">
                        <span>Caractères utiles</span>
                        <?php foreach (array('≠', '≤', '≥', 'œ', 'Œ', '«', '»') as $symbole) { ?>
                            <button type="button" data-cible="observations_francais" data-symbole="<?php echo $symbole; ?>" aria-label="Insérer <?php echo $symbole; ?>"><?php echo $symbole; ?></button>
                        <?php } ?>
                    </div>
                </section>
                <section class="observation-v2-champ">
                    <div class="observation-v2-champ-entete">
                        <label for="observations_mat">Mathématiques</label>
                        <span data-compteur-pour="observations_mat">0 / 340</span>
                    </div>
                    <p>Observation générale pour les connaissances en mathématiques.</p>
                    <textarea name="mat" id="observations_mat" rows="8" maxlength="340" placeholder="Écrivez votre observation ici..."></textarea>
                    <div class="symboles-v2" role="group" aria-label="Insérer un caractère dans l'observation de mathématiques">
                        <span>Caractères utiles</span>
                        <?php foreach (array('≠', '≤', '≥', '×', '÷', '±') as $symbole) { ?>
                            <button type="button" data-cible="observations_mat" data-symbole="<?php echo $symbole; ?>" aria-label="Insérer <?php echo $symbole; ?>"><?php echo $symbole; ?></button>
                        <?php } ?>
                    </div>
                </section>
            </div>
        <?php } ?>

        <div class="observation-v2-actions">
            <a href="index.php" class="bouton-secondaire">Modifier les choix</a>
            <button type="submit" class="bouton-principal" name="choix" value="Voir Bilan des acquis avec enregistrement des Observations">Générer le bulletin</button>
        </div>
    </form>
</main>

<script src="../utils/ui-v2.js?v=20260903-01"></script>
<script src="utils/observations-v2.js?v=20260903-01"></script>
</body>
</html>
