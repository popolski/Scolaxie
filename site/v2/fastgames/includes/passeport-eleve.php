<?php
require_once __DIR__ . '/rapport-stockage.php';
require_once __DIR__ . '/catalogue-competences.php';
require_once __DIR__ . '/helpers.php';

/** La même offre que le catalogue, lue sans déclencher son relevé de places. */
function fgDestinationsPasseport(PDO $db, array $banques, string $classe): array
{
    $niveaux = strtoupper(trim($classe)) === 'CE1' ? array('CE1')
        : (strtoupper(trim($classe)) === 'CE2' ? array('CE1', 'CE2') : array());
    $visibles = array();
    $q = $db->query("SELECT id_comp, matiere, niveau FROM comp_type WHERE commentaire IS NOT NULL AND commentaire!='' AND archivee=0");
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $reference) {
        if (fgMatiereRetenue((string)$reference['matiere']) === null) continue;
        $niveau = (string)$reference['niveau'];
        if ($niveaux && $niveau !== '' && !array_intersect($niveaux, explode(',', $niveau))) continue;
        foreach ($banques as $cle => $banque) {
            if (empty($banque['hors_offre']) && in_array((int)$reference['id_comp'], $banque['competences'], true)) {
                $visibles[$cle] = true;
            }
        }
    }

    // Une banque sans compétence garde sa place uniquement si le catalogue la connaît déjà.
    $offertes = array_filter($banques, static fn($banque) => empty($banque['hors_offre']));
    $places = fgPlacesBanques($db);
    foreach (fgBanquesSansCompetence($db, $offertes) as $cle) {
        if (isset($places[$cle]) && in_array($places[$cle]['matiere'], fgMatieresCatalogue(), true)) {
            $visibles[$cle] = true;
        }
    }

    $liens = array();
    foreach (array_keys($visibles) as $cle) {
        $liens['banque:' . $cle] = 'jeu.php?banque=' . rawurlencode($cle) . '&apercu=eleve';
    }
    foreach (require dirname(__DIR__) . '/data/jeux-speciaux.php' as $speciaux) {
        foreach ($speciaux as $special) {
            $cle = (string)$special['special'];
            $liens['special:' . $cle] = 'jeu.php?special=' . rawurlencode($cle) . '&apercu=eleve';
        }
    }
    return $liens;
}

/** Les faits viennent des mêmes parties et de la même qualification que le rapport. */
function fgDonneesPasseport(array $resultats, array $banques, array $references, array $liens = array(), array $destinations = array()): array
{
    $rapport = fgConstruireRapport($resultats, $banques, $references, $liens);
    usort($resultats, static fn($a, $b) => strcmp($a['date_enr'], $b['date_enr']) ?: ((int)($a['id_resultat'] ?? 0) <=> (int)($b['id_resultat'] ?? 0)));
    $jeux = array();
    $historique = array();
    $jours = array();
    foreach ($resultats as $r) {
        $qualification = fgQualifierResultat($r, $banques, $references, $liens);
        $cle = $qualification['activite'];
        $score = (int)$r['score'];
        $total = (int)$r['total'];
        $valide = is_numeric($r['score']) && is_numeric($r['total'])
            && $r['total'] > 0 && $r['score'] >= 0 && $r['score'] <= $r['total'];
        $observation = array('titre' => $qualification['titre'], 'score' => $score,
            'total' => $total, 'valide' => $valide, 'date' => $r['date_enr'],
            'palier' => fgPalierConnu($r));
        $historique[] = $observation;
        $jours[substr($r['date_enr'], 0, 10)] = true;
        if (!isset($jeux[$cle])) {
            $jeux[$cle] = array('cle' => $cle, 'titre' => $qualification['titre'],
                'parties' => 0, 'sans_erreur' => 0, 'parfaite' => null,
                'meilleure' => null, 'dernier' => null, 'href' => $destinations[$cle] ?? null);
        }
        $jeu =& $jeux[$cle];
        $jeu['parties']++;
        $jeu['dernier'] = $observation;
        if ($valide) {
            if ($score === $total) {
                $jeu['sans_erreur']++;
                $jeu['parfaite'] = $observation;
            }
            $meilleure = $jeu['meilleure'];
            if ($meilleure === null || $score / $total > $meilleure['score'] / $meilleure['total']) {
                $jeu['meilleure'] = $observation;
            }
        }
        unset($jeu);
    }
    $jeux = array_values($jeux);
    usort($jeux, static fn($a, $b) => strcmp($b['dernier']['date'], $a['dernier']['date']) ?: strcmp($a['cle'], $b['cle']));
    $jours = array_keys($jours);
    rsort($jours, SORT_STRING);
    return array(
        'identite' => array('prenom' => '', 'classe' => ''),
        'faits' => array('parties' => $rapport['parties'], 'jeux' => count($rapport['jeux']), 'premiere' => $rapport['premiere']),
        'derniers' => array_slice($jeux, 0, 4),
        'reussites' => $jeux,
        'parcours' => array_slice($jours, 0, 4),
        'historique' => array_reverse($historique),
        'commentaire' => '',
        'matieres' => fgMatieresCatalogue(),
    );
}

