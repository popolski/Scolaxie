<?php
require_once __DIR__ . '/referentiel.php';

/*
 * LIENS JEU <-> COMPETENCE CHOISIS DANS FAST GAMES.
 *
 * Les banques portent leur liste de competences dans data/banques.php. Les trois
 * jeux speciaux n'en avaient aucune, et plus rien n'est devine par mots-cles
 * depuis FG-AUDIT-017 (10/09/2026). Decision de le responsable technique du 14/09/2026 : un
 * administrateur choisit pour chaque jeu UNE competence par niveau, commune a
 * toutes les classes ; les mots-cles ne font que proposer. Les generateurs
 * restent hors perimetre : leur page est enseignante et seules les parties
 * d'eleves sont enregistrees.
 *
 * Depuis le 15/09/2026, les banques aussi (cle « banque:<cle> ») : sans choix pour
 * un niveau, leur liste ecrite s'applique ; voir fgFusionnerLiensBanques().
 */

const FG_NIVEAUX_LIENS = array('CE1', 'CE2');

/** Cle de lien => titre : les trois jeux speciaux, puis les banques du programme ecrit. */
function fgJeuxReliables(): array
{
    require_once __DIR__ . '/mecaniques.php';
    $jeux = array('horloge' => 'L’horloge', 'boutique' => 'La petite boutique', 'compte-est-bon' => 'Le compte est bon');
    // Une banque hors offre (DEC-08) ne se propose plus a la liaison ; sa cle,
    // ses resultats et la reprise d'une partie ne passent pas par cette liste.
    foreach (fgBanquesProgramme() as $cle => $banque) {
        if (!empty($banque['hors_offre'])) continue;
        $jeux['banque:' . $cle] = (string)$banque['titre'];
    }
    return $jeux;
}

/** La banque designee par une cle de lien, telle qu'ecrite dans le programme ; null pour un jeu special. */
function fgBanqueDuLien(string $jeu): ?array
{
    if (!str_starts_with($jeu, 'banque:')) return null;
    require_once __DIR__ . '/mecaniques.php';
    return fgBanquesProgramme()[substr($jeu, 7)] ?? null;
}

/** Une table optionnelle absente se lit comme vide ; toute autre erreur remonte. */
function fgTableAbsente(PDOException $e): bool
{
    return in_array($e->getCode(), array('42S02', 'HY000'), true)
        && (str_contains($e->getMessage(), 'no such table') || (int)($e->errorInfo[1] ?? 0) === 1146);
}

/** jeu => niveau => ligne ; null tant que la table n'a pas été créée. */
function fgLiensCompetences(PDO $db): ?array
{
    try {
        $lignes = $db->query('SELECT jeu, niveau, id_competence, id_enseignant, date_validation FROM fastgames_liens_competences')->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        if (fgTableAbsente($e)) return null;
        throw $e;
    }
    $liens = array();
    foreach ($lignes as $ligne) $liens[(string)$ligne['jeu']][(string)$ligne['niveau']] = $ligne;
    return $liens;
}

/** Forme lue par la qualification : jeu => identifiants validés, tous niveaux confondus. */
function fgIdsLiens(?array $liens): array
{
    $ids = array();
    foreach ($liens ?? array() as $jeu => $parNiveau) {
        $ids[$jeu] = array_values(array_map(static fn($ligne) => (int)$ligne['id_competence'], $parNiveau));
    }
    return $ids;
}

/** La compétence à enregistrer au lancement : celle du niveau de la classe, relue avec le même filtre qu'une banque. */
function fgReferenceLienJeu(PDO $db, string $jeu): ?array
{
    $id = (int)(fgLiensCompetences($db)[$jeu][fgNiveauReferentiel()]['id_competence'] ?? 0);
    return $id > 0 ? fgTrouverReference($db, 'competence', $id) : null;
}

