<?php
/**
 * OU RANGER UN JEU QUAND SA COMPETENCE N'EXISTE PLUS.
 *
 * Le catalogue se construit a partir du REFERENTIEL : il parcourt comp_type et
 * accroche a chaque competence les banques qui la visent. Une competence
 * supprimee dans Fast Eval emportait donc son jeu hors du catalogue - alors que
 * la banque, elle, est intacte dans data/banques.php. le responsable technique, 09/09/2026 :
 * supprimer une competence ne doit pas supprimer le jeu.
 *
 * L'IDENTIFIANT NE REVIENT JAMAIS. comp_type attribue un id neuf a chaque
 * creation : quand l’enseignante recree sa categorie et ses competences, le 4146 de
 * la banque reste mort. Ce qu'elle retape, en revanche, c'est un NOM - la
 * matiere et la sous-rubrique. C'est le seul point d'accroche stable, donc
 * c'est sur lui que le rattachement se refait tout seul.
 *
 * D'OU UN RELEVE, PAS UNE DEVINETTE. Tant que la competence vit, le catalogue
 * voit deja la place de chaque banque : elle est enregistree ici. Le jour ou la
 * competence disparait, le jeu garde cette place et reste jouable. Le jour ou
 * une categorie de ce nom revient dans cette matiere, le jeu s'y retrouve sans
 * que personne n'ait rien saisi. Si elle revient sous un autre nom, le jeu
 * reste dans sa matiere, sous « A ranger », visible et jouable.
 *
 * Le rattachement par identifiant reste PRIORITAIRE : ce releve n'est qu'un
 * repli, consulte seulement quand plus aucune competence de la banque n'existe.
 */