/** Une entrée pour la page élève et l'aperçu enseignant, sans écriture métier. */
function fgChargerPasseport(PDO $db, int $enseignant, int $eleve, DateTimeImmutable $jour, array $banques): array
{
    if ($enseignant <= 0 || $eleve <= 0) throw new DomainException('Élève non accessible.');
    $q = $db->prepare('SELECT c.id_eleve, c.prenom, e.classe1 FROM classe c JOIN enseignant e ON e.id_enseignant=c.id_enseignant WHERE c.id_enseignant=? AND c.id_eleve=?');
    $q->execute(array($enseignant, $eleve));
    $identite = $q->fetch(PDO::FETCH_ASSOC);
    if (!$identite) throw new DomainException('Élève non accessible.');
    $periode = fgChoisirPeriodeRapport(array(), 'annee', $jour);
    $q = $db->prepare('SELECT * FROM fastgames_resultats WHERE id_enseignant=? AND id_eleve=? AND date_enr>=? AND date_enr<? ORDER BY date_enr, id_resultat');
    $q->execute(array($enseignant, $eleve, $periode['sql_debut'], $periode['sql_fin']));
    $resultats = $q->fetchAll(PDO::FETCH_ASSOC);
    $references = fgReferencesRapport($db, $resultats);
    $liens = fgIdsLiens(fgLiensCompetences($db));
    $destinations = fgDestinationsPasseport($db, $banques, (string)$identite['classe1']);
    $donnees = fgDonneesPasseport($resultats, $banques, $references, $liens, $destinations);
    $donnees['identite'] = array('prenom' => (string)$identite['prenom'], 'classe' => (string)$identite['classe1']);
    if (fgStockageRapportDisponible($db)) {
        $donnees['commentaire'] = (string)fgCommentaireRapport($db, $enseignant, $eleve, $periode)['commentaire'];
    }
    return $donnees;
}

function fgDatePasseport(string $date): string
{
    return substr($date, 8, 2) . '/' . substr($date, 5, 2) . '/' . substr($date, 0, 4);
}