/** Compétences choisissables : les quatre matières du catalogue, visibles à ce niveau (un CE2 voit aussi le CE1). */
function fgCompetencesLiables(PDO $db, string $niveau): array
{
    if (!in_array($niveau, FG_NIVEAUX_LIENS, true)) return array();
    require_once __DIR__ . '/catalogue-competences.php';
    $niveaux = $niveau === 'CE2' ? array('CE1', 'CE2') : array('CE1');
    $configuration = fgConfigurationReferentiel('competence');
    $q = $db->prepare('SELECT id_comp AS id, commentaire AS libelle, matiere, categorie FROM comp_type WHERE ' . $configuration['filtre']
        . " AND (niveau=''" . str_repeat(' OR FIND_IN_SET(?, niveau)', count($niveaux)) . ') ORDER BY categorie, id_comp');
    $q->execute($niveaux);
    $competences = array();
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
        // Même règle que le catalogue : la base écrit « Mathématiques (programmes 2026/2027) »,
        // et seul le programme 2026/2027 est retenu.
        $matiere = fgMatiereRetenue((string)$ligne['matiere']);
        if ($matiere === null) continue;
        $competences[(int)$ligne['id']] = array('libelle' => fgNettoyerLibelle((string)$ligne['libelle']), 'categorie' => (string)$ligne['categorie'], 'matiere' => $matiere);
    }
    return $competences;
}

/**
 * Propositions à valider, jamais créditées sans choix : la couverture déjà écrite
 * du programme, puis des mots-clés, au mieux indicatifs (FG-AUDIT-017).
 */
function fgPropositionsLien(string $jeu, array $competences): array
{
    // Pour une banque, la proposition est sa liste ecrite : c'est elle qui s'applique sans choix.
    $banque = fgBanqueDuLien($jeu);
    if ($banque !== null) {
        return array_fill_keys(array_values(array_intersect(array_map('intval', $banque['competences']), array_keys($competences))), true);
    }
    require_once __DIR__ . '/generateurs.php';
    $couverture = require __DIR__ . '/../data/competences-jouables.php';
    $motsCles = array(
        'horloge' => array('heure', 'horloge', 'duree'),
        'boutique' => array('euro', 'monnaie', 'piece', 'billet'),
        'compte-est-bon' => array('calcul', 'addition', 'soustraction', 'multiplication', 'division'),
    );
    $propositions = array();
    foreach ($competences as $id => $competence) {
        if (($couverture[$id]['special'] ?? '') === $jeu) {
            $propositions[$id] = true;
            continue;
        }
        $texte = fgTexteComparable($competence['libelle']);
        foreach ($motsCles[$jeu] ?? array() as $mot) {
            if (str_contains($texte, $mot)) {
                $propositions[$id] = true;
                break;
            }
        }
    }
    return $propositions;
}

/** Remplace le lien d'un niveau, ou le retire quand $idCompetence vaut 0. */
function fgEnregistrerLienJeu(PDO $db, string $jeu, string $niveau, int $idCompetence, int $enseignant): void
{
    if (!isset(fgJeuxReliables()[$jeu]) || !in_array($niveau, FG_NIVEAUX_LIENS, true) || $enseignant <= 0) {
        throw new InvalidArgumentException('Jeu, niveau ou compte non reconnu.');
    }
    if ($idCompetence > 0 && !isset(fgCompetencesLiables($db, $niveau)[$idCompetence])) {
        throw new InvalidArgumentException('Compétence introuvable pour ce niveau.');
    }
    $db->beginTransaction();
    try {
        $db->prepare('DELETE FROM fastgames_liens_competences WHERE jeu=? AND niveau=?')->execute(array($jeu, $niveau));
        if ($idCompetence > 0) {
            $db->prepare('INSERT INTO fastgames_liens_competences (jeu, niveau, id_competence, id_enseignant, date_validation) VALUES (?, ?, ?, ?, ?)')
                ->execute(array($jeu, $niveau, $idCompetence, $enseignant, (new DateTimeImmutable('now', new DateTimeZone('Europe/Paris')))->format('Y-m-d H:i:s')));
            // Une cle tronquee par une colonne trop courte rattacherait le choix a un autre jeu.
            $relue = $db->prepare('SELECT COUNT(*) FROM fastgames_liens_competences WHERE jeu=? AND niveau=?');
            $relue->execute(array($jeu, $niveau));
            if ((int)$relue->fetchColumn() !== 1) throw new DomainException('Ce jeu ne peut pas encore être relié : la table des liens doit être élargie.');
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}
