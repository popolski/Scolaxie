<?php
require_once __DIR__.'/rapport.php';
require_once __DIR__.'/liens-competences.php';
$calendrier = dirname(__DIR__, 2).'/fasteval/utils/periodes-bulletin.php';
require_once is_file($calendrier) ? $calendrier : dirname(__DIR__, 2).'/staging-fasteval-v2/utils/periodes-bulletin.php';

function fgEleveRapport(PDO $db, int $enseignant, int $eleve): array
{
    if ($enseignant <= 0 || $eleve <= 0) throw new DomainException('Élève non accessible.');
    $q = $db->prepare('SELECT id_eleve, prenom, nom FROM classe WHERE id_enseignant=? AND id_eleve=?');
    $q->execute(array($enseignant, $eleve));
    $identite = $q->fetch(PDO::FETCH_ASSOC);
    if (!$identite) throw new DomainException('Élève non accessible.');
    return $identite;
}

/** Les tables optionnelles ne sont jamais créées depuis une requête utilisateur. */
function fgStockageRapportDisponible(PDO $db): bool
{
    try {
        $db->query('SELECT id_periode FROM fastgames_periodes_rapport LIMIT 0')->closeCursor();
        $db->query('SELECT revision FROM fastgames_commentaires_rapport LIMIT 0')->closeCursor();
        return true;
    } catch (PDOException $e) {
        if (fgTableAbsente($e)) return false;
        throw $e;
    }
}

function fgPeriodesRapport(PDO $db, int $enseignant): array
{
    $q = $db->prepare('SELECT * FROM fastgames_periodes_rapport WHERE id_enseignant=? ORDER BY date_debut DESC, id_periode DESC');
    $q->execute(array($enseignant));
    return $q->fetchAll(PDO::FETCH_ASSOC);
}

function fgChoisirPeriodeRapport(array $periodes, string $choix, DateTimeImmutable $jour): array
{
    $annee = feAnneeBulletin($jour);
    if ($choix === 'annee') return array_merge($annee, feBornesBulletin($annee['debut'], $annee['fin']));
    $trouvees = array_filter($periodes, static fn($p) => $choix === 'courante'
        ? $p['date_debut'] <= $jour->format('Y-m-d') && $p['date_fin'] >= $jour->format('Y-m-d')
        : (string)$p['id_periode'] === $choix);
    if (count($trouvees) !== 1) throw new InvalidArgumentException($choix === 'courante' ? 'Sélectionnez une période : aucune période courante unique.' : 'Période inconnue.');
    $p = reset($trouvees);
    return array_merge($p, feBornesBulletin($p['date_debut'], $p['date_fin']));
}

/**
 * Supprime définitivement les parties d'un jeu pour un élève de la classe, dans les bornes du
 * rapport affiché : cas de l'élève qui s'est trompé de jeu (décision de le responsable technique, 15/09/2026).
 * Le jeu est reconnu par la qualification même du rapport, et le nombre affiché doit
 * correspondre : une partie arrivée après l'affichage n'est jamais effacée à l'aveugle.
 */
function fgSupprimerPartiesRapport(PDO $db, int $enseignant, int $eleve, array $periode, string $activite, int $attendues, array $banques): int
{
    fgEleveRapport($db, $enseignant, $eleve);
    $q = $db->prepare('SELECT * FROM fastgames_resultats WHERE id_enseignant=? AND id_eleve=? AND date_enr>=? AND date_enr<?');
    $q->execute(array($enseignant, $eleve, $periode['sql_debut'], $periode['sql_fin']));
    $ids = array();
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (fgQualifierResultat($r, $banques, array())['activite'] === $activite) $ids[] = (int)$r['id_resultat'];
    }
    if (!$ids) throw new DomainException('Aucune partie de ce jeu à supprimer sur cette période.');
    if (count($ids) !== $attendues) throw new DomainException('Les parties de ce jeu ont changé depuis l’affichage. Rechargez le rapport avant de supprimer.');
    $d = $db->prepare('DELETE FROM fastgames_resultats WHERE id_enseignant=? AND id_eleve=? AND id_resultat IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
    $d->execute(array_merge(array($enseignant, $eleve), $ids));
    return $d->rowCount();
}