/** Le contenu central reste strictement identique dans les deux contextes. */
function fgRenduPasseport(array $donnees): string
{
    $identite = $donnees['identite'];
    $faits = $donnees['faits'];
    ob_start();
    ?>
<div class="fg-passeport-eleve">
    <section class="fg-passeport-identite" aria-labelledby="fg-passeport-nom">
        <span class="fg-passeport-initiale" aria-hidden="true"><?php echo fgH(mb_substr($identite['prenom'] ?: '•', 0, 1)); ?></span>
        <div><p class="fg-passeport-surtitre">Mon passeport</p><h2 id="fg-passeport-nom"><?php echo fgH($identite['prenom'] ?: 'Mon parcours'); ?></h2><?php if (trim($identite['classe']) !== '') { ?><p><?php echo fgH($identite['classe']); ?></p><?php } ?></div>
        <?php if ($faits['parties']) { ?><dl class="fg-passeport-faits"><div><dt>Parties jouées</dt><dd><?php echo (int)$faits['parties']; ?></dd></div><div><dt>Jeux différents</dt><dd><?php echo (int)$faits['jeux']; ?></dd></div><div><dt>Première partie</dt><dd><?php echo fgH(fgDatePasseport($faits['premiere'])); ?></dd></div></dl><?php } ?>
    </section>
    <?php if (!$faits['parties']) { ?><section class="fg-passeport-section"><h2>À vous de jouer !</h2><p>Aucune partie enregistrée cette année. Choisissez un jeu pour commencer.</p><a class="fg-bouton fg-bouton-principal" href="catalogue.php">Choisir un jeu</a></section><?php } else { ?>
    <section class="fg-passeport-section" aria-labelledby="fg-passeport-recents"><h2 id="fg-passeport-recents">Mes derniers jeux</h2><div class="fg-passeport-recents">
    <?php foreach ($donnees['derniers'] as $index => $jeu) { $dernier = $jeu['dernier']; ?><article class="fg-passeport-jeu<?php echo $index === 0 ? ' est-recent' : ''; ?>"><h3><?php echo fgH($jeu['titre']); ?></h3><p><?php echo $dernier['valide'] ? fgH($dernier['score'] . '/' . $dernier['total']) : 'Résultat non exploitable'; ?> · <?php echo fgH(fgDatePasseport($dernier['date'])); ?></p><p><?php echo (int)$jeu['parties']; ?> partie<?php echo $jeu['parties'] > 1 ? 's' : ''; ?> jouée<?php echo $jeu['parties'] > 1 ? 's' : ''; ?></p><?php if ($dernier['palier'] !== null) { ?><p>Niveau de départ : <?php echo (int)$dernier['palier']; ?></p><?php } ?><?php if ($jeu['href']) { ?><a class="fg-bouton fg-bouton-secondaire" href="<?php echo fgH($jeu['href']); ?>">Rejouer</a><?php } ?></article><?php } ?>
    </div></section>
    <section class="fg-passeport-section" aria-labelledby="fg-passeport-reussites"><h2 id="fg-passeport-reussites">Mes meilleurs résultats</h2><ul class="fg-passeport-reussites">
    <?php foreach ($donnees['reussites'] as $jeu) { ?><li><strong><?php echo fgH($jeu['titre']); ?></strong><span><?php if ($jeu['sans_erreur']) { $parfaite = $jeu['parfaite']; echo fgH($parfaite['score'] . '/' . $parfaite['total'] . ' · Partie terminée sans erreur · ' . $jeu['sans_erreur'] . ' fois'); } elseif ($jeu['meilleure']) { $meilleure = $jeu['meilleure']; echo fgH('Meilleur résultat : ' . $meilleure['score'] . '/' . $meilleure['total']); } else { echo 'Aucun résultat exploitable'; } ?></span></li><?php } ?>
    </ul></section>
    <section class="fg-passeport-section fg-passeport-parcours" aria-labelledby="fg-passeport-parcours"><h2 id="fg-passeport-parcours">Mon parcours récent</h2><ol class="fg-passeport-jours"><?php foreach ($donnees['parcours'] as $jour) { ?><li><?php echo fgH(fgDatePasseport($jour)); ?></li><?php } ?></ol>
    <details class="fg-passeport-historique"><summary>Voir toutes mes parties</summary><ol><?php foreach ($donnees['historique'] as $partie) { ?><li><strong><?php echo fgH($partie['titre']); ?></strong><span><?php echo $partie['valide'] ? fgH($partie['score'] . '/' . $partie['total']) : 'Résultat non exploitable'; ?> · <?php echo fgH(fgDatePasseport($partie['date'])); ?><?php if ($partie['palier'] !== null) { ?> · Niveau de départ : <?php echo (int)$partie['palier']; ?><?php } ?></span></li><?php } ?></ol></details></section>
    <?php } ?>
    <?php if (trim($donnees['commentaire']) !== '') { ?><section class="fg-passeport-section"><h2>Un mot de mon enseignant</h2><p class="fg-passeport-commentaire"><?php echo nl2br(fgH($donnees['commentaire'])); ?></p></section><?php } ?>
    <section class="fg-passeport-section"><h2>Explorer les matières</h2><div class="fg-passeport-liens-matieres"><?php foreach ($donnees['matieres'] as $matiere) { ?><a href="catalogue.php?matiere=<?php echo fgH(rawurlencode(fgSlugMatiere($matiere))); ?>"><?php echo fgH($matiere); ?></a><?php } ?></div></section>
</div>
    <?php
    return ob_get_clean();
}
