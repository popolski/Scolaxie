<?php

session_start();
require __DIR__.'/utils/journal-donnees.php';

if (empty($_SESSION['jeton_journal'])) {
    $_SESSION['jeton_journal'] = bin2hex(random_bytes(24));
}

function h($valeur)
{
    return htmlspecialchars((string)$valeur, ENT_QUOTES, 'UTF-8');
}

function classeNiveauNote($note)
{
    $valeur = (float)str_replace(',', '.', (string)$note);
    if ($valeur < 5) { return 'non-atteint'; }
    if ($valeur < 8) { return 'partiel'; }
    return 'atteint';
}

function libelleResultatCompetence($resultat)
{
    $cle = strtolower(trim((string)$resultat));
    $libelles = array(
        'nonatteint' => array('NA', 'Non atteint', 'non-atteint'),
        'patteint' => array('PA', 'Partiellement atteint', 'partiel'),
        'atteint' => array('A', 'Atteint', 'atteint'),
        'depasse' => array('D', 'Dépassé', 'depasse')
    );
    return $libelles[$cle] ?? array((string)$resultat, (string)$resultat, 'neutre');
}

$mois = array(1=>'janvier', 2=>'février', 3=>'mars', 4=>'avril', 5=>'mai', 6=>'juin', 7=>'juillet', 8=>'août', 9=>'septembre', 10=>'octobre', 11=>'novembre', 12=>'décembre');
$dateJournal = date('j').' '.$mois[(int)date('n')].' '.date('Y');
$total = count($journalEval) + count($journalComp);
$messageEtat = '';
if (($_GET['etat'] ?? '') === 'supprime') {
    $messageEtat = 'La saisie a bien été supprimée du journal.';
} elseif (($_GET['etat'] ?? '') === 'invalide') {
    $messageEtat = 'La suppression n’a pas pu être effectuée.';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Journal des saisies - Fast Éval</title>
<link rel="stylesheet" href="/galaxie-tokens.css?v=20260912-conformite">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="../utils/style-v2.css?v=20260912-conformite">
<link rel="stylesheet" href="utils/journal-v2.css?v=20260909-titres">
</head>
<body class="gx-typo gx-app-fasteval v2-fasteval">
<a class="skip-link" href="#contenu">Aller au contenu</a>

<header class="v2-app-header">
    <img class="logo" src="utils/logofasteval.png" alt="Fast Éval">
        <nav class="fil" aria-label="Fil d’Ariane"><a href="/portail/">Portail</a><span aria-hidden="true">›</span><a href="../presentation.php">Accueil</a><span aria-hidden="true">›</span><a href="index.php">Saisie</a><span aria-hidden="true">›</span><span class="actuel">Journal</span></nav>
    <?php require_once $_SERVER['DOCUMENT_ROOT'].'/v2/galaxie-role.php'; echo gxMenuIdentite('Fast Éval', array(
        array('label' => 'Revenir à la saisie', 'href' => 'index.php'),
    )); ?>
</header>

<main id="contenu" class="v2-page journal-page gx-largeur-grille">
    <section class="journal-intro gx-ligne-titre" aria-labelledby="titre-journal">
        <div>
            <p class="journal-sur-titre">Saisies du jour</p>
            <h1 id="titre-journal">Journal des évaluations</h1>
            <p>Retrouvez les résultats enregistrés le <?php echo h($dateJournal); ?>.</p>
        </div>
        <div class="journal-logo" aria-hidden="true">
            <img src="utils/logofasteval.png" alt="">
        </div>
    </section>

    <?php if ($messageEtat !== '') { ?>
    <p class="journal-message <?php echo (($_GET['etat'] ?? '') === 'supprime') ? 'succes' : 'erreur'; ?>" role="status"><?php echo h($messageEtat); ?></p>
    <?php } ?>

    <div class="journal-barre-actions">
        <div class="journal-resume" aria-label="Résumé du journal">
            <span><strong><?php echo $total; ?></strong> saisie<?php echo $total > 1 ? 's' : ''; ?></span>
            <span><strong><?php echo count($journalEval); ?></strong> connaissance<?php echo count($journalEval) > 1 ? 's' : ''; ?></span>
            <span><strong><?php echo count($journalComp); ?></strong> compétence<?php echo count($journalComp) > 1 ? 's' : ''; ?></span>
        </div>
        <div class="journal-actions">
            <a class="journal-bouton secondaire" href="journal-pdf.php" target="_blank" rel="noopener">
                <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M7 3h7l4 4v4M7 3v6h7V3M6 14h12v7H6zM14 3v4h4"/></svg>
                Ouvrir le PDF
            </a>
            <a class="journal-bouton principal" href="index.php">
                <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M5 12h14M11 6l-6 6 6 6"/></svg>
                Revenir à la saisie
            </a>
        </div>
    </div>

    <section class="journal-section connaissances" aria-labelledby="titre-connaissances">
        <header>
            <div class="journal-icone" aria-hidden="true">10</div>
            <div>
                <p class="journal-sur-titre">Notes chiffrées</p>
                <h2 id="titre-connaissances">Connaissances</h2>
            </div>
            <span class="journal-compteur"><?php echo count($journalEval); ?></span>
        </header>

        <?php if (empty($journalEval)) { ?>
        <div class="gx-etat-vide">
            <span class="gx-etat-vide-icone" aria-hidden="true">✓</span>
            <div><h2>Aucune connaissance enregistrée aujourd’hui.</h2><p>Les prochaines saisies apparaîtront ici.</p></div>
            <a class="gx-bouton gx-bouton-principal" href="index.php">Faire une saisie</a>
        </div>
        <?php } else { ?>
        <div class="journal-tableau-wrap">
            <table class="journal-tableau">
                <thead><tr><th>Élève</th><th>Connaissance évaluée</th><th class="col-resultat">Résultat</th><?php if (!$estEleve) { ?><th><span class="sr-only">Actions</span></th><?php } ?></tr></thead>
                <tbody>
                <?php foreach ($journalEval as $ligne) { ?>
                    <tr>
                        <td data-label="Élève"><strong><?php echo h($ligne['prenom'].' '.$ligne['nom']); ?></strong></td>
                        <td data-label="Connaissance"><?php echo h($ligne['intitule']); ?></td>
                        <td class="col-resultat" data-label="Résultat"><span class="journal-note <?php echo classeNiveauNote($ligne['resultat']); ?>"><?php echo h($ligne['resultat']); ?><small>/10</small></span></td>
                        <?php if (!$estEleve) { ?><td class="journal-cellule-action">
                            <form method="post" action="delete.php" data-confirmation="Supprimer cette saisie de connaissance ?">
                                <input type="hidden" name="jeton" value="<?php echo h($_SESSION['jeton_journal']); ?>">
                                <input type="hidden" name="type" value="eval">
                                <input type="hidden" name="id" value="<?php echo (int)$ligne['id_test']; ?>">
                                <button class="journal-supprimer" type="submit" aria-label="Supprimer la saisie de <?php echo h($ligne['prenom'].' '.$ligne['nom']); ?>">
                                    <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11v5"/></svg>
                                    Supprimer
                                </button>
                            </form>
                        </td><?php } ?>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
        <?php } ?>
    </section>

    <section class="journal-section competences" aria-labelledby="titre-competences">
        <header>
            <div class="journal-icone" aria-hidden="true">✓</div>
            <div>
                <p class="journal-sur-titre">Niveaux de maîtrise</p>
                <h2 id="titre-competences">Compétences</h2>
            </div>
            <span class="journal-compteur"><?php echo count($journalComp); ?></span>
        </header>

        <?php if (empty($journalComp)) { ?>
        <div class="gx-etat-vide">
            <span class="gx-etat-vide-icone" aria-hidden="true">✓</span>
            <div><h2>Aucune compétence enregistrée aujourd’hui.</h2><p>Les prochaines saisies apparaîtront ici.</p></div>
            <a class="gx-bouton gx-bouton-principal" href="index.php">Faire une saisie</a>
        </div>
        <?php } else { ?>
        <div class="journal-tableau-wrap">
            <table class="journal-tableau">
                <thead><tr><th>Élève</th><th>Compétence évaluée</th><th class="col-resultat">Résultat</th><?php if (!$estEleve) { ?><th><span class="sr-only">Actions</span></th><?php } ?></tr></thead>
                <tbody>
                <?php foreach ($journalComp as $ligne) { $resultat = libelleResultatCompetence($ligne['resultat']); ?>
                    <tr>
                        <td data-label="Élève"><strong><?php echo h($ligne['prenom'].' '.$ligne['nom']); ?></strong></td>
                        <td data-label="Compétence"><?php echo h($ligne['intitule']); ?></td>
                        <td class="col-resultat" data-label="Résultat"><span class="journal-maitrise <?php echo h($resultat[2]); ?>"><abbr title="<?php echo h($resultat[1]); ?>"><?php echo h($resultat[0]); ?></abbr><span><?php echo h($resultat[1]); ?></span></span></td>
                        <?php if (!$estEleve) { ?><td class="journal-cellule-action">
                            <form method="post" action="delete.php" data-confirmation="Supprimer cette saisie de compétence ?">
                                <input type="hidden" name="jeton" value="<?php echo h($_SESSION['jeton_journal']); ?>">
                                <input type="hidden" name="type" value="comp">
                                <input type="hidden" name="id" value="<?php echo (int)$ligne['id_test']; ?>">
                                <button class="journal-supprimer" type="submit" aria-label="Supprimer la saisie de <?php echo h($ligne['prenom'].' '.$ligne['nom']); ?>">
                                    <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11v5"/></svg>
                                    Supprimer
                                </button>
                            </form>
                        </td><?php } ?>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
        <?php } ?>
    </section>
</main>

<script src="utils/journal-v2.js?v=20260903-01"></script>
</body>
</html>