function fgLireRapport(PDO $db, int $enseignant, int $eleve, array $periode, array $banques): array
{
    fgEleveRapport($db, $enseignant, $eleve);
    $q = $db->prepare('SELECT * FROM fastgames_resultats WHERE id_enseignant=? AND id_eleve=? AND date_enr>=? AND date_enr<? ORDER BY date_enr, id_resultat');
    $q->execute(array($enseignant, $eleve, $periode['sql_debut'], $periode['sql_fin']));
    $resultats = $q->fetchAll(PDO::FETCH_ASSOC);
    return fgConstruireRapport($resultats, $banques, fgReferencesRapport($db, $resultats), fgIdsLiens(fgLiensCompetences($db)));
}

function fgCommentaireRapport(PDO $db, int $enseignant, int $eleve, array $periode): array
{
    fgEleveRapport($db, $enseignant, $eleve);
    $q = $db->prepare('SELECT commentaire, revision FROM fastgames_commentaires_rapport WHERE id_enseignant=? AND id_eleve=? AND date_debut=? AND date_fin=?');
    $q->execute(array($enseignant, $eleve, $periode['debut'], $periode['fin']));
    return $q->fetch(PDO::FETCH_ASSOC) ?: array('commentaire' => '', 'revision' => 0);
}

function fgSauverCommentaireRapport(PDO $db, int $enseignant, int $eleve, array $periode, string $texte, int $revision): void
{
    fgEleveRapport($db, $enseignant, $eleve);
    if (!mb_check_encoding($texte, 'UTF-8') || strlen($texte) > 65000) throw new InvalidArgumentException('Commentaire trop long (65 000 octets maximum) ou encodage invalide. Aucun texte enregistré.');
    $dates = feBornesBulletin($periode['debut'], $periode['fin']);
    $cle = array($enseignant, $eleve, $dates['debut'], $dates['fin']);
    $maintenant = date('Y-m-d H:i:s');
    try {
        if ($revision === 0) {
            $q = $db->prepare('INSERT INTO fastgames_commentaires_rapport (id_enseignant,id_eleve,date_debut,date_fin,commentaire,revision,date_creation,date_modification) VALUES (?,?,?,?,?,1,?,?)');
            $q->execute(array_merge($cle, array(trim($texte), $maintenant, $maintenant)));
        } else {
            $q = $db->prepare('UPDATE fastgames_commentaires_rapport SET commentaire=?,revision=revision+1,date_modification=? WHERE id_enseignant=? AND id_eleve=? AND date_debut=? AND date_fin=? AND revision=?');
            $q->execute(array_merge(array(trim($texte), $maintenant), $cle, array($revision)));
            if ($q->rowCount() !== 1) throw new DomainException('Le commentaire a changé dans un autre onglet. Rechargez avant de modifier.');
        }
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') throw new DomainException('Un commentaire a été enregistré entre-temps. Rechargez avant de modifier.');
        throw $e;
    }
}

/** Les bornes restent immuables : une nouvelle plage ne déplace aucun commentaire. */
function fgAjouterPeriodeRapport(PDO $db, int $enseignant, string $libelle, string $debut, string $fin): void
{
    if ($enseignant <= 0 || trim($libelle) === '' || mb_strlen($libelle) > 100) throw new InvalidArgumentException('Nom de période requis, 100 caractères maximum.');
    feBornesBulletin($debut, $fin);
    $q = $db->prepare('INSERT INTO fastgames_periodes_rapport (id_enseignant,libelle,date_debut,date_fin,revision) VALUES (?,?,?,?,1)');
    $q->execute(array($enseignant, trim($libelle), $debut, $fin));
}
