<?php
/**
 * L'ANCIEN CATALOGUE, garde a part depuis le 01/09/2026.
 *
 * catalogue.php est devenu le catalogue par competence (voir
 * includes/catalogue-competences.php). Ce fichier-ci reste le seul chemin
 * vers les huit generateurs de calcul et de conjugaison historiques -
 * additions, soustractions, tables, conjugaison au present... - tant que
 * Mathematiques et Francais n'ont pas ete qualifies competence par
 * competence comme Histoire-Geographie et Sciences l'ont ete.
 *
 * POURQUOI NE PAS LES AVOIR FAIT DISPARAITRE. fgTypeGenerateur() devine le
 * jeu a partir de mots trouves dans l'intitule, et cette detection s'est
 * revelee fausse dans plus d'un cas sur trois lors de la mesure du
 * 31/08/2026 - y compris cote calcul : « Decrire un cube, un pave... SOMMET
 * et arete » tombait sur des additions, « Comparer des fractions... »
 * tombait sur un generateur qui ne connait que les entiers. Les retirer
 * purement et simplement aurait rendu injoignables des jeux que l’enseignante a
 * deja testes ; les integrer tels quels au nouveau catalogue aurait ete
 * les faire passer pour vetes alors qu'ils ne le sont pas. Ce fichier est
 * le compromis : disponible, mais explicitement a part.
 */
require_once __DIR__ . '/includes/bootstrap.php';
// RESERVEE AUX ENSEIGNANTS, comme studio.php et couverture-programme.php.
// Cette page annonce elle-meme « Ancienne selection, en transition [...] pas
// encore verifies competence par competence », et son seul lien vient d'une
// page enseignante. Elle restait pourtant ouverte a un eleve qui tapait
// l'adresse, alors que les deux autres pages enseignantes redirigeaient
// (FG-AUDIT-016). Arbitrage de le responsable technique, 10/09/2026.
if (!fgEstEnseignant()) {
    header('Location: index.php');
    exit;
}
require_once __DIR__ . '/includes/generateurs.php';
require_once __DIR__ . '/includes/catalogue-mini-jeux.php';

$programme = fgProgrammeChoisi($_GET['programme'] ?? null);
$catalogue = fgCatalogueMiniJeux();
// COMBIEN DE REFERENCES CHAQUE CATEGORIE PEUT REELLEMENT SERVIR. Les cinq
// tuiles etaient affichees quoi qu'il arrive : celles qui ne trouvaient aucune
// reference compatible menaient a « indisponible » apres le clic, sans que
// rien ne l'annonce avant (FG-AUDIT-026). Le catalogue par competence, lui,
// grise deja ce qui ne se joue pas ; on reprend le meme parti.
$dbGenerateurs = bdd::connexion((string)$_SESSION['bdd']);
$referencesParCategorie = array();
foreach ($catalogue as $themeCompte => $domaineCompte) {
    foreach ($domaineCompte['categories'] as $categorieCompte => $_configuration) {
        if (!empty($_configuration['hors_offre'])) {
            continue;
        }
        $referencesParCategorie[$themeCompte . '|' . $categorieCompte] =
            fgChoisirReferenceCategorie($dbGenerateurs, $programme, $themeCompte, $categorieCompte) !== null;
    }
}
$fgTitre = 'Mini-jeux (générateurs)';
$fgPage = 'generateurs';
require __DIR__ . '/includes/header.php';
?>
<p class="fg-message-info"><strong>Ancienne sélection, en transition.</strong><span>Ces mini-jeux sont produits par une règle à partir de mots-clés, pas encore vérifiés compétence par compétence. <a href="catalogue.php">Voir le nouveau catalogue →</a></span></p>
<div class="fg-generateurs"><section class="fg-generateurs-intro gx-ligne-titre">
    <div>
        <span class="fg-pastille fg-pastille-info">Mini-jeux <?php echo htmlspecialchars(fgNiveauChoisi() === "tous" ? "CE1 et CE2" : fgNiveauChoisi()); ?></span>
        <h1>Générateurs historiques</h1>
        <p>Choisis un programme, une matière puis une catégorie simple. Fast Games sélectionne ensuite une référence compatible dans Fast Éval.</p>
    </div>
    <form method="get" class="fg-selecteur-programme">
        <label for="programme">Programme</label>
        <select id="programme" name="programme" onchange="this.form.submit()">
            <option value="2015"<?php echo $programme === '2015' ? ' selected' : ''; ?>>Programmes 2015</option>
            <option value="2026"<?php echo $programme === '2026' ? ' selected' : ''; ?>>Programmes 2026/2027</option>
        </select>
        <noscript><button class="fg-bouton fg-bouton-secondaire" type="submit">Afficher</button></noscript>
    </form>
</section>

<?php if (!empty($_GET['indisponible'])) { ?>
<div class="fg-message-info"><strong>Cette catégorie n’a pas encore de générateur compatible pour ce programme.</strong><span>Choisis une autre catégorie ou change de programme.</span></div>
<?php } ?>

<div class="fg-domaines">
<?php foreach ($catalogue as $theme => $domaine) { ?>
    <section class="fg-domaine fg-domaine-<?php echo fgH($theme); ?>">
        <header>
            <span class="fg-surtitre">Grand thème</span>
            <h2><?php echo fgH($domaine['titre']); ?></h2>
            <p><?php echo fgH($domaine['description']); ?></p>
        </header>
        <div class="fg-categories-jeux">
        <?php foreach ($domaine['categories'] as $categorie => $configuration) {
            // Une categorie retiree de l'offre (voir catalogue-mini-jeux.php)
            // n'a plus de tuile ; ses adresses restent valides pour l'historique.
            if (!empty($configuration['hors_offre'])) {
                continue;
            }
            // Une categorie sans reference compatible reste VISIBLE mais n'est
            // plus cliquable : la retirer laisserait croire qu'elle n'existe
            // pas, la laisser cliquable menait a « indisponible » apres coup.
            if (empty($referencesParCategorie[$theme . '|' . $categorie])) { ?>
            <span class="fg-categorie-jeu est-indisponible">
                <span class="fg-icone fg-icone-<?php echo fgH($configuration['icone']); ?>" aria-hidden="true"></span>
                <span><strong><?php echo fgH($configuration['titre']); ?></strong><small>Aucune compétence compatible dans ce programme.</small></span>
            </span>
            <?php continue; } ?>
            <a class="fg-categorie-jeu" href="jeu.php?programme=<?php echo fgH($programme); ?>&amp;theme=<?php echo fgH($theme); ?>&amp;categorie=<?php echo fgH($categorie); ?><?php echo fgEstEnseignant() ? '&amp;apercu=eleve' : ''; ?>">
                <span class="fg-icone fg-icone-<?php echo fgH($configuration['icone']); ?>" aria-hidden="true"></span>
                <span><strong><?php echo fgH($configuration['titre']); ?></strong><small><?php echo fgH($configuration['description']); ?></small></span>
                <b aria-hidden="true">›</b>
            </a>
        <?php } ?>
        </div>
    </section>
<?php } ?>
</div>

</div><p class="fg-note-referentiel">Les intitulés détaillés restent dans Fast Éval. Les générateurs ci-dessus conservent leurs règles de sélection existantes.</p>
<?php require __DIR__ . '/includes/footer.php'; ?>