/** La table du releve, creee au premier passage - meme facon de faire que fgAssurerTableResultats(). */
function fgAssurerTablePlacesBanques(PDO $db): bool
{
    try {
        $db->query('SELECT 1 FROM fastgames_places_banques LIMIT 1')->closeCursor();
        return true;
    } catch (PDOException $exception) {
        try {
            $db->exec(
                'CREATE TABLE IF NOT EXISTS fastgames_places_banques (
                    banque VARCHAR(64) NOT NULL,
                    matiere VARCHAR(96) NOT NULL,
                    categorie VARCHAR(96) NOT NULL,
                    releve_le DATETIME NOT NULL,
                    PRIMARY KEY (banque)
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            return true;
        } catch (PDOException $creationException) {
            error_log('FastGames création table places banques impossible: ' . $creationException->getMessage());
            return false;
        }
    }
}

/** @return array cle de banque => array('matiere' => ..., 'categorie' => ...) */
function fgPlacesBanques(PDO $db): array
{
    $places = array();
    try {
        $requete = $db->query('SELECT banque, matiere, categorie FROM fastgames_places_banques');
        foreach ($requete->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
            $places[(string)$ligne['banque']] = array(
                'matiere' => (string)$ligne['matiere'],
                'categorie' => (string)$ligne['categorie'],
            );
        }
    } catch (PDOException $exception) {
        error_log('FastGames lecture des places impossible: ' . $exception->getMessage());
    }
    return $places;
}

/**
 * Enregistre les places vues, et SEULEMENT celles qui ont change.
 *
 * Le catalogue s'affiche des dizaines de fois par jour : reecrire les 89 lignes
 * a chaque affichage couterait une ecriture permanente pour une donnee qui ne
 * bouge presque jamais. En regime normal, cette fonction n'ecrit rien.
 *
 * @param array $vues     cle => array('matiere','categorie') relevees a l'instant
 * @param array $connues  ce que la table contient deja
 * @return int nombre de lignes reecrites
 */
function fgEnregistrerPlacesBanques(PDO $db, array $vues, array $connues): int
{
    $ecrites = 0;
    foreach ($vues as $cle => $place) {
        $connue = $connues[$cle] ?? null;
        if ($connue !== null && $connue['matiere'] === $place['matiere'] && $connue['categorie'] === $place['categorie']) {
            continue;
        }
        try {
            // DELETE puis INSERT plutot qu'un ON DUPLICATE KEY UPDATE : la
            // meme ecriture vaut alors sur MySQL et sur le SQLite des tests,
            // et le cas ne se presente qu'a la creation ou a un deplacement.
            $suppression = $db->prepare('DELETE FROM fastgames_places_banques WHERE banque = :banque');
            $suppression->execute(array(':banque' => $cle));
            $insertion = $db->prepare(
                'INSERT INTO fastgames_places_banques (banque, matiere, categorie, releve_le)
                 VALUES (:banque, :matiere, :categorie, :releve)'
            );
            $insertion->execute(array(
                ':banque' => $cle,
                ':matiere' => $place['matiere'],
                ':categorie' => $place['categorie'],
                ':releve' => date('Y-m-d H:i:s'),
            ));
            $ecrites++;
        } catch (PDOException $exception) {
            error_log('FastGames relevé de place impossible pour ' . $cle . ': ' . $exception->getMessage());
        }
    }
    return $ecrites;
}

/**
 * Les banques dont PLUS AUCUNE competence n'existe dans le referentiel.
 *
 * LE NIVEAU N'ENTRE PAS EN COMPTE, et c'est essentiel : fgTrouverReference()
 * filtre sur CE1/CE2, si bien qu'une banque de CE2 ne repond deja pas a un
 * eleve de CE1. La confondre avec une banque orpheline la rendrait jouable par
 * toute la classe et casserait le filtre de niveau. On demande donc ici
 * l'existence, pas la disponibilite.
 *
 * @return array cles de banques orphelines
 */
function fgBanquesSansCompetence(PDO $db, array $banques): array
{
    $orphelines = array();
    $existe = $db->prepare(
        "SELECT COUNT(*) FROM comp_type
          WHERE id_comp = :id AND archivee = 0 AND commentaire IS NOT NULL AND commentaire != ''"
    );
    foreach ($banques as $cle => $banque) {
        if (empty($banque['competences']) || !is_array($banque['competences'])) {
            continue;
        }
        $vivante = false;
        foreach ($banque['competences'] as $id) {
            $existe->execute(array(':id' => (int)$id));
            $trouvee = (int)$existe->fetchColumn() > 0;
            $existe->closeCursor();
            if ($trouvee) {
                $vivante = true;
                break;
            }
        }
        if (!$vivante) {
            $orphelines[] = (string)$cle;
        }
    }
    return $orphelines;
}

/** La rubrique d'accueil d'un jeu dont la sous-rubrique relevee n'existe plus. */
const FG_RUBRIQUE_A_RANGER = 'À ranger';

/**
 * Ajoute au catalogue les banques orphelines, a la place relevee de leur vivant.
 *
 * Si une categorie de ce nom existe encore dans la matiere - l’enseignante l'a
 * recreee, ou elle n'avait jamais disparu - le jeu la rejoint et rien ne se
 * voit. Sinon il attend dans « A ranger », dans sa matiere.
 *
 * Fonction PURE : elle ne lit ni base ni fichier, tout lui est donne. C'est ce
 * qui permet de la tester sans monter un referentiel complet.
 */
function fgRangerBanquesOrphelines(array $catalogue, array $orphelines, array $places, array $banques): array
{
    foreach ($orphelines as $cle) {
        $place = $places[$cle] ?? null;
        // Jamais relevee de son vivant : aucune matiere connue, donc aucune
        // place ou la mettre sans l'inventer. Le releve se fait des le premier
        // affichage du catalogue, bien avant qu'une competence ne soit retiree.
        if ($place === null || !isset($catalogue[$place['matiere']])) {
            continue;
        }
        $categorie = isset($catalogue[$place['matiere']][$place['categorie']])
            ? $place['categorie']
            : FG_RUBRIQUE_A_RANGER;
        $catalogue[$place['matiere']][$categorie][] = array(
            'id' => 0,
            'type' => 'banque',
            'libelle' => (string)($banques[$cle]['titre'] ?? $cle),
            'etat' => 'jouable',
            'banques' => array($cle),
            'banque' => $cle,
            'special' => '',
            'raison' => null,
        );
    }
    return $catalogue;
}
