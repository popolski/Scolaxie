<?php
require_once __DIR__.'/rapport-stockage.php';
require_once __DIR__.'/mecaniques.php';
$db = bdd::connexion((string)$_SESSION['bdd']);
$enseignant = (int)($_SESSION['id_enseignant'] ?? 0);
$estEnseignant = fgEstEnseignant();
$eleve = $estEnseignant ? (int)($_GET['eleve'] ?? 0) : (int)($_SESSION['id_eleve'] ?? 0);
$choix = (string)($_GET['periode'] ?? 'annee');
$erreur = '';
$message = '';
$rapport = null;
$periodes = array();
$commentaire = array('commentaire' => '', 'revision' => 0);
$disponible = false;
$eleves = array();
try {
    if ($estEnseignant) {
        $q = $db->prepare('SELECT id_eleve,prenom,nom FROM classe WHERE id_enseignant=? ORDER BY prenom,nom');
        $q->execute(array($enseignant));
        $eleves = $q->fetchAll(PDO::FETCH_ASSOC);
    }
    $disponible = fgStockageRapportDisponible($db);
    $periodes = $disponible ? fgPeriodesRapport($db, $enseignant) : array();
    $periode = fgChoisirPeriodeRapport($periodes, $choix, new DateTimeImmutable('now', new DateTimeZone('Europe/Paris')));
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string)($_POST['action'] ?? '');
        // La suppression ne touche que fastgames_resultats : elle ne dépend pas des tables périodes/commentaires.
        if (!$estEnseignant || ($action !== 'supprimer-jeu' && !$disponible) || !hash_equals((string)$_SESSION['fastgames_csrf'], (string)($_POST['csrf'] ?? ''))) throw new DomainException('Action non autorisée ou session expirée.');
        $retour = array('eleve'=>$eleve,'periode'=>$choix,'enregistre'=>1);
        if ($action === 'periode') {
            fgAjouterPeriodeRapport($db, $enseignant, (string)($_POST['libelle'] ?? ''), (string)($_POST['debut'] ?? ''), (string)($_POST['fin'] ?? ''));
        } elseif ($action === 'commentaire') {
            fgSauverCommentaireRapport($db, $enseignant, $eleve, $periode, (string)($_POST['commentaire'] ?? ''), (int)($_POST['revision'] ?? 0));
        } elseif ($action === 'supprimer-jeu') {
            $retour = array('eleve'=>$eleve,'periode'=>$choix,'supprime'=>fgSupprimerPartiesRapport($db, $enseignant, $eleve, $periode, (string)($_POST['jeu'] ?? ''), (int)($_POST['parties'] ?? 0), fgBanques()));
        } else throw new InvalidArgumentException('Action inconnue.');
        header('Location: passeport.php?'.http_build_query($retour), true, 303);
        exit;
    }
    if ($eleve) {
        $identite = fgEleveRapport($db, $enseignant, $eleve);
        $rapport = fgLireRapport($db, $enseignant, $eleve, $periode, fgBanques());
        if ($disponible) $commentaire = fgCommentaireRapport($db, $enseignant, $eleve, $periode);
    }
} catch (DomainException | InvalidArgumentException $e) {
    http_response_code(400); $erreur = $e->getMessage();
} catch (PDOException $e) {
    http_response_code(503); $erreur = 'Le rapport est temporairement indisponible. Vos données n’ont pas été modifiées.';
}
// Après un conflit de saisie, conserver le texte soumis et recharger la lecture
// autorisée ; l'enseignant peut comparer sans perdre son travail.
if ($erreur && $eleve && isset($periode) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $identite = fgEleveRapport($db, $enseignant, $eleve);
        $rapport = fgLireRapport($db, $enseignant, $eleve, $periode, fgBanques());
        if ($disponible) $commentaire = fgCommentaireRapport($db, $enseignant, $eleve, $periode);
    } catch (DomainException | PDOException $e) { $rapport = null; }
}
if (($_GET['format'] ?? '') === 'pdf') {
    if (!$rapport) { http_response_code(400); echo fgH($erreur ?: 'Sélectionnez un élève.'); exit; }
    require_once __DIR__.'/rapport-pdf.php';
    $pdf = new PdfRapportFastGames($identite, $periode, (string)($_SESSION['classe_client'] ?? ''), $rapport, $commentaire['commentaire']);
    $niveauErreurs = error_reporting();
    error_reporting($niveauErreurs & ~E_DEPRECATED);
    try { $pdf->Output('I', 'rapport-fastgames.pdf'); }
    finally { error_reporting($niveauErreurs); }
    exit;
}
$fgTitre = 'Rapport individuel'; $fgPage = 'passeport';
require __DIR__.'/header.php';
?>
<link rel="stylesheet" href="assets/rapport.css?v=20260915-1">
<div class="gx-ligne-titre"><div><p class="gx-ligne-titre-surtitre">Fast Games · réussites observées</p><h1 class="gx-ligne-titre-bonjour">Rapport individuel</h1><p class="gx-ligne-titre-description">Les activités pratiquées et leurs résultats. Ce rapport ne valide aucune acquisition scolaire.</p></div></div>
<?php if ($erreur) { ?><p role="alert"><?php echo fgH($erreur); ?></p><?php } ?>
<?php if (isset($_GET['enregistre'])) { ?><p role="status">Enregistrement effectué.</p><?php } ?>
<?php if (isset($_GET['supprime'])) { ?><p role="status"><?php echo (int)$_GET['supprime']; ?> partie(s) supprimée(s) définitivement.</p><?php } ?>
<form method="get" class="admin-section fg-rapport-filtres">
<?php if ($estEnseignant) { ?><label class="gx-champ ed-etiquette">Élève<select name="eleve" required><option value="">Sélectionnez un élève</option><?php foreach ($eleves as $e) { ?><option value="<?php echo (int)$e['id_eleve']; ?>"<?php if ((int)$e['id_eleve'] === $eleve) echo ' selected'; ?>><?php echo fgH($e['prenom'].' '.$e['nom']); ?></option><?php } ?></select></label><?php } ?>
<label class="gx-champ ed-etiquette">Période scolaire<select name="periode"><option value="annee"<?php if ($choix==='annee') echo ' selected'; ?>>Année complète</option><option value="courante"<?php if ($choix==='courante') echo ' selected'; ?>>Période courante</option><?php foreach ($periodes as $p) { ?><option value="<?php echo (int)$p['id_periode']; ?>"<?php if ($choix===(string)$p['id_periode']) echo ' selected'; ?>><?php echo fgH($p['libelle'].' · '.$p['date_debut'].' au '.$p['date_fin']); ?></option><?php } ?></select></label>
<button class="fg-bouton fg-bouton-principal">Afficher</button></form>
<?php if (!$disponible) { ?><p>La configuration des périodes et les commentaires ne sont pas encore disponibles. L’année complète reste consultable.</p><?php } ?>
<?php if ($rapport) { ?>
<div class="fg-rapport-document">
<section class="admin-section"><h2><?php echo fgH($identite['prenom'].' '.$identite['nom']); ?></h2><p>Classe : <?php echo fgH($_SESSION['classe_client'] ?? 'Non renseignée'); ?> · Niveau individuel : non renseigné</p><p><?php echo fgH($periode['libelle'].' · du '.$periode['debut'].' au '.$periode['fin']); ?> · Édition : <?php echo date('d/m/Y'); ?></p><a class="fg-bouton" href="passeport.php?<?php echo fgH(http_build_query(array('eleve'=>$eleve,'periode'=>$choix,'format'=>'pdf'))); ?>" target="_blank" rel="noopener">Ouvrir le PDF pour imprimer</a> <?php if ($estEnseignant) { ?><a class="fg-bouton fg-bouton-secondaire" href="passeport.php?<?php echo fgH(http_build_query(array('eleve'=>$eleve,'periode'=>$choix,'vue'=>'passeport'))); ?>">Voir son passeport</a><?php } ?></section>
<section class="admin-section"><h2>Activité sur la période</h2><p><?php echo $rapport['parties']; ?> partie(s) · <?php echo count($rapport['jeux']); ?> jeu(x) · <?php echo $rapport['jours_actifs']; ?> jour(s) d’activité</p><p>Peu de données</p><?php if (!$rapport['parties']) { ?><p>Aucune partie enregistrée sur cette période.</p><?php } else { ?><p>Première partie : <?php echo fgH($rapport['premiere']); ?> · Dernière partie : <?php echo fgH($rapport['derniere']); ?></p><?php } ?></section>
<?php if ($rapport['tendance'] !== null) { ?><section class="admin-section"><h2>Évolution</h2><p><?php echo fgH($rapport['tendance']); ?></p></section><?php } ?>
<section class="admin-section"><h2>Compétences explicitement associées</h2><?php if (!$rapport['competences']) { ?><p>Aucune compétence qualifiée sur cette période.</p><?php } ?>
<?php foreach ($rapport['competences'] as $c) { ?><h3><?php echo fgH($c['reference']['libelle']); ?></h3><p><?php echo fgH($c['reference']['matiere'].' · '.$c['reference']['categorie']); ?> · <?php echo $c['parties']; ?> partie(s) · Peu de données</p><p>Jeux pratiqués : <?php echo fgH(implode(', ', $c['jeux'])); ?>. Résultats détaillés par jeu ci-dessous, sans moyenne entre jeux.</p><?php } ?></section>
<section class="admin-section"><h2>Détail par jeu</h2><p>Les moyennes brutes décrivent les points obtenus sur une même échelle. Elles ne mesurent ni l’acquisition d’une compétence ni une progression.</p>
<?php if (!$rapport['jeux']) { ?><p>Aucun jeu pratiqué.</p><?php } else { ?><div class="gx-tableau-cadre"><table class="gx-tableau"><thead><tr><th scope="col">Jeu / lien explicite</th><th scope="col">Parties et scores</th><th scope="col">Dernier résultat</th></tr></thead><tbody><?php foreach ($rapport['jeux'] as $cle => $j) { ?><tr><td><?php echo fgH($j['titre']); ?><p><?php echo fgH(implode(' ; ', $j['references']) ?: 'Non relié à une compétence'); ?></p><?php if ($estEnseignant) { ?><form method="post" class="fg-rapport-supprimer" onsubmit="return confirm(<?php echo fgH(json_encode('Supprimer définitivement '.$j['parties'].' partie(s) de « '.$j['titre'].' » pour '.$identite['prenom'].' '.$identite['nom'].' sur cette période ? Cette action est irréversible.', JSON_UNESCAPED_UNICODE)); ?>);"><input type="hidden" name="csrf" value="<?php echo fgH($_SESSION['fastgames_csrf']); ?>"><input type="hidden" name="action" value="supprimer-jeu"><input type="hidden" name="jeu" value="<?php echo fgH($cle); ?>"><input type="hidden" name="parties" value="<?php echo (int)$j['parties']; ?>"><button class="fg-bouton fg-bouton-secondaire" type="submit" aria-label="<?php echo fgH('Supprimer les '.$j['parties'].' partie(s) de « '.$j['titre'].' »'); ?>">Supprimer ces parties</button></form><?php } ?></td><td class="fg-rapport-texte"><?php echo fgH(fgSeriesRapport($j)); ?></td><td><?php echo fgH(fgScoreRapport($j['dernier']).' · '.$j['dernier']['date']); ?></td></tr><?php } ?></tbody></table></div><?php } ?></section>
<section class="admin-section"><h2>Jeux transversaux / activités non reliées à une compétence</h2><?php if (!$rapport['sans_attribution']) { ?><p>Aucune activité dans cette catégorie sur la période.</p><?php } ?><?php foreach ($rapport['sans_attribution'] as $j) { ?><h3><?php echo fgH($j['titre']); ?></h3><p><?php echo $j['parties']; ?> partie(s) · <?php echo fgH(implode(' ; ', array_keys($j['motifs']))); ?></p><?php } ?></section>
<?php if (trim($commentaire['commentaire']) !== '') { ?><section class="admin-section"><h2>Commentaire de l’enseignant</h2><p class="fg-rapport-texte"><?php echo fgH($commentaire['commentaire']); ?></p></section><?php } ?>
</div>
<?php if ($estEnseignant && $disponible) { ?><form method="post" class="admin-section"><h2>Commentaire manuel</h2><input type="hidden" name="csrf" value="<?php echo fgH($_SESSION['fastgames_csrf']); ?>"><input type="hidden" name="action" value="commentaire"><input type="hidden" name="revision" value="<?php echo (int)$commentaire['revision']; ?>"><label class="gx-champ ed-etiquette">Commentaire facultatif<textarea name="commentaire" rows="6"><?php echo fgH($_POST['commentaire'] ?? $commentaire['commentaire']); ?></textarea></label><button class="fg-bouton fg-bouton-principal">Enregistrer le commentaire</button></form><?php } ?>
<?php } ?>
<?php if ($estEnseignant && $disponible) { ?><details class="admin-section fg-rapport-periodes"><summary>Configurer une période nommée</summary><p>Les dates d’une période enregistrée sont conservées. Pour d’autres dates, créez une nouvelle période. En cas de chevauchement, sélectionnez explicitement la période.</p><form method="post"><input type="hidden" name="csrf" value="<?php echo fgH($_SESSION['fastgames_csrf']); ?>"><input type="hidden" name="action" value="periode"><label class="gx-champ ed-etiquette">Nom<input name="libelle" maxlength="100" required></label><label class="gx-champ ed-etiquette">Du<input type="date" name="debut" required></label><label class="gx-champ ed-etiquette">Au, inclus<input type="date" name="fin" required></label><button class="fg-bouton fg-bouton-principal">Ajouter la période</button></form></details><?php } ?>
<?php require __DIR__.'/footer.php'; ?>
