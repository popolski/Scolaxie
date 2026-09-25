<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (!fgEstEnseignant()) {
    header('Location: index.php');
    exit;
}
require_once __DIR__ . '/includes/generateurs.php';
require_once __DIR__ . '/includes/catalogue-mini-jeux.php';
require_once __DIR__ . '/includes/resultats.php';
require_once __DIR__ . '/includes/suivi.php';
require_once __DIR__ . '/includes/catalogue-competences.php';

$dbStudio = bdd::connexion((string)$_SESSION['bdd']);
$idEnseignant = (int)$_SESSION['id_enseignant'];
$fgErreurSuppression = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'supprimer-statistiques') {
    if (!hash_equals((string)$_SESSION['fastgames_csrf'], (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Session expirée. Rechargez la page.');
    }
    $supprimes = fgSupprimerResultats($dbStudio, $idEnseignant);
    if ($supprimes !== null) {
        header('Location: studio.php?statistiques=supprimees#resultats-classe');
        exit;
    }
    $fgErreurSuppression = true;
}


// La periode est choisie par l'enseignante et vaut pour TOUS les chiffres de
// la page : un ecran qui melangerait deux fenetres de temps serait un piege.
$fgJours = isset($_GET['jours']) ? (int)$_GET['jours'] : 30;
$fgJours = in_array($fgJours, array(30, 90, 365), true) ? $fgJours : 30;
$tableau = fgTableauDeBord($dbStudio, $idEnseignant, $fgJours);

$parCompetence = fgAjouterIntitules($dbStudio, fgVueParCompetence($dbStudio, $idEnseignant, $fgJours));
$parEleve = fgVueParEleve($dbStudio, $idEnseignant, $fgJours);
$parJeuTransversal = fgVueParJeuTransversal($dbStudio, $idEnseignant, $fgJours);
$prenomsEleves = fgPrenomsEleves($dbStudio, $idEnseignant);
// Filtre de matiere, applique apres coup : la requete ne sait pas a quelle
// matiere appartient une competence, seul le referentiel le sait.
$fgMatiere = isset($_GET['matiere']) ? trim((string)$_GET['matiere']) : '';
$matieresVues = array();
foreach ($parCompetence as $c) {
    if ($c['matiere_reelle'] !== '') {
        $matieresVues[$c['matiere_reelle']] = true;
    }
}
ksort($matieresVues);
if ($fgMatiere !== '') {
    $parCompetence = array_values(array_filter(
        $parCompetence,
        static function (array $c) use ($fgMatiere): bool {
            return $c['matiere_reelle'] === $fgMatiere;
        }
    ));
}

/** Largeur d'un segment de jauge, en pourcentage du total. */
function fgPart(array $niveaux, string $niveau): float
{
    $total = array_sum($niveaux);
    return $total > 0 ? round($niveaux[$niveau] * 100 / $total, 1) : 0.0;
}

/** « hier », « il y a 3 jours » : une date brute se lit moins vite. */
function fgQuand(string $date): string
{
    $jours = (int)floor((time() - strtotime($date)) / 86400);
    if ($jours <= 0) {
        return "aujourd'hui";
    }
    if ($jours === 1) {
        return 'hier';
    }
    return 'il y a ' . $jours . ' jours';
}
$fgVue = (string)($_GET['vue'] ?? 'competences');
if (!in_array($fgVue, ['competences','transversaux','recents','eleves'], true)) $fgVue = 'competences';
$selection = null;
$cleDemandee = (string)($_GET['competence'] ?? '');
foreach ($parCompetence as $competence) {
    if ($selection === null || $cleDemandee === $competence['type_reference'].'|'.$competence['id_reference']) $selection = $competence;
    if ($cleDemandee === $competence['type_reference'].'|'.$competence['id_reference']) break;
}
$parametresSuivi = ['jours'=>$fgJours, 'matiere'=>$fgMatiere, 'vue'=>'competences'];
$parametresNavigation = ['jours'=>$fgJours];
if ($fgMatiere !== '') $parametresNavigation['matiere'] = $fgMatiere;
if ($cleDemandee !== '') $parametresNavigation['competence'] = $cleDemandee;
$banquesSuivi = fgBanques();
$fgTitre = 'Suivre la classe';
$fgPage = 'studio';
require __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="/v2/fastgames/assets/studio.css?v=20260924-lot7">
<div class="gx-ligne-titre"><div><p class="gx-ligne-titre-surtitre">Fast Games · espace enseignant</p><h1 class="gx-ligne-titre-bonjour">Suivre la classe</h1><p class="gx-ligne-titre-description">Sélectionnez une compétence pour préparer la prochaine activité.</p></div></div>
<?php if (($_GET['statistiques'] ?? '') === 'supprimees') { ?><p class="fg-message-info" role="status">Les statistiques Fast Games de la classe ont été supprimées.</p><?php } elseif ($fgErreurSuppression) { ?><p class="fg-message-info" role="alert">La suppression n’a pas pu être effectuée. Réessayez plus tard.</p><?php } ?>
<?php // Hors de la rangée flexible des filtres : le lien y réduisait les listes et tronquait leurs valeurs aux largeurs intermédiaires (720 px mesurés). ?>
<p class="gx-cartouche-actions"><a class="fg-bouton fg-bouton-secondaire" href="passeport.php">Rapports individuels</a><a class="fg-bouton fg-bouton-secondaire" href="liens-competences.php">Relier les jeux aux compétences</a></p>
<nav class="sv-onglets" aria-label="Vues du Studio">
<?php foreach (['competences'=>'Par compétence','transversaux'=>'Jeux transversaux','recents'=>'Parties récentes','eleves'=>'Par élève'] as $vue=>$libelle) { ?>
    <a href="studio.php?<?php echo fgH(http_build_query(['vue'=>$vue]+$parametresNavigation)); ?>"<?php echo $fgVue===$vue?' aria-current="page"':''; ?>><?php echo $libelle; ?></a>
<?php } ?>
</nav>
<nav class="sv-periodes" aria-label="Période glissante"><span>Période glissante</span>
<?php foreach ([30,90,365] as $jours) { ?>
    <a href="studio.php?<?php echo fgH(http_build_query(['vue'=>$fgVue,'jours'=>$jours]+array_diff_key($parametresNavigation,['jours'=>true]))); ?>"<?php echo $fgJours===$jours?' aria-current="page"':''; ?>><?php echo $jours; ?> derniers jours</a>
<?php } ?>
</nav>
<?php if ($fgVue === 'competences') { ?><form method="get" action="studio.php" class="sv-filtres">
    <input type="hidden" name="vue" value="competences"><input type="hidden" name="jours" value="<?php echo $fgJours; ?>">
    <label for="sv-matiere">Matière des compétences<select id="sv-matiere" name="matiere"><option value="">Toutes les matières</option><?php foreach (array_keys($matieresVues) as $matiere) { ?><option value="<?php echo fgH($matiere); ?>"<?php echo $fgMatiere===$matiere?' selected':''; ?>><?php echo fgH($matiere); ?></option><?php } ?></select></label>
    <button class="fg-bouton fg-bouton-secondaire" type="submit">Afficher</button>
</form><?php } ?>
<section id="resultats-classe" class="fg-suivi">
<?php if (!$tableau['disponible']) { ?><p class="fg-message-info" role="alert">Les résultats sont temporairement indisponibles. Les activités restent accessibles depuis le catalogue.</p><?php } ?>
<section class="fg-indicateurs" aria-label="Indicateurs de la classe sur la période">
    <article><span>Élèves actifs</span><strong><?php echo !$tableau['disponible']?'—':(int)$tableau['eleves_actifs'].' / '.(int)$tableau['eleves_classe']; ?></strong><small>sur <?php echo $fgJours; ?> jours</small></article>
    <article><span>Parties terminées</span><strong><?php echo !$tableau['disponible']?'—':(int)$tableau['quiz']; ?></strong><small>sur <?php echo $fgJours; ?> jours</small></article>
    <article><span>À accompagner</span><strong><?php echo !$tableau['disponible']?'—':(int)$tableau['alertes']; ?></strong><small>sous 50 % après deux parties</small></article>
    <article><span>Moyenne classe</span><strong><?php echo !$tableau['disponible'] || $tableau['moyenne']===null?'—':(int)$tableau['moyenne'].' %'; ?></strong><small>moyenne des résultats de toutes les parties de la période, tous jeux et élèves confondus</small></article>
</section>
<?php if ($fgVue === 'competences') { ?>
<div class="sv-atelier">
<section class="sv-competences" aria-label="Choisir une compétence">
    <h2>Compétences travaillées</h2>
    <?php if (!$parCompetence) { ?><p class="sv-vide">Aucune compétence jouée pour ces filtres. Les premiers résultats apparaîtront après une partie.</p><a class="fg-bouton fg-bouton-secondaire" href="catalogue.php">Choisir une activité</a><?php } ?>
    <?php foreach ($parCompetence as $c) {
        $cle = $c['type_reference'].'|'.$c['id_reference'];
        $active = $selection && $selection['type_reference'].'|'.$selection['id_reference'] === $cle;
    ?>
    <a class="sv-choix-competence" href="?<?php echo fgH(http_build_query($parametresSuivi + ['competence'=>$cle])); ?>#detail-competence"<?php echo $active?' aria-current="true"':''; ?>>
        <strong><?php echo fgH($c['libelle']); ?></strong><small><?php echo fgH($c['categorie']); ?> · <?php echo (int)$c['eleves']; ?> élèves</small>
        <span class="sv-jauge" aria-hidden="true"><?php foreach (fgNiveaux() as $niveau) { ?><i class="sv-t-<?php echo $niveau; ?>" style="width:<?php echo fgPart($c['niveaux'],$niveau); ?>%"></i><?php } ?></span>
        <span><?php echo (int)$c['niveaux']['reprendre']; ?> à reprendre · <?php echo (int)$c['niveaux']['fragile']; ?> fragiles</span>
    </a>
    <?php } ?>
</section>
<section class="sv-inspecteur" id="detail-competence" aria-labelledby="titre-detail">
    <?php if ($selection) { $suiviSelection=$selection; ?>
    <p class="fg-surtitre">Compétence sélectionnée</p><h2 id="titre-detail"><?php echo fgH($selection['libelle']); ?></h2>
    <?php require __DIR__.'/includes/detail-suivi.php'; ?>
    <?php
    $activite = null;
    foreach (fgCatalogueCompetences($dbStudio) as $categories) foreach ($categories as $lignes) foreach ($lignes as $ligne) {
        if ($ligne['type']===$selection['type_reference'] && $ligne['id']===$selection['id_reference'] && $ligne['etat']==='jouable') $activite=$ligne;
    }
    if ($activite) {
        $lienActivite = $activite['special'] !== '' ? 'jeu.php?special='.rawurlencode($activite['special']) : 'jeu.php?banque='.rawurlencode((string)$activite['banque']);
    ?><div class="sv-activite"><h3>Pour retravailler cette compétence</h3><a class="fg-bouton fg-bouton-principal" href="<?php echo fgH($lienActivite); ?>&amp;apercu=eleve"><?php echo fgH($banquesSuivi[$activite['banque']]['titre'] ?? 'Ouvrir l’activité'); ?> →</a></div>
    <?php } else { ?><p class="sv-sous">Aucune activité disponible pour cette compétence dans le catalogue actuel.</p><?php } ?>
    <?php } else { ?><h2 id="titre-detail">Le détail d’une compétence</h2><p>Les élèves et leurs derniers essais apparaîtront ici.</p><?php } ?>
</section></div>
<?php } elseif ($fgVue === 'transversaux') { ?>
<section class="sv-transversaux"><h2>Jeux transversaux</h2><p>Ces activités ont un suivi distinct des compétences du référentiel.</p>
<?php if (!$parJeuTransversal) { ?><p class="sv-vide">Aucune partie transversale sur cette période.</p><?php } ?>
<?php foreach ($parJeuTransversal as $suiviSelection) { ?><article class="sv-inspecteur"><h3><?php echo fgH($suiviSelection['libelle']); ?></h3><?php require __DIR__.'/includes/detail-suivi.php'; ?></article><?php } ?>
</section>
<?php } elseif ($fgVue === 'recents') { ?>
<section class="sv-recents"><h2>Parties récentes</h2><div class="gx-tableau-cadre"><table class="sv-table"><thead><tr><th scope="col">Élève</th><th scope="col">Activité</th><th scope="col">Résultat</th><th scope="col">Date</th></tr></thead><tbody>
<?php foreach ($tableau['recents'] as $ligne) { ?><tr><td data-label="Élève"><a class="sv-lien-eleve" href="passeport.php?eleve=<?php echo (int)$ligne['id_eleve']; ?>"><?php echo fgH(trim($ligne['prenom'].' '.$ligne['nom'])); ?></a></td><td data-label="Activité"><?php echo fgH($banquesSuivi[$ligne['categorie']]['titre'] ?? fgLibelleCategorie($ligne['theme'],$ligne['categorie'])); ?></td><td data-label="Résultat"><?php echo (int)$ligne['score'].' / '.(int)$ligne['total']; ?></td><td data-label="Date"><?php echo fgH(fgQuand($ligne['date_enr'])); ?></td></tr><?php } ?>
<?php if (!$tableau['recents']) { ?><tr><td colspan="4">Aucune partie enregistrée sur cette période.</td></tr><?php } ?>
</tbody></table></div></section>
<?php } else { ?>
<section class="sv-par-eleve"><h2>Suivi par élève</h2><div class="gx-tableau-cadre"><table class="sv-table"><thead><tr><th scope="col">Élève</th><th scope="col">Compétences</th><th scope="col">À reprendre</th><th scope="col">Fragiles</th><th scope="col">Maîtrisées ou à l’aise</th><th scope="col">Dernière partie</th></tr></thead><tbody>
<?php foreach ($parEleve as $e) { ?><tr><td data-label="Élève"><a class="sv-lien-eleve" href="passeport.php?eleve=<?php echo (int)$e['id_eleve']; ?>"><?php echo fgH(trim($e['prenom'].' '.$e['nom'])); ?></a></td><td data-label="Compétences"><?php echo (int)$e['competences']; ?></td><td data-label="À reprendre"><?php echo (int)$e['niveaux']['reprendre']; ?></td><td data-label="Fragiles"><?php echo (int)$e['niveaux']['fragile']; ?></td><td data-label="Maîtrisées ou à l’aise"><?php echo (int)$e['niveaux']['maitrise']+(int)$e['niveaux']['alaise']; ?></td><td data-label="Dernière partie"><?php echo fgH(fgQuand($e['dernier'])); ?></td></tr><?php } ?>
<?php if (!$parEleve) { ?><tr><td colspan="6">Aucun élève avec une partie sur cette période.</td></tr><?php } ?>
</tbody></table></div></section>
<?php } ?>
</section>
<details class="sv-section">
    <summary><span>Gestion des statistiques</span><small>Remise à zéro de la classe</small></summary>
    <div class="fg-carte">
        <p>Cette action efface tous les résultats Fast Games de votre classe.</p>
    <form method="post" action="studio.php" onsubmit="return confirm('Supprimer définitivement toutes les statistiques Fast Games de cette classe ?');">
        <input type="hidden" name="action" value="supprimer-statistiques">
        <input type="hidden" name="csrf" value="<?php echo fgH($_SESSION['fastgames_csrf']); ?>">
        <button class="fg-bouton fg-bouton-secondaire" type="submit">Remettre les statistiques à zéro</button>
    </form>
    </div>
</details>

<?php require __DIR__ . '/includes/footer.php'; ?>
