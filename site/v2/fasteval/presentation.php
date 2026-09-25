<?php

session_start();

if (empty($_SESSION['permission'])) {
    header('location:index.php');
    exit;
}

$estEnseignant = ($_SESSION['role'] == 'enseignant');
$estAdmin = $estEnseignant && !empty($_SESSION['est_admin']);
$prenomAffiche = $_SESSION['prenom_client'] ?? '';

// Lot 6 : la variante "journee" remplace le grand cartouche par une ligne
// de titre courte et une colonne de contexte a droite des actions. Les
// chiffres sont reels, pas des espaces reserves ; si la base ne repond
// pas, le panneau s'efface plutot que d'afficher un "0" qui mentirait.
$nbSaisiesJour = null;
$nbActifs = null;
$nbTotalEleves = null;
$nbSansCode = null;

if ($estEnseignant) {
    try {
        require_once __DIR__ . '/utils/class/class_bdd.php';
        $dbhAccueil = bdd::connexion($_SESSION['bdd']);
        $dbhAccueil->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $idEnseignantAccueil = (int) $_SESSION['id_enseignant'];

        $reqEval = $dbhAccueil->prepare('SELECT COUNT(*) FROM eval_eleves WHERE date_acqui = DATE(NOW()) AND id_enseignant = :id_enseignant');
        $reqEval->bindValue(':id_enseignant', $idEnseignantAccueil, PDO::PARAM_INT);
        $reqEval->execute();
        $nbEvalJour = (int) $reqEval->fetchColumn();

        $reqComp = $dbhAccueil->prepare('SELECT COUNT(*) FROM comp_eleves WHERE date_enr = DATE(NOW()) AND id_enseignant = :id_enseignant');
        $reqComp->bindValue(':id_enseignant', $idEnseignantAccueil, PDO::PARAM_INT);
        $reqComp->execute();
        $nbCompJour = (int) $reqComp->fetchColumn();

        $nbSaisiesJour = $nbEvalJour + $nbCompJour;

        $reqClasse = $dbhAccueil->prepare(
            'SELECT COUNT(*) AS total,
                    SUM(actif = 1) AS actifs,
                    SUM(code_carte IS NULL OR code_carte = \'\') AS sans_code
             FROM classe WHERE id_enseignant = :id_enseignant'
        );
        $reqClasse->bindValue(':id_enseignant', $idEnseignantAccueil, PDO::PARAM_INT);
        $reqClasse->execute();
        $statsClasse = $reqClasse->fetch(PDO::FETCH_ASSOC);
        $nbTotalEleves = (int) ($statsClasse['total'] ?? 0);
        $nbActifs = (int) ($statsClasse['actifs'] ?? 0);
        $nbSansCode = (int) ($statsClasse['sans_code'] ?? 0);

        $dbhAccueil = null;
    } catch (\Throwable $erreurAccueil) {
        // Le panneau de contexte est un a-cote, pas une raison de faire
        // planter l'accueil : on l'efface simplement (valeurs restees null).
        $nbSaisiesJour = null;
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Fast Éval</title>
<link rel="stylesheet" href="/galaxie-tokens.css?v=20260912-accueils-b">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="utils/style-v2.css?v=20260912-conformite">
</head>

<body class="gx-typo gx-app-fasteval v2-fasteval<?php echo $estEnseignant ? ' v2-enseignant' : ''; ?>">
<a class="skip-link" href="#contenu">Aller au contenu</a>

<header class="v2-app-header">
    <img class="logo" src="utils/img/logofasteval.png" alt="Fast Éval">
        <nav class="fil" aria-label="Fil d'Ariane"><a href="/portail/">Portail</a><span aria-hidden="true">›</span><span class="actuel">Accueil</span></nav>
    <?php require_once $_SERVER['DOCUMENT_ROOT'].'/v2/galaxie-icones.php'; require_once $_SERVER['DOCUMENT_ROOT'].'/v2/galaxie-role.php'; echo gxMenuIdentite('Fast Éval'); ?>
</header>

<main id="contenu" class="v2-page gx-largeur-grille<?php echo $estEnseignant ? ' gx-accueil-compose' : ''; ?>">
    <?php if (!$estEnseignant) { ?>

        <section class="v2-page-hero v2-page-hero-centre v2-page-hero-action gx-ligne-titre" aria-labelledby="titre-espace-eleve">
            <span class="v2-page-hero-surtitre">Espace élève</span>
            <h1 id="titre-espace-eleve">Saisir une évaluation</h1>
            <p>Bipe le code de ton évaluation, puis ton résultat.</p>
            <a class="v2-page-hero-bouton" href="saisie/index.php">Commencer <span aria-hidden="true">→</span></a>
        </section>

    <?php } else { ?>

        <!-- Meme cartouche de titre que les autres pages Fast Eval : un seul
             composant, donc une seule taille et une seule graisse. -->
        <section class="v2-page-hero gx-ligne-titre" aria-labelledby="titre-espace-enseignant">
            <span class="v2-page-hero-surtitre">Tableau de bord</span>
            <h1 id="titre-espace-enseignant">Espace enseignant</h1>
            <p>Évaluer, suivre et gérer la classe.</p>
        </section>

        <div class="gx-journee-corps">
            <div class="v2-groupes">
                <section aria-labelledby="fe-evaluer">
                    <h2 class="v2-groupe-titre" id="fe-evaluer">Évaluer et suivre</h2>
                    <nav class="v2-groupe" aria-label="Évaluer et suivre">
                        <a href="saisie/index.php" class="gx-tuile">
                            <span class="ic" aria-hidden="true"><?php echo gxIcone('saisir'); ?></span>
                            <span><b>Saisir une évaluation</b><small>Élève, compétence et résultat</small></span>
                        </a>
                        <a href="bulletin/index.php" class="gx-tuile">
                            <span class="ic" aria-hidden="true"><?php echo gxIcone('bulletin'); ?></span>
                            <span><b>Préparer un bulletin</b><small>Élève, période et observations</small></span>
                        </a>
                        <a class="gx-raccourci" href="saisie/journal.php">Journal des saisies</a>
                    </nav>
                </section>
                <section aria-labelledby="fe-preparer">
                    <h2 class="v2-groupe-titre" id="fe-preparer">Préparer la classe</h2>
                    <nav class="v2-groupe" aria-label="Préparer la classe">
                        <a href="gestion-classe.php" class="gx-tuile gestion">
                            <span class="ic" aria-hidden="true"><?php echo gxIcone('classe'); ?></span>
                            <span><b>Gérer ma classe</b><small>Élèves et informations de la classe</small></span>
                        </a>
                        <details class="fe-referentiels">
                            <summary class="gx-tuile">
                                <span class="ic" aria-hidden="true"><?php echo gxIcone('referentiel'); ?></span>
                                <span><b>Référentiels</b><small>Compétences et connaissances</small></span>
                            </summary>
                            <div class="fe-referentiels-liens">
                                <a class="gx-raccourci" href="liste-competences.php">Liste des compétences</a>
                                <a class="gx-raccourci" href="liste-connaissances.php">Liste des connaissances</a>
                            </div>
                        </details>
                        <a class="gx-raccourci" href="ressources/cartes-eleves.php" target="_blank" rel="noopener">Imprimer les cartes de membre</a>
                    </nav>
                </section>
            </div>

            <div class="gx-colonne-contexte">
                <div class="gx-panneau">
                    <h2>Aujourd'hui dans la classe</h2>
                    <?php if ($nbSaisiesJour !== null) { ?>
                    <div class="gx-chiffre"><b><?php echo $nbSaisiesJour; ?></b><span>saisie<?php echo $nbSaisiesJour > 1 ? 's' : ''; ?> enregistrée<?php echo $nbSaisiesJour > 1 ? 's' : ''; ?></span></div>
                    <div class="gx-chiffre"><b><?php echo $nbActifs; ?></b><span>élève<?php echo $nbActifs > 1 ? 's' : ''; ?> actif<?php echo $nbActifs > 1 ? 's' : ''; ?> sur <?php echo $nbTotalEleves; ?></span></div>
                    <?php if ($nbSansCode > 0) { ?>
                    <div class="gx-chiffre"><b><?php echo $nbSansCode; ?></b><span>élève<?php echo $nbSansCode > 1 ? 's' : ''; ?> sans code carte</span></div>
                    <?php } ?>
                    <?php } else { ?>
                    <p>Le résumé est momentanément indisponible. Vos outils restent accessibles.</p>
                    <a class="gx-raccourci" href="saisie/journal.php">Consulter le journal des saisies</a>
                    <?php } ?>
                </div>
                <?php if ($estAdmin) { ?>
                <details class="gx-panneau fe-administration">
                    <summary>Administration</summary>
                    <a class="gx-raccourci" href="administration-enseignants.php">Comptes et accès enseignants</a>
                </details>
                <?php } ?>
            </div>
        </div>

    <?php } ?>
</main>

<script src="utils/ui-v2.js?v=20260903-01"></script>

</body>
</html>
